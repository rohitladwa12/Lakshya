<?php
/**
 * VC Dashboard - Coordinator Tasks
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

requireRole(ROLE_VC);

$id = (int)($_REQUEST['id'] ?? 0);
if (!$id) {
    header('Location: coordinators.php');
    exit;
}

$fullName = getFullName();
$GLOBALS['fullName'] = $fullName;
$db = getDB();

// Get Coordinator Name
$stmt = $db->prepare("SELECT full_name, department, institution FROM dept_coordinators WHERE id = ?");
$stmt->execute([$id]);
$coord = $stmt->fetch(PDO::FETCH_ASSOC);

// Get Tasks
$stmt = $db->prepare("
    SELECT *, 
    (SELECT COUNT(*) FROM task_completions WHERE task_id = coordinator_tasks.id) as completion_count 
    FROM coordinator_tasks 
    WHERE coordinator_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderVCHeader($coord['full_name'] . " - Tasks");
?>

<div class="header">
    <div class="view-title">
        <h2><?php echo htmlspecialchars($coord['full_name']); ?> <small style="color: var(--text-muted); font-size: 18px;">(<?php echo htmlspecialchars($coord['department']); ?> @ <?php echo $coord['institution']; ?>)</small></h2>
        <p>Monitoring assignment distribution and completion rates.</p>
    </div>
    <a href="coordinators.php" class="btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Task Title</th>
                    <th>Type</th>
                    <th>Created</th>
                    <th>Deadline</th>
                    <th>Completions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                <tr>
                    <td><span class="student-name"><?php echo htmlspecialchars($t['title']); ?></span></td>
                    <td><span class="badge" style="background:#f1f5f9; color:#475569;"><?php echo htmlspecialchars($t['task_type']); ?></span></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($t['created_at'])); ?></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($t['deadline'])); ?></td>
                    <td style="font-weight: 800; color: var(--primary-maroon); font-size: 18px;"><?php echo $t['completion_count']; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tasks)): ?>
                <tr><td colspan="5" style="padding: 40px; text-align: center; color: var(--text-muted);">No tasks found for this coordinator.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderVCFooter(); ?>
