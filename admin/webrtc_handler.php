<?php

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    require_once '../config/security.php';
    require_once '../config/database.php';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit();
    }

    $db = (new Database())->getConnection();
    if (!$db) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    // Fix table structure (if needed)
    try {
        $db->exec("ALTER TABLE webrtc_meetings DROP FOREIGN KEY webrtc_meetings_ibfk_1");
    } catch (PDOException $e) {}

    try {
        $db->exec("ALTER TABLE webrtc_meetings MODIFY complaint_id INT NULL");
    } catch (PDOException $e) {}

    try {
        $db->exec("ALTER TABLE webrtc_meetings ADD COLUMN room_name VARCHAR(255) NULL");
    } catch (PDOException $e) {}

    // Create/update tables
    $db->exec("CREATE TABLE IF NOT EXISTS webrtc_meetings (
        meeting_id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(100) UNIQUE NOT NULL,
        complaint_id INT NULL,
        created_by INT,
        room_name VARCHAR(255),
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        started_at DATETIME NULL,
        ended_at DATETIME NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS webrtc_participants (
        participant_id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT,
        user_id INT,
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        left_at DATETIME NULL,
        FOREIGN KEY (meeting_id) REFERENCES webrtc_meetings(meeting_id) ON DELETE CASCADE
    )");

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data || !isset($data['action'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }

    switch ($data['action']) {
        case 'create_room':
            $room_id = 'CALL_' . uniqid() . '_' . rand(1000, 9999);
            $created_by = $_SESSION['user_id'];
            $room_name = $data['room_name'] ?? 'Video Call';

            $stmt = $db->prepare("INSERT INTO webrtc_meetings (room_id, created_by, room_name, is_active) VALUES (?, ?, ?, 1)");
            $result = $stmt->execute([$room_id, $created_by, $room_name]);
            if ($result) {
                $meeting_id = $db->lastInsertId();
                $stmt = $db->prepare("INSERT INTO webrtc_participants (meeting_id, user_id) VALUES (?, ?)");
                $stmt->execute([$meeting_id, $created_by]);
                echo json_encode(['success' => true, 'room_id' => $room_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create room']);
            }
            break;

        case 'join_room':
            $room_id = $data['room_id'];
            $user_id = $_SESSION['user_id'];

            $stmt = $db->prepare("SELECT meeting_id FROM webrtc_meetings WHERE room_id = ? AND is_active = 1");
            $stmt->execute([$room_id]);
            $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$meeting) {
                echo json_encode(['success' => false, 'message' => 'Room not found']);
                exit();
            }

            $stmt = $db->prepare("INSERT IGNORE INTO webrtc_participants (meeting_id, user_id) VALUES (?, ?)");
            $stmt->execute([$meeting['meeting_id'], $user_id]);
            echo json_encode(['success' => true]);
            break;

        case 'leave':
            $room_id = $data['room_id'];
            $user_id = $_SESSION['user_id'];

            $stmt = $db->prepare("UPDATE webrtc_participants SET left_at = NOW() 
                                  WHERE meeting_id = (SELECT meeting_id FROM webrtc_meetings WHERE room_id = ?) 
                                  AND user_id = ? AND left_at IS NULL");
            $stmt->execute([$room_id, $user_id]);

            $stmt = $db->prepare("SELECT COUNT(*) FROM webrtc_participants p
                                  JOIN webrtc_meetings m ON p.meeting_id = m.meeting_id
                                  WHERE m.room_id = ? AND p.left_at IS NULL");
            $stmt->execute([$room_id]);
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                $stmt = $db->prepare("UPDATE webrtc_meetings SET ended_at = NOW(), is_active = 0 WHERE room_id = ?");
                $stmt->execute([$room_id]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'send_join_link':
            $room_id = $data['room_id'];
            $sender_id = $data['sender_id'];
            $receiver_id = $data['receiver_id'];
            $room_name = $data['room_name'] ?? 'Video Call';
            
            $link = "http://" . $_SERVER['HTTP_HOST'] . "/SCCS/admin/video_join.php?room=" . urlencode($room_id);
            $message = "🎥 Video Call Invite: Join me for a video call.\n\nRoom Code: " . $room_id . "\n\nClick this link to join: " . $link;
            
            $stmt = $db->prepare("INSERT INTO direct_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $result = $stmt->execute([$sender_id, $receiver_id, $message]);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to send invite']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>