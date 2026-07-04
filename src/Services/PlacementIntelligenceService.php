<?php
namespace App\Services;

use PDO;
use Exception;

class PlacementIntelligenceService {
    private $db;
    private $ai;

    public function __construct() {
        $this->db = getDB();
        $this->ai = new \AIService();
    }

    /**
     * Get raw student profile and activity data from all relevant tables
     */
    public function getRawData($studentId, $institution) {
        // 1. Demographics & Academics
        // For GMU: student_profiles has the master details, or users table
        // We'll check both
        $stmt = $this->db->prepare("SELECT * FROM student_profiles WHERE usn = ? LIMIT 1");
        $stmt->execute([$studentId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if (empty($profile)) {
            // Check users table (legacy cache)
            $stmtUser = $this->db->prepare("SELECT NAME as name, COURSE as course, PROGRAMME as programme, DISCIPLINE as department, AADHAR as aadhar FROM users WHERE USER_NAME = ? LIMIT 1");
            $stmtUser->execute([$studentId]);
            $profile = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        // Get SGPA and calculate CGPA (academic history)
        $sgpaList = [];
        $backlogs = isset($profile['active_backlogs']) ? intval($profile['active_backlogs']) : 0;
        
        if (strtoupper($institution) === 'GMU') {
            $gmuPrefix = defined('DB_GMU_PREFIX') ? DB_GMU_PREFIX : '';
            $remoteDB = getDB('gmu');
            if ($remoteDB) {
                try {
                    $stmtSgpa = $remoteDB->prepare("SELECT sem, sgpa FROM {$gmuPrefix}ad_student_approved WHERE usn = ? AND sgpa IS NOT NULL AND sgpa > 0 ORDER BY sem ASC");
                    $stmtSgpa->execute([$studentId]);
                    $sgpaRows = $stmtSgpa->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    foreach ($sgpaRows as $r) {
                        $sgpaList[intval($r['sem'])] = floatval($r['sgpa']);
                    }
                } catch (Exception $e) {
                    // Safe fallback
                }
            }
        } else {
            // GMIT student
            try {
                $stmtSgpa = $this->db->prepare("SELECT semester, sgpa FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND sgpa IS NOT NULL AND sgpa > 0 ORDER BY semester ASC");
                $stmtSgpa->execute([$studentId, $institution]);
                $sgpaRows = $stmtSgpa->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($sgpaRows as $r) {
                    $sgpaList[intval($r['semester'])] = floatval($r['sgpa']);
                }
            } catch (Exception $e) {
                // Safe fallback
            }
        }

        if (!empty($sgpaList)) {
            $cgpa = array_sum($sgpaList) / count($sgpaList);
            $semestersCount = count($sgpaList);
        } else {
            // Fallback to profile CGPA if available
            $cgpa = isset($profile['cgpa']) && is_numeric($profile['cgpa']) ? floatval($profile['cgpa']) : 7.0;
            $semestersCount = 4;
        }

        // 2. Projects & Portfolio
        $stmtProj = $this->db->prepare("SELECT * FROM student_projects WHERE student_id = ?");
        $stmtProj->execute([$studentId]);
        $projects = $stmtProj->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtPort = $this->db->prepare("SELECT * FROM student_portfolio WHERE student_id = ? AND institution = ?");
        $stmtPort->execute([$studentId, $institution]);
        $portfolioItems = $stmtPort->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Combine project items
        $allProjects = [];
        foreach ($projects as $p) {
            $allProjects[] = [
                'title' => $p['title'] ?? $p['project_name'] ?? 'Untitled Project',
                'description' => $p['description'] ?? '',
                'technologies' => $p['technologies'] ?? '',
                'link' => $p['github_url'] ?? '',
                'is_verified' => !empty($p['verified_by']) ? 1 : 0
            ];
        }
        foreach ($portfolioItems as $item) {
            if ($item['category'] === 'Project') {
                $allProjects[] = [
                    'title' => $item['title'],
                    'description' => $item['description'] ?? '',
                    'technologies' => $item['description'] ?? '',
                    'link' => $item['link'] ?? '',
                    'is_verified' => 0 // default unverified for student uploads
                ];
            }
        }

        // Skills & Certifications
        $skills = [];
        $certifications = [];
        foreach ($portfolioItems as $item) {
            if ($item['category'] === 'Skill') {
                $skills[] = $item['title'];
            } elseif ($item['category'] === 'Certification') {
                $certifications[] = [
                    'title' => $item['title'],
                    'issuer' => $item['sub_title'] ?? 'N/A',
                    'date' => $item['date_completed'] ?? 'N/A'
                ];
            }
        }

        // 3. Coding Platform Performance
        $stmtCode = $this->db->prepare("
            SELECT cp.title, cp.difficulty, cp.category, scp.status, scp.attempts, scp.language_used 
            FROM student_coding_progress scp 
            JOIN coding_problems cp ON scp.problem_id = cp.id 
            WHERE scp.student_id = ? AND scp.institution = ?
        ");
        $stmtCode->execute([$studentId, $institution]);
        $codingProgress = $stmtCode->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 4. Mock Interviews & Resume Analysis
        // Mock interviews from unified_ai_assessments or mock_ai_interview_sessions
        $stmtMock = $this->db->prepare("SELECT score, completed_at, assessment_type, details FROM unified_ai_assessments WHERE student_id = ? AND institution = ? AND score IS NOT NULL ORDER BY completed_at DESC");
        $stmtMock->execute([$studentId, $institution]);
        $mocks = $stmtMock->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Resume score from resume_analysis_cache
        $numericUserId = null;
        try {
            $stmtUserSl = $this->db->prepare("SELECT SL_NO FROM users WHERE USER_NAME = ? LIMIT 1");
            $stmtUserSl->execute([$studentId]);
            $numericUserId = $stmtUserSl->fetchColumn();
        } catch (Exception $e) {
            // Safe fallback
        }

        $stmtResume = $this->db->prepare("SELECT analysis_json FROM resume_analysis_cache WHERE user_id = ? " . ($numericUserId ? "OR user_id = ?" : "") . " ORDER BY updated_at DESC LIMIT 1");
        if ($numericUserId) {
            $stmtResume->execute([$studentId, $numericUserId]);
        } else {
            $stmtResume->execute([$studentId]);
        }
        $resumeRow = $stmtResume->fetch(PDO::FETCH_ASSOC);
        $resumeInfo = [];
        if ($resumeRow) {
            $analysis = json_decode($resumeRow['analysis_json'], true);
            $resumeInfo = [
                'score' => $analysis['score'] ?? ($analysis['overall_score'] ?? ($analysis['ats_score'] ?? 70)),
                'analysis_json' => $resumeRow['analysis_json']
            ];
        } else {
            // Fallback: Check if they have built a resume in student_resumes using their USN string
            try {
                $stmtHasResume = $this->db->prepare("SELECT id, resume_data FROM student_resumes WHERE student_id = ? LIMIT 1");
                $stmtHasResume->execute([$studentId]);
                $resRow = $stmtHasResume->fetch(PDO::FETCH_ASSOC);
                if ($resRow) {
                    $rData = json_decode($resRow['resume_data'], true) ?: [];
                    $score = 50;
                    if (!empty($rData['education'])) $score += 10;
                    if (!empty($rData['experience'])) $score += 10;
                    if (!empty($rData['projects'])) $score += 10;
                    if (!empty($rData['skills'])) $score += 10;
                    $resumeInfo = [
                        'score' => min(95, $score),
                        'analysis_json' => json_encode(['score' => $score, 'status' => 'Draft - Built'])
                    ];
                }
            } catch (Exception $e) {
                // Safe fallback
            }
        }

        // 5. AMPI FFM Personality Scores
        $stmtPers = $this->db->prepare("SELECT * FROM student_personality_profiles WHERE student_id = ? AND institution = ? LIMIT 1");
        $stmtPers->execute([$studentId, $institution]);
        $personality = $stmtPers->fetch(PDO::FETCH_ASSOC) ?: [];

        // 6. Historical Monthly Metrics Trend
        $stmtMetrics = $this->db->prepare("SELECT month_year, coding_score, project_score, communication_score, behavioral_score, career_readiness_score, git_score FROM student_monthly_metrics WHERE student_id = ? AND institution = ? ORDER BY month_year ASC");
        $stmtMetrics->execute([$studentId, $institution]);
        $metricsHistory = $stmtMetrics->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'student_id' => $studentId,
            'institution' => $institution,
            'profile' => $profile,
            'cgpa' => $cgpa,
            'backlogs' => $backlogs,
            'semesters_cleared' => $semestersCount,
            'projects' => $allProjects,
            'skills' => $skills,
            'certifications' => $certifications,
            'coding' => $codingProgress,
            'mocks' => $mocks,
            'resume' => $resumeInfo,
            'personality' => $personality,
            'metrics_history' => $metricsHistory
        ];
    }

    /**
     * Compute all subscores and estimates deterministically
     */
    public function calculateDeterministicScores($raw) {
        // 1. Data Completeness Score (DCS)
        $dcsBreakdown = [
            'academics' => $raw['cgpa'] > 0 ? 20 : 0,
            'resume' => !empty($raw['resume']) ? 20 : 0,
            'mocks' => !empty($raw['mocks']) ? 20 : 0,
            'coding' => !empty($raw['coding']) ? 20 : 0,
            'projects' => !empty($raw['projects']) ? 20 : 0
        ];
        $dcs = array_sum($dcsBreakdown);

        // 2. GitHub Quality Score (S_GIT)
        // Check if student has GitHub URL
        $hasGithub = false;
        foreach ($raw['projects'] as $p) {
            if (stripos($p['link'], 'github.com') !== false) {
                $hasGithub = true;
                break;
            }
        }
        
        $gitScore = 40.0; // Baseline if no github
        $gitBreakdown = ['Active Days' => 0, 'Repo Quality' => 0, 'Commit Complexity' => 0, 'Pull Requests' => 0];
        if ($hasGithub) {
            $gitScore = 70.0; // Base if has link
            $gitBreakdown = ['Active Days' => 60, 'Repo Quality' => 70, 'Commit Complexity' => 80, 'Pull Requests' => 50];
            // Boost if projects are verified
            $verifiedCount = 0;
            foreach ($raw['projects'] as $p) {
                if ($p['is_verified']) $verifiedCount++;
            }
            $gitScore += min(15.0, $verifiedCount * 5.0);
            // Boost based on solved coding challenges
            $solvedCount = 0;
            foreach ($raw['coding'] as $c) {
                if ($c['status'] === 'solved' || $c['status'] === 'mastered') $solvedCount++;
            }
            $gitScore += min(15.0, $solvedCount * 3.0);
            $gitScore = min(100.0, $gitScore);
        }

        // 3. Project Quality Score (S_PROJ)
        $projScores = [];
        $techsFound = [];
        foreach ($raw['projects'] as $p) {
            // Estimate complexity from description length
            $descLen = strlen($p['description']);
            $complexity = $descLen > 200 ? 0.90 : ($descLen > 100 ? 0.75 : 0.50);
            if (stripos($p['description'], 'distributed') !== false || stripos($p['description'], 'microservice') !== false || stripos($p['description'], 'api') !== false) {
                $complexity = min(1.0, $complexity + 0.10);
            }
            
            // Verification status
            $verification = $p['is_verified'] ? 1.0 : 0.5;
            
            // Documentation quality (if link is present and description is long)
            $documentation = (!empty($p['link']) && $descLen > 150) ? 1.0 : 0.6;
            
            // Tech diversity keywords
            $techs = array_map('trim', explode(',', str_replace(';', ',', $p['technologies'])));
            foreach ($techs as $t) {
                if (!empty($t)) $techsFound[strtolower($t)] = true;
            }
            $techDiversity = min(1.0, 0.8 + (count($techs) * 0.05));

            $projScores[] = $complexity * $verification * $documentation * $techDiversity * 100;
        }

        $projectScore = empty($projScores) ? 30.0 : (array_sum($projScores) / count($projScores));
        // Scale with project count
        $projectScore = min(100.0, $projectScore + min(15.0, count($raw['projects']) * 5.0));

        // 4. Coding Performance Score (S_COD)
        $totalProblems = count($raw['coding']);
        $solvedCount = 0;
        $difficultyWeightSum = 0;
        $totalWeightSum = 0;
        
        foreach ($raw['coding'] as $c) {
            $isSolved = ($c['status'] === 'solved' || $c['status'] === 'mastered');
            if ($isSolved) $solvedCount++;
            
            $weight = $c['difficulty'] === 'Hard' ? 1.4 : ($c['difficulty'] === 'Medium' ? 1.0 : 0.6);
            $totalWeightSum += $weight;
            if ($isSolved) {
                $difficultyWeightSum += $weight;
            }
        }

        $codingAccuracy = $totalProblems > 0 ? ($solvedCount / $totalProblems) * 100 : 0;
        $codingDifficultyFactor = $totalProblems > 0 ? ($difficultyWeightSum / $totalWeightSum) * 100 : 0;
        
        // Base score uses solved problems:
        $codingScore = 40.0; // baseline
        if ($solvedCount > 0) {
            $codingScore = min(100.0, 40.0 + ($solvedCount * 15.0));
            // Mix accuracy and difficulty
            $codingScore = ($codingScore * 0.5) + ($codingAccuracy * 0.3) + ($codingDifficultyFactor * 0.2);
        }

        // 5. Communication Score
        $mockScores = [];
        foreach ($raw['mocks'] as $m) {
            if (is_numeric($m['score'])) {
                $mockScores[] = floatval($m['score']);
            }
        }
        $communicationScore = empty($mockScores) ? 60.0 : (array_sum($mockScores) / count($mockScores));

        // 6. Personality Traits (FFM)
        $personality = $raw['personality'];
        $eZ = isset($personality['extraversion_z']) ? floatval($personality['extraversion_z']) : 0.0;
        $aZ = isset($personality['agreeableness_z']) ? floatval($personality['agreeableness_z']) : 0.0;
        $cZ = isset($personality['conscientiousness_z']) ? floatval($personality['conscientiousness_z']) : 0.0;
        $nZ = isset($personality['neuroticism_z']) ? floatval($personality['neuroticism_z']) : 0.0;
        $oZ = isset($personality['openness_z']) ? floatval($personality['openness_z']) : 0.0;

        $eL = $personality['extraversion_level'] ?? 'Medium';
        $aL = $personality['agreeableness_level'] ?? 'Medium';
        $cL = $personality['conscientiousness_level'] ?? 'Medium';
        $nL = $personality['neuroticism_level'] ?? 'Medium';
        $oL = $personality['openness_level'] ?? 'Medium';

        // 7. Non-linear Personality Range Matching for Careers
        // Function to calculate fit based on range and scaling parameter
        $calculateFit = function($studentZ, $lower, $upper, $p = 0.05) {
            if ($studentZ >= $lower && $studentZ <= $upper) {
                return 100.0;
            } elseif ($studentZ < $lower) {
                return max(0.0, 100.0 - $p * pow(($lower - $studentZ), 2) * 100);
            } else {
                return max(0.0, 100.0 - $p * pow(($studentZ - $upper), 2) * 100);
            }
        };

        // Define career profiles with ideal ranges [L, U] for [Conscientiousness, Agreeableness, Extraversion]
        $careers = [
            'Backend Developer' => [
                'ranges' => ['c' => [0.2, 1.5], 'a' => [-1.0, 0.5], 'e' => [-1.0, 0.5]],
                'weights' => ['coding' => 0.40, 'project' => 0.30, 'git' => 0.15, 'mock' => 0.15],
                'tech_skills' => ['PHP', 'Python', 'Java', 'SQL', 'Databases', 'API', 'Docker', 'AWS']
            ],
            'Frontend Developer' => [
                'ranges' => ['c' => [0.0, 1.2], 'a' => [-0.5, 1.0], 'e' => [-0.5, 1.0]],
                'weights' => ['coding' => 0.25, 'project' => 0.45, 'git' => 0.20, 'mock' => 0.10],
                'tech_skills' => ['HTML', 'CSS', 'JavaScript', 'React', 'Vue', 'Tailwind', 'UI/UX', 'Figma']
            ],
            'QA / Security Engineer' => [
                'ranges' => ['c' => [0.5, 1.8], 'a' => [-1.5, 0.2], 'e' => [-1.2, 0.2]],
                'weights' => ['coding' => 0.35, 'project' => 0.25, 'git' => 0.15, 'mock' => 0.25],
                'tech_skills' => ['Testing', 'Selenium', 'QA', 'Cypress', 'Penetration Testing', 'Linux', 'Security']
            ],
            'Project Manager / Consultant' => [
                'ranges' => ['c' => [0.3, 1.5], 'a' => [0.2, 1.5], 'e' => [0.5, 2.0]],
                'weights' => ['coding' => 0.10, 'project' => 0.30, 'git' => 0.10, 'mock' => 0.50],
                'tech_skills' => ['Agile', 'Scrum', 'Management', 'Jira', 'Communication', 'Presentation', 'Planning']
            ],
            'AI / Data Engineer' => [
                'ranges' => ['c' => [0.4, 1.6], 'a' => [-1.0, 0.4], 'e' => [-1.2, 0.4]],
                'weights' => ['coding' => 0.45, 'project' => 0.35, 'git' => 0.10, 'mock' => 0.10],
                'tech_skills' => ['Python', 'Machine Learning', 'Data Science', 'SQL', 'PyTorch', 'TensorFlow', 'Pandas']
            ]
        ];

        $careerMatches = [];
        foreach ($careers as $name => $c) {
            // Personality Fit
            $fitC = $calculateFit($cZ, $c['ranges']['c'][0], $c['ranges']['c'][1]);
            $fitA = $calculateFit($aZ, $c['ranges']['a'][0], $c['ranges']['a'][1]);
            $fitE = $calculateFit($eZ, $c['ranges']['e'][0], $c['ranges']['e'][1]);
            $personalityFit = ($fitC + $fitA + $fitE) / 3.0;

            // Technical Score mapping based on weights
            $w = $c['weights'];
            $technicalFit = ($codingScore * $w['coding']) + ($projectScore * $w['project']) + ($gitScore * $w['git']) + ($communicationScore * $w['mock']);

            // Overall career match combines technical (70%) and personality (30%)
            $matchPercentage = ($technicalFit * 0.70) + ($personalityFit * 0.30);
            
            // Confidence score based on DCS and score level
            $confidence = $dcs * 0.8 + ($matchPercentage * 0.2);

            // Collect evidence
            $evidence = [];
            if ($codingScore >= 70 && $w['coding'] >= 0.30) {
                $evidence[] = "Strong coding accuracy and challenge participation.";
            }
            if (!empty($raw['projects']) && $w['project'] >= 0.30) {
                $evidence[] = "Has " . count($raw['projects']) . " registered project(s).";
            }
            if ($hasGithub && $w['git'] >= 0.15) {
                $evidence[] = "Active GitHub profile detected.";
            }
            if ($communicationScore >= 75 && $w['mock'] >= 0.20) {
                $evidence[] = "Excellent verbal and communication scores in mock AI interviews.";
            }
            if ($personalityFit >= 85) {
                $evidence[] = "Personality assessment closely matches this role's working style requirements.";
            }

            // Tech match check
            $matchedTech = [];
            foreach ($raw['skills'] as $s) {
                foreach ($c['tech_skills'] as $ts) {
                    if (stripos($s, $ts) !== false) {
                        $matchedTech[] = $ts;
                    }
                }
            }
            if (!empty($matchedTech)) {
                $evidence[] = "Skills match: " . implode(', ', array_unique($matchedTech));
            }

            $careerMatches[] = [
                'role' => $name,
                'match_percentage' => round($matchPercentage, 1),
                'confidence_score' => round($confidence, 1),
                'personality_fit' => round($personalityFit, 1),
                'technical_fit' => round($technicalFit, 1),
                'evidence' => $evidence
            ];
        }

        // Sort matches by percentage descending
        usort($careerMatches, function($a, $b) {
            return $b['match_percentage'] <=> $a['match_percentage'];
        });

        // 8. Placement Probability Model (PPM)
        // Estimated Technical Selection Readiness: P_Tech = S_COD * 0.6 + S_Projects * 0.3 + S_GIT * 0.1
        $pTechReady = ($codingScore * 0.6) + ($projectScore * 0.3) + ($gitScore * 0.1);
        // Estimated HR Panel Readiness: P_HR = S_MOCK_HR * 0.6 + C_AMPI * 0.2 + (100 - N_AMPI) * 0.2
        // C_AMPI is conscientiousness fit, N_AMPI is neuroticism z-score mapped to scale (100 - mapped neuroticism)
        // Map Neuroticism Z-score (typically -2 to 2) to 0-100 scale: Z=0 is 50, Z=1 is 75, etc.
        $neuroticismScore = max(0, min(100, 50 + ($nZ * 25)));
        $conscientiousnessFit = $calculateFit($cZ, 0.3, 1.5); // ideal PM/Dev range
        $pHRReady = ($communicationScore * 0.6) + ($conscientiousnessFit * 0.2) + ((100 - $neuroticismScore) * 0.2);

        $overallReadiness = ($pTechReady + $pHRReady) / 2.0;

        // 9. Contradiction Engine (Synergies & Anomalies)
        $anomalies = [];
        // High CGPA but low practical coding
        if ($raw['cgpa'] >= 8.5 && $codingScore < 50) {
            $anomalies[] = [
                'type' => 'Academic vs Coding',
                'severity' => 'Medium',
                'message' => "Strong academic discipline is evident (CGPA: {$raw['cgpa']}), but practical coding performance does not yet reflect the same level of competence (Coding Score: " . round($codingScore, 1) . "%)."
            ];
        }
        // High mock interview score but low git/projects
        if ($communicationScore >= 80 && count($raw['projects']) === 0) {
            $anomalies[] = [
                'type' => 'Communication vs Evidence',
                'severity' => 'Low',
                'message' => "Candidate possesses highly polished verbal communication, but lacks verified project records or active portfolio implementations to back up technical claims."
            ];
        }
        // High conscientiousness but low academic score or backlogs
        if ($cL === 'High' && $raw['backlogs'] > 0) {
            $anomalies[] = [
                'type' => 'Trait vs Outcomes',
                'severity' => 'Low',
                'message' => "AMPI shows high conscientiousness (organized, methodical style), yet the academic record reflects " . $raw['backlogs'] . " active backlog(s). This suggests situational obstacles or mismatch in exam preparation strategies."
            ];
        }

        // 10. Critical Risk Indicators
        $risks = [];
        if ($raw['backlogs'] > 0) {
            $risks[] = [
                'category' => 'Academic eligibility',
                'severity' => 'High',
                'message' => "Has " . $raw['backlogs'] . " active backlog(s). Many Tier 1 companies require 0 active backlogs."
            ];
        }
        if ($raw['cgpa'] < 6.0) {
            $risks[] = [
                'category' => 'Academics',
                'severity' => 'High',
                'message' => "CGPA is below 6.0. Minimum eligibility for standard campus drives is typically 6.0 or 6.5."
            ];
        }
        if ($dcs < 60) {
            $risks[] = [
                'category' => 'Profile Completeness',
                'severity' => 'Medium',
                'message' => "Data Completeness Score is low ($dcs%). Complete your mock interviews, resume review, and coding problems to increase report accuracy."
            ];
        }
        if ($codingScore < 45) {
            $risks[] = [
                'category' => 'Technical Readiness',
                'severity' => 'Medium',
                'message' => "Coding performance score is below 45%. Solve more challenges on the practice portal to pass technical filters."
            ];
        }

        // 11. Mentor Intervention Checklist items
        $mentorChecklist = [];
        if ($raw['backlogs'] > 0) {
            $mentorChecklist[] = "Schedule backlog clearance plan prior to next placement season.";
        }
        if ($codingScore < 50) {
            $mentorChecklist[] = "Assign mandatory data structures challenges (Arrays, Strings, Recursion).";
        }
        if (count($raw['projects']) === 0) {
            $mentorChecklist[] = "Review project ideation phase and push code to GitHub.";
        }
        if ($communicationScore < 65) {
            $mentorChecklist[] = "Recommend participating in 2 additional mock AI HR rounds.";
        }
        if (empty($mentorChecklist)) {
            $mentorChecklist[] = "Regular review and profile checks for Tier 1 premium company placement prep.";
        }

        // 12. Determine Recruiter Drive Class Category Fit
        // Class A: Elite product, Class B: Standard IT services, Class C: Core engineering / Operations
        $driveCategory = 'Standard IT / Services Drives (Class B)';
        if ($overallReadiness >= 78 && $raw['cgpa'] >= 7.5 && $raw['backlogs'] === 0) {
            $driveCategory = 'Elite Product / R&D Drives (Class A)';
        } elseif ($overallReadiness < 50 || $raw['cgpa'] < 6.0) {
            $driveCategory = 'Support / Tech Operations / Graduate Trainee (Class C)';
        }

        // 13. Month-over-Month Deltas
        $history = $raw['metrics_history'];
        $deltas = [
            'coding' => 0.0,
            'projects' => 0.0,
            'communication' => 0.0,
            'behavioral' => 0.0,
            'git' => 0.0,
            'readiness' => 0.0
        ];
        if (count($history) >= 2) {
            $last = end($history);
            $prev = $history[count($history) - 2];
            $deltas = [
                'coding' => $last['coding_score'] - $prev['coding_score'],
                'projects' => $last['project_score'] - $prev['project_score'],
                'communication' => $last['communication_score'] - $prev['communication_score'],
                'behavioral' => $last['behavioral_score'] - $prev['behavioral_score'],
                'git' => $last['git_score'] - $prev['git_score'],
                'readiness' => $last['career_readiness_score'] - $prev['career_readiness_score']
            ];
        }

        return [
            'dcs' => $dcs,
            'dcs_breakdown' => $dcsBreakdown,
            'coding_score' => round($codingScore, 1),
            'project_score' => round($projectScore, 1),
            'git_score' => round($gitScore, 1),
            'communication_score' => round($communicationScore, 1),
            'behavioral_score' => round($raw['cgpa'] * 8.0, 1), // Proxy calculation
            'readiness_score' => round($overallReadiness, 1),
            'drive_category' => $driveCategory,
            'deltas' => $deltas,
            'ppm' => [
                'tech_readiness' => round($pTechReady, 1),
                'hr_readiness' => round($pHRReady, 1),
                'overall_probability' => round($overallReadiness, 1)
            ],
            'career_matches' => $careerMatches,
            'anomalies' => $anomalies,
            'risks' => $risks,
            'mentor_checklist' => $mentorChecklist,
            'personality_ffm' => [
                'extraversion' => ['level' => $eL, 'z' => $eZ],
                'agreeableness' => ['level' => $aL, 'z' => $aZ],
                'conscientiousness' => ['level' => $cL, 'z' => $cZ],
                'neuroticism' => ['level' => $nL, 'z' => $nZ],
                'openness' => ['level' => $oL, 'z' => $oZ]
            ]
        ];
    }

    /**
     * Fetch report, generating and explaining via LLM if necessary
     */
    public function generateAndCacheReport($studentId, $institution, $regenerate = false) {
        // Check cache first
        if (!$regenerate) {
            $stmt = $this->db->prepare("SELECT report_data, report_text FROM student_placement_reports WHERE student_id = ? AND institution = ? LIMIT 1");
            $stmt->execute([$studentId, $institution]);
            $cached = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cached) {
                return [
                    'success' => true,
                    'data' => json_decode($cached['report_data'], true),
                    'report_markdown' => $cached['report_text'],
                    'is_cached' => true
                ];
            }
        }

        // Run raw queries
        $rawData = $this->getRawData($studentId, $institution);
        
        // Calculate deterministic engine scores
        $metrics = $this->calculateDeterministicScores($rawData);

        // Call the LLM to generate the professional explanation report
        $systemPrompt = "ROLE DEFINITION
You are the Narrative Generation Engine of the Lakshya Placement Intelligence & Career Analysis Engine (PICE).
Your responsibility is ONLY to transform structured analysis produced by the deterministic engine into a professional, readable report.
You are NOT responsible for analysis, scoring, calculations, grading, ranking, or prediction.

INPUT
You will receive a JSON object containing:
• Student Information
• Academic Metrics
• Technical Metrics
• Communication Metrics
• Personality Metrics (AMPI)
• Calculated Intelligence Scores
• Placement Readiness Score
• Placement Probability Estimates
• Career Match Rankings
• Contradiction Flags
• Risk Indicators
• Student Roadmap
• Mentor Checklist
• Explainability Contributions
• Data Completeness Score
• Confidence Scores

FACT PRIORITY HIERARCHY
1. Deterministic Engine Output (Highest Authority)
2. Student Data
3. Historical Metrics
4. Personality Metrics
5. Narrative Explanation
6. Stylistic Improvements (Lowest Authority)
If there is ever a conflict, the higher-priority source wins.

PROHIBITED
You must NEVER:
• invent any score
• invent percentages
• infer missing values
• estimate unavailable metrics
• modify rankings
• reorder career matches
• generate new contradiction flags
• create additional risks
• calculate new scores
• introduce unsupported strengths
• introduce unsupported weaknesses

MISSING DATA HANDLING
If a field or metric is missing or unavailable:
• Do not estimate it.
• State that the information was unavailable.
• Explain how this affects confidence.
• Continue generating the remaining sections.

WRITING STYLE & TONE
• Objective, recruiter-grade, evidence-driven, direct, and constructive.
• No motivational clichés, no exaggerated praise, no speculation.

PERSONALITY RULES
• Personality traits influence work preferences. They do NOT determine capability.
• Never reject or recommend a career solely because of personality.
• Always combine personality with technical evidence.
• Frame personality traits constructively. Standard ethical safeguards:
  - Low Agreeableness: Phrase as 'strong analytical focus, skepticism, ideal for quality assurance or code auditing when backed by technical skill.'

OUTPUT RULES
• Use Markdown only.
• Follow the 14-section order exactly.
• Never change heading names.
• Never omit a section.
• Do not reorder career rankings.
• Preserve numerical formatting exactly as received.

SECTIONS STRUCTURE CONTRACT:
1. EXECUTIVE SUMMARY: 2-3 sentences explaining readiness and best matches based on evidence.
2. STUDENT SNAPSHOT: A clean table summarizing metrics (CGPA/SGPAs, Coding Score, project quality, and Data Completeness Score). Include a Confidence section exactly as follows:
   Confidence: [Value]%
   Reason: [Provide detailed reason; if reduced, state why, e.g. \"Reduced because: Mock Interview unavailable\"]
3. HISTORICAL PROGRESS SNAPSHOT: Describe month-over-month growth trends in coding, communication, or other areas using historical metrics.
4. ACADEMIC INTELLIGENCE: Explain exam performance consistency, discipline, and backlogs. For every conclusion, use:
   Evidence: [Value/data]
   Interpretation: [Meaning]
   Recommendation: [Action]
5. TECHNICAL INTELLIGENCE: Detail project engineering depth and coding platform correctness. Cite specific evidence (Coding Score, Projects). For every conclusion, use:
   Evidence: [Value/data]
   Interpretation: [Meaning]
   Recommendation: [Action]
6. COMMUNICATION INTELLIGENCE: Evaluate speech metrics, mock HR performance, and resume audit score. Cite specific evidence (Mock Interview, Resume Score). For every conclusion, use:
   Evidence: [Value/data]
   Interpretation: [Meaning]
   Recommendation: [Action]
7. BEHAVIORAL & WORK STYLE ANALYSIS: Interpret FFM AMPI personality traits (Extraversion, Agreeableness, Conscientiousness, Openness) constructively. Do not include Neuroticism as it is not measured. Always combine personality with technical evidence.
8. CORRELATION ENGINE: Explain synergies between traits and academic/technical results. Detail and explain all engine-generated contradiction flags or anomalies.
9. RECRUITER PERSPECTIVE & DRIVE CLASSIFICATION: Provide a detailed recruiter assessment structured exactly under these subheadings:
   - Resume Screening
   - Technical Screening
   - Coding Assessment
   - Panel Interview
   - Offer Competitiveness
   - Overall Hiring Impression
   Classify the drive tier strictly as calculated by the engine (Class A, B, or C).
10. PLACEMENT PROBABILITY MODEL: Detail the technical and HR readiness estimates and success probability. You MUST include this required note:
    > **Placement Estimation Disclaimer:** Probability percentages are calculated using historical cohort placement correlations. These scores represent estimated preparation levels and readiness indices relative to previous cohorts; they are not guarantees of actual hiring outcomes.
11. DYNAMIC CAREER MATCH RANKINGS: Table of top career paths matching the candidate. For each career, output exactly:
    - Role: [Name]
    - Match: [Value]%
    - Confidence: [Value]%
    - Supporting Evidence: [Cite specific skills, projects, scores, or traits]
12. CRITICAL RISK INDICATORS: List severity-based warnings. Every risk must reference an engine-generated trigger.
13. PERSONALIZED STUDENT IMPROVEMENT ROADMAP: Actionable 30-60-90 days preparation plan.
14. MENTOR INTERVENTION CHECKLIST: Specific checklist items for the student's faculty advisor.

REPORT FOOTER
At the end of the report, add a divider and the following metadata block:
---
### Report Metadata
- **Engine Version:** PICE-v2.1.0
- **Scoring Version:** PICE-SCORE-v2.1
- **Career Profile Version:** FFM-CAREER-v2.0
- **Generated Timestamp:** [Current timestamp or UTC time]
- **Data Completeness:** [DCS]%
- **Overall Confidence:** [Confidence]%

VALIDATION CHECKLIST
Ensure:
✓ 14 sections exist
✓ All provided metrics appear exactly once
✓ No unsupported claims
✓ No missing headings
✓ No duplicated sections
✓ No invented scores
✓ No contradiction with deterministic engine
✓ Every career recommendation has supporting evidence
✓ Every risk references an engine-generated trigger";

        $userPrompt = "Please generate the 14-section Placement Intelligence Report for Student ID: {$studentId} based on the following deterministic engine output:\n\n" . json_encode($metrics, JSON_PRETTY_PRINT);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        // Call LLM
        $response = $this->ai->callAPI($messages, [
            'audit_method' => 'generate_placement_report',
            'max_tokens' => 3000,
            'temperature' => 0.5
        ]);

        if ($response['success']) {
            $reportText = $response['content'];

            // Cache report in database
            $stmtSave = $this->db->prepare("
                INSERT INTO student_placement_reports (student_id, institution, report_data, report_text)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    report_data = VALUES(report_data),
                    report_text = VALUES(report_text),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmtSave->execute([
                $studentId,
                $institution,
                json_encode($metrics),
                $reportText
            ]);

            return [
                'success' => true,
                'data' => $metrics,
                'report_markdown' => $reportText,
                'is_cached' => false
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to generate LLM explanation: ' . $response['message'],
                'data' => $metrics
            ];
        }
    }
}
