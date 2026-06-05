<?php
/**
 * Bootstrap File
 * Initializes the application by loading all necessary files
 */
define('APP_VERSION', '1.1.0_async'); // Update this to force CSS/JS refresh

// Load Composer Autoloader
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Load helper functions (needed for loadEnv)
require_once ROOT_PATH . '/src/Helpers/functions.php';



// Load environment variables
loadEnv(ROOT_PATH . '/.env');

// Load configuration files
require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/database.php';

// Initialize Redis and Session Handler (MUST stay before session.php)
require_once ROOT_PATH . '/src/Helpers/RedisHelper.php';
use App\Helpers\RedisHelper;

$redisHelper = RedisHelper::getInstance();
if ($redisHelper->isConnected()) {
    try {
        // Register Predis as the session handler
        $handler = new \Predis\Session\Handler($redisHelper->getClient(), [
            'gc_maxlifetime' => 3600 * 24 // 24 hours
        ]);
        $handler->register();
    } catch (Exception $e) {
        error_log("Redis Session Handler Registration Failed: " . $e->getMessage());
        // Fall back to default PHP session handler automatically
    }
}

require_once ROOT_PATH . '/config/session.php';

// --- CSRF SECURITY SHIELD ---
if (php_sapi_name() !== 'cli') {
    // 1. Initialize Token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // 2. Global Protection for all POST requests
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // List of files that are allowed to skip CSRF (e.g., specialized webhooks)
        $skipCSRF = ['notifications_stream.php', 'ai_worker.php'];
        
        // Skip CSRF check for HOD Portal SSO when MENTOR_ID or emp_id is passed
        if (isset($_REQUEST['MENTOR_ID']) || isset($_REQUEST['mentor_id']) || isset($_REQUEST['emp_id'])) {
            $skipCSRF[] = 'dashboard.php';
            $skipCSRF[] = 'dashboard';
            $skipCSRF[] = 'ai_monitor.php';
            $skipCSRF[] = 'ai_monitor';
            $skipCSRF[] = 'login.php';
            $skipCSRF[] = 'login';
        }
        
        $currentFile = strtolower(basename($_SERVER['SCRIPT_FILENAME']));
        $currentFileSelf = strtolower(basename($_SERVER['PHP_SELF']));
        
        if (!in_array($currentFile, $skipCSRF) && !in_array($currentFileSelf, $skipCSRF)) {
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $headerToken = $headers['X-CSRF-TOKEN'] ?? $headers['x-csrf-token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            $postToken = $_POST['csrf_token'] ?? '';

            if (isset($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $headerToken) && !hash_equals($_SESSION['csrf_token'], $postToken)) {
                $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') || 
                          !empty($headerToken) ||
                          (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
                    exit;
                } else {
                    die("CSRF token mismatch. Please refresh the page.");
                }
            }
        }
    }
}

// Load base model
require_once ROOT_PATH . '/src/Models/Model.php';

// Autoload models (simple autoloader)
spl_autoload_register(function ($class) {
    // Simple namespace stripper for basic autoloader
    $parts = explode('\\', $class);
    $className = end($parts);
    
    $modelFile = ROOT_PATH . '/src/Models/' . $className . '.php';
    if (file_exists($modelFile)) {
        require_once $modelFile;
        return true;
    }
    
    $controllerFile = ROOT_PATH . '/src/Controllers/' . $className . '.php';
    if (file_exists($controllerFile)) {
        require_once $controllerFile;
        return true;
    }
    
    $serviceFile = ROOT_PATH . '/src/Services/' . $className . '.php';
    if (file_exists($serviceFile)) {
        require_once $serviceFile;
        return true;
    }

    $helperFile = ROOT_PATH . '/src/Helpers/' . $className . '.php';
    if (file_exists($helperFile)) {
        require_once $helperFile;
        return true;
    }
    
    return false;
});

// Error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $errorMessage = "Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}";
    logMessage($errorMessage, 'ERROR');
    
    if (getenv('APP_ENV') !== 'production') {
        // Check if we are in an AJAX or JSON context
        $isJson = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                  (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
                  isAjax();

        if ($isJson) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['success' => false, 'message' => "Error: {$errstr} in {$errfile} on line {$errline}"]);
            exit;
        }

        echo "<div style='background:#f8d7da;color:#721c24;padding:15px;border:1px solid #f5c6cb;border-radius:5px;margin:10px;'>";
        echo "<strong>Error:</strong> {$errstr}<br>";
        echo "<strong>File:</strong> {$errfile}<br>";
        echo "<strong>Line:</strong> {$errline}";
        echo "</div>";
    }
    
    return true;
});

// Exception handler
set_exception_handler(function($exception) {
    $errorMessage = "Uncaught Exception: " . $exception->getMessage() . 
                    " in " . $exception->getFile() . 
                    " on line " . $exception->getLine();
    logMessage($errorMessage, 'EXCEPTION');
    
    if (getenv('APP_ENV') !== 'production') {
        $isJson = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                  (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
                  isAjax();

        if ($isJson) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'success' => false, 
                'message' => 'Uncaught Exception: ' . $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]);
            exit;
        }

        echo "<div style='background:#f8d7da;color:#721c24;padding:20px;border:1px solid #f5c6cb;border-radius:5px;margin:10px;'>";
        echo "<h3>Uncaught Exception</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
    } else {
        http_response_code(500);
        echo "<h1>500 Internal Server Error</h1>";
        echo "<p>An error occurred. Please try again later.</p>";
    }
});

// Shutdown handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $errorMessage = "Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}";
        logMessage($errorMessage, 'FATAL');
    }
});

// --- GLOBAL MAINTENANCE MODE CHECK ---
if (file_exists(ROOT_PATH . '/src/maintenance.lock')) {
    $currentFile = basename($_SERVER['SCRIPT_NAME']);
    // Allow Maintenance page, Admin pages, and AJAX handlers (if admin)
    $isMaintenancePage = ($currentFile === 'maintenance.php' || $currentFile === 'login.php');
    $isAdminRole = (isLoggedIn() && getRole() === ROLE_ADMIN);
    
    // Check if the current URL path contains '/admin/'
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $isAdminPath = (strpos($requestUri, '/admin/') !== false || strpos($requestUri, '/placement_officer/') !== false);

    if (!$isMaintenancePage && !$isAdminRole) {
        // --- AJAX/JSON Awareness Enhancement ---
        $isJson = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                  (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
                  (function_exists('isAjax') && isAjax());

        if ($isJson) {
            http_response_code(503); // Service Unavailable
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'success' => false, 
                'maintenance' => true,
                'message' => 'The site is undergoing scheduled maintenance. Your progress might be temporarily paused. Please try again in a few minutes.'
            ]);
            exit;
        }

        $extension = pathinfo($currentFile, PATHINFO_EXTENSION);
        $publicAssets = ['css', 'js', 'png', 'jpg', 'jpeg', 'svg', 'gif', 'woff', 'woff2'];
        
        if (!in_array($extension, $publicAssets)) {
            // Calculate base path from APP_URL to stay within project
            $urlParts = parse_url(APP_URL);
            $basePath = rtrim($urlParts['path'] ?? '', '/');
            header("Location: " . $basePath . "/maintenance.php", true, 303);
            exit;
        }
    }
}


