<?php
/**
 * Edit Hostel Form (`hostel/edit-hostel.php`)
 * Pre-populates record data by ID and allows modifying information safely.
 */

require_once __DIR__ . '/../includes/auth.php';

$hostel_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$hostel_id) {
    set_flash_message('error', 'Invalid hostel ID specified.');
    redirect('hostel/hostel-list.php');
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM hostels WHERE id = ? LIMIT 1");
    $stmt->execute([$hostel_id]);
    $hostel = $stmt->fetch();
    
    if (!$hostel) {
        set_flash_message('error', 'Hostel record not found.');
        redirect('hostel/hostel-list.php');
    }
} catch (PDOException $e) {
    error_log("Edit Hostel Fetch Error: " . $e->getMessage());
    set_flash_message('error', 'System error while fetching record.');
    redirect('hostel/hostel-list.php');
}

$page_title = "Edit " . htmlspecialchars($hostel['hostel_name']) . " - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Edit Hostel Profile</h4>
        <p class="text-secondary mb-0">Modify information for <strong class="text-dark"><?= htmlspecialchars($hostel['hostel_name']) ?></strong> (<span class="font-monospace text-primary"><?= htmlspecialchars($hostel['hostel_code']) ?></span>).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>hostel/hostel-view.php?id=<?= $hostel['id'] ?>" class="btn btn-outline-info rounded-3">
            <i class="fas fa-eye me-1"></i> View Profile
        </a>
        <a href="<?= BASE_URL ?>hostel/hostel-list.php" class="btn btn-outline-secondary rounded-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Directory
        </a>
    </div>
</div>

