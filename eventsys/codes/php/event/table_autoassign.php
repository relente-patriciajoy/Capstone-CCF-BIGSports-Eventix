<?php
/**
 * Table auto-assignment function
 * Include this in event_register.php
 * Call: autoAssignTable($conn, $event_id, $user_id, $registration_id)
 */

function autoAssignTable($conn, $event_id, $user_id, $registration_id) {
    // Check if event uses table management
    $ev = $conn->prepare("SELECT has_tables, gender_separated FROM event WHERE event_id = ?");
    $ev->bind_param("i", $event_id);
    $ev->execute();
    $event = $ev->get_result()->fetch_assoc();
    $ev->close();

    if (!$event || !$event['has_tables']) {
        return null; // No table management for this event
    }

    // Get user gender
    $ug = $conn->prepare("SELECT gender FROM user WHERE user_id = ?");
    $ug->bind_param("i", $user_id);
    $ug->execute();
    $user = $ug->get_result()->fetch_assoc();
    $ug->close();

    $gender = $user['gender'] ?? null;
    // If gender is unknown and event is gender-separated, assign to mixed or first available
    if (!$gender) $gender = 'mixed';

    // Find the first table with available space that matches gender
    // Rule: fill table completely before moving to next (no empty seats)
    if ($event['gender_separated']) {
        // Gender-separated: find tables matching user's gender that aren't full yet
        // Prioritize the table with the most occupants (fill before opening next)
        $tq = $conn->prepare("
            SELECT et.table_number, et.capacity,
                   COUNT(r.registration_id) AS occupants
            FROM event_table et
            LEFT JOIN registration r ON r.event_id = ? AND r.table_number = et.table_number
            WHERE et.event_id = ? AND et.gender_assignment = ?
            GROUP BY et.table_id
            HAVING occupants < et.capacity
            ORDER BY occupants DESC, et.table_number ASC
            LIMIT 1
        ");
        $tq->bind_param("iis", $event_id, $event_id, $gender);
    } else {
        // Mixed: fill any table with space, most occupied first
        $tq = $conn->prepare("
            SELECT et.table_number, et.capacity,
                   COUNT(r.registration_id) AS occupants
            FROM event_table et
            LEFT JOIN registration r ON r.event_id = ? AND r.table_number = et.table_number
            WHERE et.event_id = ?
            GROUP BY et.table_id
            HAVING occupants < et.capacity
            ORDER BY occupants DESC, et.table_number ASC
            LIMIT 1
        ");
        $tq->bind_param("ii", $event_id, $event_id);
    }

    $tq->execute();
    $table = $tq->get_result()->fetch_assoc();
    $tq->close();

    if (!$table) {
        return null; // All tables full
    }

    // Assign the table
    $upd = $conn->prepare("UPDATE registration SET table_number = ? WHERE registration_id = ?");
    $upd->bind_param("ii", $table['table_number'], $registration_id);
    $upd->execute();
    $upd->close();

    return $table['table_number'];
}