<?php

require_once '../config/security.php';
require_once '../config/database.php';

Security::requireLogin();
if (!Security::hasRole('admin')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();

// Handle actions
$message = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        // Check if user has complaints
        $check_query = "SELECT COUNT(*) as count FROM complaints WHERE user_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$user_id]);
        $complaint_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($complaint_count > 0) {
            $error = "Cannot delete user with {$complaint_count} complaints.";
        } else {
            $delete_query = "DELETE FROM users WHERE user_id = ?";
            $delete_stmt = $db->prepare($delete_query);
            
            if ($delete_stmt->execute([$user_id])) {
                $log_query = "INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                             VALUES (?, 'delete_user', ?, ?)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    "Deleted user ID: {$user_id}",
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $message = "User deleted successfully.";
            } else {
                $error = "Failed to delete user.";
            }
        }
    }
}

// Toggle user active status
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $user_id = $_GET['toggle_status'];
    
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot modify your own account status.";
    } else {
        $query = "SELECT is_active FROM users WHERE user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            $new_status = $current['is_active'] ? 0 : 1;
            $status_text = $new_status ? 'activated' : 'deactivated';
            
            $update_query = "UPDATE users SET is_active = ? WHERE user_id = ?";
            $update_stmt = $db->prepare($update_query);
            
            if ($update_stmt->execute([$new_status, $user_id])) {
                $message = "User {$status_text} successfully.";
            } else {
                $error = "Failed to update user status.";
            }
        }
    }
}

// Reset password
if (isset($_GET['reset_password']) && is_numeric($_GET['reset_password'])) {
    $user_id = $_GET['reset_password'];
    $default_password = password_hash('Welcome123', PASSWORD_DEFAULT);
    
    $update_query = "UPDATE users SET password = ? WHERE user_id = ?";
    $update_stmt = $db->prepare($update_query);
    
    if ($update_stmt->execute([$default_password, $user_id])) {
        $message = "Password reset to 'Welcome123' for user ID: {$user_id}";
    } else {
        $error = "Failed to reset password.";
    }
}

// Get filter parameters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM complaints WHERE user_id = u.user_id) as complaint_count,
          (SELECT COUNT(*) FROM complaints WHERE assigned_to = u.user_id) as assigned_count
          FROM users u
          WHERE 1=1";

$params = [];

if (!empty($role_filter)) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter === 'active') {
    $query .= " AND u.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $query .= " AND u.is_active = 0";
}

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as total_students,
    SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as total_staff,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
    SUM(CASE WHEN role = 'class_rep' THEN 1 ELSE 0 END) as total_class_reps,
    SUM(CASE WHEN role = 'dean' THEN 1 ELSE 0 END) as total_deans,
    SUM(CASE WHEN role = 'principal' THEN 1 ELSE 0 END) as total_principals,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users
    FROM users";
