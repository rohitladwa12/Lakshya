<?php
$currentPage = basename($_SERVER['PHP_SELF']);
include_once __DIR__ . '/../../includes/demo_protection.php';

// Fetch user details from session for profile section
$officerName = $_SESSION['user']['full_name'] ?? 'Officer';
$officerInstitution = $_SESSION['user']['institution'] ?? 'GMU';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

    :root {
        --brand: #7C0000;
        --brand-dark: #4A0000;
        --gold: #C9972C;
        --sidebar-w: 260px;
        --ease-out: cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    body {
        padding-left: var(--sidebar-w) !important;
        padding-top: 0 !important;
    }

    #o-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: var(--sidebar-w);
        background: linear-gradient(to bottom, var(--brand-dark), var(--brand));
        z-index: 1000;
        box-shadow: 4px 0 24px rgba(0, 0, 0, 0.15);
        font-family: 'Outfit', sans-serif;
        border-right: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        flex-direction: column;
        padding: 30px 20px;
        box-sizing: border-box;
    }

    .o-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 20px;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.5px;
        text-transform: uppercase;
        margin-bottom: 30px;
    }

    .o-brand__icon {
        width: 38px;
        height: 38px;
        background: linear-gradient(135deg, var(--gold), #f3d283);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: var(--brand);
        box-shadow: 0 4px 10px rgba(201, 151, 44, 0.3);
    }

    /* Profile Card */
    .o-profile-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 14px;
        margin-bottom: 25px;
    }

    .o-profile-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .o-profile-info {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .o-profile-name {
        color: #fff;
        font-size: 13px;
        font-weight: 700;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
    }

    .o-profile-role {
        color: rgba(255, 255, 255, 0.55);
        font-size: 11px;
        font-weight: 500;
    }

    /* Navigation Links */
    .o-links {
        display: flex;
        flex-direction: column;
        gap: 8px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .o-links li {
        width: 100%;
    }

    .o-links a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.65);
        transition: all 0.3s var(--ease-out);
        text-decoration: none;
        box-sizing: border-box;
    }

    .o-links a i {
        font-size: 16px;
        opacity: 0.8;
        width: 20px;
        text-align: center;
    }

    .o-links a:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.06);
        transform: translateX(4px);
    }

    .o-links a.active {
        background: #fff;
        color: var(--brand);
        font-weight: 700;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .o-links a.active i {
        opacity: 1;
        color: var(--brand);
    }

    /* Logout Container */
    .o-logout-container {
        margin-top: auto;
        width: 100%;
    }

    .o-logout {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 12px 20px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 700;
        color: #fff;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        box-sizing: border-box;
        width: 100%;
    }

    .o-logout:hover {
        background: #ef4444;
        border-color: #ef4444;
        box-shadow: 0 8px 24px rgba(239, 68, 68, 0.35);
        transform: translateY(-2px);
    }

    /* Responsive Design (Mobile / Tablet) */
    @media (max-width: 992px) {
        body {
            padding-left: 0 !important;
            padding-top: 70px !important;
        }

        #o-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: auto;
            width: 100%;
            height: 70px;
            flex-direction: row;
            padding: 0 20px;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border-right: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .o-brand {
            margin-bottom: 0;
        }

        .o-profile-card {
            display: none;
        }

        .o-links {
            flex-direction: row;
            align-items: center;
            gap: 6px;
        }

        .o-links a {
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 13px;
        }

        .o-links a span {
            display: none;
        }

        .o-logout-container {
            margin-top: 0;
            width: auto;
        }

        .o-logout {
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
        }
    }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<nav id="o-sidebar">
    <div class="o-brand">
        <div class="o-brand__icon"><i class="fas fa-graduation-cap"></i></div>
        <span>Lakshya <span style="color: var(--gold); font-weight: 400; font-size: 14px; opacity: 0.8; margin-left: 2px;">Hub</span></span>
    </div>

    <!-- User Profile Details -->
    <div class="o-profile-card">
        <div class="o-profile-avatar">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="o-profile-info">
            <span class="o-profile-name" title="<?php echo htmlspecialchars($officerName); ?>"><?php echo htmlspecialchars($officerName); ?></span>
            <span class="o-profile-role">Officer (<?php echo htmlspecialchars($officerInstitution); ?>)</span>
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
            <a href="reports.php" class="<?php echo ($currentPage === 'reports.php') ? 'active' : ''; ?>">
                <i class="fas fa-brain"></i> <span>Intelligence</span>
            </a>
        </li>
        <li>
            <a href="jobs.php" class="<?php echo ($currentPage === 'jobs.php' || $currentPage === 'job_applicants.php') ? 'active' : ''; ?>">
                <i class="fas fa-briefcase"></i> <span>Jobs</span>
            </a>
        </li>
        <li>
            <a href="attendance.php" class="<?php echo ($currentPage === 'attendance.php' || $currentPage === 'job_attendance.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i> <span>Attendance</span>
            </a>
        </li>
        <li>
            <a href="campus_drives.php" class="<?php echo ($currentPage === 'campus_drives.php') ? 'active' : ''; ?>">
                <i class="fas fa-laptop-code"></i> <span>Campus Drive</span>
            </a>
        </li>
        <li>
            <a href="upload_placed_students.php" class="<?php echo ($currentPage === 'upload_placed_students.php') ? 'active' : ''; ?>">
                <i class="fas fa-cloud-arrow-up"></i> <span>Upload</span>
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
        <a href="../logout.php" class="o-logout"><i class="fas fa-power-off"></i> Logout</a>
    </div>
</nav>

<!-- Global Security Layer -->
<script>
    window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ""; ?>';
</script>
<script src="<?php echo APP_URL; ?>/js/security_interceptor.js?v=<?php echo time(); ?>"></script>