<?php
$page_title = "Edit Inventory Item";

// Establish database connection first
require_once '../includes/database.php';
// Now include the header
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'warehouse_manager') {
    header('Location: ../login.php');
    exit();
}

// No need to require database again
// require_once '../includes/database.php';

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success_message = '';
$error_message = '';
$item = null;

// Fetch the item to be edited
if ($item_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $item = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Inventory item not found.";
        header('Location: manage_inventory.php');
        exit();
    }
    $stmt->close();
} else {
    header('Location: manage_inventory.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');
    $inventory_type = $_POST['inventory_type'] ?? 'raw_materials';
    
    if (empty($name)) {
        $error_message = "Item name is required.";
    } else {
        // Check if name already exists (excluding current item)
        $check_stmt = $conn->prepare("SELECT id FROM inventory WHERE name = ? AND id != ?");
        $check_stmt->bind_param("si", $name, $item_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "An item with this name already exists.";
        } else {
            $stmt = $conn->prepare("UPDATE inventory SET name = ?, description = ?, inventory_type = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $description, $inventory_type, $item_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Inventory item updated successfully!";
                header("Location: manage_inventory.php");
                exit();
            } else {
                $error_message = "Error updating inventory item: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    // Re-fetch item data to show updated values if there was an error
    $item['name'] = $name;
    $item['description'] = $description;
    $item['inventory_type'] = $inventory_type;
}
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Inventory Item</h1>
            <a href="manage_inventory.php" class="btn btn-secondary btn-icon">
                <i class="bi bi-arrow-left"></i> Back to Manage Inventory
            </a>
        </div>

        <?php if ($error_message): ?>
        <div class="alert alert-danger d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="edit_inventory.php?id=<?php echo $item_id; ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <i class="bi bi-tag me-1"></i>Item Name *
                        </label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($item['name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quantity" class="form-label">
                            <i class="bi bi-123 me-1"></i>Current Quantity
                        </label>
                        <input type="number" class="form-control" id="quantity" value="<?php echo $item['quantity']; ?>" 
                               readonly disabled>
                        <small class="text-muted">Quantity can only be changed through stock adjustments.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="inventory_type" class="form-label">
                            <i class="bi bi-layers me-1"></i>Inventory Type *
                        </label>
                        <select class="form-select" id="inventory_type" name="inventory_type" required>
                            <option value="raw_materials" <?php if ($item['inventory_type'] == 'raw_materials') echo 'selected'; ?>>Raw Materials</option>
                            <option value="finished_materials" <?php if ($item['inventory_type'] == 'finished_materials') echo 'selected'; ?>>Finished Materials</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="form-label">
                            <i class="bi bi-card-text me-1"></i>Description
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Optional description of the item..."><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>
                    
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

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 