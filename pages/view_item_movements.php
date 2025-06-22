<?php
$page_title = "Inventory Item Movements";

// Establish database connection first
require_once '../includes/database.php';
// Now include the header
include '../includes/header.php'; 

// Role-specific access control
if ($_SESSION['role'] !== 'warehouse_manager') {
    header('Location: ../login.php');
    exit();
}

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($item_id <= 0) {
    header('Location: manage_inventory.php');
    exit();
}

// Get item details
$item_stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
$item_stmt->bind_param("i", $item_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();

if ($item_result->num_rows === 0) {
    header('Location: manage_inventory.php');
    exit();
}

$item = $item_result->fetch_assoc();
$item_stmt->close();

// Get movement history
$movements = [];
$movement_stmt = $conn->prepare(
    "SELECT im.*, u.username as moved_by_name, j.title as job_title
     FROM inventory_movements im
     LEFT JOIN users u ON im.moved_by_id = u.id
     LEFT JOIN jobs j ON im.job_id = j.id
     WHERE im.inventory_id = ?
     ORDER BY im.moved_at DESC"
);
$movement_stmt->bind_param("i", $item_id);
$movement_stmt->execute();
$movement_result = $movement_stmt->get_result();
while ($row = $movement_result->fetch_assoc()) {
    $movements[] = $row;
}
$movement_stmt->close();

// Calculate statistics
$total_in = 0;
$total_out = 0;
foreach ($movements as $mov) {
    if ($mov['movement_type'] === 'in') {
        $total_in += $mov['quantity_change'];
    } else {
        $total_out += $mov['quantity_change'];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-2"><i class="bi bi-clock-history me-2"></i>Movement History</h1>
        <p class="text-muted mb-0"><?php echo htmlspecialchars($item['name']); ?></p>
    </div>
    <a href="manage_inventory.php" class="btn btn-secondary btn-icon">
        <i class="bi bi-arrow-left"></i> Back to Inventory
    </a>
</div>

<!-- Item Summary Card -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1"><i class="bi bi-box me-1"></i>Current Stock</h6>
                        <h2 class="mb-0"><?php echo $item['quantity']; ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-box"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1"><i class="bi bi-arrow-down me-1"></i>Total In</h6>
                        <h2 class="mb-0">+<?php echo $total_in; ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-arrow-down"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1"><i class="bi bi-arrow-up me-1"></i>Total Out</h6>
                        <h2 class="mb-0">-<?php echo $total_out; ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-arrow-up"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Movement History -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Movement History</h5>
    </div>
    <div class="card-body">
        <?php if (empty($movements)): ?>
            <div class="text-center py-5">
                <i class="bi bi-clock-history fs-1 text-muted mb-3"></i>
                <p class="text-muted">No movement history found for this item.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Moved By</th>
                            <th>Related Job</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo date('M j, Y', strtotime($movement['moved_at'])); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($movement['moved_at'])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($movement['movement_type'] === 'in'): ?>
                                        <span class="badge bg-success"><i class="bi bi-arrow-down me-1"></i>Stock In</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-arrow-up me-1"></i>Stock Out</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold fs-5 <?php echo $movement['movement_type'] === 'in' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $movement['movement_type'] === 'in' ? '+' : '-'; ?><?php echo $movement['quantity_change']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-person-circle me-2"></i>
                                        <?php echo htmlspecialchars($movement['moved_by_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($movement['job_title']): ?>
                                        <a href="view_job.php?id=<?php echo $movement['job_id']; ?>" class="text-decoration-none">
                                            <i class="bi bi-link-45deg me-1"></i>
                                            <?php echo htmlspecialchars($movement['job_title']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($movement['notes'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($movement['notes']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
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