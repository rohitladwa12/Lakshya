<?php
/**
 * Student Dashboard
 */

ob_start();
require_once __DIR__ . '/../../config/bootstrap.php';

// 3. Handle manual cache refresh via POST (Secured)
if (isPost() && isset($_POST['action']) && $_POST['action'] === 'refresh_cache') {
    $dataProxy = new \App\Services\RemoteDataProxy();
    $dataProxy->refreshCache(getUserId(), getInstitution());

    if (isAjax()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    Session::flash('success', 'Academic data synchronized successfully.');
    redirect('student/dashboard.php');
}

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$username = getUsername();
$fullName = getFullName();

// Detect first visit this session for splash screen
$showWelcomeSplash = false;
if (empty($_SESSION['dashboard_visited'])) {
    $showWelcomeSplash = true;
    $_SESSION['dashboard_visited'] = true;
}

function formatExplanation($explanation)
{
    if (empty($explanation))
        return '';
    $escaped = htmlspecialchars($explanation);
    $pattern = '/(Option\s+[A-D]\s+is|Option\s+[A-D]\s+are|Option\s+[A-D]\s+incorrect|Option\s+[A-D]\s+correct|Option\s+[A-D]:)/i';
    $formatted = preg_replace($pattern, '<br><br><strong>$1</strong>', $escaped);
    $formatted = preg_replace('/^(<br><br>)+/', '', $formatted);
    return nl2br($formatted);
}

// Handle cache refresh request
if (isset($_GET['refresh_cache']) && $_GET['refresh_cache'] == 1) {
    $proxy = new \App\Services\RemoteDataProxy();
    $inst = $_SESSION['institution'] ?? '';
    $proxy->refreshCache($userId, $inst);
    // Redirect to clean URL
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

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

// Fetch student profile unconditionally to resolve dual-key identifiers
$institution = $_SESSION['institution'] ?? '';
$isGMIT = ($institution === INSTITUTION_GMIT);
$hasFullHistory = true;
$missingSemMsg = "";

require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$checkModel = new StudentProfile();
$history = $checkModel->getAcademicHistory($userId, $institution ?: 'GMU');
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

$needsSgpaUpdate = false;
if ($isGMIT) {
    try {
        $db = getDB();
        $studentIdToCheck = getUsername();
        $studentAadhar = $mainProfile['aadhar'] ?? '';
        
        // Fetch all sem sgpa records for this student
        $stmt = $db->prepare(
            "SELECT semester, sgpa, is_current, freezed FROM student_sem_sgpa 
             WHERE (student_id = ? OR student_id = ?) AND institution = ?"
        );
        $stmt->execute([$studentIdToCheck, $studentAadhar ?: $studentIdToCheck, INSTITUTION_GMIT]);
        $sgpaRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($sgpaRecords)) {
            $hasFullHistory = false;
            $needsSgpaUpdate = true;
        } else {
            $currentActiveSem = 0;
            $sem6Sgpa = 0.0;
            $anyFreezed = false;
            
            foreach ($sgpaRecords as $r) {
                if ($r['is_current'] == 1) {
                    $currentActiveSem = (int)$r['semester'];
                }
                if ($r['semester'] == 6) {
                    $sem6Sgpa = (float)$r['sgpa'];
                }
                if ($r['freezed'] == 1) {
                    $anyFreezed = true;
                }
            }
            
            // If not frozen:
            // - If current semester is 6 or less (they haven't moved to 7th sem yet)
            // - Or, if they don't have an active current semester
            // - Or, if they are marked in 7th/8th sem but 6th sem SGPA is empty or 0
            if (!$anyFreezed) {
                if ($currentActiveSem <= 6 || $sem6Sgpa <= 0) {
                    $needsSgpaUpdate = true;
                    $hasFullHistory = false; // Lock dashboard features
                }
            }
        }
    } catch (Exception $e) {
        error_log("Dashboard SGPA Check Error: " . $e->getMessage());
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

    $studentIdentifiers = array_unique(array_filter([$username, $mainProfile['usn'] ?? '', $mainProfile['aadhar'] ?? '']));

    // Build placeholders for tc.student_id IN (...)
    $tcPlaceholders = implode(',', array_fill(0, count($studentIdentifiers), '?'));
    
    // Build LIKE placeholders for ct.target_students LIKE ?
    $targetLikeSql = [];
    foreach ($studentIdentifiers as $id) {
        $targetLikeSql[] = "ct.target_students LIKE ?";
    }
    $targetLikeSqlStr = implode(' OR ', $targetLikeSql);

    $taskQuery = "SELECT ct.id, ct.task_type, ct.title, ct.description, 
                         ct.deadline, ct.company_name,
                         dc.department, dc.full_name as coordinator_name,
                         tc.id as completion_status
                  FROM coordinator_tasks ct
                  JOIN dept_coordinators dc ON ct.coordinator_id = dc.id
                  LEFT JOIN task_completions tc ON ct.id = tc.task_id 
                                                AND tc.student_id IN ($tcPlaceholders)
                  WHERE ct.is_active = 1 
                    AND (
                        (ct.target_type = 'department' AND dc.department = ? AND dc.institution = ?)
                        OR (ct.target_type = 'branch' AND JSON_CONTAINS(ct.target_branches, ?))
                        OR (ct.target_type = 'individual' AND ($targetLikeSqlStr))
                    )
                    AND (tc.id IS NULL OR tc.completed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) -- Show active OR completed in last 7 days
                    AND (tc.id IS NOT NULL OR ct.deadline > NOW())  -- Not expired if not completed
                  ORDER BY tc.id ASC, ct.deadline ASC
                  LIMIT 5";

    $stmtParams = [];
    // 1. student_id in LEFT JOIN task_completions
    foreach ($studentIdentifiers as $id) {
        $stmtParams[] = $id;
    }
    // 2. department and institution
    $stmtParams[] = $studentBranch;
    $stmtParams[] = $studentInstitution;
    // 3. branch
    $stmtParams[] = "\"$studentBranch\"";
    // 4. individual target search
    foreach ($studentIdentifiers as $id) {
        $stmtParams[] = "%\"$id\"%";
    }

    $stmt = $db->prepare($taskQuery);
    $stmt->execute($stmtParams);
    $assignedTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert tasks to feed items
    foreach ($assignedTasks as $task) {
        $taskColors = [
            'aptitude' => '#3498db',
            'technical' => '#e74c3c',
            'hr' => '#2ecc71'
        ];
        $isCompleted = !empty($task['completion_status']);
        $statusText = $isCompleted ? 'COMPLETED' : 'Due: ' . date('M d, h:i A', strtotime($task['deadline']));
        $iconHtml = $isCompleted ? '<i class="fas fa-check-circle" style="color:#2ecc71;"></i> ' : '<i class="fas fa-pen-nib" style="color:var(--primary-maroon);"></i> ';

        $feedItems[] = [
            'title' => $task['title'],
            'icon_html' => $iconHtml,
            'subtitle' => strtoupper($task['task_type']) . ' Task - ' . $statusText,
            'link' => 'assigned_task.php',
            'id' => $task['id'],
            'color' => $isCompleted ? '#94a3b8' : ($taskColors[$task['task_type']] ?? '#3498db')
        ];
    }
} catch (Exception $e) {
    // Silently fail if tasks can't be loaded
}

// Fetch active Campus Drives for the student
try {
    $jaPlaceholders = implode(',', array_fill(0, count($studentIdentifiers), '?'));
    $driveQuery = "SELECT cd.id, cd.drive_name, cd.deadline, cd.aptitude_active, cd.technical_active, cd.hr_active, c.name as company_name 
                   FROM campus_drives cd
                   JOIN job_applications ja ON cd.job_id = ja.job_id
                   JOIN job_postings jp ON cd.job_id = jp.id
                   LEFT JOIN companies c ON jp.company_id = c.id
                   WHERE ja.student_id IN ($jaPlaceholders) 
                     AND (cd.deadline IS NULL OR cd.deadline > NOW())";
    $stmtDrive = $db->prepare($driveQuery);
    $stmtDrive->execute($studentIdentifiers);
    $studentDrives = $stmtDrive->fetchAll(PDO::FETCH_ASSOC);

    foreach ($studentDrives as $drive) {
        $statusText = $drive['deadline'] ? 'Deadline: ' . date('M d, h:i A', strtotime($drive['deadline'])) : 'Active Now';
        
        // Build subtitle with active rounds
        $activeRounds = [];
        if ($drive['aptitude_active']) $activeRounds[] = 'Aptitude';
        if ($drive['technical_active']) $activeRounds[] = 'Technical';
        if ($drive['hr_active']) $activeRounds[] = 'HR';
        $roundsText = implode(', ', $activeRounds);
        if (empty($roundsText)) $roundsText = 'No active rounds';
        
        $subtitle = $drive['company_name'] . ' - Rounds: ' . $roundsText . ' | ' . $statusText;

        $feedItems[] = [
            'title' => 'Campus Drive: ' . $drive['drive_name'],
            'icon_html' => '<i class="fas fa-building" style="color:#8e44ad;"></i> ',
            'subtitle' => $subtitle,
            'link' => 'student_drive.php?drive_id=' . $drive['id'],
            'id' => 'drive_' . $drive['id'],
            'color' => '#8e44ad'
        ];
    }
} catch (Exception $e) {
    // Silently fail if campus drives can't be loaded
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
        $fItem['icon_html'] = '<i class="fas fa-bullhorn" style="color:#f39c12;"></i> ';
    }
    unset($fItem);

    // Merge announcements with tasks
    $feedItems = array_merge($feedItems ?? [], $announcements);
} catch (Exception $e) {
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
            'icon_html' => '<i class="fas fa-briefcase" style="color:var(--accent-gold);"></i> ',
            'subtitle' => $job['company_name'] . ' is hiring!',
            'link' => 'jobs.php',
            'color' => 'var(--accent-gold)'
        ];
    }
}

// --- AI PERSONALIZATION ENGINE LOADING ---
require_once __DIR__ . '/../../src/Services/StudentIntelligenceService.php';
require_once __DIR__ . '/../../src/Services/AIService.php';
$intelService = new \App\Services\StudentIntelligenceService();
$aiProfile = $intelService->getStudentAIProfile($username, $institution, $fullName);
$aiInsights = $intelService->getStudentInsights($username, $institution, $fullName);
$dailyChallenge = $intelService->getOrCreateDailyChallenge($username, $institution, $fullName);

