<?php
/**
 * Student Feedback System for Lakshya Portal
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$username = getUsername();
$fullName = getFullName();
$institution = $_SESSION['institution'] ?? 'GMU';

require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$studentProfileModel = new StudentProfile();
$profile = $studentProfileModel->getByUserId($userId);

$db = getDB();

$success = Session::flash('success') ?: '';
$error = '';

// Handle feedback submission
if (isPost()) {
    $generalComments = trim((string)post('general_comments'));
    $newFeatureTitle = trim((string)post('new_feature_title'));
    $newFeatureDescription = trim((string)post('new_feature_description'));
    
    // CSRF verification is automatically handled by the security interceptor if set up, 
    // but we check just in case.
    $csrfToken = post('csrf_token');
    if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please refresh and try again.";
    } else {
        $attachmentPath = null;
        
        // Handle image upload if provided with strict multi-layer security validation
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['attachment'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = "File upload failed. Please try again.";
            } elseif (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                $error = "Invalid file upload detected.";
            } else {
                $maxFileSize = 10 * 1024 * 1024; // 10MB
                $allowedExts = ['png', 'jpg', 'jpeg'];
                $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                // 1. Extension, size, and null-byte check
                if ($file['size'] > $maxFileSize || $file['size'] <= 0) {
                    $error = "Attachment file size must be between 1 byte and 10MB.";
                } elseif (!in_array($fileExt, $allowedExts, true)) {
                    $error = "Invalid file type. Only PNG, JPG, and JPEG image proofs are allowed.";
                } elseif (strpos($file['name'], "\0") !== false) {
                    $error = "Invalid file name detected.";
                } else {
                    // 2. MIME type verification via finfo
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    $allowedImageMimes = ['image/png', 'image/jpeg', 'image/pjpeg'];

                    if (!in_array($mime, $allowedImageMimes, true)) {
                        $error = "Invalid image format detected (" . htmlspecialchars($mime) . "). Please upload a genuine PNG, JPG, or JPEG image.";
                    } else {
                        // 3. Scan for disguised executable binaries (PE/ELF) and embedded script tags
                        $fileHeader = file_get_contents($file['tmp_name'], false, null, 0, 4096);
                        
                        // Check for executable binary magic bytes (Windows PE "MZ" or Linux ELF "\x7fELF")
                        if (str_starts_with($fileHeader, "MZ") || str_starts_with($fileHeader, "\x7fELF")) {
                            $error = "Security violation: Executable binary files are strictly prohibited.";
                        } else {
                            $lowerHeader = strtolower($fileHeader);
                            if (strpos($lowerHeader, '<?php') !== false || 
                                strpos($lowerHeader, '<? ') !== false || 
                                strpos($lowerHeader, '<?=') !== false || 
                                strpos($lowerHeader, '<script') !== false ||
                                strpos($lowerHeader, '__halt_compiler') !== false) {
                                $error = "Security violation: Embedded scripts are not permitted in uploaded files.";
                            }
                        }

                        if (empty($error)) {
                            $uploadDir = UPLOADS_PATH . '/feedback';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }

                            $safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
                            $cleanExt = ($fileExt === 'jpeg') ? 'jpg' : $fileExt;
                            $newFileName = 'proof_' . $safeUsername . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $cleanExt;
                            $targetFilePath = $uploadDir . '/' . $newFileName;

                            // 4. Deep Image Verification & Sanitization (Strips polyglots, hidden binaries & EXIF exploits)
                            $imgInfo = @getimagesize($file['tmp_name']);
                            if ($imgInfo === false || $imgInfo[0] <= 0 || $imgInfo[1] <= 0) {
                                $error = "The uploaded file is not a valid image or is corrupted.";
                            } elseif ($imgInfo[0] > 10000 || $imgInfo[1] > 10000) {
                                $error = "Image dimensions are too large. Maximum supported dimensions: 10,000 x 10,000 pixels.";
                            } else {
                                $srcImg = null;
                                if ($cleanExt === 'jpg') {
                                    if ($imgInfo[2] !== IMAGETYPE_JPEG) {
                                        $error = "Mismatched image structure for JPEG.";
                                    } else {
                                        $srcImg = @imagecreatefromjpeg($file['tmp_name']);
                                    }
                                } elseif ($cleanExt === 'png') {
                                    if ($imgInfo[2] !== IMAGETYPE_PNG) {
                                        $error = "Mismatched image structure for PNG.";
                                    } else {
                                        $srcImg = @imagecreatefrompng($file['tmp_name']);
                                    }
                                }

                                if (empty($error)) {
                                    if (!$srcImg) {
                                        $error = "Failed to process the image. The file appears corrupted or invalid.";
                                    } else {
                                        $saved = false;
                                        if ($cleanExt === 'png') {
                                            imagealphablending($srcImg, false);
                                            imagesavealpha($srcImg, true);
                                            $saved = imagepng($srcImg, $targetFilePath, 8);
                                        } else {
                                            $saved = imagejpeg($srcImg, $targetFilePath, 90);
                                        }
                                        imagedestroy($srcImg);

                                        if ($saved) {
                                            @chmod($targetFilePath, 0644);
                                            $attachmentPath = 'uploads/feedback/' . $newFileName;
                                        } else {
                                            $error = "Failed to save the sanitized image. Please try again.";
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if (empty($error)) {
            if (empty($generalComments) && empty($newFeatureTitle) && empty($newFeatureDescription) && empty($attachmentPath)) {
                $error = "Please provide general comments, a feature suggestion, or an attachment proof.";
            } else {
                try {
                    $sql = "INSERT INTO portal_feedback (
                                student_id, student_name, institution, current_sem, branch, 
                                general_comments, new_feature_title, new_feature_description,
                                attachment_path
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        $username,
                        $profile['name'] ?? $fullName,
                        $institution,
                        $profile['semester'] ?? null,
                        $profile['department'] ?? null,
                        $generalComments ?: null,
                        $newFeatureTitle ?: null,
                        $newFeatureDescription ?: null,
                        $attachmentPath
                    ]);
                    
                    Session::flash('success', 'Thank you! Your feedback has been submitted successfully.');
                    redirect('feedback.php');
                    exit;
                } catch (Exception $e) {
                    $error = "Error saving feedback: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch feedback history for this student
$stmt = $db->prepare("SELECT * FROM portal_feedback WHERE student_id = ? ORDER BY created_at DESC");
$stmt->execute([$username]);
$feedbackHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark unread feedback replies as read
try {
    $stmtMarkRead = $db->prepare("UPDATE portal_feedback SET reply_read = 1 WHERE student_id = ? AND reply_read = 0");
    $stmtMarkRead->execute([$username]);
} catch (Exception $e) {
    // Ignore
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback & Suggestions - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #4a0000;
            --accent-gold: #D4AF37;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .container {
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            padding: 20px 30px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }

        .page-header {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .page-header h2 {
            font-size: 28px;
            color: var(--primary-maroon);
            font-weight: 800;
        }

        .page-header p {
            color: var(--text-muted);
            margin-top: 4px;
            font-size: 14px;
        }

        .layout-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
            flex-grow: 1;
            overflow: hidden;
            min-height: 0;
        }

        @media (min-width: 992px) {
            .layout-grid {
                grid-template-columns: 4fr 5fr;
            }
        }

        .layout-grid > div {
            min-width: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Card / Section styling */
        .feedback-card {
            background: var(--white);
            padding: 25px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(128, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            transition: var(--transition);
        }

        .feedback-card:hover {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.07);
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-maroon);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .section-title i {
            color: var(--accent-gold);
        }

        /* Form inside the card should scroll if needed */
        .feedback-card form {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            overflow-y: auto;
            padding-right: 8px;
        }

        /* Alert / Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            color: var(--text-main);
            background: #fafafa;
            transition: var(--transition);
            outline: none;
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .form-control:focus {
            border-color: var(--primary-maroon);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.08);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .form-divider {
            height: 1px;
            background: var(--border-color);
            margin: 20px 0;
            position: relative;
            flex-shrink: 0;
        }

        .form-divider span {
            position: absolute;
            top: 50%;
            left: 20px;
            transform: translateY(-50%);
            background: var(--white);
            padding: 0 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--primary-maroon);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 8px 16px -4px rgba(128, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: auto;
            flex-shrink: 0;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 12px 20px -4px rgba(128, 0, 0, 0.4);
        }

        /* History Table Styling */
        .history-table-container {
            width: 100%;
            overflow-y: auto;
            flex-grow: 1;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            min-height: 0;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
            table-layout: fixed;
        }

        .history-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 12px 16px;
            border-bottom: 1.5px solid var(--border-color);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .history-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
            color: #334155;
            line-height: 1.5;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .history-table tr:last-child td {
            border-bottom: none;
        }

        .date-col {
            font-weight: 600;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .feature-badge {
            background: #fff8e6;
            color: #b25e00;
            border: 1px solid #ffe6a3;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 4px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .empty-state i {
            font-size: 40px;
            color: #cbd5e1;
            margin-bottom: 12px;
        }

        .empty-state p {
            font-weight: 500;
        }

        /* Custom Scrollbars */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(128, 0, 0, 0.15);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(128, 0, 0, 0.3);
        }

        /* Collapsible text styles */
        .collapsible-text-container {
            position: relative;
        }
        .collapsible-text {
            max-height: 75px; /* approx 3 lines */
            overflow: hidden;
            transition: max-height 0.3s ease;
            position: relative;
        }
        .collapsible-text.expanded {
            max-height: none;
        }
        .collapsible-text:not(.expanded).has-more::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 24px;
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0), rgba(255, 255, 255, 1));
            pointer-events: none;
        }
        .collapsible-text:not(.expanded).has-more.admin-reply-box::after {
            background: linear-gradient(to bottom, rgba(240, 253, 244, 0), rgba(240, 253, 244, 1));
        }
        .toggle-text-btn {
            background: none;
            border: none;
            color: var(--primary-maroon);
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            padding: 4px 0 0 0;
            margin-top: 2px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            outline: none;
        }
        .toggle-text-btn:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h2>Feedback & Suggestions</h2>
                <p>Help us improve Lakshya Portal. Share your thoughts or suggest exciting new features!</p>
            </div>
            <a href="dashboard" style="text-decoration: none; color: var(--primary-maroon); font-weight: 700; font-size: 14px;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="layout-grid">
            <!-- Left: Form -->
            <div class="feedback-card">
                <h3 class="section-title">
                    <i class="fas fa-edit"></i> Submit Your Feedback
                </h3>

                <?php if ($success): ?>
                    <div class="alert alert-success" id="successAlert">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="feedback.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    
                    <div class="form-group">
                        <label for="general_comments">General Comments & Suggestions</label>
                        <textarea class="form-control" name="general_comments" id="general_comments" placeholder="What do you think about the portal? What can we improve?"><?php echo htmlspecialchars($_POST['general_comments'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-divider">
                        <span>Suggest a New Feature</span>
                    </div>

                    <div class="form-group">
                        <label for="new_feature_title">Feature Title</label>
                        <input type="text" class="form-control" name="new_feature_title" id="new_feature_title" placeholder="Give your feature idea a name..." value="<?php echo htmlspecialchars($_POST['new_feature_title'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="new_feature_description">Detailed Feature Description</label>
                        <textarea class="form-control" name="new_feature_description" id="new_feature_description" placeholder="Describe the feature in detail. How will it help students? How should it work?"><?php echo htmlspecialchars($_POST['new_feature_description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <label for="attachment" style="display: flex; align-items: center; justify-content: space-between;">
                            <span><i class="fas fa-paperclip" style="color: var(--primary-maroon); margin-right: 4px;"></i> Proof / Screenshot Attachment</span>
                            <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">(Optional)</span>
                        </label>
                        <input type="file" class="form-control" name="attachment" id="attachment" accept=".png,.jpg,.jpeg">
                        <small style="display: block; margin-top: 5px; font-size: 11px; color: var(--text-muted);">
                            Upload a screenshot proof highlighting any website loopholes, bugs, or errors (PNG, JPG, or JPEG up to 10MB).
                        </small>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Feedback
                    </button>
                </form>
            </div>

            <!-- Right: Submission History -->
            <div class="feedback-card">
                <h3 class="section-title">
                    <i class="fas fa-history"></i> Your Feedback History
                </h3>

                <?php if (empty($feedbackHistory)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-slash"></i>
                        <p>You haven't submitted any feedback yet.</p>
                        <p style="font-size: 13px; margin-top: 5px;">Your submissions will be displayed here.</p>
                    </div>
                <?php else: ?>
                    <div class="history-table-container">
                        <table class="history-table">
                            <colgroup>
                                <col style="width: 100px;">
                                <col style="width: 30%;">
                                <col style="width: 30%;">
                                <col style="width: 30%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>General Feedback</th>
                                    <th>Suggested Feature</th>
                                    <th>Official Response</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedbackHistory as $fb): ?>
                                    <tr>
                                        <td class="date-col">
                                             <?php echo date('d M Y', strtotime($fb['created_at'])); ?>
                                            <div style="font-size: 11px; font-weight: normal; color: var(--text-muted); margin-top: 2px;">
                                                <?php echo date('h:i A', strtotime($fb['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($fb['general_comments']): ?>
                                                <div class="collapsible-text-container">
                                                    <div class="collapsible-text"><?php echo nl2br(htmlspecialchars($fb['general_comments'])); ?></div>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-style: italic;">None</span>
                                            <?php endif; ?>

                                            <?php if (!empty($fb['attachment_path'])): ?>
                                                <div style="margin-top: 8px;">
                                                    <a href="<?php echo APP_URL . '/' . htmlspecialchars($fb['attachment_path']); ?>" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; color: #800000; background: #FEF2F2; border: 1px solid #FECACA; text-decoration: none;">
                                                        <i class="fas fa-paperclip"></i> View Proof
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($fb['new_feature_title']): ?>
                                                <span class="feature-badge">Feature Idea</span>
                                                <strong style="display: block; margin-bottom: 4px; font-size: 13px; color: var(--primary-maroon);">
                                                    <?php echo htmlspecialchars($fb['new_feature_title']); ?>
                                                </strong>
                                                <div class="collapsible-text-container">
                                                    <div class="collapsible-text" style="font-size: 13px; color: #475569;">
                                                        <?php echo nl2br(htmlspecialchars($fb['new_feature_description'])); ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-style: italic;">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($fb['admin_reply'])): ?>
                                                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px; border-radius: 8px;">
                                                    <div style="font-size: 11px; font-weight: 600; color: #166534; margin-bottom: 6px; display: flex; align-items: center; gap: 4px;">
                                                        <i class="fas fa-reply"></i> Response from <?php echo htmlspecialchars($fb['replied_by']); ?>
                                                    </div>
                                                    <div class="collapsible-text-container">
                                                        <div class="collapsible-text admin-reply-box" style="font-size: 13px; line-height: 1.5; color: #1e293b; word-break: break-word;">
                                                            <?php echo nl2br(htmlspecialchars($fb['admin_reply'])); ?>
                                                        </div>
                                                    </div>
                                                    <div style="font-size: 10px; color: #64748b; margin-top: 6px; text-align: right;">
                                                        <?php echo date('d M Y, h:i A', strtotime($fb['replied_at'])); ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-style: italic;">Awaiting response</span>
                                            <?php endif; ?>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.transition = 'opacity 0.5s ease, transform 0.5s ease, margin-bottom 0.5s ease, padding 0.5s ease, height 0.5s ease';
                    successAlert.style.opacity = '0';
                    successAlert.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        successAlert.style.display = 'none';
                    }, 500);
                }, 4000); // 4 seconds delay
            }

            // Collapsible text container logic
            const collapsibles = document.querySelectorAll('.collapsible-text');
            collapsibles.forEach(el => {
                if (el.scrollHeight > el.clientHeight + 4) {
                    el.classList.add('has-more');
                    
                    const btn = document.createElement('button');
                    btn.className = 'toggle-text-btn';
                    btn.innerHTML = '<i class="fas fa-chevron-down"></i> Read More';
                    
                    btn.addEventListener('click', function() {
                        if (el.classList.contains('expanded')) {
                            el.classList.remove('expanded');
                            btn.innerHTML = '<i class="fas fa-chevron-down"></i> Read More';
                        } else {
                            el.classList.add('expanded');
                            btn.innerHTML = '<i class="fas fa-chevron-up"></i> Read Less';
                        }
                    });
                    
                    el.parentNode.appendChild(btn);
                }
            });
        });
    </script>
</body>
</html>
