<?php
/**
 * Premium Shared Navbar for Internship Officer
 */
$current_page = basename($_SERVER['PHP_SELF']);
$fullName = $_SESSION['full_name'] ?? 'Officer';
include_once __DIR__ . '/../includes/demo_protection.php';
?>
<style>
    :root {
        --primary: #800000;
        --primary-dark: #600000;
        --primary-light: #ffecec;
        --nav-height: 70px;
        --white: #ffffff;
        --text-main: #1e293b;
        --text-muted: #64748b;
    }

    .main-nav {
        background: var(--white);
        height: var(--nav-height);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 2rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
        font-family: 'Inter', sans-serif;
    }

    .nav-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 800;
        font-size: 1.25rem;
        color: var(--primary);
        text-decoration: none;
    }

    .nav-brand i {
        font-size: 1.5rem;
    }

    .nav-links {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .nav-item {
        color: var(--text-muted);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 0.6rem 1rem;
        border-radius: 8px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .nav-item:hover {
        background: var(--primary-light);
        color: var(--primary);
    }

    .nav-item.active {
        background: var(--primary-light);
        color: var(--primary);
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding-left: 1.5rem;
        margin-left: 1.5rem;
        border-left: 1px solid #e2e8f0;
    }

    .avatar {
        width: 38px;
        height: 38px;
        background: var(--primary);
        color: white;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        box-shadow: 0 4px 6px -1px rgba(128, 0, 0, 0.2);
    }

    .user-info {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--text-main);
        line-height: 1.2;
    }

    .user-role {
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .logout-btn {
        color: #ef4444;
        font-weight: 600;
        font-size: 0.85rem;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        transition: background 0.2s;
    }

    .logout-btn:hover {
        background: #fef2f2;
    }

    @media (max-width: 768px) {
        .user-info { display: none; }
        .nav-item span { display: none; }
    }
</style>

<nav class="main-nav">
    <a href="dashboard.php" class="nav-brand">
        <i class="fas fa-shield-halved"></i>
        <span>Internship Portal</span>
    </a>

    <div class="nav-links">
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        <a href="add_internship.php" class="nav-item <?php echo $current_page == 'add_internship.php' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Add Internship</span>
        </a>
    </div>

    <div style="display: flex; align-items: center;">
        <div class="user-profile">
            <div class="avatar"><?php echo strtoupper(substr($fullName, 0, 2)); ?></div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                <span class="user-role">Internship Officer</span>
            </div>
            <a href="../logout.php" class="logout-btn" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</nav>
