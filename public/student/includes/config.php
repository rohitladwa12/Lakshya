<?php
/**
 * Resume Builder Specific Configuration
 */
require_once __DIR__ . '/../../../config/bootstrap.php';

// Ensure the student is logged in
if (!function_exists('require_role')) {
    function require_role($role) {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
            header('Location: ../login.php');
            exit;
        }
    }
}
?>
