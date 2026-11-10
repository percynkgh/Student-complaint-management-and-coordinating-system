<?php
require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('staff')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$cm = new ComplaintManager();

// Get staff's department from session or database
$query = "SELECT department FROM users WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$department = $user['department'] ?? 'Unknown';

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get complaints assigned to this staff member (not the whole department -
// department-wide visibility and assignment now belongs to the Supervisor)
$query = "SELECT c.*, cat.category_name, u.full_name as student_name, u.email as student_email,
          a.full_name as assigned_to_name,
          (SELECT COUNT(*) FROM batch_members WHERE complaint_id = c.complaint_id) as affected_count,
          DATEDIFF(NOW(), c.created_at) as days_old
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN users u ON c.user_id = u.user_id
          LEFT JOIN users a ON c.assigned_to = a.user_id
          WHERE c.department_assigned = ? AND c.assigned_to = ?";

// Apply filter
if ($filter == 'urgent') {
    $query .= " AND LOWER(TRIM(c.priority)) = 'urgent'";
} elseif ($filter == 'high') {
    $query .= " AND LOWER(TRIM(c.priority)) = 'high'";
} elseif ($filter == 'medium') {
    $query .= " AND LOWER(TRIM(c.priority)) = 'medium'";
} elseif ($filter == 'low') {
    $query .= " AND LOWER(TRIM(c.priority)) = 'low'";
} elseif ($filter == 'submitted') {
    $query .= " AND LOWER(TRIM(c.status)) IN ('submitted', 'under_review')";
} elseif ($filter == 'in_progress') {
    $query .= " AND LOWER(TRIM(c.status)) = 'in_progress'";
} elseif ($filter == 'resolved') {
    $query .= " AND LOWER(TRIM(c.status)) = 'resolved'";
} elseif ($filter == 'overdue') {
    $query .= " AND DATEDIFF(NOW(), c.created_at) > 7 AND LOWER(TRIM(c.status)) != 'resolved'";
} elseif ($filter == 'pending') {
    $query .= " AND LOWER(TRIM(c.status)) != 'resolved'";
} elseif ($filter == 'awaiting_confirmation') {
    $query .= " AND c.assignment_status = 'awaiting_confirmation'";
}

$query .= " ORDER BY 
            CASE 
                WHEN LOWER(TRIM(c.priority)) = 'urgent' THEN 1
                WHEN LOWER(TRIM(c.priority)) = 'high' THEN 2
                WHEN LOWER(TRIM(c.priority)) = 'medium' THEN 3
                WHEN LOWER(TRIM(c.priority)) = 'low' THEN 4
                ELSE 5
            END,
            c.created_at ASC";

$stmt = $db->prepare($query);
$stmt->execute([$department, $_SESSION['user_id']]);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$all_complaints_query = "SELECT c.*, DATEDIFF(NOW(), c.created_at) as days_old FROM complaints c WHERE c.department_assigned = ? AND c.assigned_to = ?";
$all_stmt = $db->prepare($all_complaints_query);
$all_stmt->execute([$department, $_SESSION['user_id']]);
$all_complaints = $all_stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'total' => count($all_complaints),
    'submitted' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'urgent' => 0,
    'high' => 0,
    'medium' => 0,
    'low' => 0,
    'overdue' => 0,
    'pending' => 0,
    'awaiting_confirmation' => 0
];

foreach ($all_complaints as $c) {
    $status = strtolower(trim($c['status'] ?? ''));
    if ($status == 'submitted' || $status == 'under_review') $stats['submitted']++;
    if ($status == 'in_progress') $stats['in_progress']++;
    if ($status == 'resolved') $stats['resolved']++;
    if ($status != 'resolved') $stats['pending']++;
    
    $priority = strtolower(trim($c['priority'] ?? ''));
    if ($priority == 'urgent') $stats['urgent']++;
    elseif ($priority == 'high') $stats['high']++;
    elseif ($priority == 'medium') $stats['medium']++;
    elseif ($priority == 'low') $stats['low']++;
    
    if ($c['days_old'] > 7 && $status != 'resolved') $stats['overdue']++;

    $assignment_status = $c['assignment_status'] ?? 'unassigned';
    if ($assignment_status == 'awaiting_confirmation') $stats['awaiting_confirmation']++;
}

// Get status templates
$templates = [];
try {
    $query = "SELECT * FROM status_templates WHERE is_active = TRUE ORDER BY sort_order";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $templates = [];
}

