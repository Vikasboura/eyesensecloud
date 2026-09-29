<?php
/* My Tenants — Member Dashboard
   Branch: 78-es-78-tenant-management--client-profile-module */
require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
requirePortalLogin();
$pdo     = get_indsac_db();
$s       = getPortalSession();
$cid     = $s['client_id'];
$empId   = $_SESSION['portal_employee_id'] ?? '';

// Resolve member record
$mSt = $pdo->prepare("SELECT id, full_name, flat_number FROM society_members WHERE client_id=? AND employee_id=? AND status='active' LIMIT 1");
$mSt->execute([$cid, $empId]);
$memberRow = $mSt->fetch();
if (!$memberRow) { header('Location: dashboard.php'); exit; }

// Tenant feature flags (graceful fallback if migration v9 not yet applied)
$canAdd     = true;
$canGenLink = true;
try {
    $stCfg = $pdo->prepare("SELECT * FROM maintenance_settings WHERE client_id=? LIMIT 1");
    $stCfg->execute([$cid]);
    $cfg = $stCfg->fetch() ?: [];
    $canAdd     = !array_key_exists('tenant_member_can_add',      $cfg) || (bool)$cfg['tenant_member_can_add'];
    $canGenLink = !array_key_exists('tenant_member_can_gen_link', $cfg) || (bool)$cfg['tenant_member_can_gen_link'];
} catch (Throwable $e) { /* columns not yet created — use defaults */ }

