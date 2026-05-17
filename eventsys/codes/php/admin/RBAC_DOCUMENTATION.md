# Event-Level RBAC System Documentation

## Overview

This system implements a **Role-Based Access Control (RBAC)** system for events, allowing admins to control who can access, edit, delete, and manage specific events. **Event creators automatically get full access to their own events.**

## Key Features

### 1. **Automatic Creator Access**
- When an event is created, the organizer automatically receives full permissions
- No manual setup needed - it's automatic!
- Creators can: view, edit, delete, manage attendance, export data

### 2. **Event Access Management**
- Admin panel to set custom permissions for specific events
- Granular permission control per user per event
- Audit trail of who granted what permissions and when

### 3. **Permission Levels**
- `can_view`: View event details and registrations
- `can_edit`: Modify event details
- `can_delete`: Delete the event
- `can_manage_attendance`: Mark attendance, check-in/out
- `can_export_data`: Export event data (CSV, reports)

### 4. **Admin Override**
- Admins can access and modify any event
- Admins can set/change permissions for other users on specific events
- Admins can see the complete audit trail

---

## How to Use

### For Event Creators

1. Create a new event (automatically gets full access)
2. View, edit, and delete your own events without needing to ask admin
3. Manage attendee check-in and export attendance data
4. No additional setup required!

### For Admins

#### Managing Event Access

1. **Go to Admin Dashboard** → **Event Access**
2. **Select an Event** from the dropdown
3. See all users who have access to that event
4. **Edit Permissions** for existing users or **Add New Users**
5. Check the appropriate permission checkboxes
6. Add an optional reason (for audit trail)
7. **Save Permissions**

#### Permission States

| User Type | Permissions | How It Works |
|-----------|-------------|-----------|
| Event Creator | Full (all 5) | Automatic - set when event is created |
| Granted Users | Custom | Set by admin in Event Access page |
| Admins | Full | Always have access to everything |
| Other Users | None | Can't access unless explicitly granted |

---

## Database Tables

### `event_access`
Stores custom permissions for users on specific events.

```sql
Fields:
- access_id: Primary key
- event_id: Reference to event
- user_id: Reference to user
- can_view: Boolean (1/0)
- can_edit: Boolean (1/0)
- can_delete: Boolean (1/0)
- can_manage_attendance: Boolean (1/0)
- can_export_data: Boolean (1/0)
- granted_by: Admin user_id who set the permissions
- granted_at: Timestamp
- reason: Text reason for granting access
```

### `event`
Already has `organizer_id` field which links to the organizer table.

---

## API Functions

All functions are in `/includes/permission_functions.php`

### Check Event Creator
```php
$is_creator = isEventCreator($conn, $user_id, $event_id);
// Returns: true/false
```

### Check Access
```php
$can_edit = canAccessEvent($conn, $user_id, $event_id, 'edit');
// $action: 'view', 'edit', 'delete', 'manage_attendance', 'export_data'
// Returns: true/false
```

### Get All Permissions
```php
$perms = getEventPermissions($conn, $user_id, $event_id);
// Returns array with all permission flags + is_creator
```

### Grant Access
```php
$permissions = [
    'view' => true,
    'edit' => true,
    'delete' => false,
    'manage_attendance' => true,
    'export_data' => true
];
grantEventAccess($conn, $event_id, $user_id, $permissions, $admin_id, 'Reason here');
```

### Auto-Grant Creator Access
```php
// Call this after creating a new event
autoGrantCreatorEventAccess($conn, $event_id, $organizer_id);
```

---

## Integration Steps

### Step 1: Include in Your Event Creation Page

Add this at the top of your event creation file (e.g., in a form handler):

```php
require_once('../../includes/permission_functions.php');
```

### Step 2: Call Auto-Grant After Creating Event

After successfully inserting a new event:

