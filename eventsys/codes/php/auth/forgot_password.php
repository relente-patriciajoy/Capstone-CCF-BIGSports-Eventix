<?php
/**
 * Forgot Password — Step 1
 * User enters their registered email, receives a 6-digit OTP
 */
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/home.php");
    exit();
}

require_once('../../includes/db.php');
require_once('../../includes/otp_function.php');

$error   = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check email exists
        $stmt = $conn->prepare("SELECT user_id, first_name, last_name, phone FROM user WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!canRequestOTP($conn, $email)) {
                $error = "Too many requests. Please wait a minute before trying again.";
            } else {
                $full_name  = trim($user['first_name'] . ' ' . $user['last_name']);
                $otp_result = createOTP($conn, $email, $user['phone'], $user['user_id'], 'reset_password');

                if ($otp_result) {
                    $delivery = sendOTPDual($email, $user['phone'], $otp_result['otp_code'], $full_name);

                    if ($delivery['email'] || $delivery['sms']) {
                        $_SESSION['otp_id']            = $otp_result['otp_id'];
                        $_SESSION['forgot_email']      = $email;
                        $_SESSION['forgot_user_id']    = $user['user_id'];
                        header("Location: verify_otp.php?type=forgot_password");
                        exit();
                    } else {
                        $error = "Failed to send OTP. Please try again.";
                    }
                } else {
                    $error = "Failed to generate OTP. Please try again.";
                }
            }
        } else {
            // Intentionally vague — don't reveal whether email exists
            $stmt->close();
            $error = "If this email is registered, you will receive an OTP shortly.";
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Forgot Password — Eventix</title>
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
        <h2 class="auth-brand-title">Reset Your Password</h2>
        <p class="auth-brand-subtitle">Enter your registered email and we'll send you a 6-digit OTP to verify your identity.</p>
        <div class="auth-brand-pills">
            <span class="auth-brand-pill"><i data-lucide="mail" style="width:13px;height:13px;"></i> Email OTP</span>
            <span class="auth-brand-pill"><i data-lucide="shield" style="width:13px;height:13px;"></i> Secure Reset</span>
            <span class="auth-brand-pill"><i data-lucide="clock" style="width:13px;height:13px;"></i> 5 min expiry</span>
        </div>
    </div>
    <div class="auth-brand-dots">
        <span class="auth-brand-dot active"></span>
        <span class="auth-brand-dot"></span>
        <span class="auth-brand-dot"></span>
        <span class="auth-brand-dot"></span>
    </div>
    <div class="auth-brand-quote">
        <p>"He restores my soul." — Psalm 23:3</p>
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

            <!-- Step indicator -->
            <div class="reset-steps">
                <div class="reset-step active">
                    <span class="reset-step-num">1</span>
                    <span class="reset-step-label">Enter Email</span>
                </div>
                <div class="reset-step-line"></div>
                <div class="reset-step">
                    <span class="reset-step-num">2</span>
                    <span class="reset-step-label">Verify OTP</span>
                </div>
                <div class="reset-step-line"></div>
                <div class="reset-step">
                    <span class="reset-step-num">3</span>
                    <span class="reset-step-label">New Password</span>
                </div>
            </div>

            <h2>Forgot Password?</h2>
            <p>Enter your registered email address and we'll send you a verification code.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" style="width:17px;height:17px;"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <div class="input-group">
                    <label for="email">Registered Email Address</label>
                    <input type="email" id="email" name="email"
                           placeholder="you@example.com"
                           required autofocus autocomplete="email">
                </div>

                <button type="submit" class="auth-button">
                    <i data-lucide="send" style="width:17px;height:17px;"></i>
                    Send Verification Code
                </button>
            </form>

            <!-- Forgot email fallback -->
            <div class="auth-divider">or</div>

            <a href="account_recovery.php" class="auth-recovery-link">
                <i data-lucide="help-circle" style="width:15px;height:15px;"></i>
                Forgot your email address? Request account recovery
            </a>

            <div class="auth-link">
                Remembered your password? <a href="index.php">Sign in</a>
            </div>
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
document.querySelector('.auth-form').addEventListener('submit', function() {
    const btn = document.querySelector('.auth-button');
    btn.classList.add('loading'); btn.disabled = true;
});
</script>
</body>
</html>