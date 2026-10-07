<?php

declare(strict_types=1);

namespace Modules\Search\Repositories;

use App\Core\Database;

final class JobInteractionRepository
{
    public function isSaved(int $userId, int $jobId): bool
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM saved_jobs WHERE user_id=:user AND job_id=:job');
        $statement->execute(['user' => $userId, 'job' => $jobId]);
        return (int) $statement->fetchColumn() > 0;
    }

    public function toggleSaved(int $userId, int $jobId): void
    {
        if ($this->isSaved($userId, $jobId)) {
            Database::connection()->prepare('DELETE FROM saved_jobs WHERE user_id=:user AND job_id=:job')->execute([
                'user' => $userId,
                'job' => $jobId,
            ]);
            return;
        }

        Database::connection()->prepare('INSERT INTO saved_jobs (user_id,job_id) VALUES (:user,:job)')->execute([
            'user' => $userId,
            'job' => $jobId,
        ]);
    }

    public function hide(int $userId, int $jobId): void
    {
        Database::connection()->prepare('INSERT IGNORE INTO hidden_jobs (user_id,job_id) VALUES (:user,:job)')->execute([
            'user' => $userId,
            'job' => $jobId,
        ]);
    }

    public function hiddenJobIds(int $userId): array
    {
        $statement = Database::connection()->prepare('SELECT job_id FROM hidden_jobs WHERE user_id=:user');
        $statement->execute(['user' => $userId]);
        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function savedJobIds(int $userId): array
    {
        $statement = Database::connection()->prepare('SELECT job_id FROM saved_jobs WHERE user_id=:user');
        $statement->execute(['user' => $userId]);
        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function savedJobs(int $userId): array
    {
        $statement = Database::connection()->prepare('SELECT jobs.*,companies.name company_name FROM saved_jobs JOIN jobs ON jobs.id=saved_jobs.job_id JOIN companies ON companies.id=jobs.company_id WHERE saved_jobs.user_id=:id ORDER BY saved_jobs.created_at DESC');
        $statement->execute(['id' => $userId]);
        return $statement->fetchAll();
    }
}
