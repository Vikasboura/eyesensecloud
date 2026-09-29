<?php
/** EyeSense Cloud Portal — Backup Download (SA only) */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'DOWNLOAD_SQL');
$cid = $session['client_id'];
$isSA = $session['is_superadmin'] ?? false;

// Load clients list for SA
$clients = [];
if ($isSA) {
    $clients = $pdo->query("SELECT client_id, CONCAT(first_name, ' ', last_name) AS company_name FROM clients ORDER BY company_name")->fetchAll();
}

// Download history from audit logs
$history = $pdo->prepare("SELECT performed_at, performed_by, detail FROM portal_audit_logs WHERE client_id=? AND action='BACKUP_DOWNLOAD' ORDER BY performed_at DESC LIMIT 10");
$history->execute([$cid]);
$downloads = $history->fetchAll();
?>

<div class="h-16 border-b border-border bg-surface flex items-center px-6 flex-shrink-0">
    <div><h1 class="font-bold text-lg text-textMain">Backup Download</h1><p class="text-xs text-textSec">Export .sql backup file</p></div>
</div>

<div class="flex-1 overflow-y-auto p-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- Download Panel -->
        <div class="glass-panel rounded-xl p-6">
            <h3 class="font-bold text-base mb-1"><i class="fas fa-cloud-arrow-down text-green-400 mr-2"></i>Download Backup</h3>
            <p class="text-xs text-textSec mb-5">Downloads as <span class="font-mono text-green-400">.sql</span> file.</p>
            <div class="space-y-4">
                <?php if ($isSA && count($clients) > 1): ?>
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Client</label>
                    <select id="dl-client" class="w-full text-sm">
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= htmlspecialchars($c['client_id']) ?>" <?= $c['client_id']===$cid?'selected':'' ?>><?= htmlspecialchars($c['company_name']) ?> (<?= $c['client_id'] ?>)</option>
                        <?php endforeach; ?>
                    </select></div>
                <?php endif; ?>
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Date Range</label>
                    <div class="flex gap-2"><input type="date" id="dl-from" class="flex-1 text-sm"><input type="date" id="dl-to" class="flex-1 text-sm"></div>
                    <p class="text-xs text-textSec mt-1">Leave empty to download entire backup</p></div>
                <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Tables to Include</label>
                    <div class="grid grid-cols-2 gap-2 mt-2" id="dl-tables">
                        <?php foreach (['face_logs','vehicle_logs','access_requests','snapshot_logs','video_logs','audit_logs','guests','daily_summary','bulk_detection_alerts'] as $t): ?>
                        <label class="flex items-center gap-2 text-sm text-textSec"><input type="checkbox" checked value="<?= $t ?>"><span><?= ucwords(str_replace('_',' ',$t)) ?></span></label>
                        <?php endforeach; ?>
                    </div></div>
                <div class="flex gap-3 mt-4">
                    <button onclick="downloadBackup('sql')" class="btn-primary flex-1 py-3"><i class="fas fa-database mr-2"></i>Download .sql</button>
                    <button onclick="downloadBackup('zip')" class="bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-400 border border-indigo-500/30 rounded-lg flex-1 py-3 font-bold text-sm transition" title="Extracts images and videos into a zip archive"><i class="fas fa-file-zipper mr-2"></i>Download Media .zip</button>
                </div>
            </div>
        </div>

        <!-- Restore Guide -->
        <div class="glass-panel rounded-xl p-6">
            <h3 class="font-bold text-base mb-1"><i class="fas fa-rotate-left text-yellow-400 mr-2"></i>How to Restore</h3>
            <p class="text-xs text-textSec mb-5">Manual restore steps</p>
            <div class="space-y-4">
                <div class="flex gap-3"><div class="w-7 h-7 rounded-full bg-indigo-500/20 text-primary flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">1</div><div><div class="text-sm font-medium text-textMain">Download the .sql file</div><div class="text-xs text-textSec mt-0.5">Use the panel on the left</div></div></div>
                <div class="flex gap-3"><div class="w-7 h-7 rounded-full bg-indigo-500/20 text-primary flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">2</div><div><div class="text-sm font-medium text-textMain">Go to EyeSense Settings</div><div class="text-xs text-textSec mt-0.5">Dashboard → Settings → Cloud Backup tab</div></div></div>
                <div class="flex gap-3"><div class="w-7 h-7 rounded-full bg-indigo-500/20 text-primary flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">3</div><div><div class="text-sm font-medium text-textMain">Upload the .sql file</div><div class="text-xs text-textSec mt-0.5">Manual Restore panel → Run Restore</div></div></div>
                <div class="flex gap-3"><div class="w-7 h-7 rounded-full bg-green-500/20 text-success flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5"><i class="fas fa-check text-xs"></i></div><div><div class="text-sm font-medium text-textMain">Data restored</div><div class="text-xs text-textSec mt-0.5">Records imported via INSERT IGNORE</div></div></div>
                <div class="mt-4 p-3 rounded-lg border border-yellow-900/40 bg-yellow-900/10 text-xs text-yellow-400"><i class="fas fa-triangle-exclamation mr-1"></i>Restore uses INSERT IGNORE — existing records are never overwritten.</div>
            </div>
        </div>
    </div>

    <!-- Download History -->
    <div class="glass-panel rounded-xl p-5 mt-5">
        <h3 class="text-sm font-bold text-textSec uppercase tracking-wider mb-4"><i class="fas fa-history text-textSec mr-2"></i>Download History</h3>
        <table><thead><tr><th>Generated At</th><th>By</th><th>Details</th></tr></thead>
        <tbody>
        <?php if (empty($downloads)): ?>
            <tr><td colspan="3" class="text-center text-textSec py-6">No downloads yet</td></tr>
        <?php else: foreach ($downloads as $d): ?>
            <tr><td class="font-mono text-xs text-textSec"><?= $d['performed_at'] ?></td><td><?= htmlspecialchars($d['performed_by']) ?></td><td class="text-xs text-textSec"><?= htmlspecialchars($d['detail']) ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table>
    </div>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
<script>
function downloadBackup(type = 'sql'){
    const tables=[...document.querySelectorAll('#dl-tables input:checked')].map(c=>c.value);
    if(!tables.length){showAlert('Select at least one table','warning');return;}
    const client=document.getElementById('dl-client')?.value||PORTAL.clientId;
    const from=document.getElementById('dl-from').value, to=document.getElementById('dl-to').value;
    const endpoint = type === 'zip' ? '../media_export.php' : '../backup_export.php';
    let url=`${endpoint}?client_id=${encodeURIComponent(client)}&tables=${tables.join(',')}`;
    if(from)url+=`&from=${from}`;if(to)url+=`&to=${to}`;
    window.location.href=url;
}
</script>
