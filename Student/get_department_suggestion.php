<?php

require_once '../config/database.php';
require_once '../config/complaint_functions.php';

header('Content-Type: application/json');

$cm = new ComplaintManager();

if (isset($_POST['get_suggestion'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    
    $suggested = $cm->suggestDepartment($title, $description);
    
    echo json_encode([
        'success' => true,
        'department' => $suggested
    ]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>