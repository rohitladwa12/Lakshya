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
try {
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
} catch (Exception $e) {
    $sqlFallback = "SELECT i.*, COALESCE(u.NAME, sp.name, ja.student_id) as student_name, jp.title as job_title, c.name as company_name
            FROM interviews i
            JOIN job_applications ja ON i.application_id = ja.id AND i.application_type = 'job'
            LEFT JOIN users u ON ja.student_id = u.USER_NAME
            LEFT JOIN student_profiles sp ON ja.student_id = sp.usn
            JOIN job_postings jp ON ja.job_id = jp.id
            JOIN companies c ON jp.company_id = c.id
            ORDER BY i.interview_date ASC";
    $interviews = $db->query($sqlFallback)->fetchAll();
}

// Get shortlisted applications for the "Schedule" dropdown
try {
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
} catch (Exception $e) {
    $sqlFallback = "SELECT ja.id, COALESCE(u.NAME, sp.name, ja.student_id) as full_name, jp.title, c.name as company_name
            FROM job_applications ja
            LEFT JOIN users u ON ja.student_id = u.USER_NAME
            LEFT JOIN student_profiles sp ON ja.student_id = sp.usn
            JOIN job_postings jp ON ja.job_id = jp.id
            JOIN companies c ON jp.company_id = c.id
            WHERE ja.status = 'Shortlisted'";
    $shortlistedApps = $db->query($sqlFallback)->fetchAll();
}

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Interviews - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --maroon: #7C0000;
            --maroon-dark: #5A0000;
            --maroon-light: #9B1B1B;
            --gold: #B08D2C;
            --page-bg: #F8F9FA;
            --card-bg: #FFFFFF;
            --border-color: #E5E7EB;
            --text-primary: #111827;
            --text-secondary: #4B5563;
            --text-muted: #9CA3AF;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
            --shadow-modal: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', system-ui, -apple-system, sans-serif; 
            background: var(--page-bg); 
            color: var(--text-primary);
            padding-top: 0;
            min-height: 100vh;
        }

        .o-page { padding: 32px 40px; max-width: 1400px; margin: 0 auto; }
        
        .o-head {
            background: var(--card-bg);
            padding: 24px 30px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .o-head h1 { font-size: 24px; font-weight: 700; color: var(--maroon); letter-spacing: -0.02em; }
        .o-head p { color: var(--text-secondary); font-size: 13.5px; margin-top: 4px; }

        .interview-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); 
            gap: 20px; 
        }

        .card-glass {
            background: var(--card-bg);
            border-radius: var(--radius-md);
            padding: 22px;
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--maroon);
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }

        .card-glass:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }

        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .student-name { font-size: 16px; font-weight: 700; color: var(--text-primary); }
        .job-title { font-size: 13px; color: var(--text-secondary); margin-top: 3px; font-weight: 500; }

        .status-pill {
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .status-Scheduled { background: #EFF6FF; color: #1D4ED8; border: 1px solid #DBEAFE; }
        .status-Completed { background: #ECFDF5; color: #047857; border: 1px solid #D1FAE5; }

        .info-item { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; color: var(--text-secondary); font-size: 13.5px; }
        .info-icon { font-size: 14px; color: var(--maroon); width: 18px; text-align: center; }

        .card-footer {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-action {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-schedule { background: var(--maroon); color: #FFFFFF; }
        .btn-schedule:hover { background: var(--maroon-dark); color: #FFFFFF; }
        .btn-outline { background: #FFFFFF; border: 1px solid var(--border-color); color: var(--text-secondary); }
        .btn-outline:hover { background: #F3F4F6; color: var(--text-primary); border-color: #D1D5DB; }

        /* Modal ERP */
        #scheduleModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.45);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-glass {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 540px;
            padding: 28px;
            box-shadow: var(--shadow-modal);
            border: 1px solid var(--border-color);
            animation: modalFade 0.2s ease-out;
        }

        @keyframes modalFade { from { opacity: 0; transform: scale(0.97); } to { opacity: 1; transform: scale(1); } }

        .modal-header-erp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .close-btn-erp {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #F3F4F6;
            color: var(--text-secondary);
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.15s;
        }
        .close-btn-erp:hover { background: #E5E7EB; color: var(--text-primary); }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: var(--text-primary); }
        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #D1D5DB;
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            transition: all 0.15s;
            background: #FFFFFF;
            color: var(--text-primary);
        }
        .form-control:focus { outline: none; border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(124, 0, 0, 0.12); }
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
            <div class="modal-header-erp">
                <div>
                    <h2 id="modalTitle" style="color: var(--maroon); font-size: 18px; font-weight: 700; margin: 0;">Schedule Interview</h2>
                    <p style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;">Coordinate evaluation round</p>
                </div>
                <button type="button" onclick="closeModal()" class="close-btn-erp">&times;</button>
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

