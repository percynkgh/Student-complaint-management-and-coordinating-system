// cron_check_escalations - Run this every hour via cron job or scheduled task
// For Windows Task Scheduler or Linux Crontab
// This script checks for complaints that need escalation and sends alerts

<?php

require_once 'config/database.php';
require_once 'config/complaint_functions.php';
require_once 'config/email_helper.php';

// Disable time limit for long-running processes
set_time_limit(0);

// Log file for cron jobs
$log_file = __DIR__ . '/logs/cron_escalations.log';

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}

/* Write to log file*/
function writeLog($message, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $log_message;
}

writeLog("========== ESCALATION CHECK STARTED ==========", $log_file);

try {
    $db = (new Database())->getConnection();
    $cm = new ComplaintManager();
    $email_helper = new EmailHelper();
    
    // Get current time for logging
    $current_time = date('Y-m-d H:i:s');
    writeLog("Current time: $current_time", $log_file);
    
    // Get active escalation rules
    $rules_query = "SELECT * FROM escalation_rules WHERE is_active = TRUE ORDER BY hours_threshold ASC";
    $rules_stmt = $db->query($rules_query);
    $rules = $rules_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    writeLog("Found " . count($rules) . " active escalation rules", $log_file);
    
    $total_escalated = 0;
    $escalation_details = [];
    
    foreach ($rules as $rule) {
        writeLog("Checking rule: Status '{$rule['status']}' -> Threshold {$rule['hours_threshold']}h -> New Priority '{$rule['new_priority']}'", $log_file);
        
        // Find complaints that match the rule and haven't been escalated recently
        $query = "SELECT c.*, u.full_name as student_name, u.email as student_email,
                  cat.category_name,
                  TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as hours_old
                  FROM complaints c
                  LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                  LEFT JOIN users u ON c.user_id = u.user_id
                  WHERE c.status = ? 
                    AND c.escalated = FALSE 
                    AND c.escalation_count < 3
                    AND c.created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
                  ORDER BY c.created_at ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$rule['status'], $rule['hours_threshold']]);
        $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        writeLog("Found " . count($complaints) . " complaints matching rule", $log_file);
        
        foreach ($complaints as $complaint) {
            // Calculate how many hours overdue
            $hours_overdue = $complaint['hours_old'] - $rule['hours_threshold'];
            
            writeLog("Processing complaint #{$complaint['complaint_number']} - {$complaint['hours_old']} hours old ({$hours_overdue} hours overdue)", $log_file);
            
            // Begin transaction for this complaint
            $db->beginTransaction();
            
            try {
                // Update complaint priority and escalation status
                $update = "UPDATE complaints 
                           SET priority = ?, 
                               escalated = TRUE, 
                               escalation_count = escalation_count + 1,
                               updated_at = NOW()
                           WHERE complaint_id = ?";
                
                $update_stmt = $db->prepare($update);
                $update_stmt->execute([$rule['new_priority'], $complaint['complaint_id']]);
                
                // Add to timeline
                $timeline_message = "⚠️ Auto-escalated to {$rule['new_priority']} priority after {$rule['hours_threshold']} hours (was {$hours_overdue} hours overdue)";
                $cm->addTimelineEntry(
                    $complaint['complaint_id'], 
                    'escalated', 
                    $timeline_message,
                    null,
                    true
                );
                
                // Create notification in database
                $create_notification = "INSERT INTO notifications (user_id, complaint_id, message, type, created_at) 
                                        SELECT user_id, ?, ?, 'escalation', NOW() 
                                        FROM users WHERE role = ?";
                $notify_stmt = $db->prepare($create_notification);
                $notify_stmt->execute([$complaint['complaint_id'], $timeline_message, $rule['notify_role']]);
                
                // Commit transaction
                $db->commit();
                
                // Send email alerts (outside transaction)
                $email_sent = $email_helper->sendEscalationAlert($complaint['complaint_id'], 1);
                
                // Also send email to assigned staff if any
                if (!empty($complaint['assigned_to'])) {
                    $staff_query = "SELECT email, full_name FROM users WHERE user_id = ?";
                    $staff_stmt = $db->prepare($staff_query);
                    $staff_stmt->execute([$complaint['assigned_to']]);
                    $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($staff) {
                        $staff_subject = "⚠️ Escalation Alert: Complaint #{$complaint['complaint_number']} assigned to you";
                        $staff_message = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                                .content { background: #f9f9f9; padding: 30px; }
                                .button { display: inline-block; padding: 12px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h2>⚠️ Complaint Escalation Alert</h2>
                                </div>
                                <div class='content'>
                                    <p>Hello <strong>{$staff['full_name']}</strong>,</p>
                                    <p>A complaint assigned to you has been automatically escalated:</p>
                                    <ul>
                                        <li><strong>Complaint #:</strong> {$complaint['complaint_number']}</li>
                                        <li><strong>Title:</strong> " . htmlspecialchars($complaint['title']) . "</li>
                                        <li><strong>Student:</strong> {$complaint['student_name']}</li>
                                        <li><strong>New Priority:</strong> {$rule['new_priority']}</li>
                                        <li><strong>Hours Old:</strong> {$complaint['hours_old']} hours</li>
                                        <li><strong>Reason:</strong> Exceeded {$rule['hours_threshold']} hours in '{$rule['status']}' status</li>
                                    </ul>
                                    <p style='text-align: center;'>
                                        <a href='http://{$_SERVER['HTTP_HOST']}/SCCS/staff/view_complaint.php?id={$complaint['complaint_id']}' class='button'>Review Complaint</a>
                                    </p>
                                </div>
                            </div>
                        </body>
                        </html>";
                        
                        $email_helper->sendEmail($staff['email'], $staff_subject, $staff_message);
                    }
                }
                
                $total_escalated++;
                $escalation_details[] = "• #{$complaint['complaint_number']} - {$complaint['title']} - New Priority: {$rule['new_priority']} - Notified: {$rule['notify_role']}";
                
                writeLog("✓ Escalated complaint #{$complaint['complaint_number']} to {$rule['new_priority']} priority", $log_file);
                
            } catch (Exception $e) {
                $db->rollBack();
                writeLog("❌ Error escalating complaint #{$complaint['complaint_number']}: " . $e->getMessage(), $log_file);
            }
        }
    }
    
    // Send summary email to admin if any escalations occurred
    if ($total_escalated > 0) {
        writeLog("Total escalations: $total_escalated", $log_file);
        
        // Get admin emails
        $admin_query = "SELECT email, full_name FROM users WHERE role IN ('admin', 'dean', 'principal')";
        $admin_stmt = $db->query($admin_query);
        $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $summary_subject = "⚠️ Escalation Summary - {$total_escalated} Complaints Escalated";
        
        $escalation_list = implode("<br>", $escalation_details);
        
        foreach ($admins as $admin) {
            $summary_message = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                    .content { background: #f9f9f9; padding: 30px; }
                    .summary { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2> Escalation Summary Report</h2>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>{$admin['full_name']}</strong>,</p>
                        <p>The auto-escalation system has processed <strong>{$total_escalated}</strong> complaints in this run.</p>
                        
                        <div class='summary'>
                            <h3>Escalated Complaints:</h3>
                            <p>{$escalation_list}</p>
                        </div>
                        
                        <p>Please review these escalated complaints in the admin dashboard.</p>
                        <p style='text-align: center;'>
                            <a href='http://{$_SERVER['HTTP_HOST']}/SCCS/admin/complaint_management.php?priority=urgent' style='display: inline-block; padding: 12px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px;'>View All Escalated Complaints</a>
                        </p>
                    </div>
                </div>
            </body>
            </html>";
            
            $email_helper->sendEmail($admin['email'], $summary_subject, $summary_message);
        }
    }
    
    writeLog("========== ESCALATION CHECK COMPLETED ==========", $log_file);
    writeLog("Total complaints escalated: $total_escalated", $log_file);
    
} catch (Exception $e) {
    writeLog("FATAL ERROR: " . $e->getMessage(), $log_file);
    writeLog("Stack trace: " . $e->getTraceAsString(), $log_file);
}

// Also check for complaints that are about to escalate (warning stage)
try {
    writeLog("Checking for complaints approaching escalation threshold...", $log_file);
    
    $warning_rules_query = "SELECT * FROM escalation_rules WHERE is_active = TRUE";
    $warning_stmt = $db->query($warning_rules_query);
    $warning_rules = $warning_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($warning_rules as $rule) {
        // Find complaints that are at 80% of threshold
        $warning_hours = $rule['hours_threshold'] * 0.8;
        
        $query = "SELECT c.*, u.full_name as student_name, u.email as student_email,
                  TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as hours_old
                  FROM complaints c
                  JOIN users u ON c.user_id = u.user_id
                  WHERE c.status = ? 
                    AND c.escalated = FALSE
                    AND c.escalation_count = 0
                    AND c.created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
                    AND c.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                  ORDER BY c.created_at ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$rule['status'], $warning_hours, $rule['hours_threshold']]);
        $warning_complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($warning_complaints)) {
            writeLog("Found " . count($warning_complaints) . " complaints approaching escalation for status '{$rule['status']}'", $log_file);
            
            foreach ($warning_complaints as $complaint) {
                $hours_until = $rule['hours_threshold'] - $complaint['hours_old'];
                
                // Create warning notification
                $warning_message = " Warning: Complaint #{$complaint['complaint_number']} will escalate in approximately " . round($hours_until) . " hours";
                
                $create_warning = "INSERT INTO notifications (user_id, complaint_id, message, type, created_at) 
                                    SELECT user_id, ?, ?, 'warning', NOW() 
                                    FROM users WHERE role IN ('admin', 'staff')";
                $warning_stmt = $db->prepare($create_warning);
                $warning_stmt->execute([$complaint['complaint_id'], $warning_message]);
                
                writeLog("  - Warning created for #{$complaint['complaint_number']} (escalates in {$hours_until}h)", $log_file);
            }
        }
    }
    
} catch (Exception $e) {
    writeLog("Warning check error: " . $e->getMessage(), $log_file);
}

writeLog("========== CRON JOB FINISHED ==========", $log_file);
echo "\n";
?>