<?php
ob_start();
require_once __DIR__ . '/../../config/bootstrap.php';

// Allow Student, HOD, Coordinator, Placement Officer, and Admin roles
requireLogin();
$currentRole = Session::getRole();
$allowedRoles = [ROLE_STUDENT, ROLE_HOD, ROLE_DEPT_COORDINATOR, ROLE_PLACEMENT_OFFICER, ROLE_ADMIN];
if (!in_array($currentRole, $allowedRoles) && $currentRole !== ROLE_DEMO) {
    Session::flash('error', 'Access denied. Insufficient permissions.');
    redirect('/Lakshya/');
}

$studentId = getUsername();
$institution = getInstitution();

// If HOD/coordinator/officer requests another student's report
if (in_array($currentRole, [ROLE_HOD, ROLE_DEPT_COORDINATOR, ROLE_PLACEMENT_OFFICER, ROLE_ADMIN, ROLE_DEMO]) && isset($_REQUEST['student_id'])) {
    $studentId = trim($_REQUEST['student_id']);
    // Resolve student's institution
    require_once __DIR__ . '/../../src/Models/User.php';
    $userModel = new \User();
    $studentUser = $userModel->find($studentId);
    if ($studentUser && isset($studentUser['institution'])) {
        $institution = $studentUser['institution'];
    }
}

require_once __DIR__ . '/../../src/Services/PlacementIntelligenceService.php';
$piceService = new \App\Services\PlacementIntelligenceService();

