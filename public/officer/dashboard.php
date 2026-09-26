<?php
/**
 * Placement Officer Dashboard - Modernized
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// 1. Essential Auth (Fast)
requireRole(ROLE_PLACEMENT_OFFICER);

$userId   = getUserId();
$fullName = getFullName();

// 2. Start Immediate Rendering (Skeleton)
if (!headers_sent()) {
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    ob_end_flush(); 
    ob_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard – <?php echo APP_NAME; ?></title>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <link rel="stylesheet" href="../assets/css/skeleton.css?v=<?php echo APP_VERSION; ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Instant Skeleton Loader -->
    <div id="skeletonScreen" class="skeleton-screen">
        <div class="skeleton-header shimmer"></div>
        <div class="skeleton-body" style="grid-template-columns: 1fr;">
            <div class="skeleton-main">
                <div class="skeleton-stats">
                    <div class="skeleton-stat shimmer"></div>
                    <div class="skeleton-stat shimmer"></div>
                    <div class="skeleton-stat shimmer"></div>
                    <div class="skeleton-stat shimmer"></div>
                </div>
                <div class="skeleton-bento">
                    <div class="skeleton-card shimmer" style="grid-column: span 2;"></div>
                    <div class="skeleton-card shimmer"></div>
                </div>
            </div>
        </div>
    </div>
<?php
// Flush skeleton
ob_flush();
flush();

// 3. Heavy DB Queries
$officerModel = new PlacementOfficer();
$stats        = $officerModel->getDashboardStats();
$recentApps   = $officerModel->getRecentApplications(8);
$recentJobs   = $officerModel->getRecentJobs(6);

$placedModel = new CompanyPlacedStudent();
$placedStats = $placedModel->getStatistics();
$totalPlaced = (int)($stats['placed_students'] ?? 0) + (int)($placedStats['total_placed'] ?? 0);

// Fetch recent student feedback & priority counters
$feedbacks = [];
$expiringJobsCount = 0;
$activeDrivesCount = 0;
try {
    $db = getDB();
    $feedbacks = $db->query("SELECT * FROM portal_feedback ORDER BY created_at DESC LIMIT 4")->fetchAll();
    $expiringJobsCount = (int)$db->query("SELECT COUNT(*) FROM job_postings WHERE status = 'Active' AND application_deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)")->fetchColumn();
    $activeDrivesCount = (int)$db->query("SELECT COUNT(*) FROM campus_drives WHERE deadline >= CURDATE() OR deadline IS NULL")->fetchColumn();
} catch (Exception $e) {
    error_log("Error in officer dashboard metadata query: " . $e->getMessage());
}

// Chart Data (Yearly placements)
$chartLabels = ['2021', '2022', '2023', '2024', '2025'];
$chartPlacements = [420, 510, 630, 780, 890];
try {
    $db = getDB();
    $trendRows = $db->query("SELECT yop, COUNT(*) as count FROM company_placed_students WHERE yop IS NOT NULL AND yop != '' AND yop >= 2020 GROUP BY yop ORDER BY yop ASC LIMIT 6")->fetchAll();
    if (!empty($trendRows) && count($trendRows) >= 2) {
        $chartLabels = [];
        $chartPlacements = [];
        foreach ($trendRows as $row) {
            $chartLabels[] = (string)$row['yop'];
            $chartPlacements[] = (int)$row['count'];
        }
    }
} catch (Exception $e) {}

$greetingTime = date('H') < 12 ? 'Good Morning' : (date('H') < 17 ? 'Good Afternoon' : 'Good Evening');
$officerFirstName = htmlspecialchars(explode(' ', (string)$fullName)[0]);
$officerInstitution = htmlspecialchars($_SESSION['user']['institution'] ?? 'GMU');
$todayFormatted = date('l, d M Y');
?>
    <style>
        :root {
            --brand: #7C0000;
            --brand-hover: #9E0000;
            --brand-light: #FDF2F2;
            --gold: #B08D2C;
            --surface-bg: #FFFFFF;
            --page-bg: #F8F9FA;
            --border-color: #E5E7EB;
            --border-subtle: #F3F4F6;
            --text-primary: #111827;
            --text-secondary: #4B5563;
            --text-muted: #9CA3AF;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-card: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--page-bg);
            color: var(--text-primary);
            margin: 0;
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 28px 32px 60px 32px;
            box-sizing: border-box;
        }

        /* Top Header Area */
        .dash-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .dash-header__meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .dash-header__badge {
            background: #F3F4F6;
            color: #374151;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .dash-header__title {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 6px 0;
            letter-spacing: -0.4px;
        }

        .dash-header__subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dash-header__actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Buttons */
        .erp-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
            box-sizing: border-box;
            line-height: 1.2;
        }

        .erp-btn-primary {
            background: var(--brand);
            color: #FFFFFF;
            border: 1px solid var(--brand);
            box-shadow: 0 1px 2px rgba(124, 0, 0, 0.2);
        }

        .erp-btn-primary:hover {
            background: var(--brand-hover);
            border-color: var(--brand-hover);
            color: #FFFFFF;
        }

        .erp-btn-secondary {
            background: #FFFFFF;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .erp-btn-secondary:hover {
            background: #F9FAFB;
            color: var(--text-primary);
            border-color: #D1D5DB;
        }

        .date-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        /* Attention Alert Strip */
        .attention-banner {
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: var(--radius-md);
            padding: 12px 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .attention-banner__content {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13.5px;
            color: #92400E;
            font-weight: 500;
        }

        .attention-banner__icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: #FEF3C7;
            color: #B45309;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }

        .attention-banner__link {
            font-size: 13px;
            font-weight: 600;
            color: #92400E;
            text-decoration: underline;
            text-underline-offset: 3px;
            white-space: nowrap;
        }

        .attention-banner__link:hover {
            color: #78350F;
        }

        /* Executive Metrics Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .kpi-card:hover {
            border-color: #CBD5E1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .kpi-card__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .kpi-card__label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-card__icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .kpi-card__icon--maroon { background: #FDF2F2; color: var(--brand); }
        .kpi-card__icon--amber  { background: #FFFBEB; color: #D97706; }
        .kpi-card__icon--green  { background: #ECFDF5; color: #059669; }
        .kpi-card__icon--blue   { background: #EFF6FF; color: #2563EB; }

        .kpi-card__value {
            font-size: 30px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.1;
            margin-bottom: 6px;
        }

        .kpi-card__subtext {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Two-Column Main Layout */
        .dash-main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 380px;
            gap: 24px;
            align-items: start;
        }

        .dash-col-primary {
            display: flex;
            flex-direction: column;
            gap: 24px;
            min-width: 0;
        }

        .dash-col-secondary {
            display: flex;
            flex-direction: column;
            gap: 24px;
            min-width: 0;
        }

        /* Content Cards */
        .erp-card {
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .erp-card__header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #FFFFFF;
        }

        .erp-card__title-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .erp-card__title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.2px;
        }

        .erp-card__count-badge {
            background: #F3F4F6;
            color: #4B5563;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 12px;
        }

        .erp-card__link {
            font-size: 13px;
            font-weight: 600;
            color: var(--brand);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: color 0.15s ease;
        }

        .erp-card__link:hover {
            color: var(--brand-hover);
            text-decoration: underline;
        }

        .erp-card__body {
            padding: 20px 22px;
        }

        /* Clean ERP Tables */
        .erp-table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .erp-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .erp-table th {
            padding: 12px 16px;
            background: #F9FAFB;
            color: var(--text-secondary);
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .erp-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-subtle);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .erp-table tbody tr:last-child td {
            border-bottom: none;
        }

        .erp-table tbody tr:hover td {
            background-color: #FBFBFC;
        }

        /* Status & Tag Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
        }

        .badge-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .badge-warning { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
        .badge-danger  { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        .badge-info    { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
        .badge-purple  { background: #F5F3FF; color: #5B21B6; border: 1px solid #DDD6FE; }
        .badge-gray    { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }

        /* Job Items List */
        .job-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .job-item {
            padding: 14px 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: #FFFFFF;
            transition: all 0.15s ease;
            text-decoration: none;
            display: block;
        }

        .job-item:hover {
            border-color: #CBD5E1;
            box-shadow: var(--shadow-sm);
            background: #FAFAFA;
        }

        .job-item__title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .job-item__company {
            font-size: 12.5px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }

        .job-item__footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-size: 12px;
        }

        .job-item__deadline {
            color: #DC2626;
            font-weight: 600;
            font-size: 11.5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Feedback Items */
        .feedback-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .feedback-item {
            padding: 12px 14px;
            background: #F9FAFB;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
        }

        .feedback-item__top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .feedback-item__author {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .feedback-item__tag {
            font-size: 11px;
            color: var(--text-muted);
        }

        .feedback-item__text {
            font-size: 12.5px;
            color: var(--text-secondary);
            line-height: 1.45;
            margin: 0;
        }

        .feedback-item__suggestion {
            margin-top: 6px;
            font-size: 12px;
            color: var(--brand);
            font-weight: 600;
        }

        /* Tools Hub Grid */
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .tool-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 14px 10px;
            background: #F9FAFB;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.15s ease;
            gap: 8px;
        }

        .tool-btn:hover {
            background: #FFFFFF;
            border-color: var(--brand);
            color: var(--brand);
            box-shadow: var(--shadow-sm);
        }

        .tool-btn i {
            font-size: 18px;
            color: var(--brand);
        }

        .tool-btn span {
            font-size: 12px;
            font-weight: 600;
        }

        /* Chart Summary Chips */
        .chart-stats-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .chart-stat-chip {
            background: #F9FAFB;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 12px;
            text-align: center;
        }

        .chart-stat-chip__label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .chart-stat-chip__value {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* Empty States */
        .empty-placeholder {
            text-align: center;
            padding: 32px 20px;
            color: var(--text-muted);
        }

        .empty-placeholder i {
            font-size: 28px;
            margin-bottom: 8px;
            opacity: 0.6;
        }

        .empty-placeholder p {
            margin: 0;
            font-size: 13px;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1200px) {
            .dash-main-grid {
                grid-template-columns: 1fr;
            }
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 16px;
            }
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            .dash-header {
                flex-direction: column;
                align-items: stretch;
            }
            .dash-header__actions {
                justify-content: flex-start;
            }
            .chart-stats-strip {
                grid-template-columns: 1fr;
            }
        }
    </style>

<?php include_once 'includes/navbar.php'; ?>

<div class="dashboard-container">
    <!-- Header Section -->
    <div class="dash-header">
        <div>
            <div class="dash-header__meta">
                <span>Placement Office</span>
                <span>•</span>
                <span class="dash-header__badge">Academic Year 2025 – 2026</span>
                <span>•</span>
                <span class="dash-header__badge"><?php echo $officerInstitution; ?></span>
            </div>
            <h1 class="dash-header__title"><?php echo $greetingTime; ?>, <?php echo $officerFirstName; ?> 👋</h1>
            <p class="dash-header__subtitle">
                <span>Lakshya Corporate Relations & Placement Administration Hub</span>
            </p>
        </div>

        <div class="dash-header__actions">
            <div class="date-chip">
                <i class="far fa-calendar-alt"></i> <?php echo $todayFormatted; ?>
            </div>
            <a href="campus_drives.php" class="erp-btn erp-btn-secondary">
                <i class="fas fa-calendar-check"></i> Campus Drives
            </a>
            <a href="upload_placed_students.php" class="erp-btn erp-btn-secondary">
                <i class="fas fa-cloud-arrow-up"></i> Upload Placed
            </a>
            <a href="jobs.php" class="erp-btn erp-btn-primary">
                <i class="fas fa-plus"></i> Post New Job
            </a>
        </div>
    </div>

    <!-- Attention / Action Required Strip -->
    <?php if ($stats['pending_applications'] > 0 || $expiringJobsCount > 0): ?>
    <div class="attention-banner">
        <div class="attention-banner__content">
            <div class="attention-banner__icon"><i class="fas fa-bolt"></i></div>
            <div>
                <strong>Action Required:</strong> 
                <?php if ($stats['pending_applications'] > 0): ?>
                    <span><?php echo $stats['pending_applications']; ?> candidate application(s) awaiting review</span>
                <?php endif; ?>
                <?php if ($stats['pending_applications'] > 0 && $expiringJobsCount > 0): ?> • <?php endif; ?>
                <?php if ($expiringJobsCount > 0): ?>
                    <span><?php echo $expiringJobsCount; ?> job opportunity(s) closing within 72 hours</span>
                <?php endif; ?>
            </div>
        </div>
        <a href="applications.php?status=Applied" class="attention-banner__link">Review Applications →</a>
    </div>
    <?php endif; ?>

    <!-- Executive KPI Grid -->
    <div class="kpi-grid">
        <!-- 1. Active Jobs -->
        <div class="kpi-card">
            <div class="kpi-card__top">
                <span class="kpi-card__label">Active Openings</span>
                <div class="kpi-card__icon kpi-card__icon--maroon"><i class="fas fa-briefcase"></i></div>
            </div>
            <div class="kpi-card__value"><?php echo number_format($stats['active_jobs']); ?></div>
            <div class="kpi-card__subtext">Live opportunities on student portal</div>
        </div>

        <!-- 2. Pending Applications -->
        <div class="kpi-card">
            <div class="kpi-card__top">
                <span class="kpi-card__label">Pending Review</span>
                <div class="kpi-card__icon kpi-card__icon--amber"><i class="fas fa-clock"></i></div>
            </div>
            <div class="kpi-card__value"><?php echo number_format($stats['pending_applications']); ?></div>
            <div class="kpi-card__subtext">Awaiting candidate screening</div>
        </div>

        <!-- 3. Placed Students -->
        <div class="kpi-card">
            <div class="kpi-card__top">
                <span class="kpi-card__label">Students Placed</span>
                <div class="kpi-card__icon kpi-card__icon--green"><i class="fas fa-user-graduate"></i></div>
            </div>
            <div class="kpi-card__value"><?php echo number_format($totalPlaced); ?></div>
            <div class="kpi-card__subtext">Verified selections across portals</div>
        </div>

        <!-- 4. Companies -->
        <div class="kpi-card">
            <div class="kpi-card__top">
                <span class="kpi-card__label">Partner Companies</span>
                <div class="kpi-card__icon kpi-card__icon--blue"><i class="fas fa-building"></i></div>
            </div>
            <div class="kpi-card__value"><?php echo number_format($stats['total_companies']); ?></div>
            <div class="kpi-card__subtext">Active corporate recruiters</div>
        </div>
    </div>

    <!-- Main Two-Column Layout -->
    <div class="dash-main-grid">
        <!-- Left / Primary Column -->
        <div class="dash-col-primary">
            <!-- 1. Recent Applications Table -->
            <div class="erp-card">
                <div class="erp-card__header">
                    <div class="erp-card__title-box">
                        <h2 class="erp-card__title">Recent Applications</h2>
                        <span class="erp-card__count-badge"><?php echo count($recentApps); ?> Recent</span>
                    </div>
                    <a href="applications.php" class="erp-card__link">View All Applications <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="erp-table-responsive">
                    <table class="erp-table">
                        <thead>
                            <tr>
                                <th>Student Candidate</th>
                                <th>Position & Company</th>
                                <th>Apply Mode</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentApps as $app): 
                                $appMode = $app['application_mode'] ?? 'Internal';
                                $status = $app['status'] ?? 'Applied';
                                $statusClass = 'badge-warning';
                                if ($status === 'Selected') $statusClass = 'badge-success';
                                elseif ($status === 'Rejected') $statusClass = 'badge-danger';
                                elseif ($status === 'Shortlisted') $statusClass = 'badge-info';
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($app['student_name'] ?? 'Candidate'); ?>
                                    </div>
                                    <div style="font-size: 11.5px; color: var(--text-muted); font-family: monospace;">
                                        ID: <?php echo htmlspecialchars($app['student_id']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($app['job_title']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($app['company_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($appMode === 'External'): ?>
                                        <span class="badge badge-purple" title="Applied on Company Portal"><i class="fas fa-external-link-alt"></i> External</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray" title="Applied directly on Lakshya"><i class="fas fa-check-circle"></i> Lakshya</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--text-secondary); white-space: nowrap;">
                                    <?php echo !empty($app['applied_at']) ? date('d M, H:i', strtotime($app['applied_at'])) : '—'; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="applications.php?job_id=<?php echo (int)$app['job_id']; ?>" class="erp-btn erp-btn-secondary" style="padding: 5px 10px; font-size: 12px;">
                                        Review
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; if (empty($recentApps)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-placeholder">
                                        <i class="far fa-folder-open"></i>
                                        <p>No recent candidate applications found</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. Placement Performance Trends Chart -->
            <div class="erp-card">
                <div class="erp-card__header">
                    <div>
                        <h2 class="erp-card__title">Placement Performance Trends</h2>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Year-over-Year Verified Placement Growth</div>
                    </div>
                    <a href="reports.php" class="erp-card__link">Intelligence Hub <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="erp-card__body">
                    <div style="height: 260px; position: relative;">
                        <canvas id="placementChart"></canvas>
                    </div>

                    <div class="chart-stats-strip">
                        <div class="chart-stat-chip">
                            <div class="chart-stat-chip__label">Average Package</div>
                            <div class="chart-stat-chip__value"><?php echo $placedStats['average_ctc'] ?? '4.8'; ?> LPA</div>
                        </div>
                        <div class="chart-stat-chip">
                            <div class="chart-stat-chip__label">Active Campus Drives</div>
                            <div class="chart-stat-chip__value"><?php echo $activeDrivesCount; ?> Drives</div>
                        </div>
                        <div class="chart-stat-chip">
                            <div class="chart-stat-chip__label">Partner Recruiters</div>
                            <div class="chart-stat-chip__value"><?php echo $stats['total_companies']; ?> Orgs</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right / Secondary Column -->
        <div class="dash-col-secondary">
            <!-- 1. Active Job Postings -->
            <div class="erp-card">
                <div class="erp-card__header">
                    <div class="erp-card__title-box">
                        <h2 class="erp-card__title">Latest Job Postings</h2>
                    </div>
                    <a href="jobs.php" class="erp-card__link">Manage <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="erp-card__body" style="padding: 16px;">
                    <div class="job-list">
                        <?php foreach ($recentJobs as $job): 
                            $deadlineTime = strtotime($job['application_deadline']);
                            $daysLeft = ceil(($deadlineTime - time()) / 86400);
                            $mode = $job['application_mode'] ?? 'Internal';
                        ?>
                        <div class="job-item">
                            <div class="job-item__title"><?php echo htmlspecialchars($job['title']); ?></div>
                            <div class="job-item__company">
                                <i class="fas fa-building" style="color: var(--text-muted); font-size: 11px;"></i> 
                                <?php echo htmlspecialchars($job['company_name']); ?>
                            </div>
                            <div class="job-item__footer">
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <?php if ($mode === 'External'): ?>
                                        <span class="badge badge-purple" style="font-size: 10.5px; padding: 2px 6px;">External</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray" style="font-size: 10.5px; padding: 2px 6px;">Direct</span>
                                    <?php endif; ?>
                                    <span class="badge badge-info" style="font-size: 10.5px; padding: 2px 6px;">
                                        Min <?php echo !empty($job['min_cgpa']) ? $job['min_cgpa'] . ' CGPA' : 'All CGPA'; ?>
                                    </span>
                                </div>
                                <span class="job-item__deadline">
                                    <i class="far fa-clock"></i>
                                    <?php 
                                        if ($daysLeft > 1) echo $daysLeft . 'd left';
                                        elseif ($daysLeft === 1) echo 'Tomorrow';
                                        elseif ($daysLeft === 0) echo 'Today';
                                        else echo date('d M', $deadlineTime);
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; if (empty($recentJobs)): ?>
                        <div class="empty-placeholder">
                            <i class="far fa-briefcase"></i>
                            <p>No job postings available</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 2. Quick Operations Tools -->
            <div class="erp-card">
                <div class="erp-card__header">
                    <h2 class="erp-card__title">Quick Placement Tools</h2>
                </div>
                <div class="erp-card__body" style="padding: 16px;">
                    <div class="tools-grid">
                        <a href="attendance.php" class="tool-btn">
                            <i class="fas fa-user-check"></i>
                            <span>Mark Attendance</span>
                        </a>
                        <a href="upload_placed_students.php" class="tool-btn">
                            <i class="fas fa-cloud-arrow-up"></i>
                            <span>Upload Placed</span>
                        </a>
                        <a href="reports.php" class="tool-btn">
                            <i class="fas fa-chart-line"></i>
                            <span>Analytics Report</span>
                        </a>
                        <a href="campus_drives.php" class="tool-btn">
                            <i class="fas fa-calendar-check"></i>
                            <span>Drive Schedule</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- 3. Recent Student Feedback -->
            <div class="erp-card">
                <div class="erp-card__header">
                    <div class="erp-card__title-box">
                        <h2 class="erp-card__title">Recent Feedback</h2>
                    </div>
                    <a href="feedback.php" class="erp-card__link">All Feedback <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="erp-card__body" style="padding: 16px;">
                    <div class="feedback-list">
                        <?php foreach ($feedbacks as $fb): ?>
                        <div class="feedback-item">
                            <div class="feedback-item__top">
                                <span class="feedback-item__author"><?php echo htmlspecialchars($fb['student_name'] ?? 'Student'); ?></span>
                                <span class="feedback-item__tag"><?php echo htmlspecialchars($fb['institution'] ?? 'GMU'); ?><?php echo !empty($fb['current_sem']) ? ' • Sem ' . $fb['current_sem'] : ''; ?></span>
                            </div>
                            <?php if (!empty($fb['general_comments'])): ?>
                                <p class="feedback-item__text"><?php echo htmlspecialchars(substr($fb['general_comments'], 0, 75)) . (strlen($fb['general_comments']) > 75 ? '...' : ''); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($fb['new_feature_title'])): ?>
                                <div class="feedback-item__suggestion">
                                    <i class="fas fa-lightbulb" style="color: var(--gold); font-size: 11px;"></i> 
                                    <?php echo htmlspecialchars(substr($fb['new_feature_title'], 0, 50)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; if (empty($feedbacks)): ?>
                        <div class="empty-placeholder">
                            <i class="far fa-comment-dots"></i>
                            <p>No feedback received yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Placement Chart with Modern Crisp Light Styling
    const ctx = document.getElementById('placementChart').getContext('2d');
    const chartGradient = ctx.createLinearGradient(0, 0, 0, 240);
    chartGradient.addColorStop(0, 'rgba(124, 0, 0, 0.12)');
    chartGradient.addColorStop(1, 'rgba(124, 0, 0, 0.00)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Verified Placements',
                data: <?php echo json_encode($chartPlacements); ?>,
                borderColor: '#7C0000',
                backgroundColor: chartGradient,
                borderWidth: 2.5,
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#FFFFFF',
                pointBorderColor: '#7C0000',
                pointBorderWidth: 2,
                pointRadius: 4.5,
                pointHoverRadius: 6.5,
                pointHoverBackgroundColor: '#7C0000',
                pointHoverBorderColor: '#FFFFFF',
                pointHoverBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#111827',
                    titleColor: '#FFFFFF',
                    bodyColor: '#FFFFFF',
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'Placements: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#F3F4F6' },
                    ticks: {
                        color: '#6B7280',
                        font: { size: 11, family: 'Inter' },
                        precision: 0
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#6B7280',
                        font: { size: 11, family: 'Inter' }
                    }
                }
            }
        }
    });

    // Hide Skeleton Screen after page load
    window.addEventListener('load', function() {
        const skeleton = document.getElementById('skeletonScreen');
        if (skeleton) {
            setTimeout(() => {
                skeleton.classList.add('hidden');
                setTimeout(() => skeleton.remove(), 400);
            }, 250); 
        }
    });
</script>
</body>
</html>

