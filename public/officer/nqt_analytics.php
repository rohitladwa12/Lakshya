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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NQT Practice Analytics - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --nqt-gradient: linear-gradient(135deg, #800000 0%, #2b0000 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; min-height: 100vh; }
        
        .main-content { padding: 40px; }
        .header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }

        .banner {
            background: var(--nqt-gradient);
            padding: 30px;
            border-radius: 16px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
        }

        .banner-info h2 { font-size: 24px; margin-bottom: 5px; color: var(--primary-gold); }
        .banner-info p { opacity: 0.9; font-size: 14px; }

        .stats-summary { display: flex; gap: 20px; }
        .stat-pill { background: rgba(255,255,255,0.1); padding: 10px 20px; border-radius: 12px; border: 1px solid rgba(253, 253, 253, 0.2); text-align: center; }
        .stat-pill .val { display: block; font-size: 20px; font-weight: 700; color: var(--primary-gold); }
        .stat-pill .lbl { font-size: 11px; text-transform: uppercase; opacity: 0.8; }
        
        .table-container { background: var(--white); border-radius: 12px; box-shadow: var(--shadow); overflow: hidden; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 15px 20px; background: #f8f9fa; border-bottom: 2px solid #eee; color: #666; font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .data-table td { padding: 15px 20px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .data-table tr:hover { background: #fcfcfc; }

        .student-info { display: flex; align-items: center; gap: 12px; }
        .avatar { width: 35px; height: 35px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--primary-maroon); font-size: 14px; }
        .name { display: block; font-weight: 600; color: #333; font-size: 14px; }
        .usn { display: block; font-size: 12px; color: #999; }

        .score-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .score-high { background: #e3fcef; color: #00875a; }
        .score-mid { background: #fff4e5; color: #b76e00; }
        .score-low { background: #ffe9e9; color: #bf2600; }

        .module-tag { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: #f1f3f5; color: #495057; border: 1px solid #dee2e6; }
        .tag-aptitude { border-left: 3px solid #4dadf7; }
        .tag-technical { border-left: 3px solid #ff922b; }
        .tag-hr { border-left: 3px solid #51cf66; }

        .btn-details {
            padding: 6px 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-details:hover { background: #e9ecef; border-color: #ced4da; }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="banner">
            <div class="banner-info">
                <h2><span>🚀</span> TCS NQT Practice Hub Analytics</h2>
                <p>Monitor student performance across Foundation, Advanced, and Technical NQT modules.</p>
            </div>
            <div class="stats-summary">
                <div class="stat-pill">
                    <span class="val"><?php echo count($nqtReports); ?></span>
                    <span class="lbl">Total Attempts</span>
                </div>
                <div class="stat-pill">
                    <span class="val">
                        <?php 
                        $scores = array_column($nqtReports, 'score');
                        echo !empty($scores) ? round(array_sum($scores) / count($scores)) . '%' : '0%';
                        ?>
                    </span>
                    <span class="lbl">Avg. Score</span>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student Details</th>
                        <th>Sem</th>
                        <th>Branch</th>
                        <th>Assessment Module</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Attempted Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nqtReports as $report): 
                        $scoreClass = 'score-low';
                        if ($report['score'] >= 80) $scoreClass = 'score-high';
                        elseif ($report['score'] >= 60) $scoreClass = 'score-mid';

                        $typeClass = '';
                        if (strpos($report['assessment_type'], 'Aptitude') !== false) $typeClass = 'tag-aptitude';
                        elseif (strpos($report['assessment_type'], 'Technical') !== false) $typeClass = 'tag-technical';
                        elseif (strpos($report['assessment_type'], 'HR') !== false) $typeClass = 'tag-hr';
                    ?>
                    <tr>
                        <td>
                            <div class="student-info">
                                <div class="avatar"><?php echo strtoupper(substr($report['full_name'] ?? 'U', 0, 1)); ?></div>
                                <div>
                                    <span class="name"><?php echo htmlspecialchars($report['full_name'] ?? 'Unknown'); ?></span>
                                    <span class="usn"><?php echo htmlspecialchars($report['usn'] ?? $report['student_id'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 13px; color: #444; font-weight: 500;"><?php echo htmlspecialchars($report['current_sem'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <div style="font-size: 13px; color: #444; font-weight: 500; font-family: 'Inter', sans-serif;"><?php echo htmlspecialchars($report['branch'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <span class="module-tag <?php echo $typeClass; ?>">
                                <?php echo htmlspecialchars(!empty($report['assessment_type']) ? $report['assessment_type'] : 'NQT Assessment'); ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-size: 12px; color: #00875a; font-weight: 600;">
                                <i class="fas fa-check-circle"></i> <?php echo ucfirst($report['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="score-badge <?php echo $scoreClass; ?>">
                                <?php echo $report['score']; ?>%
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 12px; color: #666;">
                                <?php echo date('d M Y', strtotime($report['started_at'])); ?><br>
                                <span style="font-size: 10px; opacity: 0.7;"><?php echo date('h:i A', strtotime($report['started_at'])); ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; if (empty($nqtReports)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 60px; color: #999;">
                            <div style="font-size: 40px; margin-bottom: 15px; opacity: 0.3;"><i class="fas fa-search"></i></div>
                            <p>No TCS NQT practice attempts found.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
