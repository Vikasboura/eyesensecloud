<?php
/** EyeSense Cloud Portal — Dashboard (v2 — mirrored tables) */

// ── Pre-header redirect for member-only users ──
// Must run BEFORE portal_header.php (which outputs HTML)
require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
requirePortalLogin();

$pdo = get_indsac_db();
$session = getPortalSession();
$_perms = getAllPermissions($pdo, $session['client_id'], $session['role']);
$_isSA = !empty($session['is_superadmin']);
$_isMemberOnly = !$_isSA
    && empty($_perms['VIEW_SNAPSHOTS']) && empty($_perms['VIEW_VIDEOS'])
    && empty($_perms['VIEW_LOGS']) && empty($_perms['MANAGE_USERS'])
    && empty($_perms['MANAGE_ROLES']) && empty($_perms['DELETE_RECORDS'])
    && empty($_perms['DOWNLOAD_SQL']) && empty($_perms['MANAGE_MAINTENANCE']);

if ($_isMemberOnly) {
    if (strtolower($session['role'] ?? '') === 'tenant') {
        header('Location: tenant_dashboard.php');
    } elseif ($session['role'] === 'Registered User' || $session['client_id'] === 'PUBLIC') {
        header('Location: edit_profile.php');
    } else {
        header('Location: my_dues.php');
    }
    exit;
}

