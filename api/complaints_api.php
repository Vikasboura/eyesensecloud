<?php
/**
 * EyeSense Cloud Portal — Member Complaints API
 * Actions: submit, my_list, list, get, update_status,
 *           assign, unassign, get_assignments, my_assigned,
 *           close, withdraw, reopen
 */
ob_start();
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/maintenance_notify.php';
require_once __DIR__ . '/portal_notifications_helper.php';

header('Content-Type: application/json; charset=utf-8');

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
$perms   = getAllPermissions($pdo, $cid, SocietyContextHolder::getCurrentRole() ?: ($session['role'] ?? ''));
$empId   = trim((string)($_SESSION['portal_employee_id'] ?? ''));
$isAdmin = $isSA || !empty($perms['MANAGE_MAINTENANCE']);

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}
$action = $input['action'] ?? ($_GET['action'] ?? '');
$requestEmpId = trim((string)($input['employee_id'] ?? ($_GET['employee_id'] ?? '')));

$VALID_CATS     = ['General','Maintenance','Security','Noise','Parking','Water','Electricity','Cleanliness','Billing','Other'];
$VALID_PRIORITY = ['LOW','MEDIUM','HIGH','URGENT'];
$VALID_STATUS   = ['REVIEWED','RESOLVED','REJECTED'];
$VALID_LIFECYCLE = ['CLOSED','WITHDRAWN'];

