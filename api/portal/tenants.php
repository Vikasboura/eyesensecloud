<?php
/* Tenant Management — Admin/SA Dashboard
   Branch: 78-es-78-tenant-management--client-profile-module */
require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
requirePortalLogin();
$pdo   = get_indsac_db();
$s     = getPortalSession();
$cid   = $s['client_id'];
$isSA  = !empty($s['is_superadmin']);
$perms = getAllPermissions($pdo, $cid, $s['role']);
if (!$isSA && empty($perms['MANAGE_MAINTENANCE'])) { header('Location: dashboard.php'); exit; }
$pageTitle = 'Tenant Management';
require_once __DIR__ . '/portal_header.php';
?>
<style>
.stat-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;display:flex;align-items:center;gap:14px}
.stat-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.stat-label{font-size:11px;color:var(--textSec);text-transform:uppercase;letter-spacing:.05em}
.stat-value{font-size:26px;font-weight:700;color:var(--textMain);line-height:1.1}
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center}
.filter-bar select,.filter-bar input{background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--textMain);padding:8px 12px;font-size:13px;font-family:inherit;outline:none}
.filter-bar select:focus,.filter-bar input:focus{border-color:#6366f1}
.filter-bar input{flex:1;min-width:180px}
.btn-primary{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:7px}
.btn-primary:hover{opacity:.88}
.tab-container{display:inline-flex;background:var(--surfaceLight);border:1px solid var(--border);border-radius:10px;padding:4px;gap:4px;margin-bottom:18px}
.tab-btn{background:none;border:none;color:var(--textSec);padding:7px 16px;font-size:13px;font-weight:600;border-radius:7px;cursor:pointer;font-family:inherit;transition:all .18s;display:inline-flex;align-items:center;gap:6px}
.tab-btn:hover{color:var(--textMain)}
.tab-btn.active{background:#6366f1;color:#fff;box-shadow:0 4px 12px rgba(99,102,241,0.25)}
.t-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#a855f7);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:12px;flex-shrink:0}
.t-name{font-size:15px;font-weight:600;color:var(--textMain)}
.t-sub{font-size:12px;color:var(--textSec);margin-top:2px}
.badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600}
.badge-Pending{background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3)}
.badge-Active{background:rgba(16,185,129,.15);color:#34d399;border:1px solid rgba(16,185,129,.3)}
.badge-Rejected{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)}
.badge-Inactive{background:rgba(107,114,128,.15);color:#9ca3af;border:1px solid rgba(107,114,128,.2)}
.t-meta{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:12px 0;font-size:12px}
.t-meta-item{color:var(--textSec)}.t-meta-item span{color:var(--textMain);font-weight:500}
.t-actions{display:flex;gap:6px;flex-wrap:wrap;border-top:1px solid var(--border);padding-top:12px}
.btn-sm{padding:5px 12px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;border:none;font-family:inherit;display:inline-flex;align-items:center;gap:5px}
.btn-sm:hover{opacity:.8}
.btn-view{background:rgba(99,102,241,.15);color:#6366f1}
[data-theme="dark"] .btn-view{color:#a5b4fc}
.btn-approve{background:rgba(16,185,129,.14);color:#10b981}
[data-theme="dark"] .btn-approve{color:#34d399}
.btn-reject{background:rgba(239,68,68,.12);color:#ef4444}
[data-theme="dark"] .btn-reject{color:#f87171}
.btn-del{background:rgba(107,114,128,.12);color:var(--textSec)}
.btn-edit{background:rgba(245,158,11,.12);color:#d97706}
[data-theme="dark"] .btn-edit{color:#fbbf24}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:1000;display:none;align-items:center;justify-content:center;padding:16px}
.modal-overlay.show{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:18px;width:100%;max-width:640px;max-height:90vh;overflow-y:auto;box-shadow:0 32px 80px rgba(0,0,0,.3)}
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
.empty-st{text-align:center;padding:60px 20px;color:var(--textSec);grid-column:1/-1}
.empty-st i{font-size:48px;margin-bottom:14px;opacity:.35;display:block}
.loading-st{text-align:center;padding:40px;color:var(--textSec);grid-column:1/-1}
.rpt-tbl thead th{background:rgba(99,102,241,.1);color:var(--textMain);font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.05em;padding:12px 16px;text-align:left}
.rpt-tbl thead th:last-child{text-align:right}
.rpt-tbl tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
.rpt-tbl tbody tr:hover{background:var(--surfaceLight)}
@media(max-width:860px){.stat-cards{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.stat-cards{grid-template-columns:1fr 1fr};.tenant-grid{grid-template-columns:1fr}}
</style>
</style>

<div style="padding:24px 28px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:var(--textMain);margin:0"><i class="fas fa-house-user" style="color:#818cf8;margin-right:10px"></i>Tenant Management</h1>
      <p style="font-size:13px;color:var(--textSec);margin:4px 0 0">Manage and approve tenant registrations across all members</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Tenant</button>
      <button class="btn-primary" onclick="openGenLinkModal()" style="background:linear-gradient(135deg,#059669,#047857)"><i class="fas fa-link"></i> Generate Link</button>
      <a href="tenant_report.php" class="btn-primary" style="text-decoration:none;background:linear-gradient(135deg,#7c3aed,#a855f7)"><i class="fas fa-chart-bar"></i> Reports</a>
    </div>
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

  <!-- Filters -->
  <div class="filter-bar">
    <select id="fMember" onchange="loadTenants()" style="min-width:180px"><option value="">All Members</option></select>
    <select id="fStatus" onchange="loadTenants()">
      <option value="">All Status</option>
      <option>Pending</option><option>Active</option><option>Rejected</option><option>Inactive</option>
    </select>
    <select id="fPurpose" onchange="loadTenants()">
      <option value="">All Purposes</option>
      <option>Rental</option><option>Family Member</option><option>Student</option>
      <option>Employee</option><option>Guest</option><option>Other</option>
    </select>
    <input type="text" id="fSearch" placeholder="&#128269; Search name, mobile, flat…" oninput="debLoad()">
    <button class="btn-primary" onclick="loadTenants()" title="Refresh"><i class="fas fa-sync-alt"></i></button>
  </div>

  <!-- Table Container -->
  <div class="tbl-wrap" style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow-x:auto">
    <table class="rpt-tbl" id="tenantTable" style="width:100%;border-collapse:collapse;font-size:13px;white-space:nowrap">
      <thead>
        <tr>
          <th>Tenant</th>
          <th>Contact</th>
          <th>Flat / Member</th>
          <th>Purpose</th>
          <th>Occupancy</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="tenantTableBody">
        <tr>
          <td colspan="7" class="loading-st" style="text-align:center;padding:40px;color:var(--textSec)">
            <i class="fas fa-spinner fa-spin" style="font-size:24px;color:#6366f1;display:block;margin-bottom:10px"></i>Loading tenants…
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>


<!-- Add/Edit Tenant Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="mhd">
      <h3 id="modalTitle"><i class="fas fa-user-plus" style="margin-right:8px;color:#818cf8"></i>Add New Tenant</h3>
      <button class="mclose" onclick="closeModal('addModal')">&#x2715;</button>
    </div>
    <div class="mbody">
      <input type="hidden" id="eTid">
      <div class="ssep"><i class="fas fa-user" style="margin-right:6px"></i>Personal Info</div>
      <div class="frow">
        <div class="fg"><label>Full Name *</label><input type="text" id="mFn" placeholder="Full legal name"></div>
        <div class="fg"><label>Mobile *</label><input type="tel" id="mMob" placeholder="+91XXXXXXXXXX"></div>
        <div class="fg"><label>Email</label><input type="email" id="mEm" placeholder="email@example.com"></div>
        <div class="fg"><label>Gender</label>
          <select id="mGen"><option value="">—</option><option>Male</option><option>Female</option><option>Other</option></select></div>
        <div class="fg"><label>Date of Birth</label><input type="date" id="mDob"></div>
        <div class="fg"><label>Alternate Mobile</label><input type="tel" id="mAmb" placeholder="Optional"></div>
      </div>
      <div class="ssep"><i class="fas fa-home" style="margin-right:6px"></i>Property</div>
      <div class="frow">
        <div class="fg"><label>Member *</label>
          <select id="mMid"><option value="">— Select Member —</option></select></div>
        <div class="fg"><label>Flat / Room *</label><input type="text" id="mFlt" placeholder="e.g. A-101"></div>
        <div class="fg"><label>Block / Wing</label><input type="text" id="mBlk" placeholder="e.g. A"></div>
        <div class="fg"><label>Floor</label><input type="text" id="mFlr" placeholder="e.g. 2nd"></div>
        <div class="fg"><label>Move-in Date</label><input type="date" id="mOs"></div>
        <div class="fg"><label>Move-out Date</label><input type="date" id="mOe"></div>
      </div>
      <div class="ssep"><i class="fas fa-id-card" style="margin-right:6px"></i>Identity</div>
      <div class="frow">
        <div class="fg"><label>ID Type</label>
          <select id="mGit"><option value="">—</option><option>Aadhaar</option><option>PAN</option><option>Passport</option><option>Driving License</option><option>Voter ID</option></select></div>
        <div class="fg"><label>ID Number</label><input type="text" id="mGin" placeholder="ID number"></div>
      </div>
      <div class="frow one" id="docUploadRow">
        <div class="fg">
          <label><i class="fas fa-upload" style="margin-right:4px;color:#818cf8"></i>ID Document <span style="color:var(--textSec);font-weight:400">(JPG/PNG/PDF/WEBP, max 5 MB)</span></label>
          <input type="file" id="mDoc" accept=".jpg,.jpeg,.png,.pdf,.webp" style="background:var(--surfaceLight);border:1px solid var(--border);border-radius:8px;color:var(--textMain);padding:8px 12px;cursor:pointer">
          <p style="font-size:11px;color:var(--textSec);margin-top:4px">Aadhaar, PAN, Passport, Driving Licence, or Voter ID. Upload is optional unless required by the society admin.</p>
        </div>
      </div>
      <div class="ssep"><i class="fas fa-briefcase" style="margin-right:6px"></i>Tenant Details</div>
      <div class="frow">
        <div class="fg"><label>Purpose</label>
          <select id="mPur"><option value="">—</option><option>Rental</option><option>Family Member</option><option>Student</option><option>Employee</option><option>Guest</option><option>Other</option></select></div>
        <div class="fg"><label>Occupation</label><input type="text" id="mOcp" placeholder="e.g. Engineer"></div>
        <div class="fg"><label>Emergency Contact</label><input type="text" id="mEcn" placeholder="Name"></div>
        <div class="fg"><label>Emergency Mobile</label><input type="tel" id="mEcm" placeholder="Number"></div>
      </div>
      <div class="frow one">
        <div class="fg"><label>Notes</label><textarea id="mNts" rows="2" placeholder="Additional info…"></textarea></div>
      </div>
      <div class="ssep"><i class="fas fa-key" style="margin-right:6px"></i>Portal Account <span style="font-size:10px;color:#6b7280;text-transform:none;font-weight:400">(optional)</span></div>
      <div class="frow">
        <div class="fg"><label>Username</label><input type="text" id="mPu" placeholder="Login username"></div>
        <div class="fg"><label>Password</label><input type="password" id="mPp" placeholder="Login password"></div>
      </div>
      <p style="font-size:11px;color:#6b7280;margin-top:-8px;margin-bottom:6px"><i class="fas fa-info-circle" style="color:#6366f1;margin-right:4px"></i>Leave blank to skip portal access. Tenant can also log in using mobile number.</p>
    </div>
    <div class="mfoot">
      <button class="btn-sm btn-del" onclick="closeModal('addModal')"><i class="fas fa-times"></i> Cancel</button>
      <button class="btn-primary" onclick="saveTenant()"><i class="fas fa-save"></i> <span id="saveTxt">Add Tenant</span></button>
    </div>
  </div>
</div>

<!-- View Tenant Modal -->
<div class="modal-overlay" id="viewModal">
  <div class="modal" style="max-width:620px">
    <div class="mhd">
      <h3><i class="fas fa-user" style="color:#818cf8;margin-right:8px"></i><span id="vModalTitle">Tenant Details</span></h3>
      <button class="mclose" onclick="closeModal('viewModal')">&#x2715;</button>
    </div>
    <div class="mbody" id="vModalBody" style="padding:22px 24px"></div>
    <div class="mfoot">
      <button class="btn-sm btn-del" onclick="closeModal('viewModal')"><i class="fas fa-times"></i> Close</button>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal" style="max-width:420px">
    <div class="mhd"><h3><i class="fas fa-times-circle" style="color:#f87171;margin-right:8px"></i>Reject Tenant</h3>
      <button class="mclose" onclick="closeModal('rejectModal')">&#x2715;</button></div>
    <div class="mbody">
      <div class="fg"><label>Reason for Rejection *</label>
        <textarea id="rReason" rows="3" placeholder="Explain why this tenant is being rejected…"></textarea></div>
    </div>
    <div class="mfoot">
      <button class="btn-sm btn-del" onclick="closeModal('rejectModal')">Cancel</button>
      <button class="btn-sm btn-reject" onclick="confirmReject()"><i class="fas fa-times-circle"></i> Confirm Reject</button>
    </div>
  </div>
</div>


<!-- Generate Link Modal (Admin) -->
<div class="modal-overlay" id="genLinkModal">
  <div class="modal" style="max-width:480px">
    <div class="mhd"><h3><i class="fas fa-link" style="margin-right:8px;color:#34d399"></i>Generate Registration Link</h3>
      <button class="mclose" onclick="closeModal('genLinkModal')">&#x2715;</button></div>
    <div class="mbody">
      <p style="font-size:13px;color:#9ca3af;margin-bottom:16px">Select a member to generate a 5-hour self-registration link for their tenant.</p>
      <div class="fg" style="margin-bottom:16px">
        <label>Member *</label>
        <select id="genLinkMid"><option value="">— Select Member —</option></select>
      </div>
      <div id="genLinkResult" style="display:none">
        <div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.25);border-radius:10px;padding:14px;margin-top:12px">
          <div style="font-size:11px;color:var(--textSec);margin-bottom:6px"><i class="fas fa-clock" style="margin-right:4px"></i>Expires: <span id="genLinkExpiry"></span></div>
          <div style="display:flex;gap:8px;align-items:center">
            <input id="genLinkUrl" readonly style="flex:1;background:var(--surfaceLight);border:1px solid var(--border);border-radius:8px;color:#10b981;font-size:11px;padding:8px 10px;font-family:monospace">
            <button class="btn-sm btn-approve" onclick="copyGenLink()"><i class="fas fa-copy"></i></button>
          </div>
          <div style="display:flex;gap:8px;margin-top:10px">
            <a id="waLink" href="#" target="_blank" class="btn-sm btn-approve" style="text-decoration:none"><i class="fab fa-whatsapp"></i> WhatsApp</a>
            <a id="mailLink" href="#" class="btn-sm btn-edit" style="text-decoration:none"><i class="fas fa-envelope"></i> Email</a>
          </div>
        </div>
      </div>
    </div>
    <div class="mfoot">
      <button class="btn-sm btn-del" onclick="closeModal('genLinkModal')">Close</button>
      <button class="btn-primary" onclick="genLink()"><i class="fas fa-link"></i> Generate</button>
    </div>
  </div>
</div>

<script>
let _members=[], _debT=null, _rejectTid=null, _tenants={}, _currentTab=0;
const esc=s=>String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
const g=id=>document.getElementById(id);
const gv=id=>(g(id)?.value||'').trim();
const sv=(id,v)=>{
  const el=g(id); if(!el)return;
  if(el.tagName==='INPUT'||el.tagName==='SELECT'||el.tagName==='TEXTAREA')el.value=v??'';
  else el.textContent=v??'';
};
function closeModal(id){g(id).classList.remove('show')}
function initials(n){return(n||'?').split(' ').slice(0,2).map(w=>w[0]||'').join('').toUpperCase()}
function elapsed(d){if(!d)return'—';const dy=Math.floor((Date.now()-new Date(d))/86400000);return dy===0?'Today':dy===1?'Yesterday':`${dy}d ago`}
function badge(s){return`<span class="badge badge-${esc(s)}">${esc(s)}</span>`}

async function apig(p){const r=await fetch('../tenant_api.php?'+new URLSearchParams(p));return r.json()}
async function apip(b){const r=await fetch('../tenant_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)});return r.json()}
async function apipForm(fd){const r=await fetch('../tenant_api.php',{method:'POST',body:fd});return r.json()}

async function loadStats(){
  const d=await apig({action:'stats'});
  sv('sc-total',d.total??'0'); sv('sc-pending',d.pending??'0');
  sv('sc-active',d.active??'0'); sv('sc-month',d.this_month??'0');
}

async function loadMembers(){
  const d=await apig({action:'get_members'});
  _members=d.members||[];
  ['fMember','mMid','genLinkMid'].forEach(sel=>{
    const el=g(sel); if(!el)return;
    while(el.options.length>1) el.remove(1);
    _members.forEach(m=>{
      const o=document.createElement('option');
      o.value=m.id; o.textContent=`${m.full_name} — ${m.flat_number||m.member_code||''}`;
      el.appendChild(o);
    });
  });
}

async function loadTenants(){
  const tbody=g('tenantTableBody');
  tbody.innerHTML='<tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af"><i class="fas fa-spinner fa-spin" style="font-size:24px;color:#6366f1;display:block;margin-bottom:10px"></i>Loading…</td></tr>';
  const d=await apig({action:'list',member_id:gv('fMember'),status:gv('fStatus'),purpose:gv('fPurpose'),search:gv('fSearch'),history:_currentTab});
  const ts=d.tenants||[];
  _tenants={}; ts.forEach(t=>_tenants[t.id]=t);
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
        <div class="t-avatar">${initials(t.full_name)}</div>
        <div>
          <div style="font-weight:600;color:var(--textMain)">${esc(t.full_name)}</div>
          <div style="font-size:11px;color:var(--textSec)">${esc(t.email || '')}</div>
        </div>
      </td>
      <td style="padding:12px 16px;color:var(--textMain)">${esc(t.mobile)}</td>
      <td style="padding:12px 16px;color:var(--textMain)">
        <div style="font-weight:500;color:var(--textMain)">${esc(t.flat_number)}</div>
        <div style="font-size:11px;color:var(--textSec)">Owner: ${esc(t.member_name||'—')}</div>
      </td>
      <td style="padding:12px 16px;color:var(--textMain)">${esc(t.purpose || '—')}</td>
      <td style="padding:12px 16px;color:var(--textMain);font-size:12px">${esc(dateStr)}</td>
      <td style="padding:12px 16px">${badge(t.registration_status)}</td>
      <td style="padding:12px 16px;text-align:right">
        <div style="display:inline-flex;gap:5px;align-items:center;justify-content:flex-end">
          <button class="btn-sm btn-view" onclick="viewTenant(${t.id})" style="padding:4px 8px;font-size:11px"><i class="fas fa-eye"></i> View</button>
          <button class="btn-sm btn-edit" onclick="openEdit(_tenants[${t.id}])" style="padding:4px 8px;font-size:11px"><i class="fas fa-edit"></i> Edit</button>
          <button class="btn-sm btn-del" onclick="delTenant(${t.id},'${esc(t.full_name).replace(/'/g,"\\'")}')" style="padding:4px 8px;font-size:11px;background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.25)"><i class="fas fa-trash"></i> Disable</button>
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

function debLoad(){clearTimeout(_debT);_debT=setTimeout(loadTenants,380)}

function openAddModal(){
  sv('eTid',''); g('modalTitle').innerHTML='<i class="fas fa-user-plus" style="margin-right:8px;color:#818cf8"></i>Add New Tenant';
  sv('saveTxt','Add Tenant');
  ['mFn','mMob','mEm','mAmb','mDob','mFlt','mBlk','mFlr','mOs','mOe','mGin','mOcp','mEcn','mEcm','mNts','mPu','mPp'].forEach(id=>sv(id,''));
  ['mGen','mMid','mGit','mPur'].forEach(id=>sv(id,''));
  if(g('mDoc')) g('mDoc').value='';
  g('addModal').classList.add('show');
}

function openEdit(t){
  sv('eTid',t.id); g('modalTitle').innerHTML='<i class="fas fa-edit" style="margin-right:8px;color:#fbbf24"></i>Edit Tenant';
  sv('saveTxt','Save Changes');
  sv('mFn',t.full_name); sv('mMob',t.mobile); sv('mEm',t.email); sv('mAmb',t.alt_mobile);
  sv('mDob',t.dob); sv('mFlt',t.flat_number); sv('mBlk',t.block_wing); sv('mFlr',t.floor_number);
  sv('mOs',t.occupancy_start); sv('mOe',t.occupancy_end); sv('mGit',t.govt_id_type);
  sv('mGin',t.govt_id_number); sv('mOcp',t.occupation); sv('mEcn',t.emergency_contact_name);
  sv('mEcm',t.emergency_contact_number); sv('mNts',t.notes);
  sv('mGen',t.gender); sv('mMid',t.member_id); sv('mPur',t.purpose);
  g('addModal').classList.add('show');
}

async function saveTenant(){
  const id=gv('eTid');
  if(!gv('mFn')||!gv('mMob')||!gv('mFlt')){alert('Full name, mobile and flat number are required');return}
  const mid=parseInt(gv('mMid'));
  if(!id && !mid){alert('Please select a member');return}
  const fd=new FormData();
  fd.append('action',id?'update':'add');
  if(id) fd.append('tenant_id',id);
  if(mid) fd.append('member_id',mid);
  fd.append('full_name',gv('mFn')); fd.append('mobile',gv('mMob')); fd.append('flat_number',gv('mFlt'));
  fd.append('email',gv('mEm')); fd.append('alt_mobile',gv('mAmb')); fd.append('gender',gv('mGen'));
  fd.append('dob',gv('mDob')); fd.append('block_wing',gv('mBlk')); fd.append('floor_number',gv('mFlr'));
  fd.append('occupancy_start',gv('mOs')); fd.append('occupancy_end',gv('mOe'));
  fd.append('govt_id_type',gv('mGit')); fd.append('govt_id_number',gv('mGin'));
  fd.append('purpose',gv('mPur')); fd.append('occupation',gv('mOcp'));
  fd.append('emergency_contact_name',gv('mEcn')); fd.append('emergency_contact_number',gv('mEcm'));
  fd.append('notes',gv('mNts'));
  fd.append('portal_username',gv('mPu')); fd.append('portal_password',gv('mPp'));
  const docFile=g('mDoc')?.files?.[0];
  if(docFile) fd.append('govt_id_doc',docFile);
  const r=await apipForm(fd);
  if(r.success){closeModal('addModal');if(g('mDoc'))g('mDoc').value='';if(g('mPp'))g('mPp').value='';loadTenants();loadStats()}
  else alert(r.error||'Error saving tenant');
}

async function approveTenant(id){
  if(!confirm('Approve this tenant and set status to Active?'))return;
  const r=await apip({action:'approve',tenant_id:id});
  if(r.success){loadTenants();loadStats()}else alert(r.error||'Error');
}

function openReject(id){_rejectTid=id;sv('rReason','');g('rejectModal').classList.add('show')}
async function confirmReject(){
  const reason=gv('rReason');
  if(!reason){alert('Enter a rejection reason');return}
  const r=await apip({action:'reject',tenant_id:_rejectTid,reason});
  if(r.success){closeModal('rejectModal');loadTenants();loadStats()}else alert(r.error||'Error');
}

async function delTenant(id,name){
  if(!confirm(`Disable tenant "${name}"? Status will become Inactive.`))return;
  const r=await apip({action:'delete',tenant_id:id});
  if(r.success){loadTenants();loadStats()}else alert(r.error||'Error');
}

function openGenLinkModal(){g('genLinkResult').style.display='none';sv('genLinkMid','');g('genLinkModal').classList.add('show');}
async function genLink(){
  const mid=gv('genLinkMid');
  if(!mid){alert('Please select a member');return}
  const r=await apip({action:'gen_link',member_id:parseInt(mid)});
  if(!r.success){alert(r.error||'Error generating link');return}
  g('genLinkUrl').value=r.url;
  g('genLinkExpiry').textContent=r.expires_at||'';
  const msg=encodeURIComponent('Register as a tenant using this link (valid 5 hours):\n'+r.url);
  g('waLink').href='https://wa.me/?text='+msg;
  g('mailLink').href='mailto:?subject=Tenant Registration Link&body='+encodeURIComponent('Register via: '+r.url);
  g('genLinkResult').style.display='block';
}
function copyGenLink(){const u=g('genLinkUrl');u.select();navigator.clipboard.writeText(u.value).then(()=>alert('Link copied!'));}

function viewTenant(tid){
  const t=_tenants[tid]; if(!t) return;
  const rows=[
    ['Full Name',t.full_name],['Mobile',t.mobile],['Email',t.email||'—'],
    ['Gender',t.gender||'—'],['DOB',t.dob||'—'],['Alternate Mobile',t.alt_mobile||'—'],
    ['Member / Owner',t.member_name||'—'],['Flat',t.flat_number],
    ['Block / Wing',t.block_wing||'—'],['Floor',t.floor_number||'—'],
    ['Purpose',t.purpose||'—'],['Occupation',t.occupation||'—'],
    ['Move-in',t.occupancy_start||'—'],['Move-out',t.occupancy_end||'—'],
    ['Status',t.registration_status],['Registered Via',t.registered_via||'—'],
    ['ID Type',t.govt_id_type||'—'],['ID Number',t.govt_id_number||'—'],
    ['Emergency Contact',t.emergency_contact_name||'—'],['Emergency Mobile',t.emergency_contact_number||'—'],
    ['Portal Username',t.portal_username||'—'],['Notes',t.notes||'—'],
  ];
  g('vModalTitle').textContent = t.full_name;
  g('vModalBody').innerHTML = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 24px">'
    + rows.map(([l,v])=>'<div><div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:2px">'+esc(l)+'</div>'
      +'<div style="font-size:13px;color:#e5e7eb;font-weight:500">'+esc(String(v))+'</div></div>').join('')
    +'</div>';
  g('viewModal').classList.add('show');
}

document.addEventListener('DOMContentLoaded',()=>{loadStats();loadMembers();loadTenants()});
</script>
<?php require_once __DIR__ . '/portal_footer.php'; ?>
