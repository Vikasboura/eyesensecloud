<?php
/** EyeSense Cloud Portal — Maintenance Admin Hub */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_MAINTENANCE');
$cid = $session['client_id'];
?>

<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0"><h1 class="font-bold text-base md:text-lg text-textMain truncate">Maintenance Hub</h1><p class="text-xs text-textSec hidden sm:block">Track bills, receipts, and member payment status</p></div>
    <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">
        <input type="month" id="monthPicker" class="text-sm" value="<?= date('Y-m') ?>" onchange="loadData()">
        <a href="maintenance_settings.php" class="btn-ghost text-xs"><i class="fas fa-sliders mr-1"></i><span class="hidden sm:inline">Settings</span><i class="fas fa-sliders sm:hidden"></i></a>
    </div>
</div>

<div class="flex-1 overflow-y-auto p-6 space-y-6">

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" id="statsRow">
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Total Members</div><div class="text-3xl font-bold text-textMain" id="s-members">-</div></div>
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Verified</div><div class="text-3xl font-bold text-emerald-400" id="s-verified">-</div></div>
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Partial</div><div class="text-3xl font-bold text-amber-400" id="s-partial">-</div></div>
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Awaiting Review</div><div class="text-3xl font-bold text-yellow-400" id="s-uploaded">-</div></div>
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Pending</div><div class="text-3xl font-bold text-textSec" id="s-pending">-</div></div>
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Overdue</div><div class="text-3xl font-bold text-red-400" id="s-overdue">-</div></div>
    </div>

    <!-- Collection summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="glass-panel rounded-xl p-5">
            <div class="text-xs text-textSec uppercase tracking-wider mb-1">Total Collected (Verified)</div>
            <div class="text-3xl font-bold text-emerald-400" id="s-collected">₹0</div>
        </div>
        <div class="glass-panel rounded-xl p-5">
            <div class="text-xs text-textSec uppercase tracking-wider mb-1">Total Due</div>
            <div class="text-3xl font-bold text-primary" id="s-totaldue">₹0</div>
        </div>
    </div>

    <!-- Filter + Actions -->
    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 items-stretch sm:items-center">
        <input type="text" id="searchBills" placeholder="Search by name, flat..." class="flex-1 text-sm" oninput="filterBills()">
        <div class="flex gap-2">
            <select id="statusFilter" class="text-sm flex-1 sm:w-36" onchange="filterBills()">
                <option value="ALL">All Status</option>
                <option value="PENDING">Pending</option>
                <option value="UPLOADED">Awaiting Review</option>
                <option value="VERIFIED">Verified</option>
                <option value="PARTIAL">Partial</option>
                <option value="REJECTED">Rejected</option>
                <option value="OVERDUE">Overdue</option>
            </select>
            <button onclick="markOverdue()" class="btn-danger text-xs whitespace-nowrap"><i class="fas fa-clock mr-1"></i><span class="hidden sm:inline">Mark </span>Overdue</button>
        </div>
    </div>

    <!-- Bills Table -->
    <div class="glass-panel rounded-xl overflow-hidden">
        <div class="table-responsive">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-border bg-surfaceLight/50">
                        <th class="p-4 text-xs font-bold text-textSec uppercase">Member</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase">Flat</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase hidden md:table-cell">Due Date</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase">Total Due</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase">Status</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase hidden lg:table-cell">Receipt</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody id="billsBody">
                    <tr><td colspan="7" class="p-8 text-center text-textSec">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Receipt Preview Modal -->
<div id="receiptModal" class="fixed inset-0 z-[100] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
        <div class="p-5 border-b border-border flex justify-between items-center shrink-0 bg-surfaceLight">
            <h2 class="font-bold text-lg text-textMain" id="receiptTitle">Receipt Preview</h2>
            <button onclick="closeReceiptModal()" class="text-textSec hover:text-textMain transition bg-textMain hover:bg-gray-700 w-8 h-8 rounded-full flex items-center justify-center"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5 overflow-y-auto flex-1 bg-surface" id="receiptContent">
            <!-- Filled dynamically -->
        </div>
        <div class="p-4 border-t border-border shrink-0 bg-surfaceLight flex justify-end gap-3" id="receiptActions">
        </div>
    </div>
</div>

<!-- Rejection Note Modal -->
<div id="rejectModal" class="fixed inset-0 z-[110] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
        <div class="p-5 border-b border-border bg-surfaceLight">
            <h2 class="font-bold text-lg text-textMain"><i class="fas fa-times-circle text-red-400 mr-2"></i>Reject Receipt</h2>
        </div>
        <div class="p-5 bg-surface">
            <input type="hidden" id="reject_receipt_id">
            <label class="block text-xs uppercase text-textSec font-bold mb-1">Reason for rejection *</label>
            <textarea id="reject_note" class="w-full text-sm" rows="3" placeholder="e.g. Blurry image, amount mismatch, wrong month..."></textarea>
        </div>
        <div class="p-4 border-t border-border bg-surfaceLight flex justify-end gap-3">
            <button onclick="closeRejectModal()" class="btn-ghost">Cancel</button>
            <button onclick="confirmReject()" class="btn-danger"><i class="fas fa-times mr-1"></i>Reject</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>

