<?php
/**
 * Edit Student Form (`students/edit-student.php`)
 * Pre-populates student record, allows changing room allocation via AJAX, modifying status and guardian info.
 */

require_once __DIR__ . '/../includes/auth.php';

$student_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$student_id) {
    set_flash_message('error', 'Invalid student ID specified.');
    redirect('students/student-list.php');
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT s.*, h.hostel_name, h.hostel_code, r.room_number FROM students s JOIN hostels h ON s.hostel_id = h.id JOIN rooms r ON s.room_id = r.id WHERE s.id = ? LIMIT 1");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        set_flash_message('error', 'Student record not found.');
        redirect('students/student-list.php');
    }
    
    $hostel_list = $pdo->query("SELECT id, hostel_name, hostel_code FROM hostels ORDER BY hostel_name ASC")->fetchAll();
    
    // Fetch rooms for currently selected hostel
    $rooms_stmt = $pdo->prepare("SELECT id, room_number, room_type, rent_per_month, status FROM rooms WHERE hostel_id = ? ORDER BY room_number ASC");
    $rooms_stmt->execute([$student['hostel_id']]);
    $current_rooms = $rooms_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Edit Student Fetch Error: " . $e->getMessage());
    set_flash_message('error', 'System error fetching student details.');
    redirect('students/student-list.php');
}

$page_title = "Edit Student " . htmlspecialchars($student['name']) . " - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Edit Student Record</h4>
        <p class="text-secondary mb-0">Modify admission details for <strong class="text-dark font-monospace"><?= htmlspecialchars($student['student_code']) ?></strong> (<span class="fw-semibold"><?= htmlspecialchars($student['name']) ?></span>).</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>students/student-list.php" class="btn btn-outline-secondary rounded-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Directory
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card-custom">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-user-pen text-warning fs-5"></i>
                    <h5 class="mb-0">Modify Student Profile & Allocation</h5>
                </div>
            </div>
            <div class="card-body-custom p-4">
                <form action="<?= BASE_URL ?>students/student-action.php" method="POST" id="editStudentForm" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $student['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    
                    <h6 class="fw-bold text-secondary text-uppercase fs-7 mb-3 border-bottom pb-2">
                        <i class="fas fa-id-card me-2 text-primary"></i> 1. Academic & Personal Information
                    </h6>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Student ID Code</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light font-monospace"><i class="fas fa-hashtag text-primary"></i></span>
                                <input type="text" class="form-control font-monospace fw-bold bg-light" value="<?= htmlspecialchars($student['student_code']) ?>" readonly disabled>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="name" class="form-label">Student Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold" id="name" name="name" value="<?= htmlspecialchars($student['name']) ?>" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="admission_date" class="form-label">Admission Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control font-monospace" id="admission_date" name="admission_date" value="<?= htmlspecialchars($student['admission_date']) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Student Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Student Contact Mobile <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+91</span>
                                <input type="text" class="form-control font-monospace" id="phone" name="phone" value="<?= htmlspecialchars($student['phone']) ?>" required pattern="^[0-9]{10}$" maxlength="10">
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-secondary text-uppercase fs-7 mb-3 border-bottom pb-2">
                        <i class="fas fa-hotel me-2 text-info"></i> 2. Living Quarters & Room Allocation
                    </h6>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label for="hostel_id" class="form-label">Assigned Hostel Block <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="hostel_id" name="hostel_id" required onchange="loadRoomsForHostel(this.value, null)">
                                <?php foreach ($hostel_list as $h): ?>
                                    <option value="<?= $h['id'] ?>" <?= ($student['hostel_id'] == $h['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($h['hostel_name']) ?> (<?= htmlspecialchars($h['hostel_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="room_id" class="form-label">Select Room Number <span class="text-danger">*</span></label>
                            <select class="form-select fw-bold text-dark font-monospace" id="room_id" name="room_id" required>
                                <?php foreach ($current_rooms as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= ($student['room_id'] == $r['id']) ? 'selected' : '' ?>>
                                        Room <?= htmlspecialchars($r['room_number']) ?> (<?= $r['room_type'] ?>, â‚¹<?= number_format($r['rent_per_month'], 0) ?>/mo)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="roomHelper" class="text-muted d-block mt-1">Change hostel to dynamically load other available rooms.</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="status" class="form-label">Resident Status <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="status" name="status" required>
                                <option value="Active" <?= ($student['status'] === 'Active') ? 'selected' : '' ?> style="color: #10b981;">ðŸŸ¢ Active Resident</option>
                                <option value="Vacated" <?= ($student['status'] === 'Vacated') ? 'selected' : '' ?> style="color: #64748b;">âšª Vacated / Alumni</option>
                            </select>
                        </div>
                    </div>

                    <h6 class="fw-bold text-secondary text-uppercase fs-7 mb-3 border-bottom pb-2">
                        <i class="fas fa-users-between-lines me-2 text-warning"></i> 3. Guardian & Emergency Contact
                    </h6>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label for="guardian_name" class="form-label">Guardian / Parent Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="guardian_name" name="guardian_name" value="<?= htmlspecialchars($student['guardian_name']) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="guardian_phone" class="form-label">Guardian Contact Mobile <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+91</span>
                                <input type="text" class="form-control font-monospace" id="guardian_phone" name="guardian_phone" value="<?= htmlspecialchars($student['guardian_phone']) ?>" required pattern="^[0-9]{10}$" maxlength="10">
                            </div>
                        </div>
                    </div>
                    
                    <div class="pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>students/student-list.php" class="btn btn-light border px-4">Cancel</a>
                        <button type="submit" class="btn btn-warning fw-bold px-5 py-2 shadow">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function loadRoomsForHostel(hostelId, selectedRoomId) {
    const roomSelect = document.getElementById('room_id');
    const helper = document.getElementById('roomHelper');
    
    if (!hostelId) return;
    
    roomSelect.innerHTML = '<option value="">Loading rooms via AJAX...</option>';
    roomSelect.disabled = true;
    
    fetch('<?= BASE_URL ?>rooms/room-action.php?action=get_rooms&hostel_id=' + hostelId)
        .then(response => response.json())
        .then(data => {
            roomSelect.innerHTML = '';
            if (data.status === 'success' && data.rooms.length > 0) {
                let options = '<option value="">-- Select Room --</option>';
                data.rooms.forEach(room => {
                    const sel = (selectedRoomId && selectedRoomId == room.id) ? 'selected' : '';
                    options += `<option value="${room.id}" ${sel}>Room ${room.room_number} (${room.room_type}, â‚¹${room.rent_per_month}/mo)</option>`;
                });
                roomSelect.innerHTML = options;
                roomSelect.disabled = false;
                helper.innerHTML = `<i class="fas fa-check-circle me-1"></i> Loaded ${data.rooms.length} rooms for this block.`;
                helper.className = 'text-success d-block mt-1';
            } else {
                roomSelect.innerHTML = '<option value="">-- No Rooms Registered --</option>';
                roomSelect.disabled = true;
                helper.innerHTML = 'No rooms found for selected block.';
                helper.className = 'text-danger d-block mt-1';
            }
        })
        .catch(error => {
            console.error('Error fetching rooms:', error);
            roomSelect.innerHTML = '<option value="">Error loading rooms</option>';
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editStudentForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
