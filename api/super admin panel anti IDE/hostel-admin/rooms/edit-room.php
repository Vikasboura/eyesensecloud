<?php
/**
 * Edit Room Form (`rooms/edit-room.php`)
 * Pre-populates room attributes, allows updating capacity, rent, and status cleanly.
 */

require_once __DIR__ . '/../includes/auth.php';

$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$room_id) {
    set_flash_message('error', 'Invalid room ID specified.');
    redirect('rooms/room-list.php');
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT r.*, h.hostel_name, h.hostel_code FROM rooms r JOIN hostels h ON r.hostel_id = h.id WHERE r.id = ? LIMIT 1");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    
    if (!$room) {
        set_flash_message('error', 'Room record not found.');
        redirect('rooms/room-list.php');
    }
    
    $hostels_stmt = $pdo->query("SELECT id, hostel_name, hostel_code FROM hostels ORDER BY hostel_name ASC");
    $hostel_list = $hostels_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Edit Room Fetch Error: " . $e->getMessage());
    set_flash_message('error', 'System error while fetching room details.');
    redirect('rooms/room-list.php');
}

$page_title = "Edit Room " . htmlspecialchars($room['room_number']) . " - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Edit Room Record</h4>
        <p class="text-secondary mb-0">Modify attributes for Room <strong class="text-dark font-monospace"><?= htmlspecialchars($room['room_number']) ?></strong> in <span class="fw-semibold"><?= htmlspecialchars($room['hostel_name']) ?></span>.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>rooms/room-list.php" class="btn btn-outline-secondary rounded-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Rooms List
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-pen-to-square text-warning fs-5"></i>
                    <h5 class="mb-0">Modify Room Settings</h5>
                </div>
            </div>
            <div class="card-body-custom p-4">
                <form action="<?= BASE_URL ?>rooms/room-action.php" method="POST" id="editRoomForm" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $room['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="hostel_id" class="form-label">Parent Hostel Block <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="hostel_id" name="hostel_id" required>
                                <?php foreach ($hostel_list as $h): ?>
                                    <option value="<?= $h['id'] ?>" <?= ($room['hostel_id'] == $h['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($h['hostel_name']) ?> (<?= htmlspecialchars($h['hostel_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="room_number" class="form-label">Room Number / Identifier <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light font-monospace"><i class="fas fa-key text-secondary"></i></span>
                                <input type="text" class="form-control font-monospace fw-bold" id="room_number" name="room_number" value="<?= htmlspecialchars($room['room_number']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="room_type" class="form-label">Room Type / Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="room_type" name="room_type" required>
                                <option value="Single" <?= ($room['room_type'] === 'Single') ? 'selected' : '' ?>>Single Occupancy (1 Bed)</option>
                                <option value="Double" <?= ($room['room_type'] === 'Double') ? 'selected' : '' ?>>Double Occupancy (2 Beds)</option>
                                <option value="Triple" <?= ($room['room_type'] === 'Triple') ? 'selected' : '' ?>>Triple Occupancy (3 Beds)</option>
                                <option value="Dormitory" <?= ($room['room_type'] === 'Dormitory') ? 'selected' : '' ?>>Multi-bed Dormitory (4+ Beds)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="capacity" class="form-label">Total Bed Capacity <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control font-monospace fw-bold text-primary" id="capacity" name="capacity" value="<?= $room['capacity'] ?>" min="1" max="20" required>
                                <span class="input-group-text bg-light">Beds</span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="rent_per_month" class="form-label">Monthly Rental Fee <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-success">â‚¹</span>
                                <input type="number" step="0.01" class="form-control font-monospace fw-bold text-success" id="rent_per_month" name="rent_per_month" value="<?= $room['rent_per_month'] ?>" min="0" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="status" class="form-label">Operational Status <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="status" name="status" required>
                                <option value="Available" <?= ($room['status'] === 'Available') ? 'selected' : '' ?> style="color: #10b981;">ðŸŸ¢ Available for Admissions</option>
                                <option value="Full" <?= ($room['status'] === 'Full') ? 'selected' : '' ?> style="color: #6366f1;">ðŸŸ£ Occupied / Full</option>
                                <option value="Under Maintenance" <?= ($room['status'] === 'Under Maintenance') ? 'selected' : '' ?> style="color: #ef4444;">ðŸ”´ Under Maintenance / Closed</option>
                            </select>
                        </div>
                        
                        <div class="col-12 pt-3 border-top d-flex justify-content-end gap-2">
                            <a href="<?= BASE_URL ?>rooms/room-list.php" class="btn btn-light border px-4">Cancel</a>
                            <button type="submit" class="btn btn-warning fw-bold px-5 py-2 shadow">
                                <i class="fas fa-save me-2"></i> Update Room Details
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editRoomForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
