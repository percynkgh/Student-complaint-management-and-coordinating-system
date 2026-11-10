<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();

$cm = new ComplaintManager();
$db = (new Database())->getConnection();

// Get complaint ID from URL
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$complaint_id) {
    header("Location: ../dashboard.php");
    exit();
}

// Check if user confirmed deletion
if (isset($_POST['confirm_delete'])) {
    $result = $cm->deleteComplaint($complaint_id, $_SESSION['user_id'], $_SESSION['role']);
    
    if ($result['success']) {
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = 'error';
    }
    
    // Redirect based on role
    switch($_SESSION['role']) {
        case 'admin':
        case 'dean':
        case 'principal':
            header("Location: ../admin/dashboard.php");
            break;
        case 'staff':
            header("Location: ../staff/dashboard.php");
            break;
        default:
            header("Location: ../student/my_complaints.php");
    }
    exit();
}

// Get complaint details for confirmation page
$query = "SELECT c.*, cat.category_name, u.full_name as student_name
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN users u ON c.user_id = u.user_id
          WHERE c.complaint_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$complaint_id]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    header("Location: ../dashboard.php");
    exit();
}

// Check if user has permission to view this delete page
$can_access = false;
if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'dean' || $_SESSION['role'] == 'principal') {
    $can_access = true;
} elseif ($_SESSION['role'] == 'staff') {
    $dept_query = "SELECT department FROM users WHERE user_id = ?";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute([$_SESSION['user_id']]);
    $staff_dept = $dept_stmt->fetchColumn();
    if ($staff_dept && $staff_dept == $complaint['department_assigned']) {
        $can_access = true;
    }
} elseif ($_SESSION['role'] == 'student' && $complaint['user_id'] == $_SESSION['user_id']) {
    $can_access = true;
}

if (!$can_access) {
    header("Location: ../unauthorized.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Complaint</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="container-delete">
        <div class="delete-card">
            <div class="warning-icon"></div>
            <h1>Delete Complaint</h1>
            <p class="delete-warning">Are you sure you want to delete this complaint? This action cannot be undone.</p>
            
            <div class="complaint-info">
                <div class="info-item">
                    <div class="info-label">Complaint Number</div>
                    <div class="info-value">#<?php echo htmlspecialchars($complaint['complaint_number']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Title</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['title']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $complaint['status']; ?>">
                            <?php echo ucfirst($complaint['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Submitted By</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['student_name']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Date</div>
                    <div class="info-value"><?php echo date('F j, Y', strtotime($complaint['created_at'])); ?></div>
                </div>
            </div>
            
            <div class="warning-text">
                <strong> Important:</strong> Only complaints with status "resolved", "rejected", or "closed" can be deleted. All attachments and related data will be permanently removed.
            </div>
            
            <form method="POST" action="">
                <div class="btn-group">
                    <a href="javascript:history.back()" class="btn btn-cancel">Cancel</a>
                    <button type="submit" name="confirm_delete" class="btn btn-delete">Yes, Delete Complaint</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>