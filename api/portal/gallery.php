<?php
/** EyeSense Cloud Portal — Snapshot Gallery & Video Clips (v2 — mirrored tables) */

// ── MUST handle AJAX BEFORE requiring portal_header.php ──
// portal_header.php outputs a full HTML page; any output before json_encode
// corrupts the JSON response and causes "Load failed" in the browser.

require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if ($isAjax) {
    // Lightweight auth for AJAX — no HTML output
    requirePortalLogin();
    $pdo     = get_indsac_db();
    require_once __DIR__ . '/../society_context_holder.php';
    SocietyContextHolder::initFromRequest($pdo);
    $societyId = SocietyContextHolder::getCurrentSocietyId();
    $cid       = SocietyContextHolder::getLegacyClientId($pdo);
    if (!$societyId) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Authentication required: no active society context found.']);
        exit;
    }
    $session = getPortalSession();
    $role    = SocietyContextHolder::getCurrentRole() ?: ($session['role'] ?? '');

    $isVideos    = ($_GET['type'] ?? '') === 'videos';
    $permKey     = $isVideos ? 'VIEW_VIDEOS' : 'VIEW_SNAPSHOTS';
    $sourceTable = $isVideos ? 'video_logs' : 'snapshot_logs';

    if (!hasPermission($pdo, $cid, $role, $permKey)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $dateFrom = $_GET['from']     ?? '';
    $dateTo   = $_GET['to']       ?? '';
    $camera   = $_GET['camera']   ?? '';
    $category = $_GET['category'] ?? '';
    $perPage  = $isVideos ? 20 : 24;
    $offset   = max(0, (int)($_GET['offset'] ?? 0));

    $where  = 'society_id = ?';
    $params = [$societyId];

    if ($dateFrom) { $where .= ' AND created_at >= ?'; $params[] = $dateFrom; }
    if ($dateTo)   { $where .= ' AND created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    if ($camera)   { $where .= ' AND camera_id = ?';   $params[] = $camera; }
    if ($category) { $where .= ' AND category = ?';    $params[] = $category; }

    if ($session['role'] === 'guard' && !empty($session['cameras'])) {
        $ph     = implode(',', array_fill(0, count($session['cameras']), '?'));
        $where .= " AND camera_id IN ($ph)";
        $params = array_merge($params, $session['cameras']);
    }

    $total = $pdo->prepare("SELECT COUNT(*) FROM `$sourceTable` WHERE $where");
    $total->execute($params);
    $totalCount = (int)$total->fetchColumn();
    $hasMore    = ($offset + $perPage) < $totalCount;

    if ($isVideos) {
        $selectCols = 'id, client_id, event_id, camera_id, category, frame_count, duration_seconds, recording_started_at, created_at';
    } else {
        $selectCols = 'id, client_id, event_id, category, name_label, camera_id, image_path, confidence, created_at';
    }
    $stmt = $pdo->prepare("SELECT $selectCols FROM `$sourceTable` WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $files = $stmt->fetchAll();

    ob_start();
    renderCards($files, $isVideos, $pdo);
    $html = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode(['html' => $html, 'has_more' => $hasMore, 'next_offset' => $offset + $perPage]);
    exit;
}

// ── Normal page load: include full header (outputs HTML) ──
require_once __DIR__ . '/portal_header.php';

$societyId = SocietyContextHolder::getCurrentSocietyId();
$cid       = SocietyContextHolder::getLegacyClientId($pdo);
if (!$societyId) {
    echo "<div class='p-6 text-red-500 font-bold'>Authentication required: no active society context found.</div>";
    require_once __DIR__ . '/portal_footer.php';
    exit;
}

$isVideos = ($_GET['type'] ?? '') === 'videos';
$permKey  = $isVideos ? 'VIEW_VIDEOS' : 'VIEW_SNAPSHOTS';
requirePermission($pdo, $cid, $session['role'], $permKey);

$pageTitle   = $isVideos ? 'Video Clips' : 'Snapshot Gallery';
$sourceTable = $isVideos ? 'video_logs' : 'snapshot_logs';

// Filters
$dateFrom = $_GET['from']     ?? '';
$dateTo   = $_GET['to']       ?? '';
$camera   = $_GET['camera']   ?? '';
$category = $_GET['category'] ?? '';
$perPage  = $isVideos ? 20 : 24;
$offset   = max(0, (int)($_GET['offset'] ?? 0));

$where  = 'society_id = ?';
$params = [$societyId];

if ($dateFrom) { $where .= ' AND created_at >= ?'; $params[] = $dateFrom; }
if ($dateTo)   { $where .= ' AND created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
if ($camera)   { $where .= ' AND camera_id = ?';   $params[] = $camera; }
if ($category) { $where .= ' AND category = ?';    $params[] = $category; }

// Guard camera restriction
if ($session['role'] === 'guard' && !empty($session['cameras'])) {
    $ph     = implode(',', array_fill(0, count($session['cameras']), '?'));
    $where .= " AND camera_id IN ($ph)";
    $params = array_merge($params, $session['cameras']);
}

$total = $pdo->prepare("SELECT COUNT(*) FROM `$sourceTable` WHERE $where");
$total->execute($params);
$totalCount = (int)$total->fetchColumn();
$hasMore    = ($offset + $perPage) < $totalCount;

// Explicitly exclude large image/video path blobs from list query
if ($isVideos) {
    $selectCols = 'id, client_id, event_id, camera_id, category, frame_count, duration_seconds, recording_started_at, created_at';
} else {
    $selectCols = 'id, client_id, event_id, category, name_label, camera_id, image_path, confidence, created_at';
}
$stmt = $pdo->prepare("SELECT $selectCols FROM `$sourceTable` WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$files = $stmt->fetchAll();

// Get distinct cameras for filter dropdown
$camStmt = $pdo->prepare("SELECT DISTINCT camera_id FROM `$sourceTable` WHERE society_id = ? AND camera_id IS NOT NULL ORDER BY camera_id");
$camStmt->execute([$societyId]);
$cameras = $camStmt->fetchAll(PDO::FETCH_COLUMN);

// Get distinct categories
$catStmt = $pdo->prepare("SELECT DISTINCT category FROM `$sourceTable` WHERE society_id = ? AND category IS NOT NULL ORDER BY category");
$catStmt->execute([$societyId]);
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);


// ── Helper: render grid cards or video rows ──
function renderCards(array $files, bool $isVideos, PDO $pdo): void {
    if (empty($files)) {
        echo '<div class="col-span-full text-center text-textSec py-10">No results found.</div>';
        return;
    }
    if ($isVideos) {
        foreach ($files as $f): ?>
            <tr>
                <td><span class="font-mono text-primary text-sm"><?= htmlspecialchars($f['camera_id'] ?? 'N/A') ?></span></td>
                <td><span class="badge bg-textMain text-textSec"><?= htmlspecialchars($f['category'] ?? '') ?></span></td>
                <td class="font-mono text-xs text-textSec"><?= $f['duration_seconds'] ? round($f['duration_seconds'],1).'s' : '-' ?></td>
                <td class="font-mono text-xs text-textSec"><?= $f['recording_started_at'] ?? $f['created_at'] ?></td>
                <td class="text-xs text-textSec"><?= $f['frame_count'] ?? '-' ?></td>
            </tr>
        <?php endforeach;
    } else {
        $catColors = ['face_known'=>'text-green-400 bg-green-900/20','face_unknown'=>'text-red-400 bg-red-900/20','vehicle'=>'text-blue-400 bg-blue-900/20'];
        foreach ($files as $f):
            $imgSrc  = '';
            $imgPath = $f['image_path'] ?? '';
            if ($imgPath && (str_starts_with($imgPath, 'data:') || strlen($imgPath) > 500)) {
                $imgSrc = str_starts_with($imgPath, 'data:') ? $imgPath : 'data:image/jpeg;base64,' . $imgPath;
            }
            $catCls = $catColors[$f['category']] ?? 'text-textSec bg-textMain';
            ?>
            <div class="gallery-card glass-panel rounded-xl overflow-hidden cursor-pointer hover:border-indigo-500/50 transition group">
                <div class="aspect-video bg-gray-900 flex items-center justify-center relative">
                    <?php if ($imgSrc): ?>
                        <img src="<?= $imgSrc ?>" class="w-full h-full object-cover" alt="snapshot" loading="lazy">
                    <?php else: ?>
                        <i class="fas fa-camera text-gray-700 text-2xl"></i>
                    <?php endif; ?>
                </div>
                <div class="p-2.5">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-mono text-textSec"><?= htmlspecialchars($f['camera_id'] ?? '') ?></span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded <?= $catCls ?>"><?= str_replace('_',' ',$f['category'] ?? '') ?></span>
                    </div>
                    <div class="text-xs text-textSec font-mono"><?= $f['created_at'] ?? '' ?></div>
                    <?php if ($f['name_label'] ?? ''): ?>
                    <div class="text-xs text-textSec mt-0.5 truncate"><?= htmlspecialchars($f['name_label']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        endforeach;
    }
}
?>

<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0"><h1 class="font-bold text-base md:text-lg text-textMain truncate"><?= $pageTitle ?></h1><p class="text-xs text-textSec hidden sm:block">Browse backed-up <?= $isVideos ? 'videos' : 'snapshots' ?></p></div>
    <div class="text-sm text-textSec flex-shrink-0"><?= number_format($totalCount) ?> total</div>
</div>

<div class="flex-1 overflow-y-auto p-4 md:p-6" id="gallery-scroll-container">
    <!-- Filters -->
    <form class="flex flex-wrap gap-2 mb-5" id="gallery-filter-form" method="GET">
        <?php if ($isVideos): ?><input type="hidden" name="type" value="videos"><?php endif; ?>
        <input type="date" name="from" class="text-sm flex-1 min-w-[130px]" value="<?= htmlspecialchars($dateFrom) ?>" placeholder="From">
        <input type="date" name="to" class="text-sm flex-1 min-w-[130px]" value="<?= htmlspecialchars($dateTo) ?>" placeholder="To">
        <select name="camera" class="text-sm flex-1 min-w-[130px]">
            <option value="">All Cameras</option>
            <?php foreach ($cameras as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>" <?= $camera===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="category" class="text-sm flex-1 min-w-[130px]">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= $category===$cat?'selected':'' ?>><?= ucwords(str_replace('_',' ',$cat)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary"><i class="fas fa-search mr-1"></i>Filter</button>
    </form>

    <?php if ($isVideos): ?>
    <!-- Video Table -->
    <div class="glass-panel rounded-xl overflow-hidden">
        <div class="table-responsive">
        <table>
            <thead><tr><th>Camera</th><th>Category</th><th>Duration</th><th>Recorded</th><th>Frames</th></tr></thead>
            <tbody id="gallery-grid">
            <?php renderCards($files, true, $pdo); ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php else: ?>
    <!-- Snapshot Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2 md:gap-3" id="gallery-grid">
        <?php renderCards($files, false, $pdo); ?>
    </div>
    <?php endif; ?>

    <!-- Infinite Scroll Sentinel -->
    <?php if ($hasMore): ?>
    <div id="gallery-sentinel" class="flex justify-center py-6">
        <div class="flex items-center gap-2 text-textSec text-sm">
            <i class="fas fa-spinner fa-spin"></i> Loading more...
        </div>
    </div>
    <?php else: ?>
    <div class="text-center text-gray-700 text-xs py-6 mt-2">All <?= number_format($totalCount) ?> items loaded</div>
    <?php endif; ?>
</div>

<script>
(function(){
    // Build base AJAX URL from current filters
    const baseUrl = new URL(location.href);
    baseUrl.searchParams.set('ajax', '1');
    let nextOffset = <?= $offset + $perPage ?>;
    let loading    = false;
    let exhausted  = <?= $hasMore ? 'false' : 'true' ?>;
    const isVideos = <?= $isVideos ? 'true' : 'false' ?>;
    const grid     = document.getElementById('gallery-grid');
    const sentinel = document.getElementById('gallery-sentinel');

    if (!sentinel || exhausted) return;

    const observer = new IntersectionObserver(async (entries) => {
        if (!entries[0].isIntersecting || loading || exhausted) return;
        loading = true;

        try {
            baseUrl.searchParams.set('offset', nextOffset);
            const res  = await fetch(baseUrl.toString());
            const data = await res.json();

            if (isVideos) {
                // Append rows to <tbody>
                grid.insertAdjacentHTML('beforeend', data.html);
            } else {
                grid.insertAdjacentHTML('beforeend', data.html);
            }

            nextOffset = data.next_offset;
            if (!data.has_more) {
                exhausted = true;
                sentinel.innerHTML = '<p class="text-gray-700 text-xs py-4">All items loaded</p>';
                observer.disconnect();
            }
        } catch(e) {
            sentinel.innerHTML = '<p class="text-red-500 text-xs">Load failed. <a href="#" onclick="location.reload()">Refresh</a></p>';
        } finally {
            loading = false;
        }
    }, { rootMargin: '200px' }); // Trigger 200px before sentinel is visible

    observer.observe(sentinel);
})();
</script>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
