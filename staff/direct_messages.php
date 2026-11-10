<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('staff') && !Security::hasRole('admin') && !Security::hasRole('dean') && !Security::hasRole('supervisor')) {
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

// Get all staff, admin, dean, principal, AND class reps (exclude students and current user)
$query = "SELECT u.user_id, u.full_name, u.email, u.role, u.department,
          (SELECT COUNT(*) FROM direct_messages WHERE sender_id = u.user_id AND receiver_id = ? AND is_read = FALSE) as unread_count,
          (SELECT MAX(created_at) FROM direct_messages WHERE (sender_id = ? AND receiver_id = u.user_id) OR (sender_id = u.user_id AND receiver_id = ?)) as last_message_time
          FROM users u
          WHERE u.user_id != ? AND u.role IN ('staff', 'supervisor', 'admin', 'dean', 'principal', 'class_rep')
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
                              WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
                              ORDER BY m.created_at ASC LIMIT 100");
    $msg_stmt->execute([$user_id, $selected_user, $selected_user, $user_id]);
    $messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($messages)) $last_message_id = end($messages)['message_id'];
    
    // Mark messages as read
    $update = $db->prepare("UPDATE direct_messages SET is_read = TRUE, read_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE");
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Direct Messages</title>
    <link rel="stylesheet" href="css/staff.css">
    <script src="https://cdn.socket.io/4.5.0/socket.io.min.js"></script>
    <style>
        /* Additional styles for class rep badge */
        .role-badge.role-class_rep {
            background: #f39c12;
            color: white;
        }
        .role-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 5px;
        }
        .role-staff { background: #3498db; color: white; }
        .role-supervisor { background: #16a085; color: white; }
        .role-admin { background: #dc3545; color: white; }
        .role-dean { background: #9b59b6; color: white; }
        .role-principal { background: #34495e; color: white; }
    </style>
</head>
<body class="direct-messages-layout" data-user-id="<?php echo $user_id; ?>" 
      data-user-name="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" 
      data-user-role="<?php echo $_SESSION['role']; ?>"
      data-other-user-id="<?php echo $selected_user; ?>"
      data-last-message-id="<?php echo $last_message_id; ?>"
      data-join-room="<?php echo isset($_GET['join_room']) ? htmlspecialchars($_GET['join_room']) : ''; ?>">
    
    <div class="header">
        <div>
            <h1>Staff Direct Messages</h1>
            <p>Message staff, administrators, and class representatives</p>
        </div>
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>
    
    <div class="main-container">
        <div class="users-sidebar">
            <div class="sidebar-header">
                <h3>Staff & Administration</h3>
                <p>Select a user to start messaging</p>
            </div>
            <div class="search-box">
                <input type="text" id="searchUsers" placeholder="Search users..." onkeyup="filterUsers()">
            </div>
            <div class="users-list" id="usersList">
                <?php if (empty($users)): ?>
                    <div style="padding:20px; text-align:center;">No users available to message</div>
                <?php else: foreach ($users as $user): 
                    $is_active = ($selected_user == $user['user_id']);
                ?>
                    <a href="?user=<?php echo $user['user_id']; ?>" 
                       class="user-item <?php echo $is_active ? 'active' : ''; ?>"
                       data-name="<?php echo strtolower(htmlspecialchars($user['full_name'])); ?>">
                        <div class="user-avatar"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
                        <div class="user-info">
                            <div class="user-name">
                                <?php echo htmlspecialchars($user['full_name']); ?> 
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php 
                                    if ($user['role'] == 'class_rep') {
                                        echo 'Class Rep';
                                    } else {
                                        echo ucfirst($user['role']); 
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="user-department"><?php echo htmlspecialchars($user['department'] ?? 'No department'); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
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
                    <div class="chat-with">
                        Chatting with <?php echo htmlspecialchars($other_user['full_name']); ?>
                        <small>
                            <?php 
                            if ($other_user['role'] == 'class_rep') {
                                echo 'Class Representative';
                            } else {
                                echo ucfirst($other_user['role']);
                            }
                            ?> • <?php echo htmlspecialchars($other_user['department'] ?? 'No department'); ?>
                        </small>
                    </div>
                    <button class="video-call-btn" onclick="startVideoCall(<?php echo $other_user['user_id']; ?>, '<?php echo addslashes($other_user['full_name']); ?>')">
                         Start Video Call
                    </button>
                </div>
                
                <div class="messages-area" id="messagesArea">
                    <?php if (empty($messages)): ?>
                        <div class="no-messages">No messages yet. Say hello to <?php echo htmlspecialchars($other_user['full_name']); ?>!</div>
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
                            <?php if ($is_sent && $msg['is_read']): ?>
                                <div class="read-status">✓ Read</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                
                <div class="chat-input-area">
                    <form id="messageForm" class="message-form">
                        <textarea id="messageInput" placeholder="Type your message..." rows="1"></textarea>
                        <button type="submit" id="sendBtn">Send</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3> Select a user to message</h3>
                    <p>Choose someone from the list to start messaging. You can message staff, administrators, deans, principals, and class representatives.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="videoCallModal" class="modal">
        <div class="modal-content">
            <div class="video-header">
                <div class="video-header-left">
                    <span>Video Call with <strong id="videoCallUserName"></strong></span>
                </div>
                <div>
                    <span class="close" onclick="closeVideoCall()">&times;</span>
                    <button class="end-call-btn" onclick="endVideoCall()">End Call</button>
                </div>
            </div>
            <div class="video-call-container">
                <div class="video-grid-call">
                    <div class="video-box">
                        <video id="callLocalVideo" autoplay muted playsinline></video>
                        <div class="video-label">You</div>
                    </div>
                    <div class="video-box">
                        <video id="callRemoteVideo" autoplay playsinline></video>
                        <div class="video-label" id="callRemoteLabel">Waiting...</div>
                    </div>
                </div>
                <div class="call-controls">
                    <button class="ctrl-btn mic-btn" id="callMicBtn" onclick="toggleCallMic()">🎤</button>
                    <button class="ctrl-btn cam-btn" id="callCamBtn" onclick="toggleCallCam()">📷</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/direct_messages.js"></script>
    <script>
        // Filter users by name
        function filterUsers() {
            let input = document.getElementById('searchUsers');
            let filter = input.value.toLowerCase();
            let usersList = document.getElementById('usersList');
            let userItems = usersList.getElementsByClassName('user-item');
            
            for (let i = 0; i < userItems.length; i++) {
                let name = userItems[i].getAttribute('data-name') || '';
                if (name.indexOf(filter) > -1) {
                    userItems[i].style.display = "";
                } else {
                    userItems[i].style.display = "none";
                }
            }
        }
        
        // Video call functions
        function startVideoCall(userId, userName) {
            document.getElementById('videoCallUserName').innerText = userName;
            document.getElementById('videoCallModal').style.display = 'flex';
            // Initialize video call logic here
        }
        
        function closeVideoCall() {
            document.getElementById('videoCallModal').style.display = 'none';
        }
        
        function endVideoCall() {
            closeVideoCall();
        }
        
        function toggleCallMic() {
            let micBtn = document.getElementById('callMicBtn');
            if (micBtn.classList.contains('muted')) {
                micBtn.classList.remove('muted');
                micBtn.innerHTML = '🎤';
            } else {
                micBtn.classList.add('muted');
                micBtn.innerHTML = '🔇';
            }
        }
        
        function toggleCallCam() {
            let camBtn = document.getElementById('callCamBtn');
            if (camBtn.classList.contains('off')) {
                camBtn.classList.remove('off');
                camBtn.innerHTML = '📷';
            } else {
                camBtn.classList.add('off');
                camBtn.innerHTML = '📷❌';
            }
        }
    </script>
</body>
</html>