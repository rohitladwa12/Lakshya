<?php
/**
 * Dynamic print-to-PDF cohort report generator
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/Logger.php';

// Force admin authentication
requireRole(ROLE_ADMIN);

$db = getDB();
$gmit = getDB('gmit');
$gmu = getDB('gmu');

// 1. Resolve GET parameters for filtered cohort matching
$selectedDeptInst = isset($_GET['dept_inst']) ? trim($_GET['dept_inst']) : 'ALL';
$selectedDeptDisc = isset($_GET['dept_disc']) ? trim($_GET['dept_disc']) : 'ALL';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');

// 2. Fetch senior student mapping USNs
$gmitSemUsns = $db->query("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE semester IN (5, 6, 7, 8) AND is_current = 1")->fetchAll(PDO::FETCH_COLUMN);

// Fetch local login count mapping
$stmt = $db->prepare("SELECT user_id, COUNT(*) as count FROM activity_logs WHERE action = 'login' AND DATE(created_at) >= :start AND DATE(created_at) <= :end GROUP BY user_id");
$stmt->execute([':start' => $startDate, ':end' => $endDate]);
$loginCounts = [];
while ($row = $stmt->fetch()) {
    $loginCounts[$row['user_id']] = (int)$row['count'];
}

$deptStats = [];
$allStudents = [];

// GMIT cohort details mapping
if ($gmit && !empty($gmitSemUsns) && ($selectedDeptInst === 'ALL' || $selectedDeptInst === 'GMIT')) {
    $chunks = array_chunk($gmitSemUsns, 500);
    foreach ($chunks as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $sql = "SELECT DISTINCT usn, name, discipline FROM ad_student_details WHERE usn IN ($placeholders)";
        if ($selectedDeptDisc !== 'ALL') {
            $sql .= " AND UPPER(discipline) = " . $gmit->quote(strtoupper($selectedDeptDisc));
        }
        $stmtGmit = $gmit->prepare($sql);
        $stmtGmit->execute($chunk);
        while ($row = $stmtGmit->fetch()) {
            $usn = trim($row['usn']);
            $disc = strtoupper(trim($row['discipline'] ?: 'General'));
            if ($disc === '') $disc = 'General';

            $key = "GMIT | " . $disc;
            if (!isset($deptStats[$key])) {
                $deptStats[$key] = ['inst' => 'GMIT', 'dept' => $disc, 'total' => 0, 'active' => 0, 'inactive' => 0];
            }
            $deptStats[$key]['total']++;

            $logCount = $loginCounts[$usn] ?? 0;
            if ($logCount > 0) {
                $deptStats[$key]['active']++;
            } else {
                $deptStats[$key]['inactive']++;
            }

            $allStudents[] = [
                'name' => trim($row['name']),
                'usn' => $usn,
                'discipline' => $disc,
                'institution' => 'GMIT',
                'logins' => $logCount
            ];
        }
    }
}

// GMU cohort details mapping
if ($gmu && ($selectedDeptInst === 'ALL' || $selectedDeptInst === 'GMU')) {
    $sqlGmu = "SELECT DISTINCT usn, name, discipline FROM ad_student_approved WHERE sem IN (5, 6, 7, 8)";
    if ($selectedDeptDisc !== 'ALL') {
        $sqlGmu .= " AND UPPER(discipline) = " . $gmu->quote(strtoupper($selectedDeptDisc));
    }
    $stmtGmu = $gmu->query($sqlGmu);
    while ($row = $stmtGmu->fetch()) {
        $usn = trim($row['usn']);
        $disc = strtoupper(trim($row['discipline'] ?: 'General'));
        if ($disc === '') $disc = 'General';

        $key = "GMU | " . $disc;
        if (!isset($deptStats[$key])) {
            $deptStats[$key] = ['inst' => 'GMU', 'dept' => $disc, 'total' => 0, 'active' => 0, 'inactive' => 0];
        }
        $deptStats[$key]['total']++;

        $logCount = $loginCounts[$usn] ?? 0;
        if ($logCount > 0) {
            $deptStats[$key]['active']++;
        } else {
            $deptStats[$key]['inactive']++;
        }

        $allStudents[] = [
            'name' => trim($row['name']),
            'usn' => $usn,
            'discipline' => $disc,
            'institution' => 'GMU',
            'logins' => $logCount
        ];
    }
}

// Alphabetically sort the breakdown keys
ksort($deptStats);

// Unify student records to prevent duplicates
$uniqueStudents = [];
$processed = [];
foreach ($allStudents as $st) {
    if (!isset($processed[$st['usn']])) {
        $processed[$st['usn']] = true;
        $uniqueStudents[] = $st;
    }
}

// Sort the list of students by login count descending
usort($uniqueStudents, function($a, $b) {
    return $b['logins'] <=> $a['logins'];
});

// Calculate totals for executive summary inside PDF
$totalCohortCount = count($uniqueStudents);
$totalActiveCohort = 0;
foreach ($uniqueStudents as $st) {
    if ($st['logins'] > 0) $totalActiveCohort++;
}
$totalInactiveCohort = $totalCohortCount - $totalActiveCohort;
$cohortEngagementRate = $totalCohortCount > 0 ? round(($totalActiveCohort / $totalCohortCount) * 100, 1) : 0;

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cohort Analytics Report - <?php echo htmlspecialchars($selectedDeptInst . ' | ' . $selectedDeptDisc); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @page {
            size: A4;
            margin: 12mm 15mm;
        }
        @media print {
            body {
                background: white;
                color: #000;
                font-size: 10pt;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
            .keep-together {
                break-inside: avoid;
            }
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            color: #2b3674;
            background: #fafbfe;
            padding: 20px;
            line-height: 1.4;
        }

        .report-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .no-print-bar {
            display: flex;
            justify-content: space-between;
            background: #e9edf7;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            align-items: center;
        }

        .print-btn {
            background: #800000;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Outfit';
        }

        .print-btn:hover {
            opacity: 0.9;
        }

        /* Report Header Styling */
        .report-header {
            border-bottom: 3px double #800000;
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            font-size: 20pt;
            font-weight: 800;
            color: #800000;
            letter-spacing: -0.5px;
        }

        .header-title p {
            font-size: 10pt;
            color: #a3aed1;
            margin-top: 3px;
        }

        .metadata-box {
            text-align: right;
            font-size: 9pt;
            color: #718096;
        }

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .summary-card {
            border: 1px solid #e2e8f0;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
        }

        .summary-card h3 {
            font-size: 8pt;
            text-transform: uppercase;
            color: #a3aed1;
            letter-spacing: 0.5px;
        }

        .summary-card .value {
            font-size: 18pt;
            font-weight: 800;
            color: #2b3674;
            margin-top: 5px;
        }

        .summary-card .subtext {
            font-size: 8pt;
            color: #05cd99;
            margin-top: 3px;
            font-weight: 600;
        }

        /* Subtitle Section */
        .section-title {
            font-size: 12pt;
            font-weight: 800;
            color: #800000;
            border-bottom: 1.5px solid #ccc;
            padding-bottom: 5px;
            margin-top: 25px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        th {
            background: #f7fafc;
            color: #718096;
            font-weight: 700;
            font-size: 8.5pt;
            text-transform: uppercase;
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #edf2f7;
            font-size: 9pt;
            color: #2b3674;
        }

        tr:hover td {
            background: #fafcfd;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }

        .badge-success { background: #e2f9f2; color: #05cd99; }
        .badge-inactive { background: #f4f7fe; color: #a3aed1; }

        .signature-block {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            font-size: 9pt;
            color: #718096;
        }

        .sig-line {
            width: 150px;
            border-top: 1px solid #718096;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Interactive controls visible ONLY on screen -->
        <div class="no-print-bar no-print">
            <span style="font-size:13px; font-weight:700; color:#2b3674;">
                📄 Ready to generate PDF. Print dialog will trigger automatically.
            </span>
            <div style="display:flex; gap:10px;">
                <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print to PDF</button>
                <button class="print-btn" style="background:#4a5568;" onclick="window.close()">Close Window</button>
            </div>
        </div>

        <!-- Official Report Header -->
        <header class="report-header">
            <div class="header-title">
                <h1>Platform Cohort Engagement Report</h1>
                <p>Lakshya portal administrative monitoring, analytics and registry log audit</p>
            </div>
            <div class="metadata-box">
                <div><strong>Date Generated:</strong> <?php echo date('d M Y, H:i:s'); ?></div>
                <div><strong>Admin Scope:</strong> <?php echo htmlspecialchars($fullName); ?></div>
                <div><strong>Date Range:</strong> <?php echo htmlspecialchars($startDate) . ' to ' . htmlspecialchars($endDate); ?></div>
                <div><strong>Cohort Filter:</strong> <?php echo htmlspecialchars($selectedDeptInst . ' | ' . $selectedDeptDisc); ?></div>
            </div>
        </header>

        <!-- Executive Analytics Metrics -->
        <div class="summary-grid">
            <div class="summary-card">
                <h3>Total Registered</h3>
                <div class="value"><?php echo number_format($totalCohortCount); ?></div>
                <div class="subtext" style="color:#2b3674;">Students Matched</div>
            </div>
            <div class="summary-card">
                <h3>Active Logged In</h3>
                <div class="value" style="color:#05cd99;"><?php echo number_format($totalActiveCohort); ?></div>
                <div class="subtext">Active on portal</div>
            </div>
            <div class="summary-card">
                <h3>Never Logged In</h3>
                <div class="value" style="color:#ff9920;"><?php echo number_format($totalInactiveCohort); ?></div>
                <div class="subtext" style="color:#ff9920;">Pending onboarding</div>
            </div>
            <div class="summary-card">
                <h3>Engagement Rate</h3>
                <div class="value" style="color:#800000;"><?php echo $cohortEngagementRate; ?>%</div>
                <div class="subtext" style="color:#800000;">Participation KPI</div>
            </div>
        </div>

        <!-- Chart visualization before tables -->
        <div class="keep-together" style="margin-bottom: 30px;">
            <div class="section-title">Cohort Distribution Representation</div>
            <div style="height: 160px; max-width: 500px; margin: 0 auto;">
                <canvas id="pdfChart"></canvas>
            </div>
        </div>

        <!-- Cohort Summary Table -->
        <div class="keep-together">
            <div class="section-title">Department Cohort Statistics</div>
            <table>
                <thead>
                    <tr>
                        <th>Institution</th>
                        <th>Department / Discipline</th>
                        <th style="text-align:right;">Total Students</th>
                        <th style="text-align:right; color:#05cd99;">Logged In</th>
                        <th style="text-align:right; color:#ff9920;">Never Logged In</th>
                        <th style="text-align:right;">Engagement Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deptStats as $stats): ?>
                        <?php $rate = $stats['total'] > 0 ? round(($stats['active'] / $stats['total']) * 100, 1) : 0; ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($stats['inst']); ?></strong></td>
                            <td><?php echo htmlspecialchars($stats['dept']); ?></td>
                            <td style="text-align:right;"><?php echo number_format($stats['total']); ?></td>
                            <td style="text-align:right; color:#05cd99; font-weight:700;"><?php echo number_format($stats['active']); ?></td>
                            <td style="text-align:right; color:#ff9920; font-weight:700;"><?php echo number_format($stats['inactive']); ?></td>
                            <td style="text-align:right; font-weight:700;"><?php echo $rate; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Detailed Student Roster (Literally Everything in Detail) -->
        <div class="page-break">
            <div class="section-title">Detailed Student Engagement Roster (Full Cohort Log)</div>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>USN / ID</th>
                        <th>Institution</th>
                        <th>Department</th>
                        <th style="text-align:right;">Logins Detected</th>
                        <th style="text-align:center;">Onboard Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uniqueStudents as $st): ?>
                        <?php 
                            $badge = $st['logins'] > 0 ? 'badge-success' : 'badge-inactive';
                            $status = $st['logins'] > 0 ? 'Active' : 'Never Logged In';
                        ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($st['name']); ?></td>
                            <td><strong style="color:#800000;"><?php echo htmlspecialchars($st['usn']); ?></strong></td>
                            <td><?php echo htmlspecialchars($st['institution']); ?></td>
                            <td><?php echo htmlspecialchars($st['discipline']); ?></td>
                            <td style="text-align:right; font-weight:700;"><?php echo $st['logins']; ?></td>
                            <td style="text-align:center;">
                                <span class="badge <?php echo $badge; ?>"><?php echo $status; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($uniqueStudents)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 25px; color:#a3aed1;">
                                No student records match the selected cohort filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Signature Authorization Blocks -->
        <div class="signature-block keep-together">
            <div>
                <div class="sig-line"></div>
                <div>Authorized Administrator</div>
                <div style="font-size:7pt; color:#a3aed1;"><?php echo htmlspecialchars($fullName); ?></div>
            </div>
            <div>
                <div class="sig-line"></div>
                <div>System Operations Officer</div>
                <div style="font-size:7pt; color:#a3aed1;">Lakshya Analytics Engine</div>
            </div>
        </div>
    </div>

    <script>
        // Render beautiful bar chart inside printed PDF
        const ctx = document.getElementById('pdfChart').getContext('2d');
        const pdfChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Logged In', 'Never Logged In'],
                datasets: [{
                    label: 'Cohort Distribution',
                    data: [<?php echo $totalActiveCohort; ?>, <?php echo $totalInactiveCohort; ?>],
                    backgroundColor: ['#05cd99', '#ff9920'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Automatically trigger print dialog 300ms after drawing completes!
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 350);
        };
    </script>
</body>
</html>
