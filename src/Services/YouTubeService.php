<?php
/**
 * YouTube Service
 * Fetches and manages YouTube video resources for career roadmaps
 */

class YouTubeService {
    private $apiKey;
    private $baseUrl = 'https://www.googleapis.com/youtube/v3';
    private $cacheDir;
    
    public function __construct() {
        $this->apiKey = getenv('YOUTUBE_API_KEY');
        if (!$this->apiKey) {
            throw new Exception('YouTube API key not configured');
        }
        
        $this->cacheDir = __DIR__ . '/../../cache/youtube';
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Search for videos by query
     */
    public function searchVideos($query, $maxResults = 20) {
        // Check cache first
        $cacheKey = md5($query . $maxResults);
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $url = $this->baseUrl . '/search';
        $params = [
            'part' => 'snippet',
            'q' => $query,
            'type' => 'video',
            'maxResults' => $maxResults,
            'order' => 'relevance',
            'videoDuration' => 'medium', // 4-20 minutes
            'key' => $this->apiKey
        ];
        
        $response = $this->makeRequest($url, $params);
        
        if (!$response || !isset($response['items'])) {
            return [];
        }
        
        // Get video IDs for detailed info
        $videoIds = array_map(function($item) {
            return $item['id']['videoId'];
        }, $response['items']);
        
        // Get video details
        $videos = $this->getVideoDetails($videoIds);
        
        // Cache results for 7 days
        $this->saveToCache($cacheKey, $videos, 7 * 24 * 3600);
        
        return $videos;
    }
    
    /**
     * Get detailed information for videos
     */
    public function getVideoDetails($videoIds) {
        if (empty($videoIds)) {
            return [];
        }
        
        $url = $this->baseUrl . '/videos';
        $params = [
            'part' => 'snippet,contentDetails,statistics',
            'id' => implode(',', $videoIds),
            'key' => $this->apiKey
        ];
        
        $response = $this->makeRequest($url, $params);
        
        if (!$response || !isset($response['items'])) {
            return [];
        }
        
        $videos = [];
        foreach ($response['items'] as $item) {
            $videos[] = [
                'video_id' => $item['id'],
                'title' => $item['snippet']['title'],
                'description' => $item['snippet']['description'],
                'channel_name' => $item['snippet']['channelTitle'],
                'channel_id' => $item['snippet']['channelId'],
                'thumbnail_url' => $item['snippet']['thumbnails']['high']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
                'duration' => $this->formatDuration($item['contentDetails']['duration']),
                'view_count' => $item['statistics']['viewCount'] ?? 0,
                'published_at' => $item['snippet']['publishedAt']
            ];
        }
        
        return $videos;
    }
    
    /**
     * Search videos for a specific skill
     */
    public function searchForSkill($skill, $difficulty = 'beginner') {
        $queries = [];
        
        switch ($difficulty) {
            case 'beginner':
                $queries[] = "$skill tutorial for beginners";
                $queries[] = "learn $skill from scratch";
                break;
            case 'intermediate':
                $queries[] = "$skill intermediate tutorial";
                $queries[] = "$skill complete course";
                break;
            case 'advanced':
                $queries[] = "$skill advanced concepts";
                $queries[] = "$skill best practices";
                break;
        }
        
        $allVideos = [];
        foreach ($queries as $query) {
            $videos = $this->searchVideos($query, 10);
            $allVideos = array_merge($allVideos, $videos);
        }
        
        // Remove duplicates
        $uniqueVideos = [];
        $seenIds = [];
        foreach ($allVideos as $video) {
            if (!in_array($video['video_id'], $seenIds)) {
                $uniqueVideos[] = $video;
                $seenIds[] = $video['video_id'];
            }
        }
        
        // Rank by relevance
        return $this->rankByRelevance($uniqueVideos, $skill);
    }
    
    /**
     * Rank videos by relevance to skill
     */
    public function rankByRelevance($videos, $skillKeywords) {
        $keywords = is_array($skillKeywords) ? $skillKeywords : [$skillKeywords];
        
        foreach ($videos as &$video) {
            $score = 0;
            $text = strtolower($video['title'] . ' ' . $video['description']);
            
            // Check keyword presence
            foreach ($keywords as $keyword) {
                $keyword = strtolower($keyword);
                if (strpos($text, $keyword) !== false) {
                    $score += 10;
                }
            }
            
            // Boost for view count (logarithmic scale)
            if ($video['view_count'] > 0) {
                $score += log10($video['view_count']) / 2;
            }
            
            // Boost for recent videos (within 2 years)
            $publishedDate = strtotime($video['published_at']);
            $ageInDays = (time() - $publishedDate) / (60 * 60 * 24);
            if ($ageInDays < 730) { // 2 years
                $score += (730 - $ageInDays) / 365; // Max +2 points
            }
            
            $video['relevance_score'] = round($score, 2);
        }
        
        // Sort by relevance score
        usort($videos, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });
        
        return $videos;
    }
    
    /**
     * Filter videos by duration
     */
    public function filterByDuration($videos, $minMinutes = null, $maxMinutes = null) {
        return array_filter($videos, function($video) use ($minMinutes, $maxMinutes) {
            $duration = $this->parseDuration($video['duration']);
            
            if ($minMinutes && $duration < $minMinutes * 60) {
                return false;
            }
            
            if ($maxMinutes && $duration > $maxMinutes * 60) {
                return false;
            }
            
            return true;
        });
    }
    
    /**
     * Make HTTP request to YouTube API
     */
    private function makeRequest($url, $params) {
        $fullUrl = $url . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("YouTube API error: HTTP $httpCode");
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Format ISO 8601 duration to readable format
     */
    private function formatDuration($isoDuration) {
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $isoDuration, $matches);
        
        $hours = isset($matches[1]) ? (int)$matches[1] : 0;
        $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
        $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%d:%02d', $minutes, $seconds);
        }
    }
    
    /**
     * Parse duration string to seconds
     */
    private function parseDuration($duration) {
        $parts = explode(':', $duration);
        $seconds = 0;
        
        if (count($parts) == 3) {
            $seconds = $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
        } elseif (count($parts) == 2) {
            $seconds = $parts[0] * 60 + $parts[1];
        }
        
        return $seconds;
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
