<?php
session_start();
require_once '../includes/database.php';

// Check if user is logged in and is a machine operator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'machine_operator') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_GET['job_id']) || !is_numeric($_GET['job_id'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid job ID']));
}

$job_id = (int)$_GET['job_id'];
$operator_id = $_SESSION['user_id'];

// Verify the job belongs to this operator
$check_stmt = $conn->prepare("SELECT id FROM jobs WHERE id = ? AND operator_id = ?");
$check_stmt->bind_param("ii", $job_id, $operator_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    exit(json_encode(['error' => 'Job not found or not assigned to you']));
}
$check_stmt->close();

// Get job requirements and check stock
$req_stmt = $conn->prepare("
    SELECT jr.inventory_id, jr.quantity_required, i.name, i.quantity as stock_on_hand
    FROM job_requirements jr
    JOIN inventory i ON jr.inventory_id = i.id
    WHERE jr.job_id = ?
");
$req_stmt->bind_param("i", $job_id);
$req_stmt->execute();
$requirements = $req_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$req_stmt->close();

$insufficient_items = [];
$sufficient = true;

foreach ($requirements as $req) {
    if ($req['stock_on_hand'] < $req['quantity_required']) {
        $insufficient_items[] = $req;
        $sufficient = false;
    }
}

// Return JSON response
header('Content-Type: application/json');

if (empty($requirements)) {
    // No specific requirements, so stock is sufficient
    echo json_encode([
        'sufficient' => true,
        'items' => [],
        'message' => 'No specific materials required for this job'
    ]);
} else {
    echo json_encode([
        'sufficient' => $sufficient,
        'items' => $insufficient_items,
        'total_required' => count($requirements),
        'insufficient_count' => count($insufficient_items)
    ]);
}

$conn->close();
?> 