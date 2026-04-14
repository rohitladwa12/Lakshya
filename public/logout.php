<?php
/**
 * Logout Page
 */

require_once __DIR__ . '/../config/bootstrap.php';

// Log logout
trackActivity('logout', 'User logged out');

// Logout user
Session::logout();

// Set success message
Session::flash('success', 'You have been logged out successfully');

// Redirect to login page
redirect('login.php');
