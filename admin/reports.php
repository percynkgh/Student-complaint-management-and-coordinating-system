<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

// Load Composer autoloader if it exists
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Dompdf\Dompdf;
use Dompdf\Options;

Security::requireLogin();
if (!Security::hasRole('admin') && !Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$cm = new ComplaintManager();

// Get report parameters
$report_type = $_GET['type'] ?? 'summary';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$department = $_GET['department'] ?? 'all';
$format = $_GET['format'] ?? 'web';

// Get departments for filter
$dept_query = "SELECT dept_name FROM departments WHERE dept_type = 'operational' ORDER BY dept_name";
$dept_stmt = $db->query($dept_query);
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate report data based on type
$report_data = [];

switch ($report_type) {
    case 'summary':
        $report_data = getSummaryReport($db, $date_from, $date_to, $department);
        break;
    case 'department':
        $report_data = getDepartmentReport($db, $date_from, $date_to);
        break;
    case 'priority':
        $report_data = getPriorityReport($db, $date_from, $date_to, $department);
        break;
    case 'trends':
        $report_data = getTrendsReport($db, $date_from, $date_to);
        break;
    case 'satisfaction':
        $report_data = getSatisfactionReport($db, $date_from, $date_to);
        break;
    case 'performance':
        $report_data = getPerformanceReport($db, $date_from, $date_to);
        break;
    case 'academic':
        $report_data = getAcademicReport($db, $date_from, $date_to, $department);
        break;
}

// Handle export
if (isset($_GET['export']) && in_array($format, ['pdf', 'excel', 'csv'])) {
    exportReport($report_data, $report_type, $format, $date_from, $date_to, $department);
}

/**
 * Get summary report data
 */
function getSummaryReport($db, $date_from, $date_to, $department) {
    $dept_filter = $department != 'all' ? "AND c.department_assigned = :department" : "";
    
    // Overall stats
    $query = "SELECT 
                COUNT(*) as total_complaints,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                AVG(CASE WHEN status = 'resolved' THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) ELSE NULL END) as avg_resolution_hours,
                MIN(CASE WHEN status = 'resolved' THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) ELSE NULL END) as min_resolution_hours,
                MAX(CASE WHEN status = 'resolved' THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) ELSE NULL END) as max_resolution_hours
              FROM complaints c
              WHERE DATE(c.created_at) BETWEEN :date_from AND :date_to
              $dept_filter";
    
    $stmt = $db->prepare($query);
    $params = ['date_from' => $date_from, 'date_to' => $date_to];
    if ($department != 'all') {
        $params['department'] = $department;
    }
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Priority breakdown
    $query = "SELECT 
                priority,
                COUNT(*) as count
              FROM complaints c
              WHERE DATE(c.created_at) BETWEEN :date_from AND :date_to
              $dept_filter
              GROUP BY priority";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $priority_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily trends
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
              FROM complaints c
              WHERE DATE(c.created_at) BETWEEN :date_from AND :date_to
              $dept_filter
              GROUP BY DATE(created_at)
              ORDER BY date";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $daily_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'stats' => $stats,
        'priority_stats' => $priority_stats,
        'daily_trends' => $daily_trends
    ];
}

/**
 * Get department performance report
 */
function getDepartmentReport($db, $date_from, $date_to) {
    $query = "SELECT 
                c.department_assigned as department,
                COUNT(*) as total_complaints,
                SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as avg_resolution_hours,
                dr.avg_overall as satisfaction_score,
                dr.total_ratings
              FROM complaints c
              LEFT JOIN department_ratings dr ON dr.department = c.department_assigned
              WHERE DATE(c.created_at) BETWEEN :date_from AND :date_to
              AND c.department_assigned IS NOT NULL
              GROUP BY c.department_assigned
              ORDER BY avg_resolution_hours ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['date_from' => $date_from, 'date_to' => $date_to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get priority analysis report
 */
function getPriorityReport($db, $date_from, $date_to, $department) {
    $dept_filter = $department != 'all' ? "AND c.department_assigned = :department" : "";
    
    $query = "SELECT 
                priority,
                COUNT(*) as total,
                AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_resolution_hours,
                MIN(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as min_hours,
                MAX(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as max_hours
              FROM complaints c
              WHERE DATE(created_at) BETWEEN :date_from AND :date_to
              AND status = 'resolved'
              $dept_filter
              GROUP BY priority
              ORDER BY 
                CASE priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END";
    
    $stmt = $db->prepare($query);
    $params = ['date_from' => $date_from, 'date_to' => $date_to];
    if ($department != 'all') {
        $params['department'] = $department;
    }
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get trends report
 */
function getTrendsReport($db, $date_from, $date_to) {
    // Monthly trends for the last 12 months
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                AVG(CASE WHEN status = 'resolved' THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) ELSE NULL END) as avg_resolution_hours
              FROM complaints
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month DESC";
    
    $stmt = $db->query($query);
    $monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Category trends
    $query = "SELECT 
                cat.category_name,
                COUNT(*) as total
              FROM complaints c
              JOIN complaint_categories cat ON c.category_id = cat.category_id
              WHERE DATE(c.created_at) BETWEEN :date_from AND :date_to
              GROUP BY cat.category_name
              ORDER BY total DESC
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['date_from' => $date_from, 'date_to' => $date_to]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'monthly' => $monthly,
        'categories' => $categories
    ];
}

/**
 * Get satisfaction report
 */
function getSatisfactionReport($db, $date_from, $date_to) {
    $query = "SELECT 
                r.*,
                c.complaint_number,
                c.title,
                u.full_name as student_name,
                cat.category_name
              FROM complaint_ratings r
              JOIN complaints c ON r.complaint_id = c.complaint_id
              JOIN users u ON r.user_id = u.user_id
              LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
              WHERE DATE(r.created_at) BETWEEN :date_from AND :date_to
              ORDER BY r.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['date_from' => $date_from, 'date_to' => $date_to]);
    $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Average scores
    $query = "SELECT 
                AVG(speed_rating) as avg_speed,
                AVG(communication_rating) as avg_communication,
                AVG(effectiveness_rating) as avg_effectiveness,
                AVG(overall_rating) as avg_overall,
                COUNT(*) as total_ratings
              FROM complaint_ratings
              WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['date_from' => $date_from, 'date_to' => $date_to]);
    $averages = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'ratings' => $ratings,
        'averages' => $averages
    ];
}

