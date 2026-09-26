<?php
/**
 * Applications Management Page - Placement Officer
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$pageId = 'officer_applications';

// Handle POST State (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SessionFilterHelper::handlePostToSession($pageId, $_POST);
    header("Location: applications.php");
    exit;
}

// Retrieve from Session
$filters = SessionFilterHelper::getFilters($pageId);
$appModel = new JobApplication();
$jobModel = new JobPosting();

$jobId = $filters['job_id'] ?? null;
$statusFilter = $filters['status'] ?? null;
$companyId = $filters['company_id'] ?? null;
$semester = $filters['semester'] ?? null;
$minSgpa = $filters['min_sgpa'] ?? null;
$minSslc = $filters['min_sslc'] ?? null;
$minPuc = $filters['min_puc'] ?? null;

// Get all applications with detailed info for filtering
// 1. Fetch applications with basic job/company info from LOCAL DB
$sql = "SELECT ja.*, jp.title as job_title, c.name as company_name, c.id as company_id
        FROM job_applications ja
        JOIN job_postings jp ON ja.job_id = jp.id
        JOIN companies c ON jp.company_id = c.id";

$where = [];
$params = [];
if ($jobId) {
    $where[] = "ja.job_id = ?";
    $params[] = (int)$jobId;
}
if ($statusFilter) {
    $where[] = "ja.status = ?";
    $params[] = $statusFilter;
}
if ($companyId) {
    $where[] = "jp.company_id = ?";
    $params[] = (int)$companyId;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY ja.applied_at DESC";
$stmt = $appModel->getDB()->prepare($sql);
$stmt->execute($params);
$rawApps = $stmt->fetchAll();

// 2. Enrich with student details from REMOTE/LOCAL DBs and apply academic filters
$userModel = new User();
$applications = [];

foreach ($rawApps as $app) {
    // Fetch student info using User model which handles remote switches
    $student = $userModel->findByUsername($app['student_id']) ?: $userModel->find($app['student_id']);
    
    if ($student) {
        $app['student_name'] = $student['full_name'];
        $app['usn'] = $student['username'];
        $app['institution'] = $student['institution'];
        $app['gender'] = $student['gender'] ?? 'N/A';
        $app['aadhar'] = $student['aadhar'] ?? null;
        
        // Fetch academic details (Remote or Local fallback)
        $inst = $student['institution'];
        $prefix = ($inst === INSTITUTION_GMU) ? DB_GMU_PREFIX : DB_GMIT_PREFIX;
        
        // Basic enrichment defaults
        $app['academic_sgpa'] = 0.0;
        $app['course'] = 'N/A';
        $app['branch'] = 'N/A';
        $app['puc_percentage'] = 0.0;
        $app['sslc_percentage'] = 0.0;
        $app['current_semester'] = null;

        try {
            if ($app['applied_semester'] !== null && $app['applied_sgpa'] !== null) {
                $app['current_semester'] = $app['applied_semester'];
                $app['academic_sgpa'] = $app['applied_sgpa'];
                
                // Fetch only basic details (percentages, course, branch, gender)
                if ($inst === INSTITUTION_GMU) {
                    $remoteDB = getDB('gmu');
                    $stmtAc = $remoteDB->prepare("SELECT a.course, a.discipline, d.puc_percentage, d.sslc_percentage, d.gender
                                               FROM {$prefix}ad_student_approved a
                                               LEFT JOIN {$prefix}ad_student_details d ON (a.usn = d.student_id OR a.usn = d.usn)
                                               WHERE a.usn = ? LIMIT 1");
                    $stmtAc->execute([$app['usn']]);
                    $ac = $stmtAc->fetch();
                    if ($ac) {
                        $app['course'] = $ac['course'];
                        $app['branch'] = $ac['discipline'];
                        $app['puc_percentage'] = $ac['puc_percentage'];
                        $app['sslc_percentage'] = $ac['sslc_percentage'];
                        if (!empty($ac['gender'])) $app['gender'] = $ac['gender'];
                    }
                } else {
                    $remoteDB = getDB('gmit');
                    $stmtDet = $remoteDB->prepare("SELECT puc_percentage, sslc_percentage, course, discipline, gender FROM {$prefix}ad_student_details WHERE enquiry_no = ? OR student_id = ? LIMIT 1");
                    $stmtDet->execute([$app['student_id'], $app['usn']]);
                    $det = $stmtDet->fetch();
                    if ($det) {
                        $app['puc_percentage'] = $det['puc_percentage'];
                        $app['sslc_percentage'] = $det['sslc_percentage'];
                        $app['course'] = $det['course'];
                        $app['branch'] = $det['discipline'];
                        if (!empty($det['gender'])) $app['gender'] = $det['gender'];
                    }
                }
            } else {
                // Fallback to legacy dynamic lookup if not populated
                if ($inst === INSTITUTION_GMU) {
                    // GMU: Fetch from remote
                    $remoteDB = getDB('gmu');
                    
                    // Get current semester
                    $stmtSem = $remoteDB->prepare("SELECT sem FROM {$prefix}ad_student_approved WHERE usn = ? ORDER BY academic_year DESC, sem DESC LIMIT 1");
                    $stmtSem->execute([$app['usn']]);
                    $semRow = $stmtSem->fetch();
                    $app['current_semester'] = $semRow ? $semRow['sem'] : null;
    
                    // Get details and latest non-null/non-zero SGPA
                    $stmtAc = $remoteDB->prepare("SELECT a.sgpa, a.course, a.discipline, d.puc_percentage, d.sslc_percentage, d.gender
                                               FROM {$prefix}ad_student_approved a
                                               LEFT JOIN {$prefix}ad_student_details d ON (a.usn = d.student_id OR a.usn = d.usn)
                                               WHERE a.usn = ? AND a.sgpa IS NOT NULL AND a.sgpa > 0.00
                                               ORDER BY a.academic_year DESC, a.sem DESC LIMIT 1");
                    $stmtAc->execute([$app['usn']]);
                    $ac = $stmtAc->fetch();
                    if ($ac) {
                        $app['academic_sgpa'] = $ac['sgpa'];
                        $app['course'] = $ac['course'];
                        $app['branch'] = $ac['discipline'];
                        $app['puc_percentage'] = $ac['puc_percentage'];
                        $app['sslc_percentage'] = $ac['sslc_percentage'];
                        if (!empty($ac['gender'])) $app['gender'] = $ac['gender'];
                    } else {
                        // Fallback to any record (even if SGPA is null/0) to get course/percentages
                        $stmtFallback = $remoteDB->prepare("SELECT a.sgpa, a.course, a.discipline, d.puc_percentage, d.sslc_percentage, d.gender
                                                    FROM {$prefix}ad_student_approved a
                                                    LEFT JOIN {$prefix}ad_student_details d ON (a.usn = d.student_id OR a.usn = d.usn)
                                                    WHERE a.usn = ? 
                                                    ORDER BY a.academic_year DESC, a.sem DESC LIMIT 1");
                        $stmtFallback->execute([$app['usn']]);
                        $fb = $stmtFallback->fetch();
                        if ($fb) {
                            $app['academic_sgpa'] = $fb['sgpa'] ?: 0.00;
                            $app['course'] = $fb['course'];
                            $app['branch'] = $fb['discipline'];
                            $app['puc_percentage'] = $fb['puc_percentage'];
                            $app['sslc_percentage'] = $fb['sslc_percentage'];
                            if (!empty($fb['gender'])) $app['gender'] = $fb['gender'];
                        }
                    }
                } else {
                    // GMIT: Fetch academic history from local SGPA tracker
                    // First, get the current semester
                    $enrichParams = [$app['usn']];
                    $sqlCurr = "SELECT semester FROM student_sem_sgpa WHERE (student_id = ?";
                    if (!empty($app['aadhar'])) {
                        $sqlCurr .= " OR student_id = ?";
                        $enrichParams[] = $app['aadhar'];
                    }
                    $sqlCurr .= ") AND institution = ? AND is_current = 1 LIMIT 1";
                    $enrichParams[] = INSTITUTION_GMIT;
                    $stmtCurr = $appModel->getDB()->prepare($sqlCurr);
                    $stmtCurr->execute($enrichParams);
                    $currSemRow = $stmtCurr->fetch();
                    $app['current_semester'] = $currSemRow ? $currSemRow['semester'] : null;
    
                    // Then get the latest semester SGPA that is > 0
                    $enrichParams2 = [$app['usn']];
                    $sqlAc = "SELECT sgpa FROM student_sem_sgpa WHERE (student_id = ?";
                    if (!empty($app['aadhar'])) {
                        $sqlAc .= " OR student_id = ?";
                        $enrichParams2[] = $app['aadhar'];
                    }
                    $sqlAc .= ") AND institution = ? AND sgpa > 0.00 ORDER BY semester DESC LIMIT 1";
                    $enrichParams2[] = INSTITUTION_GMIT;
                    $stmtAc = $appModel->getDB()->prepare($sqlAc);
                    $stmtAc->execute($enrichParams2);
                    $ac = $stmtAc->fetch();
                    if ($ac) {
                        $app['academic_sgpa'] = $ac['sgpa'];
                    } else {
                        // Fallback to current sem SGPA if all are 0
                        $enrichParams3 = [$app['usn']];
                        $sqlAcFallback = "SELECT sgpa FROM student_sem_sgpa WHERE (student_id = ?";
                        if (!empty($app['aadhar'])) {
                            $sqlAcFallback .= " OR student_id = ?";
                            $enrichParams3[] = $app['aadhar'];
                        }
                        $sqlAcFallback .= ") AND institution = ? AND is_current = 1 LIMIT 1";
                        $enrichParams3[] = INSTITUTION_GMIT;
                        $stmtAcFallback = $appModel->getDB()->prepare($sqlAcFallback);
                        $stmtAcFallback->execute($enrichParams3);
                        $acFallback = $stmtAcFallback->fetch();
                        $app['academic_sgpa'] = $acFallback ? $acFallback['sgpa'] : 0.00;
                    }
                    
                    // Fetch puc/sslc from remote GMIT details
                    $remoteDB = getDB('gmit');
                    $stmtDet = $remoteDB->prepare("SELECT puc_percentage, sslc_percentage, course, discipline, gender FROM {$prefix}ad_student_details WHERE enquiry_no = ? OR student_id = ? LIMIT 1");
                    $stmtDet->execute([$app['student_id'], $app['usn']]);
                    $det = $stmtDet->fetch();
                    if ($det) {
                        $app['puc_percentage'] = $det['puc_percentage'];
                        $app['sslc_percentage'] = $det['sslc_percentage'];
                        $app['course'] = $det['course'];
                        $app['branch'] = $det['discipline'];
                        if (!empty($det['gender'])) $app['gender'] = $det['gender'];
                    }
                }
            }
        } catch (Exception $e) { /* ignore detail fetch errors */ }

        // Apply PHP-side academic filters
        if ($semester && $app['current_semester'] != $semester) continue;
        if ($minSgpa && $app['academic_sgpa'] < $minSgpa) continue;
        if ($minSslc && $app['sslc_percentage'] < $minSslc) continue;
        if ($minPuc && $app['puc_percentage'] < $minPuc) continue;

        $applications[] = $app;
    } else {
        // Student not found in either DB - definitely an orphaned application
        // Hide these entirely from the dashboard as requested by the user
        continue;
    }
}

