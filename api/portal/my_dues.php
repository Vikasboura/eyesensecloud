<?php
/** EyeSense Cloud Portal — My Dues & Receipts (Member Self-Service) */
require_once __DIR__ . '/portal_header.php';

$cid = $session['client_id'];
$empId = $_SESSION['portal_employee_id'] ?? '';

// Find the member linked to this portal employee
$memberStmt = $pdo->prepare("SELECT * FROM society_members WHERE client_id = ? AND employee_id = ? AND status = 'active'");
$memberStmt->execute([$cid, $empId]);
$member = $memberStmt->fetch();

// Current month bill
$currentBill = null;
$currentReceipt = null;
$billHistory = [];

// Load penalty settings (used by both bill display and estimate)
// penalty_amount = Rs. charged per interval, penalty_days = every N days
$penSettStmt = $pdo->prepare("SELECT penalty_amount, penalty_days, payment_upi_id, payment_account_name, payment_bank_name, payment_account_number, payment_ifsc, payment_instructions FROM maintenance_settings WHERE client_id = ?");
$penSettStmt->execute([$cid]);
$penSett = $penSettStmt->fetch() ?: [];
$penaltyAmt = (float) ($penSett['penalty_amount'] ?? 0);
$penaltyInterval = max(1, (int) ($penSett['penalty_days'] ?? 1));

// Payment details (for QR code display to member — loaded server-side, no extra permission needed)
$paymentUpiId = $penSett['payment_upi_id'] ?? '';
$paymentAccountName = $penSett['payment_account_name'] ?? '';
$paymentInstructions = $penSett['payment_instructions'] ?? '';

