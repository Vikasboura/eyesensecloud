<?php
/** EyeSense Cloud Portal — My Complaints (Member) */
require_once __DIR__ . '/portal_header.php';

$cid   = $session['client_id'];
$empId = $_SESSION['portal_employee_id'] ?? '';

// Verify member
$mStmt = $pdo->prepare("SELECT * FROM society_members WHERE client_id=? AND employee_id=? AND status='active'");
$mStmt->execute([$cid, $empId]);
$member = $mStmt->fetch();

// Load complaints if member found
$complaints = [];
if ($member) {
    $empIdEsc = $empId;
    $cStmt = $pdo->prepare("
        SELECT DISTINCT c.*
        FROM member_complaints c
        LEFT JOIN complaint_assignments ca
            ON ca.complaint_id = c.id
           AND ca.client_id = c.client_id
           AND ca.removed_at IS NULL
        WHERE c.client_id = ?
          AND (
                c.member_id = ?
                OR ca.assigned_to = ?
              )
        ORDER BY c.created_at DESC
    ");
    $cStmt->execute([$cid, $member['id'], $empIdEsc]);
    $complaints = $cStmt->fetchAll();
}

$total    = count($complaints);
$open     = count(array_filter($complaints, fn($c) => $c['status'] === 'OPEN'));
$resolved = count(array_filter($complaints, fn($c) => $c['status'] === 'RESOLVED'));
$pending  = count(array_filter($complaints, fn($c) => in_array($c['status'], ['OPEN','REVIEWED'])));

$categories = ['General','Maintenance','Security','Noise','Parking','Water','Electricity','Cleanliness','Billing','Other'];
$priorities  = ['LOW','MEDIUM','HIGH','URGENT'];
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<style>
.dataTables_wrapper{color:#d1d5db;}
.dataTables_wrapper .dataTables_length select,.dataTables_wrapper .dataTables_filter input{background:var(--surface)!important;border:1px solid var(--border)!important;color:var(--textMain)!important;border-radius:8px;padding:6px 12px;}
.dataTables_wrapper .dataTables_length label,.dataTables_wrapper .dataTables_filter label,.dataTables_wrapper .dataTables_info{color:#9ca3af;font-size:13px;}
.dataTables_wrapper .dataTables_paginate .paginate_button{background:var(--bg)!important;border:1px solid var(--border)!important;color:#9ca3af!important;border-radius:6px;margin:0 2px;padding:4px 10px;}
.dataTables_wrapper .dataTables_paginate .paginate_button.current{background:#6366f1!important;color:white!important;border-color:#6366f1!important;}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover{background:#d1d5db!important;color:var(--textMain)!important;}
table.dataTable thead th{background:var(--surfaceLight);color:var(--textSec);font-size:11px;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border)!important;}
table.dataTable tbody td{border-bottom:1px solid #f3f4f6!important;}
table.dataTable tbody tr:hover{background:rgba(255,255,255,0.03)!important;cursor:pointer;}
.priority-LOW{color:#9ca3af;background:rgba(156,163,175,.1);border:1px solid rgba(156,163,175,.3);}
.priority-MEDIUM{color:#f59e0b;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);}
.priority-HIGH{color:#ef4444;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);}
.priority-URGENT{color:#a78bfa;background:rgba(167,139,250,.15);border:1px solid rgba(167,139,250,.4);}
.status-OPEN{color:#60a5fa;background:rgba(96,165,250,.1);border:1px solid rgba(96,165,250,.3);}
.status-REVIEWED{color:#a5b4fc;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.3);}
.status-RESOLVED{color:#34d399;background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);}
.status-REJECTED{color:#f87171;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);}
.status-CLOSED{color:#9ca3af;background:rgba(156,163,175,.08);border:1px solid rgba(156,163,175,.2);}
.status-WITHDRAWN{color:#fbbf24;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);}
.row-faded{opacity:0.45;}
.chat-bubble-admin{background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.3);border-radius:12px 12px 4px 12px;}
.chat-bubble-member{background:rgba(20,184,166,0.12);border:1px solid rgba(20,184,166,0.25);border-radius:12px 12px 12px 4px;}
</style>

<!-- Topbar -->
<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0">
        <h1 class="font-bold text-base md:text-lg text-textMain truncate"><i class="fas fa-comment-dots text-orange-400 mr-2"></i>My Complaints</h1>
        <p class="text-xs text-textSec hidden sm:block">View and track your submitted complaints</p>
    </div>
    <?php if ($member): ?>
    <button onclick="openRaiseModal()" class="btn-primary text-xs" id="raiseBtn">
        <i class="fas fa-plus mr-1"></i><span class="hidden sm:inline">Raise </span>Complaint
    </button>
    <?php endif; ?>
</div>

<div class="flex-1 overflow-y-auto p-4 md:p-6">

<?php if (!$member): ?>
    <div class="glass-panel rounded-xl p-10 text-center border border-yellow-900/40 bg-yellow-900/5">
        <i class="fas fa-user-slash text-5xl text-yellow-400/50 mb-4 block"></i>
        <h3 class="text-lg font-bold text-gray-700 mb-2">Not Registered</h3>
        <p class="text-sm text-textSec">Your account is not linked to a society member profile. Please contact your administrator.</p>
    </div>
<?php else: ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="stat-card text-center"><div class="text-textSec text-xs uppercase tracking-wider mb-1">Total</div><div class="text-2xl font-bold text-textMain"><?= $total ?></div></div>
        <div class="stat-card text-center"><div class="text-textSec text-xs uppercase tracking-wider mb-1">Open</div><div class="text-2xl font-bold text-blue-400"><?= $open ?></div></div>
        <div class="stat-card text-center"><div class="text-textSec text-xs uppercase tracking-wider mb-1">Pending Action</div><div class="text-2xl font-bold text-yellow-400"><?= $pending ?></div></div>
        <div class="stat-card text-center"><div class="text-textSec text-xs uppercase tracking-wider mb-1">Resolved</div><div class="text-2xl font-bold text-emerald-400"><?= $resolved ?></div></div>
    </div>

    <!-- Complaints Table -->
    <div class="glass-panel rounded-xl overflow-hidden p-4">
        <?php if (empty($complaints)): ?>
        <div class="text-center py-12">
            <i class="fas fa-comment-slash text-5xl text-gray-700 mb-4 block"></i>
            <h3 class="text-textSec font-semibold mb-2">No Complaints Raised Yet</h3>
            <p class="text-sm text-textSec mb-4">If you have an issue, click the button above to raise a complaint.</p>
            <button onclick="openRaiseModal()" class="btn-primary text-sm"><i class="fas fa-plus mr-1"></i>Raise Your First Complaint</button>
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table id="myComplaintsTable" class="w-full text-left" style="width:100%">
            <thead><tr>
                <th>#</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Date</th>
                <th>View</th>
            </tr></thead>
            <tbody>
            <?php foreach ($complaints as $c):
                $statusCls = "status-{$c['status']}";
                $priCls    = "priority-{$c['priority']}";
                $date      = date('d M Y', strtotime($c['created_at']));
                $subj      = mb_strlen($c['subject']) > 55 ? mb_substr($c['subject'],0,55).'…' : $c['subject'];
            ?>
            <tr onclick="viewComplaint(<?= $c['id'] ?>)" class="cursor-pointer">
                <td class="font-mono text-xs text-textSec">#<?= $c['id'] ?></td>
                <td class="text-xs text-textSec"><?= htmlspecialchars($c['category']) ?></td>
                <td><span class="badge <?= $priCls ?> text-xs"><?= $c['priority'] ?></span></td>
                <td class="text-sm text-gray-700 max-w-xs"><?= htmlspecialchars($subj) ?></td>
                <td><span class="badge <?= $statusCls ?> text-xs"><?= $c['status'] ?></span></td>
                <td class="text-xs text-textSec"><?= $date ?></td>
                <td><button class="btn-ghost text-xs py-1 px-3"><i class="fas fa-eye mr-1"></i>View</button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="glass-panel rounded-xl overflow-hidden p-4 mt-6" id="assignedToMeWrap">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-textSec uppercase tracking-wider"><i class="fas fa-user-check text-indigo-400 mr-2"></i>Assigned To Me</h3>
            <span id="assignedToMeCount" class="text-xs text-textSec">0</span>
        </div>
        <div id="assignedToMeList" class="space-y-2 text-sm text-textSec">
            <div class="text-center text-textSec py-3">Loading...</div>
        </div>
    </div>
<?php endif; ?>
</div>

<!-- Raise Complaint Modal -->
<div id="raiseModal" class="fixed inset-0 z-[9995] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-xl shadow-2xl flex flex-col max-h-[92vh]">
        <div class="p-5 border-b border-border flex justify-between items-center shrink-0 bg-surfaceLight">
            <h2 class="font-bold text-lg text-textMain"><i class="fas fa-comment-medical text-orange-400 mr-2"></i>Raise a Complaint</h2>
            <button onclick="closeRaiseModal()" class="text-textSec hover:text-textMain w-8 h-8 rounded-full bg-textMain hover:bg-gray-700 flex items-center justify-center transition"><i class="fas fa-times"></i></button>
        </div>
        <div class="overflow-y-auto p-5 space-y-4">
            <!-- Pre-filled member info (read-only) -->
            <div class="glass-panel rounded-xl p-4 bg-gray-900/40 border border-border/50">
                <div class="text-xs text-textSec uppercase tracking-wider mb-3">Your Details (Auto-filled)</div>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-textSec">Name:</span> <span class="text-textMain font-semibold"><?= htmlspecialchars($member['full_name'] ?? '') ?></span></div>
                    <div><span class="text-textSec">Flat:</span> <span class="text-textMain font-semibold"><?= htmlspecialchars($member['flat_number'] ?? '') ?></span></div>
                    <div><span class="text-textSec">Email:</span> <span class="text-blue-400 text-xs"><?= htmlspecialchars($member['email'] ?? 'Not set') ?></span></div>
                    <div><span class="text-textSec">Mobile:</span> <span class="text-textSec text-xs"><?= htmlspecialchars($member['mobile'] ?? 'Not set') ?></span></div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase text-textSec font-bold mb-1">Category *</label>
                    <select id="c_category" class="w-full text-sm">
                        <?php foreach ($categories as $cat): ?>
                        <option><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase text-textSec font-bold mb-1">Priority *</label>
                    <select id="c_priority" class="w-full text-sm">
                        <?php foreach ($priorities as $pri): ?>
                        <option value="<?= $pri ?>" <?= $pri==='MEDIUM'?'selected':'' ?>><?= $pri ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase text-textSec font-bold mb-1">Subject *</label>
                <input type="text" id="c_subject" class="w-full text-sm" placeholder="Brief title of your complaint" maxlength="200">
            </div>
            <div>
                <label class="block text-xs uppercase text-textSec font-bold mb-1">Description *</label>
                <textarea id="c_description" rows="4" class="w-full text-sm" placeholder="Describe your issue in detail…"></textarea>
            </div>
            <div>
                <label class="block text-xs uppercase text-textSec font-bold mb-1">Attachments</label>
                <div class="border border-dashed border-border hover:border-indigo-500 rounded-lg p-4 text-center cursor-pointer relative" onclick="document.getElementById('c_files').click()">
                    <i class="fas fa-cloud-arrow-up text-textSec text-2xl mb-1 block"></i>
                    <span class="text-xs text-textSec">Click or drag files here to upload</span>
                    <input type="file" id="c_files" multiple class="hidden" onchange="previewRaiseFiles()" accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.doc,.docx,.xls,.xlsx,.csv,.zip">
                </div>
                <div id="c_files_preview" class="flex flex-wrap gap-2 mt-2"></div>
            </div>
        </div>
        <div class="p-4 border-t border-border shrink-0 bg-surfaceLight flex justify-end gap-3">
            <button onclick="closeRaiseModal()" class="btn-ghost">Cancel</button>
            <button onclick="submitComplaint()" class="btn-primary" id="submitComplaintBtn"><i class="fas fa-paper-plane mr-2"></i>Submit Complaint</button>
        </div>
    </div>
</div>

<!-- View Detail Modal -->
<div id="viewModal" class="fixed inset-0 z-[9995] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-xl shadow-2xl flex flex-col max-h-[92vh]">
        <div class="p-5 border-b border-border flex justify-between items-center shrink-0 bg-surfaceLight">
            <h2 class="font-bold text-lg text-textMain" id="viewModalTitle"><i class="fas fa-comment-dots text-orange-400 mr-2"></i>Complaint Detail</h2>
            <button onclick="closeViewModal()" class="text-textSec hover:text-textMain w-8 h-8 rounded-full bg-textMain hover:bg-gray-700 flex items-center justify-center transition"><i class="fas fa-times"></i></button>
        </div>
        <div class="overflow-y-auto p-5 space-y-4" id="viewModalBody">
            <p class="text-textSec text-sm text-center py-6"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</p>
        </div>
        <div class="p-4 border-t border-border shrink-0 bg-surfaceLight" id="viewModalFooter">
            <div class="flex justify-end gap-3">
                <button onclick="closeViewModal()" class="btn-ghost">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
// ── MODAL TELEPORT ─────────────────────────────────────────────────────────
// glass-panel uses backdrop-filter which traps fixed children. Move modals
// to <body> so they have no backdrop-filter ancestor.
;['raiseModal','viewModal'].forEach(function(id){
    var el = document.getElementById(id);
    if (el && el.parentElement !== document.body) document.body.appendChild(el);
});
// ──────────────────────────────────────────────────────────────────────────

$(document).ready(function(){
    if ($('#myComplaintsTable tbody tr').length) {
        $('#myComplaintsTable').DataTable({
            order:[[5,'desc']],
            pageLength:25,
            language:{search:'<i class="fas fa-search text-textSec mr-1"></i>',searchPlaceholder:'Search complaints…',emptyTable:'No complaints found',info:'Showing _START_ to _END_ of _TOTAL_',lengthMenu:'Show _MENU_ per page'}
        });
    }
    loadAssignedToMe();
});

const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

function openRaiseModal()  { document.getElementById('raiseModal').classList.remove('hidden'); }
function closeRaiseModal() {
    document.getElementById('raiseModal').classList.add('hidden');
    document.getElementById('c_subject').value = '';
    document.getElementById('c_description').value = '';
    document.getElementById('c_category').selectedIndex = 0;
    document.getElementById('c_priority').value = 'MEDIUM';
    if (document.getElementById('c_files')) document.getElementById('c_files').value = '';
    if (document.getElementById('c_files_preview')) document.getElementById('c_files_preview').innerHTML = '';
}
function closeViewModal()  { document.getElementById('viewModal').classList.add('hidden'); }

function previewRaiseFiles() {
    const input = document.getElementById('c_files');
    const prev = document.getElementById('c_files_preview');
    if (!input || !prev) return;
    prev.innerHTML = '';
    Array.from(input.files).forEach(f => {
        const span = document.createElement('span');
        span.className = 'text-xs bg-textMain text-textSec px-2 py-1 rounded flex items-center gap-1';
        span.innerHTML = `<i class="fas fa-file text-indigo-400"></i>${esc(f.name)}`;
        prev.appendChild(span);
    });
}

async function submitComplaint() {
    const subject     = document.getElementById('c_subject').value.trim();
    const description = document.getElementById('c_description').value.trim();
    const category    = document.getElementById('c_category').value;
    const priority    = document.getElementById('c_priority').value;

    if (!subject)     return showAlert('Subject is required', 'warning');
    if (!description) return showAlert('Description is required', 'warning');

    const btn = document.getElementById('submitComplaintBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting…';

    const fd = new FormData();
    fd.append('action', 'submit');
    fd.append('category', category);
    fd.append('priority', priority);
    fd.append('subject', subject);
    fd.append('description', description);

    const files = document.getElementById('c_files')?.files;
    if (files) {
        Array.from(files).forEach(f => fd.append('attachments[]', f));
    }

    try {
        const res  = await fetch('../complaints_api.php', {
            method:'POST',
            body: fd
        });
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        showAlert(data.message, 'success');
        closeRaiseModal();
        setTimeout(() => location.reload(), 1800);
    } catch(e) {
        showAlert(e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Submit Complaint';
    }
}

async function viewComplaint(id) {
    document.getElementById('viewModal').classList.remove('hidden');
    document.getElementById('viewModalBody').innerHTML = '<p class="text-textSec text-sm text-center py-6"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</p>';
    document.getElementById('viewModalFooter').innerHTML = '<div class="flex justify-end gap-3"><button onclick="closeViewModal()" class="btn-ghost">Close</button></div>';

    const res  = await fetch(`../complaints_api.php?action=get&id=${id}`);
    const data = await res.json();
    if (data.error) { showAlert(data.error,'error'); closeViewModal(); return; }
    const c    = data.complaint;
    const canWithdraw = ['OPEN','REVIEWED'].includes(c.status);
    const canReopen   = ['WITHDRAWN'].includes(c.status);

    document.getElementById('viewModalTitle').innerHTML = `<i class="fas fa-comment-dots text-orange-400 mr-2"></i>Complaint #${c.id}`;
    document.getElementById('viewModalBody').innerHTML = `
        <div class="flex flex-wrap gap-2 mb-2">
            <span class="badge priority-${c.priority} text-xs">${c.priority}</span>
            <span class="badge status-${c.status} text-xs">${c.status}</span>
            <span class="text-xs text-textSec ml-auto">${new Date(c.created_at).toLocaleString('en-IN')}</span>
        </div>
        <div class="glass-panel rounded-xl p-4">
            <div class="text-xs text-textSec mb-1 uppercase">Category</div>
            <div class="text-sm text-textMain">${esc(c.category)}</div>
        </div>
        <div class="glass-panel rounded-xl p-4">
            <div class="text-xs text-textSec mb-1 uppercase">Subject</div>
            <div class="text-sm text-textMain font-semibold">${esc(c.subject)}</div>
        </div>
        <div class="glass-panel rounded-xl p-4">
            <div class="text-xs text-textSec mb-1 uppercase">Description</div>
            <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">${esc(c.description)}</div>
            ${c.attachments && c.attachments.length ? `
            <div class="mt-3 pt-3 border-t border-border">
                <div class="text-xs text-textSec uppercase tracking-wider mb-2 font-bold"><i class="fas fa-paperclip mr-1 text-teal-400"></i>Initial Attachments</div>
                <div class="flex flex-wrap gap-2">
                    ${c.attachments.map(a => `<a href="../complaint_comments_api.php?action=serve_file&id=${a.id}" target="_blank" class="text-xs bg-textMain/60 hover:bg-textMain text-indigo-400 hover:text-indigo-300 border border-border/60 px-3 py-1.5 rounded-lg flex items-center gap-1.5 no-underline transition"><i class="fas fa-file"></i>${esc(a.file_name)}</a>`).join('')}
                </div>
            </div>` : ''}
        </div>
        ${c.admin_remarks ? `
        <div class="glass-panel rounded-xl p-4 border border-indigo-800/40 bg-indigo-900/10">
            <div class="text-xs text-indigo-400 uppercase tracking-wider mb-2">Administrator Response</div>
            <div class="text-sm text-gray-700 whitespace-pre-wrap">${esc(c.admin_remarks)}</div>
            ${c.reviewed_by ? `<div class="text-xs text-textSec mt-2">Reviewed by ${esc(c.reviewed_by)}</div>` : ''}
        </div>` : (c.status === 'OPEN' ? `
        <div class="glass-panel rounded-xl p-4 border border-yellow-800/30 bg-yellow-900/5">
            <p class="text-xs text-yellow-400">Your complaint is awaiting review by the administrator.</p>
        </div>` : '')}
        <!-- Comments/Chat -->
        <div class="border-t border-border pt-4">
            <div class="text-xs text-textSec uppercase tracking-wider font-bold mb-3"><i class="fas fa-comments mr-1 text-teal-400"></i>Comments / Chat</div>
            <div id="myChatBox" class="space-y-3 max-h-56 overflow-y-auto pr-1 mb-3"><p class="text-textSec text-xs text-center py-3"><i class="fas fa-spinner fa-spin mr-1"></i>Loading...</p></div>
            <div class="flex gap-2 items-end">
                <textarea id="myChatInput" rows="2" class="flex-1 text-sm" placeholder="Write a message to the admin…"></textarea>
                <div class="flex flex-col gap-1">
                    <label class="btn-ghost text-xs py-1 px-2 cursor-pointer text-center" title="Attach file">
                        <i class="fas fa-paperclip"></i>
                        <input type="file" id="myChatFile" multiple class="hidden" accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.doc,.docx,.xls,.xlsx,.csv,.zip">
                    </label>
                    <button onclick="sendMyComment(${c.id})" class="btn-primary text-xs py-1 px-3"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
            <div id="myChatFilePreview" class="flex flex-wrap gap-2 mt-2"></div>
        </div>`;

    // Dynamic footer with lifecycle buttons
    document.getElementById('viewModalFooter').innerHTML = `
        <div class="flex flex-wrap gap-2 justify-between items-center">
            <button onclick="closeViewModal()" class="btn-ghost text-sm">Close</button>
            <div class="flex gap-2">
                ${canWithdraw ? `<button onclick="withdrawComplaint(${c.id})" class="btn-ghost text-sm border-yellow-700 text-yellow-400 hover:bg-yellow-700 hover:text-textMain"><i class="fas fa-hand mr-1"></i>Withdraw</button>` : ''}
                ${canReopen   ? `<button onclick="reopenComplaint(${c.id})" class="btn-primary text-sm bg-emerald-600 hover:bg-emerald-500"><i class="fas fa-rotate-left mr-1"></i>Reopen</button>` : ''}
                <button onclick="closeViewModal(); openRaiseModal();" class="btn-primary text-sm"><i class="fas fa-plus mr-1"></i>New Complaint</button>
            </div>
        </div>`;

    loadMyComments(c.id);
    // File attach preview
    document.getElementById('myChatFile')?.addEventListener('change', function() {
        const prev = document.getElementById('myChatFilePreview');
        prev.innerHTML = '';
        Array.from(this.files).forEach(f => {
            const span = document.createElement('span');
            span.className = 'text-xs bg-textMain text-textSec px-2 py-1 rounded flex items-center gap-1';
            span.innerHTML = `<i class="fas fa-file text-indigo-400"></i>${esc(f.name)}`;
            prev.appendChild(span);
        });
    });
}

async function withdrawComplaint(id) {
    if (!await showConfirm('Withdraw this complaint? You can reopen it later if needed.')) return;
    try {
        const res  = await fetch('../complaints_api.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'withdraw', id})
        });
        const d = await res.json();
        if (d.error) throw new Error(d.error);
        showAlert(d.message, 'success');
        closeViewModal();
        setTimeout(() => location.reload(), 1500);
    } catch(e) { showAlert(e.message,'error'); }
}

async function reopenComplaint(id) {
    if (!await showConfirm('Reopen this complaint?')) return;
    try {
        const res  = await fetch('../complaints_api.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'reopen', id})
        });
        const d = await res.json();
        if (d.error) throw new Error(d.error);
        showAlert(d.message, 'success');
        closeViewModal();
        setTimeout(() => location.reload(), 1500);
    } catch(e) { showAlert(e.message,'error'); }
}

async function loadMyComments(complaintId) {
    const box = document.getElementById('myChatBox');
    if (!box) return;
    try {
        const res  = await fetch(`../complaint_comments_api.php?action=list&complaint_id=${complaintId}`);
        const data = await res.json();
        const esc  = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const comments = data.comments || [];
        if (!comments.length) { box.innerHTML = '<p class="text-xs text-textSec text-center py-4">No comments yet.</p>'; return; }
        box.innerHTML = comments.map(cmt => {
            const isAdmin = cmt.author_role !== 'MEMBER';
            const cls     = isAdmin ? 'chat-bubble-admin ml-6' : 'chat-bubble-member mr-6';
            const align   = isAdmin ? 'items-end' : 'items-start';
            const atts    = (cmt.attachments||[]).map(a =>
                `<a href="../complaint_comments_api.php?action=serve_file&id=${a.id}" target="_blank" class="text-xs text-indigo-400 hover:underline flex items-center gap-1"><i class="fas fa-paperclip"></i>${esc(a.file_name)}</a>`
            ).join('');
            return `<div class="flex flex-col ${align}">
                <div class="${cls} p-3 max-w-[85%]">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-bold ${isAdmin ? 'text-indigo-300' : 'text-teal-300'}">${esc(cmt.author_name)}</span>
                        <span class="text-xs text-textSec ml-auto">${new Date(cmt.created_at).toLocaleString('en-IN',{hour12:true,hour:'2-digit',minute:'2-digit',day:'2-digit',month:'short'})}</span>
                    </div>
                    <div class="text-sm text-gray-700 whitespace-pre-wrap">${esc(cmt.message)}</div>
                    ${atts ? '<div class="mt-1 space-y-1">'+atts+'</div>' : ''}
                </div>
            </div>`;
        }).join('');
        box.scrollTop = box.scrollHeight;
    } catch(e) {
        const box2 = document.getElementById('myChatBox');
        if (box2) box2.innerHTML = '<p class="text-xs text-red-400 text-center">Failed to load comments</p>';
    }
}

async function sendMyComment(complaintId) {
    const msg = (document.getElementById('myChatInput')?.value || '').trim();
    if (!msg) return showAlert('Please enter a message','warning');
    const fd  = new FormData();
    fd.append('action','add'); fd.append('complaint_id', complaintId); fd.append('message', msg);
    const files = document.getElementById('myChatFile')?.files;
    if (files) Array.from(files).forEach(f => fd.append('files[]', f));
    try {
        const res = await fetch('../complaint_comments_api.php', {method:'POST', body: fd});
        const d   = await res.json();
        if (d.error) throw new Error(d.error);
        document.getElementById('myChatInput').value = '';
        if (document.getElementById('myChatFile')) document.getElementById('myChatFile').value = '';
        if (document.getElementById('myChatFilePreview')) document.getElementById('myChatFilePreview').innerHTML = '';
        loadMyComments(complaintId);
    } catch(e) { showAlert(e.message,'error'); }
}

async function loadAssignedToMe() {
    const listEl = document.getElementById('assignedToMeList');
    const countEl = document.getElementById('assignedToMeCount');
    if (!listEl || !countEl) return;

    try {
        const res = await fetch('../complaints_api.php?action=my_assigned');
        const data = await res.json();
        if (data.error) throw new Error(data.error);

        const rows = data.complaints || [];
        countEl.textContent = `${rows.length}`;

        if (!rows.length) {
            listEl.innerHTML = '<div class="text-center text-textSec py-3">No complaints assigned to you</div>';
            return;
        }

        listEl.innerHTML = rows.slice(0, 8).map(c => `
            <button type="button" onclick="viewComplaint(${c.id})" class="w-full text-left px-3 py-2 rounded-lg border border-border hover:border-indigo-700/60 hover:bg-indigo-900/10 transition bg-transparent cursor-pointer">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs text-textSec">#${c.id} • ${esc(c.category || '')}</div>
                        <div class="text-sm text-gray-700 truncate">${esc(c.subject || '')}</div>
                    </div>
                    <span class="badge status-${c.status} text-xs">${c.status}</span>
                </div>
            </button>
        `).join('');
    } catch (e) {
        listEl.innerHTML = '<div class="text-center text-red-400 py-3">Failed to load assigned complaints</div>';
    }
}
</script>
