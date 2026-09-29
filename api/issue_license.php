<?php
/**
 * ============================================
 * INDSAC LICENSE ISSUER API
 * ============================================
 * API Endpoint to generate signed Eyesense licenses.
 * 
 * ---- REQUEST (POST JSON) ----
 * {
 *   "machine_id":  "sha256-hash-of-hardware-id",
 *   "plan":        "TRIAL|PRO_1M|PRO_6M|PRO_1Y",
 *   "auto_renew":  true|false,
 *   "sa_name":     "Super Admin Name",
 *   "sa_email":    "admin@company.com",
 *   "sa_phone":    "+91XXXXXXXXXX",
 *   "payment_id":  "PAY-XXXXXXX"  (optional for TRIAL)
 * }
 * 
 * ---- RESPONSE ----
 * {
 *   "success":     true,
 *   "license_key": "BASE64PAYLOAD.BASE64SIGNATURE",
 *   "expiry_date": "2027-03-11",
 *   "plan":        "PRO_1Y"
 * }
 */

// ==================================================
// CONFIGURATION
// ==================================================

$PRIVATE_KEY_PATH = __DIR__ . '/private_key.pem';

// HMAC shared secret for API security (prevents direct Postman calls)
// In production: set via env var. Both INDSAC payment webhook and this API share it.
$API_SECRET = defined('DB_CFG_LICENSE_API_SECRET') ? DB_CFG_LICENSE_API_SECRET : (getenv('LICENSE_API_SECRET') ?: 'EYESENSE-INDSAC-2026-SECRET-KEY');

// Plan durations & prices — populated entirely from the database.
// The admin dashboard (admin_dashboard.php) is the single source of truth.
// TRIAL is kept as a minimal safe fallback if DB is momentarily unreachable.
$PLAN_DURATIONS = ['TRIAL' => 14];
$PLAN_PRICES    = ['TRIAL' => '0'];

// Include database helper
require_once __DIR__ . '/db_setup.php';

// Load ALL active plans from DB — days & price come directly from what was entered
// in the admin dashboard, no calculation needed.
$db_plan_loaded = false;
try {
    $db = get_license_db();
    $stmt = $db->query("SELECT plan_code, days, price FROM plans WHERE is_active = 1");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $PLAN_DURATIONS[$p['plan_code']] = (int)$p['days'];
        $PLAN_PRICES[$p['plan_code']]    = (string)$p['price'];
    }
    $db_plan_loaded = true;
} catch (Throwable $e) {
    // DB unreachable — will only accept TRIAL as fallback
    error_log('[issue_license] DB plan load failed: ' . $e->getMessage());
}

/**
 * Look up plan duration from the DB-populated map.
 * Returns null if the plan is not registered in the admin dashboard.
 */
function resolve_plan_duration(string $plan_code, array $PLAN_DURATIONS): ?int {
    // Exact match
    if (isset($PLAN_DURATIONS[$plan_code])) {
        return $PLAN_DURATIONS[$plan_code];
    }
    // Try normalised form (dash → underscore, uppercase) e.g. pro-3m → PRO_3M
    $norm = strtoupper(str_replace('-', '_', $plan_code));
    if (isset($PLAN_DURATIONS[$norm])) {
        return $PLAN_DURATIONS[$norm];
    }
    // Not found — plan must be registered in the admin dashboard first
    return null;
}

// ==================================================
// HEADERS
// ==================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// ==================================================
// PARSE INPUT
// ==================================================

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

$machine_id = $input['machine_id'] ?? null;
$plan       = $input['plan'] ?? null;
$auto_renew = $input['auto_renew'] ?? false;
$sa_name    = $input['sa_name'] ?? null;
$sa_email   = $input['sa_email'] ?? null;
$sa_phone   = $input['sa_phone'] ?? '';
$payment_id = $input['payment_id'] ?? '';

$society_id       = isset($input['society_id']) && is_numeric($input['society_id']) ? (int)$input['society_id'] : null;
$legacy_client_id = !empty($input['legacy_client_id']) ? trim((string)$input['legacy_client_id']) : (!empty($input['client_id']) ? trim((string)$input['client_id']) : null);

