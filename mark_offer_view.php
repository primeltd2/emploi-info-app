<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/notification_helpers.php";

$id = trim((string)($_POST["id"] ?? $_GET["id"] ?? ""));
if ($id === "") {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "ID manquant"], JSON_UNESCAPED_UNICODE);
    exit;
}

$viewsFile = __DIR__ . "/notification_views.json";
$views = ei_read_json_file($viewsFile);
$views[$id] = [
    "viewed" => true,
    "last_view" => date("c")
];
ei_write_json_file($viewsFile, $views);

echo json_encode(["status" => "success"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
