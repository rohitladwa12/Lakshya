<?php
/**
 * Pre-populate AI Detailed Solutions & Explanations for Coding Problems
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/AIService.php';

$db = getDB();
$aiService = new AIService();

// Get all problems where solution_beginner is NULL or empty
$stmt = $db->query("SELECT id, title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity FROM coding_problems WHERE solution_beginner IS NULL OR solution_beginner = '' ORDER BY id ASC");
$problems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($problems);
echo "Found $total problems needing detailed AI solution generation.\n";

$updateStmt = $db->prepare("UPDATE coding_problems SET solution_beginner = ?, solution_optimized = ? WHERE id = ?");

$processed = 0;
$failed = 0;

foreach ($problems as $p) {
    echo "Processing [ID: {$p['id']}] {$p['title']}... ";
    
    try {
        $result = $aiService->generateCodingSolution($p);
        
        if ($result['success'] && isset($result['parsed']['solutions'])) {
            $solutions = $result['parsed']['solutions'];
            
            $updateStmt->execute([
                json_encode($solutions['beginner']),
                json_encode($solutions['optimized']),
                $p['id']
            ]);
            
            $processed++;
            echo "DONE!\n";
        } else {
            $failed++;
            echo "FAILED (API Response error)\n";
        }
    } catch (\Throwable $e) {
        $failed++;
        echo "ERROR: " . $e->getMessage() . "\n";
    }

    // Small delay to prevent API rate limiting
    usleep(200000); // 0.2s
}

echo "\n==========================================\n";
echo "Pre-population Complete!\n";
echo "Successfully Processed & Saved: $processed\n";
echo "Failed/Skipped: $failed\n";
