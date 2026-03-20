<?php
require_once('../../../includes/session.php');
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); exit('Unauthorized');
}

$file = basename($_GET['file'] ?? '');

if (!preg_match('/^volunteer_qr_\d+\.png$/', $file)) {
    http_response_code(400); exit('Invalid file');
}

$filepath = __DIR__ . '/../../../qr_codes/' . $file;

if (!file_exists($filepath)) {
    http_response_code(404); exit('Not found');
}

header('Content-Type: image/png');
header('Content-Length: ' . filesize($filepath));

// Download header if requested
if (isset($_GET['download'])) {
    header('Content-Disposition: attachment; filename="' . $file . '"');
} else {
    header('Content-Disposition: inline; filename="' . $file . '"');
}

header('Cache-Control: public, max-age=3600');
readfile($filepath);