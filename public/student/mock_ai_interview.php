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
$companyName = $filters['company'] ?? 'General';
$roundType = $filters['type'] ?? 'Technical';

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

    <!-- Score Modal -->
    <div id="scoreModal">
        <div class="score-card">
            <div class="score-title">Assessment Complete</div>
            <div>
                <span id="finalScoreNum" class="score-number">0</span><span id="finalScorePct"
                    class="score-percentage">%</span>
            </div>
            <div class="score-desc">Your interview performance has been evaluated.</div>
            <button class="btn-continue" onclick="closeSession()">Continue</button>
        </div>
    </div>

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

                let formatted = content;
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
                    <div class="mcq-option-badge">${opt.key}</div>
                    <div>${opt.text}</div>
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
                this.recognition = SpeechRec ? new SpeechRec() : null;
                this.synth = window.speechSynthesis;
                this.isSpeaking = false;
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
                    const btnSpeak = document.getElementById('btnSpeak');
                    if (btnSpeak) btnSpeak.classList.add('active');
                    stateStore.update('voiceEnabled', true);
                    this.currentSpeechFinal = userInput.value;
                };
                this.recognition.onend = () => {
                    const btnSpeak = document.getElementById('btnSpeak');
                    if (btnSpeak) btnSpeak.classList.remove('active');
                    stateStore.update('voiceEnabled', false);
                    if (userInput.value.trim().length > 0) {
                        sendMessage();
                    }
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
                    const btnSpeak = document.getElementById('btnSpeak');
                    if (btnSpeak) btnSpeak.classList.remove('active');
                    stateStore.update('voiceEnabled', false);
                    if (e.error === 'not-allowed') {
                        alert('Microphone access was denied. Please allow microphone access to use voice input.');
                    }
                };
            }
            startListening() {
                if (this.recognition) {
                    try {
                        if (stateStore.state.voiceEnabled) {
                            this.recognition.stop();
                        } else {
                            this.recognition.start();
                        }
                    } catch (e) {
                        console.error("Speech toggle error:", e);
                    }
                } else {
                    alert("Speech recognition is not supported in this browser. Please use Google Chrome or Microsoft Edge.");
                }
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

            const hasSpeech = ('webkitSpeechRecognition' in window);
            document.getElementById('diagSpeechAPI').innerText = hasSpeech ? 'Available' : 'Unsupported';
            document.getElementById('diagSpeaking').innerText = (runtime.components.voice && runtime.components.voice.isSpeaking) ? 'Yes' : 'No';
        }

        document.addEventListener('keydown', e => {
            if (e.ctrlKey && e.shiftKey && e.key.toUpperCase() === 'D') {
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

        function startInterviewWithCustomRole() {
            const concepts = document.getElementById('customConcepts').value.trim();
            const difficulty = document.getElementById('customDifficulty').value;
            if (!concepts) return alert('Please specify at least one concept/topic to begin the session.');

            // Show premium loader and hide role modal
            document.getElementById('roleSelection').style.display = 'none';
            const loader = document.getElementById('premiumLoader');
            loader.style.display = 'flex';
            document.getElementById('targetRoleLabel').innerText = concepts + " (" + difficulty + ")";

            runLoadingSequence(concepts, difficulty);
        }

        let p_role = "";
        let p_difficulty = "Medium";
        async function runLoadingSequence(concepts, difficulty) {
            p_role = concepts;
            p_difficulty = difficulty;
            const steps = ['step-1', 'step-2', 'step-3', 'step-4'];

            for (let i = 0; i < steps.length; i++) {
                const stepEl = document.getElementById(steps[i]);
                stepEl.classList.add('active');

                // Artificial delay for professionalism
                await new Promise(r => setTimeout(r, 1000));

                // On step 2, we actually start the backend call in parallel
                if (i === 1) {
                    initiateBackendSession(concepts, difficulty);
                }

                stepEl.classList.replace('active', 'completed');
            }

            // Show permission hint and final launch button
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
                // 2. Start Session
                const res = await fetch('mock_ai_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        action: 'start',
                        role: concepts,
                        concept: concepts, // Use concepts list
                        difficulty: difficulty,
                        company: "<?php echo addslashes($companyName); ?>",
                        type: "<?php echo $roundType; ?>"
                    })
                });
                const text = await res.text();
                try {
                    backendInitData = JSON.parse(text);
                } catch (err) {
                    console.error("Failed to parse start session JSON:", text);
                }
            } catch (e) {
                console.error("Backend init failed", e);
            }
        }

        async function executeFinalLaunch() {
            if (!backendInitData) {
                alert("Connection error. Please try again.");
                window.location.reload();
                return;
            }

            if (!backendInitData.success) {
                alert('Session initiation failed: ' + backendInitData.message);
                window.location.reload();
                return;
            }

            // Fullscreen trigger (requires user gesture - which this click provides)
            try {
                if (document.documentElement.requestFullscreen) {
                    await document.documentElement.requestFullscreen();
                } else if (document.documentElement.webkitRequestFullscreen) {
                    await document.documentElement.webkitRequestFullscreen();
                }
            } catch (e) { console.warn("Fullscreen deferred: ", e); }

            // Speech permission will be requested when user clicks the Speak button.

            // Hide loader and start interview
            document.getElementById('premiumLoader').style.opacity = '0';
            setTimeout(() => {
                document.getElementById('premiumLoader').style.display = 'none';
                finalizeInterviewStart();
            }, 500);
        }

        // Check for active session on load
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
                        isProctoringActive = true;

                        const restored = stateStore.restore(data.session_id);
                        if (restored) {
                            if (stateStore.state.currentStep) {
                                runtime.applyStep(stateStore.state.currentStep);
                            }
                            if (stateStore.state.chatHistory.length > 0) {
                                const container = document.getElementById('chatHistory');
                                container.innerHTML = ''; // Clear fresh placeholders
                                stateStore.state.chatHistory.forEach(m => {
                                    eventBus.emit('MESSAGE_RECEIVED', { role: m.role, content: m.content });
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

                        if ("<?php echo $roundType; ?>" === "Technical") {
                            toggleWorkspaceBtn.style.display = 'flex';
                        }
                    };

                    document.getElementById('btnStartFresh').onclick = async () => {
                        modal.style.display = 'none';
                        // Retire old session
                        await fetch('mock_ai_handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': CSRF_TOKEN
                            },
                            body: JSON.stringify({ action: 'cancel_pending', session_id: data.session_id })
                        });
                    };
                }
            } catch (e) { console.warn("Active session check failed", e); }
        });

        function finalizeInterviewStart() {
            if (!backendInitData || !backendInitData.success) {
                alert("Error: Backend initialization data missing or failed.");
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
            isProctoringActive = true;

            if ("<?php echo $roundType; ?>" === "Technical") {
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
            // Deprecated by startInterviewWithCustomRole
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

            // Prevent double-sends while AI is still processing
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
                    type: "<?php echo $roundType; ?>"
                }
            };

            if (!navigator.onLine) {
                offlineQueue.enqueue(requestPayload);
                stopTypingIndicator();
                addMessage('system', '⚠️ Offline. Action buffered in queue.');
                updateLiveHeaderStatus('disconnected');
                // This early return bypasses the try/finally below — without resetting
                // here, the input and send button stayed disabled forever.
                isSending = false;
                userInput.disabled = false;
                btnSend.disabled = false;
                return;
            }

            // Snapshot session id so the closure captures the right value even if
            // currentSessionId changes before the async response arrives.
            const snapshotSessionId = currentSessionId;

            // Client-side timeout aligned with the server's true worst case:
            // AIService retries up to 3× with a 90s cURL timeout (~272s total).
            // Aborting earlier caused a subtle bug — the server kept processing and
            // SAVED the AI reply, so the user's retry duplicated their answer.
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
                            const statusRes = await fetch(`ai_job_status.php?job_id=${data.job_id}`).then(r => r.json());
                            if (statusRes.success && statusRes.status === 'completed') {
                                clearInterval(pollInterval);
                                const result = statusRes.result;
                                addMessage('ai', result.message || result.content);
                                runtime.speakText(result.message || result.content);
                                if (result.is_end) {
                                    lockControls();
                                    addMessage('ai', 'SYSTEM: *Session concluded. Processing analytics...*');
                                    setTimeout(() => {
                                        window.location.href = `mock_ai_report.php?session_id=${snapshotSessionId}`;
                                    }, 3000);
                                }
                            } else if (statusRes.status === 'failed') {
                                clearInterval(pollInterval);
                                alert("AI generation failed: " + statusRes.error);
                            }
                        } catch (e) {
                            console.error("Polling error:", e);
                        }
                    }, 2000);
                } else if (data.success) {
                    // Always render the AI reply first, then apply UI step if provided
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
                            currentSessionId = null; // Unblock navigation
                            window.location.href = `mock_ai_report.php?session_id=${snapshotSessionId}`;
                        }, 3000);
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
                alert('Please write some code before running.');
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

            // AbortController gives us a real client-side timeout for the AI call —
            // aligned with the server's retry worst case (~272s), see sendMessage()
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

                const text = await res.text();
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    console.error("Failed to parse evaluate_code JSON:", text.substring(0, 300));
                    consoleOut.innerHTML = `[Error] Server returned an unexpected response. Check server logs.`;
                    consoleOut.className = 'console-out console-error';
                    return;
                }

                if (data && data.success) {
                    const ev = data.evaluation;
                    const status = ev.passed ? 'PASSED ✅' : 'FAILED ❌';
                    consoleOut.innerHTML = `[Output]\n${ev.output_log || 'Execution complete.'}\n\n[Evaluation]\nScore: ${ev.score}/10\nStatus: ${status}\n\n[Feedback]\n${ev.feedback}`;
                    consoleOut.className = ev.passed ? 'console-out console-success' : 'console-out console-error';

                    addMessage('system', `Code Execution: Score ${ev.score}/10 — ${status}`);

                    // Always send result to AI so it gives feedback and the next question,
                    // regardless of pass/fail. Truncate long feedback to avoid polluting the
                    // chat message with the full evaluation text.
                    setTimeout(() => {
                        const shortFeedback = (ev.feedback || '').substring(0, 200);
                        const resultMsg = ev.passed
                            ? `System: The code execution was successful and scored ${ev.score}/10. Please provide your technical critique and ask the next question.`
                            : `System: The code execution failed with score ${ev.score}/10. Feedback summary: ${shortFeedback}. Please briefly comment on this and continue with the next question.`;
                        sendMessage(resultMsg);
                    }, 1000);
                } else {
                    const errMsg = (data && data.message) ? data.message : 'Evaluation service did not respond.';
                    consoleOut.innerHTML = `[Error] ${errMsg}`;
                    consoleOut.className = 'console-out console-error';
                }
            } catch (e) {
                clearTimeout(timeoutId);
                if (e.name === 'AbortError') {
                    consoleOut.innerHTML = `[Timeout] Evaluation took too long. The AI service may be overloaded. Please try again.`;
                } else {
                    consoleOut.innerHTML = `[Fatal] Connection error: ${e.message}`;
                }
                consoleOut.className = 'console-out console-error';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play"></i> Run Code';
            }
        }

        function addMessage(role, text) {
            if (text.includes('[SHOW_WORKSPACE]')) {
                toggleWorkspaceBtn.style.display = 'flex';
                if (window.innerWidth >= 1200) toggleCodingPanel();
                text = text.replace(/\[SHOW_WORKSPACE\]/g, '');
            }
            eventBus.emit('MESSAGE_RECEIVED', { role, content: text });
        }

        async function endSessionManual() {
            if (!currentSessionId) {
                window.location.href = 'dashboard.php';
                return;
            }

            if (!confirm('Warning: Ending the session now will stop the interview. AI will generate a report based on the partial conversation. Proceed?')) {
                return;
            }

            isProctoringActive = false; // Disable security flag
            document.getElementById('reportLoading').style.display = 'flex';

            lockControls();
            const exitBtn = document.querySelector('.btn-end');
            exitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finalizing...';
            exitBtn.style.pointerEvents = 'none';

            const snapshotSessionId = currentSessionId;

            // Give report generation the server's full retry window (~272s) before giving up
            const endController = new AbortController();
            const endTimeout = setTimeout(() => endController.abort(), 290000);

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
                        company: '<?php echo addslashes($companyName); ?>',
                        type: '<?php echo $roundType; ?>'
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
                        alert(data.message);
                        currentSessionId = null;
                        window.location.href = 'mock_ai_interview';
                        return;
                    }
                    document.getElementById('reportLoading').style.display = 'none';

                    const modal = document.getElementById('scoreModal');
                    const scoreNum = document.getElementById('finalScoreNum');
                    const scorePct = document.getElementById('finalScorePct');

                    let s = data.score || 0;
                    scoreNum.innerText = s;
                    if (s <= 0) {
                        scoreNum.classList.add('score-zero');
                        scorePct.classList.add('score-zero');
                    } else {
                        scoreNum.classList.remove('score-zero');
                        scorePct.classList.remove('score-zero');
                    }

                    modal.style.display = 'flex';
                    currentSessionId = null; // Unblock navigation
                } else {
                    alert('Session error: ' + (data ? data.message : 'No response from server.'));
                    currentSessionId = null;
                    window.location.href = 'dashboard.php';
                }
            } catch (err) {
                clearTimeout(endTimeout);
                if (err.name === 'AbortError') {
                    alert('Report generation timed out. Your session data is saved. Please check your report from the dashboard.');
                } else {
                    console.error(err);
                }
                currentSessionId = null;
                window.location.href = 'dashboard.php';
            }
        }

        function closeSession() {
            window.location.href = 'dashboard.php';
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
        window.speechSynthesis.onvoiceschanged = () => { window.speechSynthesis.getVoices(); };

        // --- SECURITY PROTOCOLS ---
        // Security protocols (copy/paste & developer hotkeys blocks removed as requested)

        // Monitor Fullscreen Exit
        document.addEventListener('fullscreenchange', handleSecurityFlag);
        document.addEventListener('webkitfullscreenchange', handleSecurityFlag);

        function handleSecurityFlag() {
            const isFS = document.fullscreenElement || document.webkitFullscreenElement;
            const warning = document.getElementById('securityWarning');

            if (!isFS && isProctoringActive) {
                warning.style.display = 'flex';
            } else {
                warning.style.display = 'none';
            }
        }

        async function resumeFullscreen() {
            try {
                if (document.documentElement.requestFullscreen) {
                    await document.documentElement.requestFullscreen();
                } else if (document.documentElement.webkitRequestFullscreen) {
                    await document.documentElement.webkitRequestFullscreen();
                }
            } catch (e) {
                alert("Please press F11 to resume Full Screen mode manually.");
            }
        }

    </script>
</body>

</html>