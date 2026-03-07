<?php
/**
 * Eventix Email Notification Functions
 * Handles event reminders and announcements using PHPMailer
 */

require_once __DIR__ . '/../vendor/autoload.php';

// -------------------------------------------------------
// CORE MAILER FACTORY
// Reuses the same SMTP config as otp_function.php
// -------------------------------------------------------
function createMailer() {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'eventix.system@gmail.com';
    $mail->Password   = 'gjzo qozj stqh iomm';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->setFrom('eventix.system@gmail.com', 'Eventix');
    $mail->isHTML(true);
    return $mail;
}

// -------------------------------------------------------
// BUILD REMINDER EMAIL HTML BODY
// -------------------------------------------------------
function buildReminderEmailBody($participant_name, $event, $type) {
    $event_title   = htmlspecialchars($event['title']);
    $venue_name    = htmlspecialchars($event['venue_name']);
    $venue_address = htmlspecialchars($event['venue_address'] ?? '');
    $start_fmt     = date('F j, Y \a\t g:i A', strtotime($event['start_time']));
    $end_fmt       = date('g:i A', strtotime($event['end_time']));
    $my_events_url = 'http://localhost/Registration-System/eventsys/codes/php/dashboard/my_events.php';

    $subject_map = [
        'reminder_3day'  => "📅 Reminder: {$event_title} is in 3 days!",
        'reminder_1day'  => "⏰ Tomorrow: {$event_title} is almost here!",
        'reminder_day_of'=> "🎉 Today is the day! {$event_title} starts soon",
    ];

    $headline_map = [
        'reminder_3day'  => "Your event is <strong>3 days away</strong>",
        'reminder_1day'  => "Your event is <strong>tomorrow!</strong>",
        'reminder_day_of'=> "Your event <strong>starts today!</strong>",
    ];

    $subject  = $subject_map[$type]  ?? "Event Reminder: {$event_title}";
    $headline = $headline_map[$type] ?? "Event Reminder";

    // Attach QR section only if path provided (handled separately)
    $body = "
    <html>
    <body style='margin:0;padding:0;font-family:Poppins,Arial,sans-serif;background:#f4f4f4;'>
      <div style='max-width:600px;margin:30px auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);'>

        <!-- Header -->
        <div style='background:linear-gradient(135deg,#8b0000 0%,#c0392b 100%);padding:36px 40px;text-align:center;'>
          <h1 style='color:white;margin:0;font-size:26px;font-weight:700;letter-spacing:-0.5px;'>Eventix</h1>
          <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:14px;'>CCF B1G Ministry</p>
        </div>

        <!-- Body -->
        <div style='padding:36px 40px;'>
          <p style='font-size:16px;color:#374151;margin:0 0 6px;'>Hello, <strong>" . htmlspecialchars($participant_name) . "</strong> 👋</p>
          <h2 style='font-size:22px;color:#8b0000;margin:12px 0 20px;'>{$headline}</h2>

          <!-- Event Card -->
          <div style='background:#fafafa;border:1px solid #e5e7eb;border-radius:12px;padding:24px;margin-bottom:24px;'>
            <h3 style='margin:0 0 16px;font-size:18px;color:#1f2937;'>{$event_title}</h3>

            <table style='width:100%;border-collapse:collapse;'>
              <tr>
                <td style='padding:8px 0;vertical-align:top;width:28px;'>
                  <span style='font-size:18px;'>📅</span>
                </td>
                <td style='padding:8px 0;'>
                  <div style='font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;'>Date &amp; Time</div>
                  <div style='font-size:15px;color:#1f2937;font-weight:500;margin-top:2px;'>{$start_fmt} – {$end_fmt}</div>
                </td>
              </tr>
              <tr>
                <td style='padding:8px 0;vertical-align:top;'>
                  <span style='font-size:18px;'>📍</span>
                </td>
                <td style='padding:8px 0;'>
                  <div style='font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;'>Venue</div>
                  <div style='font-size:15px;color:#1f2937;font-weight:500;margin-top:2px;'>{$venue_name}</div>
                  " . ($venue_address ? "<div style='font-size:13px;color:#6b7280;margin-top:2px;'>{$venue_address}</div>" : "") . "
                </td>
              </tr>
            </table>
          </div>

          <!-- QR Reminder -->
          <div style='background:#fff8f0;border-left:4px solid #f59e0b;padding:14px 18px;border-radius:6px;margin-bottom:24px;font-size:14px;color:#92400e;'>
            <strong>📲 Don't forget your QR code!</strong> You'll need it at the event entrance for quick check-in. View it in your registered events page.
          </div>

          <!-- CTA Button -->
          <div style='text-align:center;margin:28px 0;'>
            <a href='{$my_events_url}'
               style='display:inline-block;background:linear-gradient(135deg,#8b0000,#c0392b);color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.3px;'>
              View My Events &amp; QR Code →
            </a>
          </div>
        </div>

        <!-- Footer -->
        <div style='background:#f9fafb;padding:20px 40px;border-top:1px solid #e5e7eb;text-align:center;'>
          <p style='margin:0;font-size:12px;color:#9ca3af;'>This is an automated reminder from Eventix · CCF B1G Ministry</p>
          <p style='margin:4px 0 0;font-size:12px;color:#9ca3af;'>Please do not reply to this email.</p>
        </div>

      </div>
    </body>
    </html>";

    return ['subject' => $subject, 'body' => $body];
}

