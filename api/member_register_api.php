<?php
/**
 * EyeSense — Member Self-Registration API
 * Handles: submit, list, approve, reject
 */
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/maintenance_notify.php';
require_once __DIR__ . '/portal_notifications_helper.php';

header('Content-Type: application/json; charset=utf-8');

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? ($_GET['action'] ?? '');
$pdo    = get_indsac_db();

// Helper: hash token ↔ client_id
function encodeRegToken(string $clientId): string {
    return rtrim(strtr(base64_encode($clientId . '|' . hash_hmac('sha256', $clientId, 'eyesense-reg-2026')), '+/', '-_'), '=');
}
function decodeRegToken(string $token): ?string {
    $decoded = base64_decode(strtr($token, '-_', '+/'));
    if (!$decoded) return null;
    $parts = explode('|', $decoded, 2);
    if (count($parts) !== 2) return null;
    $expected = hash_hmac('sha256', $parts[0], 'eyesense-reg-2026');
    return hash_equals($expected, $parts[1]) ? $parts[0] : null;
}

// ── Werkzeug-compatible hash ──────────────────────────────────────────
function reg_werkzeug_hash(string $password): string {
    $salt = bin2hex(random_bytes(16));
    $hash = hash_pbkdf2('sha256', $password, $salt, 600000, 0);
    return "pbkdf2:sha256:600000\${$salt}\${$hash}";
}

