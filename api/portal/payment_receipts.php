<?php
/**
 * EyeSense Cloud Portal — Payment Receipts Table
 * Shows all receipts for the logged-in member in a searchable, sortable, printable DataTable.
 * Also accessible by admins to view receipts for all members.
 */
require_once __DIR__ . '/portal_header.php';

$cid = $session['client_id'];
$empId = $_SESSION['portal_employee_id'] ?? '';
$isAdmin = $isSA || !empty($permissions['MANAGE_MAINTENANCE']);

// Find the member linked to this employee (for regular members)
$member = null;
if (!$isAdmin) {
    $memberStmt = $pdo->prepare("SELECT * FROM society_members WHERE client_id = ? AND employee_id = ? AND status = 'active'");
    $memberStmt->execute([$cid, $empId]);
    $member = $memberStmt->fetch();
}

// Fetch receipts
if ($isAdmin) {
    // Admin sees ALL receipts
    $rStmt = $pdo->prepare("
        SELECT r.*, b.billing_month, b.total_due, b.amount_due, b.previous_due, b.due_date, b.fine_amount,
               b.status AS bill_status, sm.full_name, sm.flat_number, sm.member_code
        FROM maintenance_receipts r
        JOIN maintenance_bills b ON b.id = r.bill_id AND b.client_id = r.client_id
        JOIN society_members sm ON sm.id = r.member_id AND sm.client_id = r.client_id
        WHERE r.client_id = ? AND r.review_status != 'SUPERSEDED'
        ORDER BY r.uploaded_at DESC
    ");
    $rStmt->execute([$cid]);
} elseif ($member) {
    // Member sees only their receipts
    $rStmt = $pdo->prepare("
        SELECT r.*, b.billing_month, b.total_due, b.amount_due, b.previous_due, b.due_date, b.fine_amount,
               b.status AS bill_status, sm.full_name, sm.flat_number, sm.member_code
        FROM maintenance_receipts r
        JOIN maintenance_bills b ON b.id = r.bill_id AND b.client_id = r.client_id
        JOIN society_members sm ON sm.id = r.member_id AND sm.client_id = r.client_id
        WHERE r.client_id = ? AND r.member_id = ? AND r.review_status != 'SUPERSEDED'
        ORDER BY r.uploaded_at DESC
    ");
    $rStmt->execute([$cid, $member['id']]);
} else {
    $rStmt = null;
}

$receipts = $rStmt ? $rStmt->fetchAll() : [];

// Fetch society name + penalty settings
$setStmt = $pdo->prepare("SELECT society_name, penalty_amount, penalty_days FROM maintenance_settings WHERE client_id = ?");
$setStmt->execute([$cid]);
$settingsRow = $setStmt->fetch() ?: [];
$societyName     = $settingsRow['society_name'] ?? 'Society Maintenance';
$rcpPenaltyAmt   = (float)($settingsRow['penalty_amount'] ?? 0);
$rcpPenaltyInt   = max(1, (int)($settingsRow['penalty_days'] ?? 1));
?>

<!-- DataTables CSS + JS (dark theme) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<style>
    /* ── DataTables Dark Theme Override ── */
    .dataTables_wrapper { color: #d1d5db; }
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
        background: var(--surface) !important; border: 1px solid var(--border) !important;
        color: var(--textMain) !important; border-radius: 8px; padding: 6px 12px;
    }
    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label,
    .dataTables_wrapper .dataTables_info { color: #9ca3af; font-size: 13px; }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        background: var(--bg) !important; border: 1px solid var(--border) !important;
        color: #9ca3af !important; border-radius: 6px; margin: 0 2px; padding: 4px 10px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #6366f1 !important; color: white !important; border-color: #6366f1 !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #d1d5db !important; color: var(--textMain) !important;
    }
    table.dataTable thead th { background: var(--surfaceLight); color: var(--textSec); font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border) !important; }
    table.dataTable tbody td { border-bottom: 1px solid #f3f4f6 !important; }
    table.dataTable.no-footer { border-bottom: 1px solid var(--border) !important; }
    table.dataTable tbody tr:hover { background: rgba(255,255,255,0.02) !important; }
    table.dataTable thead .sorting:after,
    table.dataTable thead .sorting_asc:after,
    table.dataTable thead .sorting_desc:after { color: #6366f1 !important; }

    /* Print button style */
    .dt-buttons .dt-button {
        background: var(--bg) !important; color: #d1d5db !important;
        border: 1px solid var(--border) !important; border-radius: 8px !important;
        padding: 6px 16px !important; font-size: 12px !important; font-weight: 600;
        cursor: pointer; transition: all 0.2s;
    }
    .dt-buttons .dt-button:hover {
        background: #d1d5db !important; color: var(--textMain) !important;
    }

    /* Print styles */
    @media print {
        body, main { background: white !important; color: black !important; }
        #sidebar, .h-16, .dt-buttons, .dataTables_length, .dataTables_filter,
        .dataTables_info, .dataTables_paginate, #mobileBottomNav { display: none !important; }
        .glass-panel { background: white !important; border: 1px solid #e5e7eb !important; box-shadow: none !important; backdrop-filter: none !important; }
        table.dataTable thead th { background: var(--surfaceLight) !important; color: #d1d5db !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table.dataTable tbody td { color: #e5e7eb !important; border-bottom: 1px solid var(--border) !important; }
        .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .print-header { display: block !important; }
        main { overflow: visible !important; }
        .flex-1.overflow-y-auto { overflow: visible !important; }
    }

    /* Print header (hidden on screen) */
    .print-header { display: none; padding: 20px 0; text-align: center; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px; }
    .print-header h2 { font-size: 20px; font-weight: 700; }
    .print-header p { font-size: 12px; color: var(--textSec); }
</style>

<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0">
        <h1 class="font-bold text-base md:text-lg text-textMain truncate">Payment Receipts</h1>
        <p class="text-xs text-textSec hidden sm:block"><?= $isAdmin ? 'All member receipts' : 'Your payment history' ?></p>
    </div>
    <div class="flex gap-2 flex-shrink-0">
        <?php if (!$isAdmin): ?>
        <a href="my_dues.php" class="btn-ghost text-xs"><i class="fas fa-arrow-left mr-1"></i><span class="hidden sm:inline">Back to </span>Dues</a>
        <?php endif; ?>
    </div>
</div>

<div class="flex-1 overflow-y-auto p-6">

    <!-- Print header (only visible when printing) -->
    <div class="print-header">
        <h2><?= htmlspecialchars($societyName) ?> — Payment Receipts</h2>
        <p>Generated on <?= date('d M Y, h:i A') ?><?= !$isAdmin && $member ? ' | ' . htmlspecialchars($member['full_name']) . ' | Flat ' . htmlspecialchars($member['flat_number']) : '' ?></p>
    </div>

    <?php if (!$member && !$isAdmin): ?>
    <div class="glass-panel rounded-xl p-10 text-center border border-yellow-900/40 bg-yellow-900/5">
        <i class="fas fa-user-slash text-5xl text-yellow-400/50 mb-4 block"></i>
        <h3 class="text-lg font-bold text-gray-700 mb-2">Not Registered</h3>
        <p class="text-sm text-textSec">Your account is not linked to a society member profile. Please contact your administrator.</p>
    </div>
    <?php else: ?>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <?php
        $totalReceipts = count($receipts);
        $verifiedReceipts = array_filter($receipts, fn($r) => $r['review_status'] === 'VERIFIED');
        $totalVerifiedAmt = array_sum(array_column($verifiedReceipts, 'amount_paid'));
        $pendingReceipts = array_filter($receipts, fn($r) => $r['review_status'] === 'PENDING');
        $rejectedReceipts = array_filter($receipts, fn($r) => $r['review_status'] === 'REJECTED');
        ?>
        <div class="stat-card">
            <div class="text-textSec text-xs uppercase tracking-wider mb-2">Total Receipts</div>
            <div class="text-3xl font-bold text-textMain"><?= $totalReceipts ?></div>
        </div>
        <div class="stat-card">
            <div class="text-textSec text-xs uppercase tracking-wider mb-2">Verified</div>
            <div class="text-3xl font-bold text-emerald-400"><?= count($verifiedReceipts) ?></div>
        </div>
        <div class="stat-card">
            <div class="text-textSec text-xs uppercase tracking-wider mb-2">Total Paid (Verified)</div>
            <div class="text-3xl font-bold text-emerald-400">₹<?= number_format($totalVerifiedAmt, 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="text-textSec text-xs uppercase tracking-wider mb-2">Pending</div>
            <div class="text-3xl font-bold text-yellow-400"><?= count($pendingReceipts) ?></div>
        </div>
    </div>

    <!-- Receipts DataTable -->
    <div class="glass-panel rounded-xl overflow-hidden p-4">
        <div class="table-responsive">
        <table id="receiptsTable" class="w-full text-left" style="width:100%">
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <?php if ($isAdmin): ?><th>Member</th><th>Flat</th><?php endif; ?>
                    <th>Month</th>
                    <th>Bill Amount</th>
                    <th>Late Fee</th>
                    <th>Amount Paid</th>
                    <th>Payment Mode</th>
                    <th>Payment Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receipts as $r):
                    $statusClass = match($r['review_status']) {
                        'VERIFIED' => 'bg-emerald-900/30 text-emerald-400 border border-emerald-700/50',
                        'PENDING'  => 'bg-yellow-900/30 text-yellow-400 border border-yellow-700/50',
                        'REJECTED' => 'bg-red-900/30 text-red-400 border border-red-700/50',
                        default    => 'bg-gray-700 text-textSec',
                    };
                    $billMonth = date('M Y', strtotime($r['billing_month']));
                    $payDate = date('d M Y', strtotime($r['payment_date']));
                    // Base bill amount (without fine)
                    $rcpBaseAmt = (float)$r['amount_due'] + (float)$r['previous_due'];
                    // Calculate late fee at the time of this payment
                    $rcpLateFee = 0;
                    if ($rcpPenaltyAmt > 0 && !empty($r['due_date']) && strtotime($r['payment_date']) > strtotime($r['due_date'])) {
                        $daysLateAtPay = max(0, (int)((strtotime($r['payment_date']) - strtotime($r['due_date'])) / 86400));
                        if ($daysLateAtPay >= 1) {
                            $intervalsAtPay = 1 + (int)floor($daysLateAtPay / $rcpPenaltyInt);
                            $rcpLateFee = round($rcpPenaltyAmt * $intervalsAtPay, 2);
                        }
                    }
                ?>
                <tr>
                    <td class="font-mono text-indigo-400 text-xs"><?= htmlspecialchars($r['receipt_number']) ?></td>
                    <?php if ($isAdmin): ?>
                    <td class="text-sm text-textMain font-bold"><?= htmlspecialchars($r['full_name']) ?></td>
                    <td class="text-sm text-textSec"><?= htmlspecialchars($r['flat_number']) ?></td>
                    <?php endif; ?>
                    <td class="text-sm text-textMain font-bold"><?= $billMonth ?></td>
                    <td class="text-sm text-primary">₹<?= number_format($rcpBaseAmt, 2) ?></td>
                    <td class="text-sm <?= $rcpLateFee > 0 ? 'text-red-400' : 'text-textSec' ?>"><?= $rcpLateFee > 0 ? '₹'.number_format($rcpLateFee, 2) : '—' ?></td>
                    <td class="text-sm text-emerald-400 font-bold">₹<?= number_format($r['amount_paid'], 2) ?></td>
                    <td class="text-sm text-textSec"><?= htmlspecialchars($r['payment_mode']) ?></td>
                    <td class="text-sm text-textSec"><?= $payDate ?></td>
                    <td><span class="badge <?= $statusClass ?> text-xs"><?= $r['review_status'] ?></span></td>
                    <td>
                        <?php if ($r['review_status'] === 'VERIFIED'): ?>
                        <a href="maintenance_receipt.php?receipt_id=<?= $r['id'] ?>" target="_blank" class="text-xs text-emerald-400 hover:underline mr-2"><i class="fas fa-file-invoice mr-1"></i>Receipt</a>
                        <?php endif; ?>
                        <a href="maintenance_serve_file.php?receipt_id=<?= $r['id'] ?>" target="_blank" class="text-xs text-primary hover:underline"><i class="fas fa-eye mr-1"></i>View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>

<!-- jQuery + DataTables + Buttons -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script>
$(document).ready(function() {
    if (!$('#receiptsTable').length || !$('#receiptsTable tbody tr').length) return;

    $('#receiptsTable').DataTable({
        order: [],  // no default sort — server already sorted by uploaded_at DESC
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        dom: '<"flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4"<"flex items-center gap-2"lB><f>>rtip',
        buttons: [
            {
                extend: 'print',
                text: '<i class="fas fa-print mr-1"></i> Print',
                title: '<?= htmlspecialchars($societyName) ?> — Payment Receipts',
                messageTop: 'Generated on <?= date("d M Y, h:i A") ?><?= !$isAdmin && $member ? " | " . htmlspecialchars($member["full_name"]) . " | Flat " . htmlspecialchars($member["flat_number"]) : "" ?>',
                exportOptions: {
                    columns: ':not(:last-child)'  // exclude Actions column
                },
                customize: function(win) {
                    $(win.document.body).css({
                        'font-family': 'Inter, Outfit, sans-serif',
                        'font-size': '12px',
                        'color': '#e5e7eb'
                    });
                    $(win.document.body).find('table')
                        .css({'border-collapse': 'collapse', 'width': '100%'})
                        .find('th').css({
                            'background': '#f3f4f6', 'padding': '8px 12px',
                            'border-bottom': '2px solid #d1d5db', 'text-align': 'left',
                            'font-size': '10px', 'text-transform': 'uppercase',
                            'color': '#6b7280', 'letter-spacing': '0.05em'
                        }).end()
                        .find('td').css({
                            'padding': '8px 12px', 'border-bottom': '1px solid #e5e7eb'
                        });
                    // Style the title
                    $(win.document.body).find('h1').css({
                        'font-size': '18px', 'font-weight': '700', 'margin-bottom': '4px'
                    });
                    $(win.document.body).find('.dt-print-message').css({
                        'font-size': '11px', 'color': '#6b7280', 'margin-bottom': '16px'
                    });
                }
            },
            {
                extend: 'csvHtml5',
                text: '<i class="fas fa-file-csv mr-1"></i> CSV',
                title: 'Payment_Receipts_<?= date("Y-m-d") ?>',
                exportOptions: { columns: ':not(:last-child)' }
            },
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel mr-1"></i> Excel',
                title: 'Payment_Receipts_<?= date("Y-m-d") ?>',
                exportOptions: { columns: ':not(:last-child)' }
            }
        ],
        language: {
            search: '<i class="fas fa-search text-textSec mr-1"></i>',
            searchPlaceholder: 'Search receipts...',
            emptyTable: 'No payment receipts found',
            info: 'Showing _START_ to _END_ of _TOTAL_ receipts',
            lengthMenu: 'Show _MENU_ per page'
        }
    });
});
</script>
