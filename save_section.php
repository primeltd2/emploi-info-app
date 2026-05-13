<?php
require_once __DIR__ . "/admin_guard.php";
header("Content-Type: application/json; charset=utf-8");

$allowed = [
    "blog" => "blog.json",
    "resources" => "resources.json",
    "services" => "services.json",
    "formations" => "formations.json"
];

$section = $_POST["section"] ?? "";
if (!isset($allowed[$section])) {
    echo json_encode(["status" => "error", "message" => "Section invalide"]);
    exit;
}
ei_require_admin($section === "formations" ? ["content"] : ["content"]);

$titre = trim((string)($_POST["titre"] ?? ""));
$texte = trim((string)($_POST["texte"] ?? ""));
$categorie = trim((string)($_POST["categorie"] ?? ""));
$lieu = trim((string)($_POST["lieu"] ?? ""));
$prix = trim((string)($_POST["prix"] ?? ""));
$pricingType = trim((string)($_POST["pricing_type"] ?? ""));
$prixNormal = trim((string)($_POST["prix_normal"] ?? ""));
$prixPromo = trim((string)($_POST["prix_promo"] ?? ""));
$promoMode = trim((string)($_POST["promo_mode"] ?? ""));
$promoUntil = trim((string)($_POST["promo_until"] ?? ""));
$promoPlaces = trim((string)($_POST["promo_places"] ?? ""));
$dateDebut = trim((string)($_POST["date_debut"] ?? ""));
$lien = trim((string)($_POST["lien"] ?? ""));
$boutons = json_decode($_POST["boutons"] ?? "[]", true);
if (!is_array($boutons)) $boutons = [];

if ($titre === "" || $texte === "") {
    echo json_encode(["status" => "error", "message" => "Titre et contenu requis"]);
    exit;
}

$filePath = "";
$fileType = "";
$fileName = "";
if (!empty($_FILES["img"]["name"])) {
    $ext = strtolower(pathinfo($_FILES["img"]["name"], PATHINFO_EXTENSION));
    $allowedExt = ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg", "mp4", "webm", "ogg", "mov", "m4v", "pdf", "doc", "docx", "txt", "apk"];
    if (!in_array($ext, $allowedExt, true)) {
        echo json_encode(["status" => "error", "message" => "Format de fichier non autorisé"]);
        exit;
    }
    $dir = "uploads/" . $section . "/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fileName = basename($_FILES["img"]["name"]);
    $filePath = $dir . $section . "_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;
    if (!move_uploaded_file($_FILES["img"]["tmp_name"], $filePath)) {
        echo json_encode(["status" => "error", "message" => "Fichier non téléversé"]);
        exit;
    }
    if (in_array($ext, ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"], true)) $fileType = "image";
    elseif (in_array($ext, ["mp4", "webm", "ogg", "mov", "m4v"], true)) $fileType = "video";
    elseif (in_array($ext, ["doc", "docx"], true)) $fileType = "doc";
    else $fileType = $ext;
}

$file = $allowed[$section];
$items = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($items)) $items = [];

$item = [
    "id" => $section . "_" . time() . "_" . bin2hex(random_bytes(2)),
    "titre" => $titre,
    "texte" => $texte,
    "categorie" => $categorie,
    "lieu" => $lieu,
    "prix" => $prix,
    "pricing_type" => in_array($pricingType, ["gratuit", "payant", "promotion"], true) ? $pricingType : "",
    "prix_normal" => $prixNormal,
    "prix_promo" => $prixPromo,
    "promo_mode" => in_array($promoMode, ["temps", "places", "total"], true) ? $promoMode : "",
    "promo_until" => $promoUntil,
    "promo_places" => $promoPlaces,
    "date_debut" => $dateDebut,
    "lien" => $lien,
    "img" => $filePath,
    "image" => $filePath,
    "fichier" => $filePath,
    "type" => $fileType,
    "file_name" => $fileName,
    "boutons" => $boutons,
    "date" => date("c"),
    "publie" => true
];

array_unshift($items, $item);

if (file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
    echo json_encode(["status" => "error", "message" => "Sauvegarde impossible"]);
    exit;
}

echo json_encode(["status" => "success", "message" => "Publication ajoutée", "data" => $item], JSON_UNESCAPED_UNICODE);
?>