try {
    switch ($action) {

        // ── Submit a new complaint (member) ──────────────────────────────
        case 'submit':
            $mStmt = $pdo->prepare("SELECT * FROM society_members WHERE client_id=? AND employee_id=? AND status='active'");
            $mStmt->execute([$cid, $empId]);
            $member = $mStmt->fetch();
            if (!$member) json_error('You are not registered as a society member.');

            $category    = trim($input['category']    ?? 'General');
            $subject     = trim($input['subject']     ?? '');
            $description = trim($input['description'] ?? '');
            $priority    = strtoupper(trim($input['priority'] ?? 'MEDIUM'));

            if (!$subject)     json_error('Subject is required');
            if (!$description) json_error('Description is required');
            if (!in_array($priority, $VALID_PRIORITY)) $priority = 'MEDIUM';
            if (!in_array($category, $VALID_CATS))     $category = 'General';

            $pdo->prepare("
                INSERT INTO member_complaints
                  (client_id, member_id, employee_id, full_name, flat_number, email, mobile,
                   category, subject, description, priority)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $cid, $member['id'], $empId,
                $member['full_name'], $member['flat_number'],
                $member['email'] ?? null, $member['mobile'] ?? null,
                $category, $subject, $description, $priority
            ]);
            $complaintId = (int)$pdo->lastInsertId();

            // Handle initial file attachments if present
            if (!empty($_FILES['attachments']['name'][0])) {
                $allowedExts = ['jpg','jpeg','png','gif','bmp','webp','mp4','avi','mov','mkv',
                                'pdf','txt','doc','docx','xls','xlsx','csv','zip','rar'];
                $mimeMap = [
                    'jpg'  => 'image/jpeg',  'jpeg' => 'image/jpeg', 'png'  => 'image/png',
                    'gif'  => 'image/gif',   'bmp'  => 'image/bmp',  'webp' => 'image/webp',
                    'mp4'  => 'video/mp4',   'avi'  => 'video/x-msvideo',
                    'mov'  => 'video/quicktime','mkv' => 'video/x-matroska',
                    'pdf'  => 'application/pdf',  'txt'  => 'text/plain',
                    'doc'  => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xls'  => 'application/vnd.ms-excel',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'csv'  => 'text/csv',    'zip'  => 'application/zip',
                    'rar'  => 'application/x-rar-compressed',
                ];

                $maxMb = 5;
                try {
                    $st = $pdo->prepare("SELECT max_attachment_size_mb FROM maintenance_settings WHERE client_id=?");
                    $st->execute([$cid]);
                    $v = (int)$st->fetchColumn();
                    if ($v > 0) $maxMb = max(1, min(50, $v));
                } catch (Throwable $e) {}
                $maxBytes = $maxMb * 1024 * 1024;

                $files = $_FILES['attachments'];
                $count = count($files['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

                    $origName = basename($files['name'][$i]);
                    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                    if (!in_array($ext, $allowedExts)) {
                        continue;
                    }
                    if ($files['size'][$i] > $maxBytes) {
                        $pdo->prepare("DELETE FROM member_complaints WHERE id=?")->execute([$complaintId]);
                        json_error("File '{$origName}' exceeds the {$maxMb} MB limit.");
                    }

                    $dir = rtrim(COMPLAINT_UPLOADS_DIR, '/\\') . "/{$cid}/{$complaintId}";
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    $safeName = time() . '_' . uniqid() . '.' . $ext;
                    $dest     = $dir . '/' . $safeName;
                    if (!move_uploaded_file($files['tmp_name'][$i], $dest)) continue;

                    $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';
                    $sizeKb   = (int)ceil($files['size'][$i] / 1024);
                    $relPath  = "complaint_attachments/{$cid}/{$complaintId}/{$safeName}";

                    $pdo->prepare("
                        INSERT INTO complaint_attachments
                          (complaint_id, comment_id, client_id, file_name, file_path, file_type, file_size_kb, uploaded_by)
                        VALUES (?,NULL,?,?,?,?,?,?)
                    ")->execute([$complaintId, $cid, $origName, $relPath, $mimeType, $sizeKb, $empId]);
                }
            }

            _sendComplaintToAdmin($pdo, $cid, $member, [
                'id'          => $complaintId,
                'category'    => $category,
                'subject'     => $subject,
                'description' => $description,
                'priority'    => $priority,
            ]);

            try {
                $adminRecipients = portalGetMaintenanceAdmins($pdo, $cid);
                portalNotifyMany(
                    $pdo,
                    $cid,
                    $adminRecipients,
                    "New Complaint #{$complaintId}",
                    "{$member['full_name']} raised: {$subject}",
                    'WARNING',
                    'complaints.php',
                    ['complaint_id' => $complaintId, 'event' => 'submit']
                );
            } catch (Throwable $e) {
            }

            try {
                portalNotify(
                    $pdo,
                    $cid,
                    $empId,
                    "Complaint #{$complaintId} Registered",
                    "Your complaint has been registered and is waiting for admin review.",
                    'SUCCESS',
                    'my_complaints.php',
                    ['complaint_id' => $complaintId, 'event' => 'registered']
                );
            } catch (Throwable $e) {
            }

            logAudit($pdo, $cid, $empId, 'SUBMIT_COMPLAINT', "Complaint #{$complaintId}: {$subject}");
            json_response(['success' => true, 'complaint_id' => $complaintId,
                'message' => 'Your complaint has been submitted. The administrator will review it shortly.']);
            break;

        // ── Member: list own complaints ───────────────────────────────────
        case 'my_list':
            $mStmt = $pdo->prepare("SELECT id FROM society_members WHERE client_id=? AND employee_id=? AND status='active'");
            $mStmt->execute([$cid, $empId]);
            $m = $mStmt->fetch();
            if (!$m) json_response(['complaints' => []]);

            $stmt = $pdo->prepare("SELECT * FROM member_complaints WHERE client_id=? AND member_id=? ORDER BY created_at DESC");
            $stmt->execute([$cid, $m['id']]);
            json_response(['complaints' => $stmt->fetchAll()]);
            break;

        // ── List complaints (admin full list, member assigned view) ───────
        case 'list':
            $filterStatus   = $input['status']   ?? ($_GET['status']   ?? '');
            $filterPriority = $input['priority'] ?? ($_GET['priority'] ?? '');
            $filterCategory = $input['category'] ?? ($_GET['category'] ?? '');
            $filterAssignedEmployeeId = trim((string)($input['assigned_employee_id'] ?? ($_GET['assigned_employee_id'] ?? $requestEmpId)));

            $where  = ['c.client_id = ?'];
            $params = [$cid];
            if ($filterStatus)   { $where[] = 'c.status = ?';   $params[] = $filterStatus; }
            if ($filterPriority) { $where[] = 'c.priority = ?'; $params[] = $filterPriority; }
            if ($filterCategory) { $where[] = 'c.category = ?'; $params[] = $filterCategory; }

            $assignedOnly = !empty($input['assigned_to_me']) || !empty($_GET['assigned_to_me']) || $filterAssignedEmployeeId !== '';
            if (!$isAdmin && !$assignedOnly) {
                json_error('Access denied', 403);
            }
            if (!$isAdmin && $assignedOnly) {
                $filterAssignedEmployeeId = $empId;
            }
            if ($assignedOnly) {
                $effectiveEmpIds = _portalEmployeeIdCandidates($pdo, $cid, $session, $filterAssignedEmployeeId ?: $empId);
                if (!$effectiveEmpIds) {
                    json_response(['complaints' => []]);
                }
                $empPlaceholders = implode(',', array_fill(0, count($effectiveEmpIds), '?'));
                $where[] = "EXISTS (
                    SELECT 1 FROM complaint_assignments ca
                    WHERE ca.complaint_id=c.id
                      AND ca.assigned_to IN ({$empPlaceholders})
                      AND ca.removed_at IS NULL
                )";
                array_push($params, ...$effectiveEmpIds);
            }
            $stmt = $pdo->prepare("
                SELECT c.*, sm.member_code,
                  (SELECT GROUP_CONCAT(ca.assigned_to ORDER BY ca.assigned_at SEPARATOR ',') FROM complaint_assignments ca WHERE ca.complaint_id=c.id AND ca.removed_at IS NULL) AS assigned_to_ids,
                  (SELECT COUNT(*) FROM complaint_comments cc WHERE cc.complaint_id=c.id) AS comment_count,
                  (SELECT COUNT(*) FROM complaint_attachments cat WHERE cat.complaint_id=c.id AND cat.comment_id IS NULL) AS attachment_count
                FROM member_complaints c
                LEFT JOIN society_members sm ON sm.id = c.member_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.created_at DESC
            ");
            $stmt->execute($params);
            json_response(['complaints' => $stmt->fetchAll()]);
            break;

        // ── Get single complaint ──────────────────────────────────────────
        case 'get':
            $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
            if (!$id) json_error('Complaint ID required');

            $stmt = $pdo->prepare("SELECT * FROM member_complaints WHERE id=? AND client_id=?");
            $stmt->execute([$id, $cid]);
            $c = $stmt->fetch();
            if (!$c) json_error('Complaint not found', 404);

            if (!$isAdmin) {
                $mStmt = $pdo->prepare("SELECT id FROM society_members WHERE client_id=? AND employee_id=?");
                $mStmt->execute([$cid, $empId]);
                $m = $mStmt->fetch();
                $isOwner = $m && ((int)$c['member_id'] === (int)$m['id']);
                $assignStmt = $pdo->prepare("SELECT 1 FROM complaint_assignments WHERE complaint_id=? AND client_id=? AND assigned_to=? AND removed_at IS NULL LIMIT 1");
                $assignStmt->execute([$id, $cid, $empId]);
                $isAssigned = (bool)$assignStmt->fetchColumn();
                if (!$isOwner && !$isAssigned) json_error('Access denied', 403);
            }
            // Load assignments
            $aStmt = $pdo->prepare("SELECT ca.*, e.full_name as assignee_name, e.role as assignee_role FROM complaint_assignments ca LEFT JOIN employees e ON e.employee_id=ca.assigned_to AND e.client_id=ca.client_id WHERE ca.complaint_id=? AND ca.removed_at IS NULL");
            $aStmt->execute([$id]);
            $c['assignments'] = $aStmt->fetchAll();
            // Load complaint-level attachments
            $attStmt = $pdo->prepare("SELECT id, file_name, file_type, file_size_kb FROM complaint_attachments WHERE complaint_id=? AND comment_id IS NULL");
            $attStmt->execute([$id]);
            $c['attachments'] = $attStmt->fetchAll();
            $c['attachment_count'] = count($c['attachments']);
            json_response(['complaint' => $c]);
            break;

        // ── Admin: update status ──────────────────────────────────────────
        case 'update_status':
            if (!$isAdmin) json_error('Access denied', 403);
            $id      = (int)($input['id']            ?? 0);
            $status  = strtoupper(trim($input['status'] ?? ''));
            $remarks = trim($input['admin_remarks']  ?? '');

            if (!$id)                              json_error('Complaint ID required');
            if (!in_array($status, $VALID_STATUS)) json_error('Invalid status. Must be REVIEWED, RESOLVED or REJECTED');

            $resolvedAt = $status === 'RESOLVED' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("
                UPDATE member_complaints
                SET status=?, admin_remarks=?, reviewed_by=?, reviewed_at=NOW(), resolved_at=?
                WHERE id=? AND client_id=?
            ")->execute([$status, $remarks ?: null, $session['username'], $resolvedAt, $id, $cid]);

            $stmt = $pdo->prepare("SELECT * FROM member_complaints WHERE id=?");
            $stmt->execute([$id]);
            $c = $stmt->fetch();
            if ($c && !empty($c['email'])) {
                _sendStatusUpdateToMember($pdo, $cid, $c, $status, $remarks);
            }
            if ($c) {
                try {
                    $participants = portalGetComplaintParticipantEmployeeIds($pdo, $cid, $id);
                    $participants = array_values(array_filter($participants, static fn($eid) => $eid !== $empId));
                    portalNotifyMany(
                        $pdo,
                        $cid,
                        $participants,
                        "Complaint #{$id} Updated",
                        "Status changed to {$status}" . ($remarks ? ' - ' . mb_substr($remarks, 0, 120) : ''),
                        'INFO',
                        'my_complaints.php',
                        ['complaint_id' => $id, 'event' => 'status', 'status' => $status]
                    );
                } catch (Throwable $e) {
                }
            }

            logAudit($pdo, $cid, $session['username'], 'UPDATE_COMPLAINT_STATUS',
                "Complaint #{$id} marked {$status}" . ($remarks ? ": {$remarks}" : ''));
            json_response(['success' => true, 'message' => "Complaint marked as {$status}"]);
            break;

        // ── Admin: assign complaint to one or more users ─────────────────
        case 'assign':
            if (!$isAdmin) json_error('Access denied', 403);
            $id       = (int)($input['id'] ?? 0);
            $assignees = $input['assignees'] ?? []; // array of employee_ids
            if (!$id) json_error('Complaint ID required');
            if (empty($assignees)) json_error('At least one assignee required');

            $cStmt = $pdo->prepare("SELECT * FROM member_complaints WHERE id=? AND client_id=?");
            $cStmt->execute([$id, $cid]);
            $complaint = $cStmt->fetch();
            if (!$complaint) json_error('Complaint not found', 404);

            $assigned = []; $skipped = [];
            foreach ($assignees as $aEmpId) {
                $aEmpId = trim($aEmpId);
                if (!$aEmpId) continue;
                // Skip if already assigned
                $dupCheck = $pdo->prepare("SELECT id FROM complaint_assignments WHERE complaint_id=? AND assigned_to=? AND removed_at IS NULL");
                $dupCheck->execute([$id, $aEmpId]);
                if ($dupCheck->fetch()) { $skipped[] = $aEmpId; continue; }
                // Get user details for email
                $eStmt = $pdo->prepare("SELECT full_name, email, role FROM employees WHERE client_id=? AND employee_id=? AND status='active' AND is_deleted=0 LIMIT 1");
                $eStmt->execute([$cid, $aEmpId]);
                $assigneeRow = $eStmt->fetch();
                if (!$assigneeRow) {
                    $skipped[] = $aEmpId;
                    continue;
                }
                $pdo->prepare("INSERT INTO complaint_assignments (complaint_id,client_id,assigned_to,assigned_by) VALUES (?,?,?,?)"
                )->execute([$id, $cid, $aEmpId, $empId]);
                $assigned[] = $aEmpId;
                // Send email notification
                if (!empty($assigneeRow['email'])) {
                    _sendAssignmentEmail($pdo, $cid, $complaint, $assigneeRow, $session['username'] ?? $empId);
                }
                try {
                    $targetUrl = strtoupper((string)$assigneeRow['role']) === 'MEMBER' ? 'my_complaints.php' : 'complaints.php';
                    portalNotify(
                        $pdo,
                        $cid,
                        $aEmpId,
                        "Complaint #{$id} Assigned",
                        "{$session['username']} assigned: {$complaint['subject']}",
                        'INFO',
                        $targetUrl,
                        ['complaint_id' => $id, 'event' => 'assigned']
                    );
                } catch (Throwable $e) {
                }
            }
            logAudit($pdo, $cid, $empId, 'ASSIGN_COMPLAINT', "Complaint #{$id} assigned to: " . implode(',', $assigned));
            json_response(['success' => true, 'assigned' => $assigned, 'skipped' => $skipped,
                'message' => count($assigned) . ' user(s) assigned']);
            break;

        // ── Admin: remove an assignee ─────────────────────────────────────
        case 'unassign':
            if (!$isAdmin) json_error('Access denied', 403);
            $id      = (int)($input['id'] ?? 0);
            $aEmpId  = trim($input['employee_id'] ?? '');
            if (!$id || !$aEmpId) json_error('Complaint ID and employee_id required');

            $pdo->prepare("UPDATE complaint_assignments SET removed_at=NOW() WHERE complaint_id=? AND client_id=? AND assigned_to=? AND removed_at IS NULL"
            )->execute([$id, $cid, $aEmpId]);
            try {
                portalNotify(
                    $pdo,
                    $cid,
                    $aEmpId,
                    "Complaint #{$id} Unassigned",
                    "You have been unassigned from complaint #{$id}.",
                    'INFO',
                    'my_complaints.php',
                    ['complaint_id' => $id, 'event' => 'unassigned']
                );
            } catch (Throwable $e) {
            }
            logAudit($pdo, $cid, $empId, 'UNASSIGN_COMPLAINT', "Complaint #{$id} unassigned: {$aEmpId}");
            json_response(['success' => true, 'message' => 'Assignee removed']);
            break;

        // ── Admin: get active assignees for a complaint ───────────────────
        case 'get_assignments':
            if (!$isAdmin) json_error('Access denied', 403);
            $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
            if (!$id) json_error('Complaint ID required');
            $stmt = $pdo->prepare("
                SELECT ca.*, e.full_name as assignee_name, e.email as assignee_email, e.role as assignee_role
                FROM complaint_assignments ca
                LEFT JOIN employees e ON e.employee_id=ca.assigned_to AND e.client_id=ca.client_id
                WHERE ca.complaint_id=? AND ca.removed_at IS NULL
                ORDER BY ca.assigned_at ASC
            ");
            $stmt->execute([$id]);
            json_response(['assignments' => $stmt->fetchAll()]);
            break;

        // ── List complaints assigned to me (admin/member) ─────────────────
        case 'my_assigned':
            $effectiveEmpIds = _portalEmployeeIdCandidates($pdo, $cid, $session, $empId, $requestEmpId);
            if (!$effectiveEmpIds) json_response(['complaints' => []]);
            $empPlaceholders = implode(',', array_fill(0, count($effectiveEmpIds), '?'));
            $stmt = $pdo->prepare("
                SELECT c.*,
                  (SELECT COUNT(*) FROM complaint_comments cc WHERE cc.complaint_id=c.id) AS comment_count
                FROM member_complaints c
                WHERE c.client_id=?
                  AND EXISTS (
                    SELECT 1 FROM complaint_assignments ca
                    WHERE ca.complaint_id=c.id
                      AND ca.assigned_to IN ({$empPlaceholders})
                      AND ca.removed_at IS NULL
                  )
                ORDER BY c.created_at DESC
            ");
            $stmt->execute(array_merge([$cid], $effectiveEmpIds));
            json_response(['complaints' => $stmt->fetchAll()]);
            break;

        // ── Admin: close a complaint ──────────────────────────────────────
        case 'close':
            if (!$isAdmin) json_error('Access denied', 403);
            $id = (int)($input['id'] ?? 0);
            if (!$id) json_error('Complaint ID required');
            $pdo->prepare("
                UPDATE member_complaints SET status='CLOSED', closed_by=?, closed_at=NOW()
                WHERE id=? AND client_id=? AND status NOT IN ('CLOSED','WITHDRAWN')
            ")->execute([$empId, $id, $cid]);
            try {
                $participants = portalGetComplaintParticipantEmployeeIds($pdo, $cid, $id);
                $participants = array_values(array_filter($participants, static fn($eid) => $eid !== $empId));
                portalNotifyMany($pdo, $cid, $participants, "Complaint #{$id} Closed", 'The complaint has been closed.', 'INFO', 'my_complaints.php', ['complaint_id' => $id, 'event' => 'close']);
            } catch (Throwable $e) {
            }
            logAudit($pdo, $cid, $empId, 'CLOSE_COMPLAINT', "Complaint #{$id} closed by admin");
            json_response(['success' => true, 'message' => 'Complaint closed']);
            break;

        // ── Member: withdraw own complaint ────────────────────────────────
        case 'withdraw':
            $mStmt = $pdo->prepare("SELECT id FROM society_members WHERE client_id=? AND employee_id=? AND status='active'");
            $mStmt->execute([$cid, $empId]);
            $m = $mStmt->fetch();
            if (!$m) json_error('Member record not found');
            $id = (int)($input['id'] ?? 0);
            if (!$id) json_error('Complaint ID required');
            $rows = $pdo->prepare("
                UPDATE member_complaints SET status='WITHDRAWN', closed_by=?, closed_at=NOW()
                WHERE id=? AND client_id=? AND member_id=? AND status IN ('OPEN','REVIEWED')
            ");
            $rows->execute([$empId, $id, $cid, $m['id']]);
            if (!$rows->rowCount()) json_error('Cannot withdraw — complaint not found or already closed/resolved');
            try {
                $participants = portalGetComplaintParticipantEmployeeIds($pdo, $cid, $id);
                $participants = array_values(array_filter($participants, static fn($eid) => $eid !== $empId));
                portalNotifyMany($pdo, $cid, $participants, "Complaint #{$id} Withdrawn", 'The complaint has been withdrawn by the member.', 'WARNING', 'complaints.php', ['complaint_id' => $id, 'event' => 'withdraw']);
            } catch (Throwable $e) {
            }
            logAudit($pdo, $cid, $empId, 'WITHDRAW_COMPLAINT', "Complaint #{$id} withdrawn by member");
            json_response(['success' => true, 'message' => 'Complaint withdrawn']);
            break;

        // ── Admin or Member: reopen a complaint ───────────────────────────
        case 'reopen':
            $id = (int)($input['id'] ?? 0);
            if (!$id) json_error('Complaint ID required');
            $cStmt = $pdo->prepare("SELECT * FROM member_complaints WHERE id=? AND client_id=?");
            $cStmt->execute([$id, $cid]);
            $complaint = $cStmt->fetch();
            if (!$complaint) json_error('Complaint not found', 404);

            if (!$isAdmin) {
                // Member can only reopen their own WITHDRAWN complaints
                $mStmt = $pdo->prepare("SELECT id FROM society_members WHERE client_id=? AND employee_id=?");
                $mStmt->execute([$cid, $empId]);
                $m = $mStmt->fetch();
                if (!$m || $complaint['member_id'] != $m['id']) json_error('Access denied', 403);
                if ($complaint['status'] !== 'WITHDRAWN') json_error('You can only reopen withdrawn complaints');
            }
            $pdo->prepare("
                UPDATE member_complaints
                SET status='OPEN', closed_by=NULL, closed_at=NULL, reopen_count=reopen_count+1
                WHERE id=? AND client_id=?
            ")->execute([$id, $cid]);
            try {
                $participants = portalGetComplaintParticipantEmployeeIds($pdo, $cid, $id);
                $participants = array_values(array_filter($participants, static fn($eid) => $eid !== $empId));
                portalNotifyMany($pdo, $cid, $participants, "Complaint #{$id} Reopened", 'The complaint has been reopened.', 'INFO', 'complaints.php', ['complaint_id' => $id, 'event' => 'reopen']);
            } catch (Throwable $e) {
            }
            logAudit($pdo, $cid, $empId, 'REOPEN_COMPLAINT', "Complaint #{$id} reopened");
            json_response(['success' => true, 'message' => 'Complaint reopened']);
            break;

        default:
            json_error('Unknown action');
    }
} catch (PDOException $e) {
    ob_end_clean();
    json_error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ob_end_clean();
    json_error('Error: ' . $e->getMessage(), 500);
}

// Shared helpers

function _portalEmployeeIdCandidates(PDO $pdo, string $cid, array $session, string ...$rawIds): array {
    $ids = [];
    $add = static function ($value) use (&$ids): void {
        $value = trim((string)$value);
        if ($value !== '' && !in_array($value, $ids, true)) {
            $ids[] = $value;
        }
    };

    foreach ($rawIds as $rawId) {
        $add($rawId);
    }

    if ($ids) {
        return $ids;
    }

    // Older sessions may have portal_user_id but not portal_employee_id.
    $userId = (int)($session['user_id'] ?? 0);
    if ($userId > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT employee_id
                FROM employees
                WHERE id=? AND client_id=? AND is_deleted=0
                LIMIT 1
            ");
            $stmt->execute([$userId, $cid]);
            $add($stmt->fetchColumn() ?: '');
        } catch (Throwable $e) {}
    }

    // portal_username is the full name in current sessions. Use it only if
    // it maps to exactly one employee in this client.
    $username = trim((string)($session['username'] ?? ''));
    if ($username !== '') {
        try {
            $stmt = $pdo->prepare("
                SELECT employee_id
                FROM employees
                WHERE client_id=? AND full_name=? AND is_deleted=0
                ORDER BY status='active' DESC, id ASC
                LIMIT 2
            ");
            $stmt->execute([$cid, $username]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($rows) === 1) {
                $add($rows[0]);
            }
        } catch (Throwable $e) {}
    }

    return $ids;
}

// Email helpers

function _sendComplaintToAdmin(PDO $pdo, string $cid, array $member, array $c): void {
    $s      = _mSettings($pdo, $cid);
    $admins = _adminEmails($s);
    if (empty($admins)) return;
    $society = $s['society_name'] ?? 'Our Society';

    $pColors = ['LOW' => '#6b7280', 'MEDIUM' => '#f59e0b', 'HIGH' => '#ef4444', 'URGENT' => '#7c3aed'];
    $pColor  = $pColors[$c['priority']] ?? '#6b7280';

    $body = '<p style="color:#374151;font-size:14px;margin:0 0 16px;">A new complaint has been raised by a society member. Please review and take appropriate action.</p>'
        . '<div style="background:#f9fafb;border-radius:10px;padding:18px;border:1px solid #e5e7eb;">'
        . _table([
            _row('Complaint ID',  '#' . $c['id']),
            _row('Member',        htmlspecialchars($member['full_name'])),
            _row('Flat / Unit',   htmlspecialchars($member['flat_number'])),
            _row('Email',         htmlspecialchars($member['email'] ?? 'Not provided')),
            _row('Mobile',        htmlspecialchars($member['mobile'] ?? 'Not provided')),
            _divider(),
            _row('Category',      htmlspecialchars($c['category'])),
            _row('Priority',      '<span style="font-weight:bold;color:' . $pColor . ';">' . htmlspecialchars($c['priority']) . '</span>'),
            _row('Subject',       htmlspecialchars($c['subject'])),
            _divider(),
            _row('Description',   '<span style="white-space:pre-wrap;font-size:13px;">' . htmlspecialchars($c['description']) . '</span>'),
        ])
        . '</div>'
        . '<div style="margin-top:20px;padding:14px;background:#fef2f2;border-radius:8px;border:1px solid #fecaca;text-align:center;">'
        . '<p style="color:#b91c1c;margin:0;font-size:13px;font-weight:bold;">Please log in to the portal to review and respond to this complaint.</p>'
        . '</div>';

    $html = _emailTpl('#dc2626', 'New Member Complaint Received', $society, $body);
    _send($admins, "[EyeSense] New Complaint #{$c['id']} - {$c['subject']} | Flat {$member['flat_number']}", $html);
}

function _sendStatusUpdateToMember(PDO $pdo, string $cid, array $c, string $status, string $remarks): void {
    $s       = _mSettings($pdo, $cid);
    $society = $s['society_name'] ?? 'Our Society';

    $sColors = ['REVIEWED' => '#6366f1', 'RESOLVED' => '#059669', 'REJECTED' => '#dc2626'];
    $sColor  = $sColors[$status] ?? '#374151';
    $sLabel  = match($status) {
        'REVIEWED' => 'is currently under review by the administrator',
        'RESOLVED' => 'has been resolved',
        'REJECTED' => 'has been rejected',
        default    => 'has been updated',
    };

    $body = '<p style="color:#374151;font-size:15px;">Dear <strong>' . htmlspecialchars($c['full_name']) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;line-height:1.6;margin:8px 0 16px;">Your complaint <strong>#' . $c['id'] . '</strong> ' . $sLabel . '.</p>'
        . '<div style="background:#f9fafb;border-radius:10px;padding:18px;border:1px solid #e5e7eb;">'
        . _table([
            _row('Complaint ID',   '#' . $c['id']),
            _row('Subject',        htmlspecialchars($c['subject'])),
            _row('Category',       htmlspecialchars($c['category'])),
            _row('Status',         '<span style="font-weight:bold;color:' . $sColor . ';">' . $status . '</span>'),
            _divider(),
            _row('Admin Remarks',  $remarks
                ? '<span style="white-space:pre-wrap;font-size:13px;">' . htmlspecialchars($remarks) . '</span>'
                : '<span style="color:#9ca3af;font-style:italic;">No remarks provided.</span>'),
        ])
        . '</div>'
        . '<p style="color:#6b7280;font-size:13px;margin-top:16px;">If you have further concerns, you may raise a new complaint through the EyeSense Cloud Portal.</p>';

    $html = _emailTpl($sColor, 'Complaint Status Update', $society . ' - EyeSense Portal', $body);
    indsacSendEmail($c['email'], "[EyeSense] Complaint #{$c['id']} Status: {$status}", $html);
}

function _sendAssignmentEmail(PDO $pdo, string $cid, array $complaint, array $adminRow, string $assignedByName): void {
    $s       = _mSettings($pdo, $cid);
    $society = $s['society_name'] ?? 'Our Society';
    $pColors = ['LOW' => '#6b7280','MEDIUM' => '#f59e0b','HIGH' => '#ef4444','URGENT' => '#7c3aed'];
    $pColor  = $pColors[$complaint['priority'] ?? 'MEDIUM'] ?? '#f59e0b';
    $body    = '<p style="color:#374151;font-size:14px;">Dear <strong>' . htmlspecialchars($adminRow['full_name']) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;line-height:1.6;">A complaint has been assigned to you by <strong>' . htmlspecialchars($assignedByName) . '</strong>. Please review and take appropriate action.</p>'
        . '<div style="background:#f9fafb;border-radius:10px;padding:18px;border:1px solid #e5e7eb;">'
        . _table([
            _row('Complaint ID',  '#' . $complaint['id']),
            _row('Member',        htmlspecialchars($complaint['full_name'] ?? '')),
            _row('Flat / Unit',   htmlspecialchars($complaint['flat_number'] ?? '')),
            _divider(),
            _row('Category',      htmlspecialchars($complaint['category'] ?? '')),
            _row('Priority',      '<span style="font-weight:bold;color:' . $pColor . ';">' . htmlspecialchars($complaint['priority'] ?? '') . '</span>'),
            _row('Subject',       htmlspecialchars($complaint['subject'] ?? '')),
            _divider(),
            _row('Description',   '<span style="white-space:pre-wrap;font-size:13px;">' . htmlspecialchars($complaint['description'] ?? '') . '</span>'),
        ])
        . '</div>'
        . '<p style="margin-top:16px;color:#6b7280;font-size:13px;">Please log in to the portal to respond to this complaint.</p>';
    $html = _emailTpl('#7c3aed', 'Complaint Assigned to You', $society, $body);
    indsacSendEmail($adminRow['email'], "[EyeSense] Complaint #{$complaint['id']} Assigned — {$complaint['subject']}", $html);
}
