<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
if (Session::getRole() !== 'admin') {
    die("Access denied. Please log in as an administrator.");
}

function formatNumberIndian($num) {
    $num = (int)$num;
    $isNegative = $num < 0;
    $numStr = (string)abs($num);
    $len = strlen($numStr);
    if ($len <= 3) {
        return ($isNegative ? '-' : '') . $numStr;
    }
    $lastThree = substr($numStr, -3);
    $remaining = substr($numStr, 0, -3);
    $reversedRemaining = strrev($remaining);
    $chunks = str_split($reversedRemaining, 2);
    $formattedRemaining = strrev(implode(',', $chunks));
    return ($isNegative ? '-' : '') . $formattedRemaining . ',' . $lastThree;
}

$db = getDB();

require_once __DIR__ . '/../../src/Helpers/RedisHelper.php';
use App\Helpers\RedisHelper;

$redisHelper = RedisHelper::getInstance();
$cachedStats = $redisHelper->get('ai_monitor:aggregate_stats');

if ($cachedStats && is_array($cachedStats)) {
    $stats = $cachedStats['stats'];
    $totalCostStats = $cachedStats['totalCostStats'];
    $serviceStats = $cachedStats['serviceStats'];
} else {
    // Overall Stats (Separating Input and Output tokens for precise cost)
    $stats = $db->query("SELECT 
        COUNT(*) as total_requests,
        SUM(prompt_tokens) as total_prompt_tokens,
        SUM(completion_tokens) as total_completion_tokens,
        SUM(total_tokens) as total_tokens,
        AVG(latency_ms) as avg_latency,
        SUM(CASE WHEN status = 'failure' THEN 1 ELSE 0 END) as failures
    FROM ai_audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch(PDO::FETCH_ASSOC);

    // Total Cost Estimation
    $totalCostStats = $db->query("SELECT 
        SUM(prompt_tokens) as total_prompt_tokens,
        SUM(completion_tokens) as total_completion_tokens,
        SUM(total_tokens) as total_tokens
    FROM ai_audit_logs")->fetch(PDO::FETCH_ASSOC);

    // By Service
    $serviceStats = $db->query("SELECT 
        service_method, 
        COUNT(*) as count, 
        SUM(total_tokens) as tokens,
        AVG(latency_ms) as latency
    FROM ai_audit_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY service_method 
    ORDER BY tokens DESC")->fetchAll(PDO::FETCH_ASSOC);

    $redisHelper->set('ai_monitor:aggregate_stats', [
        'stats' => $stats,
        'totalCostStats' => $totalCostStats,
        'serviceStats' => $serviceStats
    ], 30); // cache for 30 seconds
}

// Cost Estimation (gpt-4o-mini: $0.15 / 1M input tokens, $0.60 / 1M output tokens)
$promptCost = (($stats['total_prompt_tokens'] ?? 0) / 1000) * 0.00015;
$completionCost = (($stats['total_completion_tokens'] ?? 0) / 1000) * 0.00060;
$estimatedCost = $promptCost + $completionCost;

$totalPromptCost = (($totalCostStats['total_prompt_tokens'] ?? 0) / 1000) * 0.00015;
$totalCompletionCost = (($totalCostStats['total_completion_tokens'] ?? 0) / 1000) * 0.00060;
$totalEstimatedCost = $totalPromptCost + $totalCompletionCost;

// Recent Logs with Pagination (20 per page)
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$totalRows = $db->query("SELECT COUNT(*) FROM ai_audit_logs")->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$recentLogs = $db->query("SELECT * FROM ai_audit_logs ORDER BY created_at DESC LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);

// Worker Health (Redis)
$redis = $redisHelper->getClient();
$workerPulses = $redis->hgetall('ai_workers_pulse');
$activeWorkers = [];
$now = time();
foreach ($workerPulses as $id => $time) {
    if ($now - $time < 300) { // Keep in list for 5 mins
        $activeWorkers[$id] = [
            'last_seen' => $time,
            'is_alive' => ($now - $time < 60),
            'memory' => $redis->hget('ai_workers_memory', $id) ?? 'N/A',
            'jobs' => $redis->hget('ai_workers_jobs', $id) ?? 0
        ];
    } else {
        $redis->hdel('ai_workers_pulse', $id);
        $redis->hdel('ai_workers_memory', $id);
        $redis->hdel('ai_workers_jobs', $id);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Performance Monitor - <?php echo APP_NAME; ?></title>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #1a1a1a;
            --accent-blue: #0066cc;
            --white: #ffffff;
            --bg-light: #f5f6f8;
            --text-dark: #2c3e50;
            --text-muted: #7f8c8d;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --border: #edf2f7;
            --success: #10b981;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .main-content {
            padding: 40px;
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            background: white;
            padding: 25px 35px;
            border-radius: 24px;
            box-shadow: var(--shadow);
        }

        .header-title h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--primary-maroon);
        }
        
        .header-title p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Metrics Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .metric-card {
            background: var(--white);
            padding: 28px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .metric-card:hover {
            transform: translateY(-5px);
        }

        .metric-card .label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .metric-card .value {
            font-size: 32px;
            font-weight: 800;
            color: var(--primary-dark);
        }

        .metric-card .trend {
            font-size: 13px;
            margin-top: 8px;
            color: var(--text-muted);
        }

        /* Tables */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 25px;
            margin-bottom: 40px;
        }

        .content-card {
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 16px 24px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-primary { background: #e0f2fe; color: #0369a1; }

        .usn-font {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            color: var(--primary-maroon);
        }

        @media (max-width: 1100px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 20px;
            border-top: 1px solid var(--border);
            background: #fafbfc;
        }

        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text-dark);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .pagination-btn:hover:not(.disabled) {
            border-color: var(--primary-maroon);
            color: var(--primary-maroon);
            background: #fff8f8;
        }

        .pagination-btn.active {
            background: var(--primary-maroon);
            color: var(--white);
            border-color: var(--primary-maroon);
        }

        .pagination-btn.disabled {
            color: var(--text-muted);
            background: var(--bg-light);
            cursor: not-allowed;
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="main-content">
        <div class="header-section">
            <div class="header-title">
                <h1>AI Performance Monitor</h1>
                <p>Telemetry for LAKSHYA's Parallel AI Infrastructure</p>
            </div>
            <div class="badge badge-primary" style="font-size: 13px; padding: 8px 16px;">
                <i class="fas fa-sync-alt fa-spin mr-2"></i> Live Telemetry
            </div>
        </div>

        <!-- Metrics Area -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="label">Total Requests</div>
                <div class="value"><?php echo formatNumberIndian($stats['total_requests']); ?></div>
                <div class="trend">Last 24 hours</div>
            </div>
            
            <div class="metric-card">
                <div class="label">Est. Cost (Total)</div>
                <div class="value">$<?php echo number_format($totalEstimatedCost, 4); ?></div>
                <div class="trend">Based on all-time token usage</div>
            </div>

            <div class="metric-card">
                <div class="label">Total Tokens Used</div>
                <div class="value"><?php echo formatNumberIndian($totalCostStats['total_tokens'] ?? (($totalCostStats['total_prompt_tokens'] ?? 0) + ($totalCostStats['total_completion_tokens'] ?? 0))); ?></div>
                <div class="trend">All-time token consumption</div>
            </div>

            <div class="metric-card">
                <div class="label">Parallel Workers</div>
                <div class="value"><?php 
                    echo count(array_filter($activeWorkers, function($w) { return $w['is_alive']; })); 
                ?></div>
                <div class="trend">Active background instances</div>
            </div>

            <div class="metric-card">
                <div class="label">System Failures</div>
                <div class="value" style="color: var(--danger);"><?php echo $stats['failures']; ?></div>
                <div class="trend">Critical errors (24h)</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Service Breakdown -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Service Usage (7D)</h2>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Calls</th>
                                <th>Tokens</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($serviceStats as $s): ?>
                            <tr>
                                <td><code style="color: var(--accent-blue);"><?php echo $s['service_method']; ?></code></td>
                                <td><strong><?php echo $s['count']; ?></strong></td>
                                <td><?php echo number_format($s['tokens']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Logs -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Live Request Stream</h2>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Method</th>
                                <th>Tokens</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td style="color: var(--text-muted);"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><?php echo $log['service_method']; ?></td>
                                <td><?php echo $log['total_tokens']; ?></td>
                                <td>
                                    <span class="badge <?php echo $log['status'] === 'success' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" <?php echo $page <= 1 ? 'onclick="return false;"' : ''; ?>>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<a href="?page=1" class="pagination-btn">1</a>';
                        if ($startPage > 2) {
                            echo '<span style="color: var(--text-muted); padding: 0 4px;">...</span>';
                        }
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $activeClass = $i === $page ? 'active' : '';
                        echo "<a href='?page=$i' class='pagination-btn $activeClass'>$i</a>";
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<span style="color: var(--text-muted); padding: 0 4px;">...</span>';
                        }
                        echo "<a href='?page=$totalPages' class='pagination-btn'>$totalPages</a>";
                    }
                    ?>
                    
                    <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" <?php echo $page >= $totalPages ? 'onclick="return false;"' : ''; ?>>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Worker Registry -->
        <div class="content-card">
            <div class="card-header">
                <h2>AI Worker Registry</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Worker Identifier</th>
                            <th>Job Load</th>
                            <th>Memory</th>
                            <th>Last Heartbeat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activeWorkers)): ?>
                            <tr><td colspan="5" class="text-center" style="padding: 40px; color: var(--text-muted);">No active workers detected.</td></tr>
                        <?php else: ?>
                            <?php foreach ($activeWorkers as $id => $w): ?>
                            <tr>
                                <td><code class="usn-font"><?php echo $id; ?></code></td>
                                <td><strong><?php echo $w['jobs']; ?></strong> jobs</td>
                                <td><span style="color: var(--text-muted);"><?php echo $w['memory']; ?></span></td>
                                <td><?php echo date('H:i:s', $w['last_seen']); ?> <small style="color: var(--text-muted);">(<?php echo time() - $w['last_seen']; ?>s ago)</small></td>
                                <td>
                                    <?php if ($w['is_alive']): ?>
                                        <span class="badge badge-success">Online</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Offline</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Global Maintenance Interceptor -->
    <script src="<?php echo APP_URL; ?>/js/maintenance_interceptor.js"></script>
</body>
</html>
