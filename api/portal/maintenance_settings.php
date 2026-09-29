<?php
/** EyeSense Cloud Portal — Maintenance Billing Settings */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_MAINTENANCE');
$isAdminUser = $isSA || in_array(strtoupper(str_replace([' ', '_', '-'], '', (string)$session['role'])), ['ADMIN', 'SUPERADMIN', 'SOCIETYADMIN'], true);
?>

<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0">
        <h1 class="font-bold text-base md:text-lg text-textMain truncate">Settings &amp; Configuration</h1>
        <p class="text-xs text-textSec hidden sm:block">Society info, billing rules, branding, penalties &amp; notifications</p>
    </div>
    
    <!-- Tab Switcher -->
    <div class="tab-container my-0">
        <button type="button" id="tabBillingBtn" class="tab-btn active" onclick="switchSettingsTab('billing')">
            <i class="fas fa-file-invoice-dollar mr-1"></i> Billing &amp; Maintenance
        </button>
        <?php if ($isAdminUser): ?>
            <button type="button" id="tabBrandingBtn" class="tab-btn" onclick="switchSettingsTab('branding')">
                <i class="fas fa-palette mr-1"></i> Society Branding
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="flex-1 overflow-y-auto p-6 space-y-6">

<div id="panelBilling" class="space-y-6">

<!-- ── 1. Society Info ────────────────────────────────── -->
<div class="glass-panel rounded-xl p-6">
    <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-5"><i class="fas fa-building text-indigo-400 mr-2"></i>Society Information</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="md:col-span-2"><label class="lbl">Society Name</label><input type="text" id="s_society_name" class="w-full text-sm" placeholder="e.g. Green Valley Apartments"></div>
        <div class="md:col-span-2"><label class="lbl">Address</label><textarea id="s_address" rows="2" class="w-full text-sm" style="resize:vertical;" placeholder="Full address"></textarea></div>
        <div><label class="lbl">Landmark</label><input type="text" id="s_landmark" class="w-full text-sm" placeholder="Near City Mall"></div>
        <div><label class="lbl">City</label><input type="text" id="s_city" class="w-full text-sm" placeholder="Mumbai"></div>
        <div><label class="lbl">State</label>
            <select id="s_state_name" class="w-full text-sm" style="background:var(--surface);border:1px solid var(--border);color:var(--textMain);border-radius:8px;padding:10px 14px;outline:none;">
                <option value="">— Select State —</option>
                <?php foreach ([
                    'Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh',
                    'Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka',
                    'Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram',
                    'Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana',
                    'Tripura','Uttar Pradesh','Uttarakhand','West Bengal',
                    // UTs
                    'Andaman & Nicobar Islands','Chandigarh','Dadra & Nagar Haveli and Daman & Diu',
                    'Delhi','Jammu & Kashmir','Ladakh','Lakshadweep','Puducherry',
                ] as $state): ?>
                <option value="<?= $state ?>"><?= $state ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="lbl">Pincode</label>
            <input type="text" id="s_pincode" class="w-full text-sm" maxlength="6" minlength="6" placeholder="e.g. 560064">
            <div id="pincodeError" class="text-[11px] text-rose-400 mt-1 hidden"></div>
        </div>
        <div><label class="lbl">Default Monthly Amount ₹</label><input type="number" id="s_default_amount" class="w-full text-sm" step="0.01" min="0"></div>
        <div><label class="lbl">Default Due Day (1–28)</label><input type="number" id="s_default_due_day" class="w-full text-sm" min="1" max="28" value="10"></div>
    </div>
</div>

<!-- ── 2. Receipt Notes ───────────────────────────────── -->
<div class="glass-panel rounded-xl p-6">
    <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-2"><i class="fas fa-sticky-note text-yellow-400 mr-2"></i>Receipt Notes <span class="text-xs text-textSec font-normal normal-case">(shown at bottom of receipt, leave blank to hide)</span></h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-4">
        <div>
            <label class="lbl"><span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mr-1"></span>Partial Payment Note</label>
            <textarea id="s_notes_partial" rows="2" class="w-full text-sm" style="resize:vertical;" placeholder="e.g. Thank you for your partial payment. Please clear the remaining balance by the due date."></textarea>
        </div>
        <div>
            <label class="lbl"><span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-1"></span>Fully Paid Note</label>
            <textarea id="s_notes_exact" rows="2" class="w-full text-sm" style="resize:vertical;" placeholder="e.g. Thank you! Your payment has been received in full."></textarea>
        </div>
        <div>
            <label class="lbl"><span class="inline-block w-2 h-2 rounded-full bg-purple-400 mr-1"></span>Overpaid Note</label>
            <textarea id="s_notes_overpaid" rows="2" class="w-full text-sm" style="resize:vertical;" placeholder="e.g. You have overpaid. The excess amount will be credited to next month's bill."></textarea>
        </div>
        <div>
            <label class="lbl"><span class="inline-block w-2 h-2 rounded-full bg-red-400 mr-1"></span>Overdue Note</label>
            <textarea id="s_notes_overdue" rows="2" class="w-full text-sm" style="resize:vertical;" placeholder="e.g. This bill is overdue. A penalty may be applied. Please contact admin."></textarea>
        </div>
    </div>
</div>

<!-- ── 3. Plot Size → Rate Map ────────────────── -->
<div class="glass-panel rounded-xl p-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h3 class="text-sm font-bold text-textSec uppercase tracking-wider">
                <i class="fas fa-table text-amber-400 mr-2"></i>Plot Size → Maintenance Rate Map
            </h3>
            <p class="text-xs text-textSec mt-1">Map each plot size (sqft) to a fixed monthly maintenance amount. When a member is added with a matching plot size, the amount is auto-filled.</p>
        </div>
        <button onclick="openRateAddRow()" class="btn-primary text-xs" id="addRateBtn">
            <i class="fas fa-plus mr-1"></i> Add Rate
        </button>
    </div>

    <!-- Rate Map Table -->
    <div class="overflow-x-auto rounded-xl border border-border">
        <table class="w-full text-left" id="rateMapTable">
            <thead>
                <tr class="border-b border-border bg-gray-900/60">
                    <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider">Plot Size (sqft)</th>
                    <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider">Monthly Amount (₹)</th>
                    <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider">Label</th>
                    <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider hidden md:table-cell">Last Updated</th>
                    <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="rateMapBody">
                <tr><td colspan="5" class="px-4 py-8 text-center text-textSec text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>
            </tbody>
        </table>
    </div>
    <p class="text-xs text-textSec mt-3"><i class="fas fa-info-circle mr-1"></i>If no rate exists for a member’s plot size, the admin will be prompted to add one here.</p>
</div>

