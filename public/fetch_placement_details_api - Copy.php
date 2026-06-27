<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

$host = '172.20.6.86';
$db = 'lakshya';
$user = 'arihant';
$pass = 'Arihant#Lkshya2026!7Q';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]);
    exit;
}

$placements = [];
$sql = "SELECT * FROM company_placed_students";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $placements[] = $row;
    }
}

$internships = [];
$sql2 = "SELECT * FROM internship_placed_students";
$result2 = $conn->query($sql2);
if ($result2 && $result2->num_rows > 0) {
    while($row = $result2->fetch_assoc()) {
        $internships[] = $row;
    }
}

echo json_encode([
    "success" => true,
    "placements" => $placements,
    "internships" => $internships
]);

$conn->close();
?>
