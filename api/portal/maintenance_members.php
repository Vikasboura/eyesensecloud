<?php
/** EyeSense Cloud Portal — Society Members Management */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_MAINTENANCE');
$cid = $session['client_id'];
// Load sqft_rate for this client
$_sqftR = $pdo->prepare("SELECT sqft_rate FROM maintenance_settings WHERE client_id=?");
$_sqftR->execute([$cid]);
$_sqftRow  = $_sqftR->fetch();
$sqftRate  = $_sqftRow ? (float)$_sqftRow['sqft_rate'] : 0;
?>

<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0"><h1 class="font-bold text-base md:text-lg text-textMain truncate">Society Members</h1><p class="text-xs text-textSec hidden sm:block">Manage residents &amp; their portal accounts</p></div>
    <div class="flex gap-2 flex-shrink-0">
        <button onclick="getRegLink()" class="btn-ghost text-xs" id="regLinkBtn"><i class="fas fa-link mr-1 sm:mr-2"></i><span class="hidden sm:inline">Get Registration Link</span><span class="sm:hidden">Reg. Link</span></button>
        <button onclick="openAddModal()" class="btn-primary text-xs"><i class="fas fa-plus mr-1"></i><span class="hidden sm:inline">Add </span>Member</button>
    </div>
</div>