<!-- Rate Add/Edit Modal -->
<div id="rateModal" class="fixed inset-0 z-[110] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-md shadow-2xl">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surfaceLight">
            <h3 class="font-bold text-textMain" id="rateModalTitle"><i class="fas fa-table text-amber-400 mr-2"></i>Add Rate</h3>
            <button onclick="closeRateModal()" class="text-textSec hover:text-textMain w-8 h-8 rounded-full bg-textMain hover:bg-gray-700 flex items-center justify-center transition"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <input type="hidden" id="rateEditId" value="">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="lbl">Plot Size (sqft) *</label>
                    <input type="number" id="rateInputSqft" class="w-full text-sm" step="0.01" min="1" placeholder="e.g. 1000">
                </div>
                <div>
                    <label class="lbl">Monthly Amount (₹) *</label>
                    <input type="number" id="rateInputAmount" class="w-full text-sm" step="0.01" min="1" placeholder="e.g. 2500">
                </div>
            </div>
            <div>
                <label class="lbl">Label <span class="text-textSec font-normal normal-case">(optional)</span></label>
                <input type="text" id="rateInputLabel" class="w-full text-sm" placeholder="e.g. Small Plot, Corner Plot">
            </div>
        </div>
        <div class="p-4 border-t border-border bg-surfaceLight flex justify-between gap-3">
            <button onclick="closeRateModal()" class="btn-ghost">Cancel</button>
            <button onclick="saveRate()" class="btn-primary" id="rateModalSaveBtn"><i class="fas fa-save mr-1"></i> Save Rate</button>
        </div>
    </div>
</div>

<!-- Rate History Modal -->
<div id="rateHistoryModal" class="fixed inset-0 z-[110] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[85vh]">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surfaceLight shrink-0">
            <h3 class="font-bold text-textMain" id="rateHistoryTitle"><i class="fas fa-clock-rotate-left text-indigo-400 mr-2"></i>Rate Change History</h3>
            <button onclick="closeRateHistoryModal()" class="text-textSec hover:text-textMain w-8 h-8 rounded-full bg-textMain hover:bg-gray-700 flex items-center justify-center transition"><i class="fas fa-times"></i></button>
        </div>
        <div class="overflow-y-auto p-5" id="rateHistoryBody">
            <p class="text-textSec text-sm text-center py-6"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</p>
        </div>
    </div>
</div>

<!-- ── 4. Admin Payment Details & QR Code ─────────────── -->
<div class="glass-panel rounded-xl p-6">
    <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-5"><i class="fas fa-credit-card text-emerald-400 mr-2"></i>Administrator Payment Details <span class="text-xs text-textSec font-normal normal-case">(shown to members for direct payment)</span></h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-5">
            <div><label class="lbl">UPI ID</label><input type="text" id="s_payment_upi_id" class="w-full text-sm" placeholder="admin@upi" oninput="updateQrPreview()"></div>
            <div><label class="lbl">Account Holder Name</label><input type="text" id="s_payment_account_name" class="w-full text-sm" placeholder="John Doe" oninput="updateQrPreview()"></div>
            <div class="md:col-span-2"><label class="lbl">Payment Instructions <span class="text-xs text-textSec font-normal normal-case">(shown to members)</span></label>
                <textarea id="s_payment_instructions" rows="2" class="w-full text-sm" style="resize:vertical;" placeholder="e.g. Please include your flat number in payment remarks"></textarea>
            </div>
        </div>
        <!-- QR Code Preview -->
        <div class="flex flex-col items-center justify-start">
            <label class="lbl text-center mb-2">UPI QR Code Preview</label>
            <div id="qrPreviewContainer" class="bg-white rounded-xl p-3 shadow-lg" style="width:180px; height:180px; display:flex; align-items:center; justify-content:center;">
                <div id="qrPlaceholder" class="text-center" style="color:#9ca3af; font-size:11px;">
                    <i class="fas fa-qrcode text-3xl mb-2" style="color:#d1d5db;"></i><br>Enter UPI ID<br>to generate QR
                </div>
                <div id="qrCode" style="display:none;"></div>
            </div>
            <p class="text-xs text-textSec mt-2 text-center">Members will see this QR<br>when paying via QR Code</p>
        </div>
    </div>
</div>

<!-- ── 4. Penalty Settings ───────────────────────────── -->
<div class="glass-panel rounded-xl p-6">
    <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-5"><i class="fas fa-gavel text-red-400 mr-2"></i>Late Payment Penalty</h3>
    <p class="text-xs text-textSec mb-4">Define how much penalty is added for late payments. Set both to 0 to disable.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="lbl">Penalty Amount (₹)</label>
            <input type="number" id="s_penalty_amount" class="w-full text-sm" step="0.01" min="0" placeholder="5.00">
            <p class="text-xs text-textSec mt-1">Amount added per interval</p>
        </div>
        <div>
            <label class="lbl">Every (days)</label>
            <input type="number" id="s_penalty_days" class="w-full text-sm" min="0" placeholder="2">
            <p class="text-xs text-textSec mt-1">e.g. ₹5 every 2 days after due date</p>
        </div>
    </div>
    <div id="penaltyPreview" class="mt-4 hidden p-3 rounded-lg bg-red-900/10 border border-red-900/40 text-sm text-red-300">
        <i class="fas fa-info-circle mr-1"></i><span id="penaltyPreviewText"></span>
    </div>
</div>

<!-- ── 5. Attachment Settings ──────────────── -->
<div class="glass-panel rounded-xl p-6">
    <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-2">
        <i class="fas fa-paperclip text-violet-400 mr-2"></i>Attachment Settings
    </h3>
    <p class="text-xs text-textSec mb-4">Maximum file size allowed for complaint attachments and comment/chat attachments.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-end">
        <div>
            <label class="lbl">Max Attachment Size (MB)</label>
            <input type="number" id="s_max_attachment_size_mb" class="w-full text-sm" min="1" max="50" value="5" placeholder="5">
            <p class="text-xs text-textSec mt-1">Applies to complaint attachments &amp; comment attachments. Range: 1–50 MB.</p>
        </div>
        <div class="p-4 rounded-xl bg-violet-900/10 border border-violet-800/30 text-sm text-violet-300">
            <i class="fas fa-info-circle mr-1"></i>
            Allowed file types: JPG, PNG, GIF, WEBP, MP4, AVI, MOV, PDF, TXT, DOC, DOCX, XLS, XLSX, CSV, ZIP
        </div>
    </div>
</div>

