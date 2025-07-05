<?php
/**
 * Notification System for Job Order Request System
 * Handles notification creation, retrieval, and display
 */

require_once 'database.php';

/**
 * Create a new notification
 * 
 * @param int $user_id User ID to receive notification
 * @param string $type Notification type (job_assigned, job_completed, material_request, etc.)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $link Optional link for the notification
 * @return bool Success status
 */
function create_notification($user_id, $type, $title, $message, $link = null) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notifications for a user
 * 
 * @param int $user_id User ID
 * @param int $limit Maximum number of notifications to return
 * @return array Array of notifications
 */
function get_unread_notifications($user_id, $limit = 10) {
    global $conn;
    
    if (!$conn) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        return $notifications;
    } catch (Exception $e) {
        error_log("Error getting unread notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all notifications for a user
 * 
 * @param int $user_id User ID
 * @param int $limit Maximum number of notifications to return
 * @return array Array of notifications
 */
function get_all_notifications($user_id, $limit = 20) {
    global $conn;
    
    if (!$conn) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        return $notifications;
    } catch (Exception $e) {
        error_log("Error getting all notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 * 
 * @param int $notification_id Notification ID
 * @return bool Success status
 */
function mark_notification_read($notification_id) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $notification_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $user_id User ID
 * @return bool Success status
 */
function mark_all_notifications_read($user_id) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for a user
 * 
 * @param int $user_id User ID
 * @return int Number of unread notifications
 */
function get_unread_count($user_id) {
    global $conn;
    
    if (!$conn) {
        return 0;
    }
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'] ?? 0;
        $stmt->close();
        return $count;
    } catch (Exception $e) {
        error_log("Error getting unread count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Delete old notifications (older than 30 days)
 * 
 * @return bool Success status
 */
function cleanup_old_notifications() {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error cleaning up old notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notification icon based on type
 * 
 * @param string $type Notification type
 * @return string Bootstrap icon class
 */
function get_notification_icon($type) {
    $icons = [
        'job_assigned' => 'bi-briefcase',
        'job_completed' => 'bi-check-circle',
        'job_cancelled' => 'bi-x-circle',
        'material_request' => 'bi-box-seam',
        'material_approved' => 'bi-check-square',
        'material_rejected' => 'bi-x-square',
        'verification_required' => 'bi-clipboard-check',
        'system_alert' => 'bi-exclamation-triangle',
        'profile_update' => 'bi-person-gear',
        'password_change' => 'bi-key',
        'login_alert' => 'bi-shield-check',
        'default' => 'bi-bell'
    ];
    
    return $icons[$type] ?? $icons['default'];
}

/**
 * Get notification color based on type
 * 
 * @param string $type Notification type
 * @return string Bootstrap color class
 */
function get_notification_color($type) {
    $colors = [
        'job_assigned' => 'text-primary',
        'job_completed' => 'text-success',
        'job_cancelled' => 'text-danger',
        'material_request' => 'text-info',
        'material_approved' => 'text-success',
        'material_rejected' => 'text-danger',
        'verification_required' => 'text-warning',
        'system_alert' => 'text-danger',
        'profile_update' => 'text-info',
        'password_change' => 'text-warning',
        'login_alert' => 'text-info',
        'default' => 'text-secondary'
    ];
    
    return $colors[$type] ?? $colors['default'];
}

/**
 * Format notification time
 * 
 * @param string $timestamp Database timestamp
 * @return string Formatted time
 */
function format_notification_time($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

/**
 * Create role-based notifications
 * 
 * @param string $role User role
 * @param string $action Action performed
 * @param array $data Additional data
 */
function create_role_notifications($role, $action, $data = []) {
    switch ($role) {
        case 'supervisor':
            switch ($action) {
                case 'job_created':
                    create_notification(
                        $data['supervisor_id'],
                        'job_assigned',
                        'New Job Created',
                        'A new job "' . $data['job_title'] . '" has been created.',
                        'pages/view_job.php?id=' . $data['job_id']
                    );
                    break;
                case 'job_completed':
                    create_notification(
                        $data['supervisor_id'],
                        'job_completed',
                        'Job Completed',
                        'Job "' . $data['job_title'] . '" has been completed by ' . $data['operator_name'] . '.',
                        'pages/view_job.php?id=' . $data['job_id']
                    );
                    break;
                case 'verification_required':
                    create_notification(
                        $data['supervisor_id'],
                        'verification_required',
                        'Job Verification Required',
                        'Job "' . $data['job_title'] . '" requires verification.',
                        'pages/manage_job_verifications.php'
                    );
                    break;
            }
            break;
            
        case 'warehouse_manager':
            switch ($action) {
                case 'material_request':
                    create_notification(
                        $data['warehouse_manager_id'],
                        'material_request',
                        'New Material Request',
                        'A new material request has been submitted.',
                        'pages/manage_materials.php'
                    );
                    break;
                case 'material_approved':
                    create_notification(
                        $data['requester_id'],
                        'material_approved',
                        'Material Request Approved',
                        'Your material request has been approved.',
                        'pages/view_my_applications.php'
                    );
                    break;
                case 'material_rejected':
                    create_notification(
                        $data['requester_id'],
                        'material_rejected',
                        'Material Request Rejected',
                        'Your material request has been rejected.',
                        'pages/view_my_applications.php'
                    );
                    break;
            }
            break;
            
        case 'machine_operator':
            switch ($action) {
                case 'job_assigned':
                    create_notification(
                        $data['operator_id'],
                        'job_assigned',
                        'New Job Assigned',
                        'You have been assigned a new job: "' . $data['job_title'] . '".',
                        'pages/view_operator_job.php?id=' . $data['job_id']
                    );
                    break;
                case 'job_completed':
                    create_notification(
                        $data['supervisor_id'],
                        'job_completed',
                        'Job Completed',
                        'Job "' . $data['job_title'] . '" has been completed.',
                        'pages/view_job.php?id=' . $data['job_id']
                    );
                    break;
            }
            break;
    }
}
?> 