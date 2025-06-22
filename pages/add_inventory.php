<?php
$page_title = "Add New Inventory Item";

// Establish database connection first
require_once '../includes/database.php';
// Now include the header
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'warehouse_manager') {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $quantity = (int)$_POST['quantity'];
    $description = trim($_POST['description'] ?? '');
    $inventory_type = $_POST['inventory_type'] ?? 'raw_materials';
    
    if (empty($name)) {
        $error_message = "Item name is required.";
    } elseif ($quantity < 0) {
        $error_message = "Quantity cannot be negative.";
    } else {
        // Check if item already exists
        $check_stmt = $conn->prepare("SELECT id FROM inventory WHERE name = ?");
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "An item with this name already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO inventory (name, quantity, description, inventory_type) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siss", $name, $quantity, $description, $inventory_type);
            
            if ($stmt->execute()) {
                $inventory_id = $conn->insert_id;
                
                // If initial quantity > 0, create an initial movement record
                if ($quantity > 0) {
                    $movement_stmt = $conn->prepare("INSERT INTO inventory_movements (inventory_id, quantity_change, movement_type, moved_by_id) VALUES (?, ?, 'in', ?)");
                    $movement_stmt->bind_param("iii", $inventory_id, $quantity, $_SESSION['user_id']);
                    $movement_stmt->execute();
                    $movement_stmt->close();
                }
                
                $_SESSION['success_message'] = "Inventory item added successfully!";
                header('Location: manage_inventory.php');
                exit();
            } else {
                $error_message = "Error adding inventory item: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Add New Inventory Item</h1>
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
                <form method="POST" action="add_inventory.php">
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <i class="bi bi-tag me-1"></i>Item Name *
                        </label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                               required placeholder="Enter item name">
                        <small class="text-muted">This name must be unique in the inventory.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quantity" class="form-label">
                            <i class="bi bi-123 me-1"></i>Initial Quantity *
                        </label>
                        <input type="number" class="form-control" id="quantity" name="quantity" 
                               value="<?php echo isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0; ?>" 
                               min="0" required>
                        <small class="text-muted">Enter the initial stock quantity for this item.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="inventory_type" class="form-label">
                            <i class="bi bi-layers me-1"></i>Inventory Type *
                        </label>
                        <select class="form-select" id="inventory_type" name="inventory_type" required>
                            <option value="raw_materials" <?php if ((isset($_POST['inventory_type']) && $_POST['inventory_type'] == 'raw_materials') || !isset($_POST['inventory_type'])) echo 'selected'; ?>>Raw Materials</option>
                            <option value="finished_materials" <?php if (isset($_POST['inventory_type']) && $_POST['inventory_type'] == 'finished_materials') echo 'selected'; ?>>Finished Materials</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="form-label">
                            <i class="bi bi-card-text me-1"></i>Description
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Optional description of the item..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-lg btn-icon">
                            <i class="bi bi-check-circle me-2"></i>Add Item
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