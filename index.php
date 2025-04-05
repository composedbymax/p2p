<?php require 'p2p.php' ?>
<?php require 'nonce.php' ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>P2P - MAX</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <meta name="description" content="P2P connections">
    <meta name="author" content="MAX W">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Content-Security-Policy" content="script-src 'self' 'nonce-<?php echo $_SESSION['nonce']; ?>';">
    <meta http-equiv="Strict-Transport-Security" content="max-age=31536000; includeSubDomains; preload">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta http-equiv="Permissions-Policy" content="geolocation=(), microphone=(self), camera=(self)">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="root.css">
    <link rel="stylesheet" href="slider.css">
</head>
<body>
<?php
$error = $_SESSION['flash']['error'];
$success = $_SESSION['flash']['success'];
$_SESSION['flash'] = ['error' => null, 'success' => null];
?>
<?php if (isset($_SESSION['roomId'])): ?>
<script src="speech.js" nonce="<?php echo $_SESSION['nonce']; ?>" defer></script>
<script src="merge.js" nonce="<?php echo $_SESSION['nonce']; ?>" defer></script>
<div class="container">
    <div class="room-info">
        <h2>Room: <?php echo htmlspecialchars($_SESSION['roomId']); ?></h2>
    </div>
    <form class="logout-form" method="post">
        <button type="submit" name="logout" class="btn btn-danger">Leave Room</button>
    </form>
    <div class="connection-info">
        <h2>Connection Setup</h2>
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
                <button id="stopSharing" class="btn" disabled>Stop Sharing</button>
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
<script src="app.js" nonce="<?php echo $_SESSION['nonce']; ?>"></script>
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
            <input type="text" name="roomId" id="roomIdInput" placeholder="Room ID" required autocomplete="username">
            <button type="button" id="generateRoomBtn" class="btn">Generate Room ID</button>
            <input type="password" name="password" placeholder="Room Password" required autocomplete="current-password">
            <div class="auth-buttons">
                <button type="submit" name="action" value="create" class="btn">Create Room</button>
                <button type="submit" name="action" value="join" class="btn">Join Room</button>
            </div>
        </form>
    </div>
</div>
<script src="floor.js" nonce="<?php echo $_SESSION['nonce']; ?>"></script>
<?php endif; ?>
</body>
</html>