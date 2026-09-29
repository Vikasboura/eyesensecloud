<?php
/**
 * EyeSense Cloud Portal — Hostel Admin Wrapper
 * Embeds the standalone Hostel Admin Panel within the Portal layout.
 */
session_start();
$_SESSION['hostel_embedded'] = true;

$path = $_GET['path'] ?? 'dashboard.php';
// Prevent basic directory traversal
$path = str_replace(['../', '..\\'], '', $path);

// We need to tell portal_header.php that we are in the hostel management section
$currentPage = 'hostel_management_wrapper';
$subPage = basename($path);
$isHostelOpen = true;

require_once __DIR__ . '/portal_header.php';
?>

<?php if (empty($_SESSION['hostel_management_enabled'])): ?>
<div class="flex-1 overflow-y-auto bg-bg">
    <div class="portal-main-content p-6 max-w-7xl mx-auto space-y-6">
        <div class="portal-topbar flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-textMain tracking-tight">Hostel Management</h1>
                <p class="text-sm text-textSec mt-1">Manage your hostel, rooms, students, and more</p>
            </div>
        </div>
        
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md shadow-sm">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Access Denied</h3>
                    <div class="mt-1 text-sm text-red-700">
                        <p>Hostel Management is currently disabled for your society. Please enable it in the society settings or contact your administrator.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Full width/height iframe wrapper -->
<div class="w-full h-screen overflow-hidden flex flex-col" style="flex: 1;">
    <iframe src="../super admin panel anti IDE/hostel-admin/<?= htmlspecialchars($path, ENT_QUOTES) ?>?embedded=1" 
            style="width: 100%; height: 100%; border: none; flex: 1;"
            name="hostel_frame">
    </iframe>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
