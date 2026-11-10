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

// Get historical trends
$trends = $cm->getHistoricalTrends(12);

// Prepare data for trends chart
$trends_labels = [];
$trends_data = [];
$trends_resolved = [];

if (!empty($trends) && is_array($trends)) {
    foreach ($trends as $trend) {
        $trends_labels[] = $trend['month_name'] ?? $trend['month'] ?? 'Unknown';
        $trends_data[] = isset($trend['submitted']) ? (int)$trend['submitted'] : 0;
        $trends_resolved[] = isset($trend['resolved']) ? (int)$trend['resolved'] : 0;
    }
}

// Fallback query if no data from getHistoricalTrends
if (empty($trends_labels)) {
    $query = "SELECT 
                DATE_FORMAT(created_at, '%M %Y') as month_name,
                COUNT(*) as submitted,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
              FROM complaints 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY MIN(created_at) ASC";
    $stmt = $db->query($query);
    $direct_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($direct_trends as $trend) {
        $trends_labels[] = $trend['month_name'];
        $trends_data[] = (int)$trend['submitted'];
        $trends_resolved[] = (int)$trend['resolved'];
    }
}

// Get department analytics - ONLY OPERATIONAL DEPARTMENTS
// First, let's get all operational departments
$operational_depts_query = "SELECT dept_name FROM departments WHERE dept_type = 'operational' ORDER BY dept_name";
$operational_depts_stmt = $db->query($operational_depts_query);
$operational_dept_names = $operational_depts_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get analytics only for operational departments
$dept_analytics = [];
if (!empty($operational_dept_names)) {
    $placeholders = implode(',', array_fill(0, count($operational_dept_names), '?'));
    $query = "SELECT 
                d.dept_name,
                COUNT(c.complaint_id) as total_complaints,
                SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as avg_resolution_hours,
                AVG(r.overall_rating) as satisfaction_score,
                COUNT(DISTINCT r.rating_id) as total_ratings
              FROM departments d
              LEFT JOIN complaints c ON c.department_assigned = d.dept_name
              LEFT JOIN complaint_ratings r ON c.complaint_id = r.complaint_id
              WHERE d.dept_type = 'operational'
              GROUP BY d.dept_id
              ORDER BY total_complaints DESC";
    $stmt = $db->query($query);
    $dept_analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Also get academic departments count for info display
$academic_count_query = "SELECT COUNT(*) as total FROM departments WHERE dept_type = 'academic'";
$academic_count_stmt = $db->query($academic_count_query);
$academic_count = $academic_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

$departments_labels = [];
$departments_totals = [];
$departments_resolved = [];
$departments_avg_hours = [];

foreach ($dept_analytics as $dept) {
    $departments_labels[] = $dept['dept_name'];
    $departments_totals[] = (int)($dept['total_complaints'] ?? 0);
    $departments_resolved[] = (int)($dept['resolved'] ?? 0);
    $departments_avg_hours[] = isset($dept['avg_resolution_hours']) && is_numeric($dept['avg_resolution_hours']) 
        ? round($dept['avg_resolution_hours'], 1) : 0;
}

// Get priority distribution
$query = "SELECT priority, COUNT(*) as count FROM complaints GROUP BY priority";
$stmt = $db->query($query);
$priorities_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$priorities = ['urgent' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
foreach ($priorities_raw as $p) {
    $key = strtolower($p['priority']);
    if (isset($priorities[$key])) {
        $priorities[$key] = (int)$p['count'];
    }
}

// Get satisfaction scores
$query = "SELECT 
            AVG(speed_rating) as speed,
            AVG(communication_rating) as communication,
            AVG(effectiveness_rating) as effectiveness,
            AVG(overall_rating) as overall
          FROM complaint_ratings";
$stmt = $db->query($query);
$satisfaction_scores = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$satisfaction_scores || $satisfaction_scores['speed'] === null) {
    $satisfaction_scores = ['speed' => 0, 'communication' => 0, 'effectiveness' => 0, 'overall' => 0];
}

// Get overall stats
$stats = [];

// Average resolution time
$query = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_time 
          FROM complaints WHERE status = 'resolved' AND resolved_at IS NOT NULL";
$stmt = $db->query($query);
$stats['avg_resolution'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_time'] ?? 0, 1);

// Fastest and slowest
$query = "SELECT MIN(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as fastest,
          MAX(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as slowest
          FROM complaints WHERE status = 'resolved' AND resolved_at IS NOT NULL";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['fastest'] = $result['fastest'] ?? 0;
$stats['slowest'] = $result['slowest'] ?? 0;

// Average satisfaction
$stats['avg_satisfaction'] = round($satisfaction_scores['overall'] ?? 0, 1);

// Satisfaction trends
$query = "SELECT 
            DATE_FORMAT(r.created_at, '%Y-%m') as month,
            AVG(r.overall_rating) as avg_rating
          FROM complaint_ratings r
          GROUP BY DATE_FORMAT(r.created_at, '%Y-%m')
          ORDER BY month DESC
          LIMIT 6";
$stmt = $db->query($query);
$satisfaction_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../admin/css/admin.css">
    <style>
        .info-box {
            background: #e3f2fd;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #1976d2;
            font-size: 14px;
        }
        .performance-excellent { color: #28a745; font-weight: bold; }
        .performance-good { color: #ffc107; font-weight: bold; }
        .performance-poor { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Analytics & Insights</h1>
                <p>Performance metrics for operational departments</p>
                <span class="role-badge"><?php echo strtoupper($_SESSION['role']); ?> ACCESS</span>
            </div>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <?php if ($academic_count > 0): ?>
        
        <?php endif; ?>
        
        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Average Resolution Time</div>
                <div class="stat-number" id="avgResolution"><?php echo formatDuration($stats['avg_resolution']); ?></div>
                <div class="stat-detail">Across all operational departments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Fastest Resolution</div>
                <div class="stat-number" id="fastestResolution"><?php echo formatDuration($stats['fastest']); ?></div>
                <div class="stat-detail">Best performance record</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Slowest Resolution</div>
                <div class="stat-number" id="slowestResolution"><?php echo formatDuration($stats['slowest']); ?></div>
                <div class="stat-detail">Needs improvement</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Overall Satisfaction</div>
                <div class="stat-number" id="avgSatisfaction"><?php echo $stats['avg_satisfaction']; ?>/5</div>
                <div class="stat-detail">★★★★★</div>
            </div>
        </div>
        

        
        <!-- Department Performance -->
        <h2>Operational Department Performance</h2>
        <?php if (empty($dept_analytics)): ?>
            <div class="alert alert-info">
                <strong>No data available</strong><br>
                No operational departments found or no complaints have been assigned yet.
                <a href="department_management.php" class="btn-primary" style="margin-left: 10px;">Manage Departments</a>
            </div>
        <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Total Complaints</th>
                        <th>Resolved</th>
                        <th>Resolution Rate</th>
                        <th>Avg Time (hrs)</th>
                        <th>Satisfaction</th>
                      
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dept_analytics as $dept): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($dept['dept_name']); ?></strong>
                            <span class="badge badge-success" style="margin-left: 8px;">Operational</span>
                        </td>
                        <td><?php echo $dept['total_complaints'] ?? 0; ?></td>
                        <td><?php echo $dept['resolved'] ?? 0; ?></td>
                        <td>
                            <?php 
                            $rate = ($dept['total_complaints'] ?? 0) > 0 ? round((($dept['resolved'] ?? 0) / ($dept['total_complaints'] ?? 1)) * 100) : 0;
                            echo $rate . '%';
                            ?>
                            <div class="progress-bar" style="margin-top: 5px;">
                                <div class="progress-fill" style="width: <?php echo $rate; ?>%;"></div>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $avg_time = $dept['avg_resolution_hours'] ?? 'N/A';
                            if (is_numeric($avg_time)) {
                                echo formatDuration($avg_time);
                                if ($avg_time <= 24) {
                                    echo ' <span class="badge badge-success">Fast</span>';
                                } elseif ($avg_time <= 48) {
                                    echo ' <span class="badge badge-warning">Average</span>';
                                } else {
                                    echo ' <span class="badge badge-danger">Slow</span>';
                                }
                            } else {
                                echo $avg_time;
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($dept['satisfaction_score'] && $dept['satisfaction_score'] > 0): ?>
                                <div>
                                    <span class="stat-number" style="font-size: 18px;"><?php echo number_format($dept['satisfaction_score'], 1); ?>/5</span>
                                    <div class="rating-bar">
                                        <div class="rating-fill" style="width: <?php echo $dept['satisfaction_score'] * 20; ?>%;"></div>
                                    </div>
                                </div>
                                <small><?php echo $dept['total_ratings']; ?> ratings</small>
                            <?php else: ?>
                                <span class="badge badge-info">No ratings yet</span>
                            <?php endif; ?>
                        </td>
                       
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Priority Distribution -->
        <div class="grid-2">
            <div class="card">
                <div class="card-title">
                    <span>Priority Distribution</span>
                </div>
                <canvas id="priorityChart" 
                    data-urgent="<?php echo $priorities['urgent']; ?>"
                    data-high="<?php echo $priorities['high']; ?>"
                    data-medium="<?php echo $priorities['medium']; ?>"
                    data-low="<?php echo $priorities['low']; ?>">
                </canvas>
            </div>
            
            <div class="card">
                <div class="card-title">
                    <span>Satisfaction Metrics</span>
                </div>
                <canvas id="satisfactionChart"
                    data-speed="<?php echo round($satisfaction_scores['speed'], 1); ?>"
                    data-communication="<?php echo round($satisfaction_scores['communication'], 1); ?>"
                    data-effectiveness="<?php echo round($satisfaction_scores['effectiveness'], 1); ?>"
                    data-overall="<?php echo round($satisfaction_scores['overall'], 1); ?>">
                </canvas>
            </div>
        </div>
        
    
    <script src="../admin/js/analytics.js"></script>
    <script>
        // Priority Distribution Chart
        const priorityCtx = document.getElementById('priorityChart')?.getContext('2d');
        if (priorityCtx) {
            const priorityData = {
                labels: ['Urgent', 'High', 'Medium', 'Low'],
                datasets: [{
                    data: [
                        parseInt(document.getElementById('priorityChart')?.dataset.urgent || 0),
                        parseInt(document.getElementById('priorityChart')?.dataset.high || 0),
                        parseInt(document.getElementById('priorityChart')?.dataset.medium || 0),
                        parseInt(document.getElementById('priorityChart')?.dataset.low || 0)
                    ],
                    backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#28a745'],
                    borderWidth: 0
                }]
            };
            new Chart(priorityCtx, {
                type: 'doughnut',
                data: priorityData,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
        
        // Satisfaction Chart
        const satCtx = document.getElementById('satisfactionChart')?.getContext('2d');
        if (satCtx) {
            const satData = {
                labels: ['Speed', 'Communication', 'Effectiveness', 'Overall'],
                datasets: [{
                    label: 'Rating (1-5)',
                    data: [
                        parseFloat(document.getElementById('satisfactionChart')?.dataset.speed || 0),
                        parseFloat(document.getElementById('satisfactionChart')?.dataset.communication || 0),
                        parseFloat(document.getElementById('satisfactionChart')?.dataset.effectiveness || 0),
                        parseFloat(document.getElementById('satisfactionChart')?.dataset.overall || 0)
                    ],
                    backgroundColor: '#0A63BC',
                    borderRadius: 5
                }]
            };
            new Chart(satCtx, {
                type: 'bar',
                data: satData,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5,
                            title: { display: true, text: 'Rating (1-5)' }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    </script>
</body>
</html>