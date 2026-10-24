<?php

namespace Modules\Notifications\Services;

use Modules\Notifications\Repositories\NotificationRepository;

final class NotificationService
{
    public function __construct(
        private NotificationRepository $repository,
        private ?PushNotificationService $pushService = null
    ) {
    }

    public function forUser(int $userId): array
    {
        return $this->repository->forUser($userId);
    }

    public function create(int $userId, string $message, ?string $title = null, ?string $url = null): void
    {
        try {
            $this->repository->create($userId, $message, $title, $url);
        } catch (\ArgumentCountError) {
            $this->repository->create($userId, $message);
        }

        if ($this->pushService !== null) {
            try {
                $this->pushService->sendPush($userId, $message, $title, $url);
            } catch (\Throwable) {
                // Silencioso para garantizar resiliencia
            }
        }
    }

    public function markAsRead(int $id, int $userId): void
    {
        $this->repository->markAsRead($id, $userId);
    }

    public function markAllAsRead(int $userId): void
    {
        $this->repository->markAllAsRead($userId);
    }

    public function unreadCount(int $userId): int
    {
        return $this->repository->unreadCount($userId);
    }

    public function unreadSince(int $userId, int $sinceId): array
    {
        if (method_exists($this->repository, 'unreadSince')) {
            return $this->repository->unreadSince($userId, $sinceId);
        }
        return [];
    }

    public function getPushService(): ?PushNotificationService
    {
        return $this->pushService;
    }
}
