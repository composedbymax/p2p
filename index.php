<?php
if (isset($_GET['roomId'], $_GET['role'])) {
  $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['roomId']);
  $role   = preg_replace('/[^a-zA-Z]/', '', $_GET['role']);
  $filename = "signal_{$role}_{$roomId}.json";
  $key = hash('sha256', $roomId . '_encryption_salt', true);
  switch($_SERVER['REQUEST_METHOD']) {
    case 'DELETE':
      echo json_encode(file_exists($filename) ? (unlink($filename) ? ["status"=>"deleted"] : ["status"=>"error deleting"]) : ["status"=>"file not found"]);
      exit;
    case 'POST':
      $data = file_get_contents("php://input");
      $iv = openssl_random_pseudo_bytes(16);
      $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
      file_put_contents($filename, base64_encode($iv . $encrypted));
      echo json_encode(["status" => "saved", "encrypted" => true]);
      exit;
    case 'GET':
      if (file_exists($filename)) {
        $binary = base64_decode(file_get_contents($filename));
        $iv = substr($binary, 0, 16);
        $encrypted = substr($binary, 16);
        header('Content-Type: application/json');
        echo openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
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
  <title>Secure P2P Video Sharing</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
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
    const localVideo = document.getElementById('localVideo'),
          remoteVideo  = document.getElementById('remoteVideo'),
          startBtn  = document.getElementById('startVideo'),
          stopBtn   = document.getElementById('stopVideo'),
          createBtn = document.getElementById('createRoom'),
          joinBtn   = document.getElementById('joinRoom'),
          genBtn    = document.getElementById('generateRoom'),
          discBtn   = document.getElementById('disconnectBtn'),
          roomInput = document.getElementById('roomId'),
          connStat  = document.getElementById('connectionStatus'),
          peerStat  = document.getElementById('peerStatus'),
          config = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }] };
    let localStream, pc, roomId, isCreator = false, pollAnswer, pollOffer;
    const updateStatus = msg => { connStat.textContent = 'Status: ' + msg; console.log(msg); },
          updatePeer = msg => peerStat.textContent = msg;
    const genRoomId = () => {
      roomId = Array.from({length:12}, () => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'.charAt(Math.floor(Math.random()*62))).join('');
      roomInput.value = roomId;
    };
    const startVideo = async () => {
      try {
        localStream = await navigator.mediaDevices.getUserMedia({video:true, audio:true});
        localVideo.srcObject = localStream;
        startBtn.disabled = true; stopBtn.disabled = false;
        updateStatus('Camera started. You can now create or join a room.');
      } catch(e) { updateStatus('Failed to access camera/microphone: ' + e.message); }
    };
    const stopVideo = () => {
      localStream && localStream.getTracks().forEach(t => t.stop());
      localVideo.srcObject = null; localStream = null;
      startBtn.disabled = false; stopBtn.disabled = true;
      updateStatus('Camera stopped.');
    };
    const initPC = () => {
      pc = new RTCPeerConnection(config);
      localStream && localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
      pc.onicecandidate = e => {
        if (e.candidate) console.log('ICE candidate:', e.candidate);
        else if (isCreator && pc.localDescription) postSDP(pc.localDescription, 'offer');
      };
      pc.onconnectionstatechange = () => {
        const state = pc.connectionState;
        if (state==='connected') { updateStatus('Peer connection established!'); updatePeer('Connected'); deleteSDP(); }
        else if (state==='disconnected' || state==='failed') { updateStatus('Peer connection lost.'); updatePeer('Disconnected'); }
      };
      pc.ontrack = e => { if (remoteVideo.srcObject !== e.streams[0]) { remoteVideo.srcObject = e.streams[0]; updatePeer('Video connected'); } };
    };
    const deleteSDP = () => {
      ['offer','answer'].forEach(role => {
        fetch('index.php?roomId='+roomId+'&role='+role, { method:'DELETE' })
          .then(r=>r.json()).then(data=> console.log('Deleted '+role+':', data))
          .catch(err=> console.error('Error deleting '+role, err));
      });
      updateStatus('Connection established and signaling data deleted.');
    };
    const postSDP = (sdp, role) => {
      fetch('index.php?roomId='+roomId+'&role='+role, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ type: sdp.type, sdp: sdp.sdp })
      }).then(r=>r.json()).then(data=> console.log(role+' posted:', data))
        .catch(e=> console.error('Error posting '+role, e));
    };
    const createOffer = async () => {
      try { 
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        updateStatus('Offer created. Waiting for answer...');
      } catch(e){ updateStatus('Offer creation failed: '+e.message); }
    };
    const createAnswer = async () => {
      try {
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        updateStatus('Answer created. Sending answer back to creator...');
      } catch(e){ updateStatus('Answer creation failed: '+e.message); }
    };
    const pollSDP = (role, callback, intervalVar) => {
      intervalVar && clearInterval(intervalVar);
      return setInterval(async () => {
        try {
          const res = await fetch('index.php?roomId='+roomId+'&role='+role);
          const data = await res.json();
          if (data && data.type && data.sdp) {
            clearInterval(intervalVar);
            callback(data);
          }
        } catch(e){ console.log('Waiting for '+role+'...'); }
      },2000);
    };
    const createRoom = () => {
      if (!localStream) return updateStatus('Please start your camera first.');
      roomId = roomInput.value;
      if (!roomId) return updateStatus('Please enter or generate a room ID.');
      isCreator = true; initPC();
      updateStatus('Room created! Creating offer...');
      createBtn.disabled = joinBtn.disabled = true; discBtn.disabled = false;
      createOffer();
      pollAnswer = pollSDP('answer', async data => {
        updateStatus('Answer received. Establishing connection...');
        await pc.setRemoteDescription(new RTCSessionDescription(data));
      });
    };
    const joinRoom = () => {
      if (!localStream) return updateStatus('Please start your camera first.');
      roomId = roomInput.value;
      if (!roomId) return updateStatus('Please enter a room ID to join.');
      isCreator = false; initPC();
      updateStatus('Joining room. Waiting for offer...');
      createBtn.disabled = joinBtn.disabled = true; discBtn.disabled = false;
      pollOffer = pollSDP('offer', async data => {
        updateStatus('Offer received. Creating answer...');
        await pc.setRemoteDescription(new RTCSessionDescription(data));
        await createAnswer();
        pc.onicecandidate = e => {
          if (!e.candidate && pc.localDescription) postSDP(pc.localDescription, 'answer');
        };
      });
    };
    const disconnect = () => {
      [pollAnswer, pollOffer].forEach(interval => interval && clearInterval(interval));
      deleteSDP();
      pc && (pc.close(), pc = null);
      remoteVideo.srcObject = null;
      createBtn.disabled = joinBtn.disabled = false; discBtn.disabled = true;
      updateStatus('Disconnected from peer.');
      updatePeer('Waiting for peer to connect...');
    };
    window.addEventListener('load', () => {
      startBtn.addEventListener('click', startVideo);
      stopBtn.addEventListener('click', stopVideo);
      createBtn.addEventListener('click', createRoom);
      joinBtn.addEventListener('click', joinRoom);
      genBtn.addEventListener('click', genRoomId);
      discBtn.addEventListener('click', disconnect);
      genRoomId();
    });
  </script>
</body>
</html>