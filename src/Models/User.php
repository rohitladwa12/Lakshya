<?php
/**
 * User Model
 * Handles user authentication and management
 */

require_once __DIR__ . '/Model.php';

class User extends Model {
    protected $primaryKey = 'SL_NO'; 
    protected $remoteDB;
    
    public function __construct() {
        parent::__construct();
        // Remote DB is resolved lazily — see getRemoteDB()
        // Do NOT connect here: if GMU/GMIT is down it would add a 3s
        // timeout delay to every page that instantiates this model.
        $this->remoteDB = null;
    }

    /**
     * Lazy-load the remote GMU/GMIT connection.
     * Returns null (without crashing) if the server is unreachable.
     */
    private function getRemoteDB() {
        if ($this->remoteDB === null) {
            try {
                $this->remoteDB = getDB('gmu');
            } catch (Exception $e) {
                error_log('Remote DB unavailable: ' . $e->getMessage());
                $this->remoteDB = false; // false = already tried, skip retrying
            }
        }
        return $this->remoteDB ?: null;
    }
    
    // Map legacy columns to application attributes if needed, but for now we'll handle in methods
    
    /**
     * Find user by username
     */
    public function findByUsername($username) {
        $institutions = [
            INSTITUTION_GMU => DB_GMU_PREFIX,
            INSTITUTION_GMIT => DB_GMIT_PREFIX
        ];
        
        foreach ($institutions as $inst => $prefix) {
            $remoteDB = $this->getRemoteDB();
            if (!$remoteDB) break;

            $table = $prefix . 'users';
            $sql = "SELECT * FROM {$table} WHERE USER_NAME = ? LIMIT 1";
            $stmt = $remoteDB->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                return $this->mapToAppUser($user, $inst);
            }
        }
        