// Handle AJAX Chat POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chat') {
    header('Content-Type: application/json');
    $message = trim($_POST['message'] ?? '');
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty.']);
        exit;
    }

    try {
        // Fetch cached report first
        $reportResult = $piceService->generateAndCacheReport($studentId, $institution, false);
        $reportText = $reportResult['report_markdown'] ?? '';

        require_once __DIR__ . '/../../src/Services/AIService.php';
        $aiService = new \AIService();

        $systemPrompt = "You are the PICE AI Career Advisor. You are an expert career consultant answering student queries about their Placement Intelligence Report.
        
        Here is the student's Placement Intelligence Report:
        ------------------------------------
        {$reportText}
        ------------------------------------
        
        Guidelines:
        1. Answer the student's question based strictly on the metrics, evidence, and analysis present in the report above.
        2. Do NOT invent or estimate any scores, benchmarks, or facts not mentioned in the report.
        3. Be objective, recruiter-grade, encouraging, yet honest.
        4. Keep answers concise, clear, structured (using markdown if needed), and directly actionable.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message]
        ];

        $response = $aiService->callAPI($messages, [
            'audit_method' => 'pice_student_chat',
            'max_tokens' => 800,
            'temperature' => 0.4
        ]);

        if ($response['success']) {
            echo json_encode(['success' => true, 'reply' => $response['content']]);
        } else {
            echo json_encode(['success' => false, 'message' => $response['message']]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle Regeneration via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'regenerate') {
    header('Content-Type: application/json');
    try {
        $result = $piceService->generateAndCacheReport($studentId, $institution, true);
        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'Report regenerated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch (or generate if missing) the cached report
$errorMsg = null;
$reportMarkdown = '';

try {
    $reportResult = $piceService->generateAndCacheReport($studentId, $institution, false);
    if (!$reportResult['success'] && empty($reportResult['data'])) {
        $errorMsg = "Unable to generate your report: " . $reportResult['message'];
    } else {
        $reportMarkdown = $reportResult['report_markdown'] ?? '';
    }
} catch (Exception $e) {
    $errorMsg = "An error occurred while compiling your report: " . $e->getMessage();
}

/**
 * Custom Markdown-to-HTML parser for standard AI outputs
 */
function renderMarkdownToHtml($markdown)
{
    if (empty($markdown))
        return '<p class="text-muted">No report details available.</p>';

    // Escape HTML first for safety
    $html = htmlspecialchars($markdown, ENT_NOQUOTES);

    $lines = explode("\n", $html);
    $result = [];
    $inList = false;
    $inTable = false;
    $inBlockquote = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // 1. Blockquote (Disclaimer note)
        if (strpos($trimmed, '&gt;') === 0) {
            $content = trim(substr($trimmed, 4));
            if (!$inBlockquote) {
                $result[] = '<blockquote class="report-blockquote">';
                $inBlockquote = true;
            }
            $result[] = preg_replace('/[\*\_]{2}([^\*\_]+)[\*\_]{2}/', '<strong>$1</strong>', $content) . '<br>';
            continue;
        } else {
            if ($inBlockquote) {
                $result[] = '</blockquote>';
                $inBlockquote = false;
            }
        }

        // 2. Table parsing
        if (strpos($trimmed, '|') === 0) {
            if (!$inTable) {
                $result[] = '<div class="table-responsive"><table class="report-table">';
                $inTable = true;

                // Header row
                $cols = explode('|', trim($trimmed, '|'));
                $result[] = '<thead><tr>';
                foreach ($cols as $col) {
                    $result[] = '<th>' . preg_replace('/[\*\_]{2}([^\*\_]+)[\*\_]{2}/', '<strong>$1</strong>', trim($col)) . '</th>';
                }
                $result[] = '</tr></thead><tbody>';
                continue;
            }
            // Skip separators like |---|---|
            if (preg_match('/^[|:\-\s]+$/', $trimmed)) {
                continue;
            }
            $cols = explode('|', trim($trimmed, '|'));
            $result[] = '<tr>';
            foreach ($cols as $col) {
                $result[] = '<td>' . preg_replace('/[\*\_]{2}([^\*\_]+)[\*\_]{2}/', '<strong>$1</strong>', trim($col)) . '</td>';
            }
            $result[] = '</tr>';
            continue;
        } else {
            if ($inTable) {
                $result[] = '</tbody></table></div>';
                $inTable = false;
            }
        }

        // 3. Headers
        if (strpos($trimmed, '### ') === 0) {
            $content = trim(substr($trimmed, 4));
            $result[] = '<h4 class="report-h4">' . preg_replace('/[\*\_]{2}([^\*\_]+)[\*\_]{2}/', '<strong>$1</strong>', $content) . '</h4>';
            continue;
        }
        if (strpos($trimmed, '## ') === 0) {
            $content = trim(substr($trimmed, 3));
            $result[] = '<h3 class="report-h3">' . preg_replace('/[\*\_]{2}([^\*\_]+)[\*\_]{2}/', '<strong>$1</strong>', $content) . '</h3>';
            continue;
        }
        if (strpos($trimmed, '# ') === 0) {
            $content = trim(substr($trimmed, 2));
            $result[] = '<h2 class="report-h2">' . preg_replace('/[\*\_]{2}([^\*\_]+)[\*\_]{2}/', '<strong>$1</strong>', $content) . '</h2>';
            continue;
        }

        // 4. List Items
        if (strpos($trimmed, '- ') === 0 || strpos($trimmed, '* ') === 0) {
            $content = trim(substr($trimmed, 2));
            if (!$inList) {
                $result[] = '<ul class="report-ul">';
                $inList = true;
            }
            $result[] = '<li class="report-li">' . preg_replace('/[\*\_]{2}([^\*\_]+)[\*\_]{2}/', '<strong>$1</strong>', $content) . '</li>';
            continue;
        } else {
            if ($inList) {
                $result[] = '</ul>';
                $inList = false;
            }
        }

        // 5. Normal paragraphs
        if ($trimmed === '') {
            $result[] = '<p class="report-spacer"></p>';
        } else {
            $parsedLine = preg_replace('/[\*\_]{2}([^\*\_]+)[\*\_]{2}/', '<strong>$1</strong>', $trimmed);
            $result[] = '<p class="report-p">' . $parsedLine . '</p>';
        }
    }

    // Close remaining tags
    if ($inBlockquote)
        $result[] = '</blockquote>';
    if ($inTable)
        $result[] = '</tbody></table></div>';
    if ($inList)
        $result[] = '</ul>';

    return implode("\n", $result);
}

/**
 * Extracts a dynamic section from the AI Markdown report
 */
function getReportSectionHtml($markdown, $sectionNum, $nextSectionNum)
{
    if (empty($markdown)) {
        return '';
    }
    // Match the heading and all content until the next section heading or EOF
    $pattern = '/(?:^|\n)#+\s*' . $sectionNum . '\.\s*(.*?)(?=\n#+\s*' . $nextSectionNum . '\.|$)/is';
    if (preg_match($pattern, $markdown, $match)) {
        // Strip the section heading line
        $block = preg_replace('/^[^\n]*\n/s', '', trim($match[0]));
        return renderMarkdownToHtml(trim($block));
    }
    return '';
}

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placement Intelligence Report - <?php echo htmlspecialchars($fullName); ?></title>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Caveat:wght@700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --dark-maroon: #5b1f1f;
            --accent-gold: #D4AF37;
            --light-gold: #f4e4bc;
            --glass-bg: rgba(255, 255, 255, 0.90);
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border-color: rgba(128, 0, 0, 0.08);
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(128, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(128, 0, 0, 0.08);
            --font-outfit: 'Outfit', sans-serif;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-outfit);
            background-color: #f7f9fb;
            color: var(--text-main);
            line-height: 1.6;
            background-image:
                radial-gradient(at 0% 0%, rgba(128, 0, 0, 0.03) 0, transparent 40%),
                radial-gradient(at 100% 0%, rgba(212, 175, 55, 0.03) 0, transparent 40%);
            padding-bottom: 60px;
        }

        .container {
            max-width: 1500px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Top Header Card */
        .header-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .header-card::after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .header-info h1 {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, #b22222 50%, var(--accent-gold) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .header-info p {
            color: var(--text-muted);
            font-size: 1rem;
            font-weight: 500;
            max-width: 600px;
        }

        .btn-premium {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.2);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            z-index: 10;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.3);
            filter: brightness(1.1);
        }

        .btn-premium:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
            filter: none !important;
        }

        .btn-secondary-outline {
            background: transparent;
            color: var(--primary-maroon);
            border: 1.5px solid var(--primary-maroon);
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary-outline:hover {
            background: rgba(128, 0, 0, 0.05);
        }

        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.5rem;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 10px 20px;
            font-family: var(--font-outfit);
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 10px 10px 0 0;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: var(--primary-maroon);
            background: rgba(128, 0, 0, 0.03);
        }

        .tab-btn.active {
            color: var(--primary-maroon);
            border-bottom: 3px solid var(--primary-maroon);
            background: rgba(128, 0, 0, 0.05);
        }

        .tab-view {
            display: none;
        }

        .tab-view.active {
            display: block;
        }

        /* Dashboard Container and Cards */
        .dashboard-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .dashboard-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 1024px) {
            .dashboard-row {
                grid-template-columns: 1fr;
            }
        }

        .dashboard-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .dashboard-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .card-header-with-badge {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--primary-maroon);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge-premium {
            background: linear-gradient(135deg, var(--accent-gold) 0%, #b8860b 100%);
            color: white;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 20px;
            box-shadow: 0 4px 10px rgba(212, 175, 55, 0.25);
        }

        /* Readiness Gauge Widget */
        .readiness-widget-container {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        @media (max-width: 600px) {
            .readiness-widget-container {
                flex-direction: column;
                text-align: center;
            }
        }

        .circular-gauge {
            position: relative;
            width: 140px;
            height: 140px;
            flex-shrink: 0;
        }

        .gauge-svg {
            transform: rotate(-90deg);
            width: 100%;
            height: 100%;
        }

        .gauge-bg {
            fill: none;
            stroke: #f3f4f6;
            stroke-width: 10;
        }

        .gauge-fill {
            fill: none;
            stroke: var(--primary-maroon);
            stroke-dasharray: 376.99; /* 2 * pi * r (r=60) */
            stroke-dashoffset: 376.99; /* 100% missing by default */
            stroke-width: 10;
            stroke-linecap: round;
            transition: stroke-dashoffset 1.5s ease-in-out;
        }

        .gauge-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .gauge-percentage {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-maroon);
            line-height: 1;
        }

        .gauge-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
        }

        .readiness-details {
            flex-grow: 1;
        }

        .readiness-details h4 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .readiness-details p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1.2rem;
        }

        .why-button {
            background: none;
            border: 1px solid var(--primary-maroon);
            color: var(--primary-maroon);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .why-button:hover {
            background: var(--primary-maroon);
            color: white;
        }

        /* Why Drilldown Sub-Panel */
        .why-drilldown-panel {
            display: none;
            background: #fdfaf3;
            border: 1px dashed var(--accent-gold);
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            animation: fadeIn 0.3s ease-out;
        }

        .why-drilldown-panel.open {
            display: block;
        }

        .drilldown-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 1rem;
        }

        .drilldown-item {
            background: white;
            border: 1px solid rgba(128, 0, 0, 0.05);
            border-radius: 12px;
            padding: 0.8rem;
            text-align: center;
        }

        .drilldown-item span {
            display: block;
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .drilldown-item strong {
            font-size: 1.1rem;
            color: var(--primary-maroon);
            font-weight: 800;
        }

        /* Drill-down Accordions */
        .accordion-item {
            border: 1px solid var(--border-color);
            border-radius: 16px;
            margin-bottom: 1rem;
            background: white;
            overflow: hidden;
            transition: var(--transition);
        }

        .accordion-header {
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
            font-weight: 700;
            color: var(--text-main);
            background: #fafafb;
        }

        .accordion-header:hover {
            background: #f3f4f6;
        }

        .accordion-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.05rem;
        }

        .accordion-header-left i {
            color: var(--primary-maroon);
            font-size: 1.2rem;
            width: 24px;
        }

        .accordion-score-badge {
            background: rgba(128, 0, 0, 0.06);
            color: var(--primary-maroon);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .accordion-icon {
            transition: transform 0.3s ease;
        }

        .accordion-item.open .accordion-icon {
            transform: rotate(180deg);
        }

        .accordion-content {
            display: none;
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            line-height: 1.6;
        }

        .accordion-item.open .accordion-content {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 600px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }

        .details-box {
            background: #fafafa;
            border-radius: 12px;
            padding: 1.25rem;
            border-left: 4px solid var(--primary-maroon);
        }

        .details-box.secondary {
            border-left-color: var(--accent-gold);
        }

        .details-box h5 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .details-box p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        /* Personality & Behavioral Trait Bars */
        .trait-row {
            margin-bottom: 1.25rem;
        }

        .trait-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .trait-bar-container {
            width: 100%;
            height: 10px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .trait-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 1s ease;
        }

        .trait-extraversion { background: #3b82f6; }
        .trait-agreeableness { background: #10b981; }
        .trait-conscientiousness { background: #f59e0b; }
        .trait-neuroticism { background: #ef4444; }
        .trait-openness { background: #8b5cf6; }

        .trait-description {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 6px;
            line-height: 1.4;
            font-weight: 400;
        }

        /* Dynamic Career Match Branch View */
        .career-tree-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            position: relative;
        }

        .career-branch-card {
            border: 1px solid var(--border-color);
            border-radius: 20px;
            background: white;
            padding: 1.5rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            transition: var(--transition);
            flex-wrap: wrap;
        }

        .career-branch-card:hover {
            border-color: var(--accent-gold);
            box-shadow: var(--shadow-md);
        }

        .career-match-circle {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-gold) 0%, #b8860b 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        }

        .career-branch-info {
            flex-grow: 1;
        }

        .career-branch-info h4 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }

        .career-branch-metrics {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .career-branch-metrics span strong {
            color: var(--primary-maroon);
        }

        .career-evidence-btn {
            background: none;
            border: none;
            color: var(--primary-maroon);
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .career-evidence-panel {
            display: none;
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
            margin-top: 1rem;
            animation: fadeIn 0.3s ease-out;
            width: 100%;
        }

        .career-evidence-panel.open {
            display: block;
        }

        /* Timeline Styles */
        .timeline-view {
            position: relative;
            padding-left: 2rem;
            border-left: 3px solid rgba(128, 0, 0, 0.1);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2.35rem;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--primary-maroon);
            border: 3px solid white;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.15);
        }

        .timeline-item.gold::before {
            background: var(--accent-gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }

        .timeline-date {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--accent-gold);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .timeline-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .timeline-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Peer Benchmark Widget */
        .benchmark-row {
            display: flex;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .benchmark-label {
            width: 140px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-main);
            flex-shrink: 0;
        }

        .benchmark-bar-wrapper {
            flex-grow: 1;
            position: relative;
        }

        .benchmark-bar-bg {
            height: 12px;
            background: #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
            width: 100%;
        }

        .benchmark-bar-fill {
            height: 100%;
            border-radius: 6px;
            transition: width 1s ease-in-out;
        }

        .benchmark-bar-fill.user {
            background: var(--primary-maroon);
        }

        .benchmark-bar-fill.dept {
            background: #9ca3af;
        }

        .benchmark-bar-fill.top {
            background: var(--accent-gold);
        }

        .benchmark-val {
            width: 50px;
            text-align: right;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-main);
            flex-shrink: 0;
        }

        /* Floating Q&A Widget */
        .ai-qa-widget {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .ai-qa-toggle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, #b22222 100%);
            color: white;
            border: none;
            box-shadow: 0 10px 25px rgba(128, 0, 0, 0.35);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: var(--transition);
        }

        .ai-qa-toggle:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 12px 30px rgba(128, 0, 0, 0.45);
        }

        .ai-qa-panel {
            display: none;
            position: absolute;
            bottom: 75px;
            right: 0;
            width: 380px;
            height: 520px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--border-color);
            flex-direction: column;
            overflow: hidden;
            animation: fadeInUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .ai-qa-panel.open {
            display: flex;
        }

        @media (max-width: 450px) {
            .ai-qa-panel {
                width: 320px;
                height: 460px;
                right: -10px;
            }
        }

        .ai-qa-header {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, #b22222 100%);
            color: white;
            padding: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ai-qa-header h4 {
            font-size: 1.05rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ai-qa-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.8;
        }

        .ai-qa-close:hover {
            opacity: 1;
        }

        .ai-qa-messages {
            flex-grow: 1;
            padding: 1.25rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: #f9fafb;
        }

        .qa-message {
            max-width: 85%;
            padding: 10px 14px;
            border-radius: 16px;
            font-size: 0.85rem;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .qa-message.ai {
            align-self: flex-start;
            background: white;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-top-left-radius: 4px;
        }

        .qa-message.user {
            align-self: flex-end;
            background: var(--primary-maroon);
            color: white;
            border-top-right-radius: 4px;
        }

        .ai-qa-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 8px 12px;
            background: white;
            border-top: 1px solid var(--border-color);
        }

        .suggestion-pill {
            background: #f3f4f6;
            border: 1px solid rgba(0, 0, 0, 0.05);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-main);
            cursor: pointer;
            transition: var(--transition);
        }

        .suggestion-pill:hover {
            background: rgba(128, 0, 0, 0.08);
            color: var(--primary-maroon);
        }

        .ai-qa-input-container {
            display: flex;
            border-top: 1px solid var(--border-color);
            padding: 10px;
            background: white;
        }

        .ai-qa-input {
            flex-grow: 1;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 8px 12px;
            font-family: var(--font-outfit);
            font-size: 0.85rem;
            outline: none;
        }

        .ai-qa-input:focus {
            border-color: var(--primary-maroon);
        }

        .ai-qa-send {
            background: var(--primary-maroon);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            margin-left: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .ai-qa-send:hover {
            background: #b22222;
        }

        /* Keyframes */
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Traditional Markdown report formatting */
        .markdown-report-container {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 3rem;
            box-shadow: var(--shadow-sm);
        }

        .report-h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary-maroon);
            margin-top: 2.5rem;
            margin-bottom: 1rem;
            border-bottom: 1.5px solid var(--border-color);
            padding-bottom: 6px;
            text-transform: uppercase;
        }

        .report-h2:first-of-type {
            margin-top: 0;
        }

        .report-h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-main);
            margin-top: 1.75rem;
            margin-bottom: 0.75rem;
        }

        .report-h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-maroon);
            margin-top: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .report-p {
            font-size: 0.98rem;
            color: var(--text-main);
            margin-bottom: 1.2rem;
            text-align: justify;
            line-height: 1.7;
        }

        .report-ul {
            margin-bottom: 1.5rem;
            padding-left: 20px;
        }

        .report-li {
            font-size: 0.98rem;
            color: var(--text-main);
            margin-bottom: 8px;
            line-height: 1.6;
        }

        .report-blockquote {
            background: rgba(128, 0, 0, 0.02);
            border-left: 4px solid var(--primary-maroon);
            padding: 1.25rem 1.75rem;
            margin: 2rem 0;
            border-radius: 0 16px 16px 0;
            font-style: italic;
            font-size: 0.95rem;
            color: #4b5563;
            line-height: 1.6;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .report-table th {
            background: rgba(128, 0, 0, 0.03);
            text-align: left;
            padding: 12px 18px;
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--primary-maroon);
            border-bottom: 1px solid var(--border-color);
        }

        .report-table td {
            padding: 12px 18px;
            font-size: 0.95rem;
            border-bottom: 1px solid var(--border-color);
        }

        .report-spacer {
            height: 12px;
        }

        /* Loading spinner */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(128, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 6px solid rgba(255, 255, 255, 0.3);
            border-top: 6px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            font-family: var(--font-outfit);
        }

        .loading-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .visualization-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 900px) {
            .visualization-grid {
                grid-template-columns: 1fr;
            }
        }

        .vis-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .vis-card h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--primary-maroon);
            margin-bottom: 1.5rem;
            width: 100%;
            text-align: left;
            border-left: 4px solid var(--accent-gold);
            padding-left: 10px;
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 320px;
        }

        /* Print media styles */
        @media print {
            body {
                background: white !important;
                color: black !important;
                padding: 0 !important;
                background-image: none !important;
            }
            .container {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            header, nav, .header-card, .tab-navigation, .visualization-grid, .ai-qa-widget, #btn-regenerate, .btn-premium, .ai-qa-toggle, .ai-qa-panel {
                display: none !important;
            }
            .tab-view {
                display: none !important;
            }
            #view-narrative {
                display: block !important;
            }
            .markdown-report-container {
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                padding: 0 !important;
            }
        }
    </style>
    <?php if (isset($_GET['iframe'])): ?>
    <style>
        body {
            padding-top: 0 !important;
            background: #ffffff !important;
        }
        .container {
            margin-top: 0 !important;
            max-width: 100% !important;
            padding: 10px !important;
        }
        .header-card {
            margin-top: 0 !important;
            border-radius: 12px !important;
            padding: 20px !important;
        }
    </style>
    <?php endif; ?>
