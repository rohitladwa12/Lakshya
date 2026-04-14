<?php
/**
 * Career Resource Model
 * Manages YouTube videos and study materials for career roadmaps
 */

class CareerResource {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Add YouTube video resource
     */
    public function addVideo($roadmapId, $videoData) {
        $sql = "INSERT INTO career_resources (
            roadmap_id, video_id, title, description, channel_name, channel_id,
            thumbnail_url, duration, view_count, published_at, related_skills,
            phase_number, relevance_score
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $roadmapId,
            $videoData['video_id'],
            $videoData['title'],
            $videoData['description'],
            $videoData['channel_name'],
            $videoData['channel_id'],
            $videoData['thumbnail_url'],
            $videoData['duration'],
            $videoData['view_count'],
            $videoData['published_at'],
            json_encode($videoData['related_skills']),
            $videoData['phase_number'],
            $videoData['relevance_score']
        ]);
    }
    
    /**
     * Add study material
     */
    public function addStudyMaterial($roadmapId, $materialData) {
        $sql = "INSERT INTO study_materials (
            roadmap_id, title, description, source_url, file_type,
            source_website, author, page_count, file_size, related_skills,
            phase_number, difficulty_level, relevance_score
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $roadmapId,
            $materialData['title'],
            $materialData['description'],
            $materialData['source_url'],
            $materialData['file_type'],
            $materialData['source_website'],
            $materialData['author'] ?? null,
            $materialData['page_count'] ?? null,
            $materialData['file_size'] ?? null,
            json_encode($materialData['related_skills']),
            $materialData['phase_number'],
            $materialData['difficulty_level'] ?? 'Beginner',
            $materialData['relevance_score']
        ]);
    }
    
    /**
     * Get all videos for roadmap
     */
    public function getVideosByRoadmap($roadmapId, $phaseNumber = null) {
        $sql = "SELECT * FROM career_resources WHERE roadmap_id = ?";
        $params = [$roadmapId];
        
        if ($phaseNumber !== null) {
            $sql .= " AND phase_number = ?";
            $params[] = $phaseNumber;
        }
        $sql .= " ORDER BY relevance_score DESC, view_count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($videos as &$video) {
            $video['related_skills'] = json_decode($video['related_skills'], true);
        }
        
        return $videos;
    }
    
    /**
     * Get all study materials for roadmap
     */
    public function getStudyMaterialsByRoadmap($roadmapId, $phaseNumber = null, $fileType = null) {
        $sql = "SELECT * FROM study_materials WHERE roadmap_id = ?";
        $params = [$roadmapId];
        
        if ($phaseNumber !== null) {
            $sql .= " AND phase_number = ?";
            $params[] = $phaseNumber;
        }
        
        if ($fileType) {
            $sql .= " AND file_type = ?";
            $params[] = $fileType;
        }
        
        $sql .= " ORDER BY relevance_score DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($materials as &$material) {
            $material['related_skills'] = json_decode($material['related_skills'], true);
        }
        
        return $materials;
    }
    
    /**
     * Toggle bookmark for video
     */
    public function toggleVideoBookmark($videoId, $roadmapId) {
        $sql = "UPDATE career_resources 
                SET is_bookmarked = NOT is_bookmarked 
                WHERE id = ? AND roadmap_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$videoId, $roadmapId]);
    }
    
    /**
     * Mark video as completed
     */
    public function markVideoCompleted($videoId, $roadmapId) {
        $sql = "UPDATE career_resources 
                SET is_completed = TRUE, completion_date = NOW() 
                WHERE id = ? AND roadmap_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$videoId, $roadmapId]);
        
        if ($result) {
            // Get related skills for this video to update their progress
            $skillSql = "SELECT related_skills FROM career_resources WHERE id = ?";
            $skillStmt = $this->db->prepare($skillSql);
            $skillStmt->execute([$videoId]);
            $row = $skillStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row && $row['related_skills']) {
                $skills = json_decode($row['related_skills'], true);
                if (is_array($skills)) {
                    $roadmapModel = new CareerRoadmap();
                    foreach ($skills as $skillName) {
                        $roadmapModel->updateSkillProgressByResources($roadmapId, $skillName);
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Toggle bookmark for study material
     */
    public function toggleMaterialBookmark($materialId, $roadmapId) {
        $sql = "UPDATE study_materials 
                SET is_bookmarked = NOT is_bookmarked 
                WHERE id = ? AND roadmap_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$materialId, $roadmapId]);
    }
    
    /**
     * Mark material as downloaded
     */
    public function markMaterialDownloaded($materialId, $roadmapId) {
        $sql = "UPDATE study_materials 
                SET is_downloaded = TRUE, 
                    download_count = download_count + 1,
                    last_accessed = NOW()
                WHERE id = ? AND roadmap_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$materialId, $roadmapId]);
        
        if ($result) {
            // Get related skills for this material to update their progress
            $skillSql = "SELECT related_skills FROM study_materials WHERE id = ?";
            $skillStmt = $this->db->prepare($skillSql);
            $skillStmt->execute([$materialId]);
            $row = $skillStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row && $row['related_skills']) {
                $skills = json_decode($row['related_skills'], true);
                if (is_array($skills)) {
                    $roadmapModel = new CareerRoadmap();
                    foreach ($skills as $skillName) {
                        $roadmapModel->updateSkillProgressByResources($roadmapId, $skillName);
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Add notes to video
     */
    public function addVideoNotes($videoId, $roadmapId, $notes) {
        $sql = "UPDATE career_resources 
                SET notes = ? 
                WHERE id = ? AND roadmap_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$notes, $videoId, $roadmapId]);
    }
    
    /**
     * Add notes to study material
     */
    public function addMaterialNotes($materialId, $roadmapId, $notes) {
        $sql = "UPDATE study_materials 
                SET notes = ? 
                WHERE id = ? AND roadmap_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$notes, $materialId, $roadmapId]);
    }
    
    /**
     * Get bookmarked videos
     */
    public function getBookmarkedVideos($roadmapId) {
        $sql = "SELECT * FROM career_resources 
                WHERE roadmap_id = ? AND is_bookmarked = TRUE 
                ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roadmapId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get bookmarked study materials
     */
    public function getBookmarkedMaterials($roadmapId) {
        $sql = "SELECT * FROM study_materials 
                WHERE roadmap_id = ? AND is_bookmarked = TRUE 
                ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roadmapId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
