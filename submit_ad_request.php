<?php
// submit_ad_request.php

// CONFIG
$adminEmail = "kodjaouelisee1234@gmail.com";
$demandesFile = "demandes_ads.json";

// Réponse JSON par défaut
$response = ["status"=>"error","message"=>"Erreur inconnue"];

if($_SERVER['REQUEST_METHOD'] === "POST") {

    // Récupération des champs
    $nom = trim($_POST['nom'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $tel = trim($_POST['tel'] ?? "");
    $produit = trim($_POST['produit'] ?? "");
    $message = trim($_POST['message'] ?? "");
    $urgent = trim($_POST['urgent'] ?? "0");

    // Vérification des champs obligatoires
    if(!$nom || !$email || !$produit || !$message){
        $response['message'] = "Veuillez remplir tous les champs obligatoires.";
        echo json_encode($response);
        exit;
    }

    // Charger demandes existantes
    $demandes = [];
    if(file_exists($demandesFile)){
        $demandes = json_decode(file_get_contents($demandesFile),true) ?? [];
    }

    // Ajouter la demande
    $demandes[] = [
        "id" => time(),
        "nom" => $nom,
        "email" => $email,
        "tel" => $tel,
        "produit" => $produit,
        "message" => $message,
        "urgent" => $urgent,
        "date" => date("c")
    ];

    // Sauvegarder
    file_put_contents($demandesFile, json_encode($demandes, JSON_PRETTY_PRINT));

    // Envoyer email aux admins
    $admins = json_decode(file_get_contents("admins.json"), true);
    foreach($admins as $admin){
        if($admin['actif']){
            $to = $adminEmail; // ou email spécifique si ajouté
            $subject = "Nouvelle demande de publicité";
            $body = "Nom: $nom\nEmail: $email\nTel: $tel\nProduit: $produit\nMessage: $message";
            mail($to, $subject, $body);
        }
    }

    $response = ["status"=>"ok","message"=>"Demande envoyée avec succès."];
}

echo json_encode($response);
?>
