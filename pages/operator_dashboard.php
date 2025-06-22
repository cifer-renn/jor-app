<?php 
$page_title = "Operator Dashboard";
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'machine_operator') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$operator_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Function to check stock and get requirements
function check_stock_for_job($conn, $job_id) {
    $req_stmt = $conn->prepare("
        SELECT jr.inventory_id, jr.quantity_required, i.name, i.quantity as stock_on_hand
        FROM job_requirements jr
        JOIN inventory i ON jr.inventory_id = i.id
        WHERE jr.job_id = ?
    ");
    $req_stmt->bind_param("i", $job_id);
    $req_stmt->execute();
    $requirements = $req_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $req_stmt->close();

    $insufficient_items = [];
    foreach ($requirements as $req) {
        if ($req['stock_on_hand'] < $req['quantity_required']) {
            $insufficient_items[] = $req;
        }
    }
    return ['requirements' => $requirements, 'insufficient' => $insufficient_items];
}

// Handle Material Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_materials'])) {
    $job_id = (int)$_POST['job_id'];
    $notes = trim($_POST['request_notes']);

    $req_stmt = $conn->prepare("INSERT INTO material_requests (job_id, requested_by_id, request_notes) VALUES (?, ?, ?)");
    $req_stmt->bind_param("iis", $job_id, $operator_id, $notes);
    if ($req_stmt->execute()) {
        $success_message = "Material request submitted successfully.";
    } else {
        $error_message = "Failed to submit material request.";
    }
    $req_stmt->close();
}

// Handle "Take Job" submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['take_job'])) {
    $job_id = (int)$_POST['job_id'];

    // Atomically assign the job to the current operator
    $take_stmt = $conn->prepare("UPDATE jobs SET operator_id = ?, status = 'pending' WHERE id = ? AND operator_id IS NULL");
    $take_stmt->bind_param("ii", $operator_id, $job_id);
    
    if ($take_stmt->execute() && $take_stmt->affected_rows > 0) {
        $success_message = "Job successfully assigned to you!";
    } else {
        $error_message = "Failed to take the job. It may have already been assigned to another operator.";
    }
    $take_stmt->close();
}

