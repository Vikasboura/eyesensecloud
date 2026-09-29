<?php
/**
 * Subscription Request Handler
 * Automatically ensures the `subscription_requests` table exists and stores plan subscription requests.
 * 
 * Location: api/subscribe.php
 */

// Disable error display in output, but log errors
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set JSON response header & CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Please use POST.']);
    exit;
}

try {
    // Include the portal configuration
    $configPath = __DIR__ . '/portal_config.php';
    if (!file_exists($configPath)) {
        $configPath = __DIR__ . '/../portal_config.php';
    }
    
    if (!file_exists($configPath)) {
        throw new Exception('Configuration file not found. Please ensure portal_config.php exists.');
    }
    
    require_once $configPath;
    
    // Get database connection
    if (!function_exists('get_indsac_db')) {
        throw new Exception('get_indsac_db() function not found. Please check portal_config.php.');
    }
    
    $pdo = get_indsac_db();

    // 1. Ensure the subscription_requests table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscription_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            plan_name VARCHAR(100) NOT NULL,
            plan_price VARCHAR(50) NOT NULL,
            status VARCHAR(50) DEFAULT 'Pending',
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolution TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Get and validate input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // If JSON decode fails, try using $_POST
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }

    // Extract and trim values
    $full_name  = trim($input['full_name'] ?? $input['name'] ?? $input['customerName'] ?? '');
    $email      = trim($input['email'] ?? $input['customerEmail'] ?? '');
    $phone      = trim($input['phone'] ?? $input['customerPhone'] ?? '');
    $plan_name  = trim($input['plan_name'] ?? $input['plan'] ?? '');
    $plan_price = trim($input['plan_price'] ?? $input['price'] ?? $input['amount'] ?? '');

    // Format plan_price if numeric
    if (is_numeric($plan_price)) {
        $plan_price = '₹' . number_format((float)$plan_price);
    }

    // 3. Validate required fields
    $errors = [];

    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($full_name) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    }

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'Phone number must be exactly 10 digits.';
    }

    if (empty($plan_name)) {
        $errors[] = 'Plan name is required.';
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => implode(' ', $errors),
            'errors' => $errors
        ]);
        exit;
    }

    // 4. Check for duplicate submissions within 24 hours
    $checkStmt = $pdo->prepare("
        SELECT id, timestamp 
        FROM subscription_requests 
        WHERE email = ? AND phone = ? AND plan_name = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        LIMIT 1
    ");
    $checkStmt->execute([$email, $phone, $plan_name]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'You have already submitted a subscription request for this plan within the last 24 hours. Our team will contact you shortly.'
        ]);
        exit;
    }

    // 5. Insert submission into database
    $stmt = $pdo->prepare("
        INSERT INTO subscription_requests (full_name, email, phone, plan_name, plan_price, status)
        VALUES (?, ?, ?, ?, ?, 'Pending')
    ");
    $result = $stmt->execute([$full_name, $email, $phone, $plan_name, $plan_price]);

    if (!$result) {
        throw new Exception('Failed to insert subscription request into database.');
    }

    $insertId = $pdo->lastInsertId();

    // 6. Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Your subscription request has been submitted successfully! Our team will contact you shortly.',
        'id' => $insertId,
        'data' => [
            'full_name'  => $full_name,
            'email'      => $email,
            'phone'      => $phone,
            'plan_name'  => $plan_name,
            'plan_price' => $plan_price
        ]
    ]);

} catch (PDOException $e) {
    error_log('Database error in subscribe.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'A database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log('Error in subscribe.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
