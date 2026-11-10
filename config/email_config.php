<?php
// config/email_config.php
// Email configuration settings

// For local development with XAMPP, we'll use PHP's built-in mail()
// For production, you'd use SMTP settings

class EmailConfig {
    // For local testing with XAMPP, mail() works out of the box
    public static $use_smtp = false;
    
    // SMTP settings (for production - e.g., Gmail, SendGrid, etc.)
    public static $smtp_host = 'smtp.gmail.com';
    public static $smtp_port = 587;
    public static $smtp_username = 'your-email@gmail.com';
    public static $smtp_password = 'your-app-password'; // Use app-specific password
    public static $smtp_encryption = 'tls';
    
    // Email settings
    public static $from_email = 'noreply@studentcomplaint.system';
    public static $from_name = 'Student Complaint System';
    public static $company_name = 'Student Complaint Support System';
}
?>