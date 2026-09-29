<?php
/**
 * EyeSense Cloud Portal â€” Tenant Management API
 * Branch: 78-es-78-tenant-management--client-profile-module
 *
 * Actions (authenticated):
 *   list, my_list, add, update, delete, approve, reject,
 *   gen_link, get_settings, save_settings, get_members
 * Actions (public, token-based):
 *   self_register
 */

require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/tenant_email_helpers.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/portal_notifications_helper.php';

define('TENANT_TOKEN_TTL', 5 * 3600);
define('TENANT_TOKEN_SECRET', DB_CFG_LICENSE_API_SECRET . '_TENANT_REG');
// Upload dir: use configured path or auto-resolve to <api_dir>/uploads/tenant_documents
define('TENANT_UPLOAD_DIR', (defined('DB_CFG_TENANT_UPLOADS_DIR') && DB_CFG_TENANT_UPLOADS_DIR)
    ? DB_CFG_TENANT_UPLOADS_DIR
    : __DIR__ . '/uploads/tenant_documents');

// â”€â”€ Token Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function generate_tenant_token(string $client_id, int $member_id): string {
    $expires = time() + TENANT_TOKEN_TTL;
    $payload = $client_id . '|' . $member_id . '|' . $expires;
    $b64pay  = rtrim(base64_encode($payload), '=');
    $hmac    = rtrim(base64_encode(hash_hmac('sha256', $b64pay, TENANT_TOKEN_SECRET, true)), '=');
    return strtr($b64pay . '.' . $hmac, '+/', '-_');
}

function verify_tenant_token(string $token): ?array {
    $parts = explode('.', strtr($token, '-_', '+/'));
    if (count($parts) !== 2) return null;
    [$b64pay, $b64sig] = $parts;
    $pad      = fn($s) => $s . str_repeat('=', (4 - strlen($s) % 4) % 4);
    $expected = rtrim(base64_encode(hash_hmac('sha256', $b64pay, TENANT_TOKEN_SECRET, true)), '=');
    if (!hash_equals($expected, $b64sig)) return null;
    $raw  = base64_decode($pad($b64pay));
    $bits = explode('|', $raw, 3);
    if (count($bits) !== 3) return null;
    [$client_id, $member_id, $expires] = $bits;
    if ((int)$expires < time()) return null;
    return ['client_id' => $client_id, 'member_id' => (int)$member_id];
}

// â”€â”€ Notification helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function tenantNotifyEmail(PDO $pdo, string $subject, string $htmlBody, array $toEmails): void {
    foreach (array_filter($toEmails) as $email) {
        try {
            $ch = curl_init(DB_CFG_EMAIL_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'to'      => $email,
                    'subject' => $subject,
                    'body'    => $htmlBody,
                ]),
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (Throwable $e) { /* non-fatal */ }
    }
}

function tenantEmailHtml(string $heading, string $body): string {
    return "<!DOCTYPE html><html><body style='font-family:sans-serif;background:#f5f5f5;margin:0;padding:20px;'>"
         . "<div style='max-width:560px;margin:auto;background:white;border-radius:12px;overflow:hidden;'>"
         . "<div style='background:linear-gradient(135deg,#6366f1,#a855f7);padding:28px;text-align:center;'>"
         . "<h2 style='color:white;margin:0;'>" . htmlspecialchars($heading) . "</h2></div>"
         . "<div style='padding:28px;color:#374151;font-size:14px;'>" . $body . "</div>"
         . "</div></body></html>";
}

// â”€â”€ Resolve current member_id from session â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function resolveMemberId(PDO $pdo, string $client_id): ?int {
    $empId = $_SESSION['portal_employee_id'] ?? '';
    if (!$empId) return null;
    $st = $pdo->prepare("SELECT id FROM society_members WHERE client_id=? AND employee_id=? AND status='active' LIMIT 1");
    $st->execute([$client_id, $empId]);
    $row = $st->fetch();
    return $row ? (int)$row['id'] : null;
}

