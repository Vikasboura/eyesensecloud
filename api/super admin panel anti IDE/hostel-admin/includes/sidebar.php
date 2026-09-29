<?php
$current_script = basename($_SERVER['SCRIPT_NAME']);
$current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));

$is_hostel_active = ($current_dir === 'hostel' || in_array($current_script, ['hostel-list.php', 'add-hostel.php', 'edit-hostel.php', 'hostel-view.php']));
$is_rooms_active = ($current_dir === 'rooms' || in_array($current_script, ['room-list.php', 'add-room.php', 'edit-room.php']));
$is_students_active = ($current_dir === 'students' || in_array($current_script, ['student-list.php', 'add-student.php', 'edit-student.php', 'student-view.php']));
$is_wardens_active = ($current_dir === 'wardens' || in_array($current_script, ['warden-list.php', 'add-warden.php', 'edit-warden.php']));
$is_reports_active = ($current_dir === 'reports' || in_array($current_script, ['index.php']) && $current_dir === 'reports');
$is_settings_active = ($current_dir === 'settings' || in_array($current_script, ['index.php']) && $current_dir === 'settings');
?>
<!-- Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <a href="<?= BASE_URL ?>dashboard.php" class="sidebar-brand-link">
            <div class="sidebar-brand-icon">
                <i class="fas fa-building-user"></i>
            </div>
            <span class="sidebar-brand-text">Hostel Admin</span>
        </a>
    </div>
    
    <div class="sidebar-menu">
        <div class="sidebar-category">Core Menu</div>
        
        <div class="nav-item">
            <a href="<?= BASE_URL ?>dashboard.php" class="nav-link-custom <?= ($current_script === 'dashboard.php') ? 'active' : '' ?>">
                <div class="nav-link-icon">
                    <i class="fas fa-layer-group"></i>
                    <span class="nav-link-text">Dashboard</span>
                </div>
            </a>
        </div>
        
        <div class="sidebar-category">Hostel Operations</div>
        
        <div class="nav-item">
            <a href="#" class="nav-link-custom has-submenu <?= $is_hostel_active ? 'active' : '' ?>" data-bs-target="#submenu-hostel">
                <div class="nav-link-icon">
                    <i class="fas fa-hotel"></i>
                    <span class="nav-link-text">Hostel Management</span>
                </div>
                <i class="fas fa-chevron-down submenu-arrow <?= $is_hostel_active ? 'rotate-180' : '' ?>" style="font-size: 0.75rem; transition: transform 0.3s;"></i>
            </a>
            <ul class="sidebar-sub-menu <?= $is_hostel_active ? 'show' : '' ?>" id="submenu-hostel">
                <li>
                    <a href="<?= BASE_URL ?>hostel/hostel-list.php" class="<?= ($current_script === 'hostel-list.php' || $current_script === 'edit-hostel.php' || $current_script === 'hostel-view.php') ? 'active' : '' ?>">
                        <i class="fas fa-list-ul me-2"></i> Hostel List
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>hostel/add-hostel.php" class="<?= ($current_script === 'add-hostel.php') ? 'active' : '' ?>">
                        <i class="fas fa-plus-circle me-2"></i> Add Hostel
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="nav-item">
            <a href="#" class="nav-link-custom has-submenu <?= $is_rooms_active ? 'active' : '' ?>" data-bs-target="#submenu-rooms">
                <div class="nav-link-icon">
                    <i class="fas fa-door-open"></i>
                    <span class="nav-link-text">Rooms</span>
                </div>
                <i class="fas fa-chevron-down submenu-arrow <?= $is_rooms_active ? 'rotate-180' : '' ?>" style="font-size: 0.75rem; transition: transform 0.3s;"></i>
            </a>
            <ul class="sidebar-sub-menu <?= $is_rooms_active ? 'show' : '' ?>" id="submenu-rooms">
                <li>
                    <a href="<?= BASE_URL ?>rooms/room-list.php" class="<?= ($current_script === 'room-list.php' || $current_script === 'edit-room.php') ? 'active' : '' ?>">
                        <i class="fas fa-list-ol me-2"></i> Rooms List
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>rooms/add-room.php" class="<?= ($current_script === 'add-room.php') ? 'active' : '' ?>">
                        <i class="fas fa-plus me-2"></i> Add Room
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="nav-item">
            <a href="#" class="nav-link-custom has-submenu <?= $is_students_active ? 'active' : '' ?>" data-bs-target="#submenu-students">
                <div class="nav-link-icon">
                    <i class="fas fa-user-graduate"></i>
                    <span class="nav-link-text">Students</span>
                </div>
                <i class="fas fa-chevron-down submenu-arrow <?= $is_students_active ? 'rotate-180' : '' ?>" style="font-size: 0.75rem; transition: transform 0.3s;"></i>
            </a>
            <ul class="sidebar-sub-menu <?= $is_students_active ? 'show' : '' ?>" id="submenu-students">
                <li>
                    <a href="<?= BASE_URL ?>students/student-list.php" class="<?= ($current_script === 'student-list.php' || $current_script === 'edit-student.php' || $current_script === 'student-view.php') ? 'active' : '' ?>">
                        <i class="fas fa-users me-2"></i> Students Directory
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>students/add-student.php" class="<?= ($current_script === 'add-student.php') ? 'active' : '' ?>">
                        <i class="fas fa-user-plus me-2"></i> New Admission
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="nav-item">
            <a href="#" class="nav-link-custom has-submenu <?= $is_wardens_active ? 'active' : '' ?>" data-bs-target="#submenu-wardens">
                <div class="nav-link-icon">
                    <i class="fas fa-user-tie"></i>
                    <span class="nav-link-text">Wardens</span>
                </div>
                <i class="fas fa-chevron-down submenu-arrow <?= $is_wardens_active ? 'rotate-180' : '' ?>" style="font-size: 0.75rem; transition: transform 0.3s;"></i>
            </a>
            <ul class="sidebar-sub-menu <?= $is_wardens_active ? 'show' : '' ?>" id="submenu-wardens">
                <li>
                    <a href="<?= BASE_URL ?>wardens/warden-list.php" class="<?= ($current_script === 'warden-list.php' || $current_script === 'edit-warden.php') ? 'active' : '' ?>">
                        <i class="fas fa-id-badge me-2"></i> Wardens List
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>wardens/add-warden.php" class="<?= ($current_script === 'add-warden.php') ? 'active' : '' ?>">
                        <i class="fas fa-user-check me-2"></i> Assign Warden
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-category">Analytics & System</div>
        
        <div class="nav-item">
            <a href="<?= BASE_URL ?>reports/index.php" class="nav-link-custom <?= $is_reports_active ? 'active' : '' ?>">
                <div class="nav-link-icon">
                    <i class="fas fa-chart-pie"></i>
                    <span class="nav-link-text">Reports</span>
                </div>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?= BASE_URL ?>settings/index.php" class="nav-link-custom <?= $is_settings_active ? 'active' : '' ?>">
                <div class="nav-link-icon">
                    <i class="fas fa-sliders"></i>
                    <span class="nav-link-text">Settings</span>
                </div>
            </a>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-circle" style="width: 32px; height: 32px; font-size: 0.8rem;">
                    SA
                </div>
                <div class="user-info">
                    <span class="user-name" style="color: white; font-size: 0.8rem;">Super Admin</span>
                    <span class="user-role" style="color: #64748b; font-size: 0.7rem;">Verified Role</span>
                </div>
            </div>
            <a href="<?= BASE_URL ?>logout.php" class="text-danger" title="Logout" data-bs-toggle="tooltip">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>
