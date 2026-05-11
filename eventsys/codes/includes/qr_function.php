<?php
/**
 * QR Code Functions for Eventix System
 * Handles QR code generation and validation for event check-in
 */

require_once __DIR__ . '/../libraries/phpqrcode/qrlib.php';

/**
 * Generate QR Code for event registration
 * 
 * @param int $registration_id Registration ID
 * @param int $user_id User ID
 * @param int $event_id Event ID
 * @param mysqli $conn Database connection
 * @return string|false Returns QR code filename on success, false on failure
 */
function generateRegistrationQR($registration_id, $user_id, $event_id, $conn) {
    // Create QR codes directory if it doesn't exist
    $qr_dir = __DIR__ . '/../qr_codes/';
    if (!file_exists($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }
    
    // Generate unique token for this registration
    $token = bin2hex(random_bytes(32));
    
    // Store token in database
    $stmt = $conn->prepare("UPDATE registration SET qr_token = ? WHERE registration_id = ?");
    $stmt->bind_param("si", $token, $registration_id);
    
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $stmt->close();
    
    // Create QR code data (JSON format for security)
    $qr_data = json_encode([
        'reg_id' => $registration_id,
        'user_id' => $user_id,
        'event_id' => $event_id,
        'token' => $token,
        'timestamp' => time()
    ]);
    
    // Generate filename
    $filename = "qr_reg_{$registration_id}.png";
    $filepath = $qr_dir . $filename;
    
    // Generate QR Code
    // Parameters: data, filename, error correction level (L/M/Q/H), size, margin
    QRcode::png($qr_data, $filepath, QR_ECLEVEL_H, 10, 2);
    
    return $filename;
}

/**
 * Validate QR code and extract registration data
 * 
 * @param string $qr_data QR code scanned data
 * @param mysqli $conn Database connection
 * @return array Returns validation result with registration details
 */
function validateQRCode($qr_data, $conn) {
    // Decode QR data
    $data = json_decode($qr_data, true);
    
    if (!$data || !isset($data['reg_id']) || !isset($data['token'])) {
        return [
            'valid' => false,
            'message' => 'Invalid QR code format.'
        ];
    }
    
    $registration_id = $data['reg_id'];
    $token = $data['token'];
    $event_id = $data['event_id'];
    
    // Verify registration exists and token matches
    $stmt = $conn->prepare("
        SELECT r.registration_id, r.user_id, r.event_id, r.status, r.qr_token,
               u.first_name, u.middle_name, u.last_name, u.email,
               e.title as event_title, e.start_time, e.end_time
        FROM registration r
        JOIN user u ON r.user_id = u.user_id
        JOIN event e ON r.event_id = e.event_id
        WHERE r.registration_id = ? AND r.qr_token = ?
    ");
    
    $stmt->bind_param("is", $registration_id, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return [
            'valid' => false,
            'message' => 'Registration not found or QR code is invalid.'
        ];
    }
    
    $registration = $result->fetch_assoc();
    $stmt->close();
    
    // Check if registration is confirmed
    if ($registration['status'] !== 'confirmed') {
        return [
            'valid' => false,
            'message' => 'Registration is not confirmed. Status: ' . $registration['status']
        ];
    }
    
    // Check if event is today or in the future
    $event_date = strtotime($registration['start_time']);
    $today = strtotime(date('Y-m-d'));
    
    if ($event_date < $today) {
        return [
            'valid' => false,
            'message' => 'This event has already ended.'
        ];
    }
    
    // Check if already checked in
    $check_stmt = $conn->prepare("
        SELECT attendance_id, check_in_time, check_out_time, status
        FROM attendance
        WHERE registration_id = ?
    ");
    $check_stmt->bind_param("i", $registration_id);
    $check_stmt->execute();
    $attendance_result = $check_stmt->get_result();
    
    $attendance_status = null;
    if ($attendance_result->num_rows > 0) {
        $attendance = $attendance_result->fetch_assoc();
        $attendance_status = $attendance;
    }
    $check_stmt->close();
    
    return [
        'valid' => true,
        'registration' => $registration,
        'attendance' => $attendance_status,
        'message' => 'QR code is valid.'
    ];
}

/**
 * Process check-in using QR code
 * 
 * @param string $qr_data QR code scanned data
 * @param mysqli $conn Database connection
 * @return array Returns check-in result
 */
function processCheckIn($qr_data, $conn) {
    $validation = validateQRCode($qr_data, $conn);
    
    if (!$validation['valid']) {
        return $validation;
    }
    
    $registration_id = $validation['registration']['registration_id'];
    $user_name = trim($validation['registration']['first_name'] . ' ' . 
                      $validation['registration']['middle_name'] . ' ' . 
                      $validation['registration']['last_name']);
    
    // Check if already checked in
    if ($validation['attendance'] && $validation['attendance']['check_in_time']) {
        return [
            'success' => false,
            'already_checked_in' => true,
            'message' => $user_name . ' has already checked in at ' . 
                        date('g:i A', strtotime($validation['attendance']['check_in_time'])),
            'registration' => $validation['registration'],
            'attendance' => $validation['attendance']
        ];
    }
    
    // Perform check-in
    $stmt = $conn->prepare("
        INSERT INTO attendance (registration_id, check_in_time, status)
        VALUES (?, NOW(), 'present')
        ON DUPLICATE KEY UPDATE check_in_time = NOW(), status = 'present'
    ");
    
    $stmt->bind_param("i", $registration_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Get updated attendance record with server timestamp
        $check_stmt = $conn->prepare("
            SELECT attendance_id, check_in_time, status, DATE_FORMAT(NOW(), '%Y-%m-%dT%H:%i:%s') as server_time
            FROM attendance
            WHERE registration_id = ?
        ");
        $check_stmt->bind_param("i", $registration_id);
        $check_stmt->execute();
        $attendance = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        return [
            'success' => true,
            'message' => 'Successfully checked in ' . $user_name,
            'registration' => $validation['registration'],
            'attendance' => $attendance,
            'check_in_time' => $attendance['check_in_time'],
            'server_time' => $attendance['server_time']
        ];
    } else {
        $stmt->close();
        return [
            'success' => false,
            'message' => 'Failed to process check-in. Please try again.'
        ];
    }
}

/**
 * Process check-out using QR code
 * 
 * @param string $qr_data QR code scanned data
 * @param mysqli $conn Database connection
 * @return array Returns check-out result
 */
function processCheckOut($qr_data, $conn) {
    $validation = validateQRCode($qr_data, $conn);
    
    if (!$validation['valid']) {
        return $validation;
    }
    
    $registration_id = $validation['registration']['registration_id'];
    $user_name = trim($validation['registration']['first_name'] . ' ' . 
                      $validation['registration']['middle_name'] . ' ' . 
                      $validation['registration']['last_name']);
    
    // Check if checked in
    if (!$validation['attendance'] || !$validation['attendance']['check_in_time']) {
        return [
            'success' => false,
            'message' => $user_name . ' has not checked in yet.'
        ];
    }
    
    // Check if already checked out
    if ($validation['attendance']['check_out_time']) {
        return [
            'success' => false,
            'already_checked_out' => true,
            'message' => $user_name . ' has already checked out at ' . 
                        date('g:i A', strtotime($validation['attendance']['check_out_time'])),
            'registration' => $validation['registration'],
            'attendance' => $validation['attendance']
        ];
    }
    
    // Perform check-out
    $stmt = $conn->prepare("
        UPDATE attendance 
        SET check_out_time = NOW()
        WHERE registration_id = ?
    ");
    
    $stmt->bind_param("i", $registration_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Get updated attendance record with server timestamp
        $check_stmt = $conn->prepare("
            SELECT attendance_id, check_in_time, check_out_time, status, DATE_FORMAT(NOW(), '%Y-%m-%dT%H:%i:%s') as server_time
            FROM attendance
            WHERE registration_id = ?
        ");
        $check_stmt->bind_param("i", $registration_id);
        $check_stmt->execute();
        $attendance = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        return [
            'success' => true,
            'message' => 'Successfully checked out ' . $user_name,
            'registration' => $validation['registration'],
            'attendance' => $attendance,
            'check_out_time' => $attendance['check_out_time'],
            'server_time' => $attendance['server_time']
        ];
    } else {
        $stmt->close();
        return [
            'success' => false,
            'message' => 'Failed to process check-out. Please try again.'
        ];
    }
}

/**
 * Get QR code file path for display
 * 
 * @param int $registration_id Registration ID
 * @return string|false Returns relative path to QR code or false if not found
 */
function getQRCodePath($registration_id) {
    $filename = "qr_reg_{$registration_id}.png";
    $filepath = __DIR__ . '/../qr_codes/' . $filename;
    
    if (file_exists($filepath)) {
        return '../../qr_codes/' . $filename;
    }
    
    return false;
}

/**
 * Delete QR code file
 * 
 * @param int $registration_id Registration ID
 * @return bool Returns true if deleted, false otherwise
 */
function deleteQRCode($registration_id) {
    $filename = "qr_reg_{$registration_id}.png";
    $filepath = __DIR__ . '/../qr_codes/' . $filename;
    
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    
    return false;
}
?>