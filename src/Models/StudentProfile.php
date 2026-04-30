<?php
/**
 * StudentProfile Model
 */

require_once __DIR__ . '/Model.php';

class StudentProfile extends Model {
    protected $table;
    protected $primaryKey = 'SL_NO';
    protected $remoteDB;
    
    public function __construct() {
        parent::__construct();
        // Initialize remote connection for GMU/GMIT tables
        $this->remoteDB = getDB('gmu');
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
        $user = $userModel->find($userId, $institution);
        
        if (!$user) return null;
        
        $inst = $user['institution'];
        $prefix = ($inst === INSTITUTION_GMU) ? DB_GMU_PREFIX : DB_GMIT_PREFIX;
        $username = $user['username'];
        $aadhar = $user['aadhar'];
        
        if ($inst === INSTITUTION_GMIT) {
            // GMIT: Map app USER_NAME to student_id or use ENQUIRY_NO
            $enquiryNo = $user['id'];

            // Since username/aadhar are already extracted above, we use them directly
            $sqlDetails = "SELECT * FROM {$prefix}ad_student_details 
                           WHERE enquiry_no = ? OR student_id = ? OR usn = ? OR (aadhar IS NOT NULL AND aadhar = ?) 
                           LIMIT 1";
            $stmt = $this->remoteDB->prepare($sqlDetails); // REMOTE
            $stmt->execute([$enquiryNo, $username, $username, $aadhar]);
            $details = $stmt->fetch();

            // Fetch Current Semester & SGPA from student_sem_sgpa for GMIT (LOCAL)
            $sqlCurrent = "SELECT semester, sgpa FROM student_sem_sgpa 
                           WHERE student_id = ? AND institution = ? AND is_current = 1 
                           LIMIT 1";
            $stmtC = $this->db->prepare($sqlCurrent); // LOCAL
            $stmtC->execute([$username, INSTITUTION_GMIT]); // Using username (student_id)
            $currentData = $stmtC->fetch();

            $mapped = $this->mapToAppProfile([], $user, $details ?: [], $inst);
            if ($currentData) {
                $mapped['semester'] = $currentData['semester'];
                $mapped['sgpa'] = $currentData['sgpa'];
                $mapped['cgpa'] = $currentData['sgpa']; 
                // Derive year if missing
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
            $stmtA = $this->remoteDB->prepare($sqlApproved); // REMOTE
            $stmtA->execute([$username, $aadhar]);
            $approved = $stmtA->fetch();

            $sqlDetails = "SELECT * FROM {$prefix}ad_student_details 
                           WHERE usn = ? OR (aadhar IS NOT NULL AND aadhar = ?) 
                           LIMIT 1";
            $stmtD = $this->remoteDB->prepare($sqlDetails); // REMOTE
            $stmtD->execute([$username, $aadhar]);
            $details = $stmtD->fetch();
            
            return $this->mapToAppProfile($approved ?: [], $user, $details ?: [], $inst);
        }
    }
    
    /**
     * Alias for getByUserId - used by Career Advisor
     */
    public function getProfile($userId) {
        return $this->getByUserId($userId);
    }

    public function getAcademicHistory($userId, $institution = null) {
        if (!$userId) return [];
        
        $userModel = new User();
        $user = $userModel->find($userId, $institution);
        
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
            $rawPhoto = $userRow['photo'] ?? ($userRow['PHOTO'] ?? null);
            if (!empty($rawPhoto)) {
                 $photoData = json_decode($rawPhoto, true);
                 if (is_array($photoData) && isset($photoData[0]['thumbnail'])) {
                     $photo = $photoData[0]['thumbnail'];
                 } else {
                     $photo = $rawPhoto;
                 }
            }
        }

        return [
            'id' => (!empty($row['SL_NO']) ? $row['SL_NO'] : (!empty($detailsRow['enquiry_no']) ? $detailsRow['enquiry_no'] : null)),
            'user_id' => $userRow['id'] ?? ($userRow['SL_NO'] ?? null),
            'usn' => (!empty($row['usn']) ? $row['usn'] : (!empty($detailsRow['usn']) ? $detailsRow['usn'] : ($userRow['username'] ?? null))),
            'enrollment_number' => (!empty($row['usn']) ? $row['usn'] : (!empty($detailsRow['usn']) ? $detailsRow['usn'] : ($userRow['username'] ?? ($userRow['USER_NAME'] ?? ($userRow['EMP_ID'] ?? ($userRow['raw']['EMP_ID'] ?? null)))))),
            'student_id' => (!empty($detailsRow['student_id']) ? $detailsRow['student_id'] : (!empty($row['student_id']) ? $row['student_id'] : ($userRow['username'] ?? ($userRow['USER_NAME'] ?? ($userRow['EMP_ID'] ?? ($userRow['raw']['EMP_ID'] ?? null)))))),
            'course' => (!empty($row['course']) ? $row['course'] : (!empty($detailsRow['course']) ? $detailsRow['course'] : ($userRow['COURSE'] ?? ($userRow['raw']['COURSE'] ?? null)))),
            'department' => (!empty($row['discipline']) ? $row['discipline'] : (!empty($detailsRow['discipline']) ? $detailsRow['discipline'] : ($userRow['DISCIPLINE'] ?? ($userRow['raw']['DISCIPLINE'] ?? ($userRow['raw']['DEPT_ID'] ?? null))))),
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
            return $this->isEligibleStrict($userId, $jobRequirements['min_cgpa'], $jobRequirements);
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
        
        if (empty($history)) {
            return ['eligible' => false, 'reasons' => ["Academic history not found"]];
        }

        // Derive current semester if not explicitly marked
        $currentSem = (isset($profile['semester']) && $profile['semester'] > 0) ? (int)$profile['semester'] : 0;
        if ($currentSem === 0) {
            $latestSemRecord = 0;
            foreach($history as $h) {
                if ((int)$h['semester'] > $latestSemRecord) $latestSemRecord = (int)$h['semester'];
            }
            $currentSem = $latestSemRecord + 1; // Assume student is in the semester following their latest record
        }

        $eligible = true;
        $reasons = [];

        // Check all semesters BEFORE the current one
        $checkedSems = 0;
        foreach ($history as $sem) {
            $semNum = (int)($sem['semester'] ?? 0);
            
            if ($semNum >= $currentSem) {
                continue;
            }

            $checkedSems++;
            if ($sem['sgpa'] < $threshold) {
                $eligible = false;
                $reasons[] = "SGPA in Semester $semNum ({$sem['sgpa']}) is below required $threshold";
            }
        }

        // Check Course & Year (using latest profile data)
        $latestProfile = $history[0]; 
        
        if (isset($jobRequirements['eligible_courses'])) {
            $courses = json_decode($jobRequirements['eligible_courses'], true);
            if ($courses && !in_array($latestProfile['course'], $courses)) {
                $eligible = false;
                $reasons[] = "Course {$latestProfile['course']} is not eligible for this job";
            }
        }

        if (isset($jobRequirements['eligible_years'])) {
            $years = json_decode($jobRequirements['eligible_years'], true);
            $studentYear = $latestProfile['year_of_study'] ?? (isset($latestProfile['semester']) ? ceil((int)$latestProfile['semester'] / 2) : 0);
            if ($years && !in_array($studentYear, $years)) {
                $eligible = false;
                $reasons[] = "Students in year $studentYear are not eligible for this job";
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
        $sql = "SELECT ad.usn, ad.course, ad.discipline, ad.academic_year, u.USER_NAME, u.NAME, u.MOBILE_NO, u.SL_NO as user_sl_no, '" . INSTITUTION_GMU . "' as institution, ad.sem
                FROM {$gmuPrefix}ad_student_approved ad
                INNER JOIN (
                    SELECT usn, MAX(SL_NO) as max_sl 
                    FROM {$gmuPrefix}ad_student_approved 
                    GROUP BY usn
                ) latest ON ad.usn = latest.usn AND ad.SL_NO = latest.max_sl
                JOIN {$gmuPrefix}users u ON u.USER_NAME = ad.usn
                WHERE u.STATUS = 'ACTIVE'
                UNION ALL
                SELECT ad.usn, ad.course, ad.discipline, ad.academic_year, u.USER_NAME, u.NAME, u.MOBILE_NO, u.ENQUIRY_NO as user_sl_no, '" . INSTITUTION_GMIT . "' as institution, 0 as sem
                FROM {$gmitPrefix}ad_student_details ad
                JOIN {$gmitPrefix}users u ON (u.USER_NAME = ad.usn OR u.AADHAR = ad.aadhar)
                WHERE u.STATUS = 'ACTIVE'";
        
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
    
    public function search($query, $filters = []) {
        $gmuPrefix = DB_GMU_PREFIX;
        $gmitPrefix = DB_GMIT_PREFIX;
        $searchTerm = "%{$query}%";

        // REMOTE JOIN (include sem for coordinator 5-8 filter)
        $sql = "SELECT * FROM (
                    SELECT ad.usn, ad.name, ad.discipline, u.USER_NAME, u.NAME as user_name, u.SL_NO as user_sl_no, u.MOBILE_NO, ad.course, ad.academic_year, '" . INSTITUTION_GMU . "' as institution, ad.sem
                    FROM {$gmuPrefix}ad_student_approved ad
                    JOIN {$gmuPrefix}users u ON u.USER_NAME = ad.usn
                    WHERE u.STATUS = 'ACTIVE'
                    UNION ALL
                    SELECT ad.usn, ad.name, ad.discipline, u.USER_NAME, u.NAME as user_name, u.ENQUIRY_NO as user_sl_no, u.MOBILE_NO, ad.course, ad.academic_year, '" . INSTITUTION_GMIT . "' as institution, 0 as sem
                    FROM {$gmitPrefix}ad_student_details ad
                    JOIN {$gmitPrefix}users u ON (u.USER_NAME = ad.usn OR u.AADHAR = ad.aadhar)
                    WHERE u.STATUS = 'ACTIVE'
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
