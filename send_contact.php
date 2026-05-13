<?php
session_start();
header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");

$file = __DIR__ . "/contact_messages.json";

function now_iso() {
    return date("c");
}

function read_messages($file) {
    if (!file_exists($file)) {
        file_put_contents($file, "[]", LOCK_EX);
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function save_messages($file, $messages) {
    file_put_contents($file, json_encode(array_values($messages), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function cleanup_old_messages($messages) {
    $limit = time() - (30 * 24 * 60 * 60);
    return array_values(array_filter($messages, function($msg) use ($limit) {
        $date = strtotime($msg["updated_at"] ?? $msg["created_at"] ?? "now");
        return $date === false || $date >= $limit;
    }));
}

function json_response($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function public_conversation($conversation) {
    if (!isset($conversation["messages"]) || !is_array($conversation["messages"])) {
        return $conversation;
    }
    foreach ($conversation["messages"] as &$message) {
        if (($message["sender"] ?? "") === "admin") {
            $message["author"] = "Administration";
        }
    }
    unset($message);
    return $conversation;
}

function save_upload($field = "media") {
    if (empty($_FILES[$field]) || ($_FILES[$field]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($_FILES[$field]["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        json_response(["status" => "error", "message" => "Téléversement impossible"], 400);
    }
    $tmp = $_FILES[$field]["tmp_name"] ?? "";
    $browserMime = $_FILES[$field]["type"] ?? "";
    $mime = mime_content_type($tmp) ?: $browserMime;
    if ($browserMime && preg_match('/^(audio|video)\//i', $browserMime)) {
        $mime = $browserMime;
    }
    $mediaType = "image";
    if (preg_match('/^audio\//i', $mime)) {
        $mediaType = "audio";
    } elseif (preg_match('/^video\//i', $mime)) {
        $mediaType = "video";
    } else {
        $info = @getimagesize($tmp);
    }
    if ($mediaType === "image" && (!$info || !in_array($info["mime"], ["image/jpeg", "image/png", "image/webp", "image/gif"], true))) {
        json_response(["status" => "error", "message" => "Seules les images JPG, PNG, WebP ou GIF sont acceptées."], 400);
    }
    if ($mediaType === "image") {
        $mime = $info["mime"];
    }
    $maxSize = $mediaType === "image" ? 5 * 1024 * 1024 : 12 * 1024 * 1024;
    if (($_FILES[$field]["size"] ?? 0) > $maxSize) {
        json_response(["status" => "error", "message" => "Fichier trop lourd."], 400);
    }
    $ext = match ($mime) {
        "image/png" => "png",
        "image/webp" => "webp",
        "image/gif" => "gif",
        "audio/webm", "video/webm" => "webm",
        "audio/ogg" => "ogg",
        "audio/mpeg", "audio/mp3" => "mp3",
        "audio/mp4", "audio/x-m4a" => "m4a",
        "audio/wav", "audio/x-wav" => "wav",
        "video/mp4" => "mp4",
        default => "jpg"
    };
    $dir = __DIR__ . "/uploads/contact";
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = "contact_" . date("Ymd_His") . "_" . bin2hex(random_bytes(5)) . "." . $ext;
    if (!move_uploaded_file($tmp, $dir . "/" . $name)) {
        json_response(["status" => "error", "message" => "Enregistrement de l'image impossible"], 500);
    }
    return ["path" => "uploads/contact/" . $name, "type" => $mediaType];
}

function clean_text($value, $max = 4000) {
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    return function_exists("mb_substr") ? mb_substr($value, 0, $max, "UTF-8") : substr($value, 0, $max);
}

function require_admin() {
    if (empty($_SESSION["admin_logged"])) {
        json_response(["status" => "error", "message" => "Connexion administrateur requise"], 403);
    }
}

$messages = cleanup_old_messages(read_messages($file));
save_messages($file, $messages);

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$action = $_GET["action"] ?? "";

if ($method === "GET" && $action === "visitor") {
    $visitorId = clean_text($_GET["visitor_id"] ?? "", 120);
    if ($visitorId === "") json_response(["status" => "error", "message" => "Identifiant visiteur manquant"], 400);

    $items = array_values(array_filter($messages, fn($m) => ($m["visitor_id"] ?? "") === $visitorId));
    usort($items, fn($a, $b) => strcmp($b["updated_at"] ?? "", $a["updated_at"] ?? ""));
    $items = array_map("public_conversation", $items);
    json_response(["status" => "success", "messages" => $items]);
}

if ($method === "GET" && $action === "admin") {
    require_admin();
    usort($messages, fn($a, $b) => strcmp($b["updated_at"] ?? "", $a["updated_at"] ?? ""));
    json_response(["status" => "success", "messages" => $messages]);
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) $input = $_POST;
$postAction = $input["action"] ?? "";

if ($method === "POST" && $postAction === "submit") {
    $visitorId = clean_text($input["visitor_id"] ?? "", 120);
    $name = clean_text($input["name"] ?? "", 120);
    $email = clean_text($input["email"] ?? "", 180);
    $whatsapp = clean_text($input["whatsapp"] ?? "", 40);
    $subject = clean_text($input["subject"] ?? "", 160);
    $category = clean_text($input["category"] ?? "", 80);
    $text = clean_text($input["message"] ?? "", 4000);
    $upload = save_upload("media");
    $media = $upload["path"] ?? null;
    $mediaType = $upload["type"] ?? "";

    if ($visitorId === "" || $name === "" || $text === "") {
        json_response(["status" => "error", "message" => "Nom, message et identifiant visiteur requis"], 400);
    }
    if ($email === "" && $whatsapp === "") {
        json_response(["status" => "error", "message" => "Ajoutez au moins un email ou un numéro WhatsApp"], 400);
    }
    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(["status" => "error", "message" => "Adresse email invalide"], 400);
    }

    $id = "msg_" . bin2hex(random_bytes(8));
    $date = now_iso();
    $conversation = [
        "id" => $id,
        "visitor_id" => $visitorId,
        "name" => $name,
        "email" => $email,
        "whatsapp" => $whatsapp,
        "subject" => $subject !== "" ? $subject : "Demande d'assistance",
        "category" => $category !== "" ? $category : "general",
        "status" => "open",
        "created_at" => $date,
        "updated_at" => $date,
        "messages" => [[
            "id" => "reply_" . bin2hex(random_bytes(6)),
            "sender" => "visitor",
            "author" => $name,
            "text" => $text,
            "media" => $media,
            "media_type" => $mediaType,
            "created_at" => $date
        ]]
    ];

    $messages[] = $conversation;
    save_messages($file, $messages);
    json_response(["status" => "success", "message" => "Votre demande a été envoyée à l'administration.", "conversation" => $conversation]);
}

if ($method === "POST" && $postAction === "reply") {
    require_admin();
    $id = clean_text($input["id"] ?? "", 80);
    $text = clean_text($input["message"] ?? "", 4000);
    $upload = save_upload("media");
    $media = $upload["path"] ?? null;
    $mediaType = $upload["type"] ?? "";
    if ($id === "" || ($text === "" && !$media)) json_response(["status" => "error", "message" => "Conversation et réponse ou image requise"], 400);

    foreach ($messages as &$msg) {
        if (($msg["id"] ?? "") === $id) {
            $date = now_iso();
            $msg["messages"][] = [
                "id" => "reply_" . bin2hex(random_bytes(6)),
                "sender" => "admin",
                "author" => "Administration",
                "text" => $text,
                "media" => $media,
                "media_type" => $mediaType,
                "created_at" => $date
            ];
            $msg["status"] = "answered";
            $msg["updated_at"] = $date;
            save_messages($file, $messages);
            json_response(["status" => "success", "message" => "Réponse enregistrée", "conversation" => $msg]);
        }
    }
    unset($msg);
    json_response(["status" => "error", "message" => "Conversation introuvable"], 404);
}

if ($method === "POST" && $postAction === "delete") {
    require_admin();
    $id = clean_text($input["id"] ?? "", 80);
    $before = count($messages);
    $messages = array_values(array_filter($messages, fn($m) => ($m["id"] ?? "") !== $id));
    save_messages($file, $messages);
    json_response(["status" => "success", "deleted" => $before - count($messages)]);
}

if ($method === "POST" && $postAction === "close") {
    require_admin();
    $id = clean_text($input["id"] ?? "", 80);
    foreach ($messages as &$msg) {
        if (($msg["id"] ?? "") === $id) {
            $msg["status"] = "closed";
            $msg["updated_at"] = now_iso();
            save_messages($file, $messages);
            json_response(["status" => "success", "conversation" => $msg]);
        }
    }
    unset($msg);
    json_response(["status" => "error", "message" => "Conversation introuvable"], 404);
}

json_response(["status" => "error", "message" => "Action non reconnue"], 400);
