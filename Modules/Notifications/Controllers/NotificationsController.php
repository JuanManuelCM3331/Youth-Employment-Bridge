<?php

namespace Modules\Notifications\Controllers;

use Modules\Notifications\Services\NotificationService;

final class NotificationsController
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function forUser(int $userId): array
    {
        return $this->notifications->forUser($userId);
    }

    public function create(int $userId, string $message): void
    {
        $this->notifications->create($userId, $message);
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
}
