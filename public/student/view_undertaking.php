<?php
/**
 * Secure CASP Undertaking & Placement Policy PDF Viewer Proxy
 * Mirrors view_resume.php behavior: session & role check, signed token support, clean streaming.
 * Accessible via:
 *   /student/view_undertaking?usn=U23E01AI047
 *   /student/view_undertaking?usn=U23E01AI047&type=policy
 *   /student/view_undertaking?usn=U23E01AI047&type=opt_out
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/src/Helpers/PlacementInterestHelper.php';
use App\Helpers\PlacementInterestHelper;

// 1. Get requested USN and token from URL
$requestedUsn = isset($_GET['usn']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_GET['usn'])) : '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$requestedType = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$isDownload = isset($_GET['download']) && $_GET['download'] == '1';

// If student is logged in and no USN is provided, default to current student
if (empty($requestedUsn) && isLoggedIn() && getRole() === ROLE_STUDENT) {
    $requestedUsn = function_exists('getStudentIdForAssessment') ? getStudentIdForAssessment() : getUsername();
}

if (empty($requestedUsn)) {
    http_response_code(400);
    die("Bad Request: USN is required.");
}

$currentUser = isLoggedIn() ? getUsername() : '';
$currentRole = isLoggedIn() ? getRole() : '';
$canAccess = false;

// 2. Token-Based Authentication (for external links, e.g. Excel exports)
if (!empty($token) && function_exists('verifyResumeToken') && verifyResumeToken($requestedUsn, $token)) {
    $canAccess = true;
}

// 3. Session-Based Authorization
if (!$canAccess && isLoggedIn()) {
    $privilegedRoles = [
        ROLE_ADMIN,
        ROLE_PLACEMENT_OFFICER,
        ROLE_INTERNSHIP_OFFICER,
        ROLE_DEPT_COORDINATOR,
        ROLE_HOD,
        ROLE_VC,
        ROLE_DEMO
    ];

    if ($currentRole === ROLE_STUDENT) {
        $studentUsn = function_exists('getStudentIdForAssessment') ? getStudentIdForAssessment() : $currentUser;
        if (strtoupper(trim($currentUser)) === strtoupper(trim($requestedUsn)) ||
            strtoupper(trim($studentUsn)) === strtoupper(trim($requestedUsn))) {
            $canAccess = true;
        }
    } else if (in_array($currentRole, $privilegedRoles) || $currentRole !== ROLE_STUDENT) {
        $canAccess = true;
    }
}

if (!$canAccess) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - Lakshya</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: 'Outfit', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; color: #1e293b; }
            .error-card { background: white; padding: 3rem; border-radius: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); text-align: center; max-width: 450px; border: 1px solid #e2e8f0; }
            .icon { font-size: 4rem; color: #ef4444; margin-bottom: 1.5rem; }
            h1 { font-size: 1.75rem; margin-bottom: 1rem; color: #0f172a; }
            p { line-height: 1.6; color: #64748b; margin-bottom: 2rem; }
            .identity-box { background: #fff1f2; color: #991b1b; padding: 1rem; border-radius: 12px; font-size: 0.9rem; font-weight: 600; margin-bottom: 2rem; border: 1px solid #fecaca; }
            .btn { background: #800000; color: white; text-decoration: none; padding: 0.75rem 2rem; border-radius: 12px; font-weight: 600; transition: all 0.2s; display: inline-block; }
            .btn:hover { background: #5b1f1f; transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="icon"><i class="fas fa-shield-halved"></i></div>
            <h1>Access Denied</h1>
            <p>Hey <strong><?php echo htmlspecialchars($currentUser ?: 'User'); ?></strong>, you are currently trying to access the undertaking document of <strong><?php echo htmlspecialchars($requestedUsn); ?></strong>.</p>
            <div class="identity-box">
                <i class="fas fa-circle-exclamation"></i> Privacy Policy: Students are only permitted to view their own documents.
            </div>
            <a href="javascript:history.back()" class="btn">Go Back</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 4. Locate the Undertaking / Policy File
$filePath = '';
$fileName = '';

if ($requestedType === 'policy' || $requestedType === 'placement_policy') {
    $path = PlacementInterestHelper::getPolicyPdfPath($requestedUsn);
    if (file_exists($path) && filesize($path) > 0) {
        $filePath = $path;
        $fileName = 'POLICY_' . strtoupper($requestedUsn) . '_Undertaking.pdf';
    }
} elseif ($requestedType === 'opt_out' || $requestedType === 'optout') {
    $path = PlacementInterestHelper::getOptOutPdfPath($requestedUsn);
    if (file_exists($path) && filesize($path) > 0) {
        $filePath = $path;
        $fileName = 'OPT_OUT_' . strtoupper($requestedUsn) . '_Undertaking.pdf';
    }
} else {
    // Check registry decision first
    $decision = PlacementInterestHelper::getDecision($requestedUsn);
    if ($decision && isset($decision['opt_out']) && $decision['opt_out']) {
        $pathOpt = PlacementInterestHelper::getOptOutPdfPath($requestedUsn);
        if (file_exists($pathOpt) && filesize($pathOpt) > 0) {
            $filePath = $pathOpt;
            $fileName = 'OPT_OUT_' . strtoupper($requestedUsn) . '_Undertaking.pdf';
        }
    } else {
        // Prefer policy path if interested or signed
        $pathPol = PlacementInterestHelper::getPolicyPdfPath($requestedUsn);
        if (file_exists($pathPol) && filesize($pathPol) > 0) {
            $filePath = $pathPol;
            $fileName = 'POLICY_' . strtoupper($requestedUsn) . '_Undertaking.pdf';
        } else {
            // Check opt-out fallback
            $pathOpt = PlacementInterestHelper::getOptOutPdfPath($requestedUsn);
            if (file_exists($pathOpt) && filesize($pathOpt) > 0) {
                $filePath = $pathOpt;
                $fileName = 'OPT_OUT_' . strtoupper($requestedUsn) . '_Undertaking.pdf';
            }
        }
    }
}

if (empty($filePath) || !file_exists($filePath)) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Document Not Found - Lakshya</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: 'Outfit', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; color: #1e293b; }
            .error-card { background: white; padding: 3rem; border-radius: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); text-align: center; max-width: 450px; border: 1px solid #e2e8f0; }
            .icon { font-size: 4rem; color: #64748b; margin-bottom: 1.5rem; }
            h1 { font-size: 1.75rem; margin-bottom: 1rem; color: #0f172a; }
            p { line-height: 1.6; color: #64748b; margin-bottom: 2rem; }
            .btn { background: #800000; color: white; text-decoration: none; padding: 0.75rem 2rem; border-radius: 12px; font-weight: 600; transition: all 0.2s; display: inline-block; }
            .btn:hover { background: #5b1f1f; transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="icon"><i class="fas fa-file-circle-question"></i></div>
            <h1>Document Not Found</h1>
            <p>No signed CASP undertaking or placement policy document was found for student <strong><?php echo htmlspecialchars($requestedUsn); ?></strong>.</p>
            <a href="javascript:history.back()" class="btn">Go Back</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 5. Serve the file securely
$disposition = $isDownload ? 'attachment' : 'inline';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
