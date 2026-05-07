<?php
require_once __DIR__ . '/../config/bootstrap.php';
// Skip maintenance check for this page to avoid infinite redirect
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Maintenance | Lakshya</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #800000; /* Maroon */
            --secondary: #e9c66f; /* Gold */
            --bg-bright: #ffffff;
            --text-main: #2d3436;
            --accent-soft: rgba(233, 198, 111, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-bright);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-image: 
                radial-gradient(circle at 100% 0%, var(--accent-soft) 0%, transparent 40%),
                radial-gradient(circle at 0% 100%, rgba(128, 0, 0, 0.03) 0%, transparent 40%);
        }

        .container {
            text-align: center;
            padding: 4rem;
            max-width: 650px;
            width: 90%;
            background: #ffffff;
            border-radius: 40px;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0,0,0,0.03);
            position: relative;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .icon-box {
            width: 100px;
            height: 100px;
            background: var(--accent-soft);
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2.5rem;
            border: 2px solid var(--secondary);
            color: var(--primary);
            font-size: 3rem;
            position: relative;
        }

        .icon-box::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border: 2px solid var(--secondary);
            border-radius: 28px;
            top: 5px;
            left: 5px;
            z-index: -1;
            opacity: 0.3;
        }

        h1 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 1.2rem;
            color: var(--primary);
            letter-spacing: -1.5px;
        }

        .info-msg {
            font-size: 1.25rem;
            line-height: 1.6;
            color: #636e72;
            margin-bottom: 3rem;
            font-weight: 400;
        }

        .info-msg strong {
            color: var(--primary);
            font-weight: 700;
        }

        .loader-dots {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 2rem;
        }

        .dot {
            width: 12px;
            height: 12px;
            background: var(--secondary);
            border-radius: 50%;
            animation: pulse 1.5s infinite ease-in-out;
        }

        .dot:nth-child(2) { background: var(--primary); animation-delay: 0.2s; }
        .dot:nth-child(3) { background: var(--secondary); animation-delay: 0.4s; }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.3); opacity: 1; }
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1.2rem;
            background: var(--primary);
            color: white;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
        }

        .footer-tag {
            font-size: 0.85rem;
            color: #b2bec3;
            font-weight: 600;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="status-badge">Scheduled Maintenance</div>
        
        <div class="icon-box">
            <i class="fas fa-tools"></i>
        </div>

        <h1>System Enhancement</h1>
        
        <p class="info-msg">
            LAKSHYA is currently undergoing <strong>scheduled maintenance</strong> to enhance our infrastructure and processing capabilities. <br>
            We are working to ensure a seamless assessment experience. Service will be restored shortly.
        </p>

        <div class="loader-dots">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>

        <div class="footer-tag">LAKSHYA PLATFORM v<?php echo APP_VERSION; ?> | OFFICIAL PLACEMENT ECOSYSTEM</div>
    </div>
</body>
</html>

