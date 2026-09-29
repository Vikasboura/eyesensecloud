<?php
/** EyeSense Cloud Portal — Retention Settings */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_ROLES');
$cid = $session['client_id'];

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $snap  = max(7,  (int)($input['snapshots'] ?? 90));
    $vid   = max(7,  (int)($input['videos']    ?? 30));
    $logs  = max(30, (int)($input['logs']       ?? 180));
    $audit = max(90, (int)($input['audit']      ?? 365));

    $stmt = $pdo->prepare("INSERT INTO portal_retention_settings (client_id,snapshots_days,videos_days,logs_days,audit_days) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE snapshots_days=VALUES(snapshots_days),videos_days=VALUES(videos_days),logs_days=VALUES(logs_days),audit_days=VALUES(audit_days),updated_at=NOW()");
    $stmt->execute([$cid, $snap, $vid, $logs, $audit]);
    logAudit($pdo, $cid, $session['username'], 'RETENTION_UPDATE', "snap=$snap vid=$vid logs=$logs audit=$audit");
    json_response(['success'=>true]);
    exit;
}

// Load current settings
$ret = $pdo->prepare("SELECT * FROM portal_retention_settings WHERE client_id=?");
$ret->execute([$cid]);
$settings = $ret->fetch() ?: ['snapshots_days'=>90,'videos_days'=>30,'logs_days'=>180,'audit_days'=>365];
?>

<div class="h-16 border-b border-border bg-surface flex items-center px-6 flex-shrink-0">
    <div><h1 class="font-bold text-lg text-textMain">Retention Settings</h1><p class="text-xs text-textSec">Configure auto-delete rules</p></div>
</div>

<div class="flex-1 overflow-y-auto p-6">
    <div class="glass-panel rounded-xl p-6 max-w-lg">
        <h3 class="font-bold text-base mb-1"><i class="fas fa-clock-rotate-left text-textSec mr-2"></i>Retention Settings</h3>
        <p class="text-xs text-textSec mb-5">Configure how long backed-up data is kept before automatic deletion.</p>
        <div class="space-y-4">
            <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Snapshots</label>
                <div class="flex items-center gap-3"><input type="number" id="ret-snapshots" value="<?= $settings['snapshots_days'] ?>" min="7" class="w-24 text-sm"><span class="text-sm text-textSec">days</span></div></div>
            <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Video Clips</label>
                <div class="flex items-center gap-3"><input type="number" id="ret-videos" value="<?= $settings['videos_days'] ?>" min="7" class="w-24 text-sm"><span class="text-sm text-textSec">days</span></div></div>
            <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Event Logs</label>
                <div class="flex items-center gap-3"><input type="number" id="ret-logs" value="<?= $settings['logs_days'] ?>" min="30" class="w-24 text-sm"><span class="text-sm text-textSec">days</span></div></div>
            <div><label class="block text-xs uppercase text-textSec font-bold mb-1">Audit Logs</label>
                <div class="flex items-center gap-3"><input type="number" id="ret-audit" value="<?= $settings['audit_days'] ?>" min="90" class="w-24 text-sm"><span class="text-sm text-textSec">days (min 90)</span></div></div>
            <button onclick="saveRetention()" class="btn-primary w-full py-3 mt-2"><i class="fas fa-save mr-2"></i>Save Retention Settings</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
<script>
async function saveRetention(){
    const data = {
        snapshots: parseInt(document.getElementById('ret-snapshots').value),
        videos:    parseInt(document.getElementById('ret-videos').value),
        logs:      parseInt(document.getElementById('ret-logs').value),
        audit:     parseInt(document.getElementById('ret-audit').value)
    };
    const res = await fetch('retention.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    const r = await res.json();
    showAlert(r.success ? 'Retention settings saved successfully' : (r.error||'Failed'), r.success?'success':'error');
}
</script>