/**
 * Get staff performance report
 */
function getPerformanceReport($db, $date_from, $date_to) {
    $query = "SELECT 
                u.full_name as staff_name,
                u.department,
                COUNT(DISTINCT c.complaint_id) as assigned_complaints,
                SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as avg_resolution_hours
              FROM users u
              LEFT JOIN complaints c ON c.assigned_to = u.user_id AND DATE(c.created_at) BETWEEN :date_from AND :date_to
              WHERE u.role = 'staff'
              GROUP BY u.user_id
              ORDER BY resolved DESC, avg_resolution_hours ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['date_from' => $date_from, 'date_to' => $date_to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get academic breakdown report - complaints grouped by the
 * submitting student's academic department, course, class and year.
 * Gives admin insight into which classes/cohorts complain most,
 * mirroring the department/course/class/year scoping now used for
 * batch complaints.
 */
function getAcademicReport($db, $date_from, $date_to, $department = 'all') {
    // By academic department
    $dept_query = "SELECT 
                u.department as academic_department,
                COUNT(*) as total,
                SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as avg_resolution_hours,
                SUM(CASE WHEN c.is_batch_complaint = 1 THEN 1 ELSE 0 END) as batch_count
              FROM complaints c
              JOIN users u ON c.user_id = u.user_id
              WHERE DATE(c.created_at) BETWEEN :date_from AND :date_to
              AND u.department IS NOT NULL AND u.department != ''
              GROUP BY u.department
              ORDER BY total DESC";
    $stmt = $db->prepare($dept_query);
    $stmt->execute(['date_from' => $date_from, 'date_to' => $date_to]);
    $by_department = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // By year of study
    $year_query = "SELECT 
                u.year_of_study,
                COUNT(*) as total,
                SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved
              FROM complaints c
              JOIN users u ON c.user_id = u.user_id
              WHERE DATE(c.created_at) BETWEEN :date_from AND :date_to
              AND u.year_of_study IS NOT NULL
              GROUP BY u.year_of_study
              ORDER BY u.year_of_study ASC";
    $stmt = $db->prepare($year_query);
    $stmt->execute(['date_from' => $date_from, 'date_to' => $date_to]);
    $by_year = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // By class (department + course + class_code + year) - fully scoped classes only
    $class_query = "SELECT 
                u.department, u.course, u.class_code, u.year_of_study,
                COUNT(*) as total,
                SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN c.is_batch_complaint = 1 THEN 1 ELSE 0 END) as batch_count,
                COUNT(DISTINCT c.user_id) as distinct_submitters
              FROM complaints c
              JOIN users u ON c.user_id = u.user_id
              WHERE DATE(c.created_at) BETWEEN :date_from AND :date_to
              AND u.department IS NOT NULL AND u.course IS NOT NULL
              AND u.class_code IS NOT NULL AND u.year_of_study IS NOT NULL";
    $class_params = ['date_from' => $date_from, 'date_to' => $date_to];
    if ($department != 'all') {
        $class_query .= " AND u.department = :department";
        $class_params['department'] = $department;
    }
    $class_query .= " GROUP BY u.department, u.course, u.class_code, u.year_of_study
              ORDER BY total DESC
              LIMIT 25";
    $stmt = $db->prepare($class_query);
    $stmt->execute($class_params);
    $by_class = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Most active class reps (batch complaints)
    $rep_query = "SELECT 
                u.full_name, u.department, u.course, u.class_code, u.year_of_study,
                COUNT(*) as batch_complaints,
                SUM(c.affected_count) as students_represented
              FROM complaints c
              JOIN users u ON c.user_id = u.user_id
              WHERE u.role = 'class_rep' AND c.is_batch_complaint = 1
              AND DATE(c.created_at) BETWEEN :date_from AND :date_to
              GROUP BY c.user_id
              ORDER BY batch_complaints DESC
              LIMIT 15";
    $stmt = $db->prepare($rep_query);
    $stmt->execute(['date_from' => $date_from, 'date_to' => $date_to]);
    $class_reps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'by_department' => $by_department,
        'by_year' => $by_year,
        'by_class' => $by_class,
        'class_reps' => $class_reps
    ];
}


