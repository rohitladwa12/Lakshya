<?php
/**
 * Real-time Notification Streamer (SSE)
 * Replaced persistent loop with safe non-blocking response to prevent Apache/FastCGI thread exhaustion.
 */

// Release session lock immediately if session is active
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Setup headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: close');

// Tell any legacy EventSource clients to wait 10 minutes before retrying
echo "retry: 600000\n\n";
echo "event: notice\n";
echo "data: " . json_encode(['status' => 'idle']) . "\n\n";
exit;

