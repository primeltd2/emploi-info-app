<?php
// submit_offer.php

// CONFIG
$adminEmail = "kodjaouelisee1234@gmail.com";
$dataFile = "data.json";

// Réponse JSON par défaut
$response = ["status"=>"error","message"=>"Erreur inconnue"];

if($_SERVER['REQUEST_METHOD'] === "POST") {

    // Récupération des champs
    $nom = trim($_POST['nom'] ?? "");
    $whatsapp = trim($_POST['whatsapp'] ?? "");
    $titre = trim($_POST['titre'] ?? "");
    $categorie = trim($_POST['categorie'] ?? "");
    $texte = trim($_POST['texte'] ?? "");
    $notice = trim($_POST['notice'] ?? "");
    $date = $_POST['date'] ?? date("c");

    // Vérification des champs obligatoires
    if(!$nom || !$whatsapp || !$titre || !$categorie || !$texte){
        $response['message'] = "Veuillez remplir tous les champs obligatoires.";
        echo json_encode($response);
        exit;
    }

    // Gestion de la bannière
    $bannierePath = "";
    if(isset($_FILES['banniere']) && $_FILES['banniere']['error'] === 0){
        $uploadDir = "uploads/";
        if(!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
        $ext = pathinfo($_FILES['banniere']['name'], PATHINFO_EXTENSION);
        $filename = uniqid("banniere_").".".$ext;
        $target = $uploadDir.$filename;
        if(move_uploaded_file($_FILES['banniere']['tmp_name'], $target)){
            $bannierePath = $target;
        }
    }

    // Lecture et mise à jour de data.json
    $data = [];
    if(file_exists($dataFile)){
        $data = json_decode(file_get_contents($dataFile),true) ?? [];
    }

    // ID unique
    $id = time().rand(100,999);

    // Création d'une description complète et soutenue
    $texteComplet = <<<DESC
Cher administrateur,

Je me permets de soumettre une nouvelle offre de publication sur la plateforme EMPLOI INFO. Veuillez trouver ci-dessous toutes les informations concernant cette offre :

- Nom du soumissionnaire : $nom
- Numéro WhatsApp : $whatsapp
- Titre de l'offre : $titre
- Catégorie : $categorie
- Description de l'offre : 
$texte
DESC;

    // Ajouter la notice si renseignée
    if($notice) {
        $texteComplet .= "\n- Notice complémentaire : $notice";
    }

    $texteComplet .= "\n\nDate de soumission : $date";

    // Préparer l'offre à enregistrer
    $newOffer = [
        "id"=>$id,
        "nom"=>$nom,
        "whatsapp"=>$whatsapp,
        "titre"=>$titre,
        "categorie"=>$categorie,
        "texte"=>$texteComplet,
        "notice"=>$notice,
        "banniere"=>$bannierePath,
        "date"=>$date,
        "publie"=>false, // l'admin doit publier
        "urgent"=>false,
        "boutons"=>[]
    ];

    $data[] = $newOffer;

    // Sauvegarde et envoi email à l'admin
    if(file_put_contents($dataFile,json_encode($data,JSON_PRETTY_PRINT))){
        $subject = "[EMPLOI INFO] Nouvelle demande de publication";
        $message = $texteComplet;
        if($bannierePath) $message .= "\n\nBannière jointe : ".getcwd()."/$bannierePath";
        $headers = "From: noreply@emploi-info.page.gd\r\n";
        mail($adminEmail,$subject,$message,$headers);

        $response['status'] = "success";
        $response['message'] = "Votre offre a été envoyée avec succès à l'administration.";
    } else {
        $response['message'] = "Impossible de sauvegarder l'offre. Veuillez réessayer.";
    }
}

echo json_encode($response);