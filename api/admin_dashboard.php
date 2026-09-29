<?php
/**
 * ============================================
 * INDSAC ADMIN DASHBOARD
 * ============================================
 * A protected dashboard for INDSAC staff to:
 *   - View registered clients
 *   - Manage licensing plans (add/edit/enable/disable)
 *
 * Authentication: Hardcoded password (Indsac#1914)
 */

session_start();
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';

$ADMIN_PASSWORD = 'Indsac#1914';

// Handle login
if (isset($_POST['admin_login'])) {
    if ($_POST['password'] === $ADMIN_PASSWORD) {
        $_SESSION['indsac_admin'] = true;
    } else {
        $login_error = 'Invalid password.';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['indsac_admin']);
    header('Location: admin_dashboard.php');
    exit;
}

$is_authed = !empty($_SESSION['indsac_admin']);
$db_error = '';

// ── Handle plan actions (only if authenticated) ──
$action_msg = '';
if (!empty($_SESSION['action_msg'])) {
    $action_msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}
if ($is_authed) {
    try {
        $db = get_license_db();
    } catch (Throwable $e) {
        if (!extension_loaded('pdo_mysql')) {
            $db_error = 'Database driver missing or not configured. Please enable PDO MySQL in PHP to use the admin dashboard.';
        } else {
            $db_error = 'Database connection failed: ' . $e->getMessage();
        }
    }

    // Add new plan
    if (!$db_error && isset($_POST['add_plan'])) {
        $code  = strtoupper(trim($_POST['plan_code'] ?? ''));
        $name  = trim($_POST['name'] ?? '');
        $days  = (int)($_POST['days'] ?? 30);
        $price = (float)($_POST['price'] ?? 0);
        $cam   = (int)($_POST['camera_limit'] ?? 0);

        if ($code && $name && $days > 0) {
            try {
                $stmt = $db->prepare("INSERT INTO plans (plan_code, name, days, price, camera_limit) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$code, $name, $days, $price, $cam]);
                $action_msg = "SUCCESS: Plan '{$name}' added successfully.";
            } catch (PDOException $e) {
                $action_msg = "ERROR: " . $e->getMessage();
            }
        } else {
            $action_msg = "ERROR: Please fill in Code, Name, and Days.";
        }
    }

    // Update existing plan
    if (!$db_error && isset($_POST['update_plan'])) {
        $id    = (int)$_POST['plan_id'];
        $name  = trim($_POST['name'] ?? '');
        $days  = (int)($_POST['days'] ?? 30);
        $price = (float)($_POST['price'] ?? 0);
        $cam   = (int)($_POST['camera_limit'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $db->prepare("UPDATE plans SET name=?, days=?, price=?, camera_limit=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $days, $price, $cam, $active, $id]);
        $action_msg = "SUCCESS: Plan updated.";
    }

    // Toggle plan active status
    if (!$db_error && isset($_GET['toggle'])) {
        $id = (int)$_GET['toggle'];
        $db->exec("UPDATE plans SET is_active = NOT is_active WHERE id = {$id}");
        header('Location: admin_dashboard.php');
        exit;
    }

    // ── Client management: suspend / reactivate / delete / toggle_hostel ──────────
    if (!$db_error && isset($_GET['client_action']) && isset($_GET['cid'])) {
        $pdo        = get_indsac_db();
        $action_cid = trim($_GET['cid']);
        $ca         = trim($_GET['client_action']);

        $chkC = $db->prepare("SELECT * FROM clients WHERE client_id = ?");
        $chkC->execute([$action_cid]);
        $cRow = $chkC->fetch();

        if ($cRow) {
            $socStmt = $pdo->prepare("SELECT id FROM society WHERE legacy_client_id = ? LIMIT 1");
            $socStmt->execute([$action_cid]);
            $action_sid = (int)$socStmt->fetchColumn() ?: null;

            if ($ca === 'suspend') {
                $pdo->prepare("UPDATE employees SET status='inactive' WHERE client_id=?")->execute([$action_cid]);
                if ($action_sid) {
                    $pdo->prepare("UPDATE society SET status='SUSPENDED' WHERE id=?")->execute([$action_sid]);
                }
                $pdo->prepare("UPDATE clients SET status='suspended' WHERE client_id=?")->execute([$action_cid]);

                $licStmt = $db->prepare("SELECT id FROM licenses WHERE (society_id IS NOT NULL AND society_id=?) OR legacy_client_id=? OR (society_id IS NULL AND legacy_client_id IS NULL AND sa_email=?) ORDER BY id DESC LIMIT 1");
                $licStmt->execute([$action_sid, $action_cid, $cRow['email']]);
                $licId = $licStmt->fetchColumn();

                if ($licId) {
                    $db->prepare("UPDATE licenses SET status='suspended' WHERE id=?")->execute([$licId]);
                } else {
                    $start  = date('Y-m-d');
                    $expiry = date('Y-m-d', strtotime('+30 days'));
                    $licKey = hash('sha256', $action_cid . microtime());
                    $mid    = !empty($cRow['machine_id']) ? $cRow['machine_id'] : ('MID-' . strtoupper(substr(md5($action_cid), 0, 10)));
                    $db->prepare("INSERT INTO licenses (society_id, legacy_client_id, machine_id, plan, start_date, expiry_date, auto_renew, sa_name, sa_email, sa_phone, payment_id, payment_status, amount, license_key_hash, status)
                                  VALUES (?, ?, ?, 'PRO_1M', ?, ?, 0, ?, ?, ?, 'MANUAL-SUSPEND', 'completed', 0.00, ?, 'suspended')")
                       ->execute([$action_sid, $action_cid, $mid, $start, $expiry, trim($cRow['first_name'] . ' ' . $cRow['last_name']), $cRow['email'], $cRow['mobile'] ?: '', $licKey]);
                }
                $_SESSION['action_msg'] = "WARNING: Client {$action_cid} suspended.";

            } elseif ($ca === 'reactivate') {
                $pdo->prepare("UPDATE employees SET status='active' WHERE client_id=?")->execute([$action_cid]);
                if ($action_sid) {
                    $pdo->prepare("UPDATE society SET status='ACTIVE' WHERE id=?")->execute([$action_sid]);
                }
                $pdo->prepare("UPDATE clients SET status='active' WHERE client_id=?")->execute([$action_cid]);

                $licStmt = $db->prepare("SELECT id, expiry_date FROM licenses WHERE (society_id IS NOT NULL AND society_id=?) OR legacy_client_id=? OR (society_id IS NULL AND legacy_client_id IS NULL AND sa_email=?) ORDER BY id DESC LIMIT 1");
                $licStmt->execute([$action_sid, $action_cid, $cRow['email']]);
                $licData = $licStmt->fetch();

                if ($licData) {
                    $exp = $licData['expiry_date'];
                    if (empty($exp) || strtotime($exp) < time()) {
                        $newExp = date('Y-m-d', strtotime('+30 days'));
                        $db->prepare("UPDATE licenses SET status='active', expiry_date=? WHERE id=?")->execute([$newExp, $licData['id']]);
                    } else {
                        $db->prepare("UPDATE licenses SET status='active' WHERE id=?")->execute([$licData['id']]);
                    }
                } else {
                    $start  = date('Y-m-d');
                    $expiry = date('Y-m-d', strtotime('+30 days'));
                    $licKey = hash('sha256', $action_cid . microtime());
                    $mid    = !empty($cRow['machine_id']) ? $cRow['machine_id'] : ('MID-' . strtoupper(substr(md5($action_cid), 0, 10)));
                    $db->prepare("INSERT INTO licenses (society_id, legacy_client_id, machine_id, plan, start_date, expiry_date, auto_renew, sa_name, sa_email, sa_phone, payment_id, payment_status, amount, license_key_hash, status)
                                  VALUES (?, ?, ?, 'PRO_1M', ?, ?, 0, ?, ?, ?, 'MANUAL-ACTIVATE', 'completed', 0.00, ?, 'active')")
                       ->execute([$action_sid, $action_cid, $mid, $start, $expiry, trim($cRow['first_name'] . ' ' . $cRow['last_name']), $cRow['email'], $cRow['mobile'] ?: '', $licKey]);
                }
                $_SESSION['action_msg'] = "SUCCESS: Client {$action_cid} activated.";

            } elseif ($ca === 'delete') {
                $pdo->prepare("DELETE FROM employees WHERE client_id=?")->execute([$action_cid]);
                $db->prepare("UPDATE licenses SET status='revoked' WHERE (society_id IS NOT NULL AND society_id=?) OR legacy_client_id=? OR (society_id IS NULL AND legacy_client_id IS NULL AND sa_email=?)")
                   ->execute([$action_sid, $action_cid, $cRow['email']]);
                $db->prepare("DELETE FROM clients WHERE client_id=?")->execute([$action_cid]);
                $_SESSION['action_msg'] = "TRASH: Client {$action_cid} deleted and license revoked.";

            } elseif ($ca === 'toggle_hostel') {
                $current_status = (int)($cRow['hostel_management_enabled'] ?? 0);
                $new_status = $current_status ? 0 : 1;
                $pdo->prepare("UPDATE clients SET hostel_management_enabled = ? WHERE client_id = ?")->execute([$new_status, $action_cid]);
                $_SESSION['action_msg'] = "SUCCESS: Hostel Management has been " . ($new_status ? 'ENABLED' : 'DISABLED') . " for {$action_cid}.";
            }
        } else {
            $_SESSION['action_msg'] = "ERROR: Client ID not found: {$action_cid}";
        }
        header('Location: admin_dashboard.php');
        exit;
    }



    // ── Register new client — pure HTML POST, no AJAX ─────────────
    if (!$db_error && isset($_POST['register_client'])) {
        $pdo = get_indsac_db();

        $rc_first   = trim($_POST['rc_first_name'] ?? '');
        $rc_last    = trim($_POST['rc_last_name'] ?? '');
        $rc_society = trim($_POST['rc_society_name'] ?? '');
        $rc_email   = strtolower(trim($_POST['rc_email'] ?? ''));
        $rc_mobile  = trim($_POST['rc_mobile'] ?? '');
        $rc_user    = trim($_POST['rc_username'] ?? '');
        $rc_machine = trim($_POST['rc_machine_id'] ?? '');
        $rc_plan    = trim($_POST['rc_plan'] ?? '');
        $rc_pass    = trim($_POST['rc_password'] ?? '');

        if (!$rc_first || !$rc_society || !$rc_email || !filter_var($rc_email, FILTER_VALIDATE_EMAIL) || !$rc_pass) {
            $action_msg = "ERROR: Society name, first name, valid email and password are required.";
        } else {
            // Check duplicate email
            $chk = $db->prepare("SELECT client_id FROM clients WHERE email = ?");
            $chk->execute([$rc_email]);
            $existing = $chk->fetch();

            if ($existing) {
                $action_msg = "WARNING: Email already registered — Client ID: " . $existing['client_id'];
            } else {
                // 1. Create client record
                $new_cid    = generate_client_id($db);
                $employeeId = $rc_user ?: ($new_cid . '-SA');
                $fullName   = trim($rc_first . ' ' . $rc_last);

                $db->prepare("INSERT INTO clients (client_id, first_name, last_name, email, mobile, machine_id, sa_username)
                               VALUES (?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$new_cid, $rc_first, $rc_last, $rc_email, $rc_mobile, $rc_machine, $employeeId]);

                // 2. Create superadmin employee so client can login to portal
                $pwHash = generate_werkzeug_password($rc_pass);

                // Deduplicate employee_id within this client
                $chkEmp = $pdo->prepare("SELECT id FROM employees WHERE client_id=? AND employee_id=?");
                $chkEmp->execute([$new_cid, $employeeId]);
                if ($chkEmp->fetch()) {
                    $employeeId .= '-' . strtoupper(substr(md5(microtime()), 0, 4));
                }

                $pdo->prepare("INSERT INTO employees
                                   (client_id, employee_id, full_name, email, phone, role,
                                    access_level, password_hash, status, created_by)
                               VALUES (?, ?, ?, ?, ?, 'superadmin', 'ADMIN', ?, 'active', 'INDSAC-ADMIN')")
                    ->execute([$new_cid, $employeeId, $fullName, $rc_email, $rc_mobile, $pwHash]);

                $pdo->prepare("INSERT INTO maintenance_settings (client_id, society_name, updated_by) VALUES (?, ?, ?)")
                    ->execute([$new_cid, $rc_society, 'INDSAC-ADMIN']);

                // Ensure society row exists for this client_id
                $socCheck = $pdo->prepare("SELECT id FROM society WHERE legacy_client_id = ? LIMIT 1");
                $socCheck->execute([$new_cid]);
                $new_sid = (int)$socCheck->fetchColumn() ?: null;
                if (!$new_sid) {
                    try {
                        $socCode = 'SOC-' . strtoupper(substr(hash('sha256', $new_cid . microtime()), 0, 6));
                        $pdo->prepare("
                            INSERT INTO society (society_code, society_name, society_type, address, city, legacy_client_id, status)
                            VALUES (?, ?, 'RESIDENTIAL_SOCIETY', 'N/A', 'N/A', ?, 'ACTIVE')
                        ")->execute([$socCode, $rc_society, $new_cid]);
                        $new_sid = (int)$pdo->lastInsertId();
                    } catch (Throwable $e) {}
                }

                // 4. Auto-assign license if plan + machine ID given
                $licNote = '';
                if ($rc_plan && $rc_machine) {
                    $planRow = $db->prepare("SELECT * FROM plans WHERE plan_code=? AND is_active=1");
                    $planRow->execute([$rc_plan]);
                    $pd = $planRow->fetch();
                    if ($pd) {
                        $start  = date('Y-m-d');
                        $expiry = date('Y-m-d', strtotime("+{$pd['days']} days"));
                        $licKey = hash('sha256', $new_cid . $rc_machine . $rc_plan . microtime());
                        insert_license_record($db, $rc_machine, $rc_plan, $start, $expiry, false,
                            $fullName, $rc_email, $rc_mobile,
                            'MANUAL-' . strtoupper(substr($new_cid, -6)),
                            (string)$pd['price'], $licKey,
                            $new_sid, $new_cid);
                        $licNote = " | Plan: {$pd['name']} | Expires: {$expiry}";
                    }
                } elseif ($rc_plan && !$rc_machine) {
                    $licNote = " (no Machine ID — license pending)";
                }

                // 5. Fire-and-forget welcome email (ignore errors so registration never blocks)
                try {
                    require_once __DIR__ . '/maintenance_notify.php';
                    $portalUrl = DB_CFG_PORTAL_LOGIN_URL;
                    $emailHtml = "<!DOCTYPE html><html><body style='font-family:sans-serif;background:#f5f5f5;margin:0;padding:20px;'>"
                        . "<div style='max-width:500px;margin:auto;background:white;border-radius:12px;overflow:hidden;'>"
                        . "<div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:28px;text-align:center;'>"
                        . "<h2 style='color:white;margin:0;'>Welcome to EyeSense</h2></div>"
                        . "<div style='padding:28px;'>"
                        . "<p style='color:#374151;'>Dear <strong>{$fullName}</strong>,</p>"
                        . "<p style='color:#6b7280;font-size:14px;'>Your EyeSense client account has been created by INDSAC.</p>"
                        . "<div style='background:#f3f4f6;border-radius:10px;padding:18px;margin:16px 0;font-size:14px;'>"
                        . "<b style='color:#667eea;'>Client ID:</b> <code>{$new_cid}</code><br>"
                        . "<b>Username:</b> <code>{$employeeId}</code><br>"
                        . "<b>Password:</b> <code>{$rc_pass}</code><br>"
                        . "<b>Email:</b> {$rc_email}"
                        . "</div>"
                        . "<p style='color:#ef4444;font-size:13px;'>Please change your password after first login.</p>"
                        . "<div style='text-align:center;margin-top:20px;'>"
                        . "<a href='{$portalUrl}' style='background:#667eea;color:white;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:600;'>Login to Portal</a>"
                        . "</div></div></div></body></html>";
                    indsacSendEmail($rc_email, 'Welcome to EyeSense : Your Account Details', $emailHtml);
                } catch (Throwable $ignored) {}

                $action_msg = "SUCCESS: Client registered! Client ID: <strong>{$new_cid}</strong> | Login: {$employeeId}{$licNote}";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=5.0">
    <title>INDSAC Admin — EyeSense</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { width: 100%; overflow-x: hidden; }
        body { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: #0f0f1a; color: #e0e0e0; min-height: 100vh; }

        .login-wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 16px; }
        .login-box { background: #1a1a2e; border: 1px solid #333; border-radius: 16px; padding: 32px 24px; width: 100%; max-width: 360px; text-align: center; }
        .login-box h2 { color: #667eea; margin-bottom: 8px; font-size: 24px; }
        .login-box p { color: #888; font-size: 14px; margin-bottom: 24px; }
        .login-box input[type="password"] { width: 100%; padding: 12px 16px; border: 1px solid #333; border-radius: 8px; background: #0f0f1a; color: #fff; font-size: 15px; margin-bottom: 16px; min-height: 44px; }
        .login-box button { width: 100%; padding: 14px; border: none; border-radius: 8px; background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; min-height: 48px; transition: opacity 0.2s; }
        .login-box button:hover { opacity: 0.9; }
        .login-box button:active { opacity: 0.8; }
        .login-error { color: #f44336; font-size: 13px; margin-bottom: 12px; }

        .topbar { background: #1a1a2e; border-bottom: 1px solid #2a2a3e; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .topbar h1 { font-size: clamp(18px, 4vw, 22px); color: #667eea; }
        .topbar a { color: #f44336; text-decoration: none; font-size: 14px; padding: 8px 12px; min-height: 44px; display: flex; align-items: center; border-radius: 6px; transition: background 0.2s; }
        .topbar a:hover { background: rgba(244, 67, 54, 0.1); }

        .container { max-width: 1200px; margin: 0 auto; padding: 16px; }

        .section { background: #1a1a2e; border: 1px solid #2a2a3e; border-radius: 12px; padding: 16px; margin-bottom: 24px; }
        .section h2 { color: #667eea; margin-bottom: 16px; font-size: clamp(16px, 3vw, 20px); border-bottom: 1px solid #2a2a3e; padding-bottom: 10px; }

        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { text-align: left; padding: 10px 12px; color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #2a2a3e; white-space: nowrap; background: #0a0a0f; }
        td { padding: 10px 12px; border-bottom: 1px solid #1f1f30; font-size: 13px; }
        tr:hover { background: rgba(102,126,234,0.03); }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-active { background: rgba(16,185,129,0.15); color: #10b981; }
        .badge-inactive { background: rgba(239,68,68,0.15); color: #ef4444; }

        .btn { padding: 8px 14px; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; transition: all 0.2s; min-height: 44px; min-width: 44px; }
        .btn-sm { padding: 6px 10px; font-size: 11px; min-height: 36px; min-width: 36px; }
        .btn-primary { background: #667eea; color: #fff; }
        .btn-primary:hover { background: #4f46e5; }
        .btn-primary:active { transform: scale(0.98); }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #10b981; color: #fff; }
        .btn-success:hover { background: #059669; }
        .btn-warning { background: #f59e0b; color: #fff; }
        .btn-warning:hover { background: #d97706; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .form-grid input, .form-grid select { padding: 8px 12px; border: 1px solid #333; border-radius: 6px; background: #0f0f1a; color: #fff; font-size: 13px; min-height: 44px; width: 100%; }
        .form-grid label { font-size: 11px; color: #888; margin-bottom: 6px; display: block; font-weight: 600; }
        .form-group { display: flex; flex-direction: column; }

        .action-msg { position: fixed; top: 24px; right: 24px; z-index: 9999; padding: 16px 24px; border-radius: 8px; font-size: 14px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); animation: slideInToast 0.3s ease-out forwards; max-width: 450px; line-height: 1.4; display: flex; align-items: flex-start; gap: 8px; }
        @keyframes slideInToast { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .action-msg.success { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
        .action-msg.error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .action-msg.warning { background: rgba(245,158,11,0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }

        .icon-inline { width: 1.1em; height: 1.1em; display: inline-block; vertical-align: middle; stroke-width: 2; margin-right: 4px; margin-top: -2px; }

        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .stat-card { background: #1a1a2e; border: 1px solid #2a2a3e; border-radius: 10px; padding: 16px; text-align: center; }
        .stat-card .value { font-size: clamp(24px, 5vw, 32px); font-weight: 700; color: #667eea; }
        .stat-card .label { font-size: 12px; color: #888; margin-top: 6px; }

        .inline-edit input { max-width: 80px; padding: 4px 6px; background: #0f0f1a; border: 1px solid #333; border-radius: 4px; color: #fff; font-size: 12px; }
        .inline-edit input.wide { max-width: 140px; }

        /* DataTables Dark Theme Overrides */
        .dataTables_wrapper { color: #888; }
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info { margin-bottom: 12px; margin-top: 12px; font-size: 12px; }
        .dataTables_wrapper .dataTables_filter input { background: #0f0f1a !important; border: 1px solid #333 !important; border-radius: 6px; color: #fff !important; padding: 8px 12px; margin-left: 8px; min-height: 36px; }
        .dataTables_wrapper .dataTables_length select { background: #0f0f1a !important; border: 1px solid #333 !important; border-radius: 4px; color: #fff !important; padding: 4px 8px; min-height: 36px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { color: #888 !important; padding: 6px 10px; border-radius: 4px; border: 1px solid transparent; cursor: pointer; min-height: 36px; min-width: 36px; transition: all 0.2s; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #667eea !important; color: #fff !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: rgba(102,126,234,0.1); color: #667eea !important; }
        table.dataTable.no-footer { border-bottom: 1px solid #2a2a3e; }

        /* Mobile responsive */
        @media (max-width: 640px) {
            .container { padding: 12px; }
            .section { padding: 12px; margin-bottom: 16px; }
            .section h2 { font-size: 16px; margin-bottom: 12px; }
            .stats { grid-template-columns: 1fr; gap: 10px; }
            .stat-card { padding: 12px; }
            .form-grid { grid-template-columns: 1fr; gap: 10px; }
            .form-grid input, .form-grid select { font-size: 14px; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
            .btn { width: 100%; justify-content: center; }
            .topbar { padding: 10px; }
            .topbar h1 { font-size: 18px; }
        }

        /* Multi-Society Aggregate Overview styles */
        .agg-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .agg-card { background: #16162a; border: 1px solid #282846; border-radius: 12px; padding: 16px; position: relative; overflow: hidden; transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease; }
        .agg-card:hover { transform: translateY(-2px); border-color: rgba(99, 102, 241, 0.4); box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
        .agg-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
        .agg-card-title { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
        .agg-card-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .agg-card-value { font-size: clamp(22px, 3.5vw, 28px); font-weight: 800; line-height: 1.1; margin-bottom: 4px; }
        .agg-card-sub { font-size: 11px; color: #64748b; }

        @media (max-width: 640px) {
            .agg-stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .agg-card { padding: 12px; }
            .agg-card-value { font-size: 20px; }
        }

        @media (max-width: 420px) {
            .agg-stats-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); }
        }

        @media (min-width: 1600px) {
            .container { max-width: 1400px; }
        }
    </style>

    <!-- jQuery and DataTables CDN -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<?php if (!$is_authed): ?>
<!-- ── LOGIN SCREEN ── -->
<div class="login-wrapper">
    <div class="login-box">
        <h2><i data-lucide="lock" class="icon-inline"></i> INDSAC Admin</h2>
        <p>EyeSense License Management</p>
        <?php if (!empty($login_error)): ?>
            <div class="login-error"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="password" placeholder="Admin Password" autofocus required>
            <button type="submit" name="admin_login" value="1">Sign In</button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ── AUTHENTICATED DASHBOARD ── -->
<?php
    if (!$db_error) {
        try {
            $db = get_license_db();
        } catch (Throwable $e) {
            if (!extension_loaded('pdo_mysql')) {
                $db_error = 'Database driver missing or not configured. Please enable PDO MySQL in PHP to use the admin dashboard.';
            } else {
                $db_error = 'Database connection failed: ' . $e->getMessage();
            }
        }
    }

    if ($db_error) {
        $client_count = 0;
        $plan_count = 0;
        $license_count = 0;
        $tenant_count = 0;
        $agg_societies = 0;
        $agg_cameras = 0;
        $agg_active_users = 0;
        $agg_alerts_today = 0;
        $agg_visitors_today = 0;
        $agg_storage_usage = '0 B';
        $societies_list = [];
        $clients = [];
        $plans = [];
        $licenses = [];
    } else {

    // Fetch legacy stats
    $client_count  = $db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    $plan_count    = $db->query("SELECT COUNT(*) FROM plans WHERE is_active = 1")->fetchColumn();
    $license_count = $db->query("SELECT COUNT(*) FROM licenses WHERE status = 'active'")->fetchColumn();
    try {
        $tenant_count = $db->query("SELECT COUNT(*) FROM society_tenants")->fetchColumn();
    } catch (Throwable $e) { $tenant_count = 0; }

    // ── Multi-Society Aggregate Metrics (Requirement 14) ──
    try {
        $agg_societies = (int)$db->query("SELECT COUNT(*) FROM society")->fetchColumn();
    } catch (Throwable $e) { $agg_societies = 0; }

    try {
        $agg_cameras = (int)$db->query("SELECT COUNT(*) FROM cameras")->fetchColumn();
    } catch (Throwable $e) { $agg_cameras = 0; }

    try {
        $agg_active_users = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM user_society_mapping WHERE status = 'ACTIVE'")->fetchColumn();
    } catch (Throwable $e) { $agg_active_users = 0; }

    try {
        $agg_face_alerts = (int)$db->query("SELECT COUNT(*) FROM face_logs WHERE DATE(timestamp) = CURDATE()")->fetchColumn();
        $agg_vehicle_alerts = (int)$db->query("SELECT COUNT(*) FROM vehicle_logs WHERE DATE(timestamp) = CURDATE()")->fetchColumn();
        $agg_alerts_today = $agg_face_alerts + $agg_vehicle_alerts;
    } catch (Throwable $e) { $agg_alerts_today = 0; }

    try {
        $agg_visitors_today = (int)$db->query("SELECT COUNT(*) FROM guest_visits WHERE DATE(granted_at) = CURDATE()")->fetchColumn();
    } catch (Throwable $e) { $agg_visitors_today = 0; }

    try {
        $stmtStorage = $db->query("
            SELECT COALESCE(SUM(data_length + index_length), 0) AS total_bytes
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name IN ('cameras', 'face_logs', 'vehicle_logs', 'snapshot_logs', 'video_logs')
        ");
        $agg_storage_bytes = (float)($stmtStorage ? $stmtStorage->fetchColumn() : 0);
        if ($agg_storage_bytes >= 1073741824) {
            $agg_storage_usage = number_format($agg_storage_bytes / 1073741824, 2) . ' GB';
        } elseif ($agg_storage_bytes >= 1048576) {
            $agg_storage_usage = number_format($agg_storage_bytes / 1048576, 2) . ' MB';
        } elseif ($agg_storage_bytes >= 1024) {
            $agg_storage_usage = number_format($agg_storage_bytes / 1024, 1) . ' KB';
        } else {
            $agg_storage_usage = $agg_storage_bytes . ' B';
        }
    } catch (Throwable $e) { $agg_storage_usage = '0 B'; }

    // Multi-Society Rollup List
    try {
        $socStmt = $db->query("
            SELECT 
                s.id,
                s.society_code,
                s.society_name,
                s.legacy_client_id,
                s.city,
                s.state,
                s.status,
                s.created_date,
                COUNT(DISTINCT c.id) AS camera_count,
                COUNT(DISTINCT m.user_id) AS active_user_count
            FROM society s
            LEFT JOIN cameras c ON c.society_id = s.id
            LEFT JOIN user_society_mapping m ON m.society_id = s.id AND m.status = 'ACTIVE'
            GROUP BY s.id
            ORDER BY s.id DESC
        ");
        $societies_list = $socStmt ? $socStmt->fetchAll() : [];
    } catch (Throwable $e) {
        $societies_list = [];
    }

    // Fetch data
    // Clients augmented with their latest active license info (society-first with fallback)
    $clients = $db->query("
        SELECT c.*, 
               s.id AS society_id,
               s.society_name,
               s.society_code,
               l.status as license_status, 
               l.plan as current_plan,
               l.start_date,
               l.expiry_date
        FROM clients c
        LEFT JOIN society s ON s.legacy_client_id = c.client_id
        LEFT JOIN licenses l ON l.id = (
            SELECT l2.id FROM licenses l2
            WHERE (s.id IS NOT NULL AND l2.society_id = s.id)
               OR (l2.legacy_client_id IS NOT NULL AND l2.legacy_client_id = c.client_id)
               OR (l2.society_id IS NULL AND l2.legacy_client_id IS NULL AND l2.sa_email = c.email)
            ORDER BY 
                (CASE 
                    WHEN s.id IS NOT NULL AND l2.society_id = s.id THEN 3
                    WHEN l2.legacy_client_id IS NOT NULL AND l2.legacy_client_id = c.client_id THEN 2
                    ELSE 1
                 END) DESC,
                (CASE WHEN l2.status = 'active' THEN 1 ELSE 0 END) DESC,
                l2.id DESC
            LIMIT 1
        )
        ORDER BY c.created_at DESC
    ")->fetchAll();

    $plans   = $db->query("SELECT * FROM plans ORDER BY price ASC")->fetchAll();
    
    // Complete License History (augmented with society info)
    $licenses = $db->query("
        SELECT l.*, s.society_name, s.society_code 
        FROM licenses l 
        LEFT JOIN society s ON s.id = l.society_id 
        ORDER BY l.created_at DESC
    ")->fetchAll();
    }
?>

<div class="topbar">
    <h1>INDSAC Admin — EyeSense</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <a href="#multi-society-overview" class="btn btn-sm" style="background:rgba(99,102,241,0.2);color:#a5b4fc;border:1px solid rgba(99,102,241,0.35);text-decoration:none;">
            <i data-lucide="globe" class="icon-inline"></i> Multi-Society Rollup
        </a>
        <a href="portal/client.php" target="_blank" class="btn btn-sm" style="background:rgba(0,180,216,0.15);color:#00e0ff;border:1px solid rgba(0,224,255,0.25);text-decoration:none;">
            <i data-lucide="map-pin" class="icon-inline"></i> Client Map Entry
        </a>
        <a href="portal/visitor.php" target="_blank" class="btn btn-sm" style="background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.25);text-decoration:none;">
            <i data-lucide="search" class="icon-inline"></i> Visitor Locator
        </a>
        <a href="?logout=1" style="color:#f44336;text-decoration:none;padding:8px 12px;min-height:44px;display:flex;align-items:center;border-radius:6px;transition:background 0.2s;">
            <i data-lucide="log-out" class="icon-inline"></i> Logout
        </a>
    </div>
</div>

<div class="container">
    <!-- ── MULTI-SOCIETY AGGREGATE OVERVIEW (Requirement 14) ── -->
    <div class="section" id="multi-society-overview" style="background: linear-gradient(180deg, #18182e 0%, #141426 100%); border: 1px solid #2e2e4e; border-radius: 14px; padding: 22px 20px; margin-bottom: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom: 18px; border-bottom: 1px solid #282846; padding-bottom: 14px;">
            <div>
                <h2 style="font-size: clamp(18px, 3.5vw, 22px); color: #fff; display: flex; align-items: center; gap: 8px; margin: 0; border: none; padding: 0;">
                    <i data-lucide="globe" class="icon-inline" style="color: #6366f1;"></i> Multi-Society Aggregate Overview
                </h2>
                <p style="color: #94a3b8; font-size: 13px; margin-top: 4px;">System-wide telemetry and aggregate multi-tenant rollup across all connected societies</p>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span class="badge" style="background:rgba(99,102,241,0.15); color:#a5b4fc; border:1px solid rgba(99,102,241,0.3); font-size:12px; padding:6px 12px;">
                    <i data-lucide="shield-check" class="icon-inline" style="width:14px;height:14px;"></i> Super Admin Scope: Global (All Societies)
                </span>
            </div>
        </div>

        <!-- 6 Aggregate Metric Cards -->
        <div class="agg-stats-grid">
            <!-- Metric 1: Total Societies -->
            <div class="agg-card" style="border-top: 3px solid #6366f1;">
                <div class="agg-card-header">
                    <span class="agg-card-title">Total Societies</span>
                    <div class="agg-card-icon" style="background:rgba(99,102,241,0.15); color:#818cf8;">
                        <i data-lucide="building-2" style="width:18px;height:18px;"></i>
                    </div>
                </div>
                <div class="agg-card-value" style="color:#818cf8;"><?= number_format($agg_societies) ?></div>
                <div class="agg-card-sub">Registered tenant societies</div>
            </div>

            <!-- Metric 2: Total Cameras -->
            <div class="agg-card" style="border-top: 3px solid #06b6d4;">
                <div class="agg-card-header">
                    <span class="agg-card-title">Total Cameras</span>
                    <div class="agg-card-icon" style="background:rgba(6,182,212,0.15); color:#38bdf8;">
                        <i data-lucide="video" style="width:18px;height:18px;"></i>
                    </div>
                </div>
                <div class="agg-card-value" style="color:#38bdf8;"><?= number_format($agg_cameras) ?></div>
                <div class="agg-card-sub">Active feeds system-wide</div>
            </div>

            <!-- Metric 3: Active Users -->
            <div class="agg-card" style="border-top: 3px solid #10b981;">
                <div class="agg-card-header">
                    <span class="agg-card-title">Active Users</span>
                    <div class="agg-card-icon" style="background:rgba(16,185,129,0.15); color:#34d399;">
                        <i data-lucide="users" style="width:18px;height:18px;"></i>
                    </div>
                </div>
                <div class="agg-card-value" style="color:#34d399;"><?= number_format($agg_active_users) ?></div>
                <div class="agg-card-sub">With active society mapping</div>
            </div>

            <!-- Metric 4: AI Alerts Today -->
            <div class="agg-card" style="border-top: 3px solid #f59e0b;">
                <div class="agg-card-header">
                    <span class="agg-card-title">AI Alerts Today</span>
                    <div class="agg-card-icon" style="background:rgba(245,158,11,0.15); color:#fbbf24;">
                        <i data-lucide="shield-alert" style="width:18px;height:18px;"></i>
                    </div>
                </div>
                <div class="agg-card-value" style="color:#fbbf24;"><?= number_format($agg_alerts_today) ?></div>
                <div class="agg-card-sub">Face &amp; vehicle detections</div>
            </div>

            <!-- Metric 5: Visitors Today -->
            <div class="agg-card" style="border-top: 3px solid #ec4899;">
                <div class="agg-card-header">
                    <span class="agg-card-title">Visitors Today</span>
                    <div class="agg-card-icon" style="background:rgba(236,72,153,0.15); color:#f472b6;">
                        <i data-lucide="user-plus" style="width:18px;height:18px;"></i>
                    </div>
                </div>
                <div class="agg-card-value" style="color:#f472b6;"><?= number_format($agg_visitors_today) ?></div>
                <div class="agg-card-sub">Logged guest visits</div>
            </div>

            <!-- Metric 6: Storage Usage -->
            <div class="agg-card" style="border-top: 3px solid #8b5cf6;">
                <div class="agg-card-header">
                    <span class="agg-card-title">Storage Usage</span>
                    <div class="agg-card-icon" style="background:rgba(139,92,246,0.15); color:#a78bfa;">
                        <i data-lucide="database" style="width:18px;height:18px;"></i>
                    </div>
                </div>
                <div class="agg-card-value" style="color:#a78bfa;"><?= htmlspecialchars($agg_storage_usage) ?></div>
                <div class="agg-card-sub">5 core media/log tables</div>
            </div>
        </div>

        <!-- Connected Societies Rollup Breakdown Table -->
        <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #282846;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 14px; flex-wrap:wrap; gap:8px;">
                <h3 style="font-size: 14px; color: #c7d2fe; display:flex; align-items:center; gap:6px; margin:0;">
                    <i data-lucide="layers" class="icon-inline" style="color:#818cf8;"></i> Connected Societies Telemetry Breakdown
                </h3>
                <span style="font-size: 12px; color: #94a3b8;">
                    Total: <strong style="color:#e0e0e0;"><?= count($societies_list) ?></strong> societies registered
                </span>
            </div>
            <div class="table-responsive">
                <table id="societiesTable" class="display">
                    <thead>
                        <tr>
                            <th>Society / Code</th>
                            <th>Legacy Client ID</th>
                            <th>Location</th>
                            <th>Cameras</th>
                            <th>Active Users</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($societies_list as $soc): ?>
                        <tr>
                            <td>
                                <strong style="color: #fff; font-size: 13px;"><?= htmlspecialchars($soc['society_name']) ?></strong><br>
                                <code style="color: #818cf8; font-size: 11px;"><?= htmlspecialchars($soc['society_code']) ?></code>
                            </td>
                            <td>
                                <?php if (!empty($soc['legacy_client_id'])): ?>
                                    <code style="color: #a5b4fc; font-weight: 600;"><?= htmlspecialchars($soc['legacy_client_id']) ?></code>
                                <?php else: ?>
                                    <span style="color:#666;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($soc['city'] ?: '—') ?><?= !empty($soc['state']) ? ', ' . htmlspecialchars($soc['state']) : '' ?>
                            </td>
                            <td>
                                <span class="badge" style="background:rgba(6,182,212,0.12); color:#38bdf8; font-weight:600;">
                                    <i data-lucide="video" class="icon-inline" style="width:12px;height:12px;"></i> <?= (int)$soc['camera_count'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge" style="background:rgba(16,185,129,0.12); color:#34d399; font-weight:600;">
                                    <i data-lucide="user-check" class="icon-inline" style="width:12px;height:12px;"></i> <?= (int)$soc['active_user_count'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= strtoupper($soc['status']) === 'ACTIVE' ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= htmlspecialchars(ucfirst(strtolower($soc['status']))) ?>
                                </span>
                            </td>
                            <td>
                                <?= !empty($soc['created_date']) ? date('d M Y', strtotime($soc['created_date'])) : '—' ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <?php if (!empty($soc['legacy_client_id'])): ?>
                                    <?php $cid_enc = urlencode($soc['legacy_client_id']); ?>
                                    <a href="client_profile.php?cid=<?= $cid_enc ?>" class="btn btn-sm" style="background:rgba(99,102,241,0.15);color:#a5b4fc;border:1px solid rgba(99,102,241,0.3);text-decoration:none;" title="View Client Profile">
                                        <i data-lucide="user-circle" class="icon-inline"></i> Profile
                                    </a>
                                    <a href="portal/client.php?client_id=<?= $cid_enc ?>" target="_blank" class="btn btn-sm" style="background:rgba(0,180,216,0.15);color:#00e0ff;border:1px solid rgba(0,224,255,0.25);text-decoration:none;" title="Open Client Map Entry Form">
                                        <i data-lucide="map-pin" class="icon-inline"></i> Map
                                    </a>
                                    <a href="portal/visitor.php?client_id=<?= $cid_enc ?>" target="_blank" class="btn btn-sm" style="background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.25);text-decoration:none;" title="Open Visitor Plot Locator">
                                        <i data-lucide="search" class="icon-inline"></i> Plot
                                    </a>
                                <?php else: ?>
                                    <span style="color:#666; font-size:11px;">Unlinked</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── SUBSCRIPTION & LICENSE LEDGER SUMMARY ── -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
        <h3 style="color:#94a3b8; font-size:12px; text-transform:uppercase; letter-spacing:0.06em; font-weight:600; display:flex; align-items:center; gap:6px;">
            <i data-lucide="receipt" class="icon-inline" style="width:14px;height:14px;color:#6366f1;"></i> Client Subscription &amp; Licensing Summary
        </h3>
    </div>
    <div class="stats">
        <div class="stat-card">
            <div class="value"><?= $client_count ?></div>
            <div class="label">Registered Clients</div>
        </div>
        <div class="stat-card">
            <div class="value"><?= $plan_count ?></div>
            <div class="label">Active Plans</div>
        </div>
        <div class="stat-card">
            <div class="value"><?= $license_count ?></div>
            <div class="label">Active Licenses</div>
        </div>
        <div class="stat-card">
            <div class="value" style="color:#a78bfa"><?= $tenant_count ?></div>
            <div class="label">Total Tenants</div>
        </div>
    </div>

    <?php if ($db_error): ?>
        <div class="action-msg error">
            <?= htmlspecialchars($db_error) ?>
        </div>
    <?php endif; ?>

    <?php if ($action_msg): ?>
        <?php 
            $is_success = str_starts_with($action_msg, 'SUCCESS:');
            $is_warning = str_starts_with($action_msg, 'WARNING:');
            $is_trash = str_starts_with($action_msg, 'TRASH:');
            if ($is_success) {
                $msg_class = 'success';
                $icon = 'check-circle';
                $text = substr($action_msg, 8);
            } elseif ($is_warning) {
                $msg_class = 'warning';
                $icon = 'alert-triangle';
                $text = substr($action_msg, 8);
            } elseif ($is_trash) {
                $msg_class = 'success';
                $icon = 'trash-2';
                $text = substr($action_msg, 6);
            } else {
                $msg_class = 'error';
                $icon = 'x-circle';
                $text = str_starts_with($action_msg, 'ERROR:') ? substr($action_msg, 6) : $action_msg;
            }
        ?>
        <div class="action-msg <?= $msg_class ?>">
            <i data-lucide="<?= $icon ?>" class="icon-inline"></i> <?= $text ?>
        </div>
    <?php endif; ?>

    <!-- ── PLANS MANAGEMENT ── -->
    <div class="section">
        <h2><i data-lucide="clipboard-list" class="icon-inline"></i> Licensing Plans</h2>
        <div class="table-responsive">
        <table id="plansTable" class="display">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Days</th>
                    <th>Price (₹)</th>
                    <th>Cam Limit</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plans as $plan): ?>
                <tr>
                    <form method="POST">
                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                        <td><code><?= htmlspecialchars($plan['plan_code']) ?></code></td>
                        <td class="inline-edit"><input type="text" name="name" class="wide" value="<?= htmlspecialchars($plan['name']) ?>"></td>
                        <td class="inline-edit"><input type="number" name="days" value="<?= $plan['days'] ?>"></td>
                        <td class="inline-edit"><input type="number" name="price" step="0.01" value="<?= $plan['price'] ?>"></td>
                        <td class="inline-edit"><input type="number" name="camera_limit" value="<?= $plan['camera_limit'] ?>" title="0 = unlimited"></td>
                        <td>
                            <label style="display:flex;align-items:center;gap:6px;">
                                <input type="checkbox" name="is_active" <?= $plan['is_active'] ? 'checked' : '' ?>>
                                <span class="badge <?= $plan['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $plan['is_active'] ? 'Active' : 'Disabled' ?>
                                </span>
                            </label>
                        </td>
                        <td>
                            <button type="submit" name="update_plan" value="1" class="btn btn-sm btn-primary">Save</button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Add New Plan -->
        <div style="margin-top:24px; padding-top:16px; border-top:1px solid #2a2a3e;">
            <h3 style="color:#10b981; font-size:14px; margin-bottom:12px;"><i data-lucide="plus" class="icon-inline"></i> Add New Plan</h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Plan Code</label>
                        <input type="text" name="plan_code" placeholder="e.g. PRO_3M" required>
                    </div>
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="name" placeholder="e.g. Pro 3 Months" required>
                    </div>
                    <div class="form-group">
                        <label>Duration (days)</label>
                        <input type="number" name="days" placeholder="90" required>
                    </div>
                    <div class="form-group">
                        <label>Price (₹)</label>
                        <input type="number" name="price" step="0.01" placeholder="9999">
                    </div>
                    <div class="form-group">
                        <label>Camera Limit (0=∞)</label>
                        <input type="number" name="camera_limit" placeholder="0" value="0">
                    </div>
                </div>
                <button type="submit" name="add_plan" value="1" class="btn btn-success">Add Plan</button>
            </form>
        </div>
    </div>

    <!-- ── REGISTER CLIENT (Manual) ── -->
    <div class="section" id="register-client-section">
        <h2><i data-lucide="plus" class="icon-inline"></i> Add Society</h2>
        <p style="color:#888; font-size:13px; margin-bottom:4px;">Create a society and its first administrator. A unique Client ID (IND-YYYY-XXXXX) will be generated automatically.</p>
        <p style="color:#f59e0b; font-size:12px; margin-bottom:16px; padding:8px 12px; background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.2); border-radius:6px;">
            <i data-lucide="coins" class="icon-inline"></i> <strong>Payment assumed received</strong> — for manual registrations, payment verification is skipped. License is assigned immediately upon plan selection.
        </p>
        <form method="POST" id="registerClientForm">
            <!-- Row 1: Name / Contact -->
            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                <div class="form-group">
                    <label>Society Name *</label>
                    <input type="text" name="rc_society_name" placeholder="Green Valley Society" required>
                </div>
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="rc_first_name" placeholder="John" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="rc_last_name" placeholder="Doe">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="rc_email" placeholder="john@company.com" required>
                    <small style="color:#10b981; font-size:10px; margin-top:4px;">Reuse an existing admin email to enable society switching.</small>
                </div>
                <div class="form-group">
                    <label>Mobile</label>
                    <input type="text" name="rc_mobile" placeholder="+91XXXXXXXXXX">
                </div>
                <div class="form-group">
                    <label>SA Username</label>
                    <input type="text" name="rc_username" placeholder="SA001">
                </div>
            </div>

            <!-- Row 2: Machine ID with generator -->
            <div style="margin-bottom:16px;">
                <label style="font-size:11px; color:#888; margin-bottom:6px; display:block;">Machine ID
                    <span style="color:#667eea; font-size:10px; margin-left:8px;">
                        — Client must run EyeSense setup to get their Machine ID, or generate a registration link below
                    </span>
                </label>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" name="rc_machine_id" id="rc_machine_id"
                        placeholder="e.g. MACHINE-ABCDE-12345  (leave blank if unknown)"
                        style="flex:1; padding:8px 12px; border:1px solid #333; border-radius:6px; background:#0f0f1a; color:#fff; font-size:13px; font-family:monospace;">
                    <button type="button" onclick="generateMachineLink()" class="btn btn-warning" style="white-space:nowrap;">
                        <i data-lucide="link" class="icon-inline"></i> Generate Registration Link
                    </button>
                </div>
                <!-- Machine ID generator link output -->
                <div id="machineLinkBox" style="display:none; margin-top:10px; padding:12px 14px; background:rgba(102,126,234,0.07); border:1px solid rgba(102,126,234,0.25); border-radius:8px; font-size:13px;">
                    <p style="color:#667eea; margin-bottom:6px; font-weight:600;"><i data-lucide="clipboard" class="icon-inline"></i> Share this link with the client:</p>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="text" id="machineLinkUrl" readonly style="flex:1; padding:6px 10px; background:#0f0f1a; border:1px solid #333; border-radius:5px; color:#a5b4fc; font-family:monospace; font-size:12px;">
                        <button type="button" onclick="copyMachineLink()" class="btn btn-sm btn-primary">Copy</button>
                    </div>
                    <p style="color:#888; font-size:11px; margin-top:6px;">
                        When the client opens this link, their Machine ID will be automatically generated and displayed. They copy it and send it back to you.
                    </p>
                </div>
            </div>

            <!-- Row 3: Plan & Notes -->
            <div class="form-grid" style="grid-template-columns: 1fr 2fr;">
                <div class="form-group">
                    <label>Assign Plan <span style="color:#888; font-weight:normal;">(optional)</span></label>
                    <select name="rc_plan" id="rc_plan" style="padding:8px 12px; border:1px solid #333; border-radius:6px; background:#0f0f1a; color:#fff; font-size:13px;">
                        <option value="">— No plan / License later —</option>
                        <?php foreach ($plans as $p): if (!$p['is_active']) continue; ?>
                        <option value="<?= htmlspecialchars($p['plan_code']) ?>">
                            <?= htmlspecialchars($p['name']) ?> — ₹<?= number_format($p['price'], 0) ?> / <?= $p['days'] ?> days
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:#888; font-size:10px; margin-top:4px;">Requires Machine ID to assign license</small>
                </div>
                <div class="form-group">
                    <label>Internal Notes</label>
                    <input type="text" name="rc_notes" placeholder="e.g. Referred by XYZ, paid via cash on 12-May-2026">
                </div>
            </div>

            <!-- Row 4: Password -->
            <div class="form-grid" style="margin-top:4px;">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Initial Password * <span style="color:#888; font-weight:normal;">(sent to client via email)</span></label>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                        <input type="password" name="rc_password" id="rc_password"
                            placeholder="Set a strong password" required
                            style="flex:1; min-width: 200px; padding:10px 12px; border:1px solid #333; border-radius:6px; background:#0f0f1a; color:#fff; font-size:14px;">
                        <div style="display:flex; gap:6px;">
                            <button type="button" onclick="togglePassVis()" class="btn btn-sm" style="background:#2a2a3e; color:#aaa; padding:10px; min-height:44px;" title="Show/Hide"><i data-lucide="eye"></i></button>
                            <button type="button" onclick="genPass()" class="btn btn-sm btn-warning" style="white-space:nowrap; padding:10px 14px; min-height:44px;"><i data-lucide="dices" class="icon-inline"></i> Generate</button>
                        </div>
                    </div>
                    <small style="color:#888; font-size:11px; margin-top:6px;">Client will receive this via email and must change it on first login.</small>
                    <div id="passStrengthBar" style="margin-top:10px;"></div>
                </div>
            </div>

            <button type="submit" name="register_client" value="1" class="btn btn-primary" style="margin-top:12px; padding:10px 28px; font-size:14px;">
                <i data-lucide="fingerprint" class="icon-inline"></i> Add Society &amp; Send Welcome Email
            </button>
        </form>
    </div>


    <!-- ── REGISTERED CLIENTS ── -->
    <div class="section">
        <h2><i data-lucide="users" class="icon-inline"></i> Registered Clients & Their Licenses</h2>
        <?php if (empty($clients)): ?>
            <p style="color:#888; text-align:center; padding:20px;">No clients registered yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table id="clientsTable" class="display">
            <thead>
                <tr>
                    <th>Client ID</th>
                    <th>Name / Username</th>
                    <th>Email / Mobile</th>
                    <th>Registered</th>
                    <th>License Status</th>
                    <th>Plan</th>
                    <th>Expiry Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                <tr>
                    <td><code style="color:#667eea; font-weight:600;"><?= htmlspecialchars($c['client_id']) ?></code></td>
                    <td>
                        <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?><br>
                        <span style="font-size:11px; color:#888;">@<?= htmlspecialchars($c['sa_username'] ?: '—') ?></span>
                    </td>
                    <td>
                        <?= htmlspecialchars($c['email']) ?><br>
                        <span style="font-size:11px; color:#888;"><?= htmlspecialchars($c['mobile'] ?: '—') ?></span>
                    </td>
                    <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                    <td>
                        <?php if ($c['license_status']): ?>
                            <span class="badge <?= $c['license_status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                <?= ucfirst($c['license_status']) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background:rgba(255,255,255,0.1); color:#aaa;">No License</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($c['current_plan']): ?>
                            <strong style="color:#e0e0e0; font-size:13px;"><?= htmlspecialchars($c['current_plan']) ?></strong>
                        <?php else: ?>
                            <span style="color:#666;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($c['expiry_date']): ?>
                            <?php 
                                $is_expired = strtotime($c['expiry_date']) < time();
                                $color = $is_expired ? '#ef4444' : '#10b981';
                            ?>
                            <span style="color: <?= $color ?>; font-weight: 500;">
                                <?= date('d M Y', strtotime($c['expiry_date'])) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#666;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php $cid_enc = urlencode($c['client_id']); ?>
                        <a href="client_profile.php?cid=<?= $cid_enc ?>" class="btn btn-sm" style="background:rgba(99,102,241,0.15);color:#a5b4fc;border:1px solid rgba(99,102,241,0.3);text-decoration:none;" title="View Client Profile">
                            <i data-lucide="user-circle" class="icon-inline"></i> Profile
                        </a>
                        <a href="admin_dashboard.php?client_action=suspend&cid=<?= $cid_enc ?>" class="btn btn-sm btn-warning"
                           onclick="return confirm('Suspend <?= htmlspecialchars($c['client_id'], ENT_QUOTES) ?>?')"><i data-lucide="pause" class="icon-inline"></i> Suspend</a>
                        <a href="admin_dashboard.php?client_action=reactivate&cid=<?= $cid_enc ?>" class="btn btn-sm btn-success"><i data-lucide="play" class="icon-inline"></i> Activate</a>
                        <a href="admin_dashboard.php?client_action=delete&cid=<?= $cid_enc ?>" class="btn btn-sm btn-danger"
                           onclick="return confirm('DELETE <?= htmlspecialchars($c['client_id'], ENT_QUOTES) ?> permanently? This cannot be undone.')"><i data-lucide="trash-2" class="icon-inline"></i> Delete</a>
                        <?php 
                            $hostel_enabled = !empty($c['hostel_management_enabled']);
                            $h_text = $hostel_enabled ? 'Disable Hostel' : 'Enable Hostel';
                            $h_style = $hostel_enabled 
                                ? 'background:rgba(244,67,54,0.15);color:#fca5a5;border:1px solid rgba(244,67,54,0.3);text-decoration:none;' 
                                : 'background:rgba(139,92,246,0.15);color:#c4b5fd;border:1px solid rgba(139,92,246,0.3);text-decoration:none;';
                        ?>
                        <a href="admin_dashboard.php?client_action=toggle_hostel&cid=<?= $cid_enc ?>" class="btn btn-sm" style="<?= $h_style ?>"
                           onclick="return confirm('<?= $hostel_enabled ? 'Disable' : 'Enable' ?> Hostel Management for <?= htmlspecialchars($c['client_id'], ENT_QUOTES) ?>?')"><i data-lucide="building" class="icon-inline"></i> <?= $h_text ?></a>
                        <a href="portal/client.php?client_id=<?= $cid_enc ?>" target="_blank" class="btn btn-sm" style="background:rgba(0,180,216,0.15);color:#00e0ff;border:1px solid rgba(0,224,255,0.25);text-decoration:none;" title="Open Client Map Entry Form">
                            <i data-lucide="map-pin" class="icon-inline"></i> Map Entry
                        </a>
                        <a href="portal/visitor.php" target="_blank" class="btn btn-sm" style="background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.25);text-decoration:none;" title="Open Visitor Plot Locator">
                            <i data-lucide="search" class="icon-inline"></i> Plot Locator
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── LICENSE LEDGER ── -->
    <div class="section">
        <h2><i data-lucide="receipt" class="icon-inline"></i> Full License Ledger (History)</h2>
        <?php if (empty($licenses)): ?>
            <p style="color:#888; text-align:center; padding:20px;">No licenses issued yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table id="licensesTable" class="display">
            <thead>
                <tr>
                    <th>Purchased On</th>
                    <th>Client Email</th>
                    <th>Plan</th>
                    <th>Amount (₹)</th>
                    <th>Period</th>
                    <th>Payment ID</th>
                    <th>Machine ID</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($licenses as $l): ?>
                <tr>
                    <td><?= date('d M Y, H:i', strtotime($l['created_at'])) ?></td>
                    <td>
                        <?= htmlspecialchars($l['sa_email']) ?>
                        <?php if (!empty($l['society_name'])): ?>
                            <br><span style="font-size:11px; color:#818cf8;"><i data-lucide="building-2" class="icon-inline" style="width:11px;height:11px;"></i> <?= htmlspecialchars($l['society_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($l['plan']) ?></strong></td>
                    <td><?= number_format($l['amount'], 2) ?></td>
                    <td style="font-size: 12px; color:#aaa;">
                        <?= date('d M y', strtotime($l['start_date'])) ?> - <br>
                        <?= date('d M y', strtotime($l['expiry_date'])) ?>
                    </td>
                    <td><code style="font-size:11px;"><?= htmlspecialchars($l['payment_id'] ?: '—') ?></code></td>
                    <td><code style="font-size:10px; color:#667eea;"><?= substr(htmlspecialchars($l['machine_id']), 0, 16) ?>...</code></td>
                    <td>
                        <span class="badge <?= $l['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                            <?= ucfirst($l['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
$(document).ready(function() {
    $('#societiesTable').DataTable({
        "pageLength": 10,
        "ordering": true,
        "order": [[ 0, 'asc' ]], // order by Society Name (column index 0)
        "searching": true,
        "lengthChange": true,
        "info": true
    });

    $('#plansTable').DataTable({
        "pageLength": 10,
        "ordering": true,
        "searching": true,
        "lengthChange": true,
        "info": true
    });
    
    $('#clientsTable').DataTable({
        "pageLength": 10,
        "ordering": true,
        "order": [[ 3, 'desc' ]], // order by Registered Date (column index 3)
        "searching": true,
        "lengthChange": true,
        "info": true
    });

    $('#licensesTable').DataTable({
        "pageLength": 10,
        "ordering": true,
        "order": [[ 0, 'desc' ]], // order by Purchased On (column index 0)
        "searching": true,
        "lengthChange": true,
        "info": true
    });
});

// ── Machine ID Link Generator ──────────────────────────────────
function generateMachineLink() {
    const chars = 'ABCDEFGHIJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    let token = '';
    for (let i = 0; i < 24; i++) token += chars[Math.floor(Math.random() * chars.length)];
    const url = '<?= rtrim(INDSAC_APP_BASE_URL, '/') ?>/machine_id_gen.php?token=' + token;
    document.getElementById('machineLinkUrl').value = url;
    document.getElementById('machineLinkBox').style.display = 'block';
    document.getElementById('machineLinkBox').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function copyMachineLink() {
    const input = document.getElementById('machineLinkUrl');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = input.nextElementSibling;
        btn.innerHTML = '<i data-lucide="check" class="icon-inline"></i> Copied!';
        lucide.createIcons();
        setTimeout(() => {
            btn.textContent = 'Copy';
            lucide.createIcons();
        }, 2000);
    }).catch(() => document.execCommand('copy'));
}

// ── Password Helpers ──────────────────────────────────────────
function togglePassVis() {
    const p = document.getElementById('rc_password');
    const btn = p.nextElementSibling.querySelector('button');
    if (p.type === 'password') {
        p.type = 'text';
        btn.innerHTML = '<i data-lucide="eye-off"></i>';
    } else {
        p.type = 'password';
        btn.innerHTML = '<i data-lucide="eye"></i>';
    }
    lucide.createIcons();
}

function genPass() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
    let pass = '';
    for (let i = 0; i < 12; i++) pass += chars[Math.floor(Math.random() * chars.length)];
    const inp = document.getElementById('rc_password');
    inp.value = pass;
    inp.type = 'text';
    document.getElementById('passStrengthBar').innerHTML =
        '<span style="color:#10b981;font-size:12px;"><i data-lucide="check-circle" class="icon-inline"></i> Strong password generated — <code style=\'background:#0f0f1a;padding:2px 6px;border-radius:4px;color:#a5b4fc;\'>' + pass + '</code></span>';
    lucide.createIcons();
}

// Preserve scroll position across page reloads (e.g. after form submits)
document.addEventListener("DOMContentLoaded", function(event) { 
    var scrollpos = sessionStorage.getItem('adminDashScrollPos');
    if (scrollpos) {
        window.scrollTo(0, scrollpos);
        sessionStorage.removeItem('adminDashScrollPos');
    }
    
    // Auto-dismiss action message toast after 5 seconds
    var toast = document.querySelector('.action-msg');
    if (toast) {
        setTimeout(function() {
            toast.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 500);
        }, 5000);
    }
});

window.addEventListener("beforeunload", function(e) {
    sessionStorage.setItem('adminDashScrollPos', window.scrollY);
});

lucide.createIcons();
</script>

<?php endif; ?>
</body>
</html>
