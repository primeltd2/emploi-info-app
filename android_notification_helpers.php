<?php
function ei_android_read_json_file(string $file, array $fallback = []): array {
    if (!file_exists($file)) return $fallback;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : $fallback;
}

function ei_android_write_json_file(string $file, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false && file_put_contents($file, $json, LOCK_EX) !== false;
}

function ei_android_log(string $event, array $context = []): void {
    $file = __DIR__ . '/android_push_log.json';
    $rows = ei_android_read_json_file($file);
    $rows[] = [
        'time' => date('c'),
        'event' => $event,
        'context' => $context
    ];
    $rows = array_slice($rows, -200);
    ei_android_write_json_file($file, $rows);
}

function ei_android_base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function ei_android_service_account(): ?array {
    $file = __DIR__ . '/firebase_service_account.json';
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data) || empty($data['client_email']) || empty($data['private_key']) || empty($data['project_id'])) {
        return null;
    }
    return $data;
}

function ei_android_access_token(array $serviceAccount): ?string {
    if (!function_exists('openssl_sign') || !function_exists('curl_init')) return null;

    $cacheFile = __DIR__ . '/firebase_access_token.json';
    $cached = ei_android_read_json_file($cacheFile);
    if (!empty($cached['access_token']) && (int)($cached['expires_at'] ?? 0) > time() + 60) {
        return (string)$cached['access_token'];
    }

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600
    ];

    $unsigned = ei_android_base64url(json_encode($header)) . '.' . ei_android_base64url(json_encode($claims));
    $signature = '';
    if (!openssl_sign($unsigned, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256)) {
        return null;
    }

    $jwt = $unsigned . '.' . ei_android_base64url($signature);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ])
    ]);
    $response = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $tokenData = json_decode((string)$response, true);
    if ($code < 200 || $code >= 300 || empty($tokenData['access_token'])) {
        return null;
    }

    ei_android_write_json_file($cacheFile, [
        'access_token' => $tokenData['access_token'],
        'expires_at' => time() + (int)($tokenData['expires_in'] ?? 3600)
    ]);

    return (string)$tokenData['access_token'];
}

function ei_android_send_fcm(array $message): array {
    $serviceAccount = ei_android_service_account();
    if (!$serviceAccount) {
        ei_android_log('firebase_not_configured');
        return ['status' => 'android_firebase_not_configured', 'count' => 0, 'report' => []];
    }

    $accessToken = ei_android_access_token($serviceAccount);
    if (!$accessToken) {
        ei_android_log('firebase_auth_failed');
        return ['status' => 'android_firebase_auth_failed', 'count' => 0, 'report' => []];
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($serviceAccount['project_id']) . '/messages:send';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode(['message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ]);
    $response = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = [
        'success' => $code >= 200 && $code < 300,
        'code' => $code,
        'response' => json_decode((string)$response, true) ?: (string)$response
    ];
    ei_android_log('firebase_send_result', [
        'success' => $result['success'],
        'code' => $code,
        'response' => $result['response']
    ]);
    return $result;
}

function ei_send_android_offer_notification(array $offer, int $sendNumber = 1): array {
    $tokensFile = __DIR__ . '/android_tokens.json';
    $tokens = ei_android_read_json_file($tokensFile);
    if (!$tokens) {
        ei_android_log('no_android_tokens', ['offer_id' => (string)($offer['id'] ?? ''), 'send_number' => $sendNumber]);
        return ['status' => 'no_android_tokens', 'count' => 0, 'report' => []];
    }

    $id = (string)($offer['id'] ?? '');
    if ($sendNumber <= 1 && function_exists('ei_offer_notification_already_sent') && ei_offer_notification_already_sent($id)) {
        return ['status' => 'already_sent_android', 'count' => 0, 'report' => []];
    }
    $title = (string)($offer['titre'] ?? 'Nouvelle offre');
    if ($sendNumber > 1) $title = 'Rappel : ' . $title;

    $body = trim(strip_tags((string)($offer['notice'] ?? $offer['texte'] ?? 'Une nouvelle offre est disponible.')));
    $body = preg_replace('/\s+/u', ' ', $body);
    $body = function_exists('mb_substr') ? mb_substr($body, 0, 130) : substr($body, 0, 130);
    $url = 'https://emploi-info.page.gd/details.html?id=' . urlencode($id);

    $kept = [];
    $report = [];
    foreach ($tokens as $row) {
        $token = is_array($row) ? (string)($row['token'] ?? '') : '';
        if ($token === '') continue;

        $result = ei_android_send_fcm([
            'token' => $token,
            'data' => [
                'title' => $title,
                'body' => $body,
                'offer_id' => $id,
                'url' => $url,
                'send_number' => (string)$sendNumber,
                'type' => 'annonce'
            ],
            'android' => [
                'priority' => 'HIGH',
                'collapse_key' => $id !== '' ? 'offer_' . $id : 'emploi_info_offer'
            ]
        ]);

        $report[] = ['token' => substr($token, 0, 12) . '...', 'result' => $result];
        $responseText = json_encode($result['response'] ?? '');
        $invalid = !$result['success'] && (
            ($result['code'] ?? 0) === 404 ||
            stripos($responseText, 'UNREGISTERED') !== false ||
            stripos($responseText, 'INVALID_ARGUMENT') !== false
        );
        if (!$invalid) $kept[] = $row;
    }

    ei_android_write_json_file($tokensFile, array_values($kept));
    ei_android_log('android_offer_notification_done', [
        'offer_id' => $id,
        'send_number' => $sendNumber,
        'tokens_before' => count($tokens),
        'tokens_after' => count($kept),
        'sent_count' => count($report)
    ]);
    return ['status' => 'sent_android', 'count' => count($report), 'report' => $report];
}
?>
