<?php
header("Content-Type: application/json");

$statsFile = "stats.json";
$today = date("Y-m-d");

// =====================
// CHARGER STATS
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

// =====================
// INITIALISER 7 JOURS
// =====================
for ($i = 6; $i >= 0; $i--) {
    $day = date("Y-m-d", strtotime("-$i days"));
    if (!isset($stats['vues_7_jours'][$day])) {
        $stats['vues_7_jours'][$day] = 0;
    }
}

// =====================
// INCRÉMENTER VISITES
// =====================
$stats['total_visites']++;
$stats['vues_7_jours'][$today]++;

// =====================
// GARDER SEULEMENT 7 JOURS
// =====================
$stats['vues_7_jours'] = array_slice(
    $stats['vues_7_jours'],
    -7,
    7,
    true
);

// =====================
// SAUVEGARDE
// =====================
file_put_contents(
    $statsFile,
    json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

// =====================
// RÉPONSE
// =====================
echo json_encode([
    "status" => "ok",
    "today_visits" => $stats['vues_7_jours'][$today],
    "total_visites" => $stats['total_visites']
]);