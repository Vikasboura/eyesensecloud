<?php
/**
 * EyeSense Cloud Portal — Get Manifest API
 * Returns the entire updates/manifest.json file.
 */

require_once __DIR__ . '/portal_config.php';

$manifestFile = __DIR__ . '/updates/manifest.json';

if (!file_exists($manifestFile)) {
    json_response([
        'success' => false,
        'error' => 'Manifest file not found on server'
    ], 404);
}

$content = file_get_contents($manifestFile);
$data = json_decode($content, true);

if (!$data) {
    json_response([
        'success' => false,
        'error' => 'Invalid manifest file on server'
    ], 500);
}

json_response($data);
