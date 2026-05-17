<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole(['event_head', 'admin']);
include('../../includes/db.php');
require_once('../../includes/permission_functions.php');

$user_id = $_SESSION['user_id'];
$message = ""; $error = "";

// Handle info messages from redirected old pages
if (isset($_GET['info'])) {
    if ($_GET['info'] === 'volunteer_integrated') {
        $message = "Volunteer Management is now part of Event Management. Enable it per event using the options below.";
    } elseif ($_GET['info'] === 'table_integrated') {
        $message = "Table Management is now part of Event Management. Configure tables per event using the options below.";
    }
}

$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id); $role_stmt->execute();
$role_stmt->bind_result($role); $role_stmt->fetch(); $role_stmt->close();
if (empty($role)) $role = 'user';


// ── CREATE EVENT ──
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_event'])) {
    if (!hasPermission($conn, $user_id, 'event.create')) {
        $error = "You don't have permission to create events.";
    } else {
        $title            = $_POST['title'];
        $description      = $_POST['description'];
        $start_time       = $_POST['start_time'];
        $end_time         = $_POST['end_time'];
        $venue_name       = $_POST['venue_name'];
        $venue_address    = $_POST['venue_address'];
        $venue_city       = $_POST['venue_city'];
        $capacity         = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
        $category_id      = $_POST['category_id'];
        $has_tables       = isset($_POST['has_tables']) ? 1 : 0;
        $gender_separated = isset($_POST['gender_separated']) ? 1 : 0;
        $num_tables       = $has_tables && !empty($_POST['num_tables']) ? (int)$_POST['num_tables'] : null;
        $seats_per_table  = $has_tables && !empty($_POST['seats_per_table']) ? (int)$_POST['seats_per_table'] : null;
        $requires_registration = isset($_POST['requires_registration']) ? 1 : 0;
        $show_on_landing       = isset($_POST['show_on_landing']) ? 1 : 0;
        $has_volunteer         = isset($_POST['has_volunteer']) ? 1 : 0;

        $venue_stmt = $conn->prepare("INSERT INTO venue (name, address, city) VALUES (?, ?, ?)");
        $venue_stmt->bind_param("sss", $venue_name, $venue_address, $venue_city);
        $venue_stmt->execute();
        $venue_id = $venue_stmt->insert_id; $venue_stmt->close();

        $user_stmt = $conn->prepare("SELECT first_name, middle_name, last_name, email, phone FROM user WHERE user_id = ?");
        $user_stmt->bind_param("i", $user_id); $user_stmt->execute();
        $user_stmt->bind_result($first_name, $middle_name, $last_name, $email, $phone);
        $user_stmt->fetch(); $user_stmt->close();
        $full_name = trim("$first_name $middle_name $last_name");

        $org_stmt = $conn->prepare("SELECT organizer_id FROM organizer WHERE contact_email = ?");
        $org_stmt->bind_param("s", $email); $org_stmt->execute();
        $org_stmt->bind_result($organizer_id); $org_stmt->fetch(); $org_stmt->close();

        if (!$organizer_id) {
            $insert_org = $conn->prepare("INSERT INTO organizer (name, contact_email, phone) VALUES (?, ?, ?)");
            $insert_org->bind_param("sss", $full_name, $email, $phone);
            $insert_org->execute();
            $organizer_id = $insert_org->insert_id; $insert_org->close();
        }

        $stmt = $conn->prepare("INSERT INTO event (title, description, start_time, end_time, venue_id, organizer_id, capacity, category_id, has_tables, gender_separated, num_tables, seats_per_table, requires_registration, show_on_landing, has_volunteer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiiiiiiiiiii", $title, $description, $start_time, $end_time, $venue_id, $organizer_id, $capacity, $category_id, $has_tables, $gender_separated, $num_tables, $seats_per_table, $requires_registration, $show_on_landing, $has_volunteer);
        if ($stmt->execute()) {
            $new_event_id = $stmt->insert_id;
            
            // AUTO-GRANT FULL ACCESS TO EVENT CREATOR
            autoGrantCreatorEventAccess($conn, $new_event_id, $organizer_id);
            
            $message = "Event created successfully!";

            // If has_volunteer, create a linked volunteer_event record
            if ($has_volunteer) {
                $token = bin2hex(random_bytes(16));
                $vs = $conn->prepare("INSERT INTO volunteer_event (event_id, title, description, event_date, location, qr_token, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $vs->bind_param("isssssi", $new_event_id, $title, $description, $start_time, $venue_name, $token, $user_id);
                $vs->execute();
                $volunteer_event_id = $vs->insert_id;
                $vs->close();

                // Save volunteer roles created while adding the event
                $pending_roles = json_decode($_POST['pending_roles'] ?? '[]', true);
                if (!empty($pending_roles) && is_array($pending_roles)) {
                    foreach ($pending_roles as $pr) {
                        $role_name = trim($pr['name'] ?? '');
                        $lead_id   = !empty($pr['lead_id']) ? (int)$pr['lead_id'] : null;
                        if ($role_name) {
                            $rs = $conn->prepare("INSERT INTO volunteer_role_type (volunteer_event_id, role_name, team_lead_id) VALUES (?, ?, ?)");
                            $rs->bind_param("isi", $volunteer_event_id, $role_name, $lead_id);
                            $rs->execute(); $rs->close();
                        }
                    }
                }
            }
        } else {
            $error = "Failed to create event.";
        }
        $stmt->close();
    }
}

