<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole('admin');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/index.php");
    exit();
}
include('../../includes/db.php');

// Check role
$user_id = $_SESSION['user_id'];
$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

if ($role !== 'admin') {
    echo "Access denied.";
    exit();
}

// Get stats for organizers
$total_organizers = 0;
$total_org_result = $conn->query("SELECT COUNT(*) as total FROM organizer");
if ($total_org_result) {
    $total_organizers = $total_org_result->fetch_assoc()['total'];
}

// Get organizers with events count
$org_with_events = 0;
$org_events_result = $conn->query("SELECT COUNT(DISTINCT organizer_id) as total FROM event WHERE organizer_id IS NOT NULL");
if ($org_events_result) {
    $org_with_events = $org_events_result->fetch_assoc()['total'];
}

// Handle add/update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'];
    $email = $_POST['contact_email'];
    $phone = $_POST['phone'];

    if (isset($_POST['organizer_id']) && $_POST['organizer_id'] !== '') {
        // Update
        $organizer_id = $_POST['organizer_id'];
        $stmt = $conn->prepare("UPDATE organizer SET name=?, contact_email=?, phone=? WHERE organizer_id=?");
        $stmt->bind_param("sssi", $name, $email, $phone, $organizer_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_organizer.php?status=updated");
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO organizer (name, contact_email, phone) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $phone);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_organizer.php?status=added");
    }
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM organizer WHERE organizer_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_organizer.php?status=deleted");
    exit();
}

// Fetch for editing
$edit_organizer = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM organizer WHERE organizer_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_organizer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Search
$search = $_GET['search'] ?? '';
$search_param = '%' . $search . '%';

if (!empty($search)) {
    $stmt = $conn->prepare("SELECT * FROM organizer WHERE name LIKE ? OR contact_email LIKE ? OR phone LIKE ?");
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} else {
    $stmt = $conn->prepare("SELECT * FROM organizer");
}

$stmt->execute();
$organizers = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Manage Organizers - Eventix Admin</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/management.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="dashboard-layout">
    <?php include('admin_sidebar.php'); ?>

    <main class="management-content">
        <!-- Page Header -->
        <div class="admin-header">
          <div class="admin-badge">
              <i data-lucide="shield" style="width: 14px; height: 14px;"></i>
              Administrator
          </div>
          <h1>Manage Organizers</h1>
          <p>Manage event organizers and coordinators</p>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>Total Organizers</h3>
                    <div class="stat-card-icon">
                        <i data-lucide="users" size="24"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= $total_organizers ?></div>
                <div class="stat-card-change">Registered organizers</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>Active Organizers</h3>
                    <div class="stat-card-icon">
                        <i data-lucide="user-check" size="24"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= $org_with_events ?></div>
                <div class="stat-card-change">With events</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>Search Results</h3>
                    <div class="stat-card-icon">
                        <i data-lucide="search" size="24"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= $organizers->num_rows ?></div>
                <div class="stat-card-change">
                    <?= !empty($search) ? 'Filtered' : 'All organizers' ?>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_GET['status'])): ?>
            <div class="management-alert success">
                                    if ($_GET['status'] === 'added') echo "✅ Organizer added successfully.";
                    elseif ($_GET['status'] === 'updated') echo "✏️ Organizer updated successfully.";
                    elseif ($_GET['status'] === 'deleted') echo "🗑️ Organizer deleted successfully.";
                ?>
                <span class="close-btn" onclick="this.parentElement.style.display='none';">×</span>
            </div>
        <?php endif; ?>

        <!-- Search Card -->
        <div class="management-card">
            <form method="GET" class="management-search">
                <input
                    type="text"
                    name="search"
                    placeholder="Search by name, email, or phone..."
                    value="<?= htmlspecialchars($search) ?>"
                >
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search"></i>
                    Search
                </button>
            </form>
        </div>

        <!-- Add/Edit Form Card -->
        <div class="management-card">
            <h2><?= $edit_organizer ? 'Edit Organizer' : 'Add New Organizer' ?></h2>
            <form method="POST" class="management-form">
                <?php if ($edit_organizer): ?>
                    <input type="hidden" name="organizer_id" value="<?= $edit_organizer['organizer_id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="name">Organizer Name <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter organizer name"
                        value="<?= $edit_organizer['name'] ?? '' ?>"
                        required
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_email">Email Address <span class="text-danger">*</span></label>
                        <input
                            type="email"
                            id="contact_email"
                            name="contact_email"
                            placeholder="Enter email address"
                            value="<?= $edit_organizer['contact_email'] ?? '' ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            placeholder="Enter phone number"
                            value="<?= $edit_organizer['phone'] ?? '' ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="<?= $edit_organizer ? 'save' : 'plus' ?>"></i>
                        <?= $edit_organizer ? 'Update Organizer' : 'Add Organizer' ?>
                    </button>
                    <?php if ($edit_organizer): ?>
                        <a href="manage_organizer.php" class="btn btn-secondary">
                            <i data-lucide="x"></i>
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Organizers Table Card -->
        <div class="management-card">
            <h2>All Organizers</h2>
            <?php if ($organizers->num_rows > 0): ?>
                <table class="management-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $organizers->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($row['contact_email']) ?></td>
                                <td><?= htmlspecialchars($row['phone']) ?></td>
                                <td class="actions">
                                    <a href="manage_organizer.php?edit=<?= $row['organizer_id'] ?>" class="btn btn-edit btn-sm">
                                        <i data-lucide="edit"></i>
                                        Edit
                                    </a>
                                    <a
                                        href="manage_organizer.php?delete=<?= $row['organizer_id'] ?>"
                                        class="btn btn-delete btn-sm"
                                        onclick="return confirm('Are you sure you want to delete this organizer?')"
                                    >
                                        <i data-lucide="trash-2"></i>
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="users"></i>
                    <h3>No Organizers Found</h3>
                    <p>No organizers match your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.management-alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>