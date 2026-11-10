<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('admin') && !Security::hasRole('dean')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if (!$user_id) {
    header("Location: user_management.php");
    exit();
}

// Get user details
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: user_management.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in both password fields";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = ? WHERE user_id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$hashed_password, $user_id])) {
            $success = "Password reset successfully!";
        } else {
            $error = "Failed to reset password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link rel="stylesheet" href="../admin/css/admin.css">
</head>
<body>
    <div class="container">
        <a href="user_details.php?id=<?php echo $user_id; ?>" class="back-link">← Back to User Details</a>
        <h1>Reset Password</h1>
        
        <div class="user-info">
            <p><strong>User:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($user['student_id']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>New Password *</label>
                <input type="password" name="new_password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label>Confirm New Password *</label>
                <input type="password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn">Reset Password</button>
        </form>
    </div>
</body>
</html>