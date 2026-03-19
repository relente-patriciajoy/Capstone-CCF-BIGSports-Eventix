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

$query = "
SELECT r.registration_id, e.title, e.start_time, e.end_time,
       v.name AS venue, r.registration_date, r.table_number, r.status
FROM registration r
JOIN event e ON r.event_id = e.event_id
JOIN venue v ON e.venue_id = v.venue_id
WHERE r.user_id = ?
ORDER BY e.start_time
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
    <title>My Registered Events - Eventix</title>
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
            <h1>My Registered Events</h1>
            <p>See all the events you've registered for.</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
    </header>

    <?php if ($result->num_rows > 0): ?>

        <!-- Controls: filter dropdown + search -->
        <div class="events-controls">
            <div class="events-filter-wrap">
                <select id="events-filter" class="events-filter-select" aria-label="Filter events">
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
                    placeholder="Search by event name or venue…"
                    autocomplete="off"
                    aria-label="Search my events"
                >
            </div>
        </div>

        <section class="grid-section" id="events-grid">
            <?php
            $now = time();
            $result->data_seek(0);
            while ($row = $result->fetch_assoc()):
                $is_past  = strtotime($row['end_time']) < $now;
                $end_unix = strtotime($row['end_time']);
                $status   = strtolower($row['status']);
            ?>
            <div class="card <?= $is_past ? 'event-past-card' : '' ?>"
                 data-end="<?= $end_unix ?>"
                 data-title="<?= strtolower(htmlspecialchars($row['title'])) ?>"
                 data-venue="<?= strtolower(htmlspecialchars($row['venue'])) ?>">

                <?php if ($is_past): ?>
                    <span class="event-past-badge">
                        <i data-lucide="clock" style="width:12px;height:12px;"></i>
                        Past Event
                    </span>
                <?php endif; ?>

                <h3><?= htmlspecialchars($row['title']) ?></h3>
                <p><strong>Venue:</strong> <?= htmlspecialchars($row['venue']) ?></p>
                <?php
                $start    = strtotime($row['start_time']);
                $end      = strtotime($row['end_time']);
                $same_day = date('Y-m-d', $start) === date('Y-m-d', $end);
                $date_str = $same_day
                    ? date('F j, Y', $start) . ' · ' . date('g:i A', $start) . ' – ' . date('g:i A', $end)
                    : date('F j', $start) . ' – ' . date('F j, Y', $end);
                ?>
                <p><strong>Date:</strong> <?= $date_str ?></p>
                <p><strong>Table Number:</strong> <?= $row['table_number'] ?></p>
                <p>
                    <strong>Status:</strong>
                    <span class="status-badge status-<?= $status ?>">
                        <?= ucfirst(htmlspecialchars($row['status'])) ?>
                    </span>
                </p>

                <a href="../qr/view_qr.php?reg_id=<?= $row['registration_id'] ?>" class="qr-btn">
                    <i data-lucide="qr-code" style="width:16px;height:16px;"></i>
                    View QR Code
                </a>
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
            const passSearch = !query || card.dataset.title.includes(query) || card.dataset.venue.includes(query);
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