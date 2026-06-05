<?php
/**
 * Server Status API — called async from admin dashboard
 * Never blocks the main page render
 */
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_ADMIN);

header('Content-Type: application/json');
header('Cache-Control: no-store');

echo json_encode([
    'gmu'  => Database::checkConnection('gmu'),
    'gmit' => Database::checkConnection('gmit'),
]);
