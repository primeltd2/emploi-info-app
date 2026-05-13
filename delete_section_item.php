<?php
header("Content-Type: application/json; charset=utf-8");

$allowed = [
    "blog" => "blog.json",
    "resources" => "resources.json",
    "services" => "services.json",
    "formations" => "formations.json"
];

$input = json_decode(file_get_contents("php://input"), true);
$section = $input["section"] ?? "";
$id = (string)($input["id"] ?? "");

if (!isset($allowed[$section]) || $id === "") {
    echo json_encode(["status" => "error", "message" => "Paramètres invalides"]);
    exit;
}

$file = $allowed[$section];
$items = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($items)) $items = [];

$items = array_values(array_filter($items, function($item) use ($id) {
    return (string)($item["id"] ?? "") !== $id;
}));

file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
echo json_encode(["status" => "success"]);
?>
