<?php
/**
 * EyeSense — Machine ID Generator
 * Generates a unique, hardware-bound machine ID for EyeSense.
 * Format: SHA-256 hex (same as src/licensing/hardware.py get_machine_id())
 *
 * URL: /machine_id_gen.php?token=<token>
 */

$token = trim($_GET['token'] ?? '');
if (!$token || !preg_match('/^[A-Za-z0-9_-]{8,64}$/', $token)) {
    http_response_code(400);
    die('Invalid or missing registration token.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EyeSense — Machine ID Registration</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: radial-gradient(ellipse at top, #0f0f1a 0%, #050510 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            color: #e0e0e0;
        }
        .card {
            background: #1a1a2e; border: 1px solid #2a2a3e;
            border-radius: 20px; padding: 40px; max-width: 540px; width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6); text-align: center;
        }
        .logo { font-size: 48px; margin-bottom: 8px; }
        h1 { color: #667eea; font-size: 22px; margin-bottom: 6px; }
        p.sub { color: #888; font-size: 13px; margin-bottom: 28px; }

        .machine-id-box {
            background: #0f0f1a; border: 2px solid #667eea;
            border-radius: 12px; padding: 20px 24px; margin: 24px 0;
        }
        .machine-id-box .label { font-size: 11px; color: #888; text-transform: uppercase; margin-bottom: 8px; }
        .machine-id-box .value {
            font-size: 12px; font-weight: 700; color: #a5b4fc;
            letter-spacing: 1px; word-break: break-all; font-family: 'Courier New', monospace;
            line-height: 1.8;
        }
        .machine-id-box .value.loading {
            font-size: 14px; color: #555; animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0%,100%{ opacity:1 } 50%{ opacity:0.4 } }

        .format-note {
            font-size: 11px; color: #667eea; margin-top: 6px;
            padding: 4px 10px; background: rgba(102,126,234,0.08);
            border-radius: 6px; display: inline-block;
        }

        .btn-copy {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff; border: none; border-radius: 10px;
            padding: 12px 32px; font-size: 15px; font-weight: 600;
            cursor: pointer; transition: opacity 0.2s; width: 100%; margin-top: 4px;
        }
        .btn-copy:hover { opacity: 0.88; }
        .btn-copy:disabled { opacity: 0.4; cursor: not-allowed; }

        .copied-msg { color: #10b981; font-size: 13px; margin-top: 10px; display: none; }

        .steps { text-align: left; background: #0f0f1a; border-radius: 12px;
            padding: 18px 20px; margin-top: 24px; font-size: 13px; color: #aaa; }
        .steps h3 { color: #667eea; font-size: 13px; margin-bottom: 10px; }
        .steps ol { padding-left: 18px; line-height: 2; }
        .steps code { background: #1a1a2e; padding: 2px 6px; border-radius: 4px; color: #a5b4fc; font-size: 12px; }
        .info-box { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2);
            border-radius: 8px; padding: 12px 16px; margin-top: 16px; font-size: 12px; color: #fbbf24; text-align: left; }
        .footer { margin-top: 24px; font-size: 11px; color: #555; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">🖥️</div>
    <h1>EyeSense Machine ID</h1>
    <p class="sub">Your unique hardware fingerprint for EyeSense license activation</p>

    <div class="machine-id-box">
        <div class="label">Your Machine ID (SHA-256)</div>
        <div class="value loading" id="machineIdDisplay">⏳ Generating hardware fingerprint…</div>
        <div class="format-note" id="formatNote" style="display:none;">64-character SHA-256 hash — identical to EyeSense setup.py format</div>
    </div>

    <button class="btn-copy" onclick="copyMachineId()" id="copyBtn" disabled>
        📋 Copy Machine ID
    </button>
    <div class="copied-msg" id="copiedMsg">✅ Copied to clipboard! Send this to your INDSAC administrator.</div>

    <div class="info-box">
        ⚠️ <strong>Important:</strong> This Machine ID is generated from your browser's hardware profile.
        For the most accurate ID (used by EyeSense desktop), run <code>setup.py</code> on your machine.
        This link provides a compatible fallback for quick registration.
    </div>

    <div class="steps">
        <h3>📌 Next Steps</h3>
        <ol>
            <li>Copy the Machine ID shown above</li>
            <li>Send it to your INDSAC administrator</li>
            <li>They will activate your EyeSense license</li>
            <li>You'll receive your <code>Client ID</code> and login details by email</li>
        </ol>
    </div>

    <p class="footer">EyeSense Cloud Platform • Powered by INDSAC<br>
    Token: <?= htmlspecialchars(substr($token, 0, 8)) ?>… | <?= date('d M Y') ?></p>
</div>

<script>
// Generate a SHA-256 machine fingerprint in the SAME format as hardware.py
// hardware.py: hashlib.sha256(raw_id.encode("utf-8")).hexdigest()
// We mirror this by creating a "raw_id" from browser hardware signals
// then SHA-256 hashing it → 64 lowercase hex chars

async function sha256hex(str) {
    const buf = new TextEncoder().encode(str);
    const hash = await crypto.subtle.digest('SHA-256', buf);
    return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
}

async function generateMachineId() {
    // Collect stable hardware-like fingerprint signals (mirrors hardware.py logic)
    // On Windows: wmic UUID → hash. We approximate with stable browser signals.
    const components = [
        navigator.userAgent,            // Browser/OS string
        navigator.hardwareConcurrency || 'unknown',  // CPU cores
        screen.width + 'x' + screen.height,         // Screen resolution
        screen.colorDepth,                           // Color depth
        navigator.language,                          // Language
        new Date().getTimezoneOffset(),              // Timezone
        typeof navigator.deviceMemory !== 'undefined' ? navigator.deviceMemory : 'unknown',
        navigator.platform || 'unknown',
        navigator.maxTouchPoints || 0,
    ];

    // Build a "raw_id" string similar to what hardware.py would produce
    const rawId = components.join('|');

    // SHA-256 hash — exact same algorithm as hardware.py's get_machine_id()
    const machineId = await sha256hex(rawId);

    document.getElementById('machineIdDisplay').textContent = machineId;
    document.getElementById('machineIdDisplay').classList.remove('loading');
    document.getElementById('formatNote').style.display = 'block';
    document.getElementById('copyBtn').disabled = false;
}

function copyMachineId() {
    const id = document.getElementById('machineIdDisplay').textContent;
    navigator.clipboard.writeText(id).then(() => {
        document.getElementById('copiedMsg').style.display = 'block';
        document.getElementById('copyBtn').textContent = '✅ Copied!';
        setTimeout(() => {
            document.getElementById('copiedMsg').style.display = 'none';
            document.getElementById('copyBtn').textContent = '📋 Copy Machine ID';
        }, 4000);
    });
}

generateMachineId();
</script>
</body>
</html>
