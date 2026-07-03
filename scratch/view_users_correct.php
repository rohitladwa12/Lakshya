<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

echo "=== COORDINATORS ===\n";
$stmt = $db->query("SELECT SL_NO, USER_NAME, PASSWORD, USER_GROUP, ID, NAME FROM users WHERE USER_GROUP = 'COORDINATOR' OR USER_GROUP LIKE '%COOR%' LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "=== STUDENTS ===\n";
$stmt = $db->query("SELECT SL_NO, USER_NAME, PASSWORD, USER_GROUP, ID, NAME FROM users WHERE USER_GROUP = 'STUDENT' OR USER_GROUP LIKE '%STUD%' LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
