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
?>
    <title>Mock AI Interview | Lakshya</title>
    <!-- Resilience & Cache Busting -->
    <script src="resilience.js?v=<?php echo APP_VERSION; ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CodeMirror for Coding Workspace -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/dracula.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/clike/clike.min.js"></script>
    <style>
        :root {
            --primary: #800000;
            --primary-dark: #4a0000;
            --accent: #e9c66f;
            --bg-body: #0f0f12;
            --card-glass: rgba(255, 255, 255, 0.03);
            --border-glass: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --user-bubble: linear-gradient(135deg, #800000 0%, #4a0000 100%);
            --ai-bubble: rgba(255, 255, 255, 0.05);
            --header-height: 80px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
            user-select: none; /* Block selection */
            -webkit-user-select: none;
        }

        @media print {
            body { display: none !important; }
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
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
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
            top: 0; left: 0; width: 100%; height: 100%;
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
            from { opacity: 0; transform: scale(0.8) translateY(30px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .role-modal h2 { 
            font-size: 2rem; 
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .role-modal p { color: var(--text-muted); margin-bottom: 2rem; }

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
            background: rgba(0,0,0,0.2);
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
            background: rgba(0,0,0,0.2);
            border-top: 1px solid var(--border-glass);
            display: flex;
            gap: 15px;
        }

        .btn-send-code {
            flex: 1;
            background: rgba(255,255,255,0.05);
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
            background: rgba(0,0,0,0.4);
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

        .console-success { color: #10b981; }
        .console-error { color: #ef4444; }

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

        .chat-messages::-webkit-scrollbar { width: 6px; }
        .chat-messages::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }

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
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
            display: none; /* JS will set to flex */
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
        .dot-flashing::before, .dot-flashing::after {
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
        .dot-flashing::before { left: -12px; animation: dot-flashing 1s infinite alternate; animation-delay: 0s; }
        .dot-flashing::after { left: 12px; animation: dot-flashing 1s infinite alternate; animation-delay: 1s; }

        @keyframes dot-flashing {
            0% { background-color: var(--primary); }
            50%, 100% { background-color: rgba(128, 0, 0, 0.1); }
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

        .input-pill input {
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
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
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
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.98);
            z-index: 9999;
            display: none; /* JS will show */
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
            top: 0; left: 0; width: 100%; height: 100%;
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
            border: 5px solid rgba(255,255,255,0.1);
            border-top: 5px solid var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 30px;
        }

        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* Premium Loader Styles */
        .premium-loader-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
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
            from { opacity: 0; transform: scale(0.9) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .loader-visual {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 40px;
        }

        .orbit {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            border: 2px solid rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .orbit-pulse {
            position: absolute;
            top: 10%; left: 10%; width: 80%; height: 80%;
            border: 2px solid var(--primary);
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1.5s linear infinite;
        }

        .orbit-pulse-inner {
            position: absolute;
            top: 25%; left: 25%; width: 50%; height: 50%;
            border: 2px solid var(--accent);
            border-radius: 50%;
            border-bottom-color: transparent;
            animation: spin-reverse 2s linear infinite;
        }

        @keyframes spin-reverse {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
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
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); opacity: 1; }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .session-header { padding: 0 20px; }
            .brand-text { display: none; }
            .message { max-width: 90%; }
        }
        /* Resumption Modal */
        #resumeModal {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
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
            background: rgba(255,255,255,0.05);
            color: white;
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-new-session:hover {
            background: rgba(255,255,255,0.1);
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
            <div class="logo-icon"><i class="fas fa-microchip"></i></div>
            <div class="brand-text">
                <h1><?php echo $roundType; ?> INTERVIEW</h1>
                <span>AI MOCK SESSION • <?php echo htmlspecialchars($companyName); ?></span>
            </div>
        </div>
    </div>

    <div class="session-status" id="sessionStatus" style="display: none;">
        <div class="status-dot"></div>
        LIVE SESSION ACTIVE
    </div>

    <div style="display: flex; gap: 15px; align-items: center;">
        <button class="btn-workspace" id="toggleWorkspace" style="display:none;" onclick="toggleCodingPanel()">
            <i class="fas fa-code"></i> Coding Workspace
        </button>
        <button class="btn-end" onclick="endSessionManual()">
            <i class="fas fa-power-off"></i> End Session
        </button>
    </div>
</header>

<!-- Role Selection Overlay -->
<div id="roleSelection">
    <div class="role-modal">
        <div style="font-size: 3rem; color: var(--accent); margin-bottom: 1.5rem;"><i class="fas fa-brain"></i></div>
        <h2>Preparing Your Session</h2>
        <p>Your AI <?php echo $roundType; ?> Interviewer is analyzing the requirements for <b><?php echo htmlspecialchars($companyName); ?></b>. Which specific role are you targeting?</p>
        
        <div class="role-input-wrap">
            <input type="text" id="customRole" class="role-input" 
                   placeholder="e.g. <?php echo $roundType === 'HR' ? 'Behavioral / Cultural' : 'Junior Software Developer'; ?>" 
                   value="<?php echo $roundType === 'HR' ? 'HR Behavioral Round' : ''; ?>">
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
            <i class="fas fa-microchip" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 2rem; color: white;"></i>
        </div>
        <h2 style="font-size: 2.2rem; margin-bottom: 10px;">Initializing AI Session</h2>
        <p style="color: var(--text-muted); margin-bottom: 30px;">Setting up your proctored environment...</p>

        <ul class="loader-steps">
            <li class="loader-step" id="step-1"><i class="fas fa-search"></i> Analyzing requirements for <span id="targetRoleLabel"></span>...</li>
            <li class="loader-step" id="step-2"><i class="fas fa-cog"></i> Configuring AI Interviewer...</li>
            <li class="loader-step" id="step-3"><i class="fas fa-briefcase"></i> Preparing Industry Scenarios...</li>
            <li class="loader-step" id="step-4"><i class="fas fa-shield-alt"></i> Activating Security Protocols...</li>
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

<div class="workspace-wrapper">
    <main class="chat-container">
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
                <input type="text" id="userInput" placeholder="Type your answer here..." autocomplete="off">
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
            <select id="langSelector" style="background:#222; color:white; border:1px solid #444; padding:5px; border-radius:5px; font-size: 0.8rem;">
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
            <button class="btn-run-code" id="btnRunCode" onclick="runCodeSimulation()">
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
        <p style="color: #94a3b8; line-height: 1.6;">You have exited <b>FULL SCREEN</b> mode. This is a violation of the proctoring rules. Please return to full screen immediately to continue your interview.</p>
        <button class="btn-security" onclick="resumeFullscreen()">RESUME INTERVIEW</button>
    </div>
</div>

<!-- Report Loading Overlay -->
<div id="reportLoading" class="report-loading-overlay">
    <div class="loader-spinner"></div>
    <h2 style="color: white; font-size: 2rem; margin-bottom: 10px;">Generating Analytics</h2>
    <p style="color: var(--text-muted);">Please wait while AI analyzes your performance and generates a comprehensive report...</p>
</div>

<!-- Session Resumption Modal -->
<div id="resumeModal">
    <div class="resume-card">
        <div style="font-size: 3.5rem; color: var(--accent); margin-bottom: 1.5rem;"><i class="fas fa-history"></i></div>
        <h2>Active Session Found</h2>
        <p>You have an ongoing interview session for <b><span id="resumeRole"></span></b>. Would you like to resume where you left off or start a fresh session?</p>
        <div class="resume-actions">
            <button class="btn-new-session" id="btnStartFresh">START FRESH</button>
            <button class="btn-resume" id="btnResumeSession">RESUME SESSION</button>
        </div>
    </div>
</div>

<script>
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

    // Initialize CodeMirror
    function initEditor() {
        if (editor) return;
        editor = CodeMirror.fromTextArea(document.getElementById("codeEditor"), {
            mode: "python",
            theme: "dracula",
            lineNumbers: true,
            autoCloseBrackets: true,
            matchBrackets: true,
            indentUnit: 4,
            tabSize: 4,
            lineWrapping: true
        });
        
        document.getElementById('langSelector').onchange = (e) => {
            editor.setOption("mode", e.target.value);
        };
    }

    function toggleCodingPanel() {
        codingPanel.classList.toggle('active');
        const isShowing = codingPanel.classList.contains('active');
        if (isShowing) {
            initEditor();
            setTimeout(() => editor.refresh(), 100);
        }
    }

    function sendCodeToAI() {
        const code = editor.getValue().trim();
        if (!code) return alert("Workspace is empty.");
        const lang = document.getElementById('langSelector').value;
        
        const message = `Here is my ${lang} implementation:\n\n\`\`\`${lang}\n${code}\n\`\`\``;
        userInput.value = message;
        sendMessage();
        
        // Optionally close panel on small screens
        if (window.innerWidth < 1200) toggleCodingPanel();
    }

    // Prevent accidental navigation
    window.addEventListener('beforeunload', (e) => {
        if (currentSessionId) {
            e.preventDefault();
            e.returnValue = 'Interview session is active. Are you sure you want to exit without finishing?';
        }
    });

    // Web Speech API Setup
    const recognition = 'webkitSpeechRecognition' in window ? new webkitSpeechRecognition() : null;
    const synth = window.speechSynthesis;

    if (recognition) {
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'en-US';

        recognition.onstart = () => btnSpeak.classList.add('active');
        recognition.onend = () => btnSpeak.classList.remove('active');
        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            userInput.value = transcript;
            sendMessage();
        };
    }

    btnSpeak.onclick = () => {
        if (!recognition) return alert('Speech recognition not supported in this browser.');
        recognition.start();
    };

    let speechQueue = [];
    let isSpeaking = false;

    function speakText(text) {
        synth.cancel();
        speechQueue = [];
        isSpeaking = false;

        let cleanText = text.replace(/\[END_INTERVIEW\]/g, '')
                            .replace(/\*\*/g, '')
                            .replace(/- /g, ', ')
                            .replace(/\n/g, '. ')
                            .replace(/=/g, ' equals ')
                            .replace(/\+/g, ' plus ')
                            .replace(/(\d+):(\d+)/g, '$1 $2');
        
        // Split into smaller chunks (sentences) for better reliability
        const chunks = cleanText.match(/[^.!?]+[.!?]*|[^.!?]+/g) || [cleanText];
        chunks.forEach(c => {
            const trimmed = c.trim();
            if (trimmed.length > 0) speechQueue.push(trimmed);
        });

        processSpeechQueue();
    }

    function processSpeechQueue() {
        if (isSpeaking || speechQueue.length === 0) return;

        const text = speechQueue.shift();
        const utterance = new SpeechSynthesisUtterance(text);
        
        const voices = synth.getVoices();
        const professionalVoices = [
            'Microsoft Aria Online (Natural)', 'Microsoft Jenny Online (Natural)',
            'Google US English Female', 'Microsoft Zira Desktop', 'Samantha'
        ];

        let selectedVoice = null;
        for (let name of professionalVoices) {
            selectedVoice = voices.find(v => v.name.includes(name));
            if (selectedVoice) break;
        }

        if (!selectedVoice) {
            selectedVoice = voices.find(v => (v.lang === 'en-US' || v.lang === 'en-GB') && v.name.toLowerCase().includes('female'));
        }

        if (selectedVoice) utterance.voice = selectedVoice;
        utterance.rate = 1.0;
        utterance.pitch = 1.0;
        utterance.volume = 1.0;

        utterance.onend = () => {
            isSpeaking = false;
            processSpeechQueue();
        };

        utterance.onerror = (e) => {
            console.error("Speech error:", e);
            isSpeaking = false;
            processSpeechQueue();
        };

        isSpeaking = true;
        synth.speak(utterance);
    }

    function startInterviewWithCustomRole() {
        const role = document.getElementById('customRole').value.trim();
        if (!role) return alert('Please specify a role to begin the session.');
        
        // Request Microphone Permission early
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(stream => {
                    stream.getTracks().forEach(track => track.stop());
                })
                .catch(err => {
                    console.warn("Microphone permission was not granted: ", err);
                });
        }

        // Show premium loader and hide role modal
        document.getElementById('roleSelection').style.display = 'none';
        const loader = document.getElementById('premiumLoader');
        loader.style.display = 'flex';
        document.getElementById('targetRoleLabel').innerText = role;

        runLoadingSequence(role);
    }

    let p_role = "";
    async function runLoadingSequence(role) {
        p_role = role;
        const steps = ['step-1', 'step-2', 'step-3', 'step-4'];
        
        for (let i = 0; i < steps.length; i++) {
            const stepEl = document.getElementById(steps[i]);
            stepEl.classList.add('active');
            
            // Artificial delay for professionalism
            await new Promise(r => setTimeout(r, 1000));
            
            // On step 2, we actually start the backend call in parallel
            if (i === 1) {
                initiateBackendSession(role);
            }

            stepEl.classList.replace('active', 'completed');
        }

        // Show permission hint and final launch button
        document.getElementById('permissionHint').style.display = 'block';
        document.getElementById('finalLaunchBtn').style.display = 'inline-block';
    }

    let backendInitData = null;
    async function initiateBackendSession(role) {
        try {
            // 2. Start Session
            const res = await fetch('mock_ai_handler', { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'start', 
                    role: role, 
                    concept: role, // Use role as concept for general mocks
                    company: "<?php echo addslashes($companyName); ?>",
                    type: "<?php echo $roundType; ?>"
                })
            });
            backendInitData = await res.json();
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

        // Start Speech permission (requires user gesture)
        if (recognition) {
            try { recognition.start(); recognition.stop(); } catch(e){} // Trigger permission
        }

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
            const res = await fetch('mock_ai_handler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'check_active' })
            });
            const data = await res.json();
            if (data.success && data.has_active) {
                const modal = document.getElementById('resumeModal');
                const roleSpan = document.getElementById('resumeRole');
                roleSpan.innerText = data.role || 'a Previous Role';
                modal.style.display = 'flex';

                document.getElementById('btnResumeSession').onclick = () => {
                    modal.style.display = 'none';
                    currentSessionId = data.session_id;
                    document.getElementById('roleSelection').style.display = 'none';
                    sessionStatus.style.display = 'flex';
                    isProctoringActive = true;
                    
                    if (data.history && data.history.length > 0) {
                        data.history.forEach(m => addMessage(m.role, m.content));
                        speakText("Resuming session. Let's continue.");
                    }
                    
                    if ("<?php echo $roundType; ?>" === "Technical") {
                        toggleWorkspaceBtn.style.display = 'flex';
                    }
                };

                document.getElementById('btnStartFresh').onclick = async () => {
                    modal.style.display = 'none';
                    // Retire old session
                    await fetch('mock_ai_handler', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
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
        sessionStatus.style.display = 'flex';
        isProctoringActive = true;

        if ("<?php echo $roundType; ?>" === "Technical") {
            toggleWorkspaceBtn.style.display = 'flex';
        }

        const msg = backendInitData.message;
        if (msg) {
            addMessage('ai', msg);
            speakText(msg);
        }
    }

    async function startInterview(role) {
        // Deprecated by startInterviewWithCustomRole
    }

    async function sendMessage(customMsg = null) {
        const msg = customMsg || userInput.value.trim();
        if (!msg || !currentSessionId) return;

        if (!customMsg) {
            addMessage('user', msg);
            userInput.value = '';
        }

        typingIndicator.style.display = 'flex';

        try {
            const res = await fetch('mock_ai_handler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'chat', 
                    session_id: currentSessionId, 
                    message: msg,
                    type: "<?php echo $roundType; ?>"
                })
            });
            const data = await res.json();
            
            typingIndicator.style.display = 'none';
            if (data.success && data.job_id) {
                // Poll for Job Status
                const pollInterval = setInterval(async () => {
                    try {
                        const statusRes = await fetch(`ai_job_status.php?job_id=${data.job_id}`).then(r => r.json());
                        if (statusRes.success && statusRes.status === 'completed') {
                            clearInterval(pollInterval);
                            const result = statusRes.result;
                            addMessage('ai', result.message || result.content);
                            speakText(result.message || result.content);
                            if (result.is_end) {
                                lockControls();
                                addMessage('ai', 'SYSTEM: *Session concluded. Processing analytics...*');
                                setTimeout(() => {
                                    window.location.href = `mock_ai_report?session_id=${data.session_id}`;
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
                addMessage('ai', data.message);
                speakText(data.message);
                if (data.is_end) {
                    lockControls();
                    addMessage('ai', 'SYSTEM: *Session concluded. Processing analytics...*');
                    setTimeout(() => {
                        currentSessionId = null; // Unblock navigation
                        window.location.href = `mock_ai_report?session_id=${data.session_id}`;
                    }, 3000);
                }
            }
        } catch (e) {
            console.error(e);
            alert('Failed to sync response.');
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
        const code = editor.getValue().trim();
        if (!code) return;
        
        const btn = document.getElementById('btnRunCode');
        const consoleOut = document.getElementById('consoleOutput');
        const lang = document.getElementById('langSelector').value;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Executing...';
        consoleOut.innerHTML = `[System] Initializing ${lang} environment...\n[System] Compiling source...\n[System] Executing unit tests...`;
        consoleOut.className = 'console-out';

        try {
            const res = await fetch('mock_ai_handler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'evaluate_code', 
                    session_id: currentSessionId, 
                    code: code,
                    language: lang
                })
            });
            const data = await res.json();
            
            if (data.success) {
                const eval = data.evaluation;
                consoleOut.innerHTML = `[Output]\n${eval.output_log || 'Execution successful.'}\n\n[Evaluation]\nScore: ${eval.score}/10\nStatus: ${eval.passed ? 'PASSED' : 'FAILED'}\n\n${eval.feedback}`;
                consoleOut.className = eval.passed ? 'console-out console-success' : 'console-out console-error';
                
                // Also add a system message to chat so AI knows we ran code
                addMessage('system', `Code Execution: Score ${eval.score}/10, Status: ${eval.passed ? 'PASSED' : 'FAILED'}`);

                if (eval.passed) {
                    // Auto-trigger next question if code passed
                    setTimeout(() => {
                        sendMessage(`System: The code execution was successful and scored ${eval.score}/10. Please provide your technical critique and ask the next question.`);
                    }, 1000);
                }
            } else {
                consoleOut.innerHTML = `[Error] ${data.message}`;
                consoleOut.className = 'console-out console-error';
            }
        } catch (e) {
            consoleOut.innerHTML = `[Fatal] Connection error.`;
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Run Code';
        }
    }

    function addMessage(role, text) {
        const div = document.createElement('div');
        div.className = `message ${role}`;
        
        if (text.includes('[SHOW_WORKSPACE]')) {
            toggleWorkspaceBtn.style.display = 'flex';
            if (window.innerWidth >= 1200) toggleCodingPanel();
            text = text.replace(/\[SHOW_WORKSPACE\]/g, '');
        }

        let formattedText = text
            .replace(/\[END_INTERVIEW\]/g, '')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/- (.*?)\n/g, '<li>$1</li>')
            .replace(/\n\n/g, '<br><br>');
            
        if (formattedText.includes('Next time, say it like this:')) {
            formattedText = formattedText.replace(
                /Next time, say it like this: (.*?)(<br|$)/, 
                '<div class="expert-box"><strong>Expert Phrasing:</strong> $1</div>'
            );
        }

        div.innerHTML = formattedText;
        chatHistory.appendChild(div);
        chatHistory.scrollTop = chatHistory.scrollHeight;
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

        // Add a system log message

        try {
            const res = await fetch('mock_ai_handler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'end_session', 
                    session_id: currentSessionId,
                    company: '<?php echo $companyName; ?>',
                    type: '<?php echo $roundType; ?>'
                })
            });
            const data = await res.json();
            
            if (data.success) {
                if (data.is_incomplete) {
                    alert(data.message);
                    currentSessionId = null;
                    window.location.href = 'mock_ai_interview';
                    return;
                }
                addMessage('ai', 'SYSTEM: *Analysis complete. Redirecting to report...*');
                currentSessionId = null; // Unblock navigation
                setTimeout(() => {
                    window.location.href = `mock_ai_report?session_id=${data.session_id}`;
                }, 2000);
            } else {
                alert('Session error: ' + data.message);
                currentSessionId = null;
                window.location.href = 'dashboard.php';
            }
        } catch (err) {
            console.error(err);
            currentSessionId = null;
            window.location.href = 'dashboard.php';
        }
    }

    userInput.onkeypress = (e) => { if (e.key === 'Enter') sendMessage(); };
    btnSend.onclick = sendMessage;
    window.speechSynthesis.onvoiceschanged = () => { window.speechSynthesis.getVoices(); };

    // --- SECURITY PROTOCOLS ---
    
    // Disable Context Menu, Copy, Paste, Cut
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('copy', e => e.preventDefault());
    document.addEventListener('cut', e => e.preventDefault());
    document.addEventListener('paste', e => e.preventDefault());

    // Disable Developer Hotkeys
    document.addEventListener('keydown', e => {
        // Disable F12, Ctrl+Shift+I (Inspect), Ctrl+Shift+J (Console), Ctrl+U (Source)
        if (e.key === 'F12') e.preventDefault();
        if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J')) e.preventDefault();
        if (e.ctrlKey && e.key.toLowerCase() === 'u') e.preventDefault();
        // Disable Ctrl+S, Ctrl+P, PrintScreen
        if (e.ctrlKey && (e.key.toLowerCase() === 's' || e.key.toLowerCase() === 'p')) e.preventDefault();
        if (e.key === 'PrintScreen') {
            navigator.clipboard.writeText(""); // Clear clipboard
            alert('Screenshots are disabled for this session.');
            e.preventDefault();
        }
    });

    // Clipboard clear loop during session
    setInterval(() => {
        if(isProctoringActive) {
            try { navigator.clipboard.writeText(""); } catch(e){}
        }
    }, 2000);

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
