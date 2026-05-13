<?php
require_once __DIR__ . "/admin_guard.php";
header("Content-Type: application/json; charset=utf-8");
$publicAction = ($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET" ? ($_GET["action"] ?? "") : "";
$isPublicRequest = in_array($publicAction, ["public_refresh", "public_detail"], true);
if (!$isPublicRequest) ei_require_admin(["actualites"]);

$sourcesFile = __DIR__ . "/news_sources.json";
$cacheFile = __DIR__ . "/news_cache.json";

function read_json_file($file, $fallback = []) {
    if (!file_exists($file)) return $fallback;
    $decoded = json_decode(file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function write_json_file($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

function respond_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_news_text($value, $max = 100000) {
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, "UTF-8");
    $value = preg_replace('/\s+/', ' ', strip_tags($value));
    $value = trim($value);
    return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
}

function absolutize_url($base, $url) {
    if (!$url) return "";
    if (preg_match('/^https?:\/\//i', $url)) return $url;
    $parts = parse_url($base);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return $url;
    if (strpos($url, '//') === 0) return $parts['scheme'] . ':' . $url;
    if ($url[0] === '/') return $parts['scheme'] . '://' . $parts['host'] . $url;
    $path = isset($parts['path']) ? preg_replace('/\/[^\/]*$/', '/', $parts['path']) : '/';
    return $parts['scheme'] . '://' . $parts['host'] . $path . $url;
}

function fetch_url($url) {
    $context = stream_context_create([
        "http" => [
            "timeout" => 12,
            "user_agent" => "EMPLOI INFO News Extractor",
            "header" => "Accept: text/html,application/rss+xml,application/xml;q=0.9,*/*;q=0.8\r\n"
        ]
    ]);
    $body = @file_get_contents($url, false, $context);
    return is_string($body) ? $body : "";
}

function looks_like_ad_text($text) {
    $text = mb_strtolower(clean_news_text($text, 300));
    $patterns = [
        "publicité", "publicite", "sponsored", "sponsorisé", "sponsorise",
        "recommended for you", "recommandé pour vous", "recommande pour vous",
        "outbrain", "taboola", "adsbygoogle", "google ads", "voir plus de recommandations",
        "passer au contenu principal", "share_on_your_website"
    ];
    foreach ($patterns as $pattern) {
        if (strpos($text, $pattern) !== false) return true;
    }
    return false;
}

function node_attr_text($node) {
    if (!$node || !$node->attributes) return "";
    $value = "";
    foreach (["class", "id", "aria-label", "role"] as $attr) {
        if ($node->attributes->getNamedItem($attr)) {
            $value .= " " . $node->attributes->getNamedItem($attr)->nodeValue;
        }
    }
    return mb_strtolower($value);
}

function is_noise_node($node) {
    $attrs = node_attr_text($node);
    $needles = [
        "ad", "ads", "advert", "advertising", "pub", "publicite", "publicité",
        "sponsor", "sponsored", "taboola", "outbrain", "google", "adsbygoogle",
        "recommend", "recommended", "cookie", "newsletter", "social", "share",
        "header", "footer", "nav", "menu", "breadcrumb", "related-widget"
    ];
    foreach ($needles as $needle) {
        if ($attrs !== "" && strpos($attrs, $needle) !== false) return true;
    }
    return looks_like_ad_text($node->textContent ?? "");
}

function first_meta_content($xpath, $names) {
    foreach ($names as $name) {
        $query = '//meta[@property="' . $name . '" or @name="' . $name . '"]/@content';
        $node = $xpath->query($query)->item(0);
        if ($node) return trim($node->nodeValue);
    }
    return "";
}

function extract_detail_from_url($url, $fallbackSummary = "") {
    $html = fetch_url($url);
    if ($html === "") return ["content" => $fallbackSummary, "image" => "", "media" => []];
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//script|//style|//iframe|//ins|//nav|//footer|//form|//*[contains(@class,"ad") or contains(@id,"ad") or contains(@class,"ads") or contains(@class,"advert") or contains(@class,"banner") or contains(@class,"sponsor") or contains(@class,"cookie") or contains(@class,"newsletter") or contains(@class,"recommended") or contains(@class,"outbrain") or contains(@class,"taboola")]') as $node) {
        if ($node->parentNode) $node->parentNode->removeChild($node);
    }
    $image = first_meta_content($xpath, ["og:image", "twitter:image"]);
    $media = [];
    if ($image) $media[] = ["type" => "image", "url" => absolutize_url($url, $image)];
    foreach ($xpath->query('//article//img[@src] | //main//img[@src] | //article//source[@srcset] | //main//source[@srcset]') as $img) {
        if (is_noise_node($img)) continue;
        $src = absolutize_url($url, $img->getAttribute("src"));
        if (!$src && $img->hasAttribute("srcset")) {
            $first = trim(explode(" ", explode(",", $img->getAttribute("srcset"))[0])[0]);
            $src = absolutize_url($url, $first);
        }
        if ($src) $media[] = ["type" => "image", "url" => $src];
    }
    foreach ($xpath->query('//article//video[@src] | //article//video/source[@src] | //main//video[@src] | //main//video/source[@src]') as $video) {
        if (is_noise_node($video)) continue;
        $src = absolutize_url($url, $video->getAttribute("src"));
        if ($src) $media[] = ["type" => "video", "url" => $src];
    }
    foreach ($xpath->query('//article//a[@href] | //main//a[@href]') as $link) {
        $href = absolutize_url($url, $link->getAttribute("href"));
        if (!is_noise_node($link) && preg_match('/\.(pdf|doc|docx|xls|xlsx|ppt|pptx|txt|zip)(\?.*)?$/i', $href)) {
            $media[] = ["type" => "document", "url" => $href, "label" => clean_news_text($link->textContent, 120) ?: basename(parse_url($href, PHP_URL_PATH))];
        }
    }
    $article = $xpath->query('//article')->item(0) ?: $xpath->query('//main')->item(0) ?: $xpath->query('//body')->item(0);
    $blocks = [];
    if ($article) {
        $blockNodes = (new DOMXPath($article->ownerDocument))->query('.//h1|.//h2|.//h3|.//h4|.//p|.//li|.//blockquote|.//figcaption|.//img[@src]|.//source[@srcset]|.//video[@src]|.//video/source[@src]|.//a[@href]', $article);
        foreach ($blockNodes as $node) {
            if (is_noise_node($node)) continue;
            $name = strtolower($node->nodeName);
            if (in_array($name, ["h1", "h2", "h3", "h4"], true)) {
                $text = clean_news_text($node->textContent, 300);
                if ($text !== "") $blocks[] = ["type" => "heading", "level" => (int)substr($name, 1), "text" => $text];
            } elseif (in_array($name, ["p", "li", "blockquote", "figcaption"], true)) {
                $text = clean_news_text($node->textContent, 2500);
                if ($text !== "" && !looks_like_ad_text($text)) {
                    $blocks[] = ["type" => $name === "blockquote" ? "quote" : ($name === "figcaption" ? "caption" : "paragraph"), "text" => $text];
                }
            } elseif (in_array($name, ["img", "source"], true)) {
                $src = absolutize_url($url, $node->getAttribute("src"));
                if (!$src && $node->hasAttribute("srcset")) {
                    $first = trim(explode(" ", explode(",", $node->getAttribute("srcset"))[0])[0]);
                    $src = absolutize_url($url, $first);
                }
                if ($src) $blocks[] = ["type" => "image", "url" => $src, "alt" => clean_news_text($node->getAttribute("alt") ?? "", 180)];
            } elseif ($name === "video") {
                $src = absolutize_url($url, $node->getAttribute("src"));
                if ($src) $blocks[] = ["type" => "video", "url" => $src];
            } elseif ($name === "a") {
                $href = absolutize_url($url, $node->getAttribute("href"));
                if (preg_match('/\.(pdf|doc|docx|xls|xlsx|ppt|pptx|txt|zip)(\?.*)?$/i', $href)) {
                    $blocks[] = ["type" => "document", "url" => $href, "label" => clean_news_text($node->textContent, 120) ?: basename(parse_url($href, PHP_URL_PATH))];
                }
            }
            if (count($blocks) >= 700) break;
        }
    }
    $content = $article ? clean_news_text($article->textContent, 100000) : $fallbackSummary;
    $contentLines = array_values(array_filter(array_map("trim", preg_split('/\R+/', $content)), fn($line) => $line !== "" && !looks_like_ad_text($line)));
    $content = mb_substr(implode("\n\n", $contentLines), 0, 100000);
    $unique = [];
    foreach ($media as $item) {
        if (empty($item["url"])) continue;
        $unique[$item["type"] . ":" . $item["url"]] = $item;
    }
    $media = array_slice(array_values($unique), 0, 12);
    return [
        "content" => $content ?: $fallbackSummary,
        "image" => $media[0]["type"] === "image" ? $media[0]["url"] : "",
        "media" => $media,
        "blocks" => $blocks
    ];
}

function extract_news_items($source) {
    $html = fetch_url($source["url"] ?? "");
    if ($html === "") return [];
    libxml_use_internal_errors(true);
    $items = [];

    $xml = @simplexml_load_string($html, "SimpleXMLElement", LIBXML_NOCDATA);
    if ($xml && (isset($xml->channel->item) || isset($xml->entry))) {
        $nodes = isset($xml->channel->item) ? $xml->channel->item : $xml->entry;
        foreach ($nodes as $node) {
            $link = (string)($node->link["href"] ?? $node->link ?? "");
            $title = clean_news_text((string)($node->title ?? ""));
            $summary = clean_news_text((string)($node->description ?? $node->summary ?? $node->content ?? ""), 1200);
            if ($title && $link) {
                $finalLink = absolutize_url($source["url"], $link);
                $detail = extract_detail_from_url($finalLink, $summary);
                $items[] = [
                    "id" => "news_" . substr(hash("sha256", $link), 0, 18),
                    "source_id" => $source["id"],
                    "source_name" => $source["name"],
                    "title" => $title,
                    "summary" => $summary,
                    "content" => $detail["content"],
                    "image" => $detail["image"],
                    "media" => $detail["media"],
                    "blocks" => $detail["blocks"],
                    "url" => $finalLink,
                    "date" => (string)($node->pubDate ?? $node->updated ?? $node->published ?? date("c")),
                    "fetched_at" => date("c")
                ];
            }
        }
        return array_slice($items, 0, 20);
    }

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//script|//style|//iframe|//ins|//*[contains(@class,"ad") or contains(@id,"ad") or contains(@class,"ads") or contains(@class,"advert") or contains(@class,"banner") or contains(@class,"sponsor")]') as $node) {
        if ($node->parentNode) $node->parentNode->removeChild($node);
    }
    $links = $xpath->query('//article//a[@href] | //h1//a[@href] | //h2//a[@href] | //h3//a[@href]');
    foreach ($links as $linkNode) {
        $title = clean_news_text($linkNode->textContent, 180);
        $href = absolutize_url($source["url"], $linkNode->getAttribute("href"));
        if (mb_strlen($title) < 12 || !$href) continue;
        $article = $linkNode;
        while ($article && $article->nodeName !== "article") $article = $article->parentNode;
        $summary = $article ? clean_news_text($article->textContent, 1200) : "";
        $detail = extract_detail_from_url($href, $summary ?: $title);
        $items[] = [
            "id" => "news_" . substr(hash("sha256", $href), 0, 18),
            "source_id" => $source["id"],
            "source_name" => $source["name"],
            "title" => $title,
            "summary" => $summary ?: $title,
            "content" => $detail["content"],
            "image" => $detail["image"],
            "media" => $detail["media"],
            "blocks" => $detail["blocks"],
            "url" => $href,
            "date" => date("c"),
            "fetched_at" => date("c")
        ];
    }
    $unique = [];
    foreach ($items as $item) $unique[$item["id"]] = $item;
    return array_slice(array_values($unique), 0, 20);
}

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET") {
    if ($publicAction === "public_detail") {
        $id = preg_replace('/[^a-z0-9_-]/i', '', $_GET["id"] ?? "");
        $cache = read_json_file($cacheFile);
        foreach ($cache as $index => $item) {
            if (($item["id"] ?? "") !== $id) continue;
            $detail = extract_detail_from_url($item["url"] ?? "", $item["summary"] ?? $item["content"] ?? "");
            $cache[$index]["content"] = $detail["content"] ?: ($item["content"] ?? "");
            $cache[$index]["image"] = $detail["image"] ?: ($item["image"] ?? "");
            $cache[$index]["media"] = $detail["media"] ?: ($item["media"] ?? []);
            $cache[$index]["blocks"] = $detail["blocks"] ?: ($item["blocks"] ?? []);
            $cache[$index]["detail_fetched_at"] = date("c");
            write_json_file($cacheFile, $cache);
            respond_json(["status" => "success", "item" => $cache[$index]]);
        }
        respond_json(["status" => "error", "message" => "Actualité introuvable"], 404);
    }
    if ($publicAction === "public_refresh") {
        $sources = read_json_file($sourcesFile);
        $all = [];
        foreach ($sources as &$source) {
            $rows = extract_news_items($source);
            $source["last_fetch"] = date("c");
            foreach ($rows as $row) $all[$row["id"]] = $row;
        }
        unset($source);
        write_json_file($sourcesFile, $sources);
        write_json_file($cacheFile, array_values($all));
        respond_json(["status" => "success", "count" => count($all)]);
    }
    respond_json(["status" => "success", "sources" => read_json_file($sourcesFile)]);
}

