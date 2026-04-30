<?php
/**
 * Career Advisor AI Service
 * Generates personalized career roadmaps using OpenAI
 */

class CareerAdvisorAI {
    private $apiKey;
    private $model = 'gpt-4o-mini'; // Using GPT-4o-mini for cost efficiency
    
    public function __construct() {
        $this->apiKey = getenv('OPENAI_API_KEY');
        
        if (!$this->apiKey) {
            throw new Exception('OpenAI API key not configured');
        }
    }
    
    public function generateRoadmap($goalData, $studentContext) {
        $prompt = $this->buildPrompt($goalData, $studentContext);
        
        try {
            $response = $this->callOpenAI($prompt);
            $roadmapData = $this->parseResponse($response);
            
            // Persist to database
            require_once __DIR__ . '/../Models/CareerRoadmap.php';
            $roadmapModel = new CareerRoadmap();
            
            // Determine student ID (consistent with career_handler.php)
            $userId = $studentContext['user_id'] ?? null;
            $studentId = $this->getStudentId($studentContext, $userId);
            
            $roadmapId = $roadmapModel->createRoadmap($studentId, $goalData, $roadmapData);
            
            if (!$roadmapId) {
                throw new Exception("Failed to save roadmap to database");
            }
            
            return [
                'success' => true,
                'roadmap_id' => $roadmapId,
                'roadmap' => $roadmapData
            ];
        } catch (Exception $e) {
            // Fallback: If OpenAI fails (e.g. invalid key), verify if we can return a mock roadmap
            // for demonstration purposes, especially if unrelated to user input validation
            if (strpos($e->getMessage(), '401') !== false || strpos(strtolower($e->getMessage()), 'api key') !== false) {
                return [
                    'success' => true,
                    'roadmap' => $this->getMockRoadmap($goalData, $studentContext),
                    'warning' => 'Generated using offline mode due to AI service unavailability.'
                ];
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Build comprehensive prompt for OpenAI
     */
    private function buildPrompt($goalData, $context) {
        $targetRole = $goalData['target_role'];
        $companyType = $goalData['target_company_type'] ?? 'any company';
        $industry = $goalData['target_industry'] ?? 'Technology';
        $experienceLevel = $goalData['experience_level'] ?? 'Entry';
        $currentSkills = !empty($goalData['current_skills']) ? implode(', ', $goalData['current_skills']) : 'None';
        
        $prompt = "You are an expert career advisor. Create a detailed, personalized career roadmap for a student.

**Student Profile:**
- Name: {$context['name']}
- Current Education: {$context['degree']} at {$context['institution']}
- CGPA: " . ($context['cgpa'] ?? 'Not provided') . "
- Current Skills: {$currentSkills}


**Career Goal:**
- Target Role: {$targetRole}
- Target Company Type: {$companyType}
- Industry: {$industry}
- Experience Level: {$experienceLevel}

**Instructions:**
Generate a comprehensive career roadmap in JSON format with the following structure:

{
  \"overview\": \"A brief 2-3 sentence overview of the career path\",
  \"timeline\": \"Estimated time to achieve this goal (e.g., '6-12 months')\",
  \"phases\": [
    {
      \"phase_number\": 1,
      \"title\": \"Phase title\",
      \"duration\": \"Duration (e.g., '2-3 months')\",
      \"description\": \"What this phase focuses on\",
      \"skills\": [\"Skill 1\", \"Skill 2\"],
      \"milestones\": [\"Milestone 1\", \"Milestone 2\"]
    }
  ],
  \"required_skills\": [
    {
      \"skill_name\": \"Skill name\",
      \"category\": \"Technical/Soft/Domain\",
      \"priority\": \"Critical/Important/Nice-to-have\",
      \"current_level\": \"None/Beginner/Intermediate/Advanced\",
      \"target_level\": \"Beginner/Intermediate/Advanced/Expert\",
      \"why_important\": \"Why this skill matters for the role\"
    }
  ]
}

**Requirements:**
1. Create 4-6 learning phases with clear progression
2. List 8-15 required skills with priorities
3. Be specific and actionable
4. Consider the student's current skills
5. Make timeline realistic
6. Focus on practical, industry-relevant skills

Return ONLY valid JSON, no additional text.";

        return $prompt;
    }
    
    /**
     * Call OpenAI API
     */
    private function callOpenAI($prompt) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert career advisor who creates detailed, personalized learning roadmaps. Always respond with valid JSON only.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object']
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("OpenAI API request failed: " . $error);
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("OpenAI API returned error code: " . $httpCode . " - " . $response);
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Invalid response from OpenAI API");
        }
        
