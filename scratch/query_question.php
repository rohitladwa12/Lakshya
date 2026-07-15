<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$search = '%shirt%';
$tables = ['aptitude_questions', 'manual_aptitude_questions', 'nqt_aptitude_questions'];

foreach ($tables as $table) {
    try {
        if ($table === 'manual_aptitude_questions') {
            $stmt = $db->prepare("SELECT * FROM $table WHERE question_text LIKE ?");
        } else {
            $stmt = $db->prepare("SELECT * FROM $table WHERE question LIKE ?");
        }
        $stmt->execute([$search]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Table: $table found " . count($rows) . " rows.\n";
        foreach ($rows as $row) {
            print_r($row);
        }
    } catch (Exception $e) {
        echo "Error on $table: " . $e->getMessage() . "\n";
    }
}
