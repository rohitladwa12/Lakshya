<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_PLACEMENT_OFFICER);

$db = getDB();

// Fetch all job postings with company information and current attendance aggregates
$stmt = $db->query("
    SELECT jp.*, jp.title AS job_title, c.name as company_name, COALESCE(jp.academic_year, cd.academic_year) AS academic_year,
           (SELECT COUNT(DISTINCT student_id) FROM job_applications ja WHERE ja.job_id = jp.id) as total_applicants,
           (SELECT COUNT(*) FROM job_attendance ja WHERE ja.job_id = jp.id AND ja.status = 'Present') as present_count,
           (SELECT COUNT(*) FROM job_attendance ja WHERE ja.job_id = jp.id AND ja.status = 'Absent') as absent_count
    FROM job_postings jp
    LEFT JOIN companies c ON jp.company_id = c.id
    LEFT JOIN campus_drives cd ON cd.job_id = jp.id
    ORDER BY jp.created_at DESC
");
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance Management | Placement Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--page-bg);
            color: var(--text-primary);
            padding-top: 0;
            min-height: 100vh;
        }

        .main-content {
            padding: 32px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            background: var(--card-bg);
            padding: 24px 30px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .header-title h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--maroon);
            margin: 0 0 4px 0;
            letter-spacing: -0.02em;
        }

        .header-title p {
            font-size: 13.5px;
            color: var(--text-secondary);
            margin: 0;
        }

        .search-container {
            position: relative;
            max-width: 400px;
            width: 100%;
            margin-bottom: 20px;
        }

        .search-container i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
        }

        .search-input {
            width: 100%;
            padding: 9px 14px 9px 38px;
            border-radius: var(--radius-sm);
            border: 1px solid #D1D5DB;
            background: #fff;
            font-size: 13.5px;
            box-sizing: border-box;
            outline: none;
            transition: all 0.15s ease;
            box-shadow: var(--shadow-sm);
            color: var(--text-primary);
        }

        .search-input:focus {
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(124, 0, 0, 0.12);
        }

        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .attendance-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
        }

        .attendance-table th {
            background: #F9FAFB;
            padding: 12px 18px;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
            letter-spacing: 0.04em;
        }

        .attendance-table td {
            padding: 14px 18px;
            font-size: 13.5px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .attendance-table tr:hover td {
            background: #FDFEFE;
        }

        .attendance-table tr:last-child td {
            border-bottom: none;
        }

        .job-title {
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 3px 0;
            font-size: 14px;
        }

        .company-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #FDF2F2;
            color: var(--maroon);
            font-size: 11px;
            font-weight: 700;
            border-radius: 4px;
            text-transform: uppercase;
            border: 1px solid #FEE2E2;
        }

        .academic-year-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #EFF6FF;
            color: #1D4ED8;
            font-size: 11px;
            font-weight: 600;
            border-radius: 4px;
            border: 1px solid #DBEAFE;
        }

        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .stats-badge.present {
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #D1FAE5;
        }

        .stats-badge.absent {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FEE2E2;
        }

        .stats-badge.not-taken {
            background: #F3F4F6;
            color: var(--text-muted);
            border: 1px solid #E5E7EB;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-take {
            background: var(--maroon);
            color: #FFFFFF;
        }

        .btn-take:hover {
            background: var(--maroon-dark);
            color: #FFFFFF;
        }

        .btn-view {
            background: #FFFFFF;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-view:hover {
            background: #F3F4F6;
            color: var(--text-primary);
            border-color: #D1D5DB;
        }

        .no-data {
            text-align: center;
            padding: 60px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .no-data i {
            font-size: 40px;
            color: var(--border-color);
            margin-bottom: 12px;
            display: block;
        }
    </style>
</head>
<body>

    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="header-container">
            <div class="header-title">
                <h1>Student Attendance</h1>
                <p>Track present and absent students who have applied for company drives</p>
            </div>
        </div>

        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" id="jobSearch" class="search-input" placeholder="Search by company, title, or batch..." onkeyup="filterJobs()">
        </div>

        <div class="table-container">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Job & Company</th>
                        <th>Academic Year</th>
                        <th>Total Applied</th>
                        <th>Attendance Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="jobsTableBody">
                    <?php foreach ($jobs as $job): ?>
                    <tr class="job-row">
                        <td>
                            <h4 class="job-title"><?php echo htmlspecialchars($job['job_title'] ?? $job['title'] ?? ''); ?></h4>
                            <span class="company-badge"><?php echo htmlspecialchars($job['company_name'] ?? ''); ?></span>
                        </td>
                        <td>
                            <span class="academic-year-badge">
                                <?php 
                                $academicYear = $job['academic_year'] ?? null;
                                if (!$academicYear && !empty($job['eligible_years'])) {
                                    $years = json_decode($job['eligible_years'], true);
                                    if (is_array($years) && !empty($years)) {
                                        $academicYear = implode(', ', $years);
                                    }
                                }
                                echo htmlspecialchars((string)($academicYear ?: 'N/A')); 
                                ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo $job['total_applicants']; ?></strong> students
                        </td>
                        <td>
                            <?php if ($job['present_count'] > 0 || $job['absent_count'] > 0): ?>
                                <span class="stats-badge present"><i class="fas fa-user-check"></i> <?php echo $job['present_count']; ?> Present</span>
                                <span class="stats-badge absent" style="margin-left: 8px;"><i class="fas fa-user-times"></i> <?php echo $job['absent_count']; ?> Absent</span>
                            <?php else: ?>
                                <span class="stats-badge not-taken"><i class="fas fa-info-circle"></i> Attendance Not Taken</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <a href="job_attendance.php?job_id=<?php echo $job['id']; ?>" class="btn-action <?php echo ($job['present_count'] > 0 || $job['absent_count'] > 0) ? 'btn-view' : 'btn-take'; ?>">
                                <i class="fas <?php echo ($job['present_count'] > 0 || $job['absent_count'] > 0) ? 'fa-edit' : 'fa-clipboard-user'; ?>"></i>
                                <?php echo ($job['present_count'] > 0 || $job['absent_count'] > 0) ? 'Edit Attendance' : 'Take Attendance'; ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; if (empty($jobs)): ?>
                    <tr>
                        <td colspan="5" class="no-data">
                            <i class="fas fa-folder-open"></i>
                            No job postings found to take attendance.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function filterJobs() {
            const query = document.getElementById('jobSearch').value.toLowerCase();
            const rows = document.querySelectorAll('.job-row');
            
            rows.forEach(row => {
                const title = row.querySelector('.job-title').textContent.toLowerCase();
                const company = row.querySelector('.company-badge').textContent.toLowerCase();
                const batch = row.querySelector('.academic-year-badge').textContent.toLowerCase();
                
                if (title.includes(query) || company.includes(query) || batch.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
