<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Check if user has class_rep role
if (!Security::hasRole('class_rep')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$cm = new ComplaintManager();

if (isset($_POST['get_suggestion'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($title) || empty($description)) {
        echo json_encode(['success' => false, 'error' => 'Title and description required']);
        exit();
    }
    
    $suggested = $cm->suggestDepartment($title, $description);
    
    echo json_encode([
        'success' => true,
        'department' => $suggested
    ]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>