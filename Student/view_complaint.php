<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();

$db = (new Database())->getConnection();
$cm = new ComplaintManager();

// Get complaint ID from URL
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$complaint_id) {
    header("Location: my_complaints.php");
    exit();
}

// Fetch complaint details
$query = "SELECT c.*, cat.category_name, u.full_name as student_name, u.student_id,
          (SELECT COUNT(*) FROM complaint_upvotes WHERE complaint_id = c.complaint_id) as upvote_count
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN users u ON c.user_id = u.user_id
          WHERE c.complaint_id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$complaint_id]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    header("Location: my_complaints.php");
    exit();
}

// Check if user has permission to view this complaint
if ($complaint['user_id'] != $_SESSION['user_id'] && !in_array($_SESSION['role'], ['admin', 'staff', 'dean', 'principal', 'class_rep'])) {
    header("Location: /SCCS/auth/unauthorized.php");
    exit();
}

// Calculate ETA for this complaint
$eta_data = $cm->calculateETA($complaint_id);

// Get timeline
$query = "SELECT t.*, u.full_name as updated_by_name 
          FROM complaint_timeline t
          LEFT JOIN users u ON t.updated_by = u.user_id
          WHERE t.complaint_id = ?
          ORDER BY t.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute([$complaint_id]);
$timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attachments
$query = "SELECT * FROM complaint_attachments WHERE complaint_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$complaint_id]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get resolution evidence (before/after photos uploaded by department staff)
$evidence = [];
try {
    $query = "SELECT * FROM complaint_evidence WHERE complaint_id = ? ORDER BY created_at ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$complaint_id]);
    $evidence = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $evidence = [];
}
$before_evidence = array_values(array_filter($evidence, fn($e) => $e['evidence_type'] === 'before'));
$after_evidence = array_values(array_filter($evidence, fn($e) => $e['evidence_type'] === 'after'));

// Get similar resolved complaints - ADDED THIS SECTION
$similar_cases = [];
if ($complaint['category_id']) {
    $query = "SELECT c.*, cat.category_name,
              TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) as resolution_hours
              FROM complaints c
              LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
              WHERE c.category_id = ? 
              AND c.status = 'resolved'
              AND c.complaint_id != ?
              AND c.resolved_at IS NOT NULL
              ORDER BY c.created_at DESC
              LIMIT 3";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$complaint['category_id'], $complaint_id]);
    $similar_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle upvote
