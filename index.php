<?php
session_start();

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirect user based on their role
$role = $_SESSION['role'];
switch ($role) {
    case 'supervisor':
        header('Location: pages/supervisor_dashboard.php');
        break;
    case 'warehouse_manager':
        header('Location: pages/warehouse_dashboard.php');
        break;
    case 'machine_operator':
        header('Location: pages/operator_dashboard.php');
        break;
    default:
        // If role is not set or invalid, redirect to login
        header('Location: login.php');
        break;
}
exit();
?> 