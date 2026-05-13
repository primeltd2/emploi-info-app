<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$currentRole = $_SESSION['admin_role'] ?? '';
$currentUsername = $_SESSION['admin_username'] ?? '';

function current_admin_can_manage($username, $role) {
    $admins = file_exists('admins.json') ? json_decode(file_get_contents('admins.json'), true) : [];
    $admins = is_array($admins) ? $admins : [];
    foreach($admins as $admin){
        if(($admin['username'] ?? '') === $username){
            $permissions = $admin['permissions'] ?? [];
            return $role === 'super' && (empty($permissions) || in_array('manage_admins', $permissions, true));
        }
    }
    return $role === 'super';
}

if(!current_admin_can_manage($currentUsername, $currentRole)){
    echo json_encode(['status'=>'error','msg'=>'Action non autorisee'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$country  = trim($data['country'] ?? '');
$whatsapp = trim($data['whatsapp'] ?? '');
$email    = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');
$role     = trim($data['role'] ?? '');
$permissions = $data['permissions'] ?? [];
$allowedPermissions = ['annonces','publicites','partners','comments','content','settings','manage_admins'];
$permissions = is_array($permissions) ? array_values(array_intersect($permissions, $allowedPermissions)) : [];

if(!$username || !$country || !$whatsapp || !$email || !$password || !$role){
    echo json_encode(['status'=>'error','msg'=>'Nom utilisateur, pays, WhatsApp, email, mot de passe et role requis'], JSON_UNESCAPED_UNICODE);
    exit;
}

if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    echo json_encode(['status'=>'error','msg'=>'Email invalide'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = 'admins.json';
$admins = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
$admins = is_array($admins) ? $admins : [];

foreach($admins as $a){
    if(isset($a['username']) && $a['username'] === $username){
        echo json_encode(['status'=>'error','msg'=>'Nom utilisateur deja pris'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$admins[] = [
    'username' => $username,
    'country' => $country,
    'whatsapp' => $whatsapp,
    'email' => strtolower($email),
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'role' => $role,
    'permissions' => $permissions,
    'created_at' => date("c")
];

file_put_contents($file, json_encode($admins, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

echo json_encode(['status'=>'ok','msg'=>'Administrateur ajoute avec succes'], JSON_UNESCAPED_UNICODE);
?>
