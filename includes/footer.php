<?php
/**
 * EyeSense Cloud — Shared Standard Footer Component
 * Can be included in any view:
 *   $rootPath = './'; // or '../../' for nested paths
 *   require_once __DIR__ . '/includes/footer.php';
 */
$rootPath = $rootPath ?? './';
?>
<!-- EyeSense Shared Footer Styles -->
<style>
    .eyesense-footer {
        background: #0f2b3d;
        color: #94a3b8;
        padding: 3.5rem 0 2rem;
        margin-top: auto;
        position: relative;
        z-index: 20;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    body.dark .eyesense-footer,
    [data-theme="dark"] .eyesense-footer {
        background: #020617;
        border-top-color: rgba(255, 255, 255, 0.05);
    }

    .footer-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 1.5rem;
    }

    .eyesense-footer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 2.5rem;
        margin-bottom: 2.5rem;
    }

    .eyesense-footer-grid h4 {
        margin-bottom: 1.2rem;
        font-size: 1.05rem;
        font-weight: 700;
        color: #ffffff;
        letter-spacing: -0.01em;
    }

    .eyesense-footer-grid h4 a {
        color: #ffffff;
        text-decoration: none;
    }

    .eyesense-footer-grid ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .eyesense-footer-grid li {
        margin-bottom: 0.65rem;
    }

    .eyesense-footer-grid a {
        color: #94a3b8;
        text-decoration: none;
        font-size: 0.88rem;
        transition: color 0.2s, padding-left 0.2s;
        display: inline-block;
    }

    .eyesense-footer-grid a:hover {
        color: #ffffff;
        padding-left: 3px;
    }

    .eyesense-footer-grid p {
        color: #94a3b8;
        font-size: 0.88rem;
        margin-bottom: 0.65rem;
        display: flex;
        align-items: center;
        gap: 8px;
        line-height: 1.5;
    }

    .eyesense-footer-grid p i {
        color: #3b82f6;
        width: 16px;
        text-align: center;
    }

    .eyesense-copyright {
        text-align: center;
        padding-top: 1.8rem;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        color: #64748b;
        font-size: 0.82rem;
    }
</style>

<footer class="eyesense-footer">
    <div class="footer-container">
        <div class="eyesense-footer-grid">
            <div>
                <h4>
                    <a href="<?= htmlspecialchars($rootPath) ?>index.html" class="flex items-center gap-2">
                        <i class="fas fa-eye text-blue-500"></i> EyeSense
                    </a>
                </h4>
                <p>AI-driven security & smart facility operations platform for modern communities.</p>
                <p class="text-xs text-slate-500 mt-2">Empowering smarter security for a safer tomorrow.</p>
            </div>
            <div>
                <h4>Product</h4>
                <ul>
                    <li><a href="<?= htmlspecialchars($rootPath) ?>features/ai-surveillance.html"><i class="fas fa-video text-xs mr-1 text-slate-500"></i> AI Surveillance</a></li>
                    <li><a href="<?= htmlspecialchars($rootPath) ?>features/visitor-management.html"><i class="fas fa-id-badge text-xs mr-1 text-slate-500"></i> Visitor Management</a></li>
                    <li><a href="<?= htmlspecialchars($rootPath) ?>features/society-management.html"><i class="fas fa-building text-xs mr-1 text-slate-500"></i> Society Management</a></li>
                    <li><a href="<?= htmlspecialchars($rootPath) ?>features/payment-billing.html"><i class="fas fa-credit-card text-xs mr-1 text-slate-500"></i> Payment & Billing</a></li>
                </ul>
            </div>
            <div>
                <h4>Solutions</h4>
                <ul>
                    <li><a href="<?= htmlspecialchars($rootPath) ?>solutions/residential.html">Residential Societies</a></li>
                    <li><a href="<?= htmlspecialchars($rootPath) ?>solutions/commercial.html">Commercial Buildings</a></li>
                    <li><a href="<?= htmlspecialchars($rootPath) ?>solutions/education.html">Schools & Colleges</a></li>
                    <li><a href="<?= htmlspecialchars($rootPath) ?>solutions/healthcare.html">Hospitals & Clinics</a></li>
                </ul>
            </div>
            <div>
                <h4>Contact</h4>
                <p><i class="fas fa-envelope"></i> eyesense@indsac.com</p>
                <p><i class="fas fa-phone"></i> +91 76 7628 9081</p>
                <p><i class="fas fa-location-dot"></i> Bengaluru, Karnataka, India</p>
            </div>
        </div>
        <div class="eyesense-copyright">
            &copy; <?= date('Y') ?> EyeSense. All rights reserved. Powered by <strong style="color: #60a5fa;">INDSAC</strong>.
        </div>
    </div>
</footer>

<!-- Shared Theme & Navigation Controller -->
<script>
    (function() {
        // Theme toggle handler
        const themeBtn = document.getElementById('themeToggle');
        if (themeBtn) {
            themeBtn.addEventListener('click', function() {
                const isDark = document.body.classList.toggle('dark');
                if (document.body.hasAttribute('data-theme')) {
                    document.body.setAttribute('data-theme', isDark ? 'dark' : 'light');
                }
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                const icon = themeBtn.querySelector('i');
                if (icon) {
                    icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
                }
            });
            // Init theme state from localStorage
            if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.body.classList.add('dark');
                if (document.body.hasAttribute('data-theme')) {
                    document.body.setAttribute('data-theme', 'dark');
                }
                const icon = themeBtn.querySelector('i');
                if (icon) icon.className = 'fas fa-sun';
            }
        }

        // Mobile Menu Toggle
        const mobileToggle = document.getElementById('mobileMenuToggle');
        const navMenu = document.getElementById('navMenu');
        if (mobileToggle && navMenu) {
            mobileToggle.addEventListener('click', function() {
                navMenu.classList.toggle('open');
                const icon = mobileToggle.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-bars');
                    icon.classList.toggle('fa-xmark');
                }
            });
        }

        // Mobile dropdown toggles
        document.querySelectorAll('.dropdown > .dropdown-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024) {
                    e.preventDefault();
                    this.parentElement.classList.toggle('open');
                }
            });
        });
    })();
</script>
