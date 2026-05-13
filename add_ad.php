<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Securite admin
if (!isset($_SESSION['admin_role'])) {
    echo json_encode(["status" => "error", "msg" => "Non autorise"]);
    exit;
}

$dataFile = "ads.json";
$response = ["status" => "error", "msg" => "Erreur"];

function clean_text($data) {
    return trim((string)$data);
}

function normalize_bool($value) {
    if ($value === true || $value === 1 || $value === "1") {
        return true;
    }

    if (is_string($value) && strtolower($value) === "true") {
        return true;
    }

    return false;
}

function ensure_ads_array($ads) {
    if (!is_array($ads)) {
        return [];
    }

    foreach ($ads as &$ad) {
        if (!is_array($ad)) {
            $ad = [];
        }

        if (!array_key_exists("publie", $ad)) {
            $ad["publie"] = true;
        } else {
            $ad["publie"] = normalize_bool($ad["publie"]);
        }

        if (!isset($ad["type"]) || ($ad["type"] !== "image" && $ad["type"] !== "video")) {
            $file = $ad["fichier"] ?? $ad["image"] ?? $ad["video"] ?? "";
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $ad["type"] = in_array($ext, ["mp4", "webm", "ogg", "mov", "m4v"], true) ? "video" : "image";
        }
    }
    unset($ad);

    return $ads;
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $titre = clean_text($_POST['titre'] ?? "");
    $lien = clean_text($_POST['lien'] ?? "");
    $description = trim((string)($_POST['description'] ?? ""));

    if ($titre === "") {
        echo json_encode(["status" => "error", "msg" => "Titre requis"]);
        exit;
    }

    $fichierPath = "";
    $type = "image";

    $uploadField = null;
    foreach (["image", "fichier", "media"] as $fieldName) {
        if (isset($_FILES[$fieldName]) && isset($_FILES[$fieldName]["error"]) && $_FILES[$fieldName]["error"] === UPLOAD_ERR_OK) {
            $uploadField = $_FILES[$fieldName];
            break;
        }
    }

    if ($uploadField !== null) {
        $uploadDir = "uploads/publicites/";
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            echo json_encode(["status" => "error", "msg" => "Impossible de creer le dossier d'upload"]);
            exit;
        }

        $originalName = $uploadField["name"] ?? "";
        $tmpName = $uploadField["tmp_name"] ?? "";
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $allowedImageExt = ["jpg", "jpeg", "png", "gif", "webp"];
        $allowedVideoExt = ["mp4", "webm", "ogg", "mov", "m4v"];
        $allowedExt = array_merge($allowedImageExt, $allowedVideoExt);

        if (!in_array($ext, $allowedExt, true)) {
            echo json_encode(["status" => "error", "msg" => "Format non autorise"]);
            exit;
        }

        $filename = "pub_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        $target = $uploadDir . $filename;

        if (!move_uploaded_file($tmpName, $target)) {
            echo json_encode(["status" => "error", "msg" => "Echec de l'upload"]);
            exit;
        }

        $fichierPath = $target;
        $type = in_array($ext, $allowedVideoExt, true) ? "video" : "image";
    }

    if ($fichierPath === "") {
        echo json_encode(["status" => "error", "msg" => "Fichier requis (image ou video)"]);
        exit;
    }

    $ads = [];
    if (file_exists($dataFile)) {
        $json = file_get_contents($dataFile);
        $decoded = json_decode($json, true);
        $ads = ensure_ads_array($decoded);
    }

    $id = "pub_" . time();

    $newAd = [
        "id" => $id,
        "titre" => $titre,
        "description" => $description,
        "fichier" => $fichierPath,
        "type" => $type,
        "lien" => $lien,
        "publie" => true,
        "date" => date("Y-m-d H:i:s")
    ];

    array_unshift($ads, $newAd);

    $jsonOutput = json_encode($ads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonOutput === false) {
        echo json_encode(["status" => "error", "msg" => "Erreur d'encodage JSON"]);
        exit;
    }

    if (file_put_contents($dataFile, $jsonOutput, LOCK_EX) === false) {
        echo json_encode(["status" => "error", "msg" => "Impossible d'enregistrer la publicite"]);
        exit;
    }

    $response = [
        "status" => "ok",
        "msg" => "Publicite ajoutee",
        "data" => $newAd
    ];
}

echo json_encode($response);
?>
