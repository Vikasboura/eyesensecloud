<?php
/**
 * EyeSense Cloud Portal — System-Generated Payment Receipt
 *
 * Renders a professional, printable receipt in HTML.
 * The browser's Print → Save as PDF produces a clean document.
 * Auth-gated: only the member themselves, or admins, can view.
 *
 * Usage:
 *   maintenance_receipt.php?receipt_id=42
 *   maintenance_receipt.php?receipt_id=42&auto_print=1  (triggers window.print)
 */
require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
requirePortalLogin();

$pdo     = get_indsac_db();
$session = getPortalSession();
$cid     = $session['client_id'];
$isSA    = !empty($session['is_superadmin']);
$perms   = getAllPermissions($pdo, $cid, $session['role']);

$receiptId = (int)($_GET['receipt_id'] ?? 0);
$autoPrint = !empty($_GET['auto_print']);

if ($receiptId <= 0) { die('Missing receipt_id'); }

// ── Fetch receipt + bill + member data ──
$stmt = $pdo->prepare("
    SELECT r.*, b.billing_month, b.amount_due, b.previous_due, b.total_due, b.due_date, b.status AS bill_status,
           sm.full_name, sm.flat_number, sm.member_code, sm.email, sm.mobile
    FROM maintenance_receipts r
    JOIN maintenance_bills b ON b.id = r.bill_id AND b.client_id = r.client_id
    JOIN society_members sm ON sm.id = r.member_id AND sm.client_id = r.client_id
    WHERE r.id = ? AND r.client_id = ?
");
$stmt->execute([$receiptId, $cid]);
$data = $stmt->fetch();

if (!$data) { die('Receipt not found'); }

// ── Auth check: admin or the member themselves ──
$empId = $_SESSION['portal_employee_id'] ?? '';
$isAdmin = $isSA || !empty($perms['MANAGE_MAINTENANCE']);
$isSelf  = ($empId && $data['uploaded_by'] === $empId);
if (!$isAdmin && !$isSelf) { die('Access denied'); }

// ── Fetch society settings ──
$setStmt = $pdo->prepare("SELECT * FROM maintenance_settings WHERE client_id = ?");
$setStmt->execute([$cid]);
$settings = $setStmt->fetch() ?: [];
$societyName = $settings['society_name'] ?? 'Society Maintenance';
$societyAddr = trim(implode(', ', array_filter([
    $settings['address']    ?? '',
    $settings['landmark']   ?? '',
    $settings['city']       ?? '',
    $settings['state_name'] ?? '',
    $settings['pincode']    ?? '',
])));

// ── Computed values ──
$billingMonth  = date('F Y', strtotime($data['billing_month']));
$paymentDate   = date('d M Y', strtotime($data['payment_date']));
$dueDate       = date('d M Y', strtotime($data['due_date']));
$uploadedAt    = date('d M Y, h:i A', strtotime($data['uploaded_at']));
$thisReceiptAmt = (float)$data['amount_paid'];
$amountPaid    = number_format($thisReceiptAmt, 2);
$baseAmount    = (float)$data['amount_due'] + (float)$data['previous_due'];
$totalDueVal   = (float)$data['total_due'];
$totalDue      = number_format($totalDueVal, 2);
$previousDue   = number_format((float)$data['previous_due'], 2);
$currentDue    = number_format((float)$data['amount_due'], 2);

// Late fee at the time of this payment
$rcptPenaltyAmt = (float)($settings['penalty_amount'] ?? 0);
$rcptPenaltyInt = max(1, (int)($settings['penalty_days'] ?? 1));
$lateFeeAtPayment = 0;
if ($rcptPenaltyAmt > 0 && !empty($data['due_date']) && strtotime($data['payment_date']) > strtotime($data['due_date'])) {
    $daysLateAtPay = max(0, (int)((strtotime($data['payment_date']) - strtotime($data['due_date'])) / 86400));
    if ($daysLateAtPay >= 1) {
        $intervalsAtPay = 1 + (int)floor($daysLateAtPay / $rcptPenaltyInt);
        $lateFeeAtPayment = round($rcptPenaltyAmt * $intervalsAtPay, 2);
    }
}
$totalWithFee = $baseAmount + $lateFeeAtPayment;

// Sum verified receipts BEFORE this one (chronological snapshot)
$prevPaidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM maintenance_receipts WHERE bill_id = ? AND client_id = ? AND review_status = 'VERIFIED' AND id < ?");
$prevPaidStmt->execute([$data['bill_id'], $cid, $receiptId]);
$previouslyPaid    = (float)$prevPaidStmt->fetchColumn();

// Cumulative paid up to and including this receipt
$cumulPaid     = $previouslyPaid + $thisReceiptAmt;

$balanceDueAtTime  = max(0, $totalWithFee - $previouslyPaid); // what was owed when this receipt was submitted

$remaining     = max(0, $totalWithFee - $cumulPaid);
$overpaid      = max(0, $cumulPaid - $totalWithFee);
$isPartial     = $cumulPaid < $totalWithFee;
$isVerified    = in_array($data['bill_status'], ['VERIFIED', 'PARTIAL']);
$statusLabel   = $isVerified ? ($isPartial ? 'PARTIAL PAYMENT' : ($overpaid > 0 ? 'OVERPAID' : 'PAID IN FULL')) : strtoupper($data['review_status'] ?? 'PENDING');
// Receipt note based on status
$noteText = '';
if ($isVerified && !$isPartial && $overpaid <= 0) $noteText = $settings['notes_exact'] ?? '';
elseif ($isVerified && $isPartial)                $noteText = $settings['notes_partial'] ?? '';
elseif ($overpaid > 0)                            $noteText = $settings['notes_overpaid'] ?? '';
elseif ($data['bill_status'] === 'OVERDUE')       $noteText = $settings['notes_overdue'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= htmlspecialchars($data['receipt_number']) ?> — <?= htmlspecialchars($societyName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── Print-first styling ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f5f7; color: #1d1d1f; font-size: 14px; line-height: 1.5;
        }
        .receipt-container {
            max-width: 700px; margin: 30px auto; background: #fff;
            border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* Header */
        .receipt-header {
            background: linear-gradient(135deg, #f3f4f6 0%, #16213e 50%, #0f3460 100%);
            color: #fff; padding: 32px 40px; text-align: center;
        }
        .receipt-header h1 { font-size: 22px; font-weight: 700; margin-bottom: 4px; letter-spacing: 0.5px; }
        .receipt-header .subtitle { font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; }
        .receipt-header .receipt-num {
            display: inline-block; margin-top: 16px; background: rgba(255,255,255,0.15);
            padding: 6px 20px; border-radius: 20px; font-family: monospace; font-size: 15px;
            letter-spacing: 1px; font-weight: 600;
        }

        /* Status badge */
        .status-badge {
            display: inline-block; padding: 5px 16px; border-radius: 20px; font-size: 11px;
            font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 12px;
        }
        .status-verified { background: #d1fae5; color: #065f46; }
        .status-partial  { background: #fef3c7; color: #92400e; }
        .status-overpaid { background: #dbeafe; color: #1e40af; }
        .status-pending  { background: #e5e7eb; color: #d1d5db; }
        .status-rejected { background: #fee2e2; color: #991b1b; }

        /* Body */
        .receipt-body { padding: 32px 40px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
        .info-block label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: #9ca3af; font-weight: 600; margin-bottom: 3px; }
        .info-block .value { font-size: 15px; font-weight: 600; color: #1d1d1f; }
        .info-block .value.mono { font-family: monospace; }

        /* Payment breakdown table */
        .breakdown { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .breakdown th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: #9ca3af; font-weight: 600; padding: 10px 0; border-bottom: 2px solid #f3f4f6; }
        .breakdown td { padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
        .breakdown td:last-child, .breakdown th:last-child { text-align: right; }
        .breakdown .total-row td { border-bottom: none; border-top: 2px solid #1d1d1f; font-weight: 700; font-size: 16px; padding-top: 14px; }
        .breakdown .paid-row td { color: #059669; font-weight: 600; }
        .breakdown .remaining-row td { color: #dc2626; font-weight: 600; }

        /* Footer */
        .receipt-footer {
            padding: 24px 40px; background: var(--bg); border-top: 1px solid #f3f4f6;
            font-size: 11px; color: #9ca3af; text-align: center;
        }
        .receipt-footer .note { margin-top: 8px; font-style: italic; }

        /* Print buttons (hidden on print) */
        .actions {
            max-width: 700px; margin: 16px auto; display: flex; gap: 10px; justify-content: center;
        }
        .actions button, .actions a {
            padding: 10px 24px; border-radius: 8px; font-size: 13px; font-weight: 600;
            cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-print { background: var(--surfaceLight); color: #fff; }
        .btn-print:hover { background: #16213e; }
        .btn-back { background: var(--surfaceLight); color: #d1d5db; }
        .btn-back:hover { background: #e5e7eb; }

        @media print {
            body { background: #fff; }
            .receipt-container { box-shadow: none; margin: 0; max-width: none; border-radius: 0; }
            .actions { display: none !important; }
            .receipt-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .status-badge, .status-verified, .status-partial, .status-pending, .status-rejected {
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="actions">
    <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <a class="btn-back" href="javascript:history.back()">← Back</a>
</div>

<div class="receipt-container">

    <!-- Header -->
    <div class="receipt-header">
        <div class="subtitle">Payment Receipt</div>
        <h1><?= htmlspecialchars($societyName) ?></h1>
        <?php if ($societyAddr): ?>
        <div style="font-size:11px;color:#94a3b8;margin-top:4px;"><?= htmlspecialchars($societyAddr) ?></div>
        <?php endif; ?>
        <div class="receipt-num">#<?= htmlspecialchars($data['receipt_number']) ?></div>
        <div>
            <?php
            $badgeClass = 'status-pending';
            if ($isVerified && !$isPartial && $overpaid <= 0) $badgeClass = 'status-verified';
            elseif ($isVerified && $overpaid > 0) $badgeClass = 'status-overpaid';
            elseif ($isVerified && $isPartial) $badgeClass = 'status-partial';
            elseif (strtoupper($data['review_status']) === 'REJECTED') $badgeClass = 'status-rejected';
            ?>
            <span class="status-badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
        </div>
    </div>

    <!-- Body -->
    <div class="receipt-body">

        <!-- Member & Billing Info -->
        <div class="info-grid">
            <div class="info-block">
                <label>Member Name</label>
                <div class="value"><?= htmlspecialchars($data['full_name']) ?></div>
            </div>
            <div class="info-block">
                <label>Flat / Unit</label>
                <div class="value"><?= htmlspecialchars($data['flat_number']) ?></div>
            </div>
            <div class="info-block">
                <label>Member Code</label>
                <div class="value mono"><?= htmlspecialchars($data['member_code']) ?></div>
            </div>
            <div class="info-block">
                <label>Billing Month</label>
                <div class="value"><?= $billingMonth ?></div>
            </div>
            <div class="info-block">
                <label>Payment Date</label>
                <div class="value"><?= $paymentDate ?></div>
            </div>
            <div class="info-block">
                <label>Payment Mode</label>
                <div class="value"><?= htmlspecialchars($data['payment_mode']) ?></div>
            </div>
            <?php if ($data['reference_no']): ?>
            <div class="info-block">
                <label>Reference No.</label>
                <div class="value mono"><?= htmlspecialchars($data['reference_no']) ?></div>
            </div>
            <?php endif; ?>
            <div class="info-block">
                <label>Due Date</label>
                <div class="value"><?= $dueDate ?></div>
            </div>
        </div>

        <!-- Payment Breakdown -->
        <table class="breakdown">
            <thead>
                <tr><th>Description</th><th>Amount (₹)</th></tr>
            </thead>
            <tbody>
                <?php if ((float)$data['previous_due'] > 0): ?>
                <tr>
                    <td>Previous Balance (Carry Forward)</td>
                    <td>₹<?= $previousDue ?></td>
                </tr>
                <?php elseif ((float)$data['previous_due'] < 0): ?>
                <tr style="color: #2563eb;">
                    <td>Overpayment Credit (from previous month)</td>
                    <td>−₹<?= number_format(abs((float)$data['previous_due']), 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Current Month Maintenance</td>
                    <td>₹<?= $currentDue ?></td>
                </tr>
                <tr class="total-row">
                    <td>Total Bill Amount</td>
                    <td>₹<?= number_format($baseAmount, 2) ?></td>
                </tr>
                <?php if ($lateFeeAtPayment > 0): ?>
                <tr style="color: #dc2626;">
                    <td>Late Fee (as of <?= $paymentDate ?>)</td>
                    <td>₹<?= number_format($lateFeeAtPayment, 2) ?></td>
                </tr>
                <tr class="total-row">
                    <td>Total Payable (incl. Late Fee)</td>
                    <td>₹<?= number_format($totalWithFee, 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($previouslyPaid > 0): ?>
                <tr class="paid-row">
                    <td>Previously Paid (Earlier Receipts)</td>
                    <td>−₹<?= number_format($previouslyPaid, 2) ?></td>
                </tr>
                <tr style="font-weight:600;color:#1d4ed8;">
                    <td>Balance Due (at time of this receipt)</td>
                    <td>₹<?= number_format($balanceDueAtTime, 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="paid-row">
                    <td>This Receipt — Amount Paid</td>
                    <td>₹<?= $amountPaid ?></td>
                </tr>
                <?php if ($cumulPaid != $thisReceiptAmt && $isVerified): ?>
                <tr class="paid-row">
                    <td>Total Paid (All Receipts)</td>
                    <td>₹<?= number_format($cumulPaid, 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($isPartial): ?>
                <tr class="remaining-row">
                    <td>Remaining Balance</td>
                    <td>₹<?= number_format($remaining, 2) ?></td>
                </tr>
                <?php elseif ($overpaid > 0): ?>
                <tr style="color: #2563eb; font-weight: 600;">
                    <td>Overpayment Credit (applied to next month)</td>
                    <td>−₹<?= number_format($overpaid, 2) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($data['review_note']): ?>
        <div style="margin-top: 16px; padding: 12px 16px; background: #fef2f2; border-radius: 8px; border-left: 4px solid #ef4444;">
            <div style="font-size: 10px; text-transform: uppercase; color: #991b1b; font-weight: 600; margin-bottom: 4px;">Admin Note</div>
            <div style="font-size: 13px; color: #1d1d1f;"><?= htmlspecialchars($data['review_note']) ?></div>
        </div>
        <?php endif; ?>

    </div><!-- /receipt-body -->

    <?php if (!empty($noteText)): ?>
    <div style="margin:0 40px 20px;padding:14px 16px;background:#f0fdf4;border-radius:10px;border-left:4px solid #10b981;">
        <div style="font-size:10px;text-transform:uppercase;color:#065f46;font-weight:700;margin-bottom:4px;">Note</div>
        <div style="font-size:13px;color:#1d1d1f;"><?= htmlspecialchars($noteText) ?></div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="receipt-footer">
        <div>Generated on <?= date('d M Y, h:i A') ?> · Receipt ID: <?= $data['id'] ?></div>
        <div class="note">This is a computer-generated receipt. No signature is required.</div>
        <div style="margin-top: 6px;">EyeSense Cloud Portal · <?= htmlspecialchars($societyName) ?></div>
    </div>

</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 500));</script>
<?php endif; ?>

</body>
</html>
