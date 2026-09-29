<?php
/**
 * Student Admission Form (`students/add-student.php`)
 * Admits new students, features dynamic AJAX loading of available rooms when a hostel block is chosen.
 */

require_once __DIR__ . '/../includes/auth.php';

try {
    $pdo = get_db_connection();
    $hostel_list = $pdo->query("SELECT id, hostel_name, hostel_code FROM hostels WHERE status = 'Active' ORDER BY hostel_name ASC")->fetchAll();
    
    // Auto-calculate next student code (e.g. STU004)
    $next_code_stmt = $pdo->query("SELECT student_code FROM students ORDER BY id DESC LIMIT 1");
    $last_code = $next_code_stmt->fetchColumn();
    if ($last_code && preg_match('/^STU(\d+)$/', $last_code, $matches)) {
        $next_num = intval($matches[1]) + 1;
        $default_code = 'STU' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
    } else {
        $default_code = 'STU001';
    }
} catch (PDOException $e) {
    error_log("Add Student Fetch Error: " . $e->getMessage());
    $hostel_list = [];
    $default_code = 'STU001';
}

$page_title = "New Student Admission - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">New Student Admission</h4>
        <p class="text-secondary mb-0">Admit a student resident, assign a living room via dynamic block loading, and register guardian details.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>students/student-list.php" class="btn btn-outline-secondary rounded-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Students Directory
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card-custom">
            <div class="card-header-custom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-user-plus text-primary fs-5"></i>
                    <h5 class="mb-0">Admission & Room Allocation Details</h5>
                </div>
            </div>
            <div class="card-body-custom p-4">
                
                <?php if (empty($hostel_list)): ?>
                    <div class="alert alert-warning">
                        <strong>No Active Hostel Blocks!</strong> You must register and activate at least one hostel before admitting students.
                    </div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>students/student-action.php" method="POST" id="addStudentForm" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    
                    <h6 class="fw-bold text-secondary text-uppercase fs-7 mb-3 border-bottom pb-2">
                        <i class="fas fa-id-card me-2 text-primary"></i> 1. Academic & Personal Information
                    </h6>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <label for="student_code" class="form-label">Student ID Code <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light font-monospace"><i class="fas fa-hashtag text-primary"></i></span>
                                <input type="text" class="form-control font-monospace fw-bold text-primary" id="student_code" name="student_code" value="<?= htmlspecialchars($default_code) ?>" required readonly>
                            </div>
                            <small class="text-muted">Auto-generated unique admission ID.</small>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="name" class="form-label">Student Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold" id="name" name="name" required placeholder="e.g. Aarav Verma">
                            <div class="invalid-feedback">Student name is required.</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="admission_date" class="form-label">Admission Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control font-monospace" id="admission_date" name="admission_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Student Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="e.g. aarav.v@example.com">
                            <div class="invalid-feedback">Valid unique email address required.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Student Contact Mobile <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+91</span>
                                <input type="text" class="form-control font-monospace" id="phone" name="phone" required pattern="^[0-9]{10}$" maxlength="10" placeholder="10-digit mobile">
                            </div>
                            <div class="invalid-feedback">Must be exactly 10 digits.</div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-secondary text-uppercase fs-7 mb-3 border-bottom pb-2">
                        <i class="fas fa-hotel me-2 text-info"></i> 2. Living Quarters & Room Allocation
                    </h6>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label for="hostel_id" class="form-label">Assigned Hostel Block <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="hostel_id" name="hostel_id" required onchange="loadRoomsForHostel(this.value)">
                                <option value="">-- Select Hostel Block First --</option>
                                <?php foreach ($hostel_list as $h): ?>
                                    <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['hostel_name']) ?> (<?= htmlspecialchars($h['hostel_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select the hostel block.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="room_id" class="form-label">Select Room Number <span class="text-danger">*</span></label>
                            <select class="form-select fw-bold text-dark font-monospace" id="room_id" name="room_id" required disabled>
                                <option value="">-- Choose Hostel Above to Load Rooms --</option>
                            </select>
                            <div class="invalid-feedback">Please select a living room.</div>
                            <small id="roomHelper" class="text-muted d-block mt-1">Select a block to fetch available quarters.</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="status" class="form-label">Resident Status <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" id="status" name="status" required>
                                <option value="Active" selected style="color: #10b981;">ðŸŸ¢ Active Resident</option>
                                <option value="Vacated" style="color: #64748b;">âšª Vacated / Alumni</option>
                            </select>
                        </div>
                    </div>

                    <h6 class="fw-bold text-secondary text-uppercase fs-7 mb-3 border-bottom pb-2">
                        <i class="fas fa-users-between-lines me-2 text-warning"></i> 3. Guardian & Emergency Contact
                    </h6>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label for="guardian_name" class="form-label">Guardian / Parent Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="guardian_name" name="guardian_name" required placeholder="e.g. Suresh Verma">
                            <div class="invalid-feedback">Guardian name is required.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="guardian_phone" class="form-label">Guardian Contact Mobile <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+91</span>
                                <input type="text" class="form-control font-monospace" id="guardian_phone" name="guardian_phone" required pattern="^[0-9]{10}$" maxlength="10" placeholder="10-digit mobile">
                            </div>
                            <div class="invalid-feedback">Guardian phone must be exactly 10 digits.</div>
                        </div>
                    </div>
                    
                    <div class="pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>students/student-list.php" class="btn btn-light border px-4">Cancel</a>
                        <button type="submit" class="btn btn-custom-primary px-5 py-2 shadow" <?= empty($hostel_list) ? 'disabled' : '' ?>>
                            <i class="fas fa-save me-2"></i> Confirm Admission
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function loadRoomsForHostel(hostelId) {
    const roomSelect = document.getElementById('room_id');
    const helper = document.getElementById('roomHelper');
    
    if (!hostelId) {
        roomSelect.innerHTML = '<option value="">-- Choose Hostel Above to Load Rooms --</option>';
        roomSelect.disabled = true;
        helper.innerHTML = 'Select a block to fetch available quarters.';
        helper.className = 'text-muted d-block mt-1';
        return;
    }
    
    roomSelect.innerHTML = '<option value="">Loading rooms via AJAX...</option>';
    roomSelect.disabled = true;
    helper.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Fetching rooms from server...';
    helper.className = 'text-primary d-block mt-1';
    
    fetch('<?= BASE_URL ?>rooms/room-action.php?action=get_rooms&hostel_id=' + hostelId)
        .then(response => response.json())
        .then(data => {
            roomSelect.innerHTML = '';
            if (data.status === 'success' && data.rooms.length > 0) {
                let options = '<option value="">-- Select Room --</option>';
                data.rooms.forEach(room => {
                    const statusText = (room.status === 'Available') ? ' [Available]' : ' [' + room.status + ']';
                    options += `<option value="${room.id}">Room ${room.room_number} (${room.room_type}, â‚¹${room.rent_per_month}/mo)${statusText}</option>`;
                });
                roomSelect.innerHTML = options;
                roomSelect.disabled = false;
                helper.innerHTML = `<i class="fas fa-check-circle me-1"></i> Loaded ${data.rooms.length} rooms for this block.`;
                helper.className = 'text-success d-block mt-1';
            } else {
                roomSelect.innerHTML = '<option value="">-- No Rooms Registered in this Block --</option>';
                roomSelect.disabled = true;
                helper.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Please add rooms to this block first.';
                helper.className = 'text-danger d-block mt-1';
            }
        })
        .catch(error => {
            console.error('Error fetching rooms:', error);
            roomSelect.innerHTML = '<option value="">Error loading rooms</option>';
            helper.innerHTML = 'Failed to load rooms via AJAX.';
            helper.className = 'text-danger d-block mt-1';
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addStudentForm');
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
