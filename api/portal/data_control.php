<?php
/** EyeSense Cloud Portal — Data Control (v2 — mirrored tables, SA only) */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'DELETE_RECORDS');
$cid = $session['client_id'];

// Whitelist of deletable tables
$ALLOWED = ['face_logs','vehicle_logs','access_requests','snapshot_logs','video_logs','alert_logs','audit_logs','guests','daily_summary'];

// Handle AJAX delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    $table = $input['table'] ?? '';

    if (empty($ids) || !in_array($table, $ALLOWED)) {
        json_error('Invalid request');
    }

    $societyId = SocietyContextHolder::getCurrentSocietyId() ?: (int)($activeSocietyId ?? 0);
    $detectionTables = ['face_logs', 'vehicle_logs', 'snapshot_logs', 'video_logs'];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    if (in_array($table, $detectionTables, true)) {
        $params = array_merge([$societyId], $ids);
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE society_id=? AND id IN ($placeholders)");
    } else {
        $params = array_merge([$cid], $ids);
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE client_id=? AND id IN ($placeholders)");
    }
    $stmt->execute($params);
    $deleted = $stmt->rowCount();

    logAudit($pdo, $cid, $session['username'], 'DATA_DELETE', "Deleted $deleted rows from $table (IDs: " . implode(',', $ids) . ")");
    json_response(['success'=>true, 'deleted'=>$deleted]);
    exit;
}

$table = $_GET['table'] ?? 'face_logs';
if (!in_array($table, $ALLOWED)) $table = 'face_logs';

$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';

// Determine timestamp column
$tsCol = match($table) {
    'face_logs', 'vehicle_logs', 'alert_logs', 'audit_logs' => 'timestamp',
    'access_requests' => 'requested_at',
    'daily_summary' => 'generated_at',
    default => 'created_at'
};

$societyId = SocietyContextHolder::getCurrentSocietyId() ?: (int)($activeSocietyId ?? 0);
$detectionTables = ['face_logs', 'vehicle_logs', 'snapshot_logs', 'video_logs'];

if (in_array($table, $detectionTables, true)) {
    $where = "society_id = ?";
    $params = [$societyId];
} else {
    $where = "client_id = ?";
    $params = [$cid];
}
if ($dateFrom) { $where .= " AND `$tsCol` >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where .= " AND `$tsCol` <= ?"; $params[] = $dateTo . ' 23:59:59'; }

// Display columns per table
$colConfig = [
    'face_logs' => ['id','camera_id','person_name','confidence','timestamp'],
    'vehicle_logs' => ['id','camera_id','plate_number','vehicle_type','timestamp'],
    'access_requests' => ['id','request_type','identifier','status','requested_at'],
    'snapshot_logs' => ['id','camera_id','category','name_label','created_at'],
    'video_logs' => ['id','camera_id','category','duration_seconds','created_at'],
    'alert_logs' => ['id','event_type','alert_level','channel','timestamp'],
    'audit_logs' => ['id','actor_id','action','target_id','timestamp'],
    'guests' => ['id','guest_name','approved_by','valid_until','created_at'],
    'daily_summary' => ['id','summary_date','total_events','total_faces','total_vehicles'],
];
$cols = $colConfig[$table] ?? ['id'];
$colSelect = implode(', ', array_map(fn($c) => "`$c`", $cols));

$stmt = $pdo->prepare("SELECT $colSelect FROM `$table` WHERE $where ORDER BY `$tsCol` DESC LIMIT 100");
$stmt->execute($params);
$records = $stmt->fetchAll();
?>

<div class="h-16 border-b border-border bg-surface flex items-center px-6 flex-shrink-0">
    <div><h1 class="font-bold text-lg text-textMain">Data Control</h1><p class="text-xs text-textSec">Super Admin — delete records from <?= ucwords(str_replace('_',' ',$table)) ?></p></div>
</div>

<div class="flex-1 overflow-y-auto p-6">
    <div class="glass-panel rounded-xl p-4 mb-4 border border-red-200">
        <p class="text-sm text-red-400"><i class="fas fa-triangle-exclamation mr-2"></i>Super Admin only. All deletions are permanent and logged to the audit trail.</p>
    </div>

    <form class="flex flex-wrap gap-3 mb-5" method="GET">
        <select name="table" class="text-sm">
            <?php foreach ($ALLOWED as $t): ?>
            <option value="<?= $t ?>" <?= $table===$t?'selected':'' ?>><?= ucwords(str_replace('_',' ',$t)) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" class="text-sm" value="<?= htmlspecialchars($dateFrom) ?>">
        <input type="date" name="to" class="text-sm" value="<?= htmlspecialchars($dateTo) ?>">
        <button type="submit" class="btn-primary"><i class="fas fa-search mr-1"></i>Load</button>
        <button type="button" class="btn-danger" onclick="deleteSelected()"><i class="fas fa-trash mr-1"></i>Delete Selected</button>
    </form>

    <div class="glass-panel rounded-xl overflow-hidden">
        <table>
            <thead><tr>
                <th class="w-10"><input type="checkbox" id="dc-select-all" onchange="document.querySelectorAll('.dc-check').forEach(c=>c.checked=this.checked)"></th>
                <?php foreach ($cols as $c): ?>
                <th><?= ucwords(str_replace('_',' ',$c)) ?></th>
                <?php endforeach; ?>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="<?= count($cols)+2 ?>" class="text-center text-textSec py-10">No records found</td></tr>
            <?php else: foreach ($records as $r): ?>
            <tr>
                <td><input type="checkbox" class="dc-check" value="<?= $r['id'] ?>"></td>
                <?php foreach ($cols as $c): ?>
                <td class="font-mono text-xs text-textSec"><?= htmlspecialchars(substr((string)($r[$c] ?? '-'), 0, 60)) ?></td>
                <?php endforeach; ?>
                <td><button onclick="deleteSingle(<?= $r['id'] ?>)" class="btn-danger text-xs py-1 px-2"><i class="fas fa-trash"></i></button></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
<script>
const DC_TABLE = '<?= addslashes($table) ?>';
async function doDelete(ids) {
    const res = await fetch('data_control.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ids, table:DC_TABLE}) });
    const data = await res.json();
    if (data.success) { showAlert(`${data.deleted} records deleted and logged`, 'success'); setTimeout(()=>location.reload(),1000); }
    else showAlert(data.error||'Failed','error');
}
async function deleteSelected() {
    const ids=[...document.querySelectorAll('.dc-check:checked')].map(c=>parseInt(c.value));
    if(!ids.length){showAlert('No records selected','warning');return;}
    if(await showConfirm(`Permanently delete ${ids.length} record(s)?`)) doDelete(ids);
}
async function deleteSingle(id) { if(await showConfirm(`Permanently delete record #${id}?`)) doDelete([id]); }
</script>
