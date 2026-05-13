<?php
require_once __DIR__ . "/admin_guard.php";
header("Content-Type: application/json; charset=utf-8");
ei_require_admin(["manage_admins"]);

$invitesFile = __DIR__ . "/admin_invites.json";
$adminsFile = __DIR__ . "/admins.json";

function read_json_admin_invites($file, $fallback = []) {
    if (!file_exists($file)) return $fallback;
    $decoded = json_decode(file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function write_json_admin_invites($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

function clean_admin_invite_text($value, $max = 180) {
    $value = trim(strip_tags((string)$value));
    return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
}

function respond_admin_invite($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$action = $_GET["action"] ?? "";

if ($method === "GET") {
    $invites = read_json_admin_invites($invitesFile);
    foreach ($invites as &$invite) unset($invite["token_hash"]);
    unset($invite);
    respond_admin_invite(["status" => "success", "invites" => $invites]);
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) $input = [];
$action = $input["action"] ?? "";
$invites = read_json_admin_invites($invitesFile);

if ($action === "create") {
    $role = in_array(($input["role"] ?? "admin"), ["admin", "super"], true) ? $input["role"] : "admin";
    $permissions = array_values(array_filter((array)($input["permissions"] ?? []), fn($p) => preg_match('/^[a-z_]+$/', $p)));
    if ($role !== "super" && empty($permissions)) {
        respond_admin_invite(["status" => "error", "message" => "Cochez au moins une fonction administrateur."], 400);
    }
    $token = bin2hex(random_bytes(24));
    $id = "invite_" . bin2hex(random_bytes(6));
    $invite = [
        "id" => $id,
        "token_hash" => hash("sha256", $token),
        "role" => $role,
        "permissions" => $permissions,
        "country_hint" => clean_admin_invite_text($input["country_hint"] ?? ""),
        "whatsapp_hint" => clean_admin_invite_text($input["whatsapp_hint"] ?? ""),
        "email_hint" => clean_admin_invite_text($input["email_hint"] ?? ""),
        "group_id" => clean_admin_invite_text($input["group_id"] ?? "", 80),
        "created_by" => $_SESSION["admin_username"] ?? "",
        "created_at" => date("c"),
        "expires_at" => date("c", strtotime("+14 days")),
        "status" => "pending"
    ];
    $invites[] = $invite;
    write_json_admin_invites($invitesFile, $invites);
    unset($invite["token_hash"]);
    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
    $host = $_SERVER["HTTP_HOST"] ?? "localhost";
    respond_admin_invite([
        "status" => "success",
        "invite" => $invite,
        "link" => $scheme . "://" . $host . "/accept-admin-invite.html?token=" . $token
    ]);
}

if ($action === "cancel") {
    $id = clean_admin_invite_text($input["id"] ?? "", 80);
    foreach ($invites as &$invite) {
        if (($invite["id"] ?? "") === $id && ($invite["status"] ?? "") === "pending") {
            $invite["status"] = "cancelled";
            $invite["cancelled_at"] = date("c");
        }
    }
    unset($invite);
    write_json_admin_invites($invitesFile, $invites);
    respond_admin_invite(["status" => "success"]);
}

respond_admin_invite(["status" => "error", "message" => "Action inconnue"], 400);
?>
