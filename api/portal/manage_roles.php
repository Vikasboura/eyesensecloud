<?php
/** EyeSense Cloud Portal — Manage Roles (SA only) */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_ROLES');

$cid = $session['client_id'];

// Fetch all roles from the synced `roles` table (including default ones with empty client_id)
$stmt = $pdo->prepare("SELECT name FROM roles WHERE client_id = ? OR client_id = '' GROUP BY name ORDER BY MIN(role_rank) ASC, name ASC");
$stmt->execute([$cid]);
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Load all permissions for all roles
$permMeta = [
    ['key'=>'VIEW_SNAPSHOTS',  'label'=>'View Snapshots',   'desc'=>'Browse snapshot gallery'],
    ['key'=>'VIEW_VIDEOS',     'label'=>'View Video Clips',  'desc'=>'Browse video recordings'],
    ['key'=>'VIEW_LOGS',       'label'=>'View Event Logs',   'desc'=>'Read event log tables'],
    ['key'=>'DOWNLOAD_FILES',  'label'=>'Download Files',    'desc'=>'Download individual snapshots/clips'],
    ['key'=>'DOWNLOAD_SQL',    'label'=>'Download .sql',     'desc'=>'Export full backup as .sql file'],
    ['key'=>'DELETE_RECORDS',  'label'=>'Delete Records',    'desc'=>'Permanently delete backed-up data'],
    ['key'=>'EDIT_RECORDS',    'label'=>'Edit Records',      'desc'=>'Edit backed-up metadata'],
    ['key'=>'MANAGE_USERS',    'label'=>'Manage Users',      'desc'=>'Add/edit/remove portal staff'],
    ['key'=>'MANAGE_ROLES',    'label'=>'Manage Roles',      'desc'=>'Configure role permissions'],
    ['key'=>'MANAGE_MAINTENANCE','label'=>'Manage Maintenance','desc'=>'Create members, set billing, verify receipts'],
    ['key'=>'VIEW_MAINTENANCE', 'label'=>'View My Dues',      'desc'=>'View own dues and upload payment receipts'],
];

$rolePerms = [];
$roleSlugs = []; // map role name → safe DOM id slug
foreach ($roles as $r) {
    $rolePerms[$r] = getAllPermissions($pdo, $cid, $r);
    $roleSlugs[$r] = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $r);
}
?>

<div class="h-16 border-b border-border bg-surface flex items-center px-6 flex-shrink-0">
    <div><h1 class="font-bold text-lg text-textMain">Manage Roles</h1><p class="text-xs text-textSec">Configure portal-specific permissions for EyeSense roles</p></div>
</div>

