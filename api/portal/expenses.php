<?php
/** EyeSense Cloud Portal — Expense Tracker (Admin) */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_MAINTENANCE');
?>
<style>
#ledgerTable{width:100%;border-collapse:collapse;}
#ledgerTable thead th{background:var(--surfaceLight);color:var(--textSec);font-size:11px;text-transform:uppercase;letter-spacing:.05em;padding:10px 12px;border-bottom:1px solid var(--border);white-space:nowrap;}
#ledgerTable tbody td{padding:10px 12px;border-bottom:1px solid #f3f4f6;vertical-align:middle;font-size:13px;}
#ledgerTable tbody tr:hover{background:rgba(255,255,255,0.03);}
.type-CREDIT{color:#34d399;background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);}
.type-DEBIT{color:#f87171;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);}
.drag-handle{cursor:grab;color:var(--textSec);padding:0 8px;font-size:14px;}.drag-handle:hover{color:#a5b4fc;}
.sortable-ghost{opacity:.3;background:#1e1b4b!important;}
.meta-txt{font-size:10px;color:var(--textSec);line-height:1.5;}
#tableSearch{background:var(--surface);border:1px solid var(--border);color:var(--textMain);border-radius:8px;padding:6px 12px;font-size:13px;}
#tableSearch::placeholder{color:var(--textSec);}
.pg-btn{background:var(--bg);border:1px solid var(--border);color:#9ca3af;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:12px;}
.pg-btn:hover,.pg-btn.active{background:#6366f1;color:white;border-color:#6366f1;}
</style>

<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0">
        <h1 class="font-bold text-base md:text-lg text-textMain truncate"><i class="fas fa-wallet text-amber-400 mr-2"></i>Expense Tracker</h1>
        <p class="text-xs text-textSec hidden sm:block">Society income &amp; expense ledger — shared across all admins</p>
    </div>
    <div class="flex items-center gap-3">
        <!-- View Toggle -->
        <div class="flex bg-surface p-1 rounded-lg border border-border">
            <button onclick="toggleView('table')" id="viewBtnTable" class="px-3 py-1.5 rounded-md text-xs font-semibold bg-indigo-600 text-textMain transition">
                <i class="fas fa-table mr-1"></i>Table
            </button>
            <button onclick="toggleView('calendar')" id="viewBtnCalendar" class="px-3 py-1.5 rounded-md text-xs font-semibold text-textSec hover:text-textMain transition">
                <i class="fas fa-calendar mr-1"></i>Calendar
            </button>
        </div>
        <button onclick="openAddModal()" class="btn-primary text-xs"><i class="fas fa-plus mr-1"></i>Add Transaction</button>
    </div>
</div>

<div class="flex-1 overflow-y-auto p-4 md:p-6">
    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <div class="stat-card text-center"><div class="text-textSec text-xs uppercase tracking-wider mb-1">Transactions</div><div class="text-2xl font-bold text-textMain" id="sCount">—</div></div>
        <div class="stat-card text-center"><div class="text-textSec text-xs uppercase tracking-wider mb-1">Total Inflow</div><div class="text-2xl font-bold text-emerald-400" id="sCredit">—</div></div>
        <div class="stat-card text-center"><div class="text-textSec text-xs uppercase tracking-wider mb-1">Total Outflow</div><div class="text-2xl font-bold text-red-400" id="sDebit">—</div></div>
        <div class="stat-card text-center"><div class="text-textSec text-xs uppercase tracking-wider mb-1">Balance</div><div class="text-2xl font-bold" id="sBal">—</div></div>
    </div>

    <!-- Toolbar -->
    <div id="ledgerToolbar" class="rounded-xl p-3 mb-4 flex flex-wrap gap-3 items-center" style="background:var(--surfaceLight);border:1px solid rgba(255,255,255,0.06);">
        <div class="flex gap-2 flex-wrap">
            <input type="date" id="fFrom" onchange="applyView()" class="text-sm" title="From date">
            <input type="date" id="fTo"   onchange="applyView()" class="text-sm" title="To date">
            <select id="fType" onchange="applyView()" class="text-sm">
                <option value="">All Types</option><option value="CREDIT">Credit</option><option value="DEBIT">Debit</option>
            </select>
            <button onclick="clearFilters()" class="btn-ghost text-xs"><i class="fas fa-filter-circle-xmark mr-1"></i>Clear</button>
        </div>
        <div class="ml-auto flex gap-2 items-center flex-wrap">
            <input type="text" id="tableSearch" placeholder="Search..." oninput="applyView()" style="width:180px;">
            <button onclick="exportCSV()" class="btn-ghost text-xs"><i class="fas fa-file-csv mr-1"></i>CSV</button>
            <button onclick="exportPrint()" class="btn-ghost text-xs"><i class="fas fa-print mr-1"></i>Print</button>
        </div>
    </div>

    <div id="syncBar" class="hidden text-xs text-center text-indigo-400 mb-2 py-1"><i class="fas fa-rotate fa-spin mr-1"></i>Syncing changes from other admins...</div>

    <!-- Table View -->
    <div id="tableViewContainer" class="glass-panel rounded-xl overflow-hidden">
        <div style="overflow-x:auto;">
            <table id="ledgerTable">
                <thead><tr>
                    <th style="width:28px"></th>
                    <th>Date</th><th>Type</th><th>Party</th><th>Work / Description</th>
                    <th>Cheque / Ref</th><th class="text-right">Amount</th><th class="text-right">Balance</th>
                    <th>Added / Edited By</th><th style="width:100px;">Actions</th>
                </tr></thead>
                <tbody id="ledgerBody"></tbody>
            </table>
        </div>
        <div class="p-3 flex flex-wrap items-center justify-between gap-2 border-t border-border">
            <span class="text-xs text-textSec" id="tableInfo"></span>
            <div id="pagination" class="flex gap-1"></div>
        </div>
    </div>

    <!-- Calendar View -->
    <div id="calendarViewContainer" class="hidden space-y-4">
        <div class="glass-panel rounded-xl p-4 flex items-center justify-between bg-surfaceLight border border-border">
            <div class="flex items-center gap-3">
                <button onclick="prevMonth()" class="btn-ghost text-xs py-1.5 px-3"><i class="fas fa-chevron-left"></i></button>
                <div class="flex gap-2 items-center">
                    <select id="calMonthSelect" onchange="onMonthYearSelectChange()" class="bg-surface border border-border text-textMain rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:border-indigo-500 outline-none cursor-pointer">
                        <option value="0">January</option>
                        <option value="1">February</option>
                        <option value="2">March</option>
                        <option value="3">April</option>
                        <option value="4">May</option>
                        <option value="5">June</option>
                        <option value="6">July</option>
                        <option value="7">August</option>
                        <option value="8">September</option>
                        <option value="9">October</option>
                        <option value="10">November</option>
                        <option value="11">December</option>
                    </select>
                    <select id="calYearSelect" onchange="onMonthYearSelectChange()" class="bg-surface border border-border text-textMain rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:border-indigo-500 outline-none cursor-pointer">
                        <!-- Populated by JS -->
                    </select>
                </div>
                <button onclick="nextMonth()" class="btn-ghost text-xs py-1.5 px-3"><i class="fas fa-chevron-right"></i></button>
            </div>
            <button onclick="goToToday()" class="btn-ghost text-xs py-1.5 px-4 bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 rounded-lg">Today</button>
        </div>

        <div class="glass-panel rounded-xl p-4 overflow-hidden border border-border">
            <div class="grid grid-cols-7 gap-1 text-center font-bold text-xs text-textSec uppercase tracking-wider mb-2">
                <div class="py-1">Sun</div><div class="py-1">Mon</div><div class="py-1">Tue</div><div class="py-1">Wed</div><div class="py-1">Thu</div><div class="py-1">Fri</div><div class="py-1">Sat</div>
            </div>
            <div id="calendarGrid" class="grid grid-cols-7 gap-1.5 min-h-[350px]"></div>
        </div>
    </div>
</div>

<!-- Day Details Draggable Modal — direct child of <main>, outside glass-panel backdrop-filter elements -->
<div id="dayDetailsModal" class="fixed inset-0 z-[9999] hidden bg-textMain/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div id="draggableModalBox" class="bg-surface border border-border rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col absolute" style="left: 50%; top: 50%; transform: translate(-50%, -50%); min-width: 320px;">
        <div id="dragHeader" class="p-4 border-b border-border flex justify-between items-center shrink-0 bg-surfaceLight cursor-move select-none rounded-t-2xl">
            <h2 class="font-bold text-sm md:text-base text-textMain flex items-center gap-2">
                <i class="fas fa-calendar-day text-indigo-400"></i>
                <span id="detailsModalDate">—</span>
            </h2>
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-textSec hidden sm:inline">Drag header to move</span>
                <button onclick="closeDayDetailsModal()" class="text-textSec hover:text-textMain w-8 h-8 rounded-full bg-textMain hover:bg-gray-700 flex items-center justify-center cursor-pointer border-none"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
            <!-- Day & Cumulative Stats Grid -->
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-surfaceLight border border-border/80 rounded-xl p-3 text-center">
                    <div class="text-[10px] text-textSec uppercase tracking-wider mb-1">Day Income</div>
                    <div class="text-sm font-bold text-emerald-400" id="mDayIncome">₹0</div>
                </div>
                <div class="bg-surfaceLight border border-border/80 rounded-xl p-3 text-center">
                    <div class="text-[10px] text-textSec uppercase tracking-wider mb-1">Day Expense</div>
                    <div class="text-sm font-bold text-red-400" id="mDayExpense">₹0</div>
                </div>
                <div class="bg-surfaceLight border border-border/80 rounded-xl p-3 text-center">
                    <div class="text-[10px] text-textSec uppercase tracking-wider mb-1">Day Net</div>
                    <div class="text-sm font-bold" id="mDayNet">₹0</div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="bg-[#0f172a] border border-indigo-950/80 rounded-xl p-3 text-center">
                    <div class="text-[10px] text-indigo-400 uppercase tracking-wider mb-1">Total Income Till Day</div>
                    <div class="text-sm font-bold text-indigo-200" id="mCumIncome">₹0</div>
                </div>
                <div class="bg-[#0f172a] border border-indigo-950/80 rounded-xl p-3 text-center">
                    <div class="text-[10px] text-indigo-400 uppercase tracking-wider mb-1">Total Expense Till Day</div>
                    <div class="text-sm font-bold text-indigo-200" id="mCumExpense">₹0</div>
                </div>
                <div class="bg-[#0f172a] border border-indigo-950/80 rounded-xl p-3 text-center">
                    <div class="text-[10px] text-indigo-400 uppercase tracking-wider mb-1">Balance Till Day</div>
                    <div class="text-sm font-bold text-emerald-400" id="mCumBalance">₹0</div>
                </div>
            </div>

            <!-- Day's Transactions Table -->
            <div class="glass-panel rounded-xl overflow-hidden border border-border">
                <div style="overflow-x:auto;">
                    <table class="w-full text-left" style="min-width: 500px;">
                        <thead>
                            <tr class="border-b border-border bg-surfaceLight/50 text-[10px] font-bold text-textSec uppercase">
                                <th class="p-3" style="width: 90px;">Type</th>
                                <th class="p-3" style="width: 140px;">Party</th>
                                <th class="p-3">Description</th>
                                <th class="p-3" style="width: 120px;">Cheque/Ref</th>
                                <th class="p-3 text-right" style="width: 120px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="dayTxnsBody">
                            <!-- JS populated -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<!-- Add/Edit Modal -->
<div id="txnModal" class="fixed inset-0 z-[9995] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-xl shadow-2xl flex flex-col max-h-[92vh]">
        <div class="p-5 border-b border-border flex justify-between items-center shrink-0 bg-surfaceLight">
            <h2 class="font-bold text-lg text-textMain" id="txnTitle"><i class="fas fa-plus-circle text-amber-400 mr-2"></i>Add Transaction</h2>
            <button onclick="closeTxnModal()" class="text-textSec hover:text-textMain w-8 h-8 rounded-full bg-textMain hover:bg-gray-700 flex items-center justify-center"><i class="fas fa-times"></i></button>
        </div>
        <div class="overflow-y-auto p-5 space-y-4">
            <input type="hidden" id="txn_id"><input type="hidden" id="txn_expected_ts"><input type="hidden" id="txn_after_id">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Date *</label><input type="date" id="txn_date" class="w-full text-sm"></div>
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Type *</label>
                    <select id="txn_type" class="w-full text-sm"><option value="DEBIT">Debit (Expense)</option><option value="CREDIT">Credit (Income)</option></select></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Amount (Rs) *</label><input type="number" id="txn_amount" class="w-full text-sm" min="0.01" step="0.01" placeholder="0.00"></div>
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Cheque / Ref</label><input type="text" id="txn_cheque" class="w-full text-sm" placeholder="Cheque no, UPI ref..."></div>
            </div>
            <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Party Name *</label><input type="text" id="txn_party" class="w-full text-sm" maxlength="255"></div>
            <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Work / Description</label><input type="text" id="txn_work" class="w-full text-sm" maxlength="500"></div>
            <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Notes / Remarks</label><textarea id="txn_notes" rows="2" class="w-full text-sm"></textarea></div>
        </div>
        <div class="p-4 border-t border-border shrink-0 bg-surfaceLight flex justify-end gap-3">
            <button onclick="closeTxnModal()" class="btn-ghost">Cancel</button>
            <button onclick="saveTxn()" class="btn-primary" id="saveTxnBtn"><i class="fas fa-save mr-2"></i>Save</button>
        </div>
    </div>
</div>

<!-- Delete Confirm -->
<div id="delModal" class="fixed inset-0 z-[9998] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-sm shadow-2xl p-6 text-center">
        <i class="fas fa-trash-alt text-4xl text-red-400 mb-4"></i>
        <h3 class="text-textMain font-bold text-lg mb-2">Delete Transaction?</h3>
        <p class="text-textSec text-sm mb-6">This cannot be undone. The running balance will update automatically.</p>
        <input type="hidden" id="del_id">
        <div class="flex gap-3 justify-center">
            <button onclick="document.getElementById('delModal').classList.add('hidden')" class="btn-ghost">Cancel</button>
            <button onclick="confirmDelete()" class="btn-primary" style="background:#dc2626"><i class="fas fa-trash mr-1"></i>Delete</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
// ── MODAL TELEPORT ──────────────────────────────────────────────────────────
// backdrop-filter on any ancestor creates a new containing block for `fixed`
// elements, trapping them. Moving modals to <body> avoids all ancestors.
;['dayDetailsModal','txnModal','delModal'].forEach(function(id){
    var el = document.getElementById(id);
    if (el && el.parentElement !== document.body) document.body.appendChild(el);
});
// ───────────────────────────────────────────────────────────────────────────

let allEntries = [], filtered = [], lastChecksum = '', sortableInst = null;
let isEditing = false, isDragging = false;
const PAGE_SIZE = 50;
let curPage = 1;

const esc  = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const fmtR = n => 'Rs. '+Number(n).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
const fmtD = d => new Date(d+'T00:00:00').toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'});
const fmtT = d => d ? new Date(d).toLocaleString('en-IN',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '';

async function loadLedger(silent=false) {
    const p = new URLSearchParams({action:'list'});
    const from = document.getElementById('fFrom').value;
    const to   = document.getElementById('fTo').value;
    const type = document.getElementById('fType').value;
    if (from) p.append('from',from);
    if (to)   p.append('to',to);
    if (type) p.append('type',type);
    try {
        const res  = await fetch('../ledger_api.php?'+p);
        const data = await res.json();
        if (data.error) { if(!silent) showAlert(data.error,'error'); return; }
        allEntries   = data.entries || [];
        lastChecksum = data.checksum || '';
        updateStats(data.stats);
        applyView();
    } catch(e) { if(!silent) showAlert('Failed to load ledger','error'); }
}

function updateStats(s) {
    if (!s) return;
    const bal = parseFloat(s.total_credit||0) - parseFloat(s.total_debit||0);
    document.getElementById('sCount').textContent  = s.total_count;
    document.getElementById('sCredit').textContent = fmtR(s.total_credit||0);
    document.getElementById('sDebit').textContent  = fmtR(s.total_debit||0);
    const el = document.getElementById('sBal');
    el.textContent = (bal < 0 ? '- ' : '') + fmtR(Math.abs(bal));
    el.className   = 'text-2xl font-bold ' + (bal >= 0 ? 'text-emerald-400' : 'text-red-400');
}

function applyView() {
    const q = (document.getElementById('tableSearch').value||'').toLowerCase();
    filtered = allEntries.filter(e => {
        if (!q) return true;
        return [e.party_name, e.work_description, e.cheque_info, e.transaction_type, e.transaction_date]
            .some(v => String(v||'').toLowerCase().includes(q));
    });
    curPage = 1;
    renderPage();
    if (currentViewMode === 'calendar') {
        renderCalendar();
    }
}

function renderPage() {
    const tbody = document.getElementById('ledgerBody');
    const start = (curPage - 1) * PAGE_SIZE;
    const page  = filtered.slice(start, start + PAGE_SIZE);

    tbody.innerHTML = '';
    page.forEach(e => {
        const isC = e.transaction_type === 'CREDIT';
        const bal = parseFloat(e.balance);
        let meta = `<span class="meta-txt">Added: ${esc(e.created_by)}<br>${fmtT(e.created_at)}</span>`;
        if (e.updated_by) meta = `<span class="meta-txt">Added: ${esc(e.created_by)} ${fmtT(e.created_at)}<br>Edited: <strong>${esc(e.updated_by)}</strong> ${fmtT(e.updated_at)}</span>`;
        const tr = document.createElement('tr');
        tr.dataset.id = e.id;
        tr.innerHTML = `
            <td class="drag-handle text-center"><i class="fas fa-grip-vertical"></i></td>
            <td class="text-gray-700 whitespace-nowrap">${fmtD(e.transaction_date)}</td>
            <td><span class="badge type-${e.transaction_type} text-xs">${e.transaction_type}</span></td>
            <td class="text-textMain font-semibold">${esc(e.party_name)}</td>
            <td class="text-textSec" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${esc(e.work_description)}">${esc(e.work_description||'—')}</td>
            <td class="text-textSec text-xs">${esc(e.cheque_info||'—')}</td>
            <td class="text-right font-mono font-semibold ${isC?'text-emerald-400':'text-red-400'}">${isC?'+':'−'} ${fmtR(e.amount)}</td>
            <td class="text-right font-mono ${bal>=0?'text-blue-300':'text-red-300'}">${fmtR(bal)}</td>
            <td>${meta}</td>
            <td>
              <div class="flex gap-1 flex-wrap">
                <button onclick="openEdit(${e.id})" class="btn-ghost text-xs py-1 px-2" title="Edit"><i class="fas fa-pen"></i></button>
                <button onclick="openDel(${e.id})" class="btn-ghost text-xs py-1 px-2 text-red-400" title="Delete"><i class="fas fa-trash"></i></button>
                <button onclick="openAddBelow(${e.id},'${e.transaction_date}')" class="btn-ghost text-xs py-1 px-2 text-indigo-400" title="Insert a new row directly below this entry" style="white-space:nowrap"><i class="fas fa-plus mr-1"></i>Below</button>
              </div>
            </td>`;
        tbody.appendChild(tr);
    });

    // Info + pagination
    const total = filtered.length;
    const pages = Math.ceil(total / PAGE_SIZE);
    document.getElementById('tableInfo').textContent = total === 0 ? 'No records' : `${start+1}–${Math.min(start+PAGE_SIZE,total)} of ${total}`;
    const pgDiv = document.getElementById('pagination');
    pgDiv.innerHTML = '';
    for (let i = 1; i <= pages; i++) {
        const b = document.createElement('button');
        b.className = 'pg-btn' + (i === curPage ? ' active' : '');
        b.textContent = i;
        b.onclick = () => { curPage = i; renderPage(); };
        pgDiv.appendChild(b);
    }
    initSortable();
}

function initSortable() {
    if (sortableInst) { sortableInst.destroy(); sortableInst = null; }
    const tbody = document.getElementById('ledgerBody');
    if (!tbody || !tbody.children.length) return;
    sortableInst = Sortable.create(tbody, {
        handle: '.drag-handle', animation: 150,
        ghostClass: 'sortable-ghost',
        onStart() { isDragging = true; },
        async onEnd() {
            isDragging = false;
            const ids = Array.from(tbody.querySelectorAll('tr[data-id]')).map(r => parseInt(r.dataset.id));
            try {
                const res  = await fetch('../ledger_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'reorder',ordered_ids:ids})});
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                await loadLedger(true);
            } catch(e) { showAlert(e.message,'error'); loadLedger(true); }
        }
    });
}

// ── Modals ──────────────────────────────────────────────────
function openAddModal() {
    isEditing = true;
    document.getElementById('txnTitle').innerHTML = '<i class="fas fa-plus-circle text-amber-400 mr-2"></i>Add Transaction';
    ['txn_id','txn_expected_ts','txn_after_id','txn_amount','txn_party','txn_work','txn_cheque','txn_notes'].forEach(id => document.getElementById(id).value='');
    document.getElementById('txn_date').value = new Date().toISOString().slice(0,10);
    document.getElementById('txn_type').value = 'DEBIT';
    document.getElementById('txnModal').classList.remove('hidden');
}
function openAddBelow(afterId, date) {
    openAddModal();
    document.getElementById('txn_after_id').value = afterId;
    document.getElementById('txn_date').value = date;
    document.getElementById('txnTitle').innerHTML = `<i class="fas fa-arrow-down text-indigo-400 mr-2"></i>Insert Below #${afterId}`;
}
function openEdit(id) {
    const e = allEntries.find(x => x.id == id); if (!e) return;
    isEditing = true;
    document.getElementById('txnTitle').innerHTML = `<i class="fas fa-pen text-amber-400 mr-2"></i>Edit #${e.id}`;
    document.getElementById('txn_id').value = e.id;
    document.getElementById('txn_expected_ts').value = e.updated_at || e.created_at;
    document.getElementById('txn_after_id').value = '';
    document.getElementById('txn_date').value   = e.transaction_date;
    document.getElementById('txn_type').value   = e.transaction_type;
    document.getElementById('txn_amount').value = e.amount;
    document.getElementById('txn_party').value  = e.party_name;
    document.getElementById('txn_work').value   = e.work_description;
    document.getElementById('txn_cheque').value = e.cheque_info||'';
    document.getElementById('txn_notes').value  = e.notes||'';
    document.getElementById('txnModal').classList.remove('hidden');
}
function closeTxnModal() { document.getElementById('txnModal').classList.add('hidden'); isEditing = false; }

async function saveTxn() {
    const id     = document.getElementById('txn_id').value;
    const date   = document.getElementById('txn_date').value;
    const amount = parseFloat(document.getElementById('txn_amount').value);
    const party  = document.getElementById('txn_party').value.trim();
    if (!date)        return showAlert('Date is required','warning');
    if (!(amount>0))  return showAlert('Amount must be greater than zero','warning');
    if (!party)       return showAlert('Party name is required','warning');
    const btn = document.getElementById('saveTxnBtn');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    const body = {
        action: id ? 'update' : 'add',
        transaction_date: date, transaction_type: document.getElementById('txn_type').value,
        amount, party_name: party,
        work_description: document.getElementById('txn_work').value.trim(),
        cheque_info:      document.getElementById('txn_cheque').value.trim(),
        notes:            document.getElementById('txn_notes').value.trim(),
    };
    if (id) { body.id = parseInt(id); body.expected_updated_at = document.getElementById('txn_expected_ts').value; }
    else { const aid=document.getElementById('txn_after_id').value; if(aid) body.after_id=parseInt(aid); }
    try {
        const res  = await fetch('../ledger_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        closeTxnModal();
        await loadLedger();
        setTimeout(()=>showAlert(data.message,'success'),100);
    } catch(e) { showAlert(e.message,'error'); }
    btn.disabled=false; btn.innerHTML='<i class="fas fa-save mr-2"></i>Save';
}

function openDel(id) { document.getElementById('del_id').value=id; document.getElementById('delModal').classList.remove('hidden'); }
async function confirmDelete() {
    const id = parseInt(document.getElementById('del_id').value);
    document.getElementById('delModal').classList.add('hidden');
    try {
        const res  = await fetch('../ledger_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id})});
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        await loadLedger();
        setTimeout(()=>showAlert(data.message,'success'),100);
    } catch(e) { showAlert(e.message,'error'); }
}

function clearFilters() { ['fFrom','fTo'].forEach(id=>document.getElementById(id).value=''); document.getElementById('fType').value=''; document.getElementById('tableSearch').value=''; loadLedger(); }

// ── Export ──────────────────────────────────────────────────
function exportCSV() {
    const cols = ['Date','Type','Party','Work/Description','Cheque/Ref','Amount','Balance'];
    const rows = [cols.join(','), ...filtered.map(e => [
        e.transaction_date, e.transaction_type, '"'+String(e.party_name).replace(/"/g,'""')+'"',
        '"'+String(e.work_description||'').replace(/"/g,'""')+'"',
        '"'+String(e.cheque_info||'').replace(/"/g,'""')+'"',
        e.amount, e.balance
    ].join(','))];
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(rows.join('\r\n'));
    a.download = 'Ledger_<?= date('Y-m-d') ?>.csv'; a.click();
}
function exportPrint() {
    const rows = filtered.map(e => {
        const isC = e.transaction_type==='CREDIT';
        const bal = parseFloat(e.balance);
        let meta = `Added: ${esc(e.created_by)} ${fmtT(e.created_at)}`;
        if (e.updated_by) meta += `<br>Edited: ${esc(e.updated_by)} ${fmtT(e.updated_at)}`;
        return `<tr>
            <td>${fmtD(e.transaction_date)}</td>
            <td style="color:${isC?'#059669':'#dc2626'};font-weight:bold">${e.transaction_type}</td>
            <td>${esc(e.party_name)}</td>
            <td>${esc(e.work_description||'—')}</td>
            <td>${esc(e.cheque_info||'—')}</td>
            <td style="text-align:right;color:${isC?'#059669':'#dc2626'}">${isC?'+':'−'} ${fmtR(e.amount)}</td>
            <td style="text-align:right;color:${bal>=0?'#1d4ed8':'#dc2626'}">${fmtR(bal)}</td>
            <td style="font-size:10px;color:var(--textSec)">${meta}</td>
        </tr>`;
    }).join('');
    const win = window.open('','_blank');
    win.document.write(`<!DOCTYPE html><html><head><title>Society Ledger</title>
    <style>body{font-family:Arial,sans-serif;font-size:12px;color:#374151;padding:20px;}
    h1{font-size:18px;margin:0 0 4px;}p.sub{color:var(--textSec);font-size:11px;margin:0 0 16px;}
    table{width:100%;border-collapse:collapse;}th{background:var(--surfaceLight);padding:8px;border-bottom:2px solid #d1d5db;text-align:left;font-size:11px;text-transform:uppercase;color:var(--textSec);}
    td{padding:7px 8px;border-bottom:1px solid var(--border);}tr:nth-child(even){background:var(--bg);}
    @media print{button{display:none!important;}}</style></head><body>
    <h1>Society Ledger — Expense Tracker</h1>
    <p class="sub">Printed on <?= date('d M Y, h:i A') ?></p>
    <button onclick="window.print()" style="margin-bottom:12px;padding:6px 16px;background:#4f46e5;color:white;border:none;border-radius:6px;cursor:pointer;">Print / Save PDF</button>
    <table><thead><tr><th>Date</th><th>Type</th><th>Party</th><th>Work/Description</th><th>Cheque/Ref</th><th>Amount</th><th>Balance</th><th>Admin</th></tr></thead>
    <tbody>${rows}</tbody></table></body></html>`);
    win.document.close();
}

// ── Calendar View & Draggable Day Details Modal ──────────────────────────────
let currentViewMode = 'table';
let currentYear = new Date().getFullYear();
let currentMonth = new Date().getMonth();

function toggleView(mode) {
    currentViewMode = mode;
    const tableBtn = document.getElementById('viewBtnTable');
    const calBtn = document.getElementById('viewBtnCalendar');
    const tableCont = document.getElementById('tableViewContainer');
    const calCont = document.getElementById('calendarViewContainer');
    const toolbar = document.getElementById('ledgerToolbar');

    if (mode === 'table') {
        tableBtn.className = "px-3 py-1.5 rounded-md text-xs font-semibold bg-indigo-600 text-textMain transition";
        calBtn.className = "px-3 py-1.5 rounded-md text-xs font-semibold text-textSec hover:text-textMain transition";
        tableCont.classList.remove('hidden');
        calCont.classList.add('hidden');
        if (toolbar) toolbar.classList.remove('hidden');
    } else {
        calBtn.className = "px-3 py-1.5 rounded-md text-xs font-semibold bg-indigo-600 text-textMain transition";
        tableBtn.className = "px-3 py-1.5 rounded-md text-xs font-semibold text-textSec hover:text-textMain transition";
        tableCont.classList.add('hidden');
        calCont.classList.remove('hidden');
        if (toolbar) toolbar.classList.add('hidden');
        renderCalendar();
    }
}

function prevMonth() {
    currentMonth--;
    if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    }
    renderCalendar();
}

function nextMonth() {
    currentMonth++;
    if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    }
    renderCalendar();
}

function goToToday() {
    currentYear = new Date().getFullYear();
    currentMonth = new Date().getMonth();
    renderCalendar();
}

function onMonthYearSelectChange() {
    const monthSelect = document.getElementById('calMonthSelect');
    const yearSelect = document.getElementById('calYearSelect');
    if (monthSelect && yearSelect) {
        currentMonth = parseInt(monthSelect.value);
        currentYear = parseInt(yearSelect.value);
        renderCalendar();
    }
}

function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    if (!grid) return;

    // Populate Year Select if empty
    const yearSelect = document.getElementById('calYearSelect');
    if (yearSelect && yearSelect.options.length === 0) {
        const startY = 2020;
        const endY = new Date().getFullYear() + 10;
        for (let y = startY; y <= endY; y++) {
            const opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            yearSelect.appendChild(opt);
        }
    }
    // Set selected values
    const monthSelect = document.getElementById('calMonthSelect');
    if (monthSelect) monthSelect.value = currentMonth;
    if (yearSelect) yearSelect.value = currentYear;

    grid.innerHTML = '';

    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const totalDays = new Date(currentYear, currentMonth + 1, 0).getDate();
    const prevMonthDays = new Date(currentYear, currentMonth, 0).getDate();

    // Previous month inactive days
    for (let i = firstDay - 1; i >= 0; i--) {
        const d = prevMonthDays - i;
        const div = document.createElement('div');
        div.className = 'bg-surface/10 border border-border/30 rounded-lg p-2 min-h-[75px] opacity-30 flex flex-col justify-between';
        div.innerHTML = `<span class="text-xs font-bold text-textSec">${d}</span>`;
        grid.appendChild(div);
    }

    // Current month days
    for (let day = 1; day <= totalDays; day++) {
        const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        
        // Filter transactions from filtered list (respecting any date range, type or text query filters!)
        const dayTxns = filtered.filter(e => e.transaction_date === dateStr);
        let creditSum = 0;
        let debitSum = 0;
        dayTxns.forEach(t => {
            if (t.transaction_type === 'CREDIT') creditSum += parseFloat(t.amount);
            else debitSum += parseFloat(t.amount);
        });

        const isToday = new Date().toDateString() === new Date(currentYear, currentMonth, day).toDateString();

        const div = document.createElement('div');
        div.className = `bg-surfaceLight/20 border ${isToday ? 'border-indigo-500 bg-indigo-500/10 shadow-[0_0_15px_rgba(99,102,241,0.15)]' : 'border-border'} rounded-lg p-2 min-h-[75px] flex flex-col justify-between hover:border-indigo-400 hover:bg-indigo-950/20 transition cursor-pointer`;
        div.onclick = () => openDayDetails(dateStr);

        let content = `<span class="text-xs font-bold ${isToday ? 'text-indigo-400' : 'text-textSec'}">${day}</span>`;
        if (creditSum > 0 || debitSum > 0) {
            content += `<div class="flex flex-col gap-0.5 mt-1">`;
            if (creditSum > 0) {
                content += `<span class="text-[9px] text-emerald-400 font-semibold truncate" title="Inflow: ₹${creditSum.toLocaleString()}"><i class="fas fa-arrow-down mr-0.5"></i>₹${Math.round(creditSum).toLocaleString()}</span>`;
            }
            if (debitSum > 0) {
                content += `<span class="text-[9px] text-red-400 font-semibold truncate" title="Outflow: ₹${debitSum.toLocaleString()}"><i class="fas fa-arrow-up mr-0.5"></i>₹${Math.round(debitSum).toLocaleString()}</span>`;
            }
            content += `</div>`;
        } else {
            content += `<span class="text-[9px] text-gray-700 mt-2 block">No txns</span>`;
        }

        div.innerHTML = content;
        grid.appendChild(div);
    }

    // Next month inactive days
    const totalSlots = firstDay + totalDays;
    const remaining = 42 - totalSlots;
    for (let i = 1; i <= remaining; i++) {
        const div = document.createElement('div');
        div.className = 'bg-surface/10 border border-border/30 rounded-lg p-2 min-h-[75px] opacity-30 flex flex-col justify-between';
        div.innerHTML = `<span class="text-xs font-bold text-textSec">${i}</span>`;
        grid.appendChild(div);
    }
}

function openDayDetails(dateStr) {
    // Show all transactions of that day
    const dayTxns = allEntries.filter(e => e.transaction_date === dateStr);
    
    let dayIncome = 0;
    let dayExpense = 0;
    dayTxns.forEach(t => {
        if (t.transaction_type === 'CREDIT') dayIncome += parseFloat(t.amount);
        else dayExpense += parseFloat(t.amount);
    });
    let dayNet = dayIncome - dayExpense;

    // Cumulative sum till that day (inclusive)
    let cumIncome = 0;
    let cumExpense = 0;
    for (let e of allEntries) {
        if (e.transaction_date <= dateStr) {
            if (e.transaction_type === 'CREDIT') cumIncome += parseFloat(e.amount);
            else cumExpense += parseFloat(e.amount);
        } else {
            break;
        }
    }
    let cumBalance = cumIncome - cumExpense;

    // Populate Modal Details
    document.getElementById('detailsModalDate').textContent = fmtD(dateStr);
    document.getElementById('mDayIncome').textContent = fmtR(dayIncome);
    document.getElementById('mDayExpense').textContent = fmtR(dayExpense);
    
    const dayNetEl = document.getElementById('mDayNet');
    dayNetEl.textContent = (dayNet < 0 ? '- ' : '') + fmtR(Math.abs(dayNet));
    dayNetEl.className = 'text-sm font-bold ' + (dayNet >= 0 ? 'text-emerald-400' : 'text-red-400');

    document.getElementById('mCumIncome').textContent = fmtR(cumIncome);
    document.getElementById('mCumExpense').textContent = fmtR(cumExpense);
    
    const cumBalEl = document.getElementById('mCumBalance');
    cumBalEl.textContent = (cumBalance < 0 ? '- ' : '') + fmtR(Math.abs(cumBalance));
    cumBalEl.className = 'text-sm font-bold ' + (cumBalance >= 0 ? 'text-emerald-400' : 'text-red-400');

    const tbody = document.getElementById('dayTxnsBody');
    if (dayTxns.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="p-4 text-center text-xs text-textSec">No transactions recorded for this day.</td></tr>`;
    } else {
        tbody.innerHTML = dayTxns.map(t => {
            const isC = t.transaction_type === 'CREDIT';
            return `<tr class="border-b border-border/40 hover:bg-textMain/20 text-xs">
                <td class="p-3"><span class="badge type-${t.transaction_type} text-[10px]">${t.transaction_type}</span></td>
                <td class="p-3 font-semibold text-textMain">${esc(t.party_name)}</td>
                <td class="p-3 text-textSec">${esc(t.work_description || '—')}</td>
                <td class="p-3 text-textSec font-mono">${esc(t.cheque_info || '—')}</td>
                <td class="p-3 text-right font-mono font-bold ${isC ? 'text-emerald-400' : 'text-red-400'}">${isC ? '+' : '−'} ${fmtR(t.amount)}</td>
            </tr>`;
        }).join('');
    }

    // Reset popup/modal to center
    const box = document.getElementById('draggableModalBox');
    box.style.top = '50%';
    box.style.left = '50%';
    box.style.transform = 'translate(-50%, -50%)';

    // Show modal
    document.getElementById('dayDetailsModal').classList.remove('hidden');
    
    // Make draggable
    makeDraggable('draggableModalBox', 'dragHeader');
}

function closeDayDetailsModal() {
    document.getElementById('dayDetailsModal').classList.add('hidden');
}

function makeDraggable(modalId, headerId) {
    const modal = document.getElementById(modalId);
    const header = document.getElementById(headerId);
    if (!modal || !header) return;

    let posX = 0, posY = 0, mouseX = 0, mouseY = 0;
    header.onmousedown = dragMouseDown;

    function dragMouseDown(e) {
        e = e || window.event;
        if (e.target.closest('button')) return;
        e.preventDefault();
        
        mouseX = e.clientX;
        mouseY = e.clientY;
        
        document.onmouseup = closeDragElement;
        document.onmousemove = elementDrag;
    }

    function elementDrag(e) {
        e = e || window.event;
        e.preventDefault();
        
        posX = mouseX - e.clientX;
        posY = mouseY - e.clientY;
        mouseX = e.clientX;
        mouseY = e.clientY;
        
        modal.style.top = (modal.offsetTop - posY) + "px";
        modal.style.left = (modal.offsetLeft - posX) + "px";
        modal.style.transform = "none"; // Clear transform centering translate once dragging begins
    }

    function closeDragElement() {
        document.onmouseup = null;
        document.onmousemove = null;
    }
}

loadLedger();
setInterval(syncCheck, 8000);
</script>
