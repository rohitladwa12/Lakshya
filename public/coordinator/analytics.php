<?php
/**
 * Department Analytics Page
 * Visual overview of student progress in the department
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_DEPT_COORDINATOR);

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

requireRole(ROLE_DEPT_COORDINATOR);

$pageId = 'coordinator_analytics';

// Handle POST (Filters, Reset, Export Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_filters'])) {
        SessionFilterHelper::clearFilters($pageId);
    } else {
        // MERGE into the session (do not replace): partial posts — the institution
        // select, the export buttons — must not wipe the other stored filters.
        SessionFilterHelper::updateFilters($pageId, $_POST);
    }
    header("Location: analytics.php");
    exit;
}

// Handle GET Reset
if (isset($_GET['reset'])) {
    SessionFilterHelper::clearFilters($pageId);
    header("Location: analytics.php");
    exit;
}

$filters = SessionFilterHelper::getFilters($pageId);

// Direct GET export for the per-task "missed list" links (opened in a new tab).
// These arrive as GET params and are applied to this request only — nothing is
// written to the session, so the page's own filters stay untouched.
if (isset($_GET['missed_pdf'])) {
    $filters['missed_pdf'] = 1;
    if (isset($_GET['type'])) $filters['type'] = $_GET['type'];
    if (isset($_GET['inst'])) $filters['inst'] = $_GET['inst'];
}

$fullName = getFullName();
$coordinatorId = getUserId();
$department = getDepartment();
$allSemesters = getCoordinatorSemesterFilters($department);
$semester_filter = $allSemesters;
$semFilterVal = isset($filters['sem']) ? (int)$filters['sem'] : 0;
if ($semFilterVal > 0 && in_array($semFilterVal, $allSemesters)) {
    $semester_filter = [$semFilterVal];
} else {
    $semFilterVal = 0;
}
$discipline_filters = getCoordinatorDisciplineFilters($department);
$deptLabel = (is_array($discipline_filters) && count($discipline_filters) > 1 && $discipline_filters[0] !== $discipline_filters[1])
    ? $discipline_filters[0] . ' & ' . $discipline_filters[1]
    : $department;

// Institution Filter
$instFilter = $filters['inst'] ?? 'all';
$validInstitutions = ['GMU', 'GMIT'];
if (!in_array($instFilter, $validInstitutions)) {
    $instFilter = 'all';
}

$studentModel = new StudentProfile();
$coordFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semester_filter
];
if ($instFilter !== 'all') {
    $coordFilters['institution'] = $instFilter;
}
$students = $studentModel->getAllWithUsers($coordFilters);

// Resolve each student's CURRENT semester (GMU: latest approved row from the model;
// GMIT: highest recorded semester in the local SGPA table).
$dbLocal = getDB();
$semOf = [];
$gmitLookupIds = [];
foreach ($students as $s) {
    if ($s['institution'] === INSTITUTION_GMIT) {
        $gmitLookupIds[] = $s['usn'];
        if (!empty($s['aadhar'])) $gmitLookupIds[] = $s['aadhar'];
    }
}
$gmitSemMap = [];
if (!empty($gmitLookupIds)) {
    $gmitLookupIds = array_values(array_unique(array_filter($gmitLookupIds)));
    $ph = implode(',', array_fill(0, count($gmitLookupIds), '?'));
    $stmtSem = $dbLocal->prepare("SELECT student_id, MAX(semester) FROM student_sem_sgpa WHERE institution = ? AND student_id IN ($ph) GROUP BY student_id");
    $stmtSem->execute(array_merge([INSTITUTION_GMIT], $gmitLookupIds));
    $gmitSemMap = $stmtSem->fetchAll(PDO::FETCH_KEY_PAIR);
}
foreach ($students as $s) {
    $sem = (int)($s['semester'] ?? 0);
    if ($s['institution'] === INSTITUTION_GMIT) {
        $sem = (int)($gmitSemMap[$s['usn']] ?? (!empty($s['aadhar']) ? ($gmitSemMap[$s['aadhar']] ?? 0) : 0));
    }
    $semOf[$s['usn']] = $sem;
}

// "Semester = N" means the student's CURRENT semester is N — same rule as the
// other coordinator pages (a 7th-sem student has history rows for sems 1..7 and
// must not match a sem-5 filter).
if ($semFilterVal > 0) {
    $students = array_values(array_filter($students, function ($s) use ($semOf, $semFilterVal) {
        return ($semOf[$s['usn']] ?? 0) === $semFilterVal;
    }));
}

$studentCount = count($students);

// Extract USNs/IDs
$studentIds = array_column($students, 'usn');

// Chart data defaults (filled below when there are students)
$chartData = null;
$heatmapRows = [];
$heatmapCols = [];

$metrics = [
    'skills' => 0,
    'certifications' => 0,
    'projects' => 0,
    'resumes' => 0,
    'mock_interviews' => 0,
    'assessments' => 0
];

if (!empty($studentIds)) {
    $db = getDB();
    // PDO doesn't support binding arrays directly in IN clause easily without building it
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

    // --- Pre-calculate Metrics for both UI and Reports ---
    $getPercentage = function ($count, $total) {
        if ($total <= 0) return 0;
        return round(($count / $total) * 100);
    };

    // 1. Portfolio Metrics (Skills, Certifications, Projects)
    $portfolioSql = "SELECT category, COUNT(DISTINCT student_id) as count FROM student_portfolio WHERE student_id IN ($placeholders) GROUP BY category";
    $stmt = $db->prepare($portfolioSql);
    $stmt->execute($studentIds);
    $portData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Resumes Built
    $resumeSql = "SELECT COUNT(DISTINCT student_id) FROM student_resumes WHERE student_id IN ($placeholders)";
    $stmt = $db->prepare($resumeSql);
    $stmt->execute($studentIds);
    $resCount = (int)$stmt->fetchColumn();

    // 3. Mock AI Interview Sessions
    $mockSql = "SELECT COUNT(DISTINCT student_id) FROM mock_ai_interview_sessions WHERE student_id IN ($placeholders)";
    $stmt = $db->prepare($mockSql);
    $stmt->execute($studentIds);
    $mkCount = (int)$stmt->fetchColumn();

    // 4. Unified AI Assessments
    $assessmentSql = "SELECT COUNT(DISTINCT student_id) FROM unified_ai_assessments WHERE student_id IN ($placeholders)";
    $stmt = $db->prepare($assessmentSql);
    $stmt->execute($studentIds);
    $asCount = (int)$stmt->fetchColumn();

    $metrics = [
        'skills' => $portData['Skill'] ?? 0,
        'certifications' => $portData['Certification'] ?? 0,
        'projects' => $portData['Project'] ?? 0,
        'resumes' => $resCount,
        'mock_interviews' => $mkCount,
        'assessments' => $asCount
    ];

    // --- 5. Assigned Task Performance (Grouped by Category) ---
    $tasksDataMap = [];
    $stmtT = $db->prepare("SELECT * FROM coordinator_tasks WHERE coordinator_id = ? ORDER BY created_at DESC");
    $stmtT->execute([$coordinatorId]);
    $repoTasks = $stmtT->fetchAll(PDO::FETCH_ASSOC);

    foreach ($repoTasks as $task) {
        $taskId = $task['id'];
        $type = $task['task_type'];
        if (!isset($tasksDataMap[$type])) {
            $tasksDataMap[$type] = [
                'type' => $type,
                'title' => ucfirst($type) . " Assessment Overview",
                'completed_list' => [],
                'pending_list' => [],
                'missed_list' => [],
                'unique_assigned' => [],
                'unique_completed' => [],
                'unique_missed' => []
            ];
        }

        $targetUsns = json_decode($task['target_students'], true) ?: [];
        
        // Filter target USNs by the current institution filter
        $relevantUsns = [];
        $relevantIdsForQuery = [];
        foreach ($students as $s) {
            if (in_array($s['usn'], $targetUsns)) {
                $relevantUsns[] = $s['usn'];
                $tasksDataMap[$type]['unique_assigned'][$s['usn']] = true;
                
                $relevantIdsForQuery[] = $s['usn'];
                if (!empty($s['aadhar'])) {
                    $relevantIdsForQuery[] = $s['aadhar'];
                }
            }
        }

        if (empty($relevantUsns)) continue;

        // Get completions for these relevant USNs (using both USN and Aadhar)
        $relevantIdsForQuery = array_unique(array_filter($relevantIdsForQuery));
        $phTask = implode(',', array_fill(0, count($relevantIdsForQuery), '?'));
        $stmtC = $db->prepare("SELECT student_id, score FROM task_completions WHERE task_id = ? AND student_id IN ($phTask)");
        $stmtC->execute(array_merge([$taskId], $relevantIdsForQuery));
        $completions = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        $completeMap = [];
        foreach ($completions as $c) $completeMap[$c['student_id']] = $c;

        foreach ($relevantUsns as $ru) {
            // Find student details
            $sDet = null;
            foreach ($students as $s) { if ($s['usn'] == $ru) { $sDet = $s; break; } }
            if (!$sDet) continue;
            
            $sDet['task_title'] = $task['title'];
            $sDet['assigned_at'] = $task['created_at'];

            $isExpired = strtotime($task['deadline']) < time();
            
            $aadhar = $sDet['aadhar'] ?? '';
            $completionRow = $completeMap[$ru] ?? (!empty($aadhar) ? ($completeMap[$aadhar] ?? null) : null);

            if ($completionRow) {
                $sDet['score'] = $completionRow['score'];
                $tasksDataMap[$type]['completed_list'][$ru . '_' . $taskId] = $sDet;
                $tasksDataMap[$type]['unique_completed'][$ru] = true;
            } else if ($isExpired) {
                $tasksDataMap[$type]['missed_list'][$ru . '_' . $taskId] = $sDet;
                $tasksDataMap[$type]['unique_missed'][$ru] = true;
            } else {
                $tasksDataMap[$type]['pending_list'][$ru . '_' . $taskId] = $sDet;
            }
        }
    }

    // Finalize tasksData array from the map
    $tasksData = [];
    foreach ($tasksDataMap as $type => $data) {
        $totalAssigned = count($data['unique_assigned']);
        $totalCompleted = count($data['unique_completed']);
        $totalMissed = count($data['unique_missed']);
        $tasksData[] = [
            'type' => $type,
            'title' => $data['title'],
            'total' => $totalAssigned,
            'completed' => $totalCompleted,
            'missed' => $totalMissed,
            'completed_list' => array_values($data['completed_list']),
            'pending_list' => array_values($data['pending_list']),
            'missed_list' => array_values($data['missed_list']),
            'percentage' => $getPercentage($totalCompleted, $totalAssigned)
        ];
    }

    // --- Export Logic (Excel & PDF) ---
    if (isset($filters['export']) || isset($filters['pdf']) || isset($filters['missed_pdf'])) {
        $isPdf = isset($filters['pdf']) || isset($filters['missed_pdf']);
        $isMissedOnly = isset($filters['missed_pdf']);
        $pendingType = $filters['type'] ?? null;

        // Consume export triggers from the SESSION copy (GET-injected missed_pdf/type
        // were never stored there, so this must not write local overrides back).
        SessionFilterHelper::setFilters($pageId, array_diff_key(SessionFilterHelper::getFilters($pageId), ['export'=>1, 'pdf'=>1, 'missed_pdf'=>1, 'type'=>1]));
        
        if (!$isPdf) {
            ob_clean();
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="department_detailed_analytics_'.date('Y-m-d').'.xls"');
            echo '<html><head><link rel="icon" type="image/png" href="/Lakshya/assets/img/favicon.png"><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
        }
        
        // Fetch detailed data for mapping
        $portfolioDetails = [];
        $stmtP = $db->prepare("SELECT student_id, category, COUNT(*) as count FROM student_portfolio WHERE student_id IN ($placeholders) GROUP BY student_id, category");
        $stmtP->execute($studentIds);
        while ($row = $stmtP->fetch(PDO::FETCH_ASSOC)) {
            $portfolioDetails[$row['student_id']][$row['category']] = $row['count'];
        }

        $resumes = $db->prepare("SELECT DISTINCT student_id FROM student_resumes WHERE student_id IN ($placeholders)");
        $resumes->execute($studentIds);
        $resumes = $resumes->fetchAll(PDO::FETCH_COLUMN);

        $mocks = $db->prepare("SELECT DISTINCT student_id FROM mock_ai_interview_sessions WHERE student_id IN ($placeholders)");
        $mocks->execute($studentIds);
        $mocks = $mocks->fetchAll(PDO::FETCH_COLUMN);

        $assessments = $db->prepare("SELECT DISTINCT student_id FROM unified_ai_assessments WHERE student_id IN ($placeholders)");
        $assessments->execute($studentIds);
        $assessments = $assessments->fetchAll(PDO::FETCH_COLUMN);

        // Fetch Current Semesters for GMIT
        $gmitUsns = [];
        foreach ($students as $s) if ($s['institution'] === INSTITUTION_GMIT) $gmitUsns[] = $s['usn'];
        $gmitSems = [];
        if (!empty($gmitUsns)) {
            $phGmit = implode(',', array_fill(0, count($gmitUsns), '?'));
            $stmtC = $db->prepare("SELECT student_id, MAX(semester) FROM student_sem_sgpa WHERE institution = ? AND student_id IN ($phGmit) GROUP BY student_id");
            $stmtC->execute(array_merge([INSTITUTION_GMIT], $gmitUsns));
            $gmitSems = $stmtC->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        if ($isPdf): ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
                <meta charset="UTF-8">
                <title>Department Analytics Report - <?php echo date('Y-m-d'); ?></title>
                <style>
                    body { font-family: 'Outfit', sans-serif; padding: 20px; color: #333; }
                    .report-header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #800000; padding-bottom: 15px; }
                    .report-header h1 { margin: 0; color: #800000; }
                    .report-header p { margin: 5px 0; color: #666; }
                    
                    /* Summary Cards for PDF */
                    .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 40px; }
                    .card { border: 1px solid #eee; border-radius: 12px; padding: 15px; text-align: center; background: #fafafa; }
                    .card h3 { font-size: 11px; margin: 0; color: #666; text-transform: uppercase; }
                    .card .value { font-size: 24px; font-weight: 700; color: #800000; margin: 5px 0; }
                    .card .percent { font-size: 12px; color: #16a34a; font-weight: 600; }
                    
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
                    th, td { border: 1px solid #ddd; padding: 8px 5px; text-align: left; }
                    th { background: #800000; color: white; text-transform: uppercase; }
                    tr:nth-child(even) { background: #f9f9f9; }
                    .print-btn { display: block; width: fit-content; margin: 0 auto 20px; padding: 10px 20px; background: #800000; color: white; border: none; border-radius: 5px; cursor: pointer; }
                    @media print { .print-btn { display: none; } }
                </style>
            </head>
            <body>
                <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
                <div class="report-header">
                    <h1><?php echo $isMissedOnly ? "Missed Students Report" : "Department Analytics Report"; ?></h1>
                    <p>Generated on <?php echo date('d M Y'); ?> for <?php echo htmlspecialchars($deptLabel); ?></p>
                    <?php if ($isMissedOnly && $pendingType): ?>
                        <p style="font-weight: 700; color: #dc2626;">Filter: Only Missed <?php echo ucfirst($pendingType); ?> Tasks</p>
                    <?php endif; ?>
                    <p>Total Students: <?php echo $studentCount; ?> | Date: <?php echo date('d M Y'); ?></p>
                </div>

                <div class="summary-grid">
                    <div class="card">
                        <h3>Portfolio Skills</h3>
                        <div class="value"><?php echo $metrics['skills']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['skills'], $studentCount); ?>% Students</div>
                    </div>
                    <div class="card">
                        <h3>Certifications</h3>
                        <div class="value"><?php echo $metrics['certifications']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['certifications'], $studentCount); ?>% Students</div>
                    </div>
                    <div class="card">
                        <h3>Projects Added</h3>
                        <div class="value"><?php echo $metrics['projects']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['projects'], $studentCount); ?>% Students</div>
                    </div>
                    <div class="card">
                        <h3>Resumes Built</h3>
                        <div class="value"><?php echo $metrics['resumes']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['resumes'], $studentCount); ?>% Students</div>
                    </div>
                    <div class="card">
                        <h3>Mock Interviews</h3>
                        <div class="value"><?php echo $metrics['mock_interviews']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['mock_interviews'], $studentCount); ?>% Students</div>
                    </div>
                    <div class="card">
                        <h3>AI Assessments</h3>
                        <div class="value"><?php echo $metrics['assessments']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['assessments'], $studentCount); ?>% Students</div>
                    </div>
                </div>

                <?php if (!empty($tasksData)): ?>
                <div style="margin-top: 20px;">
                    <h2 style="color: #800000; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Assigned Task Performance</h2>
                    <table style="margin-top: 10px;">
                        <thead>
                            <tr style="background: #f1f5f9; color: #333;">
                                <th>Task Title</th>
                                <th>Type</th>
                                <th>Assigned</th>
                                <th>Completed</th>
                                <th>Missed</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasksData as $td): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($td['title']); ?></td>
                                <td><?php echo ucfirst($td['type']); ?></td>
                                <td><?php echo $td['total']; ?></td>
                                <td style="color: #16a34a; font-weight: 700;"><?php echo $td['completed']; ?></td>
                                <td style="color: #dc2626; font-weight: 700;"><?php echo count($td['pending_list']) + $td['missed']; ?></td>
                                <td><?php echo $td['percentage']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
        <?php endif; ?>

        <table border="1" cellspacing="0" cellpadding="5">
            <thead>
                <tr style="background:#800000; color:white;">
                    <th>Institution</th>
                    <th>USN</th>
                    <th>Name</th>
                    <th>Branch</th>
                    <th>Sem</th>
                    <th>Skills</th>
                    <th>Certs</th>
                    <th>Projects</th>
                    <th>Resume</th>
                    <th>Mock AI</th>
                    <th>AI Assess</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): 
                    $usn = $s['usn'];
                    
                    // If Missed Only mode, skip students who have completed the task
                    if ($isMissedOnly && $pendingType) {
                        $isMissed = false;
                        // Check both pending and missed lists as both are "not completed"
                        $combinedMissing = array_merge($tasksDataMap[$pendingType]['pending_list'] ?? [], $tasksDataMap[$pendingType]['missed_list'] ?? []);
                        foreach ($combinedMissing as $pl) {
                            if ($pl['usn'] === $usn) {
                                $isMissed = true;
                                break;
                            }
                        }
                        if (!$isMissed) continue;
                    }

                    $displaySem = ($s['semester'] ?? null) ?: ($gmitSems[$usn] ?? '-');
                    $deptName = $s['department'] ?? '-';
                    
                    $sCount = $portfolioDetails[$usn]['Skill'] ?? 0;
                    $cCount = $portfolioDetails[$usn]['Certification'] ?? 0;
                    $pCount = $portfolioDetails[$usn]['Project'] ?? 0;
                    $hasR = in_array($usn, $resumes) ? 'Yes' : 'No';
                    $hasM = in_array($usn, $mocks) ? 'Yes' : 'No';
                    $hasA = in_array($usn, $assessments) ? 'Yes' : 'No';
                ?>
                    <tr>
                        <td><?php echo $s['institution']; ?></td>
                        <td><?php echo $usn; ?></td>
                        <td><?php echo $s['name']; ?></td>
                        <td><?php echo $deptName; ?></td>
                        <td><?php echo $displaySem; ?></td>
                        <td><?php echo $sCount; ?></td>
                        <td><?php echo $cCount; ?></td>
                        <td><?php echo $pCount; ?></td>
                        <td><?php echo $hasR; ?></td>
                        <td><?php echo $hasM; ?></td>
                        <td><?php echo $hasA; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($isPdf): ?>
            </body>
            </html>
        <?php else: ?>
            </body>
            </html>
        <?php endif; ?>
        <?php exit;
    }

    // ================= Chart data (page render only — export paths exit above) =================

    // Engagement membership including Aadhar-keyed rows, so GMIT students whose
    // local records are stored under Aadhar are still counted.
    $engageIds = $studentIds;
    foreach ($students as $s) if (!empty($s['aadhar'])) $engageIds[] = $s['aadhar'];
    $engageIds = array_values(array_unique(array_filter($engageIds)));
    $phE = implode(',', array_fill(0, count($engageIds), '?'));

    $activitySets = ['skills'=>[], 'certifications'=>[], 'projects'=>[], 'resumes'=>[], 'mock_interviews'=>[], 'assessments'=>[]];
    $catKey = ['Skill'=>'skills', 'Certification'=>'certifications', 'Project'=>'projects'];
    try {
        $stmtA = $db->prepare("SELECT DISTINCT student_id, category FROM student_portfolio WHERE student_id IN ($phE)");
        $stmtA->execute($engageIds);
        while ($r = $stmtA->fetch(PDO::FETCH_ASSOC)) {
            if (isset($catKey[$r['category']])) $activitySets[$catKey[$r['category']]][$r['student_id']] = true;
        }
    } catch (Exception $e) {}
    foreach ([['student_resumes','resumes'], ['mock_ai_interview_sessions','mock_interviews'], ['unified_ai_assessments','assessments']] as $pair) {
        try {
            $stmtA = $db->prepare("SELECT DISTINCT student_id FROM {$pair[0]} WHERE student_id IN ($phE)");
            $stmtA->execute($engageIds);
            foreach ($stmtA->fetchAll(PDO::FETCH_COLUMN) as $sid) $activitySets[$pair[1]][$sid] = true;
        } catch (Exception $e) {}
    }
    $engaged = function ($set, $s) {
        return isset($set[$s['usn']]) || (!empty($s['aadhar']) && isset($set[$s['aadhar']]));
    };

    // KPI metrics for the page = distinct engaged students (USN or Aadhar keyed)
    $activityLabels = ['skills'=>'Skills', 'certifications'=>'Certifications', 'projects'=>'Projects', 'resumes'=>'Resume', 'mock_interviews'=>'Mock Interview', 'assessments'=>'AI Assessment'];
    foreach ($activitySets as $key => $set) {
        $n = 0;
        foreach ($students as $s) if ($engaged($set, $s)) $n++;
        $metrics[$key] = $n;
    }

    // Semester + institution distribution
    $semCounts = [];
    $instCounts = [];
    foreach ($students as $s) {
        $sem = $semOf[$s['usn']] ?? 0;
        $label = $sem > 0 ? 'Sem ' . $sem : 'Unknown';
        $semCounts[$label] = ($semCounts[$label] ?? 0) + 1;
        $instCounts[$s['institution']] = ($instCounts[$s['institution']] ?? 0) + 1;
    }
    ksort($semCounts); // 'Sem 5' < 'Sem 8' < 'Unknown'

    // Heatmap: semester rows x activity columns — % of that semester's students engaged
    $heatmapCols = array_values($activityLabels);
    $semGroups = [];
    foreach ($students as $s) {
        $sem = $semOf[$s['usn']] ?? 0;
        $semGroups[$sem > 0 ? 'Sem ' . $sem : 'Unknown'][] = $s;
    }
    ksort($semGroups);
    foreach ($semGroups as $label => $group) {
        $cells = [];
        $total = count($group);
        foreach ($activitySets as $key => $set) {
            $have = 0;
            foreach ($group as $s) if ($engaged($set, $s)) $have++;
            $cells[] = ['pct' => $total ? (int)round($have / $total * 100) : 0, 'have' => $have, 'total' => $total, 'activity' => $activityLabels[$key]];
        }
        $heatmapRows[] = ['sem' => $label, 'cells' => $cells];
    }

    // Task status by type (stacked bar) + score distribution of completions
    $taskChart = ['labels'=>[], 'completed'=>[], 'pending'=>[], 'missed'=>[]];
    $scoreBuckets = ['0-39'=>0, '40-59'=>0, '60-74'=>0, '75-100'=>0];
    foreach ($tasksData as $td) {
        $taskChart['labels'][] = ucfirst($td['type']);
        $taskChart['completed'][] = $td['completed'];
        $taskChart['pending'][] = count($td['pending_list']);
        $taskChart['missed'][] = $td['missed'];
        foreach ($td['completed_list'] as $c) {
            if (!isset($c['score']) || $c['score'] === null || $c['score'] === '') continue;
            $sc = (float)$c['score'];
            if ($sc < 40) $scoreBuckets['0-39']++;
            elseif ($sc < 60) $scoreBuckets['40-59']++;
            elseif ($sc < 75) $scoreBuckets['60-74']++;
            else $scoreBuckets['75-100']++;
        }
    }

    // 12-week activity trend: tasks assigned vs completions by the filtered students
    $weekStart = new DateTime('now');
    $weekStart->setISODate((int)$weekStart->format('o'), (int)$weekStart->format('W')); // this ISO week's Monday
    $trendLabels = [];
    $trendKeys = [];
    for ($i = 11; $i >= 0; $i--) {
        $w = clone $weekStart;
        $w->modify("-{$i} week");
        $trendKeys[$w->format('Y-m-d')] = count($trendLabels);
        $trendLabels[] = $w->format('d M');
    }
    $trendAssigned = array_fill(0, 12, 0);
    $trendCompleted = array_fill(0, 12, 0);
    $rangeStart = array_key_first($trendKeys) . ' 00:00:00';
    $bucketWeek = function ($dateStr) use ($trendKeys) {
        try { $d = new DateTime($dateStr); } catch (Exception $e) { return null; }
        $d->setISODate((int)$d->format('o'), (int)$d->format('W'));
        return $trendKeys[$d->format('Y-m-d')] ?? null;
    };
    try {
        $stmtTr = $db->prepare("SELECT created_at FROM coordinator_tasks WHERE coordinator_id = ? AND created_at >= ?");
        $stmtTr->execute([$coordinatorId, $rangeStart]);
        foreach ($stmtTr->fetchAll(PDO::FETCH_COLUMN) as $d) {
            $ix = $bucketWeek($d);
            if ($ix !== null) $trendAssigned[$ix]++;
        }
        $stmtTr = $db->prepare("SELECT tc.completed_at FROM task_completions tc JOIN coordinator_tasks ct ON ct.id = tc.task_id WHERE ct.coordinator_id = ? AND tc.completed_at >= ? AND tc.student_id IN ($phE)");
        $stmtTr->execute(array_merge([$coordinatorId, $rangeStart], $engageIds));
        foreach ($stmtTr->fetchAll(PDO::FETCH_COLUMN) as $d) {
            $ix = $bucketWeek($d);
            if ($ix !== null) $trendCompleted[$ix]++;
        }
    } catch (Exception $e) {}

    $engKeys = array_keys($activityLabels);
    $chartData = [
        'semDist' => ['labels' => array_keys($semCounts), 'counts' => array_values($semCounts)],
        'engagement' => [
            'labels' => array_values($activityLabels),
            'pct' => array_map(function ($k) use ($metrics, $getPercentage, $studentCount) { return $getPercentage($metrics[$k], $studentCount); }, $engKeys),
            'counts' => array_map(function ($k) use ($metrics) { return (int)$metrics[$k]; }, $engKeys),
        ],
        'tasks' => $taskChart,
        'scores' => ['labels' => array_keys($scoreBuckets), 'counts' => array_values($scoreBuckets)],
        'trend' => ['labels' => $trendLabels, 'assigned' => $trendAssigned, 'completed' => $trendCompleted],
        'totalStudents' => $studentCount,
    ];
    $hasScores = array_sum($scoreBuckets) > 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Analytics - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #D4AF37;
            --primary-gold-dark: #b59228;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --border-color: #e2e8f0;
            
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
            --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.03);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 16px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: var(--text-main);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        .navbar-spacer { height: 70px; }
        
        .main-content { 
            max-width: 1280px;
            margin: 0 auto;
            padding: 40px 24px 80px 24px;
        }
        
        .page-header { 
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 24px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header-info h2 { 
            font-size: 32px; 
            color: var(--primary-maroon); 
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        
        .header-info p { 
            color: var(--text-muted); 
            font-size: 14px; 
            font-weight: 500;
        }

        .back-btn {
            text-decoration: none;
            color: var(--primary-maroon);
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            transition: var(--transition);
        }

        .back-btn:hover {
            color: #600000;
            transform: translateX(-4px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .metric-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 28px 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.03);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }

        .metric-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .metric-tag {
            font-size: 10px;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
            display: block;
        }

        .metric-title {
            font-size: 18px;
            color: var(--text-main);
            font-weight: 700;
            margin-bottom: 16px;
            line-height: 1.3;
        }

        .stats-row {
            display: flex;
            gap: 24px;
            margin-bottom: 20px;
        }
        
        .stats-col {
            flex: 1;
        }
        
        .stats-col:not(:first-child) {
            padding-left: 24px;
            border-left: 1px solid var(--border-color);
        }
        
        .stats-number {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 4px;
        }
        .stats-number.completed { color: #16a34a; }
        .stats-number.missed { color: #ef4444; }
        .stats-number.total { color: var(--text-main); }
        
        .stats-label {
            font-size: 10px;
            color: var(--text-muted);
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .progress-container {
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            margin-bottom: 24px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-view-details {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--white);
            color: var(--text-main);
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-view-details:hover {
            background: #f8fafc;
            border-color: var(--text-muted);
        }

        /* Portfolio Highlights */
        .highlight-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .highlight-card {
            background: var(--white);
            border-radius: 14px;
            padding: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.02);
            transition: var(--transition);
        }
        
        .highlight-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .highlight-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            margin-bottom: 12px;
        }
        
        .highlight-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .highlight-value-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-top: 4px;
        }
        
        .highlight-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
        }
        
        .highlight-percent {
            font-size: 13px;
            font-weight: 700;
            color: #16a34a;
        }

        /* Modals */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(6px);
            z-index: 9999;
            padding: 20px;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .modal-container {
            background: var(--white);
            width: 100%;
            max-width: 900px;
            max-height: 85vh;
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-hover);
            border: 1px solid var(--border-color);
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title h3 {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary-maroon);
            letter-spacing: -0.5px;
        }
        
        .modal-title p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .modal-close-btn {
            background: #f1f5f9;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            transition: var(--transition);
        }
        
        .modal-close-btn:hover {
            background: #e2e8f0;
            color: var(--text-main);
        }
        
        .modal-body {
            overflow-y: auto;
            padding: 24px;
        }
        
        .modal-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .modal-stat-box {
            padding: 16px;
            border-radius: 14px;
            text-align: center;
            border: 1px solid transparent;
        }
        
        .modal-stat-box.completed {
            background: #f0fdf4;
            border-color: #dcfce7;
            color: #166534;
        }
        
        .modal-stat-box.missed {
            background: #fff5f5;
            border-color: #fee2e2;
            color: #991b1b;
        }
        
        .modal-stat-box.rate {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #475569;
        }
        
        .modal-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .modal-table th {
            padding: 12px 16px;
            background: #f8fafc;
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }
        
        .modal-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }
        
        .modal-table tr {
            transition: var(--transition);
        }
        
        .modal-table tr:hover td {
            background: #fdfafa;
        }

        .btn-action-outline {
            padding: 10px 20px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-main);
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn-action-outline:hover {
            background: #f8fafc;
            border-color: var(--text-muted);
        }
        
        .btn-action-primary {
            padding: 10px 20px;
            border-radius: 10px;
            background: var(--primary-maroon);
            color: white;
            cursor: pointer;
            border: none;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
            text-decoration: none;
        }
        
        .btn-action-primary:hover {
            background: #600000;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(128, 0, 0, 0.3);
            color: white;
        }

        .empty-state {
            background: white;
            padding: 60px;
            border-radius: var(--radius);
            text-align: center;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: var(--text-main);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--text-muted);
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .filter-form-wrapper {
            display: flex;
            gap: 10px;
            align-items: center;
            background: white;
            padding: 10px 16px;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        
        .filter-form-wrapper:focus-within {
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }
        
        .filter-select {
            border: none;
            background: transparent;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            color: var(--text-main);
            font-size: 14px;
            cursor: pointer;
            outline: none;
        }

        /* ===== Charts ===== */
        .section-head {
            margin: 8px 0 20px;
        }
        .section-head h3 {
            font-size: 20px;
            color: var(--primary-maroon);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-head p {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 2px;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
            margin-bottom: 48px;
        }
        .chart-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 22px 22px 18px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.03);
            min-width: 0;
        }
        .chart-card h4 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.2px;
        }
        .chart-sub {
            font-size: 12px;
            color: var(--text-muted);
            margin: 2px 0 16px;
        }
        .chart-canvas {
            position: relative;
            height: 280px;
        }
        .chart-card-wide { grid-column: 1 / -1; }
        .chart-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 280px;
            color: var(--text-light);
            gap: 10px;
            font-size: 13px;
            font-weight: 500;
        }
        .chart-empty i { font-size: 28px; opacity: 0.5; }

        /* Heatmap — 2px surface gaps between cells */
        .heatmap {
            display: grid;
            grid-template-columns: 84px repeat(var(--hm-cols, 6), minmax(0, 1fr));
            gap: 2px;
            margin-top: 4px;
        }
        .hm-col {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 6px 4px;
            text-align: center;
            align-self: end;
        }
        .hm-row-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 12px;
        }
        .hm-cell {
            border-radius: 6px;
            min-height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            cursor: default;
            transition: var(--transition);
        }
        .hm-cell:hover { transform: scale(1.05); box-shadow: var(--shadow-md); }
        .hm-legend {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 14px;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .hm-legend-bar {
            flex: 0 0 140px;
            height: 8px;
            border-radius: 4px;
            background: linear-gradient(90deg, #f8eeee, #d59d9d, #ad5252, #800000);
        }

        @media (max-width: 900px) {
            .charts-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
            .header-actions { width: 100%; flex-direction: column; align-items: stretch; }
            .filter-form-wrapper { justify-content: center; }
            .btn-action-primary, .btn-action-outline { justify-content: center; }
            .hm-row-label { font-size: 10.5px; padding-right: 6px; }
            .heatmap { grid-template-columns: 56px repeat(var(--hm-cols, 6), minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="main-content">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="page-header">
            <div class="header-info">
                <h2>Department Analytics</h2>
                <p><?php echo htmlspecialchars($deptLabel); ?> • <?php echo $studentCount; ?> Students Enrolled</p>
            </div>
            <div class="header-actions">
                <form id="filterBarForm" method="POST" class="filter-form-wrapper">
                    <i class="fas fa-filter" style="color: var(--text-muted); font-size: 14px;"></i>
                    <select name="inst" onchange="this.form.submit()" class="filter-select">
                        <option value="all" <?php echo $instFilter === 'all' ? 'selected' : ''; ?>>All Institutions</option>
                        <option value="GMU" <?php echo $instFilter === 'GMU' ? 'selected' : ''; ?>>GMU Only</option>
                        <option value="GMIT" <?php echo $instFilter === 'GMIT' ? 'selected' : ''; ?>>GMIT Only</option>
                    </select>
                    <span style="width: 1px; height: 20px; background: var(--border-color);"></span>
                    <select name="sem" onchange="this.form.submit()" class="filter-select">
                        <option value="">All Semesters</option>
                        <?php foreach ($allSemesters as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $semFilterVal === (int)$s ? 'selected' : ''; ?>>Semester <?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <a href="analytics.php?reset=1" class="btn-action-outline" title="Clear all filters">
                    <i class="fas fa-undo"></i>
                </a>
                <?php if ($studentCount > 0): ?>
                <form method="POST" target="_blank">
                    <input type="hidden" name="pdf" value="1">
                    <button type="submit" class="btn-action-primary" style="background: #2c3e50; box-shadow: 0 4px 12px rgba(44, 62, 80, 0.2);">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($studentCount > 0): ?>

            <!-- 1. KPI row — headline engagement numbers -->
            <div class="highlight-grid">
                <?php
                $highlightMetrics = [
                    ['label' => 'Skills Added', 'key' => 'skills', 'icon' => 'fa-bolt', 'color' => '#b45309'],
                    ['label' => 'Certifications', 'key' => 'certifications', 'icon' => 'fa-certificate', 'color' => '#2a78d6'],
                    ['label' => 'Projects', 'key' => 'projects', 'icon' => 'fa-project-diagram', 'color' => '#7c3aed'],
                    ['label' => 'Resumes', 'key' => 'resumes', 'icon' => 'fa-file-invoice', 'color' => '#be185d'],
                    ['label' => 'Mock Interviews', 'key' => 'mock_interviews', 'icon' => 'fa-headset', 'color' => '#047857'],
                    ['label' => 'AI Assessments', 'key' => 'assessments', 'icon' => 'fa-robot', 'color' => '#4f46e5']
                ];
                foreach ($highlightMetrics as $hm):
                    $p = $getPercentage($metrics[$hm['key']], $studentCount);
                ?>
                <div class="highlight-card">
                    <div class="highlight-icon-box" style="background: <?php echo $hm['color']; ?>15; color: <?php echo $hm['color']; ?>;">
                        <i class="fas <?php echo $hm['icon']; ?>"></i>
                    </div>
                    <div class="highlight-label"><?php echo $hm['label']; ?></div>
                    <div class="highlight-value-row">
                        <div class="highlight-value"><?php echo $metrics[$hm['key']]; ?></div>
                        <div class="highlight-percent"><?php echo $p; ?>%</div>
                    </div>
                    <div style="height: 4px; background: #f1f5f9; border-radius: 2px; margin-top: 8px; overflow: hidden;">
                        <div style="width: <?php echo $p; ?>%; height: 100%; background: <?php echo $hm['color']; ?>; border-radius: 2px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- 2. Visual analytics -->
            <div class="section-head">
                <h3><i class="fas fa-chart-pie"></i> Visual Analytics</h3>
                <p>
                    All charts follow the filters above &mdash;
                    <?php echo $studentCount; ?> students
                    (<?php echo (int)($instCounts['GMU'] ?? 0); ?> GMU &middot; <?php echo (int)($instCounts['GMIT'] ?? 0); ?> GMIT)<?php echo $semFilterVal > 0 ? ', Semester ' . $semFilterVal : ''; ?>.
                </p>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <h4>Students by semester</h4>
                    <p class="chart-sub">Current semester of the <?php echo $studentCount; ?> filtered students</p>
                    <div class="chart-canvas"><canvas id="chartSem"></canvas></div>
                </div>

                <div class="chart-card">
                    <h4>Portfolio engagement</h4>
                    <p class="chart-sub">Share of students with at least one item per activity</p>
                    <div class="chart-canvas"><canvas id="chartEngage"></canvas></div>
                </div>

                <div class="chart-card">
                    <h4>Task status by type</h4>
                    <p class="chart-sub">Completed vs pending vs missed &mdash; exact counts in the task cards below</p>
                    <?php if (!empty($tasksData)): ?>
                        <div class="chart-canvas"><canvas id="chartTasks"></canvas></div>
                    <?php else: ?>
                        <div class="chart-empty">
                            <i class="fas fa-clipboard-list"></i>
                            No tasks assigned yet &mdash; assign one from the Assign Tasks page.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="chart-card">
                    <h4>Activity &mdash; last 12 weeks</h4>
                    <p class="chart-sub">Tasks you assigned vs completions by these students, per week</p>
                    <div class="chart-canvas"><canvas id="chartTrend"></canvas></div>
                </div>

                <div class="chart-card chart-card-wide">
                    <h4>Engagement heatmap</h4>
                    <p class="chart-sub">Percentage of each semester's students active in each area &mdash; darker means higher</p>
                    <?php
                        $heatRamp = ['#f8eeee', '#f0dada', '#e5c0c0', '#d59d9d', '#c47878', '#ad5252', '#932d2d', '#800000'];
                    ?>
                    <div class="heatmap" style="--hm-cols: <?php echo count($heatmapCols); ?>;">
                        <div></div>
                        <?php foreach ($heatmapCols as $c): ?>
                            <div class="hm-col"><?php echo htmlspecialchars($c); ?></div>
                        <?php endforeach; ?>
                        <?php foreach ($heatmapRows as $row): ?>
                            <div class="hm-row-label"><?php echo htmlspecialchars($row['sem']); ?></div>
                            <?php foreach ($row['cells'] as $cell):
                                $ix = min(7, (int)floor($cell['pct'] / 100 * 7.999));
                                $ink = $ix >= 5 ? '#ffffff' : '#4c1d1d';
                                if ($cell['pct'] === 0) $ink = '#b08b8b';
                            ?>
                            <div class="hm-cell" style="background: <?php echo $heatRamp[$ix]; ?>; color: <?php echo $ink; ?>;"
                                 title="<?php echo htmlspecialchars($row['sem'] . ' - ' . $cell['activity'] . ': ' . $cell['pct'] . '% (' . $cell['have'] . ' of ' . $cell['total'] . ' students)'); ?>">
                                <?php echo $cell['pct']; ?>%
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="hm-legend">
                        <span>0%</span>
                        <div class="hm-legend-bar"></div>
                        <span>100%</span>
                        <span style="margin-left: auto; font-weight: 500;">Hover a cell for exact counts</span>
                    </div>
                </div>

                <div class="chart-card">
                    <h4>Score distribution</h4>
                    <p class="chart-sub">Scores across all completed task attempts</p>
                    <?php if (!empty($hasScores)): ?>
                        <div class="chart-canvas"><canvas id="chartScores"></canvas></div>
                    <?php else: ?>
                        <div class="chart-empty">
                            <i class="fas fa-percent"></i>
                            No scored completions yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. Task Completion Overview -->
            <?php if (!empty($tasksData)): ?>
            <div style="margin-top: 10px; margin-bottom: 32px;">
                <h3 style="font-size: 22px; color: var(--primary-maroon); font-weight: 700; display: flex; align-items: center; gap: 12px; margin-bottom: 4px;">
                    <i class="fas fa-chart-line"></i> Task Completion Overview
                </h3>
                <p style="color: var(--text-muted); font-size: 14px;">Real-time progress of assessments assigned by you.</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-bottom: 48px;">
                <?php foreach ($tasksData as $task): ?>
                <div class="metric-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div>
                            <span class="metric-tag">
                                <?php echo strtoupper($task['type']); ?> TRACKING
                            </span>
                            <h3 class="metric-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                        </div>
                        <div style="background: <?php echo $task['percentage'] > 70 ? '#dcfce7' : ($task['percentage'] > 30 ? '#fef3c7' : '#fee2e2'); ?>; color: <?php echo $task['percentage'] > 70 ? '#166534' : ($task['percentage'] > 30 ? '#92400e' : '#991b1b'); ?>; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 800;">
                            <?php echo $task['percentage']; ?>%
                        </div>
                    </div>

                    <div class="stats-row">
                        <div class="stats-col">
                            <div class="stats-number completed"><?php echo $task['completed']; ?></div>
                            <div class="stats-label">DONE</div>
                        </div>
                        <div class="stats-col">
                            <div class="stats-number missed"><?php echo count($task['pending_list']) + $task['missed']; ?></div>
                            <div class="stats-label">MISSED</div>
                        </div>
                        <div class="stats-col">
                            <div class="stats-number total"><?php echo $task['total']; ?></div>
                            <div class="stats-label">TOTAL</div>
                        </div>
                    </div>

                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo $task['percentage']; ?>%; background: linear-gradient(90deg, #16a34a, #22c55e);"></div>
                    </div>
                    
                    <div style="margin-top: auto;">
                        <button onclick="showTaskDetails('<?php echo $task['type']; ?>')" class="btn-view-details">
                            <i class="fas fa-list-check"></i> View Detailed Breakdown
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div style="margin-top: 32px; border-top: 1px solid #f1f5f9; padding-top: 24px; display: flex; justify-content: flex-end; gap: 15px;">
                <form method="POST">
                    <input type="hidden" name="export" value="1">
                    <button type="submit" class="btn-action-outline">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                </form>
                <form method="POST" target="_blank">
                    <input type="hidden" name="pdf" value="1">
                    <button type="submit" class="btn-action-primary">
                        <i class="fas fa-file-pdf"></i> Download Full Report
                    </button>
                </form>
            </div>

            <!-- Detail Modals -->
            <?php foreach ($tasksData as $task): ?>
                <div id="modal_<?php echo $task['type']; ?>" class="modal-overlay">
                    <div class="modal-container">
                        <div class="modal-header">
                            <div class="modal-title">
                                <h3><?php echo htmlspecialchars($task['title']); ?> - Detailed View</h3>
                                <p><?php echo $task['total']; ?> Total Assignments</p>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <a href="?missed_pdf=1&type=<?php echo $task['type']; ?>&inst=<?php echo $instFilter; ?>" target="_blank" style="padding: 8px 16px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: var(--transition);">
                                    <i class="fas fa-file-pdf"></i> Download Missed List
                                </a>
                                <button onclick="hideTaskDetails('<?php echo $task['type']; ?>')" class="modal-close-btn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="modal-body">
                            <!-- Summary Row -->
                            <div class="modal-stats">
                                <div class="modal-stat-box completed">
                                    <h4 style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Completed</h4>
                                    <div style="font-size: 24px; font-weight: 800; margin-top: 4px;"><?php echo $task['completed']; ?></div>
                                </div>
                                <div class="modal-stat-box missed">
                                    <h4 style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Missed</h4>
                                    <div style="font-size: 24px; font-weight: 800; margin-top: 4px;"><?php echo count($task['pending_list']) + $task['missed']; ?></div>
                                </div>
                                <div class="modal-stat-box rate">
                                    <h4 style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Rate</h4>
                                    <div style="font-size: 24px; font-weight: 800; margin-top: 4px;"><?php echo $task['percentage']; ?>%</div>
                                </div>
                            </div>

                            <table class="modal-table">
                                <thead>
                                    <tr>
                                        <th>Student Info</th>
                                        <th>Assigned Task</th>
                                        <th>Status</th>
                                        <th>Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($task['completed_list'] as $s): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; font-size: 14px; color: var(--text-main);"><?php echo htmlspecialchars($s['name']); ?></div>
                                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?php echo $s['usn']; ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($s['task_title']); ?></div>
                                            <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;"><?php echo date('d M Y', strtotime($s['assigned_at'])); ?></div>
                                        </td>
                                        <td>
                                            <span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px;">COMPLETED</span>
                                        </td>
                                        <td style="font-weight: 700; color: #166534; font-size: 13px;">Score: <?php echo $s['score'] ?: 'N/A'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php foreach ($task['missed_list'] as $s): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; font-size: 14px; color: var(--text-main);"><?php echo htmlspecialchars($s['name']); ?></div>
                                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?php echo $s['usn']; ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($s['task_title']); ?></div>
                                            <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;"><?php echo date('d M Y', strtotime($s['assigned_at'])); ?></div>
                                        </td>
                                        <td>
                                            <span style="background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px;">MISSED</span>
                                        </td>
                                        <td>-</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php foreach ($task['pending_list'] as $s): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; font-size: 14px; color: var(--text-main);"><?php echo htmlspecialchars($s['name']); ?></div>
                                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?php echo $s['usn']; ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($s['task_title']); ?></div>
                                            <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;"><?php echo date('d M Y', strtotime($s['assigned_at'])); ?></div>
                                        </td>
                                        <td>
                                            <span style="background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px;">MISSED</span>
                                        </td>
                                        <td>-</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <script>
                function showTaskDetails(type) {
                    document.getElementById('modal_' + type).style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
                function hideTaskDetails(type) {
                    document.getElementById('modal_' + type).style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
                window.onclick = function(event) {
                    if (event.target.id.startsWith('modal_')) {
                        event.target.style.display = "none";
                        document.body.style.overflow = 'auto';
                    }
                }
            </script>

            <script>
            (function () {
                const D = <?php echo json_encode($chartData); ?>;
                if (!D || typeof Chart === 'undefined') return;

                // Palette validated with the dataviz color checks (CVD-safe on white):
                // categorical maroon/blue/gold; status colors carry completed/pending/missed;
                // an ordered maroon ramp carries the (ordered) semester slices.
                const C = {
                    maroon: '#a03434', blue: '#2a78d6', gold: '#B8860B',
                    ordinal: ['#dfa8a8', '#cd8080', '#b45252', '#962b2b'],
                    status: { good: '#0ca30c', warning: '#fab219', serious: '#ec835a', critical: '#d03b3b' },
                    grid: '#efedea', ink: '#0f172a', muted: '#64748b', surface: '#ffffff', other: '#94a3b8'
                };

                Chart.defaults.font.family = "'Outfit', sans-serif";
                Chart.defaults.font.size = 12;
                Chart.defaults.color = C.muted;
                Chart.defaults.plugins.legend.labels.usePointStyle = true;
                Chart.defaults.plugins.legend.labels.boxWidth = 8;
                Chart.defaults.plugins.legend.labels.boxHeight = 8;
                Chart.defaults.plugins.tooltip.backgroundColor = '#1e293b';
                Chart.defaults.plugins.tooltip.padding = 10;
                Chart.defaults.plugins.tooltip.cornerRadius = 8;
                Chart.defaults.plugins.tooltip.titleFont = { weight: 700 };

                // Value labels at the tip of single-series bars (selective direct labels)
                const tipLabels = {
                    id: 'tipLabels',
                    afterDatasetsDraw(chart, args, opts) {
                        if (!opts || !opts.enabled) return;
                        const ctx = chart.ctx;
                        ctx.save();
                        ctx.font = "600 11px 'Outfit', sans-serif";
                        ctx.fillStyle = '#475569';
                        const horizontal = chart.options.indexAxis === 'y';
                        const meta = chart.getDatasetMeta(0);
                        meta.data.forEach((el, i) => {
                            const v = chart.data.datasets[0].data[i];
                            if (v === null || v === undefined) return;
                            const label = opts.format ? opts.format(v, i) : String(v);
                            if (horizontal) {
                                ctx.textAlign = 'left';
                                ctx.textBaseline = 'middle';
                                ctx.fillText(label, el.x + 6, el.y);
                            } else {
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'bottom';
                                ctx.fillText(label, el.x, el.y - 5);
                            }
                        });
                        ctx.restore();
                    }
                };

                const hairline = { color: C.grid, drawTicks: false };
                const noGrid = { display: false };

                // 1. Donut — students by current semester (ordered ramp, counts in legend)
                const semEl = document.getElementById('chartSem');
                if (semEl && D.semDist.labels.length) {
                    const colors = D.semDist.labels.map((l, i) => l === 'Unknown' ? C.other : C.ordinal[Math.min(i, C.ordinal.length - 1)]);
                    new Chart(semEl, {
                        type: 'doughnut',
                        data: {
                            labels: D.semDist.labels,
                            datasets: [{ data: D.semDist.counts, backgroundColor: colors, borderColor: C.surface, borderWidth: 2, hoverOffset: 6 }]
                        },
                        options: {
                            maintainAspectRatio: false,
                            cutout: '62%',
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        generateLabels(chart) {
                                            const base = Chart.overrides.doughnut.plugins.legend.labels.generateLabels(chart);
                                            base.forEach((item, i) => {
                                                item.text = chart.data.labels[i] + '  -  ' + chart.data.datasets[0].data[i];
                                            });
                                            return base;
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (t) => ' ' + t.label + ': ' + t.parsed + ' students (' + Math.round(t.parsed / D.totalStudents * 100) + '%)'
                                    }
                                }
                            }
                        }
                    });
                }

                // 2. Horizontal bar — portfolio engagement (single series, one hue)
                const engEl = document.getElementById('chartEngage');
                if (engEl) {
                    new Chart(engEl, {
                        type: 'bar',
                        plugins: [tipLabels],
                        data: {
                            labels: D.engagement.labels,
                            datasets: [{
                                data: D.engagement.pct,
                                backgroundColor: C.maroon,
                                maxBarThickness: 22,
                                borderRadius: { topRight: 4, bottomRight: 4 },
                                borderSkipped: 'start'
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            maintainAspectRatio: false,
                            layout: { padding: { right: 56 } },
                            scales: {
                                x: { min: 0, max: 100, grid: hairline, ticks: { callback: v => v + '%' } },
                                y: { grid: noGrid, ticks: { color: C.ink, font: { weight: 600 } } }
                            },
                            plugins: {
                                legend: { display: false },
                                tipLabels: { enabled: true, format: (v, i) => v + '% (' + D.engagement.counts[i] + ')' },
                                tooltip: { callbacks: { label: t => ' ' + t.parsed.x + '% - ' + D.engagement.counts[t.dataIndex] + ' of ' + D.totalStudents + ' students' } }
                            }
                        }
                    });
                }

                // 3. Stacked bar — task status by type (status palette; counts in cards below)
                const taskEl = document.getElementById('chartTasks');
                if (taskEl && D.tasks.labels.length) {
                    new Chart(taskEl, {
                        type: 'bar',
                        data: {
                            labels: D.tasks.labels,
                            datasets: [
                                { label: 'Completed', data: D.tasks.completed, backgroundColor: C.status.good },
                                { label: 'Pending', data: D.tasks.pending, backgroundColor: C.status.warning },
                                { label: 'Missed', data: D.tasks.missed, backgroundColor: C.status.critical }
                            ].map(d => Object.assign(d, { maxBarThickness: 24, borderColor: C.surface, borderWidth: 2 }))
                        },
                        options: {
                            maintainAspectRatio: false,
                            scales: {
                                x: { stacked: true, grid: noGrid, ticks: { color: C.ink, font: { weight: 600 } } },
                                y: { stacked: true, beginAtZero: true, grid: hairline, ticks: { precision: 0 } }
                            },
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }

                // 4. Line — 12-week activity (2px lines, ringed markers)
                const trEl = document.getElementById('chartTrend');
                if (trEl) {
                    new Chart(trEl, {
                        type: 'line',
                        data: {
                            labels: D.trend.labels,
                            datasets: [
                                { label: 'Completions', data: D.trend.completed, borderColor: C.maroon, backgroundColor: C.maroon },
                                { label: 'Tasks assigned', data: D.trend.assigned, borderColor: C.blue, backgroundColor: C.blue }
                            ].map(d => Object.assign(d, {
                                borderWidth: 2, tension: 0.35,
                                pointRadius: 4, pointHoverRadius: 6,
                                pointBorderColor: C.surface, pointBorderWidth: 2
                            }))
                        },
                        options: {
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                x: { grid: noGrid },
                                y: { beginAtZero: true, grid: hairline, ticks: { precision: 0 } }
                            },
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }

                // 5. Column — score distribution (ordered bands wear status colors)
                const scEl = document.getElementById('chartScores');
                if (scEl) {
                    new Chart(scEl, {
                        type: 'bar',
                        plugins: [tipLabels],
                        data: {
                            labels: D.scores.labels,
                            datasets: [{
                                data: D.scores.counts,
                                backgroundColor: [C.status.critical, C.status.serious, C.status.warning, C.status.good],
                                maxBarThickness: 36,
                                borderRadius: { topLeft: 4, topRight: 4 },
                                borderSkipped: 'start'
                            }]
                        },
                        options: {
                            maintainAspectRatio: false,
                            scales: {
                                x: { grid: noGrid, ticks: { color: C.ink, font: { weight: 600 } } },
                                y: { beginAtZero: true, grid: hairline, ticks: { precision: 0 } }
                            },
                            plugins: {
                                legend: { display: false },
                                tipLabels: { enabled: true },
                                tooltip: { callbacks: { label: t => ' ' + t.parsed.y + ' completions scored ' + t.label } }
                            }
                        }
                    });
                }
            })();
            </script>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h3>No Students Found</h3>
                <p>There are no students currently enrolled in your department for the selected filters.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

