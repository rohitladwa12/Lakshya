<?php
/**
 * Interview Management - Placement Officer
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$pageId = 'officer_interviews';

// Handle POST State (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SessionFilterHelper::handlePostToSession($pageId, $_POST);
    header("Location: interviews.php");
    exit;
}

// Retrieve from Session
$filters = SessionFilterHelper::getFilters($pageId);
$db = getDB();

// Handle scheduling from application page
$shortlistId = $filters['shortlist_id'] ?? null;
$initialData = null;
if ($shortlistId) {
    $gmuUsers = DB_GMU_PREFIX . 'users';
    $gmitUsers = DB_GMIT_PREFIX . 'users';
    $sql = "SELECT ja.*, u.NAME as full_name, jp.title as job_title, c.name as company_name
            FROM job_applications ja
            JOIN (
                SELECT SL_NO, NAME FROM {$gmuUsers}
                UNION ALL
                SELECT ENQUIRY_NO as SL_NO, NAME FROM {$gmitUsers}
            ) u ON ja.student_id = u.SL_NO
            JOIN job_postings jp ON ja.job_id = jp.id
            JOIN companies c ON jp.company_id = c.id
            WHERE ja.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$shortlistId]);
    $initialData = $stmt->fetch();
}

// Get all interviews
$gmuUsers = DB_GMU_PREFIX . 'users';
$gmitUsers = DB_GMIT_PREFIX . 'users';
$sql = "SELECT i.*, u.NAME as student_name, jp.title as job_title, c.name as company_name
        FROM interviews i
        JOIN job_applications ja ON i.application_id = ja.id AND i.application_type = 'job'
        JOIN (
            SELECT SL_NO, NAME FROM {$gmuUsers}
            UNION ALL
            SELECT ENQUIRY_NO as SL_NO, NAME FROM {$gmitUsers}
        ) u ON ja.student_id = u.SL_NO
        JOIN job_postings jp ON ja.job_id = jp.id
        JOIN companies c ON jp.company_id = c.id
        ORDER BY i.interview_date ASC";
$interviews = $db->query($sql)->fetchAll();

// Get shortlisted applications for the "Schedule" dropdown
$sql = "SELECT ja.id, u.NAME as full_name, jp.title, c.name as company_name
        FROM job_applications ja
        JOIN (
            SELECT SL_NO, NAME FROM {$gmuUsers}
            UNION ALL
            SELECT ENQUIRY_NO as SL_NO, NAME FROM {$gmitUsers}
        ) u ON ja.student_id = u.SL_NO
        JOIN job_postings jp ON ja.job_id = jp.id
        JOIN companies c ON jp.company_id = c.id
        WHERE ja.status = 'Shortlisted'";
$shortlistedApps = $db->query($sql)->fetchAll();

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Interviews - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --brand: #800000;
            --brand-light: #a52a2a;
            --bg-main: #f8fafc;
            --glass: rgba(255, 255, 255, 0.9);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 10px 15px -3px rgba(0,0,0,0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            background: var(--bg-main); 
            color: var(--text-main);
            padding-top: 90px; /* Adjusted for new 70px navbar + padding */
            min-height: 100vh;
        }

        .o-page { padding: 40px; max-width: 1400px; margin: 0 auto; }
        
        .o-head {
            background: var(--glass);
            backdrop-filter: blur(12px);
            padding: 30px;
            border-radius: 24px;
            border: 1px solid white;
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .o-head h1 { font-size: 28px; font-weight: 800; color: var(--brand); letter-spacing: -0.02em; }
        .o-head p { color: var(--text-muted); font-size: 14px; margin-top: 4px; }

        .interview-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); 
            gap: 25px; 
        }

        .card-glass {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid white;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .card-glass:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }

        .card-glass::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
            background: var(--brand);
            opacity: 0.8;
        }

        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .student-name { font-size: 18px; font-weight: 700; color: var(--text-main); }
        .job-title { font-size: 13px; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

        .status-pill {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-Scheduled { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
        .status-Completed { background: rgba(34, 197, 94, 0.1); color: #16a34a; }

        .info-item { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; color: var(--text-main); font-size: 14px; }
        .info-icon { font-size: 16px; color: var(--brand); opacity: 0.8; width: 20px; }

        .card-footer {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-action {
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-schedule { background: var(--brand); color: white; }
        .btn-outline { background: white; border: 1.5px solid #e2e8f0; color: var(--text-muted); }
        .btn-outline:hover { background: #f8fafc; color: var(--brand); border-color: var(--brand); }
        .btn-action:hover { transform: translateY(-2px); filter: brightness(1.1); }

        /* Modal Glass */
        #scheduleModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-glass {
            background: white;
            border-radius: 24px;
            width: 100%;
            max-width: 550px;
            padding: 35px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: modalSlide 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes modalSlide { from { opacity: 0; transform: scale(0.95) translateY(10px); } }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: var(--text-main); }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .form-control:focus { outline: none; border-color: var(--brand); background: white; box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.1); }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="o-page">
        <div class="o-head">
            <div>
                <h1>Interview Pipeline</h1>
                <p>Track and manage upcoming candidate evaluations.</p>
            </div>
            <button class="btn-action btn-schedule" onclick="openModal()">
                <i class="fas fa-calendar-plus"></i> Schedule Interview
            </button>
        </div>

        <div class="interview-grid">
            <?php foreach ($interviews as $i): ?>
            <div class="card-glass">
                <div class="card-header">
                    <div>
                        <div class="student-name"><?php echo htmlspecialchars($i['student_name']); ?></div>
                        <div class="job-title"><?php echo htmlspecialchars($i['job_title']); ?> @ <?php echo htmlspecialchars($i['company_name']); ?></div>
                    </div>
                    <span class="status-pill status-<?php echo $i['status']; ?>"><?php echo $i['status']; ?></span>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-calendar-alt info-icon"></i>
                    <span><?php echo date('D, M d, Y', strtotime($i['interview_date'])); ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-clock info-icon"></i>
                    <span><?php echo date('h:i A', strtotime($i['interview_date'])); ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-laptop-code info-icon"></i>
                    <span><?php echo htmlspecialchars($i['interview_type']); ?> (<?php echo htmlspecialchars($i['mode']); ?>)</span>
                </div>
                <div class="info-item">
                    <i class="fas fa-map-marker-alt info-icon"></i>
                    <span style="font-size: 11px;"><?php echo $i['location'] ?: 'Location not set'; ?></span>
                </div>

                <div class="card-footer">
                    <button class="btn-action btn-outline" style="font-size: 11px;" onclick='editInterview(<?php echo json_encode($i); ?>)'>
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <?php if ($i['status'] === 'Scheduled'): ?>
                    <button class="btn-action btn-schedule" style="background: #16a34a; font-size: 11px;" onclick="completeInterview(<?php echo $i['id']; ?>)">
                        <i class="fas fa-check-circle"></i> Complete
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; if (empty($interviews)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 100px 20px; color: var(--text-muted); background: var(--glass); border-radius: 24px; border: 1px dashed #cbd5e1;">
                <i class="fas fa-calendar-times" style="font-size: 40px; margin-bottom: 20px; opacity: 0.5;"></i>
                <p>No interviews scheduled yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" <?php echo $shortlistId ? 'style="display:flex"' : ''; ?>>
        <div class="modal-glass">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 30px;">
                <div>
                    <h2 id="modalTitle" style="color: var(--brand); font-size: 20px; font-weight: 800;">Schedule Interview</h2>
                    <p style="font-size: 13px; color: var(--text-muted);">Coordinate evaluation round</p>
                </div>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>

            <form id="interviewForm" method="POST" action="interview_handler.php">
                <input type="hidden" name="action" id="formAction" value="schedule">
                <input type="hidden" name="interview_id" id="interviewId">
                
                <div class="form-group">
                    <label>Shortlisted Candidate</label>
                    <select name="application_id" id="applicationId" class="form-control" required>
                        <option value="">-- Select Application --</option>
                        <?php if ($initialData): ?>
                        <option value="<?php echo $initialData['id']; ?>" selected>
                            <?php echo htmlspecialchars($initialData['full_name']); ?> - <?php echo htmlspecialchars($initialData['job_title']); ?>
                        </option>
                        <?php endif; ?>
                        <?php foreach ($shortlistedApps as $app): ?>
                        <option value="<?php echo $app['id']; ?>">
                            <?php echo htmlspecialchars($app['full_name']); ?> - <?php echo htmlspecialchars($app['title']); ?> (<?php echo htmlspecialchars($app['company_name']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Interview Type</label>
                        <select name="interview_type" id="interviewType" class="form-control">
                            <option value="Technical">Technical Round</option>
                            <option value="HR">HR Round</option>
                            <option value="Group Discussion">Group Discussion</option>
                            <option value="Final">Final Round</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Mode</label>
                        <select name="mode" id="interviewMode" class="form-control">
                            <option value="Video Call">Video Call</option>
                            <option value="In-Person">In-Person</option>
                            <option value="Phone Call">Phone Call</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Date & Time</label>
                    <input type="datetime-local" name="interview_date" id="interviewDate" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Location / Meeting Link</label>
                    <input type="text" name="location" id="interviewLocation" class="form-control" placeholder="Office address or Video Link">
                </div>

                <div style="display: flex; gap: 12px; margin-top: 40px;">
                    <button type="button" class="btn-action btn-outline" style="flex: 1; justify-content: center;" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-action btn-schedule" style="flex: 2; justify-content: center;">Save Interview</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() { 
            document.getElementById('modalTitle').innerText = 'Schedule Interview';
            document.getElementById('formAction').value = 'schedule';
            document.getElementById('interviewId').value = '';
            document.getElementById('scheduleModal').style.display = 'flex'; 
        }

        function closeModal() { document.getElementById('scheduleModal').style.display = 'none'; }
        
        function editInterview(data) {
            document.getElementById('modalTitle').innerText = 'Edit Interview';
            document.getElementById('formAction').value = 'update';
            document.getElementById('interviewId').value = data.id;
            document.getElementById('applicationId').value = data.application_id;
            document.getElementById('interviewType').value = data.interview_type;
            document.getElementById('interviewMode').value = data.mode;
            
            // Format date for datetime-local
            const d = new Date(data.interview_date);
            const formattedDate = d.toISOString().slice(0, 16);
            document.getElementById('interviewDate').value = formattedDate;
            
            document.getElementById('interviewLocation').value = data.location;
            document.getElementById('scheduleModal').style.display = 'flex';
        }

        async function completeInterview(id) {
            const feedback = prompt("Enter interview feedback and result (Selected/Rejected):");
            if (feedback) {
                const res = await fetch('interview_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'complete', interview_id: id, feedback: feedback })
                });
                const data = await res.json();
                if (data.success) location.reload();
            }
        }
    </script>
</body>
</html>
