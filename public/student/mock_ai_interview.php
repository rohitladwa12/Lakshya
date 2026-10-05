<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

use App\Helpers\SessionFilterHelper;

requireRole(ROLE_STUDENT);
requireFeature('feature_mock_ai', 'Mock AI Interview');

$userId = getUserId();
$studentModel = new StudentProfile();
$profile = $studentModel->getByUserId($userId);
$studentName = $profile['name'] ?? 'Student';

// Handle POST from assigned_task.php or dashboard
if (isPost() && (isset($_POST['company']) || isset($_POST['type']))) {
    SessionFilterHelper::setFilters('mock_ai', [
        'company' => $_POST['company'] ?? 'General',
        'type' => $_POST['type'] ?? 'Technical'
    ]);
    header("Location: mock_ai_interview.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('mock_ai');
// Session filters come straight from $_POST, so escape them the same way as the GET values
$companyName = !empty($_GET['company']) ? clean($_GET['company']) : clean((string)($filters['company'] ?? 'General'));
$roundType = !empty($_GET['type']) ? clean($_GET['type']) : clean((string)($filters['type'] ?? 'Technical'));

$compLower = strtolower($companyName);
$primaryColor = '#800000'; // Default Maroon
$primaryDark = '#4a0000';
$accentColor = '#e9c66f';  // Gold
$logoIcon = 'fa-microchip';
$brandThemeClass = 'theme-general';

if (strpos($compLower, 'google') !== false) {
    $primaryColor = '#4285f4'; // Google Blue
    $primaryDark = '#1a73e8';
    $accentColor = '#34a853';  // Google Green
    $logoIcon = 'fa-google';
    $brandThemeClass = 'theme-google';
} else if (strpos($compLower, 'amazon') !== false) {
    $primaryColor = '#ff9900'; // Amazon Orange
    $primaryDark = '#e47911';
    $accentColor = '#146eb4';  // Amazon Blue
    $logoIcon = 'fa-amazon';
    $brandThemeClass = 'theme-amazon';
} else if (strpos($compLower, 'microsoft') !== false) {
    $primaryColor = '#00a4ef'; // Microsoft Teal/Blue
    $primaryDark = '#0078d4';
    $accentColor = '#f25022';  // Microsoft Red/Orange
    $logoIcon = 'fa-windows';
    $brandThemeClass = 'theme-microsoft';
} else if (strpos($compLower, 'tcs') !== false) {
    $primaryColor = '#1f57a4'; // TCS Dark Blue
    $primaryDark = '#123970';
    $accentColor = '#00b4e5';  // TCS Cyan
    $logoIcon = 'fa-laptop-code';
    $brandThemeClass = 'theme-tcs';
} else if (strpos($compLower, 'infosys') !== false) {
    $primaryColor = '#007cc3'; // Infosys Blue
    $primaryDark = '#005a90';
    $accentColor = '#ff6600';  // Infosys Orange
    $logoIcon = 'fa-building-columns';
    $brandThemeClass = 'theme-infosys';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Mock AI Interview | Lakshya</title>
<!-- In-page replacements for the native alert / confirm popups (native popups blur the window and count as proctoring violations) -->
<script src="../js/lakshya_dialogs.js?v=<?php echo APP_VERSION; ?>"></script>
<!-- Resilience & Cache Busting -->
<script src="resilience.js?v=<?php echo APP_VERSION; ?>"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<!-- KaTeX for equation rendering -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js"></script>

<!-- CodeMirror for Coding Workspace -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/dracula.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/python/python.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/clike/clike.min.js"></script>
<style>
    :root {
        --primary:
            <?php echo $primaryColor; ?>
        ;
        --primary-dark:
            <?php echo $primaryDark; ?>
        ;
        --accent:
            <?php echo $accentColor; ?>
        ;
        --bg-body: #0f0f12;
        --card-glass: rgba(255, 255, 255, 0.03);
        --border-glass: rgba(255, 255, 255, 0.1);
        --text-main: #f8fafc;
        --text-muted: #94a3b8;
        --user-bubble: linear-gradient(135deg,
                <?php echo $primaryColor; ?>
                0%,
                <?php echo $primaryDark; ?>
                100%);
        --ai-bubble: rgba(255, 255, 255, 0.05);
        --header-height: 80px;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Outfit', sans-serif;
        background: var(--bg-body);
        color: var(--text-main);
        height: 100vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        background-image:
            radial-gradient(at 0% 0%, rgba(128, 0, 0, 0.15) 0, transparent 50%),
            radial-gradient(at 100% 100%, rgba(233, 198, 111, 0.05) 0, transparent 50%);
        user-select: none;
        /* Block selection */
        -webkit-user-select: none;
    }

    @media print {
        body {
            display: none !important;
        }
    }

    /* Restricted Navbar */
    .session-header {
        height: var(--header-height);
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--border-glass);
        padding: 0 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 100;
    }

    .brand-logo {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .logo-icon {
        width: 45px;
        height: 45px;
        background: var(--primary);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        box-shadow: 0 0 20px rgba(128, 0, 0, 0.3);
    }

    .brand-text h1 {
        font-size: 1.25rem;
        font-weight: 800;
        letter-spacing: -0.5px;
    }

    .brand-text span {
        font-size: 0.75rem;
        color: var(--accent);
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 1px;
    }

    .session-status {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(16, 185, 129, 0.1);
        padding: 8px 16px;
        border-radius: 50px;
        border: 1px solid rgba(16, 185, 129, 0.2);
        font-size: 0.85rem;
        font-weight: 600;
        color: #10b981;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        animation: pulse-green 2s infinite;
    }

    @keyframes pulse-green {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        }

        70% {
            transform: scale(1);
            box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
        }

        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
        }
    }

    .btn-end {
        background: #ef4444;
        color: white;
        text-decoration: none;
        padding: 10px 24px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.9rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
    }

    .btn-end:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(239, 68, 68, 0.3);
    }

    .btn-workspace {
        background: rgba(233, 198, 111, 0.1);
        color: var(--accent);
        border: 1px solid var(--accent);
        padding: 10px 20px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-workspace:hover {
        background: var(--accent);
        color: black;
    }

    .btn-back {
        background: rgba(255, 255, 255, 0.05);
        color: white;
        padding: 10px 15px;
        border-radius: 12px;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        border: 1px solid var(--border-glass);
        margin-right: 20px;
    }

    .btn-back:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateX(-3px);
        border-color: rgba(255, 255, 255, 0.2);
    }

    #roleSelection {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(15px);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .role-modal {
        background: #1a1a20;
        padding: 3rem;
        border-radius: 32px;
        text-align: center;
        max-width: 550px;
        width: 90%;
        border: 1px solid var(--border-glass);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        animation: modalPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    @keyframes modalPop {
        from {
            opacity: 0;
            transform: scale(0.8) translateY(30px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .role-modal h2 {
        font-size: 2rem;
        margin-bottom: 0.75rem;
        background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .role-modal p {
        color: var(--text-muted);
        margin-bottom: 2rem;
    }

    .role-input-wrap {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .role-input {
        width: 100%;
        padding: 18px 25px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-glass);
        border-radius: 16px;
        color: white;
        font-family: inherit;
        font-size: 1rem;
        outline: none;
        transition: all 0.3s;
    }

    .role-input:focus {
        border-color: var(--primary);
        background: rgba(255, 255, 255, 0.05);
        box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.2);
    }

    .role-input option {
        background: #16161c;
        color: white;
    }

    .btn-start {
        width: 100%;
        padding: 18px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 16px;
        font-weight: 800;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 10px 20px rgba(128, 0, 0, 0.2);
    }

    .btn-start:hover {
        transform: translateY(-3px);
        background: var(--primary-dark);
        box-shadow: 0 15px 30px rgba(128, 0, 0, 0.3);
    }

    /* Main Workspace Container */
    .workspace-wrapper {
        flex: 1;
        display: flex;
        overflow: hidden;
        width: 100%;
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .chat-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        max-width: 900px;
        margin: 0 auto;
        width: 100%;
        position: relative;
        overflow: hidden;
        transition: all 0.5s ease;
    }

    /* Coding Panel */
    .coding-panel {
        width: 0;
        background: #1a1a20;
        border-left: 1px solid var(--border-glass);
        display: flex;
        flex-direction: column;
        transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        visibility: hidden;
    }

    .coding-panel.active {
        width: 45%;
        visibility: visible;
    }

    .coding-header {
        padding: 20px;
        background: rgba(0, 0, 0, 0.2);
        border-bottom: 1px solid var(--border-glass);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .coding-editor-container {
        flex: 1;
        position: relative;
    }

    .CodeMirror {
        height: 100% !important;
        font-family: 'JetBrains Mono', monospace;
        font-size: 14px;
        background: transparent !important;
    }

    .coding-footer {
        padding: 20px;
        background: rgba(0, 0, 0, 0.2);
        border-top: 1px solid var(--border-glass);
        display: flex;
        gap: 15px;
    }

    .btn-send-code {
        flex: 1;
        background: rgba(255, 255, 255, 0.05);
        color: white;
        border: 1px solid var(--border-glass);
        padding: 12px;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-run-code {
        flex: 1;
        background: var(--accent);
        color: black;
        border: none;
        padding: 12px;
        border-radius: 10px;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-run-code:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(233, 198, 111, 0.3);
    }

    .coding-console {
        height: 150px;
        background: rgba(0, 0, 0, 0.4);
        border-top: 1px solid var(--border-glass);
        padding: 15px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.85rem;
        overflow-y: auto;
        color: #ccc;
    }

    .console-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--accent);
        margin-bottom: 8px;
        display: block;
        font-weight: 700;
        letter-spacing: 1px;
    }

    .console-out {
        line-height: 1.5;
        white-space: pre-wrap;
    }

    .console-success {
        color: #10b981;
    }

    .console-error {
        color: #ef4444;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 2.5rem 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 25px;
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
    }

    .chat-messages::-webkit-scrollbar {
        width: 6px;
    }

    .chat-messages::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
    }

    .message {
        max-width: 85%;
        padding: 1.25rem 1.75rem;
        border-radius: 24px;
        line-height: 1.6;
        font-size: 1.05rem;
        position: relative;
        animation: messageEntry 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        word-wrap: break-word;
    }

    @keyframes messageEntry {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .message.ai {
        align-self: flex-start;
        background: var(--ai-bubble);
        border-bottom-left-radius: 4px;
        border: 1px solid var(--border-glass);
        color: #e2e8f0;
    }

    .message.user {
        align-self: flex-end;
        background: var(--user-bubble);
        border-bottom-right-radius: 4px;
        color: white;
        box-shadow: 0 10px 25px rgba(128, 0, 0, 0.2);
    }

    .expert-box {
        background: rgba(233, 198, 111, 0.05);
        border: 1px solid rgba(233, 198, 111, 0.2);
        padding: 15px;
        margin-top: 15px;
        font-size: 0.9rem;
        border-radius: 12px;
        color: var(--accent);
    }

    .typing-hint {
        display: none;
        /* JS will set to flex */
        padding: 0.5rem 1.5rem;
        font-size: 0.85rem;
        color: var(--text-muted);
        font-style: italic;
        align-items: center;
        gap: 12px;
        transition: all 0.3s;
    }

    .dot-flashing {
        display: inline-block;
        position: relative;
        width: 6px;
        height: 6px;
        border-radius: 5px;
        background-color: var(--primary);
        color: var(--primary);
        animation: dot-flashing 1s infinite linear alternate;
        animation-delay: 0.5s;
    }

    .dot-flashing::before,
    .dot-flashing::after {
        content: "";
        display: inline-block;
        position: absolute;
        top: 0;
        width: 6px;
        height: 6px;
        border-radius: 5px;
        background-color: var(--primary);
        color: var(--primary);
    }

    .dot-flashing::before {
        left: -12px;
        animation: dot-flashing 1s infinite alternate;
        animation-delay: 0s;
    }

    .dot-flashing::after {
        left: 12px;
        animation: dot-flashing 1s infinite alternate;
        animation-delay: 1s;
    }

    @keyframes dot-flashing {
        0% {
            background-color: var(--primary);
        }

        50%,
        100% {
            background-color: rgba(128, 0, 0, 0.1);
        }
    }

    /* Input Area */
    .controls-wrapper {
        padding: 20px 20px 40px;
        background: var(--bg-body);
        border-top: 1px solid var(--border-glass);
    }

    .input-pill {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-glass);
        border-radius: 20px;
        padding: 10px 10px 10px 25px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        transition: all 0.3s;
    }

    .input-pill:focus-within {
        background: rgba(255, 255, 255, 0.05);
        border-color: var(--primary);
        box-shadow: 0 15px 40px rgba(128, 0, 0, 0.2);
    }

    .input-pill input,
    .input-pill textarea {
        flex: 1;
        background: transparent;
        border: none;
        color: white;
        font-family: inherit;
        font-size: 1rem;
        outline: none;
        padding: 10px 0;
    }

    .btn-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        font-size: 1.1rem;
    }

    .btn-mic {
        background: rgba(255, 255, 255, 0.05);
        color: var(--text-muted);
    }

    .btn-mic.active {
        background: #ef4444;
        color: white;
        animation: micPulse 1.5s infinite;
    }

    @keyframes micPulse {
        0% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
        }

        70% {
            transform: scale(1.1);
            box-shadow: 0 0 0 12px rgba(239, 68, 68, 0);
        }

        100% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
        }
    }

    .btn-submit {
        background: var(--primary);
        color: white;
        box-shadow: 0 5px 15px rgba(128, 0, 0, 0.3);
    }

    .btn-submit:hover {
        transform: scale(1.1);
        background: var(--primary-dark);
    }

    /* Security Overlay */
    .security-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.98);
        z-index: 9999;
        display: none;
        /* JS will show */
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 40px;
    }

    .security-card {
        background: #1a1a20;
        padding: 50px;
        border-radius: 24px;
        border: 2px solid var(--primary);
        max-width: 500px;
        box-shadow: 0 0 50px rgba(128, 0, 0, 0.3);
    }

    .btn-security {
        margin-top: 30px;
        padding: 15px 40px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 5px 15px rgba(128, 0, 0, 0.2);
    }

    /* Loading Screen for Report */
    .report-loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: #000;
        z-index: 10000;
        display: none;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
    }

    .loader-spinner {
        width: 80px;
        height: 80px;
        border: 5px solid rgba(255, 255, 255, 0.1);
        border-top: 5px solid var(--accent);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 30px;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* Premium Loader Styles */
    .premium-loader-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(20px);
        z-index: 10001;
        display: none;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        color: white;
        transition: all 0.5s ease;
    }

    .loader-content {
        max-width: 500px;
        width: 90%;
        animation: fadeInScale 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    @keyframes fadeInScale {
        from {
            opacity: 0;
            transform: scale(0.9) translateY(20px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .loader-visual {
        position: relative;
        width: 120px;
        height: 120px;
        margin: 0 auto 40px;
    }

    .orbit {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: 2px solid rgba(255, 255, 255, 0.05);
        border-radius: 50%;
    }

    .orbit-pulse {
        position: absolute;
        top: 10%;
        left: 10%;
        width: 80%;
        height: 80%;
        border: 2px solid var(--primary);
        border-radius: 50%;
        border-top-color: transparent;
        animation: spin 1.5s linear infinite;
    }

    .orbit-pulse-inner {
        position: absolute;
        top: 25%;
        left: 25%;
        width: 50%;
        height: 50%;
        border: 2px solid var(--accent);
        border-radius: 50%;
        border-bottom-color: transparent;
        animation: spin-reverse 2s linear infinite;
    }

    @keyframes spin-reverse {
        from {
            transform: rotate(360deg);
        }

        to {
            transform: rotate(0deg);
        }
    }

    .loader-steps {
        list-style: none;
        margin: 30px auto;
        text-align: left;
        display: inline-block;
        width: 100%;
    }

    .loader-step {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
        opacity: 0.3;
        transition: all 0.4s ease;
        font-size: 1.1rem;
        color: var(--text-muted);
    }

    .loader-step.active {
        opacity: 1;
        color: white;
        transform: translateX(10px);
    }

    .loader-step.completed {
        opacity: 0.6;
        color: #10b981;
    }

    .loader-step i {
        width: 24px;
        text-align: center;
    }

    .loader-permission-hint {
        margin-top: 40px;
        padding: 20px;
        background: rgba(233, 198, 111, 0.05);
        border: 1px solid rgba(233, 198, 111, 0.2);
        border-radius: 16px;
        font-size: 0.9rem;
        color: var(--accent);
        display: none;
        animation: fadeIn 0.5s ease;
    }

    .btn-launch-final {
        margin-top: 30px;
        background: var(--accent);
        color: black;
        padding: 15px 40px;
        border-radius: 12px;
        font-weight: 800;
        border: none;
        cursor: pointer;
        box-shadow: 0 10px 20px rgba(233, 198, 111, 0.2);
        display: none;
        animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    @keyframes bounceIn {
        0% {
            transform: scale(0.3);
            opacity: 0;
        }

        50% {
            transform: scale(1.05);
            opacity: 1;
        }

        70% {
            transform: scale(0.9);
        }

        100% {
            transform: scale(1);
        }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .session-header {
            padding: 0 20px;
        }

        .brand-text {
            display: none;
        }

        .message {
            max-width: 90%;
        }
    }

    /* Resumption Modal */
    #resumeModal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(15px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 2000;
    }

    .resume-card {
        background: #1a1a20;
        padding: 3rem;
        border-radius: 32px;
        text-align: center;
        max-width: 500px;
        width: 90%;
        border: 1px solid var(--border-glass);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }

    .resume-card h2 {
        font-size: 1.8rem;
        margin-bottom: 1rem;
        color: white;
    }

    .resume-card p {
        color: var(--text-muted);
        margin-bottom: 2rem;
        line-height: 1.6;
    }

    .resume-actions {
        display: flex;
        gap: 15px;
    }

    .btn-resume {
        flex: 1;
        padding: 15px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-new-session {
        flex: 1;
        padding: 15px;
        background: rgba(255, 255, 255, 0.05);
        color: white;
        border: 1px solid var(--border-glass);
        border-radius: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-new-session:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    /* MCQ Panel Layout */
    .mcq-panel {
        flex: 1;
        background: rgba(26, 26, 32, 0.4);
        border-right: 1px solid var(--border-glass);
        display: flex;
        flex-direction: column;
        padding: 3rem;
        overflow-y: auto;
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .mcq-header h3 {
        font-size: 1.5rem;
        color: var(--accent);
        margin-bottom: 1.5rem;
    }

    .mcq-question-body {
        font-size: 1.25rem;
        line-height: 1.7;
        color: var(--text-main);
        margin-bottom: 2.5rem;
    }

    .mcq-options-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .mcq-option {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-glass);
        padding: 18px 25px;
        border-radius: 16px;
        cursor: pointer;
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 1.1rem;
        font-weight: 500;
    }

    .mcq-option:hover {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(233, 198, 111, 0.5);
        transform: translateX(5px);
    }

    .mcq-option.selected {
        background: rgba(233, 198, 111, 0.1);
        border-color: var(--accent);
        color: var(--accent);
    }

    .mcq-option-badge {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-glass);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
    }

    .mcq-option.selected .mcq-option-badge {
        background: var(--accent);
        color: black;
        border-color: var(--accent);
    }

    /* Briefing Screen Overlay */
    .briefing-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.96);
        backdrop-filter: blur(25px);
        z-index: 15000;
        display: none;
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.4s ease;
    }

    .briefing-card {
        background: #16161c;
        border: 1px solid var(--border-glass);
        border-radius: 28px;
        padding: 3rem;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        text-align: center;
    }

    .briefing-card h2 {
        font-size: 2.2rem;
        color: white;
        margin-bottom: 1.5rem;
    }

    .briefing-metrics {
        display: flex;
        justify-content: space-around;
        margin: 2rem 0;
        background: rgba(255, 255, 255, 0.02);
        padding: 1.5rem;
        border-radius: 20px;
        border: 1px solid var(--border-glass);
    }

    .briefing-metrics div {
        display: flex;
        flex-direction: column;
        gap: 8px;
        font-weight: 600;
        color: var(--text-muted);
    }

    .briefing-metrics span {
        font-size: 1.25rem;
        color: white;
    }

    .skills-tag-list {
        display: flex;
        justify-content: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 2rem;
        list-style: none;
    }

    .skills-tag-list li {
        background: rgba(233, 198, 111, 0.1);
        color: var(--accent);
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 700;
        border: 1px solid rgba(233, 198, 111, 0.2);
    }

    /* Timeline Widget */
    .timeline-container {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.02);
        padding: 8px 18px;
        border-radius: 50px;
        border: 1px solid var(--border-glass);
        font-size: 0.8rem;
        font-weight: 700;
    }

    .timeline-node {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--text-muted);
        transition: all 0.3s ease;
    }

    .timeline-node.active {
        color: var(--accent);
    }

    .timeline-node.completed {
        color: #10b981;
    }

    .timeline-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
    }

    /* Diagnostics Panel Overlay */
    .diagnostics-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(10px);
    }

    .diagnostics-card {
        background: #111116;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        width: 550px;
        max-width: 90%;
        padding: 25px;
        color: #e2e8f0;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        font-family: 'Courier New', monospace;
    }

    .diagnostics-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 15px;
        margin-bottom: 15px;
    }

    .diagnostics-header h3 {
        margin: 0;
        color: var(--accent);
        font-size: 1.3rem;
    }

    .btn-close-diag {
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 1.8rem;
        cursor: pointer;
    }

    .diag-section {
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding-bottom: 10px;
    }

    .diag-section h4 {
        margin: 0 0 10px 0;
        color: #94a3b8;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 1px;
    }

    .diag-section p {
        margin: 5px 0;
        font-size: 0.9rem;
    }

    /* Score Modal Styles */
    #scoreModal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(8px);
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .score-card {
        background: linear-gradient(145deg, #1e1e2f, #11111a);
        padding: 50px;
        border-radius: 24px;
        text-align: center;
        border: 2px solid #333;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        max-width: 500px;
        width: 90%;
        color: white;
        animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }

    @keyframes popIn {
        0% {
            transform: scale(0.8);
            opacity: 0;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .score-title {
        font-size: 24px;
        color: #aaa;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 2px;
        font-weight: 600;
    }

    .score-number {
        font-size: 80px;
        font-weight: 900;
        color: #10b981;
        line-height: 1;
        text-shadow: 0 0 20px rgba(16, 185, 129, 0.4);
        margin-bottom: 5px;
    }

    .score-percentage {
        font-size: 30px;
        font-weight: 700;
        color: #10b981;
    }

    .score-zero {
        color: #ef4444;
        text-shadow: 0 0 20px rgba(239, 68, 68, 0.4);
    }

    .score-desc {
        font-size: 16px;
        color: #bbb;
        margin-bottom: 40px;
    }

    .btn-continue {
        background: #800000;
        color: white;
        border: none;
        padding: 15px 40px;
        border-radius: 12px;
        font-size: 18px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
        box-shadow: 0 10px 20px rgba(128, 0, 0, 0.3);
    }

    .btn-continue:hover {
        background: #a50000;
        transform: translateY(-3px);
    }

    /* Proctoring Overlays & Widgets */
    .overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(2, 6, 23, 0.95);
        z-index: 2000;
        display: flex; justify-content: center; align-items: center;
        flex-direction: column;
        overflow-y: auto;
        padding: 20px;
    }
    .hidden { display: none !important; }

    /* Proctor Floating Widget */
    .proctor-widget {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 175px;
        background: #0f172a;
        border: 2px solid rgba(255,255,255,0.15);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 15px 35px rgba(0,0,0,0.5);
        z-index: 9999;
        display: none;
    }
    .proctor-widget video {
        width: 100%;
        height: 110px;
        object-fit: cover;
        background: #000;
        transform: scaleX(-1); /* mirror preview like a selfie camera */
    }
    .proctor-widget-bar {
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        background: #020617;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .proctor-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #10b981;
        display: inline-block;
        box-shadow: 0 0 8px #10b981;
        animation: proctor-pulse 1.5s infinite;
    }
    @keyframes proctor-pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

    /* Calibration Target */
    .calib-target {
        width: 24px; height: 24px;
        background: #f59e0b;
        border-radius: 50%;
        margin: 1.5rem auto;
        box-shadow: 0 0 25px #f59e0b;
        animation: calib-pulse 1s infinite alternate;
    }
    @keyframes calib-pulse { from { transform: scale(0.85); opacity: 0.7; } to { transform: scale(1.3); opacity: 1; } }

    /* Assessment Integrity Card */
    .integrity-card {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 18px;
        padding: 18px;
        margin: 20px 0;
        text-align: left;
    }
    .integrity-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 12px;
    }
    .integrity-metric {
        background: rgba(0, 0, 0, 0.4);
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.06);
        font-size: 0.8rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .integrity-metric .label {
        font-weight: 600;
        color: #94a3b8;
    }
    .integrity-metric .val {
        font-weight: 800;
        color: #fff;
    }
    .integrity-pill {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 700;
        margin-top: 10px;
    }
    .integrity-pill.success { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
    .integrity-pill.warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }

    /* Score Breakdown Box */
    .score-breakdown-box {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 14px;
        padding: 12px 18px;
        margin: 16px 0;
        font-size: 0.9rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>
</head>

<body>

    <header class="session-header">
        <div style="display: flex; align-items: center;">
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="brand-logo">
                <div class="logo-icon"><i class="fas <?php echo $logoIcon; ?>"></i></div>
                <div class="brand-text">
                    <h1><?php echo $roundType; ?> INTERVIEW</h1>
                    <span>AI MOCK SESSION • <?php echo htmlspecialchars($companyName); ?></span>
                </div>
            </div>
        </div>

        <div class="session-status" id="sessionStatus" style="display: none; align-items: center; gap: 8px;">
            <div class="status-dot"></div>
            <span id="liveNetworkText">Connected</span>
            <span style="opacity:0.4;">|</span>
            <span id="liveAutosaveText" style="font-size:0.75rem; opacity:0.8;">Autosaved just now</span>
            <span style="opacity:0.4;">|</span>
            <span id="liveLatencyText" style="font-size:0.75rem; opacity:0.8;">Latency: 0ms</span>
        </div>

        <div class="timeline-container" id="timelineContainer" style="display: none;">
            <div class="timeline-node" id="node-1">
                <div class="timeline-dot"></div> Aptitude
            </div>
            <div style="color: var(--text-muted);">➔</div>
            <div class="timeline-node" id="node-2">
                <div class="timeline-dot"></div> Coding
            </div>
            <div style="color: var(--text-muted);">➔</div>
            <div class="timeline-node" id="node-3">
                <div class="timeline-dot"></div> HR Round
            </div>
        </div>

        <div style="display: flex; gap: 15px; align-items: center;">
            <span id="warningBadgeNav" style="display: none; background: rgba(255,255,255,0.12); padding: 5px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; border: 1px solid rgba(255,255,255,0.15);">
                <i class="fas fa-shield-alt" style="color: #10b981;"></i> Warnings: <span id="warningCountNavText" style="color: #10b981;">0</span> / 3
            </span>
            <button class="btn-workspace" id="toggleWorkspace" style="display:none;" onclick="toggleCodingPanel()">
                <i class="fas fa-code"></i> Coding Workspace
            </button>
            <button class="btn-end" onclick="dispatcher.dispatch('EndInterviewCommand', {})">
                <i class="fas fa-power-off"></i> End Session
            </button>
        </div>
    </header>

    <!-- Concept & Difficulty Selection Overlay -->
    <div id="roleSelection">
        <div class="role-modal">
            <div style="font-size: 3rem; color: var(--accent); margin-bottom: 1.5rem;"><i class="fas fa-brain"></i>
            </div>
            <h2>Preparing Your Session</h2>
            <p>Your AI <?php echo $roundType; ?> Interviewer is analyzing the requirements for
                <b><?php echo htmlspecialchars($companyName); ?></b>. Which concepts/topics and difficulty would you like to target?</p>

            <div class="role-input-wrap" style="text-align: left; margin-bottom: 1.5rem;">
                <label style="font-size: 0.85rem; color: var(--text-muted); margin-left: 5px; margin-bottom: 5px; display: block;">Concepts / Topics (comma-separated, at least 1):</label>
                <input type="text" id="customConcepts" class="role-input"
                    placeholder="e.g. <?php echo $roundType === 'HR' ? 'Behavioral, Leadership, Teamwork' : 'React, Data Structures, OOP'; ?>"
                    value="<?php echo $roundType === 'HR' ? 'HR Behavioral Round' : ''; ?>">
            </div>

            <div class="role-input-wrap" style="text-align: left; position: relative; margin-bottom: 2rem;">
                <label style="font-size: 0.85rem; color: var(--text-muted); margin-left: 5px; margin-bottom: 5px; display: block;">Difficulty Level:</label>
                <select id="customDifficulty" class="role-input" style="appearance: none; -webkit-appearance: none; cursor: pointer; padding-right: 50px;">
                    <option value="Low">Low Difficulty</option>
                    <option value="Medium" selected>Medium Difficulty</option>
                    <option value="High">High Difficulty</option>
                </select>
                <i class="fas fa-chevron-down" style="position: absolute; right: 25px; top: calc(50% + 10px); transform: translateY(-50%); color: var(--text-muted); pointer-events: none;"></i>
            </div>

            <button class="btn-start" onclick="startInterviewWithCustomRole()">
                Begin <?php echo $roundType; ?> Round <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
            </button>
        </div>
    </div>

    <!-- Premium Loader Overlay -->
    <div id="premiumLoader" class="premium-loader-overlay">
        <div class="loader-content">
            <div class="loader-visual">
                <div class="orbit"></div>
                <div class="orbit-pulse"></div>
                <div class="orbit-pulse-inner"></div>
                <i class="fas fa-microchip"
                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 2rem; color: white;"></i>
            </div>
            <h2 style="font-size: 2.2rem; margin-bottom: 10px;">Initializing AI Session</h2>
            <p style="color: var(--text-muted); margin-bottom: 30px;">Setting up your proctored environment...</p>

            <ul class="loader-steps">
                <li class="loader-step" id="step-1"><i class="fas fa-search"></i> Analyzing requirements for <span
                        id="targetRoleLabel"></span>...</li>
                <li class="loader-step" id="step-2"><i class="fas fa-cog"></i> Configuring AI Interviewer...</li>
                <li class="loader-step" id="step-3"><i class="fas fa-briefcase"></i> Preparing Industry Scenarios...
                </li>
                <li class="loader-step" id="step-4"><i class="fas fa-shield-alt"></i> Activating Security Protocols...
                </li>
            </ul>

            <div id="permissionHint" class="loader-permission-hint">
                <i class="fas fa-info-circle"></i>
                Please <b>ALLOW</b> Microphone and Fullscreen access if prompted by your browser to begin the session.
            </div>

            <button id="finalLaunchBtn" class="btn-launch-final" onclick="executeFinalLaunch()">
                LAUNCH PROCTORED SESSION <i class="fas fa-rocket" style="margin-left: 8px;"></i>
            </button>
        </div>
    </div>

    <!-- Briefing Overlay -->
    <div id="briefingOverlay" class="briefing-overlay">
        <div class="briefing-card">
            <h2 id="briefingTitle">Quantitative Aptitude</h2>
            <div class="briefing-metrics">
                <div>
                    <span>Duration</span>
                    <span id="briefingDuration">10 Min</span>
                </div>
                <div>
                    <span>Questions</span>
                    <span id="briefingQCount">10 Qs</span>
                </div>
            </div>
            <ul class="skills-tag-list" id="briefingSkills">
                <li>Arithmetic</li>
                <li>Algebra</li>
            </ul>
            <button class="btn-start" onclick="dismissBriefingAndStart()">BEGIN ROUND</button>
        </div>
    </div>

    <div class="workspace-wrapper">
        <!-- MCQ Panel -->
        <div id="mcqPanel" class="mcq-panel" style="display: none;">
            <div class="mcq-header">
                <h3 id="mcqTitle">Question 1 of 10</h3>
            </div>
            <div id="mcqQuestionBody" class="mcq-question-body">
                Loading question...
            </div>
            <div id="mcqOptionsContainer" class="mcq-options-container">
                <!-- Rendered by MCQComponent -->
            </div>
        </div>

        <main class="chat-container" id="chatContainer">
            <div class="chat-messages" id="chatHistory">
                <!-- Messages will appear here -->
            </div>

            <div id="typingIndicator" class="typing-hint">
                <div class="dot-flashing"></div>
                <span>AI is analyzing your response...</span>
            </div>

            <div class="controls-wrapper">
                <div class="input-pill">
                    <button class="btn-circle btn-mic" id="btnSpeak" title="Voice Input">
                        <i class="fas fa-microphone"></i>
                    </button>
                    <textarea id="userInput" placeholder="Type your answer here..." autocomplete="off" rows="1"
                        style="resize: none; overflow-y: auto; max-height: 120px; line-height: 1.5; align-self: center;"></textarea>
                    <button class="btn-circle btn-submit" id="btnSend">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </main>

        <aside class="coding-panel" id="codingPanel">
            <div class="coding-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-terminal" style="color: var(--accent);"></i>
                    <span style="font-weight: 700; font-size: 0.9rem;">CODING WORKSPACE</span>
                </div>
                <select id="langSelector"
                    style="background:#222; color:white; border:1px solid #444; padding:5px; border-radius:5px; font-size: 0.8rem;">
                    <option value="python">Python</option>
                    <option value="javascript">JavaScript</option>
                    <option value="text/x-java">Java</option>
                    <option value="text/x-c++src">C++</option>
                </select>
            </div>
            <div class="coding-editor-container">
                <textarea id="codeEditor"></textarea>
            </div>
            <div class="coding-console" id="codingConsole">
                <span class="console-label">Execution Console</span>
                <div class="console-out" id="consoleOutput">// Ready for execution...</div>
            </div>
            <div class="coding-footer">
                <button class="btn-send-code" onclick="sendCodeToAI()">
                    <i class="fas fa-paper-plane"></i> Share
                </button>
                <button class="btn-run-code" id="btnRunCode" onclick="dispatcher.dispatch('RunCodeCommand', {})">
                    <i class="fas fa-play"></i> Run Code
                </button>
            </div>
        </aside>
    </div>

    <!-- Security Warning Overlay -->
    <div id="securityWarning" class="security-overlay">
        <div class="security-card">
            <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #ef4444; margin-bottom: 25px;"></i>
            <h2 style="font-size: 2rem; margin-bottom: 15px; color: white;">Security Violation</h2>
            <p style="color: #94a3b8; line-height: 1.6;">You have exited <b>FULL SCREEN</b> mode. This is a violation of
                the proctoring rules. Please return to full screen immediately to continue your interview.</p>
            <button class="btn-security" onclick="resumeFullscreen()">RESUME INTERVIEW</button>
        </div>
    </div>

    <!-- Report Loading Overlay -->
    <div id="reportLoading" class="report-loading-overlay">
        <div class="loader-spinner"></div>
        <h2 style="color: white; font-size: 2rem; margin-bottom: 10px;">Generating Analytics</h2>
        <p style="color: var(--text-muted);">Please wait while AI analyzes your performance and generates a
            comprehensive report...</p>
    </div>

    <!-- Session Resumption Modal -->
    <div id="resumeModal">
        <div class="resume-card">
            <div style="font-size: 3.5rem; color: var(--accent); margin-bottom: 1.5rem;"><i class="fas fa-history"></i>
            </div>
            <h2>Active Session Found</h2>
            <p>You have an ongoing interview session for <b><span id="resumeRole"></span></b>. Would you like to resume
                where you left off or start a fresh session?</p>
            <div class="resume-actions">
                <button class="btn-new-session" id="btnStartFresh">START FRESH</button>
                <button class="btn-resume" id="btnResumeSession">RESUME SESSION</button>
            </div>
        </div>
    </div>

    <!-- Score & Assessment Report Modal -->
    <div id="scoreModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(2, 6, 23, 0.95); z-index: 2500; justify-content: center; align-items: center; overflow-y: auto; padding: 20px;">
        <div class="score-card" style="max-width: 600px; width: 95%; background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(255,255,255,0.12); border-radius: 28px; padding: 2.2rem; box-shadow: 0 30px 80px rgba(0,0,0,0.7); text-align: center;">
            <div id="scoreHeaderIcon" style="font-size: 3.5rem; color: #10b981; margin-bottom: 1rem;"><i class="fas fa-award"></i></div>
            <div class="score-title" style="font-size: 1.4rem; color: #fff; font-weight: 800; margin-bottom: 4px;">Assessment Complete</div>
            <div class="score-desc" style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">Official AI Interview Performance & Integrity Report</div>
            
            <div style="margin: 1.2rem 0;">
                <span id="finalScoreNum" class="score-number" style="font-size: 4rem; font-weight: 900; color: #10b981;">0</span><span id="finalScorePct" class="score-percentage" style="font-size: 2rem; font-weight: 700; color: #10b981;">%</span>
                <div id="scoreBadgeText" style="font-size: 0.95rem; font-weight: 700; color: #10b981; margin-top: 4px;">INTERVIEW PASSED</div>
            </div>

            <!-- Score Penalty Breakdown Box -->
            <div class="score-breakdown-box">
                <div style="text-align: left;">
                    <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 600; text-transform: uppercase;">Raw Evaluation Score</div>
                    <div style="font-size: 1.1rem; font-weight: 800; color: #fff;" id="rptRawScore">0%</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.75rem; color: #f87171; font-weight: 600; text-transform: uppercase;">Proctoring Penalty</div>
                    <div style="font-size: 1.1rem; font-weight: 800; color: #f87171;" id="rptPenaltyPct">0%</div>
                </div>
            </div>

            <!-- Assessment Integrity Audit Card -->
            <div class="integrity-card" id="integrityReportCard">
                <h3 style="font-size: 0.95rem; color: #fff; font-weight: 700; margin-bottom: 8px;">
                    <i class="fas fa-shield-alt" style="color: #10b981; margin-right: 6px;"></i> Assessment Integrity Audit
                </h3>
                <div class="integrity-pill success" id="rptStatusPill">Integrity Status: Verified</div>
                <div class="integrity-grid">
                    <div class="integrity-metric">
                        <div class="label">Screen Share</div>
                        <div class="val" id="rptScreenShare">100%</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Camera Feed</div>
                        <div class="val" id="rptCameraAvail">100%</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Face Presence</div>
                        <div class="val" id="rptFacePres">100%</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Gaze Confidence</div>
                        <div class="val" id="rptGazeConf">92%</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Deviations</div>
                        <div class="val" id="rptAttnDev">0</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Max Deviation</div>
                        <div class="val" id="rptLongestDev">0s</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Screen Interruptions</div>
                        <div class="val" id="rptScreenInt">0</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Multi-Face</div>
                        <div class="val" id="rptMultiFace">0</div>
                    </div>
                </div>
            </div>

            <button class="btn-continue" onclick="closeSession()" style="margin-top: 10px;">RETURN TO DASHBOARD</button>
        </div>
    </div>

    <!-- 3-Step Setup Intro Overlay -->
    <div id="introOverlay" class="overlay" style="display: none;">
        <div style="text-align: center; max-width: 600px; width: 92%; padding: 2.2rem; background: rgba(15, 23, 42, 0.96); backdrop-filter: blur(30px); border: 1px solid rgba(255,255,255,0.12); border-radius: 36px; box-shadow: 0 40px 100px rgba(0,0,0,0.85);">
            
            <div style="width: 65px; height: 65px; background: rgba(128, 0, 0, 0.15); border-radius: 18px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.2rem; border: 1px solid var(--primary);">
                <i class="fas fa-shield-alt" style="font-size: 2rem; color: var(--primary);"></i>
            </div>
            <h1 style="color: #fff; margin-bottom: 0.4rem; font-size: 1.7rem;">AI Interview Verification</h1>
            <h2 style="color: var(--accent); margin-bottom: 1.4rem; font-size: 1.05rem;" id="introTargetTitle"><?php echo htmlspecialchars($companyName); ?> • <?php echo $roundType; ?></h2>

            <!-- Step Indicator -->
            <div style="display: flex; justify-content: space-around; margin-bottom: 1.4rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 12px; font-size: 0.85rem; font-weight: 700;">
                <span id="stepTab1" style="color: var(--primary);"><i class="fas fa-video"></i> 1. Camera</span>
                <span id="stepTab2" style="color: #64748b;"><i class="fas fa-crosshairs"></i> 2. Calibration</span>
                <span id="stepTab3" style="color: #64748b;"><i class="fas fa-desktop"></i> 3. Screen Share</span>
            </div>

            <!-- Step 1 View: Camera Setup -->
            <div id="stepView1">
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">
                    Enable your webcam feed to establish active proctoring during the AI interview session.
                </p>
                <video id="setupWebcamPreview" autoplay muted playsinline style="width: 220px; height: 145px; border-radius: 16px; background: #000; border: 2px solid rgba(255,255,255,0.15); margin: 0 auto 1.2rem; object-fit: cover; display: block; transform: scaleX(-1);"></video>
                <div id="setupCheckStatus" style="font-size: 0.85rem; font-weight: 700; color: #f59e0b; margin-bottom: 1.4rem;">
                    Requesting camera permission…
                </div>
                <button id="btnGrantCamera" onclick="initCameraSetup()" class="btn-continue" style="padding: 12px 30px; font-size: 1rem;">
                    Enable Camera & Continue <i class="fas fa-arrow-right"></i>
                </button>
            </div>

            <!-- Step 2 View: Baseline Calibration -->
            <div id="stepView2" class="hidden">
                <h3 style="font-size: 1.1rem; color: #fff; margin-bottom: 8px;">Gaze Baseline Calibration</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;" id="calibPromptText">
                    Look directly at the center target below.
                </p>
                <!-- Live face preview so the student can see themselves while calibrating -->
                <video id="calibWebcamPreview" autoplay muted playsinline style="width: 220px; height: 145px; border-radius: 16px; background: #000; border: 2px solid rgba(255,255,255,0.15); margin: 0 auto 0.6rem; object-fit: cover; display: block; transform: scaleX(-1);"></video>
                <div class="calib-target" id="calibTarget"></div>
                <div id="calibProgressText" style="font-size: 0.9rem; font-weight: 700; color: var(--accent); margin-bottom: 1.4rem;">
                    Progress: Center (0/3s)
                </div>
                <button id="btnRetryCalib" onclick="startCalibrationFlow()" class="btn-continue hidden" style="padding: 12px 30px; font-size: 1rem;">
                    <i class="fas fa-redo"></i> Retry Calibration
                </button>
            </div>

            <!-- Step 3 View: Screen Share Setup -->
            <div id="stepView3" class="hidden">
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">
                    Share your entire desktop screen to establish anti-cheating and integrity compliance.
                </p>
                <div style="font-size: 3rem; color: var(--primary); margin: 1.2rem 0;"><i class="fas fa-desktop"></i></div>
                <div id="screenCheckStatus" style="font-size: 0.85rem; font-weight: 700; color: #f59e0b; margin-bottom: 1.4rem;">
                    Ready to share screen…
                </div>
                <button id="btnGrantScreen" onclick="initScreenShareSetup()" class="btn-continue" style="padding: 12px 30px; font-size: 1rem;">
                    Share Screen & Launch Interview <i class="fas fa-rocket"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Floating Proctor Widget -->
    <div id="proctorWidget" class="proctor-widget">
        <video id="proctorWebcamVideo" autoplay muted playsinline></video>
        <div class="proctor-widget-bar" style="flex-direction: column; align-items: flex-start; gap: 2px;">
            <div style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
                <span><span class="proctor-dot"></span> Proctor Active</span>
                <span id="proctorWidgetStatus" style="font-weight: 800; color: #10b981;">100%</span>
            </div>
            <div id="proctorWidgetLabel" style="font-size: 9px; font-weight: 700; color: #10b981;"><i class="fas fa-user-check"></i> Face In Frame</div>
        </div>
    </div>

    <!-- Warning Overlay (3 Strikes) -->
    <div id="warningOverlay" class="overlay hidden">
        <div style="text-align: center; max-width: 500px; padding: 2.5rem; border: 1px solid var(--primary); background: #000; border-radius: 30px; box-shadow: 0 0 50px rgba(128, 0, 0, 0.4);">
            <i id="warningIcon" class="fas fa-exclamation-triangle" style="color: #f59e0b; font-size: 3.8rem; margin-bottom: 1.2rem;"></i>
            <h2 id="warningTitle" style="margin-bottom: 0.8rem; color: #fff; font-size: 1.3rem;">SECURITY VIOLATION — WARNING 1 OF 3</h2>
            <p id="warningMessage" style="color: var(--text-muted); margin-bottom: 2rem; line-height: 1.6; font-size: 0.95rem;">
                Fullscreen mode has been deactivated. You have exited proctoring.
            </p>
            <button id="warningBtn" onclick="resumeFullscreen()" class="btn-continue" style="width: 100%;">RESUME ASSESSMENT</button>
        </div>
    </div>

    <!-- Hidden Proctor Analysis Canvas -->
    <canvas id="proctorAnalysisCanvas" width="320" height="240" style="display: none;"></canvas>

    <!-- Diagnostics Panel Overlay -->
    <div id="diagnosticsPanel" class="diagnostics-overlay" style="display: none;">
        <div class="diagnostics-card">
            <div class="diagnostics-header">
                <h3><i class="fas fa-terminal"></i> LAR Diagnostics</h3>
                <button class="btn-close-diag" onclick="toggleDiagnostics()">&times;</button>
            </div>
            <div class="diagnostics-body">
                <div class="diag-section">
                    <h4>System State</h4>
                    <p>Session ID: <span id="diagSessionId">N/A</span></p>
                    <p>Current Step: <span id="diagStep">N/A</span></p>
                    <p>Phase: <span id="diagPhase">N/A</span></p>
                </div>
                <div class="diag-section">
                    <h4>Active Components</h4>
                    <ul id="diagComponents"></ul>
                </div>
                <div class="diag-section">
                    <h4>Runtime Stats</h4>
                    <p>Timer Remaining: <span id="diagTimer">N/A</span>s</p>
                    <p>Events Logged: <span id="diagEventsCount">0</span></p>
                </div>
                <div class="diag-section">
                    <h4>Speech Engine</h4>
                    <p>API Status: <span id="diagSpeechAPI">Unsupported</span></p>
                    <p>Speaking: <span id="diagSpeaking">No</span></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        // json_encode keeps quotes/backslashes/closing script tags in these values from breaking the script
        const ROUND_TYPE = <?php echo json_encode($roundType, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const COMPANY_NAME = <?php echo json_encode($companyName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        let currentSessionId = null;
        let selectedRole = '';
        let editor = null;
        let isProctoringActive = false; // Flag for security monitoring

        const chatHistory = document.getElementById('chatHistory');
        const userInput = document.getElementById('userInput');
        const btnSend = document.getElementById('btnSend');
        const btnSpeak = document.getElementById('btnSpeak');
        const typingIndicator = document.getElementById('typingIndicator');
        const sessionStatus = document.getElementById('sessionStatus');
        const toggleWorkspaceBtn = document.getElementById('toggleWorkspace');
        const codingPanel = document.getElementById('codingPanel');

        // --- LAKSHYA ASSESSMENT RUNTIME (LAR) ARCHITECTURE ---
        class EventBus {
            constructor() { this.listeners = {}; }
            on(event, cb) {
                if (!this.listeners[event]) this.listeners[event] = [];
                this.listeners[event].push(cb);
            }
            emit(event, data) {
                if (!this.listeners[event]) return;
                this.listeners[event].forEach(cb => cb(data));
            }
        }

        class ComponentRegistry {
            constructor() { this.components = new Map(); }
            register(name, compClass) { this.components.set(name, compClass); }
            create(name, container, eventBus) {
                const CompClass = this.components.get(name);
                if (!CompClass) throw new Error("Component " + name + " not registered");
                return new CompClass(container, eventBus);
            }
        }

        class StateStore {
            constructor() {
                this.state = {
                    currentStep: null,
                    editor: {
                        language: 'python',
                        code: ''
                    },
                    timerRemaining: 600, // 10 mins default
                    voiceEnabled: false,
                    chatHistory: [],
                    version: 0,
                    timestamp: Date.now(),
                    performance: {
                        backendLatency: 0,
                        fps: 60,
                        autosaveSuccess: 100,
                        apiResponseTimes: []
                    },
                    telemetry: {
                        schema_version: 2,
                        events: []
                    }
                };
                this.listeners = [];
            }
            subscribe(listener) {
                this.listeners.push(listener);
            }
            update(key, val) {
                if (Array.isArray(val)) {
                    this.state[key] = val;
                } else if (typeof val === 'object' && val !== null) {
                    this.state[key] = { ...this.state[key], ...val };
                } else {
                    this.state[key] = val;
                }
                this.notify();
            }
            setState(newState) {
                this.state = { ...this.state, ...newState };
                this.notify();
            }
            notify() {
                this.listeners.forEach(listener => listener(this.state));
                this.persist();
            }
            persist() {
                if (currentSessionId) {
                    this.state.version = (this.state.version || 0) + 1;
                    this.state.timestamp = Date.now();
                    localStorage.setItem(`lar_session_${currentSessionId}`, JSON.stringify(this.state));
                }
            }
            restore(sessionId) {
                const data = localStorage.getItem(`lar_session_${sessionId}`);
                if (data) {
                    try {
                        this.state = JSON.parse(data);
                        this.notify();
                        return true;
                    } catch (e) {
                        console.error("Failed to restore checkpoint", e);
                    }
                }
                return false;
            }
        }

        class RuntimeScheduler {
            constructor(eventBus, stateStore) {
                this.bus = eventBus;
                this.store = stateStore;
                this.intervalId = null;
                this.ticks = 0;
            }
            start() {
                if (this.intervalId) return;
                this.intervalId = setInterval(() => this.tick(), 1000);
            }
            stop() {
                if (this.intervalId) {
                    clearInterval(this.intervalId);
                    this.intervalId = null;
                }
            }
            tick() {
                this.ticks++;
                let currentTimer = this.store.state.timerRemaining;
                if (currentTimer !== null && currentTimer > 0) {
                    currentTimer--;
                    this.store.update('timerRemaining', currentTimer);
                    this.bus.emit('TIMER_TICK', { remaining: currentTimer });
                    if (currentTimer === 0) {
                        this.bus.emit('TIMER_EXPIRED');
                    }
                }
                if (this.ticks % 20 === 0) {
                    this.bus.emit('AUTOSAVE_TRIGGER');
                }
            }
        }

        class CommandDispatcher {
            constructor(runtime, bus) {
                this.runtime = runtime;
                this.bus = bus;
            }
            dispatch(commandName, payload) {
                console.log(`[Command Dispatcher] Executing: ${commandName}`, payload);

                const eventLog = stateStore.state.telemetry.events;
                eventLog.push({
                    t: Date.now(),
                    event: commandName,
                    payload: payload
                });
                stateStore.update('telemetry', { events: eventLog });

                switch (commandName) {
                    case 'SubmitAnswerCommand':
                        sendMessage(payload.answer);
                        break;
                    case 'RunCodeCommand':
                        runCodeSimulation();
                        break;
                    case 'SelectMCQCommand':
                        sendMessage(payload.option);
                        break;
                    case 'StartVoiceCommand':
                        this.runtime.startListening();
                        break;
                    case 'EndInterviewCommand':
                        endSessionManual();
                        break;
                    default:
                        console.warn("Unknown Command", commandName);
                }
            }
        }

        class RuntimeSupervisor {
            constructor(runtime, bus) {
                this.runtime = runtime;
                this.bus = bus;
                this.supervisorInterval = null;
            }
            start() {
                this.supervisorInterval = setInterval(() => this.inspect(), 5000);
            }
            stop() {
                if (this.supervisorInterval) clearInterval(this.supervisorInterval);
            }
            inspect() {
                if (this.runtime.components.voice && stateStore.state.voiceEnabled) {
                    // reboot if needed
                }
                if (this.runtime.components.editor && !this.runtime.components.editor.editor) {
                    console.warn("Supervisor: Code editor crashed. Rebooting component...");
                    this.runtime.components.editor.init();
                }
            }
        }

        class OfflineQueue {
            constructor() {
                this.queue = [];
                this.isProcessing = false;
                window.addEventListener('online', () => this.flush());
            }
            enqueue(actionData) {
                this.queue.push(actionData);
                this.showWarning();
                localStorage.setItem('lar_offline_queue', JSON.stringify(this.queue));
            }
            showWarning() {
                let notice = document.getElementById('offlineNotice');
                if (!notice) {
                    notice = document.createElement('div');
                    notice.id = 'offlineNotice';
                    notice.style = 'position:fixed;bottom:20px;right:20px;background:#ef4444;color:white;padding:10px 20px;border-radius:8px;z-index:9999;font-weight:bold;box-shadow:0 4px 12px rgba(0,0,0,0.3);';
                    notice.innerText = '⚠️ Connection Lost. Actions queued offline.';
                    document.body.appendChild(notice);
                }
                notice.style.display = 'block';
            }
            hideWarning() {
                const notice = document.getElementById('offlineNotice');
                if (notice) notice.style.display = 'none';
            }
            async flush() {
                if (this.isProcessing || this.queue.length === 0) return;
                this.isProcessing = true;
                this.hideWarning();

                console.log(`[Offline Queue] Reconnected. Syncing ${this.queue.length} items...`);
                while (this.queue.length > 0) {
                    const item = this.queue[0];
                    try {
                        await fetch(item.url, {
                            method: 'POST',
                            headers: item.headers,
                            body: JSON.stringify(item.body)
                        });
                        this.queue.shift();
                    } catch (e) {
                        console.warn("[Offline Queue] Sync failed. Will retry later.", e);
                        this.showWarning();
                        break;
                    }
                }
                localStorage.setItem('lar_offline_queue', JSON.stringify(this.queue));
                this.isProcessing = false;
            }
        }

        const eventBus = new EventBus();
        const registry = new ComponentRegistry();
        const stateStore = new StateStore();
        const scheduler = new RuntimeScheduler(eventBus, stateStore);
        const offlineQueue = new OfflineQueue();

        class ChatComponent {
            constructor(container, bus) {
                this.container = container;
                this.bus = bus;
                this.bus.on('MESSAGE_RECEIVED', data => this.addBubble(data.role, data.content));
            }
            addBubble(role, content) {
                const history = stateStore.state.chatHistory;
                history.push({ role, content });
                stateStore.update('chatHistory', history);

                const div = document.createElement('div');
                div.className = `chat-bubble bubble-${role === 'user' ? 'student' : (role === 'system' ? 'system' : 'interviewer')}`;

                // AI / user / server text is escaped first so it can never inject HTML or scripts
                let formatted = escapeHtml(String(content ?? ''));
                if (role !== 'system') {
                    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                    formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
                    formatted = formatted.replace(/\n/g, '<br>');
                }

                div.innerHTML = `
                <div class="bubble-avatar">${role === 'user' ? '👤' : (role === 'system' ? '⚙️' : '🤖')}</div>
                <div class="bubble-content">
                    <span class="bubble-sender">${role === 'user' ? 'You' : (role === 'system' ? 'SYSTEM' : 'Interviewer')}</span>
                    <p>${formatted}</p>
                </div>
            `;
                this.container.appendChild(div);
                renderMath(div);
                this.container.scrollTop = this.container.scrollHeight;
            }
        }

        class EditorComponent {
            constructor(container, bus) {
                this.container = container;
                this.bus = bus;
                this.editor = null;
                this.init();
            }
            init() {
                const textarea = document.getElementById("codeEditor");
                if (!textarea) return;
                this.editor = CodeMirror.fromTextArea(textarea, {
                    mode: "python",
                    theme: "dracula",
                    lineNumbers: true,
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    indentUnit: 4,
                    tabSize: 4,
                    lineWrapping: true
                });
                this.editor.on('change', () => {
                    stateStore.update('editor', { code: this.editor.getValue() });
                });
                document.getElementById('langSelector').onchange = (e) => {
                    this.editor.setOption("mode", e.target.value);
                    stateStore.update('editor', { language: e.target.value });
                };
            }
            getValue() {
                return this.editor ? this.editor.getValue().trim() : '';
            }
            setValue(val) {
                if (this.editor) this.editor.setValue(val);
            }
            refresh() {
                if (this.editor) this.editor.refresh();
            }
        }

        class MCQComponent {
            constructor(container, bus) {
                this.container = container;
                this.bus = bus;
                this.selectedOption = null;
            }
            renderQuestion(question, options) {
                this.container.innerHTML = '';
                this.selectedOption = null;
                options.forEach(opt => {
                    const div = document.createElement('div');
                    div.className = 'mcq-option';
                    div.innerHTML = `
                    <div class="mcq-option-badge">${escapeHtml(String(opt.key ?? ''))}</div>
                    <div>${escapeHtml(String(opt.text ?? ''))}</div>
                `;
                    div.onclick = () => {
                        this.selectOption(opt.key, div);
                    };
                    this.container.appendChild(div);
                });
            }
            selectOption(key, el) {
                const options = this.container.querySelectorAll('.mcq-option');
                options.forEach(opt => opt.classList.remove('selected'));
                el.classList.add('selected');
                this.selectedOption = key;
                this.bus.emit('MCQ_OPTION_SELECTED', { option: key });
            }
        }

        class VoiceComponent {
            constructor(container, bus) {
                this.bus = bus;
                const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
                this.recognition = null;
                try { this.recognition = SpeechRec ? new SpeechRec() : null; } catch (e) { console.warn("Speech recognition unavailable:", e); }
                this.synth = window.speechSynthesis || null;
                this.isSpeaking = false;
                // Tracked on the instance (not in the persisted state) so a restored checkpoint can't leave the mic stuck "on"
                this.isListening = false;
                this.speechQueue = [];
                this.setupRecognition();

                const btnSpeak = document.getElementById('btnSpeak');
                if (btnSpeak) {
                    btnSpeak.onclick = () => {
                        dispatcher.dispatch('StartVoiceCommand', {});
                    };
                }
            }
            setupRecognition() {
                if (!this.recognition) return;
                this.recognition.continuous = true;
                this.recognition.interimResults = true;
                this.recognition.lang = 'en-US';
                this.recognition.onstart = () => {
                    this.isListening = true;
                    const btnSpeak = document.getElementById('btnSpeak');
                    if (btnSpeak) btnSpeak.classList.add('active');
                    stateStore.update('voiceEnabled', true);
                    this.currentSpeechFinal = userInput.value;
                };
                this.recognition.onend = () => {
                    this.isListening = false;
                    const btnSpeak = document.getElementById('btnSpeak');
                    if (btnSpeak) btnSpeak.classList.remove('active');
                    stateStore.update('voiceEnabled', false);
                    // Do NOT auto-submit — let the student review and submit manually
                    // The transcribed text stays in userInput for them to edit/send
                };
                this.recognition.onresult = (e) => {
                    let interimTranscript = '';
                    let finalTranscript = '';
                    for (let i = e.resultIndex; i < e.results.length; ++i) {
                        if (e.results[i].isFinal) {
                            finalTranscript += e.results[i][0].transcript;
                        } else {
                            interimTranscript += e.results[i][0].transcript;
                        }
                    }
                    this.bus.emit('SPEECH_CAPTURED', { finalTranscript, interimTranscript, source: this });
                };
                this.recognition.onerror = (e) => {
                    console.error("Speech recognition error", e.error);
                    this.isListening = false;
                    const btnSpeak = document.getElementById('btnSpeak');
                    if (btnSpeak) btnSpeak.classList.remove('active');
                    stateStore.update('voiceEnabled', false);
                    if (e.error === 'not-allowed' || e.error === 'service-not-allowed') {
                        LakshyaDialog.alert('Microphone or speech recognition access was denied. Allow microphone access for this site in your browser and try again.\n\nOn a Mac, also check System Settings → Privacy & Security → Microphone (and Speech Recognition for Safari), then restart the browser.\n\nYou can still type your answers.', { type: 'warning', title: 'Voice Input Blocked' });
                    } else if (e.error === 'audio-capture') {
                        LakshyaDialog.alert('No microphone was found. Please connect a microphone, or type your answers instead.', { type: 'warning', title: 'No Microphone' });
                    }
                };
            }
            startListening() {
                if (this.recognition) {
                    try {
                        if (this.isListening) {
                            this.recognition.stop();
                        } else {
                            this.recognition.start();
                        }
                    } catch (e) {
                        console.error("Speech toggle error:", e);
                    }
                } else {
                    LakshyaDialog.alert("Voice input is not supported in this browser. Please type your answers, or use the latest Google Chrome, Microsoft Edge or Safari for voice input.", { type: 'info', title: 'Voice Input Unavailable' });
                }
            }
            stopListening() {
                // Releases the microphone when the session ends
                if (this.recognition && this.isListening) {
                    try { this.recognition.abort(); } catch (e) {}
                }
                this.isListening = false;
            }
            speak(text) {
                if (this.synth) {
                    this.synth.cancel();
                }
            }
            processQueue() {
                // Voice playback disabled
            }
        }

        registry.register('chat_window', ChatComponent);
        registry.register('code_editor', EditorComponent);
        registry.register('mcq_viewer', MCQComponent);
        registry.register('voice_engine', VoiceComponent);

        class AssessmentRuntime {
            constructor(eventBus, registry) {
                this.bus = eventBus;
                this.registry = registry;
                this.components = {
                    voice: registry.create('voice_engine', null, eventBus)
                };
                this.currentStep = null;
                this.manifest = null;
                this.setupSubscriptions();
            }
            setupSubscriptions() {
                this.bus.on('SPEECH_CAPTURED', data => {
                    if (data.source) {
                        if (data.finalTranscript) {
                            data.source.currentSpeechFinal += data.finalTranscript;
                        }
                        userInput.value = data.source.currentSpeechFinal + (data.interimTranscript ? data.interimTranscript : '');
                        userInput.style.height = 'auto';
                        userInput.style.height = (userInput.scrollHeight) + 'px';
                    } else {
                        userInput.value = data.transcript;
                        sendMessage();
                    }
                });
                this.bus.on('MCQ_OPTION_SELECTED', data => {
                    sendMessage(data.option);
                });
                this.bus.on('TIMER_TICK', data => {
                    const min = Math.floor(data.remaining / 60);
                    const sec = data.remaining % 60;
                });
                this.bus.on('AUTOSAVE_TRIGGER', () => {
                    performAutosave();
                });
            }
            setManifest(manifest) {
                this.manifest = manifest;
                document.getElementById('timelineContainer').style.display = 'flex';
                scheduler.start();
            }
            applyStep(step) {
                this.currentStep = step;
                stateStore.update('currentStep', step);
                this.transitionUI(step);

                const chatContainer = document.getElementById('chatHistory');
                const mcqContainer = document.getElementById('mcqOptionsContainer');

                if (step.components.includes('chat_window') && !this.components.chat) {
                    this.components.chat = this.registry.create('chat_window', chatContainer, this.bus);
                }
                if (step.components.includes('code_editor') && !this.components.editor) {
                    this.components.editor = this.registry.create('code_editor', document.getElementById('codeEditor'), this.bus);
                }
                if (step.components.includes('mcq_viewer') && !this.components.mcq) {
                    this.components.mcq = this.registry.create('mcq_viewer', mcqContainer, this.bus);
                }
                if ((step.components.includes('voice_engine') || step.voice) && !this.components.voice) {
                    this.components.voice = this.registry.create('voice_engine', null, this.bus);
                }

                if (step.ui === 'mcq' && this.components.mcq && step.question) {
                    document.getElementById('mcqQuestionBody').innerText = step.question.body;
                    document.getElementById('mcqTitle').innerText = `${step.title} • Q${step.current_q} of ${step.total_questions}`;
                    this.components.mcq.renderQuestion(step.question.body, step.question.options);
                }

                if (step.tts && this.components.voice && step.message) {
                    this.components.voice.speak(step.message);
                }
            }
            transitionUI(step) {
                const chatBox = document.getElementById('chatContainer');
                const codingBox = document.getElementById('codingPanel');
                const mcqBox = document.getElementById('mcqPanel');
                const toggleWorkspaceBtn = document.getElementById('toggleWorkspace');

                codingBox.classList.remove('active');
                mcqBox.style.display = 'none';
                chatBox.style.width = '100%';
                toggleWorkspaceBtn.style.display = 'none';

                if (step.ui === 'mcq') {
                    mcqBox.style.display = 'flex';
                    chatBox.style.width = '40%';
                } else if (step.ui === 'editor' || step.phase === 'TECHNICAL' || step.phase === 'TECHNICAL_CODING') {
                    toggleWorkspaceBtn.style.display = 'flex';
                    if (step.ui === 'editor') {
                        codingBox.classList.add('active');
                        if (this.components.editor) {
                            setTimeout(() => this.components.editor.refresh(), 100);
                        }
                    }
                }

                const nodes = ['node-1', 'node-2', 'node-3'];
                nodes.forEach(n => document.getElementById(n).classList.remove('active'));
                if (step.phase === 'APTITUDE') {
                    document.getElementById('node-1').classList.add('active');
                } else if (step.phase === 'TECHNICAL_CODING' || step.phase === 'TECHNICAL') {
                    document.getElementById('node-1').classList.add('completed');
                    document.getElementById('node-2').classList.add('active');
                } else if (step.phase === 'BEHAVIORAL_HR' || step.phase === 'HR') {
                    document.getElementById('node-1').classList.add('completed');
                    document.getElementById('node-2').classList.add('completed');
                    document.getElementById('node-3').classList.add('active');
                }
            }
            speakText(text) {
                if (this.components.voice) {
                    this.components.voice.speak(text);
                }
            }
            startListening() {
                if (this.components.voice) {
                    this.components.voice.startListening();
                }
            }
            getEditorValue() {
                return this.components.editor ? this.components.editor.getValue() : '';
            }
            refreshEditor() {
                if (this.components.editor) this.components.editor.refresh();
            }
        }

        const runtime = new AssessmentRuntime(eventBus, registry);
        const dispatcher = new CommandDispatcher(runtime, eventBus);
        const supervisor = new RuntimeSupervisor(runtime, eventBus);

        supervisor.start();

        let frameCount = 0;
        let lastFPSUpdate = Date.now();
        function calculateFPS() {
            frameCount++;
            const now = Date.now();
            if (now - lastFPSUpdate >= 1000) {
                const fps = Math.round((frameCount * 1000) / (now - lastFPSUpdate));
                stateStore.update('performance', { fps });
                frameCount = 0;
                lastFPSUpdate = now;
            }
            requestAnimationFrame(calculateFPS);
        }
        requestAnimationFrame(calculateFPS);

        function toggleDiagnostics() {
            const panel = document.getElementById('diagnosticsPanel');
            panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
            if (panel.style.display === 'flex') {
                updateDiagnosticsData();
            }
        }

        function updateDiagnosticsData() {
            document.getElementById('diagSessionId').innerText = currentSessionId || 'None';
            document.getElementById('diagStep').innerText = runtime.currentStep ? runtime.currentStep.ui : 'None';
            document.getElementById('diagPhase').innerText = runtime.currentStep ? runtime.currentStep.phase : 'None';

            const compsList = document.getElementById('diagComponents');
            compsList.innerHTML = '';
            Object.keys(runtime.components).forEach(key => {
                const li = document.createElement('li');
                li.innerText = `${key} (v${stateStore.state.version})`;
                compsList.appendChild(li);
            });

            document.getElementById('diagTimer').innerText = `${stateStore.state.timerRemaining}s (FPS: ${stateStore.state.performance.fps})`;
            document.getElementById('diagEventsCount').innerText = `${stateStore.state.telemetry.events.length} (Latency: ${stateStore.state.performance.backendLatency}ms)`;

            const hasSpeech = ('SpeechRecognition' in window) || ('webkitSpeechRecognition' in window);
            document.getElementById('diagSpeechAPI').innerText = hasSpeech ? 'Available' : 'Unsupported';
            document.getElementById('diagSpeaking').innerText = (runtime.components.voice && runtime.components.voice.isSpeaking) ? 'Yes' : 'No';
        }

        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key && e.key.toUpperCase() === 'D') {
                e.preventDefault();
                toggleDiagnostics();
            }
        });

        async function performAutosave() {
            if (!currentSessionId) return;
            const stateData = stateStore.state;
            if (runtime.components.editor) {
                stateData.editor.code = runtime.components.editor.getValue();
                stateData.editor.language = document.getElementById('langSelector').value;
            }

            const requestPayload = {
                url: 'mock_ai_handler.php',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: {
                    action: 'autosave',
                    session_id: currentSessionId,
                    checkpoint: stateData
                }
            };

            if (!navigator.onLine) {
                offlineQueue.enqueue(requestPayload);
                return;
            }

            try {
                await fetch(requestPayload.url, {
                    method: 'POST',
                    headers: requestPayload.headers,
                    body: JSON.stringify(requestPayload.body)
                });
                stateStore.update('performance', { autosaveSuccess: 100 });
            } catch (e) {
                console.warn("Autosave sync failed, queueing:", e);
                offlineQueue.enqueue(requestPayload);
                stateStore.update('performance', { autosaveSuccess: 0 });
            }
        }

        let webcamStream = null;
        let screenStream = null;
        let screenVideoEl = null;
        let mediaPipeDetector = null;
        let isMediaPipeLoading = false;
        let proctorInterval = null;
        let isSessionActive = false;


        const calibrationData = {
            center: null,
            left: null,
            right: null
        };

        const proctorStats = {
            totalFrames: 0,
            validFaceFrames: 0,
            consecutiveNoFace: 0,
            consecutiveMultiFace: 0,
            consecutiveGazeDev: 0
        };
        function resetProctorStats() {
            proctorStats.totalFrames = 0;
            proctorStats.validFaceFrames = 0;
            proctorStats.consecutiveNoFace = 0;
            proctorStats.consecutiveMultiFace = 0;
            proctorStats.consecutiveGazeDev = 0;
        }

        let warningCount = 0;
        let lastNoFaceWarningTime = 0;
        let lastMultiFaceWarningTime = 0;
        let lastGazeWarningTime = 0;
        let cameraSetupTimer = null;
        let nativeFaceDetector = null;
        try {
            nativeFaceDetector = ('FaceDetector' in window) ? new window.FaceDetector({ fastMode: true, maxDetectedFaces: 3 }) : null;
        } catch (e) { nativeFaceDetector = null; }
        let backendInitPromise = null;
        // True when the student resumed an existing session (proctoring is set up again, but no new backend session)
        let isResumingSession = false;
        // Gaze threshold (fraction of frame width) — widened after calibration if the student's natural range is larger
        let gazeDeviationThreshold = 0.32;
        let isCalibrating = false;
        let calibrationRunId = 0;
        const sleep = (ms) => new Promise(r => setTimeout(r, ms));

        function startInterviewWithCustomRole() {
            const concepts = document.getElementById('customConcepts').value.trim();
            const difficulty = document.getElementById('customDifficulty').value;
            if (!concepts) {
                LakshyaDialog.alert('Please specify at least one concept/topic to begin the session.', { type: 'warning', title: 'Topic Required' });
                return;
            }

            p_role = concepts;
            p_difficulty = difficulty;

            backendInitPromise = initiateBackendSession(concepts, difficulty);

            document.getElementById('roleSelection').style.display = 'none';
            document.getElementById('introOverlay').style.display = 'flex';
            document.getElementById('stepView1').style.display = 'block';
            document.getElementById('stepView2').classList.add('hidden');
            document.getElementById('stepView3').classList.add('hidden');
            const introTitle = document.getElementById('introTargetTitle');
            if (introTitle) introTitle.innerText = `${concepts} (${difficulty}) • ${COMPANY_NAME}`;

            initCameraSetup();
        }

        async function initCameraSetup() {
            const statusEl = document.getElementById('setupCheckStatus');
            const btnEl = document.getElementById('btnGrantCamera');
            if (btnEl) btnEl.style.display = 'none';
            if (cameraSetupTimer) { clearTimeout(cameraSetupTimer); cameraSetupTimer = null; }
            goToStep(1);

            try {
                if (statusEl) {
                    statusEl.style.color = '#d97706';
                    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initializing camera hardware…';
                }

                if (webcamStream) {
                    try { webcamStream.getTracks().forEach(t => { t.onended = null; t.stop(); }); } catch(e){}
                    webcamStream = null;
                }

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    if (window.isSecureContext === false) {
                        throw new Error("INSECURE_CONTEXT");
                    }
                    throw new Error("MEDIA_NOT_SUPPORTED");
                }

                try {
                    webcamStream = await navigator.mediaDevices.getUserMedia({
                        video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                        audio: false
                    });
                } catch (firstErr) {
                    // A denied permission will fail again — only retry for constraint problems
                    if (firstErr && (firstErr.name === 'NotAllowedError' || firstErr.name === 'PermissionDeniedError')) throw firstErr;
                    console.warn("Primary camera constraints failed, attempting fallback...", firstErr);
                    // Fallback to basic video stream if high-res or facingMode fails
                    webcamStream = await navigator.mediaDevices.getUserMedia({
                        video: true,
                        audio: false
                    });
                }

                const videoTrack = webcamStream.getVideoTracks()[0];
                if (!videoTrack) throw new Error("No video track found.");

                videoTrack.onended = () => {
                    if (isSessionActive) {
                        const statusLabelEl = document.getElementById('proctorWidgetLabel');
                        if (statusLabelEl) statusLabelEl.innerHTML = `<span style="color:#ef4444;"><i class="fas fa-video-slash"></i> Camera Disconnected</span>`;
                        logProctoringEvent('CAMERA_LOST', 0, 1.0, 'HIGH', { reason: 'Camera track ended during session' });
                    } else {
                        handleCameraStreamLost();
                    }
                };
                // macOS mutes the track for a moment while the FaceTime camera warms up or switches
                // (Continuity Camera, video effects). That is not a lost camera — only 'ended' is fatal.
                videoTrack.onmute = () => console.warn("[Lakshya AI] Camera track temporarily muted.");

                const previewVideo = document.getElementById('setupWebcamPreview');
                if (previewVideo) {
                    previewVideo.srcObject = webcamStream;
                    previewVideo.onloadedmetadata = () => previewVideo.play().catch(() => {});
                    previewVideo.play().catch(() => {});
                }

                initMediaPipeDetector();

                let countdown = 3;
                let waitedForFrames = 0;
                const updateCountdown = () => {
                    const track = webcamStream ? webcamStream.getVideoTracks()[0] : null;
                    if (!track || track.readyState !== 'live') {
                        handleCameraStreamLost();
                        return;
                    }
                    if (previewVideo && previewVideo.paused) previewVideo.play().catch(() => {});

                    // Don't start the countdown until the preview is actually showing a picture
                    if (!previewVideo || previewVideo.videoWidth === 0 || track.muted) {
                        waitedForFrames++;
                        if (statusEl) {
                            statusEl.style.color = '#f59e0b';
                            statusEl.innerHTML = waitedForFrames < 10
                                ? '<i class="fas fa-spinner fa-spin"></i> Waiting for camera picture…'
                                : '<i class="fas fa-exclamation-triangle"></i> Camera opened but no picture is coming through.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Close other apps using the camera (FaceTime, Zoom, Teams). On a Mac, check System Settings → Privacy &amp; Security → Camera and make sure your browser is allowed.</span>';
                        }
                        if (waitedForFrames === 10 && btnEl) {
                            btnEl.textContent = '↻ Retry Camera Setup';
                            btnEl.style.display = 'inline-flex';
                            btnEl.disabled = false;
                        }
                        cameraSetupTimer = setTimeout(updateCountdown, 500);
                        return;
                    }
                    if (btnEl) btnEl.style.display = 'none';

                    if (countdown > 0) {
                        if (statusEl) {
                            statusEl.style.color = '#10b981';
                            statusEl.innerHTML = `✓ Camera Active! Position yourself comfortably.<br><span style="color:#f59e0b; font-size: 0.95rem; font-weight: 800;">Calibration starting in ${countdown} second${countdown > 1 ? 's' : ''}…</span>`;
                        }
                        countdown--;
                        cameraSetupTimer = setTimeout(updateCountdown, 1000);
                    } else {
                        cameraSetupTimer = null;
                        goToStep(2);
                        startCalibrationFlow();
                    }
                };
                updateCountdown();

            } catch (err) {
                console.error("Camera Error:", err);
                handleCameraStreamLost(err);
            }
        }

        function handleCameraStreamLost(err = null) {
            if (cameraSetupTimer) { clearTimeout(cameraSetupTimer); cameraSetupTimer = null; }
            // Abort any calibration in progress and send the student back to the camera step
            calibrationRunId++;
            isCalibrating = false;
            goToStep(1);
            const statusEl = document.getElementById('setupCheckStatus');
            const btnEl = document.getElementById('btnGrantCamera');

            let errMsg = '❌ Camera stream lost or permission denied.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Please enable webcam access in your browser and click Retry below.</span>';

            if (err) {
                if (err.message === 'INSECURE_CONTEXT') {
                    errMsg = '❌ Camera blocked: Insecure Context.<br><span style="font-size:0.8rem; font-weight:600; color:#ef4444;">Browsers require HTTPS or localhost for camera access. Please use https:// or access via localhost.</span>';
                } else if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                    errMsg = '❌ Camera permission was denied.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Click the 🔒 icon in your browser address bar, allow Camera access, and click Retry. On a Mac, also enable your browser under System Settings → Privacy &amp; Security → Camera, then quit and reopen the browser.</span>';
                } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                    errMsg = '❌ No camera device detected.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Please plug in a webcam and click Retry below.</span>';
                } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                    errMsg = '❌ Camera is currently in use.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Another app (FaceTime, Zoom, Teams, or another tab) is using the webcam. Please close it and click Retry.</span>';
                } else if (err.name === 'SecurityError') {
                    errMsg = '❌ Camera access restricted by security policy.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Permissions policy or browser configuration is blocking camera access.</span>';
                } else if (err.message === 'MEDIA_NOT_SUPPORTED') {
                    errMsg = '❌ This browser does not support camera access.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Please use the latest Chrome, Edge, Firefox or Safari.</span>';
                }
            }

            if (statusEl) {
                statusEl.style.color = '#ef4444';
                statusEl.innerHTML = errMsg;
            }
            if (btnEl) {
                btnEl.textContent = '↻ Retry Camera Setup';
                btnEl.style.display = 'inline-flex';
                btnEl.disabled = false;
            }
        }

        function goToStep(stepNumber) {
            const v1 = document.getElementById('stepView1');
            const v2 = document.getElementById('stepView2');
            const v3 = document.getElementById('stepView3');
            if (v1) { v1.style.display = stepNumber === 1 ? 'block' : 'none'; v1.classList.toggle('hidden', stepNumber !== 1); }
            if (v2) { v2.style.display = stepNumber === 2 ? 'block' : 'none'; v2.classList.toggle('hidden', stepNumber !== 2); }
            if (v3) { v3.style.display = stepNumber === 3 ? 'block' : 'none'; v3.classList.toggle('hidden', stepNumber !== 3); }

            const t1 = document.getElementById('stepTab1');
            const t2 = document.getElementById('stepTab2');
            const t3 = document.getElementById('stepTab3');

            if (t1) t1.style.color = stepNumber === 1 ? 'var(--primary)' : '#10b981';
            if (t2) t2.style.color = stepNumber === 2 ? 'var(--primary)' : (stepNumber > 2 ? '#10b981' : '#64748b');
            if (t3) t3.style.color = stepNumber === 3 ? 'var(--primary)' : '#64748b';
        }

        // Gives the AI detector a few seconds to finish loading before calibration relies on it
        async function waitForFaceDetector(timeoutMs) {
            const start = Date.now();
            while (!mediaPipeDetector && isMediaPipeLoading && Date.now() - start < timeoutMs) {
                await sleep(200);
            }
        }

        // Samples the live camera for durationMs and reports how often a single face was seen
        async function sampleCalibrationPose(durationMs, onTick) {
            const videoEl = document.getElementById('calibWebcamPreview');
            const canvasEl = document.getElementById('proctorAnalysisCanvas');
            let frames = 0, faceFrames = 0, multiFrames = 0, source = 'none';
            const centers = [];
            const start = Date.now();

            while (Date.now() - start < durationMs) {
                await sleep(250);
                if (onTick) onTick(Math.min(durationMs, Date.now() - start));
                if (!videoEl || videoEl.readyState < 2 || videoEl.videoWidth === 0) continue;
                let result;
                try {
                    result = await detectFaces(videoEl, canvasEl);
                } catch (e) {
                    console.warn("[Lakshya AI] Calibration frame failed:", e);
                    continue;
                }
                frames++;
                source = result.source;
                if (result.count === 1) {
                    faceFrames++;
                    if (result.centerX !== null) centers.push(result.centerX);
                } else if (result.count > 1) {
                    multiFrames++;
                }
            }

            return {
                timestamp: Date.now(),
                frames: frames,
                confidence: frames > 0 ? Math.round((faceFrames / frames) * 100) / 100 : 0,
                multi_face_frames: multiFrames,
                face_center_x: centers.length ? centers.reduce((a, b) => a + b, 0) / centers.length : null,
                detector: source
            };
        }

        // Runs one pose, retrying until a face is visible. Returns the pose data, or null if the student must retry.
        async function calibratePose(label, prompt, durationMs, runId) {
            const textEl = document.getElementById('calibPromptText');
            const progEl = document.getElementById('calibProgressText');
            const maxAttempts = 3;

            for (let attempt = 1; attempt <= maxAttempts; attempt++) {
                if (runId !== calibrationRunId) return null;
                textEl.textContent = prompt;
                progEl.style.color = 'var(--accent)';
                const pose = await sampleCalibrationPose(durationMs, (elapsed) => {
                    progEl.textContent = `Calibrating ${label} Baseline (${Math.ceil(elapsed / 1000)}/${Math.round(durationMs / 1000)}s)`;
                });
                if (runId !== calibrationRunId) return null;

                if (pose.frames === 0) {
                    progEl.style.color = '#ef4444';
                    progEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> No camera picture received. Retrying…';
                } else if (pose.multi_face_frames > pose.frames / 2) {
                    progEl.style.color = '#ef4444';
                    progEl.innerHTML = '<i class="fas fa-users-slash"></i> More than one face detected. Only you should be in front of the camera.';
                } else if (pose.confidence >= 0.4) {
                    progEl.style.color = '#10b981';
                    progEl.innerHTML = `<i class="fas fa-check-circle"></i> ${label} Baseline Saved!`;
                    await sleep(700);
                    return pose;
                } else {
                    progEl.style.color = '#ef4444';
                    progEl.innerHTML = '<i class="fas fa-user-slash"></i> We can\'t see your face clearly.<br><span style="font-size:0.8rem; color:#94a3b8;">Sit facing the screen, keep your whole face inside the preview and make sure the room is well lit (avoid a bright window behind you).</span>';
                }
                await sleep(2500);
            }
            return null;
        }

        async function startCalibrationFlow() {
            if (isCalibrating) return;
            isCalibrating = true;
            const runId = ++calibrationRunId;

            const textEl = document.getElementById('calibPromptText');
            const progEl = document.getElementById('calibProgressText');
            const retryBtn = document.getElementById('btnRetryCalib');
            const calibVideo = document.getElementById('calibWebcamPreview');
            if (retryBtn) retryBtn.classList.add('hidden');

            // Keep the face preview visible during calibration (the step-1 preview is hidden now)
            if (calibVideo && webcamStream && calibVideo.srcObject !== webcamStream) {
                calibVideo.srcObject = webcamStream;
            }
            if (calibVideo) calibVideo.play().catch(() => {});

            textEl.textContent = 'Get ready! Sit straight and face the screen.';
            progEl.style.color = 'var(--accent)';
            if (!mediaPipeDetector && isMediaPipeLoading) {
                progEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading AI face detector…';
                await waitForFaceDetector(10000);
            }
            for (let s = 2; s >= 1; s--) {
                if (runId !== calibrationRunId) return;
                progEl.textContent = `Calibration Starting in ${s} second${s > 1 ? 's' : ''}…`;
                await sleep(1000);
            }

            // Step A: Center Calibration (must actually see a face)
            const center = await calibratePose('Center', 'Look directly at the center target below.', 3000, runId);
            if (runId !== calibrationRunId) return;
            if (!center) {
                isCalibrating = false;
                textEl.textContent = 'Calibration could not detect your face.';
                if (retryBtn) retryBtn.classList.remove('hidden');
                return;
            }
            calibrationData.center = center;

            // Step B / C: Side poses only widen the gaze tolerance, so a weak result there is not fatal
            textEl.textContent = 'Look slightly to your LEFT for 2 seconds.';
            progEl.style.color = 'var(--accent)';
            progEl.textContent = 'Calibrating Left Baseline…';
            calibrationData.left = await sampleCalibrationPose(2000);
            if (runId !== calibrationRunId) return;

            textEl.textContent = 'Look slightly to your RIGHT for 2 seconds.';
            progEl.textContent = 'Calibrating Right Baseline…';
            calibrationData.right = await sampleCalibrationPose(2000);
            if (runId !== calibrationRunId) return;

            const baseX = center.face_center_x;
            const sideSpread = [calibrationData.left.face_center_x, calibrationData.right.face_center_x]
                .filter(x => x !== null && baseX !== null)
                .map(x => Math.abs(x - baseX));
            if (sideSpread.length) {
                gazeDeviationThreshold = Math.min(0.42, Math.max(0.32, Math.max(...sideSpread) + 0.08));
            }

            progEl.style.color = '#10b981';
            progEl.textContent = '✓ Calibration Completed!';
            await sleep(800);
            isCalibrating = false;
            if (runId !== calibrationRunId) return;

            // Save calibration to backend (wait for the session id so it isn't stored against session 0)
            try {
                if (backendInitPromise) await backendInitPromise;
                await fetch('mock_ai_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({ action: 'save_calibration', session_id: currentSessionId || 0, calibration: calibrationData })
                });
            } catch (e) {}

            goToStep(3);
        }

        // Safari < 16.4 only has the webkit-prefixed Fullscreen API
        function getFullscreenElement() {
            return document.fullscreenElement || document.webkitFullscreenElement || null;
        }

        // The fullscreen transition on macOS can briefly blur the window; don't count that as a violation
        let fullscreenGraceUntil = 0;

        function requestFullscreenCompat() {
            fullscreenGraceUntil = Date.now() + 1500;
            const el = document.documentElement;
            if (el.requestFullscreen) return el.requestFullscreen();
            if (el.webkitRequestFullscreen) {
                el.webkitRequestFullscreen();
                return Promise.resolve();
            }
            return Promise.reject(new Error('Fullscreen API not supported'));
        }

        async function enterFullscreen() {
            if (getFullscreenElement()) return;
            try {
                await requestFullscreenCompat();
            } catch (e) {
                console.warn("Fullscreen request error:", e);
            }
        }

        // Shown when fullscreen could not be entered automatically (e.g. Safari after the screen-share picker).
        // This is NOT a strike.
        function showFullscreenPrompt() {
            const iconEl = document.getElementById('warningIcon');
            const titleEl = document.getElementById('warningTitle');
            const msgEl = document.getElementById('warningMessage');
            const btnEl = document.getElementById('warningBtn');
            if (iconEl) { iconEl.className = 'fas fa-expand'; iconEl.style.color = 'var(--accent)'; }
            if (titleEl) titleEl.textContent = 'Full Screen Required';
            if (msgEl) msgEl.innerHTML = 'Click the button below to enter full screen and begin your interview. This is not counted as a warning.';
            if (btnEl) {
                btnEl.textContent = 'ENTER FULL SCREEN';
                btnEl.onclick = resumeFullscreen;
                btnEl.style.display = 'inline-flex';
                btnEl.style.background = 'var(--primary)';
            }
            const overlay = document.getElementById('warningOverlay');
            if (overlay) overlay.classList.remove('hidden');
        }

        async function initScreenShareSetup() {
            const statusEl = document.getElementById('screenCheckStatus');
            const btnEl = document.getElementById('btnGrantScreen');
            if (btnEl) btnEl.disabled = true;

            // Note: fullscreen is NOT requested before the picker. Requesting it first consumes the click's
            // user activation, and Safari then rejects getDisplayMedia().

            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
                    throw new Error('DISPLAY_MEDIA_NOT_SUPPORTED');
                }
                if (statusEl) {
                    statusEl.style.color = '#d97706';
                    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Requesting entire screen stream…';
                }

                screenStream = await navigator.mediaDevices.getDisplayMedia({
                    video: { displaySurface: 'monitor', cursor: 'always' },
                    audio: false
                });

                const screenTrack = screenStream.getVideoTracks()[0];
                const settings = screenTrack && screenTrack.getSettings ? screenTrack.getSettings() : {};

                if (settings.displaySurface && settings.displaySurface !== 'monitor') {
                    screenStream.getTracks().forEach(t => t.stop());
                    screenStream = null;
                    if (btnEl) btnEl.disabled = false;
                    if (statusEl) {
                        statusEl.style.color = '#ef4444';
                        statusEl.innerHTML = '<i class="fas fa-ban"></i> <strong>Entire Screen Required!</strong><br><span style="font-size:0.85rem; color:#ef4444;">You shared a single window or tab. You must choose <strong>"Entire Screen"</strong> to proceed.</span>';
                    }
                    return;
                }

                if (screenTrack) {
                    screenTrack.onended = () => {
                        triggerWarning('Entire screen sharing stream was stopped.', 'SCREEN_SHARE_STOPPED');
                    };
                }

                if (!screenVideoEl) {
                    screenVideoEl = document.createElement('video');
                    screenVideoEl.autoplay = true;
                    screenVideoEl.muted = true;
                    screenVideoEl.playsInline = true;
                    screenVideoEl.style.display = 'none';
                    document.body.appendChild(screenVideoEl);
                }
                screenVideoEl.srcObject = screenStream;
                screenVideoEl.play().catch(() => {});

                if (statusEl) {
                    statusEl.style.color = '#10b981';
                    statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Screen Share Active! Launching session…';
                }

                // May fail if the user activation expired while the picker was open (common in Safari);
                // in that case an in-page "Enter Full Screen" prompt is shown below.
                await enterFullscreen();

                setTimeout(async () => {
                    document.getElementById('introOverlay').style.display = 'none';

                    const setupPreview = document.getElementById('setupWebcamPreview');
                    if (setupPreview) setupPreview.srcObject = null;
                    const calibPreview = document.getElementById('calibWebcamPreview');
                    if (calibPreview) calibPreview.srcObject = null;

                    const proctorWidget = document.getElementById('proctorWidget');
                    if (proctorWidget) proctorWidget.style.display = 'block';

                    const proctorVideo = document.getElementById('proctorWebcamVideo');
                    if (proctorVideo && webcamStream) {
                        proctorVideo.srcObject = webcamStream;
                        await proctorVideo.play().catch(() => {});
                    }

                    const badgeNav = document.getElementById('warningBadgeNav');
                    if (badgeNav) badgeNav.style.display = 'inline-flex';

                    resetProctorStats();
                    lastStrikeTime = 0;
                    if (!isResumingSession) warningCount = 0;
                    isSessionActive = true;
                    isProctoringActive = true;

                    if (!getFullscreenElement()) showFullscreenPrompt();

                    if (proctorInterval) clearInterval(proctorInterval);
                    proctorInterval = setInterval(runProctoringCheckFrame, 1200);

                    if (isResumingSession) {
                        // Session state was already restored when the student chose "Resume"
                        sessionStatus.style.display = 'flex';
                    } else {
                        finalizeInterviewStart();
                    }
                }, 800);

            } catch (err) {
                console.error("Screen Share Error:", err);
                if (statusEl) {
                    statusEl.style.color = '#ef4444';
                    if (err && err.message === 'DISPLAY_MEDIA_NOT_SUPPORTED') {
                        statusEl.innerHTML = '❌ This browser does not support screen sharing.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Please use the latest Chrome, Edge, Firefox or Safari on a computer.</span>';
                    } else if (err && err.name === 'NotAllowedError' && /system/i.test(err.message || '')) {
                        // Chrome reports "Permission denied by system" when macOS Screen Recording access is off
                        statusEl.innerHTML = '❌ Your computer blocked screen sharing.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">On a Mac: open System Settings → Privacy &amp; Security → Screen &amp; System Audio Recording, enable your browser, then fully quit and reopen the browser.</span>';
                    } else {
                        statusEl.innerHTML = '❌ Screen share permission required to proceed.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Please select your Entire Screen to continue. If the picker never appeared on a Mac, check System Settings → Privacy &amp; Security → Screen &amp; System Audio Recording.</span>';
                    }
                }
                if (btnEl) {
                    btnEl.textContent = '↻ Retry Screen Share';
                    btnEl.disabled = false;
                }
            }
        }

        // Pinned so the JS bundle and the WASM files always come from the same release
        const MEDIAPIPE_VERSION = '0.10.14';

        async function initMediaPipeDetector(delegates = ['GPU', 'CPU']) {
            if (mediaPipeDetector || isMediaPipeLoading) return;
            isMediaPipeLoading = true;
            try {
                const vision = await import(`https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${MEDIAPIPE_VERSION}/vision_bundle.mjs`);
                const { FaceDetector, FilesetResolver } = vision;
                const filesetResolver = await FilesetResolver.forVisionTasks(`https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${MEDIAPIPE_VERSION}/wasm`);

                // The GPU delegate fails on some Macs / Safari builds, so fall back to CPU
                for (const delegate of delegates) {
                    try {
                        mediaPipeDetector = await FaceDetector.createFromOptions(filesetResolver, {
                            baseOptions: {
                                modelAssetPath: "https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite",
                                delegate: delegate
                            },
                            runningMode: "IMAGE",
                            minDetectionConfidence: 0.5
                        });
                        console.log(`[Lakshya AI] MediaPipe face detector initialized (${delegate}).`);
                        break;
                    } catch (delegateErr) {
                        console.warn(`[Lakshya AI] MediaPipe ${delegate} delegate failed:`, delegateErr);
                    }
                }
            } catch (err) {
                console.warn("[Lakshya AI] MediaPipe fallback:", err);
            } finally {
                isMediaPipeLoading = false;
            }
        }

        /**
         * Counts faces in the current video frame.
         * Returns { count, centerX (0..1 of frame width, or null), source }.
         */
        async function detectFaces(videoEl, canvasEl) {
            // 1. Primary AI Vision Engine: MediaPipe BlazeFace (Google AI)
            if (mediaPipeDetector) {
                try {
                    const mpResult = mediaPipeDetector.detect(videoEl);
                    const detections = (mpResult && mpResult.detections) || [];
                    const box = detections.length === 1 ? detections[0].boundingBox : null;
                    return {
                        count: detections.length,
                        centerX: box && videoEl.videoWidth ? (box.originX + box.width / 2) / videoEl.videoWidth : null,
                        source: 'mediapipe'
                    };
                } catch (e) {
                    // GPU context can be lost at runtime (common on Safari) — rebuild on CPU
                    console.warn("[Lakshya AI] MediaPipe detect failed, switching to CPU:", e);
                    try { mediaPipeDetector.close(); } catch (closeErr) {}
                    mediaPipeDetector = null;
                    initMediaPipeDetector(['CPU']);
                }
            }

            const ctx = canvasEl.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);

            // 2. Native Browser FaceDetector Fallback
            if (nativeFaceDetector) {
                try {
                    const detected = await nativeFaceDetector.detect(canvasEl);
                    const box = detected.length === 1 ? detected[0].boundingBox : null;
                    return {
                        count: detected.length,
                        centerX: box ? (box.x + box.width / 2) / canvasEl.width : null,
                        source: 'native'
                    };
                } catch (e) {}
            }

            // 3. Heuristic YCbCr skin-pixel fallback (if ML models are offline).
            // It cannot tell one close face from two, so it never reports more than one face.
            const data = ctx.getImageData(0, 0, canvasEl.width, canvasEl.height).data;
            let skinPixelCount = 0;
            for (let i = 0; i < data.length; i += 16) {
                const r = data[i], g = data[i+1], b = data[i+2];
                const y  = 0.299 * r + 0.587 * g + 0.114 * b;
                const cb = 128 - (0.168736 * r) - (0.331264 * g) + (0.5 * b);
                const cr = 128 + (0.5 * r) - (0.418688 * g) - (0.081312 * b);
                if (y > 30 && cb >= 77 && cb <= 127 && cr >= 133 && cr <= 173) skinPixelCount++;
            }
            const skinRatio = skinPixelCount / (data.length / 16);
            return { count: skinRatio >= 0.025 ? 1 : 0, centerX: null, source: 'heuristic' };
        }

        let isCheckingFrame = false;
        async function runProctoringCheckFrame() {
            if (!isSessionActive || !webcamStream || isCheckingFrame) return;

            const videoEl = document.getElementById('proctorWebcamVideo');
            const canvasEl = document.getElementById('proctorAnalysisCanvas');
            const statusWidgetEl = document.getElementById('proctorWidgetStatus');
            const statusLabelEl = document.getElementById('proctorWidgetLabel');

            if (!videoEl || !canvasEl) return;

            if (videoEl.paused || videoEl.ended || videoEl.videoWidth === 0 || videoEl.videoHeight === 0) {
                videoEl.play().catch(() => {});
                return;
            }

            isCheckingFrame = true;
            let detection;
            try {
                detection = await detectFaces(videoEl, canvasEl);
            } catch (e) {
                console.warn("[Lakshya AI] Proctoring frame check failed:", e);
                return;
            } finally {
                isCheckingFrame = false;
            }
            if (!isSessionActive) return;

            proctorStats.totalFrames++;

            const facesDetected = detection.count;
            // The pixel heuristic is unreliable (lighting, skin tone), so it needs a longer streak before a strike
            const noFaceStrikeFrames = detection.source === 'heuristic' ? 6 : 3;

            // Compare against the student's own calibrated position, not the frame centre,
            // so sitting slightly off-centre (common with laptop cameras) isn't flagged as looking away
            let gazeDeviated = false;
            if (facesDetected === 1 && detection.centerX !== null) {
                const baselineX = (calibrationData.center && calibrationData.center.face_center_x !== null && calibrationData.center.face_center_x !== undefined)
                    ? calibrationData.center.face_center_x
                    : 0.5;
                gazeDeviated = Math.abs(detection.centerX - baselineX) > gazeDeviationThreshold;
            }

            // 4. Evaluate Detection Results & Fire Strike Warnings
            if (facesDetected >= 1) {
                proctorStats.validFaceFrames++;
                proctorStats.consecutiveNoFace = 0;

                if (facesDetected > 1) {
                    proctorStats.consecutiveMultiFace++;
                    if (statusLabelEl) statusLabelEl.innerHTML = `<span style="color:#ef4444;"><i class="fas fa-users-slash"></i> Multi-Face (${facesDetected})</span>`;

                    if (proctorStats.consecutiveMultiFace >= 2 && (Date.now() - lastMultiFaceWarningTime) > 7000) {
                        lastMultiFaceWarningTime = Date.now();
                        triggerWarning(`Multiple faces (${facesDetected}) detected in camera stream. Assessment must be taken alone.`, 'MULTI_FACE');
                    }
                } else {
                    proctorStats.consecutiveMultiFace = 0;

                    if (gazeDeviated) {
                        proctorStats.consecutiveGazeDev++;
                        if (statusLabelEl) statusLabelEl.innerHTML = `<span style="color:#f59e0b;"><i class="fas fa-eye-slash"></i> Looking Away</span>`;
                        if (proctorStats.consecutiveGazeDev >= 3 && (Date.now() - lastGazeWarningTime) > 7000) {
                            lastGazeWarningTime = Date.now();
                            triggerWarning('Gaze deviation detected. Please look directly at your screen.', 'GAZE_DEVIATION');
                        }
                    } else {
                        proctorStats.consecutiveGazeDev = 0;
                        if (statusLabelEl) statusLabelEl.innerHTML = `<span style="color:#10b981;"><i class="fas fa-user-check"></i> Face In Frame</span>`;
                    }
                }

            } else {
                // facesDetected === 0 -> NO FACE!
                proctorStats.consecutiveNoFace++;
                proctorStats.consecutiveMultiFace = 0;
                proctorStats.consecutiveGazeDev = 0;

                if (statusLabelEl) statusLabelEl.innerHTML = `<span style="color:#ef4444;"><i class="fas fa-user-slash"></i> NO FACE DETECTED</span>`;

                if (proctorStats.consecutiveNoFace >= noFaceStrikeFrames && (Date.now() - lastNoFaceWarningTime) > 7000) {
                    lastNoFaceWarningTime = Date.now();
                    triggerWarning('No face detected in camera stream. Candidate must remain visible throughout the interview.', 'NO_FACE');
                }
            }

            const liveScore = Math.min(100, Math.max(0, Math.round((proctorStats.validFaceFrames / proctorStats.totalFrames) * 100)));
            if (statusWidgetEl) {
                statusWidgetEl.textContent = liveScore + '%';
                statusWidgetEl.style.color = liveScore >= 80 ? '#10b981' : (liveScore >= 60 ? '#f59e0b' : '#ef4444');
            }
        }

        // Separate canvas for evidence snapshots: resizing the 320x240 analysis canvas to screen size
        // (5K px on Retina Macs) made every later face-check frame huge and slow
        let snapshotCanvas = null;

        async function logProctoringEvent(eventType, duration = 0, confidence = 1.0, severity = 'LOW', metadata = {}) {
            try {
                let snapshot = null;
                const videoEl = document.getElementById('proctorWebcamVideo');
                const canvasEl = snapshotCanvas || (snapshotCanvas = document.createElement('canvas'));
                const isTabOrScreenEvent = ['TAB_SWITCH', 'WINDOW_BLUR', 'FULLSCREEN_EXIT', 'SCREEN_SHARE_STOPPED'].includes(eventType);

                if (isTabOrScreenEvent && screenVideoEl && screenVideoEl.videoWidth > 0) {
                    const screenScale = Math.min(1, 1600 / (screenVideoEl.videoWidth || 1280));
                    const sw = Math.round((screenVideoEl.videoWidth || 1280) * screenScale);
                    const sh = Math.round((screenVideoEl.videoHeight || 720) * screenScale);
                    canvasEl.width = sw;
                    canvasEl.height = sh;
                    const ctx = canvasEl.getContext('2d');
                    ctx.drawImage(screenVideoEl, 0, 0, sw, sh);

                    if (videoEl && videoEl.videoWidth > 0) {
                        const pipW = Math.min(320, Math.floor(sw * 0.25));
                        const pipH = Math.floor(pipW * ((videoEl.videoHeight || 480) / (videoEl.videoWidth || 640)));
                        const pipX = sw - pipW - 16;
                        const pipY = sh - pipH - 16;
                        ctx.fillStyle = '#000000';
                        ctx.fillRect(pipX - 3, pipY - 3, pipW + 6, pipH + 6);
                        ctx.drawImage(videoEl, pipX, pipY, pipW, pipH);

                        ctx.fillStyle = '#ef4444';
                        ctx.font = 'bold 13px sans-serif';
                        ctx.fillText('🔴 CAM + SCREEN EVIDENCE', pipX + 8, pipY + 20);
                    }
                    snapshot = canvasEl.toDataURL('image/jpeg', 0.82);
                } else if (videoEl && videoEl.videoWidth > 0) {
                    canvasEl.width = videoEl.videoWidth || 640;
                    canvasEl.height = videoEl.videoHeight || 480;
                    const ctx = canvasEl.getContext('2d');
                    ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
                    snapshot = canvasEl.toDataURL('image/jpeg', 0.85);
                }

                await fetch('mock_ai_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({
                        action: 'log_proctoring_event',
                        session_id: currentSessionId || 0,
                        event_type: eventType,
                        duration: duration,
                        confidence: confidence,
                        severity: severity,
                        metadata: metadata,
                        snapshot: snapshot
                    })
                });
            } catch (e) {}
        }

        // Window & Tab switching detection (ignored briefly after we request fullscreen — the macOS transition blurs the window)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden' && isSessionActive && Date.now() > fullscreenGraceUntil) {
                setTimeout(() => {
                    triggerWarning('Tab or application switch detected. Candidate navigated away from interview screen.', 'TAB_SWITCH');
                }, 120);
            }
        });

        window.addEventListener('blur', () => {
            if (isSessionActive && Date.now() > fullscreenGraceUntil) {
                setTimeout(() => {
                    triggerWarning('Window focus lost. Candidate clicked outside interview window.', 'WINDOW_BLUR');
                }, 120);
            }
        });

        let lastStrikeTime = 0;

        function triggerWarning(reason, eventType = 'SECURITY_VIOLATION') {
            if (!isSessionActive) return;
            // One action (e.g. Cmd+Tab) fires blur, visibilitychange and fullscreenchange together — count it once
            if (Date.now() - lastStrikeTime < 3000) return;
            lastStrikeTime = Date.now();
            warningCount++;
            logProctoringEvent(eventType, 0, 1.0, 'HIGH', { strike_count: warningCount, reason: reason });

            const badgeNavText = document.getElementById('warningCountNavText');
            const badgeNav = document.getElementById('warningBadgeNav');
            if (badgeNavText) {
                badgeNavText.textContent = warningCount;
                badgeNavText.style.color = warningCount === 1 ? '#f59e0b' : '#ef4444';
            }
            if (badgeNav) {
                badgeNav.style.background = warningCount === 1 ? 'rgba(245, 158, 11, 0.25)' : 'rgba(239, 68, 68, 0.35)';
            }

            const iconEl = document.getElementById('warningIcon');
            const titleEl = document.getElementById('warningTitle');
            const msgEl = document.getElementById('warningMessage');
            const btnEl = document.getElementById('warningBtn');
            const overlay = document.getElementById('warningOverlay');
            // The fullscreen prompt may have swapped the icon, so always restore it
            if (iconEl) iconEl.className = 'fas fa-exclamation-triangle';

            if (warningCount === 1) {
                if (iconEl) iconEl.style.color = '#f59e0b';
                if (titleEl) titleEl.textContent = 'SECURITY VIOLATION — WARNING 1 OF 3';
                if (msgEl) msgEl.innerHTML = `<strong>${escapeHtml(reason)}</strong><br><br>A <strong>-5% score penalty</strong> will be applied.<br>You have <strong>2 chances remaining</strong> before your interview is automatically submitted.`;
                if (btnEl) {
                    btnEl.textContent = 'RESUME ASSESSMENT';
                    btnEl.onclick = resumeFullscreen;
                    btnEl.style.display = 'inline-flex';
                    btnEl.style.background = 'var(--primary)';
                }
                if (overlay) overlay.classList.remove('hidden');
            } else if (warningCount === 2) {
                if (iconEl) iconEl.style.color = '#f97316';
                if (titleEl) titleEl.textContent = 'CRITICAL SECURITY WARNING — WARNING 2 OF 3';
                if (msgEl) msgEl.innerHTML = `<strong>${escapeHtml(reason)}</strong><br><br><span style="color: #f97316; font-weight: 700;">FINAL CHANCE REMAINING! (-10% Total Penalty)</span><br>One more violation will instantly auto-submit your interview.`;
                if (btnEl) {
                    btnEl.textContent = 'RESUME ASSESSMENT';
                    btnEl.onclick = resumeFullscreen;
                    btnEl.style.display = 'inline-flex';
                    btnEl.style.background = 'var(--primary)';
                }
                if (overlay) overlay.classList.remove('hidden');
            } else {
                if (iconEl) iconEl.style.color = '#ef4444';
                if (titleEl) titleEl.textContent = 'MAXIMUM VIOLATIONS EXCEEDED — COUNT 3 OF 3';
                if (msgEl) msgEl.innerHTML = `<strong>${escapeHtml(reason)}</strong><br><br><span style="color: #ef4444; font-weight: 700;">Maximum allowed integrity violations (3/3) reached (-15% Penalty Applied). Your assessment is being automatically submitted now.</span>`;
                if (btnEl) {
                    btnEl.innerHTML = '<i class="fas fa-arrow-left"></i> RETURN TO DASHBOARD';
                    btnEl.onclick = () => { window.location.href = 'dashboard.php'; };
                    btnEl.style.display = 'inline-flex';
                    btnEl.style.background = '#ef4444';
                }
                if (overlay) overlay.classList.remove('hidden');

                setTimeout(() => {
                    endSessionManual(true);
                }, 1500);
            }
        }

        function escapeHtml(text) {
            if (typeof text !== 'string') return text;
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        let p_role = "";
        let p_difficulty = "Medium";
        async function runLoadingSequence(concepts, difficulty) {
            p_role = concepts;
            p_difficulty = difficulty;
            const steps = ['step-1', 'step-2', 'step-3', 'step-4'];

            for (let i = 0; i < steps.length; i++) {
                const stepEl = document.getElementById(steps[i]);
                if (stepEl) stepEl.classList.add('active');
                await new Promise(r => setTimeout(r, 1000));
                if (i === 1) {
                    initiateBackendSession(concepts, difficulty);
                }
                if (stepEl) stepEl.classList.replace('active', 'completed');
            }

            document.getElementById('permissionHint').style.display = 'block';
            document.getElementById('finalLaunchBtn').style.display = 'inline-block';
        }

        function renderMath(element) {
            if (typeof renderMathInElement === 'function') {
                renderMathInElement(element, {
                    delimiters: [
                        { left: "$$", right: "$$", display: true },
                        { left: "$", right: "$", display: false },
                        { left: "\\(", right: "\\)", display: false },
                        { left: "\\[", right: "\\]", display: true }
                    ],
                    throwOnError: false
                });
            } else {
                setTimeout(() => renderMath(element), 100);
            }
        }

        let backendInitData = null;
        async function initiateBackendSession(concepts, difficulty) {
            try {
                const res = await fetch('mock_ai_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        action: 'start',
                        role: concepts,
                        concept: concepts,
                        difficulty: difficulty,
                        company: COMPANY_NAME,
                        type: ROUND_TYPE
                    })
                });
                const text = await res.text();
                try {
                    backendInitData = JSON.parse(text);
                    if (backendInitData && backendInitData.session_id) {
                        currentSessionId = backendInitData.session_id;
                    }
                } catch (err) {
                    console.error("Failed to parse start session JSON:", text);
                }
            } catch (e) {
                console.error("Backend init failed", e);
            }
        }

        async function executeFinalLaunch() {
            if (!backendInitData) {
                await LakshyaDialog.alert("Connection error. Please try again.", { type: 'error', title: 'Connection Error' });
                window.location.reload();
                return;
            }

            if (!backendInitData.success) {
                await LakshyaDialog.alert('Session initiation failed: ' + (backendInitData.message || 'Unknown error.'), { type: 'error', title: 'Session Not Started' });
                window.location.reload();
                return;
            }

            document.getElementById('premiumLoader').style.opacity = '0';
            setTimeout(() => {
                document.getElementById('premiumLoader').style.display = 'none';
                finalizeInterviewStart();
            }, 500);
        }

        window.addEventListener('DOMContentLoaded', async () => {
            try {
                const res = await fetch('mock_ai_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({ action: 'check_active' })
                });
                const text = await res.text();
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    console.error("Failed to parse check_active JSON:", text);
                }
                if (data && data.success && data.has_active) {
                    const modal = document.getElementById('resumeModal');
                    const roleSpan = document.getElementById('resumeRole');
                    roleSpan.innerText = (data.role || 'a Previous Session') + (data.difficulty ? ' (' + data.difficulty + ')' : '');
                    modal.style.display = 'flex';

                    document.getElementById('btnResumeSession').onclick = () => {
                        modal.style.display = 'none';
                        currentSessionId = data.session_id;
                        document.getElementById('roleSelection').style.display = 'none';
                        sessionStatus.style.display = 'flex';
                        // Proctoring (camera, calibration, screen share, fullscreen) is set up again below;
                        // the session only becomes active once that completes.
                        isResumingSession = true;

                        const restored = stateStore.restore(data.session_id);
                        if (restored) {
                            stateStore.state.voiceEnabled = false;
                            if (!Array.isArray(stateStore.state.chatHistory)) stateStore.state.chatHistory = [];
                            if (stateStore.state.currentStep) {
                                runtime.applyStep(stateStore.state.currentStep);
                            }
                            if (stateStore.state.chatHistory.length > 0) {
                                const container = document.getElementById('chatHistory');
                                container.innerHTML = '';
                                // addBubble() appends to chatHistory, so replay from a copy to avoid doubling it
                                const savedMessages = stateStore.state.chatHistory.slice();
                                stateStore.state.chatHistory = [];
                                savedMessages.forEach(m => {
                                    addMessage(m.role, m.content);
                                });
                            }
                            if (stateStore.state.editor.code && runtime.components.editor) {
                                runtime.components.editor.setValue(stateStore.state.editor.code);
                            }
                        } else if (data.step) {
                            runtime.applyStep(data.step);
                            if (data.history && data.history.length > 0) {
                                data.history.forEach(m => addMessage(m.role, m.content));
                            }
                        } else if (data.history && data.history.length > 0) {
                            data.history.forEach(m => addMessage(m.role, m.content));
                            runtime.speakText("Resuming session. Let's continue.");
                        }

                        if (ROUND_TYPE === "Technical") {
                            toggleWorkspaceBtn.style.display = 'flex';
                        }

                        document.getElementById('introOverlay').style.display = 'flex';
                        goToStep(1);
                        initCameraSetup();
                    };

                    document.getElementById('btnStartFresh').onclick = async () => {
                        modal.style.display = 'none';
                        localStorage.removeItem(`lar_session_${data.session_id}`);
                        try {
                            await fetch('mock_ai_handler.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                                body: JSON.stringify({ action: 'end_session', session_id: data.session_id, company: COMPANY_NAME, type: ROUND_TYPE })
                            });
                        } catch (e) {
                            console.warn("Could not close previous session", e);
                        }
                        document.getElementById('roleSelection').style.display = 'flex';
                    };
                }
            } catch (e) { console.warn("Active session check failed", e); }
        });

        async function finalizeInterviewStart() {
            if (backendInitPromise) {
                await backendInitPromise;
            }
            if (!backendInitData || !backendInitData.success) {
                // Stop proctoring first so the error itself can't cause strikes
                isSessionActive = false;
                isProctoringActive = false;
                if (proctorInterval) clearInterval(proctorInterval);
                const reason = (backendInitData && backendInitData.message) ? backendInitData.message : 'Could not reach the server.';
                await LakshyaDialog.alert('The interview session could not be started: ' + reason + '\n\nThe page will reload so you can try again.', { type: 'error', title: 'Session Not Started' });
                if (webcamStream) webcamStream.getTracks().forEach(t => t.stop());
                if (screenStream) screenStream.getTracks().forEach(t => t.stop());
                window.location.reload();
                return;
            }

            currentSessionId = backendInitData.session_id;
            localStorage.removeItem(`lar_session_${currentSessionId}`);
            stateStore.setState({
                currentStep: null,
                editor: {
                    language: 'python',
                    code: ''
                },
                timerRemaining: 600,
                voiceEnabled: false,
                chatHistory: [],
                telemetry: {
                    schema_version: 2,
                    events: []
                }
            });

            sessionStatus.style.display = 'flex';
            isSessionActive = true;
            isProctoringActive = true;

            if (ROUND_TYPE === "Technical") {
                toggleWorkspaceBtn.style.display = 'flex';
            }

            if (backendInitData.step) {
                runtime.applyStep(backendInitData.step);
            }

            const msg = backendInitData.message;
            if (msg) {
                addMessage('ai', msg);
                runtime.speakText(msg);
            }
        }

        async function startInterview(role) {
        }

        let typingInterval = null;
        const typingPhrases = [
            "Analyzing technical accuracy...",
            "Reviewing communication and clarity...",
            "Evaluating design trade-offs...",
            "Measuring response confidence...",
            "Formulating follow-up challenge..."
        ];

        function startTypingIndicator() {
            const textSpan = typingIndicator.querySelector('span');
            if (textSpan) textSpan.innerText = "Interviewer is thinking...";
            typingIndicator.style.display = 'flex';

            let index = 0;
            if (typingInterval) clearInterval(typingInterval);
            typingInterval = setInterval(() => {
                if (textSpan) textSpan.innerText = typingPhrases[index];
                index = (index + 1) % typingPhrases.length;
            }, 1200);
        }

        function stopTypingIndicator() {
            if (typingInterval) clearInterval(typingInterval);
            typingIndicator.style.display = 'none';
        }

        function updateLiveHeaderStatus(status, latencyMs) {
            const textNode = document.getElementById('liveNetworkText');
            const dotNode = document.querySelector('.status-dot');
            const latencyNode = document.getElementById('liveLatencyText');
            const autosaveNode = document.getElementById('liveAutosaveText');
            if (!textNode || !dotNode) return;

            if (status === 'connected') {
                textNode.innerText = 'Connected';
                dotNode.style.background = '#10b981';
                dotNode.style.animation = 'pulse-green 2s infinite';
                if (latencyMs !== undefined) {
                    latencyNode.innerText = `Latency: ${latencyMs}ms`;
                }
                autosaveNode.innerText = `Autosaved ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
            } else {
                textNode.innerText = 'Offline';
                dotNode.style.background = '#ef4444';
                dotNode.style.animation = 'none';
            }
        }

        let isSending = false;

        async function sendMessage(customMsg = null) {
            if (customMsg && (customMsg instanceof Event || typeof customMsg !== 'string')) {
                customMsg = null;
            }
            const msg = customMsg || userInput.value.trim();
            if (!msg || !currentSessionId) return;

            if (isSending) return;
            isSending = true;
            userInput.disabled = true;
            btnSend.disabled = true;

            if (!customMsg) {
                addMessage('user', msg);
                userInput.value = '';
                userInput.style.height = 'auto';
            }

            startTypingIndicator();

            const requestPayload = {
                url: 'mock_ai_handler.php',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: {
                    action: 'chat',
                    session_id: currentSessionId,
                    message: msg,
                    type: ROUND_TYPE
                }
            };

            if (!navigator.onLine) {
                offlineQueue.enqueue(requestPayload);
                stopTypingIndicator();
                addMessage('system', '⚠️ Offline. Action buffered in queue.');
                updateLiveHeaderStatus('disconnected');
                isSending = false;
                userInput.disabled = false;
                btnSend.disabled = false;
                return;
            }

            const snapshotSessionId = currentSessionId;
            const chatController = new AbortController();
            const chatTimeout = setTimeout(() => chatController.abort(), 290000);

            const startTime = Date.now();
            try {
                const res = await fetch(requestPayload.url, {
                    method: 'POST',
                    signal: chatController.signal,
                    headers: requestPayload.headers,
                    body: JSON.stringify(requestPayload.body)
                });
                clearTimeout(chatTimeout);

                const rawText = await res.text();
                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (parseErr) {
                    console.error('Server returned non-JSON:', rawText.substring(0, 500));
                    stopTypingIndicator();
                    updateLiveHeaderStatus('disconnected');
                    addMessage('system', '⚠️ Server error. Please refresh and try again.');
                    return;
                }

                if (!res.ok && !data.success) {
                    stopTypingIndicator();
                    addMessage('system', '⚠️ ' + (data.message || 'Server error. Please try again.'));
                    return;
                }

                const latency = Date.now() - startTime;
                const times = stateStore.state.performance.apiResponseTimes;
                times.push(latency);
                stateStore.update('performance', { backendLatency: latency, apiResponseTimes: times });

                stopTypingIndicator();
                updateLiveHeaderStatus('connected', latency);

                if (data.success && data.job_id) {
                    const pollInterval = setInterval(async () => {
                        try {
                            const statusRes = await fetch(`ai_job_status.php?job_id=${encodeURIComponent(data.job_id)}`).then(r => r.json());
                            if (statusRes.success && statusRes.status === 'completed') {
                                clearInterval(pollInterval);
                                const result = statusRes.result;
                                addMessage('ai', result.message || result.content);
                                runtime.speakText(result.message || result.content);
                                if (result.is_end) {
                                    lockControls();
                                    addMessage('ai', 'SYSTEM: *Session concluded. Processing analytics...*');
                                    setTimeout(() => {
                                        endSessionManual();
                                    }, 2500);
                                }
                            } else if (statusRes.status === 'failed') {
                                clearInterval(pollInterval);
                                LakshyaDialog.alert("AI generation failed: " + (statusRes.error || 'Unknown error.') + "\nPlease send your answer again.", { type: 'error', title: 'AI Error' });
                            }
                        } catch (e) {
                            console.error("Polling error:", e);
                        }
                    }, 2000);
                } else if (data.success) {
                    const aiReply = (data.message || '').trim();
                    if (aiReply) {
                        addMessage('ai', aiReply);
                        runtime.speakText(aiReply);
                    }
                    if (data.step) {
                        runtime.applyStep(data.step);
                    }
                    if (data.is_end) {
                        lockControls();
                        addMessage('ai', 'SYSTEM: *Session concluded. Processing analytics...*');
                        setTimeout(() => {
                            endSessionManual();
                        }, 2500);
                    }
                } else {
                    addMessage('system', '⚠️ ' + (data.message || 'AI response failed. Please try again.'));
                }
            } catch (e) {
                clearTimeout(chatTimeout);
                stopTypingIndicator();
                updateLiveHeaderStatus('disconnected');
                if (e.name === 'AbortError') {
                    addMessage('system', '⚠️ Request timed out. The AI service is not responding. Please try again.');
                } else {
                    console.error(e);
                    addMessage('system', '⚠️ Network error. Check your connection and try again.');
                }
            } finally {
                isSending = false;
                userInput.disabled = false;
                btnSend.disabled = false;
                userInput.focus();
            }
        }

        function lockControls() {
            userInput.disabled = true;
            btnSend.disabled = true;
            btnSpeak.disabled = true;
            btnSend.style.opacity = '0.5';
            btnSpeak.style.opacity = '0.5';
        }

        async function runCodeSimulation() {
            const code = runtime.getEditorValue();
            if (!code || !code.trim()) {
                LakshyaDialog.alert('Please write some code before running.', { type: 'warning', title: 'No Code' });
                return;
            }

            const btn = document.getElementById('btnRunCode');
            const consoleOut = document.getElementById('consoleOutput');
            const lang = document.getElementById('langSelector').value;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Executing...';
            consoleOut.innerHTML = `[System] Initializing ${lang} environment...\n[System] Compiling source...\n[System] Evaluating with AI (this may take 30–60 seconds)...`;
            consoleOut.className = 'console-out';

            if (!navigator.onLine) {
                consoleOut.innerHTML = `[Error] You are currently offline. Running code requires a server connection.`;
                consoleOut.className = 'console-out console-error';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play"></i> Run Code';
                return;
            }

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 290000);

            try {
                const res = await fetch('mock_ai_handler.php', {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        action: 'evaluate_code',
                        session_id: currentSessionId,
                        code: code,
                        language: lang
                    })
                });
                clearTimeout(timeoutId);

                const rawText = await res.text();
                let data = null;
                try {
                    data = JSON.parse(rawText);
                } catch (parseErr) {
                    console.error('Server returned non-JSON:', rawText.substring(0, 500));
                    consoleOut.innerHTML = `[Error] Server returned an unexpected response. Please try again.`;
                    consoleOut.className = 'console-out console-error';
                    return;
                }

                if (data.success && data.evaluation) {
                    const ev = data.evaluation;
                    consoleOut.className = 'console-out ' + (ev.passed ? 'console-success' : 'console-error');
                    consoleOut.textContent = `[Result] Status: ${ev.passed ? 'PASSED ✓' : 'FAILED ✗'}\n` +
                        `[Score] ${ev.score}/10\n` +
                        `[Feedback] ${ev.feedback}\n` +
                        (ev.suggestions ? `[Suggestions] ${ev.suggestions}` : '');

                    addMessage('system', `💡 **Code Evaluation Result:** ${ev.passed ? 'PASSED' : 'NEEDS IMPROVEMENT'} (${ev.score}/10)\n${ev.feedback}`);
                } else {
                    consoleOut.textContent = `[Error] ${data.message || 'Evaluation failed. Please try again.'}`;
                    consoleOut.className = 'console-out console-error';
                }
            } catch (err) {
                clearTimeout(timeoutId);
                consoleOut.innerHTML = `[Error] Request timed out or network failed. Please try again.`;
                consoleOut.className = 'console-out console-error';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play"></i> Run Code';
            }
        }

        function addMessage(role, text) {
            text = (text === null || text === undefined) ? '' : String(text);
            // Make sure the chat window exists even if the current step didn't list it
            if (!runtime.components.chat) {
                runtime.components.chat = registry.create('chat_window', document.getElementById('chatHistory'), eventBus);
            }
            if (text.includes('[SHOW_WORKSPACE]')) {
                toggleWorkspaceBtn.style.display = 'flex';
                if (window.innerWidth >= 1200) toggleCodingPanel();
                text = text.replace(/\[SHOW_WORKSPACE\]/g, '');
            }
            eventBus.emit('MESSAGE_RECEIVED', { role, content: text });
        }

        let isEndingSession = false;
        async function endSessionManual(isAuto = false) {
            if (isEndingSession) return;
            if (!currentSessionId) {
                window.location.href = 'dashboard.php';
                return;
            }

            if (!isAuto) {
                const proceed = await LakshyaDialog.confirm('Ending the session now will stop the interview. AI will generate a report based on the conversation and proctoring logs. Proceed?', { title: 'End Interview?', type: 'warning', okText: 'End Session', cancelText: 'Continue Interview', danger: true });
                if (!proceed || isEndingSession) return;
            }
            isEndingSession = true;

            isSessionActive = false;
            isProctoringActive = false;
            if (proctorInterval) clearInterval(proctorInterval);

            const widget = document.getElementById('proctorWidget');
            if (widget) widget.style.display = 'none';
            const warnOverlay = document.getElementById('warningOverlay');
            if (warnOverlay) warnOverlay.classList.add('hidden');

            if (webcamStream) webcamStream.getTracks().forEach(t => { t.onended = null; t.stop(); });
            if (screenStream) screenStream.getTracks().forEach(t => { t.onended = null; t.stop(); });
            // Release the microphone used by voice input
            if (runtime.components.voice && runtime.components.voice.stopListening) runtime.components.voice.stopListening();

            document.getElementById('reportLoading').style.display = 'flex';
            const reportLoadText = document.querySelector('#reportLoading h2');
            if (reportLoadText) {
                reportLoadText.innerText = isAuto ? 'Auto-Submitting & Compiling Integrity Report...' : 'Evaluating Performance & Integrity Report...';
            }

            lockControls();
            const exitBtn = document.querySelector('.btn-end');
            if (exitBtn) {
                exitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finalizing...';
                exitBtn.style.pointerEvents = 'none';
            }

            const snapshotSessionId = currentSessionId;
            const endController = new AbortController();
            const endTimeout = setTimeout(() => endController.abort(), 290000);

            const clientFacePct = proctorStats.totalFrames > 0 
                ? Math.round((proctorStats.validFaceFrames / proctorStats.totalFrames) * 100)
                : 100;

            try {
                const res = await fetch('mock_ai_handler.php', {
                    method: 'POST',
                    signal: endController.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        action: 'end_session',
                        session_id: snapshotSessionId,
                        company: COMPANY_NAME,
                        type: ROUND_TYPE,
                        strike_count: warningCount,
                        auto_submitted: isAuto ? 1 : 0,
                        client_face_presence_pct: clientFacePct
                    })
                });
                clearTimeout(endTimeout);

                const text = await res.text();
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    console.error("Failed to parse end_session JSON:", text.substring(0, 300));
                }

                if (data && data.success) {
                    if (data.is_incomplete) {
                        currentSessionId = null;
                        document.getElementById('reportLoading').style.display = 'none';
                        await LakshyaDialog.alert(data.message || 'The session ended before enough answers were recorded to generate a report.', { type: 'warning', title: 'Session Incomplete' });
                        window.location.href = 'mock_ai_interview.php';
                        return;
                    }
                    document.getElementById('reportLoading').style.display = 'none';

                    const modal = document.getElementById('scoreModal');
                    const scoreNum = document.getElementById('finalScoreNum');
                    const scorePct = document.getElementById('finalScorePct');
                    const badgeText = document.getElementById('scoreBadgeText');
                    const headerIcon = document.getElementById('scoreHeaderIcon');

                    const finalScore = Math.round(data.score ?? 0);
                    const rawScore = Math.round(data.raw_score ?? finalScore);
                    const penaltyPct = data.penalty_pct ?? (warningCount * 5);
                    const isPassed = finalScore >= 70;

                    scoreNum.innerText = finalScore;
                    if (isPassed) {
                        scoreNum.style.color = '#10b981';
                        scorePct.style.color = '#10b981';
                        if (badgeText) {
                            badgeText.innerText = 'INTERVIEW PASSED (Official Verification Achieved)';
                            badgeText.style.color = '#10b981';
                        }
                        if (headerIcon) {
                            headerIcon.style.color = '#10b981';
                            headerIcon.innerHTML = '<i class="fas fa-award"></i>';
                        }
                    } else {
                        scoreNum.style.color = '#ef4444';
                        scorePct.style.color = '#ef4444';
                        if (badgeText) {
                            badgeText.innerText = 'INTERVIEW NOT PASSED (Minimum 70% Required)';
                            badgeText.style.color = '#ef4444';
                        }
                        if (headerIcon) {
                            headerIcon.style.color = '#ef4444';
                            headerIcon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                        }
                    }

                    const rptRaw = document.getElementById('rptRawScore');
                    const rptPen = document.getElementById('rptPenaltyPct');
                    if (rptRaw) rptRaw.innerText = rawScore + '%';
                    if (rptPen) {
                        rptPen.innerText = penaltyPct > 0 ? `-${penaltyPct}% (${data.strike_count || warningCount} Strikes @ 5% each)` : '0% (No Violations)';
                        rptPen.style.color = penaltyPct > 0 ? '#f87171' : '#10b981';
                    }

                    if (data.integrity_report) {
                        const rpt = data.integrity_report;
                        if (document.getElementById('rptScreenShare')) document.getElementById('rptScreenShare').innerText = (rpt.screen_sharing_active_pct ?? 100) + '%';
                        if (document.getElementById('rptCameraAvail')) document.getElementById('rptCameraAvail').innerText = (rpt.camera_availability_pct ?? 100) + '%';
                        if (document.getElementById('rptFacePres')) document.getElementById('rptFacePres').innerText = (rpt.face_presence_pct ?? 100) + '%';
                        if (document.getElementById('rptGazeConf')) document.getElementById('rptGazeConf').innerText = (rpt.gaze_confidence_pct ?? 92) + '%';
                        if (document.getElementById('rptAttnDev')) document.getElementById('rptAttnDev').innerText = rpt.attention_deviations || 0;
                        if (document.getElementById('rptLongestDev')) document.getElementById('rptLongestDev').innerText = (rpt.longest_deviation_sec || 0) + 's';
                        if (document.getElementById('rptScreenInt')) document.getElementById('rptScreenInt').innerText = rpt.screen_interruptions || 0;
                        if (document.getElementById('rptMultiFace')) document.getElementById('rptMultiFace').innerText = rpt.multiple_faces_count || 0;

                        const pill = document.getElementById('rptStatusPill');
                        if (pill) {
                            pill.innerText = 'Integrity Status: ' + (rpt.integrity_status || (isPassed ? 'Verified' : 'Completed'));
                            if (rpt.auto_submitted || (data.strike_count || warningCount) >= 3 || rpt.screen_interruptions > 0 || penaltyPct > 0) {
                                pill.className = 'integrity-pill warning';
                            } else {
                                pill.className = 'integrity-pill success';
                            }
                        }
                    }

                    modal.style.display = 'flex';
                    currentSessionId = null;
                } else {
                    currentSessionId = null;
                    document.getElementById('reportLoading').style.display = 'none';
                    await LakshyaDialog.alert('Session error: ' + ((data && data.message) ? data.message : 'No response from server.'), { type: 'error', title: 'Session Error' });
                    window.location.href = 'dashboard.php';
                }
            } catch (err) {
                clearTimeout(endTimeout);
                currentSessionId = null;
                document.getElementById('reportLoading').style.display = 'none';
                if (err.name === 'AbortError') {
                    await LakshyaDialog.alert('Report generation timed out. Your session data is saved.', { type: 'warning', title: 'Report Delayed' });
                } else {
                    console.error(err);
                    await LakshyaDialog.alert('Could not reach the server to finish the session. Your session data is saved.', { type: 'error', title: 'Connection Error' });
                }
                window.location.href = 'dashboard.php';
            }
        }

        function closeSession() {
            window.location.href = 'dashboard.php';
        }

        function dismissBriefingAndStart() {
            const briefing = document.getElementById('briefingOverlay');
            if (briefing) briefing.style.display = 'none';
        }

        function sendCodeToAI() {
            const code = runtime.getEditorValue();
            if (!code || !code.trim()) {
                LakshyaDialog.alert('Please write some code before submitting.', { type: 'warning', title: 'No Code' });
                return;
            }
            sendMessage("Here is my code solution:\n```\n" + code + "\n```");
        }

        function toggleCodingPanel() {
            codingPanel.classList.toggle('active');
            const isShowing = codingPanel.classList.contains('active');
            if (isShowing) {
                runtime.refreshEditor();
            }
        }

        userInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        userInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            if (this.value === '') this.style.height = 'auto';
        });
        btnSend.onclick = sendMessage;
        if (window.speechSynthesis) {
            window.speechSynthesis.onvoiceschanged = () => { window.speechSynthesis.getVoices(); };
        }

        function onFullscreenChange() {
            if (!getFullscreenElement() && isSessionActive) {
                triggerWarning('Full screen mode was deactivated.', 'FULLSCREEN_EXIT');
            }
        }
        document.addEventListener('fullscreenchange', onFullscreenChange);
        document.addEventListener('webkitfullscreenchange', onFullscreenChange);

        function resumeFullscreen() {
            requestFullscreenCompat().then(() => {
                const warnOverlay = document.getElementById('warningOverlay');
                if (warnOverlay) warnOverlay.classList.add('hidden');
            }).catch(e => {
                // A native alert popup would blur the window and cause another strike, so show the hint inline
                const msgEl = document.getElementById('warningMessage');
                if (msgEl && !msgEl.querySelector('.fs-blocked-hint')) {
                    msgEl.innerHTML += '<br><br><span class="fs-blocked-hint" style="color:#f59e0b;">Your browser blocked full screen. Please click the button again and allow full screen if your browser asks (Mac: Ctrl+Cmd+F, Windows: F11).</span>';
                }
            });
        }

    </script>
</body>

</html>