// Handle Job Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $job_id = (int)$_POST['job_id'];
    $new_status = $_POST['new_status'];
    $notes = trim($_POST['notes']);
    $is_resubmit = isset($_POST['resubmit']) && $_POST['resubmit'] === '1';

    // Handle re-submission of a rejected job
    if ($is_resubmit) {
        $update_stmt = $conn->prepare("UPDATE jobs SET verification_status = 'pending_verification', status = 'completed', notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ? AND operator_id = ?");
        $formatted_note = "\n[Re-submitted by Operator on " . date('Y-m-d H:i') . "]: " . $notes;
        $update_stmt->bind_param("sii", $formatted_note, $job_id, $operator_id);
        if ($update_stmt->execute()) {
            $success_message = "Job re-submitted for verification successfully!";
        } else {
            $error_message = "Error re-submitting job.";
        }
        $update_stmt->close();
    }
    // Handle normal status updates
    else if ($new_status === 'in_progress' || $new_status === 'completed') {
        $stock_check = check_stock_for_job($conn, $job_id);
        if (!empty($stock_check['insufficient'])) {
            $item_list = implode(', ', array_map(fn($item) => $item['name'], $stock_check['insufficient']));
            $error_message = "Cannot proceed: Insufficient stock for {$item_list}. Please request materials.";
        } else {
            // All clear, proceed with update
            if ($new_status === 'completed') {
                $conn->begin_transaction();
                try {
                    // 1. Deduct inventory and create movement records
                    foreach ($stock_check['requirements'] as $req) {
                        // Deduct from inventory
                        $inv_update = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
                        $inv_update->bind_param("ii", $req['quantity_required'], $req['inventory_id']);
                        $inv_update->execute();
                        $inv_update->close();

                        // Log the movement
                        $move_stmt = $conn->prepare("INSERT INTO inventory_movements (inventory_id, job_id, quantity_change, movement_type, moved_by_id, notes) VALUES (?, ?, ?, 'out', ?, ?)");
                        $movement_note = "Used for job completion.";
                        $move_stmt->bind_param("iiiis", $req['inventory_id'], $job_id, $req['quantity_required'], $operator_id, $movement_note);
                        $move_stmt->execute();
                        $move_stmt->close();
                    }

                    // 2. Update job status
                    $update_stmt = $conn->prepare("UPDATE jobs SET status = ?, verification_status = 'pending_verification', notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ? AND operator_id = ?");
                    $formatted_note = "\n[Completed by Operator on " . date('Y-m-d H:i') . "]: " . $notes;
                    $update_stmt->bind_param("ssii", $new_status, $formatted_note, $job_id, $operator_id);
                    $update_stmt->execute();
                    
                    if ($update_stmt->affected_rows === 0) {
                        throw new Exception("You are not authorized to update this job or job not found.");
                    }
                    $update_stmt->close();

                    $conn->commit();
                    $success_message = "Job completed and submitted for supervisor verification!";

                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = $e->getMessage();
                }
            } else { // 'in_progress'
                $check_stmt = $conn->prepare("SELECT id FROM jobs WHERE id = ? AND operator_id = ?");
                $check_stmt->bind_param("ii", $job_id, $operator_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows === 1) {
                    $update_stmt = $conn->prepare("UPDATE jobs SET status = ?, notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?");
                    $formatted_note = "\n[Update by Operator on " . date('Y-m-d H:i') . "]: " . $notes;
                    $update_stmt->bind_param("ssi", $new_status, $formatted_note, $job_id);

                    if ($update_stmt->execute()) {
                        $success_message = "Job status updated successfully!";
                    } else {
                        $error_message = "Error updating job status.";
                    }
                    $update_stmt->close();
                } else {
                    $error_message = "You are not authorized to update this job.";
                }
                $check_stmt->close();
            }
        }
    } else if ($new_status === 'pending') {
        $check_stmt = $conn->prepare("SELECT id FROM jobs WHERE id = ? AND operator_id = ?");
        $check_stmt->bind_param("ii", $job_id, $operator_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 1) {
            $update_stmt = $conn->prepare("UPDATE jobs SET status = ?, notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?");
            $formatted_note = "\n[Update by Operator on " . date('Y-m-d H:i') . "]: " . $notes;
            $update_stmt->bind_param("ssi", $new_status, $formatted_note, $job_id);

            if ($update_stmt->execute()) {
                $success_message = "Job status updated successfully!";
            } else {
                $error_message = "Error updating job status.";
            }
            $update_stmt->close();
        } else {
            $error_message = "You are not authorized to update this job.";
        }
        $check_stmt->close();
    } else {
        $error_message = "Invalid status selected.";
    }
}

// Get job statistics for this operator
$stats = [];
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_jobs,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_jobs,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_jobs,
    SUM(CASE WHEN status = 'completed' AND verification_status = 'approved' THEN 1 ELSE 0 END) as completed_jobs
    FROM jobs WHERE operator_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get assigned jobs
$assigned_jobs = [];
$stmt = $conn->prepare("SELECT j.*, s.username as supervisor_name 
    FROM jobs j 
    LEFT JOIN users s ON j.supervisor_id = s.id 
    WHERE j.operator_id = ? 
    ORDER BY 
        CASE j.status WHEN 'in_progress' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END,
        CASE j.priority WHEN 'important' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,
        j.created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assigned_jobs[] = $row;
}
$stmt->close();

// Get unassigned jobs (job pool)
$unassigned_jobs = [];
$stmt = $conn->prepare("SELECT j.*, s.username as supervisor_name 
    FROM jobs j 
    LEFT JOIN users s ON j.supervisor_id = s.id 
    WHERE j.operator_id IS NULL AND j.status = 'pending'
    ORDER BY 
        CASE j.priority WHEN 'important' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,
        j.created_at ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $unassigned_jobs[] = $row;
}
$stmt->close();

