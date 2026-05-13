<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/notification_helpers.php';

function respond($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$title = trim((string)($_POST['title'] ?? 'Nouvelle offre'));
$body  = trim((string)($_POST['body'] ?? 'Une nouvelle offre est disponible sur EMPLOI INFO'));
$url   = trim((string)($_POST['url'] ?? '/'));
$image = trim((string)($_POST['image'] ?? ''));
$icon  = trim((string)($_POST['icon'] ?? 'https://emploi-info.page.gd/logo.png'));
$id    = trim((string)($_POST['id'] ?? ''));

respond(ei_send_push_payload([
    'title' => $title,
    'body'  => $body,
    'url'   => $url,
    'image' => $image ?: null,
    'icon'  => $icon,
    'badge' => 'https://emploi-info.page.gd/logo.png',
    'id' => $id
]));
?>
