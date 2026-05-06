<?php
/**
 * AIService
 * Handles integration with OpenAI for Resume Analysis
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
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'AI Service not configured (Missing API Key)'];
        }

        $data = array_merge([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1000
        ], $options);

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
        // Fix for XAMPP/Localhost SSL/Timeout issues
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutes timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120); // Increased to 120s
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Force IPv4 to avoid IPv6 resolution timeouts

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'message' => "CURL Error: $error"];
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success' => false, 'message' => "API Error (Code $httpCode): " . $response];
        }

        $result = json_decode($response, true);
        $content = $result['choices'][0]['message']['content'] ?? '';

        return [
            'success' => true,
            'content' => $content,
            'usage' => $result['usage'] ?? []
        ];
    }

    /**
     * Analyze Resume Text
     */
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
     * Refine Resume Analysis using AI
     * 
     * @param array $structured Parsed structured resume data
     * @param array $scores Rule-based scores
     * @param string $targetRole The target job role
     * @return array
     */
    public function refineResumeAnalysis($structured, $scores, $targetRole) {
        $systemPrompt = "You are an expert HR recruiter and technical hiring manager reviewing a resume for the role of '{$targetRole}'.
        
        You have received the parsed resume and some initial rule-based scores. Your job is to provide specific, highly targeted qualitative feedback to improve this resume's impact.
        
        Provide the response strictly as a JSON object with the following structure:
        {
            \"qualitative_summary\": \"A brutally honest, recruiter-level summary of the resume (2-3 sentences).\",
            \"layout_audit\": \"Feedback on sections that might be missing or poorly structured based on the data. Leave empty if fine.\",
            \"impact_phrases_to_use\": [\"Action verb phrase 1\", \"Action verb phrase 2\", \"...\"],
            \"bullet_surgery\": [
                {
                    \"original\": \"The weakest original bullet point from their experience or projects\",
                    \"suggested\": \"The rewritten, high-impact version using metrics and strong action verbs\",
                    \"why\": \"A brief explanation of why the new version is better (e.g., 'Quantifies impact' or 'Highlights specific tech')\"
                }
            ],
            \"strategic_advice\": [\"Actionable step 1\", \"Actionable step 2\", \"...\"],
            \"score_adjustment\": -5 to 5 // Optional adjustment to the rule-based score
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
     * Implements Keyword matching, Experience relevance, Project relevance, and Formatting clarity.
     * 
     * @param string $resumeText Raw resume text
     * @param string $jobDescription Raw job description text
     * @return array
     */
    public function advancedATSAnalysis($resumeText, $jobDescription) {
        $systemPrompt = "You are an advanced ATS (Applicant Tracking System) resume analyzer designed to evaluate student resumes with strict, logic-based criteria.

You do NOT behave like a human recruiter. You behave like a deterministic system focused on keyword matching, structure validation, and impact analysis.

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
- Do NOT invent data
- If missing, leave empty
- Normalize skills to lowercase

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
Rules:
- Prioritize frequently repeated terms
- Ignore generic words like \"good\", \"team\", etc.

-----------------------------------
STEP 3: MATCHING ENGINE
-----------------------------------
Perform strict comparison:
1. Exact keyword match
2. Context match:
   - Skill present in skills section only → weak
   - Skill used in experience/projects → strong

Output:
{
  \"matched_keywords\": [],
  \"missing_keywords\": [],
  \"weak_matches\": []
}

-----------------------------------
STEP 4: SCORING SYSTEM
-----------------------------------
Calculate ATS score (0–100) using:
- 40% keyword match
- 30% experience relevance
- 20% project relevance
- 10% formatting clarity

Penalties:
- -10 for vague bullets (no action verb)
- -10 for no measurable impact
- -15 for keyword stuffing
- -10 for inconsistent structure

-----------------------------------
STEP 5: QUALITY CHECKS
-----------------------------------
Detect:
1. Fluff words: [\"hardworking\", \"passionate\", \"team player\"]
2. Fake skills: Skill listed but not used anywhere
3. Role confusion: Too many unrelated domains
4. Weak bullets: No action verb, No measurable result

-----------------------------------
STEP 6: BULLET IMPROVEMENT ENGINE
-----------------------------------
Rewrite weak bullets using:
Format: [ACTION VERB] + [WHAT YOU DID] + [TECH USED] + [IMPACT/METRIC]

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
  \"issues\": [],
  \"red_flags\": [],
  \"suggestions\": [],
  \"improved_bullets\": [
    {
      \"original\": \"\",
      \"improved\": \"\"
    }
  ]
}

RULES:
- Never hallucinate experience or skills
- Be strict and critical in scoring
- Prefer exact keyword matching
- Penalize vague and generic resumes heavily
- Output must ALWAYS be valid JSON";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "RESUME TEXT:\n$resumeText\n\nJOB DESCRIPTION:\n$jobDescription"]
        ];

        $response = $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2 // Lower temperature for more deterministic output
        ]);

        if ($response['success']) {
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
     * Persona: Rude, Direct, Expert Phrasing
     * Question Types: Aptitude, Technical, HR (Student chooses)
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

        // Reorder flow to put selected type first if available
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
        foreach ($history as $msg) { $messages[] = $msg; }
        if (!empty($userMessage)) { $messages[] = ['role' => 'user', 'content' => $userMessage]; }

        return $this->callAPI($messages);
    }

    /**
     * Generate a professional performance report after the interview
     */
    public function generateTechnicalInterviewReport($domain, $history, $type = 'Mock', $concept = null)
    {
        $conceptContext = $concept ? " The candidate was assessed for a role specifically focused on: '**{$concept}**'." : "";
        // Extract only content from history for the transcript
        $transcript = "";
        foreach ($history as $msg) {
            $role = ucfirst($msg['role']);
            $content = $msg['content'];

            // Convert raw JSON evaluations into human-readable text for the report AI
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
            'max_tokens' => 4000,
            'response_format' => ['type' => 'json_object']
        ]);
        
        if (!$response['success']) {
            return $response;
        }

        $contentStr = $response['content'];
        $aiData = json_decode($contentStr, true);
        
        $reportText = $aiData['content'] ?? $contentStr;
        $score = $aiData['overall_score'] ?? 0;

        // Fallback score extraction if JSON content is weird
        if ($score === 0) {
            preg_match('/"overall_score":\s*"?(\d+)"?/i', $contentStr, $m);
            $score = isset($m[1]) ? (int)$m[1] : 0;
        }
        
        // Final fallback: Look for any number before % or in sectional scores
        if ($score === 0) {
            preg_match('/(\d+)\s*%/', $reportText, $matches);
            $score = isset($matches[1]) ? (int)$matches[1] : 0;
            
            if ($score === 0) {
                preg_match_all('/Score:\s*(\d+)\s*\/\s*10/i', $reportText, $scoreMatches);
                if (!empty($scoreMatches[1])) {
                    $avg = array_sum($scoreMatches[1]) / count($scoreMatches[1]);
                    $score = (int)($avg * 10);
                }
            }
        }

        return [
            'success' => true,
            'content' => $reportText,
            'overall_score' => $score
        ];
    }

    /**
     * Generate MCQs tailored to a specific Company's industry standards
     */
    public function getCompanyAptitudeQuestions($companyName, $count = 4) {
        $systemPrompt = "You are an Elite Recruitment Paper Setter for $companyName. 
Generate $count high-quality, unique Multiple Choice Questions (MCQs) for a recruitment screening.

FOCUS: TECHNICAL APTITUDE / DOMAIN LOGIC for $companyName.
- Include pseudo-code logic, tech fundamentals, or analytical data logic.
- Keep it 50% General Aptitude and 50% Company/Industry specific logic.

STRICT RULES:
1. MATH ACCURACY: All calculations must be perfect.
2. FORMAT: Return exactly $count questions in a JSON 'questions' array.
3. STRUCTURE: Each question MUST have: 'question', 'options' (array of 4), 'answer' (0-3), 'explanation' (1 short line), and 'category'.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate $count technical/logic MCQs for $companyName. Output JSON only."]
        ];

        $response = $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 3000
        ]);

        if ($response['success']) {
            $data = json_decode($response['content'], true);
            $rawQuestions = $data['questions'] ?? [];
            
            $validQuestions = [];
            foreach ($rawQuestions as $q) {
                if (empty($q['question']) || empty($q['options']) || !is_array($q['options']) || count($q['options']) < 2) {
                    continue;
                }
                
                if (count($q['options']) === 1 && strpos($q['options'][0], "\\n") !== false) {
                    $q['options'] = array_values(array_filter(explode("\\n", $q['options'][0])));
                }
                
                if (count($q['options']) < 4) {
                    while(count($q['options']) < 4) $q['options'][] = "Option " . (count($q['options']) + 1);
                }
                $q['options'] = array_slice($q['options'], 0, 4);

                // Ensure answer index is valid (0-3)
                if (!isset($q['answer']) || $q['answer'] < 0 || $q['answer'] > 3) {
                    $q['answer'] = 0; 
                }

                $validQuestions[] = $q;
            }

            return [
                'success' => count($validQuestions) > 0,
                'questions' => array_slice($validQuestions, 0, $count),
                'message' => count($validQuestions) > 0 ? '' : 'Failed to generate valid questions.'
            ];
        }

        return $response;
    }

    /**
     * Generate 10 MCQs to verify a student's proficiency in a specific skill.
     * Calibrates difficulty based on reported level.
     */
    public function generateSkillQuiz($skill, $level = 'Intermediate') {
        $systemPrompt = "You are a Technical Assessment Expert. 
Generate 10 high-quality Multiple Choice Questions (MCQs) to verify if a student actually knows the skill: '$skill'.

DIFFICULTY CALIBRATION (Level: $level):
- Beginner: Focus on fundamental syntax, basic concepts, and common terms.
- Intermediate: Focus on best practices, common libraries, debugging, and slightly complex logic.
- Expert/Advanced: Focus on architectural patterns, edge cases, performance optimization, and deep technical internals.

STRICT RULES:
1. NO TRIVIAL QUESTIONS: Avoid surface-level questions that can be googled in 2 seconds.
2. ACCURACY: Ensure the 'answer' index (0-3) exactly matches the correct option.
3. RANDOMIZATION: CRITICAL - Randomize the index of the correct answer (0-3) across the 10 questions. Do NOT consistently place the correct answer at index 0 or any single index. Verify that the correct answer is distributed across A, B, C, and D.
4. EXPLANATION: Provide a clear, technical explanation for the correct answer.
5. VARIETY: Cover different sub-topics of the skill (e.g., if Java: cover OOP, Collections, Exception Handling, etc.).

Format: Return a JSON object with a 'questions' array.
Each question object MUST follow this EXACT structure:
{
    \\\"question\\\": \\\"The clear question text here\\\",
    \\\"options\\\": [\\\"Option A\\\", \\\"Option B\\\", \\\"Option C\\\", \\\"Option D\\\"], 
    \\\"answer\\\": 0,
    \\\"explanation\\\": \\\"Brief clear explanation\\\"
}
CRITICAL: Options MUST be a unique array of exactly 4 strings. Do NOT combine them into one string.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate a 10-question verification quiz for '$skill' at the $level level. Ensure all fields are populated and options are distinct."]
        ];

        $response = $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 3000
        ]);

        if ($response['success']) {
            $data = json_decode($response['content'], true);
            $rawQuestions = $data['questions'] ?? [];
            
            // Validation & Sanitization
            $validQuestions = [];
            foreach ($rawQuestions as $q) {
                if (empty($q['question']) || empty($q['options']) || !is_array($q['options']) || count($q['options']) < 2) {
                    continue;
                }
                
                // If options are combined or broken, try a basic fix or skip
                if (count($q['options']) === 1 && strpos($q['options'][0], "\\n") !== false) {
                    $q['options'] = array_values(array_filter(explode("\\n", $q['options'][0])));
                }
                
                // Ensure exactly 4 options by padding or slicing if needed (though AI should handle 4)
                if (count($q['options']) < 4) {
                    while(count($q['options']) < 4) $q['options'][] = "Option " . (count($q['options']) + 1);
                }
                $q['options'] = array_slice($q['options'], 0, 4);

                // Ensure answer index is valid (0-3)
                if (!isset($q['answer']) || $q['answer'] < 0 || $q['answer'] > 3) {
                    $q['answer'] = 0; 
                }

                $validQuestions[] = $q;
            }

            return [
                'success' => count($validQuestions) > 0,
                'questions' => array_slice($validQuestions, 0, 10),
                'message' => count($validQuestions) > 0 ? '' : 'Failed to generate valid questions.'
            ];
        }

        return $response;
    }

    /**
     * Generate 5 deep-dive 'Viva' questions to verify a student's project.
     * Focuses on architectural decisions and technical choices.
     */
    public function generateProjectViva($projectTitle, $description) {
        $systemPrompt = "You are a Senior Project Evaluator. 
Generate 5 deep-dive, analytical questions for a student to 'defend' their project: '$projectTitle'.
Project Description: $description

GOALS:
1. Verify if the student actually built the project.
2. Test their understanding of architectural decisions.
3. Probe their knowledge of the technologies used.
4. Ask about challenges and how they were solved.

STRICT RULES:
1. NO GENERIC QUESTIONS: Avoid 'What is this project?' or 'What language did you use?'.
2. TECHNICAL FOCUS: Ask about data flow, scalability, security, or specific implementation details.
3. ADAPTIVE: Base questions strictly on the project context provided.

Format: Return a JSON object with a 'questions' array (list of 5 strings).";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate 5 project defense (viva) questions for '$projectTitle'. Description: $description"]
        ];

        $response = $this->callAPI($messages, [
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

EVALUATION CRITERIA:
1. Technical Depth: Does the student demonstrate a deep understanding of the technologies used?
2. Authenticity: Do the answers suggest they actually built the project?
3. Clarity: How well did they explain their architectural decisions?

STRICT RULES:
1. Be critical but fair.
2. Provide a score out of 100.
3. Provide a brief (2-3 sentence) constructive feedback.

Format: Return a JSON object with 'score' (0-100) and 'feedback' (string).";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Evaluate the following defense transcript:\n\n" . $transcript]
        ];

        $response = $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object']
        ]);

        if ($response['success']) {
            return json_decode($response['content'], true);
        }

        return $response;
    }

    /**
     * Get a Technical Question (Coding or Conceptual) for the new Technical Round
     */
    public function getTechnicalQuestion($role, $history, $concept = null) {
        $conceptContext = $concept ? " Specifically focus on the technical concept or role of: '**{$concept}**'." : "";
        $systemPrompt = "You are a Professional, Strict Technical Interviewer for the role of '{$role}'.{$conceptContext}
        
        YOUR GOAL: Conduct a high-quality technical screening. 
        
        INSTRUCTIONS:
        1. **FLOW (Crucial):** 
           - Evaluate the user's previous answer and provide brief, constructive feedback.
           - **Immediately** ask a NEW, DIFFERENT question in the same response. Do NOT repeat questions or ask vague follow-ups. Keep the interview moving forward.
        2. **QUESTIONING:**
           - Start with conceptual questions.
           - Progressively increase difficulty.
           - **For Technical Roles (CS/IT/Software):** Follow the coding challenge flow.
           - **For Circuit Branches (ECE/EEE/Robotics):** Present a mix of **Low-level Coding (C/Assembly/Verilog)** and **Circuit Design Scenarios**. Use the code workspace for hardware-related logic if appropriate.
           - **For Non-Technical Roles (Civil, BCom, Mechanical, etc.):** Instead of 'coding', present a **'Practical Scenario'** or **'Complex Calculation'**. Ask: \"The next part is a practical industry scenario. Are you ready?\"
           - ONLY AFTER the user replies \"yes\" or \"ready\", output `type: 'coding'` (for technical/circuit) or `type: 'conceptual'` (for non-technical) with the actual challenge.
        4. **PRACTICAL CHALLENGES:**
           - **Technical/Circuit:** Provide a Detailed Problem Statement, Constraints, and Examples. For ECE/EEE, focus on Embedded Systems, VLSI, or Signal Processing logic.
           - **Non-Technical:** Provide a **Case Study, Site Scenario, or Financial Problem**. Ask for specific steps, calculations, or justifications. Do NOT force them to write code if the role is non-technical (e.g., Taxation, Site Engineering).
           - The 'question' field should just be a short heading.
        5. **FORMATTING:**
           - Use **Markdown** for clarity. Break long texts into paragraphs.
        
        OUTPUT FORMAT (JSON):
        {
            'type': 'conceptual' | 'coding',
            'feedback': 'State if user was CORRECT or INCORRECT. Explain briefly why.',
            'question': 'ASK A BRAND NEW TECHNICAL QUESTION OR SCENARIO. Do not repeat previous questions.',
            // IF CODING:
            'problem_statement': 'Detailed description with examples...',
            'constraints': 'Time: O(n), Space: O(1)...',
            'test_cases': [
                {'input': '...', 'output': '...'}
            ]
        }";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            $messages[] = $msg;
        }

        $response = $this->callAPI($messages, ['response_format' => ['type' => 'json_object']]);
        
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
        $systemPrompt = "You are a Global Code Reviewer. Validate the student's code against the problem statement.
        
        CRITERIA:
        1. Correctness (Passes all edge cases?) - 50%
        2. Time/Space Complexity - 30%
        3. Code Quality (Variables, Indentation) - 20%
        
        OUTPUT (JSON):
        {
            'score': 0-10,
            'passed': true/false,
            'feedback': 'Detailed feedback...',
            'output_log': 'Simulated execution output...'
        }";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Problem: $problemStatement\n\nCode ($language):\n$code"]
        ];

        return $this->callAPI($messages, ['response_format' => ['type' => 'json_object']]);
    }


    /**
     * Get HR Question (Behavioral)
     */
    public function getHRQuestion($role, $history, $projects = [], $concept = null) {
        $conceptContext = $concept ? " The candidate is applying for a role specifically focused on: '**{$concept}**'." : "";
        // Build project context for AI
        $projectContext = "";
        if (!empty($projects)) {
            $projectContext = "\n\n=== CANDIDATE'S PROJECTS ===\n";
            foreach ($projects as $idx => $proj) {
                $num = $idx + 1;
                $projectContext .= "{$num}. **{$proj['title']}**\n";
                if (!empty($proj['tech_stack'])) {
                    $projectContext .= "   Tech Stack: {$proj['tech_stack']}\n";
                }
                if (!empty($proj['description'])) {
                    $projectContext .= "   Description: {$proj['description']}\n";
                }
                $projectContext .= "\n";
            }
            
            $projectContext .= "IMPORTANT INSTRUCTIONS FOR PROJECT QUESTIONS:
- Ask specific questions about their technology choices (e.g., 'Why did you choose React over Angular for {project_name}?')
- Probe into design decisions (e.g., 'What made you decide to use MongoDB instead of MySQL?')
- Ask about challenges faced (e.g., 'What was the biggest technical challenge in {project_name}?')
- Inquire about their role (e.g., 'Were you the sole developer or part of a team?')
- Ask about scalability, security, or performance considerations
- Mix 60% project-based questions with 40% standard behavioral questions
- Reference specific projects by name when asking questions\n";
        } else {
            $projectContext = "\n\n=== NO PROJECTS REGISTERED ===
INSTRUCTIONS:
1. Since no projects are listed in the candidate's profile, YOUR FIRST PRIORITY (after introduction) is to ask the candidate to describe a technical project they have worked on recently.
2. Once they describe a project, you must GO DEEP into their response.
3. Ask about their tech stack, their specific role, the biggest obstacle they faced, and what they would do differently now.
4. Don't let them give vague answers; if they say 'I built a website', ask 'What was the backend architecture? How did you handle state management?'
5. Keep probing the same project until you are satisfied with their technical depth before moving to standard HR questions.\n";
        }
        
        $systemPrompt = "You are an Expert HR Manager conducting a behavioral interview for the role of '{$role}'.{$conceptContext}
{$projectContext}
        
        GOAL: Assess the candidate's soft skills, cultural fit, situational judgment, AND their technical decision-making through their projects.
        
        INSTRUCTIONS:
        1. **Start of Interview:** If the history is empty, say: \"Welcome to the HR Round for the {$role} position. I am your AI Interviewer. Please say 'Ready' to start.\"
        2. **Readiness Check:** If the user says \"yes\", \"ready\", \"start\", \"begin\", or similar in the most recent message:
           - Set the JSON 'question' field to: \"Great. Let's start. Please introduce yourself.\"
           - If they say \"no\" or ask something else, address it briefly in 'question' and ask again if they are ready.
        3. **Questioning Strategy:** Use the STAR method (Situation, Task, Action, Result). 
           - Ask one question at a time.
        3. **STRICT RULE:** 
           - **NEVER** correct the candidate if they are wrong.
           - **NEVER** provide the 'correct' answer or better way to say it.
           - If they fail to answer, just note it silently and move to the next question.
           - Your job is to INTERVIEW, not TEACH.
        4. **Listening & Follow-up:** 
           - If their answer is vague, ask a specific follow-up.
           - If they answer, acknowledge briefly (e.g., 'Noted', 'Okay', 'I see') and move to the next topic.
        5. **Tone:** Neutral, Professional, Observant.

        OUTPUT FORMAT (JSON):
        {
            'question': 'The text you will speak to the candidate',
            'feedback': 'Brief feedback on their previous answer (internal note or spoken intro)'
        }";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            if ($msg['role'] !== 'system') $messages[] = $msg;
        }

        return $this->callAPI($messages, ['response_format' => ['type' => 'json_object']]);
    }

    /**
     * Generate HR Interview Report
     */
    public function generateHRReport($role, $history, $concept = null) {
        $conceptContext = $concept ? " The candidate was assessed for a role specifically focused on: '**{$concept}**'." : "";
        $systemPrompt = "You are a Senior Human Resources Director with 20 years of experience in technical recruitment. Generate an EXTREMELY COMPREHENSIVE, DETAILED behavioral and technical assessment report for a candidate applying for the role of '{$role}'. {$conceptContext}
        
        CRITERIA TO ANALYZE IN DEPTH:
        1. **Communication Skills**: Clarity, articulation, confidence, active listening, and ability to explain technical concepts
        2. **Behavioral Competencies**: Leadership potential, teamwork, adaptability, conflict resolution, and STAR method usage
        3. **Technical Understanding**: Depth of knowledge about their projects, technology choices, and problem-solving approach
        4. **Project Experience**: Quality of project work, role clarity, challenges overcome, and technical decision-making
        5. **Cultural Fit**: Alignment with professional standards, work ethic, and organizational values
        6. **Problem Solving**: Approach to hypothetical situations, past challenges, and critical thinking

        STRICT GRADING & REPORTING RULES:
        1. **FAIL THE CANDIDATE (Score < 30)** IF:
           - They answered very few questions
           - Their answers were one-word, non-existent, or irrelevant
           - They quit early or showed disinterest
           - Could not explain their own projects adequately
        2. **Score 85+ ONLY IF** they provided:
           - Detailed, structured (STAR method) answers with specific examples
           - Clear explanations of technology choices and design decisions
           - Evidence of problem-solving and critical thinking
           - Strong communication and professional demeanor
        3. **BE EXTREMELY DETAILED**: 
           - Write full paragraphs (minimum 3-4 sentences each) for every section
           - Quote specific examples from the interview
           - Provide concrete evidence for your assessments
           - Use professional, corporate language
           - Minimum 800-1000 words total

        OUTPUT FORMAT (JSON):
        {
            'overall_score': 0-100,
            'content': '
                <h2 style=\"color: #c10505; border-bottom: 2px solid #ddd; padding-bottom: 10px;\">Executive Summary</h2>
                <p>Provide a comprehensive 4-5 sentence paragraph summarizing the candidate\\'s overall performance, engagement level, key strengths, main weaknesses, and final suitability for the role. Be specific about what stood out during the interview.</p>
                
                <h2 style=\"color: #c10505; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-top: 25px;\">Interview Performance Overview</h2>
                <p><b>Engagement Level:</b> Describe how engaged and enthusiastic the candidate was throughout the interview. Did they show genuine interest?</p>
                <p><b>Response Quality:</b> Analyze the depth and quality of their responses. Were they detailed or superficial? Did they use the STAR method?</p>
                <p><b>Professional Demeanor:</b> Comment on their professionalism, confidence, and overall presentation.</p>
                
                <h2 style=\"color: #c10505; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-top: 25px;\">Project & Technical Assessment</h2>
                <p><b>Project Understanding:</b> Evaluate how well they explained their projects. Could they articulate the purpose, scope, and impact? Provide specific examples from their responses.</p>
                <p><b>Technology Choices:</b> Assess their ability to justify technology decisions. Did they explain WHY they chose certain technologies? Were their reasons sound and well-thought-out?</p>
                <p><b>Technical Depth:</b> Analyze the depth of their technical knowledge. Did they demonstrate understanding beyond surface-level implementation? Quote specific technical discussions.</p>
                <p><b>Problem-Solving Approach:</b> Describe how they approached technical challenges in their projects. Were they systematic and logical?</p>
                
                <h2 style=\"color: #c10505; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-top: 25px;\">Behavioral Competencies Analysis</h2>
                <p><b>Communication Skills:</b> Provide a detailed analysis of their verbal communication, clarity of expression, and ability to structure responses. Rate their articulation and confidence.</p>
                <p><b>Leadership & Initiative:</b> Discuss any evidence of leadership qualities, taking initiative, or driving projects forward. Cite specific examples from their responses.</p>
                <p><b>Teamwork & Collaboration:</b> Evaluate their ability to work in teams, handle conflicts, and collaborate effectively. What did they say about working with others?</p>
                <p><b>Adaptability & Learning:</b> Assess their willingness to learn, adapt to new situations, and handle change. Did they demonstrate growth mindset?</p>
                
                <h2 style=\"color: #c10505; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-top: 25px;\">Key Strengths</h2>
                <ul>
                    <li><b>Strength 1:</b> Identify a major strength with detailed explanation and specific example from the interview (minimum 2 sentences)</li>
                    <li><b>Strength 2:</b> Another key strength with supporting evidence from their responses</li>
                    <li><b>Strength 3:</b> Third notable strength with concrete examples</li>
                    <li><b>Strength 4:</b> Additional strength if applicable, with detailed justification</li>
                </ul>
                
                <h2 style=\"color: #c10505; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-top: 25px;\">Areas for Development</h2>
                <ul>
                    <li><b>Area 1:</b> Identify a weakness or gap with specific examples. Provide actionable advice on how to improve (minimum 2-3 sentences)</li>
                    <li><b>Area 2:</b> Another development area with detailed recommendations for improvement</li>
                    <li><b>Area 3:</b> Third area needing attention with concrete steps to address it</li>
                    <li><b>Area 4:</b> Additional area if applicable, with improvement suggestions</li>
                </ul>
                
                <h2 style=\"color: #c10505; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-top: 25px;\">Specific Recommendations</h2>
                <p><b>For the Candidate:</b> Provide 3-4 specific, actionable recommendations for how they can improve their interview performance and professional development.</p>
                <p><b>For the Hiring Team:</b> Offer insights on how this candidate might fit within the organization, what roles they\\'d excel in, and any concerns to address during onboarding.</p>
                
                <div style=\"background: #f9f9f9; padding: 20px; border-left: 5px solid #c10505; margin-top: 25px;\">
                    <h3 style=\"margin-top: 0; color: #c10505;\">Final Verdict & Hiring Recommendation</h3>
                    <p><b>Recommendation:</b> Clearly state: <b>STRONGLY RECOMMEND</b> / <b>RECOMMEND</b> / <b>RECOMMEND WITH RESERVATIONS</b> / <b>DO NOT RECOMMEND</b></p>
                    <p><b>Justification:</b> Provide a detailed 3-4 sentence justification for your recommendation, summarizing the key factors that influenced your decision.</p>
                    <p><b>Next Steps:</b> Suggest what should happen next (e.g., proceed to technical round, additional interviews, training requirements, etc.)</p>
                </div>
            '
        CRITICAL Rules for HR Report:
        1. Content MUST be valid HTML with inline CSS.
        2. Every section must be thoroughly detailed with specific examples. 
        3. Quote actual responses from the interview transcript when possible.
        4. Minimum 800-1000 words total.
        5. **NO HALLUCINATION**: If the transcript shows the candidate remained idle or gave one-word answers, provide an 'Incomplete' summary and set the score below 10.
        6. AT THE VERY END OF THE REPORT, ADD THIS EXACT LINE:
        Overall Performance Score: [X]%
        (where X is the final score you assigned in the JSON)";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            if ($msg['role'] !== 'system') $messages[] = $msg;
        }
        $messages[] = ['role' => 'user', 'content' => "Generate the extremely detailed, comprehensive final HR assessment report based on the complete interview transcript. Include specific examples and quotes from the candidate's responses. Make it thorough and professional - minimum 800-1000 words."];

        return $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 4000
        ]);
    }


    /**
     * Generate educational coding solutions
     */
    public function generateCodingSolution($problem) {
        $prompt = "You are a coding instructor teaching students. Generate TWO detailed solutions for this coding problem:

Problem: {$problem['title']}
Category: {$problem['category']}
Difficulty: {$problem['difficulty']}

Problem Statement:
{$problem['problem_statement']}

Constraints:
{$problem['constraints']}

Example:
Input: {$problem['example_input']}
Output: {$problem['example_output']}

Please provide a DEEP EDUCATIONAL BREAKDOWN following this structure:

1. BEGINNER APPROACH:
   - **Why use a function?**: Explain the practical reason for wrapping this code in a function.
   - **Variable Breakdown**: List every variable used and why its name/type was chosen.
   - **Step-by-Step Plan**: Provide a plain-English logical plan BEFORE the code.
   - **The Code**: Provide complete working code in FOUR languages: **JavaScript, Python, Java, and C++**.
   - **Why this Loop/Logic?**: Justify the specific control flow used.
   - **Line-by-line comments**: Explaining the \"purpose\" of each line in the code blocks.

2. OPTIMIZED APPROACH:
   - **The Goal**: What are we optimizing? (Time, Memory, or Readability?)
   - **Optimization Technique**: Explain the core difference from the beginner approach.
   - **Complexity Trade-off**: Explain why this is better and if there are any costs.
   - **The Code**: Provide complete working code in FOUR languages: **JavaScript, Python, Java, and C++**.

Format your response as strictly JSON with this structure:
{
  \"beginner\": {
    \"why_function\": \"explanation of why we used a function here\",
    \"variables\": \"markdown list of variables and their purposes\",
    \"plan\": \"step by step logical plan in points\",
    \"code\": {
        \"javascript\": \"...\",
        \"python\": \"...\",
        \"java\": \"...\",
        \"cpp\": \"...\"
    },
    \"why_logic\": \"explanation of why this specific logic/loop was used\"
  },
  \"optimized\": {
    \"goal\": \"what is being optimized\",
    \"technique\": \"the technique used\",
    \"tradeoff\": \"time/memory tradeoff explanation\",
    \"code\": {
        \"javascript\": \"...\",
        \"python\": \"...\",
        \"java\": \"...\",
        \"cpp\": \"...\"
    }
  }
}

