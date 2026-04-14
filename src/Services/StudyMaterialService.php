<?php
/**
 * Study Material Service
 * Finds and curates downloadable study materials from online sources
 */

class StudyMaterialService {
    private $cacheDir;
    private $googleApiKey;
    private $searchEngineId;
    
    public function __construct() {
        $this->googleApiKey = getenv('GOOGLE_SEARCH_API_KEY');
        $this->searchEngineId = getenv('GOOGLE_SEARCH_ENGINE_ID');
        
        $this->cacheDir = __DIR__ . '/../../cache/study_materials';
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Search for study materials for a skill
     */
    public function searchMaterials($skill, $fileType = 'PDF') {
        // Check cache first (30 days)
        $cacheKey = md5($skill . $fileType);
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $materials = [];
        
        // Search GitHub repositories
        $githubMaterials = $this->searchGitHub($skill);
        $materials = array_merge($materials, $githubMaterials);
        
        // Search educational sites
        $eduMaterials = $this->searchEducationalSites($skill);
        $materials = array_merge($materials, $eduMaterials);
        
        // Search for PDFs using Google Custom Search (if configured)
        if ($this->googleApiKey && $this->searchEngineId) {
            $pdfMaterials = $this->searchGoogleCustom($skill, $fileType);
            $materials = array_merge($materials, $pdfMaterials);
        }
        
        // Rank materials
        $rankedMaterials = $this->rankMaterials($materials, $skill);
        
        // Cache results for 30 days
        $this->saveToCache($cacheKey, $rankedMaterials, 30 * 24 * 3600);
        
        return $rankedMaterials;
    }
    
    /**
     * Search GitHub for learning resources
     */
    private function searchGitHub($skill) {
        $materials = [];
        $queries = [
            "awesome-$skill",
            "$skill cheatsheet",
            "$skill learning resources"
        ];
        
        foreach ($queries as $query) {
            $url = "https://api.github.com/search/repositories?q=" . urlencode($query) . "&sort=stars&per_page=5";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Placement-Portal');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['items'])) {
                    foreach ($data['items'] as $repo) {
                        $materials[] = [
                            'title' => $repo['name'],
                            'description' => $repo['description'] ?? '',
                            'source_url' => $repo['html_url'],
                            'file_type' => 'Notes',
                            'source_website' => 'GitHub',
                            'author' => $repo['owner']['login'],
                            'relevance_score' => 0,
                            'stars' => $repo['stargazers_count']
                        ];
                    }
                }
            }
            
            // Rate limiting
            sleep(1);
        }
        
        return $materials;
    }
    
    /**
     * Search educational websites
     */
    private function searchEducationalSites($skill) {
        $materials = [];
        
        // GeeksforGeeks
        $gfgUrl = "https://www.geeksforgeeks.org/" . strtolower(str_replace(' ', '-', $skill)) . "-tutorial/";
        if ($this->urlExists($gfgUrl)) {
            $materials[] = [
                'title' => "$skill Tutorial - GeeksforGeeks",
                'description' => "Comprehensive tutorial and notes on $skill",
                'source_url' => $gfgUrl,
                'file_type' => 'Notes',
                'source_website' => 'GeeksforGeeks',
                'author' => 'GeeksforGeeks',
                'relevance_score' => 0
            ];
        }
        
        // TutorialsPoint
        $tpUrl = "https://www.tutorialspoint.com/" . strtolower(str_replace(' ', '_', $skill)) . "/index.htm";
        if ($this->urlExists($tpUrl)) {
            $materials[] = [
                'title' => "$skill Tutorial - TutorialsPoint",
                'description' => "Learn $skill with examples and exercises",
                'source_url' => $tpUrl,
                'file_type' => 'Notes',
                'source_website' => 'TutorialsPoint',
                'author' => 'TutorialsPoint',
                'relevance_score' => 0
            ];
        }
        
        // W3Schools (for web technologies)
        $webTechs = ['html', 'css', 'javascript', 'php', 'sql', 'python'];
        if (in_array(strtolower($skill), $webTechs)) {
            $w3Url = "https://www.w3schools.com/" . strtolower($skill) . "/";
            $materials[] = [
                'title' => "$skill Tutorial - W3Schools",
                'description' => "Interactive $skill tutorial with examples",
                'source_url' => $w3Url,
                'file_type' => 'Notes',
                'source_website' => 'W3Schools',
                'author' => 'W3Schools',
                'relevance_score' => 0
            ];
        }
        
        return $materials;
    }
    
    /**
     * Search using Google Custom Search API
     */
    private function searchGoogleCustom($skill, $fileType) {
        if (!$this->googleApiKey || !$this->searchEngineId) {
            return [];
        }
        
        $materials = [];
        $query = "$skill cheatsheet OR notes OR tutorial filetype:pdf";
        
        $url = "https://www.googleapis.com/customsearch/v1";
        $params = [
            'key' => $this->googleApiKey,
            'cx' => $this->searchEngineId,
            'q' => $query,
            'num' => 10
        ];
        
        $fullUrl = $url . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    $materials[] = [
                        'title' => $item['title'],
                        'description' => $item['snippet'] ?? '',
                        'source_url' => $item['link'],
                        'file_type' => $this->detectFileType($item['link']),
                        'source_website' => parse_url($item['link'], PHP_URL_HOST),
                        'author' => '',
                        'relevance_score' => 0
                    ];
                }
            }
        }
        
        return $materials;
    }
    
    /**
     * Rank materials by relevance
     */
    private function rankMaterials($materials, $skill) {
        $skillLower = strtolower($skill);
        
        foreach ($materials as &$material) {
            $score = 0;
            $text = strtolower($material['title'] . ' ' . $material['description']);
            
            // Keyword match
            if (strpos($text, $skillLower) !== false) {
                $score += 10;
            }
            
            // Source credibility
            $trustedSources = ['github', 'geeksforgeeks', 'tutorialspoint', 'w3schools', 'mdn', 'stackoverflow'];
            foreach ($trustedSources as $source) {
                if (strpos(strtolower($material['source_website']), $source) !== false) {
                    $score += 5;
                    break;
                }
            }
            
            // GitHub stars bonus
            if (isset($material['stars']) && $material['stars'] > 0) {
                $score += min(log10($material['stars']), 5);
            }
            
            // File type preference
            if ($material['file_type'] === 'PDF') {
                $score += 2;
            } elseif ($material['file_type'] === 'Cheatsheet') {
                $score += 3;
            }
            
            $material['relevance_score'] = round($score, 2);
        }
        
        // Sort by relevance
        usort($materials, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });
        
        return array_slice($materials, 0, 15); // Top 15
    }
    
    /**
     * Detect file type from URL
     */
    private function detectFileType($url) {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        switch ($ext) {
            case 'pdf':
                return 'PDF';
            case 'doc':
            case 'docx':
                return 'DOC';
            case 'ppt':
            case 'pptx':
                return 'PPT';
            default:
                return 'Notes';
        }
    }
    
    /**
     * Check if URL exists
     */
    private function urlExists($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
    
    /**
     * Get from cache
     */
    private function getFromCache($key) {
        $file = $this->cacheDir . '/' . $key . '.json';
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['expires'] > time()) {
                return $data['content'];
            }
        }
        
        return null;
    }
    
    /**
     * Save to cache
     */
    private function saveToCache($key, $content, $ttl) {
        $file = $this->cacheDir . '/' . $key . '.json';
        $data = [
            'expires' => time() + $ttl,
            'content' => $content
        ];
        
        file_put_contents($file, json_encode($data));
    }
}
