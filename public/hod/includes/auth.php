<?php
/**
 * HOD Portal Authentication & Auto-Login Interceptor
 */

$mentorId = '';
if (isset($_REQUEST['emp_id'])) {
    $mentorId = trim((string)$_REQUEST['emp_id']);
} elseif (isset($_REQUEST['MENTOR_ID'])) {
    $mentorId = trim((string)$_REQUEST['MENTOR_ID']);
} elseif (isset($_REQUEST['mentor_id'])) {
    $mentorId = trim((string)$_REQUEST['mentor_id']);
}

if (!empty($mentorId)) {
    $remoteDB = getDB('gmu');
    if ($remoteDB) {
        $stmt = $remoteDB->prepare("SELECT * FROM users WHERE ID = ? AND STATUS = 'ACTIVE' LIMIT 1");
        $stmt->execute([$mentorId]);
        $userRow = $stmt->fetch();
        
        if ($userRow) {
            // Normalize department/discipline
            $department = trim((string)($userRow['DISCIPLINE'] ?? ''));
            if (empty($department)) {
                 $department = 'CSE';
            }
            
            $photo = $userRow['PHOTO'] ?? null;
            $role = 'hod';
            
            // Log in the HOD user dynamically in session
            Session::setUser(
                $userRow['SL_NO'],
                $userRow['USER_NAME'],
                $role,
                $userRow['NAME'] ?? $userRow['USER_NAME'],
                'GMU',
                $department,
                $photo
            );
            
            trackActivity('erp_auto_login', 'HOD logged in via ERP MENTOR_ID link', [
                'mentor_id' => $mentorId,
                'department' => $department
            ]);
            
            // Redirect to the same page via GET to establish the session cookie in the browser
            $redirectUrl = strtok($_SERVER['REQUEST_URI'], '?');
            header("Location: $redirectUrl");
            exit;
        }
    }
}

// Enforce HOD, PO, or Admin authorization
requireAnyRole([ROLE_HOD, ROLE_ADMIN, ROLE_PLACEMENT_OFFICER]);
