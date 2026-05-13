<?php
require_once __DIR__ . "/admin_guard.php";
ei_secure_session_start();
ei_admin_headers();
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents("php://input"), true);
$pwd = (string)($input['password'] ?? '');
$username = trim((string)($input['username'] ?? ''));
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$attemptsFile = __DIR__ . '/login_attempts.json';
$attempts = file_exists($attemptsFile) ? json_decode(file_get_contents($attemptsFile), true) : [];
$attempts = is_array($attempts) ? $attempts : [];
$attemptKey = hash('sha256', $ip . '|' . strtolower($username));
$now = time();
$attempt = $attempts[$attemptKey] ?? ['count' => 0, 'last' => 0, 'blocked_until' => 0];
if (($attempt['blocked_until'] ?? 0) > $now) {
    http_response_code(429);
    echo json_encode(["status" => "error", "msg" => "Trop de tentatives. Réessayez dans quelques minutes."], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($username === '' || $pwd === '') {
    echo json_encode(["status" => "error", "msg" => "Nom d'utilisateur et mot de passe requis"], JSON_UNESCAPED_UNICODE);
    exit;
}

$adminsFile = 'admins.json';

/* ===== CREATION ADMIN PAR DEFAUT ===== */
if (!file_exists($adminsFile) || filesize($adminsFile) == 0) {
    $defaultAdmin = [
        [
            "username" => "ELISEE",
            "password" => "1234", // 👈 NON HASHÉ (comme demandé)
            "role" => "super",
            "created_at" => date("Y-m-d")
        ]
    ];
    file_put_contents($adminsFile, json_encode($defaultAdmin, JSON_PRETTY_PRINT));
}
/* ===== FIN CREATION ===== */

$admins = json_decode(file_get_contents($adminsFile), true);

$found = false;

foreach ($admins as $admin) {
    
    // 🔥 CAS 1 : admin avec mot de passe hashé
    if (isset($admin['password_hashed'])) {
        if ($admin['username'] === $username && password_verify($pwd, $admin['password_hashed'])) {
            $found = true;
            $admin['password_needs_rehash'] = false;
        }
    }

    // 🔥 CAS 2 : admin par défaut (mot de passe en clair)
    elseif (isset($admin['password'])) {
        $stored = (string)$admin['password'];
        $isHash = preg_match('/^\$2y\$|\$argon2/i', $stored);
        if (($admin['username'] ?? '') === $username && (($isHash && password_verify($pwd, $stored)) || (!$isHash && hash_equals($stored, $pwd)))) {
            $found = true;
            $admin['password_needs_rehash'] = !$isHash;
        }
    }

    if ($found) {
        session_regenerate_id(true);
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_role'] = $admin['role'] ?? 'admin';
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_permissions'] = $admin['permissions'] ?? [];
        $_SESSION['admin_last_seen'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        if (!empty($admin['password_needs_rehash'])) {
            foreach ($admins as &$savedAdmin) {
                if (($savedAdmin['username'] ?? '') === $admin['username']) {
                    $savedAdmin['password'] = password_hash($pwd, PASSWORD_DEFAULT);
                    unset($savedAdmin['password_needs_rehash']);
                    break;
                }
            }
            unset($savedAdmin);
            file_put_contents($adminsFile, json_encode($admins, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
        break;
    }
}

if ($found) {
    unset($attempts[$attemptKey]);
    file_put_contents($attemptsFile, json_encode($attempts, JSON_PRETTY_PRINT), LOCK_EX);
    echo json_encode([
        "status" => "success",
        "role" => $_SESSION['admin_role'],
        "username" => $_SESSION['admin_username'],
        "permissions" => $_SESSION['admin_permissions'],
        "csrf" => $_SESSION['csrf_token']
    ], JSON_UNESCAPED_UNICODE);
} else {
    $attempt['count'] = (($now - (int)($attempt['last'] ?? 0)) > 900) ? 1 : ((int)($attempt['count'] ?? 0) + 1);
    $attempt['last'] = $now;
    if ($attempt['count'] >= 5) {
        $attempt['blocked_until'] = $now + 900;
    }
    $attempts[$attemptKey] = $attempt;
    file_put_contents($attemptsFile, json_encode($attempts, JSON_PRETTY_PRINT), LOCK_EX);
    echo json_encode([
        "status" => "error",
        "msg" => "Nom d'utilisateur ou mot de passe incorrect"
    ], JSON_UNESCAPED_UNICODE);
}
?>
