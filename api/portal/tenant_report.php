<?php
require_once __DIR__ . '/../portal_auth.php';
require_once __DIR__ . '/../portal_config.php';

portal_session_start();
$cid   = $_SESSION['portal_client_id'] ?? '';
$role  = $_SESSION['portal_role'] ?? '';
$empId = $_SESSION['portal_employee_id'] ?? '';
if (!$cid) { header('Location: login.php'); exit; }

$isAdmin  = in_array($role, ['ADMIN','SUPERADMIN','admin','superadmin']);
$isMember = ($role === 'MEMBER' || $role === 'member');
if (!$isAdmin && !$isMember) { header('Location: dashboard.php'); exit; }

$pdo = get_indsac_db();

// Resolve member_id for members — members only see THEIR OWN tenants
$memberId = null;
if ($isMember) {
    $ms = $pdo->prepare("SELECT id, full_name, flat_number FROM society_members WHERE client_id=? AND employee_id=? AND status='active' LIMIT 1");
    $ms->execute([$cid, $empId]);
    $memberRow = $ms->fetch();
    if (!$memberRow) { header('Location: dashboard.php'); exit; }
    $memberId = (int)$memberRow['id'];
}

// Society name
$socName = 'Society';
try {
    $r = $pdo->prepare("SELECT society_name FROM maintenance_settings WHERE client_id=? LIMIT 1");
    $r->execute([$cid]);
    $socName = $r->fetchColumn() ?: 'Society';
} catch (Throwable $e) {}

