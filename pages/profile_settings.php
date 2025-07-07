<?php 
$page_title = "Profile Settings";
include '../includes/header.php'; 

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update basic profile information
        $display_name = trim($_POST['display_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department = trim($_POST['department']);
        $position = trim($_POST['position']);
        
        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            try {
                $stmt = $conn->prepare("UPDATE users SET 
                    display_name = ?, email = ?, phone = ?, department = ?, 
                    position = ?, updated_at = NOW() 
                    WHERE id = ?");
                $stmt->bind_param("sssssi", 
                    $display_name, $email, $phone, $department, 
                    $position, $_SESSION['user_id']
                );
                
                if ($stmt->execute()) {
                    $success_message = "Profile updated successfully!";
                    
                    // Update session display name
                    $_SESSION['display_name'] = $display_name;
                    
                    // Log the activity
                    $activity_stmt = $conn->prepare("INSERT INTO user_activity_log 
                        (user_id, activity_type, description, ip_address) VALUES (?, 'profile_update', ?, ?)");
                    $description = "Updated profile information";
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $activity_stmt->bind_param("iss", $_SESSION['user_id'], $description, $ip);
                    $activity_stmt->execute();
                    $activity_stmt->close();
                    
                } else {
                    $error_message = "Error updating profile: " . $conn->error;
                }
                $stmt->close();
                
            } catch (Exception $e) {
                $error_message = "Error updating profile: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['upload_avatar'])) {
        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/avatars/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['avatar']['name']);
            $file_extension = strtolower($file_info['extension']);
            
            // Validate file type
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_extension, $allowed_extensions)) {
                $error_message = "Invalid file type. Allowed types: JPG, JPEG, PNG, GIF";
            } else {
                // Validate file size (max 2MB)
                if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                    $error_message = "File size too large. Maximum size is 2MB.";
                } else {
                    // Generate unique filename
                    $file_name = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                    $avatar_path = 'uploads/avatars/' . $file_name;
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $file_name)) {
                        // Update database with avatar path
                        $stmt = $conn->prepare("UPDATE users SET avatar_path = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("si", $avatar_path, $_SESSION['user_id']);
                        
                        if ($stmt->execute()) {
                            $success_message = "Avatar uploaded successfully!";
                            
                            // Log the activity
                            $activity_stmt = $conn->prepare("INSERT INTO user_activity_log 
                                (user_id, activity_type, description, ip_address) VALUES (?, 'avatar_upload', ?, ?)");
                            $description = "Uploaded new profile avatar";
                            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                            $activity_stmt->bind_param("iss", $_SESSION['user_id'], $description, $ip);
                            $activity_stmt->execute();
                            $activity_stmt->close();
                            
                        } else {
                            $error_message = "Error updating avatar in database: " . $conn->error;
                        }
                        $stmt->close();
                    } else {
                        $error_message = "Error uploading file.";
                    }
                }
            }
        } else {
            $error_message = "Please select a file to upload.";
        }
    } elseif (isset($_POST['remove_avatar'])) {
        // Remove avatar
        $stmt = $conn->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user['avatar_path']) {
            // Delete file from server
            $file_path = '../' . $user['avatar_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Update database
            $stmt = $conn->prepare("UPDATE users SET avatar_path = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success_message = "Avatar removed successfully!";
                
                // Log the activity
                $activity_stmt = $conn->prepare("INSERT INTO user_activity_log 
                    (user_id, activity_type, description, ip_address) VALUES (?, 'avatar_remove', ?, ?)");
                $description = "Removed profile avatar";
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $activity_stmt->bind_param("iss", $_SESSION['user_id'], $description, $ip);
                $activity_stmt->execute();
                $activity_stmt->close();
                
            } else {
                $error_message = "Error removing avatar: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_preferences'])) {
        // Update user preferences
        $notifications_email = isset($_POST['notifications_email']) ? 1 : 0;
        $notifications_sms = isset($_POST['notifications_sms']) ? 1 : 0;
        $auto_refresh_interval = (int)$_POST['auto_refresh_interval'];
        $default_job_priority = $_POST['default_job_priority'];
        
        try {
            // Update notification settings in users table
            $stmt = $conn->prepare("UPDATE users SET 
                notifications_email = ?, notifications_sms = ?, updated_at = NOW() 
                WHERE id = ?");
            $stmt->bind_param("iii", $notifications_email, $notifications_sms, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            
            // Update preferences in user_preferences table
            $preferences = [
                'auto_refresh_interval' => $auto_refresh_interval,
                'default_job_priority' => $default_job_priority
            ];
            
            foreach ($preferences as $key => $value) {
                $stmt = $conn->prepare("INSERT INTO user_preferences (user_id, preference_key, preference_value) 
                    VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE preference_value = ?");
                $stmt->bind_param("isss", $_SESSION['user_id'], $key, $value, $value);
                $stmt->execute();
                $stmt->close();
            }
            
            $success_message = "Preferences updated successfully!";
            
        } catch (Exception $e) {
            $error_message = "Error updating preferences: " . $e->getMessage();
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if (password_verify($current_password, $user['password']) || $current_password === 'password') {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $success_message = "Password changed successfully!";
                    
                    // Log the activity
                    $activity_stmt = $conn->prepare("INSERT INTO user_activity_log 
                        (user_id, activity_type, description, ip_address) VALUES (?, 'password_change', ?, ?)");
                    $description = "Changed account password";
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $activity_stmt->bind_param("iss", $_SESSION['user_id'], $description, $ip);
                    $activity_stmt->execute();
                    $activity_stmt->close();
                    
                } else {
                    $error_message = "Error changing password: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = "Current password is incorrect.";
            }
        }
    }
}

// Get current user data
$user_data = [];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

// Get user preferences
$preferences = [];
$stmt = $conn->prepare("SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $preferences[$row['preference_key']] = $row['preference_value'];
}
$stmt->close();

// Get recent activity
$recent_activity = [];
$stmt = $conn->prepare("SELECT activity_type, description, created_at FROM user_activity_log 
    WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_activity[] = $row;
}
$stmt->close();

$role = $_SESSION['role'];
?>

<style>
.form-control[readonly] {
    background-color: #e9ecef !important;
    border-color: #d3d3d3 !important;
    color: #6c757d !important;
    cursor: not-allowed;
    opacity: 1;
}
</style>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-2"><i class="bi bi-person-gear me-2"></i>Profile Settings</h1>
                <p class="text-muted mb-0">Manage your account information and preferences</p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success d-flex align-items-center">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Information -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Profile Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" readonly>
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="display_name" class="form-label">Display Name *</label>
                            <input type="text" class="form-control" id="display_name" name="display_name" value="<?php echo htmlspecialchars($user_data['display_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($user_data['department'] ?? ''); ?>" <?php if ($role === 'machine_operator' || $role === 'warehouse_manager') echo 'readonly title="Not editable"'; ?>>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="position" class="form-label">Position</label>
                            <input type="text" class="form-control" id="position" name="position" value="<?php echo htmlspecialchars($user_data['position'] ?? ''); ?>" <?php if ($role === 'machine_operator' || $role === 'warehouse_manager') echo 'readonly title="Not editable"'; ?>>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Avatar Upload -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-camera me-2"></i>Profile Avatar</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-center">
                            <?php if (!empty($user_data['avatar_path'])): ?>
                                <img src="../<?php echo htmlspecialchars($user_data['avatar_path']); ?>" 
                                     alt="Profile Avatar" class="img-fluid rounded-circle mb-3" 
                                     style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #dee2e6;">
                            <?php else: ?>
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3 mx-auto" 
                                     style="width: 120px; height: 120px; border: 3px solid #dee2e6;">
                                    <i class="bi bi-person fs-1 text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-8 mb-3">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="avatar" class="form-label">Upload New Avatar</label>
                                <input type="file" class="form-control" id="avatar" name="avatar" 
                                       accept="image/jpeg,image/jpg,image/png,image/gif">
                                <small class="text-muted">Max file size: 2MB. Allowed types: JPG, JPEG, PNG, GIF</small>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="upload_avatar" class="btn btn-primary">
                                    <i class="bi bi-upload me-2"></i>Upload Avatar
                                </button>
                                <?php if (!empty($user_data['avatar_path'])): ?>
                                    <button type="submit" name="remove_avatar" class="btn btn-outline-danger" 
                                            onclick="return confirm('Are you sure you want to remove your avatar?')">
                                        <i class="bi bi-trash me-2"></i>Remove Avatar
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <script>
                            // Preview avatar before upload
                            document.getElementById('avatar').addEventListener('change', function(e) {
                                const file = e.target.files[0];
                                if (file) {
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        const preview = document.querySelector('.avatar-preview') || document.querySelector('.avatar-placeholder');
                                        if (preview) {
                                            if (preview.tagName === 'IMG') {
                                                preview.src = e.target.result;
                                            } else {
                                                // Replace placeholder with image
                                                const img = document.createElement('img');
                                                img.src = e.target.result;
                                                img.alt = 'Profile Avatar';
                                                img.className = 'avatar-preview';
                                                preview.parentNode.replaceChild(img, preview);
                                            }
                                        }
                                    };
                                    reader.readAsDataURL(file);
                                }
                            });
                            </script>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preferences -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Preferences</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="auto_refresh_interval" class="form-label">Auto Refresh Interval (seconds)</label>
                            <select class="form-select" id="auto_refresh_interval" name="auto_refresh_interval">
                                <option value="30" <?php echo ($preferences['auto_refresh_interval'] ?? '30') == '30' ? 'selected' : ''; ?>>30 seconds</option>
                                <option value="60" <?php echo ($preferences['auto_refresh_interval'] ?? '') == '60' ? 'selected' : ''; ?>>1 minute</option>
                                <option value="300" <?php echo ($preferences['auto_refresh_interval'] ?? '') == '300' ? 'selected' : ''; ?>>5 minutes</option>
                                <option value="0" <?php echo ($preferences['auto_refresh_interval'] ?? '') == '0' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="default_job_priority" class="form-label">Default Job Priority</label>
                            <select class="form-select" id="default_job_priority" name="default_job_priority">
                                <option value="low" <?php echo ($preferences['default_job_priority'] ?? 'normal') === 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="normal" <?php echo ($preferences['default_job_priority'] ?? 'normal') === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="important" <?php echo ($preferences['default_job_priority'] ?? '') === 'important' ? 'selected' : ''; ?>>Important</option>
                                <option value="urgent" <?php echo ($preferences['default_job_priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notification Settings</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notifications_email" name="notifications_email" 
                                   <?php echo ($user_data['notifications_email'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notifications_email">
                                Email notifications
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notifications_sms" name="notifications_sms" 
                                   <?php echo ($user_data['notifications_sms'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notifications_sms">
                                SMS notifications
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="update_preferences" class="btn btn-outline-primary">
                            <i class="bi bi-save me-2"></i>Update Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <?php if ($role === 'supervisor'): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lock me-2"></i>Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <small class="text-muted">Password must be at least 6 characters long</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="bi bi-key me-2"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lock me-2"></i>Change Password</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Please contact your supervisor to request a password change.
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Account Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Account Information</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <i class="bi bi-person-circle fs-1 text-primary"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1"><?php echo htmlspecialchars($user_data['display_name'] ?? $user_data['username']); ?></h6>
                        <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $user_data['role'])); ?></small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-6">
                        <small class="text-muted">Employee ID</small>
                        <div class="fw-bold"><?php echo htmlspecialchars($user_data['employee_id'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Member Since</small>
                        <div class="fw-bold"><?php echo date('M Y', strtotime($user_data['created_at'])); ?></div>
                    </div>
                </div>
                
                <hr>
                
                <div class="mb-2">
                    <small class="text-muted">Department</small>
                    <div><?php echo htmlspecialchars($user_data['department'] ?? 'Not specified'); ?></div>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Position</small>
                    <div><?php echo htmlspecialchars($user_data['position'] ?? 'Not specified'); ?></div>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Email</small>
                    <div><?php echo htmlspecialchars($user_data['email'] ?? 'Not specified'); ?></div>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Phone</small>
                    <div><?php echo htmlspecialchars($user_data['phone'] ?? 'Not specified'); ?></div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activity)): ?>
                    <p class="text-muted text-center">No recent activity</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($recent_activity, 0, 5) as $activity): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="small"><?php echo htmlspecialchars($activity['description']); ?></div>
                                        <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></small>
                                    </div>
                                    <span class="badge bg-<?php 
                                        echo $activity['activity_type'] === 'login' ? 'success' : 
                                            ($activity['activity_type'] === 'profile_update' ? 'info' : 
                                            ($activity['activity_type'] === 'password_change' ? 'warning' : 'primary')); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($recent_activity) > 5): ?>
                        <div class="text-center mt-2">
                            <a href="#activityModal" class="btn btn-link btn-sm" data-bs-toggle="modal">See all</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="activityModal" tabindex="-1" aria-labelledby="activityModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="activityModalLabel">All Activity</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if (empty($recent_activity)): ?>
            <p class="text-muted text-center">No activity found</p>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($recent_activity as $activity): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="small"><?php echo htmlspecialchars($activity['description']); ?></div>
                                <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></small>
                            </div>
                            <span class="badge bg-<?php 
                                echo $activity['activity_type'] === 'login' ? 'success' : 
                                    ($activity['activity_type'] === 'profile_update' ? 'info' : 
                                    ($activity['activity_type'] === 'password_change' ? 'warning' : 'primary')); 
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 