try {
    switch ($action) {

        // ── Generate registration link (admin) ────────────────────────
        case 'get_link':
            portal_session_start();
            $cid = $_SESSION['portal_client_id'] ?? '';
            if (!$cid) json_error('Unauthorized', 401);
            $token = encodeRegToken($cid);
            $link = rtrim(DB_CFG_PORTAL_BASE_URL, '/') . '/register_member.php?token=' . urlencode($token);
            json_response(['success' => true, 'link' => $link, 'token' => $token]);
            break;

        // ── Public: Submit registration request ───────────────────────
        case 'submit':
            $token = trim($input['token'] ?? '');
            $clientId = decodeRegToken($token);
            if (!$clientId) json_error('Invalid or expired registration link.');

            $loginId   = trim($input['login_id'] ?? '');
            $password  = $input['password'] ?? '';
            $fullName  = trim($input['full_name'] ?? '');
            $flat      = trim($input['flat_number'] ?? '');
            $email     = trim($input['email'] ?? '');
            $mobile    = trim($input['mobile'] ?? '');
            $plotSqft  = max(0, (float)($input['plot_size_sqft'] ?? 0));
            $amount    = (float)($input['monthly_amount'] ?? 0);
            $dueDay    = (int)($input['due_day'] ?? 10);
            $notes     = trim($input['notes'] ?? '');

            if (!$loginId || strlen($loginId) < 3) json_error('Login ID must be at least 3 characters');
            if (!preg_match('/^[a-zA-Z0-9._@-]+$/', $loginId)) json_error('Login ID can only contain letters, numbers, dots, underscores, @ and hyphens');
            if (strlen($password) < 4) json_error('Password must be at least 4 characters');
            if (!$fullName) json_error('Full name is required');
            if (!$flat) json_error('Flat number is required');

            // Check duplicate login_id
            $dup = $pdo->prepare("SELECT id FROM employees WHERE client_id=? AND employee_id=?");
            $dup->execute([$clientId, $loginId]);
            if ($dup->fetch()) json_error("Login ID '{$loginId}' is already taken.");

            // Check duplicate pending request
            $dupReq = $pdo->prepare("SELECT id FROM member_registration_requests WHERE client_id=? AND login_id=? AND status='PENDING'");
            $dupReq->execute([$clientId, $loginId]);
            if ($dupReq->fetch()) json_error("A registration request with this Login ID is already pending.");

            $hash = reg_werkzeug_hash($password);
            // Insert — plot_size_sqft column added by migration v5; fall back gracefully if column missing
            try {
                $pdo->prepare("
                    INSERT INTO member_registration_requests
                        (client_id, full_name, flat_number, email, mobile, login_id, password_hash, monthly_amount, due_day, notes, plot_size_sqft)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$clientId, $fullName, $flat, $email ?: null, $mobile ?: null, $loginId, $hash, $amount, $dueDay, $notes, $plotSqft]);
            } catch (PDOException $colErr) {
                // Fallback: column may not exist on older installs
                $pdo->prepare("
                    INSERT INTO member_registration_requests
                        (client_id, full_name, flat_number, email, mobile, login_id, password_hash, monthly_amount, due_day, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$clientId, $fullName, $flat, $email ?: null, $mobile ?: null, $loginId, $hash, $amount, $dueDay, $notes]);
            }

            $reqId = (int)$pdo->lastInsertId();

            // Notify admin
            sendRegistrationRequestToAdmin($pdo, $clientId, [
                'id' => $reqId, 'full_name' => $fullName, 'flat_number' => $flat,
                'email' => $email, 'mobile' => $mobile, 'login_id' => $loginId,
                'monthly_amount' => $amount, 'due_day' => $dueDay, 'notes' => $notes
            ]);

            try {
                portalNotifyMaintenanceAdmins(
                    $pdo,
                    $clientId,
                    'New Registration Request',
                    "{$fullName} from Flat {$flat} is waiting for review.",
                    'WARNING',
                    'registration_requests.php',
                    ['request_id' => $reqId, 'event' => 'registration_request']
                );
            } catch (Throwable $e) {
                error_log('[PORTAL NOTIFY] registration request: ' . $e->getMessage());
            }

            // Notify member
            if ($email) sendRegistrationAckToMember($email, $fullName);

            json_response(['success' => true, 'message' => 'Registration request submitted! The admin will review and approve your account.']);
            break;

        // ── Admin: List pending requests ───────────────────────────────
        case 'list':
            portal_session_start();
            $cid = $_SESSION['portal_client_id'] ?? '';
            if (!$cid) json_error('Unauthorized', 401);
            $status = $input['status'] ?? ($_GET['status'] ?? 'PENDING');
            $rows = $pdo->prepare("SELECT * FROM member_registration_requests WHERE client_id=? AND status=? ORDER BY created_at DESC");
            $rows->execute([$cid, $status]);
            json_response(['success' => true, 'requests' => $rows->fetchAll()]);
            break;

        // ── Admin: Approve (with optional edits) ──────────────────────
        case 'approve':
            portal_session_start();
            $cid = $_SESSION['portal_client_id'] ?? '';
            if (!$cid) json_error('Unauthorized', 401);
            $reqId = (int)($input['request_id'] ?? 0);
            if (!$reqId) json_error('Request ID required');

            $req = $pdo->prepare("SELECT * FROM member_registration_requests WHERE id=? AND client_id=? AND status='PENDING'");
            $req->execute([$reqId, $cid]);
            $r = $req->fetch();
            if (!$r) json_error('Request not found or already processed.');

            // Admin overrides — fall back to original values if not provided
            $newFullName  = trim($input['full_name']  ?? $r['full_name']);
            $newFlat      = trim($input['flat_number'] ?? $r['flat_number']);
            $newEmail     = trim($input['email']       ?? $r['email'] ?? '');
            $newMobile    = trim($input['mobile']      ?? $r['mobile'] ?? '');
            $newLoginId   = trim($input['login_id']    ?? $r['login_id']);
            $newAmount    = isset($input['monthly_amount']) ? (float)$input['monthly_amount'] : (float)$r['monthly_amount'];
            $newDueDay    = isset($input['due_day'])  ? (int)$input['due_day']  : (int)$r['due_day'];
            $newNotes     = trim($input['notes']       ?? $r['notes'] ?? '');

            // Validate edited login_id uniqueness if changed
            if ($newLoginId !== $r['login_id']) {
                $dup = $pdo->prepare("SELECT id FROM employees WHERE client_id=? AND employee_id=?");
                $dup->execute([$cid, $newLoginId]);
                if ($dup->fetch()) json_error("Login ID '{$newLoginId}' is already taken. Choose another.");
            }

            // Detect what changed for the email
            $changes = [];
            if ($newFullName !== $r['full_name'])         $changes['Name']             = ['from' => $r['full_name'],           'to' => $newFullName];
            if ($newFlat !== $r['flat_number'])           $changes['Flat / Unit']       = ['from' => $r['flat_number'],         'to' => $newFlat];
            if ($newEmail !== ($r['email'] ?? ''))        $changes['Email']             = ['from' => $r['email'] ?: 'None',     'to' => $newEmail ?: 'None'];
            if ($newMobile !== ($r['mobile'] ?? ''))      $changes['Mobile']            = ['from' => $r['mobile'] ?: 'None',    'to' => $newMobile ?: 'None'];
            if ($newLoginId !== $r['login_id'])           $changes['Login ID']          = ['from' => $r['login_id'],            'to' => $newLoginId];
            if (abs($newAmount - (float)$r['monthly_amount']) > 0.001) $changes['Monthly Amount'] = ['from' => 'Rs. '.number_format($r['monthly_amount'],2), 'to' => 'Rs. '.number_format($newAmount,2)];
            if ($newDueDay !== (int)$r['due_day'])        $changes['Due Day']           = ['from' => $r['due_day'],             'to' => $newDueDay];

            // Save edits to request record before creating account
            $pdo->prepare("
                UPDATE member_registration_requests
                SET full_name=?, flat_number=?, email=?, mobile=?, login_id=?, monthly_amount=?, due_day=?, notes=?
                WHERE id=?
            ")->execute([$newFullName, $newFlat, $newEmail ?: null, $newMobile ?: null, $newLoginId, $newAmount, $newDueDay, $newNotes, $reqId]);

            // Generate member code
            $maxCode = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(member_code, 5) AS UNSIGNED)) FROM society_members WHERE client_id=?");
            $maxCode->execute([$cid]);
            $memberCode = 'MBR-' . str_pad(($maxCode->fetchColumn() ?: 0) + 1, 4, '0', STR_PAD_LEFT);

            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    INSERT INTO employees (client_id, employee_id, full_name, email, phone, role, access_level, status, password_hash, created_by)
                    VALUES (?, ?, ?, ?, ?, 'MEMBER', 'L0', 'active', ?, ?)
                ")->execute([$cid, $newLoginId, $newFullName, $newEmail ?: null, $newMobile ?: null, $r['password_hash'], $_SESSION['portal_username'] ?? 'admin']);

                $pdo->prepare("
                    INSERT INTO society_members (client_id, member_code, full_name, flat_number, email, mobile, employee_id, monthly_amount, due_day, notes, plot_size_sqft, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$cid, $memberCode, $newFullName, $newFlat, $newEmail ?: null, $newMobile ?: null, $newLoginId, $newAmount, $newDueDay, $newNotes, (float)($r['plot_size_sqft'] ?? 0), $_SESSION['portal_username'] ?? 'admin']);

                $pdo->prepare("INSERT IGNORE INTO portal_role_permissions (client_id, role, permission, granted) VALUES (?, 'MEMBER', 'VIEW_MAINTENANCE', 1)")->execute([$cid]);

                $pdo->prepare("UPDATE member_registration_requests SET status='APPROVED', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                    ->execute([$_SESSION['portal_username'] ?? 'admin', $reqId]);

                $pdo->commit();

                // Notify member — include changes if any
                if ($newEmail) sendRegistrationApproved($newEmail, $newFullName, $cid, $newLoginId, $changes);
                try {
                    portalNotify(
                        $pdo,
                        $cid,
                        $newLoginId,
                        'Registration Approved',
                        'Your member account has been approved. You can now view bills and receipts.',
                        'SUCCESS',
                        'my_dues.php',
                        ['request_id' => $reqId, 'event' => 'registration_approved']
                    );
                } catch (Throwable $e) {
                    error_log('[PORTAL NOTIFY] registration approved: ' . $e->getMessage());
                }

                json_response(['success' => true, 'message' => "Member {$newFullName} approved and account created." . (count($changes) ? ' (' . count($changes) . ' field(s) updated by admin)' : '')]);
            } catch (Throwable $e) {
                $pdo->rollBack();
                json_error('Failed to create member: ' . $e->getMessage(), 500);
            }
            break;


        // ── Admin: Reject ─────────────────────────────────────────────
        case 'reject':
            portal_session_start();
            $cid = $_SESSION['portal_client_id'] ?? '';
            if (!$cid) json_error('Unauthorized', 401);
            $reqId  = (int)($input['request_id'] ?? 0);
            $reason = trim($input['reason'] ?? '');
            if (!$reqId) json_error('Request ID required');
            if (!$reason) json_error('Rejection reason is required');

            $req = $pdo->prepare("SELECT * FROM member_registration_requests WHERE id=? AND client_id=? AND status='PENDING'");
            $req->execute([$reqId, $cid]);
            $r = $req->fetch();
            if (!$r) json_error('Request not found or already processed.');

            $pdo->prepare("UPDATE member_registration_requests SET status='REJECTED', reject_reason=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                ->execute([$reason, $_SESSION['username'] ?? 'admin', $reqId]);

            if ($r['email']) sendRegistrationRejected($r['email'], $r['full_name'], $reason);

            json_response(['success' => true, 'message' => "Request from {$r['full_name']} rejected."]);
            break;

        default:
            json_error('Unknown action');
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}

