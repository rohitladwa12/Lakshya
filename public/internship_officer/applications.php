<?php
/**
 * Executive Light-Theme Internship Applications & Candidate Management Console
 * High-density candidate pipeline, smart filtering, Excel export, and status management.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole('internship_officer');

$internshipId = get('id');
if (!$internshipId) {
    redirect('dashboard.php');
}

$internshipModel = new Internship();
$internship = $internshipModel->find($internshipId);

if (!$internship) {
    die("Internship opportunity not found.");
}

$applicationModel = new InternshipApplication();
$applications = $applicationModel->getByInternship($internshipId);

// Handle Status Updates
if (isPost() && isset($_POST['update_status'])) {
    $appId = post('app_id');
    $status = post('status');
    if ($appId && in_array($status, ['Applied', 'Shortlisted', 'Interview', 'Selected', 'Rejected'])) {
        $applicationModel->update($appId, ['status' => $status]);
    }
    // Refresh to update pipeline stats
    redirect("applications.php?id={$internshipId}");
}

// Handle Excel / CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="candidates_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $internship['company_name']) . '_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    try {
        $maxSemester = 0;
        foreach ($applications as $app) {
            $semSgpa = $app['sem_sgpa_all'] ?? [];
            for ($i = 8; $i >= 1; $i--) {
                if (isset($semSgpa[$i]) && $semSgpa[$i] !== null && $semSgpa[$i] !== '' && floatval($semSgpa[$i]) > 0) {
                    $maxSemester = max($maxSemester, $i);
                    break;
                }
            }
        }
        
        $hasSemesterData = ($maxSemester > 0);
        
        // CSV Headers
        $headers = ['Sl No', 'Candidate Name', 'USN / Student ID', 'Institution', 'Course', 'Branch', 'Current Sem'];
        if ($hasSemesterData) {
            for ($i = 1; $i <= $maxSemester; $i++) {
                $headers[] = "Sem {$i} SGPA";
            }
        }
        $headers = array_merge($headers, ['Company', 'Role Title', 'Applied On', 'Status', 'Email Address', 'Phone Number', 'Resume Link']);
        fputcsv($output, $headers);
        
        // CSV Rows
        $slNo = 1;
        foreach ($applications as $app) {
            $studentUsn = trim((string)($app['student_id'] ?? ($app['usn'] ?? 'N/A')));
            $usnFormatted = ($studentUsn !== 'N/A') ? '="' . $studentUsn . '"' : 'N/A';

            $row = [
                $slNo++,
                $app['student_name'] ?? 'Unknown',
                $usnFormatted,
                $app['institution'] ?? 'N/A',
                $app['course'] ?? 'N/A',
                $app['branch'] ?? 'N/A',
                $app['sem'] ?? 'N/A'
            ];
            
            if ($hasSemesterData) {
                $semSgpa = $app['sem_sgpa_all'] ?? array_fill(1, 8, null);
                for ($i = 1; $i <= $maxSemester; $i++) {
                    if (isset($semSgpa[$i]) && $semSgpa[$i] !== null && $semSgpa[$i] !== '' && floatval($semSgpa[$i]) > 0) {
                        $row[] = number_format($semSgpa[$i], 2);
                    } else {
                        $row[] = '-';
                    }
                }
            }
            
            $resumeFile = UPLOADS_PATH . '/resumes/Student_Resumes/' . strtoupper($studentUsn) . '_Resume.pdf';
            $hasResume = !empty($app['resume_path']) || file_exists($resumeFile);
            
            $token = function_exists('generateResumeToken') ? generateResumeToken($studentUsn) : '';
            $resumeUrl = APP_URL . '/student/view_resume.php?usn=' . urlencode($studentUsn) . ($token ? '&token=' . $token : '');
            $resumeLink = $hasResume ? '=HYPERLINK("' . $resumeUrl . '", "View Resume")' : 'No Resume Uploaded';
            
            $rawPhone = trim((string)($app['phone'] ?? ''));
            $phoneFormatted = (!empty($rawPhone) && $rawPhone !== 'N/A') ? '="' . preg_replace('/[^0-9+]/', '', $rawPhone) . '"' : 'N/A';

            $row = array_merge($row, [
                $internship['company_name'] ?? 'N/A',
                $internship['internship_title'] ?? 'N/A',
                !empty($app['applied_at']) ? date('d M Y, h:i A', strtotime($app['applied_at'])) : 'N/A',
                $app['status'] ?? 'Applied',
                $app['email'] ?? 'N/A',
                $phoneFormatted,
                $resumeLink
            ]);
            
            fputcsv($output, $row);
        }
    } catch (\Throwable $e) {
        error_log("Internship Candidate Export Error: " . $e->getMessage());
    }
    
    fclose($output);
    exit;
}

// Pipeline Breakdown Metrics
$statusCounts = [
    'all' => count($applications),
    'Applied' => 0,
    'Shortlisted' => 0,
    'Selected' => 0,
    'Rejected' => 0
];
foreach ($applications as $a) {
    $st = $a['status'] ?? 'Applied';
    if ($st === 'Interview') $st = 'Shortlisted';
    if (isset($statusCounts[$st])) {
        $statusCounts[$st]++;
    }
}

// Format Deadline
$dlTimestamp = !empty($internship['application_deadline']) ? strtotime($internship['application_deadline']) : null;
$formattedDeadline = 'Open / Ongoing';
if ($dlTimestamp) {
    $formattedDeadline = (date('H:i', $dlTimestamp) !== '00:00') ? date('M d, Y \a\t h:i A', $dlTimestamp) : date('M d, Y', $dlTimestamp);
}

// WhatsApp Share URL
$shareUrl = APP_URL . '/student/internship_details.php?code=' . encryptInternshipId($internshipId);
$waMessage = "*📢 Internship Applications Open!*\n\n"
           . "*Company:* " . $internship['company_name'] . "\n"
           . "*Role:* " . $internship['internship_title'] . "\n"
           . "*Stipend:* " . ($internship['stipend'] ?: 'Competitive') . "\n"
           . "*Mode:* " . $internship['mode'] . "\n"
           . "*Deadline:* " . $formattedDeadline . "\n\n"
           . "*Apply on Lakshya:* " . $shareUrl;
$waUrl = "https://api.whatsapp.com/send?text=" . urlencode($waMessage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates: <?php echo htmlspecialchars($internship['internship_title']); ?> | Placement Portal</title>
    
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5c0000;
            --primary-light: #fff5f5;
            --primary-subtle: #fbe8e8;
            --accent-gold: #b45309;
            --bg-page: #f8fafc;
            --surface-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --border-focus: #800000;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.04);
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background-color: var(--bg-page);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .outfit-font { font-family: 'Outfit', sans-serif; }

        .main-container {
            max-width: 1480px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        /* Top Header Bar */
        .top-nav-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .back-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background: var(--surface-card);
            color: var(--text-muted);
            padding: 0.6rem 1.2rem;
            border-radius: 9999px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            border: 1px solid var(--border-color);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }

        .back-pill:hover {
            color: var(--primary-maroon);
            border-color: var(--primary-maroon);
            transform: translateX(-3px);
            background: var(--primary-light);
        }

        /* Drive Context Card */
        .drive-context-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 1.75rem 2.25rem;
            margin-bottom: 1.75rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }

        .drive-context-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 6px;
            background: linear-gradient(180deg, var(--primary-maroon) 0%, #b45309 100%);
        }

        .drive-meta-cluster {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .drive-logo-badge {
            width: 68px;
            height: 68px;
            border-radius: var(--radius-md);
            background: #f8fafc;
            border: 1.5px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            color: var(--primary-maroon);
            overflow: hidden;
            flex-shrink: 0;
        }

        .drive-logo-badge img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .drive-info h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
            margin-bottom: 0.35rem;
        }

        .drive-tags-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }

        .tag-pill {
            font-size: 0.76rem;
            font-weight: 700;
            padding: 0.28rem 0.65rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .tag-company { background: #f1f5f9; color: #334155; }
        .tag-stipend { background: #fef3c7; color: #92400e; }
        .tag-mode { background: #f0fdf4; color: #166534; }
        .tag-deadline { background: #fee2e2; color: #991b1b; }

        .drive-actions-cluster {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-action-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.75rem 1.4rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-action-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
            filter: brightness(1.05);
        }

        .btn-action-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.72rem 1.25rem;
            background: #ffffff;
            color: var(--text-main);
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.88rem;
            text-decoration: none;
            border: 1.5px solid var(--border-color);
            transition: all 0.2s;
        }

        .btn-action-outline:hover {
            border-color: var(--primary-maroon);
            color: var(--primary-maroon);
            background: var(--primary-light);
        }

        /* 4-KPI Metric Pipeline Grid */
        .pipeline-metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        @media (max-width: 900px) {
            .pipeline-metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .kpi-pipe-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            user-select: none;
        }

        .kpi-pipe-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: #cbd5e1;
        }

        .kpi-pipe-card.active-filter {
            border-color: var(--primary-maroon);
            background: #fffcfc;
            box-shadow: 0 0 0 2px var(--primary-maroon);
        }

        .kpi-pipe-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .kpi-pipe-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-pipe-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
        }

        .kpi-icon-all { background: #e0f2fe; color: #0284c7; }
        .kpi-icon-shortlisted { background: #fef3c7; color: #d97706; }
        .kpi-icon-selected { background: #dcfce7; color: #16a34a; }
        .kpi-icon-rejected { background: #fee2e2; color: #dc2626; }

        .kpi-pipe-number {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1;
        }

        /* Search & Filter Toolbar */
        .table-toolbar-box {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            border-bottom: none;
        }

        .search-input-wrap {
            position: relative;
            flex: 1;
            min-width: 280px;
            max-width: 480px;
        }

        .search-input-wrap i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .search-bar-control {
            width: 100%;
            padding: 0.68rem 1rem 0.68rem 2.5rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .search-bar-control:focus {
            background: #ffffff;
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.08);
        }

        .filter-tabs-pills {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            background: #f1f5f9;
            padding: 0.3rem;
            border-radius: var(--radius-md);
        }

        .filter-tab-btn {
            background: transparent;
            border: none;
            padding: 0.45rem 0.9rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.18s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .filter-tab-btn.active {
            background: #ffffff;
            color: var(--primary-maroon);
            box-shadow: var(--shadow-sm);
        }

        .badge-count-tiny {
            background: #e2e8f0;
            color: #475569;
            padding: 0.1rem 0.45rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .filter-tab-btn.active .badge-count-tiny {
            background: var(--primary-light);
            color: var(--primary-maroon);
        }

        /* Candidates Table */
        .table-container-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 0 0 var(--radius-xl) var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }

        .candidates-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.88rem;
        }

        .candidates-table thead {
            background: #f8fafc;
            border-bottom: 2px solid var(--border-color);
        }

        .candidates-table th {
            padding: 1rem 1.25rem;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            white-space: nowrap;
        }

        .candidates-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.15s;
        }

        .candidates-table tbody tr:hover {
            background-color: #fafbfc;
        }

        .candidates-table td {
            padding: 1.1rem 1.25rem;
            vertical-align: middle;
        }

        /* Candidate Cell */
        .candidate-cell {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .candidate-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary-maroon);
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            border: 1px solid var(--primary-subtle);
            flex-shrink: 0;
        }

        .candidate-name {
            font-weight: 700;
            color: var(--text-main);
            font-size: 0.92rem;
        }

        .candidate-usn {
            font-family: monospace;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 0.1rem;
        }

        .candidate-institute {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .candidate-usn-pill {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.8rem;
            font-weight: 700;
            color: #1e293b;
            background: #f1f5f9;
            padding: 0.25rem 0.55rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            display: inline-block;
            white-space: nowrap;
        }

        .badge-sem-pill {
            font-size: 0.78rem;
            font-weight: 700;
            color: #475569;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            display: inline-block;
            white-space: nowrap;
        }

        /* Academic Badges */
        .academic-badge-wrap {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .badge-branch {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #eff6ff;
            color: #1e40af;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            width: fit-content;
        }

        .badge-sem {
            font-size: 0.74rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* SGPA Trigger Button */
        .btn-sgpa-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }

        .btn-sgpa-badge:hover {
            background: #fde68a;
            border-color: #d97706;
            transform: scale(1.03);
        }

        /* Resume & History Buttons */
        .btn-view-pdf {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 0.45rem 0.85rem;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-view-pdf:hover {
            background: #fecaca;
            border-color: #dc2626;
            transform: translateY(-1px);
        }

        .btn-history-trigger {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.45rem 0.75rem;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 0.35rem;
        }

        .btn-history-trigger:hover {
            background: var(--primary-light);
            border-color: var(--primary-maroon);
            color: var(--primary-maroon);
        }

        /* Status Dropdown */
        .status-select-wrap {
            position: relative;
            min-width: 145px;
        }

        .status-select-control {
            width: 100%;
            padding: 0.55rem 1.8rem 0.55rem 0.8rem;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            outline: none;
            border: 1.5px solid var(--border-color);
            transition: all 0.2s;
            appearance: none;
            background-repeat: no-repeat;
            background-position: right 0.6rem center;
            background-size: 0.85rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23475569'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        }

        .status-select-control.status-applied { background-color: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .status-select-control.status-shortlisted { background-color: #fef3c7; color: #92400e; border-color: #fde68a; }
        .status-select-control.status-selected { background-color: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .status-select-control.status-rejected { background-color: #f1f5f9; color: #64748b; border-color: #e2e8f0; }

        .status-select-control:focus {
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }

        /* Empty State */
        .empty-pipeline-box {
            text-align: center;
            padding: 5rem 2rem;
            color: var(--text-muted);
        }

        .empty-pipeline-box i {
            font-size: 3.5rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
            display: block;
        }

        /* Modal Overhaul */
        .custom-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .custom-modal-card {
            background: #ffffff;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 680px;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            animation: modalScale 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalScale {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-card-header {
            padding: 1.5rem 1.75rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
        }

        .modal-card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .btn-modal-close {
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 1.25rem;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-modal-close:hover {
            color: var(--text-main);
            background: #e2e8f0;
        }

        .modal-card-body {
            padding: 1.75rem;
            overflow-y: auto;
        }

        .sgpa-grid-box {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        @media (max-width: 540px) {
            .sgpa-grid-box {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .sgpa-tile {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.1rem 0.85rem;
            text-align: center;
            transition: all 0.2s;
        }

        .sgpa-tile:hover {
            border-color: var(--primary-maroon);
            background: var(--primary-light);
        }

        .sgpa-tile-label {
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.35rem;
        }

        .sgpa-tile-value {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            color: #b45309;
        }

        .sgpa-tile-empty {
            color: #cbd5e1;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    
    <!-- Top Executive Navigation -->
    <?php include 'navbar.php'; ?>

    <main class="main-container">
        
        <!-- Top Back Navigation -->
        <div class="top-nav-row">
            <a href="dashboard.php" class="back-pill">
                <i class="fas fa-arrow-left"></i> Back to Dashboard Console
            </a>
            <div style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">
                <i class="fas fa-shield-halved" style="color: var(--primary-maroon);"></i> Officer Candidate Pipeline
            </div>
        </div>

        <!-- Drive Overview Banner -->
        <div class="drive-context-card">
            <div class="drive-meta-cluster">
                <div class="drive-logo-badge">
                    <?php if (!empty($internship['company_logo'])): ?>
                        <img src="../<?php echo htmlspecialchars($internship['company_logo']); ?>" alt="Logo">
                    <?php else: ?>
                        <?php echo strtoupper(substr($internship['company_name'], 0, 2)); ?>
                    <?php endif; ?>
                </div>
                <div class="drive-info">
                    <h1><?php echo htmlspecialchars($internship['internship_title']); ?></h1>
                    <div class="drive-tags-row">
                        <span class="tag-pill tag-company">
                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($internship['company_name']); ?>
                        </span>
                        <span class="tag-pill tag-stipend">
                            <i class="fas fa-indian-rupee-sign"></i> <?php echo htmlspecialchars($internship['stipend'] ?: 'Competitive'); ?>
                        </span>
                        <span class="tag-pill tag-mode">
                            <i class="fas <?php echo $internship['mode'] === 'Remote' ? 'fa-house-laptop' : ($internship['mode'] === 'Hybrid' ? 'fa-shuffle' : 'fa-building'); ?>"></i>
                            <?php echo htmlspecialchars($internship['mode']); ?>
                        </span>
                        <span class="tag-pill tag-deadline">
                            <i class="far fa-hourglass-half"></i> Deadline: <?php echo $formattedDeadline; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="drive-actions-cluster">
                <a href="applications.php?id=<?php echo $internshipId; ?>&export=excel" class="btn-action-primary">
                    <i class="fas fa-file-excel"></i> Export Candidate CSV
                </a>
                <a href="<?php echo $waUrl; ?>" target="_blank" class="btn-action-outline">
                    <i class="fab fa-whatsapp" style="color: #25d366;"></i> WhatsApp Broadcast
                </a>
                <a href="edit_internship.php?id=<?php echo $internshipId; ?>" class="btn-action-outline">
                    <i class="fas fa-pen-to-square"></i> Edit Drive
                </a>
            </div>
        </div>

        <!-- 4-KPI Metric Pipeline Grid -->
        <div class="pipeline-metrics-grid">
            <div class="kpi-pipe-card active-filter" onclick="filterByStatus('all', this)">
                <div class="kpi-pipe-top">
                    <span class="kpi-pipe-label">Total Applications</span>
                    <div class="kpi-pipe-icon kpi-icon-all"><i class="fas fa-users"></i></div>
                </div>
                <div class="kpi-pipe-number"><?php echo $statusCounts['all']; ?></div>
            </div>

            <div class="kpi-pipe-card" onclick="filterByStatus('Shortlisted', this)">
                <div class="kpi-pipe-top">
                    <span class="kpi-pipe-label">In Review / Shortlisted</span>
                    <div class="kpi-pipe-icon kpi-icon-shortlisted"><i class="fas fa-user-check"></i></div>
                </div>
                <div class="kpi-pipe-number" style="color: #d97706;"><?php echo $statusCounts['Shortlisted']; ?></div>
            </div>

            <div class="kpi-pipe-card" onclick="filterByStatus('Selected', this)">
                <div class="kpi-pipe-top">
                    <span class="kpi-pipe-label">Offers Selected</span>
                    <div class="kpi-pipe-icon kpi-icon-selected"><i class="fas fa-award"></i></div>
                </div>
                <div class="kpi-pipe-number" style="color: #16a34a;"><?php echo $statusCounts['Selected']; ?></div>
            </div>

            <div class="kpi-pipe-card" onclick="filterByStatus('Rejected', this)">
                <div class="kpi-pipe-top">
                    <span class="kpi-pipe-label">Not Selected</span>
                    <div class="kpi-pipe-icon kpi-icon-rejected"><i class="fas fa-user-xmark"></i></div>
                </div>
                <div class="kpi-pipe-number" style="color: #64748b;"><?php echo $statusCounts['Rejected']; ?></div>
            </div>
        </div>

        <!-- Candidates Table Card -->
        <div>
            <!-- Search & Filter Tabs Toolbar -->
            <div class="table-toolbar-box">
                <div class="search-input-wrap">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="candidateSearchInput" class="search-bar-control" placeholder="Search by Student Name, USN, Branch, College..." oninput="handleClientSearch()">
                </div>

                <div class="filter-tabs-pills">
                    <button class="filter-tab-btn active" onclick="filterByStatus('all', this)">
                        All <span class="badge-count-tiny"><?php echo $statusCounts['all']; ?></span>
                    </button>
                    <button class="filter-tab-btn" onclick="filterByStatus('Applied', this)">
                        Applied <span class="badge-count-tiny"><?php echo $statusCounts['Applied']; ?></span>
                    </button>
                    <button class="filter-tab-btn" onclick="filterByStatus('Shortlisted', this)">
                        Shortlisted <span class="badge-count-tiny"><?php echo $statusCounts['Shortlisted']; ?></span>
                    </button>
                    <button class="filter-tab-btn" onclick="filterByStatus('Selected', this)">
                        Selected <span class="badge-count-tiny"><?php echo $statusCounts['Selected']; ?></span>
                    </button>
                    <button class="filter-tab-btn" onclick="filterByStatus('Rejected', this)">
                        Rejected <span class="badge-count-tiny"><?php echo $statusCounts['Rejected']; ?></span>
                    </button>
                </div>
            </div>

            <div class="table-container-card">
                <?php if (empty($applications)): ?>
                    <div class="empty-pipeline-box">
                        <i class="fas fa-user-clock"></i>
                        <h2 class="outfit-font" style="font-size: 1.3rem; font-weight: 700; color: var(--text-main);">No Candidate Submissions Yet</h2>
                        <p style="font-size: 0.9rem; margin-top: 0.35rem;">Applications will appear here automatically as students submit their profiles.</p>
                        <div style="margin-top: 1.5rem;">
                            <a href="<?php echo $waUrl; ?>" target="_blank" class="btn-action-primary">
                                <i class="fab fa-whatsapp"></i> Broadcast Opportunity on WhatsApp
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="candidates-table" id="candidatesTable">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">#</th>
                                    <th>Candidate Name</th>
                                    <th>Student ID / USN</th>
                                    <th>Institution</th>
                                    <th>Course</th>
                                    <th>Branch</th>
                                    <th style="text-align: center;">Current Sem</th>
                                    <th style="text-align: center;">SGPA</th>
                                    <th>Applied On</th>
                                    <th style="text-align: center;">Resume</th>
                                    <th style="text-align: center;">History</th>
                                    <th>Pipeline Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $slNo = 1;
                                foreach ($applications as $app): 
                                    $studentUsn = trim((string)($app['student_id'] ?? ($app['usn'] ?? 'N/A')));
                                    $studentName = $app['student_name'] ?? 'Candidate';
                                    $institution = $app['institution'] ?? 'GMU';
                                    $course = $app['course'] ?? 'UG';
                                    $branch = $app['branch'] ?? 'N/A';
                                    $sem = $app['sem'] ?? 'N/A';
                                    $appStatus = $app['status'] ?? 'Applied';
                                    if ($appStatus === 'Interview') $appStatus = 'Shortlisted';
                                    
                                    // Resume token generator
                                    $token = function_exists('generateResumeToken') ? generateResumeToken($studentUsn) : '';
                                    $resumeUrl = APP_URL . '/student/view_resume.php?usn=' . urlencode($studentUsn) . ($token ? '&token=' . $token : '');
                                    
                                    // SGPA payload
                                    $semSgpaJson = htmlspecialchars(json_encode($app['sem_sgpa_all'] ?? array_fill(1, 8, null)), ENT_QUOTES);
                                    
                                    // Candidate Initials
                                    $words = explode(' ', trim($studentName));
                                    $initials = (count($words) >= 2) ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($studentName, 0, 2));
                                ?>
                                    <tr class="candidate-row" data-status="<?php echo htmlspecialchars($appStatus); ?>" data-search="<?php echo strtolower(htmlspecialchars($studentName . ' ' . $studentUsn . ' ' . $institution . ' ' . $course . ' ' . $branch . ' ' . $sem . ' ' . ($app['email'] ?? ''))); ?>">
                                        <td style="font-weight: 700; color: #94a3b8; text-align: center;"><?php echo $slNo++; ?></td>
                                        
                                        <!-- 1. Candidate Name -->
                                        <td>
                                            <div class="candidate-cell">
                                                <div class="candidate-avatar"><?php echo $initials; ?></div>
                                                <div class="candidate-name"><?php echo htmlspecialchars($studentName); ?></div>
                                            </div>
                                        </td>

                                        <!-- 2. Student ID / USN -->
                                        <td>
                                            <span class="candidate-usn-pill"><?php echo htmlspecialchars($studentUsn); ?></span>
                                        </td>

                                        <!-- 3. Institution -->
                                        <td>
                                            <span style="font-weight: 600; color: #334155; font-size: 0.85rem;"><?php echo htmlspecialchars($institution); ?></span>
                                        </td>

                                        <!-- 4. Course -->
                                        <td>
                                            <span style="font-weight: 600; color: var(--text-muted); font-size: 0.82rem; text-transform: uppercase;"><?php echo htmlspecialchars($course); ?></span>
                                        </td>

                                        <!-- 5. Branch -->
                                        <td>
                                            <span class="badge-branch">
                                                <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($branch); ?>
                                            </span>
                                        </td>

                                        <!-- 6. Current Sem -->
                                        <td style="text-align: center;">
                                            <span class="badge-sem-pill">Sem <?php echo htmlspecialchars($sem); ?></span>
                                        </td>

                                        <!-- 7. SGPA Record -->
                                        <td style="text-align: center;">
                                            <button type="button" class="btn-sgpa-badge" onclick='viewSGPA("<?php echo htmlspecialchars($studentName, ENT_QUOTES); ?>", "<?php echo htmlspecialchars($studentUsn, ENT_QUOTES); ?>", <?php echo $semSgpaJson; ?>)'>
                                                <i class="fas fa-chart-line"></i> View
                                            </button>
                                        </td>

                                        <!-- 8. Submission Time -->
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-main); font-size: 0.83rem; white-space: nowrap;">
                                                <?php echo !empty($app['applied_at']) ? date('d M Y', strtotime($app['applied_at'])) : 'N/A'; ?>
                                            </div>
                                            <div style="font-size: 0.72rem; color: var(--text-muted); white-space: nowrap;">
                                                <i class="far fa-clock" style="font-size: 0.68rem;"></i> <?php echo !empty($app['applied_at']) ? date('h:i A', strtotime($app['applied_at'])) : ''; ?>
                                            </div>
                                        </td>

                                        <!-- 9. Resume -->
                                        <td style="text-align: center;">
                                            <a href="<?php echo $resumeUrl; ?>" target="_blank" class="btn-view-pdf">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </a>
                                        </td>

                                        <!-- 10. History -->
                                        <td style="text-align: center;">
                                            <button type="button" class="btn-history-trigger" style="margin-top: 0;" onclick="viewHistory('<?php echo htmlspecialchars($studentUsn); ?>', '<?php echo htmlspecialchars($studentName, ENT_QUOTES); ?>')">
                                                <i class="fas fa-clock-rotate-left"></i> History
                                            </button>
                                        </td>

                                        <!-- 11. Pipeline Status Dropdown -->
                                        <td>
                                            <form method="POST" class="status-select-wrap">
                                                <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                                <input type="hidden" name="update_status" value="1">
                                                <select name="status" class="status-select-control status-<?php echo strtolower($appStatus); ?>" onchange="this.form.submit()">
                                                    <option value="Applied" <?php echo $appStatus === 'Applied' ? 'selected' : ''; ?>>Applied</option>
                                                    <option value="Shortlisted" <?php echo $appStatus === 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                                    <option value="Selected" <?php echo $appStatus === 'Selected' ? 'selected' : ''; ?>>Selected</option>
                                                    <option value="Rejected" <?php echo $appStatus === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- SGPA Modal -->
    <div id="sgpaModal" class="custom-modal">
        <div class="custom-modal-card">
            <div class="modal-card-header">
                <div class="modal-card-title">
                    <i class="fas fa-chart-pie" style="color: var(--primary-maroon);"></i>
                    <span id="sgpaModalTitle">Semester-wise SGPA Scorecard</span>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeSGPAModal()">&times;</button>
            </div>
            <div class="modal-card-body">
                <div class="sgpa-grid-box" id="sgpaGrid"></div>
            </div>
        </div>
    </div>

    <!-- Student Application History Modal -->
    <div id="historyModal" class="custom-modal">
        <div class="custom-modal-card">
            <div class="modal-card-header">
                <div class="modal-card-title">
                    <i class="fas fa-clock-rotate-left" style="color: var(--primary-maroon);"></i>
                    <span id="historyModalTitle">Student Placement & Drive History</span>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeHistoryModal()">&times;</button>
            </div>
            <div class="modal-card-body" id="historyModalBody">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-maroon);"></i>
                    <p style="margin-top: 0.75rem; color: var(--text-muted); font-size: 0.9rem;">Fetching candidate application history...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-Side Search & Filter Scripts -->
    <script>
        let currentStatusFilter = 'all';

        // Filter by Status Tab / KPI Card
        function filterByStatus(status, element) {
            currentStatusFilter = status;
            
            // Highlight Tab Buttons
            document.querySelectorAll('.filter-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.filter-tab-btn').forEach(btn => {
                if (btn.textContent.trim().toLowerCase().startsWith(status.toLowerCase())) {
                    btn.classList.add('active');
                }
            });

            // Highlight KPI Cards
            document.querySelectorAll('.kpi-pipe-card').forEach(card => card.classList.remove('active-filter'));
            if (element && element.classList.contains('kpi-pipe-card')) {
                element.classList.add('active-filter');
            }

            applyClientFilters();
        }

        // Search Input Handler
        function handleClientSearch() {
            applyClientFilters();
        }

        // Apply Search & Status Filter Combined
        function applyClientFilters() {
            const query = document.getElementById('candidateSearchInput').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.candidate-row');
            
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const rowSearch = row.getAttribute('data-search') || '';
                
                const matchesStatus = (currentStatusFilter === 'all' || rowStatus === currentStatusFilter);
                const matchesSearch = (!query || rowSearch.includes(query));
                
                if (matchesStatus && matchesSearch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Open SGPA Scorecard Modal
        function viewSGPA(studentName, studentUsn, semSgpaData) {
            document.getElementById('sgpaModalTitle').textContent = studentName + ' (' + studentUsn + ') — SGPA Record';
            const grid = document.getElementById('sgpaGrid');
            grid.innerHTML = '';
            
            let hasData = false;
            for (let sem = 1; sem <= 8; sem++) {
                const sgpa = semSgpaData[sem];
                const tile = document.createElement('div');
                tile.className = 'sgpa-tile';
                
                if (sgpa !== null && sgpa !== undefined && parseFloat(sgpa) > 0) {
                    hasData = true;
                    tile.innerHTML = `
                        <div class="sgpa-tile-label">Semester ${sem}</div>
                        <div class="sgpa-tile-value">${parseFloat(sgpa).toFixed(2)}</div>
                    `;
                } else {
                    tile.innerHTML = `
                        <div class="sgpa-tile-label">Semester ${sem}</div>
                        <div class="sgpa-tile-value sgpa-tile-empty">-</div>
                    `;
                }
                grid.appendChild(tile);
            }
            
            document.getElementById('sgpaModal').style.display = 'flex';
        }

        function closeSGPAModal() {
            document.getElementById('sgpaModal').style.display = 'none';
        }

        // Open Student History Modal
        function viewHistory(studentId, name) {
            document.getElementById('historyModalTitle').textContent = 'Drive History: ' + name + ' (' + studentId + ')';
            document.getElementById('historyModal').style.display = 'flex';
            document.getElementById('historyModalBody').innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-maroon);"></i><p style="margin-top: 0.75rem; color: var(--text-muted); font-size: 0.9rem;">Fetching candidate history...</p></div>';
            
            fetch('get_student_history.php?student_id=' + encodeURIComponent(studentId))
                .then(response => response.text())
                .then(html => {
                    document.getElementById('historyModalBody').innerHTML = html;
                })
                .catch(() => {
                    document.getElementById('historyModalBody').innerHTML = '<div style="text-align: center; padding: 2rem; color: #dc2626;"><i class="fas fa-triangle-exclamation" style="font-size: 2rem;"></i><p style="margin-top: 0.75rem;">Unable to load candidate history.</p></div>';
                });
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

        // Close modals on clicking outside or ESC
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('custom-modal')) {
                e.target.style.display = 'none';
            }
        });

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.custom-modal').forEach(m => m.style.display = 'none');
            }
        });
    </script>
</body>
</html>
