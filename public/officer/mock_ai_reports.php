<?php
/**
 * Mock AI Technical Interview Reports for Officers
 */
require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$officerModel = new PlacementOfficer();
$mockReports = $officerModel->getMockAIInterviewReports();

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mock AI Reports - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; min-height: 100vh; }
        
        .main-content { padding: 40px; }
        .header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }

        .report-header { margin-top: 30px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: var(--primary-maroon); font-size: 20px; font-weight: bold; }
        
        .table-container { background: var(--white); border-radius: 12px; box-shadow: var(--shadow); overflow: hidden; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 15px 25px; background: #f8f9fa; border-bottom: 2px solid #eee; color: #666; font-size: 12px; text-transform: uppercase; font-weight: 700; }
        .data-table td { padding: 15px 25px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .data-table tr:hover { background: #fcfcfc; }

        .btn-view {
            padding: 8px 15px;
            background: var(--primary-maroon);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-view:hover { opacity: 0.9; transform: translateY(-1px); }

        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-completed { background: #e3fcef; color: #00875a; }
        
        .role-tag { padding: 3px 8px; background: #eee; border-radius: 4px; font-size: 12px; color: #555; font-weight: 600; }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="header">
            <div>
                <h2>Mock AI Interview Analytics</h2>
                <p style="color: #666;">Track student technical preparation and AI evaluations.</p>
            </div>
            <a href="reports.php" class="btn-view" style="background: #666;">
                <i class="fas fa-chart-bar"></i> Recruitment Reports
            </a>
        </div>

        <h3 class="report-header"><span>🎯</span> Technical Mock Interviews</h3>
        <div class="table-container">
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Academic Details</th>
                            <th>Target Role</th>
                            <th>Score</th>
                            <th>Completed At</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mockReports as $report): 
                            $usn = $report['usn'];
                            $sem = $report['current_sem'] ?? '0';
                            $pdfName = "{$usn}_{$sem}.pdf";
                            // Check standardized locations
                            $possiblePaths = [
                                '/mock_ai/',
                                '/technical/',
                                '/hr/'
                            ];
                            $absolutePdfPath = '';
                            $viewUrl = '';
                            
                            foreach ($possiblePaths as $path) {
                                if (file_exists(REPORTS_UPLOAD_PATH . $path . $pdfName)) {
                                    $absolutePdfPath = REPORTS_UPLOAD_PATH . $path . $pdfName;
                                    $viewUrl = '../uploads/reports' . $path . $pdfName;
                                    break;
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($report['full_name']); ?></div>
                                <div style="font-size: 12px; color: #999;"><?php echo $usn; ?></div>
                            </td>
                            <td>
                                <div style="font-size: 13px; font-weight: 600; color: #444;"><?php echo htmlspecialchars($report['branch'] ?? 'N/A'); ?></div>
                                <div style="font-size: 11px; color: #666;">AY: <?php echo htmlspecialchars($report['academic_year'] ?? 'N/A'); ?> | Sem: <?php echo $sem; ?></div>
                            </td>
                            <td>
                                <span class="role-tag"><?php echo htmlspecialchars($report['role_name']); ?></span>
                            </td>
                            <td>
                                <span class="status-badge" style="background: <?php echo $report['overall_score'] >= 80 ? '#e3fcef' : ($report['overall_score'] >= 60 ? '#fff4e5' : '#ffe9e9'); ?>; color: <?php echo $report['overall_score'] >= 80 ? '#00875a' : ($report['overall_score'] >= 60 ? '#b76e00' : '#bf2600'); ?>;">
                                    <?php echo $report['overall_score']; ?>%
                                </span>
                            </td>
                            <td style="font-size: 13px; color: #666;">
                                <?php echo date('d M Y, h:i A', strtotime($report['completed_at'])); ?>
                            </td>
                            <td>
                                <span class="status-badge status-completed">Completed</span>
                            </td>
                            <td>
                                <?php if (file_exists($absolutePdfPath)): ?>
                                    <a href="<?php echo $viewUrl; ?>" target="_blank" class="btn-view">
                                        <i class="fas fa-file-pdf"></i> View PDF Report
                                    </a>
                                <?php else: ?>
                                    <div style="font-size: 11px; color: #999;">
                                        <i class="fas fa-exclamation-triangle"></i> PDF Not Found<br>
                                        <span style="font-size: 9px;">(Expected: <?php echo $pdfName; ?>)</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; if (empty($mockReports)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #999;">
                                No mock interview sessions completed yet.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
