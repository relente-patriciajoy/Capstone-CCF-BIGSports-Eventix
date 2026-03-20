<?php
/**
 * AJAX endpoint — returns participants for a specific table
 */
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole('event_head');
include('../../includes/db.php');

header('Content-Type: application/json');

$event_id     = (int)($_GET['event_id']     ?? 0);
$table_number = (int)($_GET['table_number'] ?? 0);
$user_id      = $_SESSION['user_id'];

// Verify event ownership
$chk = $conn->prepare("
    SELECT e.event_id FROM event e
    JOIN organizer o ON e.organizer_id = o.organizer_id
    JOIN user u ON o.contact_email = u.email
    WHERE e.event_id = ? AND u.user_id = ?
");
$chk->bind_param("ii", $event_id, $user_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    echo json_encode(['error' => 'Access denied']);
    exit();
}
$chk->close();

// Get table info
$tinfo = $conn->prepare("
    SELECT et.capacity, et.gender_assignment,
           COUNT(r.registration_id) AS occupants
    FROM event_table et
    LEFT JOIN registration r ON r.event_id = ? AND r.table_number = et.table_number
    WHERE et.event_id = ? AND et.table_number = ?
    GROUP BY et.table_id
");
$tinfo->bind_param("iii", $event_id, $event_id, $table_number);
$tinfo->execute();
$table_info = $tinfo->get_result()->fetch_assoc();
$tinfo->close();

// Get participants at this table
$pq = $conn->prepare("
    SELECT r.registration_id,
           CONCAT(u.first_name, ' ', u.last_name) AS name,
           u.email, u.gender
    FROM registration r
    JOIN user u ON r.user_id = u.user_id
    WHERE r.event_id = ? AND r.table_number = ?
    ORDER BY u.last_name
");
$pq->bind_param("ii", $event_id, $table_number);
$pq->execute();
$participants = $pq->get_result()->fetch_all(MYSQLI_ASSOC);
$pq->close();

// Get all tables for the reassign dropdown
$aq = $conn->prepare("
    SELECT et.table_number, et.capacity, et.gender_assignment,
           COUNT(r.registration_id) AS occupants
    FROM event_table et
    LEFT JOIN registration r ON r.event_id = ? AND r.table_number = et.table_number
    WHERE et.event_id = ?
    GROUP BY et.table_id
    ORDER BY et.table_number
");
$aq->bind_param("ii", $event_id, $event_id);
$aq->execute();
$all_tables = $aq->get_result()->fetch_all(MYSQLI_ASSOC);
$aq->close();

$conn->close();

echo json_encode([
    'table_number' => $table_number,
    'capacity'     => $table_info['capacity'] ?? 0,
    'occupants'    => $table_info['occupants'] ?? 0,
    'gender'       => ucfirst($table_info['gender_assignment'] ?? 'mixed'),
    'participants' => $participants,
    'all_tables'   => $all_tables,
]);