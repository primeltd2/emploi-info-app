<?php
header("Content-Type: application/json; charset=utf-8");
session_start();

function current_admin_can_manage() {
    $role = $_SESSION['admin_role'] ?? '';
    $username = $_SESSION['admin_username'] ?? '';
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

if(!current_admin_can_manage()){
    echo json_encode(["status" => "error", "msg" => "Non autorisé"], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if(!is_array($data)){
    echo json_encode(["status" => "error", "msg" => "Données invalides"], JSON_UNESCAPED_UNICODE);
    exit;
}

file_put_contents("admins.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
echo json_encode(["status" => "ok"], JSON_UNESCAPED_UNICODE);
?>
