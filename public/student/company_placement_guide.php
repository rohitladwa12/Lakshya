<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/AIService.php';

use App\Helpers\SessionFilterHelper;

requireRole(ROLE_STUDENT);

// Handle POST from Dashboard
if (isPost() && isset($_POST['company'])) {
    SessionFilterHelper::setFilters('company_placement_guide', [
        'company' => $_POST['company'] ?? ''
    ]);
    header("Location: company_placement_guide.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('company_placement_guide');
$companyName = $filters['company'] ?? '';

if (empty($companyName)) {
    die("Company name is required.");
}

$aiService = new AIService();

// Call fresh to ensure accuracy/no stale data as requested.
$result = $aiService->getCompanyPlacementGuide($companyName);
$guideContent = $result['success'] ? $result['content'] : "Error generating guide: " . ($result['message'] ?? 'Unknown error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placement Strategy: <?php echo htmlspecialchars($companyName); ?> | Lakshya</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.3.0/marked.min.js"></script>
    <style>
        :root {
            --primary: #800000; /* Deep Maroon */
            --primary-light: #fff5f5;
            --accent: #b8860b; /* Goldenrod */
            --accent-soft: #fdfae6;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --bg-body: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-body);
            color: var(--text-dark);
            margin: 0;
            padding: 0;
            line-height: 1.7;
            background-image: 
                radial-gradient(at 0% 0%, rgba(128, 0, 0, 0.03) 0, transparent 50%), 
                radial-gradient(at 100% 100%, rgba(184, 134, 11, 0.03) 0, transparent 50%);
        }

        .guide-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            padding: 15px 50px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .brand h1 { font-size: 1.4rem; font-weight: 800; color: var(--primary); margin: 0; }
        .brand span { color: var(--text-muted); text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1.5px; font-weight: 700; }

        .btn-action {
            padding: 10px 22px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }

        .btn-print { background: var(--primary); color: white; border: none; }
        .btn-back { background: white; color: var(--text-dark); border: 1.5px solid var(--border-color); }
        .btn-print:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(128, 0, 0, 0.2); background: #600000; }
        .btn-back:hover { background: #f1f5f9; border-color: #cbd5e1; }

        .main-wrapper {
            max-width: 1200px;
            margin: 40px auto;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 40px;
            padding: 0 20px;
        }

        /* Sidebar Navigation */
        .guide-nav {
            position: sticky;
            top: 100px;
            height: fit-content;
            background: white;
            padding: 25px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .nav-title { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px; color: var(--text-muted); font-weight: 800; margin-bottom: 20px; }
        .nav-list { list-style: none; padding: 0; }
        .nav-item { margin-bottom: 12px; }
        .nav-link { 
            color: var(--text-dark); 
            text-decoration: none; 
            font-size: 0.95rem; 
            font-weight: 500; 
            display: flex; 
            align-items: center; 
            gap: 12px;
            padding: 10px 15px;
            border-radius: 12px;
            transition: 0.2s;
        }
        .nav-link:hover { background: var(--primary-light); color: var(--primary); }
        .nav-link.active { background: var(--primary); color: white; }

        /* Guide Content Area */
        .guide-container {
            background: var(--card-bg);
            padding: 60px;
            border-radius: 30px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            animation: fadeIn 0.8s ease-out;
            min-height: 800px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #guideOutput {
            font-size: 1.1rem;
            color: #334155;
        }

        #guideOutput h1 { 
            font-size: 2.5rem; 
            font-weight: 800; 
            color: var(--primary); 
            margin-bottom: 2rem; 
            line-height: 1.2;
            letter-spacing: -1px;
        }

        #guideOutput h2 { 
            font-size: 1.6rem; 
            color: var(--primary); 
            margin-top: 4rem; 
            margin-bottom: 1.5rem; 
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-light);
            font-weight: 700;
        }

        #guideOutput h3 { color: var(--accent); margin-top: 2.5rem; font-size: 1.3rem; font-weight: 700; }

        #guideOutput ul, #guideOutput ol {
            margin: 1.5rem 0;
            padding-left: 1.5rem;
        }

        #guideOutput li { margin-bottom: 12px; position: relative; }
        
        #guideOutput blockquote {
            background: var(--accent-soft);
            border-left: 6px solid var(--accent);
            padding: 30px;
            margin: 40px 0;
            border-radius: 0 20px 20px 0;
            font-style: italic;
            color: #92400e;
            font-weight: 500;
        }

        #guideOutput strong { color: var(--text-dark); font-weight: 800; }

        #guideOutput p { margin-bottom: 1.5rem; }

        /* Roadmap Table Stylings */
        #guideOutput table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
        }
        #guideOutput th { background: #f8fafc; color: var(--primary); padding: 18px; text-align: left; font-weight: 700; border-bottom: 2px solid var(--border-color); }
        #guideOutput td { padding: 18px; border-bottom: 1px solid var(--border-color); }

        .disclaimer {
            margin-top: 50px;
            padding: 30px;
            background: #fdfdfd;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            font-size: 0.9rem;
            color: var(--text-muted);
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        @media screen and (max-width: 1000px) {
            .main-wrapper { grid-template-columns: 1fr; }
            .guide-nav { display: none; }
            .guide-header { padding: 15px 25px; }
            .guide-container { padding: 40px; }
        }

        @media print {
            .btn-action, .guide-header, .guide-nav, .disclaimer { display: none; }
            body { background: white; }
            .main-wrapper { margin: 0; padding: 0; width: 100%; display: block; }
            .guide-container { box-shadow: none; border: none; padding: 0; width: 100%; }
            #guideOutput h1 { color: black; border-bottom: 2px solid black; }
            #guideOutput h2 { color: black; border-bottom: 1px solid #eee; }
        }
    </style>
</head>
<body>

<header class="guide-header">
    <div class="brand">
        <h1>Placement Strategy <sup style="font-size: 0.6rem; background: var(--primary); color: white; padding: 2px 6px; border-radius: 4px; vertical-align: top;">PREMIUM</sup></h1>
        <span>Expert Roadmap for <?php echo htmlspecialchars($companyName); ?></span>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="dashboard.php" class="btn-action btn-back">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
        <button onclick="window.print()" class="btn-action btn-print">
            <i class="fas fa-file-pdf"></i> Download PDF Guide
        </button>
    </div>
</header>

<div class="main-wrapper">
    <aside class="guide-nav">
        <div class="nav-title">Strategy Index</div>
        <ul class="nav-list">
            <li class="nav-item"><a href="#process" class="nav-link"><i class="fas fa-clipboard-list"></i> Recruitment Process</a></li>
            <li class="nav-item"><a href="#skills" class="nav-link"><i class="fas fa-laptop-code"></i> Core Competencies</a></li>
            <li class="nav-item"><a href="#interview" class="nav-link"><i class="fas fa-user-tie"></i> Interview Topics</a></li>
            <li class="nav-item"><a href="#roadmap" class="nav-link"><i class="fas fa-clock"></i> 4-Week Prep Plan</a></li>
            <li class="nav-item"><a href="#culture" class="nav-link"><i class="fas fa-building"></i> Culture & Ethics</a></li>
        </ul>
        
        <div style="margin-top: 40px; padding: 25px; background: linear-gradient(135deg, var(--primary) 0%, #600000 100%); border-radius: 20px; text-align: center; color: white;">
            <p style="font-size: 0.85rem; font-weight: 700; margin-bottom: 10px;">Preparation Tip</p>
            <p style="font-size: 0.75rem; opacity: 0.9; line-height: 1.5;">Verify this strategy against your latest college placement notification for specific dates.</p>
        </div>
    </aside>

    <div class="guide-container">
        <div id="guideOutput">
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 120px 0;">
                <i class="fas fa-sync fa-spin" style="font-size: 3rem; color: var(--primary); margin-bottom: 25px;"></i>
                <h3 style="color: var(--primary); margin: 0;">Generating High-Yield Strategy...</h3>
                <p style="color: var(--text-muted); margin-top: 10px;">Analyzing historical data for <?php echo htmlspecialchars($companyName); ?></p>
            </div>
        </div>

        <div class="disclaimer">
            <i class="fas fa-info-circle" style="font-size: 1.5rem; color: var(--primary);"></i> 
            <div>
                <strong>AI Methodology:</strong> This roadmap is generated using Laksha AI's deep-learning engine, which cross-references thousands of verified placement reports. It focuses on high-impact areas that maximize your selection probability.
            </div>
        </div>
    </div>
</div>

<script>
    const rawMarkdown = <?php echo json_encode($guideContent); ?>;
    document.getElementById('guideOutput').innerHTML = marked.parse(rawMarkdown);
    
    // Simple scroll to section logic
    document.querySelectorAll('.nav-link').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            // Since AI generates content, we look for h2 containing the text
            const headings = document.querySelectorAll('#guideOutput h2');
            for(let h of headings) {
                if(h.innerText.toLowerCase().includes(targetId.replace('roadmap', 'preparation').replace('process', 'recruitment'))) {
                    h.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    break;
                }
            }
        });
    });
</script>

</body>
</html>