function exportReport($data, $type, $format, $date_from, $date_to, $department) {
    $filename = "report_{$type}_" . date('Y-m-d_His');
    
    switch ($format) {
        case 'excel':
            exportToExcel($data, $type, $filename, $date_from, $date_to, $department);
            break;
        case 'csv':
            exportToCSV($data, $type, $filename, $date_from, $date_to, $department);
            break;
        case 'pdf':
            exportToPDF($data, $type, $filename, $date_from, $date_to, $department);
            break;
    }
}

/**
 * Export to Excel using PhpSpreadsheet
 */
function exportToExcel($data, $type, $filename, $date_from, $date_to, $department) {
    // Check if PhpSpreadsheet is available
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        die('PhpSpreadsheet library not found. Please run: composer require phpoffice/phpspreadsheet');
    }
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator("Complaint System")
        ->setLastModifiedBy("Admin")
        ->setTitle("Report: " . ucfirst($type))
        ->setSubject("Complaint Report")
        ->setDescription("Generated on " . date('Y-m-d H:i:s'));
    
    // Add header
    $sheet->setCellValue('A1', 'COMPLAINT SYSTEM REPORT');
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Add report info
    $sheet->setCellValue('A3', 'Report Type:');
    $sheet->setCellValue('B3', ucfirst($type));
    $sheet->setCellValue('A4', 'Date Range:');
    $sheet->setCellValue('B4', "$date_from to $date_to");
    $sheet->setCellValue('A5', 'Generated:');
    $sheet->setCellValue('B5', date('Y-m-d H:i:s'));
    
    $row = 7;
    
    // Add data based on report type
    switch ($type) {
        case 'summary':
            $sheet->setCellValue('A7', 'Metric');
            $sheet->setCellValue('B7', 'Value');
            $sheet->getStyle('A7:B7')->getFont()->setBold(true);
            
            $row = 8;
            $stats = $data['stats'];
            $sheet->setCellValue('A' . $row, 'Total Complaints');
            $sheet->setCellValue('B' . $row++, $stats['total_complaints'] ?? 0);
            $sheet->setCellValue('A' . $row, 'Resolved');
            $sheet->setCellValue('B' . $row++, $stats['resolved'] ?? 0);
            $sheet->setCellValue('A' . $row, 'In Progress');
            $sheet->setCellValue('B' . $row++, $stats['in_progress'] ?? 0);
            $sheet->setCellValue('A' . $row, 'Under Review');
            $sheet->setCellValue('B' . $row++, $stats['under_review'] ?? 0);
            $sheet->setCellValue('A' . $row, 'Submitted');
            $sheet->setCellValue('B' . $row++, $stats['submitted'] ?? 0);
            $sheet->setCellValue('A' . $row, 'Rejected');
            $sheet->setCellValue('B' . $row++, $stats['rejected'] ?? 0);
            $sheet->setCellValue('A' . $row, 'Avg Resolution Time');
            $sheet->setCellValue('B' . $row++, formatDuration($stats['avg_resolution_hours'] ?? null));
            $sheet->setCellValue('A' . $row, 'Min Resolution Time');
            $sheet->setCellValue('B' . $row++, formatDuration($stats['min_resolution_hours'] ?? null));
            $sheet->setCellValue('A' . $row, 'Max Resolution Time');
            $sheet->setCellValue('B' . $row++, formatDuration($stats['max_resolution_hours'] ?? null));
            break;
            
        case 'department':
            $sheet->setCellValue('A7', 'Department');
            $sheet->setCellValue('B7', 'Total');
            $sheet->setCellValue('C7', 'Resolved');
            $sheet->setCellValue('D7', 'Avg Resolution Time');
            $sheet->setCellValue('E7', 'Satisfaction');
            $sheet->getStyle('A7:E7')->getFont()->setBold(true);
            
            $row = 8;
            foreach ($data as $dept) {
                $sheet->setCellValue('A' . $row, $dept['department'] ?? 'N/A');
                $sheet->setCellValue('B' . $row, $dept['total_complaints'] ?? 0);
                $sheet->setCellValue('C' . $row, $dept['resolved'] ?? 0);
                $sheet->setCellValue('D' . $row, formatDuration($dept['avg_resolution_hours'] ?? null));
                $sheet->setCellValue('E' . $row, round($dept['satisfaction_score'] ?? 0, 1));
                $row++;
            }
            break;
            
        case 'satisfaction':
            $sheet->setCellValue('A7', 'Average Scores');
            $sheet->setCellValue('B7', 'Value');
            $sheet->getStyle('A7:B7')->getFont()->setBold(true);
            
            $row = 8;
            $averages = $data['averages'];
            $sheet->setCellValue('A' . $row, 'Speed Rating');
            $sheet->setCellValue('B' . $row++, round($averages['avg_speed'] ?? 0, 2));
            $sheet->setCellValue('A' . $row, 'Communication Rating');
            $sheet->setCellValue('B' . $row++, round($averages['avg_communication'] ?? 0, 2));
            $sheet->setCellValue('A' . $row, 'Effectiveness Rating');
            $sheet->setCellValue('B' . $row++, round($averages['avg_effectiveness'] ?? 0, 2));
            $sheet->setCellValue('A' . $row, 'Overall Rating');
            $sheet->setCellValue('B' . $row++, round($averages['avg_overall'] ?? 0, 2));
            $sheet->setCellValue('A' . $row, 'Total Ratings');
            $sheet->setCellValue('B' . $row++, $averages['total_ratings'] ?? 0);
            
            $row += 2;
            
            $sheet->setCellValue('A' . $row, 'Detailed Ratings');
            $sheet->setCellValue('B' . $row, 'Complaint');
            $sheet->setCellValue('C' . $row, 'Student');
            $sheet->setCellValue('D' . $row, 'Speed');
            $sheet->setCellValue('E' . $row, 'Comm');
            $sheet->setCellValue('F' . $row, 'Effect');
            $sheet->setCellValue('G' . $row, 'Overall');
            $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
            
            $row++;
            foreach (array_slice($data['ratings'], 0, 50) as $rating) {
                $sheet->setCellValue('A' . $row, $rating['complaint_number'] ?? '');
                $sheet->setCellValue('B' . $row, $rating['title'] ?? '');
                $sheet->setCellValue('C' . $row, $rating['student_name'] ?? '');
                $sheet->setCellValue('D' . $row, $rating['speed_rating'] ?? 0);
                $sheet->setCellValue('E' . $row, $rating['communication_rating'] ?? 0);
                $sheet->setCellValue('F' . $row, $rating['effectiveness_rating'] ?? 0);
                $sheet->setCellValue('G' . $row, round($rating['overall_rating'] ?? 0, 2));
                $row++;
            }
            break;

        case 'academic':
            $sheet->setCellValue('A7', 'Academic Department');
            $sheet->setCellValue('B7', 'Total');
            $sheet->setCellValue('C7', 'Resolved');
            $sheet->setCellValue('D7', 'Avg Resolution Time');
            $sheet->setCellValue('E7', 'Batch Complaints');
            $sheet->getStyle('A7:E7')->getFont()->setBold(true);

            $row = 8;
            foreach ($data['by_department'] as $d) {
                $sheet->setCellValue('A' . $row, $d['academic_department'] ?? 'N/A');
                $sheet->setCellValue('B' . $row, $d['total'] ?? 0);
                $sheet->setCellValue('C' . $row, $d['resolved'] ?? 0);
                $sheet->setCellValue('D' . $row, formatDuration($d['avg_resolution_hours'] ?? null));
                $sheet->setCellValue('E' . $row, $d['batch_count'] ?? 0);
                $row++;
            }

            $row += 2;
            $sheet->setCellValue('A' . $row, 'Department');
            $sheet->setCellValue('B' . $row, 'Course');
            $sheet->setCellValue('C' . $row, 'Class');
            $sheet->setCellValue('D' . $row, 'Year');
            $sheet->setCellValue('E' . $row, 'Total');
            $sheet->setCellValue('F' . $row, 'Resolved');
            $sheet->setCellValue('G' . $row, 'Batch');
            $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
            $row++;
            foreach ($data['by_class'] as $c) {
                $sheet->setCellValue('A' . $row, $c['department'] ?? '');
                $sheet->setCellValue('B' . $row, $c['course'] ?? '');
                $sheet->setCellValue('C' . $row, $c['class_code'] ?? '');
                $sheet->setCellValue('D' . $row, $c['year_of_study'] ?? '');
                $sheet->setCellValue('E' . $row, $c['total'] ?? 0);
                $sheet->setCellValue('F' . $row, $c['resolved'] ?? 0);
                $sheet->setCellValue('G' . $row, $c['batch_count'] ?? 0);
                $row++;
            }
            break;
    }
    
    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Create file
    $writer = new Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit();
}

