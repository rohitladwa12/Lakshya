<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../src/Services/AIService.php';

$db = getDB();
$aiService = new AIService();

// Fetch problems with short statements
$stmt = $db->query("SELECT id, title, category, difficulty, problem_statement FROM coding_problems WHERE LENGTH(problem_statement) < 200");
$problems = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($problems) . " problems to enrich.\n\n";

$updateCount = 0;
foreach ($problems as $p) {
    echo "Enriching [{$p['id']}] {$p['title']}...\n";

    $prompt = "Provide a detailed, clear, and comprehensive coding problem statement for the following problem:\n"
            . "Title: {$p['title']}\n"
            . "Category: {$p['category']}\n"
            . "Difficulty: {$p['difficulty']}\n"
            . "Basic Description: {$p['problem_statement']}\n\n"
            . "Instructions:\n"
            . "- Explain the logic, rules, and scenarios of the task thoroughly and step-by-step so a student can understand it.\n"
            . "- Explain what the function should accept as input and what it should return.\n"
            . "- Do NOT include constraints or sample input/output sections since they are handled separately in the UI.\n"
            . "- Do NOT include markdown headers like '# Problem Statement' or '## Description'. Just output the raw, detailed paragraphs of the problem statement.\n"
            . "- Keep the tone professional, educational, and clean.";

    $messages = [
        ['role' => 'system', 'content' => 'You are an expert technical writer and computer science educator. Write clear, detailed, and comprehensive coding problem statements.'],
        ['role' => 'user', 'content' => $prompt]
    ];

    $response = $aiService->callAPI($messages, [
        'max_tokens' => 600,
        'temperature' => 0.7,
        'audit_method' => 'enrich_problem_statement'
    ]);

    if ($response['success'] && !empty($response['content'])) {
        $detailedStatement = trim($response['content']);
        
        $update = $db->prepare("UPDATE coding_problems SET problem_statement = ? WHERE id = ?");
        $update->execute([$detailedStatement, $p['id']]);
        
        echo "Successfully updated [{$p['id']}] {$p['title']}.\n";
        $updateCount++;
    } else {
        echo "Failed to generate detailed statement for [{$p['id']}] {$p['title']}: " . ($response['message'] ?? 'Unknown error') . "\n";
    }

    // Sleep briefly to be respectful to API rate limits
    usleep(100000); // 100ms
}

echo "\nEnrichment complete. Total updated: {$updateCount} problems.\n";
