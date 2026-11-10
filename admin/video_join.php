<?php

require_once '../config/security.php';
require_once '../config/database.php';

Security::requireLogin();


$room_id = isset($_GET['room']) ? $_GET['room'] : '';
if (!$room_id) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Video Call</title>
    <link rel="stylesheet" href="../admin/css/admin.css">
    <script src="https://cdn.socket.io/4.5.0/socket.io.min.js"></script>
</head>
<body data-user-id="<?php echo $_SESSION['user_id']; ?>" 
      data-user-name="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" 
      data-user-role="<?php echo $_SESSION['role']; ?>">
    <div class="header">
        <h1> Video Call</h1>
        
          <a href="#" class="back-btn" onclick="endCallAndGoToMessages(event)">← Back </a>

    </div>
    
    <div class="video-join-container">
        <div class="call-header">
            <div class="room-info">
                <span>Meeting Room: <strong id="roomIdDisplay"><?php echo htmlspecialchars($room_id); ?></strong></span>
                <button class="copy-btn" onclick="copyRoomCode()"> Copy Code</button>
            </div>
            <div id="participantCount">Participants: 1</div>
        </div>
        
        <div class="video-grid-call">
            <div class="video-box">
                <video id="localVideo" autoplay muted playsinline></video>
                <div class="video-label">You</div>
            </div>
            <div class="video-box">
                <video id="remoteVideo" autoplay playsinline></video>
                <div class="video-label" id="remoteLabel">Waiting for participant...</div>
            </div>
        </div>
        
        <div class="call-controls">
            <button class="ctrl-btn mic-btn" id="micBtn" onclick="toggleMic()">🎤</button>
            <button class="ctrl-btn cam-btn" id="camBtn" onclick="toggleCamera()">📷</button>
            <button class="ctrl-btn end-btn" onclick="endCall()">❌</button>
        </div>
        
        <div class="participants-list">
            <h4> Participants</h4>
            <div id="participants"></div>
        </div>
    </div>
    
    <script src="../admin/js/video-join.js"></script>
</body>
</html>