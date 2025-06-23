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
    $filename = "signal_{$role}_{$roomId}.dat";
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'DELETE':
            if (file_exists($filename)) {
                $deleted = unlink($filename);
                echo json_encode(['status' => $deleted ? 'deleted' : 'error deleting']);
            } else {
                echo json_encode(['status' => 'file not found']);
            }
            break;
        case 'POST':
            $encryptedData = file_get_contents("php://input");
            if (empty($encryptedData)) {
                echo json_encode(['status' => 'error', 'message' => 'No data received']);
                break;
            }
            $success = file_put_contents($filename, $encryptedData);
            if ($success !== false) {
                echo json_encode(['status' => 'saved', 'encrypted' => true]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save data']);
            }
            break;
        case 'GET':
            if (file_exists($filename)) {
                $encryptedData = file_get_contents($filename);
                if ($encryptedData !== false && !empty($encryptedData)) {
                    header('Content-Type: text/plain');
                    echo $encryptedData;
                } else {
                    echo json_encode(['status' => 'no data']);
                }
            } else {
                echo json_encode(['status' => 'no data']);
            }
            break;
    }
    exit;
}
?>