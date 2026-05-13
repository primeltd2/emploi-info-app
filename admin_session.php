<?php
require_once __DIR__ . "/admin_guard.php";
ei_secure_session_start();
ei_admin_headers();
header("Content-Type: application/json; charset=utf-8");

$role = $_SESSION['admin_role'] ?? '';
if (!$role) {
    echo json_encode(["status" => "error", "message" => "Non connecté"], JSON_UNESCAPED_UNICODE);
    exit;
}

$maxIdle = 60 * 60 * 3;
if (!empty($_SESSION['admin_last_seen']) && (time() - (int)$_SESSION['admin_last_seen']) > $maxIdle) {
    session_destroy();
    echo json_encode(["status" => "error", "message" => "Session expirée"], JSON_UNESCAPED_UNICODE);
    exit;
}

$_SESSION['admin_last_seen'] = time();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo json_encode([
    "status" => "success",
    "role" => $role,
    "username" => $_SESSION['admin_username'] ?? '',
    "permissions" => $_SESSION['admin_permissions'] ?? [],
    "csrf" => $_SESSION['csrf_token']
], JSON_UNESCAPED_UNICODE);
?>
