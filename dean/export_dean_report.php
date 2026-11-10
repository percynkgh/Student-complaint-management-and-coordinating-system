<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Get department performance data
$query = "SELECT 
            c.department_assigned as department,
            COUNT(*) as total_complaints,
            SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            ROUND((SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as resolution_rate,
            AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as avg_resolution_hours,
            MIN(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as fastest_resolution,
            MAX(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as slowest_resolution,
            dr.avg_overall as satisfaction_score
          FROM complaints c
          LEFT JOIN department_ratings dr ON dr.department = c.department_assigned
          WHERE DATE(c.created_at) BETWEEN ? AND ?
          AND c.department_assigned IS NOT NULL
          GROUP BY c.department_assigned
          ORDER BY avg_resolution_hours ASC";

$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = "dean_report_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

// Add headers
fputcsv($output, [
    'Department',
    'Total Complaints',
    'Resolved',
    'Resolution Rate (%)',
    'Avg Resolution Time',
    'Fastest',
    'Slowest',
    'Satisfaction Score'
]);

// Add data
foreach ($departments as $dept) {
    fputcsv($output, [
        $dept['department'],
        $dept['total_complaints'],
        $dept['resolved'],
        $dept['resolution_rate'],
        formatDuration($dept['avg_resolution_hours'] ?? null),
        formatDuration($dept['fastest_resolution'] ?? null),
        formatDuration($dept['slowest_resolution'] ?? null),
        round($dept['satisfaction_score'] ?? 0, 1)
    ]);
}

fclose($output);
exit();
?>