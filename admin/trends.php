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

// Get trend period (default to 12 months)
$period = $_GET['period'] ?? '12';
$period = in_array($period, ['3', '6', '12', '24']) ? $period : '12';

// Monthly trends
$monthly_query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    AVG(CASE WHEN status = 'resolved' THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) ELSE NULL END) as avg_resolution_hours,
                    COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent,
                    COUNT(CASE WHEN is_batch_complaint = 1 THEN 1 END) as batch_count
                  FROM complaints
                  WHERE created_at > DATE_SUB(NOW(), INTERVAL ? MONTH)
                  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                  ORDER BY month DESC";
$monthly_stmt = $db->prepare($monthly_query);
$monthly_stmt->execute([$period]);
$monthly_trends = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);

// Available months for the "compare two months" filter, oldest first
$available_months = array_reverse(array_column($monthly_trends, 'month'));
$default_b = end($available_months) ?: date('Y-m');           // most recent
$default_a = prev($available_months) ?: $default_b;           // month before that
reset($available_months);

$compare_a = $_GET['compare_a'] ?? $default_a;
$compare_b = $_GET['compare_b'] ?? $default_b;
if (!in_array($compare_a, $available_months)) $compare_a = $default_a;
if (!in_array($compare_b, $available_months)) $compare_b = $default_b;

// Per-department complaint counts for the two selected months, used to
// show a clear "how did each department's volume change between these
// two specific months" comparison instead of a fixed this-month/last-month.
$dept_compare_query = "SELECT department_assigned as department,
                        COUNT(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = ? THEN 1 END) as count_a,
                        COUNT(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = ? THEN 1 END) as count_b
                      FROM complaints
                      WHERE department_assigned IS NOT NULL
                      AND DATE_FORMAT(created_at, '%Y-%m') IN (?, ?)
                      GROUP BY department_assigned";
$dept_compare_stmt = $db->prepare($dept_compare_query);
$dept_compare_stmt->execute([$compare_a, $compare_b, $compare_a, $compare_b]);
$dept_compare_rows = $dept_compare_stmt->fetchAll(PDO::FETCH_ASSOC);
$dept_compare_map = [];
foreach ($dept_compare_rows as $row) {
    $dept_compare_map[$row['department']] = $row;
}

// Department trends
$dept_trends_query = "SELECT 
                        department_assigned as department,
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                        AVG(CASE WHEN status = 'resolved' THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) ELSE NULL END) as avg_time
                      FROM complaints
                      WHERE created_at > DATE_SUB(NOW(), INTERVAL ? MONTH)
                      AND department_assigned IS NOT NULL
                      GROUP BY department_assigned
                      ORDER BY total DESC";
$dept_trends_stmt = $db->prepare($dept_trends_query);
$dept_trends_stmt->execute([$period]);
$dept_trends = $dept_trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// Category trends
$cat_trends_query = "SELECT 
                        cat.category_name,
                        COUNT(*) as total,
                        SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                        AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as avg_time
                      FROM complaints c
                      LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                      WHERE c.created_at > DATE_SUB(NOW(), INTERVAL ? MONTH)
                      AND cat.category_name IS NOT NULL
                      GROUP BY cat.category_name
                      ORDER BY total DESC
                      LIMIT 10";
$cat_trends_stmt = $db->prepare($cat_trends_query);
$cat_trends_stmt->execute([$period]);
$cat_trends = $cat_trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// NEW: Academic-department trends (which student departments file the most complaints)
$academic_trends_query = "SELECT 
                        u.department as academic_department,
                        COUNT(*) as total,
                        SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                        COUNT(CASE WHEN c.is_batch_complaint = 1 THEN 1 END) as batch_count
                      FROM complaints c
                      JOIN users u ON c.user_id = u.user_id
                      WHERE c.created_at > DATE_SUB(NOW(), INTERVAL ? MONTH)
                      AND u.department IS NOT NULL AND u.department != ''
                      GROUP BY u.department
                      ORDER BY total DESC
                      LIMIT 10";
