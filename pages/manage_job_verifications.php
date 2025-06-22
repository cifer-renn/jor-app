<?php 
$page_title = "Manage Job Verifications";
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$supervisor_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle verification submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_job'])) {
    $job_id = (int)$_POST['job_id'];
    $verification_status = $_POST['verification_status'];
    $verification_notes = trim($_POST['verification_notes']);

    $conn->begin_transaction();
    try {
        // Insert verification record
        $verify_stmt = $conn->prepare("INSERT INTO job_verifications (job_id, verified_by_id, verification_status, verification_notes) VALUES (?, ?, ?, ?)");
        $verify_stmt->bind_param("iiss", $job_id, $supervisor_id, $verification_status, $verification_notes);
        $verify_stmt->execute();
        $verify_stmt->close();

        // Update job verification status
        $update_stmt = $conn->prepare("UPDATE jobs SET verification_status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $verification_status, $job_id);
        $update_stmt->execute();
        $update_stmt->close();

        $conn->commit();
        $success_message = "Job verification submitted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error submitting verification: " . $e->getMessage();
    }
}

// Get pending verification jobs
$pending_jobs = [];
$stmt = $conn->prepare("SELECT j.*, o.username as operator_name, o.id as operator_id
    FROM jobs j 
    LEFT JOIN users o ON j.operator_id = o.id 
    WHERE j.status = 'completed' AND j.verification_status = 'pending_verification'
    ORDER BY j.created_at ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pending_jobs[] = $row;
}
$stmt->close();

// Get verification history
$verification_history = [];
$stmt = $conn->prepare("SELECT j.*, o.username as operator_name, v.verification_status, v.verification_notes, v.verified_at, s.username as supervisor_name
    FROM jobs j 
    LEFT JOIN users o ON j.operator_id = o.id 
    LEFT JOIN job_verifications v ON j.id = v.job_id
    LEFT JOIN users s ON v.verified_by_id = s.id
    WHERE j.verification_status IS NOT NULL AND j.verification_status != 'pending_verification'
    ORDER BY v.verified_at DESC
    LIMIT 20");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $verification_history[] = $row;
}
$stmt->close();
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="mb-2"><i class="bi bi-clipboard-check me-2"></i>Manage Job Verifications</h1>
        <p class="text-muted mb-0">Review and approve/reject completed jobs submitted by operators.</p>
    </div>
</div>

<!-- Pending Verifications -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Pending Verifications</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pending_jobs)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-all fs-1 text-success mb-3"></i>
                        <p class="text-muted">No jobs pending verification. All caught up!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-file-text me-1"></i>Job Title</th>
                                    <th><i class="bi bi-person me-1"></i>Operator</th>
                                    <th><i class="bi bi-flag me-1"></i>Priority</th>
                                    <th><i class="bi bi-calendar me-1"></i>Completed</th>
                                    <th><i class="bi bi-gear me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_jobs as $job): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
                                        <td><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($job['operator_name']); ?></td>
                                        <td>
                                            <?php
                                            $priority_class = '';
                                            $priority_icon = '';
                                            switch ($job['priority']) {
                                                case 'low': $priority_class = 'badge bg-secondary'; $priority_icon = 'bi-arrow-down'; break;
                                                case 'normal': $priority_class = 'badge bg-primary'; $priority_icon = 'bi-dash'; break;
                                                case 'important': $priority_class = 'badge bg-warning'; $priority_icon = 'bi-exclamation-triangle'; break;
                                            }
                                            ?>
                                            <span class="<?php echo $priority_class; ?>"><i class="bi <?php echo $priority_icon; ?> me-1"></i><?php echo ucfirst($job['priority']); ?></span>
                                        </td>
                                        <td><?php echo date('M j, Y H:i', strtotime($job['updated_at'])); ?></td>
                                        <td>
                                            <a href="view_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary btn-icon">
                                                <i class="bi bi-eye"></i> Review
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-success btn-icon" 
                                                    onclick="verifyJob(<?php echo $job['id']; ?>, 'approved')">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-icon" 
                                                    onclick="verifyJob(<?php echo $job['id']; ?>, 'rejected')">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
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
</div>

<!-- Verification History -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Verification History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($verification_history)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <p class="text-muted">No verification history yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-file-text me-1"></i>Job Title</th>
                                    <th><i class="bi bi-person me-1"></i>Operator</th>
                                    <th><i class="bi bi-clipboard-check me-1"></i>Status</th>
                                    <th><i class="bi bi-person-badge me-1"></i>Verified By</th>
                                    <th><i class="bi bi-calendar me-1"></i>Verified</th>
                                    <th><i class="bi bi-chat me-1"></i>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($verification_history as $job): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
                                        <td><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($job['operator_name']); ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_icon = '';
                                            switch ($job['verification_status']) {
                                                case 'approved': $status_class = 'badge bg-success'; $status_icon = 'bi-check-circle'; break;
                                                case 'rejected': $status_class = 'badge bg-danger'; $status_icon = 'bi-x-circle'; break;
                                            }
                                            ?>
                                            <span class="<?php echo $status_class; ?>"><i class="bi <?php echo $status_icon; ?> me-1"></i><?php echo ucfirst($job['verification_status']); ?></span>
                                        </td>
                                        <td><i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($job['supervisor_name']); ?></td>
                                        <td><?php echo date('M j, Y H:i', strtotime($job['verified_at'])); ?></td>
                                        <td>
                                            <?php if (!empty($job['verification_notes'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        onclick="showNotes('<?php echo htmlspecialchars($job['verification_notes']); ?>')">
                                                    <i class="bi bi-chat-text"></i> View Notes
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">No notes</span>
                                            <?php endif; ?>
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
</div>

<!-- Verification Modal -->
<div class="modal fade" id="verificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Verify Job Completion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="manage_job_verifications.php">
                <div class="modal-body">
                    <input type="hidden" name="verify_job" value="1">
                    <input type="hidden" id="verifyJobId" name="job_id">
                    <input type="hidden" id="verifyStatus" name="verification_status">
                    
                    <div class="mb-3">
                        <label for="verification_notes" class="form-label">Verification Notes</label>
                        <textarea class="form-control" id="verification_notes" name="verification_notes" rows="4" 
                                  placeholder="Provide feedback or reasons for approval/rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Notes Modal -->
<div class="modal fade" id="notesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-chat-text me-2"></i>Verification Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="notesContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function verifyJob(jobId, status) {
    document.getElementById('verifyJobId').value = jobId;
    document.getElementById('verifyStatus').value = status;
    
    const modal = new bootstrap.Modal(document.getElementById('verificationModal'));
    modal.show();
}

function showNotes(notes) {
    document.getElementById('notesContent').innerHTML = notes.replace(/\n/g, '<br>');
    const modal = new bootstrap.Modal(document.getElementById('notesModal'));
    modal.show();
}
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 