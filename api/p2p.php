<?php
session_start();
define('ROOMS_FILE', 'api/rooms.json');
if (!file_exists(ROOMS_FILE)) {
    file_put_contents(ROOMS_FILE, json_encode([]));
}
function verifyRoomPassword($roomId, $password) {
    $rooms = json_decode(file_get_contents(ROOMS_FILE), true);
    foreach ($rooms as $room) {
        if ($room['id'] === $roomId && password_verify($password, $room['password'])) {
            return true;
        }
    }
    return false;
}
function handleRoom($roomId, $password, $isCreate = false) {
    $rooms = json_decode(file_get_contents(ROOMS_FILE), true);
    $roomExists = false;
    foreach ($rooms as $index => $room) {
        if (isset($room['id']) && $room['id'] === $roomId) {
            $roomExists = true;
            $rooms[$index]['lastAccessed'] = time();
            break;
        }
    }
    if ($roomExists && $isCreate) {
        return false;
    }
    if (!$roomExists && $isCreate) {
        $rooms[] = [
            'id' => $roomId,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created' => time(),
            'lastAccessed' => time(),
        ];
        $roomExists = true;
    }
    file_put_contents(ROOMS_FILE, json_encode($rooms));
    return $roomExists;
}
if (!isset($_SESSION['flash'])) {
    $_SESSION['flash'] = ['error' => null, 'success' => null];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['roomId'], $_GET['role'])) {
    $error = null;
    $success = null;
    if (isset($_POST['action'])) {
        $password = $_POST['password'] ?? '';
        $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['roomId'] ?? '');
        if (empty($password) || empty($roomId)) {
            $error = "Room ID and password are required.";
        } else {
            if ($_POST['action'] === 'create') {
                if (handleRoom($roomId, $password, true)) {
                    $_SESSION['roomId'] = $roomId;
                    $success = "Room created successfully!";
                } else {
                    $error = "Failed to create room. A room with this ID already exists.";
                }
            } elseif ($_POST['action'] === 'join') {
                if (verifyRoomPassword($roomId, $password)) {
                    if (handleRoom($roomId, $password, false)) {
                        $_SESSION['roomId'] = $roomId;
                        $success = "Joined room successfully!";
                    } else {
                        $error = "Room does not exist.";
                    }
                } else {
                    $error = "Invalid room ID or password.";
                }
            }
        }
        $_SESSION['flash']['error'] = $error;
        $_SESSION['flash']['success'] = $success;
    } elseif (isset($_POST['logout'])) {
        session_unset();
        session_destroy();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_GET['roomId'], $_GET['role']) && isset($_SESSION['roomId'])) {
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