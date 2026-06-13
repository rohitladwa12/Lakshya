<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

$db = getDB();
$userId = getUserId();
$usn = getUsername(); // Student USN

$driveId = isset($_GET['drive_id']) ? (int)$_GET['drive_id'] : 0;
$roundType = isset($_GET['round_type']) ? $_GET['round_type'] : '';

if (!$driveId || !in_array($roundType, ['Aptitude', 'Technical', 'HR'])) {
    die("Invalid drive or round parameters.");
}

// Fetch drive details
$stmt = $db->prepare("
        SELECT cd.*, jp.title AS job_title, jp.id as job_id, c.name as company_name
    FROM campus_drives cd
    JOIN job_postings jp ON cd.job_id = jp.id
    LEFT JOIN companies c ON jp.company_id = c.id
    WHERE cd.id = ?
");
$stmt->execute([$driveId]);
$drive = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$drive) {
    die("Recruitment drive not found.");
}

// Check if round is active
$activeColumn = strtolower($roundType) . '_active';
if (!$drive[$activeColumn]) {
    die("This assessment round is currently disabled by the placement officer.");
}

// Enforce: only applied students can access this drive
$stmt = $db->prepare("
    SELECT COUNT(*) FROM job_applications 
    WHERE job_id = ? AND student_id = ?
");
$stmt->execute([$drive['job_id'], $usn]);
$hasApplied = $stmt->fetchColumn() > 0;

if (!$hasApplied) {
    die("Access denied. Only students who have applied for this job posting can access this recruitment drive.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $roundType; ?> Assessment | <?php echo htmlspecialchars($drive['drive_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="report_question.js?v=<?php echo APP_VERSION; ?>"></script>
    <style>
        :root {
            --brand: #7C0000;
            --brand-dark: #4A0000;
            --brand-light: #F9F1F1;
            --gold: #C9972C;
            --bg-light: #f3f4f6;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --success-color: #166534;
        }

        body {
            font-family: 'Outfit', -apple-system, sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Top Header */
        .exam-header {
            background: #fff;
            border-bottom: 1px solid var(--border-color);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            box-sizing: border-box;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .exam-title-box {
            display: flex;
            flex-direction: column;
        }

        .exam-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--brand-dark);
            margin: 0;
        }

        .exam-subtitle {
            font-size: 12px;
            color: var(--text-muted);
            margin: 2px 0 0 0;
        }

        .timer-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fef2f2;
            color: var(--brand);
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            border: 1px solid #fee2e2;
        }

        .timer-box i {
            animation: pulse 1s infinite alternate;
        }

        @keyframes pulse {
            from { transform: scale(1); opacity: 0.8; }
            to { transform: scale(1.1); opacity: 1; }
        }

        /* Main Area */
        .exam-body {
            flex: 1;
            display: flex;
            overflow: hidden;
            position: relative;
        }

        /* Loading Overlay */
        .loader-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #fff;
            z-index: 100;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--brand-light);
            border-top: 4px solid var(--brand);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Left Side: Question Pane */
        .question-pane {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        .question-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 30px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.03);
            margin-bottom: 25px;
        }

        .question-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 12px;
        }

        .question-number {
            font-size: 14px;
            font-weight: 700;
            color: var(--brand);
            text-transform: uppercase;
        }

        .question-category {
            font-size: 12px;
            background: #eff6ff;
            color: #1e40af;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .question-text {
            font-size: 18px;
            font-weight: 600;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .options-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .option-item {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .option-item:hover {
            border-color: var(--brand);
            background: var(--brand-light);
        }

        .option-item.selected {
            border-color: var(--brand);
            background: var(--brand-light);
            font-weight: 700;
        }

        .option-radio {
            margin-right: 15px;
            accent-color: var(--brand);
            transform: scale(1.1);
        }

        .option-text {
            font-size: 15px;
        }

        /* Right Side: Navigation Palette */
        .palette-pane {
            width: 320px;
            background: #fff;
            border-left: 1px solid var(--border-color);
            padding: 24px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .palette-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-dark);
            margin-top: 0;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 25px;
        }

        .q-btn {
            aspect-ratio: 1;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: #fff;
            color: var(--text-dark);
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .q-btn:hover {
            border-color: var(--text-dark);
        }

        .q-btn.active {
            background: var(--brand);
            color: #fff;
            border-color: var(--brand);
        }

        .q-btn.answered {
            background: #e8fbee;
            color: var(--success-color);
            border-color: #b7ebc6;
        }

        .q-btn.answered.active {
            background: var(--brand);
            color: #fff;
            border-color: var(--brand);
        }

        .legend-list {
            margin-top: auto;
            font-size: 13px;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            gap: 10px;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .legend-indicator {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .legend-indicator.answered {
            background: #e8fbee;
            border-color: #b7ebc6;
        }

        .legend-indicator.unanswered {
            background: #fff;
        }

        .legend-indicator.active {
            background: var(--brand);
            border-color: var(--brand);
        }

        /* Bottom Control Bar */
        .exam-footer {
            background: #fff;
            border-top: 1px solid var(--border-color);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            box-sizing: border-box;
        }

        .btn-control {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid var(--border-color);
            background: #fff;
            color: var(--text-dark);
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-control:hover {
            background: #f9fafb;
            border-color: var(--text-dark);
        }

        .btn-control.btn-primary {
            background: var(--brand);
            color: #fff;
            border-color: var(--brand);
            box-shadow: 0 4px 10px rgba(124,0,0,0.1);
        }

        .btn-control.btn-primary:hover {
            background: var(--brand-dark);
            border-color: var(--brand-dark);
        }

        .btn-control.btn-success {
            background: var(--success-color);
            color: #fff;
            border-color: var(--success-color);
            box-shadow: 0 4px 10px rgba(22,101,52,0.1);
        }

        .btn-control.btn-success:hover {
            background: #14532d;
            border-color: #14532d;
        }
    </style>
</head>
<body>

    <div class="exam-header">
        <div class="exam-title-box">
            <h1 class="exam-title"><?php echo htmlspecialchars($drive['drive_name']); ?></h1>
            <p class="exam-subtitle"><?php echo $roundType; ?> Round &bull; Assessment Portal</p>
        </div>
        <div class="timer-box">
            <i class="fas fa-clock"></i>
            <span id="timerText">--:--</span>
        </div>
    </div>

    <div class="exam-body">
        
        <div class="loader-overlay" id="loaderOverlay">
            <div class="spinner"></div>
            <strong style="color: var(--brand-dark);">Generating assessment questions via AI...</strong>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0;">Please wait, this might take a minute.</p>
        </div>

        <div class="question-pane">
            <div class="question-card" id="questionCard" style="display: none;">
                <div class="question-meta">
                    <div style="display:flex; align-items:center; gap: 10px;">
                        <span class="question-number" id="questionNumLabel">Question 1 of --</span>
                        <span class="question-category" id="questionCategoryLabel">General</span>
                    </div>
                    <span class="report-q" style="cursor:pointer; font-weight:600; color:var(--brand);" onclick="reportCurrentQuestion()"><i class="fas fa-flag"></i> Report Issue</span>
                </div>
                <div class="question-text" id="questionText">--</div>
                
                <div class="options-list" id="optionsList">
                    <!-- Loaded dynamically -->
                </div>
            </div>
        </div>

        <div class="palette-pane">
            <h4 class="palette-title">Question Navigation</h4>
            <div class="grid-container" id="paletteGrid">
                <!-- Loaded dynamically -->
            </div>

            <div class="legend-list">
                <div class="legend-item">
                    <div class="legend-indicator active"></div>
                    <span>Current Active Question</span>
                </div>
                <div class="legend-item">
                    <div class="legend-indicator answered"></div>
                    <span>Answered Question</span>
                </div>
                <div class="legend-item">
                    <div class="legend-indicator unanswered"></div>
                    <span>Unanswered Question</span>
                </div>
            </div>
        </div>

    </div>

    <div class="exam-footer">
        <button class="btn-control" id="btnPrev" onclick="navigate(-1)"><i class="fas fa-chevron-left"></i> Previous</button>
        <div>
            <button class="btn-control" style="background: #fef2f2; color: var(--brand); border-color: #fca5a5; margin-right: 8px;" onclick="clearResponse()"><i class="fas fa-eraser"></i> Clear Choice</button>
            <button class="btn-control btn-primary" id="btnNext" onclick="navigate(1)">Save & Next <i class="fas fa-chevron-right"></i></button>
        </div>
        <button class="btn-control btn-success" id="btnSubmit" onclick="confirmSubmit()"><i class="fas fa-check-double"></i> Submit Test</button>
    </div>

    <script>
        window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        const driveId = <?php echo $driveId; ?>;
        const roundType = '<?php echo $roundType; ?>';
        
        let attemptId = null;
        let questions = [];
        let answers = []; // index-based student choices (0 to 3 or null)
        let currentIdx = 0;
        let remainingSeconds = 0;
        let timerInterval = null;

        window.reportCurrentQuestion = function() {
            const q = questions[currentIdx];
            window.openQuestionReportModal({
                test_type: 'campus_drive',
                test_id: driveId,
                question_text: q.question,
                options: q.options,
                correct_answer: q.answer,
                user_answer: answers[currentIdx]
            });
        };

        document.addEventListener('DOMContentLoaded', () => {
            loadAssessment();
        });

        function loadAssessment() {
            const formData = new FormData();
            formData.append('action', 'start_or_get_test');
            formData.append('drive_id', driveId);
            formData.append('round_type', roundType);
            formData.append('csrf_token', window.CSRF_TOKEN);

            fetch('student_drive_test_handler.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    attemptId = data.attempt_id;
                    questions = data.questions;
                    remainingSeconds = data.remaining_seconds;
                    
                    // Recover from session storage or default array of nulls
                    const storedAnswers = sessionStorage.getItem(`drive_ans_${attemptId}`);
                    if (storedAnswers) {
                        answers = JSON.parse(storedAnswers);
                    } else {
                        answers = Array(questions.length).fill(null);
                    }

                    // Hide Loader
                    document.getElementById('loaderOverlay').style.display = 'none';
                    document.getElementById('questionCard').style.display = 'block';

                    buildPalette();
                    renderQuestion();
                    startTimer();
                } else {
                    alert(data.message || 'Error loading assessment.');
                    window.location.href = 'student_drive.php?drive_id=' + driveId;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Connection failure or error setting up exam environment.');
                window.location.href = 'student_drive.php?drive_id=' + driveId;
            });
        }

        function buildPalette() {
            const grid = document.getElementById('paletteGrid');
            grid.innerHTML = '';
            
            questions.forEach((q, idx) => {
                const btn = document.createElement('button');
                btn.className = 'q-btn';
                btn.id = `qbtn_${idx}`;
                btn.textContent = idx + 1;
                btn.onclick = () => {
                    saveCurrentResponse();
                    currentIdx = idx;
                    renderQuestion();
                };
                grid.appendChild(btn);
            });
        }

        function renderQuestion() {
            if (questions.length === 0) return;

            const q = questions[currentIdx];
            
            // Highlight active button in palette
            document.querySelectorAll('.q-btn').forEach((btn, idx) => {
                btn.className = 'q-btn';
                if (answers[idx] !== null) btn.classList.add('answered');
                if (idx === currentIdx) btn.classList.add('active');
            });

            document.getElementById('questionNumLabel').textContent = `Question ${currentIdx + 1} of ${questions.length}`;
            document.getElementById('questionCategoryLabel').textContent = q.category || 'General';
            document.getElementById('questionText').textContent = q.question;

            const list = document.getElementById('optionsList');
            list.innerHTML = '';

            q.options.forEach((opt, idx) => {
                const item = document.createElement('div');
                item.className = 'option-item';
                if (answers[currentIdx] === idx) item.classList.add('selected');

                item.onclick = () => {
                    selectOption(idx);
                };

                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'option_radio';
                radio.className = 'option-radio';
                radio.checked = (answers[currentIdx] === idx);

                const textSpan = document.createElement('span');
                textSpan.className = 'option-text';
                textSpan.textContent = opt;

                item.appendChild(radio);
                item.appendChild(textSpan);
                list.appendChild(item);
            });

            // Adjust navigation buttons
            document.getElementById('btnPrev').disabled = (currentIdx === 0);
            
            const btnNext = document.getElementById('btnNext');
            if (currentIdx === questions.length - 1) {
                btnNext.innerHTML = 'Finish review <i class="fas fa-flag-checkered"></i>';
            } else {
                btnNext.innerHTML = 'Save & Next <i class="fas fa-chevron-right"></i>';
            }
        }

        function selectOption(optIdx) {
            answers[currentIdx] = optIdx;
            sessionStorage.setItem(`drive_ans_${attemptId}`, JSON.stringify(answers));
            
            // Refresh selections visually
            const items = document.querySelectorAll('.option-item');
            items.forEach((item, idx) => {
                item.className = 'option-item';
                if (idx === optIdx) item.classList.add('selected');
                
                const radio = item.querySelector('.option-radio');
                if (radio) radio.checked = (idx === optIdx);
            });

            // Refresh palette immediately
            const pbtn = document.getElementById(`qbtn_${currentIdx}`);
            if (pbtn) pbtn.classList.add('answered');
        }

        function clearResponse() {
            answers[currentIdx] = null;
            sessionStorage.setItem(`drive_ans_${attemptId}`, JSON.stringify(answers));
            renderQuestion();
        }

        function saveCurrentResponse() {
            // Options are auto-saved on click, this handles session persist safeguards
            sessionStorage.setItem(`drive_ans_${attemptId}`, JSON.stringify(answers));
        }

        function navigate(direction) {
            saveCurrentResponse();
            const nextIdx = currentIdx + direction;
            if (nextIdx >= 0 && nextIdx < questions.length) {
                currentIdx = nextIdx;
                renderQuestion();
            } else if (nextIdx === questions.length) {
                // Prompt user to submit if they reached the end
                confirmSubmit();
            }
        }

        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);
            
            updateTimerDisplay();

            timerInterval = setInterval(() => {
                remainingSeconds--;
                updateTimerDisplay();

                if (remainingSeconds <= 0) {
                    clearInterval(timerInterval);
                    alert('Time limit reached! Your assessment will now be auto-submitted.');
                    submitAssessment(true);
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            
            const padMinutes = String(minutes).padStart(2, '0');
            const padSeconds = String(seconds).padStart(2, '0');
            
            document.getElementById('timerText').textContent = `${padMinutes}:${padSeconds}`;

            // Visual warning when time is less than 2 minutes
            if (remainingSeconds < 120) {
                document.querySelector('.timer-box').style.background = '#fef2f2';
                document.querySelector('.timer-box').style.color = '#dc2626';
                document.querySelector('.timer-box').style.borderColor = '#fca5a5';
            }
        }

        function confirmSubmit() {
            saveCurrentResponse();
            
            const unanswered = answers.filter(a => a === null).length;
            let msg = "Are you sure you want to submit your assessment?";
            if (unanswered > 0) {
                msg = `You have ${unanswered} unanswered questions. ${msg}`;
            }

            if (confirm(msg)) {
                submitAssessment(false);
            }
        }

        function submitAssessment(isAuto = false) {
            if (timerInterval) clearInterval(timerInterval);

            // Show submitting screen
            document.getElementById('loaderOverlay').style.display = 'flex';
            document.getElementById('loaderOverlay').querySelector('strong').textContent = 'Grading and saving attempts...';
            document.getElementById('loaderOverlay').querySelector('p').textContent = 'Do not close or refresh this page.';

            const submitData = new FormData();
            submitData.append('action', 'submit_test');
            submitData.append('attempt_id', attemptId);
            submitData.append('answers', JSON.stringify(answers));
            submitData.append('csrf_token', window.CSRF_TOKEN);

            fetch('student_drive_test_handler.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN },
                body: submitData
            })
            .then(res => res.json())
            .then(data => {
                sessionStorage.removeItem(`drive_ans_${attemptId}`);
                
                if (data.success) {
                    showReport(data);
                } else {
                    alert(data.message || 'Error saving answers.');
                    window.location.href = 'student_drive.php?drive_id=' + driveId;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Network connection lost. Please contact coordinator if score is not recorded.');
                window.location.href = 'student_drive.php?drive_id=' + driveId;
            });
        }

        function showReport(data) {
            document.querySelector('.exam-header').style.display = 'none';
            document.querySelector('.exam-footer').style.display = 'none';
            
            const body = document.querySelector('.exam-body');
            body.style.display = 'block';
            body.style.overflowY = 'auto';
            body.style.padding = '40px';
            body.style.background = '#fff';

            const questions = data.details.questions || [];
            const userAnswers = data.details.answers || [];

            let html = `
                <div style="max-width: 800px; margin: 0 auto;">
                    <div style="text-align: center; margin-bottom: 40px;">
                        <h1 style="color: var(--brand); margin-bottom: 10px;">Assessment Completed</h1>
                        <div style="font-size: 48px; font-weight: 900; color: ${data.score >= 60 ? 'var(--success-color)' : 'var(--brand)'};">${data.score}%</div>
                        <p style="color: var(--text-muted); font-size: 18px;">You answered ${data.correct_count} out of ${data.total_questions} questions correctly.</p>
                        <button class="btn-control btn-primary" onclick="window.location.href='student_drive.php?drive_id=' + driveId" style="margin-top: 20px;">Return to Drive <i class="fas fa-arrow-right"></i></button>
                    </div>
                    <hr style="border: none; border-top: 1px solid #eee; margin-bottom: 40px;">
            `;

            questions.forEach((q, idx) => {
                const uAnsIdx = userAnswers[idx];
                const cAnsIdx = q.answer;
                const isCorrect = (uAnsIdx === cAnsIdx);

                html += `
                    <div style="background: #fafafa; border: 1px solid #eee; border-radius: 12px; padding: 25px; margin-bottom: 25px; border-left: 5px solid ${isCorrect ? 'var(--success-color)' : '#ef4444'};">
                        <div style="font-weight: bold; margin-bottom: 15px; font-size: 16px;">${idx + 1}. ${q.question}</div>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                `;

                q.options.forEach((opt, oIdx) => {
                    let optStyle = "padding: 12px; border-radius: 8px; border: 1px solid #ddd; background: #fff;";
                    let icon = "";
                    
                    if (oIdx === cAnsIdx) {
                        optStyle = "padding: 12px; border-radius: 8px; border: 1px solid var(--success-color); background: #f0fdf4; font-weight: bold; color: var(--success-color);";
                        icon = '<i class="fas fa-check-circle" style="float: right;"></i>';
                    } else if (oIdx === uAnsIdx && !isCorrect) {
                        optStyle = "padding: 12px; border-radius: 8px; border: 1px solid #ef4444; background: #fef2f2; font-weight: bold; color: #ef4444;";
                        icon = '<i class="fas fa-times-circle" style="float: right;"></i>';
                    }

                    html += `<div style="${optStyle}">${opt} ${icon}</div>`;
                });

                html += `
                        </div>
                    </div>
                `;
            });

            html += `
                <div style="text-align: center; margin-top: 40px;">
                    <button class="btn-control btn-primary" onclick="window.location.href='student_drive.php?drive_id=' + driveId">Return to Drive Dashboard</button>
                </div>
                </div>
            `;

            body.innerHTML = html;
        }
    </script>
</body>
</html>
