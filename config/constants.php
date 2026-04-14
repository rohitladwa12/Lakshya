<?php
/**
 * Application Constants
 */

// Application Info
define('APP_NAME', 'GMU Placement Portal');
define('APP_VERSION', '2.0');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/Lakshya');

// Database Configuration
define('DB_MAIN_NAME', getenv('DB_NAME') ?: 'placement_portal_v2');
define('DB_GMU_NAME', getenv('DB_GMU_NAME') ?: 'gmu');
define('DB_GMIT_NAME', getenv('DB_GMIT_NAME') ?: 'gmit_new');

define('DB_GMU_PREFIX', (DB_GMU_NAME !== DB_MAIN_NAME) ? "`" . DB_GMU_NAME . "`." : "");
define('DB_GMIT_PREFIX', (DB_GMIT_NAME !== DB_MAIN_NAME) ? "`" . DB_GMIT_NAME . "`." : "");

// Institution Sources
define('INSTITUTION_GMU', 'GMU');
define('INSTITUTION_GMIT', 'GMIT');

// Paths
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
define('CONFIG_PATH', ROOT_PATH . '/config');
define('SRC_PATH', ROOT_PATH . '/src');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('REPORTS_UPLOAD_PATH', UPLOADS_PATH . '/reports');

// Upload Directories
define('RESUME_UPLOAD_PATH', UPLOADS_PATH . '/resumes');
define('PHOTO_UPLOAD_PATH', UPLOADS_PATH . '/photos');
define('DOCUMENT_UPLOAD_PATH', UPLOADS_PATH . '/documents');

// File Upload Limits
define('MAX_RESUME_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_PHOTO_SIZE', 2 * 1024 * 1024); // 2MB
define('MAX_DOCUMENT_SIZE', 10 * 1024 * 1024); // 10MB

// Allowed File Types
define('ALLOWED_RESUME_TYPES', ['pdf', 'doc', 'docx']);
define('ALLOWED_PHOTO_TYPES', ['jpg', 'jpeg', 'png']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx']);

// Session Settings
define('SESSION_LIFETIME', 3600 * 24); // 24 hours
define('SESSION_IDLE_TIMEOUT', 3600);   // 60 minutes (3600 seconds)
define('SESSION_NAME', 'PLACEMENT_PORTAL_SESSION');

// Pagination
define('ITEMS_PER_PAGE', 20);
define('JOBS_PER_PAGE', 15);
define('APPLICATIONS_PER_PAGE', 25);

// Email Configuration
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('FROM_EMAIL', getenv('FROM_EMAIL') ?: 'noreply@placement.com');
define('FROM_NAME', getenv('FROM_NAME') ?: 'GMU Placement Portal');

// AI Configuration
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent');

// Redis Configuration
define('REDIS_HOST', getenv('REDIS_HOST') ?: '127.0.0.1');
define('REDIS_PORT', getenv('REDIS_PORT') ?: 6379);
define('REDIS_PASSWORD', getenv('REDIS_PASSWORD') ?: null);

// Security
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_SPECIAL', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_UPPERCASE', true);

// Application Status Values
define('JOB_STATUS_DRAFT', 'Draft');
define('JOB_STATUS_ACTIVE', 'Active');
define('JOB_STATUS_CLOSED', 'Closed');
define('JOB_STATUS_CANCELLED', 'Cancelled');

define('APPLICATION_STATUS_APPLIED', 'Applied');
define('APPLICATION_STATUS_UNDER_REVIEW', 'Under Review');
define('APPLICATION_STATUS_SHORTLISTED', 'Shortlisted');
define('APPLICATION_STATUS_INTERVIEW_SCHEDULED', 'Interview Scheduled');
define('APPLICATION_STATUS_SELECTED', 'Selected');
define('APPLICATION_STATUS_REJECTED', 'Rejected');
define('APPLICATION_STATUS_WITHDRAWN', 'Withdrawn');

