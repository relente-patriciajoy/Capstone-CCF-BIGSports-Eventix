<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
if (!isset($_SESSION['user_id'])) {
  header("Location: ../auth/index.php");
  exit();
}

include('../../includes/db.php');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

$stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

$query = "SELECT 
            e.event_id, 
            e.title, 
            e.description, 
            e.start_time, 
            e.end_time, 
            e.capacity, 
            e.price, 
            v.name AS venue,
            (e.capacity - COUNT(r.registration_id)) AS available_seats
        FROM event e
        JOIN venue v ON e.venue_id = v.venue_id
        LEFT JOIN registration r ON e.event_id = r.event_id
        GROUP BY e.event_id
        ORDER BY e.start_time ASC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Events - Eventix</title>
  <link rel="stylesheet" href="../../css/style.css">
  <link rel="stylesheet" href="../../css/sidebar.css">
  <?php if ($role === 'event_head'): ?>
  <link rel="stylesheet" href="../../css/event_head.css">
  <?php endif; ?>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="dashboard-layout <?= $role === 'event_head' ? 'event-head-page' : '' ?>">
<!-- Sidebar -->
<?php include('../components/sidebar.php'); ?>

<main class="main-content">
    <header class="banner <?= $role === 'event_head' ? 'event-head-banner' : '' ?>">
        <div>
            <?php if ($role === 'event_head'): ?>
            <div class="event-head-badge">
                <i data-lucide="briefcase" style="width: 14px; height: 14px;"></i>
                Event Organizer
            </div>
            <?php endif; ?>
            <h1>Upcoming Events</h1>
            <p>Explore and register for exciting events.</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
    </header>

    <!-- Registration status indicator -->
    <?php
    if (isset($_SESSION['register_status'])) {
        echo '<div id="register-alert" class="alert alert-warning">'
            . htmlspecialchars($_SESSION['register_status']) .
            '</div>';
        unset($_SESSION['register_status']);
    }
    ?>

    <section class="grid-section">
        <?php while ($row = $result->fetch_assoc()):
            $event_ended = strtotime($row['end_time']) < time();
        ?>
            <div class="card <?= $event_ended ? 'event-closed' : '' ?>">
                <h3><?= htmlspecialchars($row['title']) ?></h3>

                <?php if ($event_ended): ?>
                    <span class="event-closed-badge">
                        <i data-lucide="lock" style="width: 12px; height: 12px;"></i>
                        Registration Closed
                    </span>
                <?php endif; ?>

                <p><strong>Venue:</strong> <?= htmlspecialchars($row['venue']) ?></p>
                <p><strong>Date:</strong> <?= $row['start_time'] ?> – <?= $row['end_time'] ?></p>
                <p><strong>Price:</strong> $<?= number_format($row['price'], 2) ?></p>
                <p><strong>Available:</strong> <?= htmlspecialchars($row['available_seats'])?> seats</p>
                <p><?= nl2br(htmlspecialchars($row['description'])) ?></p>

                <?php if ($event_ended): ?>
                    <!-- Greyed-out non-clickable button for past events -->
                    <button class="btn-register-closed" disabled title="This event has already ended">
                        <i data-lucide="lock" style="width: 15px; height: 15px;"></i>
                        Registration Closed
                    </button>
                <?php else: ?>
                    <form method="POST" action="../event/event_register.php">
                        <input type="hidden" name="capacity" value="<?= $row['capacity'] ?>">
                        <input type="hidden" name="event_id" value="<?= $row['event_id'] ?>">
                        <button type="submit">Register</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </section>
</main>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
  lucide.createIcons();

  // Alert auto-hide
  const alertBox = document.getElementById('register-alert');
  if(alertBox) {
    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 3000);
  }
</script>
</body>
</html>