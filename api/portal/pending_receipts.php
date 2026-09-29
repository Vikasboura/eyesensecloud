<?php
/** EyeSense Cloud Portal — Pending Receipts (Admin) */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_MAINTENANCE');
$cid = $session['client_id'];

// Fetch all PENDING receipts
$stmt = $pdo->prepare("
    SELECT r.*, b.billing_month, b.total_due, b.status AS bill_status,
           sm.full_name, sm.flat_number, sm.member_code
    FROM maintenance_receipts r
    JOIN maintenance_bills b ON b.id = r.bill_id AND b.client_id = r.client_id
    JOIN society_members sm ON sm.id = r.member_id AND sm.client_id = r.client_id
    WHERE r.client_id = ? AND r.review_status = 'PENDING'
    ORDER BY r.uploaded_at DESC
");
$stmt->execute([$cid]);
$pendingReceipts = $stmt->fetchAll();
$count = count($pendingReceipts);
?>

<div class="h-16 border-b border-border bg-surface flex items-center justify-between px-6 flex-shrink-0">
    <div>
        <h1 class="font-bold text-lg text-textMain">Awaiting Verification</h1>
        <p class="text-xs text-textSec"><?= $count ?> receipt(s) pending admin review</p>
    </div>
    <a href="maintenance.php" class="btn-ghost text-xs"><i class="fas fa-arrow-left mr-1"></i>Back to Hub</a>
</div>

<div class="flex-1 overflow-y-auto p-6">
    <?php if ($count === 0): ?>
    <div class="glass-panel rounded-xl p-12 text-center">
        <i class="fas fa-check-circle text-4xl text-emerald-500 mb-4"></i>
        <h3 class="text-lg font-bold text-textMain mb-2">All Caught Up!</h3>
        <p class="text-sm text-textSec">No receipts are awaiting verification right now.</p>
    </div>
    <?php else: ?>
    <div class="grid gap-4">
        <?php foreach ($pendingReceipts as $r): ?>
        <div class="glass-panel rounded-xl p-5 border border-yellow-900/30 hover:border-yellow-700/50 transition-all">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-yellow-500/20 text-yellow-400 flex items-center justify-center text-sm font-bold">
                        <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="font-bold text-textMain"><?= htmlspecialchars($r['full_name']) ?></div>
                        <div class="text-xs text-textSec">Flat <?= htmlspecialchars($r['flat_number']) ?> | <?= $r['member_code'] ?></div>
                    </div>
                </div>
                <div class="text-right">
                    <span class="badge bg-yellow-900/30 text-yellow-400 border border-yellow-700/50 text-xs">PENDING</span>
                    <div class="text-xs text-textSec mt-1"><?= date('d M Y H:i', strtotime($r['uploaded_at'])) ?></div>
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-sm mb-4">
                <div>
                    <div class="text-xs text-textSec">Month</div>
                    <div class="text-textMain font-medium"><?= date('M Y', strtotime($r['billing_month'])) ?></div>
                </div>
                <div>
                    <div class="text-xs text-textSec">Amount Paid</div>
                    <div class="text-emerald-400 font-bold"><?= number_format($r['amount_paid'], 2) ?></div>
                </div>
                <div>
                    <div class="text-xs text-textSec">Bill Total</div>
                    <div class="text-textMain"><?= number_format($r['total_due'], 2) ?></div>
                </div>
                <div>
                    <div class="text-xs text-textSec">Mode</div>
                    <div class="text-textMain"><?= htmlspecialchars($r['payment_mode']) ?></div>
                </div>
                <div>
                    <div class="text-xs text-textSec">Receipt #</div>
                    <div class="text-indigo-400 font-mono text-xs"><?= htmlspecialchars($r['receipt_number']) ?></div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="maintenance_serve_file.php?receipt_id=<?= $r['id'] ?>" target="_blank" class="btn-ghost text-xs">
                    <i class="fas fa-eye mr-1"></i>View Proof
                </a>
                <button onclick="verifyReceipt(<?= $r['id'] ?>)" class="btn-primary bg-emerald-600 hover:bg-emerald-500 text-xs">
                    <i class="fas fa-check mr-1"></i>Verify
                </button>
                <button onclick="rejectReceipt(<?= $r['id'] ?>)" class="btn-primary bg-red-600 hover:bg-red-500 text-xs">
                    <i class="fas fa-times mr-1"></i>Reject
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Reject Reason Modal -->
<div id="rejectModal" class="fixed inset-0 z-[100] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-md shadow-2xl">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surfaceLight">
            <h2 class="font-bold text-lg text-textMain">Reject Receipt</h2>
            <button onclick="document.getElementById('rejectModal').classList.add('hidden')" class="text-textSec hover:text-textMain w-8 h-8 rounded-full flex items-center justify-center bg-textMain hover:bg-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5 space-y-4 bg-surface">
            <input type="hidden" id="rejectReceiptId" value="">
            <div>
                <label class="block text-xs uppercase text-textSec font-bold mb-1">Rejection Reason *</label>
                <textarea id="rejectReason" rows="3" class="w-full text-sm" placeholder="Explain why the receipt is being rejected..." required></textarea>
            </div>
            <button onclick="submitReject()" class="btn-primary bg-red-600 hover:bg-red-500 w-full text-sm">
                <i class="fas fa-times mr-2"></i>Confirm Rejection
            </button>
        </div>
    </div>
</div>

<script>
function verifyReceipt(id) {
    if (!confirm('Verify this receipt? This will mark the payment as confirmed.')) return;
    fetch('../maintenance_review_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'verify', receipt_id: id })
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); })
    .catch(e => alert('Error: ' + e.message));
}
function rejectReceipt(id) {
    document.getElementById('rejectReceiptId').value = id;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').classList.remove('hidden');
}
function submitReject() {
    const id = document.getElementById('rejectReceiptId').value;
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { alert('Please enter a rejection reason'); return; }
    fetch('../maintenance_review_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'reject', receipt_id: parseInt(id), reason: reason })
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); })
    .catch(e => alert('Error: ' + e.message));
}
</script>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
