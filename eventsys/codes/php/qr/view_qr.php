<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
/**
 * View QR Code Page
 * Users can view and download their event QR code
 */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/index.php");
    exit();
}

include('../../includes/db.php');
require_once('../../includes/qr_function.php');

$user_id = $_SESSION['user_id'];
$registration_id = $_GET['reg_id'] ?? null;

if (!$registration_id) {
    die("Invalid registration ID.");
}

// Fetch registration details
$stmt = $conn->prepare("
    SELECT r.registration_id, r.user_id, r.event_id, r.table_number, r.status, r.qr_code,
           e.title, e.start_time, e.end_time, e.description,
           v.name as venue_name, v.address as venue_address
    FROM registration r
    JOIN event e ON r.event_id = e.event_id
    JOIN venue v ON e.venue_id = v.venue_id
    WHERE r.registration_id = ? AND r.user_id = ?
");
$stmt->bind_param("ii", $registration_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Registration not found or unauthorized access.");
}

$registration = $result->fetch_assoc();
$stmt->close();

// -------------------------------------------------------
// CHECK IF EVENT HAS ENDED
// -------------------------------------------------------
$event_ended = strtotime($registration['end_time']) < time();
// -------------------------------------------------------

// Get QR code path (only bother if event is still active)
$qr_path = null;
if (!$event_ended) {
    $qr_path = getQRCodePath($registration_id);

    // If QR doesn't exist yet, generate it
    if (!$qr_path) {
        require_once('generate_qr.php');
        if (generateQRForRegistration($registration_id, $user_id, $registration['event_id'], $conn)) {
            $qr_path = getQRCodePath($registration_id);
        }
    }
}

// Check attendance record
$attendance_stmt = $conn->prepare("
    SELECT check_in_time, check_out_time, status, notes
    FROM attendance
    WHERE registration_id = ?
");
$attendance_stmt->bind_param("i", $registration_id);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();
$attendance = $attendance_result->fetch_assoc();
$attendance_stmt->close();

// Get role for sidebar
$role_stmt = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Event QR Code - Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <?php if ($role === 'event_head'): ?>
    <link rel="stylesheet" href="../../css/event_head.css">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .qr-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .qr-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        /* Active QR wrapper */
        .qr-code-wrapper {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 12px;
            margin: 25px 0;
            border: 3px dashed #e63946;
        }

        .qr-code-wrapper img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        /* Disabled QR wrapper for past events */
        .qr-code-wrapper-disabled {
            background: #f3f4f6;
            padding: 40px 30px;
            border-radius: 12px;
            margin: 25px 0;
            border: 3px dashed #d1d5db;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            color: #6b7280;
        }

        .qr-code-wrapper-disabled .lock-icon {
            width: 56px;
            height: 56px;
            background: #e5e7eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
        }

        .qr-code-wrapper-disabled p {
            margin: 0;
            font-size: 0.95rem;
            color: #6b7280;
        }

        .qr-code-wrapper-disabled strong {
            font-size: 1.05rem;
            color: #374151;
        }

        .event-details {
            text-align: left;
            background: #f9f9f9;
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-icon { color: #e63946; flex-shrink: 0; }

        .detail-content { flex: 1; }

        .detail-label {
            font-size: 0.85rem;
            color: #6b6b6b;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 1rem;
            color: #1a1a1a;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .action-buttons button,
        .action-buttons a {
            flex: 1;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #e63946 0%, #c72c3a 100%);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230, 57, 70, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #1a1a1a;
            border: 2px solid #e0e0e0;
        }

        .btn-secondary:hover {
            background: #f9f9f9;
            border-color: #e63946;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 15px;
        }

        .status-confirmed  { background: #d1fae5; color: #065f46; }
        .status-checked-in { background: #dbeafe; color: #1e40af; }
        .status-completed  { background: #f3e8ff; color: #6b21a8; }
        .status-ended      { background: #fee2e2; color: #991b1b; }
        .status-missed     { background: #fff3cd; color: #92400e; }

        .instructions {
            background: #fff3cd;
            border-left: 4px solid #f59e0b;
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: left;
        }

        .instructions h4 {
            color: #92400e;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .instructions ul {
            margin: 0;
            padding-left: 20px;
            color: #78350f;
        }

        .instructions li { margin-bottom: 8px; }

        /* Past event notice box */
        .event-ended-notice {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 16px 20px;
            margin-top: 20px;
            text-align: left;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: #991b1b;
            font-size: 0.92rem;
        }

        .event-ended-notice i { flex-shrink: 0; margin-top: 2px; }
    </style>
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
                <h1>Your Event QR Code</h1>
                <p><?= $event_ended ? 'This event has already ended.' : 'Show this QR code at the event for quick check-in' ?></p>
            </div>
            <img src="../../assets/eventix-logo.png" alt="Eventix logo" />
        </header>

        <div class="qr-container">
            <div class="qr-card">
                <h2 style="margin-bottom: 10px;"><?= htmlspecialchars($registration['title']) ?></h2>

                <?php
                // Status badge
                if ($event_ended):
                    $missed_checkout = ($attendance['notes'] ?? '') === 'Left without checking out';
                ?>
                    <?php if ($attendance && $attendance['check_in_time']): ?>
                        <?php if ($missed_checkout): ?>
                            <div class="status-badge status-missed">
                                <i data-lucide="alert-triangle" style="width: 16px; height: 16px;"></i>
                                Present (Left without checking out)
                            </div>
                        <?php else: ?>
                            <div class="status-badge status-completed">
                                <i data-lucide="check-circle-2" style="width: 16px; height: 16px;"></i>
                                Attendance Complete
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="status-badge status-ended">
                            <i data-lucide="x-circle" style="width: 16px; height: 16px;"></i>
                            Absent — Event Ended
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <?php if ($attendance && $attendance['check_in_time']): ?>
                        <?php if ($attendance['check_out_time']): ?>
                            <div class="status-badge status-completed">
                                <i data-lucide="check-circle-2" style="width: 16px; height: 16px;"></i>
                                Attendance Complete
                            </div>
                        <?php else: ?>
                            <div class="status-badge status-checked-in">
                                <i data-lucide="log-in" style="width: 16px; height: 16px;"></i>
                                Checked In
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="status-badge status-confirmed">
                            <i data-lucide="calendar-check" style="width: 16px; height: 16px;"></i>
                            Registration Confirmed
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($event_ended): ?>
                    <!-- QR DISABLED: event has ended -->
                    <div class="qr-code-wrapper-disabled">
                        <div class="lock-icon">
                            <i data-lucide="lock" style="width: 28px; height: 28px; color: #9ca3af;"></i>
                        </div>
                        <strong>QR Code Unavailable</strong>
                        <p>This event has already ended.<br>QR codes are only active during the event period.</p>
                    </div>

                    <div class="event-ended-notice">
                        <i data-lucide="info" style="width: 18px; height: 18px;"></i>
                        <div>
                            This QR code is no longer valid for check-in. Your attendance record is final.
                            <?php if ($attendance && $attendance['check_in_time']): ?>
                                You were marked <strong>present</strong> for this event.
                            <?php else: ?>
                                You were marked <strong>absent</strong> for this event.
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($qr_path): ?>
                    <!-- QR ACTIVE: event is ongoing or upcoming -->
                    <div class="qr-code-wrapper">
                        <img src="<?= $qr_path ?>" alt="Event QR Code" id="qrImage">
                    </div>
                <?php else: ?>
                    <div class="qr-code-wrapper">
                        <p style="color: #6b6b6b;">QR Code generation failed. Please contact support.</p>
                    </div>
                <?php endif; ?>

                <!-- Event details always visible -->
                <div class="event-details">
                    <div class="detail-row">
                        <i data-lucide="calendar" class="detail-icon"></i>
                        <div class="detail-content">
                            <div class="detail-label">Event Date & Time</div>
                            <div class="detail-value">
                                <?= date('F j, Y', strtotime($registration['start_time'])) ?><br>
                                <?= date('g:i A', strtotime($registration['start_time'])) ?> –
                                <?= date('g:i A', strtotime($registration['end_time'])) ?>
                            </div>
                        </div>
                    </div>

                    <div class="detail-row">
                        <i data-lucide="map-pin" class="detail-icon"></i>
                        <div class="detail-content">
                            <div class="detail-label">Venue</div>
                            <div class="detail-value">
                                <?= htmlspecialchars($registration['venue_name']) ?><br>
                                <small style="font-weight: 400; color: #6b6b6b;">
                                    <?= htmlspecialchars($registration['venue_address']) ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="detail-row">
                        <i data-lucide="hash" class="detail-icon"></i>
                        <div class="detail-content">
                            <div class="detail-label">Table Number</div>
                            <div class="detail-value">Table <?= $registration['table_number'] ?></div>
                        </div>
                    </div>

                    <?php if ($attendance && $attendance['check_in_time']): ?>
                        <div class="detail-row">
                            <i data-lucide="clock" class="detail-icon"></i>
                            <div class="detail-content">
                                <div class="detail-label">Check-In Time</div>
                                <div class="detail-value">
                                    <?= date('F j, Y - g:i A', strtotime($attendance['check_in_time'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($attendance && $attendance['check_out_time']): ?>
                        <div class="detail-row">
                            <i data-lucide="clock" class="detail-icon"></i>
                            <div class="detail-content">
                                <div class="detail-label">Check-Out Time</div>
                                <div class="detail-value">
                                    <?= date('F j, Y - g:i A', strtotime($attendance['check_out_time'])) ?>
                                    <?php if (($attendance['notes'] ?? '') === 'Left without checking out'): ?>
                                        <br><small style="color: #92400e; font-weight: 400;">
                                            (Auto-recorded at event end)
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Download button only for active events -->
                <?php if (!$event_ended && $qr_path): ?>
                    <div class="action-buttons">
                        <button onclick="downloadQR()" class="btn-primary">
                            <i data-lucide="download"></i>
                            Download QR Code
                        </button>
                        <a href="../dashboard/my_events.php" class="btn-secondary">
                            <i data-lucide="arrow-left"></i>
                            Back to My Events
                        </a>
                    </div>

                    <div class="instructions">
                        <h4>
                            <i data-lucide="info" style="width: 20px; height: 20px;"></i>
                            How to Use Your QR Code
                        </h4>
                        <ul>
                            <li><strong>Save it:</strong> Download and save this QR code to your phone</li>
                            <li><strong>Bring it:</strong> Show this QR code at the event entrance</li>
                            <li><strong>Quick scan:</strong> Staff will scan it for instant check-in</li>
                            <li><strong>No internet needed:</strong> QR code works offline at the venue</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="action-buttons">
                        <a href="../dashboard/my_events.php" class="btn-secondary" style="flex: unset; width: 100%;">
                            <i data-lucide="arrow-left"></i>
                            Back to My Events
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        function downloadQR() {
            const img = document.getElementById('qrImage');
            const link = document.createElement('a');
            link.href = img.src;
            link.download = 'event_qr_code_<?= $registration_id ?>.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>