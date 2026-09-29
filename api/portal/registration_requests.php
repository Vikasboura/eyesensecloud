<?php
/** EyeSense Cloud Portal — Registration Requests (Admin) */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_MAINTENANCE');
$cid = $session['client_id'];
?>

<div class="h-16 border-b border-border bg-surface flex items-center justify-between px-6 flex-shrink-0">
    <div>
        <h1 class="font-bold text-lg text-textMain">Registration Requests</h1>
        <p class="text-xs text-textSec">Review, edit if needed, then approve or reject member self-registration requests</p>
    </div>
    <select id="statusFilter" onchange="loadRequests()" class="text-xs">
        <option value="PENDING" selected>Pending</option>
        <option value="APPROVED">Approved</option>
        <option value="REJECTED">Rejected</option>
    </select>
</div>

<div class="flex-1 overflow-y-auto p-6">
    <div id="requestsContainer">
        <div class="glass-panel rounded-xl p-12 text-center">
            <i class="fas fa-spinner fa-spin text-2xl text-textSec"></i>
        </div>
    </div>
</div>

<!-- Review & Approve Modal -->
<div id="approveModal" class="fixed inset-0 z-[100] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surfaceLight shrink-0">
            <div>
                <h2 class="font-bold text-lg text-textMain">Review & Approve</h2>
                <p class="text-xs text-textSec mt-0.5">Edit any field before creating the account. Changes will be shown to the member.</p>
            </div>
            <button onclick="closeApproveModal()" class="text-textSec hover:text-textMain w-8 h-8 rounded-full flex items-center justify-center bg-textMain hover:bg-gray-700 shrink-0"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5 overflow-y-auto flex-1 space-y-4 bg-surface">
            <input type="hidden" id="approveReqId">

            <div class="p-3 rounded-lg bg-indigo-900/10 border border-indigo-800/30 text-xs text-indigo-300">
                <i class="fas fa-info-circle mr-1"></i>
                Fields pre-filled from member's submission. Edit anything that needs correction — the member will see what changed.
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs uppercase text-textSec font-bold mb-1">Full Name *</label>
                    <input type="text" id="a_full_name" class="w-full text-sm" required>
                </div>
                <div>
                    <label class="block text-xs uppercase text-textSec font-bold mb-1">Flat / Unit *</label>
                    <input type="text" id="a_flat_number" class="w-full text-sm" required>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs uppercase text-textSec font-bold mb-1">Login ID *</label>
                    <input type="text" id="a_login_id" class="w-full text-sm" required>
                </div>
                <div>
                    <label class="block text-xs uppercase text-textSec font-bold mb-1">Monthly Amount (Rs.) *</label>
                    <input type="number" id="a_monthly_amount" class="w-full text-sm" step="0.01" min="0" required>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs uppercase text-textSec font-bold mb-1">Email</label>
                    <input type="email" id="a_email" class="w-full text-sm">
                </div>
                <div>
                    <label class="block text-xs uppercase text-textSec font-bold mb-1">Mobile</label>
                    <input type="tel" id="a_mobile" class="w-full text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs uppercase text-textSec font-bold mb-1">Due Day</label>
                    <select id="a_due_day" class="w-full text-sm">
                        <?php for ($i=1;$i<=28;$i++): ?><option value="<?=$i?>"><?=$i?></option><?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase text-textSec font-bold mb-1">Notes</label>
                    <input type="text" id="a_notes" class="w-full text-sm">
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-border bg-surfaceLight shrink-0 flex gap-3">
            <button onclick="closeApproveModal()" class="btn-ghost text-sm flex-1">Cancel</button>
            <button onclick="submitApprove()" class="btn-primary bg-emerald-600 hover:bg-emerald-500 text-sm flex-1" id="approveSubmitBtn">
                <i class="fas fa-check mr-2"></i>Approve & Create Account
            </button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 z-[100] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-md shadow-2xl">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surfaceLight">
            <h2 class="font-bold text-lg text-textMain">Reject Registration</h2>
            <button onclick="document.getElementById('rejectModal').classList.add('hidden')" class="text-textSec hover:text-textMain w-8 h-8 rounded-full flex items-center justify-center bg-textMain hover:bg-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5 space-y-4 bg-surface">
            <input type="hidden" id="rejectReqId">
            <div>
                <label class="block text-xs uppercase text-textSec font-bold mb-1">Rejection Reason *</label>
                <textarea id="rejectReason" rows="3" class="w-full text-sm" placeholder="Explain why the request is being rejected..." required></textarea>
            </div>
            <button onclick="submitReject()" class="btn-primary bg-red-600 hover:bg-red-500 w-full text-sm">
                <i class="fas fa-times mr-2"></i>Confirm Rejection
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadRequests);

