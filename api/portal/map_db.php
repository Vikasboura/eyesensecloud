<?php
/**
 * map_db.php — PDO connection for the Eyesense client map database.
 * Used by client.php and visitor.php.
 */

function get_map_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Try portal_config constants first, then fall back to db.php values
    $host = defined('DB_CFG_HOST') ? DB_CFG_HOST : 'localhost';
    $port = defined('DB_CFG_PORT') ? DB_CFG_PORT : '3306';

    // Map-specific DB — separate credentials for client/plot data
    $dbname = getenv('MAP_DB_NAME') ?: 'indsac_eyesense_map';
    $user   = getenv('MAP_DB_USER') ?: 'indsac_eyesense_user_map';
    $pass   = getenv('MAP_DB_PASS') ?: 'eyesense_user_map_2026';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