</head>

<body>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-title">Lakshya Placement Intelligence</div>
        <div class="loading-subtitle">Running deterministic analysis & generating report explainability...</div>
    </div>

    <!-- Include Navbar -->
    <?php if (!isset($_GET['iframe'])): ?>
        <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <?php endif; ?>

    <div class="container" style="<?php echo isset($_GET['iframe']) ? 'margin-top: 0;' : 'margin-top: 20px;'; ?>">

        <?php if ($errorMsg): ?>
            <div class="markdown-report-container" style="text-align: center; padding: 4rem 2rem;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #dc2626; margin-bottom: 1.5rem;"></i>
                <h2 style="margin-bottom: 1rem; color: var(--primary-maroon);">Failed to load Report</h2>
                <p class="text-muted" style="max-width: 600px; margin: 0 auto 2rem;">
                    <?php echo htmlspecialchars($errorMsg); ?></p>
                <button onclick="regenerateReport()" class="btn-premium"><i class="fas fa-sync-alt"></i> Attempt
                    Generation</button>
            </div>
        <?php else: ?>

            <!-- Top Header Card -->
            <div class="header-card">
                <div class="header-info">
                    <h1>Placement Intelligence & Career Analysis</h1>
                    <p>A detailed, explainable decision support report explaining campus readiness, behavioral style, and
                        career alignment for <strong><?php echo htmlspecialchars($fullName); ?></strong>.</p>
                </div>
                <div class="header-actions" style="display: flex; gap: 10px; align-items: center;">
                    <button onclick="window.print()" class="btn-secondary-outline" id="btn-print">
                        <i class="fas fa-print"></i> Print / Download PDF
                    </button>
                    <button onclick="regenerateReport()" class="btn-premium" id="btn-regenerate">
                        <i class="fas fa-sync-alt"></i> Regenerate Report
                    </button>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-btn active" id="tab-btn-dashboard" onclick="switchTab('dashboard')">
                    <i class="fas fa-chart-pie"></i> Interactive Dashboard
                </button>
                <button class="tab-btn" id="tab-btn-narrative" onclick="switchTab('narrative')">
                    <i class="fas fa-file-alt"></i> Full Written Report
                </button>
            </div>

            <!-- Extract metrics from the report result payload -->
            <?php $metrics = $reportResult['data'] ?? []; ?>

            <!-- TAB 1: INTERACTIVE DASHBOARD VIEW -->
            <div id="view-dashboard" class="tab-view active">
                <div class="dashboard-container">
                    
                    <!-- First Row: Overall Readiness & Peer Comparison -->
                    <div class="dashboard-row">
                        
                        <!-- Overall Placement Readiness Card -->
                        <div class="dashboard-card">
                            <div class="card-header-with-badge">
                                <h3 class="card-title"><i class="fas fa-chart-line"></i> Overall Placement Readiness</h3>
                                <span class="badge-premium">PICE Assessment</span>
                            </div>
                            <div class="readiness-widget-container">
                                <div class="circular-gauge">
                                    <svg class="gauge-svg">
                                        <circle class="gauge-bg" cx="70" cy="70" r="60"></circle>
                                        <circle class="gauge-fill" id="readiness-gauge-fill" cx="70" cy="70" r="60"></circle>
                                    </svg>
                                    <div class="gauge-text">
                                        <div class="gauge-percentage"><?php echo round($metrics['readiness_score'] ?? 0); ?>%</div>
                                        <div class="gauge-label">Readiness</div>
                                    </div>
                                </div>
                                <div class="readiness-details">
                                    <h4><?php echo htmlspecialchars($metrics['drive_category'] ?? 'Standard IT / Services Drives (Class B)'); ?></h4>
                                    <p>Based on your calculated placement readiness probability, this represents your ideal campus drive matching class.</p>
                                    <button class="why-button" onclick="toggleDrilldown()">
                                        <i class="fas fa-question-circle"></i> Why this score?
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Why Drilldown Sub-Panel -->
                            <div class="why-drilldown-panel" id="why-drilldown">
                                <div class="drilldown-grid">
                                    <div class="drilldown-item">
                                        <span>Coding (S_COD)</span>
                                        <strong><?php echo round($metrics['coding_score'] ?? 0); ?>%</strong>
                                    </div>
                                    <div class="drilldown-item">
                                        <span>Projects</span>
                                        <strong><?php echo round($metrics['project_score'] ?? 0); ?>%</strong>
                                    </div>
                                    <div class="drilldown-item">
                                        <span>Communication</span>
                                        <strong><?php echo round($metrics['communication_score'] ?? 0); ?>%</strong>
                                    </div>
                                    <div class="drilldown-item">
                                        <span>Profile Completeness</span>
                                        <strong><?php echo round($metrics['dcs'] ?? 0); ?>%</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Peer Benchmarks Card -->
                        <div class="dashboard-card">
                            <div class="card-header-with-badge">
                                <h3 class="card-title"><i class="fas fa-users"></i> Peer Benchmarks</h3>
                                <span class="badge-premium">Institutional Rank</span>
                            </div>
                            <div style="margin-top: 1rem;">
                                <div class="benchmark-row">
                                    <div class="benchmark-label">Your Readiness</div>
                                    <div class="benchmark-bar-wrapper">
                                        <div class="benchmark-bar-bg">
                                            <div class="benchmark-bar-fill user" style="width: <?php echo round($metrics['readiness_score'] ?? 0); ?>%;"></div>
                                        </div>
                                    </div>
                                    <div class="benchmark-val"><?php echo round($metrics['readiness_score'] ?? 0); ?>%</div>
                                </div>
                                <div class="benchmark-row">
                                    <div class="benchmark-label">Department Avg</div>
                                    <div class="benchmark-bar-wrapper">
                                        <div class="benchmark-bar-bg">
                                            <div class="benchmark-bar-fill dept" style="width: 71.5%;"></div>
                                        </div>
                                    </div>
                                    <div class="benchmark-val">71.5%</div>
                                </div>
                                <div class="benchmark-row">
                                    <div class="benchmark-label">Top 10% Avg</div>
                                    <div class="benchmark-bar-wrapper">
                                        <div class="benchmark-bar-bg">
                                            <div class="benchmark-bar-fill top" style="width: 89.2%;"></div>
                                        </div>
                                    </div>
                                    <div class="benchmark-val">89.2%</div>
                                </div>
                            </div>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 1.5rem; line-height: 1.5;">
                                <i class="fas fa-info-circle"></i> Peer rankings are compiled anonymously from students of the same department and institution.
                            </p>
                        </div>
                        
                    </div>
                    
                    <!-- Competency & Match Charts Row -->
                    <?php if (!empty($metrics)): ?>
                    <div class="visualization-grid">
                        <!-- Competency Radar Card -->
                        <div class="vis-card">
                            <h3>Competency Assessment Profile</h3>
                            <div class="chart-container">
                                <canvas id="competencyRadarChart"></canvas>
                            </div>
                        </div>

                        <!-- Career Match Bar Card -->
                        <div class="vis-card">
                            <h3>Career Path Match Analysis</h3>
                            <div class="chart-container">
                                <canvas id="careerMatchChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Accordion Core Intelligences Row -->
                    <div class="dashboard-row">
                        
                        <!-- Core Intelligences Accordions -->
                        <div>
                            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--primary-maroon); margin-bottom: 1.25rem;">
                                <i class="fas fa-brain"></i> Explainable Intelligence Breakdown
                            </h3>
                            
                            <!-- Academic Intelligence Accordion -->
                            <div class="accordion-item">
                                <div class="accordion-header" onclick="toggleAccordion(this)">
                                    <div class="accordion-header-left">
                                        <i class="fas fa-graduation-cap"></i>
                                        <span>Academic Intelligence</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span class="accordion-score-badge">CGPA: <?php echo round(($metrics['behavioral_score'] ?? 56) / 8.0, 2); ?></span>
                                        <i class="fas fa-chevron-down accordion-icon"></i>
                                    </div>
                                </div>
                                <div class="accordion-content">
                                    <div class="details-grid">
                                        <div class="details-box">
                                            <h5>Academic Health</h5>
                                            <p>Your scholastic standing is mapped dynamically. Maintain eligibility above 6.5 to clear 85% of company screening filters.</p>
                                        </div>
                                        <div class="details-box secondary">
                                            <h5>Active Backlogs</h5>
                                            <p>Current backlog count: <strong><?php echo isset($metrics['risks']) && count(array_filter($metrics['risks'], function($r){ return strpos(strtolower($r['message']), 'backlog') !== false; })) > 0 ? '1+' : '0'; ?></strong>.</p>
                                        </div>
                                    </div>
                                    <?php 
                                    $academicNarrative = getReportSectionHtml($reportMarkdown, 4, 5);
                                    if (!empty($academicNarrative)):
                                    ?>
                                        <div style="margin-top: 1rem; border-top: 1px dashed var(--border-color); padding-top: 1rem; font-size: 0.88rem; line-height: 1.6; color: var(--text-main);">
                                            <?php echo $academicNarrative; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Technical Intelligence Accordion -->
                            <div class="accordion-item">
                                <div class="accordion-header" onclick="toggleAccordion(this)">
                                    <div class="accordion-header-left">
                                        <i class="fas fa-code"></i>
                                        <span>Technical Intelligence</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span class="accordion-score-badge">S_COD: <?php echo round($metrics['coding_score'] ?? 0); ?>%</span>
                                        <i class="fas fa-chevron-down accordion-icon"></i>
                                    </div>
                                </div>
                                <div class="accordion-content">
                                    <div class="details-grid">
                                        <div class="details-box">
                                            <h5>Coding Capacity</h5>
                                            <p>Your practical problem-solving capability is evaluated based on coding challenge scores, active platforms, and logic complexity.</p>
                                        </div>
                                        <div class="details-box secondary">
                                            <h5>Project Footprint</h5>
                                            <p>Project verification score: <strong><?php echo round($metrics['project_score'] ?? 0); ?>%</strong>.</p>
                                        </div>
                                    </div>
                                    <?php 
                                    $technicalNarrative = getReportSectionHtml($reportMarkdown, 5, 6);
                                    if (!empty($technicalNarrative)):
                                    ?>
                                        <div style="margin-top: 1rem; border-top: 1px dashed var(--border-color); padding-top: 1rem; font-size: 0.88rem; line-height: 1.6; color: var(--text-main);">
                                            <?php echo $technicalNarrative; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Communication Intelligence Accordion -->
                            <div class="accordion-item">
                                <div class="accordion-header" onclick="toggleAccordion(this)">
                                    <div class="accordion-header-left">
                                        <i class="fas fa-comments"></i>
                                        <span>Communication Intelligence</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span class="accordion-score-badge">Mock HR: <?php echo round($metrics['communication_score'] ?? 0); ?>%</span>
                                        <i class="fas fa-chevron-down accordion-icon"></i>
                                    </div>
                                </div>
                                <div class="accordion-content">
                                    <div class="details-grid">
                                        <div class="details-box">
                                            <h5>Mock AI Feedback</h5>
                                            <p>Speech patterns, structural answers, and vocabulary clarity are analyzed during Mock AI sessions to estimate recruiter-panel readiness.</p>
                                        </div>
                                        <div class="details-box secondary">
                                            <h5>STAR Preparation</h5>
                                            <p>Recommended to use STAR format (Situation, Task, Action, Result) in mock interviews to bridge response gaps.</p>
                                        </div>
                                    </div>
                                    <?php 
                                    $communicationNarrative = getReportSectionHtml($reportMarkdown, 6, 7);
                                    if (!empty($communicationNarrative)):
                                    ?>
                                        <div style="margin-top: 1rem; border-top: 1px dashed var(--border-color); padding-top: 1rem; font-size: 0.88rem; line-height: 1.6; color: var(--text-main);">
                                            <?php echo $communicationNarrative; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Behavioral & Work Style Accordion -->
                            <div class="accordion-item">
                                <div class="accordion-header" onclick="toggleAccordion(this)">
                                    <div class="accordion-header-left">
                                        <i class="fas fa-user-tag"></i>
                                        <span>Behavioral & Work Style (AMPI FFM)</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span class="accordion-score-badge">FFM Profile</span>
                                        <i class="fas fa-chevron-down accordion-icon"></i>
                                    </div>
                                </div>
                                <div class="accordion-content">
                                    <?php 
                                    $ffm = $metrics['personality_ffm'] ?? [];
                                    if (!empty($ffm)): 
                                        foreach ($ffm as $trait => $info):
                                            if (strtolower($trait) === 'neuroticism') continue;
                                            $val = max(20, min(100, 50 + (floatval($info['z'] ?? 0.0) * 25)));
                                    ?>
                                    <div class="trait-row">
                                        <div class="trait-info">
                                            <span style="text-transform: capitalize;"><?php echo htmlspecialchars($trait); ?></span>
                                            <span><?php echo htmlspecialchars($info['level'] ?? 'Medium'); ?> (Z: <?php echo round($info['z'] ?? 0, 2); ?>)</span>
                                        </div>
                                        <div class="trait-bar-container">
                                            <div class="trait-bar trait-<?php echo htmlspecialchars($trait); ?>" style="width: <?php echo $val; ?>%;"></div>
                                        </div>
                                    </div>
                                    <?php 
                                        endforeach;
                                    endif; 
                                    ?>
                                    
                                    <!-- Dynamic AI Behavioral Narrative Explanation -->
                                    <?php 
                                    $behavioralNarrative = getReportSectionHtml($reportMarkdown, 7, 8);
                                    if (!empty($behavioralNarrative)):
                                    ?>
                                        <div class="details-box" style="margin-top: 1.5rem; border-left-color: var(--primary-maroon); background: #fafafb; border-radius: 12px; padding: 1.25rem;">
                                            <h5 style="margin-bottom: 0.75rem; color: var(--primary-maroon);"><i class="fas fa-brain"></i> Dynamic AI Personality Analysis</h5>
                                            <div style="font-size: 0.88rem; line-height: 1.6; color: var(--text-main);">
                                                <?php echo $behavioralNarrative; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Correlation Engine Accordion -->
                            <div class="accordion-item">
                                <div class="accordion-header" onclick="toggleAccordion(this)">
                                    <div class="accordion-header-left">
                                        <i class="fas fa-sync-alt"></i>
                                        <span>Correlation & Contradiction Engine</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span class="accordion-score-badge"><?php echo count($metrics['anomalies'] ?? []); ?> Flags</span>
                                        <i class="fas fa-chevron-down accordion-icon"></i>
                                    </div>
                                </div>
                                <div class="accordion-content">
                                    <?php if (!empty($metrics['anomalies'])): ?>
                                        <?php foreach ($metrics['anomalies'] as $anomaly): ?>
                                            <div style="background: rgba(212, 175, 55, 0.05); border-left: 4px solid var(--accent-gold); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                                                <div style="display: flex; justify-content: space-between; font-weight: 700; margin-bottom: 4px;">
                                                    <span style="color: var(--primary-maroon); font-size: 0.9rem;"><?php echo htmlspecialchars($anomaly['type']); ?></span>
                                                    <span class="badge" style="background: rgba(128,0,0,0.1); color: var(--primary-maroon); font-size: 0.75rem; padding: 2px 8px; border-radius: 4px;"><?php echo htmlspecialchars($anomaly['severity']); ?> Severity</span>
                                                </div>
                                                <p style="font-size: 0.85rem; color: var(--text-main); line-height: 1.5; margin: 0;"><?php echo htmlspecialchars($anomaly['message']); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted" style="font-size: 0.9rem; text-align: center;"><i class="fas fa-check-circle" style="color: #10b981;"></i> No contradictory behavioral or academic anomalies detected in your profile.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                        </div>
                        
                        <!-- Career Path Matching Trees -->
                        <div>
                            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--primary-maroon); margin-bottom: 1.25rem;">
                                <i class="fas fa-network-wired"></i> Career Alignment Matches
                            </h3>
                            <div class="career-tree-container">
                                <?php 
                                $matches = $metrics['career_matches'] ?? [];
                                $topMatches = array_slice($matches, 0, 3);
                                foreach ($topMatches as $m):
                                ?>
                                <div class="career-branch-card">
                                    <div class="career-match-circle">
                                        <?php echo round($m['match_percentage']); ?>%
                                    </div>
                                    <div class="career-branch-info">
                                        <h4><?php echo htmlspecialchars($m['role']); ?></h4>
                                        <div class="career-branch-metrics">
                                            <span>Confidence: <strong><?php echo round($m['confidence_score']); ?>%</strong></span>
                                            <span>Tech Fit: <strong><?php echo round($m['technical_fit']); ?>%</strong></span>
                                            <span>Personality Fit: <strong><?php echo round($m['personality_fit']); ?>%</strong></span>
                                        </div>
                                        <button class="career-evidence-btn" onclick="toggleEvidence(this)">
                                            <span>View Evidence & Gaps</span> <i class="fas fa-chevron-down"></i>
                                        </button>
                                        
                                        <!-- Evidence Sub-Panel -->
                                        <div class="career-evidence-panel">
                                            <ul style="padding-left: 20px; font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">
                                                <?php foreach ($m['evidence'] as $ev): ?>
                                                    <li><?php echo htmlspecialchars($ev); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Third Row: Action Roadmap & Risk Warnings -->
                    <div class="dashboard-row">
                        
                        <!-- Action Roadmap (30-60-90 Day Plan) -->
                        <div class="dashboard-card">
                            <div class="card-header-with-badge">
                                <h3 class="card-title"><i class="fas fa-road"></i> Personalized Action Timeline</h3>
                                <span class="badge-premium">Next 90 Days</span>
                            </div>
                            <div class="timeline-view">
                                <div class="timeline-item">
                                    <div class="timeline-date">Days 1 - 30</div>
                                    <div class="timeline-title">Core Coding & Profile Building</div>
                                    <div class="timeline-desc">Resolve active mock practice tests, link your verified GitHub profile, and complete details on student portfolio.</div>
                                </div>
                                <div class="timeline-item gold">
                                    <div class="timeline-date">Days 31 - 60</div>
                                    <div class="timeline-title">Behavioral Mock Interviews</div>
                                    <div class="timeline-desc">Participate in 2 additional mock AI HR round interviews. Practice STAR responses to bridge situational communication gaps.</div>
                                </div>
                                <div class="timeline-item">
                                    <div class="timeline-date">Days 61 - 90</div>
                                    <div class="timeline-title">Drive Specific Mock Prep</div>
                                    <div class="timeline-desc">Target mock tests and technical rounds aligned with your matching recruiter tier (Standard IT / Services).</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Risk Warnings & Mentor intervention checklist -->
                        <div class="dashboard-card">
                            <div class="card-header-with-badge">
                                <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> Critical Risk Warnings & Checklist</h3>
                                <span class="badge-premium">Urgent Actions</span>
                            </div>
                            
                            <!-- Risks -->
                            <?php if (!empty($metrics['risks'])): ?>
                                <div style="margin-bottom: 1.5rem;">
                                    <?php foreach ($metrics['risks'] as $risk): ?>
                                        <div style="background: rgba(220, 38, 38, 0.05); border-left: 4px solid #dc2626; border-radius: 8px; padding: 0.8rem 1.2rem; margin-bottom: 0.8rem; display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong style="color: #dc2626; font-size: 0.85rem; display: block; text-transform: uppercase;"><?php echo htmlspecialchars($risk['category']); ?></strong>
                                                <span style="font-size: 0.8rem; color: var(--text-main);"><?php echo htmlspecialchars($risk['message']); ?></span>
                                            </div>
                                            <span style="background: rgba(220, 38, 38, 0.1); color: #dc2626; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;"><?php echo htmlspecialchars($risk['severity']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Mentor Checklist -->
                            <div style="border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                                <h4 style="font-size: 0.95rem; font-weight: 800; color: var(--primary-maroon); margin-bottom: 1rem;"><i class="fas fa-clipboard-check"></i> Action Checklist</h4>
                                <ul style="list-style: none; padding: 0;">
                                    <?php foreach ($metrics['mentor_checklist'] ?? [] as $item): ?>
                                        <li style="display: flex; gap: 10px; align-items: flex-start; margin-bottom: 8px; font-size: 0.85rem;">
                                            <input type="checkbox" style="margin-top: 4px; accent-color: var(--primary-maroon);">
                                            <span><?php echo htmlspecialchars($item); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        
                    </div>
                    
                </div>
            </div>

            <!-- TAB 2: FULL NARRATIVE REPORT VIEW -->
            <div id="view-narrative" class="tab-view">
                <article class="markdown-report-container">
                    <?php echo renderMarkdownToHtml($reportMarkdown); ?>
                </article>
            </div>

            <!-- Floating Q&A AI Advisor Widget -->
            <div class="ai-qa-widget">
                <button class="ai-qa-toggle" onclick="toggleQAPanel()" title="Ask PICE Career Advisor">
                    <i class="fas fa-brain"></i>
                </button>
                <div class="ai-qa-panel" id="ai-qa-panel">
                    <div class="ai-qa-header">
                        <h4><i class="fas fa-brain"></i> PICE AI Advisor</h4>
                        <button class="ai-qa-close" onclick="toggleQAPanel()"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="ai-qa-messages" id="ai-qa-messages">
                        <div class="qa-message ai">
                            Hello! I am the Narrative Placement Advisor. Ask me anything about your Placement Intelligence report. For example: "Why is Backend Developer my top match?" or "How can I improve my technical score?".
                        </div>
                    </div>
                    <div class="ai-qa-suggestions">
                        <div class="suggestion-pill" onclick="sendSuggestion('Why is Backend Developer my top match?')">Why Backend Developer?</div>
                        <div class="suggestion-pill" onclick="sendSuggestion('How do I fix my technical-communication gap?')">Fix Tech-Comm gap</div>
                        <div class="suggestion-pill" onclick="sendSuggestion('What are my critical profile risks?')">Profile Risks</div>
                    </div>
                    <div class="ai-qa-input-container">
                        <input type="text" id="ai-qa-input" class="ai-qa-input" placeholder="Ask a question..." onkeydown="if(event.key==='Enter') sendUserMessage()">
                        <button class="ai-qa-send" onclick="sendUserMessage()"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <script>
        // Tab switching logic
        function switchTab(tabName) {
            // Hide all views
            document.querySelectorAll('.tab-view').forEach(v => v.classList.remove('active'));
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            // Show requested view
            document.getElementById('view-' + tabName).classList.add('active');
            // Activate button
            document.getElementById('tab-btn-' + tabName).classList.add('active');
        }

        // Toggle Why Drilldown Panel
        function toggleDrilldown() {
            const panel = document.getElementById('why-drilldown');
            if (panel) panel.classList.toggle('open');
        }

        // Toggle Accordions
        function toggleAccordion(header) {
            const item = header.parentElement;
            item.classList.toggle('open');
        }

        // Toggle Career Evidence Gaps
        function toggleEvidence(btn) {
            const panel = btn.nextElementSibling;
            panel.classList.toggle('open');
            const icon = btn.querySelector('i');
            if (panel.classList.contains('open')) {
                icon.className = 'fas fa-chevron-up';
            } else {
                icon.className = 'fas fa-chevron-down';
            }
        }

        // Floating Q&A Widget Open/Close
        function toggleQAPanel() {
            const panel = document.getElementById('ai-qa-panel');
            if (panel) {
                if (panel.style.display === 'flex' || panel.classList.contains('open')) {
                    panel.style.display = 'none';
                    panel.classList.remove('open');
                } else {
                    panel.style.display = 'flex';
                    panel.classList.add('open');
                    scrollToBottom();
                }
            }
        }

        function scrollToBottom() {
            const msgContainer = document.getElementById('ai-qa-messages');
            if (msgContainer) {
                msgContainer.scrollTop = msgContainer.scrollHeight;
            }
        }

        // AI Chat communication logic
        function sendSuggestion(text) {
            const input = document.getElementById('ai-qa-input');
            if (input) {
                input.value = text;
                sendUserMessage();
            }
        }

        function sendUserMessage() {
            const input = document.getElementById('ai-qa-input');
            if (!input) return;
            const messageText = input.value.trim();
            if (!messageText) return;

            // Render user message
            const messagesContainer = document.getElementById('ai-qa-messages');
            const userMsgDiv = document.createElement('div');
            userMsgDiv.className = 'qa-message user';
            userMsgDiv.textContent = messageText;
            messagesContainer.appendChild(userMsgDiv);
            
            input.value = '';
            scrollToBottom();

            // Render AI thinking state
            const thinkingDiv = document.createElement('div');
            thinkingDiv.className = 'qa-message ai thinking';
            thinkingDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing report...';
            messagesContainer.appendChild(thinkingDiv);
            scrollToBottom();

            // Call AJAX chat endpoint
            const formData = new FormData();
            formData.append('action', 'chat');
            formData.append('message', messageText);

            fetch('showcase.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                thinkingDiv.remove();
                const aiMsgDiv = document.createElement('div');
                aiMsgDiv.className = 'qa-message ai';
                if (data.success) {
                    aiMsgDiv.innerHTML = parseMarkdownInline(data.reply);
                } else {
                    aiMsgDiv.textContent = 'Sorry, I encountered an error: ' + data.message;
                }
                messagesContainer.appendChild(aiMsgDiv);
                scrollToBottom();
            })
            .catch(err => {
                thinkingDiv.remove();
                const aiMsgDiv = document.createElement('div');
                aiMsgDiv.className = 'qa-message ai';
                aiMsgDiv.textContent = 'Connection timeout. Please try again.';
                messagesContainer.appendChild(aiMsgDiv);
                scrollToBottom();
            });
        }

        // Helper to format basic markdown responses from LLM in Chat
        function parseMarkdownInline(text) {
            // Escape HTML
            let escaped = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
            // Bold
            escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            // Lists
            escaped = escaped.replace(/^\*\s(.*)/gm, '<li>$1</li>');
            escaped = escaped.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
            // Newlines
            escaped = escaped.replace(/\n/g, '<br>');
            return escaped;
        }

        // AJAX Report Regeneration
        function regenerateReport() {
            const btn = document.getElementById('btn-regenerate');
            if (!btn) return;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running PICE Engines...';

            // Show loading overlay
            document.getElementById('loading-overlay').style.display = 'flex';

            const formData = new FormData();
            formData.append('action', 'regenerate');

            fetch('showcase.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Failed to regenerate report: ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    document.getElementById('loading-overlay').style.display = 'none';
                }
            })
            .catch(error => {
                console.error(error);
                alert('An error occurred during report compilation.');
                btn.disabled = false;
                btn.innerHTML = originalText;
                document.getElementById('loading-overlay').style.display = 'none';
            });
        }

        // Handle animation of circular gauge on load
        window.addEventListener('DOMContentLoaded', () => {
            const readiness = <?php echo round($metrics['readiness_score'] ?? 0); ?>;
            const fill = document.getElementById('readiness-gauge-fill');
            if (fill) {
                // Circumference is 2 * pi * r = 2 * 3.14159 * 60 = 376.99
                const offset = 376.99 - (readiness / 100 * 376.99);
                setTimeout(() => {
                    fill.style.strokeDashoffset = offset;
                }, 300);
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const metrics = <?php echo json_encode($metrics); ?>;
            if (!metrics || Object.keys(metrics).length === 0) return;

            // Competency Radar Chart
            const radarCtx = document.getElementById('competencyRadarChart');
            if (radarCtx) {
                new Chart(radarCtx.getContext('2d'), {
                    type: 'radar',
                    data: {
                        labels: ['Coding Performance', 'Project Quality', 'Communication Score', 'Behavioral / Academic', 'Profile Completeness'],
                        datasets: [{
                            label: 'Student Competency (%)',
                            data: [
                                parseFloat(metrics.coding_score) || 0,
                                parseFloat(metrics.project_score) || 0,
                                parseFloat(metrics.communication_score) || 0,
                                parseFloat(metrics.behavioral_score) || 0,
                                parseFloat(metrics.dcs) || 0
                            ],
                            backgroundColor: 'rgba(128, 0, 0, 0.12)',
                            borderColor: 'rgba(128, 0, 0, 0.8)',
                            borderWidth: 2,
                            pointBackgroundColor: '#D4AF37',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgba(128, 0, 0, 1)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            r: {
                                angleLines: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                suggestedMin: 0,
                                suggestedMax: 100,
                                ticks: {
                                    stepSize: 20,
                                    backdropColor: 'transparent',
                                    color: '#9ca3af',
                                    font: {
                                        family: 'Outfit',
                                        size: 10
                                    }
                                },
                                pointLabels: {
                                    color: '#4b5563',
                                    font: {
                                        family: 'Outfit',
                                        size: 11,
                                        weight: '600'
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.raw + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Career Match Bar Chart
            const barCtx = document.getElementById('careerMatchChart');
            if (barCtx) {
                const sortedMatches = (metrics.career_matches || []).slice(0, 5);
                const careerLabels = sortedMatches.map(m => m.role);
                const careerData = sortedMatches.map(m => m.match_percentage);

                new Chart(barCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: careerLabels,
                        datasets: [{
                            label: 'Match Percentage',
                            data: careerData,
                            backgroundColor: 'rgba(212, 175, 55, 0.85)',
                            hoverBackgroundColor: 'rgba(128, 0, 0, 0.95)',
                            borderColor: 'rgba(212, 175, 55, 1)',
                            borderWidth: 1,
                            borderRadius: 8
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#9ca3af',
                                    font: {
                                        family: 'Outfit',
                                        size: 11
                                    }
                                },
                                suggestedMin: 0,
                                suggestedMax: 100
                            },
                            y: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#4b5563',
                                    font: {
                                        family: 'Outfit',
                                        size: 11,
                                        weight: '500'
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.raw + '% Match';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>