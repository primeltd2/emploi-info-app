<?php
header("Content-Type: application/json");

// Fichiers
$dataFile = "blog.json";

// Récupérer les champs
$titre = $_POST['titre'] ?? '';
$texte = $_POST['texte'] ?? '';
$boutons = json_decode($_POST['boutons'] ?? '[]', true) ?: [];
$bannierePath = '';

// Gestion de l'image
if(isset($_FILES['image']) && getimagesize($_FILES['image']['tmp_name'])){
    $targetDir = "images/";
    if(!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    $filename = uniqid() . "_" . basename($_FILES['image']['name']);
    $targetFile = $targetDir . $filename;
    if(move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)){
        $bannierePath = $targetFile;
    }
}

// Charger les articles existants
if(file_exists($dataFile)){
    $articles = json_decode(file_get_contents($dataFile), true) ?: [];
}else{
    $articles = [];
}

// Créer l'article
$id = uniqid();
$articles[] = [
    "id" => $id,
    "titre" => $titre,
    "texte" => $texte,
    "img" => $bannierePath,
    "boutons" => $boutons,
    "date" => date("c")
];

// Sauvegarder dans blog.json
if(file_put_contents($dataFile, json_encode($articles, JSON_PRETTY_PRINT))){
    echo json_encode(["status"=>"success","message"=>"Article publié !"]);
}else{
    echo json_encode(["status"=>"error","message"=>"Impossible de sauvegarder l'article."]);
}
?>