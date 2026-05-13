<?php
header("Content-Type: application/json; charset=utf-8");

$file = "resources.json";
$titre = trim($_POST["titre"] ?? "");
$texte = trim($_POST["texte"] ?? "");
$lien = trim($_POST["lien"] ?? "");

if ($titre === "" || $texte === "") {
    echo json_encode(["status" => "error", "message" => "Titre et description requis"]);
    exit;
}

$filePath = "";
$fileType = "";
$fileName = "";
if (!empty($_FILES["img"]["name"])) {
    $ext = strtolower(pathinfo($_FILES["img"]["name"], PATHINFO_EXTENSION));
    $allowed = ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg", "mp4", "webm", "ogg", "mov", "m4v", "pdf", "doc", "docx", "txt", "apk"];
    if (!in_array($ext, $allowed, true)) {
        echo json_encode(["status" => "error", "message" => "Format de fichier non autorisé"]);
        exit;
    }
    $dir = "uploads/resources/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fileName = basename($_FILES["img"]["name"]);
    $filePath = $dir . "resource_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;
    if (!move_uploaded_file($_FILES["img"]["tmp_name"], $filePath)) {
        echo json_encode(["status" => "error", "message" => "Fichier non téléversé"]);
        exit;
    }
    if (in_array($ext, ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"], true)) $fileType = "image";
    elseif (in_array($ext, ["mp4", "webm", "ogg", "mov", "m4v"], true)) $fileType = "video";
    elseif (in_array($ext, ["doc", "docx"], true)) $fileType = "doc";
    else $fileType = $ext;
}

$resources = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($resources)) $resources = [];

array_unshift($resources, [
    "id" => uniqid("resource_", true),
    "titre" => $titre,
    "texte" => $texte,
    "lien" => $lien,
    "img" => $filePath,
    "image" => $filePath,
    "fichier" => $filePath,
    "type" => $fileType,
    "file_name" => $fileName,
    "boutons" => $lien ? [["texte" => "Ouvrir", "lien" => $lien, "couleur" => "#c9a227"]] : [],
    "date" => date("c"),
    "publie" => true
]);

file_put_contents($file, json_encode($resources, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
echo json_encode(["status" => "success"]);
?>
