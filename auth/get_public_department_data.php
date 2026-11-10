<?php
// get_public_department_data.php - No login required.
// Returns ONLY aggregated, anonymized statistics for one operational
// department - never individual complaint text, student names, or
// anything else that could identify a person. This powers the
// "click a department to see more" drill-down on the public
// transparency dashboard.

require_once '../config/database.php';

header('Content-Type: application/json');

$department = $_GET['department'] ?? '';

if (empty($department)) {
    echo json_encode(['success' => false, 'message' => 'Department is required']);
    exit();
}

try {
    $db = (new Database())->getConnection();

    // Confirm this is a real, operational department (so the endpoint can't
    // be used to probe arbitrary strings against the complaints table).
    $dept_stmt = $db->prepare("SELECT dept_name, dept_code, description FROM departments WHERE dept_name = ? AND dept_type = 'operational'");
    $dept_stmt->execute([$department]);
    $dept_info = $dept_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dept_info) {
        echo json_encode(['success' => false, 'message' => 'Department not found']);
        exit();
    }

    // Overall volume + resolution performance (all-time)
    $summary_stmt = $db->prepare("SELECT 
            COUNT(*) as total_complaints,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
            SUM(CASE WHEN status IN ('submitted','under_review','in_progress') THEN 1 ELSE 0 END) as open_count,
            ROUND(AVG(CASE WHEN status = 'resolved' AND resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END), 1) as avg_resolution_hours,
            SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as last_30_days
        FROM complaints WHERE department_assigned = ?");
    $summary_stmt->execute([$department]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

    $total = (int)($summary['total_complaints'] ?? 0);
    $resolved = (int)($summary['resolved_count'] ?? 0);
    $summary['resolution_rate'] = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;

    // Priority breakdown (aggregate counts only)
    $priority_stmt = $db->prepare("SELECT priority, COUNT(*) as count 
        FROM complaints WHERE department_assigned = ? GROUP BY priority");
    $priority_stmt->execute([$department]);
    $priority_breakdown = $priority_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top complaint categories for this department
    $category_stmt = $db->prepare("SELECT cat.category_name, COUNT(*) as count
        FROM complaints c
        JOIN complaint_categories cat ON c.category_id = cat.category_id
        WHERE c.department_assigned = ?
        GROUP BY cat.category_id
        ORDER BY count DESC
        LIMIT 5");
    $category_stmt->execute([$department]);
    $top_categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly trend, last 6 months (submitted vs resolved volume)
    $trend_stmt = $db->prepare("SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            DATE_FORMAT(created_at, '%b %Y') as month_label,
            COUNT(*) as submitted,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
        FROM complaints
        WHERE department_assigned = ? AND created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month, month_label
        ORDER BY month ASC");
    $trend_stmt->execute([$department]);
    $monthly_trend = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rating breakdown (already aggregated / anonymous by design)
    $rating_stmt = $db->prepare("SELECT avg_speed, avg_communication, avg_effectiveness, avg_overall, total_ratings 
        FROM department_ratings WHERE department = ?");
    $rating_stmt->execute([$department]);
    $ratings = $rating_stmt->fetch(PDO::FETCH_ASSOC);

    // A handful of anonymized public feedback snippets for this department only
    $feedback_stmt = $db->prepare("SELECT r.feedback, r.speed_rating, r.communication_rating, r.effectiveness_rating, r.created_at
        FROM complaint_ratings r
        JOIN complaints c ON r.complaint_id = c.complaint_id
        WHERE c.department_assigned = ? AND r.is_public = 1 AND r.feedback IS NOT NULL AND r.feedback != ''
        ORDER BY r.created_at DESC
        LIMIT 5");
    $feedback_stmt->execute([$department]);
    $recent_feedback = $feedback_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'department' => $dept_info,
        'summary' => $summary,
        'priority_breakdown' => $priority_breakdown,
        'top_categories' => $top_categories,
        'monthly_trend' => $monthly_trend,
        'ratings' => $ratings,
        'recent_feedback' => $recent_feedback
    ]);

} catch (Exception $e) {
    error_log("Public department data error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to load department details right now.']);
}
