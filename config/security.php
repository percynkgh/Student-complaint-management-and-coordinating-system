<?php
// config/security.php
session_start();

class Security {
    
    // Generate CSRF token
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    // Verify CSRF token
    public static function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            die('Invalid CSRF token');
        }
        return true;
    }
    
    // Sanitize input
    public static function sanitize($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
    
    // Validate email
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    // Hash password
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    
    // Check if user is logged in
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    // Check user role
    public static function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    // Redirect if not logged in
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: ../auth/login.php");
            exit();
        }
    }
    
    // Redirect if not authorized
    public static function requireRole($role) {
        self::requireLogin();
        if (!self::hasRole($role)) {
            header("Location: /SCCS/auth/unauthorized.php");
            exit();
        }
    }
}
?>