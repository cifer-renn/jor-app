<?php 
$page_title = "Warehouse Dashboard";

// Establish database connection first
require_once '../includes/database.php';
// Now include the header
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'warehouse_manager') {
    header('Location: ../login.php');
    exit();
}

// Get inventory statistics
$stats = [];
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_items,
    SUM(quantity) as total_quantity,
    SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock
    FROM inventory");
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent inventory items
$inventory_items = [];
$stmt = $conn->prepare("SELECT * FROM inventory ORDER BY updated_at DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $inventory_items[] = $row;
}
$stmt->close();

// Get recent movements
$recent_movements = [];
$stmt = $conn->prepare("SELECT im.*, i.name as item_name, u.username as moved_by_name, j.title as job_title
    FROM inventory_movements im
    LEFT JOIN inventory i ON im.inventory_id = i.id
    LEFT JOIN users u ON im.moved_by_id = u.id
    LEFT JOIN jobs j ON im.job_id = j.id
    ORDER BY im.moved_at DESC
    LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_movements[] = $row;
}
$stmt->close();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-2"><i class="bi bi-box-seam me-2"></i>Warehouse Dashboard</h1>
                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Here's an overview of your inventory.</p>
            </div>
            <a href="add_inventory.php" class="btn btn-primary btn-icon">
                <i class="bi bi-plus-circle"></i>
                Add New Item
            </a>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1"><i class="bi bi-stack me-1"></i>Total Items</h6>
                        <h2 class="mb-0"><?php echo $stats['total_items'] ?? 0; ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-stack"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1"><i class="bi bi-boxes me-1"></i>Total Quantity</h6>
                        <h2 class="mb-0"><?php echo $stats['total_quantity'] ?? 0; ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-boxes"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1"><i class="bi bi-x-circle me-1"></i>Out of Stock</h6>
                        <h2 class="mb-0"><?php echo $stats['out_of_stock'] ?? 0; ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Inventory Items -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Recently Updated Inventory</h5>
                <a href="manage_inventory.php" class="btn btn-sm btn-outline-primary btn-icon">
                    <i class="bi bi-arrow-right me-1"></i>Manage All
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($inventory_items)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <p class="text-muted">No inventory items found. <a href="add_inventory.php">Add your first item</a>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-tag me-1"></i>Item Name</th>
                                    <th><i class="bi bi-123 me-1"></i>Quantity</th>
                                    <th><i class="bi bi-clock me-1"></i>Last Updated</th>
                                    <th><i class="bi bi-gear me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory_items as $item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                        <td>
                                            <span class="fw-bold <?php echo $item['quantity'] == 0 ? 'text-danger' : ($item['quantity'] < 10 ? 'text-warning' : 'text-success'); ?>">
                                                <?php echo $item['quantity']; ?>
                                            </span>
                                            <?php if ($item['quantity'] == 0): ?>
                                                <span class="badge bg-danger ms-2"><i class="bi bi-x-circle me-1"></i>Out of Stock</span>
                                            <?php elseif ($item['quantity'] < 10): ?>
                                                <span class="badge bg-warning ms-2"><i class="bi bi-exclamation-triangle me-1"></i>Low Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y, g:i A', strtotime($item['updated_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-success btn-icon" 
                                                    onclick="adjustStock(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>', <?php echo $item['quantity']; ?>)">
                                                <i class="bi bi-plus-slash-minus"></i> Adjust
                                            </button>
                                            <a href="view_item_movements.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-info btn-icon">
                                                <i class="bi bi-clock-history"></i> History
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

    <!-- Recent Movements -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Recent Movements</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_movements)): ?>
                    <p class="text-muted text-center py-4">No recent movements.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_movements as $movement): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($movement['item_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <span class="fw-bold <?php echo $movement['movement_type'] === 'in' ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $movement['movement_type'] === 'in' ? '+' : '-'; ?><?php echo $movement['quantity_change']; ?>
                                            </span>
                                            by <?php echo htmlspecialchars($movement['moved_by_name']); ?>
                                        </small>
                                    </div>
                                    <small class="text-muted"><?php echo date('M j, g:i A', strtotime($movement['moved_at'])); ?></small>
                                </div>
                                <?php if ($movement['job_title']): ?>
                                    <small class="text-info d-block mt-1">
                                        <i class="bi bi-link-45deg"></i>
                                        Job: <?php echo htmlspecialchars($movement['job_title']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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
    
    var modal = new bootstrap.Modal(document.getElementById('stockModal'));
    modal.show();
}
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 