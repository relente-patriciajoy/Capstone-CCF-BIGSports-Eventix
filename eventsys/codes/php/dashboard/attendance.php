<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/index.php");
    exit();
}

include('../../includes/db.php');
require_once('../../includes/permission_functions.php');

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

$stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

// Get user email for organizer check
$email_stmt = $conn->prepare("SELECT email FROM user WHERE user_id = ?");
$email_stmt->bind_param("i", $user_id);
$email_stmt->execute();
$email_stmt->bind_result($email);
$email_stmt->fetch();
$email_stmt->close();

// Check which view to show
$is_organizer = ($role === 'event_head' || $role === 'admin');
$view = isset($_GET['view']) ? $_GET['view'] : ($is_organizer ? 'manage' : 'personal');

// ─── PERSONAL ATTENDANCE VIEW ───
// AUTO-CLOSE MISSED CHECKOUTS ON PAGE LOAD
$auto_close = $conn->prepare("
    UPDATE attendance a
    JOIN registration r ON a.registration_id = r.registration_id
    JOIN event e ON r.event_id = e.event_id
    SET a.check_out_time = e.end_time,
        a.notes = 'Left without checking out'
    WHERE r.user_id = ?
      AND a.check_in_time IS NOT NULL
      AND a.check_out_time IS NULL
      AND e.end_time < NOW()
      AND (a.notes IS NULL OR a.notes != 'Left without checking out')
");
$auto_close->bind_param("i", $user_id);
$auto_close->execute();
$auto_close->close();

// Handle check-in
if (isset($_POST['check_in']) && $role !== 'event_head' && $role !== 'admin') {
    $registration_id = $_POST['registration_id'];

    $guard = $conn->prepare("
        SELECT e.end_time, a.check_in_time
        FROM registration r
        JOIN event e ON r.event_id = e.event_id
        LEFT JOIN attendance a ON r.registration_id = a.registration_id
        WHERE r.registration_id = ?
    ");
    $guard->bind_param("i", $registration_id);
    $guard->execute();
    $guard->bind_result($end_time, $existing_check_in);
    $guard->fetch();
    $guard->close();

    if (strtotime($end_time) < time() && !$existing_check_in) {
        $_SESSION['attendance_error'] = "Check-in is no longer allowed. This event has already ended and you were marked absent.";
        header("Location: attendance.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO attendance (registration_id, check_in_time, status)
                            VALUES (?, NOW(), 'present')
                            ON DUPLICATE KEY UPDATE check_in_time = NOW(), status = 'present'");
    $stmt->bind_param("i", $registration_id);
    $stmt->execute();
    $stmt->close();
}

// Handle check-out
if (isset($_POST['check_out']) && $role !== 'event_head' && $role !== 'admin') {
    $registration_id = $_POST['check_out'];

    $guard = $conn->prepare("
        SELECT e.end_time
        FROM registration r
        JOIN event e ON r.event_id = e.event_id
        WHERE r.registration_id = ?
    ");
    $guard->bind_param("i", $registration_id);
    $guard->execute();
    $guard->bind_result($end_time);
    $guard->fetch();
    $guard->close();

    if (strtotime($end_time) < time()) {
        $_SESSION['attendance_error'] = "Check-out is no longer allowed. This event has already ended — your attendance has been automatically recorded as present.";
        header("Location: attendance.php");
        exit();
    }

    $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW() WHERE registration_id = ?");
    $stmt->bind_param("i", $registration_id);
    $stmt->execute();
    $stmt->close();
}

// Get personal registration data
$query = "
SELECT r.registration_id, e.title, e.start_time, e.end_time,
       a.check_in_time, a.check_out_time, a.status, a.notes
FROM registration r
JOIN event e ON r.event_id = e.event_id
LEFT JOIN attendance a ON r.registration_id = a.registration_id
WHERE r.user_id = ?
ORDER BY e.start_time DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$personal_result = $stmt->get_result();

// ─── ORGANIZER MANAGEMENT VIEW ───
$organizer_events = null;
$selected_event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : null;
$attendee_details = null;

if ($is_organizer) {
    // Build query to get organizer's events with attendee data
    if ($role === 'admin') {
        // Admins see all events
        $query = "
        SELECT e.event_id, e.title, e.start_time, e.end_time,
               COUNT(r.registration_id) as total_registrations,
               SUM(CASE WHEN a.check_in_time IS NOT NULL THEN 1 ELSE 0 END) as checked_in,
               SUM(CASE WHEN a.check_out_time IS NOT NULL THEN 1 ELSE 0 END) as checked_out
        FROM event e
        LEFT JOIN registration r ON e.event_id = r.event_id
        LEFT JOIN attendance a ON r.registration_id = a.registration_id
        GROUP BY e.event_id, e.title, e.start_time, e.end_time
        ORDER BY e.start_time DESC
        ";
        $organizer_events = $conn->query($query);
    } else {
        // Event heads see only their events
        $query = "
        SELECT e.event_id, e.title, e.start_time, e.end_time,
               COUNT(r.registration_id) as total_registrations,
               SUM(CASE WHEN a.check_in_time IS NOT NULL THEN 1 ELSE 0 END) as checked_in,
               SUM(CASE WHEN a.check_out_time IS NOT NULL THEN 1 ELSE 0 END) as checked_out
        FROM event e
        LEFT JOIN organizer o ON e.organizer_id = o.organizer_id
        LEFT JOIN event_access ea ON e.event_id = ea.event_id AND ea.user_id = ?
        LEFT JOIN registration r ON e.event_id = r.event_id
        LEFT JOIN attendance a ON r.registration_id = a.registration_id
        WHERE o.contact_email = ? OR ea.can_manage_attendance = 1
        GROUP BY e.event_id, e.title, e.start_time, e.end_time
        ORDER BY e.start_time DESC
        ";
        $org_stmt = $conn->prepare($query);
        $org_stmt->bind_param("is", $user_id, $email);
        $org_stmt->execute();
        $organizer_events = $org_stmt->get_result();
    }

    // Handle event selection for detailed view
    if ($selected_event_id) {
        // Verify user has access to this event
        if ($role === 'admin') {
            $access_check = $conn->prepare("SELECT event_id FROM event WHERE event_id = ?");
        } else {
            $access_check = $conn->prepare("
                SELECT e.event_id FROM event e
                LEFT JOIN organizer o ON e.organizer_id = o.organizer_id
                LEFT JOIN event_access ea ON e.event_id = ea.event_id AND ea.user_id = ?
                WHERE (e.event_id = ? AND (o.contact_email = ? OR ea.can_manage_attendance = 1))
            ");
            $access_check->bind_param("iis", $user_id, $selected_event_id, $email);
        }
        
        if ($role === 'admin') {
            $access_check->bind_param("i", $selected_event_id);
        }
        
        $access_check->execute();
        $access_result = $access_check->get_result();
        
        if ($access_result->num_rows > 0) {
            // Get detailed attendee list
            $detail_query = "
            SELECT r.registration_id, u.first_name, u.middle_name, u.last_name, u.email,
                   a.check_in_time, a.check_out_time, a.status, a.notes
            FROM registration r
            JOIN user u ON r.user_id = u.user_id
            LEFT JOIN attendance a ON r.registration_id = a.registration_id
            WHERE r.event_id = ?
            ORDER BY u.last_name, u.first_name
            ";
            $detail_stmt = $conn->prepare($detail_query);
            $detail_stmt->bind_param("i", $selected_event_id);
            $detail_stmt->execute();
            $attendee_details = $detail_stmt->get_result();
            $detail_stmt->close();
        }
        $access_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Tracker - Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <?php if ($role === 'event_head'): ?>
    <link rel="stylesheet" href="../../css/event_head.css">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .attendance-card {
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }
        .attendance-card-footer {
            margin-top: auto;
        }
        .attendance-card-footer form {
            margin: 0;
        }
        .attendance-card-footer button {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 12px 16px;
            border-radius: 12px;
        }
        .view-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 2px solid #e5e7eb;
        }
        .view-tab {
            padding: 12px 16px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 500;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .view-tab.active {
            color: #e63946;
            border-bottom-color: #e63946;
        }
        .view-tab:hover {
            color: #374151;
        }
        .attendee-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #e63946;
            margin-bottom: 4px;
        }
        .stat-card .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        .attendee-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .attendee-table thead {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }
        .attendee-table th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }
        .attendee-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
        }
        .attendee-table tr:hover {
            background: #f9fafb;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        .status-checked-in {
            background: #d1fae5;
            color: #065f46;
        }
        .status-checked-out {
            background: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background: #fef9c3;
            color: #854d0e;
        }
        .status-absent {
            background: #fee2e2;
            color: #991b1b;
        }
        .event-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .event-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .event-card:hover {
            border-color: #e63946;
            box-shadow: 0 4px 12px rgba(230, 57, 70, 0.1);
        }
        .event-card h3 {
            margin: 0 0 8px;
            color: #111;
            font-size: 1rem;
        }
        .event-card-meta {
            font-size: 0.875rem;
            color: #6b7280;
            line-height: 1.5;
        }
        .event-card-stats {
            display: flex;
            gap: 12px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
        }
        .event-card-stat {
            flex: 1;
            text-align: center;
        }
        .event-card-stat-num {
            display: block;
            font-weight: 700;
            color: #e63946;
            font-size: 1.1rem;
        }
        .event-card-stat-label {
            display: block;
            font-size: 0.75rem;
            color: #9ca3af;
        }
    </style>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="dashboard-layout <?= $role === 'event_head' ? 'event-head-page' : '' ?>">
<?php include('../components/sidebar.php'); ?>

<main class="main-content">
    <header class="banner <?= $role === 'event_head' ? 'event-head-banner' : '' ?>">
        <div>
            <?php if ($role === 'event_head'): ?>
            <div class="event-head-badge">
                <i data-lucide="briefcase" style="width:14px;height:14px;"></i>
                Event Organizer
            </div>
            <?php elseif ($role === 'admin'): ?>
            <div class="event-head-badge" style="background:linear-gradient(135deg,#1a1a1a,#2d2d2d);color:#e63946;">
                <i data-lucide="shield" style="width:14px;height:14px;"></i>
                Administrator
            </div>
            <?php endif; ?>
            <h1>Attendance Tracker</h1>
            <p><?= $is_organizer ? 'Manage attendee check-ins and view registration status' : 'Check in and out of your events' ?></p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
    </header>

    <?php if (isset($_SESSION['attendance_error'])): ?>
        <div id="attendance-alert" class="att-alert">
            <i data-lucide="alert-circle"></i>
            <?= htmlspecialchars($_SESSION['attendance_error']) ?>
        </div>
        <?php unset($_SESSION['attendance_error']); ?>
    <?php endif; ?>

    <!-- View Tabs for Organizers -->
    <?php if ($is_organizer): ?>
        <div class="view-tabs">
            <button class="view-tab <?= ($view === 'manage' ? 'active' : '') ?>" onclick="location.href='?view=manage'">
                <i data-lucide="users" style="width:16px;height:16px;display:inline;margin-right:6px;vertical-align:middle;"></i>
                Manage Attendees
            </button>
            <?php if ($personal_result->num_rows > 0): ?>
            <button class="view-tab <?= ($view === 'personal' ? 'active' : '') ?>" onclick="location.href='?view=personal'">
                <i data-lucide="user" style="width:16px;height:16px;display:inline;margin-right:6px;vertical-align:middle;"></i>
                My Attendance
            </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ══════════════════ MANAGE ATTENDEES VIEW ══════════════════ -->
    <?php if ($is_organizer && $view === 'manage'): ?>

        <?php if (!$selected_event_id): ?>
            <!-- Events Overview -->
            <h2 style="margin: 24px 0 16px; color: #111; font-weight: 600;">Your Events</h2>
            
            <?php if ($organizer_events->num_rows > 0): ?>
                <div class="event-list">
                    <?php while ($event = $organizer_events->fetch_assoc()): 
                        $now = time();
                        $start = strtotime($event['start_time']);
                        $end = strtotime($event['end_time']);
                        $is_live = ($now >= $start && $now <= $end);
                        $event_ended = $end < $now;
                        $total = intval($event['total_registrations'] ?? 0);
                        $checked_in = intval($event['checked_in'] ?? 0);
                        $checked_out = intval($event['checked_out'] ?? 0);
                        $pending = $total - $checked_in;
                    ?>
                    <a href="?view=manage&event_id=<?= $event['event_id'] ?>" class="event-card">
                        <h3><?= htmlspecialchars($event['title']) ?></h3>
                        <div class="event-card-meta">
                            <div style="margin-bottom: 8px;">
                                <?php if ($is_live): ?>
                                    <span style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.75rem;">
                                        🟢 LIVE
                                    </span>
                                <?php elseif ($event_ended): ?>
                                    <span style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.75rem;">
                                        📋 ENDED
                                    </span>
                                <?php else: ?>
                                    <span style="background: #fef9c3; color: #854d0e; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.75rem;">
                                        🕐 UPCOMING
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?= date('M j, Y · g:i A', $start) ?>
                        </div>
                        <div class="event-card-stats">
                            <div class="event-card-stat">
                                <span class="event-card-stat-num"><?= $total ?></span>
                                <span class="event-card-stat-label">Total</span>
                            </div>
                            <div class="event-card-stat">
                                <span class="event-card-stat-num" style="color: #10b981;"><?= $checked_in ?></span>
                                <span class="event-card-stat-label">Checked In</span>
                            </div>
                            <div class="event-card-stat">
                                <span class="event-card-stat-num" style="color: #f59e0b;"><?= $pending ?></span>
                                <span class="event-card-stat-label">Pending</span>
                            </div>
                        </div>
                    </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="card" style="text-align:center;padding:48px 24px;">
                    <i data-lucide="calendar-off" style="width:48px;height:48px;opacity:0.4;display:block;margin:0 auto 16px;"></i>
                    <p style="color:#6b7280;margin-bottom:20px;">You haven't created any events yet.</p>
                    <a href="../event/create_event.php" class="qr-btn" style="display:inline-flex;">
                        <i data-lucide="plus" style="width:16px;height:16px;"></i>
                        Create Event
                    </a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Event Attendee Details -->
            <div style="margin-bottom: 16px;">
                <a href="?view=manage" style="color: #e63946; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 0.875rem;">
                    <i data-lucide="chevron-left" style="width:16px;height:16px;"></i>
                    Back to Events
                </a>
            </div>

            <?php if ($attendee_details): 
                $event_query = "SELECT title, start_time, end_time FROM event WHERE event_id = ?";
                $event_stmt = $conn->prepare($event_query);
                $event_stmt->bind_param("i", $selected_event_id);
                $event_stmt->execute();
                $event_info = $event_stmt->get_result()->fetch_assoc();
                $event_stmt->close();

                $now = time();
                $event_end = strtotime($event_info['end_time']);
                $total_attendees = $attendee_details->num_rows;
                $attendee_details->data_seek(0);
                
                $stats = ['checked_in' => 0, 'checked_out' => 0, 'pending' => 0];
                $temp_data = [];
                
                while ($row = $attendee_details->fetch_assoc()) {
                    $temp_data[] = $row;
                    if ($row['check_in_time']) $stats['checked_in']++;
                    if ($row['check_out_time']) $stats['checked_out']++;
                    if (!$row['check_in_time']) $stats['pending']++;
                }
            ?>
                <h2 style="margin: 24px 0 16px; color: #111; font-weight: 600;">
                    <?= htmlspecialchars($event_info['title']) ?>
                </h2>
                
                <div class="attendee-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_attendees ?></div>
                        <div class="stat-label">Total Registrations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #10b981;"><?= $stats['checked_in'] ?></div>
                        <div class="stat-label">Checked In</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #f59e0b;"><?= $stats['pending'] ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #10b981;"><?= $stats['checked_out'] ?></div>
                        <div class="stat-label">Checked Out</div>
                    </div>
                </div>

                <table class="attendee-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Checked In</th>
                            <th>Checked Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($temp_data as $attendee): 
                            $full_name = trim($attendee['first_name'] . ' ' . $attendee['middle_name'] . ' ' . $attendee['last_name']);
                            
                            if ($attendee['check_in_time'] && $attendee['check_out_time']) {
                                $status_class = 'status-checked-out';
                                $status_text = 'Complete';
                            } elseif ($attendee['check_in_time']) {
                                $status_class = 'status-checked-in';
                                $status_text = 'Checked In';
                            } elseif ($event_end < time()) {
                                $status_class = 'status-absent';
                                $status_text = 'Absent';
                            } else {
                                $status_class = 'status-pending';
                                $status_text = 'Pending';
                            }
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($full_name) ?></strong></td>
                            <td><?= htmlspecialchars($attendee['email']) ?></td>
                            <td><?= $attendee['check_in_time'] ? date('M j, g:i A', strtotime($attendee['check_in_time'])) : '—' ?></td>
                            <td><?= $attendee['check_out_time'] ? date('M j, g:i A', strtotime($attendee['check_out_time'])) : '—' ?></td>
                            <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="card" style="text-align:center;padding:48px 24px;">
                    <i data-lucide="alert-circle" style="width:48px;height:48px;opacity:0.4;display:block;margin:0 auto 16px;"></i>
                    <p style="color:#6b7280;margin-bottom:20px;">Event not found or you don't have access to view it.</p>
                    <a href="?view=manage" class="qr-btn" style="display:inline-flex;">
                        <i data-lucide="arrow-left" style="width:16px;height:16px;"></i>
                        Back to Events
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <!-- ══════════════════ PERSONAL ATTENDANCE VIEW ══════════════════ -->
    <?php else: ?>

        <?php if ($personal_result->num_rows > 0): ?>

        <!-- Controls: filter dropdown + search -->
        <div class="events-controls">
            <div class="events-filter-wrap">
                <select id="events-filter" class="events-filter-select" aria-label="Filter attendance">
                    <option value="all">All Events</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="past">Past</option>
                </select>
            </div>

            <div class="events-search-wrap">
                <svg class="events-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="text"
                    id="events-search"
                    class="events-search"
                    placeholder="Search by event name…"
                    autocomplete="off"
                    aria-label="Search attendance"
                >
            </div>
        </div>

        <section class="grid-section" id="events-grid">
            <?php
            $now = time();
            $personal_result->data_seek(0);
            while ($row = $personal_result->fetch_assoc()):
                $event_ended     = strtotime($row['end_time']) < $now;
                $was_absent      = empty($row['check_in_time']);
                $missed_checkout = ($row['notes'] === 'Left without checking out');
                $locked          = $event_ended && $was_absent;
                $end_unix        = strtotime($row['end_time']);
            ?>
            <div class="card attendance-card <?= $event_ended ? 'event-past-card' : '' ?>"
                 data-end="<?= $end_unix ?>"
                 data-title="<?= strtolower(htmlspecialchars($row['title'])) ?>"
                 >

                <h3><?= htmlspecialchars($row['title']) ?></h3>
                <?php
                $start    = strtotime($row['start_time']);
                $end      = strtotime($row['end_time']);
                $same_day = date('Y-m-d', $start) === date('Y-m-d', $end);
                $date_str = $same_day
                    ? date('F j, Y', $start) . ' · ' . date('g:i A', $start) . ' – ' . date('g:i A', $end)
                    : date('F j', $start) . ' – ' . date('F j, Y', $end);
                ?>
                <p><strong>Event Time:</strong><br><?= $date_str ?></p>
                <p><strong>Checked In:</strong> <?= $row['check_in_time'] ? date('M j, Y · g:i A', strtotime($row['check_in_time'])) : 'Not yet' ?></p>
                <p><strong>Checked Out:</strong> <?= $row['check_out_time'] ? date('M j, Y · g:i A', strtotime($row['check_out_time'])) : 'Not yet' ?></p>
                <?php
                $status_display = ($row['status'] === 'present') ? 'present' : (!$event_ended ? 'pending' : 'absent');
                $status_color = match($status_display) {
                    'present' => 'background:#d1fae5;color:#065f46;',
                    'pending' => 'background:#fef9c3;color:#854d0e;',
                    'absent'  => 'background:#fee2e2;color:#991b1b;',
                    default   => ''
                };
                ?>
                <p><strong>Status:</strong>
                    <span style="<?= $status_color ?> padding:2px 10px; border-radius:999px; font-size:0.78rem; font-weight:600; display:inline-block; margin-left:4px;">
                        <?= $status_display ?>
                    </span>
                </p>

                    <?php if ($missed_checkout): ?>
                        <div class="att-notice att-notice-warning">
                            <i data-lucide="alert-triangle"></i>
                            <em>Left without checking out — marked <strong>present</strong></em>
                        </div>

                    <?php elseif ($locked): ?>
                        <div class="att-notice att-notice-danger">
                            <i data-lucide="lock"></i>
                            <em>Event ended — attendance locked (absent)</em>
                        </div>

                    <?php elseif (!$row['check_in_time'] && ($role !== 'event_head' && $role !== 'admin')): ?>
                        <!-- Only show for participants, not organizers -->
                        <div class="att-notice" style="background:#fef9c3;border:1px solid #fcd34d;color:#854d0e;padding:12px;border-radius:8px;">
                            <i data-lucide="info" style="display:inline;margin-right:8px;"></i>
                            <em>Scan your QR code to check in</em>
                        </div>

                    <?php elseif ($row['check_in_time'] && !$row['check_out_time'] && !$event_ended && ($role !== 'event_head' && $role !== 'admin')): ?>
                        <!-- Check-out notice for participants -->
                        <div class="att-notice" style="background:#d1fae5;border:1px solid #a7f3d0;color:#065f46;padding:12px;border-radius:8px;">
                            <i data-lucide="info" style="display:inline;margin-right:8px;"></i>
                            <em>Scan your QR code to check out</em>
                        </div>

                    <?php elseif ($row['check_in_time'] && ($role === 'event_head' || $role === 'admin')): ?>
                        <!-- Manual check-out button (organizers only if they're registered) -->
                        <div class="att-notice" style="background:#e0e7ff;border:1px solid #c7d2fe;color:#3730a3;padding:12px;border-radius:8px;">
                            <i data-lucide="info" style="display:inline;margin-right:8px;"></i>
                            <em>You checked in at <?= date('g:i A', strtotime($row['check_in_time'])) ?></em>
                        </div>

                    <?php else: ?>
                        <div class="att-notice att-notice-success">
                            <i data-lucide="check-circle"></i>
                            <em>Attendance tracked via QR code</em>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>

            <div class="events-no-results" id="no-results">
                <svg class="no-res-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/>
                </svg>
                <p id="no-results-msg">No events found.</p>
            </div>
        </section>

        <?php else: ?>
            <div class="card" style="text-align:center;padding:48px 24px;">
                <i data-lucide="calendar-off" style="width:48px;height:48px;opacity:0.4;display:block;margin:0 auto 16px;"></i>
                <p style="color:#6b7280;margin-bottom:20px;">You haven't registered for any events yet.</p>
                <a href="events.php" class="qr-btn" style="display:inline-flex;">
                    <i data-lucide="search" style="width:16px;height:16px;"></i>
                    Browse Events
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
lucide.createIcons();

const alertBox = document.getElementById('attendance-alert');
if (alertBox) {
    setTimeout(() => {
        alertBox.style.opacity = '0';
        setTimeout(() => alertBox.remove(), 500);
    }, 4000);
}

const cards        = Array.from(document.querySelectorAll('#events-grid .card'));
const noResults    = document.getElementById('no-results');
const noResultsMsg = document.getElementById('no-results-msg');
const searchInput  = document.getElementById('events-search');
const filterSelect = document.getElementById('events-filter');
const now          = Math.floor(Date.now() / 1000);

if (cards.length && filterSelect) {
    function applyFilters() {
        const filter = filterSelect.value;
        const query  = searchInput.value.trim().toLowerCase();
        let visible  = 0;

        cards.forEach(card => {
            const isPast     = parseInt(card.dataset.end, 10) < now;
            const passFilter = filter === 'all' ? true : filter === 'upcoming' ? !isPast : isPast;
            const passSearch = !query || card.dataset.title.includes(query);
            const show = passFilter && passSearch;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        noResults.style.display = visible === 0 ? 'block' : 'none';
        if (visible === 0) {
            noResultsMsg.textContent = query
                ? `No events found for "${query}".`
                : filter === 'past' ? 'No past events.' : filter === 'upcoming' ? 'No upcoming events.' : 'No registered events.';
        }
    }

    filterSelect.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);
    applyFilters();
}
</script>
</body>
</html>