<?php
/**
 * Portfolio Model
 * Handles CRUD operations for student projects, skills, and certifications
 */

class Portfolio extends Model {
    protected $table = 'student_portfolio';

    public function __construct() {
        parent::__construct();
    }

    /**
     * Get all portfolio items for a student
     * @param string $studentId USN/Username
     * @param string $institution GMU/GMIT
     * @return array
     */
    public function getStudentPortfolio($studentId, $institution) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE student_id = ? AND institution = ? 
                ORDER BY category, created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $institution]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get only skills for a student
     */
    public function getStudentSkills($studentId, $institution) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE student_id = ? AND institution = ? AND category = 'Skill' 
                ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $institution]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a new item to the portfolio
     * @param array $data
     * @return int|false
     */
    public function addItem($data) {
        $sql = "INSERT INTO {$this->table} 
                (student_id, institution, category, title, description, link, attachment_path, attachment_path_2, certificate_attachments, sub_title, start_date, end_date, date_completed) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $data['student_id'],
            $data['institution'],
            $data['category'],
            $data['title'],
            $data['description'] ?? null,
            $data['link'] ?? null,
            $data['attachment_path'] ?? null,
            $data['attachment_path_2'] ?? null,
            $data['certificate_attachments'] ?? null,
            $data['sub_title'] ?? null,
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['date_completed'] ?? null
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Delete an item from the portfolio
     * @param int $id
     * @param string $studentId Ownership check
     * @return bool
     */
    public function deleteItem($id, $studentId) {
        $sql = "DELETE FROM {$this->table} WHERE id = ? AND student_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id, $studentId]);
    }

    /**
     * Update an existing portfolio item
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateItem($id, $data) {
        $sql = "UPDATE {$this->table} SET 
                title = ?, 
                description = ?, 
                link = ?, 
                attachment_path = ?,
                attachment_path_2 = ?,
                certificate_attachments = ?,
                sub_title = ?, 
                start_date = ?,
                end_date = ?,
                date_completed = ?
                WHERE id = ? AND student_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['link'] ?? null,
            $data['attachment_path'] ?? null,
            $data['attachment_path_2'] ?? null,
            $data['certificate_attachments'] ?? null,
            $data['sub_title'] ?? null,
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['date_completed'] ?? null,
            $id,
            $data['student_id'],
        ]);
    }

    /**
     * Synchronize skills from Resume Builder (grouped) to Portfolio (flat)
     * @param string $studentId
     * @param string $institution
     * @param array $skillGroups Array of {category: string, items: string[]}
     * @return bool
     */
    public function syncSkills($studentId, $institution, $skillGroups) {
        $db = $this->db;
        try {
            $db->beginTransaction();

            // 1. Fetch existing verification data for skills to preserve it
            $sqlFetch = "SELECT title, is_verified, verification_score, verification_date, verification_details 
                        FROM {$this->table} 
                        WHERE student_id = ? AND institution = ? AND category = 'Skill' AND is_verified = 1";
            $stmtFetch = $db->prepare($sqlFetch);
            $stmtFetch->execute([$studentId, $institution]);
            $existingVerifications = $stmtFetch->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

            // 2. Delete existing skills for this student
            $sqlDelete = "DELETE FROM {$this->table} WHERE student_id = ? AND institution = ? AND category = 'Skill'";
            $stmtDel = $db->prepare($sqlDelete);
            $stmtDel->execute([$studentId, $institution]);

            // 3. Insert new skills from groups
            $sqlInsert = "INSERT INTO {$this->table} 
                         (student_id, institution, category, title, sub_title, is_verified, verification_score, verification_date, verification_details) 
                         VALUES (?, ?, 'Skill', ?, ?, ?, ?, ?, ?)";
            $stmtIns = $db->prepare($sqlInsert);

            foreach ($skillGroups as $group) {
                $categoryName = !empty($group['category']) ? $group['category'] : 'Technical Skills';
                foreach ($group['items'] as $skillName) {
                    $trimmedSkill = trim($skillName);
                    if (empty($trimmedSkill)) continue;

                    // Restore verification if it existed
                    $isVerified = 0;
                    $score = null;
                    $date = null;
                    $details = null;

                    if (isset($existingVerifications[$trimmedSkill])) {
                        $oldData = $existingVerifications[$trimmedSkill][0];
                        $isVerified = 1;
                        $score = $oldData['verification_score'];
                        $date = $oldData['verification_date'];
                        $details = $oldData['verification_details'];
                    }

                    $stmtIns->execute([
                        $studentId, 
                        $institution, 
                        $trimmedSkill, 
                        $categoryName,
                        $isVerified,
                        $score,
                        $date,
                        $details
                    ]);
                }
            }

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Synchronize projects from Resume Builder to Portfolio
     * @param string $studentId
     * @param string $institution
     * @param array $projects Array of project objects
     * @return bool
     */
    public function syncProjects($studentId, $institution, $projects) {
        $db = $this->db;
        try {
            $db->beginTransaction();

            // 1. Fetch existing verification data for projects to preserve it
            $sqlFetch = "SELECT title, is_verified, verification_score, verification_date, verification_details 
                        FROM {$this->table} 
                        WHERE student_id = ? AND institution = ? AND category = 'Project' AND is_verified = 1";
            $stmtFetch = $db->prepare($sqlFetch);
            $stmtFetch->execute([$studentId, $institution]);
            $existingVerifications = $stmtFetch->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

            // 2. Delete existing projects for this student
            $sqlDelete = "DELETE FROM {$this->table} WHERE student_id = ? AND institution = ? AND category = 'Project'";
            $stmtDel = $db->prepare($sqlDelete);
            $stmtDel->execute([$studentId, $institution]);

            // 3. Insert new projects
            $sqlInsert = "INSERT INTO {$this->table} 
                         (student_id, institution, category, title, sub_title, description, link, start_date, end_date, is_verified, verification_score, verification_date, verification_details) 
                         VALUES (?, ?, 'Project', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtIns = $db->prepare($sqlInsert);

            foreach ($projects as $proj) {
                $title = trim($proj['title'] ?? '');
                if (empty($title)) continue;

                // Technologies as subtitle
                $tech = $proj['technologies'] ?? '';
                if (is_array($tech)) $tech = implode(', ', $tech);
                $subtitle = trim($tech);

                // Combine description and duration
                $desc = $proj['description'] ?? '';
                $duration = $proj['duration'] ?? '';
                if ($duration) {
                    $desc = "Duration: $duration\n\n" . $desc;
                }

                // Restore verification if it existed for this specific project title
                $isVerified = 0;
                $score = null;
                $date = null;
                $details = null;

                if (isset($existingVerifications[$title])) {
                    $oldData = $existingVerifications[$title][0];
                    $isVerified = 1;
                    $score = $oldData['verification_score'];
                    $date = $oldData['verification_date'];
                    $details = $oldData['verification_details'];
                }

                // Normalize dates
                $startDate = !empty($proj['start_date']) ? $proj['start_date'] : null;
                $endDate = !empty($proj['end_date']) ? $proj['end_date'] : null;
                if ($startDate && strlen($startDate) === 7) $startDate .= '-01';
                if ($endDate && strlen($endDate) === 7) $endDate .= '-01';

                $stmtIns->execute([
                    $studentId,
                    $institution,
                    $title,
                    $subtitle,
                    $desc,
                    $proj['link'] ?? '',
                    $startDate,
                    $endDate,
                    $isVerified,
                    $score,
                    $date,
                    $details
                ]);
            }

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Synchronize certifications from Resume Builder to Portfolio
     * @param string $studentId
     * @param string $institution
     * @param array $certifications Array of certification objects
     * @return bool
     */
    public function syncCertifications($studentId, $institution, $certifications) {
        $db = $this->db;
        try {
            $db->beginTransaction();

            // 1. Fetch existing verification data for certifications to preserve it
            $sqlFetch = "SELECT title, is_verified, verification_score, verification_date, verification_details 
                        FROM {$this->table} 
                        WHERE student_id = ? AND institution = ? AND category = 'Certification' AND is_verified = 1";
            $stmtFetch = $db->prepare($sqlFetch);
            $stmtFetch->execute([$studentId, $institution]);
            $existingVerifications = $stmtFetch->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

            // 2. Delete existing certifications for this student
            $sqlDelete = "DELETE FROM {$this->table} WHERE student_id = ? AND institution = ? AND category = 'Certification'";
            $stmtDel = $db->prepare($sqlDelete);
            $stmtDel->execute([$studentId, $institution]);

            // 3. Insert new certifications
            $sqlInsert = "INSERT INTO {$this->table} 
                         (student_id, institution, category, title, sub_title, description, link, date_completed, is_verified, verification_score, verification_date, verification_details) 
                         VALUES (?, ?, 'Certification', ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtIns = $db->prepare($sqlInsert);

            foreach ($certifications as $cert) {
                $title = trim($cert['name'] ?? '');
                if (empty($title)) continue;

                $issuer = trim($cert['issuer'] ?? '');
                $credentialUrl = trim($cert['credential_url'] ?? '');
                $dateCompleted = trim($cert['date'] ?? '');
                
                // Store date in standard format if present
                if (!empty($dateCompleted) && strlen($dateCompleted) === 7) {
                    $dateCompleted .= '-01'; // Make it YYYY-MM-DD
                } else if (empty($dateCompleted)) {
                    $dateCompleted = null;
                }

                // Restore verification if it existed for this specific certification title
                $isVerified = 0;
                $score = null;
                $date = null;
                $details = null;

                if (isset($existingVerifications[$title])) {
                    $oldData = $existingVerifications[$title][0];
                    $isVerified = 1;
                    $score = $oldData['verification_score'];
                    $date = $oldData['verification_date'];
                    $details = $oldData['verification_details'];
                }

                $stmtIns->execute([
                    $studentId, 
                    $institution, 
                    $title, 
                    $issuer, 
                    $cert['description'] ?? null,
                    $credentialUrl ?: null,
                    $dateCompleted,
                    $isVerified,
                    $score,
                    $date,
                    $details
                ]);
            }

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }
}
