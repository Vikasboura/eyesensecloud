<?php
/**
 * EyeSense — Maintenance Notification Helper (v3)
 * All emails: emoji-free, plain HTML safe for all clients.
 */
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_notifications_helper.php';

// ── Shared helpers ──────────────────────────────────────────────

function _mSettings(PDO $pdo, string $cid): array {
    $s = $pdo->prepare("SELECT * FROM maintenance_settings WHERE client_id = ?");
    $s->execute([$cid]);
    return $s->fetch() ?: [];
}

function _adminEmails(array $settings): array {
    if (empty(trim($settings['admin_emails'] ?? ''))) return [];
    return array_values(array_filter(
        array_map('trim', explode(',', $settings['admin_emails'])),
        fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)
    ));
}

function _emailTpl(string $headerBg, string $title, string $subtitle, string $body, string $footerNote = ''): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">
<div style="max-width:580px;margin:24px auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
  <div style="background:{$headerBg};padding:28px 32px;text-align:center;">
    <h1 style="color:#ffffff;margin:0;font-size:20px;font-weight:bold;">{$title}</h1>
    <p style="color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:13px;">{$subtitle}</p>
  </div>
  <div style="padding:28px 32px;">{$body}</div>
  <div style="padding:14px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">
    <p style="color:#9ca3af;margin:0;font-size:11px;">{$footerNote}EyeSense Cloud Portal</p>
  </div>
</div>
</body>
</html>
HTML;
}

function _row(string $label, string $value, string $color = '#111827'): string {
    return "<tr>
      <td style='padding:8px 0;color:#6b7280;font-size:14px;'>{$label}</td>
      <td style='padding:8px 0;text-align:right;font-weight:600;font-size:14px;color:{$color};'>{$value}</td>
    </tr>";
}

function _table(array $rows): string {
    $html = '<table style="width:100%;border-collapse:collapse;">';
    foreach ($rows as $r) $html .= $r;
    return $html . '</table>';
}

function _divider(): string {
    return '<tr><td colspan="2"><hr style="border:none;border-top:1px solid #e5e7eb;margin:4px 0;"></td></tr>';
}

function _infoBox(string $bg, string $border, string $text): string {
    return "<div style='margin-top:16px;padding:14px;background:{$bg};border-radius:8px;border:1px solid {$border};'>"
         . "<p style='color:{$text};font-size:13px;margin:0;'>\$1</p></div>";
}

function _send(array $recipients, string $subject, string $html, string $attachmentPath = ''): bool {
    $ok = true;
    foreach ($recipients as $to) {
        if (!indsacSendEmail($to, $subject, $html, $attachmentPath)) $ok = false;
    }
    return $ok;
}

// ── 1. Receipt Uploaded — admin + member ───────────────────────

