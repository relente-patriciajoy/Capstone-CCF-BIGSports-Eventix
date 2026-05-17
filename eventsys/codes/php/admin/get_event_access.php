<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole('admin');

require_once('../../includes/db.php');

header('Content-Type: application/json');

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;

if (!$user_id || !$event_id) {
    echo json_encode(['success' => false, 'error' => 'User ID and Event ID required']);
    exit();
}

// Get event access permissions for this user/event
$stmt = $conn->prepare("
    SELECT can_view, can_edit, can_delete, can_manage_attendance, can_export_data
    FROM event_access
    WHERE user_id = ? AND event_id = ?
");
$stmt->bind_param("ii", $user_id, $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'permissions' => [
            'can_view' => (bool)$row['can_view'],
            'can_edit' => (bool)$row['can_edit'],
            'can_delete' => (bool)$row['can_delete'],
            'can_manage_attendance' => (bool)$row['can_manage_attendance'],
            'can_export_data' => (bool)$row['can_export_data']
        ]
    ]);
} else {
    // No specific permissions set - return defaults
    echo json_encode([
        'success' => true,
        'permissions' => [
            'can_view' => true,
            'can_edit' => false,
            'can_delete' => false,
            'can_manage_attendance' => false,
            'can_export_data' => false
        ]
    ]);
}

$stmt->close();
?>