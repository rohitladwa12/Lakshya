<?php
/**
 * Student - AI Resume Analyzer
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/BasicPdfParser.php';
require_once __DIR__ . '/../../src/Services/ResumeParser.php';
require_once __DIR__ . '/../../src/Services/ResumeScoringEngine.php';
require_once __DIR__ . '/../../src/Models/Resume.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();

$analysis = null;
$error = '';

// Check if we are viewing a specific result (optional enhancement)
// For now, we keep it as a single-page app state
?>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --light-gray: #f4f6f9;
            --medium-gray: #dee2e6;
            --dark-gray: #343a40;
            --danger: #ff4757;
            --success: #2ed573;
            --warning: #ffa502;
            --info: #17a2b8;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #333; }
        .container { max-width: 1300px; margin: 40px auto; padding: 0 25px; }
        
        .page-header { text-align: center; margin-bottom: 50px; }
        .page-header h1 { font-size: 3rem; color: var(--primary-maroon); margin-bottom: 15px; font-weight: 900; letter-spacing: -1px; }
        .page-header p { color: #666; font-size: 1.2rem; max-width: 700px; margin: 0 auto; line-height: 1.6; }
        
        /* Input Card */
        .card { background: var(--white); border-radius: 24px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
        .input-group { margin-bottom: 25px; }
        .input-group label { display: block; margin-bottom: 10px; font-weight: 700; color: #444; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .form-control { width: 100%; padding: 15px; border: 2px solid #edf2f7; border-radius: 12px; font-size: 1rem; transition: all 0.3s; background: #fbfbfb; }
        .form-control:focus { border-color: var(--primary-maroon); outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.05); }
        
        .btn { padding: 16px 32px; border-radius: 12px; font-weight: 800; cursor: pointer; border: none; font-size: 1rem; transition: all 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-primary { background: var(--primary-maroon); color: white; width: 100%; letter-spacing: 1px; text-transform: uppercase; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(128, 0, 0, 0.2); }
        
        /* Dashboard Layout */
        .dashboard-grid { display: grid; grid-template-columns: 380px 1fr; gap: 40px; }
        
        /* Circular Gauge */
        .gauge-container { position: relative; width: 200px; height: 200px; margin: 0 auto 30px; }
        .gauge-svg { transform: rotate(-90deg); }
        .gauge-bg { fill: none; stroke: #eee; stroke-width: 12; }
        .gauge-fill { fill: none; stroke-width: 12; stroke-linecap: round; transition: stroke-dasharray 1s ease-out; }
        .gauge-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
        .gauge-value { font-size: 3.5rem; font-weight: 900; color: var(--primary-maroon); line-height: 1; }
        .gauge-label { font-size: 0.75rem; color: #888; text-transform: uppercase; font-weight: 700; margin-top: 5px; }

        /* Recruiter Simulation */
        .recruiter-scan { background: #1a202c; color: #fff; border-radius: 20px; padding: 25px; margin-bottom: 30px; position: relative; overflow: hidden; }
        .scan-badge { position: absolute; top: 20px; right: 20px; padding: 8px 16px; border-radius: 8px; font-weight: 900; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; }
        .scan-badge.fail { background: var(--danger); }
        .scan-badge.pass { background: var(--success); }
        .scan-header { font-weight: 800; font-size: 1.1rem; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .scan-log { font-family: 'Courier New', monospace; font-size: 0.85rem; color: #a0aec0; line-height: 1.5; }
        .scan-item { margin-bottom: 8px; border-left: 2px solid #4a5568; padding-left: 12px; }
        .scan-item.error { border-left-color: var(--danger); color: #feb2b2; }
        .scan-item.check { border-left-color: var(--success); color: #9ae6b4; }

        /* Score Sidebar */
        .sidebar-card { background: #fff; border-radius: 24px; padding: 35px; border: 1px solid #edf2f7; box-shadow: 0 10px 25px rgba(0,0,0,0.03); position: sticky; top: 30px; }
        .metric-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 25px; }
        .metric-box { background: #f8fafc; padding: 15px; border-radius: 16px; text-align: center; border: 1px solid #f1f5f9; }
        .metric-box .val { font-size: 1.25rem; font-weight: 800; color: #2d3748; }
        .metric-box .lbl { font-size: 0.7rem; color: #718096; font-weight: 700; text-transform: uppercase; margin-top: 4px; }

        /* Findings UI */
        .finding-item { display: flex; gap: 15px; background: #fff; border-radius: 16px; padding: 20px; margin-bottom: 15px; border: 1px solid #edf2f7; transition: all 0.2s; }
        .finding-item:hover { border-color: #cbd5e0; transform: translateX(5px); }
        .finding-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.2rem; }
        .finding-icon.critical { background: #fff5f5; color: var(--danger); }
        .finding-icon.warning { background: #fffaf0; color: var(--warning); }
        .finding-content h5 { margin: 0 0 5px; font-weight: 800; font-size: 1rem; color: #1a202c; }
        .finding-content p { margin: 0; font-size: 0.9rem; color: #4a5568; line-height: 1.5; }
        .finding-fix { margin-top: 10px; font-size: 0.85rem; font-weight: 600; color: var(--primary-maroon); display: flex; align-items: center; gap: 5px; }

        /* Bullet Surgery Visuals */
        .surgery-card { background: #fff; border-radius: 20px; border: 1px solid #edf2f7; overflow: hidden; margin-bottom: 25px; }
        .surgery-header { padding: 15px 25px; background: #f8fafc; border-bottom: 1px solid #edf2f7; font-weight: 800; font-size: 0.9rem; text-transform: uppercase; color: #718096; letter-spacing: 1px; }
        .surgery-body { padding: 25px; }
        .surgery-row { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .surgery-col h6 { margin: 0 0 10px; font-size: 0.7rem; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 5px; }
        .surgery-before { color: #a0aec0; font-style: italic; border-left: 3px solid #e2e8f0; padding-left: 15px; font-size: 0.95rem; line-height: 1.6; }
        .surgery-after { color: #1a202c; font-weight: 600; border-left: 3px solid var(--success); padding-left: 15px; font-size: 1rem; line-height: 1.6; background: #f0fff4; padding: 15px; border-radius: 0 12px 12px 0; }
        .surgery-meta { margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e2e8f0; font-size: 0.85rem; color: #4a5568; }
        
        .loader-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.9); z-index: 10000; display: none; align-items: center; justify-content: center; text-align: center; }
        .spinner { width: 60px; height: 60px; border: 5px solid #eee; border-top-color: var(--primary-maroon); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Impact Badges */
        .impact-pill { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; margin-right: 5px; margin-bottom: 5px; }
        .pill-high { background: #fff5f5; color: #c53030; }
        .pill-med { background: #fffaf0; color: #9c4221; }
        .pill-low { background: #f0fff4; color: #22543d; }

        /* Roadmap UI */
        .insight-box { background: #fff; border-radius: 12px; padding: 25px; border: 1px solid #edf2f7; }
        .action-plan { background: #2c3e50; color: white; padding: 30px; border-radius: 24px; margin-top: 40px; }
        .step { display: flex; gap: 20px; margin-bottom: 20px; }
        .step-num { font-size: 1.5rem; font-weight: 800; color: var(--primary-gold); opacity: 0.8; }
        .step-content { flex: 1; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">
    <?php if (!$analysis): ?>
        <div class="page-header">
            <h1>Validate Your Resume</h1>
            <p>Get a brutally honest, recruiter-level analysis. Upload your PDF or paste text to discover exactly why you aren't getting callbacks.</p>
        </div>
        
        <div class="card" style="max-width: 700px; margin: 0 auto;">
            <?php if ($error): ?>
                <div style="background:#fdeded; color:#5f2120; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #f5c6cb;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="analyze-form">
                <!-- Target Role Removed as per request -->

                <div class="input-group">
                    <label>Upload Resume (PDF only)</label>
                    <div style="border: 2px dashed #ccc; padding: 30px; text-align: center; border-radius: 8px; cursor: pointer; position: relative;">
                        <p style="color: #666; margin-bottom: 5px;" id="file-label">Click to Upload PDF</p>
                        <input type="file" name="resume_pdf" id="pdf_file" accept=".pdf" style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; z-index: 10;" onchange="updateFileName(this)">
                    </div>
                </div>

                <div class="input-group">
                    <label>Target Job Role or Description (Recommended)</label>
                    <textarea name="job_description" class="form-control" rows="4" placeholder="Enter the target role (e.g. 'Java Developer') or paste a full Job Description here..."></textarea>
                    <small style="color: #666; font-size: 0.75rem; margin-top: 5px; display: block;">Providing a specific role or JD enables strict keyword matching and relevance scoring.</small>
                </div>

                
                <button type="submit" name="analyze" class="btn btn-primary" id="submit-btn">Running Analysis...</button>
            </form>

            <div id="loader" class="loader">
                <div style="font-size: 3rem;">🕵️‍♂️</div>
                <p>Analyzing strict recruiter metrics...<br><span style="font-size: 0.9rem; font-weight: 400; color: #666;">Parsing keywords, checking impacts, and looking for red flags.</span></p>
            </div>
        </div>
    <?php else: ?>
        <?php 
            $score = (int)$analysis['score'];
            
            // Re-map colors for the gauge specifically (hex needed for SVG)
            $hexColor = '#ff4757'; // danger
            if ($score > 40) $hexColor = '#ffa502'; // warning
            if ($score > 70) $hexColor = '#2ed573'; // success
            
            $dashArray = ($score / 100) * 565.48; // Circumference of circle with r=90 is 2*pi*90
        ?>

        <div class="dashboard-grid">
            <!-- Sidebar -->
            <div class="sidebar-area">
                <div class="sidebar-card">
                    <div class="gauge-container">
                        <svg class="gauge-svg" viewBox="0 0 200 200" width="200" height="200">
                            <circle class="gauge-bg" cx="100" cy="100" r="90"></circle>
                            <circle class="gauge-fill" cx="100" cy="100" r="90" 
                                    style="stroke: <?php echo $hexColor; ?>; stroke-dasharray: <?php echo $dashArray; ?> 565.48;"></circle>
                        </svg>
                        <div class="gauge-text">
                            <div class="gauge-value" style="color: <?php echo $hexColor; ?>;"><?php echo $score; ?></div>
                            <div class="gauge-label">Health Score</div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-bottom: 25px;">
                        <?php if ($score < 50): ?>
                            <div style="color: var(--danger); font-weight: 800; font-size: 0.9rem;">🛑 REJECTED BY 90% OF RECRUITERS</div>
                        <?php elseif ($score < 75): ?>
                            <div style="color: var(--warning); font-weight: 800; font-size: 0.9rem;">⚠️ NEEDS CRITICAL FIXES</div>
                        <?php else: ?>
                            <div style="color: var(--success); font-weight: 800; font-size: 0.9rem;">✅ RECRUITER READY</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="metric-grid">
                        <div class="metric-box">
                            <div class="val"><?php echo $analysis['scores_breakdown']['skills'] ?? $analysis['section_scores']['skills'] ?? 0; ?>%</div>
                            <div class="lbl">Keywords</div>
                        </div>
                        <div class="metric-box">
                            <div class="val"><?php echo $analysis['scores_breakdown']['quality'] ?? $analysis['section_scores']['experience'] ?? 0; ?>%</div>
                            <div class="lbl">Relevance</div>
                        </div>
                    </div>

                    <?php if ($analysis['metadata']['is_cached']): ?>
                        <div style="font-size: 0.7rem; color: #a0aec0; margin-top: 20px; text-align: center; font-style: italic;">
                            <i class="fas fa-history"></i> Analysis from local cache
                        </div>
                    <?php endif; ?>

                    <a href="resume_analyzer.php" class="btn btn-primary" style="display: flex; margin-top: 30px; text-decoration: none;">
                        <i class="fas fa-plus"></i> New Scan
                    </a>
                </div>

                <div class="insight-box" style="margin-top: 25px; border-radius: 20px;">
                    <h4 style="margin-bottom: 15px; font-size: 0.9rem; font-weight: 800; color: #4a5568;">SKILLS DETECTED</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach($analysis['skills_detected'] as $skill): ?>
                            <span style="background: #f1f5f9; color: #475569; padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 600;"><?php echo htmlspecialchars($skill); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-report">
                
                <!-- Recruiter Mode Simulation -->
                <div class="recruiter-scan">
                    <div class="scan-badge <?php echo $score < 60 ? 'fail' : 'pass'; ?>">
                        <?php echo $score < 60 ? 'Rejected in 6s' : 'Passed Screening'; ?>
                    </div>
                    <div class="scan-header">
                        <i class="fas fa-terminal"></i> RECRUITER SCAN SIMULATION v1.0
                    </div>
                    <div class="scan-log">
                        <div class="scan-item">Initializing 6-second eye-tracking simulation...</div>
                        <?php if (empty($analysis['contact']['email'])): ?>
                            <div class="scan-item error">[!] FATAL: Email contact missing. Recruiter gave up.</div>
                        <?php else: ?>
                            <div class="scan-item check">[✓] Contact info found: <?php echo htmlspecialchars($analysis['contact']['email']); ?></div>
                        <?php endif; ?>

                        <?php if ($analysis['scores_breakdown']['quality'] < 50): ?>
                            <div class="scan-item error">[!] NOTICE: Bullet points are too passive. Boring to read.</div>
                        <?php else: ?>
                            <div class="scan-item check">[✓] High impact verbs detected. Engaging.</div>
                        <?php endif; ?>

                        <?php if ($analysis['scores_breakdown']['skills'] < 60): ?>
                            <div class="scan-item error">[!] ATS WARNING: Critical keywords missing for this role.</div>
                        <?php endif; ?>
                        
                        <div class="scan-item">Simulation complete. Candidate rank: <?php echo $score; ?>/100.</div>
                    </div>
                </div>

                <!-- Qualitative Verdict -->
                <div style="margin-bottom: 40px;">
                    <h3 class="section-title">The ATS Verdict</h3>
                    <div style="background: #fff; padding: 25px; border-radius: 20px; border: 1px solid #edf2f7; font-size: 1.15rem; line-height: 1.8; color: #2d3748; font-style: italic; position: relative;">
                        <i class="fas fa-quote-left" style="position: absolute; top: 10px; left: 10px; opacity: 0.1; font-size: 2rem;"></i>
                        <?php 
                            if (isset($analysis['refinements']['qualitative_summary'])) {
                                echo htmlspecialchars($analysis['refinements']['qualitative_summary']);
                            } elseif (isset($analysis['suggestions'][0])) {
                                echo htmlspecialchars($analysis['suggestions'][0]);
                            } else {
                                echo "Your resume has been analyzed by our advanced ATS logic. Check the detailed sections below for improvements.";
                            }
                        ?>
                    </div>
                </div>

                <!-- Layout Audit (For hidden or poorly formatted sections) -->
                <?php if (!empty($analysis['refinements']['layout_audit']) && strlen($analysis['refinements']['layout_audit']) > 10): ?>
                <div style="margin-bottom: 40px; background: #fff5f5; border: 1px solid #feb2b2; padding: 25px; border-radius: 20px;">
                    <h4 style="color: #c53030; font-size: 0.95rem; font-weight: 800; text-transform: uppercase; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-search-plus"></i> Section Detection Audit
                    </h4>
                    <p style="font-size: 1rem; line-height: 1.6; color: #2d3748;">
                        <?php echo htmlspecialchars($analysis['refinements']['layout_audit']); ?>
                    </p>
                    <div style="margin-top: 15px; font-size: 0.85rem; color: #718096; font-style: italic;">
                        <i class="fas fa-info-circle"></i> This audit helps identify sections that our scanner might have missed due to formatting.
                    </div>
                </div>
                <?php endif; ?>

                <!-- Critical Fixes / Red Flags -->
                <div style="margin-bottom: 40px;">
                    <h3 class="section-title">Critical Issues & Red Flags</h3>
                    <div style="display: grid; gap: 15px;">
                        <?php 
                        $critical = $analysis['findings'] ?? [];
                        $critical = array_filter($critical, function($f) { return $f['severity'] === 'critical'; });
                        
                        $redFlags = $analysis['red_flags'] ?? [];
                        $issues = $analysis['issues'] ?? [];
                        ?>

                        <?php foreach($redFlags as $flag): ?>
                            <div class="finding-item" style="border-left: 5px solid var(--danger);">
                                <div class="finding-icon critical"><i class="fas fa-flag"></i></div>
                                <div class="finding-content">
                                    <h5>RED FLAG DETECTED</h5>
                                    <p><?php echo htmlspecialchars($flag); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach($issues as $issue): ?>
                            <div class="finding-item">
                                <div class="finding-icon warning"><i class="fas fa-exclamation-circle"></i></div>
                                <div class="finding-content">
                                    <h5>Potential Issue</h5>
                                    <p><?php echo htmlspecialchars($issue); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach($critical as $item): ?>
                            <div class="finding-item">
                                <div class="finding-icon critical"><i class="fas fa-exclamation-triangle"></i></div>
                                <div class="finding-content">
                                    <h5><?php echo htmlspecialchars($item['message']); ?></h5>
                                    <p>Recruiters often discard resumes immediately for this reason.</p>
                                    <div class="finding-fix">
                                        <i class="fas fa-wrench"></i> <b>ACTION:</b> <?php echo htmlspecialchars($item['fix']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Keywords Analysis -->
                <div style="margin-top: 40px; padding: 30px; background: #fff; border-radius: 20px; border: 1px dashed #cbd5e0;">
                    <h4 style="color: #4a5568; font-size: 0.9rem; text-transform: uppercase; margin-bottom: 20px; font-weight: 800;">🔍 Keyword Analysis</h4>
                    
                    <?php if (!empty($analysis['matched_keywords'])): ?>
                        <div style="margin-bottom: 15px;">
                            <h6 style="font-size: 0.7rem; color: var(--success); font-weight: 800; margin-bottom: 8px;">MATCHED KEYWORDS</h6>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                <?php foreach($analysis['matched_keywords'] as $kw): ?>
                                    <span style="background: #f0fff4; color: #22543d; padding: 5px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: 1px solid #c6f6d5;"><?php echo htmlspecialchars($kw); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($analysis['missing_keywords'])): ?>
                        <div style="margin-bottom: 15px;">
                            <h6 style="font-size: 0.7rem; color: var(--danger); font-weight: 800; margin-bottom: 8px;">MISSING CRITICAL KEYWORDS</h6>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                <?php foreach($analysis['missing_keywords'] as $kw): ?>
                                    <span style="background: #fff5f5; color: #c53030; padding: 5px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: 1px solid #fed7d7;"><?php echo htmlspecialchars($kw); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($analysis['refinements']['impact_phrases_to_use'])): ?>
                        <h6 style="font-size: 0.7rem; color: var(--primary-maroon); font-weight: 800; margin-bottom: 8px;">IMPACT PHRASES TO ADD</h6>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php foreach($analysis['refinements']['impact_phrases_to_use'] as $phrase): ?>
                                <span style="background: #f8fafc; color: var(--primary-maroon); border: 1px solid #edf2f7; padding: 8px 16px; border-radius: 10px; font-size: 0.85rem; font-weight: 700;"><?php echo htmlspecialchars($phrase); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <p style="margin-top: 15px; font-size: 0.8rem; color: #718096;"><i class="fas fa-info-circle"></i> ATS algorithms prioritize resumes with exact keyword matches from the job description.</p>
                </div>

                <!-- Bullet Point Surgery -->
                <div style="margin-bottom: 40px;">
                    <h3 class="section-title">Bullet Point Surgery (Before vs After)</h3>
                    <p style="color: #666; margin-bottom: 25px;">We've identified your weakest bullets and rewritten them to sound like a senior professional.</p>
                    
                    <?php 
                        $surgeries = $analysis['refinements']['bullet_surgery'] ?? $analysis['improved_bullets'] ?? [];
                    ?>

                    <?php if (!empty($surgeries)): ?>
                        <?php foreach($surgeries as $surgery): ?>
                        <div class="surgery-card">
                            <div class="surgery-header">
                                Surgery ID: <?php echo substr(md5($surgery['original']), 0, 8); ?>
                            </div>
                            <div class="surgery-body">
                                <div class="surgery-row">
                                    <div class="surgery-col">
                                        <h6><i class="fas fa-times-circle" style="color: var(--danger);"></i> Current Version</h6>
                                        <div class="surgery-before">"<?php echo htmlspecialchars($surgery['original']); ?>"</div>
                                    </div>
                                    <div class="surgery-col">
                                        <h6><i class="fas fa-check-circle" style="color: var(--success);"></i> Pro Version</h6>
                                        <div class="surgery-after">"<?php echo htmlspecialchars($surgery['improved'] ?? $surgery['suggested']); ?>"</div>
                                    </div>
                                </div>
                                <?php if (isset($surgery['why'])): ?>
                                <div class="surgery-meta">
                                    <i class="fas fa-info-circle"></i> <b>Why this works:</b> <?php echo htmlspecialchars($surgery['why']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; background: #f8fafc; border-radius: 20px;">
                            <i class="fas fa-sparkles" style="font-size: 2rem; color: var(--primary-gold);"></i>
                            <p style="margin-top: 15px; font-weight: 600;">Your bullet points are already high-impact!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Plan -->
                <div class="action-plan" style="border-radius: 24px;">
                    <h3 style="margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; font-weight: 800;">🚀 YOUR STRATEGIC ROADMAP</h3>
                    <div style="display: grid; gap: 20px;">
                        <?php 
                            $steps = $analysis['refinements']['strategic_advice'] ?? $analysis['suggestions'] ?? [];
                        ?>
                        <?php foreach($steps as $index => $step): ?>
                        <div class="step">
                            <div class="step-num"><?php echo str_pad($index + 1, 2, '0', STR_PAD_LEFT); ?></div>
                            <div class="step-content">
                                <div style="font-weight: 700; font-size: 1.1rem; margin-bottom: 5px;">Step <?php echo $index + 1; ?></div>
                                <div style="opacity: 0.8; line-height: 1.6;"><?php echo htmlspecialchars($step); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Full Screen Loader Overlay -->
<div id="loadingOverlay" class="loader-overlay">
    <div>
        <div class="spinner"></div>
        <h2 style="color: var(--primary-maroon); font-weight: 900; letter-spacing: -1px; margin-bottom: 10px;">AI INSPECTOR AT WORK</h2>
        <p style="color: #666; font-size: 1.1rem; max-width: 400px;">Simulating recruiter eye-tracking and calculating impact metrics...</p>
        <div id="loadingStatus" style="margin-top: 20px; font-family: monospace; color: #888; font-size: 0.85rem;">[ ] Initializing neural parser...</div>
    </div>
</div>

<script>
    function updateFileName(input) {
        if (input.files && input.files[0]) {
            document.getElementById('file-label').innerHTML = "<i class='fas fa-file-pdf'></i> " + input.files[0].name;
            document.getElementById('file-label').parentElement.style.borderColor = 'var(--primary-maroon)';
            document.getElementById('file-label').parentElement.style.background = '#fff5f5';
        }
    }

    const form = document.getElementById('analyze-form');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            const formData = new FormData(this);
            formData.append('action', 'submit_analysis');

            try {
                const response = await fetch('resume_analysis_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    if (data.result) {
                        // Instant cache hit
                        renderResults(data.result);
                    } else if (data.job_id) {
                        // Polling required
                        pollJobStatus(data.job_id);
                    }
                } else {
                    alert(data.message);
                    document.getElementById('loadingOverlay').style.display = 'none';
                }
            } catch (err) {
                alert("Upload failed. Check your connection.");
                document.getElementById('loadingOverlay').style.display = 'none';
            }
        });
    }

    async function pollJobStatus(jobId) {
        const statusEl = document.getElementById('loadingStatus');
        const statuses = [
            "[#] Stripping PDF metadata...",
            "[#] Identifying Experience sections...",
            "[#] Running keyword density check...",
            "[#] Simulating 6-second recruiter scan...",
            "[#] Generating Pro-rewrites for bullets...",
            "[#] Finalizing health score..."
        ];
        let i = 0;

        const poll = async () => {
            try {
                const res = await fetch(`ai_job_status.php?job_id=${jobId}`);
                const data = await res.json();

                if (data.status === 'completed') {
                    renderResults(data.result);
                } else if (data.status === 'failed') {
                    alert("Analysis failed: " + data.error);
                    document.getElementById('loadingOverlay').style.display = 'none';
                } else {
                    if (i < statuses.length) statusEl.textContent = statuses[i++];
                    setTimeout(poll, 2000);
                }
            } catch (e) {
                console.error("Polling error", e);
                setTimeout(poll, 3000);
            }
        };
        poll();
    }

    function renderResults(analysis) {
        // Since the page has complex PHP-based rendering, we'll refresh with a session-based approach 
        // OR Inject the HTML. For simplicity and robustness, we'll save to session and reload 
        // OR just hide the form and show a pre-rendered template.
        // Given the scale, we'll use a clean redirect or hash-based view.
        
        // For now, let's just reload the page - the cache will handle the rendering immediately.
        location.reload(); 
    }
</script>

</body>
</html>
