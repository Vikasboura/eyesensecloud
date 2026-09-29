<?php
/**
 * Client Plot Registration — EyeSense
 * - SA: client_id locked to session, can fill any plot for their society
 * - Member: client_id locked, personal data auto-filled, fills own plot
 * - Direct (no session): client_id from URL or manual entry
 */

require_once __DIR__ . '/../db_setup.php';
require_once __DIR__ . '/../portal_config.php';

// ── Session & Auth ──────────────────────────────────────────────
$isPortal   = false;
$session    = [];
$isSA       = false;
$autoFill   = [];

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['portal_logged_in'])) {
    require_once __DIR__ . '/../portal_auth.php';
    $isPortal = true;
    $session  = getPortalSession();
    $isSA     = !empty($session['is_superadmin']);

    // Auto-fill from employees table
    try {
        $pdo = get_license_db();
        $empId = $_SESSION['portal_employee_id'] ?? '';
        $emp = $pdo->prepare("SELECT * FROM employees WHERE client_id=? AND employee_id=? AND is_deleted=0 LIMIT 1");
        $emp->execute([$session['client_id'], $empId]);
        $empRow = $emp->fetch() ?: [];

        $autoFill = [
          'client_id' => $session['client_id'],
          'name'      => $empRow['full_name']    ?? '',
          'email'     => $empRow['email']         ?? '',
          'phone'     => $empRow['phone']          ?? '',
          'plotno'    => $empRow['plotno']         ?? '',
          'address'   => $empRow['plot_address']  ?? '',
          'latitude'  => $empRow['latitude']       ?? '',
          'longitude' => $empRow['longitude']      ?? '',
          'alt_phone' => $empRow['alt_phone']      ?? '',
          'notes'     => $empRow['plot_notes']     ?? '',
          'status'    => $empRow['plot_status']    ?? '',
      ];
    } catch (Throwable $e) { $autoFill = []; }
} else {
    // Direct access — client_id from URL param
    $autoFill['client_id'] = trim($_GET['client_id'] ?? '');
}

// ── Ensure migration columns exist ────────────────────────────────
require_once __DIR__ . '/shared_migration.php';
try {
    $db = get_license_db();
} catch (Throwable $e) { $db = null; }


