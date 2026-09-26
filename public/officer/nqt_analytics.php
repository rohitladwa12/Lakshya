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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --maroon: #7C0000;
            --maroon-dark: #5A0000;
            --maroon-light: #9B1B1B;
            --gold: #B08D2C;
            --page-bg: #F8F9FA;
            --card-bg: #FFFFFF;
            --border-color: #E5E7EB;
            --text-primary: #111827;
            --text-secondary: #4B5563;
            --text-muted: #9CA3AF;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { 
            background: var(--page-bg); 
            color: var(--text-primary); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .o-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 40px;
        }

        .o-head-banner {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px 30px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: var(--shadow-sm);
        }

        .o-table-wrap {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .o-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .o-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: #F9FAFB;
            border-bottom: 1px solid var(--border-color);
        }

        .o-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13.5px;
            vertical-align: middle;
        }

        .o-table tr:hover td { background: #FDFEFE; }
        .o-table tr:last-child td { border-bottom: none; }

        .o-badge {
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .o-badge--blue { background: #EFF6FF; color: #1D4ED8; border: 1px solid #DBEAFE; }
        .o-badge--gold { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
        .o-badge--green { background: #ECFDF5; color: #047857; border: 1px solid #D1FAE5; }
        .o-badge--gray { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }

        .score-high { font-weight: 700; color: #047857; }
        .score-mid { font-weight: 700; color: #B45309; }
        .score-low { font-weight: 700; color: #DC2626; }

        .o-table__empty td {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            font-size: 14px;
        }
    </style>
</head>
<body>
<?php include_once 'includes/navbar.php'; ?>
<div class="o-page">

    <!-- Header banner -->
    <div class="o-head-banner">
        <div>
            <div style="font-size:20px;font-weight:700;color:var(--maroon);">🚀 TCS NQT Practice Hub Analytics</div>
            <div style="font-size:13.5px;color:var(--text-secondary);margin-top:4px;">Monitor student performance across Foundation, Advanced, and Technical NQT modules.</div>
        </div>
        <div style="display:flex;gap:12px;">
            <?php
            $scores = array_column($nqtReports, 'score');
            $avgScore = !empty($scores) ? round(array_sum($scores)/count($scores)) : 0;
            ?>
            <div style="background:#F9FAFB;padding:10px 18px;border-radius:var(--radius-sm);text-align:center;border:1px solid var(--border-color);">
                <div style="font-size:20px;font-weight:800;color:var(--maroon);"><?php echo count($nqtReports); ?></div>
                <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin-top:2px;">Total Attempts</div>
            </div>
            <div style="background:#F9FAFB;padding:10px 18px;border-radius:var(--radius-sm);text-align:center;border:1px solid var(--border-color);">
                <div style="font-size:20px;font-weight:800;color:#059669;"><?php echo $avgScore; ?>%</div>
                <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin-top:2px;">Avg Score</div>
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