// -------------------------------------------------------
// BUILD ANNOUNCEMENT EMAIL HTML BODY
// -------------------------------------------------------
function buildAnnouncementEmailBody($participant_name, $event_title, $subject_text, $message, $sender_name) {
    $my_events_url = 'http://localhost/Registration-System/eventsys/codes/php/dashboard/my_events.php';

    $body = "
    <html>
    <body style='margin:0;padding:0;font-family:Poppins,Arial,sans-serif;background:#f4f4f4;'>
      <div style='max-width:600px;margin:30px auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);'>

        <!-- Header -->
        <div style='background:linear-gradient(135deg,#8b0000 0%,#c0392b 100%);padding:36px 40px;text-align:center;'>
          <h1 style='color:white;margin:0;font-size:26px;font-weight:700;'>Eventix</h1>
          <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:14px;'>CCF B1G Ministry</p>
        </div>

        <!-- Body -->
        <div style='padding:36px 40px;'>
          <div style='display:inline-block;background:#fee2e2;color:#991b1b;font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:16px;'>
            📢 Announcement
          </div>

          <p style='font-size:16px;color:#374151;margin:0 0 6px;'>Hello, <strong>" . htmlspecialchars($participant_name) . "</strong></p>
          <p style='font-size:14px;color:#6b7280;margin:0 0 20px;'>
            You have a new announcement for: <strong style='color:#1f2937;'>" . htmlspecialchars($event_title) . "</strong>
          </p>

          <!-- Subject -->
          <h2 style='font-size:20px;color:#8b0000;margin:0 0 16px;border-bottom:2px solid #fee2e2;padding-bottom:12px;'>
            " . htmlspecialchars($subject_text) . "
          </h2>

          <!-- Message Box -->
          <div style='background:#fafafa;border:1px solid #e5e7eb;border-radius:12px;padding:24px;margin-bottom:24px;font-size:15px;color:#374151;line-height:1.7;white-space:pre-wrap;'>
            " . nl2br(htmlspecialchars($message)) . "
          </div>

          <p style='font-size:13px;color:#6b7280;margin:0 0 24px;'>
            — Sent by <strong style='color:#374151;'>" . htmlspecialchars($sender_name) . "</strong>
          </p>

          <!-- CTA -->
          <div style='text-align:center;'>
            <a href='{$my_events_url}'
               style='display:inline-block;background:linear-gradient(135deg,#8b0000,#c0392b);color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:15px;'>
              View My Events →
            </a>
          </div>
        </div>

        <!-- Footer -->
        <div style='background:#f9fafb;padding:20px 40px;border-top:1px solid #e5e7eb;text-align:center;'>
          <p style='margin:0;font-size:12px;color:#9ca3af;'>This is an official announcement from Eventix · CCF B1G Ministry</p>
        </div>

      </div>
    </body>
    </html>";

    return $body;
}

