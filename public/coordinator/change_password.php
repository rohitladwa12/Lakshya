<?php
/**
 * Change Password Page
 * Allows coordinators to update their account security
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_DEPT_COORDINATOR);

$fullName = getFullName();
$department = getDepartment();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --white: #ffffff;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
            --primary-maroon: #800000;
            --primary-gold: #e9c66f;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .main-content { padding: 40px; max-width: 600px; margin: 0 auto; width: 100%; }
        .page-header { margin-bottom: 30px; text-align: center; }
        .page-header h2 { font-size: 28px; color: var(--primary-maroon); font-weight: 800; }
        
        .card {
            background: var(--white);
            padding: 40px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .form-group { margin-bottom: 25px; }
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8fafc;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-gold);
            background: white;
            box-shadow: 0 0 0 4px rgba(233, 198, 111, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: var(--primary-maroon);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-submit:hover {
            background: #600000;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(128, 0, 0, 0.2);
        }
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .password-requirements {
            margin-top: 20px;
            padding: 15px;
            background: #fffbeb;
            border-radius: 12px;
            border: 1px solid #fef3c7;
        }
        .requirement-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #92400e;
            margin-bottom: 5px;
        }
        .requirement-item i { font-size: 12px; }

        .toast {
            position: fixed;
            top: 100px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 9999;
            transform: translateX(120%);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
        .toast.show { transform: translateX(0); }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2>Account Security</h2>
            <p style="color: #64748b; margin-top: 8px;">Update your password to keep your account secure</p>
        </div>

        <div class="card">
            <form id="passwordForm">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required placeholder="Enter current password">
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required placeholder="Enter new password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Confirm new password">
                </div>

                <div class="password-requirements">
                    <div class="requirement-item"><i class="fas fa-info-circle"></i> At least 8 characters long</div>
                    <div class="requirement-item"><i class="fas fa-info-circle"></i> Include at least one uppercase letter</div>
                    <div class="requirement-item"><i class="fas fa-info-circle"></i> Include at least one number</div>
                    <div class="requirement-item"><i class="fas fa-info-circle"></i> Include at least one special character</div>
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 30px;">
                    <i class="fas fa-save"></i> Update Password
                </button>
            </form>
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

        document.getElementById('passwordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = this.querySelector('button');
            const originalText = btn.innerHTML;
            
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                showToast('Passwords do not match', 'error');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            try {
                const formData = new FormData(this);
                const response = await fetch('password_handler', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    this.reset();
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
        });
    </script>
</body>
</html>

