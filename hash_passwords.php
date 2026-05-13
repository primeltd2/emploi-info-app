<?php
// Script pour hasher les mots de passe existants
$adminsFile = 'admins.json';

if (file_exists($adminsFile)) {
    $admins = json_decode(file_get_contents($adminsFile), true);
    
    foreach ($admins as &$admin) {
        if (!isset($admin['password_hashed']) && isset($admin['password'])) {
            $admin['password_hashed'] = password_hash($admin['password'], PASSWORD_DEFAULT);
            unset($admin['password']); // Supprimer le mot de passe en clair
        }
    }
    
    file_put_contents($adminsFile, json_encode($admins, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Mots de passe hashés avec succès.";
} else {
    echo "Fichier admins.json non trouvé.";
}
?>