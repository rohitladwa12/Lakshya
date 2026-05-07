<?php
/**
 * Career Roadmap Display
 * Shows detailed roadmap with phases, skills, and resources
 */

require_once __DIR__ . '/../../config/bootstrap.php';
use App\Helpers\SessionFilterHelper;

requireRole(ROLE_STUDENT);

$userId = getUserId();

// Load models
require_once __DIR__ . '/../../src/Models/CareerRoadmap.php';
require_once __DIR__ . '/../../src/Models/CareerResource.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

$roadmapModel = new CareerRoadmap();
$resourceModel = new CareerResource();
$studentModel = new StudentProfile();

// Get student profile and determine the correct student ID for roadmap
$studentProfile = $studentModel->getProfile($userId);
$institution = $studentProfile['institution'] ?? INSTITUTION_GMU;

// Resolve student ID for database queries
if ($institution === INSTITUTION_GMIT) {
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
    $roadmapStudentId = $userId;
}

// Handle POST from advisor or dashboard
if (isPost() && isset($_POST['id'])) {
    SessionFilterHelper::setFilters('career_roadmap', ['id' => $_POST['id']]);
    header("Location: career_roadmap.php");
    exit;
}

// Load from GET if present (to support direct links)
if (isset($_GET['id'])) {
    SessionFilterHelper::setFilters('career_roadmap', ['id' => $_GET['id']]);
}

$filters = SessionFilterHelper::getFilters('career_roadmap');
$roadmapId = $filters['id'] ?? null;

// Auto-resolve active roadmap if none selected
if (!$roadmapId) {
    $activeRoadmap = $roadmapModel->getActiveRoadmap($roadmapStudentId);
    if ($activeRoadmap) {
        $roadmapId = $activeRoadmap['id'];
        SessionFilterHelper::setFilters('career_roadmap', ['id' => $roadmapId]);
    }
}

if (!$roadmapId) {
    header('Location: career_advisor.php');
    exit;
}

// Get roadmap
$roadmap = $roadmapModel->getRoadmapById($roadmapId, $roadmapStudentId);

if (!$roadmap) {
    // Clear invalid roadmap ID
    SessionFilterHelper::setFilters('career_roadmap', ['id' => null]);
    header('Location: career_advisor.php');
    exit;
}

