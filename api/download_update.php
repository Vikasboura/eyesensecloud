<?php
/**
 * EyeSense Cloud Portal — Download Update API
 * Receives a list of files to package, creates a ZIP archive, and streams it.
 */

require_once __DIR__ . '/portal_config.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed. Use POST.'], 405);
}

// Decode JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['files']) || !is_array($input['files'])) {
    json_response(['error' => 'Invalid request payload. Expected JSON with a "files" array.'], 400);
}

$files = $input['files'];
if (empty($files)) {
    json_response(['error' => 'No files specified for download.'], 400);
}

// Check compression methods
$useZipArchive = class_exists('ZipArchive');
$usePowerShell = !$useZipArchive && (strncasecmp(PHP_OS, 'WIN', 3) === 0);

if (!$useZipArchive && !$usePowerShell) {
    json_response(['error' => 'PHP ZipArchive extension is not enabled on this server and fallback compression is unavailable.'], 500);
}

$updatesDir = __DIR__ . '/updates';
if (!is_dir($updatesDir)) {
    json_response(['error' => 'Updates source directory not found on server.'], 500);
}

// Create temporary zip archive filename
$tempZip = tempnam(sys_get_temp_dir(), 'es_upd_') . '.zip';
$addedCount = 0;

if ($useZipArchive) {
    $zip = new ZipArchive();
    if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        json_response(['error' => 'Failed to create zip archive.'], 500);
    }

    foreach ($files as $file) {
        $cleanFile = ltrim(str_replace(['\\', '../'], ['/', ''], $file), '/');
        $sourcePath = $updatesDir . '/' . $cleanFile;
        if (file_exists($sourcePath) && is_file($sourcePath)) {
            if ($zip->addFile($sourcePath, $cleanFile)) {
                $addedCount++;
            }
        }
    }
    $zip->close();
} else if ($usePowerShell) {
    // Stage files in a temporary directory to package them preserving directory structure
    $stageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'es_stage_' . uniqid();
    if (!mkdir($stageDir, 0777, true)) {
        json_response(['error' => 'Failed to create temporary staging directory for PowerShell compression.'], 500);
    }

    foreach ($files as $file) {
        $cleanFile = ltrim(str_replace(['\\', '../'], ['/', ''], $file), '/');
        $sourcePath = $updatesDir . '/' . $cleanFile;
        if (file_exists($sourcePath) && is_file($sourcePath)) {
            $destPath = $stageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanFile);
            $destSubdir = dirname($destPath);
            if (!is_dir($destSubdir)) {
                mkdir($destSubdir, 0777, true);
            }
            if (copy($sourcePath, $destPath)) {
                $addedCount++;
            }
        }
    }

    if ($addedCount > 0) {
        // Run PowerShell Compress-Archive
        $stagePathPowerShell = str_replace('/', '\\', $stageDir . '/*');
        $zipPathPowerShell = str_replace('/', '\\', $tempZip);
        
        $cmd = 'powershell.exe -NoProfile -NonInteractive -Command "Compress-Archive -Path ' 
             . escapeshellarg($stagePathPowerShell) . ' -DestinationPath ' 
             . escapeshellarg($zipPathPowerShell) . ' -Force"';
             
        exec($cmd, $output, $returnVar);

        // Recursive directory clean up
        $rrmdir = function ($dir) use (&$rrmdir) {
            if (is_dir($dir)) {
                $objects = scandir($dir);
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        $path = $dir . DIRECTORY_SEPARATOR . $object;
                        if (is_dir($path) && !is_link($path)) {
                            $rrmdir($path);
                        } else {
                            unlink($path);
                        }
                    }
                }
                rmdir($dir);
            }
        };
        $rrmdir($stageDir);

        if ($returnVar !== 0 || !file_exists($tempZip)) {
            json_response(['error' => 'PowerShell compression failed: ' . implode("\n", $output)], 500);
        }
    } else {
        rmdir($stageDir);
    }
}

if ($addedCount === 0) {
    @unlink($tempZip);
    json_response(['error' => 'None of the requested files were found in the server update repository.'], 404);
}

// Clear output buffers to ensure zip file isn't corrupted
if (ob_get_level()) {
    ob_end_clean();
}

// Stream the zip file to the client
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="eyesense_update.zip"');
header('Content-Length: ' . filesize($tempZip));
header('Pragma: no-cache');
header('Expires: 0');

readfile($tempZip);

// Clean up
@unlink($tempZip);
exit;
