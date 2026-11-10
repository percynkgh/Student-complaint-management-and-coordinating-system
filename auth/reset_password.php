<?php
// reset_password.php
require_once '../config/database.php';
require_once '../config/security.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: forgot_password.php");
    exit();
}

$db = (new Database())->getConnection();

// Verify token
$query = "SELECT pr.*, u.email, u.full_name 
          FROM password_resets pr
          JOIN users u ON pr.user_id = u.user_id
          WHERE pr.token = ? AND pr.used = FALSE AND pr.expires_at > NOW()";
$stmt = $db->prepare($query);
$stmt->execute([$token]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reset) {
    $error = "Invalid or expired reset link. Please request a new one.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$error) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in both password fields";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Hash new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user password
        $query = "UPDATE users SET password = ? WHERE user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$hashed_password, $reset['user_id']]);
        
        // Mark token as used
        $query = "UPDATE password_resets SET used = TRUE WHERE reset_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$reset['reset_id']]);
        
        $success = "Password reset successfully! You can now login with your new password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="container">
        <h1>Reset Password</h1>
        <p class="subtitle">Enter your new password</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
            <div class="login-link">
                <a href="login.php">Go to Login →</a>
            </div>
        <?php else: ?>
            <?php if ($reset): ?>
                <div class="info-box">
                    <strong>Resetting password for:</strong> <?php echo htmlspecialchars($reset['email']); ?>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" required minlength="6" placeholder="Minimum 6 characters">
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required placeholder="Re-enter password">
                    </div>
                    
                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>