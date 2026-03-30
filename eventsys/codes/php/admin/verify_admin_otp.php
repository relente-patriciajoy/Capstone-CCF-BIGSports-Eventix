<?php
/**
 * Admin OTP Verification Page
 * MODIFIED: Adds device trust after successful OTP
 */
session_start();

require_once('../../includes/db.php');
require_once('../../includes/otp_function.php');
require_once('../../includes/device_recognition.php');

// Check if there's pending admin login
if (!isset($_SESSION['pending_admin_login'])) {
    header("Location: admin-login.php");
    exit();
}

$error = "";
$resend_message = "";
$email = $_SESSION['pending_admin_login']['email'];

// Handle OTP verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
    $otp_input = trim($_POST['otp_code'] ?? '');

    if (empty($otp_input)) {
        $error = "Please enter the OTP code.";
    } elseif (strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
        $error = "Please enter a valid 6-digit OTP code.";
    } else {
        // Verify OTP - Use 'login' type (works for all login types)
        $verification = verifyOTP($conn, $email, $otp_input, 'login');
        
        if ($verification['success']) {
            $login_data = $_SESSION['pending_admin_login'];
            
            // Clear pending login
            unset($_SESSION['pending_admin_login']);
            unset($_SESSION['otp_id']);
            
            // Set admin session variables
            $_SESSION['user_id'] = $login_data['user_id'];
            $_SESSION['full_name'] = $login_data['full_name'];
            $_SESSION['role'] = 'admin';
            $_SESSION['email'] = $login_data['email'];
            $_SESSION['login_time'] = time();
            $_SESSION['is_admin_portal'] = true; // Flag for admin portal
            
            // ===== TRUST DEVICE AFTER SUCCESSFUL OTP =====
            if ($login_data['remember']) {
                trustDevice($conn, $login_data['user_id'], 30); // Remember for 30 days
            }
            // ===== END TRUST DEVICE =====

            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error = $verification['message'];
        }
    }
}

// Handle resend OTP
if (isset($_POST['resend_otp'])) {
    if (!canRequestOTP($conn, $email)) {
        $resend_message = "Please wait before requesting another OTP.";
    } else {
        $user_data = $_SESSION['pending_admin_login'];
        $name = $user_data['full_name'];
        $phone = $user_data['phone'];

        $otp_result = createOTP($conn, $email, $phone, null, 'admin_login');

        if ($otp_result) {
            $delivery = sendOTPDual($email, $phone, $otp_result['otp_code'], $name);
            
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
    <title>Admin Verification - Eventix</title>
    <link rel="icon" type="image/png" href="../../assets/eventix-logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/auth.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .admin-badge {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #e63946;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body class="auth-page">

<div class="auth-container">
    <div class="auth-box">
        <img src="../../assets/eventix-logo.png" alt="Eventix Logo" class="logo" />
        
        <div class="admin-badge">
            <i data-lucide="shield" style="width: 16px; height: 16px;"></i>
            Admin Security
        </div>

        <h2>Verify Your Identity</h2>
        <p>We've sent a 6-digit code to:<br><strong><?php echo htmlspecialchars($email); ?></strong></p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-circle" style="width: 18px; height: 18px;"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($resend_message)): ?>
            <div class="alert alert-success">
                <i data-lucide="check-circle" style="width: 18px; height: 18px;"></i>
                <?php echo htmlspecialchars($resend_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="auth-form">
            <div class="input-group">
                <label for="otp_code">Enter 6-Digit Code</label>
                <input
                    type="text"
                    id="otp_code"
                    name="otp_code"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    placeholder="000000"
                    required
                    autofocus
                    style="text-align: center; font-size: 1.5rem; letter-spacing: 8px; font-weight: 600;"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                >
            </div>

            <button type="submit" name="verify_otp" class="auth-button">
                Verify & Access Dashboard
            </button>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <p style="color: #6b6b6b; font-size: 0.9rem;">
                Didn't receive the code?
            </p>
            <form method="POST" action="" style="display: inline;">
                <button
                    type="submit"
                    name="resend_otp"
                    class="auth-button button-outline"
                    style="margin-top: 10px;"
                >
                    Resend OTP
                </button>
            </form>
        </div>

        <div class="auth-link" style="margin-top: 30px;">
            <a href="admin-login.php">← Back to Admin Login</a>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();

    const otpInput = document.getElementById('otp_code');
    otpInput.addEventListener('input', function() {
        if (this.value.length === 6) {
            // auto-submit when 6 digits entered
            // this.form.submit();
        }
    });

    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
</script>

</body>
</html>