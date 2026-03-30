<?php
/**
 * Reset Password — Step 3
 * Reached after OTP verified for forgot_password flow
 */
session_start();

// Must have completed OTP verification
if (!isset($_SESSION['reset_verified']) || !isset($_SESSION['forgot_user_id'])) {
    header("Location: forgot_password.php");
    exit();
}

require_once('../../includes/db.php');

$error   = "";
$user_id = $_SESSION['forgot_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password)) {
        $error = "Please enter a new password.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt   = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed, $user_id);

        if ($stmt->execute()) {
            // Clean up all reset-related session data
            unset(
                $_SESSION['reset_verified'],
                $_SESSION['forgot_user_id'],
                $_SESSION['forgot_email'],
                $_SESSION['otp_id']
            );
            $stmt->close();
            $conn->close();
            header("Location: index.php?reset=success");
            exit();
        } else {
            $error = "Failed to update password. Please try again.";
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
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Reset Password — Eventix</title>
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
        <h2 class="auth-brand-title">Almost There</h2>
        <p class="auth-brand-subtitle">Create a strong new password that you'll remember. Use at least 8 characters with an uppercase letter and a number.</p>
        <div class="auth-brand-pills">
            <span class="auth-brand-pill"><i data-lucide="lock" style="width:13px;height:13px;"></i> Secure Reset</span>
            <span class="auth-brand-pill"><i data-lucide="check-circle" style="width:13px;height:13px;"></i> Identity Verified</span>
        </div>
    </div>
    <div class="auth-brand-dots">
        <span class="auth-brand-dot active"></span>
        <span class="auth-brand-dot"></span>
        <span class="auth-brand-dot"></span>
        <span class="auth-brand-dot"></span>
    </div>
    <div class="auth-brand-quote">
        <p>"I can do all things through Christ who strengthens me." — Phil. 4:13</p>
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
                <div class="reset-step done">
                    <span class="reset-step-num"><i data-lucide="check" style="width:13px;height:13px;"></i></span>
                    <span class="reset-step-label">Enter Email</span>
                </div>
                <div class="reset-step-line done"></div>
                <div class="reset-step done">
                    <span class="reset-step-num"><i data-lucide="check" style="width:13px;height:13px;"></i></span>
                    <span class="reset-step-label">Verify OTP</span>
                </div>
                <div class="reset-step-line done"></div>
                <div class="reset-step active">
                    <span class="reset-step-num">3</span>
                    <span class="reset-step-label">New Password</span>
                </div>
            </div>

            <h2>Set New Password</h2>
            <p>Your identity has been verified. Choose a strong new password.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" style="width:17px;height:17px;"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <div class="input-group">
                    <label for="password">New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password"
                               placeholder="Create a strong password"
                               required autofocus oninput="checkStrength(this.value)">
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
                            <li class="pw-rule" id="rule-len"><span class="pw-rule-dot"></span> At least 8 characters</li>
                            <li class="pw-rule" id="rule-upper"><span class="pw-rule-dot"></span> One uppercase letter</li>
                            <li class="pw-rule" id="rule-num"><span class="pw-rule-dot"></span> One number</li>
                            <li class="pw-rule" id="rule-special"><span class="pw-rule-dot"></span> One special character (bonus)</li>
                        </ul>
                    </div>
                </div>

                <div class="input-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password"
                               placeholder="Re-enter your new password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password','eye-cpw')">
                            <svg id="eye-cpw" data-lucide="eye"></svg>
                        </button>
                    </div>
                    <span class="pw-match-hint" id="pw-match-hint"></span>
                </div>

                <button type="submit" class="auth-button" id="submitBtn">
                    <i data-lucide="lock" style="width:17px;height:17px;"></i>
                    Save New Password
                </button>
            </form>
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

function togglePassword(fieldId, iconId) {
    const field = document.getElementById(fieldId);
    const icon  = document.getElementById(iconId);
    field.type  = field.type === 'password' ? 'text' : 'password';
    icon.setAttribute('data-lucide', field.type === 'password' ? 'eye' : 'eye-off');
    lucide.createIcons();
}

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

    setRule('rule-len',     hasLen);
    setRule('rule-upper',   hasUpper);
    setRule('rule-num',     hasNum);
    setRule('rule-special', hasSpecial);

    if (!val.length) { meter.classList.remove('visible'); return; }
    meter.classList.add('visible');

    let pct, cls, labelText, hintText;
    if (!hasLen || !hasUpper || !hasNum) {
        pct = 28; cls = 'weak'; labelText = 'Weak';
        hintText = "Doesn't meet the minimum requirements yet.";
    } else if (isLong || hasSpecial) {
        pct = 100; cls = 'strong'; labelText = 'Strong';
        hintText = 'Great password! Well done.';
    } else {
        pct = 62; cls = 'average'; labelText = 'Average';
        hintText = 'Add a special character or make it longer for stronger security.';
    }
    fill.style.width      = pct + '%';
    fill.className        = 'pw-strength-fill ' + cls;
    label.textContent     = labelText;
    label.className       = 'pw-strength-label ' + cls;
    hint.textContent      = hintText;
}

function setRule(id, met) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('met', met);
    el.classList.toggle('unmet', !met);
}

// Live confirm match feedback
document.getElementById('confirm_password').addEventListener('input', function() {
    const pw    = document.getElementById('password').value;
    const hint  = document.getElementById('pw-match-hint');
    if (!this.value) { hint.textContent = ''; hint.className = 'pw-match-hint'; return; }
    if (this.value === pw) {
        hint.textContent = '✓ Passwords match';
        hint.className   = 'pw-match-hint match';
    } else {
        hint.textContent = '✗ Passwords do not match';
        hint.className   = 'pw-match-hint no-match';
    }
});

document.querySelector('.auth-form').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading'); btn.disabled = true;
});
</script>
</body>
</html>