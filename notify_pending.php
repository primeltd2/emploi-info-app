<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/notification_helpers.php";
require_once __DIR__ . "/android_notification_helpers.php";

echo json_encode([
    "status" => "disabled",
    "sent" => [],
    "msg" => "Les rappels automatiques sont désactivés pour garantir une seule notification par annonce."
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
