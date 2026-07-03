<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

echo "=== DISTINCT USER_GROUP ===\n";
$stmt = $db->query("SELECT DISTINCT USER_GROUP FROM users");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
