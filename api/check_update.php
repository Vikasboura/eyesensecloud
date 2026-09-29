<?php
/**
 * EyeSense Cloud Portal — Check Update API
 * Returns the latest rollout version and release date from updates/manifest.json.
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

if (!$data || !isset($data['version'])) {
    json_response([
        'success' => false,
        'error' => 'Invalid manifest file structure on server'
    ], 500);
}

// Return the version and release date
json_response([
    'success' => true,
    'version' => $data['version'],
    'release_date' => $data['release_date'] ?? ''
]);
