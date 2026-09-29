<?php
/**
 * Core Application Configuration & Utility Helper Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application Constants
define('APP_NAME', 'Super Admin Panel - Hostel Management');
define('APP_VERSION', '1.0.0');

// Dynamically determine BASE_URL so it works smoothly on any XAMPP/WAMP directory structure
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Calculate path relative to document root
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    // Find the position of hostel-admin in the path
    $pos = strpos($script_name, '/hostel-admin/');
    if ($pos !== false) {
        $base_path = substr($script_name, 0, $pos + strlen('/hostel-admin/'));
    } else {
        // Fallback or root directory installation
        $base_path = rtrim(dirname($script_name), '/') . '/';
        if ($base_path === '//') $base_path = '/';
    }
    
    define('BASE_URL', $protocol . $host . $base_path);
}

// Absolute filesystem path constants
define('ROOT_DIR', realpath(__DIR__ . '/../') . '/');
define('UPLOAD_DIR', ROOT_DIR . 'uploads/hostel/');
define('UPLOAD_URL', BASE_URL . 'uploads/hostel/');

/**
 * Sanitize user input strings
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Safe redirect helper
 */
function redirect($url) {
    // If URL doesn't start with http, prepend BASE_URL
    if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
        $url = BASE_URL . ltrim($url, '/');
    }
    header("Location: " . $url);
    exit();
}

/**
 * Generate CSRF Token and store in session
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify submitted CSRF Token against session
 */
function verify_csrf($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set session flash notification message
 * $type can be: 'success', 'error', 'warning', 'info'
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash notification message
 */
function get_flash_message() {
    if (!empty($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Format date nicely for UI
 */
function format_date($date_string, $format = 'd M Y, h:i A') {
    if (empty($date_string) || $date_string === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date($format, strtotime($date_string));
}

/**
 * Get Hostel Code Prefix based on Hostel Type
 */
function get_hostel_code_prefix($type) {
    $prefixes = [
        'Boys'  => 'BH',
        'Girls' => 'GH',
        'Co-Ed' => 'CH',
        'PG'    => 'PG',
        'Staff' => 'SH'
    ];
    return $prefixes[$type] ?? 'HX';
}
