<?php
/**
 * StudentProfile Model
 */

require_once __DIR__ . '/Model.php';

class StudentProfile extends Model {
    protected $table;
    protected $primaryKey = 'SL_NO';
    private $remoteDBConnection = null;
    
    public function __construct() {
        parent::__construct();
    }

    public function __get($name) {
        if ($name === 'remoteDB') {
            if ($this->remoteDBConnection === null) {
                $this->remoteDBConnection = getDB('gmu');
            }
            return $this->remoteDBConnection;
        }
        return null;
    }
    
    // Legacy table doesn't have these, but we map standard accessors to them
    protected $fillable = [
        // fields that might be updateable if we allowed writing back to legacy
    ];
    
    /**
     * Get profile by user ID (SL_NO from users table)
     * Links via USN or AADHAR
     */
    public function getByUserId($userId, $institution = null) {
        $userModel = new User();
        // Use findByUsername so string USNs like 'GMIT23AI80' are looked up correctly.
        // find($id) does a numeric/PK lookup that can match the wrong student via loose MySQL comparison.
        $user = $userModel->findByUsername($userId) ?: $userModel->find($userId, $institution);
        
        if (!$user) return null;
        
        $inst = $user['institution'];
        $prefix = ($inst === INSTITUTION_GMU) ? DB_GMU_PREFIX : DB_GMIT_PREFIX;
        $username = $user['username'];
        $aadhar = $user['aadhar'];
        
        if ($inst === INSTITUTION_GMIT) {
            // GMIT: Map app USER_NAME to student_id or use ENQUIRY_NO
            $enquiryNo = $user['id'];

            $details = [];
            if ($this->remoteDB) {
                // Query by USN/aadhar first (most reliable), fall back to enquiry_no
                $sqlDetails = "SELECT * FROM {$prefix}ad_student_details
                               WHERE usn = ? OR student_id = ? LIMIT 1";
                $stmt = $this->remoteDB->prepare($sqlDetails);
                $stmt->execute([$username, $username]);
                $row = $stmt->fetch();

                // Only use this row if it actually belongs to this student
                if ($row && (
                    strtoupper($row['usn'] ?? '') === strtoupper($username) ||
                    strtoupper($row['student_id'] ?? '') === strtoupper($username)
                )) {
                    $details = $row;
                }
            }

            // Fetch Current Semester & SGPA from student_sem_sgpa for GMIT (LOCAL)
            $sqlCurrent = "SELECT semester, sgpa FROM student_sem_sgpa
                           WHERE student_id = ? AND institution = ? AND is_current = 1
                           LIMIT 1";
            $stmtC = $this->db->prepare($sqlCurrent); // LOCAL
            $stmtC->execute([$username, INSTITUTION_GMIT]);
            $currentData = $stmtC->fetch();

            $mapped = $this->mapToAppProfile([], $user, $details ?: [], $inst);

            // Always trust the ERP user record for course & department — it's the authoritative source
            $mapped['course']     = $user['COURSE']     ?? ($user['raw']['COURSE']     ?? $mapped['course']);
            $mapped['department'] = $user['DISCIPLINE'] ?? ($user['raw']['DISCIPLINE'] ?? $mapped['department']);

            if ($currentData) {
                $mapped['semester'] = $currentData['semester'];
                $mapped['sgpa']     = $currentData['sgpa'];
                $mapped['cgpa']     = $currentData['sgpa'];
                if (empty($mapped['year_of_study']) && !empty($mapped['semester'])) {
                    $mapped['year_of_study'] = ceil((int)$mapped['semester'] / 2);
                }
            }
            return $mapped;
        } else {
            // GMU fetching (REMOTE)
            $sqlApproved = "SELECT * FROM {$prefix}ad_student_approved 
                            WHERE usn = ? OR (aadhar IS NOT NULL AND aadhar = ?) 
                            ORDER BY academic_year DESC, sem DESC LIMIT 1";
            if (!$this->remoteDB) return $this->mapToAppProfile([], $user, [], $inst);
            $stmtA = $this->remoteDB->prepare($sqlApproved); // REMOTE
            $stmtA->execute([$username, $aadhar]);
            $approved = $stmtA->fetch();

            $sqlDetails = "SELECT * FROM {$prefix}ad_student_details 
                           WHERE usn = ? OR (aadhar IS NOT NULL AND aadhar = ?) 
                           LIMIT 1";
            if ($this->remoteDB) {
                $stmtD = $this->remoteDB->prepare($sqlDetails); // REMOTE
                $stmtD->execute([$username, $aadhar]);
                $details = $stmtD->fetch();
            }
            
            return $this->mapToAppProfile($approved ?: [], $user, $details ?: [], $inst);
        }
    }
    