$input = json_decode(file_get_contents("php://input"), true);
$action = $input["action"] ?? "";
$sources = read_json_file($sourcesFile);

if ($action === "add") {
    $name = clean_news_text($input["name"] ?? "", 120);
    $url = trim((string)($input["url"] ?? ""));
    if (!$name || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
        respond_json(["status" => "error", "message" => "Source invalide"], 400);
    }
    $sources[] = ["id" => "src_" . bin2hex(random_bytes(5)), "name" => $name, "url" => $url, "created_at" => date("c")];
    write_json_file($sourcesFile, $sources);
    respond_json(["status" => "success", "message" => "Source ajoutée"]);
}

if ($action === "delete") {
    $id = preg_replace('/[^a-z0-9_-]/i', '', $input["id"] ?? "");
    $sources = array_values(array_filter($sources, fn($s) => ($s["id"] ?? "") !== $id));
    write_json_file($sourcesFile, $sources);
    respond_json(["status" => "success", "message" => "Source supprimée"]);
}

if ($action === "refresh") {
    $all = [];
    foreach ($sources as &$source) {
        $rows = extract_news_items($source);
        $source["last_fetch"] = date("c");
        foreach ($rows as $row) $all[$row["id"]] = $row;
    }
    unset($source);
    write_json_file($sourcesFile, $sources);
    write_json_file($cacheFile, array_values($all));
    respond_json(["status" => "success", "message" => count($all) . " actualité(s) synchronisée(s)"]);
}

respond_json(["status" => "error", "message" => "Action inconnue"], 400);
?>
