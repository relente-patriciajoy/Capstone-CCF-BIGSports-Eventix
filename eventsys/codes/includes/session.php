<?php
/**
 * Session Verification & Timeout Management
 * Uses a guard constant to ensure this file's logic only runs ONCE
 * even if included from multiple files (e.g. home.php AND role_protection.php).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Guard: only execute verification logic once per request ──
if (defined('SESSION_VERIFIED')) {
    // Already verified this request — just expose the variables and exit
    $user_id   = $_SESSION['user_id']   ?? null;
    $email     = $_SESSION['email']     ?? null;
    $full_name = $_SESSION['full_name'] ?? null;
    $user_role = $_SESSION['role']      ?? null;
    return; // stop re-running the checks below
}
define('SESSION_VERIFIED', true);

// ── Absolute path to login page — works from ANY subdirectory ──
$_auth_redirect = '/Registration-System/eventsys/codes/php/auth/index.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    $_SESSION = array();
    session_destroy();
    header("Location: " . $_auth_redirect);
    exit();
}

// Verify essential session variables exist
$required_session_vars = ['user_id', 'email', 'full_name', 'role'];
foreach ($required_session_vars as $var) {
    if (!isset($_SESSION[$var])) {
        session_destroy();
        header("Location: " . $_auth_redirect . "?error=session_invalid");
        exit();
    }
}

// Session timeout (1 hour)
$timeout_duration = 3600;
$current_time     = time();

if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = $current_time;
}

if (($current_time - $_SESSION['login_time']) > $timeout_duration) {
    $_SESSION = array();
    session_destroy();
    header("Location: " . $_auth_redirect . "?error=session_expired");
    exit();
}

$_SESSION['login_time'] = $current_time;

// Verify role is valid
$valid_roles = ['user', 'event_head', 'admin'];
if (!in_array($_SESSION['role'], $valid_roles)) {
    error_log("SECURITY: Invalid role '" . $_SESSION['role'] . "' for user_id=" . $_SESSION['user_id']);
    session_destroy();
    header("Location: " . $_auth_redirect . "?error=invalid_role");
    exit();
}

// Keep role_name in sync — role_protection.php uses role_name,
// auth pages only set role. Sync here so both always exist.
if (!isset($_SESSION['role_name'])) {
    $_SESSION['role_name'] = $_SESSION['role'];
}

$user_id   = $_SESSION['user_id'];
$email     = $_SESSION['email'];
$full_name = $_SESSION['full_name'];
$user_role = $_SESSION['role'];