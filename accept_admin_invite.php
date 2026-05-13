<?php
header("Content-Type: application/json; charset=utf-8");

$invitesFile = __DIR__ . "/admin_invites.json";
$adminsFile = __DIR__ . "/admins.json";
$groupsFile = __DIR__ . "/admin_groups.json";

function read_json_accept_admin($file, $fallback = []) {
    if (!file_exists($file)) return $fallback;
    $decoded = json_decode(file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function write_json_accept_admin($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

function clean_accept_admin_text($value, $max = 180) {
    $value = trim(strip_tags((string)$value));
    return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
}

function respond_accept_admin($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$input = [];
if ($method === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!is_array($input)) $input = [];
}
$token = clean_accept_admin_text($_GET["token"] ?? "", 160);
if ($method === "POST") {
    $token = clean_accept_admin_text($input["token"] ?? "", 160);
}

if (!$token) respond_accept_admin(["status" => "error", "message" => "Lien invalide"], 400);
$hash = hash("sha256", $token);
$invites = read_json_accept_admin($invitesFile);
$inviteIndex = null;
foreach ($invites as $i => $invite) {
    if (($invite["token_hash"] ?? "") === $hash) {
        $inviteIndex = $i;
        break;
    }
}
if ($inviteIndex === null) respond_accept_admin(["status" => "error", "message" => "Invitation introuvable"], 404);
$invite = $invites[$inviteIndex];
if (($invite["status"] ?? "pending") !== "pending" || strtotime($invite["expires_at"] ?? "1970-01-01") < time()) {
    respond_accept_admin(["status" => "error", "message" => "Invitation expirée ou déjà utilisée"], 410);
}

if ($method === "GET") {
    respond_accept_admin([
        "status" => "success",
        "invite" => [
            "role" => $invite["role"] ?? "admin",
            "permissions" => $invite["permissions"] ?? [],
            "country_hint" => $invite["country_hint"] ?? "",
            "whatsapp_hint" => $invite["whatsapp_hint"] ?? "",
            "email_hint" => $invite["email_hint"] ?? "",
            "expires_at" => $invite["expires_at"] ?? ""
        ]
    ]);
}

$username = clean_accept_admin_text($input["username"] ?? "", 60);
$password = (string)($input["password"] ?? "");
$country = clean_accept_admin_text($input["country"] ?? "", 80);
$whatsapp = clean_accept_admin_text($input["whatsapp"] ?? "", 60);
$email = clean_accept_admin_text($input["email"] ?? "", 120);

if ($username === "" || mb_strlen($password) < 8 || $country === "") {
    respond_accept_admin(["status" => "error", "message" => "Nom, pays et mot de passe de 8 caractères minimum requis."], 400);
}

$admins = read_json_accept_admin($adminsFile);
foreach ($admins as $admin) {
    if (mb_strtolower($admin["username"] ?? "") === mb_strtolower($username)) {
        respond_accept_admin(["status" => "error", "message" => "Ce nom administrateur existe déjà."], 409);
    }
}

$admins[] = [
    "username" => $username,
    "password" => password_hash($password, PASSWORD_DEFAULT),
    "role" => $invite["role"] ?? "admin",
    "permissions" => $invite["permissions"] ?? [],
    "country" => $country,
    "whatsapp" => $whatsapp,
    "email" => $email,
    "created_at" => date("c"),
    "created_from_invite" => $invite["id"] ?? "",
    "invited_by" => $invite["created_by"] ?? ""
];
write_json_accept_admin($adminsFile, $admins);

if (!empty($invite["group_id"])) {
    $groups = read_json_accept_admin($groupsFile);
    foreach ($groups as &$group) {
        if (($group["id"] ?? "") === $invite["group_id"]) {
            $members = $group["members"] ?? [];
            $members[] = $username;
            $group["members"] = array_values(array_unique($members));
        }
    }
    unset($group);
    write_json_accept_admin($groupsFile, $groups);
}

$invites[$inviteIndex]["status"] = "accepted";
$invites[$inviteIndex]["accepted_at"] = date("c");
$invites[$inviteIndex]["accepted_username"] = $username;
write_json_accept_admin($invitesFile, $invites);

respond_accept_admin(["status" => "success", "message" => "Compte administrateur créé. Vous pouvez vous connecter au panel."]);
?>
