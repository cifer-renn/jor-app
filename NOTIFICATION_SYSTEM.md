# Notification System

## Overview
The Notification System provides real-time alerts and notifications to users based on their role and system activities. It includes a notification bell in the header, a dedicated notifications page, and automatic notification creation for various system events.

## Features

### **Real-time Notifications**
- **Notification Bell**: Always visible in the header with unread count badge
- **Dropdown Menu**: Quick access to recent unread notifications
- **Auto-refresh**: Notification count updates every 30 seconds
- **Mark as Read**: Click notifications to mark them as read

### **Notification Types**
- **Job Notifications**: Job assignments, completions, and updates
- **Material Notifications**: Material requests, approvals, and rejections
- **System Notifications**: Login alerts, profile updates, and system messages
- **Verification Notifications**: Job verification requirements

### **User Interface**
- **Header Bell**: Notification bell with unread count badge
- **Notifications Page**: Full notification history and management
- **Responsive Design**: Works on all device sizes
- **Visual Indicators**: Different icons and colors for notification types

## Database Schema

### **Notifications Table**
```sql
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

## API Functions

### **Core Functions**
```php
// Create a notification
create_notification($user_id, $type, $title, $message, $link);

// Get unread notifications
$notifications = get_unread_notifications($user_id, $limit);

// Get all notifications
$notifications = get_all_notifications($user_id, $limit);

// Mark notification as read
mark_notification_read($notification_id);

// Mark all notifications as read
mark_all_notifications_read($user_id);

// Get unread count
$count = get_unread_count($user_id);
```

### **Utility Functions**
```php
// Get notification icon
$icon = get_notification_icon($type);

// Get notification color
$color = get_notification_color($type);

// Format notification time
$time = format_notification_time($timestamp);
```

## Notification Types

### **Job Notifications**
- `job_assigned` - New job assigned to operator
- `job_completed` - Job completed by operator
- `job_cancelled` - Job cancelled
- `verification_required` - Job requires verification

### **Material Notifications**
- `material_request` - New material request submitted
- `material_approved` - Material request approved
- `material_rejected` - Material request rejected

### **System Notifications**
- `system_alert` - System-wide alerts
- `profile_update` - Profile information updated
- `password_change` - Password changed
- `login_alert` - Login activity

## Usage Examples

### **Creating Notifications**
```php
// Job assignment notification
create_notification(
    $operator_id,
    'job_assigned',
    'New Job Assigned',
    'You have been assigned job: ' . $job_title,
    'pages/view_operator_job.php?id=' . $job_id
);

// Material request notification
create_notification(
    $warehouse_manager_id,
    'material_request',
    'New Material Request',
    'A new material request requires your attention.',
    'pages/manage_materials.php'
);
```

### **Role-based Notifications**
```php
// Create notifications based on user role
create_role_notifications('supervisor', 'job_created', [
    'supervisor_id' => $supervisor_id,
    'job_title' => $job_title,
    'job_id' => $job_id
]);
```

## User Interface

### **Header Notification Bell**
```html
<div class="notification-bell me-3">
    <div class="dropdown">
        <button class="btn btn-outline-secondary position-relative">
            <i class="bi bi-bell"></i>
            <span class="badge rounded-pill bg-danger">3</span>
        </button>
        <ul class="dropdown-menu notification-dropdown">
            <!-- Notification items -->
        </ul>
    </div>
</div>
```

### **Notification Item**
```html
<li class="dropdown-item notification-item" data-id="123">
    <div class="d-flex align-items-start">
        <div class="flex-shrink-0 me-3">
            <i class="bi bi-briefcase text-primary fs-5"></i>
        </div>
        <div class="flex-grow-1">
            <div class="fw-bold">New Job Assigned</div>
            <div class="small text-muted">You have been assigned a new job.</div>
            <div class="small text-muted mt-1">2 minutes ago</div>
        </div>
    </div>
</li>
```

## JavaScript Features

### **Auto-refresh**
```javascript
// Refresh notification count every 30 seconds
setInterval(function() {
    fetch('get_notification_count.php')
    .then(response => response.json())
    .then(data => {
        updateNotificationBadge(data.count);
    });
}, 30000);
```

### **Mark as Read**
```javascript
// Mark notification as read when clicked
function markNotificationRead(notificationId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationUI(notificationId);
        }
    });
}
```

## CSS Styling

### **Notification Bell**
```css
.notification-bell {
    position: relative;
}

.notification-dropdown {
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border-radius: 0.5rem;
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
}
```

### **Notification Items**
```css
.notification-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f8f9fa;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #e3f2fd;
}
```

## Integration Points

### **Existing Systems**
- **Job Management**: Notifications for job assignments and completions
- **Material Management**: Notifications for material requests and approvals
- **User Management**: Notifications for profile updates and password changes
- **Activity Logging**: Integration with user activity tracking

### **Future Enhancements**
1. **Email Notifications**: Send email alerts for important notifications
2. **Push Notifications**: Browser push notifications
3. **Notification Preferences**: User-configurable notification settings
4. **Notification Templates**: Reusable notification templates
5. **Bulk Operations**: Mass notification management

## Security Features

### **Access Control**
- Users can only see their own notifications
- Notifications are tied to user sessions
- AJAX endpoints validate user authentication

### **Data Protection**
- SQL injection prevention with prepared statements
- XSS protection with htmlspecialchars
- CSRF protection for form submissions

### **Performance**
- Indexed database queries for fast retrieval
- Pagination for large notification lists
- Auto-cleanup of old notifications (30 days)

## Troubleshooting

### **Common Issues**

#### **Notifications Not Appearing**
- Check database connection
- Verify notification creation functions
- Check user session validity
- Review browser console for JavaScript errors

#### **Badge Not Updating**
- Check AJAX endpoint responses
- Verify JavaScript fetch requests
- Check network connectivity
- Review browser console for errors

#### **Performance Issues**
- Monitor database query performance
- Check notification count queries
- Review auto-refresh intervals
- Consider pagination for large lists

### **Debug Tools**
- Browser developer tools for JavaScript debugging
- Database query logging for performance analysis
- Notification test page for functionality verification

## Configuration

### **Settings**
- **Auto-refresh interval**: 30 seconds (configurable)
- **Notification retention**: 30 days (configurable)
- **Max notifications per page**: 50 (configurable)
- **Badge max display**: 99+ (configurable)

### **Customization**
- **Notification types**: Add new types in notifications.php
- **Icons and colors**: Modify get_notification_icon() and get_notification_color()
- **Time formatting**: Customize format_notification_time()
- **UI styling**: Modify CSS classes in style.css

## Best Practices

### **Notification Creation**
1. Use descriptive titles and messages
2. Include relevant links when possible
3. Choose appropriate notification types
4. Consider user role and permissions

### **Performance**
1. Limit notification queries with proper limits
2. Use database indexes for fast queries
3. Implement pagination for large lists
4. Clean up old notifications regularly

### **User Experience**
1. Provide clear notification messages
2. Include actionable links when relevant
3. Use appropriate icons and colors
4. Implement smooth animations and transitions

## API Reference

### **Endpoints**
- `mark_notification_read.php` - Mark single notification as read
- `mark_all_notifications_read.php` - Mark all notifications as read
- `get_notification_count.php` - Get unread notification count

### **Response Format**
```json
{
    "success": true,
    "count": 5,
    "message": "Operation completed successfully"
}
```

### **Error Handling**
```json
{
    "success": false,
    "message": "Error description",
    "code": 400
}
```

This notification system provides a comprehensive solution for real-time user alerts and system communication, enhancing the overall user experience of the Job Order Request system. 