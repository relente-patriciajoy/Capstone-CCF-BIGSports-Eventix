<?php
/**
 * OTP Verification Page
 */
session_start();

require_once('../../includes/db.php');
require_once __DIR__ . '/../../includes/otp_function.php';
require_once __DIR__ . '/../../includes/device_recognition.php';

$verification_type = $_GET['type'] ?? 'registration';

if ($verification_type === 'registration' && !isset($_SESSION['pending_registration'])) {
    header("Location: register.php"); exit();
} elseif ($verification_type === 'login' && !isset($_SESSION['pending_login'])) {
    header("Location: index.php"); exit();
}

$error          = "";
$resend_message = "";

$email = $verification_type === 'registration'
    ? $_SESSION['pending_registration']['email']
    : $_SESSION['pending_login']['email'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
    $otp_input = trim($_POST['otp_code'] ?? '');
    if (empty($otp_input)) {
        $error = "Please enter the OTP code.";
    } elseif (strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
        $error = "Please enter a valid 6-digit OTP code.";
    } else {
        $verification = verifyOTP($conn, $email, $otp_input, $verification_type);
        if ($verification['success']) {
            if ($verification_type === 'registration') {
                $reg_data = $_SESSION['pending_registration'];
                $stmt = $conn->prepare("INSERT INTO user (first_name, middle_name, last_name, gender, email, phone, password, role, status, email_verified, phone_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 'user', 'active', 1, 1)");
                $stmt->bind_param("sssssss", $reg_data['first_name'], $reg_data['middle_name'], $reg_data['last_name'], $reg_data['gender'], $reg_data['email'], $reg_data['phone'], $reg_data['password']);
                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;
                    unset($_SESSION['pending_registration'], $_SESSION['otp_id']);
                    $_SESSION['user_id']    = $user_id;
                    $_SESSION['full_name']  = trim($reg_data['first_name'] . ' ' . $reg_data['last_name']);
                    $_SESSION['role']       = 'user';
                    $_SESSION['email']      = $reg_data['email'];
                    $_SESSION['login_time'] = time();
                    trustDevice($conn, $user_id, 30);
                    $stmt->close();
                    header("Location: ../dashboard/home.php?welcome=1");
                    exit();
                } else { $error = "Registration failed. Please try again."; }
            } else {
                $login_data = $_SESSION['pending_login'];
                unset($_SESSION['pending_login'], $_SESSION['otp_id']);
                $_SESSION['user_id']    = $login_data['user_id'];
                $_SESSION['full_name']  = $login_data['full_name'];
                $_SESSION['role']       = $login_data['role'];
                $_SESSION['email']      = $login_data['email'];
                $_SESSION['login_time'] = time();
                if ($login_data['remember']) trustDevice($conn, $login_data['user_id'], 30);
                header("Location: ../dashboard/home.php");
                exit();
            }
        } else { $error = $verification['message']; }
    }
}

