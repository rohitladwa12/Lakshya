<?php
/**
 * Student - Announcements
 * Shows notifications about new jobs and internships
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();

// Load models
require_once __DIR__ . '/../../src/Models/JobPosting.php';
require_once __DIR__ . '/../../src/Models/InternshipPosting.php';

$jobModel = new JobPosting();
$internshipModel = new InternshipPosting();

// Get recent jobs and internships (last 30 days)
$allJobs = $jobModel->getActiveJobs();
$allInternships = $internshipModel->getActiveInternships();

// Filter for announcements (posted within last 30 days)
$announcements = [];

foreach ($allJobs as $job) {
    $postedDate = strtotime($job['posted_date']);
    $daysSincePosted = (time() - $postedDate) / (60 * 60 * 24);
    if ($daysSincePosted <= 30) {
        $announcements[] = [
            'type' => 'job',
            'title' => $job['title'],
            'company' => $job['company_name'],
            'location' => $job['location'],
            'posted_date' => $job['posted_date'],
            'deadline' => $job['application_deadline'],
            'id' => $job['id'],
            'is_new' => $daysSincePosted <= 7
        ];
    }
}

foreach ($allInternships as $internship) {
    $postedDate = strtotime($internship['posted_date']);
    $daysSincePosted = (time() - $postedDate) / (60 * 60 * 24);
    if ($daysSincePosted <= 30) {
        $announcements[] = [
            'type' => 'internship',
            'title' => $internship['title'],
            'company' => $internship['company_name'],
            'location' => $internship['location'],
            'posted_date' => $internship['posted_date'],
            'deadline' => $internship['application_deadline'],
            'id' => $internship['id'],
            'is_new' => $daysSincePosted <= 7
        ];
    }
}

// Fetch general announcements from the announcements table
$db = getDB();
try {
    $stmtGen = $db->query("SELECT * FROM announcements WHERE is_active = 1 AND (target_role = 'all' OR target_role = 'student') ORDER BY created_at DESC LIMIT 10");
    while ($gen = $stmtGen->fetch()) {
        $searchStr = strtolower($gen['title'] . ' ' . $gen['content']);
        $isJobRelated = (strpos($searchStr, 'job') !== false || strpos($searchStr, 'hiring') !== false || strpos($searchStr, 'recruitment') !== false || strpos($searchStr, 'placement') !== false);
        
        $announcements[] = [
            'type' => 'announcement',
            'title' => $gen['title'],
            'company' => 'Campus Admin',
            'location' => 'General',
            'posted_date' => $gen['created_at'],
            'deadline' => $gen['expires_at'] ?? 'N/A',
            'id' => $gen['id'],
            'is_new' => (time() - strtotime($gen['created_at'])) / (60 * 60 * 24) <= 7,
            'is_job_redirect' => $isJobRelated
        ];
    }
} catch (Exception $e) {}

// Sort by posted date (newest first)
usort($announcements, function($a, $b) {
    return strtotime($b['posted_date'] ?? '0') - strtotime($a['posted_date'] ?? '0');
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --light-gray: #f8f9fa;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light-gray);
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, #5b1f1f 100%);
            color: var(--white);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar h1 { font-size: 24px; }
        .navbar a { color: var(--white); text-decoration: none; padding: 8px 16px; border-radius: 6px; transition: background 0.3s; }
        .navbar a:hover { background: rgba(255,255,255,0.1); }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header {
            background: var(--white);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .page-header h2 {
            color: var(--primary-maroon);
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #666;
            font-size: 16px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .filter-tab {
            padding: 12px 24px;
            background: var(--white);
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .filter-tab:hover {
            border-color: var(--primary-gold);
        }
        
        .filter-tab.active {
            background: var(--primary-maroon);
            color: var(--white);
            border-color: var(--primary-maroon);
        }
        
        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .announcement-card {
            background: var(--white);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #e0e0e0;
            transition: all 0.3s;
            position: relative;
        }
        
        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .announcement-card.job {
            border-left-color: var(--primary-maroon);
        }
        
        .announcement-card.internship {
            border-left-color: #2196F3;
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .announcement-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .announcement-type.job {
            background: #fff5f5;
            color: var(--primary-maroon);
        }
        
        .announcement-type.internship {
            background: #e3f2fd;
            color: #2196F3;
        }

        .announcement-type.announcement {
            background: #f0f4f8;
            color: #546e7a;
        }
        
        .new-indicator {
            background: #ff4444;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .announcement-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .announcement-company {
            font-size: 16px;
            color: var(--primary-maroon);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .announcement-details {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }
        
        .detail-item strong {
            color: #333;
        }
        
        .announcement-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--primary-maroon);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: #5b1f1f;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--white);
            color: var(--primary-maroon);
            border: 2px solid var(--primary-maroon);
        }
        
        .btn-secondary:hover {
            background: #fff5f5;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--white);
            border-radius: 12px;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #666;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📢 Announcements</h1>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h2>Latest Opportunities</h2>
            <p>Stay updated with the newest job postings and internship opportunities</p>
        </div>
        
        <div class="filter-tabs">
            <div class="filter-tab active" onclick="filterAnnouncements('all')">All (<?php echo count($announcements); ?>)</div>
            <div class="filter-tab" onclick="filterAnnouncements('job')">Jobs (<?php echo count(array_filter($announcements, fn($a) => $a['type'] === 'job')); ?>)</div>
            <div class="filter-tab" onclick="filterAnnouncements('internship')">Internships (<?php echo count(array_filter($announcements, fn($a) => $a['type'] === 'internship')); ?>)</div>
            <div class="filter-tab" onclick="filterAnnouncements('announcement')">General (<?php echo count(array_filter($announcements, fn($a) => $a['type'] === 'announcement')); ?>)</div>
        </div>
        
        <div class="announcements-list">
            <?php if (empty($announcements)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No Recent Announcements</h3>
                    <p>Check back later for new job postings and internship opportunities</p>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card <?php echo $announcement['type']; ?>" data-type="<?php echo $announcement['type']; ?>">
                        <div class="announcement-header">
                            <span class="announcement-type <?php echo $announcement['type']; ?>">
                                <?php 
                                if ($announcement['type'] === 'job') echo '💼 Job';
                                elseif ($announcement['type'] === 'internship') echo '🎯 Internship';
                                else echo '📢 Announcement'; 
                                ?>
                            </span>
                            <?php if ($announcement['is_new']): ?>
                                <span class="new-indicator">🔥 NEW</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                        <div class="announcement-company">🏢 <?php echo htmlspecialchars($announcement['company']); ?></div>
                        
                        <div class="announcement-details">
                            <div class="detail-item">
                                📍 <strong>Location:</strong> <?php echo htmlspecialchars($announcement['location']); ?>
                            </div>
                            <div class="detail-item">
                                📅 <strong>Posted:</strong> <?php echo date('d M Y', strtotime($announcement['posted_date'])); ?>
                            </div>
                            <div class="detail-item">
                                ⏰ <strong>Deadline:</strong> <?php echo date('d M Y', strtotime($announcement['deadline'])); ?>
                            </div>
                        </div>
                        
                        <div class="announcement-actions">
                            <?php 
                            $link = 'jobs.php';
                            if ($announcement['type'] === 'internship') {
                                $link = 'internship_details.php?id=' . $announcement['id'];
                            } elseif ($announcement['type'] === 'announcement' && !($announcement['is_job_redirect'] ?? false)) {
                                $link = '#'; // Or a modal/detail page if implemented
                            }
                            ?>
                            <a href="<?php echo $link; ?>" class="btn btn-primary">
                                <?php echo ($announcement['type'] === 'announcement' && !($announcement['is_job_redirect'] ?? false)) ? 'Read More' : 'View Details'; ?>
                            </a>
                            <a href="company_ai_prep" class="btn btn-secondary">
                                🤖 Practice Interview
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function filterAnnouncements(type) {
            const cards = document.querySelectorAll('.announcement-card');
            const tabs = document.querySelectorAll('.filter-tab');
            
            // Update active tab
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter cards
            cards.forEach(card => {
                if (type === 'all' || card.dataset.type === type) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
