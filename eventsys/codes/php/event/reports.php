<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole(['event_head', 'admin']);
include('../../includes/db.php');
require_once('../../includes/permission_functions.php');

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role_name'];

$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id); $role_stmt->execute();
$role_stmt->bind_result($role); $role_stmt->fetch(); $role_stmt->close();
if (empty($role)) $role = 'user';

if (!hasPermission($conn, $user_id, 'system.reports') && !hasPermission($conn, $user_id, 'attendance.export')) {
    die('<html><body style="display:flex;align-items:center;justify-content:center;height:100vh;background:#f3f4f6;font-family:Poppins,sans-serif;"><div style="text-align:center;padding:40px;background:white;border-radius:16px;"><h1 style="color:#ef4444;margin-bottom:16px;">Access Denied</h1><p style="color:#6b7280;margin-bottom:24px;">You don\'t have permission to generate reports.</p><a href="../event/manage_events.php" style="padding:12px 24px;background:#800020;color:white;text-decoration:none;border-radius:8px;font-weight:600;">Back to Dashboard</a></div></body></html>');
}

$email_stmt = $conn->prepare("SELECT email FROM user WHERE user_id = ?");
$email_stmt->bind_param("i", $user_id); $email_stmt->execute();
$email_stmt->bind_result($email); $email_stmt->fetch(); $email_stmt->close();

$org_stmt = $conn->prepare("SELECT organizer_id FROM organizer WHERE contact_email = ?");
$org_stmt->bind_param("s", $email); $org_stmt->execute();
$org_stmt->bind_result($organizer_id); $org_stmt->fetch(); $org_stmt->close();

if ($user_role === 'admin' || hasPermission($conn, $user_id, 'event.view.all')) {
    $eq = $conn->prepare("SELECT e.event_id, e.title, e.start_time FROM event e JOIN venue v ON e.venue_id = v.venue_id ORDER BY e.start_time DESC");
    $eq->execute();
} else {
    $eq = $conn->prepare("SELECT DISTINCT e.event_id, e.title, e.start_time FROM event e JOIN venue v ON e.venue_id = v.venue_id LEFT JOIN event_access ea ON e.event_id = ea.event_id AND ea.user_id = ? WHERE e.organizer_id = ? OR ea.can_export_data = 1 ORDER BY e.start_time DESC");
    $eq->bind_param("ii", $user_id, $organizer_id); $eq->execute();
}
$events = $eq->get_result();

$selected_event = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;
$report_data = null;

if ($selected_event) {
    if (!canAccessEvent($conn, $user_id, $selected_event, 'export_data')) {
        die('<html><body style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:Poppins,sans-serif;"><div style="text-align:center;padding:40px;background:white;border-radius:16px;"><h1 style="color:#ef4444;">Access Denied</h1><a href="reports.php" style="padding:12px 24px;background:#800020;color:white;text-decoration:none;border-radius:8px;">Back</a></div></body></html>');
    }
    $report_data = generateEventReport($conn, $selected_event);
}

