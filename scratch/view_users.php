<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

echo "=== FIRST 5 USERS ===\n";
$stmt = $db->query("SELECT id, username, email, role, password FROM users LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
