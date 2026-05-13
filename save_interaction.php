<?php
header("Content-Type: application/json; charset=utf-8");

$file = __DIR__ . "/interactions.json";
$settingsFile = __DIR__ . "/moderation_settings.json";
$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) $input = [];

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_text($value, $max = 1200) {
    $value = trim((string)$value);
    $value = strip_tags($value);
    if (mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
    return $value;
}

function public_item($item, $visitorHash) {
    $counts = [];
    foreach (($item["reactions"] ?? []) as $reaction) {
        $emoji = $reaction["reaction"] ?? "";
        if ($emoji) $counts[$emoji] = ($counts[$emoji] ?? 0) + 1;
    }

    $userReaction = "";
    foreach (($item["reactions"] ?? []) as $reaction) {
        if (($reaction["visitor_hash"] ?? "") === $visitorHash) {
            $userReaction = $reaction["reaction"] ?? "";
            break;
        }
    }

    return [
        "reactions" => (object)$counts,
        "comments" => public_comments($item["comments"] ?? []),
        "user_reaction" => $userReaction
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

function load_moderation_settings($file) {
    $autoKeywords = default_auto_keywords();
    $defaults = [
        "auto_moderation_enabled" => true,
        "blocked_keywords" => [],
        "suspect_keywords" => [],
        "auto_keywords" => $autoKeywords,
        "repeat_limit" => 2
    ];
    if (!file_exists($file)) return $defaults;
    $decoded = json_decode(file_get_contents($file), true);
    if (!is_array($decoded)) return $defaults;
    $settings = array_merge($defaults, $decoded);
    if (empty($settings["auto_keywords"]) || !is_array($settings["auto_keywords"])) {
        $settings["auto_keywords"] = $autoKeywords;
    }
    if (!array_key_exists("auto_moderation_enabled", $settings)) {
        $settings["auto_moderation_enabled"] = true;
    }
    return $settings;
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

function normalize_keyword_text($text) {
    $text = mb_strtolower((string)$text);
    $converted = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $text);
    if ($converted !== false) $text = $converted;
    $text = preg_replace('/[^a-z0-9]+/i', ' ', $text);
    return trim(preg_replace('/\s+/', ' ', $text));
}

function contains_keyword($text, $keywords) {
    $normalized = " " . normalize_keyword_text($text) . " ";
    foreach ($keywords as $keyword) {
        $keyword = normalize_keyword_text($keyword);
        if ($keyword !== "" && strpos($normalized, " " . $keyword . " ") !== false) return true;
    }
    return false;
}

$type = preg_replace('/[^a-z0-9_-]/i', '', $input["item_type"] ?? "");
$id = preg_replace('/[^a-z0-9_-]/i', '', $input["item_id"] ?? "");
$action = $input["action"] ?? "";
$visitorId = clean_text($input["visitor_id"] ?? "", 180);
$username = clean_text($input["username"] ?? "Visiteur", 40);

if (!$type || !$id || !$visitorId || !$username) {
    respond(["status" => "error", "message" => "Paramètres manquants"], 400);
}

$visitorHash = hash("sha256", $visitorId);
$ipHash = hash("sha256", $_SERVER["REMOTE_ADDR"] ?? "");
$data = [];
if (file_exists($file)) {
    $decoded = json_decode(file_get_contents($file), true);
    $data = is_array($decoded) ? $decoded : [];
}

$key = $type . ":" . $id;
if (!isset($data[$key])) {
    $data[$key] = ["reactions" => [], "comments" => []];
}

if ($action === "reaction") {
    $reaction = clean_text($input["reaction"] ?? "", 8);
    $allowed = ["👍", "❤️", "🎉", "🔥", "🙏", "💡"];
    if (!in_array($reaction, $allowed, true)) {
        respond(["status" => "error", "message" => "Réaction invalide"], 400);
    }

    $updated = false;
    foreach ($data[$key]["reactions"] as &$existing) {
        if (($existing["visitor_hash"] ?? "") === $visitorHash) {
            $existing["reaction"] = $reaction;
            $existing["username"] = $username;
            $existing["date"] = date("c");
            $updated = true;
            break;
        }
    }
    unset($existing);

    if (!$updated) {
        $data[$key]["reactions"][] = [
            "visitor_hash" => $visitorHash,
            "username" => $username,
            "reaction" => $reaction,
            "date" => date("c")
        ];
    }
} elseif ($action === "comment") {
    $text = clean_text($input["text"] ?? "");
    if ($text === "") respond(["status" => "error", "message" => "Commentaire vide"], 400);
    $settings = load_moderation_settings($settingsFile);
    $autoModerationEnabled = filter_var($settings["auto_moderation_enabled"] ?? true, FILTER_VALIDATE_BOOL);
    $blocked = contains_keyword($text, $settings["blocked_keywords"] ?? []);
    $suspectKeywords = $settings["suspect_keywords"] ?? [];
    if ($autoModerationEnabled) {
        $suspectKeywords = array_merge($suspectKeywords, $settings["auto_keywords"] ?? default_auto_keywords());
    }
    $suspect = contains_keyword($text, $suspectKeywords);

    $visitorPendingCount = 0;
    foreach (($data[$key]["comments"] ?? []) as $comment) {
        if (($comment["visitor_hash"] ?? "") === $visitorHash && ($comment["status"] ?? "approved") !== "approved") {
            $visitorPendingCount++;
        }
    }

    if ($blocked && $visitorPendingCount < (int)($settings["repeat_limit"] ?? 2)) {
        respond([
            "status" => "error",
            "message" => "Votre commentaire contient des mots non autorisés."
        ], 400);
    }

    $status = ($blocked || $suspect || $visitorPendingCount >= (int)($settings["repeat_limit"] ?? 2)) ? "pending" : "approved";

    $data[$key]["comments"][] = [
        "id" => uniqid("com_"),
        "parent_id" => clean_text($input["parent_id"] ?? "", 80),
        "reply_to" => clean_text($input["reply_to"] ?? "", 40),
        "username" => $username,
        "visitor_hash" => $visitorHash,
        "ip_hash" => $ipHash,
        "text" => $text,
        "status" => $status,
        "reason" => $status === "pending" ? "Commentaire à vérifier" : "",
        "date" => date("c")
    ];
} else {
    respond(["status" => "error", "message" => "Action inconnue"], 400);
}

if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
    respond(["status" => "error", "message" => "Impossible d'enregistrer"], 500);
}

respond([
    "status" => "success",
    "message" => isset($status) && $status === "pending" ? "Commentaire reçu. Il sera visible après validation." : "Commentaire publié.",
    "data" => public_item($data[$key], $visitorHash)
]);
?>
