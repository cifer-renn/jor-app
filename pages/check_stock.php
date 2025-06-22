<?php
header('Content-Type: application/json');
require_once '../includes/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'machine_operator') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

if ($job_id === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Job ID']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT jr.quantity_required, i.name, i.quantity as stock_on_hand
        FROM job_requirements jr
        JOIN inventory i ON jr.inventory_id = i.id
        WHERE jr.job_id = ?
    ");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $requirements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $insufficient_items = [];
    foreach ($requirements as $req) {
        if ($req['stock_on_hand'] < $req['quantity_required']) {
            $insufficient_items[] = [
                'name' => $req['name'],
                'required' => $req['quantity_required'],
                'on_hand' => $req['stock_on_hand']
            ];
        }
    }

    echo json_encode([
        'sufficient' => empty($insufficient_items),
        'items' => $insufficient_items
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?> 