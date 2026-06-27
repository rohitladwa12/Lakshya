<?php

namespace App\Services;

class LeaderboardService {
    
    /**
     * Get all rankings based on filters
     * @param array $filters ['institution', 'discipline', 'semesters']
     * @return array
     */
    public static function getRankings($filters = []) {
        $db = getDB();
        
        // 1. Fetch Students
        $students = self::fetchStudents($filters);
        if (empty($students)) return [];

        $usns = array_column($students, 'usn');
        $usnList = "'" . implode("','", array_map('addslashes', $usns)) . "'";

        // Build Aadhar list for GMIT students to catch records stored under Aadhar
        $gmitAadhars = [];
        foreach ($students as $s) {
            if ($s['institution'] === INSTITUTION_GMIT && !empty($s['aadhar'])) {
                $gmitAadhars[] = $s['aadhar'];
            }
        }
        // Extended list: USNs + Aadhar numbers combined for queries that may store either
        $allIds = array_unique(array_merge($usns, $gmitAadhars));
        $allIdList = "'" . implode("','", array_map('addslashes', $allIds)) . "'";

        // Partition USNs by institution for academic fetching
        $gmuUsns = [];
        $gmitUsns = [];
        $gmitAadharMap = []; // usn => aadhar for GMIT
        foreach ($students as $s) {
            if ($s['institution'] === INSTITUTION_GMU) $gmuUsns[] = $s['usn'];
            else {
                $gmitUsns[] = $s['usn'];
                if (!empty($s['aadhar'])) $gmitAadharMap[$s['usn']] = $s['aadhar'];
            }
        }

        // 2. Fetch Performance Data (use extended ID list so GMIT Aadhar-stored records are found)
        $scoresByUsn = self::fetchUnifiedScores($allIdList);
        $mockDataByUsn = self::fetchMockData($allIdList);
        $portfolioData = self::fetchPortfolioData($allIdList);
        $taskDataByUsn = self::fetchTaskCompletions($allIdList);
        $academicHistory = self::fetchAcademicHistory($gmuUsns, $gmitUsns, $gmitAadharMap);
        $skillData = self::fetchStudentSkills($allIdList);
        $assessmentTimestamps = self::fetchAssessmentTimestamps($allIdList);

        // Build reverse Aadhar-to-USN map for score consolidation
        $aadharToUsn = [];
        foreach ($gmitAadharMap as $usn => $aadhar) {
            $aadharToUsn[strtolower($aadhar)] = strtolower($usn);
        }

        // 3. Process into Ranking
        $rankings = [];
        foreach ($students as $s) {
            $usn = $s['usn'];
            $lowUsn = strtolower($usn);
            
            // For GMIT: merge scores stored under Aadhar into the USN key
            $aadhar = $s['aadhar'] ?? '';
            $lowAadhar = strtolower($aadhar);
            $scores = $scoresByUsn[$lowUsn] ?? ($scoresByUsn[$lowAadhar] ?? []);
            $mocks  = $mockDataByUsn[$lowUsn] ?? ($mockDataByUsn[$lowAadhar] ?? []);
            $tasks  = $taskDataByUsn[$lowUsn] ?? ($taskDataByUsn[$lowAadhar] ?? []);
            $port   = $portfolioData[$lowUsn] ?? ($portfolioData[$lowAadhar] ?? []);
            $skills = $skillData[$lowUsn] ?? ($skillData[$lowAadhar] ?? []);
            $tStamps = $assessmentTimestamps[$lowUsn] ?? ($assessmentTimestamps[$lowAadhar] ?? []);
            
            // Pillar Processing
            $pillars = self::calculatePillars($scores, $mocks, $tasks);
            
            // AI Score with Strict Hybrid Model (Weighted + Square-Count Penalty)
            $attemptedCount = 0;
            if ($pillars['aptitude'] > 0) $attemptedCount++;
            if ($pillars['technical'] > 0) $attemptedCount++;
            if ($pillars['hr'] > 0) $attemptedCount++;

            $weightedScore = ($pillars['technical'] * 0.5) + ($pillars['aptitude'] * 0.25) + ($pillars['hr'] * 0.25);
            $squarePenalty = pow($attemptedCount / 3.0, 3);
            $assessmentScore = $weightedScore * $squarePenalty;

            // Portfolio Score
            $pS = $port['Skill'] ?? 0;
            $pP = $port['Project'] ?? 0;
            $portfolioScore = min(50, $pS * 1) + min(50, $pP * 2);

            // Final Total Points
            $rawTotal = ($assessmentScore * 0.7) + ($portfolioScore * 0.3);
            $totalScore = $rawTotal;

            // Academic History (for filtering and display)
            $history = $academicHistory[$lowUsn] ?? ($academicHistory[$lowAadhar] ?? []);

            $rankings[] = [
                'name' => $s['name'],
                'usn' => $usn,
                'aadhar' => $s['aadhar'] ?? '',
                'institution' => $s['institution'],
                'discipline' => $s['department'],
                'sgpa' => (float)($s['sgpa'] ?? 0),
                'academic_history' => $history,
                'skills' => $skills,
                'aptitude' => $pillars['aptitude'],
                'technical' => $pillars['technical'],
                'hr' => $pillars['hr'],
                'ai_avg' => round($assessmentScore, 1),
                'ai_count' => $attemptedCount,
                'portfolio' => $portfolioScore,
                'total' => round((float)$totalScore, 1)
            ];
        }

        // 4. Advanced Filtering (if needed)
        if (!empty($filters)) {
            $rankings = self::applyAdvancedFilters($rankings, $filters);
        }

        // Sort by Total Score Descending
        usort($rankings, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        // Add Rank Positions
        foreach ($rankings as $i => &$r) {
            $r['rank'] = $i + 1;
        }

        return $rankings;
    }

    /**
     * Get rankings with daily historical comparison
     */
    public static function getRankingsWithHistory($filters = []) {
        $rankings = self::getRankings($filters);
        if (empty($rankings)) return [];

        $cacheDir = __DIR__ . '/../../storage/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $cacheKey = md5(json_encode($filters));
        $cacheFile = $cacheDir . '/leaderboard_' . $cacheKey . '.json';

        $snapshot = [];
        $generateNewSnapshot = true;

        if (file_exists($cacheFile)) {
            // Check if older than 24 hours
            if (time() - filemtime($cacheFile) < 86400) {
                $snapshot = json_decode(file_get_contents($cacheFile), true);
                // Do not recreate snapshot if it's fresh enough and valid
                if (is_array($snapshot) && count($snapshot) > 0) {
                    $generateNewSnapshot = false;
                }
            }
        }

        if ($generateNewSnapshot) {
            foreach ($rankings as $r) {
                $snapshot[$r['usn']] = $r['rank'];
            }
            @file_put_contents($cacheFile, json_encode($snapshot));
        }

        foreach ($rankings as &$r) {
            $r['previous_rank'] = $snapshot[$r['usn']] ?? $r['rank'];
        }

        return $rankings;
    }

    private static function fetchStudents($filters) {
        $studentModel = new \StudentProfile();
        return $studentModel->getAllWithUsers($filters);
    }

    private static function fetchUnifiedScores($usnList) {
        $stmt = getDB()->query("SELECT usn, assessment_type, AVG(score) as avg_score 
                                FROM unified_ai_assessments 
                                WHERE usn IN ($usnList) 
                                GROUP BY usn, assessment_type");
        $scores = [];
        while ($row = $stmt->fetch()) {
            $type = trim($row['assessment_type']);
            $scores[strtolower($row['usn'])][$type] = (float)$row['avg_score'];
        }
        return $scores;
    }

    private static function fetchMockData($usnList) {
        $stmt = getDB()->query("SELECT student_id, role_name, overall_score, report_content 
                                FROM mock_ai_interview_sessions 
                                WHERE student_id IN ($usnList) AND status = 'completed'");
        $mocks = [];
        while ($row = $stmt->fetch()) {
            $mocks[strtolower($row['student_id'])][] = $row;
        }
        return $mocks;
    }

    private static function fetchTaskCompletions($usnList) {
        $stmt = getDB()->query("SELECT tc.student_id, ct.task_type, tc.score 
                                FROM task_completions tc
                                JOIN coordinator_tasks ct ON tc.task_id = ct.id
                                WHERE tc.student_id IN ($usnList)");
        $data = [];
        while ($row = $stmt->fetch()) {
            $data[strtolower($row['student_id'])][] = $row;
        }
        return $data;
    }

    private static function fetchPortfolioData($usnList) {
        $stmt = getDB()->query("SELECT student_id, category, COUNT(*) as count 
                                FROM student_portfolio 
                                WHERE is_verified = 1 AND category IN ('Skill', 'Project')
                                AND student_id IN ($usnList)
                                GROUP BY student_id, category");
        $data = [];
        while ($row = $stmt->fetch()) {
            $data[strtolower($row['student_id'])][$row['category']] = (int)$row['count'];
        }
        return $data;
    }

    private static function fetchAcademicHistory($gmuUsns, $gmitUsns, $gmitAadharMap = []) {
        $history = [];

        // GMIT (LOCAL) — search by student_id (USN/enrollment) AND Aadhar
        if (!empty($gmitUsns)) {
            $allGmitIds = array_unique(array_merge($gmitUsns, array_values($gmitAadharMap)));
            $list = "'" . implode("','", array_map('addslashes', $allGmitIds)) . "'";
            $stmt = getDB()->query("SELECT student_id, semester, sgpa, academic_year 
                                    FROM student_sem_sgpa 
                                    WHERE student_id IN ($list) 
                                    ORDER BY semester ASC");
            while ($row = $stmt->fetch()) {
                $sid = strtolower($row['student_id']);
                $histRow = ['sgpa' => (float)$row['sgpa'], 'year' => $row['academic_year']];
                $history[$sid][$row['semester']] = $histRow;
                // Also index by USN if this was stored under Aadhar
                foreach ($gmitAadharMap as $usn => $aadhar) {
                    if (strtolower($aadhar) === $sid) {
                        $history[strtolower($usn)][$row['semester']] = $histRow;
                    }
                }
            }
        }

        // GMU (REMOTE)
        if (!empty($gmuUsns)) {
            $list = "'" . implode("','", array_map('addslashes', $gmuUsns)) . "'";
            $prefix = DB_GMU_PREFIX;
            $stmt = getDB('gmu')->query("SELECT usn as student_id, sem as semester, sgpa, academic_year 
                                         FROM {$prefix}ad_student_approved 
                                         WHERE usn IN ($list) 
                                         ORDER BY sem ASC");
            while ($row = $stmt->fetch()) {
                $history[strtolower($row['student_id'])][$row['semester']] = [
                    'sgpa' => (float)$row['sgpa'],
                    'year' => $row['academic_year']
                ];
            }
        }

        return $history;
    }

    private static function fetchStudentSkills($usnList) {
        $stmt = getDB()->query("SELECT student_id, title 
                                FROM student_portfolio 
                                WHERE category = 'Skill' AND is_verified = 1 
                                AND student_id IN ($usnList)");
        $skills = [];
        while ($row = $stmt->fetch()) {
            $skills[strtolower($row['student_id'])][] = $row['title'];
        }
        return $skills;
    }

    public static function getAllAvailableSkills() {
        $stmt = getDB()->query("SELECT DISTINCT title 
                                FROM student_portfolio 
                                WHERE category = 'Skill' AND is_verified = 1 
                                ORDER BY title ASC");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private static function applyAdvancedFilters($rankings, $filters) {
        return array_filter($rankings, function($r) use ($filters) {
            // 1. Basic Thresholds
            if (isset($filters['min_total']) && $r['total'] < $filters['min_total']) return false;
            if (isset($filters['min_aptitude']) && $r['aptitude'] < $filters['min_aptitude']) return false;
            if (isset($filters['min_technical']) && $r['technical'] < $filters['min_technical']) return false;
            if (isset($filters['min_hr']) && $r['hr'] < $filters['min_hr']) return false;

            // 2. SGPA in All Semesters
            if (isset($filters['min_sgpa_all'])) {
                $minRequired = (float)$filters['min_sgpa_all'];
                if (empty($r['academic_history'])) return false; // Fail if no history found
                foreach ($r['academic_history'] as $sem) {
                    if ($sem['sgpa'] > 0 && $sem['sgpa'] < $minRequired) return false;
                }
            }

            // 3. Required Skills
            if (!empty($filters['required_skills'])) {
                $required = array_map('strtolower', (array)$filters['required_skills']);
                $hasSkills = array_map('strtolower', $r['skills']);
                foreach ($required as $req) {
                    $found = false;
                    foreach ($hasSkills as $has) {
                        if (strpos($has, $req) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) return false;
                }
            }

            return true;
        });
    }

    private static function fetchAssessmentTimestamps($usnList) {
        $db = getDB();
        $timestamps = [];

        // 1. Unified Assessments
        $stmt = $db->query("SELECT usn, started_at FROM unified_ai_assessments WHERE usn IN ($usnList) ORDER BY started_at ASC");
        while ($row = $stmt->fetch()) {
            $timestamps[strtolower($row['usn'])][] = strtotime($row['started_at']);
        }

        // 2. Mock Interviews
        $stmt = $db->query("SELECT student_id as usn, started_at FROM mock_ai_interview_sessions WHERE student_id IN ($usnList) AND status = 'completed' ORDER BY started_at ASC");
        while ($row = $stmt->fetch()) {
            $timestamps[strtolower($row['usn'])][] = strtotime($row['started_at']);
        }

        return $timestamps;
    }

    private static function calculateInactivityPenalty($userTimestamps) {
        if (empty($userTimestamps)) return 0;
        
        sort($userTimestamps);
        $penalty = 0;
        $oneDay = 86400;
        $policyStartDate = strtotime('2026-04-30 00:00:00'); // Penalty starts today

        // Calculate historical gaps, but only those occurring after the policy start date
        for ($i = 0; $i < count($userTimestamps) - 1; $i++) {
            $gapStart = max($policyStartDate, $userTimestamps[$i]);
            $gapEnd = $userTimestamps[$i+1];
            
            if ($gapEnd > $gapStart) {
                $gap = $gapEnd - $gapStart;
                if ($gap > $oneDay) {
                    $penalty += floor($gap / $oneDay);
                }
            }
        }

        // Calculate current gap since last activity, relative to policy start
        $lastActivity = max($policyStartDate, end($userTimestamps));
        $currentGap = time() - $lastActivity;
        
        if ($currentGap > $oneDay) {
            $penalty += floor($currentGap / $oneDay);
        }

        return $penalty;
    }

    private static function calculatePillars($userScores, $userMocks, $userTasks = []) {
        $tempPillars = ['aptitude' => [], 'technical' => [], 'hr' => []];

        // Process Unified
        foreach ($userScores as $type => $score) {
            $lType = strtolower($type);
            if (in_array($lType, ['aptitude', 'cognitive', 'nqt foundation'])) $tempPillars['aptitude'][] = $score;
            elseif (in_array($lType, ['hr', 'behavioral', 'mock hr'])) $tempPillars['hr'][] = $score;
            else $tempPillars['technical'][] = $score;
        }

        // Process Assigned Tasks
        foreach ($userTasks as $t) {
            $type = strtolower($t['task_type']);
            $score = (float)$t['score'];
            if ($type === 'aptitude') $tempPillars['aptitude'][] = $score;
            elseif ($type === 'technical') $tempPillars['technical'][] = $score;
            elseif ($type === 'hr') $tempPillars['hr'][] = $score;
        }

        // Process Mocks (with sniffing)
        foreach ($userMocks as $m) {
            $report = $m['report_content'] ?? '';
            $role = strtolower($m['role_name'] ?? '');
            $overall = (float)($m['overall_score'] ?? 0);
            $foundSection = false;

            if (preg_match('/Aptitude:\s*\[?(\d+)\]?\s*\/\s*10/i', $report, $matches)) {
                $tempPillars['aptitude'][] = (float)$matches[1] * 10;
                $foundSection = true;
            }
            if (preg_match('/Technical(?:\s+Proficiency)?:\s*\[?(\d+)\]?\s*\/\s*10/i', $report, $matches)) {
                $tempPillars['technical'][] = (float)$matches[1] * 10;
                $foundSection = true;
            }
            if (preg_match('/HR:\s*\[?(\d+)\]?\s*\/\s*10/i', $report, $matches)) {
                $tempPillars['hr'][] = (float)$matches[1] * 10;
                $foundSection = true;
            }

            if (!$foundSection) {
                $context = $role . ' ' . strip_tags($report);
                if (preg_match('/aptitude|quant|logical|nqt/i', $context)) $tempPillars['aptitude'][] = $overall;
                elseif (preg_match('/hr|behavioral|culture|managerial/i', $context)) $tempPillars['hr'][] = $overall;
                else $tempPillars['technical'][] = $overall;
            }
        }

        return [
            'aptitude' => !empty($tempPillars['aptitude']) ? array_sum($tempPillars['aptitude']) / count($tempPillars['aptitude']) : 0,
            'technical' => !empty($tempPillars['technical']) ? array_sum($tempPillars['technical']) / count($tempPillars['technical']) : 0,
            'hr' => !empty($tempPillars['hr']) ? array_sum($tempPillars['hr']) / count($tempPillars['hr']) : 0,
        ];
    }
}
