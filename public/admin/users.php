<?php
/**
 * User Management Grid - Admin View
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/Admin.php';

// Require admin role
requireRole(ROLE_ADMIN);

// Handle POST actions (Toggle status and edit user)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = getDB();
    
    if ($_POST['action'] === 'toggle_status' && isset($_POST['id']) && isset($_POST['role'])) {
        $targetId = (int)$_POST['id'];
        $targetRole = $_POST['role'];
        $newStatus = 0;
        
        if ($targetRole === 'dept_coordinator') {
            $stmt = $db->prepare("SELECT is_active FROM dept_coordinators WHERE id = ?");
            $stmt->execute([$targetId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $newStatus = $user['is_active'] ? 0 : 1;
                $update = $db->prepare("UPDATE dept_coordinators SET is_active = ? WHERE id = ?");
                $update->execute([$newStatus, $targetId]);
            }
        } else {
            $stmt = $db->prepare("SELECT is_active FROM app_officers WHERE id = ?");
            $stmt->execute([$targetId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $newStatus = $user['is_active'] ? 0 : 1;
                $update = $db->prepare("UPDATE app_officers SET is_active = ? WHERE id = ?");
                $update->execute([$newStatus, $targetId]);
            }
        }
        
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'new_status' => $newStatus]);
            exit;
        }
        
        header("Location: users.php?success=User status updated successfully");
        exit;
    }
    
    if ($_POST['action'] === 'edit_user' && isset($_POST['id']) && isset($_POST['role'])) {
        $targetId = (int)$_POST['id'];
        $targetRole = $_POST['role'];
        $fName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $institution = trim($_POST['institution']) ?: null;
        
        if ($targetRole === 'dept_coordinator') {
            $stmt = $db->prepare("UPDATE dept_coordinators SET full_name = ?, email = ?, institution = ? WHERE id = ?");
            $stmt->execute([$fName, $email, $institution, $targetId]);
        } else {
            $stmt = $db->prepare("UPDATE app_officers SET full_name = ?, email = ?, institution = ? WHERE id = ?");
            $stmt->execute([$fName, $email, $institution, $targetId]);
        }
        
        header("Location: users.php?success=User details updated successfully");
        exit;
    }
}

$fullName = getFullName();
$adminModel = new Admin();
$users = $adminModel->getUsersList();

// Helper to get role badge style
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'admin': return 'role-admin';
        case 'vc': return 'role-vc';
        case 'placement_officer': return 'role-po';
        case 'internship_officer': return 'role-io';
        case 'student': return 'role-student';
        case 'dept_coordinator': return 'role-co';
        default: return 'role-default';
    }
}

// Helper to get role display name
function getRoleDisplayName($role) {
    return ucwords(str_replace('_', ' ', $role));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --bg-color: #f4f7fe;
            --white: #ffffff;
            --text-dark: #2b3674;
            --text-muted: #a3aed1;
            --shadow: 0 10px 20px rgba(0,0,0,0.02);
            --transition: all 0.3s ease;
            
            /* Role Colors */
            --role-admin-bg: #fee2e2;
            --role-admin-text: #991b1b;
            --role-vc-bg: #fef3c7;
            --role-vc-text: #92400e;
            --role-po-bg: #e0f2fe;
            --role-po-text: #075985;
            --role-io-bg: #f3e8ff;
            --role-io-text: #6b21a8;
            --role-student-bg: #f1f5f9;
            --role-student-text: #475569;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-color);
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 40px;
            width: 100%;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            background: var(--white);
            padding: 20px 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .header-title h1 {
            font-size: 24px;
            color: var(--text-dark);
            font-weight: 700;
        }

        .header-title p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 5px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-profile .avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.2);
        }

        .panel {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .panel-title {
            color: var(--text-dark);
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Search Bar */
        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border-radius: 12px;
            border: 1px solid #eef2f8;
            outline: none;
            font-family: inherit;
            transition: var(--transition);
        }

        .search-box input:focus {
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.05);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 15px 10px;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            border-bottom: 1px solid #eef2f8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 15px 10px;
            color: var(--text-dark);
            font-size: 14px;
            font-weight: 500;
            border-bottom: 1px solid #eef2f8;
        }

        /* Role Badges */
        .role-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .role-admin { background: var(--role-admin-bg); color: var(--role-admin-text); }
        .role-vc { background: var(--role-vc-bg); color: var(--role-vc-text); }
        .role-po { background: var(--role-po-bg); color: var(--role-po-text); }
        .role-io { background: var(--role-io-bg); color: var(--role-io-text); }
        .role-student { background: var(--role-student-bg); color: var(--role-student-text); }
        .role-co { background: #ffedd5; color: #9a3412; }
        .role-default { background: #f3f4f6; color: #1f2937; }

        /* Status Toggle */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
        
        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        .status-active .status-dot { background: #22c55e; }
        .status-inactive .status-dot { background: #ef4444; }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            border: 1px solid #eef2f8;
            background: none;
            cursor: pointer;
            transition: var(--transition);
            margin-right: 5px;
        }

        .action-btn:hover {
            border-color: var(--primary-maroon);
            color: var(--primary-maroon);
            background: rgba(128, 0, 0, 0.05);
        }

        .back-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            display: inline-block;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--primary-maroon);
        }

        .inst-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .inst-gmu { background: #e3efff; color: #3965ff; }
        .inst-gmit { background: #e8fbed; color: #05cd99; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <div class="header">
            <div class="header-title">
                <h1>User Management</h1>
                <p>Manage portal staff, placement officers, and administrative accounts.</p>
            </div>
            <div class="user-profile">
                <span style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($fullName); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($fullName, 0, 1)); ?></div>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div style="background: #dcfce7; color: #15803d; padding: 15px 25px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; border: 1px solid #bcf0da;">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-users-cog" style="color: var(--primary-maroon);"></i> System User Accounts 
                    <span style="font-weight: 400; font-size: 14px; color: var(--text-muted); margin-left: 10px;">(<?php echo count($users); ?> Portal Accounts)</span>
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="userSearch" placeholder="Search by name, USN or role...">
                </div>
            </div>
            
            <table class="data-table" id="usersTable">
                <thead>
                    <tr>
                        <th>Name / Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Institution</th>
                        <th>Status</th>
                        <th>Joined Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700;"><?php echo htmlspecialchars($user['full_name'] ?: 'No Name'); ?></div>
                                <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($user['username']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email'] ?: '-'); ?></td>
                            <td>
                                <span class="role-badge <?php echo getRoleBadgeClass($user['role']); ?>">
                                    <?php echo getRoleDisplayName($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['institution']): ?>
                                    <span class="inst-badge <?php echo strtolower($user['institution']) === 'gmit' ? 'inst-gmit' : 'inst-gmu'; ?>">
                                        <?php echo htmlspecialchars($user['institution']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 12px;">Global</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="status-pill <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <div class="status-dot"></div>
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </div>
                            </td>
                            <td style="color: var(--text-muted); font-size: 13px;">
                                <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td>
                                <button class="action-btn edit-user-btn" 
                                        title="Edit User" 
                                        data-id="<?php echo $user['id']; ?>" 
                                        data-role="<?php echo $user['role']; ?>"
                                        data-fullname="<?php echo htmlspecialchars($user['full_name'] ?: ''); ?>"
                                        data-email="<?php echo htmlspecialchars($user['email'] ?: ''); ?>"
                                        data-institution="<?php echo htmlspecialchars($user['institution'] ?: ''); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="toggle-status-form" style="display: inline-block;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="role" value="<?php echo $user['role']; ?>">
                                    <button type="submit" class="action-btn" title="Toggle Status"><i class="fas fa-power-off"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 50px;">No portal accounts found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(5px); justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 20px; width: 100%; max-width: 500px; padding: 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); position: relative; margin: 20px;">
            <h2 style="font-size: 20px; font-weight: 700; color: var(--text-dark); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-user-edit" style="color: var(--primary-maroon);"></i> Edit User Info
            </h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="role" id="edit_role">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-dark); margin-bottom: 6px;">Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" required style="width: 100%; padding: 10px 15px; border-radius: 10px; border: 1px solid #eef2f8; outline: none; font-family: inherit; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-dark); margin-bottom: 6px;">Email</label>
                    <input type="text" name="email" id="edit_email" required style="width: 100%; padding: 10px 15px; border-radius: 10px; border: 1px solid #eef2f8; outline: none; font-family: inherit; font-size: 14px;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-dark); margin-bottom: 6px;">Institution</label>
                    <select name="institution" id="edit_institution" style="width: 100%; padding: 10px 15px; border-radius: 10px; border: 1px solid #eef2f8; outline: none; background: white; font-family: inherit; font-size: 14px; cursor: pointer;">
                        <option value="">Global / None</option>
                        <option value="GMU">GMU</option>
                        <option value="GMIT">GMIT</option>
                    </select>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; border-radius: 10px; border: 1px solid #eef2f8; background: none; cursor: pointer; font-weight: 600; color: var(--text-muted); font-family: inherit;">Cancel</button>
                    <button type="submit" style="padding: 10px 20px; border-radius: 10px; border: none; background: var(--primary-maroon); color: white; cursor: pointer; font-weight: 600; font-family: inherit;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Simple search filter
            const searchInput = document.getElementById('userSearch');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#usersTable tbody tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });
            }

            // Modal functions
            const modal = document.getElementById('editUserModal');
            
            document.querySelectorAll('.edit-user-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('edit_id').value = this.dataset.id;
                    document.getElementById('edit_role').value = this.dataset.role;
                    document.getElementById('edit_full_name').value = this.dataset.fullname;
                    document.getElementById('edit_email').value = this.dataset.email;
                    document.getElementById('edit_institution').value = this.dataset.institution;
                    if (modal) modal.style.display = 'flex';
                });
            });

            // Close when clicking outside modal content
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeEditModal();
                }
            });

            // Toggle status via AJAX
            document.querySelectorAll('.toggle-status-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!confirm('Are you sure you want to toggle this user\'s status?')) {
                        return;
                    }
                    
                    const formData = new FormData(this);
                    formData.append('ajax', '1');
                    
                    fetch('users.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const row = this.closest('tr');
                            const statusPill = row.querySelector('.status-pill');
                            if (statusPill) {
                                if (data.new_status === 1 || data.new_status === '1') {
                                    statusPill.className = 'status-pill status-active';
                                    statusPill.innerHTML = '<div class="status-dot"></div>Active';
                                } else {
                                    statusPill.className = 'status-pill status-inactive';
                                    statusPill.innerHTML = '<div class="status-dot"></div>Inactive';
                                }
                            }
                        }
                    })
                    .catch(err => console.error('Error toggling status:', err));
                });
            });
        });

        function closeEditModal() {
            const modal = document.getElementById('editUserModal');
            if (modal) modal.style.display = 'none';
        }
    </script>
</body>
</html>

