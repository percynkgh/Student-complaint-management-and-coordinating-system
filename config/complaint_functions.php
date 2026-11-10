<?php
// config/complaint_functions.php
require_once 'database.php';
require_once 'security.php';

if (!function_exists('formatDuration')) {
    /**
     * Turns a decimal-hours average (e.g. from AVG(TIMESTAMPDIFF(HOUR, ...)))
     * into a plain-English duration like "45 min" or "2h 15m", instead of
     * misleadingly showing "0" or "0.3" hrs for anything resolved in under
     * an hour. Used everywhere average resolution time is displayed.
     */
    function formatDuration($hours) {
        if ($hours === null || $hours === '') return 'N/A';
        $totalMinutes = (int) round(((float) $hours) * 60);
        if ($totalMinutes < 1) return '<1 min';
        if ($totalMinutes < 60) return $totalMinutes . ' min';
        $h = intdiv($totalMinutes, 60);
        $m = $totalMinutes % 60;
        return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
    }
}

class ComplaintManager {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * Generate unique complaint number with error handling
     */
    public function generateComplaintNumber() {
        $year = date('Y');
        
        try {
            // Check if table exists and has data
            $query = "SELECT COUNT(*) as count FROM complaints WHERE complaint_number LIKE 'CMP-$year-%'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $count = ($result && isset($result['count'])) ? $result['count'] + 1 : 1;
            $complaint_number = 'CMP-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            
            // Verify it's unique
            $check_query = "SELECT complaint_id FROM complaints WHERE complaint_number = ?";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->execute([$complaint_number]);
            
            // If it exists, increment until unique
            while ($check_stmt->rowCount() > 0) {
                $count++;
                $complaint_number = 'CMP-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
                $check_stmt->execute([$complaint_number]);
            }
            
            return $complaint_number;
            
        } catch (PDOException $e) {
            // Fallback if table is empty or doesn't exist
            error_log("Error generating complaint number: " . $e->getMessage());
            return 'CMP-' . $year . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }
    }
    
  /**
 * Smart department assignment based on keywords - for students
 */
public function suggestDepartment($title, $description) {
    $text = strtolower($title . ' ' . $description);
    
    // Enhanced keyword database with categories
    $keywords = [
        'Information Technology' => [
            'computer', 'laptop', 'desktop', 'pc', 'internet', 'wifi', 'wi-fi', 'network', 
            'connection', 'email', 'outlook', 'gmail', 'login', 'password', 'account', 
            'software', 'hardware', 'printer', 'projector', 'scanner', 'server', 'system',
            'application', 'app', 'website', 'portal', 'online', 'virtual', 'tech', 'technical',
            'it', 'helpdesk', 'mouse', 'keyboard', 'monitor', 'screen', 'display', 'usb',
            'charging', 'battery', 'power cord', 'cable', 'hdmi', 'vga', 'adapter'
        ],
        'Maintenance' => [
            'broken', 'damaged', 'cracked', 'not working', 'doesn\'t work', 'not functioning',
            'repair', 'fix', 'replace', 'leak', 'leaking', 'water', 'pipe', 'plumbing',
            'toilet', 'bathroom', 'restroom', 'sink', 'faucet', 'light', 'bulb', 'lighting',
            'fan', 'ceiling fan', 'ac', 'air conditioner', 'aircon', 'cooling', 'heating',
            'temperature', 'door', 'window', 'lock', 'handle', 'chair', 'desk', 'table',
            'furniture', 'cabinet', 'paint', 'painting', 'wall', 'floor', 'carpet', 'tile',
            'roof', 'ceiling', 'electrical', 'power', 'socket', 'switch', 'outlet',
            'stuck', 'jammed', 'loose', 'wobbly', 'squeaky', 'noise', 'smell', 'odor'
        ],
        'Finance' => [
            'fee', 'fees', 'tuition', 'payment', 'pay', 'paid', 'receipt', 'invoice',
            'bill', 'money', 'refund', 'reimbursement', 'bank', 'account', 'financial aid',
            'scholarship', 'grant', 'loan', 'bursary', 'allowance', 'stipend', 'expense',
            'cost', 'charge', 'fine', 'penalty', 'balance', 'due', 'overdue', 'statement',
            'transaction', 'credit', 'debit', 'finance', 'financial', 'fund', 'funding',
            'cash', 'cheque', 'check', 'banking', 'money transfer', 'eft'
        ],
        'Registry' => [
            'registration', 'enroll', 'enrollment', 'admission', 'course', 'class',
            'subject', 'schedule', 'timetable', 'exam', 'examination', 'test', 'quiz',
            'result', 'grade', 'score', 'transcript', 'certificate', 'diploma', 'degree',
            'academic', 'record', 'student id', 'id card', 'matric', 'matriculation',
            'graduation', 'graduate', 'convocation', 'semester', 'term', 'academic year',
            'drop', 'add', 'withdraw', 'prerequisite', 'credit', 'unit', 'module',
            'lecturer', 'professor', 'instructor', 'tutor', 'supervisor'
        ],
        'Student Affairs' => [
            'harassment', 'bully', 'bullying', 'discrimination', 'sexual', 'assault',
            'abuse', 'safety', 'security', 'unsafe', 'threat', 'threatening', 'counseling',
            'mental health', 'stress', 'anxiety', 'depression', 'health', 'medical',
            'sick', 'illness', 'hospital', 'clinic', 'doctor', 'nurse', 'hostel',
            'dorm', 'dormitory', 'housing', 'accommodation', 'roommate', 'welfare',
            'wellbeing', 'discipline', 'misconduct', 'behavior', 'code of conduct',
            'student life', 'orientation', 'club', 'society', 'sports', 'athletics',
            'food', 'cafeteria', 'dining', 'meal', 'hunger', 'accommodation'
        ]
    ];
    
    $scores = [];
    $matches = [];
    
    // Calculate scores for each department
    foreach ($keywords as $dept => $dept_keywords) {
        $score = 0;
        $matched_words = [];
        
        foreach ($dept_keywords as $keyword) {
            // Check if keyword exists in text
            if (strpos($text, $keyword) !== false) {
                // Weight based on keyword length and position
                $weight = 1;
                if (strlen($keyword) > 6) $weight = 2; // Longer words are more specific
                if (strpos($title, $keyword) !== false) $weight *= 2; // Words in title are more important
                
                $score += $weight;
                $matched_words[] = $keyword;
            }
        }
        
        if ($score > 0) {
            $scores[$dept] = $score;
            $matches[$dept] = $matched_words;
        }
    }
    
    // If no matches, return default
    if (empty($scores)) {
        return 'Student Affairs'; // Default
    }
    
    // Get department with highest score
    arsort($scores);
    $top_dept = key($scores);
    
    // Log for debugging
    error_log("Department suggestion for student: $top_dept (score: {$scores[$top_dept]})");
    error_log("Matched keywords: " . implode(', ', array_slice($matches[$top_dept], 0, 5)));
    
    return $top_dept;
}
   
    /**
     * Fixed duplicate detection - includes user's own complaints
     */
    public function findDuplicates($title, $description, $user_id = null) {
        $duplicates = [];
        
        try {
            // Get the first word from title
            $words = explode(' ', trim($title));
            $primary_keyword = $words[0];
            
            error_log("FINDING DUPLICATES for keyword: " . $primary_keyword);
            
            // Simple direct query - DO NOT exclude user's own complaints
            $query = "SELECT c.*, u.full_name, u.student_id,
                      (SELECT COUNT(*) FROM complaint_upvotes WHERE complaint_id = c.complaint_id) as upvote_count
                      FROM complaints c
                      JOIN users u ON c.user_id = u.user_id
                      WHERE c.title LIKE ?
                      AND c.status NOT IN ('resolved', 'closed', 'rejected')
                      AND c.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                      ORDER BY c.created_at DESC
                      LIMIT 10";
            
            $params = ["%$primary_keyword%"];
            
            error_log("Executing query: " . $query);
            error_log("With param: " . $params[0]);
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Found " . count($results) . " results");
            
            // Add all results as duplicates
            foreach ($results as $result) {
                // Mark if it's the user's own complaint
                $is_own = ($user_id && $result['user_id'] == $user_id);
                
                $result['match_percentage'] = '100%';
                $result['relevance'] = 1.0;
                $result['matched_keywords'] = $primary_keyword;
                $result['is_your_complaint'] = $is_own;
                $duplicates[] = $result;
                
                error_log("Added: ID {$result['complaint_id']} - {$result['title']}" . ($is_own ? " (your complaint)" : ""));
            }
            
            // If no title matches, try description
            if (empty($duplicates)) {
                error_log("No title matches, trying description");
                
                $query = "SELECT c.*, u.full_name, u.student_id,
                          (SELECT COUNT(*) FROM complaint_upvotes WHERE complaint_id = c.complaint_id) as upvote_count
                          FROM complaints c
                          JOIN users u ON c.user_id = u.user_id
                          WHERE c.description LIKE ?
                          AND c.status NOT IN ('resolved', 'closed', 'rejected')
                          AND c.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                          ORDER BY c.created_at DESC
                          LIMIT 10";
                
                $stmt = $this->conn->prepare($query);
                $stmt->execute(["%$primary_keyword%"]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $result) {
                    $is_own = ($user_id && $result['user_id'] == $user_id);
                    
                    $result['match_percentage'] = '80%';
                    $result['relevance'] = 0.8;
                    $result['matched_keywords'] = $primary_keyword . ' (in description)';
                    $result['is_your_complaint'] = $is_own;
                    $duplicates[] = $result;
                }
            }
            
            // Sort by relevance (highest first)
            usort($duplicates, function($a, $b) {
                return $b['relevance'] <=> $a['relevance'];
            });
            
            error_log("Returning " . count($duplicates) . " duplicates");
            
        } catch (PDOException $e) {
            error_log("DUPLICATE ERROR: " . $e->getMessage());
        }
        
        return array_slice($duplicates, 0, 5);
    }
    