function sendMaintenanceAdminNotification(
    PDO $pdo, string $clientId, array $member, array $bill, array $receipt, string $filePath = ''
): bool {
    $s           = _mSettings($pdo, $clientId);
    $societyName = $s['society_name'] ?? 'Our Society';
    $admins      = _adminEmails($s);
    $memberEmail = trim($member['email'] ?? '');

    $month    = date('F Y', strtotime($bill['billing_month']));
    $paid     = number_format((float)$receipt['amount_paid'], 2);
    $due      = number_format((float)$bill['total_due'], 2);

    // Cumulative paid (including this receipt)
    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM maintenance_receipts WHERE bill_id=? AND client_id=? AND review_status='VERIFIED'");
    $sumStmt->execute([$bill['id'] ?? $receipt['bill_id'], $clientId]);
    $totalPaid = (float)$sumStmt->fetchColumn() + (float)$receipt['amount_paid'];
    $remaining = max(0, (float)$bill['total_due'] - $totalPaid);

    $refRow = !empty($receipt['reference_no']) ? [_row('Reference No.', htmlspecialchars($receipt['reference_no']))] : [];

    $rows = array_merge([
        _row('Member',                   htmlspecialchars($member['full_name'])),
        _row('Flat / Unit',              htmlspecialchars($member['flat_number'])),
        _row('Billing Month',            $month),
        _divider(),
        _row('Total Payable',            "Rs. {$due}", '#6366f1'),
        _row('This Receipt Paid',        "Rs. {$paid}", '#059669'),
        _row('Remaining Balance',        $remaining > 0 ? "Rs. ".number_format($remaining,2) : 'Fully Paid', $remaining > 0 ? '#dc2626' : '#059669'),
        _divider(),
        _row('Payment Mode',             htmlspecialchars($receipt['payment_mode'])),
        _row('Payment Date',             date('d M Y', strtotime($receipt['payment_date']))),
        _row('Receipt No.',              htmlspecialchars($receipt['receipt_number'])),
    ], $refRow);

    $fileHtml = _buildFileEmbed($filePath, $receipt);

    $adminBody = '<p style="color:#374151;font-size:14px;margin:0 0 16px;">A member has submitted a payment receipt. Please review and verify.</p>'
        . _table($rows)
        . $fileHtml
        . '<div style="margin-top:20px;padding:14px;background:#fef9c3;border-radius:8px;border:1px solid #fde68a;text-align:center;">'
        . '<p style="color:#92400e;margin:0;font-size:13px;font-weight:bold;">Action Required: Please log in to verify or reject this receipt.</p>'
        . '</div>';

    $adminHtml = _emailTpl('#4f46e5', 'New Receipt Uploaded', "{$societyName} — {$month}", $adminBody);
    $adminSubj = "[EyeSense] Receipt Uploaded - {$member['full_name']} | Flat {$member['flat_number']} | {$month}";

    $memberBody = '<p style="color:#374151;font-size:15px;">Dear <strong>' . htmlspecialchars($member['full_name']) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;line-height:1.6;margin:8px 0 16px;">Your payment receipt for <strong>' . $month . '</strong> has been submitted successfully and is awaiting admin verification.</p>'
        . '<div style="background:#f9fafb;border-radius:10px;padding:18px;border:1px solid #e5e7eb;">'
        . _table([
            _row('Receipt No.',    htmlspecialchars($receipt['receipt_number'])),
            _row('Amount Paid',   "Rs. {$paid}", '#059669'),
            _row('Remaining',     $remaining > 0 ? "Rs. ".number_format($remaining,2) : 'Fully Paid', $remaining > 0 ? '#dc2626' : '#059669'),
            _row('Billing Month', $month),
          ])
        . '</div>'
        . '<p style="color:#6b7280;font-size:13px;margin-top:16px;">You will receive another email once the admin reviews your receipt.</p>';

    $memberHtml = _emailTpl('#059669', 'Receipt Submitted Successfully', $societyName, $memberBody);
    $memberSubj = "[EyeSense] Receipt Submitted - {$receipt['receipt_number']} - {$month}";

    try {
        $billId = (int)($bill['id'] ?? $receipt['bill_id'] ?? 0);
        $receiptId = (int)($receipt['id'] ?? 0);
        $memberEmployeeId = trim((string)($member['employee_id'] ?? ''));
        if ($memberEmployeeId === '' && !empty($member['id'])) {
            $memberEmployeeId = portalGetMemberEmployeeId($pdo, $clientId, (int)$member['id']);
        }

        if ($memberEmployeeId !== '') {
            portalNotify(
                $pdo,
                $clientId,
                $memberEmployeeId,
                'Payment Waiting for Approval',
                "Receipt {$receipt['receipt_number']} for {$month} is waiting for admin review.",
                'WARNING',
                'my_dues.php',
                ['bill_id' => $billId, 'receipt_id' => $receiptId, 'event' => 'receipt_uploaded']
            );
        }

        portalNotifyMaintenanceAdmins(
            $pdo,
            $clientId,
            'Receipt Waiting for Review',
            "{$member['full_name']} uploaded {$receipt['receipt_number']} for {$month}.",
            'WARNING',
            'pending_receipts.php',
            ['bill_id' => $billId, 'receipt_id' => $receiptId, 'member_id' => (int)($member['id'] ?? 0), 'event' => 'receipt_uploaded']
        );
    } catch (Throwable $e) {
        error_log('[PORTAL NOTIFY] receipt upload: ' . $e->getMessage());
    }

    $ok = _send($admins, $adminSubj, $adminHtml, $filePath);
    if ($memberEmail) indsacSendEmail($memberEmail, $memberSubj, $memberHtml);

    logMaintenanceAlert($pdo, $clientId, $receipt['id'] ?? 0, 'MAINTENANCE_RECEIPT_UPLOAD',
        "Receipt {$receipt['receipt_number']} by {$member['full_name']} - Rs.{$paid}", $ok ? 'SENT' : 'PARTIAL');

    if (!empty($receipt['id']))
        $pdo->prepare("UPDATE maintenance_receipts SET notification_sent=1 WHERE id=?")->execute([$receipt['id']]);

    return $ok;
}

