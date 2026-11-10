

// SEARCH FUNCTION (works with onkeyup) 
function filterUsers() {
    var input = document.getElementById('searchUsers');
    if (!input) return;
    var filter = input.value.toLowerCase();
    var userItems = document.querySelectorAll('.user-item');
    for (var i = 0; i < userItems.length; i++) {
        var name = userItems[i].getAttribute('data-name');
        if (name && name.indexOf(filter) !== -1) {
            userItems[i].style.display = '';
        } else {
            userItems[i].style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const messagesArea = document.getElementById('messagesArea');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const currentUserId = parseInt(document.body.dataset.userId);
    const otherUserId = parseInt(document.body.dataset.otherUserId);
    let lastMessageId = parseInt(document.body.dataset.lastMessageId) || 0;

    // Auto-resize textarea
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }

    // Send message
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (!message) return;

            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending...';

            const formData = new FormData();
            formData.append('ajax_action', 'send_message');
            formData.append('receiver_id', otherUserId);
            formData.append('message', message);

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        appendMessage(data.message);
                        messageInput.value = '';
                        messageInput.style.height = 'auto';
                        if (data.message.message_id > lastMessageId) lastMessageId = data.message.message_id;
                        scrollToBottom();
                    } else {
                        alert('Error: ' + data.error);
                    }
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Send';
                })
                .catch(error => {
                    console.error(error);
                    alert('Failed to send message');
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Send';
                });
        });
    }

    function appendMessage(msg) {
        const noMessages = document.querySelector('.no-messages');
        if (noMessages) noMessages.remove();

        const div = document.createElement('div');
        div.className = `message ${msg.sender_id == currentUserId ? 'sent' : 'received'}`;
        const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        let html = '';
        if (msg.sender_id != currentUserId) {
            html += `<div class="message-info"><span class="sender-name">${escapeHtml(msg.sender_name)}</span><span class="message-time">${time}</span></div>`;
        } else {
            html += `<div class="message-info"><span class="message-time">${time}</span></div>`;
        }
        html += `<div class="message-bubble">${escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>`;
        if (msg.sender_id == currentUserId && msg.is_read) html += `<div class="read-status">✓ Read</div>`;
        div.innerHTML = html;
        messagesArea.appendChild(div);
        scrollToBottom();
    }

    function checkNewMessages() {
        if (!otherUserId) return;
        const formData = new FormData();
        formData.append('ajax_action', 'get_new_messages');
        formData.append('other_user_id', otherUserId);
        formData.append('last_id', lastMessageId);
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages.length) {
                    data.messages.forEach(msg => {
                        appendMessage(msg);
                        if (msg.message_id > lastMessageId) lastMessageId = msg.message_id;
                        scrollToBottom();
                    });
                }
            })
            .catch(console.error);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function scrollToBottom() {
        if (messagesArea) {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    }

    setInterval(checkNewMessages, 2000);
    scrollToBottom();

    const joinRoom = document.body.dataset.joinRoom;
    if (joinRoom && joinRoom !== '') {
        joinVideoCall(joinRoom);
    }
});



// VIDEO CALL FUNCTIONS 
let callLocalStream = null;
let callPeerConnection = null;
let callSocket = null;
let currentCallRoomId = null;
let currentCallUserId = null;
let callUserName = null;
let callIsMicMuted = false;
let callIsCamOff = false;

const callConfig = {
    iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
};

function startVideoCall(targetUserId, targetUserName) {
    navigator.mediaDevices.getUserMedia({ video: true, audio: true })
        .then(testStream => {
            testStream.getTracks().forEach(track => track.stop());
            proceedWithCall(targetUserId, targetUserName);
        })
        .catch(err => {
            console.error(err);
            alert('Cannot access camera/microphone. Please allow permissions.');
        });
}

function proceedWithCall(targetUserId, targetUserName) {
    currentCallUserId = targetUserId;
    callUserName = targetUserName;
    document.getElementById('videoCallModal').style.display = 'flex';
    document.getElementById('videoCallUserName').innerText = targetUserName;

    if (!callSocket) {
        callSocket = io('http://localhost:3000', { transports: ['websocket'] });
        setupCallSocketEvents();
    }

    navigator.mediaDevices.getUserMedia({ video: true, audio: true })
        .then(stream => {
            callLocalStream = stream;
            document.getElementById('callLocalVideo').srcObject = stream;
            return fetch('webrtc_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'create_room', room_name: `Call with ${targetUserName}` })
            });
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentCallRoomId = data.room_id;
                callSocket.emit('join-room', currentCallRoomId, parseInt(document.body.dataset.userId), document.body.dataset.userName, document.body.dataset.userRole);
                sendJoinLink(currentCallRoomId, currentCallUserId).catch(console.error);
            } else {
                alert('Failed to start call: ' + (data.message || 'Unknown error'));
                closeVideoCall();
            }
        })
        .catch(err => { console.error(err); alert('Error starting call'); closeVideoCall(); });
}

function sendJoinLink(roomId, targetUserId) {
    return fetch('webrtc_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'send_join_link',
            room_id: roomId,
            sender_id: parseInt(document.body.dataset.userId),
            receiver_id: targetUserId,
            room_name: `Call with ${document.body.dataset.userName}`
        })
    }).then(res => res.json());
}