<div class="flex-1 overflow-y-auto p-6">
    <!-- Info banner -->
    <div class="glass-panel rounded-xl p-4 mb-4 border border-emerald-900/50 bg-emerald-900/10 flex items-center gap-3">
        <i class="fas fa-info-circle text-emerald-400 text-xl"></i>
        <p class="text-sm text-emerald-200">Each member gets a <b>portal login account</b> automatically. They can log in with: <code class="text-emerald-300 bg-gray-200/50 px-1.5 py-0.5 rounded">Client ID + Login ID + Password</code> and upload their payment receipts.</p>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Total Members</div><div class="text-3xl font-bold text-textMain" id="stat-total">-</div></div>
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Active</div><div class="text-3xl font-bold text-emerald-400" id="stat-active">-</div></div>
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">Inactive</div><div class="text-3xl font-bold text-red-400" id="stat-inactive">-</div></div>
        <div class="stat-card"><div class="text-textSec text-xs uppercase tracking-wider mb-2">With Login</div><div class="text-3xl font-bold text-indigo-400" id="stat-linked">-</div></div>
    </div>

    <!-- Search -->
    <div class="mb-4 flex flex-col sm:flex-row gap-2">
        <input type="text" id="searchInput" placeholder="Search name, flat, code..." class="flex-1 text-sm" oninput="filterMembers()">
        <select id="statusFilter" class="text-sm sm:w-32" onchange="filterMembers()">
            <option value="all" selected>All</option><option value="active">Active</option><option value="inactive">Inactive</option>
        </select>
    </div>

    <!-- Members table -->
    <div class="glass-panel rounded-xl overflow-hidden">
        <div class="table-responsive">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-border bg-surfaceLight/50">
                        <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Code</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Member</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Flat</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider hidden md:table-cell">Contact</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Monthly ₹</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider hidden lg:table-cell">Login ID</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Status</th>
                        <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="membersBody">
                    <tr><td colspan="8" class="p-8 text-center text-textSec">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="memberModal" class="fixed inset-0 z-[100] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
        <div class="p-5 border-b border-border flex justify-between items-center shrink-0 bg-surfaceLight">
            <h2 class="font-bold text-lg text-textMain" id="modalTitle">Add Member</h2>
            <button onclick="closeModal()" class="text-textSec hover:text-textMain transition bg-textMain hover:bg-gray-700 w-8 h-8 rounded-full flex items-center justify-center"><i class="fas fa-times"></i></button>
        </div>
        <form id="memberForm" class="p-5 overflow-y-auto flex-1 space-y-4 bg-surface">
            <input type="hidden" id="editId" value="">
            <input type="hidden" id="editRecordType" value="member">
            <input type="hidden" id="editEmployeeId" value="">

            <!-- Account section (only for add) -->
            <div id="accountSection">
                <div class="text-xs uppercase text-emerald-400 font-bold mb-3 flex items-center gap-2">
                    <i class="fas fa-key"></i> Portal Login Account
                </div>

                <!-- Role selector -->
                <div class="mb-4">
                    <label class="block text-xs uppercase text-textSec font-bold mb-2">Account Role *</label>
                    <div class="flex gap-2">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="f_account_role" id="role_member" value="MEMBER" class="hidden" checked onchange="onRoleChange()">
                            <div class="role-btn text-center py-2.5 px-3 rounded-lg border border-border text-sm font-bold text-textSec hover:border-emerald-500/60 transition" id="role_member_btn">
                                <i class="fas fa-user mr-1.5"></i> Member
                            </div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="f_account_role" id="role_admin" value="ADMINISTRATOR" class="hidden" onchange="onRoleChange()">
                            <div class="role-btn text-center py-2.5 px-3 rounded-lg border border-border text-sm font-bold text-textSec hover:border-indigo-500/60 transition" id="role_admin_btn">
                                <i class="fas fa-shield-halved mr-1.5"></i> Administrator
                            </div>
                        </label>
                    </div>
                    <p class="text-xs text-textSec mt-1.5" id="roleHintText">Member can view dues and upload receipts.</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase text-textSec font-bold mb-1">Login ID *</label>
                        <input type="text" id="f_login_id" class="w-full text-sm" placeholder="e.g. flat101, john.doe" required>
                        <p class="text-xs text-textSec mt-1">Used to log in to portal</p>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-textSec font-bold mb-1" id="passwordLabel">Password *</label>
                        <input type="text" id="f_password" class="w-full text-sm" placeholder="Min 4 chars" required>
                        <p class="text-xs text-textSec mt-1" id="passwordHint">Set initial password</p>
                    </div>
                </div>
                <div class="border-t border-border my-4"></div>
            </div>

            <div class="text-xs uppercase text-textSec font-bold mb-3 flex items-center gap-2" id="detailsSectionLabel">
                <i class="fas fa-user"></i> Member Details
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Full Name *</label><input type="text" id="f_full_name" class="w-full text-sm" required></div>
                <div id="flatField"><label class="block text-xs uppercase text-textSec font-bold mb-1">Flat / Unit *</label><input type="text" id="f_flat_number" class="w-full text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Email</label><input type="email" id="f_email" class="w-full text-sm"></div>
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Mobile</label><input type="tel" id="f_mobile" class="w-full text-sm"></div>
            </div>
            <!-- Member-only billing fields — hidden for Administrator role -->
            <div id="billingFields">
                <div class="grid grid-cols-2 gap-4">
                    <div id="flatField2"><?php /* flatField is on Name row, this is for billing section */ ?></div>
                    <?php if ($sqftRate > 0): ?>
                    <div>
                        <label class="block text-xs uppercase text-textSec font-bold mb-1">Plot Size (Sq.Ft) * <span id="rateMapActiveBadge" class="hidden text-xs text-emerald-400 ml-1 normal-case font-normal"><i class="fas fa-table mr-1"></i>Rate Map active</span></label>
                        <input type="number" id="f_plot_size_sqft" class="w-full text-sm" step="1" min="0"
                               placeholder="e.g. 1200" oninput="scheduleCalcMember()">

                    </div>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase text-textSec font-bold mb-1">Monthly Amount &#8377; *</label>
                        <input type="number" id="f_monthly_amount" class="w-full text-sm" step="0.01" min="0"
                               <?= $sqftRate > 0 ? 'placeholder="Auto from plot size" style="color:#6ee7b7"' : 'placeholder="e.g. 2500"' ?>>
                        <p id="f_amount_hint" class="text-xs text-textSec mt-1"></p>
                    </div>
                    <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Due Day (1&#8211;28)</label><input type="number" id="f_due_day" class="w-full text-sm" min="1" max="28" value="10"></div>
                </div>
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Notes</label><textarea id="f_notes" class="w-full text-sm" rows="2"></textarea></div>
            </div>
            <!-- Optional member record for Administrator role -->
            <div id="adminMemberSection" style="display:none">
                <div class="border-t border-border my-3"></div>
                <div class="text-xs uppercase text-indigo-400 font-bold mb-3 flex items-center gap-2">
                    <i class="fas fa-home"></i> Resident Details (Optional)
                </div>
                <p class="text-xs text-textSec mb-3">Fill this if the administrator also lives in the society and needs to manage their own dues &amp; complaints.</p>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Flat / Unit</label><input type="text" id="af_flat_number" class="w-full text-sm" placeholder="e.g. A-101"></div>
                    <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Monthly Amount &#8377;</label><input type="number" id="af_monthly_amount" class="w-full text-sm" step="0.01" min="0" placeholder="e.g. 2500"></div>
                </div>
                <div class="grid grid-cols-2 gap-4 mt-3">
                    <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Plot Size (sqft)</label><input type="number" id="af_plot_sqft" class="w-full text-sm" step="1" min="0" placeholder="e.g. 1200" onchange="lookupAdminRate()"></div>
                    <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Due Day</label><input type="number" id="af_due_day" class="w-full text-sm" min="1" max="28" value="10"></div>
                </div>
            </div>
        </form>
        <div class="p-4 border-t border-border shrink-0 bg-surfaceLight flex justify-end gap-3">
            <button onclick="closeModal()" class="btn-ghost">Cancel</button>
            <button onclick="saveMember()" class="btn-primary" id="saveBtn">Save Member</button>
        </div>
    </div>
</div>

<!-- Member Detail Modal -->
<div id="memberDetailModal" class="fixed inset-0 z-[200] hidden bg-gray-500/60 backdrop-blur-sm flex flex-col">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-4xl mx-auto my-6 shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">

        <!-- Header bar -->
        <div class="p-5 border-b border-border flex justify-between items-center shrink-0 bg-surfaceLight">
            <div class="flex items-center gap-3 min-w-0">
                <span id="mdm-member-name" class="font-bold text-lg text-textMain truncate">—</span>
                <span id="mdm-status-badge" class="badge"></span>
            </div>
            <button onclick="closeDetailModal()" class="text-textSec hover:text-textMain transition bg-textMain hover:bg-gray-700 w-8 h-8 rounded-full flex items-center justify-center shrink-0 ml-3" title="Close (Esc)">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Error banner -->
        <div id="mdm-error-banner" class="hidden mx-5 mt-4 p-3 rounded-lg bg-red-900/20 border border-red-800/50 flex items-center gap-3">
            <i class="fas fa-exclamation-circle text-red-400"></i>
            <span id="mdm-error-message" class="text-sm text-red-300 flex-1">An error occurred.</span>
            <button id="mdm-retry-btn" onclick="mdmRetry()" class="text-xs text-red-300 hover:text-textMain border border-red-700 hover:border-red-500 px-3 py-1 rounded-lg transition shrink-0">
                <i class="fas fa-redo mr-1"></i>Retry
            </button>
        </div>

        <!-- Scrollable body -->
        <div class="flex-1 overflow-y-auto p-5 space-y-5">

            <!-- Loading spinner (shown while fetching) -->
            <div id="mdm-loading" class="hidden flex items-center justify-center py-16">
                <i class="fas fa-spinner fa-spin text-3xl text-indigo-400"></i>
            </div>

            <!-- Basic Info section -->
            <section id="mdm-basic-info" class="glass-panel rounded-xl p-4">
                <div class="text-xs uppercase text-textSec font-bold mb-3 flex items-center gap-2">
                    <i class="fas fa-user"></i> Basic Information
                </div>
                <!-- Populated by renderMemberDetail() -->
            </section>

            <!-- Due / Overdue Summary bar -->
            <div id="mdm-summary-bar" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Populated by renderMemberDetail() -->
            </div>

            <!-- Payment History section -->
            <section id="mdm-payment-history" class="glass-panel rounded-xl overflow-hidden">
                <div class="p-4 border-b border-border">
                    <div class="text-xs uppercase text-textSec font-bold flex items-center gap-2">
                        <i class="fas fa-receipt"></i> Payment History
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="mdm-bills-table" class="w-full text-left">
                        <thead>
                            <tr class="border-b border-border bg-surfaceLight/50">
                                <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Billing Month</th>
                                <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Amount Due</th>
                                <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Total Due</th>
                                <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Status</th>
                                <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Receipt</th>
                                <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="mdm-bills-body">
                            <tr><td colspan="6" class="p-8 text-center text-textSec">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div><!-- end scrollable body -->

        <!-- Actions footer -->
        <div id="mdm-actions-footer" class="p-4 border-t border-border shrink-0 bg-surfaceLight flex flex-wrap gap-3 items-center">
            <!-- Populated by renderMemberDetail() -->
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>

<script>
const _memberSqftRate = <?= (float)$sqftRate ?>;

let _calcDebounce = null;
function scheduleCalcMember() {
    clearTimeout(_calcDebounce);
    _calcDebounce = setTimeout(calcMemberAmount, 500);
}

async function calcMemberAmount() {
    const sqft = parseFloat(document.getElementById('f_plot_size_sqft')?.value) || 0;
    const amountEl = document.getElementById('f_monthly_amount');
    const hintEl   = document.getElementById('f_amount_hint');
    if (!amountEl) return;
    if (sqft <= 0) { if (hintEl) hintEl.textContent = ''; return; }

    // Try cached rate map first (keys are stored as numbers via parseFloat in prefetch)
    if (_rateMapEntries !== null) {
        const mapped = _rateMapEntries[sqft];
        if (mapped !== undefined) {
            amountEl.value = parseFloat(mapped).toFixed(2);
            if (hintEl) hintEl.textContent = 'From rate map';
            return;
        }
        // Cache loaded but no exact match — confirm via API lookup
        // (don't silently fall to legacy when a rate map is active)
    }

    // Per-lookup API call
    try {
        const res = await fetch('../rate_map_api.php?action=lookup&plot_size_sqft=' + sqft);
        const d = await res.json();
        if (d.found) {
            amountEl.value = parseFloat(d.monthly_amount).toFixed(2);
            if (hintEl) hintEl.textContent = 'From rate map';
        } else if (_rateMapActive) {
            // Rate map is active but no entry for this size — show hint, don't use legacy
            amountEl.value = '';
            if (hintEl) hintEl.textContent = `No rate map entry for ${sqft} sqft — enter manually`;
        } else {
            applyLegacyRate(sqft, hintEl, amountEl);
        }
    } catch (e) {
        if (hintEl) hintEl.textContent = 'Rate lookup failed — enter amount manually';
        amountEl.value = '';
    }
}

function applyLegacyRate(sqft, hintEl, amountEl) {
    if (_memberSqftRate > 0) {
        amountEl.value = (sqft * _memberSqftRate).toFixed(2);
        if (hintEl) hintEl.textContent = `Calculated: ${sqft} sqft × ₹${_memberSqftRate}/sqft`;
    } else {
        amountEl.value = '';
        if (hintEl) hintEl.textContent = '';
    }
}

let allMembers = [];
let _rateMapEntries = null;
let _rateMapActive  = false;
let _currentMemberId = null;
let _mdmLastData = null;
let _uploadInProgress = false;

async function loadMembers() {
    try {
        const res = await fetch('../maintenance_members_api.php?action=list');
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        allMembers = data.members || [];
        updateStats();
        filterMembers();
    } catch (e) {
        showAlert('Failed to load members: ' + e.message, 'error');
    }
}

function updateStats() {
    const active = allMembers.filter(m => m.status === 'active').length;
    const inactive = allMembers.filter(m => m.status === 'inactive').length;
    const linked = allMembers.filter(m => m.employee_id).length;
    document.getElementById('stat-total').textContent = allMembers.length;
    document.getElementById('stat-active').textContent = active;
    document.getElementById('stat-inactive').textContent = inactive;
    document.getElementById('stat-linked').textContent = linked;
}

function filterMembers() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    let filtered = allMembers;
    if (status !== 'all') filtered = filtered.filter(m => m.status === status);
    if (search) filtered = filtered.filter(m =>
        (m.full_name || '').toLowerCase().includes(search) ||
        (m.flat_number || '').toLowerCase().includes(search) ||
        (m.member_code || '').toLowerCase().includes(search)
    );
    renderMembers(filtered);
}

