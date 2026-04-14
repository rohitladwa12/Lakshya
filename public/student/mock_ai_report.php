<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
require_once __DIR__ . '/../../src/Models/User.php';

use App\Helpers\SessionFilterHelper;

requireRole(ROLE_STUDENT);

$userId = getUserId();
$studentIdForDb = getStudentIdForAssessment();

// Handle POST from assessment completion or dashboard
if (isPost() && isset($_POST['session_id'])) {
    SessionFilterHelper::setFilters('mock_ai_report', [
        'session_id' => $_POST['session_id'] ?? 0
    ]);
    header("Location: mock_ai_report.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('mock_ai_report');
$sessionId = $filters['session_id'] ?? 0;

$db = getDB();
$sql = "SELECT m.* FROM mock_ai_interview_sessions m WHERE m.id = ? AND m.student_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$sessionId, $studentIdForDb]);
$session = $stmt->fetch();

if (!$session || !$session['report_content']) {
    Session::flash('error', 'Report not found or still generating.');
    redirect('mock_ai_interview');
}

// Resolve student name: on server use remote GMU/GMIT via User model; fallback to session name if remote unavailable
$studentName = getFullName() ?: 'Student';
$institution = $session['institution'] ?? getInstitution();
$lookupId = $userId ?: getUsername();
if ($lookupId && $institution) {
    try {
        $userModel = new User();
        $remoteUser = $userModel->find($lookupId, $institution);
        if ($remoteUser && !empty($remoteUser['full_name'])) {
            $studentName = $remoteUser['full_name'];
        }
    } catch (Exception $e) {
        // Remote DB not available (e.g. local dev): keep getFullName()
    }
}

$role = $session['role_name'];
$reportContent = $session['report_content'];
$date = date('d M Y', strtotime($session['started_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Performance Report - GM University</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --white: #ffffff;
            --bg: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg);
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            padding: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-radius: 8px;
            position: relative;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid var(--primary-maroon);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header-left h1 {
            color: var(--primary-maroon);
            margin: 0;
            font-size: 2rem;
            text-transform: uppercase;
        }

        .header-right {
            text-align: right;
        }

        .header-right p {
            margin: 2px 0;
            font-weight: 600;
            color: #666;
        }

        .report-section {
            margin-bottom: 30px;
        }

        .report-section h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary-maroon);
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }

        .report-section h3 {
            color: #444;
            margin-top: 20px;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .metric-card {
            background: #fdfdfd;
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 6px;
        }

        .score-badge {
            display: inline-block;
            background: var(--primary-maroon);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .btn-container {
            max-width: 900px;
            margin: 20px auto;
            display: flex;
            justify-content: space-between;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-download {
            background-color: var(--primary-maroon);
            color: white;
        }

        .btn-back {
            background-color: #666;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        @media print {
            .btn-container { display: none; }
            .container { box-shadow: none; margin: 0; width: 100%; border-radius: 0; }
        }

        /* Markdown-ish styling */
        .report-body strong { color: var(--primary-maroon); }
        .report-body ul { padding-left: 20px; }
        .report-body li { margin-bottom: 8px; }
        .report-body p { margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="btn-container">
    <button onclick="exitReport()" class="btn btn-back">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </button>
    <button onclick="downloadPDF()" class="btn btn-download">
        <i class="fas fa-file-pdf"></i> Download Performance Report
    </button>
</div>

<div class="container" id="reportContent">
    <div class="header">
        <div class="header-left">
            <h1>GM UNIVERSITY</h1>
            <p>Training & Placement Cell</p>
        </div>
        <div class="header-right">
            <p>Student: <?php echo htmlspecialchars((string)$studentName); ?></p>
            <p>Role: <?php echo htmlspecialchars((string)$role); ?></p>
            <p>Date: <?php echo $date; ?></p>
        </div>
    </div>

    <div class="report-body">
        <?php
        // Convert Markdown-ish to HTML
        $html = $reportContent;
        // Basic Markdown Conversion
        $html = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/^- (.*$)/m', '<li>$1</li>', $html);
        
        // Wrap lists
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
        // Fix double ul
        $html = str_replace('</ul><ul>', '', $html);
        
        // Convert scores to badges
        $html = preg_replace('/Score: (\d+\/10)/', 'Score: <span class="score-badge">$1</span>', $html);

        echo $html;
        ?>
    </div>

    <div style="margin-top: 50px; border-top: 1px solid #eee; padding-top: 20px; font-size: 0.8rem; color: #999; text-align: center;">
        This is an AI-generated assessment report by GM University Placement Portal. 
        It is intended for student evaluation and preparation purposes only.
    </div>
</div>

<script>
    function exitReport() {
        // Replace current history entry so browser "Back" doesn't return here
        window.location.replace('dashboard.php');
    }

    async function autoSaveReport() {
        const element = document.getElementById('reportContent');
        const opt = {
            margin:       [10, 10, 10, 10],
            filename:     'temp_report.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        // Generate PDF as blob
        const pdfBlob = await html2pdf().set(opt).from(element).output('blob');
        
        // Upload to server
        const formData = new FormData();
        formData.append('action', 'save_pdf');
        formData.append('session_id', '<?php echo $sessionId; ?>');
        formData.append('report_pdf', pdfBlob, 'report.pdf');

        try {
            const res = await fetch('mock_ai_handler.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            console.log('Auto-save status:', data);
        } catch(e) {
            console.error('Failed to auto-save report:', e);
        }
    }

    function downloadPDF() {
        const element = document.getElementById('reportContent');
        const opt = {
            margin:       [10, 10, 10, 10],
            filename:     'GMU_Mock_Interview_Report.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save();
    }

    // Trigger auto-save after a short delay for rendering
    window.onload = () => {
        setTimeout(autoSaveReport, 1500);
    };
</script>

</body>
</html>
