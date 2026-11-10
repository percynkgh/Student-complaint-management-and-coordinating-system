

const configuration = {
    iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
};

const roomId = document.getElementById('roomIdDisplay').innerText;
const userId = parseInt(document.body.dataset.userId);
const userName = document.body.dataset.userName;
const userRole = document.body.dataset.userRole;

let localStream, peerConnection, socket;
let isMicMuted = false, isCamOff = false;
let remoteUserId = null;
let isInitiator = false;

socket = io('http://localhost:3000', { transports: ['websocket'] });

socket.on('connect', () => {
    console.log('Connected to signaling server');
    socket.emit('register', userId);
    startLocalStream();
});

socket.on('connect_error', (err) => {
    console.error('Connection error:', err);
    alert('Cannot connect to signaling server. Make sure server.js is running on port 3000.');
});

socket.on('user-joined', (data) => {
    console.log('User joined:', data);
    addParticipant(data.userId, data.userName, data.userRole);
    if (data.userId != userId && !peerConnection) {
        remoteUserId = data.userId;
        isInitiator = true;
        createPeerConnection(data.userId);
    }
});

socket.on('user-left', (data) => {
    console.log('User left:', data);
    removeParticipant(data.userId);
    if (peerConnection) {
        peerConnection.close();
        peerConnection = null;
    }
    document.getElementById('remoteVideo').srcObject = null;
    document.getElementById('remoteLabel').textContent = 'Participant left';
});

socket.on('offer', async (data) => {
    console.log('Received offer');
    if (!peerConnection) {
        isInitiator = false;
        createPeerConnection(data.from);
    }
    try {
        await peerConnection.setRemoteDescription(new RTCSessionDescription(data.offer));
        const answer = await peerConnection.createAnswer();
        await peerConnection.setLocalDescription(answer);
        socket.emit('answer', { to: data.from, answer: peerConnection.localDescription, room: roomId });
    } catch (err) {
        console.error('Error handling offer:', err);
    }
});

socket.on('answer', (data) => {
    console.log('Received answer');
    if (peerConnection && peerConnection.signalingState === 'have-local-offer') {
        peerConnection.setRemoteDescription(new RTCSessionDescription(data.answer)).catch(console.error);
    }
});

socket.on('ice-candidate', (data) => {
    console.log('Received ICE candidate');
    if (peerConnection) {
        peerConnection.addIceCandidate(new RTCIceCandidate(data.candidate)).catch(console.error);
    }
});

async function startLocalStream() {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        document.getElementById('localVideo').srcObject = localStream;
        socket.emit('join-room', roomId, userId, userName, userRole);
    } catch (err) {
        console.error('Camera error:', err);
        alert('Could not access camera/microphone. Please check permissions.');
    }
}

function createPeerConnection(targetUserId) {
    peerConnection = new RTCPeerConnection(configuration);
    if (localStream) {
        localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));
    }
    peerConnection.onicecandidate = (e) => {
        if (e.candidate) {
            socket.emit('ice-candidate', { to: targetUserId, candidate: e.candidate, room: roomId });
        }
    };
    peerConnection.ontrack = (e) => {
        console.log('Remote stream received');
        document.getElementById('remoteVideo').srcObject = e.streams[0];
        document.getElementById('remoteLabel').textContent = 'Remote User';
    };
    
    if (isInitiator) {
        peerConnection.createOffer()
            .then(offer => peerConnection.setLocalDescription(offer))
            .then(() => {
                socket.emit('offer', { to: targetUserId, offer: peerConnection.localDescription, room: roomId });
            })
            .catch(err => console.error('Error creating offer:', err));
    }
    
    return peerConnection;
}

function toggleMic() {
    let track = localStream?.getAudioTracks()[0];
    if (track) {
        isMicMuted = !isMicMuted;
        track.enabled = !isMicMuted;
        let btn = document.getElementById('micBtn');
        if (btn) {
            btn.textContent = isMicMuted ? '🔇' : '🎤';
            btn.classList.toggle('muted', isMicMuted);
        }
    }
}

