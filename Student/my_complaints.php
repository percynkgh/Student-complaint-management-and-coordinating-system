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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints</title>
   <link rel="stylesheet" href="css/Student.css">
   
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Complaints</h1>
            <div>
                <a href="dashboard.php" class="back-btn">Dashboard</a>
                <a href="submit_complaint.php" class="new-btn">New Complaint</a>
            </div>
        </div>
        
        <?php
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
        
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label">Total Complaints</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $resolved; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $in_progress; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $pending; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        
        <?php if (empty($complaints)): ?>
            <div class="empty-state">
                <h3>No complaints yet</h3>
                <p>You haven't submitted any complaints. Click the "New Complaint" button to get started.</p>
            </div>
        <?php else: ?>
            <div class="complaint-grid">
                <?php foreach ($complaints as $complaint): ?>
                    <div class="complaint-card">
                        <div class="complaint-number">#<?php echo htmlspecialchars($complaint['complaint_number']); ?></div>
                        <div class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></div>
                        
                        <div class="complaint-meta">
                            <span class="badge status-<?php echo $complaint['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                            </span>
                            <span class="badge priority-<?php echo $complaint['priority']; ?>">
                                <?php echo ucfirst($complaint['priority']); ?> Priority
                            </span>
                            <span class="badge department">
                                <?php echo htmlspecialchars($complaint['department_assigned'] ?: 'Pending Assignment'); ?>
                            </span>
                        </div>
                        
                        <div class="complaint-meta">
                            <span class="date">Submitted: <?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></span>
                            <?php if ($complaint['upvotes'] > 0): ?>
                                <span class="upvote-count">❤️ <?php echo $complaint['upvotes']; ?> upvotes</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($complaint['status'] == 'resolved' || $complaint['status'] == 'rejected' || $complaint['status'] == 'closed'): ?>
    
<?php endif; ?>

<?php if ($complaint['status'] == 'resolved'): 
    // Check if already rated
    $rated_query = "SELECT rating_id FROM complaint_ratings WHERE complaint_id = ? AND user_id = ?";
    $rated_stmt = $db->prepare($rated_query);
    $rated_stmt->execute([$complaint['complaint_id'], $_SESSION['user_id']]);
    $already_rated = $rated_stmt->rowCount() > 0;
?>
    <div style="margin-top: 10px;">
        <?php if ($already_rated): ?>
            <span>✓ You rated this complaint</span>
        <?php else: ?>
            <a href="rate_complaint.php?id=<?php echo $complaint['complaint_id']; ?>" 
               class="rate-btn" 
            >
                ⭐ Rate Resolution
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>
                        <div class="complaint-footer">
                            <a href="view_complaint.php?id=<?php echo $complaint['complaint_id']; ?>" class="view-link">View Details →</a>
                            <?php if ($complaint['status'] == 'submitted'): ?>
                                <span>Awaiting review</span>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>