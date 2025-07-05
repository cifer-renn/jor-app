<?php 
$page_title = "Material Form";
include '../includes/header.php'; 

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/database.php';

$success_message = '';
$error_message = '';

// Get available jobs for operators
$available_jobs = [];
if ($_SESSION['role'] === 'machine_operator') {
    $jobs_stmt = $conn->prepare("SELECT id, title, status FROM jobs WHERE operator_id = ? AND status IN ('in_progress', 'assigned') ORDER BY created_at DESC");
    $jobs_stmt->bind_param("i", $_SESSION['user_id']);
    $jobs_stmt->execute();
    $jobs_result = $jobs_stmt->get_result();
    while ($job = $jobs_result->fetch_assoc()) {
        $available_jobs[] = $job;
    }
    $jobs_stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_type = $_POST['form_type']; // 'application' or 'request'
    
    if ($form_type === 'application') {
        // Handle application submission
        $applicant_name = trim($_POST['applicant_name']);
        $work_unit = trim($_POST['work_unit']);
        $problem_description = trim($_POST['problem_description']);
        $location = trim($_POST['location']);
        $request_date = $_POST['request_date'];
        $priority = $_POST['priority'];
        $contact_number = trim($_POST['contact_number']);
        $email = trim($_POST['email']);
        
        // Validate required fields
        if (empty($applicant_name) || empty($work_unit) || empty($problem_description) || empty($location) || empty($request_date)) {
            $error_message = "Please fill in all required fields.";
        } else {
            // Handle file upload
            $attachment_path = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/attachments/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_info = pathinfo($_FILES['attachment']['name']);
                $file_extension = strtolower($file_info['extension']);
                
                // Validate file type
                $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
                if (!in_array($file_extension, $allowed_extensions)) {
                    $error_message = "Invalid file type. Allowed types: PDF, DOC, DOCX, JPG, PNG, TXT";
                } else {
                    // Validate file size (max 5MB)
                    if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
                        $error_message = "File size too large. Maximum size is 5MB.";
                    } else {
                        $file_name = uniqid() . '_' . time() . '.' . $file_extension;
                        $attachment_path = 'uploads/attachments/' . $file_name;
                        
                        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $file_name)) {
                            $error_message = "Error uploading file.";
                        }
                    }
                }
            }
            
            if (empty($error_message)) {
                try {
                    $stmt = $conn->prepare("INSERT INTO material_applications (
                        applicant_name, work_unit, problem_description, location, 
                        request_date, priority, contact_number, email, attachment_path, 
                        submitted_by_id, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                    
                    $stmt->bind_param("sssssssssi", 
                        $applicant_name, $work_unit, $problem_description, $location,
                        $request_date, $priority, $contact_number, $email, $attachment_path,
                        $_SESSION['user_id']
                    );
                    
                    if ($stmt->execute()) {
                        $success_message = "Material application submitted successfully! Your application number is: " . $stmt->insert_id;
                        $_POST = array(); // Clear form data
                    } else {
                        $error_message = "Error submitting application: " . $conn->error;
                    }
                    $stmt->close();
                    
                } catch (Exception $e) {
                    $error_message = "Error submitting application: " . $e->getMessage();
                }
            }
        }
    } else {
        // Handle request submission
        $job_id = (int)$_POST['job_id'];
        $request_notes = trim($_POST['request_notes']);
        
        if (empty($job_id) || empty($request_notes)) {
            $error_message = "Please select a job and provide request notes.";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO material_requests (
                    job_id, requested_by_id, request_notes, status, created_at
                ) VALUES (?, ?, ?, 'pending', NOW())");
                
                $stmt->bind_param("iis", $job_id, $_SESSION['user_id'], $request_notes);
                
                if ($stmt->execute()) {
                    $success_message = "Material request submitted successfully!";
                    $_POST = array(); // Clear form data

                    // Notify all warehouse managers
                    require_once __DIR__ . '/../includes/notifications.php';
                    $wm_query = $conn->query("SELECT id FROM users WHERE role = 'warehouse_manager'");
                    while ($wm = $wm_query->fetch_assoc()) {
                        create_role_notifications('warehouse_manager', 'material_request', [
                            'warehouse_manager_id' => $wm['id']
                        ]);
                    }
                } else {
                    $error_message = "Error submitting request: " . $conn->error;
                }
                $stmt->close();
                
            } catch (Exception $e) {
                $error_message = "Error submitting request: " . $e->getMessage();
            }
        }
    }
}

