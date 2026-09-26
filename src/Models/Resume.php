<?php
/**
 * Resume Model
 * Handles student resume data and PDF generation
 */

require_once __DIR__ . '/Model.php';

class Resume extends Model {
    protected $table = 'student_resumes';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'student_id', 'full_name', 'email', 'phone', 'location', 'gender', 'address',
        'linkedin_url', 'github_url', 'portfolio_url', 'professional_summary',
        'education', 'experience', 'projects', 'skills',
        'certifications', 'achievements', 'resume_data', 'template_id'
    ];
    
    /**
     * Get resume by student ID
     */
    public function getByStudentId($studentId) {
        if (is_array($studentId)) {
            error_log("getByStudentId called with array: " . print_r($studentId, true));
            return null;
        }
        $sql = "SELECT * FROM {$this->table} WHERE (student_id = ? OR UPPER(student_id) = UPPER(?)) ORDER BY last_updated DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $studentId]);
        $resume = $stmt->fetch();
        
        if ($resume) {
            // Decode JSON fields
            $resume['education'] = json_decode($resume['education'] ?? '[]', true);
            $resume['experience'] = json_decode($resume['experience'] ?? '[]', true);
            $resume['projects'] = json_decode($resume['projects'] ?? '[]', true);
            $resume['skills'] = json_decode($resume['skills'] ?? '{}', true);
            $resume['certifications'] = json_decode($resume['certifications'] ?? '[]', true);
            $resume['achievements'] = json_decode($resume['achievements'] ?? '[]', true);
            $resume['resume_data'] = json_decode($resume['resume_data'] ?? '{}', true);
        }
        
        return $resume;
    }

    /**
     * Check if student has a valid resume (DB record or PDF file on disk)
     * Accepts single ID or array of IDs (e.g. USN, user_id, Aadhar)
     */
    public function hasResume($studentId) {
        if (empty($studentId)) return false;

        $ids = is_array($studentId) ? $studentId : [$studentId];

        // 1. Check database for valid record
        foreach ($ids as $id) {
            $resume = $this->getByStudentId($id);
            if (!empty($resume) && !empty($resume['full_name'])) {
                return true;
            }
        }

        // 2. Check disk for uploaded / generated PDF file
        foreach ($ids as $id) {
            $idStr = trim((string)$id);
            if (empty($idStr)) continue;

            $variants = array_unique([$idStr, strtoupper($idStr), strtolower($idStr)]);
            foreach ($variants as $v) {
                if (defined('UPLOADS_PATH')) {
                    if (file_exists(UPLOADS_PATH . '/resumes/Student_Resumes/' . $v . '_Resume.pdf') ||
                        file_exists(UPLOADS_PATH . '/resumes/' . $v . '_Resume.pdf')) {
                        return true;
                    }
                }
                if (defined('RESUME_UPLOAD_PATH')) {
                    if (file_exists(RESUME_UPLOAD_PATH . '/Student_Resumes/' . $v . '_Resume.pdf') ||
                        file_exists(RESUME_UPLOAD_PATH . '/' . $v . '_Resume.pdf')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
    
    /**
     * Save or update resume
     */
    public function saveResume($studentId, $resumeData) {
        // Encode JSON fields
        $jsonFields = [
            'education' => json_encode($resumeData['education'] ?? []),
            'experience' => json_encode($resumeData['experience'] ?? []),
            'projects' => json_encode($resumeData['projects'] ?? []),
            'skills' => json_encode($resumeData['skills'] ?? []),
            'certifications' => json_encode($resumeData['certifications'] ?? []),
            'achievements' => json_encode($resumeData['achievements'] ?? []),
            'resume_data' => json_encode($resumeData)
        ];
        
        // Check if resume exists
        $existing = $this->getByStudentId($studentId);
        
        if ($existing) {
            // Update existing resume
            $sql = "UPDATE {$this->table} SET
                    full_name = ?,
                    email = ?,
                    phone = ?,
                    location = ?,
                    gender = ?,
                    address = ?,
                    linkedin_url = ?,
                    github_url = ?,
                    portfolio_url = ?,
                    professional_summary = ?,
                    education = ?,
                    experience = ?,
                    projects = ?,
                    skills = ?,
                    certifications = ?,
                    achievements = ?,
                    resume_data = ?,
                    template_id = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $resumeData['full_name'],
                $resumeData['email'],
                $resumeData['phone'] ?? null,
                $resumeData['location'] ?? null,
                $resumeData['gender'] ?? null,
                $resumeData['address'] ?? null,
                $resumeData['linkedin_url'] ?? null,
                $resumeData['github_url'] ?? null,
                $resumeData['portfolio_url'] ?? null,
                $resumeData['professional_summary'] ?? null,
                $jsonFields['education'],
                $jsonFields['experience'],
                $jsonFields['projects'],
                $jsonFields['skills'],
                $jsonFields['certifications'],
                $jsonFields['achievements'],
                $jsonFields['resume_data'],
                $resumeData['template_id'] ?? 'professional_ats',
                $existing['id']
            ]);
        } else {
            // Insert new resume
            $sql = "INSERT INTO {$this->table} (
                    student_id, full_name, email, phone, location, gender, address,
                    linkedin_url, github_url, portfolio_url, professional_summary,
                    education, experience, projects, skills,
                    certifications, achievements, resume_data, template_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $studentId,
                $resumeData['full_name'],
                $resumeData['email'],
                $resumeData['phone'] ?? null,
                $resumeData['location'] ?? null,
                $resumeData['gender'] ?? null,
                $resumeData['address'] ?? null,
                $resumeData['linkedin_url'] ?? null,
                $resumeData['github_url'] ?? null,
                $resumeData['portfolio_url'] ?? null,
                $resumeData['professional_summary'] ?? null,
                $jsonFields['education'],
                $jsonFields['experience'],
                $jsonFields['projects'],
                $jsonFields['skills'],
                $jsonFields['certifications'],
                $jsonFields['achievements'],
                $jsonFields['resume_data'],
                $resumeData['template_id'] ?? 'professional_ats'
            ]);
        }
    }
    
    /**
     * Get resume data by ID
     */
    public function getResumeData($resumeId) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$resumeId]);
        $resume = $stmt->fetch();
        
        if ($resume) {
            // Decode JSON fields
            $resume['education'] = json_decode($resume['education'] ?? '[]', true);
            $resume['experience'] = json_decode($resume['experience'] ?? '[]', true);
            $resume['projects'] = json_decode($resume['projects'] ?? '[]', true);
            $resume['skills'] = json_decode($resume['skills'] ?? '{}', true);
            $resume['certifications'] = json_decode($resume['certifications'] ?? '[]', true);
            $resume['achievements'] = json_decode($resume['achievements'] ?? '[]', true);
            $resume['resume_data'] = json_decode($resume['resume_data'] ?? '{}', true);
        }
        
        return $resume;
    }
    
    /**
     * Delete resume
     */
    public function deleteResume($studentId) {
        $sql = "DELETE FROM {$this->table} WHERE student_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$studentId]);
    }
    
    /**
     * Get all resumes (for admin)
     */
    public function getAllResumes($limit = null) {
        $gmuUsers = DB_GMU_PREFIX . 'users';
        $gmitUsers = DB_GMIT_PREFIX . 'users';
        $sql = "SELECT sr.*, u.NAME as student_name, u.USER_NAME 
                FROM {$this->table} sr
                JOIN (
                    SELECT SL_NO, NAME, USER_NAME FROM {$gmuUsers}
                    UNION ALL
                    SELECT SL_NO, NAME, USER_NAME FROM {$gmitUsers}
                ) u ON sr.student_id = u.SL_NO
                ORDER BY sr.last_updated DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Get cached analysis for a resume
     */
    public function getCachedAnalysis($userId, $resumeText) {
        $hash = hash('sha256', trim($resumeText));
        $sql = "SELECT analysis_json FROM resume_analysis_cache WHERE user_id = ? AND resume_hash = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $hash]);
        $row = $stmt->fetch();
        
        return $row ? json_decode($row['analysis_json'], true) : null;
    }

    /**
     * Get the latest analysis for a user
     */
    public function getLatestAnalysis($userId) {
        error_log("Fetching latest analysis for user: " . $userId);
        $sql = "SELECT analysis_json FROM resume_analysis_cache WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        
        if ($row) {
            error_log("Analysis found for user: " . $userId);
        } else {
            error_log("No analysis found for user: " . $userId);
        }

        return $row ? json_decode($row['analysis_json'], true) : null;
    }

    /**
     * Store analysis in cache
     */
    public function cacheAnalysis($userId, $resumeText, $analysis) {
        $hash = hash('sha256', trim($resumeText));
        $json = json_encode($analysis);
        if ($json === false) {
            logMessage("ERROR - json_encode failed: " . json_last_error_msg(), 'ERROR');
            return false;
        }
        
        logMessage("cacheAnalysis called for user: $userId", 'DEBUG');
        if (!$this->db) {
            logMessage("ERROR - No database connection in Resume model!", 'ERROR');
            error_log("Resume Model: No database connection in cacheAnalysis");
            return false;
        }
        logMessage("DB connection exists. Proceeding with SQL...", 'DEBUG');
        // Use REPLACE INTO or similar for upsert
        $sql = "INSERT INTO resume_analysis_cache (user_id, resume_hash, analysis_json) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE analysis_json = VALUES(analysis_json), updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->db->prepare($sql);
        $res = $stmt->execute([$userId, $hash, $json]);
        if (!$res) {
            error_log("Failed to cache analysis for user $userId. Hash: $hash");
        }
        return $res;
    }
}
