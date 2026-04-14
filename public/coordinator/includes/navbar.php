<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$fullName = (string)getFullName();
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
        --primary-gold: #e9c66f;
        --navbar-height: 72px;
        --glass-bg: rgba(128, 0, 0, 0.95);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding-top: var(--navbar-height);
        font-family: 'Inter', sans-serif;
        overflow-x: hidden; /* Prevent horizontal scroll at body level */
    }

    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: var(--navbar-height);
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 40px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        z-index: 2000;
        border-bottom: 1px solid rgba(233, 198, 111, 0.2);
    }

    .nav-left {
        display: flex;
        align-items: center;
        gap: 30px;
    }

    .nav-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: white;
    }

    .brand-logo {
        width: 35px;
        height: 35px;
        background: var(--primary-gold);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: var(--primary-maroon);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .brand-text {
        display: flex;
        flex-direction: column;
    }

    .brand-title {
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 0.5px;
        color: var(--primary-gold);
    }

    .brand-subtitle {
        font-size: 11px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.7);
        text-transform: uppercase;
    }

    .nav-items {
        display: flex;
        gap: 8px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .nav-link {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        padding: 10px 18px;
        border-radius: 8px;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nav-link i {
        font-size: 16px;
        opacity: 0.8;
    }

    .nav-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: var(--primary-gold);
    }

    .nav-link.active {
        background: var(--primary-gold);
        color: var(--primary-maroon);
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(233, 198, 111, 0.2);
    }

    .nav-right {
        display: flex;
        align-items: center;
        gap: 25px;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-right: 20px;
        border-right: 1px solid rgba(255, 255, 255, 0.1);
    }

    .user-info {
        text-align: right;
    }

    .user-name {
        display: block;
        color: white;
        font-size: 14px;
        font-weight: 600;
    }

    .user-dept {
        display: block;
        color: var(--primary-gold);
        font-size: 11px;
        font-weight: 500;
    }

    .logout-btn {
        color: #ff9a9a;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        padding: 8px 16px;
        border: 1px solid rgba(255, 154, 154, 0.3);
        border-radius: 8px;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .logout-btn:hover {
        background: #ff4d4d;
        color: white;
        border-color: #ff4d4d;
        box-shadow: 0 4px 12px rgba(255, 77, 77, 0.2);
    }

    .main-content {
        width: 100%;
        max-width: 100% !important;
        margin: 0;
        padding: 30px 40px;
        transition: var(--transition);
        overflow-x: hidden; /* Ensure content doesn't push viewport */
    }

    @media (max-width: 1024px) {
        .navbar { padding: 0 20px; }
        .user-profile { display: none; }
        .main-content { padding: 20px; }
    }
</style>

<nav class="navbar">
    <div class="nav-left">
        <a href="dashboard.php" class="nav-brand">
            <div class="brand-logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="brand-text">
                <span class="brand-title">Coordinator Hub</span>
                <span class="brand-subtitle"><?php echo htmlspecialchars($department ?: 'Department'); ?></span>
            </div>
        </a>

        <ul class="nav-items">
            <li>
                <a href="dashboard.php" class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="students_report.php" class="nav-link <?php echo $currentPage == 'students_report.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users-viewfinder"></i> Students & Reports
                </a>
            </li>
            <li>
                <a href="change_password" class="nav-link <?php echo $currentPage == 'change_password.php' ? 'active' : ''; ?>">
                    <i class="fas fa-key"></i> Passwords
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
            <div style="width: 35px; height: 35px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

