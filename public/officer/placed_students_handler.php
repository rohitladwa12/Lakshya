<?php
/**
 * Handle Excel/CSV upload for company placed students
 */

ob_start();

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_PLACEMENT_OFFICER);

function sendJsonResponse($data, $statusCode = 200) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// --- Clear All action ---
if (isset($_POST['action']) && $_POST['action'] === 'clear_all') {
    try {
        $model = new CompanyPlacedStudent();
        $model->clearAll();
        sendJsonResponse(['success' => true, 'message' => 'All records cleared.']);
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    sendJsonResponse(['success' => false, 'message' => 'No file uploaded or upload error'], 400);
}

$file = $_FILES['file'];
$allowedTypes = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
    'application/vnd.ms-excel', // xls
    'text/csv',
    'text/plain'
];

$mimeType = mime_content_type($file['tmp_name']);
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
    sendJsonResponse(['success' => false, 'message' => 'Invalid file type. Only CSV, XLSX, XLS allowed'], 400);
}

try {
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $data = [];
    
    if ($extension === 'csv') {
        $handle = fopen($file['tmp_name'], 'r');
        $headers = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== false) {
            $rowData = array_combine($headers, $row);
            $data[] = normalizeData($rowData);
        }
        fclose($handle);
    } else {
        if ($extension === 'xlsx') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        }
        
        $spreadsheet = $reader->load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        
        if (!empty($rows)) {
            $headers = array_shift($rows);
            
            foreach ($rows as $row) {
                if (empty(array_filter($row))) continue;
                $rowData = array_combine($headers, $row);
                $data[] = normalizeData($rowData);
            }
        }
    }
    
    if (empty($data)) {
        sendJsonResponse(['success' => false, 'message' => 'No data found in file']);
    }
    
    $model = new CompanyPlacedStudent();
    $inserted = $model->bulkInsert($data);
    
    sendJsonResponse([
        'success' => true,
        'message' => "Successfully imported {$inserted} records",
        'count' => $inserted
    ]);

} catch (Exception $e) {
    sendJsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}

function formatCtc($val) {
    $val = trim($val);
    if ($val === '') return '';
    
    // If it's a pure numeric value (like 3.6 or 5), format as "X LPA"
    if (is_numeric($val)) {
        return $val . ' LPA';
    }
    
    return $val;
}

function getValueInsensitive($row, $searchKeys) {
    // Check direct matches first
    foreach ($searchKeys as $key) {
        if (isset($row[$key])) {
            return trim($row[$key]);
        }
    }
    
    // Check variations (lowercase, uppercase, spaces replaced by underscores, etc.)
    foreach ($searchKeys as $key) {
        $cleanKey = strtolower(trim($key));
        foreach ($row as $k => $v) {
            $compKey = strtolower(trim($k));
            if ($compKey === $cleanKey) {
                return trim($v);
            }
            // Strip underscores and spaces for comparison
            $compNormalized = str_replace(['_', ' '], '', $compKey);
            $cleanNormalized = str_replace(['_', ' '], '', $cleanKey);
            if ($compNormalized === $cleanNormalized) {
                return trim($v);
            }
        }
    }
    return '';
}

function normalizeData($row) {
    $name = getValueInsensitive($row, ['name', 'Name', 'Student Name']);
    $contact = getValueInsensitive($row, ['contact_no', 'contact', 'Contact No', 'Contact Number', 'phone']);
    $email = getValueInsensitive($row, ['mail_id', 'email', 'mail', 'Mail ID', 'Mail_ID']);
    $usn = getValueInsensitive($row, ['usn', 'USN']);
    
    $yopVal = getValueInsensitive($row, ['yop', 'YOP', 'year', 'Year of Passing']);
    $yop = !empty($yopVal) ? (int)$yopVal : null;
    
    $qualification = getValueInsensitive($row, ['qualification', 'Qualification', 'degree', 'Degree']);
    $specialisation = getValueInsensitive($row, ['specialisation', 'Specialisation', 'specialization', 'Specialization', 'branch', 'Branch']);
    $companyName = getValueInsensitive($row, ['company_name', 'company', 'Company Name', 'Company']);
    $designation = getValueInsensitive($row, ['designation', 'Designation', 'role', 'Role', 'position', 'Position']);
    
    $ctcVal = getValueInsensitive($row, ['ctc_in_lakhs', 'ctc', 'package', 'CTC in Lakhs', 'CTC', 'Package']);
    $ctc = formatCtc($ctcVal);
    
    $gender = getValueInsensitive($row, ['gender', 'Gender']);
    $collegeName = getValueInsensitive($row, ['college_name', 'college', 'College Name', 'institution', 'Institution']);

    return [
        'name' => $name,
        'contact_no' => $contact,
        'mail_id' => $email,
        'usn' => $usn,
        'yop' => $yop,
        'qualification' => $qualification,
        'specialisation' => $specialisation,
        'company_name' => $companyName,
        'designation' => $designation,
        'ctc_in_lakhs' => $ctc,
        'gender' => $gender,
        'college_name' => $collegeName
    ];
}