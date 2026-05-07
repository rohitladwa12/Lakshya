<?php
/**
 * NQT Unified Report Viewer
 * Handles Technical and HR round reports with PDF download.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

use App\Helpers\SessionFilterHelper;

requireLogin();

$userId = getUserId();
$studentIdForDb = getStudentIdForAssessment();

// Handle POST from assessment completion or dashboard
if (isPost() && isset($_POST['id'])) {
    SessionFilterHelper::setFilters('nqt_report', [
        'id' => $_POST['id'] ?? 0
    ]);
    header("Location: nqt_report.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('nqt_report');
$sessionId = $filters['id'] ?? 0;

$db = getDB();
$stmt = $db->prepare("SELECT * FROM unified_ai_assessments WHERE id = ? AND student_id = ?");
$stmt->execute([$sessionId, $studentIdForDb]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    Session::flash('error', 'Assessment report not found.');
    redirect('dashboard');
}

$details = json_decode($session['details'], true) ?? [];
$reportContent = $details['report_content'] ?? 'Report is still being processed. Please refresh in a moment.';
$type = $session['assessment_type'];
$date = date('d M Y', strtotime($session['completed_at'] ?? $session['started_at']));
$studentName = $session['student_name'] ?: getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NQT Performance Report - <?php echo htmlspecialchars($type); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary: #800000;
            --secondary: #e9c66f;
            --dark: #121212;
            --card-bg: #ffffff;
            --text: #333333;
            --gray: #666666;
            --border: #eeeeee;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        
        body { 
            background: #f0f2f5; 
            color: var(--text); 
            line-height: 1.6;
            padding-bottom: 50px;
        }

        .top-nav {
            background: #ffffff;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 900px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--card-bg);
            padding: 60px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            position: relative;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 4px solid var(--primary);
            padding-bottom: 30px;
            margin-bottom: 40px;
        }

        .header-logo h1 {
            color: var(--primary);
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .header-logo p {
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }

        .header-meta {
            text-align: right;
        }

        .header-meta p {
            margin-bottom: 5px;
            font-size: 0.95rem;
        }

        .header-meta strong {
            color: var(--primary);
        }

        .score-box {
            background: var(--primary);
            color: white;
            padding: 20px;
            border-radius: 12px;
            display: inline-block;
            text-align: center;
            margin-bottom: 30px;
        }

        .score-box .label { font-size: 0.8rem; text-transform: uppercase; opacity: 0.8; }
        .score-box .value { font-size: 2.5rem; font-weight: 700; }

        .report-section {
            margin-bottom: 40px;
        }

        .report-section h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .report-content {
            font-size: 1.1rem;
        }

        /* Markdown Styling */
        .report-content h1, .report-content h2, .report-content h3 {
            color: var(--primary);
            margin: 25px 0 15px 0;
        }
        .report-content h2 { font-size: 1.4rem; border-bottom: 1px solid var(--border); padding-bottom: 8px; }
        .report-content h3 { font-size: 1.2rem; }
        .report-content p { margin-bottom: 15px; }
        .report-content ul, .report-content ol { margin-bottom: 15px; padding-left: 20px; }
        .report-content li { margin-bottom: 8px; }
        .report-content strong { color: #000; font-weight: 700; }

        .badge-score {
            background: var(--secondary);
            color: var(--primary);
            padding: 2px 10px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            cursor: pointer;
            border: none;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-outline { border: 2px solid var(--primary); color: var(--primary); background: transparent; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); opacity: 0.9; }

        .transcript-toggle {
            margin-top: 50px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        @media print {
            .top-nav { display: none; }
            .container { box-shadow: none; padding: 0; width: 100%; }
        }
    </style>
</head>
<body>

<div class="top-nav">
    <div class="nav-container">
        <a href="dashboard" class="btn btn-outline" style="padding: 8px 20px;">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
        <button onclick="downloadPDF()" class="btn btn-primary">
            <i class="fas fa-download"></i> Download PDF Report
        </button>
    </div>
</div>

<div class="container" id="reportArea">
    <div class="header">
        <div class="header-logo">
            <h1>LAKSHYA</h1>
            <p>NQT Practice Assessment</p>
        </div>
        <div class="header-meta">
            <p>Candidate: <strong><?php echo htmlspecialchars($studentName); ?></strong></p>
            <p>Round: <strong><?php echo htmlspecialchars($type); ?></strong></p>
            <p>Date: <strong><?php echo $date; ?></strong></p>
        </div>
    </div>

    <div style="display: flex; gap: 30px; align-items: flex-start;">
        <div class="score-box">
            <div class="label">Calculated Score</div>
            <div class="value"><?php echo round($session['score'] ?? 0); ?>%</div>
        </div>
        <div style="flex: 1; padding-top: 10px;">
            <h2 style="color: var(--primary); margin-bottom: 10px;">Performance Overview</h2>
            <p style="color: var(--gray);">This report contains a comprehensive evaluation of your performance in the <?php echo htmlspecialchars($type); ?> round, conducted via AI simulation.</p>
        </div>
    </div>

    <div class="report-content">
        <?php
        // Basic Markdown to HTML conversion
        $html = $reportContent;
        $html = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $html);
        $html = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/^- (.*$)/m', '<li>$1</li>', $html);
        
        // Wrap lists
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
        $html = str_replace('</ul><ul>', '', $html);
        
        // Convert scores to badges
        $html = preg_replace('/Score: (\d+\/10)/', 'Score: <span class="badge-score">$1</span>', $html);

        echo $html;
        ?>
    </div>

    <div style="margin-top: 60px; padding-top: 20px; border-top: 1px solid var(--border); font-size: 0.85rem; color: var(--gray); text-align: center;">
        <p>This is an automated evaluation generated by the Lakshya Assessment Engine.</p>
        <p>© <?php echo date('Y'); ?> Lakshya Career Portal. All rights reserved.</p>
    </div>
</div>

<script>
    function downloadPDF() {
        const element = document.getElementById('reportArea');
        const studentName = "<?php echo addslashes($studentName); ?>";
        const type = "<?php echo addslashes($type); ?>";
        
        const opt = {
            margin:       [15, 15, 15, 15],
            filename:     `NQT_Report_${studentName}_${type}.pdf`,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save();
    }
</script>

</body>
</html>

