<?php
/**
 * Placement Officer - Student Feedback Review Page
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$fullName = getFullName();
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
                $stmt->execute([$replyText, $fullName . ' (Placement Officer)', $feedbackId]);
                Session::flash('success', 'Reply submitted successfully.');
            } catch (Exception $e) {
                Session::flash('error', 'Error submitting reply: ' . $e->getMessage());
            }
        }
        redirect('feedback.php');
        exit;
    }
}

// Fetch feedback
$feedbacks = [];
$stats = [
    'total' => 0,
    'gmu' => 0,
    'gmit' => 0,
    'features' => 0
];

try {
    $feedbacks = $db->query("SELECT * FROM portal_feedback ORDER BY created_at DESC")->fetchAll();
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
    error_log("Error fetching feedbacks: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Feedback – <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--page-bg);
            color: var(--text-primary);
            padding-top: 0;
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 40px;
        }

        /* Header Section */
        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            background: var(--card-bg);
            padding: 24px 30px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .welcome-text h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            color: var(--maroon);
            letter-spacing: -0.02em;
        }

        .welcome-text p {
            color: var(--text-secondary);
            margin: 4px 0 0 0;
            font-size: 13.5px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.15s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-label {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
            display: block;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 800;
            color: var(--maroon);
            display: block;
        }

        .stat-footer {
            margin-top: 8px;
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .content-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            box-shadow: var(--shadow-sm);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .card-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-box {
            position: relative;
            width: 320px;
        }

        .search-box input {
            width: 100%;
            padding: 9px 14px 9px 38px;
            border-radius: var(--radius-sm);
            border: 1px solid #D1D5DB;
            outline: none;
            font-family: inherit;
            font-size: 13.5px;
            background: #FFFFFF;
            color: var(--text-primary);
            transition: all 0.15s ease;
        }

        .search-box input:focus {
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(124, 0, 0, 0.12);
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
        }

        .modern-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .modern-table th {
            text-align: left;
            padding: 12px 16px;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: #F9FAFB;
            border-bottom: 1px solid var(--border-color);
        }

        .modern-table td {
            padding: 14px 16px;
            background: #FFFFFF;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
            font-size: 13.5px;
        }

        .modern-table tr:hover td {
            background: #FDFEFE;
        }

        .modern-table tr:last-child td {
            border-bottom: none;
        }

        .back-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 16px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            transition: color 0.15s ease;
        }

        .back-link:hover {
            color: var(--maroon);
        }

        .badge-inst {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-gmu {
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #D1FAE5;
        }
        
        .badge-gmit {
            background: #EFF6FF;
            color: #1D4ED8;
            border: 1px solid #DBEAFE;
        }

        .badge-feature {
            background: #FEF3C7;
            color: #B45309;
            border: 1px solid #FDE68A;
            font-weight: 700;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        /* Alert styles */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #A7F3D0;
        }
        .alert-error {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }

        /* Reply styling */
        .reply-section {
            margin-top: 10px;
            padding: 12px 14px;
            background: #F9FAFB;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
        }
        .reply-meta {
            font-size: 11px;
            color: var(--text-secondary);
            margin-bottom: 4px;
            font-weight: 600;
        }
        .reply-content {
            font-size: 13px;
            color: var(--text-primary);
            line-height: 1.5;
            word-break: break-word;
        }
        .reply-form-container {
            margin-top: 10px;
        }
        .reply-btn-toggle {
            background: var(--maroon);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .reply-btn-toggle:hover {
            background: var(--maroon-dark);
        }
        .btn-reply-submit {
            background: #059669;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-reply-submit:hover {
            background: #047857;
        }
        .reply-textarea {
            width: 100%;
            min-height: 60px;
            padding: 8px 12px;
            border: 1px solid #D1D5DB;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 13px;
            margin-bottom: 8px;
            resize: vertical;
            outline: none;
            background: #FFFFFF;
            color: var(--text-primary);
        }
        .reply-textarea:focus {
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(124, 0, 0, 0.12);
        }
        .btn-template {
            background: #F3F4F6;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-template:hover {
            background: #E5E7EB;
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="dashboard-container">
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
        
        <!-- Header -->
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>Student Feedback & Suggestions</h1>
                <p>Monitor feature requests, general comments, and new portal ideas submitted by students.</p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-label">Total Submissions</span>
                <span class="stat-value"><?php echo $stats['total']; ?></span>
                <div class="stat-footer"><i class="fas fa-comments"></i> Feedbacks received</div>
            </div>
            <div class="stat-card">
                <span class="stat-label">GMU Students</span>
                <span class="stat-value"><?php echo $stats['gmu']; ?></span>
                <div class="stat-footer"><i class="fas fa-university"></i> Submissions from GMU</div>
            </div>
            <div class="stat-card">
                <span class="stat-label">GMIT Students</span>
                <span class="stat-value"><?php echo $stats['gmit']; ?></span>
                <div class="stat-footer"><i class="fas fa-graduation-cap"></i> Submissions from GMIT</div>
            </div>
            <div class="stat-card">
                <span class="stat-label">Feature Ideas</span>
                <span class="stat-value"><?php echo $stats['features']; ?></span>
                <div class="stat-footer"><i class="fas fa-lightbulb"></i> New suggestions</div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-list" style="color: var(--brand);"></i> Submissions List</h3>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="feedbackSearch" placeholder="Search feedback...">
                </div>
            </div>
            
            <?php if (empty($feedbacks)): ?>
                <div style="text-align: center; padding: 50px 20px; color: var(--text-muted);">
                    <i class="fas fa-comment-slash" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p style="font-size: 16px; font-weight: 600;">No feedback submissions yet.</p>
                </div>
            <?php else: ?>
                <table class="modern-table" id="feedbackTable">
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
                                    <?php if (!empty($fb['general_comments'])): ?>
                                        <div style="line-height: 1.5; color: #334155; font-size: 13px;">
                                            <?php echo nl2br(htmlspecialchars((string)$fb['general_comments'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-style: italic; font-size: 13px;">None</span>
                                    <?php endif; ?>

                                    <?php if (!empty($fb['attachment_path'])): ?>
                                        <div style="margin-top: 8px;">
                                            <a href="<?php echo APP_URL . '/' . htmlspecialchars($fb['attachment_path']); ?>" target="_blank" rel="noopener noreferrer" class="btn-template" style="color: var(--maroon); border-color: #FECACA; background: #FEF2F2; font-weight: 600; text-decoration: none;">
                                                <i class="fas fa-paperclip"></i> View Proof
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($fb['new_feature_title'])): ?>
                                        <span class="badge-feature">Feature Idea</span>
                                        <strong style="display: block; font-size: 13px; color: var(--brand); margin-bottom: 4px;">
                                            <?php echo htmlspecialchars((string)$fb['new_feature_title']); ?>
                                        </strong>
                                        <div style="font-size: 13px; color: #475569; line-height: 1.5;">
                                            <?php echo nl2br(htmlspecialchars((string)$fb['new_feature_description'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-style: italic; font-size: 13px;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($fb['admin_reply'])): ?>
                                        <div class="reply-section">
                                            <div class="reply-meta">
                                                <i class="fas fa-reply"></i> Replied by <?php echo htmlspecialchars((string)$fb['replied_by']); ?>
                                                <div style="font-size: 9px; font-weight: normal; margin-top: 2px;">
                                                    <?php echo date('d M Y, h:i A', strtotime($fb['replied_at'])); ?>
                                                </div>
                                            </div>
                                            <div class="reply-content"><?php echo nl2br(htmlspecialchars((string)$fb['admin_reply'])); ?></div>
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
