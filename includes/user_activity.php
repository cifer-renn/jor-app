<?php
/**
 * User Activity Logger
 * Utility functions for logging user activities
 */

/**
 * Log a user activity
 * 
 * @param int $user_id User ID
 * @param string $activity_type Type of activity (login, profile_update, job_created, etc.)
 * @param string $description Description of the activity
 * @param string|null $ip_address IP address (optional)
 * @param string|null $user_agent User agent (optional)
 * @return bool Success status
 */
function log_user_activity($user_id, $activity_type, $description, $ip_address = null, $user_agent = null) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO user_activity_log 
            (user_id, activity_type, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $activity_type, $description, $ip_address, $user_agent);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error logging user activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user preferences
 * 
 * @param int $user_id User ID
 * @param string|null $key Specific preference key (optional)
 * @return array|string|null Preferences array or specific value
 */
function get_user_preferences($user_id, $key = null) {
    global $conn;
    
    if (!$conn) {
        return null;
    }
    
    try {
        if ($key) {
            $stmt = $conn->prepare("SELECT preference_value FROM user_preferences WHERE user_id = ? AND preference_key = ?");
            $stmt->bind_param("is", $user_id, $key);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row ? $row['preference_value'] : null;
        } else {
            $stmt = $conn->prepare("SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $preferences = [];
            while ($row = $result->fetch_assoc()) {
                $preferences[$row['preference_key']] = $row['preference_value'];
            }
            $stmt->close();
            return $preferences;
        }
    } catch (Exception $e) {
        error_log("Error getting user preferences: " . $e->getMessage());
        return null;
    }
}

/**
 * Set user preference
 * 
 * @param int $user_id User ID
 * @param string $key Preference key
 * @param string $value Preference value
 * @return bool Success status
 */
function set_user_preference($user_id, $key, $value) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO user_preferences (user_id, preference_key, preference_value) 
            VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE preference_value = ?");
        $stmt->bind_param("isss", $user_id, $key, $value, $value);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error setting user preference: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user profile data
 * 
 * @param int $user_id User ID
 * @return array|null User profile data
 */
function get_user_profile($user_id) {
    global $conn;
    
    if (!$conn) {
        return null;
    }
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    } catch (Exception $e) {
        error_log("Error getting user profile: " . $e->getMessage());
        return null;
    }
}

/**
 * Update user profile
 * 
 * @param int $user_id User ID
 * @param array $data Profile data to update
 * @return bool Success status
 */
function update_user_profile($user_id, $data) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    try {
        $allowed_fields = ['display_name', 'email', 'phone', 'department', 'position', 
                          'timezone', 'language', 'theme', 'notifications_email', 'notifications_sms'];
        
        $updates = [];
        $types = '';
        $values = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $updates[] = "$field = ?";
                $types .= 's';
                $values[] = $value;
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $values[] = $user_id;
        $types .= 'i';
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error updating user profile: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent user activity
 * 
 * @param int $user_id User ID
 * @param int $limit Number of activities to return
 * @return array Recent activities
 */
function get_recent_activity($user_id, $limit = 10) {
    global $conn;
    
    if (!$conn) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare("SELECT activity_type, description, created_at FROM user_activity_log 
            WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $activities = [];
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        $stmt->close();
        return $activities;
    } catch (Exception $e) {
        error_log("Error getting recent activity: " . $e->getMessage());
        return [];
    }
}
?> 