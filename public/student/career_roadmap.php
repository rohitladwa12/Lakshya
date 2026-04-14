<?php
/**
 * Career Roadmap Display
 * Shows detailed roadmap with phases, skills, and resources
 */

require_once __DIR__ . '/../../config/bootstrap.php';
use App\Helpers\SessionFilterHelper;

requireRole(ROLE_STUDENT);

$userId = getUserId();

// Handle POST from advisor or dashboard
if (isPost() && isset($_POST['id'])) {
    SessionFilterHelper::setFilters('career_roadmap', [
        'id' => $_POST['id'] ?? 0
    ]);
    header("Location: career_roadmap.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('career_roadmap');
$roadmapId = $filters['id'] ?? null;

if (!$roadmapId) {
    header('Location: career_advisor.php');
    exit;
}

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

// Get roadmap
$roadmap = $roadmapModel->getRoadmapById($roadmapId, $roadmapStudentId);

if (!$roadmap) {
    header('Location: career_advisor.php');
    exit;
}

$roadmapData = $roadmap['roadmap_data'];
$stats = $roadmapModel->getRoadmapStats($roadmapId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Roadmap - Student Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .navbar { background: linear-gradient(135deg, #800000 0%, #a00000 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h1 { font-size: 24px; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; transition: opacity 0.3s; }
        .navbar a:hover { opacity: 0.8; }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<?php
?>

<style>
    .roadmap-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 30px 20px;
    }
    
    .roadmap-header {
        background: linear-gradient(135deg, #800000 0%, #a00000 100%);
        color: white;
        padding: 40px;
        border-radius: 12px;
        margin-bottom: 30px;
    }
    
    .roadmap-header h1 {
        font-size: 36px;
        margin-bottom: 10px;
    }
    
    .roadmap-header .subtitle {
        font-size: 18px;
        opacity: 0.9;
    }
    
    .roadmap-header .timeline {
        margin-top: 15px;
        font-size: 16px;
        opacity: 0.95;
    }
    
    .overview-card {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .overview-card h2 {
        color: #800000;
        margin-bottom: 15px;
    }
    
    .overview-card p {
        color: #666;
        line-height: 1.8;
    }
    
    .phases-section {
        margin-bottom: 40px;
    }
    
    .phase-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        border-left: 5px solid #800000;
    }
    
    .phase-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .phase-number {
        background: #800000;
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: bold;
    }
    
    .phase-title {
        flex: 1;
        margin-left: 20px;
    }
    
    .phase-title h3 {
        color: #333;
        margin-bottom: 5px;
    }
    
    .phase-duration {
        color: #800000;
        font-weight: 600;
    }
    
    .skills-list {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 15px 0;
    }
    
    .skill-badge {
        background: #f0f0f0;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        color: #333;
    }
    
    .milestones-list {
        list-style: none;
        padding: 0;
    }
    
    .milestones-list li {
        padding: 10px 0;
        padding-left: 30px;
        position: relative;
        color: #666;
    }
    
    .milestones-list li:before {
        content: "✓";
        position: absolute;
        left: 0;
        color: #800000;
        font-weight: bold;
    }
    
    .skills-section {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }
    
    .skill-item {
        padding: 20px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .skill-item:last-child {
        border-bottom: none;
    }
    
    .skill-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .skill-name {
        font-size: 18px;
        font-weight: 600;
        color: #333;
    }
    
    .priority-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .priority-critical {
        background: #ffebee;
        color: #c62828;
    }
    
    .priority-important {
        background: #fff3e0;
        color: #e65100;
    }
    
    .priority-nice {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .skill-details {
        color: #666;
        font-size: 14px;
        line-height: 1.6;
    }
    
    .action-buttons {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }
    
    .btn {
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-primary {
        background: #800000;
        color: white;
    }
    
    .btn-primary:hover {
        background: #a00000;
    }

    .btn-text {
        background: none;
        border: none;
        color: #800000;
        font-weight: 600;
        cursor: pointer;
        padding: 5px 0;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
    }

    .btn-text:hover {
        text-decoration: underline;
    }

    .resources-container {
        background: #fdfdfd;
        border: 1px solid #eee;
        border-radius: 8px;
        padding: 20px;
    }

    .loading-spinner {
        text-align: center;
        padding: 20px;
        color: #666;
        font-style: italic;
    }
</style>

<div class="roadmap-container">
    <div class="roadmap-header">
        <h1><?php echo htmlspecialchars($roadmap['target_role']); ?></h1>
        <div class="subtitle">
            <?php if ($roadmap['target_company_type']): ?>
                <?php echo htmlspecialchars($roadmap['target_company_type']); ?> •
            <?php endif; ?>
            <?php echo htmlspecialchars($roadmap['target_industry']); ?> •
            <?php echo htmlspecialchars($roadmap['experience_level']); ?> Level
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
