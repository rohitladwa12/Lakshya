<?php
/**
 * Career Handler
 * Backend API for career advisor operations
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

header('Content-Type: application/json');

$userId = getUserId();

// Load required classes
require_once __DIR__ . '/../../src/Models/CareerRoadmap.php';
require_once __DIR__ . '/../../src/Models/CareerResource.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
require_once __DIR__ . '/../../src/Services/CareerAdvisorAI.php';
require_once __DIR__ . '/../../src/Services/YouTubeService.php';
require_once __DIR__ . '/../../src/Services/StudyMaterialService.php';

$roadmapModel = new CareerRoadmap();
$resourceModel = new CareerResource();
$studentModel = new StudentProfile();

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'generate_roadmap':
            $goalData = $input['goalData'];
            require_once ROOT_PATH . '/src/Services/QueueService.php';
            
            $studentProfile = $studentModel->getProfile($userId);
            $context = [
                'name' => $studentProfile['name'] ?? 'Student',
                'degree' => $studentProfile['degree'] ?? 'Engineering',
                'cgpa' => $studentProfile['cgpa'] ?? null,
                'institution' => $studentProfile['institution'] ?? INSTITUTION_GMU,
                'usn' => $studentProfile['usn'] ?? null,
                'student_id' => $studentProfile['student_id'] ?? null,
                'id' => $studentProfile['id'] ?? null,
                'user_id' => $userId
            ];
            
            $jobId = \App\Services\QueueService::pushJob('generateRoadmap', [$goalData, $context], $userId);
            
            ob_clean(); echo json_encode([
                'success' => true, 
                'job_id' => $jobId,
                'message' => 'Designing your career path...'
            ]);
            exit;

        case 'get_active_roadmap':
            $studentProfile = $studentModel->getProfile($userId);
            $institution = $studentProfile['institution'] ?? INSTITUTION_GMU;
            $roadmapStudentId = getStudentIdForRoadmap($studentProfile, $institution, $userId);

            $activeRoadmap = $roadmapModel->getActiveRoadmap($roadmapStudentId);
            if (!$activeRoadmap) {
                echo json_encode([
                    'success' => true,
                    'has_active_roadmap' => false,
                    'roadmap' => null,
                    'stats' => null
                ]);
                break;
            }

            $stats = $roadmapModel->getRoadmapStats($activeRoadmap['id']);
            echo json_encode([
                'success' => true,
                'has_active_roadmap' => true,
                'roadmap' => $activeRoadmap,
                'stats' => $stats
            ]);
            break;

        case 'get_roadmap':
            $roadmapId = $input['roadmap_id'] ?? null;
            if (!$roadmapId) {
                throw new Exception('roadmap_id is required');
            }
            
            $studentProfile = $studentModel->getProfile($userId);
            $institution = $studentProfile['institution'] ?? INSTITUTION_GMU;
            $roadmapStudentId = getStudentIdForRoadmap($studentProfile, $institution, $userId);

            $roadmap = $roadmapModel->getRoadmapById($roadmapId, $roadmapStudentId);
            if (!$roadmap) {
                throw new Exception('Roadmap not found');
            }
            $stats = $roadmapModel->getRoadmapStats($roadmapId);
            echo json_encode([
                'success' => true,
                'roadmap' => $roadmap,
                'stats' => $stats
            ]);
            break;

        case 'get_phase_resources':
            $roadmapId = $input['roadmap_id'] ?? null;
            $phaseNumber = $input['phase_number'] ?? null;
            
            if (!$roadmapId || !$phaseNumber) {
                throw new Exception('roadmap_id and phase_number are required');
            }

            $studentProfile = $studentModel->getProfile($userId);
            $institution = $studentProfile['institution'] ?? INSTITUTION_GMU;
            $roadmapStudentId = getStudentIdForRoadmap($studentProfile, $institution, $userId);
            
            $videos = $resourceModel->getVideosByRoadmap($roadmapId, $phaseNumber);
            $materials = $resourceModel->getStudyMaterialsByRoadmap($roadmapId, $phaseNumber);
            
            // On-demand fetch if no resources exist for this phase
            if (empty($videos) && empty($materials)) {
                $roadmap = $roadmapModel->getRoadmapById($roadmapId, $roadmapStudentId);
                if ($roadmap && isset($roadmap['roadmap_data']['phases'])) {
                    $youtubeService = new YouTubeService();
                    $studyService = new StudyMaterialService();
                    
                    // Find skills for THIS phase
                    $targetPhase = null;
                    foreach ($roadmap['roadmap_data']['phases'] as $phase) {
                        if ($phase['phase_number'] == $phaseNumber) {
                            $targetPhase = $phase;
                            break;
                        }
                    }
                    
                    if ($targetPhase && !empty($targetPhase['skills'])) {
                        foreach ($targetPhase['skills'] as $skill) {
                            // Fetch real YouTube videos
                            try {
                                $newVideos = $youtubeService->searchForSkill($skill, 'beginner');
                                if (!empty($newVideos)) {
                                    $topVideos = array_slice($newVideos, 0, 2);
                                    foreach ($topVideos as $v) {
                                        $v['related_skills'] = [$skill];
                                        $v['phase_number'] = $phaseNumber;
                                        $resourceModel->addVideo($roadmapId, $v);
                                    }
                                }
                            } catch (Exception $e) {
                                error_log("On-demand video error for $skill: " . $e->getMessage());
                            }
                            
                            // Fetch study materials
                            try {
                                $newMaterials = $studyService->searchMaterials($skill);
                                if (!empty($newMaterials)) {
                                    $topMaterials = array_slice($newMaterials, 0, 2);
                                    foreach ($topMaterials as $m) {
                                        $m['related_skills'] = [$skill];
                                        $m['phase_number'] = $phaseNumber;
                                        $m['difficulty_level'] = 'Beginner';
                                        $resourceModel->addStudyMaterial($roadmapId, $m);
                                    }
                                }
                            } catch (Exception $e) {
                                error_log("On-demand material error for $skill: " . $e->getMessage());
                            }
                        }
                        
                        // Refresh after fetching
                        $videos = $resourceModel->getVideosByRoadmap($roadmapId, $phaseNumber);
                        $materials = $resourceModel->getStudyMaterialsByRoadmap($roadmapId, $phaseNumber);
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'videos' => $videos,
                'materials' => $materials
            ]);
            break;
            
        case 'toggle_video_bookmark':
            $videoId = $input['video_id'];
            $roadmapId = $input['roadmap_id'];
            
            $success = $resourceModel->toggleVideoBookmark($videoId, $roadmapId);
            
            echo json_encode([
                'success' => $success
            ]);
            break;
            
        case 'mark_video_completed':
            $videoId = $input['video_id'];
            $roadmapId = $input['roadmap_id'];
            
            $success = $resourceModel->markVideoCompleted($videoId, $roadmapId);
            
            echo json_encode([
                'success' => $success
            ]);
            break;
            
        case 'toggle_material_bookmark':
            $materialId = $input['material_id'];
            $roadmapId = $input['roadmap_id'];
            
            $success = $resourceModel->toggleMaterialBookmark($materialId, $roadmapId);
            
            echo json_encode([
                'success' => $success
            ]);
            break;
            
        case 'mark_material_downloaded':
            $materialId = $input['material_id'];
            $roadmapId = $input['roadmap_id'];
            
            $success = $resourceModel->markMaterialDownloaded($materialId, $roadmapId);
            
            echo json_encode([
                'success' => $success
            ]);
            break;
            
        case 'add_notes':
            $resourceType = $input['resource_type']; // 'video' or 'material'
            $resourceId = $input['resource_id'];
            $roadmapId = $input['roadmap_id'];
            $notes = $input['notes'];
            
            if ($resourceType === 'video') {
                $success = $resourceModel->addVideoNotes($resourceId, $roadmapId, $notes);
            } else {
                $success = $resourceModel->addMaterialNotes($resourceId, $roadmapId, $notes);
            }
            
            echo json_encode([
                'success' => $success
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Fetch resources for roadmap (runs in background)
 */
