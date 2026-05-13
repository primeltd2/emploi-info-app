<?php
header("Content-Type: application/json; charset=utf-8");

$subsFile = "push_subscriptions.json";

function respond($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    respond(["status" => "error", "msg" => "JSON invalide"], 400);
}

$subscription = $input["subscription"] ?? null;

if (!is_array($subscription)) {
    respond(["status" => "error", "msg" => "Abonnement manquant"], 400);
}

$endpoint = trim((string)($subscription["endpoint"] ?? ""));
$keys = $subscription["keys"] ?? [];

if ($endpoint === "") {
    respond(["status" => "error", "msg" => "Endpoint manquant"], 400);
}

if (!is_array($keys) || empty($keys["p256dh"]) || empty($keys["auth"])) {
    respond(["status" => "error", "msg" => "Clés d'abonnement manquantes"], 400);
}

$normalizedSubscription = [
    "endpoint" => $endpoint,
    "expirationTime" => $subscription["expirationTime"] ?? null,
    "keys" => [
        "p256dh" => (string)$keys["p256dh"],
        "auth" => (string)$keys["auth"]
    ]
];

$subs = [];
if (file_exists($subsFile)) {
    $decoded = json_decode(file_get_contents($subsFile), true);
    if (is_array($decoded)) {
        foreach ($decoded as $sub) {
            if (
                is_array($sub) &&
                !empty($sub["endpoint"]) &&
                !empty($sub["keys"]["p256dh"]) &&
                !empty($sub["keys"]["auth"])
            ) {
                $subs[] = [
                    "endpoint" => (string)$sub["endpoint"],
                    "expirationTime" => $sub["expirationTime"] ?? null,
                    "keys" => [
                        "p256dh" => (string)$sub["keys"]["p256dh"],
                        "auth" => (string)$sub["keys"]["auth"]
                    ]
                ];
            }
        }
    }
}

$exists = false;
foreach ($subs as $sub) {
    if ($sub["endpoint"] === $normalizedSubscription["endpoint"]) {
        $exists = true;
        break;
    }
}

if (!$exists) {
    $subs[] = $normalizedSubscription;

    $json = json_encode($subs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        respond(["status" => "error", "msg" => "Erreur d'encodage JSON"], 500);
    }

    if (file_put_contents($subsFile, $json, LOCK_EX) === false) {
        respond(["status" => "error", "msg" => "Impossible d'enregistrer l'abonnement"], 500);
    }

    respond(["status" => "ok", "msg" => "Abonnement enregistré"]);
}

respond(["status" => "already_subscribed", "msg" => "Déjà abonné"]);
?>
