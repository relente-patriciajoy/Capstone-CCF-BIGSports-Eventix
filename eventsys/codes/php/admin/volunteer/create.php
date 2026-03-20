<?php
require_once('../../../includes/session.php');
require_once('../../../includes/role_protection.php');
requireRole('admin');
include('../../../includes/db.php');

$user_id = $_SESSION['user_id'];
$message = '';
$error   = '';
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$event   = null;
$roles   = [];

if ($edit_id) {
    $eq = $conn->prepare("SELECT * FROM volunteer_event WHERE volunteer_event_id = ?");
    $eq->bind_param("i", $edit_id);
    $eq->execute();
    $event = $eq->get_result()->fetch_assoc();
    $eq->close();

    if ($event) {
        $rq = $conn->prepare("
            SELECT vrt.*, CONCAT(u.first_name,' ',u.last_name) AS lead_name
            FROM volunteer_role_type vrt
            LEFT JOIN user u ON vrt.team_lead_id = u.user_id
            WHERE vrt.volunteer_event_id = ?
        ");
        $rq->bind_param("i", $edit_id);
        $rq->execute();
        $roles = $rq->get_result()->fetch_all(MYSQLI_ASSOC);
        $rq->close();
    }
}

$users_result = $conn->query("SELECT user_id, CONCAT(first_name,' ',last_name) AS name, email FROM user WHERE status='active' ORDER BY first_name");
$all_users    = $users_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date  = trim($_POST['event_date'] ?? '');
    $location    = trim($_POST['location'] ?? '');

    if (empty($title) || empty($event_date)) {
        $error = "Title and event date are required.";
    } else {
        if ($edit_id && $event) {
            $stmt = $conn->prepare("UPDATE volunteer_event SET title=?,description=?,event_date=?,location=? WHERE volunteer_event_id=?");
            $stmt->bind_param("ssssi", $title, $description, $event_date, $location, $edit_id);
            $stmt->execute();
            $vol_event_id = $edit_id;
            $stmt->close();
        } else {
            $qr_token = bin2hex(random_bytes(16));
            $stmt = $conn->prepare("INSERT INTO volunteer_event (title,description,event_date,location,qr_token,created_by) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("sssssi", $title, $description, $event_date, $location, $qr_token, $user_id);
            $stmt->execute();
            $vol_event_id = $stmt->insert_id;
            $stmt->close();
        }

        // Rebuild roles
        $del = $conn->prepare("DELETE FROM volunteer_role_type WHERE volunteer_event_id = ?");
        $del->bind_param("i", $vol_event_id);
        $del->execute();
        $del->close();

        $role_names = $_POST['role_name'] ?? [];
        $role_leads = $_POST['team_lead'] ?? [];

        foreach ($role_names as $i => $rn) {
            if (!in_array($rn, ['ushering','admin','technical'])) continue;
            $lead_id = !empty($role_leads[$i]) ? (int)$role_leads[$i] : null;
            $ins = $conn->prepare("INSERT INTO volunteer_role_type (volunteer_event_id,role_name,team_lead_id) VALUES (?,?,?)");
            $ins->bind_param("isi", $vol_event_id, $rn, $lead_id);
            $ins->execute();
            $ins->close();
        }

                header("Location: detail.php?id=$vol_event_id&saved=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_id ? 'Edit' : 'Create' ?> Volunteer Event — Admin</title>
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
        <h1><?= $edit_id ? 'Edit' : 'Create' ?> Volunteer Event</h1>
        <p>Set up roles and assign team leads</p>
    </div>

    <?php if ($error): ?>
        <div class="management-alert error">
            <i data-lucide="alert-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <!-- Event details -->
        <div class="management-card">
            <h2>Event Details</h2>
            <div class="form-row" style="grid-template-columns:1fr 1fr;">
                <div class="form-group">
                    <label>Event Title <span class="text-danger">*</span></label>
                    <input type="text" name="title"
                           value="<?= htmlspecialchars($event['title'] ?? '') ?>"
                           placeholder="e.g. Sunday Service Volunteer" required>
                </div>
                <div class="form-group">
                    <label>Event Date & Time <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="event_date"
                           value="<?= $event ? date('Y-m-d\TH:i', strtotime($event['event_date'])) : '' ?>"
                           required>
                </div>
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location"
                       value="<?= htmlspecialchars($event['location'] ?? '') ?>"
                       placeholder="e.g. CCF Center – Main Hall">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"
                          placeholder="Brief description of what volunteers will do..."><?= htmlspecialchars($event['description'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Roles -->
        <div class="management-card">
            <div class="card-toolbar">
                <h2>Volunteer Roles</h2>
                <button type="button" onclick="addRole()" class="btn btn-secondary btn-sm">
                    <i data-lucide="plus" style="width:13px;height:13px;"></i> Add Role
                </button>
            </div>

            <div id="roles-container">
                <?php if (!empty($roles)): ?>
                    <?php foreach ($roles as $r): ?>
                    <div class="role-row">
                        <div class="form-group" style="margin:0;">
                            <label>Role Type</label>
                            <select name="role_name[]">
                                <option value="ushering"  <?= $r['role_name']==='ushering'  ?'selected':'' ?>>Ushering</option>
                                <option value="admin"     <?= $r['role_name']==='admin'     ?'selected':'' ?>>Admin</option>
                                <option value="technical" <?= $r['role_name']==='technical' ?'selected':'' ?>>Technical</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Team Lead</label>
                            <select name="team_lead[]">
                                <option value="">-- No lead assigned --</option>
                                <?php foreach ($all_users as $u): ?>
                                    <option value="<?= $u['user_id'] ?>"
                                        <?= $r['team_lead_id']==$u['user_id']?'selected':'' ?>>
                                        <?= htmlspecialchars($u['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" onclick="this.closest('.role-row').remove()"
                                class="btn btn-delete btn-sm" style="margin-top:20px;">
                            <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p id="no-roles-msg" style="color:#9ca3af;font-size:0.9rem;text-align:center;padding:20px;">
                        No roles added yet. Click "Add Role" to start.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" style="width:15px;height:15px;"></i>
                <?= $edit_id ? 'Save Changes' : 'Create Volunteer Event' ?>
            </button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>

        <script id="users-data" type="application/json"><?= json_encode($all_users) ?></script>
    </form>
</main>

<script>
lucide.createIcons();
const allUsers = JSON.parse(document.getElementById('users-data').textContent);

function addRole() {
    document.getElementById('no-roles-msg')?.remove();
    let opts = '<option value="">-- No lead assigned --</option>';
    allUsers.forEach(u => { opts += `<option value="${u.user_id}">${u.name}</option>`; });

    const row = document.createElement('div');
    row.className = 'role-row';
    row.innerHTML = `
        <div class="form-group" style="margin:0;">
            <label>Role Type</label>
            <select name="role_name[]">
                <option value="ushering">Ushering</option>
                <option value="admin">Admin</option>
                <option value="technical">Technical</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Team Lead</label>
            <select name="team_lead[]">${opts}</select>
        </div>
        <button type="button" onclick="this.closest('.role-row').remove()"
                class="btn btn-delete btn-sm" style="margin-top:20px;">
            <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
        </button>`;
    document.getElementById('roles-container').appendChild(row);
    lucide.createIcons();
}
</script>
</body>
</html>
<?php $conn->close(); ?>