// ── Email helpers ─────────────────────────────────────────────────────

function sendRegistrationRequestToAdmin(PDO $pdo, string $cid, array $r): void {
    $s = _mSettings($pdo, $cid);
    $admins = _adminEmails($s);
    $society = $s['society_name'] ?? 'Our Society';
    if (empty($admins)) return;

    $body = '<p style="color:#374151;font-size:14px;margin:0 0 16px;">A new member registration request has been submitted. Please review and approve or reject.</p>'
        . '<div style="background:#f9fafb;border-radius:10px;padding:18px;border:1px solid #e5e7eb;">'
        . _table([
            _row('Name', htmlspecialchars($r['full_name'])),
            _row('Flat / Unit', htmlspecialchars($r['flat_number'])),
            _row('Email', htmlspecialchars($r['email'] ?: 'Not provided')),
            _row('Mobile', htmlspecialchars($r['mobile'] ?: 'Not provided')),
            _row('Login ID', htmlspecialchars($r['login_id'])),
            _row('Monthly Amount', 'Rs. ' . number_format($r['monthly_amount'], 2)),
            _row('Due Day', $r['due_day']),
            _divider(),
            _row('Notes', htmlspecialchars($r['notes'] ?: 'None')),
        ])
        . '</div>'
        . '<div style="margin-top:20px;padding:14px;background:#fef9c3;border-radius:8px;border:1px solid #fde68a;text-align:center;">'
        . '<p style="color:#92400e;margin:0;font-size:13px;font-weight:bold;">Action Required: Log in to approve or reject this registration request.</p></div>';

    $html = _emailTpl('#4f46e5', 'New Member Registration Request', $society, $body);
    _send($admins, "[EyeSense] New Registration - {$r['full_name']} | Flat {$r['flat_number']}", $html);
}

