<?php
/**
 * Permission Functions - RBAC System
 */

/**
 * Check if user has a specific permission
 */
function hasPermission($conn, $user_id, $permission_name) {
    // Check user-specific override first
    $stmt = $conn->prepare("
        SELECT granted 
        FROM user_permission up
        JOIN permission p ON up.permission_id = p.permission_id
        WHERE up.user_id = ? AND p.permission_name = ?
    ");
    $stmt->bind_param("is", $user_id, $permission_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return (bool)$row['granted'];
    }
    $stmt->close();
    
    // Check role-based permissions
    $stmt = $conn->prepare("
        SELECT COUNT(*) as has_permission
        FROM user u
        JOIN role_permission rp ON u.role_id = rp.role_id
        JOIN permission p ON rp.permission_id = p.permission_id
        WHERE u.user_id = ? AND p.permission_name = ?
    ");
    $stmt->bind_param("is", $user_id, $permission_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['has_permission'] > 0;
}

/**
 * Get user's role name
 */
function getUserRole($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT r.role_name 
        FROM user u
        JOIN role r ON u.role_id = r.role_id
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['role_name'];
    }
    $stmt->close();
    return null;
}

/**
 * Check if user is the creator/organizer of an event
 */
function isEventCreator($conn, $user_id, $event_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as is_creator
        FROM event e
        JOIN organizer o ON e.organizer_id = o.organizer_id
        JOIN user u ON o.contact_email = u.email
        WHERE u.user_id = ? AND e.event_id = ?
    ");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['is_creator'] > 0;
}

/**
 * Check if user can access a specific event
 */
function canAccessEvent($conn, $user_id, $event_id, $action = 'view') {
    // Admin can access everything
    if (hasPermission($conn, $user_id, 'system.settings')) {
        return true;
    }
    
    // Check event-specific access FIRST (admin overrides take priority)
    $column_map = [
        'view' => 'can_view',
        'edit' => 'can_edit',
        'delete' => 'can_delete',
        'manage_attendance' => 'can_manage_attendance',
        'export_data' => 'can_export_data'
    ];
    
    if (!isset($column_map[$action])) {
        return false;
    }
    
    $column = $column_map[$action];
    
    $stmt = $conn->prepare("
        SELECT $column 
        FROM event_access 
        WHERE user_id = ? AND event_id = ?
    ");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // User has explicit permissions set - use them (admins can override creators here)
        $stmt->close();
        return (bool)$row[$column];
    }
    $stmt->close();
    
    // If no explicit permissions set, check if user is the event creator
    if (isEventCreator($conn, $user_id, $event_id)) {
        // Creator has full access by default (unless overridden by event_access above)
        return true;
    }
    
    return false;
}

/**
 * Get all permissions for a user on a specific event
 */
function getEventPermissions($conn, $user_id, $event_id) {
    $permissions = [
        'can_view' => false,
        'can_edit' => false,
        'can_delete' => false,
        'can_manage_attendance' => false,
        'can_export_data' => false,
        'is_creator' => false
    ];
    
    // Check if there are explicit permissions in event_access table (admin overrides)
    $stmt = $conn->prepare("
        SELECT can_view, can_edit, can_delete, can_manage_attendance, can_export_data
        FROM event_access
        WHERE user_id = ? AND event_id = ?
    ");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Explicit permissions set - use them (admin override)
        $permissions['can_view'] = (bool)$row['can_view'];
        $permissions['can_edit'] = (bool)$row['can_edit'];
        $permissions['can_delete'] = (bool)$row['can_delete'];
        $permissions['can_manage_attendance'] = (bool)$row['can_manage_attendance'];
        $permissions['can_export_data'] = (bool)$row['can_export_data'];
        $stmt->close();
        return $permissions;
    }
    $stmt->close();
    
    // If no explicit permissions, check if user is the creator
    if (isEventCreator($conn, $user_id, $event_id)) {
        $permissions['is_creator'] = true;
        // Creator has full access by default (unless overridden in event_access above)
        foreach ($permissions as $key => $value) {
            if ($key !== 'is_creator') {
                $permissions[$key] = true;
            }
        }
    }
    
    return $permissions;
}

/**
 * Grant permission to a user
 */
function grantPermission($conn, $user_id, $permission_name, $granted_by, $reason = '') {
    // Get permission ID
    $stmt = $conn->prepare("SELECT permission_id FROM permission WHERE permission_name = ?");
    $stmt->bind_param("s", $permission_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return false;
    }
    
    $row = $result->fetch_assoc();
    $permission_id = $row['permission_id'];
    $stmt->close();
    
    // Insert or update
    $granted = 1;
    $stmt = $conn->prepare("
        INSERT INTO user_permission (user_id, permission_id, granted, granted_by, reason)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            granted = VALUES(granted), 
            granted_by = VALUES(granted_by), 
            granted_at = NOW(), 
            reason = VALUES(reason)
    ");
    $stmt->bind_param("iiiis", $user_id, $permission_id, $granted, $granted_by, $reason);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        logPermissionChange($conn, $user_id, $permission_id, 'grant', $granted_by, $reason);
    }
    
    return $result;
}

/**
 * Revoke permission from a user
 */
function revokePermission($conn, $user_id, $permission_name, $revoked_by, $reason = '') {
    $stmt = $conn->prepare("SELECT permission_id FROM permission WHERE permission_name = ?");
    $stmt->bind_param("s", $permission_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return false;
    }
    
    $row = $result->fetch_assoc();
    $permission_id = $row['permission_id'];
    $stmt->close();
    
    $granted = 0;
    $stmt = $conn->prepare("
        INSERT INTO user_permission (user_id, permission_id, granted, granted_by, reason)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            granted = VALUES(granted), 
            granted_by = VALUES(granted_by), 
            granted_at = NOW(), 
            reason = VALUES(reason)
    ");
    $stmt->bind_param("iiiis", $user_id, $permission_id, $granted, $revoked_by, $reason);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        logPermissionChange($conn, $user_id, $permission_id, 'revoke', $revoked_by, $reason);
    }
    
    return $result;
}

/**
 * Grant event-specific access
 */
function grantEventAccess($conn, $event_id, $user_id, $permissions, $granted_by, $reason = '') {
    $can_view = isset($permissions['view']) && $permissions['view'] ? 1 : 0;
    $can_edit = isset($permissions['edit']) && $permissions['edit'] ? 1 : 0;
    $can_delete = isset($permissions['delete']) && $permissions['delete'] ? 1 : 0;
    $can_manage_attendance = isset($permissions['manage_attendance']) && $permissions['manage_attendance'] ? 1 : 0;
    $can_export_data = isset($permissions['export_data']) && $permissions['export_data'] ? 1 : 0;
    
    // Convert 0 or invalid granted_by to NULL (for system auto-grants)
    $granted_by_value = ($granted_by && $granted_by > 0) ? $granted_by : null;
    
    $stmt = $conn->prepare("
        INSERT INTO event_access (
            event_id, user_id, can_view, can_edit, can_delete, 
            can_manage_attendance, can_export_data, granted_by, reason
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            can_view = VALUES(can_view), 
            can_edit = VALUES(can_edit), 
            can_delete = VALUES(can_delete), 
            can_manage_attendance = VALUES(can_manage_attendance),
            can_export_data = VALUES(can_export_data),
            granted_by = VALUES(granted_by), 
            granted_at = NOW(), 
            reason = VALUES(reason)
    ");
    
    $stmt->bind_param("iiiiiiiis",
        $event_id, $user_id, $can_view, $can_edit, $can_delete, 
        $can_manage_attendance, $can_export_data, $granted_by_value, $reason
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        logPermissionChange($conn, $user_id, null, 'event_access', $granted_by_value, $reason, $event_id);
    }
    
    return $result;
}

/**
 * Auto-grant full access to event creator when event is created
 */
function autoGrantCreatorEventAccess($conn, $event_id, $organizer_id) {
    // Find the user associated with this organizer email
    $stmt = $conn->prepare("
        SELECT u.user_id FROM user u
        JOIN organizer o ON o.contact_email = u.email
        WHERE o.organizer_id = ?
    ");
    $stmt->bind_param("i", $organizer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        return false;
    }
    
    $creator_user_id = $user['user_id'];
    
    // Grant full access to creator (all permissions)
    $permissions = [
        'view' => 1,
        'edit' => 1,
        'delete' => 1,
        'manage_attendance' => 1,
        'export_data' => 1
    ];
    
    // Use NULL for granted_by for system auto-grants
    return grantEventAccess($conn, $event_id, $creator_user_id, $permissions, null, 'Auto-granted to event creator');
}

/**
 * Log permission changes
 */
function logPermissionChange($conn, $user_id, $permission_id, $action, $changed_by, $reason, $event_id = null) {
    // Skip logging if changed_by is null (auto-grants don't need logging)
    if ($changed_by === null) {
        return true;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO permission_audit_log (user_id, permission_id, event_id, action, changed_by, reason)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiisis", $user_id, $permission_id, $event_id, $action, $changed_by, $reason);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get all permissions grouped by category
 */
function getAllPermissions($conn) {
    $permissions = [];
    
    $result = $conn->query("
        SELECT permission_id, permission_name, permission_category, description
        FROM permission
        ORDER BY permission_category, permission_name
    ");
    
    while ($row = $result->fetch_assoc()) {
        if (!isset($permissions[$row['permission_category']])) {
            $permissions[$row['permission_category']] = [];
        }
        $permissions[$row['permission_category']][] = $row;
    }
    
    return $permissions;
}

/**
 * Get all roles
 */
function getAllRoles($conn) {
    $result = $conn->query("SELECT role_id, role_name, description FROM role ORDER BY role_id");
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>