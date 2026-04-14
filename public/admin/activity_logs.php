<?php
/**
 * Admin Activity Logs Dashboard
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/Logger.php';

// Require admin role
requireRole(ROLE_ADMIN);

$logger = new Logger();
$stats = $logger->getUsageStats();

// Pagination for logs
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$logs = $logger->getRecentLogs($perPage, $offset);
$totalLogs = $logger->count(); // Use base model count
$totalPages = ceil($totalLogs / $perPage);

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --accent-blue: #4318ff;
            --bg-color: #f4f7fe;
            --white: #ffffff;
            --text-dark: #2b3674;
            --text-muted: #a3aed1;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --shadow: 0 20px 40px rgba(0,0,0,0.05);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-color);
            display: flex;
            min-height: 100vh;
            color: var(--text-dark);
        }

        .main-content {
            flex: 1;
            padding: 40px;
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
        }

        .glass-header {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding: 25px 35px;
            border-radius: 30px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .header-title h1 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -1px;
            background: linear-gradient(135deg, var(--primary-maroon), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .metric-card {
            background: var(--white);
            padding: 24px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: var(--transition);
        }

        .metric-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .icon-active { background: #E2F9F2; color: #05CD99; }
        .icon-actions { background: #E9EDFE; color: #4318FF; }
        .icon-users { background: #FFF4E5; color: #FF9920; }

        .metric-info h3 { font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 2px; }
        .metric-info .value { font-size: 24px; font-weight: 800; }

        .logs-panel {
            background: var(--white);
            border-radius: 30px;
            padding: 35px;
            box-shadow: var(--shadow);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .panel-title { font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 12px; }

        .table-container { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            text-align: left;
            padding: 15px;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            border-bottom: 1px solid #F4F7FE;
        }

        td {
            padding: 18px 15px;
            border-bottom: 1px solid #F4F7FE;
            font-size: 14px;
        }

        tr:hover td { background: #FAFBFF; }

        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-login { background: #E2F9F2; color: #05CD99; }
        .badge-mock { background: #E9EDFE; color: #4318FF; }
        .badge-failed { background: #FFF5F5; color: #C53030; }
        .badge-other { background: #F4F7FE; color: #718096; }

        .user-info { display: flex; flex-direction: column; }
        .user-name { font-weight: 700; color: var(--text-dark); }
        .user-usn { font-size: 12px; color: var(--text-muted); }

        .action-cell { font-weight: 500; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a, .pagination span {
            padding: 8px 16px;
            border-radius: 10px;
            background: #F4F7FE;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .pagination a:hover { background: var(--primary-gold); color: var(--primary-dark); }
        .pagination span.active { background: var(--primary-maroon); color: white; }

        .meta-btn {
            background: none;
            border: 1px solid #EDF2F7;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            color: var(--accent-blue);
            transition: var(--transition);
        }

        .meta-btn:hover { background: #F0F5FF; border-color: var(--accent-blue); }

        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 24px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }

        pre {
            background: #F8FAFC;
            padding: 20px;
            border-radius: 12px;
            font-family: 'monospace';
            font-size: 13px;
            overflow: auto;
            margin-top: 15px;
            border: 1px solid #E2E8F0;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <header class="glass-header">
            <div class="header-title">
                <h1>Global Activity Intelligence</h1>
                <p>Tracking system-wide engagement and high-impact events</p>
            </div>
            
            <div style="display: flex; gap: 20px; align-items: center;">
                <div style="text-align: right;">
                    <div style="font-weight: 700;"><?php echo htmlspecialchars($fullName); ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);">Platform Administrator</div>
                </div>
                <div class="avatar" style="width: 45px; height: 45px; background: var(--primary-maroon); color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 20px;">
                    <?php echo strtoupper(substr($fullName, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon icon-actions"><i class="fas fa-bolt"></i></div>
                <div class="metric-info">
                    <h3>Actions Today</h3>
                    <div class="value"><?php echo number_format($stats['actions_today']); ?></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon icon-active"><i class="fas fa-user-check"></i></div>
                <div class="metric-info">
                    <h3>Active Users (Today)</h3>
                    <div class="value"><?php echo number_format($stats['active_today']); ?></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon icon-users"><i class="fas fa-history"></i></div>
                <div class="metric-info">
                    <h3>Total Logs</h3>
                    <div class="value"><?php echo number_format($totalLogs); ?></div>
                </div>
            </div>
        </div>

        <div class="logs-panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-stream" style="color: var(--primary-maroon);"></i> System Event Stream
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Context</th>
                            <th>IP / Device</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                            <?php 
                                $badgeClass = 'badge-other';
                                if (strpos($log['action'], 'login') !== false) $badgeClass = 'badge-login';
                                if (strpos($log['action'], 'mock_ai') !== false) $badgeClass = 'badge-mock';
                                if (strpos($log['action'], 'failed') !== false) $badgeClass = 'badge-failed';
                            ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <span class="user-name"><?php echo htmlspecialchars($log['user_name'] ?: 'Guest / System'); ?></span>
                                        <span class="user-usn"><?php echo htmlspecialchars($log['usn'] ?: $log['ip_address']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($log['action']); ?></span>
                                </td>
                                <td class="action-cell">
                                    <?php echo htmlspecialchars($log['description']); ?>
                                </td>
                                <td>
                                    <?php if($log['meta_data']): ?>
                                        <button class="meta-btn" data-meta='<?php echo htmlspecialchars($log['meta_data'], ENT_QUOTES, 'UTF-8'); ?>' onclick="showMeta(this)">
                                            <i class="fas fa-eye"></i> View JSON
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #CBD5E0; font-size: 12px;">No metadata</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);">
                                    <?php echo $log['ip_address']; ?>
                                    <div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                        <?php echo htmlspecialchars($log['user_agent']); ?>
                                    </div>
                                </td>
                                <td style="white-space: nowrap;">
                                    <div style="font-weight: 600;"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($log['created_at'])); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>

                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Metadata Modal -->
    <div id="metaModal" class="modal" onclick="this.style.display='none'">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h2 style="font-weight: 800; color: var(--primary-maroon);">Action Metadata</h2>
                <i class="fas fa-times" style="cursor:pointer; color: var(--text-muted);" onclick="document.getElementById('metaModal').style.display='none'"></i>
            </div>
            <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 10px;">Detailed attributes captured for this event:</p>
            <pre id="metaContent"></pre>
        </div>
    </div>

    <script>
        function showMeta(btn) {
            try {
                const jsonStr = btn.getAttribute('data-meta');
                const data = JSON.parse(jsonStr);
                document.getElementById('metaContent').textContent = JSON.stringify(data, null, 4);
                document.getElementById('metaModal').style.display = 'flex';
            } catch (e) {
                console.error('JSON Parse Error:', e);
                alert('Invalid JSON data');
            }
        }
    </script>
</body>
</html>