// --- DAILY GRIND MOTIVATION ---
$grindQuotes = [
    "Nobody is going to hand you that offer letter. Go out there, put in the hours, and earn it!",
    "If you want the package that 99% of people don't get, you must do the work that 99% of people won't do.",
    "Bugs will test you. Compilers will reject you. The market will challenge you. But you do not quit. Keep grinding!",
    "The dream is free. The hustle is sold separately. Wake up, write code, and build your legacy!",
    "Every rejected test case is just raw material for a stronger comeback. Fix it, run it, and dominate.",
    "Don't complain about the difficulty of the test. Raise your level. Be so good they cannot ignore you.",
    "While others are sleeping, you should be solving. Let your execution do the talking.",
    "Your future self is watching you right now, hoping you don't give up. Make them proud today!",
    "The pain of discipline is temporary. The regret of not giving it your 100% is permanent. Put in the work!",
    "Champions are built in the quiet hours when nobody is watching. Write that extra function, solve that extra problem, and secure your future.",
    "Stop searching for shortcuts. The only way to the top is through consistent, daily execution.",
    "A rejection is just a redirection to something bigger. Analyze the feedback, level up, and apply again.",
    "If it was easy, everyone would be a top-tier developer. Embrace the struggle; it's refining you.",
    "Your degree gets you the interview, but your skills and hunger get you the job. Build something undeniable!",
    "Every line of code you write today is an investment in the lifestyle you want tomorrow.",
    "Quit talking about your goals. Put your head down, open the IDE, and let your results scream.",
    "The best way to predict your placement results is to build them yourself, one solved problem at a time.",
    "Don't wait for motivation. Cultivate discipline. Motivation gets you started; discipline gets you placed.",
    "The difference between an amateur and a professional is consistency. Solve problems even when you don't feel like it.",
    "If you fail a mock test today, you've found a weakness. Patch it before the real companies arrive.",
    "Your competition is working right now. What are you doing to stand out?",
    "Excel under pressure. The most challenging coding questions yield the most rewarding career breakthroughs.",
    "A year from now, you will wish you started pushing harder today. Make today count!",
    "Clear your mind, optimize your logic, and attack the problem. You are smarter than the compiler.",
    "Success doesn't care about your excuses. It only cares about your dedication and output.",
    "Every master was once a beginner who refused to stop when things got difficult.",
    "The code you write when you are tired is the code that defines your true work ethic. Keep pushing!",
    "Interviews are won in the prep phase. Do the mock runs. Learn the patterns. Leave nothing to luck.",
    "Do not let a single day pass without moving closer to your target package. Push your limits!",
    "An offer letter is won when you solve the problem everyone else gave up on.",
    "You are one algorithmic break-through away from changing your entire career trajectory.",
    "The secret of getting ahead is simply getting started. Open the editor and solve the first problem.",
    "You don't need luck when you have preparation, logic, and relentless determination.",
    "Be obsessed with solving. A problem is just a challenge waiting for your logic to conquer it.",
    "Don't pray for an easy placement season. Pray for the strength and capability to conquer a tough one.",
    "Stop wishing. Start coding. Action is the only antidote to doubt.",
    "Run the code. See the error. Fix the bug. Repeat until you are unstoppable.",
    "A high package isn't given; it's taken by those who out-prepare and out-perform everyone else.",
    "Excuses don't compile. Results do. Focus on the output.",
    "The hard work you put in today will pay off in ways you cannot even imagine yet.",
    "Make your portfolio so impressive that recruiters feel foolish passing on your application.",
    "Doubt kills more dreams than failure ever will. Believe in your code, believe in your logic.",
    "You didn't come this far only to come this far. Keep pushing, the finish line is near!",
    "The best developers aren't born; they are forged in the fire of countless failed test cases.",
    "Focus on the process, not just the outcome. The skills you build will stay with you forever.",
    "Your career is your responsibility. Take ownership, drive your preparation, and win.",
    "Every mistake is a lesson in disguise. Learn fast, adapt quicker, and keep moving.",
    "Great things never come from comfort zones. Step up, face the hard problems, and grow.",
    "You are entirely up to you. Make the decision to be great, then do the work.",
    "The only limit to your impact is your imagination and commitment. Push beyond the limits.",
    "Every time you want to quit, remember why you started this journey in the first place.",
    "Success is the sum of small efforts, repeated day in and day out. Stay consistent!",
    "Don't measure yourself by where you are today. Measure yourself by the progress you make daily.",
    "You have the talent, you have the resources. All you need now is the relentless execution.",
    "When you feel like stopping, do one more problem. That's where the growth happens.",
    "Your attitude decides your aptitude. Approach every test with a winner's mindset.",
    "Don't let yesterday's failures take up too much of today. Reset, refocus, and rebuild.",
    "The grind is hard, but regret is harder. Choose your hard wisely.",
    "Work in silence. Let your offer letter make the noise.",
    "If you are the smartest person in the room, you are in the wrong room. Seek challenges.",
    "A problem solved is a skill earned. Collect as many skills as you can.",
    "Your potential is limitless, but only if you have the courage to test it.",
    "Be so prepared that when opportunity knocks, you don't just open the door—you take over.",
    "Don't settle for average. Aim for the top tier. You have what it takes.",
    "Every setback is a setup for a comeback. Stay resilient, stay focused.",
    "The roadmap is clear: Prepare, practice, perform. Now execute!",
    "Your code is your art, your logic is your weapon. Sharpen it every single day.",
    "Nothing worth having comes easy. The grind is part of the prize.",
    "Be the developer who solves the problems that keep others awake at night.",
    "Your career starts here, now. Don't look back, look forward to the target.",
    "Every day is another chance to improve. Make today's progress undeniable.",
    "Consistency beats talent when talent doesn't work hard. Keep showing up!",
    "The only way to fail is to stop trying. As long as you code, you are winning.",
    "Build a mindset that welcomes difficult challenges. That's where top CTCs are won.",
    "Stay hungry. Stay humble. Outwork everyone.",
    "You are writing your own success story right now. Make it a masterpiece.",
    "The best project you will ever work on is yourself. Invest time in your growth.",
    "Your work ethic is the one thing you can fully control. Make it elite.",
    "When it gets tough, remember: this is the filter that separates the best from the rest.",
    "Aim high, work hard, and never compromise on your career goals.",
    "A coding error is just a question. The fix is your answer. Keep asking, keep answering.",
    "Preparation is the key to confidence. Practice until the difficult feels natural.",
    "Don't just write code that works. Write code that inspires. Excel in your craft.",
    "Every hour you spend preparing now is a step toward your dream company.",
    "The compiler is your partner, not your enemy. Listen to the errors and grow.",
    "Your dreams don't work unless you do. Open the editor and get to work.",
    "Success is built on a foundation of failed attempts that you refused to let define you.",
    "Be the person who finds solutions while others are looking for excuses.",
    "Every challenge you face is an opportunity to prove your capability.",
    "The secret of success is constancy of purpose. Stay locked in on your target.",
    "Your future is created by what you do today, not tomorrow. Take action now.",
    "Make preparation a habit, and success will follow automatically.",
    "Push yourself, because no one else is going to do it for you.",
    "Great results require great effort. There are no shortcuts to excellence.",
    "Be relentless. If one approach fails, try ten more. The solution is out there.",
    "Your dedication today determines your placement package tomorrow.",
    "Never let a hard problem make you feel like you don't belong. You belong at the top.",
    "Focus on becoming a better version of yourself every single day.",
    "The preparation you do in private will be rewarded in public.",
    "Believe in your logic, trust your practice, and conquer the assessments.",
    "Wake up with determination. Sleep with satisfaction. Solve the code today!",
    "The best way to build confidence is to build projects that solve real-world problems.",
    "Success isn't about being the best; it's about being better than you were yesterday.",
    "Your only limit is the one you set in your own mind. Break through it.",
    "Out-prepare, out-practice, and out-perform. The offer letter is yours for the taking!"
];

