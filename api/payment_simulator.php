<?php
/**
 * ============================================
 * EYESENSE PAYMENT & LICENSE SIMULATOR
 * ============================================
 * LOCAL TESTING ONLY — simulates the full flow:
 *   1. SA enters payment details
 *   2. Payment is "processed" (simulated)
 *   3. License is generated and returned
 *   4. License key can be copied for activation
 * 
 * Usage:
 *   php -S localhost:8080 -t api/
 *   Open: http://localhost:8080/payment_simulator.php
 */

$PRIVATE_KEY_PATH = __DIR__ . '/private_key.pem';

// Auto-detect openssl.cnf for Windows
$opensslCnf = getenv('OPENSSL_CONF');
if (!$opensslCnf || !file_exists($opensslCnf)) {
    $phpDir = dirname(PHP_BINARY);
    $candidates = [$phpDir . '/extras/ssl/openssl.cnf', $phpDir . '/openssl.cnf'];
    foreach ($candidates as $path) {
        if (file_exists($path)) { $opensslCnf = $path; break; }
    }
}

// Plan durations & prices — loaded from the DB (admin dashboard is the source of truth).
// TRIAL kept as safe fallback if DB is momentarily unreachable.
$PLAN_DURATIONS = ['TRIAL' => 14];
$PLAN_PRICES    = ['TRIAL' => '₹0 (Free)'];

require_once __DIR__ . '/db_setup.php';

$db_plan_loaded = false;
try {
    $db = get_license_db();
    $stmt = $db->query("SELECT plan_code, name, days, price FROM plans WHERE is_active = 1");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $PLAN_DURATIONS[$p['plan_code']] = (int)$p['days'];
        $PLAN_PRICES[$p['plan_code']]    = '₹' . number_format((float)$p['price'], 0) . ' — ' . $p['name'];
    }
    $db_plan_loaded = true;
} catch (Throwable $e) {
    // DB unreachable — only TRIAL available as fallback
}

/**
 * Look up plan duration from the DB-populated map.
 * Returns null if the plan is not registered in the admin dashboard.
 */
function resolve_plan_duration(string $plan_code, array $PLAN_DURATIONS): ?int {
    if (isset($PLAN_DURATIONS[$plan_code])) return $PLAN_DURATIONS[$plan_code];
    // Try normalised form (dash → underscore, uppercase) e.g. pro-3m → PRO_3M
    $norm = strtoupper(str_replace('-', '_', $plan_code));
    if (isset($PLAN_DURATIONS[$norm])) return $PLAN_DURATIONS[$norm];
    return null; // not registered — must be added via admin dashboard
}

