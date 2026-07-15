<?php
/**
 * AIService
 * Handles integration with OpenAI for Resume Analysis
 * DEBUG VERSION
 */

class AIService
{
    private $apiKey;
    private $apiUrl;
    private $model = 'gpt-4o-mini'; // High performance, low cost

    public function __construct()
    {
        $this->apiKey = OPENAI_API_KEY;
        $this->apiUrl = OPENAI_API_URL;

        if (empty($this->apiKey)) {
            logMessage("AIService initialized without API Key", 'WARNING');
        }
    }

    /**
     * Send a request to OpenAI
     */
    public function callAPI($messages, $options = [])
    {
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        // Connect timeout must be short: establishing a TCP/TLS connection should
        // take a couple of seconds, not 120. A large value let a single stalled
        // connection block for minutes on top of the read timeout.
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $maxRetries = 3;
        $attempt = 0;
        $response = false;
        $httpCode = 0;

        while ($attempt < $maxRetries) {
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = curl_errno($ch);

            // Retry on transient failures: server errors (5xx), rate limiting (429)
            // and request timeout (408). 429 is common under load and previously
            // fell straight through to failure ("code does not run at all").
            $isTransientHttp = $httpCode === 429 || $httpCode === 408 || ($httpCode >= 500 && $httpCode < 600);

            // Also retry transient cURL-level network errors (previously these
            // failed on the FIRST attempt while HTTP errors got 3 tries):
            // 6=DNS, 7=connect failed, 28=timeout, 35=SSL connect,
            // 52=empty reply, 55=send error, 56=recv error.
            $isTransientCurl = in_array($curlErrno, [6, 7, 28, 35, 52, 55, 56], true);

            if (($curlErrno === 0 && $isTransientHttp) || $isTransientCurl) {
                $attempt++;
                if ($attempt < $maxRetries) {
                    // Exponential backoff: 1s, 2s.
                    sleep($attempt);
                    continue;
                }
            }
            break;
        }

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'message' => "CURL Error: $error"];
        }

        curl_close($ch);
        $latency = (int) ((microtime(true) - $startTime) * 1000);

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
    private function auditLog($method, $model, $usage, $latency, $status, $error = null)
    {
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
    public function analyzeResume($resumeText, $targetRole = 'Software Engineer')
    {
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
    public function refineResumeAnalysis($structured, $scores, $targetRole = 'Software Engineer')
    {
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
    public function analyzeResumeSequence($userId, $resumeText, $targetRole = 'Software Engineer')
    {
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
    public function advancedATSAnalysis($resumeText, $jobDescription)
    {
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
    public function analyzeResumeWithJD($userId, $resumeText, $jobDescription)
    {
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
    public function getTechnicalInterviewResponse($domain, $history, $profile, $userMessage, $type = null, $projects = [], $aptitudeQuestions = [], $concept = null, $company = 'General', $difficulty = 'Medium')
    {
        $conceptContext = $concept ? " The candidate is applying for a role specifically focused on: '**{$concept}**'." : "";
        $sgpa = $profile['sgpa'] ?? 0;
        $randomSeed = substr(md5(microtime()), 0, 8);
        $difficultyInstruction = "Keep questions at a **Medium** difficulty level. Focus on conceptual understanding, moderate practical/coding scenarios, and basic debugging.";
        if (strtolower($difficulty) === 'low') {
            $difficultyInstruction = "Keep questions very **Simple and Beginner-Friendly** (Low difficulty). Focus on core concepts, easy definitions, and basic building blocks (freshman college exam level).";
        } elseif (strtolower($difficulty) === 'high') {
            $difficultyInstruction = "Make questions **Challenging and Advanced** (High difficulty). Focus on optimization, complex edge cases, system design, architectural trade-offs, and deep technical reasoning.";
        }

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

        $aptitudeContext = "\n\n=== APTITUDE ROUND INSTRUCTIONS ===
- You must dynamically generate 10 to 15 multiple-choice questions (MCQs) for the **Aptitude** round.
- **STRICT REQUIREMENT**: Every aptitude question must be directly based on the candidate's target concepts: '**{$concept}**' and tailored to the selected difficulty level (**{$difficulty}**).
- For each question, you must generate a clear question body and exactly 4 options (A, B, C, D).
- You may ONLY provide multiple-choice options (A, B, C, D) if you are currently in the **Aptitude** round. For Technical or HR rounds, you MUST ask open-ended questions based on the candidate's role and concepts, and NEVER provide options.
- If you switch to the Technical round, ensure you add the tag '[SHOW_WORKSPACE]' to your response once.";

        $initialInstruction = "The candidate is currently in the **{$type}** round. Begin asking questions according to the **{$type}** Question Flow below IMMEDIATELY. Do NOT ask them to choose a round unless they explicitly request to switch. **FLEXIBILITY:** If the user explicitly asks to switch rounds or skip to another section (e.g., 'Switch to Technical', 'I want to do HR now') at ANY point, you MUST immediately accommodate their request and begin the new round's flow.";

        $flow = [
            'Aptitude' => "10 to 15 dynamically generated MCQ questions based on the candidate's concepts: **{$concept}**.",
            'Technical' => "Open-ended questions (NO MCQs) tailored strictly to the candidate's concepts: **{$concept}**.
            INSTRUCTIONS: 
            - **For CS/IT:** 5 conceptual deep-dives followed by 5 coding challenges.
            - **For Circuit Branches (ECE/EEE/Robotics):** 5 conceptual deep-dives followed by 5 practical hardware/low-level logic or circuit design challenges.
            - **For Non-Technical (Civil, BCom, Mechanical):** 5 conceptual deep-dives followed by 5 practical industry scenarios or calculation problems (DO NOT ask for code).",
            'HR' => "Open-ended behavioral questions (NO MCQs). 5 questions focusing on situational logic, personal projects, and candidate's concepts: **{$concept}**."
        ];

        $flowItems = "";
        if ($type && isset($flow[$type])) {
            $flowItems .= "   - **$type**: {$flow[$type]}\n";
            foreach ($flow as $k => $v) {
                if ($k !== $type)
                    $flowItems .= "   - **$k**: $v\n";
            }
        } else {
            foreach ($flow as $k => $v) {
                $flowItems .= "   - **$k**: $v\n";
            }
        }

        $personalityPrompt = $this->getInterviewerPersonalityPrompt($company);

        $systemPrompt = "You are an Elite AI Technical Interviewer at GM University (Lakshya Placement Portal).
        
        {$personalityPrompt}
        
        {$conceptContext}
        $portfolioContext
                INTERVIEW STYLE:
- Be FRIENDLY, SUPPORTIVE, and ENCOURAGING. This is a practice interview to help students learn.
- Accept answers that are approximately correct or show the right idea — even if the wording is informal, short, or not using textbook terminology. If the core concept is right, mark it correct and gently suggest the professional phrasing.
- **CRITICAL: Voice Transcription Awareness** — Many students use the microphone button, and speech-to-text can produce garbled text with wrong words (e.g. I accuracy instead of high accuracy, functionalities instead of fluctuations). When evaluating answers, always look at the INTENT and KEY CONCEPTS mentioned, NOT the exact wording. If the student clearly knows the concept even if words are garbled, mark it as Correct.
- If an answer is correct or mostly correct, say 'Correct! ✅' and briefly explain the concept in simple terms.
- If wrong, say 'Not quite! ❌' — then explain the correct answer in simple, easy-to-understand language. Be kind, not harsh.
- Keep your responses SHORT and CONCISE. Do not write long paragraphs. 2-4 sentences for feedback is ideal.
- NO contradictory feedback. Use digits for all numbers.
- Encourage use of the '🎤 Speak' button for voice answers. Use English only.

INTERVIEW STRUCTURE:
1. **Initial**: $initialInstruction
2. **Question Flow**:
$flowItems
3. **Check-ins**: After completing the specified number of questions for a category (10 for Aptitude, 5 for Technical, 5 for HR), ask the candidate whether they want to continue or switch types (Aptitude, Technical, or HR).

RULES:
1. **Difficulty**: {$difficultyInstruction}
2. **Aptitude Focus**: When presenting an Aptitude question, you MUST dynamically generate the question and ALWAYS display the full text for all 4 options in your response. Use this EXACT format — never omit the text:
   ```
   A) [full text of option A]
   B) [full text of option B]
   C) [full text of option C]
   D) [full text of option D]
   ```
   Then ask the candidate to reply with the option letter (A, B, C, or D). **NEVER** show just the letters without the option text.
3. **Coding Challenges**: During coding challenges (Technical round):
   - **Multi-Language**: Let the candidate choose their language (Python, Java, C++, JavaScript, etc.).
   - **Examples**: Provide at least one **Example Input** and **Expected Output** for every coding task.
   - **Be Lenient**: If the code has minor issues but the logic is right, acknowledge the correct approach and gently point out the fix. Don't block progress over small syntax issues.
4. **Aptitude Response Logic**: 
   - **Step 1**: Identify the correct answer letter for the question you dynamically generated.
   - **Step 2**: Compare the candidate's response. Treat 'A', 'Option A', 'a', 'option a', or even the text of the option as a match.
   - **Step 3**: 
        - **IF MATCH**: Say 'Correct! ✅' and briefly explain why.
        - **IF NO MATCH**: Say 'Not quite! ❌'. State the correct letter and explain simply.
   - **Step 4**: Your label must match your explanation. No contradictions.
5. **Formatting**: Keep it clean — use **bold** for key terms. Keep questions short and clear. One question at a time.
6. **Domain Rotation**: Rotate through different topics (OS, Networking, DBMS, DSA, OOP, Web, etc.). Don't repeat the same topic.
7. **Skill Priority**: Ask questions related to the candidate's registered skills when possible.
8. **Randomization**: Never repeat questions. Ask ONE at a time. Use random seed: {$randomSeed}.
9. **Termination**: If user says 'stop' or 'end', add '[END_INTERVIEW]' at the very end.
10. **Adaptive Difficulty**: If the candidate struggles (2+ wrong answers in a row), make questions easier. If they're doing great, slightly increase difficulty. Always be encouraging.
11. **Short Answers Welcome**: Students may give very short answers (1-2 words, abbreviations, or spoken text via microphone). Accept these if they convey the right idea. Do NOT penalize brevity.";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            // OpenAI requires content to be a string — sanitize any objects/arrays
            if (isset($msg['content']) && !is_string($msg['content'])) {
                $msg['content'] = is_array($msg['content']) ? json_encode($msg['content']) : (string) $msg['content'];
            }
            // Skip system-only internal messages not relevant to OpenAI
            if (($msg['role'] ?? '') === 'system')
                continue;
            $messages[] = $msg;
        }
        if (!empty($userMessage)) {
            $messages[] = ['role' => 'user', 'content' => $userMessage];
        }

        return $this->callAPI($messages, ['audit_method' => __FUNCTION__, 'max_tokens' => 4000]);
    }

    /**
     * Generate a professional performance report after the interview
     */
    public function generateTechnicalInterviewReport($domain, $history, $type = 'Mock', $concept = null)
    {
        $conceptContext = $concept ? " The candidate was assessed for a role specifically focused on: '**{$concept}**'." : "";
        
        // Group history by assistant questions to merge and keep the candidate's best/longest response
        $groupedHistory = [];
        $seenQuestions = [];
        $currentQuestionKey = null;
        $systemEvaluations = [];
        
        foreach ($history as $msg) {
            $roleName = $msg['role'] ?? '';
            if ($roleName === 'system') {
                $content = $msg['content'] ?? '';
                if (strpos($content, 'Evaluation:') === 0 || strpos($content, 'Mutated Challenge:') === 0) {
                    $systemEvaluations[] = $msg;
                }
                continue;
            }
            
            if ($roleName === 'assistant') {
                $content = $msg['content'] ?? '';
                $parsed = @json_decode($content, true);
                $qText = ($parsed && !empty($parsed['question'])) ? $parsed['question'] : $content;
                $cleanQ = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($qText));
                
                if (empty($cleanQ)) {
                    continue;
                }
                
                $currentQuestionKey = $cleanQ;
                if (!isset($groupedHistory[$currentQuestionKey])) {
                    $groupedHistory[$currentQuestionKey] = [
                        'assistant_msg' => $msg,
                        'user_msgs' => []
                    ];
                }
            } else if ($roleName === 'user' && $currentQuestionKey !== null) {
                $groupedHistory[$currentQuestionKey]['user_msgs'][] = $msg;
            }
        }
        
        // Reconstruct pruned history by taking the best user response for each unique question
        $prunedHistory = [];
        // Put system evaluations first if any exist
        foreach ($systemEvaluations as $sysMsg) {
            $prunedHistory[] = $sysMsg;
        }
        
        foreach ($groupedHistory as $qKey => $group) {
            $prunedHistory[] = $group['assistant_msg'];
            
            $bestUserMsg = null;
            $maxLength = -1;
            
            foreach ($group['user_msgs'] as $uMsg) {
                $uContent = $uMsg['content'] ?? '';
                if (strpos($uContent, '[No response') !== false || strtolower(trim($uContent)) === 'skip') {
                    $len = -1;
                } else {
                    $len = strlen($uContent);
                }
                
                if ($len > $maxLength) {
                    $maxLength = $len;
                    $bestUserMsg = $uMsg;
                }
            }
            
            if ($bestUserMsg !== null) {
                $prunedHistory[] = $bestUserMsg;
            } else if (!empty($group['user_msgs'])) {
                $prunedHistory[] = $group['user_msgs'][0];
            }
        }

        $transcript = "";
        foreach ($prunedHistory as $msg) {
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
        - Be brutally honest, highly critical, and constructive. Do not sugarcoat.
        - Evaluate technical answers with high rigor: if the candidate gives generic, vague, or superficial explanations without using precise technical terminology (e.g. key terms, architectural concepts, exact keywords), penalize them heavily.
        - In the Technical section, if coding/practical tasks were presented and the candidate failed to write correct code or failed the evaluation, cap their Technical section score to a maximum of 4/10.
        - To prevent score inflation, DO NOT give overall scores above 80 unless the candidate demonstrated senior, industry-ready expertise with exact terminology and logic. Average, mediocre, or theoretical-only answers must receive scores between 40 and 60.
        - DO NOT hallucinate if transcript is empty.
        - CRITICAL ZERO-EFFORT PENALTY: If the candidate answered fewer than 3 unique questions in total, or if more than 70% of their responses were skips/empty/invalid (e.g., 'skip', 'I don't know', random letters), you MUST cap the 'overall_score' strictly below 20. Otherwise, if they attempted to answer the questions with relevant content, score them fairly based on the content of their answers (e.g., between 45 and 75 depending on quality) and DO NOT apply the zero-effort penalty.
        - ASR / SPEECH-TO-TEXT TRANSCRIPTION AWARENESS: Note that many user responses in the transcript are captured via automated speech recognition / voice-to-text. These transcripts may contain transcription errors, lack punctuation, lack capitalization, or contain typographical/homophonic errors (e.g. 'for loop' transcribed as 'four loop', 'SQL query' as 'sequel query'). DO NOT penalize the candidate for these speech-to-text conversion artifacts, lack of punctuation, or spelling/homophonic errors. Instead, focus entirely on the core technical concepts, programming logic, and the semantic substance/intent of their answers.
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
            'overall_score' => (int) $score
        ];
    }

    /**
     * Generate MCQs tailored to a specific Company
     */
    public function getCompanyAptitudeQuestions($companyName, $count = 4)
    {
        $systemPrompt = "You are an Elite Recruitment Paper Setter for $companyName. 
Generate $count high-quality, unique Multiple Choice Questions (MCQs) for a recruitment screening.

FOCUS: TECHNICAL APTITUDE / DOMAIN LOGIC for $companyName.

STRICT RULES FOR ACCURACY:
1. You must solve the question yourself step-by-step in the 'step_by_step_derivation' field before deciding the options or the answer index.
2. The correct answer MUST be mathematically, logically, and factually correct.
3. Read the question carefully to identify exactly what is being asked (e.g. if the question asks for 'girls', the correct answer must be the number of girls, not the number of boys). Ensure the answer index points to the value of the requested variable.
4. The correct answer MUST be present as one of the choices in the 'options' array.
5. The 'answer' index (0, 1, 2, or 3) MUST point exactly to the correct answer in the 'options' array.
6. Never generate a question where the correct answer is missing, incorrect, or closest-guess.
7. FORMAT: Return exactly $count questions in a JSON 'questions' array.
8. STRUCTURE: Each question object MUST follow this EXACT structure:
{
    \"question\": \"The clear question text here\",
    \"step_by_step_derivation\": \"Solve the question step-by-step with formulas and intermediate values to ensure 100% accuracy. Decide the correct answer based on this derivation.\",
    \"options\": [\"Option A text\", \"Option B text\", \"Option C text\", \"Option D text\"],
    \"answer\": 0, // 0-3
    \"explanation\": \"Brief explanation of why the answer is correct\",
    \"category\": \"Target Topic Name\"
}";

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

            // Apply self-correction verification pass
            $corrected = $this->selfCorrectQuestions($validQuestions);
            if (!empty($corrected) && is_array($corrected)) {
                $validQuestions = $corrected;
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
    public function generateSkillQuiz($skill, $level = 'Intermediate')
    {
        $systemPrompt = "You are a Technical Assessment Expert. 
Generate 10 high-quality Multiple Choice Questions (MCQs) to verify if a student actually knows the skill: '$skill'.

DIFFICULTY CALIBRATION (Level: $level):
- Beginner: Focus on fundamental syntax, basic concepts.
- Intermediate: Best practices, common libraries, debugging.
- Expert/Advanced: Architectural patterns, edge cases, internals.

CRITICAL RULES FOR ACCURACY:
1. You must solve the question yourself step-by-step in the 'step_by_step_derivation' field before deciding the options or the answer index.
2. The correct answer MUST be mathematically, logically, and factually correct.
3. Read the question carefully to identify exactly what is being asked (e.g. if the question asks for 'girls', the correct answer must be the number of girls, not the number of boys). Ensure the answer index points to the value of the requested variable.
4. The correct answer MUST be present as one of the choices in the 'options' array.
5. The 'answer' index (0, 1, 2, or 3) MUST point exactly to the correct answer in the 'options' array.
6. Never generate a question where the correct answer is missing, incorrect, or closest-guess.

Format: Return a JSON object with a 'questions' array.
Each question object MUST follow this EXACT structure:
{
    \"question\": \"The clear question text here\",
    \"step_by_step_derivation\": \"Solve the question step-by-step with formulas and intermediate values to ensure 100% accuracy. Decide the correct answer based on this derivation.\",
    \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"], 
    \"answer\": 0, // 0-3
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
            $rawQuestions = $data['questions'] ?? [];

            // Apply self-correction verification pass
            $corrected = $this->selfCorrectQuestions($rawQuestions);
            if (!empty($corrected) && is_array($corrected)) {
                $rawQuestions = $corrected;
            }

            return [
                'success' => count($rawQuestions) > 0,
                'questions' => array_slice($rawQuestions, 0, 10)
            ];
        }

        return $response;
    }

    /**
     * Generate 5 deep-dive 'Viva' questions to verify a student's project.
     */
    public function generateProjectViva($projectTitle, $description)
    {
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
    public function evaluateProjectViva($projectTitle, $history)
    {
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
    public function getTechnicalQuestion($role, $history, $concept = null, $portfolio = '', $previousQuestions = [], $difficulty = 'Medium')
    {
        $conceptsLabel = $concept ?: $role;

        $difficultyGuide = "Focus on intermediate-level scenarios — typical senior-student or entry-level developer problems.";
        if (strtolower($difficulty) === 'low') {
            $difficultyGuide = "Keep ALL questions beginner-friendly (freshman / junior-dev level). Focus on core definitions, simple examples, and basic logic — no optimization or system-design.";
        } elseif (strtolower($difficulty) === 'high') {
            $difficultyGuide = "Make ALL questions challenging (senior-dev / competitive programming level). Focus on optimization, edge cases, system design trade-offs, and deep architectural reasoning.";
        }

        $exclusionContext = "";
        if (!empty($previousQuestions)) {
            $exclusionContext = "\nCRITICAL: DO NOT ASK ANY OF THE FOLLOWING QUESTIONS (they have already been asked to this candidate):\n" . implode("\n- ", $previousQuestions) . "\n";
        }

        $systemPrompt = "You are a Professional Technical Interviewer.

CANDIDATE'S TARGET CONCEPTS: {$conceptsLabel}
DIFFICULTY: {$difficulty} — {$difficultyGuide}

STRICT CONCEPT RULE: Every single question — both conceptual AND coding — MUST be directly and specifically tied to the candidate's concepts listed above ({$conceptsLabel}). DO NOT ask about unrelated technologies, data structures, or domains. If the concept is 'React', ask only React-related questions. If the concept is 'SQL Joins', every question must test SQL Join knowledge.

LANGUAGE-AGNOSTIC RULE: Questions MUST be language-agnostic. Never say 'Write a Java program' or 'Explain this in Python'. Present the problem and let the student choose their language.

PORTFOLIO SKILLS & PROJECTS:
{$portfolio}

RANDOMIZATION: Pick varied, non-generic scenarios each time. Avoid trivial textbook examples.
{$exclusionContext}

IMPORTANT: Begin immediately with the first question — no greetings, no 'Are you ready?'.

OUTPUT FORMAT (JSON):
{
    \"type\": \"conceptual\" | \"coding\",
    \"feedback\": \"Evaluation feedback on previous answer (empty string for first question)...\",
    \"question\": \"The conceptual question or discussion prompt...\",
    \"problem_statement\": \"The coding challenge details (mandatory if type is coding)...\",
    \"constraints\": \"Constraints on time/space...\",
    \"test_cases\": []
}

CRITICAL: If the question asks to write, implement, or code something — set type to \"coding\" and fill \"problem_statement\". Only use \"conceptual\" for open discussions.";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            $messages[] = $msg;
        }

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'result' => $response['parsed'] ?? json_decode($response['content'], true)
            ];
        }
        return $response;
    }

    /**
     * Evaluate Code Submission
     */
    public function evaluateCode($code, $language, $problemStatement)
    {
        $systemPrompt = "You are a Global Code Reviewer. Validate the student's code.
        CRITICAL: The student is allowed to solve the problem in ANY language (they selected $language), even if the problem implicitly mentioned a different language (like Java). If their logic correctly solves the fundamental problem statement using $language, you MUST evaluate it as correct and give a high score. Do not penalize for using a different programming language than requested.
        
        ANTI-CHEAT / EMPTY SUBMISSION RULE: If the student submits empty code, code that only contains comments/problem statement, or completely irrelevant code that doesn't attempt to solve the problem, you MUST return a score of 0, set 'passed' to false, and provide feedback that no valid code was found. Do NOT hallucinate a solution.
        
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

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object']
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'result' => $response['parsed'] ?? json_decode($response['content'], true)
            ];
        }
        return $response;
    }

    /**
     * Get HR Question (Behavioral)
     */
    public function getHRQuestion($role, $history, $projects = [], $concept = null, $previousQuestions = [], $difficulty = 'Medium')
    {
        $conceptsLabel = $concept ?: $role;

        $difficultyGuide = "Ask moderately complex situational and behavioral questions — appropriate for campus placement rounds.";
        if (strtolower($difficulty) === 'low') {
            $difficultyGuide = "Ask simple, straightforward HR questions suitable for freshers and first-round screenings.";
        } elseif (strtolower($difficulty) === 'high') {
            $difficultyGuide = "Ask challenging HR questions involving leadership dilemmas, cross-functional conflict resolution, strategic thinking, and culture-fit deep dives.";
        }

        $portfolioContext = "";
        if (!empty($projects)) {
            $portfolioContext = "\nCANDIDATE'S PORTFOLIO / SKILLS / PROJECTS:\n";
            foreach ($projects as $idx => $p) {
                $num = $idx + 1;
                $portfolioContext .= "{$num}. Title: {$p['title']} (Tech: {$p['tech_stack']})\n   Description: {$p['description']}\n";
            }
        }

        $exclusionContext = "";
        if (!empty($previousQuestions)) {
            $exclusionContext = "\nCRITICAL: DO NOT ASK ANY OF THE FOLLOWING QUESTIONS (they have already been asked to this candidate):\n" . implode("\n- ", $previousQuestions) . "\n";
        }

        $randomSeed = substr(md5(microtime()), 0, 8);

        $systemPrompt = "You are an Expert HR Manager.

CANDIDATE'S TARGET CONCEPTS / TOPICS: {$conceptsLabel}
DIFFICULTY: {$difficulty} — {$difficultyGuide}

STRICT CONCEPT RULE: All behavioral questions MUST be rooted in the candidate's target concepts ({$conceptsLabel}). For example, if the concept is 'React', frame HR questions around working in React teams, React deadlines, debugging under pressure in a React project, etc. Do NOT ask purely generic HR questions disconnected from the concepts.
{$portfolioContext}

BEHAVIORAL VARIETY RULE:
Each question you ask must focus on a DIFFERENT behavioral/situational facet. Ensure you do not repeat the same theme (e.g., if a previous question was about deadlines, the next must focus on something else like conflict resolution, mentorship, adapting to new requirements, handling technical debt, receiving criticism, or communication failure).

RANDOMIZATION: Highly varied situational scenarios. Use random seed: {$randomSeed}.
{$exclusionContext}
CRITICAL: Do NOT ask the same question or a semantically/situationally similar question to any of the previously asked questions listed above. Make the new scenario structurally and situationally distinct.

IMPORTANT: Begin immediately with the first question — no 'Welcome to the HR round' or 'Are you ready?'.

OUTPUT FORMAT (JSON):
{
    \"question\": \"...\",
    \"feedback\": \"...\"
}";

        // Prune repetitive assistant messages from history to prevent pattern reinforcement loops
        $prunedHistory = [];
        $seenAssistantQuestions = [];
        foreach ($history as $msg) {
            if ($msg['role'] === 'system') {
                continue;
            }
            if ($msg['role'] === 'assistant') {
                $content = $msg['content'] ?? '';
                $parsed = @json_decode($content, true);
                $qText = ($parsed && !empty($parsed['question'])) ? $parsed['question'] : $content;
                $cleanQ = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($qText));
                if ($cleanQ && in_array($cleanQ, $seenAssistantQuestions)) {
                    // Skip duplicate assistant message
                    continue;
                }
                $seenAssistantQuestions[] = $cleanQ;
            }
            $prunedHistory[] = $msg;
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($prunedHistory as $msg) {
            $messages[] = $msg;
        }

        $cleanPrevious = array_map(function($q) {
            return preg_replace('/[^a-zA-Z0-9]/', '', strtolower($q));
        }, $previousQuestions);

        $maxAttempts = 3;
        $attempt = 0;
        $response = null;

        $stopWords = ['describe', 'time', 'faced', 'a', 'an', 'the', 'you', 'your', 'to', 'in', 'with', 'project', 'situation', 'where', 'tell', 'me', 'about', 'how', 'do', 'handle', 'had', 'was', 'were', 'have', 'has', 'been', 'is', 'are', 'of', 'for', 'on', 'at', 'by', 'this', 'that', 'these', 'those'];
        $cleanWords = function($str) use ($stopWords) {
            $str = preg_replace('/[^a-z0-9\s]/', '', strtolower($str));
            $words = preg_split('/\s+/', $str, -1, PREG_SPLIT_NO_EMPTY);
            return array_filter($words, function($w) use ($stopWords) {
                return !in_array($w, $stopWords);
            });
        };

        $isDuplicate = function($newQ) use ($previousQuestions, $cleanPrevious, $cleanWords) {
            $newQClean = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($newQ));
            if (empty($newQClean)) return true;
            
            if (in_array($newQClean, $cleanPrevious)) {
                return true;
            }
            
            $wNew = array_unique($cleanWords($newQ));
            if (empty($wNew)) return false;
            
            foreach ($previousQuestions as $prevQ) {
                similar_text(strtolower($newQ), strtolower($prevQ), $charPercent);
                if ($charPercent >= 75.0) {
                    return true;
                }
                
                $wPrev = array_unique($cleanWords($prevQ));
                if (empty($wPrev)) continue;
                
                $intersection = array_intersect($wNew, $wPrev);
                $union = array_unique(array_merge($wNew, $wPrev));
                $keywordPercent = (count($intersection) / count($union)) * 100.0;
                
                if ($keywordPercent >= 65.0) {
                    return true;
                }
            }
            return false;
        };

        while ($attempt < $maxAttempts) {
            $response = $this->callAPI($messages, [
                'audit_method' => __FUNCTION__,
                'response_format' => ['type' => 'json_object']
            ]);

            if ($response['success']) {
                $parsed = $response['parsed'] ?? json_decode($response['content'], true);
                $question = $parsed['question'] ?? '';
                
                if (!$isDuplicate($question)) {
                    // Unique question, return it
                    return [
                        'success' => true,
                        'result' => $parsed
                    ];
                }
                
                // Duplicate question generated, try again
                $messages[] = ['role' => 'assistant', 'content' => json_encode($parsed)];
                $messages[] = ['role' => 'user', 'content' => "You generated a duplicate or very similar question: '{$question}'. Please ask a completely different HR question testing a different behavioral scenario."];
                $attempt++;
            } else {
                break;
            }
        }

        // Return whatever response we got
        if ($response && $response['success']) {
            return [
                'success' => true,
                'result' => $response['parsed'] ?? json_decode($response['content'], true)
            ];
        }
        return $response;
    }

    /**
     * Generate HR Interview Report
     */
    public function generateHRReport($role, $history, $concept = null)
    {
        $conceptContext = $concept ? " The candidate was assessed for a role specifically focused on: '**{$concept}**'." : "";
        $systemPrompt = "You are a Senior Human Resources Director. Generate an assessment report for '{$role}'. {$conceptContext}
        
        STRICT RULES:
        - Be fair, objective, and constructive in your evaluation of communication, situational awareness, and cultural fit.
        - If the candidate gives short or simple answers, you may reduce their score, but do not fail them if they demonstrated relevant knowledge or experience. Highlight both key strengths and areas for improvement.
        - To prevent score inflation, reserve scores above 80 for outstanding, highly detailed responses. Average or standard student responses should receive realistic and fair scores between 45 and 75. Do not artificially fail students who put in a genuine attempt.
        - DO NOT hallucinate if transcript is empty.
        - CRITICAL ZERO-EFFORT PENALTY: If the candidate answered fewer than 3 unique questions in total, or if more than 70% of their responses were skips/empty/invalid (e.g., 'skip', 'I don't know', random letters), you MUST cap the 'overall_score' strictly below 20. Otherwise, if they attempted to answer the questions with relevant content, score them fairly based on the content of their answers (e.g., between 45 and 75 depending on quality) and DO NOT apply the zero-effort penalty.
        - ASR / SPEECH-TO-TEXT TRANSCRIPTION AWARENESS: Note that many user responses in the transcript are captured via automated speech recognition / voice-to-text. These transcripts may contain transcription errors, lack punctuation, lack capitalization, or contain typographical/homophonic errors (e.g. 'employees as a prediction' instead of 'employee salary prediction', 'preprosing' instead of 'preprocessing', 'court' instead of 'code'). DO NOT penalize the candidate for these speech-to-text conversion artifacts, lack of punctuation, or spelling errors. Instead, focus entirely on the core content, logical structure (like the STAR method: Situation, Task, Action, Result), and the semantic substance/intent of their answers.
        
        OUTPUT FORMAT (JSON):
        {
            'overall_score': 0-100,
            'content': 'HTML report...'
        }";

        // Group history by assistant questions to merge and keep the candidate's best/longest response
        $groupedHistory = [];
        $seenQuestions = [];
        $currentQuestionKey = null;
        
        foreach ($history as $msg) {
            $roleName = $msg['role'] ?? '';
            if ($roleName === 'system') {
                continue;
            }
            
            if ($roleName === 'assistant') {
                $content = $msg['content'] ?? '';
                $parsed = @json_decode($content, true);
                $qText = ($parsed && !empty($parsed['question'])) ? $parsed['question'] : $content;
                $cleanQ = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($qText));
                
                if (empty($cleanQ)) {
                    continue;
                }
                
                $currentQuestionKey = $cleanQ;
                if (!isset($groupedHistory[$currentQuestionKey])) {
                    $groupedHistory[$currentQuestionKey] = [
                        'assistant_msg' => $msg,
                        'user_msgs' => []
                    ];
                }
            } else if ($roleName === 'user' && $currentQuestionKey !== null) {
                $groupedHistory[$currentQuestionKey]['user_msgs'][] = $msg;
            }
        }
        
        // Reconstruct pruned history by taking the best user response for each unique question
        $prunedHistory = [];
        foreach ($groupedHistory as $qKey => $group) {
            $prunedHistory[] = $group['assistant_msg'];
            
            $bestUserMsg = null;
            $maxLength = -1;
            
            foreach ($group['user_msgs'] as $uMsg) {
                $uContent = $uMsg['content'] ?? '';
                if (strpos($uContent, '[No response') !== false || strtolower(trim($uContent)) === 'skip') {
                    $len = -1;
                } else {
                    $len = strlen($uContent);
                }
                
                if ($len > $maxLength) {
                    $maxLength = $len;
                    $bestUserMsg = $uMsg;
                }
            }
            
            if ($bestUserMsg !== null) {
                $prunedHistory[] = $bestUserMsg;
            } else if (!empty($group['user_msgs'])) {
                $prunedHistory[] = $group['user_msgs'][0];
            }
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($prunedHistory as $msg) {
            $messages[] = $msg;
        }

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 4000
        ]);

        if ($response['success']) {
            $aiData = $response['parsed'];
            $contentVal = "Report content missing.";
            $scoreVal = 0;

            if (is_array($aiData)) {
                $contentVal = $aiData['content'] ?? "Report content missing.";
                $scoreVal = (int) ($aiData['overall_score'] ?? 0);
            } else {
                $rawContent = $response['content'] ?? '';

                // Extract score via regex
                if (preg_match('/["\']overall_score["\']\s*:\s*"?(\d+)"?/i', $rawContent, $m)) {
                    $scoreVal = (int) $m[1];
                }

                // Extract content block or fallback to raw content
                if (preg_match('/["\']content["\']\s*:\s*"(.*)"\s*\}\s*$/s', $rawContent, $m)) {
                    $contentVal = $m[1];
                } elseif (preg_match('/["\']content["\']\s*:\s*\'(.*)\'\s*\}\s*$/s', $rawContent, $m)) {
                    $contentVal = $m[1];
                } else {
                    $contentVal = $rawContent;
                }
            }

            return [
                'success' => true,
                'content' => $contentVal,
                'overall_score' => $scoreVal
            ];
        }

        return $response;
    }

    /**
     * Generate educational coding solutions
     */
    public function generateCodingSolution($problem)
    {
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
    public function analyzeTargetFit($studentData, $targetRole, $targetCompany)
    {
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

    public function predictCareerPath($studentData)
    {
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
    public function analyzeProfileMatch($studentData, $company = null)
    {
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
    public function getCompanyPlacementGuide($companyName, $studentDept = '')
    {
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
    private function utf8ize($mixed)
    {
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
    public function mutateAptitudeBatch($questions)
    {
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
    public function mutateCodingChallenge($seedProblem, $studentContext = [])
    {
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
    public function generateSimilarQuestion($baseQuestion, $topic)
    {
        $systemPrompt = "You are an Elite Assessment Expert. Generate a similar question for the topic.
        CRITICAL RULES FOR ACCURACY:
        1. You must solve the question yourself step-by-step in the 'step_by_step_derivation' field before deciding the options or the answer index.
        2. The correct answer MUST be mathematically, logically, and factually correct.
        3. Read the question carefully to identify exactly what is being asked (e.g. if the question asks for 'girls', the correct answer must be the number of girls, not the number of boys). Ensure the answer index points to the value of the requested variable.
        4. The correct answer MUST be present as one of the choices in the 'options' array.
        5. The 'answer' index (0, 1, 2, or 3) MUST point exactly to the correct answer in the 'options' array.
        6. Never generate a question where the correct answer is missing, incorrect, or closest-guess.

        You must return the response strictly formatted as a valid JSON object:
        {
            \"question\": \"...\",
            \"step_by_step_derivation\": \"Solve the question step-by-step with formulas and intermediate values to ensure 100% accuracy. Decide the correct answer based on this derivation.\",
            \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
            \"answer\": 0, // 0-3
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
    public function generateCertificationQuestions($certTitle, $issuer)
    {
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
    public function evaluateCertificationViva($certTitle, $issuer, $transcript)
    {
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

    /**
     * Generate MCQ questions for a specific Campus Drive round based on selected topics
     */
    public function generateDriveRoundQuestions($roundType, $topics, $questionCount, $driveName = 'Company')
    {
        $systemPrompt = "You are an Elite Recruitment Question Architect for a recruitment drive named '$driveName'.
        Your task is to generate exactly $questionCount high-quality, professional, and unique Multiple Choice Questions (MCQs) for the **$roundType** round.
        
        The questions must target these specific topics: '$topics'.
        
        STRICT RULES FOR ACCURACY:
        1. You must solve the question yourself step-by-step in the 'step_by_step_derivation' field before deciding the options or the answer index.
        2. The correct answer MUST be mathematically, logically, and factually correct.
        3. Read the question carefully to identify exactly what is being asked (e.g. if the question asks for 'girls', the correct answer must be the number of girls, not the number of boys). Ensure the answer index points to the value of the requested variable.
        4. The correct answer MUST be present as one of the choices in the 'options' array.
        5. The 'answer' index (0, 1, 2, or 3) MUST point exactly to the correct answer in the 'options' array.
        6. Never generate a question where the correct answer is missing, incorrect, or closest-guess.
        7. Format: Return a JSON object with a single 'questions' array.
        8. Structure: Each question object MUST follow this EXACT structure:
        {
            \"question\": \"The clear question text here\",
            \"step_by_step_derivation\": \"Solve the question step-by-step with formulas and intermediate values to ensure 100% accuracy. Decide the correct answer based on this derivation.\",
            \"options\": [\"Option A text\", \"Option B text\", \"Option C text\", \"Option D text\"],
            \"answer\": 0, // 0 for A, 1 for B, 2 for C, 3 for D
            \"explanation\": \"Brief explanation of why the answer is correct\",
            \"category\": \"Target Topic Name\"
        }
        Do not include any markup like ```json in the raw response, return only valid JSON.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate $questionCount MCQs for the $roundType round on topics: '$topics'. Output JSON only."]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 4000,
            'temperature' => 0.5
        ]);

        if ($response['success']) {
            $data = $response['parsed'];
            if (isset($data['questions']) && is_array($data['questions'])) {
                return [
                    'success' => true,
                    'questions' => array_slice($data['questions'], 0, $questionCount)
                ];
            }
        }
        return ['success' => false, 'message' => $response['message'] ?? 'Failed to parse AI response.'];
    }

    /**
     * AI Coding Mentor Feedback Generator
     */
    public function getMentorFeedback($problem, $code, $language, $hintLevel, $requestType, $executionResult = '', $compilerOutput = '')
    {
        $systemPrompt = "You are an Elite AI Coding Mentor. Your core educational directive is:
- Teach instead of solve.
- Explain mistakes instead of correcting them.
- Encourage reasoning before revealing hints.
- Adapt to the student's skill level.
- Never provide the final solution unless the progressive hint level is strictly at Level 7.

Here is the problem context:
Title: " . $problem['title'] . "
Category: " . $problem['category'] . "
Difficulty: " . $problem['difficulty'] . "
Statement: " . $problem['problem_statement'] . "
Constraints: " . $problem['constraints'] . "
Example Input: " . $problem['example_input'] . "
Example Output: " . $problem['example_output'] . "
Expected Concept: " . $problem['concept_explanation'] . "

Student's Environment:
Language: $language
Code:
$code
Execution Result: $executionResult
Compiler/Console Output: $compilerOutput

Request Type: $requestType (analyze, hint, socratic, concept, complexity, reflection, trace, recommendation)
Current Hint Level: $hintLevel (Out of 7)

PROGRESSIVE HINT ENGINE RULES:
- Level 1: Clue on high-level conceptual direction (e.g. check loop boundary, check comparison).
- Level 2: Clue pointing out the exact problematic block or variable.
- Level 3: Highlight the logic flaw or boundary condition specifically.
- Level 4: Walkthrough/dry run trace of the code on sample input showing values.
- Level 5: Explain the algorithm conceptually.
- Level 6: Provide pseudocode ONLY. No actual code.
- Level 7: Provide the complete working code solution in $language with a detailed explanation.

You must return a response strictly formatted as a valid JSON object matching this schema:
{
  \"syntax_analysis\": {
    \"valid\": true,
    \"message\": \"Explain language syntax rules if they wrote invalid code, otherwise empty\",
    \"error_type\": \"\"
  },
  \"logic_analysis\": {
    \"valid\": true,
    \"message\": \"Explain logic issues like variable reassignment, loop bounds, off-by-one errors\",
    \"flaws\": []
  },
  \"runtime_analysis\": {
    \"infinite_loop\": false,
    \"index_out_of_bounds\": false,
    \"null_pointer\": false,
    \"message\": \"\"
  },
  \"algorithm_analysis\": {
    \"current_approach\": \"Description of how they are trying to solve it\",
    \"optimal_approach\": \"Description of the optimal approach\",
    \"advice\": \"Algorithmic advice\"
  },
  \"complexity_analysis\": {
    \"time\": \"e.g., O(n)\",
    \"space\": \"e.g., O(1)\",
    \"advice\": \"Explain time/space complexity and optimization potential\"
  },
  \"learning_feedback\": \"Firm, direct, encouraging coaching message\",
  \"concept_coach\": {
    \"needed\": false,
    \"topic\": \"Recursion, Pointers, Arrays, etc.\",
    \"explanation\": \"A short 100-word interactive explanation of the concept\",
    \"mini_quiz\": {
      \"question\": \"Multiple choice question checking understanding\",
      \"options\": [\"Option 0\", \"Option 1\", \"Option 2\", \"Option 3\"],
      \"answer\": 0,
      \"explanation\": \"Why it is correct\"
    }
  },
  \"socratic_questions\": [\"Ask a guiding question to lead student to spot the error\"],
  \"hint\": \"The progressive hint corresponding to the current Hint Level $hintLevel, if requested\",
  \"confidence_score\": 85,
  \"struggling_topics\": [],
  \"next_recommended_action\": \"\",
  \"execution_trace\": {
    \"variables\": [{\"name\": \"variable_name\", \"value\": \"value\"}],
    \"steps\": [{\"line\": 1, \"explanation\": \"Step detail\"}]
  },
  \"reflection\": {
    \"achievements\": [\"List of achievements\"],
    \"complexity\": \"Time & Space details\",
    \"concepts_learned\": [\"Concepts masterered\"],
    \"mistakes_fixed\": [\"Mistakes resolved\"],
    \"next_recommendation\": \"Next topic or problem name\"
  }
}
Return ONLY valid JSON. Do not wrap in ```json or any other formatting.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Perform analysis on the student's code. Hint level is $hintLevel. Request type is '$requestType'."]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 2500,
            'temperature' => 0.5
        ]);
    }

    /**
     * Simulate Code Execution conceptually via LLM
     */
    public function simulateCodeExecution($code, $language, $input = '', $practiceMode = 'learning')
    {
        $modePrompt = "";
        if ($practiceMode === 'learning') {
            $modePrompt = "The student is coding in Learning Mode. Their code only defines the core logical function (e.g. solve(input) or similar). The platform automatically appends a wrapper to read input parameters and execute the function, printing its return value to standard output. Do not expect standard input/output setup in their code; analyze the logic of their function against the input, and capture the returned value as stdout.";
        } else {
            $modePrompt = "The student is coding in Competitive Mode. Their code is a complete script that reads from standard input (stdin) and prints outputs to standard output (stdout).";
        }

        $systemPrompt = "You are a code execution engine. Your job is to compile and run the student's code in $language with the provided input parameters.
$modePrompt
If there are syntax errors, output them to stderr.
If the code compiles successfully, trace the execution of the code and print any console outputs (e.g. print statements, return values, standard output) to stdout.
If the program reads from standard input (stdin) (e.g. input() in Python, System.in / Scanner in Java, cin in C++), substitute the stdin reads with the values provided in the Input payload.
If the input payload has multiple lines or variables, map them to the stdin calls sequentially.
Trace the exact values printed by standard prints (stdout) and return them in the 'stdout' field.
Be careful not to confuse standard library functions with input data. For example, in Python, the function 'list()' is a built-in constructor. Even if the Input payload contains array/list strings like '[3, 7, 2, 9, 1]', the name 'list' refers to the built-in class/function and is fully callable. Do not throw 'list object is not callable' errors under any circumstances unless the student code explicitly shadows 'list' with a variable assignment (like 'list = ...'). Standard library capabilities are completely intact.
Do not provide explanations. Return ONLY a valid JSON object matching this schema:
{
  \"success\": true,
  \"stdout\": \"The standard output printed by the program\",
  \"stderr\": \"Any compiler errors or runtime exceptions\",
  \"exit_code\": 0
}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Code:\n$code\n\nInput:\n$input"]
        ];

        return $this->callAPI($messages, [
            'audit_method' => __FUNCTION__,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 1000,
            'temperature' => 0.1
        ]);
    }

    /**
     * Self-correct generated MCQ questions to ensure 100% accuracy.
     */
    private function selfCorrectQuestions($questions)
    {
        if (empty($questions) || !is_array($questions)) {
            return $questions;
        }

        $verificationPrompt = "You are an Elite MCQ Verification Agent.
Your task is to review a set of Multiple Choice Questions and verify that their correct answer index (0-3) is mathematically, logically, and factually correct.

For each question:
1. Solve it independently.
2. Read the question carefully to identify exactly what is being asked (e.g. if the question asks for 'girls', the correct answer must be the number of girls, not the number of boys). Ensure the answer index points to the value of the requested variable.
3. Verify that the correct option index (0, 1, 2, or 3) points EXACTLY to the correct choice in the 'options' array.
4. If it does not, CORRECT the 'answer' field (0-3 index) and update the 'explanation' to explain the math/logic accurately.
5. If the options array itself does not contain the mathematically correct option, you must replace the wrong option in the options array with the correct value, and update the answer index to point to it.
6. Make sure the explanation is accurate and matches the corrected answer option.

Return the modified/verified questions in a JSON object with a 'questions' array. Output ONLY the JSON. Do not wrap in markdown code blocks.";

        $messages = [
            ['role' => 'system', 'content' => $verificationPrompt],
            ['role' => 'user', 'content' => json_encode(['questions' => $questions])]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => 'self_correct_questions',
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 3000,
            'temperature' => 0.0 // Deterministic correctness
        ]);

        if ($response['success']) {
            $data = is_array($response['parsed']) ? $response['parsed'] : json_decode($response['content'], true);
            if (isset($data['questions']) && is_array($data['questions'])) {
                return $data['questions'];
            }
        }

        return $questions;
    }

    /**
     * Solve and automatically fix a reported question using AI.
     */
    public function autoFixReportedQuestion($questionText, array $options)
    {
        $optsText = "";
        foreach ($options as $idx => $opt) {
            $optsText .= chr(65 + $idx) . ") " . $opt . "\n";
        }

        $systemPrompt = "You are an Elite MCQ Verification Agent.
We have a reported question that may have an incorrect answer key or formatting issues.

Question: {$questionText}
Options:
{$optsText}

Task:
1. Solve the question step-by-step.
2. Determine which option (A, B, C, or D) is mathematically, logically, and factually correct.
3. If none of the options is correct, identify the closest option or select 'A' and provide a corrected option text. But if one of them is correct, select it.
4. Ensure the correct_option field holds exactly 'A', 'B', 'C', or 'D'.
5. Provide a clear explanation of the mathematical/logical solution.

Format: Return a JSON object with this structure:
{
  \"correct_option\": \"A\", // A, B, C, or D
  \"explanation\": \"Step-by-step proof of the correct answer...\"
}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Verify and find the correct option index for this question. Return only JSON."]
        ];

        $response = $this->callAPI($messages, [
            'audit_method' => 'auto_fix_reported_question',
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 1000,
            'temperature' => 0.0 // Deterministic
        ]);

        if ($response['success']) {
            $data = is_array($response['parsed']) ? $response['parsed'] : json_decode($response['content'], true);
            if (isset($data['correct_option'])) {
                return [
                    'success' => true,
                    'correct_option' => strtoupper(trim($data['correct_option'])),
                    'explanation' => $data['explanation'] ?? ''
                ];
            }
        }

        return ['success' => false, 'message' => 'AI was unable to resolve this question.'];
    }

    public function getInterviewerPersonalityPrompt($companyName)
    {
        $comp = strtolower($companyName);
        if (strpos($comp, 'amazon') !== false) {
            return "INTERVIEWER PERSONALITY (Amazon Style):
            - Fast paced and direct.
            - Challenge assumptions and ask 'Why?' repeatedly for technical choices (e.g., 'Why choose that data structure over this one?').
            - Press the candidate on metrics, scale, efficiency, and trade-offs. Avoid overly supportive phrasing.
            - Focus heavily on Amazon Leadership Principles (e.g. Customer Obsession, Ownership, Bias for Action, Deep Dive, Insist on High Standards) when evaluating.";
        } else if (strpos($comp, 'google') !== false) {
            return "INTERVIEWER PERSONALITY (Google Style):
            - Friendly, conversational, and highly intellectual.
            - Encourage candidate to think aloud and explain their thought process before coding.
            - Ask deep technical follow-ups about system internals, scale, algorithmic bounds, and complexity.
            - Focus on Googliness, open-ended problem solving, and role-related knowledge.";
        } else if (strpos($comp, 'microsoft') !== false) {
            return "INTERVIEWER PERSONALITY (Microsoft Style):
            - Collaborative, mentor-like, and architecture-focused.
            - Focus on modular design, clean code, design patterns, and edge cases.
            - If the candidate gets stuck or struggles, provide subtle architectural hints rather than direct answers.
            - Probe their understanding of scalability, solid principles, and code robustness.";
        } else if (strpos($comp, 'tcs') !== false || strpos($comp, 'tata consultancy') !== false) {
            return "INTERVIEWER PERSONALITY (TCS Style):
            - Professional, structured, and polite.
            - Move steadily through questions without spending too much time digging into a single detail.
            - Focus on core fundamental concepts, terminology, and industry standard practices.";
        } else if (strpos($comp, 'infosys') !== false) {
            return "INTERVIEWER PERSONALITY (Infosys Style):
            - Formal, resume-driven, and traditional.
            - Base questions heavily on the candidate's declared portfolio skills, projects, and SGPA.
            - Maintain a very formal tone and focus on how candidates apply their academic skills.";
        } else {
            return "INTERVIEWER PERSONALITY (Direct/Standard):
            - Professional, strict, and direct.
            - Maintain high standards for clarity, depth, and correctness.
            - Avoid friendly chatter; focus purely on evaluating competence.";
        }
    }
}