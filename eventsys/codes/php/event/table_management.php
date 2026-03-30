<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole('event_head');
include('../../includes/db.php');

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$message   = '';
$error     = '';

// ── Fetch events that have table management enabled ──
$events_query = $conn->prepare("
    SELECT e.event_id, e.title, e.start_time, e.has_tables, e.gender_separated,
           e.capacity,
           (SELECT COUNT(*) FROM event_table et WHERE et.event_id = e.event_id) AS tables_configured
    FROM event e
    JOIN organizer o ON e.organizer_id = o.organizer_id
    JOIN user u ON o.contact_email = u.email
    WHERE u.user_id = ? AND e.has_tables = 1
    ORDER BY e.start_time DESC
");
$events_query->bind_param("i", $user_id);
$events_query->execute();
$events = $events_query->get_result();

// ── Selected event ──
$selected_event_id = (int)($_GET['event_id'] ?? 0);
$event_info        = null;
$tables            = [];

if ($selected_event_id) {
    $ev = $conn->prepare("
        SELECT e.event_id, e.title, e.capacity, e.has_tables, e.gender_separated, e.start_time
        FROM event e
        JOIN organizer o ON e.organizer_id = o.organizer_id
        JOIN user u ON o.contact_email = u.email
        WHERE e.event_id = ? AND u.user_id = ?
    ");
    $ev->bind_param("ii", $selected_event_id, $user_id);
    $ev->execute();
    $event_info = $ev->get_result()->fetch_assoc();
    $ev->close();

    if ($event_info) {
        // Fetch tables with occupancy
        $tq = $conn->prepare("
            SELECT et.table_id, et.table_number, et.capacity, et.gender_assignment,
                   COUNT(r.registration_id) AS occupants
            FROM event_table et
            LEFT JOIN registration r ON r.event_id = ? AND r.table_number = et.table_number
            WHERE et.event_id = ?
            GROUP BY et.table_id
            ORDER BY et.table_number
        ");
        $tq->bind_param("ii", $selected_event_id, $selected_event_id);
        $tq->execute();
        $tables_result = $tq->get_result();
        while ($t = $tables_result->fetch_assoc()) $tables[] = $t;
        $tq->close();
    }
}

// ── Handle setup: configure tables for an event ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_tables'])) {
    $ev_id          = (int)$_POST['event_id'];
    $num_tables     = (int)$_POST['num_tables'];
    $table_capacity = (int)$_POST['table_capacity'];
    $gender_sep     = isset($_POST['gender_separated']) ? 1 : 0;

    // Verify ownership
    $chk = $conn->prepare("
        SELECT e.event_id, e.capacity FROM event e
        JOIN organizer o ON e.organizer_id = o.organizer_id
        JOIN user u ON o.contact_email = u.email
        WHERE e.event_id = ? AND u.user_id = ?
    ");
    $chk->bind_param("ii", $ev_id, $user_id);
    $chk->execute();
    $chk_row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$chk_row) {
        $error = "Event not found or access denied.";
    } elseif ($num_tables < 1 || $table_capacity < 1) {
        $error = "Please enter valid table count and capacity.";
    } else {
        // Check if any tables already have participants assigned
        $has_assigned = $conn->prepare("
            SELECT COUNT(*) as c FROM registration
            WHERE event_id = ? AND table_number > 0
        ");
        $has_assigned->bind_param("i", $ev_id);
        $has_assigned->execute();
        $assigned_count = $has_assigned->get_result()->fetch_assoc()['c'];
        $has_assigned->close();

        if ($assigned_count > 0) {
            // Check if any table would exceed new capacity
            $overflow = $conn->prepare("
                SELECT COUNT(*) as c FROM registration
                WHERE event_id = ?
                GROUP BY table_number
                HAVING c > ?
            ");
            $overflow->bind_param("ii", $ev_id, $table_capacity);
            $overflow->execute();
            $overflow_result = $overflow->get_result();
            $has_overflow = $overflow_result->num_rows > 0;
            $overflow->close();

            if ($has_overflow) {
                $error = "Cannot reduce capacity — some tables already have more participants than the new capacity. Reassign participants first or increase the capacity.";
            } else {
                // Safe to update — just update capacity on existing tables, keep assignments
                $upd_cap = $conn->prepare("UPDATE event_table SET capacity = ? WHERE event_id = ?");
                $upd_cap->bind_param("ii", $table_capacity, $ev_id);
                $upd_cap->execute();
                $upd_cap->close();

                // Update gender_separated on event
                $upd = $conn->prepare("UPDATE event SET gender_separated = ? WHERE event_id = ?");
                $upd->bind_param("ii", $gender_sep, $ev_id);
                $upd->execute();
                $upd->close();

                $message = "Table capacity updated successfully! Existing assignments preserved.";
                header("Location: table_management.php?event_id=$ev_id&success=1");
                exit();
            }
        } else {
            // No assignments yet — full reconfigure allowed
            $upd = $conn->prepare("UPDATE event SET gender_separated = ? WHERE event_id = ?");
            $upd->bind_param("ii", $gender_sep, $ev_id);
            $upd->execute();
            $upd->close();

            // Delete old table definitions
            $del = $conn->prepare("DELETE FROM event_table WHERE event_id = ?");
            $del->bind_param("i", $ev_id);
            $del->execute();
            $del->close();

            // Insert new tables
            for ($i = 1; $i <= $num_tables; $i++) {
                if ($gender_sep) {
                    $half   = ceil($num_tables / 2);
                    $gender = $i <= $half ? 'male' : 'female';
                } else {
                    $gender = 'mixed';
                }
                $ins = $conn->prepare("INSERT INTO event_table (event_id, table_number, capacity, gender_assignment) VALUES (?,?,?,?)");
                $ins->bind_param("iiis", $ev_id, $i, $table_capacity, $gender);
                $ins->execute();
                $ins->close();
            }
            $message = "Tables configured successfully!";
            header("Location: table_management.php?event_id=$ev_id&success=1");
            exit();
        }
    }
}

// ── Handle manual reassignment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reassign'])) {
    $reg_id       = (int)$_POST['registration_id'];
    $new_table    = (int)$_POST['new_table_number'];
    $ev_id        = (int)$_POST['event_id'];

    // Get participant gender and target table info
    $info = $conn->prepare("
        SELECT u.gender, r.table_number as current_table
        FROM registration r
        JOIN user u ON r.user_id = u.user_id
        WHERE r.registration_id = ? AND r.event_id = ?
    ");
    $info->bind_param("ii", $reg_id, $ev_id);
    $info->execute();
    $participant = $info->get_result()->fetch_assoc();
    $info->close();

    $target = $conn->prepare("
        SELECT et.capacity, et.gender_assignment,
               COUNT(r.registration_id) AS occupants
        FROM event_table et
        LEFT JOIN registration r ON r.event_id = ? AND r.table_number = et.table_number
        WHERE et.event_id = ? AND et.table_number = ?
        GROUP BY et.table_id
    ");
    $target->bind_param("iii", $ev_id, $ev_id, $new_table);
    $target->execute();
    $target_table = $target->get_result()->fetch_assoc();
    $target->close();

    if (!$target_table) {
        $error = "Target table not found.";
    } elseif ($target_table['occupants'] >= $target_table['capacity']) {
        $error = "Table $new_table is full ({$target_table['capacity']}/{$target_table['capacity']} seats). Consider swapping with another participant.";
    } elseif ($target_table['gender_assignment'] !== 'mixed' &&
              $participant['gender'] !== $target_table['gender_assignment']) {
        $error = "Table $new_table is reserved for " . ucfirst($target_table['gender_assignment']) . " participants only.";
    } else {
        $upd = $conn->prepare("UPDATE registration SET table_number = ? WHERE registration_id = ? AND event_id = ?");
        $upd->bind_param("iii", $new_table, $reg_id, $ev_id);
        $upd->execute();
        $upd->close();
        $message = "Participant reassigned to Table $new_table.";
        header("Location: table_management.php?event_id=$ev_id&success=1");
        exit();
    }
}

if (isset($_GET['success'])) $message = "Changes saved successfully!";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Table Management — Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/event_head.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* ── Table grid ── */
        .table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .table-card {
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            border: 2px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.25s ease;
            text-align: center;
            position: relative;
        }

        .table-card:hover {
            border-color: var(--maroon, #800020);
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(128,0,32,0.15);
        }

        .table-card.full { border-color: #ef4444; background: #fff5f5; }
        .table-card.almost { border-color: #f59e0b; background: #fffbf0; }
        .table-card.available { border-color: #10b981; background: #f0fdf4; }

        .table-number {
            font-size: 2rem;
            font-weight: 900;
            color: var(--maroon, #800020);
            line-height: 1;
            margin-bottom: 6px;
        }

        .table-gender-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-male    { background: #dbeafe; color: #1d4ed8; }
        .badge-female  { background: #fce7f3; color: #be185d; }
        .badge-mixed   { background: #f3f4f6; color: #6b7280; }

        .table-occupancy {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a1a1a;
        }

        .table-occupancy span { color: #6b7280; font-weight: 400; font-size: 0.85rem; }

        .table-bar {
            height: 6px;
            background: #f3f4f6;
            border-radius: 3px;
            margin: 8px 0;
            overflow: hidden;
        }

        .table-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .fill-full    { background: #ef4444; }
        .fill-almost  { background: #f59e0b; }
        .fill-ok      { background: #10b981; }

        /* ── Detail modal ── */
        .table-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .table-modal-backdrop.open { display: flex; }

        .table-modal {
            background: white;
            border-radius: 16px;
            max-width: 700px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        }

        .table-modal-header {
            background: linear-gradient(135deg, var(--maroon, #800020), #5a0016);
            color: white;
            padding: 20px 24px;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-modal-body { padding: 24px; }

        .participant-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-radius: 10px;
            background: #f9fafb;
            margin-bottom: 8px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .participant-info { flex: 1; }
        .participant-name { font-weight: 600; color: #1a1a1a; font-size: 0.95rem; }
        .participant-meta { font-size: 0.8rem; color: #6b7280; margin-top: 2px; }

        .reassign-select {
            padding: 6px 10px;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.82rem;
            color: #1a1a1a;
            background: white;
        }

        .reassign-btn {
            padding: 6px 14px;
            background: var(--maroon, #800020);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }

        /* ── Print styles ── */
        @media print {
            .eh-page > *:not(.print-area) { display: none; }
            .print-area { display: block !important; }
            .table-card { break-inside: avoid; }
            .no-print { display: none !important; }
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .table-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        }

        @media (max-width: 480px) {
            .table-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="dashboard-layout event-head-page">
<?php
$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();
?>
<?php include('../components/sidebar.php'); ?>

<main class="main-content">
    <div class="eh-page">

        <header class="banner event-head-banner">
            <div>
                <div class="event-head-badge">
                    <i data-lucide="briefcase" style="width:14px;height:14px;"></i>
                    Event Organizer
                </div>
                <h1>Table Management</h1>
                <p>Set up and manage seating arrangements for your events.</p>
            </div>
            <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
        </header>

        <?php if ($message): ?>
            <div class="eh-alert-msg success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="eh-alert-msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Event selector -->
        <div class="eh-card no-print">
            <h2>Select Event</h2>
            <form method="GET" action="">
                <div class="eh-select-row">
                    <select name="event_id" class="eh-select" onchange="this.form.submit()">
                        <option value="">-- Choose an event with table management --</option>
                        <?php $events->data_seek(0); while ($ev = $events->fetch_assoc()): ?>
                            <option value="<?= $ev['event_id'] ?>" <?= $selected_event_id == $ev['event_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev['title']) ?> —
                                <?= date('M j, Y', strtotime($ev['start_time'])) ?>
                                (<?= $ev['tables_configured'] ?> tables configured)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($event_info): ?>

            <!-- Table setup (only if no participants assigned yet) -->
            <div class="eh-card no-print">
                <div class="card-toolbar" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                    <h2>Configure Tables — <?= htmlspecialchars($event_info['title']) ?></h2>
                    <div style="display:flex;gap:10px;">
                        <button onclick="window.print()" class="eh-btn eh-btn-secondary no-print">
                            <i data-lucide="printer" style="width:15px;height:15px;"></i> Print Layout
                        </button>
                    </div>
                </div>

                <form method="POST" action="" class="table-setup-form">
                    <input type="hidden" name="event_id" value="<?= $event_info['event_id'] ?>">
                    <div class="eh-form-group">
                        <label>Number of Tables</label>
                        <input type="number" name="num_tables" class="eh-select"
                               min="1" max="100"
                               value="<?= count($tables) ?: 4 ?>"
                               placeholder="e.g. 4">
                    </div>
                    <div class="eh-form-group">
                        <label>Seats Per Table</label>
                        <input type="number" name="table_capacity" class="eh-select"
                               min="1" max="100"
                               value="<?= $tables[0]['capacity'] ?? 10 ?>"
                               placeholder="e.g. 10">
                    </div>
                    <div class="eh-form-group table-gender-group">
                        <label>Gender Separation</label>
                        <div class="table-gender-check">
                            <input type="checkbox" name="gender_separated" id="gender_separated"
                                   <?= $event_info['gender_separated'] ? 'checked' : '' ?>>
                            <label for="gender_separated">Separate Male / Female tables</label>
                        </div>
                    </div>
                    <div class="table-setup-action">
                        <button type="submit" name="setup_tables" class="eh-btn eh-btn-primary">
                            <i data-lucide="settings" style="width:15px;height:15px;"></i>
                            Apply Table Setup
                        </button>
                    </div>
                </form>

                <!-- Live capacity calculator -->
                <div class="table-capacity-hint" id="capacityHint">
                    <div class="table-capacity-row">
                        <span>Event capacity:</span>
                        <strong><?= $event_info['capacity'] ?> seats</strong>
                    </div>
                    <div class="table-capacity-row">
                        <span>Table configuration:</span>
                        <strong id="tableTotal">— seats</strong>
                    </div>
                    <div class="table-capacity-row" id="capacityStatus"></div>
                    <p class="table-capacity-tip">
                        <i data-lucide="info" style="width:13px;height:13px;vertical-align:middle;"></i>
                        Set <strong>Number of Tables × Seats Per Table</strong> to equal your event capacity
                        (<?= $event_info['capacity'] ?> seats).
                        For example: <?= ceil($event_info['capacity'] / 10) ?> tables × 10 seats,
                        or <?= ceil($event_info['capacity'] / 8) ?> tables × 8 seats.
                    </p>
                </div>

                <?php if ($event_info['gender_separated']): ?>
                    <p style="font-size:0.82rem;color:#6b7280;margin-top:10px;">
                        <i data-lucide="info" style="width:13px;height:13px;vertical-align:middle;"></i>
                        Gender separation is ON — first half of tables are Male, second half are Female.
                        Participants auto-assigned to their gender's tables, filling each completely before moving to the next.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Table grid visual layout -->
            <?php if (count($tables) > 0): ?>
            <div class="eh-card print-area">
                <h2>Table Overview — <?= htmlspecialchars($event_info['title']) ?></h2>
                <p style="color:#6b7280;font-size:0.88rem;margin-bottom:4px;">
                    Click any table to view participants and reassign seats.
                </p>

                <?php
            // Check for unassigned participants (table_number = 0)
            $unassigned = $conn->prepare("
                SELECT r.registration_id, CONCAT(u.first_name,' ',u.last_name) AS name,
                       u.email, u.gender
                FROM registration r
                JOIN user u ON r.user_id = u.user_id
                WHERE r.event_id = ? AND r.table_number = 0
            ");
            $unassigned->bind_param("i", $selected_event_id);
            $unassigned->execute();
            $unassigned_result = $unassigned->get_result();
            $unassigned_count  = $unassigned_result->num_rows;
            ?>

            <?php if ($unassigned_count > 0): ?>
            <div style="background:#fff3cd;border-radius:12px;padding:16px 20px;margin-bottom:20px;border-left:4px solid #f59e0b;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                    <i data-lucide="alert-triangle" style="width:16px;height:16px;color:#f59e0b;"></i>
                    <strong style="color:#92400e;"><?= $unassigned_count ?> participant(s) not assigned to a table yet</strong>
                </div>
                <?php while ($ua = $unassigned_result->fetch_assoc()): ?>
                <form method="POST" action="" style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
                    <input type="hidden" name="reassign" value="1">
                    <input type="hidden" name="registration_id" value="<?= $ua['registration_id'] ?>">
                    <input type="hidden" name="event_id" value="<?= $selected_event_id ?>">
                    <span style="font-size:0.88rem;font-weight:600;flex:1;min-width:160px;">
                        <?= htmlspecialchars($ua['name']) ?>
                        <span style="background:<?= $ua['gender']==='male'?'#dbeafe':'#fce7f3' ?>;color:<?= $ua['gender']==='male'?'#1d4ed8':'#be185d' ?>;font-size:0.72rem;font-weight:700;padding:2px 8px;border-radius:20px;margin-left:6px;">
                            <?= ucfirst($ua['gender'] ?? '—') ?>
                        </span>
                    </span>
                    <select name="new_table_number" class="eh-select" style="width:auto;min-width:160px;" required>
                        <option value="">-- Assign to table --</option>
                        <?php foreach ($tables as $t):
                            $gender_ok = $event_info['gender_separated']
                                ? ($t['gender_assignment'] === $ua['gender'] || $t['gender_assignment'] === 'mixed')
                                : true;
                            $full = $t['occupants'] >= $t['capacity'];
                        ?>
                        <option value="<?= $t['table_number'] ?>"
                            <?= (!$gender_ok || $full) ? 'disabled' : '' ?>>
                            Table <?= $t['table_number'] ?>
                            (<?= $t['occupants'] ?>/<?= $t['capacity'] ?>)
                            <?= $t['gender_assignment'] !== 'mixed' ? '— '.ucfirst($t['gender_assignment']) : '' ?>
                            <?= $full ? '[FULL]' : '' ?>
                            <?= !$gender_ok ? '[WRONG GENDER]' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="eh-btn eh-btn-primary" style="white-space:nowrap;padding:8px 16px;font-size:0.85rem;">
                        <i data-lucide="user-check" style="width:14px;height:14px;"></i> Assign
                    </button>
                </form>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <div class="table-grid">
                    <?php foreach ($tables as $t):
                        $pct   = $t['capacity'] > 0 ? ($t['occupants'] / $t['capacity']) * 100 : 0;
                        $state = $pct >= 100 ? 'full' : ($pct >= 70 ? 'almost' : 'available');
                        $fill  = $pct >= 100 ? 'fill-full' : ($pct >= 70 ? 'fill-almost' : 'fill-ok');
                    ?>
                    <div class="table-card <?= $state ?>"
                         onclick="openTableModal(<?= $t['table_number'] ?>, <?= $event_info['event_id'] ?>)">
                        <div class="table-number">Table <?= $t['table_number'] ?></div>
                        <div>
                            <span class="table-gender-badge badge-<?= $t['gender_assignment'] ?>">
                                <?= ucfirst($t['gender_assignment']) ?>
                            </span>
                        </div>
                        <div class="table-bar">
                            <div class="table-bar-fill <?= $fill ?>"
                                 style="width:<?= min(100, $pct) ?>%"></div>
                        </div>
                        <div class="table-occupancy">
                            <?= $t['occupants'] ?>/<?= $t['capacity'] ?>
                            <span> seats filled</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <?php if ($selected_event_id): ?>
                <div class="eh-card">
                    <p style="color:#6b7280;text-align:center;padding:20px;">
                        Event not found or you don't have access to it.
                    </p>
                </div>
            <?php else: ?>
                <div class="eh-card">
                    <div style="text-align:center;padding:40px 20px;color:#9ca3af;">
                        <i data-lucide="layout-grid" style="width:48px;height:48px;opacity:0.3;display:block;margin:0 auto 16px;"></i>
                        <p>Select an event above to manage its tables.</p>
                        <p style="font-size:0.82rem;margin-top:8px;">
                            To enable table management for an event, edit the event and turn on "Table Management".
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</main>

<!-- Table detail modal -->
<div class="table-modal-backdrop" id="tableModal">
    <div class="table-modal">
        <div class="table-modal-header">
            <div>
                <h3 style="margin:0;font-size:1.2rem;" id="modalTitle">Table —</h3>
                <p style="margin:0;opacity:0.85;font-size:0.88rem;" id="modalSubtitle"></p>
            </div>
            <button onclick="closeTableModal()"
                    style="background:rgba(255,255,255,0.2);border:none;color:white;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.2rem;display:flex;align-items:center;justify-content:center;">×</button>
        </div>
        <div class="table-modal-body" id="modalBody">
            <p style="color:#9ca3af;text-align:center;">Loading...</p>
        </div>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
lucide.createIcons();

// ── Table capacity live calculator ──
const numTablesInput    = document.querySelector('input[name="num_tables"]');
const tableCapacityInput = document.querySelector('input[name="table_capacity"]');
const eventCapacity     = <?= $event_info['capacity'] ?>;

function updateCapacityHint() {
    const tables   = parseInt(numTablesInput?.value) || 0;
    const seats    = parseInt(tableCapacityInput?.value) || 0;
    const total    = tables * seats;
    const totalEl  = document.getElementById('tableTotal');
    const statusEl = document.getElementById('capacityStatus');

    if (!totalEl) return;
    totalEl.textContent = total + ' seats';

    if (total === 0) {
        statusEl.innerHTML = '';
    } else if (total === eventCapacity) {
        totalEl.style.color = '#059669';
        statusEl.innerHTML = '<span style="color:#059669;font-weight:700;">✓ Matches event capacity perfectly</span>';
    } else if (total < eventCapacity) {
        totalEl.style.color = '#f59e0b';
        statusEl.innerHTML = '<span style="color:#f59e0b;font-weight:700;">⚠ ' + (eventCapacity - total) + ' seats uncovered — increase tables or capacity</span>';
    } else {
        totalEl.style.color = '#ef4444';
        statusEl.innerHTML = '<span style="color:#ef4444;font-weight:700;">✗ Exceeds event capacity by ' + (total - eventCapacity) + ' seats</span>';
    }
}

if (numTablesInput) {
    numTablesInput.addEventListener('input', updateCapacityHint);
    tableCapacityInput.addEventListener('input', updateCapacityHint);
    updateCapacityHint(); // Run on load
}

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.eh-alert-msg').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(-8px)';
        el.style.transition = 'all 0.3s';
        setTimeout(() => el.remove(), 300);
    });
}, 4000);

function openTableModal(tableNum, eventId) {
    document.getElementById('tableModal').classList.add('open');
    document.getElementById('modalTitle').textContent = 'Table ' + tableNum;
    document.getElementById('modalBody').innerHTML = '<p style="color:#9ca3af;text-align:center;padding:20px;">Loading participants...</p>';

    // Fetch participants via AJAX
    fetch(`get_table_participants.php?event_id=${eventId}&table_number=${tableNum}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('modalSubtitle').textContent =
                data.gender + ' · ' + data.occupants + '/' + data.capacity + ' seats';
            renderParticipants(data, eventId, tableNum);
        })
        .catch(() => {
            document.getElementById('modalBody').innerHTML =
                '<p style="color:#ef4444;text-align:center;">Failed to load participants.</p>';
        });
}

function renderParticipants(data, eventId, tableNum) {
    const body = document.getElementById('modalBody');
    if (!data.participants || data.participants.length === 0) {
        body.innerHTML = '<p style="color:#9ca3af;text-align:center;padding:20px;">No participants assigned yet.</p>';
        return;
    }

    let html = '<div style="margin-bottom:16px;">';
    data.participants.forEach(p => {
        // Build available tables for reassign dropdown
        let options = '<option value="">Move to...</option>';
        data.all_tables.forEach(t => {
            if (t.table_number != tableNum) {
                const full = t.occupants >= t.capacity;
                options += `<option value="${t.table_number}" ${full ? 'disabled' : ''}>
                    Table ${t.table_number} (${t.occupants}/${t.capacity}) ${t.gender_assignment !== 'mixed' ? '— ' + t.gender_assignment : ''}
                    ${full ? '[FULL]' : ''}
                </option>`;
            }
        });

        html += `
        <div class="participant-row">
            <div class="participant-info">
                <div class="participant-name">${p.name}</div>
                <div class="participant-meta">${p.email} · ${p.gender}</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
                <select class="reassign-select" id="sel-${p.registration_id}">${options}</select>
                <button class="reassign-btn" onclick="reassign(${p.registration_id}, ${eventId})">Move</button>
            </div>
        </div>`;
    });
    html += '</div>';
    body.innerHTML = html;
}

function reassign(regId, eventId) {
    const sel      = document.getElementById('sel-' + regId);
    const newTable = sel.value;
    if (!newTable) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'table_management.php?event_id=' + eventId;

    [['reassign','1'],['registration_id',regId],['new_table_number',newTable],['event_id',eventId]]
        .forEach(([n,v]) => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = n; inp.value = v;
            form.appendChild(inp);
        });
    document.body.appendChild(form);
    form.submit();
}

function closeTableModal() {
    document.getElementById('tableModal').classList.remove('open');
}

document.getElementById('tableModal').addEventListener('click', function(e) {
    if (e.target === this) closeTableModal();
});
</script>
</body>
</html>
<?php $conn->close(); ?>