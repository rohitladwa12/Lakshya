<?php
/**
 * Executive Light-Theme Edit Internship Interface
 * Structured, professional, and interactive editing console for Internship Officers.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole('internship_officer');

$internshipId = get('id');
if (!$internshipId) {
    redirect('dashboard.php');
}

$internshipModel = new Internship();
$internship = $internshipModel->find($internshipId);

if (!$internship) {
    die("Internship opportunity not found.");
}

$userId = getUserId();
$message = '';
$error = '';
$waUrl = '';

if (isPost()) {
    $title = trim((string)post('internship_title'));
    $company = trim((string)post('company_name'));
    $rawDeadline = post('application_deadline');
    $formattedDeadlineDb = !empty($rawDeadline) ? str_replace('T', ' ', $rawDeadline) : null;
    
    $updateData = [
        'internship_title' => $title,
        'company_name' => $company,
        'location' => post('location'),
        'duration' => post('duration'),
        'stipend' => post('stipend'),
        'mode' => post('mode'),
        'targeted_students' => post('targeted_students'),
        'description' => post('description'),
        'requirements' => post('requirements'),
        'responsibilities' => post('responsibilities'),
        'start_date' => !empty(post('start_date')) ? post('start_date') : null,
        'end_date' => !empty(post('end_date')) ? post('end_date') : null,
        'application_deadline' => $formattedDeadlineDb,
        'positions' => (int)post('positions', 1),
        'link' => post('link'),
        'status' => post('status', 'Active')
    ];

    // File Upload: Logo
    if (!empty($_FILES['company_logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
            $logoName = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $uploadDir = 'uploads/internships/logo/';
            $fullUploadDir = __DIR__ . '/../../public/' . $uploadDir;
            if (!is_dir($fullUploadDir)) mkdir($fullUploadDir, 0777, true);
            
            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $fullUploadDir . $logoName)) {
                $updateData['company_logo'] = $uploadDir . $logoName;
            }
        }
    }
    
    // File Upload: Documents
    if (!empty($_FILES['description_documents']['name'][0])) {
        $files = $_FILES['description_documents'];
        $count = count($files['name']);
        $docUploadDir = 'uploads/internships/document/';
        $fullDocDir = __DIR__ . '/../../public/' . $docUploadDir;
        if (!is_dir($fullDocDir)) mkdir($fullDocDir, 0777, true);
        
        $docPaths = [];
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === 0) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'zip'])) {
                    $docName = 'doc_' . time() . '_' . $i . '.' . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], $fullDocDir . $docName)) {
                        $docPaths[] = $docUploadDir . $docName;
                    }
                }
            }
        }
        if (!empty($docPaths)) {
            $updateData['description_documents'] = json_encode($docPaths);
        }
    }
    
    $success = $internshipModel->update($internshipId, $updateData);
    
    if ($success) {
        $message = "Internship drive updated successfully!";
        $internship = $internshipModel->find($internshipId); // Reload fresh data
        
        // WhatsApp link
        $formattedDlWa = 'Open / Ongoing';
        if (!empty($internship['application_deadline'])) {
            $dlTime = strtotime($internship['application_deadline']);
            $formattedDlWa = (date('H:i', $dlTime) !== '00:00') ? date('M d, Y \a\t h:i A', $dlTime) : date('M d, Y', $dlTime);
        }
        $shareUrl = APP_URL . '/student/internship_details.php?code=' . encryptInternshipId($internshipId);
        $waMessage = "*📢 Updated Internship Opportunity!*\n\n"
                   . "*Company:* " . $internship['company_name'] . "\n"
                   . "*Role:* " . $internship['internship_title'] . "\n"
                   . "*Location:* " . ($internship['location'] ?: 'Unspecified') . "\n"
                   . "*Stipend:* " . ($internship['stipend'] ?: 'Competitive') . "\n"
                   . "*Mode:* " . $internship['mode'] . "\n"
                   . "*Duration:* " . ($internship['duration'] ?: 'Standard') . "\n"
                   . "*Deadline:* " . $formattedDlWa . "\n\n"
                   . "*Apply on Lakshya:* " . $shareUrl;
        $waUrl = "https://api.whatsapp.com/send?text=" . urlencode($waMessage);
    } else {
        $error = "Failed to update internship drive. Please try again.";
    }
}

// Prepare datetime value for input
$deadlineInputVal = '';
if (!empty($internship['application_deadline'])) {
    $deadlineInputVal = date('Y-m-d\TH:i', strtotime($internship['application_deadline']));
}
$existingDocs = json_decode($internship['description_documents'] ?? '', true) ?: [];
$shareUrl = APP_URL . '/student/internship_details.php?code=' . encryptInternshipId($internshipId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Internship: <?php echo htmlspecialchars($internship['internship_title']); ?> | Placement Portal</title>
    
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5c0000;
            --primary-light: #fff5f5;
            --primary-subtle: #fbe8e8;
            --accent-gold: #b45309;
            --bg-page: #f8fafc;
            --surface-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --border-focus: #800000;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.04);
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background-color: var(--bg-page);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .outfit-font { font-family: 'Outfit', sans-serif; }

        .main-container {
            max-width: 1320px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        /* Top Breadcrumb Header */
        .top-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .back-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background: var(--surface-card);
            color: var(--text-muted);
            padding: 0.6rem 1.2rem;
            border-radius: 9999px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            border: 1px solid var(--border-color);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }

        .back-pill:hover {
            color: var(--primary-maroon);
            border-color: var(--primary-maroon);
            transform: translateX(-3px);
            background: var(--primary-light);
        }

        .badge-posting-mode {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #fef3c7;
            color: #92400e;
            padding: 0.4rem 0.9rem;
            border-radius: 9999px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, #ffffff 0%, #fdf8f8 100%);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 2rem 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            position: relative;
            overflow: hidden;
        }

        .hero-banner::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 6px;
            background: linear-gradient(180deg, var(--primary-maroon) 0%, #b45309 100%);
        }

        .hero-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.03em;
            margin-bottom: 0.35rem;
        }

        .hero-subtitle {
            color: var(--text-muted);
            font-size: 0.92rem;
        }

        /* Toast Banner */
        .toast-banner {
            border-radius: var(--radius-lg);
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            animation: slideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .toast-banner-success {
            background: #ffffff;
            border: 1.5px solid #86efac;
            border-left: 6px solid #16a34a;
        }

        .toast-banner-error {
            background: #ffffff;
            border: 1.5px solid #fca5a5;
            border-left: 6px solid #dc2626;
        }

        .toast-flex {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.25rem;
        }

        .toast-header-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .toast-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast-icon-success { background: #dcfce7; color: #15803d; }
        .toast-icon-error { background: #fee2e2; color: #b91c1c; }

        .toast-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-action-sm {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.1rem;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .btn-wa-action {
            background: #25d366;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25);
        }

        .btn-wa-action:hover {
            background: #1eb956;
            transform: translateY(-1px);
        }

        .btn-outline-action {
            background: #ffffff;
            color: var(--text-main);
            border-color: var(--border-color);
        }

        .btn-outline-action:hover {
            background: var(--bg-page);
            border-color: var(--primary-maroon);
            color: var(--primary-maroon);
        }

        /* 2-Column Split Layout */
        .workspace-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
            align-items: start;
        }

        @media (max-width: 1080px) {
            .workspace-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Section Cards */
        .form-section-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 2.25rem;
            margin-bottom: 1.75rem;
            box-shadow: var(--shadow-sm);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-section-card:hover {
            border-color: #cbd5e1;
            box-shadow: var(--shadow-md);
        }

        .section-header-block {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .section-title-wrap {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .section-number-badge {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--primary-light);
            color: var(--primary-maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            border: 1px solid var(--primary-subtle);
        }

        .section-title-text {
            font-family: 'Outfit', sans-serif;
            font-size: 1.18rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.01em;
        }

        .section-caption {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

        /* Form Grid & Controls */
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .col-full {
            grid-column: 1 / -1;
        }

        @media (max-width: 640px) {
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
            }
        }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .field-label {
            font-size: 0.825rem;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .label-required {
            color: var(--primary-maroon);
            margin-left: 0.2rem;
        }

        .label-hint {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-left {
            position: absolute;
            left: 1rem;
            color: #94a3b8;
            font-size: 0.95rem;
            pointer-events: none;
            transition: color 0.2s;
        }

        .field-control {
            width: 100%;
            background: #ffffff;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.78rem 1rem;
            font-family: inherit;
            font-size: 0.92rem;
            color: var(--text-main);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
        }

        .field-control-icon {
            padding-left: 2.75rem;
        }

        .field-control:focus {
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 3.5px rgba(128, 0, 0, 0.08);
            background: #ffffff;
        }

        .field-control:hover:not(:focus) {
            border-color: #cbd5e1;
        }

        textarea.field-control {
            resize: vertical;
            min-height: 110px;
            line-height: 1.6;
        }

        /* Visual Mode Selector (Cards) */
        .mode-selector-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
        }

        .mode-option-card {
            position: relative;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.9rem 0.75rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #ffffff;
            user-select: none;
        }

        .mode-option-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .mode-option-card:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .mode-option-card.selected {
            border-color: var(--primary-maroon);
            background: var(--primary-light);
            box-shadow: 0 0 0 1px var(--primary-maroon);
        }

        .mode-option-card i {
            font-size: 1.25rem;
            margin-bottom: 0.35rem;
            display: block;
            color: var(--text-muted);
            transition: color 0.2s;
        }

        .mode-option-card.selected i {
            color: var(--primary-maroon);
        }

        .mode-label-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
            display: block;
        }

        .mode-label-sub {
            font-size: 0.72rem;
            color: var(--text-muted);
            display: block;
        }

        /* File Upload Zones */
        .upload-dropzone {
            background: #fdfdfd;
            border: 2px dashed #cbd5e1;
            border-radius: var(--radius-md);
            padding: 1.5rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .upload-dropzone:hover {
            border-color: var(--primary-maroon);
            background: var(--primary-light);
        }

        .upload-dropzone input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #f1f5f9;
            color: var(--primary-maroon);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            margin-bottom: 0.5rem;
            transition: transform 0.2s;
        }

        .upload-dropzone:hover .upload-icon-circle {
            transform: scale(1.08);
            background: #ffffff;
        }

        .upload-prompt {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .upload-subtext {
            font-size: 0.74rem;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }

        .preview-file-badge {
            margin-top: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            background: #e2e8f0;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 600;
            color: #334155;
        }

        /* Sidebar Preview Card */
        .sidebar-sticky-panel {
            position: sticky;
            top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .preview-card-wrap {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 1.75rem;
            box-shadow: var(--shadow-md);
        }

        .preview-header-tag {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .preview-header-tag span {
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--primary-maroon);
        }

        /* Mockup Student Card */
        .mock-student-card {
            background: #ffffff;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            transition: all 0.2s;
        }

        .mock-card-header {
            display: flex;
            gap: 0.85rem;
            align-items: center;
            margin-bottom: 0.85rem;
        }

        .mock-logo-box {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            background: #f1f5f9;
            color: var(--primary-maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1rem;
            font-family: 'Outfit', sans-serif;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            flex-shrink: 0;
        }

        .mock-logo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .mock-company-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .mock-role-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.25;
            margin-top: 0.1rem;
        }

        .mock-chips-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 0.85rem;
        }

        .mock-chip {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.25rem 0.55rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .mock-chip-stipend { background: #fef3c7; color: #92400e; }
        .mock-chip-mode { background: #f0fdf4; color: #166534; }
        .mock-chip-target { background: #eff6ff; color: #1e40af; }

        .mock-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            font-size: 0.76rem;
            color: #475569;
            padding: 0.75rem 0;
            border-top: 1px dashed #e2e8f0;
            border-bottom: 1px dashed #e2e8f0;
            margin-bottom: 0.85rem;
        }

        .mock-detail-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .mock-deadline-badge {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            color: #c53030;
            padding: 0.45rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Publishing Tips Box */
        .guideline-box {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            font-size: 0.82rem;
        }

        .guideline-box h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.6rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .guideline-list {
            padding-left: 1.2rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .guideline-list li {
            margin-bottom: 0.35rem;
        }

        /* Form Footer Buttons */
        .form-footer-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
        }

        .btn-publish-main {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            padding: 0.95rem 2rem;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.25);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-publish-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(128, 0, 0, 0.35);
            filter: brightness(1.05);
        }

        .btn-discard-draft {
            background: transparent;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.85rem 1.4rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-discard-draft:hover {
            background: #ffffff;
            border-color: #cbd5e1;
            color: var(--text-main);
        }
    </style>
</head>
<body>
    
    <!-- Top Modern Navigation -->
    <?php include 'navbar.php'; ?>

    <main class="main-container">
        
        <!-- Breadcrumb & Mode Pill -->
        <div class="top-header-bar">
            <a href="dashboard.php" class="back-pill">
                <i class="fas fa-arrow-left"></i> Back to Dashboard Console
            </a>
            <div class="badge-posting-mode">
                <i class="fas fa-pen-to-square"></i> Edit Drive #<?php echo $internshipId; ?>
            </div>
        </div>

        <!-- Success Toast Bar -->
        <?php if ($message): ?>
            <div class="toast-banner toast-banner-success">
                <div class="toast-flex">
                    <div class="toast-header-info">
                        <div class="toast-icon toast-icon-success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <h3 class="outfit-font" style="font-size: 1.1rem; font-weight: 700; color: #166534;"><?php echo $message; ?></h3>
                            <p style="font-size: 0.85rem; color: #15803d; margin-top: 0.15rem;">Modifications are now live for all prospective student applicants.</p>
                        </div>
                    </div>
                    <div class="toast-actions">
                        <?php if (!empty($waUrl)): ?>
                            <a href="<?php echo $waUrl; ?>" target="_blank" class="btn-action-sm btn-wa-action">
                                <i class="fab fa-whatsapp"></i> Broadcast Update
                            </a>
                        <?php endif; ?>
                        <button type="button" class="btn-action-sm btn-outline-action" onclick="copyPostingLink('<?php echo $shareUrl; ?>')">
                            <i class="far fa-copy"></i> Copy Link
                        </button>
                        <a href="applications.php?id=<?php echo $internshipId; ?>" class="btn-action-sm btn-outline-action">
                            <i class="fas fa-users"></i> View Applicants
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Toast Bar -->
        <?php if ($error): ?>
            <div class="toast-banner toast-banner-error">
                <div class="toast-flex">
                    <div class="toast-header-info">
                        <div class="toast-icon toast-icon-error">
                            <i class="fas fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <h3 class="outfit-font" style="font-size: 1.1rem; font-weight: 700; color: #991b1b;">Update Error</h3>
                            <p style="font-size: 0.85rem; color: #b91c1c; margin-top: 0.15rem;"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Hero Header -->
        <div class="hero-banner">
            <div>
                <h1 class="hero-title">Modify Internship Drive</h1>
                <p class="hero-subtitle">Update role parameters, change deadlines, toggle status, and adjust cohort eligibility.</p>
            </div>
            <div style="font-size: 2.75rem; color: var(--primary-maroon); opacity: 0.15; margin-right: 1rem;">
                <i class="fas fa-edit"></i>
            </div>
        </div>

        <!-- Main Form & Live Preview Grid -->
        <form method="POST" enctype="multipart/form-data" id="internshipForm">
            <div class="workspace-grid">
                
                <!-- Left Column: Form Sections -->
                <div class="form-sections-column">
                    
                    <!-- Section 1: Company & Role Details -->
                    <div class="form-section-card">
                        <div class="section-header-block">
                            <div class="section-title-wrap">
                                <div class="section-number-badge">1</div>
                                <div>
                                    <h2 class="section-title-text">Company & Role Identity</h2>
                                    <p class="section-caption">Core identifying details shown prominently on the student listing.</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field-group col-full">
                                <label class="field-label">
                                    <span>Internship Title / Designation <span class="label-required">*</span></span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-id-badge input-icon-left"></i>
                                    <input type="text" name="internship_title" id="input_title" class="field-control field-control-icon" value="<?php echo htmlspecialchars($internship['internship_title']); ?>" required oninput="updateLivePreview()">
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <span>Company / Organization Name <span class="label-required">*</span></span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-building input-icon-left"></i>
                                    <input type="text" name="company_name" id="input_company" class="field-control field-control-icon" value="<?php echo htmlspecialchars($internship['company_name']); ?>" required oninput="updateLivePreview()">
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <span>Location / Work City <span class="label-required">*</span></span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-location-dot input-icon-left"></i>
                                    <input type="text" name="location" id="input_location" class="field-control field-control-icon" value="<?php echo htmlspecialchars($internship['location']); ?>" required oninput="updateLivePreview()">
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <span>Company Logo <span class="label-hint">(Change only if needed)</span></span>
                                </label>
                                <div class="upload-dropzone" onclick="document.getElementById('logoFileInput').click()">
                                    <input type="file" name="company_logo" id="logoFileInput" accept="image/*" onchange="handleLogoPreview(this)">
                                    <div class="upload-icon-circle">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <div class="upload-prompt" id="logoUploadPrompt"><?php echo !empty($internship['company_logo']) ? 'Replace existing logo' : 'Upload company logo'; ?></div>
                                    <div class="upload-subtext">Square PNG / JPG recommended</div>
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <span>Eligible Target Group <span class="label-required">*</span></span>
                                    <span class="label-hint">Student cohort</span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user-graduate input-icon-left"></i>
                                    <select class="field-control field-control-icon" name="targeted_students" id="input_target" required onchange="updateLivePreview()">
                                        <option value="">-- Select Target Group --</option>
                                        <option value="UG" <?php echo $internship['targeted_students'] === 'UG' ? 'selected' : ''; ?>>UG (Undergraduate - B.Tech / BE)</option>
                                        <option value="PG" <?php echo $internship['targeted_students'] === 'PG' ? 'selected' : ''; ?>>PG (Postgraduate - M.Tech / MBA / MCA)</option>
                                        <option value="Diploma" <?php echo $internship['targeted_students'] === 'Diploma' ? 'selected' : ''; ?>>Diploma Students</option>
                                        <option value="UG,PG" <?php echo $internship['targeted_students'] === 'UG,PG' ? 'selected' : ''; ?>>UG & PG (All Degree Students)</option>
                                        <option value="UG,Diploma" <?php echo $internship['targeted_students'] === 'UG,Diploma' ? 'selected' : ''; ?>>UG & Diploma</option>
                                        <option value="PG,Diploma" <?php echo $internship['targeted_students'] === 'PG,Diploma' ? 'selected' : ''; ?>>PG & Diploma</option>
                                        <option value="UG,PG,Diploma" <?php echo $internship['targeted_students'] === 'UG,PG,Diploma' ? 'selected' : ''; ?>>All (UG, PG & Diploma)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Compensation, Mode & Status -->
                    <div class="form-section-card">
                        <div class="section-header-block">
                            <div class="section-title-wrap">
                                <div class="section-number-badge">2</div>
                                <div>
                                    <h2 class="section-title-text">Compensation, Mode & Schedule</h2>
                                    <p class="section-caption">Set stipend details, engagement modality, slot capacity, and application timing.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Work Mode Selector -->
                        <div class="field-group" style="margin-bottom: 1.5rem;">
                            <label class="field-label">
                                <span>Engagement Modality <span class="label-required">*</span></span>
                            </label>
                            <div class="mode-selector-grid">
                                <label class="mode-option-card <?php echo $internship['mode'] === 'Offline' ? 'selected' : ''; ?>" id="modeCard_Offline">
                                    <input type="radio" name="mode" value="Offline" <?php echo $internship['mode'] === 'Offline' ? 'checked' : ''; ?> onchange="selectMode('Offline')">
                                    <i class="fas fa-building"></i>
                                    <span class="mode-label-title">On-Site</span>
                                    <span class="mode-label-sub">In-office physical</span>
                                </label>
                                <label class="mode-option-card <?php echo $internship['mode'] === 'Online' ? 'selected' : ''; ?>" id="modeCard_Online">
                                    <input type="radio" name="mode" value="Online" <?php echo $internship['mode'] === 'Online' ? 'checked' : ''; ?> onchange="selectMode('Online')">
                                    <i class="fas fa-house-laptop"></i>
                                    <span class="mode-label-title">Remote</span>
                                    <span class="mode-label-sub">Virtual / Work from home</span>
                                </label>
                                <label class="mode-option-card <?php echo $internship['mode'] === 'Hybrid' ? 'selected' : ''; ?>" id="modeCard_Hybrid">
                                    <input type="radio" name="mode" value="Hybrid" <?php echo $internship['mode'] === 'Hybrid' ? 'checked' : ''; ?> onchange="selectMode('Hybrid')">
                                    <i class="fas fa-shuffle"></i>
                                    <span class="mode-label-title">Hybrid</span>
                                    <span class="mode-label-sub">Office + Remote flexible</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-row-3">
                            <div class="field-group">
                                <label class="field-label">
                                    <span>Stipend / Benefits <span class="label-required">*</span></span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-indian-rupee-sign input-icon-left"></i>
                                    <input type="text" name="stipend" id="input_stipend" class="field-control field-control-icon" value="<?php echo htmlspecialchars($internship['stipend']); ?>" required oninput="updateLivePreview()">
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <span>Internship Duration <span class="label-required">*</span></span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="far fa-clock input-icon-left"></i>
                                    <input type="text" name="duration" id="input_duration" class="field-control field-control-icon" value="<?php echo htmlspecialchars($internship['duration']); ?>" required oninput="updateLivePreview()">
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <span>Open Positions / Slots</span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-users-viewfinder input-icon-left"></i>
                                    <input type="number" name="positions" id="input_positions" class="field-control field-control-icon" value="<?php echo htmlspecialchars($internship['positions']); ?>" min="1" oninput="updateLivePreview()">
                                </div>
                            </div>
                        </div>

                        <div class="form-row-3">
                            <div class="field-group">
                                <label class="field-label">
                                    <span>Start Date (Approx)</span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="far fa-calendar-check input-icon-left"></i>
                                    <input type="date" name="start_date" id="input_start_date" class="field-control field-control-icon" value="<?php echo $internship['start_date']; ?>" oninput="updateLivePreview()">
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <span>Application Deadline (Date & Time) <span class="label-required">*</span></span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="far fa-hourglass-half input-icon-left" style="color: var(--primary-maroon);"></i>
                                    <input type="datetime-local" name="application_deadline" id="input_deadline" class="field-control field-control-icon" style="font-weight: 600;" value="<?php echo htmlspecialchars($deadlineInputVal); ?>" required oninput="updateLivePreview()">
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <span>Listing Status <span class="label-required">*</span></span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-toggle-on input-icon-left"></i>
                                    <select name="status" class="field-control field-control-icon" required>
                                        <option value="Active" <?php echo $internship['status'] === 'Active' ? 'selected' : ''; ?>>Active (Visible)</option>
                                        <option value="Inactive" <?php echo $internship['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                                        <option value="Closed" <?php echo $internship['status'] === 'Closed' ? 'selected' : ''; ?>>Closed (Applications Stopped)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Job Description & Eligibility -->
                    <div class="form-section-card">
                        <div class="section-header-block">
                            <div class="section-title-wrap">
                                <div class="section-number-badge">3</div>
                                <div>
                                    <h2 class="section-title-text">Role Overview & Prerequisites</h2>
                                    <p class="section-caption">Outline key duties, responsibilities, technical skills, and selection criteria.</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field-group col-full">
                                <label class="field-label">
                                    <span>Role Description & Overview <span class="label-required">*</span></span>
                                </label>
                                <textarea name="description" id="input_description" class="field-control" required><?php echo htmlspecialchars($internship['description']); ?></textarea>
                            </div>

                            <div class="field-group col-full">
                                <label class="field-label">
                                    <span>Skills & Eligibility Prerequisites</span>
                                </label>
                                <textarea name="requirements" id="input_requirements" class="field-control"><?php echo htmlspecialchars($internship['requirements']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Attachments & Application Link -->
                    <div class="form-section-card">
                        <div class="section-header-block">
                            <div class="section-title-wrap">
                                <div class="section-number-badge">4</div>
                                <div>
                                    <h2 class="section-title-text">Attachments & External Links</h2>
                                    <p class="section-caption">Upload updated Job Description files or configure external Google forms.</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field-group col-full">
                                <label class="field-label">
                                    <span>Attach JD / Brochure Documents <span class="label-hint">(Replaces previous attachments)</span></span>
                                </label>
                                <div class="upload-dropzone" onclick="document.getElementById('docsFileInput').click()">
                                    <input type="file" name="description_documents[]" id="docsFileInput" multiple accept=".pdf,.doc,.docx,.txt,.zip" onchange="handleDocsPreview(this)">
                                    <div class="upload-icon-circle">
                                        <i class="fas fa-file-arrow-up"></i>
                                    </div>
                                    <div class="upload-prompt" id="docsUploadPrompt">
                                        <?php echo !empty($existingDocs) ? count($existingDocs) . ' existing file(s) attached — click to replace' : 'Upload Job Description or Policy files'; ?>
                                    </div>
                                    <div class="upload-subtext">PDF, DOCX, ZIP files accepted</div>
                                </div>
                                <div id="docsListPreview" style="margin-top: 0.5rem;">
                                    <?php foreach ($existingDocs as $doc): ?>
                                        <div class="preview-file-badge">
                                            <i class="far fa-file-lines"></i> <?php echo htmlspecialchars(basename($doc)); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="field-group col-full">
                                <label class="field-label">
                                    <span>External Link / Google Form URL <span class="label-hint">(Optional)</span></span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-link input-icon-left"></i>
                                    <input type="url" name="link" class="field-control field-control-icon" value="<?php echo htmlspecialchars($internship['link'] ?? ''); ?>" placeholder="https://forms.gle/...">
                                </div>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="form-footer-actions">
                            <a href="dashboard.php" class="btn-discard-draft">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <button type="submit" class="btn-publish-main" id="btnPublish">
                                <i class="fas fa-check-circle"></i> Save All Changes
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Interactive Live Preview & Guidelines -->
                <div class="sidebar-sticky-panel">
                    
                    <!-- Real-Time Student Card Preview -->
                    <div class="preview-card-wrap">
                        <div class="preview-header-tag">
                            <span><i class="fas fa-eye"></i> Student Live Preview</span>
                            <span style="font-size: 0.72rem; color: #16a34a;"><i class="fas fa-circle" style="font-size: 0.5rem;"></i> Live</span>
                        </div>

                        <div class="mock-student-card">
                            <div class="mock-card-header">
                                <div class="mock-logo-box" id="previewLogoBox">
                                    <?php if (!empty($internship['company_logo'])): ?>
                                        <img src="../<?php echo htmlspecialchars($internship['company_logo']); ?>" alt="Logo">
                                    <?php else: ?>
                                        <span id="previewInitials"><?php echo strtoupper(substr($internship['company_name'], 0, 2)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="mock-company-title" id="previewCompany"><?php echo htmlspecialchars($internship['company_name']); ?></div>
                                    <div class="mock-role-title" id="previewTitle"><?php echo htmlspecialchars($internship['internship_title']); ?></div>
                                </div>
                            </div>

                            <div class="mock-chips-row">
                                <span class="mock-chip mock-chip-stipend" id="previewStipendChip">
                                    <i class="fas fa-indian-rupee-sign"></i> <span id="previewStipend"><?php echo htmlspecialchars($internship['stipend'] ?: 'Competitive'); ?></span>
                                </span>
                                <span class="mock-chip mock-chip-mode" id="previewModeChip">
                                    <i class="fas <?php echo $internship['mode'] === 'Online' ? 'fa-house-laptop' : ($internship['mode'] === 'Hybrid' ? 'fa-shuffle' : 'fa-building'); ?>" id="previewModeIcon"></i> <span id="previewMode"><?php echo htmlspecialchars($internship['mode']); ?></span>
                                </span>
                                <span class="mock-chip mock-chip-target" id="previewTargetChip">
                                    <i class="fas fa-user-graduate"></i> <span id="previewTarget"><?php echo htmlspecialchars($internship['targeted_students'] ?: 'All Cohorts'); ?></span>
                                </span>
                            </div>

                            <div class="mock-details-grid">
                                <div class="mock-detail-item">
                                    <i class="fas fa-location-dot" style="color: #94a3b8;"></i>
                                    <span id="previewLocation"><?php echo htmlspecialchars($internship['location'] ?: 'Unspecified'); ?></span>
                                </div>
                                <div class="mock-detail-item">
                                    <i class="far fa-clock" style="color: #94a3b8;"></i>
                                    <span id="previewDuration"><?php echo htmlspecialchars($internship['duration'] ?: 'Standard'); ?></span>
                                </div>
                                <div class="mock-detail-item">
                                    <i class="fas fa-users-viewfinder" style="color: #94a3b8;"></i>
                                    <span id="previewSlots"><?php echo htmlspecialchars($internship['positions']); ?> Slots</span>
                                </div>
                                <div class="mock-detail-item">
                                    <i class="far fa-calendar" style="color: #94a3b8;"></i>
                                    <span id="previewStartDate"><?php echo !empty($internship['start_date']) ? date('M d, Y', strtotime($internship['start_date'])) : 'Immediate'; ?></span>
                                </div>
                            </div>

                            <div class="mock-deadline-badge">
                                <span><i class="far fa-hourglass-half"></i> Deadline</span>
                                <span id="previewDeadline">
                                    <?php 
                                    if (!empty($internship['application_deadline'])) {
                                        $dlTime = strtotime($internship['application_deadline']);
                                        echo (date('H:i', $dlTime) !== '00:00') ? date('M d, Y, h:i A', $dlTime) : date('M d, Y', $dlTime);
                                    } else {
                                        echo 'Open';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links Box -->
                    <div class="guideline-box">
                        <h4><i class="fas fa-link" style="color: var(--primary-maroon);"></i> Drive Access</h4>
                        <p style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem;">Direct link to this internship's student application portal:</p>
                        <button type="button" class="btn-action-sm btn-outline-action" style="width: 100%; justify-content: center;" onclick="copyPostingLink('<?php echo $shareUrl; ?>')">
                            <i class="far fa-copy"></i> Copy Direct Application Link
                        </button>
                    </div>

                </div>

            </div>
        </form>

    </main>

    <!-- Interactive Scripts for Live Preview and UX -->
    <script>
        // Mode Selector
        function selectMode(mode) {
            document.querySelectorAll('.mode-option-card').forEach(card => card.classList.remove('selected'));
            const selectedCard = document.getElementById('modeCard_' + mode);
            if (selectedCard) selectedCard.classList.add('selected');
            
            const radio = document.querySelector(`input[name="mode"][value="${mode}"]`);
            if (radio) radio.checked = true;

            updateLivePreview();
        }

        // Live Preview Updater
        function updateLivePreview() {
            const title = document.getElementById('input_title').value.trim() || 'Internship Role Title';
            const company = document.getElementById('input_company').value.trim() || 'Company Name';
            const location = document.getElementById('input_location').value.trim() || 'Work Location';
            const stipend = document.getElementById('input_stipend').value.trim() || 'Stipend / Benefits';
            const duration = document.getElementById('input_duration').value.trim() || 'Duration';
            const positions = document.getElementById('input_positions').value.trim() || '1';
            const targetSelect = document.getElementById('input_target');
            const targetText = targetSelect.options[targetSelect.selectedIndex]?.text.split('(')[0].trim() || 'All Cohorts';
            const startDateVal = document.getElementById('input_start_date').value;
            const deadlineVal = document.getElementById('input_deadline').value;

            // Selected Mode
            const selectedModeRadio = document.querySelector('input[name="mode"]:checked');
            const mode = selectedModeRadio ? selectedModeRadio.value : 'Offline';

            // Text Updates
            document.getElementById('previewTitle').textContent = title;
            document.getElementById('previewCompany').textContent = company;
            document.getElementById('previewLocation').textContent = location;
            document.getElementById('previewStipend').textContent = stipend;
            document.getElementById('previewDuration').textContent = duration;
            document.getElementById('previewSlots').textContent = positions + ' Slot' + (positions > 1 ? 's' : '');
            document.getElementById('previewTarget').textContent = targetText || 'Eligible Cohorts';

            // Initials fallback
            if (!document.getElementById('previewLogoBox').querySelector('img')) {
                const words = company.split(' ').filter(Boolean);
                let initials = 'IN';
                if (words.length >= 2) {
                    initials = (words[0][0] + words[1][0]).toUpperCase();
                } else if (words.length === 1 && words[0].length >= 2) {
                    initials = words[0].substring(0, 2).toUpperCase();
                }
                document.getElementById('previewInitials').textContent = initials;
            }

            // Mode Tag
            const modeSpan = document.getElementById('previewMode');
            const modeIcon = document.getElementById('previewModeIcon');
            modeSpan.textContent = mode === 'Offline' ? 'On-Site' : (mode === 'Online' ? 'Remote' : 'Hybrid');
            modeIcon.className = mode === 'Offline' ? 'fas fa-building' : (mode === 'Online' ? 'fas fa-house-laptop' : 'fas fa-shuffle');

            // Start Date
            document.getElementById('previewStartDate').textContent = startDateVal ? new Date(startDateVal).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Immediate / TBD';

            // Deadline
            if (deadlineVal) {
                const dlDate = new Date(deadlineVal);
                const options = { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' };
                document.getElementById('previewDeadline').textContent = dlDate.toLocaleDateString('en-US', options);
            } else {
                document.getElementById('previewDeadline').textContent = 'Open / Not Set';
            }
        }

        // Handle Company Logo Preview
        function handleLogoPreview(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewBox = document.getElementById('previewLogoBox');
                    previewBox.innerHTML = `<img src="${e.target.result}" alt="Company Logo">`;
                    document.getElementById('logoUploadPrompt').textContent = file.name;
                };
                reader.readAsDataURL(file);
            }
        }

        // Handle Document Upload File Names
        function handleDocsPreview(input) {
            const listWrap = document.getElementById('docsListPreview');
            listWrap.innerHTML = '';
            if (input.files && input.files.length > 0) {
                document.getElementById('docsUploadPrompt').textContent = `${input.files.length} new document(s) selected`;
                Array.from(input.files).forEach(file => {
                    const badge = document.createElement('div');
                    badge.className = 'preview-file-badge';
                    badge.innerHTML = `<i class="far fa-file-lines"></i> ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
                    listWrap.appendChild(badge);
                });
            }
        }

        // Copy Student Application Link Toast
        function copyPostingLink(url) {
            navigator.clipboard.writeText(url).then(() => {
                alert('Student application link copied to clipboard!');
            }).catch(() => {
                prompt('Copy this student application link:', url);
            });
        }
    </script>
</body>
</html>
