<?php
header("Content-Type: application/json");

// =====================
// FICHIERS
// =====================
$dataFile  = "data.json";
$statsFile = "stats.json";

// =====================
// RÉCUPÉRATION FORMULAIRE
// =====================
$titre      = trim($_POST['titre'] ?? '');
$texte      = trim($_POST['texte'] ?? '');
$notice     = trim($_POST['notice'] ?? '');
$alignement = $_POST['alignement'] ?? 'left';

// Vérification minimale
if ($titre === '' || $texte === '') {
    echo json_encode(["status"=>"error","msg"=>"Champs requis manquants"]);
    exit;
}

// =====================
// UPLOAD IMAGE
// =====================
$bannierePath = "";
if (!empty($_FILES['banniere']['tmp_name']) && getimagesize($_FILES['banniere']['tmp_name'])) {
    $dir = "images/";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $ext = pathinfo($_FILES['banniere']['name'], PATHINFO_EXTENSION);
    $safeName = uniqid("img_") . "." . $ext;
    $target = $dir . $safeName;

    move_uploaded_file($_FILES['banniere']['tmp_name'], $target);
    $bannierePath = $target;
}

// =====================
// BOUTONS (LIENS)
// =====================
$boutons = json_decode($_POST['boutons'] ?? '[]', true);
if (!is_array($boutons)) $boutons = [];

foreach ($boutons as &$b) {
    $b['texte'] = trim($b['texte'] ?? '');
    $b['lien']  = trim($b['lien'] ?? '');
    // 🔹 Corriger les liens WhatsApp ou autres déjà complets
    if ($b['lien'] !== '' && !preg_match('#^https?://#i', $b['lien'])) {
        $b['lien'] = 'https://' . $b['lien'];
    }
    $b['clics'] = 0; // initialisation des clics
}
unset($b);

// =====================
// VÉRIFIER SI ADMIN CONNECTÉ
// =====================
session_start();
$isAdmin = isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['admin','super']);

// Si admin et paramètre direct_publish envoyé => publier directement
$publie = ($isAdmin && isset($_POST['direct_publish']) && $_POST['direct_publish'] == 1) ? true : false;

// =====================
// CHARGER ANNONCES EXISTANTES
// =====================
$annonces = [];
if (file_exists($dataFile)) {
    $annonces = json_decode(file_get_contents($dataFile), true) ?? [];
}

// =====================
// NOUVELLE ANNONCE
// =====================
$id = uniqid("ann_");

$annonces[] = [
    "id"         => $id,
    "titre"      => $titre,
    "texte"      => $texte,
    "notice"     => $notice,
    "banniere"   => $bannierePath,
    "boutons"    => $boutons,
    "alignement" => $alignement,
    "publie"     => $publie, // true si admin avec direct_publish=1
    "date"       => date("c")
];

// =====================
// SAUVEGARDE data.json
// =====================
file_put_contents(
    $dataFile,
    json_encode($annonces, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

// =====================
// STATS
// =====================
if (!file_exists($statsFile)) {
    $stats = [
        "total_visites" => 0,
        "vues_7_jours"  => [],
        "clics_boutons" => []
    ];
} else {
    $stats = json_decode(file_get_contents($statsFile), true);
}

// Initialiser clics boutons pour cette annonce
$stats['clics_boutons'][$id] = [];
foreach ($boutons as $b) {
    $stats['clics_boutons'][$id][$b['texte']] = 0;
}

// Initialiser les 7 derniers jours pour le graphe
for ($i = 6; $i >= 0; $i--) {
    $day = date("Y-m-d", strtotime("-$i days"));
    if (!isset($stats['vues_7_jours'][$day])) {
        $stats['vues_7_jours'][$day] = 0;
    }
}

// Ne conserver que 7 jours
$stats['vues_7_jours'] = array_slice(
    $stats['vues_7_jours'],
    -7,
    7,
    true
);

// =====================
// SAUVEGARDE stats.json
// =====================
file_put_contents(
    $statsFile,
    json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

// =====================
// RÉPONSE
// =====================
echo json_encode([
    "status" => "success",
    "id"     => $id,
    "msg"    => $publie ? "Publication publiée immédiatement" : "Publication ajoutée en attente de validation"
]);
?>