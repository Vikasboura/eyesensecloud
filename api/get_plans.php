<?php
/**
 * ============================================
 * INDSAC GET PLANS API
 * ============================================
 * Returns all active licensing plans from the database.
 * Called by the EyeSense client to populate the plan selector.
 *
 * ---- REQUEST (GET) ----
 *   No parameters required.
 *
 * ---- RESPONSE ----
 * {
 *   "success": true,
 *   "plans": [
 *     { "plan_code": "TRIAL", "name": "Free Trial", "days": 14, "price": "0.00", "camera_limit": 0 },
 *     ...
 *   ]
 * }
 */

require_once __DIR__ . '/db_setup.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $db = get_license_db();

    $stmt = $db->query("
        SELECT plan_code, name, days, price, camera_limit
        FROM plans
        WHERE is_active = 1
        ORDER BY price ASC
    ");

    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format price as string for JSON consistency
    foreach ($plans as &$p) {
        $p['price'] = number_format((float)$p['price'], 2, '.', '');
        $p['days'] = (int)$p['days'];
        $p['camera_limit'] = (int)$p['camera_limit'];
    }

    echo json_encode([
        'success' => true,
        'plans'   => $plans,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Server error: ' . $e->getMessage()
    ]);
}
