<?php
header("Content-Type: application/json; charset=utf-8");

$dataFile = "blog.json";
$titre = trim($_POST["titre"] ?? "");
$texte = trim($_POST["texte"] ?? "");
$lien = trim($_POST["lien"] ?? "");
$boutons = json_decode($_POST["boutons"] ?? "[]", true) ?: [];

if ($titre === "" || $texte === "") {
    echo json_encode(["status" => "error", "message" => "Titre et contenu requis"]);
    exit;
}

$filePath = "";
$fileType = "";
$fileName = "";
if (!empty($_FILES["image"]["name"])) {
    $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    $allowed = ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg", "mp4", "webm", "ogg", "mov", "m4v", "pdf", "doc", "docx", "txt", "apk"];
    if (!in_array($ext, $allowed, true)) {
        echo json_encode(["status" => "error", "message" => "Format de fichier non autorisé"]);
        exit;
    }

    $targetDir = "uploads/blog/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    $fileName = basename($_FILES["image"]["name"]);
    $filePath = $targetDir . "blog_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;
    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $filePath)) {
        echo json_encode(["status" => "error", "message" => "Fichier non téléversé"]);
        exit;
    }

    if (in_array($ext, ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"], true)) $fileType = "image";
    elseif (in_array($ext, ["mp4", "webm", "ogg", "mov", "m4v"], true)) $fileType = "video";
    elseif (in_array($ext, ["doc", "docx"], true)) $fileType = "doc";
    else $fileType = $ext;
}

$articles = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
if (!is_array($articles)) $articles = [];

array_unshift($articles, [
    "id" => uniqid("blog_", true),
    "titre" => $titre,
    "texte" => $texte,
    "lien" => $lien,
    "img" => $filePath,
    "image" => $filePath,
    "fichier" => $filePath,
    "type" => $fileType,
    "file_name" => $fileName,
    "boutons" => $boutons,
    "date" => date("c"),
    "publie" => true
]);

if (file_put_contents($dataFile, json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
    echo json_encode(["status" => "error", "message" => "Impossible de sauvegarder l'article"]);
    exit;
}

echo json_encode(["status" => "success", "message" => "Article publié"]);
?>
