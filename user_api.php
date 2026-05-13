<?php
session_start();
header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");

$usersFile = __DIR__ . "/users.json";
$appsFile = __DIR__ . "/applications.json";
$rateFile = __DIR__ . "/login_attempts.json";

function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_file($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function write_json_file($file, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false && file_put_contents($file, $json, LOCK_EX) !== false;
}

function clean_value($value, $max = 1000) {
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    return function_exists("mb_substr") ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function clean_email($email) {
    $email = filter_var(trim((string)$email), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : "";
}

function current_user_id() {
    return $_SESSION["user_id"] ?? "";
}

function require_user() {
    $id = current_user_id();
    if (!$id) json_response(["status" => "error", "message" => "Connexion requise"], 401);
    return $id;
}

function csrf_token() {
    if (empty($_SESSION["user_csrf"])) $_SESSION["user_csrf"] = bin2hex(random_bytes(32));
    return $_SESSION["user_csrf"];
}

function require_csrf() {
    $token = $_POST["csrf"] ?? ($_SERVER["HTTP_X_CSRF_TOKEN"] ?? "");
    if (!hash_equals(csrf_token(), (string)$token)) {
        json_response(["status" => "error", "message" => "Session expirée. Rechargez la page."], 403);
    }
}

function public_user($user) {
    return [
        "id" => $user["id"] ?? "",
        "name" => $user["name"] ?? "",
        "email" => $user["email"] ?? "",
        "phone" => $user["phone"] ?? "",
        "role" => $user["role"] ?? "candidat",
        "favorites" => $user["favorites"] ?? [],
        "alerts" => $user["alerts"] ?? ["enabled" => false, "categories" => []],
        "created_at" => $user["created_at"] ?? ""
    ];
}

function rate_limited($key, $file) {
    $rows = read_json_file($file);
    $now = time();
    $rows = array_filter($rows, function($row) use ($now) {
        return ($row["until"] ?? 0) > $now || ($row["last"] ?? 0) > $now - 900;
    });
    $row = $rows[$key] ?? ["count" => 0, "last" => 0, "until" => 0];
    if (($row["until"] ?? 0) > $now) {
        write_json_file($file, $rows);
        return true;
    }
    $row["count"] = (($row["last"] ?? 0) > $now - 900) ? (($row["count"] ?? 0) + 1) : 1;
    $row["last"] = $now;
    if ($row["count"] > 8) $row["until"] = $now + 900;
    $rows[$key] = $row;
    write_json_file($file, $rows);
    return ($row["until"] ?? 0) > $now;
}

function find_user_index_by_email($users, $email) {
    foreach ($users as $i => $user) {
        if (($user["email"] ?? "") === $email) return $i;
    }
    return -1;
}

function safe_upload_cv($fieldName) {
    if (empty($_FILES[$fieldName]["name"]) || ($_FILES[$fieldName]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return "";
    if ($_FILES[$fieldName]["error"] !== UPLOAD_ERR_OK) json_response(["status" => "error", "message" => "CV non téléversé"], 400);
    if (($_FILES[$fieldName]["size"] ?? 0) > 5 * 1024 * 1024) json_response(["status" => "error", "message" => "CV trop lourd, maximum 5 Mo"], 400);

    $ext = strtolower(pathinfo($_FILES[$fieldName]["name"], PATHINFO_EXTENSION));
    $allowed = ["pdf", "doc", "docx", "txt"];
    if (!in_array($ext, $allowed, true)) json_response(["status" => "error", "message" => "CV autorisé : PDF, DOC, DOCX ou TXT"], 400);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES[$fieldName]["tmp_name"]);
    finfo_close($finfo);
    $allowedMime = [
        "application/pdf",
        "application/msword",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "text/plain",
        "application/octet-stream"
    ];
    if (!in_array($mime, $allowedMime, true)) json_response(["status" => "error", "message" => "Type de fichier refusé"], 400);

    $dir = __DIR__ . "/uploads/cv/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = "cv_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    $target = $dir . $name;
    if (!move_uploaded_file($_FILES[$fieldName]["tmp_name"], $target)) json_response(["status" => "error", "message" => "CV non enregistré"], 500);
    return "uploads/cv/" . $name;
}

$action = $_POST["action"] ?? ($_GET["action"] ?? "me");
$users = read_json_file($usersFile);

if ($action === "me") {
    $id = current_user_id();
    $user = null;
    foreach ($users as $row) {
        if (($row["id"] ?? "") === $id) $user = $row;
    }
    json_response(["status" => "success", "csrf" => csrf_token(), "user" => $user ? public_user($user) : null]);
}

if (in_array($action, ["register", "login", "favorite", "alerts", "applications"], true)) {
    json_response(["status" => "error", "message" => "Les comptes utilisateur sont desactives."], 410);
}

if ($action === "register") {
    require_csrf();
    $name = clean_value($_POST["name"] ?? "", 120);
    $email = clean_email($_POST["email"] ?? "");
    $phone = clean_value($_POST["phone"] ?? "", 40);
    $password = (string)($_POST["password"] ?? "");
    if (!$name || !$email || strlen($password) < 8) json_response(["status" => "error", "message" => "Nom, email valide et mot de passe de 8 caractères minimum requis"], 400);
    if (find_user_index_by_email($users, $email) >= 0) json_response(["status" => "error", "message" => "Un compte existe déjà avec cet email"], 409);
    $user = [
        "id" => "usr_" . bin2hex(random_bytes(10)),
        "name" => $name,
        "email" => $email,
        "phone" => $phone,
        "password_hash" => password_hash($password, PASSWORD_DEFAULT),
        "role" => "candidat",
        "favorites" => [],
        "alerts" => ["enabled" => false, "categories" => []],
        "created_at" => date("c"),
        "last_login" => date("c")
    ];
    $users[] = $user;
    write_json_file($usersFile, $users);
    session_regenerate_id(true);
    $_SESSION["user_id"] = $user["id"];
    json_response(["status" => "success", "user" => public_user($user), "csrf" => csrf_token()]);
}

if ($action === "login") {
    require_csrf();
    $email = clean_email($_POST["email"] ?? "");
    $password = (string)($_POST["password"] ?? "");
    $rateKey = hash("sha256", ($_SERVER["REMOTE_ADDR"] ?? "") . "|" . $email);
    if (rate_limited($rateKey, $rateFile)) json_response(["status" => "error", "message" => "Trop de tentatives. Réessayez dans 15 minutes."], 429);
    $idx = find_user_index_by_email($users, $email);
    if ($idx < 0 || !password_verify($password, $users[$idx]["password_hash"] ?? "")) {
        json_response(["status" => "error", "message" => "Email ou mot de passe incorrect"], 401);
    }
    session_regenerate_id(true);
    $_SESSION["user_id"] = $users[$idx]["id"];
    $users[$idx]["last_login"] = date("c");
    write_json_file($usersFile, $users);
    json_response(["status" => "success", "user" => public_user($users[$idx]), "csrf" => csrf_token()]);
}

if ($action === "logout") {
    require_csrf();
    unset($_SESSION["user_id"]);
    session_regenerate_id(true);
    json_response(["status" => "success", "csrf" => csrf_token()]);
}

if ($action === "favorite") {
    require_csrf();
    $userId = require_user();
    $offerId = clean_value($_POST["offer_id"] ?? "", 80);
    if (!$offerId) json_response(["status" => "error", "message" => "Offre inconnue"], 400);
    foreach ($users as &$user) {
        if (($user["id"] ?? "") !== $userId) continue;
        $favorites = $user["favorites"] ?? [];
        if (in_array($offerId, $favorites, true)) {
            $favorites = array_values(array_filter($favorites, function($id) use ($offerId) {
                return $id !== $offerId;
            }));
            $active = false;
        } else {
            $favorites[] = $offerId;
            $active = true;
        }
        $user["favorites"] = $favorites;
        write_json_file($usersFile, $users);
        json_response(["status" => "success", "active" => $active, "favorites" => $favorites]);
    }
}

if ($action === "alerts") {
    require_csrf();
    $userId = require_user();
    $enabled = ($_POST["enabled"] ?? "0") === "1";
    $categories = json_decode($_POST["categories"] ?? "[]", true);
    $categories = is_array($categories) ? array_values(array_unique(array_map(function($c) {
        return clean_value($c, 80);
    }, $categories))) : [];
    foreach ($users as &$user) {
        if (($user["id"] ?? "") !== $userId) continue;
        $user["alerts"] = ["enabled" => $enabled, "categories" => $categories];
        write_json_file($usersFile, $users);
        json_response(["status" => "success", "alerts" => $user["alerts"]]);
    }
}

if ($action === "apply") {
    require_csrf();
    $userId = "";
    $apps = read_json_file($appsFile);
    $offerId = clean_value($_POST["offer_id"] ?? "", 80);
    $name = clean_value($_POST["name"] ?? "", 120);
    $email = clean_email($_POST["email"] ?? "");
    $phone = clean_value($_POST["phone"] ?? "", 40);
    $message = clean_value($_POST["message"] ?? "", 2500);
    if (!$offerId || !$name || (!$email && !$phone)) json_response(["status" => "error", "message" => "Nom, contact et offre requis"], 400);
    $cvPath = safe_upload_cv("cv");
    $apps[] = [
        "id" => "app_" . bin2hex(random_bytes(8)),
        "offer_id" => $offerId,
        "user_id" => $userId,
        "name" => $name,
        "email" => $email,
        "phone" => $phone,
        "message" => $message,
        "cv" => $cvPath,
        "status" => "new",
        "date" => date("c"),
        "ip_hash" => hash("sha256", $_SERVER["REMOTE_ADDR"] ?? "")
    ];
    write_json_file($appsFile, $apps);
    json_response(["status" => "success", "message" => "Candidature envoyée"]);
}

if ($action === "applications") {
    $userId = require_user();
    $apps = array_values(array_filter(read_json_file($appsFile), function($app) use ($userId) {
        return ($app["user_id"] ?? "") === $userId;
    }));
    json_response(["status" => "success", "applications" => $apps]);
}

json_response(["status" => "error", "message" => "Action inconnue"], 400);
?>
