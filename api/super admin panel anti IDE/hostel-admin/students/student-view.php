<?php
/**
 * Detailed Student Profile View (`students/student-view.php`)
 * Comprehensive display of student resident profile, guardian contacts, room assignments, and financial summary.
 */

require_once __DIR__ . '/../includes/auth.php';

$student_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$student_id) {
    set_flash_message('error', 'Invalid student ID specified.');
    redirect('students/student-list.php');
}

try {
    $pdo = get_db_connection();
    $sql = "SELECT s.*, h.hostel_name, h.hostel_code, h.address AS hostel_address, 
                   r.room_number, r.room_type, r.rent_per_month 
            FROM students s 
            JOIN hostels h ON s.hostel_id = h.id 
            JOIN rooms r ON s.room_id = r.id 
            WHERE s.id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id]);
    $stu = $stmt->fetch();
    
    if (!$stu) {
        set_flash_message('error', 'Student record not found.');
        redirect('students/student-list.php');
    }
} catch (PDOException $e) {
    error_log("Student Profile Fetch Error: " . $e->getMessage());
    set_flash_message('error', 'System error fetching profile.');
    redirect('students/student-list.php');
}

$page_title = "Student Profile: " . htmlspecialchars($stu['name']) . " - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Student Resident Profile</h4>
        <p class="text-secondary mb-0">Comprehensive profile and living quarter details for <span class="fw-semibold"><?= htmlspecialchars($stu['name']) ?></span>.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>students/edit-student.php?id=<?= $stu['id'] ?>" class="btn btn-warning fw-bold shadow-sm">
            <i class="fas fa-pen me-1"></i> Edit Profile
        </a>
        <a href="<?= BASE_URL ?>students/student-list.php" class="btn btn-outline-secondary rounded-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Directory
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Student Summary Card -->
    <div class="col-lg-4">
        <div class="card-custom text-center p-4">
            <div class="avatar-circle mx-auto mb-3 shadow" style="width: 96px; height: 96px; font-size: 2.5rem; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white;">
                <?= strtoupper(substr($stu['name'], 0, 2)) ?>
            </div>
            <h5 class="fw-bold mb-1"><?= htmlspecialchars($stu['name']) ?></h5>
            <span class="badge bg-primary bg-opacity-10 text-primary font-monospace px-3 py-1 mb-3">
                <?= htmlspecialchars($stu['student_code']) ?>
            </span>
            
            <div class="d-flex justify-content-center mb-4">
                <?php if ($stu['status'] === 'Active'): ?>
                    <span class="badge-status badge-status-active px-4 py-2 fs-6">ðŸŸ¢ Active Resident</span>
                <?php else: ?>
                    <span class="badge bg-secondary text-white px-4 py-2 fs-6">âšª Vacated / Alumni</span>
                <?php endif; ?>
            </div>
            
            <div class="border-top pt-3 text-start">
                <div class="mb-3">
                    <label class="text-muted fs-7 d-block mb-1">STUDENT EMAIL</label>
                    <a href="mailto:<?= htmlspecialchars($stu['email']) ?>" class="fw-semibold text-primary text-decoration-none">
                        <i class="fas fa-envelope me-2"></i><?= htmlspecialchars($stu['email']) ?>
                    </a>
                </div>
                <div class="mb-3">
                    <label class="text-muted fs-7 d-block mb-1">STUDENT CONTACT MOBILE</label>
                    <a href="tel:+91<?= htmlspecialchars($stu['phone']) ?>" class="fw-bold text-dark font-monospace text-decoration-none fs-6">
                        <i class="fas fa-phone me-2 text-success"></i>+91 <?= htmlspecialchars($stu['phone']) ?>
                    </a>
                </div>
                <div>
                    <label class="text-muted fs-7 d-block mb-1">ADMISSION DATE</label>
                    <span class="fw-medium text-dark"><i class="fas fa-calendar-check me-2 text-secondary"></i><?= format_date($stu['admission_date'], 'F d, Y') ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Room Details & Guardian Info -->
    <div class="col-lg-8">
        <!-- Room Assignment Card -->
        <div class="card-custom mb-4">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-hotel text-info fs-5"></i>
                    <h6 class="mb-0 fw-bold">Living Quarters & Room Allocation</h6>
                </div>
            </div>
            <div class="card-body-custom p-4">
                <div class="row g-4">
                    <div class="col-md-6 border-end">
                        <label class="text-muted fs-7 d-block mb-1">HOSTEL BLOCK NAME</label>
                        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($stu['hostel_name']) ?></h5>
                        <span class="badge bg-light text-secondary font-monospace border mb-2"><?= htmlspecialchars($stu['hostel_code']) ?></span>
                        <p class="text-secondary small mb-0"><i class="fas fa-map-marker-alt me-1 text-danger"></i> <?= htmlspecialchars($stu['hostel_address']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted fs-7 d-block mb-1">ASSIGNED ROOM NUMBER</label>
                        <h4 class="fw-bold text-primary font-monospace mb-1">Room <?= htmlspecialchars($stu['room_number']) ?></h4>
                        <span class="badge bg-purple bg-opacity-10 text-purple border border-purple border-opacity-25 px-3 py-1 mb-2">
                            <?= htmlspecialchars($stu['room_type']) ?> Occupancy
                        </span>
                        <div class="mt-2 pt-2 border-top">
                            <span class="text-muted small">Monthly Room Fee:</span>
                            <span class="fw-bold text-success font-monospace fs-5 ms-1">â‚¹<?= number_format($stu['rent_per_month'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Guardian Contact Card -->
        <div class="card-custom">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-users-between-lines text-warning fs-5"></i>
                    <h6 class="mb-0 fw-bold">Guardian & Emergency Contact Records</h6>
                </div>
            </div>
            <div class="card-body-custom p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="text-muted fs-7 d-block mb-1">GUARDIAN / PARENT NAME</label>
                        <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($stu['guardian_name']) ?></h5>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted fs-7 d-block mb-1">GUARDIAN CONTACT PHONE</label>
                        <a href="tel:+91<?= htmlspecialchars($stu['guardian_phone']) ?>" class="fw-bold text-dark font-monospace text-decoration-none fs-5">
                            <i class="fas fa-phone-volume me-2 text-primary"></i>+91 <?= htmlspecialchars($stu['guardian_phone']) ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