if ($member) {
    $curMonth = date('Y-m-01');

    // Current month bill
    $bStmt = $pdo->prepare("SELECT * FROM maintenance_bills WHERE client_id = ? AND member_id = ? AND billing_month = ?");
    $bStmt->execute([$cid, $member['id'], $curMonth]);
    $currentBill = $bStmt->fetch();

    // Current receipt (if uploaded)
    if ($currentBill) {
        $rStmt = $pdo->prepare("SELECT * FROM maintenance_receipts WHERE bill_id = ? AND client_id = ? AND review_status IN ('PENDING','VERIFIED','REJECTED') ORDER BY uploaded_at DESC LIMIT 1");
        $rStmt->execute([$currentBill['id'], $cid]);
        $currentReceipt = $rStmt->fetch();
    }

    // Bill history (last 12 months) — one row per bill, latest receipt only
    $hStmt = $pdo->prepare("
        SELECT b.*, r.receipt_number, r.amount_paid, r.payment_mode, r.payment_date, r.review_status, r.id AS receipt_id, r.review_note,
               (SELECT COALESCE(SUM(r2.amount_paid), 0) FROM maintenance_receipts r2
                WHERE r2.bill_id = b.id AND r2.client_id = b.client_id AND r2.review_status = 'VERIFIED'
               ) AS total_paid_all
        FROM maintenance_bills b
        LEFT JOIN maintenance_receipts r ON r.bill_id = b.id AND r.client_id = b.client_id
            AND r.id = (
                SELECT r3.id FROM maintenance_receipts r3
                WHERE r3.bill_id = b.id AND r3.client_id = b.client_id
                  AND r3.review_status IN ('PENDING','VERIFIED','REJECTED')
                ORDER BY FIELD(r3.review_status, 'PENDING', 'REJECTED', 'VERIFIED'), r3.uploaded_at DESC
                LIMIT 1
            )
        WHERE b.client_id = ? AND b.member_id = ?
        ORDER BY b.billing_month DESC LIMIT 12
    ");
    $hStmt->execute([$cid, $member['id']]);
    $billHistory = $hStmt->fetchAll();

    // ── Yearly summary: all bills grouped by calendar year ──────────────
    $yrStmt = $pdo->prepare("
        SELECT
            YEAR(b.billing_month) AS yr,
            COUNT(b.id)           AS total_months,
            SUM(b.amount_due + COALESCE(b.previous_due,0)) AS total_billed,
            SUM(COALESCE(b.fine_amount,0))                 AS total_fine,
            SUM(b.total_due)                               AS total_due_sum,
            SUM(
                COALESCE((
                    SELECT SUM(r2.amount_paid)
                    FROM maintenance_receipts r2
                    WHERE r2.bill_id = b.id AND r2.client_id = b.client_id
                      AND r2.review_status = 'VERIFIED'
                ), 0)
            ) AS total_paid,
            SUM(CASE WHEN b.status IN ('VERIFIED','PARTIAL') THEN 1 ELSE 0 END) AS paid_months,
            SUM(CASE WHEN b.status = 'OVERDUE' THEN 1 ELSE 0 END) AS overdue_months
        FROM maintenance_bills b
        WHERE b.client_id = ? AND b.member_id = ?
        GROUP BY YEAR(b.billing_month)
        ORDER BY YEAR(b.billing_month) DESC
    ");
    $yrStmt->execute([$cid, $member['id']]);
    $yearlyData = $yrStmt->fetchAll();

    // Also fetch per-month rows for each year (for expandable detail)
    $allBillsStmt = $pdo->prepare("
        SELECT b.billing_month, b.amount_due, b.previous_due, b.fine_amount, b.total_due, b.status, b.due_date,
               COALESCE((
                   SELECT SUM(r2.amount_paid)
                   FROM maintenance_receipts r2
                   WHERE r2.bill_id = b.id AND r2.client_id = b.client_id
                     AND r2.review_status = 'VERIFIED'
               ), 0) AS total_paid_verified,
               r.id AS receipt_id, r.payment_date, r.payment_mode, r.review_status AS rcp_status
        FROM maintenance_bills b
        LEFT JOIN maintenance_receipts r ON r.bill_id = b.id AND r.client_id = b.client_id
            AND r.id = (
                SELECT r3.id FROM maintenance_receipts r3
                WHERE r3.bill_id = b.id AND r3.client_id = b.client_id
                  AND r3.review_status IN ('PENDING','VERIFIED','REJECTED')
                ORDER BY FIELD(r3.review_status,'PENDING','REJECTED','VERIFIED'), r3.uploaded_at DESC
                LIMIT 1
            )
        WHERE b.client_id = ? AND b.member_id = ?
        ORDER BY b.billing_month DESC
    ");
    $allBillsStmt->execute([$cid, $member['id']]);
    $allBills = $allBillsStmt->fetchAll();
    // Group by year
    $billsByYear = [];
    foreach ($allBills as $ab) {
        $yr = date('Y', strtotime($ab['billing_month']));
        $billsByYear[$yr][] = $ab;
    }

    // ── Build 12-month calendar data (oldest → newest) ────────────────
    $calendarMonths = [];
    for ($i = 11; $i >= 0; $i--) {
        $mk = date('Y-m-01', strtotime("-{$i} months"));
        $calendarMonths[$mk] = [
            'label' => date('M Y', strtotime($mk)),
            'month_s' => date('M', strtotime($mk)),
            'year' => date('Y', strtotime($mk)),
            'status' => 'NO_BILL',
            'dot' => '#d1d5db',
            'tile_bg' => 'background:rgba(31,41,55,0.4)',
            'tile_bd' => 'border-color:#d1d5db',
            'tile_tc' => 'color:var(--textSec)',
            'label_t' => 'No bill',
            'total_due' => 0,
            'total_paid' => 0,
            'fine' => 0,
            'due_date' => null,
            'pay_date' => null,
            'pay_mode' => null,
            'receipt_id' => null,
            'bill_status' => null,
        ];
    }
    foreach ($billHistory as $h) {
        $mk = $h['billing_month'];
        if (!isset($calendarMonths[$mk]))
            continue;
        $tp = (float) $h['total_paid_all'];
        $td = (float) $h['total_due'];
        $bs = $h['status'];
        $dd = $h['due_date'];
        $pd = $h['payment_date'] ?? null;
        if ($bs === 'VERIFIED') {
            if ($tp > $td + 0.01) {
                $st = 'OVERPAID';
                $dot = '#6366f1';
                $bg = 'rgba(67,56,202,0.25)';
                $bc = '#4338ca';
                $tc = '#a5b4fc';
                $lt = 'Overpaid';
            } elseif ($pd && strtotime($pd) <= strtotime($dd)) {
                $st = 'ON_TIME';
                $dot = '#059669';
                $bg = 'rgba(6,95,70,0.3)';
                $bc = '#065f46';
                $tc = '#6ee7b7';
                $lt = 'Paid on time';
            } else {
                $st = 'LATE';
                $dot = '#d97706';
                $bg = 'rgba(120,53,15,0.3)';
                $bc = '#92400e';
                $tc = '#fcd34d';
                $lt = 'Paid late';
            }
        } elseif ($bs === 'PARTIAL') {
            $st = 'PARTIAL';
            $dot = '#ea580c';
            $bg = 'rgba(124,45,18,0.3)';
            $bc = '#9a3412';
            $tc = '#fdba74';
            $lt = 'Partial';
        } elseif ($bs === 'OVERDUE') {
            $st = 'OVERDUE';
            $dot = '#dc2626';
            $bg = 'rgba(127,29,29,0.35)';
            $bc = '#991b1b';
            $tc = '#fca5a5';
            $lt = 'Overdue';
        } elseif ($bs === 'UPLOADED') {
            $st = 'REVIEW';
            $dot = '#ca8a04';
            $bg = 'rgba(120,53,15,0.25)';
            $bc = '#854d0e';
            $tc = '#fde68a';
            $lt = 'Awaiting review';
        } elseif ($bs === 'REJECTED') {
            $st = 'REJECTED';
            $dot = '#e11d48';
            $bg = 'rgba(136,19,55,0.3)';
            $bc = '#881337';
            $tc = '#fda4af';
            $lt = 'Rejected';
        } else {
            $st = 'PENDING';
            $dot = '#9ca3af';
            $bg = 'rgba(55,65,81,0.3)';
            $bc = '#6b7280';
            $tc = '#9ca3af';
            $lt = 'Pending';
        }
        $calendarMonths[$mk] = array_merge($calendarMonths[$mk], [
            'status' => $st,
            'dot' => $dot,
            'tile_bg' => "background:{$bg}",
            'tile_bd' => "border-color:{$bc}",
            'tile_tc' => "color:{$tc}",
            'label_t' => $lt,
            'total_due' => $td,
            'total_paid' => $tp,
            'fine' => (float) ($h['fine_amount'] ?? 0),
            'due_date' => $dd,
            'pay_date' => $pd,
            'pay_mode' => $h['payment_mode'] ?? null,
            'receipt_id' => $h['receipt_id'] ?? null,
            'bill_status' => $bs,
        ]);
    }
}
?>

<div
    class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0">
        <h1 class="font-bold text-base md:text-lg text-textMain truncate">My Dues &amp; Receipts</h1>
        <p class="text-xs text-textSec hidden sm:block">View your maintenance dues and upload payment receipts</p>
    </div>
    <a href="payment_receipts.php" class="btn-ghost text-xs flex-shrink-0"><i class="fas fa-table-list mr-1"></i><span
            class="hidden sm:inline">View All </span>Receipts</a>
</div>

<div class="flex-1 overflow-y-auto p-6 space-y-6">

    <?php if (!$member): ?>
        <!-- Not registered -->
        <div class="glass-panel rounded-xl p-8 text-center border border-yellow-900/50 bg-yellow-900/5">
            <i class="fas fa-user-slash text-5xl text-yellow-400/50 mb-4"></i>
            <h3 class="text-lg font-bold text-gray-700 mb-2">Not Registered</h3>
            <p class="text-sm text-textSec max-w-md mx-auto">Your portal account is not linked to a society member record.
                Please contact your building admin to register you.</p>
        </div>
    <?php else: ?>

        <!-- Member Header -->
        <div class="glass-panel rounded-xl p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl font-bold">
                    <?= strtoupper(substr($member['full_name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="font-bold text-textMain text-lg"><?= htmlspecialchars($member['full_name']) ?></div>
                    <div class="flex items-center gap-3 text-sm text-emerald-400">
                        <span><i class="fas fa-building mr-1"></i>Flat
                            <?= htmlspecialchars($member['flat_number']) ?></span>
                        <span class="text-emerald-400">|</span>
                        <span class="font-mono text-xs text-emerald-400"><?= $member['member_code'] ?></span>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs text-beige uppercase tracking-wider">Monthly Dues</div>
                <div class="text-2xl font-bold text-emerald-400">₹<?= number_format($member['monthly_amount'], 2) ?></div>
            </div>
        </div>

        <!-- Current Month Bill -->
        <?php if ($currentBill): ?>
            <div class="glass-panel rounded-xl overflow-hidden">
                <div class="p-5 border-b border-border flex items-center justify-between">
                    <h3 class="text-sm font-bold text-textSec uppercase tracking-wider">
                        <i class="fas fa-calendar-day text-primary mr-2"></i><?= date('F Y') ?> — Current Month
                    </h3>
                    <?php
                    $statusColors = [
                        'PENDING' => 'bg-gray-700 text-textSec',
                        'UPLOADED' => 'bg-yellow-900/30 text-yellow-400 border border-yellow-700/50',
                        'VERIFIED' => 'bg-emerald-900/30 text-emerald-400 border border-emerald-700/50',
                        'PARTIAL' => 'bg-amber-900/30 text-amber-400 border border-amber-700/50',
                        'REJECTED' => 'bg-red-900/30 text-red-400 border border-red-700/50',
                        'OVERDUE' => 'bg-red-900/30 text-red-400 border border-red-700/50 animate-pulse',
                    ];
                    $sc = $statusColors[$currentBill['status']] ?? 'bg-gray-700 text-textSec';
                    ?>
                    <span class="badge <?= $sc ?>"><?= $currentBill['status'] ?></span>
                </div>
                <div class="p-5">
                    <?php
                    $billFine = (float) ($currentBill['fine_amount'] ?? 0);
                    // Compute paid so far for this bill (all verified receipts)
                    $paidStmtBill = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM maintenance_receipts WHERE bill_id=? AND client_id=? AND review_status='VERIFIED'");
                    $paidStmtBill->execute([$currentBill['id'], $cid]);
                    $billPaidSoFar = (float) $paidStmtBill->fetchColumn();
                    $billRemaining = max(0, (float) $currentBill['total_due'] - $billPaidSoFar);
                    ?>

                    <?php if ($currentBill['status'] === 'OVERDUE'): ?>
                        <!-- OVERDUE: show breakdown with remaining balance -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
                            <div>
                                <div class="text-xs text-textSec mb-1">Original Bill</div>
                                <div class="text-lg font-bold text-textMain">
                                    ₹<?= number_format((float) $currentBill['amount_due'] + (float) $currentBill['previous_due'], 2) ?>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-textSec mb-1">Already Paid</div>
                                <div class="text-lg font-bold text-emerald-400">
                                    <?= $billPaidSoFar > 0 ? '₹' . number_format($billPaidSoFar, 2) : '₹0.00' ?>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-textSec mb-1">Remaining Principal</div>
                                <div class="text-lg font-bold text-red-400">
                                    ₹<?= number_format(max(0, (float) $currentBill['amount_due'] + (float) $currentBill['previous_due'] - $billPaidSoFar), 2) ?>
                                </div>
                            </div>
                            <?php if ($billFine > 0): ?>
                                <div>
                                    <div class="text-xs text-textSec mb-1">Late Fine</div>
                                    <div class="text-lg font-bold text-red-400">+₹<?= number_format($billFine, 2) ?></div>
                                    <div class="text-xs text-red-500/60 mt-0.5">Accruing daily</div>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="text-xs text-textSec mb-1">Pay Now</div>
                                <div class="text-2xl font-bold text-red-400 animate-pulse">₹<?= number_format($billRemaining, 2) ?>
                                </div>
                                <div class="text-xs text-textSec mt-0.5">Due:
                                    <?= date('d M Y', strtotime($currentBill['due_date'])) ?>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- PENDING / Other statuses: standard breakdown -->
                        <div class="grid grid-cols-2 md:grid-cols-<?= $billFine > 0 ? '5' : '4' ?> gap-4 mb-5">
                            <div>
                                <div class="text-xs text-beige mb-1">Current Month</div>
                                <div class="text-lg font-bold text-textMain">₹<?= number_format($currentBill['amount_due'], 2) ?></div>
                            </div>
                            <?php
                            $prevDue = (float) $currentBill['previous_due'];
                            if ($prevDue > 0): ?>
                                <div>
                                    <div class="text-xs text-textSec mb-1">Underpaid Balance</div>
                                    <div class="text-lg font-bold text-red-400">+₹<?= number_format($prevDue, 2) ?></div>
                                    <div class="text-xs text-textSec mt-0.5">Carried from last month</div>
                                </div>
                            <?php elseif ($prevDue < 0): ?>
                                <div>
                                    <div class="text-xs text-textSec mb-1">Overpaid Credit</div>
                                    <div class="text-lg font-bold text-emerald-400">−₹<?= number_format(abs($prevDue), 2) ?></div>
                                    <div class="text-xs text-textSec mt-0.5">Applied from last month</div>
                                </div>
                            <?php else: ?>
                                <div>
                                    <div class="text-xs text-beige mb-1">Previous Balance</div>
                                    <div class="text-lg font-bold text-textSec">₹0.00</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($billFine > 0): ?>
                                <div>
                                    <div class="text-xs text- mb-1">Late Fine</div>
                                    <div class="text-lg font-bold text-red-400">+₹<?= number_format($billFine, 2) ?></div>
                                    <div class="text-xs text-red-500/60 mt-0.5">Penalty for late payment</div>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="text-xs text-beige mb-1">Total Payable</div>
                                <div class="text-lg font-bold text-primary">₹<?= number_format($currentBill['total_due'], 2) ?>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-beige mb-1">Due Date</div>
                                <div
                                    class="text-lg font-bold <?= (strtotime($currentBill['due_date']) < time() && in_array($currentBill['status'], ['PENDING', 'PARTIAL'])) ? 'text-red-400' : 'text-textMain' ?>">
                                    <?= date('d M Y', strtotime($currentBill['due_date'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($currentBill['status'] === 'PENDING' || $currentBill['status'] === 'OVERDUE'): ?>
                        <!-- Upload button — pre-fills REMAINING amount, not total -->
                        <button onclick="openUploadModal(<?= $currentBill['id'] ?>, <?= $billRemaining ?>)"
                            class="btn-primary bg-emerald-600 hover:bg-emerald-500 w-full md:w-auto">
                            <i class="fas fa-cloud-arrow-up mr-2"></i>Upload Payment Receipt
                            (₹<?= number_format($billRemaining, 2) ?> remaining)
                        </button>
                        <?php if ($currentBill['status'] === 'OVERDUE'): ?>
                            <p class="text-xs text-red-400 mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>This bill is overdue.
                                Late fine of ₹<?= number_format($penaltyAmt, 2) ?> every <?= $penaltyInterval ?> day(s) is being
                                charged. Pay immediately to stop the fine from increasing.</p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($currentBill['status'] === 'UPLOADED' && $currentReceipt): ?>
                        <!-- Awaiting review -->
                        <div class="p-4 rounded-xl border border-yellow-900/40 bg-yellow-900/10">
                            <p class="text-sm text-yellow-300 font-bold mb-2"><i class="fas fa-hourglass-half mr-2"></i>Receipt
                                uploaded — awaiting admin verification</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div><span class="text-textSec">Amount:</span> <span
                                        class="text-textMain">₹<?= number_format($currentReceipt['amount_paid'], 2) ?></span></div>
                                <div><span class="text-textSec">Mode:</span> <span
                                        class="text-textMain"><?= $currentReceipt['payment_mode'] ?></span></div>
                                <div><span class="text-textSec">Date:</span> <span
                                        class="text-textMain"><?= date('d M Y', strtotime($currentReceipt['payment_date'])) ?></span>
                                </div>
                                <div><span class="text-textSec">Receipt #:</span> <span
                                        class="text-indigo-400 font-mono"><?= $currentReceipt['receipt_number'] ?></span></div>
                            </div>
                            <div class="flex items-center gap-3 mt-3">
                                <a href="maintenance_serve_file.php?receipt_id=<?= $currentReceipt['id'] ?>" target="_blank"
                                    class="text-xs text-primary hover:underline"><i class="fas fa-eye mr-1"></i>View Receipt</a>
                                <button onclick="openUploadModal(<?= $currentBill['id'] ?>, <?= $currentBill['total_due'] ?>)"
                                    class="text-xs text-yellow-400 hover:underline"><i
                                        class="fas fa-rotate mr-1"></i>Re-upload</button>
                            </div>
                        </div>

                    <?php elseif (($currentBill['status'] === 'VERIFIED' || $currentBill['status'] === 'PARTIAL') && $currentReceipt): ?>
                        <!-- Verified / Partial -->
                        <?php
                        $isPartialBill = $currentBill['status'] === 'PARTIAL';
                        $totalPaidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM maintenance_receipts WHERE bill_id = ? AND client_id = ? AND review_status = 'VERIFIED'");
                        $totalPaidStmt->execute([$currentBill['id'], $cid]);
                        $totalPaidAll = (float) $totalPaidStmt->fetchColumn();
                        $remainingBalance = max(0, (float) $currentBill['total_due'] - $totalPaidAll);
                        $overpaidAmount = max(0, $totalPaidAll - (float) $currentBill['total_due']);
                        ?>
                        <div
                            class="p-4 rounded-xl border <?= $isPartialBill ? 'border-amber-900/40 bg-amber-900/10' : 'border-emerald-900/40 bg-emerald-900/10' ?>">
                            <p class="text-sm <?= $isPartialBill ? 'text-amber-300' : 'text-emerald-300' ?> font-bold mb-2">
                                <i class="fas <?= $isPartialBill ? 'fa-exclamation-circle' : 'fa-check-circle' ?> mr-2"></i>
                                <?= $isPartialBill ? 'Partial payment verified — you can upload another receipt for the remaining balance' : 'Payment verified by admin' ?>
                            </p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div><span class="text-textSec">Total Paid:</span> <span
                                        class="text-textMain"><?= number_format($totalPaidAll, 2) ?></span></div>
                                <div><span class="text-textSec">Total Bill:</span> <span
                                        class="text-textMain"><?= number_format($currentBill['total_due'], 2) ?></span></div>
                                <div><span class="text-textSec">Last Payment:</span> <span
                                        class="text-textMain"><?= $currentReceipt['payment_mode'] ?></span></div>
                                <div><span class="text-textSec">Receipt #:</span> <span
                                        class="text-emerald-400 font-mono"><?= $currentReceipt['receipt_number'] ?></span></div>
                            </div>

                            <?php if ($overpaidAmount > 0 && !$isPartialBill): ?>
                                <!-- Overpaid Credit -->
                                <div class="mt-3 p-3 rounded-lg bg-emerald-900/20 border border-emerald-800/30 text-sm">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i class="fas fa-gift text-emerald-400"></i>
                                        <span class="text-emerald-400 font-bold">Overpaid Credit:
                                            <?= number_format($overpaidAmount, 2) ?></span>
                                    </div>
                                    <span class="text-textSec text-xs">This amount will be automatically credited to your next month's
                                        bill.</span>
                                </div>
                            <?php elseif ($isPartialBill): ?>
                                <!-- Remaining Due -->
                                <div
                                    class="mt-3 p-3 rounded-lg bg-amber-900/20 border border-amber-800/30 text-sm flex items-center justify-between">
                                    <div>
                                        <span class="text-amber-400 font-bold">Remaining Due:
                                            <?= number_format($remainingBalance, 2) ?></span>
                                        <span class="text-textSec ml-2">(upload another receipt to clear)</span>
                                    </div>
                                    <button onclick="openUploadModal(<?= $currentBill['id'] ?>, <?= $remainingBalance ?>)"
                                        class="btn-primary bg-amber-600 hover:bg-amber-500 text-xs ml-4">
                                        <i class="fas fa-cloud-arrow-up mr-1"></i>Upload Another Receipt
                                    </button>
                                </div>
                            <?php endif; ?>

                            <div class="flex gap-3 mt-3">
                                <a href="maintenance_receipt.php?receipt_id=<?= $currentReceipt['id'] ?>" target="_blank"
                                    class="btn-primary inline-block text-xs"><i class="fas fa-file-invoice mr-1"></i>View
                                    Receipt</a>
                                <a href="maintenance_receipt.php?receipt_id=<?= $currentReceipt['id'] ?>&auto_print=1"
                                    target="_blank" class="btn-ghost text-xs"><i class="fas fa-print mr-1"></i>Print / PDF</a>
                            </div>
                        </div>

                    <?php elseif ($currentBill['status'] === 'REJECTED' && $currentReceipt): ?>
                        <!-- Rejected -->
                        <div class="p-4 rounded-xl border border-red-900/40 bg-red-900/10">
                            <p class="text-sm text-red-400 font-bold mb-2"><i class="fas fa-times-circle mr-2"></i>Receipt rejected
                                by admin</p>
                            <?php if ($currentReceipt['review_note']): ?>
                                <p class="text-sm text-textSec mb-3 p-3 rounded-lg bg-gray-200/50 border border-red-200"><b
                                        class="text-red-300">Reason:</b> <?= htmlspecialchars($currentReceipt['review_note']) ?></p>
                            <?php endif; ?>
                            <button onclick="openUploadModal(<?= $currentBill['id'] ?>, <?= $currentBill['total_due'] ?>)"
                                class="btn-primary bg-yellow-600 hover:bg-yellow-500 text-xs">
                                <i class="fas fa-cloud-arrow-up mr-2"></i>Re-upload Receipt
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $nextMonthAmt = (float) $member['monthly_amount'];
            $carryForward = 0;
            $estFine = 0;
            $estFineLabel = '';

            if ($currentBill) {
                $totalDueVal = (float) $currentBill['total_due'];
                $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM maintenance_receipts WHERE bill_id=? AND client_id=? AND review_status='VERIFIED'");
                $paidStmt->execute([$currentBill['id'], $cid]);
                $paidSoFar = (float) $paidStmt->fetchColumn();

                if (in_array($currentBill['status'], ['VERIFIED'])) {
                    if ($paidSoFar > $totalDueVal) {
                        $carryForward = -1 * ($paidSoFar - $totalDueVal);
                    }
                } elseif (in_array($currentBill['status'], ['OVERDUE'])) {
                    // Carry forward = remaining principal (base bill minus paid)
                    $baseDue = (float) $currentBill['amount_due'] + (float) $currentBill['previous_due'];
                    $carryForward = max(0, $baseDue - $paidSoFar);
                    // Project fine to end of month using interval formula
                    if ($penaltyAmt > 0) {
                        $dueDate = $currentBill['due_date'];
                        $endOfMonth = date('Y-m-t'); // last day of current month
                        $totalDaysLate = max(0, (int) ((strtotime($endOfMonth) - strtotime($dueDate)) / 86400));
                        // Same formula as cron: 1 (immediate) + floor(days / interval)
                        $projectedIntervals = 1 + (int) floor($totalDaysLate / $penaltyInterval);
                        $estFine = round($penaltyAmt * $projectedIntervals, 2);
                        $estFineLabel = "₹{$penaltyAmt} × {$projectedIntervals} charges (every {$penaltyInterval} day" . ($penaltyInterval > 1 ? 's' : '') . ")";
                    }
                } elseif (in_array($currentBill['status'], ['PENDING', 'UPLOADED'])) {
                    $carryForward = max(0, $totalDueVal - $paidSoFar);
                }
            }
            $nextMonthTotal = max(0, $nextMonthAmt + $carryForward + $estFine);
            ?>
            <div class="glass-panel rounded-xl p-5">
                <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-4">
                    <i class="fas fa-calendar-plus text-indigo-400 mr-2"></i>Next Month Estimate
                    (<?= date('F Y', strtotime('+1 month')) ?>)
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-<?= $estFine > 0 ? '5' : '4' ?> gap-4">
                    <div>
                        <div class="text-xs text-textSec mb-1">Monthly Maintenance</div>
                        <div class="text-lg font-bold text-textMain"><?= number_format($nextMonthAmt, 2) ?></div>
                    </div>
                    <div>
                        <?php if ($carryForward > 0): ?>
                            <div class="text-xs text-textSec mb-1">Underpaid (Carry Forward)</div>
                            <div class="text-lg font-bold text-red-400">+<?= number_format($carryForward, 2) ?></div>
                        <?php elseif ($carryForward < 0): ?>
                            <div class="text-xs text-textSec mb-1">Overpaid Credit</div>
                            <div class="text-lg font-bold text-emerald-400">−<?= number_format(abs($carryForward), 2) ?></div>
                        <?php else: ?>
                            <div class="text-xs text-textSec mb-1">Carry Forward</div>
                            <div class="text-lg font-bold text-textSec">0.00</div>
                        <?php endif; ?>
                    </div>
                    <?php if ($estFine > 0): ?>
                        <div>
                            <div class="text-xs text-textSec mb-1">Late Fine (Projected)</div>
                            <div class="text-lg font-bold text-red-400">+<?= number_format($estFine, 2) ?></div>
                            <div class="text-xs text-red-500/60 mt-0.5"><?= $estFineLabel ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="<?= $estFine > 0 ? '' : 'md:col-span-2' ?>">
                        <div class="text-xs text-textSec mb-1">Estimated Total Payable</div>
                        <div class="text-2xl font-bold text-primary"><?= number_format($nextMonthTotal, 2) ?></div>
                    </div>
                </div>
                <p class="text-xs text-textSec mt-3">* This is an estimate. The actual bill will be generated on the 1st of
                    next
                    month.<?= $estFine > 0 ? " Late fine: ₹" . number_format($penaltyAmt, 2) . " every {$penaltyInterval} day(s) — increases until fully paid." : '' ?>
                </p>
            </div>

        <?php else: ?>
            <div class="glass-panel rounded-xl p-6 text-center">
                <i class="fas fa-file-invoice text-3xl text-textSec mb-3"></i>
                <p class="text-sm text-textSec">No bill generated for <?= date('F Y') ?> yet. Your admin will generate it soon.
                </p>
            </div>
        <?php endif; ?>


        <!-- ══ 12-Month Payment Calendar ══ -->
        <div class="glass-panel rounded-xl overflow-hidden">
            <div class="p-5 border-b border-border flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-textSec uppercase tracking-wider"><i
                        class="fas fa-calendar-alt text-indigo-400 mr-2"></i>Payment Calendar — Last 12 Months</h3>
                <!-- Legend -->
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    <?php foreach ([
                        ['#6ee7b7', 'On Time'],
                        ['#fcd34d', 'Late'],
                        ['#a5b4fc', 'Overpaid'],
                        ['#fca5a5', 'Overdue'],
                        ['#fdba74', 'Partial'],
                        ['#fde68a', 'Awaiting'],
                        ['#9ca3af', 'Pending'],
                        ['#6b7280', 'No Bill'],
                    ] as [$c, $l]): ?>
                        <span class="flex items-center gap-1.5 text-xs text-textSec">
                            <span class="inline-block w-2.5 h-2.5 rounded-full flex-shrink-0"
                                style="background:<?= $c ?>"></span><?= $l ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Tile Grid -->
            <div class="p-5 grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3" id="payCalendar">
                <?php foreach ($calendarMonths as $cm):
                    $isNoBill = $cm['status'] === 'NO_BILL';
                    ?>
                    <div class="cal-tile relative rounded-xl border p-3 cursor-default select-none transition-all duration-200"
                        style="<?= $cm['tile_bg'] ?>;<?= $cm['tile_bd'] ?>" onmouseenter="showCalTip(this)"
                        onmouseleave="hideCalTip()" data-month="<?= htmlspecialchars($cm['label']) ?>"
                        data-status="<?= $cm['label_t'] ?>"
                        data-due="<?= $isNoBill ? '' : '&#8377;' . number_format($cm['total_due'], 2) ?>"
                        data-paid="<?= ($cm['total_paid'] > 0) ? '&#8377;' . number_format($cm['total_paid'], 2) : '' ?>"
                        data-duedate="<?= $cm['due_date'] ? date('d M Y', strtotime($cm['due_date'])) : '' ?>"
                        data-paydate="<?= $cm['pay_date'] ? date('d M Y', strtotime($cm['pay_date'])) : '' ?>"
                        data-mode="<?= htmlspecialchars($cm['pay_mode'] ?? '') ?>"
                        data-fine="<?= ($cm['fine'] > 0) ? '&#8377;' . number_format($cm['fine'], 2) : '' ?>">
                        <!-- Month label -->
                        <div class="text-xs font-bold" style="<?= $cm['tile_tc'] ?>"><?= $cm['month_s'] ?></div>
                        <div class="text-[10px] text-textSec mb-2"><?= $cm['year'] ?></div>
                        <!-- Status dot -->
                        <div class="w-2.5 h-2.5 rounded-full mx-auto mb-1.5 <?= ($cm['status'] === 'OVERDUE') ? 'animate-pulse' : '' ?>"
                            style="background:<?= $cm['dot'] ?>"></div>
                        <!-- Status text (small) -->
                        <div class="text-[9px] text-center leading-tight truncate" style="<?= $cm['tile_tc'] ?>">
                            <?= $cm['label_t'] ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Floating tooltip (positioned by JS) -->
        <div id="calTip" style="display:none;position:fixed;z-index:9999;pointer-events:none;"
            class="bg-gray-900 border border-border rounded-xl shadow-2xl p-3 min-w-[190px] max-w-[220px] text-left">
            <div id="calTipMonth" class="text-xs font-bold text-textMain mb-2"></div>
            <div id="calTipBody" class="space-y-1"></div>
            <div class="mt-1.5 pt-1.5 border-t border-border">
                <span id="calTipStatus" class="text-[10px] font-bold uppercase tracking-wider"></span>
            </div>
        </div>

        <!-- ══ Yearly Maintenance Summary ══ -->
        <?php if (!empty($yearlyData)): ?>
            <div class="glass-panel rounded-xl overflow-hidden">
                <div class="p-5 border-b border-border flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-bold text-textSec uppercase tracking-wider">
                        <i class="fas fa-chart-bar text-violet-400 mr-2"></i>Yearly Maintenance Summary
                    </h3>
                    <span class="text-xs text-textSec"><?= count($yearlyData) ?> year<?= count($yearlyData) !== 1 ? 's' : '' ?>
                        of records</span>
                </div>

                <?php foreach ($yearlyData as $yrRow):
                    $yr = $yrRow['yr'];
                    $yBilled = (float) $yrRow['total_billed'];
                    $yFine = (float) $yrRow['total_fine'];
                    $yDue = (float) $yrRow['total_due_sum'];
                    $yPaid = (float) $yrRow['total_paid'];
                    $yBalance = max(0, $yDue - $yPaid);
                    $yMonths = (int) $yrRow['total_months'];
                    $yPaidM = (int) $yrRow['paid_months'];
                    $yOverdue = (int) $yrRow['overdue_months'];
                    $yFullPaid = ($yBalance < 0.01 && $yPaid > 0);
                    $yBorderCls = $yOverdue > 0 ? 'border-red-900/40' : ($yFullPaid ? 'border-emerald-900/40' : 'border-border/60');
                    $yHeaderBg = $yOverdue > 0 ? 'bg-red-900/10' : ($yFullPaid ? 'bg-emerald-900/10' : 'bg-surfaceLight');
                    ?>
                    <div class="border-b <?= $yBorderCls ?> last:border-b-0">
                        <!-- Year header (clickable to expand) -->
                        <button type="button"
                            class="w-full text-left px-5 py-4 flex flex-wrap items-center gap-4 hover:bg-textMain/30 transition-colors"
                            onclick="toggleYearPanel('yr-<?= $yr ?>')" id="yr-btn-<?= $yr ?>">
                            <!-- Year badge -->
                            <div class="flex items-center gap-3 min-w-[90px]">
                                <div
                                    class="w-9 h-9 rounded-xl <?= $yOverdue > 0 ? 'bg-red-500/20 text-red-400' : ($yFullPaid ? 'bg-emerald-500/20 text-emerald-400' : 'bg-violet-500/20 text-violet-400') ?> flex items-center justify-center font-bold text-xs">
                                    <?= $yr ?>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-textMain"><?= $yr ?></div>
                                    <div class="text-[10px] text-textSec"><?= $yMonths ?> month<?= $yMonths !== 1 ? 's' : '' ?>
                                        billed</div>
                                </div>
                            </div>

                            <!-- Stats row -->
                            <div class="flex flex-wrap gap-4 flex-1">
                                <div class="text-center min-w-[70px]">
                                    <div class="text-[10px] text-textSec uppercase tracking-wider">Total Billed</div>
                                    <div class="text-sm font-bold text-textMain">₹<?= number_format($yBilled, 2) ?></div>
                                </div>
                                <?php if ($yFine > 0): ?>
                                    <div class="text-center min-w-[70px]">
                                        <div class="text-[10px] text-textSec uppercase tracking-wider">Late Fines</div>
                                        <div class="text-sm font-bold text-red-400">₹<?= number_format($yFine, 2) ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="text-center min-w-[70px]">
                                    <div class="text-[10px] text-textSec uppercase tracking-wider">Total Paid</div>
                                    <div
                                        class="text-sm font-bold <?= $yPaid >= $yDue - 0.01 ? 'text-emerald-400' : 'text-amber-400' ?>">
                                        ₹<?= number_format($yPaid, 2) ?></div>
                                </div>
                                <div class="text-center min-w-[70px]">
                                    <div class="text-[10px] text-textSec uppercase tracking-wider">Outstanding</div>
                                    <div class="text-sm font-bold <?= $yBalance > 0 ? 'text-red-400' : 'text-emerald-400' ?>">
                                        <?= $yBalance > 0 ? '₹' . number_format($yBalance, 2) : '₹0.00 ✓' ?>
                                    </div>
                                </div>
                                <!-- Progress bar -->
                                <div class="flex-1 min-w-[100px] flex items-center gap-2">
                                    <?php $pct = $yDue > 0 ? min(100, round($yPaid / $yDue * 100)) : 0; ?>
                                    <div class="flex-1 bg-textMain rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full transition-all duration-700 <?= $pct >= 100 ? 'bg-emerald-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-red-500') ?>"
                                            style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <span class="text-[10px] text-textSec w-8 text-right"><?= $pct ?>%</span>
                                </div>
                            </div>

                            <!-- Status badges -->
                            <div class="flex items-center gap-2 ml-auto">
                                <?php if ($yOverdue > 0): ?>
                                    <span
                                        class="text-[10px] bg-red-900/30 text-red-400 border border-red-800/50 px-2 py-0.5 rounded-full font-bold">
                                        <?= $yOverdue ?> Overdue
                                    </span>
                                <?php endif; ?>
                                <span class="text-[10px] bg-textMain text-textSec border border-border px-2 py-0.5 rounded-full">
                                    <?= $yPaidM ?>/<?= $yMonths ?> paid
                                </span>
                                <i class="fas fa-chevron-down text-textSec text-xs transition-transform duration-200"
                                    id="yr-icon-<?= $yr ?>"></i>
                            </div>
                        </button>

                        <!-- Expandable month-by-month breakdown -->
                        <div id="yr-<?= $yr ?>" class="hidden overflow-hidden">
                            <div class="table-responsive">
                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="border-t border-border/60 bg-gray-900/40">
                                            <th class="px-5 py-2.5 text-[10px] font-bold text-textSec uppercase tracking-wider">
                                                Month</th>
                                            <th class="px-5 py-2.5 text-[10px] font-bold text-textSec uppercase tracking-wider">
                                                Bill</th>
                                            <th
                                                class="px-5 py-2.5 text-[10px] font-bold text-textSec uppercase tracking-wider hidden md:table-cell">
                                                Fine</th>
                                            <th class="px-5 py-2.5 text-[10px] font-bold text-textSec uppercase tracking-wider">
                                                Paid</th>
                                            <th
                                                class="px-5 py-2.5 text-[10px] font-bold text-textSec uppercase tracking-wider hidden sm:table-cell">
                                                Due Date</th>
                                            <th
                                                class="px-5 py-2.5 text-[10px] font-bold text-textSec uppercase tracking-wider hidden md:table-cell">
                                                Paid On</th>
                                            <th class="px-5 py-2.5 text-[10px] font-bold text-textSec uppercase tracking-wider">
                                                Status</th>
                                            <th class="px-5 py-2.5 text-[10px] font-bold text-textSec uppercase tracking-wider">
                                                Receipt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($billsByYear[$yr] ?? [] as $ab):
                                            $abBase = (float) $ab['amount_due'] + (float) ($ab['previous_due'] ?? 0);
                                            $abFine = (float) ($ab['fine_amount'] ?? 0);
                                            $abPaid = (float) $ab['total_paid_verified'];
                                            $abDue = (float) $ab['total_due'];
                                            $abDd = $ab['due_date'] ?? null;
                                            $abPd = $ab['payment_date'] ?? null;
                                            $abOnTime = $abPd && $abDd && strtotime($abPd) <= strtotime($abDd);
                                            $abSc = $statusColors[$ab['status']] ?? 'bg-gray-700 text-textSec';
                                            ?>
                                            <tr class="border-t border-border/30 hover:bg-textMain/20">
                                                <td class="px-5 py-3 text-sm text-textMain font-semibold">
                                                    <?= date('M Y', strtotime($ab['billing_month'])) ?>
                                                </td>
                                                <td class="px-5 py-3 text-sm text-indigo-300">₹<?= number_format($abBase, 2) ?></td>
                                                <td class="px-5 py-3 text-sm hidden md:table-cell">
                                                    <?php if ($abFine > 0): ?>
                                                        <span class="text-red-400">₹<?= number_format($abFine, 2) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-textSec">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-5 py-3 text-sm">
                                                    <?php if ($abPaid > 0): ?>
                                                        <?php $abOvp = $abPaid > $abDue + 0.01;
                                                        $cls3 = $abOvp ? 'text-violet-400' : ($abOnTime ? 'text-emerald-400' : 'text-amber-400'); ?>
                                                        <span class="<?= $cls3 ?>">₹<?= number_format($abPaid, 2) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-textSec">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-5 py-3 text-xs text-textSec hidden sm:table-cell">
                                                    <?= $abDd ? date('d M Y', strtotime($abDd)) : '—' ?>
                                                </td>
                                                <td class="px-5 py-3 text-xs hidden md:table-cell">
                                                    <?php if ($abPd): ?>
                                                        <span
                                                            class="<?= $abOnTime ? 'text-emerald-400' : 'text-amber-400' ?>"><?= date('d M Y', strtotime($abPd)) ?></span>
                                                    <?php else: ?><span class="text-textSec">—</span><?php endif; ?>
                                                </td>
                                                <td class="px-5 py-3">
                                                    <span class="badge <?= $abSc ?> text-[10px]"><?= $ab['status'] ?></span>
                                                </td>
                                                <td class="px-5 py-3">
                                                    <?php if ($ab['receipt_id'] && in_array($ab['status'], ['VERIFIED', 'PARTIAL'])): ?>
                                                        <a href="maintenance_receipt.php?receipt_id=<?= $ab['receipt_id'] ?>"
                                                            target="_blank" class="text-xs text-emerald-400 hover:underline"><i
                                                                class="fas fa-file-invoice mr-1"></i>Receipt</a>
                                                    <?php elseif ($ab['receipt_id']): ?>
                                                        <a href="maintenance_serve_file.php?receipt_id=<?= $ab['receipt_id'] ?>"
                                                            target="_blank" class="text-xs text-indigo-400 hover:underline"><i
                                                                class="fas fa-eye mr-1"></i>View</a>
                                                    <?php else: ?>
                                                        <span class="text-xs text-textSec">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <!-- Year totals row -->
                                        <tr class="border-t-2 border-border bg-gray-900/60">
                                            <td class="px-5 py-3 text-xs font-bold text-textSec uppercase"><?= $yr ?> Total</td>
                                            <td class="px-5 py-3 text-sm font-bold text-textMain">₹<?= number_format($yBilled, 2) ?>
                                            </td>
                                            <td class="px-5 py-3 text-sm font-bold hidden md:table-cell">
                                                <?= $yFine > 0 ? '<span class="text-red-400">₹' . number_format($yFine, 2) . '</span>' : '<span class="text-textSec">—</span>' ?>
                                            </td>
                                            <td
                                                class="px-5 py-3 text-sm font-bold <?= $yPaid >= $yDue - 0.01 ? 'text-emerald-400' : 'text-amber-400' ?>">
                                                ₹<?= number_format($yPaid, 2) ?></td>
                                            <td class="hidden sm:table-cell"></td>
                                            <td class="hidden md:table-cell"></td>
                                            <td class="px-5 py-3">
                                                <?php if ($yBalance > 0.01): ?>
                                                    <span class="text-xs text-red-400 font-bold">₹<?= number_format($yBalance, 2) ?>
                                                        due</span>
                                                <?php else: ?>
                                                    <span class="text-xs text-emerald-400 font-bold">Cleared</span>
                                                <?php endif; ?>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ══ Payment History Table ══ -->
        <div class="glass-panel rounded-xl overflow-hidden">
            <div class="p-5 border-b border-border">
                <h3 class="text-sm font-bold text-textSec uppercase tracking-wider"><i
                        class="fas fa-table-list text-textSec mr-2"></i>Payment History</h3>
            </div>
            <div class="table-responsive">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-border bg-surfaceLight/50">
                            <th class="p-4 text-xs font-bold text-textSec uppercase">Month</th>
                            <th class="p-4 text-xs font-bold text-textSec uppercase">Bill</th>
                            <th class="p-4 text-xs font-bold text-textSec uppercase hidden md:table-cell">Fine</th>
                            <th class="p-4 text-xs font-bold text-textSec uppercase">Paid</th>
                            <th class="p-4 text-xs font-bold text-textSec uppercase hidden sm:table-cell">Due Date</th>
                            <th class="p-4 text-xs font-bold text-textSec uppercase hidden md:table-cell">Paid On</th>
                            <th class="p-4 text-xs font-bold text-textSec uppercase hidden md:table-cell">Mode</th>
                            <th class="p-4 text-xs font-bold text-textSec uppercase">Status</th>
                            <th class="p-4 text-xs font-bold text-textSec uppercase">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($billHistory)): ?>
                            <tr>
                                <td colspan="9" class="p-8 text-center text-textSec">No billing history yet</td>
                            </tr>
                        <?php else:
                            foreach ($billHistory as $h):
                                $hsc = $statusColors[$h['status']] ?? 'bg-gray-700 text-textSec';
                                $hFine = (float) ($h['fine_amount'] ?? 0);
                                $hBase = (float) $h['amount_due'] + (float) $h['previous_due'];
                                $hPaid = (float) $h['total_paid_all'];
                                // Timing indicator
                                $hPd = $h['payment_date'] ?? null;
                                $hDd = $h['due_date'] ?? null;
                                $hOnTime = $hPd && $hDd && strtotime($hPd) <= strtotime($hDd);
                                ?>
                                <tr class="border-b border-border/50 hover:bg-textMain/30">
                                    <td class="p-4 text-sm text-textMain font-bold"><?= date('M Y', strtotime($h['billing_month'])) ?>
                                    </td>
                                    <td class="p-4 text-sm text-primary">₹<?= number_format($hBase, 2) ?></td>
                                    <td class="p-4 text-sm hidden md:table-cell"><?php
                                    if ($hFine > 0 && $h['status'] === 'OVERDUE')
                                        echo '<span class="text-red-400">₹' . number_format($hFine, 2) . '</span><span class="text-[10px] text-red-500/70 ml-1 animate-pulse">live</span>';
                                    elseif ($hFine > 0)
                                        echo '<span class="text-red-400">₹' . number_format($hFine, 2) . '</span>';
                                    else
                                        echo '<span class="text-textSec">—</span>';
                                    ?></td>
                                    <td class="p-4 text-sm"><?php
                                    if (in_array($h['status'], ['VERIFIED', 'PARTIAL']) && $hPaid > 0) {
                                        $overpaid = $hPaid > (float) $h['total_due'] + 0.01;
                                        $cls = $overpaid ? 'text-indigo-400' : ($hOnTime ? 'text-emerald-400' : 'text-amber-400');
                                        echo "<span class='{$cls}'>₹" . number_format($hPaid, 2) . "</span>";
                                    } elseif ($h['amount_paid']) {
                                        echo '<span class="text-textSec">₹' . number_format($h['amount_paid'], 2) . '</span>';
                                    } else
                                        echo '<span class="text-textSec">—</span>';
                                    ?></td>
                                    <td class="p-4 text-xs text-textSec hidden sm:table-cell">
                                        <?= $hDd ? date('d M Y', strtotime($hDd)) : '—' ?>
                                    </td>
                                    <td class="p-4 text-xs hidden md:table-cell"><?php
                                    if ($hPd) {
                                        $cls2 = $hOnTime ? 'text-emerald-400' : 'text-amber-400';
                                        echo "<span class='{$cls2}'>" . date('d M Y', strtotime($hPd)) . "</span>";
                                    } else
                                        echo '<span class="text-textSec">—</span>';
                                    ?></td>
                                    <td class="p-4 text-xs text-textSec hidden md:table-cell">
                                        <?= htmlspecialchars($h['payment_mode'] ?? '—') ?>
                                    </td>
                                    <td class="p-4"><span class="badge <?= $hsc ?> text-xs"><?= $h['status'] ?></span></td>
                                    <td class="p-4"><?php
                                    if ($h['receipt_id'] && in_array($h['status'], ['VERIFIED', 'PARTIAL']))
                                        echo '<a href="maintenance_receipt.php?receipt_id=' . $h['receipt_id'] . '" target="_blank" class="text-xs text-emerald-400 hover:underline"><i class="fas fa-file-invoice mr-1"></i>Receipt</a>';
                                    elseif ($h['receipt_id'])
                                        echo '<a href="maintenance_serve_file.php?receipt_id=' . $h['receipt_id'] . '" target="_blank" class="text-xs text-primary hover:underline"><i class="fas fa-eye mr-1"></i>View</a>';
                                    else
                                        echo '<span class="text-xs text-textSec">—</span>';
                                    ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Upload Modal -->
<div id="uploadModal"
    class="fixed inset-0 z-[100] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div
        class="bg-surface border border-border rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
        <div class="p-5 border-b border-border flex justify-between items-center shrink-0 bg-surfaceLight">
            <h2 class="font-bold text-lg text-textMain"><i class="fas fa-cloud-arrow-up text-emerald-400 mr-2"></i>Upload
                Payment Receipt</h2>
            <button onclick="closeUploadModal()"
                class="text-textSec hover:text-textMain transition bg-textMain hover:bg-gray-700 w-8 h-8 rounded-full flex items-center justify-center"><i
                    class="fas fa-times"></i></button>
        </div>
        <form id="uploadForm" enctype="multipart/form-data" class="p-5 overflow-y-auto flex-1 space-y-4 bg-surface">
            <input type="hidden" id="u_bill_id" value="">

            <div>
                <label class="block text-xs uppercase text-textSec font-bold mb-1">Payment Receipt File *</label>
                <div class="border-2 border-dashed border-border rounded-xl p-6 text-center hover:border-primary/50 transition cursor-pointer"
                    onclick="document.getElementById('u_file').click()">
                    <i class="fas fa-cloud-arrow-up text-3xl text-textSec mb-2" id="uploadIcon"></i>
                    <p class="text-sm text-textSec" id="fileLabel">Click to upload PDF, JPG, or PNG (max 5MB)</p>
                    <input type="file" id="u_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                        onchange="onFileSelect(this)">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Amount Paid ₹ *</label><input
                        type="number" id="u_amount" class="w-full text-sm" step="0.01" min="0" required></div>
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Payment Mode *</label>
                    <select id="u_mode" class="w-full text-sm" onchange="onPaymentModeChange()">
                        <option value="QR Code" selected>QR Code/UPI</option>
                        <option>Cash</option>
                        <option>Other</option>
                    </select>
                </div>
            </div>

            <!-- QR Code Payment Panel (hidden until QR Code mode selected) -->
            <div id="qrPaymentPanel" style="display:none;"
                class="p-4 rounded-xl border border-indigo-700/40 bg-indigo-900/10">
                <div class="flex flex-col items-center gap-3">
                    <div class="flex items-center gap-2 text-sm text-indigo-300 font-bold">
                        <i class="fas fa-qrcode"></i> Scan QR Code to Pay
                    </div>
                    <div id="memberQrCode" class="bg-white rounded-xl p-4 shadow-lg"
                        style="min-width:200px; min-height:200px; display:flex; align-items:center; justify-content:center;">
                        <div class="text-center" style="color:#9ca3af; font-size:12px;">
                            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i><br>Loading QR...
                        </div>
                    </div>
                    <div id="qrPaymentInfo" class="text-center text-xs text-textSec max-w-xs"></div>
                    <div class="p-3 rounded-lg bg-yellow-900/20 border border-yellow-800/30 w-full">
                        <p class="text-xs text-yellow-300"><i
                                class="fas fa-info-circle mr-1"></i><strong>Instructions:</strong></p>
                        <ol class="text-xs text-yellow-200/80 mt-1 ml-4 list-decimal space-y-0.5">
                            <li>Scan the QR code above with any UPI app</li>
                            <li>Enter the amount and complete payment</li>
                            <li>Take a screenshot of the payment confirmation</li>
                            <li>Upload the screenshot below as your receipt</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Payment Date *</label><input
                        type="date" id="u_date" class="w-full text-sm" value="<?= date('Y-m-d') ?>"></div>
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Reference / UTR
                        No.</label><input type="text" id="u_ref" class="w-full text-sm" placeholder="Optional"></div>
            </div>

            <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Notes</label><textarea id="u_notes"
                    class="w-full text-sm" rows="2" placeholder="Optional"></textarea></div>
        </form>
        <div class="p-4 border-t border-border shrink-0 bg-surfaceLight flex justify-end gap-3">
            <button onclick="closeUploadModal()" class="btn-ghost">Cancel</button>
            <button onclick="submitUpload()" class="btn-primary bg-emerald-600 hover:bg-emerald-500" id="uploadBtn"><i
                    class="fas fa-upload mr-2"></i>Upload Receipt</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
    // ── Yearly Summary Toggle ─────────────────────────────────────
    function toggleYearPanel(id) {
        const panel = document.getElementById(id);
        const yr = id.replace('yr-', '');
        const icon = document.getElementById('yr-icon-' + yr);
        if (!panel) return;
        const isOpen = !panel.classList.contains('hidden');
        if (isOpen) {
            panel.style.maxHeight = panel.scrollHeight + 'px';
            requestAnimationFrame(() => {
                panel.style.transition = 'max-height 0.3s ease, opacity 0.2s ease';
                panel.style.maxHeight = '0px';
                panel.style.opacity = '0';
            });
            setTimeout(() => {
                panel.classList.add('hidden');
                panel.style.maxHeight = '';
                panel.style.opacity = '';
                panel.style.transition = '';
            }, 300);
            if (icon) icon.style.transform = 'rotate(0deg)';
        } else {
            panel.classList.remove('hidden');
            panel.style.maxHeight = '0px';
            panel.style.opacity = '0';
            requestAnimationFrame(() => {
                panel.style.transition = 'max-height 0.35s ease, opacity 0.25s ease';
                panel.style.maxHeight = panel.scrollHeight + 'px';
                panel.style.opacity = '1';
            });
            setTimeout(() => {
                panel.style.maxHeight = '';
                panel.style.transition = '';
            }, 380);
            if (icon) icon.style.transform = 'rotate(180deg)';
        }
    }

    // ── Calendar Tooltip ──────────────────────────────────────────
    function showCalTip(tile) {
        const tip = document.getElementById('calTip');
        const month = tile.dataset.month || '';
        const stat = tile.dataset.status || 'No bill';
        const due = tile.dataset.due || '';
        const paid = tile.dataset.paid || '';
        const dd = tile.dataset.duedate || '';
        const pd = tile.dataset.paydate || '';
        const mode = tile.dataset.mode || '';
        const fine = tile.dataset.fine || '';

        document.getElementById('calTipMonth').textContent = month;
        document.getElementById('calTipStatus').textContent = stat;

        const rows = [];
        if (due) rows.push(['Total Due', due, '#a5b4fc']);
        if (paid) rows.push(['Paid', paid, '#6ee7b7']);
        if (dd) rows.push(['Due Date', dd, '#9ca3af']);
        if (pd) rows.push(['Paid On', pd, '#fcd34d']);
        if (mode) rows.push(['Mode', mode, '#9ca3af']);
        if (fine) rows.push(['Late Fine', fine, '#fca5a5']);
        if (!rows.length) rows.push(['', 'No bill generated', '#6b7280']);

        document.getElementById('calTipBody').innerHTML = rows.map(([l, v, c]) =>
            l ? `<div style="display:flex;justify-content:space-between;gap:12px;">
                 <span style="color:var(--textSec);font-size:11px;">${l}</span>
                 <span style="color:${c};font-size:11px;font-weight:600;font-family:monospace;">${v}</span>
             </div>`
                : `<span style="color:${c};font-size:11px;">${v}</span>`
        ).join('');

        tip.style.display = 'block';
        positionCalTip(tile);
    }
    function hideCalTip() {
        document.getElementById('calTip').style.display = 'none';
    }
    function positionCalTip(tile) {
        const tip = document.getElementById('calTip');
        const rect = tile.getBoundingClientRect();
        const tw = tip.offsetWidth || 210;
        const th = tip.offsetHeight || 140;
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        let left = rect.left + rect.width / 2 - tw / 2;
        let top = rect.top - th - 10;
        if (left < 8) left = 8;
        if (left + tw > vw) left = vw - tw - 8;
        if (top < 8) top = rect.bottom + 10;
        if (top + th > vh) top = vh - th - 8;
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
    }

    function openUploadModal(billId, totalDue) {
        document.getElementById('u_bill_id').value = billId;
        document.getElementById('u_amount').value = totalDue;
        document.getElementById('u_file').value = '';
        document.getElementById('fileLabel').textContent = 'Click to upload PDF, JPG, or PNG (max 5MB)';
        document.getElementById('uploadIcon').className = 'fas fa-cloud-arrow-up text-3xl text-textSec mb-2';
        document.getElementById('u_mode').value = 'QR Code';
        onPaymentModeChange();
        document.getElementById('uploadModal').classList.remove('hidden');
    }

    function closeUploadModal() {
        document.getElementById('uploadModal').classList.add('hidden');
    }

    function onFileSelect(input) {
        if (input.files.length) {
            const f = input.files[0];
            const sizeMB = (f.size / 1024 / 1024).toFixed(1);
            document.getElementById('fileLabel').textContent = `${f.name} (${sizeMB} MB)`;
            document.getElementById('uploadIcon').className = 'fas fa-file-check text-3xl text-emerald-400 mb-2';
        }
    }

    async function submitUpload() {
        const fileInput = document.getElementById('u_file');
        if (!fileInput.files.length) return showAlert('Please select a file', 'warning');

        const file = fileInput.files[0];
        if (file.size > 5 * 1024 * 1024) return showAlert('File size must be under 5MB', 'error');

        const btn = document.getElementById('uploadBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';

        const fd = new FormData();
        fd.append('receipt_file', file);
        fd.append('bill_id', document.getElementById('u_bill_id').value);
        fd.append('amount_paid', document.getElementById('u_amount').value);
        fd.append('payment_mode', document.getElementById('u_mode').value);
        fd.append('payment_date', document.getElementById('u_date').value);
        fd.append('reference_no', document.getElementById('u_ref').value);
        fd.append('notes', document.getElementById('u_notes').value);

        try {
            const res = await fetch('../maintenance_upload_api.php', { method: 'POST', body: fd });
            const r = await res.json();
            if (r.error) throw new Error(r.error);
            showAlert(`Receipt uploaded! (${r.receipt_number}). Admin has been notified.`, 'success');
            closeUploadModal();
            setTimeout(() => location.reload(), 1500);
        } catch (e) {
            showAlert(e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload Receipt';
        }
    }

    document.getElementById('uploadModal')?.addEventListener('click', e => { if (e.target.id === 'uploadModal') closeUploadModal(); });

    // QR Code Payment Mode Logic — data loaded server-side (no extra permission needed)
    const adminPaymentData = {
        payment_upi_id: <?= json_encode($paymentUpiId) ?>,
        payment_account_name: <?= json_encode($paymentAccountName) ?>,
        payment_instructions: <?= json_encode($paymentInstructions) ?>,
    };

    function onPaymentModeChange() {
        const mode = document.getElementById('u_mode').value;
        const panel = document.getElementById('qrPaymentPanel');
        if (mode !== 'QR Code') { panel.style.display = 'none'; return; }

        panel.style.display = 'block';
        const qrDiv = document.getElementById('memberQrCode');
        const infoDiv = document.getElementById('qrPaymentInfo');

        if (!adminPaymentData.payment_upi_id) {
            qrDiv.innerHTML = '<div class="text-center" style="color:#ef4444; font-size:12px; padding:16px;"><i class="fas fa-exclamation-triangle" style="font-size:28px; display:block; margin-bottom:8px;"></i>Admin has not configured<br>UPI payment details yet.<br><span style="margin-top:6px;display:block;">Please use another payment mode.</span></div>';
            infoDiv.innerHTML = '';
            return;
        }

        // Build standard UPI deep-link
        let upiUri = 'upi://pay?pa=' + encodeURIComponent(adminPaymentData.payment_upi_id);
        if (adminPaymentData.payment_account_name) upiUri += '&pn=' + encodeURIComponent(adminPaymentData.payment_account_name);
        upiUri += '&cu=INR';

        try {
            const qr = qrcode(0, 'M');
            qr.addData(upiUri);
            qr.make();
            qrDiv.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2 });
        } catch (e) {
            qrDiv.innerHTML = '<div style="color:red; font-size:11px; text-align:center; padding:8px;">QR generation failed<br>' + e.message + '</div>';
        }

        let info = '<strong class="text-indigo-300">Pay to:</strong> ' + (adminPaymentData.payment_account_name || 'Admin');
        info += '<br><span class="text-textSec">UPI:</span> <span class="text-textMain font-mono">' + adminPaymentData.payment_upi_id + '</span>';
        if (adminPaymentData.payment_instructions) {
            info += '<br><span class="text-yellow-400 mt-1 inline-block">' + adminPaymentData.payment_instructions + '</span>';
        }
        infoDiv.innerHTML = info;
    }

    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('upload')) {
            <?php if ($currentBill): ?>
                openUploadModal(<?= $currentBill['id'] ?>, <?= $billRemaining ?>);
            <?php endif; ?>
        }
    });
</script>
