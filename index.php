<?php
session_start();
define('USERS_FILE', 'users.json');
define('ROOMS_FILE', 'rooms.json');
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode([]));
}
if (!file_exists(ROOMS_FILE)) {
    file_put_contents(ROOMS_FILE, json_encode([]));
}
function authenticateUser($username, $password) {
    $users = json_decode(file_get_contents(USERS_FILE), true);
    foreach ($users as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password'])) {
            return true;
        }
    }
    return false;
}
function registerUser($username, $password) {
    $users = json_decode(file_get_contents(USERS_FILE), true);
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return false;
        }
    }
    $users[] = [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT)
    ];
    file_put_contents(USERS_FILE, json_encode($users));
    return true;
}
function handleRoom($roomId, $username, $isCreate = false) {
    $rooms = json_decode(file_get_contents(ROOMS_FILE), true);
    $roomExists = false;
    foreach ($rooms as $index => $room) {
        if (isset($room['id']) && $room['id'] === $roomId) {
            $roomExists = true;
            $rooms[$index]['lastAccessed'] = time();
            if (!in_array($username, $room['users'])) {
                $rooms[$index]['users'][] = $username;
            }
            break;
        }
    }
    if (!$roomExists && $isCreate) {
        $rooms[] = [
            'id' => $roomId,
            'creator' => $username,
            'created' => time(),
            'lastAccessed' => time(),
            'users' => [$username]
        ];
        $roomExists = true;
    }
    file_put_contents(ROOMS_FILE, json_encode($rooms));
    return $roomExists;
}
$error = null;
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['roomId'] ?? '');
        if (empty($username) || empty($password) || empty($roomId)) {
            $error = "All fields are required.";
        } else {
            if ($_POST['action'] === 'create') {
                if (!authenticateUser($username, $password)) {
                    registerUser($username, $password);
                }
                if (handleRoom($roomId, $username, true)) {
                    $_SESSION['username'] = $username;
                    $_SESSION['roomId'] = $roomId;
                    $success = "Room created successfully!";
                } else {
                    $error = "Failed to create room.";
                }
            } elseif ($_POST['action'] === 'join') {
                if (authenticateUser($username, $password)) {
                    if (handleRoom($roomId, $username, false)) {
                        $_SESSION['username'] = $username;
                        $_SESSION['roomId'] = $roomId;
                        $success = "Joined room successfully!";
                    } else {
                        $error = "Room does not exist.";
                    }
                } else {
                    $error = "Invalid username or password.";
                }
            }
        }
    } elseif (isset($_POST['logout'])) {
        session_unset();
        session_destroy();
    }
}
if (isset($_GET['roomId'], $_GET['role']) && isset($_SESSION['username'])) {
    $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['roomId']);
    $role = preg_replace('/[^a-zA-Z]/', '', $_GET['role']);
    $filename = "signal_{$role}_{$roomId}.json";
    $key = hash('sha256', $roomId.'_encryption_salt', true);
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'DELETE':
            echo json_encode(file_exists($filename) ? (unlink($filename) ? ['status' => 'deleted'] : ['status' => 'error deleting']) : ['status' => 'file not found']);
            break;
        case 'POST':
            $data = file_get_contents("php://input");
            $iv = openssl_random_pseudo_bytes(16);
            file_put_contents($filename, base64_encode($iv.openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv)));
            echo json_encode(['status' => 'saved', 'encrypted' => true]);
            break;
        case 'GET':
            if (file_exists($filename)) {
                $binary = base64_decode(file_get_contents($filename));
                header('Content-Type: application/json');
                echo openssl_decrypt(substr($binary, 16), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr($binary, 0, 16));
            } else {
                echo json_encode(['status' => 'no data']);
            }
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P2P Video Chat</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/root.css">
    <link rel="stylesheet" href="slider.css">
