<?php
ob_start();
require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$studentId = getUsername();
$institution = getInstitution();

require_once __DIR__ . '/../../src/Services/PlacementIntelligenceService.php';
$piceService = new \App\Services\PlacementIntelligenceService();

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
        }

        /* Markdown report formatting */
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
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

        /* Visualization Grid Styles */
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
    </style>
</head>

<body>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-title">Lakshya Placement Intelligence</div>
        <div class="loading-subtitle">Running deterministic analysis & generating report explainability...</div>
    </div>

    <!-- Include Navbar -->
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container" style="margin-top: 20px;">

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
                        career alignment.</p>
                </div>
                <div class="header-actions">
                    <button onclick="regenerateReport()" class="btn-premium" id="btn-regenerate">
                        <i class="fas fa-sync-alt"></i> Regenerate Report
                    </button>
                </div>
            </div>

            <!-- Interactive Visualizations Grid -->
            <?php
            // Extract metrics from the report result payload
            $metrics = $reportResult['data'] ?? [];
            if (!empty($metrics)):
            ?>
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

            <!-- Detailed Report Container -->
            <article class="markdown-report-container">
                <?php echo renderMarkdownToHtml($reportMarkdown); ?>
            </article>

        <?php endif; ?>

    </div>

    <script>
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
                        labels: ['Coding Performance', 'Project Quality', 'GitHub/Git Quality', 'Communication Score', 'Behavioral / Academic', 'Profile Completeness'],
                        datasets: [{
                            label: 'Student Competency (%)',
                            data: [
                                parseFloat(metrics.coding_score) || 0,
                                parseFloat(metrics.project_score) || 0,
                                parseFloat(metrics.git_score) || 0,
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