// User Roles
define('ROLE_STUDENT', 'student');
define('ROLE_ADMIN', 'admin');
define('ROLE_PLACEMENT_OFFICER', 'placement_officer');
define('ROLE_INTERNSHIP_OFFICER', 'internship_officer');
define('ROLE_DEPT_COORDINATOR', 'dept_coordinator');
define('ROLE_VC', 'vc');
define('ROLE_DEMO', 'demo');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (set to 0 in production)
if (getenv('APP_ENV') === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
}
else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Create upload directories if they don't exist
$uploadDirs = [
    UPLOADS_PATH,
    RESUME_UPLOAD_PATH,
    PHOTO_UPLOAD_PATH,
    DOCUMENT_UPLOAD_PATH,
    LOGS_PATH
];

foreach ($uploadDirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Coordinator discipline filter: GMU stores e.g. "CSE-AIML", GMIT stores "AIML".
 * Returns [GMU discipline, GMIT discipline] for filtering both institutions.
 * Add more mappings to DISCIPLINE_GMU_TO_GMIT if needed.
 */
if (!function_exists('getCoordinatorDisciplineFilters')) {
    function getCoordinatorDisciplineFilters($department)
    {
        static $map = null;
        if ($map === null) {
            $map = [
                'CSE-AIML' => 'AIML',
                'CSE-CSE' => 'CSE',
                'CSE-DS' => 'DS',
                'CSE-IOT' => 'IOT',
                'CSE-CS' => 'CS',
            ];
        }
        $dept = trim((string)$department);

        // Consolidate all MBA sub-branches under 'MBA' coordinator
        if ($dept === 'MBA') {
            return ['MBA', 'MBA-ADV', 'MBA-AM', 'MBA-IB', 'MBA-IE', 'MBA-INTNL', 'MBA-PF'];
        }

        // Consolidate all BCA sub-branches under 'BCA' coordinator
        if ($dept === 'BCA') {
            return ['BCA', 'BCA-AIDA', 'BCA-CS', 'BCA-CY', 'BCA-DS', 'BCA-GENERAL'];
        }

        // Consolidate all MCOM sub-branches under 'MCOM' coordinator
        if ($dept === 'MCOM') {
            return ['MCOM', 'MCOM-ATFA', 'MCom-AFDB', 'MCom-FAE'];
        }

        // Consolidate all MCA sub-branches under 'MCA' coordinator
        if ($dept === 'MCA') {
            return ['MCA', 'MCA-AIDA', 'MCA-CY', 'MCA-DS'];
        }

        // Consolidate all BCOM sub-branches under 'BCOM' coordinator
        if ($dept === 'BCOM') {
            return ['BCOM', 'BCOM-A&T', 'BCOM-AF', 'BCOM-AI', 'BCOM-AT', 'BCOM-DA&BI', 'BCOM-F&A', 'BCOM-G'];
        }

        // Engineering specific consolidations for GMIT and GMU variants based on dept_coordinators keys
        if ($dept === 'CE') {
            return ['CE', 'CIVIL', 'CV', 'DIP CIVIL'];
        }

        if ($dept === 'ME') {
            return ['ME', 'MECHANICAL', 'DIP MECH'];
        }

        if ($dept === 'ECE') {
            return ['ECE', 'EC', 'DIP EC'];
        }
        
        if ($dept === 'EEE') {
            return ['EEE', 'EE', 'DIP EEE'];
        }

        if ($dept === 'ISE') {
            return ['ISE', 'IS'];
        }

        if ($dept === 'CSE') {
            return ['CSE', 'CS', 'DIP CSE'];
        }

        if (isset($map[$dept])) {
            return [$dept, $map[$dept]];
        }
        // Fallback: GMIT uses part after "CSE-" if GMU uses "CSE-*"
        if (strpos($dept, 'CSE-') === 0) {
            return [$dept, substr($dept, 4)];
        }
        return [$dept, $dept];
    }

    function getCoordinatorSemesterFilters($department)
    {
        $dept = trim((string)$department);
        // PG Courses (2 years / 4 semesters)
        if (in_array($dept, ['MBA', 'MCA', 'MCOM'])) {
            return [1, 2, 3, 4];
        }
        // BCA/BCOM (3 years / 6 semesters)
        if (in_array($dept, ['BCA', 'BCOM'])) {
            return [1, 2, 3, 4, 5, 6];
        }
        // Engineering (4 years / 8 semesters) - show 3rd and 4th year
        return [5, 6, 7, 8];
    }
}
?>
