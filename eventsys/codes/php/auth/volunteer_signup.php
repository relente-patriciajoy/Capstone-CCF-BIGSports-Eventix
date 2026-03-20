<?php
/**
 * Public volunteer signup page — accessed via QR code scan
 * URL: volunteer_signup.php?token=XXXX
 */
session_start();
require_once('../../includes/db.php');

$token   = trim($_GET['token'] ?? '');
$message = '';
$error   = '';
$joined  = false;

// Find the event by token
$ev = $conn->prepare("SELECT * FROM volunteer_event WHERE qr_token = ?");
$ev->bind_param("s", $token);
$ev->execute();
$event = $ev->get_result()->fetch_assoc();
$ev->close();

if (!$event) {
    die('<div style="font-family:Poppins,sans-serif;text-align:center;padding:60px 20px;color:#6b7280;">
        <h2>Invalid QR Code</h2><p>This volunteer event link is not valid or has expired.</p>
        <a href="../auth/index.php" style="color:#800020;font-weight:700;">Go to Login</a>
    </div>');
}

// If not logged in, save token to session and redirect to login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['volunteer_redirect'] = '../auth/volunteer_signup.php?token=' . $token;
    header("Location: ../auth/index.php?volunteer=1");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch roles for this event
$rq = $conn->prepare("
    SELECT vrt.role_type_id, vrt.role_name,
           CONCAT(u.first_name,' ',u.last_name) AS lead_name,
           (SELECT COUNT(*) FROM volunteer_member vm WHERE vm.role_type_id = vrt.role_type_id) AS member_count
    FROM volunteer_role_type vrt
    LEFT JOIN user u ON vrt.team_lead_id = u.user_id
    WHERE vrt.volunteer_event_id = ?
");
$rq->bind_param("i", $event['volunteer_event_id']);
$rq->execute();
$roles = $rq->get_result()->fetch_all(MYSQLI_ASSOC);
$rq->close();

// Check if already signed up
$already = $conn->prepare("
    SELECT vm.volunteer_member_id, vrt.role_name
    FROM volunteer_member vm
    JOIN volunteer_role_type vrt ON vm.role_type_id = vrt.role_type_id
    WHERE vm.user_id = ? AND vrt.volunteer_event_id = ?
");
$already->bind_param("ii", $user_id, $event['volunteer_event_id']);
$already->execute();
$existing = $already->get_result()->fetch_assoc();
$already->close();

// Handle signup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join'])) {
    $role_type_id = (int)$_POST['role_type_id'];

    if ($existing) {
        $error = "You're already signed up for this event as a " . ucfirst($existing['role_name']) . " volunteer.";
    } else {
        // Verify role belongs to this event
        $vchk = $conn->prepare("SELECT role_type_id FROM volunteer_role_type WHERE role_type_id = ? AND volunteer_event_id = ?");
        $vchk->bind_param("ii", $role_type_id, $event['volunteer_event_id']);
        $vchk->execute();
        if ($vchk->get_result()->num_rows === 0) {
            $error = "Invalid role selected.";
        } else {
            $ins = $conn->prepare("INSERT INTO volunteer_member (role_type_id, user_id, status) VALUES (?,?,'confirmed')");
            $ins->bind_param("ii", $role_type_id, $user_id);
            if ($ins->execute()) {
                $joined  = true;
                $message = "You've successfully signed up as a volunteer!";
                // Refresh existing
                $existing = ['role_name' => ''];
                foreach ($roles as $r) {
                    if ($r['role_type_id'] == $role_type_id) {
                        $existing['role_name'] = $r['role_name'];
                        break;
                    }
                }
            } else {
                $error = "Failed to sign up. Please try again.";
            }
            $ins->close();
        }
        $vchk->close();
    }
}
$conn->close();

$role_labels = ['ushering' => 'Ushering', 'admin' => 'Admin', 'technical' => 'Technical'];
$role_colors = ['ushering' => '#3b82f6', 'admin' => '#f59e0b', 'technical' => '#8b5cf6'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Sign Up — <?= htmlspecialchars($event['title']) ?></title>
    <link rel="icon" type="image/png" href="../../assets/eventix-logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/auth.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="auth-page" style="display:block;background:#f9fafb;min-height:100vh;padding:24px 16px;">

<div style="max-width:520px;margin:0 auto;">

    <!-- Header -->
    <div style="text-align:center;margin-bottom:24px;">
        <img src="../../assets/eventix-logo.png" alt="Eventix"
             style="width:56px;height:56px;border-radius:50%;margin-bottom:12px;">
        <h1 style="font-size:1.6rem;font-weight:900;color:#1a1a1a;margin:0 0 4px;">Volunteer Sign Up</h1>
        <p style="color:#6b7280;font-size:0.9rem;margin:0;">Join the team for this event</p>
    </div>

    <!-- Event info card -->
    <div style="background:white;border-radius:16px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.07);margin-bottom:20px;border-left:4px solid #800020;">
        <h2 style="font-size:1.2rem;font-weight:800;color:#1a1a1a;margin:0 0 8px;"><?= htmlspecialchars($event['title']) ?></h2>
        <div style="display:flex;flex-direction:column;gap:6px;font-size:0.88rem;color:#6b7280;">
            <span><i data-lucide="calendar" style="width:14px;height:14px;vertical-align:middle;margin-right:6px;"></i><?= date('F j, Y · g:i A', strtotime($event['event_date'])) ?></span>
            <?php if ($event['location']): ?>
            <span><i data-lucide="map-pin" style="width:14px;height:14px;vertical-align:middle;margin-right:6px;"></i><?= htmlspecialchars($event['location']) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($event['description']): ?>
            <p style="margin:12px 0 0;font-size:0.88rem;color:#374151;line-height:1.6;"><?= nl2br(htmlspecialchars($event['description'])) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($joined): ?>
        <!-- Success state -->
        <div style="background:white;border-radius:16px;padding:32px 24px;box-shadow:0 4px 20px rgba(0,0,0,0.07);text-align:center;">
            <div style="width:72px;height:72px;background:#d1fae5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i data-lucide="check-circle" style="width:36px;height:36px;color:#059669;"></i>
            </div>
            <h3 style="font-size:1.3rem;font-weight:800;margin:0 0 8px;color:#1a1a1a;">You're In!</h3>
            <p style="color:#6b7280;font-size:0.9rem;line-height:1.6;">
                You've successfully joined as a <strong><?= ucfirst($existing['role_name']) ?></strong> volunteer for <strong><?= htmlspecialchars($event['title']) ?></strong>.
            </p>
            <a href="../dashboard/home.php"
               style="display:inline-flex;align-items:center;gap:8px;margin-top:20px;padding:12px 24px;background:linear-gradient(135deg,#800020,#5a0016);color:white;border-radius:50px;font-weight:700;text-decoration:none;font-size:0.9rem;">
                <i data-lucide="home" style="width:16px;height:16px;"></i> Go to Dashboard
            </a>
        </div>

    <?php elseif ($existing): ?>
        <!-- Already signed up -->
        <div style="background:#fef3c7;border-radius:16px;padding:20px 24px;border-left:4px solid #f59e0b;text-align:center;">
            <p style="color:#92400e;font-weight:600;margin:0;">
                You're already signed up as a <strong><?= ucfirst($existing['role_name']) ?></strong> volunteer for this event.
            </p>
            <a href="../dashboard/home.php" style="display:inline-flex;align-items:center;gap:8px;margin-top:16px;color:#800020;font-weight:700;text-decoration:none;">
                ← Back to Dashboard
            </a>
        </div>

    <?php else: ?>
        <!-- Role selection form -->
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i data-lucide="alert-circle" style="width:16px;height:16px;"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div style="background:white;border-radius:16px;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,0.07);">
            <h3 style="font-size:1rem;font-weight:700;margin:0 0 16px;color:#1a1a1a;">Choose your volunteer role:</h3>

            <form method="POST" action="">
                <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
                    <?php foreach ($roles as $r):
                        $color = $role_colors[$r['role_name']] ?? '#6b7280';
                        $label = $role_labels[$r['role_name']] ?? ucfirst($r['role_name']);
                    ?>
                    <label style="display:flex;align-items:center;gap:14px;padding:14px 16px;border:2px solid #e5e7eb;border-radius:12px;cursor:pointer;transition:border-color 0.2s;"
                           onmouseover="this.style.borderColor='<?= $color ?>'"
                           onmouseout="this.style.borderColor='#e5e7eb'">
                        <input type="radio" name="role_type_id" value="<?= $r['role_type_id'] ?>" required
                               style="width:18px;height:18px;accent-color:<?= $color ?>;">
                        <div style="flex:1;">
                            <div style="font-weight:700;color:#1a1a1a;font-size:0.95rem;"><?= $label ?></div>
                            <?php if ($r['lead_name']): ?>
                                <div style="font-size:0.8rem;color:#6b7280;margin-top:2px;">
                                    Team Lead: <?= htmlspecialchars($r['lead_name']) ?>
                                    · <?= $r['member_count'] ?> volunteer(s) so far
                                </div>
                            <?php endif; ?>
                        </div>
                        <span style="background:<?= $color ?>20;color:<?= $color ?>;font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:20px;text-transform:uppercase;">
                            <?= $label ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" name="join"
                        style="width:100%;padding:14px;background:linear-gradient(135deg,#800020,#5a0016);color:white;border:none;border-radius:50px;font-weight:800;font-size:0.95rem;cursor:pointer;font-family:'Poppins',sans-serif;display:flex;align-items:center;justify-content:center;gap:8px;">
                    <i data-lucide="user-plus" style="width:17px;height:17px;"></i>
                    Join as Volunteer
                </button>
            </form>
        </div>
    <?php endif; ?>

    <p style="text-align:center;margin-top:20px;font-size:0.82rem;color:#9ca3af;">
        Signed in as <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></strong> ·
        <a href="../auth/logout.php" style="color:#800020;">Not you?</a>
    </p>
</div>

<script>
lucide.createIcons();
</script>
</body>
</html>