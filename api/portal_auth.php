<?php
/**
 * EyeSense Cloud Portal — Authentication & Authorization
 * 
 * Two modes:
 *   1. API key authentication (for backup agent machine-to-machine calls)
 *   2. Session-based authentication (for portal web UI)
 */

require_once __DIR__ . '/portal_config.php';

// ── Werkzeug Password Verification ──

/**
 * Verify a password against a Werkzeug-generated hash.
 * Supports: scrypt, pbkdf2 (Werkzeug), bcrypt/argon2 (PHP native).
 * Delegates scrypt/pbkdf2 verification to verify_password.py.
 */
function verify_werkzeug_password(string $password, string $stored_hash): bool {
    // Standard bcrypt / argon2 — let PHP handle it
    if (str_starts_with($stored_hash, '$2y$') || str_starts_with($stored_hash, '$argon2')) {
        return password_verify($password, $stored_hash);
    }

    // Werkzeug scrypt format
    if (str_starts_with($stored_hash, 'scrypt:')) {
        // Pure PHP cannot verify Werkzeug scrypt securely without extensions.
        // It relies on the EyeSense application using pbkdf2:sha256.
        return false;
    }

    // Werkzeug pbkdf2 format: "pbkdf2:sha256:N$salt$hex"
    if (str_starts_with($stored_hash, 'pbkdf2:')) {
        $parts = explode('$', $stored_hash, 3);
        if (count($parts) !== 3) return false;

        $method_part = $parts[0]; // "pbkdf2:sha256:260000"
        $salt        = $parts[1];
        $expected    = $parts[2];

        $method_tokens = explode(':', $method_part);
        $algo       = $method_tokens[1] ?? 'sha256';
        $iterations = (int)($method_tokens[2] ?? 260000);

        $keylen = strlen($expected) / 2;
        $derived = hash_pbkdf2($algo, $password, $salt, $iterations, $keylen, true);

        return hash_equals($expected, bin2hex($derived));
    }

    return false;
}

/**
 * Generate a Werkzeug-compatible pbkdf2:sha256 password hash.
 * Output format: "pbkdf2:sha256:600000$<salt>$<hex>"
 * This hash can be verified by both PHP (verify_werkzeug_password) and
 * Python/Werkzeug (check_password_hash).
 */
function generate_werkzeug_password(string $password): string {
    // Generate 16-char random salt (matches Werkzeug's default)
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $salt = '';
    for ($i = 0; $i < 16; $i++) {
        $salt .= $chars[random_int(0, strlen($chars) - 1)];
    }

    $iterations = 600000;
    $keylen     = 32; // 256-bit key
    $derived    = hash_pbkdf2('sha256', $password, $salt, $iterations, $keylen, true);

    return "pbkdf2:sha256:{$iterations}\${$salt}\$" . bin2hex($derived);
}


// ── Agent Client ID Validation (for agent calls) ──

/**
 * Require a valid client_id from headers, JSON body, or query string.
 * Returns the validated client_id.
 */
function requireAgentAuth(): string {
    $clientId = $_SERVER['HTTP_X_CLIENT_ID'] ?? '';

    // If not in header, check POST JSON body
    if (empty($clientId) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && !empty($input['client_id'])) {
            $clientId = $input['client_id'];
        }
    }

    // If not in body, check query params
    if (empty($clientId) && !empty($_GET['client_id'])) {
        $clientId = $_GET['client_id'];
    }

    if (empty($clientId)) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized: missing client_id parameter/header']));
    }
    return $clientId;
}

// ── Session-based Portal Auth ──

/**
 * Start or resume session for portal pages.
 */
function portal_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Guard: redirect to login if not authenticated.
 * Include this at the top of every portal page.
 */