$allCompanies = $appModel->getDB()->query("SELECT * FROM companies ORDER BY name ASC")->fetchAll();

$allJobs = $jobModel->getAllWithCompany('title ASC');
$fullName = getFullName();

// Fetch all semester SGPAs & Current Sem for the applications
$allSgpas = [];
$currentSems = [];
$studentIdentifiers = [];
$studentMapById = [];

foreach ($applications as $app) {
    if (!empty($app['usn'])) {
        $studentIdentifiers[] = $app['usn'];
        $studentMapById[$app['usn']] = $app['usn'];
    }
    if (!empty($app['aadhar'])) {
        $studentIdentifiers[] = $app['aadhar'];
        $studentMapById[$app['aadhar']] = $app['usn'];
    }
}
$studentIdentifiers = array_values(array_unique(array_filter($studentIdentifiers)));

if (!empty($studentIdentifiers)) {
    $placeholders = implode(',', array_fill(0, count($studentIdentifiers), '?'));
    $sqlSgpa = "SELECT student_id, semester, sgpa, is_current FROM student_sem_sgpa WHERE student_id IN ($placeholders)";
    $stmtSgpa = $appModel->getDB()->prepare($sqlSgpa);
    $stmtSgpa->execute($studentIdentifiers);
    $sgpaRaw = $stmtSgpa->fetchAll();
    foreach ($sgpaRaw as $row) {
        $mappedUsn = $studentMapById[$row['student_id']] ?? $row['student_id'];
        $allSgpas[$mappedUsn][$row['semester']] = $row['sgpa'];
        if ($row['is_current']) {
            $currentSems[$mappedUsn] = $row['semester'];
        }
    }
}

