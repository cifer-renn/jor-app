<?php 
$page_title = "Create New Job Order";
include '../includes/header.php'; 

// Check if user is logged in and has the supervisor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $operator_id = !empty($_POST['operator_id']) ? $_POST['operator_id'] : null;
    $materials = $_POST['materials'] ?? [];
    
    if (empty($title)) {
        $error_message = "Job title is required.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO jobs (title, description, priority, supervisor_id, operator_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $title, $description, $priority, $_SESSION['user_id'], $operator_id);
            $stmt->execute();
            $job_id = $stmt->insert_id;
            $stmt->close();

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
            $success_message = "Job order created successfully!";
            $_POST = array(); // Clear form data on success

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error creating job order: " . $e->getMessage();
        }
    }
}

// Get available operators
$operators = [];
$stmt = $conn->prepare("SELECT id, username FROM users WHERE role = 'machine_operator' ORDER BY username");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $operators[] = $row;
}
$stmt->close();

// Get available inventory items
$inventory_items = [];
$stmt = $conn->prepare("SELECT id, name, quantity FROM inventory ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $inventory_items[] = $row;
}
$stmt->close();
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">Create New Job Order</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="title" class="form-label">Job Title *</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"
                                      placeholder="Provide detailed description of the job..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'low') ? 'selected' : ''; ?>>Low</option>
                                <option value="normal" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="important" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'important') ? 'selected' : ''; ?>>Important</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="operator_id" class="form-label">Assign to Operator (Optional)</label>
                            <select class="form-select" id="operator_id" name="operator_id">
                                <option value="">-- Select Operator --</option>
                                <?php foreach ($operators as $operator): ?>
                                    <option value="<?php echo $operator['id']; ?>" 
                                            <?php echo (isset($_POST['operator_id']) && $_POST['operator_id'] == $operator['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($operator['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <hr class="my-4">

                        <div class="mb-3">
                            <h4><i class="bi bi-box-seam me-2"></i>Required Materials</h4>
                            <div id="materials-container">
                                <!-- Repeater items will be added here -->
                            </div>
                            <button type="button" id="add-material" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="bi bi-plus-circle me-1"></i>Add Material
                            </button>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="supervisor_dashboard.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Job Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const materialsContainer = document.getElementById('materials-container');
    const addMaterialButton = document.getElementById('add-material');
    let materialIndex = 0;

    addMaterialButton.addEventListener('click', function() {
        const materialRow = document.createElement('div');
        materialRow.classList.add('row', 'g-3', 'align-items-center', 'mb-2', 'material-row');
        materialRow.innerHTML = `
            <div class="col-md-7">
                <select name="materials[${materialIndex}][inventory_id]" class="form-select" required>
                    <option value="">-- Select Material --</option>
                    <?php foreach ($inventory_items as $item): ?>
                        <option value="<?php echo $item['id']; ?>" data-max="<?php echo $item['quantity']; ?>">
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