<script>
let allBills = [];

async function loadData() {
    const month = document.getElementById('monthPicker').value + '-01';
    try {
        const [statsRes, billsRes] = await Promise.all([
            fetch(`../maintenance_review_api.php?action=get_stats&month=${month}`),
            fetch(`../maintenance_review_api.php?action=get_bills&month=${month}`)
        ]);
        const stats = await statsRes.json();
        const bills = await billsRes.json();

        // Stats
        document.getElementById('s-members').textContent = stats.total_members ?? 0;
        document.getElementById('s-verified').textContent = stats.verified ?? 0;
        document.getElementById('s-uploaded').textContent = stats.uploaded ?? 0;
        document.getElementById('s-partial').textContent = stats.partial ?? 0;
        document.getElementById('s-pending').textContent = stats.pending ?? 0;
        document.getElementById('s-overdue').textContent = stats.overdue ?? 0;
        document.getElementById('s-collected').textContent = '₹' + (stats.total_collected ?? 0).toLocaleString();
        document.getElementById('s-totaldue').textContent = '₹' + (stats.total_due ?? 0).toLocaleString();

        allBills = bills.bills || [];
        filterBills();
    } catch(e) { showAlert('Failed to load data: ' + e.message, 'error'); }
}

function filterBills() {
    const search = document.getElementById('searchBills').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    let filtered = allBills;
    if (status !== 'ALL') filtered = filtered.filter(b => b.status === status);
    if (search) filtered = filtered.filter(b =>
        (b.full_name||'').toLowerCase().includes(search) ||
        (b.flat_number||'').toLowerCase().includes(search) ||
        (b.member_code||'').toLowerCase().includes(search)
    );
    renderBills(filtered);
}

function renderBills(bills) {
    const tbody = document.getElementById('billsBody');
    if (!bills.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-textSec">No bills found for this period</td></tr>';
        return;
    }

    const statusBadge = s => {
        const map = {
            'PENDING': 'bg-gray-700 text-textSec',
            'UPLOADED': 'bg-yellow-900/30 text-yellow-400 border border-yellow-700/50',
            'VERIFIED': 'bg-emerald-900/30 text-emerald-400 border border-emerald-700/50',
            'PARTIAL': 'bg-amber-900/30 text-amber-400 border border-amber-700/50',
            'REJECTED': 'bg-red-900/30 text-red-400 border border-red-700/50',
            'OVERDUE': 'bg-red-900/30 text-red-400 border border-red-700/50',
        };
        return `<span class="badge ${map[s]||'bg-gray-700 text-textSec'} text-xs">${s}</span>`;
    };

    tbody.innerHTML = bills.map(b => {
        let receiptInfo = '—';
        if (b.receipt_id) {
            const paidInfo = b.total_paid_all > 0 && b.total_paid_all != b.amount_paid
                ? `<br><span class="text-xs text-emerald-500">Total paid: ₹${parseFloat(b.total_paid_all).toLocaleString()}</span>`
                : '';
            receiptInfo = `<span class="text-xs text-indigo-400 font-mono">${b.receipt_number}</span>
                <br><span class="text-xs text-textSec">₹${parseFloat(b.amount_paid).toLocaleString()} via ${b.payment_mode}</span>${paidInfo}`;
        }
        let actions = '';
        // Show verify/reject ONLY when this specific receipt is PENDING
        if (b.receipt_id && b.review_status === 'PENDING') {
            actions = `
                <button onclick="viewReceipt(${b.receipt_id}, '${(b.full_name||'').replace(/'/g,"\\'")}', '${b.flat_number}')" class="text-xs text-primary hover:underline mr-2"><i class="fas fa-eye"></i></button>
                <button onclick="verifyReceipt(${b.receipt_id})" class="text-xs text-emerald-400 hover:underline mr-2" title="Verify"><i class="fas fa-check-circle"></i></button>
                <button onclick="openRejectModal(${b.receipt_id})" class="text-xs text-red-400 hover:underline" title="Reject"><i class="fas fa-times-circle"></i></button>`;
        } else if (b.receipt_id && (b.status === 'VERIFIED' || b.status === 'PARTIAL')) {
            actions = `<a href="maintenance_receipt.php?receipt_id=${b.receipt_id}" target="_blank" class="text-xs text-emerald-400 hover:underline mr-2" title="Download Receipt"><i class="fas fa-file-invoice mr-1"></i>Receipt</a>
                <button onclick="viewReceipt(${b.receipt_id}, '${(b.full_name||'').replace(/'/g,"\\'")}', '${b.flat_number}')" class="text-xs text-primary hover:underline"><i class="fas fa-eye"></i></button>`;
        } else if (b.receipt_id) {
            actions = `<button onclick="viewReceipt(${b.receipt_id}, '${(b.full_name||'').replace(/'/g,"\\'")}', '${b.flat_number}')" class="text-xs text-primary hover:underline"><i class="fas fa-eye mr-1"></i>View</button>`;
        }
        const dueDate = b.due_date ? new Date(b.due_date).toLocaleDateString('en-IN', {day:'numeric',month:'short',year:'numeric'}) : '—';
        return `<tr class="border-b border-border/50 hover:bg-textMain/30">
            <td class="p-4"><div class="font-bold text-textMain text-sm">${b.full_name}</div><div class="text-xs text-textSec font-mono">${b.member_code}</div></td>
            <td class="p-4 text-sm text-textSec">${b.flat_number}</td>
            <td class="p-4 text-sm text-textSec hidden md:table-cell">${dueDate}</td>
            <td class="p-4 text-sm font-bold text-primary">₹${parseFloat(b.total_due).toLocaleString()}</td>
            <td class="p-4">${statusBadge(b.status)}</td>
            <td class="p-4 hidden lg:table-cell">${receiptInfo}</td>
            <td class="p-4">${actions}</td>
        </tr>`;
    }).join('');
}

