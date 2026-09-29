<?php
/**
 * EyeSense Cloud Portal — Update Role Permission API
 * 
 * POST /api/update_permission.php
 * Body: { "role": "guard", "permission": "VIEW_SNAPSHOTS", "granted": true }
 * 
 * SA only. Changes take effect immediately.
 */

require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

// Require portal login + MANAGE_ROLES permission
requirePortalLogin();
$session = getPortalSession();
$pdo = get_indsac_db();
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_ROLES');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['role']) || empty($input['permission']) || !isset($input['granted'])) {
    json_error('Missing required fields: role, permission, granted');
}

$role       = $input['role'];
$permission = $input['permission'];
$granted    = (int)(bool)$input['granted'];
$clientId   = $session['client_id'];

// Prevent modifying superadmin permissions
if ($role === 'superadmin') {
    json_error('Cannot modify Super Admin permissions', 403);
}

// Valid permissions list
$validPerms = [
    'VIEW_SNAPSHOTS', 'VIEW_VIDEOS', 'VIEW_LOGS', 'DOWNLOAD_FILES',
    'DOWNLOAD_SQL', 'DELETE_RECORDS', 'EDIT_RECORDS', 'MANAGE_USERS', 'MANAGE_ROLES'
];
if (!in_array($permission, $validPerms)) {
    json_error('Invalid permission key: ' . $permission);
}

// Allow any role to be updated as the UI manages the available roles dynamically.

try {
    $stmt = $pdo->prepare("
        INSERT INTO portal_role_permissions (client_id, role, permission, granted)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE granted = VALUES(granted)
    ");
    $stmt->execute([$clientId, $role, $permission, $granted]);

    // Audit log
    logAudit($pdo, $clientId, $session['username'], 'PERMISSION_UPDATE',
        "$role → $permission: " . ($granted ? 'GRANTED' : 'REVOKED'));

    $societyId = (int)($_SESSION['portal_current_society_id'] ?? 0);
    if ($societyId <= 0 && !empty($clientId)) {
        try {
            $sStmt = $pdo->prepare("SELECT id FROM society WHERE legacy_client_id = ? LIMIT 1");
            $sStmt->execute([$clientId]);
            $societyId = (int)$sStmt->fetchColumn();
        } catch (Throwable $e) {}
    }

    if ($societyId > 0) {
        logSocietyAudit($pdo, $societyId, 'PERMISSION_UPDATED', [
            'actor_name'  => $session['username'] ?? 'admin',
            'target_id'   => $role,
            'target_type' => 'role_permission',
            'new_values'  => [
                'role'       => $role,
                'permission' => $permission,
                'granted'    => (bool)$granted
            ]
        ]);
    }

    json_response([
        'success'    => true,
        'role'       => $role,
        'permission' => $permission,
        'granted'    => (bool)$granted,
    ]);
} catch (Exception $e) {
    json_error('Failed to update permission: ' . $e->getMessage(), 500);
}
