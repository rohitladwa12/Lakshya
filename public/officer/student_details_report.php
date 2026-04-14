<?php
/**
 * Redirect to consolidated Reports page (Student Details section)
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

requireRole(ROLE_PLACEMENT_OFFICER);

$pageId = 'placement_officer_reports';
$q = $_GET;
$q['section'] = 'details';

SessionFilterHelper::handlePostToSession($pageId, $q);
header('Location: reports.php', true, 302);
exit;
