<?php
/**
 * Global Demo Mode Protection Include
 * Shows a "Demo Mode" badge and disables interactive elements via JS
 */
if (Session::getRole() === ROLE_DEMO):
?>
<div id="demo-mode-badge" style="position: fixed; top: 85px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, #ff9800, #f44336); color: white; padding: 6px 20px; border-radius: 30px; font-weight: 800; font-size: 11px; z-index: 99999; box-shadow: 0 4px 15px rgba(255,152,0,0.4); pointer-events: none; letter-spacing: 1px; text-transform: uppercase; border: 2px solid rgba(255,255,255,0.3); display: flex; align-items: center; gap: 8px;">
    <i class="fas fa-eye"></i> Demo Mode: Read Only
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Lakshya: Demo Mode Active');

    // 1. Disable all submit buttons
    const disableElements = () => {
        const submits = document.querySelectorAll('button[type="submit"], input[type="submit"], .btn-save, .tb-btn-save, .pf-btn-add, .add-entry-btn');
        submits.forEach(btn => {
            if (!btn.classList.contains('nav-btn')) {
                btn.disabled = true;
                btn.title = "Action disabled in Demo Mode";
                btn.style.opacity = '0.5';
                btn.style.filter = 'grayscale(1)';
                btn.style.cursor = 'not-allowed';
            }
        });

        // 2. Intercept form submissions
        document.querySelectorAll('form').forEach(form => {
            if (form.getAttribute('action') !== 'logout.php') {
                form.onsubmit = function(e) {
                    e.preventDefault();
                    showDemoWarning();
                    return false;
                };
            }
        });

        // 3. Disable various danger/action buttons
        const actionBtns = document.querySelectorAll('.btn-danger, .btn-primary:not(.nav-btn), .tb-btn-pdf, .sync-btn');
        actionBtns.forEach(btn => {
             if (btn.tagName === 'A') {
                 // Check if it's a real action or just navigation
                 const href = btn.getAttribute('href');
                 if (href && (href.includes('delete') || href.includes('remove') || href.includes('sync'))) {
                     btn.style.pointerEvents = 'none';
                     btn.style.opacity = '0.5';
                 }
             } else {
                 btn.style.opacity = '0.5';
                 btn.style.cursor = 'not-allowed';
             }
        });
    };

    const showDemoWarning = () => {
        alert('Action Denied: You are currently in Demo Mode. Modifications are not allowed.');
    };

    // Initial run
    disableElements();

    // Re-run for dynamic elements (e.g. after AJAX loads, though we block those too)
    const observer = new MutationObserver(disableElements);
    observer.observe(document.body, { childList: true, subtree: true });

    // 4. Global fetch intercept for POST requests
    const originalFetch = window.fetch;
    window.fetch = function() {
        if (arguments[1] && arguments[1].method && arguments[1].method.toUpperCase() === 'POST') {
             console.warn('POST blocked in Demo Mode');
             showDemoWarning();
             return Promise.resolve(new Response(JSON.stringify({
                 success: false,
                 message: 'Action denied in Demo Mode.'
             }), { status: 403, headers: { 'Content-Type': 'application/json' } }));
        }
        return originalFetch.apply(this, arguments);
    };
});
</script>
<?php endif; ?>

<?php if (Session::getRole() === ROLE_DEMO): ?>
<div id="demo-switcher" style="position: fixed; bottom: 25px; right: 25px; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); padding: 12px; z-index: 999999; border: 1px solid #e2e8f0; width: 180px; font-family: 'Outfit', sans-serif;">
    <div style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px; text-align: center; letter-spacing: 1px;">Switch View</div>
    <div style="display: flex; flex-direction: column; gap: 4px;">
        <a href="../student/dashboard" class="switcher-link <?php echo strpos($_SERVER['PHP_SELF'], 'student') !== false ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate"></i> Student View
        </a>
        <a href="../coordinator/dashboard.php" class="switcher-link <?php echo strpos($_SERVER['PHP_SELF'], 'coordinator') !== false ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard-teacher"></i> Coordinator
        </a>
        <a href="../officer/dashboard" class="switcher-link <?php echo strpos($_SERVER['PHP_SELF'], 'officer') !== false && strpos($_SERVER['PHP_SELF'], 'internship') === false ? 'active' : ''; ?>">
            <i class="fas fa-briefcase"></i> Placement Off.
        </a>
        <a href="../internship_officer/dashboard.php" class="switcher-link <?php echo strpos($_SERVER['PHP_SELF'], 'internship_officer') !== false ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i> Internship Off.
        </a>
    </div>
</div>

<style>
.switcher-link {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 10px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.switcher-link i {
    width: 16px;
    text-align: center;
    font-size: 14px;
    opacity: 0.7;
}
.switcher-link:hover {
    background: #f1f5f9;
    color: #800000;
    transform: translateX(4px);
}
.switcher-link.active {
    background: rgba(128, 0, 0, 0.08);
    color: #800000;
}
.switcher-link.active i {
    opacity: 1;
}
</style>
<?php endif; ?>
