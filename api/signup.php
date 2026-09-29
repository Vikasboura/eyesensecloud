<?php
/**
 * EyeSense — Public Signup API
 * POST /api/signup.php
 * Form Fields: fullname, mobile, email, password
 */

require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';

portal_session_start();
// Clear any prior logged-in session so the user starts fresh
$_SESSION = [];
if (session_id()) {
    session_destroy();
}

header('Content-Type: application/json; charset=utf-8');

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // If not JSON, try POST parameters
    $input = $_POST;
}

$fullname = trim($input['fullname'] ?? '');
$mobile   = trim($input['mobile'] ?? '');
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

// Validate
if (!$fullname || !$mobile || !$email || !$password) {
    echo json_encode(['success' => false, 'error' => 'All fields are required.']);
    exit;
}

if (strlen($fullname) < 2) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid name.']);
    exit;
}

if (!preg_match('/^\d{10}$/', $mobile)) {
    echo json_encode(['success' => false, 'error' => 'Mobile number must be exactly 10 digits.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
    exit;
}

try {
    $pdo = get_indsac_db();

    // Ensure `register` table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `register` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `fullname` VARCHAR(255) NOT NULL,
            `mobile` VARCHAR(20) NOT NULL,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Check duplicate email in `register` table
    $stmt = $pdo->prepare("SELECT id FROM register WHERE LOWER(email) = ?");
    $stmt->execute([strtolower($email)]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Email is already registered.']);
        exit;
    }

    // Check duplicate email in `employees` table too (just to be safe)
    $stmt2 = $pdo->prepare("SELECT id FROM employees WHERE LOWER(email) = ?");
    $stmt2->execute([strtolower($email)]);
    if ($stmt2->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Email is already registered.']);
        exit;
    }

    // Hash password and insert
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmtInsert = $pdo->prepare("
        INSERT INTO register (fullname, mobile, email, password)
        VALUES (?, ?, ?, ?)
    ");
    $stmtInsert->execute([$fullname, $mobile, $email, $hashedPassword]);

    echo json_encode(['success' => true, 'message' => 'You have signed up successfully.']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
