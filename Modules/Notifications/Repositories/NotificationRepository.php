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

    public function markAsRead(int $id, int $userId): void
    {
        Database::connection()->prepare(
            'UPDATE notifications SET read_at=NOW() WHERE id=:id AND user_id=:user AND read_at IS NULL'
        )->execute(['id' => $id, 'user' => $userId]);
    }

    public function markAllAsRead(int $userId): void
    {
        Database::connection()->prepare(
            'UPDATE notifications SET read_at=NOW() WHERE user_id=:user AND read_at IS NULL'
        )->execute(['user' => $userId]);
    }

    public function unreadCount(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id=:user AND read_at IS NULL'
        );
        $statement->execute(['user' => $userId]);
        return (int) $statement->fetchColumn();
    }
}
