<?php

namespace Modules\Notifications\Services;

use Modules\Notifications\Repositories\NotificationRepository;

final class NotificationService
{
    public function __construct(private NotificationRepository $repository)
    {
    }

    public function forUser(int $userId): array
    {
        return $this->repository->forUser($userId);
    }

    public function create(int $userId, string $message): void
    {
        $this->repository->create($userId, $message);
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
}
