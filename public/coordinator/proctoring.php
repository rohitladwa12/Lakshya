<?php
/**
 * Department Coordinator - AI Proctoring & Integrity Audit
 * Scoped strictly to students within the coordinator's department and assigned semesters.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireAnyRole([ROLE_DEPT_COORDINATOR, ROLE_HOD]);

$coordinatorId = getUserId();
$fullName = getFullName();
$db = getDB();

// Session feedback alerts
$successMsg = Session::flash('success') ?: '';
$errorMsg   = Session::flash('error') ?: '';

// 1. Get coordinator's department and institution details
$stmt = $db->prepare("SELECT department, institution FROM dept_coordinators WHERE id = ?");
$stmt->execute([$coordinatorId]);
$coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
$department = $coordinator['department'] ?? 'General';
$institution = $coordinator['institution'] ?? getInstitution();

// 2. Fetch all student USNs belonging strictly to this coordinator's department
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$studentModel = new StudentProfile();
$semester_filter = getCoordinatorSemesterFilters($department) ?: [1, 8];
$discipline_filters = getCoordinatorDisciplineFilters($department);

$coordFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semester_filter
];

$students = $studentModel->getAllWithUsers($coordFilters);
$deptStudentMap = [];
$deptStudentUsns = [];
foreach ($students as $s) {
    $usn = trim((string)($s['usn'] ?? ''));
    if (!empty($usn)) {
        $deptStudentMap[$usn] = trim((string)($s['name'] ?? $usn));
        $deptStudentUsns[] = $usn;
    }
}

$deptStudentUsns = array_values(array_unique(array_filter($deptStudentUsns)));
if (empty($deptStudentUsns)) {
    $deptStudentUsns = ['__NONE__'];
}

$usnPlaceholders = implode(',', array_fill(0, count($deptStudentUsns), '?'));

// 3. Handle POST actions (Single Status Update, Batch Update, Auto-Reconcile, CSV Export)
if (isPost()) {
    $action = post('action');
    $csrfToken = post('csrf_token');

    if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        Session::flash('error', 'Security token mismatch. Please try again.');
        redirect('proctoring.php');
        exit;
    }

    if ($action === 'update_review_status') {
        $eventId = (int)post('event_id');
        $newStatus = trim((string)post('review_status'));

        if ($eventId > 0 && in_array($newStatus, ['pending', 'verified_violation', 'dismissed'], true)) {
            try {
                // Securely scope update to department students only
                $stmt = $db->prepare("UPDATE assessment_integrity_events SET review_status = ? WHERE id = ? AND student_id IN ($usnPlaceholders)");
                $stmt->execute(array_merge([$newStatus, $eventId], $deptStudentUsns));
                Session::flash('success', "Event #{$eventId} review status updated to '" . str_replace('_', ' ', $newStatus) . "'.");
            } catch (Exception $e) {
                Session::flash('error', 'Failed to update review status: ' . $e->getMessage());
            }
        }
        redirect('proctoring.php?' . http_build_query($_GET));
        exit;
    }

    if ($action === 'export_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="dept_proctoring_events_' . preg_replace('/[^a-zA-Z0-9]/', '_', $department) . '_' . date('Y-m-d_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Event ID', 'Student USN', 'Student Name', 'Assessment Type', 'Event Type', 'Severity', 'Confidence (%)', 'Duration (s)', 'Review Status', 'Created At', 'Metadata / Reason']);

        $stmt = $db->prepare("SELECT id, student_id, assessment_type, event_type, severity, confidence, duration, review_status, created_at, metadata FROM assessment_integrity_events WHERE student_id IN ($usnPlaceholders) ORDER BY id DESC LIMIT 5000");
        $stmt->execute($deptStudentUsns);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $metaReason = '';
            if (!empty($row['metadata'])) {
                $metaArr = json_decode($row['metadata'], true);
                $metaReason = $metaArr['reason'] ?? $metaArr['note'] ?? $row['metadata'];
            }
            $stName = $deptStudentMap[$row['student_id']] ?? $row['student_id'];
            fputcsv($output, [
                $row['id'],
                $row['student_id'],
                $stName,
                $row['assessment_type'],
                $row['event_type'],
                $row['severity'],
                round((float)$row['confidence'] * 100, 1) . '%',
                $row['duration'] !== null ? $row['duration'] . 's' : 'N/A',
                $row['review_status'],
                $row['created_at'],
                $metaReason
            ]);
        }
        fclose($output);
        exit;
    }
}

// 4. Query Parameters & Filters
$page            = max(1, (int)($_GET['page'] ?? 1));
$limit           = 50;
$offset          = ($page - 1) * $limit;

$searchQuery     = trim($_GET['q'] ?? '');
$assessmentType  = trim($_GET['assessment_type'] ?? '');
$eventType       = trim($_GET['event_type'] ?? '');
$severityFilter  = trim($_GET['severity'] ?? '');
$reviewFilter    = trim($_GET['review_status'] ?? '');

// 5. Build Aggregated Department Violation KPI Statistics
$stats = [
    'total_events'        => 0,
    'critical_violations' => 0,
    'pending_reviews'     => 0,
    'verified_violations' => 0,
    'has_snapshots'       => 0,
];

try {
    $stmtStat = $db->prepare("SELECT COUNT(*) FROM assessment_integrity_events WHERE student_id IN ($usnPlaceholders)");
    $stmtStat->execute($deptStudentUsns);
    $stats['total_events'] = (int)$stmtStat->fetchColumn();

    $stmtStat = $db->prepare("SELECT COUNT(*) FROM assessment_integrity_events WHERE severity = 'HIGH' AND student_id IN ($usnPlaceholders)");
    $stmtStat->execute($deptStudentUsns);
    $stats['critical_violations'] = (int)$stmtStat->fetchColumn();

    $stmtStat = $db->prepare("SELECT COUNT(*) FROM assessment_integrity_events WHERE review_status = 'pending' AND student_id IN ($usnPlaceholders)");
    $stmtStat->execute($deptStudentUsns);
    $stats['pending_reviews'] = (int)$stmtStat->fetchColumn();

    $stmtStat = $db->prepare("SELECT COUNT(*) FROM assessment_integrity_events WHERE review_status = 'verified_violation' AND student_id IN ($usnPlaceholders)");
    $stmtStat->execute($deptStudentUsns);
    $stats['verified_violations'] = (int)$stmtStat->fetchColumn();

    $stmtStat = $db->prepare("SELECT COUNT(*) FROM assessment_integrity_events WHERE snapshot_path IS NOT NULL AND snapshot_path != '' AND student_id IN ($usnPlaceholders)");
    $stmtStat->execute($deptStudentUsns);
    $stats['has_snapshots'] = (int)$stmtStat->fetchColumn();
} catch (Exception $e) {}

// Event type formatting helper
function formatEventType(string $type): array {
    $map = [
        'FULLSCREEN_EXIT'              => ['label' => 'Fullscreen Exit',              'color' => '#dc2626', 'bg' => '#fef2f2', 'icon' => 'fa-expand'],
        'TAB_SWITCH'                   => ['label' => 'Tab / App Switch',            'color' => '#dc2626', 'bg' => '#fef2f2', 'icon' => 'fa-arrow-right-arrow-left'],
        'WINDOW_BLUR'                  => ['label' => 'Window Focus Lost',            'color' => '#dc2626', 'bg' => '#fef2f2', 'icon' => 'fa-window-restore'],
        'MULTI_FACE'                   => ['label' => 'Multiple Persons',             'color' => '#b91c1c', 'bg' => '#fee2e2', 'icon' => 'fa-users'],
        'MULTIPLE_PERSONS_PRESENT'     => ['label' => 'Multiple Persons',             'color' => '#b91c1c', 'bg' => '#fee2e2', 'icon' => 'fa-users'],
        'NO_FACE'                      => ['label' => 'No Face Detected',             'color' => '#ea580c', 'bg' => '#fff7ed', 'icon' => 'fa-user-slash'],
        'UNATTENDED_STATION'           => ['label' => 'Unattended Station',           'color' => '#ea580c', 'bg' => '#fff7ed', 'icon' => 'fa-user-slash'],
        'PROHIBITED_DEVICE_DETECTED'   => ['label' => 'Phone / Prohibited Device',    'color' => '#7c2d12', 'bg' => '#ffedd5', 'icon' => 'fa-mobile-screen'],
        'OBJECT_CANDIDATE'             => ['label' => 'Suspicious Foreign Object',    'color' => '#d97706', 'bg' => '#fffbeb', 'icon' => 'fa-cube'],
        'LOOKING_AWAY'                 => ['label' => 'Gaze Deviation',               'color' => '#d97706', 'bg' => '#fffbeb', 'icon' => 'fa-eye'],
        'EYE_GAZE_SAMPLE'              => ['label' => 'Gaze Deviation Sample',        'color' => '#d97706', 'bg' => '#fffbeb', 'icon' => 'fa-eye'],
        'SUSTAINED_DOWNWARD_ATTENTION' => ['label' => 'Sustained Downward Attention', 'color' => '#d97706', 'bg' => '#fffbeb', 'icon' => 'fa-arrow-down'],
        'SUSTAINED_SIDEWAYS_DEVIATION' => ['label' => 'Sustained Sideways Deviation', 'color' => '#d97706', 'bg' => '#fffbeb', 'icon' => 'fa-arrow-right'],
        'DEVTOOLS_OPEN'                => ['label' => 'Developer Tools Opened',       'color' => '#991b1b', 'bg' => '#fee2e2', 'icon' => 'fa-code'],
        'SCREEN_SHARE_STOPPED'         => ['label' => 'Screen Share Interrupted',     'color' => '#dc2626', 'bg' => '#fef2f2', 'icon' => 'fa-desktop'],
    ];

    if (isset($map[$type])) {
        return $map[$type];
    }
    return [
        'label' => ucwords(strtolower(str_replace('_', ' ', $type))),
        'color' => '#475569',
        'bg'    => '#f1f5f9',
        'icon'  => 'fa-circle-info'
    ];
}

// 6. Data Querying with Department Restrictions - Strictly Integrity Violations
$events = [];
$totalRows = 0;
$totalPages = 1;

$where = ["e.student_id IN ($usnPlaceholders)"];
$params = $deptStudentUsns;

if (!empty($searchQuery)) {
    $where[] = "(e.student_id LIKE ? OR e.session_token LIKE ? OR e.metadata LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}
if (!empty($assessmentType)) {
    $where[] = "e.assessment_type = ?";
    $params[] = $assessmentType;
}
if (!empty($eventType)) {
    $where[] = "e.event_type = ?";
    $params[] = $eventType;
}
if (!empty($severityFilter)) {
    $where[] = "e.severity = ?";
    $params[] = $severityFilter;
}
if (!empty($reviewFilter)) {
    $where[] = "e.review_status = ?";
    $params[] = $reviewFilter;
}

$whereSql = implode(' AND ', $where);

try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM assessment_integrity_events e WHERE $whereSql");
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $limit));

    $sql = "SELECT e.* FROM assessment_integrity_events e WHERE $whereSql ORDER BY e.id DESC LIMIT $limit OFFSET $offset";
    $dataStmt = $db->prepare($sql);
    $dataStmt->execute($params);
    $events = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $events = [];
}

// Distinct options for filters
$assessmentTypes = ['aptitude', 'technical', 'hr', 'general', 'mock_ai_interview', 'project_viva', 'skill_quiz'];
$eventTypes = [
    'FULLSCREEN_EXIT', 'TAB_SWITCH', 'WINDOW_BLUR', 'MULTI_FACE', 'NO_FACE', 
    'PROHIBITED_DEVICE_DETECTED', 'OBJECT_CANDIDATE', 'LOOKING_AWAY', 'DEVTOOLS_OPEN', 'SCREEN_SHARE_STOPPED'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Proctoring & Integrity Audit - Department Coordinator</title>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #800000;
            --primary-dark: #5c0000;
            --primary-light: #fef2f2;
            --accent: #D4AF37;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-dark);
            margin: 0;
            padding-top: 75px;
        }

        .main-content {
            padding: 30px 40px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-title i {
            font-size: 32px;
            color: var(--primary);
            background: var(--primary-light);
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #fecaca;
        }

        .header-title h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .header-title p {
            margin: 4px 0 0 0;
            color: var(--text-muted);
            font-size: 13.5px;
        }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .kpi-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .kpi-icon {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .kpi-info h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .kpi-info span {
            font-size: 12.5px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Tabs */
        .tab-nav {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 10px 20px;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* Table Card */
        .content-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .filter-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 13.5px;
            background: #fff;
            box-sizing: border-box;
        }

        .btn-action {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
        }

        .btn-primary { background: var(--primary); color: #fff; }
        .btn-secondary { background: #f1f5f9; color: var(--text-dark); border: 1px solid var(--border-color); }
        .btn-snapshot { background: #0284c7; color: #fff; }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .modern-table th {
            background: #f8fafc;
            padding: 12px 14px;
            text-align: left;
            font-weight: 700;
            color: #475569;
            border-bottom: 1px solid var(--border-color);
        }

        .modern-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
        }

        /* Snapshot Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(6px);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #000;
            border: 1px solid #334155;
            border-radius: 16px;
            max-width: 900px;
            width: 100%;
            overflow: hidden;
            color: #fff;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #1e293b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
            text-align: center;
            background: #090d16;
        }

        .modal-body img {
            max-width: 100%;
            max-height: 520px;
            border-radius: 8px;
            border: 1px solid #334155;
        }

        .modal-footer {
            padding: 12px 20px;
            background: #0f172a;
            border-top: 1px solid #1e293b;
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }

        .alert-toast {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-title">
            <i class="fas fa-shield-halved"></i>
            <div>
                <h1>Department AI Proctoring & Integrity Audit</h1>
                <p>Auditing AI telemetry, violation evidence, and strike records for <strong><?php echo htmlspecialchars($department); ?></strong> students.</p>
            </div>
        </div>
        <div class="header-actions">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="export_csv">
                <button type="submit" class="btn-action btn-secondary">
                    <i class="fas fa-download"></i> Export Department CSV
                </button>
            </form>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($successMsg): ?>
        <div class="alert-toast alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
        </div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert-toast alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>

    <!-- KPI Summary Cards - Violations Only -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef2f2; color:#dc2626;">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo number_format($stats['total_events']); ?></h3>
                <span>Total Violation Events</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fee2e2; color:#991b1b;">
                <i class="fas fa-circle-exclamation"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo number_format($stats['critical_violations']); ?></h3>
                <span>Critical / High Severity</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fff7ed; color:#ea580c;">
                <i class="fas fa-bell"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo number_format($stats['pending_reviews']); ?></h3>
                <span>Pending Coordinator Review</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#f0fdf4; color:#16a34a;">
                <i class="fas fa-shield-check"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo number_format($stats['verified_violations']); ?></h3>
                <span>Verified Strike Events</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#f0f9ff; color:#0284c7;">
                <i class="fas fa-camera"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo number_format($stats['has_snapshots']); ?></h3>
                <span>Evidence Vault Snapshots</span>
            </div>
        </div>
    </div>

    <div class="content-card">
        <!-- Filter Form -->
        <form method="GET" style="margin-bottom: 20px;">
            <div class="filter-bar">
                <div class="form-group">
                    <input type="text" name="q" class="form-control" placeholder="Search USN or Session Token..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
                <div class="form-group">
                    <select name="assessment_type" class="form-control">
                        <option value="">All Assessment Types</option>
                        <?php foreach ($assessmentTypes as $at): ?>
                            <option value="<?php echo htmlspecialchars($at); ?>" <?php echo $assessmentType === $at ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $at))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <select name="event_type" class="form-control">
                        <option value="">All Violation Types</option>
                        <?php foreach ($eventTypes as $et): ?>
                            <option value="<?php echo htmlspecialchars($et); ?>" <?php echo $eventType === $et ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(formatEventType($et)['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <select name="severity" class="form-control">
                        <option value="">All Severities</option>
                        <option value="HIGH" <?php echo $severityFilter === 'HIGH' ? 'selected' : ''; ?>>HIGH (Critical)</option>
                        <option value="MEDIUM" <?php echo $severityFilter === 'MEDIUM' ? 'selected' : ''; ?>>MEDIUM (Warning)</option>
                        <option value="LOW" <?php echo $severityFilter === 'LOW' ? 'selected' : ''; ?>>LOW (Info)</option>
                    </select>
                </div>
                <div class="form-group">
                    <select name="review_status" class="form-control">
                        <option value="">All Review Statuses</option>
                        <option value="pending" <?php echo $reviewFilter === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="verified_violation" <?php echo $reviewFilter === 'verified_violation' ? 'selected' : ''; ?>>Verified Violation</option>
                        <option value="dismissed" <?php echo $reviewFilter === 'dismissed' ? 'selected' : ''; ?>>Dismissed / False Positive</option>
                    </select>
                </div>
                <div class="form-group" style="display:flex; gap:8px;">
                    <button type="submit" class="btn-action btn-primary" style="flex:1;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="proctoring.php" class="btn-action btn-secondary">
                        <i class="fas fa-rotate-left"></i> Reset
                    </a>
                </div>
            </div>
        </form>

        <!-- Department Violation Events Table -->
        <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 13px; color: var(--text-muted);">
                Showing <?php echo number_format(count($events)); ?> of <?php echo number_format($totalRows); ?> department violation events
            </span>
        </div>

        <div style="overflow-x: auto;">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Student</th>
                        <th>Assessment</th>
                        <th>Violation Type</th>
                        <th>Severity</th>
                        <th>AI Confidence</th>
                        <th>Review Status</th>
                        <th>Evidence & Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding: 40px; color: #94a3b8;">
                                <i class="fas fa-shield-check" style="font-size: 32px; margin-bottom: 10px; display:block; color:#10b981;"></i>
                                No integrity violation events found for <?php echo htmlspecialchars($department); ?> students.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $e): 
                            $evInfo = formatEventType($e['event_type']);
                            $studentName = $deptStudentMap[$e['student_id']] ?? $e['student_id'];
                            $hasSnapshot = !empty($e['snapshot_path']);
                            $metaReason = '';
                            if (!empty($e['metadata'])) {
                                $meta = json_decode($e['metadata'], true);
                                $metaReason = $meta['reason'] ?? ($meta['trigger'] ?? '');
                            }
                        ?>
                        <tr>
                            <td style="font-family: 'JetBrains Mono', monospace; font-weight:700;">#<?php echo $e['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($studentName); ?></strong>
                                <div style="font-family: 'JetBrains Mono', monospace; font-size:11px; color:#64748b;"><?php echo htmlspecialchars($e['student_id']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $e['assessment_type']))); ?></td>
                            <td>
                                <span class="tag-pill" style="background:<?php echo $evInfo['bg']; ?>; color:<?php echo $evInfo['color']; ?>;">
                                    <i class="fas <?php echo $evInfo['icon']; ?>"></i> <?php echo htmlspecialchars($evInfo['label']); ?>
                                </span>
                                <?php if ($metaReason): ?>
                                    <div style="font-size:11px; color:#94a3b8; margin-top:2px;"><?php echo htmlspecialchars(substr($metaReason, 0, 45)); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight:700; color:<?php echo ((float)$e['confidence'] >= 0.85) ? '#dc2626' : '#d97706'; ?>;">
                                    <?php echo round((float)$e['confidence'] * 100, 1); ?>%
                                </span>
                            </td>
                            <td>
                                <?php if ($e['review_status'] === 'verified_violation'): ?>
                                    <span class="tag-pill" style="background:#f0fdf4; color:#16a34a;"><i class="fas fa-check"></i> Verified Violation</span>
                                <?php elseif ($e['review_status'] === 'dismissed'): ?>
                                    <span class="tag-pill" style="background:#f8fafc; color:#64748b;"><i class="fas fa-ban"></i> Dismissed</span>
                                <?php else: ?>
                                    <span class="tag-pill" style="background:#fffbeb; color:#d97706;"><i class="fas fa-clock"></i> Pending Review</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <?php if ($hasSnapshot): ?>
                                        <button type="button" class="btn-action btn-snapshot" 
                                                onclick="openSnapshotModal(<?php echo $e['id']; ?>, '<?php echo htmlspecialchars(addslashes($studentName)); ?>', '<?php echo htmlspecialchars(addslashes($evInfo['label'])); ?>', '<?php echo htmlspecialchars(addslashes($metaReason)); ?>', '<?php echo round((float)$e['confidence'] * 100, 1); ?>%', '<?php echo date('d M Y, H:i:s', strtotime($e['created_at'])); ?>', '<?php echo htmlspecialchars($e['sha256'] ?? ''); ?>', '<?php echo $e['file_size'] ? round($e['file_size']/1024, 1) . ' KB' : 'N/A'; ?>')">
                                            <i class="fas fa-image"></i> Evidence
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 13px; color: var(--text-muted);">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </span>
                <div style="display: flex; gap: 6px;">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn-action btn-secondary">
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn-action btn-secondary">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Snapshot Evidence Modal -->
<div id="snapshotModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <span id="modalEventLabel" style="font-weight:800; color:var(--accent);">Violation Snapshot</span>
                <span id="modalEventId" style="font-family:'JetBrains Mono', monospace; font-size:11px; color:#94a3b8; margin-left:8px;"></span>
            </div>
            <button type="button" onclick="closeSnapshotModal()" style="background:none; border:none; color:#fff; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <img id="modalEvidenceImg" src="" alt="Proctoring Evidence Snapshot">
        </div>
        <div class="modal-footer">
            <div>
                Candidate: <strong id="modalStudentName" style="color:#fff;"></strong> | 
                Reason: <span id="modalReason"></span>
            </div>
            <div>
                Confidence: <strong id="modalConfidence" style="color:#ef4444;"></strong> | 
                Captured: <span id="modalTimestamp"></span>
            </div>
        </div>
    </div>
</div>

<script>
function openSnapshotModal(eventId, studentName, eventLabel, reason, confidence, timestamp, sha256, fileSize) {
    document.getElementById('modalEventId').textContent = '#' + eventId;
    document.getElementById('modalStudentName').textContent = studentName;
    document.getElementById('modalEventLabel').textContent = eventLabel;
    document.getElementById('modalReason').textContent = reason || 'N/A';
    document.getElementById('modalConfidence').textContent = confidence;
    document.getElementById('modalTimestamp').textContent = timestamp || 'N/A';
    
    const imgEl = document.getElementById('modalEvidenceImg');
    imgEl.src = '<?php echo APP_URL; ?>/officer/proctor_evidence.php?event_id=' + eventId + '&t=' + Date.now();
    
    document.getElementById('snapshotModal').classList.add('active');
}

function closeSnapshotModal() {
    document.getElementById('snapshotModal').classList.remove('active');
    document.getElementById('modalEvidenceImg').src = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSnapshotModal();
});
document.getElementById('snapshotModal').addEventListener('click', function(e) {
    if (e.target === this) closeSnapshotModal();
});
</script>

</body>
</html>