    /**
     * Alias for getByUserId - used by Career Advisor
     */
    public function getProfile($userId) {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['student_profile_' . $userId])) {
            return $_SESSION['student_profile_' . $userId];
        }
        $profile = $this->getByUserId($userId);
        if ($profile && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['student_profile_' . $userId] = $profile;
        }
        return $profile;
    }

    public function getAcademicHistory($userId, $institution = null) {
        if (!$userId) return [];
        
        $userModel = new User();
        $user = $userModel->findByUsername($userId) ?: $userModel->find($userId, $institution);
        
        // CRITICAL FIX: If User::find() fails or returns demo user, use session data directly
        if (!$user || $user['username'] === 'demo' || $user['id'] === 0) {
            // Fallback to session data
            $user = [
                'id' => $userId,
                'username' => $userId,
                'institution' => $institution ?? INSTITUTION_GMIT,
                'aadhar' => null
            ];
        }

        $inst = $user['institution'];
        $prefix = ($inst === INSTITUTION_GMU) ? DB_GMU_PREFIX : DB_GMIT_PREFIX;
        $username = $user['username'];
        $aadhar = $user['aadhar'] ?? null;

        if ($inst === INSTITUTION_GMIT) {
            // GMIT: Map app USER_NAME to student_id or use ENQUIRY_NO
            $enquiryNo = $user['id'];
            
            $sqlDetails = "SELECT * FROM {$prefix}ad_student_details 
                           WHERE enquiry_no = ? OR student_id = ? OR usn = ? OR (aadhar IS NOT NULL AND aadhar = ?) 
                           LIMIT 1";
            $stmt = $this->remoteDB->prepare($sqlDetails); // REMOTE
            $stmt->execute([$enquiryNo, $username, $username, $aadhar]);
            $details = $stmt->fetch();
            
            // GMIT: SGPA and semester from student_sem_sgpa (LOCAL)
            $studentIdForSgpa = $username; // USN stored in student_sem_sgpa.student_id for GMIT

            $sqlSgpa = "SELECT semester, sgpa, academic_year FROM student_sem_sgpa 
                        WHERE student_id = ? AND institution = ? 
                        ORDER BY semester DESC";
            $stmtSgpa = $this->db->prepare($sqlSgpa); // LOCAL
            $stmtSgpa->execute([$studentIdForSgpa, INSTITUTION_GMIT]);
            $sgpaRecords = $stmtSgpa->fetchAll();

            if (empty($sgpaRecords)) {
                return [$this->mapToAppProfile([], $user, $details ?: [], $inst)];
            }

            $history = [];
            foreach ($sgpaRecords as $rec) {
                // For expanded details, we still map from $details
                $profile = $this->mapToAppProfile([], $user, $details ?: [], $inst);
                $profile['semester'] = $rec['semester'];
                $profile['sgpa'] = $rec['sgpa'];
                $profile['cgpa'] = $rec['sgpa']; // Individual sem record
                $profile['academic_year'] = $rec['academic_year'];
                
                // Derive year if missing
                if (empty($profile['year_of_study']) && !empty($profile['semester'])) {
                    $profile['year_of_study'] = ceil((int)$profile['semester'] / 2);
                }
                
                $history[] = $profile;
            }
            return $history;
        } else {
            // GMU (REMOTE)
            $sql = "SELECT * FROM {$prefix}ad_student_approved 
                    WHERE usn = ? OR (aadhar IS NOT NULL AND aadhar = ?) 
                    ORDER BY academic_year DESC, sem DESC";
            $stmt = $this->remoteDB->prepare($sql); // REMOTE
            $stmt->execute([$username, $aadhar]);
            $records = $stmt->fetchAll();
            
            return array_map(function($row) use ($user, $inst) {
                return $this->mapToAppProfile($row, $user, [], $inst);
            }, $records);
        }
    }
    
    protected function mapToAppProfile($row, $userRow = null, $detailsRow = [], $institution = null) {
        if (!$row && !$detailsRow && !$userRow) return null;
        
        $isGMIT = ($institution === INSTITUTION_GMIT);
        
        // Normalize keys to lowercase for robust mapping (GMU uses UPPERCASE columns)
        if (is_array($row)) $row = array_change_key_case($row, CASE_LOWER);
        if (is_array($detailsRow)) $detailsRow = array_change_key_case($detailsRow, CASE_LOWER);
        if (is_array($userRow)) $userRow = array_change_key_case($userRow, CASE_LOWER);

        $data = $row ?: $detailsRow;
        
        // Extract photo from User row if available
        $photo = null;
        if ($userRow) {
            $p = $userRow['photo'] ?? ($userRow['PHOTO'] ?? null);
            if (!empty($p)) {
                // Handle JSON-encoded photo data
                if (strpos($p, '[{') === 0 || strpos($p, '{') === 0) {
                    $decoded = json_decode($p, true);
                    if (is_array($decoded)) {
                        if (isset($decoded[0]['thumbnail'])) $p = $decoded[0]['thumbnail'];
                        elseif (isset($decoded[0]['url'])) $p = $decoded[0]['url'];
                        elseif (isset($decoded['thumbnail'])) $p = $decoded['thumbnail'];
                        elseif (isset($decoded['url'])) $p = $decoded['url'];
                    }
                }
                $photo = $p;
            }
        }

        // Final URL normalization
        if ($photo && !preg_match('/^https?:\/\//', $photo)) {
            if (strpos($photo, 'attachments/') !== false) {
                $photo = "https://erp.gmit.info/" . ltrim($photo, '/');
            } else {
                $photo = "https://erp.gmit.info/attachments/gmu/profile/" . ltrim($photo, '/');
            }
        }

        return [
            'id' => (!empty($row['SL_NO']) ? $row['SL_NO'] : (!empty($detailsRow['enquiry_no']) ? $detailsRow['enquiry_no'] : null)),
            'user_id' => $userRow['id'] ?? ($userRow['SL_NO'] ?? null),
            'usn' => (!empty($row['usn']) ? $row['usn'] : (!empty($detailsRow['usn']) ? $detailsRow['usn'] : ($userRow['username'] ?? null))),
            'enrollment_number' => (!empty($row['usn']) ? $row['usn'] : (!empty($detailsRow['usn']) ? $detailsRow['usn'] : ($userRow['username'] ?? ($userRow['USER_NAME'] ?? ($userRow['EMP_ID'] ?? ($userRow['raw']['EMP_ID'] ?? null)))))),
            'student_id' => (!empty($detailsRow['student_id']) ? $detailsRow['student_id'] : (!empty($row['student_id']) ? $row['student_id'] : ($userRow['username'] ?? ($userRow['USER_NAME'] ?? ($userRow['EMP_ID'] ?? ($userRow['raw']['EMP_ID'] ?? null)))))),
            'course' => (!empty($row['course']) ? $row['course'] : (!empty($detailsRow['course']) ? $detailsRow['course'] : ($userRow['COURSE'] ?? ($userRow['raw']['COURSE'] ?? null)))),
            'department' => (!empty($row['discipline']) ? $row['discipline'] : (!empty($row['branch']) ? $row['branch'] : (!empty($detailsRow['discipline']) ? $detailsRow['discipline'] : (!empty($detailsRow['branch']) ? $detailsRow['branch'] : ($userRow['DISCIPLINE'] ?? ($userRow['raw']['DISCIPLINE'] ?? ($userRow['raw']['branch'] ?? ($userRow['raw']['DEPT_ID'] ?? null)))))))),
            'year_of_study' => $row['year'] ?? (!empty($row['sem']) ? ceil((int)$row['sem'] / 2) : (!empty($row['semester']) ? ceil((int)$row['semester'] / 2) : null)),
            'semester' => $row['sem'] ?? ($row['semester'] ?? null),
            'cgpa' => $row['sgpa'] ?? 0.0,
            'sgpa' => $row['sgpa'] ?? 0.0,
            'name' => (!empty($row['name']) ? $row['name'] : (!empty($detailsRow['name']) ? $detailsRow['name'] : ($userRow['full_name'] ?? ($userRow['NAME'] ?? null)))),
            'faculty' => $row['faculty'] ?? ($detailsRow['college'] ?? null),
            'school' => $row['school'] ?? ($detailsRow['college'] ?? null),
            'programme' => $row['programme'] ?? ($detailsRow['programme'] ?? null),
            'academic_year' => $row['academic_year'] ?? ($detailsRow['academic_year'] ?? null),
            'institution' => $institution,
            
            // Expanded Personal & Demographic Fields
            'father_name' => $detailsRow['father_name'] ?? null,
            'mother_name' => $detailsRow['mother_name'] ?? null,
            'parent_mobile' => $detailsRow['parent_mobile'] ?? null,
            'student_mobile' => $detailsRow['student_mobile'] ?? ($userRow['phone'] ?? ($userRow['MOBILE_NO'] ?? null)),
            'gender' => $detailsRow['gender'] ?? null,
            'puc_percentage' => $detailsRow['puc_percentage'] ?? 0.0,
            'sslc_percentage' => $detailsRow['sslc_percentage'] ?? 0.0,
            'district' => $detailsRow['district'] ?? null,
            'taluk' => $detailsRow['taluk'] ?? null,
            'state' => $detailsRow['state'] ?? null,
            'address' => $detailsRow['address'] ?? null,
            'dob' => $detailsRow['dob'] ?? null,
            
            'profile_photo' => $photo,
            'skills' => []
        ];
    }
    
    /**
     * Get profile with user details
     */
    public function getWithUser($profileId, $institution) {
        $prefix = ($institution === INSTITUTION_GMU) ? DB_GMU_PREFIX : DB_GMIT_PREFIX;
        
        if ($institution === INSTITUTION_GMU) {
            $sql = "SELECT * FROM {$prefix}ad_student_approved WHERE SL_NO = ?";
        } else {
            $sql = "SELECT * FROM {$prefix}ad_student_details WHERE enquiry_no = ?";
        }
        
        $stmt = $this->remoteDB->prepare($sql); // REMOTE
        $stmt->execute([$profileId]);
        $profile = $stmt->fetch();
        
        if (!$profile) return null;
        
        $usersTable = $prefix . 'users';
        $sqlUser = "SELECT * FROM {$usersTable} WHERE USER_NAME = ? OR AADHAR = ?";
        $stmtU = $this->remoteDB->prepare($sqlUser); // REMOTE
        $stmtU->execute([$profile['usn'], $profile['aadhar']]);
        $user = $stmtU->fetch();
        
        $mapped = ($institution === INSTITUTION_GMU) 
            ? $this->mapToAppProfile($profile, $user, [], $institution)
            : $this->mapToAppProfile([], $user, $profile, $institution);

        if ($user) {
            $mapped['username'] = $user['USER_NAME'];
            $mapped['email'] = $user['USER_NAME']; 
            $mapped['full_name'] = $user['NAME'];
            $mapped['phone'] = $user['MOBILE_NO'];
        }
        
        return $mapped;
    }
    
    /**
     * Get profile with skills
     */
    public function getWithSkills($userId) {
        $profile = $this->getByUserId($userId);
        if (!$profile) return null;
        
        // Skills are LOCAL
        $sql = "SELECT s.*, ss.proficiency_level
                FROM student_skills ss
                JOIN skills s ON ss.skill_id = s.id
                WHERE ss.student_id = ?
                ORDER BY ss.proficiency_level DESC, s.name ASC";
        
        $stmt = $this->db->prepare($sql); // LOCAL
        $stmt->execute([$userId]);
        $profile['skills'] = $stmt->fetchAll();
        
        return $profile;
    }
    
    /**
     * Check if student is eligible for job
     */
    public function isEligibleForJob($userId, $jobRequirements) {
        // Default to strict check if min_cgpa is set
        if (isset($jobRequirements['min_cgpa']) && $jobRequirements['min_cgpa'] > 0) {
            $check = $this->isEligibleStrict($userId, $jobRequirements['min_cgpa'], $jobRequirements);
            return $check['eligible'];
        }
        
        $profile = $this->getByUserId($userId);
        if (!$profile) return false;

        if (isset($jobRequirements['eligible_courses'])) {
            $courses = json_decode($jobRequirements['eligible_courses'], true);
            if ($courses && !in_array($profile['course'], $courses)) {
                return false;
            }
        }

        if (isset($jobRequirements['eligible_years'])) {
            $years = json_decode($jobRequirements['eligible_years'], true);
            if ($years && !in_array($profile['year_of_study'], $years)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strict SGPA check: All semesters must be above threshold
     */
    public function isEligibleStrict($userId, $threshold, $jobRequirements = []) {
        $profile = $this->getByUserId($userId);
        $history = $this->getAcademicHistory($userId);

        if (empty($history) && $threshold > 0) {
            return ['eligible' => false, 'reasons' => ["Academic history not found"]];
        }

        // Derive current semester from history (highest sem recorded)
        $currentSem = (isset($profile['semester']) && (int)$profile['semester'] > 0)
            ? (int)$profile['semester'] : 0;
        if ($currentSem === 0 && !empty($history)) {
            foreach ($history as $h) {
                if ((int)($h['semester'] ?? 0) > $currentSem) {
                    $currentSem = (int)$h['semester'];
                }
            }
        }

        $eligible = true;
        $reasons  = [];

        // --- SGPA check: all completed semesters must meet threshold ---
        if ($threshold > 0) {
            $checkedSems = 0;
            foreach ($history as $sem) {
                $semNum = (int)($sem['semester'] ?? 0);
                if ($semNum === 0 || $semNum >= $currentSem) continue; // skip current/future
                $checkedSems++;
                if ((float)$sem['sgpa'] < (float)$threshold) {
                    $eligible = false;
                    $reasons[] = "SGPA in Semester $semNum ({$sem['sgpa']}) is below required $threshold";
                }
            }
        }

        // --- Course check: use $profile (reliable getByUserId result) ---
        if (!empty($jobRequirements['eligible_courses'])) {
            $courses = json_decode($jobRequirements['eligible_courses'], true);
            $studentCourse = strtoupper(trim($profile['course'] ?? ''));
            if (!empty($courses) && $studentCourse !== '' && !in_array($studentCourse, $courses)) {
                $eligible = false;
                $reasons[] = "Course $studentCourse is not eligible for this job";
            }
        }

        // --- Year of study check: use $profile ---
        if (!empty($jobRequirements['eligible_years'])) {
            $years = json_decode($jobRequirements['eligible_years'], true);
            // Derive year: prefer profile, fallback to currentSem
            $studentYear = (int)($profile['year_of_study'] ?? ($currentSem > 0 ? ceil($currentSem / 2) : 0));
            if (!empty($years) && $studentYear > 0 && !in_array((string)$studentYear, array_map('strval', $years))) {
                $eligible = false;
                $reasons[] = "Students in year $studentYear are not eligible for this job";
            }
        }

        // --- Branch check: use $profile ---
        if (!empty($jobRequirements['eligible_branches'])) {
            $branches = json_decode($jobRequirements['eligible_branches'], true);
            $studentBranch = strtoupper(trim($profile['department'] ?? ''));
            if (!empty($branches) && $studentBranch !== '') {
                $equivalentBranches = getEquivalentBranches($studentBranch);
                $matchFound = false;
                foreach ($equivalentBranches as $eqBranch) {
                    if (in_array($eqBranch, $branches)) { $matchFound = true; break; }
                }
                if (!$matchFound) {
                    $eligible = false;
                    $reasons[] = "Branch $studentBranch is not eligible (Open to: " . implode(', ', $branches) . ")";
                }
            }
        }

        return ['eligible' => $eligible, 'reasons' => $reasons];
    }

    
    /**
     * Get all students with profiles
     */
    public function getAllWithUsers($filters = []) {
        $gmuPrefix = DB_GMU_PREFIX;
        $gmitPrefix = DB_GMIT_PREFIX;
        
        // REMOTE JOIN (GMU uses SL_NO, GMIT uses ENQUIRY_NO for user id). Include sem for coordinator 5-8 filter.
        // We use a subquery for GMU to only get the LATEST administrative row per USN to avoid duplication.
        $sql = "SELECT ad.usn, ad.course, ad.discipline, ad.academic_year, IFNULL(u.USER_NAME, ad.usn) as USER_NAME, IFNULL(u.NAME, ad.name) as NAME, u.MOBILE_NO, IFNULL(u.SL_NO, 0) as user_sl_no, '" . INSTITUTION_GMU . "' as institution, ad.sem
                FROM {$gmuPrefix}ad_student_approved ad
                INNER JOIN (
                    SELECT usn, MAX(SL_NO) as max_sl 
                    FROM {$gmuPrefix}ad_student_approved 
                    GROUP BY usn
                ) latest ON ad.usn = latest.usn AND ad.SL_NO = latest.max_sl
                LEFT JOIN {$gmuPrefix}users u ON u.USER_NAME = ad.usn AND u.STATUS = 'ACTIVE'
                UNION ALL
                SELECT IFNULL(NULLIF(ad.usn, ''), ad.student_id) as usn, ad.course, ad.discipline, ad.academic_year, IFNULL(u.USER_NAME, IFNULL(NULLIF(ad.usn, ''), ad.student_id)) as USER_NAME, IFNULL(u.NAME, ad.name) as NAME, u.MOBILE_NO, IFNULL(u.ENQUIRY_NO, 0) as user_sl_no, '" . INSTITUTION_GMIT . "' as institution, 0 as sem
                FROM {$gmitPrefix}ad_student_details ad
                LEFT JOIN {$gmitPrefix}users u ON (u.USER_NAME = ad.usn OR u.USER_NAME = ad.student_id OR u.AADHAR = ad.aadhar OR u.ENQUIRY_NO = ad.enquiry_no) AND u.STATUS = 'ACTIVE'";
        
        $sql = "SELECT * FROM ({$sql}) as combined WHERE 1=1";
                
        $params = [];
        if (!empty($filters['usns'])) {
            $usns = (array)$filters['usns'];
            $placeholders = implode(',', array_fill(0, count($usns), '?'));
            $sql .= " AND usn IN ($placeholders)";
            $params = array_merge($params, $usns);
        }

        if (isset($filters['course'])) {
            $sql .= " AND course = ?";
            $params[] = $filters['course'];
        }
        
        if (isset($filters['year'])) {
            $sql .= " AND year = ?";
            $params[] = $filters['year'];
        }

        // Discipline: Handle both single string and array of disciplines
        if (!empty($filters['discipline'])) {
            $disciplines = is_array($filters['discipline']) ? $filters['discipline'] : [$filters['discipline']];
            
            // If institution is provided, filter normally
            if (!empty($filters['institution'])) {
                $placeholders = implode(',', array_fill(0, count($disciplines), '?'));
                $sql .= " AND discipline IN ($placeholders)";
                $params = array_merge($params, $disciplines);
            } else {
                // If no institution, expand disciplines for both GMU and GMIT mappings if it's a single string
                // But if it's already an array (from consolidated coordinator), use it directly with IN clause
                if (is_array($filters['discipline'])) {
                    $placeholders = implode(',', array_fill(0, count($disciplines), '?'));
                    $sql .= " AND discipline IN ($placeholders)";
                    $params = array_merge($params, $disciplines);
                } else {
                    $mapped = getCoordinatorDisciplineFilters($filters['discipline']);
                    $placeholders = implode(',', array_fill(0, count($mapped), '?'));
                    $sql .= " AND discipline IN ($placeholders)";
                    $params = array_merge($params, $mapped);
                }
            }
        }

        // Single institution filter
        if (!empty($filters['institution'])) {
            $sql .= " AND institution = ?";
            $params[] = $filters['institution'];
        }

        // Coordinator: only semesters 5,6,7,8. GMU: sem in table; GMIT: student_sem_sgpa. No institution = both.
        if (!empty($filters['semesters']) && is_array($filters['semesters'])) {
            $sems = array_map('intval', $filters['semesters']);
            $sems = array_filter($sems, function ($s) { return $s >= 1 && $s <= 8; });
            if (!empty($sems)) {
                $inst = $filters['institution'] ?? null;
                $semPlaceholders = implode(',', $sems);
                if ($inst === INSTITUTION_GMIT) {
                    $stmtLocal = $this->db->prepare("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE institution = ? AND semester IN (" . implode(',', array_fill(0, count($sems), '?')) . ")");
                    $stmtLocal->execute(array_merge([INSTITUTION_GMIT], $sems));
                    $gmitUsns = $stmtLocal->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($gmitUsns)) {
                        $ph = implode(',', array_fill(0, count($gmitUsns), '?'));
                        $sql .= " AND institution = '" . INSTITUTION_GMIT . "' AND usn IN ($ph)";
                        $params = array_merge($params, $gmitUsns);
                    } else {
                        $sql .= " AND 1=0";
                    }
                } elseif ($inst === INSTITUTION_GMU) {
                    $sql .= " AND sem IN ($semPlaceholders)";
                } else {
                    // No institution = coordinator sees both GMU and GMIT
                    $stmtLocal = $this->db->prepare("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE institution = ? AND semester IN (" . implode(',', array_fill(0, count($sems), '?')) . ")");
                    $stmtLocal->execute(array_merge([INSTITUTION_GMIT], $sems));
                    $gmitUsns = $stmtLocal->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($gmitUsns)) {
                        $ph = implode(',', array_fill(0, count($gmitUsns), '?'));
                        $sql .= " AND ( (institution = '" . INSTITUTION_GMU . "' AND sem IN ($semPlaceholders)) OR (institution = '" . INSTITUTION_GMIT . "' AND usn IN ($ph)) )";
                        $params = array_merge($params, $gmitUsns);
                    } else {
                        // No GMIT rows in student_sem_sgpa for these semesters
                        // If specifically filtering for these semesters, we should only show GMU students that match
                        $sql .= " AND (institution = '" . INSTITUTION_GMU . "' AND sem IN ($semPlaceholders))";
                    }
                }
            }
        }
        
        $sql .= " ORDER BY academic_year DESC, NAME ASC";
        
        $stmt = $this->remoteDB->prepare($sql); // REMOTE
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // One row per student with latest sem only: GMU = max(sem) per usn, GMIT = max(semester) from student_sem_sgpa
        $rows = $this->dedupeRowsByLatestSem($rows, $filters);
        
        $results = [];
        foreach ($rows as $row) {
             $userStub = ['PHOTO' => null, 'SL_NO' => $row['user_sl_no'], 'NAME' => $row['NAME'], 'USER_NAME' => $row['USER_NAME']];
             $p = $this->mapToAppProfile($row, $userStub, [], $row['institution']);
             $results[] = $p;
        }
        return $results;
    }
    
    public function getTotalAcademicStrength($filters = []) {
        $gmuPrefix = DB_GMU_PREFIX;
        $gmitPrefix = DB_GMIT_PREFIX;
        
        $combinedApproved = "(
            SELECT usn, discipline, sem, registered, '" . INSTITUTION_GMU . "' as institution FROM {$gmuPrefix}ad_student_approved
            UNION ALL
            SELECT IFNULL(NULLIF(usn, ''), student_id) as usn, discipline, 0 as sem, 1 as registered, '" . INSTITUTION_GMIT . "' as institution FROM {$gmitPrefix}ad_student_details
        )";

        $where_clauses = []; // No registered filter — matches students_report.php and assign_task.php
        $params = [];

        if (!empty($filters['discipline'])) {
            $disciplines = is_array($filters['discipline']) ? $filters['discipline'] : [$filters['discipline']];
            $placeholders = implode(',', array_fill(0, count($disciplines), '?'));
            $where_clauses[] = "discipline IN ($placeholders)";
            $params = array_merge($params, $disciplines);
        }

        if (!empty($filters['institution'])) {
            $where_clauses[] = "institution = ?";
            $params[] = $filters['institution'];
        }

        // Semester filtering
        if (!empty($filters['semesters']) && is_array($filters['semesters'])) {
            $sems = array_map('intval', $filters['semesters']);
            $semPlaceholdersSql = implode(',', array_fill(0, count($sems), '?'));
            $semValuesString = implode(',', $sems); // For the remote query part
            
            // GMIT USNs with SGPA in these semesters
            $stmtLocal = $this->db->prepare("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE institution = ? AND semester IN ($semPlaceholdersSql)");
            $stmtLocal->execute(array_merge([INSTITUTION_GMIT], $sems));
            $gmitUsnsRaw = $stmtLocal->fetchAll(PDO::FETCH_COLUMN);

            // Expand GMIT IDs
            $gmitUsns = $gmitUsnsRaw;
            if (!empty($gmitUsnsRaw)) {
                $db_gmit = getDB('gmit');
                if ($db_gmit) {
                    $in_ph = implode(',', array_fill(0, count($gmitUsnsRaw), '?'));
                    $stmtRef = $db_gmit->prepare("SELECT DISTINCT usn, student_id FROM ad_student_details WHERE student_id IN ($in_ph) OR usn IN ($in_ph) OR aadhar IN ($in_ph) OR aadhar_no IN ($in_ph)");
                    $stmtRef->execute(array_merge($gmitUsnsRaw, $gmitUsnsRaw, $gmitUsnsRaw, $gmitUsnsRaw));
                    $mapped = $stmtRef->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($mapped as $m) {
                        if ($m['usn']) $gmitUsns[] = $m['usn'];
                        if ($m['student_id']) $gmitUsns[] = $m['student_id'];
                    }
                    $gmitUsns = array_values(array_unique($gmitUsns));
                }
            }

            if (!empty($gmitUsns)) {
                $gmitPh = implode(',', array_fill(0, count($gmitUsns), '?'));
                $where_clauses[] = "((institution = '" . INSTITUTION_GMU . "' AND sem IN ($semValuesString)) OR (institution = '" . INSTITUTION_GMIT . "' AND usn IN ($gmitPh)))";
                $params = array_merge($params, $gmitUsns);
            } else {
                $where_clauses[] = "(institution = '" . INSTITUTION_GMU . "' AND sem IN ($semValuesString))";
            }
        }

        $where_sql = implode(" AND ", $where_clauses);
        $sql = "SELECT COUNT(DISTINCT usn) FROM {$combinedApproved} as asa WHERE $where_sql";
        
        $stmt = $this->remoteDB->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function search($query, $filters = []) {
        $gmuPrefix = DB_GMU_PREFIX;
        $gmitPrefix = DB_GMIT_PREFIX;
        $searchTerm = "%{$query}%";

        // REMOTE JOIN (include sem for coordinator 5-8 filter)
        $sql = "SELECT * FROM (
                    SELECT ad.usn, ad.name, ad.discipline, IFNULL(u.USER_NAME, ad.usn) as USER_NAME, IFNULL(u.NAME, ad.name) as user_name, IFNULL(u.SL_NO, 0) as user_sl_no, u.MOBILE_NO, ad.course, ad.academic_year, '" . INSTITUTION_GMU . "' as institution, ad.sem
                    FROM {$gmuPrefix}ad_student_approved ad
                    LEFT JOIN {$gmuPrefix}users u ON u.USER_NAME = ad.usn AND u.STATUS = 'ACTIVE'
                    UNION ALL
                    SELECT IFNULL(NULLIF(ad.usn, ''), ad.student_id) as usn, ad.name, ad.discipline, IFNULL(u.USER_NAME, IFNULL(NULLIF(ad.usn, ''), ad.student_id)) as USER_NAME, IFNULL(u.NAME, ad.name) as user_name, IFNULL(u.ENQUIRY_NO, 0) as user_sl_no, u.MOBILE_NO, ad.course, ad.academic_year, '" . INSTITUTION_GMIT . "' as institution, 0 as sem
                    FROM {$gmitPrefix}ad_student_details ad
                    LEFT JOIN {$gmitPrefix}users u ON (u.USER_NAME = ad.usn OR u.USER_NAME = ad.student_id OR u.AADHAR = ad.aadhar_no OR u.AADHAR = ad.aadhar OR u.ENQUIRY_NO = ad.enquiry_no) AND u.STATUS = 'ACTIVE'
                ) as combined
                WHERE (name LIKE ? OR usn LIKE ? OR USER_NAME LIKE ?)";
        $params = [$searchTerm, $searchTerm, $searchTerm];

        if (!empty($filters['discipline'])) {
            $disciplines = is_array($filters['discipline']) ? $filters['discipline'] : [$filters['discipline']];
            
            if (!empty($filters['institution'])) {
                $placeholders = implode(',', array_fill(0, count($disciplines), '?'));
                $sql .= " AND discipline IN ($placeholders)";
                $params = array_merge($params, $disciplines);
            } else {
                if (is_array($filters['discipline'])) {
                    $placeholders = implode(',', array_fill(0, count($disciplines), '?'));
                    $sql .= " AND discipline IN ($placeholders)";
                    $params = array_merge($params, $disciplines);
                } else {
                    $mapped = getCoordinatorDisciplineFilters($filters['discipline']);
                    $placeholders = implode(',', array_fill(0, count($mapped), '?'));
                    $sql .= " AND discipline IN ($placeholders)";
                    $params = array_merge($params, $mapped);
                }
            }
        }
        if (!empty($filters['institution'])) {
            $sql .= " AND institution = ?";
            $params[] = $filters['institution'];
        }
        // Coordinator: only semesters 5-8. No institution = both GMU and GMIT.
        if (!empty($filters['semesters']) && is_array($filters['semesters'])) {
            $sems = array_map('intval', $filters['semesters']);
            $sems = array_filter($sems, function ($s) { return $s >= 1 && $s <= 8; });
            if (!empty($sems)) {
                $inst = $filters['institution'] ?? null;
                $semPh = implode(',', $sems);
                if ($inst === INSTITUTION_GMIT) {
                    $stmtLocal = $this->db->prepare("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE institution = ? AND semester IN (" . implode(',', array_fill(0, count($sems), '?')) . ")");
                    $stmtLocal->execute(array_merge([INSTITUTION_GMIT], $sems));
                    $gmitUsns = $stmtLocal->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($gmitUsns)) {
                        $ph = implode(',', array_fill(0, count($gmitUsns), '?'));
                        $sql .= " AND institution = '" . INSTITUTION_GMIT . "' AND usn IN ($ph)";
                        $params = array_merge($params, $gmitUsns);
                    } else {
                        $sql .= " AND 1=0";
                    }
                } elseif ($inst === INSTITUTION_GMU) {
                    $sql .= " AND sem IN ($semPh)";
                } else {
                    // Both institutions
                    $stmtLocal = $this->db->prepare("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE institution = ? AND semester IN (" . implode(',', array_fill(0, count($sems), '?')) . ")");
                    $stmtLocal->execute(array_merge([INSTITUTION_GMIT], $sems));
                    $gmitUsns = $stmtLocal->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($gmitUsns)) {
                        $ph = implode(',', array_fill(0, count($gmitUsns), '?'));
                        $sql .= " AND ( (institution = '" . INSTITUTION_GMU . "' AND sem IN ($semPh)) OR (institution = '" . INSTITUTION_GMIT . "' AND usn IN ($ph)) )";
                        $params = array_merge($params, $gmitUsns);
                    } else {
                        $sql .= " AND ( (institution = '" . INSTITUTION_GMU . "' AND sem IN ($semPh)) OR (institution = '" . INSTITUTION_GMIT . "') )";
                    }
                }
            }
        }
        $sql .= " ORDER BY name ASC LIMIT 50";
        
        $stmt = $this->remoteDB->prepare($sql); // REMOTE
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // One row per student with latest sem only
        $rows = $this->dedupeRowsByLatestSem($rows, $filters);
        
        $results = [];
        foreach ($rows as $row) {
             $userStub = ['PHOTO' => null, 'SL_NO' => $row['user_sl_no'], 'NAME' => $row['user_name'] ?? $row['name'], 'USER_NAME' => $row['USER_NAME']];
             $p = $this->mapToAppProfile($row, $userStub, [], $row['institution']);
             $results[] = $p;
        }
        return $results;
    }

    /**
     * Keep one row per (institution, usn) with latest semester. GMU: max(sem) from row; GMIT: max(semester) from student_sem_sgpa.
     */
    private function dedupeRowsByLatestSem(array $rows, array $filters = []) {
        if (empty($rows)) {
            return $rows;
        }
        $gmitUsns = [];
        foreach ($rows as $row) {
            if (($row['institution'] ?? '') === INSTITUTION_GMIT) {
                $gmitUsns[$row['usn']] = true;
            }
        }
        $maxSemGmit = [];
        if (!empty($gmitUsns)) {
            $usnList = array_keys($gmitUsns);
            $placeholders = implode(',', array_fill(0, count($usnList), '?'));
            $stmt = $this->db->prepare("SELECT student_id, MAX(semester) as max_sem FROM student_sem_sgpa WHERE institution = ? AND student_id IN ($placeholders) GROUP BY student_id");
            $stmt->execute(array_merge([INSTITUTION_GMIT], $usnList));
            while ($r = $stmt->fetch()) {
                $maxSemGmit[$r['student_id']] = (int) $r['max_sem'];
            }
        }
        foreach ($rows as &$row) {
            if (($row['institution'] ?? '') === INSTITUTION_GMIT && isset($maxSemGmit[$row['usn']])) {
                $row['sem'] = $maxSemGmit[$row['usn']];
            }
        }
        unset($row);
        $grouped = [];
        foreach ($rows as $row) {
            $key = ($row['institution'] ?? '') . "\0" . ($row['usn'] ?? '');
            $cur = (int) ($row['sem'] ?? 0);
            if (!isset($grouped[$key]) || $cur > (int) ($grouped[$key]['sem'] ?? 0)) {
                $grouped[$key] = $row;
            }
        }
        return array_values($grouped);
    }

    /**
     * Get profile completion percentage (Placeholder)
     */
    public function getCompletionPercentage($userId) {
        return 80;
    }

    /**
     * Save semester-wise SGPA
     */
    public function saveSGPA($studentId, $institution, $semData, $currentSem = null) {
        try {
            $studentId = trim((string)$studentId);
            $this->db->beginTransaction(); // LOCAL TRANSACTION

            if ($currentSem) {
                $sqlReset = "UPDATE student_sem_sgpa SET is_current = 0 WHERE student_id = ? AND institution = ?";
                $stmtReset = $this->db->prepare($sqlReset);
                $stmtReset->execute([$studentId, $institution]);
            }

            $processedSems = [];

            foreach ($semData as $sem => $sgpa) {
                $processedSems[] = $sem;
                $isCurrent = ($sem == $currentSem) ? 1 : 0;
                
                $hasValue = ($sgpa !== null && $sgpa !== '' && $sgpa > 0);

                if (!$hasValue && !$isCurrent) {
                    $sqlDel = "DELETE FROM student_sem_sgpa WHERE student_id = ? AND semester = ? AND institution = ?";
                    $stmtDel = $this->db->prepare($sqlDel);
                    $stmtDel->execute([$studentId, $sem, $institution]);
                } else {
                    $valToSave = $hasValue ? $sgpa : 0.00;
                    $sql = "INSERT INTO student_sem_sgpa (student_id, semester, sgpa, institution, is_current) 
                            VALUES (?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE sgpa = VALUES(sgpa), is_current = VALUES(is_current)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$studentId, $sem, $valToSave, $institution, $isCurrent]);
                }
            }

            if ($currentSem && !in_array($currentSem, $processedSems)) {
                $sqlCurrent = "INSERT INTO student_sem_sgpa (student_id, semester, sgpa, institution, is_current) 
                               VALUES (?, ?, 0.00, ?, 1) 
                               ON DUPLICATE KEY UPDATE is_current = 1";
                $stmtCurrent = $this->db->prepare($sqlCurrent);
                $stmtCurrent->execute([$studentId, $currentSem, $institution]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("saveSGPA Error: " . $e->getMessage());
            return false;
        }
    }

}
