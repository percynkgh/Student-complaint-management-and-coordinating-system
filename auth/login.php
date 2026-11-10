<?php

require_once '../config/database.php';
require_once '../config/security.php';

$database = new Database();
$db = $database->getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    Security::verifyCSRFToken($_POST['csrf_token']);
    
    $student_id = Security::sanitize($_POST['student_id']);
    $password = $_POST['password'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Check login attempts
    $query = "SELECT COUNT(*) as attempts FROM login_attempts 
              WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE) 
              AND success = FALSE";
    $stmt = $db->prepare($query);
    $stmt->execute([$ip_address]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['attempts'] >= 5) {
        $error = "Too many failed attempts. Please try again after 15 minutes.";
    } else {
        // Get user
        $query = "SELECT user_id, student_id, password, full_name, role, is_active FROM users 
                  WHERE student_id = ? AND is_active = TRUE";
        $stmt = $db->prepare($query);
        $stmt->execute([$student_id]);
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($password, $user['password'])) {
                // Login successful
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                // Create session record
                $query = "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) 
                          VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $user['user_id'],
                    session_id(),
                    $ip_address,
                    $_SERVER['HTTP_USER_AGENT']
                ]);
                
                // Log successful attempt
                $query = "INSERT INTO login_attempts (student_id, ip_address, success) VALUES (?, ?, TRUE)";
                $stmt = $db->prepare($query);
                $stmt->execute([$student_id, $ip_address]);
                
                // Redirect based on role
                switch($user['role']) {
                    case 'admin':
                        header("Location: ../admin/dashboard.php");
                        break;
                    case 'dean':
                    case 'principal':
                        header("Location: ../dean/dashboard.php");
                        break;
                    case 'staff':
                        header("Location: ../staff/dashboard.php");
                        break;
                    case 'supervisor':
                        header("Location: ../supervisor/dashboard.php");
                        break;
                    case 'class_rep':
                        header("Location: ../classrep/dashboard.php");
                        break;
                    default:
                        header("Location: ../student/dashboard.php");
                }
                exit();
            } else {
                // Log failed attempt
                $query = "INSERT INTO login_attempts (student_id, ip_address, success) VALUES (?, ?, FALSE)";
                $stmt = $db->prepare($query);
                $stmt->execute([$student_id, $ip_address]);
                
                $error = "Invalid  ID or password";
            }
        } else {
            $error = "Invalid  ID or password";
        }
    }
}

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Complaint System</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="login-page-wrapper">
        <div class="login-image-side">
            <img src="images/images1.jpeg" alt="Student Complaint System">
            <div class="image-overlay-text">
                <h3>Voice Your Concerns</h3>
                <p>Your complaints matter. We're here to listen and take action.</p>
            </div>
        </div>
        <div class="login-container">
            <h2>Hello,Welcome!</h2>
            <p class="subtitle">Login to access the complaint system</p>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label>ID</label>
                    <input type="text" name="student_id" required placeholder="Enter your ID"
                           value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div class="links">
                <a href="forgot_password.php">Forgot Password?</a>
                
            </div>
            
            <div class="demo-credentials">
                <p><strong>Is this your first time?</strong></p>
               
                <p>Contact the admin if you have any problem signing in or creating an account.</p>
            </div>

                  <div class="transparency-section">
                 <a href="public_dashboard.php" class="btn-transparency">
                   Transparency Dashboard
                </a>
                    <p class="transparency-text">View department rankings, satisfaction scores, and resolved complaints</p>
                      </div>
            
        </div>
    </div>
</body>
</html>