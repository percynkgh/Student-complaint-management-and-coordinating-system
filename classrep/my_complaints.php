<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('class_rep')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();

// Get user's complaints
$query = "SELECT c.*, cat.category_name,
          (SELECT COUNT(*) FROM batch_members WHERE complaint_id = c.complaint_id) as total_members
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          WHERE c.user_id = ?
          ORDER BY c.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total = count($complaints);
$resolved = 0;
$in_progress = 0;
$batch_count = 0;

foreach ($complaints as $c) {
    if ($c['status'] == 'resolved') $resolved++;
    if ($c['status'] == 'in_progress') $in_progress++;
    if ($c['is_batch_complaint']) $batch_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints - Class Rep</title>
    <link rel="stylesheet" href="../classrep/css/classrep.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>My Complaints <span class="role-badge">CLASS REP</span></h1>
            <small>View all your submitted complaints</small>
        </div>
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>

    <!-- Statistics -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total; ?></div>
            <div class="stat-label">Total Complaints</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $batch_count; ?></div>
            <div class="stat-label">Batch Complaints</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $resolved; ?></div>
            <div class="stat-label">Resolved</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $in_progress; ?></div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>

    <!-- Complaints List -->
    <div class="complaints-list">
        <h2 class="section-title">All Complaints</h2>
        <?php if (empty($complaints)): ?>
            <div class="empty-state">
                <p>You haven't submitted any complaints yet.</p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <a href="batch_complaint.php" class="btn">Create Batch Complaint</a>
                    <a href="submit_complaint.php" class="btn">Submit Individual Complaint</a>
                </div>
            </div>
        <?php else: ?>
            <div class="complaint-grid">
                <?php foreach ($complaints as $complaint): 
                    $is_batch = $complaint['is_batch_complaint'];
                ?>
                    <div class="complaint-card <?php echo $is_batch ? 'batch' : ''; ?>">
                        <?php if ($is_batch): ?>
                            <div class="batch-tag">BATCH</div>
                        <?php endif; ?>
                        <div class="complaint-number">#<?php echo htmlspecialchars($complaint['complaint_number']); ?></div>
                        <div class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></div>
                        <div class="complaint-meta">
                            <span class="badge status-<?php echo $complaint['status']; ?>"><?php echo ucfirst($complaint['status']); ?></span>
                            <span class="badge priority-<?php echo $complaint['priority']; ?>"><?php echo ucfirst($complaint['priority']); ?> Priority</span>
                            <span>📅 <?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></span>
                        </div>
                        <?php if ($is_batch && $complaint['total_members'] > 0): ?>
                            <div class="batch-info">
                                <strong> <?php echo $complaint['total_members']; ?> students affected</strong>
                            </div>
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
                                    <span class="rated-badge">✓ Rated</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>