<?php
/**
 * VC Dashboard - Coordinators
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

requireRole(ROLE_VC);

$fullName = getFullName();
$GLOBALS['fullName'] = $fullName;
$db = getDB();

// Get Coordinators list
$coordinators = $db->query("
    SELECT *, 
    (SELECT COUNT(*) FROM coordinator_tasks WHERE coordinator_id = dept_coordinators.id) as total_tasks 
    FROM dept_coordinators 
    WHERE is_active = 1
    ORDER BY institution, department
")->fetchAll(PDO::FETCH_ASSOC);

renderVCHeader("Department Coordinators");
?>

<div class="header">
    <div class="view-title">
        <h2>Department Coordinators</h2>
        <p>Department-level oversight and task management.</p>
    </div>
</div>

<div class="table-container">
    <div style="padding: 20px; border-bottom: 1px solid var(--border);">
        <h3 style="font-size: 18px; font-weight: 700; color: var(--primary-maroon);">Active Coordinators</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Coordinator Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Institution</th>
                    <th>Tasks</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coordinators as $c): ?>
                <tr>
                    <td><span class="student-name"><?php echo htmlspecialchars($c['full_name']); ?></span></td>
                    <td style="color: var(--text-muted);"><?php echo htmlspecialchars($c['email']); ?></td>
                    <td style="font-weight: 600; color: var(--primary-maroon);"><?php echo htmlspecialchars($c['department']); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($c['institution']); ?>"><?php echo $c['institution']; ?></span></td>
                    <td style="font-weight: 700;"><?php echo $c['total_tasks']; ?></td>
                    <td>
                        <form method="POST" action="coordinator_tasks.php" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                            <button type="submit" class="btn-view" style="border:none; cursor:pointer;">
                                <i class="fas fa-tasks"></i> View Tasks
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($coordinators)): ?>
                <tr><td colspan="6" style="padding: 40px; text-align: center; color: var(--text-muted);">No active coordinators found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderVCFooter(); ?>
