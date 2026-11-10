<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('admin')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$cm = new ComplaintManager();

// Ensure dept_type column exists
try {
    $check_column = $db->query("SHOW COLUMNS FROM departments LIKE 'dept_type'");
    if ($check_column->rowCount() == 0) {
        $db->exec("ALTER TABLE departments ADD COLUMN dept_type ENUM('academic', 'operational') DEFAULT 'operational'");
    }
} catch (PDOException $e) {
    // Column might already exist
}

// Get real statistics from database
$stats = [];

// Total users
$query = "SELECT COUNT(*) as total FROM users";
$stmt = $db->query($query);
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Users by role
$query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$stmt = $db->query($query);
$users_by_role = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $users_by_role[$row['role']] = $row['count'];
}

// Total complaints
$query = "SELECT COUNT(*) as total FROM complaints";
$stmt = $db->query($query);
$stats['total_complaints'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Complaints by status
$query = "SELECT status, COUNT(*) as count FROM complaints GROUP BY status";
$stmt = $db->query($query);
$complaints_by_status = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $complaints_by_status[$row['status']] = $row['count'];
}

// Active complaints (not resolved/closed)
$query = "SELECT COUNT(*) as total FROM complaints WHERE status NOT IN ('resolved', 'closed', 'rejected')";
$stmt = $db->query($query);
$stats['active_complaints'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Resolved this week
$query = "SELECT COUNT(*) as total FROM complaints 
          WHERE status = 'resolved' 
          AND resolved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$stmt = $db->query($query);
$stats['resolved_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Urgent complaints
$query = "SELECT COUNT(*) as total FROM complaints WHERE priority = 'urgent' AND status NOT IN ('resolved', 'closed')";
$stmt = $db->query($query);
$stats['urgent_complaints'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Department count - ONLY OPERATIONAL DEPARTMENTS
$query = "SELECT COUNT(*) as total FROM departments WHERE dept_type = 'operational' OR dept_type IS NULL";
$stmt = $db->query($query);
$stats['total_departments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get department performance - ONLY OPERATIONAL DEPARTMENTS
$query = "SELECT d.dept_name, 
          COUNT(c.complaint_id) as total_complaints,
          SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
          AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as avg_resolution_hours
          FROM departments d
          LEFT JOIN complaints c ON c.department_assigned = d.dept_name
          WHERE d.dept_type = 'operational' OR d.dept_type IS NULL
          GROUP BY d.dept_id
          HAVING total_complaints > 0 OR resolved > 0
          ORDER BY avg_resolution_hours ASC";
$stmt = $db->query($query);
$department_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all operational departments count for the note
$all_operational_query = "SELECT COUNT(*) as total FROM departments WHERE dept_type = 'operational'";
$all_operational_stmt = $db->query($all_operational_query);
$all_operational_count = $all_operational_stmt->fetchColumn();

// Get academic departments count for info
$academic_query = "SELECT COUNT(*) as total FROM departments WHERE dept_type = 'academic'";
$academic_stmt = $db->query($academic_query);
$academic_count = $academic_stmt->fetchColumn();

// Recent complaints
$query = "SELECT c.*, cat.category_name, u.full_name as student_name
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN users u ON c.user_id = u.user_id
          ORDER BY c.created_at DESC
          LIMIT 10";
$stmt = $db->query($query);
$recent_complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent users
$query = "SELECT * FROM users ORDER BY created_at DESC LIMIT 10";
$stmt = $db->query($query);
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Complaint System</title>
    <link rel="stylesheet" href="../admin/css/admin.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Administration Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                <span class="role-badge"><?php echo strtoupper($_SESSION['role']); ?> ACCESS</span>
            </div>
            <div>
            <a href="direct_messages.php" class="nav-item">
    Direct Messages
    <?php
    $unread_query = "SELECT COUNT(*) as total FROM direct_messages 
                     WHERE receiver_id = ? AND is_read = FALSE";
    $unread_stmt = $db->prepare($unread_query);
    $unread_stmt->execute([$_SESSION['user_id']]);
    $unread = $unread_stmt->fetch(PDO::FETCH_ASSOC);
    if ($unread && $unread['total'] > 0): 
    ?>
        <span class="unread-badge-inline" style="background:#dc3545; color:white; border-radius:12px; padding:2px 8px; font-size:11px; margin-left:5px;">
            <?php echo $unread['total']; ?>
        </span>
    <?php endif; ?>
</a>
                <a href="profile.php" class="nav-item">My Profile</a>
                <a href="reports.php" class="nav-item">Reports</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item active">Dashboard</a>
            <a href="user_management.php" class="nav-item">User Management</a>
            <a href="complaint_management.php" class="nav-item">Complaint Management</a>
            <a href="analytics.php" class="nav-item">Analytics & Reports</a>
            <a href="trends.php" class="nav-item">Trends</a>
            <a href="manage_departments.php" class="nav-item">Departments</a>
            <a href="bulk_operations.php" class="nav-item">Bulk Operations</a>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <a href="user_management.php" class="stat-card">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-detail">
                    <?php 
                    echo isset($users_by_role['student']) ? ' ' . $users_by_role['student'] . ' students' : '';
                    echo isset($users_by_role['staff']) ? ' |  ' . $users_by_role['staff'] . ' staff' : '';   
                    ?>
                </div>
            </a>
            
            <a href="complaint_management.php" class="stat-card">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['total_complaints']; ?></div>
                <div class="stat-label">Total Complaints</div>
                <div class="stat-detail">
                    <?php echo $stats['active_complaints']; ?> active | <?php echo $stats['resolved_week']; ?> resolved this week
                </div>
            </a>
            
            <a href="complaint_management.php?filter=urgent" class="stat-card">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['urgent_complaints']; ?></div>
                <div class="stat-label">Urgent Complaints</div>
                <div class="stat-detail">Requires immediate attention</div>
            </a>
            
            <a href="manage_departments.php" class="stat-card">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['total_departments']; ?></div>
                <div class="stat-label">Operational Departments</div>
                <div class="stat-detail">
                    <?php echo $all_operational_count; ?> active | 
                    <?php if ($academic_count > 0): ?>
                        <?php echo $academic_count; ?> academic hidden
                    <?php endif; ?>
                </div>
            </a>
        </div>
        
        <!-- Department Performance (only for Dean/Principal/Admin) - ONLY OPERATIONAL DEPARTMENTS -->
        <?php if (($_SESSION['role'] == 'dean' || $_SESSION['role'] == 'principal' || $_SESSION['role'] == 'admin') && !empty($department_performance)): ?>
        <div class="card performance-card">
            <div class="card-title">
                <span> Operational Department Performance Rankings</span>
                <a href="analytics.php" class="view-all">View Detailed Analytics →</a>
                <a href="trends.php" class="view-all">View Trends →</a>
            </div>
            
            <div class="performance-grid">
                <!-- Rankings Column -->
                <div class="rankings-column">
                    <h4 class="rankings-title">Response Time Leaderboard</h4>
                    <?php 
                    $rank = 1;
                    foreach ($department_performance as $dept): 
                    ?>
                        <div class="department-rank-item">
                            <div class="rank-number <?php 
                                if ($rank == 1) echo 'rank-gold';
                                elseif ($rank == 2) echo 'rank-silver';
                                elseif ($rank == 3) echo 'rank-bronze';
                                else echo 'rank-default';
                            ?>">
                                <?php echo $rank; ?>
                            </div>
                            <div class="dept-info">
                                <div class="dept-name"><?php echo htmlspecialchars($dept['dept_name']); ?></div>
                                <div class="dept-stats">
                                    <?php echo $dept['total_complaints']; ?> complaints | 
                                    <?php echo $dept['resolved']; ?> resolved
                                    <?php if ($dept['avg_resolution_hours']): ?>
                                        | Avg: <?php echo formatDuration($dept['avg_resolution_hours']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                        $rank++;
                        if ($rank > 5) break;
                    endforeach; 
                    ?>
                </div>
                
                <!-- Resolution Efficiency Column -->
                <div class="efficiency-column">
                    <h4 class="efficiency-title"> Resolution Efficiency</h4>
                    <?php foreach (array_slice($department_performance, 0, 5) as $dept): 
                        $avg_time = $dept['avg_resolution_hours'] ?: 48;
                        $max_time = 72;
                        $percentage = min(100, ($avg_time / $max_time) * 100);
                        $performance = 100 - $percentage;
                    ?>
                        <div class="efficiency-item">
                            <div class="efficiency-header">
                                <span class="efficiency-dept-name"><?php echo htmlspecialchars($dept['dept_name']); ?></span>
                                <span class="efficiency-time">
                                    <?php echo $dept['avg_resolution_hours'] ? formatDuration($dept['avg_resolution_hours']) : 'N/A'; ?>
                                </span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $performance; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php elseif (($academic_count > 0)): ?>
        <div class="alert alert-info">
            <strong></strong> 
            <?php echo $academic_count; ?>
        </div>
        <?php endif; ?>
        
        <!-- Recent Activity Grid -->
        <div class="grid-2">
            <!-- Recent Complaints -->
            <div class="card">
                <div class="card-title">
                    <span>Recent Complaints</span>
                    <a href="complaint_management.php" class="view-all">View All →</a>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Priority</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_complaints)): ?>
                                <tr>
                                    <td colspan="4" class="empty-state">No complaints found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_complaints as $c): ?>
                                <tr>
                                    <td>#<?php echo substr($c['complaint_number'], -4); ?></td>
                                    <td>
                                        <a href="view_complaint.php?id=<?php echo $c['complaint_id']; ?>" class="complaint-link">
                                            <?php echo htmlspecialchars(substr($c['title'], 0, 30)) . '...'; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $c['status'] == 'resolved' ? 'success' : 
                                                ($c['status'] == 'submitted' ? 'info' : 
                                                ($c['status'] == 'rejected' ? 'danger' : 'warning')); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $c['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority-badge priority-<?php echo $c['priority']; ?>">
                                            <?php echo strtoupper($c['priority']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Users -->
            <div class="card">
                <div class="card-title">
                    <span>👤 Recent Users</span>
                    <a href="user_management.php" class="view-all">Manage Users →</a>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_users)): ?>
                                <tr>
                                    <td colspan="3" class="empty-state">No users found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_users as $u): ?>
                                <tr>
                                    <td>
                                        <a href="user_details.php?id=<?php echo $u['user_id']; ?>" class="user-link">
                                            <?php echo htmlspecialchars($u['full_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $u['role'] == 'admin' ? 'danger' : 
                                                ($u['role'] == 'staff' ? 'info' : 
                                                ($u['role'] == 'class_rep' ? 'warning' : 'success')); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $u['role'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
       
    </div>
    
    <script src="../admin/js/admin-core.js"></script>
    <script src="../admin/js/dashboard.js"></script>
</body>
</html>