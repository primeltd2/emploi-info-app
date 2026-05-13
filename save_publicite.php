<?php
require_once __DIR__ . "/admin_guard.php";
header('Content-Type: application/json; charset=utf-8');
ei_require_admin(["publicites"]);

$response = [
    'success' => false,
    'message' => 'Erreur inconnue'
];

$adsFile = 'ads.json';

function normalize_bool($value) {
    if ($value === true || $value === 1 || $value === '1') {
        return true;
    }

    if (is_string($value) && strtolower($value) === 'true') {
        return true;
    }

    return false;
}

function normalize_ads($ads) {
    if (!is_array($ads)) {
        return [];
    }

    foreach ($ads as &$ad) {
        if (!is_array($ad)) {
            $ad = [];
        }

        if (!array_key_exists('publie', $ad)) {
            $ad['publie'] = true;
        } else {
            $ad['publie'] = normalize_bool($ad['publie']);
        }

        if (!isset($ad['type']) || !in_array($ad['type'], ['image', 'video'], true)) {
            $file = $ad['fichier'] ?? '';
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $ad['type'] = in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'm4v'], true) ? 'video' : 'image';
        }
    }
    unset($ad);

    return $ads;
}

$ads = [];
if (file_exists($adsFile)) {
    $decoded = json_decode(file_get_contents($adsFile), true);
    $ads = normalize_ads($decoded);
}

if (
    !isset($_POST['titre'], $_POST['description'], $_POST['lien']) ||
    !isset($_FILES['file'])
) {
    $response['message'] = 'Champs manquants';
    echo json_encode($response);
    exit;
}

$titre = trim((string)$_POST['titre']);
$description = trim((string)$_POST['description']);
$lien = trim((string)$_POST['lien']);
$durationDays = (int)($_POST['duration_days'] ?? 30);
$durationDays = max(1, min(3650, $durationDays));
$file = $_FILES['file'];

if ($titre === '') {
    $response['message'] = 'Titre requis';
    echo json_encode($response);
    exit;
}

if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Fichier invalide ou absent';
    echo json_encode($response);
    exit;
}

$allowedImageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowedVideoExt = ['mp4', 'webm', 'ogg', 'mov', 'm4v'];
$allowed = array_merge($allowedImageExt, $allowedVideoExt);

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed, true)) {
    $response['message'] = 'Format de fichier non autorisé';
    echo json_encode($response);
    exit;
}

if (($file['size'] ?? 0) > 50 * 1024 * 1024) {
    $response['message'] = 'Fichier trop volumineux (max 50MB)';
    echo json_encode($response);
    exit;
}

$uploadDir = 'uploads/publicites/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    $response['message'] = 'Impossible de créer le dossier uploads';
    echo json_encode($response);
    exit;
}

$filename = 'pub_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$filepath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    $response['message'] = 'Erreur lors du téléversement';
    echo json_encode($response);
    exit;
}

$newAd = [
    'id' => 'pub_' . time(),
    'titre' => $titre,
    'description' => $description,
    'fichier' => $filepath,
    'type' => in_array($ext, $allowedVideoExt, true) ? 'video' : 'image',
    'lien' => $lien,
    'publie' => true,
    'date' => date('Y-m-d H:i:s'),
    'duration_days' => $durationDays,
    'expires_at' => date('Y-m-d H:i:s', strtotime('+' . $durationDays . ' days'))
];

array_unshift($ads, $newAd);

$json = json_encode(
    $ads,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($json === false) {
    $response['message'] = 'Erreur lors de l’encodage JSON';
    @unlink($filepath);
    echo json_encode($response);
    exit;
}

if (file_put_contents($adsFile, $json, LOCK_EX) !== false) {
    $response['success'] = true;
    $response['message'] = 'Publicité ajoutée avec succès';
    $response['data'] = $newAd;
} else {
    $response['message'] = 'Erreur lors de la sauvegarde';
    @unlink($filepath);
}

echo json_encode($response);
?>
