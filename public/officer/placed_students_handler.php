<?php
/**
 * Handle Excel/CSV upload for company placed students
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_PLACEMENT_OFFICER);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// --- Clear All action ---
if (isset($_POST['action']) && $_POST['action'] === 'clear_all') {
    try {
        $model = new CompanyPlacedStudent();
        $model->clearAll();
        echo json_encode(['success' => true, 'message' => 'All records cleared.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
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
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only CSV, XLSX, XLS allowed']);
    exit;
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
        echo json_encode(['success' => false, 'message' => 'No data found in file']);
        exit;
    }
    
    $model = new CompanyPlacedStudent();
    $inserted = $model->bulkInsert($data);
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully imported {$inserted} records",
        'count' => $inserted
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function normalizeData($row) {
    return [
        'name' => isset($row['name']) ? trim($row['name']) : (isset($row['Name']) ? trim($row['Name']) : ''),
        'contact_no' => isset($row['contact_no']) ? trim($row['contact_no']) : (isset($row['contact']) ? trim($row['contact']) : ''),
        'mail_id' => isset($row['mail_id']) ? trim($row['mail_id']) : (isset($row['email']) ? trim($row['email']) : (isset($row['mail']) ? trim($row['mail']) : '')),
        'usn' => isset($row['usn']) ? trim($row['usn']) : (isset($row['USN']) ? trim($row['USN']) : ''),
        'yop' => isset($row['yop']) ? (int)$row['yop'] : (isset($row['YOP']) ? (int)$row['YOP'] : (isset($row['year']) ? (int)$row['year'] : null)),
        'qualification' => isset($row['qualification']) ? trim($row['qualification']) : (isset($row['Qualification']) ? trim($row['Qualification']) : (isset($row['degree']) ? trim($row['degree']) : '')),
        'specialisation' => isset($row['specialisation']) ? trim($row['specialisation']) : (isset($row['Specialisation']) ? trim($row['Specialisation']) : (isset($row['specialization']) ? trim($row['specialization']) : (isset($row['branch']) ? trim($row['branch']) : ''))),
        'company_name' => isset($row['company_name']) ? trim($row['company_name']) : (isset($row['Company']) ? trim($row['Company']) : (isset($row['company']) ? trim($row['company']) : '')),
        'designation' => isset($row['designation']) ? trim($row['designation']) : (isset($row['Designation']) ? trim($row['Designation']) : (isset($row['position']) ? trim($row['position']) : (isset($row['role']) ? trim($row['role']) : ''))),
        'ctc_in_lakhs' => isset($row['ctc_in_lakhs']) ? (float)$row['ctc_in_lakhs'] : (isset($row['ctc']) ? (float)$row['ctc'] : (isset($row['package']) ? (float)$row['package'] : 0)),
        'gender' => isset($row['gender']) ? trim($row['gender']) : (isset($row['Gender']) ? trim($row['Gender']) : ''),
        'college_name' => isset($row['college_name']) ? trim($row['college_name']) : (isset($row['college']) ? trim($row['college']) : (isset($row['institution']) ? trim($row['institution']) : ''))
    ];
}