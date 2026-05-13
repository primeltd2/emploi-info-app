<?php
session_start();
header('Content-Type: application/json');

// Vérifier que l'utilisateur est super admin
$currentRole = $_SESSION['admin_role'] ?? '';
if($currentRole !== 'super'){
    echo json_encode(['status'=>'error','msg'=>'⛔ Action non autorisée']);
    exit;
}

// Récupérer les données envoyées
$data = json_decode(file_get_contents('php://input'), true);
$password = trim($data['password'] ?? '');
$role     = trim($data['role'] ?? '');

if(!$password || !$role){
    echo json_encode(['status'=>'error','msg'=>'Mot de passe et rôle requis']);
    exit;
}

// Charger les admins existants
$file = 'admins.json';
$admins = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

// Vérifier si le mot de passe existe déjà
foreach($admins as $a){
    if(password_verify($password, $a['password'])){
        echo json_encode(['status'=>'error','msg'=>'Administrateur déjà existant']);
        exit;
    }
}

// Ajouter le nouvel admin
$admins[] = [
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'role'     => $role,
    'created_at' => date("c")
];

// Sauvegarder
file_put_contents($file, json_encode($admins, JSON_PRETTY_PRINT));

echo json_encode(['status'=>'ok','msg'=>'✅ Administrateur ajouté avec succès']);