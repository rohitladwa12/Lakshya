<?php

require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure student role
if (!isLoggedIn() || getRole() !== ROLE_STUDENT) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized session. Please log in as a student.'
    ]);
    exit;
}

$userId = getUserId();

$inputJSON = file_get_contents('php://input');
$inputData = json_decode($inputJSON, true) ?? [];
$requestData = array_merge($_REQUEST, $inputData);

$action = $requestData['action'] ?? 'search';

if ($action === 'quota') {
    echo json_encode([
        'success' => true,
        'unlimited' => true
    ]);
    exit;
}

// Endpoint: Multi-Parameter AI Relevance Job Search
$params = [
    'query' => trim((string)($requestData['query'] ?? '')),
    'location' => trim((string)($requestData['location'] ?? 'any')),
    'experience' => trim((string)($requestData['experience'] ?? 'any')),
    'work_mode' => trim((string)($requestData['work_mode'] ?? 'any')),
    'emp_type' => trim((string)($requestData['emp_type'] ?? 'any')),
    'skills' => trim((string)($requestData['skills'] ?? '')),
    'recency' => trim((string)($requestData['recency'] ?? 'any')),
    'min_salary' => (float)($requestData['min_salary'] ?? 0),
    'page' => (int)($requestData['page'] ?? 1)
];

$result = \App\Services\JobSearchService::searchJobs($params, $userId);

if (!$result['success']) {
    http_response_code(429);
}

echo json_encode($result);
exit;