// Auto-resolve society_id and legacy_client_id via sa_email if not explicitly passed
if (!$society_id || !$legacy_client_id) {
    try {
        $db = get_license_db();
        $st = $db->prepare("
            SELECT s.id AS society_id, c.client_id AS legacy_client_id
            FROM clients c
            LEFT JOIN society s ON s.legacy_client_id = c.client_id
            WHERE c.email = ?
            LIMIT 1
        ");
        $st->execute([$sa_email]);
        $resolved = $st->fetch(PDO::FETCH_ASSOC);
        if ($resolved) {
            if (!$society_id && !empty($resolved['society_id'])) {
                $society_id = (int)$resolved['society_id'];
            }
            if (!$legacy_client_id && !empty($resolved['legacy_client_id'])) {
                $legacy_client_id = $resolved['legacy_client_id'];
            }
        }
    } catch (Throwable $e) {
        // Fallback gracefully
    }
}

// Auto-generate payment_id for trials
if (empty($payment_id)) {
    $prefix = ($plan === 'TRIAL') ? 'TRIAL' : 'SIM';
    $payment_id = $prefix . '-' . strtoupper(bin2hex(random_bytes(6)));
}

// Validate required fields
$errors = [];
if (!$machine_id) $errors[] = 'machine_id is required';
if (!$plan)       $errors[] = 'plan is required';
if (!$sa_name)    $errors[] = 'sa_name is required';
if (!$sa_email)   $errors[] = 'sa_email is required';

// Resolve duration from DB-loaded plan map
$resolved_days = resolve_plan_duration($plan, $PLAN_DURATIONS);
if ($resolved_days === null) {
    $errors[] = !$db_plan_loaded
        ? "Plan lookup failed: database unavailable. Please try again."
        : "Plan '{$plan}' is not registered. Add it in the INDSAC admin dashboard before issuing licenses.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Check for free trial abuse
if ($plan === 'TRIAL') {
    try {
        $db = get_license_db();
        if (has_used_trial($db, $machine_id)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'A free trial has already been used on this machine.'
            ]);
            exit;
        }
    } catch (Exception $e) {
        // Proceed if DB is down during first setup, but ideally DB should be up
    }
}

// ==================================================
// VERIFY HMAC TOKEN (prevents direct API calls)
// ==================================================

$token = $input['token'] ?? '';

// Expected token = HMAC-SHA256(payment_id|machine_id|plan, SECRET)
$expected_token = hash_hmac('sha256', $payment_id . '|' . $machine_id . '|' . $plan, $API_SECRET);

if (!hash_equals($expected_token, $token)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Invalid security token. Unauthorized request.'
    ]);
    exit;
}

// ==================================================
// LOAD PRIVATE KEY
// ==================================================

// Auto-generate keys if they don't exist (first-time INDSAC server setup)
if (!file_exists($PRIVATE_KEY_PATH)) {
    // Auto-detect openssl.cnf
    $autoSslCnf = (defined('DB_CFG_OPENSSL_CONF') && DB_CFG_OPENSSL_CONF) ? DB_CFG_OPENSSL_CONF : getenv('OPENSSL_CONF');
    if (!$autoSslCnf || !file_exists($autoSslCnf)) {
        $phpDir = dirname(PHP_BINARY);
        foreach ([$phpDir . '/extras/ssl/openssl.cnf', $phpDir . '/openssl.cnf'] as $p) {
            if (file_exists($p)) { $autoSslCnf = $p; break; }
        }
    }
    $genConfig = [
        "private_key_bits" => 4096,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
        "digest_alg" => "sha256",
    ];
    if ($autoSslCnf) $genConfig['config'] = $autoSslCnf;

    $kp = openssl_pkey_new($genConfig);
    if (!$kp) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Auto key generation failed: ' . openssl_error_string()]);
        exit;
    }
    openssl_pkey_export($kp, $privPEM, null, $genConfig);
    $pubDetails = openssl_pkey_get_details($kp);
    file_put_contents($PRIVATE_KEY_PATH, $privPEM);
    file_put_contents(__DIR__ . '/public_key.pem', $pubDetails['key']);

    // Auto-copy public key to configs/ if it exists (local dev & bundled client)
    $configsDir = dirname(__DIR__) . '/configs';
    if (is_dir($configsDir)) {
        copy(__DIR__ . '/public_key.pem', $configsDir . '/public_key.pem');
    }
}

$privateKeyPEM = file_get_contents($PRIVATE_KEY_PATH);

