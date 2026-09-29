<?php
/**
 * Database Configuration & PDO Singleton Wrapper
 * Now merged with the main EyeSense portal database.
 */

// Load the main portal configuration and database connection
require_once __DIR__ . '/../../../portal_config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Use the global connection from the main portal
        $this->pdo = get_indsac_db();
        
        // Validate that the merged tables actually exist to prevent silent failures
        try {
            $tableCount = $this->pdo->query("SHOW TABLES LIKE 'hostels'")->rowCount();
            if ($tableCount === 0) {
                // Display error similar to the previous implementation
                die("<div style='font-family: Arial, sans-serif; padding: 25px; background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; border-radius: 12px; max-width: 650px; margin: 50px auto; box-shadow: 0 10px 25px rgba(0,0,0,0.1);'>
                        <h3 style='margin-top: 0; color: #7f1d1d; display: flex; align-items: center; gap: 10px;'>
                            âš ï¸  Hostel Tables Missing in Main Database
                        </h3>
                        <p style='font-size: 15px; margin-bottom: 15px;'>The main database is connected, but the <strong>Hostel Management</strong> tables are missing.</p>
                        <h4 style='margin: 15px 0 8px; color: #7f1d1d;'>How to fix right now:</h4>
                        <ol style='margin: 0; padding-left: 20px; line-height: 1.7; font-size: 14px;'>
                            <li>Run the <code>api/db_setup.php</code> script to automatically create all missing tables in the main database.</li>
                            <li>Or manually import the updated schema from <code>indsac_schema.sql</code>.</li>
                        </ol>
                     </div>");
            }
        } catch (Exception $e) {
            // Ignore error if it fails to check
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    // Prevent cloning and un-serialization for singleton integrity
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper function to quickly obtain the PDO connection instance
 * Usage: $pdo = get_db_connection();
 */
function get_db_connection() {
    return Database::getInstance()->getConnection();
}
