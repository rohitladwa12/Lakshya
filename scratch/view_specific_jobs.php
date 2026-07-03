<?php
require_once __DIR__ . '/../config/bootstrap.php';
use App\Services\QueueService;

$jobIds = [
    'job_GMIT23CS107_6a44fc1315b5c2.93146774',
    'job_GMIT23CS107_6a44fc632a6d39.71807844'
];

foreach ($jobIds as $id) {
    echo "=== JOB: $id ===\n";
    $status = QueueService::getJobStatus($id);
    print_r($status);
    echo "---------------------------------\n\n";
}
