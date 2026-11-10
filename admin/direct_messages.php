<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('admin') && !Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: /SCCS/auth/unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];
$selected_user = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// Helper function for avatar color
function getAvatarClass($role) {
    switch($role) {
        case 'admin': return 'avatar-admin';
        case 'dean': return 'avatar-dean';
        case 'principal': return 'avatar-principal';
        case 'staff': return 'avatar-staff';
        case 'class_rep': return 'avatar-class_rep';
        default: return 'avatar-default';
    }
}

function getRoleBadgeClass($role) {
    switch($role) {
        case 'admin': return 'role-admin';
        case 'dean': return 'role-dean';
        case 'principal': return 'role-principal';
        case 'staff': return 'role-staff';
        case 'class_rep': return 'role-class_rep';
        default: return 'role-default';
    }
}

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

// Get all users (all roles) with last message time
$query = "SELECT u.user_id, u.full_name, u.email, u.role, u.department,
          (SELECT COUNT(*) FROM direct_messages WHERE sender_id = u.user_id AND receiver_id = ? AND is_read = FALSE) as unread_count,
          (SELECT MAX(created_at) FROM direct_messages WHERE (sender_id = ? AND receiver_id = u.user_id) OR (sender_id = u.user_id AND receiver_id = ?)) as last_message_time
          FROM users u
          WHERE u.user_id != ? AND u.role IN ('staff','supervisor','dean','principal','class_rep')
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Direct Messages</title>
    <link rel="stylesheet" href="../admin/css/admin.css">
    <script src="https://cdn.socket.io/4.5.0/socket.io.min.js"></script>
    <style>
        /* Avatar styling */
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
            margin-right: 12px;
            flex-shrink: 0;
            text-transform: uppercase;
        }
        
        /* Role avatar colors */
        .avatar-admin { background: #dc3545; }
        .avatar-dean { background: #9b59b6; }
        .avatar-principal { background: #34495e; }
        .avatar-staff { background: #3498db; }
        .avatar-class_rep { background: #f39c12; }
        .avatar-default { background: #6c757d; }
        
        /* Role badge colors */
        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 5px;
        }
        .role-admin { background: #dc3545; color: white; }
        .role-dean { background: #9b59b6; color: white; }
        .role-principal { background: #34495e; color: white; }
        .role-staff { background: #3498db; color: white; }
        .role-class_rep { background: #f39c12; color: white; }
        .role-default { background: #6c757d; color: white; }
        
        /* Unread badge */
        .unread-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            min-width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
            padding: 0 5px;
        }
        
        /* User item styling */
        .user-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            text-decoration: none;
            transition: background 0.2s;
        }
        .user-item:hover {
            background: #f5f5f5;
        }
        .user-item.active {
            background: #e3f2fd;
            border-left: 3px solid #2196F3;
        }
        .user-info {
            flex: 1;
        }
        .user-name {
            font-weight: 600;
            color: #333;
        }
        .user-department {
            font-size: 11px;
            color: #666;
        }
        .user-email {
            font-size: 10px;
            color: #999;
        }
    </style>
</head>
<body data-user-id="<?php echo $user_id; ?>" 
      data-user-name="<?php echo $_SESSION['full_name']; ?>" 
      data-user-role="<?php echo $_SESSION['role']; ?>"
      data-other-user-id="<?php echo $selected_user; ?>"
      data-last-message-id="<?php echo $last_message_id; ?>"
      data-join-room="<?php echo isset($_GET['join_room']) ? htmlspecialchars($_GET['join_room']) : ''; ?>">
    <div class="header">
        <div><h1>Admin Direct Messages <small>All Users</small></h1></div>
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>
    
    <div class="main-container">
        <!-- Sidebar with working search -->
        <div class="users-sidebar">
            <div class="sidebar-header">
                <h3>All Users</h3>
                <p>Select a user to start messaging</p>
            </div>
            <div class="search-box">
                <input type="text" id="searchUsers" placeholder="Search users..." onkeyup="filterUsers()">
            </div>
            <div class="users-list" id="usersList">
                <?php if (empty($users)): ?>
                    <div style="padding:20px; text-align:center;">No users available</div>
                <?php else: foreach ($users as $user): 
                    $is_active = ($selected_user == $user['user_id']);
                    $avatar_class = getAvatarClass($user['role']);
                    $badge_class = getRoleBadgeClass($user['role']);
                    // Get initials (2 letters)
                    $name_parts = explode(' ', $user['full_name']);
                    $initials = strtoupper(substr($name_parts[0], 0, 1));
                    if (count($name_parts) > 1) {
                        $initials .= strtoupper(substr(end($name_parts), 0, 1));
                    }
                ?>
                    <a href="?user=<?php echo $user['user_id']; ?>" 
                       class="user-item <?php echo $is_active ? 'active' : ''; ?>"
                       data-name="<?php echo strtolower(htmlspecialchars($user['full_name'])); ?>">
                        <div class="user-avatar <?php echo $avatar_class; ?>">
                            <?php echo $initials; ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name">
                                <?php echo htmlspecialchars($user['full_name']); ?> 
                                <span class="role-badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
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
        
        <!-- Chat area -->
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
                            <div class="message-bubble"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                            <div class="message-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                
                <div class="chat-input-area">
                    <form id="messageForm" class="message-form">
                        <textarea id="messageInput" placeholder="Type your message..." rows="1"></textarea>
                        <button type="submit" id="sendBtn" class="send-btn">Send</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-state"><h3> Select a user to start messaging</h3></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Video call modal -->
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
    
    <script>
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
        
        function startVideoCall(userId, userName) {
            document.getElementById('videoCallUserName').innerText = userName;
            document.getElementById('videoCallModal').style.display = 'flex';
        }
        
        function closeVideoCall() {
            document.getElementById('videoCallModal').style.display = 'none';
        }
        
        function endVideoCall() {
            closeVideoCall();
        }
        
        function toggleCallMic() {
            let micBtn = document.getElementById('callMicBtn');
            micBtn.classList.toggle('muted');
            micBtn.innerHTML = micBtn.classList.contains('muted') ? '🔇' : '🎤';
        }
        
        function toggleCallCam() {
            let camBtn = document.getElementById('callCamBtn');
            camBtn.classList.toggle('off');
            camBtn.innerHTML = camBtn.classList.contains('off') ? '📷❌' : '📷';
        }
    </script>
    <script src="../admin/js/direct-messages.js"></script>
</body>
</html>