<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-logo">PLACEX OFFICER</div>
    <ul class="nav-menu" style="list-style:none; padding: 0;">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                <i>📊</i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="jobs.php" class="nav-link <?php echo $currentPage == 'jobs.php' ? 'active' : ''; ?>">
                <i>💼</i> Job Postings
            </a>
        </li>
        <li class="nav-item">
            <a href="applications.php" class="nav-link <?php echo $currentPage == 'applications.php' ? 'active' : ''; ?>">
                <i>📝</i> Applications
            </a>
        </li>
        <li class="nav-item">
            <a href="interviews.php" class="nav-link <?php echo $currentPage == 'interviews.php' ? 'active' : ''; ?>">
                <i>🗓️</i> Interviews
            </a>
        </li>
        <li class="nav-item">
            <a href="reports.php" class="nav-link <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>">
                <i>📈</i> Reports
            </a>
        </li>
    </ul>
    <a href="../logout.php" class="logout-btn" style="margin-top:auto; padding: 12px; text-align: center; color: #ff9a9a; text-decoration: none; background: rgba(255,255,255,0.1); border-radius: 8px;">Logout</a>
</div>