$pageTitle = 'My Tenants';
require_once __DIR__ . '/portal_header.php';
?>
<style>
.action-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:24px}
.btn-primary{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:9px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px;transition:opacity .2s}
.btn-primary:hover{opacity:.87}
.btn-secondary{background:rgba(99,102,241,.12);color:#6366f1;border:1px solid rgba(99,102,241,.25);border-radius:9px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px;transition:all .2s}
[data-theme="dark"] .btn-secondary{color:#a5b4fc}
.btn-secondary:hover{background:rgba(99,102,241,.2)}
.tenant-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(295px,1fr));gap:16px}
.t-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;transition:border .2s}
.t-card:hover{border-color:rgba(99,102,241,.4)}
.t-card-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
.t-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#a855f7);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:15px;flex-shrink:0}
.t-name{font-size:14px;font-weight:600;color:var(--textMain)}
.t-sub{font-size:12px;color:var(--textSec);margin-top:2px}
.badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600}
.badge-Pending{background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3)}
.badge-Active{background:rgba(16,185,129,.15);color:#34d399;border:1px solid rgba(16,185,129,.3)}
.badge-Rejected{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)}
.badge-Inactive{background:rgba(107,114,128,.15);color:#9ca3af;border:1px solid rgba(107,114,128,.2)}
.t-meta{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:12px 0;font-size:12px}
.t-meta-item{color:var(--textSec)}.t-meta-item span{color:var(--textMain);font-weight:500}
.btn-approve{background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3)}
[data-theme="dark"] .btn-approve{color:#34d399}
.btn-reject{background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.25)}
[data-theme="dark"] .btn-reject{color:#f87171}
.t-actions{display:flex;gap:6px;border-top:1px solid var(--border);padding-top:12px;flex-wrap:wrap}
.btn-sm{padding:5px 12px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;border:none;font-family:inherit;display:inline-flex;align-items:center;gap:5px}
.btn-sm:hover{opacity:.8}
.btn-edit{background:rgba(245,158,11,.12);color:#d97706}
[data-theme="dark"] .btn-edit{color:#fbbf24}
.btn-view{background:rgba(99,102,241,.12);color:#6366f1}
[data-theme="dark"] .btn-view{color:#a5b4fc}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:1000;display:none;align-items:center;justify-content:center;padding:16px}
.modal-overlay.show{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:18px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 32px 80px rgba(0,0,0,.3)}
[data-theme="dark"] .modal{box-shadow:0 32px 80px rgba(0,0,0,.6)}
.mhd{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
.mhd h3{font-size:16px;font-weight:700;color:var(--textMain)}
.mclose{background:none;border:none;color:var(--textSec);font-size:20px;cursor:pointer;padding:0;line-height:1}
.mbody{padding:22px 24px}
.mfoot{padding:14px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:13px;margin-bottom:13px}
.frow.one{grid-template-columns:1fr}
.fg{display:flex;flex-direction:column;gap:4px}
.fg label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--textSec)}
.fg input,.fg select,.fg textarea{background:var(--surfaceLight);border:1px solid var(--border);border-radius:8px;color:var(--textMain);font-family:inherit;font-size:13px;padding:9px 12px;outline:none;transition:border .2s;width:100%}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:#6366f1}
.fg textarea{resize:vertical;min-height:64px}
.fg select option{background:var(--surface)}
.ssep{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6366f1;margin:16px 0 11px;padding-bottom:5px;border-bottom:1px solid var(--border)}
/* Link share panel */
.link-box{background:var(--surfaceLight);border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:16px}
.link-url{font-size:12px;color:#6366f1;word-break:break-all;margin-bottom:10px;font-family:monospace}
[data-theme="dark"] .link-url{color:#a5b4fc}
.link-actions{display:flex;gap:8px;flex-wrap:wrap}
.share-btn{padding:7px 14px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;border:none;font-family:inherit;display:inline-flex;align-items:center;gap:6px;transition:opacity .2s}
.share-btn:hover{opacity:.82}
.sb-copy{background:rgba(99,102,241,.15);color:#6366f1}
[data-theme="dark"] .sb-copy{color:#a5b4fc}
.sb-wa{background:rgba(37,211,102,.15);color:#22c55e}
[data-theme="dark"] .sb-wa{color:#4ade80}
.sb-mail{background:rgba(245,158,11,.12);color:#d97706}
[data-theme="dark"] .sb-mail{color:#fbbf24}
.expiry-note{font-size:11px;color:var(--textSec);margin-top:8px;display:flex;align-items:center;gap:5px}
.empty-st{text-align:center;padding:60px 20px;color:var(--textSec);grid-column:1/-1}
.empty-st i{font-size:48px;margin-bottom:14px;opacity:.35;display:block}
.loading-st{text-align:center;padding:40px;color:var(--textSec);grid-column:1/-1}
.btn-danger{background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.25);border-radius:9px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px;transition:all .2s}
[data-theme="dark"] .btn-danger{color:#f87171}
.btn-danger:hover{background:rgba(239,68,68,.22)}
.btn-delete{background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.25)}
[data-theme="dark"] .btn-delete{color:#f87171}
.btn-edit-card{background:rgba(245,158,11,.12);color:#d97706;border:1px solid rgba(245,158,11,.25)}
[data-theme="dark"] .btn-edit-card{color:#fbbf24}
.search-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px 16px;background:var(--surface);border:1px solid var(--border);border-radius:12px;margin-bottom:18px}
.search-bar input,.search-bar select{background:var(--surfaceLight);border:1px solid var(--border);border-radius:8px;color:var(--textMain);font-family:inherit;font-size:13px;padding:7px 12px;outline:none;transition:border .2s}
.search-bar input:focus,.search-bar select:focus{border-color:#6366f1}
.search-bar input{min-width:200px;flex:1}
.tab-container{display:inline-flex;background:var(--surfaceLight);border:1px solid var(--border);border-radius:10px;padding:4px;gap:4px;margin-bottom:18px}
.tab-btn{background:none;border:none;color:var(--textSec);padding:7px 16px;font-size:13px;font-weight:600;border-radius:7px;cursor:pointer;font-family:inherit;transition:all .18s;display:inline-flex;align-items:center;gap:6px}
.tab-btn:hover{color:var(--textMain)}
.tab-btn.active{background:#6366f1;color:#fff;box-shadow:0 4px 12px rgba(99,102,241,0.25)}
@media(max-width:520px){.frow{grid-template-columns:1fr}.search-bar{flex-direction:column}}
.stat-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;display:flex;align-items:center;gap:14px}
.stat-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.stat-label{font-size:11px;color:var(--textSec);text-transform:uppercase;letter-spacing:.05em}
.stat-value{font-size:26px;font-weight:700;color:var(--textMain);line-height:1.1}
.rpt-tbl thead th{background:rgba(99,102,241,.1);color:var(--textMain);font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.05em;padding:12px 16px;text-align:left}
.rpt-tbl thead th:last-child{text-align:right}
.rpt-tbl tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
.rpt-tbl tbody tr:hover{background:var(--surfaceLight)}
@media(max-width:860px){.stat-cards{grid-template-columns:1fr 1fr}}
</style>
@media(max-width:860px){.stat-cards{grid-template-columns:1fr 1fr}}
</style>


<div style="padding:24px 28px">
  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:var(--textMain);margin:0"><i class="fas fa-house-user" style="color:#818cf8;margin-right:10px"></i>My Tenants</h1>
      <p style="font-size:13px;color:var(--textSec);margin:4px 0 0">Tenants registered under <strong style="color:#6366f1"><?= htmlspecialchars($memberRow['full_name']) ?></strong> (<?= htmlspecialchars($memberRow['flat_number'] ?? '') ?>)</p>
    </div>
  </div>

  <!-- Action Bar -->
  <div class="action-bar" style="margin-top:20px">
    <?php if ($canAdd): ?>
    <button class="btn-primary" onclick="openAddModal()">
      <i class="fas fa-plus"></i> Add Tenant
    </button>
    <?php endif; ?>
    <?php if ($canGenLink): ?>
    <button class="btn-secondary" onclick="genLink()">
      <i class="fas fa-link"></i> Generate Registration Link
    </button>
    <?php endif; ?>
    <a href="tenant_report.php" class="btn-secondary" style="text-decoration:none">
      <i class="fas fa-chart-bar"></i> Reports
    </a>
  </div>

  <!-- Stats -->
  <div class="stat-cards">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(99,102,241,.15);color:#818cf8"><i class="fas fa-users"></i></div><div><div class="stat-label">Total Tenants</div><div class="stat-value" id="sc-total">—</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(245,158,11,.12);color:#fbbf24"><i class="fas fa-clock"></i></div><div><div class="stat-label">Pending Approval</div><div class="stat-value" id="sc-pending">—</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(16,185,129,.12);color:#34d399"><i class="fas fa-check-circle"></i></div><div><div class="stat-label">Active</div><div class="stat-value" id="sc-active">—</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(139,92,246,.12);color:#a78bfa"><i class="fas fa-calendar-plus"></i></div><div><div class="stat-label">This Month</div><div class="stat-value" id="sc-month">—</div></div></div>
  </div>

  <!-- Tab Switcher -->
  <div class="tab-container">
    <button id="tabActive" class="tab-btn active" onclick="switchTab(0)"><i class="fas fa-user-check"></i> Active Tenants</button>
    <button id="tabHistory" class="tab-btn" onclick="switchTab(1)"><i class="fas fa-history"></i> See History</button>
  </div>

  <!-- Search & Filter Bar -->
  <div class="search-bar">
    <input type="text" id="srchQuery" placeholder="Search name, mobile, flat..." onkeydown="if(event.key==='Enter')filterTenants()">
    <select id="srchStatus">
      <option value="">All Statuses</option>
      <option value="Active">Active</option>
      <option value="Pending">Pending</option>
      <option value="Rejected">Rejected</option>
      <option value="Inactive">Inactive</option>
    </select>
    <select id="srchBlock">
      <option value="">All Blocks</option>
    </select>
    <button onclick="filterTenants()" style="background:linear-gradient(135deg,#6366f1,#818cf8);color:white;border:none;border-radius:8px;padding:7px 18px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px">
      <i class="fas fa-search"></i> Search
    </button>
    <button onclick="clearFilters()" style="background:rgba(190, 192, 196, 0.12);color:#9ca3af;border:1px solid rgba(120, 123, 131, 0.2);border-radius:8px;padding:7px 14px;font-size:13px;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px">
      <i class="fas fa-undo"></i> 
      <span style="font-weight:600;">Reset</span>
    </button>
  </div>

  <!-- Table Container -->
  <div class="tbl-wrap" style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow-x:auto">
    <table class="rpt-tbl" id="tenantTable" style="width:100%;border-collapse:collapse;font-size:13px;white-space:nowrap">
      <thead>
        <tr>
          <th>Tenant</th>
          <th>Contact</th>
          <th>Flat / Block</th>
          <th>Purpose</th>
          <th>Occupancy</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="tenantTableBody">
        <tr>
          <td colspan="7" class="loading-st" style="text-align:center;padding:40px;color:var(--textSec)">
            <i class="fas fa-spinner fa-spin" style="font-size:24px;color:#6366f1;display:block;margin-bottom:10px"></i>Loading your tenants…
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>


<!-- Generate Link Modal -->
<div class="modal-overlay" id="linkModal">
  <div class="modal" style="max-width:480px">
    <div class="mhd">
      <h3><i class="fas fa-link" style="color:#818cf8;margin-right:8px"></i>Tenant Registration Link</h3>
      <button class="mclose" onclick="closeModal('linkModal')">&#x2715;</button>
    </div>
    <div class="mbody">
      <p style="font-size:13px;color:#9ca3af;margin-bottom:14px">Share this link with your tenant. They can fill in their details directly — no login needed.</p>
      <div id="linkBox" style="display:none">
        <div class="link-box">
          <div class="link-url" id="linkUrl">Generating…</div>
          <div class="link-actions">
            <button class="share-btn sb-copy" onclick="copyLink()"><i class="fas fa-copy"></i> Copy Link</button>
            <button class="share-btn sb-wa" id="waBtn"><i class="fab fa-whatsapp"></i> WhatsApp</button>
            <button class="share-btn sb-mail" id="mailBtn"><i class="fas fa-envelope"></i> Email</button>
          </div>
          <div class="expiry-note"><i class="fas fa-clock"></i> Link expires in <strong id="linkExpiry" style="color:#f3f4f6;margin:0 2px">5 hours</strong> from now</div>
        </div>
      </div>
      <div id="linkSpinner" style="text-align:center;padding:20px"><i class="fas fa-spinner fa-spin" style="font-size:22px;color:#6366f1"></i></div>
    </div>
    <div class="mfoot">
      <button class="btn-secondary" onclick="closeModal('linkModal')" style="background:rgba(107,114,128,.12);color:#9ca3af;border:none">Close</button>
      <button class="btn-primary" onclick="genLink()"><i class="fas fa-refresh"></i> New Link</button>
    </div>
  </div>
</div>

<!-- Add Tenant Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="mhd">
      <h3><i class="fas fa-user-plus" style="color:#818cf8;margin-right:8px"></i>Add Tenant</h3>
      <button class="mclose" onclick="closeModal('addModal')">&#x2715;</button>
    </div>
    <div class="mbody">
      <div class="ssep"><i class="fas fa-user" style="margin-right:6px"></i>Personal Info</div>
      <div class="frow">
        <div class="fg"><label>Full Name *</label><input type="text" id="aFn" placeholder="Tenant full name"></div>
        <div class="fg"><label>Mobile *</label><input type="tel" id="aMob" placeholder="+91XXXXXXXXXX"></div>
        <div class="fg"><label>Email</label><input type="email" id="aEm" placeholder="email@example.com"></div>
        <div class="fg"><label>Gender</label>
          <select id="aGen"><option value="">—</option><option>Male</option><option>Female</option><option>Other</option></select></div>
      </div>
      <div class="ssep"><i class="fas fa-home" style="margin-right:6px"></i>Property</div>
      <div class="frow">
        <div class="fg"><label>Flat / Room *</label><input type="text" id="aFlt" placeholder="e.g. A-101"></div>
        <div class="fg"><label>Block / Wing</label><input type="text" id="aBlk" placeholder="e.g. A"></div>
        <div class="fg"><label>Move-in Date</label><input type="date" id="aOs"></div>
        <div class="fg"><label>Move-out Date</label><input type="date" id="aOe"></div>
      </div>
      <div class="ssep"><i class="fas fa-briefcase" style="margin-right:6px"></i>Details</div>
      <div class="frow">
        <div class="fg"><label>Purpose</label>
          <select id="aPur"><option value="">—</option><option>Rental</option><option>Family Member</option><option>Student</option><option>Employee</option><option>Guest</option><option>Other</option></select></div>
        <div class="fg"><label>Occupation</label><input type="text" id="aOcp" placeholder="e.g. Engineer"></div>
        <div class="fg"><label>Emergency Contact</label><input type="text" id="aEcn" placeholder="Name"></div>
        <div class="fg"><label>Emergency Mobile</label><input type="tel" id="aEcm" placeholder="Number"></div>
      </div>
      <div class="ssep"><i class="fas fa-id-card" style="margin-right:6px"></i>Identity</div>
      <div class="frow">
        <div class="fg"><label>ID Type</label>
          <select id="aGit"><option value="">—</option><option>Aadhaar</option><option>PAN</option><option>Passport</option><option>Driving License</option><option>Voter ID</option></select></div>
        <div class="fg"><label>ID Number</label><input type="text" id="aGin" placeholder="ID number"></div>
      </div>
      <div class="frow one">
        <div class="fg">
          <label><i class="fas fa-upload" style="margin-right:4px;color:#818cf8"></i>ID Document <span style="color:var(--textSec);font-weight:400">(JPG/PNG/PDF/WEBP, max 5 MB)</span></label>
          <input type="file" id="aDoc" accept=".jpg,.jpeg,.png,.pdf,.webp" style="background:var(--surfaceLight);border:1px solid var(--border);border-radius:8px;color:var(--textMain);padding:8px 12px;cursor:pointer;width:100%">
          <p style="font-size:11px;color:var(--textSec);margin-top:4px">Aadhaar, PAN, Passport, Driving Licence, or Voter ID. Upload is optional unless required by the society admin.</p>
        </div>
      </div>
      <div class="frow one">
        <div class="fg"><label>Notes</label><textarea id="aNts" rows="2" placeholder="Any info for admin…"></textarea></div>
      </div>
      <div class="ssep"><i class="fas fa-key" style="margin-right:6px"></i>Portal Account <span style="font-size:10px;color:#6b7280;text-transform:none;font-weight:400">(optional)</span></div>
      <div class="frow">
        <div class="fg"><label>Username</label><input type="text" id="aPu" placeholder="Login username"></div>
        <div class="fg"><label>Password</label><input type="password" id="aPp" placeholder="Login password"></div>
      </div>
      <p style="font-size:11px;color:#6b7280;margin-top:-6px"><i class="fas fa-info-circle" style="color:#6366f1;margin-right:4px"></i>Leave blank to skip. Tenant can log in using mobile or username + password at the portal.</p>
    </div>
    <div class="mfoot">
      <button class="btn-secondary" onclick="closeModal('addModal')" style="background:rgba(107,114,128,.12);color:#9ca3af;border:none">Cancel</button>
      <button class="btn-primary" onclick="addTenant()"><i class="fas fa-save"></i> Add Tenant</button>
    </div>
  </div>
</div>

<script>
const esc=s=>String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
const g=id=>document.getElementById(id);
const gv=id=>(g(id)?.value||'').trim();
const sv=(id,v)=>{
  const el=g(id); if(!el)return;
  if(el.tagName==='INPUT'||el.tagName==='SELECT'||el.tagName==='TEXTAREA')el.value=v??'';
  else el.textContent=v??'';
};
async function loadStats(){
  const d=await apig({action:'stats'});
  sv('sc-total',d.total??'0'); sv('sc-pending',d.pending??'0');
  sv('sc-active',d.active??'0'); sv('sc-month',d.this_month??'0');
}
function closeModal(id){g(id).classList.remove('show')}
function initials(n){return(n||'?').split(' ').slice(0,2).map(w=>w[0]||'').join('').toUpperCase()}
function elapsed(d){if(!d)return'—';const dy=Math.floor((Date.now()-new Date(d))/86400000);return dy===0?'Today':dy===1?'Yesterday':`${dy}d ago`}
function badge(s){const m={Pending:'badge-Pending',Active:'badge-Active',Rejected:'badge-Rejected',Inactive:'badge-Inactive'};return`<span class="badge ${m[s]||'badge-Inactive'}">${esc(s)}</span>`}

async function apig(p){const r=await fetch('../tenant_api.php?'+new URLSearchParams(p));return r.json()}
async function apip(b){const r=await fetch('../tenant_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)});return r.json()}
async function apipForm(fd){const r=await fetch('../tenant_api.php',{method:'POST',body:fd});return r.json()}

let _tenants={}, _currentTab=0;

async function loadTenants(){
  const tbody=g('tenantTableBody');
  tbody.innerHTML='<tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af"><i class="fas fa-spinner fa-spin" style="font-size:24px;color:#6366f1;display:block;margin-bottom:10px"></i>Loading…</td></tr>';
  const d=await apig({action:'my_list',history:_currentTab});
  const ts=d.tenants||[];
  _tenants={}; 
  ts.forEach(t=>_tenants[t.id]=t);
  // Populate block filter
  const blocks=[...new Set(ts.map(t=>t.block_wing).filter(Boolean))];
  const blkSel=g('srchBlock'); blkSel.innerHTML='<option value="">All Blocks</option>'+blocks.map(b=>`<option value="${esc(b)}">${esc(b)}</option>`).join('');
  renderTable(ts);
}

function renderTable(ts){
  const tbody=g('tenantTableBody');
  if(!ts.length){
    tbody.innerHTML='<tr><td colspan="7" class="empty-st" style="text-align:center;padding:40px;color:var(--textSec)"><i class="fas fa-house-user" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.4"></i>No tenants found.</td></tr>';
    return;
  }
  tbody.innerHTML=ts.map(t=>{
    const isPending = t.registration_status==='Pending';
    const approveBtn = isPending
      ? `<button class="btn-sm btn-approve" onclick="approveTenant(${t.id})" style="background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.2);padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer"><i class="fas fa-check"></i></button>
         <button class="btn-sm btn-reject" onclick="openReject(${t.id})" style="background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.2);padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer"><i class="fas fa-times"></i></button>`
      : '';
    const dateStr = (t.occupancy_start || '—') + (t.occupancy_end ? ' to ' + t.occupancy_end : '');
    
    return `<tr style="transition:background .15s">
      <td style="padding:12px 16px;display:flex;align-items:center;gap:10px">
        <div class="t-avatar" style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#a855f7);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:12px;flex-shrink:0">${initials(t.full_name)}</div>
        <div>
          <div style="font-weight:600;color:var(--textMain)">${esc(t.full_name)}</div>
          <div style="font-size:11px;color:var(--textSec)">${esc(t.email || '')}</div>
        </div>
      </td>
      <td style="padding:12px 16px;color:var(--textMain)">${esc(t.mobile)}</td>
      <td style="padding:12px 16px;color:var(--textMain)">${esc(t.flat_number)} ${t.block_wing ? ' (Block ' + esc(t.block_wing) + ')' : ''}</td>
      <td style="padding:12px 16px;color:var(--textMain)">${esc(t.purpose || '—')}</td>
      <td style="padding:12px 16px;color:var(--textMain);font-size:12px">${esc(dateStr)}</td>
      <td style="padding:12px 16px">${badge(t.registration_status)}</td>
      <td style="padding:12px 16px;text-align:right">
        <div style="display:inline-flex;gap:5px;align-items:center;justify-content:flex-end">
          <button class="btn-sm btn-view" onclick="viewDetails(${t.id})" style="padding:4px 8px;font-size:11px"><i class="fas fa-eye"></i> View</button>
          <button class="btn-sm btn-edit-card" onclick="editTenant(${t.id})" style="padding:4px 8px;font-size:11px"><i class="fas fa-pencil-alt"></i> Edit</button>
          <button class="btn-sm btn-delete" onclick="deleteTenant(${t.id})" style="padding:4px 8px;font-size:11px;background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.25)"><i class="fas fa-trash"></i> Disable</button>
          ${approveBtn}
        </div>
      </td>
    </tr>`;
  }).join('');
}

function switchTab(val){
  _currentTab=val;
  g('tabActive').classList.toggle('active',val===0);
  g('tabHistory').classList.toggle('active',val===1);
  loadTenants();
}

function filterTenants(){
  const q=(g('srchQuery').value||'').toLowerCase();
  const st=g('srchStatus').value;
  const blk=g('srchBlock').value;
  const all=Object.values(_tenants);
  const filtered=all.filter(t=>{
    if(st && t.registration_status!==st) return false;
    if(blk && (t.block_wing||'')!==blk) return false;
    if(q){
      const hay=(t.full_name+' '+t.mobile+' '+t.flat_number+' '+(t.email||'')).toLowerCase();
      if(!hay.includes(q)) return false;
    }
    return true;
  });
  renderTable(filtered);
}

function clearFilters(){
  sv('srchQuery',''); g('srchStatus').value=''; g('srchBlock').value=''; renderTable(Object.values(_tenants));
}


function openAddModal(){
  ['aFn','aMob','aEm','aFlt','aBlk','aOs','aOe','aOcp','aEcn','aEcm','aNts','aGin','aPu','aPp'].forEach(id=>sv(id,''));
  ['aGen','aPur','aGit'].forEach(id=>sv(id,''));
  if(g('aDoc')) g('aDoc').value='';
  g('addModal').classList.add('show');
}

async function addTenant(){
  if(!gv('aFn')||!gv('aMob')||!gv('aFlt')){alert('Full name, mobile and flat number are required');return}
  const fd=new FormData();
  fd.append('action','add');
  fd.append('full_name',gv('aFn')); fd.append('mobile',gv('aMob')); fd.append('email',gv('aEm'));
  fd.append('gender',gv('aGen')); fd.append('flat_number',gv('aFlt')); fd.append('block_wing',gv('aBlk'));
  fd.append('occupancy_start',gv('aOs')); fd.append('occupancy_end',gv('aOe'));
  fd.append('purpose',gv('aPur')); fd.append('occupation',gv('aOcp'));
  fd.append('emergency_contact_name',gv('aEcn')); fd.append('emergency_contact_number',gv('aEcm'));
  fd.append('govt_id_type',gv('aGit')); fd.append('govt_id_number',gv('aGin'));
  fd.append('notes',gv('aNts'));
  fd.append('portal_username',gv('aPu')); fd.append('portal_password',gv('aPp'));
  const docFile=g('aDoc')?.files?.[0];
  if(docFile) fd.append('govt_id_doc',docFile);
  const r=await apipForm(fd);
  if(r.success){closeModal('addModal');if(g('aDoc'))g('aDoc').value='';if(g('aPp'))g('aPp').value='';loadTenants();loadStats();alert('Tenant added! Status: '+r.status)}
  else alert(r.error||'Error adding tenant');
}

let _genUrl='';
async function genLink(){
  g('linkModal').classList.add('show');
  g('linkBox').style.display='none';
  g('linkSpinner').style.display='block';
  const d=await apig({action:'gen_link'});
  if(d.success){
    _genUrl=d.url;
    g('linkUrl').textContent=d.url;
    g('linkExpiry').textContent=d.expires_at||'5 hours';
    g('waBtn').onclick=()=>window.open('https://wa.me/?text='+encodeURIComponent('Register as my tenant: '+d.url),'_blank');
    g('mailBtn').onclick=()=>window.open('mailto:?subject=Tenant Registration&body='+encodeURIComponent('Please register as my tenant using this link:\n'+d.url),'_blank');
    g('linkBox').style.display='block';
    g('linkSpinner').style.display='none';
  } else {
    g('linkSpinner').innerHTML='<i class="fas fa-exclamation-circle" style="color:#f87171;font-size:22px"></i><br><br><span style="color:#f87171;font-size:13px">'+(d.error||'Failed to generate link')+'</span>';
  }
}

function copyLink(){
  navigator.clipboard.writeText(_genUrl).then(()=>{
    const btn=document.querySelector('.sb-copy');
    btn.innerHTML='<i class="fas fa-check"></i> Copied!';
    setTimeout(()=>btn.innerHTML='<i class="fas fa-copy"></i> Copy Link',2000);
  });
}

let _editingTid=0;
function editTenant(tid){
  const t=_tenants[tid]; if(!t) return;
  _editingTid=tid;
  sv('aFn',t.full_name||'');
  sv('aMob',t.mobile||'');
  sv('aEm',t.email||'');
  sv('aFlt',t.flat_number||'');
  sv('aBlk',t.block_wing||'');
  sv('aOs',t.occupancy_start||'');
  sv('aOe',t.occupancy_end||'');
  sv('aOcp',t.occupation||'');
  sv('aEcn',t.emergency_contact_name||'');
  sv('aEcm',t.emergency_contact_number||'');
  sv('aNts',t.notes||'');
  sv('aGin',t.govt_id_number||'');
  sv('aPu',t.portal_username||'');
  sv('aPp',''); // Clear password field
  
  if(g('aGen')) g('aGen').value=t.gender||'';
  if(g('aPur')) g('aPur').value=t.purpose||'';
  if(g('aGit')) g('aGit').value=t.govt_id_type||'';
  
  // Change modal title and button
  const title = g('addModal').querySelector('.mhd h3');
  if(title) title.innerHTML='<i class="fas fa-pencil-alt" style="color:#818cf8;margin-right:8px"></i>Edit Tenant';
  const saveBtn = g('addModal').querySelector('.mfoot .btn-primary');
  if(saveBtn) {
    saveBtn.innerHTML='<i class="fas fa-save"></i> Save Changes';
    saveBtn.onclick=saveTenantEdit;
  }
  if(g('aDoc')) g('aDoc').value='';
  g('addModal').classList.add('show');
}

async function saveTenantEdit(){
  if(!gv('aFn')||!gv('aMob')||!gv('aFlt')){alert('Full name, mobile and flat number are required');return}
  const fd=new FormData();
  fd.append('action','update');
  fd.append('tenant_id',_editingTid);
  fd.append('full_name',gv('aFn'));
  fd.append('mobile',gv('aMob'));
  fd.append('email',gv('aEm'));
  fd.append('gender',gv('aGen'));
  fd.append('flat_number',gv('aFlt'));
  fd.append('block_wing',gv('aBlk'));
  fd.append('occupancy_start',gv('aOs'));
  fd.append('occupancy_end',gv('aOe'));
  fd.append('purpose',gv('aPur'));
  fd.append('occupation',gv('aOcp'));
  fd.append('emergency_contact_name',gv('aEcn'));
  fd.append('emergency_contact_number',gv('aEcm'));
  fd.append('govt_id_type',gv('aGit'));
  fd.append('govt_id_number',gv('aGin'));
  fd.append('notes',gv('aNts'));
  fd.append('portal_username',gv('aPu'));
  if(gv('aPp')) fd.append('portal_password',gv('aPp'));
  
  const docFile=g('aDoc')?.files?.[0];
  if(docFile) fd.append('govt_id_doc',docFile);
  
  const r=await apipForm(fd);
  if(r.success){
    closeModal('addModal');
    _editingTid=0;
    // Reset modal title and button back to Add mode
    const title = g('addModal').querySelector('.mhd h3');
    if(title) title.innerHTML='<i class="fas fa-user-plus" style="color:#818cf8;margin-right:8px"></i>Add Tenant';
    const saveBtn = g('addModal').querySelector('.mfoot .btn-primary');
    if(saveBtn) {
      saveBtn.innerHTML='<i class="fas fa-save"></i> Add Tenant';
      saveBtn.onclick=addTenant;
    }
    loadTenants();loadStats();
  } else {
    alert(r.error||'Error saving changes');
  }
}

async function deleteTenant(tid){
  const t=_tenants[tid]; if(!t) return;
  if(!confirm('Disable tenant "'+t.full_name+'"? Status will become Inactive.')) return;
  const r=await apip({action:'delete',tenant_id:tid});
  if(r.success) { loadTenants(); loadStats(); }
  else alert(r.error||'Failed to disable');
}

async function approveTenant(tid){
  const t=_tenants[tid]; if(!t) return;
  if(!confirm('Approve tenant "'+t.full_name+'"? Their status will become Active.')) return;
  const r=await apip({action:'approve',tenant_id:tid});
  if(r.success) { loadTenants(); loadStats(); }
  else alert(r.error||'Failed to approve');
}

let _rejectTid=0;
function openReject(tid){
  const t=_tenants[tid]; if(!t) return;
  _rejectTid=tid;
  g('rejectName').textContent=t.full_name;
  g('rejectReason').value='';
  g('rejectModal').classList.add('show');
}
async function submitReject(){
  const reason=gv('rejectReason');
  if(!reason){alert('Please enter a reason');return;}
  const r=await apip({action:'reject',tenant_id:_rejectTid,reason});
  if(r.success){closeModal('rejectModal');loadTenants();loadStats();}
  else alert(r.error||'Failed to reject');
}

function viewDetails(tid){
  const t=_tenants[tid]; if(!t) return;
  const rows=[
    ['Full Name',t.full_name],['Mobile',t.mobile],['Email',t.email||'\u2014'],
    ['Flat',t.flat_number],['Block / Wing',t.block_wing||'\u2014'],['Floor',t.floor_number||'\u2014'],
    ['Purpose',t.purpose||'\u2014'],['Occupation',t.occupation||'\u2014'],
    ['Move-in',t.occupancy_start||'\u2014'],['Move-out',t.occupancy_end||'\u2014'],
    ['Status',t.registration_status],['Registered Via',t.registered_via||'\u2014'],
    ['ID Type',t.govt_id_type||'\u2014'],['ID Number',t.govt_id_number||'\u2014'],
    ['Emergency Contact',t.emergency_contact_name||'\u2014'],['Emergency Mobile',t.emergency_contact_number||'\u2014'],
    ['Notes',t.notes||'\u2014'],
  ];
  g('viewModalTitle').textContent = t.full_name;
  g('viewModalBody').innerHTML = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 20px">'
    + rows.map(([l,v]) => '<div><div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:2px">'+esc(l)+'</div>'
      + '<div style="font-size:13px;color:#e5e7eb;font-weight:500">'+esc(String(v))+'</div></div>').join('')
    + '</div>';
  g('viewModal').classList.add('show');
}

document.addEventListener('DOMContentLoaded', ()=>{loadTenants();loadStats();});
</script>
<!-- View Tenant Modal -->
<div class="modal-overlay" id="viewModal">
  <div class="modal" style="max-width:580px">
    <div class="mhd">
      <h3><i class="fas fa-user" style="color:#818cf8;margin-right:8px"></i><span id="viewModalTitle">Tenant Details</span></h3>
      <button class="mclose" onclick="closeModal('viewModal')">&#x2715;</button>
    </div>
    <div class="mbody" id="viewModalBody" style="padding:22px 24px"></div>
    <div class="mfoot">
      <button class="btn-secondary" onclick="closeModal('viewModal')" style="background:rgba(107,114,128,.12);color:#9ca3af;border:none">Close</button>
    </div>
  </div>
</div>


<div class="modal-overlay" id="rejectModal">
  <div class="modal" style="max-width:400px">
    <div class="mhd">
      <h3><i class="fas fa-times-circle" style="color:#f87171;margin-right:8px"></i>Reject Tenant</h3>
      <button class="mclose" onclick="closeModal('rejectModal')">&#x2715;</button>
    </div>
    <div class="mbody">
      <p style="font-size:13px;color:#9ca3af;margin-bottom:14px">Rejecting <strong id="rejectName" style="color:#f3f4f6"></strong>. Please provide a reason.</p>
      <div class="fg">
        <label>Reason for Rejection *</label>
        <textarea id="rejectReason" rows="3" placeholder="e.g. Documents incomplete, Could not verify identity…"></textarea>
      </div>
    </div>
    <div class="mfoot">
      <button class="btn-secondary" onclick="closeModal('rejectModal')" style="background:rgba(107,114,128,.12);color:#9ca3af;border:none">Cancel</button>
      <button class="btn-primary" style="background:linear-gradient(135deg,#dc2626,#b91c1c)" onclick="submitReject()"><i class="fas fa-times-circle"></i> Confirm Reject</button>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/portal_footer.php'; ?>
