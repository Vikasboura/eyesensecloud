<?php
/**
 * Settings & Profile Controller (`settings/settings-action.php`)
 * Handles profile modification, secure bcrypt password updates, and system preference saving.
 */

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('settings/index.php');
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    set_flash_message('error', 'Security token invalid. Please refresh and try again.');
    redirect('settings/index.php');
}

$action = $_POST['action'] ?? '';

try {
    $pdo = get_db_connection();
    $admin_id = $_SESSION['admin_id'] ?? 1;
    
    // ==========================================
    // 1. Update Profile Information
    // ==========================================
    if ($action === 'update_profile') {
        $name  = sanitize($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        
        if (empty($name) || !$email) {
            set_flash_message('error', 'Please enter a valid name and email address.');
            redirect('settings/index.php');
        }
        
        // Check if email is used by another admin
        $check = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
        $check->execute([$email, $admin_id]);
        if ($check->fetch()) {
            set_flash_message('error', 'This email address is already in use by another admin account.');
            redirect('settings/index.php');
        }
        
        $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $admin_id]);
        
        // Update Session details
        $_SESSION['admin_name']  = $name;
        $_SESSION['admin_email'] = $email;
        
        set_flash_message('success', 'Admin profile information updated successfully!');
        redirect('settings/index.php');
    }
    
    // ==========================================
    // 2. Change Security Password
    // ==========================================
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($current) || empty($new) || empty($confirm)) {
            set_flash_message('error', 'Please fill in all password fields.');
            redirect('settings/index.php');
        }
        
        if (strlen($new) < 6) {
            set_flash_message('error', 'New password must be at least 6 characters long.');
            redirect('settings/index.php');
        }
        
        if ($new !== $confirm) {
            set_flash_message('error', 'New password and confirmation password do not match.');
            redirect('settings/index.php');
        }
        
        // Verify current password against stored DB hash
        $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$admin_id]);
        $stored = $stmt->fetchColumn();
        
        $verified = false;
        if (password_verify($current, $stored)) {
            $verified = true;
        } elseif ($current === $stored) {
            // Fallback check if stored in plain text or direct match
            $verified = true;
        }
        
        if (!$verified) {
            set_flash_message('error', 'The current password you entered is incorrect.');
            redirect('settings/index.php');
        }
        
        // Hash new password using bcrypt
        $new_hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
        $update = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $update->execute([$new_hash, $admin_id]);
        
        set_flash_message('success', 'Your login password has been changed securely! Please use your new password next time you login.');
        redirect('settings/index.php');
    }
    
    // ==========================================
    // 3. Update General System Settings
    // ==========================================
    if ($action === 'update_settings') {
        $settings = [
            'site_name'        => sanitize($_POST['site_name'] ?? APP_NAME),
            'currency_symbol'  => sanitize($_POST['currency_symbol'] ?? 'â‚¹'),
            'contact_email'    => filter_var(trim($_POST['contact_email'] ?? 'admin@hostel.com'), FILTER_VALIDATE_EMAIL) ?: 'admin@hostel.com',
            'maintenance_mode' => sanitize($_POST['maintenance_mode'] ?? '0')
        ];
        
        $stmt = $pdo->prepare("INSERT INTO hostel_system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        
        set_flash_message('success', 'General system configuration saved successfully!');
        redirect('settings/index.php');
    }

} catch (PDOException $e) {
    error_log("Settings Action Error: " . $e->getMessage());
    set_flash_message('error', 'Database operation failed: ' . $e->getMessage());
    redirect('settings/index.php');
}

redirect('settings/index.php');
