<?php
header("Content-Type: application/json; charset=utf-8");
session_start();

if (!isset($_SESSION["admin_role"]) || !in_array($_SESSION["admin_role"], ["admin", "super"], true)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "msg" => "Non autorise"], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Donnees invalides"], JSON_UNESCAPED_UNICODE);
    exit;
}

$villes = [];
foreach ($data as $ville) {
    $ville = trim((string)$ville);
    if ($ville !== "" && !in_array($ville, $villes, true)) {
        $villes[] = $ville;
    }
}

sort($villes, SORT_NATURAL | SORT_FLAG_CASE);
$json = json_encode(array_values($villes), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false || file_put_contents("villes.json", $json, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "Sauvegarde impossible"], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(["status" => "success", "villes" => $villes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
