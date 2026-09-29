<?php
/**
 * ============================================
 * INDSAC PUBLIC KEY ENDPOINT
 * ============================================
 * Returns the public key (base64-encoded) so the client
 * can silently download it during manual activation.
 * 
 * This is safe to expose — the public key can only VERIFY
 * signatures, not create them. The private key never leaves
 * the server.
 * 
 * GET /get_public_key.php
 * Response: { "success": true, "public_key_pem": "<base64>" }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$publicKeyPath = __DIR__ . '/public_key.pem';

if (!file_exists($publicKeyPath)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => 'Public key not yet generated. No licenses have been issued.'
    ]);
    exit;
}

$publicKeyPEM = file_get_contents($publicKeyPath);
$publicKeyB64 = base64_encode($publicKeyPEM);

echo json_encode([
    'success'        => true,
    'public_key_pem' => $publicKeyB64,
], JSON_PRETTY_PRINT);