</head>
<body>
    <?php if (isset($_SESSION['username']) && isset($_SESSION['roomId'])): ?>
        <script src="speech.js"></script>
        <script src="merge.js"></script>
        <div class="container">
            <div class="room-info">
                <h2>Room: <?php echo htmlspecialchars($_SESSION['roomId']); ?></h2>
                <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
            </div>
            <form class="logout-form" method="post">
                <button type="submit" name="logout" class="btn btn-danger">Leave Room</button>
            </form>
            <div class="connection-info">
                <h3>Connection Setup</h3>
                <div class="room-controls">
                    <input type="hidden" id="roomId" value="<?php echo htmlspecialchars($_SESSION['roomId']); ?>">
                    <button id="createRoom" class="btn">Create Connection</button>
                    <button id="joinRoom" class="btn">Join Connection</button>
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
    <?php else: ?>
        <div class="container">
            <div class="auth-container">
                <h3>Create or Join a Room</h3>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <form method="post" class="auth-form">
                    <input type="text" name="username" placeholder="Username" required autocomplete="username">
                    <input type="password" name="password" placeholder="Password" required  autocomplete="current-password">
                    <input type="text" name="roomId" id="roomIdInput" placeholder="Room ID" required>
                    <button type="button" id="generateRoomBtn" class="btn">Generate Room ID</button>
                    <div class="auth-buttons">
                        <button type="submit" name="action" value="create" class="btn">Create Room</button>
                        <button type="submit" name="action" value="join" class="btn">Join Room</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const generateRoomBtn = document.getElementById('generateRoomBtn');
            const roomIdInput = document.getElementById('roomIdInput');
            if (generateRoomBtn && roomIdInput) {
                generateRoomBtn.addEventListener('click', () => {
                    const roomId = Array.from({ length: 12 }, () => 
                        "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789".charAt(
                            Math.floor(Math.random() * 62)
                        )
                    ).join('');
                    roomIdInput.value = roomId;
                });
            }
        });
    </script>
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
            discBtn = $('disconnectBtn'),
            roomInput = $('roomId'),
            connStat = $('connectionStatus'),
            peerStat = $('peerStatus'),
            config = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }] };
            let localStream, screenStream, pc, roomId, isCreator = false, pollAnswer, pollOffer, activeStream = 'camera', isInActiveRoom = false;
            if (roomInput) {
                roomId = roomInput.value;
            }
            const updateStatus = msg => { 
                if (connStat) {
                    connStat.textContent = 'Status: ' + msg; 
                }
                console.log(msg); 
            },
            updatePeer = msg => {
                if (peerStat) {
                    peerStat.textContent = msg;
                }
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
                    updateStatus('Camera started. You can now create or join a connection.');
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
                updateStatus('P2P Connection established');
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
                if(!roomId) return updateStatus('Please enter or generate a room ID.');
                isCreator = true; initPC();
                isInActiveRoom = true;
                updateStatus('Connection initiated! Creating offer...');
                createBtn.disabled = joinBtn.disabled = true; discBtn.disabled = false;
                createOffer();
                pollAnswer = pollSDP('answer', async data => {
                    updateStatus('Answer received. Establishing connection...');
                    await pc.setRemoteDescription(new RTCSessionDescription(data));
                });
            },
            joinRoom = () => {
                if(!localStream && !screenStream) return updateStatus('Please start your camera or share your screen first.');
                if(!roomId) return updateStatus('Please enter a room ID to join.');
                isCreator = false; initPC();
                isInActiveRoom = true;
                updateStatus('Joining connection. Waiting for offer...');
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
                isInActiveRoom = false;
                updateStatus('Disconnected from peer.');
                updatePeer('Waiting for peer to connect...');
            };
            window.addEventListener('load', () => {
                if (!startBtn || !stopBtn || !shareScreenBtn || !stopScreenBtn || !switchToCameraBtn || 
                    !createBtn || !joinBtn || !discBtn) {
                    return;
                }
                startBtn.addEventListener('click', startVideo);
                stopBtn.addEventListener('click', stopVideo);
                shareScreenBtn.addEventListener('click', startScreenShare);
                stopScreenBtn.addEventListener('click', stopScreenShare);
                switchToCameraBtn.addEventListener('click', switchToCamera);
                createBtn.addEventListener('click', createRoom);
                joinBtn.addEventListener('click', joinRoom);
                discBtn.addEventListener('click', disconnect);
                const localVideoContainer = document.querySelector('.video-container:nth-child(1)');
                const remoteVideoContainer = document.querySelector('.video-container:nth-child(2)');
                if (localVideoContainer && remoteVideoContainer) {
                    const createExpandBtn = (container) => {
                        const expandBtn = document.createElement('button');
                        expandBtn.className = 'expand-btn';
                        const expandIcon = document.createElement('span');
                        expandIcon.className = 'expand-icon';
                        expandBtn.appendChild(expandIcon);
                        expandBtn.addEventListener('click', () => {
                            const isExpanded = container.classList.contains('expanded');
                            document.querySelectorAll('.video-container').forEach(cont => {
                                cont.classList.remove('expanded');
                                const btn = cont.querySelector('.expand-btn');
                                if (btn) {
                                    btn.innerHTML = '';
                                    const icon = document.createElement('span');
                                    icon.className = 'expand-icon';
                                    btn.appendChild(icon);
                                }
                            });
                            if (!isExpanded) {
                                container.classList.add('expanded');
                                expandBtn.innerHTML = '';
                                const retractIcon = document.createElement('span');
                                retractIcon.className = 'retract-icon';
                                expandBtn.appendChild(retractIcon);
                            }
                        });
                        container.appendChild(expandBtn);
                    };
                    createExpandBtn(localVideoContainer);
                    createExpandBtn(remoteVideoContainer);
                }
            });
            window.addEventListener('beforeunload', (e) => {
                if (isInActiveRoom) {
                    e.preventDefault();
                    e.returnValue = 'You are currently in an active video call. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
        })();
    </script>
</body>
</html>