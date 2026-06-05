<?php
require_once __DIR__ . '/../config/bootstrap.php';

$username = 'U23E01AI047';
$userId   = $username;
$institution = 'GMU';

function ms($start) { return round((microtime(true) - $start) * 1000); }
$pageStart = microtime(true);

// --- Simulate dashboard.php data loading ---

$t = microtime(true);
$db = getDB();
echo "[1] Local DB: " . ms($t) . "ms\n";

$t = microtime(true);
require_once __DIR__ . '/../src/Models/JobPosting.php';
$jobModel = new JobPosting();
$jobs = $jobModel->getActiveJobs();
echo "[2] getActiveJobs (" . count($jobs) . "): " . ms($t) . "ms\n";

$t = microtime(true);
require_once __DIR__ . '/../src/Models/Portfolio.php';
$pm = new Portfolio();
$portfolio = $pm->getStudentPortfolio($username, $institution);
echo "[3] getStudentPortfolio (" . count($portfolio) . "): " . ms($t) . "ms\n";

$t = microtime(true);
require_once __DIR__ . '/../src/Models/Resume.php';
$rm = new Resume();
$resume = $rm->getByStudentId($userId);
echo "[4] getResume: " . ms($t) . "ms\n";

$t = microtime(true);
require_once __DIR__ . '/../src/Models/Company.php';
$cm = new Company();
$companies = $cm->getActiveCompanies();
echo "[5] getActiveCompanies (" . count($companies) . "): " . ms($t) . "ms\n";

$t = microtime(true);
require_once __DIR__ . '/../src/Models/StudentProfile.php';
$sp = new StudentProfile();
$history = $sp->getAcademicHistory($userId, $institution);
echo "[6] getAcademicHistory: " . ms($t) . "ms\n";

$t = microtime(true);
$remote = getDB('gmu');
$stmt = $db->prepare("SELECT 1 FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND (is_current = 1 OR freezed = 1) LIMIT 1");
$stmt->execute([$username, 'GMIT']);
echo "[7] SGPA check: " . ms($t) . "ms\n";

$t = microtime(true);
require_once __DIR__ . '/../src/Services/StudentIntelligenceService.php';
$svc = new \App\Services\StudentIntelligenceService($db, $userId, $username, $institution);
$intel = $svc->getStudentIntelligence();
echo "[8] StudentIntelligenceService: " . ms($t) . "ms\n";

echo "\nTOTAL: " . ms($pageStart) . "ms\n";
