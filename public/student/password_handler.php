<?php
/**
 * Student Password Handler
 * Processes password change requests for students (handles GMIT bcrypt update)
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Ensure user is logged in as student
requireRole(ROLE_STUDENT);

$institution = getInstitution();
if ($institution !== INSTITUTION_GMIT) {
    jsonError('Password change is not allowed for your institution');
}

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
    
    if (strlen($newPassword) < 6) {
        jsonError('Password must be at least 6 characters long');
    }
    
    $userModel = new User();
    $userId = getUserId();
    $institution = getInstitution();
    
    $result = $userModel->changePassword($userId, $institution, $currentPassword, $newPassword);
    
    if ($result['success']) {
        logMessage("Password changed for student ID: " . $userId . " (" . $institution . ")", 'INFO');
        jsonSuccess([], 'Password changed successfully');
    } else {
        jsonError($result['message']);
    }
} else {
    jsonError('Invalid request method');
}
