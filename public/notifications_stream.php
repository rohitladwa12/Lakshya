<?php
/**
 * Real-time Notification Streamer (SSE)
 * Uses Redis Pub/Sub to push notifications to students instantly.
 */

require_once __DIR__ . '/../config/bootstrap.php';
use App\Helpers\RedisHelper;

// Security Check: Only allow logged-in users to stream notifications
if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

// 1. Setup SSE Headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Important for Nginx/Apache

// Prevent PHP from timing out
set_time_limit(0);

try {
    // 2. Connect to Redis
    $redis = RedisHelper::getInstance();
    if (!$redis->isConnected()) {
        echo "event: error\n";
        echo "data: " . json_encode(['message' => 'Notification service unavailable']) . "\n\n";
        exit;
    }

    $client = $redis->getClient();

    // We use a pubSubLoop to efficiently wait for messages without burning CPU
    $pubsub = $client->pubSubLoop();
    
    // Subscribe to a general campus channel
    $pubsub->subscribe('campus_feed');

    // Keep sending a 'ping' every 30 seconds to prevent browser/server timeouts
    $lastPing = time();

    foreach ($pubsub as $message) {
        if ($message->kind === 'message') {
            // Push the actual notification
            echo "event: notification\n";
            echo "data: " . $message->payload . "\n\n";
        }

        // Send a heartbeat ping every 25 seconds
        if (time() - $lastPing > 25) {
            echo "event: ping\n";
            echo "data: {\"time\": " . time() . "}\n\n";
            $lastPing = time();
        }

        // Flush output buffer to browser
        if (ob_get_level() > 0) ob_flush();
        flush();

        // Check if the client disconnected (browser tab closed)
        if (connection_aborted()) {
            break;
        }
    }
} catch (Exception $e) {
    error_log("Notification Stream Fatal Error: " . $e->getMessage());
    echo "event: error\n";
    echo "data: " . json_encode(['message' => 'Service interruption. Please refresh.']) . "\n\n";
}
