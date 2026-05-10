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
$event_id  = $_GET['id'] ?? null;

if (!$event_id) { header("Location: admin_all_events.php"); exit(); }

$stmt = $conn->prepare("
    SELECT e.*,
           v.name as venue_name, v.address as venue_address, v.city as venue_city, v.capacity as venue_capacity,
           o.name as organizer_name, o.contact_email as organizer_email, o.phone as organizer_phone,
           c.category_name,
           COUNT(DISTINCT r.registration_id) as total_registrations,
           COUNT(DISTINCT CASE WHEN r.status = 'confirmed' THEN r.registration_id END) as confirmed_registrations,
           COUNT(DISTINCT a.attendance_id) as total_attendance
    FROM event e
    LEFT JOIN venue v ON e.venue_id = v.venue_id
    LEFT JOIN organizer o ON e.organizer_id = o.organizer_id
    LEFT JOIN event_category c ON e.category_id = c.category_id
    LEFT JOIN registration r ON e.event_id = r.event_id
    LEFT JOIN attendance a ON r.registration_id = a.registration_id
    WHERE e.event_id = ?
    GROUP BY e.event_id
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { header("Location: admin_all_events.php"); exit(); }
$event = $result->fetch_assoc();
$stmt->close();

$reg_stmt = $conn->prepare("
    SELECT u.first_name, u.middle_name, u.last_name, u.email, u.phone,
           r.registration_date, r.status, r.table_number,
           a.check_in_time, a.check_out_time, a.status as attendance_status
    FROM registration r
    JOIN user u ON r.user_id = u.user_id
    LEFT JOIN attendance a ON r.registration_id = a.registration_id
    WHERE r.event_id = ?
    ORDER BY r.registration_date DESC
");
$reg_stmt->bind_param("i", $event_id);
$reg_stmt->execute();
$registrations = $reg_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Event - Admin Panel</title>
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
            <div class="admin-badge"><i data-lucide="shield" style="width:14px;height:14px;"></i> Administrator</div>
            <h1>Event Details</h1>
            <p>View comprehensive event information</p>
        </div>

        <a href="admin_all_events.php" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#f0f0f0;color:#1a1a1a;text-decoration:none;border-radius:8px;font-weight:600;margin-bottom:20px;">
            <i data-lucide="arrow-left"></i> Back to All Events
        </a>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
            <?php foreach ([
                ['Total Registrations', $event['total_registrations'], $event['capacity'] ? "Out of {$event['capacity']} capacity" : 'Unlimited capacity'],
                ['Confirmed', $event['confirmed_registrations'], 'Confirmed registrations'],
                ['Attendance', $event['total_attendance'], 'Checked in'],
                ['Available Slots', $event['capacity'] ? max(0, $event['capacity'] - $event['total_registrations']) : 'N/A', 'Remaining capacity'],
            ] as [$label, $val, $sub]): ?>
            <div style="background:white;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);border-left:4px solid #e63946;">
                <h3 style="font-size:0.85rem;color:#6b6b6b;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;"><?= $label ?></h3>
                <div style="font-size:2rem;font-weight:700;color:#e63946;"><?= $val ?></div>
                <small><?= $sub ?></small>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Event Info -->
        <div class="management-card">
            <h2 style="margin-bottom:20px;"><i data-lucide="info" style="width:20px;height:20px;vertical-align:middle;"></i> Event Information</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
                <div><div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">Title</div><div style="font-size:1.1rem;font-weight:600;margin-top:4px;"><?= htmlspecialchars($event['title']) ?></div></div>
                <div><div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">Category</div><div style="font-size:1.1rem;font-weight:600;margin-top:4px;"><?= htmlspecialchars($event['category_name'] ?? 'Uncategorized') ?></div></div>
                <div><div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">Start</div><div style="font-size:1.1rem;font-weight:600;margin-top:4px;"><?= date('F j, Y - g:i A', strtotime($event['start_time'])) ?></div></div>
                <div><div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">End</div><div style="font-size:1.1rem;font-weight:600;margin-top:4px;"><?= date('F j, Y - g:i A', strtotime($event['end_time'])) ?></div></div>
                <div>
    <div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">Capacity</div>
    <div style="font-size:1.1rem;font-weight:600;margin-top:4px;">
        <?= $event['capacity'] ? $event['capacity'] . ' people' : 'Unlimited' ?>
    </div>
    <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:8px;">
        <?php if (!($event['requires_registration'] ?? 1)): ?>
            <span style="font-size:0.72rem;padding:3px 8px;background:#f0fdf4;color:#166534;border-radius:10px;font-weight:600;">Announcement Only</span>
        <?php else: ?>
            <span style="font-size:0.72rem;padding:3px 8px;background:#dbeafe;color:#1e40af;border-radius:10px;font-weight:600;">Registration Required</span>
        <?php endif; ?>
        <?php if (!($event['show_on_landing'] ?? 1)): ?>
            <span style="font-size:0.72rem;padding:3px 8px;background:#fef3c7;color:#92400e;border-radius:10px;font-weight:600;">Hidden from Landing</span>
        <?php else: ?>
            <span style="font-size:0.72rem;padding:3px 8px;background:#d1fae5;color:#065f46;border-radius:10px;font-weight:600;">Public</span>
        <?php endif; ?>
        <?php if (!empty($event['has_volunteer'])): ?>
            <span style="font-size:0.72rem;padding:3px 8px;background:#ede9fe;color:#5b21b6;border-radius:10px;font-weight:600;">Volunteers Enabled</span>
        <?php endif; ?>
        <?php if (!empty($event['has_tables'])): ?>
            <span style="font-size:0.72rem;padding:3px 8px;background:#fee2e2;color:#991b1b;border-radius:10px;font-weight:600;">
                Tables: <?= $event['num_tables'] ?? 'N/A' ?>
                <?php if (!empty($event['seats_per_table'])): ?>(<?= $event['seats_per_table'] ?>/table)<?php endif; ?>
            </span>
        <?php endif; ?>
    </div>
</div>
            </div>
            <div style="margin-top:20px;"><div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">Description</div><p style="margin-top:8px;line-height:1.6;"><?= nl2br(htmlspecialchars($event['description'])) ?></p></div>
        </div>

        <!-- Venue -->
        <div class="management-card">
            <h2 style="margin-bottom:20px;"><i data-lucide="map-pin" style="width:20px;height:20px;vertical-align:middle;"></i> Venue</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
                <div><div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">Name</div><div style="font-size:1.1rem;font-weight:600;margin-top:4px;"><?= htmlspecialchars($event['venue_name']) ?></div></div>
                <div><div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">Address</div><div style="font-size:1.1rem;font-weight:600;margin-top:4px;"><?= htmlspecialchars($event['venue_address']) ?></div></div>
                <div><div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">City</div><div style="font-size:1.1rem;font-weight:600;margin-top:4px;"><?= htmlspecialchars($event['venue_city']) ?></div></div>
            </div>
        </div>

        <!-- Organizer -->
        <div class="management-card">
            <h2 style="margin-bottom:20px;"><i data-lucide="user-circle" style="width:20px;height:20px;vertical-align:middle;"></i> Organizer</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
                <div><div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">Name</div><div style="font-size:1.1rem;font-weight:600;margin-top:4px;"><?= htmlspecialchars($event['organizer_name']) ?></div></div>
                <div><div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">Email</div><div style="font-size:1.1rem;font-weight:600;margin-top:4px;"><?= htmlspecialchars($event['organizer_email']) ?></div></div>
                <div><div style="font-size:0.85rem;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.5px;">Phone</div><div style="font-size:1.1rem;font-weight:600;margin-top:4px;"><?= htmlspecialchars($event['organizer_phone']) ?></div></div>
            </div>
        </div>

        <!-- Participants -->
        <div class="management-card">
            <h2>Registered Participants (<?= $registrations->num_rows ?>)</h2>
            <?php if ($registrations->num_rows > 0): ?>
                <div class="table-wrapper">
                <table class="management-table">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Registered</th><th>Table</th><th>Status</th><th>Check-In</th><th>Check-Out</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($reg = $registrations->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars(trim($reg['first_name'].' '.$reg['middle_name'].' '.$reg['last_name'])) ?></td>
                            <td><?= htmlspecialchars($reg['email']) ?></td>
                            <td><?= date('M j, Y', strtotime($reg['registration_date'])) ?></td>
                            <td><?= $reg['table_number'] ? 'Table '.$reg['table_number'] : '—' ?></td>
                            <td><span class="badge badge-<?= $reg['status']==='confirmed'?'success':'warning' ?>"><?= ucfirst($reg['status']) ?></span></td>
                            <td><?= $reg['check_in_time'] ? date('g:i A', strtotime($reg['check_in_time'])) : '—' ?></td>
                            <td><?= $reg['check_out_time'] ? date('g:i A', strtotime($reg['check_out_time'])) : '—' ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div class="empty-state"><i data-lucide="users"></i><h3>No Registrations Yet</h3><p>No one has registered for this event yet.</p></div>
            <?php endif; ?>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>