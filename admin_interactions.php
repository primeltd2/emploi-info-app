<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["admin_role"]) || !in_array($_SESSION["admin_role"], ["admin", "super"], true)) {
    echo json_encode(["status" => "error", "message" => "Non autorisé"], JSON_UNESCAPED_UNICODE);
    exit;
}

$interactionsFile = __DIR__ . "/interactions.json";
$settingsFile = __DIR__ . "/moderation_settings.json";
$reportsFile = __DIR__ . "/reports.json";
$blocksFile = __DIR__ . "/blocked_visitors.json";
$appealsFile = __DIR__ . "/block_appeals.json";
$aliasesFile = __DIR__ . "/admin_aliases.json";

function read_json($file, $fallback) {
    if (!file_exists($file)) return $fallback;
    $decoded = json_decode(file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function default_auto_keywords() {
    return [
        "injure", "insulte", "harcelement", "harcèlement", "haine", "menace", "violence",
        "idiot", "imbécile", "imbecile", "stupide", "nul", "débile", "debile",
        "con", "connard", "connasse", "pute", "salope", "batard", "bâtard", "merde", "fuck",
        "ferme ta gueule", "ta gueule", "va mourir", "meurs", "suicide",
        "sexe", "sexuel", "sexuelle", "porno", "pornographie", "xxx", "nu", "nude",
        "sexy", "escort", "prostitution", "viol", "violer",
        "arnaque", "escroc", "fraude", "promets", "promesse", "gain rapide",
        "argent facile", "investissement garanti", "pari", "casino", "spam",
        "raciste", "discrimination", "humilier", "humiliation"
    ];
}

function default_moderation_settings() {
    return [
        "auto_moderation_enabled" => true,
        "blocked_keywords" => [],
        "suspect_keywords" => [],
        "auto_keywords" => default_auto_keywords(),
        "repeat_limit" => 2
    ];
}

function read_moderation_settings($file) {
    $settings = read_json($file, default_moderation_settings());
    $settings = array_merge(default_moderation_settings(), $settings);
    if (empty($settings["auto_keywords"]) || !is_array($settings["auto_keywords"])) {
        $settings["auto_keywords"] = default_auto_keywords();
    }
    return $settings;
}

function write_json($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

function clean_text($value, $max = 1600) {
    $value = trim((string)$value);
    $value = strip_tags($value);
    if (mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
    return $value;
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function flatten_comments($data) {
    $rows = [];
    foreach ($data as $key => $item) {
        [$type, $id] = array_pad(explode(":", $key, 2), 2, "");
        foreach (($item["comments"] ?? []) as $comment) {
            $comment["item_key"] = $key;
            $comment["item_type"] = $type;
            $comment["item_id"] = $id;
            $comment["status"] = $comment["status"] ?? "approved";
            $rows[] = $comment;
        }
    }
    usort($rows, fn($a, $b) => strtotime($b["date"] ?? "now") <=> strtotime($a["date"] ?? "now"));
    return $rows;
}

function admin_public_alias($username, $file) {
    $aliases = read_json($file, []);
    if (!isset($aliases[$username])) {
        $aliases[$username] = "assistant-" . (count($aliases) + 1);
        write_json($file, $aliases);
    }
    return $aliases[$username];
}

function update_comment(&$data, $commentId, $callback) {
    foreach ($data as $key => &$item) {
        foreach (($item["comments"] ?? []) as $i => &$comment) {
            if (($comment["id"] ?? "") === $commentId) {
                $callback($item, $comment, $i, $key);
                return true;
            }
        }
    }
    return false;
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$action = $_GET["action"] ?? ($_POST["action"] ?? "");

if ($method === "GET") {
    if ($action === "list") {
        respond([
            "status" => "success",
            "comments" => flatten_comments(read_json($interactionsFile, [])),
            "reports" => read_json($reportsFile, []),
            "blocks" => read_json($blocksFile, []),
            "appeals" => read_json($appealsFile, []),
            "settings" => read_moderation_settings($settingsFile)
        ]);
    }
    respond(["status" => "error", "message" => "Action inconnue"], 400);
}

if (str_starts_with($_SERVER["CONTENT_TYPE"] ?? "", "application/json")) {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!is_array($input)) $input = [];
    $action = $input["action"] ?? $action;
} else {
    $input = $_POST;
}

$data = read_json($interactionsFile, []);

if ($action === "approve" || $action === "reject" || $action === "delete") {
    $commentId = clean_text($input["comment_id"] ?? "", 100);
    if (!$commentId) respond(["status" => "error", "message" => "Commentaire manquant"], 400);
    $found = update_comment($data, $commentId, function (&$item, &$comment, $i) use ($action) {
        if ($action === "approve") $comment["status"] = "approved";
        if ($action === "reject") $comment["status"] = "rejected";
        if ($action === "delete") array_splice($item["comments"], $i, 1);
    });
    if (!$found) respond(["status" => "error", "message" => "Commentaire introuvable"], 404);
    write_json($interactionsFile, $data);
    respond(["status" => "success"]);
}

if ($action === "reply") {
    $itemType = clean_text($input["item_type"] ?? "", 40);
    $itemId = clean_text($input["item_id"] ?? "", 80);
    $parentId = clean_text($input["parent_id"] ?? "", 100);
    $text = clean_text($input["text"] ?? "", 1600);
    $link = clean_text($input["link"] ?? "", 300);
    $tagType = clean_text($input["tag_type"] ?? "", 40);
    $tagId = clean_text($input["tag_id"] ?? "", 80);
    $tagTitle = clean_text($input["tag_title"] ?? "", 140);
    if (!$itemType || !$itemId || !$text) respond(["status" => "error", "message" => "Réponse incomplète"], 400);

    $mediaPath = "";
    $mediaType = "";
    if (!empty($_FILES["media"]["tmp_name"])) {
        $ext = strtolower(pathinfo($_FILES["media"]["name"], PATHINFO_EXTENSION));
        $allowedImages = ["jpg","jpeg","png","gif","webp"];
        $allowedVideos = ["mp4","webm","ogg","mov","m4v"];
        if (!in_array($ext, array_merge($allowedImages, $allowedVideos), true)) {
            respond(["status" => "error", "message" => "Fichier non autorisé"], 400);
        }
        $dir = __DIR__ . "/uploads/admin-reponses/";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = "reply_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        if (move_uploaded_file($_FILES["media"]["tmp_name"], $dir . $name)) {
            $mediaPath = "uploads/admin-reponses/" . $name;
            $mediaType = in_array($ext, $allowedVideos, true) ? "video" : "image";
        }
    }

    $key = $itemType . ":" . $itemId;
    if (!isset($data[$key])) $data[$key] = ["reactions" => [], "comments" => []];
    $data[$key]["comments"][] = [
        "id" => uniqid("adm_"),
        "parent_id" => $parentId,
        "reply_to" => clean_text($input["reply_to"] ?? "", 40),
        "username" => $publicAlias,
        "admin_username" => $adminUsername,
        "public_username" => $publicAlias,
        "role" => "admin",
        "text" => $text,
        "status" => "approved",
        "link" => $link,
        "media" => $mediaPath,
        "media_type" => $mediaType,
        "tag" => $tagId ? ["type" => $tagType ?: "annonce", "id" => $tagId, "title" => $tagTitle ?: $tagId] : null,
        "date" => date("c")
    ];
    write_json($interactionsFile, $data);
    respond(["status" => "success"]);
}

if ($action === "block_visitor") {
    $visitorHash = clean_text($input["visitor_hash"] ?? "", 120);
    $ipHash = clean_text($input["ip_hash"] ?? "", 120);
    if (!$visitorHash && !$ipHash) respond(["status" => "error", "message" => "Visiteur introuvable"], 400);
    $blocks = read_json($blocksFile, []);
    $blocks[] = [
        "id" => uniqid("block_"),
        "visitor_hash" => $visitorHash,
        "ip_hash" => $ipHash,
        "status" => "blocked",
        "reason" => clean_text($input["reason"] ?? "Non-respect des conditions d'utilisation", 300),
        "created_by" => $_SESSION["admin_username"] ?? "Admin",
        "date" => date("c")
    ];
    write_json($blocksFile, $blocks);
    respond(["status" => "success"]);
}

if ($action === "unblock_visitor") {
    $blockId = clean_text($input["block_id"] ?? "", 100);
    $blocks = read_json($blocksFile, []);
    foreach ($blocks as &$block) {
        if (($block["id"] ?? "") === $blockId) $block["status"] = "unblocked";
    }
    write_json($blocksFile, $blocks);
    respond(["status" => "success"]);
}

if ($action === "appeal_status") {
    $appealId = clean_text($input["appeal_id"] ?? "", 100);
    $status = clean_text($input["status"] ?? "reviewed", 40);
    $appeals = read_json($appealsFile, []);
    foreach ($appeals as &$appeal) {
        if (($appeal["id"] ?? "") === $appealId) {
            $appeal["status"] = $status;
            if ($status === "approved") {
                $blocks = read_json($blocksFile, []);
                foreach ($blocks as &$block) {
                    if (
                        (($appeal["visitor_hash"] ?? "") && ($block["visitor_hash"] ?? "") === $appeal["visitor_hash"]) ||
                        (($appeal["ip_hash"] ?? "") && ($block["ip_hash"] ?? "") === $appeal["ip_hash"])
                    ) $block["status"] = "unblocked";
                }
                write_json($blocksFile, $blocks);
            }
            if ($status === "refused") {
                $blocks = read_json($blocksFile, []);
                foreach ($blocks as &$block) {
                    if (
                        (($appeal["visitor_hash"] ?? "") && ($block["visitor_hash"] ?? "") === $appeal["visitor_hash"]) ||
                        (($appeal["ip_hash"] ?? "") && ($block["ip_hash"] ?? "") === $appeal["ip_hash"])
                    ) $block["status"] = "refused";
                }
                write_json($blocksFile, $blocks);
            }
        }
    }
    write_json($appealsFile, $appeals);
    respond(["status" => "success"]);
}

if ($action === "settings") {
    $settings = [
        "auto_moderation_enabled" => filter_var($input["auto_moderation_enabled"] ?? true, FILTER_VALIDATE_BOOL),
        "blocked_keywords" => array_values(array_filter(array_map("trim", explode("\n", (string)($input["blocked_keywords"] ?? ""))))),
        "suspect_keywords" => array_values(array_filter(array_map("trim", explode("\n", (string)($input["suspect_keywords"] ?? ""))))),
        "auto_keywords" => array_values(array_filter(array_map("trim", explode("\n", (string)($input["auto_keywords"] ?? ""))))),
        "repeat_limit" => max(1, (int)($input["repeat_limit"] ?? 2))
    ];
    if (empty($settings["auto_keywords"])) $settings["auto_keywords"] = default_auto_keywords();
    write_json($settingsFile, $settings);
    respond(["status" => "success"]);
}

if ($action === "report_status") {
    $reports = read_json($reportsFile, []);
    $reportId = clean_text($input["report_id"] ?? "", 100);
    foreach ($reports as &$report) {
        if (($report["id"] ?? "") === $reportId) $report["status"] = clean_text($input["status"] ?? "read", 40);
    }
    write_json($reportsFile, $reports);
    respond(["status" => "success"]);
}

respond(["status" => "error", "message" => "Action inconnue"], 400);
?>
    $adminUsername = $_SESSION["admin_username"] ?? "Admin";
    $publicAlias = admin_public_alias($adminUsername, $aliasesFile);
