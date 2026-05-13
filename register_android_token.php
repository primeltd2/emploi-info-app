<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/android_notification_helpers.php";

function respond($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST;
if (!is_array($input)) {
    ei_android_log("token_register_failed", ["reason" => "invalid_payload", "content_type" => $_SERVER["CONTENT_TYPE"] ?? ""]);
    respond(["status" => "error", "msg" => "JSON ou formulaire invalide"], 400);
}

$token = trim((string)($input["token"] ?? $input["fcm_token"] ?? $input["device_token"] ?? ""));
if ($token === "" || strlen($token) < 40) {
    ei_android_log("token_register_failed", ["reason" => "missing_token", "keys" => array_keys($input)]);
    respond(["status" => "error", "msg" => "Token Android manquant"], 400);
}

$file = __DIR__ . "/android_tokens.json";
$tokens = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
$tokens = is_array($tokens) ? $tokens : [];

$found = false;
foreach ($tokens as &$row) {
    if (($row["token"] ?? "") === $token) {
        $row["updated_at"] = date("c");
        $row["platform"] = "android";
        $row["app_version"] = trim((string)($input["app_version"] ?? $row["app_version"] ?? ""));
        $row["device"] = trim((string)($input["device"] ?? $row["device"] ?? ""));
        $found = true;
        break;
    }
}
unset($row);

if (!$found) {
    $tokens[] = [
        "token" => $token,
        "platform" => "android",
        "app_version" => trim((string)($input["app_version"] ?? "")),
        "device" => trim((string)($input["device"] ?? "")),
        "created_at" => date("c"),
        "updated_at" => date("c")
    ];
}

$json = json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($file, $json, LOCK_EX) === false) {
    ei_android_log("token_register_failed", ["reason" => "write_failed"]);
    respond(["status" => "error", "msg" => "Impossible d'enregistrer le token"], 500);
}

ei_android_log("token_registered", ["count" => count($tokens), "token_start" => substr($token, 0, 12)]);
respond(["status" => "success", "count" => count($tokens)]);
?>
