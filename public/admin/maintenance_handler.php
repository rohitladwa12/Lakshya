<?php
require_once __DIR__ . '/../../config/bootstrap.php';

// Only allow Admins
requireRole(ROLE_ADMIN);

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$lockFile = ROOT_PATH . '/src/maintenance.lock';

switch ($action) {
    case 'toggle':
        if (file_exists($lockFile)) {
            unlink($lockFile);
            logMessage("Maintenance Mode DISABLED by admin " . getUsername());
            echo json_encode(['success' => true, 'status' => 'off', 'message' => 'Maintenance Mode Disabled']);
        } else {
            file_put_contents($lockFile, date('Y-m-d H:i:s'));
            logMessage("Maintenance Mode ENABLED by admin " . getUsername());
            echo json_encode(['success' => true, 'status' => 'on', 'message' => 'Maintenance Mode Enabled']);
        }
        break;

    case 'status':
        echo json_encode([
            'success' => true, 
            'active' => file_exists($lockFile),
            'timestamp' => file_exists($lockFile) ? file_get_contents($lockFile) : null
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
