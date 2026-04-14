<?php
/**
 * AI Certification Viva (Verification)
 */

require_once __DIR__ . '/../../config/bootstrap.php';
use App\Helpers\SessionFilterHelper;

requireLogin();

$userId = getUserId();
$username = getUsername();

// Handle POST from Dashboard
if (isPost() && (isset($_POST['id']))) {
    SessionFilterHelper::setFilters('certification_viva', [
        'id' => $_POST['id'] ?? 0
    ]);
    header("Location: certification_viva.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('certification_viva');
$portfolioId = $filters['id'] ?? 0;

// Fetch certification details
$stmt = getDB()->prepare("SELECT * FROM student_portfolio WHERE id = ? AND student_id = ?");
$stmt->execute([$portfolioId, $username]);
$cert = $stmt->fetch();

if (!$cert || $cert['category'] !== 'Certification') {
    header('Location: dashboard');
    exit;
}

$certTitle = $cert['title'];
$issuer = $cert['sub_title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certification Verification: <?php echo htmlspecialchars($certTitle); ?> - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #4a0000;
            --accent-gold: #D4AF37;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        * { margin:0; padding:0; box-sizing:border-box; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: #2d3436;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        input, textarea { -webkit-user-select: text; -moz-user-select: text; -ms-user-select: text; user-select: text; }

        .navbar {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .container {
            flex: 1;
            max-width: 800px;
            margin: 2rem auto;
            width: 90%;
            padding: 2.5rem;
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow);
            position: relative;
        }

        .viva-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .progress-steps {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 2rem;
        }

        .step-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            background: #eee;
            transition: 0.3s;
        }

        .step-dot.active { background: var(--primary-maroon); transform: scale(1.2); }
        .step-dot.completed { background: #00875a; }

        .question-box {
            font-size: 1.25rem;
            font-weight: 600;
            line-height: 1.5;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #fff9f9;
            border-left: 5px solid var(--primary-maroon);
            border-radius: 12px;
        }

        .answer-area {
            width: 100%;
            padding: 1.5rem;
            border: 2px solid #eee;
            border-radius: 16px;
            font-family: inherit;
            font-size: 1.05rem;
            resize: vertical;
            min-height: 150px;
            transition: 0.3s;
            margin-bottom: 1.5rem;
        }

        .answer-area:focus {
            outline: none;
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.05);
        }

        .btn-submit {
            background: var(--primary-maroon);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(128,0,0,0.2); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .spinner {
            width: 50px; height: 50px;
            border: 5px solid #eee;
            border-top: 5px solid var(--primary-maroon);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            display: flex; justify-content: center; align-items: center;
            flex-direction: column;
        }
        .hidden { display: none !important; }
    </style>
</head>
<body>

<div id="introOverlay" class="overlay">
    <div style="text-align: center; max-width: 600px; padding: 40px; background: white; border-radius: 24px; box-shadow: var(--shadow); border: 2px solid var(--primary-maroon);">
        <h1 style="color: var(--primary-maroon); margin-bottom: 20px;"><i class="fas fa-certificate"></i> Cert Verification</h1>
        <h2 style="margin-bottom: 15px;"><?php echo htmlspecialchars($certTitle); ?></h2>
        <p style="color: #666; margin-bottom: 30px;">
            This is a proctored AI session to verify your technical proficiency.<br><br>
            <strong>Certification:</strong> <?php echo htmlspecialchars($certTitle); ?><br>
            <strong>Issuer:</strong> <?php echo htmlspecialchars($issuer); ?><br>
            <strong>Format:</strong> 5 Technical Verification Questions
        </p>
        <button onclick="beginAssessment()" class="btn-submit" style="padding: 18px 50px; font-size: 1.2rem; width: 100%; justify-content: center;">START VERIFICATION</button>
    </div>
</div>

<div class="navbar">
    <h1><i class="fas fa-certificate"></i> Certification Verification</h1>
    <a href="dashboard" style="color: white; text-decoration: none;"><i class="fas fa-times"></i> Exit</a>
</div>

<div class="container" id="vivaContainer">
    <div id="loadingOverlay" class="overlay hidden" style="background: white; position: absolute; border-radius: 24px;">
        <div class="spinner" style="margin-bottom: 20px;"></div>
        <h2 id="loadingText">Preparing Verification Questions...</h2>
    </div>

    <div id="vivaView" style="display: none;">
        <div class="viva-header">
            <h3>Verifying: <?php echo htmlspecialchars($certTitle); ?></h3>
            <p style="color: #666; margin-top: 5px;">Question <span id="currentStepNum">1</span> of 5</p>
        </div>

        <div class="progress-steps" id="progressSteps">
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <div id="questionText" class="question-box">Loading question...</div>
        <textarea id="userAnswer" class="answer-area" placeholder="Provide a detailed technical answer..."></textarea>

        <button id="btnSubmit" class="btn-submit" onclick="submitAnswer()">
            Submit Answer <i class="fas fa-arrow-right"></i>
        </button>
    </div>

    <div id="resultsView" style="display: none; text-align: center;">
        <div style="font-size: 4rem; color: #00875a; margin-bottom: 1rem;"><i class="fas fa-award"></i></div>
        <h2>Verification Complete</h2>
        <div style="margin: 2rem 0;">
            <div style="font-size: 1.2rem; font-weight: 600;">Status: <span id="finalStatus">VERIFIED</span></div>
            <p id="finalFeedback" style="color: #666; margin-top: 10px;"></p>
        </div>
        <a href="dashboard" class="btn-submit" style="text-decoration: none; margin: 0 auto;">Return to Dashboard</a>
    </div>
</div>

<script>
    let isSessionActive = false;

    async function beginAssessment() {
        document.getElementById('introOverlay').classList.add('hidden');
        if (document.documentElement.requestFullscreen) {
            await document.documentElement.requestFullscreen().catch(e => console.log(e));
        }
        isSessionActive = true;
        startViva();
    }

    let questions = [];
    let answers = [];
    let currentIdx = 0;
    const portfolioId = <?php echo $portfolioId; ?>;

    async function startViva() {
        document.getElementById('loadingOverlay').classList.remove('hidden');
        try {
            const res = await fetch('certification_viva_handler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=generate_viva&portfolio_id=${portfolioId}`
            });
            const data = await res.json();
            if (data.success) {
                questions = data.questions;
                showQuestion();
                document.getElementById('loadingOverlay').classList.add('hidden');
                document.getElementById('vivaView').style.display = 'block';
            } else {
                alert(data.message);
                window.location.href = 'dashboard';
            }
        } catch (err) {
            alert('Connection error.');
            window.location.href = 'dashboard';
        }
    }

    function showQuestion() {
        document.getElementById('questionText').innerText = questions[currentIdx];
        document.getElementById('currentStepNum').innerText = currentIdx + 1;
        document.getElementById('userAnswer').value = '';
        const dots = document.querySelectorAll('.step-dot');
        dots.forEach((dot, idx) => {
            if (idx === currentIdx) dot.className = 'step-dot active';
            else if (idx < currentIdx) dot.className = 'step-dot completed';
            else dot.className = 'step-dot';
        });
        if (currentIdx === questions.length - 1) {
            document.getElementById('btnSubmit').innerHTML = 'Finish Verification <i class="fas fa-check"></i>';
        }
    }

    async function submitAnswer() {
        const ans = document.getElementById('userAnswer').value.trim();
        if (ans.length < 15) {
            alert('Please provide a more detailed answer.');
            return;
        }
        answers.push({ question: questions[currentIdx], answer: ans });
        if (currentIdx < questions.length - 1) {
            currentIdx++;
            showQuestion();
        } else {
            finishViva();
        }
    }

    async function finishViva() {
        isSessionActive = false;
        document.getElementById('vivaView').style.display = 'none';
        document.getElementById('loadingOverlay').classList.remove('hidden');
        document.getElementById('loadingText').innerText = 'AI is Evaluating...';

        try {
            const res = await fetch('certification_viva_handler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'submit_viva', portfolio_id: portfolioId, history: answers })
            });
            const data = await res.json();
            document.getElementById('loadingOverlay').classList.add('hidden');
            document.getElementById('resultsView').style.display = 'block';
            if (data.success) {
                document.getElementById('finalStatus').innerText = data.score >= 70 ? 'VERIFIED ✅' : 'NOT VERIFIED';
                document.getElementById('finalStatus').style.color = data.score >= 70 ? '#00875a' : '#e74c3c';
                document.getElementById('finalFeedback').innerText = data.feedback;
            } else {
                alert('Evaluation error.');
                window.location.href = 'dashboard';
            }
        } catch (err) {
            alert('Connection error.');
            window.location.href = 'dashboard';
        }
    }
</script>
</body>
</html>
