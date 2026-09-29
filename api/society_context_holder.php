<?php
/**
 * EyeSense Cloud - Society Context Holder
 * Request-scoped context service providing current society, user, and role context
 * across all endpoints and services during a request lifecycle.
 */

require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/jwt_helper.php';

class SocietyContextHolder {
    private static ?int $userId = null;
    private static ?int $societyId = null;
    private static ?string $role = null;
    private static ?string $legacyClientId = null;
    private static bool $initialized = false;

    /**
     * Explicitly set the request context.
     */
    public static function set(int $userId, int $societyId, string $role, ?string $legacyClientId = null): void {
        self::$userId = $userId;
        self::$societyId = $societyId;
        self::$role = $role;
        self::$legacyClientId = $legacyClientId;
        self::$initialized = true;
    }

    /**
     * Get the currently active society ID.
     */
    public static function getCurrentSocietyId(): ?int {
        return self::$societyId;
    }

    /**
     * Set/update the current society ID for this request.
     */
    public static function setCurrentSocietyId(int $societyId): void {
        self::$societyId = $societyId;
    }

    /**
     * Get the current user ID.
     */
    public static function getCurrentUserId(): ?int {
        return self::$userId;
    }

    /**
     * Set/update the current user ID.
     */
    public static function setCurrentUserId(int $userId): void {
        self::$userId = $userId;
    }

    /**
     * Get the current user role.
     */
    public static function getCurrentRole(): ?string {
        return self::$role;
    }

    /**
     * Set/update the current user role.
     */
    public static function setCurrentRole(string $role): void {
        self::$role = $role;
    }

    /**
     * Get the resolved legacy client ID (if available for legacy table queries).
     * If not already cached, lazily resolves from DB using the current society ID.
     */
    public static function getLegacyClientId(?PDO $pdo = null): ?string {
        if (self::$legacyClientId === null && self::$societyId !== null && self::$societyId > 0) {
            try {
                $db = $pdo ?: (function_exists('get_indsac_db') ? get_indsac_db() : null);
                if ($db instanceof PDO) {
                    $stmt = $db->prepare('SELECT legacy_client_id FROM society WHERE id = ? LIMIT 1');
                    $stmt->execute([self::$societyId]);
                    self::$legacyClientId = $stmt->fetchColumn() ?: null;
                }
            } catch (Throwable $e) {}
        }
        return self::$legacyClientId;
    }

    /**
     * Set the legacy client ID.
     */
    public static function setLegacyClientId(?string $legacyClientId): void {
        self::$legacyClientId = $legacyClientId;
    }

    /**
     * Check if context is initialized.
     */
    public static function isInitialized(): bool {
        return self::$initialized;
    }

    /**
     * Reset and clear context (critical for tests, worker runtimes, or request teardown).
     */
    public static function clear(): void {
        self::$userId = null;
        self::$societyId = null;
        self::$role = null;
        self::$legacyClientId = null;
        self::$initialized = false;
    }

    /**
     * Auto-initialize context from request headers (JWT Bearer) or active PHP session.
     *
     * @param PDO|null $pdo Optional PDO connection to resolve legacy_client_id if missing
     * @return bool True if context was resolved and populated, false otherwise
     */
    public static function initFromRequest(?PDO $pdo = null): bool {
        if (self::$initialized) {
            return true;
        }

        // 1. Try Bearer JWT Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $claims = jwt_verify_society_token($matches[1]);
            if ($claims) {
                $societyId = (int)$claims['societyId'];
                $userId = (int)$claims['userId'];
                $role = (string)$claims['role'];
                $legacyClientId = null;

                if ($societyId > 0) {
                    try {
                        $db = $pdo ?: (function_exists('get_indsac_db') ? get_indsac_db() : null);
                        if ($db instanceof PDO) {
                            $stmt = $db->prepare('SELECT legacy_client_id FROM society WHERE id = ? LIMIT 1');
                            $stmt->execute([$societyId]);
                            $legacyClientId = $stmt->fetchColumn() ?: null;
                        }
                    } catch (Throwable $e) {}
                }

                self::set($userId, $societyId, $role, $legacyClientId);
                return true;
            }
        }

        // 2. Fallback to PHP Session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!empty($_SESSION['portal_user_id'])) {
            $userId = (int)$_SESSION['portal_user_id'];
            $societyId = !empty($_SESSION['portal_current_society_id']) ? (int)$_SESSION['portal_current_society_id'] : 0;
            $role = (string)($_SESSION['portal_role'] ?? '');
            $legacyClientId = !empty($_SESSION['portal_client_id']) ? (string)$_SESSION['portal_client_id'] : null;

            // If societyId is not in session but legacyClientId is known, resolve from society table
            if ($societyId <= 0 && !empty($legacyClientId)) {
                try {
                    $db = $pdo ?: (function_exists('get_indsac_db') ? get_indsac_db() : null);
                    if ($db instanceof PDO) {
                        $stmt = $db->prepare('SELECT id FROM society WHERE legacy_client_id = ? LIMIT 1');
                        $stmt->execute([$legacyClientId]);
                        $resolved = (int)$stmt->fetchColumn();
                        if ($resolved > 0) {
                            $societyId = $resolved;
                            $_SESSION['portal_current_society_id'] = $societyId;
                        }
                    }
                } catch (Throwable $e) {}
            }

            if ($societyId > 0 || !empty($legacyClientId)) {
                self::set($userId, $societyId, $role, $legacyClientId);
                return true;
            }
        }

        return false;
    }
}
