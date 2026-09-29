<?php
/**
 * Main Application Entry Point (`hostel-admin/index.php`)
 * Checks authentication status and routes accordingly.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
