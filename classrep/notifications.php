<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('class_rep')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

if (isset($_GET['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE student_notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $stmt = $db->prepare("UPDATE student_notifications SET is_read = TRUE WHERE notification_id = ? AND user_id = ?");
    $stmt->execute([$_GET['id'], $user_id]);
    header("Location: notifications.php");
    exit();
}

$query = "SELECT n.*, c.complaint_number, c.title 
          FROM student_notifications n
          LEFT JOIN complaints c ON n.complaint_id = c.complaint_id
          WHERE n.user_id = ?
          ORDER BY n.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unread = $db->prepare("SELECT COUNT(*) as count FROM student_notifications WHERE user_id = ? AND is_read = FALSE");
$unread->execute([$user_id]);
$unread_count = $unread->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications - Class Rep</title>
    <link rel="stylesheet" href="../classrep/css/classrep.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>My Notifications <small><?php echo $unread_count; ?> unread</small></h1>
            <div class="role-badge">CLASS REP</div>
        </div>
        <div>
            <?php if ($unread_count > 0): ?>
                <a href="?mark_all_read=1" class="mark-read-btn">✓ Mark All Read</a>
            <?php endif; ?>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </div>

    <div class="notifications-list">
        <?php if (empty($notifications)): ?>
            <div class="empty-state"><p>No notifications yet</p><a href="dashboard.php" class="back-btn">Go to Dashboard</a></div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                    <div class="notification-icon"><?php echo $notif['is_read'] ? '📬' : '📫'; ?></div>
                    <div class="notification-content">
                        <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                        <div class="notification-meta">
                            <?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?>
                            <?php if ($notif['complaint_number']): ?>
                                • <a href="../student/view_complaint.php?id=<?php echo $notif['complaint_id']; ?>" class="complaint-link">View Complaint #<?php echo $notif['complaint_number']; ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$notif['is_read']): ?>
                        <div class="notification-actions"><a href="?mark_read=1&id=<?php echo $notif['notification_id']; ?>" class="mark-single-btn">✓ Mark Read</a></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>