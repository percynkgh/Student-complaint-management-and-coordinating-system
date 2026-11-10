<?php
// 
require_once 'config/database.php';

$db = (new Database())->getConnection();

echo "<h1>Database Status</h1>";

// Check complaints table
$query = "SELECT COUNT(*) as total FROM complaints";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<p>Total complaints: <strong>" . $result['total'] . "</strong></p>";

if ($result['total'] > 0) {
    // Show recent complaints
    $query = "SELECT complaint_id, complaint_number, title, description, status, created_at 
              FROM complaints 
              ORDER BY created_at DESC 
              LIMIT 5";
    $stmt = $db->query($query);
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Recent Complaints:</h2>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Number</th><th>Title</th><th>Status</th><th>Created</th></tr>";
    
    foreach ($complaints as $c) {
        echo "<tr>";
        echo "<td>" . $c['complaint_id'] . "</td>";
        echo "<td>" . $c['complaint_number'] . "</td>";
        echo "<td>" . htmlspecialchars($c['title']) . "</td>";
        echo "<td>" . $c['status'] . "</td>";
        echo "<td>" . $c['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test search for 'projector'
    $test = "%projector%";
    $query = "SELECT COUNT(*) as count FROM complaints WHERE title LIKE ? OR description LIKE ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$test, $test]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Complaints containing 'projector': <strong>" . $result['count'] . "</strong></p>";
    
} else {
    echo "<p style='color: red;'>No complaints found in database!</p>";
    echo "<p>You need to submit some complaints first for duplicate detection to work.</p>";
}
?>