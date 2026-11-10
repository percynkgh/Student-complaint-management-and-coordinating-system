<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

header('Content-Type: application/json');

if (!Security::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to upvote']);
    exit();
}

$complaint_id = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;
$user_id = $_SESSION['user_id'];

$db = (new Database())->getConnection();

try {
    // Check if already upvoted
    $query = "SELECT upvote_id FROM complaint_upvotes WHERE complaint_id = ? AND user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$complaint_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already upvoted this complaint']);
        exit();
    }
    
    // Add upvote
    $query = "INSERT INTO complaint_upvotes (complaint_id, user_id) VALUES (?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$complaint_id, $user_id]);
    
    // Update count in complaints table
    $query = "UPDATE complaints SET upvote_count = upvote_count + 1 WHERE complaint_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$complaint_id]);
    
    // Get new count
    $query = "SELECT upvote_count, priority FROM complaints WHERE complaint_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$complaint_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int)($row['upvote_count'] ?? 0);

    // Update priority based on upvote thresholds
    // > 20 => urgent, > 10 => high, else keep existing priority
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

    echo json_encode([
        'success' => true,
        'message' => 'Upvoted successfully',
        'new_count' => $count
    ]);


    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>