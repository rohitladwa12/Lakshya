<?php
/**
 * Admin - Settings Handler
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_ADMIN);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';
$db = getDB();

if ($action === 'update_setting') {
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? '';

    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Key is required']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                             VALUES (?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$key, $value, $value]);

        logMessage("System setting '{$key}' updated to '{$value}' by admin " . getUsername());
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
