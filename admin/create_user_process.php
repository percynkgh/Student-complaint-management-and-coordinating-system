<?php

require_once '../config/security.php';
require_once '../config/database.php';

Security::requireLogin();
if (!Security::hasRole('admin')) {
    header("Location: /SCCS/auth/unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = !empty($_POST['password']) ? Security::hashPassword($_POST['password']) : Security::hashPassword('Welcome123');
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    
    // Validate required fields
    if (empty($role) || empty($full_name) || empty($email)) {
        header("Location: user_management.php?error=" . urlencode("Please fill in all required fields."));
        exit();
    }
    
    // Validate email
    if (!Security::validateEmail($email)) {
        header("Location: user_management.php?error=" . urlencode("Please enter a valid email address."));
        exit();
    }
    
    // Check if email already exists
    $check_query = "SELECT user_id FROM users WHERE email = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$email]);
    
    if ($check_stmt->fetch()) {
        header("Location: user_management.php?error=" . urlencode("Email address already exists."));
        exit();
    }
    
    // Prepare base user data
    $user_data = [
        'full_name' => $full_name,
        'email' => $email,
        'password' => $password,
        'role' => $role,
        'phone' => $phone,
        'is_active' => $is_active
    ];
    
    // Add role-specific fields
    switch ($role) {
        case 'student':
        case 'class_rep':
            $student_id = trim($_POST['student_id'] ?? '');
            if (empty($student_id)) {
                header("Location: user_management.php?error=" . urlencode("Student ID is required for students."));
                exit();
            }
            
            // Validate student ID format
            if (!preg_match('/^[A-Z0-9]{6,15}$/i', $student_id)) {
                header("Location: user_management.php?error=" . urlencode("Student ID must be 6-15 characters, letters and numbers only."));
                exit();
            }
            
            // Check if student_id already exists
            $check_student = "SELECT user_id FROM users WHERE student_id = ?";
            $check_stmt = $db->prepare($check_student);
            $check_stmt->execute([$student_id]);
            
            if ($check_stmt->fetch()) {
                header("Location: user_management.php?error=" . urlencode("Student ID already exists."));
                exit();
            }
            
            $user_data['student_id'] = $student_id;
            $user_data['department'] = $_POST['student_department'] ?? null; // Academic department
            $user_data['course'] = trim($_POST['course'] ?? '');
            $user_data['class_code'] = trim($_POST['class_code'] ?? '') ?: null;
            $user_data['year_of_study'] = !empty($_POST['year_of_study']) ? (int)$_POST['year_of_study'] : null;
            $user_data['batch_year'] = null;
            break;
            
        case 'staff':
        case 'supervisor':
            $staff_id = trim($_POST['staff_id'] ?? '');
            if (empty($staff_id)) {
                header("Location: user_management.php?error=" . urlencode(ucfirst($role) . " ID is required."));
                exit();
            }
            
            // Check if staff_id already exists
            $check_staff = "SELECT user_id FROM users WHERE student_id = ?";
            $check_stmt = $db->prepare($check_staff);
            $check_stmt->execute([$staff_id]);
            
            if ($check_stmt->fetch()) {
                header("Location: user_management.php?error=" . urlencode(ucfirst($role) . " ID already exists."));
                exit();
            }
            
            $user_data['student_id'] = $staff_id;
            $user_data['department'] = $_POST['staff_department'] ?? ''; // Operational department
            $user_data['course'] = null;
            $user_data['year_of_study'] = null;
            $user_data['batch_year'] = null;
            break;
            
        case 'admin':
        case 'dean':
        case 'principal':
            $admin_id = trim($_POST['admin_id'] ?? '');
            if (empty($admin_id)) {
                header("Location: user_management.php?error=" . urlencode("Admin ID is required."));
                exit();
            }
            
            // Check if admin_id already exists
            $check_admin = "SELECT user_id FROM users WHERE student_id = ?";
            $check_stmt = $db->prepare($check_admin);
            $check_stmt->execute([$admin_id]);
            
            if ($check_stmt->fetch()) {
                header("Location: user_management.php?error=" . urlencode("Admin ID already exists."));
                exit();
            }
            
            $user_data['student_id'] = $admin_id;
            $user_data['department'] = $_POST['admin_dept'] ?? null; // Optional operational department
            $user_data['course'] = null;
            $user_data['year_of_study'] = null;
            $user_data['batch_year'] = null;
            break;
            
        default:
            header("Location: user_management.php?error=" . urlencode("Invalid role selected."));
            exit();
    }
    
    // Build insert query dynamically
    $columns = array_keys($user_data);
    $placeholders = ':' . implode(', :', $columns);
    
    $query = "INSERT INTO users (" . implode(', ', $columns) . ") VALUES (" . $placeholders . ")";
    $stmt = $db->prepare($query);
    
    try {
        $stmt->execute($user_data);
        $new_user_id = $db->lastInsertId();
        
        // Log the action
        $log_query = "INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                     VALUES (?, 'create_user', ?, ?)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([
            $_SESSION['user_id'],
            "Created new {$role} user: {$full_name} (ID: {$new_user_id})",
            $_SERVER['REMOTE_ADDR']
        ]);
        
        header("Location: user_management.php?success=" . urlencode("User created successfully."));
        exit();
    } catch (PDOException $e) {
        // Check for duplicate entry error
        if ($e->errorInfo[1] == 1062) {
            header("Location: user_management.php?error=" . urlencode("Duplicate entry: The ID or email already exists."));
        } else {
            header("Location: user_management.php?error=" . urlencode("Database error: " . $e->getMessage()));
        }
        exit();
    }
} else {
    header("Location: user_management.php");
    exit();
}
?>