// ── UPDATE EVENT ──
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_event'])) {
    $event_id = (int)$_POST['event_id'];
    if (!canAccessEvent($conn, $user_id, $event_id, 'edit')) {
        $error = "You don't have permission to edit this event.";
    } else {
        $title            = $_POST['title'];
        $description      = $_POST['description'];
        $start_time       = $_POST['start_time'];
        $end_time         = $_POST['end_time'];
        $capacity         = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
        $category_id      = $_POST['category_id'];
        $has_tables       = isset($_POST['has_tables']) ? 1 : 0;
        $gender_separated = isset($_POST['gender_separated']) ? 1 : 0;
        $num_tables       = $has_tables && !empty($_POST['num_tables']) ? (int)$_POST['num_tables'] : null;
        $seats_per_table  = $has_tables && !empty($_POST['seats_per_table']) ? (int)$_POST['seats_per_table'] : null;
        $requires_registration = isset($_POST['requires_registration']) ? 1 : 0;
        $show_on_landing       = isset($_POST['show_on_landing']) ? 1 : 0;
        $has_volunteer         = isset($_POST['has_volunteer']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE event SET title=?, description=?, start_time=?, end_time=?, capacity=?, category_id=?, has_tables=?, gender_separated=?, num_tables=?, seats_per_table=?, requires_registration=?, show_on_landing=?, has_volunteer=? WHERE event_id=?");
        $stmt->bind_param("ssssiiiiiiiiii", $title, $description, $start_time, $end_time, $capacity, $category_id, $has_tables, $gender_separated, $num_tables, $seats_per_table, $requires_registration, $show_on_landing, $has_volunteer, $event_id);
        if ($stmt->execute()) {
            $message = "Event updated successfully!";

            // Sync volunteer_event link
            $ve_check = $conn->prepare("SELECT volunteer_event_id FROM volunteer_event WHERE event_id = ?");
            $ve_check->bind_param("i", $event_id); $ve_check->execute();
            $ve_check->bind_result($ve_id); $ve_check->fetch(); $ve_check->close();

            $target_vol_event_id = $ve_id;
            $venue_n = '';
            if ($has_volunteer) {
                $venue_n = $conn->query("SELECT name FROM venue WHERE venue_id = (SELECT venue_id FROM event WHERE event_id = $event_id LIMIT 1)")->fetch_assoc()['name'] ?? '';
            }

            if ($has_volunteer && !$target_vol_event_id) {
                // Create linked volunteer_event if not exists
                $token = bin2hex(random_bytes(16));
                $vs = $conn->prepare("SELECT title, start_time FROM event WHERE event_id = ?");
                $vs->bind_param("i", $event_id); $vs->execute();
                $vs->bind_result($ev_title, $ev_start); $vs->fetch(); $vs->close();
                $ins = $conn->prepare("INSERT INTO volunteer_event (event_id, title, description, event_date, location, qr_token, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("isssssi", $event_id, $ev_title, $description, $ev_start, $venue_n, $token, $user_id);
                $ins->execute();
                $target_vol_event_id = $ins->insert_id;
                $ins->close();
            }

            if ($has_volunteer && $target_vol_event_id) {
                $update_vol = $conn->prepare("UPDATE volunteer_event SET title=?, description=?, event_date=?, location=? WHERE volunteer_event_id = ?");
                $update_vol->bind_param("ssssi", $title, $description, $start_time, $venue_n, $target_vol_event_id);
                $update_vol->execute();
                $update_vol->close();

                $pending_roles = json_decode($_POST['pending_roles'] ?? '[]', true);
                if (!empty($pending_roles) && is_array($pending_roles)) {
                    foreach ($pending_roles as $pr) {
                        $role_name = trim($pr['name'] ?? '');
                        $lead_id   = !empty($pr['lead_id']) ? (int)$pr['lead_id'] : null;
                        if ($role_name) {
                            $rs = $conn->prepare("INSERT INTO volunteer_role_type (volunteer_event_id, role_name, team_lead_id) VALUES (?, ?, ?)");
                            $rs->bind_param("isi", $target_vol_event_id, $role_name, $lead_id);
                            $rs->execute(); $rs->close();
                        }
                    }
                }
            }

            // Handle deleted roles
            $deleted_roles = json_decode($_POST['deleted_roles'] ?? '[]', true);
            if (!empty($deleted_roles) && is_array($deleted_roles)) {
                foreach ($deleted_roles as $del_id) {
                    $del_id = (int)$del_id;
                    if ($del_id > 0) {
                        $ds = $conn->prepare("DELETE FROM volunteer_role_type WHERE role_type_id = ?");
                        $ds->bind_param("i", $del_id); $ds->execute(); $ds->close();
                    }
                }
            }
        } else {
            $error = "Failed to update event.";
        }
        $stmt->close();
    }
}

// ── DELETE EVENT ──
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if (!canAccessEvent($conn, $user_id, $delete_id, 'delete')) {
        $error = "You don't have permission to delete this event.";
    } else {
        $stmt = $conn->prepare("DELETE FROM event WHERE event_id = ?");
        $stmt->bind_param("i", $delete_id);
        $message = $stmt->execute() ? "Event deleted successfully!" : "Failed to delete event.";
        $stmt->close();
    }
}

$edit_event = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    if (!canAccessEvent($conn, $user_id, $edit_id, 'edit')) {
        $error = "You don't have permission to edit this event.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM event WHERE event_id = ?");
        $stmt->bind_param("i", $edit_id); $stmt->execute();
        $edit_event = $stmt->get_result()->fetch_assoc(); $stmt->close();

        // Get volunteer roles for this event
        $vol_roles = [];
        $vr = $conn->prepare("SELECT vrt.role_type_id, vrt.role_name, CONCAT(u.first_name,' ',u.last_name) as lead_name, (SELECT COUNT(*) FROM volunteer_member vm WHERE vm.role_type_id = vrt.role_type_id) AS member_count FROM volunteer_role_type vrt LEFT JOIN user u ON vrt.team_lead_id = u.user_id WHERE vrt.volunteer_event_id = (SELECT volunteer_event_id FROM volunteer_event WHERE event_id = ? LIMIT 1)");
        $vr->bind_param("i", $edit_id); $vr->execute();
        $vol_roles = $vr->get_result()->fetch_all(MYSQLI_ASSOC); $vr->close();

        // Get volunteer event id
        $ve_row = $conn->query("SELECT volunteer_event_id FROM volunteer_event WHERE event_id = $edit_id LIMIT 1")->fetch_assoc();
        $volunteer_event_id = $ve_row['volunteer_event_id'] ?? null;
    }
}

