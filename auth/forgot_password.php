<?php

require_once '../config/database.php';
require_once '../config/security.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = Security::sanitize($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        $db = (new Database())->getConnection();
        
        // Check if email exists
        $query = "SELECT user_id, full_name FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Create password_resets table if it doesn't exist
            $create_table = "CREATE TABLE IF NOT EXISTS password_resets (
                reset_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            )";
            $db->exec($create_table);
            
            // Delete old tokens
            $query = "DELETE FROM password_resets WHERE user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$user['user_id']]);
            
            // Insert new token
            $query = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$user['user_id'], $token, $expires]);
            
            // For testing: show the reset link directly
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/SCCSv2/auth/reset_password.php?token=" . $token;
            $success = "Password reset link (copy this): <br><a href='$reset_link'>$reset_link</a>";
            
        } else {
            // Don't reveal if email exists or not (security)
            $success = "If your email exists in our system, you will receive a reset link shortly.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Student Complaint System</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="login-page-wrapper">
        <div class="login-image-side">
            <img src="images/images1.jpeg" alt="Student Complaint System">
            <div class="image-overlay-text">
                <h3>Reset Your Password</h3>
                <p>Don't worry! Enter your email and we'll help you get back into your account.</p>
            </div>
        </div>
        <div class="login-container">
            <h2>Forgot Password?</h2>
            <p class="subtitle">Enter your email address to reset your password</p>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="your@email.com">
                </div>
                
                <button type="submit" class="btn">Send Reset Link</button>
            </form>
            
            <div class="back-link">
                <a href="login.php">← Back to Login</a>
            </div>
            
            <div class="demo-credentials">
                <strong> Testing Mode:</strong> Email sending is disabled. The reset link will be shown directly on this page.
            </div>
        </div>
    </div>
</body>
</html>