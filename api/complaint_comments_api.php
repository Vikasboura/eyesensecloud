<?php
/**
 * EyeSense Cloud Portal — Complaint Comments & Attachments API
 * Actions: list, add, serve_file
 *
 * Storage:
 *   - Complaint attachments (on complaint itself):  COMPLAINT_UPLOADS_DIR/{cid}/{complaint_id}/
 *   - Comment attachments (on a comment):           COMMENT_UPLOADS_DIR/{cid}/{complaint_id}/{comment_id}/
 *
 * Allowed types: jpg, jpeg, png, gif, bmp, webp, mp4, avi, mov, mkv,
 *                pdf, txt, doc, docx, xls, xlsx, csv, zip, rar
 */
ob_start();
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/portal_notifications_helper.php';

header('Content-Type: application/json; charset=utf-8');
requirePortalLogin();

$pdo     = get_indsac_db();
$session = getPortalSession();
$cid     = $session['client_id'];
$isSA    = !empty($session['is_superadmin']);
$perms   = getAllPermissions($pdo, $cid, $session['role']);
$empId   = $_SESSION['portal_employee_id'] ?? '';
$isAdmin = $isSA || !empty($perms['MANAGE_MAINTENANCE']);

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
if (empty($input) && !empty($_POST)) {
    $input = array_merge($_POST, $input);
}
$action = $input['action'] ?? ($_GET['action'] ?? '');

// Allowed MIME types and extensions
const ALLOWED_EXTS  = ['jpg','jpeg','png','gif','bmp','webp','mp4','avi','mov','mkv',
                        'pdf','txt','doc','docx','xls','xlsx','csv','zip','rar'];
