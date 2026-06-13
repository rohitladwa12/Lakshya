<?php
namespace App\Services;

use PDO;
use Exception;

class StudentIntelligenceService {
    private $db;
    private $ai;

    public function __construct() {
        $this->db = getDB();
        $this->ai = new \AIService();
    }

    /**
     * Sync and update the student's inferred AI Profile
     */
    public function syncStudentAIProfile($studentId, $institution, $studentName = '') {
        try {
            // 1. Fetch student academic history
            $stmt = $this->db->prepare("SELECT * FROM student_profiles WHERE usn = ? LIMIT 1");
            $stmt->execute([$studentId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if (empty($profile)) {
                $studentModel = new \StudentProfile();
                $userModel = new \User();
                $user = $userModel->findByUsername($studentId);
                if ($user) {
                    $profile = $studentModel->getByUserId($user['id'], $institution) ?: [];
                }
            }

            if (empty($profile)) {
                $profile = [
                    'name' => $studentName ?: $studentId,
                    'department' => 'N/A',
                    'course' => 'N/A',
                    'cgpa' => 'N/A'
                ];
            }

            // 2. Fetch portfolio items
            $stmt = $this->db->prepare("SELECT * FROM student_portfolio WHERE student_id = ? AND institution = ?");
            $stmt->execute([$studentId, $institution]);
            $portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (empty($profile) && empty($portfolio)) {
                return ['success' => false, 'message' => 'No student profile or portfolio data found.'];
            }

            // Categorize skills, projects, certifications
            $skills = [];
            $projects = [];
            $certs = [];
            foreach ($portfolio as $item) {
                if ($item['category'] === 'Skill') {
                    $skills[] = $item['title'] . ($item['sub_title'] ? ' (' . $item['sub_title'] . ')' : '');
                } elseif ($item['category'] === 'Project') {
                    $projects[] = $item['title'] . ': ' . $item['description'];
                } elseif ($item['category'] === 'Certification') {
                    $certs[] = $item['title'] . ($item['sub_title'] ? ' by ' . $item['sub_title'] : '');
                }
            }

            // Build payload for AI Analysis
            $studentPayload = [
                'name' => $profile['name'] ?? $studentName,
                'department' => $profile['department'] ?? 'N/A',
                'course' => $profile['course'] ?? 'N/A',
                'cgpa' => $profile['cgpa'] ?? 'N/A',
                'skills' => $skills,
                'projects' => $projects,
                'certifications' => $certs
            ];

            // Call OpenAI via AIService
            $systemPrompt = "You are a Tech Career Intelligence Engine. Analyze this student's academic profile, skills, projects, and certifications.
            You must return a response strictly formatted as a valid JSON object matching the following structure:
            {
                \"predicted_role\": \"Suggested career role (e.g. Frontend Developer, Database Administrator)\",
                \"confidence_score\": 0.85, // Float between 0.00 and 1.00
                \"detected_interests\": [\"Interest 1\", \"Interest 2\"],
                \"personality_pref\": \"Professional\", // Choose from: Professional, Supportive, Brutal
                \"ai_summary\": \"A short 2-3 sentence technical summary of this student's strengths, weaknesses, and potential.\"
            }
            Ensure the output is pure JSON without any surrounding markdown wraps.";

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "STUDENT PROFILE:\n" . json_encode($studentPayload)]
            ];

            $aiResponse = $this->ai->callAPI($messages, [
                'audit_method' => __FUNCTION__,
                'response_format' => ['type' => 'json_object']
            ]);

            if (!$aiResponse['success'] || empty($aiResponse['parsed'])) {
                return ['success' => false, 'message' => 'AI analysis failed.'];
            }

            $aiData = $aiResponse['parsed'];

            // 3. Save / Update in student_ai_profiles
            $stmt = $this->db->prepare("
                INSERT INTO student_ai_profiles 
                (student_name, student_id, institution, predicted_role, confidence_score, detected_interests, personality_pref, ai_summary)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                student_name = VALUES(student_name),
                predicted_role = VALUES(predicted_role),
                confidence_score = VALUES(confidence_score),
                detected_interests = VALUES(detected_interests),
                personality_pref = VALUES(personality_pref),
                ai_summary = VALUES(ai_summary)
            ");

            $stmt->execute([
                $profile['name'] ?? $studentName,
                $studentId,
                $institution,
                $aiData['predicted_role'] ?? 'Software Engineer',
                $aiData['confidence_score'] ?? 0.50,
                json_encode($aiData['detected_interests'] ?? []),
                $aiData['personality_pref'] ?? 'Professional',
                $aiData['ai_summary'] ?? ''
            ]);

            // 4. Initialize high-priority mastery topics based on skills
            $this->initializeTopicMastery($studentId, $institution, $profile['name'] ?? $studentName, $skills, $profile);

            return ['success' => true, 'data' => $aiData];
        } catch (Exception $e) {
            error_log("syncStudentAIProfile Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Retrieve the student's AI Profile or trigger sync if missing
     */
    public function getStudentAIProfile($studentId, $institution, $studentName = '') {
        $stmt = $this->db->prepare("SELECT * FROM student_ai_profiles WHERE student_id = ? AND institution = ? LIMIT 1");
        $stmt->execute([$studentId, $institution]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            $syncRes = $this->syncStudentAIProfile($studentId, $institution, $studentName);
            if ($syncRes['success']) {
                $stmt->execute([$studentId, $institution]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        return $profile;
    }

    /**
     * Initialize Topic Mastery rows if they don't exist
     */
    /**
     * Map student's branch/department to relevant default topics
     */
    public function getDefaultTopicsForBranch($department, $course) {
        $dept = strtoupper(trim($department ?? ''));
        $crs = strtoupper(trim($course ?? ''));

        // 1. MBA, BBA, BCOM (Management/Business)
        if ($dept === 'MBA' || strpos($dept, 'BBA') !== false || strpos($dept, 'BCOM') !== false || $crs === 'MBA' || $crs === 'BBA' || $crs === 'BCOM') {
            return [
                ['name' => 'Business Strategy', 'category' => 'Technical'],
                ['name' => 'Marketing Management', 'category' => 'Technical'],
                ['name' => 'Financial Accounting', 'category' => 'Technical'],
                ['name' => 'Human Resource Management', 'category' => 'Technical'],
                ['name' => 'Operations & Supply Chain', 'category' => 'Technical'],
                ['name' => 'Corporate Finance', 'category' => 'Technical'],
                ['name' => 'Quantitative Aptitude', 'category' => 'Aptitude'],
                ['name' => 'Logical Reasoning', 'category' => 'Aptitude'],
                ['name' => 'Verbal Communication', 'category' => 'HR']
            ];
        }

        // 2. Civil Engineering (CE)
        if ($dept === 'CE' || strpos($dept, 'CIVIL') !== false) {
            return [
                ['name' => 'Site Engineering', 'category' => 'Technical'],
                ['name' => 'Concrete Technology', 'category' => 'Technical'],
                ['name' => 'Surveying & CAD', 'category' => 'Technical'],
                ['name' => 'Structural Analysis', 'category' => 'Technical'],
                ['name' => 'Geotechnical Engineering', 'category' => 'Technical'],
                ['name' => 'Environmental Engineering', 'category' => 'Technical'],
                ['name' => 'Quantitative Aptitude', 'category' => 'Aptitude'],
                ['name' => 'Logical Reasoning', 'category' => 'Aptitude'],
                ['name' => 'Verbal Communication', 'category' => 'HR']
            ];
        }

        // 3. Mechanical Engineering (ME / MECH / RA / ED)
        if ($dept === 'ME' || $dept === 'MECH' || strpos($dept, 'MECHANICAL') !== false || $dept === 'RA' || $dept === 'ED') {
            return [
                ['name' => 'Thermodynamics', 'category' => 'Technical'],
                ['name' => 'Fluid Mechanics', 'category' => 'Technical'],
                ['name' => 'Machine Design & CAD', 'category' => 'Technical'],
                ['name' => 'Strength of Materials', 'category' => 'Technical'],
                ['name' => 'Manufacturing Technology', 'category' => 'Technical'],
                ['name' => 'Control Systems & Robotics', 'category' => 'Technical'],
                ['name' => 'Quantitative Aptitude', 'category' => 'Aptitude'],
                ['name' => 'Logical Reasoning', 'category' => 'Aptitude'],
                ['name' => 'Verbal Communication', 'category' => 'HR']
            ];
        }

        // 4. Biotechnology (BT)
        if ($dept === 'BT' || strpos($dept, 'BIOTECH') !== false || strpos($dept, 'BIOTECHNOLOGY') !== false) {
            return [
                ['name' => 'Biochemistry', 'category' => 'Technical'],
                ['name' => 'Genetic Engineering', 'category' => 'Technical'],
                ['name' => 'Bioinformatics', 'category' => 'Technical'],
                ['name' => 'Cell Biology & Immunology', 'category' => 'Technical'],
                ['name' => 'Bioprocess Engineering', 'category' => 'Technical'],
                ['name' => 'Molecular Biology', 'category' => 'Technical'],
                ['name' => 'Quantitative Aptitude', 'category' => 'Aptitude'],
                ['name' => 'Logical Reasoning', 'category' => 'Aptitude'],
                ['name' => 'Verbal Communication', 'category' => 'HR']
            ];
        }

        // 5. Electronics / Electrical Engineering (ECE, EEE, EIE)
        if ($dept === 'ECE' || $dept === 'EEE' || $dept === 'EIE' || strpos($dept, 'ELECTRONICS') !== false || strpos($dept, 'ELECTRICAL') !== false) {
            return [
                ['name' => 'Digital Electronics', 'category' => 'Technical'],
                ['name' => 'Embedded Systems', 'category' => 'Technical'],
                ['name' => 'Basic Programming', 'category' => 'Technical'],
                ['name' => 'Analog Circuits & Signals', 'category' => 'Technical'],
                ['name' => 'Microprocessors & VLSI', 'category' => 'Technical'],
                ['name' => 'Power Systems & Control', 'category' => 'Technical'],
                ['name' => 'Quantitative Aptitude', 'category' => 'Aptitude'],
                ['name' => 'Logical Reasoning', 'category' => 'Aptitude'],
                ['name' => 'Verbal Communication', 'category' => 'HR']
            ];
        }

        // 6. MSc, BSc, Pure Sciences
        if (strpos($dept, 'MSC') !== false || strpos($dept, 'BSC') !== false || $crs === 'MSC' || $crs === 'BSC') {
            return [
                ['name' => 'Research Methodology', 'category' => 'Technical'],
                ['name' => 'Data Analysis & Statistics', 'category' => 'Technical'],
                ['name' => 'Scientific Writing', 'category' => 'Technical'],
                ['name' => 'Advanced Mathematics', 'category' => 'Technical'],
                ['name' => 'Environmental Sciences', 'category' => 'Technical'],
                ['name' => 'Core Scientific Concepts', 'category' => 'Technical'],
                ['name' => 'Quantitative Aptitude', 'category' => 'Aptitude'],
                ['name' => 'Logical Reasoning', 'category' => 'Aptitude'],
                ['name' => 'Verbal Communication', 'category' => 'HR']
            ];
        }

        // Default (CSE, ISE, MCA, BCA, and fallback for others)
        return [
            ['name' => 'Data Structures', 'category' => 'Technical'],
            ['name' => 'Algorithms', 'category' => 'Technical'],
            ['name' => 'SQL & Databases', 'category' => 'Technical'],
            ['name' => 'Operating Systems', 'category' => 'Technical'],
            ['name' => 'Computer Networks', 'category' => 'Technical'],
            ['name' => 'Object-Oriented Programming', 'category' => 'Technical'],
            ['name' => 'Quantitative Aptitude', 'category' => 'Aptitude'],
            ['name' => 'Logical Reasoning', 'category' => 'Aptitude'],
            ['name' => 'Verbal Communication', 'category' => 'HR']
        ];
    }

    /**
     * Initialize Topic Mastery rows if they don't exist
     */
    private function initializeTopicMastery($studentId, $institution, $studentName, $skills, $profile = []) {
        $department = $profile['department'] ?? 'N/A';
        $course = $profile['course'] ?? 'N/A';

        $defaultTopics = $this->getDefaultTopicsForBranch($department, $course);

        // Parse skills to identify other custom topics
        foreach ($skills as $skill) {
            $cleanedSkill = trim(preg_replace('/\s*\(.*\)\s*/', '', $skill));
            if (!empty($cleanedSkill) && strlen($cleanedSkill) < 100) {
                $defaultTopics[] = ['name' => $cleanedSkill, 'category' => 'Technical'];
            }
        }

        $stmt = $this->db->prepare("
            INSERT IGNORE INTO student_topic_mastery 
            (student_name, student_id, institution, topic_name, category, mastery_level, attempts_count, is_high_priority)
            VALUES (?, ?, ?, ?, ?, 30, 0, 1)
        ");

        foreach ($defaultTopics as $topic) {
            $stmt->execute([
                $studentName,
                $studentId,
                $institution,
                $topic['name'],
                $topic['category']
            ]);
        }
    }

    /**
     * Get or create today's daily micro-challenge for the student
     */
    public function getOrCreateDailyChallenge($studentId, $institution, $studentName = '') {
        try {
            // 1. Check if there is an active challenge created in the last 18 hours (pending or completed)
            $stmt = $this->db->prepare("
                SELECT * FROM daily_micro_challenges 
                WHERE student_id = ? AND institution = ? AND created_at > DATE_SUB(NOW(), INTERVAL 18 HOUR) 
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$studentId, $institution]);
            $activeChallenge = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($activeChallenge) {
                $activeChallenge['question_json'] = json_decode($activeChallenge['question_json'], true);
                return $activeChallenge;
            }

            // 2. Select a target topic based on student's weaknesses (lowest mastery level)
            $stmt = $this->db->prepare("
                SELECT topic_name FROM student_topic_mastery 
                WHERE student_id = ? AND institution = ? 
                ORDER BY mastery_level ASC, attempts_count ASC 
                LIMIT 1
            ");
            $stmt->execute([$studentId, $institution]);
            $topic = $stmt->fetchColumn();

            if (!$topic) {
                // If no topics mapped yet, sync profile first to seed topics
                $this->getStudentAIProfile($studentId, $institution, $studentName);

                // Fetch student profile to get department/course for fallback topic
                $pStmt = $this->db->prepare("SELECT * FROM student_profiles WHERE usn = ? LIMIT 1");
                $pStmt->execute([$studentId]);
                $profile = $pStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                if (empty($profile)) {
                    $studentModel = new \StudentProfile();
                    $userModel = new \User();
                    $user = $userModel->findByUsername($studentId);
                    if ($user) {
                        $profile = $studentModel->getByUserId($user['id'], $institution) ?: [];
                    }
                }

                $department = $profile['department'] ?? 'N/A';
                $course = $profile['course'] ?? 'N/A';
                $defaults = $this->getDefaultTopicsForBranch($department, $course);
                $fallbackTopic = $defaults[0]['name'] ?? 'Quantitative Aptitude';

                $stmt->execute([$studentId, $institution]);
                $topic = $stmt->fetchColumn() ?: $fallbackTopic;
            }

            // 3. Generate exactly 5 MCQs using AIService
            $scenarioInstruction = "";
            $lowerTopic = strtolower($topic);
            if (strpos($lowerTopic, 'verbal') !== false || strpos($lowerTopic, 'communication') !== false || strpos($lowerTopic, 'hr') !== false || strpos($lowerTopic, 'business') !== false) {
                $scenarioInstruction = " Frame the questions within a professional corporate or technical workspace context (e.g., explaining technical concepts to stakeholders, handling communication conflicts in engineering teams, professional email/communication etiquette in corporate environments, or technical HR scenario behavior).";
            }

            $systemPrompt = "You are a Senior Professional Examiner. Generate exactly 5 highly challenging multiple choice questions (MCQs) on the topic: '{$topic}'.{$scenarioInstruction}
            You must return a response strictly formatted as a valid JSON array of objects, where each object matches the following structure:
            [
              {
                \"question\": \"The question text\",
                \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
                \"answer\": 0, // Integer index (0 to 3) representing the correct option
                \"explanation\": \"Detailed explanation of why the correct option is right and others are wrong.\"
              }
            ]
            Ensure the output is pure JSON without any surrounding markdown wraps.";

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Generate 5 MCQ questions on: $topic"]
            ];

            $aiResponse = $this->ai->callAPI($messages, [
                'audit_method' => __FUNCTION__,
                'response_format' => ['type' => 'json_object']
            ]);

            if (!$aiResponse['success'] || empty($aiResponse['parsed'])) {
                throw new Exception("Failed to generate MCQ via AI.");
            }

            $questionData = $aiResponse['parsed'];

            // 4. Save to daily_micro_challenges
            $stmt = $this->db->prepare("
                INSERT INTO daily_micro_challenges 
                (student_name, student_id, institution, topic_name, question_json, status, expires_at)
                VALUES (?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ");
            
            $stmt->execute([
                $studentName ?: $studentId,
                $studentId,
                $institution,
                $topic,
                json_encode($questionData)
            ]);

            $challengeId = $this->db->lastInsertId();

            return [
                'id' => $challengeId,
                'student_id' => $studentId,
                'institution' => $institution,
                'topic_name' => $topic,
                'question_json' => $questionData,
                'status' => 'pending'
            ];
        } catch (Exception $e) {
            error_log("getOrCreateDailyChallenge Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Submit response to a micro challenge and update topic mastery
     */
    public function submitChallengeResponse($challengeId, $studentId, $institution, $selectedOptions) {
        try {
            // Fetch challenge details
            $stmt = $this->db->prepare("SELECT * FROM daily_micro_challenges WHERE id = ? AND student_id = ? AND institution = ? LIMIT 1");
            $stmt->execute([$challengeId, $studentId, $institution]);
            $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$challenge || $challenge['status'] !== 'pending') {
                return ['success' => false, 'message' => 'Challenge not active or already completed.'];
            }

            $questionData = json_decode($challenge['question_json'], true);
            
            $isNested = false;
            if (isset($questionData['questions']) && is_array($questionData['questions'])) {
                $questionsArray = $questionData['questions'];
                $isNested = true;
            } else {
                $questionsArray = $questionData;
            }

            // Check if we are dealing with multiple questions (array of arrays) or a single question
            $isMultiple = isset($questionsArray[0]) && is_array($questionsArray[0]);

            $correctCount = 0;
            $results = [];
            $totalQuestions = $isMultiple ? count($questionsArray) : 1;

            if ($isMultiple) {
                // $selectedOptions is expected to be an array of choices
                foreach ($questionsArray as $idx => &$q) {
                    $correctOption = (int)($q['answer'] ?? 0);
                    $selectedOption = isset($selectedOptions[$idx]) ? (int)$selectedOptions[$idx] : -1;
                    $isQCorrect = ($selectedOption === $correctOption);
                    if ($isQCorrect) {
                        $correctCount++;
                    }
                    $q['selected_answer'] = $selectedOption;
                    $results[] = [
                        'question' => $q['question'] ?? '',
                        'options' => $q['options'] ?? [],
                        'selected' => $selectedOption,
                        'correct_answer' => $correctOption,
                        'is_correct' => $isQCorrect,
                        'explanation' => $q['explanation'] ?? ''
                    ];
                }
                unset($q);
                $performanceResult = $correctCount; 
                
                // Re-nest if it was nested
                if ($isNested) {
                    $questionData['questions'] = $questionsArray;
                } else {
                    $questionData = $questionsArray;
                }
            } else {
                // Backward compatibility for single question
                $correctOption = (int)($questionData['answer'] ?? 0);
                $selectedOption = is_array($selectedOptions) ? (isset($selectedOptions[0]) ? (int)$selectedOptions[0] : -1) : (int)$selectedOptions;
                $isQCorrect = ($selectedOption === $correctOption);
                if ($isQCorrect) {
                    $correctCount = 1;
                }
                $questionData['selected_answer'] = $selectedOption;
                $results[] = [
                    'question' => $questionData['question'] ?? '',
                    'options' => $questionData['options'] ?? [],
                    'selected' => $selectedOption,
                    'correct_answer' => $correctOption,
                    'is_correct' => $isQCorrect,
                    'explanation' => $questionData['explanation'] ?? ''
                ];
                $performanceResult = $isQCorrect ? 1 : 0;
            }

            // Update challenge status and save student choices inside question_json
            $stmt = $this->db->prepare("
                UPDATE daily_micro_challenges 
                SET status = 'completed', performance_result = ?, question_json = ? 
                WHERE id = ?
            ");
            $stmt->execute([$performanceResult, json_encode($questionData), $challengeId]);

            // Update topic mastery score
            $topic = $challenge['topic_name'];
            if ($isMultiple) {
                // Adjust: -5 to +15 based on correct Count
                $adjustment = 4 * $correctCount - 5;
            } else {
                $adjustment = ($correctCount === 1) ? 15 : -5;
            }

            // Fetch current mastery level
            $stmt = $this->db->prepare("SELECT mastery_level FROM student_topic_mastery WHERE student_id = ? AND institution = ? AND topic_name = ? LIMIT 1");
            $stmt->execute([$studentId, $institution, $topic]);
            $currentMastery = $stmt->fetchColumn();

            if ($currentMastery === false) {
                $currentMastery = 30; // default start
            }

            $newMastery = max(0, min(100, $currentMastery + $adjustment));

            $stmt = $this->db->prepare("
                INSERT INTO student_topic_mastery 
                (student_name, student_id, institution, topic_name, mastery_level, attempts_count, last_tested_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE 
                mastery_level = ?, 
                attempts_count = attempts_count + 1, 
                last_tested_at = NOW()
            ");
            $stmt->execute([
                $challenge['student_name'] ?: $studentId,
                $studentId,
                $institution,
                $topic,
                $newMastery,
                $newMastery
            ]);

            return [
                'success' => true,
                'correct_count' => $correctCount,
                'total_questions' => $totalQuestions,
                'results' => $results,
                'new_mastery' => $newMastery,
                'topic' => $topic,
                // UI compatibility keys
                'is_correct' => $correctCount === $totalQuestions,
                'correct_answer' => $results[0]['correct_answer'] ?? 0,
                'explanation' => $results[0]['explanation'] ?? '',
                'question' => $results[0]['question'] ?? ''
            ];
        } catch (Exception $e) {
            error_log("submitChallengeResponse Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetch unread AI Insights, or generate them dynamically if none exist
     */
    public function getStudentInsights($studentId, $institution, $studentName = '') {
        try {
            // Retrieve recent insights
            $stmt = $this->db->prepare("SELECT * FROM student_ai_insights WHERE student_id = ? AND institution = ? ORDER BY priority DESC, created_at DESC LIMIT 5");
            $stmt->execute([$studentId, $institution]);
            $insights = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $needsRegen = empty($insights);
            if (!$needsRegen && !empty($insights)) {
                $newestTime = strtotime($insights[0]['created_at'] ?? '2000-01-01');
                if (time() - $newestTime > 7200) { // 2 hours
                    $needsRegen = true;
                }
            }

            if ($needsRegen) {
                $this->generateStudentInsights($studentId, $institution, $studentName);
                $stmt->execute([$studentId, $institution]);
                $insights = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            return $insights;
        } catch (Exception $e) {
            error_log("getStudentInsights Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate dynamic actionable insights for a student based on their profile data
     */
    public function generateStudentInsights($studentId, $institution, $studentName = '') {
        try {
            // Gather student details
            $stmt = $this->db->prepare("SELECT * FROM student_profiles WHERE usn = ? LIMIT 1");
            $stmt->execute([$studentId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if (empty($profile)) {
                $studentModel = new \StudentProfile();
                $userModel = new \User();
                $user = $userModel->findByUsername($studentId);
                if ($user) {
                    $profile = $studentModel->getByUserId($user['id'], $institution) ?: [];
                }
            }

            $stmt = $this->db->prepare("SELECT * FROM student_portfolio WHERE student_id = ? AND institution = ?");
            $stmt->execute([$studentId, $institution]);
            $portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Check resume status
            $resumeFilePath = UPLOADS_PATH . '/resumes/Student_Resumes/' . strtoupper($studentId) . '_Resume.pdf';
            $hasResumeFile = file_exists($resumeFilePath);

            $insights = [];

            // Rule 1: Resume Verification Warning
            if (!$hasResumeFile) {
                $insights[] = [
                    'insight_type' => 'Warning',
                    'message' => 'No active PDF resume detected. Upload a resume in the Resume Builder to enable AI Resume Analytics.',
                    'action_link' => 'resume_builder.php',
                    'priority' => 5
                ];
            }

            // Rule 2: Portfolio Completeness Warnings
            $skillsCount = 0;
            $projectsCount = 0;
            foreach ($portfolio as $item) {
                if ($item['category'] === 'Skill') $skillsCount++;
                if ($item['category'] === 'Project') $projectsCount++;
            }

            if ($skillsCount === 0) {
                $insights[] = [
                    'insight_type' => 'Warning',
                    'message' => 'Your skill profile is empty. Add core skills in the Portfolio builder to unlock targeted role analysis.',
                    'action_link' => 'portfolio.php',
                    'priority' => 4
                ];
            }

            if ($projectsCount === 0) {
                $insights[] = [
                    'insight_type' => 'Warning',
                    'message' => 'No academic or personal projects listed. recruiters prioritize candidates with hands-on projects.',
                    'action_link' => 'portfolio.php',
                    'priority' => 4
                ];
            }

            // Rule 3: Academic CGPA Insights
            $cgpa = floatval($profile['cgpa'] ?? 0);
            if ($cgpa >= 8.5) {
                $insights[] = [
                    'insight_type' => 'Achievement',
                    'message' => "Exceptional CGPA score of {$cgpa}. You qualify for placement pre-screening processes at tier-1 tech companies.",
                    'action_link' => 'jobs.php',
                    'priority' => 3
                ];
            } elseif ($cgpa > 0 && $cgpa < 6.5) {
                $insights[] = [
                    'insight_type' => 'Warning',
                    'message' => "Your current CGPA is {$cgpa}. Some companies mandate a 6.5+ CGPA cut-off. Focus on upcoming academic cycles.",
                    'action_link' => 'sgpa_entry.php',
                    'priority' => 4
                ];
            }

            // Rule 4: Dynamic AI Goal Match Insight
            $stmt = $this->db->prepare("SELECT predicted_role FROM student_ai_profiles WHERE student_id = ? AND institution = ? LIMIT 1");
            $stmt->execute([$studentId, $institution]);
            $predictedRole = $stmt->fetchColumn();

            if ($predictedRole) {
                $insights[] = [
                    'insight_type' => 'Goal_Match',
                    'message' => "Our AI model predicts you are best matched for a '{$predictedRole}' role based on your current skills.",
                    'action_link' => 'profile_analyser.php',
                    'priority' => 2
                ];
            }

            // Rule 5: Leaderboard Overtaken Warning (Gamification)
            try {
                $studentDept = $profile['department'] ?? ($profile['discipline'] ?? null);
                $studentInst = $institution ?: 'GMU';
                
                $filters = [];
                if (!empty($studentDept)) {
                    $filters['discipline'] = getCoordinatorDisciplineFilters($studentDept);
                    $filters['institution'] = $studentInst;
                }
                
                $rankings = \App\Services\LeaderboardService::getRankingsWithHistory($filters);
                
                // Find student's current rank and previous rank in this view
                $myRank = null;
                $myPrevRank = null;
                foreach ($rankings as $r) {
                    if (strcasecmp($r['usn'], $studentId) === 0) {
                        $myRank = $r['rank'];
                        $myPrevRank = $r['previous_rank'] ?? $r['rank'];
                        break;
                    }
                }

                if ($myRank !== null && $myPrevRank !== null && $myRank > $myPrevRank) {
                    // Student dropped in rank. Find who overtook them!
                    $overtakers = [];
                    foreach ($rankings as $r) {
                        if (strcasecmp($r['usn'], $studentId) !== 0) {
                            if ($r['rank'] < $myRank && ($r['previous_rank'] ?? $r['rank']) > $myPrevRank) {
                                $overtakers[] = $r['name'];
                            }
                        }
                    }
                    
                    if (!empty($overtakers)) {
                        $firstOvertaker = $overtakers[0];
                        $countOthers = count($overtakers) - 1;
                        if ($countOthers > 0) {
                            $msg = "Competition Alert! {$firstOvertaker} and {$countOthers} others just overtook you on the Leaderboard. Retake your lowest AI assessment to reclaim your rank!";
                        } else {
                            $msg = "Competition Alert! {$firstOvertaker} just overtook you on the Leaderboard. Retake your lowest AI assessment to reclaim your rank!";
                        }
                        
                        $insights[] = [
                            'insight_type' => 'Warning',
                            'message' => $msg,
                            'action_link' => 'leaderboard.php',
                            'priority' => 6 // Highest priority warning!
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("Leaderboard insight generation error: " . $e->getMessage());
            }

            // Save generated insights
            if (!empty($insights)) {
                // Clear old unread insights to prevent duplicates
                $stmt = $this->db->prepare("DELETE FROM student_ai_insights WHERE student_id = ? AND institution = ? AND is_read = 0");
                $stmt->execute([$studentId, $institution]);

                $stmt = $this->db->prepare("
                    INSERT INTO student_ai_insights 
                    (student_name, student_id, institution, insight_type, message, action_link, priority)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($insights as $insight) {
                    $stmt->execute([
                        $profile['name'] ?? $studentName ?: $studentId,
                        $studentId,
                        $institution,
                        $insight['insight_type'],
                        $insight['message'],
                        $insight['action_link'],
                        $insight['priority']
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("generateStudentInsights Error: " . $e->getMessage());
        }
    }
}
