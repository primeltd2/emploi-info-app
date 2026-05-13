<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once __DIR__ . "/notification_helpers.php";
require_once __DIR__ . "/android_notification_helpers.php";

$dataFile  = "data.json";
$statsFile = "stats.json";

function respond($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_text($value) {
    return trim((string)$value);
}

function absolute_asset_url($path) {
    if (!$path) return "";
    if (preg_match('#^https?://#i', $path)) return $path;
    return "https://emploi-info.page.gd/" . ltrim($path, "/");
}

function truncate_text($text, $max = 120) {
    $text = preg_replace('/\s+/u', ' ', trim((string)$text));
    if (mb_strlen($text) <= $max) return $text;
    return mb_substr($text, 0, $max) . "...";
}

$titre      = clean_text($_POST['titre'] ?? '');
$texte      = clean_text($_POST['texte'] ?? '');
$notice     = clean_text($_POST['notice'] ?? '');
$alignement = clean_text($_POST['alignement'] ?? 'left');
$categorie  = clean_text($_POST['categorie'] ?? '');
$ville      = clean_text($_POST['ville'] ?? '');

$urgent = isset($_POST['urgent']) && $_POST['urgent'] == "1";

if ($titre === '' || $texte === '') {
    respond([
        "status" => "error",
        "msg" => "Titre et description requis"
    ], 400);
}

$bannierePath = "";
if (!empty($_FILES['banniere']['tmp_name'])) {
    $dir = "images/";
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $ext = strtolower(pathinfo($_FILES['banniere']['name'], PATHINFO_EXTENSION));
    $safeName = uniqid("media_") . "." . $ext;
    $target = $dir . $safeName;

    if (move_uploaded_file($_FILES['banniere']['tmp_name'], $target)) {
        $bannierePath = $target;
    }
}

$boutons = json_decode($_POST['boutons'] ?? '[]', true);
if (!is_array($boutons)) $boutons = [];

foreach ($boutons as &$b) {
    $b['texte'] = clean_text($b['texte'] ?? '');
    $b['lien']  = clean_text($b['lien'] ?? '');
    if ($b['lien'] !== '' && !preg_match('#^https?://#i', $b['lien'])) {
        $b['lien'] = 'https://' . $b['lien'];
    }
    $b['clics'] = (int)($b['clics'] ?? 0);
}
unset($b);

$isAdmin = isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['admin', 'super'], true);
$publie = ($isAdmin && isset($_POST['direct_publish']) && (string)$_POST['direct_publish'] === "1");

$annonces = [];
if (file_exists($dataFile)) {
    $decoded = json_decode(file_get_contents($dataFile), true);
    $annonces = is_array($decoded) ? $decoded : [];
}

$id = uniqid("ann_");

$newAnnonce = [
    "id"         => $id,
    "titre"      => $titre,
    "texte"      => $texte,
    "notice"     => $notice,
    "categorie"  => $categorie,
    "ville"      => $ville,
    "banniere"   => $bannierePath,
    "boutons"    => $boutons,
    "alignement" => $alignement,
    "publie"     => $publie,
    "urgent"     => $urgent,
    "date"       => date("c")
];

array_unshift($annonces, $newAnnonce);

$jsonData = json_encode(
    $annonces,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

if ($jsonData === false || file_put_contents($dataFile, $jsonData, LOCK_EX) === false) {
    respond([
        "status" => "error",
        "msg" => "Impossible de sauvegarder l'annonce"
    ], 500);
}

if (!file_exists($statsFile)) {
    $stats = [
        "total_visites" => 0,
        "vues_7_jours"  => [],
        "clics_boutons" => []
    ];
} else {
    $decodedStats = json_decode(file_get_contents($statsFile), true);
    $stats = is_array($decodedStats) ? $decodedStats : [
        "total_visites" => 0,
        "vues_7_jours"  => [],
        "clics_boutons" => []
    ];
}

$stats['clics_boutons'][$id] = [];
foreach ($boutons as $b) {
    if (!empty($b['texte'])) {
        $stats['clics_boutons'][$id][$b['texte']] = 0;
    }
}

for ($i = 6; $i >= 0; $i--) {
    $day = date("Y-m-d", strtotime("-$i days"));
    if (!isset($stats['vues_7_jours'][$day])) {
        $stats['vues_7_jours'][$day] = 0;
    }
}

$stats['vues_7_jours'] = array_slice($stats['vues_7_jours'], -7, 7, true);

file_put_contents(
    $statsFile,
    json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

if ($publie) {
    if (!ei_offer_notification_already_sent($id)) {
        $webResult = ei_send_offer_notification($newAnnonce, 1);
        $androidResult = ei_send_android_offer_notification($newAnnonce, 1);
        ei_mark_offer_notification_sent($id, [
            'source' => 'save.php',
            'web_status' => $webResult['status'] ?? '',
            'web_count' => $webResult['count'] ?? 0,
            'android_status' => $androidResult['status'] ?? '',
            'android_count' => $androidResult['count'] ?? 0
        ]);
    }
}

respond([
    "status" => "success",
    "id"     => $id,
    "msg"    => $publie
        ? "Annonce publiée immédiatement et notification envoyée"
        : "Annonce ajoutée en attente de validation"
]);
?>
