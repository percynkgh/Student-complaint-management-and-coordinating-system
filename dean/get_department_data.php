<?php
require_once '../config/security.php';
require_once '../config/complaint_functions.php';

header('Content-Type: application/json');

Security::requireLogin();
if (!Security::hasRole('dean') && !Security::hasRole('principal')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $db = (new Database())->getConnection();
    
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $selected_dept = $_GET['department'] ?? 'all';
    
    // Get complaints
    $query = "SELECT 
                c.*, cat.category_name, u.full_name as student_name,
                TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as hours_old,
                CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END as resolution_hours,
                DATE_FORMAT(c.created_at, '%b %d, %Y') as created_date
              FROM complaints c
              LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
              LEFT JOIN users u ON c.user_id = u.user_id
              WHERE DATE(c.created_at) BETWEEN :from AND :to";
    
    $params = [':from' => $date_from, ':to' => $date_to];
    
    if ($selected_dept != 'all') {
        $query .= " AND c.department_assigned = :dept";
        $params[':dept'] = $selected_dept;
    }
    
    $query .= " ORDER BY c.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats
    $stats_query = "SELECT 
                        c.department_assigned as department,
                        COUNT(*) as total,
                        SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                        ROUND(AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END), 1) as avg_time,
                        COUNT(CASE WHEN c.priority = 'urgent' THEN 1 END) as urgent,
                        dr.avg_overall as satisfaction,
                        dr.total_ratings
                    FROM complaints c
                    LEFT JOIN department_ratings dr ON dr.department = c.department_assigned
                    WHERE DATE(c.created_at) BETWEEN :from AND :to
                    AND c.department_assigned IS NOT NULL";
    
    $stats_params = [':from' => $date_from, ':to' => $date_to];
    
    if ($selected_dept != 'all') {
        $stats_query .= " AND c.department_assigned = :dept";
        $stats_params[':dept'] = $selected_dept;
    }
    
    $stats_query .= " GROUP BY c.department_assigned";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute($stats_params);
    $stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'complaints' => $complaints,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>