<?php
/**
 * Script to populate company tags for coding problems in batches using AIService.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/AIService.php';

if (php_sapi_name() !== 'cli' && (!isset($_GET['run']) || $_GET['run'] !== 'secret123')) {
    die("Access Denied.");
}

header('Content-Type: text/plain');
echo "Starting Company Tags Population with high diversity...\n";

$db = getDB();
$aiService = new AIService();

// Reset companies column to ensure fresh generation
$db->exec("UPDATE coding_problems SET companies = NULL");
echo "Reset existing company tags.\n";

// Get all problems
$stmt = $db->query("SELECT id, title, category, difficulty FROM coding_problems");
$problems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($problems);
echo "Found {$total} problems to update.\n";

if ($total === 0) {
    echo "No problems found.\n";
    exit;
}

$batchSize = 25;
$chunks = array_chunk($problems, $batchSize);

foreach ($chunks as $index => $batch) {
    $batchNum = $index + 1;
    $chunkSize = count($batch);
    echo "Processing Batch {$batchNum}/" . count($chunks) . " ({$chunkSize} problems)...\n";

    $problemsData = [];
    foreach ($batch as $p) {
        $problemsData[] = [
            'id' => (int)$p['id'],
            'title' => $p['title'],
            'category' => $p['category'],
            'difficulty' => $p['difficulty']
        ];
    }

    $systemPrompt = "You are an Elite Tech Interview Database Assistant.
Given a list of coding problems, your task is to identify which companies frequently ask each problem in interviews.
For each problem, choose 2 to 4 companies.

CRITICAL INSTRUCTION FOR HIGH DIVERSITY:
Do NOT output the exact same set of companies for every problem. You must show variety!
Select from this diverse set of options:
- Service-based / Consulting (for Easy difficulty or basic DSA): TCS, Infosys, Wipro, Capgemini, Accenture, Cognizant, LTIMindtree, HCLTech. Rotate these randomly so each problem has a unique combination!
- Product / Tech giants (for Medium/Hard difficulty or advanced DSA): Google, Amazon, Microsoft, Meta, Apple, Netflix, Uber, Lyft, Stripe, Adobe, Bloomberg, Salesforce, Oracle, Walmart, Goldman Sachs, JPMorgan, NVIDIA, Cisco.
- Domain-specific logic:
  - If it is related to routing/networking/graphs, prioritize Cisco, Netflix, Uber, Google.
  - If it is related to financial/math/numbers, prioritize Goldman Sachs, Bloomberg, JPMorgan.
  - If it is related to databases/design/matrix, prioritize Oracle, Microsoft, Amazon.
  - If it is related to rate limiters/API, prioritize Stripe, Airbnb, Twitter.
  - If it is related to arrays/strings (medium), prioritize Meta, Adobe, Apple, Amazon.

Return a JSON object mapping each problem's ID to a comma-separated string of these company names.
Example Output Format:
{
  \"1\": \"TCS, Infosys, Cognizant\",
  \"2\": \"Goldman Sachs, Bloomberg, Google\",
  \"3\": \"Wipro, Accenture, LTIMindtree\"
}
Return ONLY valid JSON. Do not include markdown formatting or extra text.";

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => json_encode($problemsData, JSON_PRETTY_PRINT)]
    ];

    $response = $aiService->callAPI($messages, [
        'audit_method' => 'populate_companies_batch_diverse',
        'response_format' => ['type' => 'json_object'],
        'max_tokens' => 2000,
        'temperature' => 0.7 // Increase temperature for diversity
    ]);

    if ($response['success']) {
        $parsed = is_array($response['parsed']) ? $response['parsed'] : json_decode($response['content'], true);
        if (is_array($parsed)) {
            $db->beginTransaction();
            try {
                $updateStmt = $db->prepare("UPDATE coding_problems SET companies = ? WHERE id = ?");
                foreach ($parsed as $id => $companies) {
                    $updateStmt->execute([trim($companies), (int)$id]);
                }
                $db->commit();
                echo "✅ Batch {$batchNum} successfully updated in database.\n";
            } catch (Exception $dbEx) {
                $db->rollBack();
                echo "❌ Database error in Batch {$batchNum}: " . $dbEx->getMessage() . "\n";
            }
        } else {
            echo "❌ Failed to parse response for Batch {$batchNum} as array.\n";
        }
    } else {
        echo "❌ AI service call failed for Batch {$batchNum}.\n";
    }
}

echo "Company tags population finished!\n";
