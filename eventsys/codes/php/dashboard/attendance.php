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

// Fetch role (for sidebar)
$stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

// AUTO-CLOSE MISSED CHECKOUTS ON PAGE LOAD
$auto_close = $conn->prepare("
    UPDATE attendance a
    JOIN registration r ON a.registration_id = r.registration_id
    JOIN event e ON r.event_id = e.event_id
    SET a.check_out_time = e.end_time,
        a.notes = 'Left without checking out'
    WHERE r.user_id = ?
      AND a.check_in_time IS NOT NULL
      AND a.check_out_time IS NULL
      AND e.end_time < NOW()
      AND (a.notes IS NULL OR a.notes != 'Left without checking out')
");
$auto_close->bind_param("i", $user_id);
$auto_close->execute();
$auto_close->close();
// -------------------------------------------------------

// Handle check-in
if (isset($_POST['check_in'])) {
    $registration_id = $_POST['registration_id'];

    // Block check-in if event ended AND user was absent
    $guard = $conn->prepare("
        SELECT e.end_time, a.check_in_time
        FROM registration r
        JOIN event e ON r.event_id = e.event_id
        LEFT JOIN attendance a ON r.registration_id = a.registration_id
        WHERE r.registration_id = ?
    ");
    $guard->bind_param("i", $registration_id);
    $guard->execute();
    $guard->bind_result($end_time, $existing_check_in);
    $guard->fetch();
    $guard->close();

    if (strtotime($end_time) < time() && !$existing_check_in) {
        $_SESSION['attendance_error'] = "Check-in is no longer allowed. This event has already ended and you were marked absent.";
        header("Location: attendance.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO attendance (registration_id, check_in_time, status) 
                            VALUES (?, NOW(), 'present') 
                            ON DUPLICATE KEY UPDATE check_in_time = NOW(), status = 'present'");
    $stmt->bind_param("i", $registration_id);
    $stmt->execute();
    $stmt->close();
}

// Handle check-out
if (isset($_POST['check_out'])) {
    $registration_id = $_POST['registration_id'];

    // Block check-out if event has already ended
    $guard = $conn->prepare("
        SELECT e.end_time
        FROM registration r
        JOIN event e ON r.event_id = e.event_id
        WHERE r.registration_id = ?
    ");
    $guard->bind_param("i", $registration_id);
    $guard->execute();
    $guard->bind_result($end_time);
    $guard->fetch();
    $guard->close();

    if (strtotime($end_time) < time()) {
        $_SESSION['attendance_error'] = "Check-out is no longer allowed. This event has already ended — your attendance has been automatically recorded as present.";
        header("Location: attendance.php");
        exit();
    }

    $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW() WHERE registration_id = ?");
    $stmt->bind_param("i", $registration_id);
    $stmt->execute();
    $stmt->close();
}

// Get all registrations with event and attendance info
$query = "
SELECT r.registration_id, e.title, e.start_time, e.end_time,
       a.check_in_time, a.check_out_time, a.status, a.notes
FROM registration r
JOIN event e ON r.event_id = e.event_id
LEFT JOIN attendance a ON r.registration_id = a.registration_id
WHERE r.user_id = ?
ORDER BY e.start_time DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Attendance - Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <?php if ($role === 'event_head'): ?>
    <link rel="stylesheet" href="../../css/event_head.css">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-layout <?= $role === 'event_head' ? 'event-head-page' : '' ?>">
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
            <h1>Attendance Tracker</h1>
            <p>Check in and out of your events.</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
    </header>

    <!-- Attendance error/info message -->
    <?php if (isset($_SESSION['attendance_error'])): ?>
        <div id="attendance-alert" style="
            margin: 16px 24px;
            padding: 14px 18px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        ">
            <i data-lucide="alert-circle" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
            <?= htmlspecialchars($_SESSION['attendance_error']) ?>
        </div>
        <?php unset($_SESSION['attendance_error']); ?>
    <?php endif; ?>

    <section class="grid-section">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()):
                $event_ended     = strtotime($row['end_time']) < time();
                $was_absent      = empty($row['check_in_time']);
                $missed_checkout = ($row['notes'] === 'Left without checking out');
                // Fully locked: event ended and user never checked in at all
                $locked = $event_ended && $was_absent;
            ?>
                <div class="card">
                    <h3><?= htmlspecialchars($row['title']) ?></h3>
                    <p><strong>Event Time:</strong><br><?= $row['start_time'] ?> → <?= $row['end_time'] ?></p>
                    <p><strong>Checked In:</strong> <?= $row['check_in_time'] ?? 'Not yet' ?></p>
                    <p><strong>Checked Out:</strong> <?= $row['check_out_time'] ?? 'Not yet' ?></p>
                    <p><strong>Status:</strong> <?= $row['status'] ?? 'absent' ?></p>

                    <?php if ($missed_checkout): ?>
                        <!-- Checked in but never checked out — auto-closed -->
                        <p style="color: #92400e; font-size: 0.88rem; margin-top: 8px;
                                  background: #fff3cd; border-left: 3px solid #f59e0b;
                                  padding: 8px 10px; border-radius: 4px;
                                  display: flex; align-items: center; gap: 6px;">
                            <i data-lucide="alert-triangle" style="width: 15px; height: 15px; flex-shrink: 0;"></i>
                            <em>Left without checking out — marked <strong>present</strong></em>
                        </p>

                    <?php elseif ($locked): ?>
                        <!-- Event ended, user was absent — fully locked -->
                        <p style="color: #b91c1c; font-size: 0.88rem; margin-top: 8px;
                                  display: flex; align-items: center; gap: 6px;">
                            <i data-lucide="lock" style="width: 15px; height: 15px;"></i>
                            <em>Event ended — attendance locked (absent)</em>
                        </p>

                    <?php elseif (!$row['check_in_time']): ?>
                        <!-- Not checked in yet, event still active -->
                        <form method="post">
                            <input type="hidden" name="registration_id" value="<?= $row['registration_id'] ?>">
                            <button type="submit" name="check_in">
                                <i data-lucide="log-in" style="width: 16px; height: 16px;"></i>
                                Check In
                            </button>
                        </form>

                    <?php elseif ($row['check_in_time'] && !$row['check_out_time'] && !$event_ended): ?>
                        <!-- Checked in, event still live → allow checkout -->
                        <form method="post">
                            <input type="hidden" name="registration_id" value="<?= $row['registration_id'] ?>">
                            <button type="submit" name="check_out">
                                <i data-lucide="log-out" style="width: 16px; height: 16px;"></i>
                                Check Out
                            </button>
                        </form>

                    <?php else: ?>
                        <!-- Properly checked in and out -->
                        <p style="color: #059669; font-weight: 600;">
                            <i data-lucide="check-circle" style="width: 16px; height: 16px; vertical-align: middle;"></i>
                            <em>Attendance complete</em>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                <p>You haven't registered for any events yet.</p>
                <a href="events.php" style="display: inline-flex; align-items: center; gap: 8px; margin-top: 15px;">
                    <i data-lucide="search" style="width: 16px; height: 16px;"></i>
                    Browse Events
                </a>
            </div>
        <?php endif; ?>
    </section>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
  lucide.createIcons();

  const alertBox = document.getElementById('attendance-alert');
  if (alertBox) {
    setTimeout(() => {
      alertBox.style.opacity = '0';
      alertBox.style.transition = 'opacity 0.5s';
      setTimeout(() => alertBox.remove(), 500);
    }, 4000);
  }
</script>
</body>
</html>