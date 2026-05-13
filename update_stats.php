<?php
header("Content-Type: application/json");

$file = "stats.json";
$action = $_GET["action"] ?? "track";
$today = date("Y-m-d");

// ================== INIT ==================
if (!file_exists($file)) {
    $jours = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date("Y-m-d", strtotime("-$i day"));
        $jours[$d] = 0;
    }

    $stats = [
        "jours" => $jours,
        "total_visites" => 0
    ];

    file_put_contents($file, json_encode($stats, JSON_PRETTY_PRINT));
}

// ================== LECTURE ==================
$stats = json_decode(file_get_contents($file), true);
$jours = $stats["jours"];

// ================== TRACK VISITE ==================
if ($action === "track") {

    // Nouveau jour → décalage
    if (!isset($jours[$today])) {
        $jours = array_slice($jours, 1, null, true);
        $jours[$today] = 0;
    }

    $jours[$today]++;
    $stats["total_visites"]++;
    $stats["jours"] = $jours;

    file_put_contents($file, json_encode($stats, JSON_PRETTY_PRINT));

    echo json_encode([
        "status" => "ok",
        "date" => $today,
        "visites_aujourdhui" => $jours[$today],
        "total_visites" => $stats["total_visites"]
    ]);
    exit;
}

// ================== GET STATS (ADMIN) ==================
if ($action === "get") {

    // Ordre chronologique gauche ➜ droite
    $ordered = [];
    $dates = array_keys($jours);
    sort($dates);

    foreach ($dates as $d) {
        $ordered[$d] = $jours[$d];
    }

    echo json_encode([
        "status" => "ok",
        "jours" => $ordered,
        "total_visites" => $stats["total_visites"]
    ]);
    exit;
}

// ================== ACTION INVALIDE ==================
echo json_encode([
    "status" => "error",
    "msg" => "Action invalide"
]);