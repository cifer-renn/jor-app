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

// Get notification ID
$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if ($notification_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit();
}

// Mark notification as read
if (mark_notification_read($notification_id)) {
    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error marking notification as read']);
}
?> 