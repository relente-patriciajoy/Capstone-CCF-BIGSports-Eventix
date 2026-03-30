<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

require_once('../../includes/role_protection.php');
requireRole('admin');

include('../../includes/db.php');

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role      = $_SESSION['role'];

$stats = [];

$result = $conn->query("SELECT COUNT(*) as count FROM user");
$stats['total_users'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM event");
$stats['total_events'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM registration");
$stats['total_registrations'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM registration WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['recent_registrations'] = $result->fetch_assoc()['count'];

$recent_users    = $conn->query("SELECT user_id, CONCAT(first_name, ' ', last_name) as name, email, role, created_at FROM user ORDER BY created_at DESC LIMIT 5");
$upcoming_events = $conn->query("SELECT e.event_id, e.title, e.start_time, v.name as venue, COUNT(r.registration_id) as registrations FROM event e LEFT JOIN venue v ON e.venue_id = v.venue_id LEFT JOIN registration r ON e.event_id = r.event_id WHERE e.start_time >= NOW() GROUP BY e.event_id ORDER BY e.start_time ASC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Admin Dashboard - Eventix</title>
    <link rel="icon" type="image/png" href="../../assets/eventix-logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/management.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-layout">
    <?php include('admin_sidebar.php'); ?>

    <main class="management-content">
        <!-- Admin Header — no inline styles, all in management.css -->
        <div class="admin-header">
            <div class="admin-badge">
                <i data-lucide="shield" style="width:14px;height:14px;"></i>
                Administrator
            </div>
            <h1>Admin Dashboard</h1>
            <p>Welcome back, <?= htmlspecialchars($full_name) ?></p>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>Total Users</h3>
                    <div class="stat-card-icon"><i data-lucide="users" size="24"></i></div>
                </div>
                <div class="stat-card-value"><?= $stats['total_users'] ?></div>
                <div class="stat-card-change">Registered users</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>Total Events</h3>
                    <div class="stat-card-icon"><i data-lucide="calendar" size="24"></i></div>
                </div>
                <div class="stat-card-value"><?= $stats['total_events'] ?></div>
                <div class="stat-card-change">All time events</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>Total Registrations</h3>
                    <div class="stat-card-icon"><i data-lucide="ticket" size="24"></i></div>
                </div>
                <div class="stat-card-value"><?= $stats['total_registrations'] ?></div>
                <div class="stat-card-change">Event registrations</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>This Week</h3>
                    <div class="stat-card-icon"><i data-lucide="trending-up" size="24"></i></div>
                </div>
                <div class="stat-card-value"><?= $stats['recent_registrations'] ?></div>
                <div class="stat-card-change positive">New registrations</div>
            </div>
        </div>

        <!-- Quick Actions — .quick-actions + .action-card in management.css -->
        <h2>Quick Actions</h2>
        <div class="quick-actions">
            <a href="../admin/manage_user.php" class="action-card">
                <h3><i data-lucide="users" style="width:20px;height:20px;"></i>Manage Users</h3>
                <p>Add, edit, or remove users</p>
            </a>
            <a href="../admin/manage_venue.php" class="action-card">
                <h3><i data-lucide="map-pin" style="width:20px;height:20px;"></i>Manage Venues</h3>
                <p>Configure event locations</p>
            </a>
            <a href="../admin/manage_organizer.php" class="action-card">
                <h3><i data-lucide="briefcase" style="width:20px;height:20px;"></i>Manage Organizers</h3>
                <p>Event organizer management</p>
            </a>
            <a href="../admin/manage_categories.php" class="action-card">
                <h3><i data-lucide="folder" style="width:20px;height:20px;"></i>Event Categories</h3>
                <p>Organize events by category</p>
            </a>
            <a href="user_promotions.php" class="action-card">
                <h3><i data-lucide="user-plus" style="width:20px;height:20px;"></i>Promote Users</h3>
                <p>Upgrade user roles</p>
            </a>
        </div>

        <!-- Recent Activity — .recent-activity-grid in management.css -->
        <div class="recent-activity-grid">
            <div class="management-card">
                <h2>Recent Users</h2>
                <div class="table-wrapper">
                    <table class="management-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $recent_users->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'event_head' ? 'warning' : 'info') ?>">
                                            <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="management-card">
                <h2>Upcoming Events</h2>
                <div class="table-wrapper">
                    <table class="management-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Registrations</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($event['title']) ?></td>
                                    <td><?= $event['registrations'] ?></td>
                                    <td><?= date('M j', strtotime($event['start_time'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();
        function toggleDropdown(toggle) {
            toggle.closest(".dropdown-nav").classList.toggle("open");
        }
    </script>
</body>
</html>