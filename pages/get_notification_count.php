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

// Get unread notification count
$count = get_unread_count($_SESSION['user_id']);

echo json_encode(['success' => true, 'count' => $count]);
?> 