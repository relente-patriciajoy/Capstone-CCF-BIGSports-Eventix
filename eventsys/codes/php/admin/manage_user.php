<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole('admin');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/index.php");
    exit();
}
include('../../includes/db.php');

// Access control
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

// Get stats for users
$total_users = 0;
$total_users_result = $conn->query("SELECT COUNT(*) as total FROM user");
if ($total_users_result) {
    $total_users = $total_users_result->fetch_assoc()['total'];
}

// Get admin count
$admin_count = 0;
$admin_result = $conn->query("SELECT COUNT(*) as total FROM user WHERE role = 'admin'");
if ($admin_result) {
    $admin_count = $admin_result->fetch_assoc()['total'];
}

// Get event head count
$event_head_count = 0;
$event_head_result = $conn->query("SELECT COUNT(*) as total FROM user WHERE role = 'event_head'");
if ($event_head_result) {
    $event_head_count = $event_head_result->fetch_assoc()['total'];
}

// Get regular user count
$regular_user_count = 0;
$regular_user_result = $conn->query("SELECT COUNT(*) as total FROM user WHERE role = 'user'");
if ($regular_user_result) {
    $regular_user_count = $regular_user_result->fetch_assoc()['total'];
}

// Add User
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_user'])) {
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO user (first_name, middle_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $first_name, $middle_name, $last_name, $email, $phone, $password, $role);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_user.php?status=added");
    exit();
}

// Update User
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_user'])) {
    $user_id_edit = $_POST['user_id'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];

    $stmt = $conn->prepare("UPDATE user SET first_name=?, middle_name=?, last_name=?, email=?, phone=?, role=? WHERE user_id=?");
    $stmt->bind_param("ssssssi", $first_name, $middle_name, $last_name, $email, $phone, $role, $user_id_edit);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_user.php?status=updated");
    exit();
}

// Delete
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_user.php?status=deleted");
    exit();
}

// Search
$search_term = $_GET['search'] ?? '';
$stmt = $conn->prepare("SELECT * FROM user WHERE CONCAT(first_name, ' ', last_name, email, phone) LIKE ?");
$search_param = "%$search_term%";
$stmt->bind_param("s", $search_param);
$stmt->execute();
$users = $stmt->get_result();

