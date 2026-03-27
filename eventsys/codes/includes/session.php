<?php
/**
 * Session Verification & Timeout Management
 * Auto-restores session from trusted device cookie when browser was closed.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Make the session cookie itself last 30 days so it survives browser restarts
    session_set_cookie_params([
        'lifetime' => 30 * 24 * 60 * 60,   // 30 days
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Guard: only run verification logic once per request ──
if (defined('SESSION_VERIFIED')) {
    $user_id   = $_SESSION['user_id']   ?? null;
    $email     = $_SESSION['email']     ?? null;
    $full_name = $_SESSION['full_name'] ?? null;
    $user_role = $_SESSION['role']      ?? null;
    return;
}
define('SESSION_VERIFIED', true);

$_auth_redirect = '/Registration-System/eventsys/codes/php/auth/index.php';

// ── Auto-restore session from trusted device cookie ──
// Runs when: browser was closed / PC shut down and session expired,
// but the 30-day device_token cookie is still present.
if (!isset($_SESSION['user_id']) && isset($_COOKIE['device_token'])) {
    // Lazy-load DB connection if not already available
    if (!isset($conn) || !$conn) {
        require_once(__DIR__ . '/db.php');
    }

    $device_token       = $_COOKIE['device_token'];
    $device_fingerprint = hash('sha256',
        ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') .
        ($_SERVER['REMOTE_ADDR']     ?? 'unknown')
    );

    // Look up the trusted device and join user info in one query
    $restore = $conn->prepare("
        SELECT u.user_id, u.email,
               CONCAT(u.first_name, ' ', COALESCE(NULLIF(u.middle_name,''), ''), ' ', u.last_name) AS full_name,
               u.role
        FROM trusted_device td
        JOIN user u ON td.user_id = u.user_id
        WHERE td.device_token       = ?
          AND td.device_fingerprint = ?
          AND u.status              = 'active'
          AND (td.trusted_until > NOW() OR td.expires_at > NOW())
        LIMIT 1
    ");

    if ($restore) {
        $restore->bind_param("ss", $device_token, $device_fingerprint);
        $restore->execute();
        $row = $restore->get_result()->fetch_assoc();
        $restore->close();

        if ($row) {
            // Rebuild the session exactly as login does
            $_SESSION['user_id']    = $row['user_id'];
            $_SESSION['email']      = $row['email'];
            $_SESSION['full_name']  = trim(preg_replace('/\s+/', ' ', $row['full_name']));
            $_SESSION['role']       = $row['role'];
            $_SESSION['role_name']  = $row['role'];
            $_SESSION['login_time'] = time();

            // Touch last_used on the trusted device record
            $touch = $conn->prepare("UPDATE trusted_device SET last_used = NOW() WHERE device_token = ?");
            if ($touch) {
                $touch->bind_param("s", $device_token);
                $touch->execute();
                $touch->close();
            }
        }
    }
}

// ── Redirect unauthenticated users ──
if (!isset($_SESSION['user_id'])) {
    $_SESSION = array();
    session_destroy();
    header("Location: " . $_auth_redirect);
    exit();
}

// ── Verify all required session variables exist ──
$required_session_vars = ['user_id', 'email', 'full_name', 'role'];
foreach ($required_session_vars as $var) {
    if (!isset($_SESSION[$var])) {
        session_destroy();
        header("Location: " . $_auth_redirect . "?error=session_invalid");
        exit();
    }
}

// ── Session timeout (1 hour of inactivity) ──
$timeout_duration = 3600;
$current_time     = time();

if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = $current_time;
}

if (($current_time - $_SESSION['login_time']) > $timeout_duration) {
    // Before expiring, check if device is still trusted — if so, just refresh
    if (isset($_COOKIE['device_token'])) {
        $_SESSION['login_time'] = $current_time; // refresh — device is trusted
    } else {
        $_SESSION = array();
        session_destroy();
        header("Location: " . $_auth_redirect . "?error=session_expired");
        exit();
    }
}

$_SESSION['login_time'] = $current_time;

// ── Verify role is valid ──
$valid_roles = ['user', 'event_head', 'admin'];
if (!in_array($_SESSION['role'], $valid_roles)) {
    error_log("SECURITY: Invalid role '" . $_SESSION['role'] . "' for user_id=" . $_SESSION['user_id']);
    session_destroy();
    header("Location: " . $_auth_redirect . "?error=invalid_role");
    exit();
}

// ── Keep role_name in sync ──
if (!isset($_SESSION['role_name'])) {
    $_SESSION['role_name'] = $_SESSION['role'];
}

$user_id   = $_SESSION['user_id'];
$email     = $_SESSION['email'];
$full_name = $_SESSION['full_name'];
$user_role = $_SESSION['role'];