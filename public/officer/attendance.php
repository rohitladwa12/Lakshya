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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #7C0000;
            --brand-dark: #4A0000;
            --brand-light: #F9F1F1;
            --gold: #C9972C;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --bg-light: #f3f4f6;
            --border-color: #e5e7eb;
            --ease-out: cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            margin: 0;
        }

        .main-content {
            padding: 40px 50px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header-title h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--brand-dark);
            margin: 0 0 6px 0;
            letter-spacing: -0.5px;
        }

        .header-title p {
            font-size: 14px;
            color: var(--text-muted);
            margin: 0;
        }

        .search-container {
            position: relative;
            max-width: 400px;
            width: 100%;
            margin-bottom: 25px;
        }

        .search-container i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 16px;
        }

        .search-input {
            width: 100%;
            padding: 14px 16px 14px 44px;
            border-radius: 14px;
            border: 1px solid var(--border-color);
            background: #fff;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
        }

        .search-input:focus {
            border-color: var(--brand);
            box-shadow: 0 4px 12px rgba(124, 0, 0, 0.08);
        }

        .table-container {
            background: #fff;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .attendance-table th {
            background: #fafafa;
            padding: 18px 24px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
        }

        .attendance-table td {
            padding: 20px 24px;
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .attendance-table tr:last-child td {
            border-bottom: none;
        }

        .job-title {
            font-weight: 700;
            color: var(--text-dark);
            margin: 0 0 4px 0;
            font-size: 15px;
        }

        .company-badge {
            display: inline-block;
            padding: 4px 8px;
            background: var(--brand-light);
            color: var(--brand);
            font-size: 11px;
            font-weight: 700;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .academic-year-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #eff6ff;
            color: #1e40af;
            font-size: 11px;
            font-weight: 700;
            border-radius: 6px;
        }

        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .stats-badge.present {
            background: #e8fbee;
            color: #166534;
        }

        .stats-badge.absent {
            background: #fef2f2;
            color: #991b1b;
        }

        .stats-badge.not-taken {
            background: #f3f4f6;
            color: var(--text-muted);
            font-style: italic;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-take {
            background: var(--brand);
            color: #fff;
            box-shadow: 0 4px 10px rgba(124, 0, 0, 0.15);
        }

        .btn-take:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(124, 0, 0, 0.25);
        }

        .btn-view {
            background: #f3f4f6;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
        }

        .btn-view:hover {
            background: #e5e7eb;
            transform: translateY(-1px);
        }

        .no-data {
            text-align: center;
            padding: 60px;
            color: var(--text-muted);
            font-size: 16px;
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
