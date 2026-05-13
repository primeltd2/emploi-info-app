<?php    
session_start();    
header('Content-Type: application/json');    
    
$adminsFile = "admins.json";    
    
// Charger les admins existants    
$admins = file_exists($adminsFile) ? json_decode(file_get_contents($adminsFile), true) : [];    
    
$input = json_decode(file_get_contents("php://input"), true);
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if(!$password){
    echo json_encode(["status"=>"error","msg"=>"Mot de passe requis"]);
    exit;
}

// Vérifier mots de passe par défaut (pour migration)
$defaults = [
    "1234" => ["username" => "admin1", "role" => "super"],
    "53535353" => ["username" => "admin2", "role" => "super"],
    "administrateurTONNY242" => ["username" => "admin3", "role" => "admin"]
];

if(isset($defaults[$password])){
    $user = $defaults[$password];
    $_SESSION['admin_user'] = $user['username'];
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['admin_country'] = $user['country'] ?? '';
    $_SESSION['admin_whatsapp'] = $user['whatsapp'] ?? '';
    $_SESSION['admin_email'] = $user['email'] ?? '';
    $_SESSION['admin_logged'] = true;

    // Migrer vers admins.json si pas déjà
    $exists = false;
    foreach($admins as $a){
        if(isset($a['username']) && $a['username'] === $user['username']){
            $exists = true;
            break;
        }
    }
    if(!$exists){
        $admins[] = [
            'username' => $user['username'],
            'country' => $user['country'] ?? '',
            'whatsapp' => $user['whatsapp'] ?? '',
            'email' => $user['email'] ?? '',
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $user['role'],
            'created_at' => date("c")
        ];
        file_put_contents($adminsFile, json_encode($admins, JSON_PRETTY_PRINT));
    }

    echo json_encode(["status"=>"ok","role"=>$user['role'], "need_username" => false]);
    exit;
}

// Si username fourni, vérifier admins.json
if($username){
    $found = false;
    foreach($admins as &$a){
        if(isset($a['username']) && $a['username'] === $username && password_verify($password, $a['password'])){
            $_SESSION['admin_user'] = $a['username'];
            $_SESSION['admin_role'] = $a['role'];
            $_SESSION['admin_country'] = $a['country'] ?? '';
            $_SESSION['admin_whatsapp'] = $a['whatsapp'] ?? '';
            $_SESSION['admin_email'] = $a['email'] ?? '';
            $_SESSION['admin_logged'] = true;
            $found = true;
            break;
        }
    }
    if($found){
        echo json_encode(["status"=>"ok","role"=>$_SESSION['admin_role'], "need_username" => false]);
        exit;
    }
}

// Sinon, vérifier anciens admins sans username
foreach($admins as $a){    
    if(!isset($a['username']) && password_verify($password, $a['password'])){    
        $_SESSION['admin_user'] = $a['role'];    
        $_SESSION['admin_role'] = $a['role'];    
        echo json_encode(["status"=>"ok","role"=>$a['role']]);    
        exit;    
    }    
}    
    
// Mot de passe incorrect    
echo json_encode(["status"=>"error","msg"=>"Identifiants incorrects"]);
