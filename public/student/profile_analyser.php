<?php
/**
 * AI Profile Analyser - Multi-Mode Standalone Page
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();

// Check if student has any portfolio items (Skills or Projects)
require_once __DIR__ . '/../../src/Models/Portfolio.php';
$portfolioModel = new Portfolio();
$institution = $_SESSION['institution'] ?? 'GMU'; // Fallback to GMU if not set
$portfolioItems = $portfolioModel->getStudentPortfolio(getUsername(), $institution);

$hasSkillsOrProjects = false;
foreach ($portfolioItems as $item) {
    if ($item['category'] === 'Skill' || $item['category'] === 'Project') {
        $hasSkillsOrProjects = true;
        break;
    }
}

if (!$hasSkillsOrProjects) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Action Required - Profile Analyser</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .empty-state-card {
            background: white;
            padding: 3rem;
            border-radius: 24px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid #eee;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background: #fff5f5;
            color: #e74c3c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
        }
        h2 { color: #2d3436; margin-bottom: 10px; font-weight: 700; }
        p { color: #636e72; line-height: 1.6; margin-bottom: 2rem; }
        .btn-action {
            background: #800000;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(128,0,0,0.2); }
    </style>
</head>
<body>
    <div class="empty-state-card">
        <div class="icon-circle"><i class="fas fa-layer-group"></i></div>
        <h2>Build Your Portfolio First</h2>
        <p>The AI Career Architect needs data to work with. Please add at least one <strong>Skill</strong> or <strong>Project</strong> to your profile.</p>
        <a href="dashboard.php" class="btn-action">Go to Dashboard</a>
    </div>
</body>
</html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Career Architect - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #4a0000;
            --accent-gold: #D4AF37;
            --light-gold: #f4e4bc;
            --white: #ffffff;
            --bg-light: #f8f9fa;
            --text-main: #2d3436;
            --text-muted: #636e72;
            --shadow-md: 0 10px 15px rgba(0,0,0,0.08);
            --gradient-maroon: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-main);
            min-height: 100vh;
        }

        .header {
            background: var(--gradient-maroon);
            color: white;
            padding: 3.5rem 2rem;
            text-align: center;
            border-bottom: 5px solid var(--accent-gold);
        }

        .container {
            max-width: 1240px;
            margin: -3rem auto 3rem;
            padding: 0 2rem;
        }

        /* Tabs Styling */
        .tab-bar {
            display: flex;
            gap: 10px;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            padding: 8px;
            border-radius: 50px;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .tab-btn {
            padding: 12px 25px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-family: 'Outfit';
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            border-radius: 50px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tab-btn.active {
            background: var(--primary-maroon);
            color: white;
            box-shadow: 0 4px 12px rgba(128,0,0,0.2);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 2rem;
            display: none; /* Hidden by default */
        }

        .glass-card.active { display: block; animation: fadeIn 0.5s ease; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .stats-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--primary-maroon);
        }

        .loading-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.9);
            z-index: 2000;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-maroon);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 1.5rem;
        }

        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Target Mode Inputs */
        .input-group {
            display: flex;
            gap: 15px;
            margin-bottom: 2rem;
            background: #fdfdfd;
            padding: 1.5rem;
            border-radius: 20px;
            border: 1px dashed var(--accent-gold);
        }

        .input-field {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-family: 'Outfit';
            font-size: 1rem;
        }

        .btn-prime {
            background: var(--primary-maroon);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-prime:hover { background: var(--primary-dark); transform: scale(1.02); }

        .benchmark-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid #efefef;
            margin-bottom: 1rem;
        }

        .match-badge {
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 1rem;
            transition: color 0.3s;
        }
        .nav-link:hover { color: white; }

        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: 1fr; }
            .input-group { flex-direction: column; }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
        <h2 id="loadingText" style="color: var(--primary-maroon)">AI is Analysing Your Profile</h2>
        <p style="color: var(--text-muted); margin-top: 10px;">This may take 10-15 seconds...</p>
    </div>

    <header class="header">
        <div style="max-width: 1200px; margin: 0 auto; text-align: left;">
            <h1 style="font-size: 2.5rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 0.5rem;">Elite Career Architect</h1>
            <p style="opacity: 0.9; font-size: 1.1rem; font-weight: 400;">Strict AI evaluation based on Global Tech Standards & Professional Benchmarks</p>
        </div>
    </header>

    <main class="container">
        <!-- Tab Navigation -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('market')"><i class="fas fa-globe"></i> Market Trends</button>
            <button class="tab-btn" onclick="switchTab('target')"><i class="fas fa-bullseye"></i> Targeted Fit</button>
            <button class="tab-btn" onclick="switchTab('career')"><i class="fas fa-magic"></i> Career Predictor</button>
        </div>

        <!-- 1. MARKET TRENDS VIEW -->
        <div id="marketView" class="glass-card active">
            <div style="background: #fff; padding: 1.5rem; border-radius: 20px; border: 1px dashed var(--accent-gold); margin-bottom: 2rem; display: flex; align-items: center; gap: 15px;">
                <div style="flex: 1;">
                    <h4 style="font-size: 0.9rem; color: var(--primary-maroon); margin-bottom: 5px;"><i class="fas fa-building"></i> Benchmark Against Company</h4>
                    <input type="text" id="marketCompany" class="input-field" placeholder="Enter Company (e.g. Google, TCS, Tesla)" style="width: 100%; border-style: solid;">
                </div>
                <button onclick="applyMarketContext()" class="btn-prime" style="height: 45px; margin-top: 20px;">Apply Context</button>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Chart 1: Radar -->
                <div style="background: white; padding: 1.5rem; border-radius: 20px; border: 1px solid #f0f0f0;">
                    <h3 class="section-title"><i class="fas fa-chart-radar"></i> Skill Alignment Radar</h3>
                    <div style="height: 300px;"><canvas id="radarChart"></canvas></div>
                </div>
                <!-- Table Comparison -->
                <div style="background: white; padding: 1.5rem; border-radius: 20px; border: 1px solid #f0f0f0; display: flex; flex-direction: column;">
                    <h3 class="section-title"><i class="fas fa-list-ul"></i> Skill Comparison Table</h3>
                    <div style="flex: 1; overflow-y: auto; scrollbar-width: none;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="text-align: left; background: #fafafa; border-bottom: 2px solid #eee;">
                                    <th style="padding: 12px; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Metric / Skill</th>
                                    <th style="padding: 12px; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Your Score</th>
                                    <th style="padding: 12px; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Benchmark</th>
                                </tr>
                            </thead>
                            <tbody id="comparisonTableBody">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div style="background: white; padding: 1.5rem; border-radius: 20px; border: 1px solid #f0f0f0;">
                    <h3 class="section-title"><i class="fas fa-chart-line"></i> Industry Standard Delta</h3>
                    <div style="height: 300px;"><canvas id="lineChart"></canvas></div>
                </div>
                <!-- Chart 4: Benchmarks -->
                <div style="background: white; padding: 1.5rem; border-radius: 20px; border: 1px solid #f0f0f0;">
                    <h3 class="section-title"><i class="fas fa-building"></i> Industry Tiers</h3>
                    <div id="benchmarkList" style="max-height: 300px; overflow-y: auto;"></div>
                </div>

                <!-- Role Compatibility Chart -->
                <div style="background: white; padding: 1.5rem; border-radius: 20px; border: 1px solid #f0f0f0;">
                    <h3 class="section-title"><i class="fas fa-bullseye"></i> Role Compatibility Analysis</h3>
                    <div style="height: 300px;"><canvas id="roleCompatibilityChart"></canvas></div>
                </div>
                <!-- Strategic Action Roadmap -->
                <div style="background: white; padding: 1.5rem; border-radius: 20px; border: 1px solid #f0f0f0; display: flex; flex-direction: column;">
                    <h3 class="section-title"><i class="fas fa-map-signs"></i> Strategic Action Roadmap</h3>
                    <div id="actionRoadmap" style="flex: 1; overflow-y: auto; scrollbar-width: none;">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

            <div style="margin-top: 2rem; padding: 2rem; background: #fffdf5; border-radius: 20px; border-left: 8px solid var(--accent-gold);">
                <h3 id="marketSummaryTitle" style="margin-bottom: 10px;">Executive Market Positioning</h3>
                <p id="marketSummaryContent" style="color: var(--text-muted); line-height: 1.8;"></p>
                <div style="margin-top: 15px; font-size: 0.8rem; color: #e67e22; font-weight: 600; font-style: italic; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-balance-scale"></i> Strict Evaluation: Scores are calibrated against elite global benchmarks. Tutorial projects or lack of GitHub evidence will impact scoring.
                </div>
            </div>
        </div>

        <!-- 2. TARGETED FIT VIEW -->
        <div id="targetView" class="glass-card">
            <h3 class="section-title"><i class="fas fa-search-location"></i> Analyse Target Role & Company</h3>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Pick any company in the world (Google, Tesla, Zomato) and a role to see how you fit.</p>
            
            <div class="input-group">
                <input type="text" id="targetCompany" class="input-field" placeholder="Target Company (e.g. NVIDIA)">
                <input type="text" id="targetRole" class="input-field" placeholder="Target Role (e.g. SDE-1)">
                <button onclick="runTargetAnalysis()" class="btn-prime">Run AI Simulation</button>
            </div>

            <div id="targetResults" style="display: none;">
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; margin-bottom: 2rem;">
                    <div style="background: white; padding: 2rem; border-radius: 24px; border: 1px solid #eee; text-align: center;">
                        <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-muted);">SIMULATED MATCH</div>
                        <div id="targetScore" style="font-size: 5rem; font-weight: 800; color: var(--primary-maroon);">--%</div>
                        <div id="targetVerdict" style="padding: 8px 15px; border-radius: 50px; font-weight: 700; display: inline-block; margin-top: 10px;"></div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: 24px; border: 1px solid #eee;">
                        <h4 style="margin-bottom: 15px;"><i class="fas fa-chart-bar"></i> Requirement vs Possession</h4>
                        <div style="height: 180px;"><canvas id="targetBarChart"></canvas></div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 2rem;">
                    <div class="benchmark-card" style="margin: 0;">
                        <h4 style="margin-bottom: 10px;">Technical & Cultural Alignment</h4>
                        <p id="targetAlign" style="font-size: 0.95rem; color: #555; line-height: 1.6;"></p>
                    </div>
                    <div style="background: #fff0f0; padding: 1.5rem; border-radius: 20px; border: 1px solid #ffcccc;">
                        <h4 style="color: #c0392b; margin-bottom: 10px;"><i class="fas fa-exclamation-triangle"></i> Mission-Critical Gaps</h4>
                        <div id="targetGaps" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
                    </div>
                </div>
                <div style="margin-top: 2rem; background: var(--gradient-maroon); color: white; padding: 2rem; border-radius: 24px;">
                    <h3><i class="fas fa-lightbulb"></i> Custom Recruitment Advice</h3>
                    <p id="targetAdvice" style="opacity: 0.9; margin-top: 10px; line-height: 1.6;"></p>
                    <div style="margin-top: 1.5rem;">
                        <h4 style="font-size: 0.9rem; margin-bottom: 10px;">Focus Topics for Interview:</h4>
                        <div id="targetPrep" style="display: flex; flex-wrap: wrap; gap: 10px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. CAREER PREDICTOR VIEW -->
        <div id="careerView" class="glass-card">
            <h3 class="section-title"><i class="fas fa-magic"></i> What can you become?</h3>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">Based on your current portfolio, our AI predicts your most lucrative future paths.</p>

            <div id="careerResults" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem;">
                <div>
                    <div style="background: linear-gradient(135deg, #fff 0%, #f0fff4 100%); padding: 2.5rem; border-radius: 30px; border: 2px solid #2ecc71; position: relative;">
                        <span style="position: absolute; top: 20px; right: 20px; background: #2ecc71; color: white; padding: 5px 15px; border-radius: 50px; font-size: 0.8rem; font-weight: 700;">#1 Optimal Path</span>
                        <h2 id="primaryPathTitle" style="font-size: 2rem; color: #27ae60; margin-bottom: 10px;">...</h2>
                        <p id="primaryPathWhy" style="color: #444; line-height: 1.7; margin-bottom: 20px;"></p>
                        
                        <div style="margin-bottom: 20px; background: rgba(255,255,255,0.5); padding: 1.5rem; border-radius: 20px;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 15px; color: #27ae60;"><i class="fas fa-dna"></i> Strategic Alignment</h4>
                            <div style="height: 150px;"><canvas id="careerBarChart"></canvas></div>
                        </div>

                        <div style="display: flex; justify-content: space-between; padding-top: 15px; border-top: 1px solid #e0f2f1;">
                            <div>
                                <div style="font-size: 0.75rem; color: #777;">Prediction Confidence</div>
                                <div id="careerConf" style="font-weight: 800; font-size: 1.5rem; color: #2ecc71;">--%</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #777;">5-Year Growth Potential</div>
                                <div id="careerGrowth" style="font-weight: 800; font-size: 1.5rem; color: #2ecc71;">High</div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem; background: #f8f9fa; padding: 2rem; border-radius: 24px;">
                        <h4 style="margin-bottom: 15px;"><i class="fas fa-forward"></i> The 5-Year Vision</h4>
                        <p id="longTermVision" style="color: #666; font-style: italic; line-height: 1.6;"></p>
                    </div>
                </div>

                <div>
                    <div class="benchmark-card" style="border-left: 5px solid var(--accent-gold);">
                        <h4 style="margin-bottom: 15px;">Alternative Career Paths</h4>
                        <div id="altPaths"></div>
                    </div>
                    <div class="glass-card active" style="padding: 1.5rem; border-radius: 20px; background: white;">
                        <h4 style="margin-bottom: 15px;">Target Job Titles</h4>
                        <div id="idealTitles" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        let charts = {};

        document.addEventListener('DOMContentLoaded', () => {
            switchTab('market'); // Load default
        });

        async function switchTab(tab) {
            // UI state
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.glass-card').forEach(c => c.classList.remove('active'));
            
            // Activate current tab button
            document.querySelector(`.tab-btn[onclick*="'${tab}'"]`)?.classList.add('active');

            const viewId = tab + 'View';
            document.getElementById(viewId).classList.add('active');

            // Data loading (only once unless forced)
            if (tab === 'market' && !charts.radar) {
                runAnalysis('market');
            } else if (tab === 'career' && (!document.getElementById('primaryPathTitle').textContent || document.getElementById('primaryPathTitle').textContent === '...')) {
                runAnalysis('career');
            }
        }

        async function runAnalysis(mode, params = '') {
            showLoading(mode);
            try {
                const url = `profile_analyser_handler?mode=${mode}${params}`;
                const response = await fetch(url);
                const text = await response.text();
                
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error("Invalid Server Response:", text);
                    alert("Analysis Error: The system received an invalid response. Check console logs.");
                    return;
                }
                
                if (result.success) {
                    if (mode === 'market') renderMarket(result.analysis, result.cached);
                    else if (mode === 'target') renderTarget(result.analysis, result.cached);
                    else if (mode === 'career') renderCareer(result.analysis, result.cached);
                } else {
                    alert('AI Error: ' + result.message);
                }
            } catch (e) {
                console.error(e);
                alert('Connection Error');
            } finally {
                hideLoading();
            }
        }

        async function runTargetAnalysis() {
            const company = document.getElementById('targetCompany').value;
            const role = document.getElementById('targetRole').value;
            if (!company || !role) return alert('Please enter both company and role');
            runAnalysis('target', `&company=${encodeURIComponent(company)}&role=${encodeURIComponent(role)}`);
        }

        function renderMarket(data, isCached = false) {
            document.getElementById('marketSummaryContent').textContent = data.executive_summary;
            
            // Add cache badge
            const titleEl = document.getElementById('marketSummaryTitle');
            titleEl.innerHTML = 'Executive Market Positioning' + (isCached ? ' <span class="match-badge" style="background:rgba(0,0,0,0.1); color:#666; font-size:0.6rem; vertical-align:middle; margin-left:10px;">PREVIOUSLY ANALYSED</span>' : '');
            const tableBody = document.getElementById('comparisonTableBody');
            tableBody.innerHTML = '';
            data.skill_distribution.labels.forEach((label, i) => {
                const student = data.skill_distribution.student_scores[i];
                const avg = data.skill_distribution.market_avg[i];
                const diff = student - avg;
                const trendIcon = diff >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                const trendColor = diff >= 0 ? '#2ecc71' : '#e74c3c';

                tableBody.innerHTML += `
                    <tr style="border-bottom: 1px solid #f8f9fa; transition: background 0.2s;">
                        <td style="padding: 12px; font-weight: 600; font-size: 0.9rem; color: var(--text-main);">${label}</td>
                        <td style="padding: 12px;">
                            <div style="font-weight: 700; color: var(--primary-maroon); font-size: 1rem;">${student}%</div>
                        </td>
                        <td style="padding: 12px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-weight: 600; color: var(--text-muted); font-size: 0.9rem;">${avg}%</span>
                                <span style="font-size: 0.75rem; font-weight: 800; color: ${trendColor}; background: ${trendColor}15; padding: 2px 8px; border-radius: 50px; display: flex; align-items: center; gap: 4px;">
                                    <i class="fas ${trendIcon}"></i> ${Math.abs(diff)}%
                                </span>
                            </div>
                        </td>
                    </tr>
                `;
            });

            const benchmarkList = document.getElementById('benchmarkList');
            benchmarkList.innerHTML = data.market_benchmarks.map(b => `
                <div class="benchmark-card" style="margin-bottom: 12px; padding: 12px; border: 1px solid #f0f0f0; border-radius: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                        <h4 style="font-size: 0.9rem; font-weight: 700;">${b.category}</h4>
                        <span class="match-badge" style="background: ${getScoreColor(b.match_percentage)}; color: white; padding: 2px 10px; font-size: 0.8rem;">${b.match_percentage}%</span>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                        ${b.missing_keys.slice(0, 4).map(k => `<span style="font-size: 0.65rem; background: #fff0f0; color: #c0392b; padding: 2px 6px; border-radius: 4px;">+ ${k}</span>`).join('')}
                    </div>
                </div>
            `).join('');

            // 1. Radar Chart
            if (charts.radar) charts.radar.destroy();
            charts.radar = new Chart(document.getElementById('radarChart'), {
                type: 'radar',
                data: {
                    labels: data.skill_distribution.labels,
                    datasets: [{
                        label: 'Your Strength',
                        data: data.skill_distribution.student_scores,
                        backgroundColor: 'rgba(128, 0, 0, 0.2)',
                        borderColor: '#800000',
                        borderWidth: 2,
                        pointBackgroundColor: '#800000'
                    }, {
                        label: 'Global Mean',
                        data: data.skill_distribution.market_avg,
                        backgroundColor: 'rgba(212, 175, 55, 0.1)',
                        borderColor: '#D4AF37',
                        borderWidth: 1,
                        borderDash: [5, 5]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { r: { ticks: { display: false }, suggestedMin: 0, suggestedMax: 100 } },
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } }
                }
            });


            // 3. Line Chart (Industry Delta)
            if (charts.line) charts.line.destroy();
            charts.line = new Chart(document.getElementById('lineChart'), {
                type: 'line',
                data: {
                    labels: data.academic_vs_industry.labels,
                    datasets: [{
                        label: 'Your Level',
                        data: data.academic_vs_industry.student,
                        borderColor: '#800000',
                        backgroundColor: 'rgba(128, 0, 0, 0.05)',
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Elite Standard',
                        data: data.academic_vs_industry.industry_std,
                        borderColor: '#D4AF37',
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, max: 100, ticks: { font: { size: 9 } } },
                        x: { ticks: { font: { size: 9 } } }
                    },
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } }
                }
            });

            // 4. Role Compatibility Chart
            if (charts.roleCompatibility) charts.roleCompatibility.destroy();
            charts.roleCompatibility = new Chart(document.getElementById('roleCompatibilityChart'), {
                type: 'bar',
                data: {
                    labels: (data.role_fit_analysis || []).map(r => r.role),
                    datasets: [{
                        label: 'Match %',
                        data: (data.role_fit_analysis || []).map(r => r.match),
                        backgroundColor: (data.role_fit_analysis || []).map(r => getScoreColor(r.match)),
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { 
                        x: { beginAtZero: true, max: 100, ticks: { font: { size: 9 } } },
                        y: { ticks: { font: { size: 10, weight: '600' } } }
                    },
                    plugins: { legend: { display: false } }
                }
            });

            // 5. Strategic Action Roadmap
            const roadmapCont = document.getElementById('actionRoadmap');
            if (data.action_plan && data.action_plan.length > 0) {
                roadmapCont.innerHTML = data.action_plan.map(a => {
                    const pColor = a.priority === 'Critical' ? '#e74c3c' : (a.priority === 'High' ? '#e67e22' : '#f1c40f');
                    return `
                        <div style="border-left: 4px solid ${pColor}; padding: 12px 15px; margin-bottom: 12px; background: #fafafa; border-radius: 0 12px 12px 0;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 5px;">
                                <h4 style="font-size: 0.9rem; font-weight: 700; color: var(--text-main); font-family: 'Outfit';">${a.step}</h4>
                                <span style="font-size: 0.7rem; font-weight: 800; color: white; background: ${pColor}; padding: 2px 8px; border-radius: 50px;">${a.priority}</span>
                            </div>
                            <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; margin-bottom: 8px;">${a.task}</p>
                            <div style="font-size: 0.75rem; color: var(--primary-maroon); font-weight: 600;"><i class="fas fa-clock"></i> Target: ${a.timeframe}</div>
                        </div>
                    `;
                }).join('');
            } else {
                roadmapCont.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 20px;">No roadmap data available.</p>';
            }
        }

        function renderTarget(data, isCached = false) {
            document.getElementById('targetResults').style.display = 'block';
            const scoreEl = document.getElementById('targetScore');
            scoreEl.textContent = data.fit_score + '%';
            scoreEl.style.color = getScoreColor(data.fit_score);

            const verdictEl = document.getElementById('targetVerdict');
            verdictEl.innerHTML = data.verdict + (isCached ? ' <span style="font-size:0.6rem; opacity:0.8; display:block; margin-top:4px;">CACHED RESULT</span>' : '');
            verdictEl.style.background = getScoreColor(data.fit_score);
            verdictEl.style.color = 'white';

            document.getElementById('targetAlign').innerHTML = `<strong>Culture:</strong> ${data.company_culture_alignment}<br><br><strong>Tech:</strong> ${data.technical_alignment}`;
            document.getElementById('targetGaps').innerHTML = data.missing_critical_skills.map(s => `<span style="background: white; color: #c0392b; padding: 5px 12px; border-radius: 5px; font-size: 0.8rem; font-weight: 600;">${s}</span>`).join('');
            document.getElementById('targetAdvice').textContent = data.custom_advice;
            document.getElementById('targetPrep').innerHTML = data.interview_prep_topics.map(t => `<span style="background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 5px; font-size: 0.85rem;">${t}</span>`).join('');

            // Target Bar Chart
            if (charts.target) charts.target.destroy();
            charts.target = new Chart(document.getElementById('targetBarChart'), {
                type: 'bar',
                data: {
                    labels: data.requirement_match_chart.labels,
                    datasets: [{
                        label: 'Possessed',
                        data: data.requirement_match_chart.possessed,
                        backgroundColor: '#800000',
                        borderRadius: 6
                    }, {
                        label: 'Required',
                        data: data.requirement_match_chart.required,
                        backgroundColor: '#eee',
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { x: { max: 100, display: false }, y: { grid: { display: false } } },
                    plugins: { legend: { display: false } }
                }
            });
        }

        function renderCareer(data, isCached = false) {
            const titleEl = document.getElementById('primaryPathTitle');
            titleEl.innerHTML = data.primary_path.title + (isCached ? ' <i class="fas fa-history" style="font-size:1rem; opacity:0.5; margin-left:10px;" title="Retrieved from cache"></i>' : '');
            document.getElementById('primaryPathWhy').textContent = data.primary_path.why;
            document.getElementById('careerGrowth').textContent = data.primary_path.growth_potential;
            document.getElementById('longTermVision').textContent = data.long_term_projection;

            // Career Bar Chart
            if (charts.career) charts.career.destroy();
            charts.career = new Chart(document.getElementById('careerBarChart'), {
                type: 'bar',
                data: {
                    labels: data.primary_path.skill_alignment_chart.labels,
                    datasets: [{
                        data: data.primary_path.skill_alignment_chart.student,
                        backgroundColor: '#2ecc71',
                        borderRadius: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { display: false, max: 100 }, x: { grid: { display: false } } },
                    plugins: { legend: { display: false } }
                }
            });

            document.getElementById('altPaths').innerHTML = data.alternative_paths.map(p => `
                <div style="margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0;">
                    <div style="font-weight: 700; color: var(--primary-maroon);">${p.title}</div>
                    <div style="font-size: 0.8rem; color: #777;">${p.why}</div>
                </div>
            `).join('');

            document.getElementById('idealTitles').innerHTML = data.ideal_job_titles.map(t => `
                <span style="background: #eef2ff; color: #4f46e5; padding: 5px 12px; border-radius: 5px; font-size: 0.8rem; font-weight: 600;">${t}</span>
            `).join('');
        }

        async function applyMarketContext() {
            const company = document.getElementById('marketCompany').value;
            if (!company) return alert('Please enter a company name');
            runAnalysis('market', `&company=${encodeURIComponent(company)}`);
        }

        function showLoading(mode) {
            const overlay = document.getElementById('loadingOverlay');
            const text = document.getElementById('loadingText');
            
            if (mode === 'market') {
                const company = document.getElementById('marketCompany').value;
                text.textContent = company ? `Analysing ${company}'s Tech Trends...` : "Analysing Global Tech Trends...";
            }
            else if (mode === 'target') text.textContent = "Simulating Recruitment Process...";
            else if (mode === 'career') text.textContent = "Architecting Future Paths...";
            
            overlay.style.display = 'flex';
            overlay.style.opacity = '1';
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            overlay.style.opacity = '0';
            setTimeout(() => {
                if (overlay.style.opacity === '0') overlay.style.display = 'none';
            }, 500);
        }

        function getScoreColor(score) {
            if (score >= 85) return '#2ecc71'; // Expert/Elite
            if (score >= 60) return '#f1c40f'; // Solid/Improving
            return '#e67e22'; // Critical/Low
        }
    </script>
</body>
</html>
