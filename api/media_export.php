<?php
/**
 * EyeSense Cloud Portal — Media ZIP Export
 * Extracts base64 media from selected tables and bundles them into a .zip file.
 */
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/society_context_holder.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$session = getPortalSession();
$pdo = get_indsac_db();
SocietyContextHolder::initFromRequest($pdo);
$societyId = SocietyContextHolder::getCurrentSocietyId();

requirePermission($pdo, $session['client_id'], $session['role'], 'DOWNLOAD_FILES');

$cidStr = $_GET['client_id'] ?? '';
$tablesStr = $_GET['tables'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

if (empty($session['is_superadmin']) && $cidStr !== $session['client_id']) {
    die("Unauthorized client ID.");
}
$cid = $cidStr ?: $session['client_id'];
$tables = array_filter(explode(',', $tablesStr));

$allowedMediaTables = [
    'face_logs' => 'image_path',
    'vehicle_logs' => 'image_path',
    'snapshot_logs' => 'image_path',
    'video_logs' => 'video_path',
    'access_requests' => 'snapshot_path',
    'bulk_detection_alerts' => 'snapshot_path',
];

$tmpDir = sys_get_temp_dir() . '/export_media_' . uniqid();
mkdir($tmpDir, 0777, true);
$hasMedia = false;

$usePhpZip = class_exists('ZipArchive');
$zip = null;
$zipFile = sys_get_temp_dir() . '/export_' . uniqid() . '.zip';

if ($usePhpZip) {
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Cannot create zip file on the server. Check permissions and tmp directory.");
    }
}

foreach ($tables as $t) {
    if (!isset($allowedMediaTables[$t])) continue;
    $mediaCol = $allowedMediaTables[$t];

    $detectionTables = ['face_logs', 'vehicle_logs', 'snapshot_logs', 'video_logs'];
    if (in_array($t, $detectionTables, true) && $societyId) {
        $where = "society_id = ?";
        $params = [$societyId];
    } else {
        $where = "client_id = ?";
        $params = [$cid];
    }
    
    $tsCol = match($t) {
        'face_logs', 'vehicle_logs' => 'timestamp',
        'snapshot_logs', 'video_logs' => 'created_at',
        'access_requests' => 'requested_at',
        'bulk_detection_alerts' => 'detection_timestamp',
        default => 'created_at'
    };

    if ($from) { $where .= " AND `$tsCol` >= ?"; $params[] = $from; }
    if ($to)   { $where .= " AND `$tsCol` <= ?"; $params[] = $to . ' 23:59:59'; }

    try {
        $stmt = $pdo->prepare("SELECT * FROM `$t` WHERE $where AND `$mediaCol` IS NOT NULL AND `$mediaCol` != ''");
        $stmt->execute($params);

        while ($row = $stmt->fetch()) {
            $dataUri = $row[$mediaCol];
            if (preg_match('/^data:([a-zA-Z0-9\/+-.]+);base64,(.*)$/', $dataUri, $matches)) {
                $mime = $matches[1];
                $data = base64_decode($matches[2]);
                
                $ext = match($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'video/mp4' => 'mp4',
                    'video/webm' => 'webm',
                    'video/x-msvideo' => 'avi',
                    default => 'bin'
                };

                $ts = str_replace([':', ' ', '-'], ['','_',''], $row[$tsCol] ?? $row['created_at'] ?? '0000');
                $id = $row['id'] ?? uniqid();
                $filename = "{$ts}_{$id}.{$ext}";

                if ($usePhpZip) {
                    $zip->addFromString("{$t}/{$filename}", $data);
                } else {
                    // Fallback: Write directly to OS temp directory
                    if (!is_dir("$tmpDir/$t")) mkdir("$tmpDir/$t", 0777, true);
                    file_put_contents("$tmpDir/$t/$filename", $data);
                }
                $hasMedia = true;
            }
        }
    } catch (Exception $e) { }
}

if ($usePhpZip) {
    if (!$hasMedia) $zip->addFromString('empty.txt', "No media found in the database for the selected criteria.");
    $zip->close();
} else {
    if (!$hasMedia) file_put_contents("$tmpDir/empty.txt", "No media found in the database for the selected criteria.");
    
    // Windows PowerShell Zip Fallback
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $psCommand = "powershell -NoProfile -Command \"Compress-Archive -Path '$tmpDir\\*' -DestinationPath '$zipFile' -Force\"";
        shell_exec($psCommand);
    } else {
        // Linux/Mac fallback just in case
        shell_exec("cd " . escapeshellarg($tmpDir) . " && zip -r " . escapeshellarg($zipFile) . " .");
    }
}

// Cleanup temp fallback directory
if (is_dir($tmpDir)) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        shell_exec("rmdir /s /q " . escapeshellcmd($tmpDir));
    } else {
        shell_exec("rm -rf " . escapeshellarg($tmpDir));
    }
}

logAudit($pdo, $cid, $session['username'], 'BACKUP_DOWNLOAD', "Downloaded media ZIP for tables: " . implode(',', $tables));

header('Content-Type: application/zip');
header('Content-disposition: attachment; filename=EyeSense_Media_' . $cid . '_' . date('Ymd_His') . '.zip');
header('Content-Length: ' . filesize($zipFile));
readfile($zipFile);
unlink($zipFile);
exit;