$academic_trends_stmt = $db->prepare($academic_trends_query);
$academic_trends_stmt->execute([$period]);
$academic_trends = $academic_trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// NEW: Year-of-study breakdown
$year_trends_query = "SELECT 
                        u.year_of_study,
                        COUNT(*) as total,
                        SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved
                      FROM complaints c
                      JOIN users u ON c.user_id = u.user_id
                      WHERE c.created_at > DATE_SUB(NOW(), INTERVAL ? MONTH)
                      AND u.year_of_study IS NOT NULL
                      GROUP BY u.year_of_study
                      ORDER BY u.year_of_study ASC";
$year_trends_stmt = $db->prepare($year_trends_query);
$year_trends_stmt->execute([$period]);
$year_trends = $year_trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// NEW: Most active class reps (batch complaints filed) - useful now that batch complaints are class-scoped
$class_rep_query = "SELECT 
                        u.full_name, u.department, u.course, u.class_code, u.year_of_study,
                        COUNT(*) as batch_complaints,
                        SUM(c.affected_count) as total_students_represented
                      FROM complaints c
                      JOIN users u ON c.user_id = u.user_id
                      WHERE u.role = 'class_rep'
                      AND c.is_batch_complaint = 1
                      AND c.created_at > DATE_SUB(NOW(), INTERVAL ? MONTH)
                      GROUP BY c.user_id
                      ORDER BY batch_complaints DESC
                      LIMIT 10";
$class_rep_stmt = $db->prepare($class_rep_query);
$class_rep_stmt->execute([$period]);
$class_rep_activity = $class_rep_stmt->fetchAll(PDO::FETCH_ASSOC);

// NEW: Class/section breakdown (department + course + class_code + year) - the exact
// grouping now used to scope batch complaints, so admin can see which classes complain most
$class_scope_query = "SELECT 
                        u.department, u.course, u.class_code, u.year_of_study,
                        COUNT(*) as total_complaints,
                        COUNT(DISTINCT c.user_id) as distinct_submitters,
                        SUM(CASE WHEN c.is_batch_complaint = 1 THEN 1 ELSE 0 END) as batch_complaints
                      FROM complaints c
                      JOIN users u ON c.user_id = u.user_id
                      WHERE u.role IN ('student','class_rep')
                      AND u.department IS NOT NULL AND u.course IS NOT NULL 
                      AND u.class_code IS NOT NULL AND u.year_of_study IS NOT NULL
                      AND c.created_at > DATE_SUB(NOW(), INTERVAL ? MONTH)
                      GROUP BY u.department, u.course, u.class_code, u.year_of_study
                      ORDER BY total_complaints DESC
                      LIMIT 10";
