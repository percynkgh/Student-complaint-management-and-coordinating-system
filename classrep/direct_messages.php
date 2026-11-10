<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('class_rep')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];
$selected_user = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// Auto-select most recent conversation if none selected
if ($selected_user == 0) {
    $recent_query = "SELECT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_user_id
                     FROM direct_messages WHERE sender_id = ? OR receiver_id = ?
                     ORDER BY created_at DESC LIMIT 1";
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute([$user_id, $user_id, $user_id]);
    $recent = $recent_stmt->fetch(PDO::FETCH_ASSOC);
    if ($recent && $recent['other_user_id']) {
        $selected_user = (int)$recent['other_user_id'];
    }
}

// Get users class rep can message (staff, admin, dean, principal) with unread counts and last message time
$query = "SELECT u.user_id, u.full_name, u.email, u.role, u.department,
          (SELECT COUNT(*) FROM direct_messages WHERE sender_id = u.user_id AND receiver_id = ? AND is_read = FALSE) as unread_count,
          (SELECT MAX(created_at) FROM direct_messages WHERE (sender_id = ? AND receiver_id = u.user_id) OR (sender_id = u.user_id AND receiver_id = ?)) as last_message_time
          FROM users u
          WHERE u.user_id != ? AND u.role IN ('staff', 'supervisor', 'admin', 'dean', 'principal')
          ORDER BY last_message_time DESC, unread_count DESC, u.full_name ASC";
