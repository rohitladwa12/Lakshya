<?php
/**
 * Password Handler
 * Processes password change requests for coordinators
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Ensure user is logged in as coordinator
requireRole(ROLE_DEPT_COORDINATOR);

if (isPost()) {
    $currentPassword = post('current_password');
    $newPassword = post('new_password');
    $confirmPassword = post('confirm_password');
    
    // Basic validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        jsonError('All fields are required');
    }
    
    if ($newPassword !== $confirmPassword) {
        jsonError('New passwords do not match');
    }
    
    if (!isValidPassword($newPassword)) {
        jsonError('Password must be at least 8 characters long and include an uppercase letter, a number, and a special character');
    }
    
    $userModel = new User();
    $userId = getUserId();
    
    $result = $userModel->changeCoordinatorPassword($userId, $currentPassword, $newPassword);
    
    if ($result['success']) {
        logMessage("Password changed for coordinator ID: " . $userId, 'INFO');
        jsonSuccess([], 'Password changed successfully');
    } else {
        jsonError($result['message']);
    }
} else {
    jsonError('Invalid request method');
}
