<?php
session_start();
header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");

if (empty($_SESSION["admin_logged"])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Admin requis"], JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

$apps = read_json(__DIR__ . "/applications.json");

echo json_encode([
    "status" => "success",
    "users" => [],
    "applications" => $apps,
    "stats" => [
        "users" => 0,
        "applications" => count($apps),
        "alerts_enabled" => 0,
        "favorites" => 0
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