// -------------------------------------------------------
// SEND REMINDER TO ALL REGISTERED PARTICIPANTS OF AN EVENT
// type: reminder_3day | reminder_1day | reminder_day_of
// Returns: ['sent' => N, 'skipped' => N, 'failed' => N]
// -------------------------------------------------------
function sendEventReminders($conn, $event_id, $type) {
    $counts = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

    // Get event details
    $event_stmt = $conn->prepare("
        SELECT e.event_id, e.title, e.start_time, e.end_time,
               v.name AS venue_name, v.address AS venue_address
        FROM event e
        JOIN venue v ON e.venue_id = v.venue_id
        WHERE e.event_id = ?
    ");
    $event_stmt->bind_param("i", $event_id);
    $event_stmt->execute();
    $event = $event_stmt->get_result()->fetch_assoc();
    $event_stmt->close();

    if (!$event) return $counts;

    // Get all confirmed participants who haven't received this reminder yet
    $part_stmt = $conn->prepare("
        SELECT r.registration_id,
               u.first_name, u.last_name, u.email
        FROM registration r
        JOIN user u ON r.user_id = u.user_id
        WHERE r.event_id = ?
          AND r.status = 'confirmed'
          AND NOT EXISTS (
              SELECT 1 FROM email_log el
              WHERE el.registration_id = r.registration_id
                AND el.email_type = ?
          )
    ");
    $part_stmt->bind_param("is", $event_id, $type);
    $part_stmt->execute();
    $participants = $part_stmt->get_result();
    $part_stmt->close();

    // Build email content once (same for all recipients)
    $email_data = buildReminderEmailBody('', $event, $type);

    while ($p = $participants->fetch_assoc()) {
        // Personalise subject/body for this participant
        $personalised = buildReminderEmailBody(
            trim($p['first_name'] . ' ' . $p['last_name']),
            $event,
            $type
        );

        try {
            $mail = createMailer();
            $mail->addAddress($p['email'], trim($p['first_name'] . ' ' . $p['last_name']));
            $mail->Subject = $personalised['subject'];
            $mail->Body    = $personalised['body'];
            $mail->AltBody = "Reminder for {$event['title']} on " . date('F j, Y', strtotime($event['start_time']));
            $mail->send();

            // Log it so it's never sent again
            $log = $conn->prepare("INSERT INTO email_log (registration_id, email_type) VALUES (?, ?)");
            $log->bind_param("is", $p['registration_id'], $type);
            $log->execute();
            $log->close();

            $counts['sent']++;
        } catch (Exception $e) {
            error_log("Reminder email failed for {$p['email']}: " . $e->getMessage());
            $counts['failed']++;
        }
    }

    return $counts;
}

// -------------------------------------------------------
// SEND ANNOUNCEMENT TO ALL REGISTERED PARTICIPANTS
// Returns: ['sent' => N, 'failed' => N]
// -------------------------------------------------------
function sendAnnouncement($conn, $event_id, $subject_text, $message, $sender_user_id) {
    $counts = ['sent' => 0, 'failed' => 0];

    // Get event title
    $event_stmt = $conn->prepare("SELECT title FROM event WHERE event_id = ?");
    $event_stmt->bind_param("i", $event_id);
    $event_stmt->execute();
    $event_stmt->bind_result($event_title);
    $event_stmt->fetch();
    $event_stmt->close();

    // Get sender name
    $sender_stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM user WHERE user_id = ?");
    $sender_stmt->bind_param("i", $sender_user_id);
    $sender_stmt->execute();
    $sender_stmt->bind_result($sender_name);
    $sender_stmt->fetch();
    $sender_stmt->close();

    // Get all confirmed participants
    $part_stmt = $conn->prepare("
        SELECT r.registration_id, u.first_name, u.last_name, u.email
        FROM registration r
        JOIN user u ON r.user_id = u.user_id
        WHERE r.event_id = ? AND r.status = 'confirmed'
    ");
    $part_stmt->bind_param("i", $event_id);
    $part_stmt->execute();
    $participants = $part_stmt->get_result();
    $part_stmt->close();

    while ($p = $participants->fetch_assoc()) {
        $participant_name = trim($p['first_name'] . ' ' . $p['last_name']);
        $body = buildAnnouncementEmailBody($participant_name, $event_title, $subject_text, $message, $sender_name);

        try {
            $mail = createMailer();
            $mail->addAddress($p['email'], $participant_name);
            $mail->Subject = "📢 [{$event_title}] " . $subject_text;
            $mail->Body    = $body;
            $mail->AltBody = "{$subject_text}\n\n{$message}\n\n— {$sender_name}";
            $mail->send();

            // Log announcement send
            $log = $conn->prepare("INSERT INTO email_log (registration_id, email_type) VALUES (?, 'announcement')");
            $log->bind_param("i", $p['registration_id']);
            $log->execute();
            $log->close();

            $counts['sent']++;
        } catch (Exception $e) {
            error_log("Announcement email failed for {$p['email']}: " . $e->getMessage());
            $counts['failed']++;
        }
    }

    // Save announcement record
    $save = $conn->prepare("INSERT INTO announcement (event_id, sent_by, subject, message) VALUES (?, ?, ?, ?)");
    $save->bind_param("iiss", $event_id, $sender_user_id, $subject_text, $message);
    $save->execute();
    $save->close();

    return $counts;
}
?>