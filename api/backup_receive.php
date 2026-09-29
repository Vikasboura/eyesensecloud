<?php
/**
 * Indsac Cloud — Backup Data Receiver (v2)
 * 
 * Accepts JSON payloads from the EyeSense backup agent and inserts
 * data into the exact mirrored tables using ON DUPLICATE KEY UPDATE.
 * 
 * POST /api/backup_receive.php
 * Headers: X-API-Key: <key>
 * Body: { "table": "face_logs", "columns": [...], "rows": [[...], ...], "client_id": "IND-..." }
 */

require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';

// Require Agent Auth via Client ID
$clientId = requireAgentAuth();

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['table'], $input['columns'], $input['rows'], $input['client_id'])) {
    json_error('Missing required fields: table, columns, rows, client_id');
}

$tableName = $input['table'];
$columns   = $input['columns'];
$rows      = $input['rows'];
$payloadClientId = $input['client_id'];

// Prevent spoofing: ensuring the payload matches the authenticated client ID
if ($payloadClientId !== $clientId) {
    json_error('Client ID mismatch in payload');
}

// ─── Whitelist of allowed tables ───
$ALLOWED_TABLES = [
    'employees', 'roles', 'user_permissions', 'cameras', 'camera_assignment',
    'premises', 'face_logs', 'face_embeddings', 'vehicle_logs', 'snapshot_logs',
    'video_logs', 'access_requests', 'alert_logs', 'audit_logs', 'daily_summary',
    'guests', 'guest_visits', 'guest_identities', 'guest_documents',
    'registered_vehicles', 'chat_messages', 'system_logs', 'system_settings',
    'bulk_detection_alerts',
];

if (!in_array($tableName, $ALLOWED_TABLES)) {
    json_error("Table '$tableName' is not allowed");
}

if (empty($columns) || empty($rows)) {
    json_error('Columns and rows must not be empty');
}

// Validate column names (alphanumeric, underscores only)
foreach ($columns as $col) {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
        json_error("Invalid column name: $col");
    }
}

// ─── Build INSERT ... ON DUPLICATE KEY UPDATE ───
$db = get_indsac_db();

// ─── Multi-Tenant society_id Resolution (Detection & Camera Tables) ───
$MULTI_TENANT_DETECTION_TABLES = ['cameras', 'face_logs', 'vehicle_logs', 'snapshot_logs', 'video_logs'];

if (in_array($tableName, $MULTI_TENANT_DETECTION_TABLES, true)) {
    // Resolve society_id once per request cache using the authenticated client_id
    static $societyIdCache = [];
    if (!array_key_exists($clientId, $societyIdCache)) {
        $lookupStmt = $db->prepare("SELECT id FROM society WHERE legacy_client_id = ? LIMIT 1");
        $lookupStmt->execute([$clientId]);
        $val = $lookupStmt->fetchColumn();
        $societyIdCache[$clientId] = ($val !== false && $val !== null) ? (int)$val : null;
    }
    $resolvedSocietyId = $societyIdCache[$clientId];

    if ($resolvedSocietyId !== null) {
        // If society_id is not already sent in the payload columns, append it
        if (!in_array('society_id', $columns, true)) {
            $expectedColCount = count($columns);
            $columns[] = 'society_id';

            foreach ($rows as $i => &$row) {
                // Defensive check: ensure positional alignment before appending
                if (count($row) === $expectedColCount) {
                    $row[] = $resolvedSocietyId;
                } else {
                    error_log("[BACKUP_RECEIVE] Warning: Row $i in table '$tableName' has count mismatch (" . count($row) . " vs expected $expectedColCount) - skipping society_id injection to let validator report it");
                }
            }
            unset($row);
        }
    } else {
        error_log("[BACKUP_RECEIVE] Warning: Unmapped client_id '{$clientId}' for table '{$tableName}' - proceeding with society_id = NULL");
    }
}

$colList = implode(', ', array_map(function($c) { return "`$c`"; }, $columns));
$placeholders = implode(', ', array_fill(0, count($columns), '?'));

// Build UPDATE clause: update every non-PK column
$pkColumns = getPrimaryKeyColumns($tableName);
$updateParts = [];
foreach ($columns as $col) {
    if (!in_array($col, $pkColumns)) {
        $updateParts[] = "`$col` = VALUES(`$col`)";
    }
}
$updateClause = !empty($updateParts) ? implode(', ', $updateParts) : '`client_id` = VALUES(`client_id`)';

$sql = "INSERT INTO `$tableName` ($colList) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateClause";

$stmt = $db->prepare($sql);
if (!$stmt) {
    $errInfo = $db->errorInfo();
    json_error("Prepare failed: " . ($errInfo[2] ?? 'Unknown PDO error'));
}

$inserted = 0;
$updated  = 0;
$errors   = [];

foreach ($rows as $i => $row) {
    if (count($row) !== count($columns)) {
        $errors[] = "Row $i: column count mismatch";
        continue;
    }

    // Execute with positional parameters
    $values = array_values($row);
    
    if ($stmt->execute($values)) {
        if ($stmt->rowCount() === 1) {
            $inserted++;
        } elseif ($stmt->rowCount() === 2) {
            // ON DUPLICATE KEY UPDATE counts as 2 affected rows if updated
            $updated++;
        }
    } else {
        $errInfo = $stmt->errorInfo();
        $errors[] = "Row $i: " . ($errInfo[2] ?? 'Unknown error');
    }
}

// PDO does not require explicit close methods
$stmt = null;
$db = null;

echo json_encode([
    'success'  => true,
    'table'    => $tableName,
    'inserted' => $inserted,
    'updated'  => $updated,
    'errors'   => $errors,
]);
exit;


/**
 * Get the primary key columns for a table to build the UPDATE clause correctly.
 */
function getPrimaryKeyColumns(string $table): array {
    // Map tables to their unique/primary key columns
    $pkMap = [
        'employees'           => ['id'],
        'roles'               => ['client_id', 'name'],
        'user_permissions'    => ['id'],
        'cameras'             => ['id'],
        'camera_assignment'   => ['id'],
        'premises'            => ['id'],
        'face_logs'           => ['id'],
        'face_embeddings'     => ['id'],
        'vehicle_logs'        => ['id'],
        'snapshot_logs'       => ['id'],
        'video_logs'          => ['id'],
        'access_requests'     => ['id'],
        'alert_logs'          => ['id'],
        'audit_logs'          => ['id'],
        'daily_summary'       => ['id'],
        'guests'              => ['id'],
        'guest_visits'        => ['id'],
        'guest_identities'    => ['id'],
        'guest_documents'     => ['id'],
        'registered_vehicles' => ['id'],
        'chat_messages'       => ['id'],
        'system_logs'         => ['id'],
        'system_settings'     => ['client_id', 'setting_key'],
        'bulk_detection_alerts' => ['id'],
    ];
    return $pkMap[$table] ?? ['id'];
}
