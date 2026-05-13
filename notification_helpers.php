<?php
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

function ei_vapid_config(): array {
    $localConfig = __DIR__ . '/vapid_config.php';
    if (file_exists($localConfig)) {
        $config = require $localConfig;
        if (is_array($config)) {
            return [
                'subject' => (string)($config['subject'] ?? ''),
                'publicKey' => (string)($config['publicKey'] ?? ''),
                'privateKey' => (string)($config['privateKey'] ?? '')
            ];
        }
    }

    return [
        'subject' => (string)(getenv('EI_VAPID_SUBJECT') ?: ''),
        'publicKey' => (string)(getenv('EI_VAPID_PUBLIC_KEY') ?: ''),
        'privateKey' => (string)(getenv('EI_VAPID_PRIVATE_KEY') ?: '')
    ];
}

function ei_read_json_file(string $file, array $fallback = []): array {
    if (!file_exists($file)) return $fallback;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : $fallback;
}

function ei_write_json_file(string $file, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false && file_put_contents($file, $json, LOCK_EX) !== false;
}

function ei_notification_sent_file(): string {
    return __DIR__ . '/notification_sent.json';
}

function ei_offer_notification_already_sent(string $offerId): bool {
    if ($offerId === '') return true;
    $sent = ei_read_json_file(ei_notification_sent_file());
    return !empty($sent[$offerId]['sent']);
}

function ei_mark_offer_notification_sent(string $offerId, array $context = []): void {
    if ($offerId === '') return;
    $file = ei_notification_sent_file();
    $sent = ei_read_json_file($file);
    $sent[$offerId] = array_merge([
        'sent' => true,
        'sent_at' => date('c')
    ], $context);
    ei_write_json_file($file, $sent);
}

function ei_absolute_url(string $path): string {
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return 'https://emploi-info.page.gd/' . ltrim($path, '/');
}

