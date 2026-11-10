<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get current user data
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if (isset($_POST['update_profile'])) {
    $full_name = Security::sanitize($_POST['full_name']);
    $email = Security::sanitize($_POST['email']);
    $phone = Security::sanitize($_POST['phone']);
    
    if (empty($full_name) || empty($email)) {
        $error = "Name and email are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if email exists for another user
        $check_query = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$email, $user_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Email already exists for another user";
        } else {
            $update_query = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?";
            $update_stmt = $db->prepare($update_query);
            
            if ($update_stmt->execute([$full_name, $email, $phone, $user_id])) {
                $success = "Profile updated successfully!";
                // Refresh user data
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['full_name'] = $user['full_name'];
            } else {
                $error = "Failed to update profile";
            }
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all password fields";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password = ? WHERE user_id = ?";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([$hashed_password, $user_id])) {
            $success = "Password changed successfully!";
        } else {
            $error = "Failed to change password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dean Profile - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link rel="stylesheet" href="../dean/css/dean.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Dean Profile</h1>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <!-- Navigation Menu -->
        <div class="nav-menu">
            <a href="dashboard.php"> Analytics</a>
            
            <a href="profile.php" class="active"> Profile</a>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Profile Information -->
        <div class="profile-card">
            <div class="avatar-section">
                <div class="avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h2 style="margin-bottom: 5px;"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <span class="dean-id"><?php echo htmlspecialchars($user['student_id']); ?></span>
                    <span class="role-badge"><?php echo strtoupper($user['role']); ?></span>
                </div>
            </div>
            
            <h3 class="section-title">Profile Information</h3>
            
            <form method="POST">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="e.g., 0248345678">
                    </div>
                    
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['department'] ?? 'Not assigned'); ?>" readonly>
                    </div>
                </div>
                
                <button type="submit" name="update_profile" class="btn">Update Profile</button>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="profile-card">
            <h3 class="section-title">Change Password</h3>
            
            <div class="info-box">
                <strong> Password Tips:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>Use at least 6 characters</li>
                    <li>Mix letters and numbers for better security</li>
                    <li>Don't share your password with anyone</li>
                </ul>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
                
                <button type="submit" name="change_password" class="btn">Change Password</button>
            </form>
        </div>
        
        <!-- Account Information -->
        <div class="profile-card">
            <h3 class="section-title">Account Information</h3>
            
            <div class="grid-2">
                <div>
                    <label>Member Since</label>
                    <p ><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>
                
                <div>
                    <label>Last Updated</label>
                    <p ><?php echo date('F j, Y', strtotime($user['updated_at'])); ?></p>
                </div>
                
                <div>
                    <label >Account Status</label>
                    <p <?php echo $user['is_active'] ?> >
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </p>
                </div>
                
                <div>
                    <label>Role</label>
                    <p ><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>