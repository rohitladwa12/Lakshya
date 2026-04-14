<?php
/**
 * Manage Aptitude Questions
 * List, Edit, and Delete questions functionality
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_DEPT_COORDINATOR);
$fullName = getFullName();

// Fetch companies for filter dropdown
$db = getDB();
$stmt = $db->query("SELECT DISTINCT company_name FROM manual_aptitude_questions ORDER BY company_name");
$companies = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Aptitude - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #D4AF37;
            --text-main: #1e293b;
            --bg-light: #f8fafc;
            --white: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        body { font-family: 'Outfit', sans-serif; background: var(--bg-light); color: var(--text-main); margin: 0; }
        
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-size: 28px; font-weight: 800; color: var(--primary-maroon); display: flex; align-items: center; gap: 12px; }
        .btn-add { background: var(--primary-maroon); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.2s; }
        .btn-add:hover { background: #600000; transform: translateY(-2px); }

        .toolbar { background: white; padding: 20px; border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; }
        .form-control { padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; }
        .search-box { flex: 1; min-width: 250px; }
        
        .question-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: var(--shadow); border-left: 5px solid var(--primary-gold); transition: 0.2s; position: relative; }
        .question-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        
        .q-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .q-company { background: #fffbeb; color: #b45309; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .q-date { color: #94a3b8; font-size: 12px; }
        
        .q-text { font-size: 16px; font-weight: 600; line-height: 1.5; margin-bottom: 15px; color: #334155; }
        
        .q-actions { border-top: 1px solid #f1f5f9; padding-top: 15px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; border: none; cursor: pointer; transition: 0.2s; color: white; }
        .btn-edit { background: #3b82f6; } .btn-edit:hover { background: #2563eb; }
        .btn-delete { background: #ef4444; } .btn-delete:hover { background: #dc2626; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background: white; margin: 5vh auto; padding: 30px; border-radius: 16px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; position: relative; animation: slideDown 0.3s; }
        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .close { position: absolute; right: 25px; top: 20px; font-size: 24px; cursor: pointer; color: #94a3b8; }
        
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; }
        .full-width { width: 100%; }
        
        .loading-state { text-align: center; padding: 40px; color: #94a3b8; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container">
        <div class="header">
            <div class="page-title"><i class="fas fa-tasks"></i> Manage Aptitude Questions</div>
            <a href="add_aptitude.php" class="btn-add"><i class="fas fa-plus"></i> Add New</a>
        </div>

        <div class="toolbar">
            <select id="filterCompany" class="form-control" onchange="loadQuestions()">
                <option value="">All Companies</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="searchBox" class="form-control search-box" placeholder="Search question text..." onkeyup="debounce(loadQuestions, 500)()">
        </div>

        <div id="questionsList">
            <div class="loading-state"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading questions...</div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 style="margin-bottom: 20px; color: var(--primary-maroon);">Edit Question</h3>
            <form id="editForm">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="action" value="update_question">
                
                <div class="form-group">
                    <label class="form-label">Company</label>
                    <input type="text" name="company" id="editCompany" class="form-control full-width" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Question</label>
                    <textarea name="question" id="editQuestion" class="form-control full-width" rows="3" required></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group"><input type="text" name="option_a" id="editOptA" class="form-control full-width" placeholder="Option A" required></div>
                    <div class="form-group"><input type="text" name="option_b" id="editOptB" class="form-control full-width" placeholder="Option B" required></div>
                    <div class="form-group"><input type="text" name="option_c" id="editOptC" class="form-control full-width" placeholder="Option C" required></div>
                    <div class="form-group"><input type="text" name="option_d" id="editOptD" class="form-control full-width" placeholder="Option D" required></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Correct Option</label>
                    <select name="correct_option" id="editCorrect" class="form-control full-width" required>
                        <option value="A">Option A</option>
                        <option value="B">Option B</option>
                        <option value="C">Option C</option>
                        <option value="D">Option D</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Explanation</label>
                    <textarea name="explanation" id="editExpl" class="form-control full-width" rows="3"></textarea>
                </div>

                <button type="submit" class="btn-add full-width" style="border: none; cursor: pointer; font-size: 16px;">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        let allQuestions = [];

        document.addEventListener('DOMContentLoaded', loadQuestions);

        async function loadQuestions() {
            const list = document.getElementById('questionsList');
            const company = document.getElementById('filterCompany').value;
            const search = document.getElementById('searchBox').value;

            list.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

            try {
                const res = await fetch('aptitude_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'get_all_questions',
                        company: company,
                        search: search
                    })
                });
                const data = await res.json();
                
                if (data.success) {
                    allQuestions = data.questions;
                    renderQuestions(data.questions);
                } else {
                    list.innerHTML = '<div class="loading-state">Error loading questions.</div>';
                }
            } catch (e) {
                list.innerHTML = '<div class="loading-state">Connection failed.</div>';
            }
        }

        function renderQuestions(questions) {
            const list = document.getElementById('questionsList');
            if (questions.length === 0) {
                list.innerHTML = '<div class="loading-state">No questions found.</div>';
                return;
            }

            list.innerHTML = questions.map(q => `
                <div class="question-card">
                    <div class="q-header">
                        <span class="q-company">${escapeHtml(q.company_name)}</span>
                        <span class="q-date">${new Date(q.created_at).toLocaleDateString()}</span>
                    </div>
                    <div class="q-text">${escapeHtml(q.question_text)}</div>
                    <div style="font-size: 13px; color: #64748b; margin-bottom: 10px;">
                        <strong>Correct:</strong> Option ${q.correct_option}
                    </div>
                    <div class="q-actions">
                        <button class="btn-icon btn-edit" onclick="openEdit(${q.id})" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                        <button class="btn-icon btn-delete" onclick="deleteQuestion(${q.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `).join('');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Edit Functions
        function openEdit(id) {
            const q = allQuestions.find(item => item.id == id);
            if (!q) return;

            document.getElementById('editId').value = q.id;
            document.getElementById('editCompany').value = q.company_name;
            document.getElementById('editQuestion').value = q.question_text;
            document.getElementById('editOptA').value = q.option_a;
            document.getElementById('editOptB').value = q.option_b;
            document.getElementById('editOptC').value = q.option_c;
            document.getElementById('editOptD').value = q.option_d;
            document.getElementById('editCorrect').value = q.correct_option;
            document.getElementById('editExpl').value = q.explanation;

            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        document.getElementById('editForm').onsubmit = async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const res = await fetch('aptitude_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                
                if (result.success) {
                    closeModal();
                    loadQuestions();
                    alert('Updated successfully!');
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (err) {
                alert('Update failed');
            }
        };

        // Delete Function
        async function deleteQuestion(id) {
            if (!confirm('Are you sure you want to delete this question? This action cannot be undone.')) return;

            try {
                const res = await fetch('aptitude_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_question', id: id })
                });
                const result = await res.json();
                
                if (result.success) {
                    loadQuestions();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (err) {
                alert('Delete failed');
            }
        }

        // Debounce utility
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>
