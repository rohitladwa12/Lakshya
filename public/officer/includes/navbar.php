<?php
$currentPage = basename($_SERVER['PHP_SELF']);
include_once __DIR__ . '/../../includes/demo_protection.php';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

    :root {
        --brand: #7C0000;
        --brand-dark: #5A0000;
        --gold: #C9972C;
        --nav-h: 70px;
        --ease-out: cubic-bezier(0.34, 1.56, 0.64, 1);
        --nav-glass: rgba(124, 0, 0, 0.95);
    }

    #o-navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: var(--nav-h);
        background: var(--brand);
        background: linear-gradient(to right, var(--brand-dark), var(--brand));
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 40px;
        z-index: 1000;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        font-family: 'Outfit', sans-serif;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .o-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 18px;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.5px;
        white-space: nowrap;
        text-transform: uppercase;
    }

    .o-brand__icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, var(--gold), #f3d283);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: var(--brand);
        box-shadow: 0 4px 10px rgba(201, 151, 44, 0.3);
    }

    .o-links {
        display: flex;
        align-items: center;
        gap: 8px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .o-links a {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.65);
        transition: all 0.3s var(--ease-out);
        text-decoration: none;
        position: relative;
    }

    .o-links a i {
        font-size: 15px;
        opacity: 0.8;
    }

    .o-links a:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.08);
        transform: translateY(-2px);
    }

    .o-links a.active {
        background: #fff;
        color: var(--brand);
        font-weight: 700;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
    }

    .o-links a.active i {
        opacity: 1;
        color: var(--brand);
    }

    .o-logout {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.15);
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
    }

    .o-logout:hover {
        background: #ef4444;
        border-color: #ef4444;
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        transform: scale(1.05);
    }

    @media (max-width: 1100px) {
        .o-links span {
            display: none;
        }

        .o-links a {
            padding: 12px;
        }

        #o-navbar {
            padding: 0 20px;
        }
    }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<nav id="o-navbar">
    <div class="o-brand">
        <div class="o-brand__icon"><i class="fas fa-graduation-cap"></i></div>
        Lakshya <span
            style="color: var(--gold); margin-left: 4px; font-weight: 400; font-size: 14px; opacity: 0.8;">Hub</span>
    </div>

    <ul class="o-links">
        <li><a href="dashboard.php" class="<?php echo ($currentPage === 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
            </a></li>
        <li><a href="reports.php" class="<?php echo ($currentPage === 'reports.php') ? 'active' : ''; ?>">
                <i class="fas fa-brain"></i> <span>Intelligence</span>
            </a></li>
        <li><a href="jobs.php" class="<?php echo ($currentPage === 'jobs.php') ? 'active' : ''; ?>">
                <i class="fas fa-briefcase"></i> <span>Jobs</span>
            </a></li>
        <li><a href="applications.php" class="<?php echo ($currentPage === 'applications.php') ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i> <span>Applications</span>
            </a></li>
        <li><a href="upload_placed_students.php"
                class="<?php echo ($currentPage === 'upload_placed_students.php') ? 'active' : ''; ?>">
                <i class="fas fa-cloud-arrow-up"></i> <span>Upload</span>
            </a></li>
    </ul>

    <div class="o-nav-right">
        <a href="../logout.php" class="o-logout"><i class="fas fa-power-off"></i> Logout</a>
    </div>
</nav>