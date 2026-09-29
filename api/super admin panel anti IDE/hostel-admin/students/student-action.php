<?php
/**
 * Student Operations Controller (`students/student-action.php`)
 * Handles Student admissions, room reassignments, status toggling, and code generation checks.
 */

require_once __DIR__ . '/../includes/auth.php';

$action = $_REQUEST['action'] ?? '';

try {
    $pdo = get_db_connection();
    
    // Toggle Status
    if ($action === 'toggle_status') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            set_flash_message('error', 'Invalid student ID.');
            redirect('students/student-list.php');
        }
        
        $stmt = $pdo->prepare("SELECT name, status FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        
        if ($s) {
            $new_status = ($s['status'] === 'Active') ? 'Vacated' : 'Active';
            $update = $pdo->prepare("UPDATE students SET status = ? WHERE id = ?");
            $update->execute([$new_status, $id]);
            set_flash_message('success', "Status for Student {$s['name']} updated to {$new_status}.");
        } else {
            set_flash_message('error', 'Student not found.');
        }
        redirect('students/student-list.php');
    }
    
    // Delete Student
    if ($action === 'delete') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            set_flash_message('error', 'Invalid student ID.');
            redirect('students/student-list.php');
        }
        
        $stmt = $pdo->prepare("SELECT name, student_code FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        
        if ($s) {
            $delete = $pdo->prepare("DELETE FROM students WHERE id = ?");
            $delete->execute([$id]);
            set_flash_message('success', "Student {$s['name']} ({$s['student_code']}) permanently deleted.");
        } else {
            set_flash_message('error', 'Student not found.');
        }
        redirect('students/student-list.php');
    }
    
    // CSRF Check for POST (`add` and `edit`)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            set_flash_message('error', 'Security token invalid. Please refresh.');
            redirect('students/student-list.php');
        }
        
        $hostel_id      = filter_input(INPUT_POST, 'hostel_id', FILTER_VALIDATE_INT);
        $room_id        = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
        $student_code   = sanitize($_POST['student_code'] ?? '');
        $name           = sanitize($_POST['name'] ?? '');
        $email          = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone          = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
        $guardian_name  = sanitize($_POST['guardian_name'] ?? '');
        $guardian_phone = preg_replace('/[^0-9]/', '', $_POST['guardian_phone'] ?? '');
        $admission_date = sanitize($_POST['admission_date'] ?? date('Y-m-d'));
        $status         = sanitize($_POST['status'] ?? 'Active');
        
        if (!$hostel_id || !$room_id || empty($name) || !$email || strlen($phone) !== 10 || empty($guardian_name) || strlen($guardian_phone) !== 10 || empty($admission_date)) {
            set_flash_message('error', 'Please fill in all required fields and verify 10-digit mobile numbers.');
            redirect(($action === 'add') ? 'students/add-student.php' : "students/edit-student.php?id=" . (int)$_POST['id']);
        }
        
        // Add Student
        if ($action === 'add') {
            // Auto-calculate code if submitted code exists
            $check_code = $pdo->prepare("SELECT id FROM students WHERE student_code = ?");
            $check_code->execute([$student_code]);
            if ($check_code->fetch()) {
                $next_code_stmt = $pdo->query("SELECT student_code FROM students ORDER BY id DESC LIMIT 1");
                $last_code = $next_code_stmt->fetchColumn();
                if ($last_code && preg_match('/^STU(\d+)$/', $last_code, $matches)) {
                    $student_code = 'STU' . str_pad(intval($matches[1]) + 1, 3, '0', STR_PAD_LEFT);
                } else {
                    $student_code = 'STU' . rand(100, 999);
                }
            }
            
            // Check email
            $check_email = $pdo->prepare("SELECT id FROM students WHERE email = ?");
            $check_email->execute([$email]);
            if ($check_email->fetch()) {
                set_flash_message('error', "Student email address {$email} is already registered.");
                redirect('students/add-student.php');
            }
            
            $stmt = $pdo->prepare("INSERT INTO students (hostel_id, room_id, student_code, name, email, phone, guardian_name, guardian_phone, admission_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$hostel_id, $room_id, $student_code, $name, $email, $phone, $guardian_name, $guardian_phone, $admission_date, $status]);
            
            set_flash_message('success', "Student {$name} ({$student_code}) successfully admitted and assigned to room!");
            redirect('students/student-list.php');
        }
        
        // Edit Student
        if ($action === 'edit') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                set_flash_message('error', 'Invalid student ID.');
                redirect('students/student-list.php');
            }
            
            // Check email excluding current student
            $check = $pdo->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
            $check->execute([$email, $id]);
            if ($check->fetch()) {
                set_flash_message('error', "Email address {$email} is already registered by another student.");
                redirect("students/edit-student.php?id=" . $id);
            }
            
            $stmt = $pdo->prepare("UPDATE students SET hostel_id = ?, room_id = ?, name = ?, email = ?, phone = ?, guardian_name = ?, guardian_phone = ?, admission_date = ?, status = ? WHERE id = ?");
            $stmt->execute([$hostel_id, $room_id, $name, $email, $phone, $guardian_name, $guardian_phone, $admission_date, $status, $id]);
            
            set_flash_message('success', "Student profile for {$name} updated successfully!");
            redirect('students/student-list.php');
        }
    }
    
} catch (PDOException $e) {
    error_log("Student Action Error: " . $e->getMessage());
    set_flash_message('error', 'Database error occurred: ' . $e->getMessage());
    redirect('students/student-list.php');
}

redirect('students/student-list.php');
