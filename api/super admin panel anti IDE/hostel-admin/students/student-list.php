<?php
/**
 * Students Directory (`students/student-list.php`)
 * Displays registered student residents with hostel and room assignments, filtering, and status controls.
 */

require_once __DIR__ . '/../includes/auth.php';

$page_title = "Students Directory - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';

$filter_hostel_id = filter_input(INPUT_GET, 'hostel_id', FILTER_VALIDATE_INT);

try {
    $pdo = get_db_connection();
    $hostel_list = $pdo->query("SELECT id, hostel_name, hostel_code FROM hostels ORDER BY hostel_name ASC")->fetchAll();
    
    if ($filter_hostel_id) {
        $sql = "SELECT s.*, h.hostel_name, h.hostel_code, r.room_number, r.rent_per_month 
                FROM students s 
                JOIN hostels h ON s.hostel_id = h.id 
                JOIN rooms r ON s.room_id = r.id 
                WHERE s.hostel_id = ? 
                ORDER BY s.student_code DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$filter_hostel_id]);
    } else {
        $sql = "SELECT s.*, h.hostel_name, h.hostel_code, r.room_number, r.rent_per_month 
                FROM students s 
                JOIN hostels h ON s.hostel_id = h.id 
                JOIN rooms r ON s.room_id = r.id 
                ORDER BY s.student_code DESC";
        $stmt = $pdo->query($sql);
    }
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Student List Fetch Error: " . $e->getMessage());
    $students = [];
    $hostel_list = [];
}
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Student Residents Directory</h4>
        <p class="text-secondary mb-0">Manage student admissions, room allocations, guardian contacts, and status records.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>students/add-student.php" class="btn btn-custom-primary shadow-sm">
            <i class="fas fa-user-plus me-1"></i> New Student Admission
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card-custom mb-4 bg-light bg-opacity-50">
    <div class="card-body-custom py-3">
        <form method="GET" action="student-list.php" class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 420px;">
                <label for="hostel_id" class="form-label mb-0 fw-semibold text-secondary text-nowrap"><i class="fas fa-filter me-1"></i> Filter by Block:</label>
                <select class="form-select border-0 shadow-sm" id="hostel_id" name="hostel_id" onchange="this.form.submit()">
                    <option value="">All Hostel Blocks (<?= count($students) ?> students)</option>
                    <?php foreach ($hostel_list as $h): ?>
                        <option value="<?= $h['id'] ?>" <?= ($filter_hostel_id === (int)$h['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['hostel_name']) ?> (<?= htmlspecialchars($h['hostel_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filter_hostel_id): ?>
                <a href="student-list.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Clear Filter
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card-custom">
    <div class="card-body-custom">
        <?php if (empty($students)): ?>
            <div class="text-center py-5">
                <div class="avatar-circle mx-auto mb-3" style="width: 72px; height: 72px; font-size: 1.8rem; background: #f1f5f9; color: #94a3b8;">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h5 class="fw-bold text-dark">No Student Admissions Found</h5>
                <p class="text-secondary max-w-sm mx-auto mb-4">No student records match your selection. Click below to register and admit your first student.</p>
                <a href="<?= BASE_URL ?>students/add-student.php" class="btn btn-custom-primary px-4 py-2">
                    <i class="fas fa-user-plus me-1"></i> Admit Student Now
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table id="dataTable" class="table table-hover align-middle mb-0 w-100">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Assigned Room</th>
                            <th>Contact Phone</th>
                            <th>Guardian</th>
                            <th>Admission Date</th>
                            <th>Status</th>
                            <th class="text-end" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $stu): ?>
                            <tr>
                                <td class="text-secondary font-monospace"><?= $index + 1 ?></td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary font-monospace fs-6 px-3 py-1 border border-primary border-opacity-25">
                                        <?= htmlspecialchars($stu['student_code']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                            <?= strtoupper(substr($stu['name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <a href="<?= BASE_URL ?>students/student-view.php?id=<?= $stu['id'] ?>" class="fw-bold text-dark text-decoration-none d-block">
                                                <?= htmlspecialchars($stu['name']) ?>
                                            </a>
                                            <small class="text-muted"><?= htmlspecialchars($stu['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark fs-6 font-monospace">Room <?= htmlspecialchars($stu['room_number']) ?></span>
                                    <small class="text-muted d-block"><?= htmlspecialchars($stu['hostel_name']) ?> (<?= htmlspecialchars($stu['hostel_code']) ?>)</small>
                                </td>
                                <td>
                                    <a href="tel:+91<?= htmlspecialchars($stu['phone']) ?>" class="text-decoration-none fw-bold font-monospace text-dark">
                                        +91 <?= htmlspecialchars($stu['phone']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="fw-medium text-dark d-block"><?= htmlspecialchars($stu['guardian_name']) ?></span>
                                    <small class="text-muted font-monospace">+91 <?= htmlspecialchars($stu['guardian_phone']) ?></small>
                                </td>
                                <td>
                                    <span class="fw-medium text-secondary"><?= format_date($stu['admission_date'], 'd M, Y') ?></span>
                                </td>
                                <td>
                                    <?php if ($stu['status'] === 'Active'): ?>
                                        <span class="badge-status badge-status-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-1 fw-semibold">Vacated</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group shadow-sm">
                                        <a href="<?= BASE_URL ?>students/student-view.php?id=<?= $stu['id'] ?>" class="btn btn-sm btn-light border text-info" title="View Student Profile" data-bs-toggle="tooltip">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <a href="<?= BASE_URL ?>students/edit-student.php?id=<?= $stu['id'] ?>" class="btn btn-sm btn-light border text-warning" title="Edit Student" data-bs-toggle="tooltip">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        
                                        <?php $toggle_url = BASE_URL . "students/student-action.php?action=toggle_status&id=" . $stu['id'] . "&csrf_token=" . csrf_token(); ?>
                                        <a href="javascript:void(0)" onclick="confirmStatusChange('<?= $toggle_url ?>', '<?= $stu['status'] ?>', 'Student <?= addslashes($stu['name']) ?>')" class="btn btn-sm btn-light border text-secondary" title="Toggle Status" data-bs-toggle="tooltip">
                                            <i class="fas fa-rotate"></i>
                                        </a>
                                        
                                        <?php $delete_url = BASE_URL . "students/student-action.php?action=delete&id=" . $stu['id'] . "&csrf_token=" . csrf_token(); ?>
                                        <a href="javascript:void(0)" onclick="confirmDelete('<?= $delete_url ?>', 'Student <?= addslashes($stu['name']) ?>')" class="btn btn-sm btn-light border text-danger" title="Delete Record" data-bs-toggle="tooltip">
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
