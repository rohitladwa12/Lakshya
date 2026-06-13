<?php
/**
 * Student AI Talent Showcase Profile
 * A professional, highly polished portfolio card displaying academic metrics,
 * AI career analysis, topic masteries, and verified accomplishments.
 */

ob_start();
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

$userId = getUserId();
$username = getUsername();
$fullName = getFullName();
$institution = getInstitution();

$db = getDB();

// 1. Fetch Student Profile details using StudentProfile model
require_once ROOT_PATH . '/src/Models/StudentProfile.php';
$studentModel = new \StudentProfile();
$profile = $studentModel->getByUserId($userId, $institution);

if (!$profile) {
    $profile = [
        'name' => $fullName,
        'usn' => $username,
        'course' => 'N/A',
        'department' => 'N/A',
        'cgpa' => 'N/A',
        'semester' => 'N/A',
        'profile_photo' => null
    ];
}

// Ensure defaults
$studentName = $profile['name'] ?? $fullName;
$studentUsn = $profile['usn'] ?? $username;
$studentCourse = $profile['course'] ?? 'N/A';
$studentDept = $profile['department'] ?? 'N/A';
$studentCgpa = $profile['cgpa'] ?? 'N/A';
$studentSem = $profile['semester'] ?? 'N/A';

// Check profile photo
$profilePhoto = !empty($profile['profile_photo']) ? $profile['profile_photo'] : null;

// 2. Fetch Placement Ready Pool Status
$poolStmt = $db->prepare("SELECT COUNT(*) FROM placement_ready_pool WHERE usn = ? AND institution = ?");
$poolStmt->execute([$username, $institution]);
$isPlacementReady = ($poolStmt->fetchColumn() > 0);

// 3. Fetch AI Career Advisor predictions
$aiStmt = $db->prepare("SELECT * FROM student_ai_profiles WHERE student_id = ? AND institution = ? LIMIT 1");
$aiStmt->execute([$username, $institution]);
$aiProfile = $aiStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Parse interests
$interests = [];
if (!empty($aiProfile['detected_interests'])) {
    $decoded = json_decode($aiProfile['detected_interests'], true);
    if (is_array($decoded)) {
        $interests = $decoded;
    } else {
        $interests = array_map('trim', explode(',', $aiProfile['detected_interests']));
    }
}

