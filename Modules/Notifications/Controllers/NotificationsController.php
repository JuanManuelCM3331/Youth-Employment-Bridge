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
}
