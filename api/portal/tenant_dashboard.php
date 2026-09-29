<?php
/**
 * EyeSense Cloud Portal — Tenant Dashboard
 */

require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
requirePortalLogin();

$pdo = get_indsac_db();
$session = getPortalSession();

// Ensure user role is indeed Tenant
if (strtolower($session['role'] ?? '') !== 'tenant') {
    header('Location: dashboard.php');
    exit;
}

$tenantId = $_SESSION['portal_tenant_id'] ?? 0;
$clientId = $session['client_id'];

$tenant = [];
$landlord = [];
$societyName = 'Our Society';

try {
    // Fetch tenant details
    $tStmt = $pdo->prepare("SELECT * FROM society_tenants WHERE id = ? AND client_id = ? LIMIT 1");
    $tStmt->execute([$tenantId, $clientId]);
    $tenant = $tStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if ($tenant) {
        // Fetch landlord/member details
        $mStmt = $pdo->prepare("SELECT full_name, mobile, email, flat_number FROM society_members WHERE id = ? AND client_id = ? LIMIT 1");
        $mStmt->execute([$tenant['member_id'], $clientId]);
        $landlord = $mStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // Fetch society name
    $sStmt = $pdo->prepare("SELECT society_name FROM maintenance_settings WHERE client_id = ? LIMIT 1");
    $sStmt->execute([$clientId]);
    $societyName = $sStmt->fetchColumn() ?: 'Our Society';

} catch (Exception $e) {
    // Fallback
}

$currentPage = 'tenant_dashboard';
require_once __DIR__ . '/portal_header.php';
?>

<!-- TOPBAR -->
<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex items-center justify-between px-4 md:px-6 py-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0">
        <h1 class="font-bold text-base md:text-lg text-textMain truncate">Tenant Dashboard</h1>
        <p class="text-xs text-textSec hidden sm:block">Welcome back, <?= htmlspecialchars($session['username'] ?? 'Tenant') ?>!</p>
    </div>
    <div class="flex items-center gap-3 flex-shrink-0">
        <button onclick="openChangePasswordModal()" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500/20 transition cursor-pointer flex items-center gap-1.5">
            <i class="fas fa-key text-[11px]"></i> Change Password
        </button>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
            <i class="fas fa-circle-check mr-1"></i>Active Tenancy
        </span>
    </div>
</div>

<div class="flex-1 overflow-y-auto p-6 space-y-6">
    <!-- Welcome Header / Society Info Card -->
    <div class="relative overflow-hidden rounded-2xl border border-indigo-500/20 bg-gradient-to-r from-indigo-950/40 via-purple-950/20 to-slate-900/60 p-6 md:p-8 shadow-2xl">
        <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full bg-indigo-500/10 blur-3xl"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-400">Resident Portal</span>
                <h2 class="text-2xl md:text-3xl font-extrabold text-textMain mt-1"><?= htmlspecialchars($societyName) ?></h2>
                <p class="text-sm text-textSec mt-2 max-w-xl">You are registered as an active tenant. Access your tenancy details, verification docs, and landlord contact information below.</p>
            </div>
            <div class="flex items-center gap-4 bg-black/30 border border-border rounded-xl p-4 self-start md:self-auto">
                <div class="w-12 h-12 rounded-lg bg-indigo-500/15 flex items-center justify-center text-indigo-400 text-xl font-bold">
                    <i class="fas fa-door-open"></i>
                </div>
                <div>
                    <div class="text-xs text-textSec font-semibold">FLAT / ROOM</div>
                    <div class="text-lg font-bold text-textMain"><?= htmlspecialchars($tenant['flat_number'] ?? 'N/A') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Landlord Details Card -->
        <div class="glass-panel rounded-2xl border border-border p-6 shadow-xl flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400 text-sm">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-textSec">Landlord / Owner</h3>
                </div>
                <?php if (!empty($landlord)): ?>
                    <div class="space-y-4">
                        <div>
                            <div class="text-xs text-textSec">Full Name</div>
                            <div class="text-base font-bold text-textMain"><?= htmlspecialchars($landlord['full_name']) ?></div>
                        </div>
                        <div>
                            <div class="text-xs text-textSec">Mobile Number</div>
                            <div class="text-base font-bold text-textMain font-mono"><?= htmlspecialchars($landlord['mobile'] ?: 'N/A') ?></div>
                        </div>
                        <div>
                            <div class="text-xs text-textSec">Email Address</div>
                            <div class="text-base font-bold text-textMain"><?= htmlspecialchars($landlord['email'] ?: 'N/A') ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-textSec italic">Landlord details not found.</p>
                <?php endif; ?>
            </div>
            <div class="mt-6 pt-4 border-t border-border flex gap-2">
                <?php if (!empty($landlord['mobile'])): ?>
                    <a href="tel:<?= htmlspecialchars($landlord['mobile']) ?>" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-center font-bold text-sm text-white transition no-underline">
                        <i class="fas fa-phone mr-1.5"></i> Call Owner
                    </a>
                <?php endif; ?>
                <?php if (!empty($landlord['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($landlord['email']) ?>" class="flex-1 px-4 py-2.5 rounded-xl bg-surfaceLight hover:bg-border text-center font-bold text-sm text-textSec hover:text-textMain transition no-underline border border-border">
                        <i class="fas fa-envelope mr-1.5"></i> Email Owner
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tenancy details Card -->
        <div class="lg:col-span-2 glass-panel rounded-2xl border border-border p-6 shadow-xl">
            <div class="flex items-center gap-2 mb-6">
                <div class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400 text-sm">
                    <i class="fas fa-house-chimney-user"></i>
                </div>
                <h3 class="text-sm font-bold uppercase tracking-wider text-textSec">Tenancy Details</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="flex justify-between py-2 border-b border-border/40">
                        <span class="text-sm text-textSec">Full Name</span>
                        <span class="text-sm font-bold text-textMain"><?= htmlspecialchars($tenant['full_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border/40">
                        <span class="text-sm text-textSec">Mobile</span>
                        <span class="text-sm font-bold text-textMain font-mono"><?= htmlspecialchars($tenant['mobile'] ?? 'N/A') ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border/40">
                        <span class="text-sm text-textSec">Email</span>
                        <span class="text-sm font-bold text-textMain"><?= htmlspecialchars($tenant['email'] ?? 'N/A') ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border/40">
                        <span class="text-sm text-textSec">Gender / DOB</span>
                        <span class="text-sm font-bold text-textMain"><?= htmlspecialchars($tenant['gender'] ?? 'N/A') ?> <?= !empty($tenant['dob']) ? '('.date('d M Y', strtotime($tenant['dob'])).')' : '' ?></span>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex justify-between py-2 border-b border-border/40">
                        <span class="text-sm text-textSec">Move-in Date</span>
                        <span class="text-sm font-bold text-textMain"><?= !empty($tenant['occupancy_start']) ? date('d M Y', strtotime($tenant['occupancy_start'])) : 'N/A' ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border/40">
                        <span class="text-sm text-textSec">Move-out Date</span>
                        <span class="text-sm font-bold text-textMain"><?= !empty($tenant['occupancy_end']) ? date('d M Y', strtotime($tenant['occupancy_end'])) : 'N/A' ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border/40">
                        <span class="text-sm text-textSec">Block / Floor</span>
                        <span class="text-sm font-bold text-textMain"><?= htmlspecialchars(($tenant['block_wing'] ? $tenant['block_wing'] . ', ' : '') . ($tenant['floor_number'] ? $tenant['floor_number'] . ' Floor' : 'N/A')) ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border/40">
                        <span class="text-sm text-textSec">Purpose</span>
                        <span class="text-sm font-bold text-textMain"><?= htmlspecialchars($tenant['purpose'] ?? 'N/A') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row: Identity & Emergency Contacts -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Identity Verification Doc -->
        <div class="glass-panel rounded-2xl border border-border p-6 shadow-xl">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center text-purple-400 text-sm">
                    <i class="fas fa-id-card"></i>
                </div>
                <h3 class="text-sm font-bold uppercase tracking-wider text-textSec">Identity Verification</h3>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center py-2 border-b border-border/40">
                    <span class="text-sm text-textSec">ID Document Type</span>
                    <span class="text-sm font-bold text-textMain"><?= htmlspecialchars($tenant['govt_id_type'] ?? 'N/A') ?></span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-border/40">
                    <span class="text-sm text-textSec">ID Document Number</span>
                    <span class="text-sm font-bold text-textMain font-mono"><?= htmlspecialchars($tenant['govt_id_number'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($tenant['govt_id_doc_path'])): ?>
                    <div class="pt-2">
                        <a href="../<?= htmlspecialchars($tenant['govt_id_doc_path']) ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-purple-500/15 border border-purple-500/30 hover:border-purple-500/60 text-purple-400 hover:text-purple-300 font-bold text-sm transition no-underline">
                            <i class="fas fa-file-pdf"></i> View Uploaded Document
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-textSec italic">No document uploaded.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Emergency Contact Card -->
        <div class="glass-panel rounded-2xl border border-border p-6 shadow-xl">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center text-red-400 text-sm">
                    <i class="fas fa-life-ring"></i>
                </div>
                <h3 class="text-sm font-bold uppercase tracking-wider text-textSec">Emergency Contact</h3>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center py-2 border-b border-border/40">
                    <span class="text-sm text-textSec">Emergency Contact Name</span>
                    <span class="text-sm font-bold text-textMain"><?= htmlspecialchars($tenant['emergency_contact_name'] ?? 'N/A') ?></span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-border/40">
                    <span class="text-sm text-textSec">Emergency Contact Number</span>
                    <span class="text-sm font-bold text-textMain font-mono"><?= htmlspecialchars($tenant['emergency_contact_number'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($tenant['emergency_contact_number'])): ?>
                    <div class="pt-2">
                        <a href="tel:<?= htmlspecialchars($tenant['emergency_contact_number']) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-500/15 border border-red-500/30 hover:border-red-500/60 text-red-400 hover:text-red-300 font-bold text-sm transition no-underline">
                            <i class="fas fa-phone-alt"></i> Call Emergency Contact
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div id="passwordModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl max-w-md w-full shadow-2xl overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-border flex items-center justify-between">
            <h3 class="text-lg font-bold text-textMain flex items-center gap-2">
                <i class="fas fa-key text-indigo-500"></i> Change Password
            </h3>
            <button onclick="closePasswordModal()" class="text-textSec hover:text-textMain text-xl transition">&times;</button>
        </div>
        <!-- Modal Body -->
        <form id="changePasswordForm" onsubmit="submitChangePassword(event)" class="px-6 py-4 space-y-4">
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-textSec uppercase tracking-wider">Current Password</label>
                <input type="password" id="oldPassword" required class="w-full bg-surfaceLight border border-border rounded-xl px-4 py-2.5 text-textMain focus:border-indigo-500 outline-none text-sm transition">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-textSec uppercase tracking-wider">New Password</label>
                <input type="password" id="newPassword" required minlength="6" class="w-full bg-surfaceLight border border-border rounded-xl px-4 py-2.5 text-textMain focus:border-indigo-500 outline-none text-sm transition">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-textSec uppercase tracking-wider">Confirm New Password</label>
                <input type="password" id="confirmPassword" required minlength="6" class="w-full bg-surfaceLight border border-border rounded-xl px-4 py-2.5 text-textMain focus:border-indigo-500 outline-none text-sm transition">
            </div>
            <div id="passwordError" class="text-xs text-rose-500 [data-theme='dark']:text-rose-400 hidden"></div>
            <div id="passwordSuccess" class="text-xs text-emerald-500 [data-theme='dark']:text-emerald-400 hidden"></div>
            <!-- Modal Footer -->
            <div class="pt-4 border-t border-border flex justify-end gap-3">
                <button type="button" onclick="closePasswordModal()" class="px-4 py-2 rounded-xl bg-surfaceLight border border-border text-textSec hover:text-textMain text-sm font-semibold transition">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold transition flex items-center gap-1.5 shadow-lg shadow-indigo-500/20">
                    <i class="fas fa-save"></i> Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openChangePasswordModal() {
    document.getElementById('passwordModal').classList.remove('hidden');
    document.getElementById('passwordModal').classList.add('flex');
    document.getElementById('changePasswordForm').reset();
    document.getElementById('passwordError').classList.add('hidden');
    document.getElementById('passwordSuccess').classList.add('hidden');
}

function closePasswordModal() {
    document.getElementById('passwordModal').classList.add('hidden');
    document.getElementById('passwordModal').classList.remove('flex');
}

async function submitChangePassword(e) {
    e.preventDefault();
    const old_password = document.getElementById('oldPassword').value;
    const new_password = document.getElementById('newPassword').value;
    const confirm_password = document.getElementById('confirmPassword').value;
    const errorEl = document.getElementById('passwordError');
    const successEl = document.getElementById('passwordSuccess');
    
    errorEl.classList.add('hidden');
    successEl.classList.add('hidden');
    
    if (new_password !== confirm_password) {
        errorEl.textContent = "New passwords do not match.";
        errorEl.classList.remove('hidden');
        return;
    }
    
    try {
        const r = await fetch('../tenant_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'change_password',
                old_password: old_password,
                new_password: new_password
            })
        });
        const res = await r.json();
        if (res.success) {
            successEl.textContent = res.message || "Password updated successfully!";
            successEl.classList.remove('hidden');
            setTimeout(() => {
                closePasswordModal();
            }, 1500);
        } else {
            errorEl.textContent = res.error || "Failed to update password.";
            errorEl.classList.remove('hidden');
        }
    } catch (err) {
        errorEl.textContent = "An error occurred. Please try again.";
        errorEl.classList.remove('hidden');
    }
}
</script>

<?php
require_once __DIR__ . '/portal_footer.php';
?>