function requirePortalLogin(): void {
    portal_session_start();
    if (empty($_SESSION['portal_client_id']) || empty($_SESSION['portal_user_id'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Get current portal session data.
 */
function getPortalSession(): array {
    portal_session_start();
    return [
        'client_id' => $_SESSION['portal_client_id'] ?? '',
        'user_id'   => $_SESSION['portal_user_id']   ?? 0,
        'username'      => $_SESSION['portal_username']   ?? '',
        'role'          => $_SESSION['portal_role']       ?? '',
        'is_superadmin' => $_SESSION['portal_is_superadmin'] ?? false,
        'cameras'       => $_SESSION['portal_cameras']    ?? [],
        'theme'         => $_SESSION['portal_theme']      ?? 'light',
    ];
}

// ── Permission Checking ──

/**
 * Check if a role has a specific permission for a client.
 * Superadmin (ADMIN access_level) always returns true.
 */
function hasPermission(PDO $pdo, string $clientId, string $role, string $permission): bool {
    // Read superadmin flag directly from session — DO NOT use global $session
    // which may not yet be set depending on include order.
    $isSuperadmin = !empty($_SESSION['portal_is_superadmin']);

    if ($isSuperadmin) return true;

    // Check client-specific permission first
    $stmt = $pdo->prepare("
        SELECT granted FROM portal_role_permissions
        WHERE client_id = ? AND role = ? AND permission = ?
    ");
    $stmt->execute([$clientId, $role, $permission]);
    $row = $stmt->fetch();

    if ($row) return (bool)$row['granted'];

    // Fall back to default permissions
    $stmt = $pdo->prepare("
        SELECT granted FROM portal_role_permissions
        WHERE client_id = '__DEFAULT__' AND role = ? AND permission = ?
    ");
    $stmt->execute([$role, $permission]);
    $row = $stmt->fetch();

    return $row ? (bool)$row['granted'] : false;
}

/**
 * Require a specific permission or show an error.
 */
function requirePermission(PDO $pdo, string $clientId, string $role, string $permission): void {
    if (!hasPermission($pdo, $clientId, $role, $permission)) {
        if (headers_sent()) {
            // Headers already sent = mid-page or AJAX response — return JSON error.
            // The calling JS will display an appropriate inline error message.
            echo json_encode(['error' => 'Access denied: missing permission ' . $permission]);
            exit;
        } else {
            // Page-level navigation — redirect to dashboard.
            // Dashboard renders only what the user's role permits, so it handles
            // the restricted state gracefully without a jarring error screen.
            header('Location: dashboard.php');
            exit;
        }
    }
}

/**
 * Get all permissions for a given role under a client.
 * Returns associative array: ['VIEW_SNAPSHOTS' => true, ...]
 */
function getAllPermissions(PDO $pdo, string $clientId, string $role): array {
    $normRole = strtolower(str_replace('_', '', $role));
    if ($normRole === 'superadmin') {
        // SA has everything
        $allPerms = [
            'VIEW_SNAPSHOTS','VIEW_VIDEOS','VIEW_LOGS','DOWNLOAD_FILES',
            'DOWNLOAD_SQL','DELETE_RECORDS','EDIT_RECORDS','MANAGE_USERS','MANAGE_ROLES',
            'MANAGE_MAINTENANCE','VIEW_MAINTENANCE'
        ];
        return array_fill_keys($allPerms, true);
    }

    $permissions = [];

    // Load defaults first
    $stmt = $pdo->prepare("SELECT permission, granted FROM portal_role_permissions WHERE client_id = '__DEFAULT__' AND role = ?");
    $stmt->execute([$role]);
    foreach ($stmt->fetchAll() as $row) {
        $permissions[$row['permission']] = (bool)$row['granted'];
    }

    // Override with client-specific
    $stmt = $pdo->prepare("SELECT permission, granted FROM portal_role_permissions WHERE client_id = ? AND role = ?");
    $stmt->execute([$clientId, $role]);
    foreach ($stmt->fetchAll() as $row) {
        $permissions[$row['permission']] = (bool)$row['granted'];
    }

    return $permissions;
}


/**
 * Get the real client IP address (handles common proxy headers).
 */
function getClientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return '0.0.0.0';
}

function portalSessionActiveWindowSeconds(): int {
    return 45;
}

function portalEnsureLoginSessionActivityColumn(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'portal_login_sessions'
              AND COLUMN_NAME = 'last_seen_at'
        ");
        $stmt->execute();
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("
                ALTER TABLE portal_login_sessions
                ADD COLUMN last_seen_at DATETIME DEFAULT NULL
                    COMMENT 'Last heartbeat received from this browser session'
                AFTER login_at
            ");
        }
    } catch (Throwable $e) {
        // Activity tracking is optional; do not break portal pages if the
        // migration cannot run under this database user.
    }
}

function portalTouchLoginSession(PDO $pdo): void {
    portal_session_start();
    $sessionId = (int)($_SESSION['portal_login_session_id'] ?? 0);
    $clientId = trim((string)($_SESSION['portal_client_id'] ?? ''));
    if ($sessionId <= 0 || $clientId === '') {
        return;
    }

    portalEnsureLoginSessionActivityColumn($pdo);
    try {
        $stmt = $pdo->prepare("
            UPDATE portal_login_sessions
            SET last_seen_at = NOW()
            WHERE id = ? AND client_id = ? AND is_active = 1
        ");
        $stmt->execute([$sessionId, $clientId]);
    } catch (Throwable $e) {
    }
}

function portalExpireStaleLoginSessions(PDO $pdo, string $clientId): void {
    $clientId = trim($clientId);
    if ($clientId === '') {
        return;
    }

    portalEnsureLoginSessionActivityColumn($pdo);
    try {
        $stmt = $pdo->prepare("
            UPDATE portal_login_sessions
            SET is_active = 0,
                logout_at = COALESCE(last_seen_at, login_at, NOW())
            WHERE client_id = ?
              AND is_active = 1
              AND TIMESTAMPDIFF(SECOND, COALESCE(last_seen_at, login_at), NOW()) > ?
        ");
        $stmt->execute([$clientId, portalSessionActiveWindowSeconds()]);
    } catch (Throwable $e) {
    }
}

function portalCloseOtherLoginSessions(PDO $pdo, string $clientId, string $employeeId): void {
    $clientId = trim($clientId);
    $employeeId = trim($employeeId);
    if ($clientId === '' || $employeeId === '') {
        return;
    }

    portalEnsureLoginSessionActivityColumn($pdo);
    try {
        $stmt = $pdo->prepare("
            UPDATE portal_login_sessions
            SET is_active = 0,
                logout_at = COALESCE(last_seen_at, NOW()),
                last_seen_at = COALESCE(last_seen_at, NOW())
            WHERE client_id = ?
              AND employee_id = ?
              AND is_active = 1
        ");
        $stmt->execute([$clientId, $employeeId]);
    } catch (Throwable $e) {
    }
}

/**
 * Log an action to the portal audit trail (with IP + UA).
 */
function logAudit(PDO $pdo, ?string $clientId, string $performedBy, string $action, ?string $detail = null): void {
    $ip = getClientIp();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    try {
        $stmt = $pdo->prepare("INSERT INTO portal_audit_logs (client_id, performed_by, action, detail, ip_address, user_agent) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$clientId, $performedBy, $action, $detail, $ip, $ua]);
        logger_info('Application change recorded', [
            'event_id' => bin2hex(random_bytes(8)),
            'audit_action' => $action,
            'client_id' => $clientId,
            'performed_by' => $performedBy,
            'detail' => $detail,
            'ip' => $ip,
        ], __FILE__, __LINE__);
    } catch (Throwable $e) {
        // Fallback: insert without new columns if migration hasn't run yet
        try {
            $stmt = $pdo->prepare("INSERT INTO portal_audit_logs (client_id, performed_by, action, detail) VALUES (?,?,?,?)");
            $stmt->execute([$clientId, $performedBy, $action, $detail]);
            logger_info('Application change recorded', [
                'event_id' => bin2hex(random_bytes(8)),
                'audit_action' => $action,
                'client_id' => $clientId,
                'performed_by' => $performedBy,
                'detail' => $detail,
                'ip' => $ip,
                'audit_storage' => 'legacy_columns',
            ], __FILE__, __LINE__);
        } catch (Throwable $fallbackError) {
            logger_exception('Application change audit failed', $fallbackError, [
                'audit_action' => $action,
                'client_id' => $clientId,
                'performed_by' => $performedBy,
            ]);
            throw $fallbackError;
        }
    }
}

/**
 * Log a structured administrative action to the dedicated `society_audit_log` table (Requirement 16).
 *
 * @param PDO $pdo Database connection
 * @param int $societyId Target society ID
 * @param string $actionType e.g., SOCIETY_CREATED, SOCIETY_UPDATED, USER_ADDED, USER_REMOVED, SOCIETY_SWITCHED, ROLE_CHANGED
 * @param array $options Optional associative array:
 *                      - actor_user_id: int|null
 *                      - actor_name: string|null
 *                      - target_id: string|null
 *                      - target_type: string|null
 *                      - old_values: array|string|null
 *                      - new_values: array|string|null
 *                      - ip_address: string|null
 *                      - user_agent: string|null
 * @return int Inserted ID or 0 on error
 */
function logSocietyAudit(PDO $pdo, int $societyId, string $actionType, array $options = []): int {
    if ($societyId <= 0 || empty($actionType)) {
        error_log(sprintf('[logSocietyAudit] Missing or invalid parameters: society_id=%d, action_type="%s"', $societyId, $actionType));
        return 0;
    }

    $actorUserId = $options['actor_user_id'] ?? ($_SESSION['portal_user_id'] ?? $_SESSION['portal_global_user_id'] ?? null);
    if ($actorUserId !== null) {
        $actorUserId = (int)$actorUserId;
    }

    $actorName = $options['actor_name'] ?? ($_SESSION['portal_username'] ?? $_SESSION['portal_employee_id'] ?? (!empty($_SESSION['indsac_admin']) ? 'Super Admin' : 'System'));
    $targetId = isset($options['target_id']) ? (string)$options['target_id'] : null;
    $targetType = isset($options['target_type']) ? (string)$options['target_type'] : null;

    $oldValues = null;
    if (isset($options['old_values'])) {
        $oldValues = is_array($options['old_values']) 
            ? json_encode($options['old_values'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) 
            : (string)$options['old_values'];
    }

    $newValues = null;
    if (isset($options['new_values'])) {
        $newValues = is_array($options['new_values']) 
            ? json_encode($options['new_values'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) 
            : (string)$options['new_values'];
    }

    $ip = $options['ip_address'] ?? (function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? null));
    $ua = $options['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO society_audit_log 
                (society_id, actor_user_id, actor_name, action_type, target_id, target_type, old_values, new_values, ip_address, user_agent)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $societyId,
            $actorUserId,
            $actorName,
            $actionType,
            $targetId,
            $targetType,
            $oldValues,
            $newValues,
            $ip,
            $ua
        ]);
        logger_info('Society change recorded', [
            'event_id' => bin2hex(random_bytes(8)),
            'society_id' => $societyId,
            'action_type' => $actionType,
            'actor_user_id' => $actorUserId,
            'target_id' => $targetId,
            'target_type' => $targetType,
        ], __FILE__, __LINE__);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        logger_exception('Society change audit failed', $e, [
            'society_id' => $societyId,
            'action_type' => $actionType,
        ]);
        return 0;
    }
}

/**
 * Get the society name for a client ID, with fallbacks.
 */
function get_society_name_by_client_id(PDO $pdo, string $client_id): string {
    // 1. Check official society metadata table first
    try {
        $stmt = $pdo->prepare("SELECT society_name FROM society WHERE legacy_client_id = ? AND status = 'ACTIVE' LIMIT 1");
        $stmt->execute([$client_id]);
        $name = $stmt->fetchColumn();
        if ($name && trim($name) !== '' && trim($name) !== 'Our Society') return trim($name);
    } catch (Throwable $e) {}

    // 2. Check customized maintenance settings
    try {
        $stmt = $pdo->prepare("SELECT society_name FROM maintenance_settings WHERE client_id = ? LIMIT 1");
        $stmt->execute([$client_id]);
        $name = $stmt->fetchColumn();
        if ($name && trim($name) !== '' && trim($name) !== 'Our Society') return trim($name);
    } catch (Throwable $e) {}

    // 3. Fallback to clients table
    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM clients WHERE client_id = ? LIMIT 1");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch();
        if ($client) {
            $cName = trim($client['first_name'] . ' ' . $client['last_name']);
            if ($cName) return $cName;
        }
    } catch (Throwable $e) {}

    // 4. Any name in society or maintenance_settings
    try {
        $stmt = $pdo->prepare("SELECT society_name FROM society WHERE legacy_client_id = ? LIMIT 1");
        $stmt->execute([$client_id]);
        $name = $stmt->fetchColumn();
        if ($name && trim($name) !== '') return trim($name);
    } catch (Throwable $e) {}

    try {
        $stmt = $pdo->prepare("SELECT society_name FROM maintenance_settings WHERE client_id = ? LIMIT 1");
        $stmt->execute([$client_id]);
        $name = $stmt->fetchColumn();
        if ($name && trim($name) !== '') return trim($name);
    } catch (Throwable $e) {}

    return $client_id;
}


/**
 * Convert role names to a clean, user-friendly display string.
 */
function get_friendly_role_name(string $role): string {
    $r = strtolower(str_replace('_', '', $role));
    if ($r === 'superadmin') {
        return 'Super Admin';
    }
    if ($r === 'societyadmin') {
        return 'Society Admin';
    }
    if ($r === 'securitymanager') {
        return 'Security Manager';
    }
    if ($r === 'securityguard') {
        return 'Security Guard';
    }
    if ($r === 'resident') {
        return 'Resident';
    }
    if ($r === 'visitor') {
        return 'Visitor';
    }
    if ($r === 'maintenancestaff') {
        return 'Maintenance Staff';
    }
    if ($r === 'member') {
        return 'Member';
    }
    if ($r === 'tenant') {
        return 'Tenant';
    }
    return ucfirst($role);
}

/**
 * Synchronize and refresh the available societies list for a global user from the database.
 */
function portal_refresh_available_societies(PDO $pdo, int $globalUserId): array {
    if (!DB_CFG_MULTI_SOCIETY_LOGIN_ENABLED || $globalUserId < 1) {
        return $_SESSION['portal_available_societies'] ?? [];
    }
    $mappedMatches = [];
    $seenSocieties = [];
    $mappingStmt = $pdo->prepare("SELECT m.id AS mapping_id, m.society_id, m.role AS mapping_role,
            s.society_name, s.legacy_client_id,
            e.id, e.client_id, e.employee_id, e.full_name, e.role, e.access_level
        FROM user_society_mapping m
        INNER JOIN users u ON u.id = m.user_id
        INNER JOIN society s ON s.id = m.society_id AND s.status = 'ACTIVE'
        INNER JOIN employees e ON e.global_user_id = m.user_id
            AND e.client_id = s.legacy_client_id AND e.status = 'active'
        WHERE m.user_id = ? AND m.status = 'ACTIVE'
        ORDER BY (CASE WHEN u.last_active_society_id IS NOT NULL AND m.society_id = u.last_active_society_id THEN 1 ELSE 0 END) DESC,
                 m.is_default DESC,
                 m.id ASC");
    $mappingStmt->execute([$globalUserId]);
    while ($mapped = $mappingStmt->fetch()) {
        if (isset($seenSocieties[$mapped['society_id']])) continue;
        $seenSocieties[$mapped['society_id']] = true;
        $mappedMatches[] = [
            'type' => 'employee',
            'society_id' => (int)$mapped['society_id'],
            'client_id' => $mapped['client_id'],
            'user_id' => (int)$mapped['id'],
            'employee_id' => $mapped['employee_id'],
            'full_name' => $mapped['full_name'],
            'role' => $mapped['mapping_role'] ?: ($mapped['role'] ?: 'Unassigned'),
            'is_superadmin' => strtoupper($mapped['access_level'] ?? '') === 'ADMIN' || strtoupper($mapped['mapping_role'] ?? '') === 'SUPER_ADMIN',
            'society_name' => $mapped['society_name']
        ];
    }
    if (!empty($mappedMatches)) {
        $_SESSION['portal_available_societies'] = $mappedMatches;
    }
    return $_SESSION['portal_available_societies'] ?? [];
}

