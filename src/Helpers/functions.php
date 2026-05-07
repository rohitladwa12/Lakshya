<?php
/**
 * Helper Functions
 * Common utility functions used throughout the application
 */

/**
 * Redirect to a URL
 */
function redirect($url, $statusCode = 302) {
    if (!headers_sent()) {
        header("Location: " . $url, true, $statusCode);
        exit;
    } else {
        echo "<script>window.location.href='" . htmlspecialchars($url) . "';</script>";
        exit;
    }
}

/**
 * Sanitize input data
 */
function clean($data) {
    if (is_array($data)) {
        return array_map('clean', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 */
function isValidPassword($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return false;
    }
    
    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        return false;
    }
    
    if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }
    
    return true;
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

/**
 * Time ago format
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime, 'd M Y');
    }
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'INR') {
    if ($currency === 'INR') {
        return '₹' . number_format($amount, 0);
    }
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalFilename) {
    $extension = getFileExtension($originalFilename);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes, $maxSize) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size exceeds limit (' . formatFileSize($maxSize) . ')'];
    }
    
    $extension = getFileExtension($file['name']);
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
    }
    
    return ['success' => true];
}

/**
 * Upload file
 */
function uploadFile($file, $destination, $allowedTypes, $maxSize) {
    $validation = validateFileUpload($file, $allowedTypes, $maxSize);
    if (!$validation['success']) {
        return $validation;
    }
    
    $filename = generateUniqueFilename($file['name']);
    $filepath = $destination . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
    
    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

/**
 * Delete file
 */
function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Success JSON response
 */
function jsonSuccess($data = [], $message = 'Success') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Error JSON response
 */
function jsonError($message = 'Error', $statusCode = 400, $errors = []) {
    jsonResponse([
        'success' => false,
        'message' => $message,
        'errors' => $errors
    ], $statusCode);
}

/**
 * Paginate array
 */
function paginate($items, $page = 1, $perPage = ITEMS_PER_PAGE) {
    $total = count($items);
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    return [
        'items' => array_slice($items, $offset, $perPage),
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_items' => $total,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages
        ]
    ];
}

/**
 * Truncate text
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Slugify string
 */
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    return empty($text) ? 'n-a' : $text;
}

/**
 * Debug dump
 */
function dd(...$vars) {
    echo '<pre style="background: #1e1e1e; color: #dcdcdc; padding: 20px; border-radius: 5px; overflow: auto;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    die();
}

/**
 * Log message
 */
function logMessage($message, $level = 'INFO') {
    $logFile = LOGS_PATH . '/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Track user activity in the database
 * 
 * @param string $action The action performed (e.g., 'login', 'mock_ai_start')
 * @param string|null $description Human readable description
 * @param array|null $metaData Additional details in key-value pairs
 * @param string|null $entityType Type of entity involved
 * @param int|null $entityId ID of the entity involved
 */
function trackActivity($action, $description = null, $metaData = null, $entityType = null, $entityId = null) {
    static $logger = null;
    if ($logger === null) {
        $logger = new Logger();
    }
    return $logger->log($action, $description, $metaData, $entityType, $entityId);
}

/**
 * Get client IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Get user agent
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Check if request is AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Check if request is POST
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 */
function isGet() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get POST data
 */
function post($key, $default = null) {
    return $_POST[$key] ?? $default;
}

/**
 * Get GET data
 */
function get($key, $default = null) {
    return $_GET[$key] ?? $default;
}

/**
 * Get REQUEST data
 */
function request($key, $default = null) {
    return $_REQUEST[$key] ?? $default;
}
/**
 * Load .env file
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove quotes if present
        if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match("/^'(.*)'$/", $value, $matches)) {
            $value = $matches[1];
        }
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

/**
 * Check if a specific system feature is enabled
 * @param string $featureKey The key from system_settings table (e.g. 'feature_mock_ai')
 * @return bool
 */
function isFeatureEnabled($featureKey) {
    try {
        $db = getDB();
        if (!$db) return true;
        
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$featureKey]);
        $value = $stmt->fetchColumn();
        
        // If not found in DB, default to enabled
        if ($value === false) return true;
        
        return ($value === 'enabled');
    } catch (\Exception $e) {
        return true;
    }
}

/**
 * Require a feature to be enabled, otherwise show maintenance page
 * @param string $featureKey
 * @param string $featureName Human readable name for the error message
 */
function requireFeature($featureKey, $featureName = 'This feature') {
    if (!isFeatureEnabled($featureKey)) {
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo $featureName; ?> - Under Maintenance</title>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                :root { --primary: #800000; --text: #1e293b; --muted: #64748b; }
                body { font-family: 'Outfit', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; color: var(--text); }
                .container { background: white; padding: 3.5rem; border-radius: 32px; box-shadow: 0 20px 50px -12px rgba(0,0,0,0.1); text-align: center; max-width: 500px; border: 1px solid #e2e8f0; position: relative; overflow: hidden; }
                .container::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 6px; background: linear-gradient(90deg, var(--primary), #e9c66f); }
                .icon-stack { position: relative; width: 100px; height: 100px; margin: 0 auto 2rem; }
                .icon-bg { font-size: 5rem; color: #f1f5f9; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); }
                .icon-main { font-size: 3rem; color: var(--primary); position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1; }
                h1 { font-size: 2rem; font-weight: 800; margin-bottom: 1rem; color: #0f172a; letter-spacing: -0.025em; }
                p { line-height: 1.7; color: var(--muted); margin-bottom: 2.5rem; font-size: 1.1rem; }
                .status-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: #fff7ed; color: #9a3412; padding: 0.5rem 1rem; border-radius: 99px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1.5rem; border: 1px solid #ffedd5; }
                .btn { background: var(--primary); color: white; text-decoration: none; padding: 1rem 2.5rem; border-radius: 16px; font-weight: 700; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-block; box-shadow: 0 10px 15px -3px rgba(128, 0, 0, 0.3); }
                .btn:hover { background: #5b1f1f; transform: translateY(-2px); box-shadow: 0 20px 25px -5px rgba(128, 0, 0, 0.4); }
                .dots { display: flex; justify-content: center; gap: 8px; margin-top: 2rem; }
                .dot { width: 8px; height: 8px; background: #e2e8f0; border-radius: 50%; animation: pulse 1.5s infinite; }
                .dot:nth-child(2) { animation-delay: 0.2s; }
                .dot:nth-child(3) { animation-delay: 0.4s; }
                @keyframes pulse { 0%, 100% { opacity: 0.3; transform: scale(1); } 50% { opacity: 1; transform: scale(1.2); background: var(--primary); } }
            </style>
        </head>
    <body>
        <div class="container">
            <div class="status-badge"><i class="fas fa-wrench"></i> System Update</div>
            <div class="icon-stack">
                <i class="fas fa-microchip icon-bg"></i>
                <i class="fas fa-gears icon-main"></i>
            </div>
            <h1><?php echo htmlspecialchars($featureName); ?> is Updating</h1>
            <p>We're currently enhancing this feature to provide you with a better AI experience. Please check back in a short while.</p>
            <a href="javascript:history.back()" class="btn">Go Back</a>
            <div class="dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>
        </div>
    </body>
    </html>
    <?php
    exit;
    }
}
