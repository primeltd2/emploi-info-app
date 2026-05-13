<?php
// send_mail.php
header('Content-Type: text/plain');

// CONFIG
$admin_email = "kodjaouelisee1234@gmail.com";
$data_file = "data.json";
$upload_dir = "uploads/";
if(!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);

// Récupérer les données du formulaire
$nom = $_POST['nom'] ?? '';
$whatsapp = $_POST['whatsapp'] ?? '';
$titre = $_POST['titre'] ?? '';
$texte = $_POST['texte'] ?? '';
$notice = $_POST['notice'] ?? '';
$categorie = $_POST['categorie'] ?? '';
$urgent = ($_POST['urgent'] ?? 'false') === 'true' ? true : false;

// Gestion de l'image
$banniere_path = '';
if(isset($_FILES['banniereFile']) && $_FILES['banniereFile']['error']===0){
    $ext = pathinfo($_FILES['banniereFile']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('ban_').'.'.$ext;
    if(move_uploaded_file($_FILES['banniereFile']['tmp_name'], $upload_dir.$filename)){
        $banniere_path = $upload_dir.$filename;
    }
}

// Lire l'ancien fichier data.json
$data = [];
if(file_exists($data_file)){
    $json = file_get_contents($data_file);
    $data = json_decode($json, true) ?? [];
}

// Ajouter la nouvelle annonce
$id = time();
$data[] = [
    "id"=>$id,
    "nom"=>$nom,
    "whatsapp"=>$whatsapp,
    "titre"=>$titre,
    "texte"=>$texte,
    "notice"=>$notice,
    "categorie"=>$categorie,
    "urgent"=>$urgent,
    "banniere"=>$banniere_path,
    "boutons"=>[],
    "publie"=>false, // invisible jusqu'à validation admin
    "date"=>date("c")
];

// Sauvegarder
file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT));

// ENVOI MAIL
$subject = "Nouvelle publication EMPLOI INFO";
$message = "Nom: $nom\nWhatsApp: $whatsapp\nTitre: $titre\nTexte: $texte\nNotice: $notice\nCatégorie: $categorie\nUrgent: ".($urgent?"Oui":"Non")."\nDate: ".date("d/m/Y H:i")."\n\nVoir admin: https://emploi-info.page.gd/admin.html";

$headers = "From: noreply@emploi-info.page.gd\r\n";

if(mail($admin_email, $subject, $message, $headers)){
    echo "OK";
}else{
    echo "ERREUR MAIL";
}
?>