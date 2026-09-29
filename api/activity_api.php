<?php
/**
 * EyeSense Cloud Portal — Activity / Login Session API
 * Actions: list_sessions, logout_session, dashboard_summary
 *
 * Requires: SUPERADMIN or admin with MANAGE_MAINTENANCE permission.
 */
ob_start();
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';

header('Content-Type: application/json; charset=utf-8');
requirePortalLogin();

$pdo     = get_indsac_db();
$session = getPortalSession();
$cid     = $session['client_id'];
$isSA    = !empty($session['is_superadmin']);
$perms   = getAllPermissions($pdo, $cid, $session['role']);
$isAdmin = $isSA || !empty($perms['MANAGE_MAINTENANCE']);

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? ($_GET['action'] ?? '');
$activeWindow = portalSessionActiveWindowSeconds();

if ($action === 'heartbeat') {
    portalTouchLoginSession($pdo);
    json_response(['success' => true, 'active_window_seconds' => $activeWindow]);
}

if (!$isAdmin) json_error('Access denied', 403);

try {
    portalEnsureLoginSessionActivityColumn($pdo);
    portalExpireStaleLoginSessions($pdo, $cid);

    switch ($action) {

        // ── List all login sessions ────────────────────────────────────────
        case 'list_sessions':
            $limit  = min(200, max(10, (int)($input['limit'] ?? $_GET['limit'] ?? 50)));
            $filter = trim($input['filter'] ?? $_GET['filter'] ?? '');
            $where  = ['client_id = ?'];
            $params = [$cid];
            $liveExpr = "is_active = 1 AND TIMESTAMPDIFF(SECOND, COALESCE(last_seen_at, login_at), NOW()) <= ?";
            if ($filter === 'active') {
                $where[] = $liveExpr;
                $params[] = $activeWindow;
            } elseif ($filter === 'today') {
                $where[] = 'DATE(login_at) = CURDATE()';
            }
            $stmt = $pdo->prepare("
                SELECT *,
                  ({$liveExpr}) AS is_live,
                  TIMESTAMPDIFF(SECOND, COALESCE(last_seen_at, login_at), NOW()) AS seconds_since_seen
                FROM portal_login_sessions
                WHERE " . implode(' AND ', $where) . "
                ORDER BY login_at DESC
                LIMIT ?
            ");
            $selectParams = array_merge([$activeWindow], $params, [$limit]);
            $stmt->execute($selectParams);
            json_response([
                'sessions' => $stmt->fetchAll(),
                'active_window_seconds' => $activeWindow,
            ]);
            break;

        // ── Force-logout (mark session as inactive) ────────────────────────
        case 'logout_session':
            $id = (int)($input['id'] ?? 0);
            if (!$id) json_error('Session ID required');
            $pdo->prepare("UPDATE portal_login_sessions SET is_active=0, logout_at=NOW() WHERE id=? AND client_id=?"
            )->execute([$id, $cid]);
            logAudit($pdo, $cid, $session['username'] ?? '', 'FORCE_LOGOUT_SESSION', "Session #{$id} force-logged out");
            json_response(['success' => true, 'message' => 'Session marked as logged out']);
            break;

        // ── Dashboard summary ──────────────────────────────────────────────
        case 'dashboard_summary':
            $stmt = $pdo->prepare("
                SELECT
                  COUNT(*) AS total_logins,
                  SUM(is_active = 1 AND TIMESTAMPDIFF(SECOND, COALESCE(last_seen_at, login_at), NOW()) <= ?) AS active_now,
                  COUNT(DISTINCT employee_id) AS unique_users,
                  SUM(DATE(login_at) = CURDATE()) AS logins_today
                FROM portal_login_sessions
                WHERE client_id = ?
            ");
            $stmt->execute([$activeWindow, $cid]);
            $summary = $stmt->fetch();

            // Recent audit trail
            $auditStmt = $pdo->prepare("
                SELECT * FROM portal_audit_logs
                WHERE client_id = ?
                ORDER BY created_at DESC
                LIMIT 30
            ");
            $auditStmt->execute([$cid]);
            json_response(['summary' => $summary, 'recent_audits' => $auditStmt->fetchAll()]);
            break;

        default:
            json_error('Unknown action');
    }
} catch (PDOException $e) {
    ob_end_clean();
    json_error('Database error: ' . $e->getMessage(), 500);
}
