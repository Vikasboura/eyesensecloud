<?php
/**
 * ============================================
 * INDSAC LICENSE VERIFICATION ENDPOINT
 * ============================================
 * Verifies a license key's validity against server records.
 * 
 * POST /verify_license.php
 * Request:  { "machine_id": "...", "license_key_hash": "sha256-of-key" }
 * Response: { "success": true, "status": "active", "plan": "PRO_1Y", "expiry_date": "..." }
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
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

$machine_id       = $input['machine_id'] ?? '';
$license_key_hash = $input['license_key_hash'] ?? '';
$society_id       = isset($input['society_id']) && is_numeric($input['society_id']) ? (int)$input['society_id'] : null;

if (!$machine_id || !$license_key_hash) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'machine_id and license_key_hash are required.']);
    exit;
}

try {
    $db = get_license_db();

    // Look up the license by machine_id and key hash, joining society if linked
    $stmt = $db->prepare("
        SELECT l.plan, l.start_date, l.expiry_date, l.status, l.payment_id, l.sa_name, l.sa_email,
               l.society_id, l.legacy_client_id, s.society_code, s.society_name
        FROM licenses l
        LEFT JOIN society s ON s.id = l.society_id
        WHERE l.machine_id = :mid AND l.license_key_hash = :hash
        ORDER BY l.id DESC
        LIMIT 1
    ");
    $stmt->execute([':mid' => $machine_id, ':hash' => $license_key_hash]);
    $record = $stmt->fetch();

    if (!$record) {
        echo json_encode([
            'success'  => false,
            'verified' => false,
            'error'    => 'No matching license found on server.'
        ]);
        exit;
    }

    // If request specified society_id and the license is explicitly tied to a different society
    if ($society_id !== null && $record['society_id'] !== null && (int)$record['society_id'] !== $society_id) {
        echo json_encode([
            'success'  => false,
            'verified' => false,
            'error'    => 'License does not belong to specified society.'
        ]);
        exit;
    }

    // Check if it's expired based on server date
    $today  = date('Y-m-d');
    $is_expired = ($today > $record['expiry_date']);
    $effective_status = $is_expired ? 'expired' : $record['status'];

    echo json_encode([
        'success'          => true,
        'verified'         => ($effective_status === 'active'),
        'status'           => $effective_status,
        'plan'             => $record['plan'],
        'start_date'       => $record['start_date'],
        'expiry_date'      => $record['expiry_date'],
        'payment_id'       => $record['payment_id'],
        'sa_name'          => $record['sa_name'],
        'society_id'       => $record['society_id'] !== null ? (int)$record['society_id'] : null,
        'society_code'     => $record['society_code'] ?? null,
        'society_name'     => $record['society_name'] ?? null,
        'legacy_client_id' => $record['legacy_client_id'] ?? null,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Server error: ' . $e->getMessage()
    ]);
}