<!-- ── 6. Tenant Management Settings ──────────────── -->
<div class="glass-panel rounded-xl p-6">
    <h3 class="text-sm font-bold text-gray-300 uppercase tracking-wider mb-2">
        <i class="fas fa-house-user text-violet-400 mr-2"></i>Tenant Management Settings
    </h3>
    <p class="text-xs text-gray-500 mb-5">Control who can add tenants, generate registration links, and whether approval is required before a tenant becomes active.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Toggle helper -->
        <?php
        $toggleRow = fn(string $id, string $label, string $desc) =>
            '<div class="flex items-center justify-between p-4 rounded-xl border border-gray-700/50 bg-gray-800/20">'
          . '<div><div class="text-sm font-semibold text-gray-200">' . $label . '</div>'
          . '<div class="text-xs text-gray-500 mt-0.5">' . $desc . '</div></div>'
          . '<label class="toggle-switch"><input type="checkbox" id="' . $id . '" checked><span class="toggle-slider"></span></label>'
          . '</div>';
        echo $toggleRow('t_member_can_add',      'Members Can Add Tenants',           'Allow regular members to manually add tenant records');
        echo $toggleRow('t_member_can_gen_link', 'Members Can Generate Links',         'Allow members to generate a 5-hour self-registration URL');
        echo $toggleRow('t_admin_can_add',       'Admins Can Add Tenants',             'Allow admins to create and edit tenant records');
        echo $toggleRow('t_sa_can_add',          'Super Admin Can Add Tenants',        'Allow superadmin full CRUD access on tenant records');
        echo $toggleRow('t_doc_required',        'ID Document Upload Required',        'Make government ID document upload mandatory during registration');
        echo $toggleRow('t_approval_required',   'Approval Required Before Active',    'Require admin approval before a tenant status becomes Active');
        ?>
    </div>
    <div class="mt-5 flex justify-end">
        <button onclick="saveTenantSettings()" class="btn-primary text-sm px-5 py-2"><i class="fas fa-save mr-2"></i>Save Tenant Settings</button>
    </div>
</div>


<div class="glass-panel rounded-xl p-6">
    <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-5"><i class="fas fa-bell text-cyan-400 mr-2"></i>Notification Channels</h3>

    <!-- Email -->
    <div class="mb-4 p-4 rounded-xl border border-border/50 bg-textMain/20">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-900/40 flex items-center justify-center text-indigo-400"><i class="fas fa-envelope"></i></div>
                <div>
                    <div class="text-sm font-bold text-textMain">Email Notifications</div>
                    <div class="text-xs text-textSec">Receipts, approvals, bills, overdue alerts</div>
                </div>
            </div>
            <label class="toggle-switch"><input type="checkbox" id="n_email" checked onchange="toggleNotifyPhone()"><span class="toggle-slider"></span></label>
        </div>
        <div id="emailFields" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><label class="lbl">Admin Notification Emails</label><input type="text" id="s_admin_emails" class="w-full text-sm" placeholder="admin@company.com, finance@company.com"><p class="text-xs text-textSec mt-1">Comma-separated. Receive alerts on every member payment.</p></div>
        </div>
    </div>

    <!-- SMS -->
    <div class="mb-4 p-4 rounded-xl border border-border/50 bg-textMain/20 relative overflow-hidden" id="smsBox">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-green-900/40 flex items-center justify-center text-green-400"><i class="fas fa-sms"></i></div>
                <div>
                    <div class="text-sm font-bold text-textMain flex items-center gap-2">SMS Notifications <span class="coming-soon-badge">Coming Soon</span></div>
                    <div class="text-xs text-textSec">SMS alerts to member's registered mobile</div>
                </div>
            </div>
            <label class="toggle-switch"><input type="checkbox" id="n_sms" disabled><span class="toggle-slider" style="opacity:0.4;cursor:not-allowed;"></span></label>
        </div>
        <div class="coming-soon-overlay">
            <div class="text-center"><div class="text-3xl mb-1">🚀</div><div class="text-sm font-bold text-textSec">Coming Soon</div><div class="text-xs text-textSec">SMS integration will be available in a future update</div></div>
        </div>
    </div>

    <!-- WhatsApp -->
    <div class="p-4 rounded-xl border border-border/50 bg-textMain/20 relative overflow-hidden" id="waBox">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-900/40 flex items-center justify-center text-emerald-400"><i class="fab fa-whatsapp"></i></div>
                <div>
                    <div class="text-sm font-bold text-textMain flex items-center gap-2">WhatsApp Notifications <span class="coming-soon-badge bg-emerald-900/40 text-emerald-400 border-emerald-700/50">Coming Soon</span></div>
                    <div class="text-xs text-textSec">WhatsApp messages via API integration</div>
                </div>
            </div>
            <label class="toggle-switch"><input type="checkbox" id="n_whatsapp" disabled><span class="toggle-slider" style="opacity:0.4;cursor:not-allowed;"></span></label>
        </div>
        <div class="coming-soon-overlay">
            <div class="text-center"><div class="text-3xl mb-1">💬</div><div class="text-sm font-bold text-textSec">Coming Soon</div><div class="text-xs text-textSec">WhatsApp Business API integration coming soon</div></div>
        </div>
    </div>

    <div class="mt-5 flex justify-end">
        <button onclick="saveSettings()" class="btn-primary"><i class="fas fa-save mr-2"></i>Save All Settings</button>
    </div>
</div>

<!-- ── 6. Generate Monthly Bills ─────────────── -->
<div class="glass-panel rounded-xl p-6">
    <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-5"><i class="fas fa-file-invoice-dollar text-emerald-400 mr-2"></i>Generate Monthly Bills</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 items-end">
        <div><label class="lbl">Billing Month</label><input type="month" id="g_month" class="w-full text-sm" value="<?= date('Y-m') ?>"></div>
        <div><label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="g_carry_forward" checked class="accent-indigo-500 w-4 h-4"><span class="text-sm text-textSec">Carry forward unpaid dues</span></label></div>
        <div class="flex justify-end"><button onclick="generateBills()" class="btn-primary bg-emerald-600 hover:bg-emerald-500" id="genBtn"><i class="fas fa-bolt mr-2"></i>Generate Bills</button></div>
    </div>
    <div id="genResult" class="mt-4 hidden"></div>
</div>

<!-- ── 6. Mark Overdue ───────────────────────────────── -->
<div class="glass-panel rounded-xl p-6">
    <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-3"><i class="fas fa-clock text-red-400 mr-2"></i>Mark Overdue Bills</h3>
    <p class="text-sm text-textSec mb-4">Flag all PENDING bills past their due date as <span class="text-red-400 font-bold">OVERDUE</span>.</p>
    <div class="flex items-center gap-4">
        <button onclick="markOverdue()" class="btn-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Mark All Overdue</button>
        <span id="overdueResult" class="text-sm text-textSec"></span>
    </div>
</div>

</div> <!-- /panelBilling -->

