<?php
/** EyeSense Cloud Portal — Event Logs (v2 — mirrored tables) */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'VIEW_LOGS');

$cid = $session['client_id'];
$dateFrom = $_GET['from'] ?? '';
$dateTo   = $_GET['to'] ?? '';
$table    = $_GET['table'] ?? 'face_logs';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 30;
$offset   = ($page - 1) * $perPage;

// Whitelist tables
$allowedTables = ['face_logs','vehicle_logs','access_requests','alert_logs','audit_logs','guests','daily_summary'];
if (!in_array($table, $allowedTables)) $table = 'face_logs';

// Determine timestamp column per table
$tsCol = match($table) {
    'face_logs', 'vehicle_logs' => 'timestamp',
    'access_requests' => 'requested_at',
    'alert_logs' => 'timestamp',
    'audit_logs' => 'timestamp',
    'guests' => 'created_at',
    'daily_summary' => 'generated_at',
    default => 'created_at'
};

$societyId = SocietyContextHolder::getCurrentSocietyId() ?: (int)($activeSocietyId ?? 0);
$detectionTables = ['face_logs', 'vehicle_logs'];

if (in_array($table, $detectionTables, true)) {
    $where = "society_id = ?";
    $params = [$societyId];
} else {
    $where = "client_id = ?";
    $params = [$cid];
}

if ($dateFrom) { $where .= " AND `$tsCol` >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where .= " AND `$tsCol` <= ?"; $params[] = $dateTo . ' 23:59:59'; }

// Guard camera restriction
if ($session['role'] === 'guard' && !empty($session['cameras'])) {
    // Only applicable to tables with camera_id
    if (in_array($table, ['face_logs','vehicle_logs','access_requests','alert_logs'])) {
        $placeholders = implode(',', array_fill(0, count($session['cameras']), '?'));
        $where .= " AND camera_id IN ($placeholders)";
        $params = array_merge($params, $session['cameras']);
    }
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE $where");
$totalStmt->execute($params);
$totalCount = $totalStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $pdo->prepare("SELECT * FROM `$table` WHERE $where ORDER BY `$tsCol` DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Define display columns per table
$displayConfig = [
    'face_logs' => ['id','camera_id','person_name','recognized','confidence','timestamp'],
    'vehicle_logs' => ['id','camera_id','plate_number','vehicle_type','authorized','timestamp'],
    'access_requests' => ['id','request_type','identifier','status','camera_id','requested_at'],
    'alert_logs' => ['id','event_type','alert_level','channel','status','timestamp'],
    'audit_logs' => ['id','actor_id','action','target_id','timestamp'],
    'guests' => ['id','guest_name','camera_id','approved_by','valid_until','created_at'],
    'daily_summary' => ['id','summary_date','total_events','total_faces','total_vehicles','alerts_sent'],
];
$cols = $displayConfig[$table] ?? ['id'];
?>

<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0"><h1 class="font-bold text-base md:text-lg text-textMain truncate">Event Logs</h1><p class="text-xs text-textSec hidden sm:block"><?= ucwords(str_replace('_',' ',$table)) ?> &mdash; backed-up records</p></div>
    <div class="text-sm text-textSec flex-shrink-0"><?= number_format($totalCount) ?> records</div>
</div>

<div class="flex-1 overflow-y-auto p-4 md:p-6">
    <form class="flex flex-wrap gap-2 mb-5" method="GET">
        <select name="table" class="text-sm flex-1 min-w-[150px]">
            <?php foreach ($allowedTables as $t): ?>
            <option value="<?= $t ?>" <?= $table===$t?'selected':'' ?>><?= ucwords(str_replace('_',' ',$t)) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" class="text-sm flex-1 min-w-[130px]" value="<?= htmlspecialchars($dateFrom) ?>">
        <input type="date" name="to" class="text-sm flex-1 min-w-[130px]" value="<?= htmlspecialchars($dateTo) ?>">
        <button type="submit" class="btn-primary"><i class="fas fa-search mr-1"></i>Filter</button>
    </form>

    <div class="glass-panel rounded-xl overflow-hidden">
        <div class="table-responsive">
            <thead><tr>
                <?php foreach ($cols as $c): ?>
                <th><?= ucwords(str_replace('_',' ',$c)) ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="<?= count($cols) ?>" class="text-center text-textSec py-10">No records found</td></tr>
            <?php else: foreach ($records as $r): ?>
                <tr>
                <?php foreach ($cols as $c):
                    $val = $r[$c] ?? '-';
                    // Format booleans
                    if (is_numeric($val) && in_array($c, ['recognized','authorized','detection_success'])) {
                        $val = $val ? '✓' : '✗';
                    }
                ?>
                    <td class="text-sm text-textSec font-mono"><?= htmlspecialchars(substr((string)$val, 0, 80)) ?></td>
                <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center mt-5 gap-2">
        <?php for ($i = 1; $i <= min($totalPages, 10); $i++): $qs = http_build_query(array_merge($_GET, ['page'=>$i])); ?>
        <a href="?<?= $qs ?>" class="px-3 py-1 rounded text-sm <?= $i===$page?'bg-primary text-textMain':'bg-textMain text-textSec hover:bg-gray-700' ?> no-underline"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
