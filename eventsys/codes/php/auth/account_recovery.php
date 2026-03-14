<?php
/**
 * Account Recovery — for users who forgot their email address
 * Submits a request to the admin for manual review
 */
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/home.php");
    exit();
}

require_once('../../includes/db.php');

$error   = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $message   = trim($_POST['message'] ?? '');

    $name_pattern = '/^[a-zA-Z\s\-\'\.]+$/';

    if (empty($full_name)) {
        $error = "Full name is required.";
    } elseif (!preg_match($name_pattern, $full_name)) {
        $error = "Name must contain letters only.";
    } elseif (empty($phone)) {
        $error = "Phone number is required.";
    } elseif (!preg_match('/^09[0-9]{9}$/', $phone)) {
        $error = "Phone number must be 11 digits and start with 09.";
    } elseif (empty($message) || strlen($message) < 20) {
        $error = "Please provide more detail (at least 20 characters) to help the admin identify your account.";
    } else {
        $stmt = $conn->prepare("INSERT INTO account_recovery_request (full_name, phone, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $full_name, $phone, $message);
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "Failed to submit request. Please try again.";
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery — Eventix</title>
    <link rel="icon" type="image/png" href="../../assets/eventix-logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/auth.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="auth-page">

<!-- ── LEFT BRAND PANEL ── -->
<div class="auth-brand">
    <div class="auth-brand-tint"></div>
    <div class="auth-brand-slides">
        <div class="auth-brand-slide active" style="background-image:url('../../assets/highlights/soccer-sport.jpg')"></div>
        <div class="auth-brand-slide" style="background-image:url('../../assets/highlights/volleyball-sport.jpg')"></div>
        <div class="auth-brand-slide" style="background-image:url('../../assets/highlights/badminton-sport.jpg')"></div>
        <div class="auth-brand-slide" style="background-image:url('../../assets/highlights/pickleball-sport.jpg')"></div>
    </div>
    <div class="auth-brand-overlay"></div>
    <div class="auth-brand-pattern"></div>
    <div class="auth-brand-content">
        <img src="../../assets/eventix-logo.png" alt="Eventix" class="auth-brand-logo">
        <h2 class="auth-brand-title">Need Help?</h2>
        <p class="auth-brand-subtitle">Submit a recovery request and our admin team will locate your account and get back to you.</p>
        <div class="auth-brand-pills">
            <span class="auth-brand-pill"><i data-lucide="users" style="width:13px;height:13px;"></i> Admin Review</span>
            <span class="auth-brand-pill"><i data-lucide="clock" style="width:13px;height:13px;"></i> Within 24hrs</span>
        </div>
    </div>
    <div class="auth-brand-dots">
        <span class="auth-brand-dot active"></span>
        <span class="auth-brand-dot"></span>
        <span class="auth-brand-dot"></span>
        <span class="auth-brand-dot"></span>
    </div>
    <div class="auth-brand-quote">
        <p>"Ask and it will be given to you; seek and you will find." — Matt. 7:7</p>
    </div>
</div>

<!-- ── RIGHT FORM PANEL ── -->
<div class="auth-form-panel">
    <div class="auth-container">
        <div class="auth-box">

            <div class="auth-mobile-brand">
                <img src="../../assets/eventix-logo.png" alt="Eventix">
                <div class="auth-mobile-brand-text">
                    <span class="auth-mobile-brand-name">CCF Alabang</span>
                    <span class="auth-mobile-brand-sub">Eventix</span>
                </div>
            </div>

            <img src="../../assets/eventix-logo.png" alt="Eventix Logo" class="auth-logo">

            <?php if ($success): ?>
                <!-- Success state -->
                <div class="recovery-success">
                    <div class="recovery-success-icon">
                        <i data-lucide="check-circle" style="width:40px;height:40px;"></i>
                    </div>
                    <h2>Request Submitted!</h2>
                    <p>Your account recovery request has been sent to the admin team. We'll reach out to the phone number you provided within 24 hours.</p>
                    <div class="auth-notice" style="margin-top:1rem;">
                        <i data-lucide="info" style="width:15px;height:15px;flex-shrink:0;"></i>
                        <span>If you remember your email in the meantime, you can still use <a href="forgot_password.php" style="color:var(--red);font-weight:600;">Forgot Password</a>.</span>
                    </div>
                    <a href="index.php" class="auth-button" style="margin-top:1.4rem;display:flex;">
                        <i data-lucide="arrow-left" style="width:17px;height:17px;"></i>
                        Back to Login
                    </a>
                </div>

            <?php else: ?>
                <h2>Account Recovery</h2>
                <p>Forgot your email address? Fill in the details below and the admin will locate your account and contact you.</p>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i data-lucide="alert-circle" style="width:17px;height:17px;"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="auth-form">
                    <div class="input-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name"
                               placeholder="Juan Dela Cruz"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                               required oninput="this.value=this.value.replace(/[^a-zA-Z\s\-'\.]/g,'')">
                        <span class="input-hint">Enter your full name as registered</span>
                    </div>

                    <div class="input-group">
                        <label for="phone">Registered Phone Number *</label>
                        <input type="tel" id="phone" name="phone"
                               placeholder="09123456789"
                               maxlength="11"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                               required oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)">
                        <span class="input-hint">The phone number you used when registering</span>
                    </div>

                    <div class="input-group">
                        <label for="message">Additional Details *</label>
                        <textarea id="message" name="message"
                                  rows="4"
                                  placeholder="Describe any details that can help the admin identify your account — e.g. events you joined, when you registered, your table number, etc."
                                  required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        <span class="input-hint">The more detail you provide, the faster the admin can locate your account</span>
                    </div>

                    <button type="submit" class="auth-button">
                        <i data-lucide="send" style="width:17px;height:17px;"></i>
                        Submit Recovery Request
                    </button>
                </form>

                <div class="auth-link" style="margin-top:1rem;">
                    Remembered your email? <a href="forgot_password.php">Reset password instead</a>
                </div>
                <div class="auth-link">
                    <a href="index.php">← Back to Login</a>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
lucide.createIcons();
(function() {
    const slides = document.querySelectorAll('.auth-brand-slide');
    const dots   = document.querySelectorAll('.auth-brand-dot');
    if (!slides.length) return;
    let cur = 0;
    function goTo(i) {
        slides[cur].classList.remove('active'); dots[cur].classList.remove('active');
        cur = (i + slides.length) % slides.length;
        slides[cur].classList.add('active'); dots[cur].classList.add('active');
    }
    dots.forEach((d, i) => d.addEventListener('click', () => goTo(i)));
    setInterval(() => goTo(cur + 1), 4500);
})();
const form = document.querySelector('.auth-form');
if (form) form.addEventListener('submit', function() {
    const btn = document.querySelector('.auth-button');
    if (btn) { btn.classList.add('loading'); btn.disabled = true; }
});
</script>
</body>
</html>