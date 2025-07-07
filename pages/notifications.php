<?php
$page_title = "Notifications";
include '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';
require_once '../includes/notifications.php';

$success_message = '';
$error_message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $notification_id = (int)$_POST['notification_id'];
        if (mark_notification_read($notification_id)) {
            $success_message = "Notification marked as read!";
        } else {
            $error_message = "Error marking notification as read.";
        }
    } elseif (isset($_POST['mark_all_read'])) {
        if (mark_all_notifications_read($_SESSION['user_id'])) {
            $success_message = "All notifications marked as read!";
        } else {
            $error_message = "Error marking notifications as read.";
        }
    } elseif (isset($_POST['delete_notification'])) {
        $notification_id = (int)$_POST['notification_id'];
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $success_message = "Notification deleted!";
        } else {
            $error_message = "Error deleting notification.";
        }
        $stmt->close();
    }
}

// Get all notifications
$notifications = get_all_notifications($_SESSION['user_id'], 50);
$unread_count = get_unread_count($_SESSION['user_id']);
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-2"><i class="bi bi-bell me-2"></i>Notifications</h1>
                <p class="text-muted mb-0">Manage your system notifications and alerts</p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($unread_count > 0): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                            <i class="bi bi-check-all me-2"></i>Mark All Read
                        </button>
                    </form>
                <?php endif; ?>
                <button class="btn btn-outline-secondary" onclick="refreshNotifications()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
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
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>All Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-primary ms-2"><?php echo $unread_count; ?> unread</span>
                        <?php endif; ?>
                    </h5>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-secondary active" onclick="filterNotifications('all')">
                            All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="filterNotifications('unread')">
                            Unread
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash fs-1 text-muted"></i>
                        <h5 class="mt-3 text-muted">No notifications</h5>
                        <p class="text-muted">You're all caught up! No notifications to display.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush" id="notifications-container">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="list-group-item notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" 
                                 data-id="<?php echo $notification['id']; ?>" 
                                 data-read="<?php echo $notification['is_read']; ?>"
                                 style="cursor: pointer;">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0 me-3">
                                        <i class="bi <?php echo get_notification_icon($notification['type']); ?> <?php echo get_notification_color($notification['type']); ?> fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1 <?php echo $notification['is_read'] ? '' : 'fw-bold'; ?>">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                </h6>
                                                <p class="mb-1 text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <small class="text-muted">
                                                    <?php echo format_notification_time($notification['created_at']); ?>
                                                    <?php if ($notification['is_read']): ?>
                                                        · <i class="bi bi-check text-success"></i> Read
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" 
                                                        onclick="event.stopPropagation();">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if (!$notification['is_read']): ?>
                                                        <li>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                                <button type="submit" name="mark_read" class="dropdown-item">
                                                                    <i class="bi bi-check me-2"></i>Mark as read
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($notification['link']): ?>
                                                        <li><a class="dropdown-item go-to-link" data-id="<?php echo $notification['id']; ?>" href="/jor-app-rpl/<?php echo ltrim(htmlspecialchars($notification['link']), '/'); ?>">
                                                            <i class="bi bi-arrow-right me-2"></i>Go to link
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                            <button type="submit" name="delete_notification" class="dropdown-item text-danger" 
                                                                    onclick="return confirm('Are you sure you want to delete this notification?')">
                                                                <i class="bi bi-trash me-2"></i>Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Filter notifications
function filterNotifications(filter) {
    const items = document.querySelectorAll('.notification-item');
    const buttons = document.querySelectorAll('.btn-group .btn');
    
    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    items.forEach(item => {
        const isRead = item.dataset.read === '1';
        
        if (filter === 'all') {
            item.style.display = 'block';
        } else if (filter === 'unread') {
            item.style.display = isRead ? 'none' : 'block';
        }
    });
}

// Refresh notifications
function refreshNotifications() {
    location.reload();
}

// Auto-refresh notifications every 30 seconds
setInterval(function() {
    // Only refresh if user is on notifications page
    if (window.location.pathname.includes('notifications.php')) {
        refreshNotifications();
    }
}, 30000);

// Mark notification as read when clicked
document.addEventListener('DOMContentLoaded', function() {
    const notificationItems = document.querySelectorAll('.notification-item');
    
    notificationItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Don't trigger if clicking on dropdown or buttons
            if (e.target.closest('.dropdown') || e.target.closest('button') || e.target.closest('form')) {
                return;
            }
            
            const notificationId = this.dataset.id;
            const isRead = this.dataset.read === '1';
            
            if (!isRead) {
                // Add loading state
                this.classList.add('loading');
                
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
                        // Remove loading state
                        this.classList.remove('loading');
                        
                        // Add animation for marking as read
                        this.style.transition = 'all 0.3s ease';
                        this.classList.remove('unread');
                        this.classList.add('marking-read');
                        this.dataset.read = '1';
                        
                        // Update title styling
                        const title = this.querySelector('h6');
                        if (title) {
                            title.classList.remove('fw-bold');
                        }
                        
                        // Add read indicator with animation
                        const timeElement = this.querySelector('small');
                        if (timeElement && !timeElement.querySelector('.bi-check')) {
                            const readIndicator = document.createElement('span');
                            readIndicator.innerHTML = ' · <i class="bi bi-check text-success"></i> Read';
                            readIndicator.style.opacity = '0';
                            timeElement.appendChild(readIndicator);
                            
                            // Animate the read indicator
                            setTimeout(() => {
                                readIndicator.style.transition = 'opacity 0.3s ease';
                                readIndicator.style.opacity = '1';
                            }, 100);
                        }
                        
                        // Update unread count in header
                        updateUnreadCount();
                        
                        // Show success indicator
                        const successIndicator = document.createElement('div');
                        successIndicator.className = 'position-absolute top-0 end-0 m-2';
                        successIndicator.innerHTML = '<i class="bi bi-check-circle-fill text-success fs-5"></i>';
                        successIndicator.style.opacity = '0';
                        successIndicator.style.transition = 'opacity 0.3s ease';
                        this.style.position = 'relative';
                        this.appendChild(successIndicator);
                        
                        // Animate success indicator
                        setTimeout(() => {
                            successIndicator.style.opacity = '1';
                        }, 100);
                        
                        // Remove success indicator after 2 seconds
                        setTimeout(() => {
                            successIndicator.style.opacity = '0';
                            setTimeout(() => {
                                successIndicator.remove();
                            }, 300);
                        }, 2000);
                        
                        // Remove animation class after animation completes
                        setTimeout(() => {
                            this.classList.remove('marking-read');
                        }, 300);
                    } else {
                        // Remove loading state on error
                        this.classList.remove('loading');
                        console.error('Error marking notification as read:', data.message);
                    }
                })
                .catch(error => {
                    // Remove loading state on error
                    this.classList.remove('loading');
                    console.error('Error marking notification as read:', error);
                });
            }
            
            // Navigate to link if available
            const link = this.querySelector('a[href]');
            if (link) {
                window.location.href = link.href;
            }
        });
    });

    document.querySelectorAll('.go-to-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const notificationId = this.dataset.id;
            const href = this.getAttribute('href');
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'notification_id=' + notificationId
            })
            .finally(() => {
                window.location.href = href;
            });
        });
    });
});

// Update unread count in header
function updateUnreadCount() {
    const badge = document.querySelector('.notification-bell .badge');
    if (badge) {
        const currentCount = parseInt(badge.textContent);
        if (currentCount > 1) {
            badge.textContent = currentCount - 1;
        } else {
            badge.remove();
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?> 