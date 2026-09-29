<?php
/**
 * Super Admin Login Page
 * Secure PDO verification with Bcrypt support and CSRF validation.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// If already authenticated, redirect to dashboard
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    redirect('dashboard.php');
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_message = 'Security token expired or invalid. Please try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (empty($email) || empty($password)) {
            $error_message = 'Please enter both email address and password.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();
                
                if ($admin) {
                    if ($admin['status'] !== 'Active') {
                        $error_message = 'Your account has been deactivated. Please contact support.';
                    } else {
                        // Verify against Bcrypt hash or plaintext fallback for local testing convenience
                        $verified = false;
                        if (password_verify($password, $admin['password'])) {
                            $verified = true;
                        } elseif ($password === $admin['password']) {
                            // Automatically upgrade plaintext to bcrypt hash for future logins
                            $new_hash = password_hash($password, PASSWORD_BCRYPT);
                            $update_stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                            $update_stmt->execute([$new_hash, $admin['id']]);
                            $verified = true;
                        }
                        
                        if ($verified) {
                            // Check Super Admin role
                            if ($admin['role'] !== 'Super Admin') {
                                $error_message = 'Access Denied: Only Super Admin can access this control panel.';
                            } else {
                                // Initialize session
                                session_regenerate_id(true);
                                $_SESSION['admin_logged_in'] = true;
                                $_SESSION['admin_id'] = $admin['id'];
                                $_SESSION['admin_name'] = $admin['name'];
                                $_SESSION['admin_email'] = $admin['email'];
                                $_SESSION['admin_role'] = $admin['role'];
                                $_SESSION['last_activity'] = time();
                                
                                set_flash_message('success', "Welcome back, " . $admin['name'] . "!");
                                redirect('dashboard.php');
                            }
                        } else {
                            $error_message = 'Invalid email address or password.';
                        }
                    }
                } else {
                    $error_message = 'Invalid email address or password.';
                }
            } catch (PDOException $e) {
                error_log("Login Error: " . $e->getMessage());
                $error_message = 'An internal system error occurred. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-fit, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <style>
        body.login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top left, #1e293b, #0f172a 70%);
            padding: 2rem 1rem;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .login-header {
            background: var(--primary-gradient);
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
            position: relative;
        }
        .login-header-icon {
            width: 68px;
            height: 68px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        .login-body {
            padding: 2.5rem 2rem 2rem;
        }
    </style>
</head>
<body class="login-page">

<div class="login-card">
    <div class="login-header">
        <div class="login-header-icon">
            <i class="fas fa-hotel"></i>
        </div>
        <h3 class="fw-bold mb-1">Hostel Management</h3>
        <p class="mb-0 text-white-50 font-size-sm">Super Admin Portal</p>
    </div>
    
    <div class="login-body">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4 rounded-3 border-0 py-2 px-3 shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle fs-5 me-3 text-danger"></i>
                <div class="small fw-medium"><?= htmlspecialchars($error_message) ?></div>
            </div>
        <?php endif; ?>
        
        <?php
        $flash = get_flash_message();
        if ($flash && $flash['type'] === 'info'): ?>
            <div class="alert alert-info d-flex align-items-center mb-4 rounded-3 border-0 py-2 px-3 shadow-sm" role="alert">
                <i class="fas fa-info-circle fs-5 me-3 text-info"></i>
                <div class="small fw-medium"><?= htmlspecialchars($flash['message']) ?></div>
            </div>
        <?php endif; ?>
        
        <form action="<?= BASE_URL ?>login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <div class="mb-4">
                <label for="email" class="form-label text-secondary">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input type="email" class="form-control border-start-0 rounded-end-3 py-2" id="email" name="email" value="admin@hostel.com" required placeholder="Enter your admin email" autofocus>
                </div>
            </div>
            
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label text-secondary mb-0">Password</label>
                    <small class="text-muted font-monospace">Default: admin123</small>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" class="form-control border-start-0 rounded-end-3 py-2" id="password" name="password" required placeholder="Enter your password">
                </div>
            </div>
            
            <div class="d-grid gap-2 mt-4 pt-2">
                <button type="submit" class="btn btn-custom-primary justify-content-center py-3 fs-6 rounded-3">
                    <i class="fas fa-right-to-bracket me-2"></i> Sign In to Control Panel
                </button>
            </div>
        </form>
    </div>
    
    <div class="bg-light px-4 py-3 text-center border-top">
        <small class="text-muted d-block">&copy; <?= date('Y') ?> Super Admin Panel. Core PHP Architecture.</small>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
