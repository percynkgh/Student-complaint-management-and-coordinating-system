<?php
// config/email_helper.php
require_once 'email_config.php';

class EmailHelper {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * Send email using PHP mail() or SMTP
     */
    public function sendEmail($to, $subject, $message, $is_html = true) {
        $from = EmailConfig::$from_email;
        $from_name = EmailConfig::$from_name;
        
        if ($is_html) {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        
        $headers .= "From: $from_name <$from>\r\n";
        $headers .= "Reply-To: $from\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // For local development with XAMPP
        if (!EmailConfig::$use_smtp) {
            return mail($to, $subject, $message, $headers);
        } else {
            // For production with SMTP
            return $this->sendSMTP($to, $subject, $message, $headers);
        }
    }
    
    /**
     * Send email via SMTP (for production)
     */
    private function sendSMTP($to, $subject, $message, $headers) {
        // This would use PHPMailer or similar library
        // For now, we'll implement a basic socket SMTP
        // In production, use PHPMailer instead
        
        error_log("SMTP would be used here for: $to");
        return true; // Placeholder
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordReset($email, $full_name, $token) {
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/SCCS/reset_password.php?token=" . $token;
        
        $subject = "Password Reset Request - " . EmailConfig::$company_name;
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 30px; font-size: 12px; color: #999; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Password Reset Request</h2>
                </div>
                <div class='content'>
                    <p>Hello <strong>$full_name</strong>,</p>
                    <p>We received a request to reset your password for your account at " . EmailConfig::$company_name . ".</p>
                    <p>Click the button below to reset your password. This link will expire in 1 hour.</p>
                    <p style='text-align: center;'>
                        <a href='$reset_link' class='button'>Reset Password</a>
                    </p>
                    <p>If you didn't request this, please ignore this email or contact support if you have concerns.</p>
                    <p>For security, this link will expire in 1 hour.</p>
                    <p>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p style='word-break: break-all;'><small>$reset_link</small></p>
                </div>
                <div class='footer'>
                    <p>&copy; 2024 " . EmailConfig::$company_name . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($email, $subject, $message);
    }
    
    /**
     * Send complaint status update email
     */
    public function sendStatusUpdate($complaint_id, $user_email, $user_name, $new_status, $update_message) {
        // Get complaint details
        $query = "SELECT c.*, cat.category_name 
                  FROM complaints c
                  LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                  WHERE c.complaint_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$complaint_id]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$complaint) {
            return false;
        }
        
        $view_link = "http://" . $_SERVER['HTTP_HOST'] . "/SCCS/student/view_complaint.php?id=" . $complaint_id;
        
        $subject = "Complaint #{$complaint['complaint_number']} Status Update";
        
        $status_colors = [
            'submitted' => '#3498db',
            'under_review' => '#f39c12',
            'in_progress' => '#9b59b6',
            'resolved' => '#27ae60',
            'rejected' => '#e74c3c',
            'closed' => '#95a5a6'
        ];
        
        $color = $status_colors[$new_status] ?? '#3498db';
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .status-box { background: $color; color: white; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center; font-size: 18px; font-weight: bold; }
                .complaint-info { background: white; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #667eea; }
                .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 30px; font-size: 12px; color: #999; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Complaint Status Update</h2>
                </div>
                <div class='content'>
                    <p>Hello <strong>$user_name</strong>,</p>
                    <p>Your complaint has been updated:</p>
                    
                    <div class='complaint-info'>
                        <p><strong>Complaint #:</strong> {$complaint['complaint_number']}</p>
                        <p><strong>Title:</strong> " . htmlspecialchars($complaint['title']) . "</p>
                        <p><strong>Category:</strong> {$complaint['category_name']}</p>
                    </div>
                    
                    <div class='status-box'>
                        New Status: " . ucfirst(str_replace('_', ' ', $new_status)) . "
                    </div>
                    
                    <p><strong>Update Message:</strong></p>
                    <p>" . nl2br(htmlspecialchars($update_message)) . "</p>
                    
                    <p style='text-align: center;'>
                        <a href='$view_link' class='button'>View Complaint</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; 2024 " . EmailConfig::$company_name . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send batch complaint notification to all affected students
     */
    public function sendBatchNotification($complaint_id, $message_type = 'created') {
        // Get complaint details
        $query = "SELECT c.*, cat.category_name, u.full_name as rep_name 
                  FROM complaints c
                  LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                  LEFT JOIN users u ON c.user_id = u.user_id
                  WHERE c.complaint_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$complaint_id]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$complaint || !$complaint['is_batch_complaint']) {
            return false;
        }
        
        // Get all affected students
        $query = "SELECT u.email, u.full_name, bm.endorsement_status
                  FROM batch_members bm
                  JOIN users u ON bm.user_id = u.user_id
                  WHERE bm.complaint_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$complaint_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $view_link = "http://" . $_SERVER['HTTP_HOST'] . "/SCCS/student/view_complaint.php?id=" . $complaint_id;
        
        $subject = $message_type == 'created' 
            ? "New Batch Complaint #{$complaint['complaint_number']} - Action Required"
            : "Update: Batch Complaint #{$complaint['complaint_number']}";
        
        $success_count = 0;
        
        foreach ($students as $student) {
            $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .complaint-info { background: white; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #667eea; }
                    .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { margin-top: 30px; font-size: 12px; color: #999; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>" . ($message_type == 'created' ? 'New Batch Complaint' : 'Batch Complaint Update') . "</h2>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>{$student['full_name']}</strong>,</p>";
            
            if ($message_type == 'created') {
                $message .= "
                        <p>Your class representative <strong>" . htmlspecialchars($complaint['rep_name']) . "</strong> has submitted a batch complaint that includes you as an affected student.</p>
                        <p><strong>Please confirm your involvement by endorsing this complaint.</strong></p>";
            } else {
                $message .= "
                        <p>There has been an update to a batch complaint that affects you.</p>";
            }
            
            $message .= "
                        <div class='complaint-info'>
                            <p><strong>Complaint #:</strong> {$complaint['complaint_number']}</p>
                            <p><strong>Title:</strong> " . htmlspecialchars($complaint['title']) . "</p>
                            <p><strong>Status:</strong> " . ucfirst(str_replace('_', ' ', $complaint['status'])) . "</p>
                            <p><strong>Priority:</strong> " . strtoupper($complaint['priority']) . "</p>
                        </div>
                        
                        <p style='text-align: center;'>
                            <a href='$view_link' class='button'>View Complaint Details</a>
                        </p>
                        
                        <p>Thank you for your participation in improving our institution.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2024 " . EmailConfig::$company_name . ". All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            if ($this->sendEmail($student['email'], $subject, $message)) {
                $success_count++;
            }
        }
        
        return $success_count;
    }
    
    /**
     * Send escalation alert to staff/admin
     */
    public function sendEscalationAlert($complaint_id, $escalation_level) {
        // Get complaint details
        $query = "SELECT c.*, u.full_name as student_name, u.email as student_email
                  FROM complaints c
                  JOIN users u ON c.user_id = u.user_id
                  WHERE c.complaint_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$complaint_id]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$complaint) {
            return false;
        }
        
        // Get admins and relevant staff
        $query = "SELECT email, full_name FROM users WHERE role IN ('admin', 'dean', 'principal')";
        $stmt = $this->conn->query($query);
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $view_link = "http://" . $_SERVER['HTTP_HOST'] . "/SCCS/admin/complaint_management.php?id=" . $complaint_id;
        
        $subject = "⚠️ ESCALATION ALERT: Complaint #{$complaint['complaint_number']}";
        
        $success_count = 0;
        
        foreach ($admins as $admin) {
            $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .warning-box { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107; }
                    .button { display: inline-block; padding: 12px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { margin-top: 30px; font-size: 12px; color: #999; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>⚠️ Complaint Escalation Alert</h2>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>{$admin['full_name']}</strong>,</p>
                        
                        <div class='warning-box'>
                            <strong>A complaint has been automatically escalated to level {$escalation_level}.</strong>
                        </div>
                        
                        <p><strong>Complaint Details:</strong></p>
                        <ul>
                            <li><strong>Number:</strong> #{$complaint['complaint_number']}</li>
                            <li><strong>Title:</strong> " . htmlspecialchars($complaint['title']) . "</li>
                            <li><strong>Student:</strong> " . htmlspecialchars($complaint['student_name']) . "</li>
                            <li><strong>Priority:</strong> " . strtoupper($complaint['priority']) . "</li>
                            <li><strong>Current Status:</strong> " . ucfirst(str_replace('_', ' ', $complaint['status'])) . "</li>
                            <li><strong>Submitted:</strong> " . date('M j, Y g:i A', strtotime($complaint['created_at'])) . "</li>
                        </ul>
                        
                        <p>This complaint requires immediate attention as it has exceeded the expected resolution timeframe.</p>
                        
                        <p style='text-align: center;'>
                            <a href='$view_link' class='button'>Review Complaint</a>
                        </p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2024 " . EmailConfig::$company_name . ". All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            if ($this->sendEmail($admin['email'], $subject, $message)) {
                $success_count++;
            }
        }
        
        return $success_count;
    }
    /**
 * Send meeting invitation
 */
public function sendMeetingInvite($attendee_email, $attendee_name, $meeting_details) {
    $subject = "Meeting Invitation: {$meeting_details['title']}";
    
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
            .calendar-links { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Meeting Invitation</h2>
            </div>
            <div class='content'>
                <p>Hello <strong>{$attendee_name}</strong>,</p>
                <p>You have been invited to a meeting regarding Complaint #{$meeting_details['complaint_number']}.</p>
                
                <div class='meeting-details'>
                    <h3>{$meeting_details['title']}</h3>
                    <p><strong>Date:</strong> {$meeting_details['date']}</p>
                    <p><strong>Time:</strong> {$meeting_details['time']}</p>
                    <p><strong>Duration:</strong> {$meeting_details['duration']} minutes</p>
                    <p><strong>Platform:</strong> " . ucfirst(str_replace('_', ' ', $meeting_details['type'])) . "</p>
                    <p><strong>Meeting Link:</strong> <a href='{$meeting_details['link']}'>{$meeting_details['link']}</a></p>
                    " . ($meeting_details['password'] ? "<p><strong>Password:</strong> {$meeting_details['password']}</p>" : "") . "
                    " . ($meeting_details['description'] ? "<p><strong>Description:</strong> {$meeting_details['description']}</p>" : "") . "
                </div>
                
                <p style='text-align: center;'>
                    <a href='{$meeting_details['link']}' class='button'>Join Meeting</a>
                </p>
                
                <div class='calendar-links'>
                    <p><strong>Add to Calendar:</strong></p>
                    <p>
                        <a href='{$meeting_details['google_calendar_link']}'>Google Calendar</a> |
                        <a href='{$meeting_details['outlook_link']}'>Outlook</a> |
                        <a href='{$meeting_details['ics_link']}'>iCal</a>
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    return $this->sendEmail($attendee_email, $subject, $message);
}
}
?>