function fetchResourcesForRoadmap($roadmapId, $roadmapData) {
    try {
        $resourceModel = new CareerResource();
        $youtubeService = new YouTubeService();
        $studyService = new StudyMaterialService();
        
        // Get all required skills
        $skills = $roadmapData['required_skills'] ?? [];
        
        // Limit to top 10 skills to avoid API quota issues
        $topSkills = array_slice($skills, 0, 10);
        
        foreach ($topSkills as $skillData) {
            $skill = $skillData['skill_name'];
            $difficulty = strtolower($skillData['target_level'] ?? 'beginner');
            
            // Find phase number for this skill
            $phaseNumber = findPhaseForSkill($skill, $roadmapData['phases'] ?? []);
            
            // Fetch YouTube videos (limit 5 per skill)
            try {
                $videos = $youtubeService->searchForSkill($skill, $difficulty);
                $topVideos = array_slice($videos, 0, 5);
                
                foreach ($topVideos as $video) {
                    $video['related_skills'] = [$skill];
                    $video['phase_number'] = $phaseNumber;
                    $resourceModel->addVideo($roadmapId, $video);
                }
            } catch (Exception $e) {
                error_log("Error fetching videos for $skill: " . $e->getMessage());
            }
            
            // Fetch study materials (limit 3 per skill)
            try {
                $materials = $studyService->searchMaterials($skill);
                $topMaterials = array_slice($materials, 0, 3);
                
                foreach ($topMaterials as $material) {
                    $material['related_skills'] = [$skill];
                    $material['phase_number'] = $phaseNumber;
                    $material['difficulty_level'] = ucfirst($difficulty);
                    $resourceModel->addStudyMaterial($roadmapId, $material);
                }
            } catch (Exception $e) {
                error_log("Error fetching materials for $skill: " . $e->getMessage());
            }
            
            // Small delay to respect API rate limits
            usleep(500000); // 0.5 seconds
        }
    } catch (Exception $e) {
        error_log("Error in fetchResourcesForRoadmap: " . $e->getMessage());
    }
}

/**
 * Get the correct student ID for roadmap storage based on institution
 * For GMIT: prioritize enquiry_no > usn > student_id (excluding 0)
 * For GMU: use userId (SL_NO)
 */
function getStudentIdForRoadmap($studentProfile, $institution, $userId) {
    if ($institution === INSTITUTION_GMIT) {
        // Try to get a valid identifier in order of preference
        
        // 1. Try enquiry_no (stored as 'id' in profile)
        if (!empty($studentProfile['id']) && $studentProfile['id'] != 0) {
            return $studentProfile['id'];
        }
        // 2. Try USN
        if (!empty($studentProfile['usn'])) {
            return $studentProfile['usn'];
        }
        // 3. Try student_id (but only if it's not 0)
        if (!empty($studentProfile['student_id']) && $studentProfile['student_id'] != '0' && $studentProfile['student_id'] != 0) {
            return $studentProfile['student_id'];
        }
        // 4. Fallback to userId
        return $userId;
    } else {
        // GMU: Use SL_NO (userId)
        return $userId;
    }
}

/**
 * Find which phase a skill belongs to
 */
function findPhaseForSkill($skill, $phases) {
    foreach ($phases as $phase) {
        if (isset($phase['skills']) && in_array($skill, $phase['skills'])) {
            return $phase['phase_number'];
        }
    }
    return 1; // Default to phase 1
}
