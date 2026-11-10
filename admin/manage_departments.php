<?php

require_once '../config/security.php';
require_once '../config/database.php';

Security::requireLogin();
if (!Security::hasRole('admin')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();

// Handle Add Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $dept_name = trim($_POST['dept_name']);
        $dept_code = trim($_POST['dept_code']);
        $description = trim($_POST['description']);
        
        if (!empty($dept_name) && !empty($dept_code)) {
            // Check if department already exists
            $check = $db->prepare("SELECT dept_id FROM departments WHERE dept_name = ? OR dept_code = ?");
            $check->execute([$dept_name, $dept_code]);
            if ($check->fetch()) {
                $error = "Department name or code already exists!";
            } else {
                $stmt = $db->prepare("INSERT INTO departments (dept_name, dept_code, description, dept_type) VALUES (?, ?, ?, 'operational')");
                $stmt->execute([$dept_name, $dept_code, $description]);
                $success = "Department added successfully!";
            }
        } else {
            $error = "Department name and code are required.";
        }
    }
    
    // Handle Edit Department
    elseif ($_POST['action'] === 'edit') {
        $dept_id = $_POST['dept_id'];
        $dept_name = trim($_POST['dept_name']);
        $dept_code = trim($_POST['dept_code']);
        $description = trim($_POST['description']);
        
        // Check if another department has the same name or code
        $check = $db->prepare("SELECT dept_id FROM departments WHERE (dept_name = ? OR dept_code = ?) AND dept_id != ?");
        $check->execute([$dept_name, $dept_code, $dept_id]);
        if ($check->fetch()) {
            $error = "Another department already has this name or code!";
        } else {
            $stmt = $db->prepare("UPDATE departments SET dept_name = ?, dept_code = ?, description = ? WHERE dept_id = ?");
            $stmt->execute([$dept_name, $dept_code, $description, $dept_id]);
            $success = "Department updated successfully!";
        }
    }
    
    // Handle Delete Department
    elseif ($_POST['action'] === 'delete') {
        $dept_id = $_POST['dept_id'];
        
        // Get department name first
        $get_dept = $db->prepare("SELECT dept_name FROM departments WHERE dept_id = ?");
        $get_dept->execute([$dept_id]);
        $dept_name = $get_dept->fetchColumn();
        
        if ($dept_name) {
            // Check if department has complaints
            $check_complaints = $db->prepare("SELECT COUNT(*) FROM complaints WHERE department_assigned = ?");
            $check_complaints->execute([$dept_name]);
            $has_complaints = $check_complaints->fetchColumn();
            
            // Check if department has staff assigned
            $check_staff = $db->prepare("SELECT COUNT(*) FROM users WHERE department = ? AND role = 'staff'");
            $check_staff->execute([$dept_name]);
            $has_staff = $check_staff->fetchColumn();
            
            if ($has_complaints == 0 && $has_staff == 0) {
                $stmt = $db->prepare("DELETE FROM departments WHERE dept_id = ?");
                $stmt->execute([$dept_id]);
                $success = "Department deleted successfully!";
            } else {
                $error = "Cannot delete department. It has $has_complaints complaint(s) and $has_staff staff member(s) assigned.";
            }
        } else {
            $error = "Department not found.";
        }
    }
}

// Get ONLY operational departments (not academic/student departments)
$query = "SELECT d.*, 
          (SELECT COUNT(*) FROM complaints WHERE department_assigned = d.dept_name) as complaint_count,
          (SELECT COUNT(*) FROM users WHERE department = d.dept_name AND role IN ('staff', 'supervisor', 'admin', 'dean', 'principal')) as user_count,
          (SELECT COUNT(*) FROM users WHERE department = d.dept_name AND role = 'supervisor' AND is_active = 1) as supervisor_count
          FROM departments d 
          WHERE d.dept_type = 'operational' OR d.dept_type IS NULL
          ORDER BY d.dept_name";
