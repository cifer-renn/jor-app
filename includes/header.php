<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Include database connection
require_once 'database.php';

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Job Order System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../index.php" class="sidebar-brand">
            <i class="bi bi-gear-fill"></i>
            Job Order System
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <?php 
        if ($_SESSION['role'] === 'supervisor'): 
            // Get pending verifications count for supervisor
            $pending_verifications_count = 0;
            if (isset($conn)) {
                $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM jobs WHERE status = 'completed' AND verification_status = 'pending_verification'");
                $stmt_count->execute();
                $result_count = $stmt_count->get_result();
                if ($result_count) {
                    $pending_verifications_count = $result_count->fetch_assoc()['count'];
                }
                $stmt_count->close();
            }
        ?>
            <!-- Supervisor Navigation -->
            <div class="nav-item">
                <a href="supervisor_dashboard.php" class="nav-link text-white <?php echo (strpos($current_page, 'supervisor_dashboard') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="create_job.php" class="nav-link text-white <?php echo (strpos($current_page, 'create_job') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-plus-square me-2"></i> Create Job
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_jobs.php" class="nav-link text-white <?php echo (strpos($current_page, 'manage_jobs') !== false || strpos($current_page, 'edit_job') !== false || strpos($current_page, 'view_job') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-list-task me-2"></i> Manage Jobs
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_job_verifications.php" class="nav-link text-white <?php echo (strpos($current_page, 'manage_job_verifications') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard-check me-2"></i>Job Verifications
                    <?php if ($pending_verifications_count > 0): ?>
                        <span class="badge bg-danger ms-2"><?php echo $pending_verifications_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_users.php" class="nav-link text-white <?php echo (strpos($current_page, 'manage_users') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-people me-2"></i> Manage Users
                </a>
            </div>
            <div class="nav-item">
                <a href="supervisor_reports.php" class="nav-link text-white <?php echo (strpos($current_page, 'supervisor_reports') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-bar-chart-line me-2"></i>Reports
                </a>
            </div>
            
        <?php 
        elseif ($_SESSION['role'] === 'warehouse_manager'): 
            // Get pending requests count for warehouse manager
            $pending_requests_count = 0;
            $pending_applications_count = 0;
            if (isset($conn)) {
                $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM material_requests WHERE status = 'pending'");
                $stmt_count->execute();
                $result_count = $stmt_count->get_result();
                if ($result_count) {
                    $pending_requests_count = $result_count->fetch_assoc()['count'];
                }
                $stmt_count->close();
                
                // Get pending material applications count
                $stmt_app_count = $conn->prepare("SELECT COUNT(*) as count FROM material_applications WHERE status = 'pending'");
                $stmt_app_count->execute();
                $result_app_count = $stmt_app_count->get_result();
                if ($result_app_count) {
                    $pending_applications_count = $result_app_count->fetch_assoc()['count'];
                }
                $stmt_app_count->close();
            }
        ?>
            <div class="nav-item">
                <a href="warehouse_dashboard.php" class="nav-link text-white <?php echo (strpos($current_page, 'warehouse_dashboard') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_inventory.php" class="nav-link text-white <?php echo (strpos($current_page, 'manage_inventory') !== false || strpos($current_page, 'add_inventory') !== false || strpos($current_page, 'edit_inventory') !== false || strpos($current_page, 'view_item_movements') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam me-2"></i> Manage Inventory
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_materials.php" class="nav-link text-white <?php echo (strpos($current_page, 'manage_materials') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam me-2"></i>Manage Materials
                    <?php if (($pending_requests_count + $pending_applications_count) > 0): ?>
                        <span class="badge bg-danger ms-2"><?php echo ($pending_requests_count + $pending_applications_count); ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
        <?php elseif ($_SESSION['role'] === 'machine_operator'): ?>
            <!-- Machine Operator Navigation -->
            <div class="nav-item">
                <a href="operator_dashboard.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'operator_dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-workspace me-2"></i>My Jobs
                </a>
            </div>
            <div class="nav-item">
                <a href="job_history.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'job_history.php' ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history me-2"></i>Job History
                </a>
            </div>
            <div class="nav-item">
                <a href="material_form.php" class="nav-link text-white <?php echo (strpos($current_page, 'material_form') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-plus-circle me-2"></i>Material Form
                </a>
            </div>
            <div class="nav-item">
                <a href="view_my_applications.php" class="nav-link text-white <?php echo (strpos($current_page, 'view_my_applications') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-text me-2"></i>My Applications
                </a>
            </div>
        <?php endif; ?>
    </nav>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div id="sidebar-clock" class="text-center small text-muted py-2"></div>
    </div>
</div>

<script>
const userTimezone = <?php echo json_encode($_SESSION['timezone'] ?? null); ?>;
function updateSidebarClock() {
    const el = document.getElementById('sidebar-clock');
    if (!el) return;
    const now = new Date();
    const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
    let timeString, dateString;
    if (userTimezone) {
        timeString = now.toLocaleTimeString(undefined, { timeZone: userTimezone });
        dateString = now.toLocaleDateString(undefined, { ...options, timeZone: userTimezone });
    } else {
        timeString = now.toLocaleTimeString();
        dateString = now.toLocaleDateString(undefined, options);
    }
    el.innerHTML = timeString + '<br>' + dateString;
}
setInterval(updateSidebarClock, 1000);
updateSidebarClock();
</script>

<!-- Main Content -->
<div class="main-content" id="main-content">
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="d-flex align-items-center">
            <button class="sidebar-toggle" id="sidebar-toggle">
                <i class="bi bi-list"></i>
            </button>
            <h4 class="mb-0 ms-3"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h4>
        </div>
        
        <div class="user-menu">
            <?php
            // Include notifications system
            require_once 'notifications.php';
            
            // Get user avatar
            $user_avatar = null;
            try {
                if (isset($conn)) {
                    $stmt = $conn->prepare("SELECT avatar_path FROM users WHERE id = ?");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user_data = $result->fetch_assoc();
                    $user_avatar = $user_data['avatar_path'] ?? null;
                    $stmt->close();
                    
                    // Debug: Check if avatar exists (remove this in production)
                    if ($user_avatar) {
                        $file_path = '../' . $user_avatar;
                        if (!file_exists($file_path)) {
                            // File doesn't exist, clear the avatar path
                            $user_avatar = null;
                        }
                    }
                }
            } catch (Exception $e) {
                // Silently handle database errors for avatar display
                $user_avatar = null;
            }
            
            // Get unread notification count
            $unread_count = get_unread_count($_SESSION['user_id']);
            ?>
            
            <!-- Notification Bell -->
            <div class="notification-bell me-3">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 350px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <?php if ($unread_count > 0): ?>
                                <button class="btn btn-sm btn-outline-primary" onclick="markAllRead()">
                                    Mark all read
                                </button>
                            <?php endif; ?>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <div id="notifications-list">
                            <?php
                            $notifications = get_unread_notifications($_SESSION['user_id'], 10);
                            if (empty($notifications)):
                            ?>
                                <li class="dropdown-item text-center text-muted py-3">
                                    <i class="bi bi-bell-slash fs-4"></i>
                                    <div class="mt-2">No new notifications</div>
                                </li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <li class="dropdown-item notification-item" data-id="<?php echo $notification['id']; ?>" 
                                        <?php if ($notification['link']): ?>data-link="<?php echo htmlspecialchars($notification['link']); ?>"<?php endif; ?>>
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3">
                                                <i class="bi <?php echo get_notification_icon($notification['type']); ?> <?php echo get_notification_color($notification['type']); ?> fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="fw-bold text-truncate" title="<?php echo htmlspecialchars($notification['title']); ?>">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                </div>
                                                <div class="small text-muted text-truncate" title="<?php echo htmlspecialchars($notification['message']); ?>">
                                                    <?php echo htmlspecialchars($notification['message']); ?>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    <?php echo format_notification_time($notification['created_at']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="notifications.php">
                            <i class="bi bi-list-ul me-2"></i>View All Notifications
                        </a></li>
                    </ul>
                </div>
            </div>
            
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']); ?></div>
                <div class="user-role"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?></div>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                    <?php if ($user_avatar && file_exists('../' . $user_avatar)): ?>
                        <img src="../<?php echo htmlspecialchars($user_avatar); ?>" 
                             alt="Profile Avatar" class="rounded-circle me-2" 
                             style="width: 32px; height: 32px; object-fit: cover;">
                    <?php else: ?>
                        <i class="bi bi-person-circle me-2"></i>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="profile_settings.php">
                        <i class="bi bi-person-gear me-2"></i>Profile Settings
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Page Content -->
    <div class="container-fluid p-4">
    
    <script>
    // Notification functions
    function markAllRead() {
        const markAllButton = document.querySelector('[onclick="markAllRead()"]');
        if (markAllButton) {
            markAllButton.disabled = true;
            markAllButton.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Marking...';
        }
        
        fetch('mark_all_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add animation to all unread notifications
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.add('marking-read');
                    setTimeout(() => {
                        item.classList.remove('unread', 'marking-read');
                    }, 300);
                });
                
                // Update badge with animation
                const badge = document.querySelector('.notification-bell .badge');
                if (badge) {
                    badge.style.animation = 'badgeBounce 0.6s ease-out';
                    setTimeout(() => {
                        badge.remove();
                    }, 600);
                }
                
                // Update notifications list
                const notificationsList = document.getElementById('notifications-list');
                if (notificationsList) {
                    notificationsList.style.opacity = '0.5';
                    setTimeout(() => {
                        notificationsList.innerHTML = `
                            <li class="dropdown-item text-center text-muted py-3">
                                <i class="bi bi-bell-slash fs-4"></i>
                                <div class="mt-2">No new notifications</div>
                            </li>
                        `;
                        notificationsList.style.opacity = '1';
                    }, 300);
                }
                
                // Hide mark all read button
                if (markAllButton) {
                    markAllButton.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
            if (markAllButton) {
                markAllButton.disabled = false;
                markAllButton.innerHTML = '<i class="bi bi-check-all me-2"></i>Mark All Read';
            }
        });
    }
    
    // Auto-refresh notification count every 30 seconds
    setInterval(function() {
        fetch('get_notification_count.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notification-bell .badge');
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count > 99 ? '99+' : data.count;
                    badge.style.animation = 'badgeBounce 0.6s ease-out';
                } else {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                    newBadge.textContent = data.count > 99 ? '99+' : data.count;
                    newBadge.style.animation = 'badgeBounce 0.6s ease-out';
                    document.querySelector('.notification-bell .btn').appendChild(newBadge);
                }
            } else {
                if (badge) {
                    badge.style.animation = 'badgeBounce 0.6s ease-out';
                    setTimeout(() => {
                        badge.remove();
                    }, 600);
                }
            }
        })
        .catch(error => {
            console.error('Error fetching notification count:', error);
        });
    }, 30000);
    
    // Add animation to notification bell when there are notifications
    document.addEventListener('DOMContentLoaded', function() {
        const badge = document.querySelector('.notification-bell .badge');
        if (badge) {
            const notificationBell = document.querySelector('.notification-bell');
            notificationBell.classList.add('has-notifications');
        }
        
        // Handle notification clicks in header dropdown
        const notificationItems = document.querySelectorAll('.notification-item');
        notificationItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                const notificationId = this.dataset.id;
                const link = this.dataset.link;
                
                // Mark as read via AJAX
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
                        // Remove the notification item with zoom animation
                        this.style.transition = 'all 0.3s ease';
                        this.style.opacity = '0';
                        this.style.transform = 'scale(0.8)';
                        
                        setTimeout(() => {
                            this.remove();
                            
                            // Update badge count
                            const badge = document.querySelector('.notification-bell .badge');
                            if (badge) {
                                const currentCount = parseInt(badge.textContent);
                                if (currentCount > 1) {
                                    badge.textContent = currentCount - 1;
                                } else {
                                    badge.remove();
                                    document.querySelector('.notification-bell').classList.remove('has-notifications');
                                }
                            }
                            
                            // Check if no more notifications
                            const remainingNotifications = document.querySelectorAll('.notification-item');
                            if (remainingNotifications.length === 0) {
                                const notificationsList = document.getElementById('notifications-list');
                                if (notificationsList) {
                                    notificationsList.innerHTML = `
                                        <li class="dropdown-item text-center text-muted py-3">
                                            <i class="bi bi-bell-slash fs-4"></i>
                                            <div class="mt-2">No new notifications</div>
                                        </li>
                                    `;
                                }
                            }
                        }, 300);
                        
                        // Navigate to link if available
                        if (link) {
                            setTimeout(() => {
                                // Always ensure the link starts with /jor-app-rpl/
                                let base = '/jor-app-rpl/';
                                let cleanLink = link.replace(/^\/?(jor-app-rpl\/)?/, '');
                                window.location.href = base + cleanLink;
                            }, 350);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                    // Still navigate to link even if marking as read fails
                    if (link) {
                        let base = '/jor-app-rpl/';
                        let cleanLink = link.replace(/^\/?(jor-app-rpl\/)?/, '');
                        window.location.href = base + cleanLink;
                    }
                });
            });
        });
    });
    </script> 