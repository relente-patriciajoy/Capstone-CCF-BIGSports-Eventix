<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && !in_array($error['type'], [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Shutdown error occurred',
            'error' => $error,
        ]);
    } else {
        ob_end_flush();
    }
});

session_start();
header('Content-Type: application/json');

function logDebug($message) {
    $logFile = __DIR__ . '/get_events_debug.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

logDebug('get_events.php started');

if (!isset($_SESSION['user_id'])) {
    logDebug('no session user_id');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

include('../../includes/db.php');
logDebug('included db.php');

if (!isset($conn) || !$conn) {
    logDebug('db connection missing');
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit();
}

// Fetch ALL events so jumping to any past/future month works
$query = "
    SELECT e.event_id, e.title, e.description,
           e.start_time, e.end_time, e.capacity,
           v.name as venue
    FROM event e
    LEFT JOIN venue v ON e.venue_id = v.venue_id
    ORDER BY e.start_time ASC
";

$result = $conn->query($query);
if (!$result) {
    logDebug('query failed: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database query failed', 'error' => $conn->error]);
    $conn->close();
    exit();
}

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

logDebug('query succeeded, events=' . count($events));
echo json_encode(['success' => true, 'events' => $events]);
$conn->close();
?>