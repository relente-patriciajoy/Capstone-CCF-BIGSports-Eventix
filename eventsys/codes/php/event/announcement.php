<?php
/**
 * Announcements & Reminders Page
 * Event heads can send reminders and custom announcements to registered participants
 */
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole(['event_head', 'admin']);

include('../../includes/db.php');
require_once('../../includes/permission_functions.php');
require_once('../../includes/notification_function.php');

$user_id     = $_SESSION['user_id'];
$message     = "";
$error       = "";
$send_result = null;

// Fetch role for sidebar
$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

// Get user email and full name
$email_stmt = $conn->prepare("SELECT email, CONCAT(first_name,' ',last_name) AS full_name FROM user WHERE user_id = ?");
$email_stmt->bind_param("i", $user_id);
$email_stmt->execute();
$email_stmt->bind_result($user_email, $full_name);
$email_stmt->fetch();
$email_stmt->close();

// Get organizer_id
$org_stmt = $conn->prepare("SELECT organizer_id FROM organizer WHERE contact_email = ?");
$org_stmt->bind_param("s", $user_email);
$org_stmt->execute();
$org_stmt->bind_result($organizer_id);
$org_stmt->fetch();
$org_stmt->close();

// -------------------------------------------------------
// REMINDERS — upcoming events only (end_time in the future)
// -------------------------------------------------------
$reminder_events_stmt = $conn->prepare("
    SELECT e.event_id, e.title, e.start_time, e.end_time,
           COUNT(r.registration_id) AS participant_count
    FROM event e
    LEFT JOIN organizer o ON e.organizer_id = o.organizer_id
    LEFT JOIN event_access ea ON e.event_id = ea.event_id AND ea.user_id = ?
    LEFT JOIN registration r ON e.event_id = r.event_id AND r.status = 'confirmed'
    WHERE (o.contact_email = ? OR ea.can_manage_attendance = 1)
      AND e.end_time > NOW()
    GROUP BY e.event_id
    ORDER BY e.start_time ASC
");
$reminder_events_stmt->bind_param("is", $user_id, $user_email);
$reminder_events_stmt->execute();
$reminder_events = $reminder_events_stmt->get_result();
$reminder_events_stmt->close();

// -------------------------------------------------------
// ANNOUNCEMENTS — all events (past and upcoming)
// -------------------------------------------------------
$announcement_events_stmt = $conn->prepare("
    SELECT e.event_id, e.title, e.start_time, e.end_time,
           COUNT(r.registration_id) AS participant_count
    FROM event e
    LEFT JOIN organizer o ON e.organizer_id = o.organizer_id
    LEFT JOIN event_access ea ON e.event_id = ea.event_id AND ea.user_id = ?
    LEFT JOIN registration r ON e.event_id = r.event_id AND r.status = 'confirmed'
    WHERE (o.contact_email = ? OR ea.can_manage_attendance = 1)
    GROUP BY e.event_id
    ORDER BY e.start_time DESC
");
$announcement_events_stmt->bind_param("is", $user_id, $user_email);
$announcement_events_stmt->execute();
$announcement_events = $announcement_events_stmt->get_result();
$announcement_events_stmt->close();

// -------------------------------------------------------
// HANDLE SEND REMINDERS
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminders'])) {
    $event_id      = (int)$_POST['event_id'];
    $reminder_type = $_POST['reminder_type'];

    $allowed_types = ['reminder_3day', 'reminder_1day', 'reminder_day_of'];
    if (!in_array($reminder_type, $allowed_types)) {
        $error = "Invalid reminder type.";
    } else {
        $counts = sendEventReminders($conn, $event_id, $reminder_type);
        $send_result = [
            'type'    => 'reminder',
            'sent'    => $counts['sent'],
            'skipped' => $counts['skipped'],
            'failed'  => $counts['failed'],
        ];
        if ($counts['sent'] > 0) {
            $message = "✅ Reminder sent to {$counts['sent']} participant(s).";
            if ($counts['skipped'] > 0) $message .= " {$counts['skipped']} already received this reminder.";
            if ($counts['failed']  > 0) $message .= " ⚠️ {$counts['failed']} failed.";
        } elseif ($counts['skipped'] > 0) {
            $message = "ℹ️ All participants have already received this reminder.";
        } else {
            $error = "No eligible participants found or all sends failed.";
        }
    }
}

// -------------------------------------------------------
// HANDLE SEND ANNOUNCEMENT
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_announcement'])) {
    $event_id         = (int)$_POST['event_id'];
    $subject_text     = trim($_POST['subject'] ?? '');
    $announcement_msg = trim($_POST['announcement_message'] ?? '');

    if (empty($subject_text)) {
        $error = "Announcement subject is required.";
    } elseif (empty($announcement_msg)) {
        $error = "Announcement message is required.";
    } else {
        $counts = sendAnnouncement($conn, $event_id, $subject_text, $announcement_msg, $user_id);
        if ($counts['sent'] > 0) {
            $message = "✅ Announcement sent to {$counts['sent']} participant(s).";
            if ($counts['failed'] > 0) $message .= " ⚠️ {$counts['failed']} failed.";
        } else {
            $error = "No participants found for this event or all sends failed.";
        }
    }
}