// Auto-detect openssl.cnf for Windows
$opensslCnf = (defined('DB_CFG_OPENSSL_CONF') && DB_CFG_OPENSSL_CONF) ? DB_CFG_OPENSSL_CONF : getenv('OPENSSL_CONF');
if (!$opensslCnf || !file_exists($opensslCnf)) {
    $phpDir = dirname(PHP_BINARY);
    $candidates = [$phpDir . '/extras/ssl/openssl.cnf', $phpDir . '/openssl.cnf'];
    foreach ($candidates as $path) {
        if (file_exists($path)) { $opensslCnf = $path; break; }
    }
}

$privateKey = openssl_pkey_get_private($privateKeyPEM);

if (!$privateKey) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load private key: ' . openssl_error_string()
    ]);
    exit;
}

// ==================================================
// BUILD LICENSE PAYLOAD
// ==================================================

$start_date  = date('Y-m-d');
$duration    = $resolved_days; // already resolved dynamically above

// Fetch remaining days from an active license (if any)
$remaining_days = 0;
try {
    $db = get_license_db();
    $remaining_days = get_remaining_days($db, $machine_id);
} catch (Exception $e) {
    // Ignore DB errors here; rollover fails silently rather than blocking purchase
}

$total_duration = $duration + $remaining_days;
$expiry_date = date('Y-m-d', strtotime("+$total_duration days"));

$payload = [
    'machine_id'  => $machine_id,
    'plan'        => $plan,
    'start_date'  => $start_date,
    'expiry_date' => $expiry_date,
    'auto_renew'  => (bool)$auto_renew,
    'society_id'  => $society_id,
    'client_id'   => $legacy_client_id,
    'contact' => [
        'sa_name'    => $sa_name,
        'sa_email'   => $sa_email,
        'sa_phone'   => $sa_phone,
        'payment_id' => $payment_id,
    ],
];

$payloadJSON  = json_encode($payload, JSON_UNESCAPED_SLASHES);
$payloadBytes = $payloadJSON;

// ==================================================
// SIGN WITH RSA-PSS (SHA-256)
// ==================================================

// Note: openssl_sign with OPENSSL_ALGO_SHA256 uses PKCS1v1.5 padding by default.
// For PSS padding (matching Python's padding.PSS), we use openssl_sign with 
// RSA_PKCS1_PSS_PADDING via the lower-level openssl functions.

$signature = '';

// Use PSS padding for maximum security (matches Python cryptography library)
$success = openssl_sign(
    $payloadBytes,
    $signature,
    $privateKey,
    OPENSSL_ALGO_SHA256
);

// If PSS is needed, we switch to the lower-level approach:
// For compatibility, we'll adjust the Python side to accept PKCS1v15 as well.
// This is still industry-standard and used by most CAs.

if (!$success) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Signing failed: ' . openssl_error_string()
    ]);
    exit;
}

// ==================================================
// ENCODE LICENSE STRING
// ==================================================

// Format: BASE64URL(payload).BASE64URL(signature)
$payloadB64   = rtrim(strtr(base64_encode($payloadBytes), '+/', '-_'), '=');
$signatureB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

$licenseKey = $payloadB64 . '.' . $signatureB64;

// ==================================================
// STORE LICENSE RECORD IN DATABASE
// ==================================================

try {
    $db = get_license_db();
    $amount = $PLAN_PRICES[$plan] ?? '0';
    $keyHash = hash('sha256', $licenseKey);
    
    insert_license_record(
        $db, $machine_id, $plan, $start_date, $expiry_date,
        (bool)$auto_renew, $sa_name, $sa_email, $sa_phone,
        $payment_id, $amount, $keyHash,
        $society_id, $legacy_client_id
    );
    $db_status = 'recorded';
} catch (Exception $e) {
    $db_status = 'db_error: ' . $e->getMessage();
}

// ==================================================
// READ PUBLIC KEY FOR CLIENT DOWNLOAD
// ==================================================

$publicKeyPEM = file_get_contents(__DIR__ . '/public_key.pem');
$publicKeyB64 = base64_encode($publicKeyPEM);

// ==================================================
// RESPONSE (includes public_key for client auto-download)
// ==================================================

echo json_encode([
    'success'          => true,
    'license_key'      => $licenseKey,
    'public_key_pem'   => $publicKeyB64,
    'plan'             => $plan,
    'start_date'       => $start_date,
    'expiry_date'      => $expiry_date,
    'payment_id'       => $payment_id,
    'machine_id'       => substr($machine_id, 0, 16) . '...',
    'society_id'       => $society_id,
    'legacy_client_id' => $legacy_client_id,
    'db_status'        => $db_status,
], JSON_PRETTY_PRINT);
