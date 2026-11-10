<?php

session_start();

// Update session to inactive
if (isset($_SESSION['user_id'])) {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "UPDATE user_sessions SET is_active = FALSE WHERE session_token = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([session_id()]);
}

// Destroy session
$_SESSION = array();
session_destroy();

// Redirect to login
header("Location: login.php");
exit();
?>