const MIME_MAP      = [
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

/**
 * Get max attachment size in MB for this client.
 */
function _getMaxMb(PDO $pdo, string $cid): int {
    try {
        $st = $pdo->prepare("SELECT max_attachment_size_mb FROM maintenance_settings WHERE client_id=?");
        $st->execute([$cid]);
        $v = (int)$st->fetchColumn();
        return max(1, min(50, $v ?: 5));
    } catch (Throwable $e) { return 5; }
}

/**
 * Ensure a directory exists (create recursively if needed).
 */
function _ensureDir(string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Verify the caller can access the given complaint.
 * Returns the complaint row or exits with json_error.
 */
function _requireComplaintAccess(PDO $pdo, string $cid, string $empId, bool $isAdmin, int $complaintId): array {
    $st = $pdo->prepare("SELECT * FROM member_complaints WHERE id=? AND client_id=?");
    $st->execute([$complaintId, $cid]);
    $c = $st->fetch();
    if (!$c) json_error('Complaint not found', 404);

    if (!$isAdmin) {
        $mSt = $pdo->prepare("SELECT id FROM society_members WHERE client_id=? AND employee_id=? AND status='active'");
        $mSt->execute([$cid, $empId]);
        $m = $mSt->fetch();
        $isOwner = $m && ((int)$c['member_id'] === (int)$m['id']);
        $assignStmt = $pdo->prepare("SELECT 1 FROM complaint_assignments WHERE complaint_id=? AND client_id=? AND assigned_to=? AND removed_at IS NULL LIMIT 1");
        $assignStmt->execute([$complaintId, $cid, $empId]);
        $isAssigned = (bool)$assignStmt->fetchColumn();
        if (!$isOwner && !$isAssigned) json_error('Access denied', 403);
    }
    return $c;
}

try {
    switch ($action) {

        // ── List comments + attachments for a complaint ────────────────────
        case 'list':
            $complaintId = (int)($input['complaint_id'] ?? $_GET['complaint_id'] ?? 0);
            if (!$complaintId) json_error('complaint_id required');

            _requireComplaintAccess($pdo, $cid, $empId, $isAdmin, $complaintId);

            // Fetch comments
            $cStmt = $pdo->prepare("
                SELECT * FROM complaint_comments
                WHERE complaint_id=? AND client_id=?
                ORDER BY created_at ASC
            ");
            $cStmt->execute([$complaintId, $cid]);
            $comments = $cStmt->fetchAll();

            // Fetch attachments for each comment + complaint-level attachments
            $aStmt = $pdo->prepare("
                SELECT * FROM complaint_attachments
                WHERE complaint_id=? AND client_id=?
                ORDER BY uploaded_at ASC
            ");
            $aStmt->execute([$complaintId, $cid]);
            $allAttachments = $aStmt->fetchAll();

            // Group by comment_id (null = complaint-level)
            $attachMap = [];
            foreach ($allAttachments as $att) {
                $key = $att['comment_id'] ?? 'complaint';
                $attachMap[$key][] = $att;
            }

            // Attach attachments to comments
            foreach ($comments as &$comment) {
                $comment['attachments'] = $attachMap[$comment['id']] ?? [];
            }
            unset($comment);

            json_response([
                'comments'             => $comments,
                'complaint_attachments' => $attachMap['complaint'] ?? [],
            ]);
            break;

        // ── Add a comment (with optional file attachments) ─────────────────
        case 'add':
            $complaintId = (int)($input['complaint_id'] ?? $_POST['complaint_id'] ?? 0);
            $message     = trim($input['message'] ?? $_POST['message'] ?? '');

            if (!$complaintId) json_error('complaint_id required');
            if (!$message)     json_error('message is required');

            $complaint = _requireComplaintAccess($pdo, $cid, $empId, $isAdmin, $complaintId);

            // Determine author role
            $authorRole = 'MEMBER';
            if ($isSA)      $authorRole = 'SUPERADMIN';
            elseif ($isAdmin) $authorRole = 'ADMIN';

            // Get author name
            $nameStmt = $pdo->prepare("SELECT full_name FROM employees WHERE client_id=? AND employee_id=? AND is_deleted=0 LIMIT 1");
            $nameStmt->execute([$cid, $empId]);
            $authorName = $nameStmt->fetchColumn() ?: ($session['username'] ?? $empId);

            // Insert comment
            $pdo->prepare("
                INSERT INTO complaint_comments
                  (complaint_id, client_id, author_id, author_name, author_role, message)
                VALUES (?,?,?,?,?,?)
            ")->execute([$complaintId, $cid, $empId, $authorName, $authorRole, $message]);
            $commentId = (int)$pdo->lastInsertId();

            // Handle file uploads
            $maxMb    = _getMaxMb($pdo, $cid);
            $maxBytes = $maxMb * 1024 * 1024;
            $uploaded = [];

            if (!empty($_FILES['files']['name'][0])) {
                $files = $_FILES['files'];
                $count = count($files['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

                    $origName = basename($files['name'][$i]);
                    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                    if (!in_array($ext, ALLOWED_EXTS)) {
                        // Skip disallowed extension
                        continue;
                    }
                    if ($files['size'][$i] > $maxBytes) {
                        json_error("File '{$origName}' exceeds the {$maxMb} MB limit.");
                    }

                    $dir = rtrim(COMMENT_UPLOADS_DIR, '/\\') . "/{$cid}/{$complaintId}/{$commentId}";
                    _ensureDir($dir);

                    $safeName = time() . '_' . uniqid() . '.' . $ext;
                    $dest     = $dir . '/' . $safeName;
                    if (!move_uploaded_file($files['tmp_name'][$i], $dest)) continue;

                    $mimeType = MIME_MAP[$ext] ?? 'application/octet-stream';
                    $sizeKb   = (int)ceil($files['size'][$i] / 1024);
                    $relPath  = "comment_attachments/{$cid}/{$complaintId}/{$commentId}/{$safeName}";

                    $pdo->prepare("
                        INSERT INTO complaint_attachments
                          (complaint_id, comment_id, client_id, file_name, file_path, file_type, file_size_kb, uploaded_by)
                        VALUES (?,?,?,?,?,?,?,?)
                    ")->execute([$complaintId, $commentId, $cid, $origName, $relPath, $mimeType, $sizeKb, $empId]);

                    $uploaded[] = ['file_name' => $origName, 'file_type' => $mimeType, 'file_size_kb' => $sizeKb];
                }
            }

            logAudit($pdo, $cid, $empId, 'ADD_COMPLAINT_COMMENT',
                "Comment #{$commentId} on Complaint #{$complaintId}");

            try {
                $participants = portalGetComplaintParticipantEmployeeIds($pdo, $cid, $complaintId);
                $participants = array_values(array_filter($participants, static fn($eid) => $eid !== $empId));
                $targetUrl = ($authorRole === 'MEMBER') ? 'complaints.php' : 'my_complaints.php';
                portalNotifyMany(
                    $pdo,
                    $cid,
                    $participants,
                    "New Comment on Complaint #{$complaintId}",
                    "{$authorName}: " . mb_substr($message, 0, 140),
                    'INFO',
                    $targetUrl,
                    ['complaint_id' => $complaintId, 'comment_id' => $commentId, 'event' => 'comment']
                );
            } catch (Throwable $e) {
            }

            json_response([
                'success'    => true,
                'comment_id' => $commentId,
                'uploaded'   => $uploaded,
                'message'    => 'Comment posted',
            ]);
            break;

        // ── Serve/stream an attachment file ────────────────────────────────
        case 'serve_file':
            $attId = (int)($input['id'] ?? $_GET['id'] ?? 0);
            if (!$attId) json_error('Attachment ID required');

            $aStmt = $pdo->prepare("SELECT * FROM complaint_attachments WHERE id=? AND client_id=?");
            $aStmt->execute([$attId, $cid]);
            $att = $aStmt->fetch();
            if (!$att) json_error('Attachment not found', 404);

            // Verify access to the complaint
            _requireComplaintAccess($pdo, $cid, $empId, $isAdmin, (int)$att['complaint_id']);

            // Determine absolute path
            $filePath = $att['file_path'];
            if (!str_starts_with($filePath, '/') && !preg_match('/^[A-Za-z]:/', $filePath)) {
                // Relative path: resolve from parent of api/
                if (str_starts_with($filePath, 'complaint_attachments/')) {
                    $absPath = rtrim(COMPLAINT_UPLOADS_DIR, '/\\') . '/' . substr($filePath, strlen('complaint_attachments/'));
                } elseif (str_starts_with($filePath, 'comment_attachments/')) {
                    $absPath = rtrim(COMMENT_UPLOADS_DIR, '/\\') . '/' . substr($filePath, strlen('comment_attachments/'));
                } else {
                    $absPath = __DIR__ . '/uploads/' . $filePath;
                }
            } else {
                $absPath = $filePath;
            }

            if (!file_exists($absPath)) json_error('File not found on server', 404);

            // Clear any buffered output, then stream
            if (ob_get_level() > 0) ob_end_clean();

            $mime = $att['file_type'] ?: 'application/octet-stream';
            $safe = basename($att['file_name']);

            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="' . addslashes($safe) . '"');
            header('Content-Length: ' . filesize($absPath));
            header('Cache-Control: private, max-age=86400');
            readfile($absPath);
            exit;

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
