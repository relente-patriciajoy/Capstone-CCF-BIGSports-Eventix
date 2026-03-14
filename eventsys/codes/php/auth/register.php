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
    $first_name       = trim($_POST['first_name'] ?? '');
    $middle_name      = trim($_POST['middle_name'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $gender           = trim($_POST['gender'] ?? '');
    $email            = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone            = trim($_POST['phone'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $form_data = [
        'first_name'  => htmlspecialchars($first_name),
        'middle_name' => htmlspecialchars($middle_name),
        'last_name'   => htmlspecialchars($last_name),
        'gender'      => $gender,
        'email'       => htmlspecialchars($email),
        'phone'       => htmlspecialchars($phone),
    ];

    // ── Name validation — letters, spaces, hyphens only ──
    $name_pattern = '/^[a-zA-Z\s\-\'\.]+$/';

    if (empty($first_name)) {
        $errors[] = "First name is required.";
    } elseif (!preg_match($name_pattern, $first_name)) {
        $errors[] = "First name must contain letters only (no numbers).";
    }

    if (!empty($middle_name) && !preg_match($name_pattern, $middle_name)) {
        $errors[] = "Middle name must contain letters only (no numbers).";
    }

    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    } elseif (!preg_match($name_pattern, $last_name)) {
        $errors[] = "Last name must contain letters only (no numbers).";
    }

    // ── Gender ──
    if (empty($gender) || !in_array($gender, ['male', 'female'])) {
        $errors[] = "Please select a gender.";
    }

    // ── Email ──
    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // ── Phone — exactly 11 digits, starts with 09 ──
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match('/^09[0-9]{9}$/', $phone)) {
        $errors[] = "Phone number must be 11 digits and start with 09 (e.g. 09123456789).";
    }

    // ── Password ──
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // ── Email uniqueness check ──
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

    // ── OTP rate limit ──
    if (empty($errors) && !canRequestOTP($conn, $email)) {
        $errors[] = "Too many OTP requests. Please wait a minute before trying again.";
    }

    // ── All good — store in session and send OTP ──
    if (empty($errors)) {
        $_SESSION['pending_registration'] = [
            'first_name'  => $first_name,
            'middle_name' => $middle_name,
            'last_name'   => $last_name,
            'gender'      => $gender,
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

                <!-- Name row -->
                <div class="form-row">
                    <div class="input-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name"
                               value="<?= $form_data['first_name'] ?? '' ?>"
                               placeholder="Juan" required
                               autocomplete="given-name"
                               oninput="stripNumbers(this)">
                    </div>
                    <div class="input-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name"
                               value="<?= $form_data['middle_name'] ?? '' ?>"
                               placeholder="Dela"
                               autocomplete="additional-name"
                               oninput="stripNumbers(this)">
                    </div>
                </div>

                <div class="input-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name"
                           value="<?= $form_data['last_name'] ?? '' ?>"
                           placeholder="Cruz" required
                           autocomplete="family-name"
                           oninput="stripNumbers(this)">
                </div>

                <!-- Gender -->
                <div class="input-group">
                    <label>Gender *</label>
                    <div class="gender-row">
                        <label class="gender-option <?= ($form_data['gender'] ?? '') === 'male' ? 'selected' : '' ?>">
                            <input type="radio" name="gender" value="male"
                                   <?= ($form_data['gender'] ?? '') === 'male' ? 'checked' : '' ?> required>
                            <span class="gender-icon">
                                <i data-lucide="user" style="width:16px;height:16px;"></i>
                            </span>
                            <span>Male</span>
                        </label>
                        <label class="gender-option <?= ($form_data['gender'] ?? '') === 'female' ? 'selected' : '' ?>">
                            <input type="radio" name="gender" value="female"
                                   <?= ($form_data['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                            <span class="gender-icon">
                                <i data-lucide="user" style="width:16px;height:16px;"></i>
                            </span>
                            <span>Female</span>
                        </label>
                    </div>
                </div>

                <!-- Email -->
                <div class="input-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email"
                           value="<?= $form_data['email'] ?? '' ?>"
                           placeholder="juan.cruz@example.com" required
                           autocomplete="email">
                </div>

                <!-- Phone -->
                <div class="input-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone"
                           value="<?= $form_data['phone'] ?? '' ?>"
                           placeholder="09123456789"
                           maxlength="11"
                           pattern="09[0-9]{9}"
                           required
                           oninput="enforcePhone(this)">
                    <span class="input-hint">11 digits starting with 09 — used for OTP via SMS</span>
                </div>

                <!-- Password with strength meter -->
                <div class="input-group">
                    <label for="password">Password *</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password"
                               placeholder="Create a strong password"
                               required oninput="checkStrength(this.value)">
                        <button type="button" class="password-toggle" onclick="togglePassword('password','eye-pw')">
                            <svg id="eye-pw" data-lucide="eye"></svg>
                        </button>
                    </div>
                    <!-- Password strength meter -->
                    <div class="pw-strength" id="pw-strength">
                        <div class="pw-strength-bar">
                            <div class="pw-strength-fill" id="pw-strength-fill"></div>
                        </div>
                        <div class="pw-strength-row">
                            <span class="pw-strength-label" id="pw-strength-label"></span>
                            <span class="pw-strength-hint" id="pw-strength-hint"></span>
                        </div>
                        <ul class="pw-rules" id="pw-rules">
                            <li class="pw-rule" id="rule-len">
                                <span class="pw-rule-dot"></span> At least 8 characters
                            </li>
                            <li class="pw-rule" id="rule-upper">
                                <span class="pw-rule-dot"></span> One uppercase letter
                            </li>
                            <li class="pw-rule" id="rule-num">
                                <span class="pw-rule-dot"></span> One number
                            </li>
                            <li class="pw-rule" id="rule-special">
                                <span class="pw-rule-dot"></span> One special character (bonus)
                            </li>
                        </ul>
                    </div>
                    <span class="input-hint">Min. 8 characters, 1 uppercase, 1 number</span>
                </div>

                <!-- Confirm password -->
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

// ── Left panel carousel ──
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

// ── Toggle password visibility ──
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

// ── Name fields: allow letters, spaces, hyphens, apostrophes, dots only ──
function stripNumbers(input) {
    input.value = input.value.replace(/[^a-zA-Z\s\-'\.]/g, '');
}

// ── Phone: digits only, max 11, must start 09 ──
function enforcePhone(input) {
    let val = input.value.replace(/\D/g, '');
    if (val.length > 11) val = val.slice(0, 11);
    input.value = val;
}

// ── Gender radio visual feedback ──
document.querySelectorAll('input[name="gender"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.gender-option').forEach(opt => opt.classList.remove('selected'));
        this.closest('.gender-option').classList.add('selected');
    });
});

// ── Password strength meter ──
function checkStrength(val) {
    const meter  = document.getElementById('pw-strength');
    const fill   = document.getElementById('pw-strength-fill');
    const label  = document.getElementById('pw-strength-label');
    const hint   = document.getElementById('pw-strength-hint');

    const hasLen     = val.length >= 8;
    const hasUpper   = /[A-Z]/.test(val);
    const hasNum     = /[0-9]/.test(val);
    const hasSpecial = /[^a-zA-Z0-9]/.test(val);
    const isLong     = val.length >= 12;

    // Update rule indicators
    setRule('rule-len',     hasLen);
    setRule('rule-upper',   hasUpper);
    setRule('rule-num',     hasNum);
    setRule('rule-special', hasSpecial);

    if (val.length === 0) {
        meter.classList.remove('visible');
        return;
    }
    meter.classList.add('visible');

    // Determine level
    let level, pct, cls, labelText, hintText;

    if (!hasLen || !hasUpper || !hasNum) {
        level = 'weak';
        pct   = 28;
        cls   = 'weak';
        labelText = 'Weak';
        hintText  = 'Doesn\'t meet the minimum requirements yet.';
    } else if (hasLen && hasUpper && hasNum && (isLong || hasSpecial)) {
        level = 'strong';
        pct   = 100;
        cls   = 'strong';
        labelText = 'Strong';
        hintText  = 'Great password! Well done.';
    } else {
        level = 'average';
        pct   = 62;
        cls   = 'average';
        labelText = 'Average';
        hintText  = 'Add a special character or make it longer for stronger security.';
    }

    fill.style.width = pct + '%';
    fill.className   = 'pw-strength-fill ' + cls;
    label.textContent = labelText;
    label.className   = 'pw-strength-label ' + cls;
    hint.textContent  = hintText;
}

function setRule(id, met) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('met', met);
    el.classList.toggle('unmet', !met);
}

// ── Loading state on submit ──
document.querySelector('.auth-form').addEventListener('submit', function() {
    const btn = document.querySelector('.auth-button');
    btn.classList.add('loading');
    btn.disabled = true;
});
</script>
</body>
</html>