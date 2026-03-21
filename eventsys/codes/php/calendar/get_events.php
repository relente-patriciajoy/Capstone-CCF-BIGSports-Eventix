<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

include('../../includes/db.php');

$query = "
    SELECT e.event_id, e.title, e.description,
           e.start_time, e.end_time, e.capacity,
           v.name as venue
    FROM event e
    LEFT JOIN venue v ON e.venue_id = v.venue_id
    WHERE e.start_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
    ORDER BY e.start_time ASC
";

$result = $conn->query($query);
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

echo json_encode(['success' => true, 'events' => $events]);
$conn->close();
?>