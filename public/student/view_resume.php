<?php
/**
 * Secure Resume Viewer Proxy
 * Checks session and role before serving a student's resume PDF.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// 1. Basic Auth Check
if (!isLoggedIn()) {
    http_response_code(403);
    die("Access Denied: Please log in.");
}

$currentUser = getUsername();
$currentRole = getRole();

// 2. Get requested USN from URL
$requestedUsn = isset($_GET['usn']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['usn']) : '';

if (empty($requestedUsn)) {
    http_response_code(400);
    die("Bad Request: USN is required.");
}

// 3. Authorization Logic
// Students can only see their OWN resume.
// Admins, Officers, and Coordinators can see any resume.
$canAccess = false;

$privilegedRoles = [ROLE_ADMIN, ROLE_PLACEMENT_OFFICER, ROLE_INTERNSHIP_OFFICER, ROLE_DEPT_COORDINATOR, ROLE_VC];

if ($currentRole === ROLE_STUDENT) {
    if (strtoupper($currentUser) === strtoupper($requestedUsn)) {
        $canAccess = true;
    }
} else if (in_array($currentRole, $privilegedRoles)) {
    $canAccess = true;
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
            <p>Hey <strong><?php echo htmlspecialchars($currentUser); ?></strong>, you are currently trying to access the resume of <strong><?php echo htmlspecialchars($requestedUsn); ?></strong>.</p>
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

// 4. Locate the file
$requestedPath = isset($_GET['path']) ? $_GET['path'] : '';
$requestedType = isset($_GET['type']) ? $_GET['type'] : 'Resume';

if (!empty($requestedPath)) {
    // Security: Only allow paths within uploads/reports or uploads/resumes
    $normalizedPath = str_replace('\\', '/', $requestedPath);
    $isAllowedDir = (strpos($normalizedPath, 'uploads/reports/') === 0 || strpos($normalizedPath, 'uploads/resumes/') === 0);
    $isPdf = (strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION)) === 'pdf');
    
    if (!$isAllowedDir || !$isPdf || strpos($normalizedPath, '..') !== false) {
        http_response_code(403);
        die("Access Denied: Invalid file path.");
    }
    
    $filePath = ROOT_PATH . '/public/' . $normalizedPath;
    $fileName = basename($filePath);
} else {
    // Default to Resume if no path provided
    $uploadDir = UPLOADS_PATH . '/resumes/Student_Resumes';
    $variants = [
        strtoupper($requestedUsn) . '_Resume.pdf',
        strtolower($requestedUsn) . '_Resume.pdf',
        $requestedUsn . '_Resume.pdf'
    ];
    $filePath = '';
    $fileName = '';
    foreach ($variants as $v) {
        $checkPath = $uploadDir . '/' . $v;
        if (file_exists($checkPath)) {
            $filePath = $checkPath;
            $fileName = $v;
            break;
        }
    }
    if (empty($filePath)) {
        // Fallback default
        $fileName = strtoupper($requestedUsn) . '_Resume.pdf';
        $filePath = $uploadDir . '/' . $fileName;
    }
}

if (!file_exists($filePath)) {
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
            .secondary-btn { color: #64748b; text-decoration: none; font-size: 0.9rem; margin-top: 1.5rem; display: block; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="icon"><i class="fas fa-file-circle-question"></i></div>
            <h1>Document Not Found</h1>
            <p>The requested <?php echo htmlspecialchars(strtolower($requestedType)); ?> could not be located on the server.</p>
            <a href="javascript:history.back()" class="btn">Go Back</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 5. Serve the file securely
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
