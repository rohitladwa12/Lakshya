<?php
/**
 * VC Dashboard - Placements
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

requireRole(ROLE_VC);

$fullName = getFullName();
$GLOBALS['fullName'] = $fullName;
$db = getDB();

// Get Placements Postings
$postings = $db->query("
    SELECT jp.*, c.name as company_name, ao.full_name as officer_name, ao.institution,
    (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as applicant_count 
    FROM job_postings jp
    JOIN companies c ON jp.company_id = c.id
    JOIN app_officers ao ON jp.posted_by = ao.id
    ORDER BY jp.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

renderVCHeader("Placements Analysis");
?>

<div class="header">
    <div class="view-title">
        <h2>Placements Analysis</h2>
        <p>Institutional placement outreach and student applications.</p>
    </div>
</div>

<div class="table-container">
    <div style="padding: 20px; border-bottom: 1px solid var(--border);">
        <h3 style="font-size: 18px; font-weight: 700; color: var(--primary-maroon);">Job Postings</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Job Title</th>
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
                    <td><span class="student-name"><?php echo htmlspecialchars($p['title']); ?></span></td>
                    <td style="font-weight: 600; color: var(--primary-maroon);"><?php echo htmlspecialchars($p['company_name']); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($p['institution']); ?>"><?php echo $p['officer_name']; ?></span></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($p['created_at'])); ?></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($p['application_deadline'])); ?></td>
                    <td><span class="badge <?php echo $p['status'] === 'Active' ? 'badge-gmit' : 'badge-gmu'; ?>"><?php echo $p['status']; ?></span></td>
                    <td style="font-weight: 700;"><?php echo $p['applicant_count']; ?></td>
                    <td>
                        <form method="POST" action="posting_details.php" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <input type="hidden" name="type" value="job">
                            <button type="submit" class="btn-view" style="border:none; cursor:pointer;">
                                <i class="fas fa-users"></i> Analyze
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($postings)): ?>
                <tr><td colspan="8" style="padding: 40px; text-align: center; color: var(--text-muted);">No job postings found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderVCFooter(); ?>