// Get user's previous submissions
$previous_submissions = [];
$stmt = $conn->prepare("SELECT 'application' as type, id, applicant_name as title, status, created_at FROM material_applications WHERE submitted_by_id = ? 
                        UNION ALL 
                        SELECT 'request' as type, id, request_notes as title, status, created_at FROM material_requests WHERE requested_by_id = ? 
                        ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $previous_submissions[] = $row;
}
$stmt->close();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-2"><i class="bi bi-plus-circle me-2"></i>Material Form</h1>
                <p class="text-muted mb-0">Submit material applications or quick requests</p>
            </div>
            <a href="view_my_applications.php" class="btn btn-outline-primary btn-icon">
                <i class="bi bi-clock-history"></i>
                View My Submissions
            </a>
        </div>
    </div>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success d-flex align-items-center">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Form Type Selection -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Select Form Type</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="form_type" id="type_application" value="application" checked>
                            <label class="form-check-label" for="type_application">
                                <i class="bi bi-file-earmark-text me-2"></i>Material Application
                            </label>
                        </div>
                        <small class="text-muted d-block">Detailed application with comprehensive information</small>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="form_type" id="type_request" value="request">
                            <label class="form-check-label" for="type_request">
                                <i class="bi bi-card-checklist me-2"></i>Quick Request
                            </label>
                        </div>
                        <small class="text-muted d-block">Quick request linked to active job</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Form -->
    <div class="col-lg-8" id="application_form">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Material Application</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data" id="applicationForm">
                    <input type="hidden" name="form_type" value="application">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="applicant_name" class="form-label">Applicant Name *</label>
                            <input type="text" class="form-control" id="applicant_name" name="applicant_name" 
                                   value="<?php echo isset($_POST['applicant_name']) ? htmlspecialchars($_POST['applicant_name']) : htmlspecialchars($_SESSION['username']); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="work_unit" class="form-label">Work Unit *</label>
                            <input type="text" class="form-control" id="work_unit" name="work_unit" 
                                   value="<?php echo isset($_POST['work_unit']) ? htmlspecialchars($_POST['work_unit']) : ''; ?>" 
                                   placeholder="e.g., Production Line A, Maintenance Team" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="problem_description" class="form-label">Problem Description *</label>
                        <textarea class="form-control" id="problem_description" name="problem_description" rows="4" 
                                  placeholder="Describe the problem or material requirement in detail..." required><?php echo isset($_POST['problem_description']) ? htmlspecialchars($_POST['problem_description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">Location *</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" 
                                   placeholder="e.g., Building A, Floor 2, Room 205" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="request_date" class="form-label">Request Date *</label>
                            <input type="date" class="form-control" id="request_date" name="request_date" 
                                   value="<?php echo isset($_POST['request_date']) ? $_POST['request_date'] : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label">Priority Level</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'low') ? 'selected' : ''; ?>>Low</option>
                                <option value="normal" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'high') ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="contact_number" class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" id="contact_number" name="contact_number" 
                                   value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>" 
                                   placeholder="e.g., +62 812-3456-7890">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               placeholder="your.email@company.com">
                    </div>
                    
                    <div class="mb-3">
                        <label for="attachment" class="form-label">Attachment (Optional)</label>
                        <input type="file" class="form-control" id="attachment" name="attachment" 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
                        <small class="text-muted">Max file size: 5MB. Allowed types: PDF, DOC, DOCX, JPG, PNG, TXT</small>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i>Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Request Form -->
    <div class="col-lg-8" id="request_form" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Quick Material Request</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="requestForm">
                    <input type="hidden" name="form_type" value="request">
                    
                    <div class="mb-3">
                        <label for="job_id" class="form-label">Select Job *</label>
                        <select class="form-select" id="job_id" name="job_id" required>
                            <option value="">Choose a job...</option>
                            <?php foreach ($available_jobs as $job): ?>
                                <option value="<?php echo $job['id']; ?>" <?php echo (isset($_POST['job_id']) && $_POST['job_id'] == $job['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($job['title']); ?> (<?php echo ucfirst($job['status']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($available_jobs)): ?>
                            <small class="text-muted">No active jobs available. Please contact your supervisor.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="request_notes" class="form-label">Request Notes *</label>
                        <textarea class="form-control" id="request_notes" name="request_notes" rows="4" 
                                  placeholder="Describe what materials you need for this job..." required><?php echo isset($_POST['request_notes']) ? htmlspecialchars($_POST['request_notes']) : ''; ?></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning" <?php echo empty($available_jobs) ? 'disabled' : ''; ?>>
                            <i class="bi bi-send me-2"></i>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Submissions -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Submissions</h5>
            </div>
            <div class="card-body">
                <?php if (empty($previous_submissions)): ?>
                    <p class="text-muted text-center">No recent submissions</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($previous_submissions as $submission): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="badge bg-<?php echo $submission['type'] === 'application' ? 'info' : 'warning'; ?> me-2">
                                                <?php echo ucfirst($submission['type']); ?>
                                            </span>
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($submission['created_at'])); ?></small>
                                        </div>
                                        <div class="small"><?php echo htmlspecialchars(substr($submission['title'], 0, 50)); ?><?php echo strlen($submission['title']) > 50 ? '...' : ''; ?></div>
                                        <span class="badge bg-<?php 
                                            echo $submission['status'] === 'pending' ? 'danger' : 
                                                ($submission['status'] === 'approved' || $submission['status'] === 'resolved' ? 'success' : 
                                                ($submission['status'] === 'acknowledged' ? 'warning' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($submission['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle between application and request forms
document.querySelectorAll('input[name="form_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const applicationForm = document.getElementById('application_form');
        const requestForm = document.getElementById('request_form');
        
        if (this.value === 'application') {
            applicationForm.style.display = 'block';
            requestForm.style.display = 'none';
        } else {
            applicationForm.style.display = 'none';
            requestForm.style.display = 'block';
        }
    });
});
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?> 