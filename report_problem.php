<?php
header("Content-Type: application/json; charset=utf-8");

$isJson = str_starts_with($_SERVER["CONTENT_TYPE"] ?? "", "application/json");
$input = $isJson ? json_decode(file_get_contents("php://input"), true) : $_POST;
if (!is_array($input)) $input = [];

function clean_text($value, $max = 1200) {
    $value = trim((string)$value);
    $value = strip_tags($value);
    if (mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
    return $value;
}

$message = clean_text($input["message"] ?? "");
if ($message === "") {
    echo json_encode(["status" => "error", "message" => "Message requis"], JSON_UNESCAPED_UNICODE);
    exit;
}

$screenshotPath = "";
if (!$isJson && !empty($_FILES["screenshot"]["tmp_name"])) {
    $ext = strtolower(pathinfo($_FILES["screenshot"]["name"], PATHINFO_EXTENSION));
    if (in_array($ext, ["jpg", "jpeg", "png", "gif", "webp"], true)) {
        $dir = __DIR__ . "/uploads/reports/";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = "report_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        if (move_uploaded_file($_FILES["screenshot"]["tmp_name"], $dir . $name)) {
            $screenshotPath = "uploads/reports/" . $name;
        }
    }
}

$visitorId = clean_text($input["visitor_id"] ?? "", 180);
$report = [
    "id" => uniqid("rep_"),
    "kind" => clean_text($input["kind"] ?? "problem", 40),
    "item_type" => clean_text($input["item_type"] ?? "", 40),
    "item_id" => clean_text($input["item_id"] ?? "", 80),
    "visitor_hash" => $visitorId ? hash("sha256", $visitorId) : "",
    "ip_hash" => hash("sha256", $_SERVER["REMOTE_ADDR"] ?? ""),
    "username" => clean_text($input["username"] ?? "Visiteur", 40),
    "message" => $message,
    "screenshot" => $screenshotPath,
    "page" => clean_text($input["page"] ?? "", 300),
    "status" => "new",
    "date" => date("c")
];

$file = __DIR__ . "/reports.json";
$reports = [];
if (file_exists($file)) {
    $decoded = json_decode(file_get_contents($file), true);
    $reports = is_array($decoded) ? $decoded : [];
}
array_unshift($reports, $report);
file_put_contents($file, json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

$adminEmail = "kodjaouelisee1234@gmail.com";
$subject = $report["kind"] === "suggestion" ? "[EMPLOI INFO] Suggestion visiteur" : "[EMPLOI INFO] Problème signalé";
$body = ($report["kind"] === "suggestion" ? "Une suggestion privée a été envoyée." : "Un problème privé a été signalé.") . "\n\n";
$body .= "Publication : {$report['item_type']} {$report['item_id']}\n";
$body .= "Utilisateur : {$report['username']}\n";
$body .= "Page : {$report['page']}\n\n";
if ($screenshotPath) $body .= "Capture : " . getcwd() . "/$screenshotPath\n\n";
$body .= $report["message"];
@mail($adminEmail, $subject, $body, "From: noreply@emploi-info.page.gd\r\n");

echo json_encode(["status" => "success", "message" => "Message envoyé aux administrateurs"], JSON_UNESCAPED_UNICODE);
?>