$stmt = $db->prepare($query);
$stmt->execute([$user_id, $user_id, $user_id, $user_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get messages if a user is selected
$messages = [];
$other_user = null;
$last_message_id = 0;
if ($selected_user) {
    $user_stmt = $db->prepare("SELECT user_id, full_name, role, department FROM users WHERE user_id = ?");
    $user_stmt->execute([$selected_user]);
    $other_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    $msg_stmt = $db->prepare("SELECT m.*, u1.full_name as sender_name, u1.role as sender_role
                              FROM direct_messages m
                              JOIN users u1 ON m.sender_id = u1.user_id
                              WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                                 OR (m.sender_id = ? AND m.receiver_id = ?)
                              ORDER BY m.created_at ASC LIMIT 100");
    $msg_stmt->execute([$user_id, $selected_user, $selected_user, $user_id]);
    $messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($messages)) $last_message_id = end($messages)['message_id'];
    
    // Mark messages as read
    $update = $db->prepare("UPDATE direct_messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ?");
    $update->execute([$selected_user, $user_id]);
}

// AJAX handlers
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    if ($_POST['ajax_action'] == 'send_message') {
        $receiver_id = (int)$_POST['receiver_id'];
        $message = trim($_POST['message']);
        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            exit();
        }
        $stmt = $db->prepare("INSERT INTO direct_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $result = $stmt->execute([$user_id, $receiver_id, $message]);
        if ($result) {
            $msg_id = $db->lastInsertId();
            $new = $db->prepare("SELECT m.*, u.full_name as sender_name FROM direct_messages m JOIN users u ON m.sender_id = u.user_id WHERE m.message_id = ?");
            $new->execute([$msg_id]);
            echo json_encode(['success' => true, 'message' => $new->fetch(PDO::FETCH_ASSOC)]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to send message']);
        }
        exit();
    }
    if ($_POST['ajax_action'] == 'get_new_messages') {
        $other = (int)$_POST['other_user_id'];
        $last = (int)$_POST['last_id'];
        $stmt = $db->prepare("SELECT m.*, u.full_name as sender_name FROM direct_messages m JOIN users u ON m.sender_id = u.user_id WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)) AND m.message_id > ? ORDER BY m.created_at ASC");
        $stmt->execute([$user_id, $other, $other, $user_id, $last]);
        echo json_encode(['success' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }
    if ($_POST['ajax_action'] == 'get_conversations') {
        $conv_query = "SELECT u.user_id, u.full_name, u.role, u.department,
                      (SELECT COUNT(*) FROM direct_messages WHERE sender_id = u.user_id AND receiver_id = ? AND is_read = FALSE) as unread_count,
                      (SELECT MAX(created_at) FROM direct_messages WHERE (sender_id = ? AND receiver_id = u.user_id) OR (sender_id = u.user_id AND receiver_id = ?)) as last_message_time,
                      (SELECT message FROM direct_messages WHERE (sender_id = ? AND receiver_id = u.user_id) OR (sender_id = u.user_id AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message
                      FROM users u
                      WHERE u.user_id != ? AND u.role IN ('staff', 'supervisor', 'admin', 'dean', 'principal')
                      HAVING last_message_time IS NOT NULL
                      ORDER BY last_message_time DESC";
        $conv_stmt = $db->prepare($conv_query);
        $conv_stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
        echo json_encode(['success' => true, 'conversations' => $conv_stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Rep Direct Messages</title>
    <link rel="stylesheet" href="../classrep/css/classrep.css">
    <script src="https://cdn.socket.io/4.5.0/socket.io.min.js"></script>
</head>
<body data-user-id="<?php echo $user_id; ?>" 
      data-user-name="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" 
      data-user-role="<?php echo $_SESSION['role']; ?>"
      data-other-user-id="<?php echo $selected_user; ?>"
      data-last-message-id="<?php echo $last_message_id; ?>"
      data-join-room="<?php echo isset($_GET['join_room']) ? htmlspecialchars($_GET['join_room']) : ''; ?>">
<div class="header">
    <div><h1>Class Rep Direct Messages <small>Staff & Administration</small></h1></div>
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
</div>
<div class="main-container">
    <div class="users-sidebar">
        <div class="sidebar-header"><h3>Staff & Administration</h3><p>Select a user to start messaging</p></div>
        <div class="search-box"><input type="text" id="searchUsers" placeholder="Search users..." onkeyup="filterUsers()"></div>
        <div class="users-list" id="usersList">
            <?php if (empty($users)): ?>
                <div style="padding:20px; text-align:center;">No staff or admin users available</div>
            <?php else: foreach ($users as $user): 
                $is_active = ($selected_user == $user['user_id']);
            ?>
                <a href="?user=<?php echo $user['user_id']; ?>" class="user-item <?php echo $is_active ? 'active' : ''; ?>" data-user-id="<?php echo $user['user_id']; ?>" data-name="<?php echo strtolower(htmlspecialchars($user['full_name'])); ?>">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?> <span class="role-badge"><?php echo ucfirst($user['role']); ?></span></div>
                        <div class="user-department"><?php echo htmlspecialchars($user['department'] ?? 'No department'); ?></div>
                        <?php if ($user['last_message_time']): ?>
                            <div class="last-message-preview"><?php echo htmlspecialchars(substr($user['last_message'] ?? '', 0, 30)); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($user['unread_count'] > 0): ?>
                        <span class="unread-badge"><?php echo $user['unread_count']; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <div class="chat-area">
        <?php if ($selected_user && $other_user): ?>
            <div class="chat-header">
                <div class="chat-with">Chatting with <?php echo htmlspecialchars($other_user['full_name']); ?> <small><?php echo ucfirst($other_user['role']); ?></small></div>
                <button class="video-call-btn" onclick="startVideoCall(<?php echo $other_user['user_id']; ?>, '<?php echo addslashes($other_user['full_name']); ?>')"> Start Video Call</button>
            </div>
            <div class="messages-area" id="messagesArea">
                <?php if (empty($messages)): ?>
                    <div class="no-messages">No messages yet. Say hello!</div>
                <?php else: foreach ($messages as $msg): $is_sent = ($msg['sender_id'] == $user_id); ?>
                    <div class="message <?php echo $is_sent ? 'sent' : 'received'; ?>">
                        <?php if (!$is_sent): ?>
                            <div class="message-info">
                                <span class="sender-name"><?php echo htmlspecialchars($msg['sender_name']); ?></span>
                                <span class="message-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="message-info">
                                <span class="message-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="message-bubble"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="chat-input-area">
                <form id="messageForm" class="message-form">
                    <textarea id="messageInput" placeholder="Type your message..." rows="1"></textarea>
                    <div class="send-btn"><button type="submit" id="sendBtn">Send</button></div>
                </form>
            </div>
        <?php else: ?>
            <div class="empty-state"><h3> Select a user to start messaging</h3></div>
        <?php endif; ?>
    </div>
</div>

<!-- Video Call Modal -->
<div id="videoCallModal" class="modal" style="display: none;">
    <div class="modal-content video-call-modal">
        <span class="close" onclick="closeVideoCall()">&times;</span>
        <div class="video-call-container">
            <div class="video-header"><span>Video Call with <strong id="videoCallUserName"></strong></span><button class="end-call-btn" onclick="endVideoCall()">End Call</button></div>
            <div class="video-grid-call">
                <div class="video-box"><video id="callLocalVideo" autoplay muted playsinline></video><div class="video-label">You</div></div>
                <div class="video-box"><video id="callRemoteVideo" autoplay playsinline></video><div class="video-label" id="callRemoteLabel">Waiting...</div></div>
            </div>
            <div class="call-controls">
                <button class="ctrl-btn mic-btn" id="callMicBtn" onclick="toggleCallMic()">🎤</button>
                <button class="ctrl-btn cam-btn" id="callCamBtn" onclick="toggleCallCam()">📷</button>
            </div>
        </div>
    </div>
</div>

<script src="js/direct_messages.js"></script>
</body>
</html>