function viewReceipt(receiptId, name, flat) {
    document.getElementById('receiptTitle').textContent = `Receipt — ${name} | Flat ${flat}`;
    const content = document.getElementById('receiptContent');
    content.innerHTML = `<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-primary"></i></div>`;

    // Show the receipt file in an embed or img
    const embedUrl = `maintenance_serve_file.php?receipt_id=${receiptId}`;
    // Try to detect file type from the bills data
    const bill = allBills.find(b => b.receipt_id == receiptId);
    const isImage = bill && bill.file_type === 'IMAGE';

    if (isImage) {
        content.innerHTML = `<img src="${embedUrl}" class="w-full rounded-xl" alt="Receipt" style="max-height:60vh;object-fit:contain;">`;
    } else {
        content.innerHTML = `<embed src="${embedUrl}" type="application/pdf" class="w-full rounded-xl" style="height:60vh;">
            <p class="text-center text-textSec text-xs mt-2">Can't view? <a href="${embedUrl}&download=1" class="text-primary hover:underline">Download PDF</a></p>`;
    }

    const actions = document.getElementById('receiptActions');
    actions.innerHTML = `
        <a href="${embedUrl}&download=1" class="btn-ghost text-xs"><i class="fas fa-download mr-1"></i>Download</a>
        <button onclick="closeReceiptModal()" class="btn-primary text-xs">Close</button>`;

    document.getElementById('receiptModal').classList.remove('hidden');
}

function closeReceiptModal() { document.getElementById('receiptModal').classList.add('hidden'); }

async function verifyReceipt(receiptId) {
    if (!await showConfirm('Verify this receipt? This marks the bill as paid.')) return;
    try {
        const res = await fetch('../maintenance_review_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action: 'verify', receipt_id: receiptId})
        });
        const r = await res.json();
        if (r.error) throw new Error(r.error);
        showAlert(r.message || 'Receipt verified', 'success');
        loadData();
    } catch(e) { showAlert(e.message, 'error'); }
}

function openRejectModal(receiptId) {
    document.getElementById('reject_receipt_id').value = receiptId;
    document.getElementById('reject_note').value = '';
    document.getElementById('rejectModal').classList.remove('hidden');
}
function closeRejectModal() { document.getElementById('rejectModal').classList.add('hidden'); }

async function confirmReject() {
    const receiptId = document.getElementById('reject_receipt_id').value;
    const note = document.getElementById('reject_note').value.trim();
    if (!note) return showAlert('Please provide a rejection reason', 'warning');
    try {
        const res = await fetch('../maintenance_review_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action: 'reject', receipt_id: receiptId, review_note: note})
        });
        const r = await res.json();
        if (r.error) throw new Error(r.error);
        showAlert('Receipt rejected', 'success');
        closeRejectModal();
        loadData();
    } catch(e) { showAlert(e.message, 'error'); }
}

async function markOverdue() {
    try {
        const res = await fetch('../maintenance_review_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action: 'mark_overdue'})
        });
        const r = await res.json();
        if (r.error) throw new Error(r.error);
        showAlert(`${r.marked} bill(s) marked as overdue`, r.marked > 0 ? 'warning' : 'info');
        loadData();
    } catch(e) { showAlert(e.message, 'error'); }
}

// Close modals on outside click
['receiptModal','rejectModal'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', e => { if (e.target.id === id) { document.getElementById(id).classList.add('hidden'); } });
});

loadData();
</script>
