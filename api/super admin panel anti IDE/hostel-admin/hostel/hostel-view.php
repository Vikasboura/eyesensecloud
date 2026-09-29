<?php
/**
 * Detailed Hostel Profile View (`hostel/hostel-view.php`)
 * Comprehensive display of all hostel attributes, rules, contact info, and status indicators.
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
    error_log("View Hostel Error: " . $e->getMessage());
    set_flash_message('error', 'System error while retrieving record.');
    redirect('hostel/hostel-list.php');
}

$page_title = htmlspecialchars($hostel['hostel_name']) . " Profile - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Top Profile Header & Actions -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-light text-dark font-monospace border fs-6 px-3 py-1">
                <?= htmlspecialchars($hostel['hostel_code']) ?>
            </span>
            <span class="badge-type badge-type-<?= htmlspecialchars($hostel['hostel_type']) ?> fs-6 px-3 py-1">
                <?= htmlspecialchars($hostel['hostel_type']) ?> Hostel
            </span>
            <?php if ($hostel['status'] === 'Active'): ?>
                <span class="badge-status badge-status-active">Active</span>
            <?php else: ?>
                <span class="badge-status badge-status-inactive">Inactive</span>
            <?php endif; ?>
        </div>
        <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($hostel['hostel_name']) ?></h3>
        <p class="text-secondary mb-0"><i class="fas fa-location-dot me-1 text-danger"></i> <?= htmlspecialchars($hostel['city']) ?>, <?= htmlspecialchars($hostel['state']) ?> &mdash; Registered on <?= format_date($hostel['created_at'], 'd M, Y') ?></p>
    </div>
    
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>hostel/edit-hostel.php?id=<?= $hostel['id'] ?>" class="btn btn-warning text-dark fw-bold">
            <i class="fas fa-pen me-1"></i> Edit Record
        </a>
        
        <?php $toggle_url = BASE_URL . "hostel/hostel-action.php?action=toggle_status&id=" . $hostel['id'] . "&csrf_token=" . csrf_token(); ?>
        <a href="javascript:void(0)" onclick="confirmStatusChange('<?= $toggle_url ?>', '<?= $hostel['status'] ?>', '<?= addslashes($hostel['hostel_name']) ?>')" class="btn btn-outline-secondary">
            <i class="fas fa-rotate me-1"></i> Toggle Status
        </a>
        
        <?php $delete_url = BASE_URL . "hostel/hostel-action.php?action=delete&id=" . $hostel['id'] . "&csrf_token=" . csrf_token(); ?>
        <a href="javascript:void(0)" onclick="confirmDelete('<?= $delete_url ?>', '<?= addslashes($hostel['hostel_name']) ?>')" class="btn btn-outline-danger">
            <i class="fas fa-trash me-1"></i> Delete
        </a>
        
        <a href="<?= BASE_URL ?>hostel/hostel-list.php" class="btn btn-light border text-secondary">
            <i class="fas fa-arrow-left"></i>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Profile Column (8 Cols) -->
    <div class="col-lg-8">
        
        <!-- Banner Image / Description Card -->
        <div class="card-custom mb-4">
            <?php if (!empty($hostel['hostel_image']) && file_exists(UPLOAD_DIR . $hostel['hostel_image'])): ?>
                <div style="height: 320px; overflow: hidden; position: relative;">
                    <img src="<?= UPLOAD_URL . htmlspecialchars($hostel['hostel_image']) ?>" alt="Hostel Banner" class="w-100 h-100" style="object-fit: cover;">
                    <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); padding: 2rem 1.5rem 1rem; color: white;">
                        <h4 class="mb-0 fw-bold"><?= htmlspecialchars($hostel['hostel_name']) ?></h4>
                        <small><?= htmlspecialchars($hostel['hostel_code']) ?> &bull; <?= htmlspecialchars($hostel['hostel_type']) ?> Block</small>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-file-lines text-primary fs-5"></i>
                    <h5 class="mb-0">Overview & Description</h5>
                </div>
            </div>
            <div class="card-body-custom">
                <?php if (!empty($hostel['description'])): ?>
                    <p class="text-dark mb-0 fs-6" style="white-space: pre-line;"><?= htmlspecialchars($hostel['description']) ?></p>
                <?php else: ?>
                    <p class="text-muted fst-italic mb-0">No detailed description has been added for this hostel yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Rules & Guidelines Card -->
        <div class="card-custom mb-4">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-shield-halved text-success fs-5"></i>
                    <h5 class="mb-0">Hostel Rules & Regulations</h5>
                </div>
            </div>
            <div class="card-body-custom">
                <?php if (!empty($hostel['hostel_rules'])): ?>
                    <div class="bg-light p-4 rounded-3 font-monospace text-dark border-start border-4 border-success" style="white-space: pre-line;">
<?= htmlspecialchars(trim($hostel['hostel_rules'])) ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted fst-italic mb-0">Standard campus regulations apply. No custom rules entered.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Address & Location Card -->
        <div class="card-custom">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-map-pin text-danger fs-5"></i>
                    <h5 class="mb-0">Address & Location Details</h5>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Street Address</small>
                        <span class="fs-6 fw-medium text-dark"><?= nl2br(htmlspecialchars($hostel['address'])) ?></span>
                    </div>
                    <div class="col-md-3 col-6">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">City</small>
                        <span class="fs-6 fw-bold text-dark"><?= htmlspecialchars($hostel['city']) ?></span>
                    </div>
                    <div class="col-md-3 col-6">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">State</small>
                        <span class="fs-6 fw-bold text-dark"><?= htmlspecialchars($hostel['state']) ?></span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Country</small>
                        <span class="fs-6 fw-medium text-dark"><?= htmlspecialchars($hostel['country']) ?></span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Pincode / Zip Code</small>
                        <span class="fs-6 fw-bold font-monospace text-primary"><?= htmlspecialchars($hostel['pincode']) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Right Sidebar Column (4 Cols) -->
    <div class="col-lg-4">
        
        <!-- Key Metrics Card -->
        <div class="card-custom mb-4 bg-primary bg-opacity-10 border-primary border-opacity-25">
            <div class="card-body-custom text-center py-4">
                <div class="avatar-circle mx-auto mb-3" style="width: 64px; height: 64px; font-size: 1.6rem;">
                    <i class="fas fa-bed"></i>
                </div>
                <h2 class="fw-bold mb-0 text-primary"><?= number_format($hostel['capacity']) ?></h2>
                <p class="text-secondary fw-semibold mb-3">Total Student Bed Capacity</p>
                <div class="d-flex justify-content-around pt-3 border-top border-primary border-opacity-10">
                    <div>
                        <small class="text-muted d-block">Occupied Beds</small>
                        <span class="fw-bold fs-6">0</span> <small class="text-muted">(0%)</small>
                    </div>
                    <div class="border-end border-primary border-opacity-25"></div>
                    <div>
                        <small class="text-muted d-block">Available Beds</small>
                        <span class="fw-bold fs-6 text-success"><?= number_format($hostel['capacity']) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Directory Card -->
        <div class="card-custom mb-4">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-phone text-info fs-5"></i>
                    <h5 class="mb-0">Contact Directory</h5>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="mb-3 pb-3 border-bottom">
                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Primary Mobile</small>
                    <a href="tel:+91<?= htmlspecialchars($hostel['contact_number']) ?>" class="fs-5 fw-bold font-monospace text-dark text-decoration-none d-flex align-items-center gap-2">
                        <i class="fas fa-phone-flip text-success fs-6"></i> +91 <?= htmlspecialchars($hostel['contact_number']) ?>
                    </a>
                </div>
                
                <div class="mb-3 pb-3 border-bottom">
                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Official Email</small>
                    <a href="mailto:<?= htmlspecialchars($hostel['email']) ?>" class="fs-6 fw-medium text-primary text-decoration-none d-block text-truncate">
                        <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($hostel['email']) ?>
                    </a>
                </div>
                
                <div>
                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Emergency Helpline</small>
                    <?php if (!empty($hostel['emergency_contact'])): ?>
                        <a href="tel:+91<?= htmlspecialchars($hostel['emergency_contact']) ?>" class="fs-6 fw-bold font-monospace text-danger text-decoration-none d-flex align-items-center gap-2 mt-1">
                            <i class="fas fa-truck-medical"></i> +91 <?= htmlspecialchars($hostel['emergency_contact']) ?>
                        </a>
                    <?php else: ?>
                        <span class="text-muted font-size-sm">No emergency number assigned.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- System Metadata Card -->
        <div class="card-custom">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-database text-secondary fs-5"></i>
                    <h5 class="mb-0">System Audit Trail</h5>
                </div>
            </div>
            <div class="card-body-custom font-size-sm">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Record ID:</span>
                    <span class="fw-bold font-monospace">#<?= $hostel['id'] ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Created On:</span>
                    <span class="fw-medium"><?= format_date($hostel['created_at']) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Last Updated:</span>
                    <span class="fw-medium"><?= format_date($hostel['updated_at']) ?></span>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
