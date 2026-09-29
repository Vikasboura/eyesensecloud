<?php
/**
 * System Settings & Super Admin Profile (`settings/index.php`)
 * Provides multi-tab configuration for Super Admin credentials, password updates, and global site preferences.
 */

require_once __DIR__ . '/../includes/auth.php';

try {
    $pdo = get_db_connection();
    
    // Fetch current admin profile
    $admin_id = $_SESSION['admin_id'] ?? 1;
    $admin_stmt = $pdo->prepare("SELECT name, email, role, status FROM admins WHERE id = ? LIMIT 1");
    $admin_stmt->execute([$admin_id]);
    $admin_user = $admin_stmt->fetch();
    
    // Fetch key-value system settings into an associative array
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM hostel_system_settings");
    $settings_raw = $settings_stmt->fetchAll();
    $sys_settings = [];
    foreach ($settings_raw as $row) {
        $sys_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Settings Fetch Error: " . $e->getMessage());
    $admin_user = ['name' => 'Super Admin', 'email' => 'admin@hostel.com', 'role' => 'Super Admin'];
    $sys_settings = ['site_name' => APP_NAME, 'currency_symbol' => 'â‚¹', 'contact_email' => 'admin@hostel.com', 'maintenance_mode' => '0'];
}

$page_title = "System Settings & Profile - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">System Configuration & Profile</h4>
        <p class="text-secondary mb-0">Manage super admin security credentials, update login password, and customize site preferences.</p>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-pills gap-2 mb-4" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold px-4 py-2" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile-pane" type="button" role="tab" aria-controls="profile-pane" aria-selected="true">
            <i class="fas fa-user-shield me-2"></i> Super Admin Profile & Password
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold px-4 py-2" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-pane" type="button" role="tab" aria-controls="general-pane" aria-selected="false">
            <i class="fas fa-sliders me-2"></i> General Application Settings
        </button>
    </li>
</ul>

<div class="tab-content" id="settingsTabsContent">
    
    <!-- Tab 1: Profile & Password -->
    <div class="tab-pane fade show active" id="profile-pane" role="tabpanel" aria-labelledby="profile-tab" tabindex="0">
        <div class="row g-4">
            <div class="col-lg-6">
                <!-- Profile Information Card -->
                <div class="card-custom h-100">
                    <div class="card-header-custom bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-user-gear text-primary fs-5"></i>
                            <h6 class="mb-0 fw-bold">Admin Account Information</h6>
                        </div>
                    </div>
                    <div class="card-body-custom p-4">
                        <form action="<?= BASE_URL ?>settings/settings-action.php" method="POST" id="updateProfileForm" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control fw-bold" id="name" name="name" value="<?= htmlspecialchars($admin_user['name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Login Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control font-monospace" id="email" name="email" value="<?= htmlspecialchars($admin_user['email'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Assigned Role</label>
                                <input type="text" class="form-control bg-light text-secondary fw-semibold" value="<?= htmlspecialchars($admin_user['role'] ?? 'Super Admin') ?>" readonly disabled>
                            </div>
                            
                            <div class="d-flex justify-content-end pt-2 border-top">
                                <button type="submit" class="btn btn-custom-primary px-4 py-2 shadow-sm">
                                    <i class="fas fa-save me-2"></i> Update Profile Details
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <!-- Change Password Card -->
                <div class="card-custom h-100">
                    <div class="card-header-custom bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-key text-warning fs-5"></i>
                            <h6 class="mb-0 fw-bold">Change Login Password</h6>
                        </div>
                    </div>
                    <div class="card-body-custom p-4">
                        <form action="<?= BASE_URL ?>settings/settings-action.php" method="POST" id="changePasswordForm" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control font-monospace" id="current_password" name="current_password" required placeholder="Enter existing password">
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control font-monospace" id="new_password" name="new_password" required minlength="6" placeholder="At least 6 characters">
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control font-monospace" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Repeat new password">
                            </div>
                            
                            <div class="d-flex justify-content-end pt-2 border-top">
                                <button type="submit" class="btn btn-warning fw-bold px-4 py-2 shadow-sm">
                                    <i class="fas fa-lock me-2"></i> Update Security Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tab 2: General Settings -->
    <div class="tab-pane fade" id="general-pane" role="tabpanel" aria-labelledby="general-tab" tabindex="0">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-sliders text-info fs-5"></i>
                            <h6 class="mb-0 fw-bold">General System Configuration</h6>
                        </div>
                    </div>
                    <div class="card-body-custom p-4">
                        <form action="<?= BASE_URL ?>settings/settings-action.php" method="POST" id="generalSettingsForm" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="update_settings">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label for="site_name" class="form-label">Application Portal Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control fw-bold" id="site_name" name="site_name" value="<?= htmlspecialchars($sys_settings['site_name'] ?? APP_NAME) ?>" required>
                                    <small class="text-muted">Shown in top navigation header and system reports.</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="currency_symbol" class="form-label">Default Currency Symbol <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control font-monospace fw-bold text-success" id="currency_symbol" name="currency_symbol" value="<?= htmlspecialchars($sys_settings['currency_symbol'] ?? 'â‚¹') ?>" required maxlength="5">
                                    <small class="text-muted">Used for rent calculations and revenue yields.</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="contact_email" class="form-label">Official System Alert Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control font-monospace" id="contact_email" name="contact_email" value="<?= htmlspecialchars($sys_settings['contact_email'] ?? 'admin@hostel.com') ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="maintenance_mode" class="form-label">System Maintenance Mode <span class="text-danger">*</span></label>
                                    <select class="form-select fw-semibold" id="maintenance_mode" name="maintenance_mode" required>
                                        <option value="0" <?= (($sys_settings['maintenance_mode'] ?? '0') === '0') ? 'selected' : '' ?> style="color: #10b981;">ðŸŸ¢ Live Operations (Normal Mode)</option>
                                        <option value="1" <?= (($sys_settings['maintenance_mode'] ?? '0') === '1') ? 'selected' : '' ?> style="color: #ef4444;">ðŸ”´ Maintenance Active (Admin Access Only)</option>
                                    </select>
                                </div>
                                
                                <div class="col-12 pt-3 border-top d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn btn-custom-primary px-5 py-2 shadow">
                                        <i class="fas fa-save me-2"></i> Save System Preferences
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
