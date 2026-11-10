<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('student')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$cm = new ComplaintManager();

// Ensure full_name is in session
if (!isset($_SESSION['full_name']) || empty($_SESSION['full_name'])) {
    $user_query = "SELECT full_name FROM users WHERE user_id = ?";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute([$_SESSION['user_id']]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['full_name'] = $user['full_name'] ?? 'Student';
}

// Get unread notification count
$unread_count = $cm->getUnreadNotificationCount($_SESSION['user_id']);

// Get user's complaints
$query = "SELECT c.*, cat.category_name,
          (SELECT COUNT(*) FROM complaint_upvotes WHERE complaint_id = c.complaint_id) as upvotes
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          WHERE c.user_id = ?
          ORDER BY c.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$total = count($complaints);
$resolved = 0;
$in_progress = 0;
$pending = 0;

foreach ($complaints as $c) {
    if ($c['status'] == 'resolved') $resolved++;
    elseif ($c['status'] == 'in_progress') $in_progress++;
    else $pending++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../Student/css/Student.css">
    <style>
        /* Notification Badge Styles */
        .nav-links {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .nav-links a {
            position: relative;
            color: #eeeeee;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .notification-badge {
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Student Dashboard</h1>
                <small>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></small>
            </div>
            <div class="nav-links">
                <a href="notifications.php">
                    Notifications 
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge">
                            <?php echo $unread_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="profile.php">👤 Profile</a>
                <a href="../auth/logout.php"> Logout</a>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label">Total Complaints</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $resolved; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $in_progress; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="submit_complaint.php" class="action-btn primary">
                 <strong>Submit Complaint</strong>
            </a>
            <a href="my_complaints.php" class="action-btn">
                 <strong>My Complaints</strong>
            </a>
            <a href="notifications.php" class="action-btn">
                 <strong>Notifications</strong>
                <?php if ($unread_count > 0): ?>
                    <span class="badge-inline" style="background: #dc3545; color: white; border-radius: 12px; padding: 2px 8px; font-size: 11px; margin-left: 8px;">
                        <?php echo $unread_count; ?> new
                    </span>
                <?php endif; ?>
            </a>
        </div>
        
        <!-- Recent Complaints -->
        <div class="complaints-section">
            <h2 class="section-title"> Recent Complaints</h2>
            
            <?php if (empty($complaints)): ?>
                <div class="empty-state">
                    <p>You haven't submitted any complaints yet.</p>
                    <a href="submit_complaint.php">Submit Your First Complaint</a>
                </div>
            <?php else: ?>
                <div class="complaint-grid">
                    <?php foreach (array_slice($complaints, 0, 6) as $complaint): ?>
                        <div class="complaint-card">
                            <div class="complaint-number">#<?php echo htmlspecialchars($complaint['complaint_number']); ?></div>
                            <div class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></div>
                            
                            <div class="complaint-meta">
                                <span class="badge status-<?php echo $complaint['status']; ?>">
                                    <?php echo ucfirst($complaint['status']); ?>
                                </span>
                                <span class="badge priority-<?php echo $complaint['priority']; ?>">
                                    <?php echo ucfirst($complaint['priority']); ?> Priority
                                </span>
                                <span> <?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></span>
                            </div>
                            
                            <div class="complaint-footer">
                                <a href="view_complaint.php?id=<?php echo $complaint['complaint_id']; ?>" class="view-link">
                                    View Details →
                                </a>
                                <?php if ($complaint['status'] == 'resolved'): ?>
                                    <?php
                                    $rated_query = "SELECT rating_id FROM complaint_ratings WHERE complaint_id = ? AND user_id = ?";
                                    $rated_stmt = $db->prepare($rated_query);
                                    $rated_stmt->execute([$complaint['complaint_id'], $_SESSION['user_id']]);
                                    $already_rated = $rated_stmt->rowCount() > 0;
                                    ?>
                                    <?php if (!$already_rated): ?>
                                        <a href="rate_complaint.php?id=<?php echo $complaint['complaint_id']; ?>" class="rate-btn">
                                            Rate
                                        </a>
                                    <?php else: ?>
                                        <span>✓ Rated</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($complaints) > 6): ?>
                    <div class="view-all-link">
                        <a href="my_complaints.php">View All Complaints →</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>