function renderMembers(members) {
    const tbody = document.getElementById('membersBody');
    if (!members.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-textSec">No members found</td></tr>';
        return;
    }
    tbody.innerHTML = members.map(m => {
        const isAdmin = m.record_type === 'admin';
        const statusBadge = m.status === 'active'
            ? '<span class="badge bg-green-900/20 border border-green-800/50 text-green-400">Active</span>'
            : '<span class="badge bg-red-900/20 border border-red-800/50 text-red-400">Inactive</span>';
        const roleBadge = isAdmin
            ? '<span class="badge bg-indigo-900/20 border border-indigo-800/50 text-indigo-300 text-xs"><i class="fas fa-shield-halved mr-1"></i>Admin</span>'
            : '';
        const loginId = m.employee_id
            ? `<span class="text-xs text-indigo-400 font-mono"><i class="fas fa-user-check mr-1"></i>${m.employee_id}</span>`
            : '<span class="text-xs text-textSec">—</span>';
        const flatCell    = isAdmin ? '<span class="text-xs text-textSec italic">Administrator</span>' : m.flat_number;
        const amountCell  = isAdmin ? '<span class="text-xs text-textSec">—</span>' : `<span class="text-emerald-400 font-bold">₹${parseFloat(m.monthly_amount).toLocaleString()}</span>`;
        const actions = m.status === 'active'
            ? `<button onclick="event.stopPropagation();editMember(${m.id},'${m.record_type}')" class="text-xs text-indigo-400 hover:text-textMain mr-2" title="Edit"><i class="fas fa-pen"></i></button>
               <button onclick="event.stopPropagation();deactivateMember(${m.id},'${(m.full_name||'').replace(/'/g,"\\'")}',${'\''+(m.record_type||'member')+'\''},'${m.employee_id||''}')" class="text-xs text-red-400 hover:text-textMain" title="Deactivate"><i class="fas fa-ban"></i></button>`
            : `<button onclick="event.stopPropagation();reactivateMember(${m.id},'${m.record_type||'member'}','${m.employee_id||''}')" class="text-xs text-green-400 hover:text-textMain"><i class="fas fa-check mr-1"></i>Reactivate</button>`;
        return `<tr class="border-b border-border/50 hover:bg-gray-700/40 cursor-pointer" data-member-id="${m.id}" data-record-type="${m.record_type}" onclick="openMemberDetail(${m.id}, '${m.record_type}')">
            <td class="p-4 font-mono text-xs text-textSec">${m.member_code || '—'} ${roleBadge}</td>
            <td class="p-4"><div class="font-bold text-textMain text-sm">${m.full_name}</div></td>
            <td class="p-4 text-sm text-textSec">${flatCell}</td>
            <td class="p-4 hidden md:table-cell"><div class="text-xs text-textSec">${m.email || '—'}</div><div class="text-xs text-textSec">${m.mobile || ''}</div></td>
            <td class="p-4 text-sm">${amountCell}</td>
            <td class="p-4 hidden lg:table-cell">${loginId}</td>
            <td class="p-4">${statusBadge}</td>
            <td class="p-4">${actions}</td>
        </tr>`;
    }).join('');
}

