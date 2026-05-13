<?php
session_start();
header("Content-Type: application/json");

if(!isset($_SESSION['admin_logged'])){
    echo json_encode(["status"=>"error","msg"=>"Non connecté"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$username = trim($data['username'] ?? '');

if(!$username){
    echo json_encode(["status"=>"error","msg"=>"Nom d'utilisateur requis"]);
    exit;
}

$admins = json_decode(file_get_contents("admins.json"), true);
foreach($admins as &$a){
    if($a['username'] === $_SESSION['admin_user'] || !isset($a['username'])){
        $a['username'] = $username;
        break;
    }
}

file_put_contents("admins.json", json_encode($admins, JSON_PRETTY_PRINT));
$_SESSION['admin_user'] = $username;

echo json_encode(["status"=>"ok"]);
?>