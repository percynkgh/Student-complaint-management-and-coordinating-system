<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('class_rep')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (isset($_POST['update_profile'])) {
    $full_name = Security::sanitize($_POST['full_name']);
    $email = Security::sanitize($_POST['email']);
    $phone = Security::sanitize($_POST['phone']);
    
    if (empty($full_name) || empty($email)) {
        $error = "Name and email are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        $check = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->execute([$email, $user_id]);
        if ($check->rowCount() > 0) {
            $error = "Email already exists for another user";
        } else {
            $update = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
            if ($update->execute([$full_name, $email, $phone, $user_id])) {
                $success = "Profile updated successfully!";
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['full_name'] = $user['full_name'];
            } else {
                $error = "Failed to update profile";
            }
        }
    }
}

if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if (empty($current) || empty($new) || empty($confirm)) {
        $error = "Please fill in all password fields";
    } elseif (strlen($new) < 6) {
        $error = "New password must be at least 6 characters";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match";
    } elseif (!password_verify($current, $user['password'])) {
        $error = "Current password is incorrect";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        if ($update->execute([$hashed, $user_id])) {
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
    <title>Class Rep Profile - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link rel="stylesheet" href="../classrep/css/classrep.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div><h1>Class Rep Profile <span class="role-badge">CLASS REP</span></h1></div>
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>



    <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>

    <div class="profile-card">
        <div class="avatar-section">
            <div class="avatar"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
            <div><h2><?php echo htmlspecialchars($user['full_name']); ?></h2><span class="rep-id"><?php echo htmlspecialchars($user['student_id']); ?></span></div>
        </div>
        <h3 class="section-title">Profile Information</h3>
        <form method="POST">
            <div class="grid-2">
                <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div>
                <div class="form-group"><label>Email Address</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
                <div class="form-group"><label>Phone Number</label><input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"></div>
                <div class="form-group"><label>Department</label><input type="text" value="<?php echo htmlspecialchars($user['department'] ?? 'Not set'); ?>" readonly></div>
            </div>
            <button type="submit" name="update_profile" class="btn">Update Profile</button>
        </form>
    </div>

    <div class="profile-card">
        <h3 class="section-title">Change Password</h3>
        <div class="info-box"><strong> Password Tips:</strong><ul><li>Use at least 6 characters</li><li>Mix letters and numbers</li><li>Don't share your password</li></ul></div>
        <form method="POST">
            <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
            <div class="grid-2">
                <div class="form-group"><label>New Password</label><input type="password" name="new_password" required minlength="6"></div>
                <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
            </div>
            <button type="submit" name="change_password" class="btn">Change Password</button>
        </form>
    </div>

    <div class="profile-card">
        <h3 class="section-title">Account Information</h3>
        <div class="grid-2">
            <div><label>Member Since</label><p><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p></div>
            <div><label>Last Updated</label><p><?php echo date('F j, Y', strtotime($user['updated_at'])); ?></p></div>
            <div><label>Account Status</label><p style="color: <?php echo $user['is_active']  ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></p></div>
            <div><label>Role</label><p>Class Representative</p></div>
        </div>
    </div>
</div>
</body>
</html>