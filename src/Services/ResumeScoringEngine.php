<?php
/**
 * ResumeScoringEngine
 * Hybrid scoring logic using rules and keyword matching.
 */

class ResumeScoringEngine {
    
    /**
     * Score a structured resume
     */
    public function score($structured) {
        $scores = [
            'overall' => 0,
            'sections' => [],
            'metrics' => [],
            'findings' => []
        ];

        // 1. Section Coverage Score (40% of total)
        $sectionScore = $this->calculateSectionCoverage($structured['sections'], $scores['findings']);
        $scores['sections']['coverage'] = $sectionScore;

        // 2. Metrics & Quality Score (30% of total)
        $qualityScore = $this->calculateQualityMetrics($structured['sections'], $scores['findings']);
        $scores['sections']['quality'] = $qualityScore;

        // 3. Length & Format Score (10% of total)
        $formatScore = $this->calculateFormatScore($structured, $scores['findings']);
        $scores['sections']['format'] = $formatScore;

        // 4. Keyword & Skill Score (20% of total)
        $skillScore = $this->calculateSkillScore($structured, $scores['findings']);
        $scores['sections']['skills'] = $skillScore;

        // Overall Weighted Score (0-100)
        $scores['overall'] = (int)($sectionScore * 0.4 + $qualityScore * 0.3 + $formatScore * 0.1 + $skillScore * 0.2);

        return $scores;
    }

    private function calculateSectionCoverage($sections, &$findings) {
        $required = [
            'experience' => 'Work Experience',
            'education' => 'Education',
            'skills' => 'Skills',
            'projects' => 'Projects'
        ];
        $found = 0;
        foreach ($required as $key => $label) {
            if (isset($sections[$key]) && strlen($sections[$key]) > 20) {
                // Check if it's too short to be meaningful
                if (strlen($sections[$key]) < 100 && $key !== 'skills') {
                    $findings[] = [
                        'severity' => 'warning',
                        'section' => $key,
                        'message' => "Thin Content: Your $label section is very short.",
                        'fix' => "A strong $label section needs detail. Aim for at least 3-5 high-impact bullet points."
                    ];
                }
                $found++;
            } else {
                // If it's in 'general', it means the parser saw it but couldn't split it
                $findings[] = [
                    'severity' => 'critical',
                    'section' => $key,
                    'message' => "Layout Error: $label section not clearly identified.",
                    'fix' => "We couldn't find a clear '$label' header. Ensure your headings are on their own line and use standard titles like '$label'."
                ];
            }
        }
        return ($found / count($required)) * 100;
    }

    private function calculateQualityMetrics($sections, &$findings) {
        $score = 100;
        $totalBullets = 0;
        $bulletsWithMetrics = 0;

        foreach (['experience', 'projects'] as $sec) {
            if (!isset($sections[$sec])) continue;
            
            // Check for metrics (numbers followed by %, $, or keywords)
            $text = $sections[$sec];
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                if (strlen($line) < 10) continue;
                $totalBullets++;
                if (preg_match('/\d+(?:\.\d+)?%|\$\d+|(?:improved|reduced|increased|saved|achieved|managed)\b.+\d+/i', $line)) {
                    $bulletsWithMetrics++;
                }
            }
        }

        if ($totalBullets > 0) {
            $ratio = $bulletsWithMetrics / $totalBullets;
            if ($ratio < 0.3) {
                $score = 50;
                $findings[] = [
                    'severity' => 'warning',
                    'section' => 'content',
                    'message' => "Passive Language: Only " . round($ratio * 100) . "% of points have quantifiable results.",
                    'fix' => "Recruiters love data. Every bullet should ideally show a number, %, or $ value. Use 'Accomplished [X] as measured by [Y], by doing [Z]'."
                ];
            }
        } else {
            $score = 0;
        }

        return $score;
    }

    private function calculateFormatScore($structured, &$findings) {
        $score = 100;
        $length = $structured['raw_text_length'];

        // Ideal length for fresher/junior is 2000-4000 chars (approx 1 page)
        if ($length > 8000) {
            $score -= 20;
            $findings[] = [
                'severity' => 'warning',
                'section' => 'format',
                'message' => "Resume is potentially too long (over 8000 characters).",
                'fix' => "Keep it to 1 page if you have less than 5 years of experience."
            ];
        }

        // Check for contact info
        if (empty($structured['contact']['email']) || empty($structured['contact']['phone'])) {
            $score -= 30;
            $findings[] = [
                'severity' => 'critical',
                'section' => 'contact',
                'message' => "Missing vital contact information (Email or Phone).",
                'fix' => "Ensure your email and phone number are clearly visible at the top."
            ];
        }

        return max(0, $score);
    }

    private function calculateSkillScore($structured, &$findings) {
        $score = 100;
        $skills = $structured['skills_list'] ?? [];
        $count = count($skills);

        if ($count < 5) {
            $score -= 40;
                $findings[] = [
                    'severity' => 'warning',
                    'section' => 'skills',
                    'message' => "Low Search Visibility: Only $count skills detected.",
                    'fix' => "Recruiters and ATS use keyword filters. List at least 10-15 relevant technologies, tools, and domain skills to be discoverable."
                ];
        } elseif ($count > 30) {
            $score -= 20;
            $findings[] = [
                'severity' => 'warning',
                'section' => 'skills',
                'message' => "Too many skills listed ($count). Avoid 'keyword stuffing'.",
                'fix' => "Focus on the skills you have deep expertise in and are relevant to your target role."
            ];
        }

        return max(0, $score);
    }
}
