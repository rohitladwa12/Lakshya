<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$fullName = getFullName();
$institution = getInstitution();
include_once __DIR__ . '/../../includes/demo_protection.php';
?>
<!-- Fonts and Icons -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary-maroon: #800000;
        --dark-maroon: #5b1f1f;
        --accent-gold: #e9c66f;
        --nav-height: 72px;
        --glass-bg: rgba(255, 255, 255, 0.98);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        margin: 0;
        padding-top: var(--nav-height);
        font-family: 'Outfit', sans-serif;
    }

    .unified-navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: var(--nav-height);
        background: var(--glass-bg);
        backdrop-filter: blur(15px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 40px;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
        z-index: 2000;
        border-bottom: 2px solid rgba(128, 0, 0, 0.05);
    }

    .nav-left {
        display: flex;
        align-items: center;
        gap: 35px;
    }

    .nav-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: var(--primary-maroon);
    }

    .logo-container {
        width: 38px;
        height: 38px;
        background: var(--primary-maroon);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: white;
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
    }

    .logo-text {
        display: flex;
        flex-direction: column;
    }

    .logo-main {
        font-size: 20px;
        font-weight: 800;
        letter-spacing: -0.5px;
        line-height: 1;
    }

    .logo-tag {
        font-size: 10px;
        font-weight: 600;
        color: var(--accent-gold);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .nav-menu {
        display: flex;
        gap: 4px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .nav-item {
        position: relative;
    }

    .nav-btn {
        color: #4a5568;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        padding: 10px 16px;
        border-radius: 10px;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        border: none;
        background: transparent;
    }

    .nav-btn i {
        font-size: 16px;
        color: #a0aec0;
        transition: var(--transition);
    }

    .nav-btn:hover {
        background: rgba(128, 0, 0, 0.04);
        color: var(--primary-maroon);
    }

    .nav-btn:hover i {
        color: var(--primary-maroon);
        transform: translateY(-2px);
    }

    .nav-btn.active {
        background: rgba(128, 0, 0, 0.08);
        color: var(--primary-maroon);
    }

    .nav-btn.active i {
        color: var(--primary-maroon);
    }

    /* Dropdown Logic */
    .dropdown-container {
        position: relative;
    }

    .dropdown-menu {
        position: absolute;
        top: 100%;
        left: 0;
        width: 240px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        padding: 12px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(15px);
        transition: var(--transition);
        display: flex;
        flex-direction: column;
        gap: 4px;
        border: 1px solid rgba(0,0,0,0.05);
    }

    .dropdown-container:hover .dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(10px);
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border-radius: 10px;
        text-decoration: none;
        color: #4a5568;
        font-size: 14px;
        font-weight: 500;
        transition: var(--transition);
    }

    .dropdown-item i {
        width: 24px;
        height: 24px;
        background: #f7fafc;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: #718096;
        transition: var(--transition);
    }

    .dropdown-item:hover {
        background: rgba(128, 0, 0, 0.04);
        color: var(--primary-maroon);
    }

    .dropdown-item:hover i {
        background: var(--primary-maroon);
        color: white;
    }

    .nav-right {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .mobile-toggle {
        display: none;
        width: 40px;
        height: 40px;
        background: #f7fafc;
        border-radius: 10px;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: var(--primary-maroon);
        cursor: pointer;
        border: 1px solid #edf2f7;
    }

    .student-badge {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 6px 6px 6px 16px;
        background: #f7fafc;
        border-radius: 50px;
        border: 1px solid #edf2f7;
    }

    .student-details {
        text-align: right;
    }

    .student-name {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: #2d3748;
        line-height: 1.2;
    }

    .student-inst {
        display: block;
        font-size: 10px;
        font-weight: 600;
        color: var(--accent-gold);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .avatar-circle {
        width: 34px;
        height: 34px;
        background: var(--primary-maroon);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 14px;
        box-shadow: 0 4px 8px rgba(128,0,0,0.2);
    }

    .logout-link {
        color: #e53e3e;
        text-decoration: none;
        font-size: 14px;
        font-weight: 700;
        padding: 10px 18px;
        border-radius: 12px;
        transition: var(--transition);
        border: 1.5px solid rgba(229, 62, 62, 0.1);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .logout-link:hover {
        background: #fff5f5;
        border-color: #e53e3e;
        box-shadow: 0 4px 12px rgba(229, 62, 62, 0.1);
    }

    @media (max-width: 1200px) {
        .unified-navbar { padding: 0 20px; }
        .nav-logo .logo-text { display: none; }
    }

    @media (max-width: 1024px) {
        .mobile-toggle { display: flex; }
        
        .nav-menu {
            position: fixed;
            top: var(--nav-height);
            left: 0;
            width: 100%;
            height: calc(100vh - var(--nav-height));
            background: white;
            flex-direction: column;
            padding: 30px;
            gap: 15px;
            transition: var(--transition);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow-y: auto;
            z-index: 1001;
            transform: translateX(-100%);
            visibility: hidden;
            opacity: 0;
        }

        .nav-menu.active {
            transform: translateX(0);
            visibility: visible;
            opacity: 1;
        }

        .nav-btn {
            font-size: 16px;
            padding: 15px 20px;
            width: 100%;
            justify-content: flex-start;
            background: #f8fafc;
        }

        .dropdown-menu {
            position: static;
            opacity: 1;
            visibility: visible;
            transform: none;
            box-shadow: none;
            width: 100%;
            padding: 0 0 0 20px;
            margin-top: 5px;
            display: none;
            background: transparent;
            border: none;
            border-left: 2px solid #edf2f7;
        }

        .dropdown-container.active .dropdown-menu {
            display: flex;
        }

        .dropdown-container .nav-btn {
            justify-content: space-between;
        }

        .student-badge {
            padding: 6px;
        }
        
        .student-details { display: none; }
    }

    @media (max-width: 480px) {
        .unified-navbar { padding: 0 15px; }
        .nav-left { gap: 15px; }
        .logo-container { width: 34px; height: 34px; font-size: 18px; }
        .student-badge { background: transparent; border: none; padding: 0; }
        .logout-link { padding: 8px 12px; font-size: 0; }
        .logout-link i { font-size: 16px; }
    }
</style>

<nav class="unified-navbar">
    <div class="nav-left">
        <a href="dashboard.php" class="nav-logo">
            <div class="logo-container">
                <i class="fas fa-rocket"></i>
            </div>
            <div class="logo-text">
                <span class="logo-main">LAKSHYA</span>
                <span class="logo-tag">Student Growth Portal</span>
            </div>
        </a>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-btn <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
            </li>
            <li class="nav-item dropdown-container">
                <button class="nav-btn">
                    <i class="fas fa-briefcase"></i> Opportunities <i class="fas fa-chevron-down" style="font-size: 10px;"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="jobs" class="dropdown-item">
                        <i class="fas fa-building"></i> Browse Jobs
                    </a>
                    <a href="internships" class="dropdown-item">
                        <i class="fas fa-user-graduate"></i> Internships
                    </a>
                    <a href="applications" class="dropdown-item">
                        <i class="fas fa-file-signature"></i> My Applications
                    </a>
                </div>
            </li>
            <li class="nav-item dropdown-container">
                <button class="nav-btn">
                    <i class="fas fa-brain"></i> AI Tools <i class="fas fa-chevron-down" style="font-size: 10px;"></i>
                </button>
                <div class="dropdown-menu">
                    <!-- <a href="profile_analyser.php" class="dropdown-item">
                        <i class="fas fa-user-astronaut"></i> Profile Analyser
                    </a> -->
                    <a href="career_roadmap.php" class="dropdown-item">
                        <i class="fas fa-map-marked-alt"></i> Personalized Roadmap
                    </a>
                    <a href="resume_builder.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i> Resume Builder
                    </a>
                    <!-- <a href="resume_analyzer.php" class="dropdown-item">
                        <i class="fas fa-microscope"></i> Resume Analyzer
                    </a> -->
					<a href="https://gmu.ac.in/tutor/login.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i> AI Tutor
                    </a>
                </div>
            </li>
            <li class="nav-item dropdown-container">
                <button class="nav-btn">
                    <i class="fas fa-laptop-code"></i> Practice <i class="fas fa-chevron-down" style="font-size: 10px;"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="aptitude_practice.php" class="dropdown-item">
                        <i class="fas fa-puzzle-piece"></i> Aptitude Library
                    </a>
                    <a href="coding_practice.php" class="dropdown-item">
                        <i class="fas fa-code"></i> Coding Practice
                    </a>
                    <!-- <a href="mock_ai_interview.php" class="dropdown-item">
                        <i class="fas fa-user-tie"></i> Mock AI Interview
                    </a> -->
                </div>
            </li>
        </ul>
    </div>

    <div class="nav-right">
        <div class="student-badge">
            <div class="student-details">
                <span class="student-name"><?php echo htmlspecialchars($fullName); ?></span>
                <span class="student-inst"><?php echo htmlspecialchars($institution); ?></span>
            </div>
            <div class="avatar-circle">
                <?php echo strtoupper(substr($fullName, 0, 1)); ?>
            </div>
        </div>
        <a href="../logout.php" class="logout-link">
            <i class="fas fa-power-off"></i>
        </a>
        <div class="mobile-toggle" id="mobileToggle">
            <i class="fas fa-bars"></i>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileToggle = document.getElementById('mobileToggle');
        const navMenu = document.querySelector('.nav-menu');
        const dropdownToggles = document.querySelectorAll('.dropdown-container .nav-btn');

        mobileToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            const icon = this.querySelector('i');
            if (navMenu.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
                document.body.style.overflow = 'hidden';
            } else {
                icon.classList.add('fa-bars');
                icon.classList.remove('fa-times');
                document.body.style.overflow = '';
            }
        });

        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024) {
                    e.preventDefault();
                    const container = this.parentElement;
                    container.classList.toggle('active');
                    const chevron = this.querySelector('.fa-chevron-down');
                    if (chevron) {
                        chevron.style.transform = container.classList.contains('active') ? 'rotate(180deg)' : '';
                    }
                }
            });
        });
    });
</script>
<!-- Global Maintenance Interceptor -->
<script src="<?php echo APP_URL; ?>/public/js/maintenance_interceptor.js"></script>