function generateEventReport($conn, $event_id) {
    $data = [];
    // Removed price from query
    $q = $conn->prepare("
        SELECT e.title, e.start_time, e.end_time, e.capacity,
               v.name as venue, c.category_name
        FROM event e
        JOIN venue v ON e.venue_id = v.venue_id
        LEFT JOIN event_category c ON e.category_id = c.category_id
        WHERE e.event_id = ?
    ");
    $q->bind_param("i", $event_id); $q->execute();
    $data['event'] = $q->get_result()->fetch_assoc(); $q->close();

    $q = $conn->prepare("SELECT COUNT(*) as total_registrations, SUM(CASE WHEN status='confirmed' THEN 1 ELSE 0 END) as confirmed FROM registration WHERE event_id = ?");
    $q->bind_param("i", $event_id); $q->execute();
    $data['reg'] = $q->get_result()->fetch_assoc(); $q->close();

    $q = $conn->prepare("SELECT SUM(CASE WHEN a.check_in_time IS NOT NULL THEN 1 ELSE 0 END) as checked_in, SUM(CASE WHEN a.check_out_time IS NOT NULL THEN 1 ELSE 0 END) as checked_out FROM registration r LEFT JOIN attendance a ON r.registration_id = a.registration_id WHERE r.event_id = ?");
    $q->bind_param("i", $event_id); $q->execute();
    $data['att'] = $q->get_result()->fetch_assoc(); $q->close();

    $q = $conn->prepare("
        SELECT u.first_name, u.middle_name, u.last_name, u.email,
               r.registration_date, r.table_number, r.status as reg_status,
               a.check_in_time, a.check_out_time, a.status as att_status
        FROM registration r
        JOIN user u ON r.user_id = u.user_id
        LEFT JOIN attendance a ON r.registration_id = a.registration_id
        WHERE r.event_id = ? ORDER BY u.last_name, u.first_name
    ");
    $q->bind_param("i", $event_id); $q->execute();
    $data['participants'] = $q->get_result(); $q->close();

    $total = $data['reg']['total_registrations'];
    $data['rate'] = $total > 0 ? round(($data['att']['checked_in'] / $total) * 100, 2) : 0;
    return $data;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Event Reports - Eventix</title>
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
            <h1>Event Reports &amp; Analytics</h1>
            <p>Comprehensive reporting system for your events</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo">
    </header>

    <div class="eh-page">
        <div class="eh-card">
            <h2><i data-lucide="bar-chart-3" style="width:24px;height:24px;"></i> Generate Event Report</h2>

            <div class="eh-report-select-bar">
                <form method="GET" style="display:contents;">
                    <select name="event_id" required>
                        <option value="">-- Select Event --</option>
                        <?php $events->data_seek(0); while ($ev = $events->fetch_assoc()): ?>
                            <option value="<?= $ev['event_id'] ?>" <?= $selected_event == $ev['event_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev['title']) ?> — <?= date('M j, Y', strtotime($ev['start_time'])) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="eh-btn eh-btn-primary">
                        <i data-lucide="search" style="width:16px;height:16px;"></i> Generate Report
                    </button>
                </form>
            </div>

            <?php if ($report_data): ?>
                <h3><i data-lucide="info" style="width:18px;height:18px;"></i> Event Overview</h3>
                <div class="eh-overview-grid">
                    <div>
                        <p><strong>Event:</strong> <?= htmlspecialchars($report_data['event']['title']) ?></p>
                        <p><strong>Date:</strong> <?= date('F j, Y', strtotime($report_data['event']['start_time'])) ?></p>
                        <p><strong>Time:</strong> <?= date('g:i A', strtotime($report_data['event']['start_time'])) ?> – <?= date('g:i A', strtotime($report_data['event']['end_time'])) ?></p>
                    </div>
                    <div>
                        <p><strong>Venue:</strong> <?= htmlspecialchars($report_data['event']['venue']) ?></p>
                        <p><strong>Capacity:</strong> <?= $report_data['event']['capacity'] ?> people</p>
                    </div>
                </div>

                <div class="eh-stats-grid">
                    <div class="eh-stat-card"><div class="eh-stat-number"><?= $report_data['reg']['total_registrations'] ?></div><div class="eh-stat-label">Total Registrations</div></div>
                    <div class="eh-stat-card"><div class="eh-stat-number"><?= $report_data['att']['checked_in'] ?></div><div class="eh-stat-label">Total Attended</div></div>
                    <div class="eh-stat-card"><div class="eh-stat-number"><?= $report_data['rate'] ?>%</div><div class="eh-stat-label">Attendance Rate</div></div>
                </div>

                <h3><i data-lucide="users" style="width:18px;height:18px;"></i> Participant Journey Report</h3>

                <div class="eh-export-bar">
                    <button class="eh-btn eh-btn-secondary" onclick="exportToExcel()">
                        <i data-lucide="file-spreadsheet" style="width:16px;height:16px;"></i> Export to Excel
                    </button>
                    <button class="eh-btn eh-btn-secondary" onclick="window.print()">
                        <i data-lucide="file-text" style="width:16px;height:16px;"></i> Print / PDF
                    </button>
                </div>

                <div class="eh-table-wrap">
                    <table class="eh-table" id="participantTable">
                        <thead>
                            <tr>
                                <th>Name</th><th>Email</th><th>Registered</th>
                                <th>Table</th><th>Check-In</th><th>Check-Out</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = $report_data['participants']->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars(trim($p['first_name'].' '.($p['middle_name']?:'').' '.$p['last_name'])) ?></td>
                                    <td><?= htmlspecialchars($p['email']) ?></td>
                                    <td><?= date('M j, Y', strtotime($p['registration_date'])) ?></td>
                                    <td><?= $p['table_number'] ?: '—' ?></td>
                                    <td><?= $p['check_in_time'] ? date('g:i A', strtotime($p['check_in_time'])) : '—' ?></td>
                                    <td><?= $p['check_out_time'] ? date('g:i A', strtotime($p['check_out_time'])) : '—' ?></td>
                                    <td><?php if ($p['att_status'] === 'present' || $p['check_in_time']): ?>
                                        <span class="eh-badge eh-badge-success"><i data-lucide="check-circle" style="width:13px;height:13px;"></i> Present</span>
                                    <?php else: ?>
                                        <span class="eh-badge eh-badge-danger"><i data-lucide="x-circle" style="width:13px;height:13px;"></i> Absent</span>
                                    <?php endif; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<script>
lucide.createIcons();
function exportToExcel() {
    const a = document.createElement('a');
    document.body.appendChild(a);
    a.href = 'data:application/vnd.ms-excel,' + document.getElementById('participantTable').outerHTML.replace(/ /g,'%20');
    a.download = 'report_<?= date('Y-m-d') ?>.xls';
    a.click(); document.body.removeChild(a);
}
</script>
</body>
</html>