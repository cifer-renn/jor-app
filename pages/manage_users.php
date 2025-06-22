<?php 
$page_title = "Manage Users";
include '../includes/header.php'; 

// Check if user is logged in and has the supervisor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$success_message = '';
$error_message = '';

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    // Don't allow supervisor to delete themselves
    if ($user_id === $_SESSION['user_id']) {
        $error_message = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $success_message = "User deleted successfully!";
        } else {
            $error_message = "Error deleting user: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle form submission for adding/editing users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    
    if (empty($username)) {
        $error_message = "Username is required.";
    } elseif ($edit_id == 0 && empty($password)) {
        $error_message = "Password is required for new users.";
    } else {
        if ($edit_id > 0) {
            // Update existing user
            if (empty($password)) {
                $stmt = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssi", $username, $role, $edit_id);
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                $stmt->bind_param("sssi", $username, $hashed_password, $role, $edit_id);
            }
        } else {
            // Add new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $hashed_password, $role);
        }
        
        if ($stmt->execute()) {
            $success_message = $edit_id > 0 ? "User updated successfully!" : "User added successfully!";
            $_POST = array(); // Clear form
        } else {
            $error_message = "Error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get user to edit
$edit_user = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_user = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get all users
$users = [];
$stmt = $conn->prepare("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-people me-2"></i>Manage Users</h1>
    <button class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus"></i> Add New User
    </button>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>User List</h5>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <p class="text-muted text-center">No users found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><i class="bi bi-person me-1"></i>Username</th>
                            <th><i class="bi bi-shield me-1"></i>Role</th>
                            <th><i class="bi bi-calendar me-1"></i>Created</th>
                            <th><i class="bi bi-gear me-1"></i>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-2">
                                            <i class="bi bi-person-circle fs-4"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                            <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                                <span class="badge bg-primary ms-2">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $role_icons = [
                                        'supervisor' => 'bi-person-badge',
                                        'warehouse_manager' => 'bi-box-seam',
                                        'machine_operator' => 'bi-tools'
                                    ];
                                    $role_colors = [
                                        'supervisor' => 'bg-primary',
                                        'warehouse_manager' => 'bg-success',
                                        'machine_operator' => 'bg-info'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $role_colors[$user['role']]; ?>">
                                        <i class="bi <?php echo $role_icons[$user['role']]; ?> me-1"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary" 
                                                onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo $user['role']; ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                            <a href="?delete=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>Add New User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-person me-1"></i>Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock me-1"></i>Password
                        </label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">
                            <i class="bi bi-shield me-1"></i>Role
                        </label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="warehouse_manager">Warehouse Manager</option>
                            <option value="machine_operator">Machine Operator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Edit User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="edit_id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">
                            <i class="bi bi-person me-1"></i>Username
                        </label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">
                            <i class="bi bi-lock me-1"></i>Password
                        </label>
                        <input type="password" class="form-control" id="edit_password" name="password" 
                               placeholder="Leave blank to keep current password">
                        <small class="text-muted">Leave blank to keep the current password</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">
                            <i class="bi bi-shield me-1"></i>Role
                        </label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="warehouse_manager">Warehouse Manager</option>
                            <option value="machine_operator">Machine Operator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(userId, username, role) {
    document.getElementById('edit_id').value = userId;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_password').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

// Clear add user modal when it's closed
document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('role').value = '';
});

// Clear edit user modal when it's closed
document.getElementById('editUserModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('edit_id').value = '';
    document.getElementById('edit_username').value = '';
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_role').value = '';
});
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 