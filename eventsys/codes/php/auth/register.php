<?php
/**
 * Registration Page - Step 1: Collect user information and send OTP
 */
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/home.php");
    exit();
}

require_once('../../includes/db.php');
require_once('../../includes/otp_function.php');

$errors    = [];
$form_data = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name      = trim($_POST['first_name'] ?? '');
    $middle_name     = trim($_POST['middle_name'] ?? '');
    $last_name       = trim($_POST['last_name'] ?? '');
    $email           = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone           = trim($_POST['phone'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $form_data = [
        'first_name'  => htmlspecialchars($first_name),
        'middle_name' => htmlspecialchars($middle_name),
        'last_name'   => htmlspecialchars($last_name),
        'email'       => htmlspecialchars($email),
        'phone'       => htmlspecialchars($phone),
    ];

    if (empty($first_name))  $errors[] = "First name is required.";
    if (empty($last_name))   $errors[] = "Last name is required.";
    if (empty($email))       $errors[] = "Email address is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid email address.";
    if (empty($phone))       $errors[] = "Phone number is required.";
    elseif (!preg_match('/^[0-9\+\-\(\)\s]{10,}$/', $phone)) $errors[] = "Please enter a valid phone number.";
    if (empty($password))    $errors[] = "Password is required.";
    elseif (strlen($password) < 8)              $errors[] = "Password must be at least 8 characters.";
    elseif (!preg_match('/[A-Z]/', $password))  $errors[] = "Password must contain at least one uppercase letter.";
    elseif (!preg_match('/[0-9]/', $password))  $errors[] = "Password must contain at least one number.";
    if ($password !== $confirm_password)         $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $errors[] = "This email address is already registered.";
            $stmt->close();
        }
    }

    if (empty($errors) && !canRequestOTP($conn, $email))
        $errors[] = "Too many OTP requests. Please wait a minute before trying again.";

    if (empty($errors)) {
        $_SESSION['pending_registration'] = [
            'first_name'  => $first_name,
            'middle_name' => $middle_name,
            'last_name'   => $last_name,
            'email'       => $email,
            'phone'       => $phone,
            'password'    => password_hash($password, PASSWORD_DEFAULT),
            'timestamp'   => time(),
        ];
        $otp_result = createOTP($conn, $email, $phone, null, 'registration');
        if ($otp_result) {
            $full_name = trim($first_name . ' ' . $last_name);
            $delivery  = sendOTPDual($email, $phone, $otp_result['otp_code'], $full_name);
            if ($delivery['email'] || $delivery['sms']) {
                $_SESSION['otp_id'] = $otp_result['otp_id'];
                header("Location: verify_otp.php?type=registration");
                exit();
            } else { $errors[] = "Failed to send OTP. Please try again."; }
        } else { $errors[] = "Failed to generate OTP. Please try again."; }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — Eventix</title>
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
        <h2 class="auth-brand-title">Join CCF Alabang</h2>
        <p class="auth-brand-subtitle">Create your account to register for events, track attendance with QR codes, and be part of the community.</p>
        <div class="auth-brand-pills">
            <span class="auth-brand-pill"><i data-lucide="calendar" style="width:13px;height:13px;"></i> Register for Events</span>
            <span class="auth-brand-pill"><i data-lucide="qr-code" style="width:13px;height:13px;"></i> QR Check-in</span>
            <span class="auth-brand-pill"><i data-lucide="shield" style="width:13px;height:13px;"></i> OTP Secured</span>
        </div>
    </div>

    <div class="auth-brand-dots">
        <span class="auth-brand-dot active"></span>
        <span class="auth-brand-dot"></span>
        <span class="auth-brand-dot"></span>
        <span class="auth-brand-dot"></span>
    </div>

    <div class="auth-brand-quote">
        <p>"Two are better than one, because they have a good return for their labor." — Eccl. 4:9</p>
    </div>
</div>

<!-- ── RIGHT FORM PANEL ── -->
<div class="auth-form-panel">
    <div class="auth-container register-container">
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
            <h2>Create Your Account</h2>
            <p>Register with <strong>OTP verification</strong> via SMS and Email</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" style="width:17px;height:17px;flex-shrink:0;margin-top:1px;"></i>
                    <div>
                        <strong>Please fix the following:</strong>
                        <ul>
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form" autocomplete="on">
                <div class="form-row">
                    <div class="input-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name"
                               value="<?= $form_data['first_name'] ?? '' ?>"
                               placeholder="Juan" required>
                    </div>
                    <div class="input-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name"
                               value="<?= $form_data['middle_name'] ?? '' ?>"
                               placeholder="Dela">
                    </div>
                </div>

                <div class="input-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name"
                           value="<?= $form_data['last_name'] ?? '' ?>"
                           placeholder="Cruz" required>
                </div>

                <div class="input-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email"
                           value="<?= $form_data['email'] ?? '' ?>"
                           placeholder="juan.cruz@example.com" required autocomplete="email">
                </div>

                <div class="input-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone"
                           value="<?= $form_data['phone'] ?? '' ?>"
                           placeholder="09123456789" required>
                    <span class="input-hint">Format: 09XXXXXXXXX — used for OTP via SMS</span>
                </div>

                <div class="input-group">
                    <label for="password">Password *</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password"
                               placeholder="Create a strong password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password','eye-pw')">
                            <svg id="eye-pw" data-lucide="eye"></svg>
                        </button>
                    </div>
                    <span class="input-hint">Min. 8 characters, 1 uppercase letter, 1 number</span>
                </div>

                <div class="input-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password"
                               placeholder="Re-enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password','eye-cpw')">
                            <svg id="eye-cpw" data-lucide="eye"></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="auth-button">
                    <i data-lucide="arrow-right" style="width:17px;height:17px;"></i>
                    Continue to Verification
                </button>
            </form>

            <div class="auth-link">
                Already have an account? <a href="index.php">Sign in</a>
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
</script>
</body>
</html>