// Handle status update - SIMPLE VERSION (no email, no AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $complaint_id = Security::sanitize($_POST['complaint_id']);
    $new_status = Security::sanitize($_POST['status']);
    $update_message = Security::sanitize($_POST['update_message'] ?? 'Status updated');

    // Staff can move a complaint through working states, but final outcomes
    // (resolved / rejected) are reserved for the department Supervisor, who
    // confirms resolution (with evidence) before the student is notified.
    $allowed_staff_statuses = ['under_review', 'in_progress'];
    if (!in_array($new_status, $allowed_staff_statuses)) {
        header("Location: dashboard.php?msg=status_not_allowed" . ($filter != 'all' ? "&filter=" . $filter : ""));
        exit();
    }
    
    try {
        // Confirm this complaint is actually assigned to this staff member
        $own_check = $db->prepare("SELECT complaint_id FROM complaints WHERE complaint_id = ? AND assigned_to = ?");
        $own_check->execute([$complaint_id, $_SESSION['user_id']]);
        if (!$own_check->fetch()) {
            header("Location: dashboard.php?msg=not_authorized");
            exit();
        }

        // Update complaint
        $query = "UPDATE complaints SET status = ? WHERE complaint_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$new_status, $complaint_id]);
        
        // Add to timeline
        $cm->addTimelineEntry($complaint_id, $new_status, $update_message, $_SESSION['user_id']);
        
        // Send in-app notification (FAST - no email)
        $query = "SELECT u.user_id, u.full_name, c.complaint_number, c.is_batch_complaint 
                  FROM complaints c
                  JOIN users u ON c.user_id = u.user_id
                  WHERE c.complaint_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$complaint_id]);
        $complaint_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($complaint_info) {
            $notification_message = "Your complaint #{$complaint_info['complaint_number']} status changed to: " . ucfirst($new_status);
            if (!empty($update_message)) {
                $notification_message .= " - " . $update_message;
            }
            $cm->sendStudentNotification($complaint_info['user_id'], $complaint_id, $notification_message);
            
            if ($complaint_info['is_batch_complaint']) {
                $cm->sendBulkStudentNotifications($complaint_id, $notification_message, $complaint_info['user_id']);
            }
        }
        
        // Redirect back with success message
        $redirect_url = "dashboard.php?msg=status_updated";
        if ($filter != 'all') {
            $redirect_url .= "&filter=" . $filter;
        }
        header("Location: " . $redirect_url);
        exit();
        
    } catch (Exception $e) {
        error_log("Status update error: " . $e->getMessage());
        $redirect_url = "dashboard.php?msg=error";
        if ($filter != 'all') {
            $redirect_url .= "&filter=" . $filter;
        }
        header("Location: " . $redirect_url);
        exit();
    }
}

// Helper functions
function getPriorityClass($priority) {
    $priority = strtolower(trim($priority ?? 'medium'));
    switch($priority) {
        case 'urgent': return 'priority-urgent';
        case 'high': return 'priority-high';
        case 'medium': return 'priority-medium';
        case 'low': return 'priority-low';
        default: return 'priority-medium';
    }
}

function getStatusClass($status) {
    $status = strtolower(trim($status ?? ''));
    switch($status) {
        case 'submitted': return 'status-submitted';
        case 'under_review': return 'status-review';
        case 'in_progress': return 'status-progress';
        case 'resolved': return 'status-resolved';
        case 'rejected': return 'status-rejected';
        default: return 'status-default';
    }
}

