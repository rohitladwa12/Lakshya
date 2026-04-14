<?php
/**
 * Student - Internships List (Overhauled UI)
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

$userId = getUserId();
$internshipModel = new Internship();
$internships = $internshipModel->getActiveInternships();

/**
 * Helper to determine status badge
 */
function getStatusBadge($deadline) {
    $today = date('Y-m-d');
    if ($deadline < $today) {
        return '<span class="tag tag-status-ended">Ended</span>';
    }
    return '<span class="tag tag-status-open">Open</span>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internships - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root { 
            --primary: #800000; 
            --primary-light: #fef2f2;
            --bg-body: #f1f5f9; 
            --card-bg: #ffffff; 
            --text-main: #0f172a; 
            --text-muted: #64748b; 
            --border: #e2e8f0;
            --success: #10b981;
            --info: #3b82f6;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            --shadow-hover: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        
        body { 
            background: var(--bg-body); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            color: var(--text-main);
            line-height: 1.5;
        }
        
        .container { 
            max-width: 1100px; 
            margin: 0 auto; 
            padding: 3rem 1.5rem; 
        }
        
        .page-header { 
            margin-bottom: 3rem; 
            text-align: left; 
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-title { 
            font-size: 2rem; 
            font-weight: 800; 
            letter-spacing: -0.04em;
            margin: 0;
            color: var(--text-main);
        }
        
        .internship-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
            gap: 1.5rem; 
        }
        
        .internship-card { 
            background: var(--card-bg); 
            border-radius: 16px; 
            padding: 1.5rem; 
            border: 1px solid var(--border); 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; 
            flex-direction: column;
            position: relative;
            box-shadow: var(--shadow);
        }
        
        .internship-card:hover { 
            transform: translateY(-4px); 
            box-shadow: var(--shadow-hover);
            border-color: var(--primary-light);
        }
        
        .card-header-flex {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .logo-box {
            width: 56px;
            height: 56px;
            min-width: 56px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 4px;
        }

        .company-logo { 
            max-width: 100%; 
            max-height: 100%; 
            object-fit: contain;
        }

        .logo-placeholder {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .title-area {
            flex-grow: 1;
        }

        .company-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .internship-title { 
            font-size: 1.15rem; 
            font-weight: 700; 
            color: var(--text-main); 
            margin: 0;
            line-height: 1.3;
        }
        
        .meta-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .tag {
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .tag-status-open { background: #dcfce7; color: #15803d; }
        .tag-status-ended { background: #fee2e2; color: #b91c1c; }
        .tag-type { background: #eff6ff; color: #1d4ed8; }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-top: 1rem;
            border-top: 1px dashed var(--border);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .info-item i {
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .stipend-text {
            color: var(--info);
            font-weight: 700;
        }

        .stats-mini {
            display: flex;
            justify-content: space-between;
            background: var(--bg-body);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-val {
            display: block;
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .stat-lbl {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .action-footer {
            margin-top: auto;
        }

        .btn-view {
            width: 100%;
            background: var(--primary);
            color: white;
            padding: 0.75rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(128, 0, 0, 0.2);
        }

        .btn-view:hover {
            background: #600000;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(128, 0, 0, 0.3);
        }

        .deadline-warning {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--danger);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem 1rem;
            }

            .page-header {
                margin-bottom: 2rem;
                text-align: center;
            }

            .page-title {
                font-size: 1.75rem;
            }

            .internship-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .internship-card {
                padding: 1.25rem;
            }

            .card-header-flex {
                gap: 1rem;
                margin-bottom: 1rem;
            }

            .logo-box {
                width: 48px;
                height: 48px;
                min-width: 48px;
            }

            .internship-title {
                font-size: 1.05rem;
            }

            .info-grid {
                gap: 0.5rem;
                margin-bottom: 1rem;
            }

            .info-item {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .card-header-flex {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .stats-mini {
                padding: 0.5rem;
            }

            .stat-val {
                font-size: 0.9rem;
            }

            .stat-lbl {
                font-size: 0.55rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Explore Internships</h1>
            <p style="color: var(--text-muted); font-size: 1rem; margin-top: 0.5rem;">Opportunities handpicked for your career growth.</p>
        </div>
        
        <?php if (empty($internships)): ?>
            <div style="text-align: center; padding: 5rem 2rem; background: white; border-radius: 24px; border: 1px solid var(--border);">
                <div style="font-size: 3rem; margin-bottom: 1.5rem; opacity: 0.2;">🔍</div>
                <h2 style="color: var(--text-main); font-size: 1.25rem; font-weight: 700;">No internships found</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Check back later for new opportunities.</p>
            </div>
        <?php else: ?>
            <div class="internship-grid">
                <?php foreach ($internships as $i): ?>
                    <div class="internship-card">
                        <div class="card-header-flex">
                            <div class="logo-box">
                                <?php 
                                $logoPath = !empty($i['company_logo']) ? '../' . $i['company_logo'] : '';
                                $logoExists = $logoPath && file_exists(__DIR__ . '/../../public/' . $i['company_logo']);
                                if ($logoExists): 
                                ?>
                                    <img src="<?php echo $logoPath; ?>" class="company-logo" alt="Logo">
                                <?php else: ?>
                                    <div class="logo-placeholder"><?php echo strtoupper(substr($i['company_name'], 0, 2)); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="title-area">
                                <div class="company-name"><?php echo htmlspecialchars($i['company_name']); ?></div>
                                <h3 class="internship-title"><?php echo htmlspecialchars($i['internship_title']); ?></h3>
                            </div>
                        </div>
                        
                        <div class="meta-tags">
                            <?php echo getStatusBadge($i['application_deadline']); ?>
                            <span class="tag tag-type">Industry Project</span>
                            <?php if (!empty($i['targeted_students'])): ?>
                                <span class="tag tag-type" title="Target Students"><?php echo htmlspecialchars($i['targeted_students']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($i['location']); ?>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-calendar-day"></i> <?php echo htmlspecialchars($i['duration']); ?>
                            </div>
                            <div class="info-item stipend-text">
                                <i class="fas fa-indian-rupee-sign"></i> <?php echo htmlspecialchars($i['stipend']); ?>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($i['positions']); ?> Slots
                            </div>
                        </div>

                        <div class="deadline-warning">
                            <i class="fas fa-hourglass-half"></i> Deadline: <?php echo date('M d, Y', strtotime($i['application_deadline'])); ?>
                        </div>
                        
                        <div class="stats-mini">
                            <div class="stat-item">
                                <span class="stat-val"><?php echo $i['application_count']; ?></span>
                                <span class="stat-lbl">Applied</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-val"><?php echo $i['shortlisted_count']; ?></span>
                                <span class="stat-lbl">Shortlisted</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-val"><?php echo $i['selected_count']; ?></span>
                                <span class="stat-lbl">Hired</span>
                            </div>
                        </div>
                        
                        <div class="action-footer">
                            <a href="internship_details.php?id=<?php echo $i['id']; ?>" class="btn-view">
                                View Details <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
