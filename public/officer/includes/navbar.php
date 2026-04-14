<?php
$currentPage = basename($_SERVER['PHP_SELF']);
include_once __DIR__ . '/../../includes/demo_protection.php';
?>
<style>
    :root {
        --navbar-height: 70px;
    }

    * {
        box-sizing: border-box;
    }

    body {
        padding-top: var(--navbar-height);
        overflow-x: hidden;
    }
    
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: var(--navbar-height);
        background: linear-gradient(90deg, var(--primary-maroon) 0%, #5b1f1f 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 40px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
    }

    .nav-brand {
        font-size: 20px;
        font-weight: bold;
        color: var(--primary-gold);
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .nav-items {
        display: flex;
        gap: 20px;
        list-style: none;
        margin: 0;
        padding: 0;
        height: 100%;
        align-items: center;
    }

    .nav-link {
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        font-size: 14px;
        padding: 8px 16px;
        border-radius: 6px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nav-link:hover, .nav-link.active {
        background: rgba(255,255,255,0.1);
        color: var(--primary-gold);
    }

    .nav-link.active {
        background: var(--primary-gold);
        color: var(--primary-maroon);
        font-weight: bold;
    }

    .nav-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .logout-btn {
        color: #ff9a9a;
        text-decoration: none;
        font-size: 14px;
        padding: 8px 16px;
        border: 1px solid rgba(255, 154, 154, 0.3);
        border-radius: 6px;
        transition: all 0.3s ease;
    }

    .logout-btn:hover {
        background: rgba(255, 154, 154, 0.1);
        color: #ff4d4d;
        border-color: #ff4d4d;
    }

    /* Adjust main content for navbar */
    body {
        padding-top: var(--navbar-height);
    }
    
    .main-content {
        width: 100%;
        max-width: 100% !important;
        margin: 0;
        padding: 30px 40px;
        transition: all 0.3s ease;
        overflow-x: hidden;
    }

    @media (max-width: 1024px) {
        .navbar { padding: 0 20px; }
        .main-content { padding: 20px; }
    }
</style>

<nav class="navbar">
    <div class="nav-brand">
        <span>🎓</span> PLACEX OFFICER
    </div>

    <ul class="nav-items">
        <li>
            <a href="dashboard" class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                <i>📊</i> Dashboard
            </a>
        </li>
        <li>
            <a href="reports" class="nav-link <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>">
                <i>📋</i> Students & Reports
            </a>
        </li>
        <li>
            <a href="jobs" class="nav-link <?php echo $currentPage == 'jobs.php' ? 'active' : ''; ?>">
                <i>💼</i> Jobs
            </a>
        </li>
        <li>
            <a href="mock_ai_reports" class="nav-link <?php echo $currentPage == 'mock_ai_reports.php' ? 'active' : ''; ?>">
                <i>🤖</i> Mock AI
            </a>
        </li>
        <li>
            <a href="nqt_analytics" class="nav-link <?php echo $currentPage == 'nqt_analytics.php' ? 'active' : ''; ?>">
                <i>🚀</i> NQT Analytics
            </a>
        </li>
    </ul>

    <div class="nav-right">
        <a href="../logout" class="logout-btn">Logout</a>
    </div>
</nav>
