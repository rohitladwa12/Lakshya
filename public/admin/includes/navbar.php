<?php include_once __DIR__ . '/../../includes/demo_protection.php'; ?>
<style>
    /* Admin Sidebar Layout */
    .admin-sidebar {
        width: 260px;
        background: #fff;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        border-right: 1px solid var(--medium-gray);
        display: flex;
        flex-direction: column;
        z-index: 1000;
        box-shadow: var(--shadow);
    }

    .admin-sidebar .sidebar-header {
        padding: 24px;
        border-bottom: 1px solid var(--medium-gray);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .admin-sidebar .sidebar-header i {
        font-size: 24px;
        color: var(--primary-maroon);
    }

    .admin-sidebar .sidebar-header h2 {
        font-size: 20px;
        font-weight: 800;
        color: var(--primary-dark);
        margin: 0;
    }

    .admin-sidebar .nav-links {
        padding: 20px 0;
        flex: 1;
        overflow-y: auto;
    }

    .admin-sidebar .nav-item {
        display: flex;
        align-items: center;
        padding: 12px 24px;
        color: #555;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
        gap: 12px;
    }

    .admin-sidebar .nav-item:hover {
        background: #f8f9fa;
        color: var(--primary-maroon);
        border-right: 3px solid var(--primary-maroon);
    }

    .admin-sidebar .nav-item.active {
        background: rgba(128, 0, 0, 0.05);
        color: var(--primary-maroon);
        border-right: 3px solid var(--primary-maroon);
        font-weight: 600;
    }

    .admin-sidebar .nav-item i {
        width: 20px;
        text-align: center;
        font-size: 16px;
    }

    .sidebar-footer {
        padding: 20px;
        border-top: 1px solid var(--medium-gray);
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #dc3545;
        text-decoration: none;
        font-weight: 600;
        padding: 12px;
        border-radius: 8px;
        transition: background 0.2s;
    }

    .logout-btn:hover {
        background: #fee2e2;
    }

    /* For main layout offset */
    body {
        padding-left: 260px; /* Offset for the fixed sidebar */
    }
</style>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<nav class="admin-sidebar">
    <div class="sidebar-header">
        <i class="fas fa-crown"></i>
        <h2>Lakshya Admin</h2>
    </div>
    
    <div class="nav-links">
        <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Overview
        </a>
        <a href="users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> User Management
        </a>
        <a href="companies.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'companies.php' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i> Companies
        </a>
        <a href="jobs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'jobs.php' ? 'active' : ''; ?>">
            <i class="fas fa-briefcase"></i> Placements
        </a>
        <a href="learning.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'learning.php' ? 'active' : ''; ?>">
            <i class="fas fa-graduation-cap"></i> Learning Modules
        </a>
        <a href="activity_logs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'activity_logs.php' ? 'active' : ''; ?>">
            <i class="fas fa-list-check"></i> Activity Logs
        </a>
    </div>

    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>
<!-- Global Maintenance Interceptor -->
<script src="<?php echo APP_URL; ?>/public/js/maintenance_interceptor.js"></script>
