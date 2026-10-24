<?php

namespace Modules\Notifications\Repositories;

use App\Core\Database;
use PDO;

final class NotificationRepository
{
    public function forUser(int $userId): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM notifications WHERE user_id=:user ORDER BY created_at DESC');
        $statement->execute(['user' => $userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(int $userId, string $message, ?string $title = null, ?string $url = null): int
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'INSERT INTO notifications (user_id, message, title, url) VALUES (:user, :message, :title, :url)'
        );
        $statement->execute([
            'user' => $userId,
            'message' => $message,
            'title' => $title,
            'url' => $url,
        ]);

        return (int) $pdo->lastInsertId();
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

    public function unreadSince(int $userId, int $sinceId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM notifications WHERE user_id = :user AND id > :since AND read_at IS NULL ORDER BY id ASC'
        );
        $statement->execute(['user' => $userId, 'since' => $sinceId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