// Edit
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Manage Users - Eventix Admin</title>
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
        <h1>Manage Users</h1>
        <p>Add, edit, and manage user accounts</p>
    </div>

      <!-- Stats Row -->
      <div class="stats-row">
          <div class="stat-card">
              <div class="stat-card-header">
                  <h3>Total Users</h3>
                  <div class="stat-card-icon">
                      <i data-lucide="users" size="24"></i>
                  </div>
              </div>
              <div class="stat-card-value"><?= $total_users ?></div>
              <div class="stat-card-change">All registered users</div>
          </div>

          <div class="stat-card">
              <div class="stat-card-header">
                  <h3>Admins</h3>
                  <div class="stat-card-icon">
                      <i data-lucide="shield" size="24"></i>
                  </div>
              </div>
              <div class="stat-card-value"><?= $admin_count ?></div>
              <div class="stat-card-change"><?= $event_head_count ?> event heads</div>
          </div>

          <div class="stat-card">
              <div class="stat-card-header">
                  <h3>Regular Users</h3>
                  <div class="stat-card-icon">
                      <i data-lucide="user" size="24"></i>
                  </div>
              </div>
              <div class="stat-card-value"><?= $regular_user_count ?></div>
              <div class="stat-card-change">Standard accounts</div>
          </div>

          <div class="stat-card">
              <div class="stat-card-header">
                  <h3>Search Results</h3>
                  <div class="stat-card-icon">
                      <i data-lucide="search" size="24"></i>
                  </div>
              </div>
              <div class="stat-card-value"><?= $users->num_rows ?></div>
              <div class="stat-card-change">
                  <?= !empty($search_term) ? 'Filtered' : 'All users' ?>
              </div>
          </div>
      </div>

      <!-- Alert Messages -->
      <?php if (isset($_GET['status'])): ?>
          <div class="management-alert success">
                                if ($_GET['status'] === 'added') echo "✅ User added successfully.";
                  elseif ($_GET['status'] === 'updated') echo "✏️ User updated successfully.";
                  elseif ($_GET['status'] === 'deleted') echo "🗑️ User deleted successfully.";
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
                  value="<?= htmlspecialchars($search_term) ?>"
              >
              <button type="submit" class="btn btn-primary">
                  <i data-lucide="search"></i>
                  Search
              </button>
          </form>
      </div>

      <!-- Add/Edit Form Card -->
      <div class="management-card">
          <h2><?= $edit_user ? 'Edit User' : 'Add New User' ?></h2>
          <form method="POST" class="management-form">
              <?php if ($edit_user): ?>
                  <input type="hidden" name="user_id" value="<?= $edit_user['user_id'] ?>">
              <?php endif; ?>

              <div class="form-row">
                  <div class="form-group">
                      <label for="first_name">First Name <span class="text-danger">*</span></label>
                      <input
                          type="text"
                          id="first_name"
                          name="first_name"
                          placeholder="Enter first name"
                          value="<?= $edit_user['first_name'] ?? '' ?>"
                          required
                      >
                  </div>

                  <div class="form-group">
                      <label for="middle_name">Middle Name</label>
                      <input
                          type="text"
                          id="middle_name"
                          name="middle_name"
                          placeholder="Enter middle name"
                          value="<?= $edit_user['middle_name'] ?? '' ?>"
                      >
                  </div>

                  <div class="form-group">
                      <label for="last_name">Last Name <span class="text-danger">*</span></label>
                      <input
                          type="text"
                          id="last_name"
                          name="last_name"
                          placeholder="Enter last name"
                          value="<?= $edit_user['last_name'] ?? '' ?>"
                          required
                      >
                  </div>
              </div>

              <div class="form-row">
                  <div class="form-group">
                      <label for="email">Email Address <span class="text-danger">*</span></label>
                      <input
                          type="email"
                          id="email"
                          name="email"
                          placeholder="Enter email"
                          value="<?= $edit_user['email'] ?? '' ?>"
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
                          value="<?= $edit_user['phone'] ?? '' ?>"
                          required
                      >
                  </div>
              </div>

              <?php if (!$edit_user): ?>
                  <div class="form-group">
                      <label for="password">Password <span class="text-danger">*</span></label>
                      <input
                          type="password"
                          id="password"
                          name="password"
                          placeholder="Enter password"
                          required
                      >
                  </div>
              <?php endif; ?>

              <div class="form-group">
                  <label for="role">User Role <span class="text-danger">*</span></label>
                  <select name="role" id="role" required>
                      <option value="">-- Select Role --</option>
                      <option value="user" <?= (isset($edit_user['role']) && $edit_user['role'] == 'user') ? 'selected' : '' ?>>User</option>
                      <option value="event_head" <?= (isset($edit_user['role']) && $edit_user['role'] == 'event_head') ? 'selected' : '' ?>>Event Head</option>
                      <option value="admin" <?= (isset($edit_user['role']) && $edit_user['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                  </select>
              </div>

              <div class="form-actions">
                  <button type="submit" name="<?= $edit_user ? 'update_user' : 'add_user' ?>" value="1" class="btn btn-primary">
                      <i data-lucide="<?= $edit_user ? 'save' : 'plus' ?>"></i>
                      <?= $edit_user ? 'Update User' : 'Add User' ?>
                  </button>
                  <?php if ($edit_user): ?>
                      <a href="manage_user.php" class="btn btn-secondary">
                          <i data-lucide="x"></i>
                          Cancel
                      </a>
                  <?php endif; ?>
              </div>
          </form>
      </div>

      <!-- Users Table Card -->
      <div class="management-card">
          <h2>All Users</h2>
          <?php if ($users->num_rows > 0): ?>
              <table class="management-table">
                  <thead>
                      <tr>
                          <th>Name</th>
                          <th>Email</th>
                          <th>Phone</th>
                          <th>Role</th>
                          <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php while ($row = $users->fetch_assoc()): ?>
                          <tr>
                              <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                              <td><?= htmlspecialchars($row['email']) ?></td>
                              <td><?= htmlspecialchars($row['phone']) ?></td>
                              <td>
                                  <span class="badge badge-<?= $row['role'] === 'admin' ? 'danger' : ($row['role'] === 'event_head' ? 'warning' : 'info') ?>">
                                      <?= ucfirst(str_replace('_', ' ', $row['role'])) ?>
                                  </span>
                              </td>
                              <td class="actions">
                                  <a href="manage_user.php?edit=<?= $row['user_id'] ?>" class="btn btn-edit btn-sm">
                                      <i data-lucide="edit"></i>
                                      Edit
                                  </a>
                                  <a
                                      href="manage_user.php?delete=<?= $row['user_id'] ?>"
                                      class="btn btn-delete btn-sm"
                                      onclick="return confirm('Are you sure you want to delete this user?')"
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
                  <h3>No Users Found</h3>
                  <p>No users match your search criteria.</p>
              </div>
          <?php endif; ?>
      </div>
  </main>

  <script>
      lucide.createIcons();

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