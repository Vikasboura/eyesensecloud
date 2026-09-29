<?php
/**
 * EyeSense Cloud Portal — Shared Header & Sidebar
 * Include at the top of every portal page after requirePortalLogin().
 */
require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
requirePortalLogin();

if (!DB_CFG_MULTI_SOCIETY_LOGIN_ENABLED && isset($_GET['switch_client_id'])) {
    $targetClientId = trim($_GET['switch_client_id']);
    if (!empty($_SESSION['portal_available_societies'])) {
        foreach ($_SESSION['portal_available_societies'] as $soc) {
            if ($soc['client_id'] === $targetClientId) {
                // Switch active session variables
                $_SESSION['portal_client_id']     = $soc['client_id'];
                $_SESSION['portal_user_id']       = $soc['user_id'];
                $_SESSION['portal_employee_id']   = $soc['employee_id'];
                $_SESSION['portal_username']      = $soc['full_name'];
                $_SESSION['portal_role']          = $soc['role'];
                $_SESSION['portal_is_superadmin'] = $soc['is_superadmin'];
                $_SESSION['portal_cameras']       = [];
                if ($soc['type'] === 'tenant') {
                    $_SESSION['portal_tenant_id'] = $soc['tenant_id'];
                    $_SESSION['portal_member_id'] = $soc['member_id'];
                } else {
                    unset($_SESSION['portal_tenant_id']);
                    unset($_SESSION['portal_member_id']);
                }
                
                // Redirect to dashboard.php to route correctly
                header("Location: dashboard.php");
                exit;
            }
        }
    }
}

$pdo = get_indsac_db();
$session = getPortalSession();

// Real-time sync for Hostel Management Permission
try {
    $hmStmt = $pdo->prepare("SELECT hostel_management_enabled FROM clients WHERE client_id = ?");
    $hmStmt->execute([$session['client_id']]);
    $_SESSION['hostel_management_enabled'] = (int)$hmStmt->fetchColumn();
} catch (Throwable $e) {
    $_SESSION['hostel_management_enabled'] = 0;
}

$permissions = getAllPermissions($pdo, $session['client_id'], $session['role']);
$isSA = !empty($session['is_superadmin']);
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Real-time synchronization of available societies for multi-society users
if (DB_CFG_MULTI_SOCIETY_LOGIN_ENABLED && !empty($session['user_id'])) {
    try {
        $gStmt = $pdo->prepare("SELECT global_user_id FROM employees WHERE id = ? LIMIT 1");
        $gStmt->execute([(int)$session['user_id']]);
        $gUserId = (int)$gStmt->fetchColumn();
        if ($gUserId > 0) {
            portal_refresh_available_societies($pdo, $gUserId);
        }
    } catch (Throwable $e) {}
}

// Fetch the current society name
$currentSocietyName = '';
if (!empty($_SESSION['portal_available_societies'])) {
    foreach ($_SESSION['portal_available_societies'] as $soc) {
        if ($soc['client_id'] === $session['client_id']) {
            $currentSocietyName = $soc['society_name'];
            break;
        }
    }
}
if (empty($currentSocietyName)) {
    $currentSocietyName = get_society_name_by_client_id($pdo, $session['client_id']);
}

// ── Auto-billing: generate monthly bills + mark overdue once per day ──
require_once __DIR__ . '/../maintenance_cron.php';
runMaintenanceCron($pdo, $session['client_id']);
portalTouchLoginSession($pdo);

// ── Server-side Society Branding (Theme Color & Logo) ──
require_once __DIR__ . '/../society_context_holder.php';
SocietyContextHolder::initFromRequest($pdo);
$activeSocietyId = SocietyContextHolder::getCurrentSocietyId();
$societyBranding = [
    'logo_path' => null,
    'theme_color' => null
];
if ($activeSocietyId && $activeSocietyId > 0) {
    try {
        $bStmt = $pdo->prepare("SELECT logo_path, theme_color FROM society_settings WHERE society_id = ? LIMIT 1");
        $bStmt->execute([(int)$activeSocietyId]);
        $bRow = $bStmt->fetch(PDO::FETCH_ASSOC);
        if ($bRow) {
            $societyBranding['logo_path'] = $bRow['logo_path'] ?: null;
            $societyBranding['theme_color'] = $bRow['theme_color'] ?: null;
        }
    } catch (Throwable $e) {}
}
$customThemeColor = $societyBranding['theme_color'] ?: '#6366f1';

