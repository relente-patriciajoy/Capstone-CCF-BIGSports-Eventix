<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
require_once('../../includes/permission_functions.php');
requireRole('admin');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include('../../includes/db.php');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

$success_msg = '';
$error_msg = '';

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $event_id = (int)$_POST['event_id'];
    $target_user_id = (int)$_POST['user_id'];
    
    if ($action === 'update_permissions') {
        $permissions = [
            'view' => isset($_POST['can_view']) ? 1 : 0,
            'edit' => isset($_POST['can_edit']) ? 1 : 0,
            'delete' => isset($_POST['can_delete']) ? 1 : 0,
            'manage_attendance' => isset($_POST['can_manage_attendance']) ? 1 : 0,
            'export_data' => isset($_POST['can_export_data']) ? 1 : 0
        ];
        
        $reason = $_POST['reason'] ?? '';
        
        if (grantEventAccess($conn, $event_id, $target_user_id, $permissions, $user_id, $reason)) {
            $success_msg = "Permissions updated successfully!";
        } else {
            $error_msg = "Failed to update permissions.";
        }
    }
}

// Get all events
$events_query = $conn->query("SELECT event_id, title, organizer_id FROM event ORDER BY start_time DESC");
$selected_event = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;
$selected_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$users_access = [];
$event_details = null;

