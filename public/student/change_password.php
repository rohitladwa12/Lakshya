<?php
/**
 * Student Change Password Page
 * Allows students (including GMIT students) to update their password
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_STUDENT);

$fullName = getFullName();
$institution = getInstitution();

if ($institution !== INSTITUTION_GMIT) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --white: #ffffff;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --primary-maroon: #800000;
            --primary-gold: #e9c66f;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px;
            max-width: 500px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .page-header h2 {
            font-size: 24px;
            color: var(--primary-maroon);
            font-weight: 800;
        }

        .card {
            background: var(--white);
            padding: 28px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
            width: 100%;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #1e293b;
            font-size: 13px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8fafc;
            font-family: 'Outfit', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-gold);
            background: white;
            box-shadow: 0 0 0 3px rgba(233, 198, 111, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--primary-maroon);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Outfit', sans-serif;
        }

        .btn-submit:hover {
            background: #600000;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(128, 0, 0, 0.15);
        }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .password-requirements {
            margin-top: 15px;
            padding: 10px 14px;
            background: #fffbeb;
            border-radius: 10px;
            border: 1px solid #fef3c7;
        }

        .requirement-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #92400e;
        }

        .requirement-item i {
            font-size: 11px;
        }

        .toast {
            position: fixed;
            top: 100px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            transform: translateX(120%);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toast.success {
            background: #10b981;
        }

        .toast.error {
            background: #ef4444;
        }

        .toast.show {
            transform: translateX(0);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-maroon);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 15px;
            align-self: flex-start;
            font-size: 14px;
        }

        /* Modal Styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .modal-backdrop.show {
            opacity: 1;
        }

        .modal-card {
            background: var(--white);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            max-width: 420px;
            width: 90%;
            text-align: center;
            transform: scale(0.9);
            transition: transform 0.2s ease;
        }

        .modal-backdrop.show .modal-card {
            transform: scale(1);
        }

        .modal-icon {
            font-size: 40px;
            color: #d97706;
            margin-bottom: 15px;
        }

        .modal-card h3 {
            font-size: 20px;
            color: var(--text-main);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .modal-card p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn-cancel,
        .btn-proceed {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-family: 'Outfit', sans-serif;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-cancel:hover {
            background: #cbd5e1;
        }

        .btn-proceed {
            background: var(--primary-maroon);
            color: white;
        }

        .btn-proceed:hover {
            background: #600000;
        }
    </style>
</head>

<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="page-header">
            <h2>Account Security</h2>
            <p style="color: #64748b; margin-top: 6px; font-size: 13px;">Update your password to keep your student
                account secure</p>
        </div>

        <div class="card">
            <form id="passwordForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required
                        placeholder="Enter current password">
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required
                        placeholder="Enter new password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                        placeholder="Confirm new password">
                </div>

                <div class="password-requirements">
                    <div class="requirement-item"><i class="fas fa-info-circle"></i> Minimum 6 characters required</div>
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 20px;">
                    <i class="fas fa-save"></i> Update Password
                </button>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal-backdrop" style="display: none;">
        <div class="modal-card">
            <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h3>ERP Password Sync</h3>
            <p>Updating here will update your password for your GMIT ERP account too.</p>
            <div class="modal-actions">
                <button id="cancelBtn" class="btn-cancel">Cancel</button>
                <button id="proceedBtn" class="btn-proceed">Proceed</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type} show`;
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        document.getElementById('passwordForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                showToast('Passwords do not match', 'error');
                return;
            }

            const modal = document.getElementById('confirmModal');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        });

        document.getElementById('cancelBtn').addEventListener('click', function () {
            closeModal();
        });

        document.getElementById('proceedBtn').addEventListener('click', function () {
            closeModal();
            performUpdate();
        });

        function closeModal() {
            const modal = document.getElementById('confirmModal');
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 200);
        }

        async function performUpdate() {
            const form = document.getElementById('passwordForm');
            const btn = form.querySelector('button');
            const originalText = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            try {
                const formData = new FormData(form);
                const response = await fetch('password_handler.php', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    form.reset();
                } else {
                    showToast(result.message || 'Failed to update password', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('An unexpected error occurred', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>
</body>

</html>