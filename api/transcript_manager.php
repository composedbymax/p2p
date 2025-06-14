<?php
session_start();
header('Content-Type: application/json');
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || !isset($data['action'])) {
    echo json_encode(['error' => 'Invalid input. Action parameter is required.']);
    exit;
}
if (!isset($_SESSION['roomId'])) {
    echo json_encode(['error' => 'Room ID is not available in session.']);
    exit;
}
$roomId = preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['roomId']);
$dir = __DIR__ . '/conversations';
$filename = $dir . '/' . $roomId . '.json';
switch ($data['action']) {
    case 'create':
        handleCreateEndpoint($data, $dir, $filename);
        break;
    case 'delete':
        handleDeleteEndpoint($filename);
        break;
    default:
        echo json_encode(['error' => 'Invalid action. Supported actions: create, delete']);
        exit;
}
function handleCreateEndpoint($data, $dir, $filename) {
    if (!isset($data['transcripts']) || !is_array($data['transcripts'])) {
        echo json_encode(['error' => 'Invalid transcripts data.']);
        return;
    }
    $transcripts = $data['transcripts'];
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true)) {
            echo json_encode(['error' => 'Failed to create conversations directory.']);
            return;
        }
    }
    if (file_put_contents($filename, json_encode($transcripts, JSON_PRETTY_PRINT)) === false) {
        echo json_encode(['error' => 'Failed to save the transcript.']);
        return;
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $url = $protocol . '://' . $host . $scriptDir . '/conversations/' . basename($filename);
    echo json_encode(['url' => $url]);
}
function handleDeleteEndpoint($filename) {
    if (!file_exists($filename)) {
        echo json_encode(['error' => 'Conversation endpoint does not exist.']);
        return;
    }
    if (unlink($filename)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to delete the transcript.']);
    }
}
?>