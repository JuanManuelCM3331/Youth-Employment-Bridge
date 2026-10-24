<?php

namespace Modules\Notifications\Controllers;

use Modules\Notifications\Services\NotificationService;
use Modules\Notifications\Services\PushNotificationService;

final class NotificationsController
{
    public function __construct(
        private NotificationService $notifications,
        private ?PushNotificationService $pushService = null
    ) {
        if ($this->pushService === null) {
            $this->pushService = $this->notifications->getPushService();
        }
    }

    public function forUser(int $userId): array
    {
        return $this->notifications->forUser($userId);
    }

    public function create(int $userId, string $message, ?string $title = null, ?string $url = null): void
    {
        $this->notifications->create($userId, $message, $title, $url);
    }

    public function markAsRead(int $id, int $userId): void
    {
        $this->notifications->markAsRead($id, $userId);
    }

    public function markAllAsRead(int $userId): void
    {
        $this->notifications->markAllAsRead($userId);
    }

    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }

    public function unreadSince(int $userId, int $sinceId): array
    {
        return $this->notifications->unreadSince($userId, $sinceId);
    }

    /**
     * Registra una nueva suscripción push del navegador.
     */
    public function subscribe(int $userId, array $data): array
    {
        $endpoint = trim((string) ($data['endpoint'] ?? ''));
        if ($endpoint === '') {
            return ['success' => false, 'error' => 'Endpoint requerido'];
        }

        $keys = is_array($data['keys'] ?? null) ? $data['keys'] : [];
        $p256dh = $keys['p256dh'] ?? ($data['p256dh'] ?? null);
        $auth = $keys['auth'] ?? ($data['auth'] ?? null);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $deviceToken = trim((string) ($data['device_token'] ?? ""));

        $this->pushService?->registerSubscription(
            $userId,
            $endpoint,
            $p256dh !== null ? (string) $p256dh : null,
            $auth !== null ? (string) $auth : null,
            $userAgent,
            $deviceToken !== "" ? $deviceToken : null
        );

        return [
            'success' => true,
            'message' => 'Suscripción push registrada correctamente.',
            'active_subscriptions' => $this->pushService?->getSubscriptionCount($userId) ?? 1,
        ];
    }

    /**
     * Cancela la suscripción push para un endpoint específico.
     */
    public function unsubscribe(int $userId, array $data): array
    {
        $endpoint = trim((string) ($data['endpoint'] ?? ''));
        if ($endpoint !== '') {
            $this->pushService?->unregisterSubscription($userId, $endpoint);
        }

        return ['success' => true, 'message' => 'Suscripción cancelada.'];
    }

    /**
     * Obtiene en tiempo real las nuevas alertas no leídas y contadores actualizados.
     */
    public function poll(int $userId, int $lastId, int $chatUnreadCount = 0): array
    {
        $newNotifications = $this->notifications->unreadSince($userId, $lastId);
        $unreadCount = $this->notifications->unreadCount($userId);

        return [
            'success' => true,
            'notifications' => $newNotifications,
            'unread_count' => $unreadCount,
            'chat_unread_count' => $chatUnreadCount,
            'has_new' => !empty($newNotifications),
        ];
    }

    /**
     * Genera una notificación push de prueba para validar que el navegador la recibe.
     */
    public function testPush(int $userId): array
    {
        $title = 'Prueba de Notificación Push';
        $message = '¡Excelente! Las notificaciones push de Youth Employment Bridge están funcionando en tiempo real.';
        $url = './?action=alerts';

        $this->notifications->create($userId, $message, $title, $url);

        return [
            'success' => true,
            'message' => 'Notificación de prueba enviada.',
            'title' => $title,
            'body' => $message,
            'url' => $url,
        ];
    }

    public function subscriptionCount(int $userId): int
    {
        return $this->pushService?->getSubscriptionCount($userId) ?? 0;
    }
}
