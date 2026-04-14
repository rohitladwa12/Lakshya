<?php
/**
 * Officer Logout
 * Destroys officer session and redirects to login
 */

require_once __DIR__ . '/../config/bootstrap.php';

// Clear session using standard Session class
Session::logout();

// Redirect to login
redirect('login.php');
exit;
