<?php
$nonce = bin2hex(random_bytes(16));
$_SESSION['nonce'] = $nonce;
header("Content-Security-Policy: script-src 'nonce-" . $_SESSION['nonce'] . "';");
?>