$roadmapData = $roadmap['roadmap_data'];
$stats = $roadmapModel->getRoadmapStats($roadmapId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Roadmap - Student Portal</title>
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
            min-height: 100vh;
            color: var(--text-main);
            padding-top: 72px; /* Navbar height offset */
        }

        .roadmap-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .roadmap-header {
            background: linear-gradient(135deg, var(--primary) 0%, #600000 100%);
            color: white;
            padding: 60px 50px;
            border-radius: 30px;
            margin-bottom: 40px;
            box-shadow: 0 20px 40px rgba(128,0,0,0.2);
            position: relative;
            overflow: hidden;
        }

        .roadmap-header::after {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .roadmap-header h1 {
            font-size: 42px;
            margin-bottom: 12px;
            font-weight: 800;
            letter-spacing: -1px;
        }
        
        .roadmap-header .subtitle {
            font-size: 19px;
            opacity: 0.95;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .roadmap-header .timeline {
            margin-top: 20px;
            font-size: 16px;
            background: rgba(255,255,255,0.15);
            display: inline-flex;
            padding: 10px 20px;
            border-radius: 50px;
            backdrop-filter: blur(5px);
            font-weight: 600;
        }
        
        .overview-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            padding: 40px;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            margin-bottom: 40px;
        }
        
        .overview-card h2 {
            color: var(--primary);
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .overview-card p {
            color: var(--text-soft);
            line-height: 1.8;
            font-size: 17px;
        }
        
        .phases-section h2 {
            font-size: 28px;
            margin-bottom: 30px;
            color: var(--text-main);
            font-weight: 800;
            padding-left: 10px;
        }
        
        .phase-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
            position: relative;
        }

        .phase-card:hover {
            transform: translateY(-5px);
        }
        
        .phase-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
        }
        
        .phase-number {
            background: linear-gradient(135deg, var(--primary) 0%, #a00000 100%);
            color: white;
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            box-shadow: 0 8px 16px rgba(128,0,0,0.2);
        }
        
        .phase-title {
            flex: 1;
            margin-left: 25px;
        }
        
        .phase-title h3 {
            color: var(--text-main);
            font-size: 24px;
            margin-bottom: 6px;
            font-weight: 700;
        }
        
        .phase-duration {
            color: var(--primary);
            font-weight: 700;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }
        
        .skill-badge {
            background: white;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 14px;
            color: var(--text-main);
            font-weight: 600;
            border: 1px solid #eee;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        
        .milestones-list {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }
        
        .milestones-list li {
            padding: 12px 0;
            padding-left: 35px;
            position: relative;
            color: var(--text-soft);
            font-size: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.02);
        }

        .milestones-list li:last-child { border-bottom: none; }
        
        .milestones-list li:before {
            content: "✦";
            position: absolute;
            left: 0;
            color: var(--primary);
            font-weight: 800;
        }
        
        .skills-section {
            background: var(--glass-bg);
            padding: 40px;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            margin-bottom: 40px;
        }
        
        .skill-item {
            padding: 25px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: background 0.2s;
            border-radius: 16px;
        }

        .skill-item:hover { background: rgba(0,0,0,0.01); }
        
        .skill-item:last-child { border-bottom: none; }
        
        .skill-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .skill-name {
            font-size: 19px;
            font-weight: 700;
            color: var(--text-main);
        }
        
        .priority-badge {
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .priority-critical { background: #fff1f1; color: #d32f2f; }
        .priority-important { background: #fff9e6; color: #f57c00; }
        .priority-nice { background: #f1f9f1; color: #388e3c; }
        
        .skill-details {
            color: var(--text-soft);
            font-size: 15px;
            line-height: 1.6;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 40px;
            justify-content: center;
        }
        
        .btn {
            padding: 18px 36px;
            border-radius: 18px;
            text-decoration: none;
            font-weight: 700;
            font-size: 17px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            gap: 12px;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 10px 25px rgba(128,0,0,0.2);
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(128,0,0,0.3);
        }

        .btn-outline {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-text {
            background: rgba(128,0,0,0.05);
            border: none;
            color: var(--primary);
            font-weight: 700;
            cursor: pointer;
            padding: 12px 25px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .btn-text:hover { background: rgba(128,0,0,0.1); }

        .resources-container {
            background: #fff;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 20px;
            padding: 30px;
            margin-top: 20px;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.02);
        }

        .loading-spinner {
            text-align: center;
            padding: 30px;
            color: var(--text-soft);
            font-weight: 500;
        }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="roadmap-container">
    <div class="roadmap-header">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1;">
                <h1><?php echo htmlspecialchars($roadmap['target_role']); ?></h1>
                <div class="subtitle">
                    <?php if ($roadmap['target_company_type']): ?>
                        <?php echo htmlspecialchars($roadmap['target_company_type']); ?> •
                    <?php endif; ?>
                    <?php echo htmlspecialchars($roadmap['target_industry']); ?> •
                    <?php echo htmlspecialchars($roadmap['experience_level']); ?> Level
                </div>
            </div>
            <a href="career_goal_form.php" class="btn btn-outline" style="background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.3); padding: 12px 20px; font-size: 14px;">
                🔄 Switch Career Path
            </a>
        </div>
        <div class="timeline">
            ⏱️ Estimated Timeline: <?php echo htmlspecialchars($roadmapData['timeline'] ?? 'N/A'); ?>
        </div>
    </div>
    
    <div class="overview-card">
        <h2>📖 Overview</h2>
        <p><?php echo nl2br(htmlspecialchars($roadmapData['overview'] ?? '')); ?></p>
    </div>
    
    <div class="phases-section">
        <h2 style="margin-bottom: 20px;">🎯 Learning Phases</h2>
        
        <?php foreach ($roadmapData['phases'] ?? [] as $phase): ?>
            <div class="phase-card">
                <div class="phase-header">
                    <div class="phase-number"><?php echo $phase['phase_number']; ?></div>
                    <div class="phase-title">
                        <h3><?php echo htmlspecialchars($phase['title']); ?></h3>
                        <div class="phase-duration">⏱️ <?php echo htmlspecialchars($phase['duration']); ?></div>
                    </div>
                </div>
                
                <?php if (!empty($phase['description'])): ?>
                    <p style="color: #666; margin-bottom: 15px;">
                        <?php echo htmlspecialchars($phase['description']); ?>
                    </p>
                <?php endif; ?>
                
                <div>
                    <strong style="color: #333;">Skills to Learn:</strong>
                    <div class="skills-list">
                        <?php foreach ($phase['skills'] ?? [] as $skill): ?>
                            <span class="skill-badge"><?php echo htmlspecialchars($skill); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if (!empty($phase['milestones'])): ?>
                    <div style="margin-top: 15px;">
                        <strong style="color: #333;">Milestones:</strong>
                        <ul class="milestones-list">
                            <?php foreach ($phase['milestones'] as $milestone): ?>
                                <li><?php echo htmlspecialchars($milestone); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button class="btn-text" onclick="toggleResources(<?php echo $phase['phase_number']; ?>)">
                        📚 View Study Resources
                    </button>
                    
                    <div id="resources-<?php echo $phase['phase_number']; ?>" class="resources-container" style="display: none; margin-top: 15px;">
                        <div class="loading-spinner">Loading resources...</div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="skills-section">
        <h2 style="margin-bottom: 20px;">💡 Required Skills</h2>
        
        <?php foreach ($roadmapData['required_skills'] ?? [] as $skill): ?>
            <div class="skill-item">
                <div class="skill-header">
                    <div class="skill-name"><?php echo htmlspecialchars($skill['skill_name']); ?></div>
                    <div class="priority-badge priority-<?php echo strtolower(str_replace('-', '', $skill['priority'] ?? 'nice')); ?>">
                        <?php echo htmlspecialchars($skill['priority'] ?? 'Nice-to-have'); ?>
                    </div>
                </div>
                <div class="skill-details">
                    <div><strong>Category:</strong> <?php echo htmlspecialchars($skill['category'] ?? 'N/A'); ?></div>
                    <div><strong>Current Level:</strong> <?php echo htmlspecialchars($skill['current_level'] ?? 'None'); ?> → 
                         <strong>Target Level:</strong> <?php echo htmlspecialchars($skill['target_level'] ?? 'Intermediate'); ?></div>
                    <?php if (!empty($skill['why_important'])): ?>
                        <div style="margin-top: 8px;"><em><?php echo htmlspecialchars($skill['why_important']); ?></em></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="action-buttons">
        <button onclick="navigatePost('career_resources.php', {id: '<?php echo $roadmapId; ?>'})" class="btn btn-primary" style="border:none; cursor:pointer;">
            📚 Browse Learning Resources
        </button>
        <a href="career_goal_form.php" class="btn btn-outline">
            🔄 Switch Career Path
        </a>
        <a href="career_advisor.php" class="btn btn-primary">
            🏠 Back to Dashboard
        </a>
    </div>
</div>

<script>
function toggleResources(phaseNum) {
    const container = document.getElementById(`resources-${phaseNum}`);
    if (container.style.display === 'none') {
        container.style.display = 'block';
        if (!container.dataset.loaded) {
            loadResources(phaseNum);
        }
    } else {
        container.style.display = 'none';
    }
}

async function trackMaterialDownload(materialId, roadmapId, url) {
    try {
        await fetch('career_handler', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                action: 'mark_material_downloaded',
                material_id: materialId,
                roadmap_id: roadmapId
            })
        });
    } catch (e) {}
    window.open(url, '_blank');
}

async function markVideoCompleted(videoId, roadmapId, btn) {
    btn.disabled = true;
    btn.textContent = '...ing';
    
    try {
        const response = await fetch('career_handler', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                action: 'mark_video_completed',
                video_id: videoId,
                roadmap_id: roadmapId
            })
        });
        
        const result = await response.json();
        if (result.success) {
            btn.textContent = '✅ Done';
            btn.style.background = '#e8f5e9';
            btn.style.color = '#2e7d32';
            btn.classList.add('disabled');
        } else {
            btn.disabled = false;
            btn.textContent = 'Mark Done';
        }
    } catch (e) {
        btn.disabled = false;
        btn.textContent = 'Mark Done';
    }
}

async function loadResources(phaseNum) {
    const container = document.getElementById(`resources-${phaseNum}`);
    const roadmapId = <?php echo json_encode($roadmapId); ?>;
    
    try {
        const response = await fetch('career_handler', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                action: 'get_phase_resources',
                roadmap_id: roadmapId,
                phase_number: phaseNum
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            let html = '';
            
            // Videos
            if (result.videos && result.videos.length > 0) {
                html += '<h4 style="margin: 10px 0; color: #cc0000;">🎥 Recommended Videos</h4>';
                html += '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">';
                result.videos.forEach(video => {
                    const isDone = video.is_completed == 1;
                    html += `
                        <div style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: white; display: flex; flex-direction: column;">
                            <a href="https://www.youtube.com/watch?v=${video.video_id}" target="_blank" style="text-decoration: none; color: inherit;">
                                <img src="${video.thumbnail_url}" style="width: 100%; height: 140px; object-fit: cover;">
                                <div style="padding: 10px;">
                                    <div style="font-weight: 600; margin-bottom: 5px; font-size: 13px; line-height: 1.4; height: 36px; overflow: hidden;">${video.title}</div>
                                    <div style="font-size: 11px; color: #666;">${video.channel_name}</div>
                                </div>
                            </a>
                            <div style="padding: 0 10px 10px 10px; margin-top: auto;">
                                <button onclick="markVideoCompleted(${video.id}, ${roadmapId}, this)" 
                                    style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; background: ${isDone ? '#f1f8e9' : '#fff'}; color: ${isDone ? '#2e7d32' : '#333'}; font-size: 12px; cursor: pointer;"
                                    ${isDone ? 'disabled' : ''}>
                                    ${isDone ? '✅ Completed' : 'Mark as Done'}
                                </button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
            }
            
            // Materials
            if (result.materials && result.materials.length > 0) {
                html += '<h4 style="margin: 20px 0 10px; color: #2e7d32;">📖 Study Materials</h4>';
                html += '<ul style="list-style: none; padding: 0;">';
                result.materials.forEach(material => {
                    const isDl = material.is_downloaded == 1;
                    html += `
                        <li style="padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: white;">
                            <div>
                                <div style="font-weight: 600; font-size: 14px;">${material.title}</div>
                                <div style="font-size: 12px; color: #666;">${material.file_type} • ${material.difficulty_level} ${isDl ? ' • <span style="color: #2e7d32;">Downloaded</span>' : ''}</div>
                            </div>
                            <button onclick="trackMaterialDownload(${material.id}, ${roadmapId}, '${material.source_url}')" 
                                style="padding: 5px 12px; background: ${isDl ? '#f1f8e9' : '#eee'}; color: ${isDl ? '#2e7d32' : '#333'}; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                ${isDl ? 'Open Again' : 'Download'}
                            </button>
                        </li>
                    `;
                });
                html += '</ul>';
            }
            
            if (!html) {
                html = '<p style="color: #666; font-style: italic;">No specific resources found for this phase yet.</p>';
            }
            
            container.innerHTML = html;
            container.dataset.loaded = 'true';
        } else {
            container.innerHTML = `<div style="color: red;">Error: ${result.error}</div>`;
        }
    } catch (error) {
        console.error(error);
        container.innerHTML = `<div style="color: red;">Failed to load resources.</div>`;
    }
}
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
</body>
</html>

