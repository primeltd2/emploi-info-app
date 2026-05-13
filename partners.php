<?php
session_start();
header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");

$file = __DIR__ . "/partners.json";

function read_items($file) {
    if (!file_exists($file)) file_put_contents($file, "[]", LOCK_EX);
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function save_items($file, $items) {
    file_put_contents($file, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function clean_text($value, $max = 2000) {
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    return function_exists("mb_substr") ? mb_substr($value, 0, $max, "UTF-8") : substr($value, 0, $max);
}

function respond($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function current_admin() {
    if (empty($_SESSION["admin_logged"])) respond(["status"=>"error","message"=>"Connexion administrateur requise"], 403);
    $admins = read_items(__DIR__ . "/admins.json");
    $username = $_SESSION["admin_username"] ?? "";
    foreach ($admins as $admin) {
        if (($admin["username"] ?? "") === $username) return $admin;
    }
    return ["role" => $_SESSION["admin_role"] ?? "admin", "permissions" => []];
}

function require_permission($permission) {
    $admin = current_admin();
    $role = $admin["role"] ?? "";
    $permissions = $admin["permissions"] ?? [];
    if ($role === "super" && empty($permissions)) return;
    if (in_array($permission, $permissions, true)) return;
    respond(["status"=>"error","message"=>"Permission insuffisante"], 403);
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$action = $_GET["action"] ?? "";
$items = read_items($file);

if ($method === "GET" && $action === "public") {
    $approved = array_values(array_filter($items, fn($p) => ($p["status"] ?? "") === "approved"));
    usort($approved, fn($a, $b) => strcmp($b["updated_at"] ?? "", $a["updated_at"] ?? ""));
    respond(["status"=>"success","partners"=>$approved]);
}

if ($method === "GET" && $action === "admin") {
    require_permission("partners");
    usort($items, fn($a, $b) => strcmp($b["updated_at"] ?? "", $a["updated_at"] ?? ""));
    respond(["status"=>"success","partners"=>$items]);
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) $input = $_POST;
$postAction = $input["action"] ?? "";

if ($method === "POST" && $postAction === "submit") {
    $name = clean_text($input["name"] ?? "", 160);
    $organization = clean_text($input["organization"] ?? "", 180);
    $type = clean_text($input["type"] ?? "", 80);
    $email = clean_text($input["email"] ?? "", 180);
    $whatsapp = clean_text($input["whatsapp"] ?? "", 50);
    $website = clean_text($input["website"] ?? "", 220);
    $city = clean_text($input["city"] ?? "", 120);
    $message = clean_text($input["message"] ?? "", 2500);

    if ($name === "" || $organization === "" || $email === "" || $message === "") {
        respond(["status"=>"error","message"=>"Nom, organisation, email et message requis"], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(["status"=>"error","message"=>"Email invalide"], 400);
    }

    $date = date("c");
    $items[] = [
        "id" => "partner_" . bin2hex(random_bytes(8)),
        "name" => $name,
        "organization" => $organization,
        "type" => $type ?: "partenaire",
        "email" => strtolower($email),
        "whatsapp" => $whatsapp,
        "website" => $website,
        "city" => $city,
        "message" => $message,
        "status" => "pending",
        "created_at" => $date,
        "updated_at" => $date
    ];
    save_items($file, $items);
    respond(["status"=>"success","message"=>"Votre demande de partenariat a été envoyée."]);
}

if ($method === "POST" && in_array($postAction, ["approve", "reject", "delete"], true)) {
    require_permission("partners");
    $id = clean_text($input["id"] ?? "", 80);
    if ($id === "") respond(["status"=>"error","message"=>"Identifiant requis"], 400);
    if ($postAction === "delete") {
        $before = count($items);
        $items = array_values(array_filter($items, fn($p) => ($p["id"] ?? "") !== $id));
        save_items($file, $items);
        respond(["status"=>"success","deleted"=>$before - count($items)]);
    }
    foreach ($items as &$item) {
        if (($item["id"] ?? "") === $id) {
            $item["status"] = $postAction === "approve" ? "approved" : "rejected";
            $item["updated_at"] = date("c");
            save_items($file, $items);
            respond(["status"=>"success","partner"=>$item]);
        }
    }
    unset($item);
    respond(["status"=>"error","message"=>"Demande introuvable"], 404);
}

respond(["status"=>"error","message"=>"Action non reconnue"], 400);