function onRoleChange() {
    const isAdmin = document.getElementById('role_admin').checked;
    // Visual toggle on role buttons
    document.getElementById('role_member_btn').classList.toggle('border-emerald-500', !isAdmin);
    document.getElementById('role_member_btn').classList.toggle('text-emerald-400', !isAdmin);
    document.getElementById('role_member_btn').classList.toggle('border-border', isAdmin);
    document.getElementById('role_member_btn').classList.toggle('text-textSec', isAdmin);
    document.getElementById('role_admin_btn').classList.toggle('border-indigo-500', isAdmin);
    document.getElementById('role_admin_btn').classList.toggle('text-indigo-400', isAdmin);
    document.getElementById('role_admin_btn').classList.toggle('border-border', !isAdmin);
    document.getElementById('role_admin_btn').classList.toggle('text-textSec', !isAdmin);
    // Show/hide billing fields
    document.getElementById('billingFields').style.display = isAdmin ? 'none' : '';
    document.getElementById('flatField').style.display = isAdmin ? 'none' : '';
    document.getElementById('adminMemberSection').style.display = isAdmin ? '' : 'none';
    document.getElementById('f_flat_number').required = !isAdmin;
    document.getElementById('f_monthly_amount').required = !isAdmin;
    document.getElementById('detailsSectionLabel').innerHTML =
        isAdmin ? '<i class="fas fa-shield-halved"></i> Administrator Details'
                : '<i class="fas fa-user"></i> Member Details';
    document.getElementById('roleHintText').textContent =
        isAdmin ? 'Administrator has full portal access (SuperAdmin).'
                : 'Member can view dues and upload receipts.';
    document.getElementById('saveBtn').textContent = isAdmin ? 'Save Administrator' : 'Save Member';
}

async function lookupAdminRate() {
    const sqft = parseFloat(document.getElementById('af_plot_sqft')?.value) || 0;
    if (!sqft) return;
    try {
        const res = await fetch('../rate_map_api.php?action=lookup&plot_size_sqft=' + sqft);
        const d   = await res.json();
        if (d.found && d.monthly_amount) {
            document.getElementById('af_monthly_amount').value = d.monthly_amount;
        }
    } catch(e) {}
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Member / Admin';
    document.getElementById('editId').value = '';
    document.getElementById('memberForm').reset();
    document.getElementById('f_due_day').value = '10';
    // Reset role to Member
    document.getElementById('role_member').checked = true;
    onRoleChange();
    // Show account section, login_id editable, password required
    document.getElementById('accountSection').style.display = '';
    document.getElementById('f_login_id').disabled = false;
    document.getElementById('f_login_id').required = true;
    document.getElementById('f_password').required = true;
    document.getElementById('f_password').placeholder = 'Min 4 chars';
    document.getElementById('passwordLabel').textContent = 'Password *';
    document.getElementById('passwordHint').textContent = 'Set initial password';
    document.getElementById('memberModal').classList.remove('hidden');
}