// â”€â”€ Tenant settings defaults â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function getTenantSettings(PDO $pdo, string $cid): array {
    $st = $pdo->prepare("SELECT * FROM maintenance_settings WHERE client_id=? LIMIT 1");
    $st->execute([$cid]);
    $row = $st->fetch() ?: [];
    return [
        'tenant_member_can_add'      => isset($row['tenant_member_can_add'])      ? (bool)$row['tenant_member_can_add']      : true,
        'tenant_member_can_gen_link' => isset($row['tenant_member_can_gen_link']) ? (bool)$row['tenant_member_can_gen_link'] : true,
        'tenant_admin_can_add'       => isset($row['tenant_admin_can_add'])       ? (bool)$row['tenant_admin_can_add']       : true,
        'tenant_sa_can_add'          => isset($row['tenant_sa_can_add'])          ? (bool)$row['tenant_sa_can_add']          : true,
        'tenant_doc_required'        => isset($row['tenant_doc_required'])        ? (bool)$row['tenant_doc_required']        : false,
        'tenant_approval_required'   => isset($row['tenant_approval_required'])   ? (bool)$row['tenant_approval_required']   : true,
        'admin_emails'               => $row['admin_emails'] ?? '',
        'society_name'               => $row['society_name'] ?? 'Our Society',
    ];
}

// â”€â”€ Handle public self_register without session â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody, true) ?: [];
if (empty($input)) $input = array_merge($_GET, $_POST);
$action  = trim($input['action'] ?? $_GET['action'] ?? '');

header('Content-Type: application/json; charset=utf-8');

