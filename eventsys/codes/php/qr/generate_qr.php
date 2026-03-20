<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
/**
 * QR Code Generation
 * Generates QR code after successful registration
 */

require_once('../../includes/db.php');
require_once('../../includes/qr_function.php');

/**
 * Generate QR code for a registration
 *
 * @param int $registration_id
 * @param int $user_id
 * @param int $event_id
 * @param mysqli $conn
 * @return bool
 */
function generateQRForRegistration($registration_id, $user_id, $event_id, $conn) {
    $qr_filename = generateRegistrationQR($registration_id, $user_id, $event_id, $conn);

    if ($qr_filename) {
        $stmt = $conn->prepare("UPDATE registration SET qr_code = ? WHERE registration_id = ?");
        $stmt->bind_param("si", $qr_filename, $registration_id);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    return false;
}

// If called directly (for manual generation)
if (isset($_GET['registration_id'])) {
    if (!isset($_SESSION['user_id'])) {
        die("Unauthorized");
    }

    $registration_id = $_GET['registration_id'];
    $user_id         = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT event_id FROM registration WHERE registration_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $registration_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($event_id);

    if ($stmt->fetch()) {
        $stmt->close();
        if (generateQRForRegistration($registration_id, $user_id, $event_id, $conn)) {
            header("Location: view_qr.php?reg_id=" . $registration_id);
        } else {
            echo "Failed to generate QR code.";
        }
    } else {
        $stmt->close();
        echo "Invalid registration.";
    }
}
?>