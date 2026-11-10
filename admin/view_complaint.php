<?php
require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('admin') && !Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: /SCCS/auth/unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$cm = new ComplaintManager();
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if (!$complaint_id) {
    header("Location: complaint_management.php");
    exit();
}

// Handle delete complaint
if (isset($_POST['delete_complaint']) && isset($_POST['csrf_token']) && Security::verifyCSRFToken($_POST['csrf_token'])) {
    $complaint_id = (int)$_POST['complaint_id'];
    
    // Check if complaint is resolved
    $check_query = "SELECT status FROM complaints WHERE complaint_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$complaint_id]);
    $complaint_check = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($complaint_check && $complaint_check['status'] == 'resolved') {
        $result = $cm->deleteComplaint($complaint_id, $_SESSION['user_id'], $_SESSION['role']);
        if ($result['success']) {
            header("Location: complaint_management.php?success=" . urlencode("Complaint deleted successfully"));
            exit();
        } else {
            $error = $result['message'];
        }
    } else {
        $error = "Only resolved complaints can be deleted. This complaint status is: " . ($complaint_check['status'] ?? 'unknown');
    }
}

// Get complaint details
$query = "SELECT c.*, 
          cat.category_name,
          u.full_name as student_name,
          u.student_id,
          u.email as student_email,
          d.dept_name as department_name,
          a.full_name as assigned_to_name,
          b.full_name as assigned_by_name
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN users u ON c.user_id = u.user_id
          LEFT JOIN departments d ON c.department_assigned = d.dept_name
          LEFT JOIN users a ON c.assigned_to = a.user_id
          LEFT JOIN users b ON c.assigned_by = b.user_id
          WHERE c.complaint_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$complaint_id]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    header("Location: complaint_management.php");
    exit();
}

// Get timeline
$timeline_query = "SELECT t.*, u.full_name as updated_by_name 
                   FROM complaint_timeline t
                   LEFT JOIN users u ON t.updated_by = u.user_id
                   WHERE t.complaint_id = ?
                   ORDER BY t.created_at ASC";
