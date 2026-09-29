<?php
/**
 * Super Admin Dashboard Page
 * Displays real-time aggregated metrics and recent hostel activities.
 */

require_once __DIR__ . '/includes/auth.php';
$page_title = "Dashboard - " . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';

try {
    $pdo = get_db_connection();
    
    // Aggregated Metrics
    $stats = [
        'total_hostels'  => $pdo->query("SELECT COUNT(*) FROM hostels")->fetchColumn() ?: 0,
        'active_hostels' => $pdo->query("SELECT COUNT(*) FROM hostels WHERE status = 'Active'")->fetchColumn() ?: 0,
        'inactive_hostels' => $pdo->query("SELECT COUNT(*) FROM hostels WHERE status = 'Inactive'")->fetchColumn() ?: 0,
        'total_capacity' => $pdo->query("SELECT COALESCE(SUM(capacity), 0) FROM hostels")->fetchColumn() ?: 0,
    ];
    
    // Recent Hostels (Top 5)
    $stmt = $pdo->query("SELECT id, hostel_name, hostel_code, hostel_type, city, capacity, status FROM hostels ORDER BY id DESC LIMIT 5");
    $recent_hostels = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $stats = ['total_hostels' => 0, 'active_hostels' => 0, 'inactive_hostels' => 0, 'total_capacity' => 0];
    $recent_hostels = [];
}
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($_SESSION['admin_name']) ?>! ðŸ‘‹</h4>
        <p class="text-secondary mb-0">Here is what is happening across your hostel network today.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>hostel/add-hostel.php" class="btn btn-custom-primary">
            <i class="fas fa-plus-circle me-1"></i> Add New Hostel
        </a>
    </div>
</div>

<!-- Metric Stat Cards Grid -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-details">
                <p>Total Hostels</p>
                <h3 data-counter="<?= $stats['total_hostels'] ?>"><?= number_format($stats['total_hostels']) ?></h3>
            </div>
            <div class="stat-icon primary">
                <i class="fas fa-hotel"></i>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-details">
                <p>Active Hostels</p>
                <h3 data-counter="<?= $stats['active_hostels'] ?>"><?= number_format($stats['active_hostels']) ?></h3>
            </div>
            <div class="stat-icon success">
                <i class="fas fa-circle-check"></i>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-details">
                <p>Inactive Hostels</p>
                <h3 data-counter="<?= $stats['inactive_hostels'] ?>"><?= number_format($stats['inactive_hostels']) ?></h3>
            </div>
            <div class="stat-icon danger">
                <i class="fas fa-circle-pause"></i>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-details">
                <p>Total Capacity</p>
                <h3 data-counter="<?= $stats['total_capacity'] ?>"><?= number_format($stats['total_capacity']) ?></h3>
            </div>
            <div class="stat-icon warning">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
</div>

<!-- Recent Hostels & Quick Actions -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-clock-rotate-left text-primary"></i>
                    <h5>Recently Added Hostels</h5>
                </div>
                <a href="<?= BASE_URL ?>hostel/hostel-list.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    View All <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Hostel Name & Code</th>
                                <th>Type</th>
                                <th>City</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_hostels)): ?>
                                <?php foreach ($recent_hostels as $h): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($h['hostel_name']) ?></div>
                                            <small class="text-muted font-monospace"><?= htmlspecialchars($h['hostel_code']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge-type badge-type-<?= htmlspecialchars($h['hostel_type']) ?>">
                                                <?= htmlspecialchars($h['hostel_type']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($h['city']) ?></td>
                                        <td><span class="fw-semibold"><?= number_format($h['capacity']) ?></span> <small class="text-muted">beds</small></td>
                                        <td>
                                            <?php if ($h['status'] === 'Active'): ?>
                                                <span class="badge-status badge-status-active">Active</span>
                                            <?php else: ?>
                                                <span class="badge-status badge-status-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?= BASE_URL ?>hostel/hostel-view.php?id=<?= $h['id'] ?>" class="btn-action btn-action-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>hostel/edit-hostel.php?id=<?= $h['id'] ?>" class="btn-action btn-action-edit" title="Edit">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-folder-open fs-2 mb-2 d-block"></i>
                                        No hostels found. Click <a href="<?= BASE_URL ?>hostel/add-hostel.php">here</a> to add your first hostel.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-bolt text-warning"></i>
                    <h5>Quick Management</h5>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="d-grid gap-3">
                    <a href="<?= BASE_URL ?>hostel/add-hostel.php" class="btn btn-outline-primary d-flex align-items-center justify-content-between p-3 rounded-3 text-start">
                        <div>
                            <div class="fw-bold">Register New Hostel</div>
                            <small class="text-muted">Auto-generate codes & assign capacity</small>
                        </div>
                        <i class="fas fa-chevron-right text-primary"></i>
                    </a>
                    
                    <a href="<?= BASE_URL ?>hostel/hostel-list.php" class="btn btn-outline-success d-flex align-items-center justify-content-between p-3 rounded-3 text-start">
                        <div>
                            <div class="fw-bold">Hostel Directory</div>
                            <small class="text-muted">Search, sort, filter & export records</small>
                        </div>
                        <i class="fas fa-chevron-right text-success"></i>
                    </a>
                    
                    <div class="p-3 rounded-3 bg-light border">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="fas fa-shield-halved text-primary"></i>
                            <span class="fw-bold fs-6">Security Tip</span>
                        </div>
                        <small class="text-secondary d-block">
                            All database queries are protected with PDO Prepared Statements. Session and CSRF checks are active across all modules.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
