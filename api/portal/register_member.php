<?php
/**
 * EyeSense — Public Member Registration Page
 * Accessed via: register_member.php?token=XXXXX
 * No login required — token embeds hashed client_id.
 */
require_once __DIR__ . '/../portal_config.php';
$token = trim($_GET['token'] ?? '');

// Decode token to get client_id (same logic as API)
function decodeToken(string $token): ?string {
    $decoded = base64_decode(strtr($token, '-_', '+/'));
    if (!$decoded) return null;
    $parts = explode('|', $decoded, 2);
    if (count($parts) !== 2) return null;
    $expected = hash_hmac('sha256', $parts[0], 'eyesense-reg-2026');
    return hash_equals($expected, $parts[1]) ? $parts[0] : null;
}

$clientId = $token ? decodeToken($token) : null;
$societyName = 'Society';
$sqftRate    = 0;
$rateMap     = [];
if ($clientId) {
    $pdo = get_indsac_db();
    $s = $pdo->prepare("SELECT society_name, sqft_rate FROM maintenance_settings WHERE client_id=?");
    $s->execute([$clientId]);
    $row = $s->fetch();
    if ($row && $row['society_name']) $societyName = $row['society_name'];
    if ($row && $row['sqft_rate'])    $sqftRate    = (float)$row['sqft_rate'];

    // Fetch tabular rates
    $rms = $pdo->prepare("SELECT plot_size_sqft, monthly_amount FROM maintenance_rate_map WHERE client_id=?");
    $rms->execute([$clientId]);
    while ($r = $rms->fetch()) {
        $rateMap[(int)$r['plot_size_sqft']] = (float)$r['monthly_amount'];
    }
}
$showPlotSize = ($sqftRate > 0 || !empty($rateMap));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?= htmlspecialchars($societyName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--textMain);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{background:var(--surface);border:1px solid #e5e7eb;border-radius:16px;width:100%;max-width:520px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,.05)}
        .card-header{background:linear-gradient(135deg,#4f46e5,#6366f1);padding:32px;text-align:center}
        .card-header h1{color:#ffffff;font-size:24px;font-weight:700;margin-bottom:6px}
        .card-header p{color:rgba(255,255,255,.85);font-size:14px}
        .card-body{padding:28px}
        .field{margin-bottom:16px}
        .field label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;color:var(--textSec);letter-spacing:.05em;margin-bottom:5px}
        .field input,.field textarea,.field select{width:100%;padding:12px 14px;background:var(--surface);border:1px solid var(--border);border-radius:10px;color:var(--textMain);font-size:16px;outline:none;transition:border .2s}
        .field input:focus,.field textarea:focus,.field select:focus{border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.2)}
        .field .hint{font-size:12px;color:var(--textSec);margin-top:4px}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .btn{width:100%;padding:14px;background:#4f46e5;color:#ffffff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;transition:background .2s;margin-top:8px}
        .btn:hover{background:#4338ca}
        .btn:disabled{opacity:.5;cursor:not-allowed}
        .alert{padding:14px;border-radius:10px;font-size:14px;margin-bottom:16px;display:none}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
        .invalid-token{text-align:center;padding:60px 28px}
        .invalid-token i{font-size:48px;color:#ef4444;margin-bottom:16px}
        .invalid-token h2{color:var(--textMain);margin-bottom:8px}
        .invalid-token p{color:var(--textSec);font-size:14px}
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h1><i class="fas fa-building mr-2"></i><?= htmlspecialchars($societyName) ?></h1>
        <p>Member Self-Registration</p>
    </div>
    <?php if (!$clientId): ?>
    <div class="card-body invalid-token">
        <i class="fas fa-link-slash"></i>
        <h2>Invalid Registration Link</h2>
        <p>This registration link is invalid or has expired. Please request a new link from your society admin.</p>
    </div>
    <?php else: ?>
    <div class="card-body">
        <div id="alertError" class="alert alert-error"></div>
        <div id="alertSuccess" class="alert alert-success"></div>

        <form id="regForm" onsubmit="submitRegistration(event)">
            <div class="row">
                <div class="field">
                    <label>Login ID *</label>
                    <input type="text" id="login_id" required minlength="3" placeholder="e.g. flat101">
                    <div class="hint">Used to log in to the portal</div>
                </div>
                <div class="field">
                    <label>Password *</label>
                    <input type="password" id="password" required minlength="4" placeholder="Min 4 characters">
                </div>
            </div>
            <div class="field">
                <label>Full Name *</label>
                <input type="text" id="full_name" required placeholder="Your full name">
            </div>
            <div class="row">
                <div class="field">
                    <label>Flat / Unit Number *</label>
                    <input type="text" id="flat_number" required placeholder="e.g. A-101">
                </div>
                <?php if ($showPlotSize): ?>
                <div class="field">
                    <label>Plot Size (Sq.Ft) *</label>
                    <input type="number" id="plot_size_sqft" required min="1" step="1" placeholder="e.g. 1200"
                           oninput="calcAmount()">
                    <div class="hint" id="rate_hint">
                        <?php if (!empty($rateMap)): ?>
                        Tabular Rate Map active
                        <?php else: ?>
                        Rate: &#8377;<?= number_format($sqftRate, 2) ?>/sqft
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="field">
                    <label>Monthly Amount (Rs.) *</label>
                    <input type="number" id="monthly_amount" required min="0" step="0.01" placeholder="e.g. 2500">
                </div>
                <?php endif; ?>
            </div>
            <?php if ($showPlotSize): ?>
            <div class="field">
                <label>Monthly Maintenance Amount (Rs.)</label>
                <input type="number" id="monthly_amount" min="0" step="0.01" placeholder="Auto-calculated" readonly
                       style="background:var(--surfaceLight);color:#059669;font-weight:600;cursor:default;">
                <div class="hint" id="amount_hint">
                    <?php if (!empty($rateMap)): ?>
                    Calculated from the tabular rate map based on plot size.
                    <?php else: ?>
                    Calculated as plot size &times; &#8377;<?= number_format($sqftRate, 2) ?>/sqft.
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="row">
                <div class="field">
                    <label>Email</label>
                    <input type="email" id="email" placeholder="your@email.com">
                </div>
                <div class="field">
                    <label>Mobile</label>
                    <input type="tel" id="mobile" placeholder="+91XXXXXXXXXX">
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label>Due Day of Month</label>
                    <select id="due_day">
                        <?php for ($i=1;$i<=28;$i++): ?><option value="<?=$i?>" <?=$i==10?'selected':''?>><?=$i?></option><?php endfor; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Notes (optional)</label>
                    <input type="text" id="notes" placeholder="Any special notes">
                </div>
            </div>
            <button type="submit" class="btn" id="submitBtn">
                <i class="fas fa-paper-plane mr-2"></i>Submit Registration Request
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
async function submitRegistration(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const errDiv = document.getElementById('alertError');
    const okDiv = document.getElementById('alertSuccess');
    errDiv.style.display = 'none';
    okDiv.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';

    const data = {
        action: 'submit',
        token: '<?= htmlspecialchars($token) ?>',
        login_id: document.getElementById('login_id').value.trim(),
        password: document.getElementById('password').value,
        full_name: document.getElementById('full_name').value.trim(),
        flat_number: document.getElementById('flat_number').value.trim(),
        plot_size_sqft: parseFloat(document.getElementById('plot_size_sqft')?.value) || 0,
        monthly_amount: parseFloat(document.getElementById('monthly_amount').value) || 0,
        email: document.getElementById('email').value.trim(),
        mobile: document.getElementById('mobile').value.trim(),
        due_day: parseInt(document.getElementById('due_day').value),
        notes: document.getElementById('notes').value.trim()
    };

    try {
        const resp = await fetch('../member_register_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await resp.json();
        if (result.success) {
            okDiv.textContent = result.message;
            okDiv.style.display = 'block';
            document.getElementById('regForm').style.display = 'none';
        } else {
            errDiv.textContent = result.error || 'Something went wrong';
            errDiv.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Submit Registration Request';
        }
    } catch (err) {
        errDiv.textContent = 'Network error: ' + err.message;
        errDiv.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Submit Registration Request';
    }
}
</script>
<?php if ($showPlotSize): ?>
<script>
const _sqftRate = <?= (float)$sqftRate ?>;
const _rateMap = <?= json_encode($rateMap) ?>;

function calcAmount() {
    const sqft = parseInt(document.getElementById('plot_size_sqft')?.value) || 0;
    const el   = document.getElementById('monthly_amount');
    const hint = document.getElementById('amount_hint');
    if (!el) return;

    if (Object.keys(_rateMap).length > 0) {
        if (_rateMap[sqft] !== undefined) {
            el.value = _rateMap[sqft].toFixed(2);
            if (hint) {
                hint.textContent = 'Auto-filled from rate map.';
                hint.style.color = '#059669';
            }
        } else {
            el.value = '';
            if (hint) {
                hint.textContent = 'No rate configured for this plot size in the rate map.';
                hint.style.color = '#d97706';
            }
        }
    } else {
        el.value = sqft > 0 ? (sqft * _sqftRate).toFixed(2) : '';
        if (hint) {
            hint.textContent = 'Calculated as plot size × ₹' + _sqftRate.toFixed(2) + '/sqft.';
            hint.style.color = '';
        }
    }
}
</script>
<?php endif; ?>
</body>
</html>