function loadRequests() {
    const status = document.getElementById('statusFilter').value;
    const container = document.getElementById('requestsContainer');
    container.innerHTML = '<div class="glass-panel rounded-xl p-12 text-center"><i class="fas fa-spinner fa-spin text-2xl text-textSec"></i></div>';

    fetch('../member_register_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'list', status })
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success || !d.requests.length) {
            container.innerHTML = `<div class="glass-panel rounded-xl p-12 text-center">
                <i class="fas fa-inbox text-4xl text-textSec mb-4"></i>
                <h3 class="text-lg font-bold text-textMain mb-2">No ${status.toLowerCase()} requests</h3>
                <p class="text-sm text-textSec">${status === 'PENDING' ? 'No pending registrations right now.' : 'No requests with this status.'}</p>
            </div>`;
            return;
        }
        let html = '<div class="grid gap-4">';
        d.requests.forEach(r => {
            const isPending = r.status === 'PENDING';
            const isRejected = r.status === 'REJECTED';
            const border = isPending ? 'border-yellow-900/30' : isRejected ? 'border-red-200' : 'border-emerald-900/30';
            const badge  = isPending ? 'bg-yellow-900/30 text-yellow-400 border-yellow-700/50' : isRejected ? 'bg-red-900/30 text-red-400 border-red-700/50' : 'bg-emerald-900/30 text-emerald-400 border-emerald-700/50';
            const date = new Date(r.created_at).toLocaleDateString('en-IN', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
            const dataAttr = `data-req='${JSON.stringify(r).replace(/'/g,"&#39;")}'`;

            html += `<div class="glass-panel rounded-xl p-5 border ${border}">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-sm font-bold">${r.full_name.charAt(0).toUpperCase()}</div>
                        <div>
                            <div class="font-bold text-textMain">${esc(r.full_name)}</div>
                            <div class="text-xs text-textSec">Flat ${esc(r.flat_number)} | Login: <span class="text-indigo-400 font-mono">${esc(r.login_id)}</span></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge ${badge} text-xs border">${r.status}</span>
                        <div class="text-xs text-textSec mt-1">${date}</div>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-4">
                    <div><div class="text-xs text-textSec">Email</div><div class="text-textMain text-xs truncate">${esc(r.email||'N/A')}</div></div>
                    <div><div class="text-xs text-textSec">Mobile</div><div class="text-textMain text-xs">${esc(r.mobile||'N/A')}</div></div>
                    <div><div class="text-xs text-textSec">Monthly Amt</div><div class="text-textMain font-bold">${parseFloat(r.monthly_amount).toLocaleString('en-IN',{minimumFractionDigits:2})}</div></div>
                    <div><div class="text-xs text-textSec">Due Day</div><div class="text-textMain">${r.due_day}</div></div>
                </div>
                ${r.notes ? `<div class="text-xs text-textSec mb-3 p-2 rounded bg-gray-200/50 border border-border"><b>Notes:</b> ${esc(r.notes)}</div>` : ''}
                ${isRejected && r.reject_reason ? `<div class="text-xs text-red-400 mb-3 p-2 rounded bg-red-900/10 border border-red-200"><b>Rejection Reason:</b> ${esc(r.reject_reason)}</div>` : ''}
                ${r.reviewed_by ? `<div class="text-xs text-textSec mb-3">Reviewed by: ${esc(r.reviewed_by)}${r.reviewed_at ? ' on ' + new Date(r.reviewed_at).toLocaleDateString('en-IN') : ''}</div>` : ''}
                ${isPending ? `<div class="flex items-center gap-3">
                    <button onclick='openApproveModal(${JSON.stringify(r)})' class="btn-primary bg-emerald-600 hover:bg-emerald-500 text-xs">
                        <i class="fas fa-edit mr-1"></i>Review & Approve
                    </button>
                    <button onclick="openReject(${r.id})" class="btn-primary bg-red-600 hover:bg-red-500 text-xs">
                        <i class="fas fa-times mr-1"></i>Reject
                    </button>
                </div>` : ''}
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    })
    .catch(e => { container.innerHTML = `<div class="glass-panel rounded-xl p-8 text-center text-red-400">${e.message}</div>`; });
}

function esc(s) { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

function openApproveModal(r) {
    document.getElementById('approveReqId').value = r.id;
    document.getElementById('a_full_name').value = r.full_name || '';
    document.getElementById('a_flat_number').value = r.flat_number || '';
    document.getElementById('a_login_id').value = r.login_id || '';
    document.getElementById('a_monthly_amount').value = r.monthly_amount || '';
    document.getElementById('a_email').value = r.email || '';
    document.getElementById('a_mobile').value = r.mobile || '';
    document.getElementById('a_due_day').value = r.due_day || 10;
    document.getElementById('a_notes').value = r.notes || '';
    document.getElementById('approveModal').classList.remove('hidden');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
}

async function submitApprove() {
    const btn = document.getElementById('approveSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating account...';
    try {
        const payload = {
            action: 'approve',
            request_id: parseInt(document.getElementById('approveReqId').value),
            full_name: document.getElementById('a_full_name').value.trim(),
            flat_number: document.getElementById('a_flat_number').value.trim(),
            login_id: document.getElementById('a_login_id').value.trim(),
            monthly_amount: parseFloat(document.getElementById('a_monthly_amount').value) || 0,
            email: document.getElementById('a_email').value.trim(),
            mobile: document.getElementById('a_mobile').value.trim(),
            due_day: parseInt(document.getElementById('a_due_day').value),
            notes: document.getElementById('a_notes').value.trim()
        };
        const resp = await fetch('../member_register_api.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        const d = await resp.json();
        closeApproveModal();
        alert(d.message || d.error || 'Done');
        loadRequests();
    } catch(e) {
        alert('Error: ' + e.message);
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check mr-2"></i>Approve & Create Account';
}

function openReject(id) {
    document.getElementById('rejectReqId').value = id;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').classList.remove('hidden');
}

async function submitReject() {
    const id = document.getElementById('rejectReqId').value;
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { alert('Please enter a rejection reason'); return; }
    const resp = await fetch('../member_register_api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'reject', request_id: parseInt(id), reason })
    });
    const d = await resp.json();
    document.getElementById('rejectModal').classList.add('hidden');
    alert(d.message || d.error);
    loadRequests();
}
</script>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
