<?php
/**
 * Internship Officer Dashboard (Overhauled)
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole('internship_officer');

$userId = getUserId();
$fullName = getFullName();

$internshipModel = new Internship();
$internships = $internshipModel->getPortalInternships(); // Only show internships from this portal


$stats = [
    'active' => 0,
    'total_applications' => 0,
    'total_internships' => count($internships)
];

foreach ($internships as $i) {
    if ($i['status'] === 'Active')
        $stats['active']++;
    $stats['total_applications'] += $i['application_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --primary: #800000;
            --primary-light: #fef2f2;
            --primary-soft: rgba(128, 0, 0, 0.05);
            --primary-hover: #600000;
            --bg-body: #f1f5f9;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --success: #10b981;
            --info: #3b82f6;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.05), 0 4px 6px -4px rgb(0 0 0 / 0.05);
            --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.05), 0 8px 10px -6px rgb(0 0 0 / 0.05);
            --radius-md: 12px;
            --radius-lg: 20px;
            --radius-xl: 30px;
        }

        body { 
            background: var(--bg-body); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            color: var(--text-main);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        /* Welcome Section */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
            animation: fadeInDown 0.6s ease-out;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .welcome-text h1 {
            font-size: 2.25rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.05em;
            color: var(--text-main);
            background: linear-gradient(135deg, var(--text-main) 0%, #475569 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-text p {
            color: var(--text-muted);
            margin: 0.5rem 0 0 0;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 0.875rem 1.75rem;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(128, 0, 0, 0.3);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.4), transparent);
            transform: translateX(-100%);
            transition: 0.5s;
        }

        .stat-card:hover::before {
            transform: translateX(100%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-soft);
        }

        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            flex-shrink: 0;
        }

        .icon-maroon { background: #fef2f2; color: var(--primary); }
        .icon-blue { background: #eff6ff; color: var(--info); }
        .icon-green { background: #f0fdf4; color: var(--success); }

        .stat-info p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .stat-info h3 {
            font-size: 2.25rem;
            font-weight: 800;
            margin: 0.125rem 0 0 0;
            letter-spacing: -0.025em;
            color: var(--text-main);
        }

        /* Content Card */
        .content-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            padding: 2rem 2.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafafa;
        }

        .card-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-main);
        }

        .card-header .subtitle {
            font-size: 0.95rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Table Styling */
        .table-container {
            padding: 1rem 1.5rem 2rem 1.5rem;
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 1rem;
        }

        .custom-table th {
            text-align: left;
            padding: 0.75rem 1.5rem;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .custom-table tbody tr {
            transition: all 0.2s ease;
        }

        .custom-table tbody td {
            background: #fff;
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .custom-table tbody td:first-child {
            border-left: 1px solid var(--border-color);
            border-top-left-radius: 16px;
            border-bottom-left-radius: 16px;
        }

        .custom-table tbody td:last-child {
            border-right: 1px solid var(--border-color);
            border-top-right-radius: 16px;
            border-bottom-right-radius: 16px;
        }

        .custom-table tbody tr:hover td {
            background: #fdfdfd;
            border-color: #cbd5e1;
            transform: scale(1.002);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        .company-info { display: flex; align-items: center; gap: 1rem; }
        .company-logo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: contain;
            background: white;
            padding: 6px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .company-initials {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1rem;
        }
        
        .title-text { font-weight: 700; color: var(--text-main); font-size: 1.05rem; }
        .subtitle-text { font-size: 0.9rem; color: var(--text-muted); margin-top: 0.25rem; }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-inactive { background: #f1f5f9; color: #475569; }

        .app-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f0f9ff;
            color: #0369a1;
            padding: 0.4rem 0.8rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            gap: 0.5rem;
        }

        .action-group {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-muted);
        }

        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-view:hover { color: var(--info); border-color: var(--info); background: #eff6ff; }
        .btn-edit:hover { color: var(--warning); border-color: var(--warning); background: #fffbeb; }
        .btn-delete:hover { color: var(--danger); border-color: var(--danger); background: #fef2f2; }
        
        .empty-state {
            padding: 6rem 2rem;
            text-align: center;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--border-color);
            margin-bottom: 2rem;
        }

        @media (max-width: 1024px) {
            .container { padding: 2rem 1rem; }
            .header-section { flex-direction: column; align-items: flex-start; gap: 1.5rem; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        
        <div class="header-section">
            <div class="welcome-text">
                <h1>Welcome back, <?php echo htmlspecialchars((string)$fullName); ?></h1>
                <p>Monitor your active internships and track student applications.</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="internship_placed.php" class="btn-primary" style="background: var(--card-bg); color: var(--primary); border: 1px solid var(--primary); box-shadow: none;">
                    <i class="fas fa-user-graduate"></i> Placed Students
                </a>
                <a href="add_internship.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Post New Internship
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-maroon"><i class="fas fa-briefcase"></i></div>
                <div class="stat-info">
                    <p>Total Postings</p>
                    <h3><?php echo $stats['total_internships']; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-bolt-lightning"></i></div>
                <div class="stat-info">
                    <p>Active Now</p>
                    <h3><?php echo $stats['active']; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <p>Applications</p>
                    <h3><?php echo $stats['total_applications']; ?></h3>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h2><i class="fas fa-list-ul" style="color: var(--primary);"></i> Recent Postings</h2>
                <div class="subtitle">
                    Showing <?php echo count($internships); ?> internships
                </div>
            </div>
            
            <div class="table-container">
                <?php if (empty($internships)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h3>No internship postings yet</h3>
                        <p style="color: var(--text-muted); margin-bottom: 2rem;">Get started by posting your first internship opportunity for the students.</p>
                        <a href="add_internship.php" class="btn-primary">Post Internship</a>
                    </div>
                <?php
else: ?>
                    <div style="overflow-x: auto;">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Position Details</th>
                                    <th>Deadline</th>
                                    <th>Applicants</th>
                                    <th style="text-align: center;">Status</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($internships as $i): ?>
                                    <tr>
                                        <td>
                                            <div class="company-info">
                                                <?php if (!empty($i['company_logo'])): ?>
                                                    <img src="../<?php echo $i['company_logo']; ?>" class="company-logo" alt="Logo">
                                                <?php
        else: ?>
                                                    <div class="company-initials">
                                                        <?php echo strtoupper(substr($i['company_name'], 0, 2)); ?>
                                                    </div>
                                                <?php
        endif; ?>
                                                <div>
                                                    <div class="title-text"><?php echo htmlspecialchars($i['company_name']); ?></div>
                                                    <div class="subtitle-text"><?php echo htmlspecialchars($i['location']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="title-text"><?php echo htmlspecialchars($i['internship_title']); ?></div>
                                            <div class="subtitle-text"><?php echo htmlspecialchars($i['duration']); ?> • <?php echo htmlspecialchars($i['stipend']); ?></div>
                                        </td>
                                        <td>
                                            <div class="title-text">
                                                <?php echo date('M d, Y', strtotime($i['application_deadline'])); ?>
                                            </div>
                                            <div class="subtitle-text">Posted <?php echo date('j M', strtotime($i['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <div class="app-count-badge">
                                                <i class="fas fa-users"></i>
                                                <?php echo $i['application_count']; ?>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="badge <?php echo $i['status'] === 'Active' ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo $i['status']; ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <div class="action-group">
                                                <a href="applications.php?id=<?php echo $i['id']; ?>" class="btn-icon btn-view" title="View Applicants">
                                                    <i class="fas fa-users-viewfinder"></i>
                                                </a>
                                                <a href="edit_internship.php?id=<?php echo $i['id']; ?>" class="btn-icon btn-edit" title="Edit Position">
                                                    <i class="fas fa-pen-to-square"></i>
                                                </a>
                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $i['id']; ?>, '<?php echo addslashes($i['internship_title']); ?>')" class="btn-icon btn-delete" title="Remove Position">
                                                    <i class="fas fa-trash-can"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
    endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php
endif; ?>
            </div>
        </div>

        <script>
            function confirmDelete(id, title) {
                if (confirm('Are you sure you want to delete the internship "' + title + '"? This action cannot be undone.')) {
                    window.location.href = 'delete_internship.php?id=' + id;
                }
            }
        </script>
    </div>
</body>
</html>
