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

$query = "SELECT
            e.event_id,
            e.title,
            e.description,
            e.start_time,
            e.end_time,
            e.capacity,
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
  <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
  <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
  <title>Browse Events - Eventix</title>
  <link rel="stylesheet" href="../../css/style.css">
  <link rel="stylesheet" href="../../css/sidebar.css">
  <?php if ($role === 'event_head'): ?>
  <link rel="stylesheet" href="../../css/event_head.css">
  <?php endif; ?>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
    <style>
    /* ── Card button alignment — button always sticks to bottom ── */
    #events-grid .card {
        display: flex;
        flex-direction: column;
    }

    #events-grid .card form,
    #events-grid .card .btn-register-closed {
        margin-top: auto;
    }

    #events-grid .card form button,
    #events-grid .card .btn-register-closed {
        width: 100%;
    }

    /* ── Event description — Facebook-style inline See more ── */
    .event-desc {
        font-size: 0.88rem;
        color: #555;
        line-height: 1.6;
        margin: 4px 0 8px;
    }

    .event-see-more,
    .event-see-less {
        color: var(--maroon, #800020);
        font-weight: 700;
        cursor: pointer;
        font-size: 0.88rem;
        white-space: nowrap;
    }

    .event-see-more:hover,
    .event-see-less:hover { text-decoration: underline; }
    </style>
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
            <h1>Browse Events</h1>
            <p>Explore and register for exciting events.</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
    </header>

    <?php if (isset($_SESSION['register_status'])): ?>
        <div id="register-alert" class="alert alert-warning">
            <?= htmlspecialchars($_SESSION['register_status']) ?>
        </div>
        <?php unset($_SESSION['register_status']); ?>
    <?php endif; ?>

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
                aria-label="Search events"
            >
        </div>
    </div>

    <section class="grid-section" id="events-grid">
        <?php
        $now = time();
        while ($row = $result->fetch_assoc()):
            $event_ended = strtotime($row['end_time']) < $now;
            $end_unix    = strtotime($row['end_time']);
        ?>
        <div class="card <?= $event_ended ? 'event-closed event-past-card' : '' ?>"
             data-end="<?= $end_unix ?>"
             data-title="<?= strtolower(htmlspecialchars($row['title'])) ?>"
             data-venue="<?= strtolower(htmlspecialchars($row['venue'])) ?>">

            <h3><?= htmlspecialchars($row['title']) ?></h3>

            <?php if ($event_ended): ?>
                <span class="event-closed-badge">
                    <i data-lucide="lock" style="width:12px;height:12px;"></i>
                    Registration Closed
                </span>
            <?php endif; ?>

            <p><strong>Venue:</strong> <?= htmlspecialchars($row['venue']) ?></p>
            <?php
            $start     = strtotime($row['start_time']);
            $end       = strtotime($row['end_time']);
            $same_day  = date('Y-m-d', $start) === date('Y-m-d', $end);
            if ($same_day) {
                // e.g. April 24, 2026 · 7:30 PM – 9:30 PM
                $date_str = date('F j, Y', $start) . ' · ' . date('g:i A', $start) . ' – ' . date('g:i A', $end);
            } else {
                // e.g. May 16 – May 16, 2026
                $date_str = date('F j', $start) . ' – ' . date('F j, Y', $end);
            }
            ?>
            <p><strong>Date:</strong> <?= $date_str ?></p>
            <p><strong>Available:</strong> <?= htmlspecialchars($row['available_seats']) ?> seats</p>
            <?php
            $desc    = htmlspecialchars($row['description'] ?? '');
            $limit   = 80;
            $is_long = mb_strlen($desc) > $limit;
            $short   = $is_long ? mb_substr($desc, 0, $limit) : $desc;
            $desc_id = 'desc-' . $row['event_id'];
            ?>
            <?php if ($desc): ?>
            <p class="event-desc" id="<?= $desc_id ?>">
                <?php if ($is_long): ?>
                    <span class="event-desc-short"><?= $short ?>... <span class="event-see-more" onclick="toggleDesc('<?= $desc_id ?>')">See more</span></span><span class="event-desc-full" style="display:none;"><?= $desc ?> <span class="event-see-less" onclick="toggleDesc('<?= $desc_id ?>')">See less</span></span>
                <?php else: ?>
                    <?= $desc ?>
                <?php endif; ?>
            </p>
            <?php endif; ?>

            <?php if ($event_ended): ?>
                <button class="btn-register-closed" disabled title="This event has already ended">
                    <i data-lucide="lock" style="width:15px;height:15px;"></i>
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

        <div class="events-no-results" id="no-results">
            <svg class="no-res-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/>
            </svg>
            <p id="no-results-msg">No events found.</p>
        </div>
    </section>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
lucide.createIcons();

const alertBox = document.getElementById('register-alert');
if (alertBox) setTimeout(() => alertBox.style.display = 'none', 3000);

const cards        = Array.from(document.querySelectorAll('#events-grid .card'));
const noResults    = document.getElementById('no-results');
const noResultsMsg = document.getElementById('no-results-msg');
const searchInput  = document.getElementById('events-search');
const filterSelect = document.getElementById('events-filter');
const now          = Math.floor(Date.now() / 1000);

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
            : filter === 'past' ? 'No past events.' : filter === 'upcoming' ? 'No upcoming events.' : 'No events available.';
    }
}

filterSelect.addEventListener('change', applyFilters);
searchInput.addEventListener('input', applyFilters);
applyFilters();

// ── Facebook-style inline See more / See less ──
function toggleDesc(id) {
    const p       = document.getElementById(id);
    const short   = p.querySelector('.event-desc-short');
    const full    = p.querySelector('.event-desc-full');
    const showing = full.style.display !== 'none';
    short.style.display = showing ? '' : 'none';
    full.style.display  = showing ? 'none' : '';
}
</script>
</body>
</html>