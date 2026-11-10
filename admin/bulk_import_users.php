<?php
require_once '../config/security.php';
require_once '../config/database.php';

Security::requireLogin();
if (!Security::hasRole('admin')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$message = '';
$error = '';
$success_count = 0;
$updated_count = 0;
$error_count = 0;
$errors = [];

// Get departments for dropdown
$dept_query = "SELECT dept_name FROM departments WHERE dept_type = 'operational' ORDER BY dept_name";
$dept_stmt = $db->query($dept_query);
$operational_departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['user_file'])) {
    $file = $_FILES['user_file'];
    $update_existing = isset($_POST['update_existing']);
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_extension === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle !== false) {
                // Read headers
                $headers = fgetcsv($handle);
                
                $row_number = 1;
                while (($row = fgetcsv($handle)) !== false) {
                    $row_number++;
                    
                    // Expected columns: full_name, email, role, student_id, department, course, class_code, year_of_study, phone
                    $full_name = trim($row[0] ?? '');
                    $email = trim($row[1] ?? '');
                    $role = trim($row[2] ?? '');
                    $student_id = trim($row[3] ?? '');
                    $department = trim($row[4] ?? '');
                    $course = trim($row[5] ?? '');
                    $class_code = trim($row[6] ?? '');
                    $year_of_study = trim($row[7] ?? '');
                    $phone = trim($row[8] ?? '');
                    
                    // Check if email already exists
                    $check = $db->prepare("SELECT user_id FROM users WHERE email = ?");
                    $check->execute([$email]);
                    $existing_by_email = $check->fetch(PDO::FETCH_ASSOC);

                    // Check if student_id already exists (if provided)
                    $existing_by_student_id = null;
                    if (!empty($student_id)) {
                        $check_id = $db->prepare("SELECT user_id FROM users WHERE student_id = ?");
                        $check_id->execute([$student_id]);
                        $existing_by_student_id = $check_id->fetch(PDO::FETCH_ASSOC);
                    }

                    $existing_user_id = $existing_by_email['user_id'] ?? ($existing_by_student_id['user_id'] ?? null);

                    if ($existing_user_id) {
                        if (!$update_existing) {
                            $error_count++;
                            $errors[] = "Row $row_number: User already exists (matched by email or ID): $email. Check 'Update existing users' to update department/course/class/year instead.";
                            continue;
                        }

                        // Update the existing user's academic assignment fields.
                        // We deliberately do NOT touch password or role here, so a
                        // re-import can't accidentally change someone's access level.
                        $year_of_study_val = !empty($year_of_study) && is_numeric($year_of_study) ? (int)$year_of_study : null;
                        $update_query = "UPDATE users SET full_name = ?, department = ?, course = ?, class_code = ?, year_of_study = ?, phone = ? WHERE user_id = ?";
                        $update_stmt = $db->prepare($update_query);
                        if ($update_stmt->execute([$full_name, $department, $course, $class_code ?: null, $year_of_study_val, $phone, $existing_user_id])) {
                            $updated_count++;
                        } else {
                            $error_count++;
                            $errors[] = "Row $row_number: Database error updating $email";
                        }
                        continue;
                    }
                    
                    // Validate required fields
                    if (empty($full_name) || empty($email) || empty($role)) {
                        $error_count++;
                        $errors[] = "Row $row_number: Missing required fields (Name, Email, Role)";
                        continue;
                    }
                    
                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error_count++;
                        $errors[] = "Row $row_number: Invalid email format: $email";
                        continue;
                    }
                    
                    // Validate role
                    $valid_roles = ['student', 'staff', 'supervisor', 'admin', 'class_rep', 'dean', 'principal'];
                    if (!in_array($role, $valid_roles)) {
                        $error_count++;
                        $errors[] = "Row $row_number: Invalid role '$role'. Must be: " . implode(', ', $valid_roles);
                        continue;
                    }
                    
                    // Prepare data for insert
                    $password = password_hash('Welcome123', PASSWORD_DEFAULT);
                    $is_active = 1;
                    $year_of_study_val = !empty($year_of_study) && is_numeric($year_of_study) ? (int)$year_of_study : null;
                    
                    $query = "INSERT INTO users (full_name, email, password, role, student_id, department, course, class_code, year_of_study, phone, is_active, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$full_name, $email, $password, $role, $student_id, $department, $course, $class_code ?: null, $year_of_study_val, $phone, $is_active])) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $errors[] = "Row $row_number: Database error for $email";
                    }
                }
                fclose($handle);
                
                $message = "$success_count users created";
                if ($update_existing) {
                    $message .= ", $updated_count users updated";
                }
                $message .= ".";
                if ($error_count > 0) {
                    $error = "$error_count rows failed. Check errors below.";
                }
            } else {
                $error = "Could not read the CSV file.";
            }
        } else {
            $error = "Please upload a CSV file.";
        }
    } else {
        $error = "File upload failed. Error code: " . $file['error'];
    }
}

