<?php
$currentPage = basename($_SERVER['PHP_SELF']);
include_once __DIR__ . '/../../includes/demo_protection.php';
?>
<style>
    :root {
        --navbar-height: 70px;
        --vc-primary: #800000;
        --vc-gold: #D4AF37;
    }
    
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: var(--navbar-height);
        background: linear-gradient(90deg, var(--vc-primary) 0%, #5b1f1f 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 40px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 1000;
    }

    .nav-brand {
        font-size: 20px;
        font-weight: 800;
        color: var(--vc-gold);
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .nav-brand span.icon { font-size: 24px; }

    .nav-items {
        display: flex;
        gap: 10px;
        list-style: none;
        margin: 0;
        padding: 0;
        height: 100%;
        align-items: center;
    }

    .nav-link {
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        padding: 10px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .nav-link:hover {
        background: rgba(255,255,255,0.08);
        color: white;
    }

    .nav-link.active {
        background: var(--vc-gold);
        color: var(--vc-primary);
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
    }

    .nav-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
    }

    .user-avatar {
        width: 32px; height: 32px;
        background: var(--vc-gold);
        color: var(--vc-primary);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 14px;
    }

    .logout-btn {
        color: #ff9a9a;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
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

    body {
        padding-top: var(--navbar-height);
    }
</style>

<nav class="navbar">
    <div class="nav-brand">
        <span class="icon">🏛️</span> LAKSHYA 
    </div>

    <ul class="nav-items">
        <li>
            <a href="index.php" class="nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                <span>📊</span> Overview
            </a>
        </li>
        <li>
            <a href="placements.php" class="nav-link <?php echo $currentPage === 'placements.php' ? 'active' : ''; ?>">
                <span>💼</span> Placements
            </a>
        </li>
        <li>
            <a href="internships.php" class="nav-link <?php echo $currentPage === 'internships.php' ? 'active' : ''; ?>">
                <span>🎓</span> Internships
            </a>
        </li>
        <li>
            <a href="coordinators.php" class="nav-link <?php echo $currentPage === 'coordinators.php' ? 'active' : ''; ?>">
                <span>🤝</span> Coordinators
            </a>
        </li>
        <li>
            <a href="student_analysis.php" class="nav-link <?php echo $currentPage === 'student_analysis.php' ? 'active' : ''; ?>">
                <span>👨‍🎓</span> Student Analysis
            </a>
        </li>
    </ul>

    <div class="nav-right">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($GLOBALS['fullName'] ?? 'V', 0, 1)); ?></div>
            <span style="font-size:13px; font-weight:600;"><?php echo htmlspecialchars($GLOBALS['fullName'] ?? 'Vice Chancellor'); ?></span>
        </div>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
