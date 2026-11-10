<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('admin') && !Security::hasRole('dean') && !Security::hasRole('principal')) {
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

// Get system statistics for admin dashboard
$stats = [];

// Total users
$query = "SELECT COUNT(*) as total FROM users";
$stmt = $db->query($query);
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total complaints
$query = "SELECT COUNT(*) as total FROM complaints";
$stmt = $db->query($query);
$stats['total_complaints'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pending complaints
$query = "SELECT COUNT(*) as total FROM complaints WHERE status NOT IN ('resolved', 'closed', 'rejected')";
$stmt = $db->query($query);
$stats['pending_complaints'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

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
                $refresh_query = "SELECT * FROM users WHERE user_id = ?";
                $refresh_stmt = $db->prepare($refresh_query);
                $refresh_stmt->execute([$user_id]);
                $user = $refresh_stmt->fetch(PDO::FETCH_ASSOC);
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
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters";
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
            
            // Create admin_logs table if it doesn't exist
            $create_logs_table = "CREATE TABLE IF NOT EXISTS admin_logs (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE
            )";
            $db->exec($create_logs_table);
            
            // Log the password change for security
            $log_query = "INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, 'password_change', 'Password changed', ?)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([$user_id, $_SERVER['REMOTE_ADDR']]);
        } else {
            $error = "Failed to change password";
        }
    }
}

// Handle profile picture upload (optional)
if (isset($_POST['upload_avatar']) && isset($_FILES['avatar'])) {
    $target_dir = "../uploads/profiles/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Check if file was uploaded without errors
    if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_types)) {
            $new_filename = "admin_" . $user_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
                // Delete old avatar if exists
                if (!empty($user['profile_pic']) && file_exists("../" . $user['profile_pic'])) {
                    unlink("../" . $user['profile_pic']);
                }
                
                // Update database
                $profile_pic_path = "uploads/profiles/" . $new_filename;
                $update_query = "UPDATE users SET profile_pic = ? WHERE user_id = ?";
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute([$profile_pic_path, $user_id])) {
                    $success = "Profile picture updated successfully!";
                    
                    // Refresh user data
                    $refresh_query = "SELECT * FROM users WHERE user_id = ?";
                    $refresh_stmt = $db->prepare($refresh_query);
                    $refresh_stmt->execute([$user_id]);
                    $user = $refresh_stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "Failed to update profile picture in database";
                }
            } else {
                $error = "Failed to upload profile picture";
            }
        } else {
            $error = "Only JPG, JPEG, PNG & GIF files are allowed";
        }
    } else {
        $error = "Please select a valid image file";
    }
}

// Create admin_logs table if it doesn't exist (moved here for password change)
$create_logs_table = "CREATE TABLE IF NOT EXISTS admin_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE
)";
$db->exec($create_logs_table);

// Get recent admin activity
$logs_query = "SELECT * FROM admin_logs WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
$logs_stmt = $db->prepare($logs_query);
$logs_stmt->execute([$user_id]);
$recent_activity = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link rel="stylesheet" href="../admin/css/admin.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Profile</h1>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <!-- Left Sidebar - Avatar & Stats -->
            <div class="profile-sidebar">
                <?php if (!empty($user['profile_pic']) && file_exists("../" . $user['profile_pic'])): ?>
                    <img src="../<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile" class="avatar">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                
                <div class="avatar-upload">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="file" name="avatar" id="avatar" accept="image/*" required>
                        <label for="avatar"> Change Photo</label>
                        <button type="submit" name="upload_avatar" class="upload-btn">Upload</button>
                    </form>
                </div>
                
                <div class="role-badge">
                    <?php echo strtoupper($_SESSION['role']); ?>
                </div>
                
                <div class="admin-id">
                    <strong>ID:</strong> <?php echo htmlspecialchars($user['student_id']); ?>
                </div>
                
                <div class="stats-card">
                    <div class="stat-item">
                        <span class="stat-label">Total Users</span>
                        <span class="stat-value"><?php echo number_format($stats['total_users']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Complaints</span>
                        <span class="stat-value"><?php echo number_format($stats['total_complaints']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Pending</span>
                        <span class="stat-value"><?php echo number_format($stats['pending_complaints']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Member Since</span>
                        <span class="stat-value"><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Profile Forms -->
            <div class="profile-main">
                <!-- Profile Information Form -->
                <h3 class="section-title">
                    <span> Profile Information</span>
                </h3>
                
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
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="e.g., 0242345678">
                        </div>
                        
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['department'] ?? 'Administration'); ?>" readonly>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn">Update Profile</button>
                </form>
                
                <!-- Change Password Form -->
                <h3 class="section-title password-section">
                    <span> Change Password</span>
                </h3>
                
                <div class="security-tips">
                    <h4>Password Requirements:</h4>
                    <ul>
                        <li>Minimum 8 characters</li>
                        <li>Mix of letters and numbers</li>
                        <li>Include at least one uppercase letter</li>
                        <li>Include at least one special character</li>
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
                            <input type="password" name="new_password" required minlength="8">
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn">Change Password</button>
                    
                </form>
                
                
                
               
            </div>
        </div>
        
        <!-- Recent Activity Log -->
        <div class="activity-log">
            <h3 class="section-title"> Recent Activity</h3>
            
            <?php if (empty($recent_activity)): ?>
                <p class="no-activity-message">No recent activity logged</p>
            <?php else: ?>
                <?php foreach ($recent_activity as $log): ?>
                    <div class="activity-item">
                        <div class="activity-time"><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></div>
                        <div class="activity-action"><?php echo htmlspecialchars($log['action']); ?></div>
                        <div class="activity-ip">IP: <?php echo htmlspecialchars($log['ip_address']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../admin/js/admin-core.js"></script>
    <script src="../admin/js/profile.js"></script>
</body>
</html>