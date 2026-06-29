<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();
$studentId = 'GMIT23EC15';

echo "=== UNIFIED AI ASSESSMENTS ===\n";
$stmt = $db->prepare("SELECT id, assessment_type, company_name, score, status, completed_at FROM unified_ai_assessments WHERE student_id = ? OR usn = ?");
$stmt->execute([$studentId, $studentId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

echo "=== STUDENT APTITUDE ATTEMPTS ===\n";
$stmt = $db->prepare("SELECT id, task_id, score, total_questions, time_spent, created_at FROM student_aptitude_attempts WHERE student_id = ?");
$stmt->execute([$studentId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
