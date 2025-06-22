<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Job Order System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../index.php" class="sidebar-brand">
            <i class="bi bi-gear-fill"></i>
            Job Order System
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <?php 
        if ($_SESSION['role'] === 'supervisor'): 
            // Get pending verifications count for supervisor
            $pending_verifications_count = 0;
            if (isset($conn)) {
                $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM jobs WHERE status = 'completed' AND verification_status = 'pending_verification'");
                $stmt_count->execute();
                $result_count = $stmt_count->get_result();
                if ($result_count) {
                    $pending_verifications_count = $result_count->fetch_assoc()['count'];
                }
                $stmt_count->close();
            }
        ?>
            <!-- Supervisor Navigation -->
            <div class="nav-item">
                <a href="supervisor_dashboard.php" class="nav-link text-white <?php echo (strpos($current_page, 'supervisor_dashboard') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="create_job.php" class="nav-link text-white <?php echo (strpos($current_page, 'create_job') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-plus-square me-2"></i> Create Job
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_jobs.php" class="nav-link text-white <?php echo (strpos($current_page, 'manage_jobs') !== false || strpos($current_page, 'edit_job') !== false || strpos($current_page, 'view_job') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-list-task me-2"></i> Manage Jobs
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_job_verifications.php" class="nav-link text-white <?php echo (strpos($current_page, 'manage_job_verifications') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard-check me-2"></i>Job Verifications
                    <?php if ($pending_verifications_count > 0): ?>
                        <span class="badge bg-danger ms-2"><?php echo $pending_verifications_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_users.php" class="nav-link text-white <?php echo (strpos($current_page, 'manage_users') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-people me-2"></i> Manage Users
                </a>
            </div>
            
        <?php 
        elseif ($_SESSION['role'] === 'warehouse_manager'): 
            // Get pending requests count for warehouse manager
            $pending_requests_count = 0;
            if (isset($conn)) {
                $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM material_requests WHERE status = 'pending'");
                $stmt_count->execute();
                $result_count = $stmt_count->get_result();
                $pending_requests_count = $result_count->fetch_assoc()['count'];
                $stmt_count->close();
            }
        ?>
            <div class="nav-item">
                <a href="warehouse_dashboard.php" class="nav-link text-white <?php echo (strpos($current_page, 'warehouse_dashboard') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_inventory.php" class="nav-link text-white <?php echo (strpos($current_page, 'manage_inventory') !== false || strpos($current_page, 'add_inventory') !== false || strpos($current_page, 'edit_inventory') !== false || strpos($current_page, 'view_item_movements') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam me-2"></i> Manage Inventory
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_material_requests.php" class="nav-link text-white <?php echo (strpos($current_page, 'manage_material_requests') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-card-checklist me-2"></i>Material Requests
                    <?php if ($pending_requests_count > 0): ?>
                        <span class="badge bg-danger ms-2"><?php echo $pending_requests_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
        <?php elseif ($_SESSION['role'] === 'machine_operator'): ?>
            <!-- Machine Operator Navigation -->
            <div class="nav-item">
                <a href="operator_dashboard.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'operator_dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-workspace me-2"></i>My Jobs
                </a>
            </div>
            <div class="nav-item">
                <a href="job_history.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'job_history.php' ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history me-2"></i>Job History
                </a>
            </div>
            <div class="nav-item">
                <a href="request_material.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'request_material.php' ? 'active' : ''; ?>">
                    <i class="bi bi-send me-2"></i>Request Materials
                </a>
            </div>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0">
                <i class="bi bi-person-circle fs-4"></i>
            </div>
            <div class="flex-grow-1 ms-2">
                <div class="small"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                <div class="small text-muted"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content" id="main-content">
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="d-flex align-items-center">
            <button class="sidebar-toggle" id="sidebar-toggle">
                <i class="bi bi-list"></i>
            </button>
            <h4 class="mb-0 ms-3"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h4>
        </div>
        
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                <div class="user-role"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?></div>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="../logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Page Content -->
    <div class="container-fluid p-4"> 