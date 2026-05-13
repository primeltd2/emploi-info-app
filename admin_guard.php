<?php
function ei_secure_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function ei_admin_headers() {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

function ei_require_admin($permissions = []) {
    ei_secure_session_start();
    ei_admin_headers();
    $role = $_SESSION['admin_role'] ?? '';
    if (!$role) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Non autorisé"], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($role === 'super') return;
    $owned = $_SESSION['admin_permissions'] ?? [];
    foreach ((array)$permissions as $permission) {
        if (!in_array($permission, $owned, true)) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Permission refusée"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
?>
