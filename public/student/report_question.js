(function() {
    // Inject styles for the modal dynamically if not already injected
    if (!document.getElementById('question-report-styles')) {
        const style = document.createElement('style');
        style.id = 'question-report-styles';
        style.innerHTML = `
            .report-modal-overlay {
                position: fixed;
                top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0, 0, 0, 0.75);
                backdrop-filter: blur(5px);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            .report-modal-overlay.active {
                opacity: 1;
            }
            .report-modal {
                background: #1e1e1e;
                color: #f4f4f4;
                border: 1px solid #800000;
                width: 90%;
                max-width: 500px;
                border-radius: 16px;
                padding: 24px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                transform: scale(0.9);
                transition: transform 0.3s ease;
                font-family: 'Outfit', sans-serif;
            }
            .report-modal-overlay.active .report-modal {
                transform: scale(1);
            }
            .report-modal h3 {
                color: #e9c66f;
                margin-top: 0;
                margin-bottom: 15px;
                font-size: 1.3rem;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .report-modal .form-group {
                margin-bottom: 15px;
            }
            .report-modal label {
                display: block;
                margin-bottom: 5px;
                font-size: 0.9rem;
                color: #aaa;
            }
            .report-modal .q-display {
                background: rgba(255,255,255,0.05);
                padding: 12px;
                border-radius: 8px;
                font-size: 0.95rem;
                max-height: 100px;
                overflow-y: auto;
                border: 1px solid rgba(255,255,255,0.1);
                margin-bottom: 15px;
            }
            .report-modal select, .report-modal textarea {
                width: 100%;
                background: #2b2b2b;
                color: #fff;
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 8px;
                padding: 10px;
                font-size: 0.95rem;
                box-sizing: border-box;
            }
            .report-modal select:focus, .report-modal textarea:focus {
                outline: none;
                border-color: #800000;
            }
            .report-modal .btn-actions {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                margin-top: 20px;
            }
            .report-modal .btn-report {
                padding: 10px 20px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                border: none;
            }
            .report-modal .btn-report.submit {
                background: #800000;
                color: white;
            }
            .report-modal .btn-report.submit:hover {
                background: #a00000;
            }
            .report-modal .btn-report.cancel {
                background: transparent;
                color: #aaa;
                border: 1px solid rgba(255,255,255,0.1);
            }
            .report-modal .btn-report.cancel:hover {
                background: rgba(255,255,255,0.05);
                color: white;
            }
        `;
        document.head.appendChild(style);
    }

    // Main reporting function
    window.openQuestionReportModal = function(data) {
        // data: { test_type, test_id, question_text, options, correct_answer, user_answer }
        
        // Remove existing modal if any
        const existing = document.getElementById('report-question-modal-container');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'report-question-modal-container';
        overlay.className = 'report-modal-overlay';
        
        overlay.innerHTML = `
            <div class="report-modal">
                <h3><i class="fas fa-exclamation-triangle"></i> Report Question Issue</h3>
                
                <div class="form-group">
                    <label>Question</label>
                    <div class="q-display">${escapeHTML(data.question_text)}</div>
                </div>

                <div class="form-group">
                    <label for="report-issue-type">What is the issue?</label>
                    <select id="report-issue-type">
                        <option value="incorrect_key">Incorrect Answer Key</option>
                        <option value="no_correct_option">No Correct Option</option>
                        <option value="typo">Typo or Text Issue</option>
                        <option value="other">Other / Explanation Error</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="report-comment">Explain the Issue (optional)</label>
                    <textarea id="report-comment" rows="3" placeholder="Explain why you think the question is incorrect..."></textarea>
                </div>

                <div class="btn-actions">
                    <button class="btn-report cancel" id="report-cancel-btn">Cancel</button>
                    <button class="btn-report submit" id="report-submit-btn">Submit Report</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        // Animate open
        setTimeout(() => overlay.classList.add('active'), 10);

        // Handlers
        const close = () => {
            overlay.classList.remove('active');
            setTimeout(() => overlay.remove(), 300);
        };

        document.getElementById('report-cancel-btn').addEventListener('click', close);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
        });

        document.getElementById('report-submit-btn').addEventListener('click', async () => {
            const issueType = document.getElementById('report-issue-type').value;
            const comment = document.getElementById('report-comment').value;
            
            const submitBtn = document.getElementById('report-submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            try {
                const payload = new URLSearchParams();
                payload.append('test_type', data.test_type);
                payload.append('test_id', data.test_id);
                payload.append('question_text', data.question_text);
                if (data.options) payload.append('options', JSON.stringify(data.options));
                if (data.correct_answer !== undefined && data.correct_answer !== null) payload.append('correct_answer', data.correct_answer);
                if (data.user_answer !== undefined && data.user_answer !== null) payload.append('user_answer', data.user_answer);
                payload.append('issue_type', issueType);
                payload.append('comment', comment);
                if (window.CSRF_TOKEN) {
                    payload.append('csrf_token', window.CSRF_TOKEN);
                }

                const reportHeaders = {
                    'Content-Type': 'application/x-www-form-urlencoded'
                };
                if (window.CSRF_TOKEN) {
                    reportHeaders['X-CSRF-TOKEN'] = window.CSRF_TOKEN;
                }

                const response = await fetch('report_question_handler.php', {
                    method: 'POST',
                    headers: reportHeaders,
                    body: payload.toString()
                });

                const result = await response.json();
                if (result.success) {
                    alert('Thank you! Your report has been submitted for review.');
                    close();
                } else {
                    alert('Failed: ' + result.message);
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Submit Report';
                }
            } catch (err) {
                console.error(err);
                alert('Connection error. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerText = 'Submit Report';
            }
        });
    };

    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }
})();