// ── Handle Form Submit ──────────────────────────────────────────
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    $clientid  = $isPortal ? $session['client_id'] : trim($_POST['clientid'] ?? '');
    $plotno    = trim($_POST['plotno']    ?? '');
    $name      = trim($_POST['name']     ?? '');
    $email     = trim($_POST['email']    ?? '');
    $phone     = trim($_POST['phone']    ?? '');
    $alt_phone = trim($_POST['alt_phone'] ?? '');
    $address   = trim($_POST['address']  ?? '');
    $lat       = trim($_POST['latitude'] ?? '');
    $lng       = trim($_POST['longitude'] ?? '');
    $status    = trim($_POST['status']   ?? '');
    $notes     = trim($_POST['notes']    ?? '');
    $filledBy  = $isPortal ? ($session['username'] ?? 'portal') : 'direct';


    $coordError = null;
    if ($lat !== '' || $lng !== '') {
        if (!is_numeric($lat) || !is_numeric($lng)) { $coordError = "Coordinates must be numeric."; }
        elseif ((float)$lat < -90 || (float)$lat > 90)   { $coordError = "Latitude must be between -90 and 90."; }
        elseif ((float)$lng < -180 || (float)$lng > 180) { $coordError = "Longitude must be between -180 and 180."; }
    }

    // Build maplink from lat/lng or plotno fallback
    if ($lat && $lng) {
        $maplink = "https://www.google.com/maps?q={$lat},{$lng}";
    } elseif ($plotno) {
        $maplink = "https://www.google.com/maps/search/?api=1&query=" . urlencode($plotno . ' Entelipro Eyesense');
    } else { $maplink = ''; }

    // Normalize plotno: any format → NUMBER-LETTER (e.g. A136 → 136-A)
    if ($plotno) {
        preg_match('/(\d+)/', $plotno, $nm);
        preg_match('/([A-Za-z])/', $plotno, $lm);
        if (!empty($nm[1]) && !empty($lm[1])) {
            $plotno = $nm[1] . '-' . strtoupper($lm[1]);
        }
    }

    if ($coordError) {
        $message = 'error:' . $coordError;
    } else {
        try {
            $empId = $isPortal ? ($_SESSION['portal_employee_id'] ?? '') : '';

            if ($isPortal && $empId) {
                // Portal: update the logged-in employee's own row
                $db->prepare("UPDATE employees SET plotno=?,plot_address=?,latitude=?,longitude=?,
                  plot_maplink=?,alt_phone=?,plot_notes=?,plot_status=?,plot_filled_by=?,
                  updated_at=NOW()
                  WHERE client_id=? AND employee_id=?")
                ->execute([$plotno,$address,$lat,$lng,$maplink,$alt_phone,$notes,$status,
                  $filledBy,
                  $clientid,$empId]);
                $message = "success:Plot data saved successfully!";
            } else {
                // Direct/admin access: find employee by client_id or insert note
                $chk = $db->prepare("SELECT employee_id FROM employees WHERE client_id=? AND is_deleted=0 ORDER BY id LIMIT 1");
                $chk->execute([$clientid]);
                $foundEmp = $chk->fetchColumn();
                if ($foundEmp) {
                    $db->prepare("UPDATE employees SET plotno=?,plot_address=?,latitude=?,longitude=?,
                        plot_maplink=?,alt_phone=?,plot_notes=?,plot_status=?,plot_filled_by=?,
                        updated_at=NOW()
                        WHERE client_id=? AND employee_id=?")
                      ->execute([$plotno,$address,$lat,$lng,$maplink,$alt_phone,$notes,$status,
                        $filledBy,
                        $clientid,$foundEmp]);
                    $message = "success:Plot data updated for client!";
                } else {
                    $message = "error:No employee record found for client ID: {$clientid}";
                }
            }
            $autoFill['plotno']    = $plotno;
            $autoFill['address']   = $address;
            $autoFill['latitude']  = $lat;
            $autoFill['longitude'] = $lng;
            $autoFill['alt_phone'] = $alt_phone;
            $autoFill['notes']     = $notes;
            $autoFill['status']    = $status;
        } catch (Throwable $e) {
            $message = "error:" . $e->getMessage();
        }
    }
}

$isSuccess = str_starts_with($message, 'success:');
$msgText   = $message ? substr($message, strpos($message,':')+1) : '';

function af(string $key, array $fill): string {
    return htmlspecialchars($fill[$key] ?? '');
}

// Compute the initial combined string value to render in the new field
$combinedCoords = '';
if (af('latitude', $autoFill) && af('longitude', $autoFill)) {
    $combinedCoords = af('latitude', $autoFill) . ',' . af('longitude', $autoFill);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Client Plot Registration — EyeSense</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Inter',sans-serif}
body{background:linear-gradient(135deg,#f0f4f8,#e9eef4);min-height:100vh;padding:2rem 1rem;display:flex;align-items:flex-start;justify-content:center}
.card{width:100%;max-width:960px;background:rgba(255,255,255,0.98);backdrop-filter:blur(14px);border-radius:24px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.1),0 0 0 1px rgba(0,224,255,0.1);overflow:hidden}
.hd{background:linear-gradient(115deg,#001e2a,#0a2f3d);padding:1.6rem 2rem;text-align:center;border-bottom:2px solid #00e0ff}
.hd h1{font-size:1.8rem;font-weight:800;background:linear-gradient(120deg,#fff,#a8eaff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:.3rem}
.hd p{color:#bde3ef;font-size:.8rem}
.badge-row{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:.8rem}
.badge{display:inline-flex;align-items:center;gap:6px;background:rgba(0,224,255,0.12);border:1px solid rgba(0,224,255,0.3);padding:.3rem 1rem;border-radius:40px;font-size:.7rem;font-weight:700;color:#b5f0ff;letter-spacing:.5px}
.body{padding:1.8rem 2rem}
.msg{padding:.9rem 1.2rem;border-radius:12px;margin-bottom:1.2rem;font-size:.9rem;font-weight:500}
.msg.ok{background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);color:#34d399}
.msg.err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171}
.section-label{font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#007f9e;margin:1.4rem 0 .7rem;display:flex;align-items:center;gap:8px}
.section-label::after{content:'';flex:1;height:1px;background:rgba(0,127,158,.15)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.full{grid-column:span 2}
.field{position:relative}
.field label{display:block;font-size:.9rem;font-weight:700;color:#0b3b44;margin-bottom:.4rem}
.field input,.field textarea,.field select{width:100%;padding:11px 40px 11px 14px;border-radius:12px;border:2px solid #ddecf2;background:#fff;color:#022b36;font-size:1rem;transition:.2s;backdrop-filter:none}
.field input:focus,.field textarea:focus{outline:none;border-color:#00a6c4;box-shadow:0 0 0 3px rgba(0,166,196,0.2);background:#fff}
.field input[readonly]{opacity:.7;cursor:not-allowed;background:#f1f5f9;border-color:#cbd5e1}
.field i.ic{position:absolute;right:13px;top:38px;color:#7ac4d4;font-size:1rem;pointer-events:none}
::placeholder{color:#8ab8c4;font-weight:400}
.loc-row{display:flex;gap:12px;align-items:flex-end;width:100%;flex-wrap:nowrap}
.loc-row .field{flex:1}
.gps-btn{padding:11px 16px;border-radius:12px;border:1px solid rgba(0,224,255,.3);background:rgba(0,224,255,.1);color:#00e0ff;font-size:.78rem;font-weight:700;cursor:pointer;white-space:nowrap;transition:.2s;display:flex;align-items:center;gap:6px;min-height:44px}
.gps-btn:hover{background:rgba(0,224,255,.2)}
.gps-status{font-size:.7rem;color:#7ac4d4;margin-top:.3rem;min-height:1rem}
.map-preview{background:rgba(209, 213, 219, 0.5);border-radius:12px;padding:.7rem 1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:.5rem}
.map-link{color:#7dd4f0;font-size:.75rem;word-break:break-all;text-decoration:none;border-bottom:1px dashed #00e0ff}
.copy-btn{background:rgba(255,255,255,.1);border:none;padding:5px 12px;border-radius:30px;color:#fff;font-size:.7rem;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:5px}
.copy-btn:hover{background:#00b4d8;color:#001e2a}
.plot-preview{background:rgba(0,224,255,.1);border-radius:12px;padding:.6rem 1rem;margin-top:.4rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:.8rem;border-left:3px solid #00e0ff}
.plot-std{font-family:monospace;font-weight:800;color:#00e0ff;background:#001e2a;padding:3px 12px;border-radius:30px}
.submit-btn{grid-column:span 2;padding:14px;border:none;border-radius:40px;background:linear-gradient(95deg,#00b4d8,#0077b6);color:#fff;font-weight:800;font-size:1rem;cursor:pointer;transition:.25s;display:flex;align-items:center;justify-content:center;gap:10px;margin-top:8px;box-shadow:0 5px 15px rgba(209, 213, 219, 0.5)}
.submit-btn:hover{transform:translateY(-2px);background:linear-gradient(95deg,#0096c0,#005f8c)}
.back-link{text-align:center;margin-top:1rem}
.back-link a{color:#7ac4d4;font-size:.8rem;text-decoration:none}
.back-link a:hover{color:#00e0ff}
footer{background:#eaf1f5;padding:.9rem 2rem;text-align:center;border-top:1px solid #d2e3ea;color:#3e6f7e;font-size:.75rem}
@media(max-width:768px){.grid{grid-template-columns:1fr}.full,.submit-btn{grid-column:span 1}.body{padding:1.2rem 1rem}.hd{padding:1.2rem}.loc-row{flex-direction:column;align-items:stretch}.gps-btn{justify-content:center}}
</style>
</head>
<body>
<div class="card">
  <div class="hd">
    <h1><i class="fas fa-map-pin"></i> Client Plot Registration</h1>
    <p>Register location & details for an EyeSense client plot</p>
    <div class="badge-row">
      <?php if ($isPortal): ?>
        <span class="badge"><i class="fas fa-id-badge"></i> <?= htmlspecialchars($session['client_id']) ?></span>
        <span class="badge"><i class="fas fa-user"></i> <?= htmlspecialchars($session['username']) ?></span>
        <span class="badge" style="border-color:rgba(99,102,241,.4);color:#a5b4fc;background:rgba(99,102,241,.12)">
          <i class="fas fa-shield-halved"></i> <?= $isSA ? 'Super Admin' : 'Member' ?>
        </span>
      <?php else: ?>
        <span class="badge"><i class="fas fa-plug"></i> Direct Access</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="body">
    <?php if ($msgText): ?>
    <div class="msg <?= $isSuccess ? 'ok' : 'err' ?>">
      <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
      <?= htmlspecialchars($msgText) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="clientForm">
      <div class="section-label"><i class="fas fa-id-card"></i> Identity</div>
      <div class="grid">
        <div class="field">
          <label>Client ID <?= $isPortal ? '(locked to your account)' : '' ?></label>
          <input type="text" name="clientid" id="clientid"
            value="<?= af('client_id', $autoFill) ?>"
            <?= $isPortal ? 'readonly' : '' ?>
            placeholder="e.g. IND-2026-ABC12" required>
          <i class="ic fas fa-fingerprint"></i>
        </div>
        <div class="field">
          <label>Full Name</label>
          <input type="text" name="name" id="name"
            value="<?= af('name', $autoFill) ?>"
            placeholder="Resident / Owner name" required>
          <i class="ic fas fa-user"></i>
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?= af('email', $autoFill) ?>"
            placeholder="email@example.com">
          <i class="ic fas fa-envelope"></i>
        </div>
        <div class="field">
          <label>Phone</label>
          <input type="text" name="phone" value="<?= af('phone', $autoFill) ?>"
            placeholder="+91XXXXXXXXXX">
          <i class="ic fas fa-phone"></i>
        </div>
        <div class="field">
          <label>Alternate Phone</label>
          <input type="text" name="alt_phone" value="<?= af('alt_phone', $autoFill) ?>"
            placeholder="Optional second number">
          <i class="ic fas fa-phone-flip"></i>
        </div>
        <div class="field">
          <label>Plot Status</label>
          <select name="status">
            <?php foreach (['','owner','tenant','vacant','commercial'] as $s): ?>
            <option value="<?= $s ?>" <?= af('status',$autoFill)===$s?'selected':'' ?>><?= $s ? ucfirst($s) : '— Select —' ?></option>
            <?php endforeach; ?>
          </select>
          <i class="ic fas fa-info-circle"></i>
        </div>
      </div>

      <div class="section-label"><i class="fas fa-map"></i> Plot & Location</div>
      <div class="grid">
        <div class="field">
          <label>Plot Number <span style="color:#00e0ff;font-size:.65rem">(any format — auto normalized)</span></label>
          <input type="text" name="plotno" id="plotno"
            value="<?= af('plotno',$autoFill) ?>"
            placeholder="e.g. A136, 136A, 136-A, A/136" required>
          <i class="ic fas fa-map"></i>
          <div id="plotPreview"></div>
        </div>
        <div class="field full">
          <label>Address</label>
          <textarea name="address" rows="2" placeholder="Full address of the plot"><?= af('address',$autoFill) ?></textarea>
          <i class="ic fas fa-home" style="top:36px"></i>
        </div>

        <div class="field full">
          <label>GPS Location <span style="color:#7ac4d4;font-size:.65rem">(Use any column method to change coordinates)</span></label>
          <div class="loc-row">
            <div class="field">
              <label style="font-size:.65rem;color:#7ac4d4;">Latitude</label>
              <input type="text" name="latitude" id="latitude"
                value="<?= af('latitude',$autoFill) ?>"
                placeholder="e.g. 22.7196">
            </div>
            <div class="field">
              <label style="font-size:.65rem;color:#7ac4d4;">Longitude</label>
              <input type="text" name="longitude" id="longitude"
                value="<?= af('longitude',$autoFill) ?>"
                placeholder="e.g. 75.8577">
            </div>
            <div class="field">
              <label style="font-size:.65rem;color:#7ac4d4;">Combined (Lat,Lng)</label>
              <input type="text" id="combined_gps" 
                value="<?= $combinedCoords ?>" 
                placeholder="e.g. 22.7196,75.8577">
            </div>
            <button type="button" class="gps-btn" onclick="detectGPS()">
              <i class="fas fa-location-crosshairs"></i> Use GPS
            </button>
            <button type="button" class="gps-btn" onclick="openManualLocation()" style="border-color:rgba(0,180,216,.5);background:rgba(0,180,216,.12);">
              <i class="fas fa-map-pin"></i> Set Manually
            </button>
          </div>

          
          <div class="gps-status" id="gpsStatus">
            <?php if (af('latitude',$autoFill) && af('longitude',$autoFill)): ?>
              <i class="fas fa-check-circle" style="color:#34d399"></i>
              Location saved: <?= af('latitude',$autoFill) ?>, <?= af('longitude',$autoFill) ?>
            <?php endif; ?>
          </div>
          
          <div id="mapPreviewBox" <?= (af('latitude',$autoFill) && af('longitude',$autoFill)) ? '' : 'style="display:none"' ?>>
            <div class="map-preview">
              <a href="<?= ($autoFill['latitude'] && $autoFill['longitude'])
                ? 'https://www.google.com/maps?q='.$autoFill['latitude'].','.$autoFill['longitude']
                : '#' ?>"

                target="_blank" class="map-link" id="mapLinkEl">
                <i class="fas fa-external-link-alt"></i>
                <?= ($autoFill['latitude'] && $autoFill['longitude'])
                  ? 'https://www.google.com/maps?q='.$autoFill['latitude'].','.$autoFill['longitude']
                  : '#' 
                  ?>
              </a>
              <button type="button" class="copy-btn" onclick="copyMapLink()">
                <i class="fas fa-copy"></i> Copy Link
              </button>
            </div>
          </div>
        </div>

        <div class="field full">
          <label>Notes</label>
          <textarea name="notes" rows="2" placeholder="Any additional info about this plot"><?= af('notes',$autoFill) ?></textarea>
        </div>

        <button type="submit" class="submit-btn">
          <i class="fas fa-cloud-upload-alt"></i>
          <?= $isPortal ? 'Save My Plot Data' : 'Submit Client Data' ?>
        </button>
      </div>
    </form>

    <?php if ($isPortal): ?>
    <div class="back-link">
      <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <?php endif; ?>
  </div>

  <div id="manualLocModal" style="display:none;position:fixed;inset:0;z-index:999;background:rgba(0,10,15,.7);align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#001e2a;border:1px solid rgba(0,224,255,.3);border-radius:20px;width:92%;max-width:1200px;height:97vh;overflow:hidden;display:flex;flex-direction:column;">
      <div style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.4rem;border-bottom:1px solid rgba(0,224,255,.15);">
        <h3 style="color:#fff;font-size:.95rem;"><i class="fas fa-map-pin" style="color:#00e0ff;margin-right:6px;"></i>Set Exact Location</h3>
        <span onclick="closeManualLocation()" style="color:#7ac4d4;cursor:pointer;font-size:1.2rem;">&times;</span>
      </div>
      <div style="padding:1.4rem;flex:1;display:flex;flex-direction:column;min-height:0;overflow-y:auto;">
        <p style="color:#7ac4d4;font-size:.75rem;margin-bottom:.8rem;">Click the map or drag the pin, or type coordinates directly.</p>
        <div class="grid" style="margin-bottom:.6rem;">
          <div class="field">
            <label style="color:#bde3ef;font-size:.7rem;margin-bottom:.2rem;">Latitude</label>
            <input type="text" id="manualLatInput" placeholder="22.719600" style="padding:7px 10px;font-size:.82rem;border-radius:8px;">
          </div>
          <div class="field">
            <label style="color:#bde3ef;font-size:.7rem;margin-bottom:.2rem;">Longitude</label>
            <input type="text" id="manualLngInput" placeholder="75.857700" style="padding:7px 10px;font-size:.82rem;border-radius:8px;">
          </div>
        </div>
        <div id="manualMap" style="width:100%;flex:1;min-height:340px;border-radius:12px;border:1px solid rgba(0,224,255,.2);margin-bottom:.8rem;"></div>
        <div id="manualLocError" style="color:#f87171;font-size:.75rem;margin-bottom:.6rem;display:none;"></div>
        <div style="display:flex;gap:10px;">
          <button type="button" class="gps-btn" style="flex:1;justify-content:center;background:rgba(16,185,129,.15);border-color:rgba(16,185,129,.4);color:#34d399;" onclick="confirmManualLocation()">
            <i class="fas fa-check"></i> Confirm Location
          </button>
          <button type="button" class="gps-btn" onclick="closeManualLocation()">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <footer>
    <i class="fas fa-chart-line"></i> Entelipro Eyesense AI — Client Plot Registration
    <br>© 2026 <strong>Entelipro AI Technologies Pvt Ltd</strong> | All Rights Reserved
  </footer>
</div>

<script>
// ── Plot normalizer ─────────────────────────────────────────────
function normalizePlot(raw) {
  if (!raw) return null;
  const nm = raw.match(/\d+/), lm = raw.match(/[A-Za-z]/);
  if (nm && lm) return nm[0] + '-' + lm[0].toUpperCase();
  if (nm) return nm[0];
  if (lm) return lm[0].toUpperCase();
  return null;
}

const plotInput   = document.getElementById('plotno');
const plotPreview = document.getElementById('plotPreview');

function updatePlotPreview() {
  const std = normalizePlot(plotInput.value);
  if (!std) { plotPreview.innerHTML = ''; return; }
  plotPreview.innerHTML = `<div class="plot-preview">
    <span><i class="fas fa-check-circle" style="color:#00e0ff"></i> Normalized:</span>
    <span class="plot-std">${std}</span></div>`;
}
if (plotInput) {
  plotInput.addEventListener('input', updatePlotPreview);
  updatePlotPreview();
}

// ── Two-Way Synchronizer UI Components ───────────────────────────
const latInput      = document.getElementById('latitude');
const lngInput      = document.getElementById('longitude');
const combinedInput = document.getElementById('combined_gps');


// ── Manual Location ─────────────────────────────────────────────
function isValidCoords(lat, lng) {
  return Number.isFinite(lat) && Number.isFinite(lng) &&
         lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
}

let manualMap = null, manualMarker = null;

function openManualLocation() {
  document.getElementById('manualLocModal').style.display = 'flex';

  const existingLat = parseFloat(latInput.value);
  const existingLng = parseFloat(lngInput.value);
  const startLat = isValidCoords(existingLat, existingLng) ? existingLat : 22.7196;
  const startLng = isValidCoords(existingLat, existingLng) ? existingLng : 75.8577;

  document.getElementById('manualLatInput').value = startLat.toFixed(6);
  document.getElementById('manualLngInput').value = startLng.toFixed(6);

  setTimeout(() => {
    if (!manualMap) {
      manualMap = L.map('manualMap').setView([startLat, startLng], 18);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(manualMap);
      manualMarker = L.marker([startLat, startLng], { draggable: true }).addTo(manualMap);

      manualMarker.on('dragend', () => {
        const p = manualMarker.getLatLng();
        document.getElementById('manualLatInput').value = p.lat.toFixed(6);
        document.getElementById('manualLngInput').value = p.lng.toFixed(6);
      });
      manualMap.on('click', (e) => {
        manualMarker.setLatLng(e.latlng);
        document.getElementById('manualLatInput').value = e.latlng.lat.toFixed(6);
        document.getElementById('manualLngInput').value = e.latlng.lng.toFixed(6);
      });
    } else {
      manualMap.setView([startLat, startLng], 18);
      manualMarker.setLatLng([startLat, startLng]);
    }
    manualMap.invalidateSize();
  }, 100);
}

function closeManualLocation() {
  document.getElementById('manualLocModal').style.display = 'none';
}

function confirmManualLocation() {
  const lat = parseFloat(document.getElementById('manualLatInput').value);
  const lng = parseFloat(document.getElementById('manualLngInput').value);
  const errBox = document.getElementById('manualLocError');

  if (!isValidCoords(lat, lng)) {
    errBox.textContent = 'Enter a valid latitude (-90 to 90) and longitude (-180 to 180).';
    errBox.style.display = 'block';
    return;
  }
  errBox.style.display = 'none';

  latInput.value = lat.toFixed(6);
  lngInput.value = lng.toFixed(6);
  combinedInput.value = `${lat.toFixed(6)},${lng.toFixed(6)}`;

  const link = `https://www.google.com/maps?q=${lat.toFixed(6)},${lng.toFixed(6)}`;
  document.getElementById('mapLinkEl').href = link;
  document.getElementById('mapLinkEl').textContent = link;
  document.getElementById('mapPreviewBox').style.display = '';

  document.getElementById('gpsStatus').innerHTML =
    `<i class="fas fa-map-pin" style="color:#00e0ff"></i> Manually set: ${lat.toFixed(6)}, ${lng.toFixed(6)} — click Save to confirm`;

  closeManualLocation();
}


// 1. If combined changes -> Split into Latitude and Longitude
combinedInput.addEventListener('input', function() {
  const value = this.value.trim();
  if (value.includes(',')) {
    const parts = value.split(',');
    latInput.value = parts[0].trim();
    lngInput.value = parts[1].trim();
  }
});

// 2. If separate Latitude or Longitude changes -> Update the combined text field
function syncToCombined() {
  const latVal = latInput.value.trim();
  const lngVal = lngInput.value.trim();
  if(latVal || lngVal) {
    combinedInput.value = `${latVal},${lngVal}`;
  } else {
    combinedInput.value = '';
  }
}
latInput.addEventListener('input', syncToCombined);
lngInput.addEventListener('input', syncToCombined);


// ── GPS Detection ───────────────────────────────────────────────
function detectGPS() {
  const status = document.getElementById('gpsStatus');
  const box    = document.getElementById('mapPreviewBox');
  if (!navigator.geolocation) {
    status.innerHTML = '<i class="fas fa-times-circle" style="color:#f87171"></i> Geolocation not supported by this browser.';
    return;
  }
  status.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:#00e0ff"></i> Detecting location…';
  navigator.geolocation.getCurrentPosition(
    function(pos) {
      const lat = pos.coords.latitude.toFixed(6);
      const lng = pos.coords.longitude.toFixed(6);
      
      // Update individual fields
      latInput.value = lat;
      lngInput.value = lng;
      // Update combined display field
      combinedInput.value = `${lat},${lng}`;
      
      status.innerHTML = `<i class="fas fa-check-circle" style="color:#34d399"></i> Location detected: ${lat}, ${lng} (accuracy: ${Math.round(pos.coords.accuracy)}m)`;
      const link = `https://www.google.com/maps?q=${lat},${lng}`;
      document.getElementById('mapLinkEl').href        = link;
      document.getElementById('mapLinkEl').textContent = link;
      box.style.display = '';
    },
    function(err) {
      const msgs = {1:'Permission denied',2:'Position unavailable',3:'Request timed out'};
      status.innerHTML = `<i class="fas fa-exclamation-circle" style="color:#f87171"></i> ${msgs[err.code]||'Error'}`;
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
}

// ── Copy map link ───────────────────────────────────────────────
function copyMapLink() {
  const link = document.getElementById('mapLinkEl')?.href;
  if (!link || link === '#') return;
  navigator.clipboard.writeText(link).then(() => {
    const btn = event.target.closest('.copy-btn');
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> Copy Link'; }, 2000);
  }).catch(() => prompt('Copy this link:', link));
}
</script>
</body>
</html>
