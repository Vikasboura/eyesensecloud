<?php
/**
 * Helper Deletion Endpoint (`hostel/delete-hostel.php`)
 * Safely routes deletion requests to the central action controller.
 */

require_once __DIR__ . '/../includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash_message('error', 'Invalid hostel ID.');
    redirect('hostel/hostel-list.php');
}

// Redirect with secure token or forward
$delete_url = BASE_URL . "hostel/hostel-action.php?action=delete&id=" . $id . "&csrf_token=" . csrf_token();
redirect($delete_url);
