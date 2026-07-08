<?php
/**
 * Student - Internship Undertakings Generator
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

$userId = getUserId();
$username = getUsername();
$fullName = getFullName();
$institution = $_SESSION['institution'] ?? '';

// Self-healing database check: create undertakings table if it does not exist
$db = getDB();
if ($db) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS student_internship_undertakings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usn VARCHAR(50) NOT NULL,
            name VARCHAR(100) NOT NULL,
            sem VARCHAR(20) NOT NULL,
            branch VARCHAR(100) NOT NULL,
            course VARCHAR(100) NOT NULL DEFAULT 'B.Tech./B.Sc., etc',
            undertaking_type ENUM('private', 'govt') NOT NULL,
            ref_number VARCHAR(100) NOT NULL UNIQUE,
            company_name VARCHAR(255) NOT NULL,
            company_city VARCHAR(100) NOT NULL,
            academic_year VARCHAR(20) NOT NULL,
            hod_name VARCHAR(100) NOT NULL,
            hod_email VARCHAR(100) NOT NULL,
            undertaking_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Self-heal: Check for missing columns in existing table
        $columns = $db->query("DESCRIBE student_internship_undertakings")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('company_name', $columns)) {
            $db->exec("ALTER TABLE student_internship_undertakings 
                ADD COLUMN company_name VARCHAR(255) NOT NULL AFTER ref_number,
                ADD COLUMN company_city VARCHAR(100) NOT NULL AFTER company_name,
                ADD COLUMN hod_name VARCHAR(100) NOT NULL AFTER company_city,
                ADD COLUMN hod_email VARCHAR(100) NOT NULL AFTER hod_name,
                ADD COLUMN undertaking_date DATE NOT NULL AFTER hod_email");
        }
        if (!in_array('course', $columns)) {
            $db->exec("ALTER TABLE student_internship_undertakings 
                ADD COLUMN course VARCHAR(100) NOT NULL DEFAULT 'B.Tech./B.Sc., etc' AFTER branch");
        }
    } catch (Exception $e) {
        error_log("Failed to create/alter student_internship_undertakings table: " . $e->getMessage());
    }
}

require_once ROOT_PATH . '/src/Models/StudentProfile.php';
$studentProfileModel = new StudentProfile();
$profile = $studentProfileModel->getProfile($userId);

// Extract student details
$studentUSN = $profile['usn'] ?? $username;
$studentName = $profile['name'] ?? $fullName;
$studentSem = $profile['semester'] ?? '';
$studentBranch = $profile['department'] ?? '';
$studentAcademicYear = $profile['academic_year'] ?? '2026-27';

// Compute student year of study
$studentYear = '1st';
if ($studentSem) {
    $semNum = (int) $studentSem;
    if ($semNum >= 7)
        $studentYear = '4th';
    elseif ($semNum >= 5)
        $studentYear = '3rd';
    elseif ($semNum >= 3)
        $studentYear = '2nd';
}

// Branch code helper
function getBranchCode($dept)
{
    $dept = strtoupper(trim($dept));
    if (strpos($dept, 'COMPUTER SCIENCE') !== false || $dept === 'CSE' || $dept === 'COMPUTER SCIENCE & ENGINEERING')
        return 'CSE';
    if (strpos($dept, 'INFORMATION SCIENCE') !== false || $dept === 'ISE' || $dept === 'INFORMATION SCIENCE & ENGINEERING')
        return 'ISE';
    if (strpos($dept, 'ELECTRONICS') !== false || $dept === 'ECE' || $dept === 'ELECTRONICS & COMMUNICATION ENGINEERING')
        return 'ECE';
    if (strpos($dept, 'ELECTRICAL') !== false || $dept === 'EEE' || $dept === 'ELECTRICAL & ELECTRONICS ENGINEERING')
        return 'EEE';
    if (strpos($dept, 'MECHANICAL') !== false || $dept === 'ME' || $dept === 'MECHANICAL ENGINEERING')
        return 'ME';
    if (strpos($dept, 'CIVIL') !== false || $dept === 'CV' || $dept === 'CIVIL ENGINEERING')
        return 'CV';
    if (strpos($dept, 'ARTIFICIAL INTELLIGENCE') !== false || $dept === 'AIML' || $dept === 'AI & ML')
        return 'AIML';
    if (strpos($dept, 'DATA SCIENCE') !== false || $dept === 'DS')
        return 'DS';

    $words = explode(' ', $dept);
    if (count($words) > 1) {
        $code = '';
        foreach ($words as $w) {
            if (in_array($w, ['AND', 'OF', '&', 'THE']))
                continue;
            $code .= substr($w, 0, 1);
        }
        return substr($code, 0, 5);
    }
    return substr($dept, 0, 5);
}

$branchCode = strtoupper(trim($studentBranch));

// Dynamic institution details
$isGMIT = (($profile['institution'] ?? $institution) === INSTITUTION_GMIT);
$instName = $isGMIT ? 'GM Institute of Technology' : 'GM University';
$instPrefix = $isGMIT ? 'GMIT' : 'GMU';
$instLogoTitle = $isGMIT ? 'GM INSTITUTE OF TECHNOLOGY' : 'GM UNIVERSITY';
$instAddress = $isGMIT ? 'P. B. Road, Davanagere - 577006, Karnataka, India' : 'P. B. Road, Davanagere - 577006, Karnataka, India';
$instWeb = $isGMIT ? 'www.gmit.ac.in' : 'www.gmu.ac.in';

// Prefill HOD details if available
$hodName = '';
$hodEmail = '';
if ($db) {
    try {
        $stmtH = $db->prepare("SELECT name, email FROM users WHERE role = 'hod' AND department LIKE ? LIMIT 1");
        $stmtH->execute(['%' . $branchCode . '%']);
        $hod = $stmtH->fetch();
        if ($hod) {
            $hodName = $hod['name'];
            $hodEmail = $hod['email'];
        }
    } catch (Exception $e) {
        // Keep empty for manual entry
    }
}

// Handle actions
$success = Session::flash('success') ?: '';
$error = Session::flash('error') ?: '';
$viewUndertaking = null;

if (isPost()) {
    $action = post('action');

    if ($action === 'generate') {
        $type = post('undertaking_type');
        $compName = trim(post('company_name'));
        $compCity = trim(post('company_city'));
        $hodNameInput = trim(post('hod_name'));
        $hodEmailInput = trim(post('hod_email'));
        $uDate = post('undertaking_date') ?: date('Y-m-d');

        $formUsn = trim(post('usn'));
        $formName = trim(post('name'));
        $formSem = trim(post('sem'));
        $formBranch = trim(post('branch'));
        $formCourse = trim(post('course')) ?: 'B.Tech./B.Sc., etc';
        $formAcademicYear = trim(post('academic_year'));

        if (empty($compName) || empty($compCity) || empty($hodNameInput) || empty($hodEmailInput)) {
            $error = "Please fill in all company and HOD details.";
        } else {
            try {
                // Generate next sequence number for this branch & academic year
                $stmtCount = $db->prepare("SELECT COUNT(*) FROM student_internship_undertakings WHERE branch = ? AND academic_year = ?");
                $stmtCount->execute([$formBranch, $formAcademicYear]);
                $count = (int) $stmtCount->fetchColumn();

                // Retry loop to ensure unique Ref Number under concurrency
                $inserted = false;
                $attempts = 0;
                while (!$inserted && $attempts < 5) {
                    $serial = str_pad($count + 1 + $attempts, 2, '0', STR_PAD_LEFT);
                    $refNumber = "Ref:-{$instPrefix}/UIICI/{$formBranch}/{$formAcademicYear}/{$serial}";

                    try {
                        $stmtInsert = $db->prepare("INSERT INTO student_internship_undertakings 
                            (usn, name, sem, branch, course, undertaking_type, ref_number, company_name, company_city, academic_year, hod_name, hod_email, undertaking_date)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmtInsert->execute([
                            $formUsn,
                            $formName,
                            $formSem,
                            $formBranch,
                            $formCourse,
                            $type,
                            $refNumber,
                            $compName,
                            $compCity,
                            $formAcademicYear,
                            $hodNameInput,
                            $hodEmailInput,
                            $uDate
                        ]);
                        $inserted = true;
                        $newId = $db->lastInsertId();
                        Session::flash('success', "Undertaking generated successfully with Ref: " . $refNumber);
                        redirect("internship_undertakings?view_id=" . $newId . "&print=1");
                    } catch (PDOException $ex) {
                        if ($ex->getCode() == 23000) {
                            $attempts++;
                        } else {
                            throw $ex;
                        }
                    }
                }

                if (!$inserted) {
                    $error = "Failed to generate a unique reference number. Please try again.";
                }
            } catch (Exception $e) {
                $error = "Failed to generate undertaking: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) post('id');
        try {
            $stmtDel = $db->prepare("DELETE FROM student_internship_undertakings WHERE id = ? AND usn = ?");
            $stmtDel->execute([$id, $studentUSN]);
            Session::flash('success', "Undertaking deleted successfully.");
        } catch (Exception $e) {
            Session::flash('error', "Failed to delete record: " . $e->getMessage());
        }
        redirect("internship_undertakings");
    }
}

// Fetch undertaking history for this student
$undertakings = [];
if ($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM student_internship_undertakings WHERE usn = ? ORDER BY id DESC");
        $stmt->execute([$studentUSN]);
        $undertakings = $stmt->fetchAll();
    } catch (Exception $e) {
        // Safe fallback
    }
}

// Check if specifically viewing one undertaking
$viewId = (int) get('view_id');
if ($viewId > 0) {
    foreach ($undertakings as $u) {
        if ((int) $u['id'] === $viewId) {
            $viewUndertaking = $u;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Undertakings - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root {
            --primary: #800000;
            --primary-dark: #4a0000;
            --primary-light: #fdf2f2;
            --accent-gold: #D4AF37;
            --accent-gold-dark: #aa841c;
            --bg-body: #f1f5f9;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        body {
            background: var(--bg-body);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            color: var(--text-main);
            line-height: 1.5;
        }

        .container {
            max-width: 1250px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem;
        }

        .page-header {
            margin-bottom: 2.5rem;
            animation: fadeIn 0.6s ease-out;
            border-left: 6px solid var(--primary);
            padding-left: 1.25rem;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-title {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 1.05rem;
            margin-top: 0.4rem;
        }

        /* Upgraded Tabs Container */
        .tabs-container {
            display: inline-flex;
            background: #e2e8f0;
            padding: 6px;
            border-radius: 14px;
            margin-bottom: 2.5rem;
            gap: 4px;
            border: none;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-btn:hover {
            color: var(--primary);
            background: rgba(128, 0, 0, 0.05);
        }

        .tab-btn.active {
            color: white;
            background: var(--primary);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
        }

        /* Single Centered Pane Layout */
        .single-pane-container {
            max-width: 880px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
        }

        .control-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .control-info {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .control-info i {
            color: #d97706;
            animation: pulse-gold 2s infinite;
        }

        @keyframes pulse-gold {
            0% {
                opacity: 0.6;
            }

            50% {
                opacity: 1;
            }

            100% {
                opacity: 0.6;
            }
        }

        /* Desk background for realistic floating paper preview */
        .letter-desk {
            background: #0f172a;
            padding: 3rem 2rem;
            border-radius: 24px;
            border: 1px solid #1e293b;
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.5), var(--shadow-lg);
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 3rem;
            position: relative;
        }

        .preview-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 1rem;
        }

        .preview-badge {
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-dark));
            color: #1e293b;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .preview-badge.official {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            padding: 0.85rem 1.75rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(128, 0, 0, 0.35);
        }

        /* Inline Editable Fields */
        .editable-inline {
            display: inline-block;
            border-bottom: 2px dashed #b45309;
            background: #fffbeb;
            color: #78350f;
            padding: 1px 8px;
            border-radius: 4px;
            cursor: text;
            transition: all 0.2s ease;
            min-width: 200px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .editable-inline:focus {
            outline: none;
            background: #fef3c7;
            border-bottom: 2px solid #d97706;
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.2);
        }

        .editable-inline[placeholder]:empty::before {
            content: attr(placeholder);
            color: #b45309;
            opacity: 0.5;
            font-weight: normal;
            text-transform: none;
        }

        .editable-date {
            border: none;
            border-bottom: 2px dashed #b45309;
            background: #fffbeb;
            color: #78350f;
            font-family: inherit;
            font-size: inherit;
            font-weight: bold;
            padding: 1px 8px;
            border-radius: 4px;
            cursor: pointer;
            outline: none;
            text-transform: uppercase;
        }

        .editable-date:focus {
            background: #fef3c7;
            border-bottom: 2px solid #d97706;
        }

        /* Alert notifications */
        .alert {
            padding: 1.25rem;
            border-radius: 14px;
            margin-bottom: 2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            box-shadow: var(--shadow);
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 2.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent-gold));
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-top: 0;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }

        .letterhead-paper {
            background: white;
            border: none;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border-radius: 4px;
            padding: 4.5rem 3.5rem;
            min-height: 842px;
            font-family: 'Times New Roman', Times, serif;
            color: #111;
            font-size: 13.5pt;
            line-height: 1.6;
            position: relative;
            box-sizing: border-box;
        }

        .lh-header {
            text-align: center;
            border-bottom: 2px double #333;
            padding-bottom: 1rem;
            margin-bottom: 2.25rem;
        }

        .lh-logo-title {
            font-size: 21pt;
            font-weight: bold;
            color: #800000;
            margin: 0;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }

        .lh-sub {
            font-size: 11pt;
            font-weight: bold;
            color: #444;
            margin: 0.3rem 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .lh-info {
            font-size: 9.5pt;
            color: #555;
            margin: 0;
            line-height: 1.4;
        }

        .lh-ref-date {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            font-weight: bold;
        }

        .lh-to {
            margin-bottom: 2.25rem;
            line-height: 1.5;
        }

        .lh-subject {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 2.25rem;
            text-transform: uppercase;
            line-height: 1.4;
        }

        .lh-body {
            text-align: justify;
            margin-bottom: 3.5rem;
        }

        .lh-body p {
            margin: 0 0 1.25rem 0;
            text-indent: 40px;
        }

        .lh-footer-sigs {
            display: flex;
            justify-content: space-between;
            margin-top: 4.5rem;
            line-height: 1.4;
        }

        .lh-sig-col {
            width: 45%;
        }

        .highlight-blank {
            background: #fef3c7;
            color: #b45309;
            padding: 1px 6px;
            border-radius: 4px;
            border-bottom: 1.5px solid #d97706;
            font-weight: bold;
            font-style: italic;
            font-family: 'Outfit', sans-serif;
            font-size: 10.5pt;
        }

        /* Upgraded History Table */
        .history-section {
            margin-top: 3.5rem;
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            background: white;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        th {
            background: #f8fafc;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            padding: 1.25rem 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 1.25rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
            color: #334155;
            font-weight: 500;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .badge {
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            font-size: 0.725rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .badge-private {
            background: #e0f2fe;
            color: #0369a1;
        }

        .badge-govt {
            background: #e0e7ff;
            color: #4338ca;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: white;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
        }

        .btn-action:hover {
            border-color: var(--primary);
            color: white;
            background: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.15);
        }

        .btn-action.btn-delete:hover {
            border-color: var(--danger);
            color: white;
            background: var(--danger);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2);
        }

        /* Print styling */
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm 12mm 10mm 12mm;
            }

            html, body {
                width: 210mm;
                height: 297mm;
                background: white !important;
                color: black !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow: hidden !important;
            }

            /* Hide all UI chrome */
            .unified-navbar,
            .tabs-container,
            .control-bar,
            .preview-header-bar,
            .history-section,
            .no-print {
                display: none !important;
            }

            .container {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .single-pane-container {
                padding: 0 !important;
                margin: 0 !important;
            }

            .letter-desk {
                background: transparent !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                display: block !important;
            }

            /* The paper itself: shrink font & padding to always fit A4 */
            .letterhead-paper {
                border: none !important;
                box-shadow: none !important;
                padding: 6mm 8mm !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: unset !important;
                height: auto !important;
                background: transparent !important;
                font-size: 10.5pt !important;
                line-height: 1.45 !important;
                /* Prevent any page break inside the letter */
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }

            .lh-logo-title  { font-size: 15pt !important; }
            .lh-sub         { font-size: 9pt !important; }
            .lh-info        { font-size: 8pt !important; }
            .lh-body p      { margin-bottom: 0.5rem !important; }
            .lh-footer-sigs { margin-top: 2rem !important; }
            .lh-body        { margin-bottom: 1.5rem !important; }

            /* Strip editable styling */
            .editable-inline {
                border: none !important;
                background: transparent !important;
                color: black !important;
                padding: 0 !important;
                box-shadow: none !important;
                display: inline !important;
                text-transform: none !important;
                font-weight: inherit !important;
            }

            .editable-date {
                border: none !important;
                background: transparent !important;
                color: black !important;
                padding: 0 !important;
                box-shadow: none !important;
                display: inline !important;
                appearance: none;
                -webkit-appearance: none;
            }

            /* Hard stop — no second page ever */
            * {
                page-break-after: avoid !important;
                page-break-before: avoid !important;
            }
        }
    </style>
</head>

<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header no-print">
            <h1 class="page-title"><i class="fas fa-file-contract"></i> Internship Undertaking Letters</h1>
            <p class="page-subtitle">Generate official request letters for Private and Government internship
                opportunities.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success no-print"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger no-print"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <?php
        if ($viewUndertaking !== null) {
            $u = $viewUndertaking;
            if (empty($u['course'])) {
                $u['course'] = 'B.Tech./B.Sc., etc';
            }
        } else {
            $u = [
                'ref_number' => "Ref:-{$instPrefix}/UIICI/{$branchCode}/{$studentAcademicYear}/[AUTO]",
                'undertaking_date' => date('Y-m-d'),
                'company_name' => '',
                'company_city' => '',
                'hod_name' => $hodName ?: '',
                'hod_email' => $hodEmail ?: '',
                'undertaking_type' => 'private',
                'usn' => $studentUSN,
                'name' => $studentName,
                'sem' => $studentYear,
                'branch' => $studentBranch ?: '[Branch]',
                'course' => 'B.Tech./B.Sc., etc',
                'academic_year' => $studentAcademicYear
            ];
        }

        $isGovt = $u['undertaking_type'] === 'govt';
        $isReadOnly = ($viewUndertaking !== null);
        ?>

        <div class="single-pane-container">

            <!-- Tabs -->
            <?php if (!$isReadOnly): ?>
                <div class="tabs-container no-print" style="margin: 0 auto 2rem auto;">
                    <button class="tab-btn active" id="tab-private" onclick="switchTab('private')">
                        <i class="fas fa-building"></i> Private Internship Undertaking
                    </button>
                    <button class="tab-btn" id="tab-govt" onclick="switchTab('govt')">
                        <i class="fas fa-landmark"></i> Government Internship Undertaking
                    </button>
                </div>
            <?php endif; ?>

            <!-- Control Bar -->
            <div class="control-bar no-print">
                <div class="control-info">
                    <?php if ($isReadOnly): ?>
                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                        <span>Viewing official generated letter (<?php echo htmlspecialchars($u['ref_number']); ?>)</span>
                    <?php else: ?>
                        <i class="fas fa-edit"></i>
                        <span>Click on any highlighted yellow field directly on the letter below to edit.</span>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <?php if ($isReadOnly): ?>
                        <a href="internship_undertakings" class="btn-submit"
                            style="background: #475569; box-shadow: none; text-decoration: none; padding-top: 0.65rem; padding-bottom: 0.65rem;">
                            <i class="fas fa-plus"></i> New Letter
                        </a>
                        <button onclick="window.print()" class="btn-submit" style="background: #0d9488; box-shadow: none;">
                            <i class="fas fa-print"></i> Print Letter
                        </button>
                    <?php else: ?>
                        <input type="hidden" id="undertaking_type_val" value="private">
                        <button onclick="submitUndertaking()" class="btn-submit">
                            <i class="fas fa-save"></i> Save &amp; Print Letter
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hidden Submission Form -->
            <?php if (!$isReadOnly): ?>
                <form id="hiddenUndertakingForm" method="POST" style="display: none;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="generate">
                    <input type="hidden" name="undertaking_type" id="hidden_type" value="private">
                    <input type="hidden" name="company_name" id="hidden_company_name">
                    <input type="hidden" name="company_city" id="hidden_company_city">
                    <input type="hidden" name="undertaking_date" id="hidden_date">
                    <input type="hidden" name="hod_name" id="hidden_hod_name">
                    <input type="hidden" name="hod_email" id="hidden_hod_email">
                    <input type="hidden" name="usn" value="<?php echo htmlspecialchars($studentUSN); ?>">
                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($studentName); ?>">
                    <input type="hidden" name="sem" id="hidden_sem" value="<?php echo htmlspecialchars($studentYear); ?>">
                    <input type="hidden" name="course" id="hidden_course" value="B.Tech./B.Sc., etc">
                    <input type="hidden" name="branch" id="hidden_branch" value="<?php echo htmlspecialchars($branchCode); ?>">
                    <input type="hidden" name="academic_year" id="hidden_academic_year" value="<?php echo htmlspecialchars($studentAcademicYear); ?>">
                </form>
            <?php endif; ?>

            <!-- Desk Workspace -->
            <div class="letter-desk <?php echo $isReadOnly ? 'print-target' : ''; ?>">

                <div class="letterhead-paper" id="letter_paper">
                    <!-- Letterhead Top Bar -->
                    <div class="lh-header">
                        <h1 class="lh-logo-title"><?php echo htmlspecialchars($instLogoTitle); ?></h1>
                        <div class="lh-sub">University Industry Interaction Cell (UIICI)</div>
                        <div class="lh-info"><?php echo htmlspecialchars($instAddress); ?></div>
                        <div class="lh-info">Web: <?php echo htmlspecialchars($instWeb); ?> | Email: dir.col@gmu.ac.in
                        </div>
                    </div>

                    <!-- Ref & Date -->
                    <div class="lh-ref-date">
                        <span id="ref_display"><?php echo htmlspecialchars($u['ref_number']); ?></span>
                        <span>Date:
                            <?php if ($isReadOnly): ?>
                                <span
                                    id="date_display"><?php echo date('d-m-Y', strtotime($u['undertaking_date'])); ?></span>
                            <?php else: ?>
                                <input type="date" id="inline_date" class="editable-date"
                                    value="<?php echo date('Y-m-d', strtotime($u['undertaking_date'])); ?>">
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- Recipient Address -->
                    <div class="lh-to">
                        <div>To</div>
                        <div id="to_title" style="font-weight: bold;">
                            <?php echo $isGovt ? 'The Head of the Organization / Director / HR Division' : 'The HR Manager / Concerned authority'; ?>
                        </div>
                        <div>
                            <?php if ($isReadOnly): ?>
                                <strong
                                    style="text-transform: uppercase;"><?php echo htmlspecialchars($u['company_name']); ?></strong>
                            <?php else: ?>
                                <span id="inline_company_name" class="editable-inline" contenteditable="true"
                                    placeholder="[Type Industry / Organization Name]"><?php echo htmlspecialchars($u['company_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($isReadOnly): ?>
                                <strong><?php echo htmlspecialchars($u['company_city']); ?></strong>
                            <?php else: ?>
                                <span id="inline_company_city" class="editable-inline" contenteditable="true"
                                    placeholder="[Type City]"><?php echo htmlspecialchars($u['company_city']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem;">Respected Sir/Madam,</div>
                    <br>

                    <!-- Subject -->
                    <div class="lh-subject">
                        Subject: Request for Providing Internship Opportunity for Students
                    </div>

                    <!-- Greetings -->
                    <div>Greetings from <span id="greetings_inst"><?php echo htmlspecialchars($instName); ?></span>,
                        Davanagere.</div>
                    <br>

                    <!-- Body -->
                    <div class="lh-body" id="letter_body_text">
                        <!-- Government Body -->
                        <div id="body_govt" style="<?php echo $isGovt ? 'display: block;' : 'display: none;'; ?>">
                            The University Industry Interaction Cell for Student Internships (UIICI) in association with
                            the Department of <?php if ($isReadOnly): ?><span id="body_dept_govt"><?php echo htmlspecialchars($u['branch'] ?: '[Branch]'); ?></span><?php else: ?><span id="body_dept_govt" class="editable-inline" contenteditable="true" placeholder="[Department]" style="min-width: 80px;"><?php echo htmlspecialchars($u['branch'] ?: $studentBranch ?: '[Branch]'); ?></span><?php endif; ?>
                            respectfully requests your esteemed organization to consider providing internship
                            opportunities for our student Mr/Mrs. <span id="body_name_govt"
                                style="font-weight: bold;"><?php echo htmlspecialchars($u['name']); ?></span> USN <span
                                id="body_usn_govt"
                                style="font-weight: bold;"><?php echo htmlspecialchars($u['usn']); ?></span>,
                            <?php if ($isReadOnly): ?>
                                <strong><?php echo htmlspecialchars($u['sem']); ?></strong> Year,
                                <strong><?php echo htmlspecialchars($u['course']); ?></strong> during the academic year
                                <strong><?php echo htmlspecialchars($u['academic_year']); ?></strong>.
                            <?php else: ?>
                                <span id="inline_year_govt" class="editable-inline" contenteditable="true"
                                    placeholder="[Year]"
                                    style="min-width: 40px;"><?php echo htmlspecialchars($u['sem']); ?></span> Year, <span
                                    id="inline_course_govt" class="editable-inline" contenteditable="true"
                                    placeholder="[Course]"
                                    style="min-width: 150px;"><?php echo htmlspecialchars($u['course']); ?></span> during
                                the academic year <span id="inline_academic_year_govt" class="editable-inline"
                                    contenteditable="true" placeholder="[Academic Year]"
                                    style="min-width: 80px;"><?php echo htmlspecialchars($u['academic_year']); ?></span>.
                            <?php endif; ?>
                            <br><br>
                            As part of our academic curriculum and experiential learning initiatives, students are
                            encouraged to gain practical exposure through structured internships in reputed Government
                            organizations and public institutions. Such opportunities enable students to understand
                            professional practices, enhance technical and administrative competencies, and connect
                            academic learning with real-world applications.
                            <br><br>
                            In this regard, we kindly request your support to:
                            <ul style="margin-top: 0.5rem; padding-left: 20px;">
                                <li>Facilitate internship opportunities for eligible students.</li>
                                <li>Permit students to undertake practical assignments/projects relevant to their
                                    discipline.</li>
                            </ul>
                            <p style="text-indent: 0; margin-top: 1.5rem;">
                                We look forward to your kind support and an opportunity to establish meaningful
                                industry–academia collaboration with your esteemed organization.
                                <br><br>
                                Thank you for your valuable time and consideration.
                            </p>
                        </div>

                        <!-- Private Body -->
                        <div id="body_private" style="<?php echo $isGovt ? 'display: none;' : 'display: block;'; ?>">
                            The University Industry Interaction Cell for Student Internships (UIICI) in association with
                            the Department of <?php if ($isReadOnly): ?><span id="body_dept_private"><?php echo htmlspecialchars($u['branch'] ?: '[Branch]'); ?></span><?php else: ?><span id="body_dept_private" class="editable-inline" contenteditable="true" placeholder="[Department]" style="min-width: 80px;"><?php echo htmlspecialchars($u['branch'] ?: $studentBranch ?: '[Branch]'); ?></span><?php endif; ?>
                            is pleased to request your esteemed organization to provide internship opportunity for our
                            student Mr/Mrs. <span id="body_name_private"
                                style="font-weight: bold;"><?php echo htmlspecialchars($u['name']); ?></span> USN <span
                                id="body_usn_private"
                                style="font-weight: bold;"><?php echo htmlspecialchars($u['usn']); ?></span>,
                            <?php if ($isReadOnly): ?>
                                <strong><?php echo htmlspecialchars($u['sem']); ?></strong> Year,
                                <strong><?php echo htmlspecialchars($u['course']); ?></strong> during the academic year
                                <strong><?php echo htmlspecialchars($u['academic_year']); ?></strong>.
                            <?php else: ?>
                                <span id="inline_year_private" class="editable-inline" contenteditable="true"
                                    placeholder="[Year]"
                                    style="min-width: 40px;"><?php echo htmlspecialchars($u['sem']); ?></span> Year, <span
                                    id="inline_course_private" class="editable-inline" contenteditable="true"
                                    placeholder="[Course]"
                                    style="min-width: 150px;"><?php echo htmlspecialchars($u['course']); ?></span> during
                                the academic year <span id="inline_academic_year_private" class="editable-inline"
                                    contenteditable="true" placeholder="[Academic Year]"
                                    style="min-width: 80px;"><?php echo htmlspecialchars($u['academic_year']); ?></span>.
                            <?php endif; ?>
                            <br><br>
                            At <?php echo htmlspecialchars($instName); ?>, we emphasize experiential and
                            industry-oriented learning to bridge the gap between academic knowledge and professional
                            practice. The internship programme is designed to provide students with practical exposure,
                            enhance technical and professional competencies, and prepare them for future career
                            opportunities.
                            <br><br>
                            We kindly request your support in facilitating internship opportunities for our eligible
                            students and permitting them to gain hands-on experience through industrial exposure and
                            project-based learning under the guidance of your experts.
                            <p style="text-indent: 0; margin-top: 1.5rem;">
                                We look forward to your positive response and to establishing a strong and mutually
                                beneficial industry–academia partnership.
                                <br><br>
                                Thank you for your support and cooperation.
                            </p>
                        </div>
                    </div>

                    <div>Yours faithfully,</div>

                    <!-- Signatures -->
                    <div class="lh-footer-sigs">
                        <div class="lh-sig-col">
                            <strong>Dr. Rajakumar D G</strong><br>
                            Director – UIICI<br>
                            <?php echo htmlspecialchars($instName); ?>, Davanagere-06<br>
                            dir.col@gmu.ac.in
                        </div>
                        <div class="lh-sig-col" style="text-align: right;">
                            <?php if ($isReadOnly): ?>
                                <strong>Mr/Mrs. <?php echo htmlspecialchars($u['hod_name']); ?></strong><br>
                            <?php else: ?>
                                Mr/Mrs. <span id="inline_hod_name" class="editable-inline" contenteditable="true"
                                    placeholder="[Type HOD Name]"
                                    style="min-width: 150px;"><?php echo htmlspecialchars($u['hod_name']); ?></span><br>
                            <?php endif; ?>
                            Head of the Department<br>
                            <?php echo htmlspecialchars($instName); ?>, Davanagere-06<br>
                            <?php if ($isReadOnly): ?>
                                <span>Email ID: <?php echo htmlspecialchars($u['hod_email']); ?></span>
                            <?php else: ?>
                                Email ID: <span id="inline_hod_email" class="editable-inline" contenteditable="true"
                                    placeholder="[Type HOD Email]"
                                    style="min-width: 150px; text-transform: none; font-weight: normal;"><?php echo htmlspecialchars($u['hod_email']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Generated Letters Log -->
            <div class="history-section no-print">
                <div class="card">
                    <h3 class="card-title"><i class="fas fa-history"></i> Your Generated Request Letters</h3>

                    <?php if (empty($undertakings)): ?>
                        <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                            <i class="fas fa-file-pdf"
                                style="font-size: 3rem; opacity: 0.2; margin-bottom: 1rem; display: block;"></i>
                            No letters generated yet. Edit the sheet above and click "Save &amp; Print Letter" to generate
                            your first document.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Ref Number</th>
                                        <th>Date</th>
                                        <th>Undertaking Type</th>
                                        <th>Company / Org Name</th>
                                        <th>HOD Name</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($undertakings as $item): ?>
                                        <tr>
                                            <td style="font-weight: 700; color: var(--primary);">
                                                <?php echo htmlspecialchars($item['ref_number']); ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($item['undertaking_date'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $item['undertaking_type']; ?>">
                                                    <?php echo $item['undertaking_type'] === 'govt' ? 'Government' : 'Private'; ?>
                                                </span>
                                            </td>
                                            <td style="font-weight: 600;"><?php echo htmlspecialchars($item['company_name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['hod_name']); ?></td>
                                            <td style="text-align: right;">
                                                <a href="internship_undertakings?view_id=<?php echo $item['id']; ?>"
                                                    class="btn-action" title="View & Print">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <form method="POST" style="display: inline-block;"
                                                    onsubmit="return confirm('Are you sure you want to delete this undertaking? This will release the Ref Number sequence.');">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="btn-action btn-delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

    <!-- Workspace Scripts -->
    <script>
        const studentBranch = <?php echo json_encode($studentBranch); ?>;
        const studentName = <?php echo json_encode($studentName); ?>;
        const studentUSN = <?php echo json_encode($studentUSN); ?>;
        const studentYear = <?php echo json_encode($studentYear); ?>;
        const studentAcademicYear = <?php echo json_encode($studentAcademicYear); ?>;
        const instName = <?php echo json_encode($instName); ?>;
        const instPrefix = <?php echo json_encode($instPrefix); ?>;
        const isReadOnly = <?php echo json_encode($isReadOnly); ?>;

        function switchTab(type) {
            if (isReadOnly) return;

            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('tab-' + type).classList.add('active');

            document.getElementById('undertaking_type_val').value = type;

            const toTitle = document.getElementById('to_title');
            const toCompany = document.getElementById('inline_company_name');
            const bodyTextEl = document.getElementById('letter_body_text');

            if (type === 'govt') {
                toTitle.innerText = "The Head of the Organization / Director / HR Division";
                if (toCompany.innerText === '' || toCompany.innerText === '[Type Industry / Organization Name]') {
                    toCompany.innerText = '[Type Government Org / Dept Name]';
                }

                bodyTextEl.innerHTML = `
                    The University Industry Interaction Cell for Student Internships (UIICI) in association with the Department of <span id="body_dept">${escapeHtml(studentBranch || '[Branch]')}</span> respectfully requests your esteemed organization to consider providing internship opportunities for our student Mr/Mrs. <span id="body_name" style="font-weight: bold;">${escapeHtml(studentName)}</span> USN <span id="body_usn" style="font-weight: bold;">${escapeHtml(studentUSN)}</span>, <span id="body_year">${studentYear}</span> Year, B.Tech./B.Sc., etc during the academic year <span id="body_academic_year">${escapeHtml(studentAcademicYear)}</span>.
                    <br><br>
                    As part of our academic curriculum and experiential learning initiatives, students are encouraged to gain practical exposure through structured internships in reputed Government organizations and public institutions. Such opportunities enable students to understand professional practices, enhance technical and administrative competencies, and connect academic learning with real-world applications.
                    <br><br>
                    In this regard, we kindly request your support to:
                    <ul style="margin-top: 0.5rem; padding-left: 20px;">
                        <li>Facilitate internship opportunities for eligible students.</li>
                        <li>Permit students to undertake practical assignments/projects relevant to their discipline.</li>
                    </ul>
                    <p style="text-indent: 0; margin-top: 1.5rem;">
                        We look forward to your kind support and an opportunity to establish meaningful industry–academia collaboration with your esteemed organization.
                        <br><br>
                        Thank you for your valuable time and consideration.
                    </p>
                `;
            } else {
                toTitle.innerText = "The HR Manager / Concerned authority";
                if (toCompany.innerText === '' || toCompany.innerText === '[Type Government Org / Dept Name]') {
                    toCompany.innerText = '[Type Industry / Organization Name]';
                }

                bodyTextEl.innerHTML = `
                    The University Industry Interaction Cell for Student Internships (UIICI) in association with the Department of <span id="body_dept">${escapeHtml(studentBranch || '[Branch]')}</span> is pleased to request your esteemed organization to provide internship opportunity for our student Mr/Mrs. <span id="body_name" style="font-weight: bold;">${escapeHtml(studentName)}</span> USN <span id="body_usn" style="font-weight: bold;">${escapeHtml(studentUSN)}</span>, <span id="body_year">${studentYear}</span> Year, B.Tech./B.Sc., etc during the academic year <span id="body_academic_year">${escapeHtml(studentAcademicYear)}</span>.
                    <br><br>
                    At ${escapeHtml(instName)}, we emphasize experiential and industry-oriented learning to bridge the gap between academic knowledge and professional practice. The internship programme is designed to provide students with practical exposure, enhance technical and professional competencies, and prepare them for future career opportunities.
                    <br><br>
                    We kindly request your support in facilitating internship opportunities for our eligible students and permitting them to gain hands-on experience through industrial exposure and project-based learning under the guidance of your experts.
                    <p style="text-indent: 0; margin-top: 1.5rem;">
                        We look forward to your positive response and to establishing a strong and mutually beneficial industry–academia partnership.
                        <br><br>
                        Thank you for your support and cooperation.
                    </p>
                `;
            }
        }

        function submitUndertaking() {
            if (isReadOnly) return;

            const type = document.getElementById('undertaking_type_val').value;
            const suffix  = type === 'govt' ? '_govt' : '_private';

            const compName = document.getElementById('inline_company_name').innerText.trim();
            const compCity = document.getElementById('inline_company_city').innerText.trim();
            const rawDate  = document.getElementById('inline_date').value;
            const hodName  = document.getElementById('inline_hod_name').innerText.trim();
            const hodEmail = document.getElementById('inline_hod_email').innerText.trim();

            // Read editable spans from the active tab body
            const dept         = (document.getElementById('body_dept' + suffix)?.innerText || '').trim();
            const editedYear   = (document.getElementById('inline_year' + suffix)?.innerText || '').trim();
            const editedCourse = (document.getElementById('inline_course' + suffix)?.innerText || '').trim();
            const editedAcYear = (document.getElementById('inline_academic_year' + suffix)?.innerText || '').trim();

            if (!compName || compName === '[Type Industry / Organization Name]' || compName === '[Type Government Org / Dept Name]') {
                alert('Please fill in the Industry / Organization Name directly on the sheet.');
                return;
            }
            if (!compCity || compCity === '[Type City]') {
                alert('Please fill in the City directly on the sheet.');
                return;
            }
            if (!hodName || hodName === '[Type HOD Name]') {
                alert('Please fill in the HOD Name directly on the sheet.');
                return;
            }
            if (!hodEmail || hodEmail === '[Type HOD Email]') {
                alert('Please fill in the HOD Email directly on the sheet.');
                return;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(hodEmail)) {
                alert('Please enter a valid HOD Email ID.');
                return;
            }

            document.getElementById('hidden_type').value = type;
            document.getElementById('hidden_company_name').value = compName;
            document.getElementById('hidden_company_city').value = compCity;
            document.getElementById('hidden_date').value = rawDate;
            document.getElementById('hidden_hod_name').value = hodName;
            document.getElementById('hidden_hod_email').value = hodEmail;
            if (dept)         document.getElementById('hidden_branch').value = dept;
            if (editedYear)   document.getElementById('hidden_sem').value = editedYear;
            if (editedCourse) document.getElementById('hidden_course').value = editedCourse;
            if (editedAcYear) document.getElementById('hidden_academic_year').value = editedAcYear;

            document.getElementById('hiddenUndertakingForm').submit();
        }

        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Auto-print check
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') === '1') {
                const newUrl = window.location.pathname + '?view_id=' + urlParams.get('view_id');
                window.history.replaceState({}, document.title, newUrl);
                window.print();
            }
        });
    </script>
</body>

</html>