$stats_stmt = $db->query($stats_query);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get ACADEMIC departments (for students and class reps)
$academic_depts_query = "SELECT dept_id, dept_name, dept_code FROM departments WHERE dept_type = 'academic' ORDER BY dept_name";
$academic_depts_stmt = $db->query($academic_depts_query);
$academic_departments = $academic_depts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get OPERATIONAL departments (for staff and admins)
$operational_depts_query = "SELECT dept_id, dept_name, dept_code FROM departments WHERE dept_type = 'operational' ORDER BY dept_name";
$operational_depts_stmt = $db->query($operational_depts_query);
$operational_departments = $operational_depts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get distinct roles for filter
$roles_query = "SELECT DISTINCT role FROM users ORDER BY role";
$roles_stmt = $db->query($roles_query);
$roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Student Complaint System</title>
    <link rel="stylesheet" href="../admin/css/admin.css">
    <style>
        .field-hint {
            font-size: 11px;
            color: #6c757d;
            margin-top: 3px;
        }
        .error-border {
            border-color: #dc3545 !important;
        }
        .validation-error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>User Management</h1>
                <p>Manage all users in the system</p>
                <span class="role-badge"><?php echo strtoupper($_SESSION['role']); ?> ACCESS</span>
            </div>
            <div>
                <a href="dashboard.php" class="back-btn">← Dashboard</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="user_management.php" class="nav-item active">Users</a>
            <a href="complaint_management.php" class="nav-item">Complaints</a>
            <a href="manage_departments.php" class="nav-item">Departments</a>
            <a href="analytics.php" class="nav-item">Analytics</a>
            <a href="trends.php" class="nav-item">Trends</a>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_students'] + $stats['total_class_reps']; ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_staff']; ?></div>
                <div class="stat-label">Staff</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_admins'] + $stats['total_deans'] + $stats['total_principals']; ?></div>
                <div class="stat-label">Admin Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_users']; ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['inactive_users']; ?></div>
                <div class="stat-label">Inactive Users</div>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Actions Bar -->
        <div class="actions-bar">
            <div class="filters">
                <form method="GET" class="filter-form">
                    <select name="role" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role']; ?>" <?php echo $role_filter == $role['role'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $role['role'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    
                    <input type="text" name="search" class="search-input" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-secondary">Search</button>
                    <?php if (!empty($role_filter) || !empty($status_filter) || !empty($search)): ?>
                        <a href="user_management.php"><button class="btn-secondary">Clear Filters</button></a>
                    <?php endif; ?>
                </form>
            </div>
            <a href="bulk_import_users.php" style="background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 600; display: inline-block;">📚 Bulk Import</a>
            <button class="btn-primary" onclick="openCreateUserModal()">+ Create New User</button>
        </div>
        
        <!-- Users Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Contact</th>
                        <th>ID Number</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Complaints</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" class="empty-state">
                                <div>No users found</div>
                                <div style="font-size: 13px; margin-top: 10px;">Click "Create New User" to add one</div>
                            </span>
                        </span>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                            <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                                <span class="current-user-badge">(You)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </span>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $user['role'] == 'admin' ? 'danger' : 
                                            ($user['role'] == 'staff' ? 'info' : 
                                            ($user['role'] == 'supervisor' ? 'teal' : 
                                            ($user['role'] == 'class_rep' ? 'warning' : 
                                            ($user['role'] == 'dean' ? 'purple' : 
                                            ($user['role'] == 'principal' ? 'purple' : 'success'))))); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </span>
                                <td>
                                    <div><?php echo htmlspecialchars($user['email']); ?></div>
                                    <small class="user-phone"><?php echo htmlspecialchars($user['phone'] ?? 'No phone'); ?></small>
                                </span>
                                <td>
                                    <?php echo htmlspecialchars($user['student_id'] ?? 'N/A'); ?>
                                </span>
                                <td>
                                    <?php 
                                    if (!empty($user['department'])) {
                                        echo htmlspecialchars($user['department']);
                                        if (in_array($user['role'], ['student', 'class_rep'])) {
                                            echo ' <span class="badge badge-info">Academic</span>';
                                        } elseif ($user['role'] == 'staff' || $user['role'] == 'supervisor') {
                                            echo ' <span class="badge badge-success">Operational</span>';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                    <?php if (!empty($user['course'])): ?>
                                        <br><small><?php echo htmlspecialchars($user['course']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($user['class_code'])): ?>
                                        <br><small>Class: <?php echo htmlspecialchars($user['class_code']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($user['year_of_study']): ?>
                                        <br><small>Year <?php echo $user['year_of_study']; ?></small>
                                    <?php endif; ?>
                                </span>
                                <td>
                                    <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </span>
                                <td>
                                    <span class="badge badge-info"><?php echo $user['complaint_count']; ?> submitted</span>
                                    <?php if ($user['assigned_count'] > 0): ?>
                                        <br><small><?php echo $user['assigned_count']; ?> assigned</small>
                                    <?php endif; ?>
                                </span>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                                <td>
                                    <div class="action-buttons">
                                        <a href="user_details.php?id=<?php echo $user['user_id']; ?>" class="action-view">View</a>
                                        <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="action-edit">Edit</a>
                                        <a href="?reset_password=<?php echo $user['user_id']; ?>" 
                                           class="action-reset" 
                                           onclick="return confirm('Reset password to Welcome123 for this user?')">Reset</a>
                                        
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                            <a href="?toggle_status=<?php echo $user['user_id']; ?>" 
                                               class="action-toggle"
                                               onclick="return confirm('<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> this user?')">
                                                <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </span>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
   
    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; overflow-y: auto;">
            <span class="close" onclick="closeCreateUserModal()">&times;</span>
            <h2 class="modal-title">Create New User</h2>
            <div id="modalError" class="validation-error" style="display: none;"></div>
            
            <form method="post" action="create_user_process.php" id="createUserForm">
                <div class="form-group">
                    <label class="required">Role</label>
                    <select name="role" id="roleSelect" required onchange="toggleRoleFields()">
                        <option value="">Select Role</option>
                        <option value="student">Student</option>
                        <option value="class_rep">Class Representative</option>
                        <option value="staff">Staff</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="admin">Administrator</option>
                        <option value="dean">Dean</option>
                        <option value="principal">Principal</option>
                    </select>
                </div>
                
                <!-- Common Fields for All Roles -->
                <div class="form-group">
                    <label class="required">Full Name</label>
                    <input type="text" name="full_name" id="full_name" required 
                           onkeypress="return /[a-zA-Z\s]/i.test(event.key)"
                           onpaste="return false"
                           title="Letters and spaces only">
                    <div class="field-hint">Letters and spaces only (no numbers or special characters)</div>
                </div>
                
                <div class="form-group">
                    <label class="required">Email Address</label>
                    <input type="email" name="email" id="email" required>
                    <div class="field-hint">Must be a unique valid email address (e.g., name@domain.com)</div>
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" id="phone" placeholder="e.g., 0550710451" maxlength="15">
                    <div class="field-hint">Optional: 10-15 digits, numbers only</div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" value="Welcome123">
                    <div class="password-hint">Default: Welcome123. Users should change on first login.</div>
                </div>
                
                <!-- STUDENT & CLASS REP FIELDS -->
                <div id="student-fields" class="role-fields" style="display: none;">
                    <div class="info-note">
                        <strong>🎓 Student Information</strong> - Fill in the student details
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Student ID</label>
                        <input type="text" name="student_id" id="student_id_field" 
                               placeholder="e.g., STU2024001"
                               pattern="[A-Z0-9]{6,15}"
                               title="6-15 characters, letters and numbers only">
                        <div class="field-hint">Format: 6-15 characters, letters and numbers only (e.g., STU2024001)</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Academic Department</label>
                        <select name="student_department" id="student_dept_field">
                            <option value="">Select Academic Department</option>
                            <?php foreach ($academic_departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>">
                                    <?php echo htmlspecialchars($dept['dept_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-hint">e.g., Computer Science, Engineering, Business Administration</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Course/Program</label>
                        <input type="text" name="course" id="course_field" placeholder="e.g., Computer Science">
                        <div class="field-hint">Optional: Specific program of study</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Class / Section</label>
                        <input type="text" name="class_code" id="class_code_field" placeholder="e.g., CS-2A">
                        <div class="field-hint">Used to group students into the same class as their class rep (e.g., CS-2A). Students who share department, course, class and year appear together for batch complaints.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Year of Study</label>
                        <select name="year_of_study" id="year_field">
                            <option value="">Select Year</option>
                            <?php for($i=1; $i<=6; $i++): ?>
                                <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <!-- STAFF FIELDS -->
                <div id="staff-fields" class="role-fields" style="display: none;">
                    <div class="info-note">
                        <strong>👔 Staff Information</strong> - Fill in the staff details
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Staff ID</label>
                        <input type="text" name="staff_id" id="staff_id_field" placeholder="e.g., STAFF001">
                        <div class="field-hint">Unique staff identification number</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Operational Department</label>
                        <select name="staff_department" id="staff_dept_field">
                            <option value="">Select Operational Department</option>
                            <?php foreach ($operational_departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>">
                                    <?php echo htmlspecialchars($dept['dept_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-hint">Departments that handle complaints (Finance, ICT, HR, Registry)</div>
                    </div>
                </div>
                
                <!-- ADMIN/DEAN/PRINCIPAL FIELDS -->
                <div id="admin-fields" class="role-fields" style="display: none;">
                    <div class="info-note">
                        <strong>🔧 Administrator Information</strong> - Fill in the admin details
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Admin ID</label>
                        <input type="text" name="admin_id" id="admin_id_field" placeholder="e.g., ADMIN001">
                        <div class="field-hint">Unique administrator identification number</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Department (Optional)</label>
                        <select name="admin_dept" id="admin_dept_field">
                            <option value="">No Department</option>
                            <?php foreach ($operational_departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>">
                                    <?php echo htmlspecialchars($dept['dept_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-hint">Optional: Assign admin to oversee a specific operational department</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Account Status</label>
                    <select name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn-primary" onclick="validateAndSubmit()">Create User</button>
                    <button type="button" class="btn-secondary" onclick="closeCreateUserModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
   
    <script>
        // Toggle role-specific fields
        function toggleRoleFields() {
            var role = document.getElementById('roleSelect').value;
            
            // Hide all role-specific fields
            document.getElementById('student-fields').style.display = 'none';
            document.getElementById('staff-fields').style.display = 'none';
            document.getElementById('admin-fields').style.display = 'none';
            
            // Show based on selected role
            if (role === 'student' || role === 'class_rep') {
                document.getElementById('student-fields').style.display = 'block';
            } 
            else if (role === 'staff' || role === 'supervisor') {
                document.getElementById('staff-fields').style.display = 'block';
            }
            else if (role === 'admin' || role === 'dean' || role === 'principal') {
                document.getElementById('admin-fields').style.display = 'block';
            }
        }
        
        // Validate email format
        function isValidEmail(email) {
            var re = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            return re.test(email);
        }
        
        // Validate name (letters and spaces only)
        function isValidName(name) {
            var re = /^[a-zA-Z\s]{3,100}$/;
            return re.test(name);
        }
        
        // Validate phone (optional but if provided, check format)
        function isValidPhone(phone) {
            if (!phone) return true;
            var re = /^[0-9]{10,15}$/;
            return re.test(phone);
        }
        
        // Validate student ID format
        function isValidStudentId(id) {
            var re = /^[A-Z0-9]{6,15}$/i;
            return re.test(id);
        }
        
        // Show error message
        function showError(message) {
            var errorDiv = document.getElementById('modalError');
            errorDiv.innerHTML = message;
            errorDiv.style.display = 'block';
            // Scroll to error
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(function() {
                errorDiv.style.display = 'none';
            }, 5000);
        }
        
        // Validate and submit form
        function validateAndSubmit() {
            // Clear previous error
            var errorDiv = document.getElementById('modalError');
            errorDiv.style.display = 'none';
            
            // Get role
            var role = document.getElementById('roleSelect').value;
            
            // Validate role
            if (!role) {
                showError('❌ Please select a role');
                document.getElementById('roleSelect').focus();
                return false;
            }
            
            // Validate full name
            var fullName = document.getElementById('full_name').value.trim();
            if (!fullName) {
                showError('❌ Full name is required');
                document.getElementById('full_name').focus();
                return false;
            }
            
            // Check for numbers in name
            if (!isValidName(fullName)) {
                showError('❌ Full name must contain only letters and spaces (no numbers or special characters) and be at least 3 characters long');
                document.getElementById('full_name').focus();
                return false;
            }
            
            // Validate email
            var email = document.getElementById('email').value.trim();
            if (!email) {
                showError('❌ Email address is required');
                document.getElementById('email').focus();
                return false;
            }
            
            if (!isValidEmail(email)) {
                showError('❌ Please enter a valid email address (e.g., name@domain.com)');
                document.getElementById('email').focus();
                return false;
            }
            
            // Validate phone
            var phone = document.getElementById('phone').value.trim();
            if (!isValidPhone(phone)) {
                showError('❌ Phone number must be 10-15 digits (numbers only)');
                document.getElementById('phone').focus();
                return false;
            }
            
            // Role-specific validation
            if (role === 'student' || role === 'class_rep') {
                var studentId = document.getElementById('student_id_field').value.trim();
                if (!studentId) {
                    showError('❌ Student ID is required for ' + (role === 'class_rep' ? 'Class Representative' : 'Student'));
                    document.getElementById('student_id_field').focus();
                    return false;
                }
                
                if (!isValidStudentId(studentId)) {
                    showError('❌ Student ID must be 6-15 characters with only letters and numbers (e.g., STU2024001)');
                    document.getElementById('student_id_field').focus();
                    return false;
                }
                
                var academicDept = document.getElementById('student_dept_field').value;
                if (!academicDept) {
                    showError('❌ Please select an academic department');
                    document.getElementById('student_dept_field').focus();
                    return false;
                }
            }
            
            if (role === 'staff' || role === 'supervisor') {
                var staffId = document.getElementById('staff_id_field').value.trim();
                if (!staffId) {
                    showError('❌ ' + (role === 'supervisor' ? 'Supervisor' : 'Staff') + ' ID is required');
                    document.getElementById('staff_id_field').focus();
                    return false;
                }
                
                var operationalDept = document.getElementById('staff_dept_field').value;
                if (!operationalDept) {
                    showError('❌ Please select an operational department');
                    document.getElementById('staff_dept_field').focus();
                    return false;
                }
            }
            
            if (role === 'admin' || role === 'dean' || role === 'principal') {
                var adminId = document.getElementById('admin_id_field').value.trim();
                if (!adminId) {
                    showError('❌ Admin ID is required for ' + role.charAt(0).toUpperCase() + role.slice(1));
                    document.getElementById('admin_id_field').focus();
                    return false;
                }
            }
            
            // All validations passed - submit the form
            document.getElementById('createUserForm').submit();
        }
        
        // Prevent numbers in name field in real-time
        document.addEventListener('DOMContentLoaded', function() {
            var nameInput = document.getElementById('full_name');
            if (nameInput) {
                nameInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[0-9]/g, '');
                });
            }
            
            // Phone number - allow only numbers
            var phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
            
            toggleRoleFields();
        });
        
        function openCreateUserModal() {
            document.getElementById('createUserModal').style.display = 'flex';
            // Reset form
            document.getElementById('createUserForm').reset();
            document.getElementById('roleSelect').value = '';
            document.getElementById('modalError').style.display = 'none';
            toggleRoleFields();
        }
        
        function closeCreateUserModal() {
            document.getElementById('createUserModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('createUserModal');
            if (event.target === modal) {
                closeCreateUserModal();
            }
        }
    </script>
    
    <script src="../admin/js/admin-core.js"></script>
</body>
</html>