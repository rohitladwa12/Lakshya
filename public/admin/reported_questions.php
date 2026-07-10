<?php
/**
 * Global Admin - Reported Questions Management
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/Admin.php';

// Auth
requireRole(ROLE_ADMIN);

$fullName = getFullName();
$db = getDB();

$success = Session::flash('success') ?: '';
$error = Session::flash('error') ?: '';

// Handle Actions
if (isPost()) {
    $action = post('action');
    $reportId = (int)post('report_id');
    $csrfToken = post('csrf_token');
    $isAjax = post('ajax') === '1';

    $errorMsg = '';
    $successMsg = '';

    if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        $errorMsg = 'Security validation failed. Please refresh and try again.';
    } else {
        if ($action === 'resolve') {
            $correctOption = strtoupper(trim((string)post('correct_option')));
            $questionText = post('question_text');
            $originalQuestionText = post('original_question_text') ?: $questionText;
            $testType = post('test_type');
            $testId = post('test_id');
            
            $optA = post('option_a');
            $optB = post('option_b');
            $optC = post('option_c');
            $optD = post('option_d');
            $hasEditedOptions = ($optA !== null && $optB !== null && $optC !== null && $optD !== null);
            
            if ($testType !== 'coding_problem' && !in_array($correctOption, ['A', 'B', 'C', 'D'])) {
                $errorMsg = 'Please select a valid correct option (A, B, C, or D).';
            } else {
                try {
                    $db->beginTransaction();

                    if ($testType === 'coding_problem') {
                        // Update report status
                        $stmt = $db->prepare("UPDATE reported_questions SET status = 'resolved', correct_answer = 'RESOLVED' WHERE id = ?");
                        $stmt->execute([$reportId]);
                        
                        $updatedInDb = true;
                        $dbTableName = 'coding_problems';
                        $dbQuestionId = $testId;
                    } else {
                        // Update report status
                        if ($hasEditedOptions) {
                            $optionsJson = json_encode([$optA, $optB, $optC, $optD]);
                            $stmt = $db->prepare("UPDATE reported_questions SET status = 'resolved', correct_answer = ?, question_text = ?, options = ? WHERE id = ?");
                            $stmt->execute([$correctOption, $questionText, $optionsJson, $reportId]);
                        } else {
                            $stmt = $db->prepare("UPDATE reported_questions SET status = 'resolved', correct_answer = ?, question_text = ? WHERE id = ?");
                            $stmt->execute([$correctOption, $questionText, $reportId]);
                        }

                        // Try to locate and update the question in the DB tables
                        $updatedInDb = false;
                        $dbTableName = '';
                        $dbQuestionId = null;

                        // 1. Check aptitude_questions
                        $stmt = $db->prepare("SELECT id FROM aptitude_questions WHERE question = ? OR question LIKE ? LIMIT 1");
                        $stmt->execute([$originalQuestionText, '%' . $originalQuestionText . '%']);
                        $match = $stmt->fetch();
                        if ($match) {
                            $dbQuestionId = $match['id'];
                            $dbTableName = 'aptitude_questions';
                            if ($hasEditedOptions) {
                                $updateStmt = $db->prepare("UPDATE aptitude_questions SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ? WHERE id = ?");
                                $updateStmt->execute([$questionText, $optA, $optB, $optC, $optD, $correctOption, $dbQuestionId]);
                            } else {
                                $updateStmt = $db->prepare("UPDATE aptitude_questions SET question = ?, correct_option = ? WHERE id = ?");
                                $updateStmt->execute([$questionText, $correctOption, $dbQuestionId]);
                            }
                            $updatedInDb = true;
                        }

                        // 2. Check nqt_aptitude_questions if not found
                        if (!$updatedInDb) {
                            $stmt = $db->prepare("SELECT id FROM nqt_aptitude_questions WHERE question = ? OR question LIKE ? LIMIT 1");
                            $stmt->execute([$originalQuestionText, '%' . $originalQuestionText . '%']);
                            $match = $stmt->fetch();
                            if ($match) {
                                $dbQuestionId = $match['id'];
                                $dbTableName = 'nqt_aptitude_questions';
                                if ($hasEditedOptions) {
                                    $updateStmt = $db->prepare("UPDATE nqt_aptitude_questions SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ? WHERE id = ?");
                                    $updateStmt->execute([$questionText, $optA, $optB, $optC, $optD, $correctOption, $dbQuestionId]);
                                } else {
                                    $updateStmt = $db->prepare("UPDATE nqt_aptitude_questions SET question = ?, correct_option = ? WHERE id = ?");
                                    $updateStmt->execute([$questionText, $correctOption, $dbQuestionId]);
                                }
                                $updatedInDb = true;
                            }
                        }

                        // 3. Check task_manual_questions if not found
                        if (!$updatedInDb) {
                            $stmt = $db->prepare("SELECT id FROM task_manual_questions WHERE question_text = ? OR question_text LIKE ? LIMIT 1");
                            $stmt->execute([$originalQuestionText, '%' . $originalQuestionText . '%']);
                            $match = $stmt->fetch();
                            if ($match) {
                                $dbQuestionId = $match['id'];
                                $dbTableName = 'task_manual_questions';
                                if ($hasEditedOptions) {
                                    $updateStmt = $db->prepare("UPDATE task_manual_questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ? WHERE id = ?");
                                    $updateStmt->execute([$questionText, $optA, $optB, $optC, $optD, $correctOption, $dbQuestionId]);
                                } else {
                                    $updateStmt = $db->prepare("UPDATE task_manual_questions SET question_text = ?, correct_option = ? WHERE id = ?");
                                    $updateStmt->execute([$questionText, $correctOption, $dbQuestionId]);
                                }
                                $updatedInDb = true;
                            }
                        }

                        // 4. Check manual_aptitude_questions if not found
                        if (!$updatedInDb) {
                            $stmt = $db->prepare("SELECT id FROM manual_aptitude_questions WHERE question_text = ? OR question_text LIKE ? LIMIT 1");
                            $stmt->execute([$originalQuestionText, '%' . $originalQuestionText . '%']);
                            $match = $stmt->fetch();
                            if ($match) {
                                $dbQuestionId = $match['id'];
                                $dbTableName = 'manual_aptitude_questions';
                                if ($hasEditedOptions) {
                                    $updateStmt = $db->prepare("UPDATE manual_aptitude_questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ? WHERE id = ?");
                                    $updateStmt->execute([$questionText, $optA, $optB, $optC, $optD, $correctOption, $dbQuestionId]);
                                } else {
                                    $updateStmt = $db->prepare("UPDATE manual_aptitude_questions SET question_text = ?, correct_option = ? WHERE id = ?");
                                    $updateStmt->execute([$questionText, $correctOption, $dbQuestionId]);
                                }
                                $updatedInDb = true;
                            }
                        }
                    }

                    $db->commit();

                    $successMsg = 'Report resolved successfully.';
                    if ($updatedInDb) {
                        $successMsg .= " Automatically updated question & options in database table '{$dbTableName}' (ID: {$dbQuestionId}).";
                    }

                } catch (Exception $e) {
                    $db->rollBack();
                    $errorMsg = 'Error resolving report: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'ai_autofix') {
            $stmt = $db->prepare("SELECT options, question_text FROM reported_questions WHERE id = ?");
            $stmt->execute([$reportId]);
            $report = $stmt->fetch();
            if ($report) {
                $options = json_decode($report['options'] ?? '[]', true) ?: [];
                $questionText = $report['question_text'];

                require_once ROOT_PATH . '/src/Services/AIService.php';
                $aiService = new AIService();
                $res = $aiService->autoFixReportedQuestion($questionText, $options);

                if ($res && $res['success']) {
                    $correctOption = $res['correct_option'];
                    $explanation = $res['explanation'];

                    try {
                        $db->beginTransaction();

                        // Update report status
                        $stmt = $db->prepare("UPDATE reported_questions SET status = 'resolved', correct_answer = ? WHERE id = ?");
                        $stmt->execute([$correctOption, $reportId]);

                        // Try to locate and update the question in the DB tables
                        $updatedInDb = false;
                        $dbTableName = '';
                        $dbQuestionId = null;

                        // 1. Check aptitude_questions
                        $stmt = $db->prepare("SELECT id FROM aptitude_questions WHERE question = ? OR question LIKE ? LIMIT 1");
                        $stmt->execute([$questionText, '%' . $questionText . '%']);
                        $match = $stmt->fetch();
                        if ($match) {
                            $dbQuestionId = $match['id'];
                            $dbTableName = 'aptitude_questions';
                            $updateStmt = $db->prepare("UPDATE aptitude_questions SET correct_option = ? WHERE id = ?");
                            $updateStmt->execute([$correctOption, $dbQuestionId]);
                            $updatedInDb = true;
                        }

                        // 2. Check nqt_aptitude_questions
                        if (!$updatedInDb) {
                            $stmt = $db->prepare("SELECT id FROM nqt_aptitude_questions WHERE question = ? OR question LIKE ? LIMIT 1");
                            $stmt->execute([$questionText, '%' . $questionText . '%']);
                            $match = $stmt->fetch();
                            if ($match) {
                                $dbQuestionId = $match['id'];
                                $dbTableName = 'nqt_aptitude_questions';
                                $updateStmt = $db->prepare("UPDATE nqt_aptitude_questions SET correct_option = ? WHERE id = ?");
                                $updateStmt->execute([$correctOption, $dbQuestionId]);
                                $updatedInDb = true;
                            }
                        }

                        // 3. Check task_manual_questions
                        if (!$updatedInDb) {
                            $stmt = $db->prepare("SELECT id FROM task_manual_questions WHERE question_text = ? OR question_text LIKE ? LIMIT 1");
                            $stmt->execute([$questionText, '%' . $questionText . '%']);
                            $match = $stmt->fetch();
                            if ($match) {
                                $dbQuestionId = $match['id'];
                                $dbTableName = 'task_manual_questions';
                                $updateStmt = $db->prepare("UPDATE task_manual_questions SET correct_option = ? WHERE id = ?");
                                $updateStmt->execute([$correctOption, $dbQuestionId]);
                                $updatedInDb = true;
                            }
                        }

                        // 4. Check manual_aptitude_questions
                        if (!$updatedInDb) {
                            $stmt = $db->prepare("SELECT id FROM manual_aptitude_questions WHERE question_text = ? OR question_text LIKE ? LIMIT 1");
                            $stmt->execute([$questionText, '%' . $questionText . '%']);
                            $match = $stmt->fetch();
                            if ($match) {
                                $dbQuestionId = $match['id'];
                                $dbTableName = 'manual_aptitude_questions';
                                $updateStmt = $db->prepare("UPDATE manual_aptitude_questions SET correct_option = ? WHERE id = ?");
                                $updateStmt->execute([$correctOption, $dbQuestionId]);
                                $updatedInDb = true;
                            }
                        }

                        $db->commit();

                        $successMsg = "AI Auto-Fix resolved this question to Option {$correctOption}.";
                        if ($updatedInDb) {
                            $successMsg .= " Automatically updated answer key in database table '{$dbTableName}' (ID: {$dbQuestionId}) to Option {$correctOption}.";
                        }
                    } catch (Exception $e) {
                        $db->rollBack();
                        $errorMsg = 'Error saving AI Auto-Fix resolution: ' . $e->getMessage();
                    }
                } else {
                    $errorMsg = $res['message'] ?? 'AI was unable to resolve this question.';
                }
            } else {
                $errorMsg = 'Report not found.';
            }
        } elseif ($action === 'delete_question') {
            $questionText = post('question_text');
            try {
                $db->beginTransaction();

                // Update report status
                $stmt = $db->prepare("UPDATE reported_questions SET status = 'dismissed', correct_answer = 'DELETED' WHERE id = ?");
                $stmt->execute([$reportId]);

                $deletedFromDb = false;
                $dbTableName = '';
                $dbQuestionId = null;

                // 1. Check aptitude_questions
                $stmt = $db->prepare("SELECT id FROM aptitude_questions WHERE question = ? OR question LIKE ? LIMIT 1");
                $stmt->execute([$questionText, '%' . $questionText . '%']);
                $match = $stmt->fetch();
                if ($match) {
                    $dbQuestionId = $match['id'];
                    $dbTableName = 'aptitude_questions';
                    $delStmt = $db->prepare("DELETE FROM aptitude_questions WHERE id = ?");
                    $delStmt->execute([$dbQuestionId]);
                    $deletedFromDb = true;
                }

                // 2. Check nqt_aptitude_questions
                if (!$deletedFromDb) {
                    $stmt = $db->prepare("SELECT id FROM nqt_aptitude_questions WHERE question = ? OR question LIKE ? LIMIT 1");
                    $stmt->execute([$questionText, '%' . $questionText . '%']);
                    $match = $stmt->fetch();
                    if ($match) {
                        $dbQuestionId = $match['id'];
                        $dbTableName = 'nqt_aptitude_questions';
                        $delStmt = $db->prepare("DELETE FROM nqt_aptitude_questions WHERE id = ?");
                        $delStmt->execute([$dbQuestionId]);
                        $deletedFromDb = true;
                    }
                }

                // 3. Check task_manual_questions
                if (!$deletedFromDb) {
                    $stmt = $db->prepare("SELECT id FROM task_manual_questions WHERE question_text = ? OR question_text LIKE ? LIMIT 1");
                    $stmt->execute([$questionText, '%' . $questionText . '%']);
                    $match = $stmt->fetch();
                    if ($match) {
                        $dbQuestionId = $match['id'];
                        $dbTableName = 'task_manual_questions';
                        $delStmt = $db->prepare("DELETE FROM task_manual_questions WHERE id = ?");
                        $delStmt->execute([$dbQuestionId]);
                        $deletedFromDb = true;
                    }
                }

                // 4. Check manual_aptitude_questions
                if (!$deletedFromDb) {
                    $stmt = $db->prepare("SELECT id FROM manual_aptitude_questions WHERE question_text = ? OR question_text LIKE ? LIMIT 1");
                    $stmt->execute([$questionText, '%' . $questionText . '%']);
                    $match = $stmt->fetch();
                    if ($match) {
                        $dbQuestionId = $match['id'];
                        $dbTableName = 'manual_aptitude_questions';
                        $delStmt = $db->prepare("DELETE FROM manual_aptitude_questions WHERE id = ?");
                        $delStmt->execute([$dbQuestionId]);
                        $deletedFromDb = true;
                    }
                }

                $db->commit();

                $successMsg = 'Report updated and question deleted successfully.';
                if ($deletedFromDb) {
                    $successMsg .= " Automatically deleted question from database table '{$dbTableName}' (ID: {$dbQuestionId}).";
                }

            } catch (Exception $e) {
                $db->rollBack();
                $errorMsg = 'Error deleting question: ' . $e->getMessage();
            }
        } elseif ($action === 'dismiss') {
            try {
                $stmt = $db->prepare("UPDATE reported_questions SET status = 'dismissed' WHERE id = ?");
                $stmt->execute([$reportId]);
                $successMsg = 'Report dismissed successfully.';
            } catch (Exception $e) {
                $errorMsg = 'Error dismissing report: ' . $e->getMessage();
            }
        } elseif ($action === 'edit_db_question') {
            $dbTable = post('db_table');
            $questionId = (int)post('question_id');
            $questionText = post('question_text');
            $optA = post('option_a');
            $optB = post('option_b');
            $optC = post('option_c');
            $optD = post('option_d');
            $correctOption = strtoupper(trim((string)post('correct_option')));

            if (!in_array($dbTable, ['aptitude_questions', 'nqt_aptitude_questions', 'task_manual_questions', 'manual_aptitude_questions'])) {
                $errorMsg = 'Invalid database table specified.';
            } elseif (!in_array($correctOption, ['A', 'B', 'C', 'D'])) {
                $errorMsg = 'Please select a valid correct option (A, B, C, or D).';
            } else {
                try {
                    $qCol = ($dbTable === 'aptitude_questions' || $dbTable === 'nqt_aptitude_questions') ? 'question' : 'question_text';
                    $sql = "UPDATE {$dbTable} SET {$qCol} = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$questionText, $optA, $optB, $optC, $optD, $correctOption, $questionId]);
                    
                    $successMsg = 'Question updated successfully.';
                } catch (Exception $e) {
                    $errorMsg = 'Error updating question: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete_db_question') {
            $dbTable = post('db_table');
            $questionId = (int)post('question_id');

            if (!in_array($dbTable, ['aptitude_questions', 'nqt_aptitude_questions', 'task_manual_questions', 'manual_aptitude_questions'])) {
                $errorMsg = 'Invalid database table specified.';
            } else {
                try {
                    $stmt = $db->prepare("DELETE FROM {$dbTable} WHERE id = ?");
                    $stmt->execute([$questionId]);
                    $successMsg = 'Question deleted successfully.';
                } catch (Exception $e) {
                    $errorMsg = 'Error deleting question: ' . $e->getMessage();
                }
            }
        }
    }

    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        
        if ($action === 'edit_db_question' || $action === 'delete_db_question') {
            echo json_encode([
                'success' => empty($errorMsg),
                'message' => $errorMsg ?: $successMsg
            ]);
            exit;
        }

        $newStatus = 'dismissed';
        if ($action === 'resolve' || $action === 'ai_autofix') {
            $newStatus = 'resolved';
        }
        
        $finalCorrectOption = null;
        if ($action === 'resolve' || $action === 'ai_autofix') {
            $finalCorrectOption = $correctOption ?? null;
        } elseif ($action === 'delete_question') {
            $finalCorrectOption = 'DELETED';
        }

        echo json_encode([
            'success' => empty($errorMsg),
            'message' => $errorMsg ?: $successMsg,
            'status' => $newStatus,
            'correct_answer' => $finalCorrectOption
        ]);
        exit;
    }

    if ($errorMsg) {
        Session::flash('error', $errorMsg);
    } else {
        Session::flash('success', $successMsg);
    }
    redirect('reported_questions.php');
    exit;
}

// Fetch Reports
$reports = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'resolved' => 0,
    'dismissed' => 0
];

try {
    $reports = $db->query("SELECT * FROM reported_questions ORDER BY created_at DESC")->fetchAll();
    $stats['total'] = count($reports);
    foreach ($reports as $r) {
        if ($r['status'] === 'pending') $stats['pending']++;
        elseif ($r['status'] === 'resolved') $stats['resolved']++;
        elseif ($r['status'] === 'dismissed') $stats['dismissed']++;
    }
} catch (Exception $e) {
    error_log("Error fetching reported questions: " . $e->getMessage());
}

// Fetch all questions from the database tables
$dbQuestions = [];
try {
    // 1. Fetch aptitude_questions
    $stmt = $db->query("SELECT id, 'aptitude_questions' AS source_table, question AS question_text, option_a, option_b, option_c, option_d, correct_option, NULL AS extra_info FROM aptitude_questions");
    $dbQuestions = array_merge($dbQuestions, $stmt->fetchAll());
    
    // 2. Fetch nqt_aptitude_questions
    $stmt = $db->query("SELECT id, 'nqt_aptitude_questions' AS source_table, question AS question_text, option_a, option_b, option_c, option_d, correct_option, NULL AS extra_info FROM nqt_aptitude_questions");
    $dbQuestions = array_merge($dbQuestions, $stmt->fetchAll());
    
    // 3. Fetch task_manual_questions
    $stmt = $db->query("SELECT id, 'task_manual_questions' AS source_table, question_text, option_a, option_b, option_c, option_d, correct_option, CONCAT('Task ID: ', task_id) AS extra_info FROM task_manual_questions");
    $dbQuestions = array_merge($dbQuestions, $stmt->fetchAll());
    
    // 4. Fetch manual_aptitude_questions
    $stmt = $db->query("SELECT id, 'manual_aptitude_questions' AS source_table, question_text, option_a, option_b, option_c, option_d, correct_option, CONCAT('Company: ', company_name) AS extra_info FROM manual_aptitude_questions");
    $dbQuestions = array_merge($dbQuestions, $stmt->fetchAll());
} catch (Exception $e) {
    error_log("Error fetching database questions: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reported Questions - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --bg-color: #f4f7fe;
            --white: #ffffff;
            --text-dark: #2b3674;
            --text-muted: #a3aed1;
            --shadow: 0 10px 20px rgba(0,0,0,0.02);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-color);
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 40px;
            width: 100%;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: var(--white);
            padding: 20px 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .header-title h1 {
            font-size: 24px;
            color: var(--text-dark);
            font-weight: 700;
        }

        .header-title p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 5px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-profile .avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.2);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--white);
            padding: 20px 24px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid transparent;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-maroon);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .icon-total { background: #e0f2fe; color: #0284c7; }
        .icon-pending { background: #fef3c7; color: #d97706; }
        .icon-resolved { background: #dcfce7; color: #15803d; }
        .icon-dismissed { background: #f1f5f9; color: #64748b; }

        .stat-info h3 {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .stat-info .value {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .panel {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .panel-title {
            color: var(--text-dark);
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filters-container {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 10px 15px;
            border-radius: 12px;
            border: 1px solid #eef2f8;
            background: #F4F7FE;
            color: var(--text-dark);
            font-family: inherit;
            font-size: 14px;
            outline: none;
            cursor: pointer;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border-radius: 12px;
            border: 1px solid #eef2f8;
            outline: none;
            font-family: inherit;
            background: #F4F7FE;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 15px 20px;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            border-bottom: 1px solid #eef2f8;
            text-transform: uppercase;
            background: #FAFBFF;
        }

        .data-table td {
            padding: 18px 20px;
            color: var(--text-dark);
            font-size: 14px;
            border-bottom: 1px solid #eef2f8;
            vertical-align: top;
        }

        .data-table tr:hover {
            background: #FAFBFF;
        }

        .back-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            display: inline-block;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--primary-maroon);
        }

        .badge-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-resolved { background: #dcfce7; color: #15803d; }
        .badge-dismissed { background: #f1f5f9; color: #64748b; }

        .badge-db-match {
            background: rgba(128, 0, 0, 0.1);
            color: var(--primary-maroon);
            border: 1px solid rgba(128, 0, 0, 0.2);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 6px;
            text-transform: uppercase;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .q-display-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .q-text {
            font-weight: 700;
            color: #1E293B;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .options-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .opt-item {
            padding: 8px 12px;
            background: #fff;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .opt-item.correct {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
            font-weight: 600;
        }

        .opt-item.user-selected {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
            font-weight: 600;
        }

        .opt-item.correct.user-selected {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .actions-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #F8FAFC;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
        }

        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: var(--transition);
        }

        .btn-resolve {
            background: #10b981;
            color: white;
        }
        .btn-resolve:hover {
            background: #059669;
        }

        .btn-dismiss {
            background: #ef4444;
            color: white;
        }
        .btn-dismiss:hover {
            background: #dc2626;
        }

        .action-label {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="header">
            <div class="header-title">
                <h1>Reported Questions</h1>
                <p>Review and resolve student reports about incorrect answer keys or evaluation logic.</p>
            </div>
            <div class="user-profile">
                <span style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($fullName); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($fullName, 0, 1)); ?></div>
            </div>
        </div>

        <!-- Tabs Nav -->
        <div class="tabs-container" style="display: flex; gap: 20px; border-bottom: 2px solid #eef2f8; margin-bottom: 25px;">
            <a href="javascript:void(0)" class="tab-link active" onclick="switchTab('reports')" style="padding: 10px 20px; color: var(--primary-maroon); font-weight: 700; text-decoration: none; border-bottom: 3px solid var(--primary-maroon); font-size: 16px;">
                <i class="fas fa-flag"></i> Reported Questions
            </a>
            <a href="javascript:void(0)" class="tab-link" onclick="switchTab('all-questions')" style="padding: 10px 20px; color: var(--text-muted); font-weight: 600; text-decoration: none; border-bottom: 3px solid transparent; font-size: 16px;">
                <i class="fas fa-database"></i> All Database Questions
            </a>
        </div>

        <div id="reportsTabContent">
            <!-- Stats Grid -->
            <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-total"><i class="fas fa-flag"></i></div>
                <div class="stat-info">
                    <h3>Total Reports</h3>
                    <div class="value"><?php echo $stats['total']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-pending"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3>Pending Review</h3>
                    <div class="value"><?php echo $stats['pending']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-resolved"><i class="fas fa-check-double"></i></div>
                <div class="stat-info">
                    <h3>Resolved</h3>
                    <div class="value"><?php echo $stats['resolved']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-dismissed"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <h3>Dismissed</h3>
                    <div class="value"><?php echo $stats['dismissed']; ?></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-list" style="color: var(--primary-maroon);"></i> Reports List
                </div>
                
                <div class="filters-container">
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Statuses</option>
                        <option value="pending" selected>Pending</option>
                        <option value="resolved">Resolved</option>
                        <option value="dismissed">Dismissed</option>
                    </select>

                    <select id="typeFilter" class="filter-select">
                        <option value="">All Test Types</option>
                        <option value="skill_quiz">Skill Quiz</option>
                        <option value="campus_drive">Campus Drive</option>
                        <option value="nqt">NQT</option>
                        <option value="mock_ai">Mock AI / Tasks</option>
                        <option value="coding_problem">Coding Practice</option>
                    </select>

                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="reportSearch" placeholder="Search reports...">
                    </div>
                </div>
            </div>
            
            <?php if (empty($reports)): ?>
                <div style="text-align: center; padding: 50px 20px; color: var(--text-muted);">
                    <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p style="font-size: 16px; font-weight: 600;">No reported questions found.</p>
                </div>
            <?php else: ?>
                <table class="data-table" id="reportsTable">
                    <thead>
                        <tr>
                            <th style="width: 5%;">Sl No</th>
                            <th style="width: 12%;">Reported At</th>
                            <th style="width: 14%;">Student</th>
                            <th style="width: 14%;">Source Test</th>
                            <th style="width: 35%;">Question Details</th>
                            <th style="width: 20%;">Review & Resolve</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $slNo = 1; foreach($reports as $r): ?>
                            <?php 
                                $opts = json_decode($r['options'] ?? '[]', true) ?: [];
                                
                                // Map answer representation to 0-3 index for styling
                                $correctAnsIdx = null;
                                if (isset($r['correct_answer'])) {
                                    $cAns = strtoupper(trim($r['correct_answer']));
                                    if (in_array($cAns, ['A', 'B', 'C', 'D'])) {
                                        $correctAnsIdx = match($cAns) { 'A'=>0, 'B'=>1, 'C'=>2, 'D'=>3 };
                                    } elseif (is_numeric($cAns)) {
                                        $correctAnsIdx = (int)$cAns;
                                    }
                                }

                                $userAnsIdx = null;
                                if (isset($r['user_answer'])) {
                                    $uAns = strtoupper(trim($r['user_answer']));
                                    if (in_array($uAns, ['A', 'B', 'C', 'D'])) {
                                        $userAnsIdx = match($uAns) { 'A'=>0, 'B'=>1, 'C'=>2, 'D'=>3 };
                                    } elseif (is_numeric($uAns)) {
                                        $userAnsIdx = (int)$uAns;
                                    }
                                }

                                // Check if this question matches any DB question
                                $dbMatchId = null;
                                $dbMatchTable = '';
                                $dbCurrentKey = '';

                                $stmtDb = $db->prepare("SELECT id, correct_option FROM aptitude_questions WHERE question = ? OR question LIKE ? LIMIT 1");
                                $stmtDb->execute([$r['question_text'], '%' . $r['question_text'] . '%']);
                                $matchDb = $stmtDb->fetch();
                                if ($matchDb) {
                                    $dbMatchId = $matchDb['id'];
                                    $dbMatchTable = 'aptitude_questions';
                                    $dbCurrentKey = $matchDb['correct_option'];
                                } else {
                                    $stmtDb = $db->prepare("SELECT id, correct_option FROM nqt_aptitude_questions WHERE question = ? OR question LIKE ? LIMIT 1");
                                    $stmtDb->execute([$r['question_text'], '%' . $r['question_text'] . '%']);
                                    $matchDb = $stmtDb->fetch();
                                    if ($matchDb) {
                                        $dbMatchId = $matchDb['id'];
                                        $dbMatchTable = 'nqt_aptitude_questions';
                                        $dbCurrentKey = $matchDb['correct_option'];
                                    } else {
                                        $stmtDb = $db->prepare("SELECT id, correct_option FROM task_manual_questions WHERE question_text = ? OR question_text LIKE ? LIMIT 1");
                                        $stmtDb->execute([$r['question_text'], '%' . $r['question_text'] . '%']);
                                        $matchDb = $stmtDb->fetch();
                                        if ($matchDb) {
                                            $dbMatchId = $matchDb['id'];
                                            $dbMatchTable = 'task_manual_questions';
                                            $dbCurrentKey = $matchDb['correct_option'];
                                        } else {
                                            $stmtDb = $db->prepare("SELECT id, correct_option FROM manual_aptitude_questions WHERE question_text = ? OR question_text LIKE ? LIMIT 1");
                                            $stmtDb->execute([$r['question_text'], '%' . $r['question_text'] . '%']);
                                            $matchDb = $stmtDb->fetch();
                                            if ($matchDb) {
                                                $dbMatchId = $matchDb['id'];
                                                $dbMatchTable = 'manual_aptitude_questions';
                                                $dbCurrentKey = $matchDb['correct_option'];
                                            }
                                        }
                                    }
                                }
                            ?>
                            <tr class="report-row" 
                                data-status="<?php echo htmlspecialchars($r['status']); ?>"
                                data-type="<?php echo htmlspecialchars($r['test_type']); ?>"
                                data-search="<?php 
                                echo strtolower(
                                    ($r['student_name'] ?? '') . ' ' . 
                                    ($r['student_id'] ?? '') . ' ' . 
                                    ($r['question_text'] ?? '') . ' ' . 
                                    ($r['comment'] ?? '') . ' ' . 
                                    ($r['issue_type'] ?? '')
                                ); 
                            ?>">
                                <td style="font-weight: 600; text-align: center; color: var(--text-dark);">
                                    <?php echo $slNo++; ?>
                                </td>
                                <td style="font-size: 13px; color: var(--text-muted); font-weight: 500;">
                                    <?php echo date('d M Y', strtotime($r['created_at'])); ?>
                                    <div style="font-size: 11px; margin-top: 4px;"><?php echo date('h:i A', strtotime($r['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark);"><?php echo htmlspecialchars($r['student_name'] ?? 'N/A'); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($r['student_id'] ?? 'N/A'); ?></div>
                                </td>
                                <td>
                                    <span style="font-size: 12px; font-weight: 700; color: #475569; background: #e2e8f0; padding: 4px 8px; border-radius: 6px;">
                                        <?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $r['test_type']))); ?>
                                    </span>
                                    <?php if ($r['test_id']): ?>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">ID: <?php echo htmlspecialchars($r['test_id']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="q-display-box">
                                        <div class="q-text"><?php echo htmlspecialchars($r['question_text']); ?></div>
                                        
                                        <?php if (!empty($opts)): ?>
                                            <ul class="options-list">
                                                <?php foreach ($opts as $oIdx => $optText): ?>
                                                    <?php 
                                                        $optClass = 'opt-item';
                                                        $suffix = '';
                                                        
                                                        if ($oIdx === $correctAnsIdx) {
                                                            $optClass .= ' correct';
                                                            $suffix .= ' [System Key]';
                                                        }
                                                        if ($oIdx === $userAnsIdx) {
                                                            $optClass .= ' user-selected';
                                                            $suffix .= ' [User Ans]';
                                                        }
                                                    ?>
                                                    <li class="<?php echo $optClass; ?>">
                                                        <span><strong><?php echo chr(65 + $oIdx); ?>)</strong> <?php echo htmlspecialchars($optText); ?></span>
                                                        <span style="font-size: 10px; opacity: 0.8;"><?php echo $suffix; ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php elseif ($r['test_type'] === 'coding_problem'): ?>
                                            <div style="margin-top: 10px;">
                                                <strong style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Submitted Code:</strong>
                                                <pre style="background: #2d3748; color: #fff; padding: 10px; border-radius: 6px; font-family: monospace; font-size: 12px; overflow-x: auto; margin-top: 4px; white-space: pre-wrap;"><?php echo htmlspecialchars($r['user_answer'] ?? 'No code submitted'); ?></pre>
                                            </div>
                                        <?php else: ?>
                                            <div style="font-size: 12px; color: var(--text-muted); font-style: italic;">No options stored.</div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($r['comment']): ?>
                                        <div style="font-size: 13px; padding: 10px; background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 4px; color: #78350f;">
                                            <strong>Student Comment:</strong> <?php echo htmlspecialchars($r['comment']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($dbMatchId): ?>
                                        <div class="badge-db-match" style="margin-bottom: 8px;">
                                            <i class="fas fa-database"></i> Matches DB question in '<?php echo $dbMatchTable; ?>' (ID: <?php echo $dbMatchId; ?>, Key: <?php echo htmlspecialchars($dbCurrentKey); ?>)
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($r['status'] === 'pending' && $r['test_type'] !== 'coding_problem'): ?>
                                        <div style="margin-top: 12px;">
                                            <button type="button" class="btn-simple" style="padding: 6px 12px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; border-radius: 6px; border: 1px solid var(--primary-maroon); color: var(--primary-maroon); background: transparent;" onclick="toggleEditQuestion(<?php echo $r['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit Question & Options
                                            </button>
                                        </div>
                                        
                                        <div id="edit-box-<?php echo $r['id']; ?>" style="display: none; margin-top: 15px; border-top: 1px dashed #e2e8f0; padding-top: 15px; background: #fafafa; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                            <div style="margin-bottom: 10px;">
                                                <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px;">Edit Question Text</label>
                                                <textarea name="question_text" id="edit-qtext-<?php echo $r['id']; ?>" form="form-resolve-<?php echo $r['id']; ?>" style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: inherit; font-size: 13px;" disabled required><?php echo htmlspecialchars($r['question_text']); ?></textarea>
                                            </div>
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                                <div>
                                                    <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px;">Option A</label>
                                                    <input type="text" name="option_a" id="edit-opta-<?php echo $r['id']; ?>" form="form-resolve-<?php echo $r['id']; ?>" value="<?php echo htmlspecialchars($opts[0] ?? ''); ?>" style="width: 100%; padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px;" disabled required>
                                                </div>
                                                <div>
                                                    <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px;">Option B</label>
                                                    <input type="text" name="option_b" id="edit-optb-<?php echo $r['id']; ?>" form="form-resolve-<?php echo $r['id']; ?>" value="<?php echo htmlspecialchars($opts[1] ?? ''); ?>" style="width: 100%; padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px;" disabled required>
                                                </div>
                                                <div>
                                                    <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px;">Option C</label>
                                                    <input type="text" name="option_c" id="edit-optc-<?php echo $r['id']; ?>" form="form-resolve-<?php echo $r['id']; ?>" value="<?php echo htmlspecialchars($opts[2] ?? ''); ?>" style="width: 100%; padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px;" disabled required>
                                                </div>
                                                <div>
                                                    <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px;">Option D</label>
                                                    <input type="text" name="option_d" id="edit-optd-<?php echo $r['id']; ?>" form="form-resolve-<?php echo $r['id']; ?>" value="<?php echo htmlspecialchars($opts[3] ?? ''); ?>" style="width: 100%; padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px;" disabled required>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="margin-bottom: 12px;">
                                        <span class="badge-status badge-<?php echo strtolower($r['status']); ?>">
                                            <?php echo htmlspecialchars($r['status']); ?>
                                        </span>
                                    </div>

                                    <?php if ($r['status'] === 'pending'): ?>
                                        <form id="form-resolve-<?php echo $r['id']; ?>" method="POST" action="reported_questions.php" class="actions-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                            <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                                            <input type="hidden" name="original_question_text" value="<?php echo htmlspecialchars($r['question_text']); ?>">
                                            <input type="hidden" name="question_text" id="hidden-qtext-<?php echo $r['id']; ?>" value="<?php echo htmlspecialchars($r['question_text']); ?>">
                                            <input type="hidden" name="test_type" value="<?php echo htmlspecialchars($r['test_type']); ?>">
                                            <input type="hidden" name="test_id" value="<?php echo htmlspecialchars($r['test_id']); ?>">
                                            
                                            <?php if ($r['test_type'] === 'coding_problem'): ?>
                                                <div style="margin-bottom: 8px;">
                                                    <div class="action-label">Action</div>
                                                    <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 6px;">Verify code and fix problem evaluation if needed.</div>
                                                </div>
                                                <div style="display: flex; gap: 6px;">
                                                    <button type="submit" name="action" value="resolve" class="btn-action btn-resolve" style="flex: 1;">
                                                        <i class="fas fa-check-circle"></i> Resolve
                                                    </button>
                                                    <button type="submit" name="action" value="dismiss" class="btn-action btn-dismiss" style="flex: 1;">
                                                        <i class="fas fa-times-circle"></i> Dismiss
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div>
                                                    <div class="action-label">Set Correct Key</div>
                                                    <select name="correct_option" class="filter-select" style="width: 100%; padding: 6px; font-size: 13px; border-radius: 6px; margin-bottom: 8px;" required>
                                                        <option value="">-- Choose Correct Option --</option>
                                                        <option value="A" <?php echo $userAnsIdx === 0 ? 'selected' : ''; ?>>A (Mark User Correct)</option>
                                                        <option value="B" <?php echo $userAnsIdx === 1 ? 'selected' : ''; ?>>B</option>
                                                        <option value="C" <?php echo $userAnsIdx === 2 ? 'selected' : ''; ?>>C</option>
                                                        <option value="D" <?php echo $userAnsIdx === 3 ? 'selected' : ''; ?>>D</option>
                                                    </select>
                                                </div>

                                                <div style="display: flex; gap: 6px; margin-bottom: 6px;">
                                                    <button type="submit" name="action" value="resolve" class="btn-action btn-resolve" style="flex: 1;">
                                                        <i class="fas fa-check-circle"></i> Resolve
                                                    </button>
                                                    <button type="submit" name="action" value="dismiss" class="btn-action btn-dismiss" style="flex: 1;">
                                                        <i class="fas fa-times-circle"></i> Dismiss
                                                    </button>
                                                </div>
                                                <div style="display: flex; gap: 6px;">
                                                    <button type="submit" name="action" value="ai_autofix" class="btn-action btn-ai" style="flex: 1; background: #6366f1; color: white; border: none; padding: 8px 12px; border-radius: 6px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px;">
                                                        <i class="fas fa-robot"></i> AI Auto-Fix
                                                    </button>
                                                    <button type="submit" name="action" value="delete_question" class="btn-action btn-danger" style="flex: 1; background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 6px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px;" onclick="return confirm('Are you sure you want to delete this question from the database? This cannot be undone.');">
                                                        <i class="fas fa-trash-alt"></i> Delete
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                         <div style="font-size: 12px; color: var(--text-muted); line-height: 1.4;">
                                             <?php if ($r['correct_answer'] === 'DELETED'): ?>
                                                 <i class="fas fa-trash-alt" style="color: #ef4444;"></i> Question Deleted from Database
                                             <?php elseif ($r['correct_answer'] === 'RESOLVED'): ?>
                                                 <i class="fas fa-check-circle" style="color: #10b981;"></i> Resolved
                                             <?php elseif ($r['status'] === 'resolved'): ?>
                                                 <i class="fas fa-check-circle" style="color: #10b981;"></i> Resolved correct key to: <strong>Option <?php echo htmlspecialchars($r['correct_answer']); ?></strong>
                                             <?php else: ?>
                                                 <i class="fas fa-times-circle" style="color: #ef4444;"></i> Dismissed
                                             <?php endif; ?>
                                         </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        </div>

        <div id="allQuestionsTabContent" style="display: none;">
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-database" style="color: var(--primary-maroon);"></i> All Questions List
                    </div>
                    
                    <div class="filters-container">
                        <select id="dbTableFilter" class="filter-select">
                            <option value="">All Tables</option>
                            <option value="aptitude_questions">General Aptitude (aptitude_questions)</option>
                            <option value="nqt_aptitude_questions">NQT Aptitude (nqt_aptitude_questions)</option>
                            <option value="task_manual_questions">Task Manual (task_manual_questions)</option>
                            <option value="manual_aptitude_questions">Manual Aptitude (manual_aptitude_questions)</option>
                        </select>

                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="dbSearch" placeholder="Search questions...">
                        </div>
                    </div>
                </div>
                
                <?php if (empty($dbQuestions)): ?>
                    <div style="text-align: center; padding: 50px 20px; color: var(--text-muted);">
                        <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <p style="font-size: 16px; font-weight: 600;">No database questions found.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table" id="allQuestionsTable">
                        <thead>
                            <tr>
                                <th style="width: 5%;">Sl No</th>
                                <th style="width: 20%;">Source Table</th>
                                <th style="width: 55%;">Question Details</th>
                                <th style="width: 20%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $dbSlNo = 1; foreach($dbQuestions as $q): ?>
                                <tr class="db-question-row" 
                                    data-table="<?php echo htmlspecialchars($q['source_table']); ?>"
                                    data-search="<?php echo htmlspecialchars(strtolower($q['question_text'])); ?>">
                                    <td style="font-weight: 600; text-align: center; color: var(--text-dark);">
                                        <?php echo $dbSlNo++; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-dark);"><?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $q['source_table']))); ?></div>
                                        <?php if ($q['extra_info']): ?>
                                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                                                <?php echo htmlspecialchars($q['extra_info']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="q-display-box">
                                            <div class="db-q-text q-text"><?php echo htmlspecialchars($q['question_text']); ?></div>
                                            <ul class="db-options-list options-list">
                                                <?php foreach (['A', 'B', 'C', 'D'] as $oIdx => $optKey): ?>
                                                    <?php 
                                                        $optVal = $q['option_' . strtolower($optKey)];
                                                        $isCorrect = ($q['correct_option'] === $optKey);
                                                        $optClass = $isCorrect ? 'opt-item correct' : 'opt-item';
                                                        $suffix = $isCorrect ? ' [System Key]' : '';
                                                    ?>
                                                    <li class="<?php echo $optClass; ?>">
                                                        <span><strong><?php echo $optKey; ?>)</strong> <?php echo htmlspecialchars($optVal); ?></span>
                                                        <span style="font-size: 10px; opacity: 0.8;"><?php echo $suffix; ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </td>
                                    <td class="db-action-cell">
                                        <div>
                                            <button type="button" class="btn-simple" style="padding: 6px 12px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; border-radius: 6px; border: 1px solid var(--primary-maroon); color: var(--primary-maroon); background: transparent;" onclick="toggleEditDbQuestion('<?php echo $q['source_table']; ?>', <?php echo $q['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit Question & Options
                                            </button>
                                        </div>
                                        
                                        <div id="edit-db-box-<?php echo $q['source_table']; ?>-<?php echo $q['id']; ?>" style="display: none; margin-top: 15px; border-top: 1px dashed #e2e8f0; padding-top: 15px; background: #fafafa; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                            <form id="form-db-edit-<?php echo $q['source_table']; ?>-<?php echo $q['id']; ?>" method="POST" action="reported_questions.php" class="db-actions-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                <input type="hidden" name="db_table" value="<?php echo $q['source_table']; ?>">
                                                <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                                
                                                <div style="margin-bottom: 10px;">
                                                    <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px;">Edit Question Text</label>
                                                    <textarea name="question_text" style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: inherit; font-size: 13px;" required><?php echo htmlspecialchars($q['question_text']); ?></textarea>
                                                </div>
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px;">
                                                    <div>
                                                        <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px;">Option A</label>
                                                        <input type="text" name="option_a" value="<?php echo htmlspecialchars($q['option_a']); ?>" style="width: 100%; padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px;" required>
                                                    </div>
                                                    <div>
                                                        <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px;">Option B</label>
                                                        <input type="text" name="option_b" value="<?php echo htmlspecialchars($q['option_b']); ?>" style="width: 100%; padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px;" required>
                                                    </div>
                                                    <div>
                                                        <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px;">Option C</label>
                                                        <input type="text" name="option_c" value="<?php echo htmlspecialchars($q['option_c']); ?>" style="width: 100%; padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px;" required>
                                                    </div>
                                                    <div>
                                                        <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px;">Option D</label>
                                                        <input type="text" name="option_d" value="<?php echo htmlspecialchars($q['option_d']); ?>" style="width: 100%; padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px;" required>
                                                    </div>
                                                </div>
                                                
                                                <div style="margin-bottom: 10px;">
                                                    <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px;">Correct Option</label>
                                                    <select name="correct_option" class="filter-select" style="width: 100%; padding: 6px; font-size: 13px; border-radius: 6px;" required>
                                                        <option value="A" <?php echo $q['correct_option'] === 'A' ? 'selected' : ''; ?>>Option A</option>
                                                        <option value="B" <?php echo $q['correct_option'] === 'B' ? 'selected' : ''; ?>>Option B</option>
                                                        <option value="C" <?php echo $q['correct_option'] === 'C' ? 'selected' : ''; ?>>Option C</option>
                                                        <option value="D" <?php echo $q['correct_option'] === 'D' ? 'selected' : ''; ?>>Option D</option>
                                                    </select>
                                                </div>

                                                <div style="display: flex; gap: 8px;">
                                                    <button type="submit" name="action" value="edit_db_question" class="btn-action btn-resolve" style="flex: 1;">
                                                        <i class="fas fa-save"></i> Save Changes
                                                    </button>
                                                    <button type="submit" name="action" value="delete_db_question" class="btn-action btn-dismiss" style="flex: 1;" onclick="return confirm('Are you sure you want to delete this question? This cannot be undone.');">
                                                        <i class="fas fa-trash-alt"></i> Delete Question
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleEditQuestion(id) {
            const editBox = document.getElementById('edit-box-' + id);
            const hiddenQText = document.getElementById('hidden-qtext-' + id);
            const editQText = document.getElementById('edit-qtext-' + id);
            const editOptA = document.getElementById('edit-opta-' + id);
            const editOptB = document.getElementById('edit-optb-' + id);
            const editOptC = document.getElementById('edit-optc-' + id);
            const editOptD = document.getElementById('edit-optd-' + id);

            if (editBox.style.display === 'none') {
                editBox.style.display = 'block';
                hiddenQText.disabled = true;
                editQText.disabled = false;
                if (editOptA) {
                    editOptA.disabled = false;
                    editOptB.disabled = false;
                    editOptC.disabled = false;
                    editOptD.disabled = false;
                }
            } else {
                editBox.style.display = 'none';
                hiddenQText.disabled = false;
                editQText.disabled = true;
                if (editOptA) {
                    editOptA.disabled = true;
                    editOptB.disabled = true;
                    editOptC.disabled = true;
                    editOptD.disabled = true;
                }
            }
        }

        const statusFilter = document.getElementById('statusFilter');
        const typeFilter = document.getElementById('typeFilter');
        const reportSearch = document.getElementById('reportSearch');

        function applyFilters() {
            const statusVal = statusFilter.value;
            const typeVal = typeFilter.value;
            const searchVal = reportSearch.value.toLowerCase();

            const rows = document.querySelectorAll('#reportsTable tbody tr.report-row');
            rows.forEach(row => {
                const rStatus = row.getAttribute('data-status');
                const rType = row.getAttribute('data-type');
                const rSearch = row.getAttribute('data-search');

                const matchesStatus = !statusVal || rStatus === statusVal;
                const matchesType = !typeVal || rType === typeVal;
                const matchesSearch = !searchVal || rSearch.includes(searchVal);

                if (matchesStatus && matchesType && matchesSearch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        statusFilter.addEventListener('change', applyFilters);
        typeFilter.addEventListener('change', applyFilters);
        reportSearch.addEventListener('keyup', applyFilters);

        // AJAX Submission Handler
        document.querySelectorAll('.actions-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const submitter = e.submitter;
                if (!submitter) return;

                const action = submitter.value;
                const formData = new FormData(form);
                formData.append('action', action);
                formData.append('ajax', '1');

                // Append non-disabled elements referencing this form by ID
                const formId = form.getAttribute('id');
                if (formId) {
                    document.querySelectorAll(`[form="${formId}"]`).forEach(el => {
                        if (!el.disabled && el.name) {
                            formData.set(el.name, el.value);
                        }
                    });
                }

                const row = form.closest('.report-row');
                const actionCell = form.closest('td');

                // Disable submit buttons and show spinner
                form.querySelectorAll('button').forEach(btn => btn.disabled = true);
                const originalHtml = submitter.innerHTML;
                submitter.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                fetch('reported_questions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        row.setAttribute('data-status', data.status);
                        
                        if (data.correct_answer === 'DELETED') {
                            actionCell.innerHTML = `
                                <div style="margin-bottom: 12px;">
                                    <span class="badge-status badge-dismissed">dismissed</span>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted); line-height: 1.4;">
                                    <i class="fas fa-trash-alt" style="color: #ef4444;"></i> Question Deleted from Database
                                </div>
                            `;
                        } else if (data.status === 'resolved') {
                            const resolveText = data.correct_answer === 'RESOLVED' ? 'Resolved' : `Resolved correct key to: <strong>Option ${data.correct_answer}</strong>`;
                            actionCell.innerHTML = `
                                <div style="margin-bottom: 12px;">
                                    <span class="badge-status badge-resolved">resolved</span>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted); line-height: 1.4;">
                                    <i class="fas fa-check-circle" style="color: #10b981;"></i> ${resolveText}
                                </div>
                            `;
                        } else {
                            actionCell.innerHTML = `
                                <div style="margin-bottom: 12px;">
                                    <span class="badge-status badge-dismissed">dismissed</span>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted); line-height: 1.4;">
                                    <i class="fas fa-times-circle" style="color: #ef4444;"></i> Dismissed
                                </div>
                            `;
                        }

                        // Display floating/temporary alert at top of main-content
                        const alertBox = document.createElement('div');
                        alertBox.className = 'alert alert-success';
                        alertBox.innerHTML = `<i class="fas fa-check-circle"></i> <span>${data.message}</span>`;
                        document.querySelector('.main-content').insertBefore(alertBox, document.querySelector('.header') || document.querySelector('.page-header'));
                        
                        setTimeout(() => {
                            alertBox.style.transition = 'opacity 0.5s ease';
                            alertBox.style.opacity = '0';
                            setTimeout(() => alertBox.remove(), 500);
                        }, 3000);

                        updateStats();
                        applyFilters();
                    } else {
                        alert(data.message || 'An error occurred.');
                        form.querySelectorAll('button').forEach(btn => btn.disabled = false);
                        submitter.innerHTML = originalHtml;
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Request failed. Please try again.');
                    form.querySelectorAll('button').forEach(btn => btn.disabled = false);
                    submitter.innerHTML = originalHtml;
                });
            });
        });

        function updateStats() {
            const rows = document.querySelectorAll('#reportsTable tbody tr.report-row');
            let total = rows.length;
            let pending = 0;
            let resolved = 0;
            let dismissed = 0;

            rows.forEach(r => {
                const s = r.getAttribute('data-status');
                if (s === 'pending') pending++;
                else if (s === 'resolved') resolved++;
                else if (s === 'dismissed') dismissed++;
            });

            const cards = document.querySelectorAll('.stat-card .value');
            if (cards.length >= 4) {
                cards[0].textContent = total;
                cards[1].textContent = pending;
                cards[2].textContent = resolved;
                cards[3].textContent = dismissed;
            }
        }

        // Run initially to apply the default 'pending' filter
        applyFilters();

        // Tabs switcher
        function switchTab(tabId) {
            const reportsTab = document.getElementById('reportsTabContent');
            const allQuestionsTab = document.getElementById('allQuestionsTabContent');
            const links = document.querySelectorAll('.tab-link');

            links.forEach(link => {
                link.classList.remove('active');
                link.style.color = 'var(--text-muted)';
                link.style.borderBottomColor = 'transparent';
                link.style.fontWeight = '600';
            });

            if (tabId === 'reports') {
                reportsTab.style.display = 'block';
                allQuestionsTab.style.display = 'none';
                links[0].classList.add('active');
                links[0].style.color = 'var(--primary-maroon)';
                links[0].style.borderBottomColor = 'var(--primary-maroon)';
                links[0].style.fontWeight = '700';
            } else {
                reportsTab.style.display = 'none';
                allQuestionsTab.style.display = 'block';
                links[1].classList.add('active');
                links[1].style.color = 'var(--primary-maroon)';
                links[1].style.borderBottomColor = 'var(--primary-maroon)';
                links[1].style.fontWeight = '700';
            }
        }

        // Toggle DB question edit box
        function toggleEditDbQuestion(dbTable, id) {
            const editBox = document.getElementById('edit-db-box-' + dbTable + '-' + id);
            if (editBox) {
                editBox.style.display = editBox.style.display === 'none' ? 'block' : 'none';
            }
        }

        // DB questions filtering
        const dbTableFilter = document.getElementById('dbTableFilter');
        const dbSearch = document.getElementById('dbSearch');

        function applyDbFilters() {
            const tableVal = dbTableFilter.value;
            const searchVal = dbSearch.value.toLowerCase();

            const rows = document.querySelectorAll('#allQuestionsTable tbody tr.db-question-row');
            rows.forEach(row => {
                const rTable = row.getAttribute('data-table');
                const rSearch = row.getAttribute('data-search');

                const matchesTable = !tableVal || rTable === tableVal;
                const matchesSearch = !searchVal || rSearch.includes(searchVal);

                if (matchesTable && matchesSearch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        if (dbTableFilter) dbTableFilter.addEventListener('change', applyDbFilters);
        if (dbSearch) dbSearch.addEventListener('keyup', applyDbFilters);

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // AJAX Db Questions Submission Handler
        document.querySelectorAll('.db-actions-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const submitter = e.submitter;
                if (!submitter) return;

                const action = submitter.value;
                const formData = new FormData(form);
                formData.append('action', action);
                formData.append('ajax', '1');

                const row = form.closest('.db-question-row');

                // Disable submit buttons and show spinner
                form.querySelectorAll('button').forEach(btn => btn.disabled = true);
                const originalHtml = submitter.innerHTML;
                submitter.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                fetch('reported_questions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (action === 'delete_db_question') {
                            row.remove();
                        } else {
                            const qText = formData.get('question_text');
                            const optA = formData.get('option_a');
                            const optB = formData.get('option_b');
                            const optC = formData.get('option_c');
                            const optD = formData.get('option_d');
                            const correctOption = formData.get('correct_option');

                            row.querySelector('.db-q-text').textContent = qText;
                            row.setAttribute('data-search', qText.toLowerCase());
                            
                            // Re-render options in the row
                            const optsContainer = row.querySelector('.db-options-list');
                            const opts = [optA, optB, optC, optD];
                            let optsHtml = '';
                            opts.forEach((optText, oIdx) => {
                                const isCorrect = (String.fromCharCode(65 + oIdx) === correctOption);
                                const optClass = isCorrect ? 'opt-item correct' : 'opt-item';
                                const suffix = isCorrect ? ' [System Key]' : '';
                                optsHtml += `
                                    <li class="${optClass}">
                                        <span><strong>${String.fromCharCode(65 + oIdx)})</strong> ${escapeHtml(optText)}</span>
                                        <span style="font-size: 10px; opacity: 0.8;">${suffix}</span>
                                    </li>
                                `;
                            });
                            optsContainer.innerHTML = optsHtml;

                            // Collapse edit box
                            const dbTable = formData.get('db_table');
                            const questionId = formData.get('question_id');
                            const editBox = document.getElementById('edit-db-box-' + dbTable + '-' + questionId);
                            if (editBox) editBox.style.display = 'none';

                            // Re-enable buttons
                            form.querySelectorAll('button').forEach(btn => btn.disabled = false);
                            submitter.innerHTML = originalHtml;
                        }

                        // Display floating alert
                        const alertBox = document.createElement('div');
                        alertBox.className = 'alert alert-success';
                        alertBox.innerHTML = `<i class="fas fa-check-circle"></i> <span>${data.message}</span>`;
                        document.querySelector('.main-content').insertBefore(alertBox, document.querySelector('.header') || document.querySelector('.page-header'));
                        
                        setTimeout(() => {
                            alertBox.style.transition = 'opacity 0.5s ease';
                            alertBox.style.opacity = '0';
                            setTimeout(() => alertBox.remove(), 500);
                        }, 3000);
                    } else {
                        alert(data.message || 'An error occurred.');
                        form.querySelectorAll('button').forEach(btn => btn.disabled = false);
                        submitter.innerHTML = originalHtml;
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Request failed. Please try again.');
                    form.querySelectorAll('button').forEach(btn => btn.disabled = false);
                    submitter.innerHTML = originalHtml;
                });
            });
        });
    </script>
</body>
</html>
