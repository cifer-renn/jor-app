<?php 
$page_title = "My Material Applications";
include '../includes/header.php'; 

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$success_message = '';
$error_message = '';

// Handle application cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $app_id = (int)$_GET['cancel'];
    
    $stmt = $conn->prepare("UPDATE material_applications SET status = 'cancelled' WHERE id = ? AND submitted_by_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $app_id, $_SESSION['user_id']);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success_message = "Application cancelled successfully!";
    } else {
        $error_message = "Unable to cancel application. It may have already been processed.";
    }
    $stmt->close();
}

// Filtering and searching
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$sql_conditions = ["submitted_by_id = ?"];
$sql_params = [$_SESSION['user_id']];
$sql_param_types = "i";

if (!empty($status_filter)) {
    $sql_conditions[] = "status = ?";
    $sql_params[] = $status_filter;
    $sql_param_types .= "s";
}

if (!empty($priority_filter)) {
    $sql_conditions[] = "priority = ?";
    $sql_params[] = $priority_filter;
    $sql_param_types .= "s";
}

if (!empty($date_from)) {
    $sql_conditions[] = "DATE(created_at) >= ?";
    $sql_params[] = $date_from;
    $sql_param_types .= "s";
}

if (!empty($date_to)) {
    $sql_conditions[] = "DATE(created_at) <= ?";
    $sql_params[] = $date_to;
    $sql_param_types .= "s";
}

$where_clause = "WHERE " . implode(" AND ", $sql_conditions);

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM material_applications $where_clause");
$count_stmt->bind_param($sql_param_types, ...$sql_params);
$count_stmt->execute();
$total_applications = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_applications / $limit);
$count_stmt->close();

// Fetch applications
$applications = [];
$sql = "SELECT * FROM material_applications $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$sql_params[] = $limit;
$sql_params[] = $offset;
$sql_param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($sql_param_types, ...$sql_params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}
$stmt->close();

// Get statistics
$stats = [];
$stats_stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM material_applications WHERE submitted_by_id = ?");
$stats_stmt->bind_param("i", $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-2"><i class="bi bi-file-earmark-text me-2"></i>My Material Applications</h1>
                <p class="text-muted mb-0">Track your submitted material applications</p>
            </div>
                            <a href="material_form.php" class="btn btn-primary btn-icon">
                <i class="bi bi-plus-circle"></i>
                New Application
            </a>
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

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo $stats['total']; ?></h4>
                <small>Total</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white h-100">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo $stats['pending']; ?></h4>
                <small>Pending</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo $stats['approved']; ?></h4>
                <small>Approved</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white h-100">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo $stats['rejected']; ?></h4>
                <small>Rejected</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo $stats['cancelled']; ?></h4>
                <small>Cancelled</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo round(($stats['approved'] / max($stats['total'], 1)) * 100); ?>%</h4>
                <small>Success Rate</small>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Applications</h5>
    </div>
    <div class="card-body">
        <form action="view_my_applications.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" <?php if ($status_filter == 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="approved" <?php if ($status_filter == 'approved') echo 'selected'; ?>>Approved</option>
                    <option value="rejected" <?php if ($status_filter == 'rejected') echo 'selected'; ?>>Rejected</option>
                    <option value="cancelled" <?php if ($status_filter == 'cancelled') echo 'selected'; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="priority" class="form-label">Priority</label>
                <select id="priority" name="priority" class="form-select">
                    <option value="">All Priority</option>
                    <option value="low" <?php if ($priority_filter == 'low') echo 'selected'; ?>>Low</option>
                    <option value="normal" <?php if ($priority_filter == 'normal') echo 'selected'; ?>>Normal</option>
                    <option value="high" <?php if ($priority_filter == 'high') echo 'selected'; ?>>High</option>
                    <option value="urgent" <?php if ($priority_filter == 'urgent') echo 'selected'; ?>>Urgent</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-4 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-icon">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="view_my_applications.php" class="btn btn-secondary ms-2 btn-icon">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Applications Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($applications)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                <p class="text-muted">No applications found matching your criteria.</p>
                <a href="material_form.php" class="btn btn-primary">Submit New Application</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Application #</th>
                            <th>Applicant</th>
                            <th>Work Unit</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo str_pad($app['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($app['applicant_name']); ?></strong>
                                        <?php if (!empty($app['contact_number'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($app['contact_number']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($app['work_unit']); ?></td>
                                <td>
                                    <?php
                                    $priority_colors = [
                                        'low' => 'secondary',
                                        'normal' => 'primary',
                                        'high' => 'warning',
                                        'urgent' => 'danger'
                                    ];
                                    $priority_icons = [
                                        'low' => 'bi-arrow-down',
                                        'normal' => 'bi-dash',
                                        'high' => 'bi-exclamation-triangle',
                                        'urgent' => 'bi-exclamation-circle'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $priority_colors[$app['priority']]; ?>">
                                        <i class="bi <?php echo $priority_icons[$app['priority']]; ?> me-1"></i>
                                        <?php echo ucfirst($app['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'cancelled' => 'secondary'
                                    ];
                                    $status_icons = [
                                        'pending' => 'bi-clock',
                                        'approved' => 'bi-check-circle',
                                        'rejected' => 'bi-x-circle',
                                        'cancelled' => 'bi-x-circle'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_colors[$app['status']]; ?>">
                                        <i class="bi <?php echo $status_icons[$app['status']]; ?> me-1"></i>
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($app['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                onclick="viewApplication(<?php echo $app['id']; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($app['status'] === 'pending'): ?>
                                            <a href="view_my_applications.php?cancel=<?php echo $app['id']; ?>" 
                                               class="btn btn-sm btn-outline-warning"
                                               onclick="return confirm('Are you sure you want to cancel this application?')">
                                                <i class="bi bi-x-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Applications pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Application Details Modal -->
<div class="modal fade" id="applicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="applicationModalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewApplication(appId) {
    // Load application details via AJAX
    fetch(`get_application_details.php?id=${appId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('applicationModalBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('applicationModal')).show();
        })
        .catch(error => {
            console.error('Error loading application details:', error);
            alert('Error loading application details');
        });
}
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 