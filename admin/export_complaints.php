<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('admin') && !Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();

// Get selected IDs or all complaints
$selected_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
$export_all = isset($_GET['all']);

// Build query
if ($export_all) {
    $query = "SELECT c.*, cat.category_name, u.full_name as student_name, u.email, u.student_id
              FROM complaints c
              LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
              LEFT JOIN users u ON c.user_id = u.user_id
              ORDER BY c.created_at DESC";
    $stmt = $db->query($query);
} elseif (!empty($selected_ids)) {
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    $query = "SELECT c.*, cat.category_name, u.full_name as student_name, u.email, u.student_id
              FROM complaints c
              LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
              LEFT JOIN users u ON c.user_id = u.user_id
              WHERE c.complaint_id IN ($placeholders)
              ORDER BY c.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($selected_ids);
} else {
    die("No complaints selected");
}

$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = "complaints_export_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'ID',
    'Complaint Number',
    'Title',
    'Description',
    'Category',
    'Priority',
    'Status',
    'Department Assigned',
    'Student Name',
    'Student ID',
    'Student Email',
    'Location Building',
    'Location Room',
    'Affected Count',
    'Is Batch',
    'Created Date',
    'Resolved Date',
    'Upvote Count'
]);

// Add data rows
foreach ($complaints as $c) {
    fputcsv($output, [
        $c['complaint_id'],
        $c['complaint_number'],
        $c['title'],
        strip_tags($c['description']), // Remove HTML tags
        $c['category_name'] ?? 'N/A',
        $c['priority'],
        $c['status'],
        $c['department_assigned'] ?? 'Unassigned',
        $c['student_name'],
        $c['student_id'],
        $c['email'],
        $c['location_building'] ?? '',
        $c['location_room'] ?? '',
        $c['affected_count'],
        $c['is_batch_complaint'] ? 'Yes' : 'No',
        $c['created_at'],
        $c['resolved_at'] ?? '',
        $c['upvote_count'] ?? 0
    ]);
}

fclose($output);
exit();