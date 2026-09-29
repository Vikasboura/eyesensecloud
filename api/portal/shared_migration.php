<?php
/**
 * shared_migration.php
 * Shared migration helper — ensures plot columns exist in employees table.
 * Uses INFORMATION_SCHEMA so it works on MySQL 5.7+ and MariaDB.
 * Include once per file that needs the columns.
 */
function ensure_plot_columns(PDO $pdo): void {
    static $ran = false;
    if ($ran) return;
    $ran = true;

    try {
        $dbName  = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $existing = $pdo->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . "
               AND TABLE_NAME = 'employees'"
        )->fetchAll(PDO::FETCH_COLUMN);

        $cols = [
            'plotno'         => 'VARCHAR(100) DEFAULT NULL',
            'plot_address'   => 'TEXT DEFAULT NULL',
            'latitude'       => 'VARCHAR(60) DEFAULT NULL',
            'longitude'      => 'VARCHAR(60) DEFAULT NULL',
            'plot_maplink'   => 'TEXT DEFAULT NULL',
            'alt_phone'      => 'VARCHAR(30) DEFAULT NULL',
            'plot_notes'     => 'TEXT DEFAULT NULL',
            'plot_status'    => 'VARCHAR(50) DEFAULT NULL',
            'plot_filled_by' => 'VARCHAR(100) DEFAULT NULL',
        ];

        foreach ($cols as $col => $def) {
            if (!in_array($col, $existing)) {
                try { $pdo->exec("ALTER TABLE employees ADD COLUMN `{$col}` {$def}"); }
                catch (Throwable $e) { /* column may have been added by concurrent request */ }
            }
        }
    } catch (Throwable $e) {
        // Non-fatal — if columns already exist all queries will work fine
    }
}

function ensure_location_columns(PDO $pdo): void {
    static $ran = false;
    if ($ran) return;
    $ran = true;

    try {
        $dbName  = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $existing = $pdo->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . "
               AND TABLE_NAME = 'employees'"
        )->fetchAll(PDO::FETCH_COLUMN);

        $cols = [
            'location_source'            => "VARCHAR(10) NOT NULL DEFAULT 'gps'",
            'gps_latitude'               => 'VARCHAR(60) DEFAULT NULL',
            'gps_longitude'              => 'VARCHAR(60) DEFAULT NULL',
            'manual_location_updated_at' => 'DATETIME DEFAULT NULL',
        ];

        foreach ($cols as $col => $def) {
            if (!in_array($col, $existing, true)) {
                try { $pdo->exec("ALTER TABLE employees ADD COLUMN `{$col}` {$def}"); }
                catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) {}
}