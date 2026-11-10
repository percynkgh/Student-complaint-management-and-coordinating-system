<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();

// Get department performance data - FAST vs LAGGING
$query = "SELECT 
            c.department_assigned as department,
            COUNT(*) as total_complaints,
            SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            ROUND(AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END), 1) as avg_resolution_hours,
            ROUND(AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(DAY, c.created_at, c.resolved_at) ELSE NULL END), 1) as avg_resolution_days
          FROM complaints c
          WHERE c.department_assigned IS NOT NULL
          AND c.created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
          GROUP BY c.department_assigned
          ORDER BY avg_resolution_hours ASC";

$stmt = $db->query($query);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall average for comparison (only if there are resolved complaints)
$overall_avg = 0;
$total_depts = count($departments);
$valid_depts = 0;

if ($total_depts > 0) {
    $sum = 0;
    foreach ($departments as $dept) {
        if ($dept['avg_resolution_hours'] > 0) {
            $sum += $dept['avg_resolution_hours'];
            $valid_depts++;
        }
    }
    if ($valid_depts > 0) {
        $overall_avg = $sum / $valid_depts;
    }
}

// Separate fast and lagging departments
$fast_depts = [];
$lagging_depts = [];

foreach ($departments as $dept) {
    // Skip departments with no resolution data
    if ($dept['avg_resolution_hours'] <= 0) {
        continue;
    }
    
    if ($dept['avg_resolution_hours'] < $overall_avg) {
        $fast_depts[] = $dept;
    } else {
        $lagging_depts[] = $dept;
    }
}

// Sort fast departments by fastest first
usort($fast_depts, function($a, $b) {
    return $a['avg_resolution_hours'] <=> $b['avg_resolution_hours'];
});

// Sort lagging departments by slowest first
usort($lagging_depts, function($a, $b) {
    return $b['avg_resolution_hours'] <=> $a['avg_resolution_hours'];
});

// Handle export request
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="department_performance_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    // Headers
    fputcsv($output, ['Department', 'Total Complaints', 'Resolved', 'Avg Hours', 'Avg Days', 'Performance vs Average']);
    
    // Data
    foreach ($departments as $dept) {
        $performance = 'No data';
        if ($overall_avg > 0 && $dept['avg_resolution_hours'] > 0) {
            $comparison = $dept['avg_resolution_hours'] < $overall_avg ? 'Faster' : 'Slower';
            $diff = abs(round(($dept['avg_resolution_hours'] / $overall_avg) * 100 - 100, 1));
            $performance = $comparison . ' by ' . $diff . '%';
        }
        
        fputcsv($output, [
            $dept['department'],
            $dept['total_complaints'],
            $dept['resolved'],
            $dept['avg_resolution_hours'] ?: 'N/A',
            $dept['avg_resolution_days'] ?: 'N/A',
            $performance
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dean's Dashboard - Department Performance</title>
   <link rel="stylesheet" href="../dean/css/dean.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Dean's Dashboard</h1>
                <small>Department Performance Comparison</small>
                <div class="role-badge"><?php echo strtoupper($_SESSION['role']); ?></div>
            </div>
            <div class="header-actions">
                <!-- Simple Export Button -->
                <a href="?export=1" class="export-btn">
                     Export CSV
                </a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <!-- Simple Navigation -->
        <div class="nav-menu">
            <a href="dashboard.php" class="active"> Performance</a>
             <a href="department_details.php" > Department Details</a>

            <a href="profile.php"> Profile</a>
        </div>
        
        <!-- Summary Stats -->
        <div class="stats-summary">
            <div class="stat-box">
                <div class="number"><?php echo $total_depts; ?></div>
                <div class="label">Total Departments</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo $overall_avg > 0 ? formatDuration($overall_avg) : 'N/A'; ?></div>
                <div class="label">Average Resolution Time</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo count($fast_depts); ?></div>
                <div class="label">Fast Departments</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo count($lagging_depts); ?></div>
                <div class="label">Lagging Departments</div>
            </div>
        </div>
        
        <!-- Export Note (subtle) -->
        <div class="export-note">
            Click Export CSV to download this data
        </div>
        
        <!-- Fast vs Lagging Comparison - EXACTLY what requirement says -->
        <div class="comparison-grid">
            <!-- Fast Departments -->
            <div class="card fast-card">
                <h2>
                    <span></span> 
                    Fast Departments (Below Average)
                </h2>
                
                <?php if (empty($fast_depts)): ?>
                    <div class="empty-state">No fast departments with data</div>
                <?php else: ?>
                    <ul class="department-list">
                        <?php foreach ($fast_depts as $dept): 
                            // Prevent division by zero
                            $diff = 0;
                            if ($overall_avg > 0) {
                                $diff = round(100 - ($dept['avg_resolution_hours'] / $overall_avg) * 100, 1);
                            }
                        ?>
                            <li class="department-item">
                                <div class="dept-info">
                                    <h3><?php echo htmlspecialchars($dept['department']); ?></h3>
                                    <div class="dept-stats">
                                        <span> <?php echo $dept['total_complaints']; ?> complaints</span>
                                        <span> <?php echo $dept['resolved']; ?> resolved</span>
                                    </div>
                                </div>
                                <div class="dept-metric">
                                    <div class="dept-time fast-time"><?php echo formatDuration($dept['avg_resolution_hours']); ?></div>
                                    <div class="dept-days">(<?php echo $dept['avg_resolution_days']; ?> days)</div>
                                    <?php if ($overall_avg > 0): ?>
                                        <div class="vs-average faster"> <?php echo $diff; ?>% faster</div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <!-- Lagging Departments -->
            <div class="card lagging-card">
                <h2>
                    <span></span> 
                    Lagging Departments (Above Average)
                </h2>
                
                <?php if (empty($lagging_depts)): ?>
                    <div class="empty-state">No lagging departments with data</div>
                <?php else: ?>
                    <ul class="department-list">
                        <?php foreach ($lagging_depts as $dept): 
                            // Prevent division by zero
                            $diff = 0;
                            if ($overall_avg > 0) {
                                $diff = round(($dept['avg_resolution_hours'] / $overall_avg) * 100 - 100, 1);
                            }
                        ?>
                            <li class="department-item">
                                <div class="dept-info">
                                    <h3><?php echo htmlspecialchars($dept['department']); ?></h3>
                                    <div class="dept-stats">
                                        <span> <?php echo $dept['total_complaints']; ?> complaints</span>
                                        <span> <?php echo $dept['resolved']; ?> resolved</span>
                                    </div>
                                </div>
                                <div class="dept-metric">
                                    <div class="dept-time lagging-time"><?php echo formatDuration($dept['avg_resolution_hours']); ?></div>
                                    <div class="dept-days">(<?php echo $dept['avg_resolution_days']; ?> days)</div>
                                    <?php if ($overall_avg > 0): ?>
                                        <div class="vs-average slower"> <?php echo $diff; ?>% slower</div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Simple Footer Note -->
        <div >
            Based on complaints from the last 6 months • 
            <?php if ($overall_avg > 0): ?>
                Average resolution: <?php echo round($overall_avg, 1); ?> hours
            <?php else: ?>
                No resolution data available yet
            <?php endif; ?>
        </div>
    </div>
</body>
</html>