Make the content feel like a personal tutor talking to a student. Avoid overly technical jargon where a simple analogy or explanation would work better.";

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an expert coding instructor who creates clear, educational solutions for students learning programming. You always output valid JSON.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];

        $response = $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.4,
            'max_tokens' => 2000
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'solutions' => json_decode($response['content'], true)
            ];
        }

        return $response;
    }

    /**
     * Analyze Fit for a Specific Role & Company
     */
    public function analyzeTargetFit($studentData, $targetRole, $targetCompany) {
        $systemPrompt = "You are a HIGHLY CRITICAL Recruitment Head for $targetCompany.
Your task is to evaluate the student's profile for the specific role of '$targetRole' at $targetCompany.

CRITICAL SCORING RULES:
1. BE EXTREMELY STRICT. High scores (80+) must be reserved ONLY for students who already have professional-grade projects.

Output Format (JSON):
{
    'fit_score': 0-100,
    'requirement_match_chart': {
        'labels': ['Tech Stack', 'Problem Solving', 'Domain Knowledge', 'Culture Fit'],
        'required': [100, 100, 100, 100],
        'possessed': [0-100, 0-100, 0-100, 0-100]
    },
    'company_culture_alignment': '...',
    'technical_alignment': '...',
    'missing_critical_skills': ['...'],
    'interview_prep_topics': ['...'],
    'verdict': 'Highly Recommended / Recommended / Need Development / Not Fit',
    'custom_advice': '...'
}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "STUDENT PROFILE:\n" . json_encode($studentData)]
        ];

        return $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3
        ]);
    }

    public function predictCareerPath($studentData) {
        $systemPrompt = "You are a Skeptical Career Path Architect.
Look at the student's current portfolio and predict career paths ONLY where there is hard evidence.

Output Format (JSON):
{
    'primary_path': {
        'title': '...',
        'confidence': 0-100,
        'why': '...',
        'growth_potential': '...',
        'skill_alignment_chart': {
            'labels': ['Foundations', 'Advanced Tech', 'Tooling', 'Portfolio Impact'],
            'student': [0-100, 0-100, 0-100, 0-100]
        }
    },
    'alternative_paths': [ {'title': '...', 'why': '...'} ],
    'ideal_job_titles': ['...'],
    'specialization_track': '...',
    'long_term_projection': '...'
}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "STUDENT PROFILE:\n" . json_encode($studentData)]
        ];

        return $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.5
        ]);
    }

    /**
     * Analyze Profile Match (Global Market or Specific Company Comparison)
     */
    public function analyzeProfileMatch($studentData, $company = null) {
        $context = $company ? "specifically for $company's recruitment standards, tech stack, and quality benchmarks" : "against the CURRENT GLOBAL TECH MARKET at ELITE STANDARDS";
        
        $systemPrompt = "You are an Elite Global Tech Career Strategist.
Your task is to analyze a student's profile $context.

STRICT EVALUATION:
1. " . ($company ? "$company READINESS SCORE" : "GLOBAL READINESS SCORE") . ": This is NOT a participation trophy. 80%+ means they are ready for a high-impact role" . ($company ? " at $company" : " at a top-tier global firm") . ". Most students should score 20-50%.
2. SKILL OVERLAY: Be critical. If analyzing for $company, focus on their specific known tech stack and engineering culture.

Output Format (JSON):
{
    'overall_readiness_score': 0-100,
    'skill_distribution': {
        'labels': ['Languages', 'Frameworks', 'Databases', 'Cloud/DevOps', 'Soft Skills', 'Tools'],
        'student_scores': [0-100, 0-100, ...],
        'market_avg': [0-100, 0-100, ...]
    },
    'skill_gap_pie': {
        'labels': ['Skills Matched', 'Gaps Identified', 'Partial Match'],
        'values': [number, number, number]
    },
    'academic_vs_industry': {
        'labels': ['GPA Impact', 'Project Depth', 'Skill Variety', 'Market Exposure'],
        'student': [0-100, 0-100, ...],
        'industry_std': [0-100, 0-100, ...]
    },
    'market_benchmarks': [
        " . ($company ? "{ 'category': '$company Standards', 'match_percentage': 0-100, 'summary': '...', 'missing_keys': ['...'] }," : "{ 'category': 'Big Tech (Google/Meta/MSFT)', 'match_percentage': 0-100, 'summary': '...', 'missing_keys': ['...'] },") . "
        { 'category': 'FinTech & Enterprise IT', 'match_percentage': 0-100, 'summary': '...', 'missing_keys': ['...'] },
        { 'category': 'Fast-Growth Startups', 'match_percentage': 0-100, 'summary': '...', 'missing_keys': ['...'] }
    ],
    'role_fit_analysis': [
        {'role': 'SDE (Generalist)', 'match': 0-100},
        {'role': 'Frontend Specialist', 'match': 0-100},
        {'role': 'Backend / Cloud', 'match': 0-100},
        {'role': 'Data & AI', 'match': 0-100},
        {'role': 'DevOps / SRE', 'match': 0-100}
    ],
    'gap_analysis': { 'critical_missing': ['...'], 'weak_points': ['...'] },
    'action_plan': [ 
        {'step': 'Immediate Skill Acquisition', 'task': '...', 'priority': 'Critical', 'timeframe': '0-3 Months'},
        {'step': 'Portfolio Strengthening', 'task': '...', 'priority': 'High', 'timeframe': '3-6 Months'},
        {'step': 'Market Networking', 'task': '...', 'priority': 'Medium', 'timeframe': '6+ Months'}
    ],
    'executive_summary': '...'
}";

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => "STUDENT PROFILE:\n" . json_encode($studentData)
            ]
        ];

        return $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.4
        ]);
    }

    /**
     * Generate a detailed placement guide for a specific company
     */
    public function getCompanyPlacementGuide($companyName) {
        $systemPrompt = "You are an Elite Placement Officer and Career Strategist.
Your goal is to provide a comprehensive, step-by-step placement guide for a student targeting **$companyName**.

The guide should be divided into the following sections:
1. **Recruitment Process Overview**: Detailed stages (Aptitude, Technical, HR, etc.).
2. **Key Skills & Competencies**: What this company specifically looks for (e.g. Java, Python, SQL, DBMS, problem-solving, behavioral traits).
3. **Common Interview Topics**: Specifically for $companyName (e.g. if Infosys, focus on InfyTQ, HackWithInfy, or their standard recruitment).
4. **Preparation Strategy**: A 4-week intensive plan.
5. **DO's and DON'Ts**: Specific to $companyName's corporate culture.
6. **Recent Trends**: Mention any recent changes in their hiring patterns (e.g. shift to more coding-heavy rounds).

Use **Markdown** for formatting. Make it detailed, professional, and visually structured. Use bolding and lists for readability. Don't be generic—aim for specific insights related to $companyName.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate a detailed placement guide for $companyName."]
        ];

        return $this->callAPI($messages, [
            'temperature' => 0.5,
            'max_tokens' => 3000
        ]);
    }

    /**
     * Recursive UTF-8 Sanitizer
     * Ensures all strings in an array are valid UTF-8 to prevent JSON errors
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
     * Mutate a batch of aptitude questions to ensure uniqueness.
     * Takes an array of questions and rephrases each one with new values.
     */
    public function mutateAptitudeBatch($questions) {
        if (empty($questions)) return ['success' => true, 'questions' => []];

        $systemPrompt = "You are an Elite Assessment Logic Mutator.
        Your task is to take a batch of MCQs and TRANSFORM each one into a unique instance while keeping the core logic identical.
        
        STRICT MUTATION RULES:
        1. CHANGE NAMES: Use different personas (e.g., if 'Alice', use 'Vikram').
        2. CHANGE VALUES: Change numerical values but ensure the logic still works (e.g., if '5km/h', use '12km/h').
        3. REPHRASE: Rewrite the problem statement to use different scenarios.
        4. OPTIONS: Recalculate the correct answer based on new values. Provide exactly 4 distinct options.
        5. CONSISTENCY: The category and complexity must remain the same.
        
        Output format (JSON):
        {
            \"questions\": [
                {
                    \"question\": \"...\",
                    \"options\": [\"...\", \"...\", \"...\", \"...\"],
                    \"answer\": 0-3,
                    \"explanation\": \"...\",
                    \"category\": \"...\",
                    \"original_id\": 123
                }
            ]
        }";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Mutate the following questions uniquely:\n" . json_encode($questions)]
        ];

        $response = $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 4000
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
     * Mutate a coding challenge to be unique for a specific session.
     * Enhanced to include student context and industry-readiness.
     */
    public function mutateCodingChallenge($seedProblem, $studentContext = []) {
        $contextStr = "";
        if (!empty($studentContext)) {
            $contextStr = "\n\n=== CANDIDATE CONTEXT ===\n";
            if (!empty($studentContext['projects'])) {
                $contextStr .= "PROJECTS:\n";
                foreach ($studentContext['projects'] as $p) {
                    $contextStr .= "- {$p['title']}: {$p['description']}\n";
                }
            }
            if (!empty($studentContext['skills'])) {
                $contextStr .= "SKILLS: " . implode(', ', $studentContext['skills']) . "\n";
            }
            $contextStr .= "==========================\n";
        }

        $systemPrompt = "You are a Senior Technical Problem Architect at an Elite Tech Firm.
        Take the provided coding problem and RE-SKIN it into a high-stakes, industry-level challenge.
        
        STRICT OBJECTIVES:
        1. **Algorithmic Integrity**: Keep the core algorithmic requirement (e.g., sliding window, BFS, DP) identical.
        2. **Industry Realism**: Transform the story into a mission-critical industry scenario (e.g., low-latency trading, cloud resource scaling, real-time logistics, secure data pipeline).
        3. **Candidate Personalization**: " . (!empty($contextStr) ? "INTEGRATE the Candidate's Context. Reference their projects or skills in the problem story to make it feel tailormade." : "Focus on general Industry Excellence.") . "
        4. **CS Fundamentals Integration**: Explicitly mention or require understanding of CS fundamentals (DSA efficiency, OOP patterns, DBMS indexing, or OS concurrency) within the constraints or problem backstory.
        5. **Trickiness**: The logic should be 'tricky but fair'. Add edge cases in constraints that require deep thinking.
        
        REQUIREMENTS:
        - Change story, input/output variable names, and scenarios.
        - Ensure example cases match the new scenario.
        - Provide title, problem_statement, constraints (including complexity), example_input, and example_output.
        
        Return as JSON:
        {
            \"title\": \"Professional Industry Title\",
            \"problem_statement\": \"...\",
            \"constraints\": \"...\",
            \"example_input\": \"...\",
            \"example_output\": \"...\",
            \"category\": \"DSA/OOP/DBMS/OS (Most relevant)\"
        }
        
        {$contextStr}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Mutated Problem Request: " . json_encode($seedProblem)]
        ];

        $response = $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object']
        ]);

        if ($response['success']) {
            $data = json_decode($response['content'], true);
            return [
                'success' => true,
                'data' => $data
            ];
        }
        return $response;
    }

    /**
     * Generate a similar MCQ question based on a given question and topic.
     * Used for NQT practice to provide fresh variations.
     */
    public function generateSimilarQuestion($baseQuestion, $topic) {
        $systemPrompt = "You are an Elite Assessment Expert. 
        Given a base question and its topic, generate a NEW, SIMILAR question.
        The new question should test the same concept but use different values, scenarios, or phrasing.
        
        STRICT RULES:
        1. CONCEPT: Must be identical to the base question.
        2. VARIATION: Change numbers, names, or the specific scenario.
        3. ACCURACY: Ensure the logic and correct answer are perfect.
        4. STRUCTURE: Return exactly 4 options.
        5. FORMAT: Return as JSON.
        
        Output format:
        {
            \"question\": \"...\",
            \"options\": [\"Opt A\", \"Opt B\", \"Opt C\", \"Opt D\"],
            \"answer\": 0, 
            \"explanation\": \"...\"
        }";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Base Topic: $topic\nBase Question: $baseQuestion\n\nGenerate a similar variation."]
        ];

        $response = $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object']
        ]);

        if ($response['success']) {
            $data = json_decode($response['content'], true);
            return [
                'success' => true,
                'question' => $data
            ];
        }
        return $response;
    }

    /**
     * Generate 5 technical questions to verify a certification.
     */
    public function generateCertificationQuestions($certTitle, $issuer) {
        $systemPrompt = "You are a Technical Certification Auditor. 
        Generate 5 deep-dive technical questions to verify if a student actually has the knowledge implied by the certification: '$certTitle' from '$issuer'.
        
        GOALS:
        1. Verify technical depth in the certification domain.
        2. Test their understanding of core concepts.
        3. Challenge them with real-world application of the certified skills.
        
        Format: Return a JSON object with a 'questions' array (list of 5 strings).";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate 5 verification questions for certification: '$certTitle' ($issuer)"]
        ];

        return $this->callAPI($messages, [
            'response_format' => ['type' => 'json_object']
        ]);
    }

    /**
     * Evaluate Certification Viva
     */
    public function evaluateCertificationViva($certTitle, $issuer, $transcript) {
        $systemPrompt = "You are a technical certification auditor. 
        A student has claimed a certification: '$certTitle' from '$issuer'.
        You have a transcript of a Viva session where the student was asked technical questions about the certification domain.
        
        Analyze the transcript and provide:
        1. A score from 0-100 based on their conceptual understanding.
        2. Detailed feedback on what they know well and where they lack depth.
        3. A final 'Verified' status (Score >= 70).
        
        Return ONLY a JSON object:
        {
            \"score\": 85,
            \"status\": \"VERIFIED\",
            \"feedback\": \"Student demonstrated strong knowledge of...\",
            \"verified\": true
        }";

        $userPrompt = "Certification: $certTitle\nIssuer: $issuer\n\nTranscript:\n$transcript";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        return $this->callAPI($messages, ['response_format' => ['type' => 'json_object']]);
    }
}