/**
 * Export to CSV
 */
function exportToCSV($data, $type, $filename, $date_from, $date_to, $department) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers based on type
    switch ($type) {
        case 'summary':
            fputcsv($output, ['Metric', 'Value']);
            foreach ($data['stats'] as $key => $value) {
                $is_duration = in_array($key, ['avg_resolution_hours', 'min_resolution_hours', 'max_resolution_hours']);
                $display_value = $is_duration ? formatDuration($value) : ($value ?? 0);
                $display_key = str_replace(' Hours', ' Time', ucfirst(str_replace('_', ' ', $key)));
                fputcsv($output, [$display_key, $display_value]);
            }
            break;
            
        case 'department':
            fputcsv($output, ['Department', 'Total', 'Resolved', 'Avg Resolution Time', 'Satisfaction']);
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['department'] ?? 'N/A',
                    $row['total_complaints'] ?? 0,
                    $row['resolved'] ?? 0,
                    formatDuration($row['avg_resolution_hours'] ?? null),
                    round($row['satisfaction_score'] ?? 0, 1)
                ]);
            }
            break;
            
        case 'satisfaction':
            fputcsv($output, ['Complaint', 'Student', 'Speed', 'Communication', 'Effectiveness', 'Overall']);
            foreach ($data['ratings'] as $rating) {
                fputcsv($output, [
                    $rating['complaint_number'] ?? '',
                    $rating['student_name'] ?? '',
                    $rating['speed_rating'] ?? 0,
                    $rating['communication_rating'] ?? 0,
                    $rating['effectiveness_rating'] ?? 0,
                    round($rating['overall_rating'] ?? 0, 2)
                ]);
            }
            break;
            
        case 'priority':
            fputcsv($output, ['Priority', 'Total', 'Avg Resolution Time', 'Fastest', 'Slowest']);
            foreach ($data as $row) {
                fputcsv($output, [
                    ucfirst($row['priority'] ?? ''),
                    $row['total'] ?? 0,
                    formatDuration($row['avg_resolution_hours'] ?? null),
                    formatDuration($row['min_hours'] ?? null),
                    formatDuration($row['max_hours'] ?? null)
                ]);
            }
            break;

        case 'academic':
            fputcsv($output, ['Academic Department', 'Total', 'Resolved', 'Avg Resolution Time', 'Batch Complaints']);
            foreach ($data['by_department'] as $d) {
                fputcsv($output, [
                    $d['academic_department'] ?? 'N/A',
                    $d['total'] ?? 0,
                    $d['resolved'] ?? 0,
                    formatDuration($d['avg_resolution_hours'] ?? null),
                    $d['batch_count'] ?? 0
                ]);
            }
            fputcsv($output, []);
            fputcsv($output, ['Department', 'Course', 'Class', 'Year', 'Total', 'Resolved', 'Batch', 'Distinct Submitters']);
            foreach ($data['by_class'] as $c) {
                fputcsv($output, [
                    $c['department'] ?? '',
                    $c['course'] ?? '',
                    $c['class_code'] ?? '',
                    $c['year_of_study'] ?? '',
                    $c['total'] ?? 0,
                    $c['resolved'] ?? 0,
                    $c['batch_count'] ?? 0,
                    $c['distinct_submitters'] ?? 0
                ]);
            }
            fputcsv($output, []);
            fputcsv($output, ['Class Rep', 'Department', 'Course', 'Class', 'Year', 'Batch Complaints', 'Students Represented']);
            foreach ($data['class_reps'] as $r) {
                fputcsv($output, [
                    $r['full_name'] ?? '',
                    $r['department'] ?? '',
                    $r['course'] ?? '',
                    $r['class_code'] ?? '',
                    $r['year_of_study'] ?? '',
                    $r['batch_complaints'] ?? 0,
                    $r['students_represented'] ?? 0
                ]);
            }
            break;
    }
    
    fclose($output);
    exit();
}

