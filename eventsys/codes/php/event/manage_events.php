<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole(['event_head', 'admin']);

include('../../includes/db.php');
require_once('../../includes/permission_functions.php');

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// Fetch user's role from database
$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

// Fallback if role not found
if (empty($role)) {
    $role = 'user'; // default role
}

// Handle add event
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_event'])) {
    // Check if user has permission to create events
    if (!hasPermission($conn, $user_id, 'event.create')) {
        $error = "You don't have permission to create events.";
    } else {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $venue_name = $_POST['venue_name'];
        $venue_address = $_POST['venue_address'];
        $venue_city = $_POST['venue_city'];
        $capacity = $_POST['capacity'];
        $price = $_POST['price'];
        $category_id = $_POST['category_id'];

        // Insert venue and get ID
        $venue_stmt = $conn->prepare("INSERT INTO venue (name, address, city) VALUES (?, ?, ?)");
        $venue_stmt->bind_param("sss", $venue_name, $venue_address, $venue_city);
        $venue_stmt->execute();
        $venue_id = $venue_stmt->insert_id;
        $venue_stmt->close();

        // Fetch user's info
        $user_stmt = $conn->prepare("SELECT first_name, middle_name, last_name, email, phone FROM user WHERE user_id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_stmt->bind_result($first_name, $middle_name, $last_name, $email, $phone);
        $user_stmt->fetch();
        $user_stmt->close();

        // Create full name from user info
        $full_name = trim("$first_name $middle_name $last_name");

        // Check or insert organizer
        $org_stmt = $conn->prepare("SELECT organizer_id FROM organizer WHERE contact_email = ?");
        $org_stmt->bind_param("s", $email);
        $org_stmt->execute();
        $org_stmt->bind_result($organizer_id);
        $org_stmt->fetch();
        $org_stmt->close();

        if (!$organizer_id) {
            $insert_org = $conn->prepare("INSERT INTO organizer (name, contact_email, phone) VALUES (?, ?, ?)");
            $insert_org->bind_param("sss", $full_name, $email, $phone);
            $insert_org->execute();
            $organizer_id = $insert_org->insert_id;
            $insert_org->close();
        }

        // Insert event
        $stmt = $conn->prepare("INSERT INTO event (title, description, start_time, end_time, venue_id, organizer_id, capacity, price, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiiidi", $title, $description, $start_time, $end_time, $venue_id, $organizer_id, $capacity, $price, $category_id);

        if ($stmt->execute()) {
            $message = "Event created successfully!";
        } else {
            $error = "Failed to create event.";
        }
        $stmt->close();
    }
}

// Handle update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_event'])) {
    $event_id = $_POST['event_id'];

    // Check permission to edit this event
    if (!canAccessEvent($conn, $user_id, $event_id, 'edit')) {
        $error = "You don't have permission to edit this event.";
    } else {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $capacity = $_POST['capacity'];
        $price = $_POST['price'];
        $category_id = $_POST['category_id'];

        $stmt = $conn->prepare("UPDATE event SET title=?, description=?, start_time=?, end_time=?, capacity=?, price=?, category_id=? WHERE event_id=?");
        $stmt->bind_param("ssssiddi", $title, $description, $start_time, $end_time, $capacity, $price, $category_id, $event_id);

        if ($stmt->execute()) {
            $message = "Event updated successfully!";
        } else {
            $error = "Failed to update event.";
        }
        $stmt->close();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];

    // ✅ CHECK PERMISSION BEFORE DELETE
    if (!canAccessEvent($conn, $user_id, $delete_id, 'delete')) {
        $error = "You don't have permission to delete this event.";
    } else {
        $stmt = $conn->prepare("DELETE FROM event WHERE event_id = ?");
        $stmt->bind_param("i", $delete_id);

        if ($stmt->execute()) {
            $message = "Event deleted successfully!";
        } else {
            $error = "Failed to delete event.";
        }
        $stmt->close();
    }
}

