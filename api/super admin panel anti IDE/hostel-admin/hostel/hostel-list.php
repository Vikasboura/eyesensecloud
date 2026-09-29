<?php
/**
 * Hostel List Page (`hostel/hostel-list.php`)
 * Displays comprehensive table of all registered hostels with DataTables, search, sorting, and action triggers.
 */

require_once __DIR__ . '/../includes/auth.php';
$page_title = "Hostel List - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';

try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT * FROM hostels ORDER BY id DESC");
    $hostels = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Hostel List Error: " . $e->getMessage());
    $hostels = [];
}
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Hostel Directory</h4>
        <p class="text-secondary mb-0">Manage and monitor all active, inactive, and newly registered hostels.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>hostel/add-hostel.php" class="btn btn-custom-primary">
            <i class="fas fa-plus-circle me-1"></i> Add New Hostel
        </a>
    </div>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-list-ul text-primary"></i>
            <h5>All Registered Hostels (<?= count($hostels) ?>)</h5>
        </div>
    </div>
    
    <div class="card-body-custom">
        <div class="table-responsive">
            <table id="hostelTable" class="table table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th style="width: 50px;">Sr No</th>
                        <th>Hostel Name</th>
                        <th>Hostel Code</th>
                        <th>Type</th>
                        <th>City</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th class="text-end" style="min-width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($hostels)): ?>
                        <?php $sr = 1; foreach ($hostels as $row): ?>
                            <tr>
                                <td class="fw-semibold text-muted"><?= $sr++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if (!empty($row['hostel_image']) && file_exists(UPLOAD_DIR . $row['hostel_image'])): ?>
                                            <img src="<?= UPLOAD_URL . htmlspecialchars($row['hostel_image']) ?>" alt="Thumbnail" class="rounded-3 shadow-sm" style="width: 44px; height: 44px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="avatar-circle" style="width: 44px; height: 44px; font-size: 1rem; background: var(--secondary);">
                                                <i class="fas fa-hotel"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="<?= BASE_URL ?>hostel/hostel-view.php?id=<?= $row['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                <?= htmlspecialchars($row['hostel_name']) ?>
                                            </a>
                                            <small class="text-muted d-block font-size-sm">
                                                <i class="fas fa-phone-alt me-1" style="font-size: 0.7rem;"></i> <?= htmlspecialchars($row['contact_number']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace px-2 py-1 fs-6">
                                        <?= htmlspecialchars($row['hostel_code']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-type badge-type-<?= htmlspecialchars($row['hostel_type']) ?>">
                                        <?= htmlspecialchars($row['hostel_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark fw-medium"><?= htmlspecialchars($row['city']) ?></span>
                                    <small class="text-muted d-block"><?= htmlspecialchars($row['state']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fs-6 fw-bold">
                                        <?= number_format($row['capacity']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $toggle_url = BASE_URL . "hostel/hostel-action.php?action=toggle_status&id=" . $row['id'] . "&csrf_token=" . csrf_token();
                                    if ($row['status'] === 'Active'): ?>
                                        <a href="javascript:void(0)" onclick="confirmStatusChange('<?= $toggle_url ?>', 'Active', '<?= addslashes($row['hostel_name']) ?>')" class="text-decoration-none">
                                            <span class="badge-status badge-status-active">Active</span>
                                        </a>
                                    <?php else: ?>
                                        <a href="javascript:void(0)" onclick="confirmStatusChange('<?= $toggle_url ?>', 'Inactive', '<?= addslashes($row['hostel_name']) ?>')" class="text-decoration-none">
                                            <span class="badge-status badge-status-inactive">Inactive</span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <!-- ðŸ‘ View -->
                                        <a href="<?= BASE_URL ?>hostel/hostel-view.php?id=<?= $row['id'] ?>" class="btn-action btn-action-view" data-bs-toggle="tooltip" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <!-- âœ Edit -->
                                        <a href="<?= BASE_URL ?>hostel/edit-hostel.php?id=<?= $row['id'] ?>" class="btn-action btn-action-edit" data-bs-toggle="tooltip" title="Edit Hostel">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        
                                        <!-- ðŸ”„ Active/Inactive -->
                                        <a href="javascript:void(0)" onclick="confirmStatusChange('<?= $toggle_url ?>', '<?= $row['status'] ?>', '<?= addslashes($row['hostel_name']) ?>')" class="btn-action btn-action-status" data-bs-toggle="tooltip" title="Toggle Status">
                                            <i class="fas fa-rotate"></i>
                                        </a>
                                        
                                        <!-- ðŸ—‘ Delete -->
                                        <?php $delete_url = BASE_URL . "hostel/hostel-action.php?action=delete&id=" . $row['id'] . "&csrf_token=" . csrf_token(); ?>
                                        <a href="javascript:void(0)" onclick="confirmDelete('<?= $delete_url ?>', '<?= addslashes($row['hostel_name']) ?>')" class="btn-action btn-action-delete" data-bs-toggle="tooltip" title="Delete Hostel">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
