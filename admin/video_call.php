<?php

require_once '../config/security.php';

Security::requireLogin();
if (!Security::hasRole('admin') && !Security::hasRole('dean') && !Security::hasRole('principal')) {
    header("Location: ../unauthorized.php");
    exit();
}

$db = (new Database())->getConnection();

// Get staff list
$staff = $db->query("SELECT user_id, full_name, department FROM users WHERE role = 'staff' ORDER BY full_name")->fetchAll();

// Get other admins
$admins = $db->prepare("SELECT user_id, full_name, role FROM users WHERE role IN ('admin','dean','principal') AND user_id != ?");
$admins->execute([$_SESSION['user_id']]);
$admins = $admins->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Video Call</title>
    <link rel="stylesheet" href="../admin/css/admin.css">
    <script src="https://cdn.socket.io/4.5.0/socket.io.min.js"></script>
</head>
<body data-user-id="<?php echo $_SESSION['user_id']; ?>" data-user-name="<?php echo $_SESSION['full_name']; ?>" data-user-role="<?php echo $_SESSION['role']; ?>">
    <div class="container">
        <div class="header">
            <h1> Admin Video Call</h1>
            <a href="dashboard.php" class="back-btn">← Back</a>
        </div>
        
        <div class="video-layout">
            <!-- Left Sidebar - Users -->
            <div class="video-sidebar">
                <div class="sidebar-section">
                    <h3>Staff Members</h3>
                    <?php foreach ($staff as $s): ?>
                    <div class="user-item" onclick="callUser(<?php echo $s['user_id']; ?>, '<?php echo addslashes($s['full_name']); ?>')">
                        <div class="user-avatar"><?php echo strtoupper(substr($s['full_name'], 0, 1)); ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                            <div class="user-dept"><?php echo htmlspecialchars($s['department'] ?? 'Staff'); ?></div>
                        </div>
                        <button class="call-btn">Call</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="sidebar-section">
                    <h3>Administration</h3>
                    <?php foreach ($admins as $a): ?>
                    <div class="user-item" onclick="callUser(<?php echo $a['user_id']; ?>, '<?php echo addslashes($a['full_name']); ?>')">
                        <div class="user-avatar"><?php echo strtoupper(substr($a['full_name'], 0, 1)); ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($a['full_name']); ?></div>
                            <div class="user-dept"><?php echo ucfirst($a['role']); ?></div>
                        </div>
                        <button class="call-btn">Call</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button class="create-room-btn" onclick="createRoom()">Create Meeting Room</button>
                
                <div class="join-room">
                    <input type="text" id="roomCode" placeholder="Enter room code">
                    <button onclick="joinRoom()">Join</button>
                </div>
            </div>
            
            <!-- Right Side - Video Call -->
            <div class="video-area">
                <div id="waitingArea" class="waiting-area">
                    <h3> Ready to Connect</h3>
                    <p>Click on a user or create a meeting room</p>
                </div>
                
                <div id="callArea" class="call-area hidden">
                    <div class="call-header">
                        <span>Room: <strong id="roomId"></strong></span>
                        <button class="copy-btn" onclick="copyRoomCode()">Copy Code</button>
                        <span id="participantCount">Participants: 1</span>
                    </div>
                    
                    <div class="video-grid">
                        <div class="video-box">
                            <video id="localVideo" autoplay muted playsinline></video>
                            <div class="video-label">You (<?php echo $_SESSION['role']; ?>)</div>
                        </div>
                        <div class="video-box">
                            <video id="remoteVideo" autoplay playsinline></video>
                            <div class="video-label" id="remoteLabel">Waiting...</div>
                        </div>
                    </div>
                    
                    <div class="call-controls">
                        <button class="ctrl-btn mic-btn" id="micBtn" onclick="toggleMic()">🎤</button>
                        <button class="ctrl-btn cam-btn" id="camBtn" onclick="toggleCam()">📷</button>
                        <button class="ctrl-btn end-btn" onclick="endCall()">❌</button>
                    </div>
                    
                    <div class="participants-list">
                        <h4>Participants</h4>
                        <div id="participantsList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../admin/js/admin-core.js"></script>
    <script src="../admin/js/video-call.js"></script>
</body>
</html>