<?php
require_once __DIR__ . "/admin_guard.php";
header("Content-Type: application/json; charset=utf-8");
ei_require_admin([]);

$groupsFile = __DIR__ . "/admin_groups.json";
$adminsFile = __DIR__ . "/admins.json";

function read_json_admin_groups($file, $fallback = []) {
    if (!file_exists($file)) return $fallback;
    $decoded = json_decode(file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function write_json_admin_groups($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

function clean_admin_group_text($value, $max = 1200) {
    $value = trim(strip_tags((string)$value));
    return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
}

function can_manage_admin_groups() {
    $role = $_SESSION["admin_role"] ?? "";
    $permissions = $_SESSION["admin_permissions"] ?? [];
    return $role === "super" || in_array("manage_admins", $permissions, true);
}

function respond_admin_groups($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$username = $_SESSION["admin_username"] ?? "";
$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$groups = read_json_admin_groups($groupsFile);

if ($method === "GET") {
    $admins = read_json_admin_groups($adminsFile);
    $visibleGroups = can_manage_admin_groups()
        ? $groups
        : array_values(array_filter($groups, fn($g) => in_array($username, $g["members"] ?? [], true)));
    respond_admin_groups(["status" => "success", "groups" => $visibleGroups, "admins" => array_map(fn($a) => ["username" => $a["username"] ?? "", "country" => $a["country"] ?? ""], $admins)]);
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) $input = [];
$action = $input["action"] ?? "";

if ($action === "create") {
    if (!can_manage_admin_groups()) respond_admin_groups(["status" => "error", "message" => "Non autorisé"], 403);
    $name = clean_admin_group_text($input["name"] ?? "", 80);
    if ($name === "") respond_admin_groups(["status" => "error", "message" => "Nom du groupe requis"], 400);
    $members = array_values(array_filter((array)($input["members"] ?? []), fn($m) => $m !== ""));
    $members[] = $username;
    $groups[] = [
        "id" => "grp_" . bin2hex(random_bytes(5)),
        "name" => $name,
        "members" => array_values(array_unique($members)),
        "messages" => [],
        "created_by" => $username,
        "created_at" => date("c")
    ];
    write_json_admin_groups($groupsFile, $groups);
    respond_admin_groups(["status" => "success"]);
}

if ($action === "message") {
    $groupId = clean_admin_group_text($input["group_id"] ?? "", 80);
    $message = clean_admin_group_text($input["message"] ?? "", 1200);
    if ($groupId === "" || $message === "") respond_admin_groups(["status" => "error", "message" => "Message requis"], 400);
    foreach ($groups as &$group) {
        if (($group["id"] ?? "") !== $groupId) continue;
        if (!can_manage_admin_groups() && !in_array($username, $group["members"] ?? [], true)) {
            respond_admin_groups(["status" => "error", "message" => "Non autorisé"], 403);
        }
        $group["messages"][] = ["id" => "msg_" . bin2hex(random_bytes(5)), "from" => $username, "text" => $message, "date" => date("c")];
        $group["messages"] = array_slice($group["messages"], -200);
        write_json_admin_groups($groupsFile, $groups);
        respond_admin_groups(["status" => "success"]);
    }
    unset($group);
    respond_admin_groups(["status" => "error", "message" => "Groupe introuvable"], 404);
}

respond_admin_groups(["status" => "error", "message" => "Action inconnue"], 400);
?>
