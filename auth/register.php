<?php
// Student registration with strict MX validation + bypass for testing
require_once '../config/database.php';
require_once '../config/security.php';

$database = new Database();
$db = $database->getConnection();
$error = '';
$success = '';

// ========== CONFIGURATION ==========
// Set to true ONLY if XAMPP cannot perform DNS lookups (for testing)
  // Change to true to skip MX validation on localhost
$BYPASS_MX_CHECK = false;

// ========== EMAIL DOMAIN VALIDATION (Strict) ==========
function validateEmailDomain($email, $bypass = false) {
    // Basic syntax
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Please enter a valid email address.'];
    }

    $domain = substr(strrchr($email, "@"), 1);
    if (empty($domain)) {
        return ['valid' => false, 'message' => 'Invalid email domain.'];
    }

    // List of trusted domains (skip MX check for performance & reliability)
    $trustedDomains = [
        'gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 
        'protonmail.com', 'icloud.com', 'aol.com', 'mail.com'
    ];
    if (in_array($domain, $trustedDomains)) {
        return ['valid' => true, 'message' => ''];
    }

    // Bypass for local testing (if DNS is broken)
    if ($bypass) {
        error_log("Bypassing MX check for $domain due to BYPASS_MX_CHECK=true");
        return ['valid' => true, 'message' => ''];
    }

    // Check MX record
    $hasMX = false;
    if (function_exists('checkdnsrr')) {
        $hasMX = @checkdnsrr($domain, 'MX');
    }
    if (!$hasMX && function_exists('dns_get_record')) {
        $records = @dns_get_record($domain, DNS_MX);
        $hasMX = !empty($records);
    }

    if (!$hasMX) {
        return ['valid' => false, 'message' => 'The email domain appears to be invalid or misspelled. Please check for typos (e.g., gmail.com, not gmil.com).'];
    }

    return ['valid' => true, 'message' => ''];
}
// ========================================================

