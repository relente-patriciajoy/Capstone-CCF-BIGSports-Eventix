<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/index.php");
    exit();
}

include('../../includes/db.php');
require_once('../../includes/qr_function.php');
require_once('table_autoassign.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $user_id  = $_SESSION['user_id'];
    $event_id = (int)$_POST['event_id'];
    $maxCapacity = $_POST['capacity'] ?? 0;

    // ── Block registration if event has already ended ──
    $event_check = $conn->prepare("SELECT end_time, has_tables, requires_registration FROM event WHERE event_id = ?");
    $event_check->bind_param("i", $event_id);
    $event_check->execute();
    $event_check->bind_result($end_time, $has_tables, $requires_registration);
    $event_check->fetch();
    $event_check->close();

    if (!$end_time) {
        $_SESSION['register_status'] = "Event not found.";
        header("Location: ../dashboard/events.php");
        exit();
    }

    // ── Block registration if event is announcement only ──
    if (!$requires_registration) {
        $_SESSION['register_status'] = "This event does not require registration.";
        header("Location: ../dashboard/events.php");
        exit();
    }

    if (strtotime($end_time) < time()) {
        $_SESSION['register_status'] = "Registration is closed. This event has already ended.";
        header("Location: ../dashboard/events.php");
        exit();
    }

    // ── Check if already registered ──
    $check = $conn->prepare("SELECT * FROM registration WHERE user_id = ? AND event_id = ?");
    $check->bind_param("ii", $user_id, $event_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['register_status'] = "You have already registered for this event.";
        header("Location: ../dashboard/events.php");
        exit();
    }

    // ── Determine table number ──
    if ($has_tables) {
        $assignedTable = 0;
    } else {
        $assignedTable = assignTableNumberRandom($conn, $event_id, $maxCapacity);
        if ($assignedTable === null) {
            echo "<script>alert('No available tables for this event. Please try again later.'); window.location.href='../dashboard/events.php';</script>";
            exit();
        }
    }

    // ── Insert registration ──
    $stmt = $conn->prepare("INSERT INTO registration (user_id, event_id, table_number, status) VALUES (?, ?, ?, 'confirmed')");
    $stmt->bind_param("iii", $user_id, $event_id, $assignedTable);

    if ($stmt->execute()) {
        $registration_id = $stmt->insert_id;
        $stmt->close();

        // ── If event uses table management, now assign proper table ──
        if ($has_tables) {
            $assignedTable = autoAssignTable($conn, $event_id, $user_id, $registration_id);
        }

        // ── Generate QR Code ──
        $qr_filename = generateRegistrationQR($registration_id, $user_id, $event_id, $conn);
        if ($qr_filename) {
            $update_stmt = $conn->prepare("UPDATE registration SET qr_code = ? WHERE registration_id = ?");
            $update_stmt->bind_param("si", $qr_filename, $registration_id);
            $update_stmt->execute();
            $update_stmt->close();
        }

        // ── Redirect to QR code page ──
        header("Location: ../qr/view_qr.php?reg_id=" . $registration_id);
        exit();

    } else {
        echo "<script>alert('Registration failed. Please try again.'); window.location.href='../dashboard/events.php';</script>";
    }

} else {
    header("Location: ../dashboard/events.php");
}

// ── Original random table assignment ──
function assignTableNumberRandom($conn, $event_id, $maxCapacity) {
    $maxAttempts = $maxCapacity;
    $attempts    = 0;
    $count       = 0;

    do {
        $randomTable = rand(1, $maxCapacity);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM registration WHERE event_id = ? AND table_number = ?");
        $stmt->bind_param("ii", $event_id, $randomTable);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        $attempts++;
    } while ($count > 0 && $attempts < $maxAttempts);

    return $count == 0 ? $randomTable : null;
}
?>