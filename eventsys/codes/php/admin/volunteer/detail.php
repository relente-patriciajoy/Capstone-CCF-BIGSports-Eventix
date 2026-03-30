<?php
require_once('../../../includes/session.php');
require_once('../../../includes/role_protection.php');
requireRole('admin');
include('../../../includes/db.php');
require_once('../../../includes/qr_function.php');
require_once('../../../libraries/phpqrcode/qrlib.php');

$user_id = $_SESSION['user_id'];
$id      = (int)($_GET['id'] ?? 0);
$message = isset($_GET['saved']) ? "Volunteer event saved successfully!" : '';

$ev = $conn->prepare("SELECT * FROM volunteer_event WHERE volunteer_event_id = ?");
$ev->bind_param("i", $id);
$ev->execute();
$event = $ev->get_result()->fetch_assoc();
$ev->close();

if (!$event) {
    header("Location: index.php");
    exit();
}

// Roles + leads
$rq = $conn->prepare("
    SELECT vrt.role_type_id, vrt.role_name, vrt.team_lead_id,
           CONCAT(u.first_name,' ',u.last_name) AS lead_name,
           u.email AS lead_email
    FROM volunteer_role_type vrt
    LEFT JOIN user u ON vrt.team_lead_id = u.user_id
    WHERE vrt.volunteer_event_id = ?
    ORDER BY FIELD(vrt.role_name,'ushering','admin','technical')
");
$rq->bind_param("i", $id);
$rq->execute();
$roles = $rq->get_result()->fetch_all(MYSQLI_ASSOC);
$rq->close();

// Members per role
$role_members = [];
foreach ($roles as $r) {
    $mq = $conn->prepare("
        SELECT vm.volunteer_member_id, vm.status, vm.joined_at,
               CONCAT(u.first_name,' ',u.last_name) AS name,
               u.email, u.gender, u.phone
        FROM volunteer_member vm
        JOIN user u ON vm.user_id = u.user_id
        WHERE vm.role_type_id = ?
        ORDER BY vm.joined_at
    ");
    $mq->bind_param("i", $r['role_type_id']);
    $mq->execute();
    $role_members[$r['role_type_id']] = $mq->get_result()->fetch_all(MYSQLI_ASSOC);
    $mq->close();
}

$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url  = $protocol . '://' . $_SERVER['HTTP_HOST'] .
             '/Registration-System/eventsys/codes/php/auth/volunteer_signup.php?token=' . $event['qr_token'];

// Generate QR code file if not exists
$qr_dir      = __DIR__ . '/../../../qr_codes/';
$qr_filename = 'volunteer_qr_' . $id . '.png';
$qr_filepath = $qr_dir . $qr_filename;
// Serve via PHP endpoint since qr_codes/ is outside web-accessible php/ folder
$qr_web_path = '/Registration-System/eventsys/codes/php/admin/volunteer/serve_qr.php?file=' . urlencode($qr_filename);

if (!file_exists($qr_dir)) mkdir($qr_dir, 0755, true);
if (!file_exists($qr_filepath)) {
    QRcode::png($base_url, $qr_filepath, QR_ECLEVEL_H, 10, 2);
}

$role_labels = ['ushering'=>'Ushering','admin'=>'Admin','technical'=>'Technical'];
$role_colors = ['ushering'=>'#3b82f6','admin'=>'#f59e0b','technical'=>'#8b5cf6'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title><?= htmlspecialchars($event['title']) ?> — Volunteer Event</title>
    <link rel="stylesheet" href="../../../css/style.css">
    <link rel="stylesheet" href="../../../css/sidebar.css">
    <link rel="stylesheet" href="../../../css/management.css">
    <link rel="stylesheet" href="../../../css/volunteer.css">
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
        <h1><?= htmlspecialchars($event['title']) ?></h1>
        <p>
            <?= date('F j, Y · g:i A', strtotime($event['event_date'])) ?>
            <?= $event['location'] ? ' · ' . htmlspecialchars($event['location']) : '' ?>
        </p>
    </div>

    <?php if ($message): ?>
        <div class="management-alert success">
            <i data-lucide="check-circle"></i> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Action bar -->
    <div class="vol-action-bar">
        <a href="index.php" class="btn btn-secondary vol-action-btn">
            <i data-lucide="arrow-left" style="width:15px;height:15px;"></i> Back to List
        </a>
        <a href="create.php?edit=<?= $id ?>" class="btn btn-secondary vol-action-btn">
            <i data-lucide="edit" style="width:15px;height:15px;"></i> Edit Event
        </a>
        <button onclick="openQR()" class="btn btn-primary vol-action-btn">
            <i data-lucide="qr-code" style="width:15px;height:15px;"></i> Show QR Code
        </button>
    </div>

    <!-- Role cards -->
    <?php if (empty($roles)): ?>
        <div class="management-card">
            <div class="empty-state">
                <i data-lucide="users"></i>
                <h3>No Roles Configured</h3>
                <p>Edit this event to add volunteer roles and team leads.</p>
                <a href="create.php?edit=<?= $id ?>" class="btn btn-primary" style="margin-top:16px;">Add Roles</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($roles as $r):
            $members = $role_members[$r['role_type_id']] ?? [];
            $color   = $role_colors[$r['role_name']] ?? '#6b7280';
            $label   = $role_labels[$r['role_name']] ?? ucfirst($r['role_name']);
        ?>
        <div class="management-card" style="border-left:4px solid <?= $color ?>;">
            <div class="card-toolbar">
                <div>
                    <span style="background:<?= $color ?>20;color:<?= $color ?>;font-size:0.75rem;font-weight:700;padding:5px 14px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px;">
                        <?= $label ?> Team
                    </span>
                    <div style="margin-top:8px;font-size:0.88rem;color:#6b7280;">
                        <?php if ($r['lead_name']): ?>
                            <i data-lucide="user-check" style="width:13px;height:13px;vertical-align:middle;"></i>
                            Team Lead: <strong><?= htmlspecialchars($r['lead_name']) ?></strong>
                            — <?= htmlspecialchars($r['lead_email']) ?>
                        <?php else: ?>
                            <span style="color:#f59e0b;">
                                <i data-lucide="alert-triangle" style="width:13px;height:13px;vertical-align:middle;"></i>
                                No team lead assigned
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="badge badge-info"><?= count($members) ?> volunteer(s)</span>
            </div>

            <?php if (!empty($members)): ?>
                <div class="table-wrapper">
                    <table class="management-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Gender</th>
                                <th>Joined</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $m): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                                <td><?= htmlspecialchars($m['email']) ?></td>
                                <td><?= htmlspecialchars($m['phone'] ?? '—') ?></td>
                                <td><?= ucfirst($m['gender'] ?? '—') ?></td>
                                <td><?= date('M j, Y', strtotime($m['joined_at'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $m['status']==='confirmed'?'success':'warning' ?>">
                                        <?= ucfirst($m['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color:#9ca3af;font-size:0.88rem;text-align:center;padding:20px 0;">
                    No volunteers yet for this role. Share the QR code to get volunteers.
                </p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<!-- QR Modal -->
<div id="qrModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1000;align-items:center;justify-content:center;padding:12px;">
    <div style="background:white;border-radius:16px;padding:24px 16px;max-width:380px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.25);box-sizing:border-box;">
        <h3 style="margin-bottom:6px;font-size:1rem;">Volunteer QR Code</h3>
        <p style="color:#6b7280;font-size:0.8rem;margin-bottom:16px;">
            Share this with potential volunteers. They'll sign in or register then choose their role.
        </p>
        <div style="background:#f9f9f9;padding:12px;border-radius:12px;border:3px dashed #800020;display:block;width:100%;box-sizing:border-box;">
            <img src="<?= htmlspecialchars($qr_web_path) ?>"
                 alt="Volunteer QR Code"
                 style="width:100%;max-width:220px;height:auto;display:block;margin:0 auto;">
        </div>
        <div style="display:flex;gap:10px;justify-content:center;margin-top:20px;">
            <a href="<?= htmlspecialchars($qr_web_path) ?>&download=1"
               download="volunteer-qr-<?= $id ?>.png"
               class="btn btn-primary btn-sm"
               style="width:120px;">
                <i data-lucide="download" style="width:13px;height:13px;"></i> Download
            </a>
            <button onclick="closeQR()" class="btn btn-secondary btn-sm vol-close-btn" style="width:120px;">Close</button>
        </div>
    </div>
</div>

<script>
lucide.createIcons();

function openQR() {
    document.getElementById('qrModal').style.display = 'flex';
    lucide.createIcons();
}

function closeQR() {
    document.getElementById('qrModal').style.display = 'none';
}

document.getElementById('qrModal').addEventListener('click', function(e) {
    if (e.target === this) closeQR();
});

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