<?php
/**
 * Redirect to consolidated Students & Reports page (AI Reports tab)
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

requireRole(ROLE_DEPT_COORDINATOR);

$pageId = 'coordinator_students_report';
$q = $_GET;
$q['section'] = 'reports';
if (!isset($q['inst'])) $q['inst'] = 'all';

SessionFilterHelper::handlePostToSession($pageId, $q);
header('Location: students_report.php', true, 302);
exit;
