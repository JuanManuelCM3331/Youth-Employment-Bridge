<?php

declare(strict_types=1);

namespace Modules\Notifications\Repositories;

use App\Core\Database;
use PDO;

class PushSubscriptionRepository
{
    public function save(
        int $userId,
        string $endpoint,
        ?string $p256dh = null,
        ?string $auth = null,
        ?string $userAgent = null,
        ?string $deviceToken = null
    ): void {
        $pdo = Database::connection();

        // Eliminar suscripción previa con el mismo endpoint o device_token para evitar duplicados
        if ($deviceToken !== null && $deviceToken !== '') {
            $deleteStmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE device_token = :device_token OR (endpoint = :endpoint AND user_id = :user_id)');
            $deleteStmt->execute([
                'device_token' => $deviceToken,
                'endpoint' => $endpoint,
                'user_id' => $userId,
            ]);
        } else {
            $deleteStmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = :endpoint AND user_id = :user_id');
            $deleteStmt->execute([
                'endpoint' => $endpoint,
                'user_id' => $userId,
            ]);
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO push_subscriptions (user_id, device_token, endpoint, p256dh, auth, user_agent)
             VALUES (:user_id, :device_token, :endpoint, :p256dh, :auth, :user_agent)'
        );
        $insertStmt->execute([
            'user_id' => $userId,
            'device_token' => $deviceToken !== null && $deviceToken !== '' ? substr($deviceToken, 0, 100) : null,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'user_agent' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
        ]);
    }

    public function findUserIdByDeviceToken(string $deviceToken): ?int
    {
        $deviceToken = trim($deviceToken);
        if ($deviceToken === '') {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT user_id FROM push_subscriptions WHERE device_token = :device_token ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['device_token' => $deviceToken]);
        $val = $statement->fetchColumn();

        return $val !== false ? (int) $val : null;
    }

    public function delete(int $userId, string $endpoint): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM push_subscriptions WHERE user_id = :user_id AND endpoint = :endpoint'
        );
        $statement->execute([
            'user_id' => $userId,
            'endpoint' => $endpoint,
        ]);
    }

    public function deleteByDeviceToken(string $deviceToken): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM push_subscriptions WHERE device_token = :device_token'
        );
        $statement->execute(['device_token' => $deviceToken]);
    }

    public function deleteByEndpoint(string $endpoint): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM push_subscriptions WHERE endpoint = :endpoint'
        );
        $statement->execute(['endpoint' => $endpoint]);
    }

    public function findByUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM push_subscriptions WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countForUser(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM push_subscriptions WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }
}
