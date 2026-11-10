<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('admin') && !Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$cm = new ComplaintManager();
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$complaint_id) {
    header("Location: complaint_management.php");
    exit();
}

// Fetch complaint details
$query = "SELECT c.*, cat.category_name, u.full_name as student_name, u.student_id
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN users u ON c.user_id = u.user_id
          WHERE c.complaint_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$complaint_id]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    header("Location: complaint_management.php");
    exit();
}

// Handle form submission
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $status = $_POST['status'];
    $department_assigned = $_POST['department_assigned'];
    
    if (empty($title) || empty($description)) {
        $error = "Title and description are required.";
    } else {
        $update_query = "UPDATE complaints SET 
                            title = ?, description = ?, priority = ?, 
                            status = ?, department_assigned = ?
                         WHERE complaint_id = ?";
        $update_stmt = $db->prepare($update_query);
        if ($update_stmt->execute([$title, $description, $priority, $status, $department_assigned, $complaint_id])) {
            $success = "Complaint updated successfully!";
            // If the complaint was moved to a different department, let that
            // department's supervisor(s) know a complaint just landed with them
            if ($department_assigned !== $complaint['department_assigned']) {
                $cm->notifyDepartmentSupervisors($department_assigned, $complaint_id, "Complaint #{$complaint['complaint_number']} was reassigned to your department: \"{$title}\"");
            }
            // Refresh data
            $stmt->execute([$complaint_id]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Failed to update complaint.";
        }
    }
}

// Get ONLY operational departments (departments that handle complaints)
$dept_query = "SELECT dept_name FROM departments WHERE dept_type = 'operational' ORDER BY dept_name";
$dept_stmt = $db->query($dept_query);
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Complaint - <?php echo htmlspecialchars($complaint['complaint_number']); ?></title>
    <link rel="stylesheet" href="../admin/css/admin.css">
    <style>
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            border-color: #0A63BC;
            outline: none;
        }
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .btn-primary, .btn-secondary {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #0A63BC;
            color: white;
            margin-right: 10px;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .readonly-field {
            background: #f8f9fa;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Edit Complaint</h1>
                <p>Editing complaint #<?php echo htmlspecialchars($complaint['complaint_number']); ?></p>
                <span class="role-badge"><?php echo strtoupper($_SESSION['role']); ?> ACCESS</span>
            </div>
            <a href="complaint_management.php" class="back-btn">← Back to Complaints</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>Complaint Number</label>
                    <div class="readonly-field"><?php echo htmlspecialchars($complaint['complaint_number']); ?></div>
                </div>
                
                <div class="form-group">
                    <label>Student</label>
                    <div class="readonly-field"><?php echo htmlspecialchars($complaint['student_name'] . ' (' . $complaint['student_id'] . ')'); ?></div>
                </div>
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($complaint['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="6" required><?php echo htmlspecialchars($complaint['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="low" <?php echo $complaint['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $complaint['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $complaint['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="urgent" <?php echo $complaint['priority'] == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="submitted" <?php echo $complaint['status'] == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="under_review" <?php echo $complaint['status'] == 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                        <option value="in_progress" <?php echo $complaint['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $complaint['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="rejected" <?php echo $complaint['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Assigned Department (Operational)</label>
                    <select name="department_assigned">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $complaint['department_assigned'] == $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666;">Only operational departments that handle complaints are shown</small>
                </div>
                
                <div class="form-group">
                    <label>Created At</label>
                    <div class="readonly-field"><?php echo date('M j, Y g:i A', strtotime($complaint['created_at'])); ?></div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">Update Complaint</button>
                    <a href="complaint_management.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script src="../admin/js/admin-core.js"></script>
</body>
</html>