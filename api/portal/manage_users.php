<?php
/** EyeSense Cloud Portal — Synced Staff List */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_USERS');
$cid = $session['client_id'];

// Fetch from the mirrored `employees` table
$users = $pdo->prepare("
    SELECT employee_id, full_name, role, access_level, email, status, created_at, (password_hash IS NOT NULL AND password_hash != '') as has_pw 
    FROM employees 
    WHERE client_id=? 
    ORDER BY created_at DESC
");
$users->execute([$cid]);
$userList = $users->fetchAll();
?>

<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0"><h1 class="font-bold text-base md:text-lg text-textMain truncate">Synced Staff Directory</h1><p class="text-xs text-textSec hidden sm:block">View staff accounts synced from EyeSense</p></div>
</div>

<div class="flex-1 overflow-y-auto p-6">
    <div class="glass-panel rounded-xl p-5 mb-4 border border-blue-900/50 bg-blue-900/10 flex items-center gap-3">
        <i class="fas fa-satellite-dish text-blue-400 text-2xl"></i>
        <div>
            <p class="text-sm text-blue-200">This list is automatically synced from your local EyeSense system. To add, edit, or remove staff members, please make the changes in your local EyeSense dashboard.</p>
            <p class="text-xs text-blue-400 mt-1">Staff with <b>L1</b> or <b>ADMIN</b> access level automatically receive Super Admin access on the cloud portal.</p>
        </div>
    </div>

    <div class="glass-panel rounded-xl overflow-hidden">
        <div class="table-responsive">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-border bg-surfaceLight/50">
                    <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Employee</th>
                    <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Role / Level</th>
                    <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Portal Access</th>
                    <th class="p-4 text-xs font-bold text-textSec uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($userList)): ?>
                <tr><td colspan="4" class="p-8 text-center text-textSec">No staff synced yet. Ensure the EyeSense backup agent is running.</td></tr>
            <?php else: foreach ($userList as $u):
                $isSuperAdmin = in_array(strtoupper($u['access_level'] ?? ''), ['L1', 'ADMIN']);
                $roleLabel = htmlspecialchars(get_friendly_role_name($u['role'] ?: 'Unassigned'));
                $levelLabel = htmlspecialchars($u['access_level']);
            ?>
            <tr class="border-b border-border/50 hover:bg-textMain/30">
                <td class="p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-xs font-bold">
                            <?= strtoupper(substr($u['full_name'] ?: $u['employee_id'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="font-bold text-textMain"><?= htmlspecialchars($u['full_name']) ?></div>
                            <div class="text-xs text-textSec"><?= htmlspecialchars($u['employee_id']) ?></div>
                        </div>
                    </div>
                </td>
                <td class="p-4">
                    <span class="badge bg-textMain border border-border text-textSec"><?= $roleLabel ?></span>
                    <span class="badge ml-1 <?= $isSuperAdmin ? 'bg-indigo-900/40 text-indigo-400' : 'bg-textMain text-textSec' ?>"><?= $levelLabel ?></span>
                </td>
                <td class="p-4">
                    <?php if ($u['status'] !== 'active'): ?>
                        <span class="text-xs text-red-400"><i class="fas fa-ban mr-1"></i>Suspended locally</span>
                    <?php elseif (!$u['has_pw']): ?>
                        <span class="text-xs text-yellow-400"><i class="fas fa-exclamation-triangle mr-1"></i>No password set</span>
                    <?php elseif ($isSuperAdmin): ?>
                        <span class="text-xs text-indigo-400 font-bold"><i class="fas fa-crown mr-1"></i>Super Admin</span>
                    <?php else: ?>
                        <span class="text-xs text-green-400"><i class="fas fa-check mr-1"></i>Can login</span>
                    <?php endif; ?>
                </td>
                <td class="p-4">
                    <?php if ($u['status'] === 'active'): ?>
                        <span class="badge bg-green-900/20 border border-green-800/50 text-green-400">Active</span>
                    <?php else: ?>
                        <span class="badge bg-red-900/20 border border-red-800/50 text-red-400"><?= htmlspecialchars(ucfirst($u['status'])) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
