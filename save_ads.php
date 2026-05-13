<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_role'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Non autorisé"
    ]);
    exit;
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

function detect_type_from_file($file) {
    $ext = strtolower(pathinfo((string)$file, PATHINFO_EXTENSION));
    $videoExt = ["mp4", "webm", "ogg", "mov", "m4v"];

    return in_array($ext, $videoExt, true) ? "video" : "image";
}

function normalize_ads($ads) {
    if (!is_array($ads)) {
        return [];
    }

    $normalized = [];

    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }

        $fichier = $ad["fichier"] ?? $ad["image"] ?? $ad["video"] ?? "";
        $type = $ad["type"] ?? detect_type_from_file($fichier);

        if ($type !== "image" && $type !== "video") {
            $type = detect_type_from_file($fichier);
        }

        $durationDays = (int)($ad["duration_days"] ?? 30);
        $durationDays = max(1, min(3650, $durationDays));
        $date = (string)($ad["date"] ?? date("Y-m-d H:i:s"));
        $expiresAt = (string)($ad["expires_at"] ?? date("Y-m-d H:i:s", strtotime($date . " +" . $durationDays . " days")));

        $normalized[] = [
            "id" => isset($ad["id"]) ? (string)$ad["id"] : ("pub_" . time() . "_" . bin2hex(random_bytes(3))),
            "titre" => trim((string)($ad["titre"] ?? "")),
            "description" => (string)($ad["description"] ?? ""),
            "fichier" => (string)$fichier,
            "type" => $type,
            "lien" => trim((string)($ad["lien"] ?? "")),
            "publie" => array_key_exists("publie", $ad) ? normalize_bool($ad["publie"]) : true,
            "date" => $date,
            "duration_days" => $durationDays,
            "expires_at" => $expiresAt
        ];
    }

    return $normalized;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode([
        "status" => "error",
        "message" => "JSON invalide"
    ]);
    exit;
}

$ads = normalize_ads($data);

$json = json_encode(
    $ads,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($json === false) {
    echo json_encode([
        "status" => "error",
        "message" => "Erreur d'encodage JSON"
    ]);
    exit;
}

if (file_put_contents("ads.json", $json, LOCK_EX) === false) {
    echo json_encode([
        "status" => "error",
        "message" => "Erreur lors de la sauvegarde"
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "Publicités sauvegardées",
    "count" => count($ads)
]);
?>