<form action="<?= BASE_URL ?>hostel/hostel-action.php" method="POST" enctype="multipart/form-data" id="editHostelForm" class="needs-validation" novalidate>
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id" value="<?= $hostel['id'] ?>">
    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($hostel['hostel_image'] ?? '') ?>">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    
    <div class="row g-4">
        <!-- Main Form Column (Left 8 Cols) -->
        <div class="col-lg-8">
            
            <!-- Section 1: Basic Information -->
            <div class="card-custom mb-4">
                <div class="card-header-custom bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-pen-to-square text-primary fs-5"></i>
                        <h5 class="mb-0">1. Basic Information</h5>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="hostel_type" class="form-label">Hostel Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="hostel_type" name="hostel_type" required>
                                <option value="Boys" <?= ($hostel['hostel_type'] === 'Boys') ? 'selected' : '' ?>>Boys Hostel (Prefix: BH)</option>
                                <option value="Girls" <?= ($hostel['hostel_type'] === 'Girls') ? 'selected' : '' ?>>Girls Hostel (Prefix: GH)</option>
                                <option value="Co-Ed" <?= ($hostel['hostel_type'] === 'Co-Ed') ? 'selected' : '' ?>>Co-Ed Hostel (Prefix: CH)</option>
                                <option value="PG" <?= ($hostel['hostel_type'] === 'PG') ? 'selected' : '' ?>>Paying Guest / PG (Prefix: PG)</option>
                                <option value="Staff" <?= ($hostel['hostel_type'] === 'Staff') ? 'selected' : '' ?>>Staff Residency (Prefix: SH)</option>
                            </select>
                            <div class="invalid-feedback">Please select the hostel type.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="hostel_code" class="form-label d-flex justify-content-between">
                                <span>Hostel Code <span class="text-danger">*</span></span>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary font-monospace">Immutable Identifier</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light font-monospace text-muted">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="text" class="form-control font-monospace fw-bold text-muted bg-light" id="hostel_code" name="hostel_code" value="<?= htmlspecialchars($hostel['hostel_code']) ?>" readonly required>
                            </div>
                            <small class="text-muted font-size-sm">Unique identifier locked to maintain relational database integrity.</small>
                        </div>
                        
                        <div class="col-12">
                            <label for="hostel_name" class="form-label">Hostel Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="hostel_name" name="hostel_name" value="<?= htmlspecialchars($hostel['hostel_name']) ?>" required placeholder="e.g. Vidyarthi Boys Residency">
                            <div class="invalid-feedback">Hostel Name is required.</div>
                        </div>
                        
                        <div class="col-12">
                            <label for="description" class="form-label">Description / Overview</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($hostel['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section 2: Address Details -->
            <div class="card-custom mb-4">
                <div class="card-header-custom bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-map-marked-alt text-success fs-5"></i>
                        <h5 class="mb-0">2. Address & Location</h5>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="address" class="form-label">Complete Street Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="2" required><?= htmlspecialchars($hostel['address']) ?></textarea>
                            <div class="invalid-feedback">Please provide street address.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="city" name="city" value="<?= htmlspecialchars($hostel['city']) ?>" required>
                            <div class="invalid-feedback">City is required.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="state" class="form-label">State <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="state" name="state" value="<?= htmlspecialchars($hostel['state']) ?>" required>
                            <div class="invalid-feedback">State is required.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="country" class="form-label">Country <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="country" name="country" value="<?= htmlspecialchars($hostel['country']) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="pincode" class="form-label">Pincode / Zip Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="pincode" name="pincode" value="<?= htmlspecialchars($hostel['pincode']) ?>" required>
                            <div class="invalid-feedback">Valid pincode is required.</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section 3: Contact & Emergency Info -->
            <div class="card-custom mb-4">
                <div class="card-header-custom bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-phone-volume text-info fs-5"></i>
                        <h5 class="mb-0">3. Contact Information</h5>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="contact_number" class="form-label">Primary Contact Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+91</span>
                                <input type="text" class="form-control font-monospace" id="contact_number" name="contact_number" value="<?= htmlspecialchars($hostel['contact_number']) ?>" required pattern="^[0-9]{10}$" maxlength="10">
                            </div>
                            <div class="invalid-feedback">Please enter a valid 10-digit mobile number without spaces.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Hostel Official Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($hostel['email']) ?>" required>
                            <div class="invalid-feedback">Please enter a valid official email address.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="emergency_contact" class="form-label">Emergency Helpline Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+91</span>
                                <input type="text" class="form-control font-monospace" id="emergency_contact" name="emergency_contact" value="<?= htmlspecialchars($hostel['emergency_contact'] ?? '') ?>" pattern="^[0-9]{10}$" maxlength="10">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Right Column (4 Cols) - Capacity, Rules, Image & Submit -->
        <div class="col-lg-4">
            
            <!-- Section 4: Capacity & Status -->
            <div class="card-custom mb-4">
                <div class="card-header-custom bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-bed text-warning fs-5"></i>
                        <h5 class="mb-0">4. Capacity & Status</h5>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label for="capacity" class="form-label">Total Student Bed Capacity <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control font-monospace fw-bold text-primary fs-5" id="capacity" name="capacity" value="<?= htmlspecialchars($hostel['capacity']) ?>" min="1" required>
                            <span class="input-group-text bg-light">Beds</span>
                        </div>
                        <div class="invalid-feedback">Capacity must be greater than 0.</div>
                    </div>
                    
                    <div class="mb-0">
                        <label for="status" class="form-label">Operational Status <span class="text-danger">*</span></label>
                        <select class="form-select fw-semibold" id="status" name="status" required>
                            <option value="Active" <?= ($hostel['status'] === 'Active') ? 'selected' : '' ?> style="color: #10b981;">ðŸŸ¢ Active (Accepting Admissions)</option>
                            <option value="Inactive" <?= ($hostel['status'] === 'Inactive') ? 'selected' : '' ?> style="color: #ef4444;">ðŸ”´ Inactive / Under Maintenance</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Section 5: Image Upload -->
            <div class="card-custom mb-4">
                <div class="card-header-custom bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-camera text-primary fs-5"></i>
                        <h5 class="mb-0">5. Hostel Image Banner</h5>
                    </div>
                </div>
                <div class="card-body-custom">
                    <?php if (!empty($hostel['hostel_image']) && file_exists(UPLOAD_DIR . $hostel['hostel_image'])): ?>
                        <div class="mb-3 text-center">
                            <p class="small text-muted mb-1">Current Banner Image:</p>
                            <img src="<?= UPLOAD_URL . htmlspecialchars($hostel['hostel_image']) ?>" alt="Current Banner" class="img-fluid rounded-3 shadow-sm mb-2" style="max-height: 180px; object-fit: cover;">
                        </div>
                    <?php endif; ?>
                    
                    <label class="image-upload-wrapper d-block w-100 p-3" for="hostel_image">
                        <input type="file" id="hostel_image" name="hostel_image" accept="image/jpeg,image/png,image/jpg" class="d-none">
                        <div id="dropzoneText">
                            <i class="fas fa-cloud-arrow-up fs-2 text-primary mb-1"></i>
                            <div class="fw-bold small">Upload Replacement Image</div>
                            <small class="text-muted d-block mt-1 font-size-sm">Leave empty to keep existing image</small>
                        </div>
                        <div class="image-preview-container">
                            <img id="imagePreview" src="" alt="Preview" class="image-preview-img" style="display: none;">
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Section 6: Hostel Rules -->
            <div class="card-custom mb-4">
                <div class="card-header-custom bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-clipboard-list text-secondary fs-5"></i>
                        <h5 class="mb-0">6. Rules & Regulations</h5>
                    </div>
                </div>
                <div class="card-body-custom">
                    <label for="hostel_rules" class="form-label">Hostel Guidelines</label>
                    <textarea class="form-control font-monospace" id="hostel_rules" name="hostel_rules" rows="5"><?= htmlspecialchars($hostel['hostel_rules'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-custom-primary py-3 fs-6 justify-content-center shadow">
                    <i class="fas fa-save me-2"></i> Update Hostel Record
                </button>
                <a href="<?= BASE_URL ?>hostel/hostel-list.php" class="btn btn-light border py-2 text-secondary fw-semibold">
                    Cancel Changes
                </a>
            </div>
            
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editHostelForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                Swal.fire({
                    icon: 'warning',
                    title: 'Form Validation Error',
                    text: 'Please check required fields and ensure phone numbers are exactly 10 digits.',
                    confirmButtonColor: '#3b82f6'
                });
            }
            form.classList.add('was-validated');
        }, false);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
