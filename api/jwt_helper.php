<?php
/**
 * EyeSense Cloud - JWT Helper
 * Generates and verifies HMAC-SHA256 (HS256) JWT tokens for REST and Edge-Worker consumers.
 */

require_once __DIR__ . '/portal_config.php';

if (!defined('JWT_DEFAULT_TTL')) {
    define('JWT_DEFAULT_TTL', 86400); // 24 hours
}

/**
 * Base64Url encode helper.
 */
function jwt_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64Url decode helper.
 */
function jwt_base64url_decode(string $data): string {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    return (string)base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Issue a standard JWT containing exactly {userId, societyId, role} claims.
 *
 * @param int $userId
 * @param int $societyId
 * @param string $role
 * @param int $ttlSeconds (default 86400)
 * @param string|null $secret (falls back to DB_CFG_LICENSE_API_SECRET)
 * @return string JWT token string
 */
function jwt_issue_society_token(int $userId, int $societyId, string $role, int $ttlSeconds = JWT_DEFAULT_TTL, ?string $secret = null): string {
    $secret = $secret ?: DB_CFG_LICENSE_API_SECRET;
    $now = time();

    $header = [
        'typ' => 'JWT',
        'alg' => 'HS256'
    ];

    $payload = [
        'userId'    => $userId,
        'societyId' => $societyId,
        'role'      => $role,
        'iat'       => $now,
        'exp'       => $now + $ttlSeconds
    ];

    $headerEncoded = jwt_base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $payloadEncoded = jwt_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));

    $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true);
    $signatureEncoded = jwt_base64url_encode($signature);

    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

/**
 * Verify and parse a JWT token.
 *
 * @param string $token
 * @param string|null $secret (falls back to DB_CFG_LICENSE_API_SECRET)
 * @return array|null Returns parsed payload array or null if invalid/expired.
 */
function jwt_verify_society_token(string $token, ?string $secret = null): ?array {
    $secret = $secret ?: DB_CFG_LICENSE_API_SECRET;
    $parts = explode('.', trim($token));
    if (count($parts) !== 3) {
        return null;
    }

    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

    $expectedSignature = jwt_base64url_encode(hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true));
    if (!hash_equals($expectedSignature, $signatureEncoded)) {
        return null;
    }

    $payloadJson = jwt_base64url_decode($payloadEncoded);
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) {
        return null;
    }

    // Verify expiration
    if (isset($payload['exp']) && time() > (int)$payload['exp']) {
        return null;
    }

    // Ensure required claims exist
    if (!isset($payload['userId']) || !isset($payload['societyId']) || !isset($payload['role'])) {
        return null;
    }

    return $payload;
}
