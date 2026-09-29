<?php
/**
 * EyeSense Cloud — Shared Standard Header Component
 * Can be included in any view:
 *   $rootPath = './'; // or '../../' for nested paths
 *   $activePage = 'login'; // 'login', 'signup', 'pricing', etc.
 *   require_once __DIR__ . '/includes/header.php';
 */
$rootPath = $rootPath ?? './';
$activePage = $activePage ?? '';
?>
<!-- EyeSense Shared Header Styles -->
<style>
    :root {
        --header-bg-light: rgba(255, 255, 255, 0.96);
        --header-bg-dark: rgba(15, 23, 42, 0.96);
        --header-text-light: #1e293b;
        --header-text-dark: #f8fafc;
        --header-border-light: rgba(226, 232, 240, 0.8);
        --header-border-dark: rgba(30, 41, 59, 0.8);
        --header-primary: #2563eb;
        --header-primary-hover: #1d4ed8;
    }

    .header {
        background: var(--header-bg-light);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        position: fixed;
        width: 100%;
        top: 0;
        left: 0;
        z-index: 1000;
        border-bottom: 1px solid var(--header-border-light);
        transition: background 0.3s, border-color 0.3s;
    }

    body.dark .header,
    [data-theme="dark"] .header {
        background: var(--header-bg-dark);
        border-bottom-color: var(--header-border-dark);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
    }

    .header-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 1.5rem;
    }

    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.85rem 0;
        gap: 1rem;
    }

    .header-logo {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        cursor: pointer;
        flex-shrink: 0;
    }

    .header-logo i {
        font-size: 1.8rem;
        color: var(--header-primary);
        filter: drop-shadow(0 2px 8px rgba(37, 99, 235, 0.3));
    }

    .header-logo span {
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    .nav-menu {
        display: flex;
        gap: 0.85rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .nav-menu a,
    .nav-menu .dropdown-btn {
        text-decoration: none;
        color: #475569;
        font-weight: 500;
        font-size: 0.85rem;
        background: none;
        border: none;
        cursor: pointer;
        font-family: inherit;
        padding: 0.45rem 0.6rem;
        border-radius: 8px;
        transition: color 0.2s, background 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    body.dark .nav-menu a,
    body.dark .nav-menu .dropdown-btn,
    [data-theme="dark"] .nav-menu a,
    [data-theme="dark"] .nav-menu .dropdown-btn {
        color: #cbd5e1;
    }

    .nav-menu a:hover,
    .nav-menu .dropdown-btn:hover {
        color: var(--header-primary);
        background: rgba(37, 99, 235, 0.06);
    }

    body.dark .nav-menu a:hover,
    body.dark .nav-menu .dropdown-btn:hover,
    [data-theme="dark"] .nav-menu a:hover,
    [data-theme="dark"] .nav-menu .dropdown-btn:hover {
        color: #60a5fa;
        background: rgba(96, 165, 250, 0.1);
    }

    .nav-menu a.active-link {
        color: var(--header-primary) !important;
        font-weight: 700;
    }

    .dropdown {
        position: relative;
    }

    .dropdown-content {
        position: absolute;
        top: 100%;
        left: 0;
        background: #ffffff;
        min-width: 230px;
        box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.15), 0 0 1px rgba(0, 0, 0, 0.1);
        border-radius: 14px;
        padding: 0.5rem 0;
        opacity: 0;
        visibility: hidden;
        transform: translateY(8px);
        transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s;
        z-index: 1050;
        border: 1px solid #e2e8f0;
    }

    body.dark .dropdown-content,
    [data-theme="dark"] .dropdown-content {
        background: #1e293b;
        border-color: #334155;
        box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.5);
    }

    .dropdown:hover .dropdown-content,
    .dropdown:focus-within .dropdown-content {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-content a {
        display: block;
        padding: 0.65rem 1.2rem;
        color: #475569 !important;
        font-size: 0.85rem;
        border-radius: 0;
    }

    body.dark .dropdown-content a,
    [data-theme="dark"] .dropdown-content a {
        color: #cbd5e1 !important;
    }

    .dropdown-content a:hover {
        background: #f1f5f9 !important;
        color: var(--header-primary) !important;
        padding-left: 1.4rem;
    }

    body.dark .dropdown-content a:hover,
    [data-theme="dark"] .dropdown-content a:hover {
        background: #0f172a !important;
        color: #60a5fa !important;
    }

    .theme-toggle {
        background: none;
        border: 1px solid #cbd5e1;
        padding: 0.4rem 0.85rem;
        border-radius: 20px;
        cursor: pointer;
        color: #475569;
        font-weight: 500;
        font-size: 0.82rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }

    body.dark .theme-toggle,
    [data-theme="dark"] .theme-toggle {
        border-color: #475569;
        color: #e2e8f0;
    }

    .theme-toggle:hover {
        border-color: var(--header-primary);
        color: var(--header-primary);
    }

    .btn-nav-outline {
        border: 1.5px solid var(--header-primary) !important;
        background: transparent !important;
        color: var(--header-primary) !important;
        padding: 0.4rem 1rem !important;
        border-radius: 30px !important;
        font-weight: 600 !important;
        font-size: 0.82rem !important;
        transition: all 0.2s !important;
    }

    .btn-nav-outline:hover {
        background: var(--header-primary) !important;
        color: #ffffff !important;
    }

    .btn-nav-solid {
        background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
        color: #ffffff !important;
        padding: 0.45rem 1.1rem !important;
        border-radius: 30px !important;
        font-weight: 600 !important;
        font-size: 0.82rem !important;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3) !important;
        transition: all 0.2s !important;
    }

    .btn-nav-solid:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4) !important;
    }

    .btn-download {
        background: #2b32b5;
        color: #ffffff !important;
        padding: 0.4rem 0.95rem !important;
        border-radius: 30px !important;
        border: none;
        font-weight: 600 !important;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem !important;
        transition: all 0.2s;
    }

    .btn-download:hover {
        background: #1e248a;
        transform: translateY(-1px);
    }

    .mobile-toggle {
        display: none;
        background: none;
        border: none;
        font-size: 1.4rem;
        cursor: pointer;
        color: #1e293b;
        padding: 0.3rem 0.5rem;
    }

    body.dark .mobile-toggle,
    [data-theme="dark"] .mobile-toggle {
        color: #f8fafc;
    }

    @media (max-width: 1024px) {
        .mobile-toggle {
            display: block;
        }

        .nav-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            flex-direction: column;
            align-items: stretch;
            padding: 1rem 1.5rem;
            gap: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            display: none;
        }

        body.dark .nav-menu,
        [data-theme="dark"] .nav-menu {
            background: #0f172a;
            border-bottom-color: #334155;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .nav-menu.open {
            display: flex;
        }

        .dropdown-content {
            position: static;
            opacity: 1;
            visibility: visible;
            box-shadow: none;
            border: none;
            padding-left: 1rem;
            transform: none;
            display: none;
        }

        .dropdown.open .dropdown-content {
            display: block;
        }
    }
