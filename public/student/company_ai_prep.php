<?php
/**
 * Company AI Prep - Student Portal
 * Guaranteed Clean Version
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
require_once __DIR__ . '/../../src/Models/Company.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$studentIdForDb = getStudentIdForAssessment();
$studentModel = new StudentProfile();
$companyModel = new Company();

// Fetch student profile using the helper model
$profile = $studentModel->getByUserId($userId);
$companies = $companyModel->getActiveCompanies();

// Fetch unified and legacy history (Unified + Prep + Technical Mock)
$db = getDB();
$inst = $_SESSION['institution'] ?? 'GMU'; // Get current institution

$sqlHistory = "(SELECT id, assessment_type COLLATE utf8mb4_general_ci as round_type, company_name COLLATE utf8mb4_general_ci as company_name, score as overall_score, started_at, 'unified' COLLATE utf8mb4_general_ci as type 
                FROM unified_ai_assessments 
                WHERE student_id = ? AND institution = ?)
               UNION ALL
               (SELECT id, round_type COLLATE utf8mb4_general_ci as round_type, company_name COLLATE utf8mb4_general_ci as company_name, overall_score, started_at, 'prep' COLLATE utf8mb4_general_ci as type 
                FROM ai_interview_sessions 
                WHERE student_id = ?)
               UNION ALL
               (SELECT id, role_name COLLATE utf8mb4_general_ci as round_type, 'Technical Mock' COLLATE utf8mb4_general_ci as company_name, overall_score, started_at, 'mock' COLLATE utf8mb4_general_ci as type 
                FROM mock_ai_interview_sessions 
                WHERE student_id = ? AND status = 'completed')
               ORDER BY started_at DESC LIMIT 15";
$stmtH = $db->prepare($sqlHistory);
$stmtH->execute([$studentIdForDb, $inst, $studentIdForDb, $studentIdForDb]);
$history = $stmtH->fetchAll();

// SGPA Evaluation Logic
$sgpa = $profile['sgpa'] ?? 0;
$semester = $profile['semester'] ?? 'N/A';

// If GMIT and using new SGPA system, fetch current from table
if ($_SESSION['institution'] === INSTITUTION_GMIT) {
    try {
        $stmtSgpa = $db->prepare("SELECT semester, sgpa FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1");
        // Using username as ID for GMIT map consistency with saveSGPA
        $stmtSgpa->execute([getUsername(), INSTITUTION_GMIT]); 
        $currentRec = $stmtSgpa->fetch();
        
        if ($currentRec) {
            $semester = $currentRec['semester'];
            $sgpa = $currentRec['sgpa'] > 0 ? $currentRec['sgpa'] : 0.00;
        } else {
             // Fallback: Get max semester
             $stmtMax = $db->prepare("SELECT MAX(semester) as max_sem FROM student_sem_sgpa WHERE student_id = ? AND institution = ?");
             $stmtMax->execute([getUsername(), INSTITUTION_GMIT]);
             $maxSem = $stmtMax->fetchColumn();
             if ($maxSem) {
                 $semester = $maxSem;
                 // Get SGPA for max sem
                 $stmtSgpa2 = $db->prepare("SELECT sgpa FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND semester = ?");
                 $stmtSgpa2->execute([getUsername(), INSTITUTION_GMIT, $maxSem]);
                 $sgpa = $stmtSgpa2->fetchColumn() ?: 0;
             }
        }
    } catch (Exception $e) {
        // Fallback to profile
    }
}
$evaluation = '';
$evalClass = '';

if ($sgpa >= 9.0) {
    $evaluation = 'Excellent! 🌟';
    $evalClass = 'excellent';
} elseif ($sgpa >= 8.0) {
    $evaluation = 'Very Good! 👍';
    $evalClass = 'very-good';
} elseif ($sgpa >= 7.0) {
    $evaluation = 'Good! Keep it up. 🙂';
    $evalClass = 'good';
} elseif ($sgpa >= 5.0) {
    $evaluation = 'Fair. You need to work harder. 📈';
    $evalClass = 'fair';
} else {
    $evaluation = 'Bad. Immediate focus required. ⚠️';
    $evalClass = 'bad';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company AI Prep - Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --secondary-gold: #f7f3b7;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --medium-gray: #e0e0e0;
            --dark-gray: #333333;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light-gray);
            color: var(--dark-gray);
            line-height: 1.6;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar h1 { font-size: 20px; }
        .navbar a {
            color: var(--white);
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            font-size: 14px;
        }

        .prep-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        .top-row {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 25px;
            margin-bottom: 40px;
        }

        .history-box, .academic-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #eee;
        }

        .academic-box {
            text-align: center;
            border-left: 5px solid var(--primary-maroon);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .status-badge {
            display: inline-block;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 15px;
            font-size: 1rem;
            width: 100%;
        }
        .excellent { background: #dcfce7; color: #166534; }
        .very-good { background: #dcfce7; color: #15803d; }
        .good { background: #fef9c3; color: #854d0e; }
        .fair { background: #ffedd5; color: #9a3412; }
        .bad { background: #fee2e2; color: #991b1b; }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .history-table th {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 2px solid var(--light-gray);
            color: #666;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .history-table td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--light-gray);
            font-size: 0.9rem;
        }
        .score-pill {
            padding: 4px 10px;
            border-radius: 12px;
            background: var(--primary-maroon);
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .company-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .company-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        .company-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: var(--primary-maroon);
        }
        .company-card h4 {
            color: var(--primary-maroon);
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        .rounds-section {
            display: none;
            margin-top: 40px;
            animation: fadeIn 0.5s ease-out;
        }
        .rounds-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }
        .round-card {
            background: white;
            padding: 40px 20px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .round-card:hover {
            border-color: var(--primary-maroon);
            background: #fffcfc;
            transform: scale(1.02);
        }
        .round-icon {
            font-size: 50px;
            margin-bottom: 20px;
            display: block;
        }
        .round-card h4 {
            margin-bottom: 10px;
            color: #333;
        }
        .round-card p {
            color: #666;
            font-size: 0.9rem;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .btn-back {
            display: inline-block;
            margin-top: 30px;
            padding: 10px 25px;
            border: 2px solid var(--primary-maroon);
            color: var(--primary-maroon);
            background: transparent;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-back:hover {
            background: var(--primary-maroon);
            color: white;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>🤖 Company AI Prep <sup>v2.2</sup></h1>
    <div>
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>
</div>

<div class="prep-container">
    <div class="top-row">
        <!-- Left: Interview History -->
        <!-- Left: Interview History (HIDDEN AS REQUESTED)
        <div class="history-box">
            <h3 style="color: var(--primary-maroon); margin-bottom: 15px;"><i class="fas fa-history"></i> Interview History</h3>
            <?php if (empty($history)): ?>
                <p style="color: #666; font-style: italic; margin-top: 20px;">No interview sessions found yet. Start your first prep below!</p>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Round / Role</th>
                            <th>Target</th>
                            <th>Score</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $session): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($session['round_type']); ?></strong></td>
                            <td><?php echo htmlspecialchars($session['company_name']); ?></td>
                            <td><span class="score-pill"><?php echo $session['overall_score'] ?? '--'; ?>%</span></td>
                            <td style="color: #888; font-size: 0.8rem;"><?php echo date('d M, Y', strtotime($session['started_at'])); ?></td>
                             <td>
                                <?php if ($session['type'] === 'mock'): ?>
                                    <a href="mock_ai_report?session_id=<?php echo $session['id']; ?>" class="score-pill" style="background: var(--primary-gold); color: black; font-size: 0.75rem; text-decoration: none;">View Report</a>
                                <?php elseif ($session['type'] === 'unified'): ?>
                                    <a href="aptitude_report?id=<?php echo $session['id']; ?>" class="score-pill" style="background: #e3fcef; color: #00875a; font-size: 0.75rem; text-decoration: none;">Results</a>
                                <?php else: ?>
                                    <span style="color: #ccc; font-size: 0.75rem;">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        -->

        <!-- Right: Academic Status Box
        <div class="academic-box">
            <h3 style="color: var(--primary-maroon); margin-bottom: 20px;">Academic Status</h3>
            <div style="text-align: left; width: 100%;">
                <p style="margin-bottom: 10px; font-size: 1.1rem;">Current Status: <strong>Semester <?php echo htmlspecialchars($semester); ?></strong></p>
                <p style="margin-bottom: 10px; font-size: 1.1rem;">Current SGPA: <strong style="color: var(--primary-maroon);"><?php echo number_format($sgpa, 2); ?></strong></p>
            </div>
            <div class="status-badge <?php echo $evalClass; ?>">
                <?php echo $evaluation; ?>
            </div>
        </div> -->
    </div>

    <div id="companySelection">
        <h3 style="margin-bottom: 25px; color: #444; border-bottom: 2px solid var(--medium-gray); padding-bottom: 10px;">Select a Company to Start Preparation</h3>
        <div class="company-grid">
            <?php if (empty($companies)): ?>
                <p>No active companies found in the database.</p>
            <?php else: ?>
                <?php foreach ($companies as $company): ?>
                <div class="company-card" onclick="showRounds('<?php echo addslashes($company['name']); ?>')">
                    <h4><?php echo htmlspecialchars($company['name']); ?></h4>
                    <p class="text-muted small"><?php echo htmlspecialchars($company['industry'] ?: 'MNC'); ?></p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="roundsSection" class="rounds-section">
        <h3 id="selectedCompanyTitle" style="margin-bottom: 30px; text-align: center; color: #333; background: #fff; padding: 15px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">Preparation Rounds</h3>
        <div class="rounds-grid">
            <div class="round-card" onclick="startAptitude()">
                <span class="round-icon">📝</span>
                <h4>Aptitude Round</h4>
                <p>Sharpen your Logical, Quant & Verbal reasoning skills.</p>
            </div>
            <div class="round-card" onclick="startInterview('Technical')">
                <span class="round-icon">💻</span>
                <h4>Technical Round</h4>
                <p>Practice Coding challenges & Core Domain concepts.</p>
            </div>
            <div class="round-card" onclick="startInterview('HR')">
                <span class="round-icon">🤝</span>
                <h4>HR Round</h4>
                <p>Master Behavioral questions & Cultural alignment.</p>
            </div>
        </div>
        <div style="text-align: center;">
            <button onclick="backToCompanies()" class="btn-back">Change Company Selection</button>
        </div>
    </div>
</div>

<script>
    let selectedCompany = "";

    function showRounds(companyName) {
        selectedCompany = companyName;
        document.getElementById('companySelection').style.display = 'none';
        document.getElementById('roundsSection').style.display = 'block';
        document.getElementById('selectedCompanyTitle').innerText = 'Preparation for: ' + companyName;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function backToCompanies() {
        document.getElementById('companySelection').style.display = 'block';
        document.getElementById('roundsSection').style.display = 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function startAptitude() {
        if (!selectedCompany) return;
        window.open('ai_aptitude_test?company=' + encodeURIComponent(selectedCompany), '_blank');
    }

    function startInterview(type) {
        if (!selectedCompany) return;
        if (type === 'Technical') {
            window.location.href = 'ai_technical_round?company=' + encodeURIComponent(selectedCompany);
        } else if (type === 'HR') {
            window.location.href = 'ai_hr_round?company=' + encodeURIComponent(selectedCompany);
        } else {
            window.location.href = 'mock_ai_interview?company=' + encodeURIComponent(selectedCompany) + '&type=' + type;
        }
    }
</script>

</body>
</html>

