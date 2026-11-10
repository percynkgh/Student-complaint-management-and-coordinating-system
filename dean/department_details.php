<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('dean') && !Security::hasRole('principal' , 'Dean')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();

// Get initial data for page load (same as before, but we'll also use AJAX)
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$selected_dept = $_GET['department'] ?? 'all';

// Get departments for filter dropdown
$dept_query = "SELECT DISTINCT department_assigned FROM complaints WHERE department_assigned IS NOT NULL ORDER BY department_assigned";
$dept_stmt = $db->query($dept_query);
$departments_filter = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// Initial load of complaints (will be replaced by AJAX but kept for SEO/first render)
$query = "SELECT 
            c.*, cat.category_name, u.full_name as student_name,
            TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as hours_old,
            CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END as resolution_hours
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN users u ON c.user_id = u.user_id
          WHERE DATE(c.created_at) BETWEEN ? AND ?";
$params = [$date_from, $date_to];
if ($selected_dept != 'all') {
    $query .= " AND c.department_assigned = ?";
    $params[] = $selected_dept;
}
$query .= " ORDER BY c.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initial department stats
$stats_query = "SELECT 
                    c.department_assigned as department,
                    COUNT(*) as total,
                    SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    ROUND(AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END), 1) as avg_time,
                    COUNT(CASE WHEN c.priority = 'urgent' THEN 1 END) as urgent,
                    dr.avg_overall as satisfaction,
                    dr.total_ratings
                FROM complaints c
                LEFT JOIN department_ratings dr ON dr.department = c.department_assigned
                WHERE DATE(c.created_at) BETWEEN ? AND ?
                AND c.department_assigned IS NOT NULL
                GROUP BY c.department_assigned
                ORDER BY c.department_assigned";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$date_from, $date_to]);
$dept_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Details - Dean's Dashboard</title>
    <link rel="stylesheet" href="../dean/css/dean.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Department Details</h1>
                <small>Detailed view of all complaints by department</small>
                <div class="role-badge"><?php echo strtoupper($_SESSION['role']); ?> ACCESS</div>
            </div>
            <a href="/SCCS/auth/logout.php" class="logout-btn">Logout</a>
        </div>

        <div class="nav-menu">
            <a href="dashboard.php"> Analytics</a>
            <a href="department_details.php" class="active"> Department Details</a>

            <a href="profile.php"> Profile</a>
        </div>

        <!-- Filter Bar with AJAX form -->
        <div class="filter-bar">
            <form id="filterForm" method="GET" >
                <div class="filter-group">
                    <label>From:</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="filter-group">
                    <label>To:</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="filter-group">
                    <label>Department:</label>
                    <select name="department">
                        <option value="all" <?php echo $selected_dept == 'all' ? 'selected' : ''; ?>>All Departments</option>
                        <?php foreach ($departments_filter as $dept): ?>
                            <option value="<?php echo $dept; ?>" <?php echo $selected_dept == $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit">Apply Filters</button>
                </div>
            </form>
            <button class="export-btn" onclick="exportTableToCSV('department_details_<?php echo date('Y-m-d'); ?>.csv')"> Export to CSV</button>
        </div>

        <!-- Statistics Cards Grid -->
        <h3 style="margin: 20px 0;"> Department Statistics</h3>
        <div id="statsGrid" class="stats-grid">
            <?php foreach ($dept_stats as $stat): ?>
                <div class="dept-stat-card">
                    <div class="dept-stat-header">
                        <span class="dept-stat-name"><?php echo htmlspecialchars($stat['department']); ?></span>
                        <?php if ($stat['satisfaction']): ?>
                            <span class="satisfaction-stars">
                                <?php 
                                $stars = round($stat['satisfaction']);
                                for ($i = 1; $i <= 5; $i++) echo $i <= $stars ? '★' : '☆';
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="dept-stat-numbers">
                        <div class="stat-item"><div class="number"><?php echo $stat['total']; ?></div><div class="label">Total</div></div>
                        <div class="stat-item"><div class="number"><?php echo $stat['resolved']; ?></div><div class="label">Resolved</div></div>
                        <div class="stat-item"><div class="number"><?php echo formatDuration($stat['avg_time'] ?? null); ?></div><div class="label">Avg Resolution Time</div></div>
                        <div class="stat-item"><div class="number"><?php echo $stat['urgent']; ?></div><div class="label">Urgent</div></div>
                        <div class="stat-item"><div class="number"><?php echo $stat['total_ratings'] ?? 0; ?></div><div class="label">Ratings</div></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Complaints Table -->
        <h3 style="margin: 20px 0;"> Complaints List</h3>
        <div class="complaints-table-container">
            <table id="complaintsTable">
                <thead>
                    <tr><th>ID</th><th>Complaint #</th><th>Title</th><th>Student</th><th>Department</th><th>Category</th><th>Priority</th><th>Status</th><th>Age (hrs)</th><th>Resolution (hrs)</th><th>Created</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($complaints)): ?>
                        <tr><td colspan="11" >No complaints found for the selected filters</td></tr>
                    <?php else: ?>
                        <?php foreach ($complaints as $c): ?>
                            <tr>
                                <td><?php echo $c['complaint_id']; ?></td>
                                <td><strong>#<?php echo htmlspecialchars($c['complaint_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['title']); ?></td>
                                <td><?php echo htmlspecialchars($c['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($c['department_assigned']); ?></td>
                                <td><?php echo htmlspecialchars($c['category_name'] ?? 'N/A'); ?></td>
                                <td><span class="priority-badge priority-<?php echo $c['priority']; ?>"><?php echo strtoupper($c['priority']); ?></span></td>
                                <td><span class="status-badge status-<?php echo $c['status']; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                                <td><?php echo $c['hours_old']; ?></td>
                                <td><?php echo $c['resolution_hours'] ?? '—'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="../dean/js/department_details.js"></script>
</body>
</html>