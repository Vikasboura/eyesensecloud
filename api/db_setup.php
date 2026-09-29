<?php
/**
 * EyeSense — Database Setup & Helper Functions
 *
 * - Loads db.php (server) or .env (local dev) for credentials
 * - Creates the database if it doesn't exist
 * - Executes indsac_schema.sql to ensure all tables exist
 * - Provides helper functions for license management
 *
 * Run directly to initialise/reset the schema:
 *   php api/db_setup.php
 */

// ── Load configuration ─────────────────────────────────────────────────
$_db_cfg   = __DIR__ . '/db.php';
$_env_file = dirname(__DIR__) . '/.env';

if (file_exists($_db_cfg)) {
    $level = ob_get_level();
    ob_start();
    require_once $_db_cfg;
    while (ob_get_level() > $level) {
        ob_end_clean();
    }
} elseif (file_exists($_env_file)) {
    foreach (file($_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $n = trim($parts[0]); $v = trim($parts[1]);
            if (getenv($n) === false) { putenv("$n=$v"); $_ENV[$n] = $v; }
        }
    }
}

// ── Resolve DB credentials ────────────────────────────────────────────
if (!defined('DB_CFG_HOST')) define('DB_CFG_HOST', getenv('LICENSE_DB_HOST') ?: (getenv('DB_HOST') ?: 'localhost'));
if (!defined('DB_CFG_PORT')) define('DB_CFG_PORT', getenv('LICENSE_DB_PORT') ?: (getenv('DB_PORT') ?: '3306'));
if (!defined('DB_CFG_NAME')) define('DB_CFG_NAME', getenv('LICENSE_DB_NAME') ?: 'eyesense_licenses');
if (!defined('DB_CFG_USER')) define('DB_CFG_USER', getenv('LICENSE_DB_USER') ?: (getenv('DB_USER') ?: 'root'));
if (!defined('DB_CFG_PASS')) define('DB_CFG_PASS', getenv('LICENSE_DB_PASS') ?: (getenv('DB_PASSWORD') ?: ''));

$DB_HOST = DB_CFG_HOST;
$DB_PORT = DB_CFG_PORT;
$DB_NAME = DB_CFG_NAME;
$DB_USER = DB_CFG_USER;
$DB_PASS = DB_CFG_PASS;

// ── Schema SQL path ───────────────────────────────────────────────────
define('INDSAC_SCHEMA_FILE', __DIR__ . '/indsac_schema.sql');

/**
 * Execute a SQL file against a PDO connection.
 * Handles multi-statement files safely.
 */
function executeSqlFile(PDO $pdo, string $file): array {
    if (!file_exists($file)) {
        return ['error' => "Schema file not found: {$file}"];
    }

    $sql = file_get_contents($file);
    $results = [];

    // Strip block comments /* ... */ and line comments -- ...
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);

    // Split into individual statements on semicolons
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && $s !== ''
    );

    foreach ($statements as $stmt) {
        $stripped = trim($stmt);
        if (empty($stripped)) continue;

        try {
            $pdo->exec($stmt);
            // Extract first keyword for logging
            preg_match('/^\s*(\w+\s+\w+)/i', $stripped, $m);
            $results[] = '  OK: ' . ($m[1] ?? substr($stripped, 0, 40));
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // Ignore "already exists" errors — safe to re-run
            if (
                strpos($msg, 'already exists') !== false ||
                strpos($msg, 'Duplicate column') !== false ||
                strpos($msg, 'Duplicate key') !== false ||
                strpos($msg, 'Duplicate entry') !== false ||
                strpos($msg, 'Duplicate foreign key') !== false ||
                strpos($msg, "Can't DROP") !== false ||
                strpos($msg, '1060') !== false ||
                strpos($msg, '1061') !== false ||
                strpos($msg, '1826') !== false
            ) {
                $results[] = '  SKIP (exists): ' . substr($stripped, 0, 60);
            } else {
                $results[] = '  ERROR: ' . $msg . ' | SQL: ' . substr($stripped, 0, 80);
            }
        }
    }

    return $results;
}

/**
 * Get a PDO connection to the MySQL database.
 */
