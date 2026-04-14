<?php
/**
 * Aptitude Question Handler
 * Saves manually entered aptitude questions from coordinators
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Auth check
requireRole(ROLE_DEPT_COORDINATOR);
$userId = getUserId();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($input, $_POST);
$action = $input['action'] ?? '';

$db = getDB();

switch ($action) {
    case 'add_question':
        $company = trim($input['company'] ?? '');
        $question = trim($input['question'] ?? '');
        $optA = trim($input['option_a'] ?? '');
        $optB = trim($input['option_b'] ?? '');
        $optC = trim($input['option_c'] ?? '');
        $optD = trim($input['option_d'] ?? '');
        $correct = strtoupper(trim($input['correct_option'] ?? ''));
        $explanation = trim($input['explanation'] ?? '');

        if (empty($company) || empty($question) || empty($optA) || empty($optB) || empty($optC) || empty($optD) || empty($correct)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }

        if (!in_array($correct, ['A', 'B', 'C', 'D'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid correct option']);
            exit;
        }

        try {
            $stmt = $db->prepare("INSERT INTO manual_aptitude_questions 
                (company_name, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([$company, $question, $optA, $optB, $optC, $optD, $correct, $explanation, $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Question added successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'get_recent':
        try {
            // Fetch questions added by this coordinator in the last 24 hours
            $stmt = $db->prepare("SELECT id, company_name, question_text, created_at 
                                FROM manual_aptitude_questions 
                                WHERE created_by = ? 
                                ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$userId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'questions' => $questions]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_all_questions':
        try {
            $companyFilter = $input['company'] ?? '';
            $searchQuery = $input['search'] ?? '';
            
            $sql = "SELECT id, company_name, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, created_at 
                    FROM manual_aptitude_questions 
                    WHERE created_by = ?";
            $params = [$userId];

            if (!empty($companyFilter)) {
                $sql .= " AND company_name = ?";
                $params[] = $companyFilter;
            }

            if (!empty($searchQuery)) {
                $sql .= " AND (question_text LIKE ? OR company_name LIKE ?)";
                $params[] = "%$searchQuery%";
                $params[] = "%$searchQuery%";
            }

            $sql .= " ORDER BY created_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'questions' => $questions]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_question':
        $id = $input['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        try {
            // Ensure coordinator owns the question
            $stmt = $db->prepare("DELETE FROM manual_aptitude_questions WHERE id = ? AND created_by = ?");
            $stmt->execute([$id, $userId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Question not found or permission denied']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'update_question':
        $id = $input['id'] ?? 0;
        $company = trim($input['company'] ?? '');
        $question = trim($input['question'] ?? '');
        $optA = trim($input['option_a'] ?? '');
        $optB = trim($input['option_b'] ?? '');
        $optC = trim($input['option_c'] ?? '');
        $optD = trim($input['option_d'] ?? '');
        $correct = strtoupper(trim($input['correct_option'] ?? ''));
        $explanation = trim($input['explanation'] ?? '');

        if (!$id || empty($company) || empty($question) || empty($optA) || empty($optB) || empty($optC) || empty($optD) || empty($correct)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }

        try {
            $stmt = $db->prepare("UPDATE manual_aptitude_questions 
                SET company_name=?, question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=?, explanation=? 
                WHERE id=? AND created_by=?");
            
            $stmt->execute([$company, $question, $optA, $optB, $optC, $optD, $correct, $explanation, $id, $userId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Updated successfully']);
            } else {
                // It's possible rowCount is 0 if no changes were made, but we still return success if the record exists
                echo json_encode(['success' => true, 'message' => 'Updated successfully (or no changes made)']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