function joinVideoCall(roomId) {
    document.getElementById('videoCallModal').style.display = 'flex';
    document.getElementById('videoCallUserName').innerText = 'Room ' + roomId;
    navigator.mediaDevices.getUserMedia({ video: true, audio: true })
        .then(stream => {
            callLocalStream = stream;
            document.getElementById('callLocalVideo').srcObject = stream;
            return fetch('webrtc_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'join_room', room_id: roomId })
            });
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentCallRoomId = roomId;
                if (!callSocket) {
                    callSocket = io('http://localhost:3000', { transports: ['websocket'] });
                    setupCallSocketEvents();
                }
                callSocket.emit('join-room', currentCallRoomId, parseInt(document.body.dataset.userId), document.body.dataset.userName, document.body.dataset.userRole);
            } else {
                alert('Failed to join room: ' + (data.message || 'Unknown error'));
                closeVideoCall();
            }
        })
        .catch(err => { console.error(err); alert('Error joining call'); closeVideoCall(); });
}

function setupCallSocketEvents() {
    callSocket.on('connect', () => {
        console.log('Connected to signaling server');
        callSocket.emit('register', parseInt(document.body.dataset.userId));
    });
    callSocket.on('connect_error', (err) => alert('Signaling server not running on port 3000'));
    callSocket.on('user-joined', (data) => {
        if (data.userId != parseInt(document.body.dataset.userId) && data.userId == currentCallUserId) {
            createCallPeerConnection(data.userId);
        }
    });
    callSocket.on('user-left', (data) => {
        if (data.userId == currentCallUserId) {
            document.getElementById('callRemoteLabel').innerText = 'User left';
            if (callPeerConnection) callPeerConnection.close();
        }
    });
    callSocket.on('offer', async (data) => {
        if (!callPeerConnection) createCallPeerConnection(data.from);
        await callPeerConnection.setRemoteDescription(new RTCSessionDescription(data.offer));
        let answer = await callPeerConnection.createAnswer();
        await callPeerConnection.setLocalDescription(answer);
        callSocket.emit('answer', { to: data.from, answer: callPeerConnection.localDescription, room: currentCallRoomId });
    });
    callSocket.on('answer', (data) => callPeerConnection?.setRemoteDescription(new RTCSessionDescription(data.answer)));
    callSocket.on('ice-candidate', (data) => callPeerConnection?.addIceCandidate(new RTCIceCandidate(data.candidate)));
}

function createCallPeerConnection(targetId) {
    callPeerConnection = new RTCPeerConnection(callConfig);
    if (callLocalStream) callLocalStream.getTracks().forEach(t => callPeerConnection.addTrack(t, callLocalStream));
    callPeerConnection.onicecandidate = e => e.candidate && callSocket.emit('ice-candidate', { to: targetId, candidate: e.candidate, room: currentCallRoomId });
    callPeerConnection.ontrack = e => {
        document.getElementById('callRemoteVideo').srcObject = e.streams[0];
        document.getElementById('callRemoteLabel').innerText = callUserName;
    };
    callPeerConnection.createOffer()
        .then(offer => callPeerConnection.setLocalDescription(offer))
        .then(() => callSocket.emit('offer', { to: targetId, offer: callPeerConnection.localDescription, room: currentCallRoomId }))
        .catch(err => console.error(err));
    return callPeerConnection;
}

function toggleCallMic() {
    let track = callLocalStream?.getAudioTracks()[0];
    if (track) {
        callIsMicMuted = !callIsMicMuted;
        track.enabled = !callIsMicMuted;
        let btn = document.getElementById('callMicBtn');
        btn.textContent = callIsMicMuted ? '🔇' : '🎤';
        btn.classList.toggle('muted', callIsMicMuted);
    }
}
function toggleCallCam() {
    let track = callLocalStream?.getVideoTracks()[0];
    if (track) {
        callIsCamOff = !callIsCamOff;
        track.enabled = !callIsCamOff;
        let btn = document.getElementById('callCamBtn');
        btn.textContent = callIsCamOff ? '📷❌' : '📷';
        btn.classList.toggle('off', callIsCamOff);
    }
}
function endVideoCall() {
    if (currentCallRoomId) {
        fetch('webrtc_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'leave', room_id: currentCallRoomId, user_id: parseInt(document.body.dataset.userId) })
        }).catch(console.error);
        callSocket?.emit('leave-room', currentCallRoomId, parseInt(document.body.dataset.userId));
    }
    callLocalStream?.getTracks().forEach(t => t.stop());
    callPeerConnection?.close();
    closeVideoCall();
}
function closeVideoCall() {
    document.getElementById('videoCallModal').style.display = 'none';
    document.getElementById('callRemoteVideo').srcObject = null;
    document.getElementById('callLocalVideo').srcObject = null;
    currentCallRoomId = null;
    callLocalStream = null;
    callPeerConnection = null;
}
function filterUsers() {
    let term = document.getElementById('searchUsers').value.toLowerCase();
    document.querySelectorAll('.user-item').forEach(u => {
        let name = u.getAttribute('data-name') || '';
        u.style.display = name.includes(term) ? 'flex' : 'none';
    });
}
window.onbeforeunload = () => {
    if (currentCallRoomId) {
        navigator.sendBeacon('webrtc_handler.php', JSON.stringify({ action: 'leave', room_id: currentCallRoomId, user_id: parseInt(document.body.dataset.userId) }));
    }
};