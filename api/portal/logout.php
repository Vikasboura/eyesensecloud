<?php
/** Portal Logout */
require_once __DIR__ . '/../portal_auth.php';
portal_session_start();

$logoutContext = [
    'event_id' => bin2hex(random_bytes(8)),
    'user_id' => $_SESSION['portal_user_id'] ?? null,
    'client_id' => $_SESSION['portal_client_id'] ?? '',
    'username' => $_SESSION['portal_username'] ?? '',
    'ip' => getClientIp(),
];

try {
    $pdo = get_indsac_db();
    if (!empty($_SESSION['portal_username'])) {
        logAudit($pdo, $_SESSION['portal_client_id'] ?? '', $_SESSION['portal_username'], 'LOGOUT', 'Portal logout');
    }
} catch (Throwable $e) {
    logger_exception('Logout database audit failed', $e, $logoutContext);
}
logger_info('User logout', $logoutContext, __FILE__, __LINE__);

// Mark login session as closed
if (!empty($_SESSION['portal_login_session_id'])) {
    try {
        portalEnsureLoginSessionActivityColumn($pdo);
        $pdo->prepare("UPDATE portal_login_sessions SET is_active=0, logout_at=NOW(), last_seen_at=NOW() WHERE id=?")
            ->execute([(int)$_SESSION['portal_login_session_id']]);
    } catch (Throwable $e) {}
}

session_destroy();
header("Location: login.php");
exit;
