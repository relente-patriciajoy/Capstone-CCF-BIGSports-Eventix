<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole('admin');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/index.php");
    exit();
}

include('../../includes/db.php');

$user_id = $_SESSION['user_id'];
$success = $error = "";
$edit_venue = null;

// Check user role
$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

if ($role !== 'admin' && $role !== 'event_head') {
    die("Access denied.");
}

// Get stats for venues
$total_venues = 0;
$total_venue_result = $conn->query("SELECT COUNT(*) as total FROM venue");
if ($total_venue_result) {
    $total_venues = $total_venue_result->fetch_assoc()['total'];
}

// Get venues with events
$venues_with_events = 0;
$venue_events_result = $conn->query("SELECT COUNT(DISTINCT venue_id) as total FROM event WHERE venue_id IS NOT NULL");
if ($venue_events_result) {
    $venues_with_events = $venue_events_result->fetch_assoc()['total'];
}

// Get total capacity
$total_capacity = 0;
$capacity_result = $conn->query("SELECT SUM(capacity) as total FROM venue");
if ($capacity_result) {
    $total_capacity = $capacity_result->fetch_assoc()['total'] ?? 0;
}

// Handle Add Venue
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_venue'])) {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $capacity = $_POST['capacity'];

    $stmt = $conn->prepare("INSERT INTO venue (name, address, city, capacity) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $name, $address, $city, $capacity);

    if ($stmt->execute()) {
        $success = "Venue added successfully.";
    } else {
        $error = "Error adding venue.";
    }
    $stmt->close();
}

