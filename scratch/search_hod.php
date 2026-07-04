<?php
$lines = file(__DIR__ . '/../public/hod/placement_intelligence.php');
foreach ($lines as $idx => $line) {
    if (strpos($line, 'reportModal') !== false || strpos($line, 'report-frame') !== false) {
        echo "Line " . ($idx + 1) . ": " . trim($line) . "\n";
    }
}