function sendRegistrationAckToMember(string $email, string $name): void {
    $body = '<p style="color:#374151;font-size:15px;">Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;line-height:1.6;margin:8px 0 16px;">Thank you for submitting your registration request. The administrator will review your details and verify your account.</p>'
        . '<div style="margin-top:16px;padding:14px;background:#ecfdf5;border-radius:8px;border:1px solid #a7f3d0;">'
        . '<p style="color:#065f46;margin:0;font-size:13px;">Once verified, you will receive a confirmation email and can then log in to the portal to manage your maintenance payments.</p>'
        . '</div>';
    $html = _emailTpl('#4f46e5', 'Registration Request Received', 'EyeSense Cloud Portal', $body);
    indsacSendEmail($email, '[EyeSense] Registration Request Received', $html);
}

function sendRegistrationApproved(string $email, string $name, string $clientId, string $loginId, array $changes = []): void {
    $changesHtml = '';
    if (!empty($changes)) {
        $rows = '';
        foreach ($changes as $field => $v) {
            $rows .= '<tr>'
                . '<td style="padding:6px 12px;font-size:12px;color:#6b7280;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($field) . '</td>'
                . '<td style="padding:6px 12px;font-size:12px;color:#dc2626;text-decoration:line-through;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars((string)$v['from']) . '</td>'
                . '<td style="padding:6px 12px;font-size:12px;color:#059669;font-weight:bold;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars((string)$v['to']) . '</td>'
                . '</tr>';
        }
        $changesHtml = '<div style="margin-top:16px;background:#fffbeb;border-radius:10px;padding:14px;border:1px solid #fde68a;">'
            . '<p style="color:#92400e;font-size:13px;font-weight:bold;margin:0 0 10px;">Note: The admin made the following adjustments to your registration details:</p>'
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="padding:5px 12px;font-size:11px;color:#6b7280;text-align:left;border-bottom:2px solid #e5e7eb;">Field</th>'
            . '<th style="padding:5px 12px;font-size:11px;color:#6b7280;text-align:left;border-bottom:2px solid #e5e7eb;">You Submitted</th>'
            . '<th style="padding:5px 12px;font-size:11px;color:#6b7280;text-align:left;border-bottom:2px solid #e5e7eb;">Approved As</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    $body = '<p style="color:#374151;font-size:15px;">Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;line-height:1.6;margin:8px 0 16px;">Congratulations! Your registration has been <strong style="color:#059669;">APPROVED</strong> by the administrator. You can now log in to the portal.</p>'
        . '<div style="background:#f9fafb;border-radius:10px;padding:18px;border:1px solid #e5e7eb;">'
        . _table([
            _row('Client ID', htmlspecialchars($clientId)),
            _row('Login ID', htmlspecialchars($loginId)),
            _row('Password', 'Use the password you set during registration'),
        ])
        . '</div>'
        . $changesHtml
        . '<p style="color:#6b7280;font-size:13px;margin-top:16px;">Log in at the EyeSense Cloud Portal to view your bills and upload payment receipts.</p>';
    $html = _emailTpl('#059669', 'Registration Approved - Welcome!', 'EyeSense Cloud Portal', $body);
    indsacSendEmail($email, '[EyeSense] Registration Approved - You Can Now Log In', $html);
}


function sendRegistrationRejected(string $email, string $name, string $reason): void {
    $body = '<p style="color:#374151;font-size:15px;">Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;margin:8px 0 16px;">Unfortunately, your registration request has been <strong style="color:#dc2626;">rejected</strong> by the administrator.</p>'
        . '<div style="background:#fef2f2;border-radius:10px;padding:18px;border:1px solid #fecaca;">'
        . '<p style="color:#b91c1c;font-size:13px;font-weight:bold;margin:0 0 4px;">Reason:</p>'
        . '<p style="color:#991b1b;font-size:13px;margin:0;">' . htmlspecialchars($reason) . '</p></div>'
        . '<p style="color:#6b7280;font-size:13px;margin-top:16px;">If you believe this is an error, please contact your society administrator directly.</p>';
    $html = _emailTpl('#dc2626', 'Registration Request Rejected', 'EyeSense Cloud Portal', $body);
    indsacSendEmail($email, '[EyeSense] Registration Request Rejected', $html);
}