        return $result['choices'][0]['message']['content'];
    }
    
    /**
     * Parse OpenAI response
     */
    private function parseResponse($response) {
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse AI response: " . json_last_error_msg());
        }
        
        // Validate required fields
        if (!isset($data['overview']) || !isset($data['phases']) || !isset($data['required_skills'])) {
            throw new Exception("AI response missing required fields");
        }
        
        return $data;
    }
    
    /**
     * Generate search keywords for a skill
     */
    public function generateSearchKeywords($skill, $difficulty = 'beginner') {
        return [
            "{$skill} tutorial for {$difficulty}",
            "{$skill} complete course",
            "learn {$skill}",
            "{$skill} crash course"
        ];
    }
    
    /**
     * Suggest next steps based on progress
     */
    public function suggestNextSteps($roadmapData, $completedSkills) {
        $allSkills = array_column($roadmapData['required_skills'], 'skill_name');
        $remainingSkills = array_diff($allSkills, $completedSkills);
        
        // Get top 3 priority skills that aren't completed
        $suggestions = [];
        foreach ($roadmapData['required_skills'] as $skill) {
            if (in_array($skill['skill_name'], $remainingSkills)) {
                $suggestions[] = $skill;
            }
            if (count($suggestions) >= 3) break;
        }
        
        return $suggestions;
    }

    /**
     * Generate a mock roadmap when AI is unavailable
     */
    private function getMockRoadmap($goalData, $context) {
        $role = $goalData['target_role'] ?? 'Software Developer';
        
        return [
            'overview' => "This is a generated roadmap for {$role}. (Offline Mode: AI Service Unavailable)",
            'timeline' => "6-12 months",
            'phases' => [
                [
                    'phase_number' => 1,
                    'title' => 'Fundamentals',
                    'duration' => '1-2 months',
                    'description' => 'Build a strong foundation in core concepts.',
                    'skills' => ['Basic Programming', 'Algorithms', 'Database Basics'],
                    'milestones' => ['Build a simple console app', 'Solve 50 coding problems']
                ],
                [
                    'phase_number' => 2,
                    'title' => 'Advanced Concepts',
                    'duration' => '2-3 months',
                    'description' => 'Deep dive into advanced topics and frameworks.',
                    'skills' => ['Web Frameworks', 'API Design', 'System Design'],
                    'milestones' => ['Build a Full Stack App', 'Deploy a project']
                ],
                [
                    'phase_number' => 3,
                    'title' => 'Professional Polish',
                    'duration' => '1 month',
                    'description' => 'Prepare for interviews and professional work.',
                    'skills' => ['Soft Skills', 'Interview Prep', 'Resume Building'],
                    'milestones' => ['Mock Interview', 'Portfolio cleanup']
                ]
            ],
            'required_skills' => [
                [
                    'skill_name' => 'Basic Programming',
                    'category' => 'Technical',
                    'priority' => 'Critical',
                    'current_level' => 'Beginner',
                    'target_level' => 'Intermediate',
                    'why_important' => 'Foundation for all development.'
                ],
                [
                    'skill_name' => 'Web Frameworks',
                    'category' => 'Technical',
                    'priority' => 'Important',
                    'current_level' => 'None',
                    'target_level' => 'Intermediate',
                    'why_important' => 'Essential for modern web development.'
                ],
                [
                    'skill_name' => 'Database Basics',
                    'category' => 'Technical',
                    'priority' => 'Critical',
                    'current_level' => 'Beginner',
                    'target_level' => 'Intermediate',
                    'why_important' => 'Data persistence is key.'
                ]
            ]
        ];
    }

    /**
     * Get the correct student ID for storage (ported from career_handler.php)
     */
    private function getStudentId($context, $userId) {
        $institution = $context['institution'] ?? INSTITUTION_GMU;
        
        if ($institution === INSTITUTION_GMIT) {
            if (!empty($context['id']) && $context['id'] != 0) {
                return $context['id'];
            }
            if (!empty($context['usn'])) {
                return $context['usn'];
            }
            if (!empty($context['student_id']) && $context['student_id'] != '0' && $context['student_id'] != 0) {
                return $context['student_id'];
            }
            return $userId;
        } else {
            return $userId;
        }
    }
}