// Filters from GET
$filterBlock  = trim($_GET['block']  ?? '');
$filterFloor  = trim($_GET['floor']  ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterType   = trim($_GET['type']   ?? '');

// Build WHERE clause
$where  = ['st.client_id = ?'];
$params = [$cid];
if ($isMember)    { $where[] = 'st.member_id = ?';          $params[] = $memberId; }
if ($filterBlock) { $where[] = 'st.block_wing = ?';          $params[] = $filterBlock; }
if ($filterFloor) { $where[] = 'st.floor_number = ?';        $params[] = $filterFloor; }
if ($filterStatus === 'expired') {
    $where[] = "st.occupancy_end IS NOT NULL AND st.occupancy_end < CURDATE()";
} elseif ($filterStatus === 'pending_verify') {
    $where[] = "st.registration_status = 'Pending'";
} elseif ($filterStatus) {
    $where[] = 'st.registration_status = ?'; $params[] = $filterStatus;
}
if ($filterType) { $where[] = 'st.purpose = ?'; $params[] = $filterType; }

$sql = "SELECT st.*, sm.full_name AS owner_name, sm.flat_number AS owner_flat
        FROM society_tenants st
        LEFT JOIN society_members sm ON sm.id = st.member_id AND sm.client_id = st.client_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY st.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Unique filter option values
$optQ = $pdo->prepare("SELECT DISTINCT block_wing, floor_number, purpose FROM society_tenants WHERE client_id=?" . ($isMember ? " AND member_id=?" : ""));
$optP = [$cid]; if ($isMember) $optP[] = $memberId;
$optQ->execute($optP);
$optRows = $optQ->fetchAll(PDO::FETCH_ASSOC);
$blocks = array_values(array_unique(array_filter(array_column($optRows, 'block_wing'))));
$floors = array_values(array_unique(array_filter(array_column($optRows, 'floor_number'))));
$types  = array_values(array_unique(array_filter(array_column($optRows, 'purpose'))));
sort($blocks); sort($floors); sort($types);

// Stats
$total   = count($tenants);
$active  = count(array_filter($tenants, fn($t) => $t['registration_status'] === 'Active'));
$pending = count(array_filter($tenants, fn($t) => $t['registration_status'] === 'Pending'));
$expired = count(array_filter($tenants, fn($t) => !empty($t['occupancy_end']) && $t['occupancy_end'] < date('Y-m-d')));

$today = date('d M Y, h:i A');

require_once __DIR__ . '/portal_header.php';
?>
<style>
:root{--ind:#6366f1;--ind2:#818cf8}
.rpt-wrap{padding:24px 28px;max-width:1280px}
.rpt-title{font-size:22px;font-weight:700;color:var(--textMain);margin:0 0 4px}
.rpt-sub{font-size:13px;color:var(--textSec);margin:0 0 22px}
.filter-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 18px;margin-bottom:22px}
.filter-grid{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.filter-grid label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--textSec);display:block;margin-bottom:5px}
.filter-grid select{background:var(--surfaceLight);border:1px solid var(--border);border-radius:8px;color:var(--textMain);font-family:inherit;font-size:13px;padding:8px 12px;outline:none;min-width:150px;cursor:pointer}
.filter-grid select:focus{border-color:var(--ind)}
.btn-apply{background:linear-gradient(135deg,#6366f1,#818cf8);color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:7px}
.btn-reset-lnk{background:rgba(107,114,128,.12);color:var(--textSec);border:1px solid var(--border);border-radius:8px;padding:9px 14px;font-size:13px;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:7px;text-decoration:none}
.stats-row{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:20px}
.stat-box{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 22px;flex:1;min-width:120px}
.stat-num{font-size:28px;font-weight:800;color:var(--textMain);line-height:1}
.stat-lbl{font-size:11px;color:var(--textSec);margin-top:5px;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.export-bar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}
.btn-exp{display:flex;align-items:center;gap:7px;padding:8px 18px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;border:1px solid;transition:background .18s}
.btn-csv{background:rgba(16,185,129,.1);color:#10b981;border-color:rgba(16,185,129,.25)}
[data-theme="dark"] .btn-csv{color:#34d399}
.btn-csv:hover{background:rgba(16,185,129,.2)}
.btn-xls{background:rgba(59,130,246,.1);color:#2563eb;border-color:rgba(59,130,246,.25)}
[data-theme="dark"] .btn-xls{color:#60a5fa}
.btn-xls:hover{background:rgba(59,130,246,.2)}
.btn-pdf{background:rgba(239,68,68,.1);color:#ef4444;border-color:rgba(239,68,68,.25)}
[data-theme="dark"] .btn-pdf{color:#f87171}
.btn-pdf:hover{background:rgba(239,68,68,.2)}
.tbl-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:auto}
.rpt-tbl{width:100%;border-collapse:collapse;font-size:13px;white-space:nowrap}
.rpt-tbl thead th{background:rgba(99,102,241,.14);color:var(--textMain);font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.05em;padding:10px 14px;text-align:left;border-bottom:1px solid var(--border);position:sticky;top:0}
.rpt-tbl tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
.rpt-tbl tbody tr:hover{background:var(--surfaceLight)}
.rpt-tbl td{padding:10px 14px;color:var(--textMain);vertical-align:middle}
.st-badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700}
.st-Active{background:rgba(16,185,129,.15);color:#10b981}
[data-theme="dark"] .st-Active{color:#34d399}
.st-Pending{background:rgba(245,158,11,.15);color:#d97706}
[data-theme="dark"] .st-Pending{color:#fbbf24}
.st-Rejected{background:rgba(239,68,68,.15);color:#ef4444}
[data-theme="dark"] .st-Rejected{color:#f87171}
.st-Inactive,.st-Expired{background:rgba(107,114,128,.15);color:var(--textSec)}
.no-data{text-align:center;padding:60px;color:var(--textSec)}
@media(max-width:600px){.filter-grid{flex-direction:column}.export-bar{flex-direction:column}}
</style>

<div class="rpt-wrap">
  <h1 class="rpt-title"><i class="fas fa-chart-bar" style="color:#818cf8;margin-right:10px"></i>Tenant Report</h1>
  <p class="rpt-sub" id="rptSubtitle"><?= htmlspecialchars($socName) ?> — Generated <?= $today ?></p>

  <!-- Filters -->
  <div class="filter-card">
    <form method="GET" class="filter-grid">
      <div>
        <label>Block / Wing</label>
        <select name="block">
          <option value="">All Blocks</option>
          <?php foreach ($blocks as $b): ?>
          <option value="<?= htmlspecialchars($b) ?>" <?= $filterBlock === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Floor</label>
        <select name="floor">
          <option value="">All Floors</option>
          <?php foreach ($floors as $f): ?>
          <option value="<?= htmlspecialchars($f) ?>" <?= $filterFloor === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Status</label>
        <select name="status">
          <option value="">All Statuses</option>
          <option value="Active"          <?= $filterStatus === 'Active'          ? 'selected' : '' ?>>Active</option>
          <option value="Pending"         <?= $filterStatus === 'Pending'         ? 'selected' : '' ?>>Pending</option>
          <option value="Rejected"        <?= $filterStatus === 'Rejected'        ? 'selected' : '' ?>>Rejected</option>
          <option value="Inactive"        <?= $filterStatus === 'Inactive'        ? 'selected' : '' ?>>Inactive</option>
          <option value="expired"         <?= $filterStatus === 'expired'         ? 'selected' : '' ?>>Expired Occupancy</option>
          <option value="pending_verify"  <?= $filterStatus === 'pending_verify'  ? 'selected' : '' ?>>Verification Pending</option>
        </select>
      </div>
      <div>
        <label>Tenant Type</label>
        <select name="type">
          <option value="">All Types</option>
          <?php foreach ($types as $tp): ?>
          <option value="<?= htmlspecialchars($tp) ?>" <?= $filterType === $tp ? 'selected' : '' ?>><?= htmlspecialchars($tp) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:8px;align-items:flex-end">
        <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply</button>
        <a href="tenant_report.php" class="btn-reset-lnk"><i class="fas fa-undo"></i> Reset</a>
      </div>
    </form>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-box"><div class="stat-num"><?= $total ?></div><div class="stat-lbl">Total</div></div>
    <div class="stat-box"><div class="stat-num" style="color:#34d399"><?= $active ?></div><div class="stat-lbl">Active</div></div>
    <div class="stat-box"><div class="stat-num" style="color:#fbbf24"><?= $pending ?></div><div class="stat-lbl">Pending</div></div>
    <div class="stat-box"><div class="stat-num" style="color:#f87171"><?= $expired ?></div><div class="stat-lbl">Expired</div></div>
  </div>

  <!-- Export buttons -->
  <div class="export-bar">
    <button class="btn-exp btn-csv" onclick="exportCSV()"><i class="fas fa-file-csv"></i> Export CSV</button>
    <button class="btn-exp btn-xls" onclick="exportExcel()"><i class="fas fa-file-excel"></i> Export Excel</button>
    <button class="btn-exp btn-pdf" onclick="exportPDF()"><i class="fas fa-file-pdf"></i> Export PDF</button>
  </div>

  <!-- Table -->
  <div class="tbl-wrap">
    <?php if (empty($tenants)): ?>
      <div class="no-data"><i class="fas fa-inbox" style="font-size:40px;opacity:.3;display:block;margin-bottom:14px"></i>No tenants match the selected filters.</div>
    <?php else: ?>
    <table class="rpt-tbl" id="rptTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Full Name</th>
          <th>Mobile</th>
          <th>Email</th>
          <th>Flat</th>
          <th>Block</th>
          <th>Floor</th>
          <th>Purpose</th>
          <th>Move-in</th>
          <th>Move-out</th>
          <th>Status</th>
          <?php if ($isAdmin): ?><th>Owner</th><?php endif; ?>
          <th>Registered Via</th>
          <th>Added On</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tenants as $i => $t):
          $isExpired = !empty($t['occupancy_end']) && $t['occupancy_end'] < date('Y-m-d');
          $stClass   = $isExpired ? 'st-Expired' : 'st-' . htmlspecialchars($t['registration_status']);
          $stLabel   = $isExpired ? 'Expired'    : htmlspecialchars($t['registration_status']);
          $nin  = fn($v) => htmlspecialchars($v ?: '');
        ?>
        <tr>
          <td style="color:var(--textSec)"><?= $i + 1 ?></td>
          <td style="font-weight:600;color:var(--textMain)"><?= $nin($t['full_name']) ?></td>
          <td><?= $nin($t['mobile']) ?></td>
          <td><?= $nin($t['email']) ?></td>
          <td><?= $nin($t['flat_number']) ?></td>
          <td><?= $nin($t['block_wing']) ?></td>
          <td><?= $nin($t['floor_number']) ?></td>
          <td><?= $nin($t['purpose']) ?></td>
          <td><?= $nin($t['occupancy_start']) ?></td>
          <td><?= $nin($t['occupancy_end']) ?></td>
          <td><span class="st-badge <?= $stClass ?>"><?= $stLabel ?></span></td>
          <?php if ($isAdmin): ?><td><?= $nin($t['owner_name']) ?> (<?= $nin($t['owner_flat']) ?>)</td><?php endif; ?>
          <td><?= $nin($t['registered_via']) ?></td>
          <td style="color:#6b7280"><?= $t['created_at'] ? date('d M Y', strtotime($t['created_at'])) : '' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--textSec)">
    <span><?= $total ?> tenant(s) shown</span>
    <a href="<?= $isAdmin ? 'tenants.php' : 'my_tenants.php' ?>" style="color:#6366f1;text-decoration:none"><i class="fas fa-arrow-left"></i> Back to Tenants</a>
  </div>
</div>

<script>
// Strip em-dash / en-dash (used as blank placeholders) from cell text
function cleanVal(td){
  return td.textContent.trim().replace(/[\u2014\u2013\u2012\u2010]/g,'').trim();
}

function exportCSV(){
  const tbl=document.getElementById('rptTable');
  if(!tbl){alert('No data to export');return}
  const rows=[];
  const hdr=[...tbl.querySelectorAll('thead th')].map(th=>'"'+th.textContent.trim()+'"');
  rows.push(hdr.join(','));
  tbl.querySelectorAll('tbody tr').forEach(tr=>{
    const cells=[...tr.querySelectorAll('td')].map(td=>'"'+cleanVal(td).replace(/"/g,'""')+'"');
    rows.push(cells.join(','));
  });
  const BOM='\uFEFF'; // UTF-8 BOM — makes Excel open it correctly
  const blob=new Blob([BOM+rows.join('\n')],{type:'text/csv;charset=utf-8;'});
  const a=document.createElement('a');a.href=URL.createObjectURL(blob);
  a.download='tenant_report_<?= date('Ymd') ?>.csv';a.click();
}

function exportExcel(){
  const tbl=document.getElementById('rptTable');
  if(!tbl){alert('No data to export');return}
  const clone=tbl.cloneNode(true);
  // Normalize cells: strip dashes, keep plain text only
  clone.querySelectorAll('td').forEach(td=>{td.textContent=cleanVal(td);});
  const html='<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">'
    +'<head><meta charset="UTF-8"><style>'
    +'body{font-family:Arial,sans-serif;font-size:11pt}'
    +'table{border-collapse:collapse;width:100%}'
    +'th{background:#4f46e5;color:#ffffff;font-weight:bold;border:1px solid #3730a3;padding:6px 10px;text-align:left}'
    +'td{border:1px solid #d1d5db;padding:5px 10px}'
    +'tr:nth-child(even) td{background:#f9fafb}'
    +'</style></head><body>'+clone.outerHTML+'</body></html>';
  const blob=new Blob([html],{type:'application/vnd.ms-excel;charset=utf-8;'});
  const a=document.createElement('a');a.href=URL.createObjectURL(blob);
  a.download='tenant_report_<?= date('Ymd') ?>.xls';a.click();
}

function exportPDF(){
  const tbl=document.getElementById('rptTable');
  if(!tbl){alert('No data to export');return}
  // Clone and normalize
  const clone=tbl.cloneNode(true);
  clone.querySelectorAll('td').forEach(td=>{td.textContent=cleanVal(td);});
  const subtitle=document.getElementById('rptSubtitle')?.textContent||'';
  const win=window.open('','_blank','width=1100,height=750');
  win.document.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Tenant Report</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#111;padding:24px}
    h1{font-size:18px;font-weight:700;margin-bottom:5px}
    .sub{font-size:11px;color:#555;margin-bottom:18px;border-bottom:2px solid #4f46e5;padding-bottom:8px}
    table{width:100%;border-collapse:collapse}
    thead th{background:#4f46e5;color:#fff;font-size:10px;text-transform:uppercase;letter-spacing:.04em;padding:8px 10px;text-align:left;border:1px solid #3730a3}
    tbody td{padding:6px 10px;border:1px solid #e5e7eb;color:#374151;vertical-align:middle}
    tbody tr:nth-child(even) td{background:#f9fafb}
    tbody tr:last-child td{border-bottom:2px solid #d1d5db}
    .footer{margin-top:14px;font-size:10px;color:#888;text-align:right}
    @media print{
      @page{size:A4 landscape;margin:12mm}
      body{padding:0}
      thead th{-webkit-print-color-adjust:exact;print-color-adjust:exact;background:#4f46e5!important;color:#fff!important}
      tbody tr:nth-child(even) td{-webkit-print-color-adjust:exact;print-color-adjust:exact;background:#f9fafb!important}
    }
  </style>
</head>
<body>
  <h1>Tenant Report</h1>
  <div class="sub">${subtitle}</div>
  ${clone.outerHTML}
  <div class="footer">Printed on ${new Date().toLocaleString('en-IN')} | Total Records: ${clone.querySelectorAll('tbody tr').length}</div>
</body>
</html>`);
  win.document.close();
  setTimeout(()=>{win.focus();win.print();},600);
}
</script>

<?php require_once __DIR__ . '/portal_footer.php'; ?>