// 4. Fetch Skill Mastery Levels (student_topic_mastery)
$masteryStmt = $db->prepare("SELECT topic_name, category, mastery_level FROM student_topic_mastery WHERE student_id = ? AND institution = ? ORDER BY category ASC, mastery_level DESC");
$masteryStmt->execute([$username, $institution]);
$masteryList = $masteryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 5. Fetch AI-Verified Skills (from unified_ai_assessments)
$verifiedSkills = [];
$skillStmt = $db->prepare("
    SELECT feedback 
    FROM unified_ai_assessments 
    WHERE student_id = ? AND institution = ? AND status = 'completed' AND assessment_type = 'Skill Verification'
");
$skillStmt->execute([$username, $institution]);
$skillsList = $skillStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($skillsList as $s) {
    if (preg_match('/Successfully verified skill:\s*(.+)/i', $s['feedback'], $matches)) {
        $verifiedSkills[] = strtoupper(trim($matches[1]));
    }
}
$verifiedSkills = array_unique($verifiedSkills);

// 6. Fetch AI Mock Interview Feedbacks (from unified_ai_assessments)
$interviewFeedbacks = [];
$intStmt = $db->prepare("
    SELECT assessment_type, company_name, score, feedback, details, completed_at 
    FROM unified_ai_assessments 
    WHERE student_id = ? AND institution = ? AND status = 'completed' 
      AND assessment_type IN ('Technical', 'HR') 
    ORDER BY completed_at DESC 
    LIMIT 3
");
$intStmt->execute([$username, $institution]);
$interviewsList = $intStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($interviewsList as $int) {
    $detailsDec = json_decode($int['details'], true) ?: [];
    $summary = 'No overall summary evaluation was generated.';
    $targetRole = '';
    
    if (isset($detailsDec['report'])) {
        $reportStr = $detailsDec['report'];
        // Parse "## Overall Summary:" or "##  Overall Summary:"
        if (preg_match('/##\s*Overall Summary:\s*(.*?)(?=\n##|$)/s', $reportStr, $matches)) {
            $summary = trim($matches[1]);
        } else {
            // strip markdown headers
            $cleaned = preg_replace('/^#+\s+/m', '', $reportStr);
            $summary = substr($cleaned, 0, 300) . '...';
        }
    }
    
    if (isset($detailsDec['role'])) {
        $targetRole = $detailsDec['role'];
    }
    
    $interviewFeedbacks[] = [
        'type' => $int['assessment_type'],
        'company' => $int['company_name'],
        'score' => $int['score'],
        'role' => $targetRole,
        'summary' => $summary,
        'date' => date('M d, Y', strtotime($int['completed_at']))
    ];
}

// 6. Aggregate assessments metrics
$assessStmt = $db->prepare("
    SELECT assessment_type, COUNT(*) as completed_count, AVG(score) as average_score 
    FROM unified_ai_assessments 
    WHERE student_id = ? AND institution = ? AND status = 'completed' 
    GROUP BY assessment_type
");
$assessStmt->execute([$username, $institution]);
$assessments = $assessStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Total assessments count and average score
$totalAssessmentsCompleted = 0;
$avgAssessmentScore = 0;
$totalAssessScoreSum = 0;
foreach ($assessments as $a) {
    $totalAssessmentsCompleted += $a['completed_count'];
    $totalAssessScoreSum += ($a['average_score'] * $a['completed_count']);
}
if ($totalAssessmentsCompleted > 0) {
    $avgAssessmentScore = round($totalAssessScoreSum / $totalAssessmentsCompleted, 1);
}

// 7. Aggregate daily challenge metrics
$challengeStmt = $db->prepare("
    SELECT COUNT(*) FROM daily_micro_challenges 
    WHERE student_id = ? AND institution = ? AND status = 'completed'
");
$challengeStmt->execute([$username, $institution]);
$dailyChallengesCompleted = $challengeStmt->fetchColumn() ?: 0;

// 8. Check if resume PDF exists
$resumeFilePath = UPLOADS_PATH . '/resumes/Student_Resumes/' . strtoupper($username) . '_Resume.pdf';
$hasResumeFile = file_exists($resumeFilePath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Talent Showcase - <?php echo htmlspecialchars($studentName); ?></title>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #4a0000;
            --accent-gold: #D4AF37;
            --light-gold: #f4e4bc;
            --white: #ffffff;
            --bg-light: #f5f7fa;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --glass: rgba(255, 255, 255, 0.95);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-lg: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            --border-color: #e2e8f0;
            --success-color: #10b981;
            --success-bg: #ecfdf5;
            --success-border: #a7f3d0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-main);
            line-height: 1.6;
            min-height: 100vh;
            padding-top: 72px; /* Navbar height offset */
            background-image:
                radial-gradient(at 0% 0%, rgba(128, 0, 0, 0.02) 0, transparent 45%),
                radial-gradient(at 100% 0%, rgba(212, 175, 55, 0.02) 0, transparent 45%);
        }

        .showcase-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem;
        }

        /* Top Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1.25rem;
        }

        .page-title h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-top: 4px;
        }

        /* Layout Grid */
        .showcase-grid {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 2rem;
        }

        @media (max-width: 1024px) {
            .showcase-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Left Column / Card */
        .profile-side-card {
            background: var(--white);
            border-radius: 24px;
            padding: 2.25rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            height: fit-content;
        }

        .profile-avatar-wrapper {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid var(--white);
            box-shadow: 0 0 0 2px var(--accent-gold);
            overflow: hidden;
            background: #f1f5f9;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-avatar-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar-wrapper i {
            font-size: 3.5rem;
            color: var(--text-muted);
        }

        .student-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 0.25rem;
        }

        .student-usn {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }

        .ready-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1.5rem;
        }

        .ready-badge.ready {
            background: var(--success-bg);
            color: var(--success-color);
            border: 1px solid var(--success-border);
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.1);
        }

        .ready-badge.not-ready {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .meta-list {
            width: 100%;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 0;
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-align: left;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.88rem;
        }

        .meta-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .meta-val {
            font-weight: 700;
            color: var(--text-main);
        }

        /* Action Buttons */
        .side-action-btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            margin-bottom: 10px;
        }

        .btn-maroon {
            background: var(--primary-maroon);
            color: var(--white);
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.15);
        }

        .btn-maroon:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(128, 0, 0, 0.2);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-maroon);
            border: 1px solid var(--primary-maroon);
        }

        .btn-outline:hover {
            background: rgba(128, 0, 0, 0.04);
        }

        .btn-disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Right Column / Content cards */
        .showcase-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .content-card {
            background: var(--white);
            border-radius: 24px;
            padding: 2.25rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .card-header i {
            font-size: 1.35rem;
            color: var(--primary-maroon);
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--primary-dark);
        }

        /* AI Overview Section */
        .ai-overview-block {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .predicted-role-pill {
            background: linear-gradient(135deg, rgba(128, 0, 0, 0.05) 0%, rgba(212, 175, 55, 0.05) 100%);
            border: 1px solid rgba(128, 0, 0, 0.1);
            padding: 1.25rem 1.75rem;
            border-radius: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .predicted-role-label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .predicted-role-value {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary-maroon);
        }

        .confidence-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--white);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            border: 1px solid var(--border-color);
        }

        .confidence-indicator i {
            color: var(--accent-gold);
        }

        .ai-quote-box {
            font-size: 0.95rem;
            line-height: 1.7;
            color: #334155;
            background: #f8fafc;
            border-left: 4px solid var(--accent-gold);
            padding: 1.25rem 1.5rem;
            border-radius: 0 16px 16px 0;
            font-style: italic;
        }

        .interest-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 0.5rem;
        }

        .interest-tag {
            background: #f1f5f9;
            color: #475569;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 50px;
            border: 1px solid #e2e8f0;
        }

        /* Stats Grid */
        .stats-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .metric-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .metric-card .num {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--primary-maroon);
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .metric-card .label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Mastery Lists */
        .mastery-split-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .mastery-split-grid {
                grid-template-columns: 1fr;
            }
        }

        .mastery-column h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-left: 3px solid var(--accent-gold);
            padding-left: 8px;
        }

        .mastery-bar-container {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .mastery-bar-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .mastery-bar-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .mastery-bar-name {
            color: var(--text-main);
        }

        .mastery-bar-pct {
            color: var(--primary-maroon);
        }

        .mastery-bar-outer {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 50px;
            overflow: hidden;
        }

        .mastery-bar-inner {
            height: 100%;
            border-radius: 50px;
            background: linear-gradient(90deg, var(--primary-maroon) 0%, var(--accent-gold) 100%);
            transition: width 0.5s ease-out;
        }

        /* Portfolio Cards */
        .portfolio-timeline {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .portfolio-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.5rem;
            position: relative;
            transition: all 0.25s;
        }

        .portfolio-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: rgba(128, 0, 0, 0.15);
        }

        .portfolio-card .type-badge {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 0.68rem;
            font-weight: 700;
            background: #e2e8f0;
            color: #475569;
            padding: 4px 10px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .portfolio-card.project .type-badge {
            background: #dbeafe;
            color: #1e40af;
        }

        .portfolio-card.certification .type-badge {
            background: #fef3c7;
            color: #92400e;
        }

        .portfolio-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 4px;
            padding-right: 80px; /* space for badge */
        }

        .portfolio-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .portfolio-desc {
            font-size: 0.88rem;
            color: #475569;
            line-height: 1.6;
        }

        .portfolio-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--primary-maroon);
            text-decoration: none;
            margin-top: 12px;
            transition: color 0.15s;
        }

        .portfolio-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .empty-portfolio-message {
            text-align: center;
            padding: 2.5rem 0;
            color: var(--text-muted);
        }

        .empty-portfolio-message i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="showcase-container">

    <!-- Top Header -->
    <div class="page-header">
        <div class="page-title">
            <h1><i class="fas fa-id-card"></i> AI Talent Showcase</h1>
            <p>Your verified academic, performance, and professional profile showcase</p>
        </div>
        <div>
            <a href="dashboard.php" class="side-action-btn btn-outline" style="width: auto; padding: 10px 20px; margin-bottom: 0;">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Layout Grid -->
    <div class="showcase-grid">

        <!-- Left Column: Summary Card -->
        <div class="profile-side-card">
            <!-- Avatar -->
            <div class="profile-avatar-wrapper">
                <?php if ($profilePhoto): ?>
                    <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="<?php echo htmlspecialchars($studentName); ?>">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>

            <!-- Basic Info -->
            <h2 class="student-title"><?php echo htmlspecialchars($studentName); ?></h2>
            <div class="student-usn"><?php echo htmlspecialchars($studentUsn); ?></div>

            <!-- Placement Badge -->
            <?php if ($isPlacementReady): ?>
                <div class="ready-badge ready">
                    <i class="fas fa-check-circle"></i> Placement Pool Active
                </div>
            <?php else: ?>
                <div class="ready-badge not-ready">
                    <i class="fas fa-hourglass-half"></i> Under Review
                </div>
            <?php endif; ?>

            <!-- Metadata List -->
            <div class="meta-list">
                <div class="meta-item">
                    <span class="meta-label">Course</span>
                    <span class="meta-val"><?php echo htmlspecialchars($studentCourse); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Specialization</span>
                    <span class="meta-val"><?php echo htmlspecialchars($studentDept); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Semester</span>
                    <span class="meta-val"><?php echo htmlspecialchars($studentSem); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Academic CGPA</span>
                    <span class="meta-val"><?php echo htmlspecialchars($studentCgpa); ?></span>
                </div>
            </div>

            <!-- Actions -->
            <?php if ($hasResumeFile): ?>
                <a href="view_resume.php?usn=<?php echo urlencode($studentUsn); ?>" target="_blank" class="side-action-btn btn-maroon">
                    <i class="fas fa-file-pdf"></i> View Resume PDF
                </a>
            <?php else: ?>
                <button class="side-action-btn btn-disabled">
                    <i class="fas fa-file-excel"></i> No Resume Uploaded
                </button>
            <?php endif; ?>
            
            <a href="portfolio.php" class="side-action-btn btn-outline">
                <i class="fas fa-pencil-alt"></i> Edit Portfolio
            </a>
        </div>

        <!-- Right Column: AI Insights & Performances -->
        <div class="showcase-content">

            <!-- Card 1: AI Career Assessment -->
            <div class="content-card">
                <div class="card-header">
                    <i class="fas fa-brain"></i>
                    <h2>AI Career Intelligence</h2>
                </div>
                
                <div class="ai-overview-block">
                    <?php if (!empty($aiProfile['predicted_role'])): ?>
                        <div class="predicted-role-pill">
                            <div>
                                <div class="predicted-role-label">Inferred Placement Target</div>
                                <div class="predicted-role-value"><?php echo htmlspecialchars($aiProfile['predicted_role']); ?></div>
                            </div>
                            <?php if (!empty($aiProfile['confidence_score'])): ?>
                                <div class="confidence-indicator">
                                    <i class="fas fa-shield-halved"></i> Confidence: <?php echo round($aiProfile['confidence_score'] * 100); ?>%
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="predicted-role-pill">
                            <div>
                                <div class="predicted-role-label">Inferred Placement Target</div>
                                <div class="predicted-role-value" style="font-size: 1.1rem; color: var(--text-muted);">Awaiting Profiling Assessment</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($aiProfile['ai_summary'])): ?>
                        <div class="ai-quote-box">
                            "<?php echo htmlspecialchars($aiProfile['ai_summary']); ?>"
                        </div>
                    <?php else: ?>
                        <div class="ai-quote-box" style="color: var(--text-muted);">
                            No dynamic career analysis summary available yet. Participate in daily challenges and update your portfolio to trigger AI analysis.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($interests)): ?>
                        <div>
                            <h4 style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; letter-spacing: 0.5px;">Detected Focus Areas</h4>
                            <div class="interest-tags">
                                <?php foreach ($interests as $interest): ?>
                                    <span class="interest-tag"><?php echo htmlspecialchars($interest); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 2: Performance & Activity Metrics -->
            <div class="content-card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i>
                    <h2>Portal Activity & Performance</h2>
                </div>
                
                <div class="stats-summary-grid">
                    <div class="metric-card">
                        <span class="num"><?php echo $totalAssessmentsCompleted; ?></span>
                        <span class="label">Assessments Done</span>
                    </div>
                    <div class="metric-card">
                        <span class="num"><?php echo $avgAssessmentScore > 0 ? $avgAssessmentScore . '%' : 'N/A'; ?></span>
                        <span class="label">Avg Score</span>
                    </div>
                    <div class="metric-card">
                        <span class="num"><?php echo $dailyChallengesCompleted; ?></span>
                        <span class="label">Daily Challenges</span>
                    </div>
                </div>
            </div>

            <!-- Card 3: Skill & Topic Mastery -->
            <div class="content-card">
                <div class="card-header">
                    <i class="fas fa-graduation-cap"></i>
                    <h2>Verified Topic Mastery</h2>
                </div>

                <?php
                // Split mastery list into Technical and Aptitude/HR
                $techMasteries = [];
                $nonTechMasteries = [];
                foreach ($masteryList as $m) {
                    $cat = (string)($m['category'] ?? '');
                    if (strtolower($cat) === 'technical') {
                        $techMasteries[] = $m;
                    } else {
                        $nonTechMasteries[] = $m;
                    }
                }
                ?>

                <div class="mastery-split-grid">
                    <!-- Technical Mastery -->
                    <div class="mastery-column">
                        <h3>Core Domain Skills</h3>
                        <?php if (empty($techMasteries)): ?>
                            <p style="font-size: 0.85rem; color: var(--text-muted);">No technical mastery entries recorded yet.</p>
                        <?php else: ?>
                            <div class="mastery-bar-container">
                                <?php foreach (array_slice($techMasteries, 0, 6) as $m): ?>
                                    <div class="mastery-bar-item">
                                        <div class="mastery-bar-info">
                                            <span class="mastery-bar-name"><?php echo htmlspecialchars($m['topic_name']); ?></span>
                                            <span class="mastery-bar-pct"><?php echo round($m['mastery_level']); ?>%</span>
                                        </div>
                                        <div class="mastery-bar-outer">
                                            <div class="mastery-bar-inner" style="width: <?php echo round($m['mastery_level']); ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Aptitude & HR Mastery -->
                    <div class="mastery-column">
                        <h3>Aptitude & Communication</h3>
                        <?php if (empty($nonTechMasteries)): ?>
                            <p style="font-size: 0.85rem; color: var(--text-muted);">No aptitude/HR mastery entries recorded yet.</p>
                        <?php else: ?>
                            <div class="mastery-bar-container">
                                <?php foreach (array_slice($nonTechMasteries, 0, 6) as $m): ?>
                                    <div class="mastery-bar-item">
                                        <div class="mastery-bar-info">
                                            <span class="mastery-bar-name"><?php echo htmlspecialchars($m['topic_name']); ?></span>
                                            <span class="mastery-bar-pct"><?php echo round($m['mastery_level']); ?>%</span>
                                        </div>
                                        <div class="mastery-bar-outer">
                                            <div class="mastery-bar-inner" style="width: <?php echo round($m['mastery_level']); ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Card 4: AI-Verified Skill Badges -->
            <div class="content-card">
                <div class="card-header">
                    <i class="fas fa-certificate"></i>
                    <h2>AI-Verified Skill Badges</h2>
                </div>

                <?php if (empty($verifiedSkills)): ?>
                    <div class="empty-portfolio-message">
                        <i class="fas fa-award"></i>
                        <p>No verified skill badges earned yet.</p>
                        <p style="font-size: 0.82rem; margin-top: 4px; color: var(--text-muted);">Pass an AI Skill Verification quiz to earn credentials that display here.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                        <?php foreach ($verifiedSkills as $skill): ?>
                            <div style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 1.5px solid #059669; border-radius: 50px; padding: 8px 18px; font-weight: 700; font-size: 0.85rem; color: #065f46; box-shadow: 0 4px 6px rgba(5, 150, 105, 0.08);">
                                <i class="fas fa-check-circle" style="color: #059669; font-size: 0.95rem;"></i>
                                <?php echo htmlspecialchars($skill); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card 5: AI Interview Performance Evaluations -->
            <div class="content-card">
                <div class="card-header">
                    <i class="fas fa-comments-dollar" style="color: var(--primary-maroon);"></i>
                    <h2>AI Interview Insights & Feedbacks</h2>
                </div>

                <div class="portfolio-timeline">
                    <?php if (empty($interviewFeedbacks)): ?>
                        <div class="empty-portfolio-message">
                            <i class="fas fa-clipboard-question"></i>
                            <p>No completed AI Mock Interviews recorded yet.</p>
                            <p style="font-size: 0.82rem; margin-top: 4px; color: var(--text-muted);">Complete your first Technical or HR mock interview on the portal to populate these reports.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($interviewFeedbacks as $fb): ?>
                            <div class="portfolio-card" style="border-left: 4px solid var(--accent-gold);">
                                <span class="type-badge" style="background: var(--light-gold); color: var(--primary-dark); font-weight: 700;">
                                    <?php echo htmlspecialchars($fb['type']); ?> (<?php echo $fb['score']; ?>%)
                                </span>
                                <h3 class="portfolio-title" style="padding-right: 120px;">
                                    Targeting: <?php echo htmlspecialchars($fb['role'] ?: 'General Industry'); ?>
                                </h3>
                                <div class="portfolio-sub">
                                    <i class="fas fa-building"></i> Mock Company: <?php echo htmlspecialchars($fb['company'] ?: 'General'); ?> &bull; 
                                    <i class="fas fa-calendar-alt"></i> <?php echo $fb['date']; ?>
                                </div>
                                <div class="ai-quote-box" style="font-size: 0.88rem; line-height: 1.6; margin-top: 10px; padding: 10px 14px; background: #f8fafc; border-left: 3px solid var(--primary-maroon);">
                                    <?php echo htmlspecialchars($fb['summary']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

</div>

</body>
</html>
