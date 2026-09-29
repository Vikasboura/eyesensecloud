<?php
/**
 * EyeSense Cloud Portal — View Mode Switch API
 *
 * Lets admins/SA toggle between admin view and member view.
 * Actions:
 *   check  – Returns whether the current admin has a linked society_members record.
 *   switch – Toggles $_SESSION['portal_view_mode'] between 'admin' and 'member'.
 *            If switching to 'member' and no member record exists, creates one on-the-fly.
 */
ob_start();
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';

header('Content-Type: application/json; charset=utf-8');
requirePortalLogin();

$pdo     = get_indsac_db();
$session = getPortalSession();
$cid     = $session['client_id'];
$isSA    = !empty($session['is_superadmin']);
$perms   = getAllPermissions($pdo, $cid, $session['role']);
$isAdmin = $isSA || !empty($perms['MANAGE_MAINTENANCE']);
$empId   = $_SESSION['portal_employee_id'] ?? '';

if (!$isAdmin) json_error('Only admins can switch view modes', 403);

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

function _getMemberRecord(PDO $pdo, string $cid, string $empId): ?array {
    $st = $pdo->prepare("SELECT * FROM society_members WHERE client_id=? AND employee_id=? AND status='active' LIMIT 1");
    $st->execute([$cid, $empId]);
    return $st->fetch() ?: null;
}

try {
    switch ($action) {

        // ── Check if admin has a member record ────────────────────────────
        case 'check':
            $member = _getMemberRecord($pdo, $cid, $empId);
            $current = $_SESSION['portal_view_mode'] ?? 'admin';
            json_response([
                'has_member'   => $member !== null,
                'member'       => $member ? [
                    'flat_number'    => $member['flat_number'],
                    'plot_size_sqft' => $member['plot_size_sqft'],
                    'monthly_amount' => $member['monthly_amount'],
                ] : null,
                'current_mode' => $current,
            ]);
            break;

        // ── Toggle admin ↔ member view ────────────────────────────────────
        case 'switch':
            $current = $_SESSION['portal_view_mode'] ?? 'admin';

            if ($current === 'admin') {
                // Switching to member view — ensure member record exists
                $member = _getMemberRecord($pdo, $cid, $empId);
                if (!$member) {
                    // Try to create an on-the-fly member record
                    $flatNumber   = trim($input['flat_number']   ?? '');
                    $plotSize     = (float)($input['plot_size_sqft'] ?? 0);
                    $monthlyAmt   = (float)($input['monthly_amount']  ?? 0);
                    $dueDay       = (int)($input['due_day'] ?? 5);

                    if (!$flatNumber) json_error('flat_number is required to create your member record');
                    if ($plotSize <= 0) json_error('plot_size_sqft is required');

                    // Look up rate map if amount not provided
                    if ($monthlyAmt <= 0) {
                        try {
                            $rSt = $pdo->prepare("SELECT monthly_amount FROM maintenance_rate_map WHERE client_id=? AND plot_size_sqft=? LIMIT 1");
                            $rSt->execute([$cid, $plotSize]);
                            $mapped = $rSt->fetchColumn();
                            if ($mapped) $monthlyAmt = (float)$mapped;
                        } catch (Throwable $e) {}
                    }

                    // Fetch full name from employees table
                    $nameStmt = $pdo->prepare("SELECT full_name, email, phone FROM employees WHERE client_id=? AND employee_id=? AND is_deleted=0 LIMIT 1");
                    $nameStmt->execute([$cid, $empId]);
                    $emp = $nameStmt->fetch();

                    // Auto-generate member_code
                    $maxCode = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(member_code, 5) AS UNSIGNED)) FROM society_members WHERE client_id=?");
                    $maxCode->execute([$cid]);
                    $memberCode = 'MBR-' . str_pad(($maxCode->fetchColumn() ?: 0) + 1, 4, '0', STR_PAD_LEFT);

                    $pdo->prepare("
                        INSERT INTO society_members
                          (client_id, member_code, full_name, flat_number,
                           monthly_amount, due_day, email, mobile, employee_id, status, created_by)
                        VALUES (?,?,?,?,?,?,?,?,?,'active',?)
                    ")->execute([
                        $cid, $memberCode,
                        $emp['full_name'] ?? $session['username'] ?? $empId,
                        $flatNumber, $monthlyAmt, $dueDay,
                        $emp['email'] ?? null, $emp['phone'] ?? null,
                        $empId,
                        $session['username'] ?? $empId,
                    ]);

                    logAudit($pdo, $cid, $empId, 'CREATE_SELF_MEMBER_RECORD',
                        "Admin created own member record: flat={$flatNumber}, sqft={$plotSize}");
                }

                $_SESSION['portal_view_mode'] = 'member';
                json_response(['success' => true, 'mode' => 'member', 'message' => 'Switched to Member View']);

            } else {
                // Switching back to admin view
                $_SESSION['portal_view_mode'] = 'admin';
                json_response(['success' => true, 'mode' => 'admin', 'message' => 'Switched to Admin View']);
            }
            break;

        default:
            json_error('Unknown action');
    }
} catch (PDOException $e) {
    ob_end_clean();
    json_error('Database error: ' . $e->getMessage(), 500);
}
