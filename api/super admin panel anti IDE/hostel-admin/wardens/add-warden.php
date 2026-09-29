<?php
/**
 * Assign New Warden Form (`wardens/add-warden.php`)
 * Assigns resident staff/warden to a hostel block with contact details and joining date.
 */

require_once __DIR__ . '/../includes/auth.php';

try {
    $pdo = get_db_connection();
    $hostel_list = $pdo->query("SELECT id, hostel_name, hostel_code FROM hostels ORDER BY hostel_name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Add Warden Hostel Fetch Error: " . $e->getMessage());
    $hostel_list = [];
}

$page_title = "Assign Resident Warden - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Assign Resident Warden</h4>
        <p class="text-secondary mb-0">Register staff credentials, contact phone numbers, and assign block supervision.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>wardens/warden-list.php" class="btn btn-outline-secondary rounded-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Wardens Directory
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-user-shield text-primary fs-5"></i>
                    <h5 class="mb-0">Warden Profile & Assignment</h5>
                </div>
            </div>
            <div class="card-body-custom p-4">
                
                <?php if (empty($hostel_list)): ?>
                    <div class="alert alert-warning">
                        <strong>No Hostel Blocks Available!</strong> Register a hostel block before assigning wardens.
                    </div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>wardens/warden-action.php" method="POST" id="addWardenForm" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="hostel_id" class="form-label">Assigned Hostel Block <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="hostel_id" name="hostel_id" required>
                                <option value="">-- Select Block --</option>
                                <?php foreach ($hostel_list as $h): ?>
                                    <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['hostel_name']) ?> (<?= htmlspecialchars($h['hostel_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select the assigned block.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold" id="name" name="name" required placeholder="e.g. Capt. Rajesh Sharma">
                            <div class="invalid-feedback">Warden name is required.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Official Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="e.g. warden@hosteladmin.com">
                            <div class="invalid-feedback">Valid email address required.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Contact Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+91</span>
                                <input type="text" class="form-control font-monospace" id="phone" name="phone" required pattern="^[0-9]{10}$" maxlength="10" placeholder="10-digit mobile">
                            </div>
                            <div class="invalid-feedback">Must be exactly 10 digits.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="joining_date" class="form-label">Joining Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control font-monospace" id="joining_date" name="joining_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="status" class="form-label">Supervision Status <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="status" name="status" required>
                                <option value="Active" selected style="color: #10b981;">ðŸŸ¢ Active Resident Warden</option>
                                <option value="On Leave" style="color: #f59e0b;">ðŸŸ¡ On Leave / Temporary Absence</option>
                                <option value="Inactive" style="color: #ef4444;">ðŸ”´ Inactive / Former Staff</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label for="address" class="form-label">Residential Quarters / Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" placeholder="Quarters location or residential street address"></textarea>
                        </div>
                        
                        <div class="col-12 pt-3 border-top d-flex justify-content-end gap-2">
                            <a href="<?= BASE_URL ?>wardens/warden-list.php" class="btn btn-light border px-4">Cancel</a>
                            <button type="submit" class="btn btn-custom-primary px-5 py-2 shadow" <?= empty($hostel_list) ? 'disabled' : '' ?>>
                                <i class="fas fa-save me-2"></i> Assign Warden
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
    const form = document.getElementById('addWardenForm');
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
