<?php
session_start();
define('ROOMS_FILE','rooms.json');
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
  http_response_code(405);
  echo json_encode(['status'=>'error','message'=>'Invalid method']);
  exit;
}
$id = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['roomId'] ?? '');
if (!$id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'ID required']); exit; }
$rooms = json_decode(file_get_contents(ROOMS_FILE), true);
$found = false;
$rooms = array_filter($rooms, function($r) use ($id, &$found) {
  if ($r['id'] === $id) { $found = true; return false; }
  return true;
});
if (!$found) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Not found']); exit; }
file_put_contents(ROOMS_FILE, json_encode(array_values($rooms)));
if (isset($_SESSION['roomId']) && $_SESSION['roomId'] === $id) {
  session_unset(); session_destroy();
}
echo json_encode(['status'=>'deleted']);
?>