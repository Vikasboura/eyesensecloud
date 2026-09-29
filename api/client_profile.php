<?php
/**
 * EyeSense — Indsac Client Profile Page
 * URL: client_profile.php?cid=IND-2026-XXXXX
 * Auth: Indsac admin session ($_SESSION['indsac_admin'])
 * Branch: 78-es-78-tenant-management--client-profile-module
 */
session_start();
if (empty($_SESSION['indsac_admin'])) {
    header('Location: admin_login.php'); exit;
}
require_once __DIR__ . '/portal_config.php';

$cid = trim($_GET['cid'] ?? '');
if (!$cid) { header('Location: admin_dashboard.php'); exit; }

$pdo = get_indsac_db();

// Fetch client
$stc = $pdo->prepare("SELECT * FROM clients WHERE client_id=? LIMIT 1");
$stc->execute([$cid]);
$client = $stc->fetch();
if (!$client) { header('Location: admin_dashboard.php'); exit; }

// Fetch latest license (prioritizing society/legacy_client_id linkage, fallback to sa_email)
$license = [];
try {
    $stl = $pdo->prepare("
        SELECT l.*, l.plan AS plan_name, l.amount AS plan_price,
               s.society_code, s.society_name
        FROM licenses l
        LEFT JOIN society s ON s.id = l.society_id
        LEFT JOIN clients c ON c.email = l.sa_email
        WHERE l.legacy_client_id = ?
           OR s.legacy_client_id = ?
           OR c.client_id = ?
        ORDER BY 
            (CASE 
                WHEN l.society_id IS NOT NULL OR l.legacy_client_id IS NOT NULL THEN 2 
                ELSE 1 
             END) DESC,
            (CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) DESC,
            l.id DESC
        LIMIT 1
    ");
    $stl->execute([$cid, $cid, $cid]);
    $license = $stl->fetch() ?: [];
} catch (Throwable $e) { $license = []; }

// Society stats
$stats = [];
foreach ([
    'members'  => "SELECT COUNT(*) FROM society_members WHERE client_id=? AND status='active'",
    'tenants'  => "SELECT COUNT(*) FROM society_tenants WHERE client_id=?",
    'pending_tenants' => "SELECT COUNT(*) FROM society_tenants WHERE client_id=? AND registration_status='Pending'",
    'complaints' => "SELECT COUNT(*) FROM member_complaints WHERE client_id=? AND status='OPEN'",
    'employees' => "SELECT COUNT(*) FROM employees WHERE client_id=? AND is_deleted=0",
] as $key => $sql) {
    try {
        $st = $pdo->prepare($sql); $st->execute([$cid]);
        $stats[$key] = (int)$st->fetchColumn();
    } catch (Throwable $e) { $stats[$key] = 0; }
}

// Recent audit logs
try {
    $sta = $pdo->prepare("SELECT * FROM portal_audit_logs WHERE client_id=? ORDER BY created_at DESC LIMIT 10");
    $sta->execute([$cid]);
    $auditLogs = $sta->fetchAll();
} catch (Throwable $e) { $auditLogs = []; }

// Recent logins
try {
    $stls = $pdo->prepare("SELECT * FROM portal_login_sessions WHERE client_id=? ORDER BY login_at DESC LIMIT 5");
    $stls->execute([$cid]);
    $loginSessions = $stls->fetchAll();
} catch (Throwable $e) { $loginSessions = []; }

$statusColors = [
    'active'    => ['bg' => '#10b981', 'text' => 'Active'],
    'suspended' => ['bg' => '#f59e0b', 'text' => 'Suspended'],
    'revoked'   => ['bg' => '#ef4444', 'text' => 'Revoked'],
];
$cs = $statusColors[$client['status'] ?? 'active'] ?? $statusColors['active'];

function daysLeft(?string $date): int {
    if (!$date) return 0;
    return max(0, (int)ceil((strtotime($date) - time()) / 86400));
}
$daysLeft = daysLeft($license['expiry_date'] ?? null);
$totalDays = 365;
if (!empty($license['start_date']) && !empty($license['expiry_date'])) {
    $totalDays = max(1, (int)ceil((strtotime($license['expiry_date']) - strtotime($license['start_date'])) / 86400));
}
$pct = $totalDays > 0 ? min(100, max(0, round(($daysLeft / $totalDays) * 100))) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Client Profile — <?= htmlspecialchars($client['client_id']) ?> | EyeSense Indsac</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Outfit',sans-serif;background:#080812;color:#e5e7eb;min-height:100vh}
.topbar{background:rgba(10,10,20,.95);border-bottom:1px solid rgba(255,255,255,.07);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;backdrop-filter:blur(12px)}
.topbar-brand{font-size:16px;font-weight:700;color:#f3f4f6;display:flex;align-items:center;gap:10px}
.topbar-brand .dot{width:8px;height:8px;border-radius:50%;background:#6366f1}
.back-btn{display:inline-flex;align-items:center;gap:7px;color:#9ca3af;font-size:13px;text-decoration:none;padding:7px 14px;border-radius:8px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);transition:all .2s}
.back-btn:hover{color:#e5e7eb;background:rgba(255,255,255,.09)}
.page{max-width:1100px;margin:0 auto;padding:28px 20px}
/* Hero */
.hero{background:linear-gradient(135deg,rgba(79,70,229,.15),rgba(124,58,237,.08));border:1px solid rgba(99,102,241,.2);border-radius:20px;padding:28px 32px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap}
.hero-left{display:flex;align-items:flex-start;gap:20px}
.client-avatar{width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;color:#fff;flex-shrink:0}
.client-id{font-size:11px;color:#9ca3af;letter-spacing:.08em;text-transform:uppercase;font-weight:600;margin-bottom:4px;display:flex;align-items:center;gap:6px}
.copy-id{background:none;border:none;color:#6366f1;cursor:pointer;font-size:12px;padding:0}
.copy-id:hover{color:#a5b4fc}
.client-name{font-size:22px;font-weight:700;color:#f3f4f6;margin-bottom:6px}
.client-meta{font-size:13px;color:#9ca3af;display:flex;gap:16px;flex-wrap:wrap}
.client-meta span{display:flex;align-items:center;gap:5px}
.status-pill{padding:4px 14px;border-radius:999px;font-size:12px;font-weight:700;color:#fff}
.hero-actions{display:flex;flex-direction:column;gap:8px;align-items:flex-end}
.btn{padding:9px 18px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;border:none;font-family:inherit;display:inline-flex;align-items:center;gap:7px;transition:opacity .2s}
.btn:hover{opacity:.85}
.btn-primary{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff}
.btn-warn{background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.25)}
.btn-danger{background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.2)}
.btn-success{background:rgba(16,185,129,.13);color:#34d399;border:1px solid rgba(16,185,129,.2)}
/* Grid */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px}
/* Cards */
.card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:16px;padding:22px}
.card-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b7280;margin-bottom:16px;display:flex;align-items:center;gap:7px}
.card-title i{color:#6366f1}
/* Stat card */
.stat-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:18px;text-align:center}
.stat-val{font-size:28px;font-weight:800;color:#f3f4f6;line-height:1}
.stat-lbl{font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-top:5px}
/* Info rows */
.info-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:13px}
.info-row:last-child{border-bottom:none}
.info-row .lbl{color:#9ca3af}
.info-row .val{color:#e5e7eb;font-weight:500;text-align:right}
/* Progress bar */
.prog-wrap{background:rgba(255,255,255,.06);border-radius:999px;height:8px;overflow:hidden;margin:8px 0}
.prog-bar{height:100%;border-radius:999px;background:linear-gradient(90deg,#4f46e5,#7c3aed);transition:width .5s}
/* Timeline */
.timeline-item{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.timeline-item:last-child{border-bottom:none}
.tl-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;margin-top:2px}
.tl-action{font-size:13px;color:#e5e7eb;font-weight:500}
.tl-detail{font-size:11px;color:#9ca3af;margin-top:2px}
.tl-time{font-size:11px;color:#6b7280;margin-left:auto;white-space:nowrap}
/* Login session */
.session-item{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:13px}
.session-item:last-child{border-bottom:none}
.session-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
@media(max-width:860px){.grid-2,.grid-4{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.grid-4{grid-template-columns:1fr 1fr}.hero-left{flex-direction:column}}
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-brand"><div class="dot"></div>EyeSense Indsac Admin</div>
  <a href="admin_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="page">

  <!-- Hero Card -->
  <div class="hero">
    <div class="hero-left">
      <div class="client-avatar"><?= strtoupper(substr($client['company_name'] ?? $client['contact_person'] ?? 'C', 0, 1)) ?></div>
      <div>
        <div class="client-id">
          <?= htmlspecialchars($client['client_id']) ?>
          <button class="copy-id" onclick="copyText('<?= htmlspecialchars($client['client_id']) ?>')" title="Copy ID"><i class="fas fa-copy"></i></button>
        </div>
        <div class="client-name"><?= htmlspecialchars($client['company_name'] ?? $client['contact_person'] ?? '—') ?></div>
        <div class="client-meta">
          <?php if (!empty($client['email'])): ?>
          <span><i class="fas fa-envelope" style="color:#6366f1"></i><?= htmlspecialchars($client['email']) ?></span>
          <?php endif; ?>
          <?php if (!empty($client['phone'])): ?>
          <span><i class="fas fa-phone" style="color:#10b981"></i><?= htmlspecialchars($client['phone']) ?></span>
          <?php endif; ?>
          <?php if (!empty($client['city'])): ?>
          <span><i class="fas fa-map-marker-alt" style="color:#f59e0b"></i><?= htmlspecialchars($client['city']) ?><?= !empty($client['state']) ? ', '.htmlspecialchars($client['state']) : '' ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="hero-actions">
      <div style="margin-bottom:6px">
        <span class="status-pill" style="background:<?= $cs['bg'] ?>"><?= $cs['text'] ?></span>
      </div>
      <?php if (($client['status'] ?? '') === 'active'): ?>
      <button class="btn btn-warn" onclick="setStatus('suspended')"><i class="fas fa-pause-circle"></i> Suspend</button>
      <?php else: ?>
      <button class="btn btn-success" onclick="setStatus('active')"><i class="fas fa-play-circle"></i> Activate</button>
      <?php endif; ?>
      <button class="btn btn-primary" onclick="resetSAPassword()"><i class="fas fa-key"></i> Reset SA Password</button>
    </div>
  </div>

  <!-- Society Stats -->
  <div class="grid-4">
    <div class="stat-card"><div class="stat-val"><?= $stats['employees'] ?></div><div class="stat-lbl">Total Users</div></div>
    <div class="stat-card"><div class="stat-val"><?= $stats['members'] ?></div><div class="stat-lbl">Active Members</div></div>
    <div class="stat-card"><div class="stat-val"><?= $stats['tenants'] ?></div><div class="stat-lbl">Total Tenants</div></div>
    <div class="stat-card" style="border-color:<?= $stats['complaints'] > 0 ? 'rgba(239,68,68,.25)' : 'rgba(255,255,255,.06)' ?>">
      <div class="stat-val" style="color:<?= $stats['complaints'] > 0 ? '#f87171' : '#f3f4f6' ?>"><?= $stats['complaints'] ?></div>
      <div class="stat-lbl">Open Complaints</div>
    </div>
  </div>

  <div class="grid-2">

    <!-- Subscription Panel -->
    <div class="card">
      <div class="card-title"><i class="fas fa-credit-card"></i> Subscription</div>
      <?php if (!empty($license)): ?>
      <?php if (!empty($license['society_name'])): ?>
      <div class="info-row"><span class="lbl">Linked Society</span><span class="val" style="color:#60a5fa"><?= htmlspecialchars($license['society_name']) ?><?= !empty($license['society_code']) ? ' <span style="font-size:11px;color:#9ca3af">(' . htmlspecialchars($license['society_code']) . ')</span>' : '' ?></span></div>
      <?php endif; ?>
      <div class="info-row"><span class="lbl">Plan</span><span class="val" style="color:#a5b4fc"><?= htmlspecialchars($license['plan_name'] ?? 'Custom') ?></span></div>
      <div class="info-row"><span class="lbl">Start Date</span><span class="val"><?= $license['start_date'] ?? '—' ?></span></div>
      <div class="info-row"><span class="lbl">Expiry Date</span><span class="val"><?= $license['expiry_date'] ?? '—' ?></span></div>
      <div class="info-row"><span class="lbl">Days Remaining</span>
        <span class="val" style="color:<?= $daysLeft < 30 ? '#f87171' : '#34d399' ?>"><?= $daysLeft ?> days</span></div>
      <?php if (!empty($license['plan_price'])): ?>
      <div class="info-row"><span class="lbl">Plan Cost</span><span class="val">₹<?= number_format((float)$license['plan_price'], 2) ?>/yr</span></div>
      <?php endif; ?>
      <div style="margin-top:12px">
        <div style="display:flex;justify-content:space-between;font-size:11px;color:#9ca3af;margin-bottom:4px">
          <span>Subscription period</span><span><?= $pct ?>% remaining</span>
        </div>
        <div class="prog-wrap"><div class="prog-bar" style="width:<?= $pct ?>%"></div></div>
      </div>
      <?php else: ?>
      <p style="color:#6b7280;font-size:13px">No active license found.</p>
      <?php endif; ?>
    </div>

    <!-- Client Details -->
    <div class="card">
      <div class="card-title"><i class="fas fa-building"></i> Client Details</div>
      <div class="info-row"><span class="lbl">Contact Person</span><span class="val"><?= htmlspecialchars($client['contact_person'] ?? '—') ?></span></div>
      <div class="info-row"><span class="lbl">Industry</span><span class="val"><?= htmlspecialchars($client['industry_type'] ?? '—') ?></span></div>
      <div class="info-row"><span class="lbl">GST Number</span><span class="val"><?= htmlspecialchars($client['gst_number'] ?? '—') ?></span></div>
      <div class="info-row"><span class="lbl">Website</span>
        <span class="val"><?= !empty($client['website']) ? '<a href="'.htmlspecialchars($client['website']).'" target="_blank" style="color:#6366f1">'.htmlspecialchars($client['website']).'</a>' : '—' ?></span></div>
      <div class="info-row"><span class="lbl">Address</span>
        <span class="val"><?= htmlspecialchars(implode(', ', array_filter([$client['address'] ?? '', $client['city'] ?? '', $client['state'] ?? '', $client['country'] ?? '']))) ?: '—' ?></span></div>
      <div class="info-row"><span class="lbl">Registered</span><span class="val"><?= !empty($client['created_at']) ? date('d M Y', strtotime($client['created_at'])) : '—' ?></span></div>
    </div>
  </div>

  <div class="grid-2">

    <!-- Activity Timeline -->
    <div class="card">
      <div class="card-title"><i class="fas fa-timeline"></i> Recent Activity</div>
      <?php if (empty($auditLogs)): ?>
      <p style="color:#6b7280;font-size:13px">No activity recorded.</p>
      <?php else: ?>
      <?php foreach ($auditLogs as $log): ?>
      <div class="timeline-item">
        <div class="tl-dot" style="background:rgba(99,102,241,.15);color:#818cf8">
          <i class="fas fa-bolt" style="font-size:12px"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div class="tl-action"><?= htmlspecialchars($log['action'] ?? '') ?></div>
          <div class="tl-detail"><?= htmlspecialchars($log['performed_by'] ?? '') ?> — <?= htmlspecialchars(mb_strimwidth($log['detail'] ?? '', 0, 60, '…')) ?></div>
        </div>
        <div class="tl-time"><?= !empty($log['created_at']) ? date('d M, H:i', strtotime($log['created_at'])) : '' ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Login Sessions -->
    <div class="card">
      <div class="card-title"><i class="fas fa-right-to-bracket"></i> Recent Logins</div>
      <?php if (empty($loginSessions)): ?>
      <p style="color:#6b7280;font-size:13px">No login sessions recorded.</p>
      <?php else: ?>
      <?php foreach ($loginSessions as $ls): ?>
      <div class="session-item">
        <div class="session-dot" style="background:<?= $ls['is_active'] ? '#10b981' : '#6b7280' ?>"></div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;color:#e5e7eb;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($ls['full_name'] ?? $ls['employee_id']) ?></div>
          <div style="font-size:11px;color:#9ca3af"><?= htmlspecialchars($ls['role'] ?? '') ?> · <?= htmlspecialchars($ls['city'] ?? $ls['ip_address'] ?? '—') ?></div>
        </div>
        <div style="font-size:11px;color:#6b7280;flex-shrink:0"><?= !empty($ls['login_at']) ? date('d M, H:i', strtotime($ls['login_at'])) : '' ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /page -->

<script>
function copyText(t){navigator.clipboard.writeText(t).then(()=>{const b=event.currentTarget;b.innerHTML='<i class="fas fa-check"></i>';setTimeout(()=>b.innerHTML='<i class="fas fa-copy"></i>',1500)});}

async function setStatus(status){
  if(!confirm(`Set client status to "${status}"?`)) return;
  const r = await fetch('admin_api.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'set_client_status',client_id:'<?= htmlspecialchars($cid) ?>',status})}).then(x=>x.json());
  if(r.success) location.reload();
  else alert(r.error||'Error updating status');
}

async function resetSAPassword(){
  if(!confirm('Generate a new temporary password for the SuperAdmin and send via email?')) return;
  const r = await fetch('admin_api.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'reset_sa_password',client_id:'<?= htmlspecialchars($cid) ?>'})}).then(x=>x.json());
  if(r.success) alert('Password reset! New password sent to: ' + (r.email||'admin email'));
  else alert(r.error||'Error resetting password');
}
</script>
</body>
</html>
