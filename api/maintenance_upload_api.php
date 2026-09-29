<?php
/**
 * EyeSense Cloud Portal — Maintenance Receipt Upload API
 * Handles multipart file uploads from members.
 * Gate: VIEW_MAINTENANCE or MANAGE_MAINTENANCE
 * Triggers admin email notification on success.
 */

// Buffer all output so PHP warnings/notices never corrupt the JSON response
ob_start();

require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/maintenance_notify.php';

header('Content-Type: application/json; charset=utf-8');

requirePortalLogin();
$pdo     = get_indsac_db();
$session = getPortalSession();
$cid     = $session['client_id'];
$isSA    = !empty($session['is_superadmin']);
$perms   = getAllPermissions($pdo, $cid, $session['role']);
$empId   = $_SESSION['portal_employee_id'] ?? '';

if (!$isSA && empty($perms['VIEW_MAINTENANCE']) && empty($perms['MANAGE_MAINTENANCE'])) {
    json_error('Access denied', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST method required', 405);
}

try {
    // ── Validate inputs ──
    $billId      = (int)($_POST['bill_id'] ?? 0);
    $amountPaid  = (float)($_POST['amount_paid'] ?? 0);
    $paymentMode = $_POST['payment_mode'] ?? 'Cash';
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $referenceNo = trim($_POST['reference_no'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    if ($billId <= 0) json_error('Bill ID is required');
    if ($amountPaid <= 0) json_error('Amount paid must be greater than 0');

    $validModes = ['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'QR Code', 'Other'];
    if (!in_array($paymentMode, $validModes)) json_error('Invalid payment mode');

    // ── Validate file ──
    if (empty($_FILES['receipt_file']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing server temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        ];
        $errCode = $_FILES['receipt_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        json_error($uploadErrors[$errCode] ?? 'File upload failed');
    }

    $file     = $_FILES['receipt_file'];
    $fileSize = $file['size'];
    $maxSize  = 5 * 1024 * 1024; // 5MB

    if ($fileSize > $maxSize) {
        json_error('File size exceeds 5MB limit');
    }

    // Validate MIME type — use mime_content_type (always available) since fileinfo may be disabled
    $mimeType = false;
    if (function_exists('finfo_open')) {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
    if (!$mimeType && function_exists('mime_content_type')) {
        $mimeType = mime_content_type($file['tmp_name']);
    }
    // Last resort: derive from extension (less secure but avoids fatal error)
    if (!$mimeType) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extMap = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
        $mimeType = $extMap[$ext] ?? 'application/octet-stream';
    }

    $allowedMimes = [
        'application/pdf'  => 'PDF',
        'image/jpeg'       => 'IMAGE',
        'image/png'        => 'IMAGE',
        'image/jpg'        => 'IMAGE',
    ];

    if (!isset($allowedMimes[$mimeType])) {
        json_error('Invalid file type. Allowed: PDF, JPG, PNG');
    }

    $fileType = $allowedMimes[$mimeType];

    // ── Verify bill belongs to the member ──
    // Find the member linked to this employee
    $memberStmt = $pdo->prepare("SELECT * FROM society_members WHERE client_id = ? AND employee_id = ? AND status = 'active'");
    $memberStmt->execute([$cid, $empId]);
    $member = $memberStmt->fetch();

    // If admin/SA is uploading on behalf, allow them to specify member_id
    if (!$member && ($isSA || !empty($perms['MANAGE_MAINTENANCE']))) {
        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($memberId > 0) {
            $memberStmt = $pdo->prepare("SELECT * FROM society_members WHERE id = ? AND client_id = ?");
            $memberStmt->execute([$memberId, $cid]);
            $member = $memberStmt->fetch();
        }
    }

    if (!$member) {
        json_error('You are not registered as a society member. Contact your admin.');
    }

    // Verify the bill
    $billStmt = $pdo->prepare("SELECT * FROM maintenance_bills WHERE id = ? AND member_id = ? AND client_id = ?");
    $billStmt->execute([$billId, $member['id'], $cid]);
    $bill = $billStmt->fetch();

    if (!$bill) {
        json_error('Bill not found or does not belong to your account');
    }

    // Only allow upload for PENDING / OVERDUE / REJECTED / PARTIAL bills
    $allowedStatuses = ['PENDING', 'OVERDUE', 'REJECTED', 'PARTIAL'];
    if (!in_array($bill['status'], $allowedStatuses)) {
        json_error('Receipt already uploaded for this bill. Status: ' . $bill['status']);
    }

    // ── Supersede any previous PENDING/REJECTED receipt for this bill ──
    // (Verified receipts are kept — they count toward the running paid total)
    $pdo->prepare("
        UPDATE maintenance_receipts SET review_status = 'SUPERSEDED'
        WHERE bill_id = ? AND client_id = ? AND review_status IN ('PENDING', 'REJECTED')
    ")->execute([$billId, $cid]);

    // ── Save file ──
    $uploadDir = MAINTENANCE_UPLOADS_DIR . '/' . $cid;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            json_error('Upload directory could not be created. Check server permissions: ' . MAINTENANCE_UPLOADS_DIR, 500);
        }
    }

    // Protect the uploads root with .htaccess (Apache) in case it's inside web root
    $htaccessPath = MAINTENANCE_UPLOADS_DIR . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        @file_put_contents($htaccessPath, "Deny from all\n");
    }

    $ext = $fileType === 'PDF' ? 'pdf' : (str_contains($mimeType, 'png') ? 'png' : 'jpg');
    $safeFileName = "{$cid}_{$member['id']}_{$billId}_" . uniqid() . ".{$ext}";
    $savePath     = $uploadDir . '/' . $safeFileName;

    if (!move_uploaded_file($file['tmp_name'], $savePath)) {
        json_error('Failed to save uploaded file. Check write permissions on: ' . $uploadDir, 500);
    }

    // ── Generate receipt number ──
    $year = date('Y');
    $maxId = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM maintenance_receipts WHERE client_id = ?");
    $maxId->execute([$cid]);
    $nextId = $maxId->fetchColumn();
    $receiptNumber = "RCP-{$year}-" . str_pad($nextId, 5, '0', STR_PAD_LEFT);

    // ── Insert receipt ──
    // Store a portable relative key: client_id/filename
    // maintenance_serve_file.php resolves the full path using MAINTENANCE_UPLOADS_DIR
    $dbFilePath = $cid . '/' . $safeFileName;

    $insertStmt = $pdo->prepare("
        INSERT INTO maintenance_receipts
        (client_id, bill_id, member_id, uploaded_by, receipt_number, file_path, file_name, file_type, file_size_kb,
         amount_paid, payment_mode, payment_date, reference_no, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([
        $cid,
        $billId,
        $member['id'],
        $empId ?: ($session['username'] ?? 'unknown'),
        $receiptNumber,
        $dbFilePath,
        $file['name'],
        $fileType,
        (int)ceil($fileSize / 1024),
        $amountPaid,
        $paymentMode,
        $paymentDate,
        $referenceNo,
        $notes
    ]);
    $receiptId = $pdo->lastInsertId();

    // ── Update bill status ──
    $pdo->prepare("UPDATE maintenance_bills SET status = 'UPLOADED' WHERE id = ? AND client_id = ?")
        ->execute([$billId, $cid]);

    // ── Fetch inserted receipt for notification ──
    $rcpStmt = $pdo->prepare("SELECT * FROM maintenance_receipts WHERE id = ?");
    $rcpStmt->execute([$receiptId]);
    $receiptRow = $rcpStmt->fetch();

    // ── Send admin email notification ──
    sendMaintenanceAdminNotification($pdo, $cid, $member, $bill, $receiptRow ?: [
        'id'             => $receiptId,
        'receipt_number' => $receiptNumber,
        'amount_paid'    => $amountPaid,
        'payment_mode'   => $paymentMode,
        'payment_date'   => $paymentDate,
        'reference_no'   => $referenceNo,
        'uploaded_at'    => date('Y-m-d H:i:s'),
        'file_path'      => $dbFilePath,
        'file_name'      => $file['name'],
    ], $savePath);

    // ── Audit log ──
    logAudit($pdo, $cid, $empId ?: 'member', 'UPLOAD_MAINTENANCE_RECEIPT',
        "Receipt {$receiptNumber} uploaded for bill #{$billId} by {$member['full_name']}");

    json_response([
        'success'        => true,
        'receipt_id'     => $receiptId,
        'receipt_number' => $receiptNumber,
        'message'        => 'Receipt uploaded successfully. Admin has been notified.'
    ]);

} catch (PDOException $e) {
    ob_end_clean();
    json_error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ob_end_clean();
    json_error('Error: ' . $e->getMessage(), 500);
}
