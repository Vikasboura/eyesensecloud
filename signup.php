<?php
/**
 * EyeSense Cloud Platform — Registration View (PHP)
 * Uses standard shared includes/header.php and includes/footer.php
 */
$rootPath = './';
$activePage = 'signup';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - EyeSense AI Security Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f2b3d 0%, #1e4a6e 50%, #0f2b3d 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Wave Background */
        .wave-bg {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .wave-bg svg {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.15;
        }

        .wave-bg .wave1 {
            animation: wave1 15s ease-in-out infinite;
        }

        .wave-bg .wave2 {
            animation: wave2 12s ease-in-out infinite;
        }

        @keyframes wave1 {
            0%, 100% { transform: translateX(0) translateY(0); }
            50% { transform: translateX(-3%) translateY(-5px); }
        }

        @keyframes wave2 {
            0%, 100% { transform: translateX(0) translateY(0); }
            50% { transform: translateX(3%) translateY(3px); }
        }

        /* Main Center Wrapper — Keeps Form Centered & Prevents Overlap */
        .signup-main-wrapper {
            flex: 1 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 110px; /* Guarantees header never overlaps form */
            padding-bottom: 50px; /* Guarantees footer never overlaps form */
            width: 100%;
            position: relative;
            z-index: 10;
        }

        /* Signup Container */
        .signup-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
            margin: 1.5rem auto;
            padding: 0 1rem;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .signup-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            border-radius: 32px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.25);
            transition: transform 0.3s ease;
        }

        .signup-card:hover {
            transform: translateY(-3px);
        }

        /* Logo */
        .card-logo {
            text-align: center;
            margin-bottom: 1.8rem;
        }

        .card-logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.8rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4);
            }
            50% {
                box-shadow: 0 0 0 12px rgba(37, 99, 235, 0);
            }
        }

        .card-logo-icon i {
            font-size: 2rem;
            color: white;
        }

        .card-logo h2 {
            font-size: 1.7rem;
            font-weight: 800;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.3rem;
        }

        .card-logo p {
            color: #64748b;
            font-size: 0.88rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            transition: all 0.3s;
            background: white;
        }

        .input-group:hover {
            border-color: #2563eb;
        }

        .input-group:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .input-icon {
            padding: 0 1rem;
            color: #94a3b8;
            font-size: 0.95rem;
        }

        .input-group input {
            flex: 1;
            padding: 0.9rem 1rem 0.9rem 0;
            border: none;
            outline: none;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            background: transparent;
            color: #1e293b;
        }

        .input-group input::placeholder {
            color: #94a3b8;
        }

        /* Password Toggle */
        .password-toggle {
            padding: 0 1rem;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #2563eb;
        }

        /* Buttons */
        .btn-signup {
            width: 100%;
            padding: 0.95rem;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 0.5rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4);
        }

        .btn-signup:active {
            transform: translateY(0);
        }

        /* Login Link */
        .login-link {
            text-align: center;
            color: #64748b;
            font-size: 0.9rem;
        }

        .login-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
            cursor: pointer;
        }

        .login-link a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        /* Back to Home */
        .back-home {
            text-align: center;
            margin-top: 1.25rem;
        }

        .back-home a {
            color: #64748b;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s;
        }

        .back-home a:hover {
            color: #2563eb;
        }

        /* Alert Messages */
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            display: none;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border-left: 4px solid #16a34a;
        }

        .alert.show {
            display: flex;
        }

        /* Loading State */
        .btn-signup.loading {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-signup.loading::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            display: inline-block;
            animation: spin 0.8s linear infinite;
            margin-left: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            .signup-card {
                padding: 1.8rem;
                border-radius: 24px;
            }
            .signup-main-wrapper {
                padding-top: 95px;
                padding-bottom: 35px;
            }
        }
    </style>
</head>