if ($selected_event) {
    // Get event details
    $stmt = $conn->prepare("
        SELECT e.event_id, e.title, e.start_time, o.name as organizer_name, o.contact_email
        FROM event e
        JOIN organizer o ON e.organizer_id = o.organizer_id
        WHERE e.event_id = ?
    ");
    $stmt->bind_param("i", $selected_event);
    $stmt->execute();
    $result = $stmt->get_result();
    $event_details = $result->fetch_assoc();
    $stmt->close();
    
    // Get users with event access or organizer
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            u.user_id, u.first_name, u.middle_name, u.last_name, u.email,
            ea.access_id, ea.can_view, ea.can_edit, ea.can_delete, ea.can_manage_attendance, ea.can_export_data,
            CASE WHEN o.contact_email = u.email THEN 1 ELSE 0 END as is_creator,
            u_admin.first_name as granted_by_first, u_admin.last_name as granted_by_last
        FROM user u
        LEFT JOIN event_access ea ON u.user_id = ea.user_id AND ea.event_id = ?
        LEFT JOIN organizer o ON o.organizer_id = (
            SELECT organizer_id FROM event WHERE event_id = ?
        )
        LEFT JOIN user u_admin ON ea.granted_by = u_admin.user_id
        WHERE ea.access_id IS NOT NULL 
           OR o.contact_email = u.email
           OR u.user_id = ?
        ORDER BY is_creator DESC, u.first_name ASC
    ");
    $stmt->bind_param("iii", $selected_event, $selected_event, $selected_event);
    $stmt->execute();
    $result = $stmt->get_result();
    $users_access = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Manage Event Access - Admin Panel</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/management.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .permission-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        .permission-granted {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            border: 1px solid #4caf50;
        }
        .permission-denied {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border: 1px solid #f44336;
        }
        .creator-badge {
            background: rgba(128, 0, 32, 0.2);
            color: #800020;
            border: 1px solid #800020;
            font-weight: 600;
        }
        .permission-form {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .permission-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 12px 0;
        }
        .permission-checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .permission-checkbox label {
            cursor: pointer;
            flex: 1;
            margin: 0;
        }
        .user-table {
            margin-top: 20px;
        }
        .user-row {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #800020;
        }
        .user-row.creator {
            border-left: 4px solid #ffd700;
        }
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .user-details h3 {
            margin: 0 0 8px 0;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <?php include('admin_sidebar.php'); ?>

        <div class="admin-content">
            <div class="admin-header">
                <h1>Manage Event Access</h1>
                <p>Control who can access, edit, delete, and manage each event</p>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <strong>Success!</strong> <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <strong>Error!</strong> <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <div class="admin-card">
                <h2>Select Event</h2>
                <form method="GET" style="margin: 20px 0;">
                    <select name="event_id" onchange="this.form.submit()" style="padding: 10px; border-radius: 5px; width: 100%; max-width: 400px;">
                        <option value="">-- Select an event --</option>
                        <?php
                        $events_query->data_seek(0);
                        while ($event = $events_query->fetch_assoc()): ?>
                            <option value="<?= $event['event_id'] ?>" <?= $selected_event == $event['event_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['title']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>

            <?php if ($event_details): ?>
                <div class="admin-card" style="margin-top: 20px;">
                    <h2>Event: <?= htmlspecialchars($event_details['title']) ?></h2>
                    <p>Organizer: <?= htmlspecialchars($event_details['organizer_name']) ?> (<?= htmlspecialchars($event_details['contact_email']) ?>)</p>
                    <p>Start Date: <?= date('M d, Y @ H:i', strtotime($event_details['start_time'])) ?></p>

                    <h3 style="margin-top: 30px; margin-bottom: 20px;">User Access</h3>

                    <?php if (empty($users_access)): ?>
                        <p style="color: #999;">No users have been granted access to this event yet.</p>
                    <?php else: ?>
                        <div class="user-table">
                            <?php foreach ($users_access as $user): ?>
                                <div class="user-row <?= $user['is_creator'] ? 'creator' : '' ?>">
                                    <div class="user-info">
                                        <div class="user-details">
                                            <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                                            <p style="margin: 4px 0; color: #666; font-size: 0.9rem;"><?= htmlspecialchars($user['email']) ?></p>
                                            <?php if ($user['is_creator']): ?>
                                                <span class="permission-badge creator-badge">
                                                    <i data-lucide="star"></i> Event Creator
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($user['access_id']): ?>
                                                <div style="margin-top: 8px;">
                                                    <?php if ($user['can_view']): ?>
                                                        <span class="permission-badge permission-granted">Can View</span>
                                                    <?php endif; ?>
                                                    <?php if ($user['can_edit']): ?>
                                                        <span class="permission-badge permission-granted">Can Edit</span>
                                                    <?php endif; ?>
                                                    <?php if ($user['can_delete']): ?>
                                                        <span class="permission-badge permission-granted">Can Delete</span>
                                                    <?php endif; ?>
                                                    <?php if ($user['can_manage_attendance']): ?>
                                                        <span class="permission-badge permission-granted">Manage Attendance</span>
                                                    <?php endif; ?>
                                                    <?php if ($user['can_export_data']): ?>
                                                        <span class="permission-badge permission-granted">Export Data</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" onclick="editPermissions(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>')" 
                                                class="btn btn-primary" style="white-space: nowrap;">
                                            Edit Permissions
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 30px;">
                        <h3>Add User Access</h3>
                        <form method="GET" style="display: flex; gap: 10px; margin: 15px 0;">
                            <input type="hidden" name="event_id" value="<?= $selected_event ?>">
                            <select name="user_id" required style="padding: 10px; border-radius: 5px; flex: 1; min-width: 200px;">
                                <option value="">-- Select a user --</option>
                                <?php
                                $all_users = $conn->query("SELECT user_id, first_name, middle_name, last_name, email FROM user WHERE status = 'active' ORDER BY first_name");
                                while ($user = $all_users->fetch_assoc()): ?>
                                    <option value="<?= $user['user_id'] ?>">
                                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" class="btn btn-success">Add User</button>
                        </form>
                    </div>
                </div>

                <?php if ($selected_user): ?>
                    <?php 
                    $stmt = $conn->prepare("SELECT first_name, last_name, email FROM user WHERE user_id = ?");
                    $stmt->bind_param("i", $selected_user);
                    $stmt->execute();
                    $user_info = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    $perms = getEventPermissions($conn, $selected_user, $selected_event);
                    ?>
                    
                    <div class="permission-form">
                        <h3>Set Permissions for <?= htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']) ?></h3>
                        <p style="margin: 5px 0 15px 0; color: #666; font-size: 0.9rem;"><?= htmlspecialchars($user_info['email']) ?></p>

                        <?php if ($perms['is_creator']): ?>
                            <div style="background: rgba(76, 175, 80, 0.1); padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #4caf50;">
                                <strong style="color: #4caf50;">✓ This user is the event creator</strong>
                                <p style="margin: 8px 0 0 0; color: #555; font-size: 0.9rem;">Creators have full access to their events by default.</p>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_permissions">
                                <input type="hidden" name="event_id" value="<?= $selected_event ?>">
                                <input type="hidden" name="user_id" value="<?= $selected_user ?>">

                                <div class="permission-checkbox">
                                    <input type="checkbox" id="can_view" name="can_view" <?= $perms['can_view'] ? 'checked' : '' ?>>
                                    <label for="can_view">Can View Event</label>
                                </div>

                                <div class="permission-checkbox">
                                    <input type="checkbox" id="can_edit" name="can_edit" <?= $perms['can_edit'] ? 'checked' : '' ?>>
                                    <label for="can_edit">Can Edit Event Details</label>
                                </div>

                                <div class="permission-checkbox">
                                    <input type="checkbox" id="can_delete" name="can_delete" <?= $perms['can_delete'] ? 'checked' : '' ?>>
                                    <label for="can_delete">Can Delete Event</label>
                                </div>

                                <div class="permission-checkbox">
                                    <input type="checkbox" id="can_manage_attendance" name="can_manage_attendance" <?= $perms['can_manage_attendance'] ? 'checked' : '' ?>>
                                    <label for="can_manage_attendance">Can Manage Attendance</label>
                                </div>

                                <div class="permission-checkbox">
                                    <input type="checkbox" id="can_export_data" name="can_export_data" <?= $perms['can_export_data'] ? 'checked' : '' ?>>
                                    <label for="can_export_data">Can Export Data</label>
                                </div>

                                <div style="margin: 20px 0;">
                                    <label for="reason">Reason (optional):</label>
                                    <textarea id="reason" name="reason" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ddd; min-height: 60px;"></textarea>
                                </div>

                                <button type="submit" class="btn btn-success">Save Permissions</button>
                                <a href="?event_id=<?= $selected_event ?>" class="btn btn-secondary" style="text-decoration: none;">Cancel</a>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function editPermissions(userId, userName) {
        const eventId = new URLSearchParams(window.location.search).get('event_id');
        window.location.href = `?event_id=${eventId}&user_id=${userId}`;
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
    </script>
</body>
</html>
