<?php
/**
 * Login Page with Smart OTP Authentication
 * OTP only required for: new devices OR suspicious activity
 */
session_start();

$just_logged_out = isset($_SESSION['just_logged_out']);
if ($just_logged_out) unset($_SESSION['just_logged_out']);

if (isset($_SESSION['user_id']) && !$just_logged_out) {
    header("Location: ../dashboard/home.php");
    exit();
}

require_once('../../includes/db.php');
require_once('../../includes/otp_function.php');
require_once('../../includes/device_recognition.php');
require_once('../../includes/login_attempt_logger.php');
require_once('../../includes/permission_functions.php');

$error = "";
$email_value = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password_input = $_POST['password'];
    $remember = isset($_POST['remember']);
    $email_value = htmlspecialchars($email);

    if (empty($email) || empty($password_input)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, password, first_name, middle_name, last_name, phone, role FROM user WHERE email = ? AND status = 'active'");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($user_id, $hashed_password, $first_name, $middle_name, $last_name, $phone, $role);
                $stmt->fetch();
                if (password_verify($password_input, $hashed_password)) {
                    $stmt->close();
                    $is_trusted  = isTrustedDevice($conn, $user_id);
                    $is_suspicious = isSuspiciousLogin($conn, $user_id);
                    $require_otp = !$is_trusted || $is_suspicious;
                    if ($require_otp) {
                        if (!canRequestOTP($conn, $email)) {
                            $error = "Too many OTP requests. Please wait before trying again.";
                        } else {
                            $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
                            $_SESSION['pending_login'] = ['user_id'=>$user_id,'full_name'=>$full_name,'email'=>$email,'phone'=>$phone,'role'=>$role,'remember'=>$remember,'timestamp'=>time()];
                            $otp_result = createOTP($conn, $email, $phone, $user_id, 'login');
                            if ($otp_result) {
                                $delivery = sendOTPDual($email, $phone, $otp_result['otp_code'], $full_name);
                                if ($delivery['email'] || $delivery['sms']) {
                                    $_SESSION['otp_id'] = $otp_result['otp_id'];
                                    logLoginAttempt($conn, $user_id, $email, 1);
                                    header("Location: verify_otp.php?type=login");
                                    exit();
                                } else { $error = "Failed to send OTP. Please try again."; }
                            } else { $error = "Failed to generate OTP. Please try again."; }
                        }
                    } else {
                        $_SESSION['user_id']    = $user_id;
                        $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
                        $_SESSION['full_name']  = $full_name;
                        $role_name = getUserRole($conn, $user_id);
                        $_SESSION['role']       = $role_name;
                        $_SESSION['role_name']  = $role_name;
                        $_SESSION['email']      = $email;
                        $_SESSION['login_time'] = time();
                        if ($remember) trustDevice($conn, $user_id, 30);
                        logLoginAttempt($conn, $user_id, $email, 1);
                        header($role === 'admin' ? "Location: ../admin/admin_dashboard.php" : "Location: ../dashboard/home.php");
                        exit();
                    }
                } else {
                    logLoginAttempt($conn, $user_id, $email, 0);
                    $error = "Incorrect password. Please try again.";
                }
            } else { $error = "No account found with this email address."; }
            if ($stmt) $stmt->close();
        } else { $error = "An error occurred. Please try again later."; }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Eventix</title>
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
        <h2 class="auth-brand-title">Welcome Back to CCF Alabang</h2>
        <p class="auth-brand-subtitle">Sign in to register for events, track your attendance, and stay connected with your community.</p>
        <div class="auth-brand-pills">
            <span class="auth-brand-pill"><i data-lucide="calendar" style="width:13px;height:13px;"></i> Events</span>
            <span class="auth-brand-pill"><i data-lucide="users" style="width:13px;height:13px;"></i> Community</span>
            <span class="auth-brand-pill"><i data-lucide="qr-code" style="width:13px;height:13px;"></i> QR Check-in</span>
        </div>
    </div>

    <div class="auth-brand-dots">
        <span class="auth-brand-dot active"></span>
        <span class="auth-brand-dot"></span>
        <span class="auth-brand-dot"></span>
        <span class="auth-brand-dot"></span>
    </div>

    <div class="auth-brand-quote">
        <p>"As iron sharpens iron, so one person sharpens another." — Prov. 27:17</p>
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
            <h2>Welcome Back!</h2>
            <p>Sign in to your <strong>Eventix</strong> account</p>

            <?php if ($just_logged_out): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width:17px;height:17px;"></i>
                    You have been successfully logged out.
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" style="width:17px;height:17px;"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form" autocomplete="on">
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           value="<?= $email_value ?>"
                           placeholder="you@example.com"
                           required autofocus autocomplete="email">
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password"
                               required autocomplete="current-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('password','eye-icon')">
                            <svg id="eye-icon" data-lucide="eye"></svg>
                        </button>
                    </div>
                </div>

                <div class="options">
                    <label class="remember-label">
                        <input type="checkbox" name="remember" class="remember-checkbox">
                        <span>Remember this device (30 days)</span>
                    </label>
                    <a href="forgot_password.php">Forgot password?</a>
                </div>

                <button type="submit" class="auth-button">
                    <i data-lucide="log-in" style="width:17px;height:17px;"></i>
                    Sign In
                </button>

                <div class="auth-notice">
                    <i data-lucide="info" style="width:15px;height:15px;flex-shrink:0;margin-top:1px;"></i>
                    <span><strong>Smart OTP:</strong> Verification only required for new or unrecognised devices.</span>
                </div>
            </form>

            <div class="auth-link">
                Don't have an account? <a href="register.php">Create one</a>
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

function togglePassword(fieldId, iconId) {
    const field = document.getElementById(fieldId);
    const icon  = document.getElementById(iconId);
    if (field.type === 'password') {
        field.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
    } else {
        field.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
    }
    lucide.createIcons();
}

document.querySelector('.auth-form').addEventListener('submit', function() {
    const btn = document.querySelector('.auth-button');
    btn.classList.add('loading');
    btn.disabled = true;
});

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