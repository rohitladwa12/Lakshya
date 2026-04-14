<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_DEPT_COORDINATOR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Coding Problems - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #D4AF37;
            --text-main: #1e293b;
            --bg-light: #f8fafc;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg-light); color: var(--text-main); }
        
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h2 { font-size: 28px; color: var(--primary-maroon); font-weight: 800; }
        
        .toolbar { 
            background: white; padding: 20px; border-radius: 16px; margin-bottom: 30px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; gap: 15px; flex-wrap: wrap;
        }
        .search-box { flex: 1; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-family: inherit; }
        .filter-select { padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; min-width: 150px; font-family: inherit; }

        .problem-list { display: grid; gap: 15px; }
        .problem-card { 
            background: white; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; 
            display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: center; transition: 0.2s;
        }
        .problem-card:hover { border-color: var(--primary-gold); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        
        .p-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-Easy { background: #ecfdf5; color: #059669; }
        .badge-Medium { background: #fffbeb; color: #b45309; }
        .badge-Hard { background: #fef2f2; color: #b91c1c; }

        .actions { display: flex; gap: 10px; }
        .btn-icon { 
            width: 36px; height: 36px; border-radius: 8px; border: none; cursor: pointer; 
            display: flex; align-items: center; justify-content: center; transition: 0.2s;
        }
        .btn-edit { background: #eff6ff; color: #2563eb; }
        .btn-edit:hover { background: #2563eb; color: white; }
        .btn-del { background: #fef2f2; color: #ef4444; }
        .btn-del:hover { background: #ef4444; color: white; }

        .btn-add { 
            background: var(--primary-maroon); color: white; text-decoration: none; 
            padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 14px; 
        }

        /* Modal */
        .modal { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.5); display: none; align-items: center; 
            justify-content: center; z-index: 3000; padding: 20px;
        }
        .modal-content { 
            background: white; width: 100%; max-width: 800px; max-height: 90vh; 
            overflow-y: auto; border-radius: 20px; padding: 30px; position: relative; 
        }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 24px; cursor: pointer; color: #64748b; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container">
        <div class="header">
            <div>
                <h2>Manage Coding Problems</h2>
                <p style="color: #64748b;">Edit or remove existing problems</p>
            </div>
            <a href="add_coding.php" class="btn-add"><i class="fas fa-plus"></i> Add New</a>
        </div>

        <div class="toolbar">
            <input type="text" id="search" class="search-box" placeholder="Search by title or category..." oninput="debounceLoad()">
            <select id="diffFilter" class="filter-select" onchange="loadProblems()">
                <option value="">All Difficulties</option>
                <option value="Easy">Easy</option>
                <option value="Medium">Medium</option>
                <option value="Hard">Hard</option>
            </select>
        </div>

        <div id="problemList" class="problem-list">
            <div style="text-align: center; padding: 40px; color: #94a3b8;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h3 style="margin-bottom: 20px; color: var(--primary-maroon);">Edit Problem</h3>
            <form id="editForm" class="form-grid">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="action" value="update_problem">
                
                <div style="margin-bottom: 15px;">
                    <label>Title</label>
                    <input type="text" name="title" id="editTitle" class="search-box" style="width: 100%;">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label>Category</label>
                        <select name="category" id="editCategory" class="filter-select" style="width: 100%;">
                            <option>Arrays</option>
                            <option>Strings</option>
                            <option>Linked Lists</option>
                            <option>Trees</option>
                            <option>DP</option>
                            <option>Sorting</option>
                            <option>Graphs</option>
                            <option>Recursion</option>
                        </select>
                    </div>
                    <div>
                        <label>Difficulty</label>
                        <select name="difficulty" id="editDifficulty" class="filter-select" style="width: 100%;">
                            <option value="Easy">Easy</option>
                            <option value="Medium">Medium</option>
                            <option value="Hard">Hard</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 15px;">
                    <label>Statement</label>
                    <textarea name="problem_statement" id="editStatement" class="search-box" rows="5" style="width: 100%;"></textarea>
                </div>

                <!-- Hidden fields for other columns to simplify UI here, or expand modal if needed -->
                <!-- Ideally, this modal should match the full add form. For brevity, I'm including key fields. -->
                <!-- Adding hidden inputs for complex fields not shown in this simple edit view to prevent data loss if the backend updates all fields -->
                <!-- Wait, the backend updates ALL fields. I must include all fields in the edit form or fetch current values. -->
                <!-- Expanding the modal to include all fields -->

                <div style="margin-bottom: 15px;">
                    <label>Constraints</label>
                    <textarea name="constraints" id="editConstraints" class="search-box" rows="2" style="width: 100%;"></textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label>Example Input</label>
                    <textarea name="example_input" id="editInput" class="search-box" style="width: 100%;"></textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label>Example Output</label>
                    <textarea name="example_output" id="editOutput" class="search-box" style="width: 100%;"></textarea>
                </div>

                <div style="margin-bottom: 15px;">
                    <label>Explanation</label>
                    <textarea name="concept_explanation" id="editExplanation" class="search-box" rows="3" style="width: 100%;"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <input type="text" name="time_complexity" id="editTime" placeholder="Time Complexity" class="search-box">
                    <input type="text" name="space_complexity" id="editSpace" placeholder="Space Complexity" class="search-box">
                </div>

                <button type="submit" class="btn-add" style="width: 100%; border: none; cursor: pointer;">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        let allProblems = [];
        let debounceTimer;

        async function loadProblems() {
            const list = document.getElementById('problemList');
            const search = document.getElementById('search').value;
            const diff = document.getElementById('diffFilter').value;

            try {
                const res = await fetch('coding_handler', {
                    method: 'POST',
                    body: JSON.stringify({ action: 'get_all_problems', search: search, difficulty: diff })
                });
                const data = await res.json();
                
                if (data.success) {
                    allProblems = data.problems;
                    render(data.problems);
                }
            } catch (e) { console.error(e); }
        }

        function render(problems) {
            const list = document.getElementById('problemList');
            if (problems.length === 0) {
                list.innerHTML = '<div style="text-align: center; color: #94a3b8; padding: 20px;">No problems found.</div>';
                return;
            }

            list.innerHTML = problems.map(p => `
                <div class="problem-card">
                    <div>
                        <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 8px;">
                            <span class="p-badge badge-${p.difficulty}">${p.difficulty}</span>
                            <span style="font-size: 13px; color: #64748b; font-weight: 500;">${p.category}</span>
                        </div>
                        <h3 style="font-size: 18px; color: #1e293b; margin-bottom: 5px;">${p.title}</h3>
                        <p style="color: #94a3b8; font-size: 14px; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;">${p.problem_statement}</p>
                    </div>
                    <div class="actions">
                        <button class="btn-icon btn-edit" onclick="openEdit(${p.id})"><i class="fas fa-pen"></i></button>
                        <button class="btn-icon btn-del" onclick="deleteProblem(${p.id})"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            `).join('');
        }

        function debounceLoad() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadProblems, 300);
        }

        function openEdit(id) {
            const p = allProblems.find(x => x.id == id);
            if (!p) return;

            document.getElementById('editId').value = p.id;
            document.getElementById('editTitle').value = p.title;
            document.getElementById('editCategory').value = p.category;
            document.getElementById('editDifficulty').value = p.difficulty;
            document.getElementById('editStatement').value = p.problem_statement;
            document.getElementById('editConstraints').value = p.constraints || '';
            document.getElementById('editInput').value = p.example_input || '';
            document.getElementById('editOutput').value = p.example_output || '';
            document.getElementById('editExplanation').value = p.concept_explanation || '';
            document.getElementById('editTime').value = p.time_complexity || '';
            document.getElementById('editSpace').value = p.space_complexity || '';

            document.getElementById('editModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        document.getElementById('editForm').onsubmit = async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const res = await fetch('coding_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                
                if (result.success) {
                    closeModal();
                    loadProblems();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (err) { alert('Update failed'); }
        };

        async function deleteProblem(id) {
            if (!confirm('Permanently delete this problem?')) return;
            
            try {
                const res = await fetch('coding_handler', {
                    method: 'POST',
                    body: JSON.stringify({ action: 'delete_problem', id: id })
                });
                const result = await res.json();
                
                if (result.success) loadProblems();
                else alert('Error: ' + result.message);
            } catch (err) { alert('Delete failed'); }
        }

        loadProblems();
    </script>
</body>
</html>
