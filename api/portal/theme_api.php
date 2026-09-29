<?php
/**
 * Theme API — EyeSense Cloud Portal
 * Stores user theme preference in user_preferences table and session.
 */
require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';

portal_session_start();
$pdo = get_indsac_db();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

if ($action === 'set_theme') {
    $theme = $input['theme'] ?? 'light';
    if (!in_array($theme, ['light', 'dark'])) {
        $theme = 'light';
    }
    
    $_SESSION['portal_theme'] = $theme;
    
    $clientId = $_SESSION['portal_client_id'] ?? '';
    $userId = $_SESSION['portal_user_id'] ?? 0;
    
    if ($clientId && $userId) {
        try {
            // Ensure table exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_preferences (
                    client_id VARCHAR(50) NOT NULL,
                    user_id INT NOT NULL,
                    theme VARCHAR(10) DEFAULT 'light',
                    PRIMARY KEY (client_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            
            // Insert or update
            $stmt = $pdo->prepare("
                INSERT INTO user_preferences (client_id, user_id, theme) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE theme = VALUES(theme)
            ");
            $stmt->execute([$clientId, $userId, $theme]);
        } catch (Throwable $e) {
            // Ignore DB errors if migration fails, session is already updated
        }
    }
    
    echo json_encode(['success' => true, 'theme' => $theme]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
