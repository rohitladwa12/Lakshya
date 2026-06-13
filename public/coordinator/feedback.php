<?php
/**
 * Department Coordinator - Student Feedback Review Page
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Auth
requireRole(ROLE_DEPT_COORDINATOR);

$fullName = getFullName();
$department = getDepartment() ?: 'General';
$discipline_filters = getCoordinatorDisciplineFilters($department);
$deptLabel = ($department === 'General') ? 'All Departments' : $department;

$db = getDB();

$success = Session::flash('success') ?: '';
$error = Session::flash('error') ?: '';

// Handle reply submission
if (isPost()) {
    $action = post('action');
    if ($action === 'submit_reply') {
        $feedbackId = (int)post('feedback_id');
        $replyText = trim((string)post('reply_text'));
        $csrfToken = post('csrf_token');
        
        if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
            Session::flash('error', 'Security validation failed. Please refresh and try again.');
        } elseif (empty($replyText)) {
            Session::flash('error', 'Reply text cannot be empty.');
        } else {
            try {
                $stmt = $db->prepare("UPDATE portal_feedback SET admin_reply = ?, replied_by = ?, replied_at = NOW(), reply_read = 0 WHERE id = ?");
                $stmt->execute([$replyText, $fullName . ' (Coordinator)', $feedbackId]);
                Session::flash('success', 'Reply submitted successfully.');
            } catch (Exception $e) {
                Session::flash('error', 'Error submitting reply: ' . $e->getMessage());
            }
        }
        redirect('feedback.php');
        exit;
    }
}

$feedbacks = [];
$stats = [
    'total' => 0,
    'gmu' => 0,
    'gmit' => 0,
    'features' => 0
];

if (!empty($discipline_filters)) {
    try {
        $placeholders = implode(',', array_fill(0, count($discipline_filters), '?'));
        
        // Fetch all feedbacks for coordinator's department branches
        $stmt = $db->prepare("SELECT * FROM portal_feedback WHERE branch IN ($placeholders) ORDER BY created_at DESC");
        $stmt->execute($discipline_filters);
        $feedbacks = $stmt->fetchAll();
        
        $stats['total'] = count($feedbacks);
        foreach ($feedbacks as $fb) {
            if (strtolower($fb['institution'] ?? '') === 'gmit') {
                $stats['gmit']++;
            } else {
                $stats['gmu']++;
            }
            if (!empty($fb['new_feature_title'])) {
                $stats['features']++;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching coordinator feedbacks: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Feedback – <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --dark-maroon: #5b1f1f;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --shadow: 0 4px 20px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .main-content {
            padding: 40px 50px;
        }

        .back-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            display: inline-block;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--primary-maroon);
        }

        /* Page Header */
        .page-header { 
            margin-bottom: 40px; 
        }
        
        .page-header h2 { 
            font-size: 32px; 
            color: var(--primary-maroon); 
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .page-header p { 
            color: #64748b; 
            font-size: 15px; 
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            padding: 20px 24px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid transparent;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-maroon);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .icon-total { background: #e0f2fe; color: #0284c7; }
        .icon-gmu { background: #dcfce7; color: #15803d; }
        .icon-gmit { background: #e0e7ff; color: #4338ca; }
        .icon-feature { background: #fef3c7; color: #d97706; }

        .stat-info h3 {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .stat-info .value {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary-maroon);
        }

        /* Content Card */
        .content-card {
            background: var(--white);
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 20px;
        }

        .card-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box {
            position: relative;
            width: 350px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            outline: none;
            font-family: inherit;
            background: #F8FAFC;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .feedback-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .feedback-table th {
            background: #F8FAFC;
            padding: 16px 20px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        .feedback-table td {
            padding: 18px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            vertical-align: top;
            color: var(--text-dark);
        }

        .feedback-table tr:hover {
            background: #FAFBFF;
        }

        .badge-inst {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-gmu {
            background: #dcfce7;
            color: #15803d;
        }
        
        .badge-gmit {
            background: #e0e7ff;
            color: #4338ca;
        }

        .badge-feature {
            background: #fef3c7;
            color: #d97706;
            font-weight: 700;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        /* Alert styles */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        /* Reply styling */
        .reply-section {
            margin-top: 10px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .reply-meta {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 6px;
            font-weight: 600;
        }
        .reply-content {
            font-size: 13px;
            color: var(--text-dark);
            line-height: 1.5;
            word-break: break-word;
        }
        .reply-form-container {
            margin-top: 10px;
        }
        .reply-btn-toggle {
            background: var(--primary-maroon);
            color: var(--white);
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .reply-btn-toggle:hover {
            background: var(--dark-maroon);
        }
        .btn-reply-submit {
            background: #10b981;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-reply-submit:hover {
            background: #059669;
        }
        .reply-textarea {
            width: 100%;
            min-height: 60px;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            margin-bottom: 8px;
            resize: vertical;
            outline: none;
        }
        .reply-textarea:focus {
            border-color: var(--primary-maroon);
        }
        .btn-template {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-template:hover {
            background: #e2e8f0;
            color: #1e293b;
            border-color: #94a3b8;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2>Student Feedback</h2>
            <p>Viewing portal suggestions and general feedback from <strong><?php echo htmlspecialchars($deptLabel); ?></strong> students.</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-total"><i class="fas fa-comments"></i></div>
                <div class="stat-info">
                    <h3>Dept Submissions</h3>
                    <div class="value"><?php echo $stats['total']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-gmu"><i class="fas fa-university"></i></div>
                <div class="stat-info">
                    <h3>GMU Students</h3>
                    <div class="value"><?php echo $stats['gmu']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-gmit"><i class="fas fa-graduation-cap"></i></div>
                <div class="stat-info">
                    <h3>GMIT Students</h3>
                    <div class="value"><?php echo $stats['gmit']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-feature"><i class="fas fa-lightbulb"></i></div>
                <div class="stat-info">
                    <h3>Feature Ideas</h3>
                    <div class="value"><?php echo $stats['features']; ?></div>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-list" style="color: var(--primary-maroon);"></i> Submissions List</h3>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="feedbackSearch" placeholder="Search feedback...">
                </div>
            </div>
            
            <?php if (empty($feedbacks)): ?>
                <div style="text-align: center; padding: 50px 20px; color: var(--text-muted);">
                    <i class="fas fa-comment-slash" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p style="font-size: 16px; font-weight: 600;">No feedback submissions from your department yet.</p>
                </div>
            <?php else: ?>
                <table class="feedback-table" id="feedbackTable">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Submitted At</th>
                            <th style="width: 18%;">Student Info</th>
                            <th style="width: 22%;">General Comments</th>
                            <th style="width: 22%;">Suggested Feature</th>
                            <th style="width: 26%;">Response / Reply</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($feedbacks as $fb): ?>
                            <tr class="feedback-row" data-search="<?php 
                                echo strtolower(
                                    ($fb['student_name'] ?? '') . ' ' . 
                                    ($fb['student_id'] ?? '') . ' ' . 
                                    ($fb['branch'] ?? '') . ' ' . 
                                    ($fb['general_comments'] ?? '') . ' ' . 
                                    ($fb['new_feature_title'] ?? '') . ' ' . 
                                    ($fb['new_feature_description'] ?? '')
                                ); 
                            ?>">
                                <td style="font-size: 13px; color: var(--text-muted); font-weight: 500;">
                                    <?php echo date('d M Y', strtotime($fb['created_at'])); ?>
                                    <div style="font-size: 11px; margin-top: 4px;"><?php echo date('h:i A', strtotime($fb['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark);"><?php echo htmlspecialchars($fb['student_name'] ?? 'N/A'); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($fb['student_id'] ?? 'N/A'); ?></div>
                                    <div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                                        <span class="badge-inst badge-<?php echo strtolower($fb['institution'] ?? 'gmu'); ?>">
                                            <?php echo htmlspecialchars($fb['institution'] ?? 'GMU'); ?>
                                        </span>
                                        <?php if (($fb['current_sem'] ?? null) || ($fb['branch'] ?? null)): ?>
                                            <span style="font-size: 11px; color: #475569; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-weight: 600;">
                                                <?php 
                                                    $parts = [];
                                                    if ($fb['current_sem'] ?? null) $parts[] = 'Sem ' . $fb['current_sem'];
                                                    if ($fb['branch'] ?? null) $parts[] = $fb['branch'];
                                                    echo htmlspecialchars(implode(' • ', $parts));
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($fb['general_comments']): ?>
                                        <div style="line-height: 1.5; color: #334155; font-size: 13px;">
                                            <?php echo nl2br(htmlspecialchars($fb['general_comments'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-style: italic; font-size: 13px;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fb['new_feature_title']): ?>
                                        <span class="badge-feature">Feature Idea</span>
                                        <strong style="display: block; font-size: 13px; color: var(--primary-maroon); margin-bottom: 4px;">
                                            <?php echo htmlspecialchars($fb['new_feature_title']); ?>
                                        </strong>
                                        <div style="font-size: 13px; color: #475569; line-height: 1.5;">
                                            <?php echo nl2br(htmlspecialchars($fb['new_feature_description'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-style: italic; font-size: 13px;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($fb['admin_reply'])): ?>
                                        <div class="reply-section">
                                            <div class="reply-meta">
                                                <i class="fas fa-reply"></i> Replied by <?php echo htmlspecialchars($fb['replied_by']); ?>
                                                <div style="font-size: 9px; font-weight: normal; margin-top: 2px;">
                                                    <?php echo date('d M Y, h:i A', strtotime($fb['replied_at'])); ?>
                                                </div>
                                            </div>
                                            <div class="reply-content"><?php echo nl2br(htmlspecialchars($fb['admin_reply'])); ?></div>
                                        </div>
                                        <button class="reply-btn-toggle" onclick="toggleReplyForm(<?php echo $fb['id']; ?>)" style="margin-top: 8px;">
                                            <i class="fas fa-edit"></i> Edit Reply
                                        </button>
                                    <?php else: ?>
                                        <button class="reply-btn-toggle" onclick="toggleReplyForm(<?php echo $fb['id']; ?>)">
                                            <i class="fas fa-reply"></i> Reply
                                        </button>
                                    <?php endif; ?>

                                    <div id="reply-form-<?php echo $fb['id']; ?>" class="reply-form-container" style="display: none;">
                                        <form method="POST" action="feedback.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                            <input type="hidden" name="action" value="submit_reply">
                                            <input type="hidden" name="feedback_id" value="<?php echo $fb['id']; ?>">
                                            
                                            <div class="template-buttons" style="margin-bottom: 8px; display: flex; flex-wrap: wrap; gap: 6px;">
                                                <button type="button" class="btn-template" onclick="applyTemplate(<?php echo $fb['id']; ?>, <?php echo htmlspecialchars(json_encode('Thank you for your feedback, {STUDENT_NAME}! The issue(s) you raised have been successfully addressed. Please verify the update and let us know if you encounter any other issues.')); ?>, <?php echo htmlspecialchars(json_encode($fb['student_name'] ?? 'Student')); ?>)">
                                                    <i class="fas fa-check"></i> Resolved Msg
                                                </button>
                                                <button type="button" class="btn-template" onclick="applyTemplate(<?php echo $fb['id']; ?>, <?php echo htmlspecialchars(json_encode('Hi {STUDENT_NAME}, thank you for your feedback! We are currently looking into this issue and will update you soon.')); ?>, <?php echo htmlspecialchars(json_encode($fb['student_name'] ?? 'Student')); ?>)">
                                                    <i class="fas fa-clock"></i> Reviewing Msg
                                                </button>
                                                <button type="button" class="btn-template" onclick="applyTemplate(<?php echo $fb['id']; ?>, <?php echo htmlspecialchars(json_encode('Thank you for your feature suggestion, {STUDENT_NAME}! We will discuss this idea with our team and work on implementing it soon.')); ?>, <?php echo htmlspecialchars(json_encode($fb['student_name'] ?? 'Student')); ?>)">
                                                    <i class="fas fa-lightbulb"></i> Feature Msg
                                                </button>
                                                <button type="button" class="btn-template" onclick="applyTemplate(<?php echo $fb['id']; ?>, <?php echo htmlspecialchars(json_encode('Thank you for the kind words, {STUDENT_NAME}! We are thrilled to hear that you are finding the portal helpful. We will keep working to make your experience even better!')); ?>, <?php echo htmlspecialchars(json_encode($fb['student_name'] ?? 'Student')); ?>)">
                                                    <i class="fas fa-heart"></i> Appreciation Msg
                                                </button>
                                            </div>

                                            <textarea class="reply-textarea" name="reply_text" placeholder="Type your reply here..." required><?php echo htmlspecialchars($fb['admin_reply'] ?? ''); ?></textarea>
                                            <div style="display: flex; gap: 8px;">
                                                <button type="submit" class="btn-reply-submit">Send</button>
                                                <button type="button" class="reply-btn-toggle" style="background: #64748b;" onclick="toggleReplyForm(<?php echo $fb['id']; ?>)">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('feedbackSearch').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#feedbackTable tbody tr.feedback-row');
            rows.forEach(row => {
                const text = row.getAttribute('data-search');
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        function toggleReplyForm(id) {
            const form = document.getElementById('reply-form-' + id);
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function applyTemplate(id, templateText, studentName) {
            const form = document.getElementById('reply-form-' + id);
            if (form) {
                const textarea = form.querySelector('textarea');
                if (textarea) {
                    textarea.value = templateText.replace('{STUDENT_NAME}', studentName);
                }
            }
        }
    </script>
</body>
</html>
