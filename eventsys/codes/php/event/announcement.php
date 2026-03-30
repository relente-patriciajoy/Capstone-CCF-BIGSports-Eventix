<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole(['event_head', 'admin']);
include('../../includes/db.php');
require_once('../../includes/permission_functions.php');
require_once('../../includes/notification_function.php');

$user_id = $_SESSION['user_id'];
$message = ""; $error = "";

$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id); $role_stmt->execute();
$role_stmt->bind_result($role); $role_stmt->fetch(); $role_stmt->close();

$email_stmt = $conn->prepare("SELECT email, CONCAT(first_name,' ',last_name) AS full_name FROM user WHERE user_id = ?");
$email_stmt->bind_param("i", $user_id); $email_stmt->execute();
$email_stmt->bind_result($user_email, $full_name); $email_stmt->fetch(); $email_stmt->close();

$org_stmt = $conn->prepare("SELECT organizer_id FROM organizer WHERE contact_email = ?");
$org_stmt->bind_param("s", $user_email); $org_stmt->execute();
$org_stmt->bind_result($organizer_id); $org_stmt->fetch(); $org_stmt->close();

$reminder_events_stmt = $conn->prepare("SELECT e.event_id, e.title, e.start_time, e.end_time, COUNT(r.registration_id) AS participant_count FROM event e LEFT JOIN organizer o ON e.organizer_id = o.organizer_id LEFT JOIN event_access ea ON e.event_id = ea.event_id AND ea.user_id = ? LEFT JOIN registration r ON e.event_id = r.event_id AND r.status = 'confirmed' WHERE (o.contact_email = ? OR ea.can_manage_attendance = 1) AND e.end_time > NOW() GROUP BY e.event_id ORDER BY e.start_time ASC");
$reminder_events_stmt->bind_param("is", $user_id, $user_email); $reminder_events_stmt->execute();
$reminder_events = $reminder_events_stmt->get_result(); $reminder_events_stmt->close();

