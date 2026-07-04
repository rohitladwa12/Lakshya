<?php
namespace App\Services;

use PDO;
use Exception;

/**
 * DepartmentIntelligenceService
 * Aggregates PICE (Placement Intelligence) data across all department students.
 * Computes department-level metrics, risk analysis, skill analytics, and career distribution.
 * Stores pre-computed results in department_intelligence_cache for instant HOD dashboard loading.
 */
class DepartmentIntelligenceService {
    private $db;
    private $piceService;

    public function __construct() {
        $this->db = getDB();
        require_once __DIR__ . '/PlacementIntelligenceService.php';
        $this->piceService = new PlacementIntelligenceService();
    }

    /**
     * Generate or refresh department intelligence cache
     */
    public function generateCache($department, $disciplineFilters): array {
        set_time_limit(300);

        require_once __DIR__ . '/../Models/StudentProfile.php';
        $studentModel = new \StudentProfile();
        $filters = [
            'discipline' => $disciplineFilters,
            'semesters' => [5, 6, 7, 8]
        ];
        $students = $studentModel->getAllWithUsers($filters);

        if (empty($students)) {
            return ['success' => false, 'message' => 'No students found for this department.'];
        }

        $studentScores = [];
        $errors = 0;

        foreach ($students as $s) {
            $usn = trim((string)($s['usn'] ?? ''));
            if (empty($usn)) continue;
            $institution = $s['institution'] ?? 'GMU';

            try {
                $raw = $this->piceService->getRawData($usn, $institution);
                $metrics = $this->piceService->calculateDeterministicScores($raw);

                $readiness = $metrics['readiness_score'];
                $riskLevel = $readiness >= 70 ? 'ready' : ($readiness >= 45 ? 'improving' : 'at_risk');

                $resumeScore = 0;
                if (!empty($raw['resume']['score'])) {
                    $resumeScore = floatval($raw['resume']['score']);
                }

                $studentScores[] = [
                    'student_id' => $usn,
                    'name' => trim((string)($s['name'] ?? '')),
                    'semester' => (int)($s['semester'] ?? 0),
                    'institution' => $institution,
                    'cgpa' => round($raw['cgpa'], 2),
                    'backlogs' => (int)$raw['backlogs'],
                    'readiness_score' => $metrics['readiness_score'],
                    'coding_score' => $metrics['coding_score'],
                    'communication_score' => $metrics['communication_score'],
                    'project_score' => $metrics['project_score'],
                    'git_score' => $metrics['git_score'],
                    'behavioral_score' => $metrics['behavioral_score'],
                    'resume_score' => round($resumeScore, 1),
                    'dcs' => $metrics['dcs'],
                    'risk_level' => $riskLevel,
                    'drive_category' => $metrics['drive_category'],
                    'risks' => $metrics['risks'],
                    'top_careers' => array_slice(array_map(function($c) {
                        return ['role' => $c['role'], 'match' => $c['match_percentage']];
                    }, $metrics['career_matches']), 0, 3),
                    'mentor_checklist' => $metrics['mentor_checklist'],
                    'personality_ffm' => $metrics['personality_ffm'],
                    'skills' => $raw['skills'] ?? []
                ];
            } catch (Exception $e) {
                $errors++;
            }
        }

        if (empty($studentScores)) {
            return ['success' => false, 'message' => 'Failed to compute scores. Errors: ' . $errors];
        }

        $aggregated = $this->aggregateAll($studentScores);
        $cacheData = json_encode($aggregated, JSON_UNESCAPED_UNICODE);
        $studentData = json_encode($studentScores, JSON_UNESCAPED_UNICODE);

        $institutions = array_unique(array_column($studentScores, 'institution'));
        $inst = count($institutions) === 1 ? $institutions[0] : 'GMU';

        $stmt = $this->db->prepare("
            INSERT INTO department_intelligence_cache 
            (department, institution, total_students, cache_data, student_scores, generated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                total_students = VALUES(total_students),
                cache_data = VALUES(cache_data),
                student_scores = VALUES(student_scores),
                generated_at = NOW()
        ");
        $stmt->execute([$department, $inst, count($studentScores), $cacheData, $studentData]);

        return [
            'success' => true,
            'total_processed' => count($studentScores),
            'errors' => $errors
        ];
    }

    /**
     * Get cached department intelligence
     */
    public function getCachedData($department): ?array {
        $stmt = $this->db->prepare("
            SELECT cache_data, student_scores, total_students, generated_at 
            FROM department_intelligence_cache 
            WHERE department = ? ORDER BY generated_at DESC LIMIT 1
        ");
        $stmt->execute([$department]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        return [
            'data' => json_decode($row['cache_data'], true),
            'students' => json_decode($row['student_scores'], true),
            'total_students' => (int)$row['total_students'],
            'generated_at' => $row['generated_at']
        ];
    }

    /**
     * Aggregate all department-level metrics from per-student scores
     */
    private function aggregateAll($scores): array {
        $total = count($scores);
        $ready = $improving = $atRisk = 0;
        $sums = ['readiness' => 0, 'coding' => 0, 'communication' => 0, 'project' => 0, 'git' => 0, 'behavioral' => 0, 'resume' => 0, 'dcs' => 0];
        $semBreakdown = [];
        $skillCounts = [];
        $careerCounts = [];
        $risks = [
            'active_backlogs' => ['label' => 'Active Backlogs', 'icon' => 'fa-exclamation-triangle', 'color' => '#dc2626', 'students' => []],
            'low_coding' => ['label' => 'Low Coding (<45%)', 'icon' => 'fa-code', 'color' => '#ea580c', 'students' => []],
            'poor_resume' => ['label' => 'No/Poor Resume', 'icon' => 'fa-file-alt', 'color' => '#d97706', 'students' => []],
            'weak_mock' => ['label' => 'Weak Mock Interview (<50%)', 'icon' => 'fa-microphone-slash', 'color' => '#7c3aed', 'students' => []],
            'low_readiness' => ['label' => 'Low Readiness (<45%)', 'icon' => 'fa-chart-line', 'color' => '#be123c', 'students' => []],
            'low_dcs' => ['label' => 'Incomplete Profile (<60%)', 'icon' => 'fa-user-slash', 'color' => '#6b7280', 'students' => []],
        ];

        foreach ($scores as $s) {
            // Distribution
            if ($s['risk_level'] === 'ready') $ready++;
            elseif ($s['risk_level'] === 'improving') $improving++;
            else $atRisk++;

            // Sums
            $sums['readiness'] += $s['readiness_score'];
            $sums['coding'] += $s['coding_score'];
            $sums['communication'] += $s['communication_score'];
            $sums['project'] += $s['project_score'];
            $sums['git'] += $s['git_score'];
            $sums['behavioral'] += $s['behavioral_score'];
            $sums['resume'] += $s['resume_score'];
            $sums['dcs'] += $s['dcs'];

            // Semester breakdown
            $sem = $s['semester'];
            if ($sem > 0) {
                if (!isset($semBreakdown[$sem])) {
                    $semBreakdown[$sem] = ['count' => 0, 'readiness' => 0, 'coding' => 0, 'communication' => 0, 'project' => 0, 'resume' => 0];
                }
                $semBreakdown[$sem]['count']++;
                $semBreakdown[$sem]['readiness'] += $s['readiness_score'];
                $semBreakdown[$sem]['coding'] += $s['coding_score'];
                $semBreakdown[$sem]['communication'] += $s['communication_score'];
                $semBreakdown[$sem]['project'] += $s['project_score'];
                $semBreakdown[$sem]['resume'] += $s['resume_score'];
            }

            // Risks
            $ref = ['student_id' => $s['student_id'], 'name' => $s['name']];
            if ($s['backlogs'] > 0) $risks['active_backlogs']['students'][] = $ref;
            if ($s['coding_score'] < 45) $risks['low_coding']['students'][] = $ref;
            if ($s['resume_score'] < 30) $risks['poor_resume']['students'][] = $ref;
            if ($s['communication_score'] < 50) $risks['weak_mock']['students'][] = $ref;
            if ($s['readiness_score'] < 45) $risks['low_readiness']['students'][] = $ref;
            if ($s['dcs'] < 60) $risks['low_dcs']['students'][] = $ref;

            // Skills
            foreach ($s['skills'] as $skill) {
                $sk = strtolower(trim($skill));
                if (!empty($sk)) {
                    $skillCounts[$sk] = ($skillCounts[$sk] ?? 0) + 1;
                }
            }

            // Career distribution (top match per student)
            if (!empty($s['top_careers'])) {
                $topRole = $s['top_careers'][0]['role'];
                if (!isset($careerCounts[$topRole])) {
                    $careerCounts[$topRole] = ['count' => 0, 'total_match' => 0];
                }
                $careerCounts[$topRole]['count']++;
                $careerCounts[$topRole]['total_match'] += $s['top_careers'][0]['match'];
            }
        }

        // Compute averages
        $overview = [
            'total_students' => $total,
            'placement_ready' => $ready,
            'improving' => $improving,
            'at_risk' => $atRisk,
            'avg_readiness' => round($sums['readiness'] / $total, 1),
            'avg_coding' => round($sums['coding'] / $total, 1),
            'avg_communication' => round($sums['communication'] / $total, 1),
            'avg_project' => round($sums['project'] / $total, 1),
            'avg_git' => round($sums['git'] / $total, 1),
            'avg_behavioral' => round($sums['behavioral'] / $total, 1),
            'avg_resume' => round($sums['resume'] / $total, 1),
            'avg_dcs' => round($sums['dcs'] / $total, 1),
        ];

        // Semester averages
        foreach ($semBreakdown as $sem => &$data) {
            $c = $data['count'];
            $data['avg_readiness'] = round($data['readiness'] / $c, 1);
            $data['avg_coding'] = round($data['coding'] / $c, 1);
            $data['avg_communication'] = round($data['communication'] / $c, 1);
            $data['avg_project'] = round($data['project'] / $c, 1);
            $data['avg_resume'] = round($data['resume'] / $c, 1);
        }
        ksort($semBreakdown);

        // Risk counts
        $riskSummary = [];
        foreach ($risks as $key => $r) {
            $riskSummary[$key] = [
                'label' => $r['label'],
                'icon' => $r['icon'],
                'color' => $r['color'],
                'count' => count($r['students']),
                'students' => $r['students']
            ];
        }

        // Skills sorted by count
        arsort($skillCounts);
        $skillAnalytics = [];
        foreach (array_slice($skillCounts, 0, 20, true) as $skill => $count) {
            $skillAnalytics[] = [
                'skill' => ucfirst($skill),
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1)
            ];
        }

        // Career distribution
        $careerDistribution = [];
        foreach ($careerCounts as $role => $info) {
            $careerDistribution[] = [
                'role' => $role,
                'count' => $info['count'],
                'avg_match' => round($info['total_match'] / $info['count'], 1)
            ];
        }
        usort($careerDistribution, fn($a, $b) => $b['count'] <=> $a['count']);

        // Generate insights
        $insights = $this->generateInsights($overview, $riskSummary, $skillAnalytics, $careerDistribution, $semBreakdown);

        return [
            'overview' => $overview,
            'semester_breakdown' => $semBreakdown,
            'risk_summary' => $riskSummary,
            'skill_analytics' => $skillAnalytics,
            'career_distribution' => $careerDistribution,
            'ai_insights' => $insights
        ];
    }

    /**
     * Generate deterministic AI-style insights from aggregated data
     */
    private function generateInsights($overview, $risks, $skills, $careers, $semesters): array {
        $insights = [];
        $total = $overview['total_students'];

        // Readiness insight
        $readyPct = round(($overview['placement_ready'] / max(1, $total)) * 100, 1);
        if ($readyPct >= 70) {
            $insights[] = ['type' => 'success', 'icon' => 'fa-check-circle', 'text' => "{$readyPct}% of students are placement-ready. The department is in a strong position for upcoming drives."];
        } elseif ($readyPct >= 50) {
            $insights[] = ['type' => 'info', 'icon' => 'fa-info-circle', 'text' => "{$readyPct}% of students are placement-ready. Targeted interventions for the remaining pool could significantly improve outcomes."];
        } else {
            $insights[] = ['type' => 'warning', 'icon' => 'fa-exclamation-circle', 'text' => "Only {$readyPct}% of students are placement-ready. Department-wide skill development is recommended."];
        }

        // Weakest metric
        $metricLabels = ['avg_coding' => 'Coding', 'avg_communication' => 'Mock Interview', 'avg_project' => 'Project Quality', 'avg_resume' => 'Resume Quality', 'avg_git' => 'GitHub Activity'];
        $weakest = null;
        $weakestVal = 999;
        foreach ($metricLabels as $key => $label) {
            if ($overview[$key] < $weakestVal) {
                $weakestVal = $overview[$key];
                $weakest = $label;
            }
        }
        if ($weakest) {
            $insights[] = ['type' => 'warning', 'icon' => 'fa-arrow-down', 'text' => "{$weakest} ({$weakestVal}%) is the lowest-performing metric across the department. Priority improvement area."];
        }

        // Strongest metric
        $strongest = null;
        $strongestVal = 0;
        foreach ($metricLabels as $key => $label) {
            if ($overview[$key] > $strongestVal) {
                $strongestVal = $overview[$key];
                $strongest = $label;
            }
        }
        if ($strongest) {
            $insights[] = ['type' => 'success', 'icon' => 'fa-arrow-up', 'text' => "{$strongest} ({$strongestVal}%) is the department's strongest performance area."];
        }

        // Risk insight
        $topRisk = null;
        $topRiskCount = 0;
        foreach ($risks as $r) {
            if ($r['count'] > $topRiskCount) {
                $topRiskCount = $r['count'];
                $topRisk = $r['label'];
            }
        }
        if ($topRisk && $topRiskCount > 0) {
            $insights[] = ['type' => 'danger', 'icon' => 'fa-shield-alt', 'text' => "Top risk: {$topRisk} affects {$topRiskCount} students. Immediate attention recommended."];
        }

        // Career insight
        if (!empty($careers)) {
            $topCareer = $careers[0];
            $insights[] = ['type' => 'info', 'icon' => 'fa-briefcase', 'text' => "{$topCareer['role']} is the strongest career alignment ({$topCareer['count']} students, avg match: {$topCareer['avg_match']}%)."];
        }

        // At-risk insight
        if ($overview['at_risk'] > 0) {
            $insights[] = ['type' => 'danger', 'icon' => 'fa-user-times', 'text' => "{$overview['at_risk']} students are classified as high-risk (readiness below 45%). These students need immediate intervention."];
        }

        return $insights;
    }
}