if (isset($_POST['resend_otp'])) {
    if (!canRequestOTP($conn, $email)) {
        $resend_message = "Please wait before requesting another OTP.";
    } else {
        if ($verification_type === 'registration') {
            $user_data = $_SESSION['pending_registration'];
            $name  = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
            $phone = $user_data['phone'];
        } else {
            $user_data = $_SESSION['pending_login'];
            $name  = $user_data['full_name'];
            $phone = $user_data['phone'];
        }
        $otp_result = createOTP($conn, $email, $phone, null, $verification_type);
        if ($otp_result) {
            $delivery = sendOTPDual($email, $phone, $otp_result['otp_code'], $name);
            if ($delivery['email'] || $delivery['sms']) {
                $_SESSION['otp_id'] = $otp_result['otp_id'];
                $resend_message = "New OTP code sent!";
            } else { $resend_message = "Failed to send OTP. Please try again."; }
        } else { $resend_message = "Failed to generate OTP. Please try again."; }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP — Eventix</title>
    <link rel="icon" type="image/png" href="../../assets/eventix-logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/auth.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="auth-page">

<!-- ── LEFT BRAND PANEL (desktop) ── -->
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
        <h2 class="auth-brand-title">One Last Step</h2>
        <p class="auth-brand-subtitle">We sent a 6-digit code to verify your identity. Check your email and SMS messages.</p>
        <div class="auth-brand-pills">
            <span class="auth-brand-pill"><i data-lucide="mail" style="width:13px;height:13px;"></i> Email</span>
            <span class="auth-brand-pill"><i data-lucide="smartphone" style="width:13px;height:13px;"></i> SMS</span>
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
        <p>Code expires in 5 minutes. Check your spam folder if not received.</p>
    </div>
</div>

<!-- ── RIGHT FORM PANEL ── -->
<div class="auth-form-panel">
    <div class="auth-container">
        <div class="auth-box">

            <!-- Mobile brand strip -->
            <div class="auth-mobile-brand">
                <img src="../../assets/eventix-logo.png" alt="Eventix">
                <div class="auth-mobile-brand-text">
                    <span class="auth-mobile-brand-name">CCF Alabang</span>
                    <span class="auth-mobile-brand-sub">Eventix</span>
                </div>
            </div>

            <img src="../../assets/eventix-logo.png" alt="Eventix Logo" class="auth-logo">
            <h2>Verify Your Identity</h2>
            <p>We sent a 6-digit code to<br><strong><?= htmlspecialchars($email) ?></strong></p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" style="width:17px;height:17px;"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($resend_message)): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width:17px;height:17px;"></i>
                    <?= htmlspecialchars($resend_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form" id="otpForm">
                <div class="input-group">
                    <label>Enter 6-Digit Code</label>
                    <!-- Individual digit boxes — better mobile UX -->
                    <div class="otp-input-row">
                        <input type="tel" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" autofocus>
                        <input type="tel" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="tel" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="tel" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="tel" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="tel" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    </div>
                    <!-- Hidden field that gets combined value -->
                    <input type="hidden" name="otp_code" id="otp_code">
                </div>

                <button type="submit" name="verify_otp" class="auth-button" id="verifyBtn" disabled>
                    <i data-lucide="check-circle" style="width:17px;height:17px;"></i>
                    Verify Code
                </button>
            </form>

            <div class="otp-resend">
                <p>Didn't receive the code?</p>
                <form method="POST" action="">
                    <button type="submit" name="resend_otp" class="auth-button button-outline">
                        <i data-lucide="refresh-cw" style="width:15px;height:15px;"></i>
                        Resend OTP
                    </button>
                </form>
            </div>

            <div class="otp-note">
                <strong>Note:</strong> OTP expires in 5 minutes. Check your spam folder if not received.
            </div>

            <div class="auth-link">
                <a href="<?= $verification_type === 'registration' ? 'register.php' : 'index.php' ?>">
                    ← Back to <?= $verification_type === 'registration' ? 'Registration' : 'Login' ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
lucide.createIcons();

// ── Left panel image carousel ──
(function() {
    const slides = document.querySelectorAll('.auth-brand-slide');
    const dots   = document.querySelectorAll('.auth-brand-dot');
    if (!slides.length) return;
    let cur = 0;
    function goTo(idx) {
        slides[cur].classList.remove('active');
        dots[cur].classList.remove('active');
        cur = (idx + slides.length) % slides.length;
        slides[cur].classList.add('active');
        dots[cur].classList.add('active');
    }
    dots.forEach((d, i) => d.addEventListener('click', () => goTo(i)));
    setInterval(() => goTo(cur + 1), 4500);
})();

// ── OTP digit-box logic ──
const digitBoxes = document.querySelectorAll('.otp-digit');
const hiddenInput = document.getElementById('otp_code');
const verifyBtn   = document.getElementById('verifyBtn');

function updateHidden() {
    const val = Array.from(digitBoxes).map(d => d.value).join('');
    hiddenInput.value = val;
    verifyBtn.disabled = val.length < 6;
    digitBoxes.forEach(d => d.classList.toggle('filled', d.value !== ''));
}

digitBoxes.forEach((box, idx) => {
    box.addEventListener('input', function() {
        // Allow only digits
        this.value = this.value.replace(/[^0-9]/g, '').slice(-1);
        updateHidden();
        // Move to next box
        if (this.value && idx < digitBoxes.length - 1) {
            digitBoxes[idx + 1].focus();
        }
    });

    box.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && !this.value && idx > 0) {
            digitBoxes[idx - 1].focus();
            digitBoxes[idx - 1].value = '';
            updateHidden();
        }
    });

    // Handle paste (e.g. from SMS)
    box.addEventListener('paste', function(e) {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
        pasted.split('').slice(0, 6).forEach((char, i) => {
            if (digitBoxes[i]) digitBoxes[i].value = char;
        });
        updateHidden();
        const lastFilled = Math.min(pasted.length, digitBoxes.length - 1);
        digitBoxes[lastFilled].focus();
    });
});

// Auto-submit when all 6 filled
document.getElementById('otpForm').addEventListener('submit', function() {
    verifyBtn.classList.add('loading');
    verifyBtn.disabled = true;
});

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        a.style.transition = 'opacity 0.3s, transform 0.3s';
        a.style.opacity = '0';
        a.style.transform = 'translateY(-8px)';
        setTimeout(() => a.remove(), 320);
    });
}, 5000);
</script>
</body>
</html>