$timeline_stmt = $db->prepare($timeline_query);
$timeline_stmt->execute([$complaint_id]);
$timeline = $timeline_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get resolution evidence (before/after photos uploaded by department staff)
$evidence = [];
try {
    $evidence_stmt = $db->prepare("SELECT e.*, u.full_name as uploaded_by_name 
                                    FROM complaint_evidence e 
                                    LEFT JOIN users u ON e.uploaded_by = u.user_id 
                                    WHERE e.complaint_id = ? ORDER BY e.created_at ASC");
    $evidence_stmt->execute([$complaint_id]);
    $evidence = $evidence_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $evidence = [];
}
$before_evidence = array_values(array_filter($evidence, fn($e) => $e['evidence_type'] === 'before'));
$after_evidence = array_values(array_filter($evidence, fn($e) => $e['evidence_type'] === 'after'));
$general_evidence = array_values(array_filter($evidence, fn($e) => $e['evidence_type'] === 'general'));

$csrf_token = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Complaint - <?php echo htmlspecialchars($complaint['complaint_number']); ?></title>
    <link rel="stylesheet" href="../admin/css/admin.css">
    <style>
        .complaint-header {
            background: #0a63bc;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        .meta-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .meta-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        .description-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Complaint Details</h1>
                <p>Complaint #<?php echo htmlspecialchars($complaint['complaint_number']); ?></p>
                <span class="role-badge">ADMIN VIEW</span>
            </div>
            <a href="complaint_management.php" class="back-btn">← Back to Complaints</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="complaint-header">
            <h2><?php echo htmlspecialchars($complaint['title']); ?></h2>
            <div class="action-buttons">
                <span class="priority-badge priority-<?php echo $complaint['priority']; ?>">
                    Priority: <?php echo strtoupper($complaint['priority']); ?>
                </span>
                <span class="status-badge status-<?php echo $complaint['status']; ?>">
                    Status: <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                </span>
            </div>
        </div>
        
        <div class="meta-grid">
            <div class="meta-item">
                <span class="meta-label">Student Name</span>
                <span class="meta-value"><?php echo htmlspecialchars($complaint['student_name']); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Student ID</span>
                <span class="meta-value"><?php echo htmlspecialchars($complaint['student_id'] ?? 'N/A'); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Student Email</span>
                <span class="meta-value"><?php echo htmlspecialchars($complaint['student_email'] ?? 'N/A'); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Category</span>
                <span class="meta-value"><?php echo htmlspecialchars($complaint['category_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Department Assigned</span>
                <span class="meta-value"><?php echo htmlspecialchars($complaint['department_assigned'] ?? 'Not assigned'); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Assigned Staff Member</span>
                <span class="meta-value">
                    <?php if (!empty($complaint['assigned_to_name'])): ?>
                        <?php echo htmlspecialchars($complaint['assigned_to_name']); ?>
                        <?php if (!empty($complaint['assigned_by_name'])): ?>
                            <br><small style="font-weight:normal; color:#888;">by <?php echo htmlspecialchars($complaint['assigned_by_name']); ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        Not yet assigned
                    <?php endif; ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Submitted</span>
                <span class="meta-value"><?php echo date('F j, Y g:i A', strtotime($complaint['created_at'])); ?></span>
            </div>
            <?php if ($complaint['resolved_at']): ?>
            <div class="meta-item">
                <span class="meta-label">Resolved</span>
                <span class="meta-value"><?php echo date('F j, Y g:i A', strtotime($complaint['resolved_at'])); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($complaint['affected_count'] > 0): ?>
            <div class="meta-item">
                <span class="meta-label">Affected Students</span>
                <span class="meta-value"><?php echo $complaint['affected_count']; ?> students</span>
            </div>
            <?php endif; ?>
        </div>
        
        <h3>Description</h3>
        <div class="description-box">
            <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
        </div>
        
        <?php if ($complaint['location_building'] || $complaint['location_room']): ?>
        <h3>Location</h3>
        <div class="description-box">
            <strong>Building:</strong> <?php echo htmlspecialchars($complaint['location_building'] ?: 'N/A'); ?><br>
            <strong>Room:</strong> <?php echo htmlspecialchars($complaint['location_room'] ?: 'N/A'); ?><br>
            <?php if ($complaint['location_details']): ?>
            <strong>Additional Details:</strong> <?php echo htmlspecialchars($complaint['location_details']); ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($evidence)): ?>
        <h3>Resolution Evidence</h3>
        <div style="display:flex; gap:25px; flex-wrap:wrap; margin-bottom:20px;">
            <?php foreach (['before' => $before_evidence, 'after' => $after_evidence, 'general' => $general_evidence] as $label => $items): ?>
                <?php if (!empty($items)): ?>
                <div style="flex:1; min-width:220px;">
                    <div style="font-weight:700; font-size:12px; text-transform:uppercase; color:#666; margin-bottom:8px;"><?php echo ucfirst($label); ?></div>
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(110px,1fr)); gap:8px;">
                        <?php foreach ($items as $e): ?>
                            <a href="../<?php echo htmlspecialchars($e['file_path']); ?>" target="_blank" title="<?php echo htmlspecialchars($e['uploaded_by_name'] . ' - ' . $e['created_at']); ?>">
                                <img src="../<?php echo htmlspecialchars($e['file_path']); ?>" style="width:100%; height:90px; object-fit:cover; border-radius:8px;" alt="<?php echo htmlspecialchars($label); ?>">
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <h3>Timeline</h3>
        <div class="timeline">
            <?php foreach ($timeline as $entry): ?>
            <div class="timeline-item">
                <div class="timeline-status">
                    <strong><?php echo ucfirst(str_replace('_', ' ', $entry['status'])); ?></strong>
                </div>
                <div class="timeline-description">
                    <?php echo htmlspecialchars($entry['description']); ?>
                </div>
                <div class="timeline-date">
                    <?php echo date('M j, Y g:i A', strtotime($entry['created_at'])); ?>
                    <?php if ($entry['updated_by_name']): ?>
                        by <?php echo htmlspecialchars($entry['updated_by_name']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Delete Button for Resolved Complaints -->
        <?php if ($complaint['status'] == 'resolved'): ?>
        <div class="action-buttons" style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
            <form method="POST" onsubmit="return confirm('WARNING: Are you sure you want to delete this resolved complaint?\n\nThis action CANNOT be undone!\n\nAll related data will be permanently removed:\n- Complaint details\n- Timeline entries\n- Attachments\n- Ratings\n- Batch members (if any)\n\nClick OK to permanently delete this complaint.')">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="delete_complaint" value="1">
                <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                <button type="submit" class="btn-danger"> Delete Complaint</button>
            </form>
           
        </div>
        <?php endif; ?>
    </div>
</body>
</html>