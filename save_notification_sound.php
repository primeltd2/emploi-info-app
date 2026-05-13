<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["admin_role"]) || !in_array($_SESSION["admin_role"], ["admin", "super"], true)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "msg" => "Non autorise"], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_FILES["sound"]) || ($_FILES["sound"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Son manquant"], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_FILES["sound"]["size"] ?? 0) > 3 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Son trop lourd, maximum 3 Mo"], JSON_UNESCAPED_UNICODE);
    exit;
}

$ext = strtolower(pathinfo($_FILES["sound"]["name"], PATHINFO_EXTENSION));
$allowedExt = ["mp3", "wav", "ogg", "m4a"];
if (!in_array($ext, $allowedExt, true)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Format autorise : MP3, WAV, OGG, M4A"], JSON_UNESCAPED_UNICODE);
    exit;
}

$dir = __DIR__ . "/uploads/notifications/";
if (!is_dir($dir)) mkdir($dir, 0755, true);
$targetName = "notification_sound." . $ext;
$target = $dir . $targetName;

if (!move_uploaded_file($_FILES["sound"]["tmp_name"], $target)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "Televersement impossible"], JSON_UNESCAPED_UNICODE);
    exit;
}

$paramsFile = __DIR__ . "/params.json";
$params = file_exists($paramsFile) ? json_decode(file_get_contents($paramsFile), true) : [];
$params = is_array($params) ? $params : [];
$params["notification_sound"] = "uploads/notifications/" . $targetName;
$params["notification_sound_updated_at"] = time();
file_put_contents($paramsFile, json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

echo json_encode(["status" => "success", "path" => $params["notification_sound"]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
