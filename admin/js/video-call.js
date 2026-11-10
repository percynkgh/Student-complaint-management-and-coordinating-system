
let localStream, peerConnection, socket, currentRoomId;
let userId, userName, userRole;
let isMicMuted = false, isCamOff = false;

const config = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };

// Get user info from body
userId = parseInt(document.body.dataset.userId);
userName = document.body.dataset.userName;
userRole = document.body.dataset.userRole;

// Connect to signaling server
socket = io(window.location.origin, { transports: ['websocket'] });

socket.on('connect', () => console.log('Connected'));
socket.on('user-joined', (data) => {
    addParticipant(data.userId, data.userName);
    if (data.userId != userId) createPeerConnection(data.userId);
});
socket.on('user-left', (data) => removeParticipant(data.userId));
socket.on('offer', async (data) => {
    if (!peerConnection) createPeerConnection(data.from);
    await peerConnection.setRemoteDescription(new RTCSessionDescription(data.offer));
    let answer = await peerConnection.createAnswer();
    await peerConnection.setLocalDescription(answer);
    socket.emit('answer', { to: data.from, answer, room: currentRoomId });
});
socket.on('answer', (data) => peerConnection?.setRemoteDescription(new RTCSessionDescription(data.answer)));
socket.on('ice-candidate', (data) => peerConnection?.addIceCandidate(new RTCIceCandidate(data.candidate)));

// Start local camera/mic
async function startLocalStream() {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        document.getElementById('localVideo').srcObject = localStream;
        return true;
    } catch (e) {
        alert('Cannot access camera/microphone');
        return false;
    }
}

// Create meeting room
async function createRoom() {
    if (!await startLocalStream()) return;
    let res = await fetch('webrtc_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create_room' })
    });
    let data = await res.json();
    if (data.success) startCall(data.room_id);
    else alert('Failed to create room');
}

// Call a user
async function callUser(targetId, targetName) {
    if (!await startLocalStream()) return;
    let res = await fetch('webrtc_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create_room', room_name: `Call with ${targetName}` })
    });
    let data = await res.json();
    if (data.success) startCall(data.room_id);
    else alert('Failed to start call');
}

// Join room by code
async function joinRoom() {
    let roomCode = document.getElementById('roomCode').value.trim();
    if (!roomCode) return alert('Enter room code');
    if (!await startLocalStream()) return;
    let res = await fetch('webrtc_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'join_room', room_id: roomCode })
    });
    let data = await res.json();
    if (data.success) startCall(roomCode);
    else alert('Room not found');
}

// Start the call
function startCall(roomId) {
    currentRoomId = roomId;
    socket.emit('join-room', roomId, userId, userName, userRole);
    document.getElementById('waitingArea').classList.add('hidden');
    document.getElementById('callArea').classList.remove('hidden');
    document.getElementById('roomId').innerText = roomId;
}

// End call
async function endCall() {
    if (currentRoomId) {
        await fetch('webrtc_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'leave', room_id: currentRoomId, user_id: userId })
        });
        socket.emit('leave-room', currentRoomId, userId);
    }
    localStream?.getTracks().forEach(t => t.stop());
    peerConnection?.close();
    document.getElementById('waitingArea').classList.remove('hidden');
    document.getElementById('callArea').classList.add('hidden');
    document.getElementById('remoteVideo').srcObject = null;
    currentRoomId = null;
}

// Create peer connection
function createPeerConnection(targetId) {
    peerConnection = new RTCPeerConnection(config);
    localStream.getTracks().forEach(t => peerConnection.addTrack(t, localStream));
    peerConnection.onicecandidate = e => e.candidate && socket.emit('ice-candidate', { to: targetId, candidate: e.candidate, room: currentRoomId });
    peerConnection.ontrack = e => document.getElementById('remoteVideo').srcObject = e.streams[0];
    peerConnection.createOffer().then(offer => peerConnection.setLocalDescription(offer))
        .then(() => socket.emit('offer', { to: targetId, offer: peerConnection.localDescription, room: currentRoomId }));
    return peerConnection;
}

// Toggle mic
function toggleMic() {
    let track = localStream?.getAudioTracks()[0];
    if (track) {
        isMicMuted = !isMicMuted;
        track.enabled = !isMicMuted;
        let btn = document.getElementById('micBtn');
        btn.textContent = isMicMuted ? '🔇' : '🎤';
        btn.classList.toggle('muted', isMicMuted);
    }
}

// Toggle camera
function toggleCam() {
    let track = localStream?.getVideoTracks()[0];
    if (track) {
        isCamOff = !isCamOff;
        track.enabled = !isCamOff;
        let btn = document.getElementById('camBtn');
        btn.textContent = isCamOff ? '📷❌' : '📷';
        btn.classList.toggle('off', isCamOff);
    }
}

// Copy room code
function copyRoomCode() {
    navigator.clipboard.writeText(currentRoomId);
    alert('Room code copied!');
}

// Add participant to list
function addParticipant(id, name) {
    let container = document.getElementById('participantsList');
    if (document.getElementById(`p-${id}`)) return;
    let div = document.createElement('div');
    div.id = `p-${id}`;
    div.className = 'participant';
    div.innerHTML = `<div class="status"></div><span>${escapeHtml(name)}</span>`;
    container.appendChild(div);
    updateParticipantCount();
}

// Remove participant
function removeParticipant(id) {
    let el = document.getElementById(`p-${id}`);
    if (el) el.remove();
    updateParticipantCount();
}

// Update participant count
function updateParticipantCount() {
    let count = document.querySelectorAll('#participantsList .participant').length + 1;
    document.getElementById('participantCount').innerText = `Participants: ${count}`;
}

// Escape HTML
function escapeHtml(text) {
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Clean up on page unload
window.onbeforeunload = () => {
    if (currentRoomId) {
        navigator.sendBeacon('webrtc_handler.php', JSON.stringify({ action: 'leave', room_id: currentRoomId, user_id: userId }));
    }
};