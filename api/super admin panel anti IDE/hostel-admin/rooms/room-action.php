<?php
/**
 * Room Operations Controller (`rooms/room-action.php`)
 * Handles Room CRUD, status toggling, and AJAX queries for dynamic student room loading.
 */

require_once __DIR__ . '/../includes/auth.php';

$action = $_REQUEST['action'] ?? '';

try {
    $pdo = get_db_connection();
    
    // ==========================================
    // 1. AJAX: Fetch Rooms by Hostel (For Student Admission)
    // ==========================================
    if ($action === 'get_rooms') {
        header('Content-Type: application/json');
        $hostel_id = filter_input(INPUT_GET, 'hostel_id', FILTER_VALIDATE_INT);
        if (!$hostel_id) {
            echo json_encode(['status' => 'error', 'rooms' => []]);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id, room_number, room_type, capacity, rent_per_month, status FROM rooms WHERE hostel_id = ? ORDER BY room_number ASC");
        $stmt->execute([$hostel_id]);
        $rooms = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'rooms' => $rooms]);
        exit;
    }
    
    // ==========================================
    // 2. GET/POST: Toggle Status (`Available` <-> `Under Maintenance`)
    // ==========================================
    if ($action === 'toggle_status') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            set_flash_message('error', 'Invalid room ID.');
            redirect('rooms/room-list.php');
        }
        
        $stmt = $pdo->prepare("SELECT room_number, status FROM rooms WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $room = $stmt->fetch();
        
        if ($room) {
            $new_status = ($room['status'] === 'Available') ? 'Under Maintenance' : 'Available';
            $update = $pdo->prepare("UPDATE rooms SET status = ? WHERE id = ?");
            $update->execute([$new_status, $id]);
            set_flash_message('success', "Status for Room {$room['room_number']} changed to {$new_status}.");
        } else {
            set_flash_message('error', 'Room not found.');
        }
        redirect('rooms/room-list.php');
    }
    
    // ==========================================
    // 3. GET/POST: Delete Room
    // ==========================================
    if ($action === 'delete') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            set_flash_message('error', 'Invalid room ID.');
            redirect('rooms/room-list.php');
        }
        
        $stmt = $pdo->prepare("SELECT room_number FROM rooms WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $room = $stmt->fetch();
        
        if ($room) {
            $delete = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $delete->execute([$id]);
            set_flash_message('success', "Room {$room['room_number']} has been permanently deleted.");
        } else {
            set_flash_message('error', 'Room not found.');
        }
        redirect('rooms/room-list.php');
    }
    
    // CSRF Check for POST (`add` and `edit`)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            set_flash_message('error', 'Security token invalid. Please refresh.');
            redirect('rooms/room-list.php');
        }
        
        $hostel_id      = filter_input(INPUT_POST, 'hostel_id', FILTER_VALIDATE_INT);
        $room_number    = sanitize($_POST['room_number'] ?? '');
        $room_type      = sanitize($_POST['room_type'] ?? 'Double');
        $capacity       = (int) ($_POST['capacity'] ?? 2);
        $rent_per_month = (float) ($_POST['rent_per_month'] ?? 0);
        $status         = sanitize($_POST['status'] ?? 'Available');
        
        if (!$hostel_id || empty($room_number) || $capacity <= 0 || $rent_per_month < 0) {
            set_flash_message('error', 'Please fill in all required room configuration fields.');
            redirect(($action === 'add') ? 'rooms/add-room.php' : "rooms/edit-room.php?id=" . (int)$_POST['id']);
        }
        
        // ==========================================
        // 4. POST: Add Room
        // ==========================================
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO rooms (hostel_id, room_number, room_type, capacity, rent_per_month, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$hostel_id, $room_number, $room_type, $capacity, $rent_per_month, $status]);
            
            set_flash_message('success', "Room {$room_number} successfully added and assigned to hostel block!");
            redirect('rooms/room-list.php');
        }
        
        // ==========================================
        // 5. POST: Edit Room
        // ==========================================
        if ($action === 'edit') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                set_flash_message('error', 'Invalid room ID.');
                redirect('rooms/room-list.php');
            }
            
            $stmt = $pdo->prepare("UPDATE rooms SET hostel_id = ?, room_number = ?, room_type = ?, capacity = ?, rent_per_month = ?, status = ? WHERE id = ?");
            $stmt->execute([$hostel_id, $room_number, $room_type, $capacity, $rent_per_month, $status, $id]);
            
            set_flash_message('success', "Room {$room_number} details updated successfully!");
            redirect('rooms/room-list.php');
        }
    }
    
} catch (PDOException $e) {
    error_log("Room Action Error: " . $e->getMessage());
    set_flash_message('error', 'Database operation failed: ' . $e->getMessage());
    redirect('rooms/room-list.php');
}

redirect('rooms/room-list.php');
