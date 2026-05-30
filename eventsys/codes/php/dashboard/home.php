<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/index.php");
    exit();
}

include('../../includes/db.php');

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get user role from DB (source of truth)
$stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
  <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
  <title>Dashboard - Eventix</title>
  <link rel="stylesheet" href="../../css/style.css">
  <link rel="stylesheet" href="../../css/sidebar.css">
  <?php if ($role === 'event_head'): ?>
  <link rel="stylesheet" href="../../css/event_head.css">
  <link rel="stylesheet" href="../../css/eventix-design-system.css">
  <?php endif; ?>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="dashboard-layout <?= $role === 'event_head' ? 'event-head-page' : '' ?>">
  <?php include('../components/sidebar.php'); ?>

  <main class="main-content">
    <header class="banner <?= $role === 'event_head' ? 'event-head-banner' : '' ?>">
      <div>
        <?php if ($role === 'event_head'): ?>
        <div class="event-head-badge">
          <i data-lucide="briefcase" style="width:14px;height:14px;"></i>
          Event Organizer
        </div>
        <?php endif; ?>
        <h1>Hi, <?= htmlspecialchars($full_name) ?></h1>
        <p>Welcome to your dashboard. Let's manage and discover events easily.</p>
      </div>
      <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
    </header>

    <section class="grid-section">
      <div class="card">
        <h3>Browse Events</h3>
        <p>Find and register for upcoming events.</p>
        <a href="events.php">Explore</a>
      </div>

      <div class="card">
        <h3>My Registrations</h3>
        <p>View events you've registered for.</p>
        <a href="my_events.php">View</a>
      </div>

      <?php if ($role === 'event_head'): ?>
      <div class="card">
        <h3>Manage Events</h3>
        <p>Create and update the events you organize.</p>
        <a href="../event/manage_events.php">Manage</a>
      </div>
      <?php endif; ?>
    </section>
  </main>

  <script src="../../js/script.js"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>lucide.createIcons();</script>
</body>
</html>