// Get pending verification jobs
$pending_verification_jobs = [];
$stmt = $conn->prepare("SELECT j.*, s.username as supervisor_name 
    FROM jobs j 
    LEFT JOIN users s ON j.supervisor_id = s.id 
    WHERE j.operator_id = ? AND j.status = 'completed' AND j.verification_status = 'pending_verification'
    ORDER BY j.created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pending_verification_jobs[] = $row;
}
$stmt->close();
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="mb-2"><i class="bi bi-tools me-2"></i>Machine Operator Dashboard</h1>
        <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Here are your assigned jobs.</p>
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
                        <h2 class="mb-0"><?php echo $stats['total_jobs'] ?? 0; ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-list-task"></i></div>
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
                        <h2 class="mb-0"><?php echo $stats['pending_jobs'] ?? 0; ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-clock"></i></div>
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
                        <h2 class="mb-0"><?php echo $stats['in_progress_jobs'] ?? 0; ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-play-circle"></i></div>
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
                        <h2 class="mb-0"><?php echo $stats['completed_jobs'] ?? 0; ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assigned Jobs -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-check-fill me-2"></i>My Active Jobs</h5>
            </div>
            <div class="card-body">
                <?php if (empty($assigned_jobs)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <p class="text-muted">You have no active jobs. Take a job from the available pool below!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-file-text me-1"></i>Title</th>
                                    <th><i class="bi bi-flag me-1"></i>Priority</th>
                                    <th><i class="bi bi-activity me-1"></i>Status</th>
                                    <th><i class="bi bi-person-badge me-1"></i>Supervisor</th>
                                    <th><i class="bi bi-calendar me-1"></i>Assigned</th>
                                    <th><i class="bi bi-gear me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_jobs as $job): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
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
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_icon = '';
                                            $status_text = '';
                                            
                                            if ($job['status'] === 'completed') {
                                                if ($job['verification_status'] === 'pending_verification') {
                                                    $status_class = 'badge bg-warning';
                                                    $status_icon = 'bi-hourglass-split';
                                                    $status_text = 'Pending Verification';
                                                } elseif ($job['verification_status'] === 'approved') {
                                                    $status_class = 'badge bg-success';
                                                    $status_icon = 'bi-check-circle';
                                                    $status_text = 'Completed';
                                                } elseif ($job['verification_status'] === 'rejected') {
                                                    $status_class = 'badge bg-danger';
                                                    $status_icon = 'bi-x-circle';
                                                    $status_text = 'Rejected';
                                                }
                                            } else {
                                                switch ($job['status']) {
                                                    case 'pending': 
                                                        $status_class = 'badge bg-warning'; 
                                                        $status_icon = 'bi-clock'; 
                                                        $status_text = 'Pending';
                                                        break;
                                                    case 'in_progress': 
                                                        $status_class = 'badge bg-info'; 
                                                        $status_icon = 'bi-play-circle'; 
                                                        $status_text = 'In Progress';
                                                        break;
                                                }
                                            }
                                            ?>
                                            <span class="<?php echo $status_class; ?>"><i class="bi <?php echo $status_icon; ?> me-1"></i><?php echo $status_text; ?></span>
                                        </td>
                                        <td><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($job['supervisor_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                        <td>
                                            <a href="view_operator_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary btn-icon">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <?php if ($job['status'] !== 'completed' || $job['verification_status'] === 'rejected'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success btn-icon" 
                                                        onclick="updateJobStatus(<?php echo $job['id']; ?>, '<?php echo $job['status']; ?>', '<?php echo $job['verification_status']; ?>')">
                                                    <i class="bi bi-pencil-square"></i> Update Status
                                                </button>
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

<!-- Available Jobs Pool -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-briefcase-fill me-2"></i>Available Jobs Pool</h5>
            </div>
            <div class="card-body">
                <?php if (empty($unassigned_jobs)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-all fs-1 text-success mb-3"></i>
                        <p class="text-muted">No available jobs at the moment. Great work!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-file-text me-1"></i>Title</th>
                                    <th><i class="bi bi-flag me-1"></i>Priority</th>
                                    <th><i class="bi bi-person-badge me-1"></i>Supervisor</th>
                                    <th><i class="bi bi-calendar me-1"></i>Created</th>
                                    <th class="text-center"><i class="bi bi-gear me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unassigned_jobs as $job): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
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
                                        <td><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($job['supervisor_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                        <td class="text-center">
                                            <form method="POST" action="operator_dashboard.php" class="d-inline">
                                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                <button type="submit" name="take_job" class="btn btn-sm btn-success btn-icon">
                                                    <i class="bi bi-hand-thumbs-up-fill me-1"></i> Take Job
                                                </button>
                                            </form>
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

<!-- Pending Verification Jobs -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Pending Verification Jobs</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pending_verification_jobs)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-all fs-1 text-success mb-3"></i>
                        <p class="text-muted">No pending verification jobs at the moment. Great work!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-file-text me-1"></i>Title</th>
                                    <th><i class="bi bi-flag me-1"></i>Priority</th>
                                    <th><i class="bi bi-person-badge me-1"></i>Supervisor</th>
                                    <th><i class="bi bi-calendar me-1"></i>Completed</th>
                                    <th class="text-center"><i class="bi bi-gear me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_verification_jobs as $job): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
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
                                        <td><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($job['supervisor_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($job['updated_at'])); ?></td>
                                        <td class="text-center">
                                            <a href="view_operator_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary btn-icon">
                                                <i class="bi bi-eye"></i> View
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
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Update Job Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div id="modal-body-content">
                <!-- Content will be loaded by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Material Request Modal -->
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-send me-2"></i>Request Materials</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="operator_dashboard.php">
                <div class="modal-body">
                    <input type="hidden" name="request_materials" value="1">
                    <input type="hidden" id="requestJobId" name="job_id">
                    <div class="mb-3">
                        <label for="request_notes" class="form-label">Notes for Warehouse Manager</label>
                        <textarea class="form-control" id="request_notes" name="request_notes" rows="4" placeholder="e.g., Please restock the following items..."></textarea>
                    </div>
                    <div id="request-insufficient-list"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
async function updateJobStatus(jobId, currentStatus, verificationStatus) {
    const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
    const modalBody = document.getElementById('modal-body-content');
    modalBody.innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    statusModal.show();

    // If the job was rejected, the only path is to resubmit. No need to check stock again.
    if (verificationStatus === 'rejected') {
        modalBody.innerHTML = `
            <form id="statusForm" method="POST" action="operator_dashboard.php">
                <div class="modal-body">
                    <input type="hidden" name="job_id" value="${jobId}">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="new_status" value="completed"> 
                    <input type="hidden" name="resubmit" value="1">
                    <p>This job was rejected by the supervisor. You can add notes and re-submit it for verification.</p>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes for Supervisor</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Explain the changes you made..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Re-submit for Verification</button>
                </div>
            </form>
        `;
        return;
    }

    try {
        const response = await fetch(`check_stock.php?job_id=${jobId}`);
        const data = await response.json();

        if (!data.sufficient) {
            let itemsList = data.items.map(item => `<li>${item.name} (Required: ${item.required}, On Hand: ${item.on_hand})</li>`).join('');
            modalBody.innerHTML = `
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h5 class="alert-heading">Insufficient Stock!</h5>
                        <p>Cannot start or complete this job due to low stock for the following items:</p>
                        <ul>${itemsList}</ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="openRequestModal(${jobId}, '${JSON.stringify(data.items)}')">
                        <i class="bi bi-send me-1"></i>Request Materials
                    </button>
                </div>
            `;
        } else {
            // Stock is sufficient, show normal update form
            modalBody.innerHTML = `
                <form id="statusForm" method="POST" action="operator_dashboard.php">
                    <div class="modal-body">
                        <input type="hidden" name="job_id" value="${jobId}">
                        <input type="hidden" name="update_status" value="1">
                        <div class="mb-3">
                            <label for="newStatus" class="form-label">New Status</label>
                            <select class="form-select" name="new_status" required>
                                <option value="in_progress" ${currentStatus === 'pending' ? 'selected' : ''}>In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            `;
        }
    } catch (error) {
        modalBody.innerHTML = '<div class="alert alert-danger m-3">Error checking stock. Please try again.</div>';
    }
}

function openRequestModal(jobId, insufficientItems) {
    const statusModal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
    statusModal.hide();

    const requestModal = new bootstrap.Modal(document.getElementById('requestModal'));
    document.getElementById('requestJobId').value = jobId;
    
    let items = JSON.parse(insufficientItems);
    let itemsList = items.map(item => `<li>${item.name}</li>`).join('');
    document.getElementById('request-insufficient-list').innerHTML = `<p class="mt-3"><strong>Items to request:</strong></p><ul>${itemsList}</ul>`;
    
    requestModal.show();
}
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 