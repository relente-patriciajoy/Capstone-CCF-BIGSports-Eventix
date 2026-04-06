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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Event Calendar - Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/calendar.css">
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
                <h1>Event Calendar</h1>
                <p>View all upcoming events in calendar format</p>
            </div>
            <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
        </header>

        <div class="calendar-container">
            <div class="calendar-header">
                <h2 class="calendar-title" id="calendar-month-year">Loading...</h2>

                <!-- Month / Year Jump -->
                <div class="calendar-jump">
                    <select id="jump-month" class="calendar-jump-select">
                        <option value="0">January</option>
                        <option value="1">February</option>
                        <option value="2">March</option>
                        <option value="3">April</option>
                        <option value="4">May</option>
                        <option value="5">June</option>
                        <option value="6">July</option>
                        <option value="7">August</option>
                        <option value="8">September</option>
                        <option value="9">October</option>
                        <option value="10">November</option>
                        <option value="11">December</option>
                    </select>
                    <select id="jump-year" class="calendar-jump-select"></select>
                    <button class="calendar-nav-btn calendar-jump-btn" id="jump-btn">
                        <i data-lucide="search" size="16"></i> Go
                    </button>
                </div>

                <div class="calendar-navigation">
                    <button class="calendar-nav-btn" id="prev-month">
                        <i data-lucide="chevron-left" size="18"></i> Previous
                    </button>
                    <button class="calendar-nav-btn calendar-today-btn" id="today-btn">
                        <i data-lucide="calendar-days" size="18"></i> Today
                    </button>
                    <button class="calendar-nav-btn" id="next-month">
                        Next <i data-lucide="chevron-right" size="18"></i>
                    </button>
                </div>
            </div>
            <div class="calendar-grid" id="calendar-grid"></div>
            <div class="calendar-legend">
                <div class="legend-item"><div class="legend-color legend-today"></div><span>Today</span></div>
                <div class="legend-item"><div class="legend-color legend-event"></div><span>Event Day</span></div>
            </div>
        </div>
    </main>

    <!-- Event Modal -->
    <div class="event-modal-overlay" id="event-modal-overlay">
        <div class="event-modal">
            <div class="event-modal-header">
                <div>
                    <h2 class="event-modal-title" id="modal-event-title">Event Title</h2>
                    <div class="event-modal-date" id="modal-event-date">
                        <i data-lucide="calendar" size="18"></i>
                        <span>Date</span>
                    </div>
                </div>
            </div>
            <div class="event-modal-body">
                <div class="event-detail">
                    <div class="event-detail-icon"><i data-lucide="clock" size="20"></i></div>
                    <div class="event-detail-content">
                        <div class="event-detail-label">Time</div>
                        <div class="event-detail-value" id="modal-event-time">--</div>
                    </div>
                </div>
                <div class="event-detail">
                    <div class="event-detail-icon"><i data-lucide="map-pin" size="20"></i></div>
                    <div class="event-detail-content">
                        <div class="event-detail-label">Venue</div>
                        <div class="event-detail-value" id="modal-event-venue">--</div>
                    </div>
                </div>
                <div class="event-detail">
                    <div class="event-detail-icon"><i data-lucide="users" size="20"></i></div>
                    <div class="event-detail-content">
                        <div class="event-detail-label">Capacity</div>
                        <div class="event-detail-value" id="modal-event-capacity">--</div>
                    </div>
                </div>
                <div class="event-detail">
                    <div class="event-detail-icon"><i data-lucide="file-text" size="20"></i></div>
                    <div class="event-detail-content">
                        <div class="event-detail-label">Description</div>
                        <div class="event-detail-value" id="modal-event-description">--</div>
                    </div>
                </div>
            </div>
            <div class="event-modal-actions">
                <button class="event-action-btn event-register-btn" id="modal-register-btn">
                    <i data-lucide="check-circle" size="18"></i> Register Now
                </button>
                <button class="event-action-btn event-details-btn"
                        onclick="document.getElementById('event-modal-overlay').classList.remove('active')">
                    <i data-lucide="x" size="18"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script src="../../js/calendar.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
</body>
</html>