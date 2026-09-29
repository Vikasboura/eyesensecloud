<?php
/**
 * Central Controller (`hostel/hostel-action.php`)
 * Handles all CRUD operations, file uploads, AJAX auto-code calculation, and status toggles.
 */

require_once __DIR__ . '/../includes/auth.php';

$action = $_REQUEST['action'] ?? '';

try {
    $pdo = get_db_connection();
    
    // ==========================================
    // 1. AJAX: Auto-Generate Hostel Code
    // ==========================================
    if ($action === 'get_code') {
        header('Content-Type: application/json');
        $type = sanitize($_GET['type'] ?? '');
        $prefix = get_hostel_code_prefix($type);
        
        if (empty($prefix)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid hostel type']);
            exit;
        }
        
        // Find highest existing code with this prefix
        $stmt = $pdo->prepare("SELECT hostel_code FROM hostels WHERE hostel_code LIKE ? ORDER BY LENGTH(hostel_code) DESC, hostel_code DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last_code = $stmt->fetchColumn();
        
        if ($last_code) {
            // Extract numeric part
            $number_part = (int) preg_replace('/[^0-9]/', '', substr($last_code, strlen($prefix)));
            $next_number = $number_part + 1;
        } else {
            $next_number = 1;
        }
        
        $new_code = $prefix . str_pad($next_number, 3, '0', STR_PAD_LEFT);
        echo json_encode([
            'status' => 'success',
            'prefix' => $prefix,
            'code'   => $new_code
        ]);
        exit;
    }
    
    // ==========================================
    // 2. GET/POST: Toggle Status (`Active` <-> `Inactive`)
    // ==========================================
    if ($action === 'toggle_status') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            set_flash_message('error', 'Invalid hostel ID.');
            redirect('hostel/hostel-list.php');
        }
        
        $stmt = $pdo->prepare("SELECT hostel_name, status FROM hostels WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        
        if ($record) {
            $new_status = ($record['status'] === 'Active') ? 'Inactive' : 'Active';
            $update = $pdo->prepare("UPDATE hostels SET status = ? WHERE id = ?");
            $update->execute([$new_status, $id]);
            set_flash_message('success', "Status for '{$record['hostel_name']}' changed to {$new_status}.");
        } else {
            set_flash_message('error', 'Hostel not found.');
        }
        redirect('hostel/hostel-list.php');
    }
    
    // ==========================================
    // 3. GET/POST: Delete Hostel Record
    // ==========================================
    if ($action === 'delete') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            set_flash_message('error', 'Invalid hostel ID.');
            redirect('hostel/hostel-list.php');
        }
        
        // Fetch existing image to delete from disk
        $stmt = $pdo->prepare("SELECT hostel_name, hostel_image FROM hostels WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        
        if ($record) {
            // Delete record via prepared statement
            $delete = $pdo->prepare("DELETE FROM hostels WHERE id = ?");
            $delete->execute([$id]);
            
            // Unlink image file safely if present
            if (!empty($record['hostel_image']) && file_exists(UPLOAD_DIR . $record['hostel_image'])) {
                @unlink(UPLOAD_DIR . $record['hostel_image']);
            }
            
            set_flash_message('success', "Hostel '{$record['hostel_name']}' has been permanently deleted.");
        } else {
            set_flash_message('error', 'Hostel not found.');
        }
        redirect('hostel/hostel-list.php');
    }
    
    // Verify CSRF for POST actions (`add` & `edit`)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            set_flash_message('error', 'Security token invalid. Please refresh and try again.');
            redirect('hostel/hostel-list.php');
        }
        
        // Common Input Sanitation
        $hostel_name       = sanitize($_POST['hostel_name'] ?? '');
        $hostel_type       = sanitize($_POST['hostel_type'] ?? '');
        $description       = sanitize($_POST['description'] ?? '');
        $address           = sanitize($_POST['address'] ?? '');
        $city              = sanitize($_POST['city'] ?? '');
        $state             = sanitize($_POST['state'] ?? '');
        $country           = sanitize($_POST['country'] ?? 'India');
        $pincode           = sanitize($_POST['pincode'] ?? '');
        $contact_number    = sanitize($_POST['contact_number'] ?? '');
        $email             = sanitize($_POST['email'] ?? '');
        $emergency_contact = sanitize($_POST['emergency_contact'] ?? '');
        $capacity          = (int) ($_POST['capacity'] ?? 0);
        $hostel_rules      = sanitize($_POST['hostel_rules'] ?? '');
        $status            = sanitize($_POST['status'] ?? 'Active');
        
        // Validation Checks
        $errors = [];
        if (empty($hostel_name)) $errors[] = "Hostel Name is required.";
        if (empty($hostel_type)) $errors[] = "Hostel Type is required.";
        if (empty($address))     $errors[] = "Address is required.";
        if (empty($city))        $errors[] = "City is required.";
        if (empty($state))       $errors[] = "State is required.";
        if (empty($pincode))     $errors[] = "Pincode is required.";
        if (!preg_match('/^[0-9]{10}$/', $contact_number)) {
            $errors[] = "Contact number must be exactly 10 digits.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email address is required.";
        }
        if ($capacity <= 0) {
            $errors[] = "Bed capacity must be greater than zero.";
        }
        
        if (!empty($errors)) {
            set_flash_message('error', implode(' ', $errors));
            redirect(($action === 'add') ? 'hostel/add-hostel.php' : "hostel/edit-hostel.php?id=" . (int)$_POST['id']);
        }
        
        // Handle File Upload (`hostel_image`)
        $image_filename = $_POST['existing_image'] ?? null;
        if (!empty($_FILES['hostel_image']['name'])) {
            $file = $_FILES['hostel_image'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                // Check size (Max 5MB)
                if ($file['size'] > 5 * 1024 * 1024) {
                    set_flash_message('error', 'Image size exceeds 5MB limit.');
                    redirect(($action === 'add') ? 'hostel/add-hostel.php' : "hostel/edit-hostel.php?id=" . (int)$_POST['id']);
                }
                
                // Check MIME / extension
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png'];
                if (!in_array($ext, $allowed)) {
                    set_flash_message('error', 'Only JPG, JPEG, and PNG images are allowed.');
                    redirect(($action === 'add') ? 'hostel/add-hostel.php' : "hostel/edit-hostel.php?id=" . (int)$_POST['id']);
                }
                
                // Ensure upload directory exists
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0777, true);
                }
                
                $new_filename = uniqid('hostel_') . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $new_filename)) {
                    // Delete old image if replacing during edit
                    if ($action === 'edit' && !empty($image_filename) && file_exists(UPLOAD_DIR . $image_filename)) {
                        @unlink(UPLOAD_DIR . $image_filename);
                    }
                    $image_filename = $new_filename;
                }
            }
        }
        
        // ==========================================
        // 4. POST: Add New Hostel
        // ==========================================
        if ($action === 'add') {
            $hostel_code = sanitize($_POST['hostel_code'] ?? '');
            
            // Verify code uniqueness or auto-compute if blank/conflicting
            $check = $pdo->prepare("SELECT COUNT(*) FROM hostels WHERE hostel_code = ?");
            $check->execute([$hostel_code]);
            if (empty($hostel_code) || $check->fetchColumn() > 0) {
                $prefix = get_hostel_code_prefix($hostel_type);
                $stmt = $pdo->prepare("SELECT hostel_code FROM hostels WHERE hostel_code LIKE ? ORDER BY LENGTH(hostel_code) DESC, hostel_code DESC LIMIT 1");
                $stmt->execute([$prefix . '%']);
                $last_code = $stmt->fetchColumn();
                $number_part = $last_code ? ((int) preg_replace('/[^0-9]/', '', substr($last_code, strlen($prefix))) + 1) : 1;
                $hostel_code = $prefix . str_pad($number_part, 3, '0', STR_PAD_LEFT);
            }
            
            $sql = "INSERT INTO hostels (
                        hostel_name, hostel_code, hostel_type, description, address, 
                        city, state, country, pincode, contact_number, email, 
                        emergency_contact, capacity, hostel_image, hostel_rules, status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $hostel_name, $hostel_code, $hostel_type, $description, $address,
                $city, $state, $country, $pincode, $contact_number, $email,
                $emergency_contact, $capacity, $image_filename, $hostel_rules, $status
            ]);
            
            set_flash_message('success', "Hostel '{$hostel_name}' ({$hostel_code}) successfully registered!");
            redirect('hostel/hostel-list.php');
        }
        
        // ==========================================
        // 5. POST: Edit Existing Hostel
        // ==========================================
        if ($action === 'edit') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                set_flash_message('error', 'Invalid hostel ID.');
                redirect('hostel/hostel-list.php');
            }
            
            $sql = "UPDATE hostels SET 
                        hostel_name = ?, hostel_type = ?, description = ?, address = ?, 
                        city = ?, state = ?, country = ?, pincode = ?, contact_number = ?, 
                        email = ?, emergency_contact = ?, capacity = ?, hostel_image = ?, 
                        hostel_rules = ?, status = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $hostel_name, $hostel_type, $description, $address,
                $city, $state, $country, $pincode, $contact_number,
                $email, $emergency_contact, $capacity, $image_filename,
                $hostel_rules, $status, $id
            ]);
            
            set_flash_message('success', "Hostel profile for '{$hostel_name}' has been updated successfully!");
            redirect("hostel/hostel-view.php?id={$id}");
        }
    }

} catch (PDOException $e) {
    error_log("Hostel Action Error: " . $e->getMessage());
    set_flash_message('error', 'Database operation failed: ' . $e->getMessage());
    redirect('hostel/hostel-list.php');
}

// Fallback redirect
redirect('hostel/hostel-list.php');
