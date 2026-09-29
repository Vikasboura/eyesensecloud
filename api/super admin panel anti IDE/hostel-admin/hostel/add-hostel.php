<?php
/**
 * Add Hostel Form (`hostel/add-hostel.php`)
 * Multi-section responsive form with live AJAX auto-generation of Hostel Code.
 */

require_once __DIR__ . '/../includes/auth.php';
$page_title = "Add New Hostel - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Add New Hostel</h4>
        <p class="text-secondary mb-0">Register a new hostel block with auto-generated unique code (`BH001`, `GH001`, etc.).</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>hostel/hostel-list.php" class="btn btn-outline-secondary rounded-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Directory
        </a>
    </div>
</div>

<form action="<?= BASE_URL ?>hostel/hostel-action.php" method="POST" enctype="multipart/form-data" id="addHostelForm" class="needs-validation" novalidate>
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    
    <div class="row g-4">
        <!-- Main Form Column (Left 8 Cols) -->
        <div class="col-lg-8">
            
            <!-- Section 1: Basic Information -->
            <div class="card-custom mb-4">
                <div class="card-header-custom bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-info-circle text-primary fs-5"></i>
                        <h5 class="mb-0">1. Basic Information</h5>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="hostel_type" class="form-label">Hostel Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="hostel_type" name="hostel_type" required>
                                <option value="" disabled selected>Select Hostel Type</option>
                                <option value="Boys">Boys Hostel (Prefix: BH)</option>
                                <option value="Girls">Girls Hostel (Prefix: GH)</option>
                                <option value="Co-Ed">Co-Ed Hostel (Prefix: CH)</option>
                                <option value="PG">Paying Guest / PG (Prefix: PG)</option>
                                <option value="Staff">Staff Residency (Prefix: SH)</option>
                            </select>
                            <div class="invalid-feedback">Please select the hostel type.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="hostel_code" class="form-label d-flex justify-content-between">
                                <span>Hostel Code <span class="text-danger">*</span></span>
                                <span class="badge bg-info bg-opacity-10 text-info font-monospace" id="codeStatusBadge">Auto Generated</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light font-monospace text-primary fw-bold">
                                    <i class="fas fa-hashtag"></i>
                                </span>
                                <input type="text" class="form-control font-monospace fw-bold text-primary bg-light" id="hostel_code" name="hostel_code" readonly placeholder="Select type to auto-generate..." required>
                            </div>
                            <small class="text-muted font-size-sm">Auto-calculated securely by database sequence (`BH001`, `GH001`, etc.)</small>
                        </div>
                        
                        <div class="col-12">
                            <label for="hostel_name" class="form-label">Hostel Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="hostel_name" name="hostel_name" required placeholder="e.g. Vidyarthi Boys Residency">
                            <div class="invalid-feedback">Hostel Name is required.</div>
                        </div>
                        
                        <div class="col-12">
                            <label for="description" class="form-label">Description / Overview</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Brief description of facilities, environment, and block details..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section 2: Address Details -->
            <div class="card-custom mb-4">
                <div class="card-header-custom bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-map-location-dot text-success fs-5"></i>
                        <h5 class="mb-0">2. Address & Location</h5>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="address" class="form-label">Complete Street Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="2" required placeholder="Plot number, Street name, Landmark..."></textarea>
                            <div class="invalid-feedback">Please provide street address.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="city" name="city" required placeholder="e.g. Mumbai">
                            <div class="invalid-feedback">City is required.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="state" class="form-label">State <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="state" name="state" required placeholder="e.g. Maharashtra">
                            <div class="invalid-feedback">State is required.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="country" class="form-label">Country <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="country" name="country" value="India" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="pincode" class="form-label">Pincode / Zip Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="pincode" name="pincode" required placeholder="e.g. 400076">
                            <div class="invalid-feedback">Valid pincode is required.</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section 3: Contact & Emergency Info -->
            <div class="card-custom mb-4">
                <div class="card-header-custom bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-address-book text-info fs-5"></i>
                        <h5 class="mb-0">3. Contact Information</h5>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="contact_number" class="form-label">Primary Contact Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+91</span>
                                <input type="text" class="form-control font-monospace" id="contact_number" name="contact_number" required pattern="^[0-9]{10}$" maxlength="10" placeholder="10-digit mobile number">
                            </div>
                            <div class="invalid-feedback">Please enter a valid 10-digit mobile number without spaces or special characters.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Hostel Official Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="hostel@domain.com">
                            <div class="invalid-feedback">Please enter a valid official email address.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="emergency_contact" class="form-label">Emergency Helpline Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+91</span>
                                <input type="text" class="form-control font-monospace" id="emergency_contact" name="emergency_contact" pattern="^[0-9]{10}$" maxlength="10" placeholder="Warden/Security emergency number">
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
                            <input type="number" class="form-control font-monospace fw-bold text-primary fs-5" id="capacity" name="capacity" min="1" required placeholder="e.g. 150">
                            <span class="input-group-text bg-light">Beds</span>
                        </div>
                        <div class="invalid-feedback">Capacity must be greater than 0.</div>
                    </div>
                    
                    <div class="mb-0">
                        <label for="status" class="form-label">Operational Status <span class="text-danger">*</span></label>
                        <select class="form-select fw-semibold" id="status" name="status" required>
                            <option value="Active" selected style="color: #10b981;">🟢 Active (Accepting Admissions)</option>
                            <option value="Inactive" style="color: #ef4444;">🔴 Inactive / Under Maintenance</option>
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
                    <label class="image-upload-wrapper d-block w-100" for="hostel_image">
                        <input type="file" id="hostel_image" name="hostel_image" accept="image/jpeg,image/png,image/jpg" class="d-none">
                        <div id="dropzoneText">
                            <i class="fas fa-cloud-arrow-up fs-1 text-primary mb-2"></i>
                            <div class="fw-bold">Click to Upload Image</div>
                            <small class="text-muted d-block mt-1">Supports JPG, JPEG, PNG (Max 5MB)</small>
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
                    <textarea class="form-control font-monospace" id="hostel_rules" name="hostel_rules" rows="5" placeholder="1. No entry after 10:00 PM without prior warden approval.&#10;2. Keep common rooms and study halls clean.&#10;3. Smoking strictly prohibited."></textarea>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-custom-primary py-3 fs-6 justify-content-center shadow">
                    <i class="fas fa-check-circle me-2"></i> Save & Register Hostel
                </button>
                <a href="<?= BASE_URL ?>hostel/hostel-list.php" class="btn btn-light border py-2 text-secondary fw-semibold">
                    Cancel Operations
                </a>
            </div>
            
        </div>
    </div>
