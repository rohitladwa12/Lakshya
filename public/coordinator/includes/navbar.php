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
        --gold-glow: rgba(233, 198, 111, 0.4);
        --navbar-height: 80px;
        --glass-bg: rgba(128, 0, 0, 0.98);
        --transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding-top: var(--navbar-height);
        font-family: 'Inter', sans-serif;
        background-color: #f8fafc;
        overflow-x: hidden;
    }

    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: var(--navbar-height);
        background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
        backdrop-filter: blur(15px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 50px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        z-index: 2000;
        border-bottom: 2px solid var(--primary-gold);
    }

    .nav-left {
        display: flex;
        align-items: center;
        gap: 50px;
    }

    .nav-brand {
        display: flex;
        align-items: center;
        gap: 15px;
        text-decoration: none;
        color: white;
        transition: var(--transition);
    }

    .nav-brand:hover {
        transform: translateY(-1px);
    }

    .brand-logo {
        width: 42px;
        height: 42px;
        background: var(--primary-gold);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: var(--primary-maroon);
        box-shadow: 0 0 20px var(--gold-glow);
        transform: rotate(-5deg);
        transition: var(--transition);
    }

    .nav-brand:hover .brand-logo {
        transform: rotate(0deg) scale(1.05);
    }

    .brand-text {
        display: flex;
        flex-direction: column;
    }

    .brand-title {
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -0.5px;
        color: var(--primary-gold);
        line-height: 1.1;
    }

    .brand-subtitle {
        font-size: 11px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.7);
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-top: 2px;
    }

    .nav-items {
        display: flex;
        gap: 10px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .nav-link {
        color: rgba(255, 255, 255, 0.75);
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        padding: 10px 20px;
        border-radius: 100px;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 10px;
        position: relative;
    }

    .nav-link i {
        font-size: 16px;
        transition: var(--transition);
    }

    .nav-link::after {
        content: '';
        position: absolute;
        bottom: 5px;
        left: 50%;
        width: 0;
        height: 2px;
        background: var(--primary-gold);
        transition: var(--transition);
        transform: translateX(-50%);
        border-radius: 2px;
    }

    .nav-link:hover {
        color: white;
        background: rgba(255, 255, 255, 0.05);
    }

    .nav-link:hover i {
        transform: translateY(-2px);
        color: var(--primary-gold);
    }

    .nav-link:hover::after {
        width: 20px;
    }

    .nav-link.active {
        background: rgba(233, 198, 111, 0.15);
        color: var(--primary-gold);
        border: 1px solid rgba(233, 198, 111, 0.3);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .nav-link.active i {
        color: var(--primary-gold);
    }

    .nav-link.active::after {
        display: none;
    }

    .nav-right {
        display: flex;
        align-items: center;
        gap: 30px;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 6px 15px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: var(--transition);
    }

    .user-profile:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(233, 198, 111, 0.3);
    }

    .user-info {
        text-align: right;
    }

    .user-name {
        display: block;
        color: white;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 0.2px;
    }

    .user-dept {
        display: block;
        color: var(--primary-gold);
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .avatar-ring {
        width: 38px;
        height: 38px;
        background: linear-gradient(135deg, var(--primary-gold) 0%, #c5a04d 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-maroon);
        font-size: 18px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .logout-btn {
        color: #ff9a9a;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
        padding: 10px 20px;
        background: rgba(255, 77, 77, 0.1);
        border: 1px solid rgba(255, 77, 77, 0.2);
        border-radius: 10px;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .logout-btn:hover {
        background: #ff4d4d;
        color: white;
        border-color: #ff4d4d;
        box-shadow: 0 8px 20px rgba(255, 77, 77, 0.3);
        transform: translateY(-2px);
    }

    .main-content {
        width: 100%;
        max-width: 100% !important;
        margin: 0;
        padding: 40px 50px;
        transition: var(--transition);
    }

    @media (max-width: 1200px) {
        .nav-left { gap: 30px; }
        .navbar { padding: 0 30px; }
    }

    @media (max-width: 1024px) {
        .user-profile { display: none; }
        .nav-items { display: none; } /* Could add a mobile menu here if requested */
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
                    <i class="fas fa-grid-2"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="students_report.php" class="nav-link <?php echo $currentPage == 'students_report.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i> Students & Reports
                </a>
            </li>
            <li>
                <a href="change_password" class="nav-link <?php echo $currentPage == 'change_password.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-keyhole"></i> Security
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

