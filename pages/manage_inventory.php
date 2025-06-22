<?php
$page_title = "Manage Inventory";

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

// Handle item deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $item_id = (int)$_GET['delete'];
    $force_delete = isset($_GET['force']) && $_GET['force'] === '1';
    
    // Check if item has movements
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory_movements WHERE inventory_id = ?");
    $check_stmt->bind_param("i", $item_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $movement_count = $result->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($movement_count > 0 && !$force_delete) {
        $error_message = "This item has movement history. <a href='manage_inventory.php?delete={$item_id}&force=1' class='alert-link'>Click here to force delete</a> (this will also delete all movement history).";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete related movements first
            if ($movement_count > 0) {
                $delete_movements = $conn->prepare("DELETE FROM inventory_movements WHERE inventory_id = ?");
                $delete_movements->bind_param("i", $item_id);
                $delete_movements->execute();
                $delete_movements->close();
            }
            
            // Delete the inventory item
            $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
            $stmt->bind_param("i", $item_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $success_message = "Inventory item deleted successfully!" . ($movement_count > 0 ? " (Also deleted {$movement_count} movement records)" : "");
            } else {
                throw new Exception("Error deleting item: " . $conn->error);
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Filtering and searching
$search_query = $_GET['search'] ?? '';
$stock_filter = $_GET['stock'] ?? '';

$sql_conditions = [];
$sql_params = [];
$sql_param_types = "";

if (!empty($search_query)) {
    $sql_conditions[] = "name LIKE ?";
    $search_term = "%{$search_query}%";
    $sql_params[] = $search_term;
    $sql_param_types .= "s";
}

if (!empty($stock_filter)) {
    switch ($stock_filter) {
        case 'out_of_stock':
            $sql_conditions[] = "quantity = 0";
            break;
        case 'low_stock':
            $sql_conditions[] = "quantity > 0 AND quantity < 10";
            break;
        case 'in_stock':
            $sql_conditions[] = "quantity >= 10";
            break;
    }
}

$where_clause = !empty($sql_conditions) ? "WHERE " . implode(" AND ", $sql_conditions) : "";

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM inventory $where_clause");
if (!empty($sql_params)) {
    $count_stmt->bind_param($sql_param_types, ...$sql_params);
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_items / $limit);
$count_stmt->close();

// Fetch inventory items
$items = [];
$sql = "SELECT * FROM inventory $where_clause ORDER BY name ASC LIMIT ? OFFSET ?";
$sql_params[] = $limit;
$sql_params[] = $offset;
$sql_param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($sql_param_types, ...$sql_params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

// Display session messages
if (empty($success_message) && empty($error_message)) {
    $success_message = $_SESSION['success_message'] ?? '';
    $error_message = $_SESSION['error_message'] ?? '';
    unset($_SESSION['success_message'], $_SESSION['error_message']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-box-seam me-2"></i>Manage Inventory</h1>
    <a href="add_inventory.php" class="btn btn-primary btn-icon">
        <i class="bi bi-plus-circle"></i> Add New Item
    </a>
</div>

<!-- Filter and Search Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter & Search</h5>
    </div>
    <div class="card-body">
        <form action="manage_inventory.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search by item name..." 
                       value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="col-md-3">
                <label for="stock" class="form-label">Stock Status</label>
                <select id="stock" name="stock" class="form-select">
                    <option value="">All Items</option>
                    <option value="out_of_stock" <?php if ($stock_filter == 'out_of_stock') echo 'selected'; ?>>Out of Stock</option>
                    <option value="low_stock" <?php if ($stock_filter == 'low_stock') echo 'selected'; ?>>Low Stock (< 10)</option>
                    <option value="in_stock" <?php if ($stock_filter == 'in_stock') echo 'selected'; ?>>In Stock (≥ 10)</option>
                </select>
            </div>
            <div class="col-md-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-search"></i> Filter</button>
                <a href="manage_inventory.php" class="btn btn-secondary ms-2 btn-icon"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if ($success_message): ?>
<div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_message; ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?></div>
<?php endif; ?>

<!-- Inventory Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($items)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                <p class="text-muted">No inventory items found matching your criteria.</p>
                <a href="manage_inventory.php" class="btn btn-primary">Reset Filters</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                        <?php if (!empty($item['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold fs-5 <?php echo $item['quantity'] == 0 ? 'text-danger' : ($item['quantity'] < 10 ? 'text-warning' : 'text-success'); ?>">
                                        <?php echo $item['quantity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['quantity'] == 0): ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Out of Stock</span>
                                    <?php elseif ($item['quantity'] < 10): ?>
                                        <span class="badge bg-warning"><i class="bi bi-exclamation-triangle me-1"></i>Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($item['updated_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-success" 
                                                onclick="adjustStock(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>', <?php echo $item['quantity']; ?>)"
                                                title="Adjust Stock">
                                            <i class="bi bi-plus-slash-minus"></i>
                                        </button>
                                        <a href="view_item_movements.php?id=<?php echo $item['id']; ?>" 
                                           class="btn btn-sm btn-outline-info" title="View History">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="edit_inventory.php?id=<?php echo $item['id']; ?>" 
                                           class="btn btn-sm btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="manage_inventory.php?delete=<?php echo $item['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger" title="Delete"
                                           onclick="return confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <?php if($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-end">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&stock=<?php echo urlencode($stock_filter); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-slash-minus me-2"></i>Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="stockForm" method="POST" action="adjust_stock.php">
                <div class="modal-body">
                    <input type="hidden" id="itemId" name="item_id">
                    <div class="mb-3">
                        <label class="form-label"><strong>Item:</strong> <span id="itemName"></span></label>
                        <br>
                        <small class="text-muted">Current stock: <span id="currentStock" class="fw-bold"></span></small>
                    </div>
                    <div class="mb-3">
                        <label for="movementType" class="form-label"><i class="bi bi-arrow-down-up me-1"></i>Movement Type</label>
                        <select class="form-select" id="movementType" name="movement_type" required>
                            <option value="in">Stock In (+)</option>
                            <option value="out">Stock Out (-)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="quantityChange" class="form-label"><i class="bi bi-123 me-1"></i>Quantity</label>
                        <input type="number" class="form-control" id="quantityChange" name="quantity_change" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label"><i class="bi bi-card-text me-1"></i>Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add notes about this stock adjustment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-icon" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary btn-icon">
                        <i class="bi bi-check-circle"></i> Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function adjustStock(itemId, itemName, currentStock) {
    document.getElementById('itemId').value = itemId;
    document.getElementById('itemName').textContent = itemName;
    document.getElementById('currentStock').textContent = currentStock;
    document.getElementById('quantityChange').value = '';
    document.getElementById('notes').value = '';
    
    var modal = new bootstrap.Modal(document.getElementById('stockModal'));
    modal.show();
}

function confirmDelete(itemId, itemName) {
    // Simple confirmation without AJAX
    if (confirm('Are you sure you want to delete "' + itemName + '"?\n\nThis action cannot be undone.\n\nIf this item has movement history, you will need to confirm again.')) {
        window.location.href = 'manage_inventory.php?delete=' + itemId;
    }
    return false; // Prevent default link behavior
}
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 