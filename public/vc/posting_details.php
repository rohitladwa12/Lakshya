<?php
/**
 * VC Dashboard - Posting Details (Applicants)
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

requireRole(ROLE_VC);

$id = (int)($_REQUEST['id'] ?? 0);
$type = $_REQUEST['type'] ?? 'job'; // 'job' or 'internship'

if (!$id) {
    header('Location: index.php');
    exit;
}

$fullName = getFullName();
$GLOBALS['fullName'] = $fullName;
$db = getDB();

// Get Posting Title
if ($type === 'job') {
    $stmt = $db->prepare("
        SELECT jp.title, c.name as company_name 
        FROM job_postings jp 
        JOIN companies c ON jp.company_id = c.id 
        WHERE jp.id = ?
    ");
    $stmt->execute([$id]);
    $posting = $stmt->fetch(PDO::FETCH_ASSOC);
    $jobAppModel = new JobApplication();
    $applicants = $jobAppModel->getByJob($id);
} else {
    $stmt = $db->prepare("SELECT internship_title as title, company_name FROM internships WHERE id = ?");
    $stmt->execute([$id]);
    $posting = $stmt->fetch(PDO::FETCH_ASSOC);
    $internshipModel = new InternshipApplication();
    $applicants = $internshipModel->getByInternship($id);
}

// Enhance applicants with AI Assessment scores
foreach ($applicants as &$a) {
    try {
        $stmtAss = $db->prepare("
            SELECT assessment_type, score 
            FROM unified_ai_assessments 
            WHERE student_id = ?
            ORDER BY started_at DESC
        ");
        $stmtAss->execute([$a['usn']]);
        $a['assessments'] = $stmtAss->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $a['assessments'] = [];
    }
}

function getScoreClass($score) {
    if ($score >= 80) return 'score-high';
    if ($score >= 60) return 'score-mid';
    return 'score-low';
}

renderVCHeader($posting['title'] . " - Applicants");
?>

<style>
    .score-high { color: #15803d; font-weight: 800; }
    .score-mid { color: #854d0e; font-weight: 800; }
    .score-low { color: #b91c1c; font-weight: 800; }
    .assessment-badge { background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
</style>

<div class="header">
    <div class="view-title">
        <h2><?php echo htmlspecialchars($posting['title']); ?> <small style="color: var(--text-muted); font-size: 18px;">@ <?php echo htmlspecialchars($posting['company_name']); ?></small></h2>
        <p>Applicant pool and AI assessment readiness.</p>
    </div>
    <a href="<?php echo $type === 'job' ? 'placements.php' : 'internships.php'; ?>" class="btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Applicant Name</th>
                    <th>USN</th>
                    <th>Application Status</th>
                    <th>AI Assessments</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applicants as $a): ?>
                <tr>
                    <td><span class="student-name"><?php echo htmlspecialchars($a['student_name']); ?></span></td>
                    <td class="usn-font"><?php echo htmlspecialchars($a['usn']); ?></td>
                    <td><span class="badge" style="background:#f1f5f9; color:#475569;"><?php echo htmlspecialchars($a['status']); ?></span></td>
                    <td>
                        <div style="display:flex; gap:8px; flex-wrap:wrap">
                            <?php foreach ($a['assessments'] as $ass): ?>
                                <span class="assessment-badge" title="<?php echo htmlspecialchars($ass['assessment_type']); ?>">
                                    <?php echo htmlspecialchars($ass['assessment_type']); ?>: 
                                    <span class="<?php echo getScoreClass($ass['score']); ?>"><?php echo round($ass['score']); ?>%</span>
                                </span>
                            <?php endforeach; ?>
                            <?php if (empty($a['assessments'])): ?>
                                <span style="font-size:12px; color:#cbd5e1">No assessments yet</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($applicants)): ?>
                <tr><td colspan="4" style="padding: 40px; text-align: center; color: var(--text-muted);">No applicants found for this posting.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderVCFooter(); ?>
