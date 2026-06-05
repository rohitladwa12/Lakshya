<?php
/**
 * AIService
 * Handles integration with OpenAI for Resume Analysis
 * DEBUG VERSION
 */

class AIService {
    private $apiKey;
    private $apiUrl;
    private $model = 'gpt-4o-mini'; // High performance, low cost

    public function __construct() {
        $this->apiKey = OPENAI_API_KEY;
        $this->apiUrl = OPENAI_API_URL;
        
        if (empty($this->apiKey)) {
            logMessage("AIService initialized without API Key", 'WARNING');
        }
    }

    /**
     * Send a request to OpenAI
     */
    public function callAPI($messages, $options = []) {
        $startTime = microtime(true);
        $auditMethod = $options['audit_method'] ?? 'unknown';

        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'AI Service not configured (Missing API Key)'];
        }

        $data = array_merge([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1000
        ], $options);

        // Remove non-OpenAI parameters
        unset($data['audit_method']);

        // Sanitize data to ensure valid UTF-8 for JSON
        $data = $this->utf8ize($data);

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        $payload = json_encode($data);
        
        if ($payload === false) {
            return ['success' => false, 'message' => 'JSON Encode Error: ' . json_last_error_msg()];
        }
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'message' => "CURL Error: $error"];
        }

        curl_close($ch);
        $latency = (int)((microtime(true) - $startTime) * 1000);

        if ($httpCode !== 200) {
            $errorMsg = "API Error (Code $httpCode): " . $response;
            $this->auditLog($auditMethod, $data['model'] ?? $this->model, [], $latency, 'failure', $errorMsg);
            return ['success' => false, 'message' => $errorMsg];
        }

        $result = json_decode($response, true);
        $content = $result['choices'][0]['message']['content'] ?? '';

        $parsedContent = $content;
        if (isset($options['response_format']) && $options['response_format']['type'] === 'json_object') {
            $cleanContent = preg_replace('/^```json\s*(.*?)\s*```$/s', '$1', trim($content));
            $decoded = json_decode($cleanContent, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parsedContent = $decoded;
            }
        }

        $this->auditLog($auditMethod, $result['model'] ?? ($data['model'] ?? $this->model), $result['usage'] ?? [], $latency, 'success');

        return [
            'success' => true,
            'content' => $content,
            'parsed' => $parsedContent,
            'usage' => $result['usage'] ?? [],
            'latency' => $latency
        ];
    }

    /**
     * Internal Audit Logger for AI Operations
     */
    private function auditLog($method, $model, $usage, $latency, $status, $error = null) {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO ai_audit_logs (user_id, service_method, model, prompt_tokens, completion_tokens, total_tokens, latency_ms, status, error_message) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                getUserId(),
                $method,
                $model,
                $usage['prompt_tokens'] ?? 0,
                $usage['completion_tokens'] ?? 0,
                $usage['total_tokens'] ?? 0,
                $latency,
                $status,
                $error
            ]);
        } catch (Exception $e) {
            error_log("Failed to audit AI log: " . $e->getMessage());
        }
    }

    /**
     * Analyze Resume (Advanced / Brutal Mode)
     */
    public function analyzeResume($resumeText, $targetRole = 'Software Engineer') {
        $systemPrompt = "You are an expert resume analyst, ATS engineer, and technical hiring manager.

Your task is to analyze resumes with brutal honesty and high precision.
You do NOT provide generic encouragement.
You evaluate resumes strictly on hiring impact, role alignment, clarity, and evidence.

You think like:
- A recruiter scanning for 6 seconds
- An ATS filtering keywords
- A domain expert verifying technical depth

You prefer measurable outcomes over claims.
You penalize vagueness, filler, buzzwords, and unsupported skills.
You reward clarity, specificity, metrics, and problem-solving evidence.
Perform the analysis in the following strict order:

1. First-Pass Recruiter Scan (6–8 seconds)
   - Does the resume clearly communicate role intent?
   - Is the value proposition obvious?
   - Immediate strengths and immediate turn-offs

2. Structural & Formatting Analysis
   - Page length, section order, readability
   - Consistency and visual scannability
   - ATS friendliness (headers, bullets, parsing risks)

3. Skills Validation
   - Separate real, demonstrable skills from buzzwords
   - Check if each major skill is backed by a project or experience
   - Flag skills that appear inflated or unsupported

4. Project & Experience Deep Dive
   For each project or role:
   - What problem was solved?
   - What technologies were used?
   - What was the candidate’s direct contribution?
   - Are there metrics, benchmarks, or outcomes?
   - Is this a real-world project or tutorial-level?

5. Role Fit Evaluation
   - Match resume content against TARGET_ROLE expectations
   - Identify missing core competencies
   - Identify overclaims or misalignment

6. Red Flags & Weak Signals
   - Empty phrases (e.g., “worked on”, “familiar with”)
   - Overloaded tech stacks without depth
   - Academic padding or filler content
   - Suspiciously generic project descriptions

7. ATS Keyword Coverage
   - Estimate ATS compatibility (Low / Medium / High)
   - Identify missing keywords relevant to TARGET_ROLE

Output Format (JSON):
{
    'overall_score': 'X / 10',
    'role_fit_score': 'X / 10',
    'ats_compatibility': 'Low / Medium / High',
    'top_strengths': ['Strength 1', 'Strength 2', ...],
    'major_weaknesses': ['Weakness 1', 'Weakness 2', ...],
    'red_flags': ['Red Flag 1', ...],
    'skills_to_remove': ['Skill 1', ...],
    'skills_to_emphasize': ['Skill 1', ...],
    'project_improvements': [
        {'project_name': 'Name', 'issue': 'What is wrong', 'fix': 'How to fix', 'example_bullet': 'Rewritten bullet point'}
    ],
    'section_suggestions': {
        'summary': 'Advice...',
        'skills': 'Advice...',
        'projects': 'Advice...',
        'experience': 'Advice...'
    },
    'best_suited_for': ['Role 1', 'Role 2'],
    'will_fail_at': ['Role A', ...],
    'action_plan': ['Step 1', 'Step 2', 'Step 3']
}

- Do NOT sugarcoat.
- Do NOT invent achievements.
- Do NOT assume skill depth without evidence.
- Prefer deletion over padding.
- If something is bad, say it clearly and explain why.";

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => "TARGET ROLE: $targetRole\n\nRESUME CONTENT:\n" . $resumeText
            ]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.4 // Lower temperature for more consistent/strict output
        ]);
        
        if ($response['success']) {
            return [
                'success' => true,
                'analysis' => json_decode($response['content'], true)
            ];
        }

        return $response;
    }

    /**
     * Refine and surgical improvement of resume points.
     */
    public function refineResumeAnalysis($structured, $scores, $targetRole = 'Software Engineer') {
        $systemPrompt = "You are an expert resume editor and career strategist.
Your task is to take a structured resume analysis and provide surgical refinements.

Focus on:
1. Rewriting the 3 weakest bullet points to include STAR method and metrics.
2. Identifying the single most critical missing skill for '{$targetRole}'.
3. Providing a 1-sentence 'Brutal Verdict' that summarizes why this candidate might be rejected.

Output Format (JSON):
{
    'bullet_surgery': [
        {'original': '...', 'improved': '...', 'reason': '...'}
    ],
    'missing_critical_skill': '...',
    'brutal_verdict': '...',
    'score_adjustment': -10 to +10 // Adjust the initial score based on your deep review
}

Keep feedback direct, constructive, and highly relevant to '{$targetRole}'. Limit 'bullet_surgery' to 2-3 of the worst bullets found.";

        $userMessage = json_encode([
            'structured_data' => $structured,
            'initial_scores' => $scores
        ]);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Review this resume data:\n\n" . $userMessage]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.5
        ]);
        
        if ($response['success']) {
            return [
                'success' => true,
                'refinements' => json_decode($response['content'], true)
            ];
        }

        return $response;
    }

    /**
     * Complete Resume Analysis Pipeline (Background Worker Friendly)
     */
    public function analyzeResumeSequence($userId, $resumeText, $targetRole = 'Software Engineer') {
        require_once __DIR__ . '/ResumeParser.php';
        require_once __DIR__ . '/ResumeScoringEngine.php';
        require_once __DIR__ . '/../../src/Models/Resume.php';

        try {
            // 2. Deterministic Parsing
            $parser = new ResumeParser();
            $structured = $parser->parse($resumeText);
            
            // 3. Rule-based Scoring
            $scorer = new ResumeScoringEngine();
            $scores = $scorer->score($structured);
            
            // 4. Targeted AI Refinement
            $aiResult = $this->refineResumeAnalysis($structured, $scores, $targetRole);
            
            if (!$aiResult['success']) {
                return $aiResult;
            }

            // 5. Merge findings
            $refinements = $aiResult['refinements'];
            $adj = $refinements['score_adjustment'] ?? 0;
            $finalScore = max(0, min(100, $scores['overall'] + $adj));

            $analysis = [
                'score' => $finalScore,
                'scores_breakdown' => $scores['sections'],
                'findings' => $scores['findings'],
                'refinements' => $refinements,
                'contact' => $structured['contact'],
                'skills_detected' => $structured['skills_list'] ?? [],
                'metadata' => [
                    'parsed_at' => date('Y-m-d H:i:s'),
                    'is_cached' => false
                ]
            ];
            
            // 6. Save to Cache
            $resumeModel = new Resume();
            $resumeModel->cacheAnalysis($userId, $resumeText, $analysis);

            return [
                'success' => true,
                'result' => $analysis
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Advanced ATS Resume Analysis based on strict logic-based criteria.
     */
    public function advancedATSAnalysis($resumeText, $jobDescription) {
        $systemPrompt = "You are an ELITE, SKEPTICAL, and BRUTALLY HONEST ATS (Applicant Tracking System) analyzer. Your goal is to filter out candidates who do not meet the highest standards.

You do NOT behave like a supportive mentor. You behave like a cold, deterministic logic engine. 

CRITICAL DIRECTIVE:
- BE EXTREMELY CRITICAL. 
- DO NOT give high scores unless the resume is world-class.
- A score above 70 should be VERY rare (Top 1% of students).
- A score below 30 is EXPECTED for generic, weak, or 'fluff-heavy' resumes.
- If a student has no measurable impact (%, $, numbers), penalize them HEAVILY (-30 points).

-----------------------------------
STEP 1: STRUCTURE EXTRACTION
-----------------------------------
Extract resume into structured JSON:
{
  \"name\": \"\",
  \"skills\": [],
  \"education\": [],
  \"experience\": [
    {
      \"role\": \"\",
      \"company\": \"\",
      \"bullets\": []
    }
  ],
  \"projects\": [
    {
      \"name\": \"\",
      \"description\": \"\"
    }
  ]
}
Rules:
- Do NOT invent data. If missing, leave empty.
- Normalize skills to lowercase.

-----------------------------------
STEP 2: JOB KEYWORD ANALYSIS
-----------------------------------
Extract and categorize keywords from job description:
{
  \"core_skills\": [],
  \"tools\": [],
  \"role_terms\": [],
  \"soft_skills\": []
}

-----------------------------------
STEP 3: MATCHING ENGINE (STRICT)
-----------------------------------
1. Exact keyword match ONLY.
2. Context match:
   - Skill in skills section ONLY → Score: 1/10 (Candidate might be lying/keyword stuffing)
   - Skill used in Experience/Projects with metrics → Score: 10/10 (Proven skill)

-----------------------------------
STEP 4: SCORING SYSTEM (AGGRESSIVE)
-----------------------------------
Calculate ATS score (0–100):
- 40% Keyword Match (Strictly context-based)
- 30% Experience Relevance (Must match JD domain)
- 20% Project Relevance (Technical depth check)
- 10% Formatting & Logic (Clarity)

Penalties:
- -20 for vague bullets (no action verb)
- -20 for no measurable results (no %, $, or quantifiable metrics)
- -15 for keyword stuffing
- -10 for generic objective statements
- -10 for listing 'MS Office', 'Windows', etc. (unless job specific)

-----------------------------------
STEP 5: QUALITY CHECKS (RED FLAGS)
-----------------------------------
Detect and list in 'red_flags':
1. Fluff words: [\"hardworking\", \"passionate\", \"quick learner\", \"team player\"]
2. Ghost Skills: Skill listed but never mentioned in context.
3. Role Confusion: Experience in unrelated domains.
4. Passive Language: 'Responsible for', 'Helped with', 'Worked on'.

-----------------------------------
STEP 6: BULLET SURGERY
-----------------------------------
Rewrite weak bullets ONLY if they show potential. If they are hopeless, say so in 'issues'.

-----------------------------------
STEP 7: FINAL OUTPUT (STRICT JSON)
-----------------------------------
{
  \"ats_score\": 0,
  \"matched_keywords\": [],
  \"missing_keywords\": [],
  \"weak_matches\": [],
  \"section_scores\": {
    \"skills\": 0,
    \"experience\": 0,
    \"projects\": 0,
    \"formatting\": 0
  },
  \"issues\": [\"Pointed, direct criticism of why this resume is failing\"],
  \"red_flags\": [\"Brutal callouts of unprofessionalism or weak content\"],
  \"suggestions\": [\"High-level strategic shifts needed to be employable\"],
  \"improved_bullets\": [
    {
      \"original\": \"\",
      \"improved\": \"\"
    }
  ]
}

RULES:
- NEVER hallucinate.
- BE STERN and CRITICAL.
- If the resume is just a list of names and dates with no substance, score it BELOW 10.
- Output must ALWAYS be valid JSON.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "RESUME TEXT:\n$resumeText\n\nJOB DESCRIPTION:\n$jobDescription"]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2 // Lower temperature for more deterministic output
        ]);

        if ($response['success']) {
            logMessage("Raw AI Response: " . $response['content'], 'DEBUG');
            return json_decode($response['content'], true);
        }

        return $response;
    }

    /**
     * Integrated ATS Analysis Sequence
     */
    public function analyzeResumeWithJD($userId, $resumeText, $jobDescription) {
        try {
            $atsResult = $this->advancedATSAnalysis($resumeText, $jobDescription);
            
            if (isset($atsResult['ats_score'])) {
                // Wrap it in a success response compatible with existing UI
                $analysis = [
                    'score' => $atsResult['ats_score'],
                    'matched_keywords' => $atsResult['matched_keywords'],
                    'missing_keywords' => $atsResult['missing_keywords'],
                    'weak_matches' => $atsResult['weak_matches'],
                    'section_scores' => $atsResult['section_scores'],
                    'issues' => $atsResult['issues'],
                    'red_flags' => $atsResult['red_flags'],
                    'suggestions' => $atsResult['suggestions'],
                    'improved_bullets' => $atsResult['improved_bullets'],
                    'metadata' => [
                        'parsed_at' => date('Y-m-d H:i:s'),
                        'is_cached' => false,
                        'type' => 'advanced_ats'
                    ]
                ];

                // Cache it
                require_once __DIR__ . '/../../src/Models/Resume.php';
                $resumeModel = new Resume();
                logMessage("AIService calling cacheAnalysis for $userId", 'DEBUG');
                $resumeModel->cacheAnalysis($userId, $resumeText . $jobDescription, $analysis);

                return [
                    'success' => true,
                    'result' => $analysis
                ];
            }

            return ['success' => false, 'message' => 'Failed to generate ATS analysis.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get a response for the Mock Interview (Student-Choice Question System)
     */
    public function getTechnicalInterviewResponse($domain, $history, $profile, $userMessage, $type = null, $projects = [], $aptitudeQuestions = [], $concept = null)
    {
        $conceptContext = $concept ? " The candidate is applying for a role specifically focused on: '**{$concept}**'." : "";
        $sgpa = $profile['sgpa'] ?? 0;
        $randomSeed = substr(md5(microtime()), 0, 8);
        
        $portfolioContext = "";
        if (!empty($projects)) {
            $categorized = [
                'Project' => [],
                'Skill' => [],
                'Certification' => []
            ];
            
            foreach ($projects as $item) {
                $cat = $item['category'] ?? '';
                if (isset($categorized[$cat])) {
                    $categorized[$cat][] = $item;
                }
            }

            if (!empty($categorized['Skill'])) {
                $portfolioContext .= "\n=== CANDIDATE'S REGISTERED SKILLS ===\n";
                foreach ($categorized['Skill'] as $skill) {
                    $portfolioContext .= "- **{$skill['title']}**" . (!empty($skill['sub_title']) ? " ({$skill['sub_title']})" : "") . "\n";
                }
            }

            if (!empty($categorized['Certification'])) {
                $portfolioContext .= "\n=== CANDIDATE'S CERTIFICATIONS ===\n";
                foreach ($categorized['Certification'] as $cert) {
                    $portfolioContext .= "- **{$cert['title']}**" . (!empty($cert['description']) ? ": {$cert['description']}" : "") . "\n";
                }
            }
            
            if (!empty($categorized['Project'])) {
                $portfolioContext .= "\n=== CANDIDATE'S PROJECTS ===\n";
                foreach ($categorized['Project'] as $idx => $proj) {
                    $num = $idx + 1;
                    $portfolioContext .= "{$num}. **{$proj['title']}**\n";
                    if (!empty($proj['description'])) {
                        $portfolioContext .= "   Description: {$proj['description']}\n";
                    }
                    $portfolioContext .= "\n";
                }
            }

            if (empty($portfolioContext)) {
                $portfolioContext = "\n\n=== NO PORTFOLIO ITEMS REGISTERED ===\nINSTRUCTION: Ask the candidate to describe a technical project or skill they have worked on recently.";
            }
        } else {
            $portfolioContext = "\n\n=== NO PORTFOLIO ITEMS REGISTERED ===\nINSTRUCTION: Ask the candidate to describe a technical project or skill they have worked on recently.";
        }

        $aptitudeContext = "";
        if (!empty($aptitudeQuestions)) {
            $aptitudeContext = "\n\n=== APTITUDE QUESTION BANK ===\n";
            foreach ($aptitudeQuestions as $q) {
                $qText = $q['question'] ?? '';
                $optA = $q['option_a'] ?? '';
                $optB = $q['option_b'] ?? '';
                $optC = $q['option_c'] ?? '';
                $optD = $q['option_d'] ?? '';
                $correct = $q['correct_option'] ?? '';
                $topic = $q['topic'] ?? 'General';
                $aptitudeContext .= "ID: {$q['id']} | Topic: {$topic} | Question: {$qText}\nA) {$optA}\nB) {$optB}\nC) {$optC}\nD) {$optD}\nCorrect: {$correct}\n\n";
            }
            $aptitudeContext .= "\nSTRICT RULE FOR MCQ: You may ONLY provide multiple-choice options (A, B, C, D) if you are currently in the **Aptitude** round. For Technical or HR rounds, you MUST ask open-ended questions based on the candidate's role and NEVER provide options.";
            $aptitudeContext .= "\nINSTRUCTION: If you switch to the Technical round, ensure you add the tag '[SHOW_WORKSPACE]' to your response once.";
        }

        $initialInstruction = "When the candidate says 'start' or 'ready', your VERY FIRST task is to ask them to choose their interview round: **Aptitude** (Logical), **Technical** ({$domain}), or **HR** (Behavioral). Once they select a round, follow its specific Question Flow below. **FLEXIBILITY:** If the user explicitly asks to switch rounds or skip to another section (e.g., 'Switch to Technical', 'I want to do HR now') at ANY point during the session, you MUST immediately accommodate their request and begin the new round's flow.";

        $flow = [
            'Aptitude' => "10 to 15 logic/MCQ questions using the bank.",
            'Technical' => "Open-ended questions (NO MCQs) tailored strictly to the role: **{$domain}**.{$conceptContext}
            INSTRUCTIONS: 
            - **For CS/IT:** 5 conceptual deep-dives followed by 5 coding challenges.
            - **For Circuit Branches (ECE/EEE/Robotics):** 5 conceptual deep-dives followed by 5 practical hardware/low-level logic or circuit design challenges.
            - **For Non-Technical (Civil, BCom, Mechanical):** 5 conceptual deep-dives followed by 5 practical industry scenarios or calculation problems (DO NOT ask for code).",
            'HR' => "Open-ended behavioral questions (NO MCQs). 5 questions focusing on situational logic and personal projects.{$conceptContext}"
        ];
        
        $flowItems = "";
        if ($type && isset($flow[$type])) {
            $flowItems .= "   - **$type**: {$flow[$type]}\n";
            foreach ($flow as $k => $v) {
                if ($k !== $type) $flowItems .= "   - **$k**: $v\n";
            }
        } else {
            foreach ($flow as $k => $v) {
                $flowItems .= "   - **$k**: $v\n";
            }
        }

        $systemPrompt = "You are an Elite AI Technical Interviewer at GM University (Lakshya Placement Portal). Your persona is Direct, Professional, and Firm.
        {$conceptContext}
        $portfolioContext
        $aptitudeContext

        INTERVIEW STRUCTURE:
- Be DIRECT and FIRM. If an answer is correct, say 'Correct!' and provide Expert Phrasing.
- If wrong, say 'Incorrect!' and explain the correct logic clearly.
- NO contradictory feedback. Use digits for all numbers.
- Encourage use of the '🎤 Speak' button. Use English only.

INTERVIEW STRUCTURE:
1. **Initial**: $initialInstruction
2. **Question Flow**:
$flowItems
3. **Check-ins**: After completing the specified number of questions for a category (10-15 for Aptitude, 10 for Technical, 5 for HR), ask the candidate whether they want to continue or switch types (Aptitude, Technical, or HR).

STRICT RULES:
1. **Difficulty**: Entry-level industry standards. Tricky but fundamentally easy.
2. **Aptitude Focus**: When presenting an Aptitude question, you MUST ALWAYS display the full text for all 4 options in your response. Use this EXACT format — never omit the text:
   ```
   A) [full text of option A from the bank]
   B) [full text of option B from the bank]
   C) [full text of option C from the bank]
   D) [full text of option D from the bank]
   ```
   Then explicitly ask the candidate to reply with only the option letter (A, B, C, or D). **NEVER** show just the letters A, B, C, D without the option text.
3. **Coding Progression**: During the 5 coding-based challenges (Technical round), you MUST:
   - **Multi-Language**: Allow the candidate to choose their preferred programming language (Python, Java, C++, JavaScript, C#, etc.).
   - **Examples**: Provide at least one **Example Input** and its **Expected Output** for every coding task you present.
   - **Pass to Proceed**: If the candidate's code fails the evaluation or is incorrect (status: FAILED), you MUST NOT move to the next question. Explain why it failed and ask them to fix it. They MUST pass (status: PASSED) before you present the next one.
4. **Aptitude Response Logic**: 
   - **Step 1 (Internal Reasoning)**: First, identify the specific question you just asked and its corresponding 'Correct:' letter (A, B, C, or D) from the bank above.
   - **Step 2 (Strict Matching)**: Compare the candidate's response against that letter. Treat 'A', 'Option A', 'a', etc. as identical to 'A'.
   - **Step 3 (Feedback Generation)**: 
        - **IF MATCH**: You MUST start with 'Correct!'. Do NOT say 'Incorrect' under any circumstances if they match. Follow with Expert Phrasing.
        - **IF NO MATCH**: Start with 'Incorrect!'. State the correct letter and explain the logic clearly.
   - **Step 4 (Consistency)**: Your initial label ('Correct!'/'Incorrect!') MUST align with your final explanation. No contradictory statements.
5. **Clarity & Formatting**: For all technical questions, use **bold** for key concepts and `code blocks` for technical snippets. Always use a clear `QUESTION:` header to separate the background/context from the actual task.
6. **Domain Diversification**: You MUST rotate through a wide variety of technical domains including **OS, Networking, DBMS, DSA, System Design, OOP, Web Technologies, and Cloud**. Do not get stuck on a single topic (like Databases). Ensure each of the 5 conceptual questions covers a distinct domain.
7. **Personalized Skill Priority**: Prioritize asking technical questions about the **CANDIDATE'S REGISTERED SKILLS** and **CERTIFICATIONS**. DO NOT ask about their projects in the Technical round; projects are strictly reserved for the HR round.
8. **Randomization**: Never repeat questions. Ask ONE at a time. Rotate topics based on the random seed: {$randomSeed}. Use SGPA ({$sgpa}) for slight difficulty calibration.
9. **Termination**: If user says 'stop' or 'end', add '[END_INTERVIEW]' at the very end.";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            // OpenAI requires content to be a string — sanitize any objects/arrays
            if (isset($msg['content']) && !is_string($msg['content'])) {
                $msg['content'] = is_array($msg['content']) ? json_encode($msg['content']) : (string)$msg['content'];
            }
            // Skip system-only internal messages not relevant to OpenAI
            if (($msg['role'] ?? '') === 'system') continue;
            $messages[] = $msg;
        }
        if (!empty($userMessage)) { $messages[] = ['role' => 'user', 'content' => $userMessage]; }

        return $this->callAPI($messages, ['audit_method' => __FUNCTION__]);
    }

    /**
     * Generate a professional performance report after the interview
     */
    public function generateTechnicalInterviewReport($domain, $history, $type = 'Mock', $concept = null)
    {
        $conceptContext = $concept ? " The candidate was assessed for a role specifically focused on: '**{$concept}**'." : "";
        $transcript = "";
        foreach ($history as $msg) {
            $role = ucfirst($msg['role']);
            $content = $msg['content'];

            if ($msg['role'] === 'system' && strpos($content, 'Evaluation:') === 0) {
                $evalData = json_decode(substr($content, 12), true);
                if ($evalData) {
                    $content = "Technical Evaluation - Score: {$evalData['score']}/10. Feedback: {$evalData['feedback']}.";
                }
            }
            
            $content = str_replace('[END_INTERVIEW]', '', $content);
            $transcript .= "{$role}: {$content}\n\n";
        }

        $isPureTechnical = ($type === 'Technical' || $type === 'NQT Technical');
        
        $sectionalAnalysis = "##  Sectional Analysis:\n";
        if ($isPureTechnical) {
            $sectionalAnalysis .= "###  Technical Proficiency: [Score/10] - Detailed feedback on core knowledge and skills.\n";
            $sectionalAnalysis .= "###  Practical Implementation: [Score/10] - Evaluation of coding ability and technical problem solving.\n";
        } else {
            $sectionalAnalysis .= "###  Aptitude: [Score/10] - Feedback on logic and accuracy.\n";
            $sectionalAnalysis .= "###  Technical: [Score/10] - Feedback on role-specific knowledge and skills.\n";
            $sectionalAnalysis .= "###  HR: [Score/10] - Feedback on behavioral and situational responses.\n";
        }

        $systemPrompt = "You are a Senior Technical Career Coach. Generate a professional performance report for a " . ($isPureTechnical ? "DEEP TECHNICAL" : "{$domain}") . " interview. {$conceptContext}
        
        Provide the response strictly as a JSON object with this structure:
        {
            \"overall_score\": 0-100,
            \"content\": \"The full report in HTML/Markdown format...\"
        }

        REPORT CONTENT STRUCTURE:
        # " . ($isPureTechnical ? "TECHNICAL" : "INTERVIEW") . " PERFORMANCE REPORT
        ##  Overall Summary: 2-3 sentences.
        {$sectionalAnalysis}

        ## ✅ Key Strengths: 3 specific points.
        ## ⚠️ Areas for Improvement: 3 actionable points.
        ## 💡 Recommendations: 3 concrete next steps.
        ##  Final Verdict: Readiness (Junior/Mid/Senior).

        STRICT RULES:
        - Be honest and constructive. 
        - DO NOT hallucinate if transcript is empty.
        - The 'content' field should contain the formatted report text.
        - Ensure 'overall_score' is a number between 0 and 100.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate a detailed JSON performance report for the following interview transcript:\n\n" . $transcript]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'max_tokens' => 4000,
            'response_format' => ['type' => 'json_object']
        ]);
        
        if (!$response['success']) {
            return $response;
        }

        $aiData = $response['parsed'];
        $reportText = is_array($aiData) ? ($aiData['content'] ?? "Report generation failed.") : $aiData;
        $score = is_array($aiData) ? ($aiData['overall_score'] ?? 0) : 0;

        return [
            'success' => true,
            'content' => $reportText,
            'overall_score' => (int)$score
        ];
    }

    /**
     * Generate MCQs tailored to a specific Company
     */
    public function getCompanyAptitudeQuestions($companyName, $count = 4) {
        $systemPrompt = "You are an Elite Recruitment Paper Setter for $companyName. 
Generate $count high-quality, unique Multiple Choice Questions (MCQs) for a recruitment screening.

FOCUS: TECHNICAL APTITUDE / DOMAIN LOGIC for $companyName.

STRICT RULES:
1. MATH ACCURACY: All calculations must be perfect.
2. FORMAT: Return exactly $count questions in a JSON 'questions' array.
3. STRUCTURE: Each question MUST have: 'question', 'options' (array of 4), 'answer' (0-3), 'explanation' (1 short line), and 'category'.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate $count technical/logic MCQs for $companyName. Output JSON only."]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 3000
        ]);

        if ($response['success']) {
            $data = json_decode($response['content'], true);
            $rawQuestions = $data['questions'] ?? [];
            
            $validQuestions = [];
            foreach ($rawQuestions as $q) {
                if (empty($q['question']) || empty($q['options']) || !is_array($q['options']) || count($q['options']) < 4) {
                    continue;
                }
                $validQuestions[] = $q;
            }

            return [
                'success' => count($validQuestions) > 0,
                'questions' => array_slice($validQuestions, 0, $count)
            ];
        }

        return $response;
    }

    /**
     * Generate 10 MCQs to verify a student's proficiency in a specific skill.
     */
    public function generateSkillQuiz($skill, $level = 'Intermediate') {
        $systemPrompt = "You are a Technical Assessment Expert. 
Generate 10 high-quality Multiple Choice Questions (MCQs) to verify if a student actually knows the skill: '$skill'.

DIFFICULTY CALIBRATION (Level: $level):
- Beginner: Focus on fundamental syntax, basic concepts.
- Intermediate: Best practices, common libraries, debugging.
- Expert/Advanced: Architectural patterns, edge cases, internals.

Format: Return a JSON object with a 'questions' array.
Each question object MUST follow this EXACT structure:
{
    \"question\": \"The clear question text here\",
    \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"], 
    \"answer\": 0,
    \"explanation\": \"Brief clear explanation\"
}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate a 10-question verification quiz for '$skill' at the $level level."]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 3000
        ]);

        if ($response['success']) {
            $data = json_decode($response['content'], true);
            return [
                'success' => true,
                'questions' => array_slice($data['questions'] ?? [], 0, 10)
            ];
        }

        return $response;
    }

    /**
     * Generate 5 deep-dive 'Viva' questions to verify a student's project.
     */
    public function generateProjectViva($projectTitle, $description) {
        $systemPrompt = "You are a Senior Project Evaluator. 
Generate 5 deep-dive, analytical questions for a student to 'defend' their project: '$projectTitle'.
Project Description: $description

Format: Return a JSON object with a 'questions' array (list of 5 strings).";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate 5 project defense (viva) questions for '$projectTitle'. Description: $description"]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);

        if ($response['success']) {
            $data = json_decode($response['content'], true);
            return [
                'success' => true,
                'questions' => $data['questions'] ?? []
            ];
        }

        return $response;
    }

    /**
     * Evaluate a student's Project Defense (Viva) answers.
     */
    public function evaluateProjectViva($projectTitle, $history) {
        $transcript = "";
        foreach ($history as $h) {
            $transcript .= "Q: {$h['question']}\nA: {$h['answer']}\n\n";
        }

        $systemPrompt = "You are a Senior Project Evaluator. 
Analyze the following Project Defense (Viva) for the project: '$projectTitle'.

Format: Return a JSON object with 'score' (0-100) and 'feedback' (string).";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Evaluate the following defense transcript:\n\n" . $transcript]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);

        if ($response['success']) {
            return json_decode($response['content'], true);
        }

        return $response;
    }

    /**
     * Get a Technical Question (Coding or Conceptual)
     */
    public function getTechnicalQuestion($role, $history, $concept = null) {
        $conceptContext = $concept ? " Specifically focus on the technical concept or role of: '**{$concept}**'." : "";
        $systemPrompt = "You are a Professional, Strict Technical Interviewer for the role of '{$role}'.{$conceptContext}
        
        OUTPUT FORMAT (JSON):
        {
            'type': 'conceptual' | 'coding',
            'feedback': '...',
            'question': '...',
            'problem_statement': '...',
            'constraints': '...',
            'test_cases': []
        }";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) { $messages[] = $msg; }

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);
        
        if ($response['success']) {
            return [
                'success' => true,
                'result' => json_decode($response['content'], true)
            ];
        }
        return $response;
    }

    /**
     * Evaluate Code Submission
     */
    public function evaluateCode($code, $language, $problemStatement) {
        $systemPrompt = "You are a Global Code Reviewer. Validate the student's code.
        
        OUTPUT (JSON):
        {
            'score': 0-10,
            'passed': true/false,
            'feedback': '...'
        }";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Problem: $problemStatement\n\nCode ($language):\n$code"]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);
    }

    /**
     * Get HR Question (Behavioral)
     */
    public function getHRQuestion($role, $history, $projects = [], $concept = null) {
        $conceptContext = $concept ? " The candidate is applying for a role specifically focused on: '**{$concept}**'." : "";
        $systemPrompt = "You are an Expert HR Manager conducting a behavioral interview for the role of '{$role}'.{$conceptContext}
        
        OUTPUT FORMAT (JSON):
        {
            'question': '...',
            'feedback': '...'
        }";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            if ($msg['role'] !== 'system') $messages[] = $msg;
        }

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);
    }

    /**
     * Generate HR Interview Report
     */
    public function generateHRReport($role, $history, $concept = null) {
        $conceptContext = $concept ? " The candidate was assessed for a role specifically focused on: '**{$concept}**'." : "";
        $systemPrompt = "You are a Senior Human Resources Director. Generate an assessment report for '{$role}'. {$conceptContext}
        
        OUTPUT FORMAT (JSON):
        {
            'overall_score': 0-100,
            'content': 'HTML report...'
        }";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            if ($msg['role'] !== 'system') $messages[] = $msg;
        }

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 4000
        ]);

        if ($response['success']) {
            $aiData = $response['parsed'];
            return [
                'success' => true,
                'content' => is_array($aiData) ? ($aiData['content'] ?? "Report content missing.") : $aiData,
                'overall_score' => is_array($aiData) ? ((int)($aiData['overall_score'] ?? 0)) : 0
            ];
        }

        return $response;
    }

    /**
     * Generate educational coding solutions
     */
    public function generateCodingSolution($problem) {
        $systemPrompt = "You are an expert coding instructor. You must return a response strictly formatted as a valid JSON object matching the following structure:
        {
          \"solutions\": {
            \"beginner\": {
              \"why_function\": \"Explain simply why a function approach is used here.\",
              \"plan\": [\"Step 1: ...\", \"Step 2: ...\"],
              \"variables\": [\"varName: purpose...\"],
              \"code\": {
                \"javascript\": \"JavaScript code here\",
                \"python\": \"Python code here\",
                \"java\": \"Java code here\",
                \"cpp\": \"C++ code here\"
              },
              \"why_logic\": \"Explain the core logic simply.\"
            },
            \"optimized\": {
              \"goal\": \"Explain the optimization goal.\",
              \"technique\": \"Explain the optimization technique.\",
              \"tradeoff\": \"Explain space/time tradeoffs.\",
              \"code\": {
                \"javascript\": \"Optimized JavaScript code here\",
                \"python\": \"Optimized Python code here\",
                \"java\": \"Optimized Java code here\",
                \"cpp\": \"Optimized C++ code here\"
              }
            }
          }
        }
        Ensure the output is pure JSON without any surrounding markdown wraps.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate coding solutions for this coding problem: {$problem['title']}"]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 2000
        ]);
    }

    /**
     * Analyze Fit for a Specific Role & Company
     */
    public function analyzeTargetFit($studentData, $targetRole, $targetCompany) {
        $systemPrompt = "You are a Recruitment Head at $targetCompany. Evaluate the candidate's student profile for the target role: '$targetRole'.
        
        You must return the response strictly formatted as a valid JSON object matching this schema:
        {
          \"fit_score\": 0-100,
          \"verdict\": \"Brief 1-2 sentence hiring verdict (e.g. Strongly Recommended, Potential Fit, Needs Upskilling)\",
          \"company_culture_alignment\": \"Brief analysis of how this candidate aligns with $targetCompany values and culture\",
          \"technical_alignment\": \"Brief analysis of technical match for '$targetRole'\",
          \"missing_critical_skills\": [\"Skill 1\", \"Skill 2\"],
          \"custom_advice\": \"Actionable preparation advice tailored for this specific role/company\",
          \"interview_prep_topics\": [\"Topic 1\", \"Topic 2\", \"Topic 3\"],
          \"requirement_match_chart\": {
            \"labels\": [\"Skill/Domain 1\", \"Skill/Domain 2\", \"Skill/Domain 3\", \"Domain 4\", \"Domain 5\"],
            \"possessed\": [80, 60, 45, 90, 70],
            \"required\": [90, 80, 80, 85, 75]
          }
        }
        Do not return any markdown wraps outside of valid JSON.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "STUDENT PROFILE:\n" . json_encode($studentData)]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);
    }

    public function predictCareerPath($studentData) {
        $systemPrompt = "You are an expert Career Path Architect. Analyze the student's profile to project their future career path.
        
        You must return the response strictly formatted as a valid JSON object matching this schema:
        {
          \"primary_path\": {
            \"title\": \"E.g. Full-Stack Developer / Data Engineer\",
            \"why\": \"Why this is the optimal role based on their projects/skills\",
            \"growth_potential\": \"High / Medium / Stable\",
            \"skill_alignment_chart\": {
              \"labels\": [\"Skill 1\", \"Skill 2\", \"Skill 3\", \"Skill 4\", \"Skill 5\"],
              \"student\": [90, 75, 80, 60, 85]
            }
          },
          \"long_term_projection\": \"A 5-year outlook summarizing growth trajectory and what they should aim for\",
          \"alternative_paths\": [
            {
              \"title\": \"Alternative Role 1\",
              \"why\": \"Why this is a viable secondary option\"
            },
            {
              \"title\": \"Alternative Role 2\",
              \"why\": \"Why this is a viable secondary option\"
            }
          ],
          \"ideal_job_titles\": [\"Job Title 1\", \"Job Title 2\", \"Job Title 3\"]
        }
        Do not return any markdown wraps outside of valid JSON.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "STUDENT PROFILE:\n" . json_encode($studentData)]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);
    }

    /**
     * Analyze Profile Match
     */
    public function analyzeProfileMatch($studentData, $company = null) {
        $companyContext = $company ? " Benchmark the candidate against the requirements of '$company'." : " Benchmark the candidate against global industry standards.";
        $systemPrompt = "You are an Elite Global Tech Career Strategist.{$companyContext}
        
        You must return the response strictly formatted as a valid JSON object matching this schema:
        {
          \"executive_summary\": \"A high-level summary of the student's market positioning and competitiveness.\",
          \"skill_distribution\": {
            \"labels\": [\"Core Tech\", \"Problem Solving\", \"System Design\", \"Communication\", \"Tooling\"],
            \"student_scores\": [75, 80, 50, 85, 65],
            \"market_avg\": [70, 75, 60, 80, 70]
          },
          \"market_benchmarks\": [
            {
              \"category\": \"Service-Based Companies\",
              \"match_percentage\": 85,
              \"missing_keys\": [\"DSA\", \"SQL\"]
            },
            {
              \"category\": \"Product-Based Startups\",
              \"match_percentage\": 60,
              \"missing_keys\": [\"React\", \"Node.js\", \"System Design\"]
            },
            {
              \"category\": \"Tier-1 Tech Giants\",
              \"match_percentage\": 40,
              \"missing_keys\": [\"Advanced DSA\", \"System Design\", \"Cloud Computing\"]
            }
          ],
          \"academic_vs_industry\": {
            \"labels\": [\"Sem 5\", \"Sem 6\", \"Sem 7\", \"Sem 8\", \"Industry Entry\"],
            \"student\": [50, 65, 75, 80, 85],
            \"industry_std\": [60, 70, 80, 90, 95]
          },
          \"role_fit_analysis\": [
            {
              \"role\": \"Software Engineer\",
              \"match\": 80
            },
            {
              \"role\": \"Frontend Engineer\",
              \"match\": 75
            },
            {
              \"role\": \"DevOps Engineer\",
              \"match\": 45
            }
          ],
          \"action_plan\": [
            {
              \"step\": \"Step 1\",
              \"priority\": \"Critical\",
              \"task\": \"Actionable detail on what they need to learn or improve.\",
              \"timeframe\": \"1 Month\"
            },
            {
              \"step\": \"Step 2\",
              \"priority\": \"High\",
              \"task\": \"Actionable detail on projects, open source, or internship prep.\",
              \"timeframe\": \"3 Months\"
            }
          ]
        }
        Do not return any markdown wraps outside of valid JSON.";
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "STUDENT PROFILE:\n" . json_encode($studentData)]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);
    }

    /**
     * Generate a detailed placement guide
     */
    public function getCompanyPlacementGuide($companyName, $studentDept = '') {
        $systemPrompt = "You are an Elite Placement Officer. Generate a guide for $companyName.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate a placement guide for $companyName."]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__
        ]);
    }

    /**
     * Recursive UTF-8 Sanitizer
     */
    private function utf8ize($mixed) {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $this->utf8ize($value);
            }
        } else if (is_string($mixed)) {
            return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
        }
        return $mixed;
    }

    /**
     * Mutate a batch of aptitude questions
     */
    public function mutateAptitudeBatch($questions) {
        $systemPrompt = "You are an Elite Assessment Logic Mutator. You must return the mutated questions strictly formatted as a valid JSON object.
        The JSON format must be an array of questions:
        {
            \"questions\": [
                {
                    \"question\": \"...\",
                    \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
                    \"answer\": 0-3,
                    \"explanation\": \"...\",
                    \"category\": \"...\"
                }
            ]
        }";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Mutate the following questions:\n" . json_encode($questions)]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);
    }

    /**
     * Mutate a coding challenge
     */
    public function mutateCodingChallenge($seedProblem, $studentContext = []) {
        $systemPrompt = "You are a Senior Technical Problem Architect. Your task is to take a seed coding problem and mutate it into a unique, fresh variation of similar difficulty.
        
        You must return a response strictly formatted as a valid JSON object matching the following structure:
        {
            \"title\": \"Name of the mutated problem\",
            \"problem_statement\": \"Detailed description of the mutated problem, including example inputs and outputs\",
            \"difficulty\": \"Easy / Medium / Hard\",
            \"constraints\": \"Any constraints on input size, complexity, etc.\",
            \"example_input\": \"Sample input string/data\",
            \"example_output\": \"Sample output string/data\"
        }
        Ensure the output is pure JSON without any surrounding markdown wraps.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Mutate coding problem: " . json_encode($seedProblem)]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'data' => $response['parsed']
            ];
        }
        return $response;
    }

    /**
     * Generate similar MCQ
     */
    public function generateSimilarQuestion($baseQuestion, $topic) {
        $systemPrompt = "You are an Elite Assessment Expert. Generate a similar question for the topic.
        You must return the response strictly formatted as a valid JSON object:
        {
            \"question\": \"...\",
            \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
            \"answer\": 0-3,
            \"explanation\": \"...\",
            \"category\": \"...\"
        }";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate a similar question for $topic based on this question: " . json_encode($baseQuestion)]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);
    }

    /**
     * Generate certification verification questions.
     */
    public function generateCertificationQuestions($certTitle, $issuer) {
        $systemPrompt = "You are a Technical Certification Auditor.
        Generate 5 verification questions for the certification '$certTitle' ($issuer).
        You must return the response strictly formatted as a valid JSON object:
        {
            \"questions\": [
                \"Question 1\",
                \"Question 2\",
                \"Question 3\",
                \"Question 4\",
                \"Question 5\"
            ]
        }";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate 5 questions for '$certTitle' ($issuer)"]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);
    }

    /**
     * Evaluate Certification Viva
     */
    public function evaluateCertificationViva($certTitle, $issuer, $transcript) {
        $systemPrompt = "You are a technical certification auditor.
        Evaluate the student's answers to the verification questions for the certification '$certTitle' ($issuer).
        You must return the response strictly formatted as a valid JSON object:
        {
            \"score\": 0-100,
            \"feedback\": \"Detailed feedback on the student's knowledge and verification status.\"
        }";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Evaluate the following transcript for $certTitle:\n\n$transcript"]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);
    }
}
