<?php
/**
 * Student Dashboard
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$username = getUsername();
$fullName = getFullName();

// Check for new jobs (posted within last 7 days)
require_once __DIR__ . '/../../src/Models/JobPosting.php';
$jobModel = new JobPosting();
$recentJobs = $jobModel->getActiveJobs();
$newJobsCount = 0;
$activeJobsCount = 0;
foreach ($recentJobs as $job) {
    if ($job['status'] === 'Active')
        $activeJobsCount++;
    $postedDate = strtotime($job['posted_date']);
    $daysSincePosted = (time() - $postedDate) / (60 * 60 * 24);
    if ($daysSincePosted <= 7) {
        $newJobsCount++;
    }
}
$hasNewJobs = $newJobsCount > 0;

// GMIT SGPA completeness check for feature gating
$institution = $_SESSION['institution'] ?? '';
$isGMIT = ($institution === INSTITUTION_GMIT);
$hasFullHistory = true;
$missingSemMsg = "";

if ($isGMIT) {
    require_once __DIR__ . '/../../src/Models/StudentProfile.php';
    $checkModel = new StudentProfile();

    // Check if student has marked a current semester (implies they visited and saved)
    // Using username (USN) as student_id for GMIT
    try {
        $db = getDB();
        // Pass if student has marked a current semester OR if coordinator has set/frozen their SGPAs
        $stmt = $db->prepare(
            "SELECT 1 FROM student_sem_sgpa 
             WHERE student_id = ? AND institution = ? 
             AND (is_current = 1 OR freezed = 1) 
             LIMIT 1"
        );
        $stmt->execute([getUsername(), INSTITUTION_GMIT]);
        if (!$stmt->fetch()) {
            $hasFullHistory = false;
        }
    }
    catch (Exception $e) {
        error_log("Dashboard SGPA Check Error: " . $e->getMessage());
    }

    $history = $checkModel->getAcademicHistory($userId, $institution);
    $mainProfile = $history[0] ?? null;

    if (!$mainProfile && Session::getRole() === ROLE_DEMO) {
        $mainProfile = [
            'name' => 'Demo User',
            'discipline' => 'CSE',
            'institution' => 'GMU'
        ];
    }

    // Restore name to session if it was missing (for users already logged in)
    if (empty($fullName) && !empty($mainProfile['name'])) {
        $_SESSION['full_name'] = $mainProfile['name'];
        $fullName = $mainProfile['name'];
    }
}

// Load portfolio items for this student (skills, projects, certifications) from student_portfolio
require_once __DIR__ . '/../../src/Models/Portfolio.php';
$portfolioModel = new Portfolio();
$allPortfolio = $portfolioModel->getStudentPortfolio($username, $institution ?: 'GMU');

// --- COMPULSORY RESUME CHECK ---
require_once __DIR__ . '/../../src/Models/Resume.php';
$resumeModel = new Resume();
$resumeData = $resumeModel->getByStudentId($userId);

// Also check for physical file: {USN}_Resume.pdf
$resumeFilePath = UPLOADS_PATH . '/resumes/Student_Resumes/' . strtoupper($username) . '_Resume.pdf';
$hasResume = file_exists($resumeFilePath);
// -------------------------------

// Load companies for the Placement Guide tool
require_once __DIR__ . '/../../src/Models/Company.php';
$companyModel = new Company();
$allCompanies = $companyModel->getActiveCompanies();

// Categorize portfolio items early for UI usage
$byCat = ['Project' => [], 'Skill' => [], 'Certification' => [], 'Personal Intro' => []];
if (!empty($allPortfolio)) {
    foreach ($allPortfolio as $item)
        $byCat[$item['category']][] = $item;
}

$completeness = 20; // Basic profile
if (!empty($byCat['Skill']))
    $completeness += 20;
if (!empty($byCat['Project']))
    $completeness += 20;
if (!empty($byCat['Certification']))
    $completeness += 20;
if (!empty($byCat['Personal Intro']))
    $completeness += 20;

// Fetch Campus Feed Items (Coordinator Tasks + Announcements + Jobs)
$feedItems = [];
$db = getDB();
$remoteDB = getDB('gmu');

// First, fetch coordinator-assigned tasks
try {
    // Get student's branch/department and institution
    $studentBranch = $mainProfile['discipline'] ?? $mainProfile['branch'] ?? '';
    $studentInstitution = $institution ?: 'GMU';

    $taskQuery = "SELECT ct.id, ct.task_type, ct.title, ct.description, 
                         ct.deadline, ct.company_name,
                         dc.department, dc.full_name as coordinator_name,
                         tc.id as completion_status
                  FROM coordinator_tasks ct
                  JOIN dept_coordinators dc ON ct.coordinator_id = dc.id
                  LEFT JOIN task_completions tc ON ct.id = tc.task_id 
                                                AND tc.student_id = ?
                  WHERE ct.is_active = 1 
                    AND (
                        (ct.target_type = 'department' AND dc.department = ? AND dc.institution = ?)
                        OR (ct.target_type = 'branch' AND JSON_CONTAINS(ct.target_branches, ?))
                        OR (ct.target_type = 'individual' AND ct.target_students LIKE ?)
                    )
                    AND (tc.id IS NULL OR tc.completed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) -- Show active OR completed in last 7 days
                    AND (tc.id IS NOT NULL OR ct.deadline > NOW())  -- Not expired if not completed
                  ORDER BY tc.id ASC, ct.deadline ASC
                  LIMIT 5";

    $stmt = $db->prepare($taskQuery);
    $stmt->execute([
        $username,
        $studentBranch,
        $studentInstitution,
        "\"$studentBranch\"",
        "%\"$username\"%"
    ]);
    $assignedTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert tasks to feed items
    foreach ($assignedTasks as $task) {
        $taskColors = [
            'aptitude' => '#3498db',
            'technical' => '#e74c3c',
            'hr' => '#2ecc71'
        ];
        $isCompleted = !empty($task['completion_status']);
        $statusText = $isCompleted ? 'COMPLETED' : 'Due: ' . date('M d', strtotime($task['deadline']));
        $titlePrefix = $isCompleted ? '✅ ' : '📝 ';

        $feedItems[] = [
            'title' => $titlePrefix . $task['title'],
            'subtitle' => strtoupper($task['task_type']) . ' Task - ' . $statusText,
            'link' => 'assigned_task.php',
            'id' => $task['id'],
            'color' => $isCompleted ? '#94a3b8' : ($taskColors[$task['task_type']] ?? '#3498db')
        ];
    }
}
catch (Exception $e) {
// Silently fail if tasks can't be loaded
}

// Then fetch announcements
try {
    $stmtFeed = $db->query("SELECT title, content as subtitle, 'announcements' as link, '#e74c3c' as color 
                        FROM announcements 
                        WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())
                        ORDER BY created_at DESC LIMIT 3");
    $announcements = $stmtFeed->fetchAll(PDO::FETCH_ASSOC);

    // Redirect job-related announcements to jobs.php
    foreach ($announcements as &$fItem) {
        $searchStr = strtolower($fItem['title'] . ' ' . $fItem['subtitle']);
        if (strpos($searchStr, 'job') !== false || strpos($searchStr, 'hiring') !== false || strpos($searchStr, 'recruitment') !== false) {
            $fItem['link'] = 'jobs.php';
        }
    }
    unset($fItem);

    // Merge announcements with tasks
    $feedItems = array_merge($feedItems ?? [], $announcements);
}
catch (Exception $e) {
}

if (count($feedItems) < 2) {
    if (!isset($jobModel)) {
        require_once __DIR__ . '/../../src/Models/JobPosting.php';
        $jobModel = new JobPosting();
    }
    $allActiveJobs = $jobModel->getActiveJobs();
    usort($allActiveJobs, function ($a, $b) {
        return strtotime($b['posted_date']) - strtotime($a['posted_date']);
    });

    $countNeeded = 2 - count($feedItems);
    $allActiveJobs = array_slice($allActiveJobs, 0, $countNeeded);
    foreach ($allActiveJobs as $job) {
        $feedItems[] = [
            'title' => 'Job: ' . $job['title'],
            'subtitle' => $job['company_name'] . ' is hiring!',
            'link' => 'jobs.php',
            'color' => 'var(--accent-gold)'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --accent-gold: #D4AF37;
            --light-gold: #f4e4bc;
            --white: #ffffff;
            --bg-light: #f5f6f8;
            --text-main: #1a1a1a;
            --text-muted: #6b7280;
            --glass: rgba(255, 255, 255, 0.95);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --gradient-maroon: linear-gradient(135deg, #800000 0%, #4a0000 100%);
            --gradient-gold: linear-gradient(135deg, #D4AF37 0%, #B8860B 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-main);
            line-height: 1.5;
            background-image: 
                radial-gradient(at 0% 0%, rgba(128, 0, 0, 0.02) 0, transparent 40%), 
                radial-gradient(at 100% 0%, rgba(212, 175, 55, 0.02) 0, transparent 40%);
        }

        .dashboard-container {
            max-width: 1540px;
            margin: 0 auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 2rem;
        }

        @media (max-width: 1100px) {
            .dashboard-container { grid-template-columns: 1fr; }
        }

        /* Sidebar Styling */
        .sidebar-profile {
            position: sticky;
            top: 5.5rem;
            height: fit-content;
        }

        .student-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid #eee;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .profile-pic-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 0 0 2px var(--accent-gold);
            overflow: hidden;
            background: #f0f0f0;
        }

        .profile-pic-container img { width: 100%; height: 100%; object-fit: cover; }

        .student-card h2 { font-size: 1.25rem; font-weight: 700; color: var(--primary-maroon); margin-bottom: 0.25rem; }
        .student-card p { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }

        .info-strip {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f0f0f0;
        }

        .info-box { text-align: left; }
        .info-box label { font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 2px; }
        .info-box span { font-size: 0.85rem; font-weight: 600; color: var(--text-main); }

        /* Main Workspace Styling */
        .workspace { display: flex; flex-direction: column; gap: 2rem; }

        /* Modern Hero Banner */
        .hero-banner {
            background: var(--gradient-maroon);
            border-radius: 24px;
            padding: 2.5rem 3rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            top: -20%; right: -10%;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-content h2 { font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; letter-spacing: -1px; }
        .hero-content p { font-size: 1rem; opacity: 0.85; max-width: 500px; line-height: 1.6; }

        .header-stats { display: flex; gap: 2rem; }
        .header-stat-item { text-align: center; }
        .header-stat-item .val { font-size: 2rem; font-weight: 800; color: var(--accent-gold); }
        .header-stat-item .lab { font-size: 0.75rem; text-transform: uppercase; opacity: 0.7; font-weight: 600; }

        /* Stats & Quick Actions */
        .quick-actions-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .action-tool-btn {
            background: white;
            padding: 1.25rem;
            border-radius: 16px;
            text-decoration: none;
            color: var(--text-main);
            border: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }

        .action-tool-btn:hover {
            transform: translateY(-5px);
            border-color: var(--primary-maroon);
            box-shadow: var(--shadow-md);
        }

        .action-tool-btn .icon {
            width: 40px; height: 40px;
            background: var(--light-gold);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .action-tool-btn h4 { font-size: 0.95rem; font-weight: 700; color: var(--primary-maroon); }

        /* Content Bento Grid */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 850px) { .bento-grid { grid-template-columns: 1fr; } }

        .bento-card {
            background: var(--white);
            border-radius: 20px;
            padding: 1.75rem;
            border: 1px solid #eee;
            box-shadow: var(--shadow-sm);
        }

        .bento-card-large { grid-column: span 2; }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-header h3 { font-size: 1.1rem; font-weight: 800; color: var(--primary-dark); display: flex; align-items: center; gap: 10px; }
        .card-header .btn-small {
            padding: 6px 12px;
            background: #f0f0f0;
            border-radius: 8px;
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 700;
            transition: all 0.2s;
        }
        .card-header .btn-small:hover { background: var(--primary-maroon); color: white; }

        /* Skill Grid */
        .skill-pill-container { display: flex; flex-wrap: wrap; gap: 10px; }
        .skill-pill {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #4b5563;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .skill-pill.verified { 
            border-color: #10b981; 
            color: #065f46; 
            background: #ecfdf5; 
            font-weight: 700;
            border-width: 1.5px;
        }
        .skill-pill:not(.verified):hover {
            border-color: var(--primary-maroon);
            background: #fffafa;
            color: var(--primary-maroon);
        }

        /* Project List */
        .project-item {
            padding: 1.25rem;
            border-radius: 16px;
            background: #ffffff;
            border: 1px solid #f3f4f6;
            margin-bottom: 1.25rem;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            position: relative;
        }
        .project-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-color: #e5e7eb;
        }
        .project-item:last-child { margin-bottom: 0; }
        .project-item h4 { font-size: 0.95rem; font-weight: 700; color: var(--primary-maroon); line-height: 1.3; margin-bottom: 6px; padding-right: 60px; }
        .project-item p { font-size: 0.8rem; color: #6b7280; line-height: 1.5; }
        
        .verify-badge-link {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            text-decoration: none;
        }
        .verify-badge-pill {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            padding: 4px 8px;
            background: #fff5f5;
            border: 1px solid #feb2b2;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .verify-badge-pill i { font-size: 0.75rem; color: #c53030; }
        .verify-badge-pill span { font-size: 0.55rem; color: #c53030; font-weight: 800; text-transform: uppercase; }
        .verify-badge-pill:hover { background: #feb2b2; transform: scale(1.05); }

        /* Professional Success Toast */
        .success-message {
            background: #e3fcef;
            color: #006644;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border-left: 5px solid #00875a;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideInDown 0.5s ease-out;
        }

        /* Hero Welcome Section */
        .welcome-section {
            display: grid;
            grid-template-columns: 1fr;
            margin-bottom: 3rem;
        }

        .welcome-card {
            background: var(--white);
            padding: 3rem;
            border-radius: 24px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(128, 0, 0, 0.05);
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .welcome-card h2 {
            font-size: 2.25rem;
            color: var(--primary-maroon);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .welcome-card p {
            color: var(--text-muted);
            font-size: 1.1rem;
            max-width: 700px;
        }

        /* Modern Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid #f0f0f0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent-gold);
        }

        .stat-card .icon-box {
            width: 60px;
            height: 60px;
            background: #fffafa;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-maroon);
            box-shadow: inset 0 0 0 1px rgba(128, 0, 0, 0.05);
        }

        .stat-card .info h3 {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .info .number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-maroon);
        }

        /* Quick Actions - The "Dashing" Part */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .section-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-dark);
            position: relative;
        }

        .section-header h3::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--accent-gold);
            border-radius: 2px;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.25rem;
            margin-bottom: 3rem;
        }

        .action-card {
            background: var(--white);
            border-radius: 20px;
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            border: 1px solid #f1f1f1;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .action-card:hover {
            background: var(--gradient-maroon);
            transform: scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .action-card .icon-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .action-card .icon-wrap {
            width: 45px;
            height: 45px;
            background: var(--light-gold);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            transition: all 0.3s;
        }

        .action-card:hover .icon-wrap {
            background: rgba(255,255,255,0.2);
            transform: rotate(10deg);
        }

        .action-card h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-dark);
            transition: color 0.3s;
        }

        .action-card:hover h4, .action-card:hover p {
            color: var(--white);
        }

        .action-card p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .badge {
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 50px;
            text-transform: uppercase;
        }
        
        .badge-new { background: var(--accent-gold); color: var(--primary-dark); }
        .badge-ai { background: #e0f2fe; color: #0369a1; }

        /* NQT Hub Specific Styles */
        .nqt-hub-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d0000 100%);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 3rem;
            border: 1px solid rgba(255, 215, 0, 0.2);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }

        .nqt-hub-section::before {
            content: 'NQT';
            position: absolute;
            top: -20px;
            right: -10px;
            font-size: 8rem;
            font-weight: 900;
            color: rgba(255, 255, 255, 0.03);
            pointer-events: none;
        }

        .nqt-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .nqt-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            text-decoration: none;
            color: white;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .nqt-card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-gold);
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
        }

        .nqt-card .icon-box {
            width: 50px;
            height: 50px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--accent-gold);
        }

        .nqt-card h4 { font-size: 1.2rem; font-weight: 700; color: var(--accent-gold); }
        .nqt-card p { font-size: 0.85rem; opacity: 0.7; line-height: 1.5; }
        .nqt-card .btn-nqt {
            margin-top: auto;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--accent-gold);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Profile & History */
        .content-split {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 2rem;
        }

        @media (max-width: 1024px) {
            .content-split { grid-template-columns: 1fr; }
        }

        .glass-card {
            background: var(--white);
            border-radius: 24px;
            padding: 2rem;
            border: 1px solid rgba(128, 0, 0, 0.05);
            box-shadow: var(--shadow-md);
        }

        .detail-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-item label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .detail-item span {
            font-weight: 600;
            color: var(--text-main);
        }

        /* Portfolio Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 2500;
            backdrop-filter: blur(12px);
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }
        .modal-content {
            background: white;
            width: 100%;
            max-width: 700px;
            max-height: 85vh;
            border-radius: 28px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .modal-body {
            padding: 2.5rem;
            overflow-y: auto;
            flex: 1;
        }

        /* Tabs in Modal */
        .modal-tabs {
            display: flex;
            background: #f8f9fa;
            padding: 10px 20px 0;
            border-bottom: 1px solid #eee;
        }
        .m-tab {
            padding: 12px 24px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.9rem;
            color: #718096;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .m-tab.active {
            color: var(--primary-maroon);
            border-bottom-color: var(--primary-maroon);
        }

        .modal-header {
            padding: 2rem 2.5rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 { 
            color: var(--primary-maroon); 
            font-weight: 800; 
            font-size: 1.6rem; 
            letter-spacing: -0.5px;
        }
        .close-modal { 
            width: 36px;
            height: 36px;
            background: #f7fafc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer; 
            color: #a0aec0; 
            transition: all 0.2s;
        }
        .close-modal:hover {
            background: #edf2f7;
            color: #e53e3e;
            transform: rotate(90deg);
        }

        /* Entry Row Styling */
        .pf-entry-row {
            background: #ffffff;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 20px;
            border: 1px solid #edf2f7;
            position: relative;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: all 0.3s;
        }
        .pf-entry-row:hover {
            border-color: #cbd5e0;
            box-shadow: 0 10px 15px rgba(0,0,0,0.05);
        }

        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #4a5568;
            margin-bottom: 6px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .pf-input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            background: #f8fafc;
            transition: all 0.2s;
        }
        .pf-input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-maroon);
            background: white;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.05);
        }

        /* Portfolio List Tab Styles */
        .portfolio-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            padding: 16px;
            border-radius: 14px;
            border: 1px solid #edf2f7;
            margin-bottom: 12px;
            transition: all 0.2s;
        }
        .portfolio-list-item:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }
        .p-item-info h4 { font-size: 0.95rem; font-weight: 700; color: #2d3748; }
        .p-item-info span { font-size: 0.75rem; color: #a0aec0; font-weight: 600; }
        .p-item-actions { display: flex; gap: 8px; }
        .p-action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        .p-btn-delete { background: #fff5f5; color: #e53e3e; }
        .p-btn-delete:hover { background: #e53e3e; color: white; }
        
        .portfolio-item-row {
            display: flex; justify-content: space-between; align-items: flex-start;
            padding: 10px 0; border-bottom: 1px solid #f5f5f5;
        }
        .portfolio-item-row:last-child { border-bottom: none; }
        .portfolio-item-info { flex: 1; }
        .portfolio-item-title { font-weight: 600; font-size: 0.95rem; color: var(--text-main); }
        .portfolio-item-meta { font-size: 0.8rem; color: var(--text-muted); }
        .delete-item-btn { color: #ff7675; cursor: pointer; font-size: 0.85rem; margin-left: 10px; }

        /* Shared Portfolio & Verification Modal Styles */
        .pf-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 3000;
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .pf-modal-content {
            background: white;
            width: 100%;
            max-width: 600px;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            position: relative;
        }
        .pf-btn {
            padding: 12px 25px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            border: none;
            font-size: 0.95rem;
        }
        .pf-btn-primary { background: var(--primary-maroon); color: white; }
        .pf-btn-secondary { background: #f5f5f5; color: #666; }
        .pf-btn:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Locked Component Styles */
        .locked-card {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none !important;
            filter: grayscale(1);
            position: relative;
        }
        .locked-card::after {
            content: 'LOCKED';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff4757;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 4px;
            z-index: 10;
        }
        
        <?php if (!$hasFullHistory && $isGMIT): ?>
        /* Force lock navbar on this page */
        .nav-menu { 
            display: none !important; 
        }
        .nav-logo {
            pointer-events: none !important;
        }
        .student-badge {
            opacity: 0.7;
        }
        <?php
endif; ?>
        .submit-btn {
            background: var(--primary-maroon);
            color: white;
            width: 100%;
            padding: 16px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
            box-shadow: 0 4px 6px rgba(128, 0, 0, 0.2);
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(128, 0, 0, 0.3);
            background: #600000;
        }
        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* ===== ANIMATED AVATAR SYSTEM ===== */
        .profile-pic-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 0.5rem;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 0 0 2px var(--accent-gold);
            overflow: hidden;
            background: #f0f0f0;
            position: relative;
            cursor: pointer;
        }
        .profile-pic-container img { width: 100%; height: 100%; object-fit: cover; }
        .profile-pic-container .avatar-svg-wrap {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
        }
        .change-avatar-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 0 auto 1.5rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            background: #f5f5f5;
            border: none;
            border-radius: 20px;
            padding: 4px 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .change-avatar-btn:hover {
            background: var(--light-gold);
            color: var(--primary-maroon);
        }

        /* Avatar Picker Modal */
        .avatar-picker-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .avatar-picker-overlay.open { display: flex; }
        .avatar-picker-box {
            background: #fff;
            border-radius: 28px;
            padding: 2rem;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
            animation: avatarPopIn 0.35s cubic-bezier(0.175,0.885,0.32,1.275);
        }
        @keyframes avatarPopIn {
            from { transform: scale(0.8); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }
        .avatar-picker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .avatar-picker-header h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary-maroon);
        }
        .avatar-picker-close {
            width: 34px; height: 34px;
            background: #f5f5f5;
            border: none;
            border-radius: 50%;
            font-size: 1.1rem;
            cursor: pointer;
            color: #888;
            display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .avatar-picker-close:hover { background: #ffe0e0; color: #e53e3e; transform: rotate(90deg); }
        .avatar-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            max-height: 50vh;
            overflow-y: auto;
            padding-right: 10px;
        }
        .avatar-grid::-webkit-scrollbar {
            width: 6px;
        }
        .avatar-grid::-webkit-scrollbar-track {
            background: #f1f1f1; 
            border-radius: 4px;
        }
        .avatar-grid::-webkit-scrollbar-thumb {
            background: #c1c1c1; 
            border-radius: 4px;
        }
        .avatar-grid::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8; 
        }
        .avatar-option {
            border-radius: 16px;
            padding: 10px;
            border: 2.5px solid transparent;
            cursor: pointer;
            transition: all 0.25s;
            background: #fafafa;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        .avatar-option:hover {
            border-color: var(--accent-gold);
            background: var(--light-gold);
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(212,175,55,0.2);
        }
        .avatar-option.selected {
            border-color: var(--primary-maroon);
            background: #fff8f8;
            box-shadow: 0 0 0 3px rgba(128,0,0,0.08);
        }
        .avatar-option svg, .avatar-option .av-emoji {
            width: 64px;
            height: 64px;
            display: block;
        }
        .av-emoji { font-size: 3.2rem; line-height: 1; }
        .avatar-option span {
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }
        .avatar-save-btn {
            width: 100%;
            margin-top: 1.5rem;
            padding: 14px;
            background: var(--gradient-maroon);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }
        .avatar-save-btn:hover { opacity: 0.9; transform: translateY(-2px); }

        /* Individual avatar SVG animations */
        .av-bounce { animation: avBounce 2s ease-in-out infinite; }
        .av-wave  { animation: avWave  1.8s ease-in-out infinite; transform-origin: 70% 90%; }
        .av-pulse { animation: avPulse 2.2s ease-in-out infinite; }
        .av-spin  { animation: avSpin  8s linear infinite; transform-origin: center; }
        .av-float { animation: avFloat 2.5s ease-in-out infinite; }
        .av-rock  { animation: avRock  2s ease-in-out infinite; transform-origin: bottom center; }

        @keyframes avBounce {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-6px); }
        }
        @keyframes avWave {
            0%, 100% { transform: rotate(0deg); }
            25%       { transform: rotate(20deg); }
            75%       { transform: rotate(-10deg); }
        }
        @keyframes avPulse {
            0%, 100% { transform: scale(1); }
            50%       { transform: scale(1.06); }
        }
        @keyframes avSpin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        @keyframes avFloat {
            0%, 100% { transform: translateY(0) rotate(-2deg); }
            50%       { transform: translateY(-8px) rotate(2deg); }
        }
        @keyframes avRock {
            0%, 100% { transform: rotate(-5deg); }
            50%       { transform: rotate(5deg); }
        }

        /* Responsive Overrides */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
                gap: 1.5rem;
            }

            .sidebar-profile {
                position: static;
            }

            .hero-banner {
                flex-direction: column;
                text-align: center;
                padding: 2rem 1.5rem;
                gap: 2rem;
            }

            .hero-content h2 {
                font-size: 1.75rem;
            }

            .hero-content p {
                font-size: 0.9rem;
            }

            .header-stats {
                width: 100%;
                justify-content: center;
                border-top: 1px solid rgba(255,255,255,0.1);
                padding-top: 1.5rem;
            }

            .quick-actions-bar {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-tool-btn {
                padding: 1rem;
                flex-direction: column;
                text-align: center;
            }

            .action-grid {
                grid-template-columns: 1fr;
            }

            .nqt-hub-section {
                padding: 1.5rem;
            }

            .nqt-hub-section h3 {
                font-size: 1.5rem;
            }

            .nqt-grid {
                grid-template-columns: 1fr;
            }

            .bento-card-large {
                grid-column: span 1;
            }

            .bento-card-large > div:last-child {
                grid-template-columns: 1fr !important;
            }

            .modal-content {
                padding: 0;
                border-radius: 0;
                max-height: 100vh;
                height: 100vh;
            }

            .modal-body {
                padding: 1.5rem;
            }

            .pf-modal-content {
                padding: 1.5rem;
                border-radius: 20px;
                width: 95%;
            }

            .welcome-card {
                padding: 2rem 1.5rem;
            }

            .welcome-card h2 {
                font-size: 1.75rem;
            }

            .resume-alert-banner {
                flex-direction: column;
                text-align: center;
                gap: 15px;
                padding: 1.5rem !important;
            }

            .resume-alert-banner .tb-btn {
                width: 100%;
            }

            .nqt-hub-section > div:first-child {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .nqt-hub-section h3 {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .quick-actions-bar {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .hero-content h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar-profile">
            <?php
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$studentProfileModel = new StudentProfile();
$academicHistory = $studentProfileModel->getAcademicHistory($userId, $institution);
$profile = $academicHistory[0] ?? null;

// Bypass profile missing check for demo user
if (!$profile && Session::getRole() === ROLE_DEMO) {
    $profile = [
        'full_name' => 'Demo User',
        'enrollment_number' => 'DEMO123456',
        'course' => 'Computer Science (Demo)',
        'semester' => '6',
        'department' => 'Engineering',
        'branch' => 'CSE',
        'institution' => 'GMU',
        'profile_photo' => null
    ];
}

if ($profile):
?>
            <div class="student-card">
                <div class="profile-pic-container" id="profilePicContainer">
                <?php if (!empty($profile['profile_photo'])): ?>
                    <img src="<?php echo htmlspecialchars((string)$profile['profile_photo']); ?>" alt="Profile" id="profileRealPhoto" style="display:block;">
                <?php
    endif; ?>
                <div id="profileAvatarDisplay" style="width:100%;height:100%;display:<?php echo !empty($profile['profile_photo']) ? 'none' : 'flex'; ?>;align-items:center;justify-content:center;">
                    <!-- Avatar rendered by JS -->
                    <span style="font-size:3rem;">🎓</span>
                </div>
            </div>

                <h2><?php echo htmlspecialchars((string)($fullName ?? 'Student')); ?></h2>
                <p><?php echo htmlspecialchars($profile['enrollment_number'] ?? ''); ?></p>
                
                <div class="info-strip">
                    <div class="info-box">
                        <label>Course</label>
                        <span><?php echo htmlspecialchars((string)($profile['course'] ?? 'N/A')); ?></span>
                    </div>
                    <div class="info-box">
                        <label>Semester</label>
                        <span><?php echo htmlspecialchars((string)($profile['semester'] ?? '0')); ?></span>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem; text-align: left;">
                    <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 8px;">Department</label>
                    <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars((string)($profile['department'] ?? 'N/A')); ?></span>
                </div>

                <div style="margin-top: 1.5rem; text-align: left;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Profile Strength</label>
                        <span style="font-size: 0.75rem; font-weight: 800; color: var(<?php echo $completeness > 80 ? '--accent-gold' : '--primary-maroon'; ?>);"><?php echo $completeness; ?>%</span>
                    </div>
                    <div style="width: 100%; height: 6px; background: #f0f0f0; border-radius: 10px; overflow: hidden;">
                        <div style="width: <?php echo $completeness; ?>%; height: 100%; background: var(--gradient-maroon); border-radius: 10px; transition: width 1s ease-in-out;"></div>
                    </div>
                </div>

                <?php if ($isGMIT): ?>
                    <a href="sgpa_entry.php" style="display: block; margin-top: 2rem; padding: 12px; background: #fff; border: 1.5px dashed var(--accent-gold); color: var(--primary-maroon); border-radius: 12px; font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: 0.3s;">
                        <i class="fas fa-edit"></i> Update Academic History
                    </a>
                <?php
    endif; ?>
            </div>

            <!-- Mini History -->
            <div class="bento-card">
                <div class="card-header" style="margin-bottom: 1rem;">
                    <h3 style="font-size: 0.95rem;"><i class="fas fa-history"></i> Recent SGPAs</h3>
                </div>
                <?php if (count($academicHistory) > 1): ?>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <?php for ($i = 1; $i < min(4, count($academicHistory)); $i++):
            $hist = $academicHistory[$i]; ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f9f9f9;">
                                <span style="font-size: 0.8rem; font-weight: 600;">Sem <?php echo htmlspecialchars((string)($hist['semester'] ?? '')); ?></span>
                                <span style="font-size: 0.85rem; font-weight: 800; color: var(--primary-maroon);"><?php echo htmlspecialchars((string)($hist['sgpa'] ?? '0.00')); ?></span>
                            </div>
                        <?php
        endfor; ?>
                    </div>
                <?php
    else: ?>
                    <p style="font-size: 0.75rem; color: var(--text-muted); text-align: center;">No history available.</p>
                <?php
    endif; ?>
            </div>
            <?php
endif; ?>
        </aside>

        <!-- Main Workspace -->
        <main class="workspace">
            <?php if (!$hasFullHistory && $isGMIT): ?>
                <div class="welcome-card" style="text-align: center; padding: 4rem; border: 2px solid var(--primary-maroon);">
                    <div style="font-size: 4rem; margin-bottom: 2rem;">⚠️</div>
                    <h2 style="font-size: 2rem; color: var(--primary-maroon); margin-bottom: 1rem;">Academic Details Required</h2>
                    <p style="margin-bottom: 2rem; font-size: 1.1rem; color: var(--text-muted);">
                        To unlock your dashboard, you must update your <strong>Semester & SGPA</strong> details.
                    </p>
                    <a href="sgpa_entry.php" class="pf-btn pf-btn-primary" style="display: inline-block; text-decoration: none; padding: 15px 30px; font-size: 1.1rem; border-radius: 50px;">
                        <i class="fas fa-edit" style="margin-right: 8px;"></i> Update Now
                    </a>
                </div>
            <?php
else: ?>
                <!-- COMPULSORY RESUME NOTIFICATION -->
                <?php if (!$hasResume): ?>
                    <div class="resume-alert-banner" style="background: linear-gradient(90deg, #800000, #b22222); color: white; padding: 20px 30px; border-radius: 20px; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 20px rgba(128,0,0,0.2); animation: pulseAlert 2s infinite;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <i class="fas fa-file-invoice" style="font-size: 1.8rem; color: var(--accent-gold);"></i>
                            <div>
                                <h4 style="margin:0; font-weight: 800; font-size: 1.1rem;">Resume Profile Incomplete</h4>
                                <p style="margin: 5px 0 0; opacity: 0.9; font-size: 0.9rem;">Please create or upload your resume in <strong>AI Tools</strong>. It is compulsory for all internship and job applications.</p>
                            </div>
                        </div>
                        <a href="resume_builder.php" class="tb-btn" style="background: var(--accent-gold); color: #000; font-weight: 800; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-size: 0.85rem; border: none; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                            BUILD / UPLOAD NOW
                        </a>
                    </div>
                    <style>
                        @keyframes pulseAlert {
                            0% { transform: scale(1); box-shadow: 0 10px 20px rgba(128,0,0,0.2); }
                            50% { transform: scale(1.01); box-shadow: 0 15px 30px rgba(128,0,0,0.3); }
                            100% { transform: scale(1); box-shadow: 0 10px 20px rgba(128,0,0,0.2); }
                        }
                    </style>
                <?php
    endif; ?>

            <!-- TCS NQT Practice Hub (TOP PRIORITY - Only for 8th Sem) -->
            <?php if (isset($profile['semester']) && $profile['semester'] == 8): ?>
            <section class="nqt-hub-section" style="margin-bottom: 2rem; border: 2px solid var(--accent-gold); background: linear-gradient(135deg, #1a1a1a 0%, #4a0000 100%);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                            <span style="background: var(--accent-gold); color: var(--primary-dark); padding: 4px 12px; border-radius: 50px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 15px rgba(233, 198, 111, 0.3);">New in Lakshya</span>
                            <span style="color: rgba(255,255,255,0.5); font-size: 11px; font-weight: 600;">| EXCLUSIVE FOR 8TH SEMESTER</span>
                        </div>
                        <h3 style="font-size: 2rem; color: #fff; display: flex; align-items: center; gap: 15px; font-weight: 800;">
                            <i class="fas fa-bolt" style="color: var(--accent-gold); filter: drop-shadow(0 0 10px var(--accent-gold));"></i> 
                            TCS NQT 2026 Elite Hub
                        </h3>
                        <p style="color: rgba(255,255,255,0.7); font-size: 1.1rem; margin-top: 5px;">Practice for TCS NQT only for 8th sem students</p>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge" style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(5px);">PREMIUM ACCESS</span>
                    </div>
                </div>

                <div class="nqt-grid">
                    <a href="nqt_test_engine" class="nqt-card" style="border: 1px solid rgba(255, 215, 0, 0.1);">
                        <div class="icon-box" style="background: rgba(77, 173, 247, 0.15); color: #4dadf7;"><i class="fas fa-brain"></i></div>
                        <div>
                            <h4 style="color: #fff; font-size: 1.2rem;">Cognitive Assessment</h4>
                            <p style="color: rgba(255,255,255,0.5);">Targeted NQT aptitude, logic, and verbal rounds with AI mutation.</p>
                        </div>
                        <div class="btn-nqt" style="background: #4dadf7; color: white;">Launch Test <i class="fas fa-play" style="font-size: 0.8rem;"></i></div>
                    </a>

                    <a href="nqt_technical_round" class="nqt-card" style="border: 1px solid rgba(255, 215, 0, 0.1);">
                        <div class="icon-box" style="background: rgba(255, 146, 43, 0.15); color: #ff922b;"><i class="fas fa-code"></i></div>
                        <div>
                            <h4 style="color: #fff; font-size: 1.2rem;">Advanced Coding</h4>
                            <p style="color: rgba(255,255,255,0.5);">18+ Master problems. Real-time AI evaluation for NQT patterns.</p>
                        </div>
                        <div class="btn-nqt" style="background: #ff922b; color: white;">Start Coding <i class="fas fa-terminal" style="font-size: 0.8rem;"></i></div>
                    </a>

                    <a href="nqt_hr_round" class="nqt-card" style="border: 1px solid rgba(255, 215, 0, 0.1);">
                        <div class="icon-box" style="background: rgba(81, 207, 102, 0.15); color: #51cf66;"><i class="fas fa-comments"></i></div>
                        <div>
                            <h4 style="color: #fff; font-size: 1.2rem;">AI Behavioral Round</h4>
                            <p style="color: rgba(255,255,255,0.5);">Mock HR interview tailored specifically for TCS selection logic.</p>
                        </div>
                        <div class="btn-nqt" style="background: #51cf66; color: white;">Enter Round <i class="fas fa-microphone" style="font-size: 0.8rem;"></i></div>
                    </a>
                </div>
            </section>
            <?php
    endif; ?>

            <!-- Hero Banner -->
            <div class="hero-banner">
                <div class="hero-content">
                    <h2>Hello, <?php echo htmlspecialchars(explode(' ', $fullName)[0]); ?>! 👋</h2>
                    <p>Welcome to Lakshya. You have <strong><?php echo $activeJobsCount; ?></strong> matching job opportunities today. Your portfolio is currently <strong><?php echo $completeness; ?>%</strong> complete.</p>
                    
                    <?php if (!$hasFullHistory && $isGMIT): ?>
                        <div style="margin-top: 1rem; background: rgba(0,0,0,0.2); padding: 10px 20px; border-radius: 12px; display: inline-flex; align-items: center; gap: 10px; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.2);">
                            <i class="fas fa-exclamation-triangle" style="color: var(--accent-gold);"></i>
                            <span>Features Locked: Academic data required.</span>
                        </div>
                    <?php
    endif; ?>
                </div>
                
                <div class="header-stats">
                    <div class="header-stat-item">
                        <div class="val"><?php echo $activeJobsCount; ?></div>
                        <div class="lab">Jobs</div>
                    </div>
                    <div class="header-stat-item">
                        <div class="val"><?php echo count($allPortfolio); ?></div>
                        <div class="lab">Portfolio</div>
                    </div>
                </div>
            </div>

            <!-- Toolbelt -->
            <div class="quick-actions-bar">
                <a href="jobs" class="action-tool-btn <?php echo(!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                    <div class="icon">💼</div>
                    <h4>Jobs</h4>
                </a>
                <a href="company_ai_prep" class="action-tool-btn <?php echo(!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                    <div class="icon">🤖</div>
                    <h4>AI Prep</h4>
                </a>
                <a href="mock_ai_interview" class="action-tool-btn <?php echo(!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                    <div class="icon">🔥</div>
                    <h4>Mock AI Prep</h4>
                </a>
                <a href="leaderboard" class="action-tool-btn <?php echo(!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                    <div class="icon">🏆</div>
                    <h4>Leaderboard</h4>
                </a>
                <a href="javascript:void(0)" onclick="openGuideModal()" class="action-tool-btn <?php echo(!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                    <div class="icon">🎓</div>
                    <h4>Company Guide</h4>
                </a>
                <a href="internships" class="action-tool-btn <?php echo(!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                    <div class="icon">🔍</div>
                    <h4>Internships</h4>
                </a>
                <a href="applications" class="action-tool-btn <?php echo(!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                    <div class="icon">📝</div>
                    <h4>Status</h4>
                </a>
            </div>


            <!-- Bento Content -->
            <div class="bento-grid">
                <!-- Portfolio Highlights -->
                <div class="bento-card bento-card-large">
                    <div class="card-header">
                        <h3><i class="fas fa-star" style="color: var(--accent-gold);"></i> Verified Highlights</h3>
                        <div style="display: flex; gap: 10px;">
                            <a href="profile_analyser" class="btn-small" style="background: var(--gradient-maroon); color:white;">AI Analyzer</a>
                            <button onclick="openPortfolioModal()" class="btn-small">Manage</button>
                        </div>
                    </div>



                    <div style="display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 2rem;">
                        <div style="border-right: 1px solid #f3f4f6; padding-right: 2rem;">
                            <h4 style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.25rem; letter-spacing: 1.5px; font-weight: 700;">Key Projects</h4>
                            <?php if (!empty($byCat['Project'])): ?>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <?php foreach (array_slice($byCat['Project'], 0, 3) as $proj): ?>
                                        <div class="project-item">
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
                                                <h4><?php echo htmlspecialchars((string)($proj['title'] ?? '')); ?></h4>
                                                <?php if ($proj['is_verified']): ?>
                                                    <i class="fas fa-check-circle" style="color: #10b981; font-size: 1rem; position: absolute; top: 1.25rem; right: 1.25rem;" title="Verified Achievement"></i>
                                                <?php
            else: ?>
                                                    <a href="javascript:void(0)" onclick="navigatePost('project_viva', {id: '<?php echo $proj['id']; ?>'})" class="verify-badge-link" title="Verify via AI Viva">
                                                        <div class="verify-badge-pill">
                                                            <i class="fas fa-shield-alt"></i>
                                                            <span>VERIFY</span>
                                                        </div>
                                                    </a>
                                                <?php
            endif; ?>
                                            </div>
                                            <p><?php echo htmlspecialchars((string)substr($proj['description'] ?? '', 0, 100)) . (strlen($proj['description'] ?? '') > 100 ? '...' : ''); ?></p>
                                        </div>
                                    <?php
        endforeach; ?>
                                    
                                    <?php if (count($byCat['Project']) > 3): ?>
                                        <div style="text-align: right; margin-top: -5px;">
                                            <a href="javascript:void(0)" onclick="openPortfolioModal()" style="font-size: 0.75rem; color: var(--primary-maroon); font-weight: 700; text-decoration: none;">
                                                <i class="fas fa-external-link-alt"></i> View all <?php echo count($byCat['Project']); ?> projects
                                            </a>
                                        </div>
                                    <?php
        endif; ?>
                                </div>
                            <?php
    else: ?>
                                <p style="font-size: 0.8rem; color: #999;">No projects highlighted yet.</p>
                            <?php
    endif; ?>
                        </div>
                        <div>
                            <h4 style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.25rem; letter-spacing: 1.5px; font-weight: 700;">Top Skills</h4>
                            <div class="skill-pill-container">
                                <?php if (!empty($byCat['Skill'])): ?>
                                    <?php foreach ($byCat['Skill'] as $skill): ?>
                                        <div class="skill-pill <?php echo $skill['is_verified'] ? 'verified' : ''; ?>">
                                            <?php echo htmlspecialchars((string)($skill['title'] ?? '')); ?>
                                            <?php if (!$skill['is_verified']): ?>
                                                <a href="javascript:void(0)" onclick="navigatePost('skill_quiz', {id: '<?php echo $skill['id']; ?>'})" style="color: #94a3b8; transition: 0.2s;"><i class="fas fa-shield-alt" style="font-size: 0.65rem;"></i></a>
                                            <?php
            endif; ?>
                                        </div>
                                    <?php
        endforeach; ?>
                                <?php
    else: ?>
                                    <p style="font-size: 0.8rem; color: #999;">No skills added yet.</p>
                                <?php
    endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Announcements / Info -->
                <div class="bento-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bullhorn" style="color: #f39c12;"></i> Campus Feed</h3>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php if (!empty($feedItems)): ?>
                            <?php foreach ($feedItems as $idx => $fItem): ?>
                                <a href="javascript:void(0)" onclick="navigatePost('<?php echo htmlspecialchars($fItem['link']); ?>', {id: '<?php echo $fItem['id'] ?? ''; ?>'})" style="display: flex; gap: 12px; text-decoration: none; color: inherit; <?php echo $idx > 0 ? 'padding-top: 12px; border-top: 1px solid #f5f5f5;' : ''; ?>">
                                    <div style="width: 8px; height: 8px; background: <?php echo $fItem['color']; ?>; border-radius: 50%; margin-top: 6px;"></div>
                                    <div>
                                        <div style="font-size: 0.85rem; font-weight: 700;"><?php echo htmlspecialchars((string)($fItem['title'] ?? '')); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars((string)($fItem['subtitle'] ?? '')); ?></div>
                                    </div>
                                </a>
                            <?php
        endforeach; ?>
                        <?php
    else: ?>
                            <p style="font-size: 0.8rem; color: var(--text-muted); text-align: center;">No new updates tonight.</p>
                        <?php
    endif; ?>
                    </div>
                    
                    <!-- <div style="margin-top: 2rem; background: #fff4e5; padding: 1.25rem; border-radius: 16px; border: 1px solid #ffe8cc;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <div style="font-size: 0.8rem; font-weight: 800; color: #e67e22; text-transform: uppercase; letter-spacing: 0.5px;">AI Resume Analyzer</div>
                                <p style="font-size: 0.75rem; color: #d35400; margin-top: 5px;">Get a brutally honest, recruiter-level analysis in seconds.</p>
                            </div>
                            <div style="font-size: 1.5rem;">🕵️‍♂️</div>
                        </div>
                        <a href="resume_analyzer.php" style="display: inline-block; margin-top: 10px; font-size: 0.8rem; font-weight: 800; color: #e67e22; text-decoration: none; background: #fff; padding: 6px 12px; border-radius: 8px; box-shadow: 0 2px 4px rgba(230,126,34,0.1);">Analyze Now →</a>
                    </div> -->
                </div>
            </div>
            
            <?php if (!$profile): ?>
                <div style="background: #fff4f4; color: #c53030; padding: 2rem; border-radius: 20px; text-align: center; font-weight: 600; border: 1px solid #fecaca;">
                    ⚠️ Profile configuration missing. Please update your details in the office.
                </div>
            <?php
    endif; ?>
            <?php
endif; ?>
        </main>
    </div>

    <!-- Portfolio Management Modal -->
    <div id="portfolioModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Portfolio Management</h2>
                <span class="close-modal" onclick="closePortfolioModal()">&times;</span>
            </div>
            
            <div class="modal-tabs">
                <div class="m-tab active" onclick="switchModalTab('add')">Add New Entries</div>
                <div class="m-tab" onclick="switchModalTab('view')">View My Portfolio</div>
            </div>

            <div class="modal-body" id="modalViewAdd">
                <form id="portfolioForm">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" id="pfCategory" required onchange="setupPortfolioUI()">
                            <option value="Project">Project Highlight</option>
                            <option value="Skill">Technical Skill</option>
                            <option value="Certification">Certification</option>
                            <option value="Personal Intro">Personal Intro (Media)</option>
                        </select>
                    </div>

                    <div id="pfSkillModeHint" style="display: none; background: #fffdf0; padding: 12px; border-radius: 12px; border: 1px solid #fef08a; margin-bottom: 1.5rem; font-size: 0.8rem; color: #854d0e; line-height: 1.4;">
                        <i class="fas fa-magic" style="margin-right: 5px;"></i> <strong>Bulk Add:</strong> Separate skills with commas (e.g. <i>React, Node.js, AWS</i>) to save time!
                    </div>

                    <div id="pfSkillGroupsContainer" style="display: none;">
                        <div id="db-skill-groups-list"></div>
                        <button type="button" onclick="addSkillGroupDashboard()" style="background: #f8fafc; color: #475569; border: 2px dashed #cbd5e1; width: 100%; padding: 12px; border-radius: 12px; cursor: pointer; font-size: 0.85rem; font-weight: 700; margin-bottom: 2rem;">
                            <i class="fas fa-plus-circle" style="color: var(--primary-maroon);"></i> Add Skill Category (e.g. Languages)
                        </button>
                    </div>

                    <div id="pfEntriesContainer">
                        <div class="pf-entry-row">
                            <div class="form-group row-title-group">
                                <label class="form-label">Title / Name</label>
                                <input type="text" class="pf-input pf-input-title" required placeholder="Project Name or Skill">
                            </div>
                            
                            <div class="form-group row-subtitle-group">
                                <label class="form-label">Sub-title / Role</label>
                                <input type="text" class="pf-input pf-input-subtitle" placeholder="e.g. Lead Developer">
                            </div>

                            <div class="form-group row-proficiency-group" style="display: none;">
                                <label class="form-label">Proficiency Level</label>
                                <select class="pf-input-proficiency">
                                    <option value="Beginner">Beginner</option>
                                    <option value="Intermediate">Intermediate</option>
                                    <option value="Expert" selected>Expert</option>
                                </select>
                            </div>

                            <div class="pf-row-date-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1.25rem;">
                                <div>
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="pf-input pf-input-start">
                                </div>
                                <div class="pf-end-date-wrapper">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="pf-input pf-input-end">
                                    <label style="display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 0.75rem; color: #4a5568; cursor: pointer; font-weight: 600;">
                                        <input type="checkbox" class="pf-input-ongoing" onchange="toggleEndDate(this)" style="cursor: pointer; width: 14px; height: 14px;">
                                        <span>Currently Working</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group row-link-group">
                                <label class="form-label">Project / Demo Link</label>
                                <input type="url" class="pf-input pf-input-link" placeholder="https://github.com/your-repo">
                            </div>

                            <div class="form-group row-file-group-photo" style="display: none;">
                                <label class="form-label">Profile Photo <span style="color:red">*</span></label>
                                <input type="file" class="pf-input pf-input-file-photo" name="file_upload_photo" accept="image/*">
                                <p style="font-size: 0.75rem; color: #718096; margin-top: 6px;">Recommended: 1:1 Aspect Ratio (Square). Max 5MB.</p>
                            </div>

                            <div class="form-group row-file-group-video" style="display: none;">
                                <label class="form-label">Self Intro Video <span style="color:red">*</span></label>
                                <input type="file" class="pf-input pf-input-file-video" name="file_upload_video" accept="video/*">
                                <p style="font-size: 0.75rem; color: #718096; margin-top: 6px;">Format: MP4. Length: 1-2 minutes. Max 10MB.</p>
                            </div>

                            <div class="form-group row-file-group-cert" style="display: none;">
                                <label class="form-label">Upload Certificates</label>
                                <input type="file" class="pf-input pf-input-file-cert" name="certificate_files[]" accept="image/*,.pdf" multiple>
                                <p style="font-size: 0.75rem; color: #718096; margin-top: 6px;">Select one or more PDF/Image files.</p>
                            </div>

                            <div class="form-group row-desc-group">
                                <label class="form-label">Short Description</label>
                                <textarea class="pf-input pf-input-desc" rows="2" placeholder="Briefly explain your contribution or achievement..."></textarea>
                            </div>
                            
                            <button type="button" class="remove-row-btn" onclick="removePfRow(this)" style="display:none; position:absolute; top:-12px; right:-12px; background:#e53e3e; color:white; border:none; border-radius:50%; width:28px; height:28px; cursor:pointer; box-shadow: 0 4px 8px rgba(229, 62, 62, 0.3); align-items: center; justify-content: center; z-index: 10;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div id="pfAddRowContainer" style="margin-bottom: 2rem;">
                        <button type="button" onclick="addPfRow()" style="background: white; color: #4a5568; border: 2px dashed #e2e8f0; width: 100%; padding: 14px; border-radius: 14px; cursor: pointer; font-size: 0.9rem; font-weight: 700; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-plus-circle" style="color: var(--primary-maroon);"></i> Add Another Item
                        </button>
                    </div>

                    <button type="submit" class="submit-btn" id="pfSubmitBtn">Sync with Profile</button>
                </form>
            </div>

            <div class="modal-body" id="modalViewPortfolio" style="display: none;">
                <div id="pfListLoading" style="text-align: center; padding: 2rem;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size: 2rem; color: var(--primary-maroon);"></i>
                    <p style="margin-top: 15px; font-weight: 600; color: #718096;">Fetching your digital footprint...</p>
                </div>
                <div id="pfListContainer">
                    <!-- Dynamic List -->
                </div>
            </div>
        </div>
    </div>

        </div>
    </div>

    <!-- Verification Suggestion Modal -->
    <div id="verifySuggestionModal" class="pf-modal">
        <div class="pf-modal-content" style="max-width: 500px; text-align: center;">
            <div id="verifyIcon" style="font-size: 3rem; color: var(--primary-maroon); margin-bottom: 1.5rem;">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2 id="verifySuggestTitle" style="margin-bottom: 1rem;">Items Added Successfully!</h2>
            <p id="verifySuggestMsg" style="color: var(--text-muted); margin-bottom: 2rem;">
                Verification increases your placement probability by <strong>40%</strong>. Would you like to verify these items now?
            </p>
            <div id="verifyItemsList" style="text-align: left; margin-bottom: 2rem; max-height: 200px; overflow-y: auto;">
                <!-- List of items to verify will be injected here -->
            </div>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button class="pf-btn pf-btn-secondary" onclick="location.reload()" style="flex: 1;">Maybe Later</button>
                <div id="verifyActionContainer" style="flex: 1; display: flex; flex-direction: column; gap: 8px;">
                    <!-- Action buttons injected here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Company Guide Selection Modal -->
    <div id="guideModal" class="pf-modal">
        <div class="pf-modal-content" style="max-width: 500px;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 3rem; color: var(--accent-gold); margin-bottom: 1rem;">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2 style="color: var(--primary-maroon);">Target Your Future</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Select a company to generate your personalized placement roadmap.</p>
            </div>
            
            <div class="form-group">
                <label class="form-label">Target Company Name</label>
                <input type="text" id="guideCompanyInput" placeholder="e.g. Infosys, Google, TCS..." style="width: 100%; padding: 15px; border-radius: 12px; border: 2px solid #eee; font-family: 'Outfit'; font-size: 1rem;">
            </div>
            
            <div style="margin-top: 2rem; display: flex; gap: 10px;">
                <button class="pf-btn pf-btn-secondary" onclick="closeGuideModal()" style="flex: 1;">Cancel</button>
                <button class="pf-btn pf-btn-primary" onclick="generateGuide()" style="flex: 2;">Generate Guide</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        function openPortfolioModal() {
            document.getElementById('portfolioModal').style.display = 'flex';
            switchModalTab('add');
            setupPortfolioUI();
        }

        function closePortfolioModal() {
            document.getElementById('portfolioModal').style.display = 'none';
        }

        function switchModalTab(tab) {
            const addView = document.getElementById('modalViewAdd');
            const listView = document.getElementById('modalViewPortfolio');
            const tabs = document.querySelectorAll('.m-tab');
            
            tabs.forEach(t => t.classList.remove('active'));
            
            if (tab === 'add') {
                addView.style.display = 'block';
                listView.style.display = 'none';
                tabs[0].classList.add('active');
            } else {
                addView.style.display = 'none';
                listView.style.display = 'block';
                tabs[1].classList.add('active');
                loadPortfolioList();
            }
        }

        async function loadPortfolioList() {
            const container = document.getElementById('pfListContainer');
            const loader = document.getElementById('pfListLoading');
            
            container.innerHTML = '';
            loader.style.display = 'block';
            
            try {
                const response = await fetch('portfolio_handler', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'list' })
                });
                const result = await response.json();
                
                loader.style.display = 'none';
                
                if (result.success && result.items.length > 0) {
                    result.items.forEach(item => {
                        if (item.category === 'Certification' && item.certificate_attachments) {
                            // Portfolio Handler groups certs in JSON
                            const certs = JSON.parse(item.certificate_attachments);
                            certs.forEach((c, idx) => {
                                renderPfItem(container, {
                                    id: item.id,
                                    sub_id: c.id || idx,
                                    category: 'Certification',
                                    title: c.title,
                                    sub_title: c.sub_title,
                                    is_subitem: true
                                });
                            });
                        } else {
                            renderPfItem(container, item);
                        }
                    });
                } else {
                    container.innerHTML = `
                        <div style="text-align:center; padding: 2.5rem; color: #a0aec0;">
                            <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 1.5rem; opacity: 0.3;"></i>
                            <p style="font-weight: 600;">Your portfolio is currently a blank canvas.</p>
                            <p style="font-size: 0.85rem; margin-top: 5px;">Start adding projects and skills to stand out!</p>
                        </div>
                    `;
                }
            } catch (e) {
                loader.style.display = 'none';
                container.innerHTML = '<p style="color:red; text-align:center;">Failed to connect to portfolio server.</p>';
            }
        }

        function renderPfItem(container, item) {
            let icon = 'fa-folder';
            if(item.category === 'Skill') icon = 'fa-code';
            if(item.category === 'Certification') icon = 'fa-certificate';
            if(item.category === 'Personal Intro') icon = 'fa-user-circle';
            if(item.category === 'Project') icon = 'fa-lightbulb';

            const div = document.createElement('div');
            div.className = 'portfolio-list-item';
            
            // For grouped certs, use a different delete logic if needed
            const deleteAction = item.is_subitem 
                ? `deleteCertSubItem(${item.id}, '${item.sub_id}')` 
                : `deletePfItem(${item.id})`;

            const isVerified = parseInt(item.is_verified) === 1;
            const hasAttempted = item.verification_score !== null;
            let verifyUrl = 'skill_quiz';
            if (item.category === 'Project') verifyUrl = 'project_viva';
            if (item.category === 'Certification') verifyUrl = 'certification_viva';

            let verifyBtn = '';
            let unverifiedHint = '';
            if (item.category === 'Skill' || item.category === 'Project') {
                if (isVerified) {
                    verifyBtn = `<span style="color:#00875a; margin-right:10px;" title="Verified Profile"><i class="fas fa-check-circle"></i></span>`;
                } else {
                    const btnText = hasAttempted ? 'RE-VERIFY' : 'VERIFY';
                    const btnColor = hasAttempted ? '#f6993f' : 'var(--primary-maroon)';
                    verifyBtn = `<a href="javascript:void(0)" onclick="navigatePost('${verifyUrl}', {id: '${item.id}'})" class="p-action-btn" title="${hasAttempted ? 'Previously Attempted - Try Again' : 'Verify via AI'}" 
                                    style="color:${btnColor}; text-decoration:none; font-size:0.7rem; font-weight:700; display:flex; align-items:center; gap:4px; padding:4px 8px; border:1px solid ${btnColor}; border-radius:4px;">
                                    <i class="fas fa-shield-alt"></i> ${btnText}
                                 </a>`;
                    unverifiedHint = `<div style="font-size:0.6rem; color:#e53e3e; margin-top:4px; font-weight:600; font-style:italic;">* Temporary (Auto-deletes in 7 days if unverified)</div>`;
                }
            }

            div.innerHTML = `
                <div class="p-item-info">
                    <span style="display:flex; align-items:center; gap:5px;">
                        <i class="fas ${icon}" style="color:var(--primary-maroon); font-size:0.9rem;"></i> 
                        <span style="font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">${item.category}</span>
                    </span>
                    <h4 style="margin:5px 0;">${item.title}</h4>
                    ${item.sub_title ? `<span style="font-size:0.8rem; color:var(--text-muted);">${item.sub_title}</span>` : ''}
                    ${unverifiedHint}
                </div>
                <div class="p-item-actions" style="display:flex; align-items:center; gap:12px;">
                    ${verifyBtn}
                    <button class="p-action-btn p-btn-delete" onclick="${deleteAction}" title="Delete Item" style="background:none; border:none; color:#ff4d4f; cursor:pointer;">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            container.appendChild(div);
        }

        async function deleteCertSubItem(rowId, certId) {
            if (!confirm('Remove this certificate?')) return;
            try {
                const response = await fetch('portfolio_handler', {
                    method: 'POST',
                    body: new URLSearchParams({ 
                        action: 'delete_cert_subitem', 
                        row_id: rowId, 
                        cert_id: certId 
                    })
                });
                const result = await response.json();
                if (result.success) loadPortfolioList();
            } catch (e) { alert('Action failed.'); }
        }

        function setupPortfolioUI() {
            const cat = document.getElementById('pfCategory').value;
            const rows = document.querySelectorAll('.pf-entry-row');
            const addBtnCont = document.getElementById('pfAddRowContainer');
            const skillHint = document.getElementById('pfSkillModeHint');
            const submitBtn = document.getElementById('pfSubmitBtn');
            
            if (cat === 'Personal Intro') {
                const rowsList = document.querySelectorAll('.pf-entry-row');
                for(let i = 1; i < rowsList.length; i++) rowsList[i].remove();

                skillHint.style.display = 'none';
                addBtnCont.style.display = 'none';
                submitBtn.innerText = 'Upload My Story';
                
                const row = document.querySelector('.pf-entry-row');
                row.querySelector('.remove-row-btn').style.display = 'none';
                row.querySelector('.pf-input-title').placeholder = 'e.g. My Career Vision';
                row.querySelector('.pf-input-title').required = true;
                
                row.querySelector('.row-subtitle-group').style.display = 'none';
                row.querySelector('.row-subtitle-group input').required = false;
                
                row.querySelector('.row-proficiency-group').style.display = 'none';
                row.querySelector('.row-proficiency-group select').required = false;

                row.querySelector('.pf-row-date-group').style.display = 'none';
                row.querySelectorAll('.pf-row-date-group input').forEach(i => i.required = false);
                
                row.querySelector('.row-link-group').style.display = 'none';
                row.querySelector('.row-link-group input').required = false;
                
                row.querySelector('.row-desc-group').style.display = 'block';
                row.querySelector('.row-file-group-photo').style.display = 'block';
                row.querySelector('.row-file-group-video').style.display = 'block';
                row.querySelector('.row-file-group-cert').style.display = 'none';
                
            } else if (cat === 'Skill') {
                skillHint.style.display = 'block';
                addBtnCont.style.display = 'none';
                document.getElementById('pfEntriesContainer').style.display = 'none';
                document.getElementById('pfSkillGroupsContainer').style.display = 'block';
                
                // Remove required from all entry row inputs when in Skill mode
                document.querySelectorAll('.pf-entry-row input, .pf-entry-row textarea, .pf-entry-row select').forEach(i => i.required = false);
                
                submitBtn.innerText = 'Sync All Skills';
                
                // If empty category, attempt to load existing skills
                if (document.getElementById('db-skill-groups-list').children.length === 0) {
                    fetch('portfolio_handler', {
                        method: 'POST',
                        body: new URLSearchParams({ action: 'list' })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.items) {
                            const portfolioSkills = data.items.filter(i => i.category === 'Skill');
                            if (portfolioSkills.length > 0) {
                                const groups = {};
                                portfolioSkills.forEach(s => {
                                    const cat = s.sub_title || 'Technical Skills';
                                    if (!groups[cat]) groups[cat] = [];
                                    groups[cat].push(s.title);
                                });
                                for (const cat in groups) {
                                    addSkillGroupDashboard(cat, groups[cat]);
                                }
                            } else {
                                addSkillGroupDashboard('Technical Skills');
                            }
                        }
                    });
                }
            } else {
                skillHint.style.display = 'none';
                addBtnCont.style.display = 'block';
                document.getElementById('pfEntriesContainer').style.display = 'block';
                document.getElementById('pfSkillGroupsContainer').style.display = 'none';
                submitBtn.innerText = cat === 'Project' ? 'Publicize Projects' : 'Archive Certifications';
                
                const rowsList = document.querySelectorAll('.pf-entry-row');
                rowsList.forEach((row, idx) => {
                    row.querySelector('.remove-row-btn').style.display = idx === 0 ? 'none' : 'flex';
                    row.querySelector('.row-proficiency-group').style.display = 'none';
                    row.querySelector('.row-subtitle-group').style.display = 'block';
                    row.querySelector('.row-link-group').style.display = 'block';
                    row.querySelector('.row-desc-group').style.display = 'block';
                    row.querySelector('.row-file-group-photo').style.display = 'none';
                    row.querySelector('.row-file-group-video').style.display = 'none';
                    row.querySelector('.row-file-group-cert').style.display = cat === 'Certification' ? 'block' : 'none';
                    
                    // Fields that are ALWAYS required in these modes:
                    row.querySelector('.pf-input-title').required = true;

                    if (cat === 'Certification') {
                        row.querySelector('.pf-input-title').placeholder = 'Credential Name';
                        row.querySelector('.pf-input-subtitle').placeholder = 'e.g. Google Cloud';
                        row.querySelector('.pf-input-link').placeholder = 'Credential URL (Optional)';
                        row.querySelector('.pf-row-date-group').style.display = 'none';
                        row.querySelectorAll('.pf-row-date-group input').forEach(i => i.required = false);
                    } else {
                        row.querySelector('.pf-input-title').placeholder = 'Elite Project Name';
                        row.querySelector('.pf-input-subtitle').placeholder = 'e.g. Automation Script';
                        row.querySelector('.pf-input-link').placeholder = 'GitHub/Deploy Link';
                        row.querySelector('.pf-row-date-group').style.display = 'grid';
                        // row.querySelector('.pf-input-start').required = true; // Optional: make start date required for projects
                    }
                });
            }
        }

        function addPfRow() {
            const container = document.getElementById('pfEntriesContainer');
            const firstRow = container.querySelector('.pf-entry-row');
            const newRow = firstRow.cloneNode(true);
            
            newRow.querySelectorAll('input, textarea').forEach(i => i.value = '');
            newRow.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
            newRow.querySelector('.remove-row-btn').style.display = 'flex';
            
            container.appendChild(newRow);
            setupPortfolioUI();
        }

        function toggleEndDate(checkbox) {
            const row = checkbox.closest('.pf-entry-row');
            const endDateInput = row.querySelector('.pf-input-end');
            
            if (checkbox.checked) {
                endDateInput.disabled = true;
                endDateInput.value = '';
                endDateInput.style.opacity = '0.5';
            } else {
                endDateInput.disabled = false;
                endDateInput.style.opacity = '1';
            }
        }

        function removePfRow(btn) {
            btn.closest('.pf-entry-row').remove();
            setupPortfolioUI();
        }

        document.getElementById('portfolioForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('pfSubmitBtn');
            const cat = document.getElementById('pfCategory').value;
            const rows = document.querySelectorAll('.pf-entry-row');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

            const formData = new FormData();
            formData.append('category', cat);
            formData.append('action', 'add');

            if (cat === 'Skill') {
                const groups = collectDashboardSkills();
                const syncData = new FormData();
                syncData.append('action', 'sync_skills');
                syncData.append('skill_groups', JSON.stringify(groups));
                
                try {
                    const response = await fetch('portfolio_handler', { method: 'POST', body: syncData });
                    const result = await response.json();
                    if (result.success) {
                        location.reload();
                    } else {
                        alert(result.message);
                        btn.disabled = false; btn.innerText = 'Sync All Skills';
                    }
                } catch (e) {
                    alert('Sync failed.');
                    btn.disabled = false; btn.innerText = 'Sync All Skills';
                }
                return;
            }

            if (rows.length > 1) {
                formData.append('is_bulk', 'true');
                const items = [];
                rows.forEach(row => {
                    const titleRaw = row.querySelector('.pf-input-title').value;
                    if (!titleRaw) return;

                    if (cat === 'Skill' && titleRaw.includes(',')) {
                        const multiSkills = titleRaw.split(',').map(s => s.trim()).filter(s => s.length > 0);
                        multiSkills.forEach(sName => {
                            items.push({
                                title: sName,
                                sub_title: row.querySelector('.pf-input-proficiency').value,
                                link: '',
                                description: '',
                                start_date: null,
                                end_date: null
                            });
                        });
                    } else {
                        const isOngoing = row.querySelector('.pf-input-ongoing')?.checked || false;
                        items.push({
                            title: titleRaw,
                            sub_title: cat === 'Skill' ? row.querySelector('.pf-input-proficiency').value : row.querySelector('.pf-input-subtitle').value,
                            link: row.querySelector('.pf-input-link')?.value || '',
                            description: row.querySelector('.pf-input-desc')?.value || '',
                            start_date: row.querySelector('.pf-input-start')?.value || null,
                            end_date: isOngoing ? null : (row.querySelector('.pf-input-end')?.value || null)
                        });
                    }
                });
                formData.append('items', JSON.stringify(items));
            }
            else {
                const row = rows[0];
                const isOngoing = row.querySelector('.pf-input-ongoing')?.checked || false;
                formData.append('title', row.querySelector('.pf-input-title').value);
                formData.append('sub_title', row.querySelector('.pf-input-subtitle').value);
                formData.append('link', row.querySelector('.pf-input-link').value);
                formData.append('description', row.querySelector('.pf-input-desc').value);
                formData.append('start_date', row.querySelector('.pf-input-start')?.value || '');
                formData.append('end_date', isOngoing ? '' : (row.querySelector('.pf-input-end')?.value || ''));

                if (cat === 'Personal Intro') {
                    const photoInput = row.querySelector('.pf-input-file-photo');
                    const videoInput = row.querySelector('.pf-input-file-video');
                    if (photoInput.files[0]) formData.append('file_upload_photo', photoInput.files[0]);
                    if (videoInput.files[0]) formData.append('file_upload_video', videoInput.files[0]);
                }

                if (cat === 'Certification' && row.querySelector('.pf-input-file-cert').files.length > 0) {
                    const certInput = row.querySelector('.pf-input-file-cert');
                    for (let i = 0; i < certInput.files.length; i++) {
                        formData.append('certificate_files[]', certInput.files[i]);
                    }
                }
            }

            try {
                const response = await fetch('portfolio_handler', { 
                    method: 'POST', 
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                
                if (result.success) {
                    btn.disabled = false; 
                    btn.innerText = 'Sync with Profile';
                    
                    if (result.category === 'Skill' || result.category === 'Project') {
                        showVerifySuggestion(result);
                    } else {
                        location.reload(); 
                    }
                } else {
                    alert(result.message);
                    btn.disabled = false;
                    btn.innerText = 'Sync with Profile';
                }
            } catch (e) {
                console.error('Portfolio Sync Error:', e);
                alert('Connection failure: ' + e.message);
                btn.disabled = false;
                btn.innerText = 'Sync with Profile';
            }
        });

        function showVerifySuggestion(result) {
            const modal = document.getElementById('verifySuggestionModal');
            const list = document.getElementById('verifyItemsList');
            const actionCont = document.getElementById('verifyActionContainer');
            const title = document.getElementById('verifySuggestTitle');
            const msg = document.getElementById('verifySuggestMsg');
            
            list.innerHTML = '';
            actionCont.innerHTML = '';
            
            const isSkill = result.category === 'Skill';
            title.innerText = isSkill ? 'Skills Added! 💻' : 'Project Added! 🚀';
            msg.innerHTML = `Verified ${isSkill ? 'skills' : 'projects'} are <strong>prioritized</strong> by recruiters. 
                             <div style="background:#fee2e2; color:#b91c1c; padding:10px; border-radius:8px; margin-top:10px; font-size:0.8rem;">
                                 <i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> If these are not verified, they will be deleted after a week!
                             </div>`;
            
            result.new_items.forEach(item => {
                const div = document.createElement('div');
                div.style.padding = '10px';
                div.style.borderBottom = '1px solid #f0f0f0';
                div.style.fontSize = '0.9rem';
                div.innerHTML = `<i class="fas ${isSkill ? 'fa-code' : 'fa-lightbulb'}" style="color: var(--primary-maroon); margin-right: 8px;"></i> ${item.title}`;
                list.appendChild(div);
                
                // For multiple items, we direct them to the first one for now, or the general verify list
                // If single item, direct to quiz
            });

            const id = result.new_items[0].id;
            const target = isSkill ? 'skill_quiz.php' : (result.category === 'Project' ? 'project_viva.php' : 'certification_viva.php');
            actionCont.innerHTML = `<button onclick="navigatePost('${target}', {id: '${id}'})" class="pf-btn pf-btn-primary" style="text-decoration: none; text-align: center; border:none; cursor:pointer; width:100%;">Verify Now</button>`;

            modal.style.display = 'flex';
        }

        function toggleVisibility(id) {
            const el = document.getElementById(id);
            if (el.style.display === 'none') {
                el.style.display = 'block';
            } else {
                el.style.display = 'none';
            }
        }

        async function deletePfItem(id) {
            if (!confirm('Are you sure you want to remove this?')) return;
            try {
                const response = await fetch('portfolio_handler', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'delete', id: id })
                });
                const result = await response.json();
                if (result.success) {
                    loadPortfolioList();
                }
            } catch (e) {
                alert('Action failed.');
            }
        }
        
        // Initial setup
        setupPortfolioUI();
        // Company Guide Logic
        function openGuideModal() {
            document.getElementById('guideModal').style.display = 'flex';
        }
        function closeGuideModal() {
            document.getElementById('guideModal').style.display = 'none';
        }
        function generateGuide() {
            const company = document.getElementById('guideCompanyInput').value.trim();
            if (!company) {
                alert('Please enter a company name (e.g., Infosys).');
                return;
            }
            navigatePost('company_placement_guide.php', { company: company });
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

        // ── DASHBOARD SKILL GROUPS ──────────────────────────────────
        let dbTechGroupCounter = 0;
        function addSkillGroupDashboard(category = '', items = []) {
            const id = dbTechGroupCounter++;
            const list = document.getElementById('db-skill-groups-list');
            const div = document.createElement('div');
            div.className = 'db-tech-group-item';
            div.id = 'db-tech-group-' + id;
            div.innerHTML = `
                <div style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">
                    <input type="text" class="pf-input db-group-category" value="${category}" placeholder="Category (e.g. Frontend)" style="margin-bottom:0; flex:1;">
                    <button type="button" onclick="this.parentElement.parentElement.remove()" style="background:#fee2e2; color:#b91c1c; border:none; padding:8px 12px; border-radius:8px; cursor:pointer;"><i class="fas fa-trash"></i></button>
                </div>
                <div class="db-tags-container tags-container"></div>
                <div style="display:flex; gap:8px;">
                    <input type="text" class="pf-input pf-tag-input-el" placeholder="Add skill and press Enter" style="font-size:0.8rem; padding:8px 12px; margin-top:5px;">
                </div>
            `;
            list.appendChild(div);

            const input = div.querySelector('.pf-tag-input-el');
            const tagsCont = div.querySelector('.db-tags-container');

            items.forEach(it => addTagDashboard(tagsCont, it));

            input.onkeydown = function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (this.value.trim()) {
                        addTagDashboard(tagsCont, this.value.trim());
                        this.value = '';
                    }
                }
            };
        }

        function addTagDashboard(container, value) {
            const span = document.createElement('span');
            span.className = 'tag';
            span.innerHTML = `${value} <span class="tag-x" onclick="this.parentElement.remove()">✕</span>`;
            container.appendChild(span);
        }

        function collectDashboardSkills() {
            const groups = [];
            document.querySelectorAll('.db-tech-group-item').forEach(group => {
                const category = group.querySelector('.db-group-category').value.trim();
                const items = [];
                group.querySelectorAll('.tags-container .tag').forEach(tag => {
                    items.push(tag.firstChild.textContent.trim());
                });
                if (items.length > 0) {
                    groups.push({ category, items });
                }
            });
            return groups;
        }
    </script>
    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .db-tech-group-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .tag {
            background: var(--primary-maroon);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }
        .tag-x {
            cursor: pointer;
            opacity: 0.7;
            transition: 0.2s;
        }
        .tag-x:hover { opacity: 1; }
    </style>

    <!-- ===== AVATAR PICKER MODAL ===== -->
    <div class="avatar-picker-overlay" id="avatarPickerOverlay">
        <div class="avatar-picker-box">
            <div class="avatar-picker-header">
                <h3>🎨 Choose Your Avatar</h3>
                <button class="avatar-picker-close" id="avatarPickerCloseBtn">✕</button>
            </div>
            <p style="font-size:0.8rem;color:#888;margin-bottom:1.2rem;">Pick an animated character that represents you!</p>
            <div class="avatar-grid" id="avatarGrid"></div>
            <button class="avatar-save-btn" id="avatarSaveBtn">✅ Apply Avatar</button>
        </div>
    </div>

    <script>
    (function() {
        var AVATAR_KEY = 'lakshya_avatar_<?php echo htmlspecialchars($username, ENT_QUOTES); ?>';

        var AVATARS = [];
        // Generate diverse 3D-like avatars using DiceBear Micah style
        const baseStyles = ['micah', 'bottts', 'adventurer'];
        for(let i = 1; i <= 60; i++) {
            let seed = 'LakshyaUser_AvatarSeed_' + i; // Deterministic seed
            AVATARS.push({ 
                id: i.toString(), 
                url: `https://api.dicebear.com/8.x/micah/svg?seed=${seed}&radius=50&backgroundColor=b6e3f4,c0aede,d1d4f9&size=64` 
            });
        }

        var selectedAvatarId = null;

        function buildGrid() {
            var grid = document.getElementById('avatarGrid');
            grid.innerHTML = '';
            AVATARS.forEach(function(av) {
                var div = document.createElement('div');
                div.className = 'avatar-option' + (selectedAvatarId === av.id ? ' selected' : '');
                div.dataset.id = av.id;
                div.innerHTML = '<img src="' + av.url + '" style="width:64px;height:64px;border-radius:50%;object-fit:cover;" alt="Avatar"/>';
                div.addEventListener('click', function() {
                    document.querySelectorAll('.avatar-option').forEach(function(o) { o.classList.remove('selected'); });
                    div.classList.add('selected');
                    selectedAvatarId = av.id;
                });
                grid.appendChild(div);
            });
        }

        function applyAvatar(avatarId) {
            var av = AVATARS.find(a => a.id === avatarId);
            if (!av) return;
            var avUrl = av.url;
            var display  = document.getElementById('profileAvatarDisplay');
            var realPhoto = document.getElementById('profileRealPhoto');
            if (realPhoto) realPhoto.style.display = 'none';
            if (display) {
                display.style.display = 'flex';
                display.innerHTML = '<img src="' + avUrl + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="Avatar"/>';
            }
        }

        window.openAvatarPicker = function() {
            document.getElementById('avatarPickerOverlay').classList.add('open');
            buildGrid();
        };

        document.getElementById('avatarPickerCloseBtn').addEventListener('click', function() {
            document.getElementById('avatarPickerOverlay').classList.remove('open');
        });

        document.getElementById('avatarPickerOverlay').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('open');
        });

        document.getElementById('avatarSaveBtn').addEventListener('click', function() {
            if (!selectedAvatarId) { alert('Please select an avatar first!'); return; }
            localStorage.setItem(AVATAR_KEY, selectedAvatarId);
            applyAvatar(selectedAvatarId);
            document.getElementById('avatarPickerOverlay').classList.remove('open');
        });

        // Restore avatar on page load
        var saved = localStorage.getItem(AVATAR_KEY);
        if (saved) { selectedAvatarId = saved; applyAvatar(saved); }
    })();
    </script>

</body>
</html>

