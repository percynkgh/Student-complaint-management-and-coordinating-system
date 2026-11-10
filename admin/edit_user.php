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

// Get departments based on user role
// Academic departments (for students)
$academic_depts_query = "SELECT dept_name FROM departments WHERE dept_type = 'academic' ORDER BY dept_name";
$academic_depts_stmt = $db->query($academic_depts_query);
$academic_departments = $academic_depts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Operational departments (for staff and admins)
$operational_depts_query = "SELECT dept_name FROM departments WHERE dept_type = 'operational' ORDER BY dept_name";
$operational_depts_stmt = $db->query($operational_depts_query);
$operational_departments = $operational_depts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = Security::sanitize($_POST['student_id'] ?? ''); 
    $full_name = Security::sanitize($_POST['full_name']);
    $email = Security::sanitize($_POST['email']);
    $role = Security::sanitize($_POST['role']);
    $department = Security::sanitize($_POST['department'] ?? '');
    $course = Security::sanitize($_POST['course'] ?? '');
    $class_code = Security::sanitize($_POST['class_code'] ?? '');
    $year_of_study = Security::sanitize($_POST['year_of_study'] ?? '');
    $phone = Security::sanitize($_POST['phone'] ?? '');
    
    if (empty($full_name) || empty($email) || empty($role)) {
        $error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if email exists for another user
        $query = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Email already exists for another user";
        } else {
            $query = "UPDATE users SET student_id = ?, full_name = ?, email = ?, role = ?, department = ?, course = ?, class_code = ?, year_of_study = ?, phone = ? WHERE user_id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$student_id,$full_name, $email, $role, $department, $course, $class_code, $year_of_study, $phone, $user_id])) {
                $success = "User updated successfully!";
                // Refresh user data
                $query = "SELECT * FROM users WHERE user_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Failed to update user";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link rel="stylesheet" href="../admin/css/admin.css">
    <style>
        .department-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .role-specific-hint {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Edit User</h1>
                <p>Editing: <?php echo htmlspecialchars($user['full_name']); ?></p>
            </div>
            <a href="user_details.php?id=<?php echo $user_id; ?>" class="back-btn">← Back to User Details</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>Student/Staff ID</label>
                    <input type="text" name="student_id" value="<?php echo htmlspecialchars($user['student_id']); ?>"required>
                </div>
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" id="roleSelect" required onchange="toggleDepartmentFields()">
                        <option value="student" <?php echo $user['role'] == 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="class_rep" <?php echo $user['role'] == 'class_rep' ? 'selected' : ''; ?>>Class Representative</option>
                        <option value="staff" <?php echo $user['role'] == 'staff' ? 'selected' : ''; ?>>Staff</option>
                        <option value="supervisor" <?php echo $user['role'] == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                        <?php if (Security::hasRole('admin')): ?>
                            <option value="dean" <?php echo $user['role'] == 'dean' ? 'selected' : ''; ?>>Dean</option>
                            <option value="principal" <?php echo $user['role'] == 'principal' ? 'selected' : ''; ?>>Principal</option>
                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Dynamic Department Section -->
                <div id="departmentSection">
                    <?php if (in_array($user['role'], ['student', 'class_rep'])): ?>
                        <!-- Student: Show Academic Departments -->
                        <div class="form-group" id="academicDeptGroup">
                            <label>Academic Department</label>
                            <select name="department" id="academicDept">
                                <option value="">Select Academic Department</option>
                                <?php foreach ($academic_departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>" 
                                        <?php echo $user['department'] == $dept['dept_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="department-hint">Academic department for student (e.g., Computer Science, Engineering)</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Course/Program</label>
                            <input type="text" name="course" value="<?php echo htmlspecialchars($user['course'] ?? ''); ?>" 
                                   placeholder="e.g., Computer Science">
                        </div>
                        
                        <div class="form-group">
                            <label>Class / Section</label>
                            <input type="text" name="class_code" value="<?php echo htmlspecialchars($user['class_code'] ?? ''); ?>" 
                                   placeholder="e.g., CS-2A">
                            <div class="department-hint">Groups students under the same class rep for batch complaints. Must match exactly across department, course, class and year.</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Year of Study</label>
                            <select name="year_of_study">
                                <option value="">Select Year</option>
                                <?php for($i=1; $i<=6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $user['year_of_study'] == $i ? 'selected' : ''; ?>>
                                        Year <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php elseif ($user['role'] == 'staff' || $user['role'] == 'supervisor'): ?>
                        <!-- Staff: Show Operational Departments -->
                        <div class="form-group" id="operationalDeptGroup">
                            <label>Operational Department *</label>
                            <select name="department" id="operationalDept" required>
                                <option value="">Select Operational Department</option>
                                <?php foreach ($operational_departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>" 
                                        <?php echo $user['department'] == $dept['dept_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="department-hint">Operational department that handles complaints (e.g., Finance, ICT, HR)</div>
                        </div>
                        
                        <!-- Hide course and year for staff -->
                        <input type="hidden" name="course" value="">
                        <input type="hidden" name="class_code" value="">
                        <input type="hidden" name="year_of_study" value="">
                    <?php else: ?>
                        <!-- Admin/Dean/Principal: Show Optional Operational Departments -->
                        <div class="form-group" id="adminDeptGroup">
                            <label>Department (Optional)</label>
                            <select name="department" id="adminDept">
                                <option value="">No Department</option>
                                <?php foreach ($operational_departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>" 
                                        <?php echo $user['department'] == $dept['dept_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="department-hint">Optional: Assign to oversee a specific operational department</div>
                        </div>
                        
                        <!-- Hide course and year for admin -->
                        <input type="hidden" name="course" value="">
                        <input type="hidden" name="class_code" value="">
                        <input type="hidden" name="year_of_study" value="">
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                           placeholder="e.g., 0550710451">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">Update User</button>
                    <a href="user_details.php?id=<?php echo $user_id; ?>" > <button type="button" class="btn-secondary">Cancel</button></a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleDepartmentFields() {
            var role = document.getElementById('roleSelect').value;
            var departmentSection = document.getElementById('departmentSection');
            
            if (role === 'student' || role === 'class_rep') {
                // Student view - Academic departments
                departmentSection.innerHTML = `
                    <div class="form-group" id="academicDeptGroup">
                        <label>Academic Department</label>
                        <select name="department" id="academicDept">
                            <option value="">Select Academic Department</option>
                            <?php foreach ($academic_departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>">
                                    <?php echo htmlspecialchars($dept['dept_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="department-hint">Academic department for student (e.g., Computer Science, Engineering)</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Course/Program</label>
                        <input type="text" name="course" value="<?php echo htmlspecialchars($user['course'] ?? ''); ?>" 
                               placeholder="e.g., Computer Science">
                    </div>
                    
                    <div class="form-group">
                        <label>Class / Section</label>
                        <input type="text" name="class_code" value="<?php echo htmlspecialchars($user['class_code'] ?? ''); ?>" 
                               placeholder="e.g., CS-2A">
                        <div class="department-hint">Groups students under the same class rep for batch complaints.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Year of Study</label>
                        <select name="year_of_study">
                            <option value="">Select Year</option>
                            <?php for($i=1; $i<=6; $i++): ?>
                                <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                `;
            } 
            else if (role === 'staff' || role === 'supervisor') {
                // Staff/Supervisor view - Operational departments
                departmentSection.innerHTML = `
                    <div class="form-group" id="operationalDeptGroup">
                        <label>Operational Department *</label>
                        <select name="department" id="operationalDept" required>
                            <option value="">Select Operational Department</option>
                            <?php foreach ($operational_departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>">
                                    <?php echo htmlspecialchars($dept['dept_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="department-hint">${role === 'supervisor' ? 'Each department should have one supervisor by convention (not enforced by the system).' : 'Operational department that handles complaints (e.g., Finance, ICT, HR)'}</div>
                    </div>
                    <input type="hidden" name="course" value="">
                    <input type="hidden" name="class_code" value="">
                    <input type="hidden" name="year_of_study" value="">
                `;
            } 
            else {
                // Admin/Dean/Principal view - Optional operational departments
                departmentSection.innerHTML = `
                    <div class="form-group" id="adminDeptGroup">
                        <label>Department (Optional)</label>
                        <select name="department" id="adminDept">
                            <option value="">No Department</option>
                            <?php foreach ($operational_departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>">
                                    <?php echo htmlspecialchars($dept['dept_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="department-hint">Optional: Assign to oversee a specific operational department</div>
                    </div>
                    <input type="hidden" name="course" value="">
                    <input type="hidden" name="class_code" value="">
                    <input type="hidden" name="year_of_study" value="">
                `;
            }
        }
        
        // Set initial values when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // No need to call toggleDepartmentFields on load since PHP already renders correctly
        });
    </script>
</body>
</html>