<?php
/**
 * Student - Internship Details & Apply
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

$id = get('id');
if (!$id) redirect('internships.php');

$internshipModel = new Internship();
$internship = $internshipModel->find($id);

if (!$internship) die("Internship not found.");

$userId = getUserId(); // USN/EnquiryNo
$userModel = new User();
$user = $userModel->find($userId, getInstitution()); // Use session institution
$usn = $user['username'];

// Check application status
$appModel = new InternshipApplication();
$hasApplied = $appModel->hasApplied($id, $usn);

$message = '';
$error = '';

// Check for existing resume for UI / Application logic
$existingResumePath = 'uploads/resumes/Student_Resumes/' . $usn . '_Resume.pdf';
$fullResumePath = __DIR__ . '/../../public/' . $existingResumePath;
$hasResumeFile = file_exists($fullResumePath);

// Handle Application
if (isPost() && isset($_POST['apply'])) {
    if ($hasApplied) {
        $error = "You have already applied.";
    } elseif (!$hasResumeFile) {
        $error = "Please build your resume in the Resume Builder before applying.";
    } else {
        $resumePath = $existingResumePath;
        $result = $appModel->apply($id, $usn, $resumePath);
        if ($result['success']) {
            $message = "Application submitted successfully!";
            $hasApplied = true;
        } else {
            $error = "Application failed: " . $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($internship['internship_title']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #800000; 
            --primary-dark: #5b1f1f;
            --accent-gold: #e9c66f;
            --bg-light: #f8fafc; 
            --white: #fff; 
            --text-dark: #0f172a; 
            --text-muted: #64748b; 
            --border: #e2e8f0;
            --glass: rgba(255, 255, 255, 0.8);
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }
        
        body { 
            background: var(--bg-light); 
            font-family: 'Outfit', sans-serif; 
            margin: 0; 
            color: var(--text-dark);
            line-height: 1.5;
        }
        
        .page-wrapper {
            max-width: 1280px;
            margin: 2rem auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
            align-items: start;
        }
        
        @media (max-width: 1024px) {
            .page-wrapper {
                grid-template-columns: 1fr;
            }
        }

        /* Hero Section */
        .hero-card {
            background: var(--white);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--accent-gold));
        }

        .hero-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .company-logo-large {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            object-fit: contain;
            background: #fff;
            padding: 10px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
        }

        .logo-placeholder-large {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            box-shadow: 0 10px 20px rgba(128, 0, 0, 0.2);
        }

        .job-title {
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.1rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .company-info i { color: var(--primary); }

        /* Content Sections */
        .content-card {
            background: var(--white);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }

        .section-header i {
            font-size: 1.25rem;
            color: var(--primary);
        }

        .section-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-dark);
        }

        .content-body {
            font-size: 1.05rem;
            color: #475569;
            line-height: 1.7;
            white-space: pre-line;
        }

        /* Sidebar Widgets */
        .sidebar {
            position: sticky;
            top: 100px;
        }

        .widget-card {
            background: var(--white);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .quick-stats {
            display: grid;
            gap: 1.25rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .stat-item:hover {
            border-color: var(--accent-gold);
            transform: translateX(5px);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .stat-content {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Apply Flow */
        .apply-btn {
            width: 100%;
            padding: 1.2rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(128, 0, 0, 0.3);
        }

        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(128, 0, 0, 0.4);
        }

        .applied-badge {
            width: 100%;
            padding: 1.2rem;
            background: #dcfce7;
            color: #166534;
            border-radius: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            border: 2px solid #bbf7d0;
        }

        .resume-picker {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .custom-file-upload {
            border: 2px dashed var(--border);
            border-radius: 12px;
            display: block;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #fdfdfd;
        }

        .custom-file-upload:hover {
            border-color: var(--primary);
            background: #fff;
        }

        .file-input { display: none; }

        /* Documents List */
        .doc-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .doc-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 0.75rem;
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.2s;
        }

        .doc-item:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .doc-item i { color: var(--primary); font-size: 1.2rem; }
        .doc-info { display: flex; flex-direction: column; }
        .doc-name { font-weight: 600; font-size: 0.95rem; }
        .doc-size { font-size: 0.75rem; color: var(--text-muted); }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Post-Application Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-container {
            background: rgba(255, 255, 255, 0.95);
            width: 90%;
            max-width: 550px;
            padding: 3rem;
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            transform: scale(0.9);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .modal-overlay.active .modal-container {
            transform: scale(1);
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-soft);
            color: var(--primary);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 2rem;
            animation: bounceIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .modal-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 1rem;
            letter-spacing: -0.025em;
        }

        .modal-desc {
            font-size: 1.1rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        .modal-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #fef2f2;
            color: #991b1b;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1.5rem;
            border: 1px solid #fecaca;
        }

        .modal-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            padding: 1.25rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            text-decoration: none;
            border-radius: 18px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s;
            box-shadow: 0 10px 15px -3px rgba(128, 0, 0, 0.3);
        }

        .modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(128, 0, 0, 0.4);
            filter: brightness(1.1);
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); opacity: 1; }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); }
        }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .page-wrapper {
                padding: 0 1rem;
                margin: 1rem auto;
                gap: 1.5rem;
            }

            .hero-card {
                padding: 1.5rem;
            }

            .hero-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 1.5rem;
            }

            .job-title {
                font-size: 1.75rem;
            }

            .company-info {
                flex-direction: column;
                gap: 0.5rem;
                font-size: 1rem;
            }

            .content-card {
                padding: 1.5rem;
            }

            .section-header h2 {
                font-size: 1.2rem;
            }

            .content-body {
                font-size: 0.95rem;
            }

            .widget-card {
                padding: 1.5rem;
            }

            .modal-container {
                padding: 2rem 1.5rem;
                border-radius: 24px;
            }

            .modal-title {
                font-size: 1.5rem;
            }

            .modal-desc {
                font-size: 1rem;
                margin-bottom: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .company-logo-large, .logo-placeholder-large {
                width: 80px;
                height: 80px;
            }

            .hero-card {
                padding: 1.25rem;
            }

            .hero-header {
                gap: 1rem;
            }

            .job-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="page-wrapper">
        <div class="main-content">
            <!-- Navigation Back -->
            <a href="internships.php" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted); text-decoration: none; margin-bottom: 1.5rem; font-weight: 600; font-size: 0.95rem; padding: 0.5rem 1rem; background: var(--white); border-radius: 12px; border: 1px solid var(--border); transition: all 0.2s;" onmouseover="this.style.color='var(--primary)'; this.style.borderColor='var(--primary)';" onmouseout="this.style.color='var(--text-muted)'; this.style.borderColor='var(--border)';">
                <i class="fas fa-arrow-left"></i> Back to Internships
            </a>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Hero Section -->
            <div class="hero-card">
                <div class="hero-header">
                    <?php 
                    $logoPath = !empty($internship['company_logo']) ? '../' . $internship['company_logo'] : '';
                    $logoExists = $logoPath && file_exists(__DIR__ . '/../../public/' . $internship['company_logo']);
                    
                    if ($logoExists): 
                    ?>
                        <img src="<?php echo $logoPath; ?>" class="company-logo-large" alt="Logo">
                    <?php else: ?>
                        <div class="logo-placeholder-large"><?php echo strtoupper(substr($internship['company_name'], 0, 2)); ?></div>
                    <?php endif; ?>
                    
                    <div class="title-block">
                        <h1 class="job-title"><?php echo htmlspecialchars($internship['internship_title']); ?></h1>
                        <div class="company-info">
                            <span class="company-name"><i class="fas fa-building"></i> <?php echo htmlspecialchars($internship['company_name']); ?></span>
                            <span class="location"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($internship['location']); ?></span>
                            <?php if ($internship['link']): ?>
                                <a href="<?php echo htmlspecialchars($internship['link']); ?>" target="_blank" style="color: var(--primary); text-decoration: none;"><i class="fas fa-globe"></i> Website</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="content-card">
                <div class="section-header">
                    <i class="fas fa-align-left"></i>
                    <h2>Opportunity Overview</h2>
                </div>
                <div class="content-body"><?php echo nl2br(htmlspecialchars($internship['description'])); ?></div>
            </div>

            <!-- Requirements -->
            <?php if ($internship['requirements']): ?>
                <div class="content-card">
                    <div class="section-header">
                        <i class="fas fa-list-check"></i>
                        <h2>Key Requirements</h2>
                    </div>
                    <div class="content-body"><?php echo nl2br(htmlspecialchars($internship['requirements'])); ?></div>
                </div>
            <?php endif; ?>

            <!-- Responsibilities -->
            <?php if ($internship['responsibilities']): ?>
                <div class="content-card">
                    <div class="section-header">
                        <i class="fas fa-tasks"></i>
                        <h2>Your Responsibilities</h2>
                    </div>
                    <div class="content-body"><?php echo nl2br(htmlspecialchars($internship['responsibilities'])); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <aside class="sidebar">
            <!-- Apply Box -->
            <div class="widget-card">
                <div class="section-header" style="border:none; margin-bottom:1rem;">
                    <h2>Join the Team</h2>
                </div>
                
                <?php if (!$hasApplied): ?>
                    <?php if ($hasResumeFile): ?>
                        <form method="POST">
                            <div style="background: rgba(233, 198, 111, 0.1); padding: 1.2rem; border-radius: 16px; border: 1px solid var(--accent-gold); margin-bottom: 1.5rem;">
                                <div style="display:flex; align-items:center; gap:0.5rem; color:var(--primary); font-weight:700; font-size:0.9rem; margin-bottom:0.5rem;">
                                    <i class="fas fa-sparkles"></i> RESUME READY
                                </div>
                                <div style="font-size: 0.85rem; color: #4a5568;">
                                    Your Lakshya-built resume is linked and will be automatically submitted with your application.
                                </div>
                                <a href="../<?php echo $existingResumePath; ?>" target="_blank" style="display:inline-block; margin-top:0.75rem; font-weight:700; font-size:0.8rem; color:var(--primary); text-transform:uppercase;">View Resume <i class="fas fa-arrow-right"></i></a>
                            </div>

                            <button type="submit" name="apply" class="apply-btn" style="margin-top:1.5rem;">
                                Submit Application <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="background: #fee2e2; padding: 1.2rem; border-radius: 16px; border: 1px solid #fecaca; margin-bottom: 1.5rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem; color:#991b1b; font-weight:700; font-size:0.95rem; margin-bottom:0.5rem;">
                                <i class="fas fa-exclamation-triangle"></i> Resume Required
                            </div>
                            <div style="font-size: 0.85rem; color: #7f1d1d; margin-bottom: 1rem;">
                                Before you can apply to opportunities, you must build and save your central resume in Lakshya.
                            </div>
                            <a href="resume_builder.php" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; background: #991b1b; color: white; border-radius: 8px; padding: 0.75rem; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                                <i class="fas fa-magic"></i> Go to Resume Builder
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="applied-badge">
                        <i class="fas fa-check-circle"></i> Application Active
                    </div>
                    <p style="text-align: center; font-size: 0.85rem; color: var(--text-muted); margin-top: 1rem;">
                        Successfully applied on <?php echo date('d M Y'); ?>. Check your status in the dashboard.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Quick Stats -->
            <div class="widget-card">
                <div class="section-header" style="border:none; margin-bottom:1.5rem;">
                    <h2>Quick Info</h2>
                </div>
                <div class="quick-stats">
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                        <div class="stat-content">
                            <span class="stat-label">Stipend</span>
                            <span class="stat-value"><?php echo htmlspecialchars($internship['stipend']); ?></span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="stat-content">
                            <span class="stat-label">Duration</span>
                            <span class="stat-value"><?php echo htmlspecialchars($internship['duration']); ?></span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-laptop-house"></i></div>
                        <div class="stat-content">
                            <span class="stat-label">Mode</span>
                            <span class="stat-value"><?php echo htmlspecialchars($internship['mode']); ?></span>
                        </div>
                    </div>
                    <div class="stat-item" style="background: #fff5f5; border-color: #feb2b2;">
                        <div class="stat-icon" style="background:#fff; color:#e53e3e;"><i class="fas fa-hourglass-end"></i></div>
                        <div class="stat-content">
                            <span class="stat-label" style="color:#c53030;">Deadline</span>
                            <span class="stat-value" style="color:#c53030;"><?php echo date('d M Y', strtotime($internship['application_deadline'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents & Resources -->
            <?php 
                $docs = json_decode($internship['description_documents'] ?? '', true);
                if (!empty($docs) || !empty($internship['link'])): 
            ?>
                <div class="widget-card">
                    <div class="section-header" style="border:none; margin-bottom:1rem;">
                        <h2>Resources</h2>
                    </div>
                    <div class="doc-list">
                        <?php if ($internship['link']): 
                            $isGoogleForm = (strpos($internship['link'], 'forms.gle') !== false || strpos($internship['link'], 'docs.google.com/forms') !== false);
                        ?>
                            <a href="<?php echo htmlspecialchars($internship['link']); ?>" target="_blank" class="doc-item" style="border-color: var(--accent-gold); background: #fffcf5;">
                                <i class="<?php echo $isGoogleForm ? 'fab fa-google' : 'fas fa-link'; ?>" style="color: #d97706;"></i>
                                <div class="doc-info">
                                    <span class="doc-name" style="color: #b45309;"><?php echo $isGoogleForm ? 'Google Form' : 'Application Link'; ?></span>
                                    <span class="doc-size" style="color: #d97706;">External Resource</span>
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($docs) && is_array($docs)): ?>
                            <?php foreach ($docs as $doc): ?>
                                <a href="../<?php echo $doc; ?>" target="_blank" class="doc-item">
                                    <i class="fas fa-file-pdf"></i>
                                    <div class="doc-info">
                                        <span class="doc-name"><?php echo truncate(basename($doc), 20); ?></span>
                                        <span class="doc-size">PDF Document</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </aside>
    </main>

    <?php if ($message && !empty($internship['link'])): 
        $link = $internship['link'];
        $isGoogleForm = (strpos($link, 'forms.gle') !== false || strpos($link, 'docs.google.com/forms') !== false);
        
        if ($isGoogleForm):
    ?>
    <!-- Post-Application Compulsory Link Modal -->
    <div id="applicationModal" class="modal-overlay active">
        <div class="modal-container">
            <div class="modal-icon">
                <i class="fab fa-google"></i>
            </div>
            <div class="modal-badge">Compulsory Next Step</div>
            <h2 class="modal-title">Complete your application</h2>
            <p class="modal-desc">
                Your application has been received in <strong>Lakshya</strong>. As the final compulsory step, you <strong>MUST</strong> fill out this Google Form required by the company.
            </p>
            <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" class="modal-btn" onclick="document.getElementById('applicationModal').classList.remove('active')">
                <i class="fab fa-google"></i> Fill Google Form Now
            </a>
            <button onclick="document.getElementById('applicationModal').classList.remove('active')" style="background: none; border: none; color: var(--text-muted); margin-top: 1.5rem; cursor: pointer; font-weight: 600; font-size: 0.9rem;">
                I'll do it later
            </button>
        </div>
    </div>
    <?php endif; endif; ?>
</body>
</html>

