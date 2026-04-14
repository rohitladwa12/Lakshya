<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'version' => APP_VERSION,
    'server_time' => time(),
    'message' => 'System Online'
]);
