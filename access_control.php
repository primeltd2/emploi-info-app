<?php
header("Content-Type: application/json; charset=utf-8");

$visitorId = trim((string)($_GET["visitor_id"] ?? ""));
$visitorHash = $visitorId ? hash("sha256", $visitorId) : "";
$ipHash = hash("sha256", $_SERVER["REMOTE_ADDR"] ?? "");
$file = __DIR__ . "/blocked_visitors.json";

$blocks = [];
if (file_exists($file)) {
    $decoded = json_decode(file_get_contents($file), true);
    $blocks = is_array($decoded) ? $decoded : [];
}

foreach ($blocks as $block) {
    $matchVisitor = $visitorHash && ($block["visitor_hash"] ?? "") === $visitorHash;
    $matchIp = $ipHash && ($block["ip_hash"] ?? "") === $ipHash;
    if (($matchVisitor || $matchIp) && ($block["status"] ?? "blocked") !== "unblocked") {
        $status = $block["status"] ?? "blocked";
        echo json_encode([
            "blocked" => true,
            "status" => $status,
            "message" => $status === "refused"
                ? "Accès non autorisé."
                : "Accès refusé. Veuillez contacter l'administration.",
            "reason" => $block["reason"] ?? ""
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

echo json_encode(["blocked" => false], JSON_UNESCAPED_UNICODE);
?>
