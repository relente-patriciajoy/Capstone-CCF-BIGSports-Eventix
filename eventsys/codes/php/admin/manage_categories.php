<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole('admin');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/index.php");
    exit();
}
include('../../includes/db.php');

// Check role (admin only)
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

// Get stats for categories
$total_categories = 0;
$total_cat_result = $conn->query("SELECT COUNT(*) as total FROM event_category");
if ($total_cat_result) {
    $total_categories = $total_cat_result->fetch_assoc()['total'];
}

// Get categories with events
$cat_with_events = 0;
$cat_events_result = $conn->query("SELECT COUNT(DISTINCT category_id) as total FROM event WHERE category_id IS NOT NULL");
if ($cat_events_result) {
    $cat_with_events = $cat_events_result->fetch_assoc()['total'];
}

// ADD
if (isset($_POST['add_category'])) {
    $name = $_POST['category_name'];
    $desc = $_POST['description'];
    $stmt = $conn->prepare("INSERT INTO event_category (category_name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $desc);
    $stmt->execute();
    header("Location: manage_categories.php?status=added");
    exit();
}

// UPDATE
if (isset($_POST['update_category'])) {
    $id = $_POST['category_id'];
    $name = $_POST['category_name'];
    $desc = $_POST['description'];
    $stmt = $conn->prepare("UPDATE event_category SET category_name=?, description=? WHERE category_id=?");
    $stmt->bind_param("ssi", $name, $desc, $id);
    $stmt->execute();
    header("Location: manage_categories.php?status=updated");
    exit();
}

// DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM event_category WHERE category_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: manage_categories.php?status=deleted");
    exit();
}

// EDIT LOAD
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM event_category WHERE category_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_category = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// SEARCH
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM event_category WHERE category_name LIKE ?";
$stmt = $conn->prepare($query);
$searchTerm = "%$search%";
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$categories = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Manage Event Categories - Eventix</title>
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
          <h1>Manage Event Categories</h1>
          <p>Organize events by categories</p>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>Total Categories</h3>
                    <div class="stat-card-icon">
                        <i data-lucide="folder" size="24"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= $total_categories ?></div>
                <div class="stat-card-change">All categories</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>In Use</h3>
                    <div class="stat-card-icon">
                        <i data-lucide="calendar-check" size="24"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= $cat_with_events ?></div>
                <div class="stat-card-change">Categories with events</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>Search Results</h3>
                    <div class="stat-card-icon">
                        <i data-lucide="search" size="24"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= $categories->num_rows ?></div>
                <div class="stat-card-change">
                    <?= !empty($search) ? 'Filtered' : 'All categories' ?>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_GET['status'])): ?>
            <div class="management-alert success">
                                    if ($_GET['status'] === 'added') echo "✅ Category added successfully.";
                    elseif ($_GET['status'] === 'updated') echo "✏️ Category updated successfully.";
                    elseif ($_GET['status'] === 'deleted') echo "🗑️ Category deleted successfully.";
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
                    placeholder="Search categories..."
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
            <h2><?= $edit_category ? 'Edit Category' : 'Add New Category' ?></h2>
            <form method="POST" class="management-form">
                <?php if ($edit_category): ?>
                    <input type="hidden" name="category_id" value="<?= $edit_category['category_id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="category_name">Category Name <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="category_name"
                        name="category_name"
                        placeholder="Enter category name"
                        value="<?= $edit_category['category_name'] ?? '' ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        placeholder="Enter category description (optional)"
                        rows="4"
                    ><?= $edit_category['description'] ?? '' ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" name="<?= $edit_category ? 'update_category' : 'add_category' ?>" value="1" class="btn btn-primary">
                        <i data-lucide="<?= $edit_category ? 'save' : 'plus' ?>"></i>
                        <?= $edit_category ? 'Update Category' : 'Add Category' ?>
                    </button>
                    <?php if ($edit_category): ?>
                        <a href="manage_categories.php" class="btn btn-secondary">
                            <i data-lucide="x"></i>
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Categories Table Card -->
        <div class="management-card">
            <h2>All Categories</h2>
            <?php if ($categories->num_rows > 0): ?>
                <table class="management-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $categories->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['category_id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['category_name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($row['description']) ?: '—' ?></td>
                                <td class="actions">
                                    <a href="manage_categories.php?edit=<?= $row['category_id'] ?>" class="btn btn-edit btn-sm">
                                        <i data-lucide="edit"></i>
                                        Edit
                                    </a>
                                    <a
                                        href="manage_categories.php?delete=<?= $row['category_id'] ?>"
                                        class="btn btn-delete btn-sm"
                                        onclick="return confirm('Are you sure you want to delete this category?')"
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
                    <i data-lucide="folder"></i>
                    <h3>No Categories Found</h3>
                    <p>No categories match your search criteria.</p>
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