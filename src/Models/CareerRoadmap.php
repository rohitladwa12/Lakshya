<?php
/**
 * Career Roadmap Model
 * Manages student career roadmaps and progress
 */

class CareerRoadmap {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function createRoadmap($studentId, $goalData, $roadmapData) {
        $sql = "INSERT INTO career_roadmaps (
            student_id, target_role, target_company_type, target_industry,
            experience_level, current_skills, academic_background, cgpa, roadmap_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $studentId,
            $goalData['target_role'],
            $goalData['target_company_type'],
            $goalData['target_industry'],
            $goalData['experience_level'],
            json_encode($goalData['current_skills']),
            json_encode($goalData['academic_background']),
            $goalData['cgpa'],
            json_encode($roadmapData)
        ]);
        
        if ($result) {
            $roadmapId = $this->db->lastInsertId();
            
            // Initialize skill_progress for each required skill
            if (isset($roadmapData['required_skills']) && is_array($roadmapData['required_skills'])) {
                $skillSql = "INSERT INTO skill_progress (
                    roadmap_id, student_id, skill_name, skill_category, 
                    current_level, target_level
                ) VALUES (?, ?, ?, ?, ?, ?)";
                
                $skillStmt = $this->db->prepare($skillSql);
                foreach ($roadmapData['required_skills'] as $skill) {
                    $skillStmt->execute([
                        $roadmapId,
                        $studentId,
                        $skill['skill_name'],
                        $skill['category'] ?? 'Technical',
                        $skill['current_level'] ?? 'None',
                        $skill['target_level'] ?? 'Intermediate'
                    ]);
                }
            }
            
            return $roadmapId;
        }
        
        return false;
    }
    
    /**
     * Get active roadmap for student
     */
    public function getActiveRoadmap($studentId) {
        $sql = "SELECT * FROM career_roadmaps 
                WHERE student_id = ? AND status = 'active' 
                ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        
        $roadmap = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($roadmap) {
            // Decode JSON fields
            $roadmap['current_skills'] = json_decode($roadmap['current_skills'], true);
            $roadmap['academic_background'] = json_decode($roadmap['academic_background'], true);
            $roadmap['roadmap_data'] = json_decode($roadmap['roadmap_data'], true);
            return $roadmap;
        }
        
        return null;
    }
    
    /**
     * Get roadmap by ID
     */
    public function getRoadmapById($roadmapId, $studentId = null) {
        $sql = "SELECT * FROM career_roadmaps WHERE id = ?";
        $params = [$roadmapId];
        
        if ($studentId) {
            $sql .= " AND student_id = ?";
            $params[] = $studentId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $roadmap = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($roadmap) {
            $roadmap['current_skills'] = json_decode($roadmap['current_skills'], true);
            $roadmap['academic_background'] = json_decode($roadmap['academic_background'], true);
            $roadmap['roadmap_data'] = json_decode($roadmap['roadmap_data'], true);
            return $roadmap;
        }
        
        return null;
    }
    
    /**
     * Get all roadmaps for student
     */
    public function getAllRoadmaps($studentId) {
        $sql = "SELECT id, target_role, target_company_type, status, 
                progress_percentage, created_at, updated_at 
                FROM career_roadmaps 
                WHERE student_id = ? 
                ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update roadmap progress
     */
    public function updateProgress($roadmapId, $progressPercentage) {
        $sql = "UPDATE career_roadmaps 
                SET progress_percentage = ?, 
                    status = CASE 
                        WHEN ? >= 100 THEN 'completed' 
                        ELSE status 
                    END
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$progressPercentage, $progressPercentage, $roadmapId]);
    }
    
    /**
     * Archive a roadmap
     */
    public function archiveRoadmap($roadmapId, $studentId) {
        $sql = "UPDATE career_roadmaps 
                SET status = 'archived' 
                WHERE id = ? AND student_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$roadmapId, $studentId]);
    }
    
    /**
     * Get roadmap statistics
     */
    public function getRoadmapStats($roadmapId) {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM career_resources WHERE roadmap_id = ? AND is_completed = 1) as videos_completed,
                    (SELECT COUNT(*) FROM career_resources WHERE roadmap_id = ?) as total_videos,
                    (SELECT COUNT(*) FROM study_materials WHERE roadmap_id = ? AND is_downloaded = 1) as materials_downloaded,
                    (SELECT COUNT(*) FROM study_materials WHERE roadmap_id = ?) as total_materials,
                    (SELECT AVG(progress_percentage) FROM skill_progress WHERE roadmap_id = ?) as avg_skill_progress";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roadmapId, $roadmapId, $roadmapId, $roadmapId, $roadmapId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if student has active roadmap
     */
    public function hasActiveRoadmap($studentId) {
        $sql = "SELECT COUNT(*) as count FROM career_roadmaps 
                WHERE student_id = ? AND status = 'active'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['count'] > 0;
    }

    /**
     * Update overall roadmap progress percentage
     */
    public function updateRoadmapProgress($roadmapId) {
        // Average the progress of all skills in this roadmap
        $sql = "UPDATE career_roadmaps cr
                SET progress_percentage = (
                    SELECT COALESCE(AVG(progress_percentage), 0)
                    FROM skill_progress
                    WHERE roadmap_id = ?
                )
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$roadmapId, $roadmapId]);
    }

    /**
     * Update progress for a specific skill based on completed resources
     */
    public function updateSkillProgressByResources($roadmapId, $skillName) {
        $quotedSkill = '"' . $skillName . '"';
        
        // Count total and completed videos for this skill
        $videoSql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
                     FROM career_resources 
                     WHERE roadmap_id = ? AND JSON_CONTAINS(related_skills, ?)";
        
        $stmt = $this->db->prepare($videoSql);
        $stmt->execute([$roadmapId, $quotedSkill]);
        $videoStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Count total and downloaded materials for this skill
        $materialSql = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_downloaded = 1 THEN 1 ELSE 0 END) as completed
                        FROM study_materials 
                        WHERE roadmap_id = ? AND JSON_CONTAINS(related_skills, ?)";
        
        $stmt = $this->db->prepare($materialSql);
        $stmt->execute([$roadmapId, $quotedSkill]);
        $materialStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalResources = ($videoStats['total'] ?? 0) + ($materialStats['total'] ?? 0);
        $completedResources = ($videoStats['completed'] ?? 0) + ($materialStats['completed'] ?? 0);
        
        $progressPercentage = 0;
        if ($totalResources > 0) {
            $progressPercentage = round(($completedResources / $totalResources) * 100);
        }
        
        // Update skill_progress table
        $updateSql = "UPDATE skill_progress 
                      SET progress_percentage = ?, 
                          resources_completed = ?, 
                          total_resources = ?,
                          last_activity_date = NOW()
                      WHERE roadmap_id = ? AND skill_name = ?";
        
        $updateStmt = $this->db->prepare($updateSql);
        $result = $updateStmt->execute([
            $progressPercentage,
            $completedResources,
            $totalResources,
            $roadmapId,
            $skillName
        ]);
        
        if ($result) {
            // After updating a skill, update the overall roadmap progress
            $this->updateRoadmapProgress($roadmapId);
        }
        
        return $result;
    }
}
