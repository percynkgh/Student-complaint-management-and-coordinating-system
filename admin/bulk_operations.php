<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('admin') && !Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$cm = new ComplaintManager();
$message = '';
$message_type = '';

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$dept_filter = $_GET['dept'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query for complaints
$query = "SELECT c.*, cat.category_name, u.full_name as student_name, u.student_id
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN users u ON c.user_id = u.user_id
          WHERE 1=1";

$params = [];

if ($status_filter != 'all') {
    $query .= " AND c.status = ?";
    $params[] = $status_filter;
}

if ($dept_filter != 'all') {
    $query .= " AND c.department_assigned = ?";
    $params[] = $dept_filter;
}

if ($priority_filter != 'all') {
    $query .= " AND c.priority = ?";
    $params[] = $priority_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(c.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(c.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY c.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$dept_query = "SELECT DISTINCT department_assigned FROM complaints WHERE department_assigned IS NOT NULL ORDER BY department_assigned";
$dept_stmt = $db->query($dept_query);
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all departments for assignment
$all_depts_query = "SELECT dept_name FROM departments ORDER BY dept_name";
$all_depts_stmt = $db->query($all_depts_query);
$all_departments = $all_depts_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $selected_ids = $_POST['selected_complaints'] ?? [];
    $bulk_action = $_POST['bulk_action'];
    
    if (empty($selected_ids)) {
        $message = "Please select at least one complaint";
        $message_type = "error";
    } else {
        $ids_placeholder = implode(',', array_fill(0, count($selected_ids), '?'));
        
        try {
            $db->beginTransaction();
            
            switch ($bulk_action) {
                case 'update_status':
                    $new_status = $_POST['bulk_status'] ?? '';
                    if (empty($new_status)) {
                        throw new Exception("Please select a status");
                    }
                    
                    $query = "UPDATE complaints SET status = ? WHERE complaint_id IN ($ids_placeholder)";
                    $params = array_merge([$new_status], $selected_ids);
                    $stmt = $db->prepare($query);
                    $stmt->execute($params);
                    
                    // Add timeline entries
                    foreach ($selected_ids as $id) {
                        $cm->addTimelineEntry($id, $new_status, "Bulk status update to $new_status", $_SESSION['user_id']);
                    }
                    
                    $message = "Updated " . count($selected_ids) . " complaints to status: " . ucfirst($new_status);
                    break;
                    
                case 'assign_department':
                    $new_department = $_POST['bulk_department'] ?? '';
                    if (empty($new_department)) {
                        throw new Exception("Please select a department");
                    }
                    
                    $query = "UPDATE complaints SET department_assigned = ? WHERE complaint_id IN ($ids_placeholder)";
                    $params = array_merge([$new_department], $selected_ids);
                    $stmt = $db->prepare($query);
                    $stmt->execute($params);
                    
                    foreach ($selected_ids as $id) {
                        $cm->notifyDepartmentSupervisors($new_department, $id, "A complaint was reassigned to your department (bulk update).");
                    }
                    
                    $message = "Assigned " . count($selected_ids) . " complaints to department: " . $new_department;
                    break;
                    
                case 'update_priority':
                    $new_priority = $_POST['bulk_priority'] ?? '';
                    if (empty($new_priority)) {
                        throw new Exception("Please select a priority");
                    }
                    
                    $query = "UPDATE complaints SET priority = ? WHERE complaint_id IN ($ids_placeholder)";
                    $params = array_merge([$new_priority], $selected_ids);
                    $stmt = $db->prepare($query);
                    $stmt->execute($params);
                    
                    $message = "Updated " . count($selected_ids) . " complaints to priority: " . ucfirst($new_priority);
                    break;
                    
                case 'assign_staff':
                    $staff_id = $_POST['bulk_staff'] ?? '';
                    if (empty($staff_id)) {
                        throw new Exception("Please select a staff member");
                    }
                    
                    $query = "UPDATE complaints SET assigned_to = ? WHERE complaint_id IN ($ids_placeholder)";
                    $params = array_merge([$staff_id], $selected_ids);
                    $stmt = $db->prepare($query);
                    $stmt->execute($params);
                    
                    $message = "Assigned " . count($selected_ids) . " complaints to selected staff";
                    break;
                    
                case 'delete':
                    // Only allow deletion of resolved/rejected complaints
                    $check_query = "SELECT complaint_id FROM complaints WHERE complaint_id IN ($ids_placeholder) AND status NOT IN ('resolved', 'rejected', 'closed')";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute($selected_ids);
                    $invalid = $check_stmt->fetchAll();
                    
                    if (!empty($invalid)) {
                        throw new Exception("Cannot delete complaints that are not resolved, rejected, or closed");
                    }
                    
                    // Delete attachments first
                    $query = "DELETE FROM complaint_attachments WHERE complaint_id IN ($ids_placeholder)";
                    $stmt = $db->prepare($query);
                    $stmt->execute($selected_ids);
                    
                    // Delete upvotes
                    $query = "DELETE FROM complaint_upvotes WHERE complaint_id IN ($ids_placeholder)";
                    $stmt = $db->prepare($query);
                    $stmt->execute($selected_ids);
                    
                    // Delete batch members
                    $query = "DELETE FROM batch_members WHERE complaint_id IN ($ids_placeholder)";
                    $stmt = $db->prepare($query);
                    $stmt->execute($selected_ids);
                    
                    // Delete timeline
                    $query = "DELETE FROM complaint_timeline WHERE complaint_id IN ($ids_placeholder)";
                    $stmt = $db->prepare($query);
                    $stmt->execute($selected_ids);
                    
                    // Delete complaints
                    $query = "DELETE FROM complaints WHERE complaint_id IN ($ids_placeholder)";
                    $stmt = $db->prepare($query);
                    $stmt->execute($selected_ids);
                    
                    $message = "Deleted " . count($selected_ids) . " complaints permanently";
                    break;
                    
                case 'export':
                    // For export
                    $export_ids = implode(',', $selected_ids);
                    header("Location: export_complaints.php?ids=" . urlencode($export_ids));
                    exit();
                    
                default:
                    throw new Exception("Invalid bulk action");
            }
            
            $db->commit();
            $message_type = "success";
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get staff list for assignment
$staff_query = "SELECT user_id, full_name, department FROM users WHERE role = 'staff' ORDER BY full_name";
$staff_stmt = $db->query($staff_query);
$staff_members = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Operations - Complaint Management</title>
   <link rel="stylesheet" href="../admin/css/admin.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Bulk Operations</h1>
                <small>Select multiple complaints and perform batch actions</small>
            </div>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Filter Section -->
       <!-- Filter Section -->
<div class="filter-section">
    <form method="GET" id="filterForm">
        <div class="filter-grid">
            <div class="filter-group">
                <label>Status</label>
                <select name="status" id="statusFilter">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                    <option value="under_review" <?php echo $status_filter == 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                    <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Department</label>
                <select name="dept" id="deptFilter">
                    <option value="all" <?php echo $dept_filter == 'all' ? 'selected' : ''; ?>>All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept; ?>" <?php echo $dept_filter == $dept ? 'selected' : ''; ?>>
                            <?php echo $dept; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Priority</label>
                <select name="priority" id="priorityFilter">
                    <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>All Priorities</option>
                    <option value="urgent" <?php echo $priority_filter == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" name="date_from" id="dateFrom" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" name="date_to" id="dateTo" value="<?php echo $date_to; ?>">
            </div>
            
            <div >
                <label>&nbsp;</label>
                <button type="button" id="applyFilters" class="btn btn-primary">Apply Filters</button>
            </div>
            
            <div >
                <label>&nbsp;</label>
                <button type="button" id="clearFilters" class="btn btn-secondary">Clear</button>
            </div>
        </div>
    </form>
</div>



        <!-- Bulk Actions Bar -->
        <form method="POST" id="bulkForm">
            <div class="bulk-actions-bar">
                <span class="selected-count" id="selectedCount">0 selected</span>
                
                <select name="bulk_action" id="bulkAction" required>
                    <option value="">Select Action</option>
                    <option value="update_status">Update Status</option>
                    <option value="update_priority">Update Priority</option>
                    <option value="assign_department">Assign Department</option>
                    <option value="assign_staff">Assign Staff</option>
                    <option value="export">Export Selected</option>
                    <option value="delete" >Delete Selected</option>
                </select>
                
                <div id="statusOptions" >
                    <select name="bulk_status">
                        <option value="">Select Status</option>
                        <option value="submitted">Submitted</option>
                        <option value="under_review">Under Review</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <div id="priorityOptions" >
                    <select name="bulk_priority">
                        <option value="">Select Priority</option>
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                
                <div id="departmentOptions" >
                    <select name="bulk_department">
                        <option value="">Select Department</option>
                        <?php foreach ($all_departments as $dept): ?>
                            <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="staffOptions" >
                    <select name="bulk_staff">
                        <option value="">Select Staff</option>
                        <?php foreach ($staff_members as $staff): ?>
                            <option value="<?php echo $staff['user_id']; ?>">
                                <?php echo htmlspecialchars($staff['full_name']); ?> (<?php echo $staff['department']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn" id="applyBulkAction">Apply to Selected</button>
                
                <div class="export-options">
                    <button type="button" id="exportAllBtn" class="export-btn"> Export All</button>
                </div>
            </div>
            
            <!-- Complaints Table -->
            <div class="table-container">
                <div class="select-all">
                    <input type="checkbox" id="selectAll" onclick="toggleAllComplaints(this)">
                    <label for="selectAll">Select All Complaints</label>
                    <span >Total: <?php echo count($complaints); ?> complaints</span>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-col"></th>
                            <th>ID</th>
                            <th>Complaint #</th>
                            <th>Title</th>
                            <th>Student</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Department</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($complaints)): ?>
                            <tr>
                                <td colspan="10" >
                                    No complaints found matching your filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($complaints as $c): ?>
                                <tr>
                                    <td class="checkbox-col">
                                        <input type="checkbox" name="selected_complaints[]" value="<?php echo $c['complaint_id']; ?>" class="complaint-checkbox" onchange="updateSelectedCount()">
                                    </td>
                                    <td><?php echo $c['complaint_id']; ?></td>
                                    <td><strong>#<?php echo htmlspecialchars($c['complaint_number']); ?></strong></td>
                                    <td >
                                        <a href="../student/view_complaint.php?id=<?php echo $c['complaint_id']; ?>" >
                                            <?php echo htmlspecialchars(substr($c['title'], 0, 50)); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['category_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="priority-badge priority-<?php echo $c['priority']; ?>">
                                            <?php echo strtoupper($c['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $c['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $c['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['department_assigned'] ?? 'Unassigned'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
    
   <script src="../admin/js/admin-core.js"></script>
<script src="../admin/js/complaint-management.js"></script>


</body>
</html>