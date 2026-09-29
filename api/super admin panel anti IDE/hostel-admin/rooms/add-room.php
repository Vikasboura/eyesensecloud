<?php
/**
 * Add New Room Form (`rooms/add-room.php`)
 * Allows administrators to assign new rooms to existing hostel blocks with capacity and monthly rental structure.
 */

require_once __DIR__ . '/../includes/auth.php';

try {
    $pdo = get_db_connection();
    $hostels_stmt = $pdo->query("SELECT id, hostel_name, hostel_code FROM hostels ORDER BY hostel_name ASC");
    $hostel_list = $hostels_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Add Room Hostel Fetch Error: " . $e->getMessage());
    $hostel_list = [];
}

$page_title = "Assign New Room - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Assign New Living Room</h4>
        <p class="text-secondary mb-0">Register a new student room, configure occupancy rules, and set monthly fee structures.</p>
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
                    <i class="fas fa-door-open text-primary fs-5"></i>
                    <h5 class="mb-0">Room Configuration Details</h5>
                </div>
            </div>
            <div class="card-body-custom p-4">
                
                <?php if (empty($hostel_list)): ?>
                    <div class="alert alert-warning d-flex align-items-center gap-3">
                        <i class="fas fa-triangle-exclamation fs-3"></i>
                        <div>
                            <strong>No Hostel Blocks Found!</strong> You must first register at least one hostel before assigning rooms.
                            <br><a href="<?= BASE_URL ?>hostel/add-hostel.php" class="alert-link">Click here to register a hostel</a>.
                        </div>
                    </div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>rooms/room-action.php" method="POST" id="addRoomForm" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="hostel_id" class="form-label">Parent Hostel Block <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="hostel_id" name="hostel_id" required>
                                <option value="">-- Select Hostel --</option>
                                <?php foreach ($hostel_list as $h): ?>
                                    <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['hostel_name']) ?> (<?= htmlspecialchars($h['hostel_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select the parent hostel block.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="room_number" class="form-label">Room Number / Identifier <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light font-monospace"><i class="fas fa-key text-secondary"></i></span>
                                <input type="text" class="form-control font-monospace fw-bold" id="room_number" name="room_number" required placeholder="e.g. 101, B-204, G-12">
                            </div>
                            <div class="invalid-feedback">Please provide a unique room identifier.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="room_type" class="form-label">Room Type / Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="room_type" name="room_type" required onchange="syncCapacity(this.value)">
                                <option value="Single">Single Occupancy (1 Bed)</option>
                                <option value="Double" selected>Double Occupancy (2 Beds)</option>
                                <option value="Triple">Triple Occupancy (3 Beds)</option>
                                <option value="Dormitory">Multi-bed Dormitory (4+ Beds)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="capacity" class="form-label">Total Bed Capacity <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control font-monospace fw-bold text-primary" id="capacity" name="capacity" value="2" min="1" max="20" required>
                                <span class="input-group-text bg-light">Beds</span>
                            </div>
                            <div class="invalid-feedback">Capacity must be between 1 and 20.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="rent_per_month" class="form-label">Monthly Rental Fee (per student) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-success">â‚¹</span>
                                <input type="number" step="0.01" class="form-control font-monospace fw-bold text-success" id="rent_per_month" name="rent_per_month" value="5000.00" min="0" required>
                            </div>
                            <div class="invalid-feedback">Please enter valid monthly rental fee.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="status" class="form-label">Initial Operational Status <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="status" name="status" required>
                                <option value="Available" selected style="color: #10b981;">ðŸŸ¢ Available for Admissions</option>
                                <option value="Under Maintenance" style="color: #ef4444;">ðŸ”´ Under Maintenance / Closed</option>
                            </select>
                        </div>
                        
                        <div class="col-12 pt-3 border-top d-flex justify-content-end gap-2">
                            <a href="<?= BASE_URL ?>rooms/room-list.php" class="btn btn-light border px-4">Cancel</a>
                            <button type="submit" class="btn btn-custom-primary px-5 py-2 shadow" <?= empty($hostel_list) ? 'disabled' : '' ?>>
                                <i class="fas fa-save me-2"></i> Save & Assign Room
                            </button>
                        </div>
                    </div>
                </form>
                
            </div>
        </div>
    </div>
</div>

<script>
function syncCapacity(type) {
    const capInput = document.getElementById('capacity');
    if (!capInput) return;
    if (type === 'Single') capInput.value = 1;
    if (type === 'Double') capInput.value = 2;
    if (type === 'Triple') capInput.value = 3;
    if (type === 'Dormitory' && capInput.value < 4) capInput.value = 4;
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addRoomForm');
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