if (isset($_POST['upvote'])) {
    try {
        $query = "INSERT INTO complaint_upvotes (complaint_id, user_id) VALUES (?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$complaint_id, $_SESSION['user_id']]);
        
        // Update upvote count in complaints table
        $query = "UPDATE complaints SET upvote_count = upvote_count + 1 WHERE complaint_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$complaint_id]);

        // Recalculate priority based on upvote thresholds
        // > 20 => urgent, > 10 => high, else keep existing priority
        $query = "SELECT upvote_count, priority FROM complaints WHERE complaint_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$complaint_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)($row['upvote_count'] ?? 0);
        $current_priority = strtolower(trim($row['priority'] ?? ''));
        $new_priority = $current_priority;

        if ($count > 20) {
            $new_priority = 'urgent';
        } elseif ($count > 10) {
            $new_priority = 'high';
        }

        // Always sync affected_count to the latest upvote_count
        $updateAffected = "UPDATE complaints SET affected_count = ? WHERE complaint_id = ?";
        $u2 = $db->prepare($updateAffected);
        $u2->execute([$count, $complaint_id]);

        // Update priority only when thresholds are crossed
        if ($new_priority !== $current_priority) {
            $updatePriority = "UPDATE complaints SET priority = ? WHERE complaint_id = ?";
            $u3 = $db->prepare($updatePriority);
            $u3->execute([$new_priority, $complaint_id]);
        }

        header("Location: view_complaint.php?id=" . $complaint_id . "&upvoted=1");
        exit();


    } catch (PDOException $e) {
        // Already upvoted
        $error = "You have already upvoted this complaint";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Details - #<?php echo htmlspecialchars($complaint['complaint_number']); ?></title>
   <link rel="stylesheet" href="css/Student.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Complaint Details</h1>
                <small>View full details and status history</small>
            </div>
            <div>
                <?php if ($_SESSION['role'] == 'student'): ?>
                    <a href="my_complaints.php" class="back-btn">← Back to My Complaints</a>
                <?php elseif ($_SESSION['role'] == 'staff'): ?>
                    <a href="../staff/dashboard.php" class="back-btn">← Back to Dashboard</a>
                <?php elseif ($_SESSION['role'] == 'class_rep'): ?>
                    <a href="../classrep/my_complaints.php" class="back-btn">← Back to My Complaints</a>
                <?php else: ?>
                    <a href="javascript:history.back()" class="back-btn">← Go Back</a>
                <?php endif; ?>
                
                <?php if (in_array($complaint['status'], ['resolved', 'rejected', 'closed'])): ?>
                   
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_GET['upvoted'])): ?>
            <div >
                ✓ Complaint upvoted successfully!
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div >
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- ETA Display Section - Only show for unresolved complaints -->
        <?php if ($eta_data && isset($eta_data['estimated_hours']) && $complaint['status'] != 'resolved' && $complaint['status'] != 'closed' && $complaint['status'] != 'rejected'): ?>
        <div class="eta-section">
            <div class="eta-header">
                <h3>
                    <span> Estimated Resolution Time</span>
                    <?php if ($eta_data['confidence'] > 0.7): ?>
                        <span class="confidence-badge confidence-high">High Confidence</span>
                    <?php elseif ($eta_data['confidence'] > 0.4): ?>
                        <span class="confidence-badge confidence-medium">Medium Confidence</span>
                    <?php else: ?>
                        <span class="confidence-badge confidence-low">Low Confidence</span>
                    <?php endif; ?>
                </h3>
                <span >
                    Based on <?php echo $eta_data['sample_size'] ?? 0; ?> similar cases
                </span>
            </div>
            
            <div class="eta-grid">
                <?php if (isset($eta_data['estimated_date'])): ?>
                <div class="eta-item">
                    <div class="eta-label">Estimated Resolution</div>
                    <div class="eta-value">
                        <?php echo date('M j, Y', strtotime($eta_data['estimated_date'])); ?>
                    </div>
                    <div class="eta-sub">
                        by <?php echo date('g:i A', strtotime($eta_data['estimated_date'])); ?>
                    </div>
                </div>
                
                <div class="eta-item">
                    <div class="eta-label">Time Remaining</div>
                    <?php
                    $now = new DateTime();
                    $eta = new DateTime($eta_data['estimated_date']);
                    $interval = $now->diff($eta);
                    
                    if ($now < $eta):
                    ?>
                        <div class="eta-value">
                            <?php 
                            if ($interval->days > 0) echo $interval->days . 'd ';
                            if ($interval->h > 0) echo $interval->h . 'h ';
                            if ($interval->i > 0) echo $interval->i . 'm';
                            if ($interval->days == 0 && $interval->h == 0 && $interval->i == 0) echo 'Less than 1 minute';
                            ?>
                        </div>
                        <div class="eta-sub">
                            <?php echo round($eta_data['estimated_hours'], 1); ?> hours total estimate
                        </div>
                    <?php else: ?>
                        <div class="eta-value" >Overdue</div>
                        <div class="eta-sub">
                            <?php echo $interval->days; ?>d <?php echo $interval->h; ?>h overdue
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="eta-item">
                    <div class="eta-label">Confidence Score</div>
                    <div style="margin: 10px 0;">
                        <div class="confidence-bar">
                            <div class="confidence-fill" style="width: <?php echo ($eta_data['confidence'] ?? 0) * 100; ?>%;"></div>
                        </div>
                    </div>
                    <div class="eta-sub" style="display: flex; justify-content: space-between;">
                        <span><?php echo round(($eta_data['confidence'] ?? 0) * 100); ?>% confident</span>
                        <span>Sample: <?php echo $eta_data['sample_size'] ?? 0; ?> cases</span>
                    </div>
                </div>
            </div>
            
            <?php if (isset($eta_data['based_on']) && $eta_data['based_on'] == 'department'): ?>
                <div class="eta-note">
                     Estimate based on department averages. More accurate predictions will be available as more similar cases are resolved.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="complaint-card">
            <div class="status-bar">
                <span class="complaint-number">#<?php echo htmlspecialchars($complaint['complaint_number']); ?></span>
                <div>
                    <span class="badge badge-<?php echo $complaint['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                    </span>
                    <span class="priority-badge priority-<?php echo $complaint['priority']; ?>">
                        <?php echo strtoupper($complaint['priority']); ?> PRIORITY
                    </span>
                </div>
            </div>
            
            <h1 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h1>
            
            <div class="meta-grid">
                <div class="meta-item">
                    <span class="meta-label">Submitted By</span>
                    <span class="meta-value"><?php echo htmlspecialchars($complaint['student_name']); ?> (<?php echo htmlspecialchars($complaint['student_id']); ?>)</span>
                </div>
                
                <div class="meta-item">
                    <span class="meta-label">Category</span>
                    <span class="meta-value"><?php echo htmlspecialchars($complaint['category_name']); ?></span>
                </div>
                
                <div class="meta-item">
                    <span class="meta-label">Department</span>
                    <span class="meta-value"><?php echo htmlspecialchars($complaint['department_assigned'] ?: 'Not assigned'); ?></span>
                </div>
                
                <div class="meta-item">
                    <span class="meta-label">Submitted On</span>
                    <span class="meta-value"><?php echo date('F j, Y \a\t g:i A', strtotime($complaint['created_at'])); ?></span>
                </div>
                
                <?php if ($complaint['resolved_at']): ?>
                <div class="meta-item">
                    <span class="meta-label">Resolved On</span>
                    <span class="meta-value"><?php echo date('F j, Y \a\t g:i A', strtotime($complaint['resolved_at'])); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($complaint['location_building'] || $complaint['location_room']): ?>
                <div class="meta-item">
                    <span class="meta-label">Location</span>
                    <span class="meta-value">
                        <?php 
                        $location = [];
                        if ($complaint['location_building']) $location[] = $complaint['location_building'];
                        if ($complaint['location_room']) $location[] = $complaint['location_room'];
                        echo htmlspecialchars(implode(' - ', $location));
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <h3 class="section-title">Description</h3>
            <div class="description-box">
                <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
            </div>
            
            <?php if (!empty($attachments)): ?>
                <h3 class="section-title">Attachments</h3>
                <div class="attachment-list">
                    <?php foreach ($attachments as $file): ?>
                        <div class="attachment-item">
                            <div class="attachment-icon">📎</div>
                            <div class="attachment-info">
                                <div class="attachment-name"><?php echo htmlspecialchars($file['file_name']); ?></div>
                                <div class="attachment-size"><?php echo round($file['file_size'] / 1024, 1); ?> KB</div>
                            </div>
                            <a href="../<?php echo htmlspecialchars($file['file_path']); ?>" class="attachment-link" target="_blank">📥</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($before_evidence) || !empty($after_evidence)): ?>
                <h3 class="section-title">Resolution Evidence</h3>
                <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom: 15px;">
                    <?php if (!empty($before_evidence)): ?>
                        <div style="flex:1; min-width:200px;">
                            <div style="font-weight:700; font-size:12px; text-transform:uppercase; color:#666; margin-bottom:8px;">Before</div>
                            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(120px,1fr)); gap:8px;">
                                <?php foreach ($before_evidence as $e): ?>
                                    <a href="../<?php echo htmlspecialchars($e['file_path']); ?>" target="_blank">
                                        <img src="../<?php echo htmlspecialchars($e['file_path']); ?>" style="width:100%; height:100px; object-fit:cover; border-radius:8px;" alt="Before">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($after_evidence)): ?>
                        <div style="flex:1; min-width:200px;">
                            <div style="font-weight:700; font-size:12px; text-transform:uppercase; color:#666; margin-bottom:8px;">After</div>
                            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(120px,1fr)); gap:8px;">
                                <?php foreach ($after_evidence as $e): ?>
                                    <a href="../<?php echo htmlspecialchars($e['file_path']); ?>" target="_blank">
                                        <img src="../<?php echo htmlspecialchars($e['file_path']); ?>" style="width:100%; height:100px; object-fit:cover; border-radius:8px;" alt="After">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Upvote Section - Only show for students who didn't create this complaint -->
            <?php if ($_SESSION['role'] == 'student' && $complaint['user_id'] != $_SESSION['user_id']): ?>
                <div class="upvote-section">
                    <div class="upvote-count"><?php echo $complaint['upvote_count']; ?> upvotes</div>
                    <form method="POST">
                        <button type="submit" name="upvote" class="upvote-button"> Upvote this Complaint</button>
                    </form>
                </div>
            <?php elseif ($_SESSION['role'] == 'student' && $complaint['user_id'] == $_SESSION['user_id']): ?>
                <div class="upvote-section">
                    <div class="upvote-count"><?php echo $complaint['upvote_count']; ?> upvotes from other students</div>
                </div>
            <?php endif; ?>
            
            <h3 class="section-title">Status Timeline</h3>
            <div class="timeline">
                <?php if (empty($timeline)): ?>
                    <div >
                        No timeline entries yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($timeline as $entry): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo date('M j, Y \a\t g:i A', strtotime($entry['created_at'])); ?></div>
                            <div class="timeline-status"> Status changed to: <strong><?php echo ucfirst(str_replace('_', ' ', $entry['status'])); ?></strong></div>
                            <?php if ($entry['description']): ?>
                                <div class="timeline-description"><?php echo htmlspecialchars($entry['description']); ?></div>
                            <?php endif; ?>
                            <div class="timeline-user">
                                <?php echo $entry['updated_by_name'] ? 'by ' . htmlspecialchars($entry['updated_by_name']) : 'System auto-update'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Original submission -->
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M j, Y \a\t g:i A', strtotime($complaint['created_at'])); ?></div>
                        <div class="timeline-status"> Complaint submitted</div>
                        <div class="timeline-user">by <?php echo htmlspecialchars($complaint['student_name']); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Similar Cases Section -->
        <?php if (!empty($similar_cases)): ?>
            <div class="similar-cases-section">
                <h3 class="section-title"> Similar Resolved Cases</h3>
                <div class="similar-cases-grid">
                    <?php foreach ($similar_cases as $case): ?>
                        <div class="similar-case-card">
                            <div class="similar-case-number">
                                <a href="view_complaint.php?id=<?php echo $case['complaint_id']; ?>">
                                    #<?php echo htmlspecialchars($case['complaint_number']); ?>
                                </a>
                            </div>
                            <div class="similar-case-title">
                                <?php echo htmlspecialchars(substr($case['title'], 0, 60)) . (strlen($case['title']) > 60 ? '...' : ''); ?>
                            </div>
                            <div class="similar-case-meta">
                                <span><strong><?php echo round($case['resolution_hours']); ?></strong> hours</span>
                                <span> <?php echo htmlspecialchars($case['category_name']); ?></span>
                            </div>
                            <?php if ($case['resolution_hours'] < 24): ?>
                                <div style="margin-top: 8px;">
                                    <span class="resolution-time">Fast resolution</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>