```php
$stmt = $conn->prepare("INSERT INTO event (title, description, organizer_id, ...) VALUES (?, ?, ?, ...)");
// ... bind and execute ...

if ($stmt->execute()) {
    $event_id = $stmt->insert_id;
    $stmt->close();
    
    // AUTO-GRANT PERMISSIONS TO CREATOR
    autoGrantCreatorEventAccess($conn, $event_id, $organizer_id);
    
    // Continue with redirect...
}
```

### Step 3: Add Permission Checks in Your Pages

Before showing event controls, check permissions:

```php
// Check if user can edit this event
if (!canAccessEvent($conn, $_SESSION['user_id'], $event_id, 'edit')) {
    die("You don't have permission to edit this event.");
}

// Or get all perms and use them
$perms = getEventPermissions($conn, $_SESSION['user_id'], $event_id);

if ($perms['can_edit']) {
    // Show edit button
}
if ($perms['can_delete']) {
    // Show delete button
}
```

---

## User Scenarios

### Scenario 1: Event Head Creates an Event
```
1. Event Head creates "Volleyball Tournament"
2. System automatically grants them FULL access
3. They can immediately manage attendees, edit details, etc.
4. No admin action needed!
```

### Scenario 2: Admin Adds Co-Organizer
```
1. Admin goes to Event Access → Select Event
2. Clicks "Add User" and selects the co-organizer
3. Checks: can_view, can_edit, can_manage_attendance
4. Leaves unchecked: can_delete, can_export_data
5. Co-organizer can now manage that specific event
```

### Scenario 3: Admin Restricts Access
```
1. Event organizer has full access
2. Admin can override by going to Event Access
3. Even though they're the creator, admin can limit permissions
4. Permissions in the event_access table override default creator access
```

---

## Security Notes

✅ **What's Protected:**
- Event creators automatically have full access to their events
- Admins can control fine-grained permissions
- All permission changes are logged
- Checks happen on both frontend (UX) and backend (security)

⚠️ **Best Practices:**
- Always check permissions on the server-side before allowing actions
- Frontend checks are for UX only, not security
- Log all sensitive operations (already done via `logPermissionChange()`)
- Regularly audit permissions in the Event Access page

---

## Files Modified/Created

### New Files
- `/admin/manage_event_access.php` - Admin interface for managing event access
- `/admin/EVENT_RBAC_INTEGRATION_GUIDE.php` - Integration guide

### Modified Files
- `/includes/permission_functions.php` - Added `isEventCreator()`, `getEventPermissions()`, `autoGrantCreatorEventAccess()`
- `/admin/admin_sidebar.php` - Added link to Event Access page

---

## Testing the System

1. **Create an event as Event Head**
   - Verify you see your own events in manage_event_access.php
   - You should see "Event Creator" badge

2. **As Admin, grant access to another user**
   - Go to Event Access → Select Event
   - Add a user and grant specific permissions
   - Have that user log in and verify they can only do what was granted

3. **Verify Audit Trail**
   - Check the `event_access` table for the `reason` and `granted_by` fields
   - These record who made the changes

4. **Test Permission Checks**
   - Try accessing an event you shouldn't have access to
   - System should deny access

---

## Troubleshooting

### Issue: Event Creator Can't See Their Events
**Solution:** Make sure `autoGrantCreatorEventAccess()` is called when the event is created

### Issue: Admin Can't Change Permissions
**Solution:** Verify the admin has the `system.settings` permission

### Issue: Permission Changes Not Working
**Solution:** Check that the `event_access` table has the correct columns and no MySQL errors

### Issue: "Event Creator" Badge Not Showing
**Solution:** Ensure the organizer's email matches exactly with a user's email in the system

---

## Future Enhancements

- [ ] Permission templates (e.g., "Co-Organizer", "Staff", "Viewer")
- [ ] Batch permission updates
- [ ] Permission expiration dates
- [ ] Role-based event access templates
- [ ] Delegation of permission management to event creators

