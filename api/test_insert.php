<?php
/**
 * Direct DB insert test (no email, no session)
 * Run: php api/test_insert.php
 */
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';

try {
    $db  = get_license_db();
    $pdo = get_indsac_db();

    // Clean up previous test
    $db->exec("DELETE FROM clients WHERE email='testinsert@example.com'");
    $pdo->exec("DELETE FROM employees WHERE email='testinsert@example.com'");

    $new_cid    = generate_client_id($db);
    $employeeId = $new_cid . '-SA';
    $fullName   = 'Test Insert';
    $pwHash     = generate_werkzeug_password('TestPass123');

    echo "Inserting client: $new_cid\n";

    $db->prepare("INSERT INTO clients (client_id, first_name, last_name, email, mobile, machine_id, sa_username)
                  VALUES (?,?,?,?,?,?,?)")
       ->execute([$new_cid, 'Test', 'Insert', 'testinsert@example.com', '9000000000', '', $employeeId]);
    echo "clients INSERT OK\n";

    $pdo->prepare("INSERT INTO employees (client_id, employee_id, full_name, email, phone, role, access_level, password_hash, status, created_by)
                   VALUES (?,?,?,?,?,'superadmin','ADMIN',?,'active','INDSAC-ADMIN')")
        ->execute([$new_cid, $employeeId, $fullName, 'testinsert@example.com', '9000000000', $pwHash]);
    echo "employees INSERT OK\n";

    echo "Clients total:   " . $db->query('SELECT COUNT(*) FROM clients')->fetchColumn() . "\n";
    echo "Employees total: " . $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn() . "\n";

    // Fetch and verify
    $c = $db->query("SELECT client_id, email FROM clients WHERE email='testinsert@example.com'")->fetch();
    echo "Verified client: " . $c['client_id'] . " / " . $c['email'] . "\n";

    $e = $pdo->query("SELECT employee_id, role, status FROM employees WHERE email='testinsert@example.com'")->fetch();
    echo "Verified employee: " . $e['employee_id'] . " / role=" . $e['role'] . " / status=" . $e['status'] . "\n";

    echo "\n✅ All inserts work correctly!\n";
} catch (Throwable $ex) {
    echo "❌ ERROR: " . $ex->getMessage() . "\n";
    echo $ex->getTraceAsString() . "\n";
}
