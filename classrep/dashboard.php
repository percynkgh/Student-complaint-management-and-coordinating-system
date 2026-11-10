<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('class_rep')) {
    header("Location:auth/unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$cm = new ComplaintManager();

$unread_count = $cm->getUnreadNotificationCount($_SESSION['user_id']);

// Get unread direct messages count (same as admin)
$msg_query = "SELECT COUNT(*) as total FROM direct_messages WHERE receiver_id = ? AND is_read = FALSE";
$msg_stmt = $db->prepare($msg_query);
$msg_stmt->execute([$_SESSION['user_id']]);
$unread_messages = $msg_stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT c.*, cat.category_name,
          (SELECT COUNT(*) FROM complaint_upvotes WHERE complaint_id = c.complaint_id) as upvotes,
          (SELECT COUNT(*) FROM batch_members WHERE complaint_id = c.complaint_id) as total_members
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          WHERE c.user_id = ?
          ORDER BY c.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($complaints);
$resolved = 0;
$in_progress = 0;
$batch_count = 0;

foreach ($complaints as $c) {
    if ($c['status'] == 'resolved') $resolved++;
    elseif ($c['status'] == 'in_progress') $in_progress++;
    if ($c['is_batch_complaint']) $batch_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Representative Dashboard</title>
    <link rel="stylesheet" href="../classrep/css/classrep.css">
    <script src="https://cdn.socket.io/4.5.0/socket.io.min.js"></script>
</head>
<body data-user-id="<?php echo $_SESSION['user_id']; ?>" data-user-name="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" data-user-role="<?php echo $_SESSION['role']; ?>">
<div class="container">
    <div class="header">
        <div>
            <h1>Class Representative Dashboard</h1>
            <small>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></small>
            <span class="role-badge">CLASS REP</span>
        </div>
        <div class="nav-links">
            <a href="direct_messages.php" class="msg-link"> Direct Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="notification-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <a href="notifications.php"> Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php"> Profile</a>
            <a href="../auth/logout.php"> Logout</a>
        </div>
    </div>

    <div class="stats-bar">
        <div class="stat-card"><div class="stat-number"><?php echo $total; ?></div><div class="stat-label">Total Complaints</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $batch_count; ?></div><div class="stat-label">Batch Complaints</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $resolved; ?></div><div class="stat-label">Resolved</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $in_progress; ?></div><div class="stat-label">In Progress</div></div>
    </div>

    <div class="action-buttons">
        <a href="batch_complaint.php" class="action-btn batch"><br><strong>Batch Complaint</strong><br><small>Submit for multiple students</small></a>
        <a href="submit_complaint.php" class="action-btn primary"><br><strong>Submit Complaint</strong><br><small>Individual complaint</small></a>
        <a href="my_complaints.php" class="action-btn"><br><strong>My Complaints</strong><br><small>View all complaints</small></a>
        <a href="notifications.php" class="action-btn"><br><strong>Notifications</strong><br><small><?php echo $unread_count; ?> unread</small></a>
    </div>

    <div class="complaints-section">
        <h2 class="section-title"> Recent Complaints <span class="batch-badge">Batch complaints highlighted</span></h2>
        <?php if (empty($complaints)): ?>
            <div class="empty-state"><p>You haven't submitted any complaints yet.</p>
            <div>
                <a href="batch_complaint.php" class="action-btn batch">Create Batch Complaint</a>
                <a href="submit_complaint.php" class="action-btn primary">Submit Individual Complaint</a>
            </div></div>
        <?php else: ?>
            <div class="complaint-grid">
                <?php foreach (array_slice($complaints, 0, 6) as $complaint): 
                    $is_batch = $complaint['is_batch_complaint'];
                ?>
                    <div class="complaint-card <?php echo $is_batch ? 'batch' : ''; ?>">
                        <?php if ($is_batch): ?><div class="batch-tag">BATCH</div><?php endif; ?>
                        <div class="complaint-number">#<?php echo htmlspecialchars($complaint['complaint_number']); ?></div>
                        <div class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></div>
                        <div class="complaint-meta">
                            <span class="badge status-<?php echo $complaint['status']; ?>"><?php echo ucfirst($complaint['status']); ?></span>
                            <span class="badge priority-<?php echo $complaint['priority']; ?>"><?php echo ucfirst($complaint['priority']); ?> Priority</span>
                            <span> <?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></span>
                        </div>
                        <?php if ($is_batch && $complaint['total_members'] > 0): ?>
                            <div class="batch-info"><strong> <?php echo $complaint['total_members']; ?> students affected</strong></div>
                        <?php endif; ?>
                        <div class="complaint-footer">
                            <a href="view_complaint.php?id=<?php echo $complaint['complaint_id']; ?>" class="view-link">View Details →</a>
                            <?php if ($complaint['status'] == 'resolved'): ?>
                                <?php
                                $rated = $db->prepare("SELECT rating_id FROM complaint_ratings WHERE complaint_id = ? AND user_id = ?");
                                $rated->execute([$complaint['complaint_id'], $_SESSION['user_id']]);
                                $already_rated = $rated->rowCount() > 0;
                                ?>
                                <?php if (!$already_rated): ?>
                                    <a href="rate_complaint.php?id=<?php echo $complaint['complaint_id']; ?>" class="rate-btn">⭐ Rate</a>
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

<script src="js/dashboard.js"></script>
</body>
</html>