<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole('admin');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin/admin-login.php");
    exit();
}

include('../../includes/db.php');

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role      = $_SESSION['role'];

$events_query = $conn->query("SELECT event_id, title FROM event ORDER BY start_time DESC");

$selected_event = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;
$attendances    = [];

if ($selected_event) {
    $query = "
        SELECT u.first_name, u.middle_name, u.last_name, u.email,
               a.check_in_time, a.check_out_time, a.status
        FROM registration r
        JOIN user u ON r.user_id = u.user_id
        LEFT JOIN attendance a ON r.registration_id = a.registration_id
        WHERE r.event_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $selected_event);
    $stmt->execute();
    $attendances = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>View Attendance - Admin Panel</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/management.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-layout">
    <?php include('admin_sidebar.php'); ?>

    <main class="management-content">
        <div class="admin-header">
            <div class="admin-badge">
                <i data-lucide="shield" style="width:14px;height:14px;"></i>
                Administrator
            </div>
            <h1>View Attendance</h1>
            <p>View attendance records for all events</p>
        </div>

        <!-- Event Selection -->
        <div class="management-card">
            <h2>Select Event</h2>
            <form method="GET" class="management-search">
                <select name="event_id" id="event_id" required>
                    <option value="">-- Select an Event --</option>
                    <?php while ($event = $events_query->fetch_assoc()): ?>
                        <option value="<?= $event['event_id'] ?>" <?= $selected_event == $event['event_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($event['title']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="eye"></i>
                    View Attendance
                </button>
            </form>
        </div>

        <?php if ($selected_event && $attendances): ?>
            <div class="management-card">
                <!-- card-toolbar: heading left, export button right — stacks on mobile -->
                <div class="card-toolbar">
                    <h2>Attendance List</h2>
                    <button onclick="exportToExcel()" class="btn btn-primary btn-sm">
                        <i data-lucide="download"></i>
                        Export to Excel
                    </button>
                </div>

                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search by name or email..."
                    class="attendance-search"
                    onkeyup="filterTable()"
                >

                <div class="table-wrapper">
                    <table class="management-table" id="attendanceTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $attendances->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= $row['check_in_time'] ?? '—' ?></td>
                                    <td><?= $row['check_out_time'] ?? '—' ?></td>
                                    <td>
                                        <span class="badge badge-<?= ($row['status'] ?? 'absent') === 'present' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($row['status'] ?? 'absent') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($selected_event): ?>
            <div class="management-card">
                <div class="empty-state">
                    <i data-lucide="users"></i>
                    <h3>No Participants Yet</h3>
                    <p>No one has registered for this event yet.</p>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        lucide.createIcons();

        function filterTable() {
            const filter = document.getElementById('searchInput').value.toLowerCase();
            const rows   = document.getElementById('attendanceTable').getElementsByTagName('tr');
            for (let i = 1; i < rows.length; i++) {
                const name  = rows[i].cells[0]?.textContent || '';
                const email = rows[i].cells[1]?.textContent || '';
                rows[i].style.display =
                    name.toLowerCase().includes(filter) || email.toLowerCase().includes(filter) ? '' : 'none';
            }
        }

        function exportToExcel() {
            const table = document.getElementById('attendanceTable');
            let csv = 'Name,Email,Check-In,Check-Out,Status\n';
            table.querySelectorAll('tbody tr').forEach(row => {
                if (row.style.display !== 'none') {
                    const cols = Array.from(row.querySelectorAll('td'))
                        .map(td => '"' + td.innerText.trim().replace(/"/g, '""') + '"')
                        .join(',');
                    csv += cols + '\n';
                }
            });
            const link = document.createElement('a');
            link.href     = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
            link.download = 'attendance_export.csv';
            link.click();
        }
    </script>
</body>
</html>