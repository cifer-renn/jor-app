<?php 
$page_title = "View Job Details";
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'machine_operator') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$job = null;
$job_requirements = [];

if ($job_id > 0) {
    // Fetch job details, ensuring it's assigned to this operator
    $stmt = $conn->prepare("SELECT j.*, s.username as supervisor_name 
        FROM jobs j 
        JOIN users s ON j.supervisor_id = s.id 
        WHERE j.id = ? AND j.operator_id = ?");
    $stmt->bind_param("ii", $job_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $job = $result->fetch_assoc();
    }
    $stmt->close();

    // Fetch job requirements if job exists
    if ($job) {
        $req_stmt = $conn->prepare("
            SELECT jr.quantity_required, i.name, i.quantity as stock_on_hand
            FROM job_requirements jr
            JOIN inventory i ON jr.inventory_id = i.id
            WHERE jr.job_id = ?
        ");
        $req_stmt->bind_param("i", $job_id);
        $req_stmt->execute();
        $job_requirements = $req_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $req_stmt->close();
    }
}

if (!$job) {
    $_SESSION['error_message'] = "Job not found or you are not assigned to it.";
    header('Location: operator_dashboard.php');
    exit();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Job Details</h1>
                <a href="operator_dashboard.php" class="btn btn-secondary btn-icon"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0"><?php echo htmlspecialchars($job['title']); ?></h4>
                </div>
                <div class="card-body">
                    <p class="card-text text-muted"><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                    <hr>
                    <div class="row">
                        <div class="col-md-4"><strong>Priority:</strong> <?php echo ucfirst($job['priority']); ?></div>
                        <div class="col-md-4"><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?></div>
                        <div class="col-md-4"><strong>Supervisor:</strong> <?php echo htmlspecialchars($job['supervisor_name']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Required Materials</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($job_requirements)): ?>
                        <p class="text-muted">No materials required for this job.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($job_requirements as $req): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <?php echo htmlspecialchars($req['name']); ?>
                                        <br>
                                        <small class="text-muted">Required: <?php echo $req['quantity_required']; ?></small>
                                    </span>
                                    <?php 
                                        $is_sufficient = $req['stock_on_hand'] >= $req['quantity_required'];
                                        $stock_badge_class = $is_sufficient ? 'bg-success' : 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $stock_badge_class; ?> rounded-pill">
                                        <i class="bi <?php echo $is_sufficient ? 'bi-check-circle' : 'bi-x-circle'; ?> me-1"></i>
                                        Stock: <?php echo $req['stock_on_hand']; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 