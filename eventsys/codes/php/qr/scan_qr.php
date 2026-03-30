<?php
/**
 * QR Code Scanner Page
 * For event heads and admins to scan attendee QR codes for check-in/check-out
 * Only shows active/upcoming events (ended events are excluded)
 */

require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole(['event_head', 'admin']);

include('../../includes/db.php');
require_once('../../includes/permission_functions.php');
require_once('../../includes/qr_function.php');

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role_name'];

// CHECK PERMISSION
if (!hasPermission($conn, $user_id, 'attendance.qr.scan')) {
    die('
        <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Denied</title>
        <link rel="stylesheet" href="../../css/style.css"></head>
        <body style="display:flex;align-items:center;justify-content:center;height:100vh;background:#f3f4f6;">
            <div style="text-align:center;padding:40px;background:white;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                <h1 style="color:#ef4444;margin-bottom:16px;">🚫 Access Denied</h1>
                <p style="color:#6b6b6b;margin-bottom:24px;">You don\'t have permission to access the QR scanner.</p>
                <a href="../event/manage_events.php" style="display:inline-block;padding:12px 24px;background:#e63946;color:white;text-decoration:none;border-radius:8px;">← Back to Dashboard</a>
            </div>
        </body></html>
    ');
}

// Get user email
$email_stmt = $conn->prepare("SELECT email FROM user WHERE user_id = ?");
$email_stmt->bind_param("i", $user_id);
$email_stmt->execute();
$email_stmt->bind_result($email);
$email_stmt->fetch();
$email_stmt->close();

// Get ACTIVE/UPCOMING events only (end_time >= NOW())
// Ended events are excluded so the scanner can't be used for them
if ($user_role === 'admin' || hasPermission($conn, $user_id, 'event.view.all')) {
    $events = $conn->query("
        SELECT e.event_id, e.title, e.start_time, e.end_time
        FROM event e
        WHERE e.end_time >= NOW()
        ORDER BY e.start_time ASC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT DISTINCT e.event_id, e.title, e.start_time, e.end_time
        FROM event e
        LEFT JOIN organizer o ON e.organizer_id = o.organizer_id
        LEFT JOIN event_access ea ON e.event_id = ea.event_id AND ea.user_id = ?
        WHERE (o.contact_email = ? OR ea.can_manage_attendance = 1)
          AND e.end_time >= NOW()
        ORDER BY e.start_time ASC
    ");
    $stmt->bind_param("is", $user_id, $email);
    $stmt->execute();
    $events = $stmt->get_result();
}

$has_events    = ($events->num_rows > 0);
$is_event_head = ($user_role === 'event_head');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>QR Code Scanner - Eventix</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <?php if ($is_event_head): ?>
    <link rel="stylesheet" href="../../css/event_head.css">
    <?php endif; ?>
    <link rel="stylesheet" href="../../css/qr_scanner.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body class="dashboard-layout <?= $is_event_head ? 'event-head-page' : '' ?>">

    <?php
    if ($user_role === 'admin') {
        include('../admin/admin_sidebar.php');
    } else {
        $role = $user_role;
        include('../components/sidebar.php');
    }
    ?>

    <main class="main-content">

        <!-- Banner -->
        <header class="banner <?= $is_event_head ? 'event-head-banner' : '' ?>">
            <div>
                <?php if ($is_event_head): ?>
                <div class="event-head-badge">
                    <i data-lucide="briefcase" style="width:14px;height:14px;"></i>
                    Event Organizer
                </div>
                <?php elseif ($user_role === 'admin'): ?>
                <div class="event-head-badge" style="background:linear-gradient(135deg,#1a1a1a,#2d2d2d);color:#e63946;">
                    <i data-lucide="shield" style="width:14px;height:14px;"></i>
                    Administrator
                </div>
                <?php endif; ?>
                <h1>QR Code Scanner</h1>
                <p>Scan attendee QR codes for quick check-in / check-out</p>
            </div>
            <img src="../../assets/eventix-logo.png" alt="Eventix logo">
        </header>

        <?php if (!$has_events): ?>
        <!-- No active events state -->
        <div class="scanner-container" style="grid-template-columns:1fr;">
            <div class="results-empty" style="padding:64px 24px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" style="width:56px;height:56px;margin:0 auto 16px;display:block;opacity:0.35;">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="16" x2="15" y2="16"/>
                </svg>
                <p style="font-size:1rem;font-weight:600;color:#374151;margin:0 0 6px;">No active events</p>
                <p style="font-size:0.88rem;color:#9ca3af;margin:0;">There are no upcoming or ongoing events available for scanning. Ended events cannot be checked in or out.</p>
            </div>
        </div>

        <?php else: ?>
        <div class="scanner-container">

            <!-- ── LEFT: Controls ── -->
            <div class="scanner-box">
                <h2>
                    <i data-lucide="scan-line" style="width:20px;height:20px;color:#e63946;"></i>
                    Scanner Controls
                </h2>

                <div class="scanner-controls">

                    <!-- Event picker (only active/upcoming) -->
                    <select id="eventSelect" aria-label="Select event">
                        <option value="">— Select an event —</option>
                        <?php while ($event = $events->fetch_assoc()):
                            $now        = time();
                            $start_unix = strtotime($event['start_time']);
                            $end_unix   = strtotime($event['end_time']);
                            $is_live    = ($now >= $start_unix && $now <= $end_unix);
                            $label      = $is_live ? '🟢 ' : '🕐 ';
                            $date_str   = date('M j, Y · g:i A', $start_unix);
                        ?>
                        <option value="<?= $event['event_id'] ?>"
                                data-end="<?= $end_unix ?>">
                            <?= $label . htmlspecialchars($event['title']) ?> — <?= $date_str ?>
                        </option>
                        <?php endwhile; ?>
                    </select>

                    <!-- Action toggle -->
                    <div class="action-toggle" role="group" aria-label="Select action">
                        <input type="radio" id="act-checkin" name="action" value="checkin" checked>
                        <label for="act-checkin">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            Check In
                        </label>
                        <input type="radio" id="act-checkout" name="action" value="checkout">
                        <label for="act-checkout">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Check Out
                        </label>
                    </div>

                    <!-- Start / Stop -->
                    <button onclick="startScanner()" id="startBtn" class="btn-primary">
                        <i data-lucide="camera" style="width:17px;height:17px;"></i>
                        Start Scanner
                    </button>
                    <button onclick="stopScanner()" id="stopBtn" class="btn-secondary" style="display:none;">
                        <i data-lucide="square" style="width:17px;height:17px;"></i>
                        Stop Scanner
                    </button>
                </div>

                <!-- Status -->
                <div class="scanner-status" id="scannerStatus" style="display:none;">
                    <div class="status-indicator status-inactive" id="statusIndicator"></div>
                    <span id="statusText">Scanner inactive</span>
                </div>

                <!-- Camera viewport -->
                <div id="qr-reader" style="display:none;"></div>

                <!-- Stats -->
                <div class="scan-stats">
                    <div class="stat-box">
                        <div class="stat-number" id="totalScans">0</div>
                        <div class="stat-label">Total</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number" id="successScans" style="color:#10b981;">0</div>
                        <div class="stat-label">Success</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number" id="errorScans" style="color:#ef4444;">0</div>
                        <div class="stat-label">Errors</div>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT: Results ── -->
            <div>
                <div class="results-empty" id="emptyState">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    <p>Scan results will appear here</p>
                </div>
                <div id="resultContainer"></div>
            </div>

        </div>
        <?php endif; ?>

    </main>

    <script>
    lucide.createIcons();

    let html5QrCode;
    let isScanning = false;
    const stats = { total: 0, success: 0, errors: 0 };

    function getAction() {
        return document.querySelector('input[name="action"]:checked').value;
    }

    function startScanner() {
        const sel = document.getElementById('eventSelect');
        if (!sel || !sel.value) {
            alert('Please select an event first.');
            return;
        }

        // Double-check: guard against an ended event being selected
        const endTs = parseInt(sel.options[sel.selectedIndex].dataset.end, 10);
        if (endTs && endTs < Math.floor(Date.now() / 1000)) {
            alert('This event has already ended. Scanning is not allowed for past events.');
            return;
        }

        document.getElementById('qr-reader').style.display      = 'block';
        document.getElementById('scannerStatus').style.display  = 'flex';
        document.getElementById('startBtn').style.display       = 'none';
        document.getElementById('stopBtn').style.display        = 'flex';

        html5QrCode = new Html5Qrcode('qr-reader');
        html5QrCode.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 220, height: 220 } },
            onScanSuccess,
            () => {}
        ).then(() => {
            isScanning = true;
            updateStatus(true, 'Scanner active — ready to scan');
        }).catch(() => {
            alert('Camera access failed. Please allow camera permissions and try again.');
            stopScanner();
        });
    }

    function stopScanner() {
        if (html5QrCode && isScanning) {
            html5QrCode.stop().then(() => {
                isScanning = false;
                document.getElementById('qr-reader').style.display     = 'none';
                document.getElementById('scannerStatus').style.display = 'none';
                document.getElementById('startBtn').style.display      = 'flex';
                document.getElementById('stopBtn').style.display       = 'none';
                updateStatus(false, 'Scanner inactive');
            }).catch(console.error);
        }
    }

    function updateStatus(active, text) {
        document.getElementById('statusIndicator').className =
            'status-indicator ' + (active ? 'status-active' : 'status-inactive');
        document.getElementById('statusText').textContent = text;
    }

    function updateStats() {
        document.getElementById('totalScans').textContent   = stats.total;
        document.getElementById('successScans').textContent = stats.success;
        document.getElementById('errorScans').textContent   = stats.errors;
    }

    function onScanSuccess(decodedText) {
        // Re-check event end time at scan moment
        const sel    = document.getElementById('eventSelect');
        const endTs  = parseInt(sel.options[sel.selectedIndex].dataset.end, 10);
        const nowTs  = Math.floor(Date.now() / 1000);

        if (endTs && endTs < nowTs) {
            stopScanner();
            displayResult({ success: false, message: 'Event has ended — scanning locked.' }, 'error');
            stats.errors++;
            updateStats();
            return;
        }

        stats.total++;
        updateStats();
        updateStatus(true, 'Processing…');

        fetch('process_qr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ qr_data: decodedText, action: getAction() })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { stats.success++; displayResult(data, 'success'); }
            else { stats.errors++; displayResult(data, data.already_checked_in || data.already_checked_out ? 'warning' : 'error'); }
            updateStats();
            updateStatus(true, 'Scanner active — ready to scan');
        })
        .catch(err => {
            stats.errors++;
            updateStats();
            displayResult({ success: false, message: 'Network error: ' + err.message }, 'error');
            updateStatus(true, 'Scanner active — ready to scan');
        });

        html5QrCode.pause();
        setTimeout(() => { if (isScanning) html5QrCode.resume(); }, 2000);
    }

    function displayResult(data, type) {
        const emptyState = document.getElementById('emptyState');
        if (emptyState) emptyState.style.display = 'none';

        const container = document.getElementById('resultContainer');
        const icons     = { success: 'check-circle', warning: 'alert-triangle', error: 'alert-circle' };
        const card      = document.createElement('div');
        card.className  = 'result-card result-' + type;

        let html = `
            <div class="result-header">
                <div class="result-icon">
                    <i data-lucide="${icons[type]}" style="width:20px;height:20px;"></i>
                </div>
                <div>
                    <p class="result-title">${data.message}</p>
                    <p class="result-time">${new Date().toLocaleTimeString()}</p>
                </div>
            </div>`;

        if (data.registration) {
            const r    = data.registration;
            const name = [r.first_name, r.middle_name, r.last_name].filter(Boolean).join(' ');
            html += `<div class="user-info">
                <div class="info-item"><div class="info-label">Attendee</div><div class="info-value">${name}</div></div>
                <div class="info-item"><div class="info-label">Email</div><div class="info-value">${r.email}</div></div>
                <div class="info-item"><div class="info-label">Event</div><div class="info-value">${r.event_title}</div></div>`;
            if (data.check_in_time)  html += `<div class="info-item"><div class="info-label">Checked in</div><div class="info-value">${new Date(data.check_in_time).toLocaleTimeString()}</div></div>`;
            if (data.check_out_time) html += `<div class="info-item"><div class="info-label">Checked out</div><div class="info-value">${new Date(data.check_out_time).toLocaleTimeString()}</div></div>`;
            html += `</div>`;
        }

        card.innerHTML = html;
        container.insertBefore(card, container.firstChild);
        lucide.createIcons();

        while (container.children.length > 5) container.removeChild(container.lastChild);
    }

    window.addEventListener('beforeunload', () => { if (isScanning) stopScanner(); });
    </script>
</body>
</html>