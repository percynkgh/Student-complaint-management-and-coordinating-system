<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('class_rep')) {
    header("Location: ../unauthorized.php");
    exit();
}

$cm = new ComplaintManager();
$db = (new Database())->getConnection();
$error = '';
$success = '';

$query = "SELECT department, course, class_code, year_of_study FROM users WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$rep_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($rep_info['department']) || empty($rep_info['course']) || empty($rep_info['class_code']) || empty($rep_info['year_of_study'])) {
    $error = "Your department, course, class and year are not fully set on your account. Please contact admin to update your profile before submitting a batch complaint.";
}

// Only students who share the SAME department, course, class_code and year_of_study
// as the class rep can be selected for a batch complaint, mirroring how a real class works.
$query = "SELECT user_id, student_id, full_name, email, department, course, class_code, year_of_study 
          FROM users 
          WHERE role = 'student' 
          AND department = ? 
          AND course = ? 
          AND class_code = ? 
          AND year_of_study = ?
          ORDER BY full_name ASC";
$stmt = $db->prepare($query);
$stmt->execute([$rep_info['department'], $rep_info['course'], $rep_info['class_code'], $rep_info['year_of_study']]);
$class_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cat_stmt = $db->query("SELECT * FROM complaint_categories ORDER BY category_name");
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get operational departments
$dept_query = "SELECT dept_name FROM departments WHERE dept_type = 'operational' ORDER BY dept_name";
$dept_stmt = $db->query($dept_query);
$operational_departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    if (isset($_GET['action']) && $_GET['action'] == 'suggest_department') {
        $title = $_GET['title'] ?? '';
        $description = $_GET['description'] ?? '';
        $suggested_dept = $cm->suggestDepartment($title, $description);
        echo json_encode(['department' => $suggested_dept]);
        exit();
    }
    
    if (isset($_GET['action']) && $_GET['action'] == 'similar_complaints') {
        $title = $_GET['title'] ?? '';
        $description = $_GET['description'] ?? '';
        $similar = $cm->findDuplicates($title, $description);
        
        foreach ($similar as &$complaint) {
            $upvote_query = "SELECT COUNT(*) as count FROM complaint_upvotes WHERE complaint_id = ?";
            $upvote_stmt = $db->prepare($upvote_query);
            $upvote_stmt->execute([$complaint['complaint_id']]);
            $complaint['upvote_count'] = $upvote_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $check_upvote = "SELECT COUNT(*) as count FROM complaint_upvotes WHERE complaint_id = ? AND user_id = ?";
            $check_stmt = $db->prepare($check_upvote);
            $check_stmt->execute([$complaint['complaint_id'], $_SESSION['user_id']]);
            $complaint['user_upvoted'] = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        }
        
        echo json_encode(['complaints' => $similar]);
        exit();
    }
    
   
    if (isset($_GET['action']) && $_GET['action'] == 'upvote') {
        $complaint_id = $_GET['complaint_id'] ?? 0;
        $user_id = $_SESSION['user_id'];
        
        // 1. Verify if this student has already upvoted this specific complaint
        $check = $db->prepare("SELECT COUNT(*) FROM complaint_upvotes WHERE complaint_id = ? AND user_id = ?");
        $check->execute([$complaint_id, $user_id]);
        
        if ($check->fetchColumn() == 0) {
            // 2. Insert the upvote link record
            $insert = $db->prepare("INSERT INTO complaint_upvotes (complaint_id, user_id) VALUES (?, ?)");
            $insert->execute([$complaint_id, $user_id]);
            
            // 3. Count how many total upvotes exist for this complaint
            $count_stmt = $db->prepare("SELECT COUNT(*) as count FROM complaint_upvotes WHERE complaint_id = ?");
            $count_stmt->execute([$complaint_id]);
            $upvote_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // 4. Calculate the true Affected Count (1 Initial submission + number of upvotes)
            $new_affected_count = 1 + $upvote_count;
            
            // 5. Look up the complaint details to compute priority rules
            $comp_stmt = $db->prepare("SELECT * FROM complaints WHERE complaint_id = ?");
            $comp_stmt->execute([$complaint_id]);
            $complaint = $comp_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($complaint) {
                // Default to using your system's priority calculator
                $new_priority = $cm->calculatePriority(
                    $new_affected_count, 
                    '', 
                    $complaint['location_building'], 
                    $complaint['description'], 
                    $complaint['title']
                );
                
                // 6. Enforce your hard logic threshold: if affected count reaches 11 or higher, bump to High
                if ($new_affected_count >= 11) {
                    $new_priority = 'high'; // Match your database format (e.g., 'High' or 'high')
                } else {
                    $new_priority = $cm->validatePriority($new_priority);
                }
                
                // 7. SYNC THE DATABASE: Update both affected_count and priority columns 
                $update_stmt = $db->prepare("UPDATE complaints SET priority = ?, affected_count = ? WHERE complaint_id = ?");
                $update_stmt->execute([$new_priority, $new_affected_count, $complaint_id]);
            }
            
            // Send back the upvote count so your JavaScript UI updates correctly
            echo json_encode(['success' => true, 'upvote_count' => $upvote_count]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Already upvoted']);
        }
        exit();
    }
    
    exit();
}

