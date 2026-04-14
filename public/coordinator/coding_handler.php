<?php
require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: application/json');

// Ensure user is logged in and is a Coordinator
requireRole(ROLE_DEPT_COORDINATOR);

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'add_problem':
        try {
            // Basic validation
            if (empty($input['title']) || empty($input['problem_statement'])) {
                throw new Exception('Title and Problem Statement are required');
            }

            $sql = "INSERT INTO coding_problems (
                title, category, difficulty, problem_statement, 
                constraints, example_input, example_output, 
                concept_explanation, time_complexity, space_complexity
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $input['title'],
                $input['category'] ?? 'General',
                $input['difficulty'] ?? 'Medium',
                $input['problem_statement'],
                $input['constraints'] ?? '',
                $input['example_input'] ?? '',
                $input['example_output'] ?? '',
                $input['concept_explanation'] ?? '',
                $input['time_complexity'] ?? '',
                $input['space_complexity'] ?? ''
            ]);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_recent':
        try {
            $stmt = $db->query("SELECT * FROM coding_problems ORDER BY id DESC LIMIT 5");
            $problems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'problems' => $problems]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_all_problems':
        try {
            $where = [];
            $params = [];
            
            if (!empty($input['search'])) {
                $where[] = "(title LIKE ? OR category LIKE ?)";
                $params[] = '%' . $input['search'] . '%';
                $params[] = '%' . $input['search'] . '%';
            }
            
            if (!empty($input['difficulty'])) {
                $where[] = "difficulty = ?";
                $params[] = $input['difficulty'];
            }

            $sql = "SELECT * FROM coding_problems";
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            $sql .= " ORDER BY id DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $problems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'problems' => $problems]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_problem':
        try {
            if (empty($input['id'])) throw new Exception('Problem ID required');
            
            $stmt = $db->prepare("DELETE FROM coding_problems WHERE id = ?");
            $stmt->execute([$input['id']]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'update_problem':
        try {
            if (empty($input['id'])) throw new Exception('Problem ID required');
            
            $sql = "UPDATE coding_problems SET 
                title=?, category=?, difficulty=?, problem_statement=?,
                constraints=?, example_input=?, example_output=?,
                concept_explanation=?, time_complexity=?, space_complexity=?
                WHERE id=?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $input['title'],
                $input['category'],
                $input['difficulty'],
                $input['problem_statement'],
                $input['constraints'],
                $input['example_input'],
                $input['example_output'],
                $input['concept_explanation'],
                $input['time_complexity'],
                $input['space_complexity'],
                $input['id']
            ]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
