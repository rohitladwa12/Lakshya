<?php
require_once __DIR__ . '/../config/bootstrap.php';

$t = microtime(true);
$db = getDB();
echo "Local DB: " . round((microtime(true)-$t)*1000) . "ms\n";

$t = microtime(true);
$remote = getDB('gmu');
echo "Remote GMU DB: " . round((microtime(true)-$t)*1000) . "ms\n";

// Test the main dashboard query: job postings
$t = microtime(true);
require_once __DIR__ . '/../src/Models/JobPosting.php';
$j = new JobPosting();
$jobs = $j->getActiveJobs();
echo "getActiveJobs (" . count($jobs) . " jobs): " . round((microtime(true)-$t)*1000) . "ms\n";

// Test the intelligence service init
$t = microtime(true);
require_once __DIR__ . '/../src/Services/StudentIntelligenceService.php';
echo "StudentIntelligenceService load: " . round((microtime(true)-$t)*1000) . "ms\n";
