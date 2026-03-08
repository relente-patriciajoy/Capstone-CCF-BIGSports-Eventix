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

$engagement_query = $conn->prepare("
    SELECT u.user_id, u.first_name, u.middle_name, u.last_name, u.email,
        COUNT(DISTINCT r.event_id) as total_events_registered,
        COUNT(DISTINCT CASE WHEN a.attendance_id IS NOT NULL THEN r.event_id END) as events_attended,
        COUNT(DISTINCT CASE WHEN a.attendance_id IS NULL AND e.end_time < NOW() THEN r.event_id END) as events_missed,
        ROUND((COUNT(DISTINCT CASE WHEN a.attendance_id IS NOT NULL THEN r.event_id END) * 100.0) / NULLIF(COUNT(DISTINCT CASE WHEN e.end_time < NOW() THEN r.event_id END), 0), 2) as attendance_rate,
        MIN(r.registration_date) as first_registration,
        MAX(e.start_time) as last_event_date,
        CASE
            WHEN COUNT(DISTINCT r.event_id) >= 5 AND (COUNT(DISTINCT CASE WHEN a.attendance_id IS NOT NULL THEN r.event_id END) * 100.0) / NULLIF(COUNT(DISTINCT CASE WHEN e.end_time < NOW() THEN r.event_id END), 0) >= 80 THEN 'Highly Engaged'
            WHEN COUNT(DISTINCT r.event_id) >= 3 AND (COUNT(DISTINCT CASE WHEN a.attendance_id IS NOT NULL THEN r.event_id END) * 100.0) / NULLIF(COUNT(DISTINCT CASE WHEN e.end_time < NOW() THEN r.event_id END), 0) >= 60 THEN 'Active'
            WHEN COUNT(DISTINCT r.event_id) >= 2 AND (COUNT(DISTINCT CASE WHEN a.attendance_id IS NOT NULL THEN r.event_id END) * 100.0) / NULLIF(COUNT(DISTINCT CASE WHEN e.end_time < NOW() THEN r.event_id END), 0) >= 40 THEN 'Moderate'
            WHEN (COUNT(DISTINCT CASE WHEN a.attendance_id IS NOT NULL THEN r.event_id END) * 100.0) / NULLIF(COUNT(DISTINCT CASE WHEN e.end_time < NOW() THEN r.event_id END), 0) < 40 OR COUNT(DISTINCT CASE WHEN a.attendance_id IS NOT NULL THEN r.event_id END) = 0 THEN 'At Risk'
            ELSE 'New'
        END as engagement_level
    FROM registration r
    JOIN user u ON r.user_id = u.user_id
    JOIN event e ON r.event_id = e.event_id
    LEFT JOIN attendance a ON r.registration_id = a.registration_id
    WHERE e.organizer_id = ?
    GROUP BY u.user_id ORDER BY attendance_rate DESC, total_events_registered DESC
");
$engagement_query->bind_param("i", $organizer_id); $engagement_query->execute();
$participants = $engagement_query->get_result();

$total_unique = 0; $highly_engaged = 0; $active = 0; $moderate = 0; $at_risk = 0; $new_members = 0; $total_rate = 0;
$participants->data_seek(0);
while ($p = $participants->fetch_assoc()) {
    $total_unique++; $total_rate += $p['attendance_rate'] ?? 0;
    switch ($p['engagement_level']) {
        case 'Highly Engaged': $highly_engaged++; break;
        case 'Active':         $active++;         break;
        case 'Moderate':       $moderate++;       break;
        case 'At Risk':        $at_risk++;        break;
        case 'New':            $new_members++;    break;
    }
}
$avg_rate = $total_unique > 0 ? round($total_rate / $total_unique, 2) : 0;
$participants->data_seek(0);

$trends_query = $conn->prepare("SELECT DATE_FORMAT(e.start_time,'%Y-%m') as month, DATE_FORMAT(e.start_time,'%b %Y') as month_name, COUNT(DISTINCT r.registration_id) as total_registrations, COUNT(DISTINCT CASE WHEN a.attendance_id IS NOT NULL THEN r.registration_id END) as attended FROM event e JOIN registration r ON e.event_id = r.event_id LEFT JOIN attendance a ON r.registration_id = a.registration_id WHERE e.organizer_id = ? AND e.start_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND e.end_time < NOW() GROUP BY DATE_FORMAT(e.start_time,'%Y-%m') ORDER BY month DESC");
$trends_query->bind_param("i", $organizer_id); $trends_query->execute();
$trends = $trends_query->get_result();
$months = []; $regs = []; $attended = [];
while ($t = $trends->fetch_assoc()) { $months[] = $t['month_name']; $regs[] = $t['total_registrations']; $attended[] = $t['attended']; }
$months = array_reverse($months); $regs = array_reverse($regs); $attended = array_reverse($attended);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participant Engagement - Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/event_head.css">
    <link rel="stylesheet" href="../../css/participant_engagement.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dashboard-layout event-head-page">
<?php include('../components/sidebar.php'); ?>
<main class="main-content">
    <header class="banner event-head-banner">
        <div>
            <div class="event-head-badge"><i data-lucide="briefcase" style="width:14px;height:14px;"></i> Event Organizer</div>
            <h1>Participant Engagement Analytics</h1>
            <p>Track participant behavior and engagement across all your events</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo">
    </header>

    <div class="eh-page">
        <!-- Summary metrics (using existing participant_engagement.css classes) -->
        <div class="metrics-dashboard">
            <div class="metric-card metric-primary">
                <div class="metric-icon"><i data-lucide="users"></i></div>
                <div class="metric-content"><div class="metric-label">Total Unique Participants</div><div class="metric-value"><?= $total_unique ?></div><small>Across all your events</small></div>
            </div>
            <div class="metric-card metric-success">
                <div class="metric-icon"><i data-lucide="trending-up"></i></div>
                <div class="metric-content"><div class="metric-label">Average Attendance Rate</div><div class="metric-value"><?= $avg_rate ?>%</div><small>Overall performance</small></div>
            </div>
            <div class="metric-card metric-info">
                <div class="metric-icon"><i data-lucide="star"></i></div>
                <div class="metric-content"><div class="metric-label">Highly Engaged</div><div class="metric-value"><?= $highly_engaged ?></div><small>5+ events, 80%+ attendance</small></div>
            </div>
            <div class="metric-card metric-warning">
                <div class="metric-icon"><i data-lucide="alert-circle"></i></div>
                <div class="metric-content"><div class="metric-label">At Risk</div><div class="metric-value"><?= $at_risk ?></div><small>Need attention</small></div>
            </div>
        </div>

        <!-- Distribution (existing CSS) -->
        <div class="engagement-distribution">
            <h3><i data-lucide="pie-chart"></i> Engagement Level Distribution</h3>
            <div class="distribution-grid">
                <div class="distribution-item level-highly-engaged"><div class="distribution-bar" style="width:<?= $total_unique > 0 ? ($highly_engaged / $total_unique * 100) : 0 ?>%"></div><div class="distribution-label"><span class="level-name">Highly Engaged</span><span class="level-count"><?= $highly_engaged ?></span></div></div>
                <div class="distribution-item level-active"><div class="distribution-bar" style="width:<?= $total_unique > 0 ? ($active / $total_unique * 100) : 0 ?>%"></div><div class="distribution-label"><span class="level-name">Active</span><span class="level-count"><?= $active ?></span></div></div>
                <div class="distribution-item level-moderate"><div class="distribution-bar" style="width:<?= $total_unique > 0 ? ($moderate / $total_unique * 100) : 0 ?>%"></div><div class="distribution-label"><span class="level-name">Moderate</span><span class="level-count"><?= $moderate ?></span></div></div>
                <div class="distribution-item level-at-risk"><div class="distribution-bar" style="width:<?= $total_unique > 0 ? ($at_risk / $total_unique * 100) : 0 ?>%"></div><div class="distribution-label"><span class="level-name">At Risk</span><span class="level-count"><?= $at_risk ?></span></div></div>
                <div class="distribution-item level-new"><div class="distribution-bar" style="width:<?= $total_unique > 0 ? ($new_members / $total_unique * 100) : 0 ?>%"></div><div class="distribution-label"><span class="level-name">New</span><span class="level-count"><?= $new_members ?></span></div></div>
            </div>
        </div>

        <?php if (count($months) > 0): ?>
        <div class="trends-section">
            <h3><i data-lucide="activity"></i> Participation Trends (Last 6 Months)</h3>
            <canvas id="trendsChart"></canvas>
        </div>
        <?php endif; ?>

        <!-- Participants table -->
        <div class="eh-card">
            <h3><i data-lucide="users" style="width:20px;height:20px;"></i> All Participants</h3>
            <div class="eh-toolbar">
                <input type="text" id="searchInput" class="eh-search" placeholder="Search participants...">
                <select id="filterEngagement" class="eh-select">
                    <option value="all">All Levels</option>
                    <option value="Highly Engaged">Highly Engaged</option>
                    <option value="Active">Active</option>
                    <option value="Moderate">Moderate</option>
                    <option value="At Risk">At Risk</option>
                    <option value="New">New</option>
                </select>
                <button class="eh-btn eh-btn-secondary" onclick="exportToExcel()"><i data-lucide="download" style="width:16px;height:16px;"></i> Export</button>
            </div>

            <?php if ($participants->num_rows > 0): ?>
            <table class="eh-table" id="engagementTable">
                <thead><tr><th>Participant</th><th>Email</th><th>Total Events</th><th>Attended</th><th>Missed</th><th>Attendance Rate</th><th>Engagement Level</th><th>Member Since</th><th>Last Event</th></tr></thead>
                <tbody>
                    <?php $participants->data_seek(0); while ($p = $participants->fetch_assoc()):
                        $full_name = trim($p['first_name'] . ' ' . ($p['middle_name'] ?: '') . ' ' . $p['last_name']);
                        $ec = strtolower(str_replace(' ', '-', $p['engagement_level']));
                    ?>
                    <tr data-engagement="<?= htmlspecialchars($p['engagement_level']) ?>">
                        <td><?= htmlspecialchars($full_name) ?></td>
                        <td><?= htmlspecialchars($p['email']) ?></td>
                        <td><?= $p['total_events_registered'] ?></td>
                        <td><span class="eh-badge eh-badge-success"><?= $p['events_attended'] ?></span></td>
                        <td><span class="eh-badge eh-badge-danger"><?= $p['events_missed'] ?></span></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width:<?= $p['attendance_rate'] ?? 0 ?>%"></div>
                                <span class="progress-text"><?= $p['attendance_rate'] ?? 0 ?>%</span>
                            </div>
                        </td>
                        <td><span class="engagement-badge engagement-<?= $ec ?>"><?= $p['engagement_level'] ?></span></td>
                        <td><?= date('M j, Y', strtotime($p['first_registration'])) ?></td>
                        <td><?= $p['last_event_date'] ? date('M j, Y', strtotime($p['last_event_date'])) : 'N/A' ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="eh-empty">
                    <i data-lucide="inbox" style="width:56px;height:56px;"></i>
                    <p>No participant data yet. Start creating events to see engagement analytics.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
lucide.createIcons();
<?php if (count($months) > 0): ?>
new Chart(document.getElementById('trendsChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [
            { label: 'Registrations', data: <?= json_encode($regs) ?>, borderColor: '#e63946', backgroundColor: 'rgba(230,57,70,0.1)', tension: 0.4 },
            { label: 'Attended',      data: <?= json_encode($attended) ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', tension: 0.4 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
});
<?php endif; ?>

document.getElementById('searchInput').addEventListener('keyup', filterTable);
document.getElementById('filterEngagement').addEventListener('change', filterTable);
function filterTable() {
    const s = document.getElementById('searchInput').value.toLowerCase();
    const f = document.getElementById('filterEngagement').value;
    document.querySelectorAll('#engagementTable tbody tr').forEach(row => {
        const match = (row.cells[0].textContent.toLowerCase().includes(s) || row.cells[1].textContent.toLowerCase().includes(s))
                   && (f === 'all' || row.getAttribute('data-engagement') === f);
        row.style.display = match ? '' : 'none';
    });
}
function exportToExcel() {
    const a = document.createElement('a'); document.body.appendChild(a);
    a.href = 'data:application/vnd.ms-excel,' + document.getElementById('engagementTable').outerHTML.replace(/ /g,'%20');
    a.download = 'engagement_<?= date('Y-m-d') ?>.xls';
    a.click(); document.body.removeChild(a);
}
</script>
</body>
</html>