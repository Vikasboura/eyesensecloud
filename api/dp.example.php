<?php
/**
 * EyeSense — Server Configuration EXAMPLE
 *
 * Copy this file to db.php and fill in your production values.
 * db.php is git-ignored — never commit real credentials.
 */

// ── Database ─────────────────────────────────────────────────────────
define('DB_CFG_HOST', 'localhost');
define('DB_CFG_PORT', '3306');
define('DB_CFG_NAME', 'eyesense_licenses');
define('DB_CFG_USER', 'your_db_user');
define('DB_CFG_PASS', 'your_db_password');

// ── Email API ────────────────────────────────────────────────────────
// Used by: maintenance_notify.php → indsacSendEmail()
define('DB_CFG_EMAIL_API_URL', 'https://indsac.com/pge/SendEmail.php');

// ── Portal Login URL ────────────────────────────────────────────────────
define('DB_CFG_PORTAL_LOGIN_URL', 'https://indsac.com/eyesense/api/portal/login.php');
// ── App Base URL ──────────────────────────────────────────────────────
// Public URL where the EyeSense API folder is reachable (NO trailing slash).
// Reads APP_BASE_URL env var first, then falls back to this value.
//   Local dev (php -S localhost:8080 -t api) : set APP_BASE_URL=http://localhost:8080 in .env
//   XAMPP (htdocs/eyesense/api)              : 'http://localhost/eyesense/api'
//   Production (indsac.com/eyesense/api)     : 'https://indsac.com/eyesense/api'
define('DB_CFG_APP_BASE_URL', getenv('APP_BASE_URL') ?: 'https://indsac.com/eyesense/api');

// ── Portal Public Base URL ────────────────────────────────────────────
// Base URL for portal-facing pages (used in share links shown to end users).
// NOTE: php -S localhost:8080 -t api serves api/ as root, so /portal/visitor.php
// is at http://localhost:8080/portal/visitor.php (no /api/ in path).
//
//   Local dev (php -S localhost:8080 -t api) : 'http://localhost:8080/portal'
//   XAMPP (htdocs/eyesense/api)              : 'http://localhost/eyesense/api/portal'
//   Production (indsac.com/eyesense/api)     : 'https://indsac.com/eyesense/api/portal'
define('DB_CFG_PORTAL_BASE_URL', 'http://localhost:8080/portal');


// ── CORS Allowed Origins ──────────────────────────────────────────────
// Comma-separated list of origins the PHP API accepts cross-origin requests from.
// Used by: verify_tenant.php, forgot_password_api.php
//
//   Production : 'https://indsac.com'
//   Local dev  : 'http://localhost:5000,http://127.0.0.1:5000,http://localhost:8080,http://127.0.0.1:8080'
//

// ── CORS Allowed Origins ─────────────────────────────────────────────
// EyeSense clients (Flask app) run locally and call this production API.
// All localhost variants must be listed alongside the production domain.
// Used by: verify_tenant.php, forgot_password_api.php
define('DB_CFG_ALLOWED_ORIGINS', 'https://indsac.com,http://localhost:5000,http://127.0.0.1:5000,http://localhost:5001,http://127.0.0.1:5001,http://localhost:8080,http://127.0.0.1:8080');

// ── Uploads ──────────────────────────────────────────────────────────
// Absolute path for maintenance receipt uploads. Leave blank for default.
define('DB_CFG_UPLOADS_DIR', '');

// ── Complaint Attachments ─────────────────────────────────────────────
// Absolute path for complaint file attachments.
// Leave blank to use default: <api_dir>/uploads/complaint_attachments
// Example: '/var/www/html/api/uploads/complaint_attachments'
define('DB_CFG_COMPLAINT_UPLOADS_DIR', '');

// Absolute path for complaint comment/chat attachments.
// Leave blank to use default: <api_dir>/uploads/complaint_comment_attachments
// Example: '/var/www/html/api/uploads/complaint_comment_attachments'
define('DB_CFG_COMMENT_UPLOADS_DIR', '');

// ── Tenant Document Uploads ───────────────────────────────────────────
// Absolute path for tenant government ID document uploads.
// Documents stored: Aadhaar, PAN, Passport, Driving License, Voter ID (JPG/PNG/PDF/WEBP)
// Leave blank to use default: <api_dir>/uploads/tenant_documents
// Production example: '/var/www/html/api/uploads/tenant_documents'
// Local dev: '' (auto-resolves)
define('DB_CFG_TENANT_UPLOADS_DIR', '');

// ── License API ──────────────────────────────────────────────────────
define('DB_CFG_LICENSE_API_SECRET', 'EYESENSE-INDSAC-2026-SECRET-KEY');

// ── OpenSSL ──────────────────────────────────────────────────────────
define('DB_CFG_OPENSSL_CONF', '');