// Public action â€” no session required
if ($action === 'self_register') {
    try {
    $token = trim($input['token'] ?? '');
    $ctx   = $token ? verify_tenant_token($token) : null;
    if (!$ctx) json_error('Invalid or expired registration link', 400);

    $pdo      = get_indsac_db();
    $cid      = $ctx['client_id'];
    $memberId = $ctx['member_id'];

    // Verify member still exists
    $mst = $pdo->prepare("SELECT full_name, email, flat_number FROM society_members WHERE id=? AND client_id=? AND status='active' LIMIT 1");
    $mst->execute([$memberId, $cid]);
    $member = $mst->fetch();
    if (!$member) json_error('Member not found or inactive', 400);

    $settings = getTenantSettings($pdo, $cid);
    $approvalRequired = $settings['tenant_approval_required'];

    $fullName   = trim($input['full_name'] ?? '');
    $mobile     = trim($input['mobile'] ?? '');
    $flatNumber = trim($input['flat_number'] ?? '');
    if (!$fullName || !$mobile || !$flatNumber) json_error('Full name, mobile, and flat number are required');

    // Handle optional govt ID doc upload (multipart)
    $docPath = null;
    if (!empty($_FILES['govt_id_doc']['tmp_name'])) {
        if (!is_dir(TENANT_UPLOAD_DIR)) mkdir(TENANT_UPLOAD_DIR, 0755, true);
        $ext     = strtolower(pathinfo($_FILES['govt_id_doc']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf','webp'];
        if (!in_array($ext, $allowed)) json_error('ID document must be JPG, PNG, PDF, or WEBP');
        $fname   = 'tid_' . $memberId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest    = TENANT_UPLOAD_DIR . '/' . $fname;
        if (move_uploaded_file($_FILES['govt_id_doc']['tmp_name'], $dest)) {
            $docPath = 'uploads/tenant_documents/' . $fname;
        }
    }

    // Optional portal account
    $portalUsername = trim($input['portal_username'] ?? '') ?: null;
    $portalPassword = trim($input['portal_password'] ?? '');
    $portalHash     = ($portalUsername && $portalPassword) ? password_hash($portalPassword, PASSWORD_BCRYPT) : null;

    // Self-registered: always follow approval_required (Pending or Active)
    $status = $approvalRequired ? 'Pending' : 'Active';

    $st = $pdo->prepare("
        INSERT INTO society_tenants
          (client_id, member_id, full_name, email, mobile, alt_mobile, gender, dob,
           plot_number, block_wing, floor_number, flat_number, occupancy_start, occupancy_end,
           govt_id_type, govt_id_number, govt_id_doc_path,
           permanent_address, perm_city, perm_state, perm_country, perm_pincode,
           purpose, occupation, company_name,
           emergency_contact_name, emergency_contact_number, notes,
           portal_username, portal_password_hash,
           registration_status, registered_via, registered_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'')
    ");
    $st->execute([
        $cid, $memberId,
        $fullName,
        trim($input['email'] ?? '') ?: null,
        $mobile,
        trim($input['alt_mobile'] ?? '') ?: null,
        trim($input['gender'] ?? '') ?: null,
        trim($input['dob'] ?? '') ?: null,
        trim($input['plot_number'] ?? '') ?: null,
        trim($input['block_wing'] ?? '') ?: null,
        trim($input['floor_number'] ?? '') ?: null,
        $flatNumber,
        trim($input['occupancy_start'] ?? '') ?: null,
        trim($input['occupancy_end'] ?? '') ?: null,
        trim($input['govt_id_type'] ?? '') ?: null,
        trim($input['govt_id_number'] ?? '') ?: null,
        $docPath,
        trim($input['permanent_address'] ?? '') ?: null,
        trim($input['perm_city'] ?? '') ?: null,
        trim($input['perm_state'] ?? '') ?: null,
        trim($input['perm_country'] ?? '') ?: 'India',
        trim($input['perm_pincode'] ?? '') ?: null,
        trim($input['purpose'] ?? '') ?: null,
        trim($input['occupation'] ?? '') ?: null,
        trim($input['company_name'] ?? '') ?: null,
        trim($input['emergency_contact_name'] ?? '') ?: null,
        trim($input['emergency_contact_number'] ?? '') ?: null,
        trim($input['notes'] ?? '') ?: null,
        $portalUsername,
        $portalHash,
        $status,
        'Link',
    ]);
    $tenantId = (int)$pdo->lastInsertId();
    // Email tenant: Registration Submitted
    tenantEmailSubmitted($pdo, $cid, ['full_name'=>$fullName,'email'=>trim($input['email']??''),'mobile'=>$mobile,'flat_number'=>$flatNumber]);

    // Notify member ONLY (not admins) — tenant registered via member's link
    $notifyEmails = array_filter([trim($member['email'] ?? '')]);
    $societyName = htmlspecialchars($settings['society_name']);
    $body = "<p>A new tenant <strong>" . htmlspecialchars($fullName) . "</strong> has self-registered via your registration link.</p>"
          . "<p><b>Flat:</b> " . htmlspecialchars($flatNumber) . " | <b>Mobile:</b> " . htmlspecialchars($mobile) . "</p>"
          . "<p><b>Status:</b> " . htmlspecialchars($status) . ($approvalRequired ? " &#8212; Pending admin review." : " &#8212; Approved automatically.") . "</p>";
    if ($notifyEmails) {
        tenantNotifyEmail($pdo, "New Tenant Registered &#8212; {$societyName}", tenantEmailHtml("New Tenant Registered", $body), $notifyEmails);
    }

    // In-app Notification: Notify all maintenance admins
    try {
        portalNotifyMaintenanceAdmins(
            $pdo,
            $cid,
            "New Tenant Registration Request",
            "Tenant {$fullName} has registered for Flat {$flatNumber} and is awaiting review.",
            $approvalRequired ? "WARNING" : "SUCCESS",
            "tenants.php"
        );
    } catch (Throwable $e) { /* non-fatal */ }

    // In-app Notification: Notify the owner
    try {
        $recipientEmployeeId = portalGetMemberEmployeeId($pdo, $cid, (int)$memberId);
        if ($recipientEmployeeId) {
            portalNotify(
                $pdo,
                $cid,
                $recipientEmployeeId,
                "New Tenant Self-Registration",
                "Tenant {$fullName} has registered for Flat {$flatNumber}." . ($approvalRequired ? " Awaiting admin review." : " Approved automatically."),
                $approvalRequired ? "WARNING" : "SUCCESS",
                "my_tenants.php"
            );
        }
    } catch (Throwable $e) { /* non-fatal */ }

    json_response(['success' => true, 'tenant_id' => $tenantId, 'status' => $status,
        'message' => $approvalRequired
            ? 'Registration submitted! Your house owner will review and approve your application. You will be notified once approved.'
            : 'Registration complete! You are now an active tenant.'
    ]);
    } catch (Throwable $e) {
        json_error('Registration failed: ' . $e->getMessage(), 500);
    }
    exit;
}

// ── All other actions require session ──────────────────────────────────────────────────────────

requirePortalLogin();
$pdo     = get_indsac_db();
require_once __DIR__ . '/society_context_holder.php';
SocietyContextHolder::initFromRequest($pdo);
$cid = SocietyContextHolder::getLegacyClientId($pdo);
if (!$cid) {
    json_error('Authentication required: no active society context found.', 401);
}
$session = getPortalSession();
$isSA    = !empty($session['is_superadmin']);
$role    = SocietyContextHolder::getCurrentRole() ?: ($session['role'] ?? 'Unassigned');
$by      = $session['username'] ?? 'admin';
$perms   = getAllPermissions($pdo, $cid, $role);
$isAdmin = $isSA || !empty($perms['MANAGE_MAINTENANCE']);
$settings = getTenantSettings($pdo, $cid);

try {
    switch ($action) {

        // ── LIST (admin/SA) ───────────────────────────────────────────────────────────────────
        case 'list':
            if (!$isAdmin) json_error('Access denied', 403);
            $memberId = (int)($input['member_id'] ?? 0);
            $status   = trim($input['status'] ?? '');
            $purpose  = trim($input['purpose'] ?? '');
            $search   = trim($input['search'] ?? '');
            $history  = (int)($input['history'] ?? 0);

            $sql = "SELECT t.*, sm.full_name AS member_name, sm.flat_number AS member_flat,
                           sm.mobile AS member_mobile, sm.block_wing AS member_block
                    FROM society_tenants t
                    JOIN society_members sm ON sm.id = t.member_id AND sm.client_id = t.client_id
                    WHERE t.client_id = ?";
            $params = [$cid];
            
            if ($history) {
                $sql .= " AND (t.registration_status IN ('Inactive', 'Rejected') OR (t.occupancy_end IS NOT NULL AND t.occupancy_end < CURDATE()))";
            } else {
                $sql .= " AND t.registration_status IN ('Active', 'Pending') AND (t.occupancy_end IS NULL OR t.occupancy_end >= CURDATE())";
            }

            if ($memberId > 0)  { $sql .= " AND t.member_id = ?"; $params[] = $memberId; }
            if ($status)        { $sql .= " AND t.registration_status = ?"; $params[] = $status; }
            if ($purpose)       { $sql .= " AND t.purpose = ?"; $params[] = $purpose; }
            if ($search)        {
                $sql .= " AND (t.full_name LIKE ? OR t.mobile LIKE ? OR t.flat_number LIKE ? OR t.email LIKE ?)";
                $like = "%{$search}%";
                $params = array_merge($params, [$like, $like, $like, $like]);
            }
            $sql .= " ORDER BY t.created_at DESC";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            json_response(['tenants' => $st->fetchAll()]);
            break;

        // ── MY LIST (member) ───────────────────────────────────────────────────
        case 'my_list':
            $memberId = resolveMemberId($pdo, $cid);
            if (!$memberId) json_error('Member record not found for your account', 404);
            $history  = (int)($input['history'] ?? 0);
            
            $sql = "SELECT * FROM society_tenants WHERE client_id=? AND member_id=?";
            $params = [$cid, $memberId];
            
            if ($history) {
                $sql .= " AND (registration_status IN ('Inactive', 'Rejected') OR (occupancy_end IS NOT NULL AND occupancy_end < CURDATE()))";
            } else {
                $sql .= " AND registration_status IN ('Active', 'Pending') AND (occupancy_end IS NULL OR occupancy_end >= CURDATE())";
            }
            
            $sql .= " ORDER BY created_at DESC";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            json_response(['tenants' => $st->fetchAll()]);
            break;


        case 'add':
            $isMember = !$isAdmin;
            if ($isMember) {
                $memberId = resolveMemberId($pdo, $cid);
                if (!$memberId) json_error('Member record not found', 404);
                if (!$settings['tenant_member_can_add']) json_error('Members are not allowed to add tenants (disabled by admin)', 403);
            } else {
                $memberId = (int)($input['member_id'] ?? 0);
                if ($memberId <= 0) {
                    // Admin viewing member portal â€” try their own member record
                    $memberId = resolveMemberId($pdo, $cid);
                    if (!$memberId) json_error('Please select a member for this tenant', 400);
                }
            }

            $fullName   = trim($input['full_name'] ?? '');
            $mobile     = trim($input['mobile'] ?? '');
            $flatNumber = trim($input['flat_number'] ?? '');
            if (!$fullName || !$mobile || !$flatNumber) json_error('full_name, mobile and flat_number are required');

            // Handle optional govt ID document upload (supports multipart FormData)
            $docPath = null;
            if (!empty($_FILES['govt_id_doc']['tmp_name'])) {
                $uploadDir = TENANT_UPLOAD_DIR;
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext     = strtolower(pathinfo($_FILES['govt_id_doc']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','pdf','webp'];
                if (!in_array($ext, $allowed)) json_error('ID document must be JPG, PNG, PDF, or WEBP');
                if ($_FILES['govt_id_doc']['size'] > 5 * 1024 * 1024) json_error('ID document must be under 5 MB');
                $fname = 'tid_' . $memberId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['govt_id_doc']['tmp_name'], $uploadDir . '/' . $fname)) {
                    $docPath = 'uploads/tenant_documents/' . $fname;
                }
            }

            // Portal account credentials (optional)
            $portalUsername = trim($input['portal_username'] ?? '') ?: null;
            $portalPassword = trim($input['portal_password'] ?? '');
            $portalHash     = ($portalUsername && $portalPassword) ? password_hash($portalPassword, PASSWORD_BCRYPT) : null;

            // Member-manually-added â†’ always Active (member takes responsibility)
            // Admin/SA-added â†’ follows approval_required setting
            $approvalRequired = $settings['tenant_approval_required'];
            $status = $isMember ? 'Active' : ($approvalRequired ? 'Pending' : 'Active');

            $st = $pdo->prepare("
                INSERT INTO society_tenants
                  (client_id, member_id, full_name, email, mobile, alt_mobile, gender, dob,
                   plot_number, block_wing, floor_number, flat_number, occupancy_start, occupancy_end,
                   govt_id_type, govt_id_number, govt_id_doc_path,
                   permanent_address, perm_city, perm_state, perm_country, perm_pincode,
                   purpose, occupation, company_name,
                   emergency_contact_name, emergency_contact_number, notes,
                   portal_username, portal_password_hash,
                   registration_status, registered_via, registered_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Manual',?)
            ");
            $st->execute([
                $cid, $memberId, $fullName,
                trim($input['email'] ?? '') ?: null,
                $mobile,
                trim($input['alt_mobile'] ?? '') ?: null,
                trim($input['gender'] ?? '') ?: null,
                trim($input['dob'] ?? '') ?: null,
                trim($input['plot_number'] ?? '') ?: null,
                trim($input['block_wing'] ?? '') ?: null,
                trim($input['floor_number'] ?? '') ?: null,
                $flatNumber,
                trim($input['occupancy_start'] ?? '') ?: null,
                trim($input['occupancy_end'] ?? '') ?: null,
                trim($input['govt_id_type'] ?? '') ?: null,
                trim($input['govt_id_number'] ?? '') ?: null,
                $docPath,
                trim($input['permanent_address'] ?? '') ?: null,
                trim($input['perm_city'] ?? '') ?: null,
                trim($input['perm_state'] ?? '') ?: null,
                trim($input['perm_country'] ?? '') ?: 'India',
                trim($input['perm_pincode'] ?? '') ?: null,
                trim($input['purpose'] ?? '') ?: null,
                trim($input['occupation'] ?? '') ?: null,
                trim($input['company_name'] ?? '') ?: null,
                trim($input['emergency_contact_name'] ?? '') ?: null,
                trim($input['emergency_contact_number'] ?? '') ?: null,
                trim($input['notes'] ?? '') ?: null,
                $portalUsername,
                $portalHash,
                $status, $by,
            ]);
            $tid = (int)$pdo->lastInsertId();
            logAudit($pdo, $cid, $by, 'TENANT_ADD', "Added tenant #{$tid} {$fullName} for member #{$memberId}");
            json_response(['success' => true, 'tenant_id' => $tid, 'status' => $status]);
            break;

        case 'update':
            $tid = (int)($input['tenant_id'] ?? 0);
            if ($tid <= 0) json_error('tenant_id required');

            // Check authorization
            if (!$isAdmin) {
                $memberId = resolveMemberId($pdo, $cid);
                if (!$memberId) json_error('Member record not found', 404);
                // Verify ownership of the tenant
                $st = $pdo->prepare("SELECT id FROM society_tenants WHERE client_id=? AND id=? AND member_id=?");
                $st->execute([$cid, $tid, $memberId]);
                if (!$st->fetch()) {
                    json_error('Tenant not found or unauthorized', 404);
                }
            }

            $fields = ['full_name','email','mobile','alt_mobile','gender','dob',
                       'plot_number','block_wing','floor_number','flat_number',
                       'occupancy_start','occupancy_end','govt_id_type','govt_id_number',
                       'permanent_address','perm_city','perm_state','perm_country','perm_pincode',
                       'purpose','occupation','company_name',
                       'emergency_contact_name','emergency_contact_number','notes',
                       'registration_status'];
            $sets = []; $params = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $input)) {
                    $sets[]   = "{$f} = ?";
                    $params[] = trim((string)$input[$f]) ?: null;
                }
            }
            if (empty($sets)) json_error('Nothing to update');
            $params[] = $cid; $params[] = $tid;
            $pdo->prepare("UPDATE society_tenants SET " . implode(', ', $sets) . " WHERE client_id=? AND id=?")->execute($params);
            logAudit($pdo, $cid, $by, 'TENANT_UPDATE', "Updated tenant #{$tid}");
            json_response(['success' => true]);
            break;        case 'delete':
            $tid = (int)($input['tenant_id'] ?? 0);
            if ($tid <= 0) json_error('tenant_id required');
            if ($isAdmin) {
                $upd = $pdo->prepare("UPDATE society_tenants SET registration_status='Inactive' WHERE client_id=? AND id=?");
                $upd->execute([$cid, $tid]);
            } else {
                $memberId = resolveMemberId($pdo, $cid);
                if (!$memberId) json_error('Member record not found', 404);
                $upd = $pdo->prepare("UPDATE society_tenants SET registration_status='Inactive' WHERE client_id=? AND id=? AND member_id=?");
                $upd->execute([$cid, $tid, $memberId]);
                if ($upd->rowCount() === 0) json_error('Tenant not found or unauthorized', 404);
            }
            logAudit($pdo, $cid, $by, 'TENANT_DISABLE', "Disabled (soft-deleted) tenant #{$tid}");
            json_response(['success' => true]);
            break;

        // â”€â”€ APPROVE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        case 'approve':
            $tid = (int)($input['tenant_id'] ?? 0);
            if ($tid <= 0) json_error('tenant_id required');
            if ($isAdmin) {
                // Admin can approve any tenant in this client
                $pdo->prepare("UPDATE society_tenants SET registration_status='Active', approved_by=?, approved_at=NOW() WHERE client_id=? AND id=?")
                    ->execute([$by, $cid, $tid]);
            } else {
                // House owner (member) can only approve their own pending tenants
                $memberId = resolveMemberId($pdo, $cid);
                if (!$memberId) json_error('Member record not found', 404);
                $upd = $pdo->prepare("UPDATE society_tenants SET registration_status='Active', approved_by=?, approved_at=NOW() WHERE client_id=? AND id=? AND member_id=? AND registration_status='Pending'");
                $upd->execute([$by, $cid, $tid, $memberId]);
                if ($upd->rowCount() === 0) json_error('Tenant not found or not pending', 404);
            }
            logAudit($pdo, $cid, $by, 'TENANT_APPROVE', "Approved tenant #{$tid}");
            
            // In-app Notification: Notify the owner
            try {
                $tr = $pdo->prepare('SELECT full_name, flat_number, member_id FROM society_tenants WHERE id=? AND client_id=? LIMIT 1');
                $tr->execute([$tid, $cid]);
                $tData = $tr->fetch(PDO::FETCH_ASSOC);
                if ($tData) {
                    $recipientEmployeeId = portalGetMemberEmployeeId($pdo, $cid, (int)$tData['member_id']);
                    if ($recipientEmployeeId) {
                        portalNotify(
                            $pdo,
                            $cid,
                            $recipientEmployeeId,
                            "Tenant Request Approved",
                            "Tenant {$tData['full_name']} for Flat {$tData['flat_number']} has been approved.",
                            "SUCCESS",
                            "my_tenants.php"
                        );
                    }
                }
            } catch (Throwable $e) { /* non-fatal */ }

            // Email tenant: Approved (non-fatal if it fails)
            try {
                $trEmail = $pdo->prepare('SELECT * FROM society_tenants WHERE id=? AND client_id=? LIMIT 1');
                $trEmail->execute([$tid, $cid]);
                $tDataEmail = $trEmail->fetch(PDO::FETCH_ASSOC);
                if ($tDataEmail && !empty($tDataEmail['email'])) {
                    tenantEmailApproved($pdo, $cid, $tDataEmail);
                }
            } catch (Throwable $e) { error_log('[TENANT EMAIL APPROVE] ' . $e->getMessage()); }
            json_response(['success' => true]);
            break;

        // â”€â”€ REJECT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        case 'reject':
            $tid    = (int)($input['tenant_id'] ?? 0);
            $reason = trim($input['reason'] ?? '');
            if ($tid <= 0) json_error('tenant_id required');
            if (!$reason)  json_error('Rejection reason required');
            if ($isAdmin) {
                $pdo->prepare("UPDATE society_tenants SET registration_status='Rejected', approved_by=?, approved_at=NOW(), reject_reason=? WHERE client_id=? AND id=?")
                    ->execute([$by, $reason, $cid, $tid]);
            } else {
                $memberId = resolveMemberId($pdo, $cid);
                if (!$memberId) json_error('Member record not found', 404);
                $upd = $pdo->prepare("UPDATE society_tenants SET registration_status='Rejected', approved_by=?, approved_at=NOW(), reject_reason=? WHERE client_id=? AND id=? AND member_id=? AND registration_status='Pending'");
                $upd->execute([$by, $reason, $cid, $tid, $memberId]);
                if ($upd->rowCount() === 0) json_error('Tenant not found or not pending', 404);
            }
            logAudit($pdo, $cid, $by, 'TENANT_REJECT', "Rejected tenant #{$tid}: {$reason}");
            
            // In-app Notification: Notify the owner
            try {
                $tr2 = $pdo->prepare('SELECT full_name, flat_number, member_id FROM society_tenants WHERE id=? AND client_id=? LIMIT 1');
                $tr2->execute([$tid, $cid]);
                $tData2 = $tr2->fetch(PDO::FETCH_ASSOC);
                if ($tData2) {
                    $recipientEmployeeId = portalGetMemberEmployeeId($pdo, $cid, (int)$tData2['member_id']);
                    if ($recipientEmployeeId) {
                        portalNotify(
                            $pdo,
                            $cid,
                            $recipientEmployeeId,
                            "Tenant Request Rejected",
                            "Tenant {$tData2['full_name']} for Flat {$tData2['flat_number']} was rejected. Reason: {$reason}",
                            "ERROR",
                            "my_tenants.php"
                        );
                    }
                }
            } catch (Throwable $e2) { /* non-fatal */ }

            // Email tenant: Rejected (non-fatal if it fails)
            try {
                $tr2Email = $pdo->prepare('SELECT * FROM society_tenants WHERE id=? AND client_id=? LIMIT 1');
                $tr2Email->execute([$tid, $cid]);
                $tData2Email = $tr2Email->fetch(PDO::FETCH_ASSOC);
                if ($tData2Email && !empty($tData2Email['email'])) {
                    tenantEmailRejected($pdo, $cid, $tData2Email, $reason);
                }
            } catch (Throwable $e2) { error_log('[TENANT EMAIL REJECT] ' . $e2->getMessage()); }
            json_response(['success' => true]);
            break;

        // â”€â”€ GENERATE LINK â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        case 'gen_link':
            $memberId = null;
            if (!$isAdmin) {
                $memberId = resolveMemberId($pdo, $cid);
                if (!$memberId) json_error('Member record not found', 404);
                if (!$settings['tenant_member_can_gen_link']) json_error('Link generation disabled by admin', 403);
            } else {
                $memberId = (int)($input['member_id'] ?? 0);
                if ($memberId <= 0) {
                    // Admin viewing member portal â€” try to resolve their own member record
                    $memberId = resolveMemberId($pdo, $cid);
                    if (!$memberId) json_error('Please select a member from the admin Generate Link button', 400);
                }
            }
            $token = generate_tenant_token($cid, $memberId);
            $url   = rtrim(DB_CFG_APP_BASE_URL, '/') . '/tenant_register.php?token=' . $token;
            $expiresAt = date('d M Y, h:i A', time() + TENANT_TOKEN_TTL);
            logAudit($pdo, $cid, $by, 'TENANT_GEN_LINK', "Generated tenant reg link for member #{$memberId}");
            json_response(['success' => true, 'token' => $token, 'url' => $url,
                'expires_at' => $expiresAt, 'ttl_hours' => 5]);
            break;

        // â”€â”€ GET SETTINGS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        case 'get_settings':
            if (!$isAdmin) json_error('Access denied', 403);
            json_response($settings);
            break;

        // â”€â”€ SAVE SETTINGS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        case 'save_settings':
            if (!$isAdmin) json_error('Access denied', 403);
            // Use UPDATE â€” the maintenance_settings row is created by maintenance_review_api.php
            // If no row exists yet for this client, insert with only required fields
            $vals = [
                (int)(bool)($input['tenant_member_can_add']      ?? 1),
                (int)(bool)($input['tenant_member_can_gen_link'] ?? 1),
                (int)(bool)($input['tenant_admin_can_add']       ?? 1),
                (int)(bool)($input['tenant_sa_can_add']          ?? 1),
                (int)(bool)($input['tenant_doc_required']        ?? 0),
                (int)(bool)($input['tenant_approval_required']   ?? 1),
                $by, $cid,
            ];
            $stmt = $pdo->prepare("
                UPDATE maintenance_settings SET
                    tenant_member_can_add=?, tenant_member_can_gen_link=?,
                    tenant_admin_can_add=?,  tenant_sa_can_add=?,
                    tenant_doc_required=?,   tenant_approval_required=?,
                    updated_by=?
                WHERE client_id=?
            ");
            $affected = $stmt->execute($vals) ? $stmt->rowCount() : 0;

            if (!$affected) {
                // No row yet â€” create a minimal row first, then update
                try {
                    $pdo->prepare("INSERT IGNORE INTO maintenance_settings (client_id) VALUES (?)")->execute([$cid]);
                    array_pop($vals); array_pop($vals); // remove $by, $cid
                    $vals[] = $by; $vals[] = $cid;
                    $pdo->prepare("
                        UPDATE maintenance_settings SET
                            tenant_member_can_add=?, tenant_member_can_gen_link=?,
                            tenant_admin_can_add=?,  tenant_sa_can_add=?,
                            tenant_doc_required=?,   tenant_approval_required=?,
                            updated_by=?
                        WHERE client_id=?
                    ")->execute($vals);
                } catch (Throwable $ei) { /* table may have NOT NULL constraints */ }
            }
            logAudit($pdo, $cid, $by, 'TENANT_SETTINGS_SAVE', 'Saved tenant management settings');
            json_response(['success' => true, 'message' => 'Tenant settings saved']);
            break;

        // â”€â”€ GET MEMBERS (for admin filter dropdown) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        case 'get_members':
            if (!$isAdmin) json_error('Access denied', 403);
            $st = $pdo->prepare("SELECT id, full_name, flat_number, block_wing, member_code FROM society_members WHERE client_id=? AND status='active' ORDER BY flat_number, full_name");
            $st->execute([$cid]);
            json_response(['members' => $st->fetchAll()]);
            break;

        // â”€â”€ STATS (for dashboard cards) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        case 'stats':
            if (!$isAdmin) {
                $memberId = resolveMemberId($pdo, $cid);
                if (!$memberId) json_error('Member not found', 404);
                $base  = "FROM society_tenants WHERE client_id=? AND member_id=?";
                $p     = [$cid, $memberId];
            } else {
                $base  = "FROM society_tenants WHERE client_id=?";
                $p     = [$cid];
            }
                        // Simpler approach:
            $counts = [];
            foreach (['Pending','Active','Rejected','Inactive'] as $s) {
                $st = $pdo->prepare("SELECT COUNT(*) {$base} AND registration_status=?");
                $st->execute(array_merge($p, [$s]));
                $counts[strtolower($s)] = (int)$st->fetchColumn();
            }
            $st = $pdo->prepare("SELECT COUNT(*) {$base}");
            $st->execute($p);
            $counts['total'] = (int)$st->fetchColumn();
            $st = $pdo->prepare("SELECT COUNT(*) {$base} AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')");
            $st->execute($p);
            $counts['this_month'] = (int)$st->fetchColumn();
            json_response($counts);
            break;

        case 'change_password':
            $oldPassword = $input['old_password'] ?? '';
            $newPassword = $input['new_password'] ?? '';
            if (!$oldPassword || !$newPassword) {
                json_error('Current password and new password are required', 400);
            }
            if (strlen($newPassword) < 6) {
                json_error('New password must be at least 6 characters long', 400);
            }
            
            // Determine user type
            if (strtolower($session['role'] ?? '') === 'tenant') {
                $tenantId = $_SESSION['portal_tenant_id'] ?? 0;
                if (!$tenantId) json_error('Tenant session not found', 400);
                
                $st = $pdo->prepare("SELECT portal_password_hash FROM society_tenants WHERE id=? AND client_id=? LIMIT 1");
                $st->execute([$tenantId, $cid]);
                $t = $st->fetch();
                if (!$t) json_error('Tenant record not found', 404);
                
                // Verify old password
                if (empty($t['portal_password_hash']) || !password_verify($oldPassword, $t['portal_password_hash'])) {
                    json_error('Current password is incorrect', 400);
                }
                
                // Hash new password and save
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE society_tenants SET portal_password_hash=? WHERE id=? AND client_id=?")
                    ->execute([$newHash, $tenantId, $cid]);
                
                logAudit($pdo, $cid, $by, 'TENANT_CHANGE_PASSWORD', "Tenant changed password");
            } else {
                // If admin or member
                $empId = $_SESSION['portal_employee_id'] ?? '';
                if (!$empId) json_error('Employee session not found', 400);
                
                $st = $pdo->prepare("SELECT password_hash FROM employees WHERE employee_id=? AND client_id=? LIMIT 1");
                $st->execute([$empId, $cid]);
                $emp = $st->fetch();
                if (!$emp) json_error('Employee record not found', 404);
                
                // Verify old password
                if (empty($emp['password_hash']) || !verify_werkzeug_password($oldPassword, $emp['password_hash'])) {
                    json_error('Current password is incorrect', 400);
                }
                
                // Hash new password using Werkzeug compatible pbkdf2 format to maintain full interoperability
                $newHash = generate_werkzeug_password($newPassword);
                $pdo->prepare("UPDATE employees SET password_hash=? WHERE employee_id=? AND client_id=?")
                    ->execute([$newHash, $empId, $cid]);
                
                logAudit($pdo, $cid, $by, 'EMPLOYEE_CHANGE_PASSWORD', "Employee changed password");
            }
            
            json_response(['success' => true, 'message' => 'Password updated successfully!']);
            break;

        default:
            json_error('Unknown action: ' . htmlspecialchars($action));
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_error('System error: ' . $e->getMessage(), 500);
}

