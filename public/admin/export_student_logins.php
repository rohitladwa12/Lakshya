<?php
/**
 * Export Student Logins CSV Report Exporter
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/Logger.php';

// Require admin role
requireRole(ROLE_ADMIN);

$selectedDiscipline = isset($_GET['discipline']) ? trim($_GET['discipline']) : 'ALL';
$selectedInst = isset($_GET['inst']) ? trim($_GET['inst']) : 'ALL';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Set headers to trigger browser CSV download
$filename = 'student_logins_report_';
if ($selectedDiscipline !== 'ALL') {
    $filename .= strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $selectedDiscipline)) . '_';
}
if ($selectedInst !== 'ALL') {
    $filename .= strtolower($selectedInst) . '_';
}
$filename .= date('Y-m-d_H-i') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// Open the php://output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fputs($output, "\xEF\xBB\xBF");

// Write header columns
fputcsv($output, ['Student Name', 'USN / Username', 'Department / Discipline', 'Institution', 'Total Logins', 'Engagement Status']);

// 1. Fetch exact login counts from local activity_logs with date filters
$db = getDB();
$sql = "SELECT user_id, COUNT(*) as login_count FROM activity_logs WHERE action = 'login' AND user_id IS NOT NULL AND user_id != ''";
$params = [];
if (!empty($startDate)) {
    $sql .= " AND DATE(created_at) >= :start_date";
    $params[':start_date'] = $startDate;
}
if (!empty($endDate)) {
    $sql .= " AND DATE(created_at) <= :end_date";
    $params[':end_date'] = $endDate;
}
$sql .= " GROUP BY user_id";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$loginCounts = [];
while ($row = $stmt->fetch()) {
    $loginCounts[$row['user_id']] = (int)$row['login_count'];
}

// 2. Fetch local GMIT student USNs who are in semesters 5, 6, 7, 8
$gmitSemUsns = $db->query("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE semester IN (5, 6, 7, 8) AND is_current = 1")->fetchAll(PDO::FETCH_COLUMN);

// Keep track of processed USNs to avoid duplicates
$processedUsns = [];

// 3. Stream GMIT students (filtered by semesters 5, 6, 7, 8)
if ($selectedInst === 'ALL' || $selectedInst === 'GMIT') {
    $gmit = getDB('gmit');
    if ($gmit && !empty($gmitSemUsns)) {
        $chunks = array_chunk($gmitSemUsns, 500);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmtGmit = $gmit->prepare("SELECT DISTINCT usn, name, discipline FROM ad_student_details WHERE usn IN ($placeholders) ORDER BY name ASC");
            $stmtGmit->execute($chunk);
            while ($row = $stmtGmit->fetch()) {
                $usn = trim($row['usn']);
                if (empty($usn) || isset($processedUsns[$usn])) continue;
                
                $disc = trim($row['discipline'] ?: 'General');
                if ($selectedDiscipline !== 'ALL' && $disc !== $selectedDiscipline) continue;
                
                $processedUsns[$usn] = true;
                $logins = $loginCounts[$usn] ?? 0;
                $status = $logins > 0 ? 'Active' : 'Inactive';
                fputcsv($output, [
                    trim($row['name']),
                    $usn,
                    $disc,
                    'GMIT',
                    $logins,
                    $status
                ]);
            }
        }
    }
}

// 4. Stream GMU students (filtered by semesters 5, 6, 7, 8)
if ($selectedInst === 'ALL' || $selectedInst === 'GMU') {
    $gmu = getDB('gmu');
    if ($gmu) {
        $stmtGmu = $gmu->query("SELECT DISTINCT usn, name, discipline FROM ad_student_approved WHERE sem IN (5, 6, 7, 8) AND usn IS NOT NULL AND usn != '' ORDER BY name ASC");
        while ($row = $stmtGmu->fetch()) {
            $usn = trim($row['usn']);
            if (empty($usn) || isset($processedUsns[$usn])) continue;
            
            $disc = trim($row['discipline'] ?: 'General');
            if ($selectedDiscipline !== 'ALL' && $disc !== $selectedDiscipline) continue;
            
            $processedUsns[$usn] = true;
            $logins = $loginCounts[$usn] ?? 0;
            $status = $logins > 0 ? 'Active' : 'Inactive';
            fputcsv($output, [
                trim($row['name']),
                $usn,
                $disc,
                'GMU',
                $logins,
                $status
            ]);
        }
    }
}

// Close output stream
fclose($output);
exit;