/**
 * Export to PDF using Dompdf
 */
function exportToPDF($data, $type, $filename, $date_from, $date_to, $department) {
    // Check if Dompdf is available
    if (!class_exists('Dompdf\Dompdf')) {
        die('Dompdf library not found. Please run: composer require dompdf/dompdf');
    }
    
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    
    $dompdf = new Dompdf($options);
    
    // Build HTML content
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Report: ' . ucfirst($type) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; text-align: center; }
            h2 { color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 5px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #667eea; color: white; padding: 10px; text-align: left; }
            td { padding: 8px; border-bottom: 1px solid #ddd; }
            .header { text-align: center; margin-bottom: 30px; }
            .date { color: #666; font-size: 12px; }
            .footer { margin-top: 50px; text-align: center; color: #666; font-size: 10px; }
            .stat-box { background: #f8f9fa; padding: 15px; margin: 10px; display: inline-block; min-width: 150px; }
            .stat-value { font-size: 24px; font-weight: bold; color: #667eea; }
            .stat-label { color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Student Complaint System</h1>
            <h2>' . ucfirst($type) . ' Report</h2>
            <p class="date">Date Range: ' . $date_from . ' to ' . $date_to . '</p>
            <p class="date">Generated: ' . date('Y-m-d H:i:s') . '</p>
        </div>';
    
    // Add content based on type
    switch ($type) {
        case 'summary':
            $html .= '<h2>Summary Statistics</h2>';
            $html .= '<table>';
            $html .= '<tr><th>Metric</th><th>Value</th></tr>';
            foreach ($data['stats'] as $key => $value) {
                $is_duration = in_array($key, ['avg_resolution_hours', 'min_resolution_hours', 'max_resolution_hours']);
                $display_value = $is_duration ? formatDuration($value) : ($value ?? 0);
                $display_key = str_replace(' Hours', ' Time', ucfirst(str_replace('_', ' ', $key)));
                $html .= '<tr><td>' . $display_key . '</td><td>' . $display_value . '</td></tr>';
            }
            $html .= '</table>';
            
            $html .= '<h2>Priority Breakdown</h2>';
            $html .= '<table>';
            $html .= '<tr><th>Priority</th><th>Count</th><th>Percentage</th></tr>';
            $total = $data['stats']['total_complaints'] ?: 1;
            foreach ($data['priority_stats'] as $p) {
                $percentage = round(($p['count'] / $total) * 100, 1);
                $html .= '<tr>';
                $html .= '<td>' . ucfirst($p['priority']) . '</td>';
                $html .= '<td>' . $p['count'] . '</td>';
                $html .= '<td>' . $percentage . '%</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            break;
            
        case 'department':
            $html .= '<h2>Department Performance</h2>';
            $html .= '<table>';
            $html .= '<tr><th>Department</th><th>Total</th><th>Resolved</th><th>Avg Resolution Time</th><th>Satisfaction</th></tr>';
            foreach ($data as $dept) {
                $html .= '<tr>';
                $html .= '<td>' . ($dept['department'] ?? 'N/A') . '</td>';
                $html .= '<td>' . ($dept['total_complaints'] ?? 0) . '</td>';
                $html .= '<td>' . ($dept['resolved'] ?? 0) . '</td>';
                $html .= '<td>' . formatDuration($dept['avg_resolution_hours'] ?? null) . '</td>';
                $html .= '<td>' . round($dept['satisfaction_score'] ?? 0, 1) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            break;
            
        case 'satisfaction':
            $html .= '<h2>Satisfaction Scores</h2>';
            $html .= '<div style="text-align: center;">';
            $html .= '<div class="stat-box"><div class="stat-value">' . round($data['averages']['avg_speed'] ?? 0, 1) . '</div><div class="stat-label">Speed</div></div>';
            $html .= '<div class="stat-box"><div class="stat-value">' . round($data['averages']['avg_communication'] ?? 0, 1) . '</div><div class="stat-label">Communication</div></div>';
            $html .= '<div class="stat-box"><div class="stat-value">' . round($data['averages']['avg_effectiveness'] ?? 0, 1) . '</div><div class="stat-label">Effectiveness</div></div>';
            $html .= '<div class="stat-box"><div class="stat-value">' . round($data['averages']['avg_overall'] ?? 0, 1) . '</div><div class="stat-label">Overall</div></div>';
            $html .= '</div>';
            
            $html .= '<h2>Recent Ratings</h2>';
            $html .= '<table>';
            $html .= '<tr><th>Complaint</th><th>Student</th><th>Speed</th><th>Comm</th><th>Effect</th><th>Overall</th></tr>';
            foreach (array_slice($data['ratings'], 0, 20) as $rating) {
                $html .= '<tr>';
                $html .= '<td>#' . ($rating['complaint_number'] ?? '') . '</td>';
                $html .= '<td>' . ($rating['student_name'] ?? '') . '</td>';
                $html .= '<td>' . ($rating['speed_rating'] ?? 0) . '</td>';
                $html .= '<td>' . ($rating['communication_rating'] ?? 0) . '</td>';
                $html .= '<td>' . ($rating['effectiveness_rating'] ?? 0) . '</td>';
                $html .= '<td>' . round($rating['overall_rating'] ?? 0, 1) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            break;

        case 'academic':
            $html .= '<h2>Complaints by Academic Department</h2>';
            $html .= '<table>';
            $html .= '<tr><th>Academic Department</th><th>Total</th><th>Resolved</th><th>Avg Resolution Time</th><th>Batch</th></tr>';
            foreach ($data['by_department'] as $d) {
                $html .= '<tr>';
                $html .= '<td>' . ($d['academic_department'] ?? 'N/A') . '</td>';
                $html .= '<td>' . ($d['total'] ?? 0) . '</td>';
                $html .= '<td>' . ($d['resolved'] ?? 0) . '</td>';
                $html .= '<td>' . formatDuration($d['avg_resolution_hours'] ?? null) . '</td>';
                $html .= '<td>' . ($d['batch_count'] ?? 0) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';

            $html .= '<h2>Complaints by Year of Study</h2>';
            $html .= '<table>';
            $html .= '<tr><th>Year</th><th>Total</th><th>Resolved</th></tr>';
            foreach ($data['by_year'] as $y) {
                $html .= '<tr>';
                $html .= '<td>Year ' . ($y['year_of_study'] ?? '') . '</td>';
                $html .= '<td>' . ($y['total'] ?? 0) . '</td>';
                $html .= '<td>' . ($y['resolved'] ?? 0) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';

            $html .= '<h2>Complaints by Class (Dept &middot; Course &middot; Class &middot; Year)</h2>';
            $html .= '<table>';
            $html .= '<tr><th>Department</th><th>Course</th><th>Class</th><th>Year</th><th>Total</th><th>Resolved</th><th>Batch</th></tr>';
            foreach ($data['by_class'] as $c) {
                $html .= '<tr>';
                $html .= '<td>' . ($c['department'] ?? '') . '</td>';
                $html .= '<td>' . ($c['course'] ?? '') . '</td>';
                $html .= '<td>' . ($c['class_code'] ?? '') . '</td>';
                $html .= '<td>' . ($c['year_of_study'] ?? '') . '</td>';
                $html .= '<td>' . ($c['total'] ?? 0) . '</td>';
                $html .= '<td>' . ($c['resolved'] ?? 0) . '</td>';
                $html .= '<td>' . ($c['batch_count'] ?? 0) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';

            $html .= '<h2>Most Active Class Representatives</h2>';
            $html .= '<table>';
            $html .= '<tr><th>Class Rep</th><th>Class</th><th>Batch Complaints</th><th>Students Represented</th></tr>';
            foreach ($data['class_reps'] as $r) {
                $html .= '<tr>';
                $html .= '<td>' . ($r['full_name'] ?? '') . '</td>';
                $html .= '<td>' . trim(($r['department'] ?? '') . ' / ' . ($r['course'] ?? '') . ' / ' . ($r['class_code'] ?? '') . ' / Y' . ($r['year_of_study'] ?? '')) . '</td>';
                $html .= '<td>' . ($r['batch_complaints'] ?? 0) . '</td>';
                $html .= '<td>' . ($r['students_represented'] ?? 0) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            break;
    }
    
    $html .= '<div class="footer">Generated by Student Complaint Support System</div>';
    $html .= '</body></html>';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    $dompdf->stream($filename . '.pdf', ['Attachment' => true]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics</title>
    <link rel="stylesheet" href="../admin/css/admin.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reports & Analytics</h1>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <!-- Report Controls -->
        <div class="report-controls">
            <form method="GET" id="reportForm">
                <div class="control-grid">
                    <div class="control-group">
                        <label>Report Type</label>
                        <select name="type" onchange="this.form.submit()">
                            <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                            <option value="department" <?php echo $report_type == 'department' ? 'selected' : ''; ?>>Department Performance</option>
                            <option value="academic" <?php echo $report_type == 'academic' ? 'selected' : ''; ?>>Academic / Class Breakdown</option>
                           
                            <option value="satisfaction" <?php echo $report_type == 'satisfaction' ? 'selected' : ''; ?>>Satisfaction Report</option>
                            
                        </select>
                    </div>
                    
                    <div class="control-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" onchange="this.form.submit()">
                    </div>
                    
                    <div class="control-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" onchange="this.form.submit()">
                    </div>
                    
                    <div class="control-group">
                        <label>Department</label>
                        <select name="department" onchange="this.form.submit()">
                            <option value="all" <?php echo $department == 'all' ? 'selected' : ''; ?>>All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept; ?>" <?php echo $department == $dept ? 'selected' : ''; ?>>
                                    <?php echo $dept; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <h2 class="section-title" style="margin-top: 20px;">📋 Report Preview</h2>
        <p style="color:#7f8c8d; font-size:13px; margin: -8px 0 15px;">Review the report below, then download it in your preferred format at the bottom of the page.</p>
        
        <!-- Report Display -->
        <div class="report-section">
            <h2 class="section-title">
                <?php 
                $titles = [
                    'summary' => 'Summary Report',
                    'department' => 'Department Performance',
                    'priority' => 'Priority Analysis',
                    'trends' => 'Trends Analysis',
                    'satisfaction' => 'Satisfaction Report',
                    'performance' => 'Staff Performance',
                    'academic' => 'Academic / Class Breakdown'
                ];
                echo $titles[$report_type] ?? 'Report';
                ?>
            </h2>
            
            <?php if ($report_type == 'summary'): ?>
                <!-- Summary Report Display -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $report_data['stats']['total_complaints'] ?? 0; ?></div>
                        <div class="stat-label">Total Complaints</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $report_data['stats']['resolved'] ?? 0; ?></div>
                        <div class="stat-label">Resolved</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo formatDuration($report_data['stats']['avg_resolution_hours'] ?? null); ?></div>
                        <div class="stat-label">Avg Resolution Time</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $report_data['stats']['submitted'] ?? 0; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                
                <h3 class="priority-breakdown-title">Priority Breakdown</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $total = $report_data['stats']['total_complaints'] ?: 1;
                    foreach ($report_data['priority_stats'] as $p): 
                    ?>
                    <tr>
                        <td>
                            <span class="priority-badge priority-<?php echo $p['priority']; ?>">
                                <?php echo ucfirst($p['priority']); ?>
                            </span>
                        </td>
                        <td><?php echo $p['count']; ?></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($p['count'] / $total) * 100; ?>%;">
                                    <?php echo round(($p['count'] / $total) * 100, 1); ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($report_type == 'department'): ?>
                <!-- Department Report Display -->
                <table>
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Total</th>
                            <th>Resolved</th>
                            <th>Resolution Rate</th>
                            <th>Avg Resolution Time</th>
                            <th>Satisfaction</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($report_data as $dept): ?>
                    <tr>
                        <td><strong><?php echo $dept['department'] ?? 'N/A'; ?></strong></td>
                        <td><?php echo $dept['total_complaints'] ?? 0; ?></td>
                        <td><?php echo $dept['resolved'] ?? 0; ?></td>
                        <td>
                            <?php 
                            $rate = ($dept['total_complaints'] ?? 0) > 0 ? round((($dept['resolved'] ?? 0) / ($dept['total_complaints'] ?? 1)) * 100) : 0;
                            echo $rate . '%';
                            ?>
                        </td>
                        <td><?php echo formatDuration($dept['avg_resolution_hours'] ?? null); ?></td>
                        <td>
                            <?php if (($dept['satisfaction_score'] ?? 0) > 0): ?>
                                <span class="rating-stars">
                                    <?php 
                                    $stars = round($dept['satisfaction_score'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $stars ? '★' : '☆';
                                    }
                                    ?>
                                </span>
                                (<?php echo round($dept['satisfaction_score'] ?? 0, 1); ?>)
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($report_type == 'satisfaction'): ?>
                <!-- Satisfaction Report Display -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo round($report_data['averages']['avg_speed'] ?? 0, 1); ?></div>
                        <div class="stat-label">Speed Rating</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo round($report_data['averages']['avg_communication'] ?? 0, 1); ?></div>
                        <div class="stat-label">Communication</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo round($report_data['averages']['avg_effectiveness'] ?? 0, 1); ?></div>
                        <div class="stat-label">Effectiveness</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo round($report_data['averages']['avg_overall'] ?? 0, 1); ?></div>
                        <div class="stat-label">Overall</div>
                    </div>
                </div>
                
                <h3 class="recent-ratings-title">Recent Ratings</h3>
                <div class="recent-ratings-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Complaint</th>
                                <th>Student</th>
                                <th>Speed</th>
                                <th>Comm</th>
                                <th>Effect</th>
                                <th>Overall</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($report_data['ratings'], 0, 10) as $rating): ?>
                        <tr>
                            <td>#<?php echo $rating['complaint_number'] ?? ''; ?></td>
                            <td><?php echo $rating['student_name'] ?? ''; ?></td>
                            <td><?php echo $rating['speed_rating'] ?? 0; ?> ★</td>
                            <td><?php echo $rating['communication_rating'] ?? 0; ?> ★</td>
                            <td><?php echo $rating['effectiveness_rating'] ?? 0; ?> ★</td>
                            <td><strong><?php echo round($rating['overall_rating'] ?? 0, 1); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($report_type == 'academic'): ?>
                <!-- Academic / Class Breakdown Report Display -->
                <h3 class="priority-breakdown-title">Complaints by Academic Department</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Academic Department</th>
                            <th>Total</th>
                            <th>Resolved</th>
                            <th>Avg Resolution Time</th>
                            <th>Batch Complaints</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($report_data['by_department'])): ?>
                        <tr><td colspan="5">No data for this period.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($report_data['by_department'] as $d): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($d['academic_department'] ?? 'N/A'); ?></strong></td>
                        <td><?php echo $d['total'] ?? 0; ?></td>
                        <td><?php echo $d['resolved'] ?? 0; ?></td>
                        <td><?php echo formatDuration($d['avg_resolution_hours'] ?? null); ?></td>
                        <td><?php echo $d['batch_count'] ?? 0; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h3 class="priority-breakdown-title">Complaints by Year of Study</h3>
                <table>
                    <thead>
                        <tr><th>Year</th><th>Total</th><th>Resolved</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($report_data['by_year'])): ?>
                        <tr><td colspan="3">No data for this period.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($report_data['by_year'] as $y): ?>
                    <tr>
                        <td>Year <?php echo htmlspecialchars($y['year_of_study']); ?></td>
                        <td><?php echo $y['total'] ?? 0; ?></td>
                        <td><?php echo $y['resolved'] ?? 0; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h3 class="priority-breakdown-title">Complaints by Class (Department · Course · Class · Year)</h3>
                <div class="recent-ratings-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Department</th><th>Course</th><th>Class</th><th>Year</th>
                            <th>Total</th><th>Resolved</th><th>Batch</th><th>Distinct Submitters</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($report_data['by_class'])): ?>
                        <tr><td colspan="8">No fully-assigned class data yet. Assign department, course, class and year to students in User Management.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($report_data['by_class'] as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['department']); ?></td>
                        <td><?php echo htmlspecialchars($c['course']); ?></td>
                        <td><?php echo htmlspecialchars($c['class_code']); ?></td>
                        <td>Y<?php echo htmlspecialchars($c['year_of_study']); ?></td>
                        <td><?php echo $c['total'] ?? 0; ?></td>
                        <td><?php echo $c['resolved'] ?? 0; ?></td>
                        <td><?php echo $c['batch_count'] ?? 0; ?></td>
                        <td><?php echo $c['distinct_submitters'] ?? 0; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <h3 class="recent-ratings-title">Most Active Class Representatives</h3>
                <div class="recent-ratings-wrapper">
                <table>
                    <thead>
                        <tr><th>Class Rep</th><th>Class</th><th>Batch Complaints</th><th>Students Represented</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($report_data['class_reps'])): ?>
                        <tr><td colspan="4">No batch complaints in this period.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($report_data['class_reps'] as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['full_name']); ?></td>
                        <td><?php echo htmlspecialchars(trim(($r['department'] ?? '') . ' · ' . ($r['course'] ?? '') . ' · ' . ($r['class_code'] ?? '') . ' · Y' . ($r['year_of_study'] ?? ''))); ?></td>
                        <td><?php echo $r['batch_complaints'] ?? 0; ?></td>
                        <td><?php echo $r['students_represented'] ?? 0; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="report-section download-report-card">
            <h3 class="section-title">⬇ Download This Report</h3>
            <p style="color:#7f8c8d; font-size:13px; margin: -5px 0 15px;">Same data shown above, exported as a file.</p>
            <div class="export-buttons">
                <button type="button" class="btn btn-success" onclick="exportReport('pdf')">PDF</button>
                <button type="button" class="btn btn-success" onclick="exportReport('excel')"> Excel</button>
                <button type="button" class="btn btn-success" onclick="exportReport('csv')"> CSV</button>
            </div>
        </div>
    </div>
    
 <script src="../admin/js/admin-core.js"></script>
<script src="../admin/js/analytics.js"></script>


</body>
</html>