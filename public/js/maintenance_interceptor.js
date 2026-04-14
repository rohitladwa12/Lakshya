/**
 * Maintenance Mode Global Interceptor
 * Detects 503 Maintenance responses and shows a graceful overlay to prevent data loss.
 */
(function() {
    const originalFetch = window.fetch;
    window.fetch = async function(...args) {
        try {
            const response = await originalFetch(...args);
            
            // If the response is a 503 (which we set in bootstrap.php for maintenance)
            // AND it's a JSON response from our own domain
            if (response.status === 503) {
                const clone = response.clone();
                try {
                    const data = await clone.json();
                    if (data.maintenance) {
                        showMaintenanceOverlay(data.message);
                    }
                } catch (e) {
                    // Not JSON, ignore
                }
            }
            return response;
        } catch (error) {
            throw error;
        }
    };

    function showMaintenanceOverlay(message) {
        if (document.getElementById('maintenance-global-overlay')) return;

        const overlay = document.createElement('div');
        overlay.id = 'maintenance-global-overlay';
        overlay.innerHTML = `
            <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(8px); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px; font-family: 'Outfit', sans-serif;">
                <div style="background: white; padding: 40px; border-radius: 24px; max-width: 500px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); animation: springIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                    <div style="width: 80px; height: 80px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: #ef4444; font-size: 32px;">
                        <i class="fas fa-hammer"></i>
                    </div>
                    <h2 style="color: #0f172a; margin-bottom: 16px; font-weight: 800;">Maintenance Started</h2>
                    <p style="color: #64748b; line-height: 1.6; margin-bottom: 30px;">${message}</p>
                    <button onclick="window.location.reload()" style="background: #800000; color: white; border: none; padding: 12px 30px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s;">
                        Got it, take me to updates
                    </button>
                    <div style="margin-top: 20px; font-size: 12px; color: #94a3b8;">Don't worry, session progress is usually saved automatically.</div>
                </div>
            </div>
            <style>
                @keyframes springIn {
                    from { opacity: 0; transform: scale(0.8) translateY(20px); }
                    to { opacity: 1; transform: scale(1) translateY(0); }
                }
            </style>
        `;
        document.body.appendChild(overlay);
    }
})();
