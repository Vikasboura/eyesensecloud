<?php
/**
 * EyeSense Cloud Portal — Maintenance Review API
 * Admin actions: verify/reject receipts, get stats, generate bills, mark overdue.
 * Gate: MANAGE_MAINTENANCE or SuperAdmin
 */

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

if (!$isSA && empty($perms['MANAGE_MAINTENANCE'])) {
    json_error('Access denied: MANAGE_MAINTENANCE permission required', 403);
}

// ── Parse input: support JSON body, POST, and GET ──
$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody, true);
if (!$input) $input = array_merge($_GET, $_POST);
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ── VERIFY RECEIPT ──
        case 'verify':
            $receiptId = (int)($input['receipt_id'] ?? 0);
            if ($receiptId <= 0) json_error('Receipt ID required');

            $pdo->beginTransaction();

            // Update receipt
            $pdo->prepare("
                UPDATE maintenance_receipts
                SET review_status = 'VERIFIED', reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ? AND client_id = ?
            ")->execute([$session['username'] ?? 'admin', $receiptId, $cid]);

            // Get bill_id from this receipt
            $rcptStmt = $pdo->prepare("SELECT bill_id, amount_paid FROM maintenance_receipts WHERE id = ? AND client_id = ?");
            $rcptStmt->execute([$receiptId, $cid]);
            $rcptRow = $rcptStmt->fetch();
            $billId = $rcptRow['bill_id'] ?? 0;
            $thisAmount = (float)($rcptRow['amount_paid'] ?? 0);

            $newStatus = 'VERIFIED';
            $totalPaid = $thisAmount;

            if ($billId) {
                // Sum ALL verified receipts for this bill (including the one just verified)
                $sumStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(amount_paid), 0) AS total_paid
                    FROM maintenance_receipts
                    WHERE bill_id = ? AND client_id = ? AND review_status = 'VERIFIED'
                ");
                $sumStmt->execute([$billId, $cid]);
                $totalPaid = (float)$sumStmt->fetchColumn();

                // Get total_due from the bill
                $billStmt = $pdo->prepare("SELECT total_due FROM maintenance_bills WHERE id = ? AND client_id = ?");
                $billStmt->execute([$billId, $cid]);
                $totalDue = (float)($billStmt->fetchColumn() ?: 0);

                // If total paid < total_due → PARTIAL, otherwise VERIFIED
                $newStatus = ($totalPaid < $totalDue) ? 'PARTIAL' : 'VERIFIED';
                $pdo->prepare("UPDATE maintenance_bills SET status = ? WHERE id = ? AND client_id = ?")
                    ->execute([$newStatus, $billId, $cid]);
            }

            $pdo->commit();

            // Notify member their receipt was verified
            try { sendMemberReceiptVerified($pdo, $cid, $receiptId); } catch(Throwable $e) { error_log('[NOTIFY] verify: ' . $e->getMessage()); }

            $statusLabel = ($newStatus === 'PARTIAL')
                ? "Partial payment verified (₹" . number_format($totalPaid, 2) . " of ₹" . number_format($totalDue ?? 0, 2) . ")"
                : 'Receipt verified — fully paid';
            logAudit($pdo, $cid, $session['username'] ?? '', 'VERIFY_RECEIPT',
                "Verified receipt #{$receiptId} for bill #{$billId} — {$statusLabel}");

            json_response(['success' => true, 'message' => $statusLabel, 'bill_status' => $newStatus]);
            break;

        // ── REJECT RECEIPT ──
        case 'reject':
            $receiptId  = (int)($input['receipt_id'] ?? 0);
            $reviewNote = trim($input['review_note'] ?? '');
            if ($receiptId <= 0) json_error('Receipt ID required');
            if (empty($reviewNote)) json_error('Rejection reason is required');

            $pdo->beginTransaction();

            $pdo->prepare("
                UPDATE maintenance_receipts
                SET review_status = 'REJECTED', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
                WHERE id = ? AND client_id = ?
            ")->execute([$session['username'] ?? 'admin', $reviewNote, $receiptId, $cid]);

            $billStmt = $pdo->prepare("SELECT bill_id FROM maintenance_receipts WHERE id = ? AND client_id = ?");
            $billStmt->execute([$receiptId, $cid]);
            $billId = $billStmt->fetchColumn();

            if ($billId) {
                $pdo->prepare("UPDATE maintenance_bills SET status = 'REJECTED' WHERE id = ? AND client_id = ?")
                    ->execute([$billId, $cid]);
            }

            $pdo->commit();

            // Notify member their receipt was rejected
            try { sendMemberReceiptRejected($pdo, $cid, $receiptId, $reviewNote); } catch(Throwable $e) { error_log('[NOTIFY] reject: ' . $e->getMessage()); }

            logAudit($pdo, $cid, $session['username'] ?? '', 'REJECT_RECEIPT',
                "Rejected receipt #{$receiptId}: {$reviewNote}");

            json_response(['success' => true, 'message' => 'Receipt rejected']);
            break;

        // ── GET STATS FOR A MONTH ──
        case 'get_stats':
            $month = $input['month'] ?? date('Y-m-01');

            $stats = [];
            $statuses = ['PENDING', 'UPLOADED', 'VERIFIED', 'REJECTED', 'OVERDUE', 'PARTIAL'];
            foreach ($statuses as $s) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenance_bills WHERE client_id = ? AND billing_month = ? AND status = ?");
                $stmt->execute([$cid, $month, $s]);
                $stats[strtolower($s)] = (int)$stmt->fetchColumn();
            }

            // Total collected (verified receipts this month)
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(r.amount_paid), 0)
                FROM maintenance_receipts r
                JOIN maintenance_bills b ON b.id = r.bill_id
                WHERE r.client_id = ? AND b.billing_month = ? AND r.review_status = 'VERIFIED'
            ");
            $stmt->execute([$cid, $month]);
            $stats['total_collected'] = (float)$stmt->fetchColumn();

            // Total due
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_due), 0) FROM maintenance_bills WHERE client_id = ? AND billing_month = ?");
            $stmt->execute([$cid, $month]);
            $stats['total_due'] = (float)$stmt->fetchColumn();

            // Active member count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM society_members WHERE client_id = ? AND status = 'active'");
            $stmt->execute([$cid]);
            $stats['total_members'] = (int)$stmt->fetchColumn();

            json_response($stats);
            break;

        // ── GET BILLS FOR A MONTH (with member + latest receipt data) ──
        case 'get_bills':
            $month  = $input['month'] ?? date('Y-m-01');
            $status = $input['status'] ?? '';
            $search = trim($input['search'] ?? '');

            // Subquery: pick the receipt that needs attention FIRST (PENDING), then latest VERIFIED/REJECTED
            // This ensures one row per bill even with multiple receipts
            $sql = "
                SELECT b.*, sm.full_name, sm.flat_number, sm.member_code, sm.email, sm.mobile,
                       r.id AS receipt_id, r.receipt_number, r.file_type, r.amount_paid,
                       r.payment_mode, r.payment_date, r.reference_no, r.review_status,
                       r.uploaded_at, r.review_note,
                       (SELECT COALESCE(SUM(r2.amount_paid), 0) FROM maintenance_receipts r2
                        WHERE r2.bill_id = b.id AND r2.client_id = b.client_id AND r2.review_status = 'VERIFIED'
                       ) AS total_paid_all
                FROM maintenance_bills b
                JOIN society_members sm ON sm.id = b.member_id AND sm.client_id = b.client_id
                LEFT JOIN maintenance_receipts r ON r.bill_id = b.id AND r.client_id = b.client_id
                    AND r.id = (
                        SELECT r3.id FROM maintenance_receipts r3
                        WHERE r3.bill_id = b.id AND r3.client_id = b.client_id
                          AND r3.review_status IN ('PENDING','VERIFIED','REJECTED')
                        ORDER BY FIELD(r3.review_status, 'PENDING', 'REJECTED', 'VERIFIED'), r3.uploaded_at DESC
                        LIMIT 1
                    )
                WHERE b.client_id = ? AND b.billing_month = ?
            ";
            $params = [$cid, $month];

            if ($status && $status !== 'ALL') {
                $sql .= " AND b.status = ?";
                $params[] = $status;
            }
            if ($search) {
                $sql .= " AND (sm.full_name LIKE ? OR sm.flat_number LIKE ? OR sm.member_code LIKE ?)";
                $like = "%{$search}%";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $sql .= " ORDER BY sm.flat_number ASC, sm.full_name ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            json_response(['bills' => $stmt->fetchAll()]);
            break;

        // ── GENERATE MONTHLY BILLS ──
        case 'generate_bills':
            $month       = $input['month'] ?? date('Y-m-01');
            $carryForward = (bool)($input['carry_forward'] ?? true);

            // Get all active members
            $members = $pdo->prepare("SELECT * FROM society_members WHERE client_id = ? AND status = 'active'");
            $members->execute([$cid]);
            $memberList = $members->fetchAll();

            if (empty($memberList)) {
                json_error('No active members found');
            }

            // Get previous month for carry-forward
            $prevMonth = date('Y-m-01', strtotime($month . ' -1 month'));

            $inserted = 0;
            $skipped  = 0;

            $insertBill = $pdo->prepare("
                INSERT IGNORE INTO maintenance_bills (client_id, member_id, billing_month, amount_due, previous_due, total_due, due_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($memberList as $m) {
                $previousDue = 0;

                if ($carryForward) {
                    // Check last month's bill + verified payments
                    $prevBill = $pdo->prepare("
                        SELECT b.total_due, b.status, COALESCE(SUM(r.amount_paid), 0) AS paid
                        FROM maintenance_bills b
                        LEFT JOIN maintenance_receipts r ON r.bill_id = b.id AND r.client_id = b.client_id AND r.review_status = 'VERIFIED'
                        WHERE b.member_id = ? AND b.billing_month = ? AND b.client_id = ?
                        GROUP BY b.id
                    ");
                    $prevBill->execute([$m['id'], $prevMonth, $cid]);
                    $prev = $prevBill->fetch();

                    if ($prev) {
                        $due  = (float)$prev['total_due'];
                        $paid = (float)$prev['paid'];

                        if ($prev['status'] === 'VERIFIED') {
                            if ($paid > $due) {
                                // Overpaid — credit the excess
                                $previousDue = -1 * ($paid - $due);
                            } else {
                                $previousDue = 0;
                            }
                        } elseif ($prev['status'] === 'PARTIAL') {
                            $previousDue = max(0, $due - $paid);
                        } else {
                            $previousDue = $due;
                        }
                    }
                }

                $amountDue = (float)$m['monthly_amount'];
                $totalDue  = max(0, $amountDue + $previousDue);
                $dueDay    = (int)$m['due_day'];
                $dueDate   = date('Y-m-' . str_pad($dueDay, 2, '0', STR_PAD_LEFT), strtotime($month));

                $insertBill->execute([
                    $cid, $m['id'], $month, $amountDue, $previousDue, $totalDue, $dueDate
                ]);

                if ($insertBill->rowCount() > 0) {
                    $newBillId = (int)$pdo->lastInsertId();
                    if ($newBillId <= 0) {
                        $billLookup = $pdo->prepare("SELECT id FROM maintenance_bills WHERE client_id=? AND member_id=? AND billing_month=? LIMIT 1");
                        $billLookup->execute([$cid, $m['id'], $month]);
                        $newBillId = (int)$billLookup->fetchColumn();
                    }
                    if ($newBillId > 0) {
                        try {
                            sendMemberNewBill($pdo, $cid, $newBillId);
                        } catch (Throwable $e) {
                            error_log('[PORTAL NOTIFY] generate bills: ' . $e->getMessage());
                        }
                    }
                    $inserted++;
                } else {
                    $skipped++;
                }
            }

            logAudit($pdo, $cid, $session['username'] ?? '', 'GENERATE_BILLS',
                "Generated bills for {$month}: {$inserted} created, {$skipped} already existed");

            json_response([
                'success'  => true,
                'inserted' => $inserted,
                'skipped'  => $skipped,
                'month'    => $month,
                'message'  => "{$inserted} bills generated, {$skipped} already existed"
            ]);
            break;

        // ── MARK OVERDUE ──
        case 'mark_overdue':
            $billIdsStmt = $pdo->prepare("
                SELECT id
                FROM maintenance_bills
                WHERE client_id = ? AND due_date < CURDATE() AND status = 'PENDING'
            ");
            $billIdsStmt->execute([$cid]);
            $billIds = array_map('intval', $billIdsStmt->fetchAll(PDO::FETCH_COLUMN));

            $stmt = $pdo->prepare("
                UPDATE maintenance_bills
                SET status = 'OVERDUE', overdue_flagged_at = NOW()
                WHERE client_id = ? AND due_date < CURDATE() AND status = 'PENDING'
            ");
            $stmt->execute([$cid]);
            $count = $stmt->rowCount();

            foreach ($billIds as $billId) {
                try {
                    sendMemberOverdue($pdo, $cid, $billId);
                } catch (Throwable $e) {
                    error_log('[PORTAL NOTIFY] mark overdue: ' . $e->getMessage());
                }
            }

            json_response(['success' => true, 'marked' => $count, 'message' => "{$count} bills marked as overdue"]);
            break;

        // ── SAVE SETTINGS ──
        case 'save_settings':
            $pincode = trim($input['pincode'] ?? '');
            if ($pincode !== '' && !preg_match('/^\d{6}$/', $pincode)) {
                echo json_encode(['error' => 'Pincode must be exactly 6 digits']);
                break;
            }

            $cols = [
                'society_name'    => trim($input['society_name'] ?? 'Our Society'),
                'address'         => trim($input['address'] ?? ''),
                'landmark'        => trim($input['landmark'] ?? ''),
                'city'            => trim($input['city'] ?? ''),
                'state_name'      => trim($input['state_name'] ?? ''),
                'pincode'         => $pincode,
                'default_amount'  => (float)($input['default_amount'] ?? 0),
                'default_due_day' => max(1, min(28, (int)($input['default_due_day'] ?? 10))),
                'admin_emails'    => trim($input['admin_emails'] ?? ''),
                'admin_phone'     => trim($input['admin_phone'] ?? ''),
                'notes_partial'   => trim($input['notes_partial'] ?? ''),
                'notes_exact'     => trim($input['notes_exact'] ?? ''),
                'notes_overdue'   => trim($input['notes_overdue'] ?? ''),
                'notes_overpaid'  => trim($input['notes_overpaid'] ?? ''),
                'penalty_amount'  => (float)($input['penalty_amount'] ?? 0),
                'penalty_days'    => max(0, (int)($input['penalty_days'] ?? 0)),
                'notify_email'    => (int)($input['notify_email'] ?? 1),
                'notify_sms'      => (int)($input['notify_sms'] ?? 0),
                'notify_whatsapp' => (int)($input['notify_whatsapp'] ?? 0),
                'payment_upi_id'        => trim($input['payment_upi_id'] ?? ''),
                'payment_account_name'  => trim($input['payment_account_name'] ?? ''),
                'payment_instructions'  => trim($input['payment_instructions'] ?? ''),
                'max_attachment_size_mb'=> max(1, min(50, (int)($input['max_attachment_size_mb'] ?? 5))),
                'updated_by'            => $session['username'] ?? 'admin',
            ];

            $sets = implode(', ', array_map(fn($k) => "{$k} = VALUES({$k})", array_keys($cols)));
            $placeholders = implode(', ', array_fill(0, count($cols) + 1, '?'));
            $colNames = 'client_id, ' . implode(', ', array_keys($cols));

            $stmt = $pdo->prepare("
                INSERT INTO maintenance_settings ({$colNames}) VALUES ({$placeholders})
                ON DUPLICATE KEY UPDATE {$sets}
            ");
            $stmt->execute(array_merge([$cid], array_values($cols)));

            json_response(['success' => true, 'message' => 'Settings saved']);
            break;

        // ── GET SETTINGS ──
        case 'get_settings':
            $stmt = $pdo->prepare("SELECT * FROM maintenance_settings WHERE client_id = ?");
            $stmt->execute([$cid]);
            $settings = $stmt->fetch() ?: [];
            $defaults = [
                'society_name'    => 'Our Society', 'address'    => '', 'landmark'    => '',
                'city'            => '', 'state_name' => '', 'pincode'     => '',
                'default_amount'  => 0, 'default_due_day' => 10, 'admin_emails' => '',
                'admin_phone'     => '', 'notes_partial'  => '', 'notes_exact'  => '',
                'notes_overdue'   => '', 'notes_overpaid' => '', 'penalty_amount' => 0,
                'penalty_days'    => 0, 'notify_email'   => 1, 'notify_sms'    => 0,
                'notify_whatsapp' => 0,
                'payment_upi_id'  => '', 'payment_account_name' => '', 'payment_bank_name' => '',
                'payment_account_number' => '', 'payment_ifsc' => '', 'payment_instructions' => '',
                'sqft_rate' => 0, 'max_attachment_size_mb' => 5,
            ];
            json_response(array_merge($defaults, $settings));
            break;

        // ── GET SQFT RATE (public, no session required — used by register_member.php) ──
        case 'get_sqft_rate':
            $targetCid = trim($input['client_id'] ?? '');
            if (!$targetCid) json_error('client_id required');
            $r = $pdo->prepare("SELECT sqft_rate, society_name FROM maintenance_settings WHERE client_id = ?");
            $r->execute([$targetCid]);
            $row = $r->fetch();
            json_response([
                'sqft_rate'   => $row ? (float)$row['sqft_rate']   : 0,
                'society_name'=> $row ? ($row['society_name'] ?? '') : '',
            ]);
            break;

        // ── RECENT UPLOADS (for dashboard) ──
        case 'recent_uploads':
            $limit = min(10, max(1, (int)($input['limit'] ?? 5)));
            $stmt = $pdo->prepare("
                SELECT r.*, sm.full_name, sm.flat_number, sm.member_code,
                       b.billing_month, b.total_due
                FROM maintenance_receipts r
                JOIN society_members sm ON sm.id = r.member_id AND sm.client_id = r.client_id
                JOIN maintenance_bills b ON b.id = r.bill_id
                WHERE r.client_id = ? AND r.review_status IN ('PENDING','VERIFIED','REJECTED')
                ORDER BY r.uploaded_at DESC
                LIMIT ?
            ");
            $stmt->execute([$cid, $limit]);
            json_response(['uploads' => $stmt->fetchAll()]);
            break;

        default:
            json_error('Unknown action: ' . $action);
    }

} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
