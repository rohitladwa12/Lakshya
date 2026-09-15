<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$fullName = (string) getFullName();
$department = getDepartment() ?: 'General';
include_once __DIR__ . '/../../includes/demo_protection.php';
?>
<!-- Fonts and Icons -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary-maroon: #800000;
        --dark-maroon: #5b1f1f;
        --primary-gold: #D4AF37;
        --gold-glow: rgba(212, 175, 55, 0.3);
        --navbar-height: 75px;
        --glass-bg: rgba(255, 255, 255, 0.96);
        --transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding-top: var(--navbar-height);
        font-family: 'Outfit', 'Inter', sans-serif;
        background-color: #f1f5f9;
        overflow-x: hidden;
    }

    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: var(--navbar-height);
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 32px;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05), 0 2px 6px -1px rgba(0, 0, 0, 0.02);
        z-index: 1000;
        border-bottom: 1px solid #e2e8f0;
    }

    .nav-left {
        display: flex;
        align-items: center;
        gap: 28px;
    }

    .nav-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: var(--primary-maroon);
        transition: var(--transition);
    }

    .nav-brand:hover {
        transform: translateY(-1px);
    }

    .brand-logo {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--primary-maroon) 0%, #5c0000 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        color: var(--primary-gold);
        box-shadow: 0 3px 10px rgba(128, 0, 0, 0.25);
        transition: var(--transition);
    }

    .nav-brand:hover .brand-logo {
        transform: scale(1.05);
        box-shadow: 0 5px 15px rgba(128, 0, 0, 0.35);
    }

    .brand-text {
        display: flex;
        flex-direction: column;
    }

    .brand-title {
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -0.5px;
        color: var(--primary-maroon);
        line-height: 1.1;
    }

    .brand-subtitle {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 2px;
    }

    .nav-items {
        display: flex;
        gap: 6px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .nav-link {
        color: #475569;
        text-decoration: none;
        font-size: 13.5px;
        font-weight: 600;
        padding: 8px 16px;
        border-radius: 100px;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
    }

    .nav-link i {
        font-size: 15px;
        color: #64748b;
        transition: var(--transition);
    }

    .nav-link:hover {
        color: var(--primary-maroon);
        background: rgba(128, 0, 0, 0.05);
    }

    .nav-link:hover i {
        transform: translateY(-1px);
        color: var(--primary-maroon);
    }

    .nav-link.active {
        background: rgba(128, 0, 0, 0.08);
        color: var(--primary-maroon);
        border: 1px solid rgba(128, 0, 0, 0.15);
        font-weight: 700;
    }

    .nav-link.active i {
        color: var(--primary-maroon);
    }

    .nav-right {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 6px 14px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1.5px solid #e2e8f0;
        transition: var(--transition);
    }

    .user-profile:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .user-info {
        text-align: right;
    }

    .user-name {
        display: block;
        color: #0f172a;
        font-size: 13.5px;
        font-weight: 700;
        letter-spacing: -0.2px;
    }

    .user-dept {
        display: block;
        color: var(--primary-maroon);
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .avatar-ring {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, var(--primary-maroon) 0%, #5c0000 100%);
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-gold);
        font-size: 16px;
        box-shadow: 0 2px 8px rgba(128, 0, 0, 0.2);
    }

    .logout-btn {
        color: #b91c1c;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
        padding: 8px 16px;
        background: #fee2e2;
        border: 1px solid #fecaca;
        border-radius: 10px;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .logout-btn:hover {
        background: #dc2626;
        color: white;
        border-color: #dc2626;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        transform: translateY(-1px);
    }

    .main-content {
        width: 100%;
        max-width: 100% !important;
        margin: 0;
        padding: 40px 50px;
        transition: var(--transition);
    }

    @media (max-width: 1200px) {
        .nav-left {
            gap: 20px;
        }

        .navbar {
            padding: 0 20px;
        }
    }

    @media (max-width: 1024px) {
        .user-profile {
            display: none;
        }

        .nav-items {
            display: none;
        }
    }
</style>

<nav class="navbar">
    <div class="nav-left">
        <a href="dashboard.php" class="nav-brand">
            <div class="brand-logo">
                <i class="fas fa-id-badge"></i>
            </div>
            <div class="brand-text">
                <span class="brand-title">Coordinator Hub</span>
                <span class="brand-subtitle"><?php echo htmlspecialchars($department ?: 'Central Admin'); ?></span>
            </div>
        </a>

        <ul class="nav-items">
            <li>
                <a href="dashboard.php" class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="students_report.php"
                    class="nav-link <?php echo $currentPage == 'students_report.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i> Students & Reports
                </a>
            </li>
            <li>
                <a href="ai_monitor.php"
                    class="nav-link <?php echo $currentPage == 'ai_monitor.php' ? 'active' : ''; ?>">
                    <i class="fas fa-robot"></i> Student Monitor
                </a>
            </li>
            <li>
                <a href="feedback.php"
                    class="nav-link <?php echo $currentPage == 'feedback.php' ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i> Feedback
                </a>
            </li>

            <li>
                <a href="change_password.php"
                    class="nav-link <?php echo $currentPage == 'change_password.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-halved"></i> Security
                </a>
            </li>
        </ul>
    </div>

    <div class="nav-right">
        <div class="user-profile">
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                <span class="user-dept"><?php echo htmlspecialchars($department); ?></span>
            </div>
            <div class="avatar-ring">
                <i class="fas fa-user-shield"></i>
            </div>
        </div>
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-power-off"></i> Logout
        </a>
    </div>
</nav>

<!-- Global Security Layer -->
<script>
    window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ""; ?>';
</script>
<script src="<?php echo APP_URL; ?>/js/security_interceptor.js?v=<?php echo time(); ?>"></script>