</form>

<!-- AJAX Auto-Generate Code Script & Bootstrap Validation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form Validation Trigger
    const form = document.getElementById('addHostelForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                Swal.fire({
                    icon: 'warning',
                    title: 'Form Validation Error',
                    text: 'Please fill in all required fields (*) and ensure 10-digit phone numbers and valid emails.',
                    confirmButtonColor: '#3b82f6'
                });
            }
            form.classList.add('was-validated');
        }, false);
    }

    // AJAX Auto-Generate Hostel Code on Type Change
    const typeSelect = document.getElementById('hostel_type');
    const codeInput = document.getElementById('hostel_code');
    const badge = document.getElementById('codeStatusBadge');

    if (typeSelect && codeInput) {
        typeSelect.addEventListener('change', function() {
            const selectedType = this.value;
            if (!selectedType) return;

            codeInput.value = 'Calculating...';
            badge.innerText = 'Syncing...';
            badge.className = 'badge bg-warning bg-opacity-10 text-warning font-monospace';

            fetch(`<?= BASE_URL ?>hostel/hostel-action.php?action=get_code&type=${encodeURIComponent(selectedType)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.code) {
                        codeInput.value = data.code;
                        badge.innerText = `Prefix: ${data.prefix}`;
                        badge.className = 'badge bg-success bg-opacity-10 text-success font-monospace';
                    } else {
                        codeInput.value = '';
                        badge.innerText = 'Error generating code';
                        badge.className = 'badge bg-danger bg-opacity-10 text-danger font-monospace';
                    }
                })
                .catch(error => {
                    console.error('Error fetching auto-generated code:', error);
                    codeInput.value = '';
                    badge.innerText = 'Network Error';
                    badge.className = 'badge bg-danger bg-opacity-10 text-danger font-monospace';
                });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