// Fetch event to edit
$edit_event = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];

    // Check permission to edit
    if (!canAccessEvent($conn, $user_id, $edit_id, 'edit')) {
        $error = "You don't have permission to edit this event.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM event WHERE event_id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $edit_event = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Fetch categories
$category_result = $conn->query("SELECT category_id, category_name FROM event_category");

// Fetch events user can view
$stmt = $conn->prepare("
    SELECT e.event_id, e.title, e.start_time, e.end_time, v.name AS venue
    FROM event e
    JOIN venue v ON e.venue_id = v.venue_id
    JOIN organizer o ON e.organizer_id = o.organizer_id
    JOIN user u ON o.contact_email = u.email
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
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
    <style>
        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        /* Disabled Button Styles */
        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
            pointer-events: auto !important;
            position: relative;
        }

        .btn-disabled:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 5px;
        }

        .btn-disabled:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #1f2937;
        }

        /* Venue Locked Notice */
        .venue-locked-notice {
            background: #fff9e6;
            border: 2px solid #fbbf24;
            border-radius: 12px;
            padding: 16px;
            margin: 20px 0;
        }

        .notice-header {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #92400e;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .notice-header i[data-lucide="map-pin-off"] {
            width: 20px;
            height: 20px;
            color: #f59e0b;
        }

        .info-tooltip-trigger {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            margin-left: auto;
            flex-shrink: 0;
            width: 28px;
            height: 28px;
        }

        .info-tooltip-trigger:hover {
            background: #fef3c7;
        }

        .info-tooltip-trigger i {
            width: 18px;
            height: 18px;
            color: #f59e0b;
        }

        .info-popup {
            background: white;
            border: 2px solid #fbbf24;
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .popup-content h4 {
            color: #92400e;
            margin: 0 0 12px 0;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .popup-content ul,
        .popup-content ol {
            margin: 8px 0;
            padding-left: 24px;
            color: #78350f;
        }

        .popup-content li {
            margin: 6px 0;
            line-height: 1.5;
        }

        .popup-solution {
            margin: 12px 0 8px 0;
            color: #92400e;
            font-size: 0.95rem;
        }

        .current-venue-display {
            margin-top: 12px;
            padding: 12px;
            background: white;
            border-radius: 6px;
            border-left: 4px solid #f59e0b;
        }

        .current-venue-display strong {
            color: #92400e;
            margin-right: 8px;
        }

        .venue-details {
            color: #78350f;
            font-weight: 500;
        }
    </style>
</head>
<body class="dashboard-layout event-head-page">

<!-- Sidebar -->
<?php include('../components/sidebar.php'); ?>

<main class="main-content">
    <!-- Event Head Banner -->
    <header class="banner event-head-banner">
        <div>
            <div class="event-head-badge">
                <i data-lucide="briefcase" style="width: 14px; height: 14px;"></i>
                Event Organizer
            </div>
            <h1>Event Management Hub</h1>
            <p>Your central dashboard for managing events and analytics</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
    </header>

    <div class="dashboard">
        <!-- Single Unified Container -->
        <div class="event-management-hub-container">

            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Quick Actions Section -->
            <div class="hub-section">
                <h2 class="section-title">Quick Actions</h2>

                <div class="quick-actions-grid">
                    <?php if (hasPermission($conn, $user_id, 'attendance.qr.scan')): ?>
                    <a href="../qr/scan_qr.php" class="quick-action-card">
                        <div class="action-icon-simple primary">
                            <i data-lucide="scan"></i>
                        </div>
                        <h3>QR Scanner</h3>
                        <p>Scan participant QR codes for attendance tracking</p>
                    </a>
                    <?php endif; ?>

                    <?php if (hasPermission($conn, $user_id, 'attendance.view.own') || hasPermission($conn, $user_id, 'attendance.view.all')): ?>
                    <a href="view_attendance.php" class="quick-action-card">
                        <div class="action-icon-simple secondary">
                            <i data-lucide="eye"></i>
                        </div>
                        <h3>View Attendance</h3>
                        <p>Check attendance records and participant lists</p>
                    </a>
                    <?php endif; ?>

                    <!-- Announcements & Reminders -->
                    <a href="announcement.php" class="quick-action-card">
                        <div class="action-icon-simple" style="background: linear-gradient(135deg, #ea580c, #f97316); color: white;">
                          <i data-lucide="megaphone"></i>
                        </div>
                        <h3>Announcements</h3>
                        <p>Send reminders and announcements to registered participants</p>
                    </a>

                    <?php if (hasPermission($conn, $user_id, 'system.reports')): ?>
                    <a href="reports.php" class="quick-action-card">
                        <div class="action-icon-simple success">
                            <i data-lucide="file-text"></i>
                        </div>
                        <h3>Reports</h3>
                        <p>Generate and download event reports</p>
                    </a>
                    <?php endif; ?>

                    <a href="participant_engagement.php" class="quick-action-card">
                        <div class="action-icon-simple warning">
                            <i data-lucide="activity"></i>
                        </div>
                        <h3>Engagement Analytics</h3>
                        <p>Track participant behavior and engagement metrics</p>
                    </a>

                    <a href="inactive_tracking.php" class="quick-action-card">
                        <div class="action-icon-simple info">
                            <i data-lucide="user-x"></i>
                        </div>
                        <h3>Inactive Members</h3>
                        <p>Monitor and identify inactive participants</p>
                    </a>
                </div>
            </div>

            <!-- Divider -->
            <hr class="section-divider">

            <!-- Event Management Section -->
            <?php if (hasPermission($conn, $user_id, 'event.create') || $edit_event): ?>
            <div class="hub-section">
                <h2 class="section-title-with-icon">
                    <i data-lucide="settings"></i>
                    <?= $edit_event ? "Edit Event" : "Create New Event" ?>
                </h2>

                <form method="POST" class="event-form">
                    <input type="hidden" name="event_id" value="<?= $edit_event['event_id'] ?? '' ?>">

                    <div class="form-group">
                        <input type="text" name="title" placeholder="Event Title" value="<?= $edit_event['title'] ?? '' ?>" required>
                    </div>

                    <div class="form-group">
                        <textarea name="description" placeholder="Event Description" required><?= $edit_event['description'] ?? '' ?></textarea>
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

                        <div id="venueInfoPopup" class="info-popup" style="display: none;">
                            <div class="popup-content">
                                <h4>Why can't I edit the venue?</h4>
                                <ul>
                                    <li><strong>Registered participants</strong> have already received the venue location</li>
                                    <li><strong>QR codes and tickets</strong> may reference this venue</li>
                                    <li><strong>Attendance records</strong> are tied to the original location</li>
                                </ul>
                                <p class="popup-solution">
                                    <strong>Need to change location?</strong> We recommend:
                                </p>
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
                            // Fetch current venue details
                            $venue_stmt = $conn->prepare("SELECT v.name, v.address, v.city FROM venue v WHERE v.venue_id = ?");
                            $venue_stmt->bind_param("i", $edit_event['venue_id']);
                            $venue_stmt->execute();
                            $venue_result = $venue_stmt->get_result();
                            $venue = $venue_result->fetch_assoc();
                            $venue_stmt->close();
                            ?>
                            <span class="venue-details">
                                <?= htmlspecialchars($venue['name']) ?>
                                <?php if ($venue['address']): ?>
                                    - <?= htmlspecialchars($venue['address']) ?>
                                <?php endif; ?>
                                <?php if ($venue['city']): ?>
                                    , <?= htmlspecialchars($venue['city']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <input type="number" name="capacity" placeholder="Capacity" value="<?= $edit_event['capacity'] ?? '' ?>" required>
                        </div>
                        <div class="form-group">
                            <input type="number" step="0.01" name="price" placeholder="Price" value="<?= $edit_event['price'] ?? '' ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <select name="category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php
                            $category_result->data_seek(0);
                            while ($cat = $category_result->fetch_assoc()):
                            ?>
                                <option value="<?= $cat['category_id'] ?>"
                                    <?= (isset($edit_event['category_id']) && $edit_event['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-actions">
                        <?php if ($edit_event): ?>
                            <button type="submit" name="update_event" class="btn-primary">
                                <i data-lucide="save"></i>
                                Update Event
                            </button>
                            <a href="manage_events.php" class="btn-secondary">
                                <i data-lucide="x"></i>
                                Cancel
                            </a>
                        <?php else: ?>
                            <button type="submit" name="add_event" class="btn-primary">
                                <i data-lucide="plus"></i>
                                Add Event
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Divider -->
            <hr class="section-divider">
            <?php endif; ?>

            <!-- My Events Section -->
            <div class="hub-section">
                <h2 class="section-title-with-icon">
                    <i data-lucide="calendar"></i>
                    My Events
                </h2>
                <div class="event-list">
                    <?php while ($row = $events->fetch_assoc()):
                        $can_edit = canAccessEvent($conn, $user_id, $row['event_id'], 'edit');
                        $can_delete = canAccessEvent($conn, $user_id, $row['event_id'], 'delete');
                    ?>
                        <div class="event-card">
                            <h3><?= htmlspecialchars($row['title']) ?></h3>
                            <p>
                                <i data-lucide="map-pin"></i>
                                <strong>Venue:</strong> <?= htmlspecialchars($row['venue']) ?>
                            </p>
                            <p>
                                <i data-lucide="calendar"></i>
                                <strong>From:</strong> <?= $row['start_time'] ?>
                            </p>
                            <p>
                                <i data-lucide="clock"></i>
                                <strong>To:</strong> <?= $row['end_time'] ?>
                            </p>
                            <div class="event-actions">
                                <?php if ($can_edit): ?>
                                <a href="manage_events.php?edit=<?= $row['event_id'] ?>" class="edit-link">
                                    <i data-lucide="edit"></i>
                                    Edit
                                </a>
                                <?php else: ?>
                                <span class="edit-link btn-disabled" title="You don't have permission to edit this event">
                                    <i data-lucide="edit"></i>
                                    Edit
                                </span>
                                <?php endif; ?>

                                <?php if ($can_delete): ?>
                                <a href="manage_events.php?delete=<?= $row['event_id'] ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this event?')">
                                    <i data-lucide="trash-2"></i>
                                    Delete
                                </a>
                                <?php else: ?>
                                <span class="delete-link btn-disabled" title="You don't have permission to delete this event">
                                    <i data-lucide="trash-2"></i>
                                    Delete
                                </span>
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

    // Auto-dismiss alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);

    // Toggle venue info popup
    function toggleVenueInfo(event) {
        event.preventDefault();
        const popup = document.getElementById('venueInfoPopup');
        if (popup.style.display === 'none') {
            popup.style.display = 'block';
        } else {
            popup.style.display = 'none';
        }

        // Re-initialize Lucide icons
        setTimeout(() => lucide.createIcons(), 100);
    }

    // Close popup when clicking outside
    document.addEventListener('click', function(event) {
        const popup = document.getElementById('venueInfoPopup');
        const trigger = document.querySelector('.info-tooltip-trigger');

        if (popup && trigger &&
            !popup.contains(event.target) &&
            !trigger.contains(event.target) &&
            popup.style.display === 'block') {
            popup.style.display = 'none';
        }
    });
</script>
</body>
</html>