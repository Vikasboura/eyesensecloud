<?php
/**
 * EyeSense Cloud Portal — Post-Login Society Selection Screen
 * Shown when an authenticated user has access to multiple societies.
 */
require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
require_once __DIR__ . '/../jwt_helper.php';
require_once __DIR__ . '/../society_context_holder.php';

portal_session_start();
$theme = $_SESSION['portal_theme'] ?? 'light';

// If already fully authenticated and active, redirect to dashboard
if (!empty($_SESSION['portal_client_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Ensure user has valid pending available societies from login
$availableSocieties = $_SESSION['portal_available_societies'] ?? [];
if (empty($availableSocieties) || !is_array($availableSocieties) || count($availableSocieties) <= 1) {
    header("Location: login.php");
    exit;
}

$error = '';

// Handle selection submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedSocietyId = filter_var($_POST['society_id'] ?? null, FILTER_VALIDATE_INT);
    $rememberDefault = !empty($_POST['remember_default']);

    // Find the matching society from session
    $chosenMatch = null;
    foreach ($availableSocieties as $item) {
        $sid = (int)($item['society_id'] ?? 0);
        if ($sid === $selectedSocietyId) {
            $chosenMatch = $item;
            break;
        }
    }

    if (!$chosenMatch) {
        $error = 'Please select a valid society from the list.';
    } else {
        try {
            $pdo = get_indsac_db();
            $globalUserId = !empty($chosenMatch['global_user_id']) ? (int)$chosenMatch['global_user_id'] : 0;
            $societyId = (int)$chosenMatch['society_id'];

            // Persist default society if requested
            if ($globalUserId > 0 && $societyId > 0) {
                if ($rememberDefault) {
                    try {
                        $clearStmt = $pdo->prepare("UPDATE user_society_mapping SET is_default = 0 WHERE user_id = ?");
                        $clearStmt->execute([$globalUserId]);
                        $setStmt = $pdo->prepare("UPDATE user_society_mapping SET is_default = 1 WHERE user_id = ? AND society_id = ?");
                        $setStmt->execute([$globalUserId, $societyId]);
                    } catch (Throwable $e) {}
                }

                // Always track last active society
                try {
                    $updateLastActive = $pdo->prepare("UPDATE users SET last_active_society_id = ? WHERE id = ?");
                    $updateLastActive->execute([$societyId, $globalUserId]);
                } catch (Throwable $e) {}
            }

            // Set active session variables
            $_SESSION['portal_client_id']          = $chosenMatch['client_id'];
            $_SESSION['portal_current_society_id'] = $chosenMatch['society_id'] ?? null;
            $_SESSION['portal_user_id']            = $chosenMatch['user_id'];
            $_SESSION['portal_employee_id']        = $chosenMatch['employee_id'];
            $_SESSION['portal_username']           = $chosenMatch['full_name'];
            $_SESSION['portal_role']               = $chosenMatch['role'];
            $_SESSION['portal_is_superadmin']      = $chosenMatch['is_superadmin'];

            $userId   = (int)$chosenMatch['user_id'];
            $role     = (string)($chosenMatch['role'] ?: 'Unassigned');
            $clientId = (string)$chosenMatch['client_id'];

            if ($societyId > 0) {
                $jwtToken = jwt_issue_society_token($userId, $societyId, $role);
                $_SESSION['portal_jwt_token'] = $jwtToken;
            }

            SocietyContextHolder::set($userId, $societyId, $role, $clientId);

            // Fetch hostel management status
            try {
                $hmStmt = $pdo->prepare("SELECT hostel_management_enabled FROM clients WHERE client_id = ?");
                $hmStmt->execute([$chosenMatch['client_id']]);
                $_SESSION['hostel_management_enabled'] = (int)$hmStmt->fetchColumn();
            } catch (Throwable $e) {
                $_SESSION['hostel_management_enabled'] = 0;
            }
            $_SESSION['portal_cameras'] = [];

            if ($chosenMatch['type'] === 'tenant') {
                $_SESSION['portal_tenant_id'] = $chosenMatch['tenant_id'];
                $_SESSION['portal_member_id'] = $chosenMatch['member_id'];
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
                $prefStmt->execute([$chosenMatch['client_id'], $chosenMatch['user_id']]);
                if ($pref = $prefStmt->fetchColumn()) {
                    $_SESSION['portal_theme'] = $pref;
                }
            } catch (Throwable $e) {}

            if ($chosenMatch['type'] === 'employee') {
                logAudit($pdo, $chosenMatch['client_id'], $chosenMatch['employee_id'], 'LOGIN', 'Portal login successful (society selected: ' . ($chosenMatch['society_name'] ?? 'ID ' . $societyId) . ')');
                logger_info('User login successful after society selection', [
                    'event_id' => bin2hex(random_bytes(8)),
                    'user_id' => $chosenMatch['user_id'],
                    'client_id' => $chosenMatch['client_id'],
                    'society_id' => $societyId,
                    'role' => $chosenMatch['role'],
                    'ip' => getClientIp(),
                ], __FILE__, __LINE__);
                $ip = getClientIp();
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                try {
                    portalEnsureLoginSessionActivityColumn($pdo);
                    portalCloseOtherLoginSessions($pdo, $chosenMatch['client_id'], $chosenMatch['employee_id']);
                    $lStmt = $pdo->prepare("
                        INSERT INTO portal_login_sessions
                          (client_id, employee_id, full_name, role, ip_address, user_agent, last_seen_at, is_active)
                        VALUES (?,?,?,?,?,?,NOW(),1)
                    ");
                    $lStmt->execute([
                        $chosenMatch['client_id'], $chosenMatch['employee_id'],
                        $chosenMatch['full_name'], $chosenMatch['role'] ?: 'Unassigned',
                        $ip, $ua
                    ]);
                    $_SESSION['portal_login_session_id'] = (int)$pdo->lastInsertId();
                } catch (Throwable $e) {}
            } else {
                logAudit($pdo, $chosenMatch['client_id'], $chosenMatch['employee_id'], 'LOGIN', 'Tenant portal login successful (society selected)');
                logger_info('Tenant login successful after society selection', [
                    'event_id' => bin2hex(random_bytes(8)),
                    'user_id' => $chosenMatch['user_id'],
                    'client_id' => $chosenMatch['client_id'],
                    'society_id' => $societyId,
                    'ip' => getClientIp(),
                ], __FILE__, __LINE__);
            }

            if ($chosenMatch['type'] === 'tenant') {
                header("Location: tenant_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;

        } catch (Throwable $e) {
            $error = 'Failed to activate society session. Please try again.';
        }
    }
}

// Find pre-selected item (first or marked default)
$preselectedId = 0;
foreach ($availableSocieties as $s) {
    if (!empty($s['is_default'])) {
        $preselectedId = (int)$s['society_id'];
        break;
    }
}
if (!$preselectedId && !empty($availableSocieties[0]['society_id'])) {
    $preselectedId = (int)$availableSocieties[0]['society_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Society | EyeSense Cloud Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        bg: '#f9fafb', surface: '#ffffff', surfaceLight: '#f3f4f6',
                        primary: '#6366f1', accent: '#a855f7',
                        success: '#10b981', warning: '#f59e0b', danger: '#ef4444',
                        textMain: '#111827', textSec: '#6b7280'
                    },
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
            --border: #e5e7eb;
            --card-bg: #ffffff;
            --card-hover: #f8fafc;
            --card-active: #eef2ff;
            --card-border-active: #6366f1;
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
            --card-bg: #1f2937;
            --card-hover: #283548;
            --card-active: rgba(99, 102, 241, 0.15);
            --card-border-active: #818cf8;
        }
        body { background-color: var(--bg); color: var(--textMain); font-family: 'Outfit', sans-serif; }
        .glass-panel { background: var(--glassBg, rgba(255,255,255,0.95)); backdrop-filter: blur(12px); border: 1px solid var(--glassBorder, rgba(0,0,0,0.08)); box-shadow: 0 10px 40px rgba(0,0,0,0.06); }
        .society-card {
            border: 1.5px solid var(--border);
            background: var(--card-bg);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        .society-card:hover {
            background: var(--card-hover);
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }
        .society-card.selected {
            background: var(--card-active);
            border-color: var(--card-border-active);
            box-shadow: 0 0 0 1px var(--card-border-active);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4" data-theme="<?= htmlspecialchars($theme) ?>">

    <div class="glass-panel rounded-2xl w-full max-w-lg p-8 border border-border">
        <!-- Logo & Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center gap-3 mb-2">
                <i class="fas fa-building text-primary text-3xl"></i>
                <div class="text-left">
                    <div class="font-bold text-xl tracking-wide">EyeSense</div>
                    <div class="text-xs text-textSec -mt-0.5">Cloud Portal</div>
                </div>
            </div>
            <h1 class="text-xl font-bold mt-3 text-textMain">Select Society</h1>
            <p class="text-xs text-textSec mt-1">Your account is associated with multiple societies. Choose where you want to work.</p>
        </div>

        <?php if ($error): ?>
        <div class="mb-5 p-3 rounded-lg border border-red-900/40 bg-red-900/10 text-sm text-red-400 flex items-center gap-2">
            <i class="fas fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4" id="societySelectForm">

            <div class="space-y-3 max-h-[360px] overflow-y-auto pr-1">
                <?php foreach ($availableSocieties as $index => $item):
                    $sid = (int)($item['society_id'] ?? 0);
                    $sName = htmlspecialchars($item['society_name'] ?? 'Society #' . $sid);
                    $sCode = htmlspecialchars($item['society_code'] ?? $item['client_id'] ?? '');
                    $sType = htmlspecialchars($item['society_type'] ?? '');
                    $sRole = htmlspecialchars($item['role'] ?? 'Staff');
                    $isDefault = !empty($item['is_default']);
                    $isChecked = ($sid === $preselectedId);
                ?>
                <label class="society-card rounded-xl p-4 flex items-center justify-between block <?= $isChecked ? 'selected' : '' ?>" onclick="selectSocietyOption(this)">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-10 h-10 rounded-lg bg-indigo-500/10 text-primary flex items-center justify-center font-bold text-base shrink-0">
                            <i class="fas fa-city"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm text-textMain truncate"><?= $sName ?></span>
                                <?php if ($isDefault): ?>
                                <span class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-500 border border-amber-500/20">Default</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-textSec flex items-center gap-2 mt-0.5 flex-wrap">
                                <?php if ($sCode): ?>
                                <span><i class="fas fa-hashtag text-[10px] opacity-70"></i> <?= $sCode ?></span>
                                <?php endif; ?>
                                <?php if ($sType): ?>
                                <span>• <?= $sType ?></span>
                                <?php endif; ?>
                                <span class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-medium">
                                    <?= $sRole ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="shrink-0 pl-3">
                        <input type="radio" name="society_id" value="<?= $sid ?>" <?= $isChecked ? 'checked' : '' ?> class="w-4 h-4 text-primary focus:ring-primary focus:ring-offset-0 border-gray-300">
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer text-xs text-textSec select-none">
                    <input type="checkbox" name="remember_default" value="1" class="rounded text-primary focus:ring-primary border-gray-300">
                    <span>Remember my default society for future logins</span>
                </label>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 rounded-lg bg-primary hover:bg-indigo-500 text-white font-bold text-sm transition cursor-pointer shadow-lg shadow-indigo-500/20 flex items-center justify-center gap-2">
                    <span>Continue to Dashboard</span>
                    <i class="fas fa-arrow-right text-xs"></i>
                </button>
            </div>
        </form>

        <div class="mt-5 text-center">
            <a href="login.php?logout=1" onclick="sessionStorage.clear();" class="text-xs text-textSec hover:text-textMain transition inline-flex items-center gap-1.5">
                <i class="fas fa-arrow-left text-[10px]"></i>
                <span>Sign in with a different account</span>
            </a>
        </div>

        <div class="mt-6 pt-4 border-t border-border text-center">
            <p class="text-xs text-textSec">Powered by <span class="text-primary font-bold">INDSAC</span></p>
        </div>
    </div>

    <script>
        function selectSocietyOption(cardElement) {
            document.querySelectorAll('.society-card').forEach(card => card.classList.remove('selected'));
            cardElement.classList.add('selected');
            const radio = cardElement.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        }
    </script>
</body>
</html>
