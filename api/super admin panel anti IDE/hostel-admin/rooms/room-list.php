<?php
/**
 * Rooms Directory View (`rooms/room-list.php`)
 * Displays all rooms with parent hostel names, occupancy metrics, filtering by hostel, and quick status toggling.
 */

require_once __DIR__ . '/../includes/auth.php';

$page_title = "Rooms Directory - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';

$filter_hostel_id = filter_input(INPUT_GET, 'hostel_id', FILTER_VALIDATE_INT);

try {
    $pdo = get_db_connection();
    
    // Fetch all hostels for filter dropdown
    $hostels_stmt = $pdo->query("SELECT id, hostel_name, hostel_code FROM hostels ORDER BY hostel_name ASC");
    $hostel_list = $hostels_stmt->fetchAll();
    
    // Build room query with optional hostel filter
    if ($filter_hostel_id) {
        $sql = "SELECT r.*, h.hostel_name, h.hostel_code 
                FROM rooms r 
                JOIN hostels h ON r.hostel_id = h.id 
                WHERE r.hostel_id = ? 
                ORDER BY h.hostel_name ASC, r.room_number ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$filter_hostel_id]);
    } else {
        $sql = "SELECT r.*, h.hostel_name, h.hostel_code 
                FROM rooms r 
                JOIN hostels h ON r.hostel_id = h.id 
                ORDER BY h.hostel_name ASC, r.room_number ASC";
        $stmt = $pdo->query($sql);
    }
    $rooms = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Room List Fetch Error: " . $e->getMessage());
    $rooms = [];
    $hostel_list = [];
}
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Rooms Inventory</h4>
        <p class="text-secondary mb-0">Manage student living quarters, bed capacities, and monthly rental structures across campus blocks.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>rooms/add-room.php" class="btn btn-custom-primary shadow-sm">
            <i class="fas fa-plus me-1"></i> Add New Room
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card-custom mb-4 bg-light bg-opacity-50">
    <div class="card-body-custom py-3">
        <form method="GET" action="room-list.php" class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 420px;">
                <label for="hostel_id" class="form-label mb-0 fw-semibold text-secondary text-nowrap"><i class="fas fa-filter me-1"></i> Filter by Block:</label>
                <select class="form-select border-0 shadow-sm" id="hostel_id" name="hostel_id" onchange="this.form.submit()">
                    <option value="">All Hostel Blocks (<?= count($rooms) ?> total rooms)</option>
                    <?php foreach ($hostel_list as $h): ?>
                        <option value="<?= $h['id'] ?>" <?= ($filter_hostel_id === (int)$h['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['hostel_name']) ?> (<?= htmlspecialchars($h['hostel_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filter_hostel_id): ?>
                <a href="room-list.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Clear Filter
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card-custom">
    <div class="card-body-custom">
        <?php if (empty($rooms)): ?>
            <div class="text-center py-5">
                <div class="avatar-circle mx-auto mb-3" style="width: 72px; height: 72px; font-size: 1.8rem; background: #f1f5f9; color: #94a3b8;">
                    <i class="fas fa-door-closed"></i>
                </div>
                <h5 class="fw-bold text-dark">No Rooms Found</h5>
                <p class="text-secondary max-w-sm mx-auto mb-4">No rooms match your filter criteria. Click below to add your first room assignment.</p>
                <a href="<?= BASE_URL ?>rooms/add-room.php" class="btn btn-custom-primary px-4 py-2">
                    <i class="fas fa-plus me-1"></i> Add Room Now
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table id="dataTable" class="table table-hover align-middle mb-0 w-100">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Room No.</th>
                            <th>Hostel Block</th>
                            <th>Room Type</th>
                            <th>Capacity</th>
                            <th>Monthly Rent</th>
                            <th>Status</th>
                            <th class="text-end" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $index => $room): ?>
                            <tr>
                                <td class="text-secondary font-monospace"><?= $index + 1 ?></td>
                                <td>
                                    <span class="fw-bold text-dark fs-6 font-monospace d-block"><?= htmlspecialchars($room['room_number']) ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark d-block"><?= htmlspecialchars($room['hostel_name']) ?></span>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($room['hostel_code']) ?></small>
                                </td>
                                <td>
                                    <?php
                                    $type_badges = [
                                        'Single'    => 'bg-purple bg-opacity-10 text-purple border border-purple border-opacity-25',
                                        'Double'    => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25',
                                        'Triple'    => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25',
                                        'Dormitory' => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25'
                                    ];
                                    $badge_class = $type_badges[$room['room_type']] ?? 'bg-light text-dark border';
                                    ?>
                                    <span class="badge <?= $badge_class ?> px-3 py-1 fw-medium">
                                        <?= htmlspecialchars($room['room_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary font-monospace fs-6"><?= $room['capacity'] ?></span>
                                    <small class="text-muted">Beds</small>
                                </td>
                                <td>
                                    <span class="fw-bold text-success font-monospace fs-6">â‚¹<?= number_format($room['rent_per_month'], 2) ?></span>
                                </td>
                                <td>
                                    <?php if ($room['status'] === 'Available'): ?>
                                        <span class="badge-status badge-status-active">Available</span>
                                    <?php elseif ($room['status'] === 'Full'): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-1 fw-semibold">Occupied / Full</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-status-inactive">Maintenance</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group shadow-sm">
                                        <a href="<?= BASE_URL ?>rooms/edit-room.php?id=<?= $room['id'] ?>" class="btn btn-sm btn-light border text-warning" title="Edit Room" data-bs-toggle="tooltip">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        
                                        <?php $toggle_url = BASE_URL . "rooms/room-action.php?action=toggle_status&id=" . $room['id'] . "&csrf_token=" . csrf_token(); ?>
                                        <a href="javascript:void(0)" onclick="confirmStatusChange('<?= $toggle_url ?>', '<?= $room['status'] ?>', 'Room <?= addslashes($room['room_number']) ?>')" class="btn btn-sm btn-light border text-secondary" title="Toggle Status" data-bs-toggle="tooltip">
                                            <i class="fas fa-rotate"></i>
                                        </a>
                                        
                                        <?php $delete_url = BASE_URL . "rooms/room-action.php?action=delete&id=" . $room['id'] . "&csrf_token=" . csrf_token(); ?>
                                        <a href="javascript:void(0)" onclick="confirmDelete('<?= $delete_url ?>', 'Room <?= addslashes($room['room_number']) ?>')" class="btn btn-sm btn-light border text-danger" title="Delete Room" data-bs-toggle="tooltip">
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
