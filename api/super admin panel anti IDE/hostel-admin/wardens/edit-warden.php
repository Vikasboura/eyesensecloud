<?php
/**
 * Edit Warden Form (`wardens/edit-warden.php`)
 * Modifies warden contact info, assigned block, and status.
 */

require_once __DIR__ . '/../includes/auth.php';

$warden_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$warden_id) {
    set_flash_message('error', 'Invalid warden ID specified.');
    redirect('wardens/warden-list.php');
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT w.*, h.hostel_name, h.hostel_code FROM wardens w JOIN hostels h ON w.hostel_id = h.id WHERE w.id = ? LIMIT 1");
    $stmt->execute([$warden_id]);
    $warden = $stmt->fetch();
    
    if (!$warden) {
        set_flash_message('error', 'Warden record not found.');
        redirect('wardens/warden-list.php');
    }
    
    $hostel_list = $pdo->query("SELECT id, hostel_name, hostel_code FROM hostels ORDER BY hostel_name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Edit Warden Fetch Error: " . $e->getMessage());
    set_flash_message('error', 'System error fetching warden data.');
    redirect('wardens/warden-list.php');
}

$page_title = "Edit Warden " . htmlspecialchars($warden['name']) . " - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Edit Warden Assignment</h4>
        <p class="text-secondary mb-0">Update credentials for <strong class="text-dark"><?= htmlspecialchars($warden['name']) ?></strong> assigned to <span class="fw-semibold"><?= htmlspecialchars($warden['hostel_name']) ?></span>.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>wardens/warden-list.php" class="btn btn-outline-secondary rounded-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Directory
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-user-pen text-warning fs-5"></i>
                    <h5 class="mb-0">Update Warden Profile</h5>
                </div>
            </div>
            <div class="card-body-custom p-4">
                <form action="<?= BASE_URL ?>wardens/warden-action.php" method="POST" id="editWardenForm" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $warden['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="hostel_id" class="form-label">Assigned Hostel Block <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="hostel_id" name="hostel_id" required>
                                <?php foreach ($hostel_list as $h): ?>
                                    <option value="<?= $h['id'] ?>" <?= ($warden['hostel_id'] == $h['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($h['hostel_name']) ?> (<?= htmlspecialchars($h['hostel_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold" id="name" name="name" value="<?= htmlspecialchars($warden['name']) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Official Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($warden['email']) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Contact Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+91</span>
                                <input type="text" class="form-control font-monospace" id="phone" name="phone" value="<?= htmlspecialchars($warden['phone']) ?>" required pattern="^[0-9]{10}$" maxlength="10">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="joining_date" class="form-label">Joining Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control font-monospace" id="joining_date" name="joining_date" value="<?= htmlspecialchars($warden['joining_date']) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="status" class="form-label">Supervision Status <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="status" name="status" required>
                                <option value="Active" <?= ($warden['status'] === 'Active') ? 'selected' : '' ?> style="color: #10b981;">ðŸŸ¢ Active Resident Warden</option>
                                <option value="On Leave" <?= ($warden['status'] === 'On Leave') ? 'selected' : '' ?> style="color: #f59e0b;">ðŸŸ¡ On Leave</option>
                                <option value="Inactive" <?= ($warden['status'] === 'Inactive') ? 'selected' : '' ?> style="color: #ef4444;">ðŸ”´ Inactive / Former Staff</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label for="address" class="form-label">Residential Quarters / Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($warden['address']) ?></textarea>
                        </div>
                        
                        <div class="col-12 pt-3 border-top d-flex justify-content-end gap-2">
                            <a href="<?= BASE_URL ?>wardens/warden-list.php" class="btn btn-light border px-4">Cancel</a>
                            <button type="submit" class="btn btn-warning fw-bold px-5 py-2 shadow">
                                <i class="fas fa-save me-2"></i> Save Changes
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
    const form = document.getElementById('editWardenForm');
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
