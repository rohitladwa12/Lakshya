<?php
/**
 * Career Resources Browser
 * Browse YouTube videos and study materials for roadmap
 */

require_once __DIR__ . '/../../config/bootstrap.php';
use App\Helpers\SessionFilterHelper;

requireRole(ROLE_STUDENT);

$userId = getUserId();

// Handle POST from roadmap or advisor
if (isPost() && isset($_POST['id'])) {
    SessionFilterHelper::setFilters('career_resources', [
        'id' => $_POST['id'] ?? 0
    ]);
    header("Location: career_resources.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('career_resources');
$roadmapId = $filters['id'] ?? null;

if (!$roadmapId) {
    header('Location: career_advisor.php');
    exit;
}

// Load models
require_once __DIR__ . '/../../src/Models/CareerRoadmap.php';
require_once __DIR__ . '/../../src/Models/CareerResource.php';

$roadmapModel = new CareerRoadmap();
$resourceModel = new CareerResource();

// Get roadmap
$roadmap = $roadmapModel->getRoadmapById($roadmapId, $userId);

if (!$roadmap) {
    header('Location: career_advisor.php');
    exit;
}

// Get resources
$videos = $resourceModel->getVideosByRoadmap($roadmapId);
$materials = $resourceModel->getStudyMaterialsByRoadmap($roadmapId);

// Get phases for filtering
$phases = $roadmap['roadmap_data']['phases'] ?? [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Resources - Student Portal</title>
    <style>
        :root {
            --nav-height: 72px; /* Standard navbar height */
            --primary: #800000;
        }

        body { 
            font-family: 'Outfit', sans-serif; 
            background: #f5f5f5; 
            padding-top: var(--nav-height); /* Offset for fixed navbar */
        }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<?php
?>

<style>
    .resources-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 30px 20px;
    }
    
    .page-header {
        background: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .page-header h1 {
        color: #800000;
        margin-bottom: 10px;
    }
    
    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .tab {
        padding: 15px 30px;
        background: none;
        border: none;
        font-size: 16px;
        font-weight: 600;
        color: #666;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.3s;
    }
    
    .tab.active {
        color: #800000;
        border-bottom-color: #800000;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .filters {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        gap: 15px;
        align-items: center;
    }
    
    .filters select {
        padding: 10px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .resources-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
    }
    
    .video-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.3s;
    }
    
    .video-card:hover {
        transform: translateY(-5px);
    }
    
    .video-thumbnail {
        width: 100%;
        height: 200px;
        object-fit: cover;
        cursor: pointer;
    }
    
    .video-content {
        padding: 20px;
    }
    
    .video-title {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .video-channel {
        color: #666;
        font-size: 14px;
        margin-bottom: 10px;
    }
    
    .video-meta {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        color: #999;
        margin-bottom: 15px;
    }
    
    .video-actions {
        display: flex;
        gap: 10px;
    }
    
    .action-btn {
        flex: 1;
        padding: 8px 12px;
        border: 2px solid #e0e0e0;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.3s;
    }
    
    .action-btn:hover {
        border-color: #800000;
        color: #800000;
    }
    
    .action-btn.active {
        background: #800000;
        color: white;
        border-color: #800000;
    }
    
    .material-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        display: flex;
        gap: 20px;
        align-items: start;
    }
    
    .material-icon {
        font-size: 48px;
    }
    
    .material-content {
        flex: 1;
    }
    
    .material-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }
    
    .material-source {
        color: #800000;
        font-size: 14px;
        margin-bottom: 10px;
    }
    
    .material-description {
        color: #666;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 15px;
    }
    
    .material-actions {
        display: flex;
        gap: 10px;
    }
    
    .download-btn {
        padding: 10px 20px;
        background: #800000;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    
    .download-btn:hover {
        background: #a00000;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }
    
    .empty-state-icon {
        font-size: 64px;
        margin-bottom: 20px;
    }
</style>

<div class="resources-container">
    <div class="page-header">
        <h1>📚 Learning Resources</h1>
        <p style="color: #666;">Curated videos and study materials for your career roadmap</p>
    </div>
    
    <div class="tabs">
        <button class="tab active" onclick="switchTab('videos')">
            🎥 YouTube Videos (<?php echo count($videos); ?>)
        </button>
        <button class="tab" onclick="switchTab('materials')">
            📄 Study Materials (<?php echo count($materials); ?>)
        </button>
    </div>
    
    <!-- Videos Tab -->
    <div id="videos-tab" class="tab-content active">
        <div class="filters">
            <label>Filter by Phase:</label>
            <select id="videoPhaseFilter" onchange="filterVideos()">
                <option value="">All Phases</option>
                <?php foreach ($phases as $phase): ?>
                    <option value="<?php echo $phase['phase_number']; ?>">
                        Phase <?php echo $phase['phase_number']; ?>: <?php echo htmlspecialchars($phase['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if (empty($videos)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎬</div>
                <h3>Resources are being curated...</h3>
                <p>We're fetching the best YouTube tutorials for your roadmap. Please refresh in a few moments.</p>
            </div>
        <?php else: ?>
            <div class="resources-grid" id="videosGrid">
                <?php foreach ($videos as $video): ?>
                    <div class="video-card" data-phase="<?php echo $video['phase_number']; ?>">
                        <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" 
                             alt="Video thumbnail" 
                             class="video-thumbnail"
                             onclick="window.open('https://www.youtube.com/watch?v=<?php echo $video['video_id']; ?>', '_blank')">
                        <div class="video-content">
                            <div class="video-title"><?php echo htmlspecialchars($video['title']); ?></div>
                            <div class="video-channel">📺 <?php echo htmlspecialchars($video['channel_name']); ?></div>
                            <div class="video-meta">
                                <span>⏱️ <?php echo $video['duration']; ?></span>
                                <span>👁️ <?php echo number_format($video['view_count']); ?> views</span>
                            </div>
                            <div class="video-actions">
                                <button class="action-btn <?php echo $video['is_bookmarked'] ? 'active' : ''; ?>" 
                                        onclick="toggleBookmark(<?php echo $video['id']; ?>, 'video', this)">
                                    ⭐ Bookmark
                                </button>
                                <button class="action-btn <?php echo $video['is_completed'] ? 'active' : ''; ?>" 
                                        onclick="markCompleted(<?php echo $video['id']; ?>, 'video', this)">
                                    ✓ Completed
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Materials Tab -->
    <div id="materials-tab" class="tab-content">
        <div class="filters">
            <label>Filter by Phase:</label>
            <select id="materialPhaseFilter" onchange="filterMaterials()">
                <option value="">All Phases</option>
                <?php foreach ($phases as $phase): ?>
                    <option value="<?php echo $phase['phase_number']; ?>">
                        Phase <?php echo $phase['phase_number']; ?>: <?php echo htmlspecialchars($phase['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label>File Type:</label>
            <select id="fileTypeFilter" onchange="filterMaterials()">
                <option value="">All Types</option>
                <option value="PDF">PDF</option>
                <option value="Notes">Notes</option>
                <option value="Cheatsheet">Cheatsheet</option>
            </select>
        </div>
        
        <?php if (empty($materials)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📚</div>
                <h3>Study materials are being curated...</h3>
                <p>We're finding the best downloadable resources for your roadmap. Please refresh in a few moments.</p>
            </div>
        <?php else: ?>
            <div class="resources-grid" id="materialsGrid">
                <?php foreach ($materials as $material): ?>
                    <div class="material-card" data-phase="<?php echo $material['phase_number']; ?>" data-type="<?php echo $material['file_type']; ?>">
                        <div class="material-icon">
                            <?php 
                            $icons = ['PDF' => '📕', 'Notes' => '📝', 'Cheatsheet' => '📋', 'DOC' => '📄'];
                            echo $icons[$material['file_type']] ?? '📄';
                            ?>
                        </div>
                        <div class="material-content">
                            <div class="material-title"><?php echo htmlspecialchars($material['title']); ?></div>
                            <div class="material-source">🌐 <?php echo htmlspecialchars($material['source_website']); ?></div>
                            <?php if ($material['description']): ?>
                                <div class="material-description"><?php echo htmlspecialchars(substr($material['description'], 0, 150)) . '...'; ?></div>
                            <?php endif; ?>
                            <div class="material-actions">
                                <a href="<?php echo htmlspecialchars($material['source_url']); ?>" 
                                   target="_blank" 
                                   class="download-btn"
                                   onclick="markDownloaded(<?php echo $material['id']; ?>)">
                                    ⬇️ Download/View
                                </a>
                                <button class="action-btn <?php echo $material['is_bookmarked'] ? 'active' : ''; ?>" 
                                        onclick="toggleBookmark(<?php echo $material['id']; ?>, 'material', this)">
                                    ⭐ Bookmark
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const roadmapId = <?php echo $roadmapId; ?>;

function switchTab(tab) {
    // Update tab buttons
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById(tab + '-tab').classList.add('active');
}

function filterVideos() {
    const phase = document.getElementById('videoPhaseFilter').value;
    const cards = document.querySelectorAll('#videosGrid .video-card');
    
    cards.forEach(card => {
        if (!phase || card.dataset.phase === phase) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function filterMaterials() {
    const phase = document.getElementById('materialPhaseFilter').value;
    const type = document.getElementById('fileTypeFilter').value;
    const cards = document.querySelectorAll('#materialsGrid .material-card');
    
    cards.forEach(card => {
        const phaseMatch = !phase || card.dataset.phase === phase;
        const typeMatch = !type || card.dataset.type === type;
        
        if (phaseMatch && typeMatch) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

async function toggleBookmark(resourceId, type, button) {
    try {
        const response = await fetch('career_handler', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: type === 'video' ? 'toggle_video_bookmark' : 'toggle_material_bookmark',
                [type + '_id']: resourceId,
                roadmap_id: roadmapId
            })
        });
        
        const result = await response.json();
        if (result.success) {
            button.classList.toggle('active');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function markCompleted(videoId, type, button) {
    try {
        const response = await fetch('career_handler', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_video_completed',
                video_id: videoId,
                roadmap_id: roadmapId
            })
        });
        
        const result = await response.json();
        if (result.success) {
            button.classList.add('active');
            button.textContent = '✓ Completed';
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function markDownloaded(materialId) {
    try {
        await fetch('career_handler', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_material_downloaded',
                material_id: materialId,
                roadmap_id: roadmapId
            })
        });
    } catch (error) {
        console.error('Error:', error);
    }
}
</script>

</body>
</html>
