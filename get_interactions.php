<?php
header("Content-Type: application/json; charset=utf-8");

$type = preg_replace('/[^a-z0-9_-]/i', '', $_GET['type'] ?? '');
$id = preg_replace('/[^a-z0-9_-]/i', '', $_GET['id'] ?? '');
$visitorId = $_GET['visitor_id'] ?? ($_COOKIE['visitor'] ?? '');
$file = __DIR__ . "/interactions.json";

function empty_item() {
    return [
        "reactions" => new stdClass(),
        "comments" => [],
        "user_reaction" => ""
    ];
}

function public_comments($comments) {
    $safe = [];
    foreach ($comments as $comment) {
        if (isset($comment["status"]) && $comment["status"] !== "approved") continue;
        unset($comment["visitor_hash"], $comment["ip_hash"]);
        if (($comment["role"] ?? "") === "admin") {
            $comment["username"] = ($comment["public_username"] ?? "")
                ? (preg_match('/^assistant-\d+$/', $comment["username"] ?? "") ? $comment["username"] : "assistant")
                : "assistant";
        }
        $safe[] = $comment;
    }
    return array_values($safe);
}

if (!$type || !$id) {
    echo json_encode(empty_item(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$data = [];
if (file_exists($file)) {
    $decoded = json_decode(file_get_contents($file), true);
    $data = is_array($decoded) ? $decoded : [];
}

$key = $type . ":" . $id;
$item = $data[$key] ?? ["reactions" => [], "comments" => [], "visitors" => []];
$comments = public_comments($item["comments"] ?? []);
$counts = [];
foreach (($item["reactions"] ?? []) as $reaction) {
    $emoji = $reaction["reaction"] ?? "";
    if ($emoji) $counts[$emoji] = ($counts[$emoji] ?? 0) + 1;
}

$visitorHash = $visitorId ? hash("sha256", $visitorId) : "";
$userReaction = "";
if ($visitorHash) {
    foreach (($item["reactions"] ?? []) as $reaction) {
        if (($reaction["visitor_hash"] ?? "") === $visitorHash) {
            $userReaction = $reaction["reaction"] ?? "";
            break;
        }
    }
}

echo json_encode([
    "reactions" => (object)$counts,
    "comments" => $comments,
    "user_reaction" => $userReaction
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
