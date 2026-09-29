<?php
$admin_name = $_SESSION['admin_name'] ?? 'Super Admin';
$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
?>
<!-- Main Content Area Wrapper -->
<main class="main-content">
    <!-- Top Glassmorphic Navigation Bar -->
    <nav class="navbar-top">
        <div class="navbar-left">
            <button id="toggleSidebar" class="btn-toggle-sidebar" title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <div class="search-box d-none d-md-block">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Quick search across hostels...">
            </div>
        </div>
        
        <div class="navbar-right">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-semibold d-none d-sm-inline-block">
                    <i class="fas fa-shield-halved me-1"></i> <?= htmlspecialchars($admin_role) ?>
                </span>
            </div>
            
            <div class="dropdown">
                <div class="user-profile-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="avatar-circle">
                        <?= strtoupper(substr($admin_name, 0, 2)) ?>
                    </div>
                    <div class="user-info d-none d-md-flex">
                        <span class="user-name"><?= htmlspecialchars($admin_name) ?></span>
                        <span class="user-role"><?= htmlspecialchars($admin_role) ?></span>
                    </div>
                    <i class="fas fa-chevron-down ms-1 text-secondary" style="font-size: 0.75rem;"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 mt-2 p-2" style="min-width: 220px;">
                    <li>
                        <div class="px-3 py-2 border-bottom mb-1">
                            <p class="mb-0 fw-bold"><?= htmlspecialchars($admin_name) ?></p>
                            <small class="text-muted"><?= htmlspecialchars($_SESSION['admin_email'] ?? 'admin@hostel.com') ?></small>
                        </div>
                    </li>
                    <li>
                        <a class="dropdown-item rounded-2 py-2" href="<?= BASE_URL ?>dashboard.php">
                            <i class="fas fa-layer-group text-primary me-2"></i> Dashboard Overview
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item rounded-2 py-2" href="<?= BASE_URL ?>hostel/hostel-list.php">
                            <i class="fas fa-hotel text-success me-2"></i> Manage Hostels
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item rounded-2 py-2 text-danger fw-semibold" href="<?= BASE_URL ?>logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Page Content Container -->
    <div class="page-content">
