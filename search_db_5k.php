<?php
require 'config/bootstrap.php';
try {
    $db = getDB();
    $stmt = $db->query("SELECT sl_no, name, ctc_in_lakhs FROM company_placed_students WHERE ctc_in_lakhs LIKE '%LPA%' AND ctc_in_lakhs LIKE '%stipend%'");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
