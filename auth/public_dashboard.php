<?php
// public_dashboard.php - No login required
require_once '../config/database.php';
require_once '../config/complaint_functions.php';

$db = (new Database())->getConnection();
$cm = new ComplaintManager();

// DIRECT QUERY for department rankings - ONLY OPERATIONAL DEPARTMENTS
$dept_ratings = [];

try {
    // Direct query to get department ratings - Filter to only operational departments
    $dept_query = "SELECT dr.* 
                   FROM department_ratings dr
                   JOIN departments d ON dr.department = d.dept_name
                   WHERE d.dept_type = 'operational' 
                   AND dr.total_ratings > 0 
                   ORDER BY dr.avg_overall DESC, dr.total_ratings DESC 
                   LIMIT 10";
    $dept_stmt = $db->query($dept_query);
    $dept_ratings = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug - log the result
    error_log("Operational departments with ratings found: " . count($dept_ratings));
    
} catch (PDOException $e) {
    error_log("Error fetching department ratings: " . $e->getMessage());
    
    // Fallback query if the above fails
    try {
        $dept_query = "SELECT * FROM department_ratings 
                       WHERE total_ratings > 0 
                       ORDER BY avg_overall DESC, total_ratings DESC 
                       LIMIT 10";
        $dept_stmt = $db->query($dept_query);
        $dept_ratings = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filter out non-operational departments
        $operational_depts = [];
        $op_dept_query = "SELECT dept_name FROM departments WHERE dept_type = 'operational'";
        $op_dept_stmt = $db->query($op_dept_query);
        $operational_names = $op_dept_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($dept_ratings as $rating) {
            if (in_array($rating['department'], $operational_names)) {
                $operational_depts[] = $rating;
            }
        }
        $dept_ratings = $operational_depts;
        error_log("After filtering non-operational: " . count($dept_ratings));
        
    } catch (PDOException $e2) {
        error_log("Fallback query also failed: " . $e2->getMessage());
    }
}

// Get public ratings - only from operational departments
$public_ratings = $cm->getPublicRatings(10);

