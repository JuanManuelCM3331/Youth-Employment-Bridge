<?php

namespace Modules\Applications\Repositories;

use App\Core\Database;

final class ApplicationRepository
{
    public function apply(int $jobId, int $userId): void
    {
        Database::connection()->prepare('INSERT INTO applications (job_id,user_id) VALUES (:job,:user)')->execute(['job' => $jobId, 'user' => $userId]);
    }

    public function updateStatus(int $applicationId, int $companyId, string $status): void
    {
        Database::connection()->prepare('UPDATE applications a JOIN jobs j ON j.id=a.job_id SET a.status=:status WHERE a.id=:application AND j.company_id=:company')->execute(['status' => $status, 'application' => $applicationId, 'company' => $companyId]);
    }

    public function findByCompany(int $companyId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT applications.id, applications.status, applications.created_at,
                    jobs.title, users.id AS candidate_id, users.name AS candidate_name,
                    users.email, users.cv_original_name
             FROM applications
             JOIN jobs ON jobs.id = applications.job_id
             JOIN users ON users.id = applications.user_id
             WHERE jobs.company_id = :company
             ORDER BY applications.created_at DESC'
        );
        $statement->execute(['company' => $companyId]);
        return $statement->fetchAll();
    }

    public function findByUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT applications.*, jobs.title, jobs.city,
                    companies.name AS company_name
             FROM applications
             JOIN jobs ON jobs.id = applications.job_id
             JOIN companies ON companies.id = jobs.company_id
             WHERE applications.user_id = :user
             ORDER BY applications.created_at DESC'
        );
        $statement->execute(['user' => $userId]);
        return $statement->fetchAll();
    }

    public function countByStatus(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT status, COUNT(*) AS total
             FROM applications
             WHERE user_id = :user
             GROUP BY status'
        );
        $statement->execute(['user' => $userId]);
        return $statement->fetchAll();
    }
}
