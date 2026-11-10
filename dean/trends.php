<?php
require_once '../config/security.php';
Security::requireLogin();
if (!Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: ../unauthorized.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trends - Moved</title>
    <link rel="stylesheet" href="../dean/css/dean.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Trends Analysis Has Moved</h1>
                <small>This page is now managed from the Administrator dashboard</small>
            </div>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
        <div class="nav-menu">
            <a href="dashboard.php" class="active"> Performance</a>
            <a href="department_details.php"> Department Details</a>
            <a href="profile.php"> Profile</a>
        </div>
        <div class="insights-section" style="padding:30px; text-align:center;">
            <p>Detailed trend analytics have been consolidated into the Administrator dashboard for more complete, institution-wide reporting.</p>
            <p>You can still review department performance and ratings from your dashboard.</p>
            <a href="dashboard.php" class="btn" style="display:inline-block; margin-top:10px; padding:10px 20px; background:#3498db; color:#fff; border-radius:6px; text-decoration:none;">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
