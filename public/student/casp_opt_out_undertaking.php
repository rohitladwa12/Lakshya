<?php
/**
 * Student - CASP Placement Opt-Out Undertaking Generator
 * Stores direct PDF in D:/LakshyaUploads/casp_undertakings/opt_out/OPT_OUT_{USN}_Undertaking.pdf
 * Exact visual & structural replica of public/Undertaking.pdf
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/src/Helpers/PlacementInterestHelper.php';
use App\Helpers\PlacementInterestHelper;

// 1. Authorization: Allow student, coordinator, or admin
if ((isset($_GET['view']) || isset($_GET['download'])) && !empty($_GET['usn'])) {
    requireAnyRole([ROLE_STUDENT, ROLE_DEPT_COORDINATOR, ROLE_PLACEMENT_OFFICER, ROLE_INTERNSHIP_OFFICER, ROLE_ADMIN, ROLE_HOD, ROLE_VC]);
    $requestedUsn = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_GET['usn']));
    $targetPdfPath = PlacementInterestHelper::getOptOutPdfPath($requestedUsn);
    if (file_exists($targetPdfPath) && filesize($targetPdfPath) > 0) {
        $disposition = isset($_GET['download']) ? 'attachment' : 'inline';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($targetPdfPath) . '"');
        header('Content-Length: ' . filesize($targetPdfPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($targetPdfPath);
        exit;
    } else {
        Session::flash('error', 'No generated opt-out undertaking PDF found for student ' . htmlspecialchars($requestedUsn));
        redirect(hasRole(ROLE_DEPT_COORDINATOR) ? '../coordinator/students_report.php' : 'casp_opt_out_undertaking.php');
    }
}

requireRole(ROLE_STUDENT);

$userId = getUserId();
$username = getUsername();
$fullName = getFullName();
$institution = $_SESSION['institution'] ?? getInstitution();

// 2. Fetch Student Profile & Academic Details
require_once ROOT_PATH . '/src/Models/StudentProfile.php';
$studentProfileModel = new StudentProfile();
$profile = $studentProfileModel->getProfile($userId) ?: [];

$studentUSN = $profile['usn'] ?? ($profile['student_id'] ?? $username);
$studentName = $profile['name'] ?? $fullName;
$studentDept = $profile['department'] ?? ($profile['course'] ?? '');
$studentSem = $profile['semester'] ?? '';

// Institution Branding
$isGMIT = (($profile['institution'] ?? $institution) === INSTITUTION_GMIT);
$instName = $isGMIT ? 'GM Institute of Technology' : 'GM University';
$instCity = 'Davangere';

// Official GMU logo disk path
$gmuLogoDiskPath = ROOT_PATH . '/public/assets/img/ai/gmulogo.png';
$gmuLogoBase64 = '';
if (file_exists($gmuLogoDiskPath)) {
    $gmuLogoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($gmuLogoDiskPath));
}

// Determine Specialisation
$specialisation = 'General';
if (!empty($profile['department'])) {
    $deptUpper = strtoupper($profile['department']);
    if (strpos($deptUpper, 'AIML') !== false || strpos($deptUpper, 'ARTIFICIAL') !== false) {
        $specialisation = 'Artificial Intelligence & Machine Learning (AIML)';
    } elseif (strpos($deptUpper, 'DATA SCIENCE') !== false || strpos($deptUpper, 'DS') !== false) {
        $specialisation = 'Data Science (CSE-DS)';
    } elseif (strpos($deptUpper, 'CYBER') !== false) {
        $specialisation = 'Cyber Security';
    } elseif (strpos($deptUpper, 'CLOUD') !== false) {
        $specialisation = 'Cloud Computing';
    } elseif (strpos($deptUpper, 'IOT') !== false) {
        $specialisation = 'Internet of Things (IoT)';
    } elseif (!empty($profile['course']) && $profile['course'] !== $profile['department']) {
        $specialisation = $profile['department'];
    } else {
        $specialisation = 'Core / General';
    }
}

// Compute Batch Year
$batchYear = date('Y');
if (preg_match('/(?:U|GMIT|4GM)?(\d{2})/i', $studentUSN, $m)) {
    $admissionYear = 2000 + (int)$m[1];
    $batchYear = $admissionYear + 4;
} else {
    $semNum = (int) $studentSem;
    $remainingYears = max(0, ceil((8 - $semNum) / 2));
    $batchYear = (int)date('Y') + $remainingYears;
}

// Contact Number & Email with Smart Verification
$contactNumber = '';
$emailId = '';
try {
    $db = getDB();
    if ($db) {
        // 1. Check student's active resume (most accurate student personal phone & email)
        $stmtRes = $db->prepare("SELECT phone, email FROM student_resumes WHERE student_id = ? OR student_id = ? ORDER BY last_updated DESC LIMIT 1");
        $stmtRes->execute([$studentUSN, $userId]);
        $resRow = $stmtRes->fetch();
        if (!empty($resRow['phone'])) {
            $contactNumber = trim($resRow['phone']);
        }
        if (!empty($resRow['email'])) {
            $emailId = trim($resRow['email']);
        }

        // 2. Check local users table (student login MOBILE_NO)
        if (empty($contactNumber)) {
            $stmtUser = $db->prepare("SELECT MOBILE_NO FROM users WHERE USER_NAME = ? LIMIT 1");
            $stmtUser->execute([$studentUSN]);
            $uMob = $stmtUser->fetchColumn();
            if (!empty($uMob)) {
                $contactNumber = trim($uMob);
            }
        }
    }
} catch (\Throwable $e) {}

// 3. Fallback to StudentProfile
if (empty($contactNumber)) {
    $contactNumber = $profile['student_mobile'] ?? ($profile['phone'] ?? '');
}
if (empty($contactNumber) && isset($_SESSION['user']['phone'])) {
    $contactNumber = $_SESSION['user']['phone'];
}

$parentName = $profile['father_name'] ?? ($profile['mother_name'] ?? '');

// Detection for ERP swapped numbers:
$erpParentMobile = $profile['parent_mobile'] ?? '';
$erpStudentMobile = $profile['student_mobile'] ?? '';
if (!empty($contactNumber) && !empty($erpParentMobile) && $contactNumber === $erpParentMobile && !empty($erpStudentMobile)) {
    $parentMobile = $erpStudentMobile;
} else {
    $parentMobile = $erpParentMobile;
}

// Primary file path on D Drive
$safeUsn = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentUSN);
$targetPdfPath = PlacementInterestHelper::getOptOutPdfPath($studentUSN);
$hasExistingPdf = file_exists($targetPdfPath) && filesize($targetPdfPath) > 0;

// Handle PDF View / Download for Student
if (isset($_GET['download']) || isset($_GET['view'])) {
    if ($hasExistingPdf) {
        $disposition = isset($_GET['download']) ? 'attachment' : 'inline';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($targetPdfPath) . '"');
        header('Content-Length: ' . filesize($targetPdfPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($targetPdfPath);
        exit;
    } else {
        Session::flash('error', 'No generated undertaking PDF found. Please fill and generate it below.');
        redirect('casp_opt_out_undertaking.php');
    }
}

// 3. Handle PDF Generation Form Submission
$successMsg = Session::flash('success') ?: '';
$errorMsg = Session::flash('error') ?: '';

if (isPost() && post('action') === 'generate_opt_out_pdf') {
    $formName = trim(post('student_name')) ?: $studentName;
    $formUsn = trim(post('student_usn')) ?: $studentUSN;
    $formDept = trim(post('department')) ?: $studentDept;
    $formSpec = trim(post('specialisation')) ?: $specialisation;
    $formBatch = trim(post('batch_year')) ?: $batchYear;
    $formContact = trim(post('contact_number')) ?: $contactNumber;
    $formEmail = trim(post('email_id')) ?: $emailId;
    $formDate = trim(post('undertaking_date')) ?: date('d / m / Y');
    $formParentName = trim(post('parent_name')) ?: $parentName;
    $formOptOutReason = trim(post('opt_out_reason')) ?: 'Higher Studies';
    $formRemarks = trim(post('remarks')) ?: '';
    $sigData = post('signature_data');

    if (empty($formRemarks)) {
        $formRemarks = $formOptOutReason;
    } else {
        $formRemarks = $formOptOutReason . ' - ' . $formRemarks;
    }

    try {
        require_once ROOT_PATH . '/vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => 'C:/Windows/Temp',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'format' => 'A4'
        ]);

        $sigHtml = '';
        if (!empty($sigData) && strpos($sigData, 'data:image') === 0) {
            $sigHtml = '<img src="' . htmlspecialchars($sigData) . '" style="max-height: 32px; max-width: 170px;"><br>';
        }

        // Exact HTML/CSS matching public/Undertaking.pdf
        $pdfHtml = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="utf-8">
        <style>
            body {
                font-family: "DejaVu Serif", "Times New Roman", serif;
                font-size: 11.5pt;
                color: #000000;
                line-height: 1.35;
                margin: 0;
                padding: 0;
            }
            .header-banner {
                background-color: #4A151D;
                color: #ffffff;
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 0px;
            }
            .banner-left {
                width: 25%;
                padding: 10px 10px 10px 15px;
                vertical-align: middle;
                text-align: left;
            }
            .banner-right {
                width: 75%;
                padding: 8px 15px 8px 5px;
                vertical-align: middle;
                text-align: right;
            }
            .trust-text {
                font-size: 9.5pt;
                font-style: italic;
                color: #f1f5f9;
                margin-bottom: 2px;
            }
            .univ-main-title {
                font-size: 18pt;
                font-weight: bold;
                letter-spacing: 1px;
                color: #ffffff;
                margin: 2px 0;
            }
            .univ-details {
                font-size: 8pt;
                line-height: 1.25;
                color: #f8fafc;
            }
            .gold-stripe {
                height: 3.5px;
                background-color: #E68A00;
                width: 100%;
                margin-bottom: 10px;
            }
            .page-titles {
                text-align: center;
                margin-bottom: 12px;
            }
            .title-univ {
                font-size: 11.5pt;
                font-weight: bold;
                color: #631620;
                text-decoration: underline;
                margin-bottom: 2px;
            }
            .title-casp {
                font-size: 11.5pt;
                font-weight: bold;
                color: #1a4f9c;
                margin-bottom: 3px;
            }
            .title-undertaking {
                font-size: 11.5pt;
                font-weight: bold;
                color: #631620;
                text-decoration: underline;
            }
            .section-head-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                margin-bottom: 6px;
            }
            .section-title {
                font-size: 11pt;
                font-weight: bold;
                color: #1a4f9c;
            }
            .date-right {
                text-align: right;
                font-size: 11pt;
                font-weight: bold;
                color: #000000;
            }
            .details-list {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 12px;
            }
            .details-list td {
                padding: 3px 0;
                font-size: 10.5pt;
                vertical-align: bottom;
            }
            .detail-bullet {
                width: 20px;
                text-align: center;
                font-weight: bold;
            }
            .detail-lbl {
                width: 185px;
                font-weight: bold;
                color: #000000;
            }
            .detail-fill {
                border-bottom: 1px solid #111111;
                font-weight: 600;
                color: #0f172a;
                padding-left: 5px;
            }
            .declaration-heading {
                font-size: 11pt;
                font-weight: bold;
                color: #1a4f9c;
                margin-top: 6px;
                margin-bottom: 6px;
            }
            .declaration-text {
                font-size: 10.5pt;
                line-height: 1.45;
                text-align: justify;
                margin-bottom: 8px;
            }
            .clause-ol {
                margin: 4px 0 10px 22px;
                padding: 0;
                font-size: 10pt;
                line-height: 1.45;
            }
            .clause-ol li {
                margin-bottom: 4px;
                text-align: justify;
            }
            .remarks-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                margin-bottom: 14px;
            }
            .remarks-lbl {
                width: 80px;
                font-size: 10.5pt;
                font-weight: bold;
                vertical-align: bottom;
            }
            .remarks-fill {
                border-bottom: 1px solid #111111;
                font-size: 10.5pt;
                font-weight: 600;
                vertical-align: bottom;
                padding-left: 4px;
            }
            .sig-row {
                width: 100%;
                border-collapse: collapse;
                margin-top: 16px;
                margin-bottom: 12px;
            }
            .sig-row td {
                vertical-align: bottom;
                font-size: 10.5pt;
            }
            .hr-divider {
                border: 0;
                border-top: 1px solid #475569;
                margin: 12px 0;
            }
            .office-header {
                text-align: center;
                font-size: 11pt;
                font-weight: bold;
                color: #1a4f9c;
                margin-bottom: 12px;
            }
            .office-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 4px;
            }
            .office-table td {
                font-size: 10pt;
                vertical-align: top;
            }
        </style>
        </head>
        <body>

        <!-- Top Header Banner (Exact replica of public/Undertaking.pdf) -->
        <table class="header-banner">
            <tr>
                <td class="banner-left">
                    ' . ($gmuLogoBase64 ? '<img src="' . $gmuLogoBase64 . '" style="height: 60px;">' : '<span style="font-size:16pt; font-weight:bold; color:#fde047;">GM UNIVERSITY</span>') . '
                </td>
                <td class="banner-right">
                    <div class="trust-text">Srishyla Educational Trust &reg;</div>
                    <div class="univ-main-title">GM UNIVERSITY</div>
                    <div class="univ-details">
                        (Established under the Karnataka State Act No. 19 of 2023)<br>
                        P. B. Road, Davanagere, Karnataka - 577 006<br>
                        Toll Free No.: 1800 123 7099, Ph: +91 93640-99720 /721<br>
                        E-mail: info@gmu.ac.in, Website: www.gmu.ac.in
                    </div>
                </td>
            </tr>
        </table>
        <div class="gold-stripe"></div>

        <!-- Document Titles -->
        <div class="page-titles">
            <div class="title-univ">GM University, Davangere.</div>
            <div class="title-casp">Career Advice and Student Placements (CASP)</div>
            <div class="title-undertaking">Undertaking / Declaration of Non-Participation in Placements Activities</div>
        </div>

        <!-- Student Details Heading & Date -->
        <table class="section-head-table">
            <tr>
                <td class="section-title">Student Details</td>
                <td class="date-right">Date: &nbsp; ' . htmlspecialchars($formDate) . '</td>
            </tr>
        </table>

        <!-- Details List with Underlines -->
        <table class="details-list">
            <tr>
                <td class="detail-bullet">&bull;</td>
                <td class="detail-lbl">Name of the Student:</td>
                <td class="detail-fill">' . htmlspecialchars($formName) . '</td>
            </tr>
            <tr>
                <td class="detail-bullet">&bull;</td>
                <td class="detail-lbl">USN / Register Number:</td>
                <td class="detail-fill">' . htmlspecialchars($formUsn) . '</td>
            </tr>
            <tr>
                <td class="detail-bullet">&bull;</td>
                <td class="detail-lbl">Department:</td>
                <td class="detail-fill">' . htmlspecialchars($formDept) . '</td>
            </tr>
            <tr>
                <td class="detail-bullet">&bull;</td>
                <td class="detail-lbl">Specialisation:</td>
                <td class="detail-fill">' . htmlspecialchars($formSpec) . '</td>
            </tr>
            <tr>
                <td class="detail-bullet">&bull;</td>
                <td class="detail-lbl">Batch (Year of Passing):</td>
                <td class="detail-fill">' . htmlspecialchars($formBatch) . '</td>
            </tr>
            <tr>
                <td class="detail-bullet">&bull;</td>
                <td class="detail-lbl">Contact Number:</td>
                <td class="detail-fill">' . htmlspecialchars($formContact) . '</td>
            </tr>
            <tr>
                <td class="detail-bullet">&bull;</td>
                <td class="detail-lbl">Email ID:</td>
                <td class="detail-fill">' . htmlspecialchars($formEmail) . '</td>
            </tr>
        </table>

        <!-- Declaration -->
        <div class="declaration-heading">Declaration</div>
        <p class="declaration-text">
            I, <strong>' . htmlspecialchars($formName) . '</strong>, a student of <strong>' . htmlspecialchars($formDept) . '</strong> Department, hereby declare that I am not interested in participating in the Placement activities conducted by the Career Advice and Student Placements (CASP) of GM University.
        </p>

        <p style="margin: 4px 0 2px 0; font-size: 10.5pt;">I understand that:</p>
        <ol class="clause-ol">
            <li>I will not be considered for campus recruitment opportunities organised by the CASP.</li>
            <li>I will not be approached, informed, or included in any communication or drive announcements related to placements.</li>
            <li>This decision is made voluntarily and with the full consent of my parents.</li>
            <li>I understand that this declaration is <strong>final and binding</strong> for the current academic year and cannot be revoked later.</li>
        </ol>

        <!-- Remarks -->
        <table class="remarks-table">
            <tr>
                <td class="remarks-lbl">Remarks:</td>
                <td class="remarks-fill">' . htmlspecialchars($formRemarks) . '</td>
            </tr>
        </table>

        <!-- Signatures -->
        <table class="sig-row">
            <tr>
                <td style="width: 50%;">
                    <strong>Student Signature:</strong><br><br>
                    <div style="border-bottom: 1px solid #111111; width: 220px; height: 32px; line-height: 32px; font-style: italic; color: #1a4f9c; font-weight: bold;">
                        ' . ($sigHtml ?: htmlspecialchars($formName)) . '
                    </div>
                </td>
                <td style="width: 50%;">
                    <strong>Parent\'s Name &amp; Signature:</strong><br><br>
                    <div style="border-bottom: 1px solid #111111; width: 270px; height: 32px; line-height: 32px; font-style: italic; color: #334155;">
                        ' . (!empty($formParentName) ? htmlspecialchars($formParentName) : '____________________________________') . '
                    </div>
                </td>
            </tr>
        </table>

        <div class="hr-divider"></div>

        <!-- Office Use Only -->
        <div class="office-header">For Office Use Only</div>
        <table class="office-table">
            <tr>
                <td style="width: 60%;">
                    Verified by Department Placement Coordinator: ________________________<br><br><br>
                    Signature of HOD: ______________________
                </td>
                <td style="width: 40%; text-align: right;">
                    <br><br>
                    Department Seal: [ &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ]
                </td>
            </tr>
        </table>

        </body>
        </html>
        ';

        // Set watermark if available
        if ($gmuLogoDiskPath && file_exists($gmuLogoDiskPath)) {
            $mpdf->SetWatermarkImage($gmuLogoDiskPath, 0.08, [90, 90]);
            $mpdf->showWatermarkImage = true;
        }

        $mpdf->WriteHTML($pdfHtml);
        
        $optOutDir = PlacementInterestHelper::getOptOutDir();
        $targetPdfPath = $optOutDir . '/OPT_OUT_' . $safeUsn . '_Undertaking.pdf';
        $mpdf->Output($targetPdfPath, \Mpdf\Output\Destination::FILE);

        // Record student opt-out decision
        PlacementInterestHelper::setDecision($formUsn, false, [
            'type' => 'opt_out',
            'has_pdf' => true,
            'pdf_file' => basename($targetPdfPath),
            'reason' => $formOptOutReason,
            'user_id' => $userId
        ]);

        Session::flash('success', "Placement Opt-Out Undertaking successfully generated and submitted to CASP.");
        redirect('casp_opt_out_undertaking.php?generated=1');
    } catch (\Throwable $ex) {
        $errorMsg = "Failed to generate PDF: " . $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CASP Placement Opt-Out Undertaking - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --brand: #4A151D;
            --brand-gold: #E68A00;
            --navy: #1a4f9c;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --surface: #ffffff;
            --radius: 14px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text-dark);
            min-height: 100vh;
            padding-bottom: 60px;
        }

        .page-wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 18px;
            transition: color .2s;
        }
        .back-link:hover { color: var(--brand); }

        /* Top Notification Cards */
        .alert-box {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

        /* Existing PDF Download Box */
        .existing-card {
            background: #ffffff;
            border: 1px solid #bbf7d0;
            border-radius: var(--radius);
            padding: 18px 22px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 12px rgba(5,150,105,0.08);
        }

        .doc-paper-container {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-radius: 4px;
            margin: 0 auto;
            max-width: 820px;
            overflow: hidden;
        }

        /* Banner matching PDF */
        .paper-header-banner {
            background-color: #4A151D;
            color: #ffffff;
            padding: 14px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .paper-gold-stripe {
            height: 4px;
            background-color: #E68A00;
            width: 100%;
        }

        .paper-body {
            padding: 30px 40px;
            font-family: 'Georgia', serif;
            color: #000;
            font-size: 14px;
            line-height: 1.5;
        }

        .form-row {
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-row label {
            width: 220px;
            font-weight: bold;
            font-size: 13.5px;
            color: #000;
            flex-shrink: 0;
        }
        .form-row input, .form-row select {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-family: inherit;
            font-size: 13.5px;
            background: #fdfdfd;
        }
        .form-row input:focus, .form-row select:focus {
            outline: none;
            border-color: #4A151D;
            background: #ffffff;
        }

        .btn-generate {
            background: #4A151D;
            color: #ffffff;
            padding: 13px 26px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(74, 21, 29, 0.3);
            transition: all 0.2s;
        }
        .btn-generate:hover {
            background: #330e14;
            transform: translateY(-1px);
        }
        .btn-view {
            background: #1a4f9c;
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
    </style>
</head>
<body>

<div class="page-wrap">
    <a href="dashboard.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <?php if ($successMsg): ?>
        <div class="alert-box alert-success">
            <i class="fas fa-check-circle" style="font-size: 20px;"></i>
            <div><?php echo htmlspecialchars($successMsg); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert-box alert-error">
            <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i>
            <div><?php echo htmlspecialchars($errorMsg); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($hasExistingPdf): ?>
        <div class="existing-card">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; background: #ecfdf5; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #059669; font-size: 22px;">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div>
                    <h4 style="font-size: 15px; font-weight: 700; margin: 0 0 4px; color: #065f46;">
                        Generated Opt-Out Undertaking Active
                    </h4>
                    <p style="font-size: 12.5px; color: #64748b; margin: 0;">
                        Your opt-out declaration has been securely recorded and submitted to your Department Coordinator.
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="view_undertaking.php?usn=<?php echo urlencode($formUsn); ?>&type=opt_out" target="_blank" class="btn-view">
                    <i class="fas fa-eye"></i> View PDF
                </a>
                <a href="view_undertaking.php?usn=<?php echo urlencode($formUsn); ?>&type=opt_out&download=1" class="btn-view" style="background:#059669;">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Document Container Replica -->
    <div class="doc-paper-container">
        <!-- Top Banner matching Undertaking.pdf -->
        <div class="paper-header-banner">
            <div>
                <?php if ($gmuLogoBase64): ?>
                    <img src="<?php echo $gmuLogoBase64; ?>" style="height: 58px;" alt="GMU">
                <?php else: ?>
                    <span style="font-size: 20px; font-weight: bold; color: #fde047;">GM UNIVERSITY</span>
                <?php endif; ?>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 11px; font-style: italic; color: #f1f5f9;">Srishyla Educational Trust &reg;</div>
                <div style="font-size: 19px; font-weight: 700; letter-spacing: 1px; color: #ffffff;">GM UNIVERSITY</div>
                <div style="font-size: 9px; line-height: 1.3; color: #f8fafc;">
                    (Established under the Karnataka State Act No. 19 of 2023)<br>
                    P. B. Road, Davanagere, Karnataka - 577 006<br>
                    Toll Free: 1800 123 7099 &bull; Ph: +91 93640-99720 /721<br>
                    info@gmu.ac.in &bull; www.gmu.ac.in
                </div>
            </div>
        </div>
        <div class="paper-gold-stripe"></div>

        <form method="POST" action="casp_opt_out_undertaking.php" class="paper-body">
            <input type="hidden" name="action" value="generate_opt_out_pdf">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

            <!-- Titles -->
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="font-size: 15px; font-weight: bold; color: #631620; text-decoration: underline;">GM University, Davangere.</div>
                <div style="font-size: 15px; font-weight: bold; color: #1a4f9c; margin: 3px 0;">Career Advice and Student Placements (CASP)</div>
                <div style="font-size: 15px; font-weight: bold; color: #631620; text-decoration: underline;">Undertaking / Declaration of Non-Participation in Placements Activities</div>
            </div>

            <!-- Student Details Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 2px solid #1a4f9c; padding-bottom: 6px;">
                <div style="font-size: 15px; font-weight: bold; color: #1a4f9c;">Student Details</div>
                <div style="font-size: 13.5px; font-weight: bold;">
                    Date: <input type="text" name="undertaking_date" value="<?php echo date('d / m / Y'); ?>" style="width: 120px; padding: 3px 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; text-align: center;">
                </div>
            </div>

            <!-- Detail Form Fields -->
            <div class="form-row">
                <label>&bull; Name of the Student:</label>
                <input type="text" name="student_name" value="<?php echo htmlspecialchars($studentName); ?>" required>
            </div>
            <div class="form-row">
                <label>&bull; USN / Register Number:</label>
                <input type="text" name="student_usn" value="<?php echo htmlspecialchars($studentUSN); ?>" required>
            </div>
            <div class="form-row">
                <label>&bull; Department:</label>
                <input type="text" name="department" value="<?php echo htmlspecialchars($studentDept); ?>" required>
            </div>
            <div class="form-row">
                <label>&bull; Specialisation:</label>
                <input type="text" name="specialisation" value="<?php echo htmlspecialchars($specialisation); ?>">
            </div>
            <div class="form-row">
                <label>&bull; Batch (Year of Passing):</label>
                <input type="text" name="batch_year" value="<?php echo htmlspecialchars($batchYear); ?>">
            </div>
            <div class="form-row">
                <label>&bull; Contact Number:</label>
                <input type="text" name="contact_number" value="<?php echo htmlspecialchars($contactNumber); ?>" required>
            </div>
            <div class="form-row">
                <label>&bull; Email ID:</label>
                <input type="email" name="email_id" value="<?php echo htmlspecialchars($emailId); ?>" required>
            </div>

            <!-- Declaration -->
            <div style="margin-top: 24px;">
                <div style="font-size: 15px; font-weight: bold; color: #1a4f9c; margin-bottom: 8px;">Declaration</div>
                <p style="font-size: 13.5px; line-height: 1.5; text-align: justify; margin-bottom: 10px;">
                    I, <strong><?php echo htmlspecialchars($studentName); ?></strong>, a student of <strong><?php echo htmlspecialchars($studentDept); ?></strong> Department, hereby declare that I am not interested in participating in the Placement activities conducted by the Career Advice and Student Placements (CASP) of GM University.
                </p>
                <p style="font-size: 13px; font-weight: bold; margin-bottom: 4px;">I understand that:</p>
                <ol style="margin-left: 24px; font-size: 13px; line-height: 1.5; color: #1e293b;">
                    <li>I will not be considered for campus recruitment opportunities organised by the CASP.</li>
                    <li>I will not be approached, informed, or included in any communication or drive announcements related to placements.</li>
                    <li>This decision is made voluntarily and with the full consent of my parents.</li>
                    <li>I understand that this declaration is <strong>final and binding</strong> for the current academic year and cannot be revoked later.</li>
                </ol>
            </div>

            <!-- Reason & Remarks -->
            <div style="margin-top: 18px;">
                <div class="form-row">
                    <label>Reason for Opting Out:</label>
                    <select name="opt_out_reason">
                        <option value="Higher Studies (MS / M.Tech / MBA)">Higher Studies (MS / M.Tech / MBA)</option>
                        <option value="Competitive Exams (GATE / UPSC / GRE)">Competitive Exams (GATE / UPSC / GRE)</option>
                        <option value="Family Business">Family Business</option>
                        <option value="Entrepreneurship / Startup">Entrepreneurship / Startup</option>
                        <option value="Off-Campus Placement Offer">Off-Campus Placement Offer</option>
                        <option value="Personal / Other">Personal / Other Reasons</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>Remarks / Notes:</label>
                    <input type="text" name="remarks" placeholder="Optional brief explanation...">
                </div>
            </div>

            <!-- Digital Signature Studio Pad -->
            <div class="digital-sign-card" style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 18px 20px; margin: 18px 0 22px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                    <div style="font-weight: 700; font-size: 14px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-pen-nib" style="color: #4A151D;"></i> Student Digital Signature Pad
                    </div>
                    <div>
                        <button type="button" id="btnClearSig" onclick="clearSignature()" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <i class="fas fa-eraser"></i> Clear Signature
                        </button>
                    </div>
                </div>

                <div style="position: relative; background: #ffffff; border: 2px dashed #94a3b8; border-radius: 8px; overflow: hidden; touch-action: none;">
                    <canvas id="sigCanvas" width="600" height="140" style="width: 100%; height: 140px; display: block; cursor: crosshair;"></canvas>
                    <div id="sigPlaceholder" style="position: absolute; inset: 0; pointer-events: none; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 13px; font-style: italic;">
                        <i class="fas fa-signature" style="margin-right: 6px;"></i> Draw your signature here using mouse, finger, or stylus
                    </div>
                </div>
                <input type="hidden" name="signature_data" id="signature_data">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px; font-size: 11.5px; color: #64748b;">
                    <span><i class="fas fa-lock"></i> Digital signature will be placed onto the official document and archived.</span>
                    <span style="font-weight: 600; color: #4A151D;">USN: <?php echo htmlspecialchars($studentUSN); ?></span>
                </div>
            </div>

            <!-- Parent Name & Signature -->
            <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #e2e8f0;">
                <div class="form-row">
                    <label>Parent\'s / Guardian\'s Name:</label>
                    <input type="text" name="parent_name" value="<?php echo htmlspecialchars($parentName); ?>" placeholder="Full name of parent" required>
                </div>
            </div>

            <!-- Office Use Only Mockup -->
            <div style="margin-top: 24px; padding: 14px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px;">
                <div style="text-align: center; font-weight: bold; color: #1a4f9c; font-size: 13px; margin-bottom: 8px;">
                    For Office Use Only
                </div>
                <div style="font-size: 12px; color: #64748b; line-height: 1.5;">
                    &bull; Verified by Department Placement Coordinator: ____________________<br>
                    &bull; Signature of HOD: ____________________ &nbsp; &nbsp; &nbsp; &nbsp; &bull; Department Seal: [ &nbsp; &nbsp; &nbsp; &nbsp; ]
                </div>
            </div>

            <div style="text-align: center; margin-top: 28px;">
                <button type="submit" class="btn-generate">
                    <i class="fas fa-file-signature"></i> Confirm &amp; Generate Official Undertaking PDF
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const canvas = document.getElementById('sigCanvas');
const ctx = canvas.getContext('2d');
const placeholder = document.getElementById('sigPlaceholder');
const sigInput = document.getElementById('signature_data');
let isDrawing = false;
let hasDrawn = false;

// Configure stroke
ctx.strokeStyle = '#002663';
ctx.lineWidth = 2.8;
ctx.lineCap = 'round';
ctx.lineJoin = 'round';

function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top) * scaleY
    };
}

function startDrawing(e) {
    e.preventDefault();
    isDrawing = true;
    hasDrawn = true;
    if (placeholder) placeholder.style.display = 'none';
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
}

function draw(e) {
    if (!isDrawing) return;
    e.preventDefault();
    const pos = getPos(e);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
}

function stopDrawing() {
    if (isDrawing) {
        isDrawing = false;
        ctx.closePath();
        if (sigInput) sigInput.value = canvas.toDataURL('image/png');
    }
}

canvas.addEventListener('pointerdown', startDrawing);
canvas.addEventListener('pointermove', draw);
canvas.addEventListener('pointerup', stopDrawing);
canvas.addEventListener('pointercancel', stopDrawing);
canvas.addEventListener('pointerleave', stopDrawing);

function clearSignature() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasDrawn = false;
    if (placeholder) placeholder.style.display = 'flex';
    if (sigInput) sigInput.value = '';
}

// Enforce drawing signature on submit
document.querySelector('form').addEventListener('submit', (e) => {
    if (!hasDrawn || !sigInput.value) {
        e.preventDefault();
        alert('Please draw your digital signature on the signature pad before submitting.');
        canvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
        canvas.parentElement.style.borderColor = '#ef4444';
        setTimeout(() => { canvas.parentElement.style.borderColor = '#94a3b8'; }, 2500);
        return false;
    }
});
</script>

</body>
</html>
