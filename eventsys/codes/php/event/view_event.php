<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole(['event_head', 'admin']);
include('../../includes/db.php');
require_once('../../includes/permission_functions.php');

$user_id  = $_SESSION['user_id'];
$event_id = (int)($_GET['event_id'] ?? 0);

if (!$event_id) { header("Location: manage_events.php"); exit(); }

// Get event details
$stmt = $conn->prepare("
    SELECT e.*, v.name AS venue_name, v.address AS venue_address, v.city AS venue_city,
           c.category_name, o.name AS organizer_name,
           COUNT(DISTINCT r.registration_id) AS total_registered
    FROM event e
    LEFT JOIN venue v ON e.venue_id = v.venue_id
    LEFT JOIN event_category c ON e.category_id = c.category_id
    LEFT JOIN organizer o ON e.organizer_id = o.organizer_id
    LEFT JOIN registration r ON e.event_id = r.event_id
    WHERE e.event_id = ?
    GROUP BY e.event_id
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) { header("Location: manage_events.php"); exit(); }

// Get role for sidebar
$rs = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$rs->bind_param("i", $user_id); $rs->execute();
$rs->bind_result($role); $rs->fetch(); $rs->close();

// Get participants
$parts = $conn->prepare("
    SELECT u.first_name, u.last_name, u.email, u.gender,
           r.registration_id, r.table_number, r.status, r.registration_date
    FROM registration r
    JOIN user u ON r.user_id = u.user_id
    WHERE r.event_id = ?
    ORDER BY r.registration_date ASC
");
$parts->bind_param("i", $event_id);
$parts->execute();
$participants = $parts->get_result();

// Get volunteer info if enabled
$vol_event = null;
$vol_roles = [];
$vol_members_by_role = [];
if ($event['has_volunteer']) {
    $ve = $conn->prepare("SELECT * FROM volunteer_event WHERE event_id = ? LIMIT 1");
    $ve->bind_param("i", $event_id); $ve->execute();
    $vol_event = $ve->get_result()->fetch_assoc(); $ve->close();

    if ($vol_event) {
        $vr = $conn->prepare("
            SELECT vrt.role_type_id, vrt.role_name,
                   CONCAT(u.first_name,' ',u.last_name) AS lead_name,
                   COUNT(vm.volunteer_member_id) AS member_count
            FROM volunteer_role_type vrt
            LEFT JOIN user u ON vrt.team_lead_id = u.user_id
            LEFT JOIN volunteer_member vm ON vm.role_type_id = vrt.role_type_id
            WHERE vrt.volunteer_event_id = ?
            GROUP BY vrt.role_type_id
        ");
        $vr->bind_param("i", $vol_event['volunteer_event_id']);
        $vr->execute();
        $vol_roles = $vr->get_result()->fetch_all(MYSQLI_ASSOC);
        $vr->close();

        // Get all volunteer members per role
        $vm = $conn->prepare("
            SELECT vm.volunteer_member_id, vm.role_type_id, vm.status, vm.joined_at,
                   u.first_name, u.last_name, u.email, u.phone, u.gender
            FROM volunteer_member vm
            JOIN user u ON vm.user_id = u.user_id
            WHERE vm.role_type_id IN (
                SELECT role_type_id FROM volunteer_role_type WHERE volunteer_event_id = ?
            )
            ORDER BY vm.joined_at ASC
        ");
        $vm->bind_param("i", $vol_event['volunteer_event_id']);
        $vm->execute();
        $all_members = $vm->get_result()->fetch_all(MYSQLI_ASSOC);
        $vm->close();

        foreach ($all_members as $m) {
            $vol_members_by_role[$m['role_type_id']][] = $m;
        }
    }
}

// Table summary if enabled
$table_summary = [];
if ($event['has_tables'] && $event['num_tables']) {
    for ($t = 1; $t <= $event['num_tables']; $t++) {
        $tc = $conn->prepare("SELECT COUNT(*) as cnt FROM registration WHERE event_id = ? AND table_number = ?");
        $tc->bind_param("ii", $event_id, $t);
        $tc->execute();
        $cnt = $tc->get_result()->fetch_assoc()['cnt'];
        $tc->close();
        $table_summary[$t] = $cnt;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['title']) ?> — Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/event_head.css">
    <link rel="stylesheet" href="../../css/management.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .view-header { display:flex; align-items:center; gap:12px; margin-bottom:24px; flex-wrap:wrap; }
        .view-header h1 { flex:1; margin:0; font-size:1.6rem; }
        .view-tabs { display:flex; flex-wrap:nowrap; gap:8px; margin-bottom:24px; border-bottom:2px solid #e0e0e0; padding-bottom:16px; }
        .view-tab { flex:1; text-align:center; padding:10px 14px; background:#f3f4f6; border:2px solid #e0e0e0; border-radius:8px; cursor:pointer; font-weight:600; font-size:0.88rem; font-family:'Poppins',sans-serif; transition:all 0.2s; }
        .view-tab.active { background:#800020; color:white; border-color:#800020; }
        .view-tab:hover:not(.active) { border-color:#800020; color:#800020; background:#fff5f5; }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }
        .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:24px; }
        .info-card { background:#f9f9f9; border-radius:10px; padding:16px; border-left:4px solid #800020; }
        .info-card-label { font-size:0.78rem; color:#6b6b6b; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
        .info-card-value { font-size:1rem; font-weight:600; color:#1a1a1a; }
        .badge-row { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:20px; }
        .badge { font-size:0.78rem; font-weight:600; padding:4px 10px; border-radius:20px; display:flex; align-items:center; gap:5px; }
        .badge-reg    { background:#dbeafe; color:#1e40af; }
        .badge-noreg  { background:#f3f4f6; color:#6b6b6b; }
        .badge-public { background:#d1fae5; color:#065f46; }
        .badge-hidden { background:#fef3c7; color:#92400e; }
        .badge-vol    { background:#ede9fe; color:#5b21b6; }
        .badge-table  { background:#fee2e2; color:#991b1b; }
        .participant-table { width:100%; border-collapse:collapse; }
        .participant-table th { background:#800020; color:white; padding:10px 14px; text-align:left; font-size:0.85rem; }
        .participant-table td { padding:10px 14px; border-bottom:1px solid #f0f0f0; font-size:0.88rem; }
        .participant-table tr:hover td { background:#fff5f5; }
        .table-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; }
        .table-card { background:white; border:2px solid #e0e0e0; border-radius:10px; padding:16px; text-align:center; }
        .table-card-num { font-size:1.4rem; font-weight:700; color:#800020; }
        .table-card-count { font-size:0.85rem; color:#6b6b6b; margin-top:4px; }
        .table-card.full { border-color:#e63946; background:#fff5f5; }
        .vol-role-card { background:#faf5ff; border:1px solid #e9d5ff; border-radius:10px; padding:16px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; }
        .vol-role-name { font-weight:700; font-size:0.95rem; color:#5b21b6; }
        .vol-role-meta { font-size:0.83rem; color:#6b6b6b; margin-top:4px; }
        .qr-box { text-align:center; padding:24px; background:#f9f9f9; border-radius:12px; }
        .qr-box img { width:200px; height:200px; border:4px solid #800020; border-radius:10px; padding:8px; background:white; }
        .qr-url { font-size:0.78rem; color:#6b6b6b; margin-top:10px; word-break:break-all; }
        .empty-tab { text-align:center; padding:40px 20px; color:#6b6b6b; }
        .empty-tab i { width:48px; height:48px; color:#d0d0d0; margin-bottom:12px; }
        .btn-view-role {
            display:inline-flex; align-items:center; gap:5px;
            padding:6px 12px; background:#800020; color:white;
            border:none; border-radius:8px; font-size:0.82rem;
            font-weight:600; cursor:pointer; font-family:'Poppins',sans-serif;
            transition:all 0.2s;
        }
        .btn-view-role:hover { background:#5b21b6; color:white; }
        .role-members-table { width:100%; border-collapse:collapse; margin-top:12px; }
        .role-members-table th { background:#5b21b6; color:white; padding:9px 12px; text-align:left; font-size:0.82rem; }
        .role-members-table td { padding:9px 12px; border-bottom:1px solid #f0f0f0; font-size:0.83rem; }
        .role-members-table tr:hover td { background:#faf5ff; }
        .role-modal {
            background:white; border-radius:16px;
            max-width:700px; width:95%; max-height:85vh;
            overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3);
        }
        .role-modal-header {
            background:linear-gradient(135deg,#5b21b6,#7c3aed);
            color:white; padding:20px 24px; border-radius:16px 16px 0 0;
            display:flex; justify-content:space-between; align-items:center;
        }
        .role-modal-body { padding:20px 24px; }

        @media (max-width:600px) {
            .view-tabs { flex-wrap:wrap; }
            .view-tab { flex:1 1 40%; }
            .participant-table { font-size:0.78rem; }
            .participant-table th, .participant-table td { padding:8px; }
        }
    </style>
</head>
<body class="dashboard-layout <?= $role === 'event_head' ? 'event-head-page' : '' ?>">
    <?php include('../components/sidebar.php'); ?>

    <main class="main-content">
        <header class="banner <?= $role === 'event_head' ? 'event-head-banner' : '' ?>">
            <div>
                <div class="event-head-badge">
                    <i data-lucide="eye" style="width:14px;height:14px;"></i>
                    Event Detail
                </div>
                <h1><?= htmlspecialchars($event['title']) ?></h1>
                <p><?= htmlspecialchars($event['venue_name']) ?></p>
            </div>
            <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
        </header>

        <div class="main-content-inner">
            <div class="event-head-hub">

                <!-- Back + Edit buttons -->
                <div class="view-header">
                    <a href="manage_events.php" class="btn-secondary" style="display:inline-flex;align-items:center;gap:6px;">
                        <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back
                    </a>
                    <a href="manage_events.php?edit=<?= $event_id ?>" class="btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                        <i data-lucide="edit" style="width:16px;height:16px;"></i> Edit Event
                    </a>
                </div>

                <!-- Status badges -->
                <div class="badge-row">
                    <?php if ($event['requires_registration']): ?>
                        <span class="badge badge-reg"><i data-lucide="user-check" style="width:12px;height:12px;"></i> Registration Required</span>
                    <?php else: ?>
                        <span class="badge badge-noreg"><i data-lucide="megaphone" style="width:12px;height:12px;"></i> Announcement Only</span>
                    <?php endif; ?>
                    <?php if ($event['show_on_landing']): ?>
                        <span class="badge badge-public"><i data-lucide="globe" style="width:12px;height:12px;"></i> Public</span>
                    <?php else: ?>
                        <span class="badge badge-hidden"><i data-lucide="eye-off" style="width:12px;height:12px;"></i> Hidden from Landing</span>
                    <?php endif; ?>
                    <?php if ($event['has_volunteer']): ?>
                        <span class="badge badge-vol"><i data-lucide="users" style="width:12px;height:12px;"></i> Volunteers Enabled</span>
                    <?php endif; ?>
                    <?php if ($event['has_tables']): ?>
                        <span class="badge badge-table"><i data-lucide="layout-grid" style="width:12px;height:12px;"></i> Tables Enabled</span>
                    <?php endif; ?>
                </div>

                <!-- Stats row -->
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-card-label">Date</div>
                        <div class="info-card-value"><?= date('M j, Y', strtotime($event['start_time'])) ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-label">Time</div>
                        <div class="info-card-value"><?= date('g:i A', strtotime($event['start_time'])) ?> – <?= date('g:i A', strtotime($event['end_time'])) ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-label">Venue</div>
                        <div class="info-card-value"><?= htmlspecialchars($event['venue_name']) ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-label">Registered</div>
                        <div class="info-card-value">
                            <?= $event['total_registered'] ?>
                            <?php if ($event['capacity']): ?> / <?= $event['capacity'] ?><?php else: ?> / <span style="font-weight:400;font-size:0.85rem;">Unlimited</span><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($event['has_tables']): ?>
                    <div class="info-card">
                        <div class="info-card-label">Tables</div>
                        <div class="info-card-value">
                            <?= $event['num_tables'] ?? 'N/A' ?> tables
                            <?php if ($event['seats_per_table']): ?><span style="font-size:0.82rem;font-weight:400;"> · <?= $event['seats_per_table'] ?> seats each</span><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tabs -->
                <div class="view-tabs">
                    <button class="view-tab active" onclick="switchTab(event,'details')">Details</button>
                    <?php if ($event['requires_registration']): ?>
                    <button class="view-tab" onclick="switchTab(event,'participants')">
                        Participants (<?= $event['total_registered'] ?>)
                    </button>
                    <?php endif; ?>
                    <?php if ($event['has_tables']): ?>
                    <button class="view-tab" onclick="switchTab(event,'tables')">Tables</button>
                    <?php endif; ?>
                    <?php if ($event['has_volunteer']): ?>
                    <button class="view-tab" onclick="switchTab(event,'volunteers')">Volunteers</button>
                    <?php endif; ?>
                </div>

                <!-- Tab: Details -->
                <div id="tab-details" class="tab-panel active">
                    <div class="management-card">
                        <h3 style="margin-bottom:12px;">Event Description</h3>
                        <p style="color:#444;line-height:1.7;"><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                        <hr style="margin:16px 0;border-color:#f0f0f0;">
                        <p><strong>Category:</strong> <?= htmlspecialchars($event['category_name'] ?? 'N/A') ?></p>
                        <p><strong>Organizer:</strong> <?= htmlspecialchars($event['organizer_name'] ?? 'N/A') ?></p>
                        <?php if ($event['gender_separated']): ?>
                        <p><strong>Gender Separation:</strong> <span style="color:#800020;font-weight:600;">Enabled</span></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab: Participants -->
                <?php if ($event['requires_registration']): ?>
                <div id="tab-participants" class="tab-panel">
                    <div class="management-card">
                        <?php if ($participants->num_rows > 0): ?>
                            <div class="table-wrapper">
                                <table class="participant-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Gender</th>
                                            <?php if ($event['has_tables']): ?><th>Table</th><?php endif; ?>
                                            <th>Status</th>
                                            <th>Registered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; while ($p = $participants->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
                                            <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                                            <td><?= htmlspecialchars($p['email']) ?></td>
                                            <td><?= ucfirst($p['gender'] ?? 'N/A') ?></td>
                                            <?php if ($event['has_tables']): ?>
                                            <td>
                                                <?php if ($p['table_number'] > 0): ?>
                                                    <span style="background:#fee2e2;color:#991b1b;padding:3px 8px;border-radius:10px;font-weight:600;font-size:0.82rem;">
                                                        Table <?= $p['table_number'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:#6b6b6b;font-size:0.82rem;">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            <td>
                                                <span style="background:<?= $p['status']==='confirmed'?'#d1fae5':'#fef3c7' ?>;color:<?= $p['status']==='confirmed'?'#065f46':'#92400e' ?>;padding:3px 8px;border-radius:10px;font-size:0.78rem;font-weight:600;">
                                                    <?= ucfirst($p['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($p['registration_date'])) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-tab">
                                <i data-lucide="users"></i>
                                <p>No participants registered yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tab: Tables -->
                <?php if ($event['has_tables']): ?>
                <div id="tab-tables" class="tab-panel">
                    <div class="management-card">
                        <?php if (!empty($table_summary)): ?>
                            <p style="margin-bottom:16px;color:#6b6b6b;font-size:0.88rem;">
                                <?= $event['num_tables'] ?> tables
                                <?php if ($event['seats_per_table']): ?>· <?= $event['seats_per_table'] ?> seats each<?php else: ?>· No seat limit<?php endif; ?>
                                <?php if ($event['gender_separated']): ?>· Gender separated<?php endif; ?>
                            </p>
                            <div class="table-grid">
                                <?php foreach ($table_summary as $tnum => $tcount): ?>
                                    <?php
                                    $is_full = $event['seats_per_table'] && $tcount >= $event['seats_per_table'];
                                    $label = '';
                                    if ($event['gender_separated'] && $event['num_tables']) {
                                        $half = (int)ceil($event['num_tables'] / 2);
                                        $label = $tnum <= $half ? 'Male' : 'Female';
                                    }
                                    ?>
                                    <div class="table-card <?= $is_full ? 'full' : '' ?>">
                                        <div class="table-card-num">Table <?= $tnum ?></div>
                                        <?php if ($label): ?>
                                            <div style="font-size:0.72rem;color:#800020;font-weight:600;margin-top:2px;"><?= $label ?></div>
                                        <?php endif; ?>
                                        <div class="table-card-count">
                                            <?= $tcount ?> <?= $event['seats_per_table'] ? '/ ' . $event['seats_per_table'] : '' ?> seated
                                        </div>
                                        <?php if ($is_full): ?>
                                            <div style="font-size:0.72rem;color:#e63946;font-weight:700;margin-top:4px;">FULL</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-tab">
                                <i data-lucide="layout-grid"></i>
                                <p>No tables configured yet. Edit the event to set up tables.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tab: Volunteers -->
                <?php if ($event['has_volunteer']): ?>
                <div id="tab-volunteers" class="tab-panel">
                    <div class="management-card">
                        <?php if ($vol_event): ?>
                            <!-- QR Code -->
                            <div class="qr-box" style="margin-bottom:24px;">
                                <h3 style="margin-bottom:16px;color:#5b21b6;">Volunteer Sign-up QR Code</h3>
                                <?php
                                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                                $basePath = rtrim(dirname(dirname($currentPath)), '/\\');
                                $signup_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/auth/volunteer_signup.php?token=' . $vol_event['qr_token'];
                                $qr_img = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($signup_url);
                                ?>
                                <img src="<?= $qr_img ?>" alt="Volunteer QR Code">
                                <div class="qr-hint" style="margin-top:12px;font-size:0.9rem;color:#6b6b6b;">Scan this QR code to sign up as a volunteer.</div>
                                <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&download=1&data=<?= urlencode($signup_url) ?>"
                                   class="btn-primary" style="display:inline-flex;align-items:center;gap:6px;margin-top:12px;">
                                    <i data-lucide="download" style="width:15px;height:15px;"></i> Download QR
                                </a>
                            </div>

                            <!-- Roles -->
                            <?php if (!empty($vol_roles)): ?>
                                <?php foreach ($vol_roles as $vr): ?>
                                    <div class="vol-role-card">
                                        <div>
                                            <div class="vol-role-name"><?= htmlspecialchars($vr['role_name']) ?></div>
                                            <div class="vol-role-meta">
                                                <?php if ($vr['lead_name']): ?>Team Lead: <?= htmlspecialchars($vr['lead_name']) ?> · <?php endif; ?>
                                                <?= $vr['member_count'] ?> volunteer<?= $vr['member_count'] != 1 ? 's' : '' ?>
                                            </div>
                                        </div>
                                        <div style="display:flex;gap:8px;align-items:center;">
                                            <button class="btn-view-role btn-sm"
                                                onclick="openRoleModal(<?= $vr['role_type_id'] ?>, '<?= htmlspecialchars($vr['role_name'], ENT_QUOTES) ?>')">
                                                <i data-lucide="eye" style="width:14px;height:14px;"></i> View
                                            </button>
                                            <button class="btn-delete btn-sm"
                                                onclick="confirmDeleteRole(<?= $vr['role_type_id'] ?>, '<?= htmlspecialchars($vr['role_name'], ENT_QUOTES) ?>')">
                                                <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-tab">
                                    <i data-lucide="users"></i>
                                    <p>No roles defined yet. <a href="manage_events.php?edit=<?= $event_id ?>">Edit the event</a> to add volunteer roles.</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-tab">
                                <i data-lucide="users"></i>
                                <p>Volunteer setup not complete. <a href="manage_events.php?edit=<?= $event_id ?>">Edit the event</a> to configure volunteers.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <!-- Role Members Modal -->
    <div id="roleMembersModalOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center;">
        <div class="role-modal">
            <div class="role-modal-header">
                <div>
                    <h3 id="roleMembersTitle" style="margin:0;font-size:1.1rem;">Role Volunteers</h3>
                    <p id="roleMembersCount" style="margin:4px 0 0;font-size:0.85rem;opacity:0.85;"></p>
                </div>
                <button onclick="closeRoleModal()" style="background:rgba(255,255,255,0.2);border:none;color:white;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:1.2rem;display:flex;align-items:center;justify-content:center;">×</button>
            </div>
            <div class="role-modal-body">
                <div id="roleMembersContent"></div>
            </div>
        </div>
    </div>

    <!-- Delete Role Modal -->
    <div class="delete-modal-overlay" id="deleteRoleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:16px;padding:32px;max-width:400px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="width:60px;height:60px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#e63946;">
                <i data-lucide="trash-2" style="width:26px;height:26px;"></i>
            </div>
            <h3 style="margin-bottom:8px;">Delete Role?</h3>
            <p id="deleteRoleText" style="color:#6b6b6b;font-size:0.9rem;margin-bottom:24px;"></p>
            <div style="display:flex;gap:12px;">
                <button onclick="closeDeleteRoleModal()" style="flex:1;padding:11px;background:#f3f4f6;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-family:'Poppins',sans-serif;">Cancel</button>
                <a id="deleteRoleBtn" href="#" style="flex:1;padding:11px;background:#e63946;border-radius:10px;font-weight:600;color:white;text-decoration:none;display:flex;align-items:center;justify-content:center;font-family:'Poppins',sans-serif;">Delete</a>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
    lucide.createIcons();

    // Volunteer members data per role
    const volMembersByRole = <?php echo json_encode($vol_members_by_role); ?>;

    function openRoleModal(roleId, roleName) {
        const members = volMembersByRole[roleId] || [];
        document.getElementById('roleMembersTitle').textContent = roleName + ' — Volunteers';
        document.getElementById('roleMembersCount').textContent = members.length + ' volunteer' + (members.length !== 1 ? 's' : '');

        let html = '';
        if (members.length === 0) {
            html = '<div style="text-align:center;padding:32px;color:#6b6b6b;"><p>No volunteers have signed up for this role yet.</p></div>';
        } else {
            html = `<div class="table-wrapper">
                <table class="role-members-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Email</th>
                            <th>Contact No.</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>`;
            members.forEach((m, i) => {
                const date = new Date(m.joined_at).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'});
                const statusColor = m.status === 'confirmed' ? '#065f46' : '#92400e';
                const statusBg    = m.status === 'confirmed' ? '#d1fae5' : '#fef3c7';
                html += `<tr>
                    <td>${i+1}</td>
                    <td><strong>${m.first_name} ${m.last_name}</strong></td>
                    <td>${m.gender ? m.gender.charAt(0).toUpperCase() + m.gender.slice(1) : 'N/A'}</td>
                    <td>${m.email}</td>
                    <td>${m.phone || 'N/A'}</td>
                    <td><span style="background:${statusBg};color:${statusColor};padding:3px 8px;border-radius:10px;font-size:0.75rem;font-weight:700;">${m.status}</span></td>
                    <td>${date}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
        }

        document.getElementById('roleMembersContent').innerHTML = html;
        document.getElementById('roleMembersModalOverlay').style.display = 'flex';
    }

    function closeRoleModal() {
        document.getElementById('roleMembersModalOverlay').style.display = 'none';
    }

    document.getElementById('roleMembersModalOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeRoleModal();
    });

    function confirmDeleteRole(roleId, roleName) {
        document.getElementById('deleteRoleText').textContent =
            'Are you sure you want to delete the "' + roleName + '" role? This cannot be undone.';
        document.getElementById('deleteRoleBtn').href =
            'manage_events.php?edit=<?= $event_id ?>&delete_role=' + roleId;
        const modal = document.getElementById('deleteRoleModal');
        modal.style.display = 'flex';
    }

    function closeDeleteRoleModal() {
        document.getElementById('deleteRoleModal').style.display = 'none';
    }

    document.getElementById('deleteRoleModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteRoleModal();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeDeleteRoleModal();
    });

    function switchTab(e, name) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.view-tab').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        e.target.classList.add('active');
        lucide.createIcons();
    }
    </script>
</body>
</html>