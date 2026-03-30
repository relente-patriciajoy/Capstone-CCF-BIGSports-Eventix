<?php
require_once('../../includes/session.php');
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/index.php");
    exit();
}
include('../../includes/db.php');

$user_id = $_SESSION['user_id'];

// Mark volunteer events as seen — add 1 second buffer to ensure all current records are before this
$_SESSION['volunteer_seen_at'] = time() + 1;

// Fetch all volunteer events the user has joined
$stmt = $conn->prepare("
    SELECT ve.volunteer_event_id, ve.title, ve.description, ve.event_date, ve.location,
           vrt.role_name,
           CONCAT(u.first_name,' ',u.last_name) AS lead_name,
           u.email AS lead_email, u.phone AS lead_phone,
           vm.status, vm.joined_at
    FROM volunteer_member vm
    JOIN volunteer_role_type vrt ON vm.role_type_id = vrt.role_type_id
    JOIN volunteer_event ve ON vrt.volunteer_event_id = ve.volunteer_event_id
    LEFT JOIN user u ON vrt.team_lead_id = u.user_id
    WHERE vm.user_id = ?
    ORDER BY ve.event_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vol_events = $stmt->get_result();
$stmt->close();

$role_labels = ['ushering' => 'Ushering', 'admin' => 'Admin', 'technical' => 'Technical'];
$role_colors = ['ushering' => '#3b82f6', 'admin' => '#f59e0b', 'technical' => '#8b5cf6'];
$role_icons  = ['ushering' => 'door-open', 'admin' => 'clipboard-list', 'technical' => 'monitor'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>My Volunteer Events — Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/event_head.css">
    <link rel="stylesheet" href="../../css/volunteer.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
</head>
<body class="dashboard-layout <?= isset($role) && $role === 'event_head' ? 'event-head-page' : '' ?>">
<?php
// Set role for sidebar
$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();
?>
<?php include('../components/sidebar.php'); ?>

<main class="main-content">
    <header class="banner <?= $role === 'event_head' ? 'event-head-banner' : '' ?>">
        <div>
            <?php if ($role === 'event_head'): ?>
            <div class="event-head-badge">
                <i data-lucide="briefcase" style="width:14px;height:14px;"></i>
                Event Organizer
            </div>
            <?php endif; ?>
            <h1>My Volunteer Events</h1>
            <p>Events you've signed up to volunteer for</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
    </header>

    <div style="padding: 24px;">

        <?php if ($vol_events->num_rows > 0): ?>
            <div class="vol-grid">
                <?php while ($ve = $vol_events->fetch_assoc()):
                    $color = $role_colors[$ve['role_name']] ?? '#6b7280';
                    $label = $role_labels[$ve['role_name']] ?? ucfirst($ve['role_name']);
                    $icon  = $role_icons[$ve['role_name']]  ?? 'users';
                    $is_past = strtotime($ve['event_date']) < time();
                ?>
                <div class="vol-card" style="border-top: 4px solid <?= $color ?>;">
                    <div class="vol-card-header">
                        <div class="vol-card-title"><?= htmlspecialchars($ve['title']) ?></div>
                        <div class="vol-card-meta">
                            <span>
                                <i data-lucide="calendar" style="width:13px;height:13px;"></i>
                                <?= date('F j, Y · g:i A', strtotime($ve['event_date'])) ?>
                                <?php if ($is_past): ?>
                                    <span style="background:#fee2e2;color:#b91c1c;font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:20px;margin-left:4px;">Past</span>
                                <?php else: ?>
                                    <span style="background:#d1fae5;color:#065f46;font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:20px;margin-left:4px;">Upcoming</span>
                                <?php endif; ?>
                            </span>
                            <?php if ($ve['location']): ?>
                            <span>
                                <i data-lucide="map-pin" style="width:13px;height:13px;"></i>
                                <?= htmlspecialchars($ve['location']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="vol-card-body">
                        <span class="vol-role-badge" style="background:<?= $color ?>20;color:<?= $color ?>;">
                            <i data-lucide="<?= $icon ?>" style="width:13px;height:13px;"></i>
                            <?= $label ?> Team
                        </span>

                        <?php if ($ve['lead_name']): ?>
                        <div class="vol-lead-box">
                            <div class="vol-lead-label">Team Lead</div>
                            <div class="vol-lead-name"><?= htmlspecialchars($ve['lead_name']) ?></div>
                            <?php if ($ve['lead_email']): ?>
                                <div class="vol-lead-contact">
                                    <i data-lucide="mail" style="width:11px;height:11px;vertical-align:middle;"></i>
                                    <?= htmlspecialchars($ve['lead_email']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($ve['lead_phone']): ?>
                                <div class="vol-lead-contact">
                                    <i data-lucide="phone" style="width:11px;height:11px;vertical-align:middle;"></i>
                                    <?= htmlspecialchars($ve['lead_phone']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="vol-lead-box" style="color:#9ca3af;text-align:center;font-size:0.82rem;">
                            No team lead assigned yet
                        </div>
                        <?php endif; ?>

                        <?php if ($ve['description']): ?>
                        <p style="font-size:0.82rem;color:#6b7280;margin-top:12px;line-height:1.6;">
                            <?= htmlspecialchars($ve['description']) ?>
                        </p>
                        <?php endif; ?>
                    </div>

                    <div class="vol-card-footer">
                        <span class="vol-status-badge vol-status-<?= $ve['status'] ?>">
                            <i data-lucide="<?= $ve['status']==='confirmed'?'check-circle':'clock' ?>"
                               style="width:12px;height:12px;"></i>
                            <?= ucfirst($ve['status']) ?>
                        </span>
                        <span class="vol-joined-date">
                            Joined <?= date('M j, Y', strtotime($ve['joined_at'])) ?>
                        </span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

        <?php else: ?>
            <div class="empty-volunteer">
                <div class="empty-volunteer-icon">
                    <i data-lucide="users" style="width:36px;height:36px;opacity:0.4;"></i>
                </div>
                <h3 style="font-size:1.1rem;font-weight:700;color:#374151;margin-bottom:8px;">
                    No Volunteer Events Yet
                </h3>
                <p style="font-size:0.9rem;max-width:320px;margin:0 auto;">
                    You haven't signed up as a volunteer for any events. Scan a volunteer QR code to get started.
                </p>
            </div>
        <?php endif; ?>

    </div>
</main>

<script>
lucide.createIcons();
</script>
</body>
</html>
<?php $conn->close(); ?>