<?php
/**
 * EyeSense — Public Tenant Self-Registration Page
 * URL: /api/tenant_register.php?token=XXXX
 * No login required — token encodes client_id + member_id + 5-hr expiry.
 * Branch: 78-es-78-tenant-management--client-profile-module
 */
require_once __DIR__ . '/portal_config.php';

define('TENANT_TOKEN_TTL',    5 * 3600);
define('TENANT_TOKEN_SECRET', DB_CFG_LICENSE_API_SECRET . '_TENANT_REG');

function verify_tenant_token_pub(string $token): ?array {
    $parts = explode('.', strtr($token, '-_', '+/'));
    if (count($parts) !== 2) return null;
    [$b64pay, $b64sig] = $parts;
    $pad      = fn($s) => $s . str_repeat('=', (4 - strlen($s) % 4) % 4);
    $expected = rtrim(base64_encode(hash_hmac('sha256', $b64pay, TENANT_TOKEN_SECRET, true)), '=');
    if (!hash_equals($expected, $b64sig)) return null;
    $raw  = base64_decode($pad($b64pay));
    $bits = explode('|', $raw, 3);
    if (count($bits) !== 3) return null;
    [$cid, $mid, $exp] = $bits;
    if ((int)$exp < time()) return null;
    return ['client_id' => $cid, 'member_id' => (int)$mid, 'expires' => (int)$exp];
}

$token   = trim($_GET['token'] ?? '');
$ctx     = $token ? verify_tenant_token_pub($token) : null;
$valid   = $ctx !== null;
$sName   = 'Society';
$mName   = '';
$mFlat   = '';
$docReq  = false;
$ttlMins = 0;

