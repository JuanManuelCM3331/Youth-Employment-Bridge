<?php

namespace Modules\Chat\Repositories;

use App\Core\Database;
use PDO;
use PDOException;

class ChatRepository
{
    private function ensureTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        try {
            Database::connection()->exec('
                CREATE TABLE IF NOT EXISTS messages (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    application_id INT UNSIGNED NOT NULL,
                    sender_id INT UNSIGNED NOT NULL,
                    message TEXT NOT NULL,
                    read_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_messages_app (application_id),
                    INDEX idx_messages_sender (sender_id),
                    INDEX idx_messages_created (created_at),
                    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
                    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
        } catch (\Throwable) {
            // Ignorar errores si no se tienen permisos DDL o si ya existe
        }
    }

    public function sendMessage(int $applicationId, int $senderId, string $message): int
    {
        $this->ensureTable();
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'INSERT INTO messages (application_id, sender_id, message) VALUES (:application_id, :sender_id, :message)'
        );
        $statement->execute([
            'application_id' => $applicationId,
            'sender_id' => $senderId,
            'message' => $message,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function getMessages(int $applicationId): array
    {
        $this->ensureTable();
        try {
            $statement = Database::connection()->prepare(
                'SELECT m.id, m.application_id, m.sender_id, m.message, m.read_at, m.created_at,
                        u.name AS sender_name, u.role AS sender_role, u.profile_photo_path AS sender_photo
                 FROM messages m
                 JOIN users u ON u.id = m.sender_id
                 WHERE m.application_id = :application_id
                 ORDER BY m.created_at ASC, m.id ASC'
            );
            $statement->execute(['application_id' => $applicationId]);

            return $statement->fetchAll();
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    public function markAsRead(int $applicationId, int $currentUserId): void
    {
        $this->ensureTable();
        try {
            $statement = Database::connection()->prepare(
                'UPDATE messages
                 SET read_at = CURRENT_TIMESTAMP
                 WHERE application_id = :application_id
                   AND sender_id != :current_user_id
                   AND read_at IS NULL'
            );
            $statement->execute([
                'application_id' => $applicationId,
                'current_user_id' => $currentUserId,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return;
            }
            throw $e;
        }
    }

    public function findApplication(int $applicationId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT a.id, a.job_id, a.user_id AS candidate_id, a.status, a.created_at,
                    j.title AS job_title, j.company_id, j.city AS job_city,
                    c.name AS company_name, c.city AS company_city,
                    u.name AS candidate_name, u.email AS candidate_email
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             JOIN companies c ON c.id = j.company_id
             JOIN users u ON u.id = a.user_id
             WHERE a.id = :id'
        );
        $statement->execute(['id' => $applicationId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function getConversationsForCandidate(int $userId): array
    {
        $this->ensureTable();
        try {
            $statement = Database::connection()->prepare(
                'SELECT a.id AS application_id, a.status, a.created_at AS applied_at,
                        j.id AS job_id, j.title AS job_title,
                        c.id AS company_id, c.name AS company_name, c.city AS company_city,
                        (SELECT m.message FROM messages m WHERE m.application_id = a.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1) AS last_message,
                        (SELECT m.created_at FROM messages m WHERE m.application_id = a.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1) AS last_message_at,
                        (SELECT COUNT(*) FROM messages m WHERE m.application_id = a.id AND m.sender_id != :exclude_sender_id AND m.read_at IS NULL) AS unread_count
                 FROM applications a
                 JOIN jobs j ON j.id = a.job_id
                 JOIN companies c ON c.id = j.company_id
                 WHERE a.user_id = :candidate_user_id
                   AND a.status NOT IN ("pending", "review")
                 ORDER BY COALESCE(
                     (SELECT m.created_at FROM messages m WHERE m.application_id = a.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1),
                     a.created_at
                 ) DESC'
            );
            $statement->execute([
                'exclude_sender_id' => $userId,
                'candidate_user_id' => $userId,
            ]);

            return $statement->fetchAll();
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                $statement = Database::connection()->prepare(
                    'SELECT a.id AS application_id, a.status, a.created_at AS applied_at,
                            j.id AS job_id, j.title AS job_title,
                            c.id AS company_id, c.name AS company_name, c.city AS company_city,
                            NULL AS last_message, NULL AS last_message_at, 0 AS unread_count
                     FROM applications a
                     JOIN jobs j ON j.id = a.job_id
                     JOIN companies c ON c.id = j.company_id
                     WHERE a.user_id = :user_id
                       AND a.status NOT IN ("pending", "review")
                     ORDER BY a.created_at DESC'
                );
                $statement->execute(['user_id' => $userId]);
                return $statement->fetchAll();
            }
            throw $e;
        }
    }

    public function getConversationsForCompany(int $companyId, int $recruiterUserId): array
    {
        $this->ensureTable();
        try {
            $statement = Database::connection()->prepare(
                'SELECT a.id AS application_id, a.status, a.created_at AS applied_at,
                        j.id AS job_id, j.title AS job_title,
                        u.id AS candidate_id, u.name AS candidate_name, u.email AS candidate_email,
                        (SELECT m.message FROM messages m WHERE m.application_id = a.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1) AS last_message,
                        (SELECT m.created_at FROM messages m WHERE m.application_id = a.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1) AS last_message_at,
                        (SELECT COUNT(*) FROM messages m WHERE m.application_id = a.id AND m.sender_id != :recruiter_user_id AND m.read_at IS NULL) AS unread_count
                 FROM applications a
                 JOIN jobs j ON j.id = a.job_id
                 JOIN users u ON u.id = a.user_id
                 WHERE j.company_id = :company_id
                   AND a.status NOT IN ("pending", "review")
                 ORDER BY COALESCE(
                     (SELECT m.created_at FROM messages m WHERE m.application_id = a.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1),
                     a.created_at
                 ) DESC'
            );
            $statement->execute([
                'recruiter_user_id' => $recruiterUserId,
                'company_id' => $companyId,
            ]);

            return $statement->fetchAll();
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                $statement = Database::connection()->prepare(
                    'SELECT a.id AS application_id, a.status, a.created_at AS applied_at,
                            j.id AS job_id, j.title AS job_title,
                            u.id AS candidate_id, u.name AS candidate_name, u.email AS candidate_email,
                            NULL AS last_message, NULL AS last_message_at, 0 AS unread_count
                     FROM applications a
                     JOIN jobs j ON j.id = a.job_id
                     JOIN users u ON u.id = a.user_id
                     WHERE j.company_id = :company_id
                       AND a.status NOT IN ("pending", "review")
                     ORDER BY a.created_at DESC'
                );
                $statement->execute(['company_id' => $companyId]);
                return $statement->fetchAll();
            }
            throw $e;
        }
    }

    public function getCompanyRecruiterUserIds(int $companyId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id FROM users WHERE company_id = :company_id AND role = "recruiter"'
        );
        $statement->execute(['company_id' => $companyId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function unreadCountForUser(array $user): int
    {
        $this->ensureTable();
        try {
            if (($user['role'] ?? '') === 'candidate') {
                $statement = Database::connection()->prepare(
                    'SELECT COUNT(*) FROM messages m
                     JOIN applications a ON a.id = m.application_id
                     WHERE a.user_id = :candidate_id
                       AND a.status NOT IN ("pending", "review")
                       AND m.sender_id != :exclude_my_id
                       AND m.read_at IS NULL'
                );
                $statement->execute([
                    'candidate_id' => $user['id'],
                    'exclude_my_id' => $user['id'],
                ]);
                return (int) $statement->fetchColumn();
            } elseif (($user['role'] ?? '') === 'recruiter' && !empty($user['company_id'])) {
                $statement = Database::connection()->prepare(
                    'SELECT COUNT(*) FROM messages m
                     JOIN applications a ON a.id = m.application_id
                     JOIN jobs j ON j.id = a.job_id
                     WHERE j.company_id = :company_id
                       AND a.status NOT IN ("pending", "review")
                       AND m.sender_id != :recruiter_id
                       AND m.read_at IS NULL'
                );
                $statement->execute([
                    'company_id' => $user['company_id'],
                    'recruiter_id' => $user['id'],
                ]);
                return (int) $statement->fetchColumn();
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return 0;
            }
            throw $e;
        }

        return 0;
    }
}
