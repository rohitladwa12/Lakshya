<?php
require_once __DIR__ . '/../config/bootstrap.php';

echo "--- SYSTEM ERROR DIAGNOSTIC ---\n\n";

// 1. Check AI Failures
$db = getDB();
$fails = $db->query("SELECT COUNT(*) as count FROM ai_audit_logs WHERE status = 'failure'")->fetch()['count'];
echo "Total AI Failures: $fails\n";
if ($fails > 0) {
    echo "Recent AI Errors:\n";
    $recentFails = $db->query("SELECT service_method, error_message, created_at FROM ai_audit_logs WHERE status = 'failure' ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($recentFails as $f) {
        echo "- [" . $f['created_at'] . "] " . $f['service_method'] . ": " . substr($f['error_message'], 0, 100) . "...\n";
    }
}

echo "\n--- LOG FILE SCAN ---\n";

$logFiles = [
    'logs/app.log',
    'logs/ai_worker.log',
    'logs/error.log'
];

foreach ($logFiles as $file) {
    $path = ROOT_PATH . '/' . $file;
    if (file_exists($path)) {
        echo "\nFile: $file\n";
        // Get last 10 lines of errors or warnings
        $content = file_get_contents($path);
        $lines = explode("\n", $content);
        $errorLines = array_filter($lines, function($line) {
            return stripos($line, 'error') !== false || stripos($line, 'warning') !== false || stripos($line, 'fatal') !== false;
        });
        $recentErrors = array_slice($errorLines, -10);
        if (empty($recentErrors)) {
            echo "  No errors found in last 800 lines.\n";
        } else {
            foreach ($recentErrors as $err) {
                echo "  " . trim($err) . "\n";
            }
        }
    } else {
        echo "\nFile: $file (Not found)\n";
    }
}

echo "\n--- DB INTEGRITY CHECK ---\n";
try {
    $db->query("SELECT 1 FROM unified_ai_assessments LIMIT 1");
    echo "  unified_ai_assessments: OK\n";
    $db->query("SELECT 1 FROM ai_audit_logs LIMIT 1");
    echo "  ai_audit_logs: OK\n";
} catch (Exception $e) {
    echo "  DB Error: " . $e->getMessage() . "\n";
}

echo "\nDiagnostic Complete.\n";