    /**
     * Calculate priority based on keywords - SIMPLIFIED AND FIXED
     */
    public function calculatePriority($affected_count, $category, $location, $description, $title = '') {
        $priority_score = 0;
        
        // Combine title and description for checking
        $text = strtolower($title . ' ' . $description . ' ' . $location);
        
        // Log what we're checking
        error_log("========== PRIORITY CALCULATION START ==========");
        error_log("Title: " . $title);
        error_log("Description: " . $description);
        error_log("Location: " . $location);
        error_log("Category: " . $category);
        error_log("Affected count: " . $affected_count);
        
        // DIRECT KEYWORD CHECKS - Immediate priority boost for important words
        
        // URGENT keywords (should force urgent priority)
        $urgent_triggers = ['emergency', 'urgent', 'critical', 'immediately', 'asap'];
        foreach ($urgent_triggers as $keyword) {
            if (strpos($text, $keyword) !== false) {
                error_log("✓ Found URGENT keyword: '$keyword'");
                $priority_score += 50; // High enough to guarantee urgent
            }
        }
        
        // HIGH priority keywords
        $high_triggers = ['danger', 'safety', 'fire', 'accident', 'injured', 'flood', 'security'];
        foreach ($high_triggers as $keyword) {
            if (strpos($text, $keyword) !== false) {
                error_log("✓ Found HIGH keyword: '$keyword'");
                $priority_score += 30;
            }
        }
        
        // MEDIUM priority keywords
        $medium_triggers = ['broken', 'not working', 'repair', 'fix', 'issue', 'problem', 'down', 'outage'];
        foreach ($medium_triggers as $keyword) {
            if (strpos($text, $keyword) !== false) {
                error_log("✓ Found MEDIUM keyword: '$keyword'");
                $priority_score += 15;
            }
        }
        
        // Factor 1: Number of affected students
        if ($affected_count >= 50) {
            $priority_score += 30;
            error_log("✓ Large group (50+): +30");
        } elseif ($affected_count >= 20) {
            $priority_score += 20;
            error_log("✓ Medium group (20+): +20");
        } elseif ($affected_count >= 10) {
            $priority_score += 10;
            error_log("✓ Small group (10+): +10");
        } elseif ($affected_count > 1) {
            $priority_score += 5;
            error_log("✓ Multiple students: +5");
        }
        
        // Factor 2: Category-based priority
        $high_priority_cats = ['Security', 'Harassment', 'Health', 'Safety'];
        $medium_priority_cats = ['IT Issues', 'Maintenance', 'Facilities', 'Academic'];
        
        foreach ($high_priority_cats as $high_cat) {
            if (stripos($category, $high_cat) !== false) {
                $priority_score += 20;
                error_log("✓ High priority category: +20");
                break;
            }
        }
        
        foreach ($medium_priority_cats as $med_cat) {
            if (stripos($category, $med_cat) !== false) {
                $priority_score += 10;
                error_log("✓ Medium priority category: +10");
                break;
            }
        }
        
        // Factor 3: Location (common areas get higher priority)
        $common_areas = ['library', 'cafeteria', 'lecture hall', 'lab', 'bathroom', 'toilet', 'entrance', 'staircase', 'classroom'];
        $loc_lower = strtolower($location);
        foreach ($common_areas as $area) {
            if (strpos($loc_lower, $area) !== false) {
                $priority_score += 5;
                error_log("✓ Common area '$area': +5");
                break;
            }
        }
        
        // Determine priority level with clear thresholds
        $final_priority = 'low';
        
        // Force urgent if 'emergency' or 'urgent' is found, regardless of score
        if (strpos($text, 'emergency') !== false || strpos($text, 'urgent') !== false) {
            $final_priority = 'urgent';
            error_log("⚡ FORCE URGENT: 'emergency' or 'urgent' keyword found");
        } elseif ($priority_score >= 50) {
            $final_priority = 'urgent';
        } elseif ($priority_score >= 30) {
            $final_priority = 'high';
        } elseif ($priority_score >= 15) {
            $final_priority = 'medium';
        } else {
            $final_priority = 'low';
        }
        
        error_log("PRIORITY RESULT - Total score: $priority_score, Final priority: $final_priority");
        error_log("========== PRIORITY CALCULATION END ==========");
        
        return $final_priority;
    }
    
