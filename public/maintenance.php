<?php
require_once __DIR__ . '/../config/bootstrap.php';
// Skip maintenance check for this page to avoid infinite redirect
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance | Lakshya</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #800000; /* Maroon */
            --secondary: #e9c66f; /* Gold */
            --dark: #0a0a0a;
            --text: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--dark);
            color: var(--text);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .bg-glow {
            position: absolute;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(128, 0, 0, 0.15), transparent 40%),
                radial-gradient(circle at 80% 80%, rgba(233, 198, 111, 0.15), transparent 40%);
            z-index: -1;
        }

        .container {
            text-align: center;
            padding: 3rem;
            max-width: 600px;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 2.5rem;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.8);
            animation: fadeIn 1.2s ease-out;
            position: relative;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: -2px; left: -2px; right: -2px; bottom: -2px;
            background: linear-gradient(135deg, var(--primary), transparent, var(--secondary));
            border-radius: 2.5rem;
            z-index: -1;
            opacity: 0.2;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .icon-box {
            width: 110px;
            height: 110px;
            background: linear-gradient(135deg, var(--primary), #a00000);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2.5rem;
            box-shadow: 0 15px 40px rgba(128, 0, 0, 0.5);
            border: 3px solid rgba(255, 255, 255, 0.1);
        }

        h1 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 1.2rem;
            background: linear-gradient(to right, #fff, var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }

        p {
            font-size: 1.15rem;
            color: rgba(255,255,255,0.7);
            line-height: 1.7;
            margin-bottom: 2.5rem;
        }

        .loader {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 1.5rem;
        }

        .dot {
            width: 14px;
            height: 14px;
            background: var(--secondary);
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out both;
            box-shadow: 0 0 15px rgba(233, 198, 111, 0.4);
        }

        .dot:nth-child(1) { animation-delay: -0.32s; }
        .dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1.0); }
        }

        .badge {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            background: rgba(128, 0, 0, 0.2);
            color: var(--secondary);
            border-radius: 100px;
            font-size: 0.9rem;
            font-weight: 700;
            border: 1px solid rgba(233, 198, 111, 0.3);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="bg-glow"></div>
    <div class="container">
        <div class="icon-box">
            <svg width="45" height="45" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
            </svg>
        </div>
        <div class="badge">System Upgrade</div>
        <h1>We're Upgrading</h1>
        <p>We are working hard to make this site more user friendly and premium. Please wait while we roll out exciting new updates for your assessment experience.</p>
        
        <div class="loader">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
        <div style="font-size: 0.85rem; font-weight: 600; color: rgba(255,255,255,0.4); letter-spacing: 1px;">LAKSHYA PLATFORM v<?php echo APP_VERSION; ?></div>
    </div>
</body>
</html>