// If no public ratings, try direct query with operational department filter
if (empty($public_ratings)) {
    $direct_query = "SELECT r.*, c.complaint_number, c.title, u.full_name,
                     c.department_assigned as department
                     FROM complaint_ratings r
                     JOIN complaints c ON r.complaint_id = c.complaint_id
                     JOIN users u ON r.user_id = u.user_id
                     WHERE r.is_public = 1
                     AND c.department_assigned IN (SELECT dept_name FROM departments WHERE dept_type = 'operational')
                     ORDER BY r.created_at DESC
                     LIMIT 10";
    $direct_stmt = $db->query($direct_query);
    $public_ratings = $direct_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get overall stats (only from operational departments for resolution time)
$stats = [];

// Total complaints resolved (all departments - fine to keep as is)
$query = "SELECT COUNT(*) as total FROM complaints WHERE status = 'resolved'";
$stmt = $db->query($query);
$stats['resolved'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Average resolution time - only resolved complaints
$query = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_time 
          FROM complaints WHERE status = 'resolved' AND resolved_at IS NOT NULL";
$stmt = $db->query($query);
$stats['avg_resolution_time'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_time'] ?? 0, 1);

// Total operational departments (not all departments)
$query = "SELECT COUNT(*) as total FROM departments WHERE dept_type = 'operational'";
$stmt = $db->query($query);
$stats['total_depts'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total academic departments for info
$academic_query = "SELECT COUNT(*) as total FROM departments WHERE dept_type = 'academic'";
$academic_stmt = $db->query($academic_query);
$academic_count = $academic_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get hot issues (most complained about categories)
$query = "SELECT cat.category_name, COUNT(*) as count
          FROM complaints c
          JOIN complaint_categories cat ON c.category_id = cat.category_id
          WHERE c.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
          GROUP BY c.category_id
          ORDER BY count DESC
          LIMIT 5";
$stmt = $db->query($query);
$hot_issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average satisfaction for the stat card
$avg_satisfaction = 'N/A';
if (!empty($dept_ratings)) {
    $total = 0;
    $count = 0;
    foreach ($dept_ratings as $dept) {
        if (isset($dept['avg_overall']) && $dept['avg_overall'] > 0) {
            $total += $dept['avg_overall'];
            $count++;
        }
    }
    if ($count > 0) {
        $avg_satisfaction = round($total / $count, 1) . '/5';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transparency Dashboard - Student Complaint System</title>
    <link rel="stylesheet" href="css/auth.css">
    <style>
        .info-note {
            background: #e3f2fd;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #1976d2;
            font-size: 13px;
        }
        .stat-card {
            text-align: center;
            padding: 20px;
        }
        .view-details-link {
            color: #1976d2;
            font-weight: 600;
        }
        .ranking-item:hover {
            background: #f5f9ff;
        }
        .dept-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .dept-modal-overlay.show { display: flex; }
        .dept-modal {
            background: #fff;
            border-radius: 12px;
            max-width: 700px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            padding: 25px;
            position: relative;
        }
        .dept-modal-close {
            position: absolute;
            top: 15px; right: 18px;
            font-size: 22px;
            cursor: pointer;
            background: none;
            border: none;
            color: #666;
        }
        .dept-modal h2 { margin-top: 0; }
        .dept-modal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin: 15px 0;
        }
        .dept-modal-stat {
            background: #f5f7fa;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .dept-modal-stat .num { font-size: 22px; font-weight: 700; color: #1976d2; }
        .dept-modal-stat .lbl { font-size: 12px; color: #666; margin-top: 4px; }
        .dept-modal-section { margin-top: 22px; }
        .dept-modal-section h3 { font-size: 15px; margin-bottom: 10px; }
        .dept-bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 13px; }
        .dept-bar-row .bar-label { width: 130px; flex-shrink: 0; }
        .dept-bar-track { flex: 1; background: #eee; border-radius: 6px; height: 10px; overflow: hidden; }
        .dept-bar-fill { height: 100%; background: #1976d2; }
        .dept-modal-loading { text-align: center; padding: 40px; color: #666; }
        .dept-feedback-item { background: #f9f9f9; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; font-size: 13px; }
    </style>
</head>
<body class="public-body">
    <div class="public-header">
        <h1>Transparency Dashboard</h1>
        <p>See how your institution is performing - Real-time statistics, department rankings, and student satisfaction scores.</p>
    </div>
    
    <div class="public-container">
       
        
        <!-- Overall Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['resolved']); ?></div>
                <div class="stat-label">Complaints Resolved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo formatDuration($stats['avg_resolution_time']); ?></div>
                <div class="stat-label">Average Resolution Time</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_depts']; ?></div>
                <div class="stat-label">Operational Departments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"> <?php echo $avg_satisfaction; ?></div>
                <div class="stat-label">Average Satisfaction</div>
            </div>
        </div>
        
        <div class="grid-2">
            <!-- Department Rankings (Only Operational) -->
            <div class="section">
                <h2 class="section-title">Department Rankings </h2>
                <?php if (empty($dept_ratings)): ?>
                    <div class="empty-state">
                        <p>No department ratings yet</p>
                        <p class="empty-state-text">Be the first to rate a resolved complaint!</p>
                    </div>
                <?php else: ?>
                    <ul class="ranking-list">
                        <?php 
                        $rank = 1;
                        foreach ($dept_ratings as $dept): 
                            $rank_class = 'rank-' . $rank;
                        ?>
                            <li class="ranking-item" onclick="showDepartmentDetails('<?php echo htmlspecialchars(addslashes($dept['department'] ?? ''), ENT_QUOTES); ?>')" style="cursor:pointer;" title="Click to see more details about this department">
                                <div class="rank <?php echo $rank_class; ?>"><?php echo $rank; ?></div>
                                <div class="dept-info">
                                    <div class="dept-name"><?php echo htmlspecialchars($dept['department'] ?? 'Unknown'); ?></div>
                                    <div class="rating-stars">
                                        <?php 
                                        $overall = round($dept['avg_overall'] ?? 0);
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $overall ? '★' : '☆';
                                        }
                                        ?>
                                    </div>
                                    <div class="rating-details">
                                        Speed: <?php echo number_format($dept['avg_speed'] ?? 0, 1); ?> | 
                                        Comm: <?php echo number_format($dept['avg_communication'] ?? 0, 1); ?> | 
                                        Effect: <?php echo number_format($dept['avg_effectiveness'] ?? 0, 1); ?>
                                    </div>
                                    <div class="rating-bar">
                                        <div class="rating-fill" style="width: <?php echo ($dept['avg_overall'] ?? 0) * 20; ?>%"></div>
                                    </div>
                                    <small class="small-text">Based on <?php echo $dept['total_ratings'] ?? 0; ?> ratings · <span class="view-details-link">View details →</span></small>
                                </div>
                            </li>
                        <?php 
                            $rank++;
                            if ($rank > 10) break;
                        endforeach; 
                        ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <!-- Hot Issues & Recent Ratings -->
            <div class="section">
                <h2 class="section-title"> Hot Issues (Last 30 Days)</h2>
                <?php if (empty($hot_issues)): ?>
                    <div class="empty-state">
                        <p>No data available for the last 30 days</p>
                    </div>
                <?php else: ?>
                    <div class="hot-issues">
                        <?php foreach ($hot_issues as $issue): ?>
                            <div class="issue-item">
                                <div class="flex-between">
                                    <span class="issue-name"><?php echo htmlspecialchars($issue['category_name']); ?></span>
                                    <span class="issue-count"><?php echo $issue['count']; ?> reports</span>
                                </div>
                                <div class="rating-bar rating-bar-margin">
                                    <div class="rating-fill" style="width: <?php echo min(100, ($issue['count'] / $hot_issues[0]['count']) * 100); ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <h2 class="recent-ratings-title">📝 Recent Public Ratings</h2>
                <?php if (empty($public_ratings)): ?>
                    <div class="empty-state">
                        <p>No public ratings yet</p>
                        <p class="empty-state-text">Ratings will appear here once students rate resolved complaints.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($public_ratings as $rating): ?>
                        <div class="testimonial">
                            <div class="testimonial-header">
                                <span class="testimonial-name"><?php echo htmlspecialchars($rating['full_name'] ?? 'Anonymous'); ?></span>
                                <span class="testimonial-rating">
                                    <?php 
                                    $avg = ((int)($rating['speed_rating'] ?? 0) + (int)($rating['communication_rating'] ?? 0) + (int)($rating['effectiveness_rating'] ?? 0)) / 3;
                                    echo number_format($avg, 1); ?> / 5
                                </span>
                            </div>
                            <div class="testimonial-stars">
                                <?php 
                                $avg_rounded = round($avg);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $avg_rounded ? '★' : '☆';
                                }
                                ?>
                            </div>
                            <?php if (!empty($rating['feedback'])): ?>
                                <p class="testimonial-text">"<?php echo htmlspecialchars($rating['feedback']); ?>"</p>
                            <?php endif; ?>
                            <div class="testimonial-footer">
                                <span><?php echo htmlspecialchars($rating['department'] ?? 'General'); ?></span>
                                <span><?php echo date('M j, Y', strtotime($rating['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- CTA -->
        <div class="cta-wrapper">
            <p class="cta-text">Have a complaint? We're here to help.</p>
            <a href="login.php" class="btn">Submit a Complaint</a>
        </div>
    </div>
    
    <div class="public-footer">
        <p>Student Complaint Support System - Transparency Dashboard</p>
        <p class="footer-text">Committed to transparency and continuous improvement</p>
        <a href="login.php" class="login-link-footer">Student/Staff Login →</a>
    </div>

    <!-- Department drill-down modal -->
    <div class="dept-modal-overlay" id="deptModalOverlay" onclick="if(event.target===this) closeDepartmentDetails()">
        <div class="dept-modal">
            <button class="dept-modal-close" onclick="closeDepartmentDetails()">&times;</button>
            <div id="deptModalBody"><div class="dept-modal-loading">Loading department insights...</div></div>
        </div>
    </div>

    <script>
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }

        // Mirrors the PHP formatDuration() helper, for values rendered client-side.
        function formatDuration(hours) {
            if (hours === null || hours === undefined || hours === '') return 'N/A';
            const totalMinutes = Math.round(parseFloat(hours) * 60);
            if (isNaN(totalMinutes)) return 'N/A';
            if (totalMinutes < 1) return '<1 min';
            if (totalMinutes < 60) return totalMinutes + ' min';
            const h = Math.floor(totalMinutes / 60);
            const m = totalMinutes % 60;
            return m > 0 ? `${h}h ${m}m` : `${h}h`;
        }

        async function showDepartmentDetails(department) {
            const overlay = document.getElementById('deptModalOverlay');
            const body = document.getElementById('deptModalBody');
            overlay.classList.add('show');
            body.innerHTML = '<div class="dept-modal-loading">Loading department insights...</div>';

            try {
                const res = await fetch('get_public_department_data.php?department=' + encodeURIComponent(department));
                const data = await res.json();

                if (!data.success) {
                    body.innerHTML = `<h2>${escapeHtml(department)}</h2><p>${escapeHtml(data.message || 'Could not load details.')}</p>`;
                    return;
                }

                const s = data.summary || {};
                const ratings = data.ratings || {};

                let priorityHtml = '';
                const priorityColors = { urgent: '#dc3545', high: '#fd7e14', medium: '#ffc107', low: '#28a745' };
                const totalForBar = (data.priority_breakdown || []).reduce((a, p) => a + parseInt(p.count), 0) || 1;
                (data.priority_breakdown || []).forEach(p => {
                    const pct = Math.round((p.count / totalForBar) * 100);
                    priorityHtml += `<div class="dept-bar-row">
                        <div class="bar-label">${escapeHtml((p.priority || '').toUpperCase())}</div>
                        <div class="dept-bar-track"><div class="dept-bar-fill" style="width:${pct}%; background:${priorityColors[p.priority] || '#1976d2'}"></div></div>
                        <div>${p.count}</div>
                    </div>`;
                });

                let categoryHtml = '';
                const maxCat = Math.max(1, ...(data.top_categories || []).map(c => parseInt(c.count)));
                (data.top_categories || []).forEach(c => {
                    const pct = Math.round((c.count / maxCat) * 100);
                    categoryHtml += `<div class="dept-bar-row">
                        <div class="bar-label">${escapeHtml(c.category_name)}</div>
                        <div class="dept-bar-track"><div class="dept-bar-fill" style="width:${pct}%"></div></div>
                        <div>${c.count}</div>
                    </div>`;
                });

                let trendHtml = '';
                const maxTrend = Math.max(1, ...(data.monthly_trend || []).map(t => parseInt(t.submitted)));
                (data.monthly_trend || []).forEach(t => {
                    const pct = Math.round((t.submitted / maxTrend) * 100);
                    trendHtml += `<div class="dept-bar-row">
                        <div class="bar-label">${escapeHtml(t.month_label)}</div>
                        <div class="dept-bar-track"><div class="dept-bar-fill" style="width:${pct}%"></div></div>
                        <div>${t.submitted} filed / ${t.resolved} resolved</div>
                    </div>`;
                });

                let feedbackHtml = '';
                (data.recent_feedback || []).forEach(f => {
                    const avg = ((parseInt(f.speed_rating) + parseInt(f.communication_rating) + parseInt(f.effectiveness_rating)) / 3).toFixed(1);
                    feedbackHtml += `<div class="dept-feedback-item">★ ${avg}/5 — "${escapeHtml(f.feedback)}"</div>`;
                });

                body.innerHTML = `
                    <h2>${escapeHtml(data.department.dept_name)}</h2>
                    ${data.department.description ? `<p style="color:#666;font-size:13px;">${escapeHtml(data.department.description)}</p>` : ''}
                    <div class="dept-modal-grid">
                        <div class="dept-modal-stat"><div class="num">${s.total_complaints || 0}</div><div class="lbl">Total Complaints</div></div>
                        <div class="dept-modal-stat"><div class="num">${s.resolution_rate || 0}%</div><div class="lbl">Resolution Rate</div></div>
                        <div class="dept-modal-stat"><div class="num">${formatDuration(s.avg_resolution_hours)}</div><div class="lbl">Avg. Resolution Time</div></div>
                        <div class="dept-modal-stat"><div class="num">${s.open_count || 0}</div><div class="lbl">Currently Open</div></div>
                        <div class="dept-modal-stat"><div class="num">${s.last_30_days || 0}</div><div class="lbl">Filed Last 30 Days</div></div>
                        <div class="dept-modal-stat"><div class="num">${ratings.avg_overall ? Number(ratings.avg_overall).toFixed(1) : 'N/A'}</div><div class="lbl">Avg. Satisfaction /5</div></div>
                    </div>

                    ${priorityHtml ? `<div class="dept-modal-section"><h3>Priority Breakdown</h3>${priorityHtml}</div>` : ''}
                    ${categoryHtml ? `<div class="dept-modal-section"><h3>Top Complaint Categories</h3>${categoryHtml}</div>` : ''}
                    ${trendHtml ? `<div class="dept-modal-section"><h3>Last 6 Months</h3>${trendHtml}</div>` : ''}
                    ${feedbackHtml ? `<div class="dept-modal-section"><h3>Recent Public Feedback</h3>${feedbackHtml}</div>` : ''}
                `;
            } catch (err) {
                body.innerHTML = `<h2>${escapeHtml(department)}</h2><p>Something went wrong loading details. Please try again.</p>`;
            }
        }

        function closeDepartmentDetails() {
            document.getElementById('deptModalOverlay').classList.remove('show');
        }
    </script>
</body>
</html>