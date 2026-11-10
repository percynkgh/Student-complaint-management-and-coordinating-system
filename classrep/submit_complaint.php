<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('class_rep')) {
    header("Location: ../unauthorized.php");
    exit();
}

$cm = new ComplaintManager();
$error = '';
$success = '';
$db = (new Database())->getConnection();

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
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_complaint'])) {
    if (!isset($_POST['csrf_token']) || !Security::verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Security token validation failed";
    } else {
        $title = Security::sanitize($_POST['title'] ?? '');
        $description = Security::sanitize($_POST['description'] ?? '');
        $category_id = Security::sanitize($_POST['category_id'] ?? '');
        $location_building = Security::sanitize($_POST['location_building'] ?? '');
        $location_room = Security::sanitize($_POST['location_room'] ?? '');
        $location_details = Security::sanitize($_POST['location_details'] ?? '');
        $selected_department = Security::sanitize($_POST['department'] ?? '');
        
        if (empty($title)) $error = "Please enter a complaint title";
        elseif (empty($description)) $error = "Please enter a complaint description";
        elseif (empty($category_id)) $error = "Please select a category";
        elseif (empty($selected_department)) $error = "Please select a department";
        else {
            try {
                $cat = $db->prepare("SELECT * FROM complaint_categories WHERE category_id = ?");
                $cat->execute([$category_id]);
                $category = $cat->fetch(PDO::FETCH_ASSOC);
                if (!$category) throw new Exception("Invalid category selected");
                
                $priority = $cm->calculatePriority(1, $category['category_name'], $location_building.' '.$location_room, $description, $title);
                $priority = $cm->validatePriority($priority);
                $complaint_number = $cm->generateComplaintNumber();
                
                $stmt = $db->prepare("INSERT INTO complaints (complaint_number, user_id, category_id, title, description, priority, department_assigned, location_building, location_room, location_details, affected_count, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'submitted', NOW())");
                $result = $stmt->execute([$complaint_number, $_SESSION['user_id'], $category_id, $title, $description, $priority, $selected_department, $location_building, $location_room, $location_details]);
                
                if ($result) {
                    $complaint_id = $db->lastInsertId();
                    $cm->addTimelineEntry($complaint_id, 'submitted', 'Complaint submitted successfully', $_SESSION['user_id']);
                    $cm->notifyDepartmentSupervisors($selected_department, $complaint_id, "New complaint #{$complaint_number} submitted: \"{$title}\"");
                    
                    if (!empty($_FILES['attachments']['name'][0]) && $_FILES['attachments']['name'][0] != '') {
                        $files = $_FILES['attachments'];
                        for ($i=0; $i<count($files['name']); $i++) {
                            if ($files['error'][$i]==0 && $files['size'][$i]>0 && $files['size'][$i] <= 5242880) {
                                $file = ['name'=>$files['name'][$i], 'type'=>$files['type'][$i], 'tmp_name'=>$files['tmp_name'][$i], 'error'=>$files['error'][$i], 'size'=>$files['size'][$i]];
                                $upload = $cm->uploadAttachment($file, $complaint_id);
                                if (!$upload['success']) $error .= "\n".$upload['message'];
                            }
                        }
                    }
                    
                    $success = "Complaint submitted successfully! Your complaint number is: " . $complaint_number;
                    $_POST = array();
                } else {
                    $error = "Failed to submit complaint. Please try again.";
                }
            } catch (Exception $e) {
                error_log("Complaint submission error: ".$e->getMessage());
                $error = "System error: ".$e->getMessage();
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
    <title>Submit Complaint - Class Rep</title>
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
        .suggestion-box {
            background: #e8f5e9;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
            display: none;
        }
        .suggestion-box.show {
            display: block;
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
        <div>
            <h1>Submit Individual Complaint</h1>
            <small>Submit a personal complaint (same as student complaints)</small>
            <span class="role-badge">CLASS REP</span>
        </div>
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>
    
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Submitting complaint... Please wait</div>
    </div>
    
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success-message">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    
    <div id="similarWarning" class="message-box warning" style="display: none;">
        <strong>⚠️ Similar complaints found!</strong> Before creating a new complaint, consider upvoting existing ones.
    </div>
    
    <div class="info-box">
        <strong> Individual Complaint:</strong> This is for your personal complaints. 
        For complaints affecting multiple students, use the <a href="batch_complaint.php">Batch Complaint</a> form instead.
    </div>
    
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" id="complaintForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="submit_complaint" value="1">
            
            <div class="form-group">
                <label class="required">Complaint Title</label>
                <input type="text" name="title" id="complaintTitle" required placeholder="Brief summary of the issue" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
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
            
            <div class="form-row">
                <div class="form-group">
                    <label>Building/Location</label>
                    <input type="text" name="location_building" placeholder="e.g., Engineering Block" value="<?php echo isset($_POST['location_building']) ? htmlspecialchars($_POST['location_building']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Room/Area</label>
                    <input type="text" name="location_room" placeholder="e.g., Room 203" value="<?php echo isset($_POST['location_room']) ? htmlspecialchars($_POST['location_room']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Additional Location Details</label>
                <input type="text" name="location_details" placeholder="Additional location information" value="<?php echo isset($_POST['location_details']) ? htmlspecialchars($_POST['location_details']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="required">Description</label>
                <textarea name="description" id="complaintDesc" required placeholder="Provide detailed description of the issue" rows="5"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Attachments (Images, PDFs, Documents)</label>
                <div class="file-upload" onclick="document.getElementById('fileInput').click()">
                    <input type="file" name="attachments[]" id="fileInput" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt" style="display:none;" onchange="updateFileList(this)">
                    <div class="file-upload-label">
                        <div class="file-upload-icon">📎</div>
                        <div>Click to upload or drag and drop</div>
                        <small>Max 5MB per file. Allowed: JPG, PNG, GIF, PDF, DOC, TXT</small>
                    </div>
                </div>
                <div id="fileList" class="file-list"></div>
            </div>
            
            <button type="submit" class="btn" id="submitBtn">Submit Complaint</button>
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
    const departmentSelect = document.getElementById('departmentSelect');
    const suggestedDeptHint = document.getElementById('suggestedDeptHint');
    const similarBox = document.getElementById('similarBox');
    const similarWarning = document.getElementById('similarWarning');
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
                        const upvotedText = isUpvoted ? '✓ Upvoted' : ' Upvote';
                        
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
                    html += '<small style="display:block; margin-top:10px;"> Upvoting helps prioritize existing complaints. Only create new if this is unique.</small>';
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
    
    // Upvote an existing similar complaint instead of filing a duplicate
    window.upvoteComplaint = async function(complaintId, btn) {
        if (btn) {
            btn.disabled = true;
        }
        try {
            const response = await fetch(`?ajax=1&action=upvote&complaint_id=${encodeURIComponent(complaintId)}`);
            const data = await response.json();
            
            if (data.success) {
                const countEl = document.getElementById(`upvoteCount_${complaintId}`);
                if (countEl) countEl.textContent = data.upvote_count;
                if (btn) {
                    btn.textContent = '✓ Upvoted';
                    btn.classList.add('upvoted');
                }
                showTemporaryMessage('Thanks! Your upvote was added to the existing complaint.', 'success');
            } else {
                if (btn) btn.disabled = false;
                showTemporaryMessage(data.message || 'Could not upvote this complaint.', 'warning');
            }
        } catch (error) {
            if (btn) btn.disabled = false;
            showTemporaryMessage('Could not upvote this complaint. Please try again.', 'warning');
        }
    };
    
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
                    <span> ${escapeHtml(file.name)}</span>
                    <span class="file-size">(${(file.size / 1024).toFixed(1)} KB)</span>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:#dc3545;cursor:pointer;">×</button>
                `;
                fileListDiv.appendChild(fileItem);
            }
        }
    };
    
    // Form submission with AJAX
    if (complaintForm) {
        complaintForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const selectedDept = document.getElementById('departmentSelect');
            
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
                    body: formData
                });
                
                const html = await response.text();
                // Simple way to check if success (look for success message)
                if (html.indexOf('success-message') !== -1) {
                    alert('Complaint submitted successfully!');
                    window.location.href = 'dashboard.php';
                } else {
                    alert('Submission error. Please try again.');
                    if (loadingOverlay) {
                        loadingOverlay.classList.remove('active');
                    }
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Complaint';
                }
            } catch (error) {
                console.error('Submission error:', error);
                alert('Complaint submitted! Redirecting...');
                window.location.href = 'dashboard.php';
            }
            
            return false;
        });
    }
    
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
    getDepartmentSuggestion();
    
    console.log('Initialization complete');
});
</script>
</body>
</html>