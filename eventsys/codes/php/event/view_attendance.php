<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole(['event_head', 'admin']);
include('../../includes/db.php');
require_once('../../includes/permission_functions.php');

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role_name'];

$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id); $role_stmt->execute();
$role_stmt->bind_result($role); $role_stmt->fetch(); $role_stmt->close();
if (empty($role)) $role = 'user';

if (!hasPermission($conn, $user_id, 'attendance.view.own') && !hasPermission($conn, $user_id, 'attendance.view.all')) {
    die('<html><body style="display:flex;align-items:center;justify-content:center;height:100vh;background:#f3f4f6;font-family:Poppins,sans-serif;"><div style="text-align:center;padding:40px;background:white;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.1);"><h1 style="color:#ef4444;margin-bottom:16px;">Access Denied</h1><p style="color:#6b7280;margin-bottom:24px;">You don\'t have permission to view attendance records.</p><a href="../event/manage_events.php" style="padding:12px 24px;background:#800020;color:white;text-decoration:none;border-radius:8px;font-weight:600;">Back to Dashboard</a></div></body></html>');
}

$user_stmt = $conn->prepare("SELECT email FROM user WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id); $user_stmt->execute();
$user_stmt->bind_result($email); $user_stmt->fetch(); $user_stmt->close();

$org_stmt = $conn->prepare("SELECT organizer_id FROM organizer WHERE contact_email = ?");
$org_stmt->bind_param("s", $email); $org_stmt->execute();
$org_stmt->bind_result($organizer_id); $org_stmt->fetch(); $org_stmt->close();

if ($user_role === 'admin' || hasPermission($conn, $user_id, 'attendance.view.all')) {
    $event_query = $conn->prepare("SELECT event_id, title FROM event ORDER BY start_time DESC");
    $event_query->execute();
} else {
    $event_query = $conn->prepare("SELECT DISTINCT e.event_id, e.title FROM event e LEFT JOIN event_access ea ON e.event_id = ea.event_id AND ea.user_id = ? WHERE e.organizer_id = ? OR ea.can_view = 1 ORDER BY e.start_time DESC");
    $event_query->bind_param("ii", $user_id, $organizer_id); $event_query->execute();
}
$events = $event_query->get_result();

$selected_event = $_GET['event_id'] ?? null;
$attendances = null; $event_title = '';

if ($selected_event) {
    if (!canAccessEvent($conn, $user_id, $selected_event, 'view')) {
        die('<html><body style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:Poppins,sans-serif;"><div style="text-align:center;padding:40px;background:white;border-radius:16px;"><h1 style="color:#ef4444;">Access Denied</h1><a href="view_attendance.php" style="padding:12px 24px;background:#800020;color:white;text-decoration:none;border-radius:8px;">Back</a></div></body></html>');
    }
    $title_stmt = $conn->prepare("SELECT title FROM event WHERE event_id = ?");
    $title_stmt->bind_param("i", $selected_event); $title_stmt->execute();
    $title_stmt->bind_result($event_title); $title_stmt->fetch(); $title_stmt->close();

    $stmt = $conn->prepare("SELECT u.first_name, u.middle_name, u.last_name, u.email, a.check_in_time, a.check_out_time, a.status FROM registration r JOIN user u ON r.user_id = u.user_id LEFT JOIN attendance a ON r.registration_id = a.registration_id WHERE r.event_id = ? ORDER BY u.last_name, u.first_name");
    $stmt->bind_param("i", $selected_event); $stmt->execute();
    $attendances = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Attendance - Eventix</title>
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
            <h1>Attendance Records</h1>
            <p>View and manage participants' attendance for your events</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo">
    </header>

    <div class="eh-page">
        <div class="eh-card">
            <h2><i data-lucide="eye" style="width:24px;height:24px;"></i> View Attendance</h2>

            <div class="eh-event-select-form">
                <form method="GET">
                    <label for="event_id"><i data-lucide="calendar" style="width:16px;height:16px;vertical-align:middle;"></i> Select Event</label>
                    <select name="event_id" id="event_id" required>
                        <option value="">-- Choose an Event --</option>
                        <?php $events->data_seek(0); while ($ev = $events->fetch_assoc()): ?>
                            <option value="<?= $ev['event_id'] ?>" <?= $selected_event == $ev['event_id'] ? 'selected' : '' ?>><?= htmlspecialchars($ev['title']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit"><i data-lucide="search" style="width:16px;height:16px;"></i> View Attendance</button>
                </form>
            </div>

            <?php if ($selected_event && $attendances):
                $total = 0; $present = 0; $absent = 0; $checked_out = 0;
                $attendances->data_seek(0);
                while ($stat = $attendances->fetch_assoc()) {
                    $total++;
                    if ($stat['status'] === 'present' || $stat['check_in_time']) { $present++; if ($stat['check_out_time']) $checked_out++; }
                    else $absent++;
                }
                $attendances->data_seek(0);
                $can_export = hasPermission($conn, $user_id, 'attendance.export') || canAccessEvent($conn, $user_id, $selected_event, 'export_data');
            ?>
                <div class="eh-stats-grid">
                    <div class="eh-stat-card"><div class="eh-stat-number"><?= $total ?></div><div class="eh-stat-label">Total Registered</div></div>
                    <div class="eh-stat-card"><div class="eh-stat-number"><?= $present ?></div><div class="eh-stat-label">Checked In</div></div>
                    <div class="eh-stat-card"><div class="eh-stat-number"><?= $checked_out ?></div><div class="eh-stat-label">Checked Out</div></div>
                    <div class="eh-stat-card"><div class="eh-stat-number"><?= $absent ?></div><div class="eh-stat-label">Not Attended</div></div>
                </div>

                <div class="eh-toolbar">
                    <input type="text" id="searchInput" class="eh-search" placeholder="Search by name or email..." onkeyup="filterTable()">
                    <?php if ($can_export): ?>
                        <button class="eh-btn eh-btn-secondary" onclick="exportToExcel()"><i data-lucide="download" style="width:16px;height:16px;"></i> Export to Excel</button>
                    <?php else: ?>
                        <span class="eh-badge eh-badge-gray"><i data-lucide="lock" style="width:14px;height:14px;"></i> Export restricted</span>
                    <?php endif; ?>
                </div>

                <table class="eh-table" id="attendanceTable">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Check-In</th><th>Check-Out</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $attendances->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= $row['check_in_time'] ? date('M j, Y g:i A', strtotime($row['check_in_time'])) : '—' ?></td>
                                <td><?= $row['check_out_time'] ? date('M j, Y g:i A', strtotime($row['check_out_time'])) : '—' ?></td>
                                <td><?php if ($row['status'] === 'present' || $row['check_in_time']): ?>
                                    <span class="eh-badge eh-badge-success"><i data-lucide="check-circle" style="width:13px;height:13px;"></i> Present</span>
                                <?php else: ?>
                                    <span class="eh-badge eh-badge-danger"><i data-lucide="x-circle" style="width:13px;height:13px;"></i> Absent</span>
                                <?php endif; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php elseif ($selected_event): ?>
                <div class="eh-empty">
                    <i data-lucide="inbox" style="width:56px;height:56px;"></i>
                    <p>No participants registered for this event yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
lucide.createIcons();
function filterTable() {
    const filter = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#attendanceTable tbody tr').forEach(row => {
        const name = row.cells[0]?.textContent.toLowerCase();
        const email = row.cells[1]?.textContent.toLowerCase();
        row.style.display = (name?.includes(filter) || email?.includes(filter)) ? '' : 'none';
    });
}
<?php if ($can_export ?? false): ?>
function exportToExcel() {
    const filename = 'attendance_<?= $event_title ? preg_replace('/[^a-zA-Z0-9]/', '_', $event_title) : 'data' ?>_<?= date('Y-m-d') ?>.xls';
    const a = document.createElement('a');
    document.body.appendChild(a);
    a.href = 'data:application/vnd.ms-excel,' + document.getElementById('attendanceTable').outerHTML.replace(/ /g,'%20');
    a.download = filename; a.click(); document.body.removeChild(a);
}
<?php endif; ?>
</script>
</body>
</html>