function isFilterActive($filter_name) {
    global $filter;
    return $filter == $filter_name;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - <?php echo htmlspecialchars($department); ?></title>
    <link rel="stylesheet" href="css/staff.css">
    <style>
        /* INLINE FIX FOR NOTIFICATION BADGE */
        .header-actions .back-btn {
            position: relative;
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            margin: 0;
        }
        
        .unread-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: bold;
            min-width: 18px;
            text-align: center;
            display: inline-block;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .logout-btn:hover {
            background: #dc3545;
            transform: translateY(-2px);
        }
        
        .header-actions .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Staff Dashboard</h1>
                <p>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                <span class="department-badge"><?php echo htmlspecialchars($department); ?> Department</span>
            </div>
            <div class="header-actions">
                <a href="notifications.php" class="back-btn" style="position: relative;">
                    Notifications
                    <?php
                    $unread_notifs = $cm->getUnreadNotificationCount($_SESSION['user_id']);
                    if ($unread_notifs > 0): 
                    ?>
                        <span class="notification-badge">
                            <?php echo $unread_notifs; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="direct_messages.php" class="back-btn">
                    Direct Messages
                    <?php
                    $unread_direct = $cm->getDirectUnreadCount($_SESSION['user_id']);
                    if ($unread_direct > 0): 
                    ?>
                        <span class="unread-badge">
                            <?php echo $unread_direct; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="profile.php" class="back-btn">Profile</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'status_updated'): ?>
                <div class="message-alert message-success">
                     Complaint status updated successfully!
                </div>
            <?php elseif ($_GET['msg'] == 'error'): ?>
                <div class="message-alert message-error">
                     An error occurred. Please try again.
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card <?php echo isFilterActive('all') ? 'active-filter' : ''; ?>" onclick="window.location.href='?filter=all'">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Assigned</div>
            </div>
            <div class="stat-card <?php echo isFilterActive('submitted') ? 'active-filter' : ''; ?>" onclick="window.location.href='?filter=submitted'">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['submitted']; ?></div>
                <div class="stat-label">Awaiting Review</div>
            </div>
            <div class="stat-card <?php echo isFilterActive('in_progress') ? 'active-filter' : ''; ?>" onclick="window.location.href='?filter=in_progress'">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card <?php echo isFilterActive('resolved') ? 'active-filter' : ''; ?>" onclick="window.location.href='?filter=resolved'">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['resolved']; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            <div class="stat-card urgent <?php echo isFilterActive('urgent') ? 'active-filter' : ''; ?>" onclick="window.location.href='?filter=urgent'">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['urgent']; ?></div>
                <div class="stat-label">Urgent Cases</div>
            </div>
            <div class="stat-card high <?php echo isFilterActive('overdue') ? 'active-filter' : ''; ?>" onclick="window.location.href='?filter=overdue'">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['overdue']; ?></div>
                <div class="stat-label">Overdue (>7 days)</div>
            </div>
            <div class="stat-card <?php echo isFilterActive('pending') ? 'active-filter' : ''; ?>" onclick="window.location.href='?filter=pending'">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending (Not Resolved)</div>
            </div>
            <div class="stat-card <?php echo isFilterActive('awaiting_confirmation') ? 'active-filter' : ''; ?>" onclick="window.location.href='?filter=awaiting_confirmation'">
                <div class="stat-icon"></div>
                <div class="stat-number"><?php echo $stats['awaiting_confirmation']; ?></div>
                <div class="stat-label">Awaiting Supervisor Confirmation</div>
            </div>
        </div>
        
        <!-- Complaints List -->
        <div class="complaints-section">
            <div class="section-title">
                <span>
                    <?php 
                    if ($filter == 'all') echo "All Assigned Complaints";
                    elseif ($filter == 'submitted') echo "Awaiting Review Complaints";
                    elseif ($filter == 'in_progress') echo "In Progress Complaints";
                    elseif ($filter == 'resolved') echo "Resolved Complaints";
                    elseif ($filter == 'urgent') echo "Urgent Complaints";
                    elseif ($filter == 'overdue') echo "Overdue Complaints (>7 days)";
                    elseif ($filter == 'pending') echo "Pending Complaints (Not Resolved)";
                    elseif ($filter == 'awaiting_confirmation') echo "Awaiting Supervisor Confirmation";
                    else echo "Complaints";
                    ?>
                    <span class="filter-indicator"><?php echo count($complaints); ?> items</span>
                </span>
                <?php if ($filter != 'all'): ?>
                    <a href="?filter=all" class="clear-filter">✖ Clear Filter</a>
                <?php endif; ?>
            </div>
            
            <?php if (empty($complaints)): ?>
                <div class="empty-state">
                    <p> No <?php echo $filter != 'all' ? $filter : ''; ?> complaints found.</p>
                    <?php if ($filter != 'all'): ?>
                        <a href="?filter=all" class="empty-state-link">View All Complaints</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($complaints as $complaint): 
                    $priority_class = getPriorityClass($complaint['priority'] ?? 'medium');
                    $priority_display = strtoupper(trim($complaint['priority'] ?? 'MEDIUM'));
                ?>
                    <div class="complaint-card <?php echo strtolower(trim($complaint['priority'] ?? 'medium')); ?>">
                        <div class="complaint-header">
                            <span class="complaint-number">#<?php echo htmlspecialchars($complaint['complaint_number']); ?></span>
                            <div class="badge-container">
                                <span class="priority-badge <?php echo $priority_class; ?>">
                                    <?php echo $priority_display; ?>
                                </span>
                                <span class="status-badge status-<?php echo strtolower($complaint['status'] ?? ''); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $complaint['status'] ?? 'submitted')); ?>
                                </span>
                                <?php if (($complaint['days_old'] ?? 0) > 7): ?>
                                    <span class="days-old"> <?php echo $complaint['days_old']; ?> days old</span>
                                <?php endif; ?>
                                <?php 
                                $a_status = $complaint['assignment_status'] ?? 'unassigned';
                                $a_colors = ['unassigned' => '#856404;background:#fff3cd', 'assigned' => '#004085;background:#cce5ff', 'awaiting_confirmation' => '#7a3e00;background:#ffe3c4', 'resolved' => '#155724;background:#d4edda'];
                                $a_style = $a_colors[$a_status] ?? $a_colors['unassigned'];
                                ?>
                                <span class="status-badge" style="color:<?php echo explode(';', $a_style)[0]; ?>;<?php echo explode(';', $a_style)[1] ?? ''; ?>">
                                    <?php 
                                    if ($a_status === 'assigned') echo 'Working On It';
                                    elseif ($a_status === 'awaiting_confirmation') echo 'Awaiting Supervisor Confirmation';
                                    elseif ($a_status === 'resolved') echo 'Confirmed Resolved';
                                    else echo ucfirst($a_status);
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="complaint-title">
                            <?php echo htmlspecialchars($complaint['title'] ?? 'Untitled'); ?>
                        </div>
                        
                        <div class="complaint-meta">
                            <span class="meta-item"> <?php echo htmlspecialchars($complaint['student_name'] ?? 'Unknown'); ?></span>
                            <span class="meta-item"> <?php echo date('M d, Y', strtotime($complaint['created_at'] ?? 'now')); ?></span>
                            <span class="meta-item"> <?php echo htmlspecialchars($complaint['category_name'] ?? 'Uncategorized'); ?></span>
                            <?php if (($complaint['affected_count'] ?? 0) > 1): ?>
                                <span class="meta-item"> Batch (<?php echo $complaint['affected_count']; ?> students)</span>
                            <?php endif; ?>
                            <span class="meta-item"> <?php echo $complaint['days_old'] ?? 0; ?> day(s) old</span>
                        </div>
                        
                        <div class="complaint-description">
                            <?php echo htmlspecialchars(substr($complaint['description'] ?? '', 0, 300)); ?>
                            <?php if (strlen($complaint['description'] ?? '') > 300): ?>...<?php endif; ?>
                        </div>
                        
                        <div class="update-form">
                            <h4>Update Status</h4>
                            
                            <?php if (!empty($templates)): ?>
                                <div class="templates-section">
                                    <label>Quick Templates:</label>
                                    <div class="templates-list">
                                        <?php foreach ($templates as $template): ?>
                                            <div class="template-item" 
                                                 onclick="document.getElementById('status_<?php echo $complaint['complaint_id']; ?>').value='<?php echo $template['status']; ?>';
                                                          document.getElementById('message_<?php echo $complaint['complaint_id']; ?>').value='<?php echo addslashes($template['description']); ?>'">
                                                <div class="template-title"><?php echo htmlspecialchars($template['title']); ?></div>
                                                <div class="template-desc"><?php echo htmlspecialchars(substr($template['description'], 0, 50)); ?>...</div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="complaint_id" value="<?php echo $complaint['complaint_id']; ?>">
                                
                                <div class="form-group">
                                    <label>Change Status:</label>
                                    <select name="status" id="status_<?php echo $complaint['complaint_id']; ?>" required>
                                        <option value="">Select New Status</option>
                                        <option value="under_review" <?php echo ($complaint['status'] ?? '') == 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                        <option value="in_progress" <?php echo ($complaint['status'] ?? '') == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    </select>
                                    <div class="field-hint">To mark your work resolved, use "Mark as Resolved" on the complaint's detail page &mdash; your supervisor confirms it before the student is notified.</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Update Message (optional):</label>
                                    <textarea name="update_message" id="message_<?php echo $complaint['complaint_id']; ?>" rows="2" placeholder="Add a message about this update..."></textarea>
                                </div>
                                
                                <div class="form-button-group">
                                    <button type="submit" name="update_status" class="btn btn-update">Update Status</button>
                                    <a href="view_complaint.php?id=<?php echo $complaint['complaint_id']; ?>" class="btn btn-view">View Details</a>
                                    <?php if (in_array($complaint['status'], ['resolved', 'rejected', 'closed'])): ?>
                                        
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
  
    <script src="js/dashboard.js"></script>
</body>
</html>