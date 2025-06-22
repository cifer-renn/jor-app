<?php 
$page_title = "View Job Order";
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'machine_operator') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($job_id <= 0) {
    header('Location: ../index.php');
    exit();
}

// Prepare the main query based on role
$sql = "SELECT j.*, s.username as supervisor_name, o.username as operator_name
        FROM jobs j 
        LEFT JOIN users s ON j.supervisor_id = s.id 
        LEFT JOIN users o ON j.operator_id = o.id 
        WHERE j.id = ?";
$params = [$job_id];
$types = "i";

// Supervisors can only view their own jobs
if ($_SESSION['role'] === 'supervisor') {
    $sql .= " AND j.supervisor_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}
// Operators can only view jobs assigned to them
elseif ($_SESSION['role'] === 'machine_operator') {
    $sql .= " AND j.operator_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Redirect to the appropriate dashboard if job not found or no permission
    $dashboard = $_SESSION['role'] === 'supervisor' ? 'supervisor_dashboard.php' : 'operator_dashboard.php';
    header("Location: $dashboard");
    exit();
}
$job = $result->fetch_assoc();
$stmt->close();

// Fetch associated inventory movements
$movements = [];
$stmt_mov = $conn->prepare(
   "SELECT im.*, i.name as item_name 
    FROM inventory_movements im
    JOIN inventory i ON im.inventory_id = i.id
    WHERE im.job_id = ? 
    ORDER BY im.moved_at DESC"
);
$stmt_mov->bind_param("i", $job_id);
$stmt_mov->execute();
$result_mov = $stmt_mov->get_result();
while($row = $result_mov->fetch_assoc()) {
    $movements[] = $row;
}
$stmt_mov->close();

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Job Details</h1>
    <div>
        <?php if ($_SESSION['role'] === 'supervisor'): ?>
            <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-primary btn-icon"><i class="bi bi-pencil"></i> Edit</a>
            <a href="manage_jobs.php" class="btn btn-secondary btn-icon"><i class="bi bi-arrow-left"></i> Back to Manage Jobs</a>
        <?php else: ?>
            <a href="operator_dashboard.php" class="btn btn-secondary btn-icon"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Left Column: Main Details -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0"><?php echo htmlspecialchars($job['title']); ?></h4>
            </div>
            <div class="card-body">
                <p class="card-text">
                    <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Inventory Movements</h5>
            </div>
            <div class="card-body">
                <?php if (empty($movements)): ?>
                    <p class="text-muted text-center"><i class="bi bi-box me-2"></i>No inventory items have been used for this job yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Quantity</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movements as $mov): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($mov['item_name']); ?></td>
                                    <td><?php echo abs($mov['quantity_change']); ?></td>
                                    <td>
                                        <?php if ($mov['movement_type'] == 'out'): ?>
                                            <span class="badge bg-danger">Used</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Returned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($mov['moved_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Metadata -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Information</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Status</strong>
                        <span class="badge fs-6 bg-<?php echo ['pending' => 'warning', 'in_progress' => 'info', 'completed' => 'success'][$job['status']]; ?>"><?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Priority</strong>
                        <span class="badge fs-6 bg-<?php echo ['low' => 'secondary', 'normal' => 'primary', 'important' => 'warning'][$job['priority']]; ?>"><?php echo ucfirst($job['priority']); ?></span>
                    </li>
                    <li class="list-group-item">
                        <strong>Assigned To</strong><br>
                        <i class="bi bi-person-check me-1 text-muted"></i>
                        <?php echo $job['operator_name'] ? htmlspecialchars($job['operator_name']) : '<span class="text-muted">Unassigned</span>'; ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Supervisor</strong><br>
                        <i class="bi bi-person-badge me-1 text-muted"></i>
                        <?php echo htmlspecialchars($job['supervisor_name']); ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Created</strong><br>
                        <i class="bi bi-calendar-plus me-1 text-muted"></i>
                        <?php echo date('M j, Y, g:i A', strtotime($job['created_at'])); ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Last Updated</strong><br>
                        <i class="bi bi-calendar-check me-1 text-muted"></i>
                        <?php echo date('M j, Y, g:i A', strtotime($job['updated_at'])); ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 