$category_result = $conn->query("SELECT category_id, category_name FROM event_category");
$all_users = $conn->query("SELECT user_id, CONCAT(first_name,' ',last_name) as full_name FROM user ORDER BY first_name");

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 4;

$allowed_status = ['all', 'upcoming', 'past'];
if (!in_array($status, $allowed_status, true)) {
    $status = 'all';
}

$baseQuery = [];
if ($search !== '') {
    $baseQuery['search'] = $search;
}
if ($status !== 'all') {
    $baseQuery['status'] = $status;
}

$search_sql = '';
if ($search !== '') {
    $search_sql = " AND e.title LIKE ?";
}
$status_sql = '';
if ($status === 'upcoming') {
    $status_sql = " AND e.start_time >= NOW()";
} elseif ($status === 'past') {
    $status_sql = " AND e.start_time < NOW()";
}

$count_sql = "SELECT COUNT(*) AS total FROM event e JOIN venue v ON e.venue_id = v.venue_id JOIN organizer o ON e.organizer_id = o.organizer_id JOIN user u ON o.contact_email = u.email WHERE u.user_id = ?{$search_sql}{$status_sql}";
$count_stmt = $conn->prepare($count_sql);
if ($search !== '') {
    $like_search = "%{$search}%";
    $count_stmt->bind_param("is", $user_id, $like_search);
} else {
    $count_stmt->bind_param("i", $user_id);
}
$count_stmt->execute();
$total_events = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$total_pages = max(1, (int) ceil($total_events / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$events_sql = "SELECT e.event_id, e.title, e.start_time, e.end_time, e.requires_registration, e.show_on_landing, e.has_volunteer, e.has_tables, v.name AS venue FROM event e JOIN venue v ON e.venue_id = v.venue_id JOIN organizer o ON e.organizer_id = o.organizer_id JOIN user u ON o.contact_email = u.email WHERE u.user_id = ?{$search_sql}{$status_sql} ORDER BY e.start_time DESC LIMIT ? OFFSET ?";
$events_stmt = $conn->prepare($events_sql);
if ($search !== '') {
    $events_stmt->bind_param("isii", $user_id, $like_search, $per_page, $offset);
} else {
    $events_stmt->bind_param("iii", $user_id, $per_page, $offset);
}
$events_stmt->execute();
$events = $events_stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/event_head.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .option-group {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 12px;
        }
        .option-group-title {
            font-size: 0.82rem;
            font-weight: 700;
            color: #800020;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .option-toggle {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
        }
        .option-toggle:last-child { border-bottom: none; }
        .option-toggle input[type="checkbox"] {
            width: 18px; height: 18px;
            margin-top: 2px;
            accent-color: #800020;
            flex-shrink: 0;
            cursor: pointer;
        }
        .option-toggle-text { flex: 1; }
        .option-toggle-label { font-weight: 600; font-size: 0.9rem; color: #1a1a1a; display: block; }
        .option-toggle-desc  { font-size: 0.82rem; color: #6b6b6b; margin-top: 2px; }
        .sub-fields {
            margin-top: 12px;
            padding: 12px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            display: none;
        }
        .sub-fields.show { display: block; }
        .sub-fields .form-row { margin-bottom: 0; }
        .sub-fields input { margin-bottom: 8px; }
        .event-badges { display: flex; gap: 6px; flex-wrap: wrap; margin: 6px 0; }
        .event-badge {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .badge-reg    { background: #dbeafe; color: #1e40af; }
        .badge-noreg  { background: #f3f4f6; color: #6b6b6b; }
        .badge-landing{ background: #d1fae5; color: #065f46; }
        .badge-hidden { background: #fef3c7; color: #92400e; }
        .badge-vol    { background: #ede9fe; color: #5b21b6; }
        .badge-table  { background: #fee2e2; color: #991b1b; }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 18px;
        }
        .quick-action-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 20px;
            border-radius: 18px;
            background: #ffffff;
            border: 1px solid #eff1f6;
            color: #111827;
            text-align: center;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            min-height: 135px;
            text-decoration: none;
        }
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.12);
            border-color: #d8b4fe;
        }
        .quick-action-card i {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: #f5f3ff;
            color: #7c3aed;
        }
        .quick-action-card span {
            display: block;
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
        }
        .event-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .event-search-form {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            align-items: center;
            width: 100%;
            max-width: 520px;
        }
        .event-search-form input[type="text"] {
            flex: 1;
            min-width: 0;
            border-radius: 12px;
            border: 1px solid #d1d5db;
            padding: 10px 14px;
            outline: none;
            font-size: 0.95rem;
            color: #111827;
        }
        .event-search-form button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            white-space: nowrap;
        }
        .event-status-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .filter-pill {
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            color: #334155;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .filter-pill.active {
            background: #7c3aed;
            color: white;
            border-color: #7c3aed;
        }
        .event-summary {
            margin-bottom: 16px;
            color: #475569;
            font-size: 0.95rem;
        }
        .event-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(330px, 1fr));
            gap: 16px;
        }
        .event-card {
            min-height: 320px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .pagination {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 18px;
        }
        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border-radius: 999px;
            text-decoration: none;
            border: 1px solid #e5e7eb;
            color: #475569;
            font-weight: 600;
            min-width: 44px;
            text-align: center;
        }
        .pagination a:hover {
            background: #f3f4f6;
        }
        .pagination .active {
            background: #7c3aed;
            color: white;
            border-color: #7c3aed;
        }
        .pagination .page-navigation {
            min-width: auto;
        }
        .empty-state {
            padding: 28px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            color: #475569;
            text-align: center;
            font-size: 0.95rem;
        }
        .btn-primary {
            min-width: 160px;
            padding: 12px 22px;
            background: linear-gradient(135deg, #7c3aed, #be185d);
            border: none;
            border-radius: 999px;
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
        }
        .btn-secondary {
            min-width: 140px;
            padding: 12px 22px;
            background: #f3f4f6;
            border: none;
            border-radius: 999px;
            color: #111827;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-sm {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            min-width: auto;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .btn-add {
            min-width: 140px;
            padding: 10px 18px;
            font-size: 0.92rem;
            border-radius: 999px;
        }
        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            min-width: 36px;
            border-radius: 50%;
            gap: 0;
        }
        .btn-icon i {
            width: 16px;
            height: 16px;
        }
        .btn-delete {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }
        .vol-role-item .btn-delete.btn-icon {
            background: #800020;
            border-color: #800020;
            color: white;
        }
        .vol-role-item .btn-delete.btn-icon:hover {
            background: #5b021c;
        }
        .vol-role-item .btn-delete.btn-icon i {
            color: white;
        }
        .form-actions .btn-secondary {
            background: #fafafa;
            color: #800020;
            border: 1px solid #e5e7eb;
            min-width: 140px;
            padding: 12px 22px;
            border-radius: 999px;
        }
        .form-actions .btn-secondary:hover {
            background: #f3f4f6;
        }
        .btn-delete:hover {
            background: #fee2e2;
        }
        .btn-primary:hover { opacity: 0.95; }
        .btn-secondary:hover { background: #e5e7eb; }
        .vol-roles-section {
            margin-top: 20px;
            padding: 16px;
            background: #fdf4ff;
            border: 1px solid #e9d5ff;
            border-radius: 10px;
        }
        .vol-roles-title {
            font-weight: 700;
            font-size: 0.9rem;
            color: #5b21b6;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .vol-role-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            margin-bottom: 6px;
            border: 1px solid #e9d5ff;
        }
        .vol-role-item .role-name { font-weight: 600; font-size: 0.88rem; }
        .vol-role-item .role-lead { font-size: 0.8rem; color: #6b6b6b; }
        .add-role-form { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .add-role-form input,
        .add-role-form select { flex: 1; min-width: 120px; }
        .add-role-form button { white-space: nowrap; }
        .view-link {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; background: #ede9fe; color: #5b21b6;
            border-radius: 8px; font-weight: 600; font-size: 0.85rem;
            text-decoration: none; transition: all 0.2s;
        }
        .view-link:hover { background: #5b21b6; color: white; }

        /* Delete confirmation modal */
        .delete-modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
            z-index: 2000; align-items: center; justify-content: center;
        }
        .delete-modal-overlay.active { display: flex; }
        .delete-modal {
            background: white; border-radius: 16px; padding: 32px;
            max-width: 420px; width: 90%; text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }
        .delete-modal-icon {
            width: 64px; height: 64px; background: #fee2e2;
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; margin: 0 auto 16px;
            color: #e63946;
        }
        .delete-modal h3 { font-size: 1.2rem; margin-bottom: 8px; color: #1a1a1a; }
        .delete-modal p { color: #6b6b6b; font-size: 0.9rem; margin-bottom: 24px; }
        .delete-modal-actions { display: flex; gap: 12px; justify-content: center; }
        .delete-modal-actions .btn-cancel {
            flex: 1; padding: 11px 20px; background: #f3f4f6;
            color: #800000; border: none; border-radius: 10px; font-weight: 600;
            cursor: pointer; font-family: 'Poppins', sans-serif;
            font-size: 0.9rem; transition: background 0.2s;
        }
        .delete-modal-actions .btn-cancel:hover { background: #e5e7eb; }
        .delete-modal-actions .btn-confirm-delete {
            flex: 1; padding: 11px 20px; background: #e63946;
            border: none; border-radius: 10px; font-weight: 600;
            color: white; cursor: pointer; font-family: 'Poppins', sans-serif;
            font-size: 0.9rem; transition: background 0.2s;
        }
        .delete-modal-actions .btn-confirm-delete:hover { background: #c0392b; }
    </style>
</head>
<body class="dashboard-layout event-head-page">
    <?php include('../components/sidebar.php'); ?>

    <main class="main-content">
        <header class="banner event-head-banner">
            <div>
                <div class="event-head-badge">
                    <i data-lucide="briefcase" style="width:14px;height:14px;"></i>
                    Event Organizer
                </div>
                <h1>Event Management</h1>
                <p>Create and manage your events</p>
            </div>
            <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
        </header>

        <div class="main-content-inner">
            <div class="event-head-hub">

                <?php if (!empty($message)): ?>
                    <div class="eh-alert-msg success"><i data-lucide="check-circle"></i> <?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="eh-alert-msg error"><i data-lucide="alert-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <hr class="section-divider">

                <!-- ── QUICK ACTIONS ── -->
                <div class="hub-section">
                    <h2 class="section-title-with-icon">
                        <i data-lucide="zap"></i> Quick Actions
                    </h2>
                    <div class="quick-actions">
                        <a href="../qr/scan_qr.php" class="quick-action-card">
                            <i data-lucide="qr-code"></i>
                            <span>Scan QR Code</span>
                        </a>
                        <a href="view_attendance.php" class="quick-action-card">
                            <i data-lucide="clipboard-check"></i>
                            <span>View Attendance</span>
                        </a>
                        <a href="announcement.php" class="quick-action-card">
                            <i data-lucide="megaphone"></i>
                            <span>Announcements</span>
                        </a>
                        <a href="reports.php" class="quick-action-card">
                            <i data-lucide="bar-chart-2"></i>
                            <span>Reports</span>
                        </a>
                        <a href="participant_engagement.php" class="quick-action-card">
                            <i data-lucide="activity"></i>
                            <span>Engagement Analytics</span>
                        </a>
                        <a href="inactive_tracking.php" class="quick-action-card">
                            <i data-lucide="user-x"></i>
                            <span>Inactive Members</span>
                        </a>
                    </div>
                </div>

                <hr class="section-divider">

                <!-- ── CREATE / EDIT EVENT ── -->
                <?php if (hasPermission($conn, $user_id, 'event.create') || $edit_event): ?>
                <div class="hub-section">
                    <h2 class="section-title-with-icon">
                        <i data-lucide="settings"></i>
                        <?= $edit_event ? "Edit Event" : "Create New Event" ?>
                    </h2>

                    <form method="POST" class="event-form">
                        <input type="hidden" name="event_id" value="<?= $edit_event['event_id'] ?? '' ?>">

                        <!-- Basic Info -->
                        <div class="form-group">
                            <label>Event Title *</label>
                            <input type="text" name="title" placeholder="Event Title"
                                   value="<?= htmlspecialchars($edit_event['title'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Description *</label>
                            <textarea name="description" placeholder="Event Description" required><?= htmlspecialchars($edit_event['description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Start Date & Time *</label>
                                <input type="datetime-local" name="start_time"
                                       value="<?= $edit_event ? date('Y-m-d\TH:i', strtotime($edit_event['start_time'])) : '' ?>" required>
                            </div>
                            <div class="form-group">
                                <label>End Date & Time *</label>
                                <input type="datetime-local" name="end_time"
                                       value="<?= $edit_event ? date('Y-m-d\TH:i', strtotime($edit_event['end_time'])) : '' ?>" required>
                            </div>
                        </div>

                        <!-- Venue -->
                        <?php if (!$edit_event): ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Venue Name *</label>
                                <input type="text" name="venue_name" placeholder="Venue Name" required>
                            </div>
                            <div class="form-group">
                                <label>Venue Address</label>
                                <input type="text" name="venue_address" placeholder="Venue Address">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Venue City</label>
                            <input type="text" name="venue_city" placeholder="Venue City">
                        </div>
                        <?php else: ?>
                        <div class="venue-locked-notice">
                            <div class="notice-header">
                                <i data-lucide="map-pin-off"></i>
                                <span>Venue cannot be edited for existing events</span>
                            </div>
                            <div class="current-venue-display">
                                <strong>Current Venue:</strong>
                                <?php
                                $venue_stmt = $conn->prepare("SELECT v.name, v.address, v.city FROM venue v WHERE v.venue_id = ?");
                                $venue_stmt->bind_param("i", $edit_event['venue_id']); $venue_stmt->execute();
                                $venue = $venue_stmt->get_result()->fetch_assoc(); $venue_stmt->close();
                                ?>
                                <span class="venue-details">
                                    <?= htmlspecialchars($venue['name']) ?>
                                    <?php if ($venue['address']): ?> - <?= htmlspecialchars($venue['address']) ?><?php endif; ?>
                                    <?php if ($venue['city']): ?>, <?= htmlspecialchars($venue['city']) ?><?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Category -->
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category_id" required>
                                <option value="">-- Select Category --</option>
                                <?php $category_result->data_seek(0); while ($cat = $category_result->fetch_assoc()): ?>
                                    <option value="<?= $cat['category_id'] ?>"
                                        <?= (isset($edit_event['category_id']) && $edit_event['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- ── EVENT OPTIONS ── -->
                        <div class="option-group">
                            <div class="option-group-title">
                                <i data-lucide="sliders" style="width:14px;height:14px;"></i>
                                Event Options
                            </div>

                            <!-- Requires Registration -->
                            <label class="option-toggle">
                                <input type="checkbox" name="requires_registration" value="1"
                                       id="chk_reg"
                                       <?= (!$edit_event || $edit_event['requires_registration']) ? 'checked' : '' ?>
                                       onchange="toggleSubFields('reg_fields', this.checked)">
                                <div class="option-toggle-text">
                                    <span class="option-toggle-label">Requires Registration</span>
                                    <span class="option-toggle-desc">Users need to register to join. If unchecked, event is announcement only — no Register Now button shown.</span>
                                </div>
                            </label>
                            <!-- Registration sub-fields: capacity (hidden when table assignment is enabled) -->
                            <div class="sub-fields <?= (!$edit_event || ($edit_event['requires_registration'] && !$edit_event['has_tables'])) ? 'show' : '' ?>" id="reg_fields">
                                <label style="font-size:0.85rem;font-weight:600;color:#555;margin-bottom:6px;display:block;">Available Seats <span style="font-weight:400;color:#999;">(leave blank for no limit)</span></label>
                                <input type="number" name="capacity" placeholder="e.g. 100 — or leave blank for unlimited"
                                       value="<?= htmlspecialchars($edit_event['capacity'] ?? '') ?>">
                            </div>

                            <!-- Show on Landing Page -->
                            <label class="option-toggle">
                                <input type="checkbox" name="show_on_landing" value="1"
                                       <?= (!$edit_event || $edit_event['show_on_landing']) ? 'checked' : '' ?>>
                                <div class="option-toggle-text">
                                    <span class="option-toggle-label">Show on Landing Page</span>
                                    <span class="option-toggle-desc">Display this event on the public landing page. Uncheck to keep it visible only on the Events page.</span>
                                </div>
                            </label>

                            <!-- Table Assignment -->
                            <label class="option-toggle">
                                <input type="checkbox" name="has_tables" value="1"
                                       id="chk_tables"
                                       <?= ($edit_event && $edit_event['has_tables']) ? 'checked' : '' ?>
                                       onchange="toggleSubFields('table_fields', this.checked)">
                                <div class="option-toggle-text">
                                    <span class="option-toggle-label">Enable Table Assignment</span>
                                    <span class="option-toggle-desc">Participants will be auto-assigned to tables when they register.</span>
                                </div>
                            </label>
                            <!-- Table sub-fields -->
                            <div class="sub-fields <?= ($edit_event && $edit_event['has_tables']) ? 'show' : '' ?>" id="table_fields">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label style="font-size:0.85rem;font-weight:600;color:#555;">Number of Tables *</label>
                                        <input type="number" name="num_tables" placeholder="e.g. 10"
                                               value="<?= htmlspecialchars($edit_event['num_tables'] ?? '') ?>"
                                               min="1">
                                    </div>
                                    <div class="form-group">
                                        <label style="font-size:0.85rem;font-weight:600;color:#555;">Seats per Table <span style="font-weight:400;color:#999;">(optional)</span></label>
                                        <input type="number" name="seats_per_table" placeholder="Leave blank for no limit"
                                               value="<?= htmlspecialchars($edit_event['seats_per_table'] ?? '') ?>"
                                               min="1">
                                    </div>
                                </div>
                                <label class="option-toggle" style="margin-top:8px;">
                                    <input type="checkbox" name="gender_separated" value="1"
                                           <?= ($edit_event && $edit_event['gender_separated']) ? 'checked' : '' ?>>
                                    <div class="option-toggle-text">
                                        <span class="option-toggle-label">Separate by Gender</span>
                                        <span class="option-toggle-desc">Males and females assigned to separate tables.</span>
                                    </div>
                                </label>
                            </div>

                            <!-- Volunteer Management -->
                            <label class="option-toggle">
                                <input type="checkbox" name="has_volunteer" value="1"
                                       id="chk_vol"
                                       <?= ($edit_event && $edit_event['has_volunteer']) ? 'checked' : '' ?>
                                       onchange="toggleSubFields('vol_inline_fields', this.checked)">
                                <div class="option-toggle-text">
                                    <span class="option-toggle-label">Enable Volunteer Management</span>
                                    <span class="option-toggle-desc">Allow volunteers to sign up for this event via QR code. Add roles below.</span>
                                </div>
                            </label>

                            <!-- Inline volunteer roles (shown immediately when checkbox checked) -->
                            <div class="sub-fields <?= ($edit_event && $edit_event['has_volunteer']) ? 'show' : '' ?>" id="vol_inline_fields">
                                <div class="vol-roles-inline">
                                    <div class="vol-roles-title" style="margin-bottom:10px;">
                                        <i data-lucide="users" style="width:15px;height:15px;"></i>
                                        Volunteer Roles
                                    </div>

                                    <!-- Roles list (dynamic) -->
                                    <div id="vol_roles_list">
                                        <!-- Populated by JS for new events, PHP for edit -->
                                        <?php if ($edit_event && !empty($vol_roles)): ?>
                                            <?php foreach ($vol_roles as $vr): ?>
                                            <div class="vol-role-item" data-id="<?= $vr['role_type_id'] ?>">
                                                <div>
                                                    <span class="role-name"><?= htmlspecialchars($vr['role_name']) ?></span>
                                                    <?php if ($vr['lead_name']): ?>
                                                        <span class="role-lead"> — Lead: <?= htmlspecialchars($vr['lead_name']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <button type="button" class="btn-delete btn-sm btn-icon"
                                                    onclick="confirmDeleteVolunteerRole(this, <?= $vr['role_type_id'] ?>, '<?= htmlspecialchars($vr['role_name'], ENT_QUOTES) ?>', <?= (int)$vr['member_count'] ?>)">
                                                    <i data-lucide="trash-2"></i>
                                                </button>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Add new role inline -->
                                    <div class="add-role-form" style="margin-top:10px;">
                                        <input type="text" id="new_role_name" placeholder="Role name (e.g. Ushering)">
                                        <select id="new_role_lead">
                                            <option value="">-- Team Lead (optional) --</option>
                                            <?php
                                            if (isset($all_users)) $all_users->data_seek(0);
                                            while ($u = $all_users->fetch_assoc()): ?>
                                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <button type="button" class="btn-primary btn-add" onclick="addRoleInline()">
                                            <i data-lucide="plus" style="width:13px;height:13px;"></i> Add
                                        </button>
                                    </div>

                                    <!-- Hidden JSON field storing pending roles for new event -->
                                    <input type="hidden" name="pending_roles" id="pending_roles" value="[]">
                                    <!-- Hidden field for deleted existing roles -->
                                    <input type="hidden" name="deleted_roles" id="deleted_roles" value="[]">
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <?php if ($edit_event): ?>
                                <button type="submit" name="update_event" class="btn-primary">
                                    <i data-lucide="save"></i> Save Changes
                                </button>
                                <a href="manage_events.php" class="btn-secondary">
                                    <i data-lucide="x"></i> Cancel
                                </a>
                            <?php else: ?>
                                <button type="submit" name="add_event" class="btn-primary">
                                    <i data-lucide="plus"></i> Add Event
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>



                </div>
                <hr class="section-divider">
                <?php endif; ?>

                <!-- ── MY EVENTS LIST ── -->
                <div class="hub-section" id="my-events">
                    <h2 class="section-title-with-icon">
                        <i data-lucide="calendar"></i> My Events
                    </h2>
                    <div class="event-controls">
                        <form method="GET" class="event-search-form" action="manage_events.php#my-events">
                            <input type="text" name="search" placeholder="Search my events..." value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                            <button type="submit" class="btn-primary btn-sm">
                                <i data-lucide="search" style="width:14px;height:14px;"></i> Search
                            </button>
                        </form>
                        <div class="event-status-filters">
                            <?php foreach ([ 'all' => 'All', 'upcoming' => 'Upcoming', 'past' => 'Past'] as $key => $label): ?>
                                <a href="manage_events.php?<?= htmlspecialchars(http_build_query(array_merge($baseQuery, ['status' => $key, 'page' => 1]))) ?>#my-events" class="filter-pill <?= $status === $key ? 'active' : '' ?>"><?= $label ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="event-summary">
                        <?php if ($total_events === 0): ?>
                            No events found.
                        <?php else: ?>
                            Showing <?= min($total_events, ($page - 1) * $per_page + 1) ?> - <?= min($total_events, $page * $per_page) ?> of <?= $total_events ?> events
                        <?php endif; ?>
                    </div>
                    <?php if ($total_events === 0): ?>
                        <div class="empty-state">There are no events that match this filter. Try a different search or change the status.</div>
                    <?php else: ?>
                        <div class="event-list">
                            <?php while ($row = $events->fetch_assoc()):
                                $can_edit   = canAccessEvent($conn, $user_id, $row['event_id'], 'edit');
                                $can_delete = canAccessEvent($conn, $user_id, $row['event_id'], 'delete');
                            ?>
                                <div class="event-card">
                                <h3><?= htmlspecialchars($row['title']) ?></h3>

                                <!-- Status badges -->
                                <div class="event-badges">
                                    <?php if ($row['requires_registration']): ?>
                                        <span class="event-badge badge-reg"><i data-lucide="user-check" style="width:11px;height:11px;"></i> Registration</span>
                                    <?php else: ?>
                                        <span class="event-badge badge-noreg"><i data-lucide="megaphone" style="width:11px;height:11px;"></i> Announcement</span>
                                    <?php endif; ?>

                                    <?php if ($row['show_on_landing']): ?>
                                        <span class="event-badge badge-landing"><i data-lucide="globe" style="width:11px;height:11px;"></i> Public</span>
                                    <?php else: ?>
                                        <span class="event-badge badge-hidden"><i data-lucide="eye-off" style="width:11px;height:11px;"></i> Hidden</span>
                                    <?php endif; ?>

                                    <?php if ($row['has_volunteer']): ?>
                                        <span class="event-badge badge-vol"><i data-lucide="users" style="width:11px;height:11px;"></i> Volunteers</span>
                                    <?php endif; ?>

                                    <?php if ($row['has_tables']): ?>
                                        <span class="event-badge badge-table"><i data-lucide="layout-grid" style="width:11px;height:11px;"></i> Tables</span>
                                    <?php endif; ?>
                                </div>

                                <p><i data-lucide="map-pin"></i><strong>Venue:</strong> <?= htmlspecialchars($row['venue']) ?></p>
                                <?php
                                $start    = strtotime($row['start_time']);
                                $end      = strtotime($row['end_time']);
                                $same_day = date('Y-m-d', $start) === date('Y-m-d', $end);
                                $date_str = $same_day
                                    ? date('F j, Y', $start) . ' · ' . date('g:i A', $start) . ' – ' . date('g:i A', $end)
                                    : date('F j, Y', $start) . ' – ' . date('F j, Y', $end);
                                ?>
                                <p><i data-lucide="calendar"></i><strong>Date:</strong> <?= $date_str ?></p>

                                <div class="event-actions" style="margin-top:auto;padding-top:12px;">
                                    <a href="view_event.php?event_id=<?= $row['event_id'] ?>" class="view-link">
                                        <i data-lucide="eye"></i> View
                                    </a>
                                    <?php if ($can_edit): ?>
                                        <a href="manage_events.php?edit=<?= $row['event_id'] ?>" class="edit-link">
                                            <i data-lucide="edit"></i> Edit
                                        </a>
                                    <?php else: ?>
                                        <span class="edit-link btn-disabled"><i data-lucide="edit"></i> Edit</span>
                                    <?php endif; ?>
                                    <?php if ($can_delete): ?>
                                        <a href="#" class="delete-link"
                                           onclick="confirmDelete(<?= $row['event_id'] ?>, '<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>'); return false;">
                                            <i data-lucide="trash-2"></i> Delete
                                        </a>
                                    <?php else: ?>
                                        <span class="delete-link btn-disabled"><i data-lucide="trash-2"></i> Delete</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="manage_events.php?<?= htmlspecialchars(http_build_query(array_merge($baseQuery, ['page' => $page - 1]))) ?>#my-events" class="page-navigation">Previous</a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                    <?php if ($p === $page): ?>
                                        <span class="active"><?= $p ?></span>
                                    <?php else: ?>
                                        <a href="manage_events.php?<?= htmlspecialchars(http_build_query(array_merge($baseQuery, ['page' => $p]))) ?>#my-events"><?= $p ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="manage_events.php?<?= htmlspecialchars(http_build_query(array_merge($baseQuery, ['page' => $page + 1]))) ?>#my-events" class="page-navigation">Next</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Delete confirmation modal -->
    <div class="delete-modal-overlay" id="deleteModal">
        <div class="delete-modal">
            <div class="delete-modal-icon">
                <i data-lucide="trash-2" style="width:28px;height:28px;"></i>
            </div>
            <h3>Delete Event?</h3>
            <p id="deleteModalText">Are you sure you want to delete this event? This cannot be undone.</p>
            <div class="delete-modal-actions">
                <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <a id="deleteConfirmBtn" href="#" class="btn-confirm-delete">Yes, Delete</a>
            </div>
        </div>
    </div>

    <!-- Delete volunteer role confirmation modal -->
    <div class="delete-modal-overlay" id="roleDeleteModal">
        <div class="delete-modal">
            <div class="delete-modal-icon">
                <i data-lucide="trash-2" style="width:28px;height:28px;"></i>
            </div>
            <h3>Delete Volunteer Role?</h3>
            <p id="roleDeleteModalText">Are you sure you want to delete this volunteer role? Existing signups for this role may be affected.</p>
            <div class="delete-modal-actions">
                <button class="btn-cancel" onclick="closeRoleDeleteModal()">Cancel</button>
                <button id="confirmRoleDeleteBtn" class="btn-confirm-delete" type="button" onclick="proceedDeleteVolunteerRole()">Yes, Delete</button>
            </div>
        </div>
    </div>

    <!-- Unsaved changes modal -->
    <div class="delete-modal-overlay" id="unsavedModal">
        <div class="delete-modal">
            <div class="delete-modal-icon">
                <i data-lucide="alert-triangle" style="width:28px;height:28px;color:#f59e0b;"></i>
            </div>
            <h3>Unsaved Changes</h3>
            <p>You have unsaved changes. Are you sure you want to leave this page? Your changes will be lost.</p>
            <div class="delete-modal-actions">
                <button class="btn-cancel" onclick="closeUnsavedModal()">Stay on Page</button>
                <button class="btn-confirm-delete" onclick="proceedLeavePage()">Leave Anyway</button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
    lucide.createIcons();

    const editForm = document.querySelector('.event-form');
    const unsavedModal = document.getElementById('unsavedModal');
    let isFormDirty = false;
    let pendingNavigationUrl = null;

    function markFormDirty() {
        if (!isFormDirty) {
            isFormDirty = true;
        }
    }

    if (editForm) {
        const watchFields = editForm.querySelectorAll('input, textarea, select');
        watchFields.forEach(field => {
            field.addEventListener('change', markFormDirty);
        });

        editForm.addEventListener('submit', function() {
            isFormDirty = false;
        });
    }

    function closeUnsavedModal() {
        unsavedModal.classList.remove('active');
        pendingNavigationUrl = null;
    }

    function proceedLeavePage() {
        isFormDirty = false;
        const urlToNavigate = pendingNavigationUrl;
        closeUnsavedModal();
        if (urlToNavigate) {
            window.location.href = urlToNavigate;
        }
    }

    function showUnsavedModal(targetUrl = null) {
        if (targetUrl) {
            pendingNavigationUrl = targetUrl;
        }
        unsavedModal.classList.add('active');
    }

    // Intercept sidebar navigation
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarLinks = document.querySelectorAll('.sidebar a[href]');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (isFormDirty && !this.classList.contains('btn-secondary')) {
                    e.preventDefault();
                    showUnsavedModal(this.href);
                }
            });
        });
    });

    window.addEventListener('beforeunload', function(e) {
        if (isFormDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    function confirmDelete(eventId, title) {
        document.getElementById('deleteModalText').textContent =
            'Are you sure you want to delete "' + title + '"? This cannot be undone.';
        document.getElementById('deleteConfirmBtn').href =
            'manage_events.php?delete=' + eventId;
        document.getElementById('deleteModal').classList.add('active');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }

    let roleDeleteTarget = null;
    function confirmDeleteVolunteerRole(btn, roleId, roleName, assignedCount) {
        roleDeleteTarget = { btn, roleId };
        let message = 'Are you sure you want to delete the volunteer role "' + roleName + '"?';
        if (assignedCount > 0) {
            message += ' There ' + (assignedCount === 1 ? 'is 1 volunteer' : 'are ' + assignedCount + ' volunteers') + ' currently assigned to this role.';
        } else {
            message += ' No volunteers are currently assigned to this role.';
        }
        message += ' Deleting it may affect any related signups.';
        document.getElementById('roleDeleteModalText').textContent = message;
        document.getElementById('roleDeleteModal').classList.add('active');
    }

    function closeRoleDeleteModal() {
        roleDeleteTarget = null;
        document.getElementById('roleDeleteModal').classList.remove('active');
    }

    function proceedDeleteVolunteerRole() {
        if (!roleDeleteTarget) return;
        removeRole(roleDeleteTarget.btn, roleDeleteTarget.roleId);
        closeRoleDeleteModal();
    }

    // Close on overlay click
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });
    document.getElementById('roleDeleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeRoleDeleteModal();
    });
    document.getElementById('unsavedModal').addEventListener('click', function(e) {
        if (e.target === this) closeUnsavedModal();
    });

    // Close on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeDeleteModal();
            closeRoleDeleteModal();
            closeUnsavedModal();
        }
    });

    function toggleSubFields(id, show) {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('show', show);

        // If toggling registration or tables, sync the capacity field visibility
        if (id === 'reg_fields' || id === 'table_fields') {
            syncCapacityVisibility();
        }
    }

    // ── Inline volunteer role management ──
    let pendingRoles = [];
    let deletedRoles = [];

    function addRoleInline() {
        const nameInput = document.getElementById('new_role_name');
        const leadSel   = document.getElementById('new_role_lead');
        const name      = nameInput.value.trim();
        const leadId    = leadSel.value;
        const leadName  = leadSel.options[leadSel.selectedIndex].text;

        if (!name) { nameInput.focus(); return; }

        // Add to pending list
        pendingRoles.push({ name, lead_id: leadId, lead_name: leadName });
        document.getElementById('pending_roles').value = JSON.stringify(pendingRoles);
        markFormDirty();

        // Render in UI
        const list = document.getElementById('vol_roles_list');
        const idx  = pendingRoles.length - 1;
        const div  = document.createElement('div');
        div.className = 'vol-role-item';
        div.dataset.pending = idx;
        div.innerHTML = `
            <div>
                <span class="role-name">${name}</span>
                ${leadName && leadId ? ` <span class="role-lead"> — Lead: ${leadName}</span>` : ''}
            </div>
            <button type="button" class="btn-delete btn-sm" onclick="removePendingRole(this, ${idx})">
                <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
            </button>`;
        list.appendChild(div);

        // Reset inputs
        nameInput.value = '';
        leadSel.value   = '';
        lucide.createIcons();
    }

    function removePendingRole(btn, idx) {
        pendingRoles[idx] = null; // nullify instead of splice to keep indices
        document.getElementById('pending_roles').value = JSON.stringify(pendingRoles.filter(r => r !== null));
        btn.closest('.vol-role-item').remove();
        markFormDirty();
    }

    function removeRole(btn, roleId) {
        // For existing roles on edit page — mark for deletion
        deletedRoles.push(roleId);
        document.getElementById('deleted_roles').value = JSON.stringify(deletedRoles);
        btn.closest('.vol-role-item').remove();
        markFormDirty();
    }

    // Allow Enter key to add role
    document.addEventListener('DOMContentLoaded', function() {
        const nameInput = document.getElementById('new_role_name');
        if (nameInput) {
            nameInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); addRoleInline(); }
            });
        }
    });

    function syncCapacityVisibility() {
        const regChecked   = document.getElementById('chk_reg') && document.getElementById('chk_reg').checked;
        const tableChecked = document.getElementById('chk_tables') && document.getElementById('chk_tables').checked;
        const regFields    = document.getElementById('reg_fields');
        if (regFields) {
            // Show capacity only when registration is ON and table assignment is OFF
            regFields.classList.toggle('show', regChecked && !tableChecked);
        }
    }

    // Run on page load to sync state
    document.addEventListener('DOMContentLoaded', syncCapacityVisibility);

    setTimeout(() => {
        document.querySelectorAll('.eh-alert-msg').forEach(el => {
            el.style.opacity = '0'; el.style.transition = 'opacity 0.5s';
            setTimeout(() => el.remove(), 500);
        });
    }, 5000);
    </script>
</body>
</html>