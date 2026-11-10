<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="unauthorized-container">
        <h1>403</h1>
        <h2>Unauthorized Access</h2>
        <p>You don't have permission to access this page. Please contact your system administrator if you believe this is an error.</p>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="public_dashboard.php" class="btn">Go to Dashboard</a>
        <?php else: ?>
            <a href="login.php" class="btn">Login</a>
        <?php endif; ?>
        
        <a href="login.php" class="btn btn-login">Home</a>
    </div>
</body>
</html>