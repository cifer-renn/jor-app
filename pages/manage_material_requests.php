<?php 
$page_title = "Manage Material Requests";

// Establish database connection first
require_once '../includes/database.php';
// Now include the header, which can use the $conn variable
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'warehouse_manager') {
    header('Location: ../login.php');
    exit();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = (int)$_POST['request_id'];
    $new_status = $_POST['new_status'];

    if (in_array($new_status, ['acknowledged', 'resolved'])) {
        $stmt = $conn->prepare("UPDATE material_requests SET status = ?, handled_by_id = ?, handled_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $new_status, $_SESSION['user_id'], $request_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Request status updated successfully.";
        } else {
            $_SESSION['error_message'] = "Error updating status.";
        }
        $stmt->close();
        header("Location: manage_material_requests.php");
        exit();
    }
}


// Fetch material requests
$requests = [];
$stmt = $conn->prepare("
    SELECT 
        mr.*, 
        j.title as job_title, 
        u_req.username as operator_name,
        u_hand.username as handler_name
    FROM material_requests mr
    JOIN jobs j ON mr.job_id = j.id
    JOIN users u_req ON mr.requested_by_id = u_req.id
    LEFT JOIN users u_hand ON mr.handled_by_id = u_hand.id
    ORDER BY mr.status = 'pending' DESC, mr.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Manage Material Requests</h1>
</div>

<?php include '../includes/messages.php'; ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($requests)): ?>
            <div class="text-center py-5">
                <i class="bi bi-bell-slash fs-1 text-muted mb-3"></i>
                <p class="text-muted">No material requests found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Requested By</th>
                            <th>Notes</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Handled By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td>
                                    <a href="view_job.php?id=<?php echo $req['job_id']; ?>" title="View Job Details">
                                        <?php echo htmlspecialchars($req['job_title']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($req['operator_name']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($req['request_notes'])); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $req['status'] === 'pending' ? 'danger' : ($req['status'] === 'acknowledged' ? 'warning' : 'success'); ?>">
                                        <?php echo ucfirst($req['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $req['handler_name'] ? htmlspecialchars($req['handler_name']) . '<br><small>' . date('M j, Y', strtotime($req['handled_at'])) . '</small>' : 'N/A'; ?></td>
                                <td>
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" name="new_status" value="acknowledged" class="btn btn-sm btn-outline-warning" title="Acknowledge">
                                                <i class="bi bi-bell"></i>
                                            </button>
                                            <button type="submit" name="new_status" value="resolved" class="btn btn-sm btn-outline-success" title="Mark as Resolved">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        </form>
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

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 