<?php if ($isAdminUser): ?>
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- ── BRANDING TAB ─────────────────────────────────────────────────────── -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<div id="panelBranding" class="space-y-6 hidden">
    <!-- 1. Society Logo -->
    <div class="glass-panel rounded-xl p-6">
        <div class="flex items-center justify-between gap-3 mb-2">
            <div>
                <h3 class="text-sm font-bold text-textSec uppercase tracking-wider">
                    <i class="fas fa-image text-indigo-400 mr-2"></i>Society Logo
                </h3>
                <p class="text-xs text-textSec mt-1">Upload your society's custom logo. It will appear on the sidebar header and member portal.</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-6 mt-4">
            <!-- Logo Preview Box -->
            <div class="relative">
                <img id="b_logo_img" src="" alt="Society Logo" class="h-24 w-24 object-contain rounded-2xl border border-border bg-surfaceLight p-2 shadow-sm hidden">
                <div id="b_logo_ph" class="h-24 w-24 rounded-2xl border-2 border-dashed border-border flex flex-col items-center justify-center text-textSec text-xs bg-surfaceLight/50">
                    <i class="fas fa-cloud-arrow-up text-2xl mb-1 text-textSec"></i>
                    <span>No Logo</span>
                </div>
            </div>

            <!-- Upload Controls -->
            <div class="space-y-2">
                <input type="file" id="b_logo_file" accept=".png,.jpg,.jpeg,.webp,.svg" class="hidden" onchange="onLogoFileSelected(this)">
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="document.getElementById('b_logo_file').click()" class="btn-primary text-xs px-3.5 py-2">
                        <i class="fas fa-upload mr-1.5"></i> Choose Logo File
                    </button>
                    <button type="button" id="b_remove_logo_btn" onclick="removeSocietyLogo()" class="btn-danger text-xs px-3.5 py-2 hidden">
                        <i class="fas fa-trash mr-1.5"></i> Remove Logo
                    </button>
                </div>
                <div id="b_logo_selected_name" class="text-xs text-emerald-400 font-medium"></div>
                <p class="text-[11px] text-textSec">Supported formats: PNG, JPG, JPEG, WEBP, SVG. Max file size: 2MB.</p>
            </div>
        </div>
    </div>

    <!-- 2. Brand Theme Color -->
    <div class="glass-panel rounded-xl p-6">
        <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-2">
            <i class="fas fa-palette text-purple-400 mr-2"></i>Brand Theme Color
        </h3>
        <p class="text-xs text-textSec mb-4">Choose a primary brand color to customize sidebar accents, buttons, and badges.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
            <div class="flex items-center gap-3">
                <input type="color" id="b_theme_color_picker" value="#6366f1" class="h-10 w-14 rounded-lg cursor-pointer border border-border bg-transparent p-0.5 shrink-0" oninput="onColorPickerChange(this.value)">
                <div class="relative flex-1 max-w-[160px]">
                    <input type="text" id="b_theme_color_text" value="#6366F1" maxlength="7" class="w-full text-sm font-mono uppercase" placeholder="#6366F1" oninput="onColorTextChange(this.value)">
                </div>
                <button type="button" onclick="onColorPickerChange('#6366f1')" class="btn-ghost text-xs py-2 px-3 shrink-0" title="Reset to default indigo">
                    <i class="fas fa-undo mr-1"></i> Default
                </button>
            </div>

            <!-- Color Palette Presets -->
            <div>
                <label class="lbl mb-2">Preset Brand Palettes</label>
                <div class="flex flex-wrap gap-2">
                    <?php 
                    $presets = [
                        ['#6366f1', 'Indigo'],
                        ['#10b981', 'Emerald'],
                        ['#2563eb', 'Blue'],
                        ['#8b5cf6', 'Purple'],
                        ['#f59e0b', 'Amber'],
                        ['#ef4444', 'Rose'],
                        ['#06b6d4', 'Cyan'],
                        ['#475569', 'Slate'],
                    ];
                    foreach ($presets as [$hex, $name]): ?>
                        <button type="button" onclick="onColorPickerChange('<?= $hex ?>')" class="w-7 h-7 rounded-full border-2 border-white/20 hover:scale-110 transition-transform shadow-sm focus:outline-none" style="background-color: <?= $hex ?>;" title="<?= $name ?> (<?= $hex ?>)"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div id="b_color_error" class="text-xs text-red-400 mt-2 hidden"></div>
    </div>

    <!-- 3. Welcome & Notice Message -->
    <div class="glass-panel rounded-xl p-6">
        <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-2">
            <i class="fas fa-bullhorn text-amber-400 mr-2"></i>Welcome &amp; Notice Message
        </h3>
        <p class="text-xs text-textSec mb-4">Custom message or notice displayed on the dashboard and member portal.</p>
        
        <div>
            <textarea id="b_welcome_message" rows="3" class="w-full text-sm" maxlength="500" placeholder="e.g. Welcome to Green Valley Society! Please clear monthly dues by the 10th of each month." oninput="onWelcomeMsgInput()"></textarea>
            <div class="flex justify-between items-center text-xs mt-1.5 text-textSec">
                <span>Plain text only, HTML tags will be stripped.</span>
                <span id="b_welcome_counter" class="font-mono font-bold">0 / 500 characters</span>
            </div>
        </div>
    </div>

    <!-- 4. Emergency Contacts Directory -->
    <div class="glass-panel rounded-xl p-6">
        <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-2">
            <i class="fas fa-phone-volume text-emerald-400 mr-2"></i>Emergency Contacts Directory
        </h3>
        <p class="text-xs text-textSec mb-4">Configure the essential contact numbers for your society. These numbers are displayed in the member portal.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php
            $emergencyList = [
                ['label' => 'Security Gate', 'icon' => 'fa-shield-halved', 'color' => 'text-indigo-400', 'placeholder' => 'e.g. +91 98765 00001'],
                ['label' => 'Society Office', 'icon' => 'fa-building', 'color' => 'text-purple-400', 'placeholder' => 'e.g. 020-12345678'],
                ['label' => 'Electrician', 'icon' => 'fa-bolt', 'color' => 'text-yellow-400', 'placeholder' => 'e.g. +91 98765 00002'],
                ['label' => 'Plumber', 'icon' => 'fa-wrench', 'color' => 'text-cyan-400', 'placeholder' => 'e.g. +91 98765 00003'],
                ['label' => 'Police/Ambulance', 'icon' => 'fa-truck-medical', 'color' => 'text-red-400', 'placeholder' => 'e.g. 112 or 100 / 108'],
            ];
            foreach ($emergencyList as $i => $item): ?>
                <div class="p-3.5 rounded-xl border border-border bg-surfaceLight/40 flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <i class="fas <?= $item['icon'] ?> <?= $item['color'] ?> text-sm"></i>
                        <span class="text-xs font-bold text-textMain uppercase tracking-wider"><?= htmlspecialchars($item['label']) ?></span>
                    </div>
                    <input type="hidden" id="ec_label_<?= $i ?>" value="<?= htmlspecialchars($item['label']) ?>">
                    <input type="text" id="ec_phone_<?= $i ?>" class="w-full text-sm" placeholder="<?= htmlspecialchars($item['placeholder']) ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Save Branding Button -->
    <div class="flex justify-end items-center gap-4">
        <span id="brandingSaveStatus" class="text-xs text-textSec"></span>
        <button type="button" id="brandingSaveBtn" onclick="saveBrandingSettings()" class="btn-primary text-sm px-6 py-2.5">
            <i class="fas fa-save mr-2"></i> Save Branding Settings
        </button>
    </div>
