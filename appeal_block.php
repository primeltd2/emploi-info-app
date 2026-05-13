<?php
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) $input = [];

function clean_text($value, $max = 1200) {
    $value = trim((string)$value);
    $value = strip_tags($value);
    if (mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
    return $value;
}

$visitorId = clean_text($input["visitor_id"] ?? "", 180);
$message = clean_text($input["message"] ?? "");
if (!$visitorId || !$message) {
    echo json_encode(["status" => "error", "message" => "Message requis"], JSON_UNESCAPED_UNICODE);
    exit;
}

$appeal = [
    "id" => uniqid("appeal_"),
    "visitor_hash" => hash("sha256", $visitorId),
    "ip_hash" => hash("sha256", $_SERVER["REMOTE_ADDR"] ?? ""),
    "message" => $message,
    "status" => "new",
    "date" => date("c")
];

$file = __DIR__ . "/block_appeals.json";
$appeals = [];
if (file_exists($file)) {
    $decoded = json_decode(file_get_contents($file), true);
    $appeals = is_array($decoded) ? $decoded : [];
}
array_unshift($appeals, $appeal);
file_put_contents($file, json_encode($appeals, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

echo json_encode(["status" => "success", "message" => "Demande envoyée. L'administration l'examinera."], JSON_UNESCAPED_UNICODE);
?>
