<?php
$page_title = "Request Materials";
require_once '../includes/database.php';
include '../includes/header.php';

// Role-specific access control
if ($_SESSION['role'] !== 'machine_operator') {
    header('Location: ../login.php');
    exit();
}

$operator_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle the request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_materials'])) {
    $job_id = (int)$_POST['job_id'];
    $notes = trim($_POST['request_notes']);

    if (empty($job_id)) {
        $error_message = "You must select a job.";
    } else {
        $req_stmt = $conn->prepare("INSERT INTO material_requests (job_id, requested_by_id, request_notes) VALUES (?, ?, ?)");
        $req_stmt->bind_param("iis", $job_id, $operator_id, $notes);
        if ($req_stmt->execute()) {
            $_SESSION['success_message'] = "Material request submitted successfully.";
            header("Location: operator_dashboard.php");
            exit();
        } else {
            $error_message = "Failed to submit material request.";
        }
        $req_stmt->close();
    }
}

// Fetch assigned, non-completed jobs for the operator
$assigned_jobs = [];
$stmt = $conn->prepare("
    SELECT id, title FROM jobs 
    WHERE operator_id = ? AND status != 'completed'
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $operator_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assigned_jobs[] = $row;
}
$stmt->close();
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0"><i class="bi bi-send me-2"></i>Request Materials</h1>
        </div>

        <?php include '../includes/messages.php'; ?>

        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="request_material.php">
                    <input type="hidden" name="request_materials" value="1">

                    <div class="mb-3">
                        <label for="job_id" class="form-label">Select Job *</label>
                        <select class="form-select" id="job_id" name="job_id" required>
                            <option value="">-- Select a Job --</option>
                            <?php foreach ($assigned_jobs as $job): ?>
                                <option value="<?php echo $job['id']; ?>"><?php echo htmlspecialchars($job['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="materials-display" class="mb-3 p-3 bg-light rounded" style="display: none;">
                        <h5 class="mb-2">Required Materials</h5>
                        <div id="materials-list">
                            <!-- Content loaded via JS -->
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="request_notes" class="form-label">Notes for Warehouse</label>
                        <textarea class="form-control" id="request_notes" name="request_notes" rows="4" placeholder="Add any specific details for the warehouse manager..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-lg btn-icon" id="submit-request-btn" disabled>
                            <i class="bi bi-send-check me-2"></i>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const jobSelect = document.getElementById('job_id');
    const materialsDisplay = document.getElementById('materials-display');
    const materialsList = document.getElementById('materials-list');
    const submitButton = document.getElementById('submit-request-btn');

    jobSelect.addEventListener('change', async function() {
        const jobId = this.value;
        materialsDisplay.style.display = 'none';
        materialsList.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        submitButton.disabled = true;

        if (!jobId) return;

        try {
            const response = await fetch(`check_stock.php?job_id=${jobId}`);
            if (!response.ok) throw new Error('Network response was not ok.');
            
            const data = await response.json();

            materialsDisplay.style.display = 'block';
            if (data.items && data.items.length > 0) {
                let html = '<ul class="list-group">';
                data.items.forEach(item => {
                    let stockClass = item.on_hand >= item.required ? 'text-success' : 'text-danger fw-bold';
                    html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                ${item.name}
                                <span class="${stockClass}">Required: ${item.required} / On Hand: ${item.on_hand}</span>
                             </li>`;
                });
                html += '</ul>';
                materialsList.innerHTML = html;
            } else if (data.sufficient === true && data.items.length === 0) {
                 materialsList.innerHTML = '<p class="text-muted mb-0">No specific materials are required for this job, or stock is sufficient.</p>';
            } else {
                 materialsList.innerHTML = '<p class="text-danger mb-0">Could not retrieve material information.</p>';
            }
            submitButton.disabled = false;

        } catch (error) {
            materialsDisplay.style.display = 'block';
            materialsList.innerHTML = '<p class="text-danger mb-0">Error loading materials. Please try again.</p>';
        }
    });
});
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 