// Handle Edit Load
if (isset($_GET['edit'])) {
    $venue_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM venue WHERE venue_id = ?");
    $stmt->bind_param("i", $venue_id);
    $stmt->execute();
    $edit_venue = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_venue'])) {
    $venue_id = $_POST['venue_id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $capacity = $_POST['capacity'];

    $stmt = $conn->prepare("UPDATE venue SET name = ?, address = ?, city = ?, capacity = ? WHERE venue_id = ?");
    $stmt->bind_param("sssii", $name, $address, $city, $capacity, $venue_id);

    if ($stmt->execute()) {
        $success = "Venue updated successfully.";
        $edit_venue = null;
    } else {
        $error = "Error updating venue.";
    }
    $stmt->close();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $venue_id = $_GET['delete'];
    
    // Check if venue is linked to any events
    $check_stmt = $conn->prepare("SELECT COUNT(*) as event_count FROM event WHERE venue_id = ?");
    $check_stmt->bind_param("i", $venue_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ($check_row['event_count'] > 0) {
        $error = "Error: Cannot delete venue. It is linked to " . $check_row['event_count'] . " event(s).";
    } else {
        $stmt = $conn->prepare("DELETE FROM venue WHERE venue_id = ?");
        $stmt->bind_param("i", $venue_id);

        if ($stmt->execute()) {
            header("Location: manage_venue.php?status=deleted");
            exit();
        } else {
            $error = "Error: Could not delete venue.";
        }
        $stmt->close();
    }
}

// Search
$search = $_GET['search'] ?? '';
$search_query = "SELECT * FROM venue";
if (!empty($search)) {
    $search_query .= " WHERE name LIKE ? OR city LIKE ?";
    $stmt = $conn->prepare($search_query);
    $like = "%$search%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $venues = $stmt->get_result();
    $stmt->close();
} else {
    $venues = $conn->query("SELECT * FROM venue");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
  <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png"><link rel="apple-touch-icon" href="../../assets/fav-logo.png">
  <title>Manage Venues - Eventix Admin</title>
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
        <h1>Manage Venues</h1>
        <p>Add, edit, and manage event venues</p>
      </div>

      <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-card-header">
                <h3>Total Venues</h3>
                <div class="stat-card-icon">
                    <i data-lucide="map-pin" size="24"></i>
                </div>
            </div>
            <div class="stat-card-value"><?= $total_venues ?></div>
            <div class="stat-card-change">Available venues</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <h3>Active Venues</h3>
                <div class="stat-card-icon">
                    <i data-lucide="calendar" size="24"></i>
                </div>
            </div>
            <div class="stat-card-value"><?= $venues_with_events ?></div>
            <div class="stat-card-change">With events</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <h3>Total Capacity</h3>
                <div class="stat-card-icon">
                    <i data-lucide="users-round" size="24"></i>
                </div>
            </div>
            <div class="stat-card-value"><?= number_format($total_capacity) ?></div>
            <div class="stat-card-change">People capacity</div>
        </div>
    </div>

      <!-- Alert Messages -->
      <?php if (!empty($error)): ?>
          <div class="management-alert error">
              <?= htmlspecialchars($error) ?>
              <span class="close-btn" onclick="this.parentElement.style.display='none';">×</span>
          </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
          <div class="management-alert success">
              <?= htmlspecialchars($success) ?>
              <span class="close-btn" onclick="this.parentElement.style.display='none';">×</span>
          </div>
      <?php endif; ?>

      <?php if (isset($_GET['status'])): ?>
          <div class="management-alert <?= $_GET['status'] === 'error' ? 'error' : 'success' ?>">
                                if ($_GET['status'] === 'added') echo "✅ Venue added successfully.";
                  elseif ($_GET['status'] === 'updated') echo "✏️ Venue updated successfully.";
                  elseif ($_GET['status'] === 'deleted') echo "🗑️ Venue deleted successfully.";
                  elseif ($_GET['status'] === 'error') echo "❌ Error: Venue might be linked to existing events.";
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
                  placeholder="Search by venue name or city..."
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
          <h2><?= $edit_venue ? 'Edit Venue' : 'Add New Venue' ?></h2>
          <form method="POST" class="management-form">
              <?php if ($edit_venue): ?>
                  <input type="hidden" name="venue_id" value="<?= $edit_venue['venue_id'] ?>">
              <?php endif; ?>

              <div class="form-group">
                  <label for="name">Venue Name <span class="text-danger">*</span></label>
                  <input
                      type="text"
                      id="name"
                      name="name"
                      placeholder="Enter venue name"
                      value="<?= $edit_venue['name'] ?? '' ?>"
                      required
                  >
              </div>

              <div class="form-row">
                  <div class="form-group">
                      <label for="address">Address <span class="text-danger">*</span></label>
                      <input
                          type="text"
                          id="address"
                          name="address"
                          placeholder="Enter address"
                          value="<?= $edit_venue['address'] ?? '' ?>"
                          required
                      >
                  </div>

                  <div class="form-group">
                      <label for="city">City <span class="text-danger">*</span></label>
                      <input
                          type="text"
                          id="city"
                          name="city"
                          placeholder="Enter city"
                          value="<?= $edit_venue['city'] ?? '' ?>"
                          required
                      >
                  </div>
              </div>

              <div class="form-group">
                  <label for="capacity">Capacity <span class="text-danger">*</span></label>
                  <input
                      type="number"
                      id="capacity"
                      name="capacity"
                      placeholder="Enter maximum capacity"
                      value="<?= $edit_venue['capacity'] ?? '' ?>"
                      min="1"
                      required
                  >
              </div>

              <div class="form-actions">
                  <button type="submit" name="<?= $edit_venue ? 'update_venue' : 'add_venue' ?>" value="1" class="btn btn-primary">
                      <i data-lucide="<?= $edit_venue ? 'save' : 'plus' ?>"></i>
                      <?= $edit_venue ? 'Update Venue' : 'Add Venue' ?>
                  </button>
                  <?php if ($edit_venue): ?>
                      <a href="manage_venue.php" class="btn btn-secondary">
                          <i data-lucide="x"></i>
                          Cancel
                      </a>
                  <?php endif; ?>
              </div>
          </form>
      </div>

      <!-- Venues Table Card -->
      <div class="management-card">
          <h2>All Venues</h2>
          <?php if ($venues->num_rows > 0): ?>
              <table class="management-table">
                  <thead>
                      <tr>
                          <th>Venue Name</th>
                          <th>Address</th>
                          <th>City</th>
                          <th>Capacity</th>
                          <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php while ($venue = $venues->fetch_assoc()): ?>
                          <tr>
                              <td><?= htmlspecialchars($venue['name']) ?></td>
                              <td><?= htmlspecialchars($venue['address']) ?></td>
                              <td><?= htmlspecialchars($venue['city']) ?></td>
                              <td>
                                  <span class="badge badge-info">
                                      <?= $venue['capacity'] ?> people
                                  </span>
                              </td>
                              <td class="actions">
                                  <a href="manage_venue.php?edit=<?= $venue['venue_id'] ?>" class="btn btn-edit btn-sm">
                                      <i data-lucide="edit"></i>
                                      Edit
                                  </a>
                                  <a
                                      href="manage_venue.php?delete=<?= $venue['venue_id'] ?>"
                                      class="btn btn-delete btn-sm"
                                      onclick="return confirm('Are you sure you want to delete this venue?')"
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
                  <i data-lucide="map-pin"></i>
                  <h3>No Venues Found</h3>
                  <p>No venues match your search criteria.</p>
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