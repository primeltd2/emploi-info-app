<?php
$candidates = [
    __DIR__ . '/downloads/emploi-info.apk',
    __DIR__ . '/emploi-info.apk',
];

$apk = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate) && is_readable($candidate)) {
        $apk = $candidate;
        break;
    }
}

if (!$apk) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Application introuvable.";
    exit;
}

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="EMPLOI-INFO.apk"');
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Content-Length: ' . filesize($apk));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
readfile($apk);
exit;