// Get academic departments
$dept_query = "SELECT dept_name FROM departments WHERE type = 'academic' ORDER BY dept_name";
$dept_stmt = $db->prepare($dept_query);
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($departments)) {
    $departments = [
        'Computer Science', 'Engineering', 'Business Administration', 'Medicine', 'Law',
        'Arts and Humanities', 'Social Sciences', 'Education', 'Nursing', 'Pharmacy'
    ];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    Security::verifyCSRFToken($_POST['csrf_token']);
    
    $student_id = Security::sanitize($_POST['student_id']);
    $email = Security::sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = Security::sanitize($_POST['full_name']);
    $department = Security::sanitize($_POST['department']);
    $course = Security::sanitize($_POST['course']);
    $year_of_study = Security::sanitize($_POST['year_of_study']);
    $phone = Security::sanitize($_POST['phone']);
    
    if (!preg_match('/^[A-Z0-9]{6,15}$/', $student_id)) {
        $error = "Student ID must be 6-15 characters, letters and numbers only";
    } elseif (empty($student_id) || empty($email) || empty($password) || empty($full_name) || empty($department)) {
        $error = "Please fill in all required fields";
    } elseif (!Security::validateEmail($email)) {
        $error = "Please enter a valid email address";
    } else {
        // MX validation (strict)
        $domainValidation = validateEmailDomain($email, $BYPASS_MX_CHECK);
        if (!$domainValidation['valid']) {
            $error = $domainValidation['message'];
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } else {
            $query = "SELECT student_id FROM users WHERE student_id = ? OR email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$student_id, $email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Student ID or Email already exists";
            } else {
                $hashed_password = Security::hashPassword($password);
                $query = "INSERT INTO users (student_id, email, password, full_name, role, department, course, year_of_study, phone) 
                          VALUES (?, ?, ?, ?, 'student', ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$student_id, $email, $hashed_password, $full_name, $department, $course, $year_of_study, $phone])) {
                    $success = "Registration successful! You can now login.";
                    $_POST = array();
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    }
}

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Complaint System</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="login-page-wrapper">
        <div class="login-image-side">
            <img src="images/images1.jpeg" alt="Student Complaint System">
            <div class="image-overlay-text">
                <h3>Join Our Community</h3>
                <p>Create your student account and let your voice be heard. We're here to help resolve your concerns.</p>
            </div>
        </div>
        <div class="login-container">
            <h2>Student Registration</h2>
            <p class="subtitle">Create your student account to submit complaints</p>
            <div class="info-box"><strong> Academic Departments Only:</strong> Select your department.</div>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" id="registrationForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label class="required">Student ID</label>
                    <input type="text" name="student_id" required placeholder="e.g., STU2024001" pattern="[A-Z0-9]{6,15}" value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>">
                    <div class="password-hint">Use your institutional Student ID</div>
                </div>
                
                <div class="form-group">
                    <label class="required">Full Name</label>
                    <input type="text" name="full_name" required placeholder="Enter your full name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Email</label>
                        <input type="email" name="email" required placeholder="your@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <div class="password-hint">We accept Gmail, Yahoo, Outlook, @edu.gh, etc. Typos like gmil.com will be rejected.</div>
                    </div>
                    <div class="form-group">
                        <label>Phone (Optional)</label>
                        <input type="tel" name="phone" placeholder="e.g., 0712345678" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Academic Department</label>
                        <select name="department" required>
                            <option value="">Select Your Academic Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo (isset($_POST['department']) && $_POST['department'] == $dept) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Course/Program</label>
                        <input type="text" name="course" id="course" placeholder="e.g., Computer Science" value="<?php echo isset($_POST['course']) ? htmlspecialchars($_POST['course']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Year of Study</label>
                        <select name="year_of_study">
                            <option value="">Select Year</option>
                            <?php for($i=1; $i<=5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo (isset($_POST['year_of_study']) && $_POST['year_of_study'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?><?php echo $i == 1 ? 'st' : ($i == 2 ? 'nd' : ($i == 3 ? 'rd' : 'th')); ?> Year</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Password</label>
                        <input type="password" name="password" id="password" required placeholder="Minimum 8 characters">
                    </div>
                    <div class="form-group">
                        <label class="required">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required placeholder="Re-enter password">
                    </div>
                </div>
                <div class="password-hint">Password must be at least 8 characters</div>
                
                <div class="note-box"><strong>Note:</strong> By registering, you agree to the terms and conditions.</div>
                <button type="submit" class="btn">Register as Student</button>
            </form>
            
            <div class="login-link">Already have an account? <a href="login.php">Login here</a></div>
        </div>
    </div>
    
    <script>
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const studentId = document.querySelector('input[name="student_id"]').value;
            const department = document.querySelector('select[name="department"]').value;
            const email = document.querySelector('input[name="email"]').value;
            
            const domain = email.split('@')[1];
            if (domain && (domain === 'gmil.com' || domain === 'hotmal.com' || domain === 'yaho.com')) {
                e.preventDefault();
                alert('Did you mean ' + domain.replace('gmil', 'gmail').replace('hotmal', 'hotmail').replace('yaho', 'yahoo') + '? Please correct the typo.');
                return;
            }
            
            const studentIdRegex = /^[A-Z0-9]{6,15}$/;
            if (!studentIdRegex.test(studentId)) {
                e.preventDefault();
                alert('Student ID must be 6-15 characters and contain only letters and numbers');
                return;
            }
            if (!department) {
                e.preventDefault();
                alert('Please select your academic department');
                return;
            }
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long');
                return;
            }
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match');
                return;
            }
        });
        
        document.getElementById('confirm_password').addEventListener('keyup', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            if (confirm.length > 0) {
                this.style.borderColor = (password === confirm) ? '#00c851' : '#ff4444';
            } else {
                this.style.borderColor = '#e0e0e0';
            }
        });
        
        document.getElementById('course').addEventListener('blur', function() {
            if (this.value.length > 0) {
                this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
            }
        });
    </script>
</body>
</html>