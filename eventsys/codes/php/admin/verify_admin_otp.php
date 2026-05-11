<?php
/**
 * Admin OTP Verification Page
 * MODIFIED: Adds device trust after successful OTP
 */
session_start();

require_once('../../includes/db.php');
require_once('../../includes/otp_function.php');
require_once('../../includes/device_recognition.php');

if (!isset($_SESSION['pending_admin_login'])) {
    header("Location: admin-login.php");
    exit();
}

$error = "";
$resend_message = "";
$email = $_SESSION['pending_admin_login']['email'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
    $otp_input = trim($_POST['otp_code'] ?? '');

    if (empty($otp_input)) {
        $error = "Please enter the OTP code.";
    } elseif (strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
        $error = "Please enter a valid 6-digit OTP code.";
    } else {
        $verification = verifyOTP($conn, $email, $otp_input, 'login');

        if ($verification['success']) {
            $login_data = $_SESSION['pending_admin_login'];
            unset($_SESSION['pending_admin_login']);
            unset($_SESSION['otp_id']);

            $_SESSION['user_id']          = $login_data['user_id'];
            $_SESSION['full_name']        = $login_data['full_name'];
            $_SESSION['role']             = 'admin';
            $_SESSION['email']            = $login_data['email'];
            $_SESSION['login_time']       = time();
            $_SESSION['is_admin_portal']  = true;

            if ($login_data['remember']) {
                trustDevice($conn, $login_data['user_id'], 30);
            }

            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error = $verification['message'];
        }
    }
}

if (isset($_POST['resend_otp'])) {
    if (!canRequestOTP($conn, $email)) {
        $resend_message = "Please wait before requesting another OTP.";
    } else {
        $user_data = $_SESSION['pending_admin_login'];
        $otp_result = createOTP($conn, $email, $user_data['phone'], null, 'admin_login');

        if ($otp_result) {
            $delivery = sendOTPDual($email, $user_data['phone'], $otp_result['otp_code'], $user_data['full_name']);
            if ($delivery['email'] || $delivery['sms']) {
                $_SESSION['otp_id'] = $otp_result['otp_id'];
                $resend_message = "New OTP code has been sent!";
            } else {
                $resend_message = "Failed to send OTP. Please try again.";
            }
        } else {
            $resend_message = "Failed to generate OTP. Please try again.";
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
    <title>Admin Verification — Eventix</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/auth.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* ── Full page centering ── */
        html, body {
            margin: 0; padding: 0;
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }

        body.auth-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d0010 100%);
            padding: 24px;
        }

        .otp-wrap {
            width: 100%;
            max-width: 440px;
        }

        .otp-card {
            background: white;
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.4);
            text-align: center;
        }

        .otp-logo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            margin-bottom: 16px;
        }

        .otp-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 8px;
        }

        .otp-card p {
            color: #6b6b6b;
            font-size: 0.9rem;
            margin: 0 0 24px;
            line-height: 1.5;
        }

        .admin-badge {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #e63946;
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .otp-input-wrap { margin-bottom: 20px; text-align: left; }
        .otp-input-wrap label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        .otp-input-wrap input {
            width: 100%;
            padding: 14px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1.8rem;
            letter-spacing: 12px;
            font-weight: 700;
            text-align: center;
            font-family: 'Poppins', sans-serif;
            color: #1a1a1a;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .otp-input-wrap input:focus {
            outline: none;
            border-color: #800020;
        }

        .btn-verify {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #800020, #5a0016);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
            margin-bottom: 12px;
        }
        .btn-verify:hover { background: linear-gradient(135deg, #5a0016, #400010); transform: translateY(-1px); }

        .btn-resend {
            width: 100%;
            padding: 12px;
            background: transparent;
            color: #800020;
            border: 2px solid #800020;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
        }
        .btn-resend:hover { background: #800020; color: white; }

        .otp-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 16px;
            text-align: left;
        }
        .otp-alert.error   { background: #fee2e2; color: #991b1b; }
        .otp-alert.success { background: #d1fae5; color: #065f46; }

        .back-link {
            display: block;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #6b6b6b;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: #800020; }
    </style>
</head>
<body class="auth-page">

<div class="otp-wrap">
    <div class="otp-card">
        <img src="../../assets/eventix-logo.png" alt="Eventix" class="otp-logo">

        <div class="admin-badge">
            <i data-lucide="shield" style="width:15px;height:15px;"></i>
            Admin Security
        </div>

        <h2>Verify Your Identity</h2>
        <p>We've sent a 6-digit code to:<br><strong><?= htmlspecialchars($email) ?></strong></p>

        <?php if (!empty($error)): ?>
            <div class="otp-alert error">
                <i data-lucide="alert-circle" style="width:17px;height:17px;flex-shrink:0;"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($resend_message)): ?>
            <div class="otp-alert success">
                <i data-lucide="check-circle" style="width:17px;height:17px;flex-shrink:0;"></i>
                <?= htmlspecialchars($resend_message) ?>
            </div>
        <?php endif; ?>

        <!-- Verify form -->
        <form method="POST" action="">
            <div class="otp-input-wrap">
                <label for="otp_code">Enter 6-Digit Code</label>
                <input type="text" id="otp_code" name="otp_code"
                       maxlength="6" pattern="[0-9]{6}"
                       placeholder="000000" required autofocus
                       oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            </div>
            <button type="submit" name="verify_otp" class="btn-verify">
                <i data-lucide="shield-check" style="width:17px;height:17px;vertical-align:middle;margin-right:6px;"></i>
                Verify & Access Dashboard
            </button>
        </form>

        <!-- Resend form -->
        <form method="POST" action="">
            <button type="submit" name="resend_otp" class="btn-resend">
                Resend OTP Code
            </button>
        </form>

        <a href="admin-login.php" class="back-link">← Back to Admin Login</a>
    </div>
</div>

<script>
lucide.createIcons();

const otpInput = document.getElementById('otp_code');
otpInput.addEventListener('input', function() {
    if (this.value.length === 6) {
        // Auto-submit if needed — uncomment below
        // this.form.submit();
    }
});

setTimeout(() => {
    document.querySelectorAll('.otp-alert').forEach(a => {
        a.style.opacity = '0';
        a.style.transform = 'translateY(-10px)';
        a.style.transition = 'all 0.3s';
        setTimeout(() => a.remove(), 300);
    });
}, 5000);
</script>

</body>
</html>