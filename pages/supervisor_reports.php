<?php
$page_title = "Weekly & Monthly Reports";
include '../includes/header.php';

if ($_SESSION['role'] !== 'supervisor') {
    header('Location: supervisor_dashboard.php');
    exit();
}

require_once '../includes/database.php';

// Helper: Get start/end of week/month
function getDateRange($type = 'week') {
    $now = new DateTime();
    if ($type === 'week') {
        $start = clone $now;
        $start->modify('monday this week')->setTime(0,0,0);
        $end = clone $start;
        $end->modify('+6 days')->setTime(23,59,59);
    } else {
        $start = new DateTime('first day of this month');
        $start->setTime(0,0,0);
        $end = new DateTime('last day of this month');
        $end->setTime(23,59,59);
    }
    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}



function getJORStats($conn, $supervisor_id, $type = 'week') {
    list($start, $end) = getDateRange($type);
    // Total JORs
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jobs WHERE supervisor_id = ? AND created_at BETWEEN ? AND ?");
    $stmt->bind_param("iss", $supervisor_id, $start, $end);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    // Status breakdown
    $statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
    $status_counts = [];
    foreach ($statuses as $status) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM jobs WHERE supervisor_id = ? AND status = ? AND created_at BETWEEN ? AND ?");
        $stmt->bind_param("isss", $supervisor_id, $status, $start, $end);
        $stmt->execute();
        $status_counts[$status] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
        $stmt->close();
    }
    // Average completion time (in hours)
    $stmt = $conn->prepare("SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_seconds FROM jobs WHERE supervisor_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ? AND completed_at IS NOT NULL");
    $stmt->bind_param("iss", $supervisor_id, $start, $end);
    $stmt->execute();
    $avg_seconds = $stmt->get_result()->fetch_assoc()['avg_seconds'] ?? null;
    $stmt->close();
    $avg_hours = $avg_seconds ? round($avg_seconds/3600, 2) : null;
    return [
        'total' => $total,
        'status_counts' => $status_counts,
        'avg_hours' => $avg_hours
    ];
}

$week_stats = getJORStats($conn, $_SESSION['user_id'], 'week');
$month_stats = getJORStats($conn, $_SESSION['user_id'], 'month');

$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'oldest' ? 'ASC' : 'DESC';
$sort_label = $sort_order === 'ASC' ? 'Oldest First' : 'Newest First';
$period = isset($_GET['period']) && $_GET['period'] === 'month' ? 'month' : 'week';

// Custom date range logic
$custom_start = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] . ' 00:00:00' : '';
$custom_end = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] . ' 23:59:59' : '';

if ($custom_start && $custom_end) {
    $start = $custom_start;
    $end = $custom_end;
    $range_label = 'Custom Range: ' . htmlspecialchars($_GET['start_date']) . ' to ' . htmlspecialchars($_GET['end_date']);
} else {
    list($start, $end) = getDateRange($period);
    $range_label = $period === 'week' ? 'This Week' : 'This Month';
}

// Fetch jobs for the selected period and sort order
$stmt = $conn->prepare("SELECT id, title, status, created_at, completed_at FROM jobs WHERE supervisor_id = ? AND created_at BETWEEN ? AND ? ORDER BY created_at $sort_order");
$stmt->bind_param("iss", $_SESSION['user_id'], $start, $end);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<div class="row mb-4">
    <div class="col-12">
        <h1 class="mb-2"><i class="bi bi-bar-chart-line me-2"></i>Weekly & Monthly Reports</h1>
        <p class="text-muted mb-0">Job Order Request (JOR) statistics for supervisors</p>
    </div>
</div>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">This Week</h5>
            </div>
            <div class="card-body">
                <h3 class="mb-3"><?php echo $week_stats['total']; ?> JORs</h3>
                <ul class="list-group mb-3">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Pending <span class="badge bg-warning text-dark"><?php echo $week_stats['status_counts']['pending']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        In Progress <span class="badge bg-info text-dark"><?php echo $week_stats['status_counts']['in_progress']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Completed <span class="badge bg-success"><?php echo $week_stats['status_counts']['completed']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Cancelled <span class="badge bg-secondary"><?php echo $week_stats['status_counts']['cancelled']; ?></span>
                    </li>
                </ul>
                <div class="mt-2">
                    <strong>Average Completion Time:</strong>
                    <?php echo $week_stats['avg_hours'] !== null ? $week_stats['avg_hours'] . ' hours' : 'N/A'; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">This Month</h5>
            </div>
            <div class="card-body">
                <h3 class="mb-3"><?php echo $month_stats['total']; ?> JORs</h3>
                <ul class="list-group mb-3">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Pending <span class="badge bg-warning text-dark"><?php echo $month_stats['status_counts']['pending']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        In Progress <span class="badge bg-info text-dark"><?php echo $month_stats['status_counts']['in_progress']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Completed <span class="badge bg-success"><?php echo $month_stats['status_counts']['completed']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Cancelled <span class="badge bg-secondary"><?php echo $month_stats['status_counts']['cancelled']; ?></span>
                    </li>
                </ul>
                <div class="mt-2">
                    <strong>Average Completion Time:</strong>
                    <?php echo $month_stats['avg_hours'] !== null ? $month_stats['avg_hours'] . ' hours' : 'N/A'; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row mb-4">
    <div class="col-12">
        <form method="get" class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <label for="period" class="form-label mb-0 me-2">Show:</label>
            <select name="period" id="period" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value="week" <?php if($period==='week') echo 'selected'; ?>>This Week</option>
                <option value="month" <?php if($period==='month') echo 'selected'; ?>>This Month</option>
            </select>
            <label for="sort" class="form-label mb-0 ms-3 me-2">Sort by date:</label>
            <select name="sort" id="sort" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value="desc" <?php if($sort_order==='DESC') echo 'selected'; ?>>Newest First</option>
                <option value="oldest" <?php if($sort_order==='ASC') echo 'selected'; ?>>Oldest First</option>
            </select>
            <span class="ms-3">Custom Range:</span>
            <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>" class="form-control form-control-sm w-auto" />
            <span>-</span>
            <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>" class="form-control form-control-sm w-auto" />
            <button type="submit" class="btn btn-sm btn-primary ms-2">Apply</button>
            <?php if ($custom_start || $custom_end): ?>
                <a href="?period=<?php echo $period; ?>&sort=<?php echo $sort_order === 'ASC' ? 'oldest' : 'desc'; ?>" class="btn btn-sm btn-outline-secondary ms-2">Reset</a>
            <?php endif; ?>
        </form>
        <div class="card">
            <div class="card-header">
                <strong>Job Orders (<?php echo $range_label; ?>, <?php echo $sort_label; ?>)</strong>
            </div>
            <div class="card-body p-0">
                <?php if (empty($jobs)): ?>
                    <div class="p-3 text-center text-muted">No jobs found for this period.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Completed At</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($job['title']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($job['status'])); ?></td>
                                <td><?php echo htmlspecialchars($job['created_at']); ?></td>
                                <td><?php echo $job['completed_at'] ? htmlspecialchars($job['completed_at']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $conn->close(); include '../includes/footer.php'; ?> 