<?php
/**
 * Visitor Plot Locator — EyeSense
 * Lets anyone look up a plot number and get the map link / location.
 * Uses eyesense_licenses DB (employees table) via get_license_db().
 */

require_once __DIR__ . '/../db_setup.php';
require_once __DIR__ . '/../portal_config.php';

$client_id   = trim($_GET['client_id'] ?? '');
$hashed_cid  = trim($_GET['cid'] ?? '');
$plotno      = trim($_GET['plotno'] ?? '');
$resultData  = '';
$suggestions = [];
$locality_name = 'Enter Locality';

// ── Migrate: ensure plot columns exist in employees ────────────
require_once __DIR__ . '/shared_migration.php';
try { ensure_plot_columns(get_license_db()); } catch (Throwable $e) {}

try {
    $db = get_license_db();

    // Public share-link resolution: intentionally bypasses SocietyContextHolder for unauthenticated external visitors (guests, delivery, cabs)
    if ($hashed_cid !== '') {
        $stmt = $db->query("SELECT DISTINCT client_id FROM employees WHERE client_id IS NOT NULL AND client_id != ''");
        $clientIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($clientIds as $cid) {
            if ($cid && md5($cid) === $hashed_cid) {
                $client_id = $cid;
                break;
            }
        }
    }

    // Authenticated portal context resolution: if no public link param is supplied, resolve active society via SocietyContextHolder
    if ($client_id === '') {
        require_once __DIR__ . '/../society_context_holder.php';
        SocietyContextHolder::initFromRequest($db);
        $client_id = SocietyContextHolder::getLegacyClientId($db) ?? '';
    }

    // Lookup Locality Name from maintenance_settings
    if ($client_id !== '') {
        $mStmt = $db->prepare("SELECT society_name, landmark, city FROM maintenance_settings WHERE client_id = ? LIMIT 1");
        $mStmt->execute([$client_id]);
        $mSettings = $mStmt->fetch();
        if ($mSettings) {
            $parts = [];
            if (!empty($mSettings['society_name'])) $parts[] = $mSettings['society_name'];
            if (!empty($mSettings['landmark'])) $parts[] = $mSettings['landmark'];
            if (!empty($mSettings['city'])) $parts[] = $mSettings['city'];
            if (!empty($parts)) {
                $locality_name = implode(', ', $parts);
            }
        } else {
            $locality_name = $client_id;
        }

        // Autocomplete suggestions filtered by client_id
        $stmt = $db->prepare("SELECT DISTINCT plotno FROM employees WHERE client_id = ? AND plotno IS NOT NULL AND plotno != '' ORDER BY plotno");
        $stmt->execute([$client_id]);
        $suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if ($plotno !== '') {
            // Normalize: any format → NUMBER-LETTER
            preg_match('/(\d+)/', $plotno, $nm);
            preg_match('/([A-Za-z])/', $plotno, $lm);
            $normalized = (!empty($nm[1]) && !empty($lm[1]))
                ? $nm[1] . '-' . strtoupper($lm[1])
                : $plotno;

            // Try normalized then raw
            $stmt = $db->prepare("SELECT * FROM employees WHERE plotno = ? AND client_id = ? AND is_deleted = 0 LIMIT 1");
            $stmt->execute([$normalized, $client_id]);
            $row = $stmt->fetch();
            if (!$row) {
                $stmt->execute([$plotno, $client_id]);
                $row = $stmt->fetch();
            }

            if ($row) {
                $lat      = htmlspecialchars($row['latitude'] ?? '');
                $lng      = htmlspecialchars($row['longitude'] ?? '');
                $maplink  = '';
                $embedSrc = '';

                if ($lat && $lng) {
                    $maplink  = "https://www.google.com/maps?q={$lat},{$lng}";
                    $embedSrc = "https://www.google.com/maps/embed/v1/place?key=&q={$lat},{$lng}";
                    // Use standard embed as fallback
                    $embedSrc = "https://www.google.com/maps?q={$lat},{$lng}&output=embed";
                } elseif (!empty($row['plot_maplink'])) {
                    $maplink  = htmlspecialchars($row['plot_maplink']);
                    $embedSrc = $maplink . (str_contains($maplink, '?') ? '&' : '?') . 'output=embed';
                }

                $name    = htmlspecialchars(trim($row['full_name'] ?? ''));
                $addr    = htmlspecialchars($row['plot_address'] ?? '');
                $plotStd = htmlspecialchars($row['plotno']);
                $phone   = htmlspecialchars($row['phone'] ?? '');
                $status  = htmlspecialchars($row['plot_status'] ?? '');

                $resultData = "
                <div class='result-panel'>
                    <div class='result-header'>
                        <i class='fas fa-map-marker-alt'></i> Plot {$plotStd} — Found
                    </div>
                    <div class='client-info'>
                        " . ($name   ? "<div class='info-row'><i class='fas fa-user'></i><span>{$name}</span></div>"      : '') . "
                        " . ($addr   ? "<div class='info-row'><i class='fas fa-home'></i><span>{$addr}</span></div>"      : '') . "
                        " . ($phone  ? "<div class='info-row'><i class='fas fa-phone'></i><span>{$phone}</span></div>"    : '') . "
                        " . ($status ? "<div class='info-row'><i class='fas fa-info-circle'></i><span>" . ucfirst($status) . "</span></div>" : '') . "
                        " . ($lat && $lng ? "<div class='info-row'><i class='fas fa-location-dot'></i><span>{$lat}, {$lng}</span></div>" : '') . "
                    </div>
                    " . ($maplink ? "
                    <div class='map-link-wrapper'>
                        <a href='{$maplink}' target='_blank' class='map-link'>
                            <i class='fas fa-external-link-alt'></i> Open in Google Maps
                        </a>
                        <button class='copy-map' onclick='copyToClipboard(`{$maplink}`)'>
                            <i class='fas fa-copy'></i> Copy Link
                        </button>
                    </div>
                    <div class='map-embed'>
                        <iframe src='{$embedSrc}' loading='lazy'></iframe>
                    </div>" : "<p class='info-msg'><i class='fas fa-info-circle'></i> No GPS location saved for this plot yet.</p>") . "
                </div>";
            } else {
                $resultData = "<div class='error-msg'><i class='fas fa-times-circle'></i> No data found for plot <strong>" . htmlspecialchars($plotno) . "</strong> in locality <strong>" . htmlspecialchars($client_id) . "</strong></div>";
            }
        }
    } else {
        $resultData = "<div class='info-msg' style='background:#fffbeb;border:1px solid #fde68a;color:#b45309;padding:1.25rem;border-radius:1.5rem;font-weight:600;display:flex;align-items:center;gap:12px;box-shadow:0 4px 12px rgba(180,83,9,0.05);'><i class='fas fa-exclamation-triangle' style='font-size:1.5rem;color:#d97706;'></i> Please enter a valid Locality / Client ID to search for plots.</div>";
    }
} catch (Throwable $e) {
    $resultData = "<div class='error-msg'><i class='fas fa-database'></i> Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Eyesense AI | Smart Plot Locator</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Inter',sans-serif}
body{background:linear-gradient(145deg,#f0f4f8,#e9eef4);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1.5rem}
.glass-card{max-width:950px;width:100%;background:rgba(255,255,255,0.98);border-radius:2.5rem;box-shadow:0 30px 50px -20px rgba(0,0,0,0.25),0 0 0 1px rgba(0,160,180,0.1);overflow:hidden}
.brand-header{background:linear-gradient(135deg,#001e2a,#0a3342);padding:2rem;text-align:center;border-bottom:3px solid #00e0ff}
.society-name{display:inline-flex;align-items:center;gap:8px;background:rgba(0,224,255,0.15);backdrop-filter:blur(4px);padding:.35rem 1.3rem;border-radius:60px;font-size:.8rem;font-weight:700;letter-spacing:.5px;color:#bbf5ff;margin-bottom:1rem;border:1px solid rgba(0,224,255,0.4)}
.product-title{font-size:2.5rem;font-weight:800;background:linear-gradient(120deg,#fff,#b8f2ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:.5rem}
.ai-tag{color:#b2dfee;font-size:.9rem;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap}
.content-core{padding:2rem}
.info-badge{background:#eef6fa;padding:1rem 1.4rem;border-radius:1.5rem;display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:2rem;border:1px solid #cde5ef}
.info-badge i{font-size:2rem;color:#007f9e}
.info-badge p{flex:1;font-weight:500;color:#104c5c;font-size:.9rem;line-height:1.4}
.form-container{margin-bottom:1.5rem}
label{display:flex;align-items:center;gap:10px;font-weight:700;color:#0b3b44;margin-bottom:.75rem;font-size:1rem}
.search-wrapper{position:relative}
.input-group{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
#clientIdInput{width:100%;padding:1rem 1.2rem;font-size:1rem;border:2px solid #ddecf2;border-radius:2rem;background:white;transition:.2s;outline:none;font-weight:500;color:#022b36}
#clientIdInput:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,0.2)}
#plotInput{flex:2;padding:1rem 1.2rem;font-size:1rem;font-family:'Inter',monospace;border:2px solid #ddecf2;border-radius:2rem;background:white;transition:.2s;outline:none;font-weight:500;color:#022b36}
#plotInput:focus{border-color:#00a6c4;box-shadow:0 0 0 3px rgba(0,166,196,0.2)}
#plotInput:disabled{background:#f1f5f9;cursor:not-allowed;border-color:#cbd5e1}
.search-btn{background:linear-gradient(100deg,#006d8f,#0097b3);border:none;padding:.85rem 1.8rem;border-radius:2.5rem;font-weight:700;font-size:1rem;color:white;display:inline-flex;align-items:center;gap:10px;cursor:pointer;transition:.2s;box-shadow:0 6px 12px rgba(0,109,143,0.25)}
.search-btn:hover{transform:translateY(-2px);background:linear-gradient(100deg,#005c7a,#0082a0)}
.search-btn:disabled{background:#cbd5e1;cursor:not-allowed;box-shadow:none}
.autocomplete-items{position:absolute;top:100%;left:0;right:0;background:white;border-radius:1rem;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-height:200px;overflow-y:auto;z-index:1000;margin-top:5px;display:none}
.autocomplete-item{padding:10px 15px;cursor:pointer;transition:.2s;border-bottom:1px solid #eee;color:#022b36}
.autocomplete-item:hover{background:#eef6fa}
.std-preview-card{background:#f1fafd;border-radius:1.5rem;padding:.8rem 1.2rem;margin:.8rem 0;border-left:5px solid #00b4d8;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.std-label{font-weight:700;color:#004e64;display:flex;align-items:center;gap:8px}
.std-value{font-family:monospace;font-weight:800;font-size:1.2rem;background:white;padding:.2rem 1rem;border-radius:40px;color:#00758c}
.result-panel{margin-top:1.5rem;background:#fff;border-radius:1.5rem;padding:1.2rem 1.5rem;border:1px solid #d0e6ef;animation:fadeIn .4s ease}
.result-header{display:flex;align-items:center;gap:10px;font-weight:800;color:#006680;margin-bottom:1rem;font-size:1.1rem;padding-bottom:.5rem;border-bottom:2px solid #e0eef4}
.client-info{background:#f8fbfd;border-radius:1rem;padding:1rem;margin-bottom:1rem}
.info-row{display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid #e8f0f5;font-size:.9rem;color:#1a3a42}
.info-row:last-child{border-bottom:none}
.info-row i{width:24px;color:#007f9e;flex-shrink:0;text-align:center;display:inline-flex;align-items:center;justify-content:center}
.map-link-wrapper{background:#eef3f7;padding:.8rem 1rem;border-radius:1.2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:1rem}
.map-link{color:#0077b6;text-decoration:none;font-weight:600;border-bottom:1px dashed #0077b6;word-break:break-all;font-size:.85rem}
.copy-map{background:white;border:1px solid #b8d4e0;padding:6px 14px;border-radius:40px;font-size:.75rem;font-weight:600;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:6px;color:#0077b6}
.copy-map:hover{background:#e1f0f5}
.map-embed{margin-top:.5rem;border-radius:1rem;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.map-embed iframe{width:100%;height:280px;border:none;display:block}
.error-msg{background:#ffe9e9;color:#c23d3d;padding:1rem 1.2rem;border-radius:1.2rem;margin-top:1rem;font-weight:500;display:flex;align-items:center;gap:12px}
.info-msg{background:#e3f2fd;color:#0d47a1;padding:.7rem 1rem;border-radius:1rem;margin-top:1rem;font-size:.85rem;display:flex;align-items:center;gap:8px}
.rule-hint{font-size:.7rem;color:#4f7f8c;margin-top:.5rem;background:#f9fdfe;padding:.5rem .8rem;border-radius:1rem;display:inline-flex;align-items:center;gap:6px;cursor:pointer}
footer{background:#eaf1f5;padding:1rem 2rem;text-align:center;font-size:.75rem;color:#3e6f7e;border-top:1px solid #d2e3ea}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:620px){.product-title{font-size:1.8rem}.content-core{padding:1.5rem}.input-group{flex-direction:column;align-items:stretch}.search-btn{justify-content:center}.std-value{font-size:.9rem}}
</style>
</head>
<body>
<div class="glass-card">
  <div class="brand-header">
    <div class="society-name"><i class="fas fa-industry"></i> ENTELLIPRO · EYESENSE AI</div>
    <div class="product-title">Eyesense AI</div>
    <div class="ai-tag"><i class="fas fa-microchip"></i> Smart Plot Locator — <?= htmlspecialchars($locality_name) ?></div>
  </div>

  <div class="content-core">
    <div class="info-badge">
      <i class="fas fa-database"></i>
      <p><strong>Smart Plot Locator</strong> — Enter any plot combination like <strong>A136, a-136, 136A, 136-a, 136/A, A/136</strong>. Eyesense AI automatically normalizes to <strong>136-A</strong> format and searches the database.</p>
    </div>

    <form method="GET" class="form-container" style="display: flex; flex-direction: column; gap: 1.5rem;">
      <div>
        <label><i class="fas fa-fingerprint" style="color: #6366f1;"></i> Enter Locality (Client ID)</label>
        <input type="text" id="clientIdInput" name="client_id"
          placeholder="e.g. client1, client2"
          value="<?= htmlspecialchars($client_id) ?>" autocomplete="off" onchange="this.form.submit()">
      </div>

      <div>
        <label><i class="fas fa-draw-polygon" style="color: #0097b3;"></i> Enter Plot Number</label>
        <div class="search-wrapper" id="searchWrapper">
          <div class="input-group">
            <input type="text" id="plotInput" name="plotno"
              placeholder="e.g., A136, a-136, 136A, 136/a, A/136"
              value="<?= htmlspecialchars($plotno) ?>" autocomplete="off"
              <?= $client_id === '' ? 'disabled' : '' ?>>
            <button type="submit" class="search-btn" <?= $client_id === '' ? 'disabled' : '' ?>>
              <i class="fas fa-search"></i> Search Plot
            </button>
          </div>
          <div id="autocompleteList" class="autocomplete-items"></div>
        </div>
        <div class="rule-hint" id="exampleHint" style="<?= $client_id === '' ? 'opacity:0.5; pointer-events:none;' : '' ?>">
          <i class="fas fa-lightbulb"></i> Click to try an example format
        </div>
      </div>
    </form>

    <div id="standardizedPreview"></div>
    <div id="responseArea"><?= $resultData ?></div>
  </div>

  <footer>
    <i class="fas fa-map-marked-alt"></i> Entellipro Eyesense AI — Smart Plot Normalization &amp; Database Integration<br>
    <small>© 2026 Entellipro AI Technologies Pvt Ltd</small>
  </footer>
</div>

<script>
const plotInput           = document.getElementById('plotInput');
const clientIdInput       = document.getElementById('clientIdInput');
const standardizedPreview = document.getElementById('standardizedPreview');
const autocompleteList    = document.getElementById('autocompleteList');
const suggestions         = <?= json_encode($suggestions) ?>;

function normalizeToStandard(input) {
  if (!input) return null;
  const nm = input.match(/\d+/), lm = input.match(/[A-Za-z]/);
  if (nm && lm) return nm[0] + '-' + lm[0].toUpperCase();
  if (nm) return nm[0];
  if (lm) return lm[0].toUpperCase();
  return null;
}

function updatePreview() {
  if (!plotInput) return;
  const std = normalizeToStandard(plotInput.value);
  if (!std) { standardizedPreview.innerHTML = ''; return; }
  standardizedPreview.innerHTML = `<div class="std-preview-card">
    <div class="std-label"><i class="fas fa-check-circle" style="color:#00a6c4"></i> Normalized:</div>
    <div class="std-value">${std}</div></div>`;
}

function showAutocomplete() {
  if (!plotInput) return;
  const val = plotInput.value.toLowerCase();
  if (!val) { autocompleteList.style.display = 'none'; return; }
  const filtered = suggestions.filter(s => s.toLowerCase().includes(val));
  if (!filtered.length) { autocompleteList.style.display = 'none'; return; }
  autocompleteList.innerHTML = filtered.map(s =>
    `<div class="autocomplete-item" onclick="selectPlot('${s}')">${s}</div>`
  ).join('');
  autocompleteList.style.display = 'block';
}

function selectPlot(val) {
  plotInput.value = val;
  autocompleteList.style.display = 'none';
  updatePreview();
  const cidVal = clientIdInput.value.trim();
  window.location.href = '?plotno=' + encodeURIComponent(val) + '&client_id=' + encodeURIComponent(cidVal);
}

window.copyToClipboard = function(text) {
  navigator.clipboard.writeText(text)
    .then(() => alert('Link copied!'))
    .catch(() => prompt('Copy this link:', text));
};

if (plotInput) {
  plotInput.addEventListener('input', () => { updatePreview(); showAutocomplete(); });
}
document.addEventListener('click', e => {
  if (!document.getElementById('searchWrapper').contains(e.target))
    autocompleteList.style.display = 'none';
});

// Example hint cycling
const examples = ['A136','a-136','136A','136-a','136/A','A/136','136a','42B','B42'];
const exampleHint = document.getElementById('exampleHint');
if (exampleHint) {
  exampleHint.addEventListener('click', () => {
    if (clientIdInput.value.trim() === '') return;
    const cur = plotInput.value.trim();
    const idx = examples.indexOf(cur);
    plotInput.value = examples[(idx + 1) % examples.length];
    updatePreview();
  });
}

updatePreview();
</script>
</body>
</html>
