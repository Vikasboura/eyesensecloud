<?php
/**
 * EyeSense Cloud Portal — Forgot Password API
 * Handles OTP-based password reset flow.
 * Actions: send_otp | verify_otp | reset_password
 * OTP: 6-digit, 5-min expiry, 1-min resend cooldown
 *
 * Public API — no portal login required.
 */
// Buffer output so any PHP warnings/errors don't corrupt JSON responses
ob_start();

require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/maintenance_notify.php';

// Discard any buffered output from includes (warnings, notices, HTML errors)
ob_clean();

// ── CORS — allow local Flask app (port 5000) to call this PHP API (port 8080) ──
$allowedOrigins = INDSAC_ALLOWED_ORIGINS;  // configured in db.php → DB_CFG_ALLOWED_ORIGINS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: ' . $allowedOrigins[0]);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody, true) ?: array_merge($_GET, $_POST);
$action  = $input['action'] ?? '';

function fp_error(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}
function fp_ok(array $data): never {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

const OTP_EXPIRES_SECONDS = 900;
const RESET_SESSION_TTL_SECONDS = 900;

// Ensure OTP table exists
function ensureOtpTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_reset_otps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id VARCHAR(50) NOT NULL,
            employee_id VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL,
            otp VARCHAR(6) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            INDEX idx_otp_lookup (client_id, employee_id, otp, used)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/**
 * Helper function to send SMS using the Twilio API over raw cURL
 */
function indsacSendSMS(string $to, string $messageBody): bool {
    // Note: It's best practice to define these in your portal_config.php file
    $token = TWILIO_AUTH_TOKEN;
    $from  = TWILIO_PHONE_NUMBER;
    $url   = TWILIO_API_URL;
    $sid   = TWILIO_ACCOUNT_SID;

    $to = trim($to);
    if (empty($to)) return false;

    // ── DYNAMIC COUNTRY CODE FIX ──
    // If the number is exactly 10 digits long, automatically prepend '+91'
    if (preg_match('/^[0-9]{10}$/', $to)) {
        $to = '+91' . $to;
    } 
    // If it's a 10-digit number starting with 0 (e.g., 09876543210), strip the 0 and prepend '+91'
    elseif (preg_match('/^0[0-9]{10}$/', $to)) {
        $to = '+91' . substr($to, 1);
    }
    // If it has 12 digits but lacks the plus sign (e.g., 919876543210)
    elseif (preg_match('/^91[0-9]{10}$/', $to)) {
        $to = '+' . $to;
    }
    // ───────────────────────────────

    $data = [
        'To'   => $to,
        'From' => $from,
        'Body' => $messageBody
    ];

    // Gracefully skip SMS if curl extension is not loaded (e.g. local dev)
    if (!function_exists('curl_init')) return false;

    $postParams = http_build_query($data);
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postParams);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 201 || $httpCode === 200);
}

