<?php
/**
 * Student Live Market Job Search
 * Multi-Input AI Semantic Relevance Search & Aggregation System
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_STUDENT);

$userId = getUserId();
$username = getUsername();
$fullName = getFullName();
$db = getDB();

// Fetch student skills directly from student_portfolio table
$portfolioSkills = [];
try {
    $stmt = $db->prepare("SELECT title FROM student_portfolio WHERE (student_id = ? OR student_id = ? OR UPPER(student_id) = UPPER(?)) AND category = 'Skill'");
    $stmt->execute([(string)$userId, $username, $username]);
    $portfolioSkills = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) {}

$initialSkillsText = implode(', ', array_values(array_unique(array_filter($portfolioSkills))));

$pageTitle = "Live Market Job Search — Lakshya";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        maroon: { DEFAULT: '#800000', dark: '#5b1f1f', light: '#fef2f2', glass: 'rgba(128,0,0,0.04)' },
                        surface: { DEFAULT: '#ffffff', muted: '#f8fafc', subtle: '#f1f5f9' }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        display: ['Outfit', 'Inter', 'sans-serif']
                    },
                    borderRadius: {
                        '2xl': '16px',
                        '3xl': '20px',
                        '4xl': '24px'
                    }
                }
            }
        }
    </script>

    <style>
        /* ═══════════════════════════════════════════
           DESIGN TOKENS & BASE
           ═══════════════════════════════════════════ */
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }

        /* ═══════════════════════════════════════════
           SEARCH HERO
           ═══════════════════════════════════════════ */
        .search-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            border-radius: 24px;
            position: relative;
            overflow: hidden;
        }
        .search-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(128,0,0,0.15) 0%, transparent 70%);
            pointer-events: none;
        }
        .search-hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(128,0,0,0.08) 0%, transparent 70%);
            pointer-events: none;
        }

        /* ═══════════════════════════════════════════
           FORM CONTROLS
           ═══════════════════════════════════════════ */
        .search-input-main {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border-radius: 14px;
            padding: 14px 16px 14px 48px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #ffffff;
            outline: none;
            width: 100%;
            transition: all 0.25s ease;
        }
        .search-input-main::placeholder { color: rgba(255,255,255,0.45); }
        .search-input-main:focus {
            border-color: rgba(255,255,255,0.35);
            background: rgba(255,255,255,0.14);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.06);
        }

        .filter-select, .filter-input {
            appearance: none;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 0.78rem;
            font-weight: 500;
            color: rgba(255,255,255,0.85);
            outline: none;
            width: 100%;
            transition: all 0.2s ease;
        }
        .filter-select:focus, .filter-input:focus {
            border-color: rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.12);
        }
        .filter-select option { background: #1e293b; color: #e2e8f0; }
        .filter-input::placeholder { color: rgba(255,255,255,0.35); }

        .filter-label {
            font-size: 0.68rem;
            font-weight: 600;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
            display: block;
        }

        /* ═══════════════════════════════════════════
           QUICK CHIP BUTTONS
           ═══════════════════════════════════════════ */
        .quick-chip {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.6);
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .quick-chip:hover {
            background: rgba(128,0,0,0.5);
            border-color: rgba(128,0,0,0.6);
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* ═══════════════════════════════════════════
           JOB CARDS
           ═══════════════════════════════════════════ */
        .job-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .job-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.08);
            transform: translateY(-4px);
        }
        .job-card-body { padding: 20px 20px 16px; flex: 1; }
        .job-card-footer {
            padding: 14px 20px;
            border-top: 1px solid #f1f5f9;
            background: #fafbfc;
        }

        /* Match Score Ring */
        .match-ring {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 800;
            flex-shrink: 0;
            position: relative;
        }
        .match-ring::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 3px solid currentColor;
            opacity: 0.15;
        }
        .match-ring-high { color: #059669; background: #ecfdf5; }
        .match-ring-mid { color: #d97706; background: #fffbeb; }
        .match-ring-low { color: #6b7280; background: #f3f4f6; }

        /* Skill Tags */
        .skill-tag-match {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 600;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .skill-tag-missing {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 600;
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* ═══════════════════════════════════════════
           SIDEBAR INSIGHT CARD
           ═══════════════════════════════════════════ */
        .insight-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
        }
        .insight-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .insight-card-body { padding: 20px; }

        .progress-bar-track {
            width: 100%;
            height: 6px;
            background: #f1f5f9;
            border-radius: 50px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 50px;
            background: linear-gradient(90deg, #800000, #a03030);
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .location-tag {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        /* ═══════════════════════════════════════════
           SEARCH BUTTON
           ═══════════════════════════════════════════ */
        .search-btn {
            background: linear-gradient(135deg, #800000 0%, #a03030 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            box-shadow: 0 4px 14px rgba(128, 0, 0, 0.25);
        }
        .search-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(128, 0, 0, 0.35);
        }
        .search-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* ═══════════════════════════════════════════
           LOADING SKELETON
           ═══════════════════════════════════════════ */
        @keyframes shimmer {
            0% { background-position: -400px 0; }
            100% { background-position: 400px 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 800px 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 8px;
        }

        /* ═══════════════════════════════════════════
           APPLY BUTTON
           ═══════════════════════════════════════════ */
        .apply-btn {
            background: #0f172a;
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .apply-btn:hover {
            background: #800000;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
        }

        /* ═══════════════════════════════════════════
           SEMANTIC BANNER
           ═══════════════════════════════════════════ */
        .semantic-banner {
            background: linear-gradient(135deg, #eff6ff, #f0f9ff);
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 12px 16px;
        }

        /* ═══════════════════════════════════════════
           SHOW MORE BUTTON
           ═══════════════════════════════════════════ */
        .load-more-btn {
            background: #ffffff;
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 14px 28px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #475569;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .load-more-btn:hover {
            border-color: #800000;
            color: #800000;
            background: #fef2f2;
        }

        /* ═══════════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════════ */
        @media (max-width: 768px) {
            .search-hero { border-radius: 16px; }
            .filter-grid { grid-template-columns: 1fr 1fr !important; }
        }
        @media (max-width: 480px) {
            .filter-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body class="min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        <!-- ╔══════════════════════════════════════════════
             ║ SEARCH HERO — Dark gradient search panel
             ╚══════════════════════════════════════════════ -->
        <div class="search-hero p-6 sm:p-8 mb-8">

            <!-- Header Row -->
            <div class="relative z-10 flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
                <div>
                    <div class="flex items-center gap-2.5 mb-2">
                        <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-white/40">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Live Market
                        </span>
                        <span class="text-white/15">|</span>
                        <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider">AI Semantic Engine</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white font-display tracking-tight">
                        Job Search
                    </h1>
                    <p class="text-[13px] text-white/40 mt-1 max-w-lg leading-relaxed">
                        Live feeds aggregated from official APIs, semantically expanded and ranked by multi-dimensional relevance against your profile.
                    </p>
                </div>
                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <span class="inline-flex items-center gap-1.5 bg-white/[0.06] border border-white/[0.08] text-white/50 text-[11px] font-semibold px-3 py-1.5 rounded-lg">
                        <i class="fas fa-bolt text-amber-400 text-[10px]"></i>
                        Unlimited Searches
                    </span>
                </div>
            </div>

            <!-- Primary Search Input -->
            <div class="relative z-10 mb-5">
                <div class="relative">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-white/30 text-sm"></i>
                    <input type="text" id="inputQuery" placeholder="Job title, role, or technology — e.g. AI Engineer, Data Scientist, React Developer..."
                           class="search-input-main">
                </div>
            </div>

            <!-- Filters Grid -->
            <div class="relative z-10 filter-grid grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-5" style="grid-template-columns: repeat(4, 1fr);">

                <div>
                    <label class="filter-label">Location</label>
                    <select id="selectLocation" class="filter-select">
                        <option value="any">Any Location</option>
                        <option value="Bengaluru">Bengaluru</option>
                        <option value="Hyderabad">Hyderabad</option>
                        <option value="Pune">Pune</option>
                        <option value="Mumbai">Mumbai</option>
                        <option value="Delhi NCR">Delhi NCR</option>
                        <option value="Chennai">Chennai</option>
                        <option value="Remote">Remote Only</option>
                    </select>
                </div>

                <div>
                    <label class="filter-label">Experience</label>
                    <select id="selectExperience" class="filter-select">
                        <option value="any">Any Level</option>
                        <option value="internship">Internship (0 yrs)</option>
                        <option value="fresher">Fresher / Entry (0–1 yr)</option>
                        <option value="junior">Junior (1–2 yrs)</option>
                        <option value="mid">Mid Level (2–5 yrs)</option>
                        <option value="senior">Senior (5+ yrs)</option>
                    </select>
                </div>

                <div>
                    <label class="filter-label">Work Mode</label>
                    <select id="selectWorkMode" class="filter-select">
                        <option value="any">Any Mode</option>
                        <option value="remote">Remote</option>
                        <option value="hybrid">Hybrid</option>
                        <option value="onsite">On-site</option>
                    </select>
                </div>

                <div>
                    <label class="filter-label">Employment</label>
                    <select id="selectEmpType" class="filter-select">
                        <option value="any">Any Type</option>
                        <option value="fulltime">Full-time</option>
                        <option value="internship">Internship</option>
                        <option value="contract">Contract</option>
                        <option value="parttime">Part-time</option>
                    </select>
                </div>

                <div>
                    <label class="filter-label">Your Skills <span class="text-white/20">· from portfolio</span></label>
                    <input type="text" id="inputSkills" value="<?php echo htmlspecialchars($initialSkillsText); ?>" placeholder="e.g. Python, LLM, RAG" class="filter-input">
                </div>

                <div>
                    <label class="filter-label">Posted Within</label>
                    <select id="selectRecency" class="filter-select">
                        <option value="any">Anytime</option>
                        <option value="24h">Past 24 hours</option>
                        <option value="3d">Past 3 days</option>
                        <option value="7d">Past 7 days</option>
                        <option value="30d">Past 30 days</option>
                    </select>
                </div>

                <div>
                    <label class="filter-label">Min. Salary (₹/yr)</label>
                    <input type="number" id="inputMinSalary" placeholder="e.g. 600000" class="filter-input">
                </div>

                <div class="flex items-end">
                    <button type="button" id="marketSearchBtn" onclick="executeMarketJobSearch(null, false)" class="search-btn">
                        <i class="fas fa-search text-sm"></i>
                        Search Jobs
                    </button>
                </div>
            </div>

            <!-- Quick Search Chips -->
            <div class="relative z-10 flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-medium text-white/25 mr-0.5">Trending:</span>
                <button type="button" onclick="triggerQuickJobSearch('AI Engineer')" class="quick-chip">AI Engineer</button>
                <button type="button" onclick="triggerQuickJobSearch('Machine Learning Engineer')" class="quick-chip">ML Engineer</button>
                <button type="button" onclick="triggerQuickJobSearch('Software Engineer')" class="quick-chip">SWE</button>
                <button type="button" onclick="triggerQuickJobSearch('Data Scientist')" class="quick-chip">Data Scientist</button>
                <button type="button" onclick="triggerQuickJobSearch('GenAI Engineer')" class="quick-chip">GenAI</button>
                <button type="button" onclick="triggerQuickJobSearch('Frontend Developer')" class="quick-chip">Frontend</button>
                <button type="button" onclick="triggerQuickJobSearch('Backend Developer')" class="quick-chip">Backend</button>
            </div>
        </div>

        <!-- ╔══════════════════════════════════════════════
             ║ SEMANTIC EXPANSION BANNER
             ╚══════════════════════════════════════════════ -->
        <div id="semanticBanner" class="hidden semantic-banner mb-6 flex items-center gap-2 text-[13px] text-blue-800">
            <i class="fas fa-brain text-blue-500"></i>
            <span><strong>AI Expanded:</strong> Also matching <span id="semanticTitlesList" class="font-semibold"></span></span>
        </div>

        <!-- ╔══════════════════════════════════════════════
             ║ MAIN CONTENT GRID — Sidebar + Results
             ╚══════════════════════════════════════════════ -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- ── LEFT SIDEBAR: Market Insights ─────── -->
            <aside class="lg:col-span-3 flex flex-col gap-5">

                <!-- Insight: Active Jobs -->
                <div class="insight-card">
                    <div class="insight-card-header">
                        <div class="w-8 h-8 rounded-lg bg-maroon-light flex items-center justify-center">
                            <i class="fas fa-chart-bar text-maroon text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 font-display">Market Pulse</h3>
                            <p id="insightUpdatedText" class="text-[10px] text-emerald-600 font-semibold">Updated just now</p>
                        </div>
                    </div>
                    <div class="insight-card-body">
                        <div class="text-center mb-5 pb-5 border-b border-slate-100">
                            <div id="insightTotalActive" class="text-3xl font-extrabold text-slate-900 font-display">—</div>
                            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mt-1">Active Roles Analyzed</div>
                        </div>

                        <!-- Most Requested Skills -->
                        <div class="mb-5">
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-3">In-Demand Skills</div>
                            <div id="insightSkillsContainer" class="space-y-3">
                                <div class="skeleton h-4 w-full"></div>
                                <div class="skeleton h-4 w-3/4"></div>
                                <div class="skeleton h-4 w-1/2"></div>
                            </div>
                        </div>

                        <!-- Top Hiring Locations -->
                        <div>
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-3">Hiring Hubs</div>
                            <div id="insightLocationsContainer" class="flex flex-wrap gap-1.5">
                                <span class="skeleton h-6 w-20"></span>
                                <span class="skeleton h-6 w-16"></span>
                                <span class="skeleton h-6 w-24"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transparency Card -->
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-6 h-6 rounded-md bg-emerald-100 flex items-center justify-center">
                            <i class="fas fa-shield-alt text-emerald-600 text-[10px]"></i>
                        </div>
                        <span class="text-xs font-bold text-slate-700">Transparency</span>
                    </div>
                    <p class="text-[11px] text-slate-500 leading-relaxed">
                        Jobs are aggregated from official public APIs. Lakshya does not claim ownership of any listing. All applications link directly to the employer's career page.
                    </p>
                </div>
            </aside>

            <!-- ── RIGHT COLUMN: Job Results ─────── -->
            <section class="lg:col-span-9">

                <!-- Results Header -->
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-bold text-slate-900 font-display">Results</h2>
                        <span id="resultsCountBadge" class="text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-lg hidden"></span>
                    </div>
                    <div class="text-xs text-slate-400 font-medium">
                        Ranked by relevance to your profile
                    </div>
                </div>

                <!-- Job Results Grid -->
                <div id="marketJobResultsGrid" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Empty State -->
                    <div class="col-span-full">
                        <div class="bg-white border border-slate-200 rounded-2xl py-20 text-center">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-slate-100 flex items-center justify-center">
                                <i class="fas fa-compass text-slate-400 text-xl"></i>
                            </div>
                            <h3 class="text-base font-bold text-slate-700 font-display mb-1">Discover Opportunities</h3>
                            <p class="text-sm text-slate-400 max-w-sm mx-auto">
                                Use the search above to find live market roles ranked against your candidate profile.
                            </p>
                        </div>
                    </div>
                </div>

            </section>
        </div>
    </main>

    <!-- ═══════════════════════════════════════════
         CLIENT JAVASCRIPT
         ═══════════════════════════════════════════ -->
    <script>
        let currentPage = 1;
        let totalLoadedJobs = 0;

        function triggerQuickJobSearch(query) {
            document.getElementById('inputQuery').value = query;
            executeMarketJobSearch(null, false);
        }

        function loadNextJobPage() {
            currentPage++;
            executeMarketJobSearch(null, true);
        }

        async function executeMarketJobSearch(e, isAppend = false) {
            if (e) e.preventDefault();
            const btn = document.getElementById('marketSearchBtn');
            const grid = document.getElementById('marketJobResultsGrid');
            const badge = document.getElementById('resultsCountBadge');
            if (!btn || !grid) return;

            if (!isAppend) {
                currentPage = 1;
                totalLoadedJobs = 0;
            }

            const query = (document.getElementById('inputQuery').value || 'AI Engineer').trim();
            const location = document.getElementById('selectLocation').value;
            const experience = document.getElementById('selectExperience').value;
            const workMode = document.getElementById('selectWorkMode').value;
            const empType = document.getElementById('selectEmpType').value;
            const skills = document.getElementById('inputSkills').value;
            const recency = document.getElementById('selectRecency').value;
            const minSalary = document.getElementById('inputMinSalary').value;

            const oldLoadMore = document.getElementById('loadMoreContainer');
            if (oldLoadMore) oldLoadMore.remove();

            if (!isAppend) {
                btn.disabled = true;
                btn.innerHTML = `<i class="fas fa-circle-notch fa-spin text-sm"></i> Searching...`;
                grid.innerHTML = buildLoadingSkeleton(query);
            } else {
                const loadBtn = document.getElementById('loadMoreJobsBtn');
                if (loadBtn) {
                    loadBtn.disabled = true;
                    loadBtn.innerHTML = `<i class="fas fa-circle-notch fa-spin text-xs mr-1"></i> Loading more...`;
                }
            }

            try {
                const res = await fetch('search_market_jobs.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'search',
                        query, location, experience,
                        work_mode: workMode,
                        emp_type: empType,
                        skills, recency,
                        min_salary: minSalary,
                        page: currentPage
                    })
                });
                const data = await res.json();

                btn.disabled = false;
                btn.innerHTML = `<i class="fas fa-search text-sm"></i> Search Jobs`;

                // Semantic banner
                if (data.semantic_expanded_titles && data.semantic_expanded_titles.length > 1) {
                    const banner = document.getElementById('semanticBanner');
                    const list = document.getElementById('semanticTitlesList');
                    if (banner && list) {
                        banner.classList.remove('hidden');
                        list.textContent = data.semantic_expanded_titles.slice(1, 4).join(', ');
                    }
                } else {
                    document.getElementById('semanticBanner')?.classList.add('hidden');
                }

                // Market insights
                if (data.market_insights) renderMarketInsights(data.market_insights);

                if (!data.success) {
                    if (!isAppend) {
                        grid.innerHTML = `
                            <div class="col-span-full bg-red-50 border border-red-100 rounded-2xl p-8 text-center">
                                <i class="fas fa-exclamation-circle text-red-400 text-xl mb-2"></i>
                                <p class="text-red-700 font-semibold text-sm">${data.message || 'Unable to retrieve jobs.'}</p>
                            </div>`;
                    }
                    return;
                }

                if (!data.jobs || data.jobs.length === 0) {
                    if (!isAppend) {
                        grid.innerHTML = `
                            <div class="col-span-full bg-white border border-slate-200 rounded-2xl py-16 text-center">
                                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-slate-100 flex items-center justify-center">
                                    <i class="fas fa-search text-slate-400 text-lg"></i>
                                </div>
                                <h4 class="text-base font-bold text-slate-700 font-display mb-1">No matching roles found</h4>
                                <p class="text-sm text-slate-400">Try broadening your search criteria or adjusting filters.</p>
                            </div>`;
                    }
                    return;
                }

                totalLoadedJobs += data.jobs.length;
                if (badge) {
                    badge.textContent = `${totalLoadedJobs} positions`;
                    badge.classList.remove('hidden');
                }

                const cardsHTML = data.jobs.map(buildJobCard).join('');

                if (!isAppend) {
                    grid.innerHTML = cardsHTML;
                } else {
                    grid.insertAdjacentHTML('beforeend', cardsHTML);
                }

                if (data.has_more !== false) {
                    grid.insertAdjacentHTML('beforeend', `
                        <div id="loadMoreContainer" class="col-span-full py-4 text-center">
                            <button type="button" id="loadMoreJobsBtn" onclick="loadNextJobPage()" class="load-more-btn">
                                <i class="fas fa-plus mr-1.5"></i> Show More Opportunities
                            </button>
                        </div>`);
                }

            } catch(err) {
                btn.disabled = false;
                btn.innerHTML = `<i class="fas fa-search text-sm"></i> Search Jobs`;
                if (!isAppend) {
                    grid.innerHTML = `
                        <div class="col-span-full bg-white border border-slate-200 rounded-2xl p-8 text-center">
                            <i class="fas fa-wifi text-slate-300 text-2xl mb-2"></i>
                            <p class="text-slate-500 font-semibold text-sm">Connection error. Please try again.</p>
                        </div>`;
                }
            }
        }

        /* ── Build a single job card HTML ── */
        function buildJobCard(j) {
            const score = j.match_percentage || 0;
            const ringClass = score >= 80 ? 'match-ring-high' : score >= 60 ? 'match-ring-mid' : 'match-ring-low';

            const strongHTML = (j.strong_matches || []).map(s =>
                `<span class="skill-tag-match"><i class="fas fa-check text-[8px]"></i> ${s}</span>`
            ).join('');

            const missingHTML = (j.missing_requirements || []).map(s =>
                `<span class="skill-tag-missing"><i class="fas fa-times text-[8px]"></i> ${s}</span>`
            ).join('');

            const salaryDisplay = j.salary_text || 'Not disclosed';
            const expDisplay = j.exp_requirement || 'Not specified';

            return `
                <div class="job-card">
                    <div class="job-card-body">
                        <!-- Header: Match Ring + Source -->
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="text-[10px] font-semibold text-slate-400 bg-slate-50 border border-slate-100 px-2 py-0.5 rounded-md truncate">
                                        ${j.source || 'Official API'}
                                    </span>
                                    <span class="text-[10px] text-slate-300">·</span>
                                    <span class="text-[10px] font-medium text-slate-400">${j.last_checked_text || 'Recently'}</span>
                                </div>
                                <h3 class="text-[15px] font-bold text-slate-900 leading-snug line-clamp-2">${j.title}</h3>
                                <p class="text-[13px] font-semibold text-slate-500 mt-0.5">${j.company}</p>
                            </div>
                            <div class="match-ring ${ringClass}" title="${score}% Match">
                                ${score}%
                            </div>
                        </div>

                        <!-- Meta Row -->
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[12px] text-slate-500 mb-3.5">
                            <span class="flex items-center gap-1.5">
                                <i class="fas fa-map-marker-alt text-slate-300 text-[10px]"></i>
                                ${j.location || 'Remote'}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <i class="fas fa-briefcase text-slate-300 text-[10px]"></i>
                                ${expDisplay}
                            </span>
                            <span class="flex items-center gap-1.5 font-semibold ${salaryDisplay === 'Salary: Not disclosed' ? 'text-slate-400' : 'text-slate-700'}">
                                <i class="fas fa-indian-rupee-sign text-slate-300 text-[10px]"></i>
                                ${salaryDisplay}
                            </span>
                        </div>

                        <!-- Skill Tags -->
                        ${(strongHTML || missingHTML) ? `
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                ${strongHTML}${missingHTML}
                            </div>
                        ` : ''}

                        <!-- Why Matched -->
                        ${j.why_matched_explanation ? `
                            <div class="text-[11px] text-slate-500 leading-relaxed bg-amber-50/60 border border-amber-100/70 rounded-lg px-3 py-2">
                                <i class="fas fa-lightbulb text-amber-400 mr-1"></i>
                                ${j.why_matched_explanation}
                            </div>
                        ` : ''}
                    </div>

                    <!-- Footer -->
                    <div class="job-card-footer flex items-center justify-between">
                        <span class="text-[10px] font-medium text-slate-400">
                            ${j.source_reliability || 'Verified'} · ${j.employment_type || 'Full-time'}
                        </span>
                        <a href="${j.apply_url}" target="_blank" rel="noopener noreferrer" class="apply-btn">
                            Apply <i class="fas fa-external-link-alt text-[9px]"></i>
                        </a>
                    </div>
                </div>`;
        }

        /* ── Loading skeleton ── */
        function buildLoadingSkeleton(query) {
            const card = `
                <div class="job-card">
                    <div class="job-card-body space-y-3">
                        <div class="flex justify-between"><div class="skeleton h-3 w-24"></div><div class="skeleton h-10 w-10 rounded-full"></div></div>
                        <div class="skeleton h-5 w-3/4"></div>
                        <div class="skeleton h-3 w-1/2"></div>
                        <div class="flex gap-3"><div class="skeleton h-3 w-20"></div><div class="skeleton h-3 w-16"></div><div class="skeleton h-3 w-24"></div></div>
                        <div class="flex gap-2"><div class="skeleton h-5 w-16 rounded-md"></div><div class="skeleton h-5 w-20 rounded-md"></div></div>
                    </div>
                    <div class="job-card-footer flex justify-between"><div class="skeleton h-3 w-20"></div><div class="skeleton h-8 w-16 rounded-lg"></div></div>
                </div>`;
            return `
                <div class="col-span-full mb-2">
                    <div class="flex items-center gap-2 text-sm text-slate-500 font-medium">
                        <i class="fas fa-circle-notch fa-spin text-maroon"></i>
                        Searching live feeds for "<strong class="text-slate-700">${query}</strong>"…
                    </div>
                </div>
                ${card}${card}${card}${card}`;
        }

        /* ── Render Market Insights ── */
        function renderMarketInsights(insights) {
            const totalEl = document.getElementById('insightTotalActive');
            const updatedEl = document.getElementById('insightUpdatedText');
            const skillsEl = document.getElementById('insightSkillsContainer');
            const locsEl = document.getElementById('insightLocationsContainer');

            if (totalEl) totalEl.textContent = insights.total_active_jobs || '—';
            if (updatedEl) updatedEl.textContent = insights.updated_text || 'Updated just now';

            if (skillsEl && insights.requested_skills) {
                skillsEl.innerHTML = insights.requested_skills.map(s => `
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-[12px] font-semibold text-slate-700">${s.skill}</span>
                            <span class="text-[11px] font-bold text-slate-400">${s.percentage}%</span>
                        </div>
                        <div class="progress-bar-track">
                            <div class="progress-bar-fill" style="width: ${s.percentage}%"></div>
                        </div>
                    </div>
                `).join('');
            }

            if (locsEl && insights.top_locations) {
                locsEl.innerHTML = insights.top_locations.map(l =>
                    `<span class="location-tag">${l}</span>`
                ).join('');
            }
        }

        /* ── Enter key support on search input ── */
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('inputQuery')?.addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); executeMarketJobSearch(null, false); }
            });

            // Auto-search on page load
            executeMarketJobSearch(null, false);
        });
    </script>
</body>
</html>
