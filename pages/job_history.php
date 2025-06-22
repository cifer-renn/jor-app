<?php 
$page_title = "My Job History";
require_once '../includes/database.php';
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'machine_operator') {
    header('Location: ../login.php');
    exit();
}

$operator_id = $_SESSION['user_id'];

// Fetch completed jobs for the operator
$completed_jobs = [];
$stmt = $conn->prepare("
    SELECT j.*, s.username as supervisor_name 
    FROM jobs j 
    JOIN users s ON j.supervisor_id = s.id 
    WHERE j.operator_id = ? AND j.status = 'completed'
    ORDER BY j.updated_at DESC
");
$stmt->bind_param("i", $operator_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $completed_jobs[] = $row;
}
$stmt->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-clock-history me-2"></i>My Job History</h1>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($completed_jobs)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                <p class="text-muted">You have not completed any jobs yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Supervisor</th>
                            <th>Date Completed</th>
                            <th>View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completed_jobs as $job): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($job['title']); ?></td>
                                <td><?php echo htmlspecialchars($job['supervisor_name']); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($job['updated_at'])); ?></td>
                                <td>
                                    <a href="view_operator_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 