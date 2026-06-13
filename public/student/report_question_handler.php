<?php
ob_start();
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($input, $_POST);

$testType = $input['test_type'] ?? '';
$testId = $input['test_id'] ?? '';
$questionText = trim($input['question_text'] ?? '');
$options = $input['options'] ?? null;
$correctAnswer = $input['correct_answer'] ?? null;
$userAnswer = $input['user_answer'] ?? null;
$issueType = $input['issue_type'] ?? '';
$comment = trim($input['comment'] ?? '');

if (empty($testType) || empty($questionText) || empty($issueType)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing required fields: test_type, question_text, issue_type.']);
    exit;
}

try {
    $db = getDB();
    $studentId = getUsername();
    $studentName = getFullName();

    // Encode options if they are an array
    if (is_array($options)) {
        $options = json_encode($options);
    }

    $stmt = $db->prepare("
        INSERT INTO reported_questions (
            student_id, student_name, test_type, test_id, question_text, 
            options, correct_answer, user_answer, issue_type, comment, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->execute([
        $studentId,
        $studentName,
        $testType,
        $testId,
        $questionText,
        $options,
        $correctAnswer,
        $userAnswer,
        $issueType,
        $comment
    ]);

    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Question issue report submitted successfully!']);
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
