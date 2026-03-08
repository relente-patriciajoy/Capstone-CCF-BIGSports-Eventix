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

// Handle check-in
if (isset($_POST['check_in'])) {
    $registration_id = $_POST['registration_id'];

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
                <i data-lucide="briefcase" style="width:14px;height:14px;"></i>
                Event Organizer
            </div>
            <?php endif; ?>
            <h1>Attendance Tracker</h1>
            <p>Check in and out of your events.</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
    </header>

    <?php if (isset($_SESSION['attendance_error'])): ?>
        <div id="attendance-alert" class="att-alert">
            <i data-lucide="alert-circle"></i>
            <?= htmlspecialchars($_SESSION['attendance_error']) ?>
        </div>
        <?php unset($_SESSION['attendance_error']); ?>
    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>

        <!-- Controls: filter dropdown + search -->
        <div class="events-controls">
            <div class="events-filter-wrap">
                <select id="events-filter" class="events-filter-select" aria-label="Filter attendance">
                    <option value="all">All Events</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="past">Past</option>
                </select>
            </div>

            <div class="events-search-wrap">
                <svg class="events-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="text"
                    id="events-search"
                    class="events-search"
                    placeholder="Search by event name…"
                    autocomplete="off"
                    aria-label="Search attendance"
                >
            </div>
        </div>

        <section class="grid-section" id="events-grid">
            <?php
            $now = time();
            $result->data_seek(0);
            while ($row = $result->fetch_assoc()):
                $event_ended     = strtotime($row['end_time']) < $now;
                $was_absent      = empty($row['check_in_time']);
                $missed_checkout = ($row['notes'] === 'Left without checking out');
                $locked          = $event_ended && $was_absent;
                $end_unix        = strtotime($row['end_time']);
            ?>
            <div class="card <?= $event_ended ? 'event-past-card' : '' ?>"
                 data-end="<?= $end_unix ?>"
                 data-title="<?= strtolower(htmlspecialchars($row['title'])) ?>">

                <h3><?= htmlspecialchars($row['title']) ?></h3>
                <p><strong>Event Time:</strong><br><?= $row['start_time'] ?> → <?= $row['end_time'] ?></p>
                <p><strong>Checked In:</strong> <?= $row['check_in_time'] ?? 'Not yet' ?></p>
                <p><strong>Checked Out:</strong> <?= $row['check_out_time'] ?? 'Not yet' ?></p>
                <p><strong>Status:</strong> <?= $row['status'] ?? 'absent' ?></p>

                <?php if ($missed_checkout): ?>
                    <div class="att-notice att-notice-warning">
                        <i data-lucide="alert-triangle"></i>
                        <em>Left without checking out — marked <strong>present</strong></em>
                    </div>

                <?php elseif ($locked): ?>
                    <div class="att-notice att-notice-danger">
                        <i data-lucide="lock"></i>
                        <em>Event ended — attendance locked (absent)</em>
                    </div>

                <?php elseif (!$row['check_in_time']): ?>
                    <form method="post" style="margin-top:12px;">
                        <input type="hidden" name="registration_id" value="<?= $row['registration_id'] ?>">
                        <button type="submit" name="check_in">
                            <i data-lucide="log-in" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>
                            Check In
                        </button>
                    </form>

                <?php elseif ($row['check_in_time'] && !$row['check_out_time'] && !$event_ended): ?>
                    <form method="post" style="margin-top:12px;">
                        <input type="hidden" name="registration_id" value="<?= $row['registration_id'] ?>">
                        <button type="submit" name="check_out">
                            <i data-lucide="log-out" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>
                            Check Out
                        </button>
                    </form>

                <?php else: ?>
                    <div class="att-notice att-notice-success">
                        <i data-lucide="check-circle"></i>
                        <em>Attendance complete</em>
                    </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>

            <div class="events-no-results" id="no-results">
                <svg class="no-res-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/>
                </svg>
                <p id="no-results-msg">No events found.</p>
            </div>
        </section>

    <?php else: ?>
        <div class="card" style="text-align:center;padding:48px 24px;">
            <i data-lucide="calendar-off" style="width:48px;height:48px;opacity:0.4;display:block;margin:0 auto 16px;"></i>
            <p style="color:#6b7280;margin-bottom:20px;">You haven't registered for any events yet.</p>
            <a href="events.php" class="qr-btn" style="display:inline-flex;">
                <i data-lucide="search" style="width:16px;height:16px;"></i>
                Browse Events
            </a>
        </div>
    <?php endif; ?>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
lucide.createIcons();

const alertBox = document.getElementById('attendance-alert');
if (alertBox) {
    setTimeout(() => {
        alertBox.style.opacity = '0';
        setTimeout(() => alertBox.remove(), 500);
    }, 4000);
}

const cards        = Array.from(document.querySelectorAll('#events-grid .card'));
const noResults    = document.getElementById('no-results');
const noResultsMsg = document.getElementById('no-results-msg');
const searchInput  = document.getElementById('events-search');
const filterSelect = document.getElementById('events-filter');
const now          = Math.floor(Date.now() / 1000);

if (cards.length && filterSelect) {
    function applyFilters() {
        const filter = filterSelect.value;
        const query  = searchInput.value.trim().toLowerCase();
        let visible  = 0;

        cards.forEach(card => {
            const isPast     = parseInt(card.dataset.end, 10) < now;
            const passFilter = filter === 'all' ? true : filter === 'upcoming' ? !isPast : isPast;
            const passSearch = !query || card.dataset.title.includes(query);
            const show = passFilter && passSearch;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        noResults.style.display = visible === 0 ? 'block' : 'none';
        if (visible === 0) {
            noResultsMsg.textContent = query
                ? `No events found for "${query}".`
                : filter === 'past' ? 'No past events.' : filter === 'upcoming' ? 'No upcoming events.' : 'No registered events.';
        }
    }

    filterSelect.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);
    applyFilters();
}
</script>
</body>
</html>