function get_license_db(): PDO {
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS;
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Connect without DB name first to CREATE DATABASE IF NOT EXISTS
    try {
        $bootstrapDsn = "mysql:host=$DB_HOST;port=$DB_PORT;charset=utf8mb4";
        $bootstrap = new PDO($bootstrapDsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $bootstrap->exec(
            "CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    } catch (PDOException $e) {
        throw new RuntimeException("MySQL bootstrap failed: " . $e->getMessage());
    }

    // Connect to the actual database
    $dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // NOTE: Schema is NOT auto-applied here — run db_setup.php manually once to initialise tables.
    // Running DROP+CREATE on every request would wipe all data.

    return $pdo;
}

/**
 * Seed the plans table with default EyeSense plans (only if empty).
 */
function seed_default_plans(PDO $db): void {
    $count = $db->query("SELECT COUNT(*) FROM plans")->fetchColumn();
    if ($count > 0) return;

    $defaults = [
        ['TRIAL',  'Free Trial',   14,  0,     0],
        ['PRO_1M', 'Pro Monthly',  30,  2999,  0],
        ['PRO_6M', 'Pro 6 Months', 180, 14999, 0],
        ['PRO_1Y', 'Pro Annual',   365, 24999, 0],
    ];
    $stmt = $db->prepare("INSERT IGNORE INTO plans (plan_code, name, days, price, camera_limit) VALUES (?, ?, ?, ?, ?)");
    foreach ($defaults as $p) $stmt->execute($p);
}

/**
 * Generate a unique, human-memorable client ID.
 * Format: IND-YYYY-XXXXX  (e.g. IND-2026-A4B9C)
 */
function generate_client_id(PDO $db): string {
    $year = date('Y');
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($i = 0; $i < 20; $i++) {
        $suffix = '';
        for ($j = 0; $j < 5; $j++) $suffix .= $chars[random_int(0, strlen($chars) - 1)];
        $candidate = "IND-{$year}-{$suffix}";
        $stmt = $db->prepare("SELECT COUNT(*) FROM clients WHERE client_id = ?");
        $stmt->execute([$candidate]);
        if ($stmt->fetchColumn() == 0) return $candidate;
    }
    return "IND-{$year}-" . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

/**
 * Insert a license record (deactivates existing active licenses for the same machine).
 */
function insert_license_record(
    PDO $db, string $machine_id, string $plan, string $start_date, string $expiry_date,
    bool $auto_renew, string $sa_name, string $sa_email, string $sa_phone,
    string $payment_id, string $amount, string $license_key_hash,
    ?int $society_id = null, ?string $legacy_client_id = null
): int {
    $db->prepare("UPDATE licenses SET status='superseded', updated_at=NOW() WHERE machine_id=:mid AND status='active'")
       ->execute([':mid' => $machine_id]);
    $stmt = $db->prepare("
        INSERT INTO licenses (
            society_id, legacy_client_id, machine_id, plan, start_date, expiry_date, auto_renew,
            sa_name, sa_email, sa_phone, payment_id, payment_status, amount, license_key_hash
        ) VALUES (
            :society_id, :legacy_client_id, :mid, :plan, :start, :expiry, :renew,
            :name, :email, :phone, :pid, 'completed', :amount, :hash
        )
    ");
    $stmt->execute([
        ':society_id'        => $society_id,
        ':legacy_client_id'  => $legacy_client_id,
        ':mid'               => $machine_id,
        ':plan'              => $plan,
        ':start'             => $start_date,
        ':expiry'            => $expiry_date,
        ':renew'             => $auto_renew ? 1 : 0,
        ':name'              => $sa_name,
        ':email'             => $sa_email,
        ':phone'             => $sa_phone,
        ':pid'               => $payment_id,
        ':amount'            => $amount,
        ':hash'              => $license_key_hash,
    ]);
    return (int)$db->lastInsertId();
}

/** Check if a machine has already claimed a free trial. */
function has_used_trial(PDO $db, string $machine_id): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM licenses WHERE machine_id=:mid AND plan='TRIAL'");
    $stmt->execute([':mid' => $machine_id]);
    return $stmt->fetchColumn() > 0;
}

/** Get remaining days from an active license. */
function get_remaining_days(PDO $db, string $machine_id): int {
    $stmt = $db->prepare("SELECT expiry_date FROM licenses WHERE machine_id=:mid AND status='active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([':mid' => $machine_id]);
    $expiry = $stmt->fetchColumn();
    if (!$expiry) return 0;
    $today = new DateTime(); $exp = new DateTime($expiry);
    return $exp > $today ? (int)$today->diff($exp)->days : 0;
}

// ── CLI: Run directly to initialise / verify schema ──────────────────
if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "\nEyeSense DB Setup\n";
    echo "Host: $DB_HOST | DB: $DB_NAME | User: $DB_USER\n\n";

    // Bootstrap DB
    try {
        $bootstrapDsn = "mysql:host=$DB_HOST;port=$DB_PORT;charset=utf8mb4";
        $bp = new PDO($bootstrapDsn, $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $bp->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Database '$DB_NAME' ready.\n";
    } catch (PDOException $e) {
        die("ERROR: Cannot connect to MySQL - " . $e->getMessage() . "\n");
    }

    // Connect and run schema
    $dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "\nRunning indsac_schema.sql...\n";
    $results = executeSqlFile($pdo, INDSAC_SCHEMA_FILE);
    foreach ($results as $r) echo $r . "\n";

    // Seed plans
    seed_default_plans($pdo);

    // Show tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "\nTables in '$DB_NAME' (" . count($tables) . " total):\n";
    foreach ($tables as $t) echo "  - $t\n";

    echo "\nSetup complete!\n\n";
}
