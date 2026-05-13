<?php
header("Content-Type: application/json; charset=utf-8");

$dataFile = "services.json";
$uploadDir = "uploads/services/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Méthode non autorisée"]);
    exit;
}

$titre = trim($_POST["titre"] ?? "");
$texte = trim($_POST["texte"] ?? "");
$boutons = json_decode($_POST["boutons"] ?? "[]", true);
if (!is_array($boutons)) $boutons = [];

if ($titre === "" || $texte === "") {
    echo json_encode(["status" => "error", "message" => "Champs obligatoires manquants"]);
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
    $fileName = basename($_FILES["img"]["name"]);
    $filePath = $uploadDir . "service_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;
    if (!move_uploaded_file($_FILES["img"]["tmp_name"], $filePath)) {
        echo json_encode(["status" => "error", "message" => "Fichier non téléversé"]);
        exit;
    }
    if (in_array($ext, ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"], true)) $fileType = "image";
    elseif (in_array($ext, ["mp4", "webm", "ogg", "mov", "m4v"], true)) $fileType = "video";
    elseif (in_array($ext, ["doc", "docx"], true)) $fileType = "doc";
    else $fileType = $ext;
}

$services = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
if (!is_array($services)) $services = [];

array_unshift($services, [
    "id" => uniqid("service_", true),
    "titre" => $titre,
    "texte" => $texte,
    "image" => $filePath,
    "img" => $filePath,
    "fichier" => $filePath,
    "type" => $fileType,
    "file_name" => $fileName,
    "boutons" => $boutons,
    "date" => date("c"),
    "published_at" => time(),
    "publie" => true
]);

file_put_contents($dataFile, json_encode($services, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

echo json_encode(["status" => "success", "message" => "Service publié avec succès"]);
?>
