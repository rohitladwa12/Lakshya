<?php
/**
 * Lakshya Evidence Vault - Authorized Snapshot Streaming Endpoint
 * Restricted to authenticated faculty and officers.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Prevent caching and sniffing
header('Cache-Control: private, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Require faculty/officer authorization
requireAnyRole([ROLE_PLACEMENT_OFFICER, ROLE_INTERNSHIP_OFFICER, ROLE_DEPT_COORDINATOR, ROLE_ADMIN, ROLE_HOD]);

$eventId = filter_var($_GET['event_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$eventId || $eventId <= 0) {
    http_response_code(400);
    die('Invalid event identifier.');
}

$db = getDB();
$stmt = $db->prepare("SELECT `snapshot_path`, `sha256`, `mime_type` FROM `assessment_integrity_events` WHERE `id` = ? LIMIT 1");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event || empty($event['snapshot_path'])) {
    http_response_code(404);
    die('Evidence snapshot not found.');
}

$baseVaultDir = realpath(__DIR__ . '/../../storage/proctor_vault');
$targetPath   = realpath(__DIR__ . '/../../' . $event['snapshot_path']);

// Path traversal defense
if (!$targetPath || strpos($targetPath, $baseVaultDir) !== 0 || !file_exists($targetPath)) {
    http_response_code(403);
    die('Access to the requested evidence is forbidden.');
}

// Validate real image mime
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $targetPath);
finfo_close($finfo);

if (!in_array($realMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    die('Invalid evidence media type.');
}

header('Content-Type: ' . $realMime);
header('Content-Length: ' . filesize($targetPath));

readfile($targetPath);
exit;