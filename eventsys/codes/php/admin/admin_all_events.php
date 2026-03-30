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

if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM event WHERE event_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_all_events.php?status=deleted");
    exit();
}

$search       = $_GET['search'] ?? '';
$search_param = "%$search%";

if (!empty($search)) {
    $stmt = $conn->prepare("
        SELECT e.event_id, e.title, e.start_time, e.end_time, e.capacity,
               v.name AS venue, o.name AS organizer,
               COUNT(r.registration_id) AS registrations
        FROM event e
        LEFT JOIN venue v ON e.venue_id = v.venue_id
        LEFT JOIN organizer o ON e.organizer_id = o.organizer_id
        LEFT JOIN registration r ON e.event_id = r.event_id
        WHERE e.title LIKE ? OR v.name LIKE ? OR o.name LIKE ?
        GROUP BY e.event_id
        ORDER BY e.start_time DESC
    ");
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} else {
    $stmt = $conn->prepare("
        SELECT e.event_id, e.title, e.start_time, e.end_time, e.capacity,
               v.name AS venue, o.name AS organizer,
               COUNT(r.registration_id) AS registrations
        FROM event e
        LEFT JOIN venue v ON e.venue_id = v.venue_id
        LEFT JOIN organizer o ON e.organizer_id = o.organizer_id
        LEFT JOIN registration r ON e.event_id = r.event_id
        GROUP BY e.event_id
        ORDER BY e.start_time DESC
    ");
}

$stmt->execute();
$events = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>All Events - Admin Panel</title>
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
            <h1>All Events</h1>
            <p>View and manage all events in the system</p>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <div class="management-alert success">
                <?php if ($_GET['status'] === 'deleted') echo "Event deleted successfully."; ?>
                <span class="close-btn" onclick="this.parentElement.style.display='none';">×</span>
            </div>
        <?php endif; ?>

        <div class="management-card">
            <form method="GET" class="management-search">
                <input type="text" name="search"
                       placeholder="Search by event name, venue, or organizer..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search"></i> Search
                </button>
            </form>
        </div>

        <div class="management-card">
            <h2>All Events (<?= $events->num_rows ?>)</h2>
            <?php if ($events->num_rows > 0): ?>
                <table class="management-table">
                    <thead>
                        <tr>
                            <th>Event Title</th>
                            <th>Venue</th>
                            <th>Organizer</th>
                            <th>Date</th>
                            <th>Registrations</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($event = $events->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($event['title']) ?></strong></td>
                                <td><?= htmlspecialchars($event['venue']) ?></td>
                                <td><?= htmlspecialchars($event['organizer']) ?></td>
                                <td><?= date('M j, Y', strtotime($event['start_time'])) ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?= $event['registrations'] ?> / <?= $event['capacity'] ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="admin_view_event.php?id=<?= $event['event_id'] ?>"
                                       class="btn btn-primary btn-sm">
                                        <i data-lucide="eye"></i> View
                                    </a>
                                    <a href="admin_all_events.php?delete=<?= $event['event_id'] ?>"
                                       class="btn btn-delete btn-sm"
                                       onclick="return confirm('Delete this event? All registrations will be lost.')">
                                        <i data-lucide="trash-2"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="calendar"></i>
                    <h3>No Events Found</h3>
                    <p>No events match your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        lucide.createIcons();
        setTimeout(() => {
            document.querySelectorAll('.management-alert').forEach(a => {
                a.style.opacity = '0';
                a.style.transform = 'translateY(-10px)';
                setTimeout(() => a.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>