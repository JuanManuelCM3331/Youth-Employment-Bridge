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
}
