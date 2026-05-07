<?php
/**
 * Student - Career Advisor
 * AI-powered career guidance and roadmap generation
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();

// Load models
require_once __DIR__ . '/../../src/Models/CareerRoadmap.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

$roadmapModel = new CareerRoadmap();
$studentModel = new StudentProfile();

use App\Helpers\SessionFilterHelper;

// Get student profile first to determine correct student ID
$studentProfile = $studentModel->getProfile($userId);
$institution = $studentProfile['institution'] ?? INSTITUTION_GMU;

// Use the same logic as career_handler.php
if ($institution === INSTITUTION_GMIT) {
    // Prioritize: enquiry_no (id) > usn > student_id (excluding 0)
    if (!empty($studentProfile['id']) && $studentProfile['id'] != 0) {
        $roadmapStudentId = $studentProfile['id'];
    } else if (!empty($studentProfile['usn'])) {
        $roadmapStudentId = $studentProfile['usn'];
    } else if (!empty($studentProfile['student_id']) && $studentProfile['student_id'] != '0' && $studentProfile['student_id'] != 0) {
        $roadmapStudentId = $studentProfile['student_id'];
    } else {
        $roadmapStudentId = $userId;
    }
} else {
    // GMU: Use SL_NO (userId)
    $roadmapStudentId = $userId;
}

// Handle POST from creation or dashboard
if (isPost() && isset($_POST['roadmap_id'])) {
    SessionFilterHelper::setFilters('career_advisor', [
        'roadmap_id' => $_POST['roadmap_id'] ?? 0
    ]);
    header("Location: career_advisor.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('career_advisor');
$requestedRoadmapId = $filters['roadmap_id'] ?? null;
$requestedRoadmap = null;
if ($requestedRoadmapId) {
    $requestedRoadmap = $roadmapModel->getRoadmapById($requestedRoadmapId, $roadmapStudentId);
}

// Get active roadmap
$activeRoadmap = $requestedRoadmap ?: $roadmapModel->getActiveRoadmap($roadmapStudentId);
$hasActiveRoadmap = !empty($activeRoadmap);

// Get roadmap stats if exists
$stats = null;
if ($hasActiveRoadmap) {
    $stats = $roadmapModel->getRoadmapStats($activeRoadmap['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Advisor - Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #800000;
            --primary-light: #a00000;
            --secondary: #FFD700;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.3);
            --text-main: #2d3436;
            --text-soft: #636e72;
            --card-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
            color: var(--text-main);
            min-height: 100vh;
            padding-top: 72px; /* Navbar height offset */
        }

        .career-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary) 0%, #600000 100%);
            color: white;
            padding: 80px 40px;
            border-radius: 30px;
            margin-bottom: 50px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(128, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .hero-section::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .hero-section h1 {
            font-size: 48px;
            margin-bottom: 20px;
            font-weight: 800;
            letter-spacing: -1px;
        }
        
        .hero-section p {
            font-size: 20px;
            opacity: 0.9;
            max-width: 750px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .roadmap-card {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            margin-bottom: 40px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        
        .roadmap-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .roadmap-title {
            font-size: 32px;
            color: var(--text-main);
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .progress-badge {
            background: linear-gradient(135deg, var(--primary) 0%, #a00000 100%);
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 4px 15px rgba(128,0,0,0.2);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-box {
            background: rgba(255,255,255,0.5);
            padding: 25px;
            border-radius: 20px;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.03);
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            background: #fff;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        
        .stat-box .number {
            font-size: 36px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .stat-box .label {
            color: var(--text-soft);
            font-size: 15px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .cta-section {
            text-align: center;
            padding: 40px 20px;
        }
        
        .cta-button {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 20px 45px;
            border-radius: 20px;
            font-size: 18px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 8px 25px rgba(128,0,0,0.25);
        }
        
        .cta-button:hover {
            background: var(--primary-light);
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(128,0,0,0.35);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 60px 0;
        }
        
        .feature-card {
            background: white;
            padding: 40px;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            border-color: rgba(128,0,0,0.1);
        }
        
        .feature-icon {
            font-size: 56px;
            margin-bottom: 25px;
            display: block;
        }
        
        .feature-card h3 {
            font-size: 22px;
            color: var(--text-main);
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .feature-card p {
            color: var(--text-soft);
            line-height: 1.7;
            font-size: 16px;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 16px 32px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 15px rgba(128,0,0,0.2);
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(128,0,0,0.3);
        }
        
        .btn-secondary {
            background: rgba(0,0,0,0.05);
            color: var(--text-main);
        }
        
        .btn-secondary:hover {
            background: rgba(0,0,0,0.1);
        }

        .tag-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }

    </style>
</head>
<body>

<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="career-container">
    <?php if (!$hasActiveRoadmap): ?>
        <!-- No Active Roadmap - Show CTA -->
        <div class="hero-section">
            <h1>🎯 AI Career Advisor</h1>
            <p>Get a personalized career roadmap powered by AI. Tell us your dream role, and we'll create a step-by-step learning path with curated resources from YouTube and top educational sites.</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🤖</div>
                <h3>AI-Powered Roadmap</h3>
                <p>Get a personalized learning path generated by advanced AI based on your goals and current skills.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📚</div>
                <h3>Curated Resources</h3>
                <p>Access hand-picked YouTube tutorials and downloadable study materials for each skill.</p>
            </div>
          
            <div class="feature-card">
                <div class="feature-icon">🎓</div>
                <h3>Phase-Based Learning</h3>
                <p>Follow a structured learning path divided into clear phases with specific goals.</p>
            </div>
        </div>
        
        <div class="cta-section">
            <h2 style="margin-bottom: 20px;">Ready to Plan Your Career?</h2>
            <a href="career_goal_form.php" class="cta-button">🚀 Create My Roadmap</a>
        </div>
        
    <?php else: ?>
        <!-- Active Roadmap Exists -->
        <div class="roadmap-card">
            <div class="roadmap-header">
                <div>
                    <h1 class="roadmap-title">Your Career Roadmap</h1>
                    <p style="color: #666; margin: 5px 0 0 0;">
                        <strong id="js-target-role"><?php echo htmlspecialchars($activeRoadmap['target_role']); ?></strong>
                        <?php if ($activeRoadmap['target_company_type']): ?>
                            at <span id="js-target-company"><?php echo htmlspecialchars($activeRoadmap['target_company_type']); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="progress-badge" id="js-progress-badge">
                    <?php echo $activeRoadmap['progress_percentage']; ?>% Complete
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="number" id="js-videos-stat"><?php echo $stats['videos_completed'] ?? 0; ?>/<?php echo $stats['total_videos'] ?? 0; ?></div>
                    <div class="label">Videos Completed</div>
                </div>
                <div class="stat-box">
                    <div class="number" id="js-materials-stat"><?php echo $stats['materials_downloaded'] ?? 0; ?>/<?php echo $stats['total_materials'] ?? 0; ?></div>
                    <div class="label">Materials Downloaded</div>
                </div>
                <div class="stat-box">
                    <div class="number" id="js-skill-progress"><?php echo round($stats['avg_skill_progress'] ?? 0); ?>%</div>
                    <div class="label">Avg Skill Progress</div>
                </div>
                <div class="stat-box">
                    <div class="number" id="js-phases-count"><?php echo count($activeRoadmap['roadmap_data']['phases'] ?? []); ?></div>
                    <div class="label">Learning Phases</div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button onclick="navigatePost('career_roadmap.php', {id: '<?php echo $activeRoadmap['id']; ?>'})" class="btn btn-primary" style="border:none; cursor:pointer;">
                    📋 View Full Roadmap
                </button>
                <button onclick="navigatePost('career_resources.php', {id: '<?php echo $activeRoadmap['id']; ?>'})" class="btn btn-primary" style="border:none; cursor:pointer;">
                    📚 Browse Resources
                </button>
                <a href="career_goal_form.php" class="btn btn-secondary">
                    ✨ Create New Roadmap
                </a>
            </div>
        </div>
        
        <!-- Quick Overview -->
        <div class="roadmap-card">
            <h3 style="margin-bottom: 20px;">📖 Roadmap Overview</h3>
            <p style="color: #666; line-height: 1.8; margin-bottom: 20px;" id="js-overview">
                <?php echo htmlspecialchars($activeRoadmap['roadmap_data']['overview'] ?? 'Your personalized career roadmap'); ?>
            </p>
            <p style="color: #800000; font-weight: 600;" id="js-timeline">
                ⏱️ Estimated Timeline: <?php echo htmlspecialchars($activeRoadmap['roadmap_data']['timeline'] ?? 'N/A'); ?>
            </p>

            <div style="margin-top: 22px; border-top: 1px solid #f0f0f0; padding-top: 18px;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 18px;">
                    <div>
                        <div style="font-weight: 700; color:#333;">Current Skills</div>
                        <div class="tag-row" id="js-current-skills">
                            <?php if (!empty($activeRoadmap['current_skills'])): ?>
                                <?php foreach ($activeRoadmap['current_skills'] as $s): ?>
                                    <span class="tag"><?php echo htmlspecialchars((string)$s); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="tag empty">No skills added</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                       
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($hasActiveRoadmap): ?>
<script>
// Optional: refresh the dashboard with "real" saved data via API (useful right after creation).
(function () {
    const params = new URLSearchParams(window.location.search);
    const roadmapId = params.get('roadmap_id');

    const payload = roadmapId
        ? { action: 'get_roadmap', roadmap_id: roadmapId }
        : { action: 'get_active_roadmap' };

    fetch('career_handler', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (!data || !data.success) return;
        const roadmap = data.roadmap || (data.has_active_roadmap ? data.roadmap : null);
        const stats = data.stats || null;
        if (!roadmap) return;

        const setText = (id, text) => {
            const el = document.getElementById(id);
            if (el && typeof text === 'string') el.textContent = text;
        };

        setText('js-target-role', roadmap.target_role || '');
        if (roadmap.target_company_type) setText('js-target-company', roadmap.target_company_type);
        setText('js-progress-badge', (roadmap.progress_percentage ?? 0) + '% Complete');

        if (stats) {
            setText('js-videos-stat', (stats.videos_completed ?? 0) + '/' + (stats.total_videos ?? 0));
            setText('js-materials-stat', (stats.materials_downloaded ?? 0) + '/' + (stats.total_materials ?? 0));
            setText('js-skill-progress', Math.round(stats.avg_skill_progress ?? 0) + '%');
        }

        const phasesCount = Array.isArray(roadmap.roadmap_data?.phases) ? roadmap.roadmap_data.phases.length : 0;
        setText('js-phases-count', String(phasesCount));

        if (roadmap.roadmap_data?.overview) setText('js-overview', roadmap.roadmap_data.overview);
        if (roadmap.roadmap_data?.timeline) setText('js-timeline', '⏱️ Estimated Timeline: ' + roadmap.roadmap_data.timeline);

        const renderTags = (containerId, items, emptyText) => {
            const el = document.getElementById(containerId);
            if (!el) return;
            el.innerHTML = '';
            if (Array.isArray(items) && items.length) {
                items.forEach(v => {
                    const span = document.createElement('span');
                    span.className = 'tag';
                    span.textContent = String(v);
                    el.appendChild(span);
                });
            } else {
                const span = document.createElement('span');
                span.className = 'tag empty';
                span.textContent = emptyText;
                el.appendChild(span);
            }
        };

        renderTags('js-current-skills', roadmap.current_skills, 'No skills added');
        renderTags('js-achievements', roadmap.achievements, 'No achievements added');
    })
    .catch(() => {});
})();
    /**
     * Universal POST Navigator for Clean URLs
     */
    function navigatePost(url, data) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        for (const key in data) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = data[key];
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
    }
</script>
<?php endif; ?>

</body>
</html>

