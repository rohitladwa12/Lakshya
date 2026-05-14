<?php
$files = [
    'public/student/ai_hr_handler.php',
    'public/student/ai_technical_handler.php',
    'public/student/ai_aptitude_handler.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Fix ob_start()
    $content = preg_replace('/ob_start\(\);/', 'if (ob_get_level() === 0) ob_start();', $content, 1);
    
    // Fix redundant decoding in aptitude handler
    if (basename($file) === 'ai_aptitude_handler.php') {
        $target = '/\$companyName = post\(\'company_name\'\);\s*\$answers = json_decode\(post\(\'answers\'\), true\);\s*\/\/ error_log\(.*?\);\s*\$answers = json_decode\(\$_POST\[\'answers\'\] \?\? \'\[\]\', true\);/s';
        $replacement = "\$answers = json_decode(\$_POST['answers'] ?? '[]', true);";
        $content = preg_replace($target, $replacement, $content);
    }
    
    file_put_contents($file, $content);
    echo "Fixed $file\n";
}
