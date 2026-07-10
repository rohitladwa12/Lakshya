<?php
require_once __DIR__ . '/config/bootstrap.php';

try {
    $db = getDB();
    
    echo "=== DESCRIBE student_portfolio ===\n";
    $stmt = $db->query("DESCRIBE student_portfolio");
    print_r($stmt->fetchAll());
    
    echo "\n=== SEARCH student_portfolio FOR 23AI12 ===\n";
    $stmt = $db->query("SELECT * FROM student_portfolio WHERE student_id LIKE '%23AI12%'");
    print_r($stmt->fetchAll());
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
