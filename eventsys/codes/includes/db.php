<?php
/**
 * Database Configuration
 * Supports both local XAMPP and Railway (Linux) environments
 * Uses environment variables on Railway, falls back to local defaults
 */

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'event_registration';
$db_port = getenv('DB_PORT') ?: 3306;

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    error_log("DB Connection failed: " . $conn->connect_error);
    die(json_encode(['error' => 'Database connection failed.']));
}

$conn->set_charset("utf8mb4");
?>