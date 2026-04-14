<?php
require_once 'c:/xampp/htdocs/Lakshya/config/database.php';
$mysqli = getDB('default');
$search = 'GMIT23AI80';
echo "Searching for '$search' in all columns of users table...\n";
try {
    $res = $mysqli->query("DESCRIBE users");
    $cols = [];
    while($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $cols[] = $row['Field'];
    }
    
    foreach($cols as $col) {
        $stmt = $mysqli->prepare("SELECT * FROM users WHERE `$col` = ?");
        $stmt->execute([$search]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "Found in column '$col':\n";
            print_r($row);
            exit;
        }
    }
    echo "Not found in any column of users table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