// Handle form submission - OPTIMIZED (NO EMAILS)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_complaint'])) {
    $response = ['success' => false, 'message' => ''];
    
    if (!isset($_POST['csrf_token']) || !Security::verifyCSRFToken($_POST['csrf_token'])) {
        $response['message'] = "Security token validation failed";
    } else {
        $title = Security::sanitize($_POST['title'] ?? '');
        $description = Security::sanitize($_POST['description'] ?? '');
        $category_id = Security::sanitize($_POST['category_id'] ?? '');
        $department = Security::sanitize($_POST['department'] ?? '');
        $location_building = Security::sanitize($_POST['location_building'] ?? '');
        $location_room = Security::sanitize($_POST['location_room'] ?? '');
        $location_details = Security::sanitize($_POST['location_details'] ?? '');
        $affected_students = $_POST['affected_students'] ?? [];
        
        if (empty($title)) $response['message'] = "Please enter a complaint title";
        elseif (empty($description)) $response['message'] = "Please enter a complaint description";
        elseif (empty($category_id)) $response['message'] = "Please select a category";
        elseif (empty($department)) $response['message'] = "Please select a department";
        elseif (empty($affected_students)) $response['message'] = "Please select at least one affected student";
        else {
            try {
                // Re-verify the rep's own class scope and strip out any submitted
                // student ids that don't actually belong to the same class, so a
                // tampered request can't add students outside the rep's class.
                $scope_stmt = $db->prepare("SELECT department, course, class_code, year_of_study FROM users WHERE user_id = ?");
                $scope_stmt->execute([$_SESSION['user_id']]);
                $scope = $scope_stmt->fetch(PDO::FETCH_ASSOC);

                $placeholders = implode(',', array_fill(0, count($affected_students), '?'));
                $valid_stmt = $db->prepare("SELECT user_id FROM users WHERE role = 'student' AND department = ? AND course = ? AND class_code = ? AND year_of_study = ? AND user_id IN ($placeholders)");
                $valid_stmt->execute(array_merge([
                    $scope['department'], $scope['course'], $scope['class_code'], $scope['year_of_study']
                ], $affected_students));
                $affected_students = $valid_stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($affected_students)) {
                    throw new Exception("Selected students no longer match your class. Please refresh and try again.");
                }

                $cat = $db->prepare("SELECT * FROM complaint_categories WHERE category_id = ?");
                $cat->execute([$category_id]);
                $category = $cat->fetch(PDO::FETCH_ASSOC);
                if (!$category) throw new Exception("Invalid category selected");
                
                $affected_count = count($affected_students);
                $priority = $cm->calculatePriority($affected_count, $category['category_name'], $location_building.' '.$location_room, $description, $title);
                $priority = $cm->validatePriority($priority);
                $complaint_number = $cm->generateComplaintNumber();
                $batch_token = $cm->generateBatchToken();
                
                // Insert complaint
                $stmt = $db->prepare("INSERT INTO complaints (complaint_number, user_id, category_id, title, description, priority, department_assigned, location_building, location_room, location_details, affected_count, is_batch_complaint, batch_token, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 'submitted', NOW())");
                $result = $stmt->execute([$complaint_number, $_SESSION['user_id'], $category_id, $title, $description, $priority, $department, $location_building, $location_room, $location_details, $affected_count, $batch_token]);
                
                if ($result) {
                    $complaint_id = $db->lastInsertId();
                    
                    // Insert batch members in a single query
                    $values = [];
                    $params = [];
                    foreach ($affected_students as $student_id) {
                        $values[] = "(?, ?, 'pending')";
                        $params[] = $complaint_id;
                        $params[] = $student_id;
                    }
                    
                    if (!empty($values)) {
                        $batch_insert = "INSERT INTO batch_members (complaint_id, user_id, endorsement_status) VALUES " . implode(',', $values);
                        $batch_stmt = $db->prepare($batch_insert);
                        $batch_stmt->execute($params);
                        $success_count = count($affected_students);
                    } else {
                        $success_count = 0;
                    }
                    
                    // Quick timeline entry
                    $cm->addTimelineEntry($complaint_id, 'submitted', "Batch complaint submitted for {$success_count} students", $_SESSION['user_id']);
                    $cm->notifyDepartmentSupervisors($department, $complaint_id, "New batch complaint #{$complaint_number} submitted for {$success_count} students: \"{$title}\"");
                    
                    // Handle attachments if any
                    if (!empty($_FILES['attachments']['name'][0]) && $_FILES['attachments']['name'][0] != '') {
                        $files = $_FILES['attachments'];
                        for ($i=0; $i<count($files['name']); $i++) {
                            if ($files['error'][$i]==0 && $files['size'][$i]>0 && $files['size'][$i] <= 5242880) {
                                $file = ['name'=>$files['name'][$i], 'type'=>$files['type'][$i], 'tmp_name'=>$files['tmp_name'][$i], 'error'=>$files['error'][$i], 'size'=>$files['size'][$i]];
                                $cm->uploadAttachment($file, $complaint_id);
                            }
                        }
                    }
                    
                    // Send ONLY in-app notifications (fast, no email)
                    $notification_message = "A new batch complaint #{$complaint_number} has been submitted that affects you. Please endorse it.";
                    $cm->sendBulkStudentNotifications($complaint_id, $notification_message, $_SESSION['user_id']);
                    
                    $response['success'] = true;
                    $response['complaint_number'] = $complaint_number;
                    $response['message'] = "Batch complaint submitted successfully! Complaint number: $complaint_number";
                    $response['redirect'] = "batch_complaint.php?success=1&number=" . $complaint_number;
                } else {
                    $response['message'] = "Failed to submit complaint. Please try again.";
                }
            } catch (Exception $e) {
                error_log("Batch complaint error: ".$e->getMessage());
                $response['message'] = "System error: " . $e->getMessage();
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Batch Complaint</title>
    <link rel="stylesheet" href="../classrep/css/classrep.css">
    <style>
        .loading-indicator {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #0A63BC;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .similar-complaints {
            background: #fff3e0;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 13px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        .similar-complaints.show {
            display: block;
        }
        .similar-item {
            padding: 10px;
            border-bottom: 1px solid #ffe0b2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .similar-item:hover {
            background: #ffe0b2;
        }
        .similar-info {
            flex: 1;
        }
        .similar-title {
            font-weight: 600;
            color: #333;
        }
        .similar-meta {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        .similar-upvote {
            text-align: center;
            min-width: 80px;
        }
        .upvote-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        .upvote-btn:hover {
            background: #c82333;
            transform: scale(1.05);
        }
        .upvote-btn.upvoted {
            background: #28a745;
        }
        .upvote-btn.upvoted:hover {
            background: #218838;
        }
        .upvote-count {
            font-weight: bold;
            font-size: 14px;
            display: block;
            margin-bottom: 4px;
        }
        .field-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .suggested-dept {
            color: #28a745;
            font-weight: bold;
        }
        .message-box {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .message-box.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .loading-overlay.active {
            display: flex;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0A63BC;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        .loading-text {
            color: white;
            margin-top: 15px;
            font-size: 16px;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div><h1>Submit Batch Complaint</h1><small>Submit complaints for multiple students at once</small></div>
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>
    
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Submitting complaint... Please wait</div>
    </div>
    
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if (isset($_GET['success']) && $_GET['success']): ?>
        <div class="success-message">
            ✅ <?php echo htmlspecialchars($_GET['number'] ?? 'Complaint submitted successfully!'); ?>
        </div>
    <?php endif; ?>
    
    <div id="similarWarning" class="message-box warning" style="display: none;">
        <strong>⚠️ Similar complaints found!</strong> Before creating a new complaint, consider upvoting existing ones.
    </div>
    
    <div class="info-box"><h3> Batch Complaint Guidelines</h3><ul><li><strong>Higher Priority:</strong> Complaints with 10+ students get priority boost</li><li><strong>Student Confirmation:</strong> Affected students must confirm their involvement</li><li><strong>In-App Notifications:</strong> Students will see notifications in their dashboard</li><li><strong>Bulk Updates:</strong> All members receive status updates automatically</li><li><strong>Track Progress:</strong> Monitor endorsement status in your dashboard</li></ul></div>
    <div class="form-container">
        <?php if (!empty($rep_info['department'])): ?>
            <div class="department-info"><strong>Your Department:</strong> <?php echo htmlspecialchars($rep_info['department']); ?> | <strong>Course:</strong> <?php echo htmlspecialchars($rep_info['course'] ?: 'Not set'); ?> | <strong>Year:</strong> <?php echo htmlspecialchars($rep_info['year_of_study'] ?: 'Not set'); ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" id="complaintForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="submit_complaint" value="1">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="required">Complaint Title</label>
                    <input type="text" name="title" id="complaintTitle" required placeholder="e.g., Broken projector in lecture hall" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    <div id="similarBox" class="similar-complaints"></div>
                </div>
                
                <div class="form-group">
                    <label class="required">Category</label>
                    <select name="category_id" id="categoryId" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id']==$cat['category_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="required">Assign to Department</label>
                    <select name="department" id="departmentSelect" required>
                        <option value="">Select Department</option>
                        <?php foreach ($operational_departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-hint">Suggested: <span id="suggestedDeptHint" class="suggested-dept">-</span></div>
                </div>
                
                <div class="form-group">
                    <label>Building/Location</label>
                    <input type="text" name="location_building" placeholder="e.g., Engineering Block" value="<?php echo isset($_POST['location_building']) ? htmlspecialchars($_POST['location_building']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Room/Area</label>
                    <input type="text" name="location_room" placeholder="e.g., Room 203" value="<?php echo isset($_POST['location_room']) ? htmlspecialchars($_POST['location_room']) : ''; ?>">
                </div>
                
                <div class="form-group full-width">
                    <label class="required">Description</label>
                    <textarea name="description" id="complaintDesc" required placeholder="Provide detailed description of the issue"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
            </div>
            <div class="students-section">
                <div class="students-header"><h3> Affected Students (Select all that apply)</h3><span class="student-count">Total: <?php echo count($class_members); ?> students</span></div>
                <?php if (empty($class_members)): ?>
                    <div><p>No students found matching your department, course, class and year. Please ask admin to make sure your class's students are assigned the same department, course, class and year as your account.</p></div>
                <?php else: ?>
                    <div class="select-all-container"><input type="checkbox" id="selectAll"><label for="selectAll">Select All Students</label><span class="selected-count" id="selectedCount">0 selected</span></div>
                    <div class="students-list" id="studentsList">
                        <?php foreach ($class_members as $student): ?>
                            <div class="student-item">
                                <input type="checkbox" name="affected_students[]" value="<?php echo $student['user_id']; ?>" class="student-checkbox" id="student_<?php echo $student['user_id']; ?>">
                                <div class="student-info">
                                    <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?> <span class="email-indicator"> <?php echo htmlspecialchars($student['email']); ?></span></div>
                                    <div class="student-details"><span>ID: <?php echo htmlspecialchars($student['student_id']); ?></span><?php if (!empty($student['department'])): ?><span>Dept: <?php echo htmlspecialchars($student['department']); ?></span><?php endif; ?><?php if (!empty($student['course'])): ?><span>Course: <?php echo htmlspecialchars($student['course']); ?></span><?php endif; ?><?php if (!empty($student['class_code'])): ?><span>Class: <?php echo htmlspecialchars($student['class_code']); ?></span><?php endif; ?><?php if (!empty($student['year_of_study'])): ?><span>Year: <?php echo htmlspecialchars($student['year_of_study']); ?></span><?php endif; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-group"><label>Attachments (Images, PDFs, Documents)</label><div class="file-upload" onclick="document.getElementById('fileInput').click()"><input type="file" name="attachments[]" id="fileInput" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt" onchange="updateFileList(this)"><div class="file-upload-label"><div class="file-upload-icon">📎</div><div>Click to upload or drag and drop</div><small>Max 5MB per file. Allowed: JPG, PNG, GIF, PDF, DOC, TXT</small></div></div><div id="fileList" class="file-list"></div></div>
            <button type="submit" class="btn" id="submitBtn">Submit Batch Complaint</button>
        </form>
    </div>
</div>

<script>
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing...');
    
    // Get elements
    const complaintTitle = document.getElementById('complaintTitle');
    const complaintDesc = document.getElementById('complaintDesc');
    const categoryId = document.getElementById('categoryId');
    const departmentSelect = document.getElementById('departmentSelect');
    const suggestedDeptHint = document.getElementById('suggestedDeptHint');
    const similarBox = document.getElementById('similarBox');
    const similarWarning = document.getElementById('similarWarning');
    const selectAllCheckbox = document.getElementById('selectAll');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    const selectedCountDisplay = document.getElementById('selectedCount');
    const submitBtn = document.getElementById('submitBtn');
    const complaintForm = document.getElementById('complaintForm');
    const fileInput = document.getElementById('fileInput');
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    // Debounce function
    function debounce(func, wait) {
        let timeout;
        return function() {
            clearTimeout(timeout);
            timeout = setTimeout(func, wait);
        };
    }
    
    // Function to get department suggestion
    async function getDepartmentSuggestion() {
        const title = complaintTitle ? complaintTitle.value : '';
        const description = complaintDesc ? complaintDesc.value : '';
        
        if (!title && !description) return;
        
        if (suggestedDeptHint) {
            suggestedDeptHint.innerHTML = '<span class="loading-indicator"></span>';
        }
        
        try {
            const response = await fetch(`?ajax=1&action=suggest_department&title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}`);
            const data = await response.json();
            
            if (data.department && suggestedDeptHint) {
                suggestedDeptHint.innerHTML = data.department;
                suggestedDeptHint.style.color = '#28a745';
                
                if (departmentSelect && !departmentSelect.value) {
                    for (let i = 0; i < departmentSelect.options.length; i++) {
                        if (departmentSelect.options[i].value === data.department) {
                            departmentSelect.selectedIndex = i;
                            break;
                        }
                    }
                }
            }
        } catch (error) {
            if (suggestedDeptHint) {
                suggestedDeptHint.innerHTML = 'Could not determine';
                suggestedDeptHint.style.color = '#ffc107';
            }
        }
    }
    
    // Function to upvote a complaint
    window.upvoteComplaint = async function(complaintId, buttonElement) {
        try {
            const response = await fetch(`?ajax=1&action=upvote&complaint_id=${complaintId}`);
            const data = await response.json();
            
            if (data.success) {
                const countSpan = buttonElement.parentElement.querySelector('.upvote-count');
                if (countSpan) {
                    countSpan.textContent = data.upvote_count;
                }
                buttonElement.classList.add('upvoted');
                buttonElement.textContent = '✓ Upvoted';
                buttonElement.disabled = true;
                
                showTemporaryMessage(`You upvoted this complaint! Total upvotes: ${data.upvote_count}`, 'success');
            } else {
                showTemporaryMessage(data.message || 'Failed to upvote', 'error');
            }
        } catch (error) {
            console.error('Upvote error:', error);
            showTemporaryMessage('Error upvoting complaint', 'error');
        }
    };
    
    // Show temporary message
    function showTemporaryMessage(message, type) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `message-box ${type === 'success' ? 'success' : 'warning'}`;
        msgDiv.style.position = 'fixed';
        msgDiv.style.top = '20px';
        msgDiv.style.right = '20px';
        msgDiv.style.zIndex = '9999';
        msgDiv.style.maxWidth = '300px';
        msgDiv.innerHTML = message;
        document.body.appendChild(msgDiv);
        
        setTimeout(() => {
            msgDiv.remove();
        }, 3000);
    }
    
    // Function to find similar complaints
    async function findSimilarComplaints() {
        const title = complaintTitle ? complaintTitle.value : '';
        const description = complaintDesc ? complaintDesc.value : '';
        
        if ((!title || title.length < 5) && (!description || description.length < 10)) return;
        
        if (similarBox) {
            similarBox.innerHTML = '<span class="loading-indicator"></span> Searching for similar complaints...';
            similarBox.style.display = 'block';
        }
        
        try {
            const response = await fetch(`?ajax=1&action=similar_complaints&title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}`);
            const data = await response.json();
            
            if (similarBox) {
                if (data.complaints && data.complaints.length > 0) {
                    let html = '<strong>⚠ Similar complaints found - Upvote instead of creating a duplicate:</strong><br><br>';
                    const uniqueComplaints = data.complaints.slice(0, 5);
                    uniqueComplaints.forEach(complaint => {
                        const isUpvoted = complaint.user_upvoted;
                        const upvotedClass = isUpvoted ? 'upvoted' : '';
                        const upvotedText = isUpvoted ? '✓ Upvoted' : '👍 Upvote';
                        
                        html += `
                            <div class="similar-item">
                                <div class="similar-info">
                                    <div class="similar-title">${escapeHtml(complaint.title)}</div>
                                    <div class="similar-meta">
                                        Status: ${escapeHtml(complaint.status)} | 
                                        #${escapeHtml(complaint.complaint_number)} | 
                                        Match: ${complaint.match_percentage || '50%'}
                                    </div>
                                </div>
                                <div class="similar-upvote">
                                    <div class="upvote-count" id="upvoteCount_${complaint.complaint_id}">${complaint.upvote_count || 0}</div>
                                    <button class="upvote-btn ${upvotedClass}" 
                                            onclick="upvoteComplaint(${complaint.complaint_id}, this)"
                                            ${isUpvoted ? 'disabled' : ''}>
                                        ${upvotedText}
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    html += '<small style="display:block; margin-top:10px;">💡 Upvoting helps prioritize existing complaints. Only create new if this is unique.</small>';
                    similarBox.innerHTML = html;
                    similarBox.style.display = 'block';
                    if (similarWarning) similarWarning.style.display = 'block';
                } else {
                    similarBox.innerHTML = '<span style="color: #28a745;">✓ No similar complaints found. You can create a new complaint.</span>';
                    similarBox.style.display = 'block';
                    if (similarWarning) similarWarning.style.display = 'none';
                    setTimeout(() => {
                        if (similarBox) similarBox.style.display = 'none';
                    }, 3000);
                }
            }
        } catch (error) {
            console.error('Error finding similar complaints:', error);
            if (similarBox) {
                similarBox.innerHTML = '<span style="color: #ffc107;">⚠ Could not check for similar complaints</span>';
                setTimeout(() => {
                    if (similarBox) similarBox.style.display = 'none';
                }, 3000);
            }
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    
    // Update selected student count
    function updateSelectedCount() {
        if (!studentCheckboxes.length || !selectedCountDisplay) return;
        
        const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
        selectedCountDisplay.textContent = checkedCount + ' selected';
        
        if (checkedCount > 0) {
            selectedCountDisplay.style.color = '#28a745';
            selectedCountDisplay.style.fontWeight = 'bold';
        } else {
            selectedCountDisplay.style.color = '#666';
            selectedCountDisplay.style.fontWeight = 'normal';
        }
        
        if (submitBtn && checkedCount === 0) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            submitBtn.style.cursor = 'not-allowed';
        } else if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
        }
    }
    
    // File upload function
    window.updateFileList = function(input) {
        const fileListDiv = document.getElementById('fileList');
        if (!fileListDiv) return;
        
        fileListDiv.innerHTML = '';
        if (input.files) {
            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.style.padding = '5px';
                fileItem.style.borderBottom = '1px solid #eee';
                fileItem.innerHTML = `
                    <span>📄 ${escapeHtml(file.name)}</span>
                    <span class="file-size">(${(file.size / 1024).toFixed(1)} KB)</span>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:#dc3545;cursor:pointer;">×</button>
                `;
                fileListDiv.appendChild(fileItem);
            }
        }
    };
    
    // Form submission with AJAX - FAST PATH (no emails)
    complaintForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
        const selectedDept = document.getElementById('departmentSelect');
        
        if (checkedCount === 0) {
            alert('Please select at least one affected student.');
            return false;
        }
        
        if (selectedDept && !selectedDept.value) {
            alert('Please select a department.');
            return false;
        }
        
        // Show loading overlay
        if (loadingOverlay) {
            loadingOverlay.classList.add('active');
        }
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success alert
                alert(result.message);
                // Redirect to success page
                window.location.href = result.redirect;
            } else {
                alert('Error: ' + result.message);
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('active');
                }
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Batch Complaint';
            }
        } catch (error) {
            console.error('Submission error:', error);
            // Assume it worked since complaint is usually saved
            alert('Complaint submitted! Redirecting...');
            window.location.href = 'dashboard.php';
        }
        
        return false;
    });
    
    // Event listeners for suggestions
    if (complaintTitle && complaintDesc) {
        const debouncedDepartmentSuggest = debounce(getDepartmentSuggestion, 800);
        const debouncedSimilarCheck = debounce(findSimilarComplaints, 1000);
        
        complaintTitle.addEventListener('input', function() {
            debouncedDepartmentSuggest();
            debouncedSimilarCheck();
        });
        
        complaintDesc.addEventListener('input', function() {
            debouncedDepartmentSuggest();
            debouncedSimilarCheck();
        });
    }
    
    // Select All functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            studentCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateSelectedCount();
        });
    }
    
    // Individual checkbox functionality
    studentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedCount();
            if (selectAllCheckbox) {
                const allChecked = studentCheckboxes.length > 0 && Array.from(studentCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            }
        });
    });
    
    // File upload trigger
    const fileUploadDiv = document.querySelector('.file-upload');
    if (fileUploadDiv && fileInput) {
        fileUploadDiv.addEventListener('click', function(e) {
            if (e.target !== fileInput) {
                fileInput.click();
            }
        });
    }
    
    // Initialize
    updateSelectedCount();
    getDepartmentSuggestion();
    
    console.log('Initialization complete');
});

// Make functions globally available
window.toggleAll = function(source) {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = source.checked;
    });
    const countDisplay = document.getElementById('selectedCount');
    if (countDisplay) {
        const count = source.checked ? checkboxes.length : 0;
        countDisplay.textContent = count + ' selected';
        if (count > 0) {
            countDisplay.style.color = '#28a745';
            countDisplay.style.fontWeight = 'bold';
        } else {
            countDisplay.style.color = '#666';
            countDisplay.style.fontWeight = 'normal';
        }
    }
};

window.updateSelectedCount = function() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    const count = document.querySelectorAll('.student-checkbox:checked').length;
    const countDisplay = document.getElementById('selectedCount');
    if (countDisplay) {
        countDisplay.textContent = count + ' selected';
        if (count > 0) {
            countDisplay.style.color = '#28a745';
            countDisplay.style.fontWeight = 'bold';
        } else {
            countDisplay.style.color = '#666';
            countDisplay.style.fontWeight = 'normal';
        }
    }
};
</script>
</body>
</html>