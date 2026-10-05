/**
 * Lakshya Dialogs — in-page replacement for the browser's native alert() / confirm() popups.
 *
 * Native popups look like a Chrome warning, exit fullscreen in some browsers and blur the
 * window (which proctored pages count as a violation). These dialogs render inside the page.
 *
 *   await LakshyaDialog.alert('Saved!', { type: 'success' });
 *   if (await LakshyaDialog.confirm('Submit now?', { okText: 'Submit' })) { ... }
 *
 * Options: title, type ('info' | 'success' | 'warning' | 'error'), okText, cancelText,
 *          html (true = message is trusted HTML; default is plain text), danger (red OK button).
 *
 * window.alert is also replaced as a safety net so a missed call never shows a native popup.
 * It is non-blocking, so code that redirects right after alert() must await LakshyaDialog.alert().
 */
(function () {
    if (window.LakshyaDialog) return;

    const ICONS = {
        info:    { icon: 'fa-info-circle',          color: '#60a5fa' },
        success: { icon: 'fa-check-circle',         color: '#10b981' },
        warning: { icon: 'fa-exclamation-triangle', color: '#f59e0b' },
        error:   { icon: 'fa-times-circle',         color: '#ef4444' }
    };
    const DEFAULT_TITLES = { info: 'Notice', success: 'Success', warning: 'Please Note', error: 'Something Went Wrong' };

    const CSS = `
        .lk-dialog-backdrop {
            position: fixed; inset: 0; z-index: 2147483000;
            background: rgba(2, 6, 23, 0.72);
            backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
            display: flex; align-items: center; justify-content: center;
            padding: 16px; opacity: 0; transition: opacity 0.18s ease;
            font-family: 'Outfit', 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        .lk-dialog-backdrop.lk-open { opacity: 1; }
        .lk-dialog {
            width: 100%; max-width: 440px;
            background: #0f172a; color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-top: 3px solid #800000;
            border-radius: 20px; padding: 28px 26px 22px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6);
            text-align: center;
            transform: translateY(12px) scale(0.97); transition: transform 0.18s ease;
            -webkit-user-select: text; user-select: text;
        }
        .lk-dialog-backdrop.lk-open .lk-dialog { transform: none; }
        .lk-dialog-icon { font-size: 2.6rem; margin-bottom: 12px; }
        .lk-dialog-title { font-size: 1.15rem; font-weight: 700; color: #fff; margin: 0 0 10px; }
        .lk-dialog-msg { font-size: 0.95rem; line-height: 1.55; color: #cbd5e1; margin: 0 0 22px; word-wrap: break-word; white-space: pre-line; }
        .lk-dialog-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .lk-dialog-btn {
            flex: 1 1 140px; min-height: 44px; padding: 10px 18px;
            border-radius: 12px; border: 1px solid transparent;
            font: inherit; font-weight: 700; font-size: 0.95rem; cursor: pointer;
            transition: transform 0.12s ease, filter 0.12s ease;
        }
        .lk-dialog-btn:hover { filter: brightness(1.12); }
        .lk-dialog-btn:active { transform: scale(0.97); }
        .lk-dialog-btn:focus-visible { outline: 2px solid #D4AF37; outline-offset: 2px; }
        .lk-dialog-btn-ok { background: linear-gradient(135deg, #800000, #4a0000); color: #fff; }
        .lk-dialog-btn-ok.lk-danger { background: linear-gradient(135deg, #dc2626, #991b1b); }
        .lk-dialog-btn-cancel { background: rgba(255, 255, 255, 0.06); color: #e2e8f0; border-color: rgba(255, 255, 255, 0.14); }
    `;

    function injectStyles() {
        if (document.getElementById('lk-dialog-styles')) return;
        const style = document.createElement('style');
        style.id = 'lk-dialog-styles';
        style.textContent = CSS;
        (document.head || document.documentElement).appendChild(style);
    }

    // Dialogs are shown one at a time, in the order they were requested
    let queue = Promise.resolve();

    function open(kind, message, options) {
        const opts = options || {};
        const run = () => new Promise((resolve) => {
            injectStyles();
            const type = ICONS[opts.type] ? opts.type : (kind === 'confirm' ? 'warning' : 'info');
            const previousFocus = document.activeElement;

            const backdrop = document.createElement('div');
            backdrop.className = 'lk-dialog-backdrop';
            backdrop.setAttribute('role', kind === 'confirm' ? 'alertdialog' : 'dialog');
            backdrop.setAttribute('aria-modal', 'true');

            const box = document.createElement('div');
            box.className = 'lk-dialog';

            const icon = document.createElement('div');
            icon.className = 'lk-dialog-icon';
            icon.innerHTML = `<i class="fas ${ICONS[type].icon}" style="color:${ICONS[type].color}"></i>`;

            const title = document.createElement('h3');
            title.className = 'lk-dialog-title';
            title.textContent = opts.title || (kind === 'confirm' ? 'Please Confirm' : DEFAULT_TITLES[type]);

            const msg = document.createElement('p');
            msg.className = 'lk-dialog-msg';
            if (opts.html) msg.innerHTML = String(message ?? '');
            else msg.textContent = String(message ?? '');

            const actions = document.createElement('div');
            actions.className = 'lk-dialog-actions';

            const okBtn = document.createElement('button');
            okBtn.type = 'button';
            okBtn.className = 'lk-dialog-btn lk-dialog-btn-ok' + (opts.danger ? ' lk-danger' : '');
            okBtn.textContent = opts.okText || 'OK';

            let cancelBtn = null;
            if (kind === 'confirm') {
                cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.className = 'lk-dialog-btn lk-dialog-btn-cancel';
                cancelBtn.textContent = opts.cancelText || 'Cancel';
                actions.appendChild(cancelBtn);
            }
            actions.appendChild(okBtn);

            box.append(icon, title, msg, actions);
            backdrop.appendChild(box);
            title.id = 'lk-dialog-title-' + Date.now();
            backdrop.setAttribute('aria-labelledby', title.id);

            const close = (result) => {
                document.removeEventListener('keydown', onKey, true);
                backdrop.classList.remove('lk-open');
                setTimeout(() => backdrop.remove(), 180);
                if (previousFocus && typeof previousFocus.focus === 'function') {
                    try { previousFocus.focus({ preventScroll: true }); } catch (e) {}
                }
                resolve(kind === 'confirm' ? result : undefined);
            };

            const onKey = (e) => {
                if (e.key === 'Escape') {
                    e.preventDefault(); e.stopPropagation();
                    close(false);
                } else if (e.key === 'Enter') {
                    e.preventDefault(); e.stopPropagation();
                    close(document.activeElement === cancelBtn ? false : true);
                } else if (e.key === 'Tab') {
                    // Keep focus inside the dialog
                    const buttons = cancelBtn ? [cancelBtn, okBtn] : [okBtn];
                    const idx = buttons.indexOf(document.activeElement);
                    e.preventDefault();
                    buttons[(idx + (e.shiftKey ? buttons.length - 1 : 1)) % buttons.length].focus();
                }
            };

            okBtn.addEventListener('click', () => close(true));
            if (cancelBtn) cancelBtn.addEventListener('click', () => close(false));

            // In fullscreen only the fullscreen element and its children are visible
            const host = document.fullscreenElement || document.webkitFullscreenElement || document.body || document.documentElement;
            (host === document.documentElement ? document.body : host).appendChild(backdrop);
            document.addEventListener('keydown', onKey, true);
            requestAnimationFrame(() => backdrop.classList.add('lk-open'));
            okBtn.focus({ preventScroll: true });
        });

        const result = queue.then(run, run);
        queue = result.catch(() => {});
        return result;
    }

    window.LakshyaDialog = {
        alert: (message, options) => open('alert', message, options),
        confirm: (message, options) => open('confirm', message, options)
    };

    // Safety net: never show the native Chrome popup for alert()
    window.alert = function (message) { window.LakshyaDialog.alert(message); };
})();