function ei_send_push_payload_with_node(array $payload, array $subs): array {
    if (!function_exists('proc_open')) {
        return [
            'status' => 'server_push_not_ready',
            'count' => 0,
            'msg' => 'proc_open indisponible pour le secours Node WebPush'
        ];
    }

    $script = __DIR__ . '/scripts/send_web_push.js';
    if (!file_exists($script) || !is_dir(__DIR__ . '/node_modules/web-push')) {
        return [
            'status' => 'server_push_not_ready',
            'count' => 0,
            'msg' => 'Librairie WebPush manquante'
        ];
    }

    $inputFile = tempnam(sys_get_temp_dir(), 'ei_webpush_');
    $vapid = ei_vapid_config();
    if ($vapid['subject'] === '' || $vapid['publicKey'] === '' || $vapid['privateKey'] === '') {
        return [
            'status' => 'server_push_not_ready',
            'count' => 0,
            'msg' => 'Configuration VAPID manquante'
        ];
    }

    $input = [
        'vapid' => [
            'subject' => $vapid['subject'],
            'publicKey' => $vapid['publicKey'],
            'privateKey' => $vapid['privateKey'],
        ],
        'payload' => $payload,
        'subscriptions' => $subs
    ];
    file_put_contents($inputFile, json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $cmd = 'node ' . escapeshellarg($script) . ' ' . escapeshellarg($inputFile);
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__);
    if (!is_resource($process)) {
        @unlink($inputFile);
        return ['status' => 'node_web_push_error', 'count' => 0, 'msg' => 'Impossible de lancer Node WebPush'];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    @unlink($inputFile);

    $result = json_decode((string)$stdout, true);
    if (!is_array($result)) {
        return [
            'status' => 'node_web_push_error',
            'count' => 0,
            'msg' => trim((string)$stderr) ?: 'Réponse Node WebPush invalide'
        ];
    }

    if (isset($result['kept']) && is_array($result['kept'])) {
        ei_write_json_file(__DIR__ . '/push_subscriptions.json', array_values($result['kept']));
        unset($result['kept']);
    }

    return $result;
}

function ei_offer_details_url(string $id): string {
    return 'https://emploi-info.page.gd/details.html?id=' . urlencode($id);
}

function ei_send_push_payload(array $payload): array {
    $subsFile = __DIR__ . '/push_subscriptions.json';
    $subs = ei_read_json_file($subsFile);
    if (!$subs) return ['status' => 'no_subs', 'count' => 0, 'report' => []];

    $payload = array_merge([
        'title' => 'EMPLOI INFO',
        'body' => 'Une nouvelle offre est disponible.',
        'url' => 'https://emploi-info.page.gd/',
        'icon' => 'https://emploi-info.page.gd/logo.png',
        'badge' => 'https://emploi-info.page.gd/logo.png',
        'image' => null,
        'id' => '',
        'type' => 'annonce'
    ], $payload);

    if (!class_exists('\\Minishlink\\WebPush\\WebPush') || !class_exists('\\Minishlink\\WebPush\\Subscription')) {
        return ei_send_push_payload_with_node($payload, $subs);
    }

    $vapid = ei_vapid_config();
    if ($vapid['subject'] === '' || $vapid['publicKey'] === '' || $vapid['privateKey'] === '') {
        return [
            'status' => 'server_push_not_ready',
            'count' => 0,
            'msg' => 'Configuration VAPID manquante'
        ];
    }

    $webPush = new \Minishlink\WebPush\WebPush([
        'VAPID' => [
            'subject' => $vapid['subject'],
            'publicKey' => $vapid['publicKey'],
            'privateKey' => $vapid['privateKey'],
        ],
    ]);

    $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $report = [];
    $keptSubs = [];

    foreach ($subs as $subData) {
        if (
            !is_array($subData) ||
            empty($subData['endpoint']) ||
            empty($subData['keys']['p256dh']) ||
            empty($subData['keys']['auth'])
        ) {
            continue;
        }

        try {
            $subscription = \Minishlink\WebPush\Subscription::create($subData);
            $webPush->queueNotification($subscription, $encodedPayload);
        } catch (Throwable $e) {
            $report[] = [
                'endpoint' => $subData['endpoint'] ?? '',
                'success' => false,
                'reason' => $e->getMessage()
            ];
        }
    }

    foreach ($webPush->flush() as $result) {
        $endpoint = $result->getRequest()->getUri()->__toString();
        $success = $result->isSuccess();

        $report[] = [
            'endpoint' => $endpoint,
            'success' => $success,
            'reason' => $success ? 'Notification envoyee' : $result->getReason()
        ];

        $response = $result->getResponse();
        $statusCode = $response ? $response->getStatusCode() : null;
        if ($success || !in_array($statusCode, [404, 410], true)) {
            foreach ($subs as $sub) {
                if (($sub['endpoint'] ?? '') === $endpoint) {
                    $keptSubs[] = $sub;
                    break;
                }
            }
        }
    }

    ei_write_json_file($subsFile, array_values($keptSubs));

    return [
        'status' => 'sent',
        'count' => count($report),
        'report' => $report
    ];
}

function ei_send_offer_notification(array $offer, int $sendNumber = 1): array {
    $id = (string)($offer['id'] ?? '');
    if ($sendNumber <= 1 && ei_offer_notification_already_sent($id)) {
        return ['status' => 'already_sent', 'count' => 0, 'report' => []];
    }
    $title = (string)($offer['titre'] ?? 'Nouvelle offre');
    $body = trim(strip_tags((string)($offer['notice'] ?? $offer['texte'] ?? 'Une nouvelle offre est disponible.')));
    $body = preg_replace('/\s+/u', ' ', $body);
    if (function_exists('mb_substr')) {
        $body = mb_substr($body, 0, 130);
    } else {
        $body = substr($body, 0, 130);
    }
    if ($sendNumber > 1) {
        $title = 'Rappel : ' . $title;
    }

    return ei_send_push_payload([
        'title' => $title,
        'body' => $body,
        'url' => ei_offer_details_url($id),
        'image' => !empty($offer['banniere']) ? ei_absolute_url((string)$offer['banniere']) : null,
        'icon' => 'https://emploi-info.page.gd/logo.png',
        'badge' => 'https://emploi-info.page.gd/logo.png',
        'id' => $id,
        'type' => 'annonce',
        'sendNumber' => $sendNumber
    ]);
}

function ei_queue_offer_reminder(array $offer, int $delaySeconds = 600): void {
    return;
    $id = (string)($offer['id'] ?? '');
    if ($id === '') return;

    $queueFile = __DIR__ . '/notification_queue.json';
    $queue = ei_read_json_file($queueFile);
    $queue = array_values(array_filter($queue, function($row) use ($id) {
        return ($row['offer_id'] ?? '') !== $id || !empty($row['second_sent']);
    }));

    $queue[] = [
        'offer_id' => $id,
        'created_at' => date('c'),
        'send_after' => time() + $delaySeconds,
        'first_sent' => true,
        'second_sent' => false
    ];

    ei_write_json_file($queueFile, $queue);
}
?>
