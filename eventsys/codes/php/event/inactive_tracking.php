<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole(['event_head', 'admin']);
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/index.php"); exit(); }
include('../../includes/db.php');

$user_id = $_SESSION['user_id'];
$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id); $role_stmt->execute();
$role_stmt->bind_result($role); $role_stmt->fetch(); $role_stmt->close();
if ($role !== 'event_head') die("Access denied.");

$email_stmt = $conn->prepare("SELECT email FROM user WHERE user_id = ?");
$email_stmt->bind_param("i", $user_id); $email_stmt->execute();
$email_stmt->bind_result($email); $email_stmt->fetch(); $email_stmt->close();

$org_stmt = $conn->prepare("SELECT organizer_id FROM organizer WHERE contact_email = ?");
$org_stmt->bind_param("s", $email); $org_stmt->execute();
$org_stmt->bind_result($organizer_id); $org_stmt->fetch(); $org_stmt->close();

$inactive_query = $conn->prepare("
    SELECT DISTINCT u.user_id, u.first_name, u.middle_name, u.last_name, u.email, u.phone,
        COUNT(r.registration_id) as total_registrations,
        SUM(CASE WHEN a.attendance_id IS NULL THEN 1 ELSE 0 END) as missed_events,
        MAX(e.start_time) as last_registered_event
    FROM registration r
    JOIN user u ON r.user_id = u.user_id
    JOIN event e ON r.event_id = e.event_id
    LEFT JOIN attendance a ON r.registration_id = a.registration_id
    WHERE e.organizer_id = ? AND e.end_time < NOW() AND a.attendance_id IS NULL
    GROUP BY u.user_id HAVING missed_events > 0
    ORDER BY missed_events DESC, last_registered_event DESC
");
$inactive_query->bind_param("i", $organizer_id); $inactive_query->execute();
$inactive_members = $inactive_query->get_result();

$total_inactive = $inactive_members->num_rows;
$high_risk = 0; $medium_risk = 0; $low_risk = 0;
$inactive_members->data_seek(0);
while ($m = $inactive_members->fetch_assoc()) {
    if ($m['missed_events'] >= 3) $high_risk++;
    elseif ($m['missed_events'] == 2) $medium_risk++;
    else $low_risk++;
}
$inactive_members->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inactive Members Tracking - Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/event_head.css">
    <link rel="stylesheet" href="../../css/inactive_tracking.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-layout event-head-page">
<?php include('../components/sidebar.php'); ?>
<main class="main-content">
    <header class="banner event-head-banner">
        <div>
            <div class="event-head-badge"><i data-lucide="briefcase" style="width:14px;height:14px;"></i> Event Organizer</div>
            <h1>Inactive Members Tracking</h1>
            <p>Track members who registered but didn't attend</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo">
    </header>

    <div class="eh-page">
        <div class="eh-stats-grid">
            <div class="eh-stat-card"><div class="eh-stat-number"><?= $total_inactive ?></div><div class="eh-stat-label">Total Inactive</div></div>
            <div class="eh-stat-card"><div class="eh-stat-number"><?= $high_risk ?></div><div class="eh-stat-label">High Risk (3+)</div></div>
            <div class="eh-stat-card"><div class="eh-stat-number"><?= $medium_risk ?></div><div class="eh-stat-label">Medium Risk (2)</div></div>
            <div class="eh-stat-card"><div class="eh-stat-number"><?= $low_risk ?></div><div class="eh-stat-label">Low Risk (1)</div></div>
        </div>

        <div class="eh-card">
            <h3><i data-lucide="user-x" style="width:20px;height:20px;"></i> Inactive Participants</h3>

            <?php if ($inactive_members->num_rows > 0): ?>
                <div class="eh-filter-bar">
                    <button class="eh-filter-btn active" data-filter="all">All Members</button>
                    <button class="eh-filter-btn" data-filter="high">High Risk (3+)</button>
                    <button class="eh-filter-btn" data-filter="medium">Medium Risk (2)</button>
                    <button class="eh-filter-btn" data-filter="low">Low Risk (1)</button>
                    <button class="eh-export-btn" onclick="exportToExcel()">
                        <i data-lucide="download" style="width:15px;height:15px;"></i> Export to Excel
                    </button>
                </div>

                <div class="eh-toolbar">
                    <input type="text" id="searchInput" class="eh-search" placeholder="Search by name, email, or phone...">
                </div>

                <div class="eh-table-wrap">
                <table class="eh-table" id="inactiveTable">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Phone</th><th>Total Registrations</th><th>Missed Events</th><th>Last Registered</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($m = $inactive_members->fetch_assoc()):
                            $full_name = trim(($m['first_name'] ?? '') . ' ' . ($m['middle_name'] ? $m['middle_name'] . ' ' : '') . ($m['last_name'] ?? ''));
                            $risk = $m['missed_events'] >= 3 ? 'high' : ($m['missed_events'] == 2 ? 'medium' : 'low');
                        ?>
                            <tr data-risk="<?= $risk ?>">
                                <td><?= htmlspecialchars($full_name) ?></td>
                                <td><?= htmlspecialchars($m['email']) ?></td>
                                <td><?= htmlspecialchars($m['phone'] ?: 'N/A') ?></td>
                                <td><?= $m['total_registrations'] ?></td>
                                <td><span class="eh-badge eh-badge-danger"><?= $m['missed_events'] ?></span></td>
                                <td><?= date('M j, Y', strtotime($m['last_registered_event'])) ?></td>
                                <td>
                                    <button class="eh-btn-sm" onclick="sendReminder('<?= htmlspecialchars($m['email']) ?>','<?= htmlspecialchars($full_name) ?>')">
                                        <i data-lucide="mail" style="width:14px;height:14px;"></i> Send Reminder
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div class="eh-empty">
                    <i data-lucide="check-circle" style="width:56px;height:56px;color:#10b981;"></i>
                    <h3>Great News!</h3>
                    <p>All registered participants have attended your events.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="../../js/inactive_tracking.js"></script>
<script>
lucide.createIcons();
document.querySelectorAll('.eh-filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.eh-filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const filter = this.getAttribute('data-filter');
        document.querySelectorAll('#inactiveTable tbody tr').forEach(row => {
            row.style.display = (filter === 'all' || row.getAttribute('data-risk') === filter) ? '' : 'none';
        });
    });
});
document.getElementById('searchInput')?.addEventListener('keyup', function() {
    const val = this.value.toLowerCase();
    document.querySelectorAll('#inactiveTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
});
function exportToExcel() {
    const a = document.createElement('a'); document.body.appendChild(a);
    a.href = 'data:application/vnd.ms-excel,' + document.getElementById('inactiveTable').outerHTML.replace(/ /g,'%20');
    a.download = 'inactive_members_<?= date('Y-m-d') ?>.xls';
    a.click(); document.body.removeChild(a);
}
</script>
</body>
</html>