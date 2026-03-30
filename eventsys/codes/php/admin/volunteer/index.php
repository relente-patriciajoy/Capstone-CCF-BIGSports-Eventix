<?php
require_once('../../../includes/session.php');
require_once('../../../includes/role_protection.php');
requireRole('admin');
include('../../../includes/db.php');

$user_id = $_SESSION['user_id'];
$message = '';
$error   = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $del = $conn->prepare("DELETE FROM volunteer_event WHERE volunteer_event_id = ?");
    $del->bind_param("i", $del_id);
    if ($del->execute()) $message = "Volunteer event deleted.";
    else $error = "Failed to delete.";
    $del->close();
}

$events = $conn->query("
    SELECT ve.*,
           (SELECT COUNT(*) FROM volunteer_role_type vrt
            WHERE vrt.volunteer_event_id = ve.volunteer_event_id) AS roles_count,
           (SELECT COUNT(*) FROM volunteer_member vm
            JOIN volunteer_role_type vrt ON vm.role_type_id = vrt.role_type_id
            WHERE vrt.volunteer_event_id = ve.volunteer_event_id) AS volunteers_count
    FROM volunteer_event ve
    ORDER BY ve.event_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Volunteer Management — Admin</title>
    <link rel="stylesheet" href="../../../css/style.css">
    <link rel="stylesheet" href="../../../css/sidebar.css">
    <link rel="stylesheet" href="../../../css/management.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-layout">
<?php include(__DIR__ . '/../admin_sidebar.php'); ?>

<main class="management-content">
    <div class="admin-header">
        <div class="admin-badge">
            <i data-lucide="shield" style="width:14px;height:14px;"></i>
            Administrator
        </div>
        <h1>Volunteer Management</h1>
        <p>Create and manage volunteer events with team roles</p>
    </div>

    <?php if ($message): ?>
        <div class="management-alert success">
            <i data-lucide="check-circle"></i> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="management-alert error">
            <i data-lucide="alert-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="management-card">
        <div class="card-toolbar">
            <h2>Volunteer Events</h2>
            <a href="create.php" class="btn btn-primary">
                <i data-lucide="plus" style="width:15px;height:15px;"></i>
                Create Volunteer Event
            </a>
        </div>

        <?php if ($events && $events->num_rows > 0): ?>
            <div class="table-wrapper">
                <table class="management-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Roles</th>
                            <th>Volunteers</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ve = $events->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ve['title']) ?></strong></td>
                            <td><?= date('M j, Y · g:i A', strtotime($ve['event_date'])) ?></td>
                            <td><?= htmlspecialchars($ve['location'] ?? '—') ?></td>
                            <td><span class="badge badge-info"><?= $ve['roles_count'] ?> roles</span></td>
                            <td><span class="badge badge-success"><?= $ve['volunteers_count'] ?> people</span></td>
                            <td>
                                <div class="actions">
                                    <a href="detail.php?id=<?= $ve['volunteer_event_id'] ?>"
                                       class="btn btn-sm btn-primary">
                                        <i data-lucide="eye" style="width:13px;height:13px;"></i> View
                                    </a>
                                    <a href="create.php?edit=<?= $ve['volunteer_event_id'] ?>"
                                       class="btn btn-sm btn-secondary">
                                        <i data-lucide="edit" style="width:13px;height:13px;"></i> Edit
                                    </a>
                                    <a href="?delete=<?= $ve['volunteer_event_id'] ?>"
                                       class="btn btn-sm btn-delete"
                                       onclick="return confirm('Delete this volunteer event?')">
                                        <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i data-lucide="users"></i>
                <h3>No Volunteer Events Yet</h3>
                <p>Create your first volunteer event to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
lucide.createIcons();
document.querySelectorAll('.management-alert').forEach(a => {
    setTimeout(() => {
        a.style.transition = 'opacity 0.3s'; a.style.opacity = '0';
        setTimeout(() => a.remove(), 300);
    }, 4000);
});
</script>
</body>
</html>
<?php $conn->close(); ?>