<?php
/**
 * EyeSense Cloud Portal — In-app Notifications API
 * Actions: list, count, mark_read, mark_all_read, delete, delete_all
 */

ob_start();
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/portal_notifications_helper.php';

header('Content-Type: application/json; charset=utf-8');

requirePortalLogin();
$pdo = get_indsac_db();
$session = getPortalSession();
$cid = $session['client_id'];

$rawInput = json_decode(file_get_contents('php://input'), true) ?: [];
$input = !empty($rawInput) ? $rawInput : array_merge($_GET, $_POST);
$action = trim((string)($input['action'] ?? 'list'));

function _notificationEmployeeCandidates(PDO $pdo, string $cid, array $session): array {
    $ids = [];

    $sessionEmp = trim((string)($_SESSION['portal_employee_id'] ?? ''));
    if ($sessionEmp !== '') {
        $ids[] = $sessionEmp;
    }

    if (!empty($ids)) {
        return $ids;
    }

    $userId = (int)($session['user_id'] ?? 0);
    if ($userId > 0) {
        try {
            $stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE id=? AND client_id=? AND is_deleted=0 LIMIT 1");
            $stmt->execute([$userId, $cid]);
            $emp = trim((string)$stmt->fetchColumn());
            if ($emp !== '') {
                $ids[] = $emp;
            }
        } catch (Throwable $e) {
        }
    }

    $username = trim((string)($session['username'] ?? ''));
    if ($username !== '') {
        try {
            $stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE client_id=? AND full_name=? AND is_deleted=0 LIMIT 1");
            $stmt->execute([$cid, $username]);
            $emp = trim((string)$stmt->fetchColumn());
            if ($emp !== '' && !in_array($emp, $ids, true)) {
                $ids[] = $emp;
            }
        } catch (Throwable $e) {
        }
    }

    return $ids;
}

try {
    portalEnsureNotificationsTable($pdo);

    $empIds = _notificationEmployeeCandidates($pdo, $cid, $session);
    if (empty($empIds)) {
        json_response(['notifications' => [], 'unread_count' => 0]);
    }

    $placeholders = implode(',', array_fill(0, count($empIds), '?'));

    switch ($action) {
        case 'count':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM portal_notifications
                WHERE client_id=? AND recipient_employee_id IN ({$placeholders})
                  AND is_read=0 AND deleted_at IS NULL");
            $stmt->execute(array_merge([$cid], $empIds));
            json_response(['unread_count' => (int)$stmt->fetchColumn()]);
            break;

        case 'list':
            $limit = (int)($input['limit'] ?? 40);
            if ($limit < 1) {
                $limit = 1;
            }
            if ($limit > 200) {
                $limit = 200;
            }

            $rowsStmt = $pdo->prepare("SELECT id, title, message, kind, target_url, payload_json, is_read, created_at
                FROM portal_notifications
                WHERE client_id=? AND recipient_employee_id IN ({$placeholders})
                  AND deleted_at IS NULL
                ORDER BY created_at DESC, id DESC
                LIMIT {$limit}");
            $rowsStmt->execute(array_merge([$cid], $empIds));
            $rows = $rowsStmt->fetchAll();

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM portal_notifications
                WHERE client_id=? AND recipient_employee_id IN ({$placeholders})
                  AND is_read=0 AND deleted_at IS NULL");
            $countStmt->execute(array_merge([$cid], $empIds));
            $unreadCount = (int)$countStmt->fetchColumn();

            json_response(['notifications' => $rows, 'unread_count' => $unreadCount]);
            break;

        case 'mark_read':
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                json_error('Notification id is required');
            }
            $stmt = $pdo->prepare("UPDATE portal_notifications
                SET is_read=1, read_at=NOW()
                WHERE id=? AND client_id=? AND recipient_employee_id IN ({$placeholders}) AND deleted_at IS NULL");
            $stmt->execute(array_merge([$id, $cid], $empIds));
            json_response(['success' => true]);
            break;

        case 'mark_all_read':
            $stmt = $pdo->prepare("UPDATE portal_notifications
                SET is_read=1, read_at=NOW()
                WHERE client_id=? AND recipient_employee_id IN ({$placeholders})
                  AND is_read=0 AND deleted_at IS NULL");
            $stmt->execute(array_merge([$cid], $empIds));
            json_response(['success' => true]);
            break;

        case 'delete':
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                json_error('Notification id is required');
            }
            $stmt = $pdo->prepare("UPDATE portal_notifications
                SET deleted_at=NOW()
                WHERE id=? AND client_id=? AND recipient_employee_id IN ({$placeholders}) AND deleted_at IS NULL");
            $stmt->execute(array_merge([$id, $cid], $empIds));
            json_response(['success' => true]);
            break;

        case 'delete_all':
            $stmt = $pdo->prepare("UPDATE portal_notifications
                SET deleted_at=NOW()
                WHERE client_id=? AND recipient_employee_id IN ({$placeholders}) AND deleted_at IS NULL");
            $stmt->execute(array_merge([$cid], $empIds));
            json_response(['success' => true]);
            break;

        default:
            json_error('Unknown action');
    }
} catch (Throwable $e) {
    ob_end_clean();
    json_error('Error: ' . $e->getMessage(), 500);
}