// ── Read and immediately clear Society Switch Notification Toast flag ──
$societyNotice = $_SESSION['portal_society_notice'] ?? null;
if ($societyNotice !== null) {
    unset($_SESSION['portal_society_notice']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EyeSense Cloud Portal | <?= ucfirst($currentPage) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="api/responsive.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { bg: 'var(--bg)', surface: 'var(--surface)', surfaceLight: 'var(--surfaceLight)', primary: 'var(--primary, #6366f1)', accent: '#a855f7', success: '#10b981', warning: '#f59e0b', danger: '#ef4444', textMain: 'var(--textMain)', textSec: 'var(--textSec)', border: 'var(--border)' },
                    fontFamily: { sans: ['Outfit', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>

        :root {
            --bg: #f9fafb;
            --surface: #ffffff;
            --surfaceLight: #f3f4f6;
            --textMain: #111827;
            --textSec: #6b7280;
            --border: #d1d5db;
            --primary: #6366f1;
            --society-theme-color: <?= htmlspecialchars($customThemeColor) ?>;
        }
        [data-theme="dark"] {
            --bg: #0a0a0a;
            --surface: #111827;
            --surfaceLight: #1f2937;
            --textMain: #f3f4f6;
            --textSec: #9ca3af;
            --border: #374151;
            --glassBg: rgba(31, 41, 55, 0.7);
            --glassBorder: rgba(255, 255, 255, 0.08);
        }
        body {
            background: var(--bg);
            color: var(--textMain);
            font-family: 'Outfit', sans-serif;
        }

        .glass-panel {
            background: var(--glassBg, rgba(255, 255, 255, 0.9));
            backdrop-filter: blur(12px);
            border: 1px solid var(--glassBorder, rgba(0, 0, 0, 0.08));
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05);
        }

        .nav-item {
            opacity: 0.7;
            transition: all 0.2s;
            color: var(--textSec);
        }

        .nav-item:hover {
            opacity: 1;
            background: rgba(0, 0, 0, 0.03);
            color: var(--textMain);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
            border-left: 3px solid var(--primary);
            color: var(--textMain);
            opacity: 1;
            font-weight: 600;
        }

        .nav-section {
            border-top: 1px solid #e5e7eb;
            padding-top: 0.75rem;
            margin-top: 0.75rem;
        }

        .nav-section:first-child {
            border-top: 0;
            padding-top: 0;
            margin-top: 0;
        }

        .nav-section-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.25rem 0.75rem;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--textSec);
            background: transparent;
            border: none;
            cursor: pointer;
        }

        .nav-section-toggle:hover {
            color: #9ca3af;
        }

        .nav-section-sign {
            width: 1rem;
            text-align: center;
            font-size: 14px;
            line-height: 1;
            color: #9ca3af;
        }

        .nav-section-content {
            margin-top: 0.25rem;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg);
        }

        ::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 4px;
        }

        .toggle-switch {
            position: relative;
            width: 40px;
            height: 22px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #d1d5db;
            border-radius: 22px;
            transition: 0.3s;
        }

        .toggle-slider:before {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
        }

        input:checked+.toggle-slider {
            background: #6366f1;
        }

        input:checked+.toggle-slider:before {
            transform: translateX(18px);
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-role {
            background: rgba(99, 102, 241, 0.12);
            color: #4f46e5;
            border: 1px solid rgba(99, 102, 241, 0.25);
        }
        [data-theme="dark"] .badge-role {
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .btn-add-society {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.35);
        }
        .btn-add-society:hover {
            background: rgba(16, 185, 129, 0.18);
            color: #065f46;
        }
        [data-theme="dark"] .btn-add-society {
            background: rgba(16, 185, 129, 0.12);
            color: #6ee7b7;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        [data-theme="dark"] .btn-add-society:hover {
            background: rgba(16, 185, 129, 0.22);
            color: #a7f3d0;
        }

        .btn-view-switch {
            background: rgba(99, 102, 241, 0.08);
            color: #4338ca;
            border: 1px solid rgba(99, 102, 241, 0.25);
        }
        .btn-view-switch:hover {
            background: rgba(99, 102, 241, 0.15);
            color: #3730a3;
        }
        [data-theme="dark"] .btn-view-switch {
            background: rgba(99, 102, 241, 0.12);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        [data-theme="dark"] .btn-view-switch:hover {
            background: rgba(99, 102, 241, 0.22);
            color: #c7d2fe;
        }

        input,
        select,
        textarea {
            background: var(--surface) !important;
            border: 1px solid var(--border) !important;
            color: var(--textMain) !important;
            border-radius: 8px;
            padding: 8px 12px;
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus,
        select:focus {
            border-color: #6366f1 !important;
        }

        .btn-primary {
            background: #6366f1;
            color: white;
            border-radius: 8px;
            padding: 8px 18px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s;
            border: none;
            min-height: 44px;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            padding: 6px 14px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            min-height: 40px;
        }

        .btn-danger:hover {
            background: #ef4444;
            color: white;
        }

        .btn-ghost {
            background: transparent;
            color: var(--textSec);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 14px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            min-height: 40px;
        }

        .btn-ghost:hover {
            color: var(--textMain);
            border-color: #9ca3af;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            padding: 10px 16px;
            background: var(--surfaceLight);
            color: var(--textSec);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: left;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            color: #d1d5db;
        }

        tbody tr:hover {
            background: rgba(0, 0, 0, 0.02);
        }

        /* ─── Sidebar & Overlay ─── */
        #sidebar {
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #sidebarOverlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(156, 163, 175, 0.6);
            z-index: 55;
            backdrop-filter: blur(2px);
        }

        #sidebarOverlay.active {
            display: block;
        }

        body.sidebar-open {
            overflow: hidden;
        }

        /* ─── Table responsive (always) ─── */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }

        /* ─── Mobile bottom nav ─── */
        #mobileBottomNav {
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(18, 18, 18, 0.92);
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }

        #mobileBottomNav a {
            min-height: 52px;
            min-width: 48px;
        }

        /* ─── Mobile (<768px) ─── */
        @media (max-width:767px) {

            html,
            body {
                overflow-x: hidden;
                padding-top: max(1.5rem, env(safe-area-inset-top));
            }

            .portal-main-content {
                padding-bottom: 5rem !important;
            }

            .flex-1.overflow-y-auto {
                padding-bottom: 5rem !important;
            }

            /* Topbar title scaling (Increased for better readability) */
            .portal-topbar h1 {
                font-size: 1.35rem !important;
                line-height: 1.3;
            }

            .portal-topbar p {
                font-size: 0.85rem !important;
            }

            /* Wrap topbar action buttons */
            .portal-topbar>div:last-child {
                flex-wrap: wrap;
                gap: 0.5rem !important;
            }

            /* Modals fit screen */
            [id$="Modal"]>div:not(#modalBox) {
                max-width: calc(100vw - 1.5rem) !important;
            }

            #modalBox {
                max-width: calc(100vw - 1.5rem) !important;
            }

            /* Stat cards: more readable fonts */
            .stat-card {
                padding: 16px 12px;
            }

            .stat-card .text-3xl {
                font-size: 1.75rem !important;
            }

            /* Buttons: full width on phone forms */
            form .btn-primary,
            form .btn-ghost {
                width: 100%;
                justify-content: center;
            }

            /* Select/input: ensure no overflow */
            select,
            input[type="text"],
            input[type="email"],
            input[type="number"],
            input[type="password"],
            input[type="date"],
            input[type="month"] {
                max-width: 100%;
                box-sizing: border-box;
            }
        }

        /* ─── Tablet (768–1023px) ─── */
        @media (min-width:768px) and (max-width:1023px) {

            html,
            body {
                overflow-x: hidden;
            }
        }

        /* ─── TV / Large screens (≥1600px) ─── */
        @media (min-width:1600px) {

            .flex-1.overflow-y-auto>div,
            .flex-1.overflow-y-auto>.p-6 {
                max-width: 1400px;
                margin-left: auto;
                margin-right: auto;
            }
        }

        /* ─── Universal: prevent horizontal overflow ─── */
        * {
            box-sizing: border-box;
        }

        img,
        video {
            max-width: 100%;
            height: auto;
        }
    </style>
    <script>
        function toggleSidebar() { var s = document.getElementById('sidebar'), o = document.getElementById('sidebarOverlay'); if (!s) return; if (!s.classList.contains('-translate-x-full')) { s.classList.add('-translate-x-full'); s.classList.remove('translate-x-0'); if (o) o.classList.remove('active'); document.body.classList.remove('sidebar-open'); } else { s.classList.remove('-translate-x-full'); s.classList.add('translate-x-0'); if (o) o.classList.add('active'); document.body.classList.add('sidebar-open'); } }
        function closeSidebar() { var s = document.getElementById('sidebar'), o = document.getElementById('sidebarOverlay'); if (s) { s.classList.add('-translate-x-full'); s.classList.remove('translate-x-0'); } if (o) o.classList.remove('active'); document.body.classList.remove('sidebar-open'); }
        function toggleNavSection(sectionId, btn) {
            var section = document.getElementById(sectionId);
            if (!section) return;
            var willOpen = section.classList.contains('hidden');
            section.classList.toggle('hidden');
            var sign = btn ? btn.querySelector('.nav-section-sign') : null;
            if (sign) sign.textContent = willOpen ? '-' : '+';
        }

        async function switchSociety(select) {
            const societyId = select.value;
            try {
                const response = await fetch('../society/switch.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ societyId: societyId })
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert(data.error || 'Unable to switch society.');
                    return;
                }
                window.location.href = 'dashboard.php';
            } catch (error) {
                alert('Unable to switch society.');
            }
        }
    
        function toggleTheme() {
            const current = document.body.getAttribute('data-theme') || 'light';
            const nextTheme = current === 'dark' ? 'light' : 'dark';
            document.body.setAttribute('data-theme', nextTheme);
            fetch('theme_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'set_theme', theme: nextTheme })
            });
        }
        
        document.addEventListener("DOMContentLoaded", function() {
            var sidebarNav = document.getElementById('sidebarNav');
            if (sidebarNav) {
                var scrollPos = sessionStorage.getItem('sidebarScrollPos');
                if (scrollPos) {
                    sidebarNav.scrollTop = parseInt(scrollPos, 10);
                }
                window.addEventListener("beforeunload", function() {
                    sessionStorage.setItem('sidebarScrollPos', sidebarNav.scrollTop);
                });
            }
        });