// Generate sample CSV
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_users.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['full_name', 'email', 'role', 'student_id', 'department', 'course', 'class_code', 'year_of_study', 'phone']);
    
    // Sample data
    fputcsv($output, ['John Doe', 'john.doe@university.edu', 'student', 'STU001', 'Computer Science', 'Computer Science', 'CS-1A', '1', '0551234567']);
    fputcsv($output, ['Jane Smith', 'jane.smith@university.edu', 'student', 'STU002', 'Engineering', 'Mechanical Engineering', 'MECH-2B', '2', '0551234568']);
    fputcsv($output, ['Mike Johnson', 'mike.johnson@university.edu', 'staff', 'STAFF001', 'Finance', '', '', '', '0551234569']);
    fputcsv($output, ['Sarah Williams', 'sarah.williams@university.edu', 'class_rep', 'STU003', 'Business', 'Business Administration', 'BUS-3A', '3', '0551234570']);
    fputcsv($output, ['David Brown', 'david.brown@university.edu', 'admin', 'ADMIN001', 'ICT Department', '', '', '', '0551234571']);
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import Users - Admin Dashboard</title>
    <link rel="stylesheet" href="../admin/css/admin.css">
    <style>
        .import-stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .stat-box {
            flex: 1;
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
        }
        .stat-number.success { color: #28a745; }
        .stat-number.error { color: #dc3545; }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .error-list {
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-size: 13px;
        }
        .error-list ul {
            margin: 0;
            padding-left: 20px;
        }
        .error-list li {
            color: #dc3545;
            margin: 5px 0;
        }
        .instructions {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #1976d2;
        }
        .instructions h4 {
            margin-top: 0;
            color: #1976d2;
        }
        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .role-student { background: #27ae60; color: white; }
        .role-staff { background: #3498db; color: white; }
        .role-admin { background: #dc3545; color: white; }
        .role-class_rep { background: #f39c12; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Bulk Import Users</h1>
                <p>Import multiple users at once using a CSV file</p>
                <span class="role-badge">ADMIN ACCESS</span>
            </div>
            <a href="user_management.php" class="back-btn">← Back to Users</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success_count > 0 || $error_count > 0): ?>
        <div class="import-stats">
            <div class="stat-box">
                <div class="stat-number success"><?php echo $success_count; ?></div>
                <div class="stat-label">Successfully Imported</div>
            </div>
            <div class="stat-box">
                <div class="stat-number error"><?php echo $error_count; ?></div>
                <div class="stat-label">Failed</div>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="error-list">
            <strong>Errors:</strong>
            <ul>
                <?php foreach (array_slice($errors, 0, 20) as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
                <?php if (count($errors) > 20): ?>
                    <li>... and <?php echo count($errors) - 20; ?> more errors</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <div class="card">
            <h3>Upload CSV File</h3>
            <p>Upload a CSV file with your user data. The first row should contain column headers.</p>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Choose CSV File</label>
                    <input type="file" name="user_file" accept=".csv" required>
                    <div class="field-hint">Maximum file size: 10MB</div>
                </div>
                
                <div class="form-group" style="margin-top: 10px;">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:normal;">
                        <input type="checkbox" name="update_existing" value="1" style="width:auto;">
                        Update existing users (matched by email or Student/Staff ID) instead of skipping them
                    </label>
                    <div class="field-hint">Turn this on to bulk-assign or fix department, course, class code and year for students who already have accounts. Their name, department, course, class code, year and phone will be updated; password and role are never changed by import.</div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;">
    <button type="submit" style="background: #0A63BC; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;"> Import Users</button>
    <a href="?download_sample=1" style="background: #28a745; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; font-weight: 600; display: inline-block;"> Download Sample CSV</a>
    <a href="user_management.php" style="background: #6c757d; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; font-weight: 600; display: inline-block;">← Back to Users</a>
</div>
            </form>
        </div>
        
        <div class="instructions">
            <h4> CSV Format Instructions</h4>
            <p>Your CSV file should have these columns in order (first row is headers):</p>
            
            <table class="table" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Column</th>
                        <th>Required</th>
                        <th>Description</th>
                        <th>Example</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>full_name</strong></td>
                        <td>Yes</td>
                        <td>Full name of the user</td>
                        <td>John Doe</td>
                    </tr>
                    <tr>
                        <td><strong>email</strong></td>
                        <td>Yes</td>
                        <td>Unique email address</td>
                        <td>john@university.edu</td>
                    </tr>
                    <tr>
                        <td><strong>role</strong></td>
                        <td>Yes</td>
                        <td>
                            <span class="role-badge role-student">student</span>
                            <span class="role-badge role-staff">staff</span>
                            <span class="role-badge role-admin">admin</span>
                            <span class="role-badge role-class_rep">class_rep</span>
                            <span class="role-badge role-student">dean</span>
                            <span class="role-badge role-student">principal</span>
                        </td>
                        <td>student</td>
                    </tr>
                    <tr>
                        <td><strong>student_id</strong></td>
                        <td>No</td>
                        <td>Student/Staff ID number</td>
                        <td>STU001</td>
                    </tr>
                    <tr>
                        <td><strong>department</strong></td>
                        <td>No</td>
                        <td>Operational dept (staff) or Academic dept (students)</td>
                        <td>Computer Science</td>
                    </tr>
                    <tr>
                        <td><strong>course</strong></td>
                        <td>No</td>
                        <td>Course/Program (for students)</td>
                        <td>Computer Science</td>
                    </tr>
                    <tr>
                        <td><strong>class_code</strong></td>
                        <td>No</td>
                        <td>Class/section (for students &amp; class reps). Students who share department, course, class_code and year appear together for batch complaints.</td>
                        <td>CS-2A</td>
                    </tr>
                    <tr>
                        <td><strong>year_of_study</strong></td>
                        <td>No</td>
                        <td>Year 1-6 (for students)</td>
                        <td>1</td>
                    </tr>
                    <tr>
                        <td><strong>phone</strong></td>
                        <td>No</td>
                        <td>Phone number</td>
                        <td>0551234567</td>
                    </tr>
                </tbody>
            </table>
            
            <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px;">
                <strong>Important Notes:</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <li>Default password for all imported users is: <strong>Welcome123</strong></li>
                    <li>Email addresses must be unique across all users</li>
                    <li>Student/Staff IDs must be unique (if provided)</li>
                    <li>All imported users are created with <strong>Active</strong> status by default</li>
                    <li>To bulk-update class info for students who already have accounts, check "Update existing users" above and re-upload with the same emails/IDs — name, department, course, class_code, year and phone will be updated (role and password are left untouched)</li>
                    <li>Click "Download Sample CSV" to see the correct format</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>