    /**
     * Add entry to complaint timeline
     */
    public function addTimelineEntry($complaint_id, $status, $description, $updated_by = null, $is_auto = false) {
        try {
            $query = "INSERT INTO complaint_timeline (complaint_id, status, description, updated_by, is_auto_update) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$complaint_id, $status, $description, $updated_by, $is_auto]);
        } catch (PDOException $e) {
            error_log("Failed to add timeline entry: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle file upload with better error handling
     */
    public function uploadAttachment($file, $complaint_id) {
        // Ensure upload directory exists
        $base_upload_dir = dirname(dirname(__FILE__)) . '/uploads/complaints/';
        $target_dir = $base_upload_dir . $complaint_id . '/';
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                return ['success' => false, 'message' => 'Failed to create upload directory'];
            }
        }
        
        // Generate safe filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safe_filename = time() . '_' . uniqid() . '.' . $extension;
        $target_file = $target_dir . $safe_filename;
        
        // Check file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $max_size) {
            return ['success' => false, 'message' => 'File too large (max 5MB)'];
        }
        
        // Check file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx'];
        if (!in_array($extension, $allowed_types)) {
            return ['success' => false, 'message' => 'File type not allowed. Allowed: ' . implode(', ', $allowed_types)];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $error_message = isset($upload_errors[$file['error']]) ? 
                $upload_errors[$file['error']] : 'Unknown upload error';
            return ['success' => false, 'message' => 'Upload error: ' . $error_message];
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            // Save to database with relative path
            $relative_path = 'uploads/complaints/' . $complaint_id . '/' . $safe_filename;
            
            try {
                $query = "INSERT INTO complaint_attachments (complaint_id, file_name, file_path, file_size, file_type) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([
                    $complaint_id, 
                    $safe_filename, 
                    $relative_path, 
                    $file['size'], 
                    $extension
                ]);
                
                return ['success' => true, 'path' => $relative_path, 'filename' => $safe_filename];
            } catch (PDOException $e) {
                // Delete file if database insert fails
                unlink($target_file);
                error_log("Database insert failed for attachment: " . $e->getMessage());
                return ['success' => false, 'message' => 'Failed to save file information to database'];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to move uploaded file. Check directory permissions.'];
        }
    }
    
    /**
     * Generate batch token for group complaints
     */
    public function generateBatchToken() {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Get priority color class for display
     */
    public function getPriorityClass($priority) {
        switch(strtolower($priority)) {
            case 'urgent':
                return 'priority-urgent';
            case 'high':
                return 'priority-high';
            case 'medium':
                return 'priority-medium';
            case 'low':
                return 'priority-low';
            default:
                return 'priority-medium';
        }
    }
    
    /**
     * Validate priority value
     */
    public function validatePriority($priority) {
        $valid = ['urgent', 'high', 'medium', 'low'];
        return in_array(strtolower($priority), $valid) ? strtolower($priority) : 'medium';
    }

    /**
     * Delete a complaint (only if resolved or rejected)
     */
    public function deleteComplaint($complaint_id, $user_id, $user_role) {
        try {
            // First, check if the complaint exists and can be deleted
            $query = "SELECT c.*, u.full_name 
                      FROM complaints c
                      JOIN users u ON c.user_id = u.user_id
                      WHERE c.complaint_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$complaint) {
                return ['success' => false, 'message' => 'Complaint not found'];
            }
            
            // Check if user has permission to delete
            $can_delete = false;
            
            // Admins can delete any resolved/rejected complaint
            if ($user_role == 'admin' || $user_role == 'dean' || $user_role == 'principal') {
                $can_delete = true;
            }
            // Staff can delete complaints from their department
            elseif ($user_role == 'staff') {
                // Get staff's department
                $dept_query = "SELECT department FROM users WHERE user_id = ?";
                $dept_stmt = $this->conn->prepare($dept_query);
                $dept_stmt->execute([$user_id]);
                $staff_dept = $dept_stmt->fetchColumn();
                
                if ($staff_dept && $staff_dept == $complaint['department_assigned']) {
                    $can_delete = true;
                }
            }
            // Students can only delete their own complaints
            elseif ($user_role == 'student' && $complaint['user_id'] == $user_id) {
                $can_delete = true;
            }
            
            if (!$can_delete) {
                return ['success' => false, 'message' => 'You do not have permission to delete this complaint'];
            }
            
            // Check if complaint can be deleted (only resolved or rejected)
            $allowed_statuses = ['resolved', 'rejected', 'closed'];
            if (!in_array($complaint['status'], $allowed_statuses)) {
                return ['success' => false, 'message' => 'Only resolved, rejected, or closed complaints can be deleted'];
            }
            
            // Begin transaction
            $this->conn->beginTransaction();
            
            // Delete related records first (foreign key constraints)
            
            // 1. Delete attachments
            $query = "DELETE FROM complaint_attachments WHERE complaint_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id]);
            
            // 2. Delete upvotes
            $query = "DELETE FROM complaint_upvotes WHERE complaint_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id]);
            
            // 3. Delete batch members
            $query = "DELETE FROM batch_members WHERE complaint_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id]);
            
            // 4. Delete timeline entries
            $query = "DELETE FROM complaint_timeline WHERE complaint_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id]);
            
            // 5. Finally, delete the complaint
            $query = "DELETE FROM complaints WHERE complaint_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id]);
            
            // Commit transaction
            $this->conn->commit();
            
            // Try to delete physical attachment files if they exist
            $upload_dir = dirname(dirname(__FILE__)) . '/uploads/complaints/' . $complaint_id . '/';
            if (file_exists($upload_dir)) {
                $this->deleteDirectory($upload_dir);
            }
            
            // Log the deletion
            error_log("Complaint ID $complaint_id deleted by user ID $user_id (role: $user_role)");
            
            return ['success' => true, 'message' => 'Complaint deleted successfully'];
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Delete complaint error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log("Delete complaint error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Helper function to delete directory recursively
     */
    private function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }

    /**
     * Check if user can rate a complaint
     */
    public function canRateComplaint($complaint_id, $user_id) {
        try {
            // Check if complaint exists, is resolved, and belongs to user
            $query = "SELECT c.*, 
                      (SELECT COUNT(*) FROM complaint_ratings WHERE complaint_id = c.complaint_id AND user_id = ?) as already_rated
                      FROM complaints c
                      WHERE c.complaint_id = ? AND c.user_id = ? AND c.status = 'resolved'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id, $complaint_id, $user_id]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$complaint) {
                return ['can_rate' => false, 'message' => 'Complaint not found or not resolved'];
            }
            
            if ($complaint['already_rated'] > 0) {
                return ['can_rate' => false, 'message' => 'You have already rated this complaint'];
            }
            
            return ['can_rate' => true, 'complaint' => $complaint];
            
        } catch (PDOException $e) {
            error_log("canRateComplaint error: " . $e->getMessage());
            return ['can_rate' => false, 'message' => 'Database error'];
        }
    }

    /**
     * Save complaint rating
     */
    public function saveRating($complaint_id, $user_id, $ratings, $feedback = '', $is_public = false) {
        try {
            // Validate ratings
            if ($ratings['speed'] < 1 || $ratings['speed'] > 5 ||
                $ratings['communication'] < 1 || $ratings['communication'] > 5 ||
                $ratings['effectiveness'] < 1 || $ratings['effectiveness'] > 5) {
                return ['success' => false, 'message' => 'Invalid rating values'];
            }
            
            // Begin transaction
            $this->conn->beginTransaction();
            
            // Insert rating
            $query = "INSERT INTO complaint_ratings 
                      (complaint_id, user_id, speed_rating, communication_rating, effectiveness_rating, feedback, is_public) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $complaint_id, 
                $user_id, 
                $ratings['speed'], 
                $ratings['communication'], 
                $ratings['effectiveness'], 
                $feedback, 
                $is_public ? 1 : 0
            ]);
            
            // Get complaint department
            $query = "SELECT department_assigned FROM complaints WHERE complaint_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id]);
            $department = $stmt->fetchColumn();
            
            // Update department ratings
            if ($department) {
                $query = "INSERT INTO department_ratings (department, avg_speed, avg_communication, avg_effectiveness, avg_overall, total_ratings)
                          SELECT 
                              ? as department,
                              AVG(speed_rating) as speed,
                              AVG(communication_rating) as comm,
                              AVG(effectiveness_rating) as eff,
                              AVG(overall_rating) as overall,
                              COUNT(*) as total
                          FROM complaint_ratings r
                          JOIN complaints c ON r.complaint_id = c.complaint_id
                          WHERE c.department_assigned = ?
                          ON DUPLICATE KEY UPDATE
                              avg_speed = VALUES(avg_speed),
                              avg_communication = VALUES(avg_communication),
                              avg_effectiveness = VALUES(avg_effectiveness),
                              avg_overall = VALUES(avg_overall),
                              total_ratings = VALUES(total_ratings)";
                
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$department, $department]);
            }
            
            $this->conn->commit();
            
            return ['success' => true, 'message' => 'Rating submitted successfully'];
            
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("saveRating error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

 /**
 * Get department ratings for public dashboard
 */
public function getDepartmentRatings($limit = 10) {
    try {
        // Simple direct query to get department ratings
        $query = "SELECT * FROM department_ratings 
                  WHERE total_ratings > 0 
                  ORDER BY avg_overall DESC, total_ratings DESC 
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("getDepartmentRatings found: " . count($results) . " departments");
        
        return $results;
        
    } catch (PDOException $e) {
        error_log("getDepartmentRatings error: " . $e->getMessage());
        return [];
    }
}

   /**
 * Get recent public ratings
 */
public function getPublicRatings($limit = 10) {
    try {
        // Simple direct query - no joins first to test
        $query = "SELECT r.*, 
                  (SELECT complaint_number FROM complaints WHERE complaint_id = r.complaint_id) as complaint_number,
                  (SELECT title FROM complaints WHERE complaint_id = r.complaint_id) as title,
                  (SELECT full_name FROM users WHERE user_id = r.user_id) as full_name,
                  (SELECT department_assigned FROM complaints WHERE complaint_id = r.complaint_id) as department
                  FROM complaint_ratings r
                  WHERE r.is_public = 1
                  ORDER BY r.created_at DESC
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("getPublicRatings simple query found: " . count($results));
        
        return $results;
        
    } catch (PDOException $e) {
        error_log("getPublicRatings error: " . $e->getMessage());
        return [];
    }
}

    /**
     * Get rating for a specific complaint
     */
    public function getComplaintRating($complaint_id) {
        try {
            $query = "SELECT * FROM complaint_ratings WHERE complaint_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("getComplaintRating error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate ETA for a complaint based on historical data
     */
    public function calculateETA($complaint_id) {
        try {
            // Get complaint details
            $query = "SELECT c.*, cat.category_name 
                      FROM complaints c
                      LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                      WHERE c.complaint_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$complaint) {
                return null;
            }
            
            // Find similar historical cases
            $query = "SELECT 
                        AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_time,
                        COUNT(*) as sample_size,
                        STDDEV(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as stddev
                      FROM complaints 
                      WHERE status = 'resolved' 
                        AND resolved_at IS NOT NULL
                        AND department_assigned = ?
                        AND category_id = ?
                        AND created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint['department_assigned'], $complaint['category_id']]);
            $historical = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $eta_data = [
                'estimated_hours' => null,
                'confidence' => 0,
                'sample_size' => 0,
                'min_time' => null,
                'max_time' => null,
                'based_on' => 'historical'
            ];
            
            if ($historical && $historical['avg_time'] > 0) {
                $eta_data['estimated_hours'] = round($historical['avg_time'], 1);
                $eta_data['sample_size'] = $historical['sample_size'];
                $eta_data['confidence'] = $this->calculateConfidence($historical['sample_size'], $historical['stddev']);
                
                // Calculate date range
                $created = new DateTime($complaint['created_at']);
                $eta_data['estimated_date'] = $created->add(new DateInterval('PT' . round($historical['avg_time'] * 3600) . 'S'))->format('Y-m-d H:i:s');
            } else {
                // Fallback to department averages
                $query = "SELECT 
                            AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_time,
                            COUNT(*) as sample_size
                          FROM complaints 
                          WHERE status = 'resolved' 
                            AND resolved_at IS NOT NULL
                            AND department_assigned = ?
                            AND created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)";
                
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$complaint['department_assigned']]);
                $dept_avg = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($dept_avg && $dept_avg['avg_time'] > 0) {
                    $eta_data['estimated_hours'] = round($dept_avg['avg_time'], 1);
                    $eta_data['sample_size'] = $dept_avg['sample_size'];
                    $eta_data['confidence'] = 0.5; // Lower confidence for department-only data
                    $eta_data['based_on'] = 'department';
                    
                    $created = new DateTime($complaint['created_at']);
                    $eta_data['estimated_date'] = $created->add(new DateInterval('PT' . round($dept_avg['avg_time'] * 3600) . 'S'))->format('Y-m-d H:i:s');
                }
            }
            
            // Update complaint with ETA
            if (isset($eta_data['estimated_date'])) {
                $query = "UPDATE complaints SET estimated_resolution_date = ? WHERE complaint_id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$eta_data['estimated_date'], $complaint_id]);
            }
            
            return $eta_data;
            
        } catch (PDOException $e) {
            error_log("calculateETA error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate confidence score based on sample size and variance
     */
    private function calculateConfidence($sample_size, $stddev) {
        if ($sample_size < 5) return 0.3;
        if ($sample_size < 10) return 0.5;
        if ($sample_size < 20) return 0.7;
        
        // Higher confidence for larger samples with low variance
        $base_confidence = min(0.95, 0.7 + ($sample_size / 100));
        
        // Reduce confidence if high variance
        if ($stddev > 24) { // More than 24 hours variance
            $base_confidence *= 0.8;
        }
        
        return round($base_confidence, 2);
    }

    /**
     * Check for complaints that need escalation
     */
    public function checkEscalations() {
        try {
            $escalated_count = 0;
            
            // Get active escalation rules
            $rules_query = "SELECT * FROM escalation_rules WHERE is_active = TRUE";
            $rules_stmt = $this->conn->query($rules_query);
            $rules = $rules_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rules as $rule) {
                // Find complaints that match the rule
                $query = "SELECT complaint_id, status, priority, created_at 
                          FROM complaints 
                          WHERE status = ? 
                            AND escalated = FALSE 
                            AND escalation_count < 3
                            AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)";
                
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$rule['status'], $rule['hours_threshold']]);
                $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($complaints as $complaint) {
                    // Update priority
                    $update = "UPDATE complaints 
                               SET priority = ?, 
                                   escalated = TRUE, 
                                   escalation_count = escalation_count + 1 
                               WHERE complaint_id = ?";
                    
                    $update_stmt = $this->conn->prepare($update);
                    $update_stmt->execute([$rule['new_priority'], $complaint['complaint_id']]);
                    
                    // Add to timeline
                    $this->addTimelineEntry(
                        $complaint['complaint_id'], 
                        'escalated', 
                        "Auto-escalated to {$rule['new_priority']} priority after {$rule['hours_threshold']} hours",
                        null,
                        true
                    );
                    
                    // Create notification for staff/admin
                    $this->createEscalationNotification($complaint['complaint_id'], $rule);
                    
                    $escalated_count++;
                }
            }
            
            return ['success' => true, 'escalated' => $escalated_count];
            
        } catch (PDOException $e) {
            error_log("checkEscalations error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create notification for escalated complaint
     */
    private function createEscalationNotification($complaint_id, $rule) {
        try {
            // Get complaint details
            $query = "SELECT c.*, u.full_name 
                      FROM complaints c
                      JOIN users u ON c.user_id = u.user_id
                      WHERE c.complaint_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Create notification table if not exists
            $this->conn->exec("CREATE TABLE IF NOT EXISTS notifications (
                notification_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                complaint_id INT,
                message TEXT,
                type VARCHAR(50),
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE
            )");
            
            // Get users to notify based on role
            $users_query = "SELECT user_id FROM users WHERE role = ?";
            $users_stmt = $this->conn->prepare($users_query);
            $users_stmt->execute([$rule['notify_role']]);
            $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $message = "Complaint #{$complaint['complaint_number']} has been escalated to {$rule['new_priority']} priority. It has been {$rule['hours_threshold']} hours in '{$rule['status']}' status.";
            
            foreach ($users as $user) {
                $insert = "INSERT INTO notifications (user_id, complaint_id, message, type) VALUES (?, ?, ?, 'escalation')";
                $insert_stmt = $this->conn->prepare($insert);
                $insert_stmt->execute([$user['user_id'], $complaint_id, $message]);
            }
            
        } catch (PDOException $e) {
            error_log("createEscalationNotification error: " . $e->getMessage());
        }
    }

    /**
     * Get historical trends for analytics
     */
    public function getHistoricalTrends($months = 6) {
        try {
            $query = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                        AVG(CASE WHEN status = 'resolved' THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) ELSE NULL END) as avg_resolution_hours
                      FROM complaints
                      WHERE created_at > DATE_SUB(NOW(), INTERVAL ? MONTH)
                      GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                      ORDER BY month DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$months]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("getHistoricalTrends error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get department performance analytics
     */
    public function getDepartmentAnalytics() {
        try {
            $query = "SELECT 
                        d.dept_name,
                        COUNT(c.complaint_id) as total_complaints,
                        SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                        AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as avg_resolution_hours,
                        dr.avg_overall as satisfaction_score,
                        dr.total_ratings
                      FROM departments d
                      LEFT JOIN complaints c ON c.department_assigned = d.dept_name
                      LEFT JOIN department_ratings dr ON dr.department = d.dept_name
                      GROUP BY d.dept_id
                      ORDER BY avg_resolution_hours ASC";
            
            $stmt = $this->conn->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("getDepartmentAnalytics error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all users that the current user can message
     */
    public function getMessagableUsers($current_user_id, $current_user_role) {
        try {
            $query = "SELECT user_id, full_name, email, role, department, 
                      (SELECT COUNT(*) FROM direct_messages 
                       WHERE ((sender_id = ? AND receiver_id = users.user_id) 
                       OR (sender_id = users.user_id AND receiver_id = ?)) 
                       AND is_read = FALSE 
                       AND sender_id != ?) as unread_count
                      FROM users 
                      WHERE user_id != ?";
            
            // Filter based on role
            if ($current_user_role == 'staff' || $current_user_role == 'supervisor') {
                // Staff/Supervisor can message others in same department and admins
                $dept = $this->getUserDepartment($current_user_id);
                $query .= " AND (department = ? OR role IN ('admin', 'dean', 'principal'))";
                $params = [$current_user_id, $current_user_id, $current_user_id, $current_user_id, $dept];
            } elseif ($current_user_role == 'admin' || $current_user_role == 'dean' || $current_user_role == 'principal') {
                // Admins can message everyone except students
                $query .= " AND role IN ('staff', 'supervisor', 'admin', 'dean', 'principal')";
                $params = [$current_user_id, $current_user_id, $current_user_id, $current_user_id];
            } else {
                // Students can only message staff/admins (not other students)
                $query .= " AND role IN ('staff', 'supervisor', 'admin', 'dean', 'principal')";
                $params = [$current_user_id, $current_user_id, $current_user_id, $current_user_id];
            }
            
            $query .= " ORDER BY full_name ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("getMessagableUsers error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's department
     */
    private function getUserDepartment($user_id) {
        try {
            $query = "SELECT department FROM users WHERE user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Get conversation between two users
     */
    public function getConversation($user1_id, $user2_id, $last_message_id = 0, $limit = 50) {
        try {
            $query = "SELECT m.*, 
                      u1.full_name as sender_name, u1.role as sender_role,
                      u2.full_name as receiver_name
                      FROM direct_messages m
                      JOIN users u1 ON m.sender_id = u1.user_id
                      JOIN users u2 ON m.receiver_id = u2.user_id
                      WHERE ((m.sender_id = ? AND m.receiver_id = ?) 
                         OR (m.sender_id = ? AND m.receiver_id = ?))
                         AND m.message_id > ?
                      ORDER BY m.created_at ASC
                      LIMIT ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $user1_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $user2_id, PDO::PARAM_INT);
            $stmt->bindValue(3, $user2_id, PDO::PARAM_INT);
            $stmt->bindValue(4, $user1_id, PDO::PARAM_INT);
            $stmt->bindValue(5, $last_message_id, PDO::PARAM_INT);
            $stmt->bindValue(6, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Mark messages as read
            $this->markDirectMessagesAsRead($user1_id, $user2_id);
            
            // Get attachments
            foreach ($messages as &$msg) {
                $att_query = "SELECT * FROM direct_message_attachments WHERE message_id = ?";
                $att_stmt = $this->conn->prepare($att_query);
                $att_stmt->execute([$msg['message_id']]);
                $msg['attachments'] = $att_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $messages;
            
        } catch (PDOException $e) {
            error_log("getConversation error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Send direct message
     */
    public function sendDirectMessage($sender_id, $receiver_id, $message, $attachments = []) {
        try {
            $this->conn->beginTransaction();
            
            // Insert message
            $query = "INSERT INTO direct_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$sender_id, $receiver_id, $message]);
            $message_id = $this->conn->lastInsertId();
            
            // Handle attachments
            if (!empty($attachments)) {
                foreach ($attachments as $file) {
                    $upload_result = $this->uploadDirectMessageAttachment($file, $message_id);
                    if (!$upload_result['success']) {
                        throw new Exception($upload_result['message']);
                    }
                }
            }
            
            // Update or create conversation
            $query = "INSERT INTO user_conversations (user1_id, user2_id, last_message_id) 
                      VALUES (?, ?, ?)
                      ON DUPLICATE KEY UPDATE last_message_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$sender_id, $receiver_id, $message_id, $message_id]);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message_id' => $message_id,
                'message' => 'Message sent successfully'
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("sendDirectMessage error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Upload direct message attachment
     */
    public function uploadDirectMessageAttachment($file, $message_id) {
        $upload_dir = dirname(dirname(__FILE__)) . '/uploads/direct_messages/' . $message_id . '/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safe_filename = time() . '_' . uniqid() . '.' . $extension;
        $target_file = $upload_dir . $safe_filename;
        
        if ($file['size'] > 10 * 1024 * 1024) {
            return ['success' => false, 'message' => 'File too large (max 10MB)'];
        }
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx'];
        if (!in_array($extension, $allowed_types)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $relative_path = 'uploads/direct_messages/' . $message_id . '/' . $safe_filename;
            
            $query = "INSERT INTO direct_message_attachments (message_id, file_name, file_path, file_size, file_type) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$message_id, $safe_filename, $relative_path, $file['size'], $extension]);
            
            return ['success' => true, 'path' => $relative_path];
        }
        
        return ['success' => false, 'message' => 'Upload failed'];
    }

    /**
     * Mark direct messages as read
     */
    public function markDirectMessagesAsRead($user_id, $other_user_id) {
        try {
            $query = "UPDATE direct_messages 
                      SET is_read = TRUE, read_at = NOW() 
                      WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$other_user_id, $user_id]);
            
            return true;
        } catch (PDOException $e) {
            error_log("markDirectMessagesAsRead error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get unread message count for user
     */
    public function getDirectUnreadCount($user_id) {
        try {
            $query = "SELECT COUNT(*) as total FROM direct_messages 
                      WHERE receiver_id = ? AND is_read = FALSE";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("getDirectUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get recent conversations for user
     */
    public function getRecentConversations($user_id) {
        try {
            $query = "SELECT 
                        u.user_id, u.full_name, u.role, u.department,
                        (SELECT COUNT(*) FROM direct_messages 
                         WHERE sender_id = u.user_id AND receiver_id = ? AND is_read = FALSE) as unread_from_them,
                        (SELECT COUNT(*) FROM direct_messages 
                         WHERE sender_id = ? AND receiver_id = u.user_id AND is_read = FALSE) as unread_from_me,
                        m.message as last_message,
                        m.created_at as last_message_time
                      FROM users u
                      LEFT JOIN user_conversations c ON 
                        (c.user1_id = ? AND c.user2_id = u.user_id) OR 
                        (c.user2_id = ? AND c.user1_id = u.user_id)
                      LEFT JOIN direct_messages m ON c.last_message_id = m.message_id
                      WHERE u.user_id != ?
                        AND (c.conversation_id IS NOT NULL OR 
                             EXISTS (SELECT 1 FROM direct_messages 
                                    WHERE (sender_id = ? AND receiver_id = u.user_id) 
                                       OR (sender_id = u.user_id AND receiver_id = ?)))
                      ORDER BY 
                        (unread_from_them > 0) DESC,
                        m.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("getRecentConversations error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Set direct typing status
     */
    public function setDirectTypingStatus($sender_id, $receiver_id, $is_typing) {
        try {
            $query = "INSERT INTO direct_typing (sender_id, receiver_id, is_typing) 
                      VALUES (?, ?, ?)
                      ON DUPLICATE KEY UPDATE is_typing = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$sender_id, $receiver_id, $is_typing, $is_typing]);
            return true;
        } catch (PDOException $e) {
            error_log("setDirectTypingStatus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get typing status
     */
    public function getDirectTypingStatus($receiver_id, $sender_id) {
        try {
            $query = "SELECT is_typing FROM direct_typing 
                      WHERE sender_id = ? AND receiver_id = ? 
                      AND updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$sender_id, $receiver_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (bool)$result['is_typing'] : false;
        } catch (PDOException $e) {
            return false;
        }
    }



/**
 * Generate random string helper
 */
private function generateRandomString($length = 10, $type = 'alnum') {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if ($type == 'lower') {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
    } elseif ($type == 'upper') {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    } elseif ($type == 'numeric') {
        $characters = '0123456789';
    }
    
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
 

    /**
 * Create a meeting for a complaint
 */
public function createMeeting($complaint_id, $created_by, $data) {
    try {
        // Generate meeting link based on type
        $meeting = $this->generateMeetingLink($data['meeting_type'], $data['title']);
        
        $query = "INSERT INTO complaint_meetings 
                  (complaint_id, created_by, meeting_title, meeting_type, meeting_link, 
                   meeting_id_code, meeting_password, scheduled_at, duration_minutes, description, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $complaint_id,
            $created_by,
            $data['title'],
            $meeting['type'],
            $meeting['link'],
            $meeting['meeting_id'],
            $meeting['password'],
            $data['scheduled_at'],
            $data['duration'],
            $data['description'] ?? ''
        ]);
        
        $meeting_id = $this->conn->lastInsertId();
        
        // Add attendees ONLY if any were selected
        if (!empty($data['attendees']) && is_array($data['attendees'])) {
            $attendee_query = "INSERT INTO meeting_attendees (meeting_id, user_id) VALUES (?, ?)";
            $attendee_stmt = $this->conn->prepare($attendee_query);
            
            foreach ($data['attendees'] as $user_id) {
                // Make sure user_id is valid
                if (!empty($user_id)) {
                    $attendee_stmt->execute([$meeting_id, $user_id]);
                }
            }
        }
        
        // Add the complaint owner automatically (always add the owner)
        $owner_query = "SELECT user_id FROM complaints WHERE complaint_id = ?";
        $owner_stmt = $this->conn->prepare($owner_query);
        $owner_stmt->execute([$complaint_id]);
        $owner_id = $owner_stmt->fetchColumn();
        
        if ($owner_id) {
            // Check if owner is already in attendees (in case they were also selected)
            $check_query = "SELECT COUNT(*) FROM meeting_attendees WHERE meeting_id = ? AND user_id = ?";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->execute([$meeting_id, $owner_id]);
            $already_added = $check_stmt->fetchColumn();
            
            if (!$already_added) {
                // Prepare attendee statement only if we haven't already prepared it
                $owner_attendee_stmt = $this->conn->prepare("INSERT INTO meeting_attendees (meeting_id, user_id) VALUES (?, ?)");
                $owner_attendee_stmt->execute([$meeting_id, $owner_id]);
            }
        }
        
        // Add to timeline
        $this->addTimelineEntry(
            $complaint_id,
            'meeting_scheduled',
            "Meeting scheduled: {$data['title']} on " . date('M j, g:i A', strtotime($data['scheduled_at'])),
            $created_by
        );
        
        // Count total attendees for response message
        $attendee_count = !empty($data['attendees']) ? count($data['attendees']) : 0;
        $attendee_count++; // Add the owner
        
        return [
            'success' => true,
            'meeting_id' => $meeting_id,
            'meeting_link' => $meeting['link'],
            'message' => "Meeting created successfully with {$attendee_count} attendees (including complaint owner)"
        ];
        
    } catch (PDOException $e) {
        error_log("createMeeting error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return ['success' => false, 'message' => 'Failed to create meeting: ' . $e->getMessage()];
    }
}
    /**
     * Get meetings for a complaint
     */
    public function getComplaintMeetings($complaint_id) {
        try {
            $query = "SELECT m.*, u.full_name as created_by_name,
                      (SELECT COUNT(*) FROM meeting_attendees WHERE meeting_id = m.meeting_id) as attendee_count
                      FROM complaint_meetings m
                      JOIN users u ON m.created_by = u.user_id
                      WHERE m.complaint_id = ?
                      ORDER BY m.scheduled_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id]);
            $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get attendees for each meeting
            foreach ($meetings as &$meeting) {
                $att_query = "SELECT u.user_id, u.full_name, u.email, u.role, ma.attendance_status
                              FROM meeting_attendees ma
                              JOIN users u ON ma.user_id = u.user_id
                              WHERE ma.meeting_id = ?";
                $att_stmt = $this->conn->prepare($att_query);
                $att_stmt->execute([$meeting['meeting_id']]);
                $meeting['attendees'] = $att_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $meetings;
            
        } catch (PDOException $e) {
            error_log("getComplaintMeetings error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a meeting
     */
    public function deleteMeeting($meeting_id, $user_id) {
        try {
            // Check if user has permission to delete this meeting
            $query = "SELECT created_by FROM complaint_meetings WHERE meeting_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$meeting_id]);
            $created_by = $stmt->fetchColumn();
            
            // Get user role
            $role_query = "SELECT role FROM users WHERE user_id = ?";
            $role_stmt = $this->conn->prepare($role_query);
            $role_stmt->execute([$user_id]);
            $user_role = $role_stmt->fetchColumn();
            
            // Only the creator or admin can delete
            if ($created_by != $user_id && $user_role != 'admin' && $user_role != 'dean' && $user_role != 'principal') {
                return ['success' => false, 'message' => 'You do not have permission to delete this meeting'];
            }
            
            // Start transaction
            $this->conn->beginTransaction();
            
            // Delete attendees first
            $query = "DELETE FROM meeting_attendees WHERE meeting_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$meeting_id]);
            
            // Delete invites if table exists
            try {
                $query = "DELETE FROM meeting_invites WHERE meeting_id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$meeting_id]);
            } catch (PDOException $e) {
                // Table might not exist, ignore
            }
            
            // Delete the meeting
            $query = "DELETE FROM complaint_meetings WHERE meeting_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$meeting_id]);
            
            $this->conn->commit();
            
            return ['success' => true, 'message' => 'Meeting deleted successfully'];
            
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("deleteMeeting error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete meeting: ' . $e->getMessage()];
        }
    }

    /**
     * Complete a meeting (mark as completed)
     */
    public function completeMeeting($meeting_id, $user_id) {
        try {
            $query = "UPDATE complaint_meetings SET status = 'completed' WHERE meeting_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$meeting_id]);
            
            return ['success' => true, 'message' => 'Meeting marked as completed'];
        } catch (PDOException $e) {
            error_log("completeMeeting error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update meeting'];
        }
    }

    /**
     * Update meeting status
     */
    public function updateMeetingStatus($meeting_id, $status) {
        try {
            $query = "UPDATE complaint_meetings SET status = ? WHERE meeting_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$status, $meeting_id]);
            
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("updateMeetingStatus error: " . $e->getMessage());
            return ['success' => false];
        }
    }

    /**
     * Send meeting invitations via email
     */
    public function sendMeetingInvites($meeting_id, $email_helper) {
        try {
            // Get meeting details with attendees
            $query = "SELECT m.*, c.complaint_number, u.email as creator_email, u.full_name as creator_name
                      FROM complaint_meetings m
                      JOIN complaints c ON m.complaint_id = c.complaint_id
                      JOIN users u ON m.created_by = u.user_id
                      WHERE m.meeting_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$meeting_id]);
            $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get attendees
            $att_query = "SELECT u.user_id, u.full_name, u.email
                          FROM meeting_attendees ma
                          JOIN users u ON ma.user_id = u.user_id
                          WHERE ma.meeting_id = ?";
            $att_stmt = $this->conn->prepare($att_query);
            $att_stmt->execute([$meeting_id]);
            $attendees = $att_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sent_count = 0;
            
            foreach ($attendees as $attendee) {
                $subject = "Meeting Invitation: Complaint #{$meeting['complaint_number']}";
                
                $message = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .meeting-details { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; }
                        .button { display: inline-block; padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; }
                        .footer { margin-top: 20px; color: #666; font-size: 12px; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Meeting Invitation</h2>
                        </div>
                        <div class='content'>
                            <p>Hello <strong>{$attendee['full_name']}</strong>,</p>
                            <p><strong>{$meeting['creator_name']}</strong> has scheduled a meeting regarding Complaint #{$meeting['complaint_number']}.</p>
                            
                            <div class='meeting-details'>
                                <h3>{$meeting['meeting_title']}</h3>
                                <p><strong>Date:</strong> " . date('F j, Y', strtotime($meeting['scheduled_at'])) . "</p>
                                <p><strong>Time:</strong> " . date('g:i A', strtotime($meeting['scheduled_at'])) . "</p>
                                <p><strong>Duration:</strong> {$meeting['duration_minutes']} minutes</p>
                                <p><strong>Platform:</strong> " . ucfirst(str_replace('_', ' ', $meeting['meeting_type'])) . "</p>
                                <p><strong>Meeting Link:</strong> <a href='{$meeting['meeting_link']}'>{$meeting['meeting_link']}</a></p>";
                
                if ($meeting['meeting_password']) {
                    $message .= "<p><strong>Password:</strong> {$meeting['meeting_password']}</p>";
                }
                
                if ($meeting['description']) {
                    $message .= "<p><strong>Description:</strong> {$meeting['description']}</p>";
                }
                
                $message .= "
                            </div>
                            
                            <p style='text-align: center;'>
                                <a href='{$meeting['meeting_link']}' class='button' target='_blank'>Join Meeting</a>
                            </p>
                            
                            <p>Please add this meeting to your calendar.</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message from the Student Complaint System.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
                if ($email_helper->sendEmail($attendee['email'], $subject, $message)) {
                    $sent_count++;
                    
                    // Mark as notified
                    $update_query = "UPDATE meeting_attendees SET notified = TRUE WHERE meeting_id = ? AND user_id = ?";
                    $update_stmt = $this->conn->prepare($update_query);
                    $update_stmt->execute([$meeting_id, $attendee['user_id']]);
                }
            }
            
            return ['success' => true, 'sent' => $sent_count];
            
        } catch (Exception $e) {
            error_log("sendMeetingInvites error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

  /**
 * Send in-app notification to a student
 */
public function sendStudentNotification($user_id, $complaint_id, $message) {
    try {
        $query = "INSERT INTO student_notifications (user_id, complaint_id, message) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id, $complaint_id, $message]);
        return true;
    } catch (PDOException $e) {
        error_log("sendStudentNotification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send bulk in-app notifications to all batch members
 */
public function sendBulkStudentNotifications($complaint_id, $message, $exclude_user_id = null) {
    try {
        // Get all batch members
        $query = "SELECT user_id FROM batch_members WHERE complaint_id = ?";
        $params = [$complaint_id];
        
        if ($exclude_user_id) {
            $query .= " AND user_id != ?";
            $params[] = $exclude_user_id;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 0;
        foreach ($students as $student) {
            $insert = "INSERT INTO student_notifications (user_id, complaint_id, message) VALUES (?, ?, ?)";
            $insert_stmt = $this->conn->prepare($insert);
            $insert_stmt->execute([$student['user_id'], $complaint_id, $message]);
            $count++;
        }
        
        return ['success' => true, 'count' => $count];
        
    } catch (PDOException $e) {
        error_log("sendBulkStudentNotifications error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send notifications'];
    }
}

/**
 * Get student notifications
 */
public function getStudentNotifications($user_id, $limit = 20) {
    try {
        $query = "SELECT n.*, c.complaint_number 
                  FROM student_notifications n
                  JOIN complaints c ON n.complaint_id = c.complaint_id
                  WHERE n.user_id = ?
                  ORDER BY n.created_at DESC
                  LIMIT ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getStudentNotifications error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get unread notification count
 */
public function getUnreadNotificationCount($user_id) {
    try {
        $query = "SELECT COUNT(*) as count FROM student_notifications 
                  WHERE user_id = ? AND is_read = FALSE";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("getUnreadNotificationCount error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark notification as read
 */
public function markNotificationRead($notification_id, $user_id) {
    try {
        $query = "UPDATE student_notifications SET is_read = TRUE 
                  WHERE notification_id = ? AND user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$notification_id, $user_id]);
        return true;
    } catch (PDOException $e) {
        error_log("markNotificationRead error: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read
 */
public function markAllNotificationsRead($user_id) {
    try {
        $query = "UPDATE student_notifications SET is_read = TRUE 
                  WHERE user_id = ? AND is_read = FALSE";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);
        return true;
    } catch (PDOException $e) {
        error_log("markAllNotificationsRead error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get class rep unread notification count
 */
public function getClassRepUnreadCount($user_id) {
    try {
        $query = "SELECT COUNT(*) as count FROM class_rep_notifications 
                  WHERE user_id = ? AND is_read = FALSE";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("getClassRepUnreadCount error: " . $e->getMessage());
        return 0;
    }
}

// ========================================================================
// Internal staff assignment + resolution evidence tracking
//
// Lets department staff hand a complaint off to a specific colleague in
// the same department to work on, track that colleague's progress and
// before/after photo evidence, and confirm the final resolution before
// it's shown as resolved to the student.
// ========================================================================

/**
 * Send a generic in-app notification to any user (student, class rep,
 * or staff). Reuses the same student_notifications table since it's not
 * actually role-specific - just keyed by user_id.
 */
public function sendStaffNotification($user_id, $complaint_id, $message, $type = 'assignment') {
    try {
        $query = "INSERT INTO student_notifications (user_id, complaint_id, message, type) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$user_id, $complaint_id, $message, $type]);
    } catch (PDOException $e) {
        error_log("sendStaffNotification error: " . $e->getMessage());
        return false;
    }
}

/**
 * List other staff members in the given operational department, for the
 * "assign to a colleague" dropdown.
 */
public function getDepartmentStaff($department, $exclude_user_id = null) {
    try {
        $query = "SELECT user_id, full_name, email FROM users 
                  WHERE role = 'staff' AND department = ? AND is_active = 1";
        $params = [$department];
        if ($exclude_user_id) {
            $query .= " AND user_id != ?";
            $params[] = $exclude_user_id;
        }
        $query .= " ORDER BY full_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getDepartmentStaff error: " . $e->getMessage());
        return [];
    }
}

/**
 * Delegate a complaint to a specific staff member in the department.
 * Works for a fresh assignment or a reassignment to someone else.
 */
public function assignComplaintToStaff($complaint_id, $assigned_to, $assigned_by, $note = '') {
    try {
        // Confirm the assignee is actually staff in the same department as the complaint
        $check = $this->conn->prepare("SELECT u.full_name, u.department FROM users u WHERE u.user_id = ? AND u.role = 'staff'");
        $check->execute([$assigned_to]);
        $assignee = $check->fetch(PDO::FETCH_ASSOC);
        if (!$assignee) {
            return ['success' => false, 'message' => 'Selected staff member not found'];
        }

        $comp_stmt = $this->conn->prepare("SELECT status, department_assigned FROM complaints WHERE complaint_id = ?");
        $comp_stmt->execute([$complaint_id]);
        $complaint = $comp_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }
        if ($assignee['department'] !== $complaint['department_assigned']) {
            return ['success' => false, 'message' => 'That staff member is not in this complaint\'s department'];
        }

        $new_status = in_array($complaint['status'], ['submitted', 'under_review']) ? 'in_progress' : $complaint['status'];

        $update = $this->conn->prepare("UPDATE complaints 
            SET assigned_to = ?, assigned_by = ?, assigned_at = NOW(), assignment_status = 'assigned', status = ?
            WHERE complaint_id = ?");
        $update->execute([$assigned_to, $assigned_by, $new_status, $complaint_id]);

        $assigner_stmt = $this->conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $assigner_stmt->execute([$assigned_by]);
        $assigner_name = $assigner_stmt->fetchColumn() ?: 'a colleague';

        $desc = "Assigned to {$assignee['full_name']} by {$assigner_name}";
        if (!empty($note)) $desc .= ": " . $note;
        $this->addTimelineEntry($complaint_id, $new_status, $desc, $assigned_by);

        $this->sendStaffNotification($assigned_to, $complaint_id, "You've been assigned a complaint to work on" . (!empty($note) ? ": $note" : "."), 'assignment');

        return ['success' => true];
    } catch (PDOException $e) {
        error_log("assignComplaintToStaff error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error while assigning complaint'];
    }
}

/**
 * The assigned staff member uploads before/after (or general) photo
 * evidence of their work on the complaint.
 */
public function uploadEvidence($file, $complaint_id, $uploaded_by, $evidence_type = 'general', $caption = '') {
    $base_upload_dir = dirname(dirname(__FILE__)) . '/uploads/evidence/';
    $target_dir = $base_upload_dir . $complaint_id . '/';

    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safe_filename = time() . '_' . uniqid() . '.' . $extension;
    $target_file = $target_dir . $safe_filename;

    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large (max 5MB)'];
    }

    // Evidence is photo-only by design (before/after pictures of the issue)
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Only image files are allowed for evidence (jpg, png, gif, webp)'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error occurred'];
    }

    if (!in_array($evidence_type, ['before', 'after', 'general'])) {
        $evidence_type = 'general';
    }

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $relative_path = 'uploads/evidence/' . $complaint_id . '/' . $safe_filename;
        try {
            $query = "INSERT INTO complaint_evidence (complaint_id, uploaded_by, evidence_type, file_name, file_path, file_size, file_type, caption) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$complaint_id, $uploaded_by, $evidence_type, $safe_filename, $relative_path, $file['size'], $extension, $caption]);

            $label = $evidence_type === 'before' ? 'Before' : ($evidence_type === 'after' ? 'After' : 'Supporting');
            $this->addTimelineEntry($complaint_id, null, "$label photo evidence uploaded" . (!empty($caption) ? ": $caption" : ""), $uploaded_by);

            return ['success' => true, 'path' => $relative_path];
        } catch (PDOException $e) {
            unlink($target_file);
            error_log("Database insert failed for evidence: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to save evidence information to database'];
        }
    }

    return ['success' => false, 'message' => 'Failed to move uploaded file. Check directory permissions.'];
}

/**
 * All evidence photos uploaded for a complaint, oldest first so
 * before/after pairs read naturally in a timeline.
 */
public function getComplaintEvidence($complaint_id) {
    try {
        $query = "SELECT e.*, u.full_name as uploaded_by_name 
                  FROM complaint_evidence e
                  LEFT JOIN users u ON e.uploaded_by = u.user_id
                  WHERE e.complaint_id = ?
                  ORDER BY e.created_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$complaint_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getComplaintEvidence error: " . $e->getMessage());
        return [];
    }
}

/**
 * The assigned staff member reports their work as done. This does NOT
 * mark the complaint resolved for the student yet - it flags it as
 * awaiting confirmation from whoever assigned it (or the department).
 */
public function markAssignmentResolved($complaint_id, $user_id, $message = '') {
    try {
        $check = $this->conn->prepare("SELECT assigned_to FROM complaints WHERE complaint_id = ?");
        $check->execute([$complaint_id]);
        $complaint = $check->fetch(PDO::FETCH_ASSOC);
        if (!$complaint || (int)$complaint['assigned_to'] !== (int)$user_id) {
            return ['success' => false, 'message' => 'Only the staff member assigned to this complaint can mark it resolved'];
        }

        $update = $this->conn->prepare("UPDATE complaints SET assignment_status = 'awaiting_confirmation' WHERE complaint_id = ?");
        $update->execute([$complaint_id]);

        $name_stmt = $this->conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $name_stmt->execute([$user_id]);
        $name = $name_stmt->fetchColumn() ?: 'the assigned staff member';

        $desc = "Marked as resolved by $name, pending confirmation";
        if (!empty($message)) $desc .= ": " . $message;
        $this->addTimelineEntry($complaint_id, 'in_progress', $desc, $user_id);

        // Notify whoever assigned it (fall back to notifying any staff in the department if unassigned-by is missing)
        $assigner_stmt = $this->conn->prepare("SELECT assigned_by, department_assigned FROM complaints WHERE complaint_id = ?");
        $assigner_stmt->execute([$complaint_id]);
        $info = $assigner_stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($info['assigned_by'])) {
            $this->sendStaffNotification($info['assigned_by'], $complaint_id, "$name has marked their assigned complaint as resolved. Please review and confirm.", 'assignment');
        }

        return ['success' => true];
    } catch (PDOException $e) {
        error_log("markAssignmentResolved error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

/**
 * The assigner (or, for complaints with no specific assigner on record,
 * any staff member in the department) confirms the work is genuinely
 * done. This is what finally marks the complaint resolved for the student.
 */
public function confirmResolution($complaint_id, $confirmed_by, $message = '') {
    try {
        $query = "UPDATE complaints SET status = 'resolved', assignment_status = 'resolved', resolved_at = NOW() WHERE complaint_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$complaint_id]);

        $name_stmt = $this->conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $name_stmt->execute([$confirmed_by]);
        $name = $name_stmt->fetchColumn() ?: 'the department';

        $desc = "Resolution confirmed by $name";
        if (!empty($message)) $desc .= ": " . $message;
        else $desc .= ". Your complaint has been resolved. Please confirm if you are satisfied.";
        $this->addTimelineEntry($complaint_id, 'resolved', $desc, $confirmed_by);

        // Notify the student(s) - mirrors the notification logic used for direct status updates
        $info_stmt = $this->conn->prepare("SELECT u.user_id, c.complaint_number, c.is_batch_complaint 
                                            FROM complaints c JOIN users u ON c.user_id = u.user_id 
                                            WHERE c.complaint_id = ?");
        $info_stmt->execute([$complaint_id]);
        $info = $info_stmt->fetch(PDO::FETCH_ASSOC);
        if ($info) {
            $evidence_check = $this->conn->prepare("SELECT COUNT(*) FROM complaint_evidence WHERE complaint_id = ?");
            $evidence_check->execute([$complaint_id]);
            $has_evidence = $evidence_check->fetchColumn() > 0;

            $notif = "Your complaint #{$info['complaint_number']} has been resolved" . (!empty($message) ? " - $message" : ".");
            if ($has_evidence) {
                $notif .= " Photos of the work done are attached to your complaint for you to review.";
            }
            $this->sendStudentNotification($info['user_id'], $complaint_id, $notif);
            if ($info['is_batch_complaint']) {
                $this->sendBulkStudentNotifications($complaint_id, $notif, $info['user_id']);
            }
        }

        // Let the assignee know their work was confirmed
        $assignee_stmt = $this->conn->prepare("SELECT assigned_to FROM complaints WHERE complaint_id = ?");
        $assignee_stmt->execute([$complaint_id]);
        $assigned_to = $assignee_stmt->fetchColumn();
        if ($assigned_to) {
            $this->sendStaffNotification($assigned_to, $complaint_id, "Your resolution was confirmed by $name. Great work!", 'assignment');
        }

        return ['success' => true];
    } catch (PDOException $e) {
        error_log("confirmResolution error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

/**
 * The assigner isn't satisfied with the reported resolution and sends
 * it back to the assignee for more work.
 */
public function reopenAssignment($complaint_id, $user_id, $reason = '') {
    try {
        $update = $this->conn->prepare("UPDATE complaints SET assignment_status = 'assigned' WHERE complaint_id = ?");
        $update->execute([$complaint_id]);

        $name_stmt = $this->conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $name_stmt->execute([$user_id]);
        $name = $name_stmt->fetchColumn() ?: 'the department';

        $desc = "Sent back for more work by $name";
        if (!empty($reason)) $desc .= ": " . $reason;
        $this->addTimelineEntry($complaint_id, 'in_progress', $desc, $user_id);

        $assignee_stmt = $this->conn->prepare("SELECT assigned_to FROM complaints WHERE complaint_id = ?");
        $assignee_stmt->execute([$complaint_id]);
        $assigned_to = $assignee_stmt->fetchColumn();
        if ($assigned_to) {
            $this->sendStaffNotification($assigned_to, $complaint_id, "Your resolution was sent back for more work" . (!empty($reason) ? ": $reason" : "."), 'assignment');
        }

        return ['success' => true];
    } catch (PDOException $e) {
        error_log("reopenAssignment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

// ========================================================================
// Supervisor: staff performance & department performance reporting
// ========================================================================

/**
 * Per-staff-member breakdown for a department: how many complaints are
 * currently assigned to each staff member, how many they've resolved,
 * how many are still pending, and their average resolution time.
 * Used on the Supervisor dashboard and in Supervisor reports.
 */
public function getDepartmentStaffPerformance($department) {
    try {
        $query = "SELECT u.user_id, u.full_name, u.email,
                    COUNT(c.complaint_id) as total_assigned,
                    SUM(CASE WHEN c.assignment_status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                    SUM(CASE WHEN c.assignment_status IN ('assigned','awaiting_confirmation') THEN 1 ELSE 0 END) as pending_count,
                    AVG(CASE WHEN c.assignment_status = 'resolved' AND c.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, c.assigned_at, c.resolved_at) ELSE NULL END) as avg_resolution_hours
                  FROM users u
                  LEFT JOIN complaints c ON c.assigned_to = u.user_id
                  WHERE u.role = 'staff' AND u.department = ?
                  GROUP BY u.user_id, u.full_name, u.email
                  ORDER BY u.full_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$department]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getDepartmentStaffPerformance error: " . $e->getMessage());
        return [];
    }
}

/**
 * Overall department performance summary for the Supervisor report:
 * volume, resolution rate, average resolution time, and a category
 * breakdown, scoped to a date range.
 */
public function getDepartmentPerformanceSummary($department, $date_from = null, $date_to = null) {
    try {
        $params = [$department];
        $date_clause = '';
        if ($date_from && $date_to) {
            $date_clause = " AND DATE(c.created_at) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }

        $summary_query = "SELECT 
                    COUNT(*) as total_complaints,
                    SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                    SUM(CASE WHEN c.assignment_status = 'unassigned' OR c.assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned_count,
                    AVG(CASE WHEN c.status = 'resolved' AND c.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as avg_resolution_hours
                  FROM complaints c WHERE c.department_assigned = ?" . $date_clause;
        $stmt = $this->conn->prepare($summary_query);
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)($summary['total_complaints'] ?? 0);
        $resolved = (int)($summary['resolved_count'] ?? 0);
        $summary['resolution_rate'] = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;

        $category_query = "SELECT cat.category_name, COUNT(*) as total
                  FROM complaints c
                  JOIN complaint_categories cat ON c.category_id = cat.category_id
                  WHERE c.department_assigned = ?" . $date_clause . "
                  GROUP BY cat.category_id ORDER BY total DESC LIMIT 10";
        $cat_stmt = $this->conn->prepare($category_query);
        $cat_stmt->execute($params);
        $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['summary' => $summary, 'categories' => $categories];
    } catch (PDOException $e) {
        error_log("getDepartmentPerformanceSummary error: " . $e->getMessage());
        return ['summary' => [], 'categories' => []];
    }
}

/**
 * Whether an operational department currently has a Supervisor account.
 * Complaint assignment is blocked (no one to do it) until one exists -
 * this helper lets admin screens surface that gap.
 */
public function departmentHasSupervisor($department) {
    try {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'supervisor' AND department = ? AND is_active = 1");
        $stmt->execute([$department]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("departmentHasSupervisor error: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify every active supervisor of a department that a new complaint has
 * just come in. Called right after a complaint is submitted, so a
 * department's supervisor(s) see an unread badge without having to keep
 * refreshing their dashboard.
 */
public function notifyDepartmentSupervisors($department, $complaint_id, $message) {
    try {
        $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE role = 'supervisor' AND department = ? AND is_active = 1");
        $stmt->execute([$department]);
        $supervisor_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($supervisor_ids as $supervisor_id) {
            $this->sendStaffNotification($supervisor_id, $complaint_id, $message, 'new_complaint');
        }
        return true;
    } catch (PDOException $e) {
        error_log("notifyDepartmentSupervisors error: " . $e->getMessage());
        return false;
    }
}

/**
 * Detailed complaint list for a single staff member, for the Supervisor's
 * per-staff report drill-down (as opposed to the aggregate summary row
 * returned by getDepartmentStaffPerformance).
 */
public function getStaffComplaintDetails($staff_user_id, $date_from = null, $date_to = null) {
    try {
        $params = [$staff_user_id];
        $date_clause = '';
        if ($date_from && $date_to) {
            $date_clause = " AND DATE(c.created_at) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }
        $query = "SELECT c.complaint_id, c.complaint_number, c.title, c.status, c.assignment_status,
                    c.priority, c.assigned_at, c.resolved_at, c.created_at,
                    cat.category_name
                  FROM complaints c
                  LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                  WHERE c.assigned_to = ?" . $date_clause . "
                  ORDER BY c.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getStaffComplaintDetails error: " . $e->getMessage());
        return [];
    }
}

}
?>