// ==================================================
// HANDLE FORM SUBMISSION
// ==================================================

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $machine_id  = trim($_POST['machine_id'] ?? '');
    $plan        = $_POST['plan'] ?? '';
    $sa_name     = trim($_POST['sa_name'] ?? '');
    $sa_email    = trim($_POST['sa_email'] ?? '');
    $sa_phone    = trim($_POST['sa_phone'] ?? '');
    $auto_renew  = isset($_POST['auto_renew']);

    // Validate
    $errors = [];
    if (!$machine_id) $errors[] = 'Machine ID is required';
    $resolved_days = $plan ? resolve_plan_duration($plan, $PLAN_DURATIONS) : null;
    if (!$plan || $resolved_days === null) {
        $errors[] = !$db_plan_loaded
            ? 'Plan lookup failed: database unavailable.'
            : "Plan '{$plan}' is not registered. Add it in the INDSAC admin dashboard first.";
    }
    if (!$sa_name) $errors[] = 'SA Name is required';
    if (!$sa_email) $errors[] = 'SA Email is required';

    if (empty($errors)) {
        // --- SIMULATE PAYMENT ---
        $payment_id = 'SIM-' . strtoupper(bin2hex(random_bytes(6)));
        
        // --- GENERATE LICENSE ---
        if (!file_exists($PRIVATE_KEY_PATH)) {
            $error = 'Private key not found. Run generate_keys.php first.';
        } else {
            $privateKeyPEM = file_get_contents($PRIVATE_KEY_PATH);
            $privateKey = openssl_pkey_get_private($privateKeyPEM);
            
            if (!$privateKey) {
                $error = 'Failed to load private key: ' . openssl_error_string();
            } else {
                $start_date  = date('Y-m-d');
                $duration    = $resolved_days; // dynamically resolved above
                $expiry_date = date('Y-m-d', strtotime("+$duration days"));

                $payload = [
                    'machine_id'  => $machine_id,
                    'plan'        => $plan,
                    'start_date'  => $start_date,
                    'expiry_date' => $expiry_date,
                    'auto_renew'  => $auto_renew,
                    'contact' => [
                        'sa_name'    => $sa_name,
                        'sa_email'   => $sa_email,
                        'sa_phone'   => $sa_phone,
                        'payment_id' => $payment_id,
                    ],
                ];

                $payloadJSON = json_encode($payload, JSON_UNESCAPED_SLASHES);
                $signature = '';
                
                $success = openssl_sign($payloadJSON, $signature, $privateKey, OPENSSL_ALGO_SHA256);
                
                if ($success) {
                    $payloadB64   = rtrim(strtr(base64_encode($payloadJSON), '+/', '-_'), '=');
                    $signatureB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
                    
                    $result = [
                        'license_key' => $payloadB64 . '.' . $signatureB64,
                        'plan'        => $plan,
                        'payment_id'  => $payment_id,
                        'start_date'  => $start_date,
                        'expiry_date' => $expiry_date,
                    ];
                } else {
                    $error = 'Signing failed: ' . openssl_error_string();
                }
            }
        }
    } else {
        $error = implode(', ', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EyeSense | Payment Simulator</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #050505; --surface: #121212; --surface2: #1E1E1E;
            --border: #333; --text: #fff; --text2: #b0b0b0;
            --primary: #6366f1; --glow: rgba(99,102,241,0.3);
            --green: #10b981; --amber: #f59e0b; --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 2rem;
        }
        .container { width: 100%; max-width: 600px; }
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 24px; padding: 2.5rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.7);
        }
        .brand { 
            font-size: 1.5rem; font-weight: 700; text-align: center;
            margin-bottom: 0.5rem; letter-spacing: 1px;
        }
        .brand span { color: var(--primary); }
        .subtitle { text-align: center; color: var(--text2); margin-bottom: 2rem; font-size: 0.9rem; }
        .badge { 
            display: inline-block; background: rgba(245,158,11,0.15); color: var(--amber);
            padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;
            border: 1px solid rgba(245,158,11,0.3);
        }
        label { display: block; color: var(--text2); font-size: 0.8rem; font-weight: 600; 
                text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
        input, select, textarea {
            width: 100%; background: #0a0a0a; border: 1px solid var(--border);
            color: white; padding: 12px 14px; border-radius: 12px; font-family: inherit;
            font-size: 0.9rem; transition: border-color 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--glow);
        }
        textarea { font-family: 'Courier New', monospace; font-size: 0.75rem; resize: vertical; }
        .form-group { margin-bottom: 1.25rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .checkbox-row { display: flex; align-items: center; gap: 8px; }
        .checkbox-row input { width: auto; }
        .btn {
            width: 100%; padding: 14px; border: none; border-radius: 12px;
            font-weight: 700; font-size: 0.95rem; cursor: pointer;
            transition: all 0.3s; letter-spacing: 0.5px;
        }
        .btn-primary {
            background: var(--primary); color: white;
            box-shadow: 0 4px 15px var(--glow);
        }
        .btn-primary:hover { background: #4f46e5; transform: translateY(-2px); }
        .btn-copy {
            background: var(--green); color: white; margin-top: 0.75rem;
        }
        .btn-copy:hover { opacity: 0.9; }
        .result-card {
            background: var(--surface2); border: 1px solid var(--green);
            border-radius: 16px; padding: 1.5rem; margin-top: 1.5rem;
        }
        .result-card h3 { color: var(--green); margin-bottom: 1rem; }
        .meta-row { display: flex; justify-content: space-between; padding: 0.5rem 0;
                    border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; }
        .meta-row:last-child { border-bottom: none; }
        .meta-label { color: var(--text2); }
        .meta-value { font-weight: 600; }
        .error-box {
            background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
            color: var(--danger); padding: 12px 16px; border-radius: 12px;
            margin-bottom: 1rem; font-size: 0.9rem;
        }
        .divider { border: none; border-top: 1px solid var(--border); margin: 1.5rem 0; }
        .info { font-size: 0.8rem; color: var(--text2); text-align: center; margin-top: 1.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="brand"><span>⚡</span> EyeSense</div>
            <div class="subtitle">
                Payment & License Simulator
                <br><span class="badge">🔧 LOCAL TESTING ONLY</span>
            </div>

            <?php if ($error): ?>
                <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($result): ?>
                <!-- SUCCESS: Show generated license -->
                <div class="result-card">
                    <h3>✅ Payment Successful — License Generated</h3>
                    <div class="meta-row">
                        <span class="meta-label">Payment ID</span>
                        <span class="meta-value" style="color: var(--amber);"><?= $result['payment_id'] ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Plan</span>
                        <span class="meta-value"><?= $result['plan'] ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Valid From</span>
                        <span class="meta-value"><?= $result['start_date'] ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Valid Until</span>
                        <span class="meta-value" style="color: var(--green);"><?= $result['expiry_date'] ?></span>
                    </div>
                    
                    <hr class="divider">
                    
                    <label>License Key (copy & paste into EyeSense)</label>
                    <textarea id="licenseKey" rows="5" readonly onclick="this.select()"><?= htmlspecialchars($result['license_key']) ?></textarea>
                    <button class="btn btn-copy" onclick="copyKey()">📋 Copy License Key</button>
                </div>
                
                <hr class="divider">
                <p style="text-align:center;"><a href="payment_simulator.php" style="color:var(--primary);text-decoration:none;font-weight:600;">← Generate Another</a></p>

            <?php else: ?>
                <!-- FORM: Collect payment info -->
                <form method="POST">
                    <div class="form-group">
                        <label>Machine ID (from EyeSense activation page)</label>
                        <input type="text" name="machine_id" placeholder="Paste the SHA-256 machine ID here" required
                               value="<?= htmlspecialchars($_POST['machine_id'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Plan</label>
                        <select name="plan" required>
                            <option value="">Select a plan...</option>
                            <?php foreach ($PLAN_PRICES as $key => $price): ?>
                                <option value="<?= $key ?>" <?= (($_POST['plan'] ?? '') === $key) ? 'selected' : '' ?>>
                                    <?= $key ?> — <?= $price ?> (<?= $PLAN_DURATIONS[$key] ?> days)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr class="divider">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>SA Name</label>
                            <input type="text" name="sa_name" placeholder="Super Admin" required
                                   value="<?= htmlspecialchars($_POST['sa_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>SA Phone</label>
                            <input type="text" name="sa_phone" placeholder="+91XXXXXXXXXX"
                                   value="<?= htmlspecialchars($_POST['sa_phone'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>SA Email</label>
                        <input type="email" name="sa_email" placeholder="admin@company.com" required
                               value="<?= htmlspecialchars($_POST['sa_email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <div class="checkbox-row">
                            <input type="checkbox" name="auto_renew" id="autoRenew" 
                                   <?= isset($_POST['auto_renew']) ? 'checked' : '' ?>>
                            <label for="autoRenew" style="margin:0;text-transform:none;font-weight:400;">
                                Enable auto-renewal
                            </label>
                        </div>
                    </div>

                    <hr class="divider">

                    <div class="form-group">
                        <label>💳 Card Number (simulated)</label>
                        <input type="text" value="4242 4242 4242 4242" readonly style="color:var(--text2);">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiry</label>
                            <input type="text" value="12/28" readonly style="color:var(--text2);">
                        </div>
                        <div class="form-group">
                            <label>CVV</label>
                            <input type="text" value="***" readonly style="color:var(--text2);">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        💰 Simulate Payment & Generate License
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="info">
                🔒 This simulator runs locally for testing.<br>
                In production, this will be on <strong>indsac.com</strong>
            </div>
        </div>
    </div>

    <script>
    function copyKey() {
        const ta = document.getElementById('licenseKey');
        ta.select();
        navigator.clipboard.writeText(ta.value).then(() => {
            const btn = event.target;
            btn.textContent = '✅ Copied!';
            btn.style.background = '#059669';
            setTimeout(() => {
                btn.textContent = '📋 Copy License Key';
                btn.style.background = '';
            }, 2000);
        });
    }
    </script>
</body>
</html>
