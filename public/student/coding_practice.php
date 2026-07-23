<?php
/**
 * Coding Practice - Problem Library
 * Educational coding platform for students
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();

// Warm up the student profile session cache for AJAX calls
$studentModel = new StudentProfile();
$studentModel->getProfile($userId);
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

        /* Company Tags CSS */
        .company-tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px dashed #e2e8f0;
        }

        .company-logo-tile {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
            position: relative;
            cursor: pointer;
        }

        .company-logo-tile:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }

        .company-logo-img {
            width: 18px;
            height: 18px;
            object-fit: contain;
        }

        /* Tooltip style for company name on hover */
        .company-logo-tile::after {
            content: attr(data-company);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(-4px);
            background: #1e293b;
            color: #ffffff;
            font-size: 0.65rem;
            padding: 4px 8px;
            border-radius: 4px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 10;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .company-logo-tile:hover::after {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-8px);
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

        /* DSA Tabs Styling */
        .dsa-tabs-container {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 5px;
        }

        .dsa-tab {
            background: none;
            border: none;
            padding: 12px 24px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            color: #64748b;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
            outline: none;
        }

        .dsa-tab:hover {
            color: var(--primary-maroon);
        }

        .dsa-tab.active {
            color: var(--primary-maroon);
            border-bottom-color: var(--primary-maroon);
        }

        .hot-badge {
            background: linear-gradient(135deg, #ff4500 0%, #ff8c00 100%);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 800;
            letter-spacing: 0.5px;
            animation: dsaPulse 1.5s infinite;
        }

        @keyframes dsaPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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

        <!-- DSA Tabs Switcher -->
        <div class="dsa-tabs-container">
            <button class="dsa-tab active" data-tab="basic" onclick="switchDsaTab('basic')">
                <i class="fas fa-graduation-cap"></i> Basic DSA
            </button>
            <button class="dsa-tab" data-tab="interview" onclick="switchDsaTab('interview')">
                <i class="fas fa-fire" style="color: #ff4500;"></i> Interview DSA
                <span class="hot-badge">HOT</span>
            </button>
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
        let allCategories = [];
        let activeTab = 'basic';
        const basicCategories = ['Loops', 'Sorting', 'Searching', 'Math', 'Recursion'];

        function getCompanyLogoUrl(name) {
            const slugMap = {
                'google': 'google',
                'amazon': 'amazon',
                'microsoft': 'microsoft',
                'meta': 'meta',
                'apple': 'apple',
                'netflix': 'netflix',
                'uber': 'uber',
                'lyft': 'lyft',
                'stripe': 'stripe',
                'adobe': 'adobe',
                'bloomberg': 'bloomberg',
                'salesforce': 'salesforce',
                'oracle': 'oracle',
                'walmart': 'walmart',
                'goldman sachs': 'goldmansachs',
                'jpmorgan': 'jpmorganchase',
                'nvidia': 'nvidia',
                'cisco': 'cisco',
                'tcs': 'tataconsultancyservices',
                'infosys': 'infosys',
                'wipro': 'wipro',
                'capgemini': 'capgemini',
                'accenture': 'accenture',
                'cognizant': 'cognizant',
                'hcltech': 'hcl',
                'ltimindtree': 'ltimindtree'
            };
            const cleanName = name.toLowerCase().trim();
            const slug = slugMap[cleanName] || cleanName;
            return `https://cdn.simpleicons.org/${slug}`;
        }

        function handleLogoError(img, name) {
            const parent = img.parentNode;
            img.remove();
            // Show initials
            let initials = name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            if (initials.length === 1 && name.length > 1) {
                initials = name.substring(0, 2).toUpperCase();
            }
            parent.innerText = initials;
            parent.style.fontSize = '9px';
            parent.style.fontWeight = '800';
            parent.style.color = '#475569';
            parent.style.backgroundColor = '#f1f5f9';
        }

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
                const response = await fetch('coding_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_categories' })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse categories JSON. Raw text:', text);
                    throw new Error('Invalid JSON response');
                }

                if (data.success) {
                    allCategories = data.categories;
                    updateCategoryDropdown();
                } else {
                    throw new Error(data.message || 'Failed to load categories');
                }
            } catch (error) {
                console.error('Failed to load categories:', error);
            }
        }

        function updateCategoryDropdown() {
            const select = document.getElementById('categoryFilter');
            select.innerHTML = '<option value="">All Categories</option>';
            
            const filteredCats = allCategories.filter(cat => {
                const isBasic = basicCategories.includes(cat);
                return activeTab === 'basic' ? isBasic : !isBasic;
            });

            filteredCats.forEach(category => {
                const option = document.createElement('option');
                option.value = category;
                option.textContent = category;
                select.appendChild(option);
            });
        }

        async function loadProgressStats() {
            try {
                const response = await fetch('coding_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_progress_stats' })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse stats JSON. Raw text:', text);
                    throw new Error('Invalid JSON response');
                }

                if (data.success) {
                    progressStats = data.stats;
                    document.getElementById('totalSolved').textContent = data.stats.total_solved;
                    document.getElementById('totalAttempted').textContent = data.stats.total_attempted;
                } else {
                    throw new Error(data.message || 'Failed to load stats');
                }
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        }

        async function loadProblems() {
            try {
                const response = await fetch('coding_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_problems' })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse problems JSON. Raw text:', text);
                    throw new Error('Invalid JSON response');
                }

                if (data.success) {
                    allProblems = data.problems;
                    document.getElementById('totalProblems').textContent = allProblems.length;
                    filterProblems();
                } else {
                    throw new Error(data.message || 'Failed to load problems');
                }
            } catch (error) {
                console.error('Failed to load problems:', error);
                document.getElementById('problemsContainer').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Failed to load problems: ${error.message}</p>
                    </div>
                `;
            }
        }

        function switchDsaTab(tab) {
            activeTab = tab;
            
            // Toggle active class on tab buttons
            document.querySelectorAll('.dsa-tab').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-tab') === tab);
            });

            // Reset category filter value
            document.getElementById('categoryFilter').value = '';

            // Update category dropdown list options for new tab
            updateCategoryDropdown();

            // Re-filter problems
            filterProblems();
        }

        function filterProblems() {
            const category = document.getElementById('categoryFilter').value;
            const difficulty = document.getElementById('difficultyFilter').value;
            const search = document.getElementById('searchBox').value.toLowerCase();

            const filtered = allProblems.filter(problem => {
                // Tab filter
                const isBasicCategory = basicCategories.includes(problem.category);
                const matchTab = activeTab === 'basic' ? isBasicCategory : !isBasicCategory;

                const matchCategory = !category || problem.category === category;
                const matchDifficulty = !difficulty || problem.difficulty === difficulty;
                const matchSearch = !search || problem.title.toLowerCase().includes(search);
                return matchTab && matchCategory && matchDifficulty && matchSearch;
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
                            ${problem.companies ? `
                            <div class="company-tags-list">
                                ${problem.companies.split(',').map(company => {
                                    const cleaned = company.trim();
                                    return `
                                    <span class="company-logo-tile" data-company="${cleaned}">
                                        <img src="${getCompanyLogoUrl(cleaned)}" alt="" class="company-logo-img" onerror="handleLogoError(this, '${cleaned}')">
                                    </span>
                                    `;
                                }).join('')}
                            </div>
                            ` : ''}
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

