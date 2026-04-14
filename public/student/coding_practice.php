<?php
/**
 * Coding Practice - Problem Library
 * Educational coding platform for students
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coding Practice - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #FFD700;
            --white: #ffffff;
            --bg: #f8f9fa;
            --shadow: 0 4px 20px rgba(0,0,0,0.06);
            --shadow-hover: 0 10px 30px rgba(128, 0, 0, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Outfit', 'Inter', 'Segoe UI', sans-serif; 
            background: var(--bg); 
            color: #333; 
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, #600000 100%);
            padding: 50px 40px;
            border-radius: 16px;
            color: white;
            margin-bottom: 40px;
            box-shadow: var(--shadow);
        }

        .header h1 {
            color: white;
            font-size: 2.8rem;
            margin-bottom: 10px;
            font-weight: 800;
        }

        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 1.2rem;
        }

        .stats-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-around;
            align-items: center;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-maroon);
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-group label {
            font-weight: 600;
            color: #555;
        }

        select, input[type="text"] {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            outline: none;
            transition: border 0.3s;
        }

        select:focus, input[type="text"]:focus {
            border-color: var(--primary-maroon);
        }

        .search-box {
            flex: 1;
            min-width: 250px;
        }

        .problems-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .problem-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
        }

        .problem-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary-maroon);
        }

        .problem-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .problem-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .difficulty-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .difficulty-easy {
            background: #e3fcef;
            color: #00875a;
        }

        .difficulty-medium {
            background: #fff4e5;
            color: #b76e00;
        }

        .difficulty-hard {
            background: #ffe9e9;
            color: #bf2600;
        }

        .category-tag {
            display: inline-block;
            padding: 5px 12px;
            background: #f0f0f0;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #666;
            margin-top: 10px;
        }

        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-solved {
            background: #00875a;
            color: white;
        }

        .status-attempted {
            background: #ff991f;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container">
        <div class="header">
            <h1>👨‍💻 Coding Practice</h1>
            <p>Learn concepts, practice problems, and master coding skills</p>
        </div>

        <!-- Progress Stats -->
        <div class="stats-bar" id="statsBar">
            <div class="stat-item">
                <div class="stat-number" id="totalSolved">0</div>
                <div class="stat-label">Problems Solved</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="totalAttempted">0</div>
                <div class="stat-label">Attempted</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="totalProblems">0</div>
                <div class="stat-label">Total Problems</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <div class="filter-group">
                <label>Category:</label>
                <select id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="Arrays">Arrays</option>
                    <option value="Strings">Strings</option>
                    <option value="Loops">Loops</option>
                    <option value="Recursion">Recursion</option>
                    <option value="Sorting">Sorting</option>
                    <option value="Searching">Searching</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Difficulty:</label>
                <select id="difficultyFilter">
                    <option value="">All Levels</option>
                    <option value="Easy">Easy</option>
                    <option value="Medium">Medium</option>
                    <option value="Hard">Hard</option>
                </select>
            </div>

            <input type="text" id="searchBox" class="search-box" placeholder="🔍 Search problems...">
        </div>

        <!-- Problems Grid -->
        <div id="problemsContainer">
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading problems...</p>
            </div>
        </div>
    </div>

    <script>
        let allProblems = [];
        let progressStats = {};

        // Load problems and stats on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadProgressStats();
            loadCategories(); // New function
            loadProblems();

            // Event listeners for filters
            document.getElementById('categoryFilter').addEventListener('change', filterProblems);
            document.getElementById('difficultyFilter').addEventListener('change', filterProblems);
            document.getElementById('searchBox').addEventListener('input', filterProblems);
        });

        async function loadCategories() {
            try {
                const response = await fetch('coding_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_categories' })
                });

                const data = await response.json();
                if (data.success) {
                    const select = document.getElementById('categoryFilter');
                    // Keep the "All Categories" option
                    select.innerHTML = '<option value="">All Categories</option>';
                    data.categories.forEach(category => {
                        const option = document.createElement('option');
                        option.value = category;
                        option.textContent = category;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Failed to load categories:', error);
            }
        }

        async function loadProgressStats() {
            try {
                const response = await fetch('coding_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_progress_stats' })
                });

                const data = await response.json();
                if (data.success) {
                    progressStats = data.stats;
                    document.getElementById('totalSolved').textContent = data.stats.total_solved;
                    document.getElementById('totalAttempted').textContent = data.stats.total_attempted;
                }
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        }

        async function loadProblems() {
            try {
                const response = await fetch('coding_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_problems' })
                });

                const data = await response.json();
                if (data.success) {
                    allProblems = data.problems;
                    document.getElementById('totalProblems').textContent = allProblems.length;
                    displayProblems(allProblems);
                }
            } catch (error) {
                console.error('Failed to load problems:', error);
                document.getElementById('problemsContainer').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Failed to load problems. Please try again.</p>
                    </div>
                `;
            }
        }

        function filterProblems() {
            const category = document.getElementById('categoryFilter').value;
            const difficulty = document.getElementById('difficultyFilter').value;
            const search = document.getElementById('searchBox').value.toLowerCase();

            const filtered = allProblems.filter(problem => {
                const matchCategory = !category || problem.category === category;
                const matchDifficulty = !difficulty || problem.difficulty === difficulty;
                const matchSearch = !search || problem.title.toLowerCase().includes(search);
                return matchCategory && matchDifficulty && matchSearch;
            });

            displayProblems(filtered);
        }

        function displayProblems(problems) {
            const container = document.getElementById('problemsContainer');

            if (problems.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <p>No problems found matching your criteria.</p>
                    </div>
                `;
                return;
            }

            const html = `
                <div class="problems-grid">
                    ${problems.map(problem => `
                        <div class="problem-card" onclick="navigatePost('coding_problem.php', {id: ${problem.id}})">
                            ${getStatusBadge(problem.status)}
                            <div class="problem-header">
                                <div>
                                    <div class="problem-title">${problem.title}</div>
                                    <span class="difficulty-badge difficulty-${problem.difficulty.toLowerCase()}">
                                        ${problem.difficulty}
                                    </span>
                                </div>
                            </div>
                            <span class="category-tag">
                                <i class="fas fa-tag"></i> ${problem.category}
                            </span>
                        </div>
                    `).join('')}
                </div>
            `;

            container.innerHTML = html;
        }

        function getStatusBadge(status) {
            if (status === 'solved' || status === 'mastered') {
                return '<span class="status-badge status-solved">✓ Solved</span>';
            } else if (status === 'attempted') {
                return '<span class="status-badge status-attempted">⚡ Attempted</span>';
            }
            return '';
        }

        /**
         * Universal POST Navigator for Clean URLs
         */
        function navigatePost(url, data) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = url;
            for (const key in data) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = data[key];
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
