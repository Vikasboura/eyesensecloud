<?php
/**
 * ============================================
 * INDSAC VERIFY CLIENT EXISTS API
 * ============================================
 * Called by EyeSense Flask app when a user wants to update
 * or verify their Client ID in Settings → Cloud Backup.
 *
 * ---- REQUEST (POST JSON) ----
 * { "client_id": "IND-2026-A4B9C" }
 *
 * ---- RESPONSE ----
 * { "exists": true, "client_id": "IND-2026-A4B9C", "name": "John Doe", "email": "john@company.com" }
 * OR
 * { "exists": false }
 */

require_once __DIR__ . '/db_setup.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exists' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['exists' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

$client_id = strtoupper(trim($input['client_id'] ?? ''));

if (!$client_id) {
    http_response_code(400);
    echo json_encode(['exists' => false, 'error' => 'client_id is required.']);
    exit;
}

try {
    $db = get_license_db();

    $stmt = $db->prepare("SELECT client_id, first_name, last_name, email FROM clients WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode([
            'exists'    => true,
            'client_id' => $row['client_id'],
            'name'      => trim($row['first_name'] . ' ' . $row['last_name']),
            'email'     => $row['email'],
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['exists' => false]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'exists' => false,
        'error'  => 'Server error: ' . $e->getMessage()
    ]);
}
