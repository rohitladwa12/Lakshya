<?php
/**
 * TCS NQT Practice Analytics for Placement Officers
 */
require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$officerModel = new PlacementOfficer();
$allReports = $officerModel->getUnifiedAIReports();

// Filter for TCS NQT Practice and Completed status only
$nqtReports = array_filter($allReports, function($report) {
    $isNqt = isset($report['company_name']) && $report['company_name'] === 'TCS NQT Practice';
    $isCompleted = isset($report['status']) && strtolower($report['status']) === 'completed';
    return $isNqt && $isCompleted;
});

// Sort by date descending
usort($nqtReports, function($a, $b) {
    return strtotime($b['started_at'] ?? '0') - strtotime($a['started_at'] ?? '0');
});

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NQT Analytics – <?php echo APP_NAME; ?></title>
</head>
<body>
<?php include_once 'includes/navbar.php'; ?>
<div class="o-page">

    <!-- Header banner -->
    <div style="background:linear-gradient(135deg,var(--brand) 0%,var(--brand-dark) 100%);border-radius:var(--radius-lg);padding:24px 28px;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <div style="font-size:18px;font-weight:700;color:var(--gold);">🚀 TCS NQT Practice Hub Analytics</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.8);margin-top:4px;">Monitor student performance across Foundation, Advanced, and Technical NQT modules.</div>
        </div>
        <div style="display:flex;gap:14px;">
            <?php
            $scores = array_column($nqtReports, 'score');
            $avgScore = !empty($scores) ? round(array_sum($scores)/count($scores)) : 0;
            ?>
            <div style="background:rgba(255,255,255,0.1);padding:10px 18px;border-radius:10px;text-align:center;border:1px solid rgba(255,255,255,0.15);">
                <div style="font-size:20px;font-weight:700;color:var(--gold);"><?php echo count($nqtReports); ?></div>
                <div style="font-size:11px;color:rgba(255,255,255,0.7);text-transform:uppercase;margin-top:2px;">Total Attempts</div>
            </div>
            <div style="background:rgba(255,255,255,0.1);padding:10px 18px;border-radius:10px;text-align:center;border:1px solid rgba(255,255,255,0.15);">
                <div style="font-size:20px;font-weight:700;color:var(--gold);"><?php echo $avgScore; ?>%</div>
                <div style="font-size:11px;color:rgba(255,255,255,0.7);text-transform:uppercase;margin-top:2px;">Avg Score</div>
            </div>
        </div>
    </div>

    <div class="o-table-wrap">
        <table class="o-table">
            <thead><tr>
                <th>Student</th><th>Sem</th><th>Branch</th><th>Module</th><th>Status</th><th>Score</th><th>Date</th>
            </tr></thead>
            <tbody>
                <?php foreach ($nqtReports as $report):
                    $sc = (int)($report['score'] ?? 0);
                    $scCls = $sc >= 80 ? 'score-high' : ($sc >= 60 ? 'score-mid' : 'score-low');
                    $typeClass = '';
                    if (strpos($report['assessment_type'], 'Aptitude') !== false) $typeClass = 'o-badge--blue';
                    elseif (strpos($report['assessment_type'], 'Technical') !== false) $typeClass = 'o-badge--gold';
                    elseif (strpos($report['assessment_type'], 'HR') !== false) $typeClass = 'o-badge--green';
                    else $typeClass = 'o-badge--gray';
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;"><?php echo htmlspecialchars($report['full_name'] ?? 'Unknown'); ?></div>
                        <div style="font-size:11px;color:var(--text-muted);font-family:monospace;"><?php echo htmlspecialchars($report['usn'] ?? $report['student_id'] ?? ''); ?></div>
                    </td>
                    <td style="font-weight:600;color:var(--brand);"><?php echo htmlspecialchars($report['current_sem'] ?? '-'); ?></td>
                    <td style="font-size:12px;"><?php echo htmlspecialchars($report['branch'] ?? 'N/A'); ?></td>
                    <td><span class="o-badge <?php echo $typeClass; ?>"><?php echo htmlspecialchars($report['assessment_type'] ?? 'NQT'); ?></span></td>
                    <td><span class="o-badge o-badge--green"><i class="fas fa-check"></i> <?php echo ucfirst($report['status']); ?></span></td>
                    <td><span class="<?php echo $scCls; ?>"><?php echo $sc; ?>%</span></td>
                    <td style="font-size:12px;color:var(--text-muted);">
                        <?php echo date('d M Y', strtotime($report['started_at'])); ?><br>
                        <span style="font-size:10px;"><?php echo date('g:i A', strtotime($report['started_at'])); ?></span>
                    </td>
                </tr>
                <?php endforeach; if (empty($nqtReports)): ?>
                <tr class="o-table__empty"><td colspan="7"><i class="fas fa-search" style="font-size:28px;opacity:.3;display:block;margin-bottom:10px;"></i>No TCS NQT practice attempts found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>