$announcement_events_stmt = $conn->prepare("SELECT e.event_id, e.title, e.start_time, e.end_time, COUNT(r.registration_id) AS participant_count FROM event e LEFT JOIN organizer o ON e.organizer_id = o.organizer_id LEFT JOIN event_access ea ON e.event_id = ea.event_id AND ea.user_id = ? LEFT JOIN registration r ON e.event_id = r.event_id AND r.status = 'confirmed' WHERE (o.contact_email = ? OR ea.can_manage_attendance = 1) GROUP BY e.event_id ORDER BY e.start_time DESC");
$announcement_events_stmt->bind_param("is", $user_id, $user_email); $announcement_events_stmt->execute();
$announcement_events = $announcement_events_stmt->get_result(); $announcement_events_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminders'])) {
    $event_id = (int)$_POST['event_id']; $reminder_type = $_POST['reminder_type'];
    $allowed = ['reminder_3day','reminder_1day','reminder_day_of'];
    if (!in_array($reminder_type, $allowed)) { $error = "Invalid reminder type."; }
    else {
        $counts = sendEventReminders($conn, $event_id, $reminder_type);
        if ($counts['sent'] > 0) { $message = "✅ Reminder sent to {$counts['sent']} participant(s)."; if ($counts['skipped'] > 0) $message .= " {$counts['skipped']} already received this."; if ($counts['failed'] > 0) $message .= " ⚠️ {$counts['failed']} failed."; }
        elseif ($counts['skipped'] > 0) { $message = "ℹ️ All participants already received this reminder."; }
        else { $error = "No eligible participants found or all sends failed."; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_announcement'])) {
    $event_id = (int)$_POST['event_id'];
    $subject_text = trim($_POST['subject'] ?? ''); $announcement_msg = trim($_POST['announcement_message'] ?? '');
    if (empty($subject_text)) { $error = "Subject is required."; }
    elseif (empty($announcement_msg)) { $error = "Message is required."; }
    else {
        $counts = sendAnnouncement($conn, $event_id, $subject_text, $announcement_msg, $user_id);
        if ($counts['sent'] > 0) { $message = "✅ Announcement sent to {$counts['sent']} participant(s)."; if ($counts['failed'] > 0) $message .= " ⚠️ {$counts['failed']} failed."; }
        else { $error = "No participants found or all sends failed."; }
    }
}

$history_stmt = $conn->prepare("SELECT a.subject, a.message, a.sent_at, e.title AS event_title, CONCAT(u.first_name,' ',u.last_name) AS sender_name FROM announcement a JOIN event e ON a.event_id = e.event_id JOIN user u ON a.sent_by = u.user_id LEFT JOIN organizer o ON e.organizer_id = o.organizer_id WHERE o.contact_email = ? OR a.sent_by = ? ORDER BY a.sent_at DESC LIMIT 20");
$history_stmt->bind_param("si", $user_email, $user_id); $history_stmt->execute();
$announcements_history = $history_stmt->get_result(); $history_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Announcements & Reminders - Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/event_head.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-layout event-head-page">
<?php include('../components/sidebar.php'); ?>
<main class="main-content">
    <header class="banner event-head-banner">
        <div>
            <div class="event-head-badge"><i data-lucide="briefcase" style="width:14px;height:14px;"></i> Event Organizer</div>
            <h1>Announcements &amp; Reminders</h1>
            <p>Notify your registered participants via email</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo">
    </header>

    <div class="eh-page">
        <?php if (!empty($message)): ?>
            <div class="eh-alert eh-alert-success"><i data-lucide="check-circle" style="width:18px;height:18px;flex-shrink:0;"></i><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="eh-alert eh-alert-error"><i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="eh-tip-box">
            <i data-lucide="lightbulb" style="width:17px;height:17px;flex-shrink:0;"></i>
            <div><strong>How this works:</strong> Select an event, choose a reminder type or write an announcement, and all confirmed participants will receive an email. Reminder emails are sent only once per type per participant.</div>
        </div>

        <div class="eh-tab-bar">
            <button class="eh-tab-btn active" onclick="switchTab('reminders',this)"><i data-lucide="bell" style="width:16px;height:16px;"></i> Send Reminders</button>
            <button class="eh-tab-btn" onclick="switchTab('announcements',this)"><i data-lucide="megaphone" style="width:16px;height:16px;"></i> Send Announcement</button>
            <button class="eh-tab-btn" onclick="switchTab('history',this)"><i data-lucide="history" style="width:16px;height:16px;"></i> History</button>
        </div>

        <!-- REMINDERS -->
        <div id="tab-reminders" class="eh-tab-panel active">
            <div class="eh-card">
                <h3><i data-lucide="bell" style="width:20px;height:20px;"></i> Send Event Reminder</h3>
                <form method="POST" id="reminderForm">
                    <div class="eh-form-group">
                        <label>Select Event</label>
                        <select name="event_id" required onchange="updateBadge(this,'reminderBadge')">
                            <option value="">-- Choose an upcoming event --</option>
                            <?php while ($ev = $reminder_events->fetch_assoc()): ?>
                                <option value="<?= $ev['event_id'] ?>" data-count="<?= $ev['participant_count'] ?>">
                                    <?= htmlspecialchars($ev['title']) ?> (<?= date('M j, Y', strtotime($ev['start_time'])) ?>) — <?= $ev['participant_count'] ?> registered
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if ($reminder_events->num_rows === 0): ?>
                            <span class="eh-form-hint">No upcoming events found. Reminders can only be sent for future events.</span>
                        <?php endif; ?>
                        <div id="reminderBadge" class="eh-participant-badge" style="display:none;">
                            <i data-lucide="users" style="width:14px;height:14px;"></i>
                            <span id="reminderBadgeText"></span>
                        </div>
                    </div>

                    <div class="eh-form-group">
                        <label>Reminder Type</label>
                        <div class="eh-reminder-grid">
                            <div class="eh-reminder-option">
                                <input type="radio" name="reminder_type" id="r3day" value="reminder_3day" required>
                                <label for="r3day"><span class="remind-icon">📅</span><span class="remind-days">3 Days</span><span>Before event</span></label>
                            </div>
                            <div class="eh-reminder-option">
                                <input type="radio" name="reminder_type" id="r1day" value="reminder_1day">
                                <label for="r1day"><span class="remind-icon">⏰</span><span class="remind-days">1 Day</span><span>Before event</span></label>
                            </div>
                            <div class="eh-reminder-option">
                                <input type="radio" name="reminder_type" id="rdayof" value="reminder_day_of">
                                <label for="rdayof"><span class="remind-icon">🎉</span><span class="remind-days">Day Of</span><span>7 hrs before</span></label>
                            </div>
                        </div>
                    </div>

                    <div class="eh-alert eh-alert-info" style="margin-bottom:0;">
                        <i data-lucide="info" style="width:16px;height:16px;flex-shrink:0;"></i>
                        <span>Each reminder type can only be sent <strong>once per participant</strong>. Already-sent reminders are skipped automatically.</span>
                    </div>
                    <button type="submit" name="send_reminders" class="eh-btn-send" id="sendReminderBtn">
                        <i data-lucide="send" style="width:17px;height:17px;"></i> Send Reminder Emails
                    </button>
                </form>
            </div>
        </div>

        <!-- ANNOUNCEMENTS -->
        <div id="tab-announcements" class="eh-tab-panel">
            <div class="eh-card">
                <h3><i data-lucide="megaphone" style="width:20px;height:20px;"></i> Send Announcement</h3>
                <form method="POST" id="announcementForm">
                    <div class="eh-form-group">
                        <label>Select Event</label>
                        <select name="event_id" required onchange="updateBadge(this,'announcementBadge')">
                            <option value="">-- Choose an event --</option>
                            <?php
                            $upcoming = []; $past = [];
                            while ($ev = $announcement_events->fetch_assoc()) {
                                if (strtotime($ev['end_time']) >= time()) $upcoming[] = $ev;
                                else $past[] = $ev;
                            }
                            if (!empty($upcoming)): ?>
                                <optgroup label="── Upcoming Events ──">
                                    <?php foreach ($upcoming as $ev): ?>
                                        <option value="<?= $ev['event_id'] ?>" data-count="<?= $ev['participant_count'] ?>">
                                            <?= htmlspecialchars($ev['title']) ?> (<?= date('M j, Y', strtotime($ev['start_time'])) ?>) — <?= $ev['participant_count'] ?> registered
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; if (!empty($past)): ?>
                                <optgroup label="── Past Events ──">
                                    <?php foreach ($past as $ev): ?>
                                        <option value="<?= $ev['event_id'] ?>" data-count="<?= $ev['participant_count'] ?>">
                                            <?= htmlspecialchars($ev['title']) ?> (<?= date('M j, Y', strtotime($ev['start_time'])) ?>) — <?= $ev['participant_count'] ?> registered
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <div id="announcementBadge" class="eh-participant-badge" style="display:none;">
                            <i data-lucide="users" style="width:14px;height:14px;"></i>
                            <span id="announcementBadgeText"></span>
                        </div>
                    </div>

                    <div class="eh-form-group">
                        <label>Subject / Title</label>
                        <input type="text" name="subject" placeholder="e.g. Venue change for Saturday's event" maxlength="200"
                               value="<?= (isset($_POST['send_announcement']) && empty($error)) ? '' : htmlspecialchars($_POST['subject'] ?? '') ?>">
                        <span class="eh-form-hint">This will appear as the email subject line.</span>
                    </div>

                    <div class="eh-form-group">
                        <label>Message</label>
                        <textarea name="announcement_message" placeholder="Write your announcement here..." maxlength="2000"><?= (isset($_POST['send_announcement']) && empty($error)) ? '' : htmlspecialchars($_POST['announcement_message'] ?? '') ?></textarea>
                        <span class="eh-form-hint">Max 2,000 characters. Your name will be shown as the sender.</span>
                    </div>

                    <div class="eh-alert eh-alert-info" style="margin-bottom:0;">
                        <i data-lucide="info" style="width:16px;height:16px;flex-shrink:0;"></i>
                        <span>Announcements are sent to <strong>all confirmed registered participants</strong> of the selected event. Use responsibly.</span>
                    </div>
                    <button type="button" class="eh-btn-send" onclick="openConfirmModal()">
                        <i data-lucide="send" style="width:17px;height:17px;"></i> Send Announcement
                    </button>
                </form>
            </div>
        </div>

        <!-- HISTORY -->
        <div id="tab-history" class="eh-tab-panel">
            <div class="eh-card">
                <h3>
                    <i data-lucide="history" style="width:20px;height:20px;"></i>
                    Announcement History
                    <span style="margin-left:auto;font-size:0.8rem;color:#9ca3af;font-weight:400;">Last 20</span>
                </h3>
                <?php if ($announcements_history->num_rows > 0): ?>
                    <?php while ($ann = $announcements_history->fetch_assoc()): ?>
                        <div class="eh-ann-item">
                            <div class="eh-ann-header">
                                <div class="eh-ann-subject"><?= htmlspecialchars($ann['subject']) ?></div>
                                <div class="eh-ann-meta"><?= date('M j, Y · g:i A', strtotime($ann['sent_at'])) ?></div>
                            </div>
                            <div class="eh-ann-event-tag">
                                <i data-lucide="calendar" style="width:12px;height:12px;"></i>
                                <?= htmlspecialchars($ann['event_title']) ?>
                            </div>
                            <div class="eh-ann-preview"><?= htmlspecialchars($ann['message']) ?></div>
                            <div class="eh-ann-sender">Sent by <?= htmlspecialchars($ann['sender_name']) ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="eh-empty-history">
                        <i data-lucide="inbox" style="width:48px;height:48px;display:block;margin:0 auto 12px;opacity:0.3;"></i>
                        <p>No announcements sent yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CONFIRM MODAL -->
    <div id="confirmModal" class="eh-modal-backdrop" style="display:none;">
        <div class="eh-modal-box">
            <div class="eh-modal-icon"><i data-lucide="megaphone" style="width:26px;height:26px;color:var(--maroon);"></i></div>
            <h3 class="eh-modal-title">Send Announcement?</h3>
            <p class="eh-modal-body">This will send your announcement to <strong>all confirmed registered participants</strong> of the selected event. This action cannot be undone.</p>
            <div class="eh-modal-actions">
                <button class="eh-btn eh-btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                <button class="eh-btn eh-btn-primary" onclick="confirmSend()">Yes, Send It</button>
            </div>
        </div>
    </div>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
lucide.createIcons();

function switchTab(tab, btn) {
    document.querySelectorAll('.eh-tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.eh-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    btn.classList.add('active');
    lucide.createIcons();
}

function updateBadge(select, badgeId) {
    const count = select.options[select.selectedIndex].getAttribute('data-count');
    const badge = document.getElementById(badgeId);
    const text  = document.getElementById(badgeId + 'Text');
    if (count !== null && select.value) {
        badge.style.display = 'inline-flex';
        text.textContent = count + ' confirmed participant' + (count == 1 ? '' : 's') + ' will receive this email';
        lucide.createIcons();
    } else { badge.style.display = 'none'; }
}

function openConfirmModal() {
    if (!document.getElementById('announcementForm').reportValidity()) return;
    document.getElementById('confirmModal').style.display = 'flex';
    lucide.createIcons();
}

function closeConfirmModal() { document.getElementById('confirmModal').style.display = 'none'; }

function confirmSend() {
    closeConfirmModal();
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'send_announcement'; input.value = '1';
    const form = document.getElementById('announcementForm');
    form.appendChild(input); form.submit();
}

document.getElementById('confirmModal').addEventListener('click', function(e) { if (e.target === this) closeConfirmModal(); });

document.getElementById('reminderForm').addEventListener('submit', function() {
    const btn = document.getElementById('sendReminderBtn');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" style="width:17px;height:17px;"></i> Sending...';
    lucide.createIcons();
});

setTimeout(() => {
    document.querySelectorAll('.eh-alert-success, .eh-alert-error').forEach(el => {
        el.style.opacity = '0'; el.style.transition = 'opacity 0.5s';
        setTimeout(() => el.remove(), 500);
    });
}, 6000);

<?php if (isset($_POST['send_reminders'])): ?>
    switchTab('reminders', document.querySelectorAll('.eh-tab-btn')[0]);
<?php elseif (isset($_POST['send_announcement'])): ?>
    switchTab('announcements', document.querySelectorAll('.eh-tab-btn')[1]);
<?php endif; ?>
</script>
</body>
</html>