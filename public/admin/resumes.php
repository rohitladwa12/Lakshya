<?php
/**
 * Detailed Resume List - Admin View
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/Admin.php';

// Require admin role
requireRole(ROLE_ADMIN);

$fullName = getFullName();
$adminModel = new Admin();
$resumes = $adminModel->getDetailedResumeList();

// Extract unique departments for filter
$departments = array_unique(array_map(function($r) { 
    return $r['department']; 
}, $resumes));
sort($departments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central Resumes - Detailed List - <?php echo APP_NAME; ?></title>
    <!-- Use Outfit font from Google Fonts like student views -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --bg-color: #f4f7fe;
            --white: #ffffff;
            --text-dark: #2b3674;
            --text-muted: #a3aed1;
            --shadow: 0 10px 20px rgba(0,0,0,0.02);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-color);
            display: flex;
            min-height: 100vh;
        }

        /* Main Content Container */
        .main-content {
            flex: 1;
            padding: 40px;
            width: 100%;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            background: var(--white);
            padding: 20px 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .header-title h1 {
            font-size: 24px;
            color: var(--text-dark);
            font-weight: 700;
        }

        .header-title p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 5px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-profile .avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.2);
        }

        /* Table Area */
        .panel {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .panel-title {
            color: var(--text-dark);
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 15px 10px;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            border-bottom: 1px solid #eef2f8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 15px 10px;
            color: var(--text-dark);
            font-size: 14px;
            font-weight: 500;
            border-bottom: 1px solid #eef2f8;
        }

        .inst-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .inst-gmu { background: #e3efff; color: #3965ff; }
        .inst-gmit { background: #e8fbed; color: #05cd99; }

        .btn-view {
            color: var(--primary-maroon);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .back-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            display: inline-block;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--primary-maroon);
        }

        /* Filter Row Styling */
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .filter-select {
            padding: 8px 15px;
            border-radius: 12px;
            border: 1px solid #eef2f8;
            color: var(--text-dark);
            font-family: inherit;
            font-size: 14px;
            outline: none;
            cursor: pointer;
            min-width: 200px;
        }

        .filter-select:focus {
            border-color: var(--primary-maroon);
        }

        .search-input {
            padding: 8px 15px;
            border-radius: 12px;
            border: 1px solid #eef2f8;
            font-family: inherit;
            font-size: 14px;
            min-width: 250px;
            outline: none;
        }

        .search-input:focus {
            border-color: var(--primary-maroon);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <div class="header">
            <div class="header-title">
                <h1>Central Resumes</h1>
                <p>List of all students who have built their resumes on the platform.</p>
            </div>
            <div class="user-profile">
                <span style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($fullName); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($fullName, 0, 1)); ?></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-file-pdf" style="color: var(--primary-maroon);"></i> Student Resume Database 
                    <span style="font-weight: 400; font-size: 14px; color: var(--text-muted); margin-left: 10px;">(<?php echo count($resumes); ?> Total)</span>
                </div>
                <div class="filter-row">
                    <input type="text" id="resumeSearch" class="search-input" placeholder="Search by name or USN...">
                    <select id="deptFilter" class="filter-select">
                        <option value="">All Departments</option>
                        <?php foreach($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>USN / ID</th>
                        <th>Institution</th>
                        <th>Department</th>
                        <th>Built Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="resumeTableBody">
                    <?php foreach($resumes as $r): ?>
                        <tr class="resume-row" data-department="<?php echo htmlspecialchars($r['department']); ?>">
                            <td>
                                <div style="font-weight: 700;"><?php echo htmlspecialchars($r['full_name']); ?></div>
                            </td>
                            <td><code style="background: #f0f0f0; padding: 2px 5px; border-radius: 4px; font-size: 12px;"><?php echo htmlspecialchars($r['student_id']); ?></code></td>
                            <td>
                                <span class="inst-badge <?php echo strtolower($r['institution']) === 'gmit' ? 'inst-gmit' : 'inst-gmu'; ?>">
                                    <?php echo htmlspecialchars($r['institution']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($r['department']); ?></td>
                            <td style="color: var(--text-muted);">
                                <?php echo date('d M Y, h:i A', strtotime($r['built_at'])); ?>
                            </td>
                            <td>
                                <a href="../<?php echo htmlspecialchars($r['pdf_path']); ?>" target="_blank" class="btn-view">
                                    <i class="fas fa-external-link-alt"></i> View PDF
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($resumes)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 50px;">No resumes found in the centralized database.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const resumeSearch = document.getElementById('resumeSearch');
        const deptFilter = document.getElementById('deptFilter');
        const rows = document.querySelectorAll('.resume-row');

        function filterResumes() {
            const searchTerm = resumeSearch.value.toLowerCase();
            const selectedDept = deptFilter.value;

            rows.forEach(row => {
                const nameAndUsn = row.textContent.toLowerCase();
                const dept = row.getAttribute('data-department');
                
                const matchesSearch = nameAndUsn.includes(searchTerm);
                const matchesDept = selectedDept === "" || dept === selectedDept;

                if (matchesSearch && matchesDept) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }

        resumeSearch.addEventListener('input', filterResumes);
        deptFilter.addEventListener('change', filterResumes);
    </script>
</body>
</html>
