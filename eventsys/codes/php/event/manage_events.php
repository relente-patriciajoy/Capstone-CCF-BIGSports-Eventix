<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole(['event_head', 'admin']);
include('../../includes/db.php');
require_once('../../includes/permission_functions.php');

$user_id = $_SESSION['user_id'];
$message = ""; $error = "";

$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id); $role_stmt->execute();
$role_stmt->bind_result($role); $role_stmt->fetch(); $role_stmt->close();
if (empty($role)) $role = 'user';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_event'])) {
    if (!hasPermission($conn, $user_id, 'event.create')) { $error = "You don't have permission to create events."; }
    else {
        $title = $_POST['title']; $description = $_POST['description'];
        $start_time = $_POST['start_time']; $end_time = $_POST['end_time'];
        $venue_name = $_POST['venue_name']; $venue_address = $_POST['venue_address']; $venue_city = $_POST['venue_city'];
        $capacity = $_POST['capacity']; $category_id = $_POST['category_id'];
        $has_tables = isset($_POST['has_tables']) ? 1 : 0;
        $gender_separated = isset($_POST['gender_separated']) ? 1 : 0;

        $venue_stmt = $conn->prepare("INSERT INTO venue (name, address, city) VALUES (?, ?, ?)");
        $venue_stmt->bind_param("sss", $venue_name, $venue_address, $venue_city); $venue_stmt->execute();
        $venue_id = $venue_stmt->insert_id; $venue_stmt->close();

        $user_stmt = $conn->prepare("SELECT first_name, middle_name, last_name, email, phone FROM user WHERE user_id = ?");
        $user_stmt->bind_param("i", $user_id); $user_stmt->execute();
        $user_stmt->bind_result($first_name, $middle_name, $last_name, $email, $phone); $user_stmt->fetch(); $user_stmt->close();
        $full_name = trim("$first_name $middle_name $last_name");

        $org_stmt = $conn->prepare("SELECT organizer_id FROM organizer WHERE contact_email = ?");
        $org_stmt->bind_param("s", $email); $org_stmt->execute();
        $org_stmt->bind_result($organizer_id); $org_stmt->fetch(); $org_stmt->close();

        if (!$organizer_id) {
            $insert_org = $conn->prepare("INSERT INTO organizer (name, contact_email, phone) VALUES (?, ?, ?)");
            $insert_org->bind_param("sss", $full_name, $email, $phone); $insert_org->execute();
            $organizer_id = $insert_org->insert_id; $insert_org->close();
        }

        $stmt = $conn->prepare("INSERT INTO event (title, description, start_time, end_time, venue_id, organizer_id, capacity, category_id, has_tables, gender_separated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiiiiii", $title, $description, $start_time, $end_time, $venue_id, $organizer_id, $capacity, $category_id, $has_tables, $gender_separated);
        $message = $stmt->execute() ? "Event created successfully!" : "Failed to create event.";
        if (!$stmt->execute()) $error = "Failed to create event.";
        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_event'])) {
    $event_id = $_POST['event_id'];
    if (!canAccessEvent($conn, $user_id, $event_id, 'edit')) { $error = "You don't have permission to edit this event."; }
    else {
        $title = $_POST['title']; $description = $_POST['description'];
        $start_time = $_POST['start_time']; $end_time = $_POST['end_time'];
        $capacity = $_POST['capacity']; $category_id = $_POST['category_id'];
        $has_tables = isset($_POST['has_tables']) ? 1 : 0;
        $gender_separated = isset($_POST['gender_separated']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE event SET title=?, description=?, start_time=?, end_time=?, capacity=?, category_id=?, has_tables=?, gender_separated=? WHERE event_id=?");
        $stmt->bind_param("ssssiiiii", $title, $description, $start_time, $end_time, $capacity, $category_id, $has_tables, $gender_separated, $event_id);
        $message = $stmt->execute() ? "Event updated successfully!" : "Failed to update event.";
        if (!$stmt->execute()) $error = "Failed to update event.";
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    if (!canAccessEvent($conn, $user_id, $delete_id, 'delete')) { $error = "You don't have permission to delete this event."; }
    else {
        $stmt = $conn->prepare("DELETE FROM event WHERE event_id = ?");
        $stmt->bind_param("i", $delete_id);
        $message = $stmt->execute() ? "Event deleted successfully!" : "Failed to delete event.";
        if (!$stmt->execute()) $error = "Failed to delete event.";
        $stmt->close();
    }
}

$edit_event = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    if (!canAccessEvent($conn, $user_id, $edit_id, 'edit')) { $error = "You don't have permission to edit this event."; }
    else {
        $stmt = $conn->prepare("SELECT * FROM event WHERE event_id = ?");
        $stmt->bind_param("i", $edit_id); $stmt->execute();
        $edit_event = $stmt->get_result()->fetch_assoc(); $stmt->close();
    }
}

$category_result = $conn->query("SELECT category_id, category_name FROM event_category");

$stmt = $conn->prepare("SELECT e.event_id, e.title, e.start_time, e.end_time, v.name AS venue FROM event e JOIN venue v ON e.venue_id = v.venue_id JOIN organizer o ON e.organizer_id = o.organizer_id JOIN user u ON o.contact_email = u.email WHERE u.user_id = ?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$events = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Event Management Hub</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/event_head.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-layout event-head-page">
<?php include('../components/sidebar.php'); ?>
<main class="main-content">
    <header class="banner event-head-banner">
        <div>
            <div class="event-head-badge"><i data-lucide="briefcase" style="width:14px;height:14px;"></i> Event Organizer</div>
            <h1>Event Management Hub</h1>
            <p>Your central dashboard for managing events and analytics</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo">
    </header>

    <div class="eh-page">
        <div class="event-management-hub-container">

            <?php if (!empty($message)): ?>
                <div class="eh-alert-msg success"><i data-lucide="check-circle"></i><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="eh-alert-msg error"><i data-lucide="alert-circle"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="hub-section">
                <h2 class="section-title">Quick Actions</h2>
                <div class="quick-actions-grid">
                    <?php if (hasPermission($conn, $user_id, 'attendance.qr.scan')): ?>
                    <a href="../qr/scan_qr.php" class="quick-action-card">
                        <div class="action-icon-simple primary"><i data-lucide="scan"></i></div>
                        <h3>QR Scanner</h3>
                        <p>Scan participant QR codes for attendance tracking</p>
                    </a>
                    <?php endif; ?>

                    <?php if (hasPermission($conn, $user_id, 'attendance.view.own') || hasPermission($conn, $user_id, 'attendance.view.all')): ?>
                    <a href="view_attendance.php" class="quick-action-card">
                        <div class="action-icon-simple secondary"><i data-lucide="eye"></i></div>
                        <h3>View Attendance</h3>
                        <p>Check attendance records and participant lists</p>
                    </a>
                    <?php endif; ?>

                    <a href="announcement.php" class="quick-action-card">
                        <div class="action-icon-simple" style="background:linear-gradient(135deg,#ea580c,#f97316);color:white;">
                            <i data-lucide="megaphone"></i>
                        </div>
                        <h3>Announcements</h3>
                        <p>Send reminders and announcements to registered participants</p>
                    </a>

                    <?php if (hasPermission($conn, $user_id, 'system.reports')): ?>
                    <a href="reports.php" class="quick-action-card">
                        <div class="action-icon-simple success"><i data-lucide="file-text"></i></div>
                        <h3>Reports</h3>
                        <p>Generate and download event reports</p>
                    </a>
                    <?php endif; ?>

                    <a href="participant_engagement.php" class="quick-action-card">
                        <div class="action-icon-simple warning"><i data-lucide="activity"></i></div>
                        <h3>Engagement Analytics</h3>
                        <p>Track participant behavior and engagement metrics</p>
                    </a>

                    <a href="inactive_tracking.php" class="quick-action-card">
                        <div class="action-icon-simple info"><i data-lucide="user-x"></i></div>
                        <h3>Inactive Members</h3>
                        <p>Monitor and identify inactive participants</p>
                    </a>
                </div>
            </div>

            <hr class="section-divider">

            <!-- Create / Edit Event -->
            <?php if (hasPermission($conn, $user_id, 'event.create') || $edit_event): ?>
            <div class="hub-section">
                <h2 class="section-title-with-icon">
                    <i data-lucide="settings"></i>
                    <?= $edit_event ? "Edit Event" : "Create New Event" ?>
                </h2>
                <form method="POST" class="event-form">
                    <input type="hidden" name="event_id" value="<?= $edit_event['event_id'] ?? '' ?>">

                    <div class="form-group">
                        <input type="text" name="title" placeholder="Event Title" value="<?= htmlspecialchars($edit_event['title'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <textarea name="description" placeholder="Event Description" required><?= htmlspecialchars($edit_event['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <input type="datetime-local" name="start_time" value="<?= $edit_event['start_time'] ?? '' ?>" required>
                        </div>
                        <div class="form-group">
                            <input type="datetime-local" name="end_time" value="<?= $edit_event['end_time'] ?? '' ?>" required>
                        </div>
                    </div>

                    <?php if (!$edit_event): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="venue_name" placeholder="Venue Name" required>
                        </div>
                        <div class="form-group">
                            <input type="text" name="venue_address" placeholder="Venue Address">
                        </div>
                    </div>
                    <div class="form-group">
                        <input type="text" name="venue_city" placeholder="Venue City">
                    </div>
                    <?php else: ?>
                    <div class="venue-locked-notice">
                        <div class="notice-header">
                            <i data-lucide="map-pin-off"></i>
                            <span>Venue cannot be edited for existing events</span>
                            <button type="button" class="info-tooltip-trigger" onclick="toggleVenueInfo(event)">
                                <i data-lucide="help-circle"></i>
                            </button>
                        </div>
                        <div id="venueInfoPopup" class="info-popup" style="display:none;">
                            <div class="popup-content">
                                <h4>Why can't I edit the venue?</h4>
                                <ul>
                                    <li><strong>Registered participants</strong> have already received the venue location</li>
                                    <li><strong>QR codes and tickets</strong> may reference this venue</li>
                                    <li><strong>Attendance records</strong> are tied to the original location</li>
                                </ul>
                                <p class="popup-solution"><strong>Need to change location?</strong> We recommend:</p>
                                <ol>
                                    <li>Notify all registered participants about the venue change</li>
                                    <li>Create a new event with the correct venue</li>
                                    <li>Cancel or delete this event if needed</li>
                                </ol>
                            </div>
                        </div>
                        <div class="current-venue-display">
                            <strong>Current Venue:</strong>
                            <?php
                            $venue_stmt = $conn->prepare("SELECT v.name, v.address, v.city FROM venue v WHERE v.venue_id = ?");
                            $venue_stmt->bind_param("i", $edit_event['venue_id']); $venue_stmt->execute();
                            $venue = $venue_stmt->get_result()->fetch_assoc(); $venue_stmt->close();
                            ?>
                            <span class="venue-details">
                                <?= htmlspecialchars($venue['name']) ?>
                                <?php if ($venue['address']): ?> - <?= htmlspecialchars($venue['address']) ?><?php endif; ?>
                                <?php if ($venue['city']): ?>, <?= htmlspecialchars($venue['city']) ?><?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Available Seats</label>
                        <input type="number" name="capacity" placeholder="e.g. 100" value="<?= htmlspecialchars($edit_event['capacity'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <select name="category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php $category_result->data_seek(0); while ($cat = $category_result->fetch_assoc()): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= (isset($edit_event['category_id']) && $edit_event['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Table Management Options -->
                    <div class="form-group table-mgmt-box">
                        <div class="table-mgmt-header">
                            <i data-lucide="layout-grid" style="width:15px;height:15px;"></i>
                            Table Management
                        </div>
                        <label class="table-mgmt-option">
                            <input type="checkbox" name="has_tables" value="1"
                                   <?= ($edit_event && $edit_event['has_tables']) ? 'checked' : '' ?>
                                   onchange="document.getElementById('gender-sep-row').style.display = this.checked ? 'flex' : 'none'">
                            <div class="table-mgmt-option-text">
                                <span class="table-mgmt-option-label">Enable table assignment</span>
                                <span class="table-mgmt-option-desc">Participants will be auto-assigned to tables when they register</span>
                            </div>
                        </label>
                        <label class="table-mgmt-option table-mgmt-sub" id="gender-sep-row"
                               style="display:<?= ($edit_event && $edit_event['has_tables']) ? 'flex' : 'none' ?>">
                            <input type="checkbox" name="gender_separated" value="1"
                                   <?= ($edit_event && $edit_event['gender_separated']) ? 'checked' : '' ?>>
                            <div class="table-mgmt-option-text">
                                <span class="table-mgmt-option-label">Separate by gender</span>
                                <span class="table-mgmt-option-desc">Males and females assigned to separate tables</span>
                            </div>
                        </label>
                    </div>

                    <div class="form-actions">
                        <?php if ($edit_event): ?>
                            <button type="submit" name="update_event" class="btn-primary"><i data-lucide="save"></i> Update Event</button>
                            <a href="manage_events.php" class="btn-secondary"><i data-lucide="x"></i> Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_event" class="btn-primary"><i data-lucide="plus"></i> Add Event</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <hr class="section-divider">
            <?php endif; ?>

            <!-- My Events -->
            <div class="hub-section">
                <h2 class="section-title-with-icon"><i data-lucide="calendar"></i> My Events</h2>
                <div class="event-list">
                    <?php while ($row = $events->fetch_assoc()):
                        $can_edit = canAccessEvent($conn, $user_id, $row['event_id'], 'edit');
                        $can_delete = canAccessEvent($conn, $user_id, $row['event_id'], 'delete');
                    ?>
                        <div class="event-card" style="display:flex;flex-direction:column;">
                            <h3><?= htmlspecialchars($row['title']) ?></h3>
                            <p><i data-lucide="map-pin"></i><strong>Venue:</strong> <?= htmlspecialchars($row['venue']) ?></p>
                            <?php
                            $start    = strtotime($row['start_time']);
                            $end      = strtotime($row['end_time']);
                            $same_day = date('Y-m-d', $start) === date('Y-m-d', $end);
                            $date_str = $same_day
                                ? date('F j, Y', $start) . ' · ' . date('g:i A', $start) . ' – ' . date('g:i A', $end)
                                : date('F j, Y', $start) . ' – ' . date('F j, Y', $end);
                            ?>
                            <p><i data-lucide="calendar"></i><strong>Date:</strong> <?= $date_str ?></p>
                            <div class="event-actions" style="margin-top:auto;padding-top:12px;">
                                <?php if ($can_edit): ?>
                                    <a href="manage_events.php?edit=<?= $row['event_id'] ?>" class="edit-link"><i data-lucide="edit"></i> Edit</a>
                                <?php else: ?>
                                    <span class="edit-link btn-disabled" title="No permission"><i data-lucide="edit"></i> Edit</span>
                                <?php endif; ?>
                                <?php if ($can_delete): ?>
                                    <a href="manage_events.php?delete=<?= $row['event_id'] ?>" class="delete-link" onclick="return confirm('Delete this event?')"><i data-lucide="trash-2"></i> Delete</a>
                                <?php else: ?>
                                    <span class="delete-link btn-disabled" title="No permission"><i data-lucide="trash-2"></i> Delete</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

        </div>
    </div>
</main>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
lucide.createIcons();
setTimeout(() => {
    document.querySelectorAll('.eh-alert-msg').forEach(el => {
        el.style.opacity = '0'; el.style.transition = 'opacity 0.5s';
        setTimeout(() => el.remove(), 500);
    });
}, 5000);
function toggleVenueInfo(event) {
    event.preventDefault();
    const popup = document.getElementById('venueInfoPopup');
    popup.style.display = popup.style.display === 'none' ? 'block' : 'none';
    setTimeout(() => lucide.createIcons(), 100);
}
document.addEventListener('click', function(e) {
    const popup = document.getElementById('venueInfoPopup');
    const trigger = document.querySelector('.info-tooltip-trigger');
    if (popup && trigger && !popup.contains(e.target) && !trigger.contains(e.target) && popup.style.display === 'block') {
        popup.style.display = 'none';
    }
});
</script>
</body>
</html>