        return null;
    }
    
    /**
     * Find user by email (Legacy users might allow email as username)
     */
    public function findByEmail($email) {
        return $this->findByUsername($email);
    }
    
    /**
     * Find user by ID
     */
    public function find($id, $institution = null) {
        if ($id === null) return null;

        // Hardening: Handle array inputs gracefully to avoid "Array to string conversion" errors.
        // This can happen if session user data or a full row is accidentally passed as the ID.
        if (is_array($id)) {
            $extractedId = null;
            if (isset($id['id'])) $extractedId = $id['id'];
            elseif (isset($id['username'])) $extractedId = $id['username'];
            elseif (isset($id['SL_NO'])) $extractedId = $id['SL_NO'];
            elseif (isset($id['USER_NAME'])) $extractedId = $id['USER_NAME'];
            elseif (isset($id['ENQUIRY_NO'])) $extractedId = $id['ENQUIRY_NO'];
            
            if ($extractedId !== null && is_scalar($extractedId)) {
                $id = $extractedId;
            } else {
                // If we can't find a scalar ID, log it and return null to prevent a PDO crash
                error_log("Warning: User::find() called with an array that does not contain a recognizable scalar ID. Value: " . print_r($id, true));
                return null;
            }
        }

        // If institution is provided, we know where to look and what column to use
        if ($institution) {
            $db = $this->getRemoteDB();
            if (!$db) return null; // Remote DB required
            
            $prefix = ($institution === INSTITUTION_GMU) ? DB_GMU_PREFIX : DB_GMIT_PREFIX;
            $table = $prefix . 'users';
            
            $user = null;
            if ($institution === INSTITUTION_GMIT) {
                // For GMIT, search by both Enquiry No and Username, and JOIN with ad_student_details for name
                $sql = "SELECT u.*, d.name as student_name 
                        FROM {$table} u 
                        LEFT JOIN {$prefix}ad_student_details d ON u.ENQUIRY_NO = d.enquiry_no OR u.USER_NAME = d.student_id 
                        WHERE u.ENQUIRY_NO = ? OR u.USER_NAME = ? LIMIT 1";
                $stmt = $db->prepare($sql);
                $stmt->execute([$id, $id]);
                $user = $stmt->fetch();
            } else {
                // GMU: Try numerical SL_NO first, fall back to USER_NAME
                if (is_numeric($id)) {
                    $sql = "SELECT * FROM {$table} WHERE SL_NO = ? LIMIT 1";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$id]);
                    $user = $stmt->fetch();
                }
                
                if (!$user) {
                    $sql = "SELECT * FROM {$table} WHERE USER_NAME = ? LIMIT 1";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$id]);
                    $user = $stmt->fetch();
                }
            }
            return $this->mapToAppUser($user, $institution);
        }
        
        // Otherwise search both (Slower)
        $institutions = [
            INSTITUTION_GMU => ['prefix' => DB_GMU_PREFIX, 'col' => 'SL_NO'],
            INSTITUTION_GMIT => ['prefix' => DB_GMIT_PREFIX, 'col' => 'ENQUIRY_NO']
        ];
        
        // Priority Routing for Lookups
        if (is_string($id)) {
            $upperId = strtoupper($id);
            if (strpos($upperId, '4GM') === 0 || strpos($upperId, 'GMIT') === 0) {
                // Reorder to check GMIT first
                $institutions = [
                    INSTITUTION_GMIT => ['prefix' => DB_GMIT_PREFIX, 'col' => 'ENQUIRY_NO'],
                    INSTITUTION_GMU => ['prefix' => DB_GMU_PREFIX, 'col' => 'SL_NO']
                ];
            }
        }

        foreach ($institutions as $inst => $config) {
            if (!$this->remoteDB) break;

            $table = $config['prefix'] . 'users';
            $idCol = $config['col'];
            $prefix = $config['prefix'];
            
            $user = null;
            if ($inst === INSTITUTION_GMIT) {
                try {
                    // Optimized: Fetch user first to avoid complex OR join on temporary tables
                    $sql = "SELECT u.* FROM {$table} u WHERE u.ENQUIRY_NO = ? OR u.USER_NAME = ? LIMIT 1";
                    $stmt = $this->remoteDB->prepare($sql);
                    $stmt->execute([$id, $id]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        $enq = $user['ENQUIRY_NO'] ?? '';
                        $sid = $user['USER_NAME'] ?? '';
                        $sqlDet = "SELECT name as student_name, discipline FROM {$prefix}ad_student_details WHERE enquiry_no = ? OR student_id = ? LIMIT 1";
                        $stmtDet = $this->remoteDB->prepare($sqlDet);
                        $stmtDet->execute([$enq, $sid]);
                        $details = $stmtDet->fetch();
                        if ($details) {
                            $user = array_merge($user, $details);
                        }
                    }
                } catch (Exception $e) {
                    // Fallback to minimal query if columns are missing
                    $sql = "SELECT u.* FROM {$table} u WHERE u.ENQUIRY_NO = ? OR u.USER_NAME = ? LIMIT 1";
                    $stmt = $this->remoteDB->prepare($sql);
                    $stmt->execute([$id, $id]);
                    $user = $stmt->fetch();
                }
            } else {
                // GMU: Try joining with ad_student_approved for name/discipline
                try {
                    $queryId = is_numeric($id) ? "u.{$idCol}" : "u.USER_NAME";
                    $sql = "SELECT u.*, ad.name as student_name, ad.discipline
                            FROM {$table} u
                            LEFT JOIN {$prefix}ad_student_approved ad ON u.USER_NAME = ad.usn
                            WHERE {$queryId} = ? LIMIT 1";
                    $stmt = $this->remoteDB->prepare($sql);
                    $stmt->execute([$id]);
                    $user = $stmt->fetch();
                } catch (Exception $e) {
                    // Fallback to minimal query
                    $queryId = is_numeric($id) ? "{$idCol}" : "USER_NAME";
                    $sql = "SELECT * FROM {$table} WHERE {$queryId} = ? LIMIT 1";
                    $stmt = $this->remoteDB->prepare($sql);
                    $stmt->execute([$id]);
                    $user = $stmt->fetch();
                }
            }
            if ($user) return $this->mapToAppUser($user, $inst);
        }
        
        return null;
    }

    /**
     * Create new user (Updated for legacy schema)
     */
    public function createUser($data) {
        if (!$this->remoteDB) return false;

        // This functionality writes to REMOTE DB
        $sql = "INSERT INTO " . DB_GMU_PREFIX . "users (USER_NAME, PASSWORD, USER_GROUP, NAME, EMAIL, STATUS, LAST_UPDATED) 
                VALUES (?, ?, ?, ?, ?, 'ACTIVE', NOW())";
        
        // Caution: This assumes writing to GMU users table by default?
        // Or should we support creating in GMIT? 
        // For now, defaulting to GMU connection logic, IF creating users via this portal is supported.
        
        $roleMap = [
            'student' => 'STUDENT',
            'placement_officer' => 'ADMIN',
            'admin' => 'ADMIN'
        ];
        
        $legacyRole = $roleMap[$data['role']] ?? 'STUDENT';
        
        $stmt = $this->remoteDB->prepare($sql);
        $res = $stmt->execute([
            $data['username'],
            password_hash($data['password'], PASSWORD_BCRYPT), 
            $legacyRole,
            $data['full_name'],
            $data['email'] ?? null 
        ]);
        
        if ($res) {
            return $this->remoteDB->lastInsertId();
        }
        return false;
    }
    
    /**
     * Authenticate user
     */
    public function authenticate($username, $password) {
        // 1. Check App Users Table (LOCAL DB) - as requested
        $stmt = $this->db->prepare("SELECT * FROM app_officers WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $appUser = $stmt->fetch();
        
        if ($appUser) {
             // Support both plain text (legacy) and bcrypt
             $passMatch = false;
             if ($appUser['password'] === $password) $passMatch = true;
             elseif (password_verify($password, $appUser['password'])) $passMatch = true;
             
             if ($passMatch) {
                // Normalize role to avoid redirect/permission mismatches (e.g. 'VC', ' vc ')
                $normRole = strtolower(trim((string)($appUser['role'] ?? '')));
                if (in_array($normRole, ['vice_chancellor', 'vice-chancellor', 'vice chancellor', 'vc'], true)) {
                    $normRole = 'vc';
                }
                return [
                    'success' => true,
                    'user' => [
                        'id' => $appUser['id'],
                        'username' => $appUser['username'],
                        'email' => $appUser['email'] ?? null,
                        'full_name' => $appUser['full_name'] ?? $appUser['username'],
                        'role' => $normRole ?: ($appUser['role'] ?? ''),
                        'is_active' => true,
                        'original_role' => strtoupper($appUser['role'] ?? ''),
                        'institution' => $appUser['institution'] ?? 'GMU'
                    ]
                ];
             }
        }

        // 1b. Check App Officers Table (Legacy / Fallback)
        $stmt = $this->db->prepare("SELECT * FROM app_officers WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $officer = $stmt->fetch();
        
        if ($officer && $password === $officer['password']) {
            return [
                'success' => true,
                'user' => [
                    'id' => $officer['id'],
                    'username' => $officer['username'],
                    'email' => $officer['email'],
                    'full_name' => $officer['full_name'],
                    'role' => $officer['role'],
                    'is_active' => true,
                    'original_role' => 'APP_OFFICER',
                    'institution' => $officer['institution']
                ]
            ];
        }

        // 1c. Check Department Coordinators (LOCAL DB) - login by email + bcrypt
        $stmt = $this->db->prepare("SELECT * FROM dept_coordinators WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $coord = $stmt->fetch();
        if ($coord && password_verify($password, $coord['password'])) {
            return [
                'success' => true,
                'user' => [
                    'id' => $coord['id'],
                    'username' => $coord['email'],
                    'email' => $coord['email'],
                    'full_name' => $coord['full_name'],
                    'role' => 'dept_coordinator',
                    'is_active' => true,
                    'original_role' => 'DEPT_COORDINATOR',
                    'institution' => $coord['institution'] ?? 'GMU',
                    'department' => $coord['department']
                ]
            ];
        }

        // 2. Check Legacy Institutions (REMOTE DB)
        $institutions = [
            INSTITUTION_GMU => DB_GMU_PREFIX,
            INSTITUTION_GMIT => DB_GMIT_PREFIX
        ];
        
        // Priority Routing: 4GM and GMIT prefixes belong to GMIT
        $upperUsername = strtoupper($username);
        if (strpos($upperUsername, '4GM') === 0 || strpos($upperUsername, 'GMIT') === 0) {
            // Reorder institutions to prioritize GMIT
            $institutions = [
                INSTITUTION_GMIT => DB_GMIT_PREFIX,
                INSTITUTION_GMU => DB_GMU_PREFIX
            ];
        }
        
        foreach ($institutions as $inst => $prefix) {
            $remoteDB = $this->getRemoteDB(); // lazy — only connects here
            if (!$remoteDB) {
                // $this->remoteDB === false means connection was ATTEMPTED but FAILED
                if ($this->remoteDB === false) {
                    return [
                        'success' => false,
                        'server_down' => true,
                        'message' => 'The student portal server is currently unavailable. Please try again in a few minutes or contact your administrator.'
                    ];
                }
                break;
            }
            $this->remoteDB = $remoteDB; // cache for subsequent loop iterations

            $table = $prefix . 'users';
            
            // Optimized: Fetch user first to avoid complex joins triggering "Disk Full" errors on temp files
            $sqlUser = "SELECT * FROM {$table} WHERE (USER_NAME = ? OR AADHAR = ?) AND STATUS = 'ACTIVE' LIMIT 1";
            $stmt = $this->remoteDB->prepare($sqlUser);
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // VERIFY PASSWORD FIRST (Fastest)
                $authenticated = false;
                if ($inst === INSTITUTION_GMIT) {
                    $authenticated = password_verify($password, $user['PASSWORD']);
                } else {
                    if (password_verify($password, $user['PASSWORD'])) {
                        $authenticated = true;
                    } elseif ($password === $user['PASSWORD']) {
                        $authenticated = true;
                        // MIGRATION: Update to bcrypt
                        $newHash = password_hash($password, PASSWORD_BCRYPT);
                        $this->remoteDB->prepare("UPDATE {$table} SET PASSWORD = ? WHERE SL_NO = ?")
                                       ->execute([$newHash, $user['SL_NO']]);
                    }
                }

                if (!$authenticated) {
                    return ['success' => false, 'message' => 'Invalid credentials'];
                }

                // If authenticated, NOW fetch extra details (Enrichment)
                if ($inst === INSTITUTION_GMIT) {
                    $enquiry = $user['ENQUIRY_NO'] ?? '';
                    $uid = $user['USER_NAME'] ?? '';
                    $sqlDet = "SELECT name as student_name FROM {$prefix}ad_student_details WHERE enquiry_no = ? OR student_id = ? LIMIT 1";
                    $stmtDet = $this->remoteDB->prepare($sqlDet);
                    $stmtDet->execute([$enquiry, $uid]);
                    $details = $stmtDet->fetch();
                    if ($details) $user['student_name'] = $details['student_name'];
                } else {
                    $usn = $user['USER_NAME'] ?? '';
                    $aadhar = $user['AADHAR'] ?? '';
                    $sqlDet = "SELECT d.usn as actual_usn, ad.sem 
                               FROM {$prefix}ad_student_details d
                               LEFT JOIN {$prefix}ad_student_approved ad ON d.usn = ad.usn
                               WHERE d.usn = ? OR d.aadhar = ?
                               ORDER BY ad.academic_year DESC, ad.sem DESC LIMIT 1";
                    $stmtDet = $this->remoteDB->prepare($sqlDet);
                    $stmtDet->execute([$usn, $aadhar]);
                    $details = $stmtDet->fetch();
                    if ($details) {
                        $user['actual_usn'] = $details['actual_usn'];
                        $user['sem'] = $details['sem'];
                    }
                    $user['student_name'] = $user['NAME'] ?? '';
                }

                $appUser = $this->mapToAppUser($user, $inst);
                
                // GMU Semester Gating
                if ($inst === INSTITUTION_GMU && $appUser['role'] === 'student') {
                    $sem = (int)($user['sem'] ?? 0);
                    if ($sem < 5 || $sem > 8) {
                        return ['success' => false, 'message' => 'Login restricted to Semesters 5-8 for GMU students.'];
                    }
                }

                return ['success' => true, 'user' => $appUser];
            }
        }
        // If remote connection was attempted but failed, tell the student clearly
        if ($this->remoteDB === false) {
            return [
                'success'     => false,
                'server_down' => true,
                'message'     => 'The student portal server is currently unavailable. Please try again in a few minutes or contact your administrator.'
            ];
        }

        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    private function mapToAppUser($row, $institution = null) {
        if (!$row) return null;
        
        // Role Mapping
        $role = 'student'; // Default
        $group = strtoupper($row['USER_GROUP'] ?? '');
        
        if (in_array($group, ['VC', 'VICE_CHANCELLOR', 'VICE-CHANCELLOR', 'VICE CHANCELLOR'])) {
            $role = 'vc';
        } elseif (in_array($group, ['ADMIN', 'PRINCIPAL', 'HOD', 'FACULTY', 'TEACHING', 'PLACEMENT'])) {
            $role = 'placement_officer';
        } elseif ($group === 'INTERNSHIP_OFFICER' || ($row['role'] ?? '') === 'internship_officer') {
             // Check if it comes from app_officers or remote DB
             $role = 'internship_officer';
        } elseif ($group === 'STUDENT') {
            $role = 'student';
        }
        
        // Handle app_officers role overrides
        if (isset($row['role']) && $row['role'] === 'internship_officer') {
            $role = 'internship_officer';
            $group = 'INTERNSHIP_OFFICER';
        }

        if (isset($row['role']) && $row['role'] === 'vc') {
            $role = 'vc';
            $group = 'VC';
        }
        
        if ($role === 'student') {
            // For GMU, prioritize the authoritative USN fetched from ad_student_details
            if ($institution === INSTITUTION_GMU && !empty($row['actual_usn'])) {
                $id = $row['actual_usn'];
            } else {
                $id = $row['USER_NAME'] ?? ($row['user_name'] ?? null);
            }
        } else {
            if ($institution === INSTITUTION_GMIT) {
                $id = (!empty($row['ENQUIRY_NO'])) ? $row['ENQUIRY_NO'] : ($row['USER_NAME'] ?? null);
            } else {
                $id = $row['SL_NO'] ?? ($row['id'] ?? null);
            }
        }
        
        return [
            'id' => $id,
            'username' => ($institution === INSTITUTION_GMU && $role === 'student' && !empty($row['actual_usn'])) ? $row['actual_usn'] : ($row['USER_NAME'] ?? ($row['user_name'] ?? null)),
            'email' => $row['USER_NAME'] ?? ($row['user_name'] ?? null), 
            'full_name' => $row['NAME'] ?? ($row['student_name'] ?? ($row['name'] ?? ($row['full_name'] ?? null))),
            'role' => $role,
            'is_active' => ($row['STATUS'] === 'ACTIVE' || $row['STATUS'] === 'Active' || ($row['STATUS'] ?? '') == 1),
            'original_role' => $group,
            'aadhar' => $row['AADHAR'] ?? null,
            'student_id_str' => $row['ID'] ?? ($row['EMP_ID'] ?? null),
            'institution' => $institution,
            'COURSE' => $row['COURSE'] ?? null,
            'DISCIPLINE' => $row['discipline'] ?? ($row['DISCIPLINE'] ?? ($row['branch'] ?? ($row['DEPT_ID'] ?? null))),
            'department' => $row['discipline'] ?? ($row['DISCIPLINE'] ?? ($row['branch'] ?? ($row['DEPT_ID'] ?? null))),
            'photo' => (function($p) {
                if (empty($p)) return null;
                
                // Handle JSON-encoded photo data (common in some CMS/ERP systems)
                if (strpos($p, '[{') === 0 || strpos($p, '{') === 0) {
                    $decoded = json_decode($p, true);
                    if (is_array($decoded)) {
                        // Priority: thumbnail -> url -> first array element
                        if (isset($decoded[0]['thumbnail'])) $p = $decoded[0]['thumbnail'];
                        elseif (isset($decoded[0]['url'])) $p = $decoded[0]['url'];
                        elseif (isset($decoded['thumbnail'])) $p = $decoded['thumbnail'];
                        elseif (isset($decoded['url'])) $p = $decoded['url'];
                    }
                }

                if (empty($p) || preg_match('/^https?:\/\//', $p)) return $p;
                
                // Prepend base URL for relative paths
                if (strpos($p, 'attachments/') !== false) {
                    return "https://erp.gmit.info/" . ltrim($p, '/');
                }
                return "https://erp.gmit.info/attachments/gmu/profile/" . ltrim($p, '/');
            })($row['PHOTO'] ?? null),
            'raw' => $row // Keep raw data for deep fallback in profile mapping
        ];
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $institution, $currentPassword, $newPassword) {
        $prefix = ($institution === INSTITUTION_GMU) ? DB_GMU_PREFIX : DB_GMIT_PREFIX;
        $table = $prefix . 'users';
        
        $sql = "SELECT PASSWORD FROM {$table} WHERE SL_NO = ?";
        if (!$this->remoteDB) return ['success' => false, 'message' => 'Remote database unavailable'];

        $stmt = $this->remoteDB->prepare($sql); // REMOTE
        $stmt->execute([$userId]);
        $stored = $stmt->fetchColumn();
        
        if ($institution === INSTITUTION_GMIT) {
             if (!password_verify($currentPassword, $stored)) {
                 return ['success' => false, 'message' => 'Current password is incorrect'];
             }
             $newPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        } else {
             // GMU - Check hash first, then plain text (handling migration if they change pw)
             $verified = false;
             if (password_verify($currentPassword, $stored)) {
                 $verified = true;
             } elseif ($currentPassword === $stored) {
                 $verified = true;
             }
             
             if (!$verified) {
                  return ['success' => false, 'message' => 'Current password is incorrect'];
             }
             
             // Always hash the new password
             $newPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        }
        
        $updateSql = "UPDATE {$table} SET PASSWORD = ? WHERE SL_NO = ?";
        $stmt = $this->remoteDB->prepare($updateSql); // REMOTE
        $stmt->execute([$newPassword, $userId]);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
    }
    
     /**
     * Change password for department coordinators (Local DB)
     */
    public function changeCoordinatorPassword($userId, $currentPassword, $newPassword) {
        $stmt = $this->db->prepare("SELECT password FROM dept_coordinators WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $stored = $stmt->fetchColumn();
        
        if (!$stored || !password_verify($currentPassword, $stored)) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $this->db->prepare("UPDATE dept_coordinators SET password = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$newHash, $userId]);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
    }

    /**
     * Get all students
     */
    public function getStudents() {
        $gmuTable = DB_GMU_PREFIX . 'users';
        $gmitTable = DB_GMIT_PREFIX . 'users';
        
        $sql = "SELECT *, '" . INSTITUTION_GMU . "' as institution FROM {$gmuTable} WHERE USER_GROUP = 'STUDENT'
                UNION ALL
                SELECT *, '" . INSTITUTION_GMIT . "' as institution FROM {$gmitTable} WHERE USER_GROUP = 'STUDENT'
                ORDER BY NAME ASC";
        
        if (!$this->remoteDB) return [];

        $stmt = $this->remoteDB->query($sql); // REMOTE
        $users = $stmt->fetchAll();
        return array_map(function($user) {
            return $this->mapToAppUser($user, $user['institution']);
        }, $users);
    }
    
    /**
     * Get active users
     */
    public function getActiveUsers() {
        // This method is generic but likely intended for REMOTE users list? 
        // If Model class assumes $this->table, User doesn't set it. 
        // We will assume this iterates both active lists.
        return $this->getStudents();
    }

}


