<?php
/**
 * MS Report - Officer Folder Performance Dashboard
 * Visual loading time report for all files in public/officer/
 */
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_PLACEMENT_OFFICER);

$reportDate  = date('d M Y, h:i A');
$jsonFile    = __DIR__ . '/ms_report_data.json';
$rawData     = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

// ── Stats ──────────────────────────────────────────────────────────────────
$timed   = array_filter($rawData, fn($r) => is_numeric($r['load_time_ms']) && $r['load_time_ms'] > 0);
$handlers = array_filter($rawData, fn($r) => in_array($r['rating'], ['HANDLER', 'REDIRECT']));
$skipped = array_filter($rawData, fn($r) => $r['rating'] === 'SKIP');
$pages   = array_filter($timed,   fn($r) => $r['category'] === 'Page / View');

$allMs  = array_column($timed, 'load_time_ms');
$avgMs  = $allMs ? round(array_sum($allMs) / count($allMs), 1) : 0;
$maxMs  = $allMs ? max($allMs) : 0;
$minMs  = $allMs ? min($allMs) : 0;
$slowestFile = '';
$fastestFile = '';
foreach ($timed as $r) {
    if ($r['load_time_ms'] == $maxMs) $slowestFile = $r['file'];
    if ($r['load_time_ms'] == $minMs) $fastestFile = $r['file'];
}

$excellentCount = count(array_filter($timed, fn($r) => $r['rating'] === 'EXCELLENT'));
$goodCount      = count(array_filter($timed, fn($r) => $r['rating'] === 'GOOD'));
$moderateCount  = count(array_filter($timed, fn($r) => $r['rating'] === 'MODERATE'));
$slowCount      = count(array_filter($timed, fn($r) => $r['rating'] === 'SLOW'));

// Rating helpers
function ratingBadge(string $rating): string {
    $map = [
        'EXCELLENT' => ['bg:#16a34a', '⚡ EXCELLENT'],
        'GOOD'      => ['bg:#2563eb', '✅ GOOD'],
        'MODERATE'  => ['bg:#d97706', '⚠️ MODERATE'],
        'SLOW'      => ['bg:#dc2626', '🐢 SLOW'],
        'HANDLER'   => ['bg:#6b7280', '📤 HANDLER'],
        'REDIRECT'  => ['bg:#7c3aed', '↪️ REDIRECT'],
        'SKIP'      => ['bg:#9ca3af', '⏭ SKIP'],
    ];
    [$bg, $label] = $map[$rating] ?? ['bg:#9ca3af', $rating];
    return "<span class=\"badge\" style=\"{$bg}\">{$label}</span>";
}

function catBadge(string $cat): string {
    $map = [
        'Page / View'    => '#0f172a',
        'Action Handler' => '#b45309',
        'API / AJAX'     => '#0369a1',
        'Redirect Router'=> '#6d28d9',
    ];
    $bg = $map[$cat] ?? '#374151';
    return "<span class=\"cat-badge\" style=\"background:{$bg}\">{$cat}</span>";
}

function msBar(float $ms, float $maxMs): string {
    $pct = $maxMs > 0 ? min(100, ($ms / $maxMs) * 100) : 0;
    $color = $ms < 100 ? '#16a34a' : ($ms < 300 ? '#2563eb' : ($ms < 800 ? '#d97706' : '#dc2626'));
    return "<div class='bar-wrap'><div class='bar' style='width:{$pct}%;background:{$color}'></div><span class='bar-label'>{$ms} ms</span></div>";
}

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MS Report — Officer Folder</title>
<style>
/* ── Reset & Base ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;padding:0}
a{color:inherit;text-decoration:none}

/* ── Layout ── */
.page-wrap{max-width:1400px;margin:0 auto;padding:0 20px 60px}