function editMember(id, recordType) {
    const m = allMembers.find(x => x.id == id);
    if (!m) return;
    const isAdmin = (recordType === 'admin') || (m.record_type === 'admin');

    document.getElementById('editId').value = m.id;
    document.getElementById('editRecordType').value = isAdmin ? 'admin' : 'member';
    document.getElementById('editEmployeeId').value = m.employee_id || '';

    document.getElementById('modalTitle').textContent = isAdmin ? 'Edit Administrator' : 'Edit Member';
    document.getElementById('f_full_name').value  = m.full_name;
    document.getElementById('f_email').value       = m.email || '';
    document.getElementById('f_mobile').value      = m.mobile || '';
    document.getElementById('f_password').value    = '';
    document.getElementById('f_password').required = false;
    document.getElementById('f_password').placeholder = 'Leave blank to keep current';
    document.getElementById('passwordLabel').textContent  = 'New Password';
    document.getElementById('passwordHint').textContent   = 'Leave blank to keep unchanged';

    // Show/hide account section & role selector
    document.getElementById('accountSection').style.display = '';
    document.getElementById('f_login_id').value    = m.employee_id || '';
    document.getElementById('f_login_id').disabled = true;
    document.getElementById('f_login_id').required = false;

    if (isAdmin) {
        // Hide member-only fields
        document.getElementById('billingFields').style.display = 'none';
        document.getElementById('flatField').style.display     = 'none';
        document.getElementById('f_flat_number').required      = false;
        document.getElementById('f_monthly_amount').required   = false;
        document.getElementById('detailsSectionLabel').innerHTML = '<i class="fas fa-shield-halved"></i> Administrator Details';
        document.getElementById('roleHintText').textContent    = 'Administrator has full portal access.';
    } else {
        document.getElementById('billingFields').style.display = '';
        document.getElementById('flatField').style.display     = '';
        document.getElementById('f_flat_number').required      = true;
        document.getElementById('f_monthly_amount').required   = true;
        document.getElementById('f_flat_number').value         = m.flat_number;
        document.getElementById('f_monthly_amount').value      = m.monthly_amount;
        document.getElementById('f_due_day').value             = m.due_day || 10;
        document.getElementById('f_notes').value               = m.notes || '';
        document.getElementById('detailsSectionLabel').innerHTML = '<i class="fas fa-user"></i> Member Details';
        document.getElementById('roleHintText').textContent    = 'Member can view dues and upload receipts.';
    }
    document.getElementById('memberModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('memberModal').classList.add('hidden');
}

async function saveMember() {
    const editId     = document.getElementById('editId').value;
    const recordType = document.getElementById('editRecordType').value;
    const employeeId = document.getElementById('editEmployeeId').value;
    const isAdminEdit = (recordType === 'admin');
    // For new adds, check if role_admin radio is selected
    const isNewAdmin = !editId && document.getElementById('role_admin')?.checked;

    if (isAdminEdit) {
        // Admin edit — only name/email/mobile/password
        const fullName = document.getElementById('f_full_name').value.trim();
        const email    = document.getElementById('f_email').value.trim();
        const mobile   = document.getElementById('f_mobile').value.trim();
        const password = document.getElementById('f_password').value;
        if (!fullName) return showAlert('Full name is required', 'warning');
        const btn = document.getElementById('saveBtn');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
        try {
            const res = await fetch('../maintenance_members_api.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action:'edit_admin', employee_id: employeeId, full_name: fullName, email, mobile, password })
            });
            const result = await res.json();
            if (result.error) throw new Error(result.error);
            showAlert('Administrator updated', 'success');
            closeModal();
            loadMembers();
        } catch(e) { showAlert(e.message, 'error'); }
        btn.disabled = false; btn.innerHTML = 'Save Member';
        return;
    }

    const data = {
        action: editId ? 'edit' : 'add',
        id: editId || undefined,
        account_role: isNewAdmin ? 'ADMINISTRATOR' : 'MEMBER',
        login_id: document.getElementById('f_login_id').value,
        password: document.getElementById('f_password').value,
        full_name: document.getElementById('f_full_name').value,
        flat_number: isNewAdmin
            ? (document.getElementById('af_flat_number')?.value?.trim() || '')
            : document.getElementById('f_flat_number').value,
        email: document.getElementById('f_email').value,
        mobile: document.getElementById('f_mobile').value,
        plot_size_sqft: isNewAdmin ? 0 : (parseFloat(document.getElementById('f_plot_size_sqft')?.value) || 0),
        monthly_amount: isNewAdmin
            ? (parseFloat(document.getElementById('af_monthly_amount')?.value) || 0)
            : document.getElementById('f_monthly_amount').value,
        due_day: isNewAdmin
            ? (parseInt(document.getElementById('af_due_day')?.value) || 10)
            : document.getElementById('f_due_day').value,
        notes: isNewAdmin ? '' : document.getElementById('f_notes').value,
    };

    // Validation
    if (!data.full_name) return showAlert('Full name is required', 'warning');
    if (!isNewAdmin && (!data.flat_number || !data.monthly_amount)) {
        return showAlert('Flat and Monthly Amount are required for members', 'warning');
    }
    if (!editId) {
        if (!data.login_id || data.login_id.length < 3) return showAlert('Login ID must be at least 3 characters', 'warning');
        if (!data.password || data.password.length < 4) return showAlert('Password must be at least 4 characters', 'warning');
    }

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

    try {
        const res = await fetch('../maintenance_members_api.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.error) throw new Error(result.error);

        if (editId) {
            showAlert('Member updated', 'success');
        } else {
            showAlert(`Member created!\nLogin ID: ${result.login_id}\nCode: ${result.member_code}\n\nShare the Client ID + Login ID + Password with the member so they can log in.`, 'success');
        }
        closeModal();
        loadMembers();
    } catch (e) {
        showAlert(e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Save Member';
    }
}

async function deactivateMember(id, name, recordType, empId) {
    if (!await showConfirm(`Deactivate "${name}"? Their portal login will also be disabled.`)) return;
    try {
        const action = (recordType === 'admin') ? 'deactivate_admin' : 'deactivate';
        const body   = (recordType === 'admin')
            ? { action, employee_id: empId }
            : { action, id };
        const res = await fetch('../maintenance_members_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)
        });
        const r = await res.json();
        if (r.error) throw new Error(r.error);
        showAlert('Account deactivated', 'success');
        loadMembers();
    } catch (e) { showAlert(e.message, 'error'); }
}

async function reactivateMember(id, recordType, empId) {
    try {
        const action = (recordType === 'admin') ? 'reactivate_admin' : 'reactivate';
        const body   = (recordType === 'admin')
            ? { action, employee_id: empId }
            : { action, id };
        const res = await fetch('../maintenance_members_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)
        });
        const r = await res.json();
        if (r.error) throw new Error(r.error);
        showAlert('Account reactivated', 'success');
        loadMembers();
    } catch (e) { showAlert(e.message, 'error'); }
}

// Close modal on outside click
document.getElementById('memberModal').addEventListener('click', e => { if (e.target.id === 'memberModal') closeModal(); });

loadMembers();

async function getRegLink() {
    const btn = document.getElementById('regLinkBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating...';
    try {
        const resp = await fetch('../member_register_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'get_link' })
        });
        const d = await resp.json();
        if (!d.success) throw new Error(d.error);
        await navigator.clipboard.writeText(d.link);
        showAlert('Registration link copied to clipboard! Share it with potential members.', 'success');
    } catch (e) {
        // Fallback: show link in prompt
        try {
            const resp = await fetch('../member_register_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'get_link' })
            });
            const d = await resp.json();
            if (d.link) prompt('Copy this registration link:', d.link);
            else showAlert(e.message, 'error');
        } catch (e2) { showAlert(e2.message, 'error'); }
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-link mr-2"></i>Get Registration Link';
}

// ── Member Detail Modal Functions ──

