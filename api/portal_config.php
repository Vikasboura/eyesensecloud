<?php
/**
 * EyeSense Cloud Portal — Database Configuration
 * Uses db.php for credentials (replaces .env for server compatibility).
 */

// ── Timezone: always IST ─────────────────────────────────────────────────
// Must be set before ANY date()/strtotime() call anywhere in the portal.
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/logger.php';

require_once __DIR__ . '/logger.php';

// ── Suppress PHP errors from leaking into JSON API responses ────────────
// On production the web server may have display_errors=On in php.ini;
// we override it here so HTML error output never corrupts a JSON body.
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    // Keep logging so errors are still captured in error_log
    ini_set('log_errors', '1');
}

// ── Load configuration ────────────────────────────────────────────────
// Priority: db.php (server) → .env (local dev fallback)
$_db_cfg = __DIR__ . '/db.php';
$_env_file = __DIR__ . '/.env';
if (!file_exists($_env_file)) {
    $_env_file = dirname(__DIR__) . '/.env';
}

if (file_exists($_db_cfg)) {
    $level = ob_get_level();
    ob_start();
    require_once $_db_cfg;
    while (ob_get_level() > $level) {
        ob_end_clean();
    }
} elseif (file_exists($_env_file)) {
    // Legacy .env fallback for local development
    foreach (file($_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $n = trim($parts[0]); $v = trim($parts[1]);
            if (getenv($n) === false) { putenv("$n=$v"); $_ENV[$n] = $v; }
        }
    }
}