// Now safe to include header (outputs HTML)
require_once __DIR__ . '/portal_header.php';
$stats = [];
try {
    $cid = $pdo->quote($session['client_id']);
    $societyId = SocietyContextHolder::getCurrentSocietyId() ?: (int)($activeSocietyId ?? 0);
    $sid = (int)$societyId;

    // Count from mirrored tables (detection & camera tables scoped by society_id)
    $stats['snapshots'] = $sid > 0 ? (int)$pdo->query("SELECT COUNT(*) FROM snapshot_logs WHERE society_id=$sid")->fetchColumn() : 0;
    $stats['videos']    = $sid > 0 ? (int)$pdo->query("SELECT COUNT(*) FROM video_logs WHERE society_id=$sid")->fetchColumn() : 0;
    $stats['face_events'] = $sid > 0 ? (int)$pdo->query("SELECT COUNT(*) FROM face_logs WHERE society_id=$sid")->fetchColumn() : 0;
    $stats['vehicle_events'] = $sid > 0 ? (int)$pdo->query("SELECT COUNT(*) FROM vehicle_logs WHERE society_id=$sid")->fetchColumn() : 0;
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM employees WHERE client_id=$cid AND status='active'")->fetchColumn();

    // Total events (face + vehicle + access_requests)
    $stats['total_events'] = $stats['face_events'] + $stats['vehicle_events']
        + $pdo->query("SELECT COUNT(*) FROM access_requests WHERE client_id=$cid")->fetchColumn();

    // Last sync time from system_settings mirror
    $lastSync = $pdo->query("SELECT setting_value FROM system_settings WHERE client_id={$cid} AND setting_key='indsac_last_sync'")->fetchColumn() ?: null;

    // Recent activity (face_logs + vehicle_logs combined, last 10, scoped to active society)
    $recentActivity = [];
    if ($sid > 0) {
        $recentStmt = $pdo->query("
            (SELECT 'face' as type, person_name as label, camera_id, timestamp FROM face_logs WHERE society_id=$sid ORDER BY timestamp DESC LIMIT 5)
            UNION ALL
            (SELECT 'vehicle' as type, plate_number as label, camera_id, timestamp FROM vehicle_logs WHERE society_id=$sid ORDER BY timestamp DESC LIMIT 5)
            ORDER BY timestamp DESC LIMIT 10
        ");
        $recentActivity = $recentStmt ? $recentStmt->fetchAll() : [];
    }

} catch (Exception $e) {
    $stats = ['snapshots'=>0, 'videos'=>0, 'face_events'=>0, 'vehicle_events'=>0, 'users'=>0, 'total_events'=>0];
    $lastSync = null; $recentActivity = [];
}
?>

<?php
$socLogoSrc = '';
if (!empty($societyBranding['logo_path'])) {
    $fsPath = __DIR__ . '/../' . ltrim($societyBranding['logo_path'], '/\\');
    if (file_exists($fsPath)) {
        $socLogoSrc = '../' . htmlspecialchars($societyBranding['logo_path']);
    }
}
?>

<!-- TOPBAR -->
<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex items-center justify-between px-4 md:px-6 py-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0 flex items-center gap-3">
        <?php if (!empty($socLogoSrc)): ?>
            <img src="<?= $socLogoSrc ?>" alt="<?= htmlspecialchars($currentSocietyName) ?> Logo" class="h-10 w-10 object-contain rounded-xl shrink-0 border border-border/60 p-1 bg-white/5 shadow-sm">
        <?php endif; ?>
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h1 class="font-bold text-base md:text-lg text-textMain truncate">Dashboard</h1>
                <?php if ($currentSocietyName): ?>
                    <span class="text-xs text-textSec font-normal ml-1 hidden md:inline">Welcome to</span>
                    <span class="badge text-[11px] font-semibold hidden md:inline-flex" style="background:rgba(99,102,241,0.12);color:var(--society-theme-color,#6366f1);border:1px solid rgba(99,102,241,0.25);">
                        <?= htmlspecialchars($currentSocietyName) ?>
                    </span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-textSec hidden sm:block">Cloud backup overview</p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
        <div class="text-right hidden sm:block">
            <div class="text-xs text-textSec">Last sync</div>
            <div class="text-xs font-mono text-green-400"><?= $lastSync ? date('d M H:i', strtotime($lastSync)) : '--:--' ?></div>
        </div>
        <div class="w-2 h-2 rounded-full <?= $lastSync ? 'bg-green-400 animate-pulse' : 'bg-gray-600' ?>"></div>
    </div>
</div>

<div class="flex-1 overflow-y-auto p-6">
    <!-- Stat Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Snapshots</div>
            <div class="text-3xl font-bold text-textMain"><?= number_format($stats['snapshots']) ?></div><div class="text-xs text-textSec mt-1">backed up</div></div>
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Video Clips</div>
            <div class="text-3xl font-bold text-purple-400"><?= number_format($stats['videos']) ?></div><div class="text-xs text-textSec mt-1">backed up</div></div>
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Face Events</div>
            <div class="text-3xl font-bold text-cyan-400"><?= number_format($stats['face_events']) ?></div><div class="text-xs text-textSec mt-1">total detections</div></div>
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Vehicle Events</div>
            <div class="text-3xl font-bold text-yellow-400"><?= number_format($stats['vehicle_events']) ?></div><div class="text-xs text-textSec mt-1">total detections</div></div>
    </div>

    <!-- Panels -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="glass-panel rounded-xl p-5">
            <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-4"><i class="fas fa-clock-rotate-left text-primary mr-2"></i>Recent Activity</h3>
            <div class="space-y-2 text-sm text-textSec">
                <?php if (empty($recentActivity)): ?>
                    <div class="text-center text-textSec py-4">No activity yet</div>
                <?php else: foreach ($recentActivity as $a):
                    $icon = $a['type'] === 'face' ? 'fa-user text-cyan-400' : 'fa-car text-yellow-400';
                ?>
                    <div class="flex items-center justify-between py-2 border-b border-border last:border-0">
                        <div class="flex items-center gap-2">
                            <i class="fas <?= $icon ?> text-xs w-4"></i>
                            <span class="text-textSec text-sm"><?= htmlspecialchars($a['label'] ?? 'Unknown') ?></span>
                            <span class="text-textSec text-xs"><?= htmlspecialchars($a['camera_id'] ?? '') ?></span>
                        </div>
                        <span class="font-mono text-xs text-textSec"><?= $a['timestamp'] ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="glass-panel rounded-xl p-5">
            <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-4"><i class="fas fa-circle-info text-blue-400 mr-2"></i>Client Info</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-textSec">Client ID</span><span class="font-mono text-primary text-xs"><?= htmlspecialchars($session['client_id']) ?></span></div>
                <div class="flex justify-between"><span class="text-textSec">Portal Users</span><span class="text-textMain"><?= $stats['users'] ?> staff</span></div>
                <div class="flex justify-between"><span class="text-textSec">Total Events</span><span class="text-textMain"><?= number_format($stats['total_events']) ?></span></div>
                <div class="flex justify-between"><span class="text-textSec">Status</span><span class="badge" style="background:rgba(16,185,129,0.15);color:#34d399;">Active</span></div>
            </div>
        </div>
    </div>

    <?php if ($_isSA): ?>
    <!-- ── SA Quick Tools ── -->
    <div class="mt-6">
        <div class="flex items-center gap-2 mb-3">
            <h3 class="text-sm font-bold text-textSec uppercase tracking-wider"><i class="fas fa-toolbox text-primary mr-2"></i>Quick Tools</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Client Map Entry -->
            <a href="client.php?client_id=<?= urlencode($session['client_id']) ?>" target="_blank"
               class="glass-panel rounded-xl p-5 flex items-center gap-4 hover:border-cyan-500/40 transition-all group no-underline">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background:rgba(0,180,216,0.15);border:1px solid rgba(0,224,255,0.2);">
                    <i class="fas fa-map-pin text-cyan-400 text-xl"></i>
                </div>
                <div class="min-w-0">
                    <div class="font-bold text-textMain text-sm group-hover:text-cyan-300 transition">Client Map Entry</div>
                    <div class="text-xs text-textSec mt-0.5">Register client plot + GPS location</div>
                </div>
                <i class="fas fa-arrow-right text-textSec group-hover:text-cyan-400 ml-auto transition"></i>
            </a>

            <!-- Visitor Plot Locator -->
            <a href="visitor.php" target="_blank"
               class="glass-panel rounded-xl p-5 flex items-center gap-4 hover:border-emerald-500/40 transition-all group no-underline">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.2);">
                    <i class="fas fa-search-location text-emerald-400 text-xl"></i>
                </div>
                <div class="min-w-0">
                    <div class="font-bold text-textMain text-sm group-hover:text-emerald-300 transition">Visitor Plot Locator</div>
                    <div class="text-xs text-textSec mt-0.5">Find any plot by number — share map link</div>
                </div>
                <i class="fas fa-arrow-right text-textSec group-hover:text-emerald-400 ml-auto transition"></i>
            </a>

            <!-- Share Login Link (SA only) -->
            <?php
            $loginRef    = generate_login_ref($session['client_id']);
            $loginRefUrl = rtrim(DB_CFG_PORTAL_BASE_URL, '/') . '/login.php?ref=' . $loginRef;
            $loginRefDisplay = rtrim(DB_CFG_PORTAL_BASE_URL, '/') . '/login.php?ref=' . substr($loginRef, 0, 18) . '…';
            ?>
            <button type="button"
                id="dashShareLoginBtn"
                data-loginrefurl="<?= htmlspecialchars($loginRefUrl, ENT_QUOTES) ?>"
                onclick="dashCopyLoginRef()"
                class="glass-panel rounded-xl p-5 flex items-center gap-4 hover:border-purple-500/40 transition-all group
                       text-left cursor-pointer border-none w-full">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background:rgba(139,92,246,0.15);border:1px solid rgba(139,92,246,0.25);">
                    <i class="fas fa-user-plus text-purple-400 text-xl"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div id="dashShareLoginLabel" class="font-bold text-textMain text-sm group-hover:text-purple-300 transition">Share Login Link</div>
                    <div class="text-xs text-textSec mt-0.5">Pre-filled login URL for your members</div>
                    <div class="mt-1.5 font-mono text-[10px] text-textSec truncate max-w-[220px]"
                         title="<?= htmlspecialchars($loginRefUrl, ENT_QUOTES) ?>">
                        <?= htmlspecialchars($loginRefDisplay) ?>
                    </div>
                </div>
                <i id="dashShareLoginIcon" class="fas fa-copy text-textSec group-hover:text-purple-400 ml-auto transition flex-shrink-0"></i>
            </button>
            <script>
            function dashCopyLoginRef() {
                var btn  = document.getElementById('dashShareLoginBtn');
                var url  = btn.getAttribute('data-loginrefurl');
                var lbl  = document.getElementById('dashShareLoginLabel');
                var icon = document.getElementById('dashShareLoginIcon');
                navigator.clipboard.writeText(url).then(function() {
                    lbl.textContent  = 'Link Copied!';
                    lbl.style.color  = '#a78bfa';
                    icon.className   = 'fas fa-check text-purple-400 ml-auto transition flex-shrink-0';
                    setTimeout(function() {
                        lbl.textContent = 'Share Login Link';
                        lbl.style.color = '';
                        icon.className  = 'fas fa-copy text-textSec group-hover:text-purple-400 ml-auto transition flex-shrink-0';
                    }, 2500);
                }).catch(function() { prompt('Copy this link:', url); });
            }
            </script>

        </div><!-- /quick-tools grid -->
    </div><!-- /SA Quick Tools -->
    <?php endif; ?>


    <?php
    // ── Member Quick Actions (visible to members only) ──
    $empId = $_SESSION['portal_employee_id'] ?? '';
    if (!$_isSA):
        // Fetch plotno from employees table (set via Edit Profile)
        $mbrEmp = $pdo->prepare("SELECT plotno FROM employees WHERE client_id=? AND employee_id=? AND is_deleted=0 LIMIT 1");
        $mbrEmp->execute([$session['client_id'], $empId]);
        $mbrPlot = $mbrEmp->fetchColumn() ?: '';

        // Build share URL from configurable base (set in db.php)
        $mbrShareUrl = $mbrPlot
            ? rtrim(DB_CFG_PORTAL_BASE_URL, '/') . '/visitor.php?plotno=' . urlencode($mbrPlot) . '&cid=' . md5($session['client_id'])
            : '';
    ?>

    <div class="mt-6">
        <div class="flex items-center gap-2 mb-3">
            <h3 class="text-sm font-bold text-textSec uppercase tracking-wider"><i class="fas fa-bolt text-yellow-400 mr-2"></i>Quick Actions</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <!-- Share My Location -->
            <div class="glass-panel rounded-xl p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                         style="background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.25);">
                        <i class="fas fa-share-nodes text-primary text-lg"></i>
                    </div>
                    <div>
                        <div class="font-bold text-textMain text-sm">Share My Location</div>
                        <div class="text-xs text-textSec">Copy your plot locator link</div>
                    </div>
                </div>
                <?php if ($mbrShareUrl): ?>
                <div class="bg-gray-200/50 rounded-lg px-3 py-2 flex items-center gap-2 mb-3">
                    <i class="fas fa-map-pin text-primary text-xs"></i>
                    <span class="font-mono text-xs text-textSec flex-1 truncate" id="shareLocUrl">
                        <?= htmlspecialchars($mbrShareUrl) ?>
                    </span>
                </div>
                <button onclick="copyShareLink()" id="shareLocBtn"
                    class="btn-primary w-full text-center text-sm flex items-center justify-center gap-2">
                    <i class="fas fa-copy"></i> Copy Location Link
                </button>
                <script>
                function copyShareLink() {
                    const url = document.getElementById('shareLocUrl').textContent.trim();
                    navigator.clipboard.writeText(url).then(function() {
                        const btn = document.getElementById('shareLocBtn');
                        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                        setTimeout(function() { btn.innerHTML = '<i class="fas fa-copy"></i> Copy Location Link'; }, 2000);
                    }).catch(function() { prompt('Copy this link:', url); });
                }
                </script>
                <?php else: ?>
                <p class="text-xs text-textSec">Complete your profile to get a shareable location link.</p>
                <a href="edit_profile.php" class="btn-primary mt-3 inline-block text-sm text-center">
                    <i class="fas fa-user-pen mr-1"></i> Complete Profile
                </a>
                <?php endif; ?>
            </div>

            <!-- Edit / Complete Profile -->
            <a href="edit_profile.php?client_id=<?= urlencode($session['client_id']) ?>"
               class="glass-panel rounded-xl p-5 flex items-center gap-4 hover:border-yellow-500/40 transition-all group no-underline">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.2);">
                    <i class="fas fa-user-pen text-yellow-400 text-xl"></i>
                </div>
                <div class="min-w-0">
                    <div class="font-bold text-textMain text-sm group-hover:text-yellow-300 transition">Edit / Complete Profile</div>
                    <div class="text-xs text-textSec mt-0.5">Update your plot, address &amp; GPS location</div>
                </div>
                <i class="fas fa-chevron-right text-textSec group-hover:text-yellow-400 ml-auto transition"></i>
            </a>

        </div>
    </div>
    <?php endif; ?>

    <?php
    // ── MAINTENANCE SECTION — role-based ──
    $hasManage = $isSA || !empty($permissions['MANAGE_MAINTENANCE']);
    $hasView   = !empty($permissions['VIEW_MAINTENANCE']);
    ?>

    <?php if ($hasManage): ?>
    <!-- Admin Maintenance Dashboard Section -->
    <div class="mt-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-textSec uppercase tracking-wider">
                <i class="fas fa-building-columns text-emerald-400 mr-2"></i>Maintenance — <?= date('F Y') ?>
            </h3>
            <a href="maintenance.php" class="text-xs text-primary hover:underline">View All →</a>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4" id="maint-stats">
            <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Verified</div><div class="text-3xl font-bold text-emerald-400" id="md-verified">-</div></div>
            <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Awaiting Review</div><div class="text-3xl font-bold text-yellow-400" id="md-uploaded">-</div></div>
            <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Pending</div><div class="text-3xl font-bold text-textSec" id="md-pending">-</div></div>
            <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Overdue</div><div class="text-3xl font-bold text-red-400" id="md-overdue">-</div></div>
        </div>

        <!-- Recent Uploads mini-table -->
        <div class="glass-panel rounded-xl p-5">
            <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-4"><i class="fas fa-clock-rotate-left text-yellow-400 mr-2"></i>Recent Receipt Uploads</h3>
            <div class="space-y-2 text-sm text-textSec" id="md-recent">
                <div class="text-center text-textSec py-4">Loading...</div>
            </div>
        </div>
    </div>
    <script>
    (async function() {
        try {
            const month = '<?= date('Y-m-01') ?>';
            const [sRes, uRes] = await Promise.all([
                fetch('../maintenance_review_api.php?action=get_stats&month=' + month),
                fetch('../maintenance_review_api.php?action=recent_uploads&limit=5')
            ]);
            const stats = await sRes.json();
            const uploads = await uRes.json();

            document.getElementById('md-verified').textContent = stats.verified ?? 0;
            document.getElementById('md-uploaded').textContent = stats.uploaded ?? 0;
            document.getElementById('md-pending').textContent = stats.pending ?? 0;
            document.getElementById('md-overdue').textContent = stats.overdue ?? 0;

            const recentDiv = document.getElementById('md-recent');
            const items = uploads.uploads || [];
            if (!items.length) {
                recentDiv.innerHTML = '<div class="text-center text-textSec py-4">No receipts uploaded yet</div>';
            } else {
                recentDiv.innerHTML = items.map(u => {
                    const statusMap = { PENDING:'text-yellow-400', VERIFIED:'text-emerald-400', REJECTED:'text-red-400' };
                    const sc = statusMap[u.review_status] || 'text-textSec';
                    const ago = new Date(u.uploaded_at).toLocaleDateString('en-IN', {day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});
                    return `<div class="flex items-center justify-between py-2 border-b border-border last:border-0">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-receipt text-emerald-400 text-xs w-4"></i>
                            <span class="text-textSec text-sm">${u.full_name}</span>
                            <span class="text-textSec text-xs">Flat ${u.flat_number}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-bold text-xs ${sc}">₹${parseFloat(u.amount_paid).toLocaleString()}</span>
                            <span class="font-mono text-xs text-textSec">${ago}</span>
                            <a href="maintenance.php" class="text-xs text-primary hover:underline">Review</a>
                        </div>
                    </div>`;
                }).join('');
            }
        } catch(e) {
            document.getElementById('md-recent').innerHTML = '<div class="text-center text-textSec py-4">Error loading data</div>';
        }
    })();
    </script>

    <?php elseif ($hasView): ?>
    <!-- Member Maintenance Section -->
    <?php
    $empId = $_SESSION['portal_employee_id'] ?? '';
    $myMember = $pdo->prepare("SELECT * FROM society_members WHERE client_id = ? AND employee_id = ? AND status = 'active'");
    $myMember->execute([$session['client_id'], $empId]);
    $mbrRow = $myMember->fetch();
    if ($mbrRow):
        $curBill = $pdo->prepare("SELECT * FROM maintenance_bills WHERE client_id = ? AND member_id = ? AND billing_month = ?");
        $curBill->execute([$session['client_id'], $mbrRow['id'], date('Y-m-01')]);
        $myBill = $curBill->fetch();
    ?>
    <div class="mt-6">
        <div class="glass-panel rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-textSec uppercase tracking-wider">
                    <i class="fas fa-building text-emerald-400 mr-2"></i>Your Maintenance — <?= date('F Y') ?>
                </h3>
                <a href="my_dues.php" class="text-xs text-primary hover:underline">View Details →</a>
            </div>
            <?php if ($myBill): ?>
            <div class="grid grid-cols-3 gap-4">
                <div><div class="text-xs text-textSec mb-1">Total Due</div><div class="text-xl font-bold text-primary">₹<?= number_format($myBill['total_due'], 2) ?></div></div>
                <div><div class="text-xs text-textSec mb-1">Due Date</div><div class="text-xl font-bold text-textMain"><?= date('d M', strtotime($myBill['due_date'])) ?></div></div>
                <div><div class="text-xs text-textSec mb-1">Status</div>
                    <?php
                    $sColors = ['PENDING'=>'text-textSec','UPLOADED'=>'text-yellow-400','VERIFIED'=>'text-emerald-400','REJECTED'=>'text-red-400','OVERDUE'=>'text-red-400'];
                    $sc = $sColors[$myBill['status']] ?? 'text-textSec';
                    ?>
                    <div class="text-xl font-bold <?= $sc ?>"><?= $myBill['status'] ?></div>
                </div>
            </div>
            <?php if (in_array($myBill['status'], ['PENDING', 'OVERDUE', 'REJECTED'])): ?>
            <a href="my_dues.php?upload=1" class="btn-primary bg-emerald-600 hover:bg-emerald-500 mt-4 inline-block text-xs"><i class="fas fa-cloud-arrow-up mr-2"></i>Upload Receipt</a>
            <?php endif; ?>
            <?php else: ?>
            <p class="text-sm text-textSec">No bill generated for this month yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; // $mbrRow ?>
    <?php endif; // hasManage/hasView ?>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
