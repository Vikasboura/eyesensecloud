<?php
/**
 * Executive Analytics & Reports Dashboard (`reports/index.php`)
 * Displays real-time campus occupancy rates, room distribution summaries, and financial capacity estimation.
 */

require_once __DIR__ . '/../includes/auth.php';

try {
    $pdo = get_db_connection();
    
    // Overall metrics
    $hostel_count = $pdo->query("SELECT COUNT(*) FROM hostels WHERE status = 'Active'")->fetchColumn();
    $room_count   = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    $student_count = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
    $warden_count  = $pdo->query("SELECT COUNT(*) FROM wardens WHERE status = 'Active'")->fetchColumn();
    
    // Estimated Monthly Revenue Capacity
    $revenue_stmt = $pdo->query("SELECT SUM(capacity * rent_per_month) FROM rooms WHERE status != 'Under Maintenance'");
    $monthly_revenue_cap = (float) $revenue_stmt->fetchColumn();
    
    // Actual Monthly Revenue from Active Students
    $actual_rev_stmt = $pdo->query("SELECT SUM(r.rent_per_month) FROM students s JOIN rooms r ON s.room_id = r.id WHERE s.status = 'Active'");
    $actual_monthly_revenue = (float) $actual_rev_stmt->fetchColumn();
    
    // Block-by-block breakdown
    $sql_blocks = "SELECT h.id, h.hostel_name, h.hostel_code, h.hostel_type, h.capacity AS stated_capacity,
                          (SELECT COUNT(*) FROM rooms WHERE hostel_id = h.id) AS total_rooms,
                          (SELECT COALESCE(SUM(capacity), 0) FROM rooms WHERE hostel_id = h.id) AS room_bed_capacity,
                          (SELECT COUNT(*) FROM students WHERE hostel_id = h.id AND status = 'Active') AS active_students,
                          (SELECT COUNT(*) FROM wardens WHERE hostel_id = h.id AND status = 'Active') AS active_wardens
                   FROM hostels h 
                   ORDER BY h.hostel_name ASC";
    $blocks = $pdo->query($sql_blocks)->fetchAll();
    
    // Room Type breakdown
    $sql_types = "SELECT room_type, COUNT(*) as count, COALESCE(SUM(capacity),0) as beds, COALESCE(AVG(rent_per_month),0) as avg_rent 
                  FROM rooms GROUP BY room_type ORDER BY beds DESC";
    $types = $pdo->query($sql_types)->fetchAll();
} catch (PDOException $e) {
    error_log("Reports Fetch Error: " . $e->getMessage());
    $blocks = [];
    $types = [];
    $hostel_count = $room_count = $student_count = $warden_count = 0;
    $monthly_revenue_cap = $actual_monthly_revenue = 0;
}

$page_title = "Executive Reports & Analytics - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Campus Analytics & Financial Reports</h4>
        <p class="text-secondary mb-0">Live occupancy progress bars, financial rental yields, and block capacity distribution.</p>
    </div>
    <div>
        <button onclick="window.print()" class="btn btn-outline-primary rounded-3 shadow-sm">
            <i class="fas fa-print me-1"></i> Export / Print Summary
        </button>
    </div>
</div>