<div class="flex-1 overflow-y-auto p-6">
    <div class="glass-panel rounded-xl p-5 mb-4 border border-blue-900/50 bg-blue-900/10">
        <p class="text-sm text-blue-200"><i class="fas fa-info-circle text-blue-400 mr-2"></i>Configure what each <b>synced EyeSense role</b> can access on this cloud portal. Staff with <code>L1</code> or <code>ADMIN</code> access level are automatically considered Super Admins and have full access.</p>
    </div>
    
    <?php if (empty($roles)): ?>
    <div class="glass-panel rounded-xl p-8 text-center border-dashed border-border">
        <i class="fas fa-users-cog text-4xl text-textSec mb-3"></i>
        <h3 class="text-lg font-bold text-textSec">No Custom Roles Found</h3>
        <p class="text-sm text-textSec">Add staff with custom roles in your EyeSense application. Once the backup agent syncs them, they will appear here automatically.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($roles as $r): ?>
        <div class="glass-panel p-6 rounded-xl relative overflow-hidden group border border-border hover:border-primary/50 transition duration-300">
            <div class="absolute -right-4 -top-4 p-4 opacity-5 group-hover:opacity-10 transition duration-300">
                <i class="fas fa-shield-halved text-8xl text-textMain"></i>
            </div>
            <div class="flex justify-between items-start mb-5 relative z-10">
                <div>
                    <h3 class="font-bold text-xl text-textMain mb-1"><?= htmlspecialchars($r) ?></h3>
                    <p class="text-xs text-textSec uppercase tracking-widest font-semibold">EyeSense Custom Role</p>
                </div>
                <button onclick="openModal('<?= htmlspecialchars($roleSlugs[$r], ENT_QUOTES) ?>')" class="bg-primary/20 hover:bg-primary text-primary hover:text-textMain transition-colors duration-300 text-xs py-2 px-4 rounded-lg font-bold shadow-lg shadow-gray-300/50">
                    <i class="fas fa-pen mr-2"></i>Edit
                </button>
            </div>
            
            <div class="pt-5 border-t border-border/50 relative z-10">
                <div class="text-[10px] text-textSec mb-3 uppercase tracking-widest font-bold">Enabled Portal Permissions</div>
                <div class="flex flex-wrap gap-2">
                    <?php
                    $hasPerms = false;
                    foreach ($permMeta as $p) {
                        if (!empty($rolePerms[$r][$p['key']])) {
                            echo '<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">' . $p['label'] . '</span>';
                            $hasPerms = true;
                        }
                    }
                    if (!$hasPerms) echo '<span class="text-xs text-textSec italic">No permissions granted yet.</span>';
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Modal for this role -->
        <div id="modal-<?= htmlspecialchars($roleSlugs[$r], ENT_QUOTES) ?>" class="fixed inset-0 z-[100] hidden bg-gray-500/60 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity">
            <div class="bg-surface border border-border rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
                <div class="p-6 border-b border-border flex justify-between items-center shrink-0 bg-surfaceLight">
                    <div>
                        <h2 class="font-bold text-xl text-textMain">Edit Permissions</h2>
                        <p class="text-sm text-textSec mt-1">Role: <strong class="text-primary"><?= htmlspecialchars($r) ?></strong></p>
                    </div>
                    <button onclick="closeModal('<?= htmlspecialchars($roleSlugs[$r], ENT_QUOTES) ?>')" class="text-textSec hover:text-textMain transition bg-textMain hover:bg-gray-700 w-8 h-8 rounded-full flex items-center justify-center"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-6 overflow-y-auto flex-1 space-y-3 bg-surface">
                    <?php foreach ($permMeta as $p): $checked = !empty($rolePerms[$r][$p['key']]); ?>
                    <div class="flex justify-between items-center p-4 rounded-xl bg-surface border border-border hover:border-gray-600 transition duration-200">
                        <div>
                            <div class="font-bold text-sm text-gray-700"><?= $p['label'] ?></div>
                            <div class="text-xs text-textSec mt-1"><?= $p['desc'] ?></div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" <?= $checked ? 'checked' : '' ?> onchange="updatePermission('<?= htmlspecialchars($r, ENT_QUOTES) ?>','<?= $p['key'] ?>',this.checked)" data-role="<?= htmlspecialchars($r, ENT_QUOTES) ?>">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="p-5 border-t border-border shrink-0 bg-surfaceLight flex justify-end">
                    <button onclick="closeModal('<?= htmlspecialchars($roleSlugs[$r], ENT_QUOTES) ?>')" class="bg-indigo-600 hover:bg-indigo-500 text-textMain px-6 py-2.5 rounded-lg text-sm font-bold shadow-lg transition">Done</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
<script>
function openModal(slug) { 
    document.getElementById('modal-' + slug).classList.remove('hidden'); 
    document.body.style.overflow = 'hidden';
}
function closeModal(slug) { 
    document.getElementById('modal-' + slug).classList.add('hidden'); 
    document.body.style.overflow = '';
}
// Close modal on outside click
document.addEventListener('click', (e) => {
    if (e.target.id && e.target.id.startsWith('modal-')) {
        e.target.classList.add('hidden');
        document.body.style.overflow = '';
    }
});
async function updatePermission(role, permission, granted) {
    try {
        const res = await fetch('../update_permission.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({role, permission, granted})
        });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || 'Failed');

        // Toast
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-5 right-5 z-[9999] px-4 py-2 rounded-lg text-sm font-bold text-textMain bg-indigo-600 shadow-lg';
        toast.textContent = `${role} → ${permission}: ${granted?'Granted':'Revoked'}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    } catch (e) {
        showAlert('Failed to update permission: ' + e.message, 'error');
    }
}
</script>
