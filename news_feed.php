<?php
header("Content-Type: application/json; charset=utf-8");

$sourcesFile = __DIR__ . "/news_sources.json";
$cacheFile = __DIR__ . "/news_cache.json";

function read_json_public($file) {
    if (!file_exists($file)) return [];
    $decoded = json_decode(file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

if (!file_exists($cacheFile) || (time() - filemtime($cacheFile)) > 900) {
    $_POST = [];
    $sources = read_json_public($sourcesFile);
    if ($sources) {
        // Keep public refresh conservative: the admin endpoint remains the full control surface.
        $context = stream_context_create(["http" => ["timeout" => 20]]);
        @file_get_contents(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://") . ($_SERVER['HTTP_HOST'] ?? "localhost") . "/news_sources.php?action=public_refresh", false, $context);
    }
}

$items = read_json_public($cacheFile);
usort($items, fn($a, $b) => strtotime($b["date"] ?? $b["fetched_at"] ?? "now") <=> strtotime($a["date"] ?? $a["fetched_at"] ?? "now"));
echo json_encode(["status" => "success", "items" => array_slice($items, 0, 80)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
