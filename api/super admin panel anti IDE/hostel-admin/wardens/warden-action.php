<?php
/**
 * Warden Operations Controller (`wardens/warden-action.php`)
 * Handles CRUD operations, status toggles, and duplicate email validation.
 */

require_once __DIR__ . '/../includes/auth.php';

$action = $_REQUEST['action'] ?? '';

try {
    $pdo = get_db_connection();
    
    // Toggle Status
    if ($action === 'toggle_status') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            set_flash_message('error', 'Invalid warden ID.');
            redirect('wardens/warden-list.php');
        }
        
        $stmt = $pdo->prepare("SELECT name, status FROM wardens WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $w = $stmt->fetch();
        
        if ($w) {
            // Cycle status or toggle between Active / On Leave
            $new_status = ($w['status'] === 'Active') ? 'On Leave' : (($w['status'] === 'On Leave') ? 'Inactive' : 'Active');
            $update = $pdo->prepare("UPDATE wardens SET status = ? WHERE id = ?");
            $update->execute([$new_status, $id]);
            set_flash_message('success', "Status for Warden {$w['name']} updated to {$new_status}.");
        } else {
            set_flash_message('error', 'Warden not found.');
        }
        redirect('wardens/warden-list.php');
    }
    
    // Delete Warden
    if ($action === 'delete') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            set_flash_message('error', 'Invalid warden ID.');
            redirect('wardens/warden-list.php');
        }
        
        $stmt = $pdo->prepare("SELECT name FROM wardens WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $w = $stmt->fetch();
        
        if ($w) {
            $delete = $pdo->prepare("DELETE FROM wardens WHERE id = ?");
            $delete->execute([$id]);
            set_flash_message('success', "Warden {$w['name']} record permanently deleted.");
        } else {
            set_flash_message('error', 'Warden not found.');
        }
        redirect('wardens/warden-list.php');
    }
    
    // CSRF Check for POST (`add` and `edit`)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            set_flash_message('error', 'Security token invalid. Please refresh.');
            redirect('wardens/warden-list.php');
        }
        
        $hostel_id    = filter_input(INPUT_POST, 'hostel_id', FILTER_VALIDATE_INT);
        $name         = sanitize($_POST['name'] ?? '');
        $email        = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone        = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
        $joining_date = sanitize($_POST['joining_date'] ?? date('Y-m-d'));
        $status       = sanitize($_POST['status'] ?? 'Active');
        $address      = sanitize($_POST['address'] ?? '');
        
        if (!$hostel_id || empty($name) || !$email || strlen($phone) !== 10 || empty($joining_date)) {
            set_flash_message('error', 'Please fill in all required fields with valid email and 10-digit mobile number.');
            redirect(($action === 'add') ? 'wardens/add-warden.php' : "wardens/edit-warden.php?id=" . (int)$_POST['id']);
        }
        
        // Add Warden
        if ($action === 'add') {
            // Check email uniqueness
            $check = $pdo->prepare("SELECT id FROM wardens WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                set_flash_message('error', "A warden with email address {$email} is already registered.");
                redirect('wardens/add-warden.php');
            }
            
            $stmt = $pdo->prepare("INSERT INTO wardens (hostel_id, name, email, phone, address, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$hostel_id, $name, $email, $phone, $address, $joining_date, $status]);
            
            set_flash_message('success', "Warden {$name} successfully assigned to hostel block!");
            redirect('wardens/warden-list.php');
        }
        
        // Edit Warden
        if ($action === 'edit') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                set_flash_message('error', 'Invalid warden ID.');
                redirect('wardens/warden-list.php');
            }
            
            // Check email uniqueness excluding current warden
            $check = $pdo->prepare("SELECT id FROM wardens WHERE email = ? AND id != ?");
            $check->execute([$email, $id]);
            if ($check->fetch()) {
                set_flash_message('error', "Email address {$email} is already taken by another warden.");
                redirect("wardens/edit-warden.php?id=" . $id);
            }
            
            $stmt = $pdo->prepare("UPDATE wardens SET hostel_id = ?, name = ?, email = ?, phone = ?, address = ?, joining_date = ?, status = ? WHERE id = ?");
            $stmt->execute([$hostel_id, $name, $email, $phone, $address, $joining_date, $status, $id]);
            
            set_flash_message('success', "Warden profile for {$name} successfully updated!");
            redirect('wardens/warden-list.php');
        }
    }
    
} catch (PDOException $e) {
    error_log("Warden Action Error: " . $e->getMessage());
    set_flash_message('error', 'Database error occurred: ' . $e->getMessage());
    redirect('wardens/warden-list.php');
}

redirect('wardens/warden-list.php');
