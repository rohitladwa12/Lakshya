<?php
/**
 * ResumeParser
 * Deterministic parsing of resume text into structured sections.
 */

class ResumeParser {
    
    // Common section headers in resumes
    private $sectionHeaders = [
        'experience' => ['experience', 'work experience', 'employment history', 'professional experience', 'working history'],
        'education' => ['education', 'academic background', 'qualifications', 'academic profile'],
        'skills' => ['skills', 'technical skills', 'core competencies', 'relevant skills', 'technologies', 'expertise'],
        'projects' => ['projects', 'academic projects', 'personal projects', 'key projects'],
        'summary' => ['summary', 'professional summary', 'objective', 'about me', 'profile'],
        'links' => ['links', 'online profile', 'portfolio', 'social media', 'github', 'linkedin']
    ];

    /**
     * Parse raw resume text into a structured object
     */
    public function parse($text) {
        $structured = [
            'contact' => $this->extractContactInfo($text),
            'sections' => $this->extractSections($text),
            'raw_text_length' => strlen($text)
        ];

        // Further refine sections (e.g., extract list of skills)
        if (isset($structured['sections']['skills'])) {
            $structured['skills_list'] = $this->parseSkills($structured['sections']['skills']);
        }

        return $structured;
    }

    /**
     * Extract email, phone, and links using regex
     */
    private function extractContactInfo($text) {
        $info = [
            'email' => null,
            'phone' => null,
            'links' => []
        ];

        // Email
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $info['email'] = $matches[0];
        }

        // Phone (handles various formats)
        if (preg_match('/(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $text, $matches)) {
            $info['phone'] = $matches[0];
        }

        // URLs (LinkedIn, GitHub, Portfolios)
        if (preg_match_all('/https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*)/', $text, $matches)) {
            $info['links'] = array_unique($matches[0]);
        }

        return $info;
    }

    /**
     * Split text into sections based on headers using regex for robustness
     */
    private function extractSections($text) {
        $lines = explode("\n", $text);
        $sections = [];
        $currentSection = 'general';
        $sectionContent = [];

        // Build a giant regex for all headers
        $allHeaders = [];
        foreach ($this->sectionHeaders as $name => $list) {
            foreach ($list as $h) {
                $allHeaders[] = preg_quote($h, '/');
            }
        }
        $headerRegex = '/^(?:[#\-\*•\s\d\.प\>]*)\b(' . implode('|', $allHeaders) . ')\b[\s\:]*$/i';

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) continue;

            // Check if line looks like a header (short, and matches header keywords)
            $isHeader = false;
            if (strlen($trimmedLine) < 50 && preg_match($headerRegex, $trimmedLine, $matches)) {
                $matchedKeyword = strtolower($matches[1]);
                
                // Find which section this keyword belongs to
                foreach ($this->sectionHeaders as $sectionName => $headers) {
                    if (in_array($matchedKeyword, $headers)) {
                        // Save previous section
                        if (!empty($sectionContent)) {
                            $sections[$currentSection] = ($sections[$currentSection] ?? '') . "\n" . implode("\n", $sectionContent);
                        }
                        
                        $currentSection = $sectionName;
                        $sectionContent = [];
                        $isHeader = true;
                        break;
                    }
                }
            }

            if (!$isHeader) {
                $sectionContent[] = $trimmedLine;
            }
        }

        // Save last section
        if (!empty($sectionContent)) {
            $sections[$currentSection] = ($sections[$currentSection] ?? '') . "\n" . implode("\n", $sectionContent);
        }

        return array_map('trim', $sections);
    }

    /**
     * Clean up skills section into a list
     */
    private function parseSkills($skillsText) {
        // Split by commas, bullets, or newlines
        $skills = preg_split('/[,•\n|]|\s{2,}/', $skillsText);
        $cleaned = [];
        foreach ($skills as $skill) {
            $s = trim($skill);
            if (strlen($s) > 1 && strlen($s) < 50) {
                $cleaned[] = $s;
            }
        }
        return array_values(array_unique($cleaned));
    }
}
