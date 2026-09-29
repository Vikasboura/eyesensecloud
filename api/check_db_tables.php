<?php
require 'api/db_setup.php';
try {
    $db = get_license_db();
    
    echo "--- CLIENTS ---\n";
    $clients = $db->query("SELECT * FROM clients")->fetchAll();
    foreach ($clients as $c) {
        print_r($c);
    }
    
    echo "\n--- PREMISES ---\n";
    $premises = $db->query("SELECT * FROM premises")->fetchAll();
    foreach ($premises as $p) {
        print_r($p);
    }
    
} catch(Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
