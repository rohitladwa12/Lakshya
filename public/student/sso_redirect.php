<?php
/**
 * Single Sign-On (SSO) Redirect for AI Tutor
 * Generates a stateless signed token to authenticate students on gmu.ac.in/tutor/login.php
 */
require_once __DIR__ . '/../../config/bootstrap.php';

// Ensure user is logged in
requireLogin();

// Check if AI Tutor feature is enabled
if (!isFeatureEnabled('feature_ai_tutor')) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AI Tutor - Under Update</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primary: #800000;
                --text: #1e293b;
                --muted: #64748b;
            }
            body {
                font-family: 'Outfit', sans-serif;
                background: #f8fafc;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
                color: var(--text);
            }
            .container {
                background: white;
                padding: 3.5rem;
                border-radius: 32px;
                box-shadow: 0 20px 50px -12px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
                border: 1px solid #e2e8f0;
                position: relative;
                overflow: hidden;
            }
            .container::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 6px;
                background: linear-gradient(90deg, var(--primary), #e9c66f);
            }
            .icon-stack {
                position: relative;
                width: 100px;
                height: 100px;
                margin: 0 auto 2rem;
            }
            .icon-bg {
                font-size: 5rem;
                color: #f1f5f9;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }
            .icon-main {
                font-size: 3rem;
                color: var(--primary);
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 1;
            }
            h1 {
                font-size: 2rem;
                font-weight: 800;
                margin-bottom: 1rem;
                color: #0f172a;
                letter-spacing: -0.025em;
            }
            p {
                line-height: 1.7;
                color: var(--muted);
                margin-bottom: 2.5rem;
                font-size: 1.1rem;
            }
            .interest-note {
                background: #fdf2f2;
                color: #9b1c1c;
                padding: 0.75rem 1.5rem;
                border-radius: 12px;
                font-size: 0.95rem;
                font-weight: 600;
                margin-bottom: 2rem;
                border: 1px solid #fde8e8;
                display: inline-block;
            }
            .btn {
                background: var(--primary);
                color: white;
                text-decoration: none;
                padding: 1rem 2.5rem;
                border-radius: 16px;
                font-weight: 700;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                display: inline-block;
                box-shadow: 0 10px 15px -3px rgba(128, 0, 0, 0.3);
            }
            .btn:hover {
                background: #5b1f1f;
                transform: translateY(-2px);
                box-shadow: 0 20px 25px -5px rgba(128, 0, 0, 0.4);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon-stack">
                <i class="fas fa-graduation-cap icon-bg"></i>
                <i class="fas fa-wrench icon-main"></i>
            </div>
            <h1>AI Tutor is being updated</h1>
            <div class="interest-note">
                Thank you for your interest!
            </div>
            <p>We are enhancing the AI Tutor environment to bring you the best learning experience. Please check back in a short while.</p>
            <a href="dashboard.php" class="btn"><i class="fas fa-arrow-left"></i> Go to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Retrieve logged-in student session details
$username = getUsername();
$fullName = getFullName();
$role = getRole();
$department = Session::get('department');
$institution = Session::get('institution');
$leapUrl = defined('APP_URL') ? APP_URL : 'http://leap.gmu.ac.in/Lakshya';

// Shared secret key — must match login.php on tutor side
define('SSO_SECRET', 'gmu_tutor_sso_secret_key_2026');

// Build signed payload
$payload = base64_encode(json_encode([
    'username' => $username,
    'full_name' => $fullName,
    'role' => $role,
    'department' => $department,
    'institution' => $institution,
    'leap_url' => $leapUrl,
    'expires' => time() + 60,
    'nonce' => bin2hex(random_bytes(8))
]));
$signature = hash_hmac('sha256', $payload, SSO_SECRET);
$sso_token = $payload . '.' . $signature;

// Build redirect URL
$redirectUrl = 'https://gmu.ac.in/tutor/login.php?sso_token=' . urlencode($sso_token);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to AI Tutor...</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #1a1a1a;
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
        }

        .container {
            padding: 2rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 400px;
            width: 100%;
        }

        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border-left-color: #ea580c;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        h2 {
            margin-bottom: 10px;
            font-size: 1.5rem;
            color: #e9c66f;
        }

        p {
            color: #ccc;
            font-size: 0.95rem;
        }

        a {
            color: #ea580c;
            text-decoration: none;
            font-weight: 600;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
    <script>
        var shouldRedirect = true;
        document.addEventListener("DOMContentLoaded", function() {
            var message = <?php 
                $db = getDB();
                $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'feature_ai_tutor_message' LIMIT 1");
                $stmt->execute();
                $msg = $stmt->fetchColumn();
                echo json_encode($msg ?: ''); 
            ?>;
            var seen = localStorage.getItem('seen_announcement_feature_ai_tutor');
            if (message && seen !== message) {
                shouldRedirect = false;
            } else {
                startRedirectTimer();
            }
        });

        function startRedirectTimer() {
            setTimeout(function() {
                window.location.href = <?php echo json_encode($redirectUrl); ?>;
            }, 1500);
        }

        function dismissAnnouncement() {
            var message = <?php 
                $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'feature_ai_tutor_message' LIMIT 1");
                $stmt->execute();
                $msg = $stmt->fetchColumn();
                echo json_encode($msg ?: ''); 
            ?>;
            localStorage.setItem('seen_announcement_feature_ai_tutor', message);
            var modal = document.getElementById("announcement-modal");
            modal.classList.remove("show");
            setTimeout(function() {
                modal.style.display = "none";
            }, 400);
            startRedirectTimer();
        }
    </script>
</head>

<body>
    <div class="container">
        <div class="spinner"></div>
        <h2>Connecting to AI Tutor</h2>
        <p>Please wait while we secure your session...</p>
        <p>If you are not redirected automatically,
            <a href="<?php echo htmlspecialchars($redirectUrl); ?>">click here</a>.
        </p>
    </div>
    <?php renderFeatureAnnouncement('feature_ai_tutor'); ?>
</body>

</html>