<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('admin') && !Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Handle toggle status (ADD THIS SECTION)
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $toggle_id = (int)$_GET['toggle'];
    $new_status = $_GET['status'] ?? '';
    
    // Prevent self deactivation
    if ($toggle_id == $_SESSION['user_id']) {
        $error = "You cannot change your own account status.";
    } else {
        // Validate status
        if ($new_status == 'active') {
            $update_query = "UPDATE users SET is_active = 1 WHERE user_id = ?";
            $message = "User activated successfully.";
        } elseif ($new_status == 'inactive') {
            $update_query = "UPDATE users SET is_active = 0 WHERE user_id = ?";
            $message = "User deactivated successfully.";
        } else {
            $error = "Invalid status.";
        }
        
        if (empty($error)) {
            $stmt = $db->prepare($update_query);
            if ($stmt->execute([$toggle_id])) {
                // Log the action
                $log_query = "INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                             VALUES (?, 'toggle_user_status', ?, ?)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    "Toggled user ID: {$toggle_id} to {$new_status}",
                    $_SERVER['REMOTE_ADDR']
                ]);
                $success_message = $message;
            } else {
                $error = "Failed to update user status.";
            }
        }
    }
    
    // Refresh user data after toggle
    $query = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Get user details
    $query = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user) {
    header("Location: user_management.php");
    exit();
}

// Get user's complaints
$query = "SELECT c.*, cat.category_name 
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          WHERE c.user_id = ?
          ORDER BY c.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link rel="stylesheet" href="../admin/css/admin.css">
    <style>
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-edit {
            background: #ffc107;
            color: #333;
        }
        .btn-toggle {
            background: #dc3545;
            color: white;
        }
        .btn-toggle.active {
            background: #28a745;
        }
        .btn-reset {
            background: #6c757d;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-left: 10px;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>User Details</h1>
                <p>Viewing profile of <?php echo htmlspecialchars($user['full_name']); ?></p>
                <span class="role-badge"><?php echo strtoupper($_SESSION['role']); ?> ACCESS</span>
            </div>
            <a href="user_management.php" class="back-btn">← Back to Users</a>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="user-card">
            <div class="user-header">
                <div class="user-avatar-large">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div class="user-title">
                    <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <p><?php echo htmlspecialchars($user['email']); ?> | ID: <?php echo htmlspecialchars($user['student_id'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <span class="role-badge role-<?php echo $user['role']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                    </span>
                    <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label"> Department</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['department'] ?? 'Not assigned'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"> Course</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['course'] ?? 'Not assigned'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Year of Study</span>
                    <span class="info-value"><?php echo $user['year_of_study'] ? $user['year_of_study'] . ' Year' : 'Not assigned'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"> Phone</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Last Updated</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($user['updated_at'] ?? $user['created_at'])); ?></span>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-edit">Edit User</a>
                
                <?php if (Security::hasRole('admin') && $user['user_id'] != $_SESSION['user_id']): ?>
                    <?php if ($user['is_active']): ?>
                        <a href="?id=<?php echo $user_id; ?>&toggle=<?php echo $user['user_id']; ?>&status=inactive" 
                           class="btn btn-toggle"
                           onclick="return confirm('Are you sure you want to DEACTIVATE this user? They will not be able to login.')">
                           Deactivate User
                        </a>
                    <?php else: ?>
                        <a href="?id=<?php echo $user_id; ?>&toggle=<?php echo $user['user_id']; ?>&status=active" 
                           class="btn btn-toggle active"
                           onclick="return confirm('Are you sure you want to ACTIVATE this user? They will be able to login again.')">
                            Activate User
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                
                <a href="reset_password.php?id=<?php echo $user['user_id']; ?>" class="btn btn-reset"> Reset Password</a>
            </div>
        </div>
        
        <h3 class="section-title">Complaints by <?php echo htmlspecialchars($user['full_name']); ?></h3>
        
        <?php if (empty($complaints)): ?>
            <div class="empty-state">
                <p>This user has not submitted any complaints yet.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Complaint #</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $c): ?>
                        <tr>
                            <td><a href="../student/view_complaint.php?id=<?php echo $c['complaint_id']; ?>">#<?php echo htmlspecialchars($c['complaint_number']); ?></a></td>
                            <td><?php echo htmlspecialchars($c['title']); ?></td>
                            <td><?php echo htmlspecialchars($c['category_name'] ?? 'N/A'); ?></span>
                            <td>
                                <span class="priority-badge priority-<?php echo $c['priority']; ?>">
                                    <?php echo strtoupper($c['priority']); ?>
                                </span>
                            </span>
                            <td><?php echo ucfirst(str_replace('_', ' ', $c['status'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                            <td>
                                <a href="view_complaint.php?id=<?php echo $c['complaint_id']; ?>" class="view-link">View →</a>
                            </span>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>