// ── Resolve constants (db.php → env → defaults) ───────────────────────
if (!defined('DB_CFG_HOST'))             define('DB_CFG_HOST',             getenv('LICENSE_DB_HOST')  ?: (getenv('DB_HOST')     ?: 'localhost'));
if (!defined('DB_CFG_PORT'))             define('DB_CFG_PORT',             getenv('LICENSE_DB_PORT')  ?: (getenv('DB_PORT')     ?: '3306'));
if (!defined('DB_CFG_NAME'))             define('DB_CFG_NAME',             getenv('LICENSE_DB_NAME')  ?: 'eyesense_licenses');
if (!defined('DB_CFG_USER'))             define('DB_CFG_USER',             getenv('LICENSE_DB_USER')  ?: (getenv('DB_USER')     ?: 'root'));
if (!defined('DB_CFG_PASS'))             define('DB_CFG_PASS',             getenv('LICENSE_DB_PASS') !== false ? getenv('LICENSE_DB_PASS') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ''));
if (!defined('DB_CFG_EMAIL_API_URL'))    define('DB_CFG_EMAIL_API_URL',    getenv('INDSAC_EMAIL_API_URL') ?: 'https://indsac.com/pge/SendEmail.php');
if (!defined('DB_CFG_PORTAL_LOGIN_URL')) define('DB_CFG_PORTAL_LOGIN_URL', getenv('PORTAL_LOGIN_URL') ?: 'https://indsac.com/eyesense/api/portal/login.php');
if (!defined('DB_CFG_APP_BASE_URL'))     define('DB_CFG_APP_BASE_URL',     getenv('EYESENSE_APP_BASE_URL') ?: 'http://localhost:8080');
if (!defined('DB_CFG_PORTAL_BASE_URL'))  define('DB_CFG_PORTAL_BASE_URL',  getenv('PORTAL_BASE_URL') ?: rtrim(DB_CFG_APP_BASE_URL, '/') . '/portal');
if (!defined('DB_CFG_LOGIN_SHARE_SECRET')) define('DB_CFG_LOGIN_SHARE_SECRET', getenv('PORTAL_LOGIN_SHARE_SECRET') ?: DB_CFG_LICENSE_API_SECRET . '_SHARE');
if (!defined('DB_CFG_MULTI_SOCIETY_LOGIN_ENABLED')) define('DB_CFG_MULTI_SOCIETY_LOGIN_ENABLED', filter_var(getenv('MULTI_SOCIETY_LOGIN_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));


if (!defined('DB_CFG_ALLOWED_ORIGINS'))  define('DB_CFG_ALLOWED_ORIGINS',  getenv('EYESENSE_ALLOWED_ORIGINS') ?: 'http://localhost:5000,http://127.0.0.1:5000,http://localhost:8080,http://127.0.0.1:8080');
if (!defined('DB_CFG_UPLOADS_DIR'))      define('DB_CFG_UPLOADS_DIR',      getenv('MAINTENANCE_UPLOADS_DIR') ?: '');
if (!defined('DB_CFG_COMPLAINT_UPLOADS_DIR')) define('DB_CFG_COMPLAINT_UPLOADS_DIR', getenv('COMPLAINT_UPLOADS_DIR') ?: '');
if (!defined('DB_CFG_COMMENT_UPLOADS_DIR'))   define('DB_CFG_COMMENT_UPLOADS_DIR',   getenv('COMMENT_UPLOADS_DIR') ?: '');
if (!defined('DB_CFG_LICENSE_API_SECRET')) define('DB_CFG_LICENSE_API_SECRET', getenv('LICENSE_API_SECRET') ?: 'EYESENSE-INDSAC-2026-SECRET-KEY');

function portal_resolve_upload_dir(string $configured, string $default): string {
    $path = trim($configured) !== '' ? trim($configured) : $default;
    $path = str_replace('\\', '/', $path);

    if (preg_match('#^/public_html(?:/|$)#i', $path)) {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $docRoot = $docRoot ? str_replace('\\', '/', $docRoot) : '';
        $docRoot = $docRoot ? rtrim($docRoot, '/') : '';

        if ($docRoot !== '' && preg_match('#^(.*?/public_html)(?:/|$)#i', $docRoot, $m)) {
            $path = rtrim($m[1], '/') . substr($path, strlen('/public_html'));
        }
    } elseif (!preg_match('#^(?:[A-Za-z]:/|/)#', $path)) {
        $path = __DIR__ . '/' . ltrim($path, '/');
    }

    return rtrim($path, '/\\');
}

// ── Named constants used throughout the portal ────────────────────────
define('INDSAC_DB_HOST',       DB_CFG_HOST);
define('INDSAC_DB_PORT',       DB_CFG_PORT);
define('INDSAC_DB_NAME',       DB_CFG_NAME);
define('INDSAC_DB_USER',       DB_CFG_USER);
define('INDSAC_DB_PASS',       DB_CFG_PASS);
define('INDSAC_EMAIL_API_URL', DB_CFG_EMAIL_API_URL);
define('INDSAC_APP_BASE_URL',  DB_CFG_APP_BASE_URL);
define('INDSAC_ALLOWED_ORIGINS', array_map('trim', explode(',', DB_CFG_ALLOWED_ORIGINS)));
define('BACKUP_STORAGE_PATH', __DIR__ . '/backups');
define('MAINTENANCE_UPLOADS_DIR',
    portal_resolve_upload_dir(DB_CFG_UPLOADS_DIR, __DIR__ . '/uploads/maintenance_receipts')
);

// Complaint attachment folders (auto-created at runtime if missing)
define('COMPLAINT_UPLOADS_DIR',
    portal_resolve_upload_dir(DB_CFG_COMPLAINT_UPLOADS_DIR, __DIR__ . '/uploads/complaint_attachments')
);
define('COMMENT_UPLOADS_DIR',
    portal_resolve_upload_dir(DB_CFG_COMMENT_UPLOADS_DIR, __DIR__ . '/uploads/complaint_comment_attachments')
);

/**
 * Get PDO connection to the Indsac database.
 */
function get_indsac_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = "mysql:host=" . INDSAC_DB_HOST . ";port=" . INDSAC_DB_PORT
         . ";dbname=" . INDSAC_DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, INDSAC_DB_USER, INDSAC_DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Set MySQL session timezone to IST so NOW(), CURDATE(), etc. match PHP
    $pdo->exec("SET time_zone = '+05:30'");

    return $pdo;
}

/**
 * Send a JSON response and exit.
 * Cleans ALL output buffer levels so PHP warnings/notices/HTML cannot
 * corrupt the JSON body — critical on shared hosting where display_errors
 * may be On and multiple ob_start() calls (db.php, api files) are nested.
 */
function json_response($data, int $status = 200): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send an error JSON response and exit.
 */
function json_error(string $message, int $status = 400): void {
    json_response(['error' => $message], $status);
}

// Centralized API Base URL endpoint


// ── TWILIO API CONFIGURATION ──
define('TWILIO_ACCOUNT_SID',  getenv('TWILIO_ACCOUNT_SID') ?: 'AC_TWILIO_ACCOUNT_SID');
define('TWILIO_AUTH_TOKEN',   getenv('TWILIO_AUTH_TOKEN') ?: 'TWILIO_AUTH_TOKEN');
define('TWILIO_PHONE_NUMBER', getenv('TWILIO_PHONE_NUMBER') ?: '+10000000000');

define('TWILIO_API_URL',      'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json');

// ── Login Share Ref Helpers ────────────────────────────────────────────────

/**
 * Generate a hash-protected login reference token embedding a client_id.
 * Format: base64url(client_id) . '.' . ts . '.' . base64url(hmac_sha256)
 * $ttlSeconds = 0 means no expiry.
 */
function generate_login_ref(string $client_id, int $ttlSeconds = 0): string {
    $ts      = $ttlSeconds > 0 ? (string)(time() + $ttlSeconds) : '0';
    $payload = rtrim(base64_encode($client_id), '=') . '.' . $ts;
    $sig     = rtrim(base64_encode(hash_hmac('sha256', $payload, DB_CFG_LOGIN_SHARE_SECRET, true)), '=');
    return strtr($payload . '.' . $sig, '+/', '-_');
}

/**
 * Verify a login ref token. Returns the client_id on success, '' on failure.
 */
function verify_login_ref(string $token): string {
    try {
        $parts = explode('.', strtr($token, '-_', '+/'));
        if (count($parts) !== 3) return '';
        [$b64id, $ts, $b64sig] = $parts;

        // Re-pad base64
        $pad = fn($s) => $s . str_repeat('=', (4 - strlen($s) % 4) % 4);

        // Check expiry
        $expires = (int)$ts;
        if ($expires > 0 && time() > $expires) return '';

        // Verify HMAC (constant-time compare)
        $payload  = $b64id . '.' . $ts;
        $expected = rtrim(base64_encode(hash_hmac('sha256', $payload, DB_CFG_LOGIN_SHARE_SECRET, true)), '=');
        if (!hash_equals($expected, $b64sig)) return '';

        return (string)base64_decode($pad($b64id));
    } catch (Throwable $e) {
        return '';
    }
}