</style>

<header class="header">
    <div class="header-container">
        <div class="navbar">
            <a href="<?= htmlspecialchars($rootPath) ?>index.html" class="header-logo">
                <i class="fas fa-eye"></i>
                <span>EyeSense</span>
            </a>

            <button class="mobile-toggle" id="mobileMenuToggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <nav class="nav-menu" id="navMenu">
                <a href="<?= htmlspecialchars($rootPath) ?>index.html" <?= $activePage === 'home' ? 'class="active-link"' : '' ?>>Home</a>

                <!-- Features Dropdown -->
                <div class="dropdown">
                    <button type="button" class="dropdown-btn">Features <i class="fas fa-chevron-down text-xs"></i></button>
                    <div class="dropdown-content">
                        <a href="<?= htmlspecialchars($rootPath) ?>features/ai-surveillance.html"><i class="fas fa-video mr-1.5"></i> AI Surveillance</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>features/visitor-management.html"><i class="fas fa-id-badge mr-1.5"></i> Visitor Management</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>features/society-management.html"><i class="fas fa-building mr-1.5"></i> Society Management</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>features/security-operations.html"><i class="fas fa-shield-halved mr-1.5"></i> Security Operations</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>features/payment-billing.html"><i class="fas fa-credit-card mr-1.5"></i> Payment & Billing</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>features/smart-notifications.html"><i class="fas fa-bell mr-1.5"></i> Smart Notifications</a>
                    </div>
                </div>

                <!-- Solutions Dropdown -->
                <div class="dropdown">
                    <button type="button" class="dropdown-btn">Solutions <i class="fas fa-chevron-down text-xs"></i></button>
                    <div class="dropdown-content">
                        <a href="<?= htmlspecialchars($rootPath) ?>solutions/residential.html"><i class="fas fa-house-chimney mr-1.5"></i> Residential Societies</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>solutions/commercial.html"><i class="fas fa-building mr-1.5"></i> Commercial Buildings</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>solutions/education.html"><i class="fas fa-graduation-cap mr-1.5"></i> Schools & Colleges</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>solutions/healthcare.html"><i class="fas fa-hospital mr-1.5"></i> Hospitals</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>solutions/manufacturing.html"><i class="fas fa-industry mr-1.5"></i> Factories & Warehouses</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>solutions/retail.html"><i class="fas fa-store mr-1.5"></i> Retail Stores</a>
                    </div>
                </div>

                <!-- Industries Dropdown -->
                <div class="dropdown">
                    <button type="button" class="dropdown-btn">Industries <i class="fas fa-chevron-down text-xs"></i></button>
                    <div class="dropdown-content">
                        <a href="<?= htmlspecialchars($rootPath) ?>industries/residential-industry.html">Residential Societies</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>industries/healthcare-industry.html">Healthcare Industries</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>industries/education-industry.html">Education Societies</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>industries/manufacturing-industry.html">Manufacturing Industries</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>industries/logistics-industry.html">Logistics Industries</a>
                        <a href="<?= htmlspecialchars($rootPath) ?>industries/corporate-industry.html">Corporate Offices</a>
                    </div>
                </div>

                <a href="<?= htmlspecialchars($rootPath) ?>pricing.html" <?= $activePage === 'pricing' ? 'class="active-link"' : '' ?>>Pricing</a>
                <a href="<?= htmlspecialchars($rootPath) ?>index.html#about">About</a>
                <a href="<?= htmlspecialchars($rootPath) ?>index.html#contact">Contact</a>
                <a href="<?= htmlspecialchars($rootPath) ?>index.html#request-demo">Request Demo</a>

                <!-- Theme Toggle -->
                <button type="button" class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i> <span>Theme</span>
                </button>

                <!-- Auth Buttons -->
                <a href="<?= htmlspecialchars($rootPath) ?>login.php" class="<?= $activePage === 'login' ? 'btn-nav-solid' : 'btn-nav-outline' ?>">Login</a>
                <a href="<?= htmlspecialchars($rootPath) ?>signup.html" class="<?= $activePage === 'signup' ? 'btn-nav-solid' : 'btn-nav-outline' ?>">Sign Up</a>

                <!-- Download Dropdown -->
                <div class="dropdown">
                    <button type="button" class="btn-download">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <div class="dropdown-content" style="min-width: 190px; right: 0; left: auto;">
                        <a href="https://indsac.cloudtb.online/Eyesense.exe" download><i class="fas fa-download mr-1.5"></i> Windows EXE</a>
                        <a href="https://indsac.cloudtb.online/u/download/prod/Eyesense.apk" download><i class="fab fa-android mr-1.5" style="color:#10b981;"></i> Android APK</a>
                    </div>
                </div>
            </nav>
        </div>
    </div>
</header>
