<?php
/** EyeSense Cloud Portal — Complaints Management (Admin) */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_MAINTENANCE');
$cid = $session['client_id'];

// Stats
$stats = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status='OPEN') AS open_count,
        SUM(status='REVIEWED') AS reviewed_count,
        SUM(status='RESOLVED') AS resolved_count,
        SUM(status='REJECTED') AS rejected_count,
        SUM(status='CLOSED') AS closed_count,
        SUM(status='WITHDRAWN') AS withdrawn_count,
        SUM(priority='URGENT') AS urgent_count
    FROM member_complaints WHERE client_id=?
");
$stats->execute([$cid]);
$st = $stats->fetch();
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<style>
    .dataTables_wrapper {
        color: #d1d5db;
    }

    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
        background: var(--surface) !important;
        border: 1px solid var(--border) !important;
        color: var(--textMain) !important;
        border-radius: 8px;
        padding: 6px 12px;
    }

    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label,
    .dataTables_wrapper .dataTables_info {
        color: #9ca3af;
        font-size: 13px;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        background: var(--bg) !important;
        border: 1px solid var(--border) !important;
        color: #9ca3af !important;
        border-radius: 6px;
        margin: 0 2px;
        padding: 4px 10px;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #6366f1 !important;
        color: var(--textMain) !important;
        border-color: #6366f1 !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #d1d5db !important;
        color: var(--textMain) !important;
    }

    table.dataTable thead th {
        background: var(--surfaceLight);
        color: var(--textSec);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--border) !important;
    }

    table.dataTable tbody td {
        border-bottom: 1px solid #f3f4f6 !important;
    }

    table.dataTable tbody tr:hover {
        background: rgba(255, 255, 255, 0.03) !important;
        cursor: pointer;
    }

    .dt-buttons .dt-button {
        background: var(--bg) !important;
        color: #d1d5db !important;
        border: 1px solid var(--border) !important;
        border-radius: 8px !important;
        padding: 6px 14px !important;
        font-size: 12px !important;
        font-weight: 600;
        cursor: pointer;
    }

    .dt-buttons .dt-button:hover {
        background: #d1d5db !important;
        color: var(--textMain) !important;
    }

    .priority-LOW {
        color: #9ca3af;
        background: rgba(156, 163, 175, 0.1);
        border: 1px solid rgba(156, 163, 175, 0.3);
    }

    .priority-MEDIUM {
        color: #f59e0b;
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .priority-HIGH {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .priority-URGENT {
        color: #a78bfa;
        background: rgba(167, 139, 250, 0.15);
        border: 1px solid rgba(167, 139, 250, 0.4);
    }

    .status-OPEN {
        color: #60a5fa;
        background: rgba(96, 165, 250, 0.1);
        border: 1px solid rgba(96, 165, 250, 0.3);
    }

    .status-REVIEWED {
        color: #a5b4fc;
        background: rgba(99, 102, 241, 0.1);
        border: 1px solid rgba(99, 102, 241, 0.3);
    }

    .status-RESOLVED {
        color: #34d399;
        background: rgba(52, 211, 153, 0.1);
        border: 1px solid rgba(52, 211, 153, 0.3);
    }

    .status-REJECTED {
        color: #f87171;
        background: rgba(248, 113, 113, 0.1);
        border: 1px solid rgba(248, 113, 113, 0.3);
    }

    .status-CLOSED {
        color: #9ca3af;
        background: rgba(156, 163, 175, 0.08);
        border: 1px solid rgba(156, 163, 175, 0.2);
    }

    .status-WITHDRAWN {
        color: #fbbf24;
        background: rgba(251, 191, 36, 0.08);
        border: 1px solid rgba(251, 191, 36, 0.2);
    }

    .row-faded {
        opacity: 0.45;
    }

    .chat-bubble-admin {
        background: rgba(99, 102, 241, 0.15);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 12px 12px 4px 12px;
    }

    .chat-bubble-member {
        background: rgba(20, 184, 166, 0.12);
        border: 1px solid rgba(20, 184, 166, 0.25);
        border-radius: 12px 12px 12px 4px;
    }
</style>

<!-- Topbar -->
<div
    class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0">
        <h1 class="font-bold text-base md:text-lg text-textMain truncate"><i
                class="fas fa-triangle-exclamation text-red-400 mr-2"></i>Member Complaints</h1>
        <p class="text-xs text-textSec hidden sm:block">Review and manage complaints raised by society members</p>
    </div>
</div>

<div class="flex-1 overflow-y-auto p-4 md:p-6">

    <!-- Stats -->
    <div class="grid grid-cols-3 md:grid-cols-7 gap-3 mb-6">
        <div class="stat-card text-center">
            <div class="text-textSec text-xs uppercase tracking-wider mb-1">Total</div>
            <div class="text-2xl font-bold text-textMain"><?= (int) $st['total'] ?></div>
        </div>
        <div class="stat-card text-center cursor-pointer" onclick="setStatusFilter('OPEN')">
            <div class="text-textSec text-xs uppercase tracking-wider mb-1">Open</div>
            <div class="text-2xl font-bold text-blue-400"><?= (int) $st['open_count'] ?></div>
        </div>
        <div class="stat-card text-center cursor-pointer" onclick="setStatusFilter('REVIEWED')">
            <div class="text-textSec text-xs uppercase tracking-wider mb-1">Reviewed</div>
            <div class="text-2xl font-bold text-indigo-400"><?= (int) $st['reviewed_count'] ?></div>
        </div>
        <div class="stat-card text-center cursor-pointer" onclick="setStatusFilter('RESOLVED')">
            <div class="text-textSec text-xs uppercase tracking-wider mb-1">Resolved</div>
            <div class="text-2xl font-bold text-emerald-400"><?= (int) $st['resolved_count'] ?></div>
        </div>
        <div class="stat-card text-center cursor-pointer" onclick="setStatusFilter('REJECTED')">
            <div class="text-textSec text-xs uppercase tracking-wider mb-1">Rejected</div>
            <div class="text-2xl font-bold text-red-400"><?= (int) $st['rejected_count'] ?></div>
        </div>
        <div class="stat-card text-center cursor-pointer" onclick="setStatusFilter('CLOSED')">
            <div class="text-textSec text-xs uppercase tracking-wider mb-1">Closed</div>
            <div class="text-2xl font-bold text-textSec"><?= (int) $st['closed_count'] ?></div>
        </div>
        <div class="stat-card text-center">
            <div class="text-textSec text-xs uppercase tracking-wider mb-1">Urgent</div>
            <div class="text-2xl font-bold text-purple-400"><?= (int) $st['urgent_count'] ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="glass-panel rounded-xl p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-textSec uppercase mb-1">Status</label>
            <select id="fStatus" onchange="applyFilters()" class="text-sm">
                <option value="">All Statuses</option>
                <option value="OPEN">Open</option>
                <option value="REVIEWED">Reviewed</option>
                <option value="RESOLVED">Resolved</option>
                <option value="REJECTED">Rejected</option>
                <option value="CLOSED">Closed</option>
                <option value="WITHDRAWN">Withdrawn</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-textSec uppercase mb-1">Priority</label>
            <select id="fPriority" onchange="applyFilters()" class="text-sm">
                <option value="">All Priorities</option>
                <option value="URGENT">Urgent</option>
                <option value="HIGH">High</option>
                <option value="MEDIUM">Medium</option>
                <option value="LOW">Low</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-textSec uppercase mb-1">Category</label>
            <select id="fCategory" onchange="applyFilters()" class="text-sm">
                <option value="">All Categories</option>
                <?php foreach (['General', 'Maintenance', 'Security', 'Noise', 'Parking', 'Water', 'Electricity', 'Cleanliness', 'Billing', 'Other'] as $cat): ?>
                    <option value="<?= $cat ?>"><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end gap-2 ml-auto">
            <button id="btnAssignedMe" onclick="toggleAssignedToMe()" class="btn-ghost text-xs">
                <i class="fas fa-user-check mr-1"></i>Assigned to Me Only
            </button>
            <button onclick="clearFilters()" class="btn-ghost text-xs"><i
                    class="fas fa-filter-circle-xmark mr-1"></i>Clear</button>
        </div>
    </div>

    <!-- Table -->
    <div class="glass-panel rounded-xl overflow-hidden p-4">
        <div class="table-responsive">
            <table id="complaintsTable" class="w-full text-left" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Member</th>
                        <th>Flat</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="complaintsBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detail / Action Modal -->
<div id="complaintModal"
    class="fixed inset-0 z-[100] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[92vh]">
        <div class="p-5 border-b border-border flex justify-between items-center shrink-0 bg-surfaceLight">
            <h2 class="font-bold text-lg text-textMain"><i
                    class="fas fa-triangle-exclamation text-red-400 mr-2"></i>Complaint Detail</h2>
            <button onclick="closeModal()"
                class="text-textSec hover:text-textMain w-8 h-8 rounded-full bg-textMain hover:bg-gray-700 flex items-center justify-center transition"><i
                    class="fas fa-times"></i></button>
        </div>
        <div class="overflow-y-auto p-5 space-y-4" id="modalBody">
            <p class="text-textSec text-sm">Loading...</p>
        </div>
        <div class="p-4 border-t border-border shrink-0 bg-surfaceLight space-y-3" id="modalFooter"></div>
    </div>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script>
    let dtTable = null;
    let allComplaints = [];

    const priorityBadge = p => `<span class="badge priority-${p} text-xs">${p}</span>`;
    const statusBadge = s => `<span class="badge status-${s} text-xs">${s}</span>`;


    let _assignedToMe = false;
    let _assigneeList = [];

    function syncAssignedButton() {
        const btn = document.getElementById('btnAssignedMe');
        if (!btn) return;
        btn.className = _assignedToMe
            ? 'text-xs px-3 py-2 rounded-lg font-bold bg-indigo-600 text-textMain border border-indigo-500 transition'
            : 'btn-ghost text-xs';
    }

    function setStatusFilter(s) {
        document.getElementById('fStatus').value = s;
        applyFilters();
    }

    function toggleAssignedToMe() {
        _assignedToMe = !_assignedToMe;
        syncAssignedButton();
        applyFilters();
    }

    function renderAdminOptions(selectId, admins, includeCurrentAdmin = false) {
        const sel = document.getElementById(selectId);
        if (!sel) return;
        const currentAdmin = includeCurrentAdmin && PORTAL.employeeId ? PORTAL.employeeId : '';
        const options = ['<option value="">All Admins</option>'];
        admins.forEach(admin => {
            const value = String(admin.employee_id || '').trim();
            if (!value) return;
            options.push(`<option value="${escHtml(value)}">${escHtml(admin.full_name || value)}</option>`);
        });
        if (currentAdmin && !options.some(option => option.includes(`value="${currentAdmin}"`))) {
            options.push(`<option value="${escHtml(currentAdmin)}">My assignments</option>`);
        }
        sel.innerHTML = options.join('');
    }

    async function loadAdmins() {
        if (_assigneeList.length) return _assigneeList;
        try {
            const res = await fetch('../maintenance_members_api.php?action=list_complaint_assignees');
            const data = await res.json();
            _assigneeList = data.assignees || [];
        } catch (e) { _assigneeList = []; }
        return _assigneeList;
    }

    async function loadComplaints(status = '', priority = '', category = '', assignedEmployeeId = '') {
        const params = new URLSearchParams({ action: 'list' });
        if (status) params.append('status', status);
        if (priority) params.append('priority', priority);
        if (category) params.append('category', category);
        const effectiveAssignedEmployeeId = assignedEmployeeId || (_assignedToMe ? PORTAL.employeeId : '');
        if (effectiveAssignedEmployeeId) {
            params.append('assigned_employee_id', effectiveAssignedEmployeeId);
            params.append('assigned_to_me', '1');
            params.append('employee_id', effectiveAssignedEmployeeId);
            console.log('[Complaints] Assigned filter ON. employee_id =', effectiveAssignedEmployeeId);
        }
        const url = '../complaints_api.php?' + params;
        console.log('[Complaints] Fetching:', url);
        const res = await fetch(url);
        const data = await res.json();
        if (data.error) { console.error('[Complaints] API error:', data.error); showAlert(data.error, 'error'); return; }
        allComplaints = data.complaints || [];
        console.log('[Complaints] Got', allComplaints.length, 'results');
        renderTable(allComplaints);
    }

    function buildComplaintRow(c) {
        const date = new Date(c.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        const subj = c.subject.length > 50 ? c.subject.substring(0, 50) + '…' : c.subject;
        const faded = ['CLOSED', 'WITHDRAWN'].includes(c.status) ? 'row-faded' : '';
        const cmtBadge = c.comment_count > 0 ? `<span title="${c.comment_count} comments" style="margin-left:4px;font-size:10px;color:#a5b4fc;"><i class="fas fa-comment"></i> ${c.comment_count}</span>` : '';
        const assignedChips = c.assigned_to_ids
            ? c.assigned_to_ids.split(',').map(id => `<span class="text-xs bg-indigo-900/40 text-indigo-300 px-1.5 py-0.5 rounded" title="Assigned">${escHtml(id)}</span>`).join(' ')
            : '';

        return [
            `<span class="font-mono text-xs text-textSec ${faded}">#${c.id}</span>`,
            `<span class="text-sm text-textMain font-semibold ${faded}">${escHtml(c.full_name)}</span>`,
            `<span class="text-sm text-textSec ${faded}">${escHtml(c.flat_number)}</span>`,
            `<span class="text-xs text-textSec ${faded}">${escHtml(c.category)}</span>`,
            priorityBadge(c.priority),
            `<span class="text-sm text-gray-700 max-w-xs ${faded}">${escHtml(subj)}${cmtBadge}</span>`,
            `${statusBadge(c.status)}${assignedChips ? '<div class="mt-1">' + assignedChips + '</div>' : ''}`,
            `<span class="text-xs text-textSec ${faded}">${date}</span>`,
            `<button onclick="event.stopPropagation();openComplaint(${c.id})" class="btn-ghost text-xs py-1 px-3"><i class="fas fa-eye mr-1"></i>View</button>`
        ];
    }

    function renderTable(complaints) {
        const rows = complaints.map(buildComplaintRow);
        const tbody = document.getElementById('complaintsBody');
        const tableExists = $.fn.DataTable.isDataTable('#complaintsTable');

        if (tableExists) {
            dtTable = $('#complaintsTable').DataTable();
            dtTable.clear();
            if (rows.length) {
                dtTable.rows.add(rows);
            }
            dtTable.draw(false);
            return;
        }

        tbody.innerHTML = '';
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-10 text-center text-textSec text-sm">No complaints found</td></tr>';
            return;
        }

        rows.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = row.map(cell => `<td>${cell}</td>`).join('');
            tr.onclick = () => openComplaint(complaints[index].id);
            tbody.appendChild(tr);
        });

        dtTable = $('#complaintsTable').DataTable({
            order: [],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            dom: '<"flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4"<"flex items-center gap-2"lB><f>rtip',
            buttons: [
                { extend: 'csvHtml5', text: '<i class="fas fa-file-csv mr-1"></i>CSV', title: 'Complaints_<?= date('Y-m-d') ?>', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] } },
                { extend: 'excelHtml5', text: '<i class="fas fa-file-excel mr-1"></i>Excel', title: 'Complaints_<?= date('Y-m-d') ?>', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] } },
                { extend: 'print', text: '<i class="fas fa-print mr-1"></i>Print', title: 'Member Complaints', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] } }
            ],
            language: { search: '<i class="fas fa-search text-textSec mr-1"></i>', searchPlaceholder: 'Search complaints…', emptyTable: 'No complaints found', info: 'Showing _START_ to _END_ of _TOTAL_ complaints', lengthMenu: 'Show _MENU_ per page' }
        });
    }

    function applyFilters() {
        loadComplaints(
            document.getElementById('fStatus').value,
            document.getElementById('fPriority').value,
            document.getElementById('fCategory').value,
            _assignedToMe ? PORTAL.employeeId : ''
        );
    }

    function clearFilters() {
        ['fStatus', 'fPriority', 'fCategory'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        _assignedToMe = false;
        syncAssignedButton();
        loadComplaints();
    }

    async function openComplaint(id) {
        document.getElementById('complaintModal').classList.remove('hidden');
        document.getElementById('modalBody').innerHTML = '<p class="text-textSec text-sm text-center py-6"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</p>';
        document.getElementById('modalFooter').innerHTML = '';
        const res = await fetch(`../complaints_api.php?action=get&id=${id}`);
        const data = await res.json();
        if (data.error) { showAlert(data.error, 'error'); return; }
        const c = data.complaint;
        const isClosed = ['CLOSED', 'WITHDRAWN'].includes(c.status);
        const assignees = c.assignments || [];
        // Build assignee chips
        const assigneeChips = assignees.map(a =>
            `<span class="inline-flex items-center gap-1 text-xs bg-indigo-900/40 text-indigo-300 border border-indigo-700/40 px-2 py-0.5 rounded-full">${escHtml(a.assignee_name || a.admin_name || a.assigned_to)}${a.assignee_role ? `<span class="text-[10px] text-textSec ml-1">(${escHtml(a.assignee_role)})</span>` : ''}<button onclick="unassignAdmin(${id},'${a.assigned_to}')" class="text-red-400 hover:text-red-300 ml-1"><i class="fas fa-times"></i></button></span>`
        ).join('');

        document.getElementById('modalBody').innerHTML = `
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
            <div class="glass-panel rounded-lg p-3"><div class="text-xs text-textSec mb-1">Complaint ID</div><div class="font-mono text-textMain font-bold">#${c.id}</div></div>
            <div class="glass-panel rounded-lg p-3"><div class="text-xs text-textSec mb-1">Status</div>${statusBadge(c.status)}</div>
            <div class="glass-panel rounded-lg p-3"><div class="text-xs text-textSec mb-1">Priority</div>${priorityBadge(c.priority)}</div>
            <div class="glass-panel rounded-lg p-3"><div class="text-xs text-textSec mb-1">Member</div><div class="text-textMain font-semibold">${escHtml(c.full_name)}</div></div>
            <div class="glass-panel rounded-lg p-3"><div class="text-xs text-textSec mb-1">Flat / Unit</div><div class="text-textMain">${escHtml(c.flat_number)}</div></div>
            <div class="glass-panel rounded-lg p-3"><div class="text-xs text-textSec mb-1">Category</div><div class="text-textMain">${escHtml(c.category)}</div></div>
        </div>
        <div class="glass-panel rounded-xl p-4">
            <div class="text-xs text-textSec uppercase tracking-wider mb-2">Subject</div>
            <div class="text-textMain font-semibold text-sm">${escHtml(c.subject)}</div>
        </div>
        <div class="glass-panel rounded-xl p-4">
            <div class="text-xs text-textSec uppercase tracking-wider mb-2">Description</div>
            <div class="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap">${escHtml(c.description)}</div>
            ${c.attachments && c.attachments.length ? `
            <div class="mt-3 pt-3 border-t border-border">
                <div class="text-xs text-textSec uppercase tracking-wider mb-2 font-bold"><i class="fas fa-paperclip mr-1 text-teal-400"></i>Initial Attachments (${c.attachments.length})</div>
                <div class="flex flex-wrap gap-2">
                    ${c.attachments.map(a => `<a href="../complaint_comments_api.php?action=serve_file&id=${a.id}" target="_blank"
                        class="text-xs bg-textMain/60 hover:bg-textMain text-indigo-400 hover:text-indigo-300 border border-border/60 px-3 py-1.5 rounded-lg flex items-center gap-1.5 no-underline transition">
                        <i class="fas fa-file"></i>${escHtml(a.file_name)}
                        <span class="text-textSec ml-1">${a.file_size_kb ? a.file_size_kb + 'KB' : ''}</span>
                    </a>`).join('')}
                </div>
            </div>` : ''}
        </div>
        <!-- Assign Section -->
        <div class="glass-panel rounded-xl p-4 border border-indigo-800/30">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs text-indigo-400 uppercase tracking-wider font-bold"><i class="fas fa-user-check mr-1"></i>Assigned To</div>
                <button onclick="openAssignPanel(${id})" class="btn-ghost text-xs py-1 px-2"><i class="fas fa-plus mr-1"></i>Assign</button>
            </div>
            <div id="assigneeChips" class="flex flex-wrap gap-2 min-h-[24px]">${assigneeChips || '<span class="text-xs text-textSec">No users assigned</span>'}</div>
            <div id="assignPanel" class="hidden mt-3">
                <select id="assignSelect" class="w-full text-sm mb-2"><option value="">Loading users...</option></select>
                <button onclick="doAssign(${id})" class="btn-primary text-xs"><i class="fas fa-user-plus mr-1"></i>Assign Selected</button>
            </div>
        </div>
        ${c.admin_remarks ? `<div class="glass-panel rounded-xl p-4 border border-indigo-800/40 bg-indigo-900/10"><div class="text-xs text-indigo-400 uppercase tracking-wider mb-2">Admin Remarks</div><div class="text-gray-700 text-sm whitespace-pre-wrap">${escHtml(c.admin_remarks)}</div></div>` : ''}
        <div class="border-t border-border pt-4">
            <label class="block text-xs text-textSec uppercase tracking-wider mb-2">Admin Remarks / Response</label>
            <textarea id="adminRemarks" rows="2" class="w-full text-sm">${c.admin_remarks ? escHtml(c.admin_remarks) : ''}</textarea>
        </div>
        <!-- Comments/Chat Section -->
        <div class="border-t border-border pt-4">
            <div class="text-xs text-textSec uppercase tracking-wider font-bold mb-3"><i class="fas fa-comments mr-1 text-teal-400"></i>Comments / Chat</div>
            <div id="chatBox" class="space-y-3 max-h-64 overflow-y-auto pr-1 mb-3"><p class="text-textSec text-xs text-center py-3"><i class="fas fa-spinner fa-spin mr-1"></i>Loading...</p></div>
            <div class="flex gap-2 items-end">
                <textarea id="chatInput" rows="2" class="flex-1 text-sm" placeholder="Write a comment…"></textarea>
                <div class="flex flex-col gap-1">
                    <label class="btn-ghost text-xs py-1 px-2 cursor-pointer text-center" title="Attach file">
                        <i class="fas fa-paperclip"></i>
                        <input type="file" id="chatFile" multiple class="hidden" accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.mp4,.avi,.mov,.mkv,.pdf,.txt,.doc,.docx,.xls,.xlsx,.csv,.zip,.rar">
                    </label>
                    <button onclick="sendComment(${id})" class="btn-primary text-xs py-1 px-3"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
            <div id="chatFilePreview" class="flex flex-wrap gap-2 mt-2"></div>
        </div>
        <div class="text-xs text-textSec">Submitted: ${new Date(c.created_at).toLocaleString('en-IN')}${c.reviewed_by ? ' &bull; Reviewed by: ' + escHtml(c.reviewed_by) : ''}</div>`;

        document.getElementById('modalFooter').innerHTML = `
        <div class="flex flex-wrap gap-2 justify-between items-center">
            <button onclick="closeModal()" class="btn-ghost text-sm">Close</button>
            <div class="flex flex-wrap gap-2">
                ${isClosed
                ? `<button onclick="doReopen(${id})" class="btn-primary text-sm bg-emerald-600 hover:bg-emerald-500"><i class="fas fa-rotate-left mr-1"></i>Reopen</button>`
                : `<button onclick="updateStatus(${id},'REVIEWED')" class="btn-ghost text-sm border-indigo-600 text-indigo-400 hover:bg-indigo-600 hover:text-textMain"><i class="fas fa-eye mr-1"></i>Mark Reviewed</button>
                       <button onclick="updateStatus(${id},'RESOLVED')" class="btn-primary text-sm bg-emerald-600 hover:bg-emerald-500"><i class="fas fa-check mr-1"></i>Mark Resolved</button>
                       <button onclick="updateStatus(${id},'REJECTED')" class="btn-danger text-sm"><i class="fas fa-ban mr-1"></i>Reject</button>
                       <button onclick="doClose(${id})" class="btn-ghost text-sm border-gray-600 text-textSec hover:bg-gray-700"><i class="fas fa-xmark mr-1"></i>Close</button>`
            }
            </div>
        </div>`;

        loadComments(id);
        loadAdmins().then(admins => {
            const sel = document.getElementById('assignSelect');
            if (!sel) return;
            sel.innerHTML = '<option value="">— Select User —</option>' +
            admins.map(a => `<option value="${escHtml(a.employee_id)}">${escHtml(a.full_name)} (${escHtml(a.role || 'USER')})</option>`).join('');
        });
        // File preview
        document.getElementById('chatFile')?.addEventListener('change', function () {
            const prev = document.getElementById('chatFilePreview');
            prev.innerHTML = '';
            Array.from(this.files).forEach(f => {
                const pill = document.createElement('span');
                pill.className = 'text-xs bg-textMain text-textSec px-2 py-1 rounded flex items-center gap-1';
                pill.innerHTML = `<i class="fas fa-file text-indigo-400"></i>${escHtml(f.name)}`;
                prev.appendChild(pill);
            });
        });
    }

    function openAssignPanel(id) {
        const p = document.getElementById('assignPanel');
        if (p) p.classList.toggle('hidden');
    }

    async function doAssign(id) {
        const sel = document.getElementById('assignSelect');
        const empId = sel?.value;
        if (!empId) return showAlert('Select a user to assign', 'warning');
        try {
            const res = await fetch('../complaints_api.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'assign', id, assignees: [empId] })
            });
            const d = await res.json();
            if (d.error) throw new Error(d.error);
            showAlert(d.message, 'success');
            openComplaint(id); // Refresh modal
        } catch (e) { showAlert(e.message, 'error'); }
    }

    async function unassignAdmin(id, empId) {
        if (!await showConfirm('Remove this admin from the complaint?')) return;
        try {
            const res = await fetch('../complaints_api.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'unassign', id, employee_id: empId })
            });
            const d = await res.json();
            if (d.error) throw new Error(d.error);
            showAlert(d.message, 'success');
            openComplaint(id);
        } catch (e) { showAlert(e.message, 'error'); }
    }

    async function doClose(id) {
        if (!await showConfirm('Close this complaint?')) return;
        try {
            const res = await fetch('../complaints_api.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'close', id })
            });
            const d = await res.json();
            if (d.error) throw new Error(d.error);
            closeModal(); applyFilters();
            setTimeout(() => showAlert(d.message, 'success'), 100);
        } catch (e) { showAlert(e.message, 'error'); }
    }

    async function doReopen(id) {
        if (!await showConfirm('Reopen this complaint?')) return;
        try {
            const res = await fetch('../complaints_api.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reopen', id })
            });
            const d = await res.json();
            if (d.error) throw new Error(d.error);
            closeModal(); applyFilters();
            setTimeout(() => showAlert(d.message, 'success'), 100);
        } catch (e) { showAlert(e.message, 'error'); }
    }

    async function loadComments(complaintId) {
        const box = document.getElementById('chatBox');
        if (!box) return;
        try {
            const res = await fetch(`../complaint_comments_api.php?action=list&complaint_id=${complaintId}`);
            const data = await res.json();
            const comments = data.comments || [];
            if (!comments.length) {
                box.innerHTML = '<p class="text-xs text-textSec text-center py-4">No comments yet. Start the conversation!</p>';
                return;
            }
            box.innerHTML = comments.map(cmt => {
                const isAdmin = cmt.author_role !== 'MEMBER';
                const cls = isAdmin ? 'chat-bubble-admin ml-6' : 'chat-bubble-member mr-6';
                const align = isAdmin ? 'items-end' : 'items-start';
                const atts = (cmt.attachments || []).map(a =>
                    `<a href="../complaint_comments_api.php?action=serve_file&id=${a.id}" target="_blank" class="text-xs text-indigo-400 hover:underline flex items-center gap-1"><i class="fas fa-paperclip"></i>${escHtml(a.file_name)}</a>`
                ).join('');
                return `<div class="flex flex-col ${align}">
                <div class="${cls} p-3 max-w-[85%]">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-bold ${isAdmin ? 'text-indigo-300' : 'text-teal-300'}">${escHtml(cmt.author_name)}</span>
                        <span class="text-xs text-textSec">${cmt.author_role}</span>
                        <span class="text-xs text-textSec ml-auto">${new Date(cmt.created_at).toLocaleString('en-IN', { hour12: true, hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' })}</span>
                    </div>
                    <div class="text-sm text-gray-700 whitespace-pre-wrap">${escHtml(cmt.message)}</div>
                    ${atts ? '<div class="mt-2 space-y-1">' + atts + '</div>' : ''}
                </div>
            </div>`;
            }).join('');
            box.scrollTop = box.scrollHeight;
        } catch (e) {
            if (box) box.innerHTML = '<p class="text-xs text-red-400 text-center py-2">Failed to load comments</p>';
        }
    }

    async function sendComment(complaintId) {
        const msg = (document.getElementById('chatInput')?.value || '').trim();
        if (!msg) return showAlert('Please enter a comment', 'warning');
        const fd = new FormData();
        fd.append('action', 'add');
        fd.append('complaint_id', complaintId);
        fd.append('message', msg);
        const files = document.getElementById('chatFile')?.files;
        if (files) Array.from(files).forEach(f => fd.append('files[]', f));
        try {
            const res = await fetch('../complaint_comments_api.php', { method: 'POST', body: fd });
            const d = await res.json();
            if (d.error) throw new Error(d.error);
            document.getElementById('chatInput').value = '';
            if (document.getElementById('chatFile')) document.getElementById('chatFile').value = '';
            if (document.getElementById('chatFilePreview')) document.getElementById('chatFilePreview').innerHTML = '';
            loadComments(complaintId);
        } catch (e) { showAlert(e.message, 'error'); }
    }



    async function updateStatus(id, status) {
        const ta = document.getElementById('adminRemarks');
        const remarks = ta?.value?.trim() || '';

        // Reject requires a reason — show inline error on the textarea (visible above modal)
        if (status === 'REJECTED' && !remarks) {
            if (ta) {
                ta.style.borderColor = '#ef4444';
                ta.focus();
                let errEl = document.getElementById('remarksError');
                if (!errEl) {
                    errEl = document.createElement('p');
                    errEl.id = 'remarksError';
                    errEl.className = 'text-xs text-red-400 mt-1';
                    ta.parentNode.appendChild(errEl);
                }
                errEl.textContent = 'A reason is required when rejecting a complaint.';
                ta.addEventListener('input', () => {
                    ta.style.borderColor = '';
                    if (errEl) errEl.textContent = '';
                }, { once: true });
            }
            return;
        }

        // Clear any previous inline error
        if (ta) ta.style.borderColor = '';
        const errEl = document.getElementById('remarksError');
        if (errEl) errEl.textContent = '';

        try {
            const res = await fetch('../complaints_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_status', id, status, admin_remarks: remarks })
            });
            const data = await res.json();
            if (data.error) throw new Error(data.error);
            closeModal();
            applyFilters();
            // Show success toast after modal is closed so it's always visible
            setTimeout(() => showAlert(data.message, 'success'), 100);
        } catch (e) {
            showAlert(e.message, 'error');
        }
    }

    function closeModal() { document.getElementById('complaintModal').classList.add('hidden'); }
    function escHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    syncAssignedButton();
    loadAdmins().finally(() => loadComplaints());
</script>