async function openMemberDetail(memberId) {
    _currentMemberId = memberId;
    const modal = document.getElementById('memberDetailModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    // hide error, show loading
    document.getElementById('mdm-error-banner').classList.add('hidden');
    document.getElementById('mdm-loading').classList.remove('hidden');
    document.getElementById('mdm-basic-info').innerHTML = '<div class="text-xs uppercase text-textSec font-bold mb-3 flex items-center gap-2"><i class="fas fa-user"></i> Basic Information</div>';
    document.getElementById('mdm-summary-bar').innerHTML = '';
    document.getElementById('mdm-bills-body').innerHTML = '<tr><td colspan="6" class="p-8 text-center text-textSec">Loading...</td></tr>';
    document.getElementById('mdm-actions-footer').innerHTML = '';
    try {
        const res = await fetch('../maintenance_members_api.php?action=get_member_detail&member_id=' + memberId);
        if (!res.ok) throw new Error('Server error ' + res.status);
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        document.getElementById('mdm-loading').classList.add('hidden');
        renderMemberDetail(data);
    } catch (e) {
        document.getElementById('mdm-loading').classList.add('hidden');
        document.getElementById('mdm-error-message').textContent = 'Failed to load member details: ' + e.message;
        document.getElementById('mdm-error-banner').classList.remove('hidden');
    }
}

function mdmRetry() {
    if (_currentMemberId) openMemberDetail(_currentMemberId);
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderMemberDetail(data) {
    _mdmLastData = data;
    const m = data.member;
    const s = data.summary;
    const bills = data.bills || [];

    // Header
    document.getElementById('mdm-member-name').textContent = m.full_name || '—';
    const statusBadge = document.getElementById('mdm-status-badge');
    if (m.status === 'active') {
        statusBadge.className = 'badge bg-green-900/20 border border-green-800/50 text-green-400';
        statusBadge.textContent = 'Active';
    } else {
        statusBadge.className = 'badge bg-textMain/20 border border-border/50 text-textSec';
        statusBadge.textContent = 'Inactive';
    }

    // Basic Info section
    const isAdmin = m.record_type === 'admin';
    let infoHtml = '<div class="text-xs uppercase text-textSec font-bold mb-3 flex items-center gap-2"><i class="fas fa-user"></i> Basic Information</div>';
    infoHtml += '<div class="grid grid-cols-2 gap-3 text-sm">';
    infoHtml += `<div><span class="text-textSec text-xs uppercase">Full Name</span><div class="text-textMain font-medium mt-0.5">${escHtml(m.full_name || '—')}</div></div>`;
    if (isAdmin) {
        infoHtml += `<div><span class="text-xs"><span class="badge bg-indigo-900/20 border border-indigo-800/50 text-indigo-300"><i class="fas fa-shield-halved mr-1"></i>Administrator</span></span></div>`;
    } else {
        infoHtml += `<div><span class="text-textSec text-xs uppercase">Flat / Unit</span><div class="text-textMain mt-0.5">${escHtml(m.flat_number || '—')}</div></div>`;
        infoHtml += `<div><span class="text-textSec text-xs uppercase">Member Code</span><div class="text-textMain font-mono mt-0.5">${escHtml(m.member_code || '—')}</div></div>`;
    }
    infoHtml += `<div><span class="text-textSec text-xs uppercase">Login ID</span><div class="text-indigo-400 font-mono mt-0.5">${escHtml(m.employee_id || '—')}</div></div>`;
    infoHtml += `<div><span class="text-textSec text-xs uppercase">Client ID</span><div class="text-textMain font-mono mt-0.5">${escHtml(m.client_id || '—')}</div></div>`;
    infoHtml += '</div>';
    // Contact
    infoHtml += '<div class="mt-3 pt-3 border-t border-border text-sm">';
    if (m.email || m.mobile) {
        if (m.email) infoHtml += `<div class="text-textSec"><i class="fas fa-envelope mr-2 text-textSec"></i>${escHtml(m.email)}</div>`;
        if (m.mobile) infoHtml += `<div class="text-textSec mt-1"><i class="fas fa-phone mr-2 text-textSec"></i>${escHtml(m.mobile)}</div>`;
    } else {
        infoHtml += '<div class="text-textSec italic">No contact information available</div>';
    }
    infoHtml += '</div>';
    document.getElementById('mdm-basic-info').innerHTML = infoHtml;

    // render summary, bills, actions
    renderMdmSummary(s);
    renderMdmBills(bills, m);
    renderMdmActions(m, s, bills);
}

function renderMdmSummary(s) {
    const bar = document.getElementById('mdm-summary-bar');
    const outstanding = parseFloat(s.total_outstanding || 0);
    const partialTotal = parseFloat(s.partial_total || 0);
    const pendingCount = (parseInt(s.pending_count||0) + parseInt(s.overdue_count||0));
    const partialCount = parseInt(s.partial_count || 0);

    if (outstanding === 0 && partialTotal === 0) {
        bar.innerHTML = '<div class="sm:col-span-3 glass-panel rounded-xl p-4 border border-green-900/50 bg-green-900/10 flex items-center gap-3"><i class="fas fa-check-circle text-green-400 text-xl"></i><span class="text-green-300 font-medium">All dues cleared</span></div>';
        return;
    }
    let html = '';
    html += `<div class="glass-panel rounded-xl p-4 border border-red-900/50 bg-red-900/10">
        <div class="text-xs uppercase text-red-400 font-bold mb-1">Outstanding</div>
        <div class="text-2xl font-bold text-red-300">₹${parseFloat(outstanding).toLocaleString('en-IN', {minimumFractionDigits:2})}</div>
        <div class="text-xs text-red-500 mt-1">${pendingCount} bill${pendingCount!==1?'s':''} pending/overdue</div>
    </div>`;
    if (partialTotal > 0) {
        html += `<div class="glass-panel rounded-xl p-4 border border-amber-900/50 bg-amber-900/10">
            <div class="text-xs uppercase text-amber-400 font-bold mb-1">Partial</div>
            <div class="text-2xl font-bold text-amber-300">₹${parseFloat(partialTotal).toLocaleString('en-IN', {minimumFractionDigits:2})}</div>
            <div class="text-xs text-amber-500 mt-1">${partialCount} partial payment${partialCount!==1?'s':''}</div>
        </div>`;
    } else {
        html += '<div></div>';
    }
    html += '<div></div>';
    bar.innerHTML = html;
}

function renderMdmBills(bills, member) {
    const tbody = document.getElementById('mdm-bills-body');
    if (!bills.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-textSec">No payment history</td></tr>';
        return;
    }
    const statusColors = {
        'PENDING':  'bg-yellow-900/20 border border-yellow-800/50 text-yellow-400',
        'OVERDUE':  'bg-red-900/20 border border-red-800/50 text-red-400',
        'PARTIAL':  'bg-amber-900/20 border border-amber-800/50 text-amber-400',
        'VERIFIED': 'bg-green-900/20 border border-green-800/50 text-green-400',
        'PAID':     'bg-green-900/20 border border-green-800/50 text-green-400',
    };
    tbody.innerHTML = bills.map(bill => {
        const d = new Date(bill.billing_month);
        const monthLabel = d.toLocaleString('en-IN', {month:'short', year:'numeric'});
        const sc = statusColors[bill.status] || 'bg-textMain/20 border border-border/50 text-textSec';
        const statusBadge = `<span class="badge ${sc}">${bill.status}</span>`;
        const amtDue = `₹${parseFloat(bill.amount_due).toLocaleString('en-IN', {minimumFractionDigits:2})}`;
        const totalDue = `₹${parseFloat(bill.total_due).toLocaleString('en-IN', {minimumFractionDigits:2})}`;

        let receiptCell = '<span class="text-textSec text-xs">No payment recorded</span>';
        let actionsCell = '';
        if (bill.latest_receipt) {
            const r = bill.latest_receipt;
            const pd = r.payment_date ? new Date(r.payment_date).toLocaleDateString('en-IN') : '—';
            const amt = r.receipt_amount ? `₹${parseFloat(r.receipt_amount).toLocaleString('en-IN', {minimumFractionDigits:2})}` : '—';
            receiptCell = `<div class="text-xs text-textSec">${pd}</div><div class="text-xs text-emerald-400">${amt}</div>`;
            if (r.review_status === 'VERIFIED' && r.id) {
                receiptCell += `<a href="maintenance_serve_file.php?receipt_id=${r.id}&download=1" target="_blank" class="text-xs text-indigo-400 hover:text-indigo-300 mt-1 inline-block"><i class="fas fa-download mr-1"></i>Download</a>`;
            }
            if (r.review_status === 'PENDING') {
                actionsCell = `<button class="btn-verify-receipt text-xs text-yellow-400 hover:text-textMain border border-yellow-800/50 hover:border-yellow-500 px-2 py-1 rounded transition" data-receipt-id="${r.id}" onclick="mdmVerifyReceipt(this, ${r.id}, ${bill.id})"><i class="fas fa-check mr-1"></i>Verify</button>`;
            }
        }
        return `<tr class="border-b border-border/50 hover:bg-textMain/20">
            <td class="p-3 text-sm text-textSec">${monthLabel}</td>
            <td class="p-3 text-sm text-textSec">${amtDue}</td>
            <td class="p-3 text-sm text-textMain font-medium">${totalDue}</td>
            <td class="p-3">${statusBadge}</td>
            <td class="p-3">${receiptCell}</td>
            <td class="p-3">${actionsCell}</td>
        </tr>`;
    }).join('');
}

async function mdmVerifyReceipt(btn, receiptId, billId) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Verifying...';
    try {
        const res = await fetch('../maintenance_review_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'verify', receipt_id: receiptId})
        });
        const data = await res.json();
        if (!res.ok || data.error || !data.success) throw new Error(data.error || data.message || 'Verification failed');
        // Refresh modal
        openMemberDetail(_currentMemberId);
    } catch (e) {
        // Show error inline next to button, restore button
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-1"></i>Verify';
        // Insert error span after button if not already there
        const errId = 'verr-' + receiptId;
        if (!document.getElementById(errId)) {
            const errSpan = document.createElement('span');
            errSpan.id = errId;
            errSpan.className = 'text-xs text-red-400 ml-2';
            errSpan.textContent = e.message;
            btn.parentNode.insertBefore(errSpan, btn.nextSibling);
        }
    }
}

