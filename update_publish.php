<?php
session_start();
header("Content-Type: application/json");
require_once __DIR__ . "/notification_helpers.php";
require_once __DIR__ . "/android_notification_helpers.php";

// =====================
// Vérifier rôle admin
// =====================
$role = $_SESSION['admin_role'] ?? '';
if (!$role) {
    echo json_encode(["status"=>"error","msg"=>"Non autorisé"]);
    exit;
}

// =====================
// Fichiers JSON
// =====================
$dataFile  = "data.json";
$statsFile = "stats.json";

// Charger annonces
$annonces = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
$stats    = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : [
    'total_visites' => 0,
    'clics_boutons' => []
];

// =====================
// Récupérer requête JSON
// =====================
$input = json_decode(file_get_contents("php://input"), true);
$id    = $input['id'] ?? null;
$publie  = $input['publie'] ?? null;
$suppr   = $input['supprimer'] ?? false;

if (!$id || (!isset($publie) && !$suppr)) {
    echo json_encode(["status"=>"error","msg"=>"Paramètres manquants"]);
    exit;
}

// =====================
// Trouver annonce
// =====================
$foundIndex = null;
foreach ($annonces as $k => $a) {
    if ($a['id'] === $id) {
        $foundIndex = $k;
        break;
    }
}

if ($foundIndex === null) {
    echo json_encode(["status"=>"error","msg"=>"Annonce non trouvée"]);
    exit;
}

// =====================
// Supprimer annonce (admin ou super)
// =====================
if ($suppr) {
    if (!in_array($role, ["admin","super"], true)) {
        echo json_encode(["status"=>"error","msg"=>"Action non autorisée"]);
        exit;
    }

    // Supprimer l'image si elle existe
    if (!empty($annonces[$foundIndex]['banniere']) && file_exists($annonces[$foundIndex]['banniere'])) {
        unlink($annonces[$foundIndex]['banniere']);
    }

    unset($annonces[$foundIndex]);
    $annonces = array_values($annonces); // Réindexer

    if (isset($stats['clics_boutons'][$id])) unset($stats['clics_boutons'][$id]);

    file_put_contents($dataFile, json_encode($annonces, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode(["status"=>"success","msg"=>"Annonce supprimée"]);
    exit;
}

// =====================
// Valider / publier (admin ou super)
// =====================
if (isset($publie)) {
    if (!in_array($role, ["admin","super"])) {
        echo json_encode(["status"=>"error","msg"=>"Action non autorisée"]);
        exit;
    }

    $wasPublished = !empty($annonces[$foundIndex]['publie']);
    $annonces[$foundIndex]['publie'] = (bool)$publie;
    if ((bool)$publie && !$wasPublished) {
        $annonces[$foundIndex]['date'] = date("c");
    }
    file_put_contents($dataFile, json_encode($annonces, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    if ((bool)$publie && !$wasPublished) {
        if (!ei_offer_notification_already_sent($id)) {
            $webResult = ei_send_offer_notification($annonces[$foundIndex], 1);
            $androidResult = ei_send_android_offer_notification($annonces[$foundIndex], 1);
            ei_mark_offer_notification_sent($id, [
                'source' => 'update_publish.php',
                'web_status' => $webResult['status'] ?? '',
                'web_count' => $webResult['count'] ?? 0,
                'android_status' => $androidResult['status'] ?? '',
                'android_count' => $androidResult['count'] ?? 0
            ]);
        }
    }

    echo json_encode(["status"=>"success","msg"=>"Annonce mise à jour"]);
    exit;
}

echo json_encode(["status"=>"error","msg"=>"Action inconnue"]);
?>
