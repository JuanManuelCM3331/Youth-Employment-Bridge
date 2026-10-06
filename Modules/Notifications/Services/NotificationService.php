<?php

namespace Modules\Notifications\Services;

use Modules\Notifications\Repositories\NotificationRepository;

final class NotificationService
{
    public function __construct(private NotificationRepository $repository)
    {
    }

    public function notify(int $userId, string $message): void
    {
        $this->repository->create($userId, $message);
    }

    public function forUser(int $userId): array
    {
        return $this->repository->forUser($userId);
    }
}
