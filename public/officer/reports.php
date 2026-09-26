<?php
/**
 * Placement Officer - Intelligence Hub
 * Simplified, Paged & Tabbed Reporting
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_PLACEMENT_OFFICER);

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

$pageId = 'officer_intelligence';

// Handle POST State (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Session filtering only (Verification logic removed per user request)
    
    SessionFilterHelper::handlePostToSession($pageId, $_POST);
    $section = $_POST['section'] ?? 'details';
    header("Location: reports.php?section=" . urlencode($section));
    exit;
}

// Retrieve Filters & State
$filters = SessionFilterHelper::getFilters($pageId);

// Handle GET state updates for persistent tab switching
if (isset($_GET['section'])) {
    if (($filters['section'] ?? '') !== $_GET['section']) {
        unset($filters['page']); // Reset page when swapping to a new tab
    }
    $filters['section'] = $_GET['section'];
    SessionFilterHelper::setFilters($pageId, $filters);
}
if (isset($_GET['page'])) {
    $filters['page'] = (int)$_GET['page'];
    SessionFilterHelper::setFilters($pageId, $filters);
}

$section = $filters['section'] ?? 'details';
if (!in_array($section, ['details', 'portfolio', 'ai', 'stats'])) $section = 'details';

$page = (int)($filters['page'] ?? 1);
if ($page < 1) $page = 1;
$limit = 15;

$officerModel = new PlacementOfficer();

// 1. Fetch Disciplines for dropdown
try {
    $disciplines = $officerModel->getDisciplines() ?: [];
} catch (Exception $e) {
    error_log("Error fetching disciplines: " . $e->getMessage());
    $disciplines = [];
}

// 2. Fetch Data based on Section
$data = ['data' => [], 'total' => 0, 'page' => $page, 'total_pages' => 0, 'summary' => []];

try {
    if ($section === 'details') {
        $data = $officerModel->getStudentsPaged($filters, $page, $limit);

        // Handle Export
        if (isset($filters['export'])) {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="student_report_'.date('Y-m-d').'.xls"');
            
            $allData = $officerModel->getStudentsPaged($filters, 1, 10000);
            echo '<table border="1">';
            echo '<tr><th>Name</th><th>Institution</th><th>Contact No</th><th>Email ID</th><th>USN</th><th>Sem</th><th>SGPA</th><th>10th %</th><th>12th %</th><th>Father Name</th><th>Mother Name</th><th>Address</th><th>Status</th><th>Resume Link</th></tr>';
            foreach ($allData['data'] as $s) {
                $resumeFile = strtoupper($s['usn']) . '_Resume.pdf';
                $token = function_exists('generateResumeToken') ? generateResumeToken($s['usn']) : '';
                $resumeUrl = APP_URL . '/student/view_resume.php?usn=' . urlencode($s['usn']) . ($token ? '&token=' . $token : '');
                $resumePath = UPLOADS_PATH . '/resumes/Student_Resumes/' . $resumeFile;
                $resumeLink = file_exists($resumePath) ? '=HYPERLINK("' . $resumeUrl . '", "View Resume")' : 'No Resume';

                echo "<tr>
                        <td>".htmlspecialchars((string)$s['name'])."</td>
                        <td>".htmlspecialchars((string)$s['institution'])."</td>
                        <td>".htmlspecialchars((string)($s['mobile'] ?? ($s['phone'] ?? '-')))."</td>
                        <td>".htmlspecialchars((string)($s['email'] ?? '-'))."</td>
                        <td>".htmlspecialchars((string)$s['usn'])."</td>
                        <td>{$s['sem']}</td>
                        <td>{$s['sgpa']}</td>
                        <td>".htmlspecialchars((string)$s['sslc_percentage'])."</td>
                        <td>".htmlspecialchars((string)$s['puc_percentage'])."</td>
                        <td>".htmlspecialchars((string)$s['father_name'])."</td>
                        <td>".htmlspecialchars((string)$s['mother_name'])."</td>
                        <td>".htmlspecialchars((string)$s['address'])."</td>
                        <td>" . ($s['registered'] ? 'Active' : 'Pending') . "</td>
                        <td>{$resumeLink}</td>
                      </tr>";
            }
            echo '</table>';
            exit;
        }
    } elseif ($section === 'portfolio') {
        $data = $officerModel->getAllPortfolioItemsPaged($filters, $page, $limit);
    } elseif ($section === 'ai') {
        $data = $officerModel->getUnifiedAIReportsPaged($filters, $page, $limit);
    } elseif ($section === 'stats') {
        $data = $officerModel->getRecentPlacementsPaged($page, $limit);
    }
} catch (Exception $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
    $error_message = "A database error occurred while fetching reports.";
}

// Global page sync
if (isset($data['page'])) {
    $page = $data['page'];
}

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intelligence Hub - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --maroon: #7C0000;
            --maroon-dark: #5A0000;
            --maroon-light: #9B1B1B;
            --gold: #B08D2C;
            --page-bg: #F8F9FA;
            --card-bg: #FFFFFF;
            --border-color: #E5E7EB;
            --text-primary: #111827;
            --text-secondary: #4B5563;
            --text-muted: #9CA3AF;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
            --shadow-modal: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { 
            background: var(--page-bg); 
            color: var(--text-primary); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .hub-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 40px;
        }

        header { 
            margin-bottom: 24px; 
            background: var(--card-bg);
            padding: 24px 30px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        h1 { font-size: 22px; font-weight: 700; margin: 0; letter-spacing: -0.02em; color: var(--maroon); }
        .subtitle { color: var(--text-secondary); font-size: 13.5px; margin-top: 4px; }

        /* Tabs */
        .hub-tabs {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 24px;
            background: transparent;
        }

        .tab-link {
            padding: 9px 18px;
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 12.5px;
            letter-spacing: 0.03em;
            transition: all 0.15s ease;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
        }

        .tab-link:hover { color: var(--text-primary); background: #F3F4F6; }
        .tab-link.active {
            color: #FFFFFF;
            background: var(--maroon);
            border-color: var(--maroon);
        }

        /* Tables - Clean ERP */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--card-bg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .data-table th {
            text-align: left;
            padding: 12px 16px;
            background: #F9FAFB;
            color: var(--text-secondary);
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--border-color);
            border-right: none;
        }

        .data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13.5px;
            color: var(--text-primary);
            border-right: none;
            vertical-align: middle;
        }

        .data-table tr:hover td { background: #FDFEFE; }
        .data-table tr:last-child td { border-bottom: none; }

        /* Generic UI */
        .btn-black {
            background: var(--maroon);
            color: #FFFFFF;
            border: none;
            padding: 8px 16px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.02em;
            border-radius: var(--radius-sm);
            transition: all 0.15s ease;
        }

        .btn-black:hover { background: var(--maroon-dark); color: #FFFFFF; opacity: 1; }

        .btn-outline {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-outline:hover { background: #F3F4F6; color: var(--text-primary); border-color: #D1D5DB; }

        .search-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            padding: 18px 24px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            align-items: center;
            box-shadow: var(--shadow-sm);
        }

        .form-input {
            padding: 9px 14px;
            border: 1px solid #D1D5DB;
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            width: 100%;
            max-width: 280px;
            background: #FFFFFF;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-input:focus { outline: none; border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(124, 0, 0, 0.12); }

        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 30px;
        }

        .page-btn {
            padding: 7px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 12.5px;
            font-weight: 600;
            background: #FFFFFF;
            transition: all 0.15s ease;
        }

        .page-btn.active {
            background: var(--maroon);
            color: #FFFFFF;
            border-color: var(--maroon);
        }

        .page-btn:hover:not(.active) { background: #F3F4F6; color: var(--text-primary); border-color: #D1D5DB; }

        .tag {
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 9999px;
            text-transform: uppercase;
            display: inline-block;
            background: #F3F4F6;
            color: var(--text-secondary);
            border: 1px solid #E5E7EB;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 22px;
            background: var(--card-bg);
            box-shadow: var(--shadow-sm);
        }

        .stat-val { font-size: 32px; font-weight: 800; line-height: 1; color: var(--maroon); }
        .stat-lbl { font-size: 11.5px; font-weight: 700; color: var(--text-secondary); margin-top: 8px; text-transform: uppercase; letter-spacing: 0.04em; }

        @media (max-width: 768px) {
            .search-bar { flex-direction: column; }
            .form-input { max-width: none; }
            .hub-container { padding: 20px; }
        }

        /* Modal System ERP */
        .modal-overlay {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            background: rgba(17, 24, 39, 0.45);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-content {
            background: #FFFFFF;
            width: 100%;
            max-width: 580px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-modal);
            padding: 28px;
            position: relative;
            max-height: 85vh;
            overflow-y: auto;
            box-sizing: border-box;
        }

        .modal-header-erp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--border-color);
        }

        .close-btn-erp {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #F3F4F6;
            color: var(--text-secondary);
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .close-btn-erp:hover { background: #E5E7EB; color: var(--text-primary); }

        .modal-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .modal-item { border: 1px solid var(--border-color); padding: 12px 14px; background: #F9FAFB; border-radius: var(--radius-sm); }
        .modal-item-title { font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 4px; }
        .modal-item-val { font-size: 16px; font-weight: 700; color: var(--text-primary); }

        /* Card Layout for Portfolio/AI */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .info-card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
            background: var(--card-bg);
            box-shadow: var(--shadow-sm);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .card-date { font-size: 11px; color: var(--text-muted); font-family: monospace; }
        
        .card-student-name { font-size: 16px; font-weight: 700; margin: 0; color: var(--text-primary); }
        .card-student-meta { font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 12px; }
        
        .card-content-box {
            background: #F9FAFB;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin-bottom: 14px;
            flex-grow: 1;
        }

        .card-category { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 4px; }
        .card-title { font-size: 14px; font-weight: 700; margin: 0 0 4px; color: var(--text-primary); }
        .card-desc { font-size: 13px; line-height: 1.5; color: var(--text-secondary); }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }

        .card-branch { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); }
        .card-score { font-size: 18px; font-weight: 800; color: var(--maroon); }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="hub-container">
        <header>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
            <h1>Intelligence Hub</h1>
            <p class="subtitle">Minimalist data orchestration for placement officers.</p>
        </header>

        <nav class="hub-tabs">
            <a href="?section=details" class="tab-link <?php echo $section === 'details' ? 'active' : ''; ?>">STUDENT LIST</a>
            <a href="?section=portfolio" class="tab-link <?php echo $section === 'portfolio' ? 'active' : ''; ?>">PORTFOLIO VERIFICATION</a>
            <a href="?section=ai" class="tab-link <?php echo $section === 'ai' ? 'active' : ''; ?>">AI PERFORMANCE</a>
            <a href="?section=stats" class="tab-link <?php echo $section === 'stats' ? 'active' : ''; ?>">SYSTEM STATS</a>
        </nav>

        <form method="POST" class="search-bar">
            <input type="hidden" name="section" value="<?php echo htmlspecialchars((string)$section); ?>">
            <input type="text" name="search" class="form-input" placeholder="Search USN or Name..." 
                   value="<?php echo htmlspecialchars((string)($filters['search'] ?? '')); ?>"
                   onchange="this.form.submit()">
            
            <select name="discipline" class="form-input" onchange="this.form.submit()">
                <option value="">All Branches</option>
                <?php foreach($disciplines as $d): ?>
                    <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($filters['discipline'] ?? '') === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="institution" class="form-input" style="max-width: 150px;" onchange="this.form.submit()">
                <option value="">Institutions</option>
                <option value="GMU" <?php echo ($filters['institution'] ?? '') === 'GMU' ? 'selected' : ''; ?>>GMU</option>
                <option value="GMIT" <?php echo ($filters['institution'] ?? '') === 'GMIT' ? 'selected' : ''; ?>>GMIT</option>
            </select>

            <?php if ($section === 'details'): ?>
            <select name="semester" class="form-input" style="max-width: 150px;" onchange="this.form.submit()">
                <option value="">Semesters</option>
                <?php foreach([5,6,7,8] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo (int)($filters['semester'] ?? 0) === $s ? 'selected' : ''; ?>>Sem <?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <?php if ($section === 'portfolio'): ?>
            <select name="status" class="form-input" style="max-width: 150px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="1" <?php echo (isset($filters['status']) && $filters['status'] === '1') ? 'selected' : ''; ?>>Verified</option>
                <option value="0" <?php echo (isset($filters['status']) && $filters['status'] === '0') ? 'selected' : ''; ?>>Not Verified</option>
            </select>
            <?php endif; ?>

            <!-- Automatic filtering enabled - button removed -->
        </form>

        <?php if ($section === 'details'): ?>

            <div style="overflow-x: auto; background: #fff; border-radius: 12px; border: 1px solid var(--border);">
                <table class="data-table" style="min-width: 1550px; margin: 0;">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Branch</th>
                            <th style="width: 50px;">Sem</th>
                            <th style="width: 70px;">Inst</th>
                            <th>Contact No</th>
                            <th>Email ID</th>
                            <th>USN / ID</th>
                            <th>10th %</th>
                            <th>12th %</th>
                            <th>Father Name</th>
                            <th>Mother Name</th>
                            <th>Address</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($data['data'])): foreach ($data['data'] as $s): ?>
                        <tr>
                            <td style="font-weight: 800; font-size: 13px;"><?php echo htmlspecialchars((string)($s['name'] ?? 'Unknown')); ?></td>
                            <td style="font-size: 11px;"><?php echo htmlspecialchars((string)($s['discipline'] ?? '-')); ?></td>
                            <td style="font-weight: 700;"><?php echo (string)($s['sem'] ?? '-'); ?></td>
                            <td><span class="tag"><?php echo htmlspecialchars((string)($s['institution'] ?? '-')); ?></span></td>
                            <td style="font-weight: 600; font-size: 12px; color: #1e293b; mso-number-format:'\@';"><?php echo htmlspecialchars((string)($s['mobile'] ?? ($s['phone'] ?? '-'))); ?></td>
                            <td style="font-size: 12px; color: #2563eb; font-weight: 500;"><?php echo htmlspecialchars((string)($s['email'] ?? '-')); ?></td>
                            <td style="font-family: 'Courier New', monospace; font-weight: 700; font-size: 12px; mso-number-format:'\@';"><?php echo htmlspecialchars((string)($s['usn'] ?? 'N/A')); ?></td>
                            <td style="font-weight: 700; color: #000000ff;"><?php echo !empty($s['sslc_percentage']) ? round($s['sslc_percentage'], 1) . '%' : '-'; ?></td>
                            <td style="font-weight: 700; color: #000000ff;"><?php echo !empty($s['puc_percentage']) ? round($s['puc_percentage'], 1) . '%' : '-'; ?></td>
                            <td style="font-size: 12px;"><?php echo htmlspecialchars((string)($s['father_name'] ?? '-')); ?></td>
                            <td style="font-size: 12px;"><?php echo htmlspecialchars((string)($s['mother_name'] ?? '-')); ?></td>
                            <td style="max-width: 250px;">
                                <div style="font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars((string)($s['address'] ?? '')); ?>">
                                    <?php echo htmlspecialchars((string)($s['address'] ?? '-')); ?>
                                </div>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="../student/view_resume.php?usn=<?php echo urlencode($s['usn'] ?? ''); ?>" target="_blank" class="btn-black btn-outline" style="padding: 6px 10px; font-size: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 3px;">
                                    <i class="fas fa-file-pdf"></i> Resume
                                </a>
                                <button onclick="viewSgpa('<?php echo htmlspecialchars((string)($s['usn'] ?? '')); ?>', '<?php echo htmlspecialchars((string)($s['institution'] ?? '')); ?>')" class="btn-black btn-outline" style="padding: 6px 10px; font-size: 10px;">SGPA</button>
                                <button onclick="viewPortfolio('<?php echo htmlspecialchars((string)($s['usn'] ?? '')); ?>', '<?php echo htmlspecialchars((string)($s['institution'] ?? '')); ?>')" class="btn-black btn-outline" style="padding: 6px 10px; font-size: 10px;">Portfolio</button>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="13" style="text-align: center; padding: 40px;">No records found matching filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (isset($data['total_pages']) && $data['total_pages'] > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&section=details" class="page-btn">PREV</a>
                <?php endif; ?>
                
                <?php for($i=1; $i<=$data['total_pages']; $i++): 
                    if ($i > 3 && $i < $data['total_pages'] - 2 && abs($i - $page) > 2) { 
                        if($i == 4 || $i == $data['total_pages'] - 3) echo '<span style="padding: 10px;">...</span>'; 
                        continue; 
                    }
                ?>
                    <a href="?page=<?php echo $i; ?>&section=details" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page < $data['total_pages']): ?>
                    <a href="?page=<?php echo $page + 1; ?>&section=details" class="page-btn">NEXT</a>
                <?php endif; ?>
            </div>
            <p style="text-align: center; font-size: 12px; color: var(--text-muted); margin-top: 15px;">Showing page <?php echo $page; ?> of <?php echo $data['total_pages']; ?> (<?php echo $data['total']; ?> total students)</p>
            <?php endif; ?>

        <?php elseif ($section === 'portfolio'): ?>
            <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 25px;">
                <div class="stat-card">
                    <div class="stat-val"><?php echo number_format($data['summary']['total'] ?? 0); ?></div>
                    <div class="stat-lbl">Total Submissions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val"><?php echo number_format($data['summary']['pending'] ?? 0); ?></div>
                    <div class="stat-lbl">Pending Verification</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val"><?php echo number_format($data['summary']['verified'] ?? 0); ?></div>
                    <div class="stat-lbl">Verified Items</div>
                </div>
            </div>

            <div style="overflow-x: auto; background: #fff; border-radius: 12px; border: 1px solid var(--border);">
                <table class="data-table" style="min-width: 1400px; margin: 0;">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Date</th>
                            <th>Student Name</th>
                            <th>USN / ID</th>
                            <th style="width: 80px;">Inst</th>
                            <th>Branch</th>
                            <th>Category</th>
                            <th>Topic & Details</th>
                            <th style="text-align: right;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($data['data'])): foreach ($data['data'] as $item): ?>
                        <tr id="row-<?php echo $item['id']; ?>">
                            <td style="font-size: 11px; font-family: monospace;"><?php echo date('d-m-Y', strtotime($item['created_at'])); ?></td>
                            <td style="font-weight: 800; font-size: 13px;"><?php echo htmlspecialchars((string)$item['student_name']); ?></td>
                            <td style="font-family: 'Courier New', monospace; font-weight: 700; font-size: 12px;"><?php echo htmlspecialchars((string)$item['usn']); ?></td>
                            <td><span class="tag"><?php echo $item['institution']; ?></span></td>
                            <td style="font-size: 11px;"><?php echo htmlspecialchars((string)($item['discipline'] ?? '-')); ?></td>
                            <td><span class="tag" style="background: var(--gray-subtle); color: #000;"><?php echo $item['category']; ?></span></td>
                            <td>
                                <div style="font-weight: 700; font-size: 13px;"><?php echo htmlspecialchars((string)$item['title']); ?></div>
                                <div style="font-size: 11px; margin-top: 4px; color: var(--text-muted);"><?php echo htmlspecialchars((string)$item['description']); ?></div>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button onclick="viewPortfolio('<?php echo $item['usn']; ?>', '<?php echo $item['institution']; ?>')" 
                                        class="btn-black <?php echo (isset($item['is_verified']) && $item['is_verified']) ? '' : 'btn-outline'; ?>" 
                                        style="padding: 6px 12px; font-size: 11px; min-width: 100px;">
                                    <?php echo (isset($item['is_verified']) && $item['is_verified']) ? 'Verified' : 'Not Verified'; ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="8" style="text-align: center; padding: 80px; color: var(--text-muted); font-weight: 700;">NO PORTFOLIO ITEMS RECORDED</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (isset($data['total_pages']) && $data['total_pages'] > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&section=portfolio" class="page-btn">PREV</a>
                <?php endif; ?>
                
                <?php for($i=1; $i<=$data['total_pages']; $i++): 
                    if ($i > 3 && $i < $data['total_pages'] - 2 && abs($i - $page) > 2) { 
                        if($i == 4 || $i == $data['total_pages'] - 3) echo '<span style="padding: 10px;">...</span>'; 
                        continue; 
                    }
                ?>
                    <a href="?page=<?php echo $i; ?>&section=portfolio" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page < $data['total_pages']): ?>
                    <a href="?page=<?php echo $page + 1; ?>&section=portfolio" class="page-btn">NEXT</a>
                <?php endif; ?>
            </div>
            <p style="text-align: center; font-size: 12px; color: var(--text-muted); margin-top: 15px;">Showing page <?php echo $page; ?> of <?php echo $data['total_pages']; ?> (<?php echo $data['total']; ?> pending items)</p>
            <?php endif; ?>

        <?php elseif ($section === 'ai'): ?>
            <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 25px;">
                <div class="stat-card">
                    <div class="stat-val"><?php echo number_format($data['summary']['total_assessments'] ?? 0); ?></div>
                    <div class="stat-lbl">Total Assessments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val"><?php echo $data['summary']['avg_score'] ?? 0; ?>%</div>
                    <div class="stat-lbl">Average AI Score</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val"><?php echo number_format($data['summary']['filtered_count'] ?? 0); ?></div>
                    <div class="stat-lbl">Students in Context</div>
                </div>
            </div>

            <div style="overflow-x: auto; background: #fff; border-radius: 12px; border: 1px solid var(--border);">
                <table class="data-table" style="min-width: 1400px; margin: 0;">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Date</th>
                            <th>Student Name</th>
                            <th>USN / ID</th>
                            <th style="width: 80px;">Inst</th>
                            <th>Branch</th>
                            <th>Assessment Type</th>
                            <th>Company / Context</th>
                            <th style="text-align: right;">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($data['data'])): foreach ($data['data'] as $it): ?>
                        <tr>
                            <td style="font-size: 11px; font-family: monospace;"><?php echo date('d-m-Y', strtotime($it['started_at'])); ?></td>
                            <td style="font-weight: 800; font-size: 13px;"><?php echo htmlspecialchars($it['full_name'] ?? 'Unknown'); ?></td>
                            <td style="font-family: 'Courier New', monospace; font-weight: 700; font-size: 12px;"><?php echo htmlspecialchars($it['usn'] ?? ''); ?></td>
                            <td><span class="tag"><?php echo htmlspecialchars((string)($it['institution'] ?? '-')); ?></span></td>
                            <td style="font-size: 11px;"><?php echo htmlspecialchars((string)($it['discipline'] ?? '-')); ?></td>
                            <td><span class="tag" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;"><?php echo htmlspecialchars((string)($it['assessment_type'] ?? 'General')); ?></span></td>
                            <td><span style="font-weight: 700; font-size: 13px;"><?php echo htmlspecialchars($it['company_name'] ?? 'General'); ?></span></td>
                            <td style="font-weight: 900; font-size: 16px; text-align: right; color: #000;"><?php echo (int)$it['score']; ?>%</td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="8" style="text-align: center; padding: 80px; color: var(--text-muted); font-weight: 700;">NO ASSESSMENT RECORDS FOUND</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (isset($data['total_pages']) && $data['total_pages'] > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&section=ai" class="page-btn">PREV</a>
                <?php endif; ?>
                
                <?php for($i=1; $i<=$data['total_pages']; $i++): 
                    if ($i > 3 && $i < $data['total_pages'] - 2 && abs($i - $page) > 2) { 
                        if($i == 4 || $i == $data['total_pages'] - 3) echo '<span style="padding: 10px;">...</span>'; 
                        continue; 
                    }
                ?>
                    <a href="?page=<?php echo $i; ?>&section=ai" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page < $data['total_pages']): ?>
                    <a href="?page=<?php echo $page + 1; ?>&section=ai" class="page-btn">NEXT</a>
                <?php endif; ?>
            </div>
            <p style="text-align: center; font-size: 12px; color: var(--text-muted); margin-top: 15px;">Showing page <?php echo $page; ?> of <?php echo $data['total_pages']; ?> (<?php echo $data['total']; ?> total reports)</p>
            <?php endif; ?>

        <?php elseif ($section === 'stats'): ?>
            <?php $stats = $officerModel->getDashboardStats(); ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-val"><?php echo number_format($stats['total_students']); ?></div>
                    <div class="stat-lbl">Active Student Base</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val"><?php echo number_format($stats['placed_students']); ?></div>
                    <div class="stat-lbl">Successfully Placed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val"><?php echo number_format($stats['total_applications']); ?></div>
                    <div class="stat-lbl">Total Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val"><?php echo number_format($stats['active_jobs']); ?></div>
                    <div class="stat-lbl">Live Job Postings</div>
                </div>
            </div>
            <div style="padding: 30px; border: 1px solid var(--border); background: var(--gray-subtle); text-align: center; margin-bottom: 20px;">
                <p style="font-weight: 700; margin-bottom: 20px;">Detailed placement and academic data can be exported via the Student List section.</p>
                <a href="?section=details&export=1" class="btn-black">Download Master Report (.XLS)</a>
            </div>

            <h2 style="font-size: 16px; font-weight: 900; margin: 30px 0 15px;">RECENT PLACEMENTS</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student Name & ID</th>
                        <th>Branch</th>
                        <th>Company</th>
                        <th>Job Title</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($data['data'])): foreach ($data['data'] as $p): ?>
                    <tr>
                        <td style="font-size: 11px; font-family: monospace;"><?php echo date('d-m-Y', strtotime($p['applied_at'])); ?></td>
                        <td>
                            <div style="font-weight: 800;"><?php echo htmlspecialchars((string)$p['student_name']); ?></div>
                            <div style="font-size: 11px; font-weight: 600;"><?php echo htmlspecialchars((string)$p['usn']); ?></div>
                        </td>
                        <td style="font-size: 11px;"><?php echo htmlspecialchars((string)($p['discipline'] ?? '-')); ?></td>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars((string)$p['company_name']); ?></td>
                        <td><?php echo htmlspecialchars((string)$p['job_title']); ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px;">No recent placements found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if (isset($data['total_pages']) && $data['total_pages'] > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&section=stats" class="page-btn">PREV</a>
                <?php endif; ?>
                
                <?php for($i=1; $i<=$data['total_pages']; $i++): 
                    if ($i > 3 && $i < $data['total_pages'] - 2 && abs($i - $page) > 2) { 
                        if($i == 4 || $i == $data['total_pages'] - 3) echo '<span style="padding: 10px;">...</span>'; 
                        continue; 
                    }
                ?>
                    <a href="?page=<?php echo $i; ?>&section=stats" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page < $data['total_pages']): ?>
                    <a href="?page=<?php echo $page + 1; ?>&section=stats" class="page-btn">NEXT</a>
                <?php endif; ?>
            </div>
            <p style="text-align: center; font-size: 12px; color: var(--text-muted); margin-top: 15px;">Showing page <?php echo $page; ?> of <?php echo $data['total_pages']; ?> (<?php echo $data['total']; ?> total placements)</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Modals -->
    <div id="infoModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header-erp">
                <div id="modalTitle" style="color: var(--maroon); font-size: 17px; font-weight: 700; margin: 0;">Details</div>
                <button type="button" class="close-btn-erp" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        function closeModal() { document.getElementById('infoModal').style.display = 'none'; }
        
        async function viewSgpa(usn, inst) {
            const modal = document.getElementById('infoModal');
            const title = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');
            
            title.innerText = "SGPA HISTORY: " + usn;
            body.innerHTML = "Loading...";
            modal.style.display = 'flex';

            try {
                const res = await fetch('get_sgpa_details.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `usn=${usn}&institution=${inst}`
                });
                const d = await res.json();
                if (d.success) {
                    let html = '<div class="modal-grid">';
                    for (let sem = 1; sem <= 8; sem++) {
                        html += `
                            <div class="modal-item">
                                <div class="modal-item-title">Semester ${sem}</div>
                                <div class="modal-item-val">${d.sgpa[sem]}</div>
                            </div>`;
                    }
                    html += '</div>';
                    body.innerHTML = html;
                } else { body.innerHTML = 'Failed to load data.'; }
            } catch (e) { body.innerHTML = 'Error fetching data.'; }
        }

        async function viewPortfolio(usn, inst) {
            const modal = document.getElementById('infoModal');
            const title = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');
            
            title.innerText = "PORTFOLIO: " + usn;
            body.innerHTML = "Loading...";
            modal.style.display = 'flex';

            try {
                const res = await fetch('portfolio_details.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `usn=${usn}&institution=${inst}`
                });
                const d = await res.json();
                if (d.success) {
                    let html = '<div>';
                    if (d.skills.length > 0) {
                        html += '<h3 style="font-size:14px; text-transform:uppercase; margin: 20px 0 10px;">Skills</h3><div class="modal-grid">';
                        d.skills.forEach(s => {
                            html += `
                                <div class="modal-item">
                                    <div class="modal-item-title">${s.is_verified ? 'VERIFIED' : 'PENDING'}</div>
                                    <div class="modal-item-val">${s.title}</div>
                                    <div style="font-size:11px; margin-top:5px;">${s.sub_title || ''}</div>
                                </div>`;
                        });
                        html += '</div>';
                    }
                    if (d.projects.length > 0) {
                        html += '<h3 style="font-size:14px; text-transform:uppercase; margin: 20px 0 10px;">Projects</h3>';
                        d.projects.forEach(p => {
                            html += `
                                <div class="modal-item" style="margin-bottom:10px;">
                                    <div class="modal-item-title">${p.is_verified ? 'VERIFIED' : 'PENDING'}</div>
                                    <div class="modal-item-val">${p.title}</div>
                                    <div style="font-size:12px; margin-top:5px;">${p.description}</div>
                                    ${p.link ? `<a href="${p.link}" target="_blank" style="font-size:11px; color:#000;">View Project</a>` : ''}
                                </div>`;
                        });
                    }
                    if (d.skills.length === 0 && d.projects.length === 0) {
                        html = '<p>No portfolio items found.</p>';
                    }
                    html += '</div>';
                    body.innerHTML = html;
                } else { body.innerHTML = 'Failed to load data.'; }
            } catch (e) { body.innerHTML = 'Error fetching data.'; }
        }
    </script>
</body>
</html>

