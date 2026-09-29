<?php
/**
 * Wardens Directory (`wardens/warden-list.php`)
 * Displays all assigned resident wardens, contact phone/email directory, assigned hostel blocks, and status management.
 */

require_once __DIR__ . '/../includes/auth.php';

$page_title = "Wardens Directory - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';

$filter_hostel_id = filter_input(INPUT_GET, 'hostel_id', FILTER_VALIDATE_INT);

try {
    $pdo = get_db_connection();
    
    // Fetch hostels for filter
    $hostels_stmt = $pdo->query("SELECT id, hostel_name, hostel_code FROM hostels ORDER BY hostel_name ASC");
    $hostel_list = $hostels_stmt->fetchAll();
    
    if ($filter_hostel_id) {
        $sql = "SELECT w.*, h.hostel_name, h.hostel_code 
                FROM wardens w 
                JOIN hostels h ON w.hostel_id = h.id 
                WHERE w.hostel_id = ? 
                ORDER BY w.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$filter_hostel_id]);
    } else {
        $sql = "SELECT w.*, h.hostel_name, h.hostel_code 
                FROM wardens w 
                JOIN hostels h ON w.hostel_id = h.id 
                ORDER BY w.name ASC";
        $stmt = $pdo->query($sql);
    }
    $wardens = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Warden List Fetch Error: " . $e->getMessage());
    $wardens = [];
    $hostel_list = [];
}
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Hostel Wardens Directory</h4>
        <p class="text-secondary mb-0">Assign resident wardens, manage staff contact details, and monitor block supervision across campus.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>wardens/add-warden.php" class="btn btn-custom-primary shadow-sm">
            <i class="fas fa-user-plus me-1"></i> Assign New Warden
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card-custom mb-4 bg-light bg-opacity-50">
    <div class="card-body-custom py-3">
        <form method="GET" action="warden-list.php" class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 420px;">
                <label for="hostel_id" class="form-label mb-0 fw-semibold text-secondary text-nowrap"><i class="fas fa-filter me-1"></i> Filter by Block:</label>
                <select class="form-select border-0 shadow-sm" id="hostel_id" name="hostel_id" onchange="this.form.submit()">
                    <option value="">All Assigned Blocks (<?= count($wardens) ?> wardens)</option>
                    <?php foreach ($hostel_list as $h): ?>
                        <option value="<?= $h['id'] ?>" <?= ($filter_hostel_id === (int)$h['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['hostel_name']) ?> (<?= htmlspecialchars($h['hostel_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filter_hostel_id): ?>
                <a href="warden-list.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Clear Filter
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card-custom">
    <div class="card-body-custom">
        <?php if (empty($wardens)): ?>
            <div class="text-center py-5">
                <div class="avatar-circle mx-auto mb-3" style="width: 72px; height: 72px; font-size: 1.8rem; background: #f1f5f9; color: #94a3b8;">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h5 class="fw-bold text-dark">No Wardens Assigned</h5>
                <p class="text-secondary max-w-sm mx-auto mb-4">No wardens found matching your criteria. Click below to assign a resident warden to a hostel.</p>
                <a href="<?= BASE_URL ?>wardens/add-warden.php" class="btn btn-custom-primary px-4 py-2">
                    <i class="fas fa-user-check me-1"></i> Assign Warden Now
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table id="dataTable" class="table table-hover align-middle mb-0 w-100">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Warden Name</th>
                            <th>Assigned Block</th>
                            <th>Official Email</th>
                            <th>Contact Mobile</th>
                            <th>Joining Date</th>
                            <th>Status</th>
                            <th class="text-end" style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wardens as $index => $w): ?>
                            <tr>
                                <td class="text-secondary font-monospace"><?= $index + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                            <?= strtoupper(substr($w['name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars($w['name']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($w['address'] ? substr($w['address'], 0, 35) . '...' : 'Resident Staff') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark d-block"><?= htmlspecialchars($w['hostel_name']) ?></span>
                                    <span class="badge bg-light text-secondary font-monospace border"><?= htmlspecialchars($w['hostel_code']) ?></span>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($w['email']) ?>" class="text-decoration-none fw-medium text-primary">
                                        <?= htmlspecialchars($w['email']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="tel:+91<?= htmlspecialchars($w['phone']) ?>" class="text-decoration-none fw-bold font-monospace text-dark">
                                        +91 <?= htmlspecialchars($w['phone']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="fw-medium text-secondary"><?= format_date($w['joining_date'], 'd M, Y') ?></span>
                                </td>
                                <td>
                                    <?php if ($w['status'] === 'Active'): ?>
                                        <span class="badge-status badge-status-active">Active</span>
                                    <?php elseif ($w['status'] === 'On Leave'): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-3 py-1 fw-semibold">On Leave</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-status-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group shadow-sm">
                                        <a href="<?= BASE_URL ?>wardens/edit-warden.php?id=<?= $w['id'] ?>" class="btn btn-sm btn-light border text-warning" title="Edit Warden" data-bs-toggle="tooltip">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        
                                        <?php $toggle_url = BASE_URL . "wardens/warden-action.php?action=toggle_status&id=" . $w['id'] . "&csrf_token=" . csrf_token(); ?>
                                        <a href="javascript:void(0)" onclick="confirmStatusChange('<?= $toggle_url ?>', '<?= $w['status'] ?>', '<?= addslashes($w['name']) ?>')" class="btn btn-sm btn-light border text-secondary" title="Toggle Status" data-bs-toggle="tooltip">
                                            <i class="fas fa-rotate"></i>
                                        </a>
                                        
                                        <?php $delete_url = BASE_URL . "wardens/warden-action.php?action=delete&id=" . $w['id'] . "&csrf_token=" . csrf_token(); ?>
                                        <a href="javascript:void(0)" onclick="confirmDelete('<?= $delete_url ?>', 'Warden <?= addslashes($w['name']) ?>')" class="btn btn-sm btn-light border text-danger" title="Remove Warden" data-bs-toggle="tooltip">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