<body>
    <!-- Standard Shared Header -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- Animated Wave Background -->
    <div class="wave-bg">
        <svg class="wave1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
            <path fill="#ffffff" fill-opacity="0.1"
                d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z">
            </path>
        </svg>
        <svg class="wave2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
            <path fill="#ffffff" fill-opacity="0.05"
                d="M0,192L48,197.3C96,203,192,213,288,208C384,203,480,181,576,181.3C672,181,768,203,864,208C960,213,1056,203,1152,186.7C1248,171,1344,149,1392,138.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z">
            </path>
        </svg>
    </div>

    <!-- Main Authentication Content (Centered, Non-overlapping) -->
    <main class="signup-main-wrapper">
        <div class="signup-container">
            <div class="signup-card">
                <div class="card-logo">
                    <div class="card-logo-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h2>Create Account</h2>
                    <p>Join EyeSense AI Platform</p>
                </div>

                <div id="alertMessage" class="alert"></div>

                <form id="signupForm">
                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <input type="text" id="fullname" placeholder="Full Name" required autocomplete="name">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-phone"></i></span>
                            <input type="tel" id="mobile" placeholder="Mobile Number (10 digits)" required maxlength="10" autocomplete="tel">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="email" placeholder="Email Address" required autocomplete="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" placeholder="Password (min 6 characters)" required autocomplete="new-password">
                            <span class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')" title="Toggle password visibility">
                                <i class="fas fa-eye-slash" id="toggleIcon1"></i>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-shield-halved"></i></span>
                            <input type="password" id="confirmPassword" placeholder="Confirm Password" required autocomplete="new-password">
                            <span class="password-toggle" onclick="togglePassword('confirmPassword', 'toggleIcon2')" title="Toggle password visibility">
                                <i class="fas fa-eye-slash" id="toggleIcon2"></i>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn-signup" id="signupBtn">
                        <i class="fas fa-user-plus"></i> Sign Up
                    </button>
                </form>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login</a>
                </div>

                <div class="back-home">
                    <a href="index.html">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Standard Shared Footer -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <!-- Form Validation Scripts -->
    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        }

        function showAlert(message, type) {
            const alertDiv = document.getElementById('alertMessage');
            alertDiv.innerHTML = `<i class="fas ${type === 'error' ? 'fa-exclamation-circle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i> ${message}`;
            alertDiv.className = `alert alert-${type} show`;
            setTimeout(() => {
                alertDiv.classList.remove('show');
            }, 4000);
        }

        document.getElementById('signupForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const fullName = document.getElementById('fullname').value.trim();
            const mobile = document.getElementById('mobile').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const signupBtn = document.getElementById('signupBtn');

            if (!fullName || !mobile || !email || !password || !confirmPassword) {
                showAlert('All fields are required.', 'error');
                return;
            }

            if (fullName.length < 2) {
                showAlert('Please enter a valid name.', 'error');
                return;
            }

            const mobileRegex = /^\d{10}$/;
            if (!mobileRegex.test(mobile)) {
                showAlert('Mobile number must be exactly 10 digits.', 'error');
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showAlert('Please enter a valid email address.', 'error');
                return;
            }

            if (password.length < 6) {
                showAlert('Password must be at least 6 characters.', 'error');
                return;
            }

            if (password !== confirmPassword) {
                showAlert('Passwords do not match.', 'error');
                return;
            }

            signupBtn.classList.add('loading');
            signupBtn.disabled = true;

            fetch('api/signup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    fullname: fullName,
                    mobile: mobile,
                    email: email,
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('You have signed up successfully. Redirecting to login...', 'success');
                    setTimeout(() => {
                        document.getElementById('signupForm').reset();
                        signupBtn.classList.remove('loading');
                        signupBtn.disabled = false;
                        window.location.href = 'login.php?registered=1';
                    }, 1500);
                } else {
                    showAlert(data.error || 'Registration failed.', 'error');
                    signupBtn.classList.remove('loading');
                    signupBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'error');
                signupBtn.classList.remove('loading');
                signupBtn.disabled = false;
            });
        });
    </script>
</body>

</html>
