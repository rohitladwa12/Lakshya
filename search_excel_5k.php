<?php
require 'vendor/autoload.php';
try {
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $spreadsheet = $reader->load('public/officer/Placement Data For Lakshya Portal.xlsx');
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();
    $headers = array_shift($rows);
    $ctcIdx = array_search('CTC in Lakhs', $headers);
    foreach ($rows as $idx => $row) {
        $val = $row[$ctcIdx] ?? '';
        if (stripos($val, '5k') !== false) {
            echo "ROW " . ($idx + 2) . ": " . var_export($val, true) . "\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
