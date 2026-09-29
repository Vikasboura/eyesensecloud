<?php
/**
 * Switch the authenticated user to an explicitly mapped society.
 * POST /api/society/switch
 */
ob_start();

session_start();
require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
require_once __DIR__ . '/../db_setup.php';
require_once __DIR__ . '/../jwt_helper.php';
require_once __DIR__ . '/../society_context_holder.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!DB_CFG_MULTI_SOCIETY_LOGIN_ENABLED) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Multi-society login is disabled.']);
    exit;
}
if (empty($_SESSION['portal_user_id']) || empty($_SESSION['portal_client_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}
if (empty($_SESSION['portal_is_superadmin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only super administrators are authorized to switch between societies.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$societyId = filter_var($input['societyId'] ?? null, FILTER_VALIDATE_INT);
$rememberDefault = filter_var($input['rememberDefault'] ?? false, FILTER_VALIDATE_BOOLEAN);
if (!$societyId || $societyId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid societyId is required.']);
    exit;
}

try {
    $pdo = get_indsac_db();
    $currentEmployee = $pdo->prepare('SELECT global_user_id FROM employees WHERE id = ? AND status = \'active\' LIMIT 1');
    $currentEmployee->execute([(int)$_SESSION['portal_user_id']]);
    $globalUserId = $currentEmployee->fetchColumn();
    if (!$globalUserId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Your account is not linked to a global identity.']);
        exit;
    }

    $target = $pdo->prepare('SELECT s.id, s.society_name, s.legacy_client_id, m.role,
                                    e.id AS employee_id, e.employee_id AS employee_code,
                                    e.full_name, e.access_level
                             FROM user_society_mapping m
                             INNER JOIN society s ON s.id = m.society_id AND s.status = \'ACTIVE\'
                             INNER JOIN employees e ON e.global_user_id = m.user_id
                                AND e.client_id = s.legacy_client_id AND e.status = \'active\'
                             WHERE m.user_id = ? AND m.society_id = ? AND m.status = \'ACTIVE\'
                             LIMIT 1');
    $target->execute([(int)$globalUserId, $societyId]);
    $society = $target->fetch();
    if (!$society) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not authorized to access this society.']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $updateLastActive = $pdo->prepare('UPDATE users SET last_active_society_id = ? WHERE id = ?');
        $updateLastActive->execute([$societyId, (int)$globalUserId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $previousClientId = (string)$_SESSION['portal_client_id'];
    $_SESSION['portal_current_society_id'] = (int)$society['id'];
    $_SESSION['portal_client_id'] = $society['legacy_client_id'];
    $_SESSION['portal_user_id'] = (int)$society['employee_id'];
    $_SESSION['portal_employee_id'] = $society['employee_code'];
    $_SESSION['portal_username'] = $society['full_name'];
    $_SESSION['portal_role'] = $society['role'];
    $_SESSION['portal_is_superadmin'] = strtoupper($society['access_level'] ?? '') === 'ADMIN';
    $_SESSION['portal_cameras'] = [];
    unset($_SESSION['portal_tenant_id'], $_SESSION['portal_member_id']);

    // Issue JWT token with exact claims {userId, societyId, role}
    $jwtToken = jwt_issue_society_token(
        (int)$society['employee_id'],
        (int)$society['id'],
        (string)$society['role']
    );
    $_SESSION['portal_jwt_token'] = $jwtToken;

    // Set request-scoped SocietyContextHolder
    SocietyContextHolder::set(
        (int)$society['employee_id'],
        (int)$society['id'],
        (string)$society['role'],
        $society['legacy_client_id']
    );

    logAudit($pdo, $society['legacy_client_id'], (string)$society['employee_code'], 'SOCIETY_SWITCHED', json_encode([
        'from_client_id' => $previousClientId,
        'to_society_id' => (int)$society['id'],
        'to_client_id' => $society['legacy_client_id']
    ]));

    logSocietyAudit($pdo, (int)$society['id'], 'SOCIETY_SWITCHED', [
        'actor_user_id' => (int)$globalUserId,
        'actor_name'    => (string)$society['full_name'],
        'target_id'     => (string)$society['id'],
        'target_type'   => 'society',
        'old_values'    => ['from_client_id' => $previousClientId],
        'new_values'    => [
            'to_society_id'   => (int)$society['id'],
            'to_client_id'    => $society['legacy_client_id'],
            'to_society_name' => $society['society_name']
        ]
    ]);
    $_SESSION['portal_society_notice'] = 'You are now managing: ' . $society['society_name'];

    echo json_encode([
        'success' => true,
        'societyId' => (int)$society['id'],
        'currentSociety' => $society['society_name'],
        'token' => $jwtToken
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Society switch failed.']);
}