$stmt = $db->query($query);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_departments = count($departments);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - Admin Dashboard</title>
    <link rel="stylesheet" href="../admin/css/admin.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Department Management</h1>
                <p>Manage operational departments for staff assignment</p>
            </div>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <!-- Stats Card -->
        <div class="stats-card" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div class="stats-number"><?php echo $total_departments; ?></div>
                <div>Operational Departments</div>
                <small style="color: #666;">Departments that handle complaints</small>
            </div>
            <button class="add-btn" onclick="openAddModal()">+ Add New Department</button>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        
        
        <?php if ($total_departments == 0): ?>
            <div class="alert alert-warning">
                <strong>No operational departments found!</strong><br>
                Click "Add New Department" to add departments that will handle complaints (e.g., ICT Department, Finance, Human Resources, etc.).
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Department Name</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Complaints</th>
                        <th>Staff</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <div>No operational departments found</div>
                                <div style="font-size: 13px; margin-top: 10px;">Click "Add New Department" to add one</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><?php echo $dept['dept_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($dept['dept_name']); ?></strong>
                                    <span class="badge badge-success" style="margin-left: 8px;">Operational</span>
                                    <?php if (($dept['supervisor_count'] ?? 0) == 0): ?>
                                        <span class="badge badge-danger" style="margin-left: 4px;" title="Complaints in this department cannot be assigned to staff until a supervisor is added.">No Supervisor</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($dept['dept_code']); ?></span></td>
                                <td><?php echo htmlspecialchars(substr($dept['description'], 0, 50)) ?: '—'; ?></td>
                                <td>
                                    <span class="badge <?php echo $dept['complaint_count'] > 0 ? 'badge-warning' : 'badge-success'; ?>">
                                        <?php echo $dept['complaint_count']; ?>
                                    </span>
                                </td>
                                <td><?php echo $dept['user_count']; ?></td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick='openEditModal(<?php echo json_encode($dept); ?>)'>Edit</button>
                                    <button class="delete-btn" onclick="openDeleteModal(<?php echo $dept['dept_id']; ?>, '<?php echo addslashes($dept['dept_name']); ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add/Edit Modal -->
    <div id="deptModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">Add Operational Department</h3>
            <form method="POST" id="departmentForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="dept_id" id="dept_id">
                
                <div class="form-group">
                    <label class="required">Department Name</label>
                    <input type="text" name="dept_name" id="dept_name" 
                           placeholder="e.g., ICT Department, Human Resources, Finance" required>
                    <div class="field-hint">Only operational departments that handle complaints</div>
                </div>
                
                <div class="form-group">
                    <label class="required">Department Code</label>
                    <input type="text" name="dept_code" id="dept_code" 
                           placeholder="e.g., ICT, HR, FIN" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="description" rows="3" 
                              placeholder="Brief description of the department's role in handling complaints"></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Department</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteModal()">&times;</span>
            <h3>Delete Department</h3>
            <p>Are you sure you want to delete <strong id="deleteDeptName"></strong>?</p>
            <p style="color: #dc3545; font-size: 13px; margin-top: 10px;">
                ⚠️ This action cannot be undone.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="dept_id" id="deleteDeptId">
                <div class="modal-buttons">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-danger">Delete Department</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Department Management JavaScript
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Operational Department';
            document.getElementById('formAction').value = 'add';
            document.getElementById('dept_id').value = '';
            document.getElementById('dept_name').value = '';
            document.getElementById('dept_code').value = '';
            document.getElementById('description').value = '';
            document.getElementById('deptModal').style.display = 'flex';
        }
        
        function openEditModal(dept) {
            document.getElementById('modalTitle').textContent = 'Edit Department';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('dept_id').value = dept.dept_id;
            document.getElementById('dept_name').value = dept.dept_name;
            document.getElementById('dept_code').value = dept.dept_code;
            document.getElementById('description').value = dept.description || '';
            document.getElementById('deptModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('deptModal').style.display = 'none';
        }
        
        function openDeleteModal(deptId, deptName) {
            document.getElementById('deleteDeptId').value = deptId;
            document.getElementById('deleteDeptName').textContent = deptName;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const deptModal = document.getElementById('deptModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target === deptModal) closeModal();
            if (event.target === deleteModal) closeDeleteModal();
        }
        
        // Form validation
        document.getElementById('departmentForm').addEventListener('submit', function(e) {
            const deptName = document.getElementById('dept_name').value.trim();
            const deptCode = document.getElementById('dept_code').value.trim();
            
            if (!deptName || !deptCode) {
                e.preventDefault();
                alert('Please fill in both Department Name and Code.');
                return false;
            }
            
            if (deptCode.length < 2) {
                e.preventDefault();
                alert('Department Code must be at least 2 characters.');
                return false;
            }
        });
    </script>
</body>
</html>