</div>
<?php endif; ?>

</div> <!-- /main container -->

<style>
.tab-container { display:inline-flex; background:var(--surfaceLight); border:1px solid var(--border); border-radius:10px; padding:4px; gap:4px; }
.tab-btn { background:none; border:none; color:var(--textSec); padding:7px 16px; font-size:13px; font-weight:600; border-radius:7px; cursor:pointer; font-family:inherit; transition:all .18s; display:inline-flex; align-items:center; gap:6px; }
.tab-btn:hover { color:var(--textMain); }
.tab-btn.active { background:var(--primary, #6366f1); color:#fff; box-shadow:0 4px 12px rgba(99,102,241,0.25); }
.lbl { display:block; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--textSec); font-weight:700; margin-bottom:4px; }
textarea { background:var(--surface) !important; border:1px solid var(--border) !important; color:var(--textMain) !important; border-radius:8px; padding:8px 12px; outline:none; }
textarea:focus { border-color:var(--primary, #6366f1) !important; }
.coming-soon-badge { display:inline-flex; align-items:center; padding:1px 8px; border-radius:999px; font-size:10px; font-weight:700; background:rgba(99,102,241,0.15); color:#a5b4fc; border:1px solid rgba(99,102,241,0.3); letter-spacing:0.03em; }
.coming-soon-overlay { position:absolute; inset:0; background:rgba(10,10,10,0.75); backdrop-filter:blur(2px); display:flex; align-items:center; justify-content:center; border-radius:inherit; z-index:10; pointer-events:none; }
</style>

<?php require_once __DIR__ . '/portal_footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
async function loadSettings() {
    try {
        const res = await fetch('../maintenance_review_api.php?action=get_settings');
        const d = await res.json();
        if (d.error) return;
        const f = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
        f('s_society_name', d.society_name); f('s_address', d.address); f('s_landmark', d.landmark);
        f('s_city', d.city); f('s_state_name', d.state_name); f('s_pincode', d.pincode);
        f('s_default_amount', d.default_amount); f('s_default_due_day', d.default_due_day || 10);
        f('s_admin_emails', d.admin_emails); f('s_notes_partial', d.notes_partial);
        f('s_notes_exact', d.notes_exact); f('s_notes_overdue', d.notes_overdue);
        f('s_notes_overpaid', d.notes_overpaid); f('s_penalty_amount', d.penalty_amount || '');
        f('s_penalty_days', d.penalty_days || '');
        f('s_payment_upi_id', d.payment_upi_id); f('s_payment_account_name', d.payment_account_name);
        f('s_payment_instructions', d.payment_instructions);
        f('s_max_attachment_size_mb', d.max_attachment_size_mb || 5);
        const cb = (id, val) => { const el = document.getElementById(id); if (el) el.checked = !!parseInt(val); };
        cb('n_email', d.notify_email); cb('n_sms', d.notify_sms); cb('n_whatsapp', d.notify_whatsapp);
        // Load tenant flags
        try {
            const td = await fetch('../tenant_api.php?action=get_settings').then(r=>r.json());
            cb('t_member_can_add',      td.tenant_member_can_add      ?? true);
            cb('t_member_can_gen_link', td.tenant_member_can_gen_link ?? true);
            cb('t_admin_can_add',       td.tenant_admin_can_add       ?? true);
            cb('t_sa_can_add',          td.tenant_sa_can_add          ?? true);
            cb('t_doc_required',        td.tenant_doc_required        ?? false);
            cb('t_approval_required',   td.tenant_approval_required   ?? true);
        } catch(te) {}
        updatePenaltyPreview();
        updateQrPreview();
    } catch(e) {}
}


function updatePenaltyPreview() {
    const amt = parseFloat(document.getElementById('s_penalty_amount')?.value || 0);
    const days = parseInt(document.getElementById('s_penalty_days')?.value || 0);
    const preview = document.getElementById('penaltyPreview');
    const text = document.getElementById('penaltyPreviewText');
    if (!preview) return;
    if (amt > 0 && days > 0) {
        preview.classList.remove('hidden');
        text.textContent = `Penalty: ₹${amt.toFixed(2)} will be added every ${days} day(s) after due date.`;
    } else {
        preview.classList.add('hidden');
    }
}

function updateQrPreview() {
    const upiId = (document.getElementById('s_payment_upi_id')?.value || '').trim();
    const name = (document.getElementById('s_payment_account_name')?.value || '').trim();
    const qrDiv = document.getElementById('qrCode');
    const placeholder = document.getElementById('qrPlaceholder');
    if (!upiId) {
        qrDiv.style.display = 'none'; qrDiv.innerHTML = '';
        placeholder.style.display = 'block';
        return;
    }
    // Build UPI deep-link URI
    let upiUri = `upi://pay?pa=${encodeURIComponent(upiId)}`;
    if (name) upiUri += `&pn=${encodeURIComponent(name)}`;
    upiUri += `&cu=INR`;

    try {
        const qr = qrcode(0, 'M');
        qr.addData(upiUri);
        qr.make();
        qrDiv.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2 });
        qrDiv.style.display = 'block';
        placeholder.style.display = 'none';
    } catch(e) {
        qrDiv.innerHTML = '<span style="color:red;font-size:10px;">QR Error</span>';
        qrDiv.style.display = 'block';
        placeholder.style.display = 'none';
    }
}

const sPincodeInput = document.getElementById('s_pincode');
const sPincodeError = document.getElementById('pincodeError');

function validateMaintenancePincode() {
    if (!sPincodeInput || !sPincodeError) return true;
    const val = sPincodeInput.value.trim();
    if (!val) {
        sPincodeError.classList.add('hidden');
        sPincodeInput.classList.remove('border-rose-500');
        return true;
    }
    if (!/^\d{6}$/.test(val)) {
        sPincodeError.textContent = 'Pincode must be exactly 6 digits';
        sPincodeError.classList.remove('hidden');
        sPincodeInput.classList.add('border-rose-500');
        return false;
    }
    sPincodeError.classList.add('hidden');
    sPincodeInput.classList.remove('border-rose-500');
    return true;
}

if (sPincodeInput) {
    sPincodeInput.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
        validateMaintenancePincode();
    });
    sPincodeInput.addEventListener('blur', validateMaintenancePincode);
}