$class_scope_stmt = $db->prepare($class_scope_query);
$class_scope_stmt->execute([$period]);
$class_scope_trends = $class_scope_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate change between the two selected comparison months
$processed_dept_trends = [];
foreach ($dept_trends as $dept) {
    $cmp = $dept_compare_map[$dept['department']] ?? ['count_a' => 0, 'count_b' => 0];
    $count_a = (int) $cmp['count_a'];
    $count_b = (int) $cmp['count_b'];
    $growth = 0;
    if ($count_a > 0) {
        $growth = round((($count_b - $count_a) / $count_a) * 100, 1);
    } elseif ($count_b > 0) {
        $growth = 100;
    }
    $dept['growth'] = $growth;
    $dept['count_a'] = $count_a;
    $dept['count_b'] = $count_b;
    $processed_dept_trends[] = $dept;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trends & Analytics - Admin Dashboard</title>
    <link rel="stylesheet" href="../admin/css/admin.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Trends & Analytics</h1>
                <p>Complaint trends and patterns over time</p>
                <span class="role-badge"><?php echo strtoupper($_SESSION['role']); ?> ACCESS</span>
            </div>
            <div>
                <a href="profile.php" class="nav-item">My Profile</a>
                <a href="reports.php" class="nav-item">Reports</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Navigation Menu -->
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="user_management.php" class="nav-item">User Management</a>
            <a href="complaint_management.php" class="nav-item">Complaint Management</a>
            <a href="analytics.php" class="nav-item">Analytics & Reports</a>
            <a href="trends.php" class="nav-item active">Trends</a>
            <a href="manage_departments.php" class="nav-item">Departments</a>
            <a href="bulk_operations.php" class="nav-item">Bulk Operations</a>
        </div>

        <!-- Period Selector -->
        <div class="period-selector">
            <form method="GET" action="">
                <label for="period">Show last:</label>
                <select name="period" id="period">
                    <option value="3" <?php echo $period == '3' ? 'selected' : ''; ?>>3 months</option>
                    <option value="6" <?php echo $period == '6' ? 'selected' : ''; ?>>6 months</option>
                    <option value="12" <?php echo $period == '12' ? 'selected' : ''; ?>>12 months</option>
                    <option value="24" <?php echo $period == '24' ? 'selected' : ''; ?>>24 months</option>
                </select>
                <button type="submit">Apply</button>
            </form>
        </div>

        <!-- Summary Stats -->
        <?php 
        $total_complaints = array_sum(array_column($monthly_trends, 'total'));
        $total_resolved = array_sum(array_column($monthly_trends, 'resolved'));
        $avg_resolution = !empty($monthly_trends) ? array_sum(array_column($monthly_trends, 'avg_resolution_hours')) / count($monthly_trends) : 0;
        $total_urgent = array_sum(array_column($monthly_trends, 'urgent'));
        $total_batch = array_sum(array_column($monthly_trends, 'batch_count'));
        ?>
        <div class="summary-stats">
            <div class="summary-stat">
                <div class="number"><?php echo $total_complaints; ?></div>
                <div class="label">Total Complaints</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo $total_resolved; ?></div>
                <div class="label">Resolved</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo formatDuration($avg_resolution); ?></div>
                <div class="label">Avg Resolution Time</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo $total_urgent; ?></div>
                <div class="label">Urgent Cases</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo $total_batch; ?></div>
                <div class="label">Batch Complaints</div>
            </div>
        </div>

        <!-- Trends Grid -->
        <div class="trends-grid">
            <!-- Monthly Trends -->
            <div class="trend-card">
                <h3>📈 Monthly Trends</h3>
                <p class="trend-explain">How many complaints were filed each month, how many of those were resolved, and how long resolution took on average, over the period selected above.</p>
                <div class="trend-table-wrap">
                <table class="trend-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total</th>
                            <th>Resolved</th>
                            <th>Avg Resolution Time</th>
                            <th>Urgent</th>
                            <th>Batch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_trends as $trend): ?>
                            <tr>
                                <td><?php echo date('M Y', strtotime($trend['month'] . '-01')); ?></td>
                                <td class="trend-value"><?php echo $trend['total']; ?></td>
                                <td class="trend-value"><?php echo $trend['resolved']; ?></td>
                                <td class="trend-value"><?php echo formatDuration($trend['avg_resolution_hours']); ?></td>
                                <td class="trend-value"><?php echo $trend['urgent']; ?></td>
                                <td class="trend-value"><?php echo $trend['batch_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <!-- Line chart: total complaints per month, one colour per month -->
                <?php if (!empty($monthly_trends)): ?>
                <div class="chart-wrap">
                    <canvas id="monthlyTrendChart" height="220"></canvas>
                </div>
                <?php endif; ?>
            </div>

            <!-- Department Trends -->
            <div class="trend-card">
                <h3>🏢 Department Trends</h3>
                <p class="trend-explain">Which departments get the most complaints, how long they take on average to resolve them, and whether their monthly complaint volume is rising or falling between two months you choose below.</p>

                <form method="GET" class="compare-filter">
                    <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                    <label>Compare</label>
                    <select name="compare_a">
                        <?php foreach ($available_months as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $compare_a ? 'selected' : ''; ?>><?php echo date('M Y', strtotime($m . '-01')); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>against</label>
                    <select name="compare_b">
                        <?php foreach ($available_months as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $compare_b ? 'selected' : ''; ?>><?php echo date('M Y', strtotime($m . '-01')); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Compare</button>
                </form>

                <div class="trend-table-wrap">
                <table class="trend-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Total (Period)</th>
                            <th>Avg Resolution Time</th>
                            <th>
                                <?php echo date('M Y', strtotime($compare_a . '-01')); ?> → <?php echo date('M Y', strtotime($compare_b . '-01')); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($processed_dept_trends as $trend): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($trend['department']); ?></td>
                                <td class="trend-value"><?php echo $trend['total']; ?></td>
                                <td class="trend-value"><?php echo formatDuration($trend['avg_time']); ?></td>
                                <td>
                                    <?php echo $trend['count_a']; ?> → <?php echo $trend['count_b']; ?>
                                    <?php if ($trend['growth'] > 0): ?>
                                        <span class="trend-negative">↑ <?php echo $trend['growth']; ?>% more complaints</span>
                                    <?php elseif ($trend['growth'] < 0): ?>
                                        <span class="trend-positive">↓ <?php echo abs($trend['growth']); ?>% fewer complaints</span>
                                    <?php else: ?>
                                        <span class="trend-neutral">→ no change</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <p class="trend-footnote">The last column compares the number of complaints filed in each of the two selected months. A ↑ increase can mean a growing or newly-noticed issue in that department; a ↓ decrease can mean things are improving, or simply fewer complaints were filed that month.</p>
            </div>

            <!-- Category Trends -->
            <div class="trend-card">
                <h3>🗂️ Top Complaint Categories</h3>
                <p class="trend-explain">The most common types of complaints filed institution-wide, and how quickly each type tends to get resolved on average.</p>
                <div class="trend-table-wrap">
                <table class="trend-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Total</th>
                            <th>Resolved</th>
                            <th>Avg Resolution Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cat_trends as $trend): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($trend['category_name']); ?></td>
                                <td class="trend-value"><?php echo $trend['total']; ?></td>
                                <td class="trend-value"><?php echo $trend['resolved']; ?></td>
                                <td class="trend-value"><?php echo formatDuration($trend['avg_time']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Resolution Time Trends -->
            <div class="trend-card">
                <h3>⏱️ Resolution Time Trends</h3>
                <p class="trend-explain">How the institution-wide average resolution time has changed from one month to the next. A ↓ decrease means complaints are being resolved faster than the previous month; a ↑ increase means they're taking longer.</p>
                <div class="trend-table-wrap">
                <table class="trend-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Avg Resolution Time</th>
                            <th>Change vs Previous Month</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $prev_avg_minutes = null;
                        foreach ($monthly_trends as $trend): 
                            $avg_minutes = $trend['avg_resolution_hours'] !== null ? round($trend['avg_resolution_hours'] * 60) : null;
                            $change = '';
                            $change_class = '';

                            if ($prev_avg_minutes !== null && $avg_minutes !== null) {
                                $diff = $avg_minutes - $prev_avg_minutes;
                                if ($diff > 0) {
                                    $change = '↑ +' . formatDuration($diff / 60) . ' slower';
                                    $change_class = 'trend-negative';
                                } elseif ($diff < 0) {
                                    $change = '↓ ' . formatDuration(abs($diff) / 60) . ' faster';
                                    $change_class = 'trend-positive';
                                } else {
                                    $change = '→ no change';
                                    $change_class = 'trend-neutral';
                                }
                            }
                            $prev_avg_minutes = $avg_minutes;
                        ?>
                            <tr>
                                <td><?php echo date('M Y', strtotime($trend['month'] . '-01')); ?></td>
                                <td class="trend-value"><?php echo formatDuration($trend['avg_resolution_hours']); ?></td>
                                <td class="<?php echo $change_class; ?>"><?php echo $change; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- NEW: Academic Department Trends (who is complaining) -->
            <div class="trend-card">
                <h3>🎓 Complaints by Student Academic Department</h3>
                <p class="trend-explain">Which students' academic departments (e.g. Computer Science, Nursing) file the most complaints - not to be confused with the operational departments that resolve them, shown above.</p>
                <div class="trend-table-wrap">
                <table class="trend-table">
                    <thead>
                        <tr>
                            <th>Academic Dept.</th>
                            <th>Total</th>
                            <th>Resolved</th>
                            <th>Batch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($academic_trends)): ?>
                            <tr><td colspan="4">No data for this period.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($academic_trends as $trend): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($trend['academic_department']); ?></td>
                                <td class="trend-value"><?php echo $trend['total']; ?></td>
                                <td class="trend-value"><?php echo $trend['resolved']; ?></td>
                                <td class="trend-value"><?php echo $trend['batch_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- NEW: Year of Study Breakdown -->
            <div class="trend-card">
                <h3>📚 Complaints by Year of Study</h3>
                <p class="trend-explain">How complaint volume breaks down across students' year of study (Year 1, Year 2, etc.).</p>
                <div class="trend-table-wrap">
                <table class="trend-table">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Total</th>
                            <th>Resolved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($year_trends)): ?>
                            <tr><td colspan="3">No data for this period.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($year_trends as $trend): ?>
                            <tr>
                                <td>Year <?php echo htmlspecialchars($trend['year_of_study']); ?></td>
                                <td class="trend-value"><?php echo $trend['total']; ?></td>
                                <td class="trend-value"><?php echo $trend['resolved']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- NEW: Most Active Class Reps -->
            <div class="trend-card">
                <h3>🧑‍🎓 Most Active Class Representatives</h3>
                <p class="trend-explain">Which class representatives have filed the most batch complaints (one complaint covering their whole class) and how many students each has represented.</p>
                <div class="trend-table-wrap">
                <table class="trend-table">
                    <thead>
                        <tr>
                            <th>Class Rep</th>
                            <th>Class</th>
                            <th>Batch Complaints</th>
                            <th>Students Represented</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($class_rep_activity)): ?>
                            <tr><td colspan="4">No batch complaints in this period.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($class_rep_activity as $rep): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rep['full_name']); ?></td>
                                <td><?php echo htmlspecialchars(trim(($rep['department'] ?? '') . ' · ' . ($rep['course'] ?? '') . ' · ' . ($rep['class_code'] ?? '') . ' · Y' . ($rep['year_of_study'] ?? ''))); ?></td>
                                <td class="trend-value"><?php echo $rep['batch_complaints']; ?></td>
                                <td class="trend-value"><?php echo $rep['total_students_represented']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- NEW: Class/Section Scope Breakdown -->
            <div class="trend-card">
                <h3>🏫 Complaints by Class (Dept · Course · Class · Year)</h3>
                <p class="trend-explain">The most specific view available: which exact class (same department, course, class code and year) files the most complaints, and how many distinct students within that class have submitted at least one.</p>
                <div class="trend-table-wrap">
                <table class="trend-table class-scope-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Course</th>
                            <th>Class</th>
                            <th>Year</th>
                            <th>Total</th>
                            <th>Students</th>
                            <th>Batch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($class_scope_trends)): ?>
                            <tr><td colspan="7">No fully-assigned class data yet. Assign department, course, class and year to students in User Management.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($class_scope_trends as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><?php echo htmlspecialchars($row['course']); ?></td>
                                <td><?php echo htmlspecialchars($row['class_code']); ?></td>
                                <td>Y<?php echo htmlspecialchars($row['year_of_study']); ?></td>
                                <td class="trend-value"><?php echo $row['total_complaints']; ?></td>
                                <td class="trend-value"><?php echo $row['distinct_submitters']; ?></td>
                                <td class="trend-value"><?php echo $row['batch_complaints']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- Insights Section -->
        <div class="insights-section">
            <h3>💡 Key Insights</h3>
            <div class="insights-grid">
                <?php 
                $busiest_month = null;
                if (!empty($monthly_trends)) {
                    $busiest_month = array_reduce($monthly_trends, function($a, $b) {
                        if ($a === null) return $b;
                        return ($a['total'] > $b['total']) ? $a : $b;
                    });
                }

                $best_dept = null;
                if (!empty($processed_dept_trends)) {
                    $best_dept = array_reduce($processed_dept_trends, function($a, $b) {
                        if ($a === null) return $b;
                        $a_time = $a['avg_time'] ?? 999;
                        $b_time = $b['avg_time'] ?? 999;
                        return ($a_time < $b_time) ? $a : $b;
                    });
                }

                $worst_dept = null;
                if (!empty($processed_dept_trends)) {
                    $worst_dept = array_reduce($processed_dept_trends, function($a, $b) {
                        if ($a === null) return $b;
                        $a_time = $a['avg_time'] ?? 0;
                        $b_time = $b['avg_time'] ?? 0;
                        return ($a_time > $b_time) ? $a : $b;
                    });
                }

                $trend_direction = '';
                $trend_value = '';
                $comparison_month = '';
                if (count($monthly_trends) >= 2) {
                    $first = end($monthly_trends);
                    $last = reset($monthly_trends);
                    if ($first && $last) {
                        $change = (($last['total'] - $first['total']) / max($first['total'], 1)) * 100;
                        $trend_direction = $change > 0 ? 'increasing' : 'decreasing';
                        $trend_value = round(abs($change), 1) . '%';
                        $comparison_month = date('F Y', strtotime($first['month'] . '-01'));
                    }
                }

                $top_academic_dept = $academic_trends[0] ?? null;
                $top_class = $class_scope_trends[0] ?? null;
                ?>

                <?php if ($busiest_month): ?>
                    <div class="insight-card">
                        <div class="insight-content">
                            <strong>Busiest Month</strong>
                            <?php echo date('F Y', strtotime($busiest_month['month'] . '-01')); ?> 
                            (<?php echo $busiest_month['total']; ?> complaints)
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($best_dept): ?>
                    <div class="insight-card">
                        <div class="insight-content">
                            <strong>Fastest Department</strong>
                            <?php echo htmlspecialchars($best_dept['department']); ?> 
                            (<?php echo formatDuration($best_dept['avg_time']); ?> avg)
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($worst_dept): ?>
                    <div class="insight-card">
                        <div class="insight-content">
                            <strong>Needs Improvement</strong>
                            <?php echo htmlspecialchars($worst_dept['department']); ?> 
                            (<?php echo formatDuration($worst_dept['avg_time']); ?> avg)
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($trend_direction): ?>
                    <div class="insight-card">
                        <div class="insight-content">
                            <strong>Overall Trend</strong>
                            Complaints are <?php echo $trend_direction; ?> by <?php echo $trend_value; ?>
                            <?php if ($comparison_month): ?>
                                compared to <?php echo $comparison_month; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($top_academic_dept): ?>
                    <div class="insight-card">
                        <div class="insight-content">
                            <strong>Most Complaints From</strong>
                            <?php echo htmlspecialchars($top_academic_dept['academic_department']); ?> students
                            (<?php echo $top_academic_dept['total']; ?> complaints)
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($top_class): ?>
                    <div class="insight-card">
                        <div class="insight-content">
                            <strong>Most Active Class</strong>
                            <?php echo htmlspecialchars($top_class['department'] . ' · ' . $top_class['course'] . ' · ' . $top_class['class_code'] . ' · Y' . $top_class['year_of_study']); ?>
                            (<?php echo $top_class['total_complaints']; ?> complaints)
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($monthly_trends)):
        $chart_months = array_reverse(array_column($monthly_trends, 'month'));
        $chart_totals = array_reverse(array_column($monthly_trends, 'total'));
        $chart_labels = array_map(function($m) { return date('M Y', strtotime($m . '-01')); }, $chart_months);
        // A distinct colour per month so each point on the line is visually its own month
        $palette = ['#0a63bc', '#e74c3c', '#f39c12', '#27ae60', '#8e44ad', '#16a085', '#d35400', '#2980b9', '#c0392b', '#7f8c8d', '#2c3e50', '#f1c40f', '#1abc9c', '#e67e22', '#34495e', '#9b59b6', '#3498db', '#2ecc71', '#e84393', '#00cec9', '#fdcb6e', '#0984e3', '#6c5ce7', '#00b894'];
        $point_colors = [];
        foreach ($chart_totals as $i => $t) { $point_colors[] = $palette[$i % count($palette)]; }
    ?>
    <script>
        const monthlyCtx = document.getElementById('monthlyTrendChart');
        if (monthlyCtx) {
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Total Complaints',
                        data: <?php echo json_encode($chart_totals); ?>,
                        borderColor: '#0a63bc',
                        borderWidth: 2,
                        backgroundColor: 'rgba(10,99,188,0.08)',
                        fill: true,
                        tension: 0.25,
                        pointBackgroundColor: <?php echo json_encode($point_colors); ?>,
                        pointBorderColor: '#ffffff',
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ctx.parsed.y + ' complaint' + (ctx.parsed.y === 1 ? '' : 's')
                            }
                        }
                    },
                    scales: {
                        x: { title: { display: true, text: 'Month' } },
                        y: { title: { display: true, text: 'Total Complaints' }, beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>