function renderMdmActions(member, summary, bills) {
    const footer = document.getElementById('mdm-actions-footer');
    const hasEmail = !!(member.email && member.email.trim());
    const hasPendingOverdue = bills.some(b => b.status === 'PENDING' || b.status === 'OVERDUE');

    // Send Reminder button
    let reminderBtn = '';
    if (!hasEmail) {
        reminderBtn = `<button disabled title="No email address on file" class="btn-ghost opacity-50 cursor-not-allowed text-sm relative group">
            <i class="fas fa-bell mr-2"></i>Send Reminder
            <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 text-xs bg-textMain text-textSec rounded whitespace-nowrap hidden group-hover:block">No email address on file</span>
        </button>`;
    } else if (hasPendingOverdue) {
        reminderBtn = `<button onclick="mdmSendReminder(${member.id})" class="btn-ghost text-sm" id="mdm-reminder-btn">
            <i class="fas fa-bell mr-2"></i>Send Reminder
        </button>`;
    } else {
        reminderBtn = `<button disabled class="btn-ghost opacity-50 cursor-not-allowed text-sm" title="No pending/overdue bills">
            <i class="fas fa-bell mr-2"></i>Send Reminder
        </button>`;
    }

    // Upload Receipt button
    const eligibleBills = bills.filter(b => ['PENDING','OVERDUE','PARTIAL'].includes(b.status));
    const uploadBtn = `<button onclick="mdmUploadReceipt(${member.id})" class="btn-primary text-sm" id="mdm-upload-btn">
        <i class="fas fa-upload mr-2"></i>Upload Receipt
    </button>`;

    footer.innerHTML = `<div class="flex flex-wrap gap-3 items-center w-full">
        ${reminderBtn}
        ${eligibleBills.length > 0 ? uploadBtn : ''}
        <div id="mdm-action-feedback" class="text-sm ml-2"></div>
    </div>`;
}

