<?php 
$page_title = "Adjust Stock";
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'warehouse_manager') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)$_POST['item_id'];
    $movement_type = $_POST['movement_type'];
    $quantity_change = (int)$_POST['quantity_change'];
    $notes = trim($_POST['notes'] ?? '');
    $job_id = !empty($_POST['job_id']) ? (int)$_POST['job_id'] : null;
    
    if ($quantity_change <= 0) {
        $error_message = "Quantity must be greater than 0.";
    } else {
        // Get current item details
        $item_stmt = $conn->prepare("SELECT name, quantity FROM inventory WHERE id = ?");
        $item_stmt->bind_param("i", $item_id);
        $item_stmt->execute();
        $item_result = $item_stmt->get_result();
        
        if ($item_result->num_rows === 0) {
            $error_message = "Inventory item not found.";
        } else {
            $item = $item_result->fetch_assoc();
            $current_quantity = $item['quantity'];
            
            // Calculate new quantity
            if ($movement_type === 'in') {
                $new_quantity = $current_quantity + $quantity_change;
            } else {
                $new_quantity = $current_quantity - $quantity_change;
                if ($new_quantity < 0) {
                    $error_message = "Cannot remove more items than available. Current stock: $current_quantity";
                }
            }
            
            if (empty($error_message)) {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Update inventory quantity
                    $update_stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
                    $update_stmt->bind_param("ii", $new_quantity, $item_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Record movement
                    $movement_stmt = $conn->prepare("INSERT INTO inventory_movements (inventory_id, job_id, quantity_change, movement_type, moved_by_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
                    $movement_stmt->bind_param("iiissi", $item_id, $job_id, $quantity_change, $movement_type, $_SESSION['user_id'], $notes);
                    $movement_stmt->execute();
                    $movement_stmt->close();
                    
                    $conn->commit();
                    
                    $_SESSION['success_message'] = "Stock updated successfully! {$item['name']}: " . 
                        ($movement_type === 'in' ? '+' : '-') . $quantity_change . 
                        " (New total: $new_quantity)";
                    header('Location: manage_inventory.php');
                    exit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error updating stock: " . $e->getMessage();
                }
            }
        }
        $item_stmt->close();
    }
}

// If we get here, there was an error or invalid request
if (empty($error_message)) {
    $error_message = "Invalid request.";
}

$_SESSION['error_message'] = $error_message;
header('Location: manage_inventory.php');
exit();
?> 