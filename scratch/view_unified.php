<?php
$content = file_get_contents(__DIR__ . '/../public/student/dashboard.php');
$lines = explode("\n", $content);
foreach ($lines as $idx => $line) {
    if (strpos($line, 'unified_ai_assessments') !== false || strpos($line, 'report') !== false || strpos($line, 'pdf') !== false) {
        echo "Line " . ($idx + 1) . ": " . trim($line) . "\n";
    }
}
