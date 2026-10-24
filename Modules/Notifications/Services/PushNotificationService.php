<?php

declare(strict_types=1);

namespace Modules\Notifications\Services;

use Modules\Auditoria\Services\AuditService;
use Modules\Notifications\Repositories\PushSubscriptionRepository;

class PushNotificationService
{
    public function __construct(
        private PushSubscriptionRepository $subscriptions,
        private ?AuditService $audit = null
    ) {
    }

    /**
     * Envía una notificación push a todos los dispositivos suscritos de un usuario.
     *
     * @return array{
     *     success: bool,
     *     subscriptions_count: int,
     *     payload: array<string, mixed>
     * }
     */
    public function sendPush(
        int $userId,
        string $message,
        ?string $title = 'Youth Employment Bridge',
        ?string $url = null
    ): array {
        $title = $title ?: 'Youth Employment Bridge';
        $url = $url ?: './?action=alerts';

        $payload = [
            'title' => $title,
            'body' => $message,
            'message' => $message,
            'url' => $url,
            'icon' => './assets/icons/notification-icon.png',
            'badge' => './assets/icons/notification-badge.png',
            'tag' => 'yeb-notification-' . $userId . '-' . time(),
            'timestamp' => (int) round(microtime(true) * 1000),
        ];

        $subs = $this->subscriptions->findByUser($userId);
        $sentCount = 0;

        foreach ($subs as $sub) {
            $endpoint = (string) ($sub['endpoint'] ?? '');
            if ($endpoint === '') {
                continue;
            }

            // Intenta enviar Web Push al endpoint registrado
            $sent = $this->dispatchToEndpoint($endpoint, $sub['p256dh'] ?? null, $sub['auth'] ?? null, $payload);
            if ($sent) {
                $sentCount++;
            }
        }

        $this->audit?->log(
            'push_notification_dispatched',
            'Notifications',
            'Notificación push enviada a usuario #' . $userId,
            'success',
            $userId,
            [
                'subscriptions' => count($subs),
                'dispatched' => $sentCount,
                'title' => $title,
            ]
        );

        return [
            'success' => true,
            'subscriptions_count' => count($subs),
            'dispatched_count' => $sentCount,
            'payload' => $payload,
        ];
    }

    /**
     * Envía la notificación push a un listado de usuarios.
     *
     * @param list<int> $userIds
     */
    public function sendPushToMany(
        array $userIds,
        string $message,
        ?string $title = 'Youth Employment Bridge',
        ?string $url = null
    ): array {
        $results = [];
        foreach ($userIds as $id) {
            $results[$id] = $this->sendPush((int) $id, $message, $title, $url);
        }

        return $results;
    }

    public function registerSubscription(
        int $userId,
        string $endpoint,
        ?string $p256dh = null,
        ?string $auth = null,
        ?string $userAgent = null,
        ?string $deviceToken = null
    ): void {
        $this->subscriptions->save($userId, $endpoint, $p256dh, $auth, $userAgent, $deviceToken);
    }

    public function unregisterSubscription(int $userId, string $endpoint): void
    {
        $this->subscriptions->delete($userId, $endpoint);
    }

    public function getSubscriptionCount(int $userId): int
    {
        return $this->subscriptions->countForUser($userId);
    }

    /**
     * Envío HTTP directo al servicio push del navegador (FCM, Mozilla Autopush, etc.)
     * Si el endpoint responde con 404 o 410 (Gone), la suscripción caducada se elimina.
     */
    private function dispatchToEndpoint(string $endpoint, ?string $p256dh, ?string $auth, array $payload): bool
    {
        // En entorno local o endpoints simulados, no bloquea
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return false;
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            return false;
        }

        try {
            $ch = curl_init($endpoint);
            if ($ch === false) {
                return false;
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonPayload),
                    'TTL: 86400',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Si el servicio push indica que la suscripción ya no existe (404 o 410 Gone)
            if ($httpCode === 404 || $httpCode === 410) {
                $this->subscriptions->deleteByEndpoint($endpoint);
                return false;
            }

            return $httpCode >= 200 && $httpCode < 300;
        } catch (\Throwable) {
            return false;
        }
    }
}
