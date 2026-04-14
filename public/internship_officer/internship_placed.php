<?php
/**
 * Internship Placed Students Management
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';
requireAnyRole([ROLE_ADMIN, ROLE_INTERNSHIP_OFFICER]);

use PhpOffice\PhpSpreadsheet\IOFactory;

$db = getDB();
$success = '';
$error = '';

// Handle Sample CSV Download
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="internship_import_template.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Academic Year', 'USN', 'Company Name', 'Internship Type', 'Status', 'Start Date (YYYY-MM-DD)', 'End Date (YYYY-MM-DD)', 'Duration (Months)']);
    fputcsv($output, ['2023-2024', '1GD20CS001', 'Google', 'On-site', 'Confirmed', '2024-06-01', '2024-12-01', '6']);
    fclose($output);
    exit;
}

// Handle Data Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $stmt = $db->prepare("DELETE FROM internship_placed_students WHERE sl_no = ?");
    $stmt->execute([$_POST['delete_id']]);
    header("Location: internship_placed.php?success=Entry deleted successfully");
    exit;
}

// Handle Form Submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        try {
            $offerLetter = '';
            if (isset($_FILES['offer_letter']) && $_FILES['offer_letter']['error'] === 0) {
                $uploadDir = __DIR__ . '/../../uploads/offer_letters/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $fileName = time() . '_' . $_FILES['offer_letter']['name'];
                if (move_uploaded_file($_FILES['offer_letter']['tmp_name'], $uploadDir . $fileName)) {
                    $offerLetter = 'uploads/offer_letters/' . $fileName;
                }
            }

            $stmt = $db->prepare("INSERT INTO internship_placed_students 
                (academic_year, name, usn, branch, college, sem, whatsapp_no, email, company_name, internship_type, internship_status, start_date, end_date, duration_months, offer_letter) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $_POST['academic_year'], $_POST['name'], $_POST['usn'], $_POST['branch'], $_POST['college'], 
                $_POST['sem'], $_POST['whatsapp_no'], $_POST['email'], $_POST['company_name'],
                $_POST['internship_type'], $_POST['internship_status'], $_POST['start_date'],
                $_POST['end_date'], $_POST['duration_months'], $offerLetter
            ]);
            
            header("Location: internship_placed.php?success=New placement added successfully");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($_POST['action'] === 'edit') {
        try {
            $sl_no = $_POST['sl_no'];
            $offerLetter = '';
            
            // Handle optional new offer letter
            if (isset($_FILES['offer_letter']) && $_FILES['offer_letter']['error'] === 0) {
                $uploadDir = __DIR__ . '/../../uploads/offer_letters/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $fileName = time() . '_' . $_FILES['offer_letter']['name'];
                if (move_uploaded_file($_FILES['offer_letter']['tmp_name'], $uploadDir . $fileName)) {
                    $offerLetter = 'uploads/offer_letters/' . $fileName;
                }
            }

            $sql = "UPDATE internship_placed_students SET 
                    academic_year = ?, name = ?, usn = ?, branch = ?, college = ?, sem = ?, 
                    whatsapp_no = ?, email = ?, company_name = ?, internship_type = ?, 
                    internship_status = ?, start_date = ?, end_date = ?, duration_months = ?";
            
            $params = [
                $_POST['academic_year'], $_POST['name'], $_POST['usn'], $_POST['branch'], $_POST['college'], 
                $_POST['sem'], $_POST['whatsapp_no'], $_POST['email'], $_POST['company_name'],
                $_POST['internship_type'], $_POST['internship_status'], $_POST['start_date'],
                $_POST['end_date'], $_POST['duration_months']
            ];

            if ($offerLetter) {
                $sql .= ", offer_letter = ?";
                $params[] = $offerLetter;
            }

            $sql .= " WHERE sl_no = ?";
            $params[] = $sl_no;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            header("Location: internship_placed.php?success=Placement record updated successfully");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($_POST['action'] === 'import_csv') {
        try {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
                $file = $_FILES['csv_file']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                
                $rows = [];
                if ($ext === 'csv') {
                    $handle = fopen($file, "r");
                    while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                        $rows[] = $data;
                    }
                    fclose($handle);
                } else {
                    $spreadsheet = IOFactory::load($file);
                    $rows = $spreadsheet->getActiveSheet()->toArray();
                }

                if (empty($rows)) throw new Exception("The file is empty.");

                // --- Smart Header Mapping (ALL fields from file) ---
                $headers = array_map('strtolower', array_map(fn($v) => trim((string)($v ?? '')), $rows[0]));
                $mapping = array_fill_keys([
                    'academic_year', 'name', 'usn', 'branch', 'college', 'sem',
                    'whatsapp_no', 'email', 'company_name', 'internship_type',
                    'internship_status', 'start_date', 'end_date', 'duration_months'
                ], -1);

                foreach ($headers as $idx => $h) {
                    if (strpos($h, 'year') !== false || strpos($h, 'academic') !== false) $mapping['academic_year'] = $idx;
                    if (strpos($h, 'name') !== false && strpos($h, 'company') === false) $mapping['name'] = $idx;
                    if (strpos($h, 'usn') !== false || $h === 'id' || $h === 'student id') $mapping['usn'] = $idx;
                    if (strpos($h, 'branch') !== false || strpos($h, 'department') !== false || strpos($h, 'discipline') !== false) $mapping['branch'] = $idx;
                    if (strpos($h, 'college') !== false || strpos($h, 'institution') !== false) $mapping['college'] = $idx;
                    if ($h === 'sem' || strpos($h, 'semester') !== false) $mapping['sem'] = $idx;
                    if (strpos($h, 'whatsapp') !== false || strpos($h, 'phone') !== false || strpos($h, 'mobile') !== false || strpos($h, 'contact') !== false) $mapping['whatsapp_no'] = $idx;
                    if (strpos($h, 'email') !== false || strpos($h, 'mail') !== false) $mapping['email'] = $idx;
                    if (strpos($h, 'company') !== false || strpos($h, 'organization') !== false || strpos($h, 'employer') !== false) $mapping['company_name'] = $idx;
                    if (strpos($h, 'type') !== false || strpos($h, 'mode') !== false) $mapping['internship_type'] = $idx;
                    if (strpos($h, 'status') !== false) $mapping['internship_status'] = $idx;
                    if (strpos($h, 'start') !== false || strpos($h, 'from') !== false) $mapping['start_date'] = $idx;
                    if (strpos($h, 'end') !== false || strpos($h, ' to') !== false) $mapping['end_date'] = $idx;
                    if (strpos($h, 'duration') !== false || strpos($h, 'month') !== false) $mapping['duration_months'] = $idx;
                }

                if ($mapping['company_name'] === -1) {
                    throw new Exception("Could not find a 'Company' column. Please check your file headers.");
                }

                $stmt = $db->prepare("INSERT INTO internship_placed_students 
                    (academic_year, name, usn, branch, college, sem, whatsapp_no, email, company_name, internship_type, internship_status, start_date, end_date, duration_months) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $count = 0; $errors = [];
                array_shift($rows); // Remove header row
                foreach ($rows as $data) {
                    if (empty(array_filter($data))) continue;

                    $cell = fn($idx) => $idx !== -1 ? trim((string)($data[$idx] ?? '')) : '';
                    $company = $cell($mapping['company_name']);
                    if (empty($company)) continue;

                    $startRaw = $cell($mapping['start_date']);
                    $endRaw   = $cell($mapping['end_date']);

                    $stmt->execute([
                        $cell($mapping['academic_year']) ?: date('Y'),
                        $cell($mapping['name']) ?: 'N/A',
                        $cell($mapping['usn']) ?: 'N/A',
                        $cell($mapping['branch']) ?: 'N/A',
                        $cell($mapping['college']) ?: 'GMU',
                        $cell($mapping['sem']) ?: 0,
                        $cell($mapping['whatsapp_no']),
                        $cell($mapping['email']),
                        $company,
                        $cell($mapping['internship_type']) ?: 'On-site',
                        $cell($mapping['internship_status']) ?: 'Confirmed',
                        !empty($startRaw) ? date('Y-m-d', strtotime($startRaw)) : null,
                        !empty($endRaw)   ? date('Y-m-d', strtotime($endRaw))   : null,
                        intval($cell($mapping['duration_months']))
                    ]);
                    $count++;
                }

                $msg = "$count records imported successfully.";
                if (!empty($errors)) $msg .= " " . count($errors) . " rows skipped.";
                header("Location: internship_placed.php?success=" . urlencode($msg));
                exit;
            } else {
                throw new Exception("Please upload a valid file.");
            }
        } catch (Exception $e) {
            $error = "Import failed: " . $e->getMessage();
        }
    }
}

// --- Fetch Filter Values ---
$years = $db->query("SELECT DISTINCT academic_year FROM internship_placed_students ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$branches = $db->query("SELECT DISTINCT branch FROM internship_placed_students ORDER BY branch ASC")->fetchAll(PDO::FETCH_COLUMN);
$sems = $db->query("SELECT DISTINCT sem FROM internship_placed_students ORDER BY sem ASC")->fetchAll(PDO::FETCH_COLUMN);
$colleges = $db->query("SELECT DISTINCT college FROM internship_placed_students ORDER BY college ASC")->fetchAll(PDO::FETCH_COLUMN);

// --- Build Filter Query ---
$f_name = $_REQUEST['f_name'] ?? '';
$f_year = $_REQUEST['f_year'] ?? '';
$f_branch = $_REQUEST['f_branch'] ?? '';
$f_sem = $_REQUEST['f_sem'] ?? '';
$f_college = $_REQUEST['f_college'] ?? '';

$where = [];
$params = [];

if (!empty($f_name)) {
    $where[] = "name LIKE ?";
    $params[] = "%$f_name%";
}
if (!empty($f_year)) {
    $where[] = "academic_year = ?";
    $params[] = $f_year;
}
if (!empty($f_branch)) {
    $where[] = "branch = ?";
    $params[] = $f_branch;
}
if (!empty($f_sem)) {
    $where[] = "sem = ?";
    $params[] = $f_sem;
}
if (!empty($f_college)) {
    $where[] = "college = ?";
    $params[] = $f_college;
}

$sql = "SELECT * FROM internship_placed_students";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY sl_no ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Analytics Aggregation ---
$stats = [
    'total_students' => count($students),
    'unique_companies' => count(array_unique(array_column($students, 'company_name'))),
    'branch_counts' => array_count_values(array_column($students, 'branch')),
    'college_counts' => array_count_values(array_column($students, 'college'))
];
arsort($stats['branch_counts']);
arsort($stats['college_counts']);
$top_branch = !empty($stats['branch_counts']) ? key($stats['branch_counts']) : 'N/A';
$top_college = !empty($stats['college_counts']) ? key($stats['college_counts']) : 'N/A';

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Placed Students - Admin</title>
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #800000;
            --secondary: #4a0000;
            --accent: #D4AF37;
            --bg-glass: rgba(255, 255, 255, 0.9);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --shadow: 0 20px 50px rgba(0,0,0,0.04);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: var(--text-main); line-height: 1.5; }
        .outfit { font-family: 'Outfit', sans-serif; }

        .main-content { padding: 40px 20px; max-width: 1400px; margin: 0 auto; width: 100%; }

        /* Navigation Breadcrumb */
        .back-link { 
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--text-muted); text-decoration: none;
            font-size: 13px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1px; margin-bottom: 24px;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary); }

        /* Glass Header */
        .glass-header {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 32px 40px;
            display: flex; justify-content: space-between; align-items: center;
            border: 1px solid rgba(255,255,255,0.7);
            box-shadow: var(--shadow);
            margin-bottom: 32px;
        }
        .glass-header h1 { font-size: 32px; font-weight: 800; letter-spacing: -1px; margin-bottom: 8px; }
        .glass-header p { color: var(--text-muted); font-size: 15px; font-weight: 500; }

        /* Filter Bar */
        .filter-bar {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #f1f5f9;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: end;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }
        .filter-group { display: flex; flex-direction: column; gap: 8px; }
        .filter-group label { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; padding-left: 4px; }
        .filter-input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1.5px solid #f1f5f9;
            background: #f8fafc;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .filter-input:focus { border-color: var(--accent); background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1); }
        .filter-actions { display: flex; gap: 10px; }
        .btn-filter {
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            font-family: 'Outfit', sans-serif;
        }
        .btn-search { background: var(--primary); color: #fff; }
        .btn-search:hover { background: var(--secondary); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2); }
        .btn-reset { background: #f1f5f9; color: var(--text-muted); }
        .btn-reset:hover { background: #e2e8f0; color: var(--text-main); }

        /* Analytics Dashboard */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .stats-card {
            background: #fff;
            padding: 24px;
            border-radius: 24px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease;
        }
        .stats-card:hover { transform: translateY(-5px); }
        .stats-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .icon-blue { background: #eff6ff; color: #3b82f6; }
        .icon-purple { background: #f5f3ff; color: #8b5cf6; }
        .icon-green { background: #ecfdf5; color: #10b981; }
        .icon-orange { background: #fff7ed; color: #f97316; }
        
        .stats-info h3 { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .stats-info div { font-size: 24px; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; }

        /* Table Components */
        .btn-group { display: flex; gap: 12px; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 24px; border-radius: 14px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none; text-decoration: none;
        }
        .btn-maroon { background: var(--primary); color: white; }
        .btn-maroon:hover { background: var(--secondary); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(128,0,0,0.15); }
        .btn-outline { background: white; color: var(--text-main); border: 1px solid #e2e8f0; }
        .btn-outline:hover { background: #f1f5f9; border-color: #cbd5e1; }

        /* Content Panel (Table) */
        .panel {
            background: white; border-radius: 24px;
            box-shadow: var(--shadow); border: 1px solid #f1f5f9;
            overflow: hidden;
        }
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            background: #f8fafc; padding: 16px 24px;
            text-align: left; font-size: 11px; font-weight: 800;
            color: #0f172a; text-transform: uppercase;
            letter-spacing: 1px; border-bottom: 1px solid #f1f5f9;
        }
        .data-table td { padding: 20px 24px; border-bottom: 1px solid #f8fafc; vertical-align: middle; color: #000; font-weight: 500; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: #fdfdfd; }

        /* Badge Styles */
        .badge {
            display: inline-flex; padding: 4px 10px; border-radius: 8px;
            font-size: 10px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-success { background: #ecfdf5; color: #10b981; }
        .badge-warning { background: #fffbeb; color: #f59e0b; }
        .badge-info { background: #eff6ff; color: #3b82f6; }

        /* Modal Redesign */
        .modal {
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            z-index: 2000; display: none; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.3s ease; padding: 20px;
        }
        .modal.open { display: flex; opacity: 1; }
        .modal-content {
            background: white; width: 100%; max-width: 800px;
            border-radius: 28px; box-shadow: 0 40px 100px rgba(0,0,0,0.15);
            max-height: 90vh; overflow-y: auto;
            animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalPop { 
            from { transform: scale(0.95) translateY(20px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        .modal-header { padding: 32px 40px 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 32px 40px 40px; }
        .modal-close { 
            width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
            color: var(--text-muted); transition: all 0.2s;
        }
        .modal-close:hover { background: #e2e8f0; color: #0f172a; }

        /* Form Controls */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 12px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase; }
        .form-control {
            width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0;
            font-family: inherit; font-size: 14px; font-weight: 500; transition: all 0.2s;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(128,0,0,0.05); }

        .form-section-title { 
            font-size: 11px; font-weight: 900; color: var(--primary); 
            text-transform: uppercase; letter-spacing: 1.5px;
            margin: 32px 0 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;
        }
        .form-section-title:first-child { margin-top: 0; }

        @media (max-width: 768px) {
            .glass-header { flex-direction: column; gap: 20px; text-align: center; align-items: center; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include_once 'navbar.php'; ?>

    <div class="main-content">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Placements</a>
        
        <header class="glass-header">
            <div class="header-title">
                <h1 class="outfit">Internship Placed Students</h1>
                <p>Registry of students secured for internships</p>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline outfit" onclick="openImportModal()">
                    <i class="fas fa-file-csv"></i> Bulk Upload
                </button>
                <button class="btn btn-maroon outfit" onclick="openModal()">
                    <i class="fas fa-plus"></i> Add Entry
                </button>
            </div>
        </header>

        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="POST" class="filter-bar">
            <div class="filter-group">
                <label>Student Name</label>
                <input type="text" name="f_name" class="filter-input" placeholder="Search by name..." value="<?php echo htmlspecialchars($f_name); ?>">
            </div>
            <div class="filter-group">
                <label>Academic Year</label>
                <select name="f_year" class="filter-input">
                    <option value="">All Years</option>
                    <?php foreach($years as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo $f_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Branch</label>
                <select name="f_branch" class="filter-input">
                    <option value="">All Branches</option>
                    <?php foreach($branches as $b): ?>
                        <option value="<?php echo $b; ?>" <?php echo $f_branch == $b ? 'selected' : ''; ?>><?php echo $b; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Semester</label>
                <select name="f_sem" class="filter-input">
                    <option value="">All</option>
                    <?php foreach($sems as $s_val): ?>
                        <option value="<?php echo $s_val; ?>" <?php echo $f_sem == $s_val ? 'selected' : ''; ?>>Sem <?php echo $s_val; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>College</label>
                <select name="f_college" class="filter-input">
                    <option value="">All Colleges</option>
                    <?php foreach($colleges as $c): ?>
                        <option value="<?php echo $c; ?>" <?php echo $f_college == $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="internship_placed.php" class="btn-filter btn-reset">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>

        <div class="analytics-grid">
            <div class="stats-card">
                <div class="stats-icon icon-blue">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stats-info">
                    <h3>Total Placed</h3>
                    <div><?php echo $stats['total_students']; ?></div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon icon-purple">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stats-info">
                    <h3>Companies</h3>
                    <div><?php echo $stats['unique_companies']; ?></div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon icon-green">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stats-info">
                    <h3>Top Branch</h3>
                    <div style="font-size: 16px;"><?php echo htmlspecialchars($top_branch); ?></div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon icon-orange">
                    <i class="fas fa-university"></i>
                </div>
                <div class="stats-info">
                    <h3>Top College</h3>
                    <div style="font-size: 16px;"><?php echo htmlspecialchars($top_college); ?></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">SL</th>
                                <th>Year</th>
                                <th>Name</th>
                                <th>USN</th>
                                <th>Branch</th>
                                <th>Sem</th>
                                <th>College</th>
                                <th>WhatsApp</th>
                                <th>Email</th>
                                <th>Company</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Offer</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $s): ?>
                                <tr>
                                    <td style="font-weight: 800; color: #000; font-size: 11px;"><?php echo $s['sl_no']; ?></td>
                                    <td style="font-weight: 700; white-space: nowrap; font-size: 12px;"><?php echo htmlspecialchars($s['academic_year']); ?></td>
                                    <td>
                                        <div class="outfit" style="font-weight: 800; color: #000; white-space: nowrap; font-size: 13px;"><?php echo htmlspecialchars($s['name']); ?></div>
                                    </td>
                                    <td style="font-size: 11px; font-weight: 700; color: #000; font-family: monospace;"><?php echo htmlspecialchars($s['usn']); ?></td>
                                    <td style="font-weight: 600; font-size: 12px;"><?php echo htmlspecialchars($s['branch']); ?></td>
                                    <td style="font-weight: 700; font-size: 12px;"><?php echo htmlspecialchars($s['sem']); ?></td>
                                    <td style="font-size: 11px; font-weight: 600; color: #000;"><?php echo htmlspecialchars($s['college']); ?></td>
                                    <td style="font-size: 11px; font-weight: 600;"><?php echo htmlspecialchars($s['whatsapp_no']); ?></td>
                                    <td style="font-size: 11px; color: #000;"><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td style="border-left: 2px solid #f1f5f9; padding-left: 15px;">
                                        <div class="outfit" style="font-weight: 800; color: #000; font-size: 14px; white-space: nowrap;"><?php echo htmlspecialchars($s['company_name']); ?></div>
                                    </td>
                                    <td><div class="badge badge-info"><?php echo htmlspecialchars($s['internship_type']); ?></div></td>
                                    <td><div class="badge badge-success"><?php echo strtoupper($s['internship_status']); ?></div></td>
                                    <td style="font-weight: 800; font-size: 12px; color: #000;"><?php echo $s['duration_months']; ?>m</td>
                                    <td style="font-size: 11px; font-weight: 600; white-space: nowrap; color: #000;"><?php echo !empty($s['start_date']) ? date('d M, y', strtotime($s['start_date'])) : '—'; ?></td>
                                    <td style="font-size: 11px; font-weight: 600; white-space: nowrap; color: #000;"><?php echo !empty($s['end_date']) ? date('d M, y', strtotime($s['end_date'])) : '—'; ?></td>
                                    <td>
                                        <?php if ($s['offer_letter']): ?>
                                            <a href="../../<?php echo htmlspecialchars($s['offer_letter']); ?>" target="_blank" class="btn btn-outline" style="padding: 4px 10px; font-size: 10px; border-radius: 8px;">
                                                <i class="fas fa-file-pdf"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 10px; font-weight: 600;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                        <button class="btn btn-outline" style="padding: 6px; border-color: #e2e8f0; color: #3b82f6; border-radius: 8px;" 
                                                onclick='editRecord(<?php echo json_encode($s); ?>)'>
                                            <i class="fas fa-edit" style="font-size: 12px;"></i>
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this entry?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="delete_id" value="<?php echo $s['sl_no']; ?>">
                                            <button type="submit" class="btn btn-outline" style="padding: 6px; border-color: #fee2e2; color: #ef4444; border-radius: 8px;">
                                                <i class="fas fa-trash-alt" style="font-size: 12px;"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($students)): ?>
                                <tr><td colspan="17" style="text-align: center; padding: 80px; color: var(--text-muted); font-weight: 600;">No placement records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="placedModal" class="modal">
        <div class="modal-content outfit">
            <div class="modal-header">
                <div>
                    <h2 id="modalTitle" class="text-2xl font-black outfit">Add Placement Entry</h2>
                    <p id="modalSubtitle" class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">New Manual Record</p>
                </div>
                <button onclick="closeModal()" class="modal-close">&times;</button>
            </div>

            <div class="modal-body">
                <form id="placedForm" action="internship_placed.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="sl_no" id="recordId" value="">
                    
                    <div class="form-section-title">Student Details</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Academic Year</label>
                                <input type="text" name="academic_year" id="edit_academic_year" class="form-control" required placeholder="e.g. 2023-2024">
                        </div>
                        <div class="form-group">
                            <label>USN</label>
                                <input type="text" name="usn" id="edit_usn" class="form-control" required placeholder="University Seat Number">
                        </div>
                        <div class="form-group">
                            <label>Student Name</label>
                                <input type="text" name="name" id="edit_name" class="form-control" required placeholder="Full Name">
                        </div>
                        <div class="form-group">
                            <label>Branch</label>
                                <input type="text" name="branch" id="edit_branch" class="form-control" required placeholder="e.g. CSE">
                        </div>
                        <div class="form-group">
                            <label>Semester</label>
                                <select name="sem" id="edit_sem" class="form-control" required>
                                <option value="">Select Sem</option>
                                <?php for($i=1; $i<=8; $i++) echo "<option value='{$i}'>{$i}th Sem</option>"; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>WhatsApp</label>
                                <input type="text" name="whatsapp_no" id="edit_whatsapp_no" class="form-control" required placeholder="+91">
                        </div>
                    </div>

                    <div class="form-section-title">Internship Logistics</div>
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Company Name</label>
                                <input type="text" name="company_name" id="edit_company_name" class="form-control" required placeholder="Hiring Organization">
                        </div>
                        <div class="form-group">
                            <label>Internship Type</label>
                                <select name="internship_type" id="edit_internship_type" class="form-control" required>
                                <option value="Virtual">Virtual</option>
                                <option value="On-site">On-site</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                                <select name="internship_status" id="edit_internship_status" class="form-control" required>
                                <option value="Ongoing">Ongoing</option>
                                <option value="Completed">Completed</option>
                                <option value="Confirmed">Confirmed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Duration (Months)</label>
                            <input type="number" name="duration_months" id="edit_duration_months" class="form-control" required placeholder="6">
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <label>Offer Letter File</label>
                        <input type="file" name="offer_letter" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    </div>

                    <div style="margin-top: 40px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-outline outfit" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-maroon outfit">Save Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content outfit" style="max-width: 500px;">
            <div class="modal-header">
                <div>
                    <h2 class="text-2xl font-black outfit">Bulk Import</h2>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">Smart CSV Processing</p>
                </div>
                <button onclick="closeImportModal()" class="modal-close">&times;</button>
            </div>
            
            <div class="modal-body">
                <form action="internship_placed.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import_csv">
                    
                    <div style="background: #f8fafc; padding: 20px; border-radius: 20px; border: 1px solid #f1f5f9; margin-bottom: 24px;">
                        <p class="text-xs font-black text-slate-700 uppercase tracking-widest mb-2"><i class="fas fa-bolt text-amber-500 mr-1.5"></i> Smart Detection</p>
                        <p class="text-[11px] text-slate-500 font-medium leading-relaxed">
                            Support for **.XLSX** and **.CSV**. The system now automatically detects your column headers (USN, Company, etc.) regardless of their order.
                        </p>
                        <a href="?download_template=1" class="btn btn-outline outfit mt-4" style="padding: 8px 16px; font-size: 11px;">
                            <i class="fas fa-download"></i> Download Template
                        </a>
                    </div>

                    <div class="form-group">
                        <label>Choose Excel/CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,.xlsx" required>
                    </div>

                    <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-outline outfit" onclick="closeImportModal()">Cancel</button>
                        <button type="submit" class="btn btn-maroon outfit">Start Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('modalTitle').innerText = 'Add Placement Entry';
            document.getElementById('modalSubtitle').innerText = 'New Manual Record';
            document.getElementById('formAction').value = 'add';
            document.getElementById('placedForm').reset();
            document.getElementById('recordId').value = '';
            
            const m = document.getElementById('placedModal');
            m.style.display = 'flex';
            setTimeout(() => m.classList.add('open'), 10);
        }

        function editRecord(data) {
            document.getElementById('modalTitle').innerText = 'Edit Placement Entry';
            document.getElementById('modalSubtitle').innerText = 'Update Existing Record';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('recordId').value = data.sl_no;

            document.getElementById('edit_academic_year').value = data.academic_year;
            document.getElementById('edit_usn').value = data.usn;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_branch').value = data.branch;
            document.getElementById('edit_sem').value = data.sem;
            document.getElementById('edit_whatsapp_no').value = data.whatsapp_no;
            document.getElementById('edit_company_name').value = data.company_name;
            document.getElementById('edit_internship_type').value = data.internship_type;
            document.getElementById('edit_internship_status').value = data.internship_status;
            document.getElementById('edit_start_date').value = data.start_date;
            document.getElementById('edit_end_date').value = data.end_date;

            const m = document.getElementById('placedModal');
            m.style.display = 'flex';
            setTimeout(() => m.classList.add('open'), 10);
        }
        function closeModal() {
            const m = document.getElementById('placedModal');
            m.classList.remove('open');
            setTimeout(() => m.style.display = 'none', 300);
        }
        
        function openImportModal() {
            const m = document.getElementById('importModal');
            m.style.display = 'flex';
            setTimeout(() => m.classList.add('open'), 10);
        }
        function closeImportModal() {
            const m = document.getElementById('importModal');
            m.classList.remove('open');
            setTimeout(() => m.style.display = 'none', 300);
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('placedModal')) closeModal();
            if (event.target == document.getElementById('importModal')) closeImportModal();
        }
    </script>
</body>
</html>