try {
    portal_session_start();
    $pdo = get_indsac_db();
    ensureOtpTable($pdo);

    switch ($action) {

        // ── STEP 1-ALT: Send OTP (email-only lookup from login page) ──
        case 'send_otp_by_email':
            $email    = strtolower(trim($input['email'] ?? ''));
            $clientId = trim($input['client_id'] ?? '');   // optionally injected by Flask proxy
            if (!$email) fp_error('Email is required');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fp_error('Invalid email address');

            // Find active employee — scope to client_id when provided, prioritize valid client_id
            if ($clientId) {
                $stmt = $pdo->prepare("
                    SELECT id, client_id, employee_id, full_name, email, phone, status
                    FROM employees
                    WHERE LOWER(email) = ? AND client_id = ? AND status = 'active'
                    ORDER BY (CASE WHEN client_id IS NOT NULL AND client_id != '' THEN 0 ELSE 1 END), id DESC
                    LIMIT 1
                ");
                $stmt->execute([$email, $clientId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, client_id, employee_id, full_name, email, phone, status
                    FROM employees
                    WHERE LOWER(email) = ? AND status = 'active'
                    ORDER BY (CASE WHEN client_id IS NOT NULL AND client_id != '' THEN 0 ELSE 1 END), id DESC
                    LIMIT 1
                ");
                $stmt->execute([$email]);
            }
            $user = $stmt->fetch();

            // If not found in employees, check public registered accounts in `register` table
            if (!$user) {
                try {
                    $rStmt = $pdo->prepare("SELECT id, fullname, email, mobile FROM register WHERE LOWER(email) = ? LIMIT 1");
                    $rStmt->execute([$email]);
                    $regUser = $rStmt->fetch();
                    if ($regUser) {
                        $user = [
                            'id'          => $regUser['id'],
                            'client_id'   => 'PUBLIC',
                            'employee_id' => 'R-' . $regUser['id'],
                            'full_name'   => $regUser['fullname'],
                            'email'       => $regUser['email'],
                            'phone'       => $regUser['mobile'] ?? '',
                            'status'      => 'active'
                        ];
                    }
                } catch (Throwable $e) {}
            }

            if (!$user) {
                fp_error('No active account found with this email address');
            }

            // Cleanly resolve client_id: fallback to employee_id prefix or 'PUBLIC' if blank
            $clientId = trim($user['client_id'] ?? '');
            if (!$clientId && !empty($user['employee_id'])) {
                if (preg_match('/^(IND-[0-9]{4}-[A-Za-z0-9]+)/i', $user['employee_id'], $m)) {
                    $clientId = $m[1];
                }
            }
            if (!$clientId) {
                $clientId = 'PUBLIC';
            }

            // Auto-heal empty client_id in employees table if resolved
            if (!empty($user['id']) && empty($user['client_id']) && $clientId !== 'PUBLIC') {
                try {
                    $pdo->prepare("UPDATE employees SET client_id = ? WHERE id = ?")->execute([$clientId, $user['id']]);
                } catch (Throwable $e) {}
            }

            // FIX: Check 1-minute resend cooldown using pure MySQL engine time
            $cooldownStmt = $pdo->prepare("
                SELECT id FROM password_reset_otps
                WHERE employee_id = ? AND used = 0
                  AND created_at > NOW() - INTERVAL 1 MINUTE
                LIMIT 1
            ");
            $cooldownStmt->execute([$user['employee_id']]);
            if ($cooldownStmt->fetch()) {
                fp_error('Please wait a minute before requesting a new OTP', 429);
            }

            // Invalidate old OTPs for this user/employee
            $pdo->prepare("UPDATE password_reset_otps SET used = 1 WHERE employee_id = ? AND used = 0")
                ->execute([$user['employee_id']]);

            // Generate 6-digit OTP
            $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRES_SECONDS);

            // FIX: Use NOW() + INTERVAL 5 MINUTE to generate absolute database matching time windows
            $pdo->prepare("
                INSERT INTO password_reset_otps (client_id, employee_id, email, otp, expires_at)
                VALUES (?, ?, ?, ?, NOW() + INTERVAL 5 MINUTE)
            ")->execute([$clientId, $user['employee_id'], $email, $otp]);

            // Send OTP email with client_id included
            $maskedEmail = preg_replace('/(?<=.{2}).(?=.*@)/', '*', $email);
            $clientIdInfo = ($clientId && $clientId !== 'PUBLIC') ? "<p style=\"color:#6b7280;font-size:14px;line-height:1.6;\">Your Client ID is: <strong style=\"color:#6366f1;font-family:monospace;font-size:16px;\">{$clientId}</strong></p>" : "";

            $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:'Segoe UI',sans-serif;">
<div style="max-width:480px;margin:20px auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
    <div style="background:linear-gradient(135deg,#6366f1,#a855f7);padding:28px;text-align:center;">
        <div style="font-size:48px;">🔐</div>
        <h1 style="color:white;margin:10px 0 0;font-size:22px;">Password Reset OTP</h1>
        <p style="color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:13px;">EyeSense Cloud Portal</p>
    </div>
    <div style="padding:32px;">
        <p style="color:#374151;font-size:15px;">Dear <strong>{$user['full_name']}</strong>,</p>
        {$clientIdInfo}
        <p style="color:#6b7280;font-size:14px;line-height:1.6;">Use the OTP below to reset your password. This code expires in <strong>5 minutes</strong>.</p>

        <div style="text-align:center;margin:28px 0;">
            <div style="display:inline-block;background:#f3f4f6;border:2px dashed #6366f1;border-radius:12px;padding:18px 36px;">
                <span style="font-size:36px;font-weight:900;letter-spacing:10px;color:#6366f1;font-family:monospace;">{$otp}</span>
            </div>
        </div>
        <p style="color:#ef4444;font-size:13px;text-align:center;">⏰ Expires in 5 minutes &nbsp;|&nbsp; Do not share this OTP</p>

        <p style="color:#9ca3af;font-size:12px;margin-top:20px;">If you did not request this, you can safely ignore this email.</p>
    </div>
    <div style="padding:16px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">
        <p style="color:#9ca3af;margin:0;font-size:11px;">EyeSense Cloud Portal • Powered by INDSAC</p>
    </div>
</div>
</body></html>
HTML;
            $emailSent = indsacSendEmail($email, '[EyeSense] Password Reset OTP', $html);

            // ── TWILIO SMS DELIVERY ──
            $smsSent = true;
            $phoneNumber = trim($user['phone'] ?? '');
            if (!empty($phoneNumber)) {
                $smsMessage = "Your EyeSense verification code is: {$otp}. This code expires in 5 minutes.";
                $smsSent = indsacSendSMS($phoneNumber, $smsMessage);
            }

            // NOTE: Even if email/SMS delivery fails, the OTP row has already been inserted into DB.
            $deliveryOk = $emailSent || $smsSent;

            $_SESSION['otp_reset_client'] = $clientId;
            $_SESSION['otp_reset_emp']    = $user['employee_id'];
            $_SESSION['otp_reset_email']  = $email;

            fp_ok([
                'message'          => $deliveryOk ? 'OTP sent successfully.' : 'OTP generated. Delivery may be delayed.',
                'masked_email'     => $maskedEmail,
                'sms_delivered'    => $smsSent,
                'email_delivered'  => $emailSent,
                'delivery_ok'      => $deliveryOk,
                'client_id'        => $clientId,
                'employee_id'      => $user['employee_id'],
                'expires_in'       => OTP_EXPIRES_SECONDS,
            ]);

        // ── STEP 1: Send OTP (original — needs client_id + username + email) ──
        case 'send_otp_by_username':
            $clientId = trim($input['client_id'] ?? '');
            $username = trim($input['username'] ?? '');
            $email    = strtolower(trim($input['email'] ?? ''));

            if (!$clientId || !$username || !$email) fp_error('All fields are required');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fp_error('Invalid email address');

            // Look up user — match client_id, username/employee_id, AND email
            $stmt = $pdo->prepare("
                SELECT id, client_id, employee_id, full_name, email, phone, status
                FROM employees
                WHERE (client_id = ? OR client_id = '')
                  AND (employee_id = ? OR full_name = ?)
                  AND LOWER(email) = ?
                  AND status = 'active'
                ORDER BY (CASE WHEN client_id IS NOT NULL AND client_id != '' THEN 0 ELSE 1 END), id DESC
                LIMIT 1
            ");
            $stmt->execute([$clientId, $username, $username, $email]);
            $user = $stmt->fetch();

            if (!$user) {
                fp_error('No active account found matching those credentials');
            }

            // FIX: Check 1-minute resend cooldown using pure MySQL engine time
            $cooldownStmt = $pdo->prepare("
                SELECT id FROM password_reset_otps
                WHERE employee_id = ? AND used = 0
                  AND created_at > NOW() - INTERVAL 1 MINUTE
                LIMIT 1
            ");
            $cooldownStmt->execute([$user['employee_id']]);
            if ($cooldownStmt->fetch()) {
                fp_error('Please wait a minute before requesting a new OTP', 429);
            }

            // Invalidate old OTPs for this user
            $pdo->prepare("UPDATE password_reset_otps SET used = 1 WHERE employee_id = ? AND used = 0")
                ->execute([$user['employee_id']]);

            // Generate 6-digit OTP
            $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRES_SECONDS);

            // FIX: Use NOW() + INTERVAL 5 MINUTE to generate absolute database matching time windows
            $pdo->prepare("
                INSERT INTO password_reset_otps (client_id, employee_id, email, otp, expires_at)
                VALUES (?, ?, ?, ?, NOW() + INTERVAL 5 MINUTE)
            ")->execute([$clientId, $user['employee_id'], $email, $otp]);

            // Send OTP email
            $maskedEmail = preg_replace('/(?<=.{2}).(?=.*@)/', '*', $email);
            $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:'Segoe UI',sans-serif;">
<div style="max-width:480px;margin:20px auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
    <div style="background:linear-gradient(135deg,#6366f1,#a855f7);padding:28px;text-align:center;">
        <div style="font-size:48px;">🔐</div>
        <h1 style="color:white;margin:10px 0 0;font-size:22px;">Password Reset OTP</h1>
        <p style="color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:13px;">EyeSense Cloud Portal</p>
    </div>
    <div style="padding:32px;">
        <p style="color:#374151;font-size:15px;">Dear <strong>{$user['full_name']}</strong>,</p>
        <p style="color:#6b7280;font-size:14px;line-height:1.6;">Use the OTP below to reset your password. This code expires in <strong>5 minutes</strong>.</p>
        <div style="text-align:center;margin:28px 0;">
            <div style="display:inline-block;background:#f3f4f6;border:2px dashed #6366f1;border-radius:12px;padding:18px 36px;">
                <span style="font-size:36px;font-weight:900;letter-spacing:10px;color:#6366f1;font-family:monospace;">{$otp}</span>
            </div>
        </div>
        <p style="color:#ef4444;font-size:13px;text-align:center;">⏰ Expires in 5 minutes &nbsp;|&nbsp; Do not share this OTP</p>
        <p style="color:#9ca3af;font-size:12px;margin-top:20px;">If you did not request this, you can safely ignore this email.</p>
    </div>
    <div style="padding:16px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">
        <p style="color:#9ca3af;margin:0;font-size:11px;">EyeSense Cloud Portal • Powered by INDSAC</p>
    </div>
</div>
</body></html>
HTML;
            $emailSent = indsacSendEmail($email, '[EyeSense] Password Reset OTP', $html);

            // ── TWILIO SMS DELIVERY ──
            $smsSent = false;
            $phoneNumber = trim($user['phone'] ?? '');
            if (!empty($phoneNumber)) {
                $smsMessage = "Your EyeSense verification code is: {$otp}. This code expires in 5 minutes.";
                $smsSent = indsacSendSMS($phoneNumber, $smsMessage);
            }

            if (!$emailSent && !$smsSent) {
                fp_error('Failed to send OTP via Email or SMS. Please try again.');
            }

            $_SESSION['otp_reset_client'] = $clientId;
            $_SESSION['otp_reset_emp']    = $user['employee_id'];
            $_SESSION['otp_reset_email']  = $email;

            fp_ok([
                'message'       => 'OTP sent successfully.',
                'masked_email'  => $maskedEmail,
                'sms_delivered' => $smsSent,
                'expires_in'    => OTP_EXPIRES_SECONDS,
                'employee_id'   => $user['employee_id'],
            ]);

        // ── STEP 2: Verify OTP ──
        case 'verify_otp':
            $clientId   = trim($input['client_id']   ?? $_SESSION['otp_reset_client'] ?? '');
            $employeeId = trim($input['employee_id'] ?? $_SESSION['otp_reset_emp']    ?? '');
            $otp        = trim($input['otp'] ?? '');

            if (!$otp) fp_error('Please enter the 6-digit OTP');
            if (!$employeeId && !$clientId) fp_error('Session expired. Please request OTP again.');

            // Fetch without strict time filter — check expiry in PHP to avoid MySQL timezone mismatch
            if ($clientId && $employeeId) {
                $stmt = $pdo->prepare("
                    SELECT id, client_id, employee_id, email, expires_at FROM password_reset_otps
                    WHERE (client_id = ? OR client_id = '' OR client_id IS NULL) AND employee_id = ? AND otp = ? AND used = 0
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$clientId, $employeeId, $otp]);
            } elseif ($employeeId) {
                $stmt = $pdo->prepare("
                    SELECT id, client_id, employee_id, email, expires_at FROM password_reset_otps
                    WHERE employee_id = ? AND otp = ? AND used = 0
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$employeeId, $otp]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, client_id, employee_id, email, expires_at FROM password_reset_otps
                    WHERE client_id = ? AND otp = ? AND used = 0
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$clientId, $otp]);
            }
            $row = $stmt->fetch();

            if (!$row) {
                fp_error('Invalid OTP. Please check the code and try again.');
            }
            if (strtotime($row['expires_at']) < time()) {
                fp_error('OTP has expired. Please request a new one.');
            }

            $finalClientId   = !empty($row['client_id']) ? $row['client_id'] : ($clientId ?: 'PUBLIC');
            $finalEmployeeId = !empty($row['employee_id']) ? $row['employee_id'] : $employeeId;

            // Store verified flag in session
            $_SESSION['otp_verified_id']     = $row['id'];
            $_SESSION['otp_reset_client']    = $finalClientId;
            $_SESSION['otp_reset_emp']       = $finalEmployeeId;
            $_SESSION['otp_reset_email']     = $row['email'];
            $_SESSION['otp_verified_at']     = time();

            fp_ok([
                'message'     => 'OTP verified. You may now reset your password.',
                'client_id'   => $finalClientId,
                'employee_id' => $finalEmployeeId
            ]);

        // ── STEP 3: Reset Password ──
        case 'reset_password':
            $clientId   = trim($input['client_id']   ?? $_SESSION['otp_reset_client'] ?? '');
            $employeeId = trim($input['employee_id'] ?? $_SESSION['otp_reset_emp']    ?? '');
            $email      = trim($_SESSION['otp_reset_email'] ?? '');
            $verifiedAt = $_SESSION['otp_verified_at'] ?? 0;
            $verifiedId = $_SESSION['otp_verified_id'] ?? 0;

            if (!$verifiedId || (!$employeeId && !$clientId && !$email)) fp_error('Session expired. Please start over.');
            if ((time() - $verifiedAt) > RESET_SESSION_TTL_SECONDS) fp_error('Reset session expired. Please start over.');

            $newPass = $input['new_password'] ?? '';
            $confirm = $input['confirm_password'] ?? '';

            if (strlen($newPass) < 8) fp_error('Password must be at least 8 characters');
            if ($newPass !== $confirm) fp_error('Passwords do not match');

            // Handle public registered user (register table)
            if ($clientId === 'PUBLIC' || (is_string($employeeId) && str_starts_with($employeeId, 'R-'))) {
                $regId = (int)str_replace('R-', '', $employeeId);
                $bcryptHash = password_hash($newPass, PASSWORD_DEFAULT);
                if ($regId > 0) {
                    $pdo->prepare("UPDATE register SET password = ? WHERE id = ?")->execute([$bcryptHash, $regId]);
                } elseif ($email) {
                    $pdo->prepare("UPDATE register SET password = ? WHERE LOWER(email) = ?")->execute([$bcryptHash, strtolower($email)]);
                }
            } else {
                // Employee / Admin account
                // Use the same hash format as the portal login (Werkzeug-compatible pbkdf2:sha256)
                $hash = generate_werkzeug_password($newPass);

                if ($clientId && $employeeId) {
                    $pdo->prepare("UPDATE employees SET password_hash = ? WHERE (client_id = ? OR client_id = '' OR client_id IS NULL) AND employee_id = ?")
                        ->execute([$hash, $clientId, $employeeId]);
                } elseif ($employeeId) {
                    $pdo->prepare("UPDATE employees SET password_hash = ? WHERE employee_id = ?")
                        ->execute([$hash, $employeeId]);
                } elseif ($email) {
                    $pdo->prepare("UPDATE employees SET password_hash = ? WHERE LOWER(email) = ?")
                        ->execute([$hash, strtolower($email)]);
                }
            }

            // Mark OTP as used
            $pdo->prepare("UPDATE password_reset_otps SET used = 1 WHERE id = ?")
                ->execute([$verifiedId]);

            // Clear session keys
            unset($_SESSION['otp_reset_client'], $_SESSION['otp_reset_emp'],
                  $_SESSION['otp_reset_email'],
                  $_SESSION['otp_verified_id'], $_SESSION['otp_verified_at']);

            fp_ok(['message' => 'Password reset successfully. You can now log in.']);

        default:
            fp_error('Unknown action');
    }

} catch (PDOException $e) {
    fp_error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    fp_error('System error: ' . $e->getMessage(), 500);
}