async function saveSettings() {
    if (!validateMaintenancePincode()) {
        showAlert('Pincode must be exactly 6 digits', 'error');
        if (sPincodeInput) sPincodeInput.focus();
        return;
    }

    const val = id => document.getElementById(id)?.value || '';
    const chk = id => document.getElementById(id)?.checked ? 1 : 0;
    const body = {
        action: 'save_settings',
        society_name: val('s_society_name'), address: val('s_address'), landmark: val('s_landmark'),
        city: val('s_city'), state_name: val('s_state_name'), pincode: val('s_pincode'),
        default_amount: val('s_default_amount'), default_due_day: val('s_default_due_day'),
        admin_emails: val('s_admin_emails'),
        notes_partial: val('s_notes_partial'), notes_exact: val('s_notes_exact'),
        notes_overdue: val('s_notes_overdue'), notes_overpaid: val('s_notes_overpaid'),
        penalty_amount: val('s_penalty_amount'), penalty_days: val('s_penalty_days'),
        notify_email: chk('n_email'), notify_sms: chk('n_sms'), notify_whatsapp: chk('n_whatsapp'),
        payment_upi_id: val('s_payment_upi_id'), payment_account_name: val('s_payment_account_name'),
        payment_instructions: val('s_payment_instructions'),
        max_attachment_size_mb: parseInt(val('s_max_attachment_size_mb')) || 5,
    };
    try {
        const res = await fetch('../maintenance_review_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
        const r = await res.json();
        if (r.error) throw new Error(r.error);
        showAlert('Settings saved successfully', 'success');
    } catch(e) { showAlert(e.message, 'error'); }
}

async function saveTenantSettings() {
    const chk = id => document.getElementById(id)?.checked ? 1 : 0;
    const body = {
        action: 'save_settings',
        tenant_member_can_add:      chk('t_member_can_add'),
        tenant_member_can_gen_link: chk('t_member_can_gen_link'),
        tenant_admin_can_add:       chk('t_admin_can_add'),
        tenant_sa_can_add:          chk('t_sa_can_add'),
        tenant_doc_required:        chk('t_doc_required'),
        tenant_approval_required:   chk('t_approval_required'),
    };
    try {
        const r = await fetch('../tenant_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) }).then(x=>x.json());
        if (r.error) throw new Error(r.error);
        showAlert('Tenant settings saved', 'success');
    } catch(e) { showAlert(e.message, 'error'); }
}

async function generateBills() {
    const monthInput = document.getElementById('g_month').value;
    if (!monthInput) return showAlert('Select a month', 'warning');
    const month = monthInput + '-01';
    const carry = document.getElementById('g_carry_forward').checked;
    const btn = document.getElementById('genBtn');
    if (!await showConfirm(`Generate bills for ${monthInput}? ${carry ? 'Carry forward enabled.' : 'No carry-forward.'}`)) return;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating...';
    try {
        const res = await fetch('../maintenance_review_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'generate_bills', month, carry_forward:carry }) });
        const r = await res.json();
        if (r.error) throw new Error(r.error);
        const result = document.getElementById('genResult');
        result.classList.remove('hidden');
        result.innerHTML = `<div class="p-4 rounded-xl border border-emerald-900/50 bg-emerald-900/10"><p class="text-sm text-emerald-300"><i class="fas fa-check-circle mr-2"></i><b>${r.inserted}</b> bills generated, <b>${r.skipped}</b> already existed for ${monthInput}.</p></div>`;
    } catch(e) { showAlert(e.message, 'error'); } finally { btn.disabled=false; btn.innerHTML='<i class="fas fa-bolt mr-2"></i>Generate Bills'; }
}

async function markOverdue() {
    try {
        const res = await fetch('../maintenance_review_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'mark_overdue' }) });
        const r = await res.json();
        if (r.error) throw new Error(r.error);
        document.getElementById('overdueResult').textContent = `${r.marked} bill(s) marked as overdue`;
        showAlert(r.marked > 0 ? `${r.marked} bills marked overdue` : 'No pending overdue bills', r.marked > 0 ? 'warning' : 'info');
    } catch(e) { showAlert(e.message, 'error'); }
}

// Penalty preview on change
['s_penalty_amount','s_penalty_days'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', updatePenaltyPreview);
});

// ── Rate Map CRUD ──────────────────────────────────────────────────────
const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

async function loadRateMap() {
    const tbody = document.getElementById('rateMapBody');
    try {
        const res  = await fetch('../rate_map_api.php?action=list');
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        renderRateMap(data.rows || []);
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-6 text-center text-red-400 text-sm">Error: ${esc(e.message)}</td></tr>`;
    }
}

function renderRateMap(rows) {
    const tbody = document.getElementById('rateMapBody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-textSec text-sm">
            <i class="fas fa-table text-3xl block mb-3 text-gray-700"></i>
            No rates configured yet. Click <strong class="text-amber-400">Add Rate</strong> to get started.
        </td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map(r => {
        const updInfo = r.updated_at
            ? `<span class="text-xs text-textSec">by ${esc(r.updated_by||r.created_by)} on ${new Date(r.updated_at).toLocaleDateString('en-IN')}</span>`
            : `<span class="text-xs text-textSec">by ${esc(r.created_by)} on ${new Date(r.created_at).toLocaleDateString('en-IN')}</span>`;
        return `<tr class="border-b border-border/50 hover:bg-textMain/20">
            <td class="px-4 py-3 text-sm font-bold text-textMain font-mono">${parseFloat(r.plot_size_sqft).toLocaleString()} sqft</td>
            <td class="px-4 py-3 text-sm text-emerald-400 font-bold">₹${parseFloat(r.monthly_amount).toLocaleString('en-IN',{minimumFractionDigits:2})}</td>
            <td class="px-4 py-3 text-sm text-textSec">${r.label ? `<span class="badge" style="background:rgba(245,158,11,0.1);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);">${esc(r.label)}</span>` : '<span class="text-gray-700">—</span>'}</td>
            <td class="px-4 py-3 hidden md:table-cell">${updInfo}
                ${r.history_count > 0 ? `<button onclick="viewRateHistory(${r.id}, '${parseFloat(r.plot_size_sqft)} sqft')" class="ml-2 text-xs text-indigo-400 hover:text-indigo-300"><i class="fas fa-clock-rotate-left mr-1"></i>${r.history_count}</button>` : ''}
            </td>
            <td class="px-4 py-3">
                <div class="flex gap-2">
                    <button onclick="openRateEditRow(${r.id},${r.plot_size_sqft},'${parseFloat(r.monthly_amount)}','${esc(r.label||'')}')" class="btn-ghost text-xs py-1 px-2"><i class="fas fa-pen"></i></button>
                    <button onclick="deleteRate(${r.id},'${parseFloat(r.plot_size_sqft)} sqft')" class="btn-danger text-xs py-1 px-2"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function openRateAddRow() {
    document.getElementById('rateEditId').value = '';
    document.getElementById('rateInputSqft').value = '';
    document.getElementById('rateInputAmount').value = '';
    document.getElementById('rateInputLabel').value = '';
    document.getElementById('rateModalTitle').innerHTML = '<i class="fas fa-plus text-amber-400 mr-2"></i>Add Rate';
    document.getElementById('rateInputSqft').removeAttribute('readonly');
    document.getElementById('rateModal').classList.remove('hidden');
    setTimeout(() => document.getElementById('rateInputSqft').focus(), 100);
}

function openRateEditRow(id, sqft, amount, label) {
    document.getElementById('rateEditId').value = id;
    document.getElementById('rateInputSqft').value = sqft;
    document.getElementById('rateInputAmount').value = amount;
    document.getElementById('rateInputLabel').value = label;
    document.getElementById('rateModalTitle').innerHTML = '<i class="fas fa-pen text-amber-400 mr-2"></i>Edit Rate';
    document.getElementById('rateInputSqft').setAttribute('readonly', 'readonly');
    document.getElementById('rateModal').classList.remove('hidden');
    setTimeout(() => document.getElementById('rateInputAmount').focus(), 100);
}

function closeRateModal() { document.getElementById('rateModal').classList.add('hidden'); }

async function saveRate() {
    const id     = document.getElementById('rateEditId').value;
    const sqft   = parseFloat(document.getElementById('rateInputSqft').value);
    const amount = parseFloat(document.getElementById('rateInputAmount').value);
    const label  = document.getElementById('rateInputLabel').value.trim();

    if (!sqft || sqft <= 0)   return showAlert('Plot size must be > 0', 'warning');
    if (!amount || amount <=0) return showAlert('Monthly amount must be > 0', 'warning');

    const btn = document.getElementById('rateModalSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving…';

    try {
        const body = id
            ? { action:'update', id: parseInt(id), monthly_amount: amount, label }
            : { action:'add', plot_size_sqft: sqft, monthly_amount: amount, label };
        const res  = await fetch('../rate_map_api.php', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)
        });
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        showAlert(data.message, 'success');
        closeRateModal();
        loadRateMap();
    } catch(e) {
        showAlert(e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-1"></i>Save Rate';
    }
}

async function deleteRate(id, label) {
    if (!await showConfirm(`Delete rate for ${label}? This cannot be undone.`)) return;
    try {
        const res  = await fetch('../rate_map_api.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({action:'delete', id})
        });
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        showAlert(data.message, 'success');
        loadRateMap();
    } catch(e) { showAlert(e.message, 'error'); }
}

async function viewRateHistory(id, label) {
    document.getElementById('rateHistoryTitle').innerHTML =
        `<i class="fas fa-clock-rotate-left text-indigo-400 mr-2"></i>History — ${esc(label)}`;
    document.getElementById('rateHistoryBody').innerHTML =
        '<p class="text-textSec text-sm text-center py-6"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</p>';
    document.getElementById('rateHistoryModal').classList.remove('hidden');
    try {
        const res  = await fetch(`../rate_map_api.php?action=history&id=${id}`);
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        const rows = data.history || [];
        if (!rows.length) {
            document.getElementById('rateHistoryBody').innerHTML =
                '<p class="text-textSec text-sm text-center py-6">No history found.</p>';
            return;
        }
        const typeCls = { CREATED:'text-emerald-400', UPDATED:'text-amber-400', DELETED:'text-red-400' };
        document.getElementById('rateHistoryBody').innerHTML = `
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b border-border">
                    <th class="py-2 px-3 text-xs text-textSec uppercase">Change</th>
                    <th class="py-2 px-3 text-xs text-textSec uppercase">Old Amount</th>
                    <th class="py-2 px-3 text-xs text-textSec uppercase">New Amount</th>
                    <th class="py-2 px-3 text-xs text-textSec uppercase">Label</th>
                    <th class="py-2 px-3 text-xs text-textSec uppercase">By / When</th>
                </tr></thead>
                <tbody>${rows.map(h => `<tr class="border-b border-border/40">
                    <td class="py-2 px-3"><span class="font-bold ${typeCls[h.change_type]||''} text-xs">${h.change_type}</span></td>
                    <td class="py-2 px-3 text-textSec">${h.old_amount != null ? '₹'+parseFloat(h.old_amount).toLocaleString('en-IN',{minimumFractionDigits:2}) : '—'}</td>
                    <td class="py-2 px-3 text-textMain font-bold">₹${parseFloat(h.new_amount).toLocaleString('en-IN',{minimumFractionDigits:2})}</td>
                    <td class="py-2 px-3 text-textSec">${esc(h.new_label||'—')}</td>
                    <td class="py-2 px-3 text-xs text-textSec">${esc(h.changed_by)}<br><span class="text-textSec">${new Date(h.changed_at).toLocaleString('en-IN')}</span></td>
                </tr>`).join('')}</tbody>
            </table>`;
    } catch(e) {
        document.getElementById('rateHistoryBody').innerHTML =
            `<p class="text-red-400 text-sm text-center py-6">Error: ${esc(e.message)}</p>`;
    }
}

function closeRateHistoryModal() { document.getElementById('rateHistoryModal').classList.add('hidden'); }

// ── BRANDING TAB JAVASCRIPT ──────────────────────────────────────────────────
function switchSettingsTab(tab) {
    const isBranding = tab === 'branding';
    const bBtn = document.getElementById('tabBrandingBtn');
    const mBtn = document.getElementById('tabBillingBtn');
    const pBilling = document.getElementById('panelBilling');
    const pBranding = document.getElementById('panelBranding');

    if (isBranding) {
        if (bBtn) bBtn.classList.add('active');
        if (mBtn) mBtn.classList.remove('active');
        if (pBilling) pBilling.classList.add('hidden');
        if (pBranding) pBranding.classList.remove('hidden');
        loadBrandingSettings();
    } else {
        if (mBtn) mBtn.classList.add('active');
        if (bBtn) bBtn.classList.remove('active');
        if (pBranding) pBranding.classList.add('hidden');
        if (pBilling) pBilling.classList.remove('hidden');
    }
}

let _brandingLoaded = false;
let _selectedLogoFile = null;
let _currentLogoPath = null;

async function loadBrandingSettings() {
    try {
        const res = await fetch('../society/settings_api.php?action=get_branding');
        const d = await res.json();
        if (!d || !d.success || !d.data) return;
        
        const data = d.data;
        _currentLogoPath = data.logo_path || null;

        // 1. Logo
        updateLogoPreviewUI(_currentLogoPath);

        // 2. Theme color
        const color = data.theme_color || '#6366f1';
        onColorPickerChange(color);

        // 3. Welcome message
        const welcome = data.welcome_message || '';
        const welcomeEl = document.getElementById('b_welcome_message');
        if (welcomeEl) {
            welcomeEl.value = welcome;
            onWelcomeMsgInput();
        }

        // 4. Emergency contacts
        const contacts = data.emergency_contacts || [];
        for (let i = 0; i < 5; i++) {
            const labelEl = document.getElementById(`ec_label_${i}`);
            const phoneEl = document.getElementById(`ec_phone_${i}`);
            if (labelEl && phoneEl) {
                const label = labelEl.value;
                const match = contacts.find(c => c.label && c.label.toLowerCase() === label.toLowerCase());
                phoneEl.value = match ? match.phone || '' : '';
            }
        }
        _brandingLoaded = true;
    } catch (e) {
        console.error('Error loading society branding:', e);
    }
}

function updateLogoPreviewUI(path) {
    const img = document.getElementById('b_logo_img');
    const ph = document.getElementById('b_logo_ph');
    const remBtn = document.getElementById('b_remove_logo_btn');
    const nameEl = document.getElementById('b_logo_selected_name');

    if (!img || !ph) return;

    if (path) {
        img.src = (path.startsWith('http') || path.startsWith('../') ? '' : '../') + path;
        img.classList.remove('hidden');
        ph.classList.add('hidden');
        if (remBtn) remBtn.classList.remove('hidden');
        if (nameEl) nameEl.textContent = '';
    } else {
        img.src = '';
        img.classList.add('hidden');
        ph.classList.remove('hidden');
        if (remBtn) remBtn.classList.add('hidden');
        if (nameEl) nameEl.textContent = '';
    }
}

function onLogoFileSelected(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    
    // Client-side validations
    const allowed = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowed.includes(ext)) {
        showAlert('Invalid file type. Please select a PNG, JPG, JPEG, WEBP, or SVG image.', 'error');
        input.value = '';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        showAlert('File size exceeds the 2MB limit.', 'error');
        input.value = '';
        return;
    }

    _selectedLogoFile = file;
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById('b_logo_img');
        const ph = document.getElementById('b_logo_ph');
        const nameEl = document.getElementById('b_logo_selected_name');
        if (img && ph) {
            img.src = e.target.result;
            img.classList.remove('hidden');
            ph.classList.add('hidden');
            if (nameEl) nameEl.textContent = `Selected: ${file.name} (${(file.size/1024).toFixed(1)} KB)`;
        }
    };
    reader.readAsDataURL(file);
}

async function removeSocietyLogo() {
    if (!await showConfirm('Remove custom society logo and revert to the default EyeSense logo?')) return;
    
    const btn = document.getElementById('b_remove_logo_btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Removing…'; }
    
    try {
        const fd = new FormData();
        fd.append('action', 'save_branding');
        fd.append('remove_logo', '1');
        
        const res = await fetch('../society/settings_api.php', { method: 'POST', body: fd });
        const d = await res.json();
        if (!d.success) throw new Error(d.error || 'Failed to remove logo');
        
        _selectedLogoFile = null;
        _currentLogoPath = null;
        const fileInput = document.getElementById('b_logo_file');
        if (fileInput) fileInput.value = '';
        updateLogoPreviewUI(null);
        showAlert('Logo removed successfully.', 'success');
    } catch (e) {
        showAlert(e.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash mr-1.5"></i> Remove Logo'; }
    }
}

function onColorPickerChange(hex) {
    if (!hex) return;
    const picker = document.getElementById('b_theme_color_picker');
    const text = document.getElementById('b_theme_color_text');
    const err = document.getElementById('b_color_error');
    if (picker) picker.value = hex;
    if (text) text.value = hex.toUpperCase();
    if (err) err.classList.add('hidden');
}

function onColorTextChange(val) {
    val = val.trim();
    if (!val.startsWith('#') && val.length > 0) val = '#' + val;
    const errEl = document.getElementById('b_color_error');
    const picker = document.getElementById('b_theme_color_picker');
    if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        if (picker) picker.value = val;
        if (errEl) errEl.classList.add('hidden');
    } else {
        if (errEl) {
            errEl.textContent = 'Hex color must be in format #RRGGBB (e.g. #6366F1)';
            errEl.classList.remove('hidden');
        }
    }
}

function onWelcomeMsgInput() {
    const el = document.getElementById('b_welcome_message');
    const counter = document.getElementById('b_welcome_counter');
    const saveBtn = document.getElementById('brandingSaveBtn');
    if (!el || !counter) return;
    const len = (el.value || '').length;
    counter.textContent = `${len} / 500 characters`;
    if (len > 500) {
        counter.classList.add('text-red-400');
        if (saveBtn) saveBtn.disabled = true;
    } else {
        counter.classList.remove('text-red-400');
        if (saveBtn) saveBtn.disabled = false;
    }
}

async function saveBrandingSettings() {
    const colorVal = document.getElementById('b_theme_color_text')?.value.trim() || '';
    if (colorVal && !/^#[0-9A-Fa-f]{6}$/.test(colorVal)) {
        const errEl = document.getElementById('b_color_error');
        if (errEl) {
            errEl.textContent = 'Please enter a valid 6-character hex color (e.g. #6366F1)';
            errEl.classList.remove('hidden');
        }
        return showAlert('Invalid theme color format. Please enter #RRGGBB.', 'error');
    }

    const welcomeMsg = document.getElementById('b_welcome_message')?.value || '';
    if (welcomeMsg.length > 500) {
        return showAlert('Welcome message exceeds the 500 character limit.', 'error');
    }

    // Prepare emergency contacts array
    const contacts = [];
    for (let i = 0; i < 5; i++) {
        const label = document.getElementById(`ec_label_${i}`)?.value || '';
        const phone = document.getElementById(`ec_phone_${i}`)?.value.trim() || '';
        if (label && phone) {
            contacts.push({ label, phone });
        }
    }

    const saveBtn = document.getElementById('brandingSaveBtn');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving…';
    }

    try {
        const fd = new FormData();
        fd.append('action', 'save_branding');
        fd.append('theme_color', colorVal);
        fd.append('welcome_message', welcomeMsg);
        fd.append('emergency_contacts', JSON.stringify(contacts));
        
        if (_selectedLogoFile) {
            fd.append('logo', _selectedLogoFile);
        }

        const res = await fetch('../society/settings_api.php', { method: 'POST', body: fd });
        const d = await res.json();
        if (!d.success) throw new Error(d.error || 'Failed to save branding');

        _selectedLogoFile = null;
        const fileInput = document.getElementById('b_logo_file');
        if (fileInput) fileInput.value = '';
        
        if (d.data && d.data.logo_path) {
            _currentLogoPath = d.data.logo_path;
            updateLogoPreviewUI(_currentLogoPath);
        }

        showAlert('Society Branding settings saved successfully! Reload to see changes across portal.', 'success');
    } catch (e) {
        showAlert(e.message, 'error');
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Branding Settings';
        }
    }
}

loadSettings();
loadRateMap();

</script>

