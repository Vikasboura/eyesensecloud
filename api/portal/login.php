<?php
/**
 * EyeSense Cloud Portal — Login Page
 * Authenticates against the synced `employees` table.
 * Users log in with: Client ID + Employee ID (or email) + Password
 */
require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
require_once __DIR__ . '/../jwt_helper.php';
require_once __DIR__ . '/../society_context_holder.php';

portal_session_start();

// If coming from signup or explicit logout/registered, ensure session is fully cleared so login page is displayed
if (isset($_GET['registered']) || isset($_GET['from_signup']) || isset($_GET['logout'])) {
    $_SESSION = [];
    if (session_id()) {
        session_destroy();
        portal_session_start();
    }
} elseif (!empty($_SESSION['portal_client_id'])) {
    // If genuinely logged in, redirect to dashboard
    header("Location: dashboard.php");
    exit;
}

$theme = $_SESSION['portal_theme'] ?? 'light';
$error = '';

// ── Hash-protected login share link support ───────────────────────────────
// SA can share: /portal/login.php?ref=<signed_token>
// This pre-fills the Client ID field without exposing it in plain text.
$refClientId = '';
$refBanner   = '';
if (!empty($_GET['ref'])) {
    $refClientId = verify_login_ref($_GET['ref']);
    if ($refClientId) {
        $refBanner = htmlspecialchars($refClientId);
    }
}