// Fetch announcement history
$history_stmt = $conn->prepare("
    SELECT a.announcement_id, a.subject, a.message, a.sent_at,
           e.title AS event_title,
           CONCAT(u.first_name,' ',u.last_name) AS sender_name
    FROM announcement a
    JOIN event e ON a.event_id = e.event_id
    JOIN user u ON a.sent_by = u.user_id
    LEFT JOIN organizer o ON e.organizer_id = o.organizer_id
    WHERE o.contact_email = ? OR a.sent_by = ?
    ORDER BY a.sent_at DESC
    LIMIT 20
");
$history_stmt->bind_param("si", $user_email, $user_id);
$history_stmt->execute();
$announcements_history = $history_stmt->get_result();
$history_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements & Reminders - Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/event_head.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .notif-container { max-width: 900px; margin: 0 auto; padding: 0 24px 40px; }

        /* ── Tab bar ── */
        .tab-bar {
            display: flex;
            gap: 0;
            background: #f3f4f6;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 28px;
        }

        /* Override event_head.css global button styles for tab buttons */
        .tab-bar .tab-btn {
            flex: 1;
            padding: 10px 16px !important;
            border: none !important;
            background: transparent !important;
            border-radius: 8px !important;
            font-family: Poppins, sans-serif !important;
            font-size: 0.92rem !important;
            font-weight: 500 !important;
            color: #6b7280 !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 7px !important;
            box-shadow: none !important;
            transform: none !important;
            width: auto !important;
            margin: 0 !important;
        }

        .tab-bar .tab-btn:hover {
            background: rgba(139, 0, 0, 0.08) !important;
            color: #8b0000 !important;
            transform: none !important;
            box-shadow: none !important;
        }

        .tab-bar .tab-btn.active {
            background: white !important;
            color: #8b0000 !important;
            font-weight: 600 !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1) !important;
        }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── Form card ── */
        .form-card {
            background: white;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 24px;
        }

        .form-card h3 {
            margin: 0 0 20px;
            font-size: 1.1rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group { margin-bottom: 16px; }

        .form-group label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-group select,
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            font-family: Poppins, sans-serif;
            font-size: 0.93rem;
            color: #1f2937;
            background: #fafafa;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #8b0000;
            background: white;
        }

        .form-group textarea { resize: vertical; min-height: 120px; }

        /* ── Reminder option cards ── */
        .reminder-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 4px;
        }

        .reminder-option { position: relative; }

        .reminder-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
        }

        .reminder-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 16px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            background: #fafafa;
            font-weight: 500 !important;
            font-size: 0.88rem !important;
            color: #374151 !important;
        }

        .reminder-option label .icon { font-size: 24px; line-height: 1; }

        .reminder-option label .days {
            font-size: 1.1rem;
            font-weight: 700;
            color: #8b0000;
        }

        .reminder-option input[type="radio"]:checked + label {
            border-color: #8b0000;
            background: #fff5f5;
            color: #8b0000 !important;
            box-shadow: 0 2px 8px rgba(139,0,0,0.12);
        }

        /* ── Send button ── */
        .btn-send {
            width: 100% !important;
            padding: 13px !important;
            background: linear-gradient(135deg, #8b0000, #c0392b) !important;
            color: white !important;
            border: none !important;
            border-radius: 8px !important;
            font-family: Poppins, sans-serif !important;
            font-size: 0.95rem !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            transition: all 0.2s !important;
            margin-top: 16px !important;
            box-shadow: none !important;
        }

        .btn-send:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(139,0,0,0.3) !important;
        }

        .btn-send:disabled {
            opacity: 0.6 !important;
            cursor: not-allowed !important;
            transform: none !important;
        }

        /* ── Participant badge ── */
        .participant-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f0fdf4;
            color: #166534;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            margin-top: 8px;
            border: 1px solid #bbf7d0;
        }

        /* ── Alerts ── */
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 0.93rem;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .alert-info    { background: #dbeafe; color: #1e40af; border: 1px solid #60a5fa; }

        /* ── History ── */
        .history-card {
            background: white;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }

        .history-card h3 {
            margin: 0 0 20px;
            font-size: 1.05rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .announcement-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 12px;
            transition: border-color 0.2s;
        }

        .announcement-item:hover { border-color: #fca5a5; }

        .announcement-item .ann-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
        }

        .announcement-item .ann-subject {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.97rem;
        }

        .announcement-item .ann-meta {
            font-size: 0.82rem;
            color: #9ca3af;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .announcement-item .ann-event {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.82rem;
            background: #fff5f5;
            color: #8b0000;
            padding: 3px 8px;
            border-radius: 20px;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .announcement-item .ann-preview {
            font-size: 0.88rem;
            color: #6b7280;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .no-history {
            text-align: center;
            padding: 40px 20px;
            color: #9ca3af;
        }

        .no-history i { margin-bottom: 12px; opacity: 0.4; }

        /* ── Tip box ── */
        .tip-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 0.88rem;
            color: #92400e;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .tip-box i { flex-shrink: 0; margin-top: 2px; }

        /* ── Past event label in announcement dropdown ── */
        .past-event-label {
            color: #9ca3af;
            font-style: italic;
        }

        @media (max-width: 640px) {
            .reminder-options { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="dashboard-layout event-head-page">
<?php include('../components/sidebar.php'); ?>

<main class="main-content">
    <header class="banner event-head-banner">
        <div>
            <div class="event-head-badge">
                <i data-lucide="briefcase" style="width:14px;height:14px;"></i>
                Event Organizer
            </div>
            <h1>Announcements &amp; Reminders</h1>
            <p>Notify your registered participants via email</p>
        </div>
        <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
    </header>

    <div class="notif-container">

        <!-- Back link -->
        <a href="manage_events.php" style="display:inline-flex;align-items:center;gap:6px;color:#8b0000;font-size:0.9rem;font-weight:500;text-decoration:none;margin-bottom:20px;">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i>
            Back to Event Management
        </a>

        <!-- Alerts -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i data-lucide="check-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Tip -->
        <div class="tip-box">
            <i data-lucide="lightbulb" style="width:17px;height:17px;"></i>
            <div>
                <strong>How this works:</strong> Select an event, choose a reminder type or write an announcement, and all confirmed registered participants will receive an email. Reminder emails are sent only once per type per participant — duplicate sends are automatically prevented.
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('reminders', this)">
                <i data-lucide="bell" style="width:16px;height:16px;"></i>
                Send Reminders
            </button>
            <button class="tab-btn" onclick="switchTab('announcements', this)">
                <i data-lucide="megaphone" style="width:16px;height:16px;"></i>
                Send Announcement
            </button>
            <button class="tab-btn" onclick="switchTab('history', this)">
                <i data-lucide="history" style="width:16px;height:16px;"></i>
                History
            </button>
        </div>

        <!-- ==================== TAB: REMINDERS ==================== -->
        <div id="tab-reminders" class="tab-panel active">
            <div class="form-card">
                <h3>
                    <i data-lucide="bell" style="width:20px;height:20px;color:#8b0000;"></i>
                    Send Event Reminder
                </h3>

                <form method="POST" id="reminderForm">
                    <div class="form-group">
                        <label>Select Event</label>
                        <select name="event_id" id="reminderEventSelect" required
                                onchange="updateParticipantCount(this, 'reminderBadge')">
                            <option value="">-- Choose an upcoming event --</option>
                            <?php while ($ev = $reminder_events->fetch_assoc()): ?>
                                <option value="<?= $ev['event_id'] ?>"
                                        data-count="<?= $ev['participant_count'] ?>"
                                        data-start="<?= $ev['start_time'] ?>">
                                    <?= htmlspecialchars($ev['title']) ?>
                                    (<?= date('M j, Y', strtotime($ev['start_time'])) ?>)
                                    — <?= $ev['participant_count'] ?> registered
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if ($reminder_events->num_rows === 0): ?>
                            <p style="color:#9ca3af;font-size:0.85rem;margin-top:8px;">
                                <i data-lucide="info" style="width:14px;height:14px;vertical-align:middle;"></i>
                                No upcoming events found. Reminders can only be sent for future events.
                            </p>
                        <?php endif; ?>
                        <div id="reminderBadge" style="display:none;" class="participant-badge">
                            <i data-lucide="users" style="width:14px;height:14px;"></i>
                            <span id="reminderBadgeText"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Reminder Type</label>
                        <div class="reminder-options">
                            <div class="reminder-option">
                                <input type="radio" name="reminder_type" id="r3day" value="reminder_3day" required>
                                <label for="r3day">
                                    <span class="icon">📅</span>
                                    <span class="days">3 Days</span>
                                    <span>Before event</span>
                                </label>
                            </div>
                            <div class="reminder-option">
                                <input type="radio" name="reminder_type" id="r1day" value="reminder_1day">
                                <label for="r1day">
                                    <span class="icon">⏰</span>
                                    <span class="days">1 Day</span>
                                    <span>Before event</span>
                                </label>
                            </div>
                            <div class="reminder-option">
                                <input type="radio" name="reminder_type" id="rdayof" value="reminder_day_of">
                                <label for="rdayof">
                                    <span class="icon">🎉</span>
                                    <span class="days">Day Of</span>
                                    <span>7 hrs before</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" style="margin-top:16px;margin-bottom:0;">
                        <i data-lucide="info" style="width:16px;height:16px;flex-shrink:0;"></i>
                        <span>Each reminder type can only be sent <strong>once per participant</strong>. If a participant already received a specific reminder, they will be skipped automatically.</span>
                    </div>

                    <button type="submit" name="send_reminders" class="btn-send" id="sendReminderBtn">
                        <i data-lucide="send" style="width:17px;height:17px;"></i>
                        Send Reminder Emails
                    </button>
                </form>
            </div>
        </div>

        <!-- ==================== TAB: ANNOUNCEMENTS ==================== -->
        <div id="tab-announcements" class="tab-panel">
            <div class="form-card">
                <h3>
                    <i data-lucide="megaphone" style="width:20px;height:20px;color:#8b0000;"></i>
                    Send Announcement
                </h3>

                <form method="POST" id="announcementForm">
                    <div class="form-group">
                        <label>Select Event</label>
                        <select name="event_id" id="announcementEventSelect" required
                                onchange="updateParticipantCount(this, 'announcementBadge')">
                            <option value="">-- Choose an event --</option>
                            <?php
                            // Separate upcoming and past into optgroups for clarity
                            $upcoming = [];
                            $past     = [];
                            while ($ev = $announcement_events->fetch_assoc()) {
                                if (strtotime($ev['end_time']) >= time()) {
                                    $upcoming[] = $ev;
                                } else {
                                    $past[] = $ev;
                                }
                            }
                            ?>
                            <?php if (!empty($upcoming)): ?>
                                <optgroup label="── Upcoming Events ──">
                                    <?php foreach ($upcoming as $ev): ?>
                                        <option value="<?= $ev['event_id'] ?>"
                                                data-count="<?= $ev['participant_count'] ?>">
                                            <?= htmlspecialchars($ev['title']) ?>
                                            (<?= date('M j, Y', strtotime($ev['start_time'])) ?>)
                                            — <?= $ev['participant_count'] ?> registered
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($past)): ?>
                                <optgroup label="── Past Events ──">
                                    <?php foreach ($past as $ev): ?>
                                        <option value="<?= $ev['event_id'] ?>"
                                                data-count="<?= $ev['participant_count'] ?>">
                                            <?= htmlspecialchars($ev['title']) ?>
                                            (<?= date('M j, Y', strtotime($ev['start_time'])) ?>)
                                            — <?= $ev['participant_count'] ?> registered
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <div id="announcementBadge" style="display:none;" class="participant-badge">
                            <i data-lucide="users" style="width:14px;height:14px;"></i>
                            <span id="announcementBadgeText"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Subject / Title</label>
                        <input type="text" name="subject"
                               placeholder="e.g. Venue change for Saturday's event"
                               maxlength="200"
                               value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                        <small style="color:#9ca3af;font-size:0.8rem;margin-top:4px;display:block;">
                            This will appear as the email subject line.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="announcement_message"
                                  placeholder="Write your announcement here. Be clear and concise — participants will receive this directly in their inbox."
                                  maxlength="2000"><?= htmlspecialchars($_POST['announcement_message'] ?? '') ?></textarea>
                        <small style="color:#9ca3af;font-size:0.8rem;margin-top:4px;display:block;">
                            Max 2,000 characters. Your name will be shown as the sender.
                        </small>
                    </div>

                    <div class="alert alert-info" style="margin-bottom:0;">
                        <i data-lucide="info" style="width:16px;height:16px;flex-shrink:0;"></i>
                        <span>Announcements are sent to <strong>all confirmed registered participants</strong> of the selected event. Use responsibly.</span>
                    </div>

                    <button type="submit" name="send_announcement" class="btn-send"
                            onclick="return confirm('Send this announcement to all registered participants?')">
                        <i data-lucide="send" style="width:17px;height:17px;"></i>
                        Send Announcement
                    </button>
                </form>
            </div>
        </div>

        <!-- ==================== TAB: HISTORY ==================== -->
        <div id="tab-history" class="tab-panel">
            <div class="history-card">
                <h3>
                    <i data-lucide="history" style="width:20px;height:20px;color:#8b0000;"></i>
                    Announcement History
                    <span style="margin-left:auto;font-size:0.8rem;color:#9ca3af;font-weight:400;">Last 20 announcements</span>
                </h3>

                <?php if ($announcements_history->num_rows > 0): ?>
                    <?php while ($ann = $announcements_history->fetch_assoc()): ?>
                        <div class="announcement-item">
                            <div class="ann-header">
                                <div class="ann-subject"><?= htmlspecialchars($ann['subject']) ?></div>
                                <div class="ann-meta"><?= date('M j, Y · g:i A', strtotime($ann['sent_at'])) ?></div>
                            </div>
                            <div class="ann-event">
                                <i data-lucide="calendar" style="width:12px;height:12px;"></i>
                                <?= htmlspecialchars($ann['event_title']) ?>
                            </div>
                            <div class="ann-preview"><?= htmlspecialchars($ann['message']) ?></div>
                            <div style="margin-top:8px;font-size:0.8rem;color:#9ca3af;">
                                Sent by <?= htmlspecialchars($ann['sender_name']) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-history">
                        <i data-lucide="inbox" style="width:48px;height:48px;display:block;margin:0 auto 12px;"></i>
                        <p style="margin:0;font-size:0.95rem;">No announcements sent yet.</p>
                        <p style="margin:4px 0 0;font-size:0.85rem;">Announcements you send will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();

    // Tab switching
    function switchTab(tab, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        btn.classList.add('active');
        lucide.createIcons();
    }

    // Show participant count badge when event is selected
    function updateParticipantCount(select, badgeId) {
        const opt   = select.options[select.selectedIndex];
        const count = opt.getAttribute('data-count');
        const badge = document.getElementById(badgeId);
        const text  = document.getElementById(badgeId + 'Text');

        if (count !== null && select.value) {
            badge.style.display = 'inline-flex';
            text.textContent = count + ' confirmed participant' + (count == 1 ? '' : 's') + ' will receive this email';
            lucide.createIcons();
        } else {
            badge.style.display = 'none';
        }
    }

    // Prevent double-submit on reminder form
    document.getElementById('reminderForm').addEventListener('submit', function() {
        const btn = document.getElementById('sendReminderBtn');
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" style="width:17px;height:17px;"></i> Sending...';
        lucide.createIcons();
    });

    // Auto-dismiss success/error alerts after 6s
    setTimeout(() => {
        document.querySelectorAll('.alert-success, .alert-error').forEach(el => {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.5s';
            setTimeout(() => el.remove(), 500);
        });
    }, 6000);

    // Re-open correct tab after POST
    <?php if (isset($_POST['send_reminders'])): ?>
        switchTab('reminders', document.querySelectorAll('.tab-btn')[0]);
    <?php elseif (isset($_POST['send_announcement'])): ?>
        switchTab('announcements', document.querySelectorAll('.tab-btn')[1]);
    <?php endif; ?>
</script>
</body>
</html>