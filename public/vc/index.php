<?php
/**
 * VC Dashboard - Overview (Home)
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

requireRole(ROLE_VC);

$fullName = getFullName();
$GLOBALS['fullName'] = $fullName;
$db = getDB();

$safe = function ($v, $default = 0) {
    return ($v === null || $v === false) ? $default : $v;
};

$stats = [
    'active_jobs' => 0,
    'total_jobs' => 0,
    'total_job_applications' => 0,
    'placed_students' => 0,
    'total_students' => 0,
    'assessments_30d' => 0,
    'avg_assessment_score_30d' => 0,
];

try {
    $stats['placed_students'] = (int) $safe($db->query("SELECT COUNT(DISTINCT student_id) FROM job_applications WHERE status = 'Selected'")->fetchColumn(), 0);

    // AI Assessments (last 30 days)
    $assRows = $db->query("
        SELECT
            COUNT(*) as total,
            AVG(score) as avg_score
        FROM unified_ai_assessments
        WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetch(PDO::FETCH_ASSOC);
    if ($assRows) {
        $stats['assessments_30d'] = (int) ($assRows['total'] ?? 0);
        $stats['avg_assessment_score_30d'] = (float) ($assRows['avg_score'] ?? 0);
    }
    
    // Remote student counts (GMU + GMIT)
    $gmuDB = getDB('gmu');
    $gmuCount = (int) $safe($gmuDB->query("SELECT COUNT(*) FROM users WHERE USER_GROUP = 'STUDENT' AND STATUS = 'ACTIVE'")->fetchColumn(), 0);
    $gmitDB = getDB('gmit');
    $gmitCount = (int) $safe($gmitDB->query("SELECT COUNT(*) FROM users WHERE USER_GROUP = 'STUDENT' AND STATUS = 'ACTIVE'")->fetchColumn(), 0);
    $stats['total_students'] = $gmuCount + $gmitCount;

    // Top Placement Officers
    $placementOfficers = $db->query("SELECT full_name, institution, (SELECT COUNT(*) FROM job_postings WHERE posted_by = app_officers.id) as post_count FROM app_officers WHERE role IN ('placement_officer', 'admin') AND is_active = 1 LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    // Active Coordinators
    $coordinators = $db->query("SELECT full_name, department, institution FROM dept_coordinators WHERE is_active = 1 LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {}

renderVCHeader("Organization Overview");
?>

<div class="header">
    <div class="view-title">
        <h2>Organization Overview</h2>
        <p>Aggregated performance metrics across GMU and GMIT.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Total Students</div>
        <div class="value"><?php echo number_format($stats['total_students']); ?></div>
        <div class="subtext">Active in legacy schemas</div>
    </div>
    <div class="stat-card">
        <div class="label">Placements</div>
        <div class="value"><?php echo number_format($stats['placed_students']); ?></div>
        <div class="subtext">Successful selections (Jobs)</div>
    </div>
    <div class="stat-card">
        <div class="label">AI Assessments</div>
        <div class="value"><?php echo number_format($stats['assessments_30d']); ?></div>
        <div class="subtext">Last 30 days throughput</div>
    </div>
    <div class="stat-card">
        <div class="label">Avg Assessment Score</div>
        <div class="value"><?php echo number_format($stats['avg_assessment_score_30d'], 1); ?>%</div>
        <div class="subtext">Overall organizational average</div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px;">
    <div class="table-container">
        <div style="padding: 20px; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 18px; font-weight: 700; color: var(--primary-maroon);">Top Placement Officers</h3>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Officer</th>
                        <th>Institution</th>
                        <th>Postings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($placementOfficers as $po): ?>
                    <tr>
                        <td><span class="student-name"><?php echo htmlspecialchars($po['full_name']); ?></span></td>
                        <td><span class="badge badge-<?php echo strtolower($po['institution']); ?>"><?php echo $po['institution']; ?></span></td>
                        <td style="font-weight: 700; color: var(--primary-maroon);"><?php echo $po['post_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-container">
        <div style="padding: 20px; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 18px; font-weight: 700; color: var(--primary-maroon);">Active Coordinators</h3>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Coordinator</th>
                        <th>Dept</th>
                        <th>Institution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coordinators as $dc): ?>
                    <tr>
                        <td><span class="student-name"><?php echo htmlspecialchars($dc['full_name']); ?></span></td>
                        <td style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($dc['department']); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($dc['institution']); ?>"><?php echo $dc['institution']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php renderVCFooter(); ?>
