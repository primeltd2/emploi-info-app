<?php    
session_start();    
header('Content-Type: application/json');    
    
$adminsFile = "admins.json";    
    
// Charger les admins existants    
$admins = file_exists($adminsFile) ? json_decode(file_get_contents($adminsFile), true) : [];    
    
$input = json_decode(file_get_contents("php://input"), true);    
$password = trim($input['password'] ?? '');    
    
if(!$password){    
    echo json_encode(["status"=>"error","msg"=>"Mot de passe requis"]);    
    exit;    
}    
    
// Vérifier mots de passe par défaut    
$defaults = [    
    "1234" => "super",    
    "53535353" => "super",    
    "administrateurTONNY242" => "admin"    
];    
    
if(isset($defaults[$password])){    
    $_SESSION['admin_user'] = $defaults[$password];    
    $_SESSION['admin_role'] = $defaults[$password];    
    
    // Sauvegarder dans admins.json si pas déjà    
    $exists = false;    
    foreach($admins as $a){    
        if(isset($a['password']) && password_verify($password, $a['password'])){    
            $exists = true;    
            break;    
        }    
    }    
    if(!$exists){    
        $admins[] = [    
            'password' => password_hash($password, PASSWORD_DEFAULT),    
            'role' => $defaults[$password],    
            'created_at' => date("c")    
        ];    
        file_put_contents($adminsFile, json_encode($admins, JSON_PRETTY_PRINT));    
    }    
    
    echo json_encode(["status"=>"ok","role"=>$defaults[$password]]);    
    exit;    
}    
    
// Vérifier admins.json    
foreach($admins as $a){    
    if(password_verify($password, $a['password'])){    
        $_SESSION['admin_user'] = $a['role'];    
        $_SESSION['admin_role'] = $a['role'];    
        echo json_encode(["status"=>"ok","role"=>$a['role']]);    
        exit;    
    }    
}    
    
// Mot de passe incorrect    
echo json_encode(["status"=>"error","msg"=>"Mot de passe incorrect"]);