// Fetch drive scores for each application if it's tied to a drive
$driveScores = [];
if (!empty($applications)) {
    // Collect unique job IDs to find which ones are drives
    $jobIds = array_values(array_unique(array_column($applications, 'job_id')));
    if (!empty($jobIds)) {
        $inJobIds = implode(',', array_fill(0, count($jobIds), '?'));
        $stmtDrives = $appModel->getDB()->prepare("SELECT id, job_id FROM campus_drives WHERE job_id IN ($inJobIds)");
        $stmtDrives->execute($jobIds);
        $drivesMap = []; // job_id => drive_id
        while ($row = $stmtDrives->fetch()) {
            $drivesMap[$row['job_id']] = $row['id'];
        }

        if (!empty($drivesMap)) {
            $driveIds = array_values($drivesMap);
            $inDriveIds = implode(',', array_fill(0, count($driveIds), '?'));
            
            // Get all scores per round per student for these drives
            $sqlScores = "
                SELECT drive_id, student_id, round_type, score, attempt_number
                FROM student_drive_attempts
                WHERE drive_id IN ($inDriveIds) AND score IS NOT NULL
                ORDER BY attempt_number ASC
            ";
            $stmtScores = $appModel->getDB()->prepare($sqlScores);
            $stmtScores->execute($driveIds);
            while ($row = $stmtScores->fetch()) {
                $driveScores[$row['drive_id']][$row['student_id']][$row['round_type']][] = $row['score'];
            }
        }
        
        // Attach scores to applications
        foreach ($applications as &$app) {
            $jId = $app['job_id'];
            $app['drive_scores'] = [];
            if (isset($drivesMap[$jId])) {
                $dId = $drivesMap[$jId];
                if (isset($driveScores[$dId][$app['usn']])) {
                    $app['drive_scores'] = $driveScores[$dId][$app['usn']];
                }
            }
        }
        unset($app);
    }
}

