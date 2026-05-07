<?php
/**
 * Companies Management Grid - Admin View
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/Admin.php';

// Require admin role
requireRole(ROLE_ADMIN);

$fullName = getFullName();
$adminModel = new Admin();
$companies = $adminModel->getCompaniesList();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Management - <?php echo APP_NAME; ?></title>
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

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border-radius: 12px;
            border: 1px solid #eef2f8;
            outline: none;
            font-family: inherit;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
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
        }

        .data-table td {
            padding: 15px 10px;
            color: var(--text-dark);
            font-size: 14px;
            border-bottom: 1px solid #eef2f8;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .company-logo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #f0f4f8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--primary-maroon);
            overflow: hidden;
            border: 1px solid #eef2f8;
        }

        .company-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; }
        .status-active .status-dot { background: #22c55e; }
        .status-inactive .status-dot { background: #ef4444; }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            border: 1px solid #eef2f8;
            background: none;
            cursor: pointer;
            transition: var(--transition);
            margin-right: 5px;
        }

        .action-btn:hover {
            border-color: var(--primary-maroon);
            color: var(--primary-maroon);
        }

        .back-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <div class="header">
            <div class="header-title">
                <h1>Company Management</h1>
                <p>Register and manage global partner companies for placements and internships.</p>
            </div>
            <div class="user-profile">
                <span style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($fullName); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($fullName, 0, 1)); ?></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-building" style="color: var(--primary-maroon);"></i> Registered Companies 
                    <span style="font-weight: 400; font-size: 14px; color: var(--text-muted); margin-left: 10px;">(<?php echo count($companies); ?> Total)</span>
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="companySearch" placeholder="Search companies...">
                </div>
            </div>
            
            <table class="data-table" id="companiesTable">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Industry / Sector</th>
                        <th>Location</th>
                        <th>Website</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($companies as $c): ?>
                        <tr>
                            <td>
                                <div class="company-info">
                                    <div class="company-logo">
                                        <?php if ($c['logo_url']): ?>
                                            <img src="<?php echo htmlspecialchars($c['logo_url']); ?>" alt="Logo">
                                        <?php else: ?>
                                            <i class="fas fa-industry"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700;"><?php echo htmlspecialchars($c['name']); ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);">Added <?php echo date('M Y', strtotime($c['created_at'])); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($c['industry'] ?: '-'); ?></div>
                                <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($c['sector'] ?: '-'); ?></div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($c['district'] ?: ($c['state'] ?: '-')); ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($c['country'] ?: ''); ?></div>
                            </td>
                            <td>
                                <?php if ($c['website']): ?>
                                    <a href="<?php echo htmlspecialchars($c['website']); ?>" target="_blank" style="color: #3965ff;"><i class="fas fa-link"></i> Visit</a>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="status-pill <?php echo $c['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <div class="status-dot"></div>
                                    <?php echo $c['is_active'] ? 'Active' : 'Inactive'; ?>
                                </div>
                            </td>
                            <td>
                                <button class="action-btn" title="Edit Company"><i class="fas fa-edit"></i></button>
                                <button class="action-btn" title="Toggle Status"><i class="fas fa-power-off"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.getElementById('companySearch').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#companiesTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>

