<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/admin_guard.php";
ei_require_admin([]);

$adminsFile = __DIR__ . "/admins.json";
$groupsFile = __DIR__ . "/admin_groups.json";
$messagesFile = __DIR__ . "/admin_messages.json";
$presenceFile = __DIR__ . "/admin_presence.json";
$uploadsDir = __DIR__ . "/uploads/admin_messages";

function read_json_admin_messages($file, $fallback = []) {
    if (!file_exists($file)) return $fallback;
    $decoded = json_decode(file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function write_json_admin_messages($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

function clean_admin_messages_text($value, $max = 1200) {
    $value = trim(strip_tags((string)$value));
    return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
}

function respond_admin_messages($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_messages_can_manage_groups() {
    $role = $_SESSION["admin_role"] ?? "";
    $permissions = $_SESSION["admin_permissions"] ?? [];
    return $role === "super" || in_array("manage_admins", $permissions, true);
}

function admin_messages_can_manage_calls() {
    return admin_messages_can_manage_groups();
}

function room_link_admin_messages($id) {
    return "https://meet.jit.si/emploi-info-admin-" . preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
}

function admin_messages_upload_attachment($uploadsDir) {
    $gifUrl = clean_admin_messages_text($_POST["gif_url"] ?? "", 500);
    if ($gifUrl !== "" && preg_match('/^https?:\/\//i', $gifUrl)) {
        $kind = clean_admin_messages_text($_POST["attachment_kind"] ?? "gif", 30);
        return [
            "name" => $kind === "sticker" ? "Sticker" : "GIF",
            "url" => $gifUrl,
            "mime" => "image/gif",
            "size" => 0,
            "kind" => $kind
        ];
    }
    if (empty($_FILES["attachment"]) || ($_FILES["attachment"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($_FILES["attachment"]["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) respond_admin_messages(["status" => "error", "message" => "Fichier non reçu"], 400);
    if (($_FILES["attachment"]["size"] ?? 0) > 100 * 1024 * 1024) respond_admin_messages(["status" => "error", "message" => "Fichier trop lourd, maximum 100 Mo"], 400);
    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0775, true);
    $original = clean_admin_messages_text($_FILES["attachment"]["name"] ?? "fichier", 180);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $safeExt = preg_match('/^[a-z0-9]{1,12}$/', $ext) ? "." . $ext : "";
    $name = "adm_" . date("Ymd_His") . "_" . bin2hex(random_bytes(5)) . $safeExt;
    $target = $uploadsDir . "/" . $name;
    if (!move_uploaded_file($_FILES["attachment"]["tmp_name"], $target)) respond_admin_messages(["status" => "error", "message" => "Téléversement impossible"], 500);
    $kind = clean_admin_messages_text($_POST["attachment_kind"] ?? "", 30);
    $browserMime = $_FILES["attachment"]["type"] ?? "";
    $mime = mime_content_type($target) ?: ($browserMime ?: "application/octet-stream");
    if (($kind === "voice" || $kind === "audio") && preg_match('/^audio\//i', $browserMime)) {
        $mime = $browserMime;
    }
    if ($kind === "") {
        if (preg_match('/^image\/gif/i', $mime)) {
            $kind = "gif";
        } elseif (preg_match('/^image\//i', $mime)) {
            $kind = "image";
        } elseif (preg_match('/^video\//i', $mime)) {
            $kind = "video";
        } elseif (preg_match('/^audio\//i', $mime)) {
            $kind = "audio";
        } else {
            $kind = "file";
        }
    }
    return [
        "name" => $original,
        "url" => "/uploads/admin_messages/" . $name,
        "mime" => $mime,
        "size" => (int)($_FILES["attachment"]["size"] ?? 0),
        "kind" => $kind
    ];
}

function admin_messages_new_message($username, $message, $attachment = null) {
    return [
        "id" => "msg_" . bin2hex(random_bytes(5)),
        "from" => $username,
        "text" => $message,
        "attachment" => $attachment,
        "deleted" => false,
        "reactions" => [],
        "edited_at" => "",
        "date" => date("c")
    ];
}

function admin_messages_local_call_link($id) {
    return "/admin_call.php?id=" . rawurlencode($id);
}

$username = $_SESSION["admin_username"] ?? "";
$nowIso = date("c");
$presence = read_json_admin_messages($presenceFile);
$presence[$username] = ["last_seen" => $nowIso];
write_json_admin_messages($presenceFile, $presence);

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$store = read_json_admin_messages($messagesFile, ["dms" => [], "meetings" => []]);
if (!isset($store["dms"]) || !is_array($store["dms"])) $store["dms"] = [];
if (!isset($store["meetings"]) || !is_array($store["meetings"])) $store["meetings"] = [];
if (!isset($store["banned_admins"]) || !is_array($store["banned_admins"])) $store["banned_admins"] = [];

if ($method === "GET") {
    $admins = read_json_admin_messages($adminsFile);
    $adminList = array_values(array_filter(array_map(function($admin) use ($presence) {
        $name = $admin["username"] ?? "";
        if ($name === "") return null;
        $lastSeen = $presence[$name]["last_seen"] ?? "";
        $lastTs = $lastSeen ? strtotime($lastSeen) : 0;
        return [
            "username" => $name,
            "country" => $admin["country"] ?? "",
            "whatsapp" => $admin["whatsapp"] ?? "",
            "email" => $admin["email"] ?? "",
            "banned" => in_array($name, $store["banned_admins"] ?? [], true),
            "online" => $lastTs && (time() - $lastTs) <= 180,
            "last_seen" => $lastSeen
        ];
    }, $admins)));

    $groups = read_json_admin_messages($groupsFile);
    $visibleGroups = admin_messages_can_manage_groups()
        ? $groups
        : array_values(array_filter($groups, fn($g) => in_array($username, $g["members"] ?? [], true)));

    $dms = array_values(array_filter($store["dms"], fn($dm) => in_array($username, $dm["members"] ?? [], true)));
    $meetings = array_values(array_filter($store["meetings"], function($meeting) use ($username) {
        $scope = $meeting["scope"] ?? "all";
        if ($scope === "all") return true;
        return in_array($username, $meeting["members"] ?? [], true);
    }));

    respond_admin_messages([
        "status" => "success",
        "current_admin" => $username,
        "admins" => $adminList,
        "groups" => $visibleGroups,
        "dms" => $dms,
        "meetings" => $meetings
    ]);
}

$isMultipart = stripos($_SERVER["CONTENT_TYPE"] ?? "", "multipart/form-data") !== false;
$input = $isMultipart ? $_POST : json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) $input = [];
$action = $input["action"] ?? "";
$attachment = $isMultipart ? admin_messages_upload_attachment($uploadsDir) : null;
$isBanned = in_array($username, $store["banned_admins"] ?? [], true);

if ($action === "dm_message") {
    if ($isBanned) respond_admin_messages(["status" => "error", "message" => "Administrateur bloqué"], 403);
    $to = clean_admin_messages_text($input["to"] ?? "", 80);
    $message = clean_admin_messages_text($input["message"] ?? "", 1200);
    if ($to === "" || $to === $username || ($message === "" && !$attachment)) respond_admin_messages(["status" => "error", "message" => "Destinataire et message requis"], 400);
    if (in_array($to, $store["banned_admins"] ?? [], true)) respond_admin_messages(["status" => "error", "message" => "Cet administrateur est bloqué"], 403);
    $members = [$username, $to];
    sort($members, SORT_STRING);
    $dmId = "dm_" . hash("sha256", implode("|", $members));
    $found = false;
    foreach ($store["dms"] as &$dm) {
        if (($dm["id"] ?? "") !== $dmId) continue;
        $dm["messages"][] = admin_messages_new_message($username, $message, $attachment);
        $dm["messages"] = array_slice($dm["messages"], -300);
        $dm["updated_at"] = date("c");
        $found = true;
        break;
    }
    unset($dm);
    if (!$found) {
        $store["dms"][] = [
            "id" => $dmId,
            "members" => $members,
            "messages" => [admin_messages_new_message($username, $message, $attachment)],
            "created_at" => date("c"),
            "updated_at" => date("c")
        ];
    }
    write_json_admin_messages($messagesFile, $store);
    respond_admin_messages(["status" => "success"]);
}

if ($action === "group_message") {
    if ($isBanned) respond_admin_messages(["status" => "error", "message" => "Administrateur bloqué"], 403);
    $groupId = clean_admin_messages_text($input["group_id"] ?? "", 80);
    $message = clean_admin_messages_text($input["message"] ?? "", 1200);
    if ($groupId === "" || ($message === "" && !$attachment)) respond_admin_messages(["status" => "error", "message" => "Message requis"], 400);
    $groups = read_json_admin_messages($groupsFile);
    foreach ($groups as &$group) {
        if (($group["id"] ?? "") !== $groupId) continue;
        if (!admin_messages_can_manage_groups() && !in_array($username, $group["members"] ?? [], true)) respond_admin_messages(["status" => "error", "message" => "Non autorisé"], 403);
        $group["messages"][] = admin_messages_new_message($username, $message, $attachment);
        $group["messages"] = array_slice($group["messages"], -300);
        write_json_admin_messages($groupsFile, $groups);
        respond_admin_messages(["status" => "success"]);
    }
    unset($group);
    respond_admin_messages(["status" => "error", "message" => "Groupe introuvable"], 404);
}

if ($action === "create_group") {
    if (!admin_messages_can_manage_groups()) respond_admin_messages(["status" => "error", "message" => "Non autorisé"], 403);
    $name = clean_admin_messages_text($input["name"] ?? "", 80);
    $description = clean_admin_messages_text($input["description"] ?? "", 300);
    if ($name === "") respond_admin_messages(["status" => "error", "message" => "Nom du groupe requis"], 400);
    $members = array_values(array_filter((array)($input["members"] ?? []), fn($m) => clean_admin_messages_text($m, 80) !== ""));
    $members[] = $username;
    $groups = read_json_admin_messages($groupsFile);
    $groups[] = [
        "id" => "grp_" . bin2hex(random_bytes(5)),
        "name" => $name,
        "description" => $description,
        "members" => array_values(array_unique($members)),
        "messages" => [],
        "created_by" => $username,
        "created_at" => date("c")
    ];
    write_json_admin_messages($groupsFile, $groups);
    respond_admin_messages(["status" => "success"]);
}

if ($action === "create_meeting") {
    if ($isBanned) respond_admin_messages(["status" => "error", "message" => "Administrateur bloqué"], 403);
    $type = in_array(($input["type"] ?? "video"), ["video", "audio"], true) ? $input["type"] : "video";
    $scope = in_array(($input["scope"] ?? "all"), ["all", "dm", "group"], true) ? $input["scope"] : "all";
    $title = clean_admin_messages_text($input["title"] ?? "Réunion administrateurs", 120);
    $members = [];
    if ($scope === "dm") {
        $to = clean_admin_messages_text($input["to"] ?? "", 80);
        if ($to === "") respond_admin_messages(["status" => "error", "message" => "Administrateur requis"], 400);
        $members = array_values(array_unique([$username, $to]));
    }
    if ($scope === "group") {
        $groupId = clean_admin_messages_text($input["group_id"] ?? "", 80);
        $groups = read_json_admin_messages($groupsFile);
        foreach ($groups as $group) {
            if (($group["id"] ?? "") === $groupId) $members = $group["members"] ?? [];
        }
        if (empty($members)) respond_admin_messages(["status" => "error", "message" => "Groupe introuvable"], 404);
    }
    $id = "call_" . bin2hex(random_bytes(6));
    $meeting = [
        "id" => $id,
        "title" => $title,
        "type" => $type,
        "scope" => $scope,
        "members" => $members,
        "created_by" => $username,
        "created_at" => date("c"),
        "ended_at" => "",
        "status" => "active",
        "participants" => [$username],
        "host" => $username,
        "room" => "emploi-info-admin-" . $id,
        "link" => admin_messages_local_call_link($id),
        "room_url" => room_link_admin_messages($id)
    ];
    $store["meetings"][] = $meeting;
    write_json_admin_messages($messagesFile, $store);
    respond_admin_messages(["status" => "success", "meeting" => $meeting]);
}

if ($action === "join_meeting") {
    $id = clean_admin_messages_text($input["id"] ?? "", 80);
    foreach ($store["meetings"] as &$meeting) {
        if (($meeting["id"] ?? "") !== $id) continue;
        if (($meeting["status"] ?? "") !== "active") respond_admin_messages(["status" => "error", "message" => "Appel terminé"], 410);
        $meeting["participants"] = array_values(array_unique(array_merge($meeting["participants"] ?? [], [$username])));
        write_json_admin_messages($messagesFile, $store);
        respond_admin_messages(["status" => "success", "link" => admin_messages_local_call_link($id)]);
    }
    unset($meeting);
    respond_admin_messages(["status" => "error", "message" => "Réunion introuvable"], 404);
}

if ($action === "end_meeting") {
    $id = clean_admin_messages_text($input["id"] ?? "", 80);
    foreach ($store["meetings"] as &$meeting) {
        if (($meeting["id"] ?? "") !== $id) continue;
        if (!admin_messages_can_manage_calls()) respond_admin_messages(["status" => "error", "message" => "Permission requise pour arrêter l'appel"], 403);
        $meeting["status"] = "ended";
        $meeting["ended_at"] = date("c");
        write_json_admin_messages($messagesFile, $store);
        respond_admin_messages(["status" => "success"]);
    }
    unset($meeting);
    respond_admin_messages(["status" => "error", "message" => "Réunion introuvable"], 404);
}

if ($action === "clear_meetings_history") {
    if (!admin_messages_can_manage_calls()) respond_admin_messages(["status" => "error", "message" => "Permission requise pour vider l'historique"], 403);
    $before = count($store["meetings"]);
    $store["meetings"] = array_values(array_filter($store["meetings"], fn($meeting) => ($meeting["status"] ?? "") === "active"));
    write_json_admin_messages($messagesFile, $store);
    respond_admin_messages(["status" => "success", "deleted" => $before - count($store["meetings"])]);
}

if ($action === "edit_message" || $action === "react_message") {
    $scope = clean_admin_messages_text($input["scope"] ?? "", 20);
    $targetId = clean_admin_messages_text($input["target_id"] ?? "", 100);
    $messageId = clean_admin_messages_text($input["message_id"] ?? "", 80);
    $newText = clean_admin_messages_text($input["text"] ?? "", 1200);
    $reaction = clean_admin_messages_text($input["reaction"] ?? "", 20);
    $changed = false;
    $apply = function (&$msg) use ($action, $username, $newText, $reaction, &$changed) {
        if ($action === "edit_message") {
            if (($msg["from"] ?? "") !== $username && !admin_messages_can_manage_groups()) respond_admin_messages(["status" => "error", "message" => "Non autorisé"], 403);
            $msg["text"] = $newText;
            $msg["edited_at"] = date("c");
        } else {
            if (!isset($msg["reactions"]) || !is_array($msg["reactions"])) $msg["reactions"] = [];
            if ($reaction === "") unset($msg["reactions"][$username]);
            else $msg["reactions"][$username] = $reaction;
        }
        $changed = true;
    };
    if ($scope === "dm") {
        foreach ($store["dms"] as &$dm) {
            if (($dm["id"] ?? "") !== $targetId || !in_array($username, $dm["members"] ?? [], true)) continue;
            foreach ($dm["messages"] as &$msg) if (($msg["id"] ?? "") === $messageId) $apply($msg);
            unset($msg);
            if ($changed) { write_json_admin_messages($messagesFile, $store); respond_admin_messages(["status" => "success"]); }
        }
        unset($dm);
    }
    if ($scope === "group") {
        $groups = read_json_admin_messages($groupsFile);
        foreach ($groups as &$group) {
            if (($group["id"] ?? "") !== $targetId || (!admin_messages_can_manage_groups() && !in_array($username, $group["members"] ?? [], true))) continue;
            foreach ($group["messages"] as &$msg) if (($msg["id"] ?? "") === $messageId) $apply($msg);
            unset($msg);
            if ($changed) { write_json_admin_messages($groupsFile, $groups); respond_admin_messages(["status" => "success"]); }
        }
        unset($group);
    }
    respond_admin_messages(["status" => "error", "message" => "Message introuvable"], 404);
}

if ($action === "delete_message") {
    $scope = clean_admin_messages_text($input["scope"] ?? "", 20);
    $targetId = clean_admin_messages_text($input["target_id"] ?? "", 100);
    $messageId = clean_admin_messages_text($input["message_id"] ?? "", 80);
    if ($messageId === "") respond_admin_messages(["status" => "error", "message" => "Message requis"], 400);
    if ($scope === "dm") {
        foreach ($store["dms"] as &$dm) {
            if (($dm["id"] ?? "") !== $targetId || !in_array($username, $dm["members"] ?? [], true)) continue;
            foreach ($dm["messages"] as &$msg) {
                if (($msg["id"] ?? "") === $messageId) {
                    if (($msg["from"] ?? "") !== $username && !admin_messages_can_manage_groups()) respond_admin_messages(["status" => "error", "message" => "Non autorisé"], 403);
                    $msg["deleted"] = true;
                    $msg["text"] = "";
                    $msg["attachment"] = null;
                    $msg["deleted_at"] = date("c");
                }
            }
            unset($msg);
            write_json_admin_messages($messagesFile, $store);
            respond_admin_messages(["status" => "success"]);
        }
        unset($dm);
    }
    if ($scope === "group") {
        $groups = read_json_admin_messages($groupsFile);
        foreach ($groups as &$group) {
            if (($group["id"] ?? "") !== $targetId || (!admin_messages_can_manage_groups() && !in_array($username, $group["members"] ?? [], true))) continue;
            foreach ($group["messages"] as &$msg) {
                if (($msg["id"] ?? "") === $messageId) {
                    if (($msg["from"] ?? "") !== $username && !admin_messages_can_manage_groups()) respond_admin_messages(["status" => "error", "message" => "Non autorisé"], 403);
                    $msg["deleted"] = true;
                    $msg["text"] = "";
                    $msg["attachment"] = null;
                    $msg["deleted_at"] = date("c");
                }
            }
            unset($msg);
            write_json_admin_messages($groupsFile, $groups);
            respond_admin_messages(["status" => "success"]);
        }
        unset($group);
    }
    respond_admin_messages(["status" => "error", "message" => "Message introuvable"], 404);
}

if ($action === "ban_admin" || $action === "unban_admin") {
    if (!admin_messages_can_manage_groups()) respond_admin_messages(["status" => "error", "message" => "Non autorisé"], 403);
    $target = clean_admin_messages_text($input["username"] ?? "", 80);
    if ($target === "" || $target === $username) respond_admin_messages(["status" => "error", "message" => "Administrateur invalide"], 400);
    $banned = $store["banned_admins"] ?? [];
    if ($action === "ban_admin") $banned[] = $target;
    if ($action === "unban_admin") $banned = array_values(array_filter($banned, fn($name) => $name !== $target));
    $store["banned_admins"] = array_values(array_unique($banned));
    write_json_admin_messages($messagesFile, $store);
    respond_admin_messages(["status" => "success"]);
}

respond_admin_messages(["status" => "error", "message" => "Action inconnue"], 400);
?>
