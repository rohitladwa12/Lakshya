/**
 * Lakshya Frontend Resilience Script
 * Handles version checking and update notifications
 */
(function() {
    let currentVersion = null;
    let checkInterval = 120000; // Check every 2 minutes

    async function checkVersion() {
        try {
            const res = await fetch('version.php').then(r => r.json());
            if (res.success) {
                if (currentVersion === null) {
                    currentVersion = res.version;
                } else if (currentVersion !== res.version) {
                    showUpdateNotification();
                    currentVersion = res.version;
                }
            }
        } catch (e) {
            console.warn("Version check failed", e);
        }
    }

    function showUpdateNotification() {
        const div = document.createElement('div');
        div.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 10000;
            background: #D4AF37; color: #000; padding: 15px 25px;
            border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            font-family: 'Outfit', sans-serif; font-weight: 600;
            display: flex; align-items: center; gap: 15px;
            animation: slideIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        `;
        div.innerHTML = `
            <i class="fas fa-sync-alt fa-spin"></i>
            <span>System Updated! Refresh after this question for new features.</span>
            <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; font-size:1.2rem;">&times;</button>
        `;
        document.body.appendChild(div);

        const style = document.createElement('style');
        style.innerHTML = `
            @keyframes slideIn { from { transform: translateX(120%); } to { transform: translateX(0); } }
        `;
        document.head.appendChild(style);
    }

    // Start checking
    setTimeout(checkVersion, 5000); // Initial check after 5s
    setInterval(checkVersion, checkInterval);
})();
