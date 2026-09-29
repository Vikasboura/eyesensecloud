<?php
/**
 * EyeSense INDSAC — Tenant Verification API
 * Called by the local Flask app during first-time setup.
 * Verifies client_id + password and returns the employee record + license data.
 *
 * POST /verify_tenant.php
 * Body: { "client_id": "IND-2026-XXXXX", "password": "...", "machine_id": "..." }
 */
ob_start();

require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/db_setup.php';

ob_clean();

// CORS — local Flask (port 5000) calling PHP (port 8080)
$allowedOrigins = INDSAC_ALLOWED_ORIGINS;  // configured in db.php → DB_CFG_ALLOWED_ORIGINS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($origin, $allowedOrigins) ? $origin : $allowedOrigins[0]));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

header('Content-Type: application/json; charset=utf-8');

function vt_error(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true) ?: array_merge($_GET, $_POST);

$client_id  = trim($input['client_id']  ?? '');
$password   = trim($input['password']   ?? '');
$machine_id = trim($input['machine_id'] ?? '');

if (!$client_id || !$password) vt_error('client_id and password are required');

// ── 1. Verify superadmin employee in portal DB (eyesense_licenses) ──────
$pdo = get_indsac_db();

$stmt = $pdo->prepare("
    SELECT id, client_id, employee_id, full_name, email, phone,
           role, access_level, password_hash, status
    FROM employees
    WHERE client_id = ?
      AND LOWER(REPLACE(role, '_', '')) = 'superadmin'
      AND is_deleted = 0
    LIMIT 1
");
$stmt->execute([$client_id]);
$emp = $stmt->fetch();

if (!$emp) {
    vt_error('Client ID not found or no superadmin account exists');
}

if ($emp['status'] === 'inactive') {
    vt_error('This account has been suspended. Contact INDSAC support.');
}

// Verify password against Werkzeug hash
if (!verify_werkzeug_password($password, $emp['password_hash'])) {
    vt_error('Invalid password');
}

// ── 2. Fetch license for this client ────────────────────────────────────
$db = get_license_db();

$licStmt = $db->prepare("
    SELECT machine_id, plan, start_date, expiry_date,
           sa_name, sa_email, sa_phone, payment_id, payment_status, amount,
           license_key_hash, status
    FROM licenses
    WHERE sa_email = ? AND status = 'active'
    ORDER BY expiry_date DESC LIMIT 1
");
$licStmt->execute([$emp['email']]);
$license = $licStmt->fetch() ?: null;

// Use provided machine_id (fallback to stored one)
$effective_machine_id = $machine_id ?: ($license['machine_id'] ?? '');

// If no license exists yet → create a TRIAL record
if ($effective_machine_id && !$license) {
    $plan   = 'TRIAL';
    $start  = date('Y-m-d');
    $expiry = date('Y-m-d', strtotime('+14 days'));
    $hash   = hash('sha256', $client_id . $effective_machine_id . $plan . microtime());

    insert_license_record(
        $db, $effective_machine_id, $plan, $start, $expiry, false,
        $emp['full_name'], $emp['email'], $emp['phone'] ?? '',
        'ACTIVATION-' . strtoupper(substr($client_id, -6)),
        '0', $hash
    );

    $license = [
        'machine_id'        => $effective_machine_id,
        'plan'              => $plan,
        'start_date'        => $start,
        'expiry_date'       => $expiry,
        'sa_name'           => $emp['full_name'],
        'sa_email'          => $emp['email'],
        'sa_phone'          => $emp['phone'] ?? '',
        'payment_id'        => 'ACTIVATION-' . strtoupper(substr($client_id, -6)),
        'payment_status'    => 'completed',
        'amount'            => '0',
        'license_key_hash'  => $hash,
        'status'            => 'active',
    ];
}

// ── 3. Generate signed license key ────────────────────────────────────────
$license_key_string = null;
$license_error      = null;

if ($license && $effective_machine_id) {
    $privKeyPath = __DIR__ . '/private_key.pem';
    if (file_exists($privKeyPath)) {
        $payload = [
            'machine_id'  => $effective_machine_id,
            'plan'        => $license['plan'],
            'start_date'  => $license['start_date'],
            'expiry_date' => $license['expiry_date'],
            'auto_renew'  => false,
            'contact' => [
                'sa_name'    => $license['sa_name']  ?? $emp['full_name'],
                'sa_email'   => $license['sa_email'] ?? $emp['email'],
                'sa_phone'   => $license['sa_phone'] ?? '',
                'payment_id' => $license['payment_id'] ?? '',
            ],
        ];
        $payloadJSON = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $privKey     = openssl_pkey_get_private(file_get_contents($privKeyPath));

        if ($privKey) {
            $sig = '';
            if (openssl_sign($payloadJSON, $sig, $privKey, OPENSSL_ALGO_SHA256)) {
                $payloadB64  = rtrim(strtr(base64_encode($payloadJSON), '+/', '-_'), '=');
                $sigB64      = rtrim(strtr(base64_encode($sig),         '+/', '-_'), '=');
                $license_key_string = $payloadB64 . '.' . $sigB64;

                // Update stored hash to match freshly generated key
                $newHash = hash('sha256', $license_key_string);
                $db->prepare("UPDATE licenses SET license_key_hash = ? WHERE machine_id = ? AND sa_email = ?")
                   ->execute([$newHash, $effective_machine_id, $emp['email']]);
            } else {
                $license_error = 'RSA signing failed: ' . openssl_error_string();
            }
        } else {
            $license_error = 'Cannot load private key';
        }
    } else {
        $license_error = 'private_key.pem not found — run issue_license.php once to generate keys';
    }
}

// ── 4. Return employee + license + signed key ─────────────────────────────
echo json_encode([
    'success'         => true,
    'client_id'       => $client_id,
    'employee'        => [
        'employee_id'   => $emp['employee_id'],
        'full_name'     => $emp['full_name'],
        'email'         => $emp['email'],
        'phone'         => $emp['phone'] ?? '',
        'role'          => 'SUPER_ADMIN',
        'access_level'  => 'L1',
        'password_hash' => $emp['password_hash'],
        'status'        => 'active',
    ],
    'license'         => $license,
    'license_key'     => $license_key_string,   // null if key generation failed
    'license_error'   => $license_error,         // non-null if signing failed
]);
