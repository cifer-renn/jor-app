<?php 
$page_title = "Edit Job Order";
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success_message = '';
$error_message = '';
$job = null;

// Fetch the job to be edited
if ($job_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND supervisor_id = ?");
    $stmt->bind_param("ii", $job_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $job = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Job not found or you don't have permission to edit it.";
        header('Location: manage_jobs.php');
        exit();
    }
    $stmt->close();
} else {
    header('Location: manage_jobs.php');
    exit();
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $status = $_POST['status'];
    $operator_id = !empty($_POST['operator_id']) ? (int)$_POST['operator_id'] : null;
    $materials = $_POST['materials'] ?? [];
    
    if (empty($title)) {
        $error_message = "Job title is required.";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Update the job details
            $stmt = $conn->prepare("UPDATE jobs SET title = ?, description = ?, priority = ?, status = ?, operator_id = ? WHERE id = ?");
            $stmt->bind_param("ssssii", $title, $description, $priority, $status, $operator_id, $job_id);
            $stmt->execute();
            $stmt->close();

            // 2. Delete existing requirements
            $del_stmt = $conn->prepare("DELETE FROM job_requirements WHERE job_id = ?");
            $del_stmt->bind_param("i", $job_id);
            $del_stmt->execute();
            $del_stmt->close();

            // 3. Insert new requirements
            if (!empty($materials)) {
                $req_stmt = $conn->prepare("INSERT INTO job_requirements (job_id, inventory_id, quantity_required) VALUES (?, ?, ?)");
                foreach ($materials as $material) {
                    if (!empty($material['inventory_id']) && !empty($material['quantity'])) {
                        $req_stmt->bind_param("iii", $job_id, $material['inventory_id'], $material['quantity']);
                        $req_stmt->execute();
                    }
                }
                $req_stmt->close();
            }
            
            $conn->commit();
            $_SESSION['success_message'] = "Job order updated successfully!";
            header("Location: manage_jobs.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error updating job order: " . $e->getMessage();
        }
    }
    // Re-fetch job data to show updated values if there was an error
    $job['title'] = $title;
    $job['description'] = $description;
    $job['priority'] = $priority;
    $job['status'] = $status;
    $job['operator_id'] = $operator_id;
}

// Get available inventory items
$inventory_items = [];
$stmt_inv = $conn->prepare("SELECT id, name, quantity FROM inventory ORDER BY name");
$stmt_inv->execute();
$result_inv = $stmt_inv->get_result();
while ($row = $result_inv->fetch_assoc()) {
    $inventory_items[] = $row;
}
$stmt_inv->close();

// Get current job requirements
$job_requirements = [];
$stmt_req = $conn->prepare("SELECT inventory_id, quantity_required FROM job_requirements WHERE job_id = ?");
$stmt_req->bind_param("i", $job_id);
$stmt_req->execute();
$result_req = $stmt_req->get_result();
while ($row = $result_req->fetch_assoc()) {
    $job_requirements[] = $row;
}
$stmt_req->close();

// Get available operators for the dropdown
$operators = [];
$stmt_operators = $conn->prepare("SELECT id, username FROM users WHERE role = 'machine_operator' ORDER BY username");
$stmt_operators->execute();
$result_operators = $stmt_operators->get_result();
while ($row = $result_operators->fetch_assoc()) {
    $operators[] = $row;
}
$stmt_operators->close();

?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Job Order</h1>
            <a href="manage_jobs.php" class="btn btn-secondary btn-icon"><i class="bi bi-arrow-left"></i> Back to Manage Jobs</a>
        </div>

        <?php if ($error_message): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="edit_job.php?id=<?php echo $job_id; ?>">
                    <div class="mb-3">
                        <label for="title" class="form-label">Job Title *</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($job['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low" <?php if($job['priority'] == 'low') echo 'selected'; ?>>Low</option>
                                <option value="normal" <?php if($job['priority'] == 'normal') echo 'selected'; ?>>Normal</option>
                                <option value="important" <?php if($job['priority'] == 'important') echo 'selected'; ?>>Important</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="pending" <?php if($job['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                                <option value="in_progress" <?php if($job['status'] == 'in_progress') echo 'selected'; ?>>In Progress</option>
                                <option value="completed" <?php if($job['status'] == 'completed') echo 'selected'; ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="operator_id" class="form-label">Assign to Operator</label>
                        <select class="form-select" id="operator_id" name="operator_id">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($operators as $operator): ?>
                                <option value="<?php echo $operator['id']; ?>" <?php if($job['operator_id'] == $operator['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($operator['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <hr class="my-4">

                    <h4 class="mb-3"><i class="bi bi-box-seam me-2"></i>Required Materials</h4>
                    <div id="materials-container">
                        <?php foreach ($job_requirements as $index => $req): ?>
                            <div class="row g-3 align-items-center mb-2 material-row">
                                <div class="col-md-7">
                                    <select name="materials[<?php echo $index; ?>][inventory_id]" class="form-select" required>
                                        <option value="">-- Select Material --</option>
                                        <?php foreach ($inventory_items as $item): ?>
                                            <option value="<?php echo $item['id']; ?>" <?php if($req['inventory_id'] == $item['id']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($item['name']) . " (In Stock: " . $item['quantity'] . ")"; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="materials[<?php echo $index; ?>][quantity]" class="form-control" placeholder="Qty" min="1" value="<?php echo $req['quantity_required']; ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-sm btn-danger remove-material"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="add-material" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="bi bi-plus-circle me-1"></i>Add Material
                    </button>
                    
                    <hr class="my-4">

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-lg btn-icon">
                            <i class="bi bi-check-circle me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const materialsContainer = document.getElementById('materials-container');
    const addMaterialButton = document.getElementById('add-material');
    let materialIndex = <?php echo count($job_requirements); ?>;

    addMaterialButton.addEventListener('click', function() {
        const materialRow = document.createElement('div');
        materialRow.classList.add('row', 'g-3', 'align-items-center', 'mb-2', 'material-row');
        materialRow.innerHTML = `
            <div class="col-md-7">
                <select name="materials[${materialIndex}][inventory_id]" class="form-select" required>
                    <option value="">-- Select Material --</option>
                    <?php foreach ($inventory_items as $item): ?>
                        <option value="<?php echo $item['id']; ?>">
                            <?php echo htmlspecialchars($item['name']) . " (In Stock: " . $item['quantity'] . ")"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" name="materials[${materialIndex}][quantity]" class="form-control" placeholder="Qty" min="1" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-sm btn-danger remove-material"><i class="bi bi-trash"></i></button>
            </div>
        `;
        materialsContainer.appendChild(materialRow);
        materialIndex++;
    });

    materialsContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-material') || e.target.closest('.remove-material')) {
            e.target.closest('.material-row').remove();
        }
    });
});
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 