// ── 2. Receipt Verified ────────────────────────────────────────

function sendMemberReceiptVerified(PDO $pdo, string $cid, int $receiptId): void {
    $stmt = $pdo->prepare("
        SELECT r.*, b.billing_month, b.total_due, b.status AS bill_status,
               sm.full_name, sm.flat_number, sm.email, sm.employee_id
        FROM maintenance_receipts r
        JOIN maintenance_bills b ON b.id=r.bill_id AND b.client_id=r.client_id
        JOIN society_members sm ON sm.id=r.member_id AND sm.client_id=r.client_id
        WHERE r.id=? AND r.client_id=?
    ");
    $stmt->execute([$receiptId, $cid]);
    $d = $stmt->fetch();
    if (!$d) return;

    $s           = _mSettings($pdo, $cid);
    $societyName = $s['society_name'] ?? 'Our Society';
    $month       = date('F Y', strtotime($d['billing_month']));
    $paid        = number_format((float)$d['amount_paid'], 2);

    $cumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM maintenance_receipts WHERE bill_id=? AND client_id=? AND review_status='VERIFIED'");
    $cumStmt->execute([$d['bill_id'], $cid]);
    $totalPaid   = (float)$cumStmt->fetchColumn();
    $remaining   = max(0, (float)$d['total_due'] - $totalPaid);
    $isFullyPaid = $remaining <= 0;
    $note        = $isFullyPaid ? ($s['notes_exact'] ?? '') : ($s['notes_partial'] ?? '');

    try {
        $memberEmployeeId = trim((string)($d['employee_id'] ?? ''));
        if ($memberEmployeeId !== '') {
            portalNotify(
                $pdo,
                $cid,
                $memberEmployeeId,
                $isFullyPaid ? 'Payment Approved' : 'Partial Payment Approved',
                "Admin approved receipt {$d['receipt_number']} for {$month}.",
                'SUCCESS',
                'my_dues.php',
                ['bill_id' => (int)$d['bill_id'], 'receipt_id' => $receiptId, 'event' => 'receipt_verified', 'bill_status' => $d['bill_status']]
            );
        }
    } catch (Throwable $e) {
        error_log('[PORTAL NOTIFY] receipt verified: ' . $e->getMessage());
    }

    if (empty($d['email'])) return;

    $noteHtml = $note ? "<div style='margin-top:14px;padding:12px;background:#ecfdf5;border-radius:8px;border-left:4px solid #10b981;color:#065f46;font-size:13px;'>{$note}</div>" : '';

    $body = '<p style="color:#374151;font-size:15px;">Dear <strong>' . htmlspecialchars($d['full_name']) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;margin:8px 0 16px;">Your payment receipt has been <strong style="color:#059669;">VERIFIED</strong> by the admin.</p>'
        . '<div style="background:#f9fafb;border-radius:10px;padding:18px;border:1px solid #e5e7eb;">'
        . _table([
            _row('Receipt No.',  htmlspecialchars($d['receipt_number'])),
            _row('Billing Month', $month),
            _row('Amount Verified', "Rs. {$paid}", '#059669'),
            _row('Total Paid',   'Rs. ' . number_format($totalPaid,2), '#059669'),
            _divider(),
            _row('Remaining Balance', $isFullyPaid ? 'Rs. 0.00 - Fully Paid' : 'Rs. ' . number_format($remaining,2),
                 $isFullyPaid ? '#059669' : '#d97706'),
            _row('Bill Status',  $isFullyPaid ? 'PAID IN FULL' : 'PARTIAL PAYMENT',
                 $isFullyPaid ? '#059669' : '#d97706'),
          ])
        . '</div>' . $noteHtml;

    $html = _emailTpl('#059669', 'Payment Receipt Verified', $societyName, $body);
    indsacSendEmail($d['email'], "[EyeSense] Receipt Verified - {$d['receipt_number']} - {$month}", $html);
}

// ── 3. Receipt Rejected ────────────────────────────────────────

function sendMemberReceiptRejected(PDO $pdo, string $cid, int $receiptId, string $reason): void {
    $stmt = $pdo->prepare("
        SELECT r.*, b.billing_month, sm.full_name, sm.email, sm.employee_id
        FROM maintenance_receipts r
        JOIN maintenance_bills b ON b.id=r.bill_id AND b.client_id=r.client_id
        JOIN society_members sm ON sm.id=r.member_id AND sm.client_id=r.client_id
        WHERE r.id=? AND r.client_id=?
    ");
    $stmt->execute([$receiptId, $cid]);
    $d = $stmt->fetch();
    if (!$d) return;

    $s           = _mSettings($pdo, $cid);
    $societyName = $s['society_name'] ?? 'Our Society';
    $month       = date('F Y', strtotime($d['billing_month']));

    try {
        $memberEmployeeId = trim((string)($d['employee_id'] ?? ''));
        if ($memberEmployeeId !== '') {
            portalNotify(
                $pdo,
                $cid,
                $memberEmployeeId,
                'Payment Rejected',
                "Admin rejected receipt {$d['receipt_number']} for {$month}: " . mb_substr($reason, 0, 120),
                'ERROR',
                'my_dues.php',
                ['bill_id' => (int)$d['bill_id'], 'receipt_id' => $receiptId, 'event' => 'receipt_rejected']
            );
        }
    } catch (Throwable $e) {
        error_log('[PORTAL NOTIFY] receipt rejected: ' . $e->getMessage());
    }

    if (empty($d['email'])) return;

    $body = '<p style="color:#374151;font-size:15px;">Dear <strong>' . htmlspecialchars($d['full_name']) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;margin:8px 0 16px;">Unfortunately, your payment receipt has been <strong style="color:#dc2626;">REJECTED</strong> by the admin.</p>'
        . '<div style="background:#fef2f2;border-radius:10px;padding:18px;border:1px solid #fecaca;">'
        . _table([
            _row('Receipt No.',  htmlspecialchars($d['receipt_number'])),
            _row('Billing Month', $month),
          ])
        . '<div style="margin-top:12px;padding:12px;background:#fee2e2;border-radius:8px;">'
        . '<p style="color:#b91c1c;font-size:13px;font-weight:bold;margin:0 0 4px;">Rejection Reason:</p>'
        . '<p style="color:#991b1b;font-size:13px;margin:0;">' . htmlspecialchars($reason) . '</p></div></div>'
        . '<p style="color:#6b7280;font-size:13px;margin-top:16px;">Please upload a corrected receipt with valid payment proof. Contact your admin if you have any questions.</p>';

    $html = _emailTpl('#dc2626', 'Payment Receipt Rejected', $societyName, $body);
    indsacSendEmail($d['email'], "[EyeSense] Receipt Rejected - {$d['receipt_number']} - {$month}", $html);
}

// ── 4. New Bill Generated ──────────────────────────────────────

function sendMemberNewBill(PDO $pdo, string $cid, int $billId): void {
    $stmt = $pdo->prepare("
        SELECT b.*, sm.full_name, sm.email, sm.flat_number, sm.member_code, sm.employee_id
        FROM maintenance_bills b
        JOIN society_members sm ON sm.id=b.member_id AND sm.client_id=b.client_id
        WHERE b.id=? AND b.client_id=?
    ");
    $stmt->execute([$billId, $cid]);
    $d = $stmt->fetch();
    if (!$d) return;

    $s           = _mSettings($pdo, $cid);
    $societyName = $s['society_name'] ?? 'Our Society';
    $month       = date('F Y', strtotime($d['billing_month']));
    $totalDue    = number_format((float)$d['total_due'], 2);
    $dueDate     = date('d M Y', strtotime($d['due_date']));
    $prevDue     = (float)$d['previous_due'];
    $curDue      = (float)$d['amount_due'];

    try {
        $memberEmployeeId = trim((string)($d['employee_id'] ?? ''));
        if ($memberEmployeeId !== '') {
            portalNotify(
                $pdo,
                $cid,
                $memberEmployeeId,
                "Bill Generated - {$month}",
                "Your maintenance bill of Rs. {$totalDue} is due by {$dueDate}.",
                'INFO',
                'my_dues.php',
                ['bill_id' => $billId, 'event' => 'bill_generated', 'billing_month' => $d['billing_month']]
            );
        }
    } catch (Throwable $e) {
        error_log('[PORTAL NOTIFY] new bill: ' . $e->getMessage());
    }

    if (empty($d['email'])) return;

    // Build carry-forward rows
    $carryRows = [];
    if ($prevDue > 0) {
        $carryRows[] = _row('Previous Unpaid Balance (Carried Forward)', 'Rs. ' . number_format($prevDue, 2), '#dc2626');
    } elseif ($prevDue < 0) {
        $carryRows[] = _row('Overpayment Credit Applied', '- Rs. ' . number_format(abs($prevDue), 2), '#059669');
    }

    $rows = array_merge(
        [_row('Current Month Maintenance', 'Rs. ' . number_format($curDue, 2))],
        $carryRows,
        [
            _divider(),
            _row('Total Payable Amount', 'Rs. ' . $totalDue, '#4f46e5'),
            _row('Payment Due Date',     $dueDate, '#dc2626'),
        ]
    );

    // Build carry-forward notice
    $carryNotice = '';
    if ($prevDue > 0) {
        $carryNotice = '<div style="margin-top:16px;padding:12px;background:#fef3c7;border-radius:8px;border-left:4px solid #f59e0b;color:#92400e;font-size:13px;">'
            . 'Note: Your previous month had an unpaid balance of Rs. ' . number_format($prevDue, 2) . ' which has been added to this bill.</div>';
    } elseif ($prevDue < 0) {
        $carryNotice = '<div style="margin-top:16px;padding:12px;background:#ecfdf5;border-radius:8px;border-left:4px solid #10b981;color:#065f46;font-size:13px;">'
            . 'Note: You had an overpayment credit of Rs. ' . number_format(abs($prevDue), 2) . ' from last month which has been applied to this bill.</div>';
    }

    $body = '<p style="color:#374151;font-size:15px;">Dear <strong>' . htmlspecialchars($d['full_name']) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;line-height:1.6;margin:8px 0 16px;">Your maintenance bill for <strong>' . $month . '</strong> has been generated.</p>'
        . '<div style="background:#f9fafb;border-radius:10px;padding:18px;border:1px solid #e5e7eb;">'
        . _table($rows)
        . '</div>'
        . $carryNotice
        . '<p style="color:#6b7280;font-size:13px;margin-top:16px;">Please log in to the portal to upload your payment receipt before <strong>' . $dueDate . '</strong>.</p>';

    $html = _emailTpl('#4f46e5', 'New Bill Generated - ' . $month, $societyName, $body);
    indsacSendEmail($d['email'], "[EyeSense] Bill Generated - {$month} - Rs. {$totalDue} Due by {$dueDate}", $html);
}

// ── 5. Bill Overdue ────────────────────────────────────────────

function sendMemberOverdue(PDO $pdo, string $cid, int $billId): void {
    $stmt = $pdo->prepare("
        SELECT b.*, sm.full_name, sm.email, sm.flat_number, sm.employee_id
        FROM maintenance_bills b
        JOIN society_members sm ON sm.id=b.member_id AND sm.client_id=b.client_id
        WHERE b.id=? AND b.client_id=?
    ");
    $stmt->execute([$billId, $cid]);
    $d = $stmt->fetch();
    if (!$d) return;

    $s           = _mSettings($pdo, $cid);
    $societyName = $s['society_name'] ?? 'Our Society';
    $month       = date('F Y', strtotime($d['billing_month']));
    $due         = number_format((float)$d['total_due'], 2);
    $dueDate     = date('d M Y', strtotime($d['due_date']));
    $note        = $s['notes_overdue'] ?? '';

    try {
        $memberEmployeeId = trim((string)($d['employee_id'] ?? ''));
        if ($memberEmployeeId !== '') {
            portalNotify(
                $pdo,
                $cid,
                $memberEmployeeId,
                "Bill Overdue - {$month}",
                "Your maintenance bill of Rs. {$due} was due on {$dueDate}.",
                'ERROR',
                'my_dues.php',
                ['bill_id' => $billId, 'event' => 'bill_overdue', 'billing_month' => $d['billing_month']]
            );
        }
    } catch (Throwable $e) {
        error_log('[PORTAL NOTIFY] overdue bill: ' . $e->getMessage());
    }

    if (empty($d['email'])) return;

    $penaltyHtml = '';
    if (!empty($s['penalty_amount']) && !empty($s['penalty_days'])) {
        $penaltyHtml = '<div style="margin-top:14px;padding:12px;background:#fef9c3;border-radius:8px;border:1px solid #fde68a;">'
            . '<p style="color:#92400e;font-size:13px;margin:0;font-weight:bold;">Penalty Notice</p>'
            . '<p style="color:#b45309;font-size:13px;margin:4px 0 0;">A penalty of Rs. ' . $s['penalty_amount'] . ' is added every ' . $s['penalty_days'] . ' day(s) for late payment.</p>'
            . '</div>';
    }
    $noteHtml = $note ? '<div style="margin-top:14px;padding:12px;background:#fef2f2;border-radius:8px;border-left:4px solid #ef4444;color:#991b1b;font-size:13px;">' . htmlspecialchars($note) . '</div>' : '';

    $body = '<p style="color:#374151;font-size:15px;">Dear <strong>' . htmlspecialchars($d['full_name']) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;margin:8px 0 16px;">Your maintenance payment for <strong>' . $month . '</strong> is <strong style="color:#dc2626;">OVERDUE</strong>.</p>'
        . '<div style="background:#fef2f2;border-radius:10px;padding:18px;border:1px solid #fecaca;">'
        . _table([
            _row('Billing Month', $month),
            _row('Due Date',      $dueDate, '#dc2626'),
            _row('Amount Due',    "Rs. {$due}", '#dc2626'),
          ])
        . '</div>' . $penaltyHtml . $noteHtml
        . '<p style="color:#6b7280;font-size:13px;margin-top:16px;">Please pay immediately to avoid further penalties. Log in to the portal to upload your payment receipt.</p>';

    $html = _emailTpl('#dc2626', 'Payment OVERDUE - Action Required', "{$societyName} - {$month}", $body);
    indsacSendEmail($d['email'], "[EyeSense] OVERDUE - {$month} - Rs. {$due} Not Paid", $html);
}

// ── 6. New Member Welcome (created by admin) ─────────────────

function sendNewMemberWelcome(
    PDO    $pdo,
    string $cid,
    string $memberEmail,
    string $memberName,
    string $flatNumber,
    string $memberCode,
    string $loginId,
    string $plainPassword,
    float  $monthlyAmount,
    int    $dueDay
): void {
    if (!$memberEmail || !filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) return;

    $s           = _mSettings($pdo, $cid);
    $societyName = $s['society_name'] ?? 'Our Society';
    $loginUrl    = rtrim(DB_CFG_PORTAL_BASE_URL, '/') . '/login.php';

    $body = '<p style="color:#374151;font-size:15px;">Dear <strong>' . htmlspecialchars($memberName) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;line-height:1.7;margin:8px 0 20px;">'
        . 'Welcome to <strong>' . htmlspecialchars($societyName) . '</strong>. Your member account has been created by the administrator. '
        . 'You can now log in to the EyeSense Cloud Portal to view your maintenance dues and upload payment receipts.</p>'

        . '<div style="background:#f9fafb;border-radius:10px;padding:20px;border:1px solid #e5e7eb;margin-bottom:20px;">'
        . '<p style="color:#374151;font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 12px;">Your Login Credentials</p>'
        . _table([
            _row('Client ID',   htmlspecialchars($cid)),
            _row('Login ID',    htmlspecialchars($loginId)),
            _row('Password',    htmlspecialchars($plainPassword)),
          ])
        . '</div>'

        . '<div style="background:#f9fafb;border-radius:10px;padding:20px;border:1px solid #e5e7eb;margin-bottom:20px;">'
        . '<p style="color:#374151;font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 12px;">Your Member Details</p>'
        . _table([
            _row('Full Name',           htmlspecialchars($memberName)),
            _row('Flat / Unit',         htmlspecialchars($flatNumber)),
            _row('Member Code',         htmlspecialchars($memberCode)),
            _divider(),
            _row('Monthly Maintenance', 'Rs. ' . number_format($monthlyAmount, 2), '#4f46e5'),
            _row('Due Day',             'Day ' . $dueDay . ' of each month'),
          ])
        . '</div>'

        . '<div style="margin-top:4px;padding:16px;background:#eef2ff;border-radius:8px;border:1px solid #c7d2fe;">'
        . '<p style="color:#374151;font-size:13px;font-weight:bold;margin:0 0 6px;">How to Log In</p>'
        . '<p style="color:#4338ca;font-size:13px;margin:0;">Visit: <a href="' . htmlspecialchars($loginUrl) . '" style="color:#4f46e5;font-weight:bold;">'
        . htmlspecialchars($loginUrl) . '</a></p>'
        . '<p style="color:#6b7280;font-size:12px;margin:6px 0 0;">Use your Client ID, Login ID and the password above to sign in.</p>'
        . '</div>'

        . '<p style="color:#9ca3af;font-size:12px;margin-top:20px;border-top:1px solid #e5e7eb;padding-top:14px;">'
        . 'Please change your password after your first login. If you did not expect this email or believe this was sent in error, '
        . 'please contact your society administrator immediately.</p>';

    $html = _emailTpl(
        '#4f46e5',
        'Welcome to ' . htmlspecialchars($societyName),
        'Your portal account is now active',
        $body
    );

    indsacSendEmail(
        $memberEmail,
        '[EyeSense] Welcome - Your Member Account Is Ready | ' . htmlspecialchars($societyName),
        $html
    );
}

// ── 7. New Administrator Welcome (created by SA) ──────────────

function sendNewAdminWelcome(
    PDO    $pdo,
    string $cid,
    string $adminEmail,
    string $adminName,
    string $loginId,
    string $plainPassword
): void {
    if (!$adminEmail || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) return;

    $s           = _mSettings($pdo, $cid);
    $societyName = $s['society_name'] ?? 'Our Society';
    $loginUrl    = rtrim(DB_CFG_PORTAL_BASE_URL, '/') . '/login.php';

    $body = '<p style="color:#374151;font-size:15px;">Dear <strong>' . htmlspecialchars($adminName) . '</strong>,</p>'
        . '<p style="color:#6b7280;font-size:14px;line-height:1.7;margin:8px 0 20px;">'
        . 'An administrator account has been created for you on the EyeSense Cloud Portal for <strong>' . htmlspecialchars($societyName) . '</strong>. '
        . 'As an administrator, you have full access to manage members, billing, and society settings.</p>'

        . '<div style="background:#f9fafb;border-radius:10px;padding:20px;border:1px solid #e5e7eb;margin-bottom:20px;">'
        . '<p style="color:#374151;font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 12px;">Your Login Credentials</p>'
        . _table([
            _row('Client ID', htmlspecialchars($cid)),
            _row('Login ID',  htmlspecialchars($loginId)),
            _row('Password',  htmlspecialchars($plainPassword)),
          ])
        . '</div>'

        . '<div style="background:#f9fafb;border-radius:10px;padding:20px;border:1px solid #e5e7eb;margin-bottom:20px;">'
        . '<p style="color:#374151;font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 12px;">Account Information</p>'
        . _table([
            _row('Full Name', htmlspecialchars($adminName)),
            _row('Role',      'Administrator (Full Access)'),
            _row('Society',   htmlspecialchars($societyName)),
          ])
        . '</div>'

        . '<div style="margin-top:4px;padding:16px;background:#eef2ff;border-radius:8px;border:1px solid #c7d2fe;">'
        . '<p style="color:#374151;font-size:13px;font-weight:bold;margin:0 0 6px;">How to Log In</p>'
        . '<p style="color:#4338ca;font-size:13px;margin:0;">Visit: <a href="' . htmlspecialchars($loginUrl) . '" style="color:#4f46e5;font-weight:bold;">'
        . htmlspecialchars($loginUrl) . '</a></p>'
        . '<p style="color:#6b7280;font-size:12px;margin:6px 0 0;">Use your Client ID, Login ID and the password above to sign in.</p>'
        . '</div>'

        . '<p style="color:#9ca3af;font-size:12px;margin-top:20px;border-top:1px solid #e5e7eb;padding-top:14px;">'
        . 'Please change your password after your first login. Keep your credentials confidential. '
        . 'If you did not expect this email or believe this was sent in error, please contact your organization immediately.</p>';

    $html = _emailTpl(
        '#1e3a5f',
        'Administrator Account Created',
        htmlspecialchars($societyName) . ' — EyeSense Portal',
        $body
    );

    indsacSendEmail(
        $adminEmail,
        '[EyeSense] Administrator Account Ready | ' . htmlspecialchars($societyName),
        $html
    );
}

// ── File Embed ─────────────────────────────────────────────────

function _buildFileEmbed(string $filePath, array $receipt): string {
    if (empty($filePath) || !file_exists($filePath)) return '';
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png'])) {
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        $b64  = base64_encode(file_get_contents($filePath));
        return '<div style="margin-top:20px;"><p style="color:#9ca3af;font-size:12px;margin:0 0 8px;">Attached Receipt Image</p>'
             . "<img src='data:{$mime};base64,{$b64}' style='max-width:100%;border-radius:8px;border:1px solid #e5e7eb;display:block;'></div>";
    }
    if ($ext === 'pdf') {
        $kb = round(filesize($filePath)/1024);
        return '<div style="margin-top:20px;padding:16px;background:#eef2ff;border-radius:8px;border:1px solid #c7d2fe;">'
             . "<p style='color:#4338ca;font-weight:bold;margin:0 0 4px;font-size:14px;'>PDF Receipt Attached</p>"
             . "<p style='color:#6b7280;margin:0;font-size:12px;'>{$kb} KB - Log in to portal to view</p></div>";
    }
    return '';
}

function buildFileEmbedHtml(string $filePath, array $receipt): string {
    return _buildFileEmbed($filePath, $receipt);
}

// ── INDSAC Email Sender ────────────────────────────────────────

function indsacSendEmail(string $to, string $subject, string $htmlBody, string $attachmentPath = ''): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    // Safety: if no email API URL is configured, log and return
    if (!defined('INDSAC_EMAIL_API_URL') || empty(INDSAC_EMAIL_API_URL)) {
        error_log("[INDSAC EMAIL] No INDSAC_EMAIL_API_URL configured. Email to {$to} not sent.");
        return false;
    }

    if (function_exists('curl_version')) {
        $ch = curl_init(INDSAC_EMAIL_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        // Bypass SSL verification to prevent 'unable to get local issuer certificate' errors on Windows local setups
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $postData = [
            'recipient_email' => $to,
            'subject'         => $subject,
            'message_body'    => $htmlBody,
        ];

        // If a valid attachment file path is provided, upload as multipart/form-data
        if (!empty($attachmentPath) && file_exists($attachmentPath)) {
            $postData['attachment'] = new CURLFile(
                $attachmentPath,
                mime_content_type($attachmentPath) ?: 'application/octet-stream',
                basename($attachmentPath)
            );
            // Do NOT set Content-Type header — curl sets multipart boundary automatically
        } else {
            // Plain JSON for emails without attachments
            $encoded = json_encode($postData);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($resp === false || $code === 0) { error_log("[INDSAC EMAIL] Network error to {$to}"); return false; }
            if ($code !== 200) { error_log("[INDSAC EMAIL] HTTP {$code} to {$to}"); return false; }
            return true;
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        // Fallback stream_context (no attachment support in this path)
        $payload = json_encode(['recipient_email' => $to, 'subject' => $subject, 'message_body' => $htmlBody]);
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        $resp = @file_get_contents(INDSAC_EMAIL_API_URL, false, $ctx);
        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 000';
        preg_match('/HTTP\/\S+\s+(\d+)/', $statusLine, $m);
        $code = (int)($m[1] ?? 0);
    }

    if ($resp === false || $code === 0) { error_log("[INDSAC EMAIL] Network error to {$to}"); return false; }
    if ($code !== 200) { error_log("[INDSAC EMAIL] HTTP {$code} to {$to}"); return false; }
    return true;
}

// ── Alert Logger ───────────────────────────────────────────────

function logMaintenanceAlert(PDO $pdo, string $clientId, int $receiptId, string $eventType, string $message, string $status = 'SENT'): void {
    try {
        $pdo->prepare("INSERT INTO alert_logs (client_id,event_id,event_type,alert_level,message,channel,status,timestamp) VALUES (?,?,?,'INFO',?,'EMAIL',?,NOW())")
            ->execute([$clientId, 'MAINT-' . $receiptId, $eventType, $message, $status]);
    } catch (Exception $e) {
        error_log("[MAINTENANCE ALERT LOG] " . $e->getMessage());
    }
}
