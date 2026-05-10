<?php
/**
 * Table auto-assignment function
 * Updated: uses num_tables and seats_per_table from event table directly
 * No longer depends on separate event_table records
 * Call: autoAssignTable($conn, $event_id, $user_id, $registration_id)
 */

function autoAssignTable($conn, $event_id, $user_id, $registration_id) {
    // Get event table settings
    $ev = $conn->prepare("SELECT has_tables, gender_separated, num_tables, seats_per_table FROM event WHERE event_id = ?");
    $ev->bind_param("i", $event_id);
    $ev->execute();
    $event = $ev->get_result()->fetch_assoc();
    $ev->close();

    if (!$event || !$event['has_tables'] || !$event['num_tables']) {
        return null; // No table management or no tables configured
    }

    $num_tables      = (int)$event['num_tables'];
    $seats_per_table = $event['seats_per_table'] ? (int)$event['seats_per_table'] : null; // null = no limit
    $gender_separated = $event['gender_separated'];

    // Get user gender
    $ug = $conn->prepare("SELECT gender FROM user WHERE user_id = ?");
    $ug->bind_param("i", $user_id);
    $ug->execute();
    $user = $ug->get_result()->fetch_assoc();
    $ug->close();

    $gender = $user['gender'] ?? null;

    // Determine table range based on gender separation
    // If gender separated: first half = male, second half = female
    $start_table = 1;
    $end_table   = $num_tables;

    if ($gender_separated && $gender) {
        $half = (int)ceil($num_tables / 2);
        if ($gender === 'male') {
            $start_table = 1;
            $end_table   = $half;
        } else {
            $start_table = $half + 1;
            $end_table   = $num_tables;
        }
    }

    // Find table with most occupants that still has space (fill-first algorithm)
    // Loop through table numbers in range
    $best_table    = null;
    $best_occupants = -1;

    for ($t = $start_table; $t <= $end_table; $t++) {
        // Count occupants in this table
        $cq = $conn->prepare("SELECT COUNT(*) as cnt FROM registration WHERE event_id = ? AND table_number = ?");
        $cq->bind_param("ii", $event_id, $t);
        $cq->execute();
        $cnt_row = $cq->get_result()->fetch_assoc();
        $cq->close();
        $occupants = (int)($cnt_row['cnt'] ?? 0);

        // Check if table has space
        $has_space = $seats_per_table === null || $occupants < $seats_per_table;

        if ($has_space && $occupants > $best_occupants) {
            $best_table     = $t;
            $best_occupants = $occupants;
        }
    }

    if ($best_table === null) {
        // All tables full — try any table if gender separated and no space in gender tables
        if ($gender_separated) {
            for ($t = 1; $t <= $num_tables; $t++) {
                $cq = $conn->prepare("SELECT COUNT(*) as cnt FROM registration WHERE event_id = ? AND table_number = ?");
                $cq->bind_param("ii", $event_id, $t);
                $cq->execute();
                $cnt_row = $cq->get_result()->fetch_assoc();
                $cq->close();
                $occupants = (int)($cnt_row['cnt'] ?? 0);
                $has_space = $seats_per_table === null || $occupants < $seats_per_table;
                if ($has_space && $occupants > $best_occupants) {
                    $best_table     = $t;
                    $best_occupants = $occupants;
                }
            }
        }
    }

    if ($best_table === null) {
        return null; // All tables truly full
    }

    // Assign the table
    $upd = $conn->prepare("UPDATE registration SET table_number = ? WHERE registration_id = ?");
    $upd->bind_param("ii", $best_table, $registration_id);
    $upd->execute();
    $upd->close();

    return $best_table;
}
?>