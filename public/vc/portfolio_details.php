<?php
/**
 * VC Dashboard - Student Portfolio Details (AJAX)
 * Returns Skills & Projects added by a student + verified status.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
if (!defined('BYPASS_ROLE_CHECK')) {
    requireRole(ROLE_VC);
}

header('Content-Type: application/json');

$usn = trim((string)($_POST['usn'] ?? $_GET['usn'] ?? ''));
$institution = trim((string)($_POST['institution'] ?? $_GET['institution'] ?? ''));

if ($usn === '' || !in_array($institution, [INSTITUTION_GMU, INSTITUTION_GMIT], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

try {
    $db = getDB();

    $stmtCol = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_portfolio' AND COLUMN_NAME = 'is_verified'");
    $stmtCol->execute();
    $hasIsVerified = ((int)$stmtCol->fetchColumn() > 0);

    $selectVerified = $hasIsVerified ? "is_verified" : "0 as is_verified";
    $stmt = $db->prepare("
        SELECT id, category, title, sub_title, description, link, {$selectVerified} as is_verified
        FROM student_portfolio
        WHERE student_id = ? AND institution = ?
          AND category IN ('Skill','Project')
        ORDER BY category ASC, created_at DESC, id DESC
    ");
    $stmt->execute([$usn, $institution]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $skills = [];
    $projects = [];
    foreach ($items as $it) {
        $payload = [
            'id' => $it['id'],
            'category' => $it['category'],
            'title' => $it['title'],
            'sub_title' => $it['sub_title'],
            'description' => $it['description'],
            'link' => $it['link'],
            'is_verified' => (int)($it['is_verified'] ?? 0),
        ];
        if (($it['category'] ?? '') === 'Skill') $skills[] = $payload;
        if (($it['category'] ?? '') === 'Project') $projects[] = $payload;
    }

    echo json_encode([
        'success' => true,
        'skills' => $skills,
        'projects' => $projects,
    ]);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to load portfolio.']);
    exit;
}
