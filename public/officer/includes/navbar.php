<?php
$currentPage = basename($_SERVER['PHP_SELF']);
include_once __DIR__ . '/../../includes/demo_protection.php';

// Fetch user details from session for profile section
$officerName = $_SESSION['user']['full_name'] ?? 'Officer';
$officerInstitution = $_SESSION['user']['institution'] ?? 'GMU';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    :root {
        --brand: #7C0000;
        --brand-hover: #9E0000;
        --brand-light: #FDF2F2;
        --gold: #B08D2C;
        --sidebar-w: 260px;
        --text-primary: #111827;
        --text-secondary: #4B5563;
        --text-muted: #9CA3AF;
        --border-color: #E5E7EB;
        --surface-bg: #FFFFFF;
        --hover-bg: #F3F4F6;
    }

    body {
        padding-left: var(--sidebar-w) !important;
        padding-top: 0 !important;
        background-color: #F8F9FA;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    #o-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: var(--sidebar-w);
        background: var(--surface-bg);
        z-index: 1000;
        box-shadow: 1px 0 3px rgba(0, 0, 0, 0.04);
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        padding: 24px 16px;
        box-sizing: border-box;
    }

    /* Brand Header */
    .o-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        margin-bottom: 24px;
        padding: 0 4px;
    }

    .o-brand__icon {
        width: 38px;
        height: 38px;
        background: var(--brand);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #FFFFFF;
        box-shadow: 0 2px 6px rgba(124, 0, 0, 0.2);
        flex-shrink: 0;
    }

    .o-brand__text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .o-brand__title {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
        letter-spacing: -0.3px;
    }

    .o-brand__badge {
        font-size: 11px;
        font-weight: 600;
        color: var(--brand);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Profile Card */
    .o-profile-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        background: #F9FAFB;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        margin-bottom: 20px;
    }

    .o-profile-avatar {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: var(--brand-light);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: var(--brand);
        font-weight: 700;
        flex-shrink: 0;
    }

    .o-profile-info {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .o-profile-name {
        color: var(--text-primary);
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
    }

    .o-profile-role {
        color: var(--text-muted);
        font-size: 11px;
        font-weight: 500;
    }

    /* Navigation Links */
    .o-links {
        display: flex;
        flex-direction: column;
        gap: 4px;
        list-style: none;
        margin: 0;
        padding: 0;
        flex: 1;
        overflow-y: auto;
    }

    .o-links li {
        width: 100%;
    }

    .o-links a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 8px;
        font-size: 13.5px;
        font-weight: 500;
        color: var(--text-secondary);
        transition: all 0.15s ease;
        text-decoration: none;
        box-sizing: border-box;
    }

    .o-links a i {
        font-size: 15px;
        width: 18px;
        text-align: center;
        color: #6B7280;
        transition: color 0.15s ease;
    }

    .o-links a:hover {
        color: var(--text-primary);
        background: var(--hover-bg);
    }

    .o-links a:hover i {
        color: var(--text-primary);
    }

    .o-links a.active {
        background: var(--brand-light);
        color: var(--brand);
        font-weight: 600;
        border-left: 3px solid var(--brand);
        padding-left: 9px;
    }

    .o-links a.active i {
        color: var(--brand);
    }

    /* Logout Section */
    .o-logout-container {
        margin-top: auto;
        padding-top: 16px;
        border-top: 1px solid var(--border-color);
        width: 100%;
    }

    .o-logout {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        background: #F9FAFB;
        border: 1px solid var(--border-color);
        transition: all 0.15s ease;
        cursor: pointer;
        text-decoration: none;
        box-sizing: border-box;
        width: 100%;
    }

    .o-logout:hover {
        background: #FEF2F2;
        border-color: #FECACA;
        color: #DC2626;
    }

    .o-logout i {
        font-size: 13px;
    }

    /* Responsive Design (Mobile / Tablet) */
    @media (max-width: 992px) {
        body {
            padding-left: 0 !important;
            padding-top: 64px !important;
        }

        #o-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: auto;
            width: 100%;
            height: 64px;
            flex-direction: row;
            padding: 0 16px;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border-right: none;
            border-bottom: 1px solid var(--border-color);
        }

        .o-brand {
            margin-bottom: 0;
            padding: 0;
        }

        .o-profile-card {
            display: none;
        }

        .o-links {
            flex-direction: row;
            align-items: center;
            gap: 4px;
            overflow-x: auto;
            overflow-y: hidden;
            flex: initial;
        }

        .o-links a {
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 12.5px;
        }

        .o-links a span {
            display: none;
        }

        .o-links a.active {
            border-left: none;
            border-bottom: 2px solid var(--brand);
            padding-left: 10px;
            padding-bottom: 6px;
        }

        .o-logout-container {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
            width: auto;
        }

        .o-logout {
            padding: 8px 12px;
            font-size: 12px;
        }

        .o-logout span {
            display: none;
        }
    }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<nav id="o-sidebar">
    <!-- Brand / System Title -->
    <a href="dashboard.php" class="o-brand">
        <div class="o-brand__icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="o-brand__text">
            <span class="o-brand__title">Lakshya</span>
            <span class="o-brand__badge">Placement Admin</span>
        </div>
    </a>

    <!-- User Profile Details -->
    <div class="o-profile-card">
        <div class="o-profile-avatar">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="o-profile-info">
            <span class="o-profile-name" title="<?php echo htmlspecialchars($officerName); ?>"><?php echo htmlspecialchars($officerName); ?></span>
            <span class="o-profile-role">Placement Officer (<?php echo htmlspecialchars($officerInstitution); ?>)</span>
        </div>
    </div>

    <!-- Navigation List -->
    <ul class="o-links">
        <li>
            <a href="dashboard.php" class="<?php echo ($currentPage === 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="jobs.php" class="<?php echo ($currentPage === 'jobs.php') ? 'active' : ''; ?>">
                <i class="fas fa-briefcase"></i> <span>Jobs & Drives</span>
            </a>
        </li>
        <li>
            <a href="applications.php" class="<?php echo ($currentPage === 'applications.php' || $currentPage === 'job_applicants.php') ? 'active' : ''; ?>">
                <i class="fas fa-file-signature"></i> <span>Applications</span>
            </a>
        </li>
        <li>
            <a href="campus_drives.php" class="<?php echo ($currentPage === 'campus_drives.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> <span>Campus Drives</span>
            </a>
        </li>
        <li>
            <a href="attendance.php" class="<?php echo ($currentPage === 'attendance.php' || $currentPage === 'job_attendance.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i> <span>Attendance</span>
            </a>
        </li>
        <li>
            <a href="upload_placed_students.php" class="<?php echo ($currentPage === 'upload_placed_students.php') ? 'active' : ''; ?>">
                <i class="fas fa-cloud-arrow-up"></i> <span>Upload Placed</span>
            </a>
        </li>
        <li>
            <a href="reports.php" class="<?php echo ($currentPage === 'reports.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> <span>Intelligence</span>
            </a>
        </li>
        <li>
            <a href="feedback.php" class="<?php echo ($currentPage === 'feedback.php') ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> <span>Feedback</span>
            </a>
        </li>
    </ul>

    <!-- Logout -->
    <div class="o-logout-container">
        <a href="../logout.php" class="o-logout" title="Sign out of Lakshya"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>
</nav>

<!-- Global Security Layer -->
<script>
    window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ""; ?>';
</script>
<script src="<?php echo APP_URL; ?>/js/security_interceptor.js?v=<?php echo time(); ?>"></script>