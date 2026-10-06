<?php

namespace Modules\Notifications\Repositories;

use App\Core\Database;

final class NotificationRepository
{
    public function forUser(int $userId): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM notifications WHERE user_id=:user ORDER BY created_at DESC');
        $statement->execute(['user' => $userId]);
        return $statement->fetchAll();
    }

    public function create(int $userId, string $message): void
    {
        Database::connection()->prepare('INSERT INTO notifications (user_id,message) VALUES (:user,:message)')->execute(['user' => $userId, 'message' => $message]);
    }
}