/* ── Header ── */
.top-bar{background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);border-bottom:1px solid #334155;padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;backdrop-filter:blur(8px)}
.top-bar-left{display:flex;align-items:center;gap:12px}
.top-bar-icon{font-size:28px;line-height:1}
.top-bar-title{font-size:20px;font-weight:700;color:#f8fafc;letter-spacing:-.3px}
.top-bar-sub{font-size:13px;color:#94a3b8;margin-top:2px}
.top-bar-right{display:flex;align-items:center;gap:16px}
.back-btn{background:#1e293b;border:1px solid #334155;color:#94a3b8;padding:7px 14px;border-radius:8px;font-size:13px;cursor:pointer;transition:.2s;display:flex;align-items:center;gap:6px}
.back-btn:hover{background:#334155;color:#f8fafc}
.report-date{font-size:12px;color:#64748b}

/* ── KPI Cards ── */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;padding:28px 0 8px}
.kpi-card{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:20px;position:relative;overflow:hidden;transition:.2s}
.kpi-card:hover{border-color:#475569;transform:translateY(-2px)}
.kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0}
.kpi-card.blue::before{background:linear-gradient(90deg,#2563eb,#3b82f6)}
.kpi-card.green::before{background:linear-gradient(90deg,#16a34a,#22c55e)}
.kpi-card.orange::before{background:linear-gradient(90deg,#d97706,#f59e0b)}
.kpi-card.red::before{background:linear-gradient(90deg,#dc2626,#ef4444)}
.kpi-card.purple::before{background:linear-gradient(90deg,#7c3aed,#a855f7)}
.kpi-card.gray::before{background:linear-gradient(90deg,#475569,#64748b)}
.kpi-icon{font-size:28px;margin-bottom:10px}
.kpi-value{font-size:32px;font-weight:800;color:#f8fafc;line-height:1;margin-bottom:4px}
.kpi-label{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;font-weight:600}
.kpi-sub{font-size:11px;color:#475569;margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* ── Optimization Banner ── */
.opt-banner{background:linear-gradient(135deg,#064e3b 0%,#065f46 100%);border:1px solid #059669;border-radius:12px;padding:16px 20px;margin:16px 0 24px;display:flex;align-items:flex-start;gap:14px}
.opt-banner-icon{font-size:24px;flex-shrink:0;margin-top:2px}
.opt-banner-title{font-size:15px;font-weight:700;color:#6ee7b7;margin-bottom:4px}
.opt-banner-text{font-size:13px;color:#a7f3d0;line-height:1.5}
.opt-items{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.opt-chip{background:#047857;border:1px solid #059669;border-radius:20px;padding:4px 12px;font-size:12px;color:#d1fae5;font-weight:600}

/* ── Section Headers ── */
.section-header{display:flex;align-items:center;gap:10px;margin:28px 0 14px}
.section-header h2{font-size:18px;font-weight:700;color:#f1f5f9}
.section-pill{background:#1e293b;border:1px solid #334155;border-radius:20px;padding:3px 10px;font-size:12px;color:#64748b}

/* ── Table ── */
.table-wrap{background:#1e293b;border:1px solid #334155;border-radius:14px;overflow:hidden}
.perf-table{width:100%;border-collapse:collapse}
.perf-table thead{background:#0f172a}
.perf-table th{padding:12px 16px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;text-align:left;white-space:nowrap;border-bottom:1px solid #334155}
.perf-table td{padding:11px 16px;font-size:13px;color:#cbd5e1;border-bottom:1px solid #1e293b;vertical-align:middle}
.perf-table tr:last-child td{border-bottom:none}
.perf-table tr:hover td{background:#263044}
.file-name{font-family:'Cascadia Code','Fira Code',monospace;font-size:12.5px;color:#93c5fd;font-weight:600}
.file-note{font-size:11px;color:#64748b;margin-top:3px;font-style:italic}
.na-cell{color:#374151;font-style:italic;font-size:12px}

/* ── Progress Bar ── */
.bar-wrap{display:flex;align-items:center;gap:10px;min-width:180px}
.bar{height:8px;border-radius:4px;min-width:2px;transition:width .3s}
.bar-label{font-size:12px;color:#94a3b8;white-space:nowrap;min-width:60px;font-family:monospace}

/* ── Badges ── */
.badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;white-space:nowrap}
.cat-badge{display:inline-block;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;color:#fff;white-space:nowrap}

/* ── Legend ── */
.legend-row{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0 24px;align-items:center}
.legend-item{display:flex;align-items:center;gap:6px;font-size:12px;color:#64748b}
.legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}

/* ── Footer ── */
.footer{text-align:center;padding:24px;font-size:12px;color:#334155;border-top:1px solid #1e293b;margin-top:40px}

/* ── Responsive ── */
@media(max-width:768px){
    .kpi-grid{grid-template-columns:repeat(2,1fr)}
    .perf-table th:nth-child(4),.perf-table td:nth-child(4),.perf-table th:nth-child(5),.perf-table td:nth-child(5){display:none}
    .top-bar{padding:12px 16px}
}
</style>
</head>
<body>

<!-- Top Bar -->
<div class="top-bar">
    <div class="top-bar-left">
        <div class="top-bar-icon">📊</div>
        <div>
            <div class="top-bar-title">MS Report — Officer Folder</div>
            <div class="top-bar-sub">Performance loading time analysis · All 25 PHP files</div>
        </div>
    </div>
    <div class="top-bar-right">
        <div class="report-date">Generated: <?= htmlspecialchars($reportDate) ?> &nbsp;|&nbsp; <?= htmlspecialchars($fullName) ?></div>
        <a href="dashboard.php" class="back-btn">← Dashboard</a>
    </div>
</div>

<div class="page-wrap">

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card blue">
        <div class="kpi-icon">📁</div>
        <div class="kpi-value"><?= count($rawData) ?></div>
        <div class="kpi-label">Total Files</div>
        <div class="kpi-sub"><?= count($timed) ?> measured · <?= count($handlers) ?> handlers</div>
    </div>
    <div class="kpi-card green">
        <div class="kpi-icon">⚡</div>
        <div class="kpi-value"><?= $avgMs ?> <small style="font-size:16px">ms</small></div>
        <div class="kpi-label">Avg Load Time</div>
        <div class="kpi-sub">Across <?= count($timed) ?> benchmarked pages</div>
    </div>
    <div class="kpi-card green">
        <div class="kpi-icon">🚀</div>
        <div class="kpi-value"><?= $minMs ?> <small style="font-size:16px">ms</small></div>
        <div class="kpi-label">Fastest</div>
        <div class="kpi-sub"><?= htmlspecialchars($fastestFile) ?></div>
    </div>
    <div class="kpi-card orange">
        <div class="kpi-icon">🐢</div>
        <div class="kpi-value"><?= $maxMs ?> <small style="font-size:16px">ms</small></div>
        <div class="kpi-label">Slowest</div>
        <div class="kpi-sub"><?= htmlspecialchars($slowestFile) ?></div>
    </div>
    <div class="kpi-card green">
        <div class="kpi-icon">✅</div>
        <div class="kpi-value"><?= $excellentCount ?></div>
        <div class="kpi-label">Excellent (&lt;100ms)</div>
        <div class="kpi-sub"><?= $goodCount ?> GOOD · <?= $moderateCount ?> MODERATE</div>
    </div>
    <div class="kpi-card purple">
        <div class="kpi-icon">📤</div>
        <div class="kpi-value"><?= count($handlers) ?></div>
        <div class="kpi-label">Handlers / Routers</div>
        <div class="kpi-sub">POST-only · no render time</div>
    </div>
</div>

<!-- Optimization Banner -->
<div class="opt-banner">
    <div class="opt-banner-icon">🎯</div>
    <div>
        <div class="opt-banner-title">Major Optimizations Applied This Session</div>
        <div class="opt-banner-text">Three critical performance bottlenecks were identified and resolved, delivering massive speedups:</div>
        <div class="opt-items">
            <span class="opt-chip">applications.php: 2,010ms → 249ms (8× faster)</span>
            <span class="opt-chip">nqt_analytics.php: 1,562ms → 14ms (111× faster)</span>
            <span class="opt-chip">interviews.php: Fatal SQL error → 15ms (fixed)</span>
        </div>
    </div>
</div>

<!-- Legend -->
<div class="legend-row">
    <span style="font-size:12px;color:#94a3b8;font-weight:600;margin-right:4px">Rating:</span>
    <span class="legend-item"><span class="legend-dot" style="background:#16a34a"></span> Excellent &lt;100ms</span>
    <span class="legend-item"><span class="legend-dot" style="background:#2563eb"></span> Good 100–300ms</span>
    <span class="legend-item"><span class="legend-dot" style="background:#d97706"></span> Moderate 300–800ms</span>
    <span class="legend-item"><span class="legend-dot" style="background:#dc2626"></span> Slow &gt;800ms</span>
    <span class="legend-item"><span class="legend-dot" style="background:#6b7280"></span> Handler / No render</span>
</div>

<!-- Table -->
<div class="section-header">
    <h2>📋 All Files — Detailed Report</h2>
    <span class="section-pill"><?= count($rawData) ?> total files</span>
</div>

<div class="table-wrap">
<table class="perf-table">
<thead>
<tr>
    <th>#</th>
    <th>File Name</th>
    <th>Category</th>
    <th>Avg Load Time</th>
    <th>Peak Memory</th>
    <th>Output Size</th>
    <th>Rating</th>
</tr>
</thead>
<tbody>
<?php
$i = 1;
foreach ($rawData as $row):
    $ms  = $row['load_time_ms'];
    $mem = $row['memory_mb'];
    $sz  = $row['size_kb'];
    $note = $row['note'] ?? '';
?>
<tr>
    <td style="color:#475569;font-size:12px"><?= $i++ ?></td>
    <td>
        <div class="file-name"><?= htmlspecialchars($row['file']) ?></div>
        <?php if ($note): ?><div class="file-note"><?= htmlspecialchars($note) ?></div><?php endif; ?>
    </td>
    <td><?= catBadge($row['category']) ?></td>
    <td>
        <?php if (is_numeric($ms) && $ms > 0): ?>
            <?= msBar((float)$ms, (float)$maxMs) ?>
        <?php else: ?>
            <span class="na-cell">— N/A</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if (is_numeric($mem) && $mem > 0): ?>
            <span style="color:#94a3b8;font-family:monospace;font-size:12px"><?= $mem ?> MB</span>
        <?php else: ?>
            <span class="na-cell">—</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if (is_numeric($sz) && $sz > 0): ?>
            <span style="color:#94a3b8;font-family:monospace;font-size:12px"><?= $sz ?> KB</span>
        <?php else: ?>
            <span class="na-cell">—</span>
        <?php endif; ?>
    </td>
    <td><?= ratingBadge($row['rating']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- Breakdown by category -->
<div class="section-header" style="margin-top:36px">
    <h2>📌 Notes & Legend</h2>
</div>
<div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px 24px;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;font-size:13px;color:#94a3b8;line-height:1.6">
    <div>
        <div style="color:#f1f5f9;font-weight:700;margin-bottom:6px">📄 Page / View</div>
        Fully rendered HTML pages. Timing covers PHP execution + all DB queries. Benchmarked with 3 runs averaged.
    </div>
    <div>
        <div style="color:#f1f5f9;font-weight:700;margin-bottom:6px">📤 Action Handler</div>
        POST-only form processors (e.g. <code>job_handler.php</code>). No render output — only processes form data and redirects. Not benchmarked.
    </div>
    <div>
        <div style="color:#f1f5f9;font-weight:700;margin-bottom:6px">🔗 API / AJAX</div>
        JSON endpoints called via JavaScript. Require POST body — not applicable for GET benchmarking.
    </div>
    <div>
        <div style="color:#f1f5f9;font-weight:700;margin-bottom:6px">↪️ Redirect Router</div>
        Files that immediately issue HTTP 302 redirects (e.g. <code>students.php</code>). No page render — timing not applicable.
    </div>
    <div>
        <div style="color:#f1f5f9;font-weight:700;margin-bottom:6px">⏭ SKIP</div>
        Parameter-dependent pages requiring valid IDs (e.g. <code>drive_details.php</code>) where no live data exists to test with.
    </div>
    <div>
        <div style="color:#f1f5f9;font-weight:700;margin-bottom:6px">⚡ Benchmark Method</div>
        Each page loaded in an isolated PHP subprocess with session mocked as <em>Placement Officer</em>. 3 warm runs averaged. Memory = peak_usage(true).
    </div>
</div>

</div><!-- /page-wrap -->

<div class="footer">
    MS Report · Lakshya Placement Portal · Officer Folder · Generated <?= htmlspecialchars($reportDate) ?>
</div>

</body>
</html>