// Roles are now dynamically fetched from the synced EyeSense `roles` table.
// `portal_is_superadmin` is set if access_level is L1 or ADMIN.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');      // employee_id OR email OR mobile OR portal_username
    $password = $_POST['password'] ?? '';

    if (!$login || !$password) {
        $error = 'All fields are required';
    } else {
        try {
            $pdo = get_indsac_db();

            $valid_matches = [];
            $globalUserColumn = DB_CFG_MULTI_SOCIETY_LOGIN_ENABLED ? ', global_user_id' : '';

            // ── 1. Try employee / member accounts ─────────────────────────
            $stmt = $pdo->prepare("
                  SELECT id, client_id, employee_id, full_name, email, role,
                       access_level, status, password_hash, custom_permissions{$globalUserColumn}
                FROM employees
                WHERE employee_id = ? OR email = ?
            ");
            $stmt->execute([$login, $login]);
            $employees = $stmt->fetchAll();

            foreach ($employees as $u) {
                if ($u['status'] === 'active' && !empty($u['password_hash']) && verify_werkzeug_password($password, $u['password_hash'])) {
                    $sName = get_society_name_by_client_id($pdo, $u['client_id']);
                    $valid_matches[] = [
                        'type' => 'employee',
                        'global_user_id' => (int)($u['global_user_id'] ?? 0),
                        'client_id' => $u['client_id'],
                        'user_id' => $u['id'],
                        'employee_id' => $u['employee_id'],
                        'full_name' => $u['full_name'],
                        'role' => $u['role'] ?: 'Unassigned',
                        'is_superadmin' => strtoupper($u['access_level'] ?? '') === 'ADMIN',
                        'society_name' => $sName
                    ];
                }
            }

            // ── 2. Try tenant portal accounts ─────────────────────────
            try {
                $tStmt = $pdo->prepare("
                    SELECT id, client_id, member_id, full_name, mobile, email,
                           registration_status, portal_username, portal_password_hash
                    FROM society_tenants
                    WHERE (portal_username = ? OR mobile = ? OR email = ?)
                      AND registration_status = 'Active'
                ");
                $tStmt->execute([$login, $login, $login]);
                $tenants = $tStmt->fetchAll();

                foreach ($tenants as $tenant) {
                    if (!empty($tenant['portal_password_hash']) && password_verify($password, $tenant['portal_password_hash'])) {
                        $sName = get_society_name_by_client_id($pdo, $tenant['client_id']);
                        $valid_matches[] = [
                            'type' => 'tenant',
                            'client_id' => $tenant['client_id'],
                            'user_id' => $tenant['id'],
                            'employee_id' => 'T-' . $tenant['id'],
                            'full_name' => $tenant['full_name'],
                            'role' => 'Tenant',
                            'is_superadmin' => false,
                            'tenant_id' => $tenant['id'],
                            'member_id' => $tenant['member_id'],
                            'society_name' => $sName
                        ];
                    }
                }
            } catch (Throwable $te) { /* portal_username column may not exist yet */ }

            // ── 3. Try registered public accounts ─────────────────────────
            try {
                $rStmt = $pdo->prepare("
                    SELECT id, fullname, email, mobile, password
                    FROM register
                    WHERE email = ? OR mobile = ?
                ");
                $rStmt->execute([$login, $login]);
                $regUsers = $rStmt->fetchAll();

                foreach ($regUsers as $ru) {
                    if (!empty($ru['password']) && password_verify($password, $ru['password'])) {
                        $valid_matches[] = [
                            'type' => 'register',
                            'client_id' => 'PUBLIC',
                            'user_id' => $ru['id'],
                            'employee_id' => 'R-' . $ru['id'],
                            'full_name' => $ru['fullname'],
                            'role' => 'Registered User',
                            'is_superadmin' => false,
                            'society_name' => 'EyeSense Public Portal'
                        ];
                    }
                }
            } catch (Throwable $re) { /* table might not exist yet */ }

            // ── 4. Multi-society mapping resolution ─────────────────────────
            if (DB_CFG_MULTI_SOCIETY_LOGIN_ENABLED && !empty($valid_matches)) {
                $mappedMatches = [];
                $seenSocieties = [];
                $mappingStmt = $pdo->prepare("SELECT m.id AS mapping_id, m.society_id, m.role AS mapping_role, m.is_default,
                        s.society_name, s.society_code, s.society_type, s.legacy_client_id,
                        e.id, e.client_id, e.employee_id, e.full_name, e.role, e.access_level
                    FROM user_society_mapping m
                    INNER JOIN users u ON u.id = m.user_id
                    INNER JOIN society s ON s.id = m.society_id AND s.status = 'ACTIVE'
                    INNER JOIN employees e ON e.global_user_id = m.user_id
                        AND e.client_id = s.legacy_client_id AND e.status = 'active'
                    WHERE m.user_id = ? AND m.status = 'ACTIVE'
                    ORDER BY (CASE WHEN u.last_active_society_id IS NOT NULL AND m.society_id = u.last_active_society_id THEN 1 ELSE 0 END) DESC,
                             m.is_default DESC,
                             m.id ASC");
                foreach ($valid_matches as $match) {
                    if ($match['type'] !== 'employee' || empty($match['global_user_id'])) continue;
                    $mappingStmt->execute([(int)$match['global_user_id']]);
                    while ($mapped = $mappingStmt->fetch()) {
                        if (isset($seenSocieties[$mapped['society_id']])) continue;
                        $seenSocieties[$mapped['society_id']] = true;
                        $mappedMatches[] = [
                            'type' => 'employee',
                            'global_user_id' => (int)$match['global_user_id'],
                            'society_id' => (int)$mapped['society_id'],
                            'client_id' => $mapped['client_id'],
                            'user_id' => (int)$mapped['id'],
                            'employee_id' => $mapped['employee_id'],
                            'full_name' => $mapped['full_name'],
                            'role' => $mapped['mapping_role'] ?: ($mapped['role'] ?: 'Unassigned'),
                            'is_superadmin' => strtoupper($mapped['access_level'] ?? '') === 'ADMIN' || strtoupper($mapped['mapping_role'] ?? '') === 'SUPER_ADMIN',
                            'society_name' => $mapped['society_name'],
                            'society_code' => $mapped['society_code'] ?? '',
                            'society_type' => $mapped['society_type'] ?? '',
                            'is_default' => (int)($mapped['is_default'] ?? 0)
                        ];
                    }
                }
                if (!empty($mappedMatches)) {
                    $valid_matches = $mappedMatches;
                }
            }

            if (empty($valid_matches)) {
                $error = 'Invalid credentials';
                logger_warning('Authentication failed', [
                    'login' => $login,
                    'ip' => getClientIp(),
                ], __FILE__, __LINE__);
            } else {
                // Store all valid matching accounts/societies in session for sidebar switcher
                $_SESSION['portal_available_societies'] = $valid_matches;

                // Automatically select default/active society (first match in pre-sorted $valid_matches)
                $active = $valid_matches[0];

                $_SESSION['portal_client_id']     = $active['client_id'];
                $_SESSION['portal_current_society_id'] = $active['society_id'] ?? null;
                $_SESSION['portal_user_id']       = $active['user_id'];
                $_SESSION['portal_employee_id']   = $active['employee_id'];
                $_SESSION['portal_username']      = $active['full_name'];
                $_SESSION['portal_role']          = $active['role'];
                $_SESSION['portal_is_superadmin'] = $active['is_superadmin'];

                $societyId = !empty($active['society_id']) ? (int)$active['society_id'] : 0;
                $userId = (int)$active['user_id'];
                $role = (string)($active['role'] ?: 'Unassigned');
                $clientId = (string)$active['client_id'];

                if ($societyId > 0) {
                    $jwtToken = jwt_issue_society_token($userId, $societyId, $role);
                    $_SESSION['portal_jwt_token'] = $jwtToken;
                }

                SocietyContextHolder::set($userId, $societyId, $role, $clientId);
                
                // Fetch hostel management status
                try {
                    $hmStmt = $pdo->prepare("SELECT hostel_management_enabled FROM clients WHERE client_id = ?");
                    $hmStmt->execute([$active['client_id']]);
                    $_SESSION['hostel_management_enabled'] = (int)$hmStmt->fetchColumn();
                } catch (Throwable $e) {
                    $_SESSION['hostel_management_enabled'] = 0;
                }
                $_SESSION['portal_cameras']       = [];

                if ($active['type'] === 'tenant') {
                    $_SESSION['portal_tenant_id'] = $active['tenant_id'];
                    $_SESSION['portal_member_id'] = $active['member_id'];
                } else {
                    unset($_SESSION['portal_tenant_id']);
                    unset($_SESSION['portal_member_id']);
                }

                // Load user preference
                try {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS user_preferences (
                            client_id VARCHAR(50) NOT NULL,
                            user_id INT NOT NULL,
                            theme VARCHAR(10) DEFAULT 'light',
                            PRIMARY KEY (client_id, user_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                    $prefStmt = $pdo->prepare("SELECT theme FROM user_preferences WHERE client_id = ? AND user_id = ?");
                    $prefStmt->execute([$active['client_id'], $active['user_id']]);
                    if ($pref = $prefStmt->fetchColumn()) {
                        $_SESSION['portal_theme'] = $pref;
                    }
                } catch (Throwable $e) {}

                if ($active['type'] === 'employee') {
                    logAudit($pdo, $active['client_id'], $active['employee_id'], 'LOGIN', 'Portal login successful');
                    logger_info('User login successful', [
                        'event_id' => bin2hex(random_bytes(8)),
                        'user_id' => $active['user_id'],
                        'client_id' => $active['client_id'],
                        'role' => $active['role'],
                        'ip' => getClientIp(),
                    ], __FILE__, __LINE__);
                    $ip = getClientIp();
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                    try {
                        portalEnsureLoginSessionActivityColumn($pdo);
                        portalCloseOtherLoginSessions($pdo, $active['client_id'], $active['employee_id']);
                        $lStmt = $pdo->prepare("
                            INSERT INTO portal_login_sessions
                              (client_id, employee_id, full_name, role, ip_address, user_agent, last_seen_at, is_active)
                            VALUES (?,?,?,?,?,?,NOW(),1)
                        ");
                        $lStmt->execute([
                            $active['client_id'], $active['employee_id'],
                            $active['full_name'], $active['role'] ?: 'Unassigned',
                            $ip, $ua
                        ]);
                        $_SESSION['portal_login_session_id'] = (int)$pdo->lastInsertId();
                    } catch (Throwable $e) { /* migration may not have run yet */ }
                } else {
                    logAudit($pdo, $active['client_id'], $active['employee_id'], 'LOGIN', 'Tenant portal login successful');
                    logger_info('Tenant login successful', [
                        'event_id' => bin2hex(random_bytes(8)),
                        'user_id' => $active['user_id'],
                        'client_id' => $active['client_id'],
                        'ip' => getClientIp(),
                    ], __FILE__, __LINE__);
                }

                if ($active['type'] === 'tenant') {
                    header("Location: tenant_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            }

        } catch (Exception $e) {
            $error = 'System error. Please try again.';
            logger_exception('Portal login database or application failure', $e, ['login' => $login]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EyeSense Cloud Portal | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        },
                        accent: {
                            500: '#a855f7',
                        }
                    },
                    fontFamily: { 
                        sans: ['Outfit', 'sans-serif'],
                        script: ['Caveat', 'cursive']
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --bg-dark: #070a14;
            --hero-overlay: rgba(7, 10, 20, 0.85);
            --card-bg: #ffffff;
            --input-bg: #f0f4fd;
            --text-main: #0f172a;
            --text-sec: #64748b;
            --modal-backdrop: rgba(15, 23, 42, 0.75);
        }

        body {
            background-color: var(--bg-dark);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            position: relative;
        }

        /* Curved Right Accent Layer - Modern Wave Boundary */
        .right-curve-bg {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 52vw;
            background: linear-gradient(135deg, rgba(240, 244, 255, 0.97) 0%, rgba(226, 232, 255, 0.94) 100%);
            clip-path: ellipse(85% 100% at 95% 50%);
            pointer-events: none;
            z-index: 1;
        }
        .right-curve-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, transparent, rgba(99, 102, 241, 0.35), rgba(168, 85, 247, 0.35), transparent);
            filter: blur(2px);
        }
        .right-curve-sub {
            position: fixed;
            top: -10%;
            right: -5%;
            bottom: -10%;
            width: 45vw;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.18) 0%, rgba(99, 102, 241, 0.12) 100%);
            border-radius: 50%;
            filter: blur(60px);
            pointer-events: none;
            z-index: 0;
        }

        /* Ambient Background Mesh & Hero Visual */
        .hero-bg-container {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }
        .hero-bg-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            opacity: 0.65;
            filter: brightness(0.85) contrast(1.15);
        }
        .hero-gradient-overlay {
            position: absolute;
            inset: 0;
            background: 
                linear-gradient(to right, rgba(7, 10, 20, 0.88) 0%, rgba(7, 10, 20, 0.65) 45%, rgba(7, 10, 20, 0.25) 100%),
                radial-gradient(circle at 15% 30%, rgba(99, 102, 241, 0.25) 0%, transparent 50%),
                radial-gradient(circle at 75% 80%, rgba(168, 85, 247, 0.18) 0%, transparent 50%);
        }

        /* Laser Security Scanning Line Effect */
        @keyframes laserScan {
            0% { top: -10%; opacity: 0; }
            15% { opacity: 0.55; }
            85% { opacity: 0.55; }
            100% { top: 110%; opacity: 0; }
        }
        .hero-scan-line {
            position: absolute;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent 0%, rgba(99, 102, 241, 0.6) 20%, rgba(59, 130, 246, 0.85) 50%, rgba(168, 85, 247, 0.6) 80%, transparent 100%);
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.8), 0 0 30px rgba(59, 130, 246, 0.6);
            animation: laserScan 9s ease-in-out infinite;
            pointer-events: none;
            z-index: 1;
        }

        /* Floating Glowing Ambient Orbs */
        @keyframes floatOrb1 {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(25px, -35px) scale(1.08); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
        }
        @keyframes floatOrb2 {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            50% { transform: translate(-30px, -25px) scale(1.12); }
        }
        .glow-orb-1 {
            position: absolute;
            top: 15%;
            left: 8%;
            width: 340px;
            height: 340px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.22) 0%, rgba(7, 10, 20, 0) 70%);
            filter: blur(55px);
            animation: floatOrb1 14s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }
        .glow-orb-2 {
            position: absolute;
            bottom: 18%;
            left: 32%;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.18) 0%, rgba(7, 10, 20, 0) 70%);
            filter: blur(50px);
            animation: floatOrb2 18s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        /* Glass / Elevated Card */
        .login-card-shadow {
            box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.2), 0 0 35px rgba(99, 102, 241, 0.12);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .login-card-shadow:hover {
            box-shadow: 0 30px 70px -15px rgba(15, 23, 42, 0.25), 0 0 45px rgba(99, 102, 241, 0.18);
        }

        /* Custom Input Styling & Focus Animation */
        .styled-input-box {
            background-color: #f0f4fd !important;
            border: 1px solid #e2e8f0 !important;
            color: #0f172a !important;
            transition: all 0.25s ease-in-out;
        }
        .styled-input-box i {
            transition: color 0.25s ease, transform 0.25s ease;
        }
        .styled-input-box:focus-within {
            background-color: #ffffff !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3.5px rgba(59, 130, 246, 0.18) !important;
        }
        .styled-input-box:focus-within i {
            color: #2563eb !important;
            transform: scale(1.15);
        }

        /* Feature Card Interactive Hover */
        .feature-card-item {
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .feature-card-item:hover {
            transform: translateY(-3px) translateX(4px);
            background-color: rgba(15, 23, 42, 0.85) !important;
            border-color: rgba(99, 102, 241, 0.45) !important;
            box-shadow: 0 10px 28px -5px rgba(99, 102, 241, 0.25), 0 0 15px rgba(99, 102, 241, 0.15);
        }
        .feature-card-item:hover .feature-icon-box {
            background-color: rgba(99, 102, 241, 0.28) !important;
            color: #818cf8 !important;
            transform: scale(1.1) rotate(5deg);
        }
        .feature-icon-box {
            transition: all 0.3s ease;
        }

        /* Button Gradient & Shimmer Elevation */
        .btn-gradient-primary {
            background: linear-gradient(135deg, #2563eb 0%, #4f46e5 50%, #7c3aed 100%);
            background-size: 200% 200%;
            box-shadow: 0 8px 25px -4px rgba(37, 99, 235, 0.35);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-gradient-primary:hover {
            background-position: 100% 50%;
            box-shadow: 0 12px 32px -4px rgba(37, 99, 235, 0.55), 0 4px 18px rgba(124, 58, 237, 0.4);
            transform: translateY(-2px);
        }
        .btn-gradient-primary:active {
            transform: translateY(0);
        }

        /* Watermark Vertical Text */
        .watermark-text {
            writing-mode: vertical-rl;
            text-transform: uppercase;
            letter-spacing: 0.35em;
            font-size: 11px;
            font-weight: 700;
            color: rgba(99, 102, 241, 0.25);
            user-select: none;
        }

        /* Password Eye Icon Rotation */
        #passToggleIcon {
            transition: transform 0.3s ease, color 0.2s ease;
        }

        /* Animations */
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        .animate-float {
            animation: floatSlow 5s ease-in-out infinite;
        }

        @keyframes pulseGlow {
            0%, 100% { opacity: 0.85; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.05); }
        }
        .animate-pulse-glow {
            animation: pulseGlow 3s ease-in-out infinite;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .delay-1 { animation-delay: 0.1s; opacity: 0; }
        .delay-2 { animation-delay: 0.2s; opacity: 0; }
        .delay-3 { animation-delay: 0.3s; opacity: 0; }
        .delay-4 { animation-delay: 0.4s; opacity: 0; }
        .delay-5 { animation-delay: 0.5s; opacity: 0; }

        /* Modal Step Indicator */
        .fp-step {
            flex: 1;
            text-align: center;
            padding: 8px 6px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .fp-step span {
            display: inline-flex;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #cbd5e1;
            color: #475569;
            font-size: 10px;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .fp-step.active {
            color: #2563eb;
            border-color: #2563eb;
            background: rgba(37, 99, 235, 0.08);
        }
        .fp-step.active span {
            background: #2563eb;
            color: #ffffff;
        }
        .fp-step.done {
            color: #10b981;
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.08);
        }
        .fp-step.done span {
            background: #10b981;
            color: #ffffff;
        }

        /* Reduced Motion */
        @media (prefers-reduced-motion: reduce) {
            .animate-float, .animate-pulse-glow, .animate-fade-in-up, .hero-scan-line, .glow-orb-1, .glow-orb-2 {
                animation: none !important;
                transform: none !important;
                opacity: 1 !important;
            }
        }
    </style>
</head>
<body class="relative min-h-screen flex flex-col justify-between overflow-x-hidden" data-theme="<?= htmlspecialchars($theme) ?>">

    <!-- Hero Background Image & Gradient Mesh -->
    <div class="hero-bg-container">
        <img src="../../images/eyesense_hero_bg.jpg" alt="EyeSense Security Cloud" class="hero-bg-image" onerror="this.style.display='none'">
        <div class="hero-gradient-overlay"></div>
        <div class="hero-scan-line"></div>
        <div class="glow-orb-1"></div>
        <div class="glow-orb-2"></div>
    </div>

    <!-- Curved Right Accent Layer -->
    <div class="right-curve-sub"></div>
    <div class="right-curve-bg hidden lg:block"></div>

    <!-- EyeSense Standard Shared Header -->
    <?php
    $rootPath = '../../';
    $activePage = 'login';
    require_once __DIR__ . '/../../includes/header.php';
    ?>

    <!-- Main Section (Centered responsive layout with clearance below header) -->
    <main class="relative z-20 w-full max-w-7xl mx-auto px-6 pt-24 sm:pt-28 pb-12 my-auto flex-1 flex items-center">
        <div class="w-full grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10 items-center">
            
            <!-- LEFT HERO COLUMN -->
            <div class="lg:col-span-7 space-y-4 pr-0 lg:pr-4">
                
                <!-- Tagline -->
                <div class="space-y-2 animate-fade-in-up delay-1">
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white tracking-tight leading-[1.15]">
                        Smarter Security<br>
                        for a <span class="bg-gradient-to-r from-blue-400 via-indigo-400 to-purple-400 bg-clip-text text-transparent">Safer Tomorrow</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300 max-w-lg leading-relaxed font-normal">
                        AI-powered surveillance, access control, and community management — all in one secure cloud platform.
                    </p>
                </div>

                <!-- 4 Feature Highlights Stack (Compact Pills matching design mockup) -->
                <div class="space-y-2.5 max-w-[330px] animate-fade-in-up delay-2">
                    <!-- Feature 1 -->
                    <div class="feature-card-item flex items-center gap-3 p-2.5 rounded-2xl bg-slate-900/60 backdrop-blur-md border border-white/10 cursor-pointer">
                        <div class="feature-icon-box w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center text-indigo-400 shrink-0">
                            <i class="fas fa-shield-halved text-xs"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white">Secure & Reliable</h4>
                            <p class="text-[11px] text-slate-300">Your data, always protected</p>
                        </div>
                    </div>

                    <!-- Feature 2 -->
                    <div class="feature-card-item flex items-center gap-3 p-2.5 rounded-2xl bg-slate-900/60 backdrop-blur-md border border-white/10 cursor-pointer">
                        <div class="feature-icon-box w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center text-indigo-400 shrink-0">
                            <i class="fas fa-chart-column text-xs"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white">Real-time Monitoring</h4>
                            <p class="text-[11px] text-slate-300">Stay informed, always</p>
                        </div>
                    </div>

                    <!-- Feature 3 -->
                    <div class="feature-card-item flex items-center gap-3 p-2.5 rounded-2xl bg-slate-900/60 backdrop-blur-md border border-white/10 cursor-pointer">
                        <div class="feature-icon-box w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center text-indigo-400 shrink-0">
                            <i class="fas fa-users text-xs"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white">Simplified Management</h4>
                            <p class="text-[11px] text-slate-300">Communities made easier</p>
                        </div>
                    </div>

                    <!-- Feature 4 -->
                    <div class="feature-card-item flex items-center gap-3 p-2.5 rounded-2xl bg-slate-900/60 backdrop-blur-md border border-white/10 cursor-pointer">
                        <div class="feature-icon-box w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center text-indigo-400 shrink-0">
                            <i class="fas fa-cloud text-xs"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white">Cloud Powered</h4>
                            <p class="text-[11px] text-slate-300">Access from anywhere</p>
                        </div>
                    </div>
                </div>

                <!-- Live Floating Badge & Handwritten Accent -->
                <div class="pt-1 flex items-center justify-between max-w-md animate-fade-in-up delay-3">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-400/30 text-xs font-semibold text-indigo-300 backdrop-blur-md shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-blue-400 animate-ping"></span>
                        <span class="w-2 h-2 rounded-full bg-blue-400 -ml-4"></span>
                        <span>A Safer Community</span>
                    </div>
                    <div class="font-script text-2xl lg:text-3xl text-indigo-300 opacity-90 -rotate-3 select-none animate-float">
                        Security Redefined
                    </div>
                </div>

                <!-- Bottom Stats Row -->
                <div class="pt-2.5 border-t border-white/10 max-w-lg animate-fade-in-up delay-4">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-2">Trusted by modern communities</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="hover:scale-105 transition-transform duration-300 cursor-default">
                            <div class="text-xl lg:text-2xl font-extrabold text-white">100+</div>
                            <div class="text-[11px] text-slate-400 font-medium">Societies</div>
                        </div>
                        <div class="hover:scale-105 transition-transform duration-300 cursor-default">
                            <div class="text-xl lg:text-2xl font-extrabold text-white">50K+</div>
                            <div class="text-[11px] text-slate-400 font-medium">Residents</div>
                        </div>
                        <div class="hover:scale-105 transition-transform duration-300 cursor-default">
                            <div class="text-xl lg:text-2xl font-extrabold text-white">99.9%</div>
                            <div class="text-[11px] text-slate-400 font-medium">Uptime</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT LOGIN CARD COLUMN -->
            <div class="lg:col-span-5 flex justify-center lg:justify-end animate-fade-in-up delay-5">
                <div class="w-full max-w-[420px] bg-white rounded-[32px] p-6 sm:p-7 login-card-shadow text-slate-900 relative">
                    
                    <!-- Card Logo Header -->
                    <div class="text-center mb-5">
                        <div class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-purple-600 shadow-md shadow-indigo-500/20 mb-2 text-white">
                            <i class="fas fa-eye text-lg"></i>
                        </div>
                        <h2 class="text-xl font-bold tracking-tight text-slate-900">Welcome Back</h2>
                        <p class="text-xs text-slate-500 font-medium mt-0.5">Sign in with your EyeSense staff credentials</p>
                    </div>

                    <!-- Preset Client ID Ref Banner -->
                    <?php if (!empty($refBanner)): ?>
                    <div class="mb-4 p-3 rounded-2xl border border-indigo-200 bg-indigo-50 text-xs text-indigo-700 flex items-center gap-2">
                        <i class="fas fa-link text-indigo-500"></i>
                        <div>
                            <span class="font-bold">Login Preset:</span> Client ID <code class="px-1.5 py-0.5 rounded bg-indigo-100 font-mono font-bold"><?= $refBanner ?></code>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Success Registration Alert -->
                    <?php if (!empty($_GET['registered'])): ?>
                    <div class="mb-4 p-3 rounded-2xl border border-emerald-200 bg-emerald-50 text-xs text-emerald-700 flex items-center gap-2">
                        <i class="fas fa-circle-check text-base shrink-0 text-emerald-600"></i>
                        <span>Account created successfully! Please sign in below.</span>
                    </div>
                    <?php endif; ?>

                    <!-- Error Alert -->
                    <?php if ($error): ?>
                    <div class="mb-4 p-3 rounded-2xl border border-rose-200 bg-rose-50 text-xs text-rose-700 flex items-center gap-2">
                        <i class="fas fa-circle-exclamation text-base shrink-0 text-rose-600"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Form -->
                    <form method="POST" id="loginForm" onsubmit="handleLoginSubmit(event)" class="space-y-3.5">
                        
                        <!-- Employee ID or Email -->
                        <div class="space-y-1">
                            <label for="loginInput" class="block text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                                Employee ID or Email
                            </label>
                            <div class="relative flex items-center rounded-2xl styled-input-box px-3.5 py-2.5">
                                <i class="far fa-envelope text-slate-400 text-xs mr-3 shrink-0"></i>
                                <input type="text" id="loginInput" name="login" required autocomplete="username"
                                       class="w-full bg-transparent border-none text-xs text-slate-900 font-medium placeholder-slate-400 focus:outline-none"
                                       placeholder="amana2710@gmail.com"
                                       value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="space-y-1">
                            <label for="loginPassword" class="block text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                                Password
                            </label>
                            <div class="relative flex items-center rounded-2xl styled-input-box px-3.5 py-2.5">
                                <i class="fas fa-lock text-slate-400 text-xs mr-3 shrink-0"></i>
                                <input type="password" id="loginPassword" name="password" required autocomplete="current-password"
                                       class="w-full bg-transparent border-none text-xs text-slate-900 font-medium placeholder-slate-400 focus:outline-none pr-8"
                                       placeholder="••••••••">
                                <button type="button" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility"
                                        class="absolute right-3.5 text-slate-400 hover:text-indigo-600 transition-colors cursor-pointer bg-transparent border-none">
                                    <i id="passToggleIcon" class="far fa-eye-slash text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Checkbox & Forgot Password -->
                        <div class="flex items-center justify-between pt-0.5">
                            <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-600 select-none">
                                <input type="checkbox" name="remember" class="w-3.5 h-3.5 rounded text-blue-600 border-slate-300 focus:ring-blue-500 cursor-pointer" checked>
                                <span>Remember me</span>
                            </label>
                            <button type="button" onclick="openForgotModal()"
                                    class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition-colors cursor-pointer bg-transparent border-none">
                                Forgot password?
                            </button>
                        </div>

                        <!-- Sign In Button -->
                        <button type="submit" id="submitBtn"
                                class="btn-gradient-primary w-full py-3 px-4 rounded-2xl text-white font-bold text-xs tracking-wide cursor-pointer flex items-center justify-between transition-all">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-right-to-bracket text-xs"></i>
                                <span id="submitBtnText">Sign In</span>
                            </div>
                            <i class="fas fa-chevron-right text-[10px] opacity-80"></i>
                        </button>
                    </form>

                    <!-- Divider OR -->
                    <div class="relative my-4 text-center">
                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200"></div></div>
                        <span class="relative bg-white px-2.5 text-[10px] uppercase font-bold text-slate-400 tracking-wider">OR</span>
                    </div>

                    <!-- Create Account Button -->
                    <a href="../../signup.html"
                       class="w-full py-2.5 px-4 rounded-2xl border border-indigo-200 hover:border-indigo-400 bg-white hover:bg-slate-50 text-indigo-950 font-bold text-xs flex items-center justify-center gap-2 transition shadow-sm cursor-pointer no-underline hover:shadow-md hover:scale-[1.01]">
                        <i class="fas fa-user-plus text-indigo-600 text-xs"></i>
                        <span>Create Account</span>
                    </a>

                    <!-- Footer -->
                    <div class="mt-4 pt-3 border-t border-slate-100 text-center">
                        <p class="text-xs text-slate-500 font-medium">Powered by <span class="text-indigo-600 font-extrabold tracking-wide">INDSAC</span></p>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <!-- EyeSense Standard Shared Footer -->
    <?php
    $rootPath = '../../';
    require_once __DIR__ . '/../../includes/footer.php';
    ?>

    <!-- Forgot Password Modal -->
    <div id="forgotModal" style="display:none; position:fixed; inset:0; z-index:2000; background:var(--modal-backdrop); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); align-items:center; justify-content:center; padding:1rem;">
        <div style="background:#ffffff; border-radius:28px; padding:2rem; max-width:440px; width:100%; box-shadow:0 25px 60px rgba(15,23,42,0.3); position:relative; color:#0f172a;">

            <button onclick="closeForgotModal()" style="position:absolute;top:16px;right:16px;background:#f1f5f9;border:1px solid #e2e8f0;color:#64748b;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:all 0.2s;">×</button>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
                <h3 style="margin:0; font-weight:700; font-size:1.15rem; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-key" style="color:#2563eb;"></i>Reset Password
                </h3>
            </div>

            <!-- Step indicator -->
            <div style="display:flex; gap:8px; margin-bottom:1.5rem;">
                <div class="fp-step active" id="fpStep1"><span>1</span> Find Account</div>
                <div class="fp-step" id="fpStep2"><span>2</span> Verify OTP</div>
                <div class="fp-step" id="fpStep3"><span>3</span> New Password</div>
            </div>

            <div id="fpAlert" style="display:none; padding:10px 14px; border-radius:12px; font-size:13px; margin-bottom:14px;"></div>

            <!-- Step 1: Email -->
            <div id="fpPanel1">
                <p style="color:#64748b; font-size:13px; margin-bottom:16px; line-height:1.4;">Enter your registered email. We'll send your Client ID + a verification OTP.</p>
                <div style="margin-bottom:16px;">
                    <label style="font-size:11px; text-transform:uppercase; font-weight:700; letter-spacing:0.05em; color:#64748b; display:block; margin-bottom:6px;">Registered Email Address</label>
                    <input type="email" id="fp_email" style="width:100%; padding:11px 14px; background:#f0f4fd; border:1px solid #e2e8f0; border-radius:12px; color:#0f172a; font-size:14px; box-sizing:border-box; outline:none;" placeholder="you@company.com">
                </div>
                <button onclick="fpSendOtp()" id="fpSendBtn" class="btn-gradient-primary" style="width:100%; padding:12px; color:white; border:none; border-radius:12px; font-weight:700; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="fas fa-paper-plane"></i><span>Send OTP</span>
                </button>
            </div>

            <!-- Step 2: OTP -->
            <div id="fpPanel2" style="display:none;">
                <p style="color:#64748b; font-size:13px; margin-bottom:4px;">OTP sent to <strong id="fpMaskedEmail" style="color:#2563eb;"></strong></p>
                <p style="color:#10b981; font-size:12px; margin-bottom:16px; font-weight:600;"><i class="fas fa-id-badge mr-1"></i>Your Client ID: <strong id="fpClientIdDisplay" style="font-family:monospace;"></strong></p>
                <div style="margin-bottom:16px;">
                    <label style="font-size:11px; text-transform:uppercase; font-weight:700; letter-spacing:0.05em; color:#64748b; display:block; margin-bottom:6px;">Enter 6-Digit OTP</label>
                    <input type="text" id="fp_otp" maxlength="6" style="width:100%; padding:12px; background:#f0f4fd; border:1px solid #e2e8f0; border-radius:12px; color:#0f172a; font-size:26px; text-align:center; letter-spacing:10px; font-family:monospace; font-weight:700; box-sizing:border-box; outline:none;" placeholder="------" oninput="this.value=this.value.replace(/\D/g,'')">
                </div>
                <button onclick="fpVerifyOtp()" id="fpVerifyBtn" class="btn-gradient-primary" style="width:100%; padding:12px; color:white; border:none; border-radius:12px; font-weight:700; cursor:pointer; font-size:14px; margin-bottom:10px; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="fas fa-shield-alt"></i><span>Verify OTP</span>
                </button>
                <div style="display:flex; gap:8px;">
                    <button id="fpResendBtn" onclick="fpResendOtp()" style="flex:1; padding:10px; background:#f1f5f9; color:#2563eb; border:1px solid #2563eb; border-radius:10px; font-weight:600; cursor:pointer; font-size:12px; transition:opacity 0.4s ease; display:flex; align-items:center; justify-content:center; gap:4px;">
                        <i class="fas fa-rotate-right"></i><span>Resend OTP</span>
                    </button>
                    <button onclick="fpChangeEmail()" style="flex:1; padding:10px; background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; border-radius:10px; font-weight:600; cursor:pointer; font-size:12px; display:flex; align-items:center; justify-content:center; gap:4px;">
                        <i class="fas fa-envelope"></i><span>Change Email</span>
                    </button>
                </div>
            </div>

            <!-- Step 3: New Password -->
            <div id="fpPanel3" style="display:none;">
                <p style="color:#10b981; font-size:13px; margin-bottom:16px; font-weight:600;"><i class="fas fa-check-circle mr-1"></i>OTP verified! Set your new password below.</p>
                <div style="margin-bottom:14px;">
                    <label style="font-size:11px; text-transform:uppercase; font-weight:700; letter-spacing:0.05em; color:#64748b; display:block; margin-bottom:6px;">New Password (min 8 chars)</label>
                    <input type="password" id="fp_newpass" style="width:100%; padding:11px 14px; background:#f0f4fd; border:1px solid #e2e8f0; border-radius:12px; color:#0f172a; font-size:14px; box-sizing:border-box; outline:none;" placeholder="••••••••">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-size:11px; text-transform:uppercase; font-weight:700; letter-spacing:0.05em; color:#64748b; display:block; margin-bottom:6px;">Confirm Password</label>
                    <input type="password" id="fp_confirm" style="width:100%; padding:11px 14px; background:#f0f4fd; border:1px solid #e2e8f0; border-radius:12px; color:#0f172a; font-size:14px; box-sizing:border-box; outline:none;" placeholder="••••••••">
                </div>
                <button onclick="fpResetPassword()" id="fpResetBtn" class="btn-gradient-primary" style="width:100%; padding:12px; background:#10b981; color:white; border:none; border-radius:12px; font-weight:700; cursor:pointer; font-size:14px; box-shadow:0 4px 15px rgba(16,185,129,0.3); display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="fas fa-check"></i><span>Reset Password</span>
                </button>
            </div>

        </div>
    </div>

    <!-- JavaScript Handlers -->
    <script>
        function togglePasswordVisibility() {
            const passInput = document.getElementById('loginPassword');
            const toggleIcon = document.getElementById('passToggleIcon');
            toggleIcon.style.transform = 'scale(1.2) rotate(180deg)';
            setTimeout(() => {
                if (passInput.type === 'password') {
                    passInput.type = 'text';
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                } else {
                    passInput.type = 'password';
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                }
                toggleIcon.style.transform = 'scale(1) rotate(0deg)';
            }, 150);
        }

        function handleLoginSubmit(event) {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.classList.add('opacity-85', 'cursor-not-allowed');
            btn.innerHTML = '<div class="flex items-center gap-2"><i class="fas fa-circle-notch fa-spin text-sm"></i><span>Signing in...</span></div>';
        }

        /* ── Forgot Password Logic ── */
        let fpState = { client_id:'', employee_id:'', email:'' };
        let resendCooldown = 0;
        let resendInterval = null;

        function openForgotModal() {
            document.getElementById('forgotModal').style.display = 'flex';
            fpGoStep(1);
        }
        function closeForgotModal() {
            document.getElementById('forgotModal').style.display = 'none';
            fpState = { client_id:'', employee_id:'', email:'' };
            document.getElementById('fp_email').value   = '';
            document.getElementById('fp_otp').value     = '';
            document.getElementById('fp_newpass').value = '';
            document.getElementById('fp_confirm').value = '';
            fpResetResendBtn();
            if (resendInterval) { clearInterval(resendInterval); resendInterval = null; }
            resendCooldown = 0;
            fpGoStep(1);
        }

        function fpGoStep(n) {
            [1,2,3].forEach(i => {
                document.getElementById('fpPanel'+i).style.display = i===n ? 'block' : 'none';
                document.getElementById('fpStep'+i).className =
                    'fp-step' + (i===n ? ' active' : (i<n ? ' done' : ''));
            });
            document.getElementById('fpAlert').style.display = 'none';
        }

        function fpAlert(msg, type) {
            const el = document.getElementById('fpAlert');
            el.style.display = 'block';
            el.style.background = type==='error' ? 'rgba(239,68,68,0.12)' : 'rgba(16,185,129,0.12)';
            el.style.color      = type==='error' ? '#ef4444' : '#10b981';
            el.style.border     = '1px solid ' + (type==='error' ? 'rgba(239,68,68,0.3)' : 'rgba(16,185,129,0.3)');
            el.innerHTML = '<i class="fas fa-'+(type==='error'?'exclamation-triangle':'check-circle')+' mr-2"></i>' + msg;
        }

        function fpResetResendBtn() {
            const btn = document.getElementById('fpResendBtn');
            if (!btn) return;
            btn.disabled          = false;
            btn.style.opacity     = '1';
            btn.style.pointerEvents = 'auto';
            btn.style.cursor      = 'pointer';
            btn.innerHTML         = '<i class="fas fa-rotate-right mr-1"></i>Resend OTP';
        }

        function startResendCooldown() {
            if (resendInterval) clearInterval(resendInterval);
            resendCooldown = 60;
            const btn = document.getElementById('fpResendBtn');
            if (!btn) return;

            btn.disabled          = true;
            btn.style.opacity     = '0.45';
            btn.style.pointerEvents = 'none';
            btn.style.cursor      = 'not-allowed';
            btn.innerHTML         = `<i class="fas fa-clock mr-1"></i>Resend in ${resendCooldown}s`;

            resendInterval = setInterval(() => {
                resendCooldown--;
                if (resendCooldown <= 0) {
                    clearInterval(resendInterval);
                    fpResetResendBtn();
                } else {
                    btn.innerHTML = `<i class="fas fa-clock mr-1"></i>Resend in ${resendCooldown}s`;
                }
            }, 1000);
        }

        function fpChangeEmail() {
            if (resendInterval) { clearInterval(resendInterval); resendInterval = null; }
            resendCooldown = 0;
            fpResetResendBtn();
            fpGoStep(1);
            document.getElementById('fp_otp').value = '';
        }

        async function fpSendOtp() {
            const email = document.getElementById('fp_email').value.trim();
            if (!email) return fpAlert('Please enter your email', 'error');
            const btn = document.getElementById('fpSendBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';

            try {
                const res = await fetch('../forgot_password_api.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'send_otp_by_email', email })
                });
                const d = await res.json();

                if (d.error) {
                    fpAlert(d.error, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send OTP';
                    return;
                }

                fpState.client_id   = d.client_id   || '';
                fpState.employee_id = d.employee_id || '';
                fpState.email       = email;
                
                document.getElementById('fpMaskedEmail').textContent = d.masked_email || email;
                const cidDisplay = (d.client_id && d.client_id !== 'PUBLIC') ? d.client_id : (d.client_id === 'PUBLIC' ? 'Public Account' : '');
                document.getElementById('fpClientIdDisplay').textContent = cidDisplay;
                fpGoStep(2);
                fpAlert('OTP sent! Please check your email.', 'success');
                startResendCooldown();

            } catch(e) {
                fpAlert('Network error. Please try again.', 'error');
            } finally {
                btn.disabled  = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send OTP';
            }
        }

        async function fpResendOtp() {
            if (resendCooldown > 0) return;
            const email = fpState.email;
            if (!email) return fpAlert('Please go back and enter your email.', 'error');

            const btn = document.getElementById('fpResendBtn');
            btn.disabled      = true;
            btn.style.opacity = '0.45';
            btn.innerHTML     = '<i class="fas fa-spinner fa-spin mr-1"></i>Sending...';

            try {
                const res = await fetch('../forgot_password_api.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'send_otp_by_email', email })
                });
                const d = await res.json();
                if (d.error) throw new Error(d.error);
                fpState.client_id   = d.client_id   || fpState.client_id;
                fpState.employee_id = d.employee_id || fpState.employee_id;
                document.getElementById('fpMaskedEmail').textContent = d.masked_email || email;
                const cidDisplay = (fpState.client_id && fpState.client_id !== 'PUBLIC') ? fpState.client_id : (fpState.client_id === 'PUBLIC' ? 'Public Account' : '');
                document.getElementById('fpClientIdDisplay').textContent = cidDisplay;
                fpAlert('New OTP sent successfully!', 'success');
            } catch(e) {
                fpAlert(e.message, 'error');
            } finally {
                startResendCooldown();
            }
        }

        async function fpVerifyOtp() {
            const otp = document.getElementById('fp_otp').value.trim();
            if (otp.length !== 6) return fpAlert('Enter the 6-digit OTP', 'error');
            
            if (!fpState.employee_id && !fpState.client_id && !fpState.email) {
                fpAlert('Session expired. Please request OTP again.', 'error');
                fpGoStep(1);
                return;
            }
            
            const btn = document.getElementById('fpVerifyBtn');
            btn.disabled = true; 
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verifying...';
            
            try {
                const res = await fetch('../forgot_password_api.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'verify_otp', 
                        client_id: fpState.client_id, 
                        employee_id: fpState.employee_id, 
                        otp: otp 
                    })
                });
                const d = await res.json();
                if (d.error) throw new Error(d.error);
                if (d.client_id) fpState.client_id = d.client_id;
                if (d.employee_id) fpState.employee_id = d.employee_id;
                if (resendInterval) { clearInterval(resendInterval); resendInterval = null; }
                fpGoStep(3);
                fpAlert('OTP verified successfully!', 'success');
            } catch(e) { 
                fpAlert(e.message, 'error'); 
            } finally { 
                btn.disabled = false; 
                btn.innerHTML = '<i class="fas fa-shield-alt mr-2"></i>Verify OTP'; 
            }
        }

        async function fpResetPassword() {
            const np = document.getElementById('fp_newpass').value;
            const cp = document.getElementById('fp_confirm').value;
            if (np.length < 8) return fpAlert('Password must be at least 8 characters', 'error');
            if (np !== cp)     return fpAlert('Passwords do not match', 'error');
            
            if (!fpState.employee_id && !fpState.client_id && !fpState.email) {
                fpAlert('Session expired. Please start over.', 'error');
                fpGoStep(1);
                return;
            }
            
            const btn = document.getElementById('fpResetBtn');
            btn.disabled = true; 
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Resetting...';
            
            try {
                const res = await fetch('../forgot_password_api.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'reset_password', 
                        client_id: fpState.client_id,
                        employee_id: fpState.employee_id, 
                        new_password: np, 
                        confirm_password: cp 
                    })
                });
                const d = await res.json();
                if (d.error) throw new Error(d.error);
                fpAlert('Password reset successfully! Redirecting...', 'success');
                setTimeout(() => { 
                    closeForgotModal(); 
                    location.reload(); 
                }, 2000);
            } catch(e) { 
                fpAlert(e.message, 'error'); 
            } finally { 
                btn.disabled = false; 
                btn.innerHTML = '<i class="fas fa-check mr-2"></i>Reset Password'; 
            }
        }

        document.getElementById('forgotModal').addEventListener('click', e => {
            if (e.target.id === 'forgotModal') closeForgotModal();
        });

        document.getElementById('fp_otp').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') fpVerifyOtp();
        });

        document.getElementById('fp_confirm').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') fpResetPassword();
        });
    </script>
</body>
</html>