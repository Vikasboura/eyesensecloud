<?php
/**
 * ============================================
 * INDSAC CLIENT REGISTRATION API
 * ============================================
 * Called by setup.py during first-time EyeSense installation.
 * Registers the Super Admin as a new "Client" and returns a
 * memorable client_id (e.g. IND-2026-A4B9C).
 *
 * ---- REQUEST (POST JSON) ----
 * {
 *   "first_name":   "John",
 *   "last_name":    "Doe",
 *   "email":        "john@company.com",
 *   "mobile":       "+91XXXXXXXXXX",
 *   "sa_username":  "SA001",
 *   "machine_id":   "sha256-of-hw-id"
 * }
 *
 * ---- RESPONSE ----
 * {
 *   "success":    true,
 *   "client_id":  "IND-2026-A4B9C"
 * }
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

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

$first_name   = trim($input['first_name'] ?? '');
$last_name    = trim($input['last_name'] ?? '');
$email        = trim($input['email'] ?? '');
$mobile       = trim($input['mobile'] ?? '');
$sa_username  = trim($input['sa_username'] ?? '');
$machine_id   = trim($input['machine_id'] ?? '');

// Validate
$errors = [];
if (!$first_name) $errors[] = 'first_name is required';
if (!$email)      $errors[] = 'email is required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $db = get_license_db();

    // Check if this email is already registered
    $stmt = $db->prepare("SELECT client_id FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Return the existing client_id (idempotent)
        echo json_encode([
            'success'   => true,
            'client_id' => $existing['client_id'],
            'message'   => 'Client already registered with this email.'
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Generate unique client ID
    $client_id = generate_client_id($db);

    // Insert client record
    $stmt = $db->prepare("
        INSERT INTO clients (client_id, first_name, last_name, email, mobile, machine_id, sa_username)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$client_id, $first_name, $last_name, $email, $mobile, $machine_id, $sa_username]);

    echo json_encode([
        'success'   => true,
        'client_id' => $client_id,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Server error: ' . $e->getMessage()
    ]);
}
