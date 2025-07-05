<?php 
$page_title = "Manage Materials";
include '../includes/header.php'; 

// Check if user is logged in and has warehouse manager role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warehouse_manager') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$success_message = '';
$error_message = '';

// Handle status updates for applications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_application_status'])) {
    $app_id = (int)$_POST['application_id'];
    $new_status = $_POST['new_status'];
    $admin_notes = trim($_POST['admin_notes']);
    
    if (empty($new_status)) {
        $error_message = "Please select a status.";
    } else {
        $conn->begin_transaction();
        try {
            // Update application status
            $update_stmt = $conn->prepare("UPDATE material_applications SET 
                status = ?, 
                admin_notes = ?, 
                processed_by_id = ?, 
                processed_at = NOW() 
                WHERE id = ?");
            $update_stmt->bind_param("ssii", $new_status, $admin_notes, $_SESSION['user_id'], $app_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Add to history
            $history_stmt = $conn->prepare("INSERT INTO material_application_history 
                (application_id, status, notes, changed_by_id) VALUES (?, ?, ?, ?)");
            $history_stmt->bind_param("issi", $app_id, $new_status, $admin_notes, $_SESSION['user_id']);
            $history_stmt->execute();
            $history_stmt->close();
            
            $conn->commit();
            $success_message = "Application status updated successfully!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error updating application: " . $e->getMessage();
        }
    }
}

// Handle status updates for requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request_status'])) {
    $request_id = (int)$_POST['request_id'];
    $new_status = $_POST['new_status'] ?? '';

    if (!empty($new_status) && in_array($new_status, ['acknowledged', 'resolved'])) {
        $stmt = $conn->prepare("UPDATE material_requests SET status = ?, handled_by_id = ?, handled_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $new_status, $_SESSION['user_id'], $request_id);
        if ($stmt->execute()) {
            $success_message = "Request status updated successfully.";
        } else {
            $error_message = "Error updating status.";
        }
        $stmt->close();
    }
}

// Filtering and searching
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search_query = $_GET['search'] ?? '';

$sql_conditions = [];
$sql_params = [];
$sql_param_types = "";

if (!empty($type_filter)) {
    $sql_conditions[] = "type = ?";
    $sql_params[] = $type_filter;
    $sql_param_types .= "s";
}

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

if (!empty($search_query)) {
    $sql_conditions[] = "(applicant_name LIKE ? OR work_unit LIKE ? OR problem_description LIKE ? OR request_notes LIKE ?)";
    $search_term = "%{$search_query}%";
    $sql_params[] = $search_term;
    $sql_params[] = $search_term;
    $sql_params[] = $search_term;
    $sql_params[] = $search_term;
    $sql_param_types .= "ssss";
}

$where_clause = !empty($sql_conditions) ? "WHERE " . implode(" AND ", $sql_conditions) : "";

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM (
    SELECT 'application' as type, id, applicant_name, work_unit, problem_description, priority, status, created_at, submitted_by_id, processed_by_id
    FROM material_applications
    UNION ALL
    SELECT 'request' as type, id, '' as applicant_name, '' as work_unit, request_notes as problem_description, 'normal' as priority, status, created_at, requested_by_id as submitted_by_id, handled_by_id as processed_by_id
    FROM material_requests
) combined $where_clause");
if (!empty($sql_params)) {
    $count_stmt->bind_param($sql_param_types, ...$sql_params);
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_items / $limit);
$count_stmt->close();

// Fetch combined data
$materials = [];
$sql = "SELECT * FROM (
    SELECT 'application' as type, id, applicant_name, work_unit, problem_description, priority, status, created_at, submitted_by_id, processed_by_id, NULL as job_id, NULL as job_title
    FROM material_applications
    UNION ALL
    SELECT 'request' as type, id, '' as applicant_name, '' as work_unit, request_notes as problem_description, 'normal' as priority, status, created_at, requested_by_id as submitted_by_id, handled_by_id as processed_by_id, job_id, (SELECT title FROM jobs WHERE id = material_requests.job_id) as job_title
    FROM material_requests
) combined $where_clause 
ORDER BY 
    CASE priority 
        WHEN 'urgent' THEN 1 
        WHEN 'high' THEN 2 
        WHEN 'normal' THEN 3 
        WHEN 'low' THEN 4 
    END,
    created_at DESC 
LIMIT ? OFFSET ?";

$sql_params[] = $limit;
$sql_params[] = $offset;
$sql_param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($sql_param_types, ...$sql_params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $materials[] = $row;
}
$stmt->close();

// Get statistics
$stats = [];
$stats_stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN type = 'application' THEN 1 ELSE 0 END) as applications,
    SUM(CASE WHEN type = 'request' THEN 1 ELSE 0 END) as requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' OR status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN priority = 'urgent' AND status = 'pending' THEN 1 ELSE 0 END) as urgent_pending
    FROM (
        SELECT 'application' as type, status, priority FROM material_applications
        UNION ALL
        SELECT 'request' as type, status, 'normal' as priority FROM material_requests
    ) combined");
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-2"><i class="bi bi-box-seam me-2"></i>Manage Materials</h1>
                <p class="text-muted mb-0">Review and process material applications and requests</p>
            </div>
            <div class="d-flex gap-2">
                <a href="warehouse_dashboard.php" class="btn btn-outline-secondary btn-icon">
                    <i class="bi bi-arrow-left"></i>
                    Back to Dashboard
                </a>

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

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center">
                <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                <small>Total Items</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center">
                <h3 class="mb-1"><?php echo $stats['applications']; ?></h3>
                <small>Applications</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white h-100">
            <div class="card-body text-center">
                <h3 class="mb-1"><?php echo $stats['requests']; ?></h3>
                <small>Requests</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white h-100">
            <div class="card-body text-center">
                <h3 class="mb-1"><?php echo $stats['pending']; ?></h3>
                <small>Pending</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center">
                <h3 class="mb-1"><?php echo $stats['resolved']; ?></h3>
                <small>Resolved</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body text-center">
                <h3 class="mb-1"><?php echo $stats['urgent_pending']; ?></h3>
                <small>Urgent Pending</small>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form action="manage_materials.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All Types</option>
                    <option value="application" <?php echo $type_filter === 'application' ? 'selected' : ''; ?>>Applications</option>
                    <option value="request" <?php echo $type_filter === 'request' ? 'selected' : ''; ?>>Requests</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="acknowledged" <?php echo $status_filter === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                    <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="priority" class="form-label">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="">All Priorities</option>
                    <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="normal" <?php echo $priority_filter === 'normal' ? 'selected' : ''; ?>>Normal</option>
                    <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
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
            <div class="col-md-2">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search...">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="manage_materials.php" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Materials Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($materials)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                <p class="text-muted">No materials found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Details</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $item): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $item['type'] === 'application' ? 'info' : 'warning'; ?>">
                                        <?php echo ucfirst($item['type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <?php if ($item['type'] === 'application'): ?>
                                            <strong><?php echo htmlspecialchars($item['applicant_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['work_unit']); ?></small>
                                        <?php else: ?>
                                            <strong>Job: <?php echo htmlspecialchars($item['job_title']); ?></strong><br>
                                            <small class="text-muted">Request</small>
                                        <?php endif; ?>
                                        <br>
                                        <small><?php echo htmlspecialchars(substr($item['problem_description'], 0, 100)); ?><?php echo strlen($item['problem_description']) > 100 ? '...' : ''; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($item['type'] === 'application'): ?>
                                        <span class="badge bg-<?php 
                                            echo $item['priority'] === 'urgent' ? 'danger' : 
                                                ($item['priority'] === 'high' ? 'warning' : 
                                                ($item['priority'] === 'normal' ? 'primary' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($item['priority']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $item['status'] === 'pending' ? 'danger' : 
                                            ($item['status'] === 'approved' || $item['status'] === 'resolved' ? 'success' : 
                                            ($item['status'] === 'acknowledged' ? 'warning' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($item['created_at'])); ?></td>
                                <td>
                                    <?php if ($item['status'] === 'pending'): ?>
                                        <?php if ($item['type'] === 'application'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openApplicationModal(<?php echo $item['id']; ?>)">
                                                <i class="bi bi-pencil"></i> Process
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="update_request_status" value="acknowledged" class="btn btn-sm btn-outline-warning" title="Acknowledge">
                                                    <i class="bi bi-bell"></i>
                                                </button>
                                                <button type="submit" name="update_request_status" value="resolved" class="btn btn-sm btn-outline-success" title="Mark as Resolved">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo $type_filter; ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search_query); ?>">
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

<!-- Application Processing Modal -->
<div class="modal fade" id="applicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Process Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="application_id">
                    <div class="mb-3">
                        <label for="new_status" class="form-label">Status</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="">Select Status</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4" placeholder="Add notes about this decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_application_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openApplicationModal(applicationId) {
    document.getElementById('application_id').value = applicationId;
    new bootstrap.Modal(document.getElementById('applicationModal')).show();
}


</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 