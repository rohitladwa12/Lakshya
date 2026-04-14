<?php
/**
 * Session Management
 * Handles user sessions and authentication state
 */

class Session {
    private static $started = false;
    
    public static function start() {
        if (self::$started) {
            return;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            // Configure session
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
            ini_set('session.cookie_samesite', 'Lax');
            
            session_name(SESSION_NAME);
            session_start();
            
            self::$started = true;

            // Check for idle timeout (10 minutes)
            if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
                if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT)) {
                    // Session expired due to inactivity
                    self::logout();
                    if (!isAjax()) {
                         Session::flash('error', 'Session expired. Please login again.');
                         header("Location: /Lakshya/login.php");
                         exit;
                    }
                } else {
                    // Update last activity time
                    $_SESSION['last_activity'] = time();
                }
            }
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    public static function has($key) {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    public static function remove($key) {
        self::start();
        unset($_SESSION[$key]);
    }
    
    public static function destroy() {
        self::start();
        $_SESSION = [];
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
        self::$started = false;
    }
    
    public static function flash($key, $value = null) {
        self::start();
        
        if ($value === null) {
            // Get and remove flash message
            $message = self::get('flash_' . $key);
            self::remove('flash_' . $key);
            return $message;
        } else {
            // Set flash message
            self::set('flash_' . $key, $value);
        }
    }
    
    // Authentication helpers
    public static function setUser($userId, $username, $role, $fullName, $institution = null, $department = null) {
        self::set('user_id', $userId);
        self::set('username', $username);
        self::set('role', $role);
        self::set('full_name', $fullName);
        self::set('institution', $institution);
        if ($department !== null) {
            self::set('department', $department);
        } else {
            self::remove('department');
        }
        self::set('logged_in', true);
        self::set('login_time', time());
        self::set('last_activity', time());
    }
    
    public static function isLoggedIn() {
        return self::get('logged_in', false) === true;
    }
    
    public static function getUserId() {
        return self::get('user_id');
    }
    
    public static function getUsername() {
        return self::get('username');
    }
    
    public static function getRole() {
        return self::get('role');
    }
    
    public static function getFullName() {
        return self::get('full_name');
    }
    
    public static function getInstitution() {
        return self::get('institution');
    }

    public static function getDepartment() {
        return self::get('department');
    }
    
    public static function hasRole($role) {
        return self::getRole() === $role;
    }
    
    public static function hasAnyRole($roles) {
        return in_array(self::getRole(), $roles);
    }
    
    public static function logout() {
        self::destroy();
    }
}

// Auto-start session
Session::start();

// Helper functions
function isLoggedIn() {
    return Session::isLoggedIn();
}

function requireLogin() {
    if (!isLoggedIn()) {
        if (isAjax() || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
            exit;
        }
        Session::flash('error', 'Please login to continue');
        redirect('/Lakshya/login');
    }
}

function requireRole($role) {
    requireLogin();
    // Demo user can bypass all role checks for viewing
    if (Session::getRole() === ROLE_DEMO) {
        return;
    }
    if (!Session::hasRole($role)) {
        if (isAjax() || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        Session::flash('error', 'Access denied. Insufficient permissions.');
        redirect('/Lakshya/');
    }

    // GMIT SGPA Enforcement
    if ($role === ROLE_STUDENT && Session::getInstitution() === INSTITUTION_GMIT) {
        // No forced redirect anymore. We will block cards on the dashboard instead.
    }
}

function requireAnyRole($roles) {
    requireLogin();
    // Demo user can bypass all role checks for viewing
    if (Session::getRole() === ROLE_DEMO) {
        return;
    }
    if (!Session::hasAnyRole($roles)) {
        Session::flash('error', 'Access denied. Insufficient permissions.');
        redirect('/Lakshya/');
    }
}

function getUserId() {
    return Session::getUserId();
}

function getUsername() {
    return Session::getUsername();
}

function getRole() {
    return Session::getRole();
}

function getFullName() {
    return Session::getFullName();
}

function getInstitution() {
    return Session::getInstitution();
}

function getDepartment() {
    return Session::getDepartment();
}

/**
 * Student identifier for unified_ai_assessments and mock_ai_interview_sessions.
 * GMIT users: store USN (username) because user_id can be 0 when stored in INT.
 * GMU users: store user_id as string for consistent VARCHAR column.
 */
function getStudentIdForAssessment() {
    //$inst = getInstitution();
    //if ($inst === INSTITUTION_GMIT || $inst === 'GMIT') {
    //    return getUsername(); // USN for GMIT
    //}
    //$uid = getUserId();
    //return $uid !== null && $uid !== '' ? (string) $uid : getUsername();
	return getUsername();
}

function hasRole($role) {
    return Session::hasRole($role);
}

/**
 * Global Demo Mode Protection
 * Prevent any POST modifications for demo users
 */
if (isLoggedIn() && getRole() === ROLE_DEMO && isPost()) {
    if (isAjax() || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Action Denied: Demo users cannot modify data.'
        ]);
        exit;
    } else {
        Session::flash('error', 'Action Denied: Demo users cannot modify data.');
        $referer = $_SERVER['HTTP_REFERER'] ?? '/Lakshya/';
        header("Location: $referer");
        exit;
    }
}
