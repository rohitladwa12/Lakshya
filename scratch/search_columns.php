<?php
$content = file_get_contents(__DIR__ . '/../public/hod/placement_intelligence.php');
$startJs = strpos($content, '<script>');
$endJs = strrpos($content, '</script>');
if ($startJs !== false && $endJs !== false) {
    $js = substr($content, $startJs, $endJs - $startJs);
    $lines = explode("\n", $js);
    foreach ($lines as $idx => $line) {
        if (strpos($line, 'click') !== false || strpos($line, 'open') !== false || strpos($line, 'href') !== false) {
            echo "Line " . ($idx + 1) . ": " . trim($line) . "\n";
        }
    }
}
