<?php
if (isset($_GET['roomId']) && isset($_GET['role'])) {
    $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['roomId']);
    $role = preg_replace('/[^a-zA-Z]/', '', $_GET['role']);
    $filename = "signal_{$role}_{$roomId}.json";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = file_get_contents("php://input");
        file_put_contents($filename, $data);
        echo "Saved";
        exit;
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (file_exists($filename)) {
            header('Content-Type: application/json');
            readfile($filename);
        } else {
            echo json_encode(["status" => "no data"]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>P2P Video Sharing</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 20px;
      background-color: #f5f5f5;
      color: #333;
    }
    .container {
      max-width: 1200px;
      margin: 0 auto;
      background-color: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    h1 {
      text-align: center;
      color: #2c3e50;
    }
    .videos {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-bottom: 20px;
    }
    .video-container {
      flex: 1;
      min-width: 300px;
      border: 1px solid #ddd;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .video-container h3 {
      margin: 0;
      padding: 10px;
      background-color: #2c3e50;
      color: white;
      font-size: 16px;
      text-align: center;
    }
    video {
      width: 100%;
      background-color: #000;
      display: block;
    }
    .controls {
      padding: 15px;
      background-color: #f8f9fa;
      border-top: 1px solid #eee;
    }
    .connection-info {
      margin-bottom: 20px;
      padding: 15px;
      background-color: #f8f9fa;
      border-radius: 8px;
      border: 1px solid #ddd;
    }
    .room-controls {
      display: flex;
      gap: 10px;
      margin-bottom: 15px;
    }
    .btn {
      background-color: #3498db;
      color: white;
      border: none;
      padding: 10px 15px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      transition: background-color 0.3s;
    }
    .btn:hover {
      background-color: #2980b9;
    }
    .btn:disabled {
      background-color: #95a5a6;
      cursor: not-allowed;
    }
    .btn-danger {
      background-color: #e74c3c;
    }
    .btn-danger:hover {
      background-color: #c0392b;
    }
    #roomId {
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-family: monospace;
      flex: 1;
    }
    .status {
      margin-top: 10px;
      font-size: 14px;
      color: #7f8c8d;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>P2P Video Sharing</h1>
    <div class="connection-info">
      <h3>Connection Setup</h3>
      <div class="room-controls">
        <input type="text" id="roomId" placeholder="Enter room ID or generate a new one">
        <button id="generateRoom" class="btn">Generate Room ID</button>
        <button id="createRoom" class="btn">Create Room</button>
        <button id="joinRoom" class="btn">Join Room</button>
      </div>
      <div class="status" id="connectionStatus">Status: Not connected</div>
    </div>
    <div class="videos">
      <div class="video-container">
        <h3>Your Video</h3>
        <video id="localVideo" autoplay muted playsinline></video>
        <div class="controls">
          <button id="startVideo" class="btn">Start Camera</button>
          <button id="stopVideo" class="btn" disabled>Stop Camera</button>
        </div>
      </div>
      <div class="video-container">
        <h3>Peer Video</h3>
        <video id="remoteVideo" autoplay playsinline></video>
        <div class="controls">
          <div class="status" id="peerStatus">Waiting for peer to connect...</div>
        </div>
      </div>
    </div>
    <button id="disconnectBtn" class="btn btn-danger" disabled>Disconnect</button>
  </div>
  <script>
    const localVideo = document.getElementById('localVideo');
    const remoteVideo = document.getElementById('remoteVideo');
    const startVideoBtn = document.getElementById('startVideo');
    const stopVideoBtn = document.getElementById('stopVideo');
    const createRoomBtn = document.getElementById('createRoom');
    const joinRoomBtn = document.getElementById('joinRoom');
    const generateRoomBtn = document.getElementById('generateRoom');
    const disconnectBtn = document.getElementById('disconnectBtn');
    const roomIdInput = document.getElementById('roomId');
    const connectionStatus = document.getElementById('connectionStatus');
    const peerStatus = document.getElementById('peerStatus');
    const configuration = {
      iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' }
      ]
    };
    let localStream;
    let peerConnection;
    let roomId;
    let isRoomCreator = false;
    function init() {
      startVideoBtn.addEventListener('click', startVideo);
      stopVideoBtn.addEventListener('click', stopVideo);
      createRoomBtn.addEventListener('click', createRoom);
      joinRoomBtn.addEventListener('click', joinRoom);
      generateRoomBtn.addEventListener('click', generateRoomId);
      disconnectBtn.addEventListener('click', disconnect);
      generateRoomId();
    }
    function generateRoomId() {
      roomId = Math.floor(100000 + Math.random() * 900000).toString();
      roomIdInput.value = roomId;
    }
    async function startVideo() {
      try {
        localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        localVideo.srcObject = localStream;
        startVideoBtn.disabled = true;
        stopVideoBtn.disabled = false;
        updateStatus('Camera started. You can now create or join a room.');
      } catch (error) {
        console.error('Error accessing media devices:', error);
        updateStatus('Failed to access camera and microphone. ' + error.message);
      }
    }
    function stopVideo() {
      if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
        localVideo.srcObject = null;
        localStream = null;
        startVideoBtn.disabled = false;
        stopVideoBtn.disabled = true;
        updateStatus('Camera stopped.');
      }
    }
    function createRoom() {
      if (!localStream) {
        updateStatus('Please start your camera first.');
        return;
      }
      roomId = roomIdInput.value;
      if (!roomId) {
        updateStatus('Please enter or generate a room ID.');
        return;
      }
      isRoomCreator = true;
      initializePeerConnection();
      updateStatus('Room created! Creating offer...');
      createRoomBtn.disabled = true;
      joinRoomBtn.disabled = true;
      disconnectBtn.disabled = false;
      createOffer();
    }
    function joinRoom() {
      if (!localStream) {
        updateStatus('Please start your camera first.');
        return;
      }
      roomId = roomIdInput.value;
      if (!roomId) {
        updateStatus('Please enter a room ID to join.');
        return;
      }
      isRoomCreator = false;
      initializePeerConnection();
      updateStatus('Joining room. Waiting for offer...');
      createRoomBtn.disabled = true;
      joinRoomBtn.disabled = true;
      disconnectBtn.disabled = false;
      pollForOffer();
    }
    function initializePeerConnection() {
      peerConnection = new RTCPeerConnection(configuration);
      if (localStream) {
        localStream.getTracks().forEach(track => {
          peerConnection.addTrack(track, localStream);
        });
      }
      peerConnection.onicecandidate = event => {
        if (event.candidate) {
          console.log('New ICE candidate:', event.candidate);
        } else {
          if (isRoomCreator && peerConnection.localDescription) {
            postSDP(peerConnection.localDescription, 'offer');
            pollForAnswer();
          }
        }
      };
      peerConnection.onconnectionstatechange = () => {
        console.log('Connection state:', peerConnection.connectionState);
        if (peerConnection.connectionState === 'connected') {
          updateStatus('Peer connection established!');
          updatePeerStatus('Connected');
        } else if (peerConnection.connectionState === 'disconnected' || 
                   peerConnection.connectionState === 'failed') {
          updateStatus('Peer connection lost.');
          updatePeerStatus('Disconnected');
        }
      };
      peerConnection.ontrack = event => {
        if (remoteVideo.srcObject !== event.streams[0]) {
          remoteVideo.srcObject = event.streams[0];
          console.log('Received remote stream');
          updatePeerStatus('Video connected');
        }
      };
    }
    async function createOffer() {
      try {
        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);
        updateStatus('Offer created. Waiting for answer...');
      } catch (error) {
        console.error('Error creating offer:', error);
        updateStatus('Failed to create offer: ' + error.message);
      }
    }
    async function createAnswer() {
      try {
        const answer = await peerConnection.createAnswer();
        await peerConnection.setLocalDescription(answer);
        updateStatus('Answer created. Sending answer back to creator...');
      } catch (error) {
        console.error('Error creating answer:', error);
        updateStatus('Failed to create answer: ' + error.message);
      }
    }
    function postSDP(sdp, role) {
      fetch('index.php?roomId=' + roomId + '&role=' + role, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: sdp.type, sdp: sdp.sdp })
      })
      .then(response => response.text())
      .then(data => console.log(role + ' posted:', data))
      .catch(error => console.error('Error posting ' + role + ':', error));
    }
    function pollForOffer() {
      const interval = setInterval(async () => {
        try {
          const response = await fetch('index.php?roomId=' + roomId + '&role=offer');
          const data = await response.json();
          if (data && data.type && data.sdp) {
            clearInterval(interval);
            updateStatus('Offer received. Creating answer...');
            await peerConnection.setRemoteDescription(new RTCSessionDescription(data));
            await createAnswer();
            peerConnection.onicecandidate = event => {
              if (!event.candidate && peerConnection.localDescription) {
                postSDP(peerConnection.localDescription, 'answer');
              }
            };
          }
        } catch (error) {
          console.log('Waiting for offer...');
        }
      }, 2000);
    }
    function pollForAnswer() {
      const interval = setInterval(async () => {
        try {
          const response = await fetch('index.php?roomId=' + roomId + '&role=answer');
          const data = await response.json();
          if (data && data.type && data.sdp) {
            clearInterval(interval);
            updateStatus('Answer received. Establishing connection...');
            await peerConnection.setRemoteDescription(new RTCSessionDescription(data));
          }
        } catch (error) {
          console.log('Waiting for answer...');
        }
      }, 2000);
    }
    function disconnect() {
      if (peerConnection) {
        peerConnection.close();
        peerConnection = null;
      }
      remoteVideo.srcObject = null;
      createRoomBtn.disabled = false;
      joinRoomBtn.disabled = false;
      disconnectBtn.disabled = true;
      updateStatus('Disconnected from peer.');
      updatePeerStatus('Waiting for peer to connect...');
    }
    function updateStatus(message) {
      connectionStatus.textContent = 'Status: ' + message;
      console.log(message);
    }
    function updatePeerStatus(message) {
      peerStatus.textContent = message;
    }
    window.addEventListener('load', init);
  </script>
</body>
</html>