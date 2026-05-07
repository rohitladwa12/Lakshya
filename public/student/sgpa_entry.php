a<?php
/**
 * Mandatory SGPA Entry for GMIT Students
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$username = getUsername();
$fullName = getFullName();
$institution = $_SESSION['institution'] ?? '';

// Only for GMIT
if ($institution !== INSTITUTION_GMIT) {
    redirect('dashboard');
}

require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$studentProfileModel = new StudentProfile();

// Check if SGPA is frozen (reads from student_sem_sgpa.freezed column)
$db = getDB();
$stmtFreeze = $db->prepare("SELECT MAX(freezed) FROM student_sem_sgpa WHERE student_id = ? AND institution = ?");
$stmtFreeze->execute([$username, $institution]);
$isFrozen = (int)$stmtFreeze->fetchColumn() === 1;

// Handle form submission
$success = '';
$error = '';
if (isPost() && !$isFrozen) {
    $semData = [];
    for ($i = 1; $i <= 8; $i++) {
        $val = post('sem' . $i);
            if ($val !== '' && $val !== null) {
                $semData[$i] = (float)$val;
            }
        }
        
        $currentSem = post('current_sem');
        
        if (!$currentSem) {
            $error = "Please select your current semester.";
        } else {
            // Check for gaps
            $missingSems = [];
            for ($i = 1; $i < $currentSem; $i++) {
                if (!isset($semData[$i])) {
                    $missingSems[] = $i;
                }
            }

            if (!empty($missingSems)) {
                $error = "Please enter SGPA for all completed semesters (Missing: " . implode(', ', $missingSems) . ").";
            } elseif (empty($semData) && $currentSem > 1) {
                $error = "Please enter at least one semester SGPA.";
            } else {
                if ($studentProfileModel->saveSGPA($username, INSTITUTION_GMIT, $semData, $currentSem)) {
                    Session::flash('success', 'Academic history updated successfully.');
                    redirect('dashboard');
                } else {
                    $error = "Failed to save data. Please try again.";
                }
            }
        }
    } elseif (isPost() && $isFrozen) {
        $error = "Your SGPA has been frozen by the coordinator and cannot be updated.";
    }

    // Check current data directly from DB to ensure consistency with saveSGPA
    $db = getDB();
    $stmt = $db->prepare("SELECT semester, sgpa, is_current FROM student_sem_sgpa WHERE student_id = ? AND institution = ?");
    $stmt->execute([trim((string)$username), INSTITUTION_GMIT]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $currentSgpas = [];
    $activeSem = null;

    foreach ($records as $r) {
        // Populate SGPA if exists
        if ($r['sgpa'] > 0) {
            $currentSgpas[$r['semester']] = $r['sgpa'];
        }
        // Check active semester
        if ($r['is_current'] == 1) {
            $activeSem = $r['semester'];
        }
    }

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Academic History - LAKSHYA</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #4a0000;
            --accent-gold: #D4AF37;
            --light-gold: #f9f3d8;
            --white: #ffffff;
            --bg-light: #fdfdfd;
            --text-main: #2d3436;
            --text-muted: #636e72;
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #fdfdfd 0%, #fff5f5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--text-main);
            background-image: 
                radial-gradient(at 0% 0%, rgba(128, 0, 0, 0.03) 0, transparent 50%), 
                radial-gradient(at 100% 100%, rgba(212, 175, 55, 0.05) 0, transparent 50%);
        }

        .entry-card {
            background: var(--white);
            width: 100%;
            max-width: 800px;
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr;
            border: 1px solid rgba(128,0,0,0.05);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .header-section {
            background: var(--primary-maroon);
            color: var(--white);
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: radial-gradient(circle at top right, rgba(212, 175, 55, 0.2), transparent 70%);
        }

        .header-section h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .header-section p {
            color: rgba(255,255,255,0.8);
            font-size: 0.95rem;
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .form-section {
            padding: 2.5rem;
        }

        .info-message {
            background: var(--light-gold);
            color: #856404;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border-left: 4px solid var(--accent-gold);
            font-size: 0.9rem;
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .error {
            background: #fff5f5;
            color: #c53030;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid #fed7d7;
            font-weight: 500;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            background: #f8f9fa;
            padding: 1.25rem;
            border-radius: 16px;
            border: 1px solid #eee;
            transition: all 0.3s ease;
        }

        .form-group:hover, .form-group:focus-within {
            border-color: var(--accent-gold);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.1);
            background: #fff;
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .sem-label {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--primary-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Custom Radio for Current Sem */
        .current-sem-radio {
            position: relative;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s;
        }

        .current-sem-radio input {
            appearance: none;
            width: 16px;
            height: 16px;
            border: 2px solid #ccc;
            border-radius: 50%;
            margin: 0;
            position: relative;
            transition: all 0.2s;
        }

        .current-sem-radio input:checked {
            border-color: var(--accent-gold);
            background: var(--accent-gold);
            box-shadow: 0 0 0 2px white, 0 0 0 4px var(--accent-gold);
        }

        .current-sem-radio input:checked ~ span {
            color: var(--primary-maroon);
        }

        input[type="number"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-main);
            background: white;
            transition: all 0.2s;
        }

        input[type="number"]:focus {
            outline: none;
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }

        input[type="number"]:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
            border-color: transparent;
        }

        .btn {
            width: 100%;
            padding: 16px;
            background: var(--primary-maroon);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 2rem;
            transition: all 0.3s;
            box-shadow: 0 10px 20px -5px rgba(128, 0, 0, 0.3);
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(128, 0, 0, 0.4);
        }

        .dashboard-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s;
        }

        .dashboard-link:hover { color: var(--primary-maroon); }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Mobile tweaks */
        @media (max-width: 600px) {
            body { padding: 1rem; }
            .header-section { padding: 2rem 1.5rem; }
            .form-section { padding: 1.5rem; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="entry-card">
        <div class="header-section">
            <h2>Academic History</h2>
            <p>Please update your semester-wise SGPA accurately. This data is verified against university records.</p>
        </div>

        <div class="form-section">
            <?php if ($error): ?>
                <div class="error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($isFrozen): ?>
            <div class="error" style="background:#fff1f2; color:#be123c; border-color:#fda4af; display:flex; align-items:center; gap: 10px;">
                <i class="fas fa-lock" style="font-size:18px;"></i>
                <div>
                    <strong>Access Locked:</strong> Your SGPA records have been frozen by the department coordinator. You can no longer make changes. If you need corrections, please contact your department coordinator.
                </div>
            </div>
            <?php else: ?>
            <div class="info-message">
                <div>
                    <strong>Action Required:</strong> Select your <strong>Current Semester</strong> first.
                    <div style="font-size: 0.85rem; margin-top: 4px; opacity: 0.9;">You can only enter marks for completed semesters. Your current semester will be incorrectly locked as 'Ongoing'.</div>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" id="sgpaForm">
                <div class="grid">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                    <div class="form-group" id="group-<?php echo $i; ?>">
                        <div class="form-header">
                            <span class="sem-label">Semester <?php echo $i; ?></span>
                            <?php if (!$isFrozen): ?>
                            <label class="current-sem-radio">
                                <input type="radio" name="current_sem" value="<?php echo $i; ?>" 
                                       <?php echo ($activeSem == $i) ? 'checked' : ''; ?>
                                       onchange="updateSemesters(this.value)">
                                <span>Currently Here</span>
                            </label>
                            <?php endif; ?>
                        </div>
                        <input type="number" step="0.01" min="0" max="10" 
                               name="sem<?php echo $i; ?>" 
                               id="sem-<?php echo $i; ?>"
                               placeholder="Enter SGPA" 
                               value="<?php echo $currentSgpas[$i] ?? ''; ?>"
                               <?php echo $isFrozen ? 'disabled' : ''; ?>>
                    </div>
                    <?php endfor; ?>
                </div>
                
                <?php if (!$isFrozen): ?>
                <button type="submit" class="btn">Save & Update Profile</button>
                <?php endif; ?>
                <a href="dashboard" class="dashboard-link">Return to Dashboard</a>
            </form>
        </div>
    </div>

    <script>
        const isFrozenStr = '<?php echo $isFrozen ? "1" : "0"; ?>';
        const isFrozen = (isFrozenStr === "1");

        function updateSemesters(currentSem) {
            if (isFrozen) return; // Do not update locks if frozen
            
            currentSem = parseInt(currentSem);
            
            for (let i = 1; i <= 8; i++) {
                const input = document.getElementById('sem-' + i);
                const group = document.getElementById('group-' + i);
                const radio = group.querySelector('input[type="radio"]');
                
                if (i < currentSem) {
                    // Completed Semesters
                    input.disabled = false;
                    group.style.opacity = '1';
                    group.style.borderColor = '#e2e8f0';
                    if(!input.value) input.placeholder = "Enter SGPA";
                } else if (i === currentSem) {
                    // Current Semester
                    input.disabled = true;
                    input.value = ''; 
                    group.style.opacity = '1'; 
                    group.style.background = '#fffbf0'; // Light gold bg accent
                    group.style.borderColor = 'var(--accent-gold)';
                    input.placeholder = "Ongoing (Current)";
                } else {
                    // Future Semesters
                    input.disabled = true;
                    input.value = ''; 
                    group.style.opacity = '0.4';
                    group.style.borderColor = '#eee';
                    input.placeholder = "Locked";
                }
            }
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            if (!isFrozen) {
                const checked = document.querySelector('input[name="current_sem"]:checked');
                if (checked) {
                    updateSemesters(checked.value);
                } else {
                    updateSemesters(0);
                }
            } else {
                // If frozen, ensure all groups show as locked visually
                for (let i = 1; i <= 8; i++) {
                    const input = document.getElementById('sem-' + i);
                    const group = document.getElementById('group-' + i);
                    if (!input.value) {
                         input.placeholder = "N/A";
                    }
                    group.style.opacity = '0.8';
                    group.style.background = '#f8fafc';
                }
            }
        });
    </script>
</body>
</html>

