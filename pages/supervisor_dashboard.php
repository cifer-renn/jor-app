<?php 
$page_title = "Supervisor Dashboard";
include '../includes/header.php'; 

// Check if user is logged in and has the supervisor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    // Redirect to login page or an unauthorized page
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

// Get job statistics
$stats = [];
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_jobs,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_jobs,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_jobs,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs
    FROM jobs WHERE supervisor_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent jobs
$recent_jobs = [];
$stmt = $conn->prepare("SELECT j.*, u.username as operator_name 
    FROM jobs j 
    LEFT JOIN users u ON j.operator_id = u.id 
    WHERE j.supervisor_id = ? 
    ORDER BY j.created_at DESC 
    LIMIT 10");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_jobs[] = $row;
}
$stmt->close();

// Get user count by role
$user_stats = [];
$stmt = $conn->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_stats[$row['role']] = $row['count'];
}
$stmt->close();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-2"><i class="bi bi-speedometer2 me-2"></i>Supervisor Dashboard</h1>
                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Here's an overview of your operations.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="create_job.php" class="btn btn-primary btn-icon">
                    <i class="bi bi-plus-circle"></i>
                    Create Job Order
                </a>
                <a href="manage_users.php" class="btn btn-outline-primary btn-icon">
                    <i class="bi bi-people"></i>
                    Manage Users
                </a>
                <a href="supervisor_reports.php" class="btn btn-outline-success btn-icon">
                    <i class="bi bi-bar-chart-line"></i>
                    View Reports
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1"><i class="bi bi-list-task me-1"></i>Total Jobs</h6>
                        <h2 class="mb-0"><?php echo $stats['total_jobs']; ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-list-task"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1"><i class="bi bi-clock me-1"></i>Pending</h6>
                        <h2 class="mb-0"><?php echo $stats['pending_jobs']; ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1"><i class="bi bi-play-circle me-1"></i>In Progress</h6>
                        <h2 class="mb-0"><?php echo $stats['in_progress_jobs']; ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-play-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1"><i class="bi bi-check-circle me-1"></i>Completed</h6>
                        <h2 class="mb-0"><?php echo $stats['completed_jobs']; ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Jobs -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Job Orders</h5>
                <a href="manage_jobs.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-right me-1"></i>View All
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_jobs)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <p class="text-muted">No job orders found. <a href="create_job.php">Create your first job order</a>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-file-text me-1"></i>Title</th>
                                    <th><i class="bi bi-flag me-1"></i>Priority</th>
                                    <th><i class="bi bi-activity me-1"></i>Status</th>
                                    <th><i class="bi bi-person me-1"></i>Assigned To</th>
                                    <th><i class="bi bi-calendar me-1"></i>Created</th>
                                    <th><i class="bi bi-gear me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_jobs as $job): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($job['title']); ?></strong>
                                                <?php if (!empty($job['description'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($job['description'], 0, 50)) . (strlen($job['description']) > 50 ? '...' : ''); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $priority_class = '';
                                            $priority_icon = '';
                                            switch ($job['priority']) {
                                                case 'low': 
                                                    $priority_class = 'badge bg-secondary'; 
                                                    $priority_icon = 'bi-arrow-down';
                                                    break;
                                                case 'normal': 
                                                    $priority_class = 'badge bg-primary'; 
                                                    $priority_icon = 'bi-dash';
                                                    break;
                                                case 'important': 
                                                    $priority_class = 'badge bg-warning'; 
                                                    $priority_icon = 'bi-exclamation-triangle';
                                                    break;
                                            }
                                            ?>
                                            <span class="<?php echo $priority_class; ?>">
                                                <i class="bi <?php echo $priority_icon; ?> me-1"></i>
                                                <?php echo ucfirst($job['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_icon = '';
                                            switch ($job['status']) {
                                                case 'pending': 
                                                    $status_class = 'badge bg-warning'; 
                                                    $status_icon = 'bi-clock';
                                                    break;
                                                case 'in_progress': 
                                                    $status_class = 'badge bg-info'; 
                                                    $status_icon = 'bi-play-circle';
                                                    break;
                                                case 'completed': 
                                                    $status_class = 'badge bg-success'; 
                                                    $status_icon = 'bi-check-circle';
                                                    break;
                                            }
                                            ?>
                                            <span class="<?php echo $status_class; ?>">
                                                <i class="bi <?php echo $status_icon; ?> me-1"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($job['operator_name']): ?>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-person-circle me-1"></i>
                                                    <?php echo htmlspecialchars($job['operator_name']); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="bi bi-person-x me-1"></i>Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>User Statistics</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-person-badge text-primary me-2"></i>
                        <span>Supervisors</span>
                    </div>
                    <span class="badge bg-primary"><?php echo isset($user_stats['supervisor']) ? $user_stats['supervisor'] : 0; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-box-seam text-success me-2"></i>
                        <span>Warehouse Managers</span>
                    </div>
                    <span class="badge bg-success"><?php echo isset($user_stats['warehouse_manager']) ? $user_stats['warehouse_manager'] : 0; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-tools text-info me-2"></i>
                        <span>Machine Operators</span>
                    </div>
                    <span class="badge bg-info"><?php echo isset($user_stats['machine_operator']) ? $user_stats['machine_operator'] : 0; ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="create_job.php" class="btn btn-primary btn-icon">
                        <i class="bi bi-plus-circle"></i>
                        Create New Job
                    </a>
                    <a href="manage_users.php" class="btn btn-outline-primary btn-icon">
                        <i class="bi bi-people"></i>
                        Manage Users
                    </a>
                    <a href="supervisor_reports.php" class="btn btn-outline-success btn-icon">
                        <i class="bi bi-bar-chart-line"></i>
                        View Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 