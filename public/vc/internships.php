<?php
/**
 * VC Dashboard - Internships
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

requireRole(ROLE_VC);

$fullName = getFullName();
$GLOBALS['fullName'] = $fullName;
$db = getDB();

// Get Internships Postings
$postings = $db->query("
    SELECT i.*, ao.full_name as officer_name, ao.institution,
    (SELECT COUNT(*) FROM internship_applications WHERE internship_id = i.id) as applicant_count 
    FROM internships i
    JOIN app_officers ao ON i.created_by = ao.id
    ORDER BY i.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

renderVCHeader("Internships Analysis");
?>

<div class="header">
    <div class="view-title">
        <h2>Internships Analysis</h2>
        <p>Institutional internship opportunities and student engagement.</p>
    </div>
</div>

<div class="table-container">
    <div style="padding: 20px; border-bottom: 1px solid var(--border);">
        <h3 style="font-size: 18px; font-weight: 700; color: var(--primary-maroon);">Internship Postings</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Company</th>
                    <th>Officer</th>
                    <th>Posted</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Apps</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($postings as $p): ?>
                <tr>
                    <td><span class="student-name"><?php echo htmlspecialchars($p['internship_title']); ?></span></td>
                    <td style="font-weight: 600; color: var(--primary-maroon);"><?php echo htmlspecialchars($p['company_name']); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($p['institution']); ?>"><?php echo $p['officer_name']; ?></span></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($p['created_at'])); ?></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?php echo $p['application_deadline'] ? date('d M Y', strtotime($p['application_deadline'])) : 'N/A'; ?></td>
                    <td><span class="badge <?php echo $p['status'] === 'Active' ? 'badge-gmit' : 'badge-gmu'; ?>"><?php echo $p['status']; ?></span></td>
                    <td style="font-weight: 700;"><?php echo $p['applicant_count']; ?></td>
                    <td>
                        <form method="POST" action="posting_details.php" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <input type="hidden" name="type" value="internship">
                            <button type="submit" class="btn-view" style="border:none; cursor:pointer;">
                                <i class="fas fa-users"></i> Analyze
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($postings)): ?>
                <tr><td colspan="8" style="padding: 40px; text-align: center; color: var(--text-muted);">No internship postings found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderVCFooter(); ?>
