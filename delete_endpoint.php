<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['roomId'])) {
    echo json_encode(['error' => 'Room ID is not available in session.']);
    exit;
}
$roomId = preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['roomId']);
$dir = __DIR__ . '/conversations';
$filename = $dir . '/conversation_' . $roomId . '.json';
if (!file_exists($filename)) {
    echo json_encode(['error' => 'Conversation endpoint does not exist.']);
    exit;
}
if (unlink($filename)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to delete the transcript.']);
}
?>