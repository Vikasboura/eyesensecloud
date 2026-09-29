<?php
/**
 * EyeSense Cloud Portal — In-app Notification Helpers
 */

require_once __DIR__ . '/portal_config.php';

function portalEnsureNotificationsTable(PDO $pdo): void {
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS portal_notifications (
        id INT NOT NULL AUTO_INCREMENT,
        client_id VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
        recipient_employee_id VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
        title VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        message TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        kind VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INFO',
        target_url VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        payload_json TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        read_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_client_recipient (client_id, recipient_employee_id),
        KEY idx_client_unread (client_id, recipient_employee_id, is_read, deleted_at),
        KEY idx_client_created (client_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ready = true;
}

function portalNotify(
    PDO $pdo,
    string $clientId,
    string $recipientEmployeeId,
    string $title,
    string $message,
    string $kind = 'INFO',
    string $targetUrl = '',
    array $payload = []
): void {
    $recipientEmployeeId = trim($recipientEmployeeId);
    if ($recipientEmployeeId === '') {
        return;
    }

    portalEnsureNotificationsTable($pdo);

    $stmt = $pdo->prepare("INSERT INTO portal_notifications
        (client_id, recipient_employee_id, title, message, kind, target_url, payload_json)
        VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        $clientId,
        $recipientEmployeeId,
        trim($title),
        trim($message),
        strtoupper(trim($kind) ?: 'INFO'),
        $targetUrl !== '' ? trim($targetUrl) : null,
        !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function portalNotifyMany(
    PDO $pdo,
    string $clientId,
    array $recipientEmployeeIds,
    string $title,
    string $message,
    string $kind = 'INFO',
    string $targetUrl = '',
    array $payload = []
): void {
    $seen = [];
    foreach ($recipientEmployeeIds as $employeeId) {
        $employeeId = trim((string)$employeeId);
        if ($employeeId === '' || isset($seen[$employeeId])) {
            continue;
        }
        $seen[$employeeId] = true;
        portalNotify($pdo, $clientId, $employeeId, $title, $message, $kind, $targetUrl, $payload);
    }
}

function portalGetComplaintParticipantEmployeeIds(PDO $pdo, string $clientId, int $complaintId): array {
    $ids = [];

    $ownerStmt = $pdo->prepare("SELECT employee_id FROM member_complaints WHERE id=? AND client_id=? LIMIT 1");
    $ownerStmt->execute([$complaintId, $clientId]);
    $ownerId = trim((string)$ownerStmt->fetchColumn());
    if ($ownerId !== '') {
        $ids[] = $ownerId;
    }

    $assigneeStmt = $pdo->prepare("SELECT assigned_to FROM complaint_assignments WHERE complaint_id=? AND client_id=? AND removed_at IS NULL");
    $assigneeStmt->execute([$complaintId, $clientId]);
    foreach ($assigneeStmt->fetchAll(PDO::FETCH_COLUMN) as $assigneeId) {
        $assigneeId = trim((string)$assigneeId);
        if ($assigneeId !== '' && !in_array($assigneeId, $ids, true)) {
            $ids[] = $assigneeId;
        }
    }

    return $ids;
}

function portalGetMaintenanceAdmins(PDO $pdo, string $clientId): array {
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.employee_id
        FROM employees e
        LEFT JOIN portal_role_permissions prp
          ON prp.role = e.role
         AND prp.permission = 'MANAGE_MAINTENANCE'
         AND prp.granted = 1
         AND prp.client_id IN (?, '__DEFAULT__')
        WHERE e.client_id = ?
          AND e.status = 'active'
          AND e.is_deleted = 0
          AND (
              UPPER(e.role) IN ('ADMIN', 'SUPERADMIN')
              OR e.access_level = 'ADMIN'
              OR prp.role IS NOT NULL
          )
    ");
    $stmt->execute([$clientId, $clientId]);
    return array_values(array_filter(array_map(static fn($v) => trim((string)$v), $stmt->fetchAll(PDO::FETCH_COLUMN))));
}

function portalGetMemberEmployeeId(PDO $pdo, string $clientId, int $memberId): string {
    if ($memberId <= 0) {
        return '';
    }

    $stmt = $pdo->prepare("SELECT employee_id FROM society_members WHERE id=? AND client_id=? LIMIT 1");
    $stmt->execute([$memberId, $clientId]);
    return trim((string)$stmt->fetchColumn());
}

function portalNotifyMaintenanceAdmins(
    PDO $pdo,
    string $clientId,
    string $title,
    string $message,
    string $kind = 'INFO',
    string $targetUrl = '',
    array $payload = [],
    array $excludeEmployeeIds = []
): void {
    $excluded = array_flip(array_filter(array_map(static fn($v) => trim((string)$v), $excludeEmployeeIds)));
    $recipients = array_values(array_filter(
        portalGetMaintenanceAdmins($pdo, $clientId),
        static fn($employeeId) => $employeeId !== '' && !isset($excluded[$employeeId])
    ));

    portalNotifyMany($pdo, $clientId, $recipients, $title, $message, $kind, $targetUrl, $payload);
}
