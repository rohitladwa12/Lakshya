<?php
/**
 * Modern Executive Light-Theme Navbar for Internship Officer
 */
$current_page = basename($_SERVER['PHP_SELF']);
$fullName = $_SESSION['full_name'] ?? 'Internship Officer';
include_once __DIR__ . '/../includes/demo_protection.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
    :root {
        --io-maroon: #800000;
        --io-maroon-dark: #5b0000;
        --io-maroon-light: #fdf2f2;
        --io-maroon-subtle: rgba(128, 0, 0, 0.08);
        --io-navy: #0f172a;
        --io-slate-800: #1e293b;
        --io-slate-600: #475569;
        --io-slate-500: #64748b;
        --io-slate-400: #94a3b8;
        --io-slate-200: #e2e8f0;
        --io-slate-100: #f1f5f9;
        --io-slate-50: #f8fafc;
        --io-white: #ffffff;
        --io-shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06);
        --io-shadow-md: 0 4px 14px -2px rgba(15, 23, 42, 0.08);
    }

    .io-navbar {
        background: var(--io-white);
        height: 72px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 2rem;
        border-bottom: 1px solid var(--io-slate-200);
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        position: sticky;
        top: 0;
        z-index: 1000;
        font-family: 'Inter', sans-serif;
    }

    .io-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    .io-brand-icon {
        width: 42px;
        height: 42px;
        background: linear-gradient(135deg, var(--io-maroon), #a11616);
        color: var(--io-white);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.25);
    }

    .io-brand-text {
        display: flex;
        flex-direction: column;
    }

    .io-brand-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.2rem;
        font-weight: 800;
        color: var(--io-navy);
        letter-spacing: -0.02em;
        line-height: 1.1;
    }

    .io-brand-title span {
        color: var(--io-maroon);
    }

    .io-brand-subtitle {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--io-slate-500);
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .io-nav-links {
        display: flex;
        align-items: center;
        gap: 6px;
        background: var(--io-slate-100);
        padding: 5px;
        border-radius: 14px;
        border: 1px solid var(--io-slate-200);
    }

    .io-nav-item {
        color: var(--io-slate-600);
        text-decoration: none;
        font-size: 0.88rem;
        font-weight: 600;
        padding: 8px 16px;
        border-radius: 10px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .io-nav-item i {
        font-size: 0.95rem;
        transition: transform 0.2s ease;
    }

    .io-nav-item:hover {
        color: var(--io-navy);
        background: rgba(255, 255, 255, 0.7);
    }

    .io-nav-item:hover i {
        transform: translateY(-1px);
    }

    .io-nav-item.active {
        background: var(--io-white);
        color: var(--io-maroon);
        box-shadow: var(--io-shadow-sm);
        border: 1px solid var(--io-slate-200);
    }

    .io-nav-item.active i {
        color: var(--io-maroon);
    }

    .io-user-section {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .io-profile-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 6px 14px;
        background: var(--io-slate-50);
        border: 1px solid var(--io-slate-200);
        border-radius: 14px;
    }

    .io-avatar {
        width: 38px;
        height: 38px;
        background: linear-gradient(135deg, var(--io-maroon), var(--io-maroon-dark));
        color: var(--io-white);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 0.95rem;
        box-shadow: 0 2px 6px rgba(128, 0, 0, 0.2);
        position: relative;
    }

    .io-avatar::after {
        content: '';
        position: absolute;
        bottom: -1px;
        right: -1px;
        width: 10px;
        height: 10px;
        background: #10b981;
        border: 2px solid #ffffff;
        border-radius: 50%;
    }

    .io-user-meta {
        display: flex;
        flex-direction: column;
    }

    .io-user-name {
        font-size: 0.86rem;
        font-weight: 700;
        color: var(--io-navy);
        line-height: 1.2;
    }

    .io-user-role {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--io-maroon);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .io-btn-logout {
        color: #dc2626;
        background: #fef2f2;
        border: 1px solid #fee2e2;
        padding: 9px 14px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .io-btn-logout:hover {
        background: #dc2626;
        color: #ffffff;
        border-color: #dc2626;
        box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2);
    }

    @media (max-width: 960px) {
        .io-navbar { padding: 0 1rem; }
        .io-brand-subtitle { display: none; }
        .io-user-meta { display: none; }
    }

    @media (max-width: 720px) {
        .io-nav-item span { display: none; }
        .io-nav-item { padding: 8px 10px; }
        .io-btn-logout span { display: none; }
        .io-btn-logout { padding: 8px 10px; }
    }
</style>

<nav class="io-navbar">
    <a href="dashboard.php" class="io-brand">
        <div class="io-brand-icon">
            <i class="fas fa-briefcase"></i>
        </div>
        <div class="io-brand-text">
            <div class="io-brand-title">Lakshya <span>Internships</span></div>
            <div class="io-brand-subtitle">Executive Console</div>
        </div>
    </a>

    <div class="io-nav-links">
        <a href="dashboard.php" class="io-nav-item <?php echo in_array($current_page, ['dashboard.php', 'index.php']) ? 'active' : ''; ?>">
            <i class="fas fa-grid-2"></i>
            <span>Dashboard</span>
        </a>
        <a href="add_internship.php" class="io-nav-item <?php echo $current_page == 'add_internship.php' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Post Internship</span>
        </a>
        <a href="internship_placed.php" class="io-nav-item <?php echo $current_page == 'internship_placed.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate"></i>
            <span>Placed Registry</span>
        </a>
    </div>

    <div class="io-user-section">
        <div class="io-profile-card">
            <div class="io-avatar"><?php echo strtoupper(substr($fullName, 0, 2)); ?></div>
            <div class="io-user-meta">
                <span class="io-user-name"><?php echo htmlspecialchars($fullName); ?></span>
                <span class="io-user-role">Internship Admin</span>
            </div>
        </div>
        <a href="../logout.php" class="io-btn-logout" title="Sign out of Lakshya console">
            <i class="fas fa-arrow-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>

<!-- Global Security Layer -->
<script>
    window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ""; ?>';
</script>
<script src="<?php echo APP_URL; ?>/js/security_interceptor.js?v=<?php echo time(); ?>"></script>
