<?php
/**
 * Student Management Page - Placement Officer
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$pageId = 'officer_students';

// Handle POST State (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SessionFilterHelper::handlePostToSession($pageId, $_POST);
    header("Location: students.php");
    exit;
}

// Retrieve from Session
$filters = SessionFilterHelper::getFilters($pageId);
$studentModel = new StudentProfile();

// Handle Search
$query = $filters['q'] ?? '';

if ($query) {
    $students = $studentModel->search($query);
} else {
    $students = $studentModel->getAllWithUsers();
}

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        
        /* Navbar handled by include */

        .main-content { 
            /* Layout handled by navbar.php */
        }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-size: 28px; color: var(--primary-maroon); font-weight: 700; }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            background: var(--white);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 500px;
        }

        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary { background: var(--primary-maroon); color: var(--white); }
        .btn-primary:hover { background: var(--primary-dark); }

        .table-container {
            background: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; }
        
        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            color: #666;
            font-weight: 600;
            font-size: 14px;
        }

        td { color: #333; font-size: 15px; }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .btn-view {
            color: var(--primary-maroon);
            background: rgba(128, 0, 0, 0.1);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .btn-view:hover { background: var(--primary-maroon); color: white; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1100;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .detail-item strong { display: block; color: #666; font-size: 13px; margin-bottom: 5px; }
        .detail-item span { font-size: 16px; font-weight: 600; color: #333; display: block; }
        
        .modal-header h2 { color: var(--primary-maroon); border-bottom: 2px solid var(--primary-gold); display: inline-block; padding-bottom: 10px; }

    </style>
</head>
<body>

    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Manage Students</h1>
        </div>

        <div class="actions-bar">
            <form class="search-box" method="POST">
                <input type="text" name="q" class="search-input" placeholder="Search by name, USN or email..." value="<?php echo htmlspecialchars($query); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
            <div>
                <!-- Future: Export Button -->
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>USN</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Discipline</th>
                        <th>Year</th>
                        <th>Sem</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                                No students found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['enrollment_number']); ?></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($student['name']); ?></div>
                                <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($student['email'] ?? ''); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($student['course']); ?></td>
                            <td><?php echo htmlspecialchars($student['department']); ?></td>
                            <td><?php echo htmlspecialchars($student['year_of_study']); ?></td>
                            <td><?php echo htmlspecialchars($student['semester']); ?></td>
                            <td>
                                <button class="btn btn-view" onclick='viewStudent(<?php echo json_encode($student); ?>)'>
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Student Detail Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <div class="modal-header">
                <h2>Student Details</h2>
            </div>
            <div id="modalBody" class="detail-grid">
                <!-- Content populated by JS -->
            </div>
        </div>
    </div>

    <script>
        function viewStudent(data) {
            const modal = document.getElementById('studentModal');
            const body = document.getElementById('modalBody');
            
            // Build content
            let html = `
                <div class="detail-item"><strong>Full Name</strong><span>${data.name}</span></div>
                <div class="detail-item"><strong>USN</strong><span>${data.enrollment_number}</span></div>
                <div class="detail-item"><strong>Course</strong><span>${data.course}</span></div>
                <div class="detail-item"><strong>Discipline</strong><span>${data.department}</span></div>
                <div class="detail-item"><strong>Year Of Study</strong><span>${data.year_of_study}</span></div>
                <div class="detail-item"><strong>Semester</strong><span>${data.semester}</span></div>
                <div class="detail-item"><strong>Faculty</strong><span>${data.faculty || '-'}</span></div>
                <div class="detail-item"><strong>School</strong><span>${data.school || '-'}</span></div>
                <div class="detail-item"><strong>Programme</strong><span>${data.programme || '-'}</span></div>
                <div class="detail-item"><strong>Student ID (Inquiry)</strong><span>${data.student_id || '-'}</span></div>
                <div class="detail-item"><strong>Section</strong><span>${data.section || '-'}</span></div>
                <div class="detail-item"><strong>Academic Year</strong><span>${data.academic_year || '-'}</span></div>
            `;
            
            body.innerHTML = html;
            modal.style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('studentModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('studentModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
