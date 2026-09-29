<?php
/**
 * INDSAC Admin — Register Tenant Client (AJAX API)
 * POST /api/admin_register_client.php
 * Requires active INDSAC admin session.
 */
ob_start();

session_start();
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/maintenance_notify.php';

ob_clean(); // discard any include-time output

header('Content-Type: application/json; charset=utf-8');

// Auth check
if (empty($_SESSION['indsac_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Handle GET management actions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ca  = trim($_GET['client_action'] ?? '');
    $cid = trim($_GET['cid'] ?? '');
    if (!$ca || !$cid) {
        echo json_encode(['success' => false, 'error' => 'Missing params']); exit;
    }

    $db  = get_license_db();
    $pdo = get_indsac_db();

    $chkC = $db->prepare("SELECT * FROM clients WHERE client_id = ?");
    $chkC->execute([$cid]);
    $clientRow = $chkC->fetch();

    if (!$clientRow) {
        echo json_encode(['success' => false, 'error' => "Client ID not found: $cid"]); exit;
    }

    // Find associated society if exists
    $socStmt = $pdo->prepare("SELECT id FROM society WHERE legacy_client_id = ? LIMIT 1");
    $socStmt->execute([$cid]);
    $socId = (int)$socStmt->fetchColumn() ?: null;

    if ($ca === 'suspend') {
        $pdo->prepare("UPDATE employees SET status='inactive' WHERE client_id=?")->execute([$cid]);
        if ($socId) {
            $pdo->prepare("UPDATE society SET status='SUSPENDED' WHERE id=?")->execute([$socId]);
        }
        $pdo->prepare("UPDATE clients SET status='suspended' WHERE client_id=?")->execute([$cid]);

        $licStmt = $db->prepare("SELECT id FROM licenses WHERE (society_id IS NOT NULL AND society_id=?) OR legacy_client_id=? OR (society_id IS NULL AND legacy_client_id IS NULL AND sa_email=?) ORDER BY id DESC LIMIT 1");
        $licStmt->execute([$socId, $cid, $clientRow['email']]);
        $licId = $licStmt->fetchColumn();

        if ($licId) {
            $db->prepare("UPDATE licenses SET status='suspended' WHERE id=?")->execute([$licId]);
        } else {
            $start  = date('Y-m-d');
            $expiry = date('Y-m-d', strtotime('+30 days'));
            $licKey = hash('sha256', $cid . microtime());
            $mid    = !empty($clientRow['machine_id']) ? $clientRow['machine_id'] : ('MID-' . strtoupper(substr(md5($cid), 0, 10)));
            $db->prepare("INSERT INTO licenses (society_id, legacy_client_id, machine_id, plan, start_date, expiry_date, auto_renew, sa_name, sa_email, sa_phone, payment_id, payment_status, amount, license_key_hash, status)
                          VALUES (?, ?, ?, 'PRO_1M', ?, ?, 0, ?, ?, ?, 'MANUAL-SUSPEND', 'completed', 0.00, ?, 'suspended')")
               ->execute([$socId, $cid, $mid, $start, $expiry, trim($clientRow['first_name'] . ' ' . $clientRow['last_name']), $clientRow['email'], $clientRow['mobile'] ?: '', $licKey]);
        }
        echo json_encode(['success' => true, 'message' => "Client $cid suspended."]);
    } elseif ($ca === 'reactivate') {
        $pdo->prepare("UPDATE employees SET status='active' WHERE client_id=?")->execute([$cid]);
        if ($socId) {
            $pdo->prepare("UPDATE society SET status='ACTIVE' WHERE id=?")->execute([$socId]);
        }
        $pdo->prepare("UPDATE clients SET status='active' WHERE client_id=?")->execute([$cid]);

        $licStmt = $db->prepare("SELECT id, expiry_date FROM licenses WHERE (society_id IS NOT NULL AND society_id=?) OR legacy_client_id=? OR (society_id IS NULL AND legacy_client_id IS NULL AND sa_email=?) ORDER BY id DESC LIMIT 1");
        $licStmt->execute([$socId, $cid, $clientRow['email']]);
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
            $licKey = hash('sha256', $cid . microtime());
            $mid    = !empty($clientRow['machine_id']) ? $clientRow['machine_id'] : ('MID-' . strtoupper(substr(md5($cid), 0, 10)));
            $db->prepare("INSERT INTO licenses (society_id, legacy_client_id, machine_id, plan, start_date, expiry_date, auto_renew, sa_name, sa_email, sa_phone, payment_id, payment_status, amount, license_key_hash, status)
                          VALUES (?, ?, ?, 'PRO_1M', ?, ?, 0, ?, ?, ?, 'MANUAL-ACTIVATE', 'completed', 0.00, ?, 'active')")
               ->execute([$socId, $cid, $mid, $start, $expiry, trim($clientRow['first_name'] . ' ' . $clientRow['last_name']), $clientRow['email'], $clientRow['mobile'] ?: '', $licKey]);
        }
        echo json_encode(['success' => true, 'message' => "Client $cid reactivated."]);
    } elseif ($ca === 'delete') {
        $pdo->prepare("DELETE FROM employees WHERE client_id=?")->execute([$cid]);
        $db->prepare("UPDATE licenses SET status='revoked' WHERE (society_id IS NOT NULL AND society_id=?) OR legacy_client_id=? OR (society_id IS NULL AND legacy_client_id IS NULL AND sa_email=?)")
           ->execute([$socId, $cid, $clientRow['email']]);
        $db->prepare("DELETE FROM clients WHERE client_id=?")->execute([$cid]);
        echo json_encode(['success' => true, 'message' => "Client $cid deleted and license revoked."]);
    } elseif ($ca === 'toggle_hostel') {
        $current_status = (int)($clientRow['hostel_management_enabled'] ?? 0);
        $new_status = $current_status ? 0 : 1;
        $pdo->prepare("UPDATE clients SET hostel_management_enabled = ? WHERE client_id = ?")->execute([$new_status, $cid]);
        echo json_encode(['success' => true, 'message' => "Hostel Management " . ($new_status ? 'enabled' : 'disabled') . " for $cid.", 'hostel_management_enabled' => $new_status]);
    } else {
        echo json_encode(['success' => false, 'error' => "Unknown action: $ca"]);
    }
    exit;
}

// POST — Register new client
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success' => false, 'error' => 'POST required']); exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$rc_first   = trim($input['rc_first_name'] ?? '');
$rc_last    = trim($input['rc_last_name'] ?? '');
$rc_society = trim($input['rc_society_name'] ?? '');
$rc_email   = strtolower(trim($input['rc_email'] ?? ''));
$rc_mobile  = trim($input['rc_mobile'] ?? '');
$rc_user    = trim($input['rc_username'] ?? '');
$rc_machine = trim($input['rc_machine_id'] ?? '');
$rc_plan    = trim($input['rc_plan'] ?? '');
$rc_notes   = trim($input['rc_notes'] ?? '');
$rc_pass    = trim($input['rc_password'] ?? '');

// Validate
if (!$rc_first)                                      { echo json_encode(['success'=>false,'error'=>'First name required']); exit; }
if (!$rc_society)                                    { echo json_encode(['success'=>false,'error'=>'Society name required']); exit; }
if (!$rc_email || !filter_var($rc_email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'error'=>'Valid email required']); exit; }
if (!$rc_pass || strlen($rc_pass) < 6)               { echo json_encode(['success'=>false,'error'=>'Password must be at least 6 characters']); exit; }

try {
    $db  = get_license_db();
    $pdo = get_indsac_db();

    // 1. Check duplicate email in license DB
    $chk = $db->prepare("SELECT client_id FROM clients WHERE email = ?");
    $chk->execute([$rc_email]);
    $existing = $chk->fetch();
    if ($existing) {
        echo json_encode(['success'=>false,'error'=>'Email already registered. Client ID: ' . $existing['client_id']]);
        exit;
    }

    // 2. Generate Client ID and insert into clients
    $new_cid = generate_client_id($db);
    $db->prepare("
        INSERT INTO clients (client_id, first_name, last_name, email, mobile, machine_id, sa_username)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$new_cid, $rc_first, $rc_last, $rc_email, $rc_mobile, $rc_machine, $rc_user ?: $new_cid . '-SA']);

    // 3. Create Werkzeug-compatible password hash
    $pwHash    = generate_werkzeug_password($rc_pass);
    $fullName  = trim($rc_first . ' ' . $rc_last);
    $employeeId = $rc_user ?: ($new_cid . '-SA');

    // Ensure unique employee_id
    $chkEmp = $pdo->prepare("SELECT id FROM employees WHERE client_id=? AND employee_id=?");
    $chkEmp->execute([$new_cid, $employeeId]);
    if ($chkEmp->fetch()) {
        $employeeId .= '-' . strtoupper(substr(md5(microtime()), 0, 4));
    }

    // 4. Insert Super Admin into employees (portal login)
    $pdo->prepare("
        INSERT INTO employees
            (client_id, employee_id, full_name, email, phone, role, access_level,
             password_hash, status, created_by)
        VALUES (?, ?, ?, ?, ?, 'superadmin', 'ADMIN', ?, 'active', 'INDSAC-ADMIN')
    ")->execute([$new_cid, $employeeId, $fullName, $rc_email, $rc_mobile, $pwHash]);

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

    // 5. Assign license if plan + machine ID provided
    $licenseNote = '';
    $licenseExpiry = null;
    if ($rc_plan && $rc_machine) {
        $planRow = $db->prepare("SELECT * FROM plans WHERE plan_code = ? AND is_active = 1");
        $planRow->execute([$rc_plan]);
        $planData = $planRow->fetch();
        if ($planData) {
            $start  = date('Y-m-d');
            $expiry = date('Y-m-d', strtotime("+{$planData['days']} days"));
            $licKey = hash('sha256', $new_cid . $rc_machine . $rc_plan . microtime());
            insert_license_record(
                $db, $rc_machine, $rc_plan, $start, $expiry, false,
                $fullName, $rc_email, $rc_mobile,
                'MANUAL-ADMIN-' . strtoupper(substr($new_cid, -6)),
                (string)$planData['price'], $licKey,
                $new_sid, $new_cid
            );
            $licenseNote   = "Plan: {$planData['name']} | Expires: {$expiry}";
            $licenseExpiry = $expiry;
        }
    } elseif ($rc_plan && !$rc_machine) {
        $licenseNote = 'Plan selected but no Machine ID — license not assigned yet';
    }

    // 6. Send welcome email
    $portalUrl = DB_CFG_PORTAL_LOGIN_URL;
    $emailHtml = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:'Segoe UI',sans-serif;">
<div style="max-width:520px;margin:20px auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
  <div style="background:linear-gradient(135deg,#667eea,#764ba2);padding:32px;text-align:center;">
    <h1 style="color:white;margin:10px 0 0;font-size:24px;">Welcome to EyeSense!</h1>
    <p style="color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:13px;">Your account has been set up by INDSAC</p>
  </div>
  <div style="padding:32px;">
    <p style="color:#374151;font-size:15px;">Dear <strong>{$fullName}</strong>,</p>
    <p style="color:#6b7280;font-size:14px;line-height:1.7;margin-top:12px;">
      Your EyeSense client account has been created by INDSAC. Use the details below to log in.
    </p>
    <div style="background:#f3f4f6;border-radius:12px;padding:20px;margin:20px 0;">
      <table style="width:100%;font-size:14px;border-collapse:collapse;">
        <tr><td style="padding:7px 0;color:#9ca3af;width:130px;">Client ID</td><td style="font-weight:700;color:#667eea;font-family:monospace;font-size:15px;">{$new_cid}</td></tr>
        <tr><td style="padding:7px 0;color:#9ca3af;">Username</td><td style="font-family:monospace;">{$employeeId}</td></tr>
        <tr><td style="padding:7px 0;color:#9ca3af;">Password</td><td style="font-family:monospace;">{$rc_pass}</td></tr>
        <tr><td style="padding:7px 0;color:#9ca3af;">Email</td><td>{$rc_email}</td></tr>
      </table>
    </div>
    <p style="color:#ef4444;font-size:13px;"><strong>Note:</strong> Change your password on first login for security.</p>
    <div style="text-align:center;margin:24px 0;">
      <a href="{$portalUrl}" style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:600;font-size:14px;">Login to EyeSense Portal</a>
    </div>
  </div>
  <div style="padding:16px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">
    <p style="color:#9ca3af;margin:0;font-size:11px;">EyeSense Cloud Platform • Powered by INDSAC</p>
  </div>
</div>
</body></html>
HTML;
    $emailSent = indsacSendEmail($rc_email, 'Welcome to EyeSense : Your Account Details', $emailHtml);

    echo json_encode([
        'success'       => true,
        'client_id'     => $new_cid,
        'society_id'    => $new_sid,
        'employee_id'   => $employeeId,
        'license_note'  => $licenseNote,
        'license_expiry'=> $licenseExpiry,
        'email_sent'    => $emailSent,
        'society_name'  => $rc_society,
        'message'       => "Society added successfully! Client ID: {$new_cid}" .
                           ($licenseNote ? " | {$licenseNote}" : '') .
                           ($emailSent ? " | Welcome email sent." : ' | Email send failed (check SMTP config).'),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'trace'   => substr($e->getTraceAsString(), 0, 500),
    ]);
}
