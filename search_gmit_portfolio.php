<?php
require_once __DIR__ . '/config/bootstrap.php';

try {
    $db = getDB();
    
    echo "=== ALL PORTFOLIO ITEMS FOR GMIT23AI12 ===\n";
    $stmt = $db->prepare("SELECT id, student_id, category, title, sub_title, created_at, updated_at FROM student_portfolio WHERE student_id = ?");
    $stmt->execute(['GMIT23AI12']);
    $rows = $stmt->fetchAll();
    print_r($rows);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