async function mdmSendReminder(memberId) {
    const btn = document.getElementById('mdm-reminder-btn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...'; }
    const fb = document.getElementById('mdm-action-feedback');
    try {
        const res = await fetch('../maintenance_members_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'send_reminder', member_id: memberId})
        });
        const data = await res.json();
        if (fb) {
            if (data.success) {
                fb.innerHTML = `<span class="text-green-400"><i class="fas fa-check mr-1"></i>${escHtml(data.message || 'Reminder sent')}</span>`;
            } else {
                fb.innerHTML = `<span class="text-red-400"><i class="fas fa-times mr-1"></i>${escHtml(data.message || 'Failed to send')}</span>`;
            }
        }
    } catch (e) {
        if (fb) fb.innerHTML = `<span class="text-red-400"><i class="fas fa-times mr-1"></i>Error: ${escHtml(e.message)}</span>`;
    }
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bell mr-2"></i>Send Reminder'; }
}

function mdmUploadReceipt(memberId) {
    // We need the data from API — re-use _mdmLastData
    const bills = (_mdmLastData && _mdmLastData.bills) ? _mdmLastData.bills.filter(b => ['PENDING','OVERDUE','PARTIAL'].includes(b.status)) : [];

    if (bills.length === 0) {
        showAlert('No eligible bills for receipt upload', 'warning');
        return;
    }

    // Show upload section inside footer
    const footer = document.getElementById('mdm-actions-footer');
    let billOptions = bills.map(b => {
        const d = new Date(b.billing_month);
        const label = d.toLocaleString('en-IN', {month:'short', year:'numeric'});
        return `<option value="${b.id}">${label} — ₹${parseFloat(b.total_due).toLocaleString('en-IN')}</option>`;
    }).join('');

    footer.innerHTML += `<div id="mdm-upload-section" class="w-full mt-3 p-3 glass-panel rounded-xl border border-indigo-900/50">
        <div class="text-xs uppercase text-indigo-400 font-bold mb-2"><i class="fas fa-upload mr-1"></i> Upload Receipt</div>
        ${bills.length > 1 ? `<select id="mdm-bill-select" class="w-full text-sm mb-2">${billOptions}</select>` : `<input type="hidden" id="mdm-bill-select" value="${bills[0].id}">`}
        <div class="grid grid-cols-2 gap-2 mb-2">
            <input type="number" id="mdm-receipt-amount" step="0.01" min="0.01" placeholder="Amount paid ₹" class="text-sm">
            <input type="date" id="mdm-receipt-date" class="text-sm" value="${new Date().toISOString().slice(0,10)}">
        </div>
        <div class="flex gap-2 items-center">
            <input type="file" id="mdm-receipt-file" accept="image/*,application/pdf" class="text-sm flex-1">
            <button onclick="mdmSubmitReceipt(${memberId})" class="btn-primary text-xs px-3 py-1.5">Upload</button>
            <button onclick="document.getElementById('mdm-upload-section').remove()" class="btn-ghost text-xs px-2 py-1.5">Cancel</button>
        </div>
        <div id="mdm-upload-feedback" class="text-xs mt-2"></div>
    </div>`;
}

async function mdmSubmitReceipt(memberId) {
    const billId = document.getElementById('mdm-bill-select')?.value;
    const fileInput = document.getElementById('mdm-receipt-file');
    const amountPaid = parseFloat(document.getElementById('mdm-receipt-amount')?.value) || 0;
    const paymentDate = document.getElementById('mdm-receipt-date')?.value || new Date().toISOString().slice(0,10);
    const fb = document.getElementById('mdm-upload-feedback');
    if (!billId) { if(fb) fb.innerHTML = '<span class="text-red-400">Select a bill first</span>'; return; }
    if (amountPaid <= 0) { if(fb) fb.innerHTML = '<span class="text-red-400">Enter amount paid</span>'; return; }
    if (!fileInput || !fileInput.files[0]) { if(fb) fb.innerHTML = '<span class="text-red-400">Select a file first</span>'; return; }

    _uploadInProgress = true;
    if (fb) fb.innerHTML = '<span class="text-textSec"><i class="fas fa-spinner fa-spin mr-1"></i>Uploading...</span>';

    try {
        const form = new FormData();
        form.append('bill_id', billId);
        form.append('member_id', memberId);
        form.append('amount_paid', amountPaid);
        form.append('payment_date', paymentDate);
        form.append('payment_mode', 'Other');
        form.append('receipt_file', fileInput.files[0]);
        const res = await fetch('../maintenance_upload_api.php', {method: 'POST', body: form});
        const data = await res.json();
        _uploadInProgress = false;
        if (data.success || data.receipt_id) {
            if (fb) fb.innerHTML = '<span class="text-green-400"><i class="fas fa-check mr-1"></i>Receipt uploaded. Awaiting verification.</span>';
            setTimeout(() => openMemberDetail(_currentMemberId), 1500);
        } else {
            if (fb) fb.innerHTML = `<span class="text-red-400"><i class="fas fa-times mr-1"></i>${escHtml(data.error || data.message || 'Upload failed')}</span>`;
        }
    } catch (e) {
        _uploadInProgress = false;
        if (fb) fb.innerHTML = `<span class="text-red-400"><i class="fas fa-times mr-1"></i>Upload error: ${escHtml(e.message)}</span>`;
    }
}

function closeDetailModal() {
    if (_uploadInProgress) {
        const banner = document.getElementById('mdm-error-banner');
        document.getElementById('mdm-error-message').textContent = 'Upload in progress. Please wait until it completes before closing.';
        banner.classList.remove('hidden');
        return;
    }
    document.getElementById('memberDetailModal').classList.add('hidden');
    document.body.style.overflow = '';
    _currentMemberId = null;
}

// Rate map prefetch on page load
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res  = await fetch('../rate_map_api.php?action=list');
        const data = await res.json();
        const rows = data.rows || [];
        _rateMapEntries = Object.fromEntries(rows.map(r => [parseFloat(r.plot_size_sqft), parseFloat(r.monthly_amount)]));
        _rateMapActive  = rows.length > 0;
        if (_rateMapActive) {
            const badge = document.getElementById('rateMapActiveBadge');
            if (badge) badge.classList.remove('hidden');
        }
    } catch (e) {
        console.warn('Rate map prefetch failed; falling back to per-lookup mode.', e);
        _rateMapEntries = null;
    }

    // Escape key and backdrop click to close detail modal
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetailModal(); });
    document.getElementById('memberDetailModal').addEventListener('click', function(e) {
        if (e.target === this) closeDetailModal();
    });
});
</script>
