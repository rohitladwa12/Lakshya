<?php
/**
 * Executive Light-Theme Internship Dashboard for Officers
 * Clean, structured, and modern administrative interface.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole('internship_officer');

$userId = getUserId();
$fullName = getFullName() ?: 'Internship Officer';
$db = getDB();

// --- 1. Global KPI Metrics (Instant Cacheable Aggregates) ---
$totalCount = (int)$db->query("SELECT COUNT(*) FROM internships WHERE created_by IS NOT NULL")->fetchColumn();
$activeCount = (int)$db->query("SELECT COUNT(*) FROM internships WHERE status = 'Active' AND (application_deadline IS NULL OR application_deadline >= NOW()) AND created_by IS NOT NULL")->fetchColumn();
$totalApps = (int)$db->query("SELECT COUNT(*) FROM internship_applications ia JOIN internships i ON ia.internship_id = i.id WHERE i.created_by IS NOT NULL")->fetchColumn();
$shortlistedCount = (int)$db->query("SELECT COUNT(*) FROM internship_applications ia JOIN internships i ON ia.internship_id = i.id WHERE i.created_by IS NOT NULL AND ia.status IN ('Shortlisted', 'Interview')")->fetchColumn();
$selectedCount = (int)$db->query("SELECT COUNT(*) FROM internship_applications ia JOIN internships i ON ia.internship_id = i.id WHERE i.created_by IS NOT NULL AND ia.status = 'Selected'")->fetchColumn();

// Conversion Rate Calculation
$conversionRate = ($totalApps > 0) ? round(($selectedCount / $totalApps) * 100, 1) : 0;

// Mode Distribution Stats
$modeStmt = $db->query("SELECT mode, COUNT(*) as cnt FROM internships WHERE created_by IS NOT NULL GROUP BY mode");
$modesCount = $modeStmt ? $modeStmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

// --- 2. Filters & Search Handling ---
$search = trim($_GET['q'] ?? '');
$statusFilter = strtolower($_GET['status'] ?? 'all');
$modeFilter = $_GET['mode'] ?? 'all';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? min(50, max(5, (int)$_GET['per_page'])) : 10;

$where = ["i.created_by IS NOT NULL"];
$params = [];

if ($search !== '') {
    $where[] = "(i.internship_title LIKE ? OR i.company_name LIKE ? OR i.location LIKE ? OR i.stipend LIKE ? OR i.targeted_students LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($statusFilter === 'active') {
    $where[] = "i.status = 'Active' AND (i.application_deadline IS NULL OR i.application_deadline >= NOW())";
} elseif ($statusFilter === 'ended') {
    $where[] = "(i.status != 'Active' OR (i.application_deadline IS NOT NULL AND i.application_deadline < NOW()))";
} elseif ($statusFilter === 'draft') {
    $where[] = "i.status = 'Draft'";
}

if ($modeFilter !== 'all' && in_array($modeFilter, ['On-Site', 'Remote', 'Hybrid', 'Virtual', 'Online'])) {
    $where[] = "i.mode = ?";
    $params[] = $modeFilter;
}

$whereClause = implode(' AND ', $where);

// Count Total Matching Records
$countSql = "SELECT COUNT(*) FROM internships i WHERE {$whereClause}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Fetch Filtered & Paginated Records with Funnel Details
$sql = "SELECT i.*, 
               (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id) as application_count,
               (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id AND ia.status IN ('Shortlisted', 'Interview')) as shortlisted_count,
               (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id AND ia.status = 'Selected') as selected_count
        FROM internships i
        WHERE {$whereClause}
        ORDER BY i.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$internships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Normalize Display Status
foreach ($internships as &$item) {
    if ($item['status'] === 'Active' && !empty($item['application_deadline']) && strtotime($item['application_deadline']) < time()) {
        $item['status'] = 'Ended';
    }
}
unset($item);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Management Console - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <!-- Premium Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-maroon-dark: #580000;
            --primary-maroon-light: #fef2f2;
            --primary-maroon-subtle: rgba(128, 0, 0, 0.06);
            --navy-950: #0b0f19;
            --navy-900: #0f172a;
            --navy-800: #1e293b;
            --slate-600: #475569;
            --slate-500: #64748b;
            --slate-400: #94a3b8;
            --slate-300: #cbd5e1;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;
            --slate-50: #f8fafc;
            --white: #ffffff;
            --emerald-600: #059669;
            --emerald-500: #10b981;
            --emerald-50: #ecfdf5;
            --emerald-200: #a7f3d0;
            --amber-600: #d97706;
            --amber-500: #f59e0b;
            --amber-50: #fffbeb;
            --amber-200: #fde68a;
            --blue-600: #2563eb;
            --blue-50: #eff6ff;
            --purple-600: #7c3aed;
            --purple-50: #faf5ff;
            --rose-600: #e11d48;
            --rose-50: #fff1f2;
            --shadow-card: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
            --shadow-hover: 0 12px 28px -4px rgba(15, 23, 42, 0.09), 0 4px 10px -2px rgba(15, 23, 42, 0.04);
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background-color: var(--slate-50);
            color: var(--navy-900);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .dashboard-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 2.25rem 2rem 4rem 2rem;
        }

        /* --- Header Section --- */
        .executive-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .header-title-block h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            color: var(--navy-900);
            letter-spacing: -0.03em;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title-block h1 .badge-session {
            font-family: 'Inter', sans-serif;
            font-size: 0.75rem;
            font-weight: 700;
            background: var(--primary-maroon-light);
            color: var(--primary-maroon);
            border: 1px solid rgba(128, 0, 0, 0.15);
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .header-title-block p {
            color: var(--slate-500);
            font-size: 0.95rem;
            margin-top: 4px;
            font-weight: 500;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-primary-action {
            background: linear-gradient(135deg, var(--primary-maroon), var(--primary-maroon-dark));
            color: var(--white);
            padding: 0.75rem 1.4rem;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.92rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(128, 0, 0, 0.25);
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .btn-primary-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.35);
            color: var(--white);
        }

        .btn-secondary-action {
            background: var(--white);
            color: var(--navy-800);
            padding: 0.75rem 1.3rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.92rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            transition: var(--transition);
        }

        .btn-secondary-action:hover {
            background: var(--slate-50);
            border-color: var(--slate-300);
            color: var(--primary-maroon);
            transform: translateY(-2px);
        }

        .btn-secondary-action .counter-badge {
            background: var(--primary-maroon-light);
            color: var(--primary-maroon);
            font-weight: 800;
            font-size: 0.75rem;
            padding: 2px 7px;
            border-radius: 12px;
        }

        /* --- KPI Grid (5-Card Metrics Architecture) --- */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            padding: 1.25rem 1.25rem 1.15rem 1.25rem;
            box-shadow: var(--shadow-card);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            border-color: var(--slate-300);
        }

        .metric-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .metric-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--slate-500);
        }

        .metric-icon-box {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .icon-maroon { background: var(--primary-maroon-light); color: var(--primary-maroon); }
        .icon-emerald { background: var(--emerald-50); color: var(--emerald-600); }
        .icon-blue { background: var(--blue-50); color: var(--blue-600); }
        .icon-amber { background: var(--amber-50); color: var(--amber-600); }
        .icon-purple { background: var(--purple-50); color: var(--purple-600); }

        .metric-value-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .metric-value {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            color: var(--navy-900);
            line-height: 1;
        }

        .metric-meta {
            font-size: 0.75rem;
            color: var(--slate-400);
            font-weight: 500;
            margin-top: 6px;
        }

        /* --- Quick Analytics Sub-bar --- */
        .analytics-subbar {
            background: var(--white);
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-md);
            padding: 0.85rem 1.25rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            box-shadow: var(--shadow-card);
        }

        .subbar-left {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }

        .subbar-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.84rem;
            color: var(--slate-600);
        }

        .subbar-pill {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .pill-onsite { background: var(--blue-50); color: var(--blue-600); border: 1px solid #bfdbfe; }
        .pill-remote { background: var(--purple-50); color: var(--purple-600); border: 1px solid #ddd6fe; }
        .pill-hybrid { background: var(--amber-50); color: var(--amber-600); border: 1px solid #fde68a; }

        /* --- Main Content Panel --- */
        .panel-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .panel-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--slate-200);
            background: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .panel-title-group h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--navy-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-title-group p {
            font-size: 0.85rem;
            color: var(--slate-500);
            margin-top: 2px;
        }

        /* --- Filters & Search Toolbar --- */
        .filter-toolbar {
            padding: 1.25rem 2rem;
            background: var(--slate-50);
            border-bottom: 1px solid var(--slate-200);
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .search-box-wrapper {
            position: relative;
            flex: 1;
            min-width: 280px;
            max-width: 480px;
        }

        .search-box-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--slate-400);
            font-size: 0.95rem;
        }

        .search-input {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 2.4rem;
            background: var(--white);
            border: 1px solid var(--slate-300);
            border-radius: var(--radius-md);
            font-family: 'Inter', sans-serif;
            font-size: 0.88rem;
            color: var(--navy-900);
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.08);
        }

        .filter-controls-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .select-filter {
            padding: 0.62rem 2rem 0.62rem 0.9rem;
            background: var(--white) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") no-repeat right 0.75rem center/10px 10px;
            border: 1px solid var(--slate-300);
            border-radius: var(--radius-md);
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--navy-800);
            appearance: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .select-filter:focus {
            outline: none;
            border-color: var(--primary-maroon);
        }

        .status-pill-tabs {
            display: flex;
            background: var(--slate-200);
            padding: 3px;
            border-radius: 10px;
            gap: 2px;
        }

        .tab-btn {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--slate-600);
            text-decoration: none;
            transition: var(--transition);
        }

        .tab-btn.active {
            background: var(--white);
            color: var(--primary-maroon);
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
        }

        /* --- High-Density Table Layout --- */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .opportunity-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .opportunity-table th {
            background: var(--slate-50);
            padding: 0.9rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--slate-500);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid var(--slate-200);
            white-space: nowrap;
        }

        .opportunity-table td {
            padding: 1.15rem 1.5rem;
            border-bottom: 1px solid var(--slate-100);
            vertical-align: middle;
            background: var(--white);
            transition: background 0.15s ease;
        }

        .opportunity-table tr:hover td {
            background: #fafcff;
        }

        /* Opportunity Row Components */
        .company-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .company-badge-logo {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--slate-200);
            background: var(--slate-50);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1rem;
            color: var(--primary-maroon);
            flex-shrink: 0;
            overflow: hidden;
        }

        .company-badge-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4px;
        }

        .company-meta-name {
            font-weight: 700;
            font-size: 0.96rem;
            color: var(--navy-900);
        }

        .company-meta-loc {
            font-size: 0.8rem;
            color: var(--slate-500);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
        }

        .role-title {
            font-weight: 700;
            font-size: 0.94rem;
            color: var(--navy-900);
            margin-bottom: 3px;
        }

        .role-tags-row {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .pill-badge {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .pill-stipend { background: var(--emerald-50); color: var(--emerald-600); border: 1px solid var(--emerald-200); }
        .pill-duration { background: var(--slate-100); color: var(--slate-600); border: 1px solid var(--slate-200); }
        .pill-target { background: #fdf2f8; color: #be185d; border: 1px solid #fbcfe8; }

        /* Status Pills */
        .status-indicator-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge-status-active {
            background: var(--emerald-50);
            color: var(--emerald-600);
            border: 1px solid var(--emerald-200);
        }

        .badge-status-active .pulse-dot {
            width: 7px;
            height: 7px;
            background: var(--emerald-500);
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
            animation: pulseDot 2s infinite;
        }

        @keyframes pulseDot {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.5; }
        }

        .badge-status-ended {
            background: var(--slate-100);
            color: var(--slate-500);
            border: 1px solid var(--slate-200);
        }

        .badge-status-draft {
            background: var(--amber-50);
            color: var(--amber-600);
            border: 1px solid var(--amber-200);
        }

        /* Funnel Progress Pill */
        .funnel-metric-box {
            display: inline-flex;
            flex-direction: column;
            gap: 3px;
        }

        .funnel-count-main {
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--navy-900);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .funnel-substats {
            font-size: 0.74rem;
            color: var(--slate-500);
            display: flex;
            gap: 8px;
        }

        .funnel-substats span.shortlisted { color: var(--blue-600); font-weight: 600; }
        .funnel-substats span.selected { color: var(--emerald-600); font-weight: 700; }

        /* Deadline Display */
        .deadline-cell {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .deadline-date {
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--navy-900);
        }

        .deadline-countdown {
            font-size: 0.74rem;
            font-weight: 600;
        }

        .countdown-safe { color: var(--emerald-600); }
        .countdown-warning { color: var(--amber-600); font-weight: 700; }
        .countdown-expired { color: var(--slate-400); }

        /* Action Buttons Cluster */
        .actions-cluster {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .btn-action-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 0.88rem;
            border: 1px solid var(--slate-200);
            background: var(--white);
            color: var(--slate-500);
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-action-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
        }

        .btn-action-view:hover { color: var(--blue-600); border-color: #bfdbfe; background: var(--blue-50); }
        .btn-action-edit:hover { color: var(--amber-600); border-color: #fde68a; background: var(--amber-50); }
        .btn-action-wa:hover { color: #16a34a; border-color: #bbf7d0; background: #f0fdf4; }
        .btn-action-copy:hover { color: var(--purple-600); border-color: #ddd6fe; background: var(--purple-50); }
        .btn-action-delete:hover { color: var(--rose-600); border-color: #fecdd3; background: var(--rose-50); }

        /* --- Pagination Controls --- */
        .pagination-container {
            padding: 1.25rem 2rem;
            background: var(--white);
            border-top: 1px solid var(--slate-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .pagination-info {
            font-size: 0.85rem;
            color: var(--slate-500);
        }

        .pagination-info strong {
            color: var(--navy-900);
        }

        .pagination-nav {
            display: flex;
            list-style: none;
            gap: 4px;
        }

        .pagination-link {
            padding: 6px 12px;
            min-width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--slate-600);
            text-decoration: none;
            border: 1px solid var(--slate-200);
            background: var(--white);
            transition: var(--transition);
        }

        .pagination-link:hover {
            border-color: var(--slate-300);
            background: var(--slate-50);
            color: var(--navy-900);
        }

        .pagination-link.active {
            background: var(--primary-maroon);
            color: var(--white);
            border-color: var(--primary-maroon);
            box-shadow: 0 2px 8px rgba(128, 0, 0, 0.2);
        }

        .pagination-link.disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        /* --- Empty State --- */
        .empty-dataset-card {
            text-align: center;
            padding: 5rem 2rem;
        }

        .empty-illustration {
            width: 72px;
            height: 72px;
            background: var(--slate-100);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--slate-400);
            font-size: 2rem;
            margin-bottom: 1.25rem;
        }

        .empty-dataset-card h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--navy-900);
            margin-bottom: 6px;
        }

        .empty-dataset-card p {
            color: var(--slate-500);
            font-size: 0.9rem;
            max-width: 420px;
            margin: 0 auto 1.5rem auto;
        }

        /* Toast notification */
        #toastContainer {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
        }

        .toast-msg {
            background: var(--navy-900);
            color: var(--white);
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: toastSlideIn 0.3s ease-out;
        }

        @keyframes toastSlideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 1280px) {
            .metrics-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 900px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
            .dashboard-container { padding: 1.5rem 1rem; }
        }

        @media (max-width: 600px) {
            .metrics-grid { grid-template-columns: 1fr; }
            .executive-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .btn-primary-action, .btn-secondary-action { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        
        <!-- 1. Executive Top Header -->
        <div class="executive-header">
            <div class="header-title-block">
                <h1>
                    Internship Dashboard
                    <span class="badge-session">Academic Drive 2025-26</span>
                </h1>
                <p>Real-time corporate recruitment pipeline, applicant telemetry, and placement conversions.</p>
            </div>
            
            <div class="header-actions">
                <a href="internship_placed.php" class="btn-secondary-action" title="View all verified placed students">
                    <i class="fas fa-user-graduate" style="color: var(--primary-maroon);"></i>
                    <span>Placed Registry</span>
                    <span class="counter-badge"><?php echo number_format($selectedCount); ?></span>
                </a>
                
                <a href="add_internship.php" class="btn-primary-action">
                    <i class="fas fa-plus-circle"></i>
                    <span>Post New Internship</span>
                </a>
            </div>
        </div>

        <!-- 2. Five-Card Performance KPI Grid -->
        <div class="metrics-grid">
            
            <div class="metric-card">
                <div class="metric-card-header">
                    <span class="metric-label">Total Postings</span>
                    <div class="metric-icon-box icon-maroon"><i class="fas fa-briefcase"></i></div>
                </div>
                <div>
                    <div class="metric-value-row">
                        <span class="metric-value"><?php echo number_format($totalCount); ?></span>
                    </div>
                    <div class="metric-meta">Combined corporate drives</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-card-header">
                    <span class="metric-label">Active Drives</span>
                    <div class="metric-icon-box icon-emerald"><i class="fas fa-bolt-lightning"></i></div>
                </div>
                <div>
                    <div class="metric-value-row">
                        <span class="metric-value"><?php echo number_format($activeCount); ?></span>
                    </div>
                    <div class="metric-meta">Accepting student applications</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-card-header">
                    <span class="metric-label">Total Applications</span>
                    <div class="metric-icon-box icon-blue"><i class="fas fa-users-viewfinder"></i></div>
                </div>
                <div>
                    <div class="metric-value-row">
                        <span class="metric-value"><?php echo number_format($totalApps); ?></span>
                    </div>
                    <div class="metric-meta">Across all departments</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-card-header">
                    <span class="metric-label">In Review / Shortlist</span>
                    <div class="metric-icon-box icon-amber"><i class="fas fa-filter-circle-dollar"></i></div>
                </div>
                <div>
                    <div class="metric-value-row">
                        <span class="metric-value"><?php echo number_format($shortlistedCount); ?></span>
                    </div>
                    <div class="metric-meta">Advancing in selection rounds</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-card-header">
                    <span class="metric-label">Offers Placed</span>
                    <div class="metric-icon-box icon-purple"><i class="fas fa-award"></i></div>
                </div>
                <div>
                    <div class="metric-value-row">
                        <span class="metric-value"><?php echo number_format($selectedCount); ?></span>
                    </div>
                    <div class="metric-meta">Conversion rate: <strong><?php echo $conversionRate; ?>%</strong></div>
                </div>
            </div>

        </div>

        <!-- 3. Quick Analytics Sub-bar -->
        <div class="analytics-subbar">
            <div class="subbar-left">
                <div class="subbar-item">
                    <i class="fas fa-layer-group" style="color: var(--slate-400);"></i>
                    <span>Engagement Modes:</span>
                </div>
                <span class="subbar-pill pill-onsite"><i class="fas fa-building"></i> On-Site (<?php echo $modesCount['On-Site'] ?? 0; ?>)</span>
                <span class="subbar-pill pill-remote"><i class="fas fa-house-laptop"></i> Remote (<?php echo $modesCount['Remote'] ?? 0; ?>)</span>
                <span class="subbar-pill pill-hybrid"><i class="fas fa-shuffle"></i> Hybrid (<?php echo $modesCount['Hybrid'] ?? 0; ?>)</span>
            </div>
            <div class="subbar-right" style="font-size: 0.82rem; color: var(--slate-500); font-weight: 500;">
                <i class="fas fa-clock-rotate-left" style="margin-right: 4px;"></i> Real-time sync with candidate applicant telemetry
            </div>
        </div>

        <!-- 4. Opportunities Panel -->
        <div class="panel-card">
            
            <div class="panel-header">
                <div class="panel-title-group">
                    <h2>
                        <i class="fas fa-table-list" style="color: var(--primary-maroon);"></i>
                        Internship Opportunities
                    </h2>
                    <p>Showing <?php echo count($internships); ?> of <?php echo number_format($totalRecords); ?> matched corporate listings</p>
                </div>

                <div class="status-pill-tabs">
                    <a href="?status=all<?php echo $search ? '&q=' . urlencode($search) : ''; ?><?php echo $modeFilter !== 'all' ? '&mode=' . urlencode($modeFilter) : ''; ?>" class="tab-btn <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All (<?php echo $totalCount; ?>)</a>
                    <a href="?status=active<?php echo $search ? '&q=' . urlencode($search) : ''; ?><?php echo $modeFilter !== 'all' ? '&mode=' . urlencode($modeFilter) : ''; ?>" class="tab-btn <?php echo $statusFilter === 'active' ? 'active' : ''; ?>">Active (<?php echo $activeCount; ?>)</a>
                    <a href="?status=ended<?php echo $search ? '&q=' . urlencode($search) : ''; ?><?php echo $modeFilter !== 'all' ? '&mode=' . urlencode($modeFilter) : ''; ?>" class="tab-btn <?php echo $statusFilter === 'ended' ? 'active' : ''; ?>">Ended / Closed</a>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <form method="GET" action="dashboard.php" class="filter-toolbar">
                <?php if ($statusFilter !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                <?php endif; ?>

                <div class="search-box-wrapper">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="search-input" placeholder="Search by role, company, location, stipend..." oninput="debounceSearch(this.form)">
                </div>

                <div class="filter-controls-group">
                    <select name="mode" class="select-filter" onchange="this.form.submit()">
                        <option value="all" <?php echo $modeFilter === 'all' ? 'selected' : ''; ?>>All Work Modes</option>
                        <option value="On-Site" <?php echo $modeFilter === 'On-Site' ? 'selected' : ''; ?>>On-Site Only</option>
                        <option value="Remote" <?php echo $modeFilter === 'Remote' ? 'selected' : ''; ?>>Remote Only</option>
                        <option value="Hybrid" <?php echo $modeFilter === 'Hybrid' ? 'selected' : ''; ?>>Hybrid Only</option>
                    </select>

                    <select name="per_page" class="select-filter" onchange="this.form.submit()">
                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10 / Page</option>
                        <option value="20" <?php echo $perPage == 20 ? 'selected' : ''; ?>>20 / Page</option>
                        <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50 / Page</option>
                    </select>

                    <?php if ($search !== '' || $statusFilter !== 'all' || $modeFilter !== 'all'): ?>
                        <a href="dashboard.php" class="btn-action-icon" title="Reset all active filters" style="width: auto; padding: 0 12px; gap: 6px; font-weight: 600; font-size: 0.8rem;">
                            <i class="fas fa-rotate-left"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Table View -->
            <div class="table-responsive">
                <?php if (empty($internships)): ?>
                    <div class="empty-dataset-card">
                        <div class="empty-illustration">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h3>No Internship Postings Found</h3>
                        <p>No listings matched your active filter criteria. Try adjusting your search keyword or clearing the filters.</p>
                        <a href="add_internship.php" class="btn-primary-action">
                            <i class="fas fa-plus"></i> Post An Internship
                        </a>
                    </div>
                <?php else: ?>
                    <table class="opportunity-table">
                        <thead>
                            <tr>
                                <th>Company & Location</th>
                                <th>Opportunity & Stipend</th>
                                <th>Target Cohort</th>
                                <th>Applications Funnel</th>
                                <th>Application Deadline</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: right;">Quick Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($internships as $i): 
                                $shareUrl = APP_URL . '/student/internship_details.php?code=' . encryptInternshipId($i['id']);
                                // WhatsApp formatted deadline
                                $deadlineRaw = $i['application_deadline'] ?? null;
                                $deadlineTime = !empty($deadlineRaw) ? strtotime($deadlineRaw) : null;
                                $formattedDlWa = 'Open / Ongoing';
                                if ($deadlineTime) {
                                    $formattedDlWa = (date('H:i', $deadlineTime) !== '00:00') ? date('M d, Y \a\t h:i A', $deadlineTime) : date('M d, Y', $deadlineTime);
                                }

                                $waMessage = "*📢 New Internship Opportunity!*\n\n"
                                           . "*Company:* " . $i['company_name'] . "\n"
                                           . "*Role:* " . $i['internship_title'] . "\n"
                                           . "*Location:* " . $i['location'] . "\n"
                                           . "*Stipend:* " . $i['stipend'] . "\n"
                                           . "*Mode:* " . $i['mode'] . "\n"
                                           . "*Duration:* " . $i['duration'] . "\n"
                                           . "*Deadline:* " . $formattedDlWa . "\n\n"
                                           . "*Apply on Lakshya:* " . $shareUrl;
                                $waUrl = "https://api.whatsapp.com/send?text=" . urlencode($waMessage);

                                // Deadline Math
                                $daysLeft = $deadlineTime ? (int)ceil(($deadlineTime - time()) / 86400) : null;
                            ?>
                                <tr>
                                    <!-- 1. Company Column -->
                                    <td>
                                        <div class="company-cell">
                                            <div class="company-badge-logo">
                                                <?php if (!empty($i['company_logo'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($i['company_logo']); ?>" alt="Logo">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($i['company_name'], 0, 2)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="company-meta-name"><?php echo htmlspecialchars($i['company_name']); ?></div>
                                                <div class="company-meta-loc">
                                                    <i class="fas fa-location-dot" style="font-size: 0.75rem; color: var(--slate-400);"></i>
                                                    <span><?php echo htmlspecialchars($i['location'] ?: 'Unspecified'); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 2. Opportunity Column -->
                                    <td>
                                        <div class="role-title"><?php echo htmlspecialchars($i['internship_title']); ?></div>
                                        <div class="role-tags-row">
                                            <span class="pill-badge pill-stipend">
                                                <i class="fas fa-indian-rupee-sign"></i> <?php echo htmlspecialchars($i['stipend'] ?: 'Competitive'); ?>
                                            </span>
                                            <span class="pill-badge pill-duration">
                                                <i class="far fa-clock"></i> <?php echo htmlspecialchars($i['duration'] ?: 'Standard'); ?>
                                            </span>
                                            <span class="pill-badge <?php echo $i['mode'] === 'Remote' ? 'pill-remote' : ($i['mode'] === 'Hybrid' ? 'pill-hybrid' : 'pill-onsite'); ?>">
                                                <i class="fas <?php echo $i['mode'] === 'Remote' ? 'fa-house-laptop' : ($i['mode'] === 'Hybrid' ? 'fa-shuffle' : 'fa-building'); ?>"></i>
                                                <?php echo htmlspecialchars($i['mode']); ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- 3. Target Cohort -->
                                    <td>
                                        <?php if (!empty($i['targeted_students'])): ?>
                                            <span class="pill-badge pill-target" title="Eligible Target Cohort">
                                                <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($i['targeted_students']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size: 0.78rem; color: var(--slate-400);">Open for all courses</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 4. Funnel Metrics -->
                                    <td>
                                        <div class="funnel-metric-box">
                                            <div class="funnel-count-main">
                                                <i class="fas fa-user-group" style="color: var(--primary-maroon); font-size: 0.85rem;"></i>
                                                <span><?php echo number_format($i['application_count']); ?> Applied</span>
                                            </div>
                                            <div class="funnel-substats">
                                                <span class="shortlisted"><?php echo $i['shortlisted_count']; ?> Shortlisted</span>
                                                <span>•</span>
                                                <span class="selected"><?php echo $i['selected_count']; ?> Offers</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 5. Deadline -->
                                    <td>
                                        <div class="deadline-cell">
                                            <div class="deadline-date">
                                                <?php 
                                                    if ($deadlineTime) {
                                                        echo date('M d, Y', $deadlineTime);
                                                        if (date('H:i', $deadlineTime) !== '00:00') {
                                                            echo '<span style="display:block; font-size: 0.74rem; color: var(--slate-500); font-weight: 600; margin-top: 1px;"><i class="far fa-clock" style="font-size:0.7rem;"></i> ' . date('h:i A', $deadlineTime) . '</span>';
                                                        }
                                                    } else {
                                                        echo 'Open / Ongoing';
                                                    }
                                                ?>
                                            </div>
                                            <?php if ($deadlineTime): ?>
                                                <?php if ($daysLeft < 0): ?>
                                                    <span class="deadline-countdown countdown-expired">Closed</span>
                                                <?php elseif ($daysLeft <= 3): ?>
                                                    <span class="deadline-countdown countdown-warning"><i class="fas fa-fire"></i> <?php echo $daysLeft == 0 ? 'Closes Today' : $daysLeft . 'd remaining'; ?></span>
                                                <?php else: ?>
                                                    <span class="deadline-countdown countdown-safe"><?php echo $daysLeft; ?> days remaining</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- 6. Status -->
                                    <td style="text-align: center;">
                                        <?php if ($i['status'] === 'Active'): ?>
                                            <span class="status-indicator-badge badge-status-active">
                                                <span class="pulse-dot"></span> Active
                                            </span>
                                        <?php elseif ($i['status'] === 'Ended'): ?>
                                            <span class="status-indicator-badge badge-status-ended">
                                                <i class="fas fa-circle-xmark"></i> Ended
                                            </span>
                                        <?php else: ?>
                                            <span class="status-indicator-badge badge-status-draft">
                                                <i class="fas fa-file-lines"></i> <?php echo htmlspecialchars($i['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 7. Actions Toolbar -->
                                    <td style="text-align: right;">
                                        <div class="actions-cluster">
                                            <button type="button" class="btn-action-icon btn-action-copy" title="Copy student application link" onclick="copyLink('<?php echo $shareUrl; ?>')">
                                                <i class="fas fa-link"></i>
                                            </button>

                                            <a href="<?php echo $waUrl; ?>" target="_blank" class="btn-action-icon btn-action-wa" title="Share circular on WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>

                                            <a href="applications.php?id=<?php echo $i['id']; ?>" class="btn-action-icon btn-action-view" title="View Applicants & Selection Pipeline">
                                                <i class="fas fa-users-viewfinder"></i>
                                            </a>

                                            <a href="edit_internship.php?id=<?php echo $i['id']; ?>" class="btn-action-icon btn-action-edit" title="Edit Posting Details">
                                                <i class="fas fa-pen-to-square"></i>
                                            </a>

                                            <button type="button" class="btn-action-icon btn-action-delete" title="Archive / Delete Posting" onclick="confirmDelete(<?php echo $i['id']; ?>, '<?php echo addslashes($i['internship_title']); ?>')">
                                                <i class="fas fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Pagination Footer -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $perPage, $totalRecords); ?></strong> of <strong><?php echo number_format($totalRecords); ?></strong> internships
                    </div>

                    <ul class="pagination-nav">
                        <li>
                            <a href="?page=<?php echo max(1, $page - 1); ?>&status=<?php echo urlencode($statusFilter); ?>&mode=<?php echo urlencode($modeFilter); ?>&q=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>" class="pagination-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" title="Previous Page">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        if ($startPage > 1) {
                            echo '<li><a href="?page=1&status=' . urlencode($statusFilter) . '&mode=' . urlencode($modeFilter) . '&q=' . urlencode($search) . '&per_page=' . $perPage . '" class="pagination-link">1</a></li>';
                            if ($startPage > 2) echo '<li><span class="pagination-link disabled">...</span></li>';
                        }

                        for ($p = $startPage; $p <= $endPage; $p++) {
                            $activeClass = ($p == $page) ? 'active' : '';
                            echo '<li><a href="?page=' . $p . '&status=' . urlencode($statusFilter) . '&mode=' . urlencode($modeFilter) . '&q=' . urlencode($search) . '&per_page=' . $perPage . '" class="pagination-link ' . $activeClass . '">' . $p . '</a></li>';
                        }

                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) echo '<li><span class="pagination-link disabled">...</span></li>';
                            echo '<li><a href="?page=' . $totalPages . '&status=' . urlencode($statusFilter) . '&mode=' . urlencode($modeFilter) . '&q=' . urlencode($search) . '&per_page=' . $perPage . '" class="pagination-link">' . $totalPages . '</a></li>';
                        }
                        ?>

                        <li>
                            <a href="?page=<?php echo min($totalPages, $page + 1); ?>&status=<?php echo urlencode($statusFilter); ?>&mode=<?php echo urlencode($modeFilter); ?>&q=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>" class="pagination-link <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" title="Next Page">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>

        </div>

    </div>

    <!-- Toast Notification Overlay -->
    <div id="toastContainer"></div>

    <script>
        // Copy share link helper
        function copyLink(url) {
            navigator.clipboard.writeText(url).then(() => {
                showToast('Link copied to clipboard!');
            }).catch(() => {
                // Fallback prompt
                prompt('Copy this link:', url);
            });
        }

        // Lightweight Toast notification
        function showToast(message) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast-msg';
            toast.innerHTML = '<i class="fas fa-circle-check" style="color: #10b981;"></i> ' + message;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 2500);
        }

        // Live Debounced Search
        let searchTimeout = null;
        function debounceSearch(form) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                form.submit();
            }, 600);
        }

        // Delete confirmation
        function confirmDelete(id, title) {
            if (confirm('Are you sure you want to delete the internship position:\n\n"' + title + '"\n\nThis will remove the listing and archive candidate records.')) {
                window.location.href = 'delete_internship.php?id=' + id;
            }
        }
    </script>
</body>
</html>
