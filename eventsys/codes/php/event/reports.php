<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole(['event_head', 'admin']);

include('../../includes/db.php');
require_once('../../includes/permission_functions.php');

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role_name'];

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

// CHECK PERMISSION - Must have reports permission
if (!hasPermission($conn, $user_id, 'system.reports') && !hasPermission($conn, $user_id, 'attendance.export')) {
    die('
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Access Denied</title>
            <link rel="stylesheet" href="../../css/style.css">
        </head>
        <body style="display: flex; align-items: center; justify-content: center; height: 100vh; background: #f3f4f6;">
            <div style="text-align: center; padding: 40px; background: white; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <h1 style="color: #ef4444; margin-bottom: 16px;">🚫 Access Denied</h1>
                <p style="color: #6b6b6b; margin-bottom: 24px;">You don\'t have permission to generate reports.</p>
                <a href="../event/manage_events.php" style="display: inline-block; padding: 12px 24px; background: #e63946; color: white; text-decoration: none; border-radius: 8px;">← Back to Dashboard</a>
            </div>
        </body>
        </html>
    ');
}

// Get user's email to find their events
$email_stmt = $conn->prepare("SELECT email FROM user WHERE user_id = ?");
$email_stmt->bind_param("i", $user_id);
$email_stmt->execute();
$email_stmt->bind_result($email);
$email_stmt->fetch();
$email_stmt->close();

// Get organizer ID
$org_stmt = $conn->prepare("SELECT organizer_id FROM organizer WHERE contact_email = ?");
$org_stmt->bind_param("s", $email);
$org_stmt->execute();
$org_stmt->bind_result($organizer_id);
$org_stmt->fetch();
$org_stmt->close();

// Fetch events based on permissions
if ($user_role === 'admin' || hasPermission($conn, $user_id, 'event.view.all')) {
    // Admin sees all events
    $events_query = $conn->prepare("
        SELECT e.event_id, e.title, e.start_time, e.end_time,
               e.capacity, v.name as venue_name
        FROM event e
        JOIN venue v ON e.venue_id = v.venue_id
        ORDER BY e.start_time DESC
    ");
    $events_query->execute();
} else {
    // Event heads see only their events or events with export permission
    $events_query = $conn->prepare("
        SELECT DISTINCT e.event_id, e.title, e.start_time, e.end_time,
               e.capacity, v.name as venue_name
        FROM event e
        JOIN venue v ON e.venue_id = v.venue_id
        LEFT JOIN event_access ea ON e.event_id = ea.event_id AND ea.user_id = ?
        WHERE e.organizer_id = ? OR ea.can_export_data = 1
        ORDER BY e.start_time DESC
    ");
    $events_query->bind_param("ii", $user_id, $organizer_id);
    $events_query->execute();
}

$events = $events_query->get_result();

// Handle report generation
$selected_event = $_GET['event_id'] ?? null;
$report_data = null;

if ($selected_event) {
    // ✅ VERIFY USER CAN ACCESS THIS EVENT'S REPORTS
    if (!canAccessEvent($conn, $user_id, $selected_event, 'export_data')) {
        die('
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Access Denied</title>
                <link rel="stylesheet" href="../../css/style.css">
            </head>
            <body style="display: flex; align-items: center; justify-content: center; height: 100vh; background: #f3f4f6;">
                <div style="text-align: center; padding: 40px; background: white; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <h1 style="color: #ef4444; margin-bottom: 16px;">🚫 Access Denied</h1>
                    <p style="color: #6b6b6b; margin-bottom: 24px;">You don\'t have permission to view reports for this event.</p>
                    <a href="reports.php" style="display: inline-block; padding: 12px 24px; background: #e63946; color: white; text-decoration: none; border-radius: 8px;">← Back to Reports</a>
                </div>
            </body>
            </html>
        ');
    }
    
    // Gather comprehensive report data
    $report_data = generateEventReport($conn, $selected_event);
}

function generateEventReport($conn, $event_id) {
    $data = [];
    
    // Basic event info
    $event_query = $conn->prepare("
        SELECT e.title, e.description, e.start_time, e.end_time, 
               e.capacity, e.price, v.name as venue, v.address, v.city,
               c.category_name
        FROM event e
        JOIN venue v ON e.venue_id = v.venue_id
        LEFT JOIN event_category c ON e.category_id = c.category_id
        WHERE e.event_id = ?
    ");
    $event_query->bind_param("i", $event_id);
    $event_query->execute();
    $data['event'] = $event_query->get_result()->fetch_assoc();
    $event_query->close();
    
    // Registration statistics
    $reg_stats = $conn->prepare("
        SELECT 
            COUNT(*) as total_registrations,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM registration
        WHERE event_id = ?
    ");
    $reg_stats->bind_param("i", $event_id);
    $reg_stats->execute();
    $data['registration_stats'] = $reg_stats->get_result()->fetch_assoc();
    $reg_stats->close();
    
    // Attendance statistics
    $att_stats = $conn->prepare("
        SELECT 
            COUNT(DISTINCT a.registration_id) as total_attended,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.check_in_time IS NOT NULL THEN 1 ELSE 0 END) as checked_in,
            SUM(CASE WHEN a.check_out_time IS NOT NULL THEN 1 ELSE 0 END) as checked_out
        FROM registration r
        LEFT JOIN attendance a ON r.registration_id = a.registration_id
        WHERE r.event_id = ?
    ");
    $att_stats->bind_param("i", $event_id);
    $att_stats->execute();
    $data['attendance_stats'] = $att_stats->get_result()->fetch_assoc();
    $att_stats->close();
    
    // Participant list with journey tracking
    $participants = $conn->prepare("
        SELECT 
            u.first_name, u.middle_name, u.last_name, u.email, u.phone,
            r.registration_id, r.registration_date, r.table_number, r.status as reg_status,
            a.check_in_time, a.check_out_time, a.status as attendance_status
        FROM registration r
        JOIN user u ON r.user_id = u.user_id
        LEFT JOIN attendance a ON r.registration_id = a.registration_id
        WHERE r.event_id = ?
        ORDER BY u.last_name, u.first_name
    ");
    $participants->bind_param("i", $event_id);
    $participants->execute();
    $data['participants'] = $participants->get_result();
    $participants->close();
    
    // Calculate attendance rate
    $total_reg = $data['registration_stats']['total_registrations'];
    $total_attended = $data['attendance_stats']['checked_in'];
    $data['attendance_rate'] = $total_reg > 0 ? round(($total_attended / $total_reg) * 100, 2) : 0;
    
    // Calculate revenue
    $data['revenue'] = $total_reg * $data['event']['price'];
    
    return $data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Reports - Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/event_head.css">
    <link rel="stylesheet" href="../../css/reports.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-layout event-head-page">
    <!-- Sidebar Component -->
    <?php include('../components/sidebar.php'); ?>

    <main class="main-content">
        <header class="banner event-head-banner">
            <div>
                <div class="event-head-badge">
                    <i data-lucide="briefcase"></i>
                    Event Organizer
                </div>
                <h1>Event Reports & Analytics</h1>
                <p>Comprehensive reporting system for events</p>
            </div>
            <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
        </header>

        <div class="report-container">
            <div class="report-header">
                <h2>
                    <i data-lucide="bar-chart-3"></i>
                    Generate Event Report
                </h2>

                <form method="GET">
                    <select name="event_id" required class="report-select">
                        <option value="">-- Select Event --</option>
                        <?php 
                        $events->data_seek(0);
                        while ($event = $events->fetch_assoc()): ?>
                            <option value="<?= $event['event_id'] ?>" <?= $selected_event == $event['event_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['title']) ?> -
                                <?= date('M j, Y', strtotime($event['start_time'])) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="report-submit-btn">
                        <i data-lucide="search"></i>
                        Generate Report
                    </button>
                </form>
            </div>

            <?php if ($report_data): ?>
                <!-- Event Summary -->
                <div class="report-section">
                    <h3>
                        <i data-lucide="info"></i>
                        Event Overview
                    </h3>
                    <div class="event-overview-grid">
                        <div>
                            <p><strong>Event Name:</strong> <?= htmlspecialchars($report_data['event']['title']) ?></p>
                            <p><strong>Date:</strong> <?= date('F j, Y', strtotime($report_data['event']['start_time'])) ?></p>
                            <p><strong>Time:</strong> <?= date('g:i A', strtotime($report_data['event']['start_time'])) ?> - <?= date('g:i A', strtotime($report_data['event']['end_time'])) ?></p>
                        </div>
                        <div>
                            <p><strong>Venue:</strong> <?= htmlspecialchars($report_data['event']['venue']) ?></p>
                            <p><strong>Capacity:</strong> <?= $report_data['event']['capacity'] ?> people</p>
                            <p><strong>Price:</strong> $<?= number_format($report_data['event']['price'], 2) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Registrations</div>
                        <div class="stat-value"><?= $report_data['registration_stats']['total_registrations'] ?></div>
                        <small class="stat-success"><?= $report_data['registration_stats']['confirmed'] ?> confirmed</small>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Total Attended</div>
                        <div class="stat-value"><?= $report_data['attendance_stats']['checked_in'] ?></div>
                        <small class="stat-info"><?= $report_data['attendance_stats']['checked_out'] ?> checked out</small>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Attendance Rate</div>
                        <div class="stat-value"><?= $report_data['attendance_rate'] ?>%</div>
                        <small class="stat-muted">Based on check-ins</small>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value">$<?= number_format($report_data['revenue'], 2) ?></div>
                        <small class="stat-muted">From registrations</small>
                    </div>
                </div>

                <!-- Participant Journey -->
                <div class="report-section">
                    <h3>
                        <i data-lucide="users"></i>
                        Participant Journey Report
                    </h3>

                    <div class="export-buttons">
                        <button onclick="exportToExcel()" class="export-btn export-btn-excel">
                            <i data-lucide="file-spreadsheet"></i>
                            Export to Excel
                        </button>
                        <button onclick="printReport()" class="export-btn export-btn-pdf">
                            <i data-lucide="file-text"></i>
                            Print/PDF
                        </button>
                    </div>

                    <table class="participant-table" id="participantTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Registration Date</th>
                                <th>Table</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($participant = $report_data['participants']->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars(trim($participant['first_name'] . ' ' . ($participant['middle_name'] ?: '') . ' ' . $participant['last_name'])) ?></td>
                                    <td><?= htmlspecialchars($participant['email']) ?></td>
                                    <td><?= date('M j, Y', strtotime($participant['registration_date'])) ?></td>
                                    <td><?= $participant['table_number'] ?></td>
                                    <td><?= $participant['check_in_time'] ? date('g:i A', strtotime($participant['check_in_time'])) : '—' ?></td>
                                    <td><?= $participant['check_out_time'] ? date('g:i A', strtotime($participant['check_out_time'])) : '—' ?></td>
                                    <td>
                                        <?php if ($participant['attendance_status'] === 'present' || $participant['check_in_time']): ?>
                                            <span class="status-badge status-present">
                                                <i data-lucide="check-circle"></i>
                                                Present
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-absent">
                                                <i data-lucide="x-circle"></i>
                                                Absent
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="../../js/reports.js"></script>
</body>
</html>