if ($valid) {
    $pdo = get_indsac_db();
    $st  = $pdo->prepare("SELECT society_name, tenant_doc_required FROM maintenance_settings WHERE client_id=? LIMIT 1");
    $st->execute([$ctx['client_id']]);
    $cfg    = $st->fetch() ?: [];
    $sName  = $cfg['society_name'] ?? 'Society';
    $docReq = !empty($cfg['tenant_doc_required']);
    $st2    = $pdo->prepare("SELECT full_name, flat_number FROM society_members WHERE id=? AND client_id=? AND status='active' LIMIT 1");
    $st2->execute([$ctx['member_id'], $ctx['client_id']]);
    $mem   = $st2->fetch() ?: [];
    $mName = $mem['full_name'] ?? '';
    $mFlat = $mem['flat_number'] ?? '';
    $ttlMins = max(0, (int)(($ctx['expires'] - time()) / 60));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tenant Registration — <?= htmlspecialchars($sName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Outfit',sans-serif;background:linear-gradient(135deg,#080812 0%,#0e0e1c 100%);min-height:100vh;display:flex;justify-content:center;padding:24px 14px}
.card{background:rgba(14,14,28,.97);border:1px solid rgba(99,102,241,.22);border-radius:20px;width:100%;max-width:640px;overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,.7);height:fit-content}
.hd{background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:26px 30px;text-align:center}
.hd h1{color:#fff;font-size:21px;font-weight:700;margin-bottom:4px}
.hd p{color:rgba(255,255,255,.72);font-size:13px}
.ttl{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.14);border-radius:999px;padding:3px 12px;font-size:12px;color:#e0e7ff;margin-top:10px}
.body{padding:26px 30px}
.sec{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6366f1;margin:20px 0 12px;padding-bottom:6px;border-bottom:1px solid rgba(99,102,241,.18)}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:13px}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:13px}
.f{display:flex;flex-direction:column;gap:4px}
.f label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af}
.req{color:#f87171}
.f input,.f select,.f textarea{background:#080814;border:1px solid #252535;border-radius:8px;color:#e5e7eb;font-family:inherit;font-size:13.5px;padding:9px 12px;outline:none;transition:border .2s;width:100%}
.f input:focus,.f select:focus,.f textarea:focus{border-color:#6366f1}
.f textarea{resize:vertical;min-height:68px}
.f select option{background:#0c0c1c}
.hint{font-size:11px;color:#6b7280;margin-top:1px}
.minfo{background:rgba(99,102,241,.09);border:1px solid rgba(99,102,241,.2);border-radius:9px;padding:11px 15px;margin-bottom:2px;font-size:13px;color:#a5b4fc}
.file-wrap{display:flex;align-items:center;gap:8px;padding:9px 12px;background:#080814;border:1.5px dashed #2e2e45;border-radius:8px;cursor:pointer;color:#9ca3af;font-size:13px;transition:border .2s}
.file-wrap:hover{border-color:#6366f1;color:#c7d2fe}
.file-wrap input{display:none}
.alert{border-radius:9px;padding:11px 15px;font-size:13px;margin-bottom:14px;display:none}
.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.ok{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#6ee7b7}
.btn{width:100%;padding:13px;border:none;border-radius:10px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;font-size:15px;font-weight:600;cursor:pointer;transition:opacity .2s;margin-top:10px;font-family:inherit;letter-spacing:.02em}
.btn:hover{opacity:.87}.btn:disabled{opacity:.45;cursor:not-allowed}
.inv{text-align:center;padding:52px 26px}
.inv i{font-size:52px;color:#ef4444;margin-bottom:16px;display:block}
.inv h2{color:#fff;font-size:22px;margin-bottom:8px}
.inv p{color:#9ca3af;font-size:14px;line-height:1.75}
.suc{text-align:center;padding:52px 26px;display:none}
.suc i{font-size:56px;color:#10b981;margin-bottom:16px;display:block}
.suc h2{color:#fff;font-size:22px;margin-bottom:8px}
.suc p{color:#9ca3af;font-size:14px;line-height:1.75}
@media(max-width:520px){.g2,.g3{grid-template-columns:1fr}.body{padding:18px 15px}}
</style>
</head>
<body>
<div class="card">
  <div class="hd">
    <h1><i class="fas fa-house-user" style="margin-right:8px"></i><?= htmlspecialchars($sName) ?></h1>
    <p>Tenant Self-Registration Form</p>
    <?php if ($valid && $ttlMins > 0): ?>
    <div class="ttl"><i class="fas fa-clock"></i> Link valid for <?= $ttlMins ?> minute<?= $ttlMins !== 1 ? 's' : '' ?></div>
    <?php endif; ?>
  </div>

  <?php if (!$valid): ?>
  <div class="body inv">
    <i class="fas fa-link-slash"></i>
    <h2>Link Expired or Invalid</h2>
    <p>This registration link has expired or is not valid.<br>Please ask the society member to generate a new link.</p>
  </div>
  <?php else: ?>
  <div class="body" id="formWrap">
    <div id="aErr" class="alert err"></div>

    <?php if ($mName): ?>
    <div class="minfo"><i class="fas fa-user-tie" style="margin-right:6px"></i>
      Registering as tenant of <strong><?= htmlspecialchars($mName) ?></strong>
      <?php if ($mFlat): ?>(Flat <?= htmlspecialchars($mFlat) ?>)<?php endif; ?>
    </div>
    <?php endif; ?>

    <form id="tForm" onsubmit="doSubmit(event)" enctype="multipart/form-data">
      <input type="hidden" id="ft" value="<?= htmlspecialchars($token) ?>">

      <!-- Personal Info -->
      <div class="sec"><i class="fas fa-user" style="margin-right:6px"></i>Personal Information</div>
      <div class="g2">
        <div class="f"><label>Full Name <span class="req">*</span></label>
          <input type="text" id="fn" required placeholder="Your full legal name"></div>
        <div class="f"><label>Mobile <span class="req">*</span></label>
          <input type="tel" id="mob" required placeholder="+91XXXXXXXXXX"></div>
        <div class="f"><label>Email</label>
          <input type="email" id="em" placeholder="your@email.com"></div>
        <div class="f"><label>Alternate Mobile</label>
          <input type="tel" id="amb" placeholder="Optional"></div>
        <div class="f"><label>Gender</label>
          <select id="gen"><option value="">— Select —</option>
            <option>Male</option><option>Female</option><option>Other</option></select></div>
        <div class="f"><label>Date of Birth</label>
          <input type="date" id="dob"></div>
      </div>

      <!-- Property -->
      <div class="sec"><i class="fas fa-home" style="margin-right:6px"></i>Property Details</div>
      <div class="g3">
        <div class="f"><label>Flat / Room <span class="req">*</span></label>
          <input type="text" id="flt" required placeholder="e.g. A-101"></div>
        <div class="f"><label>Block / Wing</label>
          <input type="text" id="blk" placeholder="e.g. A Block"></div>
        <div class="f"><label>Floor</label>
          <input type="text" id="flr" placeholder="e.g. 2nd"></div>
        <div class="f"><label>Plot Number</label>
          <input type="text" id="plt" placeholder="Optional"></div>
        <div class="f"><label>Move-in Date</label>
          <input type="date" id="ocs"></div>
        <div class="f"><label>Move-out Date</label>
          <input type="date" id="oce"></div>
      </div>

      <!-- ID Verification -->
      <div class="sec"><i class="fas fa-id-card" style="margin-right:6px"></i>Identity Verification</div>
      <div class="g2">
        <div class="f"><label>ID Type <?= $docReq ? '<span class="req">*</span>' : '' ?></label>
          <select id="git" <?= $docReq ? 'required' : '' ?>>
            <option value="">— Select —</option>
            <option>Aadhaar</option><option>PAN</option><option>Passport</option>
            <option>Driving License</option><option>Voter ID</option></select></div>
        <div class="f"><label>ID Number</label>
          <input type="text" id="gin" placeholder="Enter ID number"></div>
      </div>
      <div class="f" style="margin-top:13px">
        <label>Upload ID Document <?= $docReq ? '<span class="req">*</span>' : '' ?>
          <span class="hint" style="display:inline">&nbsp;JPG / PNG / PDF / WEBP</span></label>
        <label class="file-wrap"><input type="file" id="gid" name="govt_id_doc"
          accept=".jpg,.jpeg,.png,.pdf,.webp" <?= $docReq ? 'required' : '' ?>>
          <i class="fas fa-upload"></i><span id="flbl">Click to choose file</span></label>
      </div>

      <!-- Permanent Address -->
      <div class="sec"><i class="fas fa-map-marker-alt" style="margin-right:6px"></i>Permanent Address</div>
      <div class="f"><label>Street / Locality</label>
        <textarea id="pad" rows="2" placeholder="Door no., street, landmark…"></textarea></div>
      <div class="g3">
        <div class="f"><label>City</label><input type="text" id="pct" placeholder="City"></div>
        <div class="f"><label>State</label><input type="text" id="pst" placeholder="State"></div>
        <div class="f"><label>Pincode</label><input type="text" id="ppn" placeholder="6-digit"></div>
      </div>

      <!-- Tenant Details -->
      <div class="sec"><i class="fas fa-briefcase" style="margin-right:6px"></i>Tenant Details</div>
      <div class="g2">
        <div class="f"><label>Purpose <span class="req">*</span></label>
          <select id="pur" required><option value="">— Select —</option>
            <option>Rental</option><option>Family Member</option><option>Student</option>
            <option>Employee</option><option>Guest</option><option>Other</option></select></div>
        <div class="f"><label>Occupation</label>
          <input type="text" id="occ" placeholder="e.g. Software Engineer"></div>
        <div class="f"><label>Company / Institute</label>
          <input type="text" id="cmp" placeholder="Employer or college"></div>
        <div class="f"><label>Emergency Contact Name</label>
          <input type="text" id="ecn" placeholder="Full name"></div>
        <div class="f"><label>Emergency Contact No.</label>
          <input type="tel" id="ecm" placeholder="Mobile number"></div>
      </div>
      <div class="f" style="margin-top:13px"><label>Additional Notes</label>
        <textarea id="nts" rows="2" placeholder="Anything the admin should know…"></textarea></div>

      <!-- Portal Account (optional) -->
      <div class="sec"><i class="fas fa-key" style="margin-right:6px"></i>Portal Account <span style="font-size:10px;color:#6b7280;text-transform:none;font-weight:400">(optional — lets you log in and view your tenancy details)</span></div>
      <div class="g2">
        <div class="f"><label>Username</label>
          <input type="text" id="pu" placeholder="Choose a login username" autocomplete="username"></div>
        <div class="f"><label>Password</label>
          <input type="password" id="pp" placeholder="Choose a password" autocomplete="new-password"></div>
      </div>
      <p class="hint" style="margin-top:-6px;margin-bottom:14px"><i class="fas fa-info-circle" style="margin-right:4px;color:#6366f1"></i>Leave blank if you don't want portal access. You can also log in using your mobile number once your account is Active.</p>

      <button type="submit" class="btn" id="sbtn">
        <i class="fas fa-paper-plane" style="margin-right:8px"></i>Submit Registration
      </button>
    </form>

    <div class="suc" id="sucScreen">
      <i class="fas fa-check-circle"></i>
      <h2>Registration Submitted!</h2>
      <p id="sucMsg">Your registration has been submitted. The admin will review and approve shortly.</p>
    </div>
  </div>
  <?php endif; ?>
</div>
<script>
document.getElementById('gid')?.addEventListener('change',function(){
  document.getElementById('flbl').textContent=this.files[0]?.name||'Click to choose file';
});
const v=id=>document.getElementById(id)?.value?.trim()||'';
async function doSubmit(e){
  e.preventDefault();
  const err=document.getElementById('aErr'), btn=document.getElementById('sbtn');
  err.style.display='none';
  btn.disabled=true;
  btn.innerHTML='<i class="fas fa-spinner fa-spin" style="margin-right:8px"></i>Submitting…';
  const fd=new FormData();
  const map={action:'self_register',token:v('ft'),full_name:v('fn'),mobile:v('mob'),
    email:v('em'),alt_mobile:v('amb'),gender:v('gen'),dob:v('dob'),
    flat_number:v('flt'),block_wing:v('blk'),floor_number:v('flr'),plot_number:v('plt'),
    occupancy_start:v('ocs'),occupancy_end:v('oce'),govt_id_type:v('git'),
    govt_id_number:v('gin'),permanent_address:v('pad'),perm_city:v('pct'),
    perm_state:v('pst'),perm_pincode:v('ppn'),purpose:v('pur'),occupation:v('occ'),
    company_name:v('cmp'),emergency_contact_name:v('ecn'),emergency_contact_number:v('ecm'),notes:v('nts'),
    portal_username:v('pu'),portal_password:v('pp')};
  for(const[k,val] of Object.entries(map)) fd.append(k,val);
  const fi=document.getElementById('gid');
  if(fi?.files[0]) fd.append('govt_id_doc',fi.files[0]);
  try{
    const r=await fetch('./tenant_api.php',{method:'POST',body:fd});
    const d=await r.json();
    if(d.success){
      document.getElementById('formWrap').style.display='none';
      document.getElementById('sucScreen').style.display='block';
      document.getElementById('sucMsg').textContent=d.message;
    }else{
      err.textContent=d.error||'Submission failed';
      err.style.display='block';
      btn.disabled=false;
      btn.innerHTML='<i class="fas fa-paper-plane" style="margin-right:8px"></i>Submit Registration';
    }
  }catch(ex){
    err.textContent='Network error: '+ex.message;
    err.style.display='block';
    btn.disabled=false;
    btn.innerHTML='<i class="fas fa-paper-plane" style="margin-right:8px"></i>Submit Registration';
  }
}
</script>
</body>
</html>
