<?php
/**
 * EyeSense Cloud Portal — Backup Export API (v2 — mirrored tables)
 * 
 * Generates and streams a .sql file from mirrored EyeSense tables.
 * GET /api/backup_export.php?client_id=X&from=Y&to=Z&tables=face_logs,vehicle_logs
 * 
 * Authentication: X-Client-Id header (agent) OR portal session.
 * Tenant isolation: non-SA portal users can only export their own client_id.
 */

require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/society_context_holder.php';

$pdo = get_indsac_db();

// Authenticate
$clientId = null;
$agentClientId = $_SERVER['HTTP_X_CLIENT_ID'] ?? '';
if ($agentClientId) {
    // Agent requests: locked to the authenticated client_id
    $clientId = $agentClientId;
} else {
    requirePortalLogin();
    $session = getPortalSession();
    requirePermission($pdo, $session['client_id'], $session['role'], 'DOWNLOAD_SQL');
    $clientId = $session['client_id'];
    SocietyContextHolder::initFromRequest($pdo);
}

$targetClient = $_GET['client_id'] ?? $clientId;

// ── Tenant Isolation ──
if ($agentClientId) {
    // Agent requests cannot override client_id
    $targetClient = $clientId;
} elseif (!($session['is_superadmin'] ?? false) && $targetClient !== $clientId) {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied: cannot export another tenant\'s data']));
}
$dateFrom     = $_GET['from'] ?? null;
$dateTo       = $_GET['to']   ?? null;
$requestedTables = isset($_GET['tables']) ? explode(',', $_GET['tables']) : null;

// All exportable mirrored tables
$ALL_TABLES = [
    'employees', 'roles', 'user_permissions', 'cameras', 'camera_assignment',
    'premises', 'face_logs', 'face_embeddings', 'vehicle_logs', 'snapshot_logs',
    'video_logs', 'access_requests', 'alert_logs', 'audit_logs', 'daily_summary',
    'guests', 'guest_visits', 'guest_identities', 'guest_documents',
    'registered_vehicles', 'chat_messages', 'system_logs', 'system_settings',
    'bulk_detection_alerts',
];

// Determine which tables to export
$tablesToExport = $requestedTables
    ? array_intersect(array_map('trim', $requestedTables), $ALL_TABLES)
    : $ALL_TABLES;

// Timestamp column per table (for date filtering)
$tsColMap = [
    'face_logs' => 'timestamp', 'vehicle_logs' => 'created_at',
    'snapshot_logs' => 'created_at',
    'alert_logs' => 'timestamp', 'audit_logs' => 'timestamp',
    'access_requests' => 'requested_at', 'guests' => 'created_at',
    'daily_summary' => 'generated_at',
    'bulk_detection_alerts' => 'detection_timestamp',
];

// Generate SQL
$sql  = "-- ============================================================\n";
$sql .= "-- EyeSense Cloud Backup Export (mirrored tables)\n";
$sql .= "-- Client:    $targetClient\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
if ($dateFrom && $dateTo) {
    $sql .= "-- Range:     $dateFrom to $dateTo\n";
}
$sql .= "-- ============================================================\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

$totalExported = 0;

$societyId = SocietyContextHolder::getCurrentSocietyId();
$detectionTables = ['cameras', 'face_logs', 'vehicle_logs', 'snapshot_logs', 'video_logs'];

foreach ($tablesToExport as $table) {
    if (!$agentClientId && $societyId && in_array($table, $detectionTables, true)) {
        $where = "society_id = " . (int)$societyId;
    } else {
        $where = "client_id = " . $pdo->quote($targetClient);
    }

    // Apply date filter if table has a timestamp column
    if ($dateFrom && $dateTo && isset($tsColMap[$table])) {
        $tsCol = $tsColMap[$table];
        $where .= " AND `$tsCol` BETWEEN " . $pdo->quote($dateFrom) . " AND " . $pdo->quote($dateTo . ' 23:59:59');
    }

    $rows = $pdo->query("SELECT * FROM `$table` WHERE $where ORDER BY 1")
                 ->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) continue;

    $sql .= "-- $table: " . count($rows) . " rows\n";
    foreach ($rows as $row) {
        $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($row)));
        $vals = implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), array_values($row)));
        $sql .= "INSERT IGNORE INTO `$table` ($cols) VALUES ($vals);\n";
    }
    $sql .= "\n";
    $totalExported += count($rows);
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Log the download
try {
    $performer = $agentClientId ? 'AGENT' : ($session['username'] ?? 'unknown');
    logAudit($pdo, $targetClient, $performer, 'BACKUP_DOWNLOAD', json_encode([
        'from' => $dateFrom, 'to' => $dateTo,
        'tables' => count($tablesToExport), 'total_rows' => $totalExported
    ]));
} catch (Exception $e) {}

// Stream
$filename = "eyesense_backup_{$targetClient}_" . date('Ymd_His') . ".sql";
header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Content-Length: ' . strlen($sql));
header('Cache-Control: no-cache, no-store, must-revalidate');
echo $sql;
exit;
