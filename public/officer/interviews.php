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
            --primary-maroon: #800000;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --sidebar-width: 260px;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; display: flex; flex-direction: column; min-height: 100vh; }
        
        .main-content { flex: 1; padding: 40px; width: 100%; max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }

        .btn-schedule { background: var(--primary-maroon); color: var(--white); border: none; padding: 12px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: var(--transition); }
        .btn-schedule:hover { background: #5b1f1f; transform: scale(1.02); }

        .interview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; }
        .interview-card { background: var(--white); border-radius: 16px; padding: 25px; box-shadow: var(--shadow); border-top: 5px solid var(--primary-gold); position: relative; }
        
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .student-title { font-size: 18px; font-weight: bold; color: var(--primary-maroon); }
        .job-subtitle { font-size: 14px; color: #666; margin-top: 2px; }

        .info-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 14px; color: #444; }
        .info-icon { width: 20px; text-align: center; color: var(--primary-gold); font-weight: bold; }

        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-Scheduled { background: #e3f2fd; color: #1976d2; }
        .status-Completed { background: #e8f5e9; color: #2e7d32; }

        .card-footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }
        
        /* Modal */
        #scheduleModal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: var(--white); width: 600px; padding: 30px; border-radius: 20px; position: relative; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        .modal-footer { margin-top: 30px; display: flex; justify-content: flex-end; gap: 15px; }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h2>Interview Schedule</h2>
            <button class="btn-schedule" onclick="openModal()">+ Schedule Interview</button>
        </div>

        <div class="interview-grid">
            <?php foreach ($interviews as $i): ?>
            <div class="interview-card">
                <div class="card-header">
                    <div>
                        <div class="student-title"><?php echo htmlspecialchars($i['student_name']); ?></div>
                        <div class="job-subtitle"><?php echo htmlspecialchars($i['job_title']); ?> @ <?php echo htmlspecialchars($i['company_name']); ?></div>
                    </div>
                    <span class="status-pill status-<?php echo $i['status']; ?>"><?php echo $i['status']; ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-icon">📅</span>
                    <span><?php echo date('D, M d, Y', strtotime($i['interview_date'])); ?> at <?php echo date('h:i A', strtotime($i['interview_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-icon">📍</span>
                    <span><?php echo $i['mode']; ?> (<?php echo $i['location'] ?: 'Online'; ?>)</span>
                </div>
                <div class="info-row">
                    <span class="info-icon">🔄</span>
                    <span>Round: <?php echo $i['interview_type']; ?> (#<?php echo $i['round_number']; ?>)</span>
                </div>

                <div class="card-footer">
                    <button class="btn-schedule" style="background:none; color: var(--primary-maroon); border: 1px solid var(--primary-maroon); padding: 8px 15px;" onclick="editInterview(<?php echo htmlspecialchars(json_encode($i)); ?>)">Reschedule</button>
                    <button class="btn-schedule" style="padding: 8px 15px;" onclick="completeInterview(<?php echo $i['id']; ?>)">Complete</button>
                </div>
            </div>
            <?php endforeach; if (empty($interviews)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: #999;">No interviews scheduled yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" <?php echo $shortlistId ? 'style="display:flex"' : ''; ?>>
        <div class="modal-content">
            <h3>Schedule New Interview</h3>
            <form id="interviewForm" method="POST" action="interview_handler.php">
                <input type="hidden" name="action" value="schedule">
                
                <div class="form-group">
                    <label>Shortlisted Candidate</label>
                    <select name="application_id" class="form-control" required>
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

                <div class="form-group">
                    <label>Interview Type</label>
                    <select name="interview_type" class="form-control">
                        <option value="Technical">Technical Round</option>
                        <option value="HR">HR Round</option>
                        <option value="Aptitude">Aptitude Test</option>
                        <option value="Group Discussion">Group Discussion</option>
                        <option value="Final">Final Round</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Date & Time</label>
                    <input type="datetime-local" name="interview_date" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Mode</label>
                    <select name="mode" class="form-control">
                        <option value="In-Person">In-Person</option>
                        <option value="Video Call">Video Call</option>
                        <option value="Phone Call">Phone Call</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Location / Meeting Link</label>
                    <input type="text" name="location" class="form-control" placeholder="Office address or Video Link">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-schedule" style="background:#eee; color:#333;" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-schedule">Schedule Now</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('scheduleModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('scheduleModal').style.display = 'none'; }
        
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
