<?php
if(isset($_GET['roomId'],$_GET['role'])){
  $roomId = preg_replace('/[^a-zA-Z0-9]/','',$_GET['roomId']);
  $role = preg_replace('/[^a-zA-Z]/','',$_GET['role']);
  $filename = "signal_{$role}_{$roomId}.json";
  $key = hash('sha256',$roomId.'_encryption_salt',true);
  switch($_SERVER['REQUEST_METHOD']){
    case 'DELETE':
      echo json_encode(file_exists($filename)?(unlink($filename)?['status'=>'deleted']:['status'=>'error deleting']):['status'=>'file not found']);
      break;
    case 'POST':
      $data = file_get_contents("php://input");
      $iv = openssl_random_pseudo_bytes(16);
      file_put_contents($filename, base64_encode($iv.openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv)));
      echo json_encode(['status'=>'saved','encrypted'=>true]);
      break;
    case 'GET':
      if(file_exists($filename)){
        $binary = base64_decode(file_get_contents($filename));
        header('Content-Type: application/json');
        echo openssl_decrypt(substr($binary,16),'AES-256-CBC',$key,OPENSSL_RAW_DATA,substr($binary,0,16));
      } else echo json_encode(['status'=>'no data']);
      break;
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="en"><head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>P2P</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include '../header.php'; ?>
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
          <button id="shareScreen" class="btn" disabled>Share Screen</button>
          <button id="stopScreen" class="btn" disabled>Stop Sharing</button>
          <button id="switchToCamera" class="btn" disabled>Switch to Camera</button>
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
  <?php include '../footer.php'; ?>
  <script>
    (() => {
      const $ = id => document.getElementById(id),
        localVideo = $('localVideo'),
        remoteVideo = $('remoteVideo'),
        startBtn = $('startVideo'),
        stopBtn = $('stopVideo'),
        shareScreenBtn = $('shareScreen'),
        stopScreenBtn = $('stopScreen'),
        switchToCameraBtn = $('switchToCamera'),
        createBtn = $('createRoom'),
        joinBtn = $('joinRoom'),
        genBtn = $('generateRoom'),
        discBtn = $('disconnectBtn'),
        roomInput = $('roomId'),
        connStat = $('connectionStatus'),
        peerStat = $('peerStatus'),
        config = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }] };
      let localStream, screenStream, pc, roomId, isCreator = false, pollAnswer, pollOffer, activeStream = 'camera';
      const updateStatus = msg => { connStat.textContent = 'Status: ' + msg; console.log(msg); },
            updatePeer = msg => peerStat.textContent = msg,
            genRoomId = () => {
              roomId = Array.from({ length: 12 }, () => "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789".charAt(Math.floor(Math.random() * 62))).join('');
              roomInput.value = roomId;
            },
            createBlackVideoTrack = () => {
              const canvas = document.createElement('canvas'); canvas.width = 640; canvas.height = 480;
              const ctx = canvas.getContext('2d');
              ctx.fillStyle = 'black'; ctx.fillRect(0, 0, canvas.width, canvas.height);
              return canvas.captureStream(10).getVideoTracks()[0];
            },
            startVideo = async () => {
              try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                localVideo.srcObject = localStream; activeStream = 'camera';
                if(pc){
                  const newTrack = localStream.getVideoTracks()[0];
                  const sender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
                  sender && sender.replaceTrack(newTrack);
                }
                startBtn.disabled = true; stopBtn.disabled = false; shareScreenBtn.disabled = false;
                updateStatus('Camera started. You can now create or join a room.');
              } catch(e){ updateStatus('Failed to access camera/microphone: ' + e.message); }
            },
            stopVideo = () => {
              if(activeStream==='camera' && localStream){
                const blackTrack = createBlackVideoTrack();
                if(pc){
                  pc.getSenders().forEach(s => { if(s.track && s.track.kind==='video') s.replaceTrack(blackTrack); });
                }
                localStream.getTracks().forEach(t => t.stop());
                localVideo.srcObject = new MediaStream([blackTrack]); localStream = null;
                startBtn.disabled = false; stopBtn.disabled = true; shareScreenBtn.disabled = true; switchToCameraBtn.disabled = true;
                updateStatus('Camera stopped.');
              }
            },
            startScreenShare = async () => {
              try{
                screenStream = await navigator.mediaDevices.getDisplayMedia({ video: { cursor: 'always', displaySurface: 'monitor' }, audio: false });
                screenStream.getVideoTracks()[0].onended = stopScreenShare;
                if(pc) pc.getSenders().filter(s => s.track?.kind==='video').forEach(s => s.replaceTrack(screenStream.getVideoTracks()[0]));
                localVideo.srcObject = screenStream; activeStream = 'screen';
                shareScreenBtn.disabled = true; stopScreenBtn.disabled = false; switchToCameraBtn.disabled = false;
                updateStatus('Screen sharing started');
              } catch(e){ updateStatus('Failed to start screen sharing: ' + e.message); }
            },
            stopScreenShare = () => {
              if(screenStream){
                screenStream.getTracks().forEach(t => t.stop()); screenStream = null;
                shareScreenBtn.disabled = false; stopScreenBtn.disabled = true; switchToCameraBtn.disabled = true;
                localStream ? switchToCamera() : (localVideo.srcObject = null, activeStream = null);
                updateStatus('Screen sharing stopped');
              }
            },
            switchToCamera = () => {
              if(localStream){
                if(pc){
                  pc.getSenders().filter(s => s.track?.kind==='video').forEach(s => s.replaceTrack(localStream.getVideoTracks()[0]));
                }
                localVideo.srcObject = localStream; activeStream = 'camera';
                shareScreenBtn.disabled = false; stopScreenBtn.disabled = true; switchToCameraBtn.disabled = true;
                updateStatus('Switched back to camera');
              }
            },
            initPC = () => {
              pc = new RTCPeerConnection(config);
              if(activeStream==='camera' && localStream) localStream.getTracks().forEach(t => pc.addTrack(t,localStream));
              else if(activeStream==='screen' && screenStream){
                pc.addTrack(screenStream.getVideoTracks()[0],screenStream);
                localStream?.getAudioTracks()[0] && pc.addTrack(localStream.getAudioTracks()[0],localStream);
              }
              pc.onicecandidate = e => {
                if(e.candidate) console.log('ICE candidate:',e.candidate);
                else if(isCreator && pc.localDescription) postSDP(pc.localDescription, 'offer');
              };
              pc.onconnectionstatechange = () => {
                const state = pc.connectionState;
                if(state==='connected'){
                  updateStatus('Peer connection established!');
                  updatePeer('Connected');
                  deleteSDP();
                } else if(state==='disconnected'||state==='failed'){
                  updateStatus('Peer connection lost.');
                  updatePeer('Disconnected');
                }
              };
              pc.ontrack = e => {
                if(remoteVideo.srcObject !== e.streams[0]){
                  remoteVideo.srcObject = e.streams[0];
                  updatePeer('Video connected');
                }
              };
            },
            deleteSDP = () => {
              ['offer','answer'].forEach(r=>{
                fetch('index.php?roomId='+roomId+'&role='+r,{method:'DELETE'})
                  .then(r=>r.json()).then(data=>console.log('Deleted '+r+':',data))
                  .catch(err=>console.error('Error deleting '+r,err));
              });
              updateStatus('Connection established and signaling data deleted.');
            },
            postSDP = (sdp,role) => {
              fetch('index.php?roomId='+roomId+'&role='+role,{
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body:JSON.stringify({ type: sdp.type, sdp: sdp.sdp })
              }).then(r=>r.json()).then(data=>console.log(role+' posted:',data))
              .catch(e=>console.error('Error posting '+role,e));
            },
            createOffer = async () => {
              try{
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                updateStatus('Offer created. Waiting for answer...');
              } catch(e){ updateStatus('Offer creation failed: '+e.message); }
            },
            createAnswer = async () => {
              try{
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                updateStatus('Answer created. Sending answer back to creator...');
              } catch(e){ updateStatus('Answer creation failed: '+e.message); }
            },
            pollSDP = (role, callback) => {
              const interval = setInterval(async () => {
                try{
                  const res = await fetch('index.php?roomId='+roomId+'&role='+role);
                  const data = await res.json();
                  if(data?.type && data.sdp){
                    clearInterval(interval);
                    callback(data);
                  }
                } catch(e){ console.log('Waiting for '+role+'...'); }
              },2000);
              return interval;
            },
            createRoom = () => {
              if(!localStream && !screenStream) return updateStatus('Please start your camera or share your screen first.');
              roomId = roomInput.value;
              if(!roomId) return updateStatus('Please enter or generate a room ID.');
              isCreator = true; initPC();
              updateStatus('Room created! Creating offer...');
              createBtn.disabled = joinBtn.disabled = true; discBtn.disabled = false;
              createOffer();
              pollAnswer = pollSDP('answer', async data => {
                updateStatus('Answer received. Establishing connection...');
                await pc.setRemoteDescription(new RTCSessionDescription(data));
              });
            },
            joinRoom = () => {
              if(!localStream && !screenStream) return updateStatus('Please start your camera or share your screen first.');
              roomId = roomInput.value;
              if(!roomId) return updateStatus('Please enter a room ID to join.');
              isCreator = false; initPC();
              updateStatus('Joining room. Waiting for offer...');
              createBtn.disabled = joinBtn.disabled = true; discBtn.disabled = false;
              pollOffer = pollSDP('offer', async data => {
                updateStatus('Offer received. Creating answer...');
                await pc.setRemoteDescription(new RTCSessionDescription(data));
                await createAnswer();
                pc.onicecandidate = e => { if(!e.candidate && pc.localDescription) postSDP(pc.localDescription, 'answer'); };
              });
            },
            disconnect = () => {
              [pollAnswer, pollOffer].forEach(i => i && clearInterval(i));
              deleteSDP();
              if(pc){ pc.close(); pc = null; }
              remoteVideo.srcObject = null;
              createBtn.disabled = joinBtn.disabled = false; discBtn.disabled = true;
              updateStatus('Disconnected from peer.');
              updatePeer('Waiting for peer to connect...');
            };
      window.addEventListener('load', () => {
        startBtn.addEventListener('click', startVideo);
        stopBtn.addEventListener('click', stopVideo);
        shareScreenBtn.addEventListener('click', startScreenShare);
        stopScreenBtn.addEventListener('click', stopScreenShare);
        switchToCameraBtn.addEventListener('click', switchToCamera);
        createBtn.addEventListener('click', createRoom);
        joinBtn.addEventListener('click', joinRoom);
        genBtn.addEventListener('click', genRoomId);
        discBtn.addEventListener('click', disconnect);
        genRoomId();
      });
    })();
  </script>
</body>
</html>