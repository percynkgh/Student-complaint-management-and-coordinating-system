<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('admin') && !Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$dept_filter = isset($_GET['dept']) ? $_GET['dept'] : 'all';

// Get all complaints
$query = "SELECT c.*, cat.category_name, u.full_name as student_name, u.student_id
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN users u ON c.user_id = u.user_id";
          
$params = [];
$where = [];

if ($status_filter != 'all') {
    $where[] = "c.status = ?";
    $params[] = $status_filter;
}

if ($dept_filter != 'all') {
    $where[] = "c.department_assigned = ?";
    $params[] = $dept_filter;
}

if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}

$query .= " ORDER BY c.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$dept_query = "SELECT DISTINCT department_assigned FROM complaints WHERE department_assigned IS NOT NULL";
$dept_stmt = $db->query($dept_query);
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Management</title>
    <link rel="stylesheet" href="../admin/css/admin.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Complaint Management</h1>
            <div>
                <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <select class="filter-select" onchange="window.location.href='?status='+this.value+'&dept=<?php echo $dept_filter; ?>'">
                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                <option value="under_review" <?php echo $status_filter == 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            
            <select class="filter-select" onchange="window.location.href='?status=<?php echo $status_filter; ?>&dept='+this.value">
                <option value="all" <?php echo $dept_filter == 'all' ? 'selected' : ''; ?>>All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept; ?>" <?php echo $dept_filter == $dept ? 'selected' : ''; ?>>
                        <?php echo $dept; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Quick Stats -->
        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($complaints); ?></div>
                <div>Showing</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $urgent = array_filter($complaints, function($c) { return $c['priority'] == 'urgent'; });
                    echo count($urgent);
                    ?>
                </div>
                <div>Urgent</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $resolved = array_filter($complaints, function($c) { return $c['status'] == 'resolved'; });
                    echo count($resolved);
                    ?>
                </div>
                <div>Resolved</div>
            </div>
        </div>
        
        <!-- Complaints Table -->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Complaint #</th>
                    <th>Title</th>
                    <th>Student</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Department</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($complaints as $c): ?>
                <tr>
                    <td><?php echo $c['complaint_id']; ?></td>
                    <td>#<?php echo htmlspecialchars($c['complaint_number']); ?></td>
                    <td><?php echo htmlspecialchars(substr($c['title'], 0, 50)); ?></td>
                    <td><?php echo htmlspecialchars($c['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($c['category_name'] ?? 'N/A'); ?></td>
                    <td>
                        <span class="priority-badge priority-<?php echo $c['priority']; ?>">
                            <?php echo strtoupper($c['priority']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo $c['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $c['status'])); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($c['department_assigned'] ?? 'Unassigned'); ?></td>
                    <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                    <td>
                        <a href="view_complaint.php?id=<?php echo $c['complaint_id']; ?>" class="action-btn btn-view">View</a>
                        <?php if (Security::hasRole('admin')): ?>
                            <a href="edit_complaint.php?id=<?php echo $c['complaint_id']; ?>" class="action-btn btn-edit">Edit</a>
                            <?php if (in_array($c['status'], ['resolved', 'rejected', 'closed'])): ?>
                        
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


</body>
</html>