function toggleCamera() {
    let track = localStream?.getVideoTracks()[0];
    if (track) {
        isCamOff = !isCamOff;
        track.enabled = !isCamOff;
        let btn = document.getElementById('camBtn');
        if (btn) {
            btn.textContent = isCamOff ? '📷❌' : '📷';
            btn.classList.toggle('off', isCamOff);
        }
    }
}

function endCall() {
    if (confirm('Leave the meeting?')) {
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
            localStream = null;
        }
        
        if (peerConnection) {
            peerConnection.close();
            peerConnection = null;
        }
        
        fetch('webrtc_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'leave', room_id: roomId, user_id: userId })
        }).catch(console.error);
        
        if (socket && roomId) {
            socket.emit('leave-room', roomId, userId);
        }
        
        document.getElementById('localVideo').srcObject = null;
        document.getElementById('remoteVideo').srcObject = null;
        document.getElementById('remoteLabel').textContent = 'Call ended';
        
        const micBtn = document.getElementById('micBtn');
        const camBtn = document.getElementById('camBtn');
        if (micBtn) micBtn.disabled = true;
        if (camBtn) camBtn.disabled = true;
    }
}

function endCallAndGoToMessages(event) {
    event.preventDefault();
    
    if (confirm('End the call and go back to messages?')) {
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
            localStream = null;
        }
        
        if (peerConnection) {
            peerConnection.close();
            peerConnection = null;
        }
        
        fetch('webrtc_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'leave', room_id: roomId, user_id: userId })
        }).catch(console.error);
        
        if (socket && roomId) {
            socket.emit('leave-room', roomId, userId);
        }
        
        window.location.href = 'direct_messages.php';
    }
}

function goBackBasedOnRole(event) {
    event.preventDefault();
    
    const userRole = document.body.dataset.userRole;
    
    if (userRole === 'admin' || userRole === 'dean' || userRole === 'principal') {
        // End call and go to direct messages
        if (confirm('End the call and go back to messages?')) {
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
            }
            if (peerConnection) {
                peerConnection.close();
                peerConnection = null;
            }
            fetch('webrtc_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'leave', room_id: roomId, user_id: userId })
            }).catch(console.error);
            if (socket && roomId) {
                socket.emit('leave-room', roomId, userId);
            }
            window.location.href = 'direct_messages.php';
        }
    } else {
        // Staff and class_rep: end call and go to dashboard
        if (confirm('End the call and go back to dashboard?')) {
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
            }
            if (peerConnection) {
                peerConnection.close();
                peerConnection = null;
            }
            fetch('webrtc_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'leave', room_id: roomId, user_id: userId })
            }).catch(console.error);
            if (socket && roomId) {
                socket.emit('leave-room', roomId, userId);
            }
            window.location.href = 'dashboard.php';
        }
    }
}

function copyRoomCode() {
    navigator.clipboard.writeText(roomId);
    alert('Room code copied: ' + roomId);
}

function addParticipant(id, name, role) {
    let container = document.getElementById('participants');
    if (document.getElementById(`p-${id}`)) return;
    let div = document.createElement('div');
    div.id = `p-${id}`;
    div.className = 'participant-item';
    div.innerHTML = `<div class="participant-status"></div><div><strong>${escapeHtml(name)}</strong> <span style="font-size:12px; opacity:0.7;">(${escapeHtml(role)})</span></div>`;
    container.appendChild(div);
    updateParticipantCount();
}

function removeParticipant(id) {
    let el = document.getElementById(`p-${id}`);
    if (el) el.remove();
    updateParticipantCount();
}

function updateParticipantCount() {
    let count = document.querySelectorAll('#participants .participant-item').length + 1;
    document.getElementById('participantCount').innerText = `Participants: ${count}`;
}

function escapeHtml(text) {
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

window.onbeforeunload = () => {
    if (socket && roomId) {
        navigator.sendBeacon('webrtc_handler.php', JSON.stringify({
            action: 'leave',
            room_id: roomId,
            user_id: userId
        }));
    }
};