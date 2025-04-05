<?php
session_start();
header('Content-Type: application/json');
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || !isset($data['transcripts'])) {
    echo json_encode(['error' => 'Invalid input.']);
    exit;
}
if (!isset($_SESSION['roomId'])) {
    echo json_encode(['error' => 'Room ID is not available in session.']);
    exit;
}
$roomId = preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['roomId']);
$transcripts = $data['transcripts'];
$dir = __DIR__ . '/conversations';
$filename = $dir . '/conversation_' . $roomId . '.json';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
if (file_put_contents($filename, json_encode($transcripts, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['error' => 'Failed to save the transcript.']);
    exit;
}
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$url = $protocol . '://' . $host . $scriptDir . '/conversations/conversation_' . $roomId . '.json';
echo json_encode(['url' => $url]);