<!-- Executive Metric Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card-custom h-100 p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-bold text-uppercase d-block mb-1">Total Capacity Beds</span>
                    <h3 class="fw-bold text-dark mb-0 font-monospace"><?= $student_count ?> / <?= array_sum(array_column($blocks, 'room_bed_capacity')) ?: $student_count ?></h3>
                    <span class="badge bg-success bg-opacity-10 text-success mt-2">
                        <i class="fas fa-user-check me-1"></i> Active Students
                    </span>
                </div>
                <div class="avatar-circle" style="width: 54px; height: 54px; font-size: 1.4rem; background: #e0f2fe; color: #0369a1;">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-sm-6">
        <div class="card-custom h-100 p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-bold text-uppercase d-block mb-1">Registered Quarters</span>
                    <h3 class="fw-bold text-dark mb-0 font-monospace"><?= $room_count ?> Rooms</h3>
                    <span class="badge bg-info bg-opacity-10 text-info mt-2">
                        Across <?= $hostel_count ?> Blocks
                    </span>
                </div>
                <div class="avatar-circle" style="width: 54px; height: 54px; font-size: 1.4rem; background: #f3e8ff; color: #7e22ce;">
                    <i class="fas fa-door-open"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-sm-6">
        <div class="card-custom h-100 p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-bold text-uppercase d-block mb-1">Actual Monthly Yield</span>
                    <h3 class="fw-bold text-success mb-0 font-monospace">â‚¹<?= number_format($actual_monthly_revenue, 0) ?></h3>
                    <span class="badge bg-success bg-opacity-10 text-success mt-2">
                        <i class="fas fa-chart-line me-1"></i> From Resident Rent
                    </span>
                </div>
                <div class="avatar-circle" style="width: 54px; height: 54px; font-size: 1.4rem; background: #dcfce7; color: #15803d;">
                    <i class="fas fa-indian-rupee-sign"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-sm-6">
        <div class="card-custom h-100 p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-bold text-uppercase d-block mb-1">Max Potential Yield</span>
                    <h3 class="fw-bold text-primary mb-0 font-monospace">â‚¹<?= number_format($monthly_revenue_cap, 0) ?></h3>
                    <span class="badge bg-primary bg-opacity-10 text-primary mt-2">
                        100% Full Capacity
                    </span>
                </div>
                <div class="avatar-circle" style="width: 54px; height: 54px; font-size: 1.4rem; background: #e0e7ff; color: #4338ca;">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Block-by-Block Occupancy Breakdown Table -->
    <div class="col-lg-8">
        <div class="card-custom h-100">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-building text-primary fs-5"></i>
                        <h6 class="mb-0 fw-bold">Hostel Block Occupancy & Supervision</h6>
                    </div>
                    <span class="badge bg-primary rounded-pill"><?= count($blocks) ?> Blocks</span>
                </div>
            </div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Block Name</th>
                                <th>Type</th>
                                <th>Rooms</th>
                                <th>Occupancy Status</th>
                                <th>Wardens</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($blocks)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No blocks registered.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($blocks as $b): 
                                    $cap = $b['room_bed_capacity'] ?: $b['stated_capacity'];
                                    $occ = $b['active_students'];
                                    $percent = ($cap > 0) ? round(($occ / $cap) * 100, 1) : 0;
                                    
                                    if ($percent >= 90) {
                                        $bar_class = 'bg-danger';
                                    } elseif ($percent >= 60) {
                                        $bar_class = 'bg-warning';
                                    } else {
                                        $bar_class = 'bg-success';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars($b['hostel_name']) ?></span>
                                            <small class="text-muted font-monospace"><?= htmlspecialchars($b['hostel_code']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($b['hostel_type']) ?></span>
                                        </td>
                                        <td>
                                            <span class="fw-bold font-monospace fs-6"><?= $b['total_rooms'] ?></span>
                                        </td>
                                        <td style="min-width: 180px;">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small fw-bold font-monospace"><?= $occ ?> / <?= $cap ?> Beds</span>
                                                <span class="badge <?= $bar_class ?> bg-opacity-10 text-dark small fw-bold font-monospace"><?= $percent ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar <?= $bar_class ?>" role="progressbar" style="width: <?= $percent ?>%" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info bg-opacity-10 text-info fw-bold font-monospace px-3 py-1">
                                                <i class="fas fa-user-tie me-1"></i><?= $b['active_wardens'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Room Category Distribution Card -->
    <div class="col-lg-4">
        <div class="card-custom h-100">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-layer-group text-purple fs-5"></i>
                    <h6 class="mb-0 fw-bold">Room Category Analysis</h6>
                </div>
            </div>
            <div class="card-body-custom p-4">
                <?php if (empty($types)): ?>
                    <p class="text-center text-muted py-4">No rooms categorized yet.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($types as $t): ?>
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($t['room_type']) ?> Occupancy</span>
                                    <span class="badge bg-primary bg-opacity-10 text-primary font-monospace"><?= $t['count'] ?> Rooms</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center text-secondary small">
                                    <span>Total Bed Capacity: <strong class="text-dark font-monospace"><?= $t['beds'] ?></strong></span>
                                    <span>Avg Fee: <strong class="text-success font-monospace">â‚¹<?= number_format($t['avg_rent'], 0) ?></strong></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4 pt-3 border-top text-center">
                    <span class="text-muted small d-block mb-2"><i class="fas fa-shield-halved text-success me-1"></i> System Data Verified Live</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
