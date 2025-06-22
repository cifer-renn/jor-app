<?php 
$page_title = "Manage Job Orders";
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

// --- Handle Job Deletion ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $job_id_to_delete = (int)$_GET['delete'];
    
    // First, delete related movements to maintain referential integrity
    $stmt_delete_movements = $conn->prepare("DELETE FROM inventory_movements WHERE job_id = ?");
    $stmt_delete_movements->bind_param("i", $job_id_to_delete);
    $stmt_delete_movements->execute();
    $stmt_delete_movements->close();

    // Then, delete the job
    $stmt_delete_job = $conn->prepare("DELETE FROM jobs WHERE id = ? AND supervisor_id = ?");
    $stmt_delete_job->bind_param("ii", $job_id_to_delete, $_SESSION['user_id']);
    if ($stmt_delete_job->execute()) {
        $_SESSION['success_message'] = "Job order and related inventory movements deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting job order: " . $conn->error;
    }
    $stmt_delete_job->close();
    header('Location: manage_jobs.php');
    exit();
}


// --- Filtering and Searching ---
$search_query = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

$sql_conditions = ["j.supervisor_id = ?"];
$sql_params = [$_SESSION['user_id']];
$sql_param_types = "i";

if (!empty($search_query)) {
    $sql_conditions[] = "(j.title LIKE ? OR j.description LIKE ?)";
    $search_term = "%{$search_query}%";
    $sql_params[] = $search_term;
    $sql_params[] = $search_term;
    $sql_param_types .= "ss";
}
if (!empty($status_filter)) {
    $sql_conditions[] = "j.status = ?";
    $sql_params[] = $status_filter;
    $sql_param_types .= "s";
}
if (!empty($priority_filter)) {
    $sql_conditions[] = "j.priority = ?";
    $sql_params[] = $priority_filter;
    $sql_param_types .= "s";
}

$where_clause = !empty($sql_conditions) ? "WHERE " . implode(" AND ", $sql_conditions) : "";

// --- Pagination ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total number of jobs for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM jobs j $where_clause");
$count_stmt->bind_param($sql_param_types, ...$sql_params);
$count_stmt->execute();
$total_jobs = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_jobs / $limit);
$count_stmt->close();

// --- Fetch Jobs for current page ---
$jobs = [];
$sql = "SELECT j.*, u.username as operator_name 
        FROM jobs j 
        LEFT JOIN users u ON j.operator_id = u.id 
        $where_clause
        ORDER BY j.created_at DESC 
        LIMIT ? OFFSET ?";
        
$sql_params[] = $limit;
$sql_params[] = $offset;
$sql_param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($sql_param_types, ...$sql_params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $jobs[] = $row;
}
$stmt->close();

// Display session messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-list-task me-2"></i>Manage Job Orders</h1>
    <a href="create_job.php" class="btn btn-primary btn-icon">
        <i class="bi bi-plus-circle"></i> Create New Job
    </a>
</div>

<!-- Filter and Search Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter & Search</h5>
    </div>
    <div class="card-body">
        <form action="manage_jobs.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Search by title or description..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    <option value="pending" <?php if ($status_filter == 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="in_progress" <?php if ($status_filter == 'in_progress') echo 'selected'; ?>>In Progress</option>
                    <option value="completed" <?php if ($status_filter == 'completed') echo 'selected'; ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="priority" class="form-label">Priority</label>
                <select id="priority" name="priority" class="form-select">
                    <option value="">All</option>
                    <option value="low" <?php if ($priority_filter == 'low') echo 'selected'; ?>>Low</option>
                    <option value="normal" <?php if ($priority_filter == 'normal') echo 'selected'; ?>>Normal</option>
                    <option value="important" <?php if ($priority_filter == 'important') echo 'selected'; ?>>Important</option>
                </select>
            </div>
            <div class="col-md-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-search"></i> Filter</button>
                <a href="manage_jobs.php" class="btn btn-secondary ms-2 btn-icon"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if ($success_message): ?>
<div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_message; ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?></div>
<?php endif; ?>

<!-- Jobs Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($jobs)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                <p class="text-muted">No job orders found matching your criteria.</p>
                <a href="manage_jobs.php" class="btn btn-primary">Reset Filters</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
                                <td><?php echo $job['operator_name'] ? htmlspecialchars($job['operator_name']) : '<span class="text-muted">Unassigned</span>'; ?></td>
                                <td><span class="badge bg-<?php echo ['low' => 'secondary', 'normal' => 'primary', 'important' => 'warning'][$job['priority']]; ?>"><?php echo ucfirst($job['priority']); ?></span></td>
                                <td><span class="badge bg-<?php echo ['pending' => 'warning', 'in_progress' => 'info', 'completed' => 'success'][$job['status']]; ?>"><?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="manage_jobs.php?delete=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this job? This action cannot be undone.')"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <?php if($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-end">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 