</script>
</head>

<body class="flex h-screen overflow-hidden" data-theme="<?= htmlspecialchars($session['theme'] ?? 'light') ?>">

    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- SIDEBAR -->
    <?php
    // Pre-compute sidebar variables before rendering HTML
    $isMemberOnly = !$isSA
        && empty($permissions['VIEW_SNAPSHOTS']) && empty($permissions['VIEW_VIDEOS'])
        && empty($permissions['VIEW_LOGS']) && empty($permissions['MANAGE_USERS'])
        && empty($permissions['MANAGE_ROLES']) && empty($permissions['DELETE_RECORDS'])
        && empty($permissions['DOWNLOAD_SQL']) && empty($permissions['MANAGE_MAINTENANCE']);
    $_viewMode = $_SESSION['portal_view_mode'] ?? 'admin';
    $_inMemberView = ($isSA || !$isMemberOnly) && ($_viewMode === 'member');
    ?>

    <!-- Member Setup Modal -->
    <div id="memberSetupModal"
        class="fixed inset-0 z-[200] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-surface border border-border rounded-2xl w-full max-w-sm shadow-2xl">
            <div class="p-5 border-b border-border flex justify-between items-center">
                <h2 class="font-bold text-base text-textMain"><i class="fas fa-user-plus text-indigo-400 mr-2"></i>Setup
                    Member Profile</h2>
                <button onclick="document.getElementById('memberSetupModal').classList.add('hidden')"
                    class="text-textSec hover:text-textMain w-8 h-8 rounded-full bg-textMain flex items-center justify-center"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-xs text-textSec">You don't have a society member record yet. Fill in your details to
                    switch to member view.</p>
                <div><label class="block text-xs text-textSec uppercase font-bold mb-1">Flat / Unit Number
                        *</label><input type="text" id="ms_flat" class="w-full text-sm" placeholder="e.g. A-101"></div>
                <div><label class="block text-xs text-textSec uppercase font-bold mb-1">Plot Size (sqft)
                        *</label><input type="number" id="ms_sqft" class="w-full text-sm" placeholder="e.g. 1200"
                        min="1" onchange="lookupRate()" onblur="lookupRate()"></div>
                <div><label class="block text-xs text-textSec uppercase font-bold mb-1">Monthly Amount
                        (₹)</label><input type="number" id="ms_amount" class="w-full text-sm"
                        placeholder="Auto-filled or enter manually" min="0" step="0.01">
                    <div id="ms_amount_hint" class="text-xs text-textSec mt-1"></div>
                </div>
                <div><label class="block text-xs text-textSec uppercase font-bold mb-1">Due Day of Month</label><input
                        type="number" id="ms_due" class="w-full text-sm" value="5" min="1" max="31"></div>
            </div>
            <div class="p-4 border-t border-border flex gap-3 justify-end">
                <button onclick="document.getElementById('memberSetupModal').classList.add('hidden')"
                    class="btn-ghost text-sm">Cancel</button>
                <button onclick="confirmMemberSetup()" id="memberSetupConfirmBtn" class="btn-primary text-sm">Confirm
                    &amp; Switch</button>
            </div>
        </div>
    </div>

    <script>
        async function handleViewSwitch() {
            const btn = document.getElementById('viewSwitchBtn');
            if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }
            try {
                const checkRes = await fetch('../switch_view_api.php?action=check');
                const checkData = await checkRes.json();
                if (checkData.error) { alert(checkData.error); return; }
                if (checkData.current_mode === 'member') {
                    const res = await fetch('../switch_view_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'switch' }) });
                    const d = await res.json();
                    if (d.error) { alert(d.error); return; }
                    window.location.href = 'dashboard.php';
                } else if (checkData.has_member) {
                    const res = await fetch('../switch_view_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'switch' }) });
                    const d = await res.json();
                    if (d.error) { alert(d.error); return; }
                    window.location.href = 'my_dues.php';
                } else {
                    document.getElementById('memberSetupModal').classList.remove('hidden');
                }
            } catch (e) { alert('Error: ' + e.message); }
            finally { if (btn) { btn.disabled = false; btn.style.opacity = ''; } }
        }
        async function confirmMemberSetup() {
            const flat = document.getElementById('ms_flat').value.trim();
            const sqft = parseFloat(document.getElementById('ms_sqft').value) || 0;
            const amount = parseFloat(document.getElementById('ms_amount').value) || 0;
            const due = parseInt(document.getElementById('ms_due').value) || 5;
            if (!flat) { alert('Flat number is required'); return; }
            if (sqft <= 0) { alert('Plot size is required'); return; }
            const btn = document.getElementById('memberSetupConfirmBtn');
            btn.disabled = true; btn.textContent = 'Saving…';
            try {
                const res = await fetch('../switch_view_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'switch', flat_number: flat, plot_size_sqft: sqft, monthly_amount: amount, due_day: due }) });
                const d = await res.json();
                if (d.error) { alert(d.error); return; }
                window.location.href = 'my_dues.php';
            } catch (e) { alert('Error: ' + e.message); }
            finally { btn.disabled = false; btn.textContent = 'Confirm & Switch'; }
        }
        async function lookupRate() {
            const sqft = parseFloat(document.getElementById('ms_sqft').value) || 0;
            if (!sqft) return;
            try {
                const res = await fetch('../rate_map_api.php?action=lookup&plot_size_sqft=' + sqft);
                const d = await res.json();
                const hint = document.getElementById('ms_amount_hint');
                if (d.found && d.monthly_amount) {
                    document.getElementById('ms_amount').value = d.monthly_amount;
                    hint.textContent = 'Auto-filled from rate map'; hint.className = 'text-xs text-emerald-400 mt-1';
                } else {
                    hint.textContent = 'No rate configured — enter manually'; hint.className = 'text-xs text-yellow-500 mt-1';
                }
            } catch (e) { }
        }
    </script>

    <aside id="sidebar"
        class="fixed md:relative w-64 h-full bg-surface flex-shrink-0 border-r border-border flex flex-col z-[60] md:z-20 -translate-x-full md:translate-x-0">
        <div class="h-16 flex items-center px-6 border-b border-border gap-3 relative">
            <i class="fas fa-eye text-primary text-xl"></i>
            <div class="min-w-0 flex-1 truncate"><span class="font-bold text-base tracking-wide block truncate text-textMain">EyeSense</span><span
                    class="block text-xs text-textSec -mt-0.5 truncate"><?= htmlspecialchars($currentSocietyName ?: 'Cloud Portal') ?></span></div>
            <label class="toggle-switch absolute right-4 scale-[0.6] origin-right" title="Toggle Dark Theme">
                <input type="checkbox" onchange="toggleTheme()" <?= ($session['theme'] ?? 'light') === 'dark' ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="px-4 py-3 border-b border-border bg-surfaceLight">
            <div class="text-xs text-textSec uppercase tracking-wider mb-1">Logged in as</div>
            <div class="font-bold text-textMain text-sm"><?= htmlspecialchars($session['username']) ?>
            <span class="badge badge-role"><b><?= htmlspecialchars(get_friendly_role_name($session['role'])) ?></b></span></div>
            <?php if ($isSA && !empty($_SESSION['portal_available_societies']) && count($_SESSION['portal_available_societies']) > 1): ?>
                <div class="mt-2 text-xs">
                    <label class="block text-[10px] uppercase text-textSec font-bold mb-1 tracking-wider">Active Society</label>
                        <select onchange="<?= DB_CFG_MULTI_SOCIETY_LOGIN_ENABLED ? 'switchSociety(this)' : "location.href='?switch_client_id=' + encodeURIComponent(this.value)" ?>"
                            class="w-full text-xs bg-surfaceLight border border-border rounded-lg px-2.5 py-1.5 font-semibold text-textMain cursor-pointer focus:border-primary outline-none">
                        <?php foreach ($_SESSION['portal_available_societies'] as $soc): ?>
                            <option value="<?= htmlspecialchars((string)($soc['society_id'] ?? $soc['client_id'])) ?>" <?= ($soc['society_id'] ?? null) === ($_SESSION['portal_current_society_id'] ?? null) || (!isset($soc['society_id']) && $soc['client_id'] === $session['client_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($soc['society_name']) ?> (<?= htmlspecialchars(get_friendly_role_name($soc['role'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="mt-1 text-xs">
                    <span class="block text-[10px] uppercase text-textSec font-bold tracking-wider">Society</span>
                    <span class="font-semibold text-textMain text-sm"><?= htmlspecialchars($currentSocietyName) ?></span>
                </div>
            <?php endif; ?>
            <a href="society_register.php"
                class="btn-add-society mt-2 w-full flex items-center justify-center gap-2 px-2 py-1.5 rounded-lg text-xs font-semibold transition no-underline">
                <i class="fas fa-plus w-4"></i> Add Society
            </a>
            <?php if (!$isMemberOnly): ?>
                <button onclick="handleViewSwitch()" id="viewSwitchBtn"
                    class="btn-view-switch mt-2 w-full flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer">
                    <i class="fas <?= $_inMemberView ? 'fa-shield-halved' : 'fa-user' ?> w-4"></i>
                    <?= $_inMemberView ? '⬅ Login to Admin Portal' : 'Login to Member Portal' ?>
                </button>
            <?php endif; ?>
        </div>
        <nav id="sidebarNav" class="flex-1 overflow-y-auto py-4 px-4 space-y-1">
            <?php if (!$isMemberOnly && !$_inMemberView): ?>
                <?php
                $eyesensePages = ['dashboard', 'gallery', 'logs'];
                $adminPages = ['manage_users', 'manage_roles', 'data_control', 'backup_download', 'retention'];
                $maintenancePages = ['maintenance', 'maintenance_members', 'maintenance_settings', 'payment_receipts', 'pending_receipts', 'registration_requests', 'complaints', 'expenses', 'activity_monitor', 'tenants'];
                $quickToolsPages = ['client', 'visitor'];
                $isEyesenseOpen = in_array($currentPage, $eyesensePages, true);
                $isAdminOpen = in_array($currentPage, $adminPages, true);
                $isMaintenanceOpen = in_array($currentPage, $maintenancePages, true);
                $isQuickToolsOpen = in_array($currentPage, $quickToolsPages, true);
                $isHostelOpen = ($currentPage === 'hostel_wrapper');
                ?>

                <div class="nav-section">
                    <button type="button" class="nav-section-toggle" onclick="toggleNavSection('navSectionEyesense', this)">
                        <span>EyeSense</span>
                        <span class="nav-section-sign"><?= $isEyesenseOpen ? '-' : '+' ?></span>
                    </button>
                    <div id="navSectionEyesense" class="nav-section-content space-y-1 <?= $isEyesenseOpen ? '' : 'hidden' ?>">
                        <a href="dashboard.php"
                            class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                            <i class="fas fa-chart-pie text-primary w-5"></i><span>Dashboard</span></a>
                        <?php if ($isSA || !empty($permissions['VIEW_SNAPSHOTS'])): ?>
                            <a href="gallery.php"
                                class="nav-item <?= $currentPage === 'gallery' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-photo-film text-purple-400 w-5"></i><span>Snapshot Gallery</span></a>
                        <?php endif; ?>
                        <?php if ($isSA || !empty($permissions['VIEW_VIDEOS'])): ?>
                            <a href="gallery.php?type=videos"
                                class="nav-item w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-video text-blue-400 w-5"></i><span>Video Clips</span></a>
                        <?php endif; ?>
                        <?php if ($isSA || !empty($permissions['VIEW_LOGS'])): ?>
                            <a href="logs.php"
                                class="nav-item <?= $currentPage === 'logs' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-list-ul text-cyan-400 w-5"></i><span>Event Logs</span></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="nav-section">
                    <button type="button" class="nav-section-toggle" onclick="toggleNavSection('navSectionAdministration', this)">
                        <span>Administration</span>
                        <span class="nav-section-sign"><?= $isAdminOpen ? '-' : '+' ?></span>
                    </button>
                    <div id="navSectionAdministration" class="nav-section-content space-y-1 <?= $isAdminOpen ? '' : 'hidden' ?>">
                        <?php if ($isSA || !empty($permissions['MANAGE_USERS'])): ?>
                            <a href="manage_users.php"
                                class="nav-item <?= $currentPage === 'manage_users' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-users-cog text-yellow-400 w-5"></i><span>Manage Users</span></a>
                        <?php endif; ?>
                        <?php if ($isSA || !empty($permissions['MANAGE_ROLES'])): ?>
                            <a href="manage_roles.php"
                                class="nav-item <?= $currentPage === 'manage_roles' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-shield-halved text-orange-400 w-5"></i><span>Manage Roles</span></a>
                        <?php endif; ?>
                        <?php if ($isSA || !empty($permissions['DELETE_RECORDS'])): ?>
                            <a href="data_control.php"
                                class="nav-item <?= $currentPage === 'data_control' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-database text-red-400 w-5"></i><span>Data Control</span></a>
                        <?php endif; ?>
                        <?php if ($isSA || !empty($permissions['DOWNLOAD_SQL'])): ?>
                            <a href="backup_download.php"
                                class="nav-item <?= $currentPage === 'backup_download' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-cloud-arrow-down text-green-400 w-5"></i><span>Backup Download</span></a>
                        <?php endif; ?>
                        <?php if ($isSA || !empty($permissions['MANAGE_ROLES'])): ?>
                            <a href="retention.php"
                                class="nav-item <?= $currentPage === 'retention' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-clock-rotate-left text-textSec w-5"></i><span>Retention Settings</span></a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isSA || !empty($permissions['MANAGE_MAINTENANCE'])): ?>
                    <div class="nav-section">
                        <button type="button" class="nav-section-toggle" onclick="toggleNavSection('navSectionMaintenance', this)">
                            <span>Maintenance</span>
                            <span class="nav-section-sign"><?= $isMaintenanceOpen ? '-' : '+' ?></span>
                        </button>
                        <div id="navSectionMaintenance" class="nav-section-content space-y-1 <?= $isMaintenanceOpen ? '' : 'hidden' ?>">
                            <a href="maintenance.php"
                                class="nav-item <?= $currentPage === 'maintenance' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-building-columns text-emerald-400 w-5"></i><span>Maintenance Hub</span></a>
                            <a href="maintenance_members.php"
                                class="nav-item <?= $currentPage === 'maintenance_members' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-users text-teal-400 w-5"></i><span>Society Members</span></a>
                            <a href="maintenance_settings.php"
                                class="nav-item <?= $currentPage === 'maintenance_settings' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-sliders text-textSec w-5"></i><span>Settings</span></a>
                            <a href="payment_receipts.php"
                                class="nav-item <?= $currentPage === 'payment_receipts' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-table-list text-indigo-400 w-5"></i><span>Payment Receipts</span></a>
                            <a href="pending_receipts.php"
                                class="nav-item <?= $currentPage === 'pending_receipts' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-clock text-yellow-400 w-5"></i><span>Awaiting Verification</span>
                                <?php
                                try {
                                    $pcnt = $pdo->prepare("SELECT COUNT(*) FROM maintenance_receipts WHERE client_id=? AND review_status='PENDING'");
                                    $pcnt->execute([$session['client_id']]);
                                    $pc = (int) $pcnt->fetchColumn();
                                    if ($pc > 0)
                                        echo '<span class="ml-auto text-xs bg-yellow-500/20 text-yellow-400 px-2 py-0.5 rounded-full font-bold">' . $pc . '</span>';
                                } catch (Throwable $e) {
                                } ?>
                            </a>
                            <a href="registration_requests.php"
                                class="nav-item <?= $currentPage === 'registration_requests' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-user-plus text-cyan-400 w-5"></i><span>Registration Requests</span>
                                <?php
                                try {
                                    $rcnt = $pdo->prepare("SELECT COUNT(*) FROM member_registration_requests WHERE client_id=? AND status='PENDING'");
                                    $rcnt->execute([$session['client_id']]);
                                    $rc = (int) $rcnt->fetchColumn();
                                    if ($rc > 0)
                                        echo '<span class="ml-auto text-xs bg-cyan-500/20 text-cyan-400 px-2 py-0.5 rounded-full font-bold">' . $rc . '</span>';
                                } catch (Throwable $e) {
                                } ?>
                            </a>
                            <a href="complaints.php"
                                class="nav-item <?= $currentPage === 'complaints' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-triangle-exclamation text-red-400 w-5"></i><span>Complaints</span>
                                <?php
                                try {
                                    $ccnt = $pdo->prepare("SELECT COUNT(*) FROM member_complaints WHERE client_id=? AND status='OPEN'");
                                    $ccnt->execute([$session['client_id']]);
                                    $cc = (int) $ccnt->fetchColumn();
                                    if ($cc > 0)
                                        echo '<span class="ml-auto text-xs bg-red-500/20 text-red-400 px-2 py-0.5 rounded-full font-bold">' . $cc . '</span>';
                                } catch (Throwable $e) {
                                } ?>
                            </a>
                            <a href="expenses.php"
                                class="nav-item <?= $currentPage === 'expenses' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-wallet text-amber-400 w-5"></i><span>Expense Tracker</span>
                            </a>
                            <a href="activity_monitor.php"
                                class="nav-item <?= $currentPage === 'activity_monitor' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-satellite-dish text-indigo-400 w-5"></i><span>Activity Monitor</span>
                                <?php
                                try {
                                    portalEnsureLoginSessionActivityColumn($pdo);
                                    portalExpireStaleLoginSessions($pdo, $session['client_id']);
                                    $acnt = $pdo->prepare("
                                        SELECT COUNT(*)
                                        FROM portal_login_sessions
                                        WHERE client_id=?
                                          AND is_active=1
                                          AND TIMESTAMPDIFF(SECOND, COALESCE(last_seen_at, login_at), NOW()) <= ?
                                    ");
                                    $acnt->execute([$session['client_id'], portalSessionActiveWindowSeconds()]);
                                    $ac = (int) $acnt->fetchColumn();
                                    if ($ac > 0)
                                        echo '<span class="ml-auto text-xs bg-indigo-500/20 text-indigo-400 px-2 py-0.5 rounded-full font-bold">' . $ac . '</span>';
                                } catch (Throwable $e) {
                                } ?>
                            </a>
                            <!-- Tenant Management -->
                            <a href="tenants.php"
                                class="nav-item <?= $currentPage === 'tenants' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-gray-300 no-underline">
                                <i class="fas fa-house-user text-violet-400 w-5"></i><span>Tenant Management</span>
                                <?php
                                try {
                                    $tcnt = $pdo->prepare("SELECT COUNT(*) FROM society_tenants WHERE client_id=? AND registration_status='Pending'");
                                    $tcnt->execute([$session['client_id']]);
                                    $tc = (int) $tcnt->fetchColumn();
                                    if ($tc > 0)
                                        echo '<span class="ml-auto text-xs bg-violet-500/20 text-violet-400 px-2 py-0.5 rounded-full font-bold">' . $tc . '</span>';
                                } catch (Throwable $e) {
                                } ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($isSA): ?>
                    <!-- ── SA Quick Tools ── -->
                    <?php
                    $_sidebarLoginRef = generate_login_ref($session['client_id']);
                    $_sidebarLoginRefUrl = rtrim(DB_CFG_PORTAL_BASE_URL, '/') . '/login.php?ref=' . $_sidebarLoginRef;
                    ?>
                    <div class="nav-section">
                        <button type="button" class="nav-section-toggle" onclick="toggleNavSection('navSectionQuickTools', this)">
                            <span>Quick Tools</span>
                            <span class="nav-section-sign"><?= $isQuickToolsOpen ? '-' : '+' ?></span>
                        </button>
                        <div id="navSectionQuickTools" class="nav-section-content space-y-1 <?= $isQuickToolsOpen ? '' : 'hidden' ?>">
                            <a href="client.php?client_id=<?= urlencode($session['client_id']) ?>" target="_blank"
                                class="nav-item <?= $currentPage === 'client' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-map-pin text-cyan-400 w-5"></i><span>Client Map Entry</span>
                                <i class="fas fa-external-link-alt text-textSec text-xs ml-auto"></i>
                            </a>
                            <a href="visitor.php" target="_blank"
                                class="nav-item <?= $currentPage === 'visitor' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-search-location text-emerald-400 w-5"></i><span>Visitor Plot Locator</span>
                                <i class="fas fa-external-link-alt text-textSec text-xs ml-auto"></i>
                            </a>
                            <button type="button" id="sidebarShareLoginBtn"
                                data-loginrefurl="<?= htmlspecialchars($_sidebarLoginRefUrl, ENT_QUOTES) ?>"
                                onclick="sidebarCopyLoginRef()"
                                class="nav-item w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec cursor-pointer bg-transparent border-none">
                                <i class="fas fa-user-plus text-purple-400 w-5"></i>
                                <span id="sidebarShareLoginLabel">Share Login Link</span>
                                <i class="fas fa-copy text-textSec text-xs ml-auto" id="sidebarShareLoginIcon"></i>
                            </button>
                        </div>
                    </div>
                    <script>
                        function sidebarCopyLoginRef() {
                            var btn = document.getElementById('sidebarShareLoginBtn');
                            var url = btn ? btn.getAttribute('data-loginrefurl') : '';
                            var label = document.getElementById('sidebarShareLoginLabel');
                            var icon = document.getElementById('sidebarShareLoginIcon');
                            if (!url) return;
                            navigator.clipboard.writeText(url).then(function () {
                                if (label) {
                                    label.textContent = 'Link Copied!';
                                    label.style.color = '#a78bfa';
                                }
                                if (icon) icon.className = 'fas fa-check text-purple-400 text-xs ml-auto';
                                setTimeout(function () {
                                    if (label) {
                                        label.textContent = 'Share Login Link';
                                        label.style.color = '';
                                    }
                                    if (icon) icon.className = 'fas fa-copy text-textSec text-xs ml-auto';
                                }, 2500);
                            }).catch(function () {
                                prompt('Copy this link:', url);
                            });
                        }
                    </script>

                    <?php if (!empty($_SESSION['hostel_management_enabled'])): ?>
                    <div class="nav-section">
                        <button type="button" class="nav-section-toggle" onclick="toggleNavSection('navSectionHostelManagement', this)">
                            <span style="text-transform: uppercase;">Hostel Management</span>
                            <span class="nav-section-sign"><?= $isHostelOpen ? '-' : '+' ?></span>
                        </button>
                        <div id="navSectionHostelManagement" class="nav-section-content space-y-1 <?= $isHostelOpen ? '' : 'hidden' ?>">
                            <a href="hostel_wrapper.php?path=hostel/hostel-list.php"
                                class="nav-item <?= ($subPage ?? '') === 'hostel-list.php' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-hotel text-orange-400 w-5"></i><span>Hostel Management</span>
                            </a>
                            <a href="hostel_wrapper.php?path=rooms/room-list.php"
                                class="nav-item <?= ($subPage ?? '') === 'room-list.php' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-door-open text-orange-400 w-5"></i><span>Rooms</span>
                            </a>
                            <a href="hostel_wrapper.php?path=students/student-list.php"
                                class="nav-item <?= ($subPage ?? '') === 'student-list.php' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-user-graduate text-orange-400 w-5"></i><span>Students</span>
                            </a>
                            <a href="hostel_wrapper.php?path=wardens/warden-list.php"
                                class="nav-item <?= ($subPage ?? '') === 'warden-list.php' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-user-tie text-orange-400 w-5"></i><span>Wardens</span>
                            </a>
                            <a href="hostel_wrapper.php?path=reports/index.php"
                                class="nav-item <?= (($subPage ?? '') === 'index.php' && strpos($path ?? '', 'reports') !== false) ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-chart-pie text-orange-400 w-5"></i><span>Reports</span>
                            </a>
                            <a href="hostel_wrapper.php?path=settings/index.php"
                                class="nav-item <?= (($subPage ?? '') === 'index.php' && strpos($path ?? '', 'settings') !== false) ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                                <i class="fas fa-sliders text-orange-400 w-5"></i><span>Settings</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php else: ?>
                <?php if (strtolower($session['role'] ?? '') === 'tenant'): ?>
                    <a href="tenant_dashboard.php"
                        class="nav-item <?= $currentPage === 'tenant_dashboard' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                        <i class="fas fa-chart-pie text-primary w-5"></i><span>Dashboard</span></a>
                <?php else: ?>
                    <!-- Member sidebar (member-only users OR admins in member view) -->
                    <?php if ($_inMemberView): ?>
                    <div class="mx-1 mb-2 px-3 py-2 rounded-lg flex items-center gap-2 text-xs font-bold"
                        style="background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.2);color:#34d399;">
                        <i class="fas fa-eye w-4"></i> Member Preview Mode
                    </div>
                <?php endif; ?>
                <a href="my_dues.php"
                    class="nav-item <?= $currentPage === 'my_dues' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                    <i class="fas fa-receipt text-emerald-400 w-5"></i><span>My Dues &amp; Receipts</span></a>
                <a href="payment_receipts.php"
                    class="nav-item <?= $currentPage === 'payment_receipts' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                    <i class="fas fa-table-list text-indigo-400 w-5"></i><span>Payment Receipts</span></a>
                <a href="my_complaints.php"
                    class="nav-item <?= $currentPage === 'my_complaints' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                    <i class="fas fa-comment-dots text-orange-400 w-5"></i><span>My Complaints</span>
                    <?php
                    try {
                        $mccnt = $pdo->prepare("SELECT id FROM society_members WHERE client_id=? AND employee_id=? AND status='active' LIMIT 1");
                        $mccnt->execute([$session['client_id'], $_SESSION['portal_employee_id'] ?? '']);
                        $mcm = $mccnt->fetch();
                        if ($mcm) {
                            $mcc2 = $pdo->prepare("SELECT COUNT(*) FROM member_complaints WHERE client_id=? AND member_id=? AND status IN('REVIEWED','RESOLVED')");
                            $mcc2->execute([$session['client_id'], $mcm['id']]);
                            $mcc2v = (int) $mcc2->fetchColumn();
                            if ($mcc2v > 0)
                                echo '<span class="ml-auto text-xs bg-orange-500/20 text-orange-400 px-2 py-0.5 rounded-full font-bold">' . $mcc2v . '</span>';
                        }
                    } catch (Throwable $e) {
                    } ?>
                </a>
                <!-- My Tenants -->
                <?php
                $mts = $pdo->prepare("SELECT id FROM society_members WHERE client_id=? AND employee_id=? AND status='active' LIMIT 1");
                $mts->execute([$session['client_id'], $_SESSION['portal_employee_id'] ?? '']);
                if ($mts->fetch()): ?>
                <a href="my_tenants.php"
                    class="nav-item <?= $currentPage === 'my_tenants' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                    <i class="fas fa-house-user text-violet-400 w-5"></i><span>My Tenants</span>
                </a>
                <?php endif; ?>
                <div class="border-t border-border my-3"></div>
                <div class="px-3 py-1 text-xs font-bold text-textSec uppercase tracking-wider">My Profile</div>

                <a href="edit_profile.php"
                    class="nav-item <?= $currentPage === 'edit_profile' ? 'active' : '' ?> w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline">
                    <i class="fas fa-user-pen text-yellow-400 w-5"></i><span>Edit / Complete Profile</span></a>
                <?php
                // Build member share URL for sidebar copy button
                $_smlPlot = '';
                try {
                    $_smlEmpId = $_SESSION['portal_employee_id'] ?? '';
                    $_smlCid   = $session['client_id'] ?? '';
                    
                    // 1. Check employees table in $pdo (active society DB)
                    if (!empty($_smlEmpId) && $_smlCid !== 'PUBLIC') {
                        $_smlStmt = $pdo->prepare("SELECT plotno FROM employees WHERE client_id=? AND employee_id=? AND is_deleted=0 LIMIT 1");
                        $_smlStmt->execute([$_smlCid, $_smlEmpId]);
                        $_smlPlot = $_smlStmt->fetchColumn() ?: '';
                    }

                    // 2. Fallback: check employees in license DB
                    if (empty($_smlPlot) && !empty($_smlEmpId)) {
                        try {
                            $_licPdo = get_license_db();
                            $_licStmt = $_licPdo->prepare("SELECT plotno FROM employees WHERE client_id=? AND employee_id=? AND is_deleted=0 LIMIT 1");
                            $_licStmt->execute([$_smlCid, $_smlEmpId]);
                            $_smlPlot = $_licStmt->fetchColumn() ?: '';
                        } catch (Throwable $_licE) {}
                    }

                    // 3. Fallback: check flat_number in society_members
                    if (empty($_smlPlot) && !empty($_smlEmpId) && $_smlCid !== 'PUBLIC') {
                        try {
                            $_smStmt = $pdo->prepare("SELECT flat_number FROM society_members WHERE client_id=? AND employee_id=? AND status='active' LIMIT 1");
                            $_smStmt->execute([$_smlCid, $_smlEmpId]);
                            $_smlPlot = $_smStmt->fetchColumn() ?: '';
                        } catch (Throwable $_smE) {}
                    }
                } catch (Throwable $_smlE) {
                }
                $_smlUrl = $_smlPlot
                    ? rtrim(DB_CFG_PORTAL_BASE_URL, '/') . '/visitor.php?plotno=' . urlencode($_smlPlot) . '&cid=' . md5($session['client_id'])
                    : '';
                ?>
                <?php if ($_smlUrl): ?>
                    <button type="button" id="sidebarShareBtn" data-shareurl="<?= htmlspecialchars($_smlUrl, ENT_QUOTES) ?>"
                        onclick="sidebarCopyShareUrl(this)"
                        class="nav-item w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec cursor-pointer bg-transparent border-none">
                        <i class="fas fa-share-nodes text-purple-400 w-5"></i>
                        <span>Share My Location</span>
                        <i class="fas fa-copy text-textSec text-xs ml-auto" id="sidebarShareIcon"></i>
                    </button>
                    <script>
                        function sidebarCopyShareUrl(btn) {
                            var url = btn.getAttribute('data-shareurl');
                            var icon = document.getElementById('sidebarShareIcon');
                            navigator.clipboard.writeText(url).then(function () {
                                btn.querySelector('span').textContent = 'Link Copied!';
                                btn.querySelector('span').style.color = '#34d399';
                                if (icon) { icon.className = 'fas fa-check text-emerald-400 text-xs ml-auto'; }
                                setTimeout(function () {
                                    btn.querySelector('span').textContent = 'Share My Location';
                                    btn.querySelector('span').style.color = '';
                                    if (icon) { icon.className = 'fas fa-copy text-textSec text-xs ml-auto'; }
                                }, 2500);
                            }).catch(function () {
                                prompt('Copy this link:', url);
                            });
                        }
                    </script>
                <?php else: ?>
                    <a href="edit_profile.php"
                        class="nav-item w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-3 text-textSec no-underline"
                        title="Complete your profile to get a share link">
                        <i class="fas fa-share-nodes text-textSec w-5"></i>
                        <span>Share My Location</span>
                        <i class="fas fa-plus text-textSec text-xs ml-auto"></i>
                    </a>
                <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
        <div class="border-t border-border p-4">
            <a href="logout.php"
                class="w-full text-left px-3 py-2 rounded-lg flex items-center gap-3 text-textSec hover:text-red-400 transition text-sm no-underline">
                <i class="fas fa-right-from-bracket w-5"></i><span>Logout</span></a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 overflow-hidden flex flex-col relative">
        <?php if (!empty($societyNotice)): ?>
        <!-- Society Switch Notification Toast -->
        <div id="societyNoticeToast" class="fixed top-5 right-5 z-[9999] max-w-sm w-full transition-all duration-300 transform translate-y-0 opacity-100">
            <div class="glass-panel p-4 rounded-xl border border-indigo-500/30 bg-surface/95 dark:bg-surface/90 shadow-2xl flex items-start gap-3 backdrop-blur-md">
                <div class="w-8 h-8 rounded-lg bg-indigo-500/10 text-primary flex items-center justify-center font-bold text-sm shrink-0 mt-0.5">
                    <i class="fas fa-building-circle-check"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-semibold text-textSec uppercase tracking-wider">Society Switched</div>
                    <div class="text-sm font-medium text-textMain mt-0.5 break-words">
                        <?= htmlspecialchars($societyNotice) ?>
                    </div>
                </div>
                <button type="button" onclick="dismissSocietyNotice()" class="text-textSec hover:text-textMain transition p-1 text-sm shrink-0 bg-transparent border-none cursor-pointer" title="Dismiss">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <script>
            function dismissSocietyNotice() {
                const toast = document.getElementById('societyNoticeToast');
                if (toast) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(-10px)';
                    setTimeout(() => toast.remove(), 300);
                }
            }
            setTimeout(dismissSocietyNotice, 4500);
        </script>
        <?php endif; ?>
