<?php
/**
 * Student - CASP Placement Policy & Undertaking Generator (For Interested / Opt-In Students)
 * Stores direct PDF in D:/LakshyaUploads/casp_undertakings/policy/POLICY_{USN}_Undertaking.pdf
 * Exact visual & structural replica of public/CASP_Placement_Policy_Undertaking.pdf (2 Pages)
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/src/Helpers/PlacementInterestHelper.php';
use App\Helpers\PlacementInterestHelper;

// 1. Authorization: Allow student, coordinator, or admin
if ((isset($_GET['view']) || isset($_GET['download'])) && !empty($_GET['usn'])) {
    requireAnyRole([ROLE_STUDENT, ROLE_DEPT_COORDINATOR, ROLE_PLACEMENT_OFFICER, ROLE_INTERNSHIP_OFFICER, ROLE_ADMIN, ROLE_HOD, ROLE_VC]);
    $requestedUsn = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_GET['usn']));
    $targetPdfPath = PlacementInterestHelper::getPolicyPdfPath($requestedUsn);
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
        Session::flash('error', 'No generated placement policy PDF found for student ' . htmlspecialchars($requestedUsn));
        redirect(hasRole(ROLE_DEPT_COORDINATOR) ? '../coordinator/students_report.php' : 'casp_policy_undertaking.php');
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
$branchSem = trim($studentDept . (!empty($studentSem) ? (" & Semester " . $studentSem) : ''));

$parentName = $profile['father_name'] ?? ($profile['mother_name'] ?? '');

// Resolve Student Contact Number & Email with Smart Verification
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

// Detection for ERP swapped numbers:
$erpParentMobile = $profile['parent_mobile'] ?? '';
$erpStudentMobile = $profile['student_mobile'] ?? '';
if (!empty($contactNumber) && !empty($erpParentMobile) && $contactNumber === $erpParentMobile && !empty($erpStudentMobile)) {
    $parentMobile = $erpStudentMobile;
} else {
    $parentMobile = $erpParentMobile;
}

// Logo
$gmuLogoDiskPath = ROOT_PATH . '/public/assets/img/ai/gmulogo.png';
$gmuLogoBase64 = '';
if (file_exists($gmuLogoDiskPath)) {
    $gmuLogoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($gmuLogoDiskPath));
}

// Check if existing PDF already exists on D Drive
$safeUsn = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentUSN);
$targetPdfPath = PlacementInterestHelper::getPolicyPdfPath($studentUSN);
$hasExistingPdf = file_exists($targetPdfPath) && filesize($targetPdfPath) > 0;

// Stream / Download Handler for Student
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
        Session::flash('error', 'No generated policy PDF found. Please fill and sign below.');
        redirect('casp_policy_undertaking.php');
    }
}

// 3. Handle PDF Generation Form Submission
$successMsg = Session::flash('success') ?: '';
$errorMsg = Session::flash('error') ?: '';

if (isPost() && post('action') === 'generate_policy_pdf') {
    $formName = trim(post('student_name')) ?: $studentName;
    $formUsn = trim(post('student_usn')) ?: $studentUSN;
    $formBranchSem = trim(post('branch_semester')) ?: $branchSem;
    $formParentName = trim(post('parent_name')) ?: $parentName;
    $formMobile = trim(post('student_mobile')) ?: $contactNumber;
    $formDate = trim(post('policy_date')) ?: date('d/m/Y');
    $sigData = post('signature_data');

    try {
        require_once ROOT_PATH . '/vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => 'C:/Windows/Temp',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'format' => 'A4'
        ]);

        $sigHtml = '';
        if (!empty($sigData) && strpos($sigData, 'data:image') === 0) {
            $sigHtml = '<img src="' . htmlspecialchars($sigData) . '" style="max-height: 28px; max-width: 130px;"><br>';
        }

        // Exact replica of public/CASP_Placement_Policy_Undertaking.pdf
        $pdfHtml = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="utf-8">
        <style>
            body {
                font-family: "DejaVu Sans", "Helvetica", Arial, sans-serif;
                font-size: 8.8pt;
                color: #000000;
                line-height: 1.35;
                margin: 0;
                padding: 0;
            }
            .page-footer-text {
                font-size: 7.5pt;
                color: #334155;
                text-align: center;
                margin-top: 14px;
            }
            .header-center {
                text-align: center;
                margin-bottom: 10px;
            }
            .univ-name {
                font-size: 11pt;
                font-weight: bold;
                letter-spacing: 0.5px;
                margin-top: 4px;
                color: #000000;
            }
            .casp-line {
                font-size: 9.5pt;
                font-weight: bold;
                margin: 2px 0;
                color: #000000;
            }
            .policy-line {
                font-size: 9.5pt;
                font-weight: bold;
                margin: 2px 0;
                color: #000000;
            }
            .applicable-line {
                font-size: 8.5pt;
                font-style: italic;
                margin-top: 2px;
                color: #1e293b;
            }
            .cand-title {
                font-size: 8.8pt;
                font-weight: bold;
                margin-top: 8px;
                margin-bottom: 3px;
                color: #000000;
            }
            .cand-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 10px;
            }
            .cand-table td {
                border: 1px solid #111111;
                padding: 4.5px 8px;
                font-size: 8.5pt;
            }
            .cand-lbl {
                width: 45%;
                font-weight: bold;
            }
            .cand-val {
                width: 55%;
                color: #0f172a;
            }
            .sec-title {
                font-size: 8.8pt;
                font-weight: bold;
                margin-top: 6px;
                margin-bottom: 3px;
                color: #000000;
            }
            .bullet-list {
                margin: 2px 0 6px 14px;
                padding: 0;
                font-size: 8.4pt;
                line-height: 1.35;
            }
            .bullet-list li {
                margin-bottom: 3.5px;
                text-align: justify;
            }
            .undertaking-p {
                font-size: 8.5pt;
                line-height: 1.4;
                text-align: justify;
                margin-top: 4px;
                margin-bottom: 8px;
            }
            .sig-grid {
                width: 100%;
                border-collapse: collapse;
                margin-top: 6px;
                margin-bottom: 6px;
            }
            .sig-grid th {
                border: 1px solid #111111;
                padding: 5px 4px;
                font-size: 7.5pt;
                font-weight: bold;
                text-align: center;
                background-color: #ffffff;
            }
            .sig-grid td {
                border: 1px solid #111111;
                padding: 6px 6px;
                font-size: 8pt;
                height: 52px;
                vertical-align: top;
            }
            .official-note {
                border: 1px solid #111111;
                padding: 4px 8px;
                font-size: 7.8pt;
                font-weight: bold;
                text-align: center;
                margin-top: 4px;
                margin-bottom: 8px;
            }
        </style>
        </head>
        <body>

        <!-- PAGE 1 -->
        <div class="header-center">
            ' . ($gmuLogoBase64 ? '<img src="' . $gmuLogoBase64 . '" style="height: 48px;">' : '') . '
            <div class="univ-name">GM UNIVERSITY</div>
            <div class="casp-line">CAREER ADVANCEMENT &amp; STUDENT PLACEMENTS (CASP)</div>
            <div class="policy-line">STUDENT PLACEMENT POLICY &amp; UNDERTAKING</div>
            <div class="applicable-line">Applicable to all students participating in CASP placement and recruitment activities</div>
        </div>

        <div class="cand-title">CANDIDATE DETAILS</div>
        <table class="cand-table">
            <tr>
                <td class="cand-lbl">Student Name</td>
                <td class="cand-val">' . htmlspecialchars($formName) . '</td>
            </tr>
            <tr>
                <td class="cand-lbl">USN / Registration No.</td>
                <td class="cand-val">' . htmlspecialchars($formUsn) . '</td>
            </tr>
            <tr>
                <td class="cand-lbl">Branch &amp; Semester</td>
                <td class="cand-val">' . htmlspecialchars($formBranchSem) . '</td>
            </tr>
            <tr>
                <td class="cand-lbl">Parent/Guardian Name</td>
                <td class="cand-val">' . htmlspecialchars($formParentName) . '</td>
            </tr>
            <tr>
                <td class="cand-lbl">Student Mobile No.</td>
                <td class="cand-val">' . htmlspecialchars($formMobile) . '</td>
            </tr>
            <tr>
                <td class="cand-lbl">Date</td>
                <td class="cand-val">' . htmlspecialchars($formDate) . '</td>
            </tr>
        </table>

        <div class="sec-title">1. Mandatory Registration &amp; Eligibility</div>
        <ul class="bullet-list">
            <li>All eligible students shall register on the Lakshya Portal within the prescribed deadline. Students who do not register may not be considered for placement drives.</li>
            <li>Students shall provide complete, accurate and truthful academic/personal information and shall independently verify company eligibility requirements including percentage/CGPA, backlogs, branch, year of passing, skills and other criteria.</li>
            <li>CASP may verify eligibility against official University/academic records; meeting a company’s stated eligibility threshold is the student’s responsibility.</li>
        </ul>

        <div class="sec-title">2. Attendance &amp; Participation</div>
        <ul class="bullet-list">
            <li>Registration for a company/drive constitutes a commitment to participate. Attendance is mandatory for the pre-placement talk, aptitude/assessment, GD, technical/HR rounds, interviews and all other applicable stages.</li>
            <li>Absence without prior approval or a valid reason, or repeated non-participation, may result in cancellation/suspension/disqualification from placement opportunities.</li>
        </ul>

        <div class="sec-title">3. Professional Dress, Conduct &amp; Online Etiquette</div>
        <ul class="bullet-list">
            <li>Students shall follow the prescribed professional dress code and maintain a well-groomed appearance during campus placement activities.</li>
            <li>For virtual activities, students shall join on time, keep the camera ON whenever required, use a suitable professional environment/background and maintain professional communication and behaviour.</li>
            <li>Students shall treat recruiters, company representatives, CASP staff, faculty and fellow students respectfully and shall follow all instructions and timelines communicated by CASP/recruiters.</li>
            <li>Salary/offer negotiations during recruitment are not permitted unless specifically allowed by the recruiter.</li>
        </ul>

        <div class="sec-title">4. Integrity, Confidentiality &amp; Prohibited Activities</div>
        <ul class="bullet-list">
            <li>Fake/forged/altered documents, cheating or malpractice, misconduct, unauthorised sharing of question papers, assessment links, company documents or confidential recruitment information are strictly prohibited.</li>
            <li>Students shall not misuse social media/online platforms in a manner that adversely affects GM University, CASP, recruiters or other students, or otherwise damage the University–recruiter relationship.</li>
        </ul>

        <div class="sec-title">5. Placement Eligibility After Selection / Package Rules</div>
        <ul class="bullet-list">
            <li>Students placed below &#8377;3 LPA may participate in subsequent opportunities, but shall not participate in another drive below &#8377;3 LPA.</li>
        </ul>

        <div class="page-footer-text">GM University &ndash; CASP | Student Placement Policy &amp; Undertaking</div>

        <!-- PAGE BREAK TO PAGE 2 -->
        <pagebreak />

        <!-- PAGE 2 HEADER -->
        <div style="text-align: center; margin-bottom: 8px;">
            ' . ($gmuLogoBase64 ? '<img src="' . $gmuLogoBase64 . '" style="height: 38px;">' : '') . '
        </div>

        <ul class="bullet-list" style="margin-top: 0;">
            <li>Students placed between &#8377;4 LPA and &#8377;8 LPA shall not participate in further drives in the &#8377;4&ndash;&#8377;8 LPA range, but may participate in opportunities of &#8377;9 LPA and above, subject to company eligibility.</li>
            <li>Students placed above &#8377;8 LPA shall not participate in lower package categories, but may participate in opportunities of &#8377;9 LPA and above, subject to company eligibility.</li>
            <li>For every drive, company-specific eligibility and recruitment instructions shall prevail wherever they are stricter than the general CASP policy.</li>
        </ul>

        <div class="sec-title">6. Offer Acceptance &amp; Joining Commitment</div>
        <ul class="bullet-list">
            <li>Before participating in final selection, students shall carefully consider the role, location, compensation, bond/service agreement, joining date and other employment terms.</li>
            <li>Once an offer is accepted, the student is expected to honour the commitment and join the selected organisation as per its joining instructions. Any joining/withdrawal issue shall be immediately communicated to CASP.</li>
            <li>Refusal to join after final selection without a valid and acceptable reason may lead to restriction from future placement opportunities.</li>
        </ul>

        <div class="sec-title">7. Responsibility &amp; Disciplinary Action</div>
        <ul class="bullet-list">
            <li>Every student is responsible for compliance with this undertaking, CASP instructions, recruiter instructions and maintenance of the reputation and dignity of GM University.</li>
            <li>Depending on severity/frequency, violations may result in warning, cancellation of a drive, temporary suspension, disqualification from future drives, referral to the University disciplinary authority, or permanent restriction in serious/repeated cases.</li>
            <li>CASP reserves the right to interpret and implement the placement policy in the best interest of students, GM University and recruiting organisations. Exceptional cases shall be reviewed by the competent authority.</li>
        </ul>

        <div class="sec-title" style="margin-top: 14px; font-size: 9pt;">STUDENT &amp; PARENT/GUARDIAN UNDERTAKING</div>
        <p class="undertaking-p">
            We hereby confirm that the student has read and understood the above Student Placement Policy and agrees to comply with all CASP/GM University placement rules. We understand that violation of these conditions may result in cancellation, suspension, disqualification or other disciplinary action as applicable. The student further acknowledges that participation in a recruitment drive and acceptance of an offer carry the responsibilities stated above.
        </p>

        <!-- 4-Box Signatures Table -->
        <table class="sig-grid">
            <tr>
                <th style="width: 25%;">CANDIDATE SIGNATURE</th>
                <th style="width: 25%;">PARENT / GUARDIAN SIGNATURE</th>
                <th style="width: 25%;">DEPARTMENT HEAD SIGNATURE</th>
                <th style="width: 25%;">CASP DEPARTMENT SIGNATURE</th>
            </tr>
            <tr>
                <td>
                    ' . ($sigHtml ?: '<div style="font-style:italic; font-weight:bold; color:#1a4f9c; font-size:9pt; margin-bottom:4px;">' . htmlspecialchars($formName) . '</div>') . '
                    Signature: ___________<br>
                    Date: ' . htmlspecialchars($formDate) . '
                </td>
                <td>
                    ' . (!empty($formParentName) ? '<div style="font-style:italic; font-size:8.5pt; color:#334155; margin-bottom:4px;">' . htmlspecialchars($formParentName) . '</div>' : '') . '
                    Signature: ___________<br>
                    Date: ' . htmlspecialchars($formDate) . '
                </td>
                <td>
                    <br>
                    Signature: ___________<br>
                    Date: ______________
                </td>
                <td>
                    <br>
                    Signature: ___________<br>
                    Date: ______________
                </td>
            </tr>
        </table>

        <div class="official-note">
            OFFICIAL NOTE: Department Head and CASP Department shall verify receipt of the undertaking and complete the required office record.
        </div>

        <div class="page-footer-text">GM University &ndash; CASP | Student Placement Policy &amp; Undertaking</div>

        </body>
        </html>
        ';

        $mpdf->WriteHTML($pdfHtml);

        $policyDir = PlacementInterestHelper::getPolicyDir();
        $targetPdfPath = $policyDir . '/POLICY_' . $safeUsn . '_Undertaking.pdf';
        $mpdf->Output($targetPdfPath, \Mpdf\Output\Destination::FILE);

        // Record placement interest = true with policy document
        PlacementInterestHelper::setDecision($formUsn, true, [
            'type' => 'policy',
            'has_pdf' => true,
            'pdf_file' => basename($targetPdfPath),
            'user_id' => $userId
        ]);

        Session::flash('success', "Placement Policy & Undertaking successfully signed and submitted to CASP.");
        redirect('casp_policy_undertaking.php?generated=1');
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
    <title>CASP Student Placement Policy &amp; Undertaking - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --brand-green: #059669;
            --brand-dark: #064e3b;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #cbd5e1;
            --bg: #f8fafc;
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
            max-width: 900px;
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
        .back-link:hover { color: var(--brand-green); }

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

        .existing-card {
            background: #ffffff;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
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
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 6px;
            padding: 34px 40px;
            font-family: Arial, sans-serif;
        }

        .cand-table-ui {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .cand-table-ui td {
            border: 1px solid #94a3b8;
            padding: 8px 12px;
            font-size: 13.5px;
        }
        .cand-table-ui .lbl {
            width: 35%;
            font-weight: bold;
            background: #f8fafc;
        }
        .cand-table-ui input {
            width: 100%;
            padding: 4px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 13.5px;
        }

        .policy-section-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 14px;
            margin-bottom: 6px;
            color: #0f172a;
        }
        .policy-bullets {
            margin-left: 20px;
            font-size: 13px;
            line-height: 1.5;
            color: #334155;
            margin-bottom: 12px;
        }
        .policy-bullets li {
            margin-bottom: 5px;
        }

        .sig-grid-ui {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            margin-bottom: 14px;
        }
        .sig-grid-ui th {
            border: 1px solid #94a3b8;
            padding: 8px;
            font-size: 11px;
            background: #f1f5f9;
            text-align: center;
        }
        .sig-grid-ui td {
            border: 1px solid #94a3b8;
            padding: 10px;
            font-size: 12px;
            vertical-align: top;
            height: 70px;
        }

        .btn-sign {
            background: #059669;
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
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
            transition: all 0.2s;
        }
        .btn-sign:hover {
            background: #047857;
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
                    <i class="fas fa-file-signature"></i>
                </div>
                <div>
                    <h4 style="font-size: 15px; font-weight: 700; margin: 0 0 4px; color: #065f46;">
                        Placement Policy &amp; Undertaking Signed
                    </h4>
                    <p style="font-size: 12.5px; color: #64748b; margin: 0;">
                        Your signed placement policy has been securely submitted and verified for campus drives.
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="view_undertaking.php?usn=<?php echo urlencode($formUsn); ?>&type=policy" target="_blank" class="btn-view">
                    <i class="fas fa-eye"></i> View PDF
                </a>
                <a href="view_undertaking.php?usn=<?php echo urlencode($formUsn); ?>&type=policy&download=1" class="btn-view" style="background:#059669;">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Document Paper Replica -->
    <div class="doc-paper-container">
        <div style="text-align: center; margin-bottom: 20px;">
            <?php if ($gmuLogoBase64): ?>
                <img src="<?php echo $gmuLogoBase64; ?>" style="height: 54px; margin-bottom: 4px;" alt="GMU">
            <?php endif; ?>
            <div style="font-size: 18px; font-weight: bold; color: #000000;">GM UNIVERSITY</div>
            <div style="font-size: 14px; font-weight: bold; color: #000000;">CAREER ADVANCEMENT &amp; STUDENT PLACEMENTS (CASP)</div>
            <div style="font-size: 14px; font-weight: bold; color: #000000;">STUDENT PLACEMENT POLICY &amp; UNDERTAKING</div>
            <div style="font-size: 12.5px; font-style: italic; color: #475569;">Applicable to all students participating in CASP placement and recruitment activities</div>
        </div>

        <form method="POST" action="casp_policy_undertaking.php">
            <input type="hidden" name="action" value="generate_policy_pdf">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

            <div style="font-weight: bold; font-size: 14px; margin-bottom: 8px;">CANDIDATE DETAILS</div>
            <table class="cand-table-ui">
                <tr>
                    <td class="lbl">Student Name</td>
                    <td><input type="text" name="student_name" value="<?php echo htmlspecialchars($studentName); ?>" required></td>
                </tr>
                <tr>
                    <td class="lbl">USN / Registration No.</td>
                    <td><input type="text" name="student_usn" value="<?php echo htmlspecialchars($studentUSN); ?>" required></td>
                </tr>
                <tr>
                    <td class="lbl">Branch &amp; Semester</td>
                    <td><input type="text" name="branch_semester" value="<?php echo htmlspecialchars($branchSem); ?>" required></td>
                </tr>
                <tr>
                    <td class="lbl">Parent/Guardian Name</td>
                    <td><input type="text" name="parent_name" value="<?php echo htmlspecialchars($parentName); ?>" placeholder="Enter parent/guardian name" required></td>
                </tr>
                <tr>
                    <td class="lbl">Student Mobile No.</td>
                    <td><input type="text" name="student_mobile" value="<?php echo htmlspecialchars($contactNumber); ?>" required></td>
                </tr>
                <tr>
                    <td class="lbl">Date</td>
                    <td><input type="text" name="policy_date" value="<?php echo date('d/m/Y'); ?>" required></td>
                </tr>
            </table>

            <!-- Policy Sections -->
            <div class="policy-section-title">1. Mandatory Registration &amp; Eligibility</div>
            <ul class="policy-bullets">
                <li>All eligible students shall register on the Lakshya Portal within the prescribed deadline. Students who do not register may not be considered for placement drives.</li>
                <li>Students shall provide complete, accurate and truthful academic/personal information and shall independently verify company eligibility requirements including percentage/CGPA, backlogs, branch, year of passing, skills and other criteria.</li>
                <li>CASP may verify eligibility against official University/academic records; meeting a company’s stated eligibility threshold is the student’s responsibility.</li>
            </ul>

            <div class="policy-section-title">2. Attendance &amp; Participation</div>
            <ul class="policy-bullets">
                <li>Registration for a company/drive constitutes a commitment to participate. Attendance is mandatory for the pre-placement talk, aptitude/assessment, GD, technical/HR rounds, interviews and all other applicable stages.</li>
                <li>Absence without prior approval or a valid reason, or repeated non-participation, may result in cancellation/suspension/disqualification from placement opportunities.</li>
            </ul>

            <div class="policy-section-title">3. Professional Dress, Conduct &amp; Online Etiquette</div>
            <ul class="policy-bullets">
                <li>Students shall follow the prescribed professional dress code and maintain a well-groomed appearance during campus placement activities.</li>
                <li>For virtual activities, students shall join on time, keep the camera ON whenever required, use a suitable professional environment/background and maintain professional communication and behaviour.</li>
                <li>Students shall treat recruiters, company representatives, CASP staff, faculty and fellow students respectfully and shall follow all instructions and timelines communicated by CASP/recruiters.</li>
                <li>Salary/offer negotiations during recruitment are not permitted unless specifically allowed by the recruiter.</li>
            </ul>

            <div class="policy-section-title">4. Integrity, Confidentiality &amp; Prohibited Activities</div>
            <ul class="policy-bullets">
                <li>Fake/forged/altered documents, cheating or malpractice, misconduct, unauthorised sharing of question papers, assessment links, company documents or confidential recruitment information are strictly prohibited.</li>
                <li>Students shall not misuse social media/online platforms in a manner that adversely affects GM University, CASP, recruiters or other students, or otherwise damage the University–recruiter relationship.</li>
            </ul>

            <div class="policy-section-title">5. Placement Eligibility After Selection / Package Rules</div>
            <ul class="policy-bullets">
                <li>Students placed below &#8377;3 LPA may participate in subsequent opportunities, but shall not participate in another drive below &#8377;3 LPA.</li>
                <li>Students placed between &#8377;4 LPA and &#8377;8 LPA shall not participate in further drives in the &#8377;4&ndash;&#8377;8 LPA range, but may participate in opportunities of &#8377;9 LPA and above, subject to company eligibility.</li>
                <li>Students placed above &#8377;8 LPA shall not participate in lower package categories, but may participate in opportunities of &#8377;9 LPA and above, subject to company eligibility.</li>
                <li>For every drive, company-specific eligibility and recruitment instructions shall prevail wherever they are stricter than the general CASP policy.</li>
            </ul>

            <div class="policy-section-title">6. Offer Acceptance &amp; Joining Commitment</div>
            <ul class="policy-bullets">
                <li>Before participating in final selection, students shall carefully consider the role, location, compensation, bond/service agreement, joining date and other employment terms.</li>
                <li>Once an offer is accepted, the student is expected to honour the commitment and join the selected organisation as per its joining instructions. Any joining/withdrawal issue shall be immediately communicated to CASP.</li>
                <li>Refusal to join after final selection without a valid and acceptable reason may lead to restriction from future placement opportunities.</li>
            </ul>

            <div class="policy-section-title">7. Responsibility &amp; Disciplinary Action</div>
            <ul class="policy-bullets">
                <li>Every student is responsible for compliance with this undertaking, CASP instructions, recruiter instructions and maintenance of the reputation and dignity of GM University.</li>
                <li>Depending on severity/frequency, violations may result in warning, cancellation of a drive, temporary suspension, disqualification from future drives, referral to the University disciplinary authority, or permanent restriction in serious/repeated cases.</li>
                <li>CASP reserves the right to interpret and implement the placement policy in the best interest of students, GM University and recruiting organisations. Exceptional cases shall be reviewed by the competent authority.</li>
            </ul>

            <div style="font-weight: bold; font-size: 14px; margin-top: 22px; color: #000000;">
                STUDENT &amp; PARENT/GUARDIAN UNDERTAKING
            </div>
            <p style="font-size: 13px; line-height: 1.5; color: #1e293b; margin: 8px 0 14px;">
                We hereby confirm that the student has read and understood the above Student Placement Policy and agrees to comply with all CASP/GM University placement rules. We understand that violation of these conditions may result in cancellation, suspension, disqualification or other disciplinary action as applicable. The student further acknowledges that participation in a recruitment drive and acceptance of an offer carry the responsibilities stated above.
            </p>

            <!-- Digital Signature Studio Pad -->
            <div class="digital-sign-card" style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 18px 20px; margin: 18px 0 22px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                    <div style="font-weight: 700; font-size: 14px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-pen-nib" style="color: #059669;"></i> Candidate Digital Signature Pad
                    </div>
                    <div>
                        <button type="button" id="btnClearSig" onclick="clearSignature()" style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px;">
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
                    <span><i class="fas fa-lock"></i> Your signature is digitally embedded into the official 2-page PDF.</span>
                    <span style="font-weight: 600; color: #059669;">Verified Candidate USN: <?php echo htmlspecialchars($studentUSN); ?></span>
                </div>
            </div>

            <!-- Signature Table Mockup -->
            <table class="sig-grid-ui">
                <tr>
                    <th style="width: 25%;">CANDIDATE SIGNATURE</th>
                    <th style="width: 25%;">PARENT / GUARDIAN SIGNATURE</th>
                    <th style="width: 25%;">DEPARTMENT HEAD SIGNATURE</th>
                    <th style="width: 25%;">CASP DEPARTMENT SIGNATURE</th>
                </tr>
                <tr>
                    <td id="candidateSigCell">
                        <div id="sigThumbnailPreview" style="height: 38px; display: flex; align-items: center;">
                            <span style="color: #64748b; font-size: 11px; font-style: italic;">(Drawn signature appears here)</span>
                        </div>
                        <div style="color: #059669; font-size: 11px; font-weight: bold;">
                            <i class="fas fa-check-circle"></i> Digital Candidate Signature
                        </div>
                        <div style="font-size: 11px;">Date: <?php echo date('d/m/Y'); ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #1e293b; font-size: 12px; margin-bottom: 2px;">
                            <?php echo htmlspecialchars($parentName ?: 'Parent / Guardian'); ?>
                        </div>
                        <span style="color: #64748b; font-size: 11px;">Acknowledged by Parent</span><br>
                        <span style="font-size: 11px;">Date: <?php echo date('d/m/Y'); ?></span>
                    </td>
                    <td style="color: #94a3b8; font-style: italic;">
                        Pending Office Verification
                    </td>
                    <td style="color: #94a3b8; font-style: italic;">
                        Pending Office Verification
                    </td>
                </tr>
            </table>

            <div style="border: 1px dashed #94a3b8; padding: 10px; font-size: 12px; font-weight: bold; text-align: center; color: #334155; margin-bottom: 24px;">
                OFFICIAL NOTE: Department Head and CASP Department shall verify receipt of the undertaking and complete the required office record.
            </div>

            <div style="text-align: center;">
                <button type="submit" class="btn-sign">
                    <i class="fas fa-check-circle"></i> I Agree &amp; Generate Signed Placement Policy PDF
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
const sigThumb = document.getElementById('sigThumbnailPreview');
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
    updateThumbnail();
}

function stopDrawing() {
    if (isDrawing) {
        isDrawing = false;
        ctx.closePath();
        updateThumbnail();
    }
}

// Pointer events support desktop mouse, iOS/Android touch, and pen/stylus
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
    if (sigThumb) sigThumb.innerHTML = '<span style="color: #64748b; font-size: 11px; font-style: italic;">(Drawn signature appears here)</span>';
}

function updateThumbnail() {
    const dataUrl = canvas.toDataURL('image/png');
    if (sigInput) sigInput.value = dataUrl;
    if (sigThumb) {
        sigThumb.innerHTML = '<img src="' + dataUrl + '" style="max-height: 34px; max-width: 140px; display: block;">';
    }
}

// Enforce signature on submit
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