if (!isset($_SESSION['grind_quote'])) {
    $_SESSION['grind_quote'] = $grindQuotes[array_rand($grindQuotes)];
}
$dailyQuote = $_SESSION['grind_quote'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo APP_NAME; ?></title>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&family=Outfit:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
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
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --gradient-maroon: linear-gradient(135deg, #800000 0%, #4a0000 100%);
            --gradient-gold: linear-gradient(135deg, #D4AF37 0%, #B8860B 100%);
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
            .dashboard-container {
                grid-template-columns: 1fr;
            }
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

        .profile-pic-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .student-card h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-maroon);
            margin-bottom: 0.25rem;
        }

        .student-card p {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .info-strip {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f0f0f0;
        }

        .info-box {
            text-align: left;
        }

        .info-box label {
            font-size: 0.65rem;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 700;
            display: block;
            margin-bottom: 2px;
        }

        .info-box span {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
        }

        /* Main Workspace Styling */
        .workspace {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        /* Modern Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, rgba(128, 0, 0, 0.04) 0%, rgba(212, 175, 55, 0.04) 100%);
            border: 1px solid rgba(128, 0, 0, 0.08);
            border-radius: 20px;
            padding: 1.25rem 2rem;
            color: var(--text-main);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px 0 rgba(128, 0, 0, 0.02);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            margin-bottom: 1rem;
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            bottom: -30px;
            right: -30px;
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }

        .hero-banner::before {
            content: '';
            position: absolute;
            top: -30px;
            left: -30px;
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(128, 0, 0, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }

        @keyframes wave {
            0% {
                transform: rotate(0.0deg)
            }

            10% {
                transform: rotate(14.0deg)
            }

            20% {
                transform: rotate(-8.0deg)
            }

            30% {
                transform: rotate(14.0deg)
            }

            40% {
                transform: rotate(-4.0deg)
            }

            50% {
                transform: rotate(10.0deg)
            }

            60% {
                transform: rotate(0.0deg)
            }

            100% {
                transform: rotate(0.0deg)
            }
        }

        .hero-content {
            z-index: 1;
        }

        .hero-content h2 {
            font-family: 'Caveat', cursive;
            font-size: 2.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, #b22222 50%, var(--accent-gold) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.1rem;
            letter-spacing: normal;
            display: inline-block;
        }

        .hero-content p {
            font-size: 0.85rem;
            color: #4b5563;
            max-width: 580px;
            line-height: 1.5;
            font-weight: 500;
        }



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
            width: 40px;
            height: 40px;
            background: var(--light-gold);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .action-tool-btn h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary-maroon);
        }

        /* Content Bento Grid */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 850px) {
            .bento-grid {
                grid-template-columns: 1fr;
            }
        }

        .bento-card {
            background: var(--white);
            border-radius: 20px;
            padding: 1.75rem;
            border: 1px solid #eee;
            box-shadow: var(--shadow-sm);
        }

        .bento-card-large {
            grid-column: span 2;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-header h3 {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

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

        .card-header .btn-small:hover {
            background: var(--primary-maroon);
            color: white;
        }

        /* Skill Grid */
        .skill-pill-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

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
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            position: relative;
        }

        .project-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-color: #e5e7eb;
        }

        .project-item:last-child {
            margin-bottom: 0;
        }

        .project-item h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary-maroon);
            line-height: 1.3;
            margin-bottom: 6px;
            padding-right: 60px;
        }

        .project-item p {
            font-size: 0.8rem;
            color: #6b7280;
            line-height: 1.5;
        }

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

        .verify-badge-pill i {
            font-size: 0.75rem;
            color: #c53030;
        }

        .verify-badge-pill span {
            font-size: 0.55rem;
            color: #c53030;
            font-weight: 800;
            text-transform: uppercase;
        }

        .verify-badge-pill:hover {
            background: #feb2b2;
            transform: scale(1.05);
        }

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
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(10deg);
        }

        .action-card h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-dark);
            transition: color 0.3s;
        }

        .action-card:hover h4,
        .action-card:hover p {
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

        .badge-new {
            background: var(--accent-gold);
            color: var(--primary-dark);
        }

        .badge-ai {
            background: #e0f2fe;
            color: #0369a1;
        }

        /* NQT Hub Specific Styles */
        .nqt-hub-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d0000 100%);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 3rem;
            border: 1px solid rgba(255, 215, 0, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
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
            border: 1px solid rgba(255, 255, 255, 0.1);
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
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
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

        .nqt-card h4 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent-gold);
        }

        .nqt-card p {
            font-size: 0.85rem;
            opacity: 0.7;
            line-height: 1.5;
        }

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
            .content-split {
                grid-template-columns: 1fr;
            }
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
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
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
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            transition: all 0.3s;
        }

        .pf-entry-row:hover {
            border-color: #cbd5e0;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.05);
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

        .pf-input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .pf-input:focus,
        .form-group select:focus {
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

        .p-item-info h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2d3748;
        }

        .p-item-info span {
            font-size: 0.75rem;
            color: #a0aec0;
            font-weight: 600;
        }

        .p-item-actions {
            display: flex;
            gap: 8px;
        }

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

        .p-btn-delete {
            background: #fff5f5;
            color: #e53e3e;
        }

        .p-btn-delete:hover {
            background: #e53e3e;
            color: white;
        }

        .portfolio-item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .portfolio-item-row:last-child {
            border-bottom: none;
        }

        .portfolio-item-info {
            flex: 1;
        }

        .portfolio-item-title {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .portfolio-item-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .delete-item-btn {
            color: #ff7675;
            cursor: pointer;
            font-size: 0.85rem;
            margin-left: 10px;
        }

        /* Shared Portfolio & Verification Modal Styles */
        .pf-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
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
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
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

        .pf-btn-primary {
            background: var(--primary-maroon);
            color: white;
        }

        .pf-btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .pf-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

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

            /* Compulsory SGPA Update Modal */
            .vtu-results-overlay {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(15, 23, 42, 0.95);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 99999;
                backdrop-filter: blur(12px);
            }
            .vtu-results-modal {
                background: #ffffff;
                border-radius: 24px;
                padding: 40px;
                width: 90%;
                max-width: 550px;
                text-align: center;
                box-shadow: 0 25px 50px -12px rgba(128, 0, 0, 0.35);
                border: 2px solid var(--accent-gold);
                animation: modalSlideIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            }
            @keyframes modalSlideIn {
                from { transform: scale(0.9) translateY(30px); opacity: 0; }
                to { transform: scale(1) translateY(0); opacity: 1; }
            }
            .vtu-modal-icon {
                font-size: 4rem;
                color: #e53e3e;
                margin-bottom: 20px;
                display: inline-block;
                animation: pulseWarning 1.5s infinite;
            }
            @keyframes pulseWarning {
                0% { transform: scale(1); filter: drop-shadow(0 0 0 rgba(229, 62, 98, 0)); }
                50% { transform: scale(1.08); filter: drop-shadow(0 0 15px rgba(229, 62, 98, 0.6)); }
                100% { transform: scale(1); filter: drop-shadow(0 0 0 rgba(229, 62, 98, 0)); }
            }
            .vtu-results-modal h2 {
                font-size: 1.8rem;
                font-weight: 800;
                color: var(--primary-maroon);
                margin-bottom: 15px;
            }
            .vtu-results-modal p {
                color: var(--text-muted);
                font-size: 1.05rem;
                line-height: 1.6;
                margin-bottom: 25px;
            }
            .vtu-results-modal .funny-sentence {
                background: #fff5f5;
                border: 1px dashed #feb2b2;
                border-radius: 12px;
                padding: 15px;
                color: #c53030;
                font-weight: 600;
                font-size: 1rem;
                margin-bottom: 30px;
                position: relative;
                text-align: center;
                line-height: 1.5;
            }
            .vtu-results-modal .funny-sentence::before {
                content: "🎓 ";
            }
            .vtu-update-btn {
                background: linear-gradient(135deg, var(--primary-maroon) 0%, #a82020 100%);
                color: white;
                font-weight: 700;
                font-size: 1.1rem;
                padding: 15px 30px;
                border-radius: 50px;
                border: none;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                box-shadow: 0 10px 20px rgba(128, 0, 0, 0.25);
                transition: all 0.3s ease;
                margin: 0 auto;
            }
            .vtu-update-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 15px 25px rgba(128, 0, 0, 0.35);
                background: linear-gradient(135deg, #a82020 0%, var(--primary-maroon) 100%);
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

        /* Real-time Toast Notifications */
        #notification-toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 15px;
            pointer-events: none;
        }

        .notification-toast {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-left: 5px solid var(--accent-gold);
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 350px;
            display: flex;
            gap: 15px;
            pointer-events: auto;
            animation: toastSlideIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        @keyframes toastSlideIn {
            from {
                transform: translateX(100%) scale(0.8);
                opacity: 0;
            }

            to {
                transform: translateX(0) scale(1);
                opacity: 1;
            }
        }

        .toast-exit {
            transform: translateX(120%);
            opacity: 0;
        }

        .toast-icon {
            width: 45px;
            height: 45px;
            background: var(--light-gold);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--primary-maroon);
            font-size: 1.2rem;
            overflow: hidden;
        }

        .toast-content h5 {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--primary-maroon);
            margin-bottom: 4px;
        }

        .toast-content p {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
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

        .profile-pic-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-pic-container .avatar-svg-wrap {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
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
            background: rgba(0, 0, 0, 0.55);
            z-index: 9999;
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .avatar-picker-overlay.open {
            display: flex;
        }

        .avatar-picker-box {
            background: #fff;
            border-radius: 28px;
            padding: 2rem;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
            animation: avatarPopIn 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes avatarPopIn {
            from {
                transform: scale(0.8);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
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
            width: 34px;
            height: 34px;
            background: #f5f5f5;
            border: none;
            border-radius: 50%;
            font-size: 1.1rem;
            cursor: pointer;
            color: #888;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }

        .avatar-picker-close:hover {
            background: #ffe0e0;
            color: #e53e3e;
            transform: rotate(90deg);
        }

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
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.2);
        }

        .avatar-option.selected {
            border-color: var(--primary-maroon);
            background: #fff8f8;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.08);
        }

        .avatar-option svg,
        .avatar-option .av-emoji {
            width: 64px;
            height: 64px;
            display: block;
        }

        .av-emoji {
            font-size: 3.2rem;
            line-height: 1;
        }

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

        .avatar-save-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Individual avatar SVG animations */
        .av-bounce {
            animation: avBounce 2s ease-in-out infinite;
        }

        .av-wave {
            animation: avWave 1.8s ease-in-out infinite;
            transform-origin: 70% 90%;
        }

        .av-pulse {
            animation: avPulse 2.2s ease-in-out infinite;
        }

        .av-spin {
            animation: avSpin 8s linear infinite;
            transform-origin: center;
        }

        .av-float {
            animation: avFloat 2.5s ease-in-out infinite;
        }

        .av-rock {
            animation: avRock 2s ease-in-out infinite;
            transform-origin: bottom center;
        }

        @keyframes avBounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-6px);
            }
        }

        @keyframes avWave {

            0%,
            100% {
                transform: rotate(0deg);
            }

            25% {
                transform: rotate(20deg);
            }

            75% {
                transform: rotate(-10deg);
            }
        }

        @keyframes avPulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.06);
            }
        }

        @keyframes avSpin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        @keyframes avFloat {

            0%,
            100% {
                transform: translateY(0) rotate(-2deg);
            }

            50% {
                transform: translateY(-8px) rotate(2deg);
            }
        }

        @keyframes avRock {

            0%,
            100% {
                transform: rotate(-5deg);
            }

            50% {
                transform: rotate(5deg);
            }
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
                border-top: 1px solid rgba(255, 255, 255, 0.1);
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

            .bento-card-large>div:last-child {
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

            .nqt-hub-section>div:first-child {
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

        /* Global Loading Overlay */
        .global-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 4, 4, 0.95);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(10px);
            color: #fff;
            text-align: center;
        }

        .loading-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .loading-content h3 {
            font-size: 1.5rem;
            margin-top: 2rem;
            color: var(--accent-gold);
            letter-spacing: 1px;
            font-weight: 800;
        }

        .loading-content p {
            color: rgba(255, 255, 255, 0.6);
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .premium-spinner {
            width: 80px;
            height: 80px;
            border: 3px solid rgba(233, 198, 111, 0.1);
            border-top: 3px solid var(--accent-gold);
            border-radius: 50%;
            animation: spin-premium 1s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            position: relative;
        }

        .premium-spinner::after {
            content: '';
            position: absolute;
            top: 5px;
            left: 5px;
            right: 5px;
            bottom: 5px;
            border: 2px solid rgba(128, 0, 0, 0.2);
            border-top: 2px solid var(--primary-maroon);
            border-radius: 50%;
            animation: spin-premium 2s linear infinite reverse;
        }

        @keyframes spin-premium {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* AI Personalization Zone Styling */
        .ai-personalization-zone {
            margin-bottom: 1.5rem;
        }

        .ai-intel-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        @media (max-width: 1024px) {
            .ai-intel-grid {
                grid-template-columns: 1fr;
            }
        }

        .intel-card {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 1.25rem;
            border: 1px solid rgba(128, 0, 0, 0.08);
            box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 310px;
        }

        .intel-card:hover {
            box-shadow: 0 10px 30px 0 rgba(128, 0, 0, 0.08);
            border-color: rgba(128, 0, 0, 0.2);
            transform: translateY(-2px);
        }

        /* AI Coach Card */
        .ai-coach-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0.75rem;
        }

        .coach-avatar {
            width: 40px;
            height: 40px;
            background: var(--light-gold);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--primary-maroon);
        }

        .ai-coach-header h4 {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .persona-badge {
            font-size: 0.6rem;
            background: var(--light-gold);
            color: var(--primary-maroon);
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .predicted-role-box {
            background: rgba(128, 0, 0, 0.02);
            border: 1px solid rgba(128, 0, 0, 0.08);
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 0.75rem;
        }

        .predicted-role-box label {
            font-size: 0.6rem;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 700;
            display: block;
            margin-bottom: 2px;
        }

        .role-title {
            font-size: 0.95rem;
            font-weight: 750;
            color: var(--primary-maroon);
            margin-bottom: 6px;
        }

        .confidence-bar-wrap {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .conf-lbl {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .conf-bar {
            width: 100%;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }

        .conf-fill {
            height: 100%;
            background: var(--gradient-gold);
            border-radius: 2px;
        }

        .ai-summary {
            font-size: 0.8rem;
            font-style: italic;
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 0.75rem;
        }

        .coach-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            justify-content: space-between;
        }

        .sync-profile-btn {
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.08);
            color: var(--text-main);
            padding: 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            width: 100%;
            text-align: center;
        }

        .sync-profile-btn:hover {
            background: var(--primary-maroon);
            color: white;
            border-color: var(--primary-maroon);
        }

        /* Daily Challenge Card */
        .challenge-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 6px;
        }

        .challenge-header .lbl {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .topic-badge {
            font-size: 0.65rem;
            background: #e0f2fe;
            color: #0369a1;
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 700;
        }

        .question-text {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }

        .options-container {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 0.75rem;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .option-item:hover {
            background: rgba(0, 0, 0, 0.04);
            border-color: rgba(0, 0, 0, 0.12);
        }

        .option-item input[type="radio"] {
            display: none;
        }

        .option-marker {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #fff;
            border: 1.5px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--text-muted);
            transition: all 0.15s;
        }

        .option-item input[type="radio"]:checked+.option-marker {
            background: var(--primary-maroon);
            border-color: var(--primary-maroon);
            color: white;
        }

        .option-item:has(input[type="radio"]:checked) {
            border-color: var(--primary-maroon);
            background: rgba(128, 0, 0, 0.02);
        }

        .option-text {
            font-size: 0.78rem;
            font-weight: 550;
            color: var(--text-main);
        }

        .challenge-submit-btn {
            background: var(--gradient-maroon);
            color: white;
            border: none;
            padding: 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            width: 100%;
        }

        .challenge-submit-btn:hover {
            opacity: 0.9;
        }

        .result-message {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .result-message.correct {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .result-message.incorrect {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .explanation-box {
            background: #f8fafc;
            border-left: 2px solid #cbd5e1;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 8px;
            font-size: 0.72rem;
            line-height: 1.35;
        }

        .completed-note {
            font-size: 0.65rem;
            color: var(--text-muted);
            text-align: center;
            font-style: italic;
        }

        .challenge-results-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 250px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .challenge-results-list::-webkit-scrollbar {
            width: 4px;
        }

        .challenge-results-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .challenge-results-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .challenge-results-list::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .challenge-nav-buttons button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Insights Card */
        .insights-header {
            margin-bottom: 0.75rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 6px;
        }

        .insights-header h4 {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .insights-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 230px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .insight-item {
            display: flex;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            line-height: 1.35;
            border: 1px solid transparent;
        }

        .insight-item.warning {
            background: #fffbeb;
            color: #92400e;
            border-color: #fde68a;
        }

        .insight-item.achievement {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }

        .insight-item.goal_match {
            background: #eff6ff;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .type-icon {
            font-size: 0.95rem;
            margin-top: 1px;
        }

        .insight-content {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .insight-action-link {
            font-size: 0.68rem;
            font-weight: 700;
            color: inherit;
            text-decoration: underline;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }

        .no-insights,
        .no-challenge {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
            padding: 1.5rem 0;
        }

        details[open] summary i {
            transform: rotate(180deg);
        }

        details summary i {
            transition: transform 0.2s ease;
        }
    </style>

    <!-- Welcome Splash Screen Styles (isolated) -->
    <style>
        #welcome-splash {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: radial-gradient(circle at center, #2a0000 0%, #100000 60%, #000000 100%) !important;
            z-index: 999999 !important;
            display: none;
            justify-content: center !important;
            align-items: center !important;
            flex-direction: column !important;
            opacity: 1;
            transition: opacity 0.9s ease;
        }

        #welcome-splash.fade-out {
            opacity: 0 !important;
            pointer-events: none !important;
        }

        .splash-word {
            font-family: 'Caveat', cursive;
            font-size: 8rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 4px;
            line-height: 1;
            display: inline-block;
            white-space: nowrap;
        }

        #splash-cursor {
            display: inline-block;
            width: 4px;
            height: 0.82em;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 2px;
            vertical-align: middle;
            margin-left: 6px;
            animation: penBlink 0.65s ease infinite;
        }

        .splash-sub {
            font-size: 0.78rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.45);
            letter-spacing: 6px;
            text-transform: uppercase;
            margin-top: 14px;
            opacity: 0;
            transition: opacity 0.8s ease;
            text-align: center;
            width: 100%;
        }

        @keyframes penBlink {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Welcome Splash Screen (First Login of Session) -->
    <div id="welcome-splash">
        <div
            style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
            <div id="splash-text-container" class="splash-word"></div>
            <div class="splash-sub" id="splash-sub">Welcome to Lakshya Portal</div>
        </div>
    </div>

    <!-- Instant Skeleton Loader -->
    <div id="skeleton-screen">
        <div class="skeleton-container">
            <div class="skeleton-sidebar"></div>
            <div class="skeleton-main">
                <div class="skeleton-header"></div>
                <div class="skeleton-grid">
                    <div class="skeleton-card"></div>
                    <div class="skeleton-card"></div>
                    <div class="skeleton-card"></div>
                    <div class="skeleton-card"></div>
                </div>
            </div>
        </div>
    </div>
    <style>
        #skeleton-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            z-index: 9999;
            padding: 20px;
        }

        .skeleton-container {
            display: flex;
            gap: 20px;
            height: 100%;
        }

        .skeleton-sidebar {
            width: 280px;
            background: #eee;
            border-radius: 16px;
            animation: shimmer 1.5s infinite;
        }

        .skeleton-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .skeleton-header {
            height: 100px;
            background: #eee;
            border-radius: 16px;
            animation: shimmer 1.5s infinite;
        }

        .skeleton-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .skeleton-card {
            height: 250px;
            background: #eee;
            border-radius: 16px;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% {
                opacity: 0.5;
            }

            50% {
                opacity: 0.8;
            }

            100% {
                opacity: 0.5;
            }
        }
    </style>
    <?php
    ob_flush();
    flush();
    ?>

    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar-profile">
            <?php
            // Use RemoteDataProxy to avoid remote DB lag
            $dataProxy = new \App\Services\RemoteDataProxy();
            $academicHistory = $dataProxy->getAcademicHistory($userId, $institution);
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
                        <?php
                        $displayPhoto = !empty($profile['profile_photo']) ? $profile['profile_photo'] : getPhoto();
                        if (!empty($displayPhoto)): ?>
                            <img src="<?php echo htmlspecialchars((string) $displayPhoto); ?>" alt="Profile"
                                id="profileRealPhoto"
                                style="display:block; width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php endif; ?>
                        <div id="profileAvatarDisplay"
                            style="width:100%;height:100%;display:<?php echo !empty($displayPhoto) ? 'none' : 'flex'; ?>;align-items:center;justify-content:center;">
                            <!-- Avatar rendered by JS -->
                            <span style="font-size:3rem; color:var(--primary-maroon); opacity:0.3;"><i
                                    class="fas fa-user-graduate"></i></span>
                        </div>
                    </div>

                    <h2><?php echo htmlspecialchars((string) ($fullName ?? 'Student')); ?></h2>
                    <p><?php echo htmlspecialchars($profile['enrollment_number'] ?? ''); ?></p>

                    <div class="info-strip">
                        <div class="info-box">
                            <label>Course</label>
                            <span><?php echo htmlspecialchars((string) ($profile['course'] ?? 'N/A')); ?></span>
                        </div>
                        <div class="info-box">
                            <label>Semester</label>
                            <span><?php echo htmlspecialchars((string) ($profile['semester'] ?? '0')); ?></span>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; text-align: left;">
                        <label
                            style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 8px;">Department</label>
                        <span
                            style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars((string) ($profile['department'] ?? 'N/A')); ?></span>
                    </div>

                    <div style="margin-top: 1.5rem; text-align: left;">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label
                                style="font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Profile
                                Strength</label>
                            <span
                                style="font-size: 0.75rem; font-weight: 800; color: var(<?php echo $completeness > 80 ? '--accent-gold' : '--primary-maroon'; ?>);"><?php echo $completeness; ?>%</span>
                        </div>
                        <div style="width: 100%; height: 6px; background: #f0f0f0; border-radius: 10px; overflow: hidden;">
                            <div
                                style="width: <?php echo $completeness; ?>%; height: 100%; background: var(--gradient-maroon); border-radius: 10px; transition: width 1s ease-in-out;">
                            </div>
                        </div>
                    </div>

                    <!-- <a href="showcase.php"
                        style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 1.5rem; padding: 12px; background: var(--gradient-maroon); color: #fff; border-radius: 12px; font-size: 0.85rem; font-weight: 700; text-decoration: none; transition: all 0.3s; box-shadow: 0 4px 10px rgba(128, 0, 0, 0.15);"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 14px rgba(128,0,0,0.25)';"
                        onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 10px rgba(128,0,0,0.15)';">
                        <i class="fas fa-id-card"></i> View AI Talent Showcase
                    </a> -->

                    <?php if ($isGMIT): ?>
                        <a href="sgpa_entry.php"
                            style="display: block; margin-top: 2rem; padding: 12px; background: #fff; border: 1.5px dashed var(--accent-gold); color: var(--primary-maroon); border-radius: 12px; font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: 0.3s;">
                            <i class="fas fa-edit"></i> Update Academic History
                        </a>
                        <?php
                    endif; ?>
                </div>

                <!-- Mini History -->
                <div class="bento-card">
                    <div class="card-header" style="margin-bottom: 1rem;">
                        <h3 style="font-size: 0.95rem;"><i class="fas fa-history" style="color: var(--primary-maroon);"></i>
                            Recent SGPAs</h3>
                        <a href="javascript:void(0)" onclick="refreshAcademicCache()" title="Refresh from University DB"
                            style="color:var(--text-muted); font-size:0.8rem;">
                            <i class="fas fa-sync-alt" id="academic-sync-icon"></i>
                        </a>
                    </div>
                    <?php if (count($academicHistory) > 1): ?>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php for ($i = 1; $i < min(4, count($academicHistory)); $i++):
                                $hist = $academicHistory[$i]; ?>
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f9f9f9;">
                                    <span style="font-size: 0.8rem; font-weight: 600;">Sem
                                        <?php echo htmlspecialchars((string) ($hist['semester'] ?? '')); ?></span>
                                    <span
                                        style="font-size: 0.85rem; font-weight: 800; color: var(--primary-maroon);"><?php echo htmlspecialchars((string) ($hist['sgpa'] ?? '0.00')); ?></span>
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
                <div class="welcome-card"
                    style="text-align: center; padding: 4rem; border: 2px solid var(--primary-maroon);">
                    <div style="font-size: 4rem; margin-bottom: 2rem; color: var(--accent-gold);"><i
                            class="fas fa-exclamation-triangle"></i></div>
                    <h2 style="font-size: 2rem; color: var(--primary-maroon); margin-bottom: 1rem;">Academic Details
                        Required</h2>
                    <p style="margin-bottom: 2rem; font-size: 1.1rem; color: var(--text-muted);">
                        To unlock your dashboard, you must update your <strong>Semester & SGPA</strong> details.
                    </p>
                    <a href="sgpa_entry.php" class="pf-btn pf-btn-primary"
                        style="display: inline-block; text-decoration: none; padding: 15px 30px; font-size: 1.1rem; border-radius: 50px;">
                        <i class="fas fa-edit" style="margin-right: 8px;"></i> Update Now
                    </a>
                </div>
                <?php
            else: ?>
                <!-- COMPULSORY RESUME NOTIFICATION -->
                <?php if (!$hasResume): ?>
                    <div class="resume-alert-banner"
                        style="background: linear-gradient(90deg, #800000, #b22222); color: white; padding: 20px 30px; border-radius: 20px; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 20px rgba(128,0,0,0.2); animation: pulseAlert 2s infinite;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <i class="fas fa-file-invoice" style="font-size: 1.8rem; color: var(--accent-gold);"></i>
                            <div>
                                <h4 style="margin:0; font-weight: 800; font-size: 1.1rem;">Resume Profile Incomplete</h4>
                                <p style="margin: 5px 0 0; opacity: 0.9; font-size: 0.9rem;">Please create or upload your resume
                                    in <strong>AI Tools</strong>. It is compulsory for all internship and job applications.</p>
                            </div>
                        </div>
                        <?php if (isFeatureEnabled('feature_resume_builder')): ?>
                            <a href="resume_builder.php" class="tb-btn"
                                style="background: var(--accent-gold); color: #000; font-weight: 800; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-size: 0.85rem; border: none; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                BUILD / UPLOAD NOW
                            </a>
                        <?php endif; ?>
                    </div>
                    <style>
                        @keyframes pulseAlert {
                            0% {
                                transform: scale(1);
                                box-shadow: 0 10px 20px rgba(128, 0, 0, 0.2);
                            }

                            50% {
                                transform: scale(1.01);
                                box-shadow: 0 15px 30px rgba(128, 0, 0, 0.3);
                            }

                            100% {
                                transform: scale(1);
                                box-shadow: 0 10px 20px rgba(128, 0, 0, 0.2);
                            }
                        }
                    </style>
                    <?php
                endif; ?>

                <!-- TCS NQT Practice Hub (TOP PRIORITY - Only for 8th Sem) -->
                <?php if (isset($profile['semester']) && $profile['semester'] == 8): ?>
                    <section class="nqt-hub-section"
                        style="margin-bottom: 2rem; border: 2px solid var(--accent-gold); background: linear-gradient(135deg, #1a1a1a 0%, #4a0000 100%);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <span
                                        style="background: var(--accent-gold); color: var(--primary-dark); padding: 4px 12px; border-radius: 50px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 15px rgba(233, 198, 111, 0.3);">New
                                        in Lakshya</span>
                                    <span style="color: rgba(255,255,255,0.5); font-size: 11px; font-weight: 600;">| EXCLUSIVE
                                        FOR 8TH SEMESTER</span>
                                </div>
                                <h3
                                    style="font-size: 2rem; color: #fff; display: flex; align-items: center; gap: 15px; font-weight: 800;">
                                    <i class="fas fa-bolt"
                                        style="color: var(--accent-gold); filter: drop-shadow(0 0 10px var(--accent-gold));"></i>
                                    TCS NQT 2026 Elite Hub
                                </h3>
                                <p style="color: rgba(255,255,255,0.7); font-size: 1.1rem; margin-top: 5px;">Practice for TCS
                                    NQT only for 8th sem students</p>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge"
                                    style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(5px);">PREMIUM
                                    ACCESS</span>
                            </div>
                        </div>

                        <div class="nqt-grid">
                            <a href="nqt_test_engine" class="nqt-card" style="border: 1px solid rgba(255, 215, 0, 0.1);">
                                <div class="icon-box" style="background: rgba(77, 173, 247, 0.15); color: #4dadf7;"><i
                                        class="fas fa-brain"></i></div>
                                <div>
                                    <h4 style="color: #fff; font-size: 1.2rem;">Cognitive Assessment</h4>
                                    <p style="color: rgba(255,255,255,0.5);">Targeted NQT aptitude, logic, and verbal rounds
                                        with AI mutation.</p>
                                </div>
                                <div class="btn-nqt" style="background: #4dadf7; color: white;">Launch Test <i
                                        class="fas fa-play" style="font-size: 0.8rem;"></i></div>
                            </a>

                            <a href="nqt_technical_round" class="nqt-card" style="border: 1px solid rgba(255, 215, 0, 0.1);">
                                <div class="icon-box" style="background: rgba(255, 146, 43, 0.15); color: #ff922b;"><i
                                        class="fas fa-code"></i></div>
                                <div>
                                    <h4 style="color: #fff; font-size: 1.2rem;">Advanced Coding</h4>
                                    <p style="color: rgba(255,255,255,0.5);">18+ Master problems. Real-time AI evaluation for
                                        NQT patterns.</p>
                                </div>
                                <div class="btn-nqt" style="background: #ff922b; color: white;">Start Coding <i
                                        class="fas fa-terminal" style="font-size: 0.8rem;"></i></div>
                            </a>

                            <a href="nqt_hr_round" class="nqt-card" style="border: 1px solid rgba(255, 215, 0, 0.1);">
                                <div class="icon-box" style="background: rgba(81, 207, 102, 0.15); color: #51cf66;"><i
                                        class="fas fa-comments"></i></div>
                                <div>
                                    <h4 style="color: #fff; font-size: 1.2rem;">AI Behavioral Round</h4>
                                    <p style="color: rgba(255,255,255,0.5);">Mock HR interview tailored specifically for TCS
                                        selection logic.</p>
                                </div>
                                <div class="btn-nqt" style="background: #51cf66; color: white;">Enter Round <i
                                        class="fas fa-microphone" style="font-size: 0.8rem;"></i></div>
                            </a>
                        </div>
                    </section>
                    <?php
                endif; ?>

                <!-- Hero Banner -->
                <div class="hero-banner">
                    <div class="hero-content">
                        <h2>Hello <?php echo htmlspecialchars(explode(' ', $fullName)[0]); ?>, Welcome to Lakshya Portal
                            <span
                                style="-webkit-text-fill-color: initial; -webkit-background-clip: initial; background: none; font-size: 1.8rem; display: inline-block; animation: wave 2.5s infinite; transform-origin: 70% 70%;">👋</span>
                        </h2>
                        <p>You have <strong><?php echo $activeJobsCount; ?></strong> matching job opportunities today. Your
                            portfolio is currently <strong><?php echo $completeness; ?>%</strong> complete.</p>

                        <!-- Daily Grind Motivation Widget -->
                        <div class="grind-motivation-banner" style="margin-top: 18px; padding: 12px 20px; background: rgba(128, 0, 0, 0.03); border: 1px dashed rgba(128, 0, 0, 0.15); border-radius: 12px; display: flex; align-items: center; gap: 12px; max-width: 100%;">
                            <div style="font-size: 1.2rem; color: var(--accent-gold); display: flex; align-items: center; justify-content: center; background: rgba(212, 175, 55, 0.1); width: 32px; height: 32px; border-radius: 50%;">⚡</div>
                            <div style="flex: 1;">
                                <span style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: var(--primary-maroon); font-weight: 800; display: block; margin-bottom: 2px;">Daily Grind</span>
                                <span style="font-style: italic; font-weight: 500; font-size: 0.85rem; color: #374151; line-height: 1.4;">
                                    "<?php echo htmlspecialchars($dailyQuote); ?>"
                                </span>
                            </div>
                        </div>

                        <?php if (!$hasFullHistory && $isGMIT): ?>
                            <div
                                style="margin-top: 1rem; background: rgba(0,0,0,0.2); padding: 10px 20px; border-radius: 12px; display: inline-flex; align-items: center; gap: 10px; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.2);">
                                <i class="fas fa-exclamation-triangle" style="color: var(--accent-gold);"></i>
                                <span>Features Locked: Academic data required.</span>
                            </div>
                            <?php
                        endif; ?>
                    </div>

                </div>

                <!-- Toolbelt -->
                <div class="quick-actions-bar">
                    <a href="jobs"
                        class="action-tool-btn <?php echo (!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                        <div class="icon"><i class="fas fa-briefcase" style="color: #800000;"></i></div>
                        <h4>Jobs</h4>
                    </a>
                    <a href="student_drives.php"
                        class="action-tool-btn <?php echo (!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                        <div class="icon"><i class="fas fa-robot" style="color: #1e3a8a;"></i></div>
                        <h4>Campus Drives</h4>
                    </a> 
                    <?php if (isFeatureEnabled('feature_mock_ai')): ?>
                        <a href="mock_ai_interview"
                            class="action-tool-btn <?php echo (!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                            <div class="icon"><i class="fas fa-fire" style="color: #ea580c;"></i></div>
                            <h4>Mock AI Prep</h4>
                        </a>
                    <?php endif; ?>
                    <?php if (isFeatureEnabled('feature_leaderboard')): ?>
                        <a href="leaderboard"
                            class="action-tool-btn <?php echo (!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                            <div class="icon"><i class="fas fa-trophy" style="color: #b8860b;"></i></div>
                            <h4>Leaderboard</h4>
                        </a>
                    <?php endif; ?>
                    <?php if (isFeatureEnabled('feature_company_guide')): ?>
                        <a href="javascript:void(0)" onclick="openGuideModal()"
                            class="action-tool-btn <?php echo (!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                            <div class="icon"><i class="fas fa-graduation-cap" style="color: #800000;"></i></div>
                            <h4>Company Guide</h4>
                        </a>
                    <?php endif; ?>
                    <a href="internships"
                        class="action-tool-btn <?php echo (!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                        <div class="icon"><i class="fas fa-search" style="color: #0d9488;"></i></div>
                        <h4>Internships</h4>
                    </a>
                    <a href="applications"
                        class="action-tool-btn <?php echo (!$hasFullHistory && $isGMIT) ? 'locked-card' : ''; ?>">
                        <div class="icon"><i class="fas fa-clipboard-list" style="color: #4f46e5;"></i></div>
                        <h4>Status</h4>
                    </a>
                </div>

                <!-- AI Career Personalization Zone -->
                <section class="ai-personalization-zone">
                    <div class="ai-intel-grid">
                        <!-- 1. AI Coach Profile & Insights -->
                        <div class="intel-card ai-coach-card">
                            <div class="ai-coach-header">
                                <div class="coach-avatar">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <div>
                                    <h4 style="margin:0;">AI Career Coach</h4>
                                    <span
                                        class="persona-badge"><?php echo htmlspecialchars($aiProfile['personality_pref'] ?? 'Professional'); ?>
                                        Mode</span>
                                </div>
                            </div>

                            <div class="coach-body">
                                <div class="predicted-role-box">
                                    <label>Predicted Path</label>
                                    <div class="role-title">
                                        <?php echo htmlspecialchars($aiProfile['predicted_role'] ?? 'Software Engineer'); ?>
                                    </div>
                                    <div class="confidence-bar-wrap">
                                        <span class="conf-lbl">Portfolio Match:
                                            <?php echo min(85, round(($aiProfile['confidence_score'] ?? 0.5) * 100)); ?>%</span>
                                        <div class="conf-bar">
                                            <div class="conf-fill"
                                                style="width: <?php echo min(85, (($aiProfile['confidence_score'] ?? 0.5) * 100)); ?>%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="ai-summary">
                                    "<?php echo htmlspecialchars($aiProfile['ai_summary'] ?? 'Analyze your profile to get predictions.'); ?>"
                                </p>

                                <button onclick="syncAIProfile()" class="sync-profile-btn"><i class="fas fa-sync-alt"></i>
                                    Re-Sync Career Profile</button>
                            </div>
                        </div>

                        <!-- 2. Daily Cognitive Micro-Challenge -->
                        <div class="intel-card daily-challenge-card" id="challenge-card-container">
                            <?php if ($dailyChallenge):
                                $questions = $dailyChallenge['question_json'];
                                if (is_string($questions)) {
                                    $questions = json_decode($questions, true);
                                }
                                
                                // Robust extraction of questions array
                                if (is_array($questions)) {
                                    // Check if it's a wrapper object (e.g. {"questions": [...]}, {"data": [...]})
                                    if (!isset($questions[0])) {
                                        foreach ($questions as $key => $val) {
                                            if (is_array($val) && (isset($val[0]['question']) || isset($val['question']))) {
                                                $questions = $val;
                                                break;
                                            }
                                        }
                                    }
                                }

                                // If it is still a single question object, wrap it
                                $isMultiple = is_array($questions) && isset($questions[0]) && is_array($questions[0]);
                                if (is_array($questions) && !$isMultiple && isset($questions['question'])) {
                                    $questions = [$questions];
                                }
                                
                                // Filter out invalid questions to prevent key errors
                                $validQuestions = [];
                                if (is_array($questions)) {
                                    foreach ($questions as $q) {
                                        if (is_array($q) && isset($q['question']) && isset($q['options']) && is_array($q['options'])) {
                                            $validQuestions[] = $q;
                                        }
                                    }
                                }
                                $questions = $validQuestions;
                                $totalQ = count($questions);
                                
                                if ($totalQ > 0):
                                ?>
                                <div class="challenge-header">
                                    <div class="lbl"><i class="fas fa-brain"></i> Daily Challenge</div>
                                    <span
                                        class="topic-badge"><?php echo htmlspecialchars($dailyChallenge['topic_name']); ?></span>
                                </div>

                                <?php if ($dailyChallenge['status'] === 'pending'): ?>
                                    <form id="daily-challenge-form"
                                        onsubmit="submitChallenge(event, <?php echo $dailyChallenge['id']; ?>, <?php echo $totalQ; ?>)">
                                        <div class="challenge-questions-wrapper">
                                            <?php foreach ($questions as $qIdx => $q): ?>
                                                <div class="challenge-question-slide" id="q-slide-<?php echo $qIdx; ?>"
                                                    style="<?php echo $qIdx === 0 ? '' : 'display: none;'; ?>">
                                                    <div class="question-meta-row"
                                                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                                                        <span class="q-progress-text"
                                                            style="font-size:0.7rem; font-weight:700; color:var(--text-muted);">Question
                                                            <?php echo ($qIdx + 1); ?> of <?php echo $totalQ; ?></span>
                                                    </div>
                                                    <p class="question-text"><?php echo htmlspecialchars($q['question']); ?></p>

                                                    <div class="options-container">
                                                        <?php foreach ($q['options'] as $idx => $option): ?>
                                                            <label class="option-item">
                                                                <input type="radio" name="challenge_option_<?php echo $qIdx; ?>"
                                                                    value="<?php echo $idx; ?>" required>
                                                                <span class="option-marker"><?php echo chr(65 + $idx); ?></span>
                                                                <span class="option-text"><?php echo htmlspecialchars($option); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Navigation Footer -->
                                        <div class="challenge-nav-buttons" style="display: flex; gap: 8px; margin-top: 0.75rem;">
                                            <button type="button" class="challenge-nav-btn prev-btn" onclick="changeQuestion(-1)"
                                                style="display: none; flex: 1; background: #e2e8f0; color: #475569; border: none; padding: 8px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer;">Previous</button>
                                            <button type="button" class="challenge-nav-btn next-btn" onclick="changeQuestion(1)"
                                                style="flex: 1; background: var(--gradient-maroon); color: white; border: none; padding: 8px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer;">Next</button>
                                            <button type="submit" class="challenge-submit-btn"
                                                style="display: none; flex: 1; background: var(--gradient-maroon); color: white; border: none; padding: 8px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer;">Submit
                                                Answers</button>
                                        </div>
                                    </form>
                                <?php else:
                                    $correctCount = (int) ($dailyChallenge['performance_result'] ?? 0);
                                    ?>
                                    <div class="challenge-completed-state">
                                        <div class="challenge-score-summary"
                                            style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; background: rgba(128, 0, 0, 0.03); padding: 10px 14px; border-radius: 10px; border: 1px solid rgba(128, 0, 0, 0.08);">
                                            <span style="font-size: 0.8rem; font-weight: 700; color: var(--primary-maroon);">Your
                                                Daily Score</span>
                                            <span
                                                style="font-size: 0.9rem; font-weight: 800; color: var(--primary-maroon); background: white; padding: 2px 8px; border-radius: 6px; border: 1px solid rgba(128,0,0,0.15);"><?php echo $correctCount; ?>
                                                / <?php echo $totalQ; ?> Correct</span>
                                        </div>

                                        <div class="challenge-results-list">
                                            <?php foreach ($questions as $qIdx => $q):
                                                $selected = isset($q['selected_answer']) ? (int) $q['selected_answer'] : -1;
                                                $correct = (int) ($q['answer'] ?? 0);
                                                $isQCorrect = ($selected === $correct);
                                                ?>
                                                <div class="challenge-result-item"
                                                    style="border: 1px solid <?php echo $isQCorrect ? '#10b981' : '#ef4444'; ?>; background: <?php echo $isQCorrect ? '#ecfdf5' : '#fef2f2'; ?>; border-radius: 10px; padding: 10px; margin-bottom: 10px;">
                                                    <p class="question-text"
                                                        style="margin-bottom:0.4rem; font-size:0.75rem; line-height: 1.35;">
                                                        <?php echo htmlspecialchars($q['question']); ?>
                                                    </p>
                                                    <div
                                                        style="font-size:0.7rem; font-weight:600; color: #4b5563; margin-bottom:0.4rem; display:flex; flex-direction:column; gap:2px;">
                                                        <div><strong>Your Answer:</strong>
                                                            <?php echo htmlspecialchars($q['options'][$selected] ?? 'None'); ?>
                                                            <?php if ($isQCorrect): ?>
                                                                <i class="fas fa-check-circle" style="color:#10b981; margin-left:4px;"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-times-circle" style="color:#ef4444; margin-left:4px;"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!$isQCorrect): ?>
                                                            <div><strong>Correct Answer:</strong>
                                                                <?php echo htmlspecialchars($q['options'][$correct] ?? ''); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <details style="margin-top: 0.25rem;">
                                                        <summary
                                                            style="cursor: pointer; font-size: 0.65rem; font-weight: 700; color: var(--primary-maroon); outline: none; list-style: none; display: flex; align-items: center; gap: 4px;">
                                                            <i class="fas fa-chevron-down" style="font-size: 0.55rem;"></i> View
                                                            Explanation
                                                        </summary>
                                                        <div class="explanation-box"
                                                            style="margin-top: 5px; margin-bottom: 0; background: white; padding: 8px; border-radius: 6px; font-size: 0.7rem; border: 1px solid rgba(0,0,0,0.05);">
                                                            <p><?php echo formatExplanation($q['explanation'] ?? ''); ?></p>
                                                        </div>
                                                    </details>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <p class="completed-note" style="margin-top:0.75rem; text-align:center; font-size:0.65rem;">
                                            Completed. Tomorrow's challenge will unlock in 24 hours.</p>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="challenge-header">
                                    <div class="lbl"><i class="fas fa-brain"></i> Daily Challenge</div>
                                </div>
                                <p class="no-challenge">Daily challenge format error. Please re-sync your AI profile to regenerate.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="challenge-header">
                                <div class="lbl"><i class="fas fa-brain"></i> Daily Challenge</div>
                            </div>
                            <p class="no-challenge">No challenge generated today. Re-sync your AI profile to set up topics.</p>
                        <?php endif; ?>
                        </div>

                        <!-- 3. AI Insights Feed -->
                        <div class="intel-card insights-card">
                            <div class="insights-header">
                                <h4 style="margin:0;"><i class="fas fa-lightbulb"></i> AI Insights & Actions</h4>
                            </div>
                            <div class="insights-list">
                                <?php if (!empty($aiInsights)): ?>
                                    <?php foreach ($aiInsights as $insight):
                                        $typeClass = strtolower($insight['insight_type']);
                                        $icon = 'fa-info-circle';
                                        if ($insight['insight_type'] === 'Warning')
                                            $icon = 'fa-exclamation-triangle';
                                        if ($insight['insight_type'] === 'Achievement')
                                            $icon = 'fa-trophy';
                                        if ($insight['insight_type'] === 'Goal_Match')
                                            $icon = 'fa-crosshairs';
                                        ?>
                                        <div class="insight-item <?php echo $typeClass; ?>">
                                            <i class="fas <?php echo $icon; ?> type-icon"></i>
                                            <div class="insight-content">
                                                <p style="margin:0; font-weight:600;">
                                                    <?php echo htmlspecialchars($insight['message']); ?>
                                                </p>
                                                <?php if ($insight['action_link']): ?>
                                                    <a href="<?php echo htmlspecialchars($insight['action_link']); ?>"
                                                        class="insight-action-link" style="margin-top:4px;">Take Action <i
                                                            class="fas fa-arrow-right"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="no-insights">You are fully up to date! No critical recommendations at this time.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>


                <!-- Bento Content -->
                <div class="bento-grid">
                    <!-- Portfolio Highlights -->
                    <div class="bento-card bento-card-large">
                        <div class="card-header">
                            <h3><i class="fas fa-star" style="color: var(--accent-gold);"></i> Verified Highlights</h3>
                            <div style="display: flex; gap: 10px;">
                                <?php if (isFeatureEnabled('feature_profile_analyzer')): ?>
                                    <a href="profile_analyser" class="btn-small"
                                        style="background: var(--gradient-maroon); color:white;">AI Analyzer</a>
                                <?php endif; ?>
                                <button onclick="openPortfolioModal()" class="btn-small">Manage</button>
                            </div>
                        </div>



                        <div style="display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 2rem;">
                            <div style="border-right: 1px solid #f3f4f6; padding-right: 2rem;">
                                <h4
                                    style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.25rem; letter-spacing: 1.5px; font-weight: 700;">
                                    Key Projects</h4>
                                <?php if (!empty($byCat['Project'])): ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <?php foreach (array_slice($byCat['Project'], 0, 3) as $proj): ?>
                                            <div class="project-item">
                                                <div
                                                    style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
                                                    <h4><?php echo htmlspecialchars((string) ($proj['title'] ?? '')); ?></h4>
                                                    <?php if ($proj['is_verified']): ?>
                                                        <i class="fas fa-check-circle"
                                                            style="color: #10b981; font-size: 1rem; position: absolute; top: 1.25rem; right: 1.25rem;"
                                                            title="Verified Achievement"></i>
                                                        <?php
                                                    else: ?>
                                                        <a href="javascript:void(0)"
                                                            onclick="navigatePost('project_viva', {id: '<?php echo $proj['id']; ?>'})"
                                                            class="verify-badge-link" title="Verify via AI Viva">
                                                            <div class="verify-badge-pill">
                                                                <i class="fas fa-shield-alt"></i>
                                                                <span>VERIFY</span>
                                                            </div>
                                                        </a>
                                                        <?php
                                                    endif; ?>
                                                </div>
                                                <p><?php echo htmlspecialchars((string) substr($proj['description'] ?? '', 0, 100)) . (strlen($proj['description'] ?? '') > 100 ? '...' : ''); ?>
                                                </p>
                                            </div>
                                            <?php
                                        endforeach; ?>

                                        <?php if (count($byCat['Project']) > 3): ?>
                                            <div style="text-align: right; margin-top: -5px;">
                                                <a href="javascript:void(0)" onclick="openPortfolioModal()"
                                                    style="font-size: 0.75rem; color: var(--primary-maroon); font-weight: 700; text-decoration: none;">
                                                    <i class="fas fa-external-link-alt"></i> View all
                                                    <?php echo count($byCat['Project']); ?> projects
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
                                <h4
                                    style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.25rem; letter-spacing: 1.5px; font-weight: 700;">
                                    Top Skills</h4>
                                <div class="skill-pill-container">
                                    <?php if (!empty($byCat['Skill'])): ?>
                                        <?php foreach ($byCat['Skill'] as $skill): ?>
                                            <div class="skill-pill <?php echo $skill['is_verified'] ? 'verified' : ''; ?>">
                                                <?php echo htmlspecialchars((string) ($skill['title'] ?? '')); ?>
                                                <?php if (!$skill['is_verified']): ?>
                                                    <a href="javascript:void(0)"
                                                        onclick="navigatePost('skill_quiz', {id: '<?php echo $skill['id']; ?>'})"
                                                        style="color: #94a3b8; transition: 0.2s;"><i class="fas fa-shield-alt"
                                                            style="font-size: 0.65rem;"></i></a>
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
                                    <a href="javascript:void(0)"
                                        onclick="navigatePost('<?php echo htmlspecialchars($fItem['link']); ?>', {id: '<?php echo $fItem['id'] ?? ''; ?>'})"
                                        style="display: flex; gap: 12px; text-decoration: none; color: inherit; <?php echo $idx > 0 ? 'padding-top: 12px; border-top: 1px solid #f5f5f5;' : ''; ?>">
                                        <div
                                            style="width: 8px; height: 8px; background: <?php echo $fItem['color']; ?>; border-radius: 50%; margin-top: 6px;">
                                        </div>
                                        <div>
                                            <div style="font-size: 0.85rem; font-weight: 700;">
                                                <?php echo $fItem['icon_html'] ?? ''; ?>
                                                <?php echo htmlspecialchars((string) ($fItem['title'] ?? '')); ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                <?php echo htmlspecialchars((string) ($fItem['subtitle'] ?? '')); ?>
                                            </div>
                                        </div>
                                    </a>
                                    <?php
                                endforeach; ?>
                                <?php
                            else: ?>
                                <p style="font-size: 0.8rem; color: var(--text-muted); text-align: center;">No new updates
                                    tonight.</p>
                                <?php
                            endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!$profile): ?>
                    <div
                        style="background: #fff4f4; color: #c53030; padding: 2rem; border-radius: 20px; text-align: center; font-weight: 600; border: 1px solid #fecaca; margin-top: 2rem;">
                        <i class="fas fa-exclamation-triangle" style="margin-right:8px; color:#e11d48;"></i> Profile
                        configuration missing. Please update your details in the office.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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

                    <div id="pfSkillModeHint"
                        style="display: none; background: #fffdf0; padding: 12px; border-radius: 12px; border: 1px solid #fef08a; margin-bottom: 1.5rem; font-size: 0.8rem; color: #854d0e; line-height: 1.4;">
                        <i class="fas fa-magic" style="margin-right: 5px;"></i> <strong>Bulk Add:</strong> Separate
                        skills with commas (e.g. <i>React, Node.js, AWS</i>) to save time!
                    </div>

                    <div id="pfSkillGroupsContainer" style="display: none;">
                        <div id="db-skill-groups-list"></div>
                        <button type="button" onclick="addSkillGroupDashboard()"
                            style="background: #f8fafc; color: #475569; border: 2px dashed #cbd5e1; width: 100%; padding: 12px; border-radius: 12px; cursor: pointer; font-size: 0.85rem; font-weight: 700; margin-bottom: 2rem;">
                            <i class="fas fa-plus-circle" style="color: var(--primary-maroon);"></i> Add Skill Category
                            (e.g. Languages)
                        </button>
                    </div>

                    <div id="pfEntriesContainer">
                        <div class="pf-entry-row">
                            <div class="form-group row-title-group">
                                <label class="form-label">Title / Name</label>
                                <input type="text" class="pf-input pf-input-title" required
                                    placeholder="Project Name or Skill">
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

                            <div class="pf-row-date-group"
                                style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1.25rem;">
                                <div>
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="pf-input pf-input-start">
                                </div>
                                <div class="pf-end-date-wrapper">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="pf-input pf-input-end">
                                    <label
                                        style="display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 0.75rem; color: #4a5568; cursor: pointer; font-weight: 600;">
                                        <input type="checkbox" class="pf-input-ongoing" onchange="toggleEndDate(this)"
                                            style="cursor: pointer; width: 14px; height: 14px;">
                                        <span>Currently Working</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group row-link-group">
                                <label class="form-label">Project / Demo Link</label>
                                <input type="url" class="pf-input pf-input-link"
                                    placeholder="https://github.com/your-repo">
                            </div>

                            <div class="form-group row-file-group-photo" style="display: none;">
                                <label class="form-label">Profile Photo <span style="color:red">*</span></label>
                                <input type="file" class="pf-input pf-input-file-photo" name="file_upload_photo"
                                    accept="image/*">
                                <p style="font-size: 0.75rem; color: #718096; margin-top: 6px;">Recommended: 1:1 Aspect
                                    Ratio (Square). Max 5MB.</p>
                            </div>

                            <div class="form-group row-file-group-video" style="display: none;">
                                <label class="form-label">Self Intro Video <span style="color:red">*</span></label>
                                <input type="file" class="pf-input pf-input-file-video" name="file_upload_video"
                                    accept="video/*">
                                <p style="font-size: 0.75rem; color: #718096; margin-top: 6px;">Format: MP4. Length: 1-2
                                    minutes. Max 10MB.</p>
                            </div>

                            <div class="form-group row-file-group-cert" style="display: none;">
                                <label class="form-label">Upload Certificates</label>
                                <input type="file" class="pf-input pf-input-file-cert" name="certificate_files[]"
                                    accept="image/*,.pdf" multiple>
                                <p style="font-size: 0.75rem; color: #718096; margin-top: 6px;">Select one or more
                                    PDF/Image files.</p>
                            </div>

                            <div class="form-group row-desc-group">
                                <label class="form-label">Short Description</label>
                                <textarea class="pf-input pf-input-desc" rows="2"
                                    placeholder="Briefly explain your contribution or achievement..."></textarea>
                            </div>

                            <button type="button" class="remove-row-btn" onclick="removePfRow(this)"
                                style="display:none; position:absolute; top:-12px; right:-12px; background:#e53e3e; color:white; border:none; border-radius:50%; width:28px; height:28px; cursor:pointer; box-shadow: 0 4px 8px rgba(229, 62, 62, 0.3); align-items: center; justify-content: center; z-index: 10;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div id="pfAddRowContainer" style="margin-bottom: 2rem;">
                        <button type="button" onclick="addPfRow()"
                            style="background: white; color: #4a5568; border: 2px dashed #e2e8f0; width: 100%; padding: 14px; border-radius: 14px; cursor: pointer; font-size: 0.9rem; font-weight: 700; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-plus-circle" style="color: var(--primary-maroon);"></i> Add Another Item
                        </button>
                    </div>

                    <button type="submit" class="submit-btn" id="pfSubmitBtn">Sync with Profile</button>
                </form>
            </div>

            <div class="modal-body" id="modalViewPortfolio" style="display: none;">
                <div id="pfListLoading" style="text-align: center; padding: 2rem;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size: 2rem; color: var(--primary-maroon);"></i>
                    <p style="margin-top: 15px; font-weight: 600; color: #718096;">Fetching your digital footprint...
                    </p>
                </div>
                <div id="pfListContainer">
                    <!-- Dynamic List -->
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
                Verification increases your placement probability by <strong>40%</strong>. Would you like to verify
                these items now?
            </p>
            <div id="verifyItemsList"
                style="text-align: left; margin-bottom: 2rem; max-height: 200px; overflow-y: auto;">
                <!-- List of items to verify will be injected here -->
            </div>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button class="pf-btn pf-btn-secondary" onclick="location.reload()" style="flex: 1;">Maybe
                    Later</button>
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
                <p style="color: var(--text-muted); font-size: 0.9rem;">Select a company to generate your personalized
                    placement roadmap.</p>
            </div>

            <div class="form-group">
                <label class="form-label">Target Company Name</label>
                <input type="text" id="guideCompanyInput" placeholder="e.g. Infosys, Google, TCS..."
                    style="width: 100%; padding: 15px; border-radius: 12px; border: 2px solid #eee; font-family: 'Outfit'; font-size: 1rem;">
            </div>

            <div style="margin-top: 2rem; display: flex; gap: 10px;">
                <button class="pf-btn pf-btn-secondary" onclick="closeGuideModal()" style="flex: 1;">Cancel</button>
                <button class="pf-btn pf-btn-primary" onclick="generateGuide()" style="flex: 2;">Generate Guide</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        function formatExplanation(text) {
            if (!text) return '';
            const escaped = escapeHtml(text);
            const pattern = /(Option\s+[A-D]\s+is|Option\s+[A-D]\s+are|Option\s+[A-D]\s+incorrect|Option\s+[A-D]\s+correct|Option\s+[A-D]:)/ig;
            let formatted = escaped.replace(pattern, '<br><br><strong>$1</strong>');
            formatted = formatted.replace(/^(<br><br>)+/, '');
            return formatted.replace(/\n/g, '<br>');
        }

        let currentQuestionIndex = 0;
        function changeQuestion(dir) {
            const wrapper = document.querySelector('.challenge-questions-wrapper');
            if (!wrapper) return;
            const slides = wrapper.querySelectorAll('.challenge-question-slide');
            const total = slides.length;

            if (dir > 0) {
                const currentSlide = slides[currentQuestionIndex];
                const checked = currentSlide.querySelector('input[type="radio"]:checked');
                if (!checked) {
                    showToast('Selection Required', 'Please select an option to proceed.', 'info');
                    return;
                }
            }

            slides[currentQuestionIndex].style.display = 'none';
            currentQuestionIndex += dir;
            slides[currentQuestionIndex].style.display = '';

            const prevBtn = document.querySelector('.prev-btn');
            const nextBtn = document.querySelector('.next-btn');
            const submitBtn = document.querySelector('.challenge-submit-btn');

            if (prevBtn) prevBtn.style.display = (currentQuestionIndex === 0) ? 'none' : '';
            if (nextBtn) nextBtn.style.display = (currentQuestionIndex === total - 1) ? 'none' : '';
            if (submitBtn) submitBtn.style.display = (currentQuestionIndex === total - 1) ? '' : 'none';
        }

        async function submitChallenge(event, challengeId, totalQ) {
            event.preventDefault();
            const form = event.target;

            const selectedOptions = [];
            for (let i = 0; i < totalQ; i++) {
                const checkedRadio = form.querySelector(`input[name="challenge_option_${i}"]:checked`);
                if (!checkedRadio) {
                    showToast('Incomplete Challenge', 'Please answer all questions before submitting.', 'info');
                    return;
                }
                selectedOptions.push(parseInt(checkedRadio.value));
            }

            const btn = form.querySelector('.challenge-submit-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            try {
                const response = await fetch('intelligence_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
                    },
                    body: new URLSearchParams({
                        action: 'submit_challenge',
                        challenge_id: challengeId,
                        selected_options: JSON.stringify(selectedOptions),
                        csrf_token: window.CSRF_TOKEN || ''
                    })
                });
                const result = await response.json();
                if (result.success) {
                    const correctCount = result.correct_count;
                    const totalQuestions = result.total_questions;
                    const results = result.results;

                    const container = document.getElementById('challenge-card-container');

                    let resultsHtml = '';
                    results.forEach((q) => {
                        const isCorrect = q.is_correct;
                        resultsHtml += `
                            <div class="challenge-result-item" style="border: 1px solid ${isCorrect ? '#10b981' : '#ef4444'}; background: ${isCorrect ? '#ecfdf5' : '#fef2f2'}; border-radius: 10px; padding: 10px; margin-bottom: 10px;">
                                 <p class="question-text" style="margin-bottom:0.4rem; font-size:0.75rem; line-height: 1.35;">${escapeHtml(q.question)}</p>
                                 <div style="font-size:0.7rem; font-weight:600; color: #4b5563; margin-bottom:0.4rem; display:flex; flex-direction:column; gap:2px;">
                                      <div><strong>Your Answer:</strong> ${escapeHtml(q.options[q.selected] ?? 'None')} 
                                           ${isCorrect ? '<i class="fas fa-check-circle" style="color:#10b981; margin-left:4px;"></i>' : '<i class="fas fa-times-circle" style="color:#ef4444; margin-left:4px;"></i>'}
                                      </div>
                                      ${!isCorrect ? `<div><strong>Correct Answer:</strong> ${escapeHtml(q.options[q.correct_answer] ?? '')}</div>` : ''}
                                 </div>
                                 <details style="margin-top: 0.25rem;">
                                      <summary style="cursor: pointer; font-size: 0.65rem; font-weight: 700; color: var(--primary-maroon); outline: none; list-style: none; display: flex; align-items: center; gap: 4px;">
                                           <i class="fas fa-chevron-down" style="font-size: 0.55rem;"></i> View Explanation
                                      </summary>
                                      <div class="explanation-box" style="margin-top: 5px; margin-bottom: 0; background: white; padding: 8px; border-radius: 6px; font-size: 0.7rem; border: 1px solid rgba(0,0,0,0.05);">
                                           <p>${formatExplanation(q.explanation || '')}</p>
                                      </div>
                                 </details>
                            </div>
                        `;
                    });

                    let resultHtml = `
                        <div class="challenge-header">
                            <div class="lbl"><i class="fas fa-brain"></i> Daily Challenge</div>
                            <span class="topic-badge">${escapeHtml(result.topic || '')}</span>
                        </div>
                        <div class="challenge-completed-state">
                            <div class="challenge-score-summary" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; background: rgba(128, 0, 0, 0.03); padding: 10px 14px; border-radius: 10px; border: 1px solid rgba(128, 0, 0, 0.08);">
                                 <span style="font-size: 0.8rem; font-weight: 700; color: var(--primary-maroon);">Your Daily Score</span>
                                 <span style="font-size: 0.9rem; font-weight: 800; color: var(--primary-maroon); background: white; padding: 2px 8px; border-radius: 6px; border: 1px solid rgba(128,0,0,0.15);">${correctCount} / ${totalQuestions} Correct</span>
                            </div>
                            
                            <div class="challenge-results-list">
                                 ${resultsHtml}
                            </div>
                            
                            <p class="completed-note" style="margin-top:0.75rem; text-align:center; font-size:0.65rem;">Completed. Tomorrow's challenge will unlock in 24 hours.</p>
                        </div>
                    `;
                    container.innerHTML = resultHtml;

                    showToast('Challenge Completed', `Mastery updated! You scored ${correctCount}/${totalQuestions}!`, 'success');
                } else {
                    showToast('Submission Failed', result.message || 'Unknown error occurred.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = 'Submit Answers';
                }
            } catch (e) {
                showToast('Connection Error', 'Failed to connect to the intelligence engine.', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Submit Answers';
            }
        }

        async function syncAIProfile() {
            const overlay = document.querySelector('.global-loading-overlay');
            if (overlay) {
                overlay.style.display = 'flex';
                const loadingTitle = overlay.querySelector('.loading-content h3');
                const loadingText = overlay.querySelector('.loading-content p');
                if (loadingTitle) loadingTitle.innerText = 'AI Career Coaching';
                if (loadingText) loadingText.innerText = 'Syncing profile achievements and generating tailored challenges...';
            }

            try {
                const response = await fetch('intelligence_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
                    },
                    body: new URLSearchParams({
                        action: 'sync_profile',
                        csrf_token: window.CSRF_TOKEN || ''
                    })
                });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    if (overlay) overlay.style.display = 'none';
                    showToast('Sync Failed', result.message || 'Unknown error occurred.', 'error');
                }
            } catch (e) {
                if (overlay) overlay.style.display = 'none';
                showToast('Connection Error', 'Failed to reach AI Intelligence Service.', 'error');
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function showToast(title, message, type = 'info') {
            const container = document.getElementById('notification-toast-container') || createToastContainer();
            const toast = document.createElement('div');
            toast.className = 'notification-toast';
            if (type === 'error') {
                toast.style.borderColor = '#ff4d4f';
            } else if (type === 'success') {
                toast.style.borderColor = '#52c41a';
            }

            let icon = '<i class="fas fa-info-circle"></i>';
            if (type === 'error') icon = '<i class="fas fa-exclamation-circle"></i>';
            if (type === 'success') icon = '<i class="fas fa-check-circle"></i>';

            toast.innerHTML = `
                <div class="toast-icon" style="${type === 'error' ? 'color:#ff4d4f; background:#fff2f0;' : (type === 'success' ? 'color:#52c41a; background:#f6ffed;' : '')}">${icon}</div>
                <div class="toast-content">
                    <h5 style="${type === 'error' ? 'color:#ff4d4f;' : (type === 'success' ? 'color:#52c41a;' : '')}">${escapeHtml(title)}</h5>
                    <p style="margin:0;">${escapeHtml(message)}</p>
                </div>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('toast-exit');
                setTimeout(() => toast.remove(), 500);
            }, 4000);
        }

        function createToastContainer() {
            let container = document.getElementById('notification-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'notification-toast-container';
                document.body.appendChild(container);
            }
            return container;
        }

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

            if (tabs) {
                tabs.forEach(t => t.classList.remove('active'));
            }

            if (tab === 'add') {
                if (addView) addView.style.display = 'block';
                if (listView) listView.style.display = 'none';
                if (tabs && tabs[0]) tabs[0].classList.add('active');
            } else {
                if (addView) addView.style.display = 'none';
                if (listView) listView.style.display = 'block';
                if (tabs && tabs[1]) tabs[1].classList.add('active');
                loadPortfolioList();
            }
        }

        async function loadPortfolioList() {
            const container = document.getElementById('pfListContainer');
            const loader = document.getElementById('pfListLoading');

            container.innerHTML = '';
            loader.style.display = 'block';

            try {
                const response = await fetch('portfolio_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: new URLSearchParams({ action: 'list', csrf_token: CSRF_TOKEN })
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
            if (item.category === 'Skill') icon = 'fa-code';
            if (item.category === 'Certification') icon = 'fa-certificate';
            if (item.category === 'Personal Intro') icon = 'fa-user-circle';
            if (item.category === 'Project') icon = 'fa-lightbulb';

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
                for (let i = 1; i < rowsList.length; i++) rowsList[i].remove();

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
            title.innerHTML = isSkill ? 'Skills Added! <i class="fas fa-laptop-code" style="color: #4f46e5;"></i>' : 'Project Added! <i class="fas fa-rocket" style="color: #ea580c;"></i>';
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

            // Show Loading Overlay
            document.getElementById('guideModal').style.display = 'none';
            document.getElementById('globalLoadingOverlay').style.display = 'flex';

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
            // Add CSRF Token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = CSRF_TOKEN;
            form.appendChild(csrfInput);
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

            input.onkeydown = function (e) {
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

        async function refreshAcademicCache() {
            const icon = document.getElementById('academic-sync-icon');
            if (icon) icon.classList.add('fa-spin');

            try {
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'refresh_cache' })
                });

                if (response.ok) {
                    location.reload();
                }
            } catch (e) {
                console.error('Refresh failed', e);
            } finally {
                if (icon) icon.classList.remove('fa-spin');
            }
        }
    </script>
    <style>
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .db-tech-group-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
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

        .tag-x:hover {
            opacity: 1;
        }
    </style>

    <!-- ===== AVATAR PICKER MODAL ===== -->
    <div class="avatar-picker-overlay" id="avatarPickerOverlay">
        <div class="avatar-picker-box">
            <div class="avatar-picker-header">
                <h3>🎨 Choose Your Avatar</h3>
                <button class="avatar-picker-close" id="avatarPickerCloseBtn">✕</button>
            </div>
            <p style="font-size:0.8rem;color:#888;margin-bottom:1.2rem;">Pick an animated character that represents you!
            </p>
            <div class="avatar-grid" id="avatarGrid"></div>
            <button class="avatar-save-btn" id="avatarSaveBtn"><i class="fas fa-check"></i> Apply Avatar</button>
        </div>
    </div>

    <script>
        (function () {
            window.addEventListener('load', function () {
                const splash = document.getElementById('welcome-splash');
                const skeleton = document.getElementById('skeleton-screen');

                if (skeleton) skeleton.style.display = 'none';

                var userId = '<?php echo addslashes($userId); ?>';
                var lsKey = 'lakshya_splash_date_' + userId;
                var today = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD
                var lastSeen = localStorage.getItem(lsKey);
                var showSplash = (lastSeen !== today);

                if (splash && showSplash) {
                    localStorage.setItem(lsKey, today);

                    var text = 'Hi there!';
                    var container = document.getElementById('splash-text-container');
                    var subEl = document.getElementById('splash-sub');

                    // Create blinking pen cursor
                    var cursor = document.createElement('span');
                    cursor.id = 'splash-cursor';
                    container.appendChild(cursor);

                    splash.style.display = 'flex';

                    // Type one character at a time
                    var idx = 0;
                    var typing = setInterval(function () {
                        if (idx < text.length) {
                            var charNode = document.createTextNode(text[idx]);
                            container.insertBefore(charNode, cursor);
                            idx++;
                        } else {
                            clearInterval(typing);
                            // Cursor blinks 3 more times then fades
                            setTimeout(function () {
                                cursor.style.transition = 'opacity 0.4s';
                                cursor.style.opacity = '0';
                                // Show subtitle
                                if (subEl) subEl.style.opacity = '1';
                            }, 500);
                            // Dismiss whole splash
                            setTimeout(function () {
                                splash.classList.add('fade-out');
                                setTimeout(function () { splash.remove(); }, 1000);
                            }, 2200);
                        }
                    }, 130);

                } else if (splash) {
                    splash.remove();
                }
            });

            const AVATAR_KEY = 'lakshya_avatar_id_<?php echo $userId; ?>';

            var AVATARS = [];
            // Generate diverse 3D-like avatars using DiceBear Micah style
            const baseStyles = ['micah', 'bottts', 'adventurer'];
            for (let i = 1; i <= 60; i++) {
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
                AVATARS.forEach(function (av) {
                    var div = document.createElement('div');
                    div.className = 'avatar-option' + (selectedAvatarId === av.id ? ' selected' : '');
                    div.dataset.id = av.id;
                    div.innerHTML = '<img src="' + av.url + '" style="width:64px;height:64px;border-radius:50%;object-fit:cover;" alt="Avatar"/>';
                    div.addEventListener('click', function () {
                        document.querySelectorAll('.avatar-option').forEach(function (o) {
                            if (o) o.classList.remove('selected');
                        });
                        if (div) div.classList.add('selected');
                        selectedAvatarId = av.id;
                    });
                    grid.appendChild(div);
                });
            }

            function applyAvatar(avatarId) {
                var av = AVATARS.find(a => a.id === avatarId);
                if (!av) return;
                var avUrl = av.url;
                var display = document.getElementById('profileAvatarDisplay');
                var realPhoto = document.getElementById('profileRealPhoto');
                if (realPhoto) realPhoto.style.display = 'none';
                if (display) {
                    display.style.display = 'flex';
                    display.innerHTML = '<img src="' + avUrl + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="Avatar"/>';
                }
            }

            window.openAvatarPicker = function () {
                const overlay = document.getElementById('avatarPickerOverlay');
                if (overlay) {
                    overlay.classList.add('open');
                    buildGrid();
                }
            };

            const closeBtn = document.getElementById('avatarPickerCloseBtn');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    const overlay = document.getElementById('avatarPickerOverlay');
                    if (overlay) overlay.classList.remove('open');
                });
            }

            const overlay = document.getElementById('avatarPickerOverlay');
            if (overlay) {
                overlay.addEventListener('click', function (e) {
                    if (e.target === this) this.classList.remove('open');
                });
            }

            const saveBtn = document.getElementById('avatarSaveBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function () {
                    if (!selectedAvatarId) { alert('Please select an avatar first!'); return; }
                    localStorage.setItem(AVATAR_KEY, selectedAvatarId);
                    applyAvatar(selectedAvatarId);
                    const overlay = document.getElementById('avatarPickerOverlay');
                    if (overlay) overlay.classList.remove('open');
                });
            }

            // Restore avatar on page load
            var saved = localStorage.getItem(AVATAR_KEY);
            if (saved) { selectedAvatarId = saved; applyAvatar(saved); }
        })();
    </script>

    <!-- Global Loading Overlay -->
    <div id="globalLoadingOverlay" class="global-loading-overlay">
        <div class="loading-content">
            <div class="premium-spinner"></div>
            <h3>Generating Expert Roadmap</h3>
            <p>Analyzing company data & recruitment trends...</p>
        </div>
    </div>
    <!-- Real-time Notifications UI -->
    <div id="notification-toast-container"></div>

    <script>
        // --- REAL-TIME NOTIFICATION SYSTEM ---
        if (!!window.EventSource) {
            const source = new EventSource('../notifications_stream.php');

            source.addEventListener('notification', function (e) {
                try {
                    const data = JSON.parse(e.data);
                    showNotificationToast(data);
                } catch (err) {
                    console.error("Invalid notification data", err);
                }
            });

            source.addEventListener('error', function (e) {
                if (e.readyState == EventSource.CLOSED) {
                    console.log("Notification stream closed");
                }
            });
        }

        function showNotificationToast(data) {
            const container = document.getElementById('notification-toast-container');
            const toast = document.createElement('div');
            toast.className = 'notification-toast';

            const iconHtml = data.company_logo
                ? `<img src="${data.company_logo.startsWith('http') ? data.company_logo : '../uploads/company_images/' + data.company_logo}" style="width:100%;height:100%;object-fit:cover;">`
                : `<i class="fas fa-briefcase"></i>`;

            toast.innerHTML = `
                <div class="toast-icon">${iconHtml}</div>
                <div class="toast-content">
                    <h5>${data.title}</h5>
                    <p>${data.subtitle}</p>
                    <div style="margin-top:8px; font-size:10px; font-weight:700; color:var(--accent-gold); text-transform:uppercase;">Click to view opportunity</div>
                </div>
            `;

            toast.onclick = () => {
                if (data.link) {
                    navigatePost(data.link, { id: data.id });
                }
            };

            container.appendChild(toast);

            // Play notification sound
            try {
                const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
                audio.volume = 0.3;
                audio.play();
            } catch (e) { }

            // Auto-remove after 10 seconds
            setTimeout(() => {
                if (toast) {
                    toast.classList.add('toast-exit');
                    setTimeout(() => toast.remove(), 500);
                }
            }, 10000);
        }
    </script>
    
    <?php if ($needsSgpaUpdate && $isGMIT): ?>
        <?php
        $funnySentences = [
            "VTU results are like a horror movie: you know something scary is coming, but you still have to look! Let's get your 6th Sem SGPA updated before the placement cell sends a search party.",
            "6th Sem results are out! Whether you are celebrating your success or currently questioning the examiner's sanity, it's time to update your SGPA.",
            "VTU just dropped the 6th sem results like a hot potato. Let's make it official on your profile before the placement department calls your home landline!",
            "VTU results are finally here! Update your SGPA and current semester now, because ignoring it won't make the backlogs disappear."
        ];
        $randomSentence = $funnySentences[array_rand($funnySentences)];
        ?>
        <div class="vtu-results-overlay">
            <div class="vtu-results-modal">
                <div class="vtu-modal-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2>6th Sem Results are Out!</h2>
                <p>VTU has officially released the 6th Semester results. Before we can celebrate (or initiate a recovery plan), you must update your academic profile.</p>
                <div class="funny-sentence">
                    "<?php echo htmlspecialchars($randomSentence); ?>"
                </div>
                <a href="sgpa_entry.php" class="vtu-update-btn">
                    Update Academic Profile <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>
</body>

</html>