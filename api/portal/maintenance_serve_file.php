<?php
/**
 * EyeSense Cloud Portal — Secure Maintenance Receipt File Server
 * Validates session + permission + ownership before streaming the file.
 * Usage: maintenance_serve_file.php?receipt_id=123
 */

require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';

requirePortalLogin();
$pdo     = get_indsac_db();
$session = getPortalSession();
$cid     = $session['client_id'];
$isSA    = !empty($session['is_superadmin']);
$perms   = getAllPermissions($pdo, $cid, $session['role']);
$empId   = $_SESSION['portal_employee_id'] ?? '';

$receiptId = (int)($_GET['receipt_id'] ?? 0);

if ($receiptId <= 0) {
    http_response_code(400);
    die('Invalid receipt ID');
}

// Fetch receipt
$stmt = $pdo->prepare("
    SELECT r.*, sm.employee_id AS member_employee_id
    FROM maintenance_receipts r
    JOIN society_members sm ON sm.id = r.member_id AND sm.client_id = r.client_id
    WHERE r.id = ? AND r.client_id = ?
");
$stmt->execute([$receiptId, $cid]);
$receipt = $stmt->fetch();

if (!$receipt) {
    http_response_code(404);
    die('Receipt not found');
}

// Permission check: admin can see all, member can only see own
if (!$isSA && empty($perms['MANAGE_MAINTENANCE'])) {
    // Must be the member's own receipt
    if ($receipt['member_employee_id'] !== $empId) {
        http_response_code(403);
        die('Access denied');
    }
    if (empty($perms['VIEW_MAINTENANCE'])) {
        http_response_code(403);
        die('Access denied');
    }
}

// Resolve file path using the configured uploads directory
// file_path in DB is stored as "client_id/filename" (portable relative key)
$filePath = MAINTENANCE_UPLOADS_DIR . '/' . $receipt['file_path'];

// Fallback: legacy records stored full relative path like "uploads/maintenance_receipts/..."
if (!file_exists($filePath)) {
    $filePath = __DIR__ . '/../' . $receipt['file_path'];
}

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found on server. Path: ' . basename($receipt['file_path']));
}

// Determine content type
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$contentTypes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];

$contentType = $contentTypes[$ext] ?? 'application/octet-stream';
$disposition = ($_GET['download'] ?? '') === '1' ? 'attachment' : 'inline';

// Stream file
header('Content-Type: ' . $contentType);
header('Content-Disposition: ' . $disposition . '; filename="' . $receipt['file_name'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
