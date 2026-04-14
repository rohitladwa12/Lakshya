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
        // Initialize remote connection for GMU/GMIT tables
        try {
            $this->remoteDB = getDB('gmu');
        } catch (Exception $e) {
            error_log("Warning: Remote GMU/GMIT database not available: " . $e->getMessage());
            $this->remoteDB = null;
        }
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
            if (!$this->remoteDB) break;
            
            $table = $prefix . 'users';
            $sql = "SELECT * FROM {$table} WHERE USER_NAME = ? LIMIT 1";
            $stmt = $this->remoteDB->prepare($sql);
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

        // If institution is provided, we know where to look and what column to use
        if ($institution) {
            if (!$this->remoteDB) return null; // Remote DB required
            
            $prefix = ($institution === INSTITUTION_GMU) ? DB_GMU_PREFIX : DB_GMIT_PREFIX;
            $table = $prefix . 'users';
            
            $user = null;
            if ($institution === INSTITUTION_GMIT) {
                // For GMIT, search by both Enquiry No and Username, and JOIN with ad_student_details for name
                $sql = "SELECT u.*, d.name as student_name 
                        FROM {$table} u 
                        LEFT JOIN {$prefix}ad_student_details d ON u.ENQUIRY_NO = d.enquiry_no OR u.USER_NAME = d.student_id 
                        WHERE u.ENQUIRY_NO = ? OR u.USER_NAME = ? LIMIT 1";
                $stmt = $this->remoteDB->prepare($sql);
                $stmt->execute([$id, $id]);
                $user = $stmt->fetch();
            } else {
                // GMU: Try numerical SL_NO first, fall back to USER_NAME
                if (is_numeric($id)) {
                    $sql = "SELECT * FROM {$table} WHERE SL_NO = ? LIMIT 1";
                    $stmt = $this->remoteDB->prepare($sql);
                    $stmt->execute([$id]);
                    $user = $stmt->fetch();
                }
                
                if (!$user) {
                    $sql = "SELECT * FROM {$table} WHERE USER_NAME = ? LIMIT 1";
                    $stmt = $this->remoteDB->prepare($sql);
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
                $sql = "SELECT u.*, d.name as student_name 
                        FROM {$table} u 
                        LEFT JOIN {$prefix}ad_student_details d ON u.ENQUIRY_NO = d.enquiry_no OR u.USER_NAME = d.student_id 
                        WHERE u.ENQUIRY_NO = ? OR u.USER_NAME = ? LIMIT 1";
                $stmt = $this->remoteDB->prepare($sql);
                $stmt->execute([$id, $id]);
                $user = $stmt->fetch();
            } else {
                // GMU: Try numeric SL_NO first, fall back to USER_NAME
                if (is_numeric($id)) {
                    $sql = "SELECT * FROM {$table} WHERE {$idCol} = ? LIMIT 1";
                    $stmt = $this->remoteDB->prepare($sql);
                    $stmt->execute([$id]);
                    $user = $stmt->fetch();
                }

                if (!$user) {
                    $sql = "SELECT * FROM {$table} WHERE USER_NAME = ? LIMIT 1";
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
        $sql = "INSERT INTO {$DB_GMU_PREFIX}users (USER_NAME, PASSWORD, USER_GROUP, NAME, EMAIL, STATUS, LAST_UPDATED) 
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
            if (!$this->remoteDB) break;

            $table = $prefix . 'users';
            
            if ($inst === INSTITUTION_GMIT) {
                // GMIT: Join with ad_student_details using valid keys
                // Queries REMOTE DB
                $sql = "SELECT u.*, d.name as student_name 
                        FROM {$table} u 
                        LEFT JOIN {$prefix}ad_student_details d ON u.ENQUIRY_NO = d.enquiry_no OR u.USER_NAME = d.student_id 
                        WHERE (u.USER_NAME = ? OR u.AADHAR = ?) AND u.STATUS = 'ACTIVE' LIMIT 1";
            } else {
                // GMU: Join with ad_student_details to get authoritative USN and ad_student_approved for semester
                $sql = "SELECT u.*, u.NAME as student_name, d.usn as actual_usn, ad.sem 
                        FROM {$table} u 
                        LEFT JOIN {$prefix}ad_student_details d ON (u.USER_NAME = d.usn OR u.AADHAR = d.aadhar)
                        LEFT JOIN {$prefix}ad_student_approved ad ON d.usn = ad.usn
                        WHERE (u.USER_NAME = ? OR u.AADHAR = ?) AND u.STATUS = 'ACTIVE' 
                        ORDER BY ad.academic_year DESC, ad.sem DESC LIMIT 1";
            }

            $stmt = $this->remoteDB->prepare($sql);
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user) {
                $authenticated = false;
                if ($inst === INSTITUTION_GMIT) {
                    // GMIT uses bcrypt
                    $authenticated = password_verify($password, $user['PASSWORD']);
                } else {
                    // GMU - Migration Logic
                    // 1. Check if already hashed (valid bcrypt)
                    if (password_verify($password, $user['PASSWORD'])) {
                        $authenticated = true;
                    } 
                    // 2. Fallback to plain text (Legacy)
                    elseif ($password === $user['PASSWORD']) {
                        $authenticated = true;
                        // MIGRATION: Update to bcrypt immediately
                        $newHash = password_hash($password, PASSWORD_BCRYPT);
                        $upStmt = $this->remoteDB->prepare("UPDATE {$table} SET PASSWORD = ? WHERE SL_NO = ?");
                        $upStmt->execute([$newHash, $user['SL_NO']]);
                        logMessage("Migrated user {$user['USER_NAME']} (GMU) to Bcrypt", 'INFO');
                    } else {
                        $authenticated = false;
                    }
                }

                if ($authenticated) {
                    $appUser = $this->mapToAppUser($user, $inst);
                    
                    // GMU Semester Gating: Only allow Sem 5, 6, 7, 8
                    if ($inst === INSTITUTION_GMU && $appUser['role'] === 'student') {
                        $sem = (int)($user['sem'] ?? 0);
                        if ($sem < 5 || $sem > 8) {
                            logMessage("Login blocked for GMU student {$user['USER_NAME']} due to Semester constraint (Sem: $sem)", 'WARNING');
                            return ['success' => false, 'message' => 'Login is currently restricted to Semesters 5, 6, 7, and 8 for GMU students.'];
                        }
                    }

                    return [
                        'success' => true,
                        'user' => $appUser
                    ];
                } else {
                    return ['success' => false, 'message' => 'Invalid credentials'];
                }
            }
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
            'DISCIPLINE' => $row['DISCIPLINE'] ?? ($row['DEPT_ID'] ?? null),
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


