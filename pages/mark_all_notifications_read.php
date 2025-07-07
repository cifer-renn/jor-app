<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Mark all notifications as read
if (mark_all_notifications_read($_SESSION['user_id'])) {
    echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error marking notifications as read']);
}
?> 