function formatResponsesForPrint($json) {
    if (empty($json)) return '';
    $data = json_decode($json, true);
    if (empty($data)) return '';
    $out = '<div class="print-responses" style="margin-top: 5px; border-top: 1px dashed #ccc; padding-top: 5px;">';
    $out .= '<div style="font-weight: bold; font-size: 11px; color: #666; margin-bottom: 3px;">CUSTOM RESPONSES:</div>';
    foreach ($data as $resp) {
        $val = $resp['value'] ?? 'N/A';
        if ($resp['type'] === 'file' && !empty($val)) $val = '[Document Uploaded]';
        $out .= '<div style="font-size: 11px; margin-bottom: 2px;">';
        $out .= '<strong>' . htmlspecialchars($resp['label']) . ':</strong> ' . htmlspecialchars($val);
        $out .= '</div>';
    }
    $out .= '</div>';
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applications - <?php echo APP_NAME; ?></title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        :root {
            --brand: #7C0000;
            --brand-hover: #9E0000;
            --brand-light: #FDF2F2;
            --gold: #B08D2C;
            --surface-bg: #FFFFFF;
            --page-bg: #F8F9FA;
            --border-color: #E5E7EB;
            --border-subtle: #F3F4F6;
            --text-primary: #111827;
            --text-secondary: #4B5563;
            --text-muted: #9CA3AF;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-card: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.02);
            --shadow-modal: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--page-bg);
            color: var(--text-primary);
            margin: 0;
            padding-top: 0;
            line-height: 1.5;
        }

        .o-page {
            max-width: 1440px;
            margin: 0 auto;
            padding: 28px 32px 60px 32px;
            box-sizing: border-box;
        }

        .o-head {
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
        }

        .o-head h1 {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.4px;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }

        /* Filter Card */
        .filter-glass {
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-card);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }

        .filter-item label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-item select, .filter-item input {
            width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #D1D5DB;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.15s ease;
            background: #FFFFFF;
            color: var(--text-primary);
            box-sizing: border-box;
        }

        .filter-item select:focus, .filter-item input:focus {
            border-color: var(--brand);
            outline: none;
            box-shadow: 0 0 0 3px rgba(124, 0, 0, 0.1);
        }

        .filter-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border-subtle);
        }

        /* Table Design */
        .table-card {
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }

        .modern-table th {
            text-align: left;
            padding: 12px 16px;
            background: #F9FAFB;
            color: var(--text-secondary);
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .modern-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-subtle);
            font-size: 13px;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .modern-table tr:last-child td { border: none; }
        .modern-table tr:hover td { background: #FBFBFC; }

        .job-title { font-weight: 600; color: var(--text-primary); margin-bottom: 2px; }
        .comp-name { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        /* Status Pills */
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
        }
        .st-applied { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
        .st-shortlisted { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
        .st-selected { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .st-rejected { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

        .status-select {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #D1D5DB;
            font-size: 12px;
            font-weight: 600;
            background: #FFFFFF;
            color: var(--text-primary);
            cursor: pointer;
            outline: none;
        }
        .status-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 2px rgba(124, 0, 0, 0.1);
        }

        /* Buttons */
        .btn-action {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            box-sizing: border-box;
        }

        .btn-primary { 
            background: var(--brand); 
            color: #FFFFFF; 
            border: 1px solid var(--brand);
            box-shadow: 0 1px 2px rgba(124, 0, 0, 0.2); 
        }
        .btn-primary:hover { 
            background: var(--brand-hover); 
            border-color: var(--brand-hover);
        }

        .btn-excel { 
            background: #059669; 
            color: #FFFFFF; 
            border: 1px solid #059669;
        }
        .btn-excel:hover { 
            background: #047857; 
        }

        .btn-view { 
            background: #F3F4F6; 
            color: var(--text-secondary); 
            border: 1px solid var(--border-color);
        }
        .btn-view:hover { 
            background: #E5E7EB; 
            color: var(--text-primary); 
        }

        .usn-badge {
            background: #F3F4F6;
            color: #374151;
            padding: 2px 7px;
            border-radius: 6px;
            font-family: inherit;
            font-weight: 600;
            font-size: 11.5px;
        }

        .pagination { display: flex; justify-content: center; gap: 6px; padding: 24px; }
        .page-link {
            padding: 8px 14px;
            border-radius: 8px;
            background: #FFFFFF;
            border: 1px solid #D1D5DB;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.15s ease;
        }
        .page-link.active { background: var(--brand); color: #FFFFFF; border-color: var(--brand); }
        .page-link:hover:not(.active) { border-color: var(--brand); color: var(--brand); }

        /* Modal Architecture */
        .modal { 
            display: none; 
            position: fixed; 
            inset: 0; 
            background: rgba(17, 24, 39, 0.45); 
            backdrop-filter: blur(4px); 
            z-index: 2000; 
            align-items: center; 
            justify-content: center; 
            padding: 20px; 
            box-sizing: border-box;
        }

        .modal-content { 
            background: #FFFFFF; 
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg); 
            padding: 28px 30px; 
            width: 100%; 
            max-width: 620px; 
            position: relative; 
            box-shadow: var(--shadow-modal); 
            box-sizing: border-box;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.2px;
        }

        .close-modal { 
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #F3F4F6;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px; 
            cursor: pointer; 
            transition: all 0.15s ease;
            line-height: 1;
        }

        .close-modal:hover {
            background: #E5E7EB;
            color: var(--text-primary);
        }

        .print-only { display: none; }
        @media print {
            .no-print, header, nav, .navbar, .screen-only { display: none !important; }
            .print-only { display: block !important; }
            body { background: white !important; padding-top: 0 !important; color: black !important; }
            .o-page { padding: 0 !important; margin: 0 !important; max-width: none !important; }
            .o-head { display: none !important; }
            .table-card { border: none !important; box-shadow: none !important; background: transparent !important; overflow: visible !important; }
            .modern-table { border-collapse: collapse !important; width: 100% !important; border: 1px solid black !important; }
            .modern-table th, .modern-table td { 
                border: 1px solid #000 !important; 
                padding: 4px 6px !important; 
                font-size: 10px !important; 
                color: black !important;
                background: white !important;
                text-align: left !important;
            }
            .modern-table th { font-weight: bold !important; }
            .usn-badge, .status-pill { 
                background: transparent !important; 
                color: black !important; 
                border: none !important; 
                padding: 0 !important; 
                font-weight: normal !important;
            }
        }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="o-page">
        <div class="o-head">
            <div>
                <h1>Application Tracker</h1>
                <p style="color: var(--text-muted); font-size: 14px; margin-top: 5px;">Managing <strong><?php echo count($applications); ?></strong> student applications</p>
            </div>
            <div style="display: flex; gap: 12px;">
                <button onclick="exportToExcel()" class="btn-action btn-excel no-print">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button onclick="window.print()" class="btn-action btn-primary no-print">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <form method="POST" class="filter-glass no-print">
            <div class="filter-grid">
                <div class="filter-item">
                    <label>Semester</label>
                    <select name="semester" onchange="this.form.submit()">
                        <option value="">All Semesters</option>
                        <?php for($i=1; $i<=8; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $semester == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Company</label>
                    <select name="company_id" onchange="this.form.submit()">
                        <option value="">All Companies</option>
                        <?php foreach ($allCompanies as $comp): ?>
                        <option value="<?php echo $comp['id']; ?>" <?php echo $companyId == $comp['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($comp['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Job Role</label>
                    <select name="job_id" onchange="this.form.submit()">
                        <option value="">All Roles</option>
                        <?php foreach ($allJobs as $job): ?>
                        <option value="<?php echo $job['id']; ?>" <?php echo $jobId == $job['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($job['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Min SGPA</label>
                    <input type="number" step="0.01" name="min_sgpa" value="<?php echo htmlspecialchars($minSgpa); ?>" placeholder="e.g. 7.5">
                </div>
                <div class="filter-item">
                    <label>Min 10th %</label>
                    <input type="number" step="0.01" name="min_sslc" value="<?php echo htmlspecialchars($minSslc); ?>" placeholder="0">
                </div>
                <div class="filter-item">
                    <label>Min 12th %</label>
                    <input type="number" step="0.01" name="min_puc" value="<?php echo htmlspecialchars($minPuc); ?>" placeholder="0">
                </div>
            </div>
            <div class="filter-footer">
                <div style="font-size: 13px; color: var(--text-muted);">
                    Showing <strong><?php echo count($applications); ?></strong> applications
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="reset_filters" value="1" class="btn-action" style="background: transparent; color: var(--text-muted);">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="submit" class="btn-action btn-primary">
                        <i class="fas fa-check"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>

        <div class="table-card screen-only">
            <div style="overflow-x: auto;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>USN</th>
                            <th>Inst</th>
                            <th>Course</th>
                            <th>Branch</th>
                            <th>Sem</th>
                            <th>Job Role</th>
                            <th>Company</th>
                            <th>SGPA</th>
                            <th>10th %</th>
                            <th>12th %</th>
                            <th>Applied</th>
                            <th class="no-print">Status</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $limit = 10;
                        $total_records = count($applications);
                        $total_pages = ceil($total_records / $limit);
                        $page = $filters['page'] ?? 1;
                        if ($page < 1) $page = 1;
                        $offset = ($page - 1) * $limit;
                        $pagedApps = array_slice($applications, $offset, $limit);

                        foreach ($pagedApps as $app): 
                            $statusClass = 'st-' . strtolower($app['status']);
                        ?>
                        <tr data-sems='<?php echo json_encode($allSgpas[$app['usn']] ?? []); ?>' data-resume-link="<?php echo $app['resume_path'] ? (APP_URL . '/student/view_resume.php?usn=' . urlencode($app['usn'])) : ''; ?>">
                            <td>
                                <div class="job-title"><?php echo htmlspecialchars($app['student_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div class="usn-badge"><?php echo htmlspecialchars($app['usn'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($app['institution'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-size: 13px; font-weight: 600;"><?php echo htmlspecialchars($app['course'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-size: 13px; font-weight: 600;"><?php echo htmlspecialchars($app['branch'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($app['current_semester'] ?? '?'); ?></div>
                            </td>
                            <td>
                                <div class="job-title" style="font-size: 13px;"><?php echo htmlspecialchars($app['job_title'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div class="comp-name"><?php echo htmlspecialchars($app['company_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #10b981;"><?php echo $app['academic_sgpa'] ? number_format($app['academic_sgpa'], 2) : '-'; ?></div>
                            </td>
                            <td>
                                <div style="font-size: 13px;"><?php echo $app['sslc_percentage'] ? round($app['sslc_percentage']).'%' : '-'; ?></div>
                            </td>
                            <td>
                                <div style="font-size: 13px;"><?php echo $app['puc_percentage'] ? round($app['puc_percentage']).'%' : '-'; ?></div>
                            </td>

                            <td>
                                <div style="font-size: 13px; font-weight: 500;"><?php echo date('d M Y', strtotime($app['applied_at'])); ?></div>
                            </td>
                            <td class="no-print">
                                <select class="status-select <?php echo $statusClass; ?>" onchange="updateStatus(<?php echo $app['id']; ?>, this.value)">
                                    <option value="Applied" <?php echo $app['status'] == 'Applied' ? 'selected' : ''; ?>>Applied</option>
                                    <option value="Shortlisted" <?php echo $app['status'] == 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                    <option value="Rejected" <?php echo $app['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="Selected" <?php echo $app['status'] == 'Selected' ? 'selected' : ''; ?>>Selected</option>
                                </select>
                            </td>
                            <td class="no-print">
                                <div style="display: flex; gap: 8px;">
                                    <?php if ($app['resume_path']): ?>
                                        <a href="../student/view_resume.php?usn=<?php echo urlencode($app['usn']); ?>" target="_blank" class="btn-action btn-view" title="Resume">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn-action btn-view" onclick="showSgpaDetails('<?php echo $app['usn']; ?>', '<?php echo htmlspecialchars($app['student_name'] ?? 'N/A'); ?>')" title="Trend">
                                        <i class="fas fa-chart-line"></i>
                                    </button>
                                    <?php if (!empty($app['custom_responses'])): ?>
                                        <button class="btn-action btn-view no-print" onclick='showResponses(<?php echo json_encode($app['custom_responses']); ?>, "<?php echo addslashes($app['student_name'] ?? ''); ?>")' title="Answers">
                                            <i class="fas fa-list-check"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($applications)): ?>
                        <tr><td colspan="13" style="text-align: center; padding: 60px; color: var(--text-muted);">No applications found. Try adjusting filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <form method="POST" id="paginationForm" style="display:none;"><input type="hidden" name="page" id="pageNum"></form>
            <div class="pagination">
                <?php
                if ($page > 1) { echo '<a href="javascript:void(0)" onclick="goToPage('.($page-1).')" class="page-link">&laquo;</a>'; }
                for ($i = 1; $i <= $total_pages; $i++) {
                    $active = ($i == $page) ? 'active' : '';
                    echo '<a href="javascript:void(0)" onclick="goToPage('.$i.')" class="page-link '.$active.'">'.$i.'</a>';
                }
                if ($page < $total_pages) { echo '<a href="javascript:void(0)" onclick="goToPage('.($page+1).')" class="page-link">&raquo;</a>'; }
                ?>
            </div>
            <script>
                function goToPage(n) {
                    document.getElementById('pageNum').value = n;
                    document.getElementById('paginationForm').submit();
                }
            </script>
            <?php endif; ?>
        </div>

        <!-- PRINT ONLY SECTION -->
        <div class="print-only">
            <?php
            $selectedJobName = "All Jobs";
            $selectedCompanyName = "All Companies";
            if (!empty($jobId)) {
                foreach ($allJobs as $j) {
                    if ($j['id'] == $jobId) { $selectedJobName = $j['title']; break; }
                }
            }
            if (!empty($companyId)) {
                foreach ($allCompanies as $c) {
                    if ($c['id'] == $companyId) { $selectedCompanyName = $c['name']; break; }
                }
            }
            ?>
            <div style="text-align: center; margin-bottom: 20px;">
                <h2 style="font-size: 24px; margin-bottom: 5px; color: black !important; font-weight: 800;">GM University</h2>
                <h3 style="font-size: 18px; margin-bottom: 5px; color: black !important; font-weight: 700;">Application Report</h3>
                <p style="font-size: 14px; margin-bottom: 0; color: black !important;"><strong>Company:</strong> <?php echo htmlspecialchars($selectedCompanyName); ?> &nbsp;|&nbsp; <strong>Job Role:</strong> <?php echo htmlspecialchars($selectedJobName); ?></p>
                <p style="font-size: 14px; margin-top: 2px; color: black !important;"><strong>Total Students:</strong> <?php echo count($applications); ?></p>
            </div>
            <table class="modern-table" id="exportTable">
                <thead>
                    <tr>
                        <th>Sl.No</th>
                        <th>Student Name</th>
                        <th>USN</th>
                        <th>Inst</th>
                        <th>Course</th>
                        <th>Branch</th>
                        <th>Sem</th>
                        <th>Job Role</th>
                        <th>Company</th>
                        <th>SGPA</th>
                        <th>10th %</th>
                        <th>12th %</th>
                        <th>Applied</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $slNo = 1;
                    foreach ($applications as $app): 
                    ?>
                    <tr data-resume-link="<?php echo $app['resume_path'] ? (APP_URL . '/student/view_resume.php?usn=' . urlencode($app['usn'])) : ''; ?>">
                        <td><?php echo $slNo++; ?></td>
                        <td><?php echo htmlspecialchars($app['student_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($app['usn'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($app['institution'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($app['course'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($app['branch'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($app['current_semester'] ?? '?'); ?></td>
                        <td><?php echo htmlspecialchars($app['job_title'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($app['company_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $app['academic_sgpa'] ? number_format($app['academic_sgpa'], 2) : '-'; ?></td>
                        <td><?php echo $app['sslc_percentage'] ? round($app['sslc_percentage']).'%' : '-'; ?></td>
                        <td><?php echo $app['puc_percentage'] ? round($app['puc_percentage']).'%' : '-'; ?></td>
                        <td><?php echo date('d M Y', strtotime($app['applied_at'])); ?></td>
                        <td><?php echo htmlspecialchars($app['status'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($applications)): ?>
                    <tr><td colspan="14" style="text-align: center; padding: 20px;">No applications found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- END PRINT ONLY SECTION -->
    </div>

            </div>
        </div>
    </div>

    <!-- Attempts Modal -->
    <div id="attemptsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalAttemptStudentName">ASSESSMENT ATTEMPTS</h3>
                <span class="close-modal" onclick="closeAttemptsModal()">&times;</span>
            </div>
            <div id="attemptsContent">
                <table class="modern-table" style="border-radius: 8px; border: 1px solid var(--border-color); overflow: hidden;">
                    <thead>
                        <tr>
                            <th>ATTEMPT</th>
                            <th>SCORE</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>
                    <tbody id="attemptsTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SGPA Details Modal -->
    <div id="sgpaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalStudentName">Academic History</h3>
                <span class="close-modal" onclick="closeSgpaModal()">&times;</span>
            </div>
            <div id="sgpaLoading" style="text-align: center; padding: 40px; display: none;">
                <i class="fas fa-circle-notch fa-spin" style="font-size: 28px; color: var(--brand);"></i>
            </div>
            <div id="sgpaContent">
                <table class="modern-table" style="border-radius: 8px; border: 1px solid var(--border-color); overflow: hidden;">
                    <thead>
                        <tr>
                            <th>Semester</th>
                            <th>SGPA</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="sgpaTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Custom Responses Modal -->
    <div id="responsesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="responseStudentName">Form Responses</h3>
                <span class="close-modal" onclick="closeResponsesModal()">&times;</span>
            </div>
            <div id="responsesContainer" style="max-height: 450px; overflow-y: auto; padding-right: 10px;"></div>
            <div style="margin-top: 24px; text-align: right; padding-top: 16px; border-top: 1px solid var(--border-color);">
                <button class="btn-action" style="background: #FFFFFF; border: 1px solid #D1D5DB; color: var(--text-secondary); border-radius: 8px;" onclick="closeResponsesModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        async function updateStatus(appId, newStatus) {
            try {
                const res = await fetch('application_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_status', application_id: appId, status: newStatus })
                });
                const data = await res.json();
                if (data.success) location.reload();
                else alert('Error: ' + data.message);
            } catch (err) {
                alert('Failed to update status.');
            }
        }

        async function showSgpaDetails(usn, name) {
            const modal = document.getElementById('sgpaModal');
            const tbody = document.getElementById('sgpaTableBody');
            document.getElementById('modalStudentName').innerText = name;
            tbody.innerHTML = '';
            modal.style.display = 'flex';
            document.getElementById('sgpaLoading').style.display = 'block';
            document.getElementById('sgpaContent').style.display = 'none';

            try {
                const res = await fetch('application_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_academic_history', student_id: usn })
                });
                const data = await res.json();
                document.getElementById('sgpaLoading').style.display = 'none';
                document.getElementById('sgpaContent').style.display = 'block';

                if (data.success && data.history) {
                    data.history.forEach(sem => {
                        tbody.innerHTML += `
                            <tr>
                                <td style="font-weight: 600;">Semester ${sem.semester}</td>
                                <td style="color: var(--brand); font-weight: 700;">${sem.sgpa}</td>
                                <td><span style="color: #059669; font-size: 11px; font-weight: 700;">VERIFIED</span></td>
                            </tr>`;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">No history found.</td></tr>';
                }
            } catch (err) {
                document.getElementById('sgpaLoading').style.display = 'none';
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">Error loading history.</td></tr>';
            }
        }

        function closeSgpaModal() { document.getElementById('sgpaModal').style.display = 'none'; }

        function showResponses(json, name) {
            const modal = document.getElementById('responsesModal');
            const container = document.getElementById('responsesContainer');
            document.getElementById('responseStudentName').innerText = name;
            container.innerHTML = '';
            
            try {
                const data = JSON.parse(json);
                if (!data || data.length === 0) {
                    container.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No custom responses found.</div>';
                } else {
                    data.forEach(item => {
                        let val = item.value || '<span style="color: #ccc;">N/A</span>';
                        let extra = '';
                        if (item.type === 'file' && item.value) {
                            val = '[Document Uploaded]';
                            extra = `<br><a href="../${item.value}" target="_blank" class="btn-action btn-view" style="font-size: 11px; margin-top: 8px;"><i class="fas fa-download"></i> Download</a>`;
                        }
                        container.innerHTML += `
                            <div style="padding: 15px; border-bottom: 1px solid #f1f5f9;">
                                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">${item.label}</div>
                                <div style="font-size: 14px; color: var(--text-dark); font-weight: 500; margin-top: 4px;">${val}${extra}</div>
                            </div>`;
                    });
                }
            } catch (e) {
                container.innerHTML = '<div style="padding: 20px; text-align: center; color: #dc2626;">Error parsing responses.</div>';
            }
            modal.style.display = 'flex';
        }

        function closeResponsesModal() { document.getElementById('responsesModal').style.display = 'none'; }

        function showAttempts(type, studentName, scoresJson) {
            const scores = JSON.parse(scoresJson);
            const tbody = document.getElementById('attemptsTableBody');
            document.getElementById('modalAttemptStudentName').innerText = studentName;
            
            tbody.innerHTML = '';
            
            if (scores.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">No attempts found.</td></tr>';
            } else {
                scores.forEach((score, index) => {
                    const scoreNum = parseFloat(score);
                    const color = scoreNum >= 60 ? 'var(--brand)' : '#800000';
                    tbody.innerHTML += `
                        <tr>
                            <td style="font-weight: 600; color: #334155;">Attempt ${index + 1}</td>
                            <td style="color: ${color}; font-weight: 800; font-size: 14px;">${scoreNum.toFixed(2)}%</td>
                            <td><span style="color: #059669; font-size: 11px; font-weight: 700;">COMPLETED</span></td>
                        </tr>`;
                });
            }
            
            document.getElementById('attemptsModal').style.display = 'flex';
        }
        
        function closeAttemptsModal() {
            document.getElementById('attemptsModal').style.display = 'none';
        }

        function exportToExcel() {
            const table = document.getElementById("exportTable");
            const rows = Array.from(table.rows);
            const rawData = rows.map((row, rowIndex) => {
                const cells = Array.from(row.cells);
                const rowData = cells.map(cell => cell.innerText.split('\n')[0].trim());
                if (rowIndex === 0) {
                    rowData.push("Resume Link");
                } else {
                    const resumeLink = row.getAttribute("data-resume-link") || "";
                    rowData.push(resumeLink);
                }
                return rowData;
            });
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(rawData);
            
            // Format resume links as clickable hyperlinks
            const colIndex = rawData[0].length - 1;
            for (let r = 1; r < rawData.length; r++) {
                const url = rawData[r][colIndex];
                if (url && url !== "No Resume" && url.startsWith("http")) {
                    const cellAddress = XLSX.utils.encode_cell({ c: colIndex, r: r });
                    if (ws[cellAddress]) {
                        ws[cellAddress].l = { Target: url, Tooltip: "Click to open resume" };
                    }
                }
            }
            
            XLSX.utils.book_append_sheet(wb, ws, "Applications");
            XLSX.writeFile(wb, `Applications_${new Date().toISOString().split('T')[0]}.xlsx`);
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                closeSgpaModal();
                closeResponsesModal();
                closeAttemptsModal();
            }
        }
    </script>
</body>
</html>
</body>
</html>

