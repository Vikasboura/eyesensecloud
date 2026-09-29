<?php
/**
 * Authentication & Permission Middleware
 * Ensures user is authenticated and possesses Super Admin privileges.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure core config and database connection are loaded
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../config/db.php';
}

if (isset($_SESSION['portal_client_id']) && empty($_SESSION['hostel_management_enabled'])) {
    echo "<script>window.top.location.href = '../../../portal/dashboard.php?error=Access+Denied:+You+do+not+have+permission+to+access+Hostel+Management.';</script>";
    exit;
}

// Check session login state
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    set_flash_message('warning', 'Please log in to access the Super Admin Panel.');
    redirect('login.php');
}

// Role verification (User requested Super Admin access check)
if (empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'Super Admin') {
    set_flash_message('error', 'Access Denied: Super Admin permissions required.');
    redirect('login.php');
}

// Session security check against hijacking/timeout (optional enhancement)
if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
} elseif (time() - $_SESSION['last_activity'] > 3600) {
    // Session expired after 1 hour of inactivity
    session_unset();
    session_destroy();
    session_start();
    set_flash_message('info', 'Your session expired due to inactivity. Please log in again.');
    redirect('login.php');
}
$_SESSION['last_activity'] = time();
