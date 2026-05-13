<?php
// save_service.php
header("Content-Type: application/json");

// ===== CONFIG =====
$dataFile = "services.json";
$uploadDir = "uploads/services/";

// Créer dossier upload si inexistant
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ===== Vérification POST =====
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Méthode non autorisée"]);
    exit;
}

// ===== Récupération données =====
$titre   = trim($_POST["titre"] ?? "");
$texte   = trim($_POST["texte"] ?? "");
$boutons = json_decode($_POST["boutons"] ?? "[]", true);

// Validation minimale
if ($titre === "" || $texte === "") {
    echo json_encode(["status" => "error", "message" => "Champs obligatoires manquants"]);
    exit;
}

// ===== Gestion image =====
$imagePath = "";
if (!empty($_FILES["img"]["name"])) {
    $ext = strtolower(pathinfo($_FILES["img"]["name"], PATHINFO_EXTENSION));
    $allowed = ["jpg", "jpeg", "png", "webp"];

    if (!in_array($ext, $allowed)) {
        echo json_encode(["status" => "error", "message" => "Format image non autorisé"]);
        exit;
    }

    $imageName = "service_" . time() . "_" . rand(100,999) . "." . $ext;
    $targetPath = $uploadDir . $imageName;

    if (move_uploaded_file($_FILES["img"]["tmp_name"], $targetPath)) {
        $imagePath = $targetPath;
    }
}

// ===== Charger anciens services =====
$services = [];
if (file_exists($dataFile)) {
    $json = file_get_contents($dataFile);
    $services = json_decode($json, true) ?: [];
}

// ===== Nouveau service =====
$newService = [
    "titre"        => htmlspecialchars($titre, ENT_QUOTES, "UTF-8"),
    "texte"        => htmlspecialchars($texte, ENT_QUOTES, "UTF-8"),
    "image"        => $imagePath,
    "boutons"      => $boutons,
    "date"         => date("Y-m-d H:i:s"),
    "published_at" => time()
];

// Ajouter en haut (plus récent d’abord)
array_unshift($services, $newService);

// ===== Sauvegarde =====
file_put_contents(
    $dataFile,
    json_encode($services, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

// ===== Réponse =